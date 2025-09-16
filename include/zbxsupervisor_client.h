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

#ifndef ZABBIX_SUPERVISOR_CLIENT_H
#define ZABBIX_SUPERVISOR_CLIENT_H

#include "zbxcommon.h"
#include "zbxthreads.h"
#include "zbx_rtc_constants.h"

#define ZBX_IPC_SERVICE_SUPERVISOR	"supervisor"

#define ZBX_SUPERVISOR_PROC_RUNNING	(ZBX_IPC_RTC_MAX + 1)
#define ZBX_SUPERVISOR_RUNLEVEL_WAIT	(ZBX_IPC_RTC_MAX + 10)
#define ZBX_SUPERVISOR_RUNLEVEL_OK	(ZBX_IPC_RTC_MAX + 11)

typedef struct
{
	zbx_thread_args_t	args;
	zbx_log_component_t	*logger;
}
zbx_supervisor_unit_args_t;

void	zbx_supervisor_set_process_running(int proc_index);
void	zbx_supervisor_wait_for_runlevel(int runlevel);

void	zbx_supervisor_get_process_info(int process_type, zbx_proc_owner_t *owner, int *runlevel);
int	zbx_supervisor_get_process_count(const int *config_forks);

#endif
