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

#ifndef ZABBIX_PERFSTAT_H
#define ZABBIX_PERFSTAT_H

#ifndef _WINDOWS
#	error "This module is only available for Windows OS"
#endif

#include "perfmon.h"

#define UNSUPPORTED_REFRESH_PERIOD		600
#define USE_DEFAULT_INTERVAL			0

typedef struct
{
	PERF_COUNTER_DATA	*pPerfCounterList;
	PDH_HQUERY		pdh_query;
	time_t			nextcheck;	/* refresh time of not supported counters */
}
ZBX_PERF_STAT_DATA;

extern ZBX_PERF_STAT_DATA	ppsd;

PERF_COUNTER_DATA	*add_perf_counter(const char *name, const char *counterpath, int interval);
void			remove_perf_counter(PERF_COUNTER_DATA *counter);

double	compute_average_value(PERF_COUNTER_DATA *counter, int interval);

int	init_perf_collector(int multithreaded);
void	free_perf_collector();
int	perf_collector_started();
void	collect_perfstat();

#endif
