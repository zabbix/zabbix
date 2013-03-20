/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include <stdio.h>
#include <stdlib.h>

#include "common.h"
#include "nodehistory.h"
#include "db.h"
#include "log.h"
#include "zbxalgo.h"

#include "../events.h"
#include "../nodewatcher/nodecomms.h"
#include "../nodewatcher/nodewatcher.h"

static char	*buffer = NULL, *tmp = NULL;
static size_t	buffer_alloc, tmp_alloc;

/******************************************************************************
 *                                                                            *
 * Function: send_history_last_id                                             *
 *                                                                            *
 * Purpose: send list of last historical tables IDs                           *
 *                                                                            *
 * Parameters: sock - opened socket of node-node connection                   *
 *             record                                                         *
 *                                                                            *
 * Return value:  SUCCEED - sent successfully                                 *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	send_history_last_id(zbx_sock_t *sock, const char *data)
{
	DB_RESULT	result;
	DB_ROW		row;
	const char	*r;
	const ZBX_TABLE	*table;
	size_t		buffer_offset;
	int		sender_nodeid = (-1), nodeid = (-1), res;

	zabbix_log(LOG_LEVEL_DEBUG, "In send_history_last_id()");

	buffer_alloc = 320;
	buffer = zbx_malloc(buffer, buffer_alloc);

	r = data;
	if (NULL == r)
		goto error;

	zbx_get_next_field(&r, &buffer, &buffer_alloc, ZBX_DM_DELIMITER); /* constant 'ZBX_GET_HISTORY_LAST_ID' */
	if (NULL == r)
		goto error;

	zbx_get_next_field(&r, &buffer, &buffer_alloc, ZBX_DM_DELIMITER); /* sender_nodeid */
	sender_nodeid = atoi(buffer);
	if (NULL == r)
		goto error;

	if (FAIL == is_direct_slave_node(sender_nodeid))
	{
		zabbix_log(LOG_LEVEL_ERR, "NODE %d: Received data from node %d that is not a direct slave node [%s]",
				CONFIG_NODEID, sender_nodeid, data);
		goto fail;
	}

	zbx_get_next_field(&r, &buffer, &buffer_alloc, '\n'); /* nodeid */
	nodeid = atoi(buffer);
	if (NULL == r)
		goto error;

	if (FAIL == is_slave_node(CONFIG_NODEID, nodeid))
	{
		zabbix_log(LOG_LEVEL_ERR, "NODE %d: Received data for unknown slave node %d [%s]",
				CONFIG_NODEID, nodeid, data);
		goto fail;
	}

	zbx_get_next_field(&r, &buffer, &buffer_alloc, ZBX_DM_DELIMITER); /* table name */
	if (NULL == (table = DBget_table(buffer)))
		goto error;

	if (0 == (table->flags & ZBX_HISTORY))
		goto error;

	if (NULL == r)
		goto error;

	zbx_get_next_field(&r, &buffer, &buffer_alloc, ZBX_DM_DELIMITER); /* field name */
	if (0 != strcmp(buffer, table->recid))
		goto error;

	buffer_offset= 0;
	zbx_snprintf_alloc(&buffer, &buffer_alloc, &buffer_offset,
			"select max(%s)"
			" from %s"
			" where 1=1" DB_NODE,
			table->recid,
			table->table,
			DBnode(table->recid, nodeid));

	buffer_offset= 0;
	result = DBselect("%s", buffer);
	if (NULL != (row = DBfetch(result)))
		zbx_strcpy_alloc(&buffer, &buffer_alloc, &buffer_offset, SUCCEED == DBis_null(row[0]) ? "0" : row[0]);
	DBfree_result(result);

	if (buffer_offset == 0)
		goto error;

	alarm(CONFIG_TIMEOUT);
	res = send_data_to_node(sender_nodeid, sock, buffer);
	alarm(0);

	zbx_free(buffer);

	return res;
error:
	zabbix_log(LOG_LEVEL_ERR, "NODE %d: Received invalid record from node %d for node %d [%s]",
		CONFIG_NODEID, sender_nodeid, nodeid, data);
