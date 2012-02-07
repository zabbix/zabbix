/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"
#include "log.h"

#include "checks_aggregate.h"

#define ZBX_GRP_FUNC_MIN	0
#define ZBX_GRP_FUNC_AVG	1
#define ZBX_GRP_FUNC_MAX	2
#define ZBX_GRP_FUNC_SUM	3

static void	evaluate_one(double *result, int *num, int grp_func, const char *value, unsigned char value_type)
{
	double	value_float;

	switch (value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
		case ITEM_VALUE_TYPE_UINT64:
			value_float = atof(value);
			break;
		default:
			assert(0);
	}

	switch (grp_func)
	{
		case ZBX_GRP_FUNC_AVG:
		case ZBX_GRP_FUNC_SUM:
			*result += value_float;
			break;
		case ZBX_GRP_FUNC_MIN:
			if (0 == *num || value_float < *result)
				*result = value_float;
			break;
		case ZBX_GRP_FUNC_MAX:
			if (0 == *num || value_float > *result)
				*result = value_float;
			break;
		default:
			assert(0);
	}

	*num += 1;
}

/*
 * get array of items with specified key for selected groups
 */
static void	aggregate_get_items(zbx_uint64_t **ids, int *ids_alloc, int *ids_num, const char *groups, const char *itemkey)
{
	char		*group, *esc;
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	itemid;
	char		*sql = NULL;
	int		sql_alloc = 1024, sql_offset = 0;
	int		num, n;

	sql = zbx_malloc(sql, sql_alloc);

	esc = DBdyn_escape_string(itemkey);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 192 + strlen(esc),
			"select i.itemid"
			" from items i,hosts_groups hg,hosts h,groups g"
			" where hg.groupid=g.groupid"
				" and i.hostid=h.hostid"
				" and hg.hostid=h.hostid"
				" and i.key_='%s'"
				" and i.status=%d"
				" and h.status=%d",
			esc,
			ITEM_STATUS_ACTIVE,
			HOST_STATUS_MONITORED);

	zbx_free(esc);

	num = num_param(groups);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 32,
			" and g.name in (");

	for (n = 1; n <= num; n++)
	{
		if (NULL == (group = get_param_dyn(groups, n)))
			continue;

		esc = DBdyn_escape_string(group);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 3 + strlen(esc),
				"'%s'", esc);
		
		if (n != num)
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 2, ",");

		zbx_free(esc);
		zbx_free(group);
	}

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 128,
			")" DB_NODE, DBnode_local("h.hostid"));

	result = DBselect("%s", sql);

	zbx_free(sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(itemid, row[0]);
		uint64_array_add(ids, ids_alloc, ids_num, itemid, 64);
	}
	DBfree_result(result);
}

/*
 * grpfunc: grpavg, grpmax, grpmin, grpsum
 * itemfunc: avg, count, last, max, min, sum
 */
