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

#ifndef ZABBIX_PERFSTAT_H
#define ZABBIX_PERFSTAT_H

#ifdef _WINDOWS

#	include "perfmon.h"

#	define PERF_COLLECTOR_STARTED(collector)	((collector) && (collector)->perfs.pdh_query)
#	define UNSUPPORTED_REFRESH_PERIOD		600
#	define USE_DEFAULT_INTERVAL			0

typedef struct s_perfs_stat_data
{
	PERF_COUNTERS	*pPerfCounterList;
	HQUERY		pdh_query;
	time_t		nextcheck;	/* refresh time of not supported counters */
} ZBX_PERF_STAT_DATA;

PDH_STATUS	calculate_counter_value(const char *function, const char *counterpath, DWORD dwFormat, PPDH_FMT_COUNTERVALUE value);
double		compute_counter_statistics(const char *function, PERF_COUNTERS *counter, int interval);
PERF_COUNTERS	*add_perf_counter(const char *name, const char *counterpath, int interval);
int		add_perfs_from_config(const char *line);
void		remove_perf_counter(PERF_COUNTERS *counter);
void		perfs_list_free(void);

int		init_perf_collector(ZBX_PERF_STAT_DATA *pperf);
void		collect_perfstat();
void		close_perf_collector();
#endif /* _WINDOWS */

#endif /* ZABBIX_PERFSTAT_H */
