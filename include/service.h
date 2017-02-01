/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

#include "threads.h"

extern ZBX_THREAD_HANDLE	*threads;

void	service_start(int flags);

int	ZabbixCreateService(const char *path, int multiple_agents);
int	ZabbixRemoveService();
int	ZabbixStartService();
int	ZabbixStopService();

void	set_parent_signal_handler();

int	application_status;	/* required for closing application from service */

#define ZBX_APP_STOPPED	0
#define ZBX_APP_RUNNING	1

#define ZBX_IS_RUNNING()	(ZBX_APP_RUNNING == application_status)
#define ZBX_DO_EXIT()		application_status = ZBX_APP_STOPPED

#define START_MAIN_ZABBIX_ENTRY(allow_root, user, flags)	service_start(flags)

#endif /* ZABBIX_SERVICE_H */
