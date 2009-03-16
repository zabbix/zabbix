/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/


#include <stdio.h>
#include <stdlib.h>

#include "common.h"
#include "db.h"
#include "log.h"

#include "../events.h"
#include "../nodewatcher/nodecomms.h"

static char	*buffer = NULL, *sql = NULL, *sql2 = NULL, *tmp = NULL;
static int	buffer_allocated, sql_allocated, sql2_allocated, tmp_allocated;

/******************************************************************************
 *                                                                            *
 * Function: send_history_last_id                                             *
 *                                                                            *
 * Purpose: send list of last historical tables ids                           *
 *                                                                            *
 * Parameters: sock - opened socket of node-node connection                   *
 *             record                                                         *
 *                                                                            *
 * Return value:  SUCCEED - sent succesfully                                  *
 *                FAIL - an error occured                                     *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	send_history_last_id(zbx_sock_t *sock, const char *data)
{
	DB_RESULT	result;
	DB_ROW		row;
	const char	*r;
	char		tablename[MAX_STRING_LEN], fieldname[MAX_STRING_LEN];
	int		buffer_offset;
	int		sender_nodeid, nodeid, res;

	zabbix_log(LOG_LEVEL_DEBUG, "In send_list_of_history_ids()");

	buffer_allocated = 256;
	buffer = zbx_malloc(buffer, buffer_allocated);

	r = data;
	if (NULL == r)
		goto error;

	zbx_get_next_field(&r, &buffer, &buffer_allocated, ZBX_DM_DELIMITER); /* constant 'ZBX_GET_HISTORY_LAST_ID' */
	if (NULL == r)
		goto error;

	zbx_get_next_field(&r, &buffer, &buffer_allocated, ZBX_DM_DELIMITER); /* sender_nodeid */
	sender_nodeid=atoi(buffer);
	if (NULL == r)
		goto error;

	zbx_get_next_field(&r, &buffer, &buffer_allocated, '\n'); /* nodeid */
	nodeid=atoi(buffer);
	if (NULL == r)
		goto error;

	zbx_get_next_field(&r, &buffer, &buffer_allocated, ZBX_DM_DELIMITER); /* table name */
	zbx_strlcpy(tablename, buffer, sizeof(tablename));

	if (NULL == r)
		goto error;

	zbx_get_next_field(&r, &buffer, &buffer_allocated, ZBX_DM_DELIMITER); /* field name */
	zbx_strlcpy(fieldname, buffer, sizeof(fieldname));

	buffer_offset= 0;
	zbx_snprintf_alloc(&buffer, &buffer_allocated, &buffer_offset, 256, "select MAX(%s) "
		"from %s where 1=1" DB_NODE,
		fieldname,
		tablename,
		DBnode(fieldname, nodeid));

	buffer_offset= 0;
	result = DBselect("%s", buffer);
	if (NULL != (row = DBfetch(result)))
		zbx_snprintf_alloc(&buffer, &buffer_allocated, &buffer_offset, 128, "%s",
			SUCCEED == DBis_null(row[0]) ? "0" : row[0]);
	DBfree_result(result);

	if (buffer_offset == 0)
		goto error;

	res = send_data_to_node(sender_nodeid, sock, buffer);

	zbx_free(buffer);

	return  res;
