/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

#include "common.h"
#include "log.h"
#include "valuecache.h"

#include "checks_aggregate.h"

#define ZBX_VALUE_FUNC_MIN	0
#define ZBX_VALUE_FUNC_AVG	1
#define ZBX_VALUE_FUNC_MAX	2
#define ZBX_VALUE_FUNC_SUM	3
#define ZBX_VALUE_FUNC_COUNT	4
#define ZBX_VALUE_FUNC_LAST	5

/******************************************************************************
 *                                                                            *
 * Function: evaluate_history_func_min                                        *
 *                                                                            *
 * Purpose: calculate minimum value from the history value vector             *
 *                                                                            *
 * Parameters: values      - [IN] a vector containing history values          *
 *             value_type  - [IN] the type of values. Only float/uint64       *
 *                           values are supported.                            *
 *             result      - [OUT] the resulting value                        *
 *                                                                            *
 ******************************************************************************/
static void	evaluate_history_func_min(zbx_vector_history_record_t *values, int value_type, history_value_t *result)
{
	int	i;

	*result = values->values[0].value;

	if (ITEM_VALUE_TYPE_UINT64 == value_type)
	{
		for (i = 1; i < values->values_num; i++)
			if (values->values[i].value.ui64 < result->ui64)
				result->ui64 = values->values[i].value.ui64;
	}
	else
	{
		for (i = 1; i < values->values_num; i++)
			if (values->values[i].value.dbl < result->dbl)
				result->dbl = values->values[i].value.dbl;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_history_func_max                                        *
 *                                                                            *
 * Purpose: calculate maximum value from the history value vector             *
 *                                                                            *
 * Parameters: values      - [IN] a vector containing history values          *
 *             value_type  - [IN] the type of values. Only float/uint64       *
 *                           values are supported.                            *
 *             result      - [OUT] the resulting value                        *
 *                                                                            *
 ******************************************************************************/
static void	evaluate_history_func_max(zbx_vector_history_record_t *values, int value_type, history_value_t *result)
{
	int	i;

	*result = values->values[0].value;

	if (ITEM_VALUE_TYPE_UINT64 == value_type)
	{
		for (i = 1; i < values->values_num; i++)
			if (values->values[i].value.ui64 > result->ui64)
				result->ui64 = values->values[i].value.ui64;
	}
	else
	{
		for (i = 1; i < values->values_num; i++)
			if (values->values[i].value.dbl > result->dbl)
				result->dbl = values->values[i].value.dbl;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_history_func_sum                                        *
 *                                                                            *
 * Purpose: calculate sum of values from the history value vector             *
 *                                                                            *
 * Parameters: values      - [IN] a vector containing history values          *
 *             value_type  - [IN] the type of values. Only float/uint64       *
 *                           values are supported.                            *
 *             result      - [OUT] the resulting value                        *
 *                                                                            *
 ******************************************************************************/
static void	evaluate_history_func_sum(zbx_vector_history_record_t *values, int value_type, history_value_t *result)
{
	int	i;

	if (ITEM_VALUE_TYPE_UINT64 == value_type)
	{
		result->ui64 = 0;
		for (i = 0; i < values->values_num; i++)
			result->ui64 += values->values[i].value.ui64;
	}
	else
	{
		result->dbl = 0;
		for (i = 0; i < values->values_num; i++)
			result->dbl += values->values[i].value.dbl;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_history_func_avg                                        *
 *                                                                            *
 * Purpose: calculate average value of values from the history value vector   *
 *                                                                            *
 * Parameters: values      - [IN] a vector containing history values          *
 *             value_type  - [IN] the type of values. Only float/uint64       *
 *                           values are supported.                            *
 *             result      - [OUT] the resulting value                        *
 *                                                                            *
 ******************************************************************************/
static void	evaluate_history_func_avg(zbx_vector_history_record_t *values, int value_type, history_value_t *result)
{
	evaluate_history_func_sum(values, value_type, result);

	if (ITEM_VALUE_TYPE_UINT64 == value_type)
		result->ui64 /= values->values_num;
	else
		result->dbl /= values->values_num;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_history_func_count                                      *
 *                                                                            *
 * Purpose: calculate number of values in value vector                        *
 *                                                                            *
 * Parameters: values      - [IN] a vector containing history values          *
 *             value_type  - [IN] the type of values. Only float/uint64       *
 *                           values are supported.                            *
 *             result      - [OUT] the resulting value                        *
 *                                                                            *
 ******************************************************************************/
static void	evaluate_history_func_count(zbx_vector_history_record_t *values, int value_type,
		history_value_t *result)
{
	if (ITEM_VALUE_TYPE_UINT64 == value_type)
		result->ui64 = values->values_num;
	else
		result->dbl = values->values_num;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_history_func_last                                       *
 *                                                                            *
 * Purpose: calculate the last (newest) value in value vector                 *
 *                                                                            *
 * Parameters: values      - [IN] a vector containing history values          *
 *             result      - [OUT] the resulting value                        *
 *                                                                            *
 ******************************************************************************/
static void	evaluate_history_func_last(zbx_vector_history_record_t *values, history_value_t *result)
{
	*result = values->values[0].value;
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_history_func                                            *
 *                                                                            *
 * Purpose: calculate function with values from value vector                  *
 *                                                                            *
 * Parameters: values      - [IN] a vector containing history values          *
 *             value_type  - [IN] the type of values. Only float/uint64       *
 *                           values are supported.                            *
 *             func        - [IN] the function to calculate. Only             *
 *                           ZBX_VALUE_FUNC_MIN, ZBX_VALUE_FUNC_AVG,          *
 *                           ZBX_VALUE_FUNC_MAX, ZBX_VALUE_FUNC_SUM,          *
 *                           ZBX_VALUE_FUNC_COUNT, ZBX_VALUE_FUNC_LAST        *
 *                           functions are supported.                         *
 *             result      - [OUT] the resulting value                        *
 *                                                                            *
 ******************************************************************************/
static void	evaluate_history_func(zbx_vector_history_record_t *values, int value_type, int func,
		history_value_t *result)
{
	switch (func)
	{
		case ZBX_VALUE_FUNC_MIN:
			evaluate_history_func_min(values, value_type, result);
			break;
		case ZBX_VALUE_FUNC_AVG:
			evaluate_history_func_avg(values, value_type, result);
			break;
		case ZBX_VALUE_FUNC_MAX:
			evaluate_history_func_max(values, value_type, result);
			break;
		case ZBX_VALUE_FUNC_SUM:
			evaluate_history_func_sum(values, value_type, result);
			break;
		case ZBX_VALUE_FUNC_COUNT:
			evaluate_history_func_count(values, value_type, result);
			break;
		case ZBX_VALUE_FUNC_LAST:
			evaluate_history_func_last(values, result);
			break;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: aggregate_get_items                                              *
 *                                                                            *
 * Purpose: get array of items specified by key for selected groups           *
 *                                                                            *
 * Parameters: itemids - [OUT] list of item ids                               *
 *             groups  - [IN] list of comma-separated host groups             *
 *             itemkey - [IN] item key to aggregate                           *
 *                                                                            *
 ******************************************************************************/
static void	aggregate_get_items(zbx_vector_uint64_t *itemids, const char *groups, const char *itemkey)
{
	const char	*__function_name = "aggregate_get_items";

	char			*group, *esc;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_uint64_t		itemid;
	char			*sql = NULL;
	size_t			sql_alloc = ZBX_KIBIBYTE, sql_offset = 0;
	int			num, n;
	zbx_vector_uint64_t	groupids;
	zbx_vector_str_t	group_names;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() groups:'%s' itemkey:'%s'", __function_name, groups, itemkey);

	zbx_vector_uint64_create(&groupids);
	sql = zbx_malloc(sql, sql_alloc);

	esc = DBdyn_escape_string(itemkey);

	zbx_vector_str_create(&group_names);

	num = num_param(groups);
	for (n = 1; n <= num; n++)
	{
		if (NULL == (group = get_param_dyn(groups, n)))
			continue;

		zbx_vector_str_append(&group_names, group);
	}

	/* TODO: make item notsupported when zbx_dc_get_nested_hostgroupids_by_names() fails */
	zbx_dc_get_nested_hostgroupids_by_names(group_names.values, group_names.values_num, &groupids);
	zbx_vector_str_clear_ext(&group_names, zbx_ptr_free);
	zbx_vector_str_destroy(&group_names);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct i.itemid"
			" from items i,hosts h,hosts_groups hg"
			" where i.hostid=h.hostid"
				" and h.hostid=hg.hostid"
				" and i.key_='%s'"
				" and i.status=%d"
				" and i.state=%d"
				" and h.status=%d"
				" and",
			esc, ITEM_STATUS_ACTIVE, ITEM_STATE_NORMAL, HOST_STATUS_MONITORED);

	zbx_free(esc);

	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hg.groupid", groupids.values,
			groupids.values_num);

	zbx_vector_uint64_destroy(&groupids);

	result = DBselect("%s", sql);

	zbx_free(sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(itemid, row[0]);
		zbx_vector_uint64_append(itemids, itemid);
	}
	DBfree_result(result);

	zbx_vector_uint64_sort(itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: evaluate_aggregate                                               *
 *                                                                            *
 * Parameters: item      - [IN] aggregated item                               *
 *             grp_func  - [IN] one of ZBX_GRP_FUNC_*                         *
 *             groups    - [IN] list of comma-separated host groups           *
 *             itemkey   - [IN] item key to aggregate                         *
 *             item_func - [IN] one of ZBX_VALUE_FUNC_*                       *
 *             param     - [IN] item_func parameter (optional)                *
 *                                                                            *
 * Return value: SUCCEED - aggregate item evaluated successfully              *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_aggregate(DC_ITEM *item, AGENT_RESULT *res, int grp_func, const char *groups,
		const char *itemkey, int item_func, const char *param)
{
	const char			*__function_name = "evaluate_aggregate";
	zbx_vector_uint64_t		itemids;
	history_value_t			value, item_result;
	zbx_history_record_t		group_value;
	int				ret = FAIL, now, *errcodes = NULL, i, count, seconds;
	DC_ITEM				*items = NULL;
	zbx_vector_history_record_t	values, group_values;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() grp_func:%d groups:'%s' itemkey:'%s' item_func:%d param:'%s'",
			__function_name, grp_func, groups, itemkey, item_func, ZBX_NULL2STR(param));

	now = time(NULL);

	zbx_vector_uint64_create(&itemids);
	aggregate_get_items(&itemids, groups, itemkey);

	if (0 == itemids.values_num)
	{
		SET_MSG_RESULT(res, zbx_dsprintf(NULL, "No items for key \"%s\" in group(s) \"%s\".", itemkey, groups));
		goto clean1;
	}

	memset(&value, 0, sizeof(value));
	zbx_history_record_vector_create(&group_values);

	items = zbx_malloc(items, sizeof(DC_ITEM) * itemids.values_num);
	errcodes = zbx_malloc(errcodes, sizeof(int) * itemids.values_num);

	DCconfig_get_items_by_itemids(items, itemids.values, errcodes, itemids.values_num);

	if (ZBX_VALUE_FUNC_LAST == item_func)
	{
		count = 1;
		seconds = 0;
	}
	else
	{
		if (FAIL == is_time_suffix(param, &seconds))
		{
			SET_MSG_RESULT(res, zbx_strdup(NULL, "Invalid fourth parameter."));
			goto clean2;
		}
		count = 0;
	}

	for (i = 0; i < itemids.values_num; i++)
	{
		if (SUCCEED != errcodes[i])
			continue;

		if (ITEM_STATUS_ACTIVE != items[i].status)
			continue;

		if (HOST_STATUS_MONITORED != items[i].host.status)
			continue;

		if (ITEM_VALUE_TYPE_FLOAT != items[i].value_type && ITEM_VALUE_TYPE_UINT64 != items[i].value_type)
			continue;

		zbx_history_record_vector_create(&values);

		if (SUCCEED == zbx_vc_get_value_range(items[i].itemid, items[i].value_type, &values, seconds,
				count, now) && 0 < values.values_num)
		{
			evaluate_history_func(&values, items[i].value_type, item_func, &item_result);

			if (item->value_type == items[i].value_type)
				group_value.value = item_result;
			else
			{
				if (ITEM_VALUE_TYPE_UINT64 == item->value_type)
					group_value.value.ui64 = (zbx_uint64_t)item_result.dbl;
				else
					group_value.value.dbl = (double)item_result.ui64;
			}

			zbx_vector_history_record_append_ptr(&group_values, &group_value);
		}

		zbx_history_record_vector_destroy(&values, items[i].value_type);
	}

	if (0 == group_values.values_num)
	{
		SET_MSG_RESULT(res, zbx_dsprintf(NULL, "No values for key \"%s\" in group(s) \"%s\"", itemkey, groups));
		goto clean2;
	}

	evaluate_history_func(&group_values, item->value_type, grp_func, &value);

	if (ITEM_VALUE_TYPE_FLOAT == item->value_type)
		SET_DBL_RESULT(res, value.dbl);
	else
		SET_UI64_RESULT(res, value.ui64);

	ret = SUCCEED;
clean2:
	DCconfig_clean_items(items, errcodes, itemids.values_num);

	zbx_free(errcodes);
	zbx_free(items);
	zbx_history_record_vector_destroy(&group_values, item->value_type);
clean1:
	zbx_vector_uint64_destroy(&itemids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: get_value_aggregate                                              *
 *                                                                            *
 * Purpose: retrieve data from Zabbix server (aggregate items)                *
 *                                                                            *
 * Parameters: item - item we are interested in                               *
 *                                                                            *
 * Return value: SUCCEED - data successfully retrieved and stored in result   *
 *                         and result_str (as string)                         *
 *               NOTSUPPORTED - requested item is not supported               *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
int	get_value_aggregate(DC_ITEM *item, AGENT_RESULT *result)
{
	const char	*__function_name = "get_value_aggregate";

	AGENT_REQUEST	request;
	int		ret = NOTSUPPORTED;
	const char	*tmp, *groups, *itemkey, *funcp = NULL;
	int		grp_func, item_func, params_num;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s'", __function_name, item->key_orig);

	init_request(&request);

	if (ITEM_VALUE_TYPE_FLOAT != item->value_type && ITEM_VALUE_TYPE_UINT64 != item->value_type)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Value type must be Numeric for aggregate items"));
		goto out;
	}

	if (SUCCEED != parse_item_key(item->key, &request))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid item key format."));
		goto out;
	}

	if (0 == strcmp(get_rkey(&request), "grpmin"))
	{
		grp_func = ZBX_VALUE_FUNC_MIN;
	}
	else if (0 == strcmp(get_rkey(&request), "grpavg"))
	{
		grp_func = ZBX_VALUE_FUNC_AVG;
	}
	else if (0 == strcmp(get_rkey(&request), "grpmax"))
	{
		grp_func = ZBX_VALUE_FUNC_MAX;
	}
	else if (0 == strcmp(get_rkey(&request), "grpsum"))
	{
		grp_func = ZBX_VALUE_FUNC_SUM;
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid item key."));
		goto out;
	}

	params_num = get_rparams_num(&request);

	if (3 > params_num || params_num > 4)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	groups = get_rparam(&request, 0);
	itemkey = get_rparam(&request, 1);
	tmp = get_rparam(&request, 2);

	if (0 == strcmp(tmp, "min"))
		item_func = ZBX_VALUE_FUNC_MIN;
	else if (0 == strcmp(tmp, "avg"))
		item_func = ZBX_VALUE_FUNC_AVG;
	else if (0 == strcmp(tmp, "max"))
		item_func = ZBX_VALUE_FUNC_MAX;
	else if (0 == strcmp(tmp, "sum"))
		item_func = ZBX_VALUE_FUNC_SUM;
	else if (0 == strcmp(tmp, "count"))
		item_func = ZBX_VALUE_FUNC_COUNT;
	else if (0 == strcmp(tmp, "last"))
		item_func = ZBX_VALUE_FUNC_LAST;
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
		goto out;
	}

	if (4 == params_num)
	{
		funcp = get_rparam(&request, 3);
	}
	else if (3 == params_num && ZBX_VALUE_FUNC_LAST != item_func)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
		goto out;
	}

	if (SUCCEED != evaluate_aggregate(item, result, grp_func, groups, itemkey, item_func, funcp))
		goto out;

	ret = SUCCEED;
out:
	free_request(&request);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
