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

#include "common.h"
#include "log.h"
#include "valuecache.h"
#include "dbcache.h"

#include "checks_aggregate.h"

#define ZBX_VALUE_FUNC_MIN	0
#define ZBX_VALUE_FUNC_AVG	1
#define ZBX_VALUE_FUNC_MAX	2
#define ZBX_VALUE_FUNC_SUM	3
#define ZBX_VALUE_FUNC_COUNT	4
#define ZBX_VALUE_FUNC_LAST	5

/******************************************************************************
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
 * Purpose: quotes string by enclosing it in double quotes and escaping       *
 *          double quotes inside string with '\'.                             *
 *                                                                            *
 * Parameters: str    - [IN/OUT] the string to quote                          *
 *             sz_str - [IN] the string length                                *
 *                                                                            *
 * Comments: The '\' character itself is not quoted. As the result if string  *
 *           ends with '\' it can be quoted (for example for error messages), *
 *           but it's impossible to unquote it.                               *
 *                                                                            *
 ******************************************************************************/
static void	quote_string(char **str, size_t sz_src)
{
	size_t	sz_dst;

	sz_dst = zbx_get_escape_string_len(*str, "\"") + 3;

	*str = (char *)zbx_realloc(*str, sz_dst);

	(*str)[--sz_dst] = '\0';
	(*str)[--sz_dst] = '"';

	while (0 < sz_src)
	{
		(*str)[--sz_dst] = (*str)[--sz_src];

		if ('"' == (*str)[sz_src])
			(*str)[--sz_dst] = '\\';
	}
	(*str)[--sz_dst] = '"';
}

/******************************************************************************
 *                                                                            *
 * Purpose: quotes the individual groups in the list if necessary             *
 *                                                                            *
 ******************************************************************************/
