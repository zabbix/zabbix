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
#include "zlog.h"

#include "../events.h"
#include "../nodewatcher/nodecomms.h"

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
	char		*tmp = NULL, tablename[MAX_STRING_LEN], fieldname[MAX_STRING_LEN];
	int		tmp_allocated = 256, tmp_offset;
	int		sender_nodeid, nodeid, res;

	zabbix_log(LOG_LEVEL_DEBUG, "In send_list_of_history_ids()");

	tmp = zbx_malloc(tmp, tmp_allocated);

	r = data;
	if (NULL == r)
		goto error;

	zbx_get_next_field(&r, &tmp, &tmp_allocated, ZBX_DM_DELIMITER); /* constant 'ZBX_GET_HISTORY_LAST_ID' */
	if (NULL == r)
		goto error;

	zbx_get_next_field(&r, &tmp, &tmp_allocated, ZBX_DM_DELIMITER); /* sender_nodeid */
	sender_nodeid=atoi(tmp);
	if (NULL == r)
		goto error;

	zbx_get_next_field(&r, &tmp, &tmp_allocated, '\n'); /* nodeid */
	nodeid=atoi(tmp);
	if (NULL == r)
		goto error;

	zbx_get_next_field(&r, &tmp, &tmp_allocated, ZBX_DM_DELIMITER); /* table name */
	strcpy(tablename, tmp);

	if (NULL == r)
		goto error;

	zbx_get_next_field(&r, &tmp, &tmp_allocated, ZBX_DM_DELIMITER); /* field name */
	strcpy(fieldname, tmp);

	tmp_offset= 0;
	zbx_snprintf_alloc(&tmp, &tmp_allocated, &tmp_offset, 256, "select MAX(%s) "
		"from %s where"ZBX_COND_NODEID,
		fieldname,
		tablename,
		ZBX_NODE(fieldname, nodeid));

	tmp_offset= 0;
	result = DBselect("%s", tmp);
	if (NULL != (row = DBfetch(result)))
		zbx_snprintf_alloc(&tmp, &tmp_allocated, &tmp_offset, 128, "%s",
			SUCCEED == DBis_null(row[0]) ? "0" : row[0]);
	DBfree_result(result);

	if (tmp_offset == 0)
		goto error;

	res = send_data_to_node(sender_nodeid, sock, tmp);

	zbx_free(tmp);

	return  res;
error:
	tmp_offset= 0;
	zbx_snprintf_alloc(&tmp, &tmp_allocated, &tmp_offset, 128, "FAIL");

	res = send_data_to_node(sender_nodeid, sock, tmp);

	zbx_free(tmp);

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
	char		*tmp = NULL, tablename[MAX_STRING_LEN];
	int		tmp_allocated = 256, tmp_offset;
	int		sender_nodeid, nodeid, res;

	zabbix_log(LOG_LEVEL_DEBUG, "In send_list_of_history_ids()");

	tmp = zbx_malloc(tmp, tmp_allocated);

	r = data;
	if (NULL == r)
		goto error;

	zbx_get_next_field(&r, &tmp, &tmp_allocated, ZBX_DM_DELIMITER); /* constant 'ZBX_GET_HISTORY_LAST_ID' */
	if (NULL == r)
		goto error;

	zbx_get_next_field(&r, &tmp, &tmp_allocated, ZBX_DM_DELIMITER); /* sender_nodeid */
	sender_nodeid=atoi(tmp);
	if (NULL == r)
		goto error;

	zbx_get_next_field(&r, &tmp, &tmp_allocated, '\n'); /* nodeid */
	nodeid=atoi(tmp);
	if (NULL == r)
		goto error;

	zbx_get_next_field(&r, &tmp, &tmp_allocated, ZBX_DM_DELIMITER); /* table name */
	strcpy(tablename, tmp);

	tmp_offset= 0;
	zbx_snprintf_alloc(&tmp, &tmp_allocated, &tmp_offset, 256, "select itemid,clock "
		"from %s where"ZBX_COND_NODEID"order by itemid desc,clock desc",
		tablename,
		ZBX_NODE("itemid", nodeid));

	tmp_offset= 0;
	result = DBselectN(tmp, 1);
	if (NULL == (row = DBfetch(result)))
		zbx_snprintf_alloc(&tmp, &tmp_allocated, &tmp_offset, 64, "0%c0",
			ZBX_DM_DELIMITER);
	else
		zbx_snprintf_alloc(&tmp, &tmp_allocated, &tmp_offset, 128, "%s%c%s",
			SUCCEED == DBis_null(row[0]) ? "0" : row[0],
			ZBX_DM_DELIMITER,
			SUCCEED == DBis_null(row[1]) ? "0" : row[1]);
	DBfree_result(result);

	res = send_data_to_node(sender_nodeid, sock, tmp);

	zbx_free(tmp);

	return  res;
