/*
** Copyright (C) 2001-2026 Zabbix SIA
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
#include "zbxipcservice.h"

#define ZBX_IPC_SERVICE_SUPERVISOR	"supervisor"

#define ZBX_SUPERVISOR_PROC_RUNNING	(ZBX_IPC_RTC_MAX + 1)
#define ZBX_SUPERVISOR_GET_ACTIVITIES	(ZBX_IPC_RTC_MAX + 2)
#define ZBX_SUPERVISOR_RUNLEVEL_WAIT	(ZBX_IPC_RTC_MAX + 10)
#define ZBX_SUPERVISOR_RUNLEVEL_OK	(ZBX_IPC_RTC_MAX + 11)

typedef enum
{
	UNIT_IDLE,
	UNIT_RUNNING,
	UNIT_STOPPING,
	UNIT_ABORTING
}
zbx_supervisor_runstate_t;

typedef struct
{
	zbx_thread_args_t			args;
	zbx_log_component_t			*logger;
	_Atomic zbx_supervisor_runstate_t	*runstate;
	char					name[ZBX_MAX_PROCNAME_LEN + 1];
}
zbx_supervisor_unit_args_t;

void	zbx_supervisor_set_process_running(int proc_index);

typedef struct
{
	zbx_ipc_async_socket_t	asock;
	int		last_runlevel;
}
zbx_supervisor_client_t;

int	zbx_supervisor_client_init(zbx_supervisor_client_t *svc, char **error);
void	zbx_supervisor_client_clear(zbx_supervisor_client_t *svc);
int	zbx_supervisor_client_poll(zbx_supervisor_client_t *svc, int runlevel, char **error);

void	zbx_supervisor_worklog_init(void);
void	zbx_supervisor_worklog_clear(void);
void	zbx_supervisor_update_activity(const char *fmt, ...);
char	*zbx_supervisor_get_activities(void);


#endif
