/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

zbx_perf_counter_data_t	*add_perf_counter(const char *name, const char *counterpath, int interval, char **error);
void			remove_perf_counter(zbx_perf_counter_data_t *counter);

typedef enum
{
	ZBX_SINGLE_THREADED,
	ZBX_MULTI_THREADED
}
zbx_threadedness_t;

int	init_perf_collector(zbx_threadedness_t threadedness, char **error);
void	free_perf_collector(void);
void	collect_perfstat(void);

int	get_perf_counter_value_by_name(const char *name, double *value, char **error);
int	get_perf_counter_value_by_path(const char *counterpath, int interval, double *value, char **error);
int	get_perf_counter_value(zbx_perf_counter_data_t *counter, int interval, double *value, char **error);

#endif
