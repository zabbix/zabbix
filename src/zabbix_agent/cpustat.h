/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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

#if defined(_WINDOWS)

	#define MAX_CPU_HISTORY	(15 * SEC_PER_MIN)

	typedef struct
	{
		PDH_HCOUNTER	usage_counter;
		PDH_RAW_COUNTER	usage;
		PDH_RAW_COUNTER	usage_old;

		double	util1;
		double	util5;
		double	util15;

		LONG	util1sum;
		LONG	util5sum;
		LONG	util15sum;

		LONG	h_usage[MAX_CPU_HISTORY]; /* usage history */
		int	h_usage_index;
	}
	ZBX_SINGLE_CPU_STAT_DATA;

	typedef struct
	{
		ZBX_SINGLE_CPU_STAT_DATA	*cpu;
		int				count;

		double	load1;
		double	load5;
		double	load15;

		LONG	load1sum;
		LONG	load5sum;
		LONG	load15sum;

		LONG	h_queue[MAX_CPU_HISTORY]; /* queue history */
		int	h_queue_index;

		HQUERY		pdh_query;
		PDH_RAW_COUNTER	queue;
		PDH_HCOUNTER	queue_counter;
	}
	ZBX_CPUS_STAT_DATA;

	#define CPU_COLLECTOR_STARTED(collector)	((collector) && (collector)->cpus.pdh_query)

#else /* not _WINDOWS */

	typedef struct
	{
		zbx_uint64_t	h_counter[ZBX_CPU_STATE_COUNT][MAX_COLLECTOR_HISTORY];
		int		h_first;
		int		h_count;
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
void	collect_cpustat(ZBX_CPUS_STAT_DATA *pcpus);
#ifndef _WINDOWS
int	get_cpustat(AGENT_RESULT *result, int cpu_num, int state, int mode);
#endif /* not _WINDOWS */

#endif
