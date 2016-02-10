/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"

#include "cfg.h"
#include "db.h"
#include "log.h"

#include "history.h"
#include "nodewatcher.h"
#include "nodecomms.h"

extern int	CONFIG_NODE_NOHISTORY;
extern int	CONFIG_NODE_NOEVENTS;

/******************************************************************************
 *                                                                            *
 * Function: get_history_lastid                                               *
 *                                                                            *
 * Purpose: get last history id from master node                              *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	get_history_lastid(int master_nodeid, int nodeid, const ZBX_TABLE *table, zbx_uint64_t *lastid)
{
	const char	*__function_name = "get_history_lastid";
	zbx_sock_t	sock;
	char		data[MAX_STRING_LEN], *answer;
	int		res = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED == connect_to_node(master_nodeid, &sock))
	{
		zbx_snprintf(data, sizeof(data), "ZBX_GET_HISTORY_LAST_ID%c%d%c%d\n%s%c%s",
			ZBX_DM_DELIMITER, CONFIG_NODEID,
			ZBX_DM_DELIMITER, nodeid,
			table->table, ZBX_DM_DELIMITER, table->recid);

		if (FAIL == send_data_to_node(master_nodeid, &sock, data))
			goto disconnect;

		if (FAIL == recv_data_from_node(master_nodeid, &sock, &answer))
			goto disconnect;

		if (0 == strncmp(answer, "FAIL", 4))
		{
			zabbix_log(LOG_LEVEL_ERR, "NODE %d: %s() FAIL from node %d for node %d",
				CONFIG_NODEID, __function_name, master_nodeid, nodeid);
			goto disconnect;
		}

		ZBX_STR2UINT64(*lastid, answer);
		res = SUCCEED;
disconnect:
		disconnect_node(&sock);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: process_history_table_data                                       *
 *                                                                            *
 * Purpose: process new history data                                          *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	process_history_table_data(const ZBX_TABLE *table, int master_nodeid, int nodeid)
{
	const char	*__function_name = "process_history_table_data";
	DB_RESULT	result;
	DB_ROW		row;
	char		*data = NULL, *tmp = NULL;
	size_t		data_alloc = ZBX_MEBIBYTE, data_offset = 0,
			tmp_alloc = 4 * ZBX_KIBIBYTE, tmp_offset = 0;
	int		data_found = 0, f, fld;
	zbx_uint64_t	lastid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (0 != (table->flags & ZBX_HISTORY) && FAIL == get_history_lastid(master_nodeid, nodeid, table, &lastid))
		return;

	DBbegin();

	data = zbx_malloc(data, data_alloc);
	tmp = zbx_malloc(tmp, tmp_alloc);

	zbx_snprintf_alloc(&data, &data_alloc, &data_offset, "History%c%d%c%d%c%s",
		ZBX_DM_DELIMITER, CONFIG_NODEID,
		ZBX_DM_DELIMITER, nodeid,
		ZBX_DM_DELIMITER, table->table);

	if (0 != (table->flags & ZBX_HISTORY_SYNC))
		zbx_snprintf_alloc(&tmp, &tmp_alloc, &tmp_offset, "select %s,", table->recid);
	else	/* ZBX_HISTORY */
		zbx_strcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, "select ");

	for (f = 0; NULL != table->fields[f].name; f++)
	{
		if (0 != (table->flags & ZBX_HISTORY_SYNC) && 0 == (table->fields[f].flags & ZBX_HISTORY_SYNC))
			continue;

		zbx_snprintf_alloc(&tmp, &tmp_alloc, &tmp_offset, "%s,", table->fields[f].name);
	}
	tmp_offset--;

	if (0 != (table->flags & ZBX_HISTORY_SYNC))
	{
		zbx_snprintf_alloc(&tmp, &tmp_alloc, &tmp_offset, " from %s where nodeid=%d order by %s",
			table->table, nodeid, table->recid);
	}
	else	/* ZBX_HISTORY */
	{
		zbx_snprintf_alloc(&tmp, &tmp_alloc, &tmp_offset, " from %s where %s>" ZBX_FS_UI64 DB_NODE " order by %s",
			table->table, table->recid, lastid, DBnode(table->recid, nodeid), table->recid);
	}

	result = DBselectN(tmp, 10000);

	while (NULL != (row = DBfetch(result)))
	{
		if (0 != (table->flags & ZBX_HISTORY_SYNC))
		{
			ZBX_STR2UINT64(lastid, row[0]);
			fld = 1;
		}
		else
			fld = 0;

		zbx_chrcpy_alloc(&data, &data_alloc, &data_offset, '\n');

		for (f = 0; NULL != table->fields[f].name; f++)
		{
			if (0 != (table->flags & ZBX_HISTORY_SYNC) && 0 == (table->fields[f].flags & ZBX_HISTORY_SYNC))
				continue;

			if (ZBX_TYPE_INT == table->fields[f].type ||
					ZBX_TYPE_UINT == table->fields[f].type ||
					ZBX_TYPE_ID == table->fields[f].type ||
					ZBX_TYPE_FLOAT == table->fields[f].type)
			{
				if (SUCCEED == DBis_null(row[fld]))
				{
					zbx_snprintf_alloc(&data, &data_alloc, &data_offset, "NULL%c",
							ZBX_DM_DELIMITER);
				}
				else
				{
					zbx_snprintf_alloc(&data, &data_alloc, &data_offset, "%s%c",
							row[fld], ZBX_DM_DELIMITER);
				}
			}
			else	/* ZBX_TYPE_CHAR ZBX_TYPE_BLOB ZBX_TYPE_TEXT */
			{
				zbx_binary2hex((u_char *)row[fld], strlen(row[fld]), &tmp, &tmp_alloc);
				zbx_snprintf_alloc(&data, &data_alloc, &data_offset, "%s%c", tmp, ZBX_DM_DELIMITER);
			}

			fld++;
		}

		data_offset--;
		data_found = 1;
	}
	DBfree_result(result);

	data[data_offset] = '\0';

	if (1 == data_found && SUCCEED == send_to_node(table->table, master_nodeid, nodeid, data))
	{
		if (0 != (table->flags & ZBX_HISTORY_SYNC))
		{
			DBexecute("delete from %s where nodeid=%d and %s<=" ZBX_FS_UI64,
				table->table, nodeid, table->recid, lastid);
		}
	}

	DBcommit();

	zbx_free(tmp);
	zbx_free(data);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: process_history_tables                                           *
 *                                                                            *
 * Purpose: process new history data from tables with ZBX_HISTORY* flags      *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	process_history_tables(int master_nodeid, int nodeid)
{
	const char	*__function_name = "process_history_tables";
	const ZBX_TABLE	*t;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (t = tables; NULL != t->table; t++)
	{
		if (0 == (t->flags & (ZBX_HISTORY | ZBX_HISTORY_SYNC)))
			continue;

		/* Do not send history or events for current node if CONFIG_NODE_NO* is set */
		if (CONFIG_NODEID == nodeid)
		{
			if (0 != CONFIG_NODE_NOHISTORY && 0 == strncmp(t->table, "history", 7))
				continue;

			if (0 != CONFIG_NODE_NOEVENTS && SUCCEED == str_in_list("events,acknowledges", t->table, ','))
				continue;
		}

		process_history_table_data(t, master_nodeid, nodeid);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: main_historysender                                               *
 *                                                                            *
 * Purpose: periodically sends historical data to master node                 *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	main_historysender()
{
	const char	*__function_name = "main_historysender";
	DB_RESULT	result;
	DB_ROW		row;
	int		nodeid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (0 == CONFIG_MASTER_NODEID)
		return;

	result = DBselect("select nodeid from nodes");

	while (NULL != (row = DBfetch(result)))
	{
		nodeid = atoi(row[0]);

		if (SUCCEED == is_master_node(CONFIG_NODEID, nodeid))
			continue;

		process_history_tables(CONFIG_MASTER_NODEID, nodeid);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
