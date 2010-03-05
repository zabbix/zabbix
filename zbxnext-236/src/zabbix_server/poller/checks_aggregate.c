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

static	int	evaluate_one(double *result, int *num, char *grpfunc, char const *value_str, unsigned char valuetype)
{
	int		ret = SUCCEED;
	double		value = 0;
	zbx_uint64_t	value_uint64;

	if (ITEM_VALUE_TYPE_FLOAT == valuetype)
		value = zbx_atod(value_str);
	else if (ITEM_VALUE_TYPE_UINT64 == valuetype)
	{
		ZBX_STR2UINT64(value_uint64, value_str);
		value = (double)value_uint64;
	}

	if (0 == strcmp(grpfunc, "grpsum") || 0 == strcmp(grpfunc,"grpavg"))
	{
		*result += value;
		*num += 1;
	}
	else if (0 == strcmp(grpfunc, "grpmin"))
	{
		if (0 == *num || value < *result)
			*result = value;
		*num += 1;
	}
	else if (0 == strcmp(grpfunc, "grpmax"))
	{
		if (0 == *num || value > *result)
			*result = value;
		*num += 1;
	}
	else
		ret = FAIL;

	return ret;
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

	esc = DBdyn_escape_string(itemkey);

	sql = zbx_malloc(sql, sql_alloc);

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
 * grpfunc: grpmax, grpmin, grpsum, grpavg
 * itemfunc: last, min, max, avg, sum, count
 */
static int	evaluate_aggregate(AGENT_RESULT *res, char *grpfunc,
		const char *groups, const char *itemkey,
		const char *itemfunc, const char *param)
{
	const char	*__function_name = "evaluate_aggregate";
	char		*sql = NULL;
	int		sql_alloc = 1024, sql_offset;
	zbx_uint64_t	*ids = NULL;
	int		ids_alloc = 0, ids_num = 0;

	DB_RESULT	result;
	DB_ROW		row;

	unsigned char	valuetype;
	double		d = 0;
	const		char		*value;
	int		num = 0;
	int		now;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() grpfunc:'%s' groups:'%s'"
			" itemkey:'%s' function:'%s(%s)'",
			__function_name, grpfunc, groups, itemkey,
			itemfunc, param);

	init_result(res);

	now = time(NULL);

	aggregate_get_items(&ids, &ids_alloc, &ids_num, groups, itemkey);

	if (0 == ids_num)
	{
		SET_MSG_RESULT(res, zbx_dsprintf(NULL, "No items for key [%s] in group(s) [%s]",
				itemkey, groups));
		goto clean;
	}

	sql = zbx_malloc(sql, sql_alloc);

	if (0 == strcmp(itemfunc, "last"))
	{
		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 256,
				"select itemid,value_type,lastvalue"
				" from items"
				" where lastvalue is not NULL"
					" and value_type in (%d,%d)"
					" and",
				ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", ids, ids_num);

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			valuetype = (unsigned char)atoi(row[1]);
			value = row[2];

			if (FAIL == evaluate_one(&d, &num, grpfunc, value, valuetype))
			{
				SET_MSG_RESULT(res, strdup("Unsupported group function"));
				DBfree_result(result);
				goto clean;
			}
		}
		DBfree_result(result);
	}
		/* The SQL works very very slow on MySQL 4.0. That's why it has been split into two. */
/*		zbx_snprintf(sql,sizeof(sql),"select items.itemid,items.value_type,min(history.value) from items,hosts_groups,hosts,groups,history where history.itemid=items.itemid and hosts_groups.groupid=groups.groupid and items.hostid=hosts.hostid and hosts_groups.hostid=hosts.hostid and groups.name='%s' and items.key_='%s' and history.clock>%d group by 1,2",hostgroup_esc, itemkey_esc, now - atoi(param));*/
	else if (0 == strcmp(itemfunc,"min") || 0 == strcmp(itemfunc,"max") ||
			0 == strcmp(itemfunc,"avg") || 0 == strcmp(itemfunc,"count") ||
			0 == strcmp(itemfunc,"sum"))
	{
		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 256,
				"select i.itemid,i.value_type,%s(h.value)"
				" from items i,history h"
				" where h.itemid=i.itemid"
					" and h.clock>%d"
					" and i.value_type=%d"
					" and",
				itemfunc,
				now - atoi(param),
				ITEM_VALUE_TYPE_FLOAT);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.itemid", ids, ids_num);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 64,
				" group by i.itemid,i.value_type");

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			valuetype = (unsigned char)atoi(row[1]);
			value = row[2];

			if (FAIL == evaluate_one(&d, &num, grpfunc, value, valuetype))
			{
				SET_MSG_RESULT(res, strdup("Unsupported group function"));
				DBfree_result(result);
				goto clean;
			}
		}
		DBfree_result(result);

		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 256,
				"select i.itemid,i.value_type,%s(h.value)"
				" from items i,history_uint h"
				" where h.itemid=i.itemid"
					" and h.clock>%d"
					" and i.value_type=%d"
					" and",
				itemfunc,
				now - atoi(param),
				ITEM_VALUE_TYPE_UINT64);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.itemid", ids, ids_num);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 64,
				" group by i.itemid,i.value_type");

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			valuetype = (unsigned char)atoi(row[1]);
			value = row[2];

			if (FAIL == evaluate_one(&d, &num, grpfunc, value, valuetype))
			{
				SET_MSG_RESULT(res, strdup("Unsupported group function"));
				DBfree_result(result);
				goto clean;
			}
		}
		DBfree_result(result);
	}
	else
	{
		SET_MSG_RESULT(res, strdup("Unsupported item function"));
		goto clean;
	}

	if (0 == num)
	{
		SET_MSG_RESULT(res, zbx_dsprintf(NULL, "No values for key [%s] in group [%s]",
				itemkey, groups));
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
 * Purpose: retrieve data from ZABBIX server (aggregate items)                *
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
	char		key[8], params[MAX_STRING_LEN], groups[MAX_STRING_LEN],
			itemkey[MAX_STRING_LEN],
			func[8], funcp[32];
	int		ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s'", __function_name,
			item->key_orig);

	init_result(result);

	if (2/* command with parameters */ != parse_command(item->key, key,
				sizeof(key), params, sizeof(params)))
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

	return ret;
}
