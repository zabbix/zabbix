/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


#ifndef ZABBIX_ZBXTRENDS_H
#define ZABBIX_ZBXTRENDS_H

#include "common.h"

int	zbx_trends_parse_base(const char *params, zbx_time_unit_t *base, char **error);

int	zbx_trends_parse_range(time_t from, const char *period, const char *period_shift, int *start, int *end,
		char **error);
int	zbx_trends_parse_nextcheck(time_t from, const char *period_shift, time_t *nextcheck, char **error);

int	zbx_trends_eval_avg(const char *table, zbx_uint64_t itemid, int start, int end, double *value, char **error);
int	zbx_trends_eval_count(const char *table, zbx_uint64_t itemid, int start, int end, double *value, char **error);
int	zbx_trends_eval_delta(const char *table, zbx_uint64_t itemid, int start, int end, double *value, char **error);
int	zbx_trends_eval_max(const char *table, zbx_uint64_t itemid, int start, int end, double *value, char **error);
int	zbx_trends_eval_min(const char *table, zbx_uint64_t itemid, int start, int end, double *value, char **error);
int	zbx_trends_eval_sum(const char *table, zbx_uint64_t itemid, int start, int end, double *value, char **error);

#endif
