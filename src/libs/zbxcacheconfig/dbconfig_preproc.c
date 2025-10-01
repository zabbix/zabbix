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

#include "dbconfig_local.h"
#include "dbconfig.h"
#include "zbxcacheconfig.h"
#include "zbxlog.h"
#include "zbxpreprocbase.h"
#include "zbx_item_constants.h"

static void	dcl_preproc_item_clear(void *data)
{
	zbx_pp_item_t        *item = (zbx_pp_item_t *)data;

	// TODO: release item->preproc
}

void	dcl_preproc_cache_init(zbx_dcl_preproc_cache_t *cache)
{
	int	err;

	if (0 != (err = pthread_mutex_init(&cache->mu, NULL)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize pre-processing cache mutex: %s", zbx_strerror(err));
		zbx_exit(EXIT_FAILURE);
	}

	zbx_hashset_create_ext(&cache->items, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC,
			dcl_preproc_item_clear, ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC,
			ZBX_DEFAULT_MEM_FREE_FUNC);

	cache->um_handle = NULL;
}

void	dcl_preproc_cache_destroy(zbx_dcl_preproc_cache_t *cache)
{
	zbx_hashset_destroy(&cache->items);
	pthread_mutex_destroy(&cache->mu);
}

/******************************************************************************
 *                                                                            *
 * Purpose: sync item preprocessing steps with preprocessing manager cache,   *
 *          updating preprocessing revision if any changes were detected      *
 *                                                                            *
 ******************************************************************************/
static void	dcl_preproc_cache_sync_preproc(zbx_pp_item_preproc_t *item_preproc, const zbx_dcl_preproc_t *preproc)
{
	item_preproc->steps = (zbx_pp_step_t *)zbx_malloc(NULL, sizeof(zbx_pp_step_t) *
			(size_t)preproc->ops.values_num);

	for (int i = 0; i < preproc->ops.values_num; i++)
	{
		zbx_dc_preproc_op_t	*op = (zbx_dc_preproc_op_t *)preproc->ops.values[i];

		item_preproc->steps[i].type = op->type;
		item_preproc->steps[i].error_handler = op->error_handler;

		item_preproc->steps[i].params =  zbx_strdup(NULL, op->params);
		item_preproc->steps[i].error_handler_params = zbx_strdup(NULL, op->error_handler_params);
	}

	item_preproc->steps_num = preproc->ops.values_num;
	item_preproc->pp_revision = preproc->revision;
}

static void	dcl_preproc_cache_sync_item(zbx_dcl_preproc_cache_t *cache, ZBX_DC_ITEM *dc_item,
		zbx_dcl_preproc_t *preproc, zbx_uint64_t revision)
{
	zbx_pp_item_t		*pp_item;
	zbx_pp_history_cache_t	*history_cache = NULL;

	if (NULL == (pp_item = (zbx_pp_item_t *)zbx_hashset_search(&cache->items, &dc_item->itemid)))
	{
		zbx_pp_item_t	pp_item_local = {.itemid = dc_item->itemid};

		pp_item = (zbx_pp_item_t *)zbx_hashset_insert(&cache->items, &pp_item_local, sizeof(pp_item_local));
	}
	else
	{
		if (NULL != preproc && pp_item->preproc->pp_revision == preproc->revision)
			history_cache = zbx_pp_history_cache_acquire(pp_item->preproc->history_cache);

		zbx_pp_item_preproc_release(pp_item->preproc);
	}

	pp_item->preproc = zbx_pp_item_preproc_create(dc_item->hostid, dc_item->value_type, dc_item->flags);
	pp_item->revision = revision;

	if (NULL != dc_item->master_item)
		dc_preproc_sync_masteritem(pp_item->preproc, dc_item->master_item);

	if (NULL != preproc)
		dcl_preproc_cache_sync_preproc(pp_item->preproc, preproc);

	for (int i = 0; i < pp_item->preproc->steps_num; i++)
	{
		if (SUCCEED == zbx_pp_preproc_has_history(pp_item->preproc->steps[i].type))
		{
			pp_item->preproc->history_num++;

			if (SUCCEED == zbx_pp_preproc_has_serial_history(pp_item->preproc->steps[i].type))
				pp_item->preproc->mode = ZBX_PP_PROCESS_SERIAL;
		}
	}

	pp_item->preproc->history_cache = history_cache;
	if (0 != pp_item->preproc->history_num)
	{
		if (NULL == pp_item->preproc->history_cache)
			pp_item->preproc->history_cache = zbx_pp_history_cache_create();
	}
}

static void	dcl_preproc_cache_sync_item_rec(zbx_dcl_preproc_cache_t *cache, ZBX_DC_ITEM *dc_item,
		zbx_uint64_t revision)
{
	zbx_dcl_item_t		*item;
	zbx_dcl_preproc_t	*preproc = NULL;
	ZBX_DC_HOST		*dc_host;

	if (NULL == (dc_host = (ZBX_DC_HOST *)zbx_hashset_search(&config->hosts, &dc_item->hostid)))
		return;

	if (HOST_STATUS_MONITORED != dc_host->status)
		return;

	if (ITEM_STATUS_ACTIVE != dc_item->status || ITEM_TYPE_DEPENDENT == dc_item->type)
		return;

	if (ZBX_ITEM_PREPROCESSING_NONE == zbx_dc_item_requires_preprocessing(dc_item))
		return;

	if (HOST_MONITORED_BY_SERVER == dc_host->monitored_by ||
			SUCCEED == zbx_is_item_processed_by_server(dc_item->type, dc_item->key) ||
			ITEM_TYPE_TRAPPER == dc_item->type || (ITEM_TYPE_HTTPAGENT == dc_item->type &&
			1 == dc_item->itemtype.httpitem->allow_traps))
	{
		dc_preproc_add_item_rec(dc_item, &items_sync);
	}


	if (NULL != (item = (zbx_dcl_item_t *)zbx_hashset_search(&dcl_config()->items, &dc_item->itemid)))
		preproc = item->preproc;

	dcl_preproc_cache_sync_item(cache, dc_item, preproc, revision);
}


static void	dcl_preproc_cache_update_item(zbx_dcl_preproc_cache_t *cache, zbx_uint64_t itemid)
{
	zbx_dcl_pp_item_t	*item;

	item = (zbx_dcl_pp_item_t *)zbx_hashset_search(&cache->items, &itemid);
}

static void	dcl_preproc_cache_remove_preproc(zbx_dcl_preproc_cache_t *cache, zbx_uint64_t itemid)
{
	zbx_dcl_pp_item_t        *item;

	if (NULL == (item = (zbx_dcl_pp_item_t *)zbx_hashset_search(&cache->items, &itemid)))
		return;

	zbx_pp_item_preproc_release(item->preproc);
	item->preproc = NULL;

}

void	dcl_preproc_cache_update(zbx_dcl_preproc_cache_t *cache, const zbx_vector_uint64_t *itemids)
{
	zbx_dc_item_t			*item;
	zbx_dc_um_shared_handle_t	*um_handle_new = NULL;

	zbx_dcl_preproc_lock();

	um_handle_new = zbx_dc_um_shared_handle_update(cache->um_handle);

	if (NULL == itemids)
	{
		/* initial sync */

		zbx_hashset_iter_t	iter;

		zbx_hashset_iter_reset(&iter, cache->items);
		while (NULL != (item = (zbx_dc_item_t *)zbx_hashset_iter_next(&iter)))
			dcl_preproc_cache_update_item(cache, item);
	}
	else
	{
		for (int i = 0; i < itemids->values_num; i++)
		{
			item = (zbx_dc_item_t *)zbx_hashset_search(&dcl_config()->items, &itemids->values[i]);

			if (NULL != item)
				dcl_preproc_cache_update_item(cache, item);
			else
				dcl_preproc_cache_remove_preproc(cache, itemids->values[i]);
		}
	}

	if (SUCCEED == zbx_dc_um_shared_handle_reacquire(cache->um_handle, um_handle_new))
		cache->um_handle = um_handle_new;

	zbx_dcl_preproc_unlock();
}

void	dcl_update_preproc(const zbx_vector_uint64_t *itemids)
{
	dcl_preproc_cache_update(&dcl_config()->preproc, itemids);
}

void	zbx_dcl_preproc_lock(void)
{
}

void	zbx_dcl_preproc_unlock(void)
{
}

