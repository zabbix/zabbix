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

#include "cachehistory_server.h"

#include "zbxcacheconfig.h"
#include "zbx_trigger_constants.h"
#include "zbx_item_constants.h"
#include "zbx_host_constants.h"
#include "zbxexpression.h"
#include "zbxcachevalue.h"
#include "zbxdbwrap.h"
#include "zbxvariant.h"
#include "zbxtime.h"
#include "zbxnum.h"
#include "zbxexpr.h"
#include "zbxeval.h"
#include "zbxdbhigh.h"
#include "zbxalgo.h"

static void	extract_functionids(zbx_vector_uint64_t *functionids, zbx_vector_dc_trigger_t *triggers)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tr_num:%d", __func__, triggers->values_num);

	zbx_vector_uint64_reserve(functionids, triggers->values_num);

	for (int i = 0; i < triggers->values_num; i++)
	{
		zbx_dc_trigger_t	*tr = triggers->values[i];

		if (NULL != tr->new_error)
			continue;

		zbx_eval_get_functionids(tr->eval_ctx, functionids);

		if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == tr->recovery_mode)
			zbx_eval_get_functionids(tr->eval_ctx_r, functionids);
	}

	zbx_vector_uint64_sort(functionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(functionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() functionids_num:%d", __func__, functionids->values_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: expand macros in a trigger expression.                            *
 *                                                                            *
 ******************************************************************************/
static int	expand_normal_trigger_macros(zbx_eval_context_t *ctx, const zbx_db_event *event, char *error,
		size_t maxerrlen)
{
	int	i;

	for (i = 0; i < ctx->stack.values_num; i++)
	{
		zbx_eval_token_t	*token = &ctx->stack.values[i];

		if (ZBX_EVAL_TOKEN_VAR_MACRO != token->type && ZBX_EVAL_TOKEN_VAR_STR != token->type)
		{
			continue;
		}

		/* all trigger macros are already extracted into strings */
		if (ZBX_VARIANT_STR != token->value.type)
			continue;

		if (FAIL == zbx_substitute_macros(&token->value.data.str, error, maxerrlen,
				zbx_macro_event_trigger_expr_resolv, event))
		{
			return FAIL;
		}
	}

	return SUCCEED;
}

typedef struct
{
	/* input data */
	zbx_uint64_t	itemid;
	char		*function;
	char		*parameter;
	zbx_timespec_t	timespec;
	unsigned char	type;

	/* output data */
	zbx_variant_t	value;
	char		*error;
}
zbx_func_t;

typedef struct
{
	zbx_uint64_t	functionid;
	zbx_func_t	*func;
}
zbx_ifunc_t;

static zbx_hash_t	func_hash_func(const void *data)
{
	const zbx_func_t	*func = (const zbx_func_t *)data;
	zbx_hash_t		hash;

	hash = ZBX_DEFAULT_UINT64_HASH_FUNC(&func->itemid);
	hash = ZBX_DEFAULT_STRING_HASH_ALGO(func->function, strlen(func->function), hash);
	hash = ZBX_DEFAULT_STRING_HASH_ALGO(func->parameter, strlen(func->parameter), hash);
	hash = ZBX_DEFAULT_HASH_ALGO(&func->timespec.sec, sizeof(func->timespec.sec), hash);
	hash = ZBX_DEFAULT_HASH_ALGO(&func->timespec.ns, sizeof(func->timespec.ns), hash);

	return hash;
}

static int	func_compare_func(const void *d1, const void *d2)
{
	const zbx_func_t	*func1 = (const zbx_func_t *)d1;
	const zbx_func_t	*func2 = (const zbx_func_t *)d2;
	int			ret;

	ZBX_RETURN_IF_NOT_EQUAL(func1->itemid, func2->itemid);

	if (0 != (ret = strcmp(func1->function, func2->function)))
		return ret;

	if (0 != (ret = strcmp(func1->parameter, func2->parameter)))
		return ret;

	ZBX_RETURN_IF_NOT_EQUAL(func1->timespec.sec, func2->timespec.sec);
	ZBX_RETURN_IF_NOT_EQUAL(func1->timespec.ns, func2->timespec.ns);

	return 0;
}

static void	func_clean(void *ptr)
{
	zbx_func_t	*func = (zbx_func_t *)ptr;

	zbx_free(func->function);
	zbx_free(func->parameter);
	zbx_free(func->error);

	zbx_variant_clear(&func->value);
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepare hashset of functions to evaluate.                         *
 *                                                                            *
 * Parameters: functionids - [IN] function identifiers                        *
 *             funcs       - [OUT] functions indexed by itemid, name,         *
 *                                 parameter, timestamp                       *
 *             ifuncs      - [OUT] function index by functionid               *
 *             triggers    - [IN] vector of triggers, sorted by triggerid     *
 *                                                                            *
 ******************************************************************************/
static void	populate_function_items(const zbx_vector_uint64_t *functionids, zbx_hashset_t *funcs,
		zbx_hashset_t *ifuncs, const zbx_vector_dc_trigger_t *triggers)
{
	int			i, j;
	zbx_dc_trigger_t	*tr;
	zbx_dc_function_t	*functions = NULL;
	int			*errcodes = NULL;
	zbx_ifunc_t		ifunc_local;
	zbx_func_t		*func, func_local;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() functionids_num:%d", __func__, functionids->values_num);

	zbx_variant_set_none(&func_local.value);
	func_local.error = NULL;

	functions = (zbx_dc_function_t *)zbx_malloc(functions, sizeof(zbx_dc_function_t) * functionids->values_num);
	errcodes = (int *)zbx_malloc(errcodes, sizeof(int) * functionids->values_num);

	zbx_dc_config_history_sync_get_functions_by_functionids(functions, functionids->values, errcodes,
			(size_t)functionids->values_num);

	for (i = 0; i < functionids->values_num; i++)
	{
		if (SUCCEED != errcodes[i])
			continue;

		func_local.itemid = functions[i].itemid;

		if (FAIL != (j = zbx_vector_ptr_bsearch((const zbx_vector_ptr_t *)triggers, &functions[i].triggerid,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			tr = triggers->values[j];
			func_local.timespec = tr->timespec;
		}
		else
		{
			func_local.timespec.sec = 0;
			func_local.timespec.ns = 0;
		}

		func_local.function = functions[i].function;
		func_local.parameter = functions[i].parameter;

		if (NULL == (func = (zbx_func_t *)zbx_hashset_search(funcs, &func_local)))
		{
			func = (zbx_func_t *)zbx_hashset_insert(funcs, &func_local, sizeof(func_local));
			func->function = zbx_strdup(NULL, func_local.function);
			func->parameter = zbx_strdup(NULL, func_local.parameter);
			func->type = functions[i].type;
			zbx_variant_set_none(&func->value);
		}

		ifunc_local.functionid = functions[i].functionid;
		ifunc_local.func = func;
		zbx_hashset_insert(ifuncs, &ifunc_local, sizeof(ifunc_local));
	}

	zbx_dc_config_clean_functions(functions, errcodes, functionids->values_num);

	zbx_free(errcodes);
	zbx_free(functions);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() ifuncs_num:%d", __func__, ifuncs->num_data);
}

static void	evaluate_item_functions(zbx_hashset_t *funcs, const zbx_vector_uint64_t *history_itemids,
		const zbx_history_sync_item_t *history_items, const int *history_errcodes,
		zbx_history_sync_item_t **items, int **items_err, int *items_num)
{
	char			*error = NULL;
	int			i;
	zbx_func_t		*func;
	zbx_vector_uint64_t	itemids;
	zbx_hashset_iter_t	iter;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() funcs_num:%d", __func__, funcs->num_data);

	zbx_vector_uint64_create(&itemids);

	zbx_hashset_iter_reset(funcs, &iter);
	while (NULL != (func = (zbx_func_t *)zbx_hashset_iter_next(&iter)))
	{
		if (FAIL == zbx_vector_uint64_bsearch(history_itemids, func->itemid, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			zbx_vector_uint64_append(&itemids, func->itemid);
	}

	if (0 != itemids.values_num)
	{
		zbx_vector_uint64_sort(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		*items_num = itemids.values_num;
		*items = (zbx_history_sync_item_t *)zbx_malloc(NULL, sizeof(zbx_history_sync_item_t) *
				(size_t)itemids.values_num);
		*items_err = (int *)zbx_malloc(NULL, sizeof(int) * (size_t)itemids.values_num);

		zbx_dc_config_history_sync_get_items_by_itemids(*items, itemids.values, *items_err,
				(size_t)itemids.values_num, ZBX_ITEM_GET_SYNC);
	}

	zbx_hashset_iter_reset(funcs, &iter);
	while (NULL != (func = (zbx_func_t *)zbx_hashset_iter_next(&iter)))
	{
		int				errcode, ret;
		const zbx_history_sync_item_t	*item;
		char				*params;
		zbx_dc_evaluate_item_t		evaluate_item;

		/* avoid double copying from configuration cache if already retrieved when saving history */
		if (FAIL != (i = zbx_vector_uint64_bsearch(history_itemids, func->itemid,
				ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
		{
			item = history_items + i;
			errcode = history_errcodes[i];
		}
		else
		{
			i = zbx_vector_uint64_bsearch(&itemids, func->itemid, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
			item = *items + i;
			errcode = (*items_err)[i];
		}

		if (SUCCEED != errcode)
		{
			zbx_free(func->error);
			func->error = zbx_eval_format_function_error(func->function, NULL, NULL, func->parameter,
					"item does not exist");
			continue;
		}

		if (ITEM_VALUE_TYPE_BIN == item->value_type)
		{
			zbx_free(func->error);
			func->error = zbx_eval_format_function_error(func->function, item->host.host, item->key_orig,
					func->parameter, "binary-type items are not supported in functions");
			continue;
		}

		/* do not evaluate if the item is disabled or belongs to a disabled host */

		if (ITEM_STATUS_ACTIVE != item->status)
		{
			zbx_free(func->error);
			func->error = zbx_eval_format_function_error(func->function, item->host.host,
					item->key_orig, func->parameter, "item is disabled");
			continue;
		}

		if ((ZBX_FUNCTION_TYPE_HISTORY == func->type || ZBX_FUNCTION_TYPE_TIMER == func->type) &&
				0 == item->history)
		{
			zbx_free(func->error);
			func->error = zbx_eval_format_function_error(func->function, item->host.host,
					item->key_orig, func->parameter, "item history is disabled");
			continue;
		}

		if (ZBX_FUNCTION_TYPE_TRENDS == func->type)
		{
			if (ITEM_VALUE_TYPE_FLOAT != item->value_type && ITEM_VALUE_TYPE_UINT64 != item->value_type)
			{
				zbx_free(func->error);
				func->error = zbx_eval_format_function_error(func->function, item->host.host,
						item->key_orig, func->parameter, "trend functions are supported only "
						"for numeric types");
				continue;
			}
			else if (0 == item->trends)
			{
				zbx_free(func->error);
				func->error = zbx_eval_format_function_error(func->function, item->host.host,
						item->key_orig, func->parameter, "item trends are disabled");
				continue;
			}
		}

		if (HOST_STATUS_MONITORED != item->host.status)
		{
			zbx_free(func->error);
			func->error = zbx_eval_format_function_error(func->function, item->host.host,
					item->key_orig, func->parameter, "item belongs to a disabled host");
			continue;
		}

		if (ITEM_STATE_NOTSUPPORTED == item->state &&
				FAIL == zbx_evaluatable_for_notsupported(func->function))
		{
			/* set 'unknown' error value */
			zbx_variant_set_error(&func->value,
					zbx_eval_format_function_error(func->function, item->host.host,
							item->key_orig, func->parameter, "item is not supported"));
			continue;
		}

		params = zbx_dc_expand_user_macros_in_func_params(func->parameter, item->host.hostid);

		evaluate_item.itemid = item->itemid;
		evaluate_item.value_type = item->value_type;
		evaluate_item.proxyid = item->host.proxyid;
		evaluate_item.host = item->host.host;
		evaluate_item.key_orig = item->key_orig;

		ret = zbx_evaluate_function(&func->value, &evaluate_item, func->function, params, &func->timespec, &error);

		if (SUCCEED != ret)
		{
			/* compose and store error message for future use */
			zbx_variant_set_error(&func->value,
					zbx_eval_format_function_error(func->function, item->host.host,
							item->key_orig, params, error));
			zbx_free(error);
		}

		zbx_free(params);
	}

	zbx_vc_flush_stats();
	zbx_vector_uint64_destroy(&itemids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static int	substitute_expression_functions_results(zbx_hashset_t *ifuncs, zbx_eval_context_t *ctx, char **error)
{
	zbx_uint64_t		functionid;
	zbx_func_t		*func;
	zbx_ifunc_t		*ifunc;
	int			i;

	for (i = 0; i < ctx->stack.values_num; i++)
	{
		zbx_eval_token_t	*token = &ctx->stack.values[i];

		if (ZBX_EVAL_TOKEN_FUNCTIONID != token->type)
			continue;

		if (ZBX_VARIANT_UI64 != token->value.type)
		{
			/* functionids should be already extracted into uint64 vars */
			THIS_SHOULD_NEVER_HAPPEN;
			*error = zbx_dsprintf(*error, "Cannot parse function at: \"%s\"",
					ctx->expression + token->loc.l);
			return FAIL;
		}

		functionid = token->value.data.ui64;
		if (NULL == (ifunc = (zbx_ifunc_t *)zbx_hashset_search(ifuncs, &functionid)))
		{
			*error = zbx_dsprintf(*error, "Cannot obtain function"
					" and item for functionid: " ZBX_FS_UI64, functionid);
			return FAIL;
		}

		func = ifunc->func;

		if (NULL != func->error)
		{
			*error = zbx_strdup(*error, func->error);
			return FAIL;
		}

		if (ZBX_VARIANT_NONE == func->value.type)
		{
			*error = zbx_strdup(*error, "Unexpected error while processing a trigger expression");
			return FAIL;
		}

		zbx_variant_copy(&token->value, &func->value);
	}

	return SUCCEED;
}

static void	log_expression(const char *prefix, int index, const zbx_eval_context_t *ctx)
{
	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
	{
		char	*expression = NULL;

		zbx_eval_compose_expression(ctx, &expression);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() expression[%d]:'%s' => '%s'", prefix, index, ctx->expression,
				expression);
		zbx_free(expression);
	}
}

static void	substitute_functions_results(zbx_hashset_t *ifuncs, zbx_vector_dc_trigger_t *triggers)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() ifuncs_num:%d tr_num:%d",
			__func__, ifuncs->num_data, triggers->values_num);

	for (int i = 0; i < triggers->values_num; i++)
	{
		zbx_dc_trigger_t	*tr = triggers->values[i];

		if (NULL != tr->new_error)
			continue;

		if( SUCCEED != substitute_expression_functions_results(ifuncs, tr->eval_ctx, &tr->new_error))
		{
			tr->new_value = TRIGGER_VALUE_UNKNOWN;
			continue;
		}

		log_expression(__func__, i, tr->eval_ctx);

		if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == tr->recovery_mode)
		{
			if (SUCCEED != substitute_expression_functions_results(ifuncs, tr->eval_ctx_r, &tr->new_error))
			{
				tr->new_value = TRIGGER_VALUE_UNKNOWN;
				continue;
			}

			log_expression(__func__, i, tr->eval_ctx_r);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: substitute expression functions with their values.                *
 *                                                                            *
 * Comments: example: "({15}>10) or ({123}=1)" => "(26.416>10) or (0=1)"      *
 *                                                                            *
 ******************************************************************************/
static void	substitute_functions(zbx_vector_dc_trigger_t *triggers, const zbx_vector_uint64_t *history_itemids,
		const zbx_history_sync_item_t *history_items, const int *history_errcodes,
		zbx_history_sync_item_t **items, int **items_err, int *items_num)
{
	zbx_vector_uint64_t	functionids;
	zbx_hashset_t		ifuncs, funcs;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&functionids);
	extract_functionids(&functionids, triggers);

	if (0 == functionids.values_num)
		goto empty;

	zbx_hashset_create(&ifuncs, triggers->values_num, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_hashset_create_ext(&funcs, triggers->values_num, func_hash_func, func_compare_func, func_clean,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	populate_function_items(&functionids, &funcs, &ifuncs, triggers);

	if (0 != ifuncs.num_data)
	{
		evaluate_item_functions(&funcs, history_itemids, history_items, history_errcodes, items, items_err,
				items_num);
		substitute_functions_results(&ifuncs, triggers);
	}

	zbx_hashset_destroy(&ifuncs);
	zbx_hashset_destroy(&funcs);
empty:
	zbx_vector_uint64_destroy(&functionids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static int	evaluate_expression(zbx_eval_context_t *ctx, const zbx_timespec_t *ts, double *result,
		char **error)
{
	zbx_variant_t	 value;

	if (SUCCEED != zbx_eval_execute(ctx, ts, &value, error))
		return FAIL;

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
	{
		char	*expression = NULL;

		zbx_eval_compose_expression(ctx, &expression);
		zabbix_log(LOG_LEVEL_DEBUG, "%s(): %s => %s", __func__, expression, zbx_variant_value_desc(&value));
		zbx_free(expression);
	}

	if (SUCCEED != zbx_variant_convert(&value, ZBX_VARIANT_DBL))
	{
		*error = zbx_dsprintf(*error, "Cannot convert expression result of type \"%s\" to"
				" floating point value", zbx_variant_type_desc(&value));
		zbx_variant_clear(&value);

		return FAIL;
	}

	*result = value.data.dbl;

	return SUCCEED;
}

static int	expand_expression_macros(zbx_eval_context_t *ctx, zbx_dc_um_handle_t *um_handle,
		const zbx_db_event *db_event, const zbx_uint64_t *hostids, int hostids_num, char **error)
{
	char	err[MAX_STRING_LEN];

	if (SUCCEED != expand_normal_trigger_macros(ctx, db_event, err, sizeof(err)))
	{
		*error = zbx_strdup(NULL, err);
		return FAIL;
	}

	return zbx_eval_substitute_macros(ctx, error, zbx_db_trigger_recovery_user_and_func_macro_eval_resolv,
			um_handle, hostids, hostids_num);
}

static int	expand_trigger_macros(zbx_dc_trigger_t *tr, zbx_db_event *db_event, zbx_dc_um_handle_t *um_handle,
		const zbx_vector_uint64_t *hostids, char **error)
{
	db_event->value = tr->value;

	if (SUCCEED != expand_expression_macros(tr->eval_ctx, um_handle, db_event, hostids->values, hostids->values_num,
			error))
	{
		return FAIL;
	}

	if (TRIGGER_RECOVERY_MODE_RECOVERY_EXPRESSION == tr->recovery_mode)
	{
		return expand_expression_macros(tr->eval_ctx_r, um_handle, db_event, hostids->values,
					hostids->values_num, error);
	}

	return SUCCEED;
}

static int	dc_item_compare_by_itemid(const void *d1, const void *d2)
{
	zbx_uint64_t	itemid = *(const zbx_uint64_t *)d1;
	const zbx_history_sync_item_t	*item = (const zbx_history_sync_item_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(itemid, item->itemid);
	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: evaluate trigger expressions.                                     *
 *                                                                            *
 * Parameters: triggers - [IN] vector of zbx_dc_trigger_t pointers, sorted by *
 *                             triggerids                                     *
 *                                                                            *
 ******************************************************************************/
void	zbx_evaluate_expressions(zbx_vector_dc_trigger_t *triggers, const zbx_vector_uint64_t *history_itemids,
		const zbx_history_sync_item_t *history_items, const int *history_errcodes)
{
	zbx_db_event		event;
	zbx_dc_trigger_t	*tr;
	zbx_history_sync_item_t	*items = NULL;
	int			i, *items_err, items_num = 0;
	double			expr_result;
	zbx_dc_um_handle_t	*um_handle;
	zbx_vector_uint64_t	hostids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tr_num:%d", __func__, triggers->values_num);

	event.object = EVENT_OBJECT_TRIGGER;

	zbx_vector_uint64_create(&hostids);
	um_handle = zbx_dc_open_user_macros();

	substitute_functions(triggers, history_itemids, history_items, history_errcodes, &items, &items_err,
			&items_num);

	for (i = 0; i < triggers->values_num; i++)
	{
		char	*error = NULL;
		int	j, k;

		tr = triggers->values[i];

		for (j = 0; j < tr->itemids.values_num; j++)
		{
			if (FAIL != (k = zbx_vector_uint64_bsearch(history_itemids, tr->itemids.values[j],
					ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				if (SUCCEED != history_errcodes[k])
					continue;

				zbx_vector_uint64_append(&hostids, history_items[k].host.hostid);
			}
			else
			{
				zbx_history_sync_item_t	*item;

				if (NULL == items)
					continue;

				item = (zbx_history_sync_item_t *)bsearch(&tr->itemids.values[j], items,
						(size_t)items_num, sizeof(zbx_history_sync_item_t),
						dc_item_compare_by_itemid);

				if (NULL == item || SUCCEED != items_err[item - items])
					continue;

				zbx_vector_uint64_append(&hostids, item->host.hostid);
			}
		}

		zbx_vector_uint64_sort(&hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&hostids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		if (SUCCEED != expand_trigger_macros(tr, &event, um_handle, &hostids, &error))
		{
			tr->new_error = zbx_dsprintf(tr->new_error, "Cannot evaluate expression: %s", error);
			tr->new_value = TRIGGER_VALUE_UNKNOWN;
			zbx_free(error);
		}

		zbx_vector_uint64_clear(&hostids);
	}

	zbx_dc_close_user_macros(um_handle);
	zbx_vector_uint64_destroy(&hostids);

	if (0 != items_num)
	{
		zbx_dc_config_clean_history_sync_items(items, items_err, (size_t)items_num);
		zbx_free(items);
		zbx_free(items_err);
	}

	/* calculate new trigger values based on their recovery modes and expression evaluations */
	for (i = 0; i < triggers->values_num; i++)
	{
		tr = triggers->values[i];

		if (NULL != tr->new_error)
			continue;

		if (SUCCEED != evaluate_expression(tr->eval_ctx, &tr->timespec, &expr_result, &tr->new_error))
			continue;

		/* trigger expression evaluates to true, set PROBLEM value */
		if (SUCCEED != zbx_double_compare(expr_result, 0.0))
		{
			if (0 == (tr->flags & ZBX_DC_TRIGGER_PROBLEM_EXPRESSION))
			{
				/* trigger value should remain unchanged and no PROBLEM events should be generated if */
				/* problem expression evaluates to true, but trigger recalculation was initiated by a */
				/* time-based function or a new value of an item in recovery expression */
				tr->new_value = TRIGGER_VALUE_NONE;
			}
			else
				tr->new_value = TRIGGER_VALUE_PROBLEM;

			continue;
		}

		/* otherwise try to recover trigger by setting OK value */
		if (TRIGGER_VALUE_PROBLEM == tr->value && TRIGGER_RECOVERY_MODE_NONE != tr->recovery_mode)
		{
			if (TRIGGER_RECOVERY_MODE_EXPRESSION == tr->recovery_mode)
			{
				tr->new_value = TRIGGER_VALUE_OK;
				continue;
			}

			/* processing recovery expression mode */
			if (SUCCEED != evaluate_expression(tr->eval_ctx_r, &tr->timespec, &expr_result, &tr->new_error))
			{
				tr->new_value = TRIGGER_VALUE_UNKNOWN;
				continue;
			}

			if (SUCCEED != zbx_double_compare(expr_result, 0.0))
			{
				tr->new_value = TRIGGER_VALUE_OK;
				continue;
			}
		}

		/* no changes, keep the old value */
		tr->new_value = TRIGGER_VALUE_NONE;
	}

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
	{
		for (i = 0; i < triggers->values_num; i++)
		{
			tr = triggers->values[i];

			if (NULL != tr->new_error)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s():expression [%s] cannot be evaluated: %s",
						__func__, tr->expression, tr->new_error);
			}
		}

		zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
	}
}