static void	aggregate_quote_groups(char **str, size_t *str_alloc, size_t *str_offset, zbx_vector_str_t *groups)
{
	int	i;
	char	*group, *separator = "";

	for (i = 1; i <= groups->values_num; i++)
	{
		group = zbx_strdup(NULL, groups->values[i - 1]);
		zbx_strcpy_alloc(str, str_alloc, str_offset, separator);
		separator = (char *)", ";

		quote_string(&group, strlen(group));
		zbx_strcpy_alloc(str, str_alloc, str_offset, group);
		zbx_free(group);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: get array of items specified by key for selected groups           *
 *          (including nested groups)                                         *
 *                                                                            *
 * Parameters: itemids - [OUT] list of item ids                               *
 *             groups  - [IN] list of host groups                             *
 *             itemkey - [IN] item key to aggregate                           *
 *             error   - [OUT] the error message                              *
 *                                                                            *
 * Return value: SUCCEED - item identifier(s) were retrieved successfully     *
 *               FAIL    - no items matching the specified groups or keys     *
 *                                                                            *
 ******************************************************************************/
static int	aggregate_get_items(zbx_vector_uint64_t *itemids, zbx_vector_str_t *groups, const char *itemkey,
		char **error)
{
	char			*esc;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_uint64_t		itemid;
	char			*sql = NULL;
	size_t			sql_alloc = ZBX_KIBIBYTE, sql_offset = 0, error_alloc = 0, error_offset = 0;
	int			ret = FAIL;
	zbx_vector_uint64_t	groupids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() itemkey:'%s'", __func__, itemkey);

	zbx_vector_uint64_create(&groupids);
	zbx_dc_get_nested_hostgroupids_by_names(groups, &groupids);

	if (0 == groupids.values_num)
	{
		zbx_strcpy_alloc(error, &error_alloc, &error_offset, "None of the groups in list ");
		aggregate_quote_groups(error, &error_alloc, &error_offset, groups);
		zbx_strcpy_alloc(error, &error_alloc, &error_offset, " is correct.");
		goto out;
	}

	sql = (char *)zbx_malloc(sql, sql_alloc);
	esc = DBdyn_escape_string(itemkey);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct i.itemid"
			" from items i,hosts h,hosts_groups hg,item_rtdata ir"
			" where i.hostid=h.hostid"
				" and h.hostid=hg.hostid"
				" and i.key_='%s'"
				" and i.status=%d"
				" and ir.itemid=i.itemid"
				" and ir.state=%d"
				" and h.status=%d"
				" and",
			esc, ITEM_STATUS_ACTIVE, ITEM_STATE_NORMAL, HOST_STATUS_MONITORED);

	zbx_free(esc);

	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hg.groupid", groupids.values, groupids.values_num);
	result = DBselect("%s", sql);
	zbx_free(sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(itemid, row[0]);
		zbx_vector_uint64_append(itemids, itemid);
	}
	DBfree_result(result);

	if (0 == itemids->values_num)
	{
		zbx_snprintf_alloc(error, &error_alloc, &error_offset, "No items for key \"%s\" in group(s) ", itemkey);
		aggregate_quote_groups(error, &error_alloc, &error_offset, groups);
		zbx_chrcpy_alloc(error, &error_alloc, &error_offset, '.');
		goto out;
	}

	zbx_vector_uint64_sort(itemids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	ret = SUCCEED;

out:
	zbx_vector_uint64_destroy(&groupids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Parameters: item      - [IN] aggregated item                               *
 *             grp_func  - [IN] one of ZBX_GRP_FUNC_*                         *
 *             groups    - [IN] list of host groups                           *
 *             itemkey   - [IN] item key to aggregate                         *
 *             item_func - [IN] one of ZBX_VALUE_FUNC_*                       *
 *             param     - [IN] item_func parameter (optional)                *
 *                                                                            *
 * Return value: SUCCEED - aggregate item evaluated successfully              *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_aggregate(const DC_ITEM *item, AGENT_RESULT *res, int grp_func, zbx_vector_str_t *groups,
		const char *itemkey, int item_func, const char *param)
{
	zbx_vector_uint64_t		itemids;
	history_value_t			value, item_result;
	zbx_history_record_t		group_value;
	int				ret = FAIL, *errcodes = NULL, i, count, seconds;
	DC_ITEM				*items = NULL;
	zbx_vector_history_record_t	values, group_values;
	char				*error = NULL;
	zbx_timespec_t			ts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() grp_func:%d itemkey:'%s' item_func:%d param:'%s'",
			__func__, grp_func, itemkey, item_func, ZBX_NULL2STR(param));

	zbx_timespec(&ts);
	zbx_vector_uint64_create(&itemids);

	if (FAIL == aggregate_get_items(&itemids, groups, itemkey, &error))
	{
		SET_MSG_RESULT(res, error);
		goto clean1;
	}

	memset(&value, 0, sizeof(value));
	zbx_history_record_vector_create(&group_values);

	items = (DC_ITEM *)zbx_malloc(items, sizeof(DC_ITEM) * itemids.values_num);
	errcodes = (int *)zbx_malloc(errcodes, sizeof(int) * itemids.values_num);

	DCconfig_get_items_by_itemids(items, itemids.values, errcodes, itemids.values_num);

	if (ZBX_VALUE_FUNC_LAST == item_func)
	{
		count = 1;
		seconds = 0;
	}
	else
	{
		if (FAIL == is_time_suffix(param, &seconds, ZBX_LENGTH_UNLIMITED))
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

		if (SUCCEED == zbx_vc_get_values(items[i].itemid, items[i].value_type, &values, seconds, count, &ts) &&
				0 < values.values_num)
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

	zbx_vc_flush_stats();

	if (0 == group_values.values_num)
	{
		char	*tmp = NULL;
		size_t	tmp_alloc = 0, tmp_offset = 0;

		aggregate_quote_groups(&tmp, &tmp_alloc, &tmp_offset, groups);
		SET_MSG_RESULT(res, zbx_dsprintf(NULL, "No values for key \"%s\" in group(s) %s.", itemkey, tmp));
		zbx_free(tmp);

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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
