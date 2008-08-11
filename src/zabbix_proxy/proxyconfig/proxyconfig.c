/* 
** ZABBIX
** Copyright (C) 2000-2006 SIA Zabbix
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

#include "common.h"
#include "db.h"
#include "log.h"
#include "daemon.h"
#include "zbxjson.h"

#include "proxyconfig.h"
#include "../servercomms.h"

#define CONFIG_PROXYCONFIG_RETRY 120 /* seconds */

/******************************************************************************
 *                                                                            *
 * Function: process_proxyconfig_table                                        *
 *                                                                            *
 * Purpose: update configuration table                                        *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCESS - processed succesfully                              * 
 *               FAIL - an error occured                                      *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	process_proxyconfig_table(struct zbx_json_parse *jp, const char *tablename, const char *p)
{
	int			f, field_count, insert;
	const ZBX_TABLE		*table = NULL;
	const ZBX_FIELD		*fields[ZBX_MAX_FIELDS];
	struct zbx_json_parse	jp_obj, jp_data, jp_row;
	char			buf[MAX_STRING_LEN], *esc;
	zbx_uint64_t		recid;
	const char		*pf;
	static zbx_uint64_t	*new = NULL, *old = NULL;
	static int		new_alloc = 100, old_alloc = 100;
	int			new_num = 0, old_num = 0;
	static char		*sql = NULL;
	static int		sql_alloc = 4096;
	int			sql_offset;
	DB_RESULT		result;
	DB_ROW			row;

	zabbix_log(LOG_LEVEL_DEBUG, "In process_proxyconfig_table() [tablename:%s]",
			tablename);

	if (NULL == (table = DBget_table(tablename))) {
		zabbix_log(LOG_LEVEL_WARNING, "Invalid table name \"%s\"",
				tablename);
		return FAIL;
	}

	if (NULL == new)
		new = zbx_malloc(new, new_alloc * sizeof(zbx_uint64_t));
	if (NULL == old)
		old = zbx_malloc(old, old_alloc * sizeof(zbx_uint64_t));
	if (NULL == sql)
		sql = zbx_malloc(sql, sql_alloc * sizeof(char));

	result = DBselect("select %s from %s", table->recid, table->table);
	while (NULL != (row = DBfetch(result)))
		uint64_array_add(&old, &old_alloc, &old_num, zbx_atoui64(row[0]));
	DBfree_result(result);

/* {"hosts":{"fields":["hostid","host",...],"data":[[1,"zbx01",...],[2,"zbx02",...],...]},"items":{...},...} 
 *          ^---------------------------------------------------------------------------^
 */	if (FAIL == zbx_json_brackets_open(p, &jp_obj))
		goto json_error;

/* {"hosts":{"fields":["hostid","host",...],"data":[[1,"zbx01",...],[2,"zbx02",...],...]},"items":{...},...} 
 *                    ^
 */	if (NULL == (p = zbx_json_pair_by_name(&jp_obj, "fields"))) {
		zabbix_log(LOG_LEVEL_WARNING, "Can't find \"fields\" pair");
		return FAIL;
	}

/* {"hosts":{"fields":["hostid","host",...],"data":[[1,"zbx01",...],[2,"zbx02",...],...]},"items":{...},...} 
 *                    ^-------------------^
 */	if (FAIL == zbx_json_brackets_open(p, &jp_data))
		goto json_error;

	p = NULL;
	field_count = 0;
	while (NULL != (p = zbx_json_next(&jp_data, p))) {
		if (NULL == (p = zbx_json_decodevalue(p, buf, sizeof(buf))))
			goto json_error;

		fields[field_count] = NULL;
		for(f = 0; table->fields[f].name != NULL; f++)
			if (0 == strcmp(table->fields[f].name, buf)) {
				fields[field_count] = &table->fields[f];
				break;
			}

		if (NULL == fields[field_count]) {
			zabbix_log(LOG_LEVEL_WARNING, "Invalid field name \"%s\"",
					buf);
			return FAIL;
		}
		field_count++;
	}

