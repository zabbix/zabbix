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

#include "common.h"

#include "db.h"
#include "daemon.h"
#include "zbxself.h"
#include "log.h"
#include "dbconfig.h"
#include "dbcache.h"
#include "zbxrtc.h"

extern int		CONFIG_CONFSYNCER_FREQUENCY;
extern ZBX_THREAD_LOCAL unsigned char	process_type;
extern unsigned char			program_type;
extern ZBX_THREAD_LOCAL int		server_num, process_num;

static volatile sig_atomic_t	secrets_reload;

static void	zbx_dbconfig_sigusr_handler(int flags)
{
	int	msg;

	/* it is assumed that only one signal is used at a time, any subsequent signals are ignored */
	if (ZBX_RTC_CONFIG_CACHE_RELOAD == (msg = ZBX_RTC_GET_MSG(flags)))
	{
		if (0 < zbx_sleep_get_remainder())
		{
			zabbix_log(LOG_LEVEL_WARNING, "forced reloading of the configuration cache");
			zbx_wakeup();
		}
		else
			zabbix_log(LOG_LEVEL_WARNING, "configuration cache reloading is already in progress");
	}
	else if (ZBX_RTC_SECRETS_RELOAD == msg)
	{
		if (0 < zbx_sleep_get_remainder())
		{
			secrets_reload = 1;
			zabbix_log(LOG_LEVEL_WARNING, "forced reloading of the secrets");
			zbx_wakeup();
		}
		else
			zabbix_log(LOG_LEVEL_WARNING, "configuration cache reloading is already in progress");
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: periodically synchronises database data with memory cache         *
 *                                                                            *
 * Comments: never returns                                                    *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_ENTRY(dbconfig_thread, args)
{
	double	sec = 0.0;
	int	nextcheck = 0;
	char	*error = NULL;

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

	zbx_set_sigusr_handler(zbx_dbconfig_sigusr_handler);

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	sec = zbx_time();
	zbx_setproctitle("%s [syncing configuration]", get_process_type_string(process_type));
	DCsync_configuration(ZBX_DBSYNC_INIT);
	DCsync_kvs_paths(NULL);
	zbx_setproctitle("%s [synced configuration in " ZBX_FS_DBL " sec, idle %d sec]",
			get_process_type_string(process_type), (sec = zbx_time() - sec), CONFIG_CONFSYNCER_FREQUENCY);

	if (SUCCEED != zbx_rtc_notify_config_sync(&error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send configuration syncer notification: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	zbx_sleep_loop(CONFIG_CONFSYNCER_FREQUENCY);

	while (ZBX_IS_RUNNING())
	{
		zbx_setproctitle("%s [synced configuration in " ZBX_FS_DBL " sec, syncing configuration]",
				get_process_type_string(process_type), sec);

		sec = zbx_time();
		zbx_update_env(sec);

		if (1 == secrets_reload)
		{
			DCsync_kvs_paths(NULL);
			secrets_reload = 0;
		}
		else
		{
			DCsync_configuration(ZBX_DBSYNC_UPDATE);
			DCsync_kvs_paths(NULL);
			DCupdate_interfaces_availability();
			nextcheck = time(NULL) + CONFIG_CONFSYNCER_FREQUENCY;
		}

		sec = zbx_time() - sec;

		zbx_setproctitle("%s [synced configuration in " ZBX_FS_DBL " sec, idle %d sec]",
				get_process_type_string(process_type), sec, CONFIG_CONFSYNCER_FREQUENCY);

		zbx_sleep_loop(nextcheck - time(NULL));
	}

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
}
