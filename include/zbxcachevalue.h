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

#ifndef ZABBIX_CACHEVALUE_H
#define ZABBIX_CACHEVALUE_H

#include "zbxtypes.h"
#include "zbxalgo.h"
#include "zbxhistory.h"
#include "zbxshmem.h"

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
 *   The history data is accessed with zbx_vc_get_values() and zbx_vc_get_value()
 *   functions. Afterwards the retrieved history data must be freed by the caller by using
 *   either zbx_history_record_vector_destroy() function (free the zbx_vc_get_values()
 *   call output) or zbx_history_record_clear() function (free the zbx_vc_get_value() call output).
 *
 * Locking
 *
 *   The cache ensures synchronization between processes by using automatic locks whenever
 *   a cache function (zbx_vc_*) is called and by providing manual cache locking functionality
 *   with zbx_vc_lock()/zbx_vc_unlock() functions.
 *
 */

#define ZBX_VC_MODE_NORMAL	0
#define ZBX_VC_MODE_LOWMEM	1

/* indicates that all values from database are cached */
#define ZBX_ITEM_STATUS_CACHED_ALL	1

/* the cache statistics */
typedef struct
{
	/* Value cache misses are new values cached during request and hits are calculated by  */
	/* subtracting misses from the total number of values returned (0 if the number of     */
	/* returned values is less than misses.                                                */
	/* When performing count based requests the number of cached values might be greater   */
	/* than number of returned values. This can skew the hits/misses ratio towards misses. */
	zbx_uint64_t	hits;
	zbx_uint64_t	misses;

	zbx_uint64_t	total_size;
	zbx_uint64_t	free_size;

	/* value cache operating mode - see ZBX_VC_MODE_* defines */
	int		mode;
}
zbx_vc_stats_t;

/* item diagnostic statistics */
typedef struct
{
	zbx_uint64_t	itemid;
	int		values_num;
	int		hourly_num;
}
zbx_vc_item_stats_t;

ZBX_PTR_VECTOR_DECL(vc_item_stats_ptr, zbx_vc_item_stats_t *)

void	zbx_vc_item_stats_free(zbx_vc_item_stats_t *vc_item_stats);

int	zbx_vc_init(zbx_uint64_t value_cache_size, char **error);

void	zbx_vc_destroy(void);

void	zbx_vc_reset(void);

void	zbx_vc_enable(void);

void	zbx_vc_disable(void);

int	zbx_vc_get_values(zbx_uint64_t itemid, unsigned char value_type, zbx_vector_history_record_t *values,
		int seconds, int count, const zbx_timespec_t *ts);

int	zbx_vc_get_value(zbx_uint64_t itemid, unsigned char value_type, const zbx_timespec_t *ts,
		zbx_history_record_t *value);

int	zbx_vc_add_values(zbx_vector_dc_history_ptr_t *history, int *ret_flush, int config_history_storage_pipelines);

int	zbx_vc_get_statistics(zbx_vc_stats_t *stats);

void	zbx_vc_remove_items_by_ids(zbx_vector_uint64_t *itemids);

void	zbx_vc_get_diag_stats(zbx_uint64_t *items_num, zbx_uint64_t *values_num, int *mode);
void	zbx_vc_get_mem_stats(zbx_shmem_stats_t *mem);
void	zbx_vc_get_item_stats(zbx_vector_vc_item_stats_ptr_t *stats);
void	zbx_vc_flush_stats(void);

void	zbx_vc_add_new_items(const zbx_vector_uint64_pair_t *items);

#endif
