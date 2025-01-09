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

#include "../../../src/libs/zbxcachevalue/valuecache.c"

#include "valuecache_test.h"
#include "zbxmocktest.h"

void	zbx_vc_set_mode(int mode)
{
	vc_cache->mode = mode;
	vc_cache->mode_time = time(NULL);
}

int	zbx_vc_get_cached_values(zbx_uint64_t itemid, unsigned char value_type, zbx_vector_history_record_t *values)
{
	zbx_vc_item_t	*item;
	int		i;
	zbx_vc_chunk_t	*chunk;

	if (NULL == (item = zbx_hashset_search(&vc_cache->items, &itemid)))
		return FAIL;

	if (NULL == item->head)
		return SUCCEED;

	for (chunk = item->tail; NULL != chunk; chunk = chunk->next)
	{
		for (i = chunk->first_value; i <= chunk->last_value; i++)
			vc_history_record_vector_append(values, value_type, &chunk->slots[i]);
	}

	return SUCCEED;
}

int	zbx_vc_precache_values(zbx_uint64_t itemid, int value_type, int seconds, int count, const zbx_timespec_t *ts)
{
	zbx_vc_item_t			*item;
	int				ret;
	zbx_vector_history_record_t	values;

	/* add item to cache if necessary */
	if (NULL == (item = (zbx_vc_item_t *)zbx_hashset_search(&vc_cache->items, &itemid)))
	{
		zbx_vc_item_t   new_item = {.itemid = itemid, .value_type = value_type};
		item = zbx_hashset_insert(&vc_cache->items, &new_item, sizeof(zbx_vc_item_t));
	}

	/* perform request to cache values */
	zbx_history_record_vector_create(&values);
	RDLOCK_CACHE;
	ret = vch_item_get_values(item, &values, seconds, count, ts);
	UNLOCK_CACHE;
	zbx_vc_flush_stats();
	zbx_history_record_vector_destroy(&values, value_type);

	/* reset cache statistics */
	vc_cache->hits = 0;
	vc_cache->misses = 0;

	return ret;
}

int	zbx_vc_get_item_state(zbx_uint64_t itemid, int *status, int *active_range, int *values_total,
		int *db_cached_from)
{
	zbx_vc_item_t	*item;
	int		ret = FAIL;

	if (NULL != (item = (zbx_vc_item_t *)zbx_hashset_search(&vc_cache->items, &itemid)))
	{
		*status = item->status;
		*active_range = item->active_range;
		*values_total = item->values_total;
		*db_cached_from = item->db_cached_from;

		ret = SUCCEED;
	}

	return ret;
}

int	zbx_vc_get_cache_state(int *mode, zbx_uint64_t *hits, zbx_uint64_t *misses)
{
	if (NULL == vc_cache)
		return FAIL;

	*mode = vc_cache->mode;
	*hits = vc_cache->hits;
	*misses = vc_cache->misses;

	return SUCCEED;
}

/*
 * cache working mode handling
 */

/******************************************************************************
 *                                                                            *
 * Purpose: sets value cache mode if the specified key is present in input    *
 *          data                                                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_vcmock_set_mode(zbx_mock_handle_t hitem, const char *key)
{
	const char		*data;
	zbx_mock_handle_t	hmode;
	zbx_mock_error_t	err;

	if (ZBX_MOCK_SUCCESS == zbx_mock_object_member(hitem, key, &hmode))
	{
		if (ZBX_MOCK_SUCCESS != (err = zbx_mock_string(hmode, &data)))
			fail_msg("Cannot read \"%s\" parameter: %s", key, zbx_mock_error_string(err));

		zbx_vc_set_mode(zbx_vcmock_str_to_cache_mode(data));
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: converts value cache mode from text format                        *
 *                                                                            *
 ******************************************************************************/
int	zbx_vcmock_str_to_cache_mode(const char *mode)
{
	if (0 == strcmp(mode, "ZBX_VC_MODE_NORMAL"))
		return ZBX_VC_MODE_NORMAL;

	if (0 == strcmp(mode, "ZBX_VC_MODE_LOWMEM"))
		return ZBX_VC_MODE_LOWMEM;

	fail_msg("Unknown value cache mode \"%s\"", mode);
	return FAIL;
}