error:
	buffer_offset= 0;
	zbx_snprintf_alloc(&buffer, &buffer_allocated, &buffer_offset, 128, "FAIL");

	res = send_data_to_node(sender_nodeid, sock, buffer);

	zbx_free(buffer);

	zabbix_log( LOG_LEVEL_ERR, "NODE %d: Received invalid record from node %d for node %d [%s]",
		CONFIG_NODEID,
		sender_nodeid,
		nodeid,
		data);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: send_trends_last_id                                              *
 *                                                                            *
 * Purpose: send last historical tables ids                                   *
 *                                                                            *
 * Parameters: sock - opened socket of node-node connection                   *
 *             record                                                         *
 *                                                                            *
 * Return value:  SUCCEED - sent succesfully                                  *
 *                FAIL - an error occured                                     *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	send_trends_last_id(zbx_sock_t *sock, const char *data)
{
	DB_RESULT	result;
	DB_ROW		row;
	const char	*r;
	char		tablename[MAX_STRING_LEN];
	int		buffer_offset;
	int		sender_nodeid, nodeid, res;

	zabbix_log(LOG_LEVEL_DEBUG, "In send_list_of_history_ids()");


	buffer = zbx_malloc(buffer, buffer_allocated);

	r = data;
	if (NULL == r)
		goto error;

	zbx_get_next_field(&r, &buffer, &buffer_allocated, ZBX_DM_DELIMITER); /* constant 'ZBX_GET_HISTORY_LAST_ID' */
	if (NULL == r)
		goto error;

	zbx_get_next_field(&r, &buffer, &buffer_allocated, ZBX_DM_DELIMITER); /* sender_nodeid */
	sender_nodeid=atoi(buffer);
	if (NULL == r)
		goto error;

	zbx_get_next_field(&r, &buffer, &buffer_allocated, '\n'); /* nodeid */
	nodeid=atoi(buffer);
	if (NULL == r)
		goto error;

	zbx_get_next_field(&r, &buffer, &buffer_allocated, ZBX_DM_DELIMITER); /* table name */
	zbx_strlcpy(tablename, buffer, sizeof(tablename));

	buffer_offset= 0;
	zbx_snprintf_alloc(&buffer, &buffer_allocated, &buffer_offset, 256, "select itemid,clock "
		"from %s where 1=1" DB_NODE " order by itemid desc,clock desc",
		tablename,
		DBnode("itemid", nodeid));

	buffer_offset= 0;
	result = DBselectN(buffer, 1);
	if (NULL == (row = DBfetch(result)))
		zbx_snprintf_alloc(&buffer, &buffer_allocated, &buffer_offset, 64, "0%c0",
			ZBX_DM_DELIMITER);
	else
		zbx_snprintf_alloc(&buffer, &buffer_allocated, &buffer_offset, 128, "%s%c%s",
			SUCCEED == DBis_null(row[0]) ? "0" : row[0],
			ZBX_DM_DELIMITER,
			SUCCEED == DBis_null(row[1]) ? "0" : row[1]);
	DBfree_result(result);

	res = send_data_to_node(sender_nodeid, sock, buffer);

	zbx_free(buffer);

	return  res;
error:
	buffer_offset= 0;
	zbx_snprintf_alloc(&buffer, &buffer_allocated, &buffer_offset, 128, "FAIL");

	res = send_data_to_node(sender_nodeid, sock, buffer);

	zbx_free(buffer);

	zabbix_log( LOG_LEVEL_ERR, "NODE %d: Received invalid record from node %d for node %d [%s]",
		CONFIG_NODEID,
		sender_nodeid,
		nodeid,
		data);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: process_record_event                                             *
 *                                                                            *
 * Purpose: process record update                                             *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occured                                     *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	process_record_event(int sender_nodeid, int nodeid, const ZBX_TABLE *table, const char *record)
{
	const char	*r;
	int		f, len;
	DB_EVENT	event;

	zabbix_log(LOG_LEVEL_DEBUG, "In process_record_event()");

	memset(&event, 0, sizeof(event));

	r = record;
	for (f = 0; table->fields[f].name != 0; f++) {
		if (NULL == r)
			goto error;

		len = zbx_get_next_field(&r, &buffer, &buffer_allocated, ZBX_DM_DELIMITER);

		if (0 == strcmp(table->fields[f].name, "eventid")) {
			ZBX_STR2UINT64(event.eventid, buffer);
		} else if (0 == strcmp(table->fields[f].name, "source")) {
			event.source = atoi(buffer);
		} else if (0 == strcmp(table->fields[f].name, "object")) {
			event.object = atoi(buffer);
		} else if (0 == strcmp(table->fields[f].name, "objectid")) {
			ZBX_STR2UINT64(event.objectid, buffer);
		} else if (0 == strcmp(table->fields[f].name, "clock")) {
			event.clock=atoi(buffer);
		} else if (0 == strcmp(table->fields[f].name, "value")) {
			event.value=atoi(buffer);
		} else if (0 == strcmp(table->fields[f].name, "acknowledged")) {
			event.acknowledged=atoi(buffer);
		}
	}

	return process_event(&event);
error:
	zabbix_log( LOG_LEVEL_ERR, "NODE %d: Received invalid record from node %d for node %d [%s]",
		CONFIG_NODEID,
		sender_nodeid,
		nodeid,
		record);

	return FAIL;
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
 *                FAIL - an error occured                                     *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	process_record(int sender_nodeid, int nodeid, const ZBX_TABLE *table, const char *record)
{
	const char	*r;
	int		f, len, sql_offset = 0, sql2_offset = 0, history = 0;
	int		res = FAIL;
	zbx_uint64_t	itemid = 0;
	char		*value_esc;

	zabbix_log(LOG_LEVEL_DEBUG, "In process_record()");

	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, ZBX_TABLENAME_LEN + 16, "insert into %s (",
			table->table);

	if (0 == strncmp(table->table, "history", 7))
		history = 1;

	if (0 != (table->flags & ZBX_HISTORY_SYNC))
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "nodeid,");

	for (f = 0; table->fields[f].name != 0; f++) {
		if (0 != (table->flags & ZBX_HISTORY_SYNC) && 0 == (table->fields[f].flags & ZBX_HISTORY_SYNC))
			continue;

		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, ZBX_FIELDNAME_LEN + 2, "%s,",
				table->fields[f].name);
	}

	sql_offset--;
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 16, ") values (");

	if (0 != (table->flags & ZBX_HISTORY_SYNC))
		zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 16, "%d,",
				nodeid);

	if (0 != history)
		zbx_snprintf_alloc(&sql2, &sql2_allocated, &sql2_offset, 40, "update items set prevvalue=lastvalue");

	for (r = record, f = 0; table->fields[f].name != 0; f++) {
		if (0 != (table->flags & ZBX_HISTORY_SYNC) && 0 == (table->fields[f].flags & ZBX_HISTORY_SYNC))
			continue;

		if (NULL == r)
			goto error;

		len = zbx_get_next_field(&r, &buffer, &buffer_allocated, ZBX_DM_DELIMITER);

		if (0 == strcmp(table->fields[f].name, "itemid"))
			ZBX_STR2UINT64(itemid, buffer)

		if (table->fields[f].type == ZBX_TYPE_INT ||
				table->fields[f].type == ZBX_TYPE_UINT ||
				table->fields[f].type == ZBX_TYPE_ID ||
				table->fields[f].type == ZBX_TYPE_FLOAT)
		{
			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, len + 2, "%s,",
					buffer);

			if (0 != history)
			{
				if (0 == strcmp(table->fields[f].name, "clock"))
					zbx_snprintf_alloc(&sql2, &sql2_allocated, &sql2_offset, len + 16, ",lastclock=%s",
							buffer);
				else if (0 == strcmp(table->fields[f].name, "value"))
					zbx_snprintf_alloc(&sql2, &sql2_allocated, &sql2_offset, len + 16, ",lastvalue='%s'",
							buffer);
			}
		}
		else if (table->fields[f].type == ZBX_TYPE_BLOB)
		{
#if defined(HAVE_POSTGRESQL)
			len = zbx_hex2binary(buffer);
			len = zbx_pg_escape_bytea((u_char *)buffer, len, &tmp, &tmp_allocated);

			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, len + 4, "'%s',");