/* {"hosts":{"fields":["hostid","host",...],"data":[[1,"zbx01",...],[2,"zbx02",...],...]},"items":{...},...} 
 *                                                 ^
 */	if (NULL == (p = zbx_json_pair_by_name(&jp_obj, "data"))) {
		zabbix_log(LOG_LEVEL_WARNING, "Can't find \"data\" pair");
		return FAIL;
	}

/* {"hosts":{"fields":["hostid","host",...],"data":[[1,"zbx01",...],[2,"zbx02",...],...]},"items":{...},...} 
 *                                                 ^-----------------------------------^
 */	if (FAIL == zbx_json_brackets_open(p, &jp_data))
		goto json_error;

	p = NULL;
	sql_offset = 0;

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "begin\n");
#endif

/* {"hosts":{"fields":["hostid","host",...],"data":[[1,"zbx01",...],[2,"zbx02",...],...]},"items":{...},...} 
 *                                                  ^
 */	while (NULL != (p = zbx_json_next(&jp_data, p))) {
/* {"hosts":{"fields":["hostid","host",...],"data":[[1,"zbx01",...],[2,"zbx02",...],...]},"items":{...},...} 
 *                                                  ^-------------^
 */		if (FAIL == zbx_json_brackets_open(p, &jp_row))
			goto json_error;

		pf = NULL;
		if (NULL == (pf = zbx_json_next_value(&jp_row, pf, buf, sizeof(buf))))
			goto json_error;

		recid = zbx_atoui64(buf);

		insert = (SUCCEED == uint64_array_exists(old, old_num, recid)) ? 0 : 1;

		if (insert)
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 128, "insert into %s (", table->table);

			for (f = 0; f < field_count; f ++)
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 128, "%s,", fields[f]->name);

			sql_offset--;
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 128, ") values (" ZBX_FS_UI64 ",",
					recid);
		}
		else
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 128, "update %s set ",
					table->table);

/* {"hosts":{"fields":["hostid","host",...],"data":[[1,"zbx01",...],[2,"zbx02",...],...]},"items":{...},...} 
 *                                                   ^
 */		f = 1;
		while (NULL != (pf = zbx_json_next_value(&jp_row, pf, buf, sizeof(buf))))
		{
			if (f == field_count)
			{
				zabbix_log(LOG_LEVEL_WARNING, "Invalid number of fields \"%.*s\"",
						jp_row.end - jp_row.start + 1,
						jp_row.start);
				return FAIL;
			}

			if (fields[f]->type == ZBX_TYPE_INT || fields[f]->type == ZBX_TYPE_UINT || fields[f]->type == ZBX_TYPE_ID || fields[f]->type == ZBX_TYPE_FLOAT)
		       	{
				if (0 == insert)
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 128, "%s=", fields[f]->name);

				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 128, "%s,", buf);
			}
			else
			{
				if (0 == insert)
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 128, "%s=", fields[f]->name);

				esc = DBdyn_escape_string(buf);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, strlen(esc) + 8, "'%s',", esc);
			}
			f++;
		}

		if (f != field_count)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Invalid number of fields \"%.*s\"",
					jp_row.end - jp_row.start + 1,
					jp_row.start);
			return FAIL;
		}

		sql_offset--;
		if (insert)
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 4, ");\n");
		else
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 256, " where %s=" ZBX_FS_UI64 ";\n",
					table->recid,
					recid);

		uint64_array_add(&new, &new_alloc, &new_num, recid);
	}

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_allocated, &sql_offset, 8, "end;\n");
#endif

	if (sql_offset > 16) /* In ORACLE always present begin..end; */
		if (ZBX_DB_OK > DBexecute("%s", sql))
			goto db_error;

	uint64_array_rm(old, &old_num, new, new_num);

	if (old_num > 0)
	{
		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 128, "delete from %s where", table->table);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, table->recid, old, old_num);
		if (ZBX_DB_OK > DBexecute("%s", sql))
			goto db_error;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End process_proxyconfig_table()");

	return SUCCEED;