static int	evaluate_aggregate(AGENT_RESULT *res, char *grpfunc,
		const char *groups, const char *itemkey,
		const char *itemfunc, const char *param)
{
	const char	*__function_name = "evaluate_aggregate";
	char		*sql = NULL;
	int		sql_alloc = 1024, sql_offset = 0;
	zbx_uint64_t	itemid, *ids = NULL;
	int		ids_alloc = 0, ids_num = 0;

	DB_RESULT	result;
	DB_ROW		row;

	unsigned char	value_type;
	double		d = 0;
	int		num = 0;
	int		item_func, grp_func;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() grpfunc:'%s' groups:'%s' itemkey:'%s' function:'%s(%s)'",
			__function_name, grpfunc, groups, itemkey, itemfunc, param);

	if (0 == strcmp(grpfunc, "grpmin"))
		grp_func = ZBX_GRP_FUNC_MIN;
	else if (0 == strcmp(grpfunc, "grpavg"))
		grp_func = ZBX_GRP_FUNC_AVG;
	else if (0 == strcmp(grpfunc, "grpmax"))
		grp_func = ZBX_GRP_FUNC_MAX;
	else if (0 == strcmp(grpfunc, "grpsum"))
		grp_func = ZBX_GRP_FUNC_SUM;
	else
	{
		SET_MSG_RESULT(res, zbx_strdup(NULL, "unsupported group function"));
		goto clean;
	}

	if (0 == strcmp(itemfunc, "min"))
		item_func = ZBX_DB_GET_HIST_MIN;
	else if (0 == strcmp(itemfunc, "avg"))
		item_func = ZBX_DB_GET_HIST_AVG;
	else if (0 == strcmp(itemfunc, "max"))
		item_func = ZBX_DB_GET_HIST_MAX;
	else if (0 == strcmp(itemfunc, "sum"))
		item_func = ZBX_DB_GET_HIST_SUM;
	else if (0 == strcmp(itemfunc, "count"))
		item_func = ZBX_DB_GET_HIST_COUNT;
	else if (0 == strcmp(itemfunc, "last"))
		item_func = ZBX_DB_GET_HIST_VALUE;
	else
	{
		SET_MSG_RESULT(res, zbx_strdup(NULL, "unsupported item function"));
		goto clean;
	}

	aggregate_get_items(&ids, &ids_alloc, &ids_num, groups, itemkey);

	if (0 == ids_num)
	{
		SET_MSG_RESULT(res, zbx_dsprintf(NULL, "No items for key [%s] in group(s) [%s]", itemkey, groups));
		goto clean;
	}

	sql = zbx_malloc(sql, sql_alloc);

	if (ZBX_DB_GET_HIST_VALUE == item_func)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 256,
				"select value_type,lastvalue"
				" from items"
				" where lastvalue is not null"
					" and value_type in (%d,%d)"
					" and",
				ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", ids, ids_num);

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			value_type = (unsigned char)atoi(row[0]);

			evaluate_one(&d, &num, grp_func, row[1], value_type);
		}
		DBfree_result(result);
	}
	else
	{
		int	clock_from;
		char	**h_value;

		clock_from = time(NULL) - atoi(param);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 256,
				"select itemid,value_type"
				" from items"
				" where value_type in (%d,%d)"
					" and",
				ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", ids, ids_num);

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(itemid, row[0]);
			value_type = (unsigned char)atoi(row[1]);

			h_value = DBget_history(itemid, value_type, item_func, clock_from, 0, NULL, 0);

			if (NULL != h_value[0])
				evaluate_one(&d, &num, grp_func, h_value[0], value_type);
			DBfree_history(h_value);
		}
		DBfree_result(result);
	}

	if (0 == num)
	{
		SET_MSG_RESULT(res, zbx_dsprintf(NULL, "No values for key [%s] in group [%s]", itemkey, groups));
		goto clean;
	}

	if (0 == strcmp(grpfunc, "grpavg"))
		d = d / num;

	SET_DBL_RESULT(res, d);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() result:" ZBX_FS_DBL, __function_name, d);

	ret = SUCCEED;
clean:
	zbx_free(ids);
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
	char		key[8], params[MAX_STRING_LEN],
			groups[MAX_STRING_LEN], itemkey[MAX_STRING_LEN], func[8], funcp[32];
	int		ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s'", __function_name, item->key_orig);

	init_result(result);

	if (2 != parse_command(item->key, key, sizeof(key), params, sizeof(params)))
		return NOTSUPPORTED;

	if (num_param(params) != 4)
		return NOTSUPPORTED;

	if (0 != get_param(params, 1, groups, sizeof(groups)))
		return NOTSUPPORTED;

	if (0 != get_param(params, 2, itemkey, sizeof(itemkey)))
		return NOTSUPPORTED;

	if (0 != get_param(params, 3, func, sizeof(func)))
		return NOTSUPPORTED;

	if (0 != get_param(params, 4, funcp, sizeof(funcp)))
		return NOTSUPPORTED;

	if (SUCCEED != evaluate_aggregate(result, key, groups, itemkey, func, funcp))
		ret = NOTSUPPORTED;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
