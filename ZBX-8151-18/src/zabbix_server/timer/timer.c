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

#include "common.h"

#include "cfg.h"
#include "pid.h"
#include "db.h"
#include "log.h"
#include "../events.h"
#include "dbcache.h"
#include "zlog.h"
#include "zbxserver.h"
#include "daemon.h"
#include "zbxself.h"

#include "timer.h"

#define TIMER_DELAY	30

extern unsigned char	process_type;

/******************************************************************************
 *                                                                            *
 * Function: process_time_functions                                           *
 *                                                                            *
 * Purpose: re-calculate and update values of time-driven functions           *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
static void	process_time_functions()
{
	const char		*__function_name = "process_time_functions";
	DB_RESULT		result;
	DB_ROW			row;
	DB_TRIGGER_UPDATE	*tr = NULL, *tr_last;
	int			tr_alloc = 0, tr_num = 0, events_num = 0;
	char			*sql = NULL;
	int			sql_alloc = 16 * ZBX_KIBIBYTE, sql_offset = 0, i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select distinct t.triggerid,t.type,t.value,t.error,t.expression"
			" from triggers t,functions f,items i,hosts h"
			" where t.triggerid=f.triggerid"
				" and f.itemid=i.itemid"
				" and i.hostid=h.hostid"
				" and t.status=%d"
				" and f.function in (" ZBX_SQL_TIME_FUNCTIONS ")"
				" and i.status=%d"
				" and h.status=%d"
				" and (h.maintenance_status=%d or h.maintenance_type=%d)"
				DB_NODE
			" order by t.triggerid",
			TRIGGER_STATUS_ENABLED,
			ITEM_STATUS_ACTIVE,
			HOST_STATUS_MONITORED,
			HOST_MAINTENANCE_STATUS_OFF, MAINTENANCE_TYPE_NORMAL,
			DBnode_local("h.hostid"));

	while (NULL != (row = DBfetch(result)))
	{
		if (tr_num == tr_alloc)
		{
			tr_alloc += 64;
			tr = zbx_realloc(tr, tr_alloc * sizeof(DB_TRIGGER_UPDATE));
		}

		tr_last = &tr[tr_num++];

		ZBX_STR2UINT64(tr_last->triggerid, row[0]);
		tr_last->type = (unsigned char)atoi(row[1]);
		tr_last->value = atoi(row[2]);
		tr_last->error = zbx_strdup(NULL, row[3]);
		tr_last->new_error = NULL;
		tr_last->expression = zbx_strdup(NULL, row[4]);
		tr_last->lastchange = time(NULL);
	}
	DBfree_result(result);

	if (0 == tr_num)
		goto clean;

	evaluate_expressions(tr, tr_num);

	DBbegin();

	sql = zbx_malloc(sql, sql_alloc);
#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 7, "begin\n");
#endif

	for (i = 0; i < tr_num; i++)
	{
		tr_last = &tr[i];

		if (SUCCEED == DBget_trigger_update_sql(&sql, &sql_alloc, &sql_offset, tr_last->triggerid,
				tr_last->type, tr_last->value, tr_last->error, tr_last->new_value, tr_last->new_error,
				tr_last->lastchange, &tr_last->add_event))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 3, ";\n");

			DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
		}

		if (1 == tr_last->add_event)
			events_num++;

		zbx_free(tr_last->error);
		zbx_free(tr_last->new_error);
		zbx_free(tr_last->expression);
	}

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 6, "end;\n");
#endif

	if (sql_offset > 16)	/* In ORACLE always present begin..end; */
		DBexecute("%s", sql);

	zbx_free(sql);

	if (0 != events_num)
	{
		zbx_uint64_t	eventid;

		eventid = DBget_maxid_num("events", events_num);

		for (i = 0; i < tr_num; i++)
		{
			tr_last = &tr[i];

			if (1 != tr_last->add_event)
				continue;

			process_event(eventid++, EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER, tr_last->triggerid,
					tr_last->lastchange, tr_last->new_value, 0, 0);
		}
	}

	DBcommit();
