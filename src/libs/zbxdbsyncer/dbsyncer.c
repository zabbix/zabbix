/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "zbxdbsyncer.h"

#include "zbxnix.h"
#include "zbxself.h"
#include "zbxtime.h"
#include "zbxcachehistory.h"
#include "zbxexport.h"
#include "zbxprof.h"
#include "zbxtimekeeper.h"
#include "zbxcacheconfig.h"
#include "zbxdb.h"
#include "zbxstr.h"
#include "zbxthreads.h"
#include "zbxrtc.h"
#include "zbx_rtc_constants.h"
#include "zbxipcservice.h"

static sigset_t			orig_mask;

static zbx_export_file_t	*problems_export = NULL;
static zbx_export_file_t	*get_problems_export(void)
{
	return problems_export;
}

static zbx_export_file_t	*history_export = NULL;
static zbx_export_file_t	*get_history_export(void)
{
	return history_export;
}

static zbx_export_file_t	*trends_export = NULL;
static zbx_export_file_t	*get_trends_export(void)
{
	return trends_export;
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush timer queue to the database                                 *
 *                                                                            *
 ******************************************************************************/
static void	zbx_db_flush_timer_queue(void)
{
	zbx_vector_trigger_timer_ptr_t	persistent_timers;
	zbx_db_insert_t			db_insert;

	zbx_vector_trigger_timer_ptr_create(&persistent_timers);
	zbx_dc_clear_timer_queue(&persistent_timers);

	if (0 != persistent_timers.values_num)
	{
		zbx_db_insert_prepare(&db_insert, "trigger_queue", "trigger_queueid", "objectid", "type", "clock", "ns",
				(char *)NULL);

		for (int i = 0; i < persistent_timers.values_num; i++)
		{
			zbx_trigger_timer_t	*timer = persistent_timers.values[i];

			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), timer->objectid, timer->type,
					timer->eval_ts.sec, timer->eval_ts.ns);
		}

		zbx_dc_free_timers(&persistent_timers);

		zbx_db_insert_autoincrement(&db_insert, "trigger_queueid");
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}

	zbx_vector_trigger_timer_ptr_destroy(&persistent_timers);
}

static void	db_trigger_queue_cleanup(void)
{
	zbx_db_execute("delete from trigger_queue");
	zbx_db_trigger_queue_unlock();
}

