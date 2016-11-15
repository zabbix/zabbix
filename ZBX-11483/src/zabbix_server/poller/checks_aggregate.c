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

#include "checks_aggregate.h"

#define ZBX_GRP_FUNC_MIN	0
#define ZBX_GRP_FUNC_AVG	1
#define ZBX_GRP_FUNC_MAX	2
#define ZBX_GRP_FUNC_SUM	3

static void	evaluate_one(DC_ITEM *item, history_value_t *result, int *num, int grp_func,
		const char *value_str, unsigned char value_type)
{
	history_value_t	value;

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			value.dbl = atof(value_str);
			if (ITEM_VALUE_TYPE_UINT64 == item->value_type)
				value.ui64 = (zbx_uint64_t)value.dbl;
			break;
		case ITEM_VALUE_TYPE_UINT64:
			ZBX_STR2UINT64(value.ui64, value_str);
			if (ITEM_VALUE_TYPE_FLOAT == item->value_type)
				value.dbl = (double)value.ui64;
			break;
		default:
			assert(0);
	}

	switch (item->value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			switch (grp_func)
			{
				case ZBX_GRP_FUNC_AVG:
				case ZBX_GRP_FUNC_SUM:
					result->dbl += value.dbl;
					break;
				case ZBX_GRP_FUNC_MIN:
					if (0 == *num || value.dbl < result->dbl)
						result->dbl = value.dbl;
					break;
				case ZBX_GRP_FUNC_MAX:
					if (0 == *num || value.dbl > result->dbl)
						result->dbl = value.dbl;
					break;
				default:
					assert(0);
			}
			break;
		case ITEM_VALUE_TYPE_UINT64:
			switch (grp_func)
			{
				case ZBX_GRP_FUNC_AVG:
				case ZBX_GRP_FUNC_SUM:
					result->ui64 += value.ui64;
					break;
				case ZBX_GRP_FUNC_MIN:
					if (0 == *num || value.ui64 < result->ui64)
						result->ui64 = value.ui64;
					break;
				case ZBX_GRP_FUNC_MAX:
					if (0 == *num || value.ui64 > result->ui64)
						result->ui64 = value.ui64;
					break;
				default:
					assert(0);
			}
			break;
		default:
			assert(0);
	}

	*num += 1;
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

	char		*group, *esc;
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	itemid;
	char		*sql = NULL;
	size_t		sql_alloc = ZBX_KIBIBYTE, sql_offset = 0;
	int		num, n;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() groups:'%s' itemkey:'%s'", __function_name, groups, itemkey);

	sql = zbx_malloc(sql, sql_alloc);

	esc = DBdyn_escape_string(itemkey);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct i.itemid"
			" from items i,hosts h,hosts_groups hg,groups g"
			" where i.hostid=h.hostid"
				" and h.hostid=hg.hostid"
				" and hg.groupid=g.groupid"
				" and i.key_='%s'"
				" and i.status=%d"
				" and h.status=%d",
			esc, ITEM_STATUS_ACTIVE, HOST_STATUS_MONITORED);

	zbx_free(esc);

	num = num_param(groups);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " and g.name in (");

	for (n = 1; n <= num; n++)
	{
		if (NULL == (group = get_param_dyn(groups, n)))
			continue;

		esc = DBdyn_escape_string(group);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "'%s'", esc);

		if (n != num)
			zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, ',');

		zbx_free(esc);
		zbx_free(group);
	}

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, ")" DB_NODE, DBnode_local("h.hostid"));

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
 *             item_func - [IN] one of ZBX_DB_GET_HIST_*                      *
 *             param     - [IN] item_func parameter (optional)                *
 *                                                                            *
 * Return value: SUCCEED - aggregate item evaluated successfully              *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	evaluate_aggregate(DC_ITEM *item, AGENT_RESULT *res, int grp_func, const char *groups,
		const char *itemkey, int item_func, const char *param)
{
	const char		*__function_name = "evaluate_aggregate";

	char			*sql = NULL;
	size_t			sql_alloc = 1024, sql_offset = 0;
	zbx_uint64_t		itemid;
	zbx_vector_uint64_t	itemids;
	DB_RESULT		result;
	DB_ROW			row;
	unsigned char		value_type;
	history_value_t		value;
	int			num = 0, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() grp_func:%d groups:'%s' itemkey:'%s' item_func:%d param:'%s'",
			__function_name, grp_func, groups, itemkey, item_func, param);

	memset(&value, 0, sizeof(value));

	zbx_vector_uint64_create(&itemids);
	aggregate_get_items(&itemids, groups, itemkey);

	if (0 == itemids.values_num)
	{
		SET_MSG_RESULT(res, zbx_dsprintf(NULL, "No items for key [%s] in group(s) [%s]", itemkey, groups));
		goto clean;
	}

	sql = zbx_malloc(sql, sql_alloc);

	if (ZBX_DB_GET_HIST_VALUE == item_func)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select value_type,lastvalue"
				" from items"
				" where lastvalue is not null"
					" and value_type in (%d,%d)"
					" and",
				ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", itemids.values, itemids.values_num);

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			value_type = (unsigned char)atoi(row[0]);

			evaluate_one(item, &value, &num, grp_func, row[1], value_type);
		}
		DBfree_result(result);
	}
	else
	{
		int		clock_from;
		unsigned int	period;
		char		**h_value;

		if (FAIL == is_uint_suffix(param, &period))
		{
			SET_MSG_RESULT(res, zbx_strdup(NULL, "Invalid fourth parameter"));
			goto clean;
		}

		clock_from = time(NULL) - period;

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select itemid,value_type"
				" from items"
				" where value_type in (%d,%d)"
					" and",
				ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", itemids.values, itemids.values_num);

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(itemid, row[0]);
			value_type = (unsigned char)atoi(row[1]);

			h_value = DBget_history(itemid, value_type, item_func, clock_from, 0, NULL, NULL, 0);

			if (NULL != h_value[0])
				evaluate_one(item, &value, &num, grp_func, h_value[0], value_type);
			DBfree_history(h_value);
		}
		DBfree_result(result);
	}

	if (0 == num)
	{
		SET_MSG_RESULT(res, zbx_dsprintf(NULL, "No values for key \"%s\" in group(s) \"%s\"", itemkey, groups));
		goto clean;
	}

	if (ZBX_GRP_FUNC_AVG == grp_func)
	{
		switch (item->value_type)
		{
			case ITEM_VALUE_TYPE_FLOAT:
				value.dbl = value.dbl / num;
				break;
			case ITEM_VALUE_TYPE_UINT64:
				value.ui64 = value.ui64 / num;
				break;
			default:
				assert(0);
		}
	}

	if (ITEM_VALUE_TYPE_FLOAT == item->value_type)
		SET_DBL_RESULT(res, value.dbl);
	else
		SET_UI64_RESULT(res, value.ui64);

	ret = SUCCEED;
