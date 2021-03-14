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
	zbx_host_key_t	*hostkeys;
	DC_ITEM		*items;
	int		*errcodes;
	int		num;
}
zbx_dc_items_t;

/******************************************************************************
 *                                                                            *
 * Typedef: calcitem_parse_item_query                                         *
 *                                                                            *
 * Purpose: parse item query /host/key into host, key components              *
 *                                                                            *
 * Parameters: dc_item - [IN] the calculated item                             *
 *             query   - [IN] the item query, can change contents during      *
 *                            processing                                      *
 *             hostkey - [OUT] the host key values referencing to the query   *
 *                                                                            *
 * Comments: The host key values is not allocated but will point at the host, *
 *           key values in the item query. If host is absent in query, then   *
 *           hostkey->host will point at the host.host from dc_item           *
 *                                                                            *
 ******************************************************************************/
static void	calcitem_parse_item_query(const DC_ITEM *dc_item, char *query, zbx_host_key_t *hostkey)
{
	char	*key;

	if ('/' != *query || NULL == (key = strchr(++query, '/')))
	{
		hostkey->host = "";
		hostkey->key = "";
		return;
	}

	hostkey->host = (char *)(query == key ? dc_item->host.host : query);
	*key++ = '\0';
	hostkey->key = key;
}

/******************************************************************************
 *                                                                            *
 * Typedef: calcitem_get_items                                                *
 *                                                                            *
 * Purpose: get items referred by queries from configuration cache            *
 *                                                                            *
 * Parameters: dc_item - [IN] the calculated item                             *
 *             queries - [IN] the item queries (/host/key references)         *
 *                            in calculated item formula                      *
 *             dcitems - [OUT] item errorcodes and data                       *
 *                                                                            *
 * Comments: Call calcitem_dcitems_clear() to free the allocated resources.   *
 *                                                                            *
 ******************************************************************************/
static void	calcitem_get_items(const DC_ITEM *dc_item, zbx_vector_str_t *queries, zbx_dc_items_t *dcitems)
{
	int		i;

	dcitems->num = queries->values_num;
	dcitems->hostkeys = (zbx_host_key_t *)zbx_malloc(NULL, sizeof(zbx_host_key_t) * dcitems->num);
	dcitems->items = (DC_ITEM *)zbx_malloc(NULL, sizeof(DC_ITEM) * dcitems->num);
	dcitems->errcodes = (int *)zbx_malloc(NULL, sizeof(int) * dcitems->num);

	for (i = 0; i < queries->values_num; i++)
		calcitem_parse_item_query(dc_item, queries->values[i], &dcitems->hostkeys[i]);

	DCconfig_get_items_by_keys(dcitems->items, dcitems->hostkeys, dcitems->errcodes, dcitems->num);
}

/******************************************************************************
 *                                                                            *
 * Typedef: calcitem_dcitems_clear                                            *
 *                                                                            *
 * Purpose: clears resources allocated by calcitem_get_items() function       *
 *                                                                            *
 ******************************************************************************/
static void	calcitem_dcitems_clear(zbx_dc_items_t *dcitems)
{
	DCconfig_clean_items(dcitems->items, dcitems->errcodes, dcitems->num);
	zbx_free(dcitems->hostkeys);
	zbx_free(dcitems->errcodes);
	zbx_free(dcitems->items);
}

