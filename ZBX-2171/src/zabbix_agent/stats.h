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
#include "diskdevices.h"
#ifdef _WINDOWS
#	include "perfmon.h"
#	include "perfstat.h"
#endif	/* _WINDOWS */
#include "cpustat.h"
#ifdef _AIX
#	include "vmstats.h"
#endif	/* _AIX */

typedef struct
{
	ZBX_CPUS_STAT_DATA	cpus;
	ZBX_DISKDEVICES_DATA	diskdevices;
#ifdef _WINDOWS
	ZBX_PERF_STAT_DATA	perfs;
#endif	/* _WINDOWS */
#ifdef _AIX
	ZBX_VMSTAT_DATA		vmstat;
#endif	/* _AIX */
}
ZBX_COLLECTOR_DATA;

extern ZBX_COLLECTOR_DATA	*collector;

ZBX_THREAD_ENTRY(collector_thread, pSemColectorStarted);

void	init_collector_data();
void	free_collector_data();

#endif	/* ZABBIX_STATS_H */
