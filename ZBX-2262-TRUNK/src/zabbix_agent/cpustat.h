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

#if defined (_WINDOWS)

	#define MAX_CPU_HISTORY 900 /* 15 min in seconds */

	typedef struct s_single_cpu_stat_data
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

	typedef struct s_cpus_stat_data
	{
		int	count;
		ZBX_SINGLE_CPU_STAT_DATA *cpu; /* count + 1 */

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

#	define CPU_COLLECTOR_STARTED(collector)	((collector) && (collector)->cpus.pdh_query)

#else /* not _WINDOWS */

	#define MAX_CPU_HISTORY 900 /* 15 min in seconds */

	typedef struct s_single_cpu_stat_data
	{
		/* private */
		int	clock[MAX_CPU_HISTORY];
		zbx_uint64_t	h_user[MAX_CPU_HISTORY];
		zbx_uint64_t	h_system[MAX_CPU_HISTORY];
		zbx_uint64_t	h_nice[MAX_CPU_HISTORY];
		zbx_uint64_t	h_idle[MAX_CPU_HISTORY];
		zbx_uint64_t	h_interrupt[MAX_CPU_HISTORY];
		zbx_uint64_t	h_iowait[MAX_CPU_HISTORY];
		zbx_uint64_t	h_softirq[MAX_CPU_HISTORY];
		zbx_uint64_t	h_steal[MAX_CPU_HISTORY];

		/* public */
		double	user[ZBX_AVGMAX];
		double	system[ZBX_AVGMAX];
		double	nice[ZBX_AVGMAX];
		double	idle[ZBX_AVGMAX];
		double	interrupt[ZBX_AVGMAX];
		double	iowait[ZBX_AVGMAX];
		double	softirq[ZBX_AVGMAX];
		double	steal[ZBX_AVGMAX];
	}
	ZBX_SINGLE_CPU_STAT_DATA;

	typedef struct s_cpus_stat_data
	{
		int	count;
		ZBX_SINGLE_CPU_STAT_DATA *cpu; /* count + 1 */
	}
	ZBX_CPUS_STAT_DATA;

#	define CPU_COLLECTOR_STARTED(collector)	(collector)

#endif /* _WINDOWS */

int	init_cpu_collector(ZBX_CPUS_STAT_DATA *pcpus);
void	collect_cpustat(ZBX_CPUS_STAT_DATA *pcpus);
void	close_cpu_collector(ZBX_CPUS_STAT_DATA *pcpus);

#endif
