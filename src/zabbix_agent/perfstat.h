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

#if defined (_WINDOWS)

#	include "perfmon.h"

#else /* not _WINDOWS */

#	define PDH_RAW_COUNTER	void*
#	define HCOUNTER		void*
#	define HQUERY		void*

#endif /* _WINDOWS */

struct zbx_perfs
{
   struct zbx_perfs	*next;
   char			*name;
   char			*counterPath;
   int			interval;
   PDH_RAW_COUNTER	*rawValueArray;
   HCOUNTER		handle;
   double		lastValue;
   int			CurrentCounter;
   int			CurrentNum;
};

typedef struct zbx_perfs PERF_COUNTERS;

typedef struct s_perfs_stat_data
{
	PERF_COUNTERS	*pPerfCounterList;
	HQUERY		pdh_query;
} ZBX_PERF_STAT_DATA;

int	add_perfs_from_config(char *line);
void	perfs_list_free(void);

int	init_perf_collector(ZBX_PERF_STAT_DATA *pperf);
void	collect_perfstat(ZBX_PERF_STAT_DATA *pcpus);
void	close_perf_collector(ZBX_PERF_STAT_DATA *pcpus);

#endif /* ZABBIX_PERFSTAT_H */
