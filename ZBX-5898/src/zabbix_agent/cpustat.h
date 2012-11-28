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

#ifndef ZABBIX_CPUSTAT_H
#define ZABBIX_CPUSTAT_H

#include "sysinfo.h"

#ifdef _WINDOWS

#define MAX_CPU_HISTORY	(15 * SEC_PER_MIN)

typedef struct
{
	PERF_COUNTER_DATA	**cpu_counter;
	PERF_COUNTER_DATA	*queue_counter;
	int			count;
}
ZBX_CPUS_STAT_DATA;

#define CPU_COLLECTOR_STARTED(collector)	((collector) && (collector)->cpus.queue_counter)

#else /* not _WINDOWS */

typedef struct
{
	zbx_uint64_t	h_counter[ZBX_CPU_STATE_COUNT][MAX_COLLECTOR_HISTORY];
	unsigned char	h_status[MAX_COLLECTOR_HISTORY];
	int		h_first;
	int		h_count;
	int		cpu_num;
}
ZBX_SINGLE_CPU_STAT_DATA;

typedef struct
{
	ZBX_SINGLE_CPU_STAT_DATA	*cpu;
	int				count;
}
ZBX_CPUS_STAT_DATA;

#define CPU_COLLECTOR_STARTED(collector)	(collector)

#endif /* _WINDOWS */

int	init_cpu_collector(ZBX_CPUS_STAT_DATA *pcpus);
void	free_cpu_collector(ZBX_CPUS_STAT_DATA *pcpus);

#ifndef _WINDOWS
void	collect_cpustat(ZBX_CPUS_STAT_DATA *pcpus);
int	get_cpustat(AGENT_RESULT *result, int cpu_num, int state, int mode);
#endif /* not _WINDOWS */

#endif
