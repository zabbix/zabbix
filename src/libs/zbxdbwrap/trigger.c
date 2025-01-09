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

#include "zbxdbwrap.h"

#include "zbxdbhigh.h"
#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxeval.h"
#include "zbxexpr.h"
#include "zbxnum.h"
#include "zbxstr.h"
#include "zbxtime.h"
#include "zbxvariant.h"
#include "zbx_expression_constants.h"
#include "zbxexpression.h"

/* temporary cache of trigger related data */
typedef struct
{
	zbx_uint32_t		init;
	zbx_uint32_t		done;
	zbx_eval_context_t	eval_ctx;
	zbx_eval_context_t	eval_ctx_r;
	zbx_vector_uint64_t	hostids;
}
zbx_trigger_cache_t;

/* related trigger data caching states */
typedef enum
{
	ZBX_TRIGGER_CACHE_EVAL_CTX,
	ZBX_TRIGGER_CACHE_EVAL_CTX_R,
	ZBX_TRIGGER_CACHE_EVAL_CTX_MACROS,
	ZBX_TRIGGER_CACHE_EVAL_CTX_R_MACROS,
	ZBX_TRIGGER_CACHE_HOSTIDS,
}
zbx_trigger_cache_state_t;

static int	db_trigger_expand_macros(const zbx_db_trigger *trigger, zbx_eval_context_t *ctx);

/******************************************************************************
 *                                                                            *
 * Purpose: get trigger cache with the requested data cached                  *
 *                                                                            *
 * Parameters: trigger - [IN] the trigger                                     *
 *             state   - [IN] the required cache state                        *
 *                                                                            *
 ******************************************************************************/
static zbx_trigger_cache_t	*db_trigger_get_cache(const zbx_db_trigger *trigger, zbx_trigger_cache_state_t state)
{
	zbx_trigger_cache_t	*cache;
	char			*error = NULL;
	zbx_uint32_t		flag = 1 << state;
	zbx_vector_uint64_t	functionids;

	if (NULL == trigger->cache)
	{
		cache = (zbx_trigger_cache_t *)zbx_malloc(NULL, sizeof(zbx_trigger_cache_t));
		cache->init = cache->done = 0;
		((zbx_db_trigger *)trigger)->cache = cache;
	}
	else
		cache = (zbx_trigger_cache_t *)trigger->cache;

	if (0 != (cache->init & flag))
		return 0 != (cache->done & flag) ? cache : NULL;

	cache->init |= flag;

	switch (state)
	{
		case ZBX_TRIGGER_CACHE_EVAL_CTX:
			if ('\0' == *trigger->expression)
				return NULL;

			if (FAIL == zbx_eval_parse_expression(&cache->eval_ctx, trigger->expression,
					ZBX_EVAL_TRIGGER_EXPRESSION, &error))
			{
				zbx_free(error);
				return NULL;
			}
			break;
		case ZBX_TRIGGER_CACHE_EVAL_CTX_R:
			if ('\0' == *trigger->recovery_expression)
				return NULL;

			if (FAIL == zbx_eval_parse_expression(&cache->eval_ctx_r, trigger->recovery_expression,
					ZBX_EVAL_TRIGGER_EXPRESSION, &error))
			{
				zbx_free(error);
				return NULL;
			}
			break;
		case ZBX_TRIGGER_CACHE_EVAL_CTX_MACROS:
			if (FAIL == db_trigger_expand_macros(trigger, &cache->eval_ctx))
				return NULL;

			break;
		case ZBX_TRIGGER_CACHE_EVAL_CTX_R_MACROS:
			if (FAIL == db_trigger_expand_macros(trigger, &cache->eval_ctx_r))
				return NULL;

			break;
		case ZBX_TRIGGER_CACHE_HOSTIDS:
			zbx_vector_uint64_create(&cache->hostids);
			zbx_vector_uint64_create(&functionids);
			zbx_db_trigger_get_all_functionids(trigger, &functionids);
			zbx_dc_get_hostids_by_functionids(&functionids, &cache->hostids);
			zbx_vector_uint64_destroy(&functionids);
			break;
		default:
			return NULL;
	}

	cache->done |= flag;

	return cache;
}