fail:
	buffer_offset= 0;
	zbx_strcpy_alloc(&buffer, &buffer_alloc, &buffer_offset, "FAIL");

	alarm(CONFIG_TIMEOUT);
	res = send_data_to_node(sender_nodeid, sock, buffer);
	alarm(0);

	zbx_free(buffer);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: process_record_event                                             *
 *                                                                            *
 * Purpose: process record update                                             *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
static int	process_record_event(int sender_nodeid, int nodeid, const ZBX_TABLE *table, const char *record)
{
	const char	*r;
	int		f, source = 0, object = 0, value = 0, acknowledged = 0;
	unsigned char	value_changed = 0;
	zbx_uint64_t	eventid = 0, objectid = 0;
	zbx_timespec_t	ts;

	zabbix_log(LOG_LEVEL_DEBUG, "In process_record_event()");

	ts.sec = 0;
	ts.ns = 0;
	r = record;

	for (f = 0; NULL != table->fields[f].name; f++)
	{
		if (NULL == r)
			goto error;

		zbx_get_next_field(&r, &buffer, &buffer_alloc, ZBX_DM_DELIMITER);

		if (0 == strcmp(table->fields[f].name, "eventid"))
			ZBX_STR2UINT64(eventid, buffer);
		else if (0 == strcmp(table->fields[f].name, "source"))
			source = atoi(buffer);
		else if (0 == strcmp(table->fields[f].name, "object"))
			object = atoi(buffer);
		else if (0 == strcmp(table->fields[f].name, "objectid"))
			ZBX_STR2UINT64(objectid, buffer);
		else if (0 == strcmp(table->fields[f].name, "clock"))
			ts.sec = atoi(buffer);
		else if (0 == strcmp(table->fields[f].name, "ns"))
			ts.ns = atoi(buffer);
		else if (0 == strcmp(table->fields[f].name, "value"))
			value = atoi(buffer);
		else if (0 == strcmp(table->fields[f].name, "value_changed"))
			value_changed = (unsigned char)atoi(buffer);
		else if (0 == strcmp(table->fields[f].name, "acknowledged"))
			acknowledged = atoi(buffer);
	}

	return process_event(eventid, source, object, objectid, &ts, value, value_changed, acknowledged, 0);
error:
	zabbix_log(LOG_LEVEL_ERR, "NODE %d: received invalid record from node %d for node %d [%s]",
			CONFIG_NODEID, sender_nodeid, nodeid, record);

	return FAIL;
}

static void	begin_history_sql(char **sql, size_t *sql_alloc, size_t *sql_offset, const ZBX_TABLE *table)
{
	int	f;

	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "insert into %s (", table->table);

	if (0 != (table->flags & ZBX_HISTORY_SYNC))
		zbx_strcpy_alloc(sql, sql_alloc, sql_offset, "nodeid,");

	for (f = 0; table->fields[f].name != 0; f++)
	{
		if (0 != (table->flags & ZBX_HISTORY_SYNC) && 0 == (table->fields[f].flags & ZBX_HISTORY_SYNC))
			continue;

		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s,", table->fields[f].name);
	}

	(*sql_offset)--;
	zbx_strcpy_alloc(sql, sql_alloc, sql_offset, ") values ");
}