#else
			if ('\0' == *buffer)
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, "'',");
			else
				zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, len + 4, "0x%s,",
						buffer);
#endif
		}
		else	/* ZBX_TYPE_TEXT, ZBX_TYPE_CHAR */
		{
			zbx_hex2binary(buffer);
			value_esc = DBdyn_escape_string(buffer);
			len = strlen(value_esc);

			zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, len + 4, "'%s',",
					value_esc);

			zbx_free(value_esc);

			if (0 != history && 0 == strcmp(table->fields[f].name, "value"))
			{
				value_esc = DBdyn_escape_string_len(buffer, ITEM_LASTVALUE_LEN);
				len = strlen(value_esc);

				zbx_snprintf_alloc(&sql2, &sql2_allocated, &sql2_offset, len + 16, ",lastvalue='%s'",
						value_esc);

				zbx_free(value_esc);
			}
		}
	}

	sql_offset--;
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 4, ")");

	if (0 != history && 0 != itemid)
	{
		zbx_snprintf_alloc(&sql2, &sql2_allocated, &sql2_offset, 40, " where itemid=" ZBX_FS_UI64,
				itemid);

		if (DBexecute("%s;\n%s;\n", sql, sql2) >= ZBX_DB_OK)
			res = SUCCEED;
	} else
		if (DBexecute("%s", sql) >= ZBX_DB_OK)
			res = SUCCEED;

	return res;