/******************************************************************************
 *                                                                            *
 * Purpose: periodically synchronises data in memory cache with database      *
 *                                                                            *
 * Comments: never returns                                                    *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_ENTRY(zbx_dbsyncer_thread, args)
{
	int			sleeptime = -1, total_values_num = 0, values_num, more, total_triggers_num = 0,
				triggers_num;
	double			sec, total_sec = 0.0;
	time_t			last_stat_time;
	char			*stats = NULL;
	const char		*process_name;
	size_t			stats_alloc = 0, stats_offset = 0;
	const zbx_thread_info_t	*info = &((zbx_thread_args_t *)args)->info;
	int			server_num = ((zbx_thread_args_t *)args)->info.server_num;
	int			process_num = ((zbx_thread_args_t *)args)->info.process_num;
	unsigned char		process_type = ((zbx_thread_args_t *)args)->info.process_type;
	zbx_uint32_t		rtc_msgs[] = {ZBX_RTC_HISTORY_SYNC_NOTIFY};
	zbx_ipc_async_socket_t	rtc;

	zbx_thread_dbsyncer_args	*dbsyncer_args = (zbx_thread_dbsyncer_args *)
			(((zbx_thread_args_t *)args)->args);

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			server_num, (process_name = get_process_type_string(process_type)), process_num);

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

#define STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	zbx_setproctitle("%s #%d [connecting to the database]", process_name, process_num);
	last_stat_time = time(NULL);

	zbx_strcpy_alloc(&stats, &stats_alloc, &stats_offset, "started");

	/* database APIs might not handle signals correctly and hang, block signals to avoid hanging */
	zbx_block_signals(&orig_mask);
	zbx_db_connect(ZBX_DB_CONNECT_NORMAL);

	if (1 == process_num)
		db_trigger_queue_cleanup();

	zbx_unblock_signals(&orig_mask);

	if (SUCCEED == zbx_is_export_enabled(ZBX_FLAG_EXPTYPE_HISTORY))
		history_export = zbx_history_export_init(get_history_export, "history-syncer", process_num);

	if (SUCCEED == zbx_is_export_enabled(ZBX_FLAG_EXPTYPE_TRENDS))
		trends_export = zbx_trends_export_init(get_trends_export, "history-syncer", process_num);

	if (SUCCEED == zbx_is_export_enabled(ZBX_FLAG_EXPTYPE_EVENTS))
		problems_export = zbx_problems_export_init(get_problems_export, "history-syncer", process_num);

	zbx_rtc_subscribe(process_type, process_num, rtc_msgs, ARRSIZE(rtc_msgs), dbsyncer_args->config_timeout, &rtc);

	for (;;)
	{
		sec = zbx_time();

		zbx_prof_update(get_process_type_string(process_type), sec);
		zabbix_report_log_level_change();

		if (0 != sleeptime)
			zbx_setproctitle("%s #%d [%s, syncing history]", process_name, process_num, stats);

		/* clear timer trigger queue to avoid processing time triggers at exit */
		if (!ZBX_IS_RUNNING())
			zbx_log_sync_history_cache_progress();

		/* database APIs might not handle signals correctly and hang, block signals to avoid hanging */
		zbx_block_signals(&orig_mask);

		zbx_prof_start(__func__, ZBX_PROF_PROCESSING);
		zbx_sync_history_cache(dbsyncer_args->events_cbs, &rtc, dbsyncer_args->config_history_storage_pipelines,
				&values_num, &triggers_num, &more);
		zbx_prof_end();

		if (!ZBX_IS_RUNNING() && SUCCEED != zbx_db_trigger_queue_locked())
			zbx_db_flush_timer_queue();

		zbx_unblock_signals(&orig_mask);

		total_values_num += values_num;
		total_triggers_num += triggers_num;
		total_sec += zbx_time() - sec;

		sleeptime = (ZBX_SYNC_MORE == more ? 0 : dbsyncer_args->config_histsyncer_frequency);

		if (0 != sleeptime || STAT_INTERVAL <= time(NULL) - last_stat_time)
		{
			stats_offset = 0;
			zbx_snprintf_alloc(&stats, &stats_alloc, &stats_offset, "processed %d values",
					total_values_num);

			if (0 != (info->program_type & ZBX_PROGRAM_TYPE_SERVER))
			{
				zbx_snprintf_alloc(&stats, &stats_alloc, &stats_offset, ", %d triggers",
						total_triggers_num);
			}

			zbx_snprintf_alloc(&stats, &stats_alloc, &stats_offset, " in " ZBX_FS_DBL " sec", total_sec);

			if (0 == sleeptime)
			{
				zbx_setproctitle("%s #%d [%s, syncing history]", process_name, process_num, stats);
			}
			else
			{
				zbx_setproctitle("%s #%d [%s, idle %d sec]", process_name, process_num, stats,
						sleeptime);
			}

			total_values_num = 0;
			total_triggers_num = 0;
			total_sec = 0.0;
			last_stat_time = time(NULL);
		}

		zbx_uint32_t	rtc_cmd;
		unsigned char	*rtc_data = NULL;

		while (SUCCEED == zbx_rtc_wait(&rtc, info, &rtc_cmd, &rtc_data, sleeptime) && 0 != rtc_cmd)
		{
			switch (rtc_cmd)
			{
				case ZBX_RTC_HISTORY_SYNC_NOTIFY:
					sleeptime = 0;
				case ZBX_RTC_SHUTDOWN:
				default:
					break;
			}
		}

		if (ZBX_SYNC_MORE == more)
			continue;

		if (!ZBX_IS_RUNNING())
			break;
	}

	if (SUCCEED != zbx_ipc_async_socket_flush(&rtc, dbsyncer_args->config_timeout))
		zabbix_log(LOG_LEVEL_WARNING, "%s #%d cannot flush RTC socket", process_name, process_num);

	/* database APIs might not handle signals correctly and hang, block signals to avoid hanging */
	zbx_block_signals(&orig_mask);
	if (SUCCEED != zbx_db_trigger_queue_locked())
		zbx_db_flush_timer_queue();

	zbx_db_close();
	zbx_unblock_signals(&orig_mask);

	zbx_log_sync_history_cache_progress();

	if (SUCCEED == zbx_is_export_enabled(ZBX_FLAG_EXPTYPE_HISTORY))
		zbx_export_deinit(history_export);

	if (SUCCEED == zbx_is_export_enabled(ZBX_FLAG_EXPTYPE_TRENDS))
		zbx_export_deinit(trends_export);

	if (SUCCEED == zbx_is_export_enabled(ZBX_FLAG_EXPTYPE_EVENTS))
		zbx_export_deinit(problems_export);

	zbx_free(stats);

	exit(EXIT_SUCCESS);
#undef STAT_INTERVAL
}
