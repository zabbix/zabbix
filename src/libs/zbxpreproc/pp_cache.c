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

/* debug logging, remove for release */

#include "pp_cache.h"
#include "zbxjson.h"
#include "zbxprometheus.h"
#include "preproc_snmp.h"

/******************************************************************************
 *                                                                            *
 * Purpose: create preprocessing cache                                        *
 *                                                                            *
 * Parameters: preproc  - [IN] the preprocessing data                         *
 *             value    - [IN/OUT] the input value - it will copied to cache  *
 *                                 and cleared                                *
 *                                                                            *
 * Return value: The created preprocessing cache                              *
 *                                                                            *
 ******************************************************************************/
zbx_pp_cache_t	*pp_cache_create(const zbx_pp_item_preproc_t *preproc, const zbx_variant_t *value)
{
	zbx_pp_cache_t	*cache = (zbx_pp_cache_t *)zbx_malloc(NULL, sizeof(zbx_pp_cache_t));

	cache->type = (0 != preproc->steps_num ? preproc->steps[0].type : ZBX_PREPROC_NONE);
	zbx_variant_copy(&cache->value, value);
	cache->data = NULL;
	cache->refcount = 1;

	return cache;
}

/******************************************************************************
 *                                                                            *
 * Purpose: free preprocessing cache                                          *
 *                                                                            *
 * Parameters: cache - [IN] the preprocessing cache                           *
 *                                                                            *
 ******************************************************************************/
static void	pp_cache_free(zbx_pp_cache_t *cache)
{
	zbx_variant_clear(&cache->value);

	if (NULL != cache->data)
	{
		switch (cache->type)
		{
			case ZBX_PREPROC_JSONPATH:
				zbx_jsonobj_clear((zbx_jsonobj_t *)cache->data);
				break;
			case ZBX_PREPROC_PROMETHEUS_PATTERN:
				zbx_prometheus_clear((zbx_prometheus_t *)cache->data);
				break;
			case ZBX_PREPROC_SNMP_WALK_TO_VALUE:
				zbx_snmp_value_cache_clear((zbx_snmp_value_cache_t *)cache->data);
				break;
		}

		zbx_free(cache->data);
	}

	zbx_free(cache);
}

/******************************************************************************
 *                                                                            *
 * Purpose: release preprocessing cache                                       *
 *                                                                            *
 * Parameters: cache - [IN] the preprocessing cache                           *
 *                                                                            *
 ******************************************************************************/
void	pp_cache_release(zbx_pp_cache_t *cache)
{
	if (NULL == cache || 0 != --cache->refcount)
		return;

	pp_cache_free(cache);
}

/******************************************************************************
 *                                                                            *
 * Purpose: copy preprocessing cache                                          *
 *                                                                            *
 * Parameters: cache - [IN] the preprocessing cache                           *
 *                                                                            *
 * Return value: The copied preprocessing cache.                              *
 *                                                                            *
 ******************************************************************************/
zbx_pp_cache_t	*pp_cache_copy(zbx_pp_cache_t *cache)
{
	if (NULL == cache)
		return NULL;

	cache->refcount++;

	return cache;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copy original value from cache if needed                          *
 *                                                                            *
 * Parameters: cache     - [IN] the preprocessing cache                       *
 *             step_type - [IN] the preprocessing step type                   *
 *             value     - [OUT] the output value                             *
 *                                                                            *
 * Comments: The value is copied from preprocessing cache if cache exists and *
 *           cache is not initialized or wrong preprocessing step type is     *
 *           cached. Otherwise the cache will be used to execute the step.    *
 *                                                                            *
 ******************************************************************************/
void	pp_cache_prepare_output_value(zbx_pp_cache_t *cache, int step_type, zbx_variant_t *value)
{
	if (NULL == cache->data || step_type != cache->type)
		zbx_variant_copy(value, &cache->value);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if caching can be done for the specified preprocessing      *
 *          data                                                              *
 *                                                                            *
 * Parameters: preproc  - [IN] the preprocessing data                         *
 *                                                                            *
 * Return value: SUCCEED - the preprocessing caching is possible              *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	pp_cache_is_supported(zbx_pp_item_preproc_t *preproc)
{
	if (0 < preproc->steps_num)
	{
		switch (preproc->steps[0].type)
		{
			case ZBX_PREPROC_JSONPATH:
			case ZBX_PREPROC_PROMETHEUS_PATTERN:
			case ZBX_PREPROC_SNMP_WALK_TO_VALUE:
				return SUCCEED;
		}
	}

	return FAIL;
}