error:
	tmp_offset= 0;
	zbx_snprintf_alloc(&tmp, &tmp_allocated, &tmp_offset, 128, "FAIL");

	res = send_data_to_node(sender_nodeid, sock, tmp);

	zbx_free(tmp);

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
static int	process_record_event(int sender_nodeid, int nodeid, const ZBX_TABLE *table, const char *record, char **tmp, int *tmp_allocated)
{
	const char	*r;
	int		f, len;
	DB_EVENT	event;

	zabbix_log(LOG_LEVEL_DEBUG, "In process_record_event ()");

	memset(&event, 0, sizeof(event));

	r = record;
	for (f = 0; table->fields[f].name != 0; f++) {
		if (NULL == r)
			goto error;

		len = zbx_get_next_field(&r, tmp, tmp_allocated, ZBX_DM_DELIMITER);

		if (0 == strcmp(table->fields[f].name, "eventid")) {
			ZBX_STR2UINT64(event.eventid, *tmp);
		} else if (0 == strcmp(table->fields[f].name, "source")) {
			event.source = atoi(*tmp);
		} else if (0 == strcmp(table->fields[f].name, "object")) {
			event.object = atoi(*tmp);
		} else if (0 == strcmp(table->fields[f].name, "objectid")) {
			ZBX_STR2UINT64(event.objectid, *tmp);
		} else if (0 == strcmp(table->fields[f].name, "clock")) {
			event.clock=atoi(*tmp);
		} else if (0 == strcmp(table->fields[f].name, "value")) {
			event.value=atoi(*tmp);
		} else if (0 == strcmp(table->fields[f].name, "acknowledged")) {
			event.acknowledged=atoi(*tmp);
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
static int	process_record(int sender_nodeid, int nodeid, const ZBX_TABLE *table, const char *record, char **tmp, int *tmp_allocated, char **sql, int *sql_allocated, int lastrecord)
{
	const char	*r;
	int		f, len, sql_offset, lastvalue_type;
	int		res = FAIL;
	char		lastvalue[MAX_STRING_LEN], lastclock[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In process_record ()");

	r = record;

	sql_offset = 0;
	zbx_snprintf_alloc(sql, sql_allocated, &sql_offset, 128, "insert into %s (",
		table->table);

	if (0 != (table->flags & ZBX_HISTORY_SYNC))
		zbx_snprintf_alloc(sql, sql_allocated, &sql_offset, 128, "nodeid,");

	for (f = 0; table->fields[f].name != 0; f++) {
		if (0 != (table->flags & ZBX_HISTORY_SYNC) && 0 == (table->fields[f].flags & ZBX_HISTORY_SYNC))
			continue;

		zbx_snprintf_alloc(sql, sql_allocated, &sql_offset, 128, "%s,",
			table->fields[f].name);
	}

	sql_offset--;
	zbx_snprintf_alloc(sql, sql_allocated, &sql_offset, 64, ") values (");

	if (0 != (table->flags & ZBX_HISTORY_SYNC))
		zbx_snprintf_alloc(sql, sql_allocated, &sql_offset, 128, "%d,",
			nodeid);
		
	for (f = 0; table->fields[f].name != 0; f++) {
		if ((table->flags & ZBX_HISTORY_SYNC) && 0 == (table->fields[f].flags & ZBX_HISTORY_SYNC))
			continue;

		if (NULL == r)
			goto error;

		len = zbx_get_next_field(&r, tmp, tmp_allocated, ZBX_DM_DELIMITER);

		if (table->fields[f].type == ZBX_TYPE_INT ||
			table->fields[f].type == ZBX_TYPE_UINT ||
			table->fields[f].type == ZBX_TYPE_ID ||
			table->fields[f].type == ZBX_TYPE_FLOAT)
		{
			zbx_snprintf_alloc(sql, sql_allocated, &sql_offset, len + 8, "%s,",
				*tmp);
		} else { /* ZBX_TYPE_CHAR ZBX_TYPE_BLOB ZBX_TYPE_TEXT */
			if (0 == len)
				zbx_snprintf_alloc(sql, sql_allocated, &sql_offset, 8, "'',");
			else
				zbx_snprintf_alloc(sql, sql_allocated, &sql_offset, len + 8, "0x%s,",
					*tmp);
		}

		if (lastrecord && 0 != (table->flags & ZBX_HISTORY_SYNC)) {
			if (0 == strcmp(table->fields[f].name, "clock")) {
				strcpy(lastclock, *tmp);
			} else if (0 == strcmp(table->fields[f].name, "value")) {
				strcpy(lastvalue, *tmp);
				lastvalue_type = table->fields[f].type;
			}
		}
	}

	sql_offset--;
	zbx_snprintf_alloc(sql, sql_allocated, &sql_offset, 8, ")");

	if (DBexecute("%s", *sql) >= ZBX_DB_OK)
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
	char		*newline = NULL, *tmp = NULL, *sql = NULL;
	char		*pos;
	int		tmp_allocated = 4096, sql_allocated = 8192;
	int		sender_nodeid = 0, nodeid = 0, firstline = 1, events = 0;
	const ZBX_TABLE	*table_sync = NULL, *table = NULL;
	int		res = SUCCEED;

	assert(data);

	zabbix_log(LOG_LEVEL_DEBUG, "In node_history()");

	tmp = zbx_malloc(tmp, tmp_allocated);
	sql = zbx_malloc(sql, sql_allocated);

	DBbegin();

	for (r = data; *r != '\0' && res == SUCCEED;) {
		if (NULL != (newline = strchr(r, '\n')))
			*newline = '\0';

		if (1 == firstline) {
			zbx_get_next_field(&r, &tmp, &tmp_allocated, ZBX_DM_DELIMITER); /* constant 'History' */
			zbx_get_next_field(&r, &tmp, &tmp_allocated, ZBX_DM_DELIMITER); /* sender_nodeid */
			sender_nodeid=atoi(tmp);
			zbx_get_next_field(&r, &tmp, &tmp_allocated, ZBX_DM_DELIMITER); /* nodeid */
			nodeid=atoi(tmp);
			zbx_get_next_field(&r, &tmp, &tmp_allocated, ZBX_DM_DELIMITER); /* tablename */

			table = DBget_table(tmp);
			if (0 == (table->flags & (ZBX_HISTORY | ZBX_HISTORY_SYNC | ZBX_HISTORY_TRENDS)))
				table = NULL;

			if (NULL != table && 0 != (table->flags & ZBX_HISTORY_SYNC)) {
				table_sync = table;
				if (NULL != (pos = strstr(tmp, "_sync"))) {
					*pos = '\0';
					table = DBget_table(tmp);
				}
			}

			if (NULL != table && 0 == strcmp(table->table, "events"))
				events = 1;

			if (NULL == table) {
				zabbix_log(LOG_LEVEL_WARNING, "NODE %d: Invalid received data: unknown tablename \"%s\"",
					CONFIG_NODEID,
					tmp);
			}
			if (NULL != newline) {
				zabbix_log(LOG_LEVEL_WARNING, "NODE %d: Received %s from node %d for node %d datalen %zd",
					CONFIG_NODEID,
					tmp,
					sender_nodeid,
					nodeid,
					datalen);
			}
			firstline = 0;
		} else if (NULL != table) {
			if (events) {
				res = process_record_event(sender_nodeid, nodeid, table, r, &tmp, &tmp_allocated);
			} else {
				res = process_record(sender_nodeid, nodeid, table, r, &tmp, &tmp_allocated, &sql, &sql_allocated, NULL == newline);
				if (SUCCEED == res && NULL != table_sync && 0 != CONFIG_MASTER_NODEID)
					res = process_record(sender_nodeid, nodeid, table_sync, r, &tmp, &tmp_allocated, &sql, &sql_allocated, 0);
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

	zbx_free(sql);
	zbx_free(tmp);

	return res;
}