clean:
	zbx_free(tr);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

typedef struct
{
	zbx_uint64_t	hostid;
	char		*host;
	time_t		maintenance_from;
	zbx_uint64_t	maintenanceid;
	int		maintenance_type;
	zbx_uint64_t	host_maintenanceid;
	int		host_maintenance_status;
	int		host_maintenance_type;
	int		host_maintenance_from;
}
zbx_host_maintenance_t;

static int	get_host_maintenance_nearestindex(zbx_host_maintenance_t *hm, int hm_count,
		zbx_uint64_t hostid, time_t maintenance_from, zbx_uint64_t maintenanceid)
{
	int	first_index, last_index, index;

	if (0 == hm_count)
		return 0;

	first_index = 0;
	last_index = hm_count - 1;

	while (1)
	{
		index = first_index + (last_index - first_index) / 2;

		if (hm[index].hostid == hostid &&
				hm[index].maintenance_from == maintenance_from &&
				hm[index].maintenanceid == maintenanceid)
		{
			return index;
		}
		else if (last_index == first_index)
		{
			if (hm[index].hostid < hostid ||
					(hm[index].hostid == hostid && hm[index].maintenance_from < maintenance_from) ||
					(hm[index].hostid == hostid && hm[index].maintenance_from == maintenance_from &&
					 	hm[index].maintenanceid < maintenanceid))
				index++;

			return index;
		}
		else if (hm[index].hostid < hostid ||
				(hm[index].hostid == hostid && hm[index].maintenance_from < maintenance_from) ||
				(hm[index].hostid == hostid && hm[index].maintenance_from == maintenance_from &&
				 	hm[index].maintenanceid < maintenanceid))
		{
			first_index = index + 1;
		}
		else
			last_index = index;
	}
}

static zbx_host_maintenance_t	*get_host_maintenance(zbx_host_maintenance_t **hm, int *hm_alloc, int *hm_count,
		zbx_uint64_t hostid, const char *host, time_t maintenance_from, zbx_uint64_t maintenanceid,
		int maintenance_type, zbx_uint64_t host_maintenanceid, int host_maintenance_status,
		int host_maintenance_type, int host_maintenance_from)
{
	int	hm_index;

	hm_index = get_host_maintenance_nearestindex(*hm, *hm_count, hostid, maintenance_from, maintenanceid);

	if (hm_index < *hm_count && (*hm)[hm_index].hostid == hostid &&
			(*hm)[hm_index].maintenance_from == maintenance_from &&
			(*hm)[hm_index].maintenanceid == maintenanceid)
	{
		return &(*hm)[hm_index];
	}

	if (*hm_alloc == *hm_count)
	{
		*hm_alloc += 4;
		*hm = zbx_realloc(*hm, *hm_alloc * sizeof(zbx_host_maintenance_t));
	}

	memmove(&(*hm)[hm_index + 1], &(*hm)[hm_index], sizeof(zbx_host_maintenance_t) * (*hm_count - hm_index));

	(*hm)[hm_index].hostid = hostid;
	(*hm)[hm_index].host = zbx_strdup(NULL, host);
	(*hm)[hm_index].maintenance_from = maintenance_from;
	(*hm)[hm_index].maintenanceid = maintenanceid;
	(*hm)[hm_index].maintenance_type = maintenance_type;
	(*hm)[hm_index].host_maintenanceid = host_maintenanceid;
	(*hm)[hm_index].host_maintenance_status = host_maintenance_status;
	(*hm)[hm_index].host_maintenance_type = host_maintenance_type;
	(*hm)[hm_index].host_maintenance_from = host_maintenance_from;
	(*hm_count)++;

	return &(*hm)[hm_index];
}

