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
#include "comms.h"
#include "db.h"
#include "log.h"
#include "daemon.h"
#include "zbxjson.h"

#include "datasender.h"
#include "../servercomms.h"

#define ZBX_HISTORY_FIELD struct history_field_t
#define ZBX_HISTORY_TABLE struct history_table_t

struct history_field_t {
	const char		*field;
	const char		*tag;
};

struct history_table_t {
	const char		*table, *lastfieldname;
	ZBX_HISTORY_FIELD	fields[ZBX_MAX_FIELDS];
};

ZBX_HISTORY_TABLE ht[]={
	{"history_sync", "history_lastid",
		{
		{"clock",	ZBX_PROTO_TAG_CLOCK},
		{"value",	ZBX_PROTO_TAG_VALUE},
		{NULL}
		}
	},
	{"history_uint_sync", "history_uint_lastid",
		{
		{"clock",	ZBX_PROTO_TAG_CLOCK},
		{"value",	ZBX_PROTO_TAG_VALUE},
		{NULL}
		}
	},
	{"history_str_sync", "history_str_lastid",
		{
		{"clock",	ZBX_PROTO_TAG_CLOCK},
		{"value",	ZBX_PROTO_TAG_VALUE},
		{NULL}
		}
	},
	{"history_text", "history_text_lastid",
		{
		{"clock",	ZBX_PROTO_TAG_CLOCK},
		{"value",	ZBX_PROTO_TAG_VALUE},
		{NULL}
		}
	},
	{"history_log", "history_log_lastid",
		{
		{"clock",	ZBX_PROTO_TAG_CLOCK},
		{"timestamp",	ZBX_PROTO_TAG_LOGTIMESTAMP},
		{"source",	ZBX_PROTO_TAG_LOGSOURCE},
		{"severity",	ZBX_PROTO_TAG_LOGSEVERITY},
		{"value",	ZBX_PROTO_TAG_VALUE},
		{NULL}
		}
	},
	{NULL}
};

ZBX_HISTORY_TABLE dht[]={
	{"proxy_dhistory", "dhistory_lastid",
		{
		{"clock",	ZBX_PROTO_TAG_CLOCK},
		{"druleid",	ZBX_PROTO_TAG_DRULE},
		{"type",	ZBX_PROTO_TAG_TYPE},
		{"ip",		ZBX_PROTO_TAG_IP},
		{"port",	ZBX_PROTO_TAG_PORT},
		{"key_",	ZBX_PROTO_TAG_KEY},
		{"value",	ZBX_PROTO_TAG_VALUE},
		{"status",	ZBX_PROTO_TAG_STATUS},
		{NULL}
		}
	},
	{NULL}
};

struct last_ids {
	const char	*lastfieldname;
	zbx_uint64_t	lastid;
};

#define ZBX_SENDER_TABLE_COUNT 6

