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
#include "pid.h"
#include "db.h"
#include "log.h"
#include "../events.h"
#include "dbcache.h"
#include "zbxserver.h"
#include "daemon.h"
#include "zbxself.h"

#include "timer.h"

#define TIMER_DELAY	30

#define ZBX_TRIGGERS_MAX	1000

extern unsigned char	process_type, program_type;
extern int		server_num, process_num;

/******************************************************************************
 *                                                                            *
 * Function: process_time_functions                                           *
 *                                                                            *
 * Purpose: re-calculate and update values of time-driven functions           *
 *                                                                            *
 * Author: Alexei Vladishev, Aleksandrs Saveljevs                             *
 *                                                                            *
 ******************************************************************************/
static void	process_time_functions(int *triggers_count, int *events_count)
{
	const char		*__function_name = "process_time_functions";
	DC_TRIGGER		trigger_info[ZBX_TRIGGERS_MAX];
	zbx_vector_ptr_t	trigger_order, trigger_diff;
	zbx_vector_uint64_t	triggerids;
	int			events_num, i;
	zbx_uint64_t		next_triggerid = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_ptr_create(&trigger_order);
	zbx_vector_ptr_reserve(&trigger_order, ZBX_TRIGGERS_MAX);
	zbx_vector_ptr_create(&trigger_diff);
	zbx_vector_uint64_create(&triggerids);

	while (0 != DCconfig_get_time_based_triggers(trigger_info, &trigger_order, ZBX_TRIGGERS_MAX, next_triggerid,
			process_num))
	{
		for (i = 0; i < trigger_order.values_num; i++)
			zbx_vector_uint64_append(&triggerids, trigger_info[i].triggerid);

		next_triggerid = trigger_info[trigger_order.values_num - 1].triggerid + 1;

		*triggers_count += trigger_order.values_num;

		evaluate_expressions(&trigger_order);

		DBbegin();

		zbx_process_triggers(&trigger_order, &trigger_diff);

		if (0 != (events_num = process_trigger_events(&trigger_diff, &triggerids,
				ZBX_EVENTS_PROCESS_CORRELATION)))
		{
			*events_count += events_num;

			DCconfig_triggers_apply_changes(&trigger_diff);
			zbx_save_trigger_changes(&trigger_diff);
		}

		DBcommit();

		DCconfig_unlock_triggers(&triggerids);
		zbx_vector_uint64_clear(&triggerids);

		DCfree_triggers(&trigger_order);
	}

	zbx_vector_uint64_destroy(&triggerids);
	zbx_vector_ptr_clear_ext(&trigger_diff, (zbx_clean_func_t)zbx_trigger_diff_free);
	zbx_vector_ptr_destroy(&trigger_diff);
	zbx_vector_ptr_destroy(&trigger_order);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

typedef struct
{
	zbx_uint64_t	hostid;
	char		*host;
	time_t		maintenance_from;
	zbx_uint64_t	maintenanceid;
	zbx_uint64_t	host_maintenanceid;
	int		maintenance_type;
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

/******************************************************************************
 *                                                                            *
 * Function: get_maintenance_groups                                           *
 *                                                                            *
 * Purpose: get groups (including nested groups) assigned to a maintenance    *
 *          period                                                            *
 *                                                                            *
 * Parameters: maintenanceid - [IN] the maintenance period id                 *
 *             groupids      - [OUT] the group ids                            *
 *                                                                            *
 ******************************************************************************/
static void	get_maintenance_groups(zbx_uint64_t maintenanceid, zbx_vector_uint64_t *groupids)
{
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	parent_groupids;

	zbx_vector_uint64_create(&parent_groupids);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select groupid from maintenances_groups where maintenanceid=" ZBX_FS_UI64, maintenanceid);

	DBselect_uint64(sql, &parent_groupids);

	zbx_dc_get_nested_hostgroupids(parent_groupids.values, parent_groupids.values_num, groupids);

	zbx_free(sql);
	zbx_vector_uint64_destroy(&parent_groupids);
}

static void	process_maintenance_hosts(zbx_host_maintenance_t **hm, int *hm_alloc, int *hm_count,
		time_t maintenance_from, zbx_uint64_t maintenanceid, int maintenance_type)
{
	const char		*__function_name = "process_maintenance_hosts";
	DB_RESULT		result;
	DB_ROW			row;
	zbx_uint64_t		host_hostid, host_maintenanceid;
	int			host_maintenance_status, host_maintenance_type, host_maintenance_from;
	zbx_vector_uint64_t	groupids;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	assert(maintenanceid);

	result = DBselect(
			"select h.hostid,h.host,h.maintenanceid,h.maintenance_status,"
				"h.maintenance_type,h.maintenance_from"
			" from maintenances_hosts mh,hosts h"
			" where mh.hostid=h.hostid"
				" and h.status in (%d,%d)"
				" and mh.maintenanceid=" ZBX_FS_UI64,
			HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED,
			maintenanceid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(host_hostid, row[0]);
		ZBX_DBROW2UINT64(host_maintenanceid, row[2]);
		host_maintenance_status = atoi(row[3]);
		host_maintenance_type = atoi(row[4]);
		host_maintenance_from = atoi(row[5]);

		get_host_maintenance(hm, hm_alloc, hm_count, host_hostid, row[1], maintenance_from, maintenanceid,
				maintenance_type, host_maintenanceid, host_maintenance_status, host_maintenance_type,
				host_maintenance_from);
	}
	DBfree_result(result);

	/* get hosts by assigned maintenance groups */

	zbx_vector_uint64_create(&groupids);
	get_maintenance_groups(maintenanceid, &groupids);

	if (0 != groupids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select h.hostid,h.host,h.maintenanceid,h.maintenance_status,"
					"h.maintenance_type,h.maintenance_from"
				" from hosts_groups hg,hosts h"
				" where hg.hostid=h.hostid"
					" and h.status in (%d,%d)"
					" and",
				HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED);

		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hg.groupid", groupids.values,
				groupids.values_num);

		result = DBselect("%s", sql);

		zbx_free(sql);
		zbx_vector_uint64_destroy(&groupids);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(host_hostid, row[0]);
			ZBX_DBROW2UINT64(host_maintenanceid, row[2]);
			host_maintenance_status = atoi(row[3]);
			host_maintenance_type = atoi(row[4]);
			host_maintenance_from = atoi(row[5]);

			get_host_maintenance(hm, hm_alloc, hm_count, host_hostid, row[1], maintenance_from, maintenanceid,
					maintenance_type, host_maintenanceid, host_maintenance_status, host_maintenance_type,
					host_maintenance_from);
		}
		DBfree_result(result);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static int	update_maintenance_hosts(zbx_host_maintenance_t *hm, int hm_count)
{
	const char	*__function_name = "update_maintenance_hosts";
	int		i;
	zbx_uint64_t	*ids = NULL, hostid;
	int		ids_alloc = 0, ids_num = 0;
	DB_RESULT	result;
	DB_ROW		row;
	char		*sql = NULL;
	size_t		sql_alloc = ZBX_KIBIBYTE, sql_offset;
	int		ret = 0;

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
			zabbix_log(LOG_LEVEL_DEBUG, "putting host '%s' into maintenance (%s)",
					hm[i].host, MAINTENANCE_TYPE_NORMAL == hm[i].maintenance_type ?
					"with data collection" : "without data collection");

			sql_offset = 0;
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"update hosts"
					" set maintenanceid=" ZBX_FS_UI64 ","
						"maintenance_status=%d,"
						"maintenance_type=%d",
					hm[i].maintenanceid,
					HOST_MAINTENANCE_STATUS_ON,
					hm[i].maintenance_type);

			if (0 == hm[i].host_maintenance_from)
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, ",maintenance_from=%d",
						hm[i].maintenance_from);
			}

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where hostid=" ZBX_FS_UI64,
					hm[i].hostid);

			DBexecute("%s", sql);

			DCconfig_set_maintenance(&hm[i].hostid, 1, HOST_MAINTENANCE_STATUS_ON,
					hm[i].maintenance_type, hm[i].maintenance_from);

			ret++;
		}

		uint64_array_add(&ids, &ids_alloc, &ids_num, hm[i].hostid, 4);
	}

	sql_offset = 0;
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select hostid,host,maintenance_type,maintenance_from"
			" from hosts"
			" where status=%d"
				" and flags<>%d"
				" and maintenance_status=%d",
			HOST_STATUS_MONITORED, ZBX_FLAG_DISCOVERY_PROTOTYPE, HOST_MAINTENANCE_STATUS_ON);

	if (NULL != ids && 0 != ids_num)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " and not");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", ids, ids_num);
	}

	result = DBselect("%s", sql);

	ids_num = 0;

	while (NULL != (row = DBfetch(result)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "taking host '%s' out of maintenance", row[1]);

		ZBX_STR2UINT64(hostid, row[0]);

		uint64_array_add(&ids, &ids_alloc, &ids_num, hostid, 4);
	}
	DBfree_result(result);

	sql_offset = 0;
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"update hosts"
			" set maintenanceid=null,"
				"maintenance_status=%d,"
				"maintenance_type=%d,"
				"maintenance_from=0"
			" where",
			HOST_MAINTENANCE_STATUS_OFF,
			MAINTENANCE_TYPE_NORMAL);

	if (NULL != ids && 0 != ids_num)
	{
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", ids, ids_num);
		DBexecute("%s", sql);

		DCconfig_set_maintenance(ids, ids_num, HOST_MAINTENANCE_STATUS_OFF, 0, 0);

		ret += ids_num;
	}

	DBcommit();

	zbx_free(sql);
	zbx_free(ids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return ret;
}

