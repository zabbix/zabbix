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

#include "dbconfig_correlation.h"
#include "dbconfig.h"
#include "dbconfig_local.h"
#include "dbsync.h"
#include "zbxcacheconfig.h"

#include "zbxcommon.h"
#include "zbxdbhigh.h"
#include "zbxtypes_ext.h"
#include "zbxdb.h"
#include "zbxnum.h"
#include "zbxstr.h"
#include "zbxalgo.h"

static void	correlation_cache_handle_release(zbx_correlation_cache_handle_t handle);

struct zbx_correlation_cache_handle
{
	zbx_vector_correlation_ptr_t	correlations;
	zbx_atomic_uint32_t		refcount;
};

static zbx_corr_condition_t	*corr_condition_addref(zbx_corr_condition_t *cond)
{
	atomic_fetch_add(&cond->refcount, 1);

	return cond;
}

static void	corr_condition_release(zbx_corr_condition_t *cond)
{
	if (1 != atomic_fetch_sub(&cond->refcount, 1))
		return;

	switch (cond->type)
	{
		case ZBX_CORR_CONDITION_OLD_EVENT_TAG:
		case ZBX_CORR_CONDITION_NEW_EVENT_TAG:
			zbx_free(cond->data.tag.tag);
			break;
		case ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP:
			break;
		case ZBX_CORR_CONDITION_EVENT_TAG_PAIR:
			zbx_free(cond->data.tag_pair.oldtag);
			zbx_free(cond->data.tag_pair.newtag);
			break;
		case ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE:
		case ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE:
			zbx_free(cond->data.tag_value.tag);
			zbx_free(cond->data.tag_value.value);
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN_MSG("freeing unsupported correlation condition type %d", cond->type);
			break;
	}

	zbx_free(cond);
}

static zbx_correlation_t	*correlation_addref(zbx_correlation_t *correlation)
{
	atomic_fetch_add(&correlation->refcount, 1);

	return correlation;
}

static void	correlation_release(zbx_correlation_t *correlation)
{
	if (1 != atomic_fetch_sub(&correlation->refcount, 1))
		return;

	zbx_free(correlation->name);
	zbx_free(correlation->formula);

	for (int i = 0; i < correlation->conditions.values_num; i++)
		corr_condition_release(correlation->conditions.values[i]);

	zbx_vector_corr_condition_ptr_destroy(&correlation->conditions);

	zbx_free(correlation);
}

static void	correlation_ref_clear(void *a)
{
	zbx_correlation_ref_t	*ref = (zbx_correlation_ref_t *)a;

	correlation_release(ref->correlation);
}

static void	corr_condition_ref_clear(void *a)
{
	zbx_corr_condition_ref_t	*ref = (zbx_corr_condition_ref_t *)a;

	corr_condition_release(ref->condition);
}

