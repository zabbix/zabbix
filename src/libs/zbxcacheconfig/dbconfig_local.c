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
#include "dbsync.h"
#include "zbxalgo.h"

/* avoid external (shared memory) string pool usage for local data */
#define DCL_EXTERN_STRPOOL_ERROR	#error "Shared memory strpool must not be updated from local cache"
#define dc_strpool_intern(s)	DCL_EXTERN_STRPOOL_ERROR
#define dc_strpool_release(s)	DCL_EXTERN_STRPOOL_ERROR
#define dc_strpool_acquire(s)	DCL_EXTERN_STRPOOL_ERROR
#define dc_strpool_replace(s)	DCL_EXTERN_STRPOOL_ERROR

ZBX_PTR_VECTOR_IMPL(dcl_item_ptr, zbx_dcl_item_t *)

static zbx_dcl_config_t	config_local;

/* private strpool functions */

#define	REFCOUNT_FIELD_SIZE	sizeof(zbx_uint32_t)

static zbx_hash_t	dcl_strpool_str_hash(const void *data)
{
	return ZBX_DEFAULT_STRING_HASH_FUNC((const char *)data + REFCOUNT_FIELD_SIZE);
}

static int	dcl_strpool_str_compare(const void *d1, const void *d2)
{
	return strcmp((const char *)d1 + REFCOUNT_FIELD_SIZE, (const char *)d2 + REFCOUNT_FIELD_SIZE);
}

const char	*dcl_strpool_intern(const char *str)
{
	void		*record;
	zbx_uint32_t	*refcount;
	size_t		size;

	if (NULL == str)
		return NULL;

	size = REFCOUNT_FIELD_SIZE + strlen(str) + 1;
	record = zbx_hashset_insert_ext(&config_local.strpool, str - REFCOUNT_FIELD_SIZE, size, REFCOUNT_FIELD_SIZE, size,
			ZBX_HASHSET_UNIQ_FALSE);

	refcount = (zbx_uint32_t *)record;
	(*refcount)++;

	return (char *)record + REFCOUNT_FIELD_SIZE;
}

void	dcl_strpool_release(const char *str)
{
	zbx_uint32_t	*refcount;

	refcount = (zbx_uint32_t *)(str - REFCOUNT_FIELD_SIZE);
	if (0 == --(*refcount))
		zbx_hashset_remove(&config_local.strpool, str - REFCOUNT_FIELD_SIZE);
}

const char	*dcl_strpool_acquire(const char *str)
{
	zbx_uint32_t	*refcount;

	if (NULL == str)
		return NULL;

	refcount = (zbx_uint32_t *)(str - REFCOUNT_FIELD_SIZE);
	(*refcount)++;

	return str;
}

int	dcl_strpool_replace(int found, const char **curr, const char *new_str)
{
	if (1 == found && NULL != *curr)
	{
		if (0 == strcmp(*curr, new_str))
			return FAIL;

		dcl_strpool_release(*curr);
	}

	*curr = dcl_strpool_intern(new_str);

	return SUCCEED;	/* indicate that the string has been replaced */
}

/* string pool end */

static void	dcl_preproc_free(zbx_dcl_preproc_t *item)
{
	zbx_vector_dc_preproc_op_ptr_destroy(&item->ops);
	zbx_free(item);
}

static void	dcl_item_clear(zbx_dcl_item_t *item)
{
	dcl_preproc_free(item->preproc);
}


static void	dcl_preprocop_clear(void *d)
{
	zbx_dc_preproc_op_t	*op = (zbx_dc_preproc_op_t *)d;

	dcl_strpool_release(op->params);
	dcl_strpool_release(op->error_handler_params);
}

