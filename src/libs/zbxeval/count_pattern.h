/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#ifndef ZABBIX_COUNT_PATTERN_H
#define ZABBIX_COUNT_PATTERN_H

#include "count_pattern.h"

void	zbx_clear_count_pattern(zbx_eval_count_pattern_data_t *pdata);
int	zbx_count_var_vector_with_pattern(zbx_eval_count_pattern_data_t *pdata, char *pattern, zbx_vector_var_t *values,
		int limit, int *count, char **error);
#endif
