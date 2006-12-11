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

#ifndef ZABBIX_STATS_H
#define ZABBIX_STATS_H

#include "threads.h"
#include "cpustat.h"
#include "interfaces.h"
#include "diskdevices.h"

typedef struct s_collector_data
{
	ZBX_CPUS_STAT_DATA	cpus;
	ZBX_INTERFACES_DATA	interfaces;
	ZBX_DISKDEVICES_DATA	diskdevices;
} ZBX_COLLECTOR_DATA;
 
extern ZBX_COLLECTOR_DATA *collector;


ZBX_THREAD_ENTRY(collector_thread, pSemColectorStarted);

void	init_collector_data(void);
void	free_collector_data(void);

#endif
