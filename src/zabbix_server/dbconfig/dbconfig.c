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

#include "dbconfig.h"

#include "daemon.h"
#include "zbxself.h"
#include "log.h"
#include "dbcache.h"
#include "zbxrtc.h"

extern int		CONFIG_CONFSYNCER_FREQUENCY;
extern ZBX_THREAD_LOCAL unsigned char	process_type;
extern unsigned char			program_type;
extern ZBX_THREAD_LOCAL int		server_num, process_num;

/******************************************************************************
 *                                                                            *
 * Purpose: periodically synchronises database data with memory cache         *
 *                                                                            *
 * Comments: never returns                                                    *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_ENTRY(dbconfig_thread, args)
{
	double			sec = 0.0;
	int			nextcheck = 0, sleeptime, secrets_reload = 0;
	zbx_ipc_async_socket_t	rtc;

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

	zbx_rtc_subscribe(&rtc, process_type, process_num);

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	sec = zbx_time();
	zbx_setproctitle("%s [syncing configuration]", get_process_type_string(process_type));
	DCsync_configuration(ZBX_DBSYNC_INIT);
	DCsync_kvs_paths(NULL);
	zbx_setproctitle("%s [synced configuration in " ZBX_FS_DBL " sec, idle %d sec]",
			get_process_type_string(process_type), (sec = zbx_time() - sec), CONFIG_CONFSYNCER_FREQUENCY);

	zbx_rtc_notify_config_sync(&rtc);

	nextcheck = (int)time(NULL) + CONFIG_CONFSYNCER_FREQUENCY;

	while (ZBX_IS_RUNNING())
	{
		zbx_uint32_t	rtc_cmd;
		unsigned char	*rtc_data;

		sleeptime = nextcheck - (int)time(NULL);

		while (SUCCEED == zbx_rtc_wait(&rtc, &rtc_cmd, &rtc_data, sleeptime) && 0 != rtc_cmd)
		{
			if (ZBX_RTC_CONFIG_CACHE_RELOAD == rtc_cmd)
			{
				if (0 != nextcheck)
				{
					zabbix_log(LOG_LEVEL_WARNING, "forced reloading of the configuration cache");
					nextcheck = 0;
				}
				else
					zabbix_log(LOG_LEVEL_WARNING, "configuration cache reloading is already in progress");
			}
			else if (ZBX_RTC_SECRETS_RELOAD == rtc_cmd)
			{
				if (0 == secrets_reload)
				{
					zabbix_log(LOG_LEVEL_WARNING, "forced reloading of the secrets");
					secrets_reload = 1;
				}
				else
					zabbix_log(LOG_LEVEL_WARNING, "configuration cache reloading is already in progress");
			}
			else if (ZBX_RTC_SHUTDOWN == rtc_cmd)
				goto stop;

			sleeptime = 0;
		}

		zbx_setproctitle("%s [synced configuration in " ZBX_FS_DBL " sec, syncing configuration]",
				get_process_type_string(process_type), sec);

		sec = zbx_time();
		zbx_update_env(sec);

		if (0 == secrets_reload)
		{
			DCsync_configuration(ZBX_DBSYNC_UPDATE);
			DCsync_kvs_paths(NULL);
			DCupdate_interfaces_availability();
			nextcheck = (int)time(NULL) + CONFIG_CONFSYNCER_FREQUENCY;
		}
		else
		{
			DCsync_kvs_paths(NULL);
			secrets_reload = 0;
		}

		sec = zbx_time() - sec;

		zbx_setproctitle("%s [synced configuration in " ZBX_FS_DBL " sec, idle %d sec]",
				get_process_type_string(process_type), sec, CONFIG_CONFSYNCER_FREQUENCY);
	}
stop:
	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
}
