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
#include "zbxjson.h"

#include "datasender.h"
#include "../servercomms.h"

#define CONFIG_DATASENDER_FREQUENCY 3

#define ZBX_HISTORY_FIELD struct history_field_t
#define ZBX_HISTORY_TABLE struct history_table_t

struct history_field_t {
	const char		*field;
	const char		*tag;
};

struct history_table_t {
	const char		*table;
	ZBX_HISTORY_FIELD	fields[ZBX_MAX_FIELDS];
};

static const ZBX_HISTORY_TABLE ht[]={
	{"history",
		{
		{"clock",	ZBX_PROTO_TAG_CLOCK},
		{"value",	ZBX_PROTO_TAG_VALUE},
		{NULL}
		}
	},
	{"history_uint",
		{
		{"clock",	ZBX_PROTO_TAG_CLOCK},
		{"value",	ZBX_PROTO_TAG_VALUE},
		{NULL}
		}
	},
	{"history_text",
		{
		{"clock",	ZBX_PROTO_TAG_CLOCK},
		{"value",	ZBX_PROTO_TAG_VALUE},
		{NULL}
		}
	},
	{"history_str",
		{
		{"clock",	ZBX_PROTO_TAG_CLOCK},
		{"value",	ZBX_PROTO_TAG_VALUE},
		{NULL}
		}
	},
	{"history_log",
		{
		{"clock",	ZBX_PROTO_TAG_CLOCK},
		{"timestamp",	ZBX_PROTO_TAG_TIMESTAMP},
		{"source",	ZBX_PROTO_TAG_SOURCE},
		{"severity",	ZBX_PROTO_TAG_SEVERITY},
		{"value",	ZBX_PROTO_TAG_VALUE},
		{NULL}
		}
	},
	{NULL}
};

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
static int get_history_data(struct zbx_json *j, const ZBX_HISTORY_TABLE *ht, int min_clock)
{
	int		offset = 0, f, records = 0;
	char		sql[MAX_STRING_LEN];
	DB_RESULT	result;
	DB_ROW		row;
	const ZBX_FIELD	*field;
	const ZBX_TABLE	*table;
	zbx_json_type_t	jt;

	zabbix_log(LOG_LEVEL_DEBUG, "In get_history_data() [table:%s]",
			ht->table);

	offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, "select h.host,i.key_");

	for (f = 0; ht->fields[f].field != NULL; f ++)
		offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, ",d.%s",
				ht->fields[f].field);

	result = DBselect("%s from hosts h,items i,%s d where h.hostid=i.hostid and i.itemid=d.itemid and d.clock<%d",
			sql,
			ht->table,
			min_clock + 4 * CONFIG_DATASENDER_FREQUENCY);

	table = DBget_table(ht->table);

	while (NULL != (row = DBfetch(result))) {
		zbx_json_addobject(j, NULL);

		zbx_json_addstring(j, ZBX_PROTO_TAG_HOST, row[0], ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(j, ZBX_PROTO_TAG_KEY, row[1], ZBX_JSON_TYPE_STRING);
		for (f = 0; ht->fields[f].field != NULL; f ++) {
			field = DBget_field(table, ht->fields[f].field);

			switch (field->type) {
			case ZBX_TYPE_ID:
			case ZBX_TYPE_INT:
			case ZBX_TYPE_UINT:
				jt = ZBX_JSON_TYPE_INT;
				break;
			case ZBX_TYPE_FLOAT:
				jt = ZBX_JSON_TYPE_FLOAT;
				break;
			default :
				jt = ZBX_JSON_TYPE_STRING;
				break;
			}

			zbx_json_addstring(j, ht->fields[f].tag, row[f + 2], jt);
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
	int		t, records = 0, clock, min_clock = 0;
	DB_RESULT	result;
	DB_ROW		row;
	double		sec;
	zbx_sock_t	sock;
	char		*answer, buf[11]; /* strlen("4294967296") + 1 */

	zabbix_log(LOG_LEVEL_DEBUG, "In main_datasender()");

	if (FAIL == connect_to_server(&sock))
		return FAIL;

	sec = zbx_time();

	for (t = 0; ht[t].table != NULL; t ++) {
		result = DBselect("select min(clock) from %s",
				ht[t].table);

		if(NULL != (row = DBfetch(result)) && FAIL == DBis_null(row[0])) {
			clock = atoi(row[0]);
			if (min_clock == 0 || min_clock > clock)
				min_clock = clock;
		}
		DBfree_result(result);
	}

	zbx_json_init(&j, 512*1024);
	zbx_json_addstring(&j, ZBX_PROTO_TAG_REQUEST, ZBX_PROTO_VALUE_SENDER_DATA, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&j, ZBX_PROTO_TAG_PROXY, CONFIG_HOSTNAME, ZBX_JSON_TYPE_STRING);
	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);

	for (t = 0; ht[t].table != NULL; t ++)
		records += get_history_data(&j, &ht[t], min_clock);

	zbx_snprintf(buf, sizeof(buf), "%d", (int)time(NULL));

	zbx_json_close(&j);

	zbx_json_addstring(&j, ZBX_PROTO_TAG_CLOCK, buf, ZBX_JSON_TYPE_INT);

	if (SUCCEED == put_data_to_server(&sock, &j, &answer))
		;

	zabbix_log(LOG_LEVEL_DEBUG, "----- [%d] [%d] [seconds:%f]\n%s", records, j.buffer_size, zbx_time() - sec, j.buffer);

	zbx_json_free(&j);

	disconnect_server(&sock);

	return SUCCEED;
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
	int	start, sleeptime;

	zabbix_log(LOG_LEVEL_DEBUG, "In main_datasender_loop()");

	for (;;) {
		start = time(NULL);

		zbx_setproctitle("data sender [connecting to the database]");

		DBconnect(ZBX_DB_CONNECT_NORMAL);

		zbx_setproctitle("data sender [sending data]");

		main_datasender();

		DBclose();

		sleeptime = CONFIG_DATASENDER_FREQUENCY - (time(NULL) - start);

		if (sleeptime > 0) {
			zbx_setproctitle("data sender [sleeping for %d seconds]",
					sleeptime);
			zabbix_log(LOG_LEVEL_DEBUG, "Sleeping for %d seconds",
					sleeptime);
			sleep(sleeptime);
		}
	}
}
