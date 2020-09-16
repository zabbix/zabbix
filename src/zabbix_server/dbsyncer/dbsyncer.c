/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

#include "db.h"
#include "log.h"
#include "daemon.h"
#include "zbxself.h"

#include "dbcache.h"
#include "dbsyncer.h"
#include "export.h"

extern int		CONFIG_HISTSYNCER_FREQUENCY;
extern unsigned char	process_type, program_type;
extern int		server_num, process_num;
static sigset_t		orig_mask;

/******************************************************************************
 *                                                                            *
 * Function: block_signals                                                    *
 *                                                                            *
 * Purpose: block signals to avoid interruption                               *
 *                                                                            *
 ******************************************************************************/
static	void	block_signals(void)
{
	sigset_t	mask;

	sigemptyset(&mask);
	sigaddset(&mask, SIGUSR1);
	sigaddset(&mask, SIGUSR2);
	sigaddset(&mask, SIGTERM);
	sigaddset(&mask, SIGINT);
	sigaddset(&mask, SIGQUIT);

	if (0 > sigprocmask(SIG_BLOCK, &mask, &orig_mask))
		zabbix_log(LOG_LEVEL_WARNING, "cannot set sigprocmask to block the signal");
}

/******************************************************************************
 *                                                                            *
 * Function: unblock_signals                                                  *
 *                                                                            *
 * Purpose: unblock signals after blocking                                    *
 *                                                                            *
 ******************************************************************************/
static	void	unblock_signals(void)
{
	if (0 > sigprocmask(SIG_SETMASK, &orig_mask, NULL))
		zabbix_log(LOG_LEVEL_WARNING,"cannot restore sigprocmask");
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_db_flush_timer_queue                                         *
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
		zbx_db_insert_prepare(&db_insert, "trigger_queue", "objectid", "type", "clock", "ns", NULL);

		for (i = 0; i < persistent_timers.values_num; i++)
		{
			zbx_trigger_timer_t	*timer = (zbx_trigger_timer_t *)persistent_timers.values[i];

			zbx_db_insert_add_values(&db_insert, timer->objectid, timer->type, timer->eval_ts.sec,
					timer->eval_ts.ns);
		}

		zbx_dc_free_timers(&persistent_timers);

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
 * Function: main_dbsyncer_loop                                               *
 *                                                                            *
 * Purpose: periodically synchronises data in memory cache with database      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
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
	block_signals();
	DBconnect(ZBX_DB_CONNECT_NORMAL);

	if (1 == process_num)
		db_trigger_queue_cleanup();

	unblock_signals();

	if (SUCCEED == zbx_is_export_enabled())
	{
		zbx_history_export_init("history-syncer", process_num);
		zbx_problems_export_init("history-syncer", process_num);
	}

	for (;;)
	{
		sec = zbx_time();
		zbx_update_env(sec);

		if (0 != sleeptime)
			zbx_setproctitle("%s #%d [%s, syncing history]", process_name, process_num, stats);

		/* clear timer trigger queue to avoid processing time triggers at exit */
		if (!ZBX_IS_RUNNING())
			zbx_log_sync_history_cache_progress();

		/* database APIs might not handle signals correctly and hang, block signals to avoid hanging */
		block_signals();
		zbx_sync_history_cache(&values_num, &triggers_num, &more);

		if (!ZBX_IS_RUNNING() && SUCCEED != zbx_db_trigger_queue_locked())
			zbx_db_flush_timer_queue();

		unblock_signals();

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
	block_signals();
	if (SUCCEED != zbx_db_trigger_queue_locked())
		zbx_db_flush_timer_queue();

	DBclose();
	unblock_signals();

	zbx_log_sync_history_cache_progress();

	zbx_free(stats);

	exit(EXIT_SUCCESS);
#undef STAT_INTERVAL
}
