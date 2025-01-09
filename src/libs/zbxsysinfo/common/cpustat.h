/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#ifndef ZABBIX_CPUSTAT_H
#define ZABBIX_CPUSTAT_H

#include "zbxsysinfo.h"
#include "zbxalgo.h"

#ifdef _WINDOWS
#	include "zbxwin32.h"

typedef struct
{
	zbx_perf_counter_data_t	**cpu_counter;
	zbx_perf_counter_data_t	*queue_counter;
	int			count;
}
ZBX_CPUS_STAT_DATA;

int	get_cpu_perf_counter_value(int cpu_num, int interval, double *value, char **error);

#else	/* not _WINDOWS */

typedef struct
{
	zbx_uint64_t	h_counter[ZBX_CPU_STATE_COUNT][ZBX_MAX_COLLECTOR_HISTORY];
	unsigned char	h_status[ZBX_MAX_COLLECTOR_HISTORY];
#if (ZBX_MAX_COLLECTOR_HISTORY % 8) > 0
	unsigned char	padding0[8 - (ZBX_MAX_COLLECTOR_HISTORY % 8)];	/* for 8-byte alignment */
#endif
	int		h_first;
	int		h_count;
	int		cpu_num;
	int		padding1;	/* for 8-byte alignment */
}
ZBX_SINGLE_CPU_STAT_DATA;

typedef struct
{
	ZBX_SINGLE_CPU_STAT_DATA	*cpu;
	int				count;
}
ZBX_CPUS_STAT_DATA;

#ifdef _AIX
/* collector layout is designed to be cache-friendly for updates and large number of CPUs */
typedef struct
{
	unsigned char	status;
	/* data comes from IBM AIX 'perfstat_cpu_util_t' (percents of utilization) */
	zbx_uint64_t	user_pct;	/* user mode */
	zbx_uint64_t	kern_pct;	/* kernel mode */
	zbx_uint64_t	idle_pct;	/* idle mode */
	zbx_uint64_t	wait_pct;	/* wait mode */
}
ZBX_CPU_UTIL_PCT_AIX;

typedef struct
{
	ZBX_CPU_UTIL_PCT_AIX	*counters;	/* pointer to 2D array with history data */
	int			row_num;	/* number of rows (ZBX_MAX_COLLECTOR_HISTORY) */
	int			column_num;	/* number of columns (number of CPUs) */
	int			h_latest;	/* index of the most recent entry in the history data */
	int			h_count;	/* number of entries in the history data */
}
ZBX_CPUS_UTIL_DATA_AIX;
#endif /* _AIX */

void	collect_cpustat(ZBX_CPUS_STAT_DATA *pcpus);
int	get_cpustat(AGENT_RESULT *result, int cpu_num, int state, int mode);
#ifdef _AIX
void	collect_cpustat_physical(ZBX_CPUS_UTIL_DATA_AIX *cpus_phys_util);
int	get_cpustat_physical(AGENT_RESULT *result, int cpu_num, int state, int mode);
#endif

#endif	/* _WINDOWS */

int	init_cpu_collector(ZBX_CPUS_STAT_DATA *pcpus);
void	free_cpu_collector(ZBX_CPUS_STAT_DATA *pcpus);

#define ZBX_CPUNUM_UNDEF	-1	/* unidentified yet CPUs */
#define ZBX_CPUNUM_ALL		-2	/* request data for all CPUs */

#define ZBX_CPU_STATUS_ONLINE	0
#define ZBX_CPU_STATUS_OFFLINE	1
#define ZBX_CPU_STATUS_UNKNOWN	2

int	get_cpus(zbx_vector_uint64_pair_t *vector);

#endif
