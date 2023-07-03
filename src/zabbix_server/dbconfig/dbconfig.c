/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#include "zbxnix.h"
#include "zbxself.h"
#include "zbxlog.h"
#include "zbxcachehistory.h"
#include "zbxrtc.h"
#include "zbxtime.h"
#include "zbx_rtc_constants.h"
#include "zbxcachevalue.h"

extern int		CONFIG_CONFSYNCER_FREQUENCY;

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
	int			nextcheck = 0, sleeptime, secrets_reload = 0, cache_reload = 0;
	zbx_ipc_async_socket_t	rtc;
	const zbx_thread_info_t	*info = &((zbx_thread_args_t *)args)->info;
	int			server_num = ((zbx_thread_args_t *)args)->info.server_num;
	int			process_num = ((zbx_thread_args_t *)args)->info.process_num;
	unsigned char		process_type = ((zbx_thread_args_t *)args)->info.process_type;
	zbx_uint32_t		rtc_msgs[] = {ZBX_RTC_CONFIG_CACHE_RELOAD, ZBX_RTC_SECRETS_RELOAD};

	zbx_thread_dbconfig_args	*dbconfig_args_in = (zbx_thread_dbconfig_args *)
			(((zbx_thread_args_t *)args)->args);

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			server_num, get_process_type_string(process_type), process_num);

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

	zbx_rtc_subscribe(process_type, process_num, rtc_msgs, ARRSIZE(rtc_msgs), dbconfig_args_in->config_timeout,
			&rtc);

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));

	zbx_db_connect(ZBX_DB_CONNECT_NORMAL);

	sec = zbx_time();
	zbx_setproctitle("%s [syncing configuration]", get_process_type_string(process_type));
	zbx_dc_sync_configuration(ZBX_DBSYNC_INIT, ZBX_SYNCED_NEW_CONFIG_NO, NULL, dbconfig_args_in->config_vault,
			dbconfig_args_in->proxyconfig_frequency);
	zbx_dc_sync_kvs_paths(NULL, dbconfig_args_in->config_vault, dbconfig_args_in->config_source_ip);
	zbx_setproctitle("%s [synced configuration in " ZBX_FS_DBL " sec, idle %d sec]",
			get_process_type_string(process_type), (sec = zbx_time() - sec), CONFIG_CONFSYNCER_FREQUENCY);

	zbx_rtc_notify_config_sync(dbconfig_args_in->config_timeout, &rtc);

	nextcheck = (int)time(NULL) + CONFIG_CONFSYNCER_FREQUENCY;

	while (ZBX_IS_RUNNING())
	{
		zbx_uint32_t	rtc_cmd;
		unsigned char	*rtc_data;

		sleeptime = nextcheck - (int)time(NULL);

		while (SUCCEED == zbx_rtc_wait(&rtc, info, &rtc_cmd, &rtc_data, sleeptime) && 0 != rtc_cmd)
		{
			if (ZBX_RTC_CONFIG_CACHE_RELOAD == rtc_cmd)
			{
				if (0 == cache_reload)
				{
					zabbix_log(LOG_LEVEL_WARNING, "forced reloading of the configuration cache");
					cache_reload = 1;
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
		zbx_update_env(get_process_type_string(process_type), sec);

		if (0 == secrets_reload)
		{
			zbx_vector_uint64_t	deleted_itemids;

			zbx_vector_uint64_create(&deleted_itemids);

			zbx_dc_sync_configuration(ZBX_DBSYNC_UPDATE, ZBX_SYNCED_NEW_CONFIG_YES, &deleted_itemids,
					dbconfig_args_in->config_vault, dbconfig_args_in->proxyconfig_frequency);
			zbx_dc_sync_kvs_paths(NULL, dbconfig_args_in->config_vault, dbconfig_args_in->config_source_ip);
			zbx_dc_update_interfaces_availability();
			nextcheck = (int)time(NULL) + CONFIG_CONFSYNCER_FREQUENCY;

			zbx_vc_remove_items_by_ids(&deleted_itemids);
			zbx_vector_uint64_destroy(&deleted_itemids);

			if (0 != cache_reload)
			{
				cache_reload = 0;
				zabbix_log(LOG_LEVEL_WARNING, "finished forced reloading of the configuration cache");
			}
		}
		else
		{
			zbx_dc_sync_kvs_paths(NULL, dbconfig_args_in->config_vault, dbconfig_args_in->config_source_ip);
			secrets_reload = 0;
			zabbix_log(LOG_LEVEL_WARNING, "finished forced reloading of the secrets");
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
