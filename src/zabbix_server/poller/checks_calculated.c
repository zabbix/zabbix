/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

#include "checks_calculated.h"
#include "zbxserver.h"
#include "log.h"
#include "../../libs/zbxserver/evalfunc.h"

typedef struct
{
	zbx_host_key_t		*hostkeys;
	DC_ITEM			*items;
	int			*errcodes;
	int			items_num;

	zbx_vector_ptr_t	itemrefs;
}
zbx_calc_eval_t;

/******************************************************************************
 *                                                                            *
 * Function: calcitem_parse_item_query                                        *
 *                                                                            *
 * Purpose: parse item query /host/key?[filter] into host, key and filter     *
 *          components                                                        *
 *                                                                            *
 * Parameters: query - [IN] the item query                                    *
 *                                                                            *
 * Return value: The allocated item reference or NULL in the case of error.   *
 *                                                                            *
 ******************************************************************************/
static zbx_item_query_t	*calcitem_parse_item_query(const char *query)
{
	zbx_item_query_t	*ref;

	ref = (zbx_item_query_t *)zbx_malloc(NULL, sizeof(zbx_item_query_t));
	memset(ref, 0, sizeof(zbx_item_query_t));

	zbx_eval_parse_query(query, strlen(query), ref);

	return ref;
}

/******************************************************************************
 *                                                                            *
 * Function: calc_eval_init                                                   *
 *                                                                            *
 * Purpose: initialize calculated item evaluation data                        *
 *                                                                            *
 * Parameters: eval     - [IN] the evaluation data                            *
 *             dc_item  - [IN] the calculated item                            *
 *             ctx      - [IN] parsed calculated item formula                 *
 *                                                                            *
 ******************************************************************************/
static void	calc_eval_init(zbx_calc_eval_t *eval, DC_ITEM *dc_item, zbx_eval_context_t *ctx)
{
	int			i;
	zbx_item_query_t	*query;
	zbx_vector_str_t	filters;

	zbx_vector_str_create(&filters);
	zbx_eval_extract_item_refs(ctx, &filters);

	zbx_vector_ptr_create(&eval->itemrefs);
	eval->items_num = 0;

	for (i = 0; i < filters.values_num; i++)
	{
		query = calcitem_parse_item_query(filters.values[i]);
		zbx_vector_ptr_append(&eval->itemrefs, query);

		if (ZBX_ITEM_QUERY_SINGLE == query->type)
			query->index = eval->items_num++;
	}

	/* get data for functions working with single item filters */
	if (0 != eval->items_num)
	{
		eval->hostkeys = (zbx_host_key_t *)zbx_malloc(NULL, sizeof(zbx_host_key_t) * eval->items_num);
		eval->items = (DC_ITEM *)zbx_malloc(NULL, sizeof(DC_ITEM) * eval->items_num);
		eval->errcodes = (int *)zbx_malloc(NULL, sizeof(int) * eval->items_num);

		for (i = 0; i < eval->itemrefs.values_num; i++)
		{
			query = (zbx_item_query_t *)eval->itemrefs.values[i];

			if (ZBX_ITEM_QUERY_SINGLE != query->type)
				continue;

			eval->hostkeys[query->index].host = (NULL == query->host ? dc_item->host.host : query->host);
			eval->hostkeys[query->index].key = query->key;
		}

		DCconfig_get_items_by_keys(eval->items, eval->hostkeys, eval->errcodes, eval->items_num);
	}

	zbx_vector_str_clear_ext(&filters, zbx_str_free);
	zbx_vector_str_destroy(&filters);
}

static void	item_query_free(zbx_item_query_t *query)
{
	zbx_eval_clear_query(query);
	zbx_free(query);
}

/******************************************************************************
 *                                                                            *
 * Function: calc_eval_clear                                                  *
 *                                                                            *
 * Purpose: free resources allocated by calculated item evaluation data       *
 *                                                                            *
 * Parameters: eval     - [IN] the evaluation data                            *
 *                                                                            *
 ******************************************************************************/
