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

#define PERF_COLLECTOR_STARTED(collector)	((collector) && (collector)->perfs.pdh_query)
#define UNSUPPORTED_REFRESH_PERIOD		600
#define USE_DEFAULT_INTERVAL			0

typedef struct
{
	PERF_COUNTER_DATA	*pPerfCounterList;
	PDH_HQUERY		pdh_query;
	time_t			nextcheck;	/* refresh time of not supported counters */
}
ZBX_PERF_STAT_DATA;

PERF_COUNTER_DATA	*add_perf_counter(const char *name, const char *counterpath, int interval);
void			remove_perf_counter(PERF_COUNTER_DATA *counter);

double	compute_average_value(const char *function, PERF_COUNTER_DATA *counter, int interval);

int	init_perf_collector(ZBX_PERF_STAT_DATA *pperf);
void	free_perf_collector();
void	collect_perfstat();

#endif /* _WINDOWS */

#endif /* ZABBIX_PERFSTAT_H */
