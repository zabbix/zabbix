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

	vc_try_lock();

	if (NULL == (item = zbx_hashset_search(&vc_cache->items, &itemid)))
		return FAIL;

	if (NULL == item->head)
		return SUCCEED;

	for (chunk = item->tail; NULL != chunk; chunk = chunk->next)
	{
		for (i = chunk->first_value; i <= chunk->last_value; i++)
			vc_history_record_vector_append(values, value_type, &chunk->slots[i]);
	}

	vc_try_unlock();

	return SUCCEED;
}

int	zbx_vc_precache_values(zbx_uint64_t itemid, int value_type, int seconds, int count, int end)
{
	zbx_vc_item_t			*item;
	int				ret;
	zbx_vector_history_record_t	values;

	vc_try_lock();

	/* add item to cache if necessary */
	if (NULL == (item = (zbx_vc_item_t *)zbx_hashset_search(&vc_cache->items, &itemid)))
	{
		zbx_vc_item_t   new_item = {.itemid = itemid, .value_type = value_type};
		item = zbx_hashset_insert(&vc_cache->items, &new_item, sizeof(zbx_vc_item_t));
	}

	/* perform request to cache values */
	vc_item_addref(item);
	zbx_history_record_vector_create(&values);
	ret = vch_item_get_value_range(item, &values, seconds, count, end);
	zbx_history_record_vector_destroy(&values, value_type);
	vc_item_release(item);

	/* reset cache statistics */
	vc_cache->hits = 0;
	vc_cache->misses = 0;

	vc_try_unlock();

	return ret;
}

int	zbx_vc_get_item_state(zbx_uint64_t itemid, int *status, int *active_range, int *values_total,
		int *db_cached_from)
{
	zbx_vc_item_t	*item;
	int		ret = FAIL;

	vc_try_lock();

	if (NULL != (item = (zbx_vc_item_t *)zbx_hashset_search(&vc_cache->items, &itemid)))
	{
		*status = item->status;
		*active_range = item->active_range;
		*values_total = item->values_total;
		*db_cached_from = item->db_cached_from;

		ret = SUCCEED;
	}

	vc_try_unlock();

	return ret;
}

int	zbx_vc_get_cache_state(int *mode, zbx_uint64_t *hits, zbx_uint64_t *misses)
{
	if (NULL == vc_cache)
		return FAIL;

	vc_try_lock();

	*mode = vc_cache->mode;
	*hits = vc_cache->hits;
	*misses = vc_cache->misses;

	vc_try_unlock();

	return SUCCEED;
}
