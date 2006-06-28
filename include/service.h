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

#include "threads.h"

extern ZBX_THREAD_HANDLE	*threads;

#define ZABBIX_SERVICE_NAME   "ZabbixAgentdW32"

#ifdef TODO
extern HANDLE eventShutdown;
#endif /* TODO */

void init_service(void);

int ZabbixCreateService(char *execName);
int ZabbixRemoveService(void);
int ZabbixStartService(void);
int ZabbixStopService(void);


#endif /* ZABBIX_SERVICE_H */
