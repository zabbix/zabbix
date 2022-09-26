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

#include "zbxdbwrap.h"

#include "log.h"
#include "dbcache.h"
#include "events.h"
#include "zbxserver.h"
#include "zbxnum.h"

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
 * Purpose: get functionids from trigger expression                           *
 *                                                                            *
 * Parameters: trigger     - [IN] the trigger                                 *
 *             functionids - [OUT] the extracted functionids                  *
 *                                                                            *
 * Comments: This function will cache parsed expressions in the trigger.      *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_trigger_get_functionids(const ZBX_DB_TRIGGER *trigger, zbx_vector_uint64_t *functionids)
{
	zbx_trigger_cache_t	*cache;

	if (NULL != (cache = db_trigger_get_cache(trigger, ZBX_TRIGGER_CACHE_EVAL_CTX)))
		zbx_eval_get_functionids(&cache->eval_ctx, functionids);

	zbx_vector_uint64_sort(functionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(functionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
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
int	zbx_db_trigger_get_itemid(const ZBX_DB_TRIGGER *trigger, int index, zbx_uint64_t *itemid)
{
	int			i, ret = FAIL;
	zbx_trigger_cache_t	*cache;

	if (NULL == (cache = db_trigger_get_cache(trigger, ZBX_TRIGGER_CACHE_EVAL_CTX)))
		return FAIL;

	for (i = 0; i < cache->eval_ctx.stack.values_num; i++)
	{
		zbx_eval_token_t	*token = &cache->eval_ctx.stack.values[i];
		zbx_uint64_t		functionid;
		DC_FUNCTION		function;
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

		DCconfig_get_functions_by_functionids(&function, &functionid, &errcode, 1);

		if (SUCCEED == errcode)
		{
			*itemid = function.itemid;
			ret = SUCCEED;
		}

		DCconfig_clean_functions(&function, &errcode, 1);
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
void	zbx_db_trigger_get_itemids(const ZBX_DB_TRIGGER *trigger, zbx_vector_uint64_t *itemids)
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
		DC_FUNCTION	*functions;
		int		i, *errcodes, index;

		zbx_vector_uint64_append_array(&functionids, functionids_ordered.values,
				functionids_ordered.values_num);

		zbx_vector_uint64_sort(&functionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&functionids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		functions = (DC_FUNCTION *)zbx_malloc(NULL, sizeof(DC_FUNCTION) * functionids.values_num);
		errcodes = (int *)zbx_malloc(NULL, sizeof(int) * functionids.values_num);

		DCconfig_get_functions_by_functionids(functions, functionids.values, errcodes,
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

		DCconfig_clean_functions(functions, errcodes, functionids.values_num);
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
int	zbx_db_trigger_get_all_hostids(const ZBX_DB_TRIGGER *trigger, const zbx_vector_uint64_t **hostids)
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
void	zbx_db_trigger_clean(ZBX_DB_TRIGGER *trigger)
{
	zbx_free(trigger->description);
	zbx_free(trigger->expression);
	zbx_free(trigger->recovery_expression);
	zbx_free(trigger->comments);
	zbx_free(trigger->url);
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
		DC_FUNCTION		function;
		DC_ITEM			item;
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

		DCconfig_get_functions_by_functionids(&function, &functionid, &err_func, 1);

		if (SUCCEED == err_func)
		{
			DCconfig_get_items_by_itemids(&item, &function.itemid, &err_item, 1);

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
				DCconfig_clean_items(&item, &err_item, 1);
			}
			else
			{
				zbx_variant_clear(&token->value);
				zbx_variant_set_error(&token->value, zbx_dsprintf(NULL, "item id:" ZBX_FS_UI64
						" deleted", function.itemid));
			}

			DCconfig_clean_functions(&function, &err_func, 1);
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
void	zbx_db_trigger_get_expression(const ZBX_DB_TRIGGER *trigger, char **expression)
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
void	zbx_db_trigger_get_recovery_expression(const ZBX_DB_TRIGGER *trigger, char **expression)
{
	zbx_trigger_cache_t	*cache;

	if (NULL == (cache = db_trigger_get_cache(trigger, ZBX_TRIGGER_CACHE_EVAL_CTX_R)))
		*expression = zbx_strdup(NULL, trigger->recovery_expression);
	else
		db_trigger_get_expression(&cache->eval_ctx_r, expression);
}
