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

#include "zbxprometheus.h"

#include "item_preproc.h"

ZBX_VECTOR_IMPL(ppcache, zbx_preproc_cache_ref_t)

/******************************************************************************
 *                                                                            *
 * Purpose: get cache by preprocessing step type                              *
 *                                                                            *
 * Parameters: cache - [IN] the preprocessing cache                           *
 *             type  - [IN] the preprocessing step type                       *
 *                                                                            *
 * Return value: The preprocessing step cache or NULL if its type was not     *
 *               cached.                                                      *
 *                                                                            *
 ******************************************************************************/
void	*zbx_preproc_cache_get(zbx_preproc_cache_t *cache, unsigned char type)
{
	int	i;

	for (i = 0; i < cache->refs.values_num; i++)
	{
		if (cache->refs.values[i].type == type)
			return cache->refs.values[i].impl;
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: put preprocessing step cache into preprocessing cache             *
 *                                                                            *
 * Parameters: cache - [IN] the preprocessing cache                           *
 *             type  - [IN] the preprocessing step type                       *
 *             impl  - [IN] the preprocessing step cache                      *
 *                                                                            *
 * Return value: The preprocessing step cache or NULL if its type was not     *
 *               cached.                                                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_preproc_cache_put(zbx_preproc_cache_t *cache, unsigned char type, void *impl)
{
	zbx_preproc_cache_ref_t	ref;

	ref.type = type;
	ref.impl = impl;

	zbx_vector_ppcache_append(&cache->refs, ref);
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize preprocessing cache                                    *
 *                                                                            *
 ******************************************************************************/
void	zbx_preproc_cache_init(zbx_preproc_cache_t *cache)
{
	zbx_vector_ppcache_create(&cache->refs);
}

/******************************************************************************
 *                                                                            *
 * Purpose: free resources allocated by preprocessing cache                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_preproc_cache_clear(zbx_preproc_cache_t *cache)
{
	int	 i;

	for (i = 0; i < cache->refs.values_num; i++)
	{
		switch (cache->refs.values[i].type)
		{
			case ZBX_PREPROC_PROMETHEUS_PATTERN:
				zbx_prometheus_clear((zbx_prometheus_t *)cache->refs.values[i].impl);
				zbx_free(cache->refs.values[i].impl);
				break;
			case ZBX_PREPROC_JSONPATH:
				zbx_jsonobj_clear((zbx_jsonobj_t *)cache->refs.values[i].impl);
				zbx_free(cache->refs.values[i].impl);
				break;
		}
	}

	zbx_vector_ppcache_destroy(&cache->refs);
}
