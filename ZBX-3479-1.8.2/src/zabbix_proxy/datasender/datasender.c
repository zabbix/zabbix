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

#define ZBX_MAX_HRECORDS	1000

struct history_field_t {
	const char		*field;
	const char		*tag;
	zbx_json_type_t		jt;
	char			*default_value;
};

struct history_table_t {
	const char		*table, *lastfieldname;
	int			cidx;	/* index of column 'clock' */
	ZBX_HISTORY_FIELD	fields[ZBX_MAX_FIELDS];
};

static ZBX_HISTORY_TABLE ht={
	"proxy_history", "history_lastid", 0,
		{
		{"clock",	ZBX_PROTO_TAG_CLOCK,		ZBX_JSON_TYPE_INT,	NULL},
		{"timestamp",	ZBX_PROTO_TAG_LOGTIMESTAMP,	ZBX_JSON_TYPE_INT,	"0"},
		{"source",	ZBX_PROTO_TAG_LOGSOURCE,	ZBX_JSON_TYPE_STRING,	""},
		{"severity",	ZBX_PROTO_TAG_LOGSEVERITY,	ZBX_JSON_TYPE_INT,	"0"},
		{"value",	ZBX_PROTO_TAG_VALUE,		ZBX_JSON_TYPE_STRING,	NULL},
		{"logeventid",	ZBX_PROTO_TAG_LOGEVENTID,	ZBX_JSON_TYPE_INT,	"0"},
		{NULL}
		}
};

static ZBX_HISTORY_TABLE dht={
	"proxy_dhistory", "dhistory_lastid", 0,
		{
		{"clock",	ZBX_PROTO_TAG_CLOCK,		ZBX_JSON_TYPE_INT,	NULL},
		{"druleid",	ZBX_PROTO_TAG_DRULE,		ZBX_JSON_TYPE_INT,	NULL},
		{"dcheckid",	ZBX_PROTO_TAG_DCHECK,		ZBX_JSON_TYPE_INT,	NULL},
		{"type",	ZBX_PROTO_TAG_TYPE,		ZBX_JSON_TYPE_INT,	NULL},
		{"ip",		ZBX_PROTO_TAG_IP,		ZBX_JSON_TYPE_STRING,	NULL},
		{"port",	ZBX_PROTO_TAG_PORT,	 	ZBX_JSON_TYPE_INT,	"0"},
		{"key_",	ZBX_PROTO_TAG_KEY,		ZBX_JSON_TYPE_STRING,	""},
		{"value",	ZBX_PROTO_TAG_VALUE,		ZBX_JSON_TYPE_STRING,	""},
		{"status",	ZBX_PROTO_TAG_STATUS,		ZBX_JSON_TYPE_INT,	"0"},
		{NULL}
		}
};

static ZBX_HISTORY_TABLE areg={
	"proxy_autoreg_host", "autoreg_host_lastid", 0,
		{
		{"clock",	ZBX_PROTO_TAG_CLOCK,		ZBX_JSON_TYPE_INT,	NULL},
		{"host",	ZBX_PROTO_TAG_HOST,		ZBX_JSON_TYPE_STRING,	NULL},
		{NULL}
		}
};

#define ZBX_SENDER_TABLE_COUNT 6

