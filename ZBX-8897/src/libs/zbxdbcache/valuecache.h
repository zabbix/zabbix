/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

/*
 * The Value Cache provides read caching of item historical data residing in history
 * tables. No components must read history tables manually. Instead all history data
 * must be read from the Value Cache.
 *
 * Usage notes:
 *
 * Initialization
 *
 *   The value cache must be initialized at the start of the program with zbx_vc_init()
 *   function. To ensure proper removal of shared memory the value cache must be destroyed
 *   upon a program exit with zbx_vc_destroy() function.
 *
 * Adding data
 *
 *   Whenever a new item value is added to system (history tables) the item value must be
 *   also added added to Value Cache with zbx_dc_add_value() function to keep it up to date.
 *
 * Retrieving data
 *
 *   The history data is accessed with zbx_vc_get_value_range() and zbx_vc_get_value()
 *   functions. Afterwards the retrieved history data must be freed by the caller by using
 *   either zbx_history_record_vector_destroy() function (free the zbx_vc_get_value_range()
 *   call output) or zbx_history_record_clear() function (free the zbx_vc_get_value() call output).
 *
 * Locking
 *
 *   The cache ensures synchronization between processes by using automatic locks whenever
 *   a cache function (zbx_vc_*) is called and by providing manual cache locking functionality
 *   with zbx_vc_lock()/zbx_vc_unlock() functions.
 *
 */

/* the item history value */
typedef struct
{
	zbx_timespec_t	timestamp;
	history_value_t	value;
}
zbx_history_record_t;

ZBX_VECTOR_DECL(history_record, zbx_history_record_t);

/* the cache statistics */
typedef struct
{
	zbx_uint64_t	hits;
	zbx_uint64_t	misses;

	zbx_uint64_t	total_size;
	zbx_uint64_t	free_size;

	/* 0 - cache is working normally, 1 - cache is working in low memory mode */
	int		low_memory;
}
zbx_vc_stats_t;

void	zbx_vc_init(void);

void	zbx_vc_destroy(void);

void	zbx_vc_lock(void);

void	zbx_vc_unlock(void);

int	zbx_vc_get_value_range(zbx_uint64_t itemid, int value_type, zbx_vector_history_record_t *values, int seconds,
		int count, int timestamp);

int	zbx_vc_get_value(zbx_uint64_t itemid, int value_type, const zbx_timespec_t *ts, zbx_history_record_t *value);

int	zbx_vc_add_value(zbx_uint64_t itemid, int value_type, const zbx_timespec_t *timestamp, history_value_t *value);

int	zbx_vc_get_statistics(zbx_vc_stats_t *stats);

void	zbx_history_record_vector_destroy(zbx_vector_history_record_t *vector, int value_type);

void	zbx_history_record_clear(zbx_history_record_t *value, int value_type);

void	zbx_vc_history_value2str(char *buffer, size_t size, history_value_t *value, int value_type);

/* In most cases zbx_history_record_vector_destroy() function should be used to free the  */
/* value vector filled by zbx_vc_get_value* functions. This define simply better          */
/* mirrors the vector creation function to vector destroying function.                    */
#define zbx_history_record_vector_create(vector)	zbx_vector_history_record_create(vector)

#endif	/* ZABBIX_VALUECACHE_H */