zbx_correlation_cache_t	*correlation_cache_create(void)
{
	zbx_correlation_cache_t	*cache;

	cache = (zbx_correlation_cache_t *)zbx_malloc(NULL, sizeof(zbx_correlation_cache_t));

	if (0 != pthread_mutex_init(&cache->lock, NULL))
	{
		zbx_free(cache);
		THIS_SHOULD_NEVER_HAPPEN_MSG("failed to initialize channel mutex");
		exit(EXIT_FAILURE);
	}

	cache->correlations_num = 0;
	cache->handle = NULL;

	zbx_hashset_create_ext(&cache->correlations, 0, ZBX_DEFAULT_ID_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC, correlation_ref_clear, ZBX_DEFAULT_MEM_MALLOC_FUNC,
			ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	zbx_hashset_create(&cache->corr_operations, 0, ZBX_DEFAULT_ID_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_hashset_create_ext(&cache->corr_conditions, 0, ZBX_DEFAULT_ID_HASH_FUNC,
		ZBX_DEFAULT_UINT64_COMPARE_FUNC, corr_condition_ref_clear, ZBX_DEFAULT_MEM_MALLOC_FUNC,
		ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);
	return cache;
}

void	correlation_cache_destroy(zbx_correlation_cache_t *cache)
{
	zbx_hashset_destroy(&cache->correlations);
	zbx_hashset_destroy(&cache->corr_conditions);
	zbx_hashset_destroy(&cache->corr_operations);

	if (NULL != cache->handle)
		correlation_cache_handle_release(cache->handle);

	pthread_mutex_destroy(&cache->lock);

	zbx_free(cache);
}

static zbx_correlation_t	*correlation_create(zbx_uint64_t correlationid)
{
	zbx_correlation_t	*correlation;

	correlation = (zbx_correlation_t *)zbx_calloc(NULL, 1, sizeof(zbx_correlation_t));
	correlation->correlationid = correlationid;
	correlation->refcount = 1;
	zbx_vector_corr_condition_ptr_create(&correlation->conditions);

	return correlation;
}

static zbx_correlation_t	*correlation_clone(const zbx_correlation_t *correlation)
{
	zbx_correlation_t	*clone = correlation_create(correlation->correlationid);

	clone->name = zbx_strdup(NULL, correlation->name);
	clone->formula = zbx_strdup(NULL, correlation->formula);
	clone->evaltype = correlation->evaltype;
	clone->operations = correlation->operations;

	if (0 != correlation->conditions.values_num)
	{
		zbx_vector_corr_condition_ptr_reserve(&clone->conditions, (size_t)correlation->conditions.values_num);
		for (int i = 0; i < correlation->conditions.values_num; i++)
		{
			zbx_corr_condition_t	*condition = correlation->conditions.values[i];

			zbx_vector_corr_condition_ptr_append(&clone->conditions, corr_condition_addref(condition));
		}
	}

	return clone;
}

static zbx_correlation_t	*correlation_acquire(zbx_correlation_t *correlation)
{
	if (1 == atomic_load(&correlation->refcount))
		return correlation;

	return correlation_clone(correlation);
}

static void	correlation_ref_update(zbx_correlation_ref_t *ref, zbx_correlation_t *correlation)
{
	if (ref->correlation == correlation)
		return;

	if (NULL != ref->correlation)
		correlation_release(ref->correlation);

	ref->correlation = correlation;
}

static void	correlation_remove_condition(zbx_correlation_t *correlation, zbx_corr_condition_t *condition)
{
	for (int i = 0; i < correlation->conditions.values_num; i++)
	{
		if (correlation->conditions.values[i] == condition)
		{
			zbx_vector_corr_condition_ptr_remove_noorder(&correlation->conditions, i);
			corr_condition_release(condition);
			return;
		}
	}
}

static char	*correlation_basic_formula(const zbx_correlation_t *correlation)
{
	#define ZBX_OPERATION_TYPE_UNKNOWN	0
	#define ZBX_OPERATION_TYPE_OR		1
	#define ZBX_OPERATION_TYPE_AND		2

	char				*formula = NULL;
	const char			*op = NULL;
	size_t				formula_alloc = 0, formula_offset = 0;
	int				i, last_type = -1, last_op = ZBX_OPERATION_TYPE_UNKNOWN;
	const zbx_corr_condition_t	*condition;
	zbx_uint64_t			last_id;

	if (ZBX_CONDITION_EVAL_TYPE_EXPRESSION == correlation->evaltype || 0 == correlation->conditions.values_num)
		return NULL;

	switch (correlation->evaltype)
	{
		case ZBX_CONDITION_EVAL_TYPE_OR:
			op = " or ";
			break;
		case ZBX_CONDITION_EVAL_TYPE_AND:
			op = " and ";
			break;
	}

	if (NULL != op)
	{
		for (i = 0; i < correlation->conditions.values_num; i++)
		{
			if (0 != formula_offset)
				zbx_strcpy_alloc(&formula, &formula_alloc, &formula_offset, op);

			zbx_snprintf_alloc(&formula, &formula_alloc, &formula_offset, "{" ZBX_FS_UI64 "}",
					correlation->conditions.values[i]->corr_conditionid);
		}

		return formula;
	}

	last_id = correlation->conditions.values[0]->corr_conditionid;
	last_type = correlation->conditions.values[0]->type;

	for (i = 1; i < correlation->conditions.values_num; i++)
	{
		condition = correlation->conditions.values[i];

		if (last_type == condition->type)
		{
			if (last_op != ZBX_OPERATION_TYPE_OR)
				zbx_chrcpy_alloc(&formula, &formula_alloc, &formula_offset, '(');

			zbx_snprintf_alloc(&formula, &formula_alloc, &formula_offset, "{" ZBX_FS_UI64 "} or ",
					last_id);
			last_op = ZBX_OPERATION_TYPE_OR;
		}
		else
		{
			zbx_snprintf_alloc(&formula, &formula_alloc, &formula_offset, "{" ZBX_FS_UI64 "}",
					last_id);

			if (last_op == ZBX_OPERATION_TYPE_OR)
				zbx_chrcpy_alloc(&formula, &formula_alloc, &formula_offset, ')');

			zbx_strcpy_alloc(&formula, &formula_alloc, &formula_offset, " and ");

			last_op = ZBX_OPERATION_TYPE_AND;
		}

		last_type = condition->type;
		last_id = condition->corr_conditionid;
	}

	zbx_snprintf_alloc(&formula, &formula_alloc, &formula_offset, "{" ZBX_FS_UI64 "}", last_id);

	if (last_op == ZBX_OPERATION_TYPE_OR)
		zbx_chrcpy_alloc(&formula, &formula_alloc, &formula_offset, ')');

	return formula;

	#undef ZBX_OPERATION_TYPE_UNKNOWN
	#undef ZBX_OPERATION_TYPE_OR
	#undef ZBX_OPERATION_TYPE_AND
}

static zbx_corr_condition_t	*corr_condition_create(zbx_uint64_t conditionid, unsigned char type, zbx_db_row_t row)
{
	zbx_corr_condition_t	*condition;

	condition = (zbx_corr_condition_t *)zbx_calloc(NULL, 1, sizeof(zbx_corr_condition_t));
	condition->corr_conditionid = conditionid;
	condition->type = type;
	condition->refcount = 1;

	if (ZBX_CORR_CONDITION_OLD_EVENT_TAG == type || ZBX_CORR_CONDITION_NEW_EVENT_TAG == type)
	{
		condition->data.tag.tag = zbx_strdup(NULL, row[0]);

		return condition;
	}
	row++;

	if (ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE == type || ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE == type)
	{
		condition->data.tag_value.tag = zbx_strdup(NULL, row[0]);
		condition->data.tag_value.value = zbx_strdup(NULL, row[1]);
		ZBX_STR2UCHAR(condition->data.tag_value.op, row[2]);

		return condition;
	}
	row += 3;

	if (ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP == type)
	{
		ZBX_STR2UINT64(condition->data.group.groupid, row[0]);
		ZBX_STR2UCHAR(condition->data.group.op, row[1]);

		return condition;
	}
	row += 2;

	if (ZBX_CORR_CONDITION_EVENT_TAG_PAIR == condition->type)
	{
		condition->data.tag_pair.oldtag = zbx_strdup(NULL, row[0]);
		condition->data.tag_pair.newtag = zbx_strdup(NULL, row[1]);
	}

	return condition;
}

static int	correlation_compare_by_id(const void *a1, const void *a2)
{
	const zbx_correlation_t	*c1 = *(zbx_correlation_t **)a1;
	const zbx_correlation_t	*c2 = *(zbx_correlation_t **)a2;

	ZBX_RETURN_IF_NOT_EQUAL(c1->correlationid, c2->correlationid);

	return 0;
}

static zbx_correlation_cache_handle_t	correlation_cache_handle_create(void)
{
	zbx_correlation_cache_handle_t	handle;
	zbx_correlation_cache_t		*cache = dc_local()->correlation_cache;
	zbx_hashset_iter_t		iter;
	zbx_correlation_ref_t		*ref;

	handle = (zbx_correlation_cache_handle_t)zbx_malloc(NULL, sizeof(struct zbx_correlation_cache_handle));
	handle->refcount = 1;
	zbx_vector_correlation_ptr_create(&handle->correlations);
	zbx_vector_correlation_ptr_reserve(&handle->correlations, (size_t)cache->correlations.num_data);

	zbx_hashset_iter_reset(&cache->correlations, &iter);
	while (NULL != (ref = (zbx_correlation_ref_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_vector_correlation_ptr_append(&handle->correlations, correlation_addref(ref->correlation));
	}

	zbx_vector_correlation_ptr_sort(&handle->correlations, correlation_compare_by_id);

	return handle;
}

static void	correlation_cache_handle_release(zbx_correlation_cache_handle_t handle)
{
	if (1 != atomic_fetch_sub(&handle->refcount, 1))
		return;

	for (int i = 0; i < handle->correlations.values_num; i++)
		correlation_release(handle->correlations.values[i]);

	zbx_vector_correlation_ptr_destroy(&handle->correlations);

	zbx_free(handle);
}

/******************************************************************************
 *                                                                            *
 * Purpose: compare two correlation conditions by their type                  *
 *                                                                            *
 * Comments: This function is used to sort correlation conditions by type.    *
 *                                                                            *
 ******************************************************************************/
static int	compare_corr_conditions_by_type(const void *a1, const void *a2)
{
	zbx_corr_condition_t	*c1 = *(zbx_corr_condition_t **)a1;
	zbx_corr_condition_t	*c2 = *(zbx_corr_condition_t **)a2;

	ZBX_RETURN_IF_NOT_EQUAL(c1->type, c2->type);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: update correlations in local configuration cache                  *
 *                                                                            *
 * Parameters: sync - [IN] the db synchronization data                        *
 *                                                                            *
 * Comments: The result contains the following fields:                        *
 *           0 - correlationid                                                *
 *           1 - name                                                         *
 *           2 - evaltype                                                     *
 *           3 - formula                                                      *
 *                                                                            *
 ******************************************************************************/
static void	correlation_cache_sync_correlations(zbx_dbsync_t *sync)
{
	char			**row;
	zbx_uint64_t		rowid;
	unsigned char		tag;
	int			ret;
	zbx_correlation_cache_t	*cache = dc_local()->correlation_cache;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		zbx_correlation_ref_t	ref_local, *ref;
		zbx_correlation_t	*correlation;

		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(ref_local.correlationid, row[0]);
		ref_local.correlation = NULL;

		ref = (zbx_correlation_ref_t *)zbx_hashset_insert(&cache->correlations, &ref_local, sizeof(ref_local));

		if (NULL != ref->correlation)
			correlation = correlation_acquire(ref->correlation);
		else
			correlation = correlation_create(ref->correlationid);

		if (NULL == correlation->name || 0 != strcmp(correlation->name, row[1]))
			correlation->name = zbx_strdup(correlation->name, row[1]);

		if (ZBX_CONDITION_EVAL_TYPE_EXPRESSION == correlation->evaltype)
		{
			if (NULL == correlation->formula || 0 != strcmp(correlation->formula, row[1]))
				correlation->formula = zbx_strdup(correlation->formula, row[1]);
		}
		else if (NULL != ref->correlation)
		{
			/* for new correlations the formula will be updated during condition sync */
			zbx_free(correlation->formula);
			correlation->formula = correlation_basic_formula(correlation);
		}

		ZBX_STR2UCHAR(correlation->evaltype, row[2]);

		correlation_ref_update(ref, correlation);
	}

	/* remove deleted correlations */
	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		zbx_correlation_ref_t	ref_local, *ref;

		ref_local.correlationid = rowid;

		if (NULL == (ref = (zbx_correlation_ref_t *)zbx_hashset_search(&cache->correlations, &ref_local)))
			continue;

		zbx_hashset_remove_direct(&cache->correlations, ref);
	}

	atomic_store(&cache->correlations_num, cache->correlations.num_data);
	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: updates correlation operations in local configuration cache       *
 *                                                                            *
 * Parameters: result - [IN] the result of correlation operations database    *
 *                           select                                           *
 *                                                                            *
 * Comments: The result contains the following fields:                        *
 *           0 - corr_operationid                                             *
 *           1 - correlationid                                                *
 *           2 - type                                                         *
 *                                                                            *
 ******************************************************************************/
static void	correlation_cache_sync_operations(zbx_dbsync_t *sync)
{
	char			**row;
	zbx_uint64_t		rowid;
	unsigned char		tag;
	int			ret;
	zbx_correlation_cache_t	*cache = dc_local()->correlation_cache;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	/* When switching correlation operations they are recreated in database */
	/* rather than updated. This means server doesn't need to check if type */
	/* was changed.                                                         */
	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		zbx_correlation_ref_t	*ref;
		zbx_dc_corr_operation_t	op_local = {0}, *op;
		zbx_uint64_t		correlationid;
		zbx_correlation_t	*correlation;

		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(correlationid, row[1]);

		if (NULL == (ref = (zbx_correlation_ref_t *)zbx_hashset_search(&cache->correlations, &correlationid)))
			continue;

		ZBX_STR2UINT64(op_local.corr_operationid, row[0]);
		op = (zbx_dc_corr_operation_t *)zbx_hashset_insert(&cache->corr_operations, &op_local,
				sizeof(op_local));
		op->correlationid = ref->correlationid;
		correlation = correlation_acquire(ref->correlation);
		ZBX_STR2UCHAR(op->type, row[2]);

		switch (op->type)
		{
			case ZBX_CORR_OPERATION_CLOSE_OLD:
				ref->correlation->operations |= CORRELATION_OP_CLOSE_OLD;
				break;
			case ZBX_CORR_OPERATION_CLOSE_NEW:
				ref->correlation->operations |= CORRELATION_OP_CLOSE_NEW;
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN_MSG("unsupported correlation operation");
				continue;
		}

		correlation_ref_update(ref, correlation);
	}

	/* remove deleted correlation operations */

	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		zbx_correlation_ref_t	*ref;
		zbx_dc_corr_operation_t	*op;
		zbx_correlation_t	*correlation;

		if (NULL == (op = (zbx_dc_corr_operation_t *)zbx_hashset_search(&cache->corr_operations, &rowid)))
			continue;

		if (NULL == (ref = (zbx_correlation_ref_t *)zbx_hashset_search(&cache->correlations,
				&op->correlationid)))
		{
			continue;
		}

		correlation = correlation_acquire(ref->correlation);

		switch (op->type)
		{
			case ZBX_CORR_OPERATION_CLOSE_OLD:
				correlation->operations &= ~CORRELATION_OP_CLOSE_OLD;
				break;
			case ZBX_CORR_OPERATION_CLOSE_NEW:
				correlation->operations &= ~CORRELATION_OP_CLOSE_NEW;
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN_MSG("unsupported correlation operation");
				continue;
		}
		zbx_hashset_remove_direct(&cache->corr_operations, op);

		correlation_ref_update(ref, correlation);
	}

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Updates correlation conditions configuration cache                *
 *                                                                            *
 * Parameters: sync - [IN] the db synchronization data                        *
 *                                                                            *
 * Comments: The result contains the following fields:                        *
 *           0 - corr_conditionid                                             *
 *           1 - correlationid                                                *
 *           2 - type                                                         *
 *           3 - corr_condition_tag.tag                                       *
 *           4 - corr_condition_tagvalue.tag                                  *
 *           5 - corr_condition_tagvalue.value                                *
 *           6 - corr_condition_tagvalue.operator                             *
 *           7 - corr_condition_group.groupid                                 *
 *           8 - corr_condition_group.operator                                *
 *           9 - corr_condition_tagpair.oldtag                                *
 *          10 - corr_condition_tagpair.newtag                                *
 *                                                                            *
 ******************************************************************************/
static void	correlation_cache_sync_conditions(zbx_dbsync_t *sync)
{
	char				**row;
	zbx_uint64_t			rowid;
	unsigned char			tag;
	zbx_uint64_t			correlationid;
	int				ret;
	zbx_vector_correlation_ptr_t	correlations;
	zbx_correlation_cache_t		*cache = dc_local()->correlation_cache;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dcsync_sync_start(sync, dbconfig_used_size());

	zbx_vector_correlation_ptr_create(&correlations);

	while (SUCCEED == (ret = zbx_dbsync_next(sync, &rowid, &row, &tag)))
	{
		zbx_correlation_ref_t		*ref;
		zbx_corr_condition_ref_t	cond_ref_local = {0}, *cond_ref;
		unsigned char			type;
		zbx_correlation_t		*correlation;

		/* removed rows will be always added at the end */
		if (ZBX_DBSYNC_ROW_REMOVE == tag)
			break;

		ZBX_STR2UINT64(correlationid, row[1]);

		if (NULL == (ref = (zbx_correlation_ref_t *)zbx_hashset_search(&cache->correlations, &correlationid)))
			continue;

		ZBX_STR2UINT64(cond_ref_local.conditionid, row[0]);

		cond_ref = (zbx_corr_condition_ref_t *)zbx_hashset_insert(&cache->corr_conditions, &cond_ref_local,
				sizeof(cond_ref_local));

		correlation = correlation_acquire(ref->correlation);

		if (NULL != cond_ref->condition)
			corr_condition_release(cond_ref->condition);
		else
			cond_ref->correlationid = correlationid;

		ZBX_STR2UCHAR(type, row[2]);
		cond_ref->condition = corr_condition_create(cond_ref->conditionid, type, row + 3);

		/* sort the conditions later */
		if (ZBX_CONDITION_EVAL_TYPE_AND_OR == correlation->evaltype)
			zbx_vector_correlation_ptr_append(&correlations, correlation);

		zbx_vector_corr_condition_ptr_append(&correlation->conditions,
				corr_condition_addref(cond_ref->condition));

		correlation_ref_update(ref, correlation);
	}

	/* remove deleted correlation conditions */

	for (; SUCCEED == ret; ret = zbx_dbsync_next(sync, &rowid, &row, &tag))
	{
		zbx_correlation_ref_t		*ref;
		zbx_corr_condition_ref_t	*cond_ref;
		zbx_correlation_t		*correlation;

		if (NULL == (cond_ref = (zbx_corr_condition_ref_t *)zbx_hashset_search(&cache->corr_conditions,
				&rowid)))
		{
			continue;
		}

		if (NULL == (ref = (zbx_correlation_ref_t *)zbx_hashset_search(&cache->correlations,
				&cond_ref->correlationid)))
		{
				continue;
		}

		correlation = correlation_acquire(ref->correlation);
		correlation_remove_condition(correlation, cond_ref->condition);

		/* sort the conditions later */
		if (ZBX_CONDITION_EVAL_TYPE_AND_OR == correlation->evaltype)
			zbx_vector_correlation_ptr_append(&correlations, correlation);

		zbx_hashset_remove_direct(&cache->corr_conditions, cond_ref);

		correlation_ref_update(ref, correlation);
	}

	/* sort conditions by type */

	zbx_vector_correlation_ptr_sort(&correlations, ZBX_DEFAULT_PTR_COMPARE_FUNC);
	zbx_vector_correlation_ptr_uniq(&correlations, ZBX_DEFAULT_PTR_COMPARE_FUNC);

	for (int i = 0; i < correlations.values_num; i++)
	{
		zbx_correlation_t	*correlation = (zbx_correlation_t *)correlations.values[i];

		zbx_vector_corr_condition_ptr_sort(&correlation->conditions, compare_corr_conditions_by_type);

		if (ZBX_CONDITION_EVAL_TYPE_EXPRESSION != correlation->evaltype)
		{
			zbx_free(correlation->formula);
			correlation->formula = correlation_basic_formula(correlation);
		}
	}

	zbx_vector_correlation_ptr_destroy(&correlations);

	zbx_dcsync_sync_end(sync, dbconfig_used_size());

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	correlation_cache_sync(zbx_dbsync_t *correlation_sync, zbx_dbsync_t *corr_operation_sync,
		zbx_dbsync_t *corr_condition_sync)
{
	zbx_uint64_t	changes_num;

	correlation_cache_sync_correlations(correlation_sync);
	correlation_cache_sync_operations(corr_operation_sync);
	correlation_cache_sync_conditions(corr_condition_sync);

	changes_num = correlation_sync->add_num + correlation_sync->update_num + correlation_sync->remove_num;
	changes_num += corr_operation_sync->add_num + corr_operation_sync->update_num + corr_operation_sync->remove_num;
	changes_num += corr_condition_sync->add_num + corr_condition_sync->update_num + corr_condition_sync->remove_num;

	if (0 != changes_num)
	{
		zbx_correlation_cache_t		*cache = dc_local()->correlation_cache;
		zbx_correlation_cache_handle_t	handle = correlation_cache_handle_create();

		pthread_mutex_lock(&cache->lock);

		if (NULL != cache->handle)
			correlation_cache_handle_release(cache->handle);
		cache->handle = handle;

		pthread_mutex_unlock(&cache->lock);
	}
}

static void	correlation_conditon_dump(const zbx_corr_condition_t *cond, const char *prefix)
{
	zabbix_log(LOG_LEVEL_TRACE, "%sconditionid:" ZBX_FS_UI64 " type:%d", prefix, cond->corr_conditionid,
			cond->type);

	switch (cond->type)
	{
		case ZBX_CORR_CONDITION_OLD_EVENT_TAG:
		case ZBX_CORR_CONDITION_NEW_EVENT_TAG:
			zabbix_log(LOG_LEVEL_TRACE, "%s  tag:%s", prefix, cond->data.tag.tag);
			break;
		case ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP:
			zabbix_log(LOG_LEVEL_TRACE, "%s  hostgroup:" ZBX_FS_UI64 "op:%u", prefix,
					cond->data.group.groupid, cond->data.group.op);
			break;
		case ZBX_CORR_CONDITION_EVENT_TAG_PAIR:
			zabbix_log(LOG_LEVEL_TRACE, "%s  old:%s new:%s", prefix, cond->data.tag_pair.oldtag,
					cond->data.tag_pair.newtag);
			break;
		case ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE:
		case ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE:
			zabbix_log(LOG_LEVEL_TRACE, "%s  tag:%s value:%s op:%u", prefix, cond->data.tag_value.tag,
					cond->data.tag_value.value, cond->data.tag_value.op);
			break;
		default:
			zabbix_log(LOG_LEVEL_TRACE, "%s  unknown", prefix);
			break;
	}
}

static void	correlation_dump(const zbx_correlation_t *correlation)
{
	zabbix_log(LOG_LEVEL_TRACE, "  correlationid:" ZBX_FS_UI64 " name:%s operations:%x refcount:%u",
			correlation->correlationid, correlation->name, correlation->operations, correlation->refcount);

	zabbix_log(LOG_LEVEL_TRACE, "  conditions:");

	for (int i = 0; i < correlation->conditions.values_num; i++)
		correlation_conditon_dump(correlation->conditions.values[i], "    ");
}

void	correlation_cache_dump(void)
{
	zbx_hashset_iter_t	iter;
	zbx_correlation_ref_t	*ref;
	zbx_correlation_cache_t	*cache = dc_local()->correlation_cache;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	pthread_mutex_lock(&cache->lock);

	zbx_hashset_iter_reset(&cache->correlations, &iter);
	while (NULL != (ref = (zbx_correlation_ref_t *)zbx_hashset_iter_next(&iter)))
	{
		correlation_dump(ref->correlation);
	}

	pthread_mutex_unlock(&cache->lock);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

zbx_correlation_cache_handle_t	zbx_correlation_cache_open(void)
{
	zbx_correlation_cache_handle_t	handle;
	zbx_correlation_cache_t		*cache = dc_local()->correlation_cache;

	if (0 == atomic_load(&cache->correlations_num))
		return NULL;

	pthread_mutex_lock(&cache->lock);
	handle = cache->handle;
	atomic_fetch_add(&handle->refcount, 1);

	pthread_mutex_unlock(&cache->lock);

	return handle;
}

void	zbx_correlation_cache_close(zbx_correlation_cache_handle_t handle)
{
	correlation_cache_handle_release(handle);
}

zbx_vector_correlation_ptr_t	*zbx_correlation_cache_get_correlations(zbx_correlation_cache_handle_t handle)
{
	return &handle->correlations;
}
