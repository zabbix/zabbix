/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
#include "dbcache.h"

int	zbx_trends_parse_base(const char *params, zbx_time_unit_t *base, char **error);
int	zbx_trends_parse_timeshift(time_t from, const char *timeshift, struct tm *tm, char **error);

int	zbx_trends_parse_range(time_t from, const char *param, int *start, int *end, char **error);
int	zbx_trends_parse_nextcheck(time_t from, const char *period_shift, time_t *nextcheck, char **error);

int	zbx_trends_eval_avg(const char *table, zbx_uint64_t itemid, int start, int end, double *value, char **error);
int	zbx_trends_eval_count(const char *table, zbx_uint64_t itemid, int start, int end, double *value, char **error);
int	zbx_trends_eval_max(const char *table, zbx_uint64_t itemid, int start, int end, double *value, char **error);
int	zbx_trends_eval_min(const char *table, zbx_uint64_t itemid, int start, int end, double *value, char **error);
int	zbx_trends_eval_sum(const char *table, zbx_uint64_t itemid, int start, int end, double *value, char **error);

/* trends function cache */
typedef struct
{
	zbx_uint64_t	hits;
	zbx_uint64_t	misses;
	zbx_uint64_t	items_num;
	zbx_uint64_t	requests_num;
}
zbx_tfc_stats_t;

int	zbx_tfc_init(zbx_uint64_t cache_size, char **error);
void	zbx_tfc_destroy(void);
int	zbx_tfc_get_stats(zbx_tfc_stats_t *stats, char **error);
void	zbx_tfc_invalidate_trends(ZBX_DC_TREND *trends, int trends_num);

int	zbx_baseline_get_data(zbx_uint64_t itemid, unsigned char value_type, time_t now, const char *period,
		int season_num, zbx_time_unit_t season_unit, int skip, zbx_vector_dbl_t *values,
		zbx_vector_uint64_t *index, char **error);
#endif