/******************************************************************************
 *                                                                            *
 * Function: process_record                                                   *
 *                                                                            *
 * Purpose: process record update                                             *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	process_record(char **sql, size_t *sql_alloc, size_t *sql_offset, int sender_nodeid, int nodeid,
		const ZBX_TABLE *table, const char *record, int lastrecord, int acknowledges,
		zbx_vector_uint64_t *ack_eventids)
{
	const char	*r;
	int		f, res = FAIL;
	char		*value_esc;

	zabbix_log(LOG_LEVEL_DEBUG, "In process_record()");

	if (0 == *sql_offset)
	{
		DBbegin_multiple_update(sql, sql_alloc, sql_offset);
#ifdef HAVE_MULTIROW_INSERT
		begin_history_sql(sql, sql_alloc, sql_offset, table);
#endif
	}

#if !defined(HAVE_MULTIROW_INSERT)
	begin_history_sql(sql, sql_alloc, sql_offset, table);
#endif

	zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, '(');
	if (0 != (table->flags & ZBX_HISTORY_SYNC))
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%d,", nodeid);

	for (r = record, f = 0; table->fields[f].name != 0; f++)
	{
		if (0 != (table->flags & ZBX_HISTORY_SYNC) && 0 == (table->fields[f].flags & ZBX_HISTORY_SYNC))
			continue;

		if (NULL == r)
			goto error;

		zbx_get_next_field(&r, &buffer, &buffer_alloc, ZBX_DM_DELIMITER);

		if (0 != acknowledges && 0 == strcmp(table->fields[f].name, "eventid"))
		{
			zbx_uint64_t	eventid;

			ZBX_STR2UINT64(eventid, buffer);
			zbx_vector_uint64_append(ack_eventids, eventid);
		}

		if (table->fields[f].type == ZBX_TYPE_INT ||
				table->fields[f].type == ZBX_TYPE_UINT ||
				table->fields[f].type == ZBX_TYPE_ID ||
				table->fields[f].type == ZBX_TYPE_FLOAT)
		{
			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s,", buffer);
		}
		else if (table->fields[f].type == ZBX_TYPE_BLOB)
		{
			if ('\0' == *buffer)
				zbx_strcpy_alloc(sql, sql_alloc, sql_offset, "'',");
			else
			{
#ifdef HAVE_POSTGRESQL
				size_t	len;

				len = zbx_hex2binary(buffer);
				zbx_pg_escape_bytea((u_char *)buffer, len, &tmp, &tmp_alloc);
				zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "'%s',", tmp);
#else
				zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "0x%s,", buffer);
#endif
			}
		}
		else	/* ZBX_TYPE_TEXT, ZBX_TYPE_CHAR */
		{
			zbx_hex2binary(buffer);
			value_esc = DBdyn_escape_string(buffer);

			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "'%s',", value_esc);

			zbx_free(value_esc);
		}
	}

	(*sql_offset)--;

#ifdef HAVE_MULTIROW_INSERT
	zbx_strcpy_alloc(sql, sql_alloc, sql_offset, "),");
#else
	zbx_strcpy_alloc(sql, sql_alloc, sql_offset, ");\n");
