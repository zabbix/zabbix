/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

#ifndef ZABBIX_VALUECACHE_H
#define ZABBIX_VALUECACHE_H

#include "zbxtypes.h"
#include "zbxalgo.h"

/* the item history value */
typedef struct
{
	zbx_timespec_t		timestamp;

	history_value_t		value;
}
zbx_vc_value_t;

ZBX_VECTOR_DECL(vc_value, zbx_vc_value_t);

/* the cache statistics */
typedef struct
{
	zbx_uint64_t	hits;
	zbx_uint64_t	misses;

	/* 0 - cache is working normally, 1 - cache is working in low memory mode */
	int		low_memory;
}
zbx_vc_stats_t;

void	zbx_vc_init(void);

void	zbx_vc_destroy(void);

void	zbx_vc_lock(void);

void	zbx_vc_unlock(void);

int	zbx_vc_get_values_by_time(zbx_uint64_t itemid, int value_type, zbx_vector_vc_value_t *values,
		int seconds, int timestamp);

int	zbx_vc_get_values_by_count(zbx_uint64_t itemid, int value_type, zbx_vector_vc_value_t *values,
		int count, int timestamp);

int	zbx_vc_get_value(zbx_uint64_t itemid, int value_type, const zbx_timespec_t *ts, zbx_vc_value_t *value);

int	zbx_vc_add_values(zbx_uint64_t itemid, int value_type, zbx_vector_vc_value_t *values);

int	zbx_vc_add_value(zbx_uint64_t itemid, int value_type, const zbx_timespec_t *timestamp, history_value_t *value);

int	zbx_vc_get_statistics(zbx_vc_stats_t *stats);

void	zbx_vc_value_vector_destroy(zbx_vector_vc_value_t *vector, int value_type);

void	zbx_vc_value_vector_append(zbx_vector_vc_value_t *vector, int value_type, zbx_vc_value_t *value);

void	zbx_vc_value_clear(zbx_vc_value_t *value, int value_type);

void	zbx_vc_history_value2str(char *buffer, size_t size, history_value_t *value, int value_type);

/* In most cases zbx_vc_value_vector_destroy() function should be used to free the  */
/* value vector filled by zbx_vc_get_value* functions. This define simply better    */
/* mirrors the vector creation function to vector destroying function.              */
#define zbx_vc_value_vector_create(vector)	zbx_vector_vc_value_create(vector);

#endif /* HISTORY_CACHE_H_ */
