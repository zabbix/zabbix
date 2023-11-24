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

#include "pg_cache.h"
#include "zbxcacheconfig.h"

void	pg_group_clear(zbx_pg_group_t *group)
{
	for (int i = 0; i < group->proxies.values_num; i++)
		group->proxies.values[i]->group = NULL;

	zbx_vector_pg_proxy_ptr_destroy(&group->proxies);
	zbx_vector_uint64_destroy(&group->hostids);
}

void	pg_cache_init(zbx_pg_cache_t *cache)
{
	zbx_hashset_create(&cache->groups, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	cache->group_revision = 0;

	zbx_vector_pg_group_ptr_create(&cache->updates);

	pthread_mutex_init(&cache->lock, NULL);
}

void	pg_cache_destroy(zbx_pg_cache_t *cache)
{
	pthread_mutex_destroy(&cache->lock);

	zbx_vector_pg_group_ptr_destroy(&cache->updates);

	zbx_hashset_iter_t	iter;
	zbx_pg_group_t		*group;

	zbx_hashset_iter_reset(&cache->groups, &iter);
	while (NULL != (group = (zbx_pg_group_t *)zbx_hashset_iter_next(&iter)))
		pg_group_clear(group);

	zbx_hashset_destroy(&cache->groups);

}


void	pg_cache_queue_update(zbx_pg_cache_t *cache, zbx_pg_group_t *group)
{
	int	i;

	for (i = 0; i < cache->updates.values_num; i++)
	{
		if (cache->updates.values[i] == group)
			return;
	}

	zbx_vector_pg_group_ptr_append(&cache->updates, group);
}

void	pg_cache_process_updates(zbx_pg_cache_t *cache)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() groups:%d", __func__, cache->updates.values_num);

	for (int i = 0; i < cache->updates.values_num; i++)
	{
		/* TODO: host reassignment implementation */
		pg_cache_dump_group(cache->updates.values[i]);
	}

	zbx_vector_pg_group_ptr_clear(&cache->updates);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

// WDN: debug
void	pg_cache_dump_group(zbx_pg_group_t *group)
{
	zabbix_log(LOG_LEVEL_DEBUG, "proxy group:" ZBX_FS_UI64, group->proxy_groupid);
	zabbix_log(LOG_LEVEL_DEBUG, "    status:%d failover_delay:%d min_online:%d");

	zabbix_log(LOG_LEVEL_DEBUG, "    hostids:");
	for (int i = 0; i < group->hostids.values_num; i++)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "        " ZBX_FS_UI64, group->hostids.values[i]);
	}
}