/******************************************************************************
 *                                                                            *
 * Function: get_lastid                                                       *
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
static void	get_lastid(const ZBX_HISTORY_TABLE *ht, zbx_uint64_t *lastid)
{
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In get_lastid() [%s.%s]",
			ht->table,
			ht->lastfieldname);

	result = DBselect("select nextid from ids where table_name='%s' and field_name='%s'",
			ht->table,
			ht->lastfieldname);

	if (NULL == (row = DBfetch(result)))
		*lastid = 0;
	else
		ZBX_STR2UINT64(*lastid, row[0])

	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of get_lastid() [%s.%s]:" ZBX_FS_UI64,
			ht->table,
			ht->lastfieldname,
			*lastid);
}

/******************************************************************************
 *                                                                            *
 * Function: set_lastid                                                       *
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
static void	set_lastid(const ZBX_HISTORY_TABLE *ht, const zbx_uint64_t lastid)
{
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In set_lastid() [%s.%s:" ZBX_FS_UI64 "]",
			ht->table,
			ht->lastfieldname,
			lastid);

	result = DBselect("select 1 from ids where table_name='%s' and field_name='%s'",
			ht->table,
			ht->lastfieldname);

	if (NULL == (row = DBfetch(result)))
		DBexecute("insert into ids (table_name,field_name,nextid)"
				"values ('%s','%s'," ZBX_FS_UI64 ")",
				ht->table,
				ht->lastfieldname,
				lastid);
	else
		DBexecute("update ids set nextid=" ZBX_FS_UI64
				" where table_name='%s' and field_name='%s'",
				lastid,
				ht->table,
				ht->lastfieldname);

	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: get_host_availability_data                                       *
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
static int	get_host_availability_data(struct zbx_json *j)
{
	typedef struct zbx_host_available {
		zbx_uint64_t	hostid;
		unsigned char	available, snmp_available, ipmi_available;
		char		*error, *snmp_error, *ipmi_error;
	} t_zbx_host_available;

	const char			*__function_name = "get_host_availability_data";
	zbx_uint64_t			hostid;
	size_t				sz;
	DB_RESULT			result;
	DB_ROW				row;
	static t_zbx_host_available	*ha = NULL;
	static int			ha_alloc = 0, ha_num = 0;
	int				index, new, ret = FAIL;
	unsigned char			available, snmp_available, ipmi_available;
	char				*error, *snmp_error, *ipmi_error;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select hostid,available,error,snmp_available,snmp_error,"
				"ipmi_available,ipmi_error"
			" from hosts");

	while (NULL != (row = DBfetch(result))) {
		ZBX_STR2UINT64(hostid, row[0]);

		new = 0;

		index = get_nearestindex(ha, sizeof(t_zbx_host_available), ha_num, hostid);
		if (index == ha_num || ha[index].hostid != hostid)
		{
			if (ha_num == ha_alloc)
			{
				ha_alloc += 8;
				ha = zbx_realloc(ha, sizeof(t_zbx_host_available) * ha_alloc);
			}

			if (0 != (sz = sizeof(t_zbx_host_available) * (ha_num - index)))
				memmove(&ha[index + 1], &ha[index], sz);
			ha_num++;

			ha[index].hostid = hostid;
			ha[index].available = HOST_AVAILABLE_UNKNOWN;
			ha[index].snmp_available = HOST_AVAILABLE_UNKNOWN;
			ha[index].ipmi_available = HOST_AVAILABLE_UNKNOWN;
			ha[index].error = NULL;
			ha[index].snmp_error = NULL;
			ha[index].ipmi_error = NULL;

			new = 1;
		}

		available = (unsigned char)atoi(row[1]);
		error = row[2];
		snmp_available = (unsigned char)atoi(row[3]);
		snmp_error = row[4];
		ipmi_available = (unsigned char)atoi(row[5]);
		ipmi_error = row[6];

		if (0 == new && ha[index].available == available &&
				ha[index].snmp_available == snmp_available &&
				ha[index].ipmi_available == ipmi_available &&
				0 == strcmp(ha[index].error, error) &&
				0 == strcmp(ha[index].snmp_error, snmp_error) &&
				0 == strcmp(ha[index].ipmi_error, ipmi_error))
			continue;

		zbx_json_addobject(j, NULL);

		zbx_json_adduint64(j, ZBX_PROTO_TAG_HOSTID, hostid);

		if (1 == new || ha[index].available != available)
		{
			zbx_json_adduint64(j, ZBX_PROTO_TAG_AVAILABLE, available);
			ha[index].available = available;
		}

		if (1 == new || ha[index].snmp_available != snmp_available)
		{
			zbx_json_adduint64(j, ZBX_PROTO_TAG_SNMP_AVAILABLE, snmp_available);
			ha[index].snmp_available = snmp_available;
		}

		if (1 == new || ha[index].ipmi_available != ipmi_available)
		{
			zbx_json_adduint64(j, ZBX_PROTO_TAG_IPMI_AVAILABLE, ipmi_available);
			ha[index].ipmi_available = ipmi_available;
		}

		if (1 == new || 0 != strcmp(ha[index].error, error))
		{
			zbx_json_addstring(j, ZBX_PROTO_TAG_ERROR, error, ZBX_JSON_TYPE_STRING);
			zbx_free(ha[index].error);
			ha[index].error = strdup(error);
		}

		if (1 == new || 0 != strcmp(ha[index].snmp_error, snmp_error))
		{
			zbx_json_addstring(j, ZBX_PROTO_TAG_SNMP_ERROR, snmp_error, ZBX_JSON_TYPE_STRING);
			zbx_free(ha[index].snmp_error);
			ha[index].snmp_error = strdup(snmp_error);
		}

		if (1 == new || 0 != strcmp(ha[index].ipmi_error, ipmi_error))
		{
			zbx_json_addstring(j, ZBX_PROTO_TAG_IPMI_ERROR, ipmi_error, ZBX_JSON_TYPE_STRING);
			zbx_free(ha[index].ipmi_error);
			ha[index].ipmi_error = strdup(ipmi_error);
		}

		zbx_json_close(j);

		ret = SUCCEED;
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
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
static int get_history_data(struct zbx_json *j, const ZBX_HISTORY_TABLE *ht, zbx_uint64_t *lastid, int *lastclock)
{
	int		offset = 0, f, records = 0;
	char		sql[MAX_STRING_LEN];
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	id;

	zabbix_log(LOG_LEVEL_DEBUG, "In get_history_data() [table:%s]",
			ht->table);

	*lastid = 0;

	get_lastid(ht, &id);

	offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, "select p.id,h.host,i.key_");

	for (f = 0; ht->fields[f].field != NULL; f ++)
		offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, ",p.%s",
				ht->fields[f].field);

	offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, " from hosts h,items i,%s p"
			" where h.hostid=i.hostid and i.itemid=p.itemid and p.id>" ZBX_FS_UI64 " order by p.id",
			ht->table,
			id);

	result = DBselectN(sql, ZBX_MAX_HRECORDS);

	while (NULL != (row = DBfetch(result))) {
		zbx_json_addobject(j, NULL);

		ZBX_STR2UINT64(*lastid, row[0])
		zbx_json_addstring(j, ZBX_PROTO_TAG_HOST, row[1], ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(j, ZBX_PROTO_TAG_KEY, row[2], ZBX_JSON_TYPE_STRING);
		*lastclock = atoi(row[ht->cidx + 3]);

		for (f = 0; ht->fields[f].field != NULL; f ++)
		{
			if (NULL != ht->fields[f].default_value && 0 == strcmp(row[f + 3], ht->fields[f].default_value))
				continue;

			zbx_json_addstring(j, ht->fields[f].tag, row[f + 3], ht->fields[f].jt);
		}

		records++;

		zbx_json_close(j);
	}

	DBfree_result(result);

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
static int get_dhistory_data(struct zbx_json *j, const ZBX_HISTORY_TABLE *ht, zbx_uint64_t *lastid, int *lastclock)
{
	int		offset = 0, f, records = 0;
	char		sql[MAX_STRING_LEN];
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	id;

	zabbix_log(LOG_LEVEL_DEBUG, "In get_dhistory_data() [table:%s]",
			ht->table);

	*lastid = 0;

	get_lastid(ht, &id);

	offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, "select id");

	for (f = 0; ht->fields[f].field != NULL; f ++)
		offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, ",%s",
				ht->fields[f].field);

	offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, " from %s"
			" where id>" ZBX_FS_UI64 " order by id",
			ht->table,
			id);

	result = DBselectN(sql, ZBX_MAX_HRECORDS);

	while (NULL != (row = DBfetch(result))) {
		zbx_json_addobject(j, NULL);

		ZBX_STR2UINT64(*lastid, row[0])
		*lastclock = atoi(row[ht->cidx + 1]);

		for (f = 0; ht->fields[f].field != NULL; f ++)
		{
			if (NULL != ht->fields[f].default_value && 0 == strcmp(row[f + 1], ht->fields[f].default_value))
				continue;

			zbx_json_addstring(j, ht->fields[f].tag, row[f + 1], ht->fields[f].jt);
		}

		records++;

		zbx_json_close(j);
	}

	DBfree_result(result);

	return records;
}

/******************************************************************************
 *                                                                            *
 * Function: host_availability_sender                                         *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	host_availability_sender(struct zbx_json *j)
{
	zbx_sock_t	sock;

	zabbix_log(LOG_LEVEL_DEBUG, "In host_availability_sender()");

	zbx_json_clean(j);
	zbx_json_addstring(j, ZBX_PROTO_TAG_REQUEST, ZBX_PROTO_VALUE_HOST_AVAILABILITY, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(j, ZBX_PROTO_TAG_HOST, CONFIG_HOSTNAME, ZBX_JSON_TYPE_STRING);

	zbx_json_addarray(j, ZBX_PROTO_TAG_DATA);

	if (SUCCEED == get_host_availability_data(j))
	{
retry:
		if (SUCCEED == connect_to_server(&sock, 600))	/* alarm !!! */
		{
			put_data_to_server(&sock, j);
			disconnect_server(&sock);
		}
		else
		{
			sleep(CONFIG_DATASENDER_FREQUENCY);
			goto retry;
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: history_sender                                                   *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	history_sender(struct zbx_json *j, int *records, int *lastclock)
{
	zbx_sock_t	sock;
	zbx_uint64_t	lastid;

	zabbix_log(LOG_LEVEL_DEBUG, "In history_sender()");

	*lastclock = 0;

	zbx_json_clean(j);
	zbx_json_addstring(j, ZBX_PROTO_TAG_REQUEST, ZBX_PROTO_VALUE_HISTORY_DATA, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(j, ZBX_PROTO_TAG_HOST, CONFIG_HOSTNAME, ZBX_JSON_TYPE_STRING);

	zbx_json_addarray(j, ZBX_PROTO_TAG_DATA);

	*records = get_history_data(j, &ht, &lastid, lastclock);

	zbx_json_close(j);

	zbx_json_adduint64(j, ZBX_PROTO_TAG_CLOCK, (int)time(NULL));

	if (*records > 0)
	{
retry:
		if (SUCCEED == connect_to_server(&sock, 600))	/* alarm !!! */
		{
			if (SUCCEED == put_data_to_server(&sock, j))
			{
				DBbegin();
				set_lastid(&ht, lastid);
				DBcommit();
			}
			else
				*records = 0;

			disconnect_server(&sock);
		}
		else
		{
			sleep(CONFIG_DATASENDER_FREQUENCY);
			goto retry;
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: dhistory_sender                                                  *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	dhistory_sender(struct zbx_json *j, int *records, int *lastclock)
{
	zbx_sock_t	sock;
	zbx_uint64_t	lastid;

	zabbix_log(LOG_LEVEL_DEBUG, "In dhistory_sender()");

	*lastclock = 0;

	zbx_json_clean(j);
	zbx_json_addstring(j, ZBX_PROTO_TAG_REQUEST, ZBX_PROTO_VALUE_DISCOVERY_DATA, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(j, ZBX_PROTO_TAG_HOST, CONFIG_HOSTNAME, ZBX_JSON_TYPE_STRING);

	zbx_json_addarray(j, ZBX_PROTO_TAG_DATA);

	*records = get_dhistory_data(j, &dht, &lastid, lastclock);

	zbx_json_close(j);

	zbx_json_adduint64(j, ZBX_PROTO_TAG_CLOCK, (int)time(NULL));

	if (*records > 0)
	{
retry:
		if (SUCCEED == connect_to_server(&sock, 600))	/* alarm !!! */
		{
			if (SUCCEED == put_data_to_server(&sock, j))
			{
				DBbegin();
				set_lastid(&dht, lastid);
				DBcommit();
			}
			else
				*records = 0;

			disconnect_server(&sock);
		}
		else
		{
			sleep(CONFIG_DATASENDER_FREQUENCY);
			goto retry;
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: autoreg_host_sender                                              *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	autoreg_host_sender(struct zbx_json *j, int *records, int *lastclock)
{
	zbx_sock_t	sock;
	zbx_uint64_t	lastid;

	zabbix_log(LOG_LEVEL_DEBUG, "In autoreg_host_sender()");

	*lastclock = 0;

	zbx_json_clean(j);
	zbx_json_addstring(j, ZBX_PROTO_TAG_REQUEST, ZBX_PROTO_VALUE_AUTO_REGISTRATION_DATA, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(j, ZBX_PROTO_TAG_HOST, CONFIG_HOSTNAME, ZBX_JSON_TYPE_STRING);

	zbx_json_addarray(j, ZBX_PROTO_TAG_DATA);

	*records = get_dhistory_data(j, &areg, &lastid, lastclock);

	zbx_json_close(j);

	zbx_json_adduint64(j, ZBX_PROTO_TAG_CLOCK, (int)time(NULL));

	if (*records > 0)
	{
retry:
		if (SUCCEED == connect_to_server(&sock, 600))	/* alarm !!! */
		{
			if (SUCCEED == put_data_to_server(&sock, j))
			{
				DBbegin();
				set_lastid(&areg, lastid);
				DBcommit();
			}
			else
				*records = 0;

			disconnect_server(&sock);
		}
		else
		{
			sleep(CONFIG_DATASENDER_FREQUENCY);
			goto retry;
		}
	}
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
				records, r;
	double			sec;
	struct zbx_json		j;
	int			lastclock;

	zabbix_log(LOG_LEVEL_DEBUG, "In main_datasender_loop()");

	phan.sa_sigaction = child_signal_handler;
	sigemptyset(&phan.sa_mask);
	phan.sa_flags = SA_SIGINFO;
	sigaction(SIGALRM, &phan, NULL);

	zbx_setproctitle("data sender [connecting to the database]");

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	zbx_json_init(&j, 16*1024);

	for (;;) {
		now = time(NULL);
		sec = zbx_time();

		zbx_setproctitle("data sender [sending data]");

		host_availability_sender(&j);

		records = 0;
retry_history:
		history_sender(&j, &r, &lastclock);
		records += r;

		if (r == ZBX_MAX_HRECORDS && lastclock && time(NULL) - lastclock > CONFIG_DATASENDER_FREQUENCY * 2)
			goto retry_history;

retry_dhistory:
		dhistory_sender(&j, &r, &lastclock);
		records += r;

		if (r == ZBX_MAX_HRECORDS && lastclock && time(NULL) - lastclock > CONFIG_DATASENDER_FREQUENCY * 2)
			goto retry_dhistory;

retry_autoreg_host:
		autoreg_host_sender(&j, &r, &lastclock);
		records += r;

		if (r == ZBX_MAX_HRECORDS && lastclock && time(NULL) - lastclock > CONFIG_DATASENDER_FREQUENCY * 2)
			goto retry_autoreg_host;

		zabbix_log(LOG_LEVEL_DEBUG, "Datasender spent " ZBX_FS_DBL " seconds while processing %3d values.",
				zbx_time() - sec,
				records);

		sleeptime = CONFIG_DATASENDER_FREQUENCY;

		if (sleeptime > 0) {
			zbx_setproctitle("data sender [sleeping for %d seconds]",
					sleeptime);
			zabbix_log(LOG_LEVEL_DEBUG, "Sleeping for %d seconds",
					sleeptime);
			sleep(sleeptime);
		}
	}

	zbx_json_free(&j);

	DBclose();
}
