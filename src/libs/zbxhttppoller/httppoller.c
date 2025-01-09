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

#include "zbxhttppoller.h"

#include "zbxtimekeeper.h"
#include "zbxdb.h"
#include "zbxlog.h"
#include "zbxnix.h"
#include "zbxself.h"
#include "httptest.h"
#include "zbxtime.h"
#include "zbxthreads.h"

/******************************************************************************
 *                                                                            *
 * Purpose: main loop of processing of httptests                              *
 *                                                                            *
 * Comments: never returns                                                    *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_ENTRY(zbx_httppoller_thread, args)
{
	int					sleeptime = -1, httptests_count = 0, old_httptests_count = 0,
						server_num = ((zbx_thread_args_t *)args)->info.server_num,
						process_num = ((zbx_thread_args_t *)args)->info.process_num;
	double					total_sec = 0.0, old_total_sec = 0.0;
	time_t					last_stat_time, nextcheck = 0;
	const zbx_thread_info_t			*info = &((zbx_thread_args_t *)args)->info;
	unsigned char				process_type = ((zbx_thread_args_t *)args)->info.process_type;

	const zbx_thread_httppoller_args	*httppoller_args_in = (const zbx_thread_httppoller_args *)
						(((zbx_thread_args_t *)args)->args);

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			server_num, get_process_type_string(process_type), process_num);

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

#define STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	zbx_setproctitle("%s #%d [connecting to the database]", get_process_type_string(process_type), process_num);
	last_stat_time = time(NULL);

	zbx_db_connect(ZBX_DB_CONNECT_NORMAL);

	while (ZBX_IS_RUNNING())
	{
		double	sec = zbx_time();

		zbx_update_env(get_process_type_string(process_type), sec);

		if (0 != sleeptime)
		{
			zbx_setproctitle("%s #%d [got %d values in " ZBX_FS_DBL " sec, getting values]",
					get_process_type_string(process_type), process_num, old_httptests_count,
					old_total_sec);
		}

		if ((int)sec >= nextcheck)
		{
			time_t	now;

			httptests_count += process_httptests((int)sec, httppoller_args_in->config_source_ip,
					httppoller_args_in->config_ssl_ca_location,
					httppoller_args_in->config_ssl_cert_location,
					httppoller_args_in->config_ssl_key_location, &nextcheck);
			total_sec += zbx_time() - sec;

			now = time(NULL);

			if (0 == nextcheck || nextcheck > now + POLLER_DELAY)
				nextcheck = now + POLLER_DELAY;
		}

		sleeptime = zbx_calculate_sleeptime(nextcheck, POLLER_DELAY);

		if (0 != sleeptime || STAT_INTERVAL <= time(NULL) - last_stat_time)
		{
			if (0 == sleeptime)
			{
				zbx_setproctitle("%s #%d [got %d values in " ZBX_FS_DBL " sec, getting values]",
						get_process_type_string(process_type), process_num, httptests_count,
						total_sec);
			}
			else
			{
				zbx_setproctitle("%s #%d [got %d values in " ZBX_FS_DBL " sec, idle %d sec]",
						get_process_type_string(process_type), process_num, httptests_count,
						total_sec, sleeptime);
				old_httptests_count = httptests_count;
				old_total_sec = total_sec;
			}
			httptests_count = 0;
			total_sec = 0.0;
			last_stat_time = time(NULL);
		}

		zbx_sleep_loop(info, sleeptime);
	}

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
#undef STAT_INTERVAL
}