static int	process_maintenance(void)
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
	int				hm_count = 0, ret;

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
		db_active_since		= atoi(row[2]);
		db_timeperiod_type	= atoi(row[3]);
		db_every		= atoi(row[4]);
		db_month		= atoi(row[5]);
		db_dayofweek		= atoi(row[6]);
		db_day			= atoi(row[7]);
		db_start_time		= atoi(row[8]);
		db_period		= atoi(row[9]);
		db_start_date		= atoi(row[10]);

		switch (db_timeperiod_type)
		{
			case TIMEPERIOD_TYPE_ONETIME:
				break;
			case TIMEPERIOD_TYPE_DAILY:
				db_start_date = now - sec + db_start_time;
				if (sec < db_start_time)
					db_start_date -= SEC_PER_DAY;

				if (db_start_date < db_active_since)
					continue;

				tm = localtime(&db_active_since);
				active_since = db_active_since - (tm->tm_hour * SEC_PER_HOUR + tm->tm_min * SEC_PER_MIN
						+ tm->tm_sec);

				day = (db_start_date - active_since) / SEC_PER_DAY;
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
				active_since = db_active_since - (wday * SEC_PER_DAY + tm->tm_hour * SEC_PER_HOUR +
						tm->tm_min * SEC_PER_MIN + tm->tm_sec);

				for (; db_start_date >= db_active_since; db_start_date -= SEC_PER_DAY)
				{
					/* check for every x week(s) */
					week = (db_start_date - active_since) / SEC_PER_WEEK;
					if (0 != week % db_every)
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
							if (tm->tm_mday + 7 <= zbx_day_in_month(1900 + tm->tm_year,
									tm->tm_mon + 1))
							{
								continue;
							}
						}
						else if (db_every != day)
						{
							continue;
						}
					}

					break;
				}
				break;
			default:
				continue;
		}

		/* allow one time periods to start before active time */
		if (db_start_date < db_active_since && TIMEPERIOD_TYPE_ONETIME != db_timeperiod_type)
			continue;

		if (db_start_date > now || now >= db_start_date + db_period)
			continue;

		maintenance_from = db_start_date;

		if (maintenance_from < db_active_since)
			maintenance_from = db_active_since;

		process_maintenance_hosts(&hm, &hm_alloc, &hm_count, maintenance_from, db_maintenanceid,
				db_maintenance_type);
	}
	DBfree_result(result);

	ret = update_maintenance_hosts(hm, hm_count);

	while (0 != hm_count--)
		zbx_free(hm[hm_count].host);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return ret;
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
ZBX_THREAD_ENTRY(timer_thread, args)
{
	int	now, nextcheck, sleeptime = -1,
		triggers_count = 0, events_count = 0, hm_count = 0,
		old_triggers_count = 0, old_events_count = 0, old_hm_count = 0,
		tr_count, ev_count;
	double	sec = 0.0, sec_maint = 0.0,
		total_sec = 0.0, total_sec_maint = 0.0,
		old_total_sec = 0.0, old_total_sec_maint = 0.0;
	time_t	last_stat_time;

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

#define STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	zbx_setproctitle("%s #%d [connecting to the database]", get_process_type_string(process_type), process_num);
	last_stat_time = time(NULL);

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;)
	{
		now = time(NULL);
		nextcheck = now + TIMER_DELAY - (now % TIMER_DELAY);
		sleeptime = nextcheck - now;

		/* flush correlated event queue and set minimal sleep time if queue is not empty */
		if (0 != flush_correlated_events() && 1 < sleeptime)
			sleeptime = 1;

		if (0 != sleeptime || STAT_INTERVAL <= time(NULL) - last_stat_time)
		{
			if (0 == sleeptime)
			{
				if (1 != process_num)
				{
					zbx_setproctitle("%s #%d [processed %d triggers, %d events in " ZBX_FS_DBL
							" sec, processing time functions]",
							get_process_type_string(process_type), process_num,
							triggers_count, events_count, total_sec);
				}
				else
				{
					zbx_setproctitle("%s #1 [processed %d triggers, %d events in " ZBX_FS_DBL
							" sec, %d maintenances in " ZBX_FS_DBL " sec, processing time "
							"functions]",
							get_process_type_string(process_type),
							triggers_count, events_count, total_sec, hm_count,
							total_sec_maint);
				}
			}
			else
			{
				if (1 != process_num)
				{
					zbx_setproctitle("%s #%d [processed %d triggers, %d events in " ZBX_FS_DBL
							" sec, idle %d sec]",
							get_process_type_string(process_type), process_num,
							triggers_count, events_count, total_sec, sleeptime);
				}
				else
				{
					zbx_setproctitle("%s #1 [processed %d triggers, %d events in " ZBX_FS_DBL
							" sec, %d maintenances in " ZBX_FS_DBL " sec, idle %d sec]",
							get_process_type_string(process_type),
							triggers_count, events_count, total_sec, hm_count,
							total_sec_maint, sleeptime);
					old_hm_count = hm_count;
					old_total_sec_maint = total_sec_maint;
				}
				old_triggers_count = triggers_count;
				old_events_count = events_count;
				old_total_sec = total_sec;
			}

			triggers_count = 0;
			events_count = 0;
			hm_count = 0;
			total_sec = 0.0;
			total_sec_maint = 0.0;
			last_stat_time = time(NULL);
		}

		zbx_sleep_loop(sleeptime);

		zbx_handle_log();

		if (0 != sleeptime)
		{
			if (1 != process_num)
			{
				zbx_setproctitle("%s #%d [processed %d triggers, %d events in " ZBX_FS_DBL
						" sec, processing time functions]",
						get_process_type_string(process_type), process_num,
						old_triggers_count, old_events_count, old_total_sec);
			}
			else
			{
				zbx_setproctitle("%s #1 [processed %d triggers, %d events in " ZBX_FS_DBL
						" sec, %d maintenances in " ZBX_FS_DBL " sec, processing time "
						"functions]",
						get_process_type_string(process_type),
						old_triggers_count, old_events_count, old_total_sec, old_hm_count,
						old_total_sec_maint);
			}
		}

		sec = zbx_time();
		tr_count = 0;
		ev_count = 0;
		process_time_functions(&tr_count, &ev_count);
		triggers_count += tr_count;
		events_count += ev_count;
		total_sec += zbx_time() - sec;

		/* only the "timer #1" process evaluates the maintenance periods */
		if (1 != process_num)
			continue;

		/* we process maintenance at every 00 sec */
		/* process time functions can take long time */
		if (0 == nextcheck % SEC_PER_MIN || nextcheck + SEC_PER_MIN - (nextcheck % SEC_PER_MIN) <= time(NULL))
		{
			zbx_setproctitle("%s #1 [processed %d triggers, %d events in " ZBX_FS_DBL
					" sec, %d maintenances in " ZBX_FS_DBL " sec, processing maintenance periods]",
					get_process_type_string(process_type),
					triggers_count, events_count, total_sec, old_hm_count,
					old_total_sec_maint);

			sec_maint = zbx_time();
			hm_count += process_maintenance();
			total_sec_maint += zbx_time() - sec_maint;
		}
	}

#undef STAT_INTERVAL
}