void	dcl_config_init(void)
{
	zbx_dcl_config_t	*dc_local = &config_local;

	zbx_hashset_create(config->strpool, 0, dcl_strpool_str_hash, dcl_strpool_str_compare);

	zbx_hashset_create(&dc_local->item_tag_links, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_hashset_create_ext(&dc_local->items, 0, dcl_item_clear,
			ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	zbx_hashet_create_ext(&dc_local->preprocops, 0, dcl_preprocop_clear,
			ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

}

void	dcl_config_clear(void)
{
	zbx_dcl_config_t	*dc_local = &config_local;

	zbx_hashset_destroy(&dc_local->item_tag_links);
	zbx_hashset_destroy(&dc_local->items);
	zbx_hashset_destroy(&dc_local->preprocops);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get local configuration cache                                     *
 *                                                                            *
 ******************************************************************************/
zbx_dcl_config_t	*dcl_config(void)
{
	return &config_local;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compare two item preprocessing operations by step                 *
 *                                                                            *
 * Comments: This function is used to sort correlation conditions by type.    *
 *                                                                            *
 ******************************************************************************/
static int	dcl_compare_preprocops_by_step(const void *d1, const void *d2)
{
	zbx_dc_preproc_op_t	*p1 = *(zbx_dc_preproc_op_t **)d1;
	zbx_dc_preproc_op_t	*p2 = *(zbx_dc_preproc_op_t **)d2;

	if (ZBX_PREPROC_VALIDATE_NOT_SUPPORTED == p1->type && ZBX_PREPROC_VALIDATE_NOT_SUPPORTED == p2->type)
	{
		if (p1->step < p2->step)
			return -1;

		if (p1->step > p2->step)
			return 1;
	}

	if (ZBX_PREPROC_VALIDATE_NOT_SUPPORTED == p1->type)
		return -1;

	if (ZBX_PREPROC_VALIDATE_NOT_SUPPORTED == p2->type)
		return 1;

	ZBX_RETURN_IF_NOT_EQUAL(p1->step, p2->step);

	return 0;
}

static void	dcl_item_update_preproc(zbx_dcl_item_t *item, zbx_uint64_t revision)
{
	zbx_dcl_preproc_t	*preproc;

	if (NULL == (preproc = item->preproc))
		return;

	if (0 == preproc->ops.values_num)
	{
		dc_preprocitem_free(preproc);
		item->preproc = NULL;
	}
	else
	{
		zbx_vector_dc_preproc_op_sort(&preproc->ops, dcl_compare_preprocops_by_step);
		preproc->revision = revision;
	}
}

static void	dcl_item_update_revision(zbx_dcl_item_t *item, zbx_uint64_t revision)
{
	zbx_dc_item_t	*dc_item;

	if (NULL != ((zbx_dc_item_t *)dc_item = zbx_hashset_search(&config->items, &item->itemid)))
		dc_item_update_revision(dc_item, revision);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Updates item preprocessing steps in configuration cache           *
 *                                                                            *
 * Parameters: sync     - [IN] db synchronization data                        *
 *             revision - [IN] new configuration revision                     *
 *             itemids  - [OUT] updated itemids when syncing update, NULL for *
 *                              initial sync                                  *
 *                                                                            *
 * Comments: The result contains the following fields:                        *
 *           0 - item_preprocid                                               *
 *           1 - itemid                                                       *
 *           2 - type                                                         *
 *           3 - params                                                       *
 *                                                                            *
 ******************************************************************************/
void	dcl_sync_item_preproc(zbx_dbsync_t *sync, zbx_uint64_t revision, zbx_vector_uint64_t *itemids)
{
	char				**row;
	zbx_uint64_t			rowid;
	unsigned char			tag;
	zbx_uint64_t			item_preprocid, itemid;
	zbx_hashset_uniq_t		uniq = ZBX_HASHSET_UNIQ_FALSE;
	int				found, ret, i, index, row_num;
	zbx_dcl_preproc_t		*preproc = NULL;
	zbx_dc_preproc_op_t		*op;
	zbx_vector_dcl_item_ptr_t	items;
	zbx_dcl_config_t		*dc_local = &config_local;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	row_num = zbx_dbsync_get_row_num(sync);

	if (0 == dc_local->preprocops.num_slots)
	{
		zbx_hashset_reserve(&config->preprocops, MAX(row_num, 100));
		uniq = ZBX_HASHSET_UNIQ_TRUE;
	}

	if (ZBX_DBSYNC_INIT != sync->mode)
	{
		zbx_vector_dcl_item_ptr_create(&items);
		zbx_vector_dcl_item_ptr_reserve(&items, row_num);
	}

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		int		found;
		zbx_dcl_item_t	*item;
		zbx_dc_item_t	*dc_item;

		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(itemid, row[1]);

		if (NULL == (dc_item = (zbx_dc_item_t *)zbx_hashset_search(&config->items, &itemid)))
			continue;

		item = (zbx_dcl_item_t *)DCfind_id(&dc_local->items, &itemid, sizeof(zbx_dcl_item_t), &found);

		if (0 == found)
		{
			preproc = (zbx_dcl_preproc_t *)zbx_malloc(NULL, sizeof(zbx_dcl_preproc_t));
			zbx_vector_dc_preproc_op_create(&preproc->ops);
			item->preproc = preproc;
		}

		if (ZBX_DBSYNC_INIT != sync->mode)
			zbx_vector_dcl_item_ptr_append(&items, item);

		ZBX_STR2UINT64(item_preprocid, row[0]);

		op = (zbx_dc_preproc_op_t *)DCfind_id_ext(&dc_local->preprocops, item_preprocid,
				sizeof(zbx_dc_preproc_op_t), &found, uniq);

		ZBX_STR2UCHAR(op->type, row[2]);
		dcl_strpool_replace(found, &op->params, row[3]);
		op->step = atoi(row[4]);
		op->error_handler = atoi(row[5]);
		dcl_strpool_replace(found, &op->error_handler_params, row[6]);

		if (0 == found)
		{
			op->itemid = itemid;
			zbx_vector_ptr_reserve(&preproc->ops, ZBX_VECTOR_ARRAY_RESERVE);
			zbx_vector_ptr_append(&preproc->ops, op);
		}
	}

	/* remove deleted item preprocessing operations */

	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		zbx_dcl_item_t	*item;

		if (NULL == (op = (zbx_dc_preproc_op_t *)zbx_hashset_search(&dc_local->preprocops, &rowid)))
			continue;

		if (NULL != (item = (ZBX_DC_ITEM *)zbx_hashset_search(&dc_local->items, &op->itemid)) &&
				NULL != (preproc = item->preproc))
		{
			if (FAIL != (index = zbx_vector_ptr_search(&preproc->ops, op,
					ZBX_DEFAULT_PTR_COMPARE_FUNC)))
			{
				zbx_vector_ptr_remove_noorder(&preproc->ops, index);

				if (ZBX_DBSYNC_INIT != sync->mode)
					zbx_vector_dcl_item_ptr_append(&items, item);
			}
		}

		dcl_strpool_release(op->params);
		dcl_strpool_release(op->error_handler_params);
		zbx_hashset_remove_direct(&dc_local->preprocops, op);
	}

	/* sort item preprocessing operations by step and update revisions */
	if (ZBX_DBSYNC_INIT != sync->mode)
	{
		zbx_vector_dc_item_ptr_sort(&items, ZBX_DEFAULT_PTR_COMPARE_FUNC);
		zbx_vector_dc_item_ptr_uniq(&items, ZBX_DEFAULT_PTR_COMPARE_FUNC);

		for (i = 0; i < items.values_num; i++)
		{
			if (NULL != itemids)
				zbx_vector_uint64_append(itemids, items.values[i]->itemid);

			dcl_item_update_preproc(items.values[i]);
			dcl_item_update_revision(items.values[i], revision);
		}

		zbx_vector_dcl_item_ptr_destroy(&items);
	}
	else
	{
		zbx_hashset_iter_t	iter;
		zbx_dcl_item_t		*item;

		zbx_hashset_iter_reset(&iter, &dc_local->items);
		while (NULL != (item = (zbx_dcl_item_t *)zbx_hashset_iter_next(&iter)))
			dcl_item_update_preproc(item);
	}

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	dcl_dump_item(zbx_uint64_t itemid)
{
	zbx_dcl_item_t	*item;

	if (NULL != (item = (zbx_dcl_item_t *)zbx_hashset_search(&config_local.items, &itemid)))
	{
		zabbix_log(LOG_LEVEL_TRACE, "  preprocessing:");

		for (int i = 0; i < item->preproc_item->ops.values_num; i++)
		{
			zbx_dc_preproc_op_t	*op = (zbx_dc_preproc_op_t *)item->preproc_item->ops.values[i];
			zabbix_log(LOG_LEVEL_TRACE, "      opid:" ZBX_FS_UI64 " step:%d type:%u params:'%s'"
					" error_handler:%d error_handler_params:'%s'",
					op->item_preprocid, op->step, op->type, op->params, op->error_handler,
					op->error_handler_params);
		}
}

