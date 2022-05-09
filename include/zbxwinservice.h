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

#ifndef ZABBIX_SERVICE_H
#define ZABBIX_SERVICE_H

#ifndef _WINDOWS
#	error "This module is only available for Windows OS"
#endif

#include "zbxthreads.h"

extern ZBX_THREAD_HANDLE	*threads;

void	service_start(int flags);

int	ZabbixCreateService(const char *path, int multiple_agents);
int	ZabbixRemoveService(void);
int	ZabbixStartService(void);
int	ZabbixStopService(void);

typedef void	(*zbx_on_exit_t)(int);
void	set_parent_signal_handler(zbx_on_exit_t zbx_on_exit_cb_arg);

int	ZBX_IS_RUNNING(void);
void	ZBX_DO_EXIT(void);

#endif /* ZABBIX_SERVICE_H */
