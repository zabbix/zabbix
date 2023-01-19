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
	int				config_startup_time;
}
zbx_thread_taskmanager_args;

void	zbx_tm_get_remote_tasks(zbx_vector_tm_task_t *tasks, zbx_uint64_t proxy_hostid,
		zbx_proxy_compatibility_t compatibility);

ZBX_THREAD_ENTRY(taskmanager_thread, args);

#endif /*ZABBIX_PROXY_TASKMANAGER_H*/