/******************************************************************************
 *                                                                            *
 * Function: get_lastclock                                                    *
 *                                                                            *
 * Purpose:                                                                   *
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
static void	get_lastid(const char *fieldname, zbx_uint64_t *lastid)
{
	DB_RESULT	result;
	DB_ROW		row;

	*lastid = 0;

	result = DBselect("select %s from proxies",
			fieldname);

	if (NULL != (row = DBfetch(result)) && FAIL == DBis_null(row[0]))
		*lastid = zbx_atoui64(row[0]);

	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: get_history_data                                                 *
 *                                                                            *
 * Purpose:                                                                   *
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
static int get_history_data(struct zbx_json *j, const ZBX_HISTORY_TABLE *ht, zbx_uint64_t *lastid)
{
	int		offset = 0, f, records = 0;
	char		sql[MAX_STRING_LEN];
	DB_RESULT	result;
	DB_ROW		row;
	const ZBX_FIELD	*field;
	const ZBX_TABLE	*table;
	zbx_json_type_t	jt;
	zbx_uint64_t	id;

	zabbix_log(LOG_LEVEL_DEBUG, "In get_history_data() [table:%s]",
			ht->table);

	*lastid = 0;

	get_lastid(ht->lastfieldname, &id);

	offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, "select d.id,h.host,i.key_");

	for (f = 0; ht->fields[f].field != NULL; f ++)
		offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, ",d.%s",
				ht->fields[f].field);

	offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, " from hosts h,items i,%s d"
			" where h.hostid=i.hostid and i.itemid=d.itemid and d.id>" ZBX_FS_UI64 " order by d.id",
			ht->table,
			id);

	result = DBselectN(sql, 1000);

	table = DBget_table(ht->table);

	while (NULL != (row = DBfetch(result))) {
		zbx_json_addobject(j, NULL);

		*lastid = zbx_atoui64(row[0]);

		zbx_json_addstring(j, ZBX_PROTO_TAG_HOST, row[1], ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(j, ZBX_PROTO_TAG_KEY, row[2], ZBX_JSON_TYPE_STRING);
		for (f = 0; ht->fields[f].field != NULL; f ++) {
			field = DBget_field(table, ht->fields[f].field);

			switch (field->type) {
			case ZBX_TYPE_ID:
			case ZBX_TYPE_INT:
			case ZBX_TYPE_UINT:
				jt = ZBX_JSON_TYPE_INT;
				break;
			default :
				jt = ZBX_JSON_TYPE_STRING;
				break;
			}

			zbx_json_addstring(j, ht->fields[f].tag, row[f + 3], jt);
		}

		records++;

		zbx_json_close(j);
	}

	return records;
}

/******************************************************************************
 *                                                                            *
 * Function: get_dhistory_data                                                 *
 *                                                                            *
 * Purpose:                                                                   *
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
static int get_dhistory_data(struct zbx_json *j, const ZBX_HISTORY_TABLE *ht, zbx_uint64_t *lastid)
{
	int		offset = 0, f, records = 0;
	char		sql[MAX_STRING_LEN];
	DB_RESULT	result;
	DB_ROW		row;
	const ZBX_FIELD	*field;
	const ZBX_TABLE	*table;
	zbx_json_type_t	jt;
	zbx_uint64_t	id;

	zabbix_log(LOG_LEVEL_DEBUG, "In get_dhistory_data() [table:%s]",
			ht->table);

	*lastid = 0;

	get_lastid(ht->lastfieldname, &id);

	offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, "select id");

	for (f = 0; ht->fields[f].field != NULL; f ++)
		offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, ",%s",
				ht->fields[f].field);

	offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, " from %s"
			" where id>" ZBX_FS_UI64 " order by id",
			ht->table,
			id);

	result = DBselectN(sql, 1000);

	table = DBget_table(ht->table);

	while (NULL != (row = DBfetch(result))) {
		zbx_json_addobject(j, NULL);

		*lastid = zbx_atoui64(row[0]);

		for (f = 0; ht->fields[f].field != NULL; f ++) {
			field = DBget_field(table, ht->fields[f].field);

			switch (field->type) {
			case ZBX_TYPE_ID:
			case ZBX_TYPE_INT:
			case ZBX_TYPE_UINT:
				jt = ZBX_JSON_TYPE_INT;
				break;
			default :
				jt = ZBX_JSON_TYPE_STRING;
				break;
			}

			zbx_json_addstring(j, ht->fields[f].tag, row[f + 1], jt);
		}

		records++;

		zbx_json_close(j);
	}

	return records;
}

/******************************************************************************
 *                                                                            *
 * Function: main_datasender                                                  *
 *                                                                            *
 * Purpose:                                                                   *
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
static int	main_datasender()
{
	struct zbx_json	j;
	int		i, r, records = 0;
	zbx_sock_t	sock;
	char		*answer, buf[11]; /* strlen("4294967296") + 1 */
	zbx_uint64_t	lastid;
	struct last_ids li[ZBX_SENDER_TABLE_COUNT];
	int		li_no = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In main_datasender()");

	if (FAIL == connect_to_server(&sock, 60)) /* alarm !!! */
		return FAIL;

	zbx_json_init(&j, 512*1024);
	zbx_json_addstring(&j, ZBX_PROTO_TAG_REQUEST, ZBX_PROTO_VALUE_SENDER_DATA, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&j, ZBX_PROTO_TAG_PROXY, CONFIG_HOSTNAME, ZBX_JSON_TYPE_STRING);

	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);
	for (i = 0; ht[i].table != NULL; i ++) {
		if (0 == (r = get_history_data(&j, &ht[i], &lastid)))
			continue;

		li[li_no].lastfieldname = ht[i].lastfieldname;
		li[li_no].lastid = lastid;
		li_no++;

		records += r;
	}
	zbx_json_close(&j); /* array */

	zbx_json_addarray(&j, ZBX_PROTO_TAG_DISCOVERY_DATA);
	for (i = 0; dht[i].table != NULL; i ++) {
		if (0 == (r = get_dhistory_data(&j, &dht[i], &lastid)))
			continue;

		li[li_no].lastfieldname = dht[i].lastfieldname;
		li[li_no].lastid = lastid;
		li_no++;

		records += r;
	}
	zbx_json_close(&j); /* array */

	zbx_snprintf(buf, sizeof(buf), "%d", (int)time(NULL));

	zbx_json_addstring(&j, ZBX_PROTO_TAG_CLOCK, buf, ZBX_JSON_TYPE_INT);

	if (SUCCEED == put_data_to_server(&sock, &j, &answer)) {
		DBbegin();

		for (i = 0; i < li_no; i++) {
			DBexecute("update proxies set %s=" ZBX_FS_UI64,
					li[i].lastfieldname,
					li[i].lastid);
		}

		DBcommit();
	}

	zbx_json_free(&j);

	disconnect_server(&sock);

	return records;
}

/******************************************************************************
 *                                                                            *
 * Function: main_datasender_loop                                             *
 *                                                                            *
 * Purpose: periodically sends history and events to the server               *
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
int	main_datasender_loop()
{
	struct sigaction	phan;
	int			now, sleeptime,
				records;
	double			sec;

	zabbix_log(LOG_LEVEL_DEBUG, "In main_datasender_loop()");

	phan.sa_handler = child_signal_handler;
	sigemptyset(&phan.sa_mask);
	phan.sa_flags = 0;
	sigaction(SIGALRM, &phan, NULL);

	for (;;) {
		now = time(NULL);
		sec = zbx_time();

		zbx_setproctitle("data sender [connecting to the database]");

		DBconnect(ZBX_DB_CONNECT_NORMAL);

		zbx_setproctitle("data sender [sending data]");

		records = main_datasender();

		zabbix_log(LOG_LEVEL_DEBUG, "Datasender spent " ZBX_FS_DBL " seconds while processing %3d values.",
				zbx_time() - sec,
				records);

		DBclose();

		sleeptime = CONFIG_DATASENDER_FREQUENCY - (time(NULL) - now);

		if (sleeptime > 0) {
			zbx_setproctitle("data sender [sleeping for %d seconds]",
					sleeptime);
			zabbix_log(LOG_LEVEL_DEBUG, "Sleeping for %d seconds",
					sleeptime);
			sleep(sleeptime);
		}
	}
}
