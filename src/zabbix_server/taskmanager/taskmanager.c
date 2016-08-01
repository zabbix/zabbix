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
#include "daemon.h"
#include "zbxself.h"
#include "log.h"

#define ZBX_TASKMANAGER_TIMEOUT		5

extern unsigned char	process_type, program_type;
extern int		server_num, process_num;

static int	tm_process_tasks()
{
	return 0;
}

ZBX_THREAD_ENTRY(taskmanager_thread, args)
{
	double	sec;
	int	tasks_num;

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	for (;;)
	{
		zbx_handle_log();

		zbx_setproctitle("%s [processing tasks]", get_process_type_string(process_type));

		sec = zbx_time();
		tasks_num = tm_process_tasks();
		sec = zbx_time() - sec;

		zbx_setproctitle("%s [processed %d task(s) in " ZBX_FS_DBL " sec, idle %d sec]",
				get_process_type_string(process_type), tasks_num, sec, ZBX_TASKMANAGER_TIMEOUT);

		zbx_sleep_loop(ZBX_TASKMANAGER_TIMEOUT);
	}
}
