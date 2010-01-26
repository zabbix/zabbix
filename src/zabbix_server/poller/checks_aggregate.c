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

static	int	evaluate_one(double *result, int *num, char *grpfunc, char const *value_str, int valuetype)
{
	int	ret = SUCCEED;
	double	value = 0;

	if(valuetype == ITEM_VALUE_TYPE_FLOAT)
	{
		value = zbx_atod(value_str);
	}
	else if(valuetype == ITEM_VALUE_TYPE_UINT64)
	{
		value = (double)zbx_atoui64(value_str);
	}

	if(strcmp(grpfunc,"grpsum") == 0)
	{
		*result+=value;
		*num+=1;
	}
	else if(strcmp(grpfunc,"grpavg") == 0)
	{
		*result+=value;
		*num+=1;
	}
	else if(strcmp(grpfunc,"grpmin") == 0)
	{
		if(*num==0)
		{
			*result=value;
		}
		else if(value<*result)
		{
			*result=value;
		}
		*num+=1;
	}
	else if(strcmp(grpfunc,"grpmax") == 0)
	{
		if(*num==0)
		{
			*result=value;
		}
		else if(value>*result)
		{
			*result=value;
		}
		*num+=1;
	}
	else
	{
		ret = FAIL;
	}

	return ret;
}

/*
 * get array of items with specified key for selected group
 */
static void	aggregate_get_items(zbx_uint64_t **ids, int *ids_alloc, int *ids_num, const char *hostgroup, const char *itemkey)
{
	char		*hostgroup_esc, *itemkey_esc;
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	itemid;

	hostgroup_esc = DBdyn_escape_string(hostgroup);
	itemkey_esc = DBdyn_escape_string(itemkey);

	result = DBselect(
			"select i.itemid"
			" from items i,hosts_groups hg,hosts h,groups g"
			" where hg.groupid=g.groupid"
				" and i.hostid=h.hostid"
				" and hg.hostid=h.hostid"
				" and g.name='%s'"
				" and i.key_='%s'"
				" and i.status=%d"
				" and h.status=%d"
				DB_NODE,
			hostgroup_esc,
			itemkey_esc,
			ITEM_STATUS_ACTIVE,
			HOST_STATUS_MONITORED,
			DBnode_local("h.hostid"));

	zbx_free(itemkey_esc);
	zbx_free(hostgroup_esc);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(itemid, row[0]);
		uint64_array_add(ids, ids_alloc, ids_num, itemid, 64);
	}
	DBfree_result(result);
}

/*
 * grpfunc: grpmax, grpmin, grpsum, grpavg
 * itemfunc: last, min, max, avg, sum,count
 */
static int	evaluate_aggregate(AGENT_RESULT *res,char *grpfunc,
		const char *hostgroup, const char *itemkey,
		const char *itemfunc, const char *param)
{
	char		*sql = NULL;
	int		sql_alloc = 2048, sql_offset = 0;
	char		*sql2 = NULL;
	int		sql2_alloc = 2048, sql2_offset = 0;

	DB_RESULT	result;
	DB_ROW		row;

	int		valuetype;
	double		d = 0;
	const		char		*value;
	int		num = 0;
	int		now;
	zbx_uint64_t	*ids = NULL;
	int		ids_alloc = 0, ids_num = 0;
	int		ret = FAIL;

	now=time(NULL);

	zabbix_log( LOG_LEVEL_DEBUG, "In evaluate_aggregate('%s','%s','%s','%s','%s')",
		grpfunc,
		hostgroup,
		itemkey,
		itemfunc,
		param);

	init_result(res);

	aggregate_get_items(&ids, &ids_alloc, &ids_num, hostgroup, itemkey);

	if (0 == ids_num)
	{
		SET_MSG_RESULT(res, zbx_dsprintf(NULL, "No items for key [%s] in group [%s]",
				itemkey, hostgroup));
		goto clean;
	}

	sql = zbx_malloc(sql, sql_alloc);
	sql2 = zbx_malloc(sql2, sql2_alloc);

	if (0 == strcmp(itemfunc, "last"))
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 128,
				"select itemid,value_type,lastvalue"
				" from items"
				" where lastvalue is not NULL"
					" and");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", ids, ids_num);

		zbx_snprintf_alloc(&sql2, &sql2_alloc, &sql2_offset, 128,
				"select itemid,value_type,lastvalue"
				" from items"
				" where 0=1");
	}
		/* The SQL works very very slow on MySQL 4.0. That's why it has been split into two. */