static void	calc_eval_clear(zbx_calc_eval_t *eval)
{
	if (0 != eval->items_num)
	{
		DCconfig_clean_items(eval->items, eval->errcodes, eval->items_num);
		zbx_free(eval->items);
		zbx_free(eval->errcodes);
		zbx_free(eval->hostkeys);
	}

	zbx_vector_ptr_clear_ext(&eval->itemrefs, (zbx_clean_func_t)item_query_free);
	zbx_vector_ptr_destroy(&eval->itemrefs);
}

/******************************************************************************
 *                                                                            *
 * Function: calcitem_eval_single                                             *
 *                                                                            *
 * Purpose: evaluate historical function for single item                      *
 *                                                                            *
 * Parameters: eval     - [IN] the evaluation data                            *
 *             ref      - [IN] the item reference (host, key ..)              *
 *             name     - [IN] the function name (not zero terminated)        *
 *             len      - [IN] the function name length                       *
 *             args_num - [IN] the number of function arguments               *
 *             args     - [IN] an array of the function arguments.            *
 *             data     - [IN] the caller data used for function evaluation   *
 *             ts       - [IN] the function execution time                    *
 *             value    - [OUT] the function return value                     *
 *             error    - [OUT] the error message if function failed          *
 *                                                                            *
 * Return value: SUCCEED - the function was executed successfully             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	calcitem_eval_single(zbx_calc_eval_t *eval, zbx_item_query_t *query, const char *name, size_t len,
		int args_num, const zbx_variant_t *args, const zbx_timespec_t *ts, zbx_variant_t *value, char **error)
{
	char		func_name[MAX_STRING_LEN], *params = NULL;
	size_t		params_alloc = 0, params_offset = 0;
	DC_ITEM		*item;
	int		i, ret = FAIL;

	if (SUCCEED != eval->errcodes[query->index])
	{
		*error = zbx_dsprintf(NULL, "Cannot evaluate function because item \"/%s/%s\" does not exist",
				eval->hostkeys[query->index].host, eval->hostkeys[query->index].key);
		goto out;
	}

	item = &eval->items[query->index];

	/* do not evaluate if the item is disabled or belongs to a disabled host */

	if (ITEM_STATUS_ACTIVE != item->status)
	{
		*error = zbx_dsprintf(NULL, "Cannot evaluate function with disabled item \"/%s/%s\"",
				eval->hostkeys[query->index].host, eval->hostkeys[query->index].key);
		goto out;
	}

	if (HOST_STATUS_MONITORED != item->host.status)
	{
		*error = zbx_dsprintf(NULL, "Cannot evaluate function with item \"/%s/%s\" belonging to a disabled host",
				eval->hostkeys[query->index].host, eval->hostkeys[query->index].key);
		goto out;
	}

	memcpy(func_name, name, len);
	func_name[len] = '\0';

	/* If the item is NOTSUPPORTED then evaluation is allowed for:   */
	/*   - functions white-listed in evaluatable_for_notsupported(). */
	/*     Their values can be evaluated to regular numbers even for */
	/*     NOTSUPPORTED items. */
	/*   - other functions. Result of evaluation is ZBX_UNKNOWN.     */

	if (ITEM_STATE_NOTSUPPORTED == item->state && FAIL == zbx_evaluatable_for_notsupported(func_name))
	{
		/* compose and store 'unknown' message for future use */
		*error = zbx_dsprintf(NULL,"Cannot evaluate function with not supported item \"/%s/%s\"",
				eval->hostkeys[query->index].host, eval->hostkeys[query->index].key);
		goto out;
	}

	if (0 == args_num)
	{
		ret = evaluate_function2(value, item, func_name, "", ts, error);
		goto out;
	}

	for (i = 0; i < args_num; i++)
	{
		if (0 != i)
			zbx_chrcpy_alloc(&params, &params_alloc, &params_offset, ',');

		switch (args[i].type)
		{
			case ZBX_VARIANT_DBL:
				zbx_snprintf_alloc(&params, &params_alloc, &params_offset, ZBX_FS_DBL64,
						args[i].data.dbl);
				break;
			case ZBX_VARIANT_STR:
				zbx_strquote_alloc(&params, &params_alloc, &params_offset, args[i].data.str);
				break;
			case ZBX_VARIANT_UI64:
				zbx_snprintf_alloc(&params, &params_alloc, &params_offset, ZBX_FS_UI64,
						args[i].data.ui64);
				break;
			case ZBX_VARIANT_NONE:
				break;
			default:
				*error = zbx_dsprintf(NULL,"Cannot evaluate function \"%s\":"
						" unsupported argument #%d type: %s",
						func_name, i + 1, zbx_variant_type_desc(&args[i]));
				goto out;
		}
	}

	ret = evaluate_function2(value, item, func_name, params, ts, error);