#endif

	if (0 != lastrecord || *sql_offset > ZBX_MAX_SQL_SIZE)
	{
#ifdef HAVE_MULTIROW_INSERT
		(*sql_offset)--;
		zbx_strcpy_alloc(sql, sql_alloc, sql_offset, ";\n");
#endif
		DBend_multiple_update(sql, sql_alloc, sql_offset);

		if (ZBX_DB_OK <= DBexecute("%s", *sql))
			res = SUCCEED;
		*sql_offset = 0;

		if (SUCCEED == res && 0 != lastrecord && 0 != acknowledges && 0 != ack_eventids->values_num)
		{
			zbx_vector_uint64_sort(ack_eventids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
			zbx_vector_uint64_uniq(ack_eventids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

			zbx_strcpy_alloc(sql, sql_alloc, sql_offset,
					"update events"
					" set acknowledged=1"
					" where");
			DBadd_condition_alloc(sql, sql_alloc, sql_offset,
					"eventid", ack_eventids->values, ack_eventids->values_num);

			if (ZBX_DB_OK > DBexecute("%s", *sql))
				res = FAIL;
			*sql_offset = 0;
		}
	}
	else
		res = SUCCEED;

	return res;
error:
	zabbix_log(LOG_LEVEL_ERR, "NODE %d: Received invalid record from node %d for node %d [%s]",
			CONFIG_NODEID, sender_nodeid, nodeid, record);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: update_items                                                     *
 *                                                                            *
 * Purpose: process record update                                             *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 * Author:                                                                    *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	process_items(char **sql, size_t *sql_alloc, size_t *sql_offset, int sender_nodeid, int nodeid, const ZBX_TABLE *table,
		const char *record, int lastrecord)
{
	const char	*r;
	int		f, res = FAIL;
	zbx_uint64_t	itemid = 0;
	char		*value_esc;
	int		clock = 0, value_type = -1;
	double		value_double = 0;
	zbx_uint64_t	value_uint64 = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In process_items()");

	if (*sql_offset == 0)
		DBbegin_multiple_update(sql, sql_alloc, sql_offset);

	zbx_strcpy_alloc(sql, sql_alloc, sql_offset, "update items set prevvalue=lastvalue");

	for (r = record, f = 0; table->fields[f].name != 0; f++)
	{
		if (0 != (table->flags & ZBX_HISTORY_SYNC) && 0 == (table->fields[f].flags & ZBX_HISTORY_SYNC))
			continue;

		if (NULL == r)
			goto error;

		zbx_get_next_field(&r, &buffer, &buffer_alloc, ZBX_DM_DELIMITER);

		if (0 == strcmp(table->fields[f].name, "itemid"))
			ZBX_STR2UINT64(itemid, buffer);

		if (table->fields[f].type == ZBX_TYPE_INT ||
				table->fields[f].type == ZBX_TYPE_UINT ||
				table->fields[f].type == ZBX_TYPE_ID ||
				table->fields[f].type == ZBX_TYPE_FLOAT)
		{
			if (0 == strcmp(table->fields[f].name, "clock"))
			{
				zbx_snprintf_alloc(sql, sql_alloc, sql_offset, ",lastclock=%s", buffer);
				clock = atoi(buffer);
			}
			else if (0 == strcmp(table->fields[f].name, "value"))
			{
				zbx_snprintf_alloc(sql, sql_alloc, sql_offset, ",lastvalue=%s", buffer);

				value_type = table->fields[f].type;
				if (value_type == ZBX_TYPE_FLOAT)
					value_double = atof(buffer);
				else if (value_type == ZBX_TYPE_UINT)
					ZBX_STR2UINT64(value_uint64, buffer);
			}
		}
		else	/* ZBX_TYPE_TEXT, ZBX_TYPE_CHAR */
		{
			if (0 == strcmp(table->fields[f].name, "value"))
			{
				zbx_hex2binary(buffer);
				value_esc = DBdyn_escape_string_len(buffer, ITEM_LASTVALUE_LEN);
				zbx_snprintf_alloc(sql, sql_alloc, sql_offset, ",lastvalue='%s'", value_esc);
				zbx_free(value_esc);
			}
		}
	}

	if (value_type == ZBX_TYPE_FLOAT)
		DBadd_trend(itemid, value_double, clock);
	else if (value_type == ZBX_TYPE_UINT)
		DBadd_trend_uint(itemid, value_uint64, clock);

	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, " where itemid=" ZBX_FS_UI64 ";\n", itemid);

	if (lastrecord || *sql_offset > ZBX_MAX_SQL_SIZE)
	{
		DBend_multiple_update(sql, sql_alloc, sql_offset);

		if (DBexecute("%s", *sql) >= ZBX_DB_OK)
			res = SUCCEED;
		*sql_offset = 0;
	}
	else
		res = SUCCEED;

	return res;
error:
	zabbix_log(LOG_LEVEL_ERR, "NODE %d: Received invalid record from node %d for node %d [%s]",
		CONFIG_NODEID, sender_nodeid, nodeid, record);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: node_history                                                     *
 *                                                                            *
 * Purpose: process new history received from a slave node                    *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	node_history(char *data, size_t datalen)
{
	const char		*r;
	char			*newline = NULL;
	char			*pos;
	int			sender_nodeid = 0, nodeid = 0, firstline = 1, events = 0, history = 0, acknowledges = 0;
	const ZBX_TABLE		*table_sync = NULL, *table = NULL;
	int			res = SUCCEED;

	char			*sql1 = NULL, *sql2 = NULL, *sql3 = NULL;
	size_t			sql1_alloc, sql2_alloc, sql3_alloc;
	size_t			sql1_offset, sql2_offset, sql3_offset;

	zbx_vector_uint64_t	ack_eventids;

	assert(data);

	zabbix_log(LOG_LEVEL_DEBUG, "In node_history()");

	buffer_alloc = 4 * ZBX_KIBIBYTE;
	sql1_alloc = 32 * ZBX_KIBIBYTE;
	sql2_alloc = 32 * ZBX_KIBIBYTE;
	sql3_alloc = 32 * ZBX_KIBIBYTE;
	tmp_alloc = 4 * ZBX_KIBIBYTE;

	buffer = zbx_malloc(buffer, buffer_alloc);
	sql1 = zbx_malloc(sql1, sql1_alloc);
	sql2 = zbx_malloc(sql2, sql2_alloc);
	sql3 = zbx_malloc(sql3, sql3_alloc);
	tmp = zbx_malloc(tmp, tmp_alloc);

	zbx_vector_uint64_create(&ack_eventids);

	DBbegin();

	for (r = data; *r != '\0' && res == SUCCEED;)
	{
		if (NULL != (newline = strchr(r, '\n')))
			*newline = '\0';

		if (1 == firstline)
		{
			zbx_get_next_field(&r, &buffer, &buffer_alloc, ZBX_DM_DELIMITER); /* constant 'History' */
			zbx_get_next_field(&r, &buffer, &buffer_alloc, ZBX_DM_DELIMITER); /* sender_nodeid */
			sender_nodeid=atoi(buffer);
			zbx_get_next_field(&r, &buffer, &buffer_alloc, ZBX_DM_DELIMITER); /* nodeid */
			nodeid=atoi(buffer);
			zbx_get_next_field(&r, &buffer, &buffer_alloc, ZBX_DM_DELIMITER); /* tablename */

			if (FAIL == is_direct_slave_node(sender_nodeid))
			{
				zabbix_log(LOG_LEVEL_ERR, "NODE %d: Received data from node %d"
							" that is not a direct slave node",
						CONFIG_NODEID, sender_nodeid);
				res = FAIL;
			}

			if (FAIL == is_slave_node(CONFIG_NODEID, nodeid))
			{
				zabbix_log(LOG_LEVEL_ERR, "NODE %d: Received history for unknown slave node %d",
						CONFIG_NODEID, nodeid);
				res = FAIL;
			}

			table = DBget_table(buffer);
			if (NULL != table && 0 == (table->flags & (ZBX_HISTORY | ZBX_HISTORY_SYNC)))
				table = NULL;

			if (NULL != table && 0 != (table->flags & ZBX_HISTORY_SYNC))
			{
				table_sync = table;
				if (NULL != (pos = strstr(buffer, "_sync")))
				{
					*pos = '\0';
					table = DBget_table(buffer);
				}
			}

			if (NULL == table)
			{
				zabbix_log(LOG_LEVEL_ERR, "NODE %d: Invalid received data: unknown tablename \"%s\"",
						CONFIG_NODEID, buffer);
				res = FAIL;
			}
			else
			{
				if (0 == strcmp(table->table, "events"))
					events = 1;

				if (0 == strncmp(table->table, "history", 7))
					history = 1;

				if (0 == strcmp(table->table, "acknowledges"))
					acknowledges = 1;
			}

			if (NULL != newline)
			{
				zabbix_log(LOG_LEVEL_WARNING, "NODE %d: Received %s from node %d for node %d datalen " ZBX_FS_SIZE_T,
						CONFIG_NODEID, buffer, sender_nodeid, nodeid, (zbx_fs_size_t)datalen);
			}
			firstline = 0;
			sql1_offset = 0;
			sql2_offset = 0;
			sql3_offset = 0;
		}
		else if (NULL != table)
		{
			if (events)
			{
				res = process_record_event(sender_nodeid, nodeid, table, r);
			}
			else
			{
				res = process_record(&sql1, &sql1_alloc, &sql1_offset, sender_nodeid,
						nodeid, table, r, newline ? 0 : 1, acknowledges, &ack_eventids);

				if (SUCCEED == res && 0 != history)
				{
					res = process_items(&sql2, &sql2_alloc, &sql2_offset, sender_nodeid,
							nodeid, table, r, newline ? 0 : 1);
				}

				if (SUCCEED == res && NULL != table_sync && 0 != CONFIG_MASTER_NODEID)
				{
					res = process_record(&sql3, &sql3_alloc, &sql3_offset, sender_nodeid,
							nodeid, table_sync, r, newline ? 0 : 1, 0, NULL);
				}
			}
		}

		if (newline != NULL)
		{
			*newline = '\n';
			r = newline + 1;
		}
		else
			break;
	}

	if (SUCCEED == res)
		DBcommit();
	else
		DBrollback();

	zbx_vector_uint64_destroy(&ack_eventids);

	zbx_free(tmp);
	zbx_free(sql1);
	zbx_free(sql2);
	zbx_free(sql3);
	zbx_free(buffer);

	return res;
}