/******************************************************************************
 *                                                                            *
 * Typedef: calcitem_eval                                                     *
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
	int		index, ret = FAIL, i;
	zbx_dc_items_t	*dcitems;
	DC_ITEM		*item;
	char		func_name[MAX_STRING_LEN], *params = NULL;
	size_t		params_alloc = 0, params_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() function:%.*s", __func__, (int)len, name);

	zbx_variant_set_none(value);

	if (0 == args_num)
	{
		*error = zbx_strdup(NULL, "invalid number of arguments");
		goto out;
	}

	if (len >= MAX_STRING_LEN)
	{
		*error = zbx_strdup(NULL, "too long function name");
		goto out;
	}

	dcitems = (zbx_dc_items_t *)data;

	/* the historical function item query argument is replaced with corresponding DC_ITEM index */
	index = (int)args[0].data.ui64;

	if (SUCCEED != dcitems->errcodes[index])
	{
		*error = zbx_dsprintf(NULL, "Cannot evaluate function because item \"/%s/%s\" does not exist",
				dcitems->hostkeys[index].host, dcitems->hostkeys[index].key);
		goto out;
	}

	item = &dcitems->items[index];

	/* do not evaluate if the item is disabled or belongs to a disabled host */

	if (ITEM_STATUS_ACTIVE != item->status)
	{
		*error = zbx_dsprintf(NULL, "Cannot evaluate function with disabled item \"/%s/%s\"",
				dcitems->hostkeys[index].host, dcitems->hostkeys[index].key);
		goto out;
	}

	if (HOST_STATUS_MONITORED != item->host.status)
	{
		*error = zbx_dsprintf(NULL, "Cannot evaluate function with item \"/%s/%s\" belonging to a disabled host",
				dcitems->hostkeys[index].host, dcitems->hostkeys[index].key);
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
				dcitems->hostkeys[index].host, dcitems->hostkeys[index].key);
		goto out;
	}

	if (1 == args_num)
	{
		ret = evaluate_function2(value, item, func_name, "", ts, error);
		goto out;
	}

	for (i = 1; i < args_num; i++)
	{
		if (1 < i)
			zbx_chrcpy_alloc(&params, &params_alloc, &params_offset, ',');

		switch (args[i].type)
		{
		case ZBX_VARIANT_DBL:
			zbx_snprintf_alloc(&params, &params_alloc, &params_offset, ZBX_FS_DBL64, args[i].data.dbl);
			break;
		case ZBX_VARIANT_STR:
			zbx_strquote_alloc(&params, &params_alloc, &params_offset, args[i].data.str);
			break;
		case ZBX_VARIANT_UI64:
			zbx_snprintf_alloc(&params, &params_alloc, &params_offset, ZBX_FS_UI64, args[i].data.ui64);
			break;
		case ZBX_VARIANT_NONE:
			break;
		default:
			*error = zbx_dsprintf(NULL,"Cannot evaluate function \"%s\": unsupported argument #%d type: %s",
					func_name, i + 1, zbx_variant_type_desc(&args[i]));
			goto out;
		}
	}

	ret = evaluate_function2(value, item, func_name, params, ts, error);
out:
	zbx_free(params);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s value:%s", __func__, zbx_result_string(ret),
			zbx_variant_value_desc(value));

	return ret;
}

int	get_value_calculated(DC_ITEM *dc_item, AGENT_RESULT *result)
{
	int			ret = NOTSUPPORTED;
	char			*error = NULL;
	zbx_eval_context_t	ctx;
	zbx_vector_str_t	queries;
	zbx_dc_items_t		dcitems;
	zbx_timespec_t		ts;
	zbx_variant_t		value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s' expression:'%s'", __func__, dc_item->key_orig, dc_item->params);

	zbx_vector_str_create(&queries);

	if (SUCCEED != zbx_eval_parse_expression(&ctx, dc_item->params, ZBX_EVAL_PARSE_CALC_EXPRESSSION, &error))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() error:%s", __func__, error);
		SET_MSG_RESULT(result, error);
		error = NULL;
		goto out;
	}

	zbx_eval_extract_item_queries(&ctx, &queries);
	calcitem_get_items(dc_item, &queries, &dcitems);

	zbx_timespec(&ts);

	if (SUCCEED != zbx_eval_execute_ext(&ctx, &ts, calcitem_eval, (void *)&dcitems, &value, &error))
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

	calcitem_dcitems_clear(&dcitems);
	zbx_vector_str_clear_ext(&queries, zbx_str_free);
	zbx_vector_str_destroy(&queries);
	zbx_eval_clear(&ctx);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