error:
	zabbix_log( LOG_LEVEL_ERR, "NODE %d: Received invalid record from node %d for node %d [%s]",
		CONFIG_NODEID,
		sender_nodeid,
		nodeid,
		record);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: node_history                                                     *
 *                                                                            *
 * Purpose: process new history received from a salve node                    *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occured                                     *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	node_history(char *data, size_t datalen)
{
	const char	*r;
	char		*newline = NULL;
	char		*pos;
	int		sender_nodeid = 0, nodeid = 0, firstline = 1, events = 0;
	const ZBX_TABLE	*table_sync = NULL, *table = NULL;
	int		res = SUCCEED;

	assert(data);

	zabbix_log(LOG_LEVEL_DEBUG, "In node_history()");

	buffer_allocated = 4096;
	sql_allocated = 4096;
	sql2_allocated = 512;
	tmp_allocated = 4096;

	buffer = zbx_malloc(buffer, buffer_allocated);
	sql = zbx_malloc(sql, sql_allocated);
	sql2 = zbx_malloc(sql2, sql2_allocated);
	tmp = zbx_malloc(tmp, tmp_allocated);

	DBbegin();

	for (r = data; *r != '\0' && res == SUCCEED;) {
		if (NULL != (newline = strchr(r, '\n')))
			*newline = '\0';

		if (1 == firstline) {
			zbx_get_next_field(&r, &buffer, &buffer_allocated, ZBX_DM_DELIMITER); /* constant 'History' */
			zbx_get_next_field(&r, &buffer, &buffer_allocated, ZBX_DM_DELIMITER); /* sender_nodeid */
			sender_nodeid=atoi(buffer);
			zbx_get_next_field(&r, &buffer, &buffer_allocated, ZBX_DM_DELIMITER); /* nodeid */
			nodeid=atoi(buffer);
			zbx_get_next_field(&r, &buffer, &buffer_allocated, ZBX_DM_DELIMITER); /* tablename */

			table = DBget_table(buffer);
			if (0 == (table->flags & (ZBX_HISTORY | ZBX_HISTORY_SYNC | ZBX_HISTORY_TRENDS)))
				table = NULL;

			if (NULL != table && 0 != (table->flags & ZBX_HISTORY_SYNC)) {
				table_sync = table;
				if (NULL != (pos = strstr(buffer, "_sync"))) {
					*pos = '\0';
					table = DBget_table(buffer);
				}
			}

			if (NULL != table && 0 == strcmp(table->table, "events"))
				events = 1;

			if (NULL == table) {
				zabbix_log(LOG_LEVEL_ERR, "NODE %d: Invalid received data: unknown tablename \"%s\"",
					CONFIG_NODEID,
					buffer);
			}
			if (NULL != newline) {
				zabbix_log(LOG_LEVEL_WARNING, "NODE %d: Received %s from node %d for node %d datalen %d",
					CONFIG_NODEID,
					buffer,
					sender_nodeid,
					nodeid,
					(int)datalen);
			}
			firstline = 0;
		} else if (NULL != table) {
			if (events) {
				res = process_record_event(sender_nodeid, nodeid, table, r);
			} else {
				res = process_record(sender_nodeid, nodeid, table, r);
				if (SUCCEED == res && NULL != table_sync && 0 != CONFIG_MASTER_NODEID)
					res = process_record(sender_nodeid, nodeid, table_sync, r);
			}
		}

		if (newline != NULL) {
			*newline = '\n';
			r = newline + 1;
		} else
			break;
	}
	if (res == SUCCEED)
		DBcommit();
	else
		DBrollback();

	zbx_free(tmp);
	zbx_free(sql);
	zbx_free(sql2);
	zbx_free(buffer);

	return res;
}