json_error:
	zabbix_log(LOG_LEVEL_DEBUG, "Can't proceed table \"%s\". %s",
			tablename,
			zbx_json_strerror());
db_error:
	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: process_proxyconfig                                              *
 *                                                                            *
 * Purpose: update configuration                                              *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCESS - processed succesfully                              * 
 *               FAIL - an error occured                                      *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	process_proxyconfig(struct zbx_json_parse *jp)
{
	char		buf[MAX_STRING_LEN];
	size_t		len = sizeof(buf);
	const char	*p = NULL;
	int		res = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In process_proxyconfig()");

	DBbegin();
/*
 * {"hosts":{"fields":["hostid","host",...],"data":[[1,"zbx01",...],[2,"zbx02",...],...]},"items":{...},...} 
 *          ^
 */	while (NULL != (p = zbx_json_pair_next(jp, p, buf, len)) && res == SUCCEED) {
		if (ZBX_JSON_TYPE_OBJECT != zbx_json_type(p)) {
			zabbix_log(LOG_LEVEL_WARNING, "Invalid type of data for table \"%s\" \"%.40s...\"",
					buf,
					p);
			res = FAIL;
			break;
		}

		res = process_proxyconfig_table(jp, buf, p);
	}
	if (res == SUCCEED)
		DBcommit();
	else
		DBrollback();
}

/******************************************************************************
 *                                                                            *
 * Function: process_configuration_sync                                       *
 *                                                                            *
 * Purpose: calculates checks sum of config data                              *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              * 
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments: never returns                                                    *
 *                                                                            *
 ******************************************************************************/
static void	process_configuration_sync()
{
	zbx_sock_t	sock;
	char		*data;
	struct		zbx_json_parse jp;
	
	zabbix_log(LOG_LEVEL_DEBUG, "In process_configuration_sync()");

	while (FAIL == connect_to_server(&sock, 600)) { /* alarm */
		zabbix_log(LOG_LEVEL_WARNING, "Connect to the server failed. Retry after %d seconds",
				CONFIG_PROXYCONFIG_RETRY);

		sleep(CONFIG_PROXYCONFIG_RETRY);
	}

	if (FAIL == get_data_from_server(&sock, ZBX_PROTO_VALUE_PROXY_CONFIG, &data))
		goto exit;

	if (FAIL == zbx_json_open(data, &jp))
		goto exit;

	process_proxyconfig(&jp);
exit:
	disconnect_server(&sock);
}

/******************************************************************************
 *                                                                            *
 * Function: main_proxyconfig_loop                                            *
 *                                                                            *
 * Purpose: periodically request config data                                  *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              * 
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments: never returns                                                    *
 *                                                                            *
 ******************************************************************************/
void	main_proxyconfig_loop()
{
	struct	sigaction phan;
	int	start, sleeptime;

	zabbix_log(LOG_LEVEL_DEBUG, "In main_proxyconfig_loop()");

	phan.sa_handler = child_signal_handler;
	sigemptyset(&phan.sa_mask);
	phan.sa_flags = 0;
	sigaction(SIGALRM, &phan, NULL);

	for (;;) {
		start = time(NULL);

		zbx_setproctitle("configuration syncer [connecting to the database]]");

		DBconnect(ZBX_DB_CONNECT_NORMAL);

		zbx_setproctitle("configuration syncer [load configuration]");

		process_configuration_sync();

		DBclose();

		sleeptime = CONFIG_PROXYCONFIG_FREQUENCY - (time(NULL) - start);

		if (sleeptime > 0) {
			zbx_setproctitle("configuration syncer [sleeping for %d seconds]",
					sleeptime);
			zabbix_log(LOG_LEVEL_DEBUG, "Sleeping for %d seconds",
					sleeptime);
			sleep(sleeptime);
		}
	}
}
