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

#ifndef ZABBIX_PERFSTAT_H
#define ZABBIX_PERFSTAT_H

#ifndef _WINDOWS
#	error "This module is only available for Windows OS"
#endif

#include "zbxwin32.h"

void	collect_perfstat(void);

int	get_perf_counter_value_by_name(const char *name, double *value, char **error);
int	get_perf_counter_value_by_path(const char *counterpath, int interval, zbx_perf_counter_lang_t lang,
		double *value, char **error);
int	get_perf_counter_value(zbx_perf_counter_data_t *counter, int interval, double *value, char **error);
int	refresh_object_cache(void);
wchar_t	*get_object_name_local(char *eng_name);

#endif
