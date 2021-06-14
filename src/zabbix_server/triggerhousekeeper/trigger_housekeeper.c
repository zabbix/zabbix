/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

#include "trigger_housekeeper.h"

#include "common.h"
#include "log.h"
#include "zbxself.h"
#include "zbxservice.h"
#include "zbxipcservice.h"
#include "daemon.h"
#include "sighandler.h"
#include "dbcache.h"
#include "zbxalgo.h"
#include "zbxalgo.h"
#include "service_protocol.h"

extern unsigned char	process_type, program_type;
extern int		server_num, process_num;

extern int CONFIG_TRIGGERHOUSEKEEPING_FREQUENCY;

ZBX_THREAD_ENTRY(trigger_housekeeper_thread, args)
{
	int	now, d_history_and_trends, d_cleanup, d_events, d_problems, d_sessions, d_services, d_audit, sleeptime,
		records;
	double	sec, time_slept, time_now;
	char	sleeptext[25];

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));
	DBconnect(ZBX_DB_CONNECT_NORMAL);

	while (ZBX_IS_RUNNING())
	{
		zbx_sleep_loop(CONFIG_TRIGGERHOUSEKEEPING_FREQUENCY);

		if (!ZBX_IS_RUNNING())
			break;

		time_now = zbx_time();
		zbx_update_env(time_now);

		zabbix_log(LOG_LEVEL_WARNING, "executing housekeeper");

		zbx_setproctitle("%s [removing deleted items data]", get_process_type_string(process_type));
		/*d_cleanup = housekeeping_cleanup();*/
		sec = zbx_time() - sec;

		zabbix_log(LOG_LEVEL_WARNING, "%s [deleted %d hist/trends, %d items/triggers, %d events, %d problems,"
				" %d sessions, %d alarms, %d audit, %d records in " ZBX_FS_DBL " sec, %s]",
				get_process_type_string(process_type), d_history_and_trends, d_cleanup, d_events,
				d_problems, d_sessions, d_services, d_audit, records, sec, sleeptext);

		zbx_setproctitle("%s [deleted %d hist/trends, %d items/triggers, %d events, %d sessions, %d alarms,"
				" %d audit items, %d records in " ZBX_FS_DBL " sec, %s]",
				get_process_type_string(process_type), d_history_and_trends, d_cleanup, d_events,
				d_sessions, d_services, d_audit, records, sec, sleeptext);
	}

	DBclose();

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
}