static void	process_maintenance_hosts(zbx_host_maintenance_t **hm, int *hm_alloc, int *hm_count,
		time_t maintenance_from, zbx_uint64_t maintenanceid, int maintenance_type)
{
	const char	*__function_name = "process_maintenance_hosts";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	host_hostid, host_maintenanceid;
	int		host_maintenance_status, host_maintenance_type, host_maintenance_from;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select h.hostid,h.host,h.maintenanceid,h.maintenance_status,"
				"h.maintenance_type,h.maintenance_from "
			"from maintenances_hosts mh,hosts h "
			"where mh.hostid=h.hostid and "
				"h.status=%d and "
				"mh.maintenanceid=" ZBX_FS_UI64,
			HOST_STATUS_MONITORED,
			maintenanceid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(host_hostid, row[0]);
		ZBX_STR2UINT64(host_maintenanceid, row[2]);
		host_maintenance_status = atoi(row[3]);
		host_maintenance_type = atoi(row[4]);
		host_maintenance_from = atoi(row[5]);

		get_host_maintenance(hm, hm_alloc, hm_count, host_hostid, row[1], maintenance_from, maintenanceid,
				maintenance_type, host_maintenanceid, host_maintenance_status, host_maintenance_type,
				host_maintenance_from);
	}
	DBfree_result(result);

	result = DBselect(
			"select h.hostid,h.host,h.maintenanceid,h.maintenance_status,"
				"h.maintenance_type,h.maintenance_from "
			"from maintenances_groups mg,hosts_groups hg,hosts h "
			"where mg.groupid=hg.groupid and "
				"hg.hostid=h.hostid and "
				"h.status=%d and "
				"mg.maintenanceid=" ZBX_FS_UI64,
			HOST_STATUS_MONITORED,
			maintenanceid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(host_hostid, row[0]);
		ZBX_STR2UINT64(host_maintenanceid, row[2]);
		host_maintenance_status = atoi(row[3]);
		host_maintenance_type = atoi(row[4]);
		host_maintenance_from = atoi(row[5]);

		get_host_maintenance(hm, hm_alloc, hm_count, host_hostid, row[1], maintenance_from, maintenanceid,
				maintenance_type, host_maintenanceid, host_maintenance_status, host_maintenance_type,
				host_maintenance_from);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: get_trigger_values                                               *
 *                                                                            *
 * Purpose: get trigger values for specified period                           *
 *                                                                            *
 * Parameters: triggerid        - [IN] trigger identifier from database       *
 *             maintenance_from - [IN] maintenance period start               *
 *             maintenance_to   - [IN] maintenance period stop                *
 *             value_before     - [OUT] trigger value before maintenance      *
 *             value_inside     - [OUT] trigger value inside maintenance      *
 *                                      (only if value_before=value_after)    *
 *             value_after      - [OUT] trigger value after maintenance       *
 *                                                                            *
 * Return value: SUCCEED if found event with OK or PROBLEM statuses           *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	get_trigger_values(zbx_uint64_t triggerid, int maintenance_from, int maintenance_to,
		int *value_before, int *value_inside, int *value_after)
{
	const char	*__function_name = "get_trigger_values";

	DB_RESULT	result;
	DB_ROW		row;
	char		sql[256];
	int		clock;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() from:'%s %s'", __function_name,
			zbx_date2str(maintenance_from), zbx_time2str(maintenance_from));
	zabbix_log(LOG_LEVEL_DEBUG, "%s() to:'%s %s'", __function_name,
			zbx_date2str(maintenance_to), zbx_time2str(maintenance_to));

	/* check for value after maintenance period */
	zbx_snprintf(sql, sizeof(sql),
			"select clock,value"
			" from events"
			" where source=%d"
				" and object=%d"
				" and objectid=" ZBX_FS_UI64
				" and clock<%d"
				" and value in (%d,%d)"
			" order by object desc,objectid desc,eventid desc",
			EVENT_SOURCE_TRIGGERS,
			EVENT_OBJECT_TRIGGER,
			triggerid,
			maintenance_to,
			TRIGGER_VALUE_FALSE, TRIGGER_VALUE_TRUE);

	result = DBselectN(sql, 1);

	if (NULL != (row = DBfetch(result)))
	{
		clock = atoi(row[0]);
		*value_after = atoi(row[1]);
	}
	else
	{
		clock = 0;
		*value_after = TRIGGER_VALUE_UNKNOWN;
	}
	DBfree_result(result);

	/* if no events inside maintenance */
	if (clock < maintenance_from)
	{
		*value_before = *value_after;
		*value_inside = *value_after;
		goto out;
	}

	/* check for value before maintenance period */
	zbx_snprintf(sql, sizeof(sql),
			"select value"
			" from events"
			" where source=%d"
				" and object=%d"
				" and objectid=" ZBX_FS_UI64
				" and clock<%d"
				" and value in (%d,%d)"
			" order by object desc,objectid desc,eventid desc",
			EVENT_SOURCE_TRIGGERS,
			EVENT_OBJECT_TRIGGER,
			triggerid,
			maintenance_from,
			TRIGGER_VALUE_FALSE, TRIGGER_VALUE_TRUE);

	result = DBselectN(sql, 1);

	if (NULL != (row = DBfetch(result)))
		*value_before = atoi(row[0]);
	else
		*value_before = TRIGGER_VALUE_UNKNOWN;
	DBfree_result(result);

	if (*value_after != *value_before)
	{
		*value_inside = TRIGGER_VALUE_UNKNOWN;	/* not important what value is here */
		goto out;
	}

	/* check for value inside maintenance period */
	result = DBselect(
			"select value"
			" from events"
			" where source=%d"
				" and object=%d"
				" and objectid=" ZBX_FS_UI64
				" and clock between %d and %d"
				" and value in (%d)"
			" order by object desc,objectid desc,eventid desc",
			EVENT_SOURCE_TRIGGERS,
			EVENT_OBJECT_TRIGGER,
			triggerid,
			maintenance_from, maintenance_to - 1,
			*value_after == TRIGGER_VALUE_FALSE ? TRIGGER_VALUE_TRUE : TRIGGER_VALUE_FALSE);

	if (NULL != (row = DBfetch(result)))
		*value_inside = atoi(row[0]);
	else
		*value_inside = *value_before;
	DBfree_result(result);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() before:%d inside:%d after:%d",
			__function_name, *value_before, *value_inside, *value_after);
}

