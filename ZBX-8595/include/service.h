/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#ifndef ZABBIX_SERVICE_H
#define ZABBIX_SERVICE_H

#if !defined(_WINDOWS)
#	error "This module allowed only for Windows OS"
#endif /* _WINDOWS */

#include "threads.h"

extern ZBX_THREAD_HANDLE	*threads;

void	service_start();

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

#define START_MAIN_ZABBIX_ENTRY(a)	service_start()

#endif /* ZABBIX_SERVICE_H */
