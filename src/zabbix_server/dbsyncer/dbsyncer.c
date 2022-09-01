/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "dbsyncer.h"

#include "log.h"
#include "zbxnix.h"
#include "zbxself.h"

#include "dbcache.h"
#include "zbxexport.h"

extern int				CONFIG_HISTSYNCER_FREQUENCY;
extern ZBX_THREAD_LOCAL unsigned char	process_type;
extern unsigned char			program_type;
extern ZBX_THREAD_LOCAL int		server_num, process_num;
static sigset_t				orig_mask;

/******************************************************************************
 *                                                                            *
 * Purpose: flush timer queue to the database                                 *
 *                                                                            *
 ******************************************************************************/
static void	zbx_db_flush_timer_queue(void)
{
	int			i;
	zbx_vector_ptr_t	persistent_timers;
	zbx_db_insert_t		db_insert;

	zbx_vector_ptr_create(&persistent_timers);
	zbx_dc_clear_timer_queue(&persistent_timers);

	if (0 != persistent_timers.values_num)
	{
		zbx_db_insert_prepare(&db_insert, "trigger_queue", "trigger_queueid", "objectid", "type", "clock", "ns", NULL);

		for (i = 0; i < persistent_timers.values_num; i++)
		{
			zbx_trigger_timer_t	*timer = (zbx_trigger_timer_t *)persistent_timers.values[i];

			zbx_db_insert_add_values(&db_insert, __UINT64_C(0), timer->objectid, timer->type,
					timer->eval_ts.sec, timer->eval_ts.ns);
		}

		zbx_dc_free_timers(&persistent_timers);

		zbx_db_insert_autoincrement(&db_insert, "trigger_queueid");
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}

	zbx_vector_ptr_destroy(&persistent_timers);
}

static void	db_trigger_queue_cleanup(void)
{
	DBexecute("delete from trigger_queue");
	zbx_db_trigger_queue_unlock();
}

/******************************************************************************
 *                                                                            *
 * Purpose: periodically synchronises data in memory cache with database      *
 *                                                                            *
 * Comments: never returns                                                    *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_ENTRY(dbsyncer_thread, args)
{
	int		sleeptime = -1, total_values_num = 0, values_num, more, total_triggers_num = 0, triggers_num;
	double		sec, total_sec = 0.0;
	time_t		last_stat_time;
	char		*stats = NULL;
	const char	*process_name;
	size_t		stats_alloc = 0, stats_offset = 0;

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type), server_num,
			(process_name = get_process_type_string(process_type)), process_num);

	update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

#define STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	zbx_setproctitle("%s #%d [connecting to the database]", process_name, process_num);
	last_stat_time = time(NULL);

	zbx_strcpy_alloc(&stats, &stats_alloc, &stats_offset, "started");

	/* database APIs might not handle signals correctly and hang, block signals to avoid hanging */
	zbx_block_signals(&orig_mask);
	DBconnect(ZBX_DB_CONNECT_NORMAL);

	if (1 == process_num)
		db_trigger_queue_cleanup();

	zbx_unblock_signals(&orig_mask);

	if (SUCCEED == zbx_is_export_enabled(ZBX_FLAG_EXPTYPE_HISTORY))
		zbx_history_export_init("history-syncer", process_num);

	if (SUCCEED == zbx_is_export_enabled(ZBX_FLAG_EXPTYPE_TRENDS))
		zbx_trends_export_init("history-syncer", process_num);

	if (SUCCEED == zbx_is_export_enabled(ZBX_FLAG_EXPTYPE_EVENTS))
		zbx_problems_export_init("history-syncer", process_num);

	for (;;)
	{
		sec = zbx_time();

		if (0 != sleeptime)
			zbx_setproctitle("%s #%d [%s, syncing history]", process_name, process_num, stats);

		/* clear timer trigger queue to avoid processing time triggers at exit */
		if (!ZBX_IS_RUNNING())
			zbx_log_sync_history_cache_progress();

		/* database APIs might not handle signals correctly and hang, block signals to avoid hanging */
		zbx_block_signals(&orig_mask);
		zbx_sync_history_cache(&values_num, &triggers_num, &more);

		if (!ZBX_IS_RUNNING() && SUCCEED != zbx_db_trigger_queue_locked())
			zbx_db_flush_timer_queue();

		zbx_unblock_signals(&orig_mask);

		total_values_num += values_num;
		total_triggers_num += triggers_num;
		total_sec += zbx_time() - sec;

		sleeptime = (ZBX_SYNC_MORE == more ? 0 : CONFIG_HISTSYNCER_FREQUENCY);

		if (0 != sleeptime || STAT_INTERVAL <= time(NULL) - last_stat_time)
		{
			stats_offset = 0;
			zbx_snprintf_alloc(&stats, &stats_alloc, &stats_offset, "processed %d values", total_values_num);

			if (0 != (program_type & ZBX_PROGRAM_TYPE_SERVER))
			{
				zbx_snprintf_alloc(&stats, &stats_alloc, &stats_offset, ", %d triggers",
						total_triggers_num);
			}

			zbx_snprintf_alloc(&stats, &stats_alloc, &stats_offset, " in " ZBX_FS_DBL " sec", total_sec);

			if (0 == sleeptime)
				zbx_setproctitle("%s #%d [%s, syncing history]", process_name, process_num, stats);
			else
				zbx_setproctitle("%s #%d [%s, idle %d sec]", process_name, process_num, stats, sleeptime);

			total_values_num = 0;
			total_triggers_num = 0;
			total_sec = 0.0;
			last_stat_time = time(NULL);
		}

		if (ZBX_SYNC_MORE == more)
			continue;

		if (!ZBX_IS_RUNNING())
			break;

		zbx_sleep_loop(sleeptime);
	}

	/* database APIs might not handle signals correctly and hang, block signals to avoid hanging */
	zbx_block_signals(&orig_mask);
	if (SUCCEED != zbx_db_trigger_queue_locked())
		zbx_db_flush_timer_queue();

	DBclose();
	zbx_unblock_signals(&orig_mask);

	zbx_log_sync_history_cache_progress();

	zbx_free(stats);

	exit(EXIT_SUCCESS);
#undef STAT_INTERVAL
}