/******************************************************************************
 *                                                                            *
 * Function: generate_events                                                  *
 *                                                                            *
 * Purpose: generate events for triggers after maintenance period             *
 *          The events will be generated only in case of trigger was FALSE    *
 *          before maintenance and became TRUE after maintenance, also in     *
 *          case if it was TRUE before and FALSE after.                       *
 *                                                                            *
 * Parameters: hostid - host identifier from database                         *
 *             maintenance_from, maintenance_to - maintenance period bounds   *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	generate_events(zbx_uint64_t hostid, int maintenance_from, int maintenance_to)
{
	const char		*__function_name = "generate_events";
	DB_RESULT		result;
	DB_ROW			row;
	zbx_uint64_t		triggerid, eventid;
	int			value_before, value_inside, value_after, i;
	DB_TRIGGER_UPDATE	*tr = NULL;
	int			tr_alloc = 0, tr_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select distinct t.triggerid"
			" from triggers t,functions f,items i"
			" where t.triggerid=f.triggerid"
				" and f.itemid=i.itemid"
				" and t.status=%d"
				" and i.status=%d"
				" and i.hostid=" ZBX_FS_UI64,
			TRIGGER_STATUS_ENABLED,
			ITEM_STATUS_ACTIVE,
			hostid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(triggerid, row[0]);

		get_trigger_values(triggerid, maintenance_from, maintenance_to,
				&value_before, &value_inside, &value_after);

		if (value_before == value_inside && value_inside == value_after)
			continue;

		if (tr_num == tr_alloc)
		{
			tr_alloc += 64;
			tr = zbx_realloc(tr, tr_alloc * sizeof(DB_TRIGGER_UPDATE));
		}

		tr[tr_num].triggerid = triggerid;
		tr[tr_num].new_value = value_after;
		tr_num++;
	}
	DBfree_result(result);

	if (0 != tr_num)
	{
		eventid = DBget_maxid_num("events", tr_num);

		for (i = 0; i < tr_num; i++)
		{
			process_event(eventid++, EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER, tr[i].triggerid,
					maintenance_to, tr[i].new_value, 0, 1);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static void	update_maintenance_hosts(zbx_host_maintenance_t *hm, int hm_count, int now)
{
	typedef struct
	{
		zbx_uint64_t	hostid;
		int		maintenance_from;
		void		*next;
	}
	maintenance_t;

	const char	*__function_name = "update_maintenance_hosts";
	int		i;
	zbx_uint64_t	*ids = NULL, hostid;
	int		ids_alloc = 0, ids_num = 0;
	DB_RESULT	result;
	DB_ROW		row;
	char		*sql = NULL;
	int		sql_alloc = 1024, sql_offset;
	maintenance_t	*maintenances = NULL, *m;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	sql = zbx_malloc(sql, sql_alloc);

	DBbegin();

	for (i = 0; i < hm_count; i++)
	{
		if (SUCCEED == uint64_array_exists(ids, ids_num, hm[i].hostid))
			continue;

		if (hm[i].host_maintenanceid != hm[i].maintenanceid ||
				HOST_MAINTENANCE_STATUS_ON != hm[i].host_maintenance_status ||
				hm[i].host_maintenance_type != hm[i].maintenance_type ||
				0 == hm[i].host_maintenance_from)
		{
			zabbix_log(LOG_LEVEL_WARNING, "putting host [%s] into maintenance (with%s data collection)",
					hm[i].host, MAINTENANCE_TYPE_NORMAL == hm[i].maintenance_type ? "" : "out");

			sql_offset = 0;
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 128,
					"update hosts"
					" set maintenanceid=" ZBX_FS_UI64 ","
						"maintenance_status=%d,"
						"maintenance_type=%d",
					hm[i].maintenanceid,
					HOST_MAINTENANCE_STATUS_ON,
					hm[i].maintenance_type);

			if (0 == hm[i].host_maintenance_from)
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 64,
						",maintenance_from=%d",
						hm[i].maintenance_from);

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 64,
					" where hostid=" ZBX_FS_UI64,
					hm[i].hostid);

			DBexecute("%s", sql);

			DCconfig_set_maintenance(&hm[i].hostid, 1, HOST_MAINTENANCE_STATUS_ON,
					hm[i].maintenance_type, hm[i].maintenance_from);
		}

		uint64_array_add(&ids, &ids_alloc, &ids_num, hm[i].hostid, 4);
	}

	sql_offset = 0;
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 128,
			"select hostid,host,maintenance_type,maintenance_from"
			" from hosts"
			" where status=%d"
			" and maintenance_status=%d",
			HOST_STATUS_MONITORED,
			HOST_MAINTENANCE_STATUS_ON);

	if (NULL != ids && 0 != ids_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 16, " and not");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", ids, ids_num);
	}

	result = DBselect("%s", sql);

	ids_num = 0;

	while (NULL != (row = DBfetch(result)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "taking host [%s] out of maintenance", row[1]);

		ZBX_STR2UINT64(hostid, row[0]);

		uint64_array_add(&ids, &ids_alloc, &ids_num, hostid, 4);

		if (MAINTENANCE_TYPE_NORMAL != atoi(row[2]))
			continue;

		m = zbx_malloc(NULL, sizeof(maintenance_t));
		m->hostid = hostid;
		m->maintenance_from = atoi(row[3]);
		m->next = maintenances;
		maintenances = m;
	}
	DBfree_result(result);

	sql_offset = 0;
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 128,
			"update hosts"
			" set maintenanceid=0,"
				"maintenance_status=%d,"
				"maintenance_type=0,"
				"maintenance_from=0"
			" where",
			HOST_MAINTENANCE_STATUS_OFF);

	if (NULL != ids && 0 != ids_num)
	{
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", ids, ids_num);
		DBexecute("%s", sql);

		DCconfig_set_maintenance(ids, ids_num, HOST_MAINTENANCE_STATUS_OFF, 0, 0);
	}

	DBcommit();

	zbx_free(sql);
	zbx_free(ids);

	for (m = maintenances; m != NULL; m = m->next)
		generate_events(m->hostid, m->maintenance_from, now);

	for (m = maintenances; m != NULL; m = maintenances)
	{
		maintenances = m->next;
		zbx_free(m);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static int	day_in_month(int year, int mon)
{
#define is_leap_year(year) (((year % 4) == 0 && (year % 100) != 0) || (year % 400) == 0)
	unsigned char month[12] = { 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 };
	unsigned char month_leap[12] = { 31, 29, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 };

	if (is_leap_year(year))
		return month_leap[mon];
	else
		return month[mon];
}

static void	process_maintenance()
{
	const char			*__function_name = "process_maintenance";
	DB_RESULT			result;
	DB_ROW				row;
	int				day, week, wday, sec;
	struct tm			*tm;
	zbx_uint64_t			db_maintenanceid;
	time_t				now, db_active_since, active_since, db_start_date, maintenance_from;
	zbx_timeperiod_type_t		db_timeperiod_type;
	int				db_every, db_month, db_dayofweek, db_day, db_start_time,
					db_period, db_maintenance_type;
	static zbx_host_maintenance_t	*hm = NULL;
	static int			hm_alloc = 4;
	int				hm_count = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (NULL == hm)
		hm = zbx_malloc(hm, sizeof(zbx_host_maintenance_t) * hm_alloc);

	now = time(NULL);
	tm = localtime(&now);
	sec = tm->tm_hour * SEC_PER_HOUR + tm->tm_min * SEC_PER_MIN + tm->tm_sec;

	result = DBselect(
			"select m.maintenanceid,m.maintenance_type,m.active_since,"
				"tp.timeperiod_type,tp.every,tp.month,tp.dayofweek,"
				"tp.day,tp.start_time,tp.period,tp.start_date"
			" from maintenances m,maintenances_windows mw,timeperiods tp"
			" where m.maintenanceid=mw.maintenanceid"
				" and mw.timeperiodid=tp.timeperiodid"
				" and m.active_since<=%d"
				" and m.active_till>%d",
			now, now);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(db_maintenanceid, row[0]);
		db_maintenance_type	= atoi(row[1]);
		db_active_since		= (time_t)atoi(row[2]);
		db_timeperiod_type	= atoi(row[3]);
		db_every		= atoi(row[4]);
		db_month		= atoi(row[5]);
		db_dayofweek		= atoi(row[6]);
		db_day			= atoi(row[7]);
		db_start_time		= atoi(row[8]);
		db_period		= atoi(row[9]);
		db_start_date		= atoi(row[10]);

		switch (db_timeperiod_type) {
		case TIMEPERIOD_TYPE_ONETIME:
			break;
		case TIMEPERIOD_TYPE_DAILY:
			db_start_date = now - sec + db_start_time;
			if (sec < db_start_time)
				db_start_date -= SEC_PER_DAY;

			if (db_start_date < db_active_since)
				continue;

			tm = localtime(&db_active_since);
			active_since = db_active_since - (tm->tm_hour * SEC_PER_HOUR + tm->tm_min * SEC_PER_MIN + tm->tm_sec);

			day = (db_start_date - active_since) / SEC_PER_DAY + 1;
			db_start_date -= SEC_PER_DAY * (day % db_every);
			break;
		case TIMEPERIOD_TYPE_WEEKLY:
			db_start_date = now - sec + db_start_time;
			if (sec < db_start_time)
				db_start_date -= SEC_PER_DAY;

			if (db_start_date < db_active_since)
				continue;

			tm = localtime(&db_active_since);
			wday = (0 == tm->tm_wday ? 7 : tm->tm_wday) - 1;
			active_since = db_active_since - (wday * SEC_PER_DAY + tm->tm_hour * SEC_PER_HOUR + tm->tm_min * SEC_PER_MIN + tm->tm_sec);

			for (; db_start_date >= db_active_since; db_start_date -= SEC_PER_DAY)
			{
				/* check for every x week(s) */
				week = (db_start_date - active_since) / SEC_PER_WEEK + 1;
				if (0 != (week % db_every))
					continue;

				/* check for day of the week */
				tm = localtime(&db_start_date);
				wday = (0 == tm->tm_wday ? 7 : tm->tm_wday) - 1;
				if (0 == (db_dayofweek & (1 << wday)))
					continue;

				break;
			}
			break;
		case TIMEPERIOD_TYPE_MONTHLY:
			db_start_date = now - sec + db_start_time;
			if (sec < db_start_time)
				db_start_date -= SEC_PER_DAY;

			for (; db_start_date >= db_active_since; db_start_date -= SEC_PER_DAY)
			{
				/* check for month */
				tm = localtime(&db_start_date);
				if (0 == (db_month & (1 << tm->tm_mon)))
					continue;

				if (0 != db_day)
				{
					/* check for day of the month */
					if (db_day != tm->tm_mday)
						continue;
				}
				else
				{
					/* check for day of the week */
					wday = (0 == tm->tm_wday ? 7 : tm->tm_wday) - 1;
					if (0 == (db_dayofweek & (1 << wday)))
						continue;

					/* check for number of day (first, second, third, fourth or last) */
					day = (tm->tm_mday - 1) / 7 + 1;
					if (5 == db_every && 4 == day)
					{
						if (tm->tm_mday + 7 <= day_in_month(tm->tm_year, tm->tm_mon))
							continue;
					}
					else if (db_every != day)
						continue;
				}

				break;
			}
			break;
		default:
			continue;
		}

		if (db_start_date < db_active_since)
			continue;

		if (db_start_date > now || now >= db_start_date + db_period)
			continue;

		maintenance_from = db_start_date;

		process_maintenance_hosts(&hm, &hm_alloc, &hm_count, maintenance_from, db_maintenanceid, db_maintenance_type);
	}
	DBfree_result(result);

	update_maintenance_hosts(hm, hm_count, (int)now);

	while (0 != hm_count--)
		zbx_free(hm[hm_count].host);
}

/******************************************************************************
 *                                                                            *
 * Function: main_timer_loop                                                  *
 *                                                                            *
 * Purpose: periodically updates time-related triggers                        *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: does update once per 30 seconds (hardcoded)                      *
 *                                                                            *
 ******************************************************************************/
void	main_timer_loop()
{
	int	now, nextcheck, sleeptime;

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;)
	{
		now = time(NULL);
		nextcheck = now + TIMER_DELAY - (now % TIMER_DELAY);
		sleeptime = nextcheck - now;

		zbx_sleep_loop(sleeptime);

		zbx_setproctitle("%s [processing time functions]", get_process_type_string(process_type));

		process_time_functions();

		/* we process maintenance at every 00 sec */
		/* process time functions can take long time */
		if (0 == nextcheck % SEC_PER_MIN || nextcheck + SEC_PER_MIN - (nextcheck % SEC_PER_MIN) <= time(NULL))
		{
			zbx_setproctitle("%s [processing maintenance periods]", get_process_type_string(process_type));

			process_maintenance();
		}
	}
}
