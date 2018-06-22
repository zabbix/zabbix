/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
#include "export.h"

#include "timer.h"

#define TIMER_DELAY	1

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

		if (0 != (events_num = zbx_process_events(&trigger_diff, &triggerids)))
		{
			*events_count += events_num;

			DCconfig_triggers_apply_changes(&trigger_diff);
			zbx_db_save_trigger_changes(&trigger_diff);
		}

		DBcommit();

		DBupdate_itservices(&trigger_diff);

		DCconfig_unlock_triggers(&triggerids);
		zbx_vector_uint64_clear(&triggerids);

		DCfree_triggers(&trigger_order);

		if (SUCCEED == zbx_is_export_enabled())
			zbx_export_events();

		zbx_clean_events();
	}

	zbx_vector_uint64_destroy(&triggerids);
	zbx_vector_ptr_clear_ext(&trigger_diff, (zbx_clean_func_t)zbx_trigger_diff_free);
	zbx_vector_ptr_destroy(&trigger_diff);
	zbx_vector_ptr_destroy(&trigger_order);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: db_update_host_maintenances                                      *
 *                                                                            *
 * Purpose: update host maintenance properties in database                    *
 *                                                                            *
 ******************************************************************************/
static void	db_update_host_maintenances(const zbx_vector_ptr_t *updates)
{
	int					i;
	const zbx_host_maintenance_diff_t	*diff;
	char					*sql = NULL;
	size_t					sql_alloc = 0, sql_offset = 0;

	do
	{
		DBbegin();
		DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

		for (i = 0; i < updates->values_num; i++)
		{
			char delim = ' ';

			diff = (const zbx_host_maintenance_diff_t *)updates->values[i];

			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update hosts set");

			if (0 != (diff->flags & ZBX_FLAG_HOST_MAINTENANCE_UPDATE_MAINTENANCEID))
			{
				if (0 != diff->maintenanceid)
				{
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cmaintenanceid=" ZBX_FS_UI64,
						delim, diff->maintenanceid);
				}
				else
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cmaintenanceid=null", delim);

				delim = ',';
			}

			if (0 != (diff->flags & ZBX_FLAG_HOST_MAINTENANCE_UPDATE_MAINTENANCE_TYPE))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cmaintenance_type=%u",
						delim, diff->maintenance_type);
				delim = ',';
			}

			if (0 != (diff->flags & ZBX_FLAG_HOST_MAINTENANCE_UPDATE_MAINTENANCE_STATUS))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cmaintenance_status=%u",
						delim, diff->maintenance_status);
				delim = ',';
			}

			if (0 != (diff->flags & ZBX_FLAG_HOST_MAINTENANCE_UPDATE_MAINTENANCE_FROM))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cmaintenance_from=%d",
						delim, diff->maintenance_from);
			}

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where hostid=" ZBX_FS_UI64 ";\n", diff->hostid);

			if (SUCCEED != DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset))
				break;

		}
		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		if (16 < sql_offset)
			DBexecute("%s", sql);
	}
	while (ZBX_DB_DOWN == DBcommit());

	zbx_free(sql);
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
	time_t	maintenance_time;

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

	if (SUCCEED == zbx_is_export_enabled())
		zbx_problems_export_init("timer", process_num);

	maintenance_time = time(NULL) - SEC_PER_MIN;

	for (;;)
	{
		now = time(NULL);

		if (1 == process_num)
		{
			if (now - maintenance_time >= SEC_PER_MIN)
			{
				zbx_vector_ptr_t	updates;

				zbx_dc_update_maintenances();

				zbx_vector_ptr_create(&updates);
				zbx_vector_ptr_reserve(&updates, 100);

				zbx_dc_update_host_maintenances(&updates);

				if (0 != updates.values_num)
				{
					db_update_host_maintenances(&updates);
					zbx_vector_ptr_clear_ext(&updates, (zbx_clean_func_t)zbx_ptr_free);
				}
				zbx_vector_ptr_destroy(&updates);

				maintenance_time = now;
			}
		}

		zbx_sleep_loop(TIMER_DELAY);

#ifdef OLD
		now = time(NULL);
		nextcheck = now + TIMER_DELAY - (now % TIMER_DELAY);
		sleeptime = nextcheck - now;

		/* try flushing correlated event queue */
		if (0 != zbx_flush_correlated_events())
		{
			/* force minimal sleep period if there are still some events left in queue */
			if (1 < sleeptime)
				sleeptime = 1;
		}

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
			goto next;

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
next:
#if !defined(_WINDOWS) && defined(HAVE_RESOLV_H)
		zbx_update_resolver_conf();	/* handle /etc/resolv.conf update */
#else
		;
#endif
#endif
	}

#undef STAT_INTERVAL
}