/**********************************************************************************************
 *                                                                                            *
 * Purpose: resolves user macros and macro functions in trigger and recovery expressions      *
 *                                                                                            *
 * Parameters: token_type - [IN]                                                              *
 *             value      - [IN/OUT] value to be replaces with                                *
 *             error      - [OUT] pointer to error message                                    *
 *             args       - [IN] list of variadic parameters                                  *
 *                               Mandatory content:                                           *
 *                                - zbx_dc_um_handle_t *um_handle: handle to user macro cache *
 *                                - uint64_t *hostids: pointer to array of host ids           *
 *                                - int hostids_num: count of host ids in array               *
 *                                                                                            *
 * Return value: SUCCEED - macros were resolved successfully                                  *
 *               FAIL    - error parameter was given and at least one of                      *
 *                         macros was not resolved                                            *
 *                                                                                            *
 **********************************************************************************************/
int	zbx_db_trigger_recovery_user_and_func_macro_eval_resolv(zbx_token_type_t token_type, char **value,
		char **error, va_list args)
{
	/* Passed arguments */
	const zbx_dc_um_handle_t	*um_handle = va_arg(args, const zbx_dc_um_handle_t *);
	const uint64_t			*hostids = va_arg(args, const uint64_t *);
	const int			hostids_num = va_arg(args, const int);

	int	ret = SUCCEED;

	switch (token_type)
	{
		case ZBX_EVAL_TOKEN_VAR_STR:
		case ZBX_EVAL_TOKEN_VAR_USERMACRO:
		case ZBX_EVAL_TOKEN_VAR_NUM:
		case ZBX_EVAL_TOKEN_ARG_PERIOD:
			ret = zbx_dc_expand_user_and_func_macros(um_handle, value, hostids, hostids_num, error);
			break;
		case ZBX_EVAL_TOKEN_ARG_QUERY:
			/* parsing /host/item which support {HOST.HOST} and {ITEM.NAME} */
			ret = zbx_eval_query_subtitute_user_macros(*value, strlen(*value), value, error,
					zbx_db_trigger_recovery_user_and_func_macro_eval_resolv, um_handle, hostids,
					hostids_num);
			break;
		default:
			break;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: resolves {FUNCTION.VALUE<1-9>} and {FUNCTION.RECOVERY.VALUE<1-9>} *
 *          macros in expression macros {?EXPRESSION}                         *
 *                                                                            *
 * Parameters: p          - [IN] macro resolver data structure                *
 *             args       - [IN] list of variadic parameters                  *
 *                               Mandatory content:                           *
 *                                - zbx_db_event *event: trigger event        *
 *             replace_to - [OUT] pointer to value to replace macro with      *
 *             data       - [IN/OUT] pointer to input data string             *
 *             error      - [OUT] pointer to pre-allocated error message      *
 *                                buffer                                      *
 *             maxerrlen  - [IN] size of error message buffer                 *
 *                                                                            *
 ******************************************************************************/
static int	function_recovery_value_resolv(zbx_macro_resolv_data_t *p, va_list args, char **replace_to,
		char **data, char *error, size_t maxerrlen)
{
	/* Passed arguments */
	const zbx_db_event	*event = va_arg(args, const zbx_db_event *);

	ZBX_UNUSED(data);
	ZBX_UNUSED(error);
	ZBX_UNUSED(maxerrlen);

	if (0 == strcmp(p->macro, MVAR_FUNCTION_VALUE))
	{
		zbx_db_trigger_get_function_value(&event->trigger, p->index, replace_to, zbx_evaluate_function, 0);
	}
	else if (0 == strcmp(p->macro, MVAR_FUNCTION_RECOVERY_VALUE))
	{
		zbx_db_trigger_get_function_value(&event->trigger, p->index, replace_to, zbx_evaluate_function, 1);
	}

	return SUCCEED;
}

/**********************************************************************************************
 *                                                                                            *
 * Purpose: resolved expression tokens which can point to trigger or recovery expressions     *
 *                                                                                            *
 * Parameters: token_type - [IN]                                                              *
 *             value      - [IN/OUT] value to be replaces with                                *
 *             error      - [OUT] pointer to error message                                    *
 *             args       - [IN] list of variadic parameters                                  *
 *                               Mandatory content:                                           *
 *                                - zbx_dc_um_handle_t *um_handle: handle to user macro cache *
 *                                - uint64_t *hostids: pointer to array of host ids           *
 *                                - int hostids_num: count of host ids in array               *
 *                                - zbx_db_event *event: trigger event                        *
 *                                                                                            *
 * Return value: SUCCEED - macros were resolved successfully                                  *
 *               FAIL    - error parameter was given and at least one of                      *
 *                         macros was not resolved                                            *
 *                                                                                            *
 **********************************************************************************************/
int	zbx_db_trigger_supplement_eval_resolv(zbx_token_type_t token_type, char **value, char **error, va_list args)
{
	/* Passed arguments */
	const zbx_dc_um_handle_t	*um_handle = va_arg(args, const zbx_dc_um_handle_t *);
	const uint64_t			*hostids = va_arg(args, const uint64_t *);
	const int			hostids_num = va_arg(args, const int);
	const zbx_db_event		*event = va_arg(args, const zbx_db_event *);

	int				ret = SUCCEED;

	switch (token_type)
	{
		case ZBX_EVAL_TOKEN_VAR_MACRO:
			/* for {FUNCTION.VALUE<1-9>} and {FUNCTION.RECOVERY.VALUE<1-9>} */
			ret = zbx_substitute_macros(value, NULL, 0, &function_recovery_value_resolv, event);
			break;
		case ZBX_EVAL_TOKEN_VAR_STR:
		case ZBX_EVAL_TOKEN_VAR_USERMACRO:
		case ZBX_EVAL_TOKEN_VAR_NUM:
		case ZBX_EVAL_TOKEN_ARG_PERIOD:
			ret = zbx_dc_expand_user_and_func_macros(um_handle, value, hostids, hostids_num, error);
			break;
		case ZBX_EVAL_TOKEN_ARG_QUERY:
			/* parsing /host/item which support {HOST.HOST} and {ITEM.NAME} */
			ret = zbx_eval_query_subtitute_user_macros(*value, strlen(*value), value, error,
					zbx_db_trigger_recovery_user_and_func_macro_eval_resolv, um_handle, hostids,
					hostids_num);
			break;
		default:
			break;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: expand macros in trigger expression/recovery expression           *
 *                                                                            *
 ******************************************************************************/
static int	db_trigger_expand_macros(const zbx_db_trigger *trigger, zbx_eval_context_t *ctx)
{
	int 			i;
	zbx_db_event		db_event;
	zbx_dc_um_handle_t	*um_handle;
	zbx_trigger_cache_t	*cache;

	if (NULL == (cache = db_trigger_get_cache(trigger, ZBX_TRIGGER_CACHE_HOSTIDS)))
		return FAIL;

	db_event.value = trigger->value;
	db_event.object = EVENT_OBJECT_TRIGGER;

	um_handle = zbx_dc_open_user_macros();

	zbx_eval_substitute_macros(ctx, NULL, zbx_db_trigger_recovery_user_and_func_macro_eval_resolv, um_handle,
			cache->hostids.values, cache->hostids.values_num);

	zbx_dc_close_user_macros(um_handle);

	for (i = 0; i < ctx->stack.values_num; i++)
	{
		char			*value;
		zbx_eval_token_t	*token = &ctx->stack.values[i];

		switch (token->type)
		{
			case ZBX_EVAL_TOKEN_VAR_STR:
				if (ZBX_VARIANT_NONE != token->value.type)
				{
					if (FAIL == zbx_variant_convert(&token->value, ZBX_VARIANT_STR))
					{
						zabbix_log(LOG_LEVEL_CRIT, "cannot convert value from %s to %s",
								zbx_variant_type_desc(&token->value),
								zbx_get_variant_type_desc(ZBX_VARIANT_STR));

						THIS_SHOULD_NEVER_HAPPEN;
						return FAIL;
					}
					value = token->value.data.str;
					zbx_variant_set_none(&token->value);
					break;
				}
				value = zbx_substr_unquote(ctx->expression, token->loc.l, token->loc.r);
				break;
			case ZBX_EVAL_TOKEN_VAR_MACRO:
				value = zbx_substr_unquote(ctx->expression, token->loc.l, token->loc.r);
				break;
			default:
				continue;
		}

		if (SUCCEED == zbx_substitute_macros(&value, NULL, 0, zbx_macro_event_trigger_expr_resolv, &db_event))
		{
			zbx_variant_clear(&token->value);
			zbx_variant_set_str(&token->value, value);
		}
		else
			zbx_free(value);
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: free trigger cache                                                *
 *                                                                            *
 * Parameters: cache - [IN] the trigger cache                                 *
 *                                                                            *
 ******************************************************************************/
static void	trigger_cache_free(zbx_trigger_cache_t *cache)
{
	if (0 != (cache->done & (1 << ZBX_TRIGGER_CACHE_EVAL_CTX)))
		zbx_eval_clear(&cache->eval_ctx);

	if (0 != (cache->done & (1 << ZBX_TRIGGER_CACHE_EVAL_CTX_R)))
		zbx_eval_clear(&cache->eval_ctx_r);

	if (0 != (cache->done & (1 << ZBX_TRIGGER_CACHE_HOSTIDS)))
		zbx_vector_uint64_destroy(&cache->hostids);

	zbx_free(cache);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get functionids from trigger expression and recovery expression   *
 *                                                                            *
 * Parameters: trigger     - [IN] the trigger                                 *
 *             functionids - [OUT] the extracted functionids                  *
 *                                                                            *
 * Comments: This function will cache parsed expressions in the trigger.      *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_trigger_get_all_functionids(const zbx_db_trigger *trigger, zbx_vector_uint64_t *functionids)
{
	zbx_trigger_cache_t	*cache;

	if (NULL != (cache = db_trigger_get_cache(trigger, ZBX_TRIGGER_CACHE_EVAL_CTX)))
		zbx_eval_get_functionids(&cache->eval_ctx, functionids);

	if (NULL != (cache = db_trigger_get_cache(trigger, ZBX_TRIGGER_CACHE_EVAL_CTX_R)))
		zbx_eval_get_functionids(&cache->eval_ctx_r, functionids);

	zbx_vector_uint64_sort(functionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(functionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get functionids from trigger expression                           *
 *                                                                            *
 * Parameters: trigger     - [IN] the trigger                                 *
 *             functionids - [OUT] the extracted functionids                  *
 *                                                                            *
 * Comments: This function will cache parsed expressions in the trigger.      *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_trigger_get_functionids(const zbx_db_trigger *trigger, zbx_vector_uint64_t *functionids)
{
	zbx_trigger_cache_t	*cache;

	if (NULL != (cache = db_trigger_get_cache(trigger, ZBX_TRIGGER_CACHE_EVAL_CTX)))
		zbx_eval_get_functionids(&cache->eval_ctx, functionids);

	zbx_vector_uint64_sort(functionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(functionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}
/******************************************************************************
 *                                                                            *
 * Purpose: get trigger expression constant at the specified location         *
 *                                                                            *
 * Parameters: trigger - [IN] the trigger                                     *
 *             index   - [IN] the constant index, starting with 1             *
 *             out     - [IN] the constant value, if exists                   *
 *                                                                            *
 * Return value: SUCCEED - the expression was parsed and constant extracted   *
 *                         (if the index was valid)                           *
 *               FAIL    - the expression failed to parse                     *
 *                                                                            *
 * Comments: This function will cache parsed expressions in the trigger.      *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_trigger_get_constant(const zbx_db_trigger *trigger, int index, char **out)
{
	zbx_trigger_cache_t	*cache;

	if (NULL == (cache = db_trigger_get_cache(trigger, ZBX_TRIGGER_CACHE_EVAL_CTX_MACROS)))
		return FAIL;

	zbx_eval_get_constant(&cache->eval_ctx, index, out);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get the Nth function item from trigger expression                 *
 *                                                                            *
 * Parameters: trigger - [IN] the trigger                                     *
 *             index   - [IN] the function index                              *
 *             itemid  - [IN] the function itemid                             *
 *                                                                            *
 * Comments: SUCCEED - the itemid was extracted successfully                  *
 *           FAIL    - otherwise                                              *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_trigger_get_itemid(const zbx_db_trigger *trigger, int index, zbx_uint64_t *itemid)
{
	int			i, ret = FAIL;
	zbx_trigger_cache_t	*cache;

	if (NULL == (cache = db_trigger_get_cache(trigger, ZBX_TRIGGER_CACHE_EVAL_CTX)))
		return FAIL;

	for (i = 0; i < cache->eval_ctx.stack.values_num; i++)
	{
		zbx_eval_token_t	*token = &cache->eval_ctx.stack.values[i];
		zbx_uint64_t		functionid;
		zbx_dc_function_t	function;
		int			errcode;

		if (ZBX_EVAL_TOKEN_FUNCTIONID != token->type || (int)token->opt + 1 != index)
			continue;

		switch (token->value.type)
		{
			case ZBX_VARIANT_UI64:
				functionid = token->value.data.ui64;
				break;
			case ZBX_VARIANT_NONE:
				if (SUCCEED != zbx_is_uint64_n(cache->eval_ctx.expression + token->loc.l + 1,
						token->loc.r - token->loc.l - 1, &functionid))
				{
					return FAIL;
				}
				zbx_variant_set_ui64(&token->value, functionid);
				break;
			default:
				return FAIL;
		}

		zbx_dc_config_get_functions_by_functionids(&function, &functionid, &errcode, 1);

		if (SUCCEED == errcode)
		{
			*itemid = function.itemid;
			ret = SUCCEED;
		}

		zbx_dc_config_clean_functions(&function, &errcode, 1);
		break;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get unique itemids of trigger functions in the order they are     *
 *          written in expression                                             *
 *                                                                            *
 * Parameters: trigger - [IN] the trigger                                     *
 *             itemids - [IN] the function itemids                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_trigger_get_itemids(const zbx_db_trigger *trigger, zbx_vector_uint64_t *itemids)
{
	zbx_vector_uint64_t	functionids, functionids_ordered;
	zbx_trigger_cache_t	*cache;

	if (NULL == (cache = db_trigger_get_cache(trigger, ZBX_TRIGGER_CACHE_EVAL_CTX)))
		return;

	zbx_vector_uint64_create(&functionids);
	zbx_vector_uint64_create(&functionids_ordered);

	zbx_eval_get_functionids_ordered(&cache->eval_ctx, &functionids_ordered);

	if (0 != functionids_ordered.values_num)
	{
		zbx_dc_function_t	*functions;
		int			i, *errcodes, index;

		zbx_vector_uint64_append_array(&functionids, functionids_ordered.values,
				functionids_ordered.values_num);

		zbx_vector_uint64_sort(&functionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&functionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		functions = (zbx_dc_function_t *)zbx_malloc(NULL, sizeof(zbx_dc_function_t) * functionids.values_num);
		errcodes = (int *)zbx_malloc(NULL, sizeof(int) * functionids.values_num);

		zbx_dc_config_get_functions_by_functionids(functions, functionids.values, errcodes,
				functionids.values_num);

		for (i = 0; i < functionids_ordered.values_num; i++)
		{
			if (-1 == (index = zbx_vector_uint64_bsearch(&functionids, functionids_ordered.values[i],
					ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			if (SUCCEED != errcodes[index])
				continue;

			if (FAIL == zbx_vector_uint64_search(itemids, functions[index].itemid,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			{
				zbx_vector_uint64_append(itemids, functions[index].itemid);
			}
		}

		zbx_dc_config_clean_functions(functions, errcodes, functionids.values_num);
		zbx_free(functions);
		zbx_free(errcodes);
	}

	zbx_vector_uint64_destroy(&functionids_ordered);
	zbx_vector_uint64_destroy(&functionids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get hostids from trigger expression and recovery expression       *
 *                                                                            *
 * Parameters: trigger - [IN] the trigger                                     *
 *             hostids - [OUT] the extracted hostids                          *
 *                                                                            *
 * Return value: SUCCEED - the hostids vector was returned (but can be empty  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: This function will cache parsed expressions in the trigger.      *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_trigger_get_all_hostids(const zbx_db_trigger *trigger, const zbx_vector_uint64_t **hostids)
{
	zbx_trigger_cache_t	*cache;

	if (NULL == (cache = db_trigger_get_cache(trigger, ZBX_TRIGGER_CACHE_HOSTIDS)))
		return FAIL;

	*hostids = &cache->hostids;
	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: frees resources allocated to store trigger data                   *
 *                                                                            *
 * Parameters: trigger -                                                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_trigger_clean(zbx_db_trigger *trigger)
{
	zbx_free(trigger->description);
	zbx_free(trigger->expression);
	zbx_free(trigger->recovery_expression);
	zbx_free(trigger->comments);
	zbx_free(trigger->url);
	zbx_free(trigger->url_name);
	zbx_free(trigger->opdata);
	zbx_free(trigger->event_name);

	if (NULL != trigger->cache)
		trigger_cache_free((zbx_trigger_cache_t *)trigger->cache);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get original trigger expression/recovery expression with expanded *
 *          functions                                                         *
 *                                                                            *
 * Parameters: ctx        - [IN] the parsed expression                        *
 *             expression - [OUT] the trigger expression                      *
 *                                                                            *
 ******************************************************************************/
static void	db_trigger_get_expression(const zbx_eval_context_t *ctx, char **expression)
{
	int			i;
	zbx_eval_context_t	local_ctx;

	zbx_eval_copy(&local_ctx, ctx, ctx->expression);
	local_ctx.rules |= ZBX_EVAL_COMPOSE_MASK_ERROR;

	for (i = 0; i < local_ctx.stack.values_num; i++)
	{
		zbx_eval_token_t	*token = &local_ctx.stack.values[i];
		zbx_uint64_t		functionid;
		zbx_dc_function_t	function;
		zbx_dc_item_t		item;
		int			err_func, err_item;

		if (ZBX_EVAL_TOKEN_FUNCTIONID != token->type)
		{
			/* reset cached token values to get the original expression */
			zbx_variant_clear(&token->value);
			continue;
		}

		switch (token->value.type)
		{
			case ZBX_VARIANT_UI64:
				functionid = token->value.data.ui64;
				break;
			case ZBX_VARIANT_NONE:
				if (SUCCEED != zbx_is_uint64_n(local_ctx.expression + token->loc.l + 1,
						token->loc.r - token->loc.l - 1, &functionid))
				{
					continue;
				}
				break;
			default:
				continue;
		}

		zbx_dc_config_get_functions_by_functionids(&function, &functionid, &err_func, 1);

		if (SUCCEED == err_func)
		{
			zbx_dc_config_get_items_by_itemids(&item, &function.itemid, &err_item, 1);

			if (SUCCEED == err_item)
			{
				char	*func = NULL;
				size_t	func_alloc = 0, func_offset = 0;

				zbx_snprintf_alloc(&func, &func_alloc, &func_offset, "%s(/%s/%s",
						function.function, item.host.host, item.key_orig);

				if ('\0' != *function.parameter)
					zbx_snprintf_alloc(&func, &func_alloc, &func_offset, ",%s", function.parameter);

				zbx_chrcpy_alloc(&func, &func_alloc, &func_offset,')');

				zbx_variant_clear(&token->value);
				zbx_variant_set_str(&token->value, func);
				zbx_dc_config_clean_items(&item, &err_item, 1);
			}
			else
			{
				zbx_variant_clear(&token->value);
				zbx_variant_set_error(&token->value, zbx_dsprintf(NULL, "item id:" ZBX_FS_UI64
						" deleted", function.itemid));
			}

			zbx_dc_config_clean_functions(&function, &err_func, 1);
		}
		else
		{
			zbx_variant_clear(&token->value);
			zbx_variant_set_error(&token->value, zbx_dsprintf(NULL, "function id:" ZBX_FS_UI64 " deleted",
					functionid));
		}
	}

	zbx_eval_compose_expression(&local_ctx, expression);

	zbx_eval_clear(&local_ctx);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get original trigger expression with expanded functions           *
 *                                                                            *
 * Parameters: trigger    - [IN] the trigger                                  *
 *             expression - [OUT] the trigger expression                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_trigger_get_expression(const zbx_db_trigger *trigger, char **expression)
{
	zbx_trigger_cache_t	*cache;

	if (NULL == (cache = db_trigger_get_cache(trigger, ZBX_TRIGGER_CACHE_EVAL_CTX)))
		*expression = zbx_strdup(NULL, trigger->expression);
	else
		db_trigger_get_expression(&cache->eval_ctx, expression);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get original trigger recovery expression with expanded functions  *
 *                                                                            *
 * Parameters: trigger    - [IN] the trigger                                  *
 *             expression - [OUT] the trigger expression                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_trigger_get_recovery_expression(const zbx_db_trigger *trigger, char **expression)
{
	zbx_trigger_cache_t	*cache;

	if (NULL == (cache = db_trigger_get_cache(trigger, ZBX_TRIGGER_CACHE_EVAL_CTX_R)))
		*expression = zbx_strdup(NULL, trigger->recovery_expression);
	else
		db_trigger_get_expression(&cache->eval_ctx_r, expression);
}

static void	evaluate_function_by_id(zbx_uint64_t functionid, char **value, zbx_trigger_func_t eval_func_cb)
{
	zbx_dc_item_t		item;
	zbx_dc_function_t	function;
	int			err_func, err_item;

	zbx_dc_config_get_functions_by_functionids(&function, &functionid, &err_func, 1);

	if (SUCCEED == err_func)
	{
		zbx_dc_config_get_items_by_itemids(&item, &function.itemid, &err_item, 1);

		if (SUCCEED == err_item)
		{
			char			*error = NULL, *parameter = NULL;
			zbx_variant_t		var;
			zbx_timespec_t		ts;
			zbx_dc_evaluate_item_t	evaluate_item;

			parameter = zbx_dc_expand_user_macros_in_func_params(function.parameter, item.host.hostid);
			zbx_timespec(&ts);

			evaluate_item.itemid = item.itemid;
			evaluate_item.value_type = item.value_type;
			evaluate_item.proxyid = item.host.proxyid;
			evaluate_item.host = item.host.host;
			evaluate_item.key_orig = item.key_orig;

			if (SUCCEED == eval_func_cb(&var, &evaluate_item, function.function, parameter, &ts, &error) &&
					ZBX_VARIANT_NONE != var.type)
			{
				*value = zbx_strdup(NULL, zbx_variant_value_desc(&var));
				zbx_variant_clear(&var);
			}
			else
				zbx_free(error);

			zbx_free(parameter);
			zbx_dc_config_clean_items(&item, &err_item, 1);
		}

		zbx_dc_config_clean_functions(&function, &err_func, 1);
	}

	if (NULL == *value)
		*value = zbx_strdup(NULL, STR_UNKNOWN_VARIABLE);
}

static void	db_trigger_explain_expression(const zbx_eval_context_t *ctx, char **expression,
		zbx_trigger_func_t eval_func_cb)
{
	int			i;
	zbx_eval_context_t	local_ctx;

	zbx_eval_copy(&local_ctx, ctx, ctx->expression);
	local_ctx.rules |= ZBX_EVAL_COMPOSE_MASK_ERROR;

	for (i = 0; i < local_ctx.stack.values_num; i++)
	{
		zbx_eval_token_t	*token = &local_ctx.stack.values[i];
		char			*value = NULL;
		zbx_uint64_t		functionid;

		if (ZBX_EVAL_TOKEN_FUNCTIONID != token->type)
			continue;

		switch (token->value.type)
		{
			case ZBX_VARIANT_UI64:
				functionid = token->value.data.ui64;
				break;
			case ZBX_VARIANT_NONE:
				if (SUCCEED != zbx_is_uint64_n(local_ctx.expression + token->loc.l + 1,
						token->loc.r - token->loc.l - 1, &functionid))
				{
					continue;
				}
				break;
			default:
				continue;
		}

		zbx_variant_clear(&token->value);
		evaluate_function_by_id(functionid, &value, eval_func_cb);
		zbx_variant_set_str(&token->value, value);
	}

	zbx_eval_compose_expression(&local_ctx, expression);

	zbx_eval_clear(&local_ctx);
}

static void	db_trigger_get_function_value(const zbx_eval_context_t *ctx, int index, char **value_ret,
		zbx_trigger_func_t eval_func_cb)
{
	int			i;
	zbx_eval_context_t	local_ctx;

	zbx_eval_copy(&local_ctx, ctx, ctx->expression);

	for (i = 0; i < local_ctx.stack.values_num; i++)
	{
		zbx_eval_token_t	*token = &local_ctx.stack.values[i];
		zbx_uint64_t		functionid;

		if (ZBX_EVAL_TOKEN_FUNCTIONID != token->type || (int)token->opt + 1 != index)
			continue;

		switch (token->value.type)
		{
			case ZBX_VARIANT_UI64:
				functionid = token->value.data.ui64;
				break;
			case ZBX_VARIANT_NONE:
				if (SUCCEED != zbx_is_uint64_n(local_ctx.expression + token->loc.l + 1,
						token->loc.r - token->loc.l - 1, &functionid))
				{
					continue;
				}
				break;
			default:
				continue;
		}

		evaluate_function_by_id(functionid, value_ret, eval_func_cb);
		break;
	}

	zbx_eval_clear(&local_ctx);

	if (NULL == *value_ret)
		*value_ret = zbx_strdup(NULL, STR_UNKNOWN_VARIABLE);
}

void	zbx_db_trigger_explain_expression(const zbx_db_trigger *trigger, char **expression,
		zbx_trigger_func_t eval_func_cb, int recovery)
{
	zbx_trigger_cache_t		*cache;
	zbx_trigger_cache_state_t	state;
	const zbx_eval_context_t	*ctx;

	state = (1 == recovery) ? ZBX_TRIGGER_CACHE_EVAL_CTX_R_MACROS : ZBX_TRIGGER_CACHE_EVAL_CTX_MACROS;

	if (NULL == (cache = db_trigger_get_cache(trigger, state)))
	{
		*expression = zbx_strdup(NULL, STR_UNKNOWN_VARIABLE);
		return;
	}

	ctx = (1 == recovery) ? &cache->eval_ctx_r : &cache->eval_ctx;

	db_trigger_explain_expression(ctx, expression, eval_func_cb);
}

void	zbx_db_trigger_get_function_value(const zbx_db_trigger *trigger, int index, char **value,
		zbx_trigger_func_t eval_func_cb, int recovery)
{
	zbx_trigger_cache_t		*cache;
	zbx_trigger_cache_state_t	state;
	const zbx_eval_context_t	*ctx;

	state = (1 == recovery) ? ZBX_TRIGGER_CACHE_EVAL_CTX_R : ZBX_TRIGGER_CACHE_EVAL_CTX;

	if (NULL == (cache = db_trigger_get_cache(trigger, state)))
	{
		*value = zbx_strdup(NULL, STR_UNKNOWN_VARIABLE);
		return;
	}

	ctx = (1 == recovery) ? &cache->eval_ctx_r : &cache->eval_ctx;

	db_trigger_get_function_value(ctx, index, value, eval_func_cb);
}
