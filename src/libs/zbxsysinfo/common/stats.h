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

#ifndef ZABBIX_STATS_H
#define ZABBIX_STATS_H

#include "../sysinfo.h"

#include "zbxthreads.h"

#ifndef _WINDOWS
#	include "diskdevices.h"
#	include "zbxnix.h"
#endif

#include "cpustat.h"

#if defined(HAVE_KSTAT_H) && defined(HAVE_VMINFO_T_UPDATES)	/* Solaris */
#	include "zbxkstat.h"
#endif

#ifdef ZBX_PROCSTAT_COLLECTOR
#	include "procstat.h"
#endif

typedef struct
{
	ZBX_CPUS_STAT_DATA	cpus;
#ifndef _WINDOWS
	int			diskstat_shmid;
#endif
#ifdef ZBX_PROCSTAT_COLLECTOR
	zbx_dshm_t		procstat;
#endif
#ifdef _AIX
	ZBX_VMSTAT_DATA		vmstat;
	ZBX_CPUS_UTIL_DATA_AIX	cpus_phys_util;
#endif
#if defined(HAVE_KSTAT_H) && defined(HAVE_VMINFO_T_UPDATES)
	zbx_kstat_t		kstat;
#endif
}
zbx_collector_data;

int	cpu_collector_started(void);
zbx_collector_data	*get_collector(void);

#ifndef _WINDOWS
zbx_diskdevices_data	*get_diskdevices(void);
int	diskdevice_collector_started(void);
void	stats_lock_diskstats(void);
void	stats_unlock_diskstats(void);
#endif

void	diskstat_shm_init(void);
void	diskstat_shm_reattach(void);
void	diskstat_shm_extend(void);

#endif	/* ZABBIX_STATS_H */
