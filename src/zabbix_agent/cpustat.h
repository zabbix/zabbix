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

#if defined (_WINDOWS)

	#define MAX_CPU	16
	#define MAX_CPU_HISTORY 900 /* 15 min in seconds */

	typedef struct s_single_cpu_stat_data
	{
		PDH_HCOUNTER	usage_couter;
		PDH_RAW_COUNTER	usage;
		PDH_RAW_COUNTER	usage_old;

		double util1;
		double util5;
		double util15;

		LONG	h_usage[MAX_CPU_HISTORY]; /* usage history */
		int	h_usage_index;
	} ZBX_SINGLE_CPU_STAT_DATA;

	typedef struct s_cpus_stat_data
	{
		ZBX_SINGLE_CPU_STAT_DATA cpu[MAX_CPU];
		int	count;

		double	load1;
		double	load5;
		double	load15;

		LONG	h_queue[MAX_CPU_HISTORY]; /* queue history */
		int	h_queue_index;

		HQUERY		pdh_query;
		PDH_RAW_COUNTER	queue;
		PDH_HCOUNTER	queue_counter;

	} ZBX_CPUS_STAT_DATA;

#	define CPU_COLLECTOR_STARTED(collector)	((collector) && (collector)->cpus.pdh_query)

#else /* not _WINDOWS */

	#define MAX_CPU	16
	#define MAX_CPU_HISTORY 900 /* 15 min in seconds */

	typedef struct s_single_cpu_stat_data
	{
		/* private */
		int	clock[MAX_CPU_HISTORY];
		zbx_uint64_t	h_user[MAX_CPU_HISTORY];
		zbx_uint64_t	h_system[MAX_CPU_HISTORY];
		zbx_uint64_t	h_nice[MAX_CPU_HISTORY];
		zbx_uint64_t	h_idle[MAX_CPU_HISTORY];

		/* public */
		double	idle1;
		double	idle5;
		double	idle15;
		double	user1;
		double	user5;
		double	user15;
		double	system1;
		double	system5;
		double	system15;
		double	nice1;
		double	nice5;
		double	nice15;

	} ZBX_SINGLE_CPU_STAT_DATA;

	typedef struct s_cpus_stat_data
	{
		ZBX_SINGLE_CPU_STAT_DATA cpu[MAX_CPU];
		int	count;

	} ZBX_CPUS_STAT_DATA;

#	define CPU_COLLECTOR_STARTED(pcpus)	(collector)
#endif /* _WINDOWS */


int	init_cpu_collector(ZBX_CPUS_STAT_DATA *pcpus);
void	collect_cpustat(ZBX_CPUS_STAT_DATA *pcpus);
void	close_cpu_collector(ZBX_CPUS_STAT_DATA *pcpus);

#endif