clean:
	zbx_vector_uint64_destroy(&itemids);
	zbx_free(sql);

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
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	get_value_aggregate(DC_ITEM *item, AGENT_RESULT *result)
{
	const char	*__function_name = "get_value_aggregate";

	char		tmp[8], params[MAX_STRING_LEN], groups[MAX_STRING_LEN], itemkey[MAX_STRING_LEN], funcp[32];
	int		grp_func, item_func, ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s'", __function_name, item->key_orig);

	if (ITEM_VALUE_TYPE_FLOAT != item->value_type && ITEM_VALUE_TYPE_UINT64 != item->value_type)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Value type must be Numeric for aggregate items"));
		return NOTSUPPORTED;
	}

	if (2 != parse_command(item->key, tmp, sizeof(tmp), params, sizeof(params)))
		return NOTSUPPORTED;

	if (0 == strcmp(tmp, "grpmin"))
		grp_func = ZBX_GRP_FUNC_MIN;
	else if (0 == strcmp(tmp, "grpavg"))
		grp_func = ZBX_GRP_FUNC_AVG;
	else if (0 == strcmp(tmp, "grpmax"))
		grp_func = ZBX_GRP_FUNC_MAX;
	else if (0 == strcmp(tmp, "grpsum"))
		grp_func = ZBX_GRP_FUNC_SUM;
	else
		return NOTSUPPORTED;

	if (4 != num_param(params))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters"));
		return NOTSUPPORTED;
	}

	if (0 != get_param(params, 1, groups, sizeof(groups)))
		return NOTSUPPORTED;

	if (0 != get_param(params, 2, itemkey, sizeof(itemkey)))
		return NOTSUPPORTED;

	if (0 != get_param(params, 3, tmp, sizeof(tmp)))
		return NOTSUPPORTED;

	if (0 == strcmp(tmp, "min"))
		item_func = ZBX_DB_GET_HIST_MIN;
	else if (0 == strcmp(tmp, "avg"))
		item_func = ZBX_DB_GET_HIST_AVG;
	else if (0 == strcmp(tmp, "max"))
		item_func = ZBX_DB_GET_HIST_MAX;
	else if (0 == strcmp(tmp, "sum"))
		item_func = ZBX_DB_GET_HIST_SUM;
	else if (0 == strcmp(tmp, "count"))
		item_func = ZBX_DB_GET_HIST_COUNT;
	else if (0 == strcmp(tmp, "last"))
		item_func = ZBX_DB_GET_HIST_VALUE;
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter"));
		return NOTSUPPORTED;
	}

	if (0 != get_param(params, 4, funcp, sizeof(funcp)))
		return NOTSUPPORTED;

	if (SUCCEED != evaluate_aggregate(item, result, grp_func, groups, itemkey, item_func, funcp))
		ret = NOTSUPPORTED;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
