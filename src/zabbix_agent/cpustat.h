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

#if defined (WIN32)

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

#else /* not WIN32 */

	#define MAX_CPU_HISTORY 900 /* 15 min in seconds */

	typedef struct s_cpus_stat_data
	{
		int	clock[MAX_CPU_HISTORY];
		float	h_user[MAX_CPU_HISTORY];
		float	h_system[MAX_CPU_HISTORY];
		float	h_nice[MAX_CPU_HISTORY];
		float	h_idle[MAX_CPU_HISTORY];

		float	idle;
		float	idle1;
		float	idle5;
		float	idle15;
		float	user;
		float	user1;
		float	user5;
		float	user15;
		float	system;
		float	system1;
		float	system5;
		float	system15;
		float	nice;
		float	nice1;
		float	nice5;
		float	nice15;
		float	all;
		float	all1;
		float	all5;
		float	all15;

	} ZBX_CPUS_STAT_DATA;

#endif /* WIN32 */


int	init_cpu_collector(ZBX_CPUS_STAT_DATA *pcpus);
void	collect_cpustat(ZBX_CPUS_STAT_DATA *pcpus);
void	close_cpu_collector(ZBX_CPUS_STAT_DATA *pcpus);

#endif