/*		zbx_snprintf(sql,sizeof(sql),"select items.itemid,items.value_type,min(history.value) from items,hosts_groups,hosts,groups,history where history.itemid=items.itemid and hosts_groups.groupid=groups.groupid and items.hostid=hosts.hostid and hosts_groups.hostid=hosts.hostid and groups.name='%s' and items.key_='%s' and history.clock>%d group by 1,2",hostgroup_esc, itemkey_esc, now - atoi(param));*/
	else if (0 == strcmp(itemfunc,"min") || 0 == strcmp(itemfunc,"max") ||
			0 == strcmp(itemfunc,"avg") || 0 == strcmp(itemfunc,"count") ||
			0 == strcmp(itemfunc,"sum"))
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 128,
				"select i.itemid,i.value_type,%s(h.value)"
				" from items i,history h"
				" where h.itemid=i.itemid"
					" and h.clock>%d"
					" and",
				itemfunc,
				now - atoi(param));
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.itemid", ids, ids_num);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 64,
				" group by i.itemid,i.value_type");

		zbx_snprintf_alloc(&sql2, &sql2_alloc, &sql2_offset, 128,
				"select i.itemid,i.value_type,%s(h.value)"
				" from items i,history_uint h"
				" where h.itemid=i.itemid"
					" and h.clock>%d"
					" and",
				itemfunc,
				now - atoi(param));
		DBadd_condition_alloc(&sql2, &sql2_alloc, &sql2_offset, "i.itemid", ids, ids_num);
		zbx_snprintf_alloc(&sql2, &sql2_alloc, &sql2_offset, 64,
				" group by i.itemid,i.value_type");
	}
	else
	{
		SET_MSG_RESULT(res, strdup("Unsupported item function"));
		goto clean;
	}

	result = DBselect("%s",sql);
	while((row=DBfetch(result)))
	{
		valuetype = atoi(row[1]);
		value = row[2];
		if(FAIL == evaluate_one(&d, &num, grpfunc, value, valuetype))
		{
			SET_MSG_RESULT(res, strdup("Unsupported group function"));
			DBfree_result(result);
			goto clean;
		}
	}
	DBfree_result(result);

	result = DBselect("%s",sql2);
	while((row=DBfetch(result)))
	{
		valuetype = atoi(row[1]);
		value = row[2];
		if(FAIL == evaluate_one(&d, &num, grpfunc, value, valuetype))
		{
			SET_MSG_RESULT(res, strdup("Unsupported group function"));
			DBfree_result(result);
			goto clean;
		}
	}
	DBfree_result(result);

	if(num==0)
	{
		SET_MSG_RESULT(res, zbx_dsprintf(NULL, "No values for key [%s] in group [%s]",
				itemkey, hostgroup));
		goto clean;
	}

	if(strcmp(grpfunc,"grpavg") == 0)
	{
		SET_DBL_RESULT(res, d/num);
	}
	else
	{
		SET_DBL_RESULT(res, d);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "evaluate_aggregate() result:" ZBX_FS_DBL, d);

	ret = SUCCEED;
clean:
	zbx_free(ids);
	zbx_free(sql);
	zbx_free(sql2);

	zabbix_log( LOG_LEVEL_DEBUG, "End of evaluate_aggregate():%s", zbx_result_string(ret));

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
	char	function_grp[MAX_STRING_LEN];
	char	key[MAX_STRING_LEN];
	char	group[MAX_STRING_LEN];
	char	itemkey[MAX_STRING_LEN];
	char	function_item[MAX_STRING_LEN];
	char	parameter[MAX_STRING_LEN];
	char	*p,*p2;

	int	ret = SUCCEED;


	zabbix_log(LOG_LEVEL_DEBUG, "In get_value_aggregate() key:'%s'",
			item->key_orig);

	init_result(result);

	strscpy(key, item->key);
	if((p=strchr(key,'[')) != NULL)
	{
		*p=0;
		strscpy(function_grp,key);
		*p='[';
		p++;
	}
	else	ret = NOTSUPPORTED;

	if(ret == SUCCEED)
	{
		if((p2=strchr(p,'"')) != NULL)
		{
			p2++;
		}
		else	ret = NOTSUPPORTED;

		if((ret == SUCCEED) && (p=strchr(p2,'"')) != NULL)
		{
			*p=0;
			strscpy(group,p2);
			*p='"';
			p++;
		}
		else	ret = NOTSUPPORTED;
	}

	if(ret == SUCCEED)
	{
		if(*p != ',')	ret = NOTSUPPORTED;
	}

	if(ret == SUCCEED)
	{
		if((p2=strchr(p,'"')) != NULL)
		{
			p2++;
		}
		else	ret = NOTSUPPORTED;

		if((ret == SUCCEED) && (p=strchr(p2,'"')) != NULL)
		{
			*p=0;
			strscpy(itemkey,p2);
			*p='"';
			p++;
		}
		else	ret = NOTSUPPORTED;
	}

	if(ret == SUCCEED)
	{
		if(*p != ',')	ret = NOTSUPPORTED;
	}

	if(ret == SUCCEED)
	{
		if((p2=strchr(p,'"')) != NULL)
		{
			p2++;
		}
		else	ret = NOTSUPPORTED;

		if((ret == SUCCEED) && (p=strchr(p2,'"')) != NULL)
		{
			*p=0;
			strscpy(function_item,p2);
			*p='"';
			p++;
		}
		else	ret = NOTSUPPORTED;
	}

	if(ret == SUCCEED)
	{
		if(*p != ',')	ret = NOTSUPPORTED;
	}

	if(ret == SUCCEED)
	{
		if((p2=strchr(p,'"')) != NULL)
		{
			p2++;
		}
		else	ret = NOTSUPPORTED;

		if((ret == SUCCEED) && (p=strchr(p2,'"')) != NULL)
		{
			*p=0;
			strscpy(parameter,p2);
			*p='"';
			p++;
		}
		else	ret = NOTSUPPORTED;
	}

	zabbix_log( LOG_LEVEL_DEBUG, "Evaluating aggregate[%s] grpfunc[%s] group[%s] itemkey[%s] itemfunc [%s] parameter [%s]",
		item->key_orig,
		function_grp,
		group,
		itemkey,
		function_item,
		parameter);

	if( (ret == SUCCEED) &&
		(evaluate_aggregate(result,function_grp, group, itemkey, function_item, parameter) != SUCCEED)
	)
	{
		ret = NOTSUPPORTED;
	}

	return ret;
}
