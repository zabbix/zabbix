/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

#ifndef ZABBIX_STATS_H
#define ZABBIX_STATS_H

#include "threads.h"
#ifndef _WINDOWS
#	include "diskdevices.h"
#endif
#include "cpustat.h"
#ifdef _AIX
#	include "vmstats.h"
#endif

#ifndef _WINDOWS
#	define NONEXISTENT_SHMID	(-1)
#endif

typedef struct
{
	ZBX_CPUS_STAT_DATA	cpus;
#ifndef _WINDOWS
	int 			diskstat_shmid;
#endif
#ifdef _AIX
	ZBX_VMSTAT_DATA		vmstat;
#endif
#ifndef _LP64
	long			padding;	/* The goal of padding is to make sizeof(ZBX_COLLECTOR_DATA) */
						/* a multiple of 8. In 32-bit environment the padding will be */
						/* be 4 bytes. In 64-bit environment the padding is currently not */
						/* necessary, but harmless if used. The padding size must be */
						/* adjusted if structure or its components change. */
#endif
}
ZBX_COLLECTOR_DATA;

extern ZBX_COLLECTOR_DATA	*collector;
#ifndef _WINDOWS
extern ZBX_DISKDEVICES_DATA	*diskdevices;
extern int			my_diskstat_shmid;
#endif

ZBX_THREAD_ENTRY(collector_thread, pSemColectorStarted);

void	init_collector_data();
void	free_collector_data();
void	diskstat_shm_init();
void	diskstat_shm_reattach();
void	diskstat_shm_extend();

#endif	/* ZABBIX_STATS_H */