out:
	zbx_free(params);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: calcitem_eval                                                    *
 *                                                                            *
 * Purpose: evaluate historical function                                      *
 *                                                                            *
 * Parameters: name     - [IN] the function name (not zero terminated)        *
 *             len      - [IN] the function name length                       *
 *             args_num - [IN] the number of function arguments               *
 *             args     - [IN] an array of the function arguments.            *
 *             data     - [IN] the caller data used for function evaluation   *
 *             ts       - [IN] the function execution time                    *
 *             value    - [OUT] the function return value                     *
 *             error    - [OUT] the error message if function failed          *
 *                                                                            *
 * Return value: SUCCEED - the function was executed successfully             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	calcitem_eval(const char *name, size_t len, int args_num, const zbx_variant_t *args,
		void *data, const zbx_timespec_t *ts, zbx_variant_t *value, char **error)
{
	int			ret = FAIL;
	zbx_calc_eval_t		*eval;
	zbx_item_query_t	*query;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() function:%.*s", __func__, (int)len, name);

	zbx_variant_set_none(value);

	if (0 == args_num)
	{
		*error = zbx_strdup(NULL, "Cannot evaluate function: invalid number of arguments");
		goto out;
	}

	if (len >= MAX_STRING_LEN)
	{
		*error = zbx_strdup(NULL, "Cannot evaluate function: name too long");
		goto out;
	}

	eval = (zbx_calc_eval_t *)data;

	/* the historical function item query argument is replaced with corresponding itemrefs index */
	query = (zbx_item_query_t *)eval->itemrefs.values[(int)args[0].data.ui64];

	if (ZBX_ITEM_QUERY_SINGLE == query->type)
		ret = calcitem_eval_single(eval, query, name, len, args_num - 1, args + 1, ts, value, error);
	else
		*error = zbx_strdup(NULL, "Cannot evaluate function: multiple item filters are not supported");

out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s value:%s", __func__, zbx_result_string(ret),
			zbx_variant_value_desc(value));

	return ret;
}

int	get_value_calculated(DC_ITEM *dc_item, AGENT_RESULT *result)
{
	int			ret = NOTSUPPORTED;
	char			*error = NULL;
	zbx_eval_context_t	ctx;
	zbx_calc_eval_t		eval;
	zbx_timespec_t		ts;
	zbx_variant_t		value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s' expression:'%s'", __func__, dc_item->key_orig, dc_item->params);

	if (NULL == dc_item->formula_bin)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() serialized formula is not set", __func__);
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot evaluate calculated item:"
				" serialized formula is not set"));
		error = NULL;
		goto out;
	}

	zbx_eval_deserialize(&ctx, dc_item->params, ZBX_EVAL_PARSE_CALC_EXPRESSSION, dc_item->formula_bin);

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
	{
		char	*expression = NULL;

		zbx_eval_compose_expression(&ctx, &expression);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() expanded expression:'%s'", __func__, expression);
		zbx_free(expression);
	}

	calc_eval_init(&eval, dc_item, &ctx);
	zbx_timespec(&ts);

	if (SUCCEED != zbx_eval_execute_ext(&ctx, &ts, calcitem_eval, (void *)&eval, &value, &error))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() error:%s", __func__, error);
		SET_MSG_RESULT(result, error);
		error = NULL;
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() value:%s", __func__, zbx_variant_value_desc(&value));

		switch (value.type)
		{
			case ZBX_VARIANT_DBL:
				SET_DBL_RESULT(result, value.data.dbl);
				break;
			case ZBX_VARIANT_UI64:
				SET_UI64_RESULT(result, value.data.ui64);
				break;
			case ZBX_VARIANT_STR:
				SET_TEXT_RESULT(result, value.data.str);
				break;
		}

		zbx_variant_set_none(&value);
		ret = SUCCEED;
	}

	calc_eval_clear(&eval);
	zbx_eval_clear(&ctx);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
