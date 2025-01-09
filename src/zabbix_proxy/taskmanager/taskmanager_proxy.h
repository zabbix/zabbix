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

#ifndef ZABBIX_PROXY_TASKMANAGER_H
#define ZABBIX_PROXY_TASKMANAGER_H

#include "zbxtasks.h"

#include "zbxcomms.h"
#include "zbxthreads.h"
#include "zbxversion.h"

typedef struct
{
	const zbx_config_comms_args_t	*config_comms;
	zbx_get_program_type_f		zbx_get_program_type_cb_arg;
	const char			*progname;
	int				config_startup_time;
	int				config_enable_remote_commands;
	int				config_log_remote_commands;
	const char			*config_hostname;
	zbx_get_config_forks_f		get_process_forks_cb_arg;
	const char			*config_java_gateway;
	int				config_java_gateway_port;
	const char			*config_externalscripts;
	int				config_enable_global_scripts;
	const char			*config_ssh_key_location;
	const char			*config_webdriver_url;
}
zbx_thread_taskmanager_args;

void	zbx_tm_get_remote_tasks(zbx_vector_tm_task_t *tasks, zbx_uint64_t proxyid,
		zbx_proxy_compatibility_t compatibility);

ZBX_THREAD_ENTRY(taskmanager_thread, args);

#endif /*ZABBIX_PROXY_TASKMANAGER_H*/
