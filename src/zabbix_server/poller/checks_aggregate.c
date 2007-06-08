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
#include "checks_aggregate.h"

static	int	evaluate_one(double *result, int *num, char *grpfunc, char const *value_str, int valuetype)
{
	int	ret = SUCCEED;
	double	value;

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
		zabbix_log( LOG_LEVEL_WARNING, "Unsupported group function [%s])",
			grpfunc);
		ret = FAIL;
	}

	return ret;
}

/*
 * grpfunc: grpmax, grpmin, grpsum, grpavg
 * itemfunc: last, min, max, avg, sum,count
 */
static int	evaluate_aggregate(AGENT_RESULT *res,char *grpfunc, char *hostgroup, char *itemkey, char *itemfunc, char *param)
{
	char		sql[MAX_STRING_LEN];
	char		sql2[MAX_STRING_LEN];
	char		hostgroup_esc[MAX_STRING_LEN],itemkey_esc[MAX_STRING_LEN];
 
	DB_RESULT	result;
	DB_ROW		row;

	int		valuetype;
	double		d = 0;
	const		char		*value;
	int		num = 0;
	int		now;
	char		items[MAX_STRING_LEN],items2[MAX_STRING_LEN];

	now=time(NULL);

	zabbix_log( LOG_LEVEL_DEBUG, "In evaluate_aggregate('%s','%s','%s','%s','%s')",
		grpfunc,
		hostgroup,
		itemkey,
		itemfunc,
		param);

	init_result(res);

	DBescape_string(itemkey,itemkey_esc,MAX_STRING_LEN);
	DBescape_string(hostgroup,hostgroup_esc,MAX_STRING_LEN);
/* Get list of affected item IDs */
	strscpy(items,"0");
	result = DBselect("select itemid from items i,hosts_groups hg,hosts h,groups g where hg.groupid=g.groupid and i.hostid=h.hostid and hg.hostid=h.hostid and g.name='%s' and i.key_='%s' and i.status=%d and h.status=%d and" ZBX_COND_NODEID,
		hostgroup_esc,
		itemkey_esc,
		ITEM_STATUS_ACTIVE,
		HOST_STATUS_MONITORED,
		LOCAL_NODE("h.hostid"));

	while((row=DBfetch(result)))
	{
		zbx_snprintf(items2,sizeof(items2),"%s,%s",
			items,
			row[0]);
/*		zabbix_log( LOG_LEVEL_WARNING, "ItemIDs items2[%s])",items2);*/
		strscpy(items,items2);
/*		zabbix_log( LOG_LEVEL_WARNING, "ItemIDs items[%s])",items2);*/
	}
	DBfree_result(result);

	if(strcmp(itemfunc,"last") == 0)
	{
		zbx_snprintf(sql,sizeof(sql),"select itemid,value_type,lastvalue from items where lastvalue is not NULL and items.itemid in (%s)",
			items);
		zbx_snprintf(sql2,sizeof(sql2),"select itemid,value_type,lastvalue from items where 0=1");
	}
		/* The SQL works very very slow on MySQL 4.0. That's why it has been split into two. */
/*		zbx_snprintf(sql,sizeof(sql),"select items.itemid,items.value_type,min(history.value) from items,hosts_groups,hosts,groups,history where history.itemid=items.itemid and hosts_groups.groupid=groups.groupid and items.hostid=hosts.hostid and hosts_groups.hostid=hosts.hostid and groups.name='%s' and items.key_='%s' and history.clock>%d group by 1,2",hostgroup_esc, itemkey_esc, now - atoi(param));*/
	else if( (strcmp(itemfunc,"min") == 0) ||
		(strcmp(itemfunc,"max") == 0) ||
		(strcmp(itemfunc,"avg") == 0) ||
		(strcmp(itemfunc,"count") == 0) ||
		(strcmp(itemfunc,"sum") == 0)
	)
	{
		zbx_snprintf(sql,sizeof(sql),"select h.itemid,i.value_type,%s(h.value) from items i,history h where h.itemid=i.itemid and h.itemid in (%s) and h.clock>%d group by h.itemid,i.value_type",
			itemfunc,
			items,
			now - atoi(param));
		zbx_snprintf(sql2,sizeof(sql),"select h.itemid,i.value_type,%s(h.value) from items i,history_uint h where h.itemid=i.itemid and h.itemid in (%s) and h.clock>%d group by h.itemid,i.value_type",
			itemfunc,
			items,
			now - atoi(param));
	}
	else
	{
		zabbix_log( LOG_LEVEL_WARNING, "Unsupported item function [%s])",
			itemfunc);
		return FAIL;
	}
	zabbix_log( LOG_LEVEL_DEBUG, "SQL [%s]",sql);
	zabbix_log( LOG_LEVEL_DEBUG, "SQL2 [%s]",sql2);

	result = DBselect(sql);
	while((row=DBfetch(result)))
	{
		valuetype = atoi(row[1]);
		value = row[2];
		if(FAIL == evaluate_one(&d, &num, grpfunc, value, valuetype))
		{
			zabbix_log( LOG_LEVEL_WARNING, "Unsupported group function [%s])",
				grpfunc);
			DBfree_result(result);
			return FAIL;
		}
	}
	DBfree_result(result);

	result = DBselect(sql2);
	while((row=DBfetch(result)))
	{
		valuetype = atoi(row[1]);
		value = row[2];
		if(FAIL == evaluate_one(&d, &num, grpfunc, value, valuetype))
		{
			zabbix_log( LOG_LEVEL_WARNING, "Unsupported group function [%s])",
				grpfunc);
			DBfree_result(result);
			return FAIL;
		}
	}
	DBfree_result(result);

	if(num==0)
	{
		zabbix_log( LOG_LEVEL_WARNING, "No values for group[%s] key[%s])",
			hostgroup,
			itemkey);
		return FAIL;
	}

	if(strcmp(grpfunc,"grpavg") == 0)
	{
		SET_DBL_RESULT(res, d/num);
	}
	else
	{
		SET_DBL_RESULT(res, d);
	}

	zabbix_log( LOG_LEVEL_DEBUG, "End evaluate_aggregate(result:" ZBX_FS_DBL ")",
		d);
	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: get_value_aggregate                                              *
 *                                                                            *
 * Purpose: retrieve data from ZABBIX server (aggregate items)                *
 *                                                                            *
 * Parameters: item - item we are interested in                               *
 *                                                                            *
 * Return value: SUCCEED - data succesfully retrieved and stored in result    *
 *                         and result_str (as string)                         *
 *               NOTSUPPORTED - requested item is not supported               *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	get_value_aggregate(DB_ITEM *item, AGENT_RESULT *result)
{
	char	function_grp[MAX_STRING_LEN];
	char	key[MAX_STRING_LEN];
	char	group[MAX_STRING_LEN];
	char	itemkey[MAX_STRING_LEN];
	char	function_item[MAX_STRING_LEN];
	char	parameter[MAX_STRING_LEN];
	char	*p,*p2;

	int 	ret = SUCCEED;


	zabbix_log( LOG_LEVEL_DEBUG, "In get_value_aggregate([%s])",
		item->key);

	init_result(result);

	strscpy(key, item->key);
	if((p=strstr(key,"(")) != NULL)
	{
		*p=0;
		strscpy(function_grp,key);
		*p='(';
		p++;
	}
	else	ret = NOTSUPPORTED;

	if(ret == SUCCEED)
	{
		if((p2=strstr(p,"'")) != NULL)
		{
			p2++;
		}
		else	ret = NOTSUPPORTED;

		if((ret == SUCCEED) && (p=strstr(p2,"'")) != NULL)
		{
			*p=0;
			strscpy(group,p2);
			*p='\'';
			p++;
		}
		else	ret = NOTSUPPORTED;
	}

	if(*p != ',')	ret = NOTSUPPORTED;

	if(ret == SUCCEED)
	{
		if((p2=strstr(p,"'")) != NULL)
		{
			p2++;
		}
		else	ret = NOTSUPPORTED;

		if((ret == SUCCEED) && (p=strstr(p2,"'")) != NULL)
		{
			*p=0;
			strscpy(itemkey,p2);
			*p='\'';
			p++;
		}
		else	ret = NOTSUPPORTED;
	}

	if(*p != ',')	ret = NOTSUPPORTED;

	if(ret == SUCCEED)
	{
		if((p2=strstr(p,"'")) != NULL)
		{
			p2++;
		}
		else	ret = NOTSUPPORTED;

		if((ret == SUCCEED) && (p=strstr(p2,"'")) != NULL)
		{
			*p=0;
			strscpy(function_item,p2);
			*p='\'';
			p++;
		}
		else	ret = NOTSUPPORTED;
	}

	if(*p != ',')	ret = NOTSUPPORTED;

	if(ret == SUCCEED)
	{
		if((p2=strstr(p,"'")) != NULL)
		{
			p2++;
			zabbix_log( LOG_LEVEL_DEBUG, "p2[%s]",p2);
		}
		else	ret = NOTSUPPORTED;

		if((ret == SUCCEED) && (p=strstr(p2,"'")) != NULL)
		{
			*p=0;
			strscpy(parameter,p2);
			*p='\'';
			p++;
		}
		else	ret = NOTSUPPORTED;
	}

	zabbix_log( LOG_LEVEL_DEBUG, "Evaluating aggregate[%s] grpfunc[%s] group[%s] itemkey[%s] itemfunc [%s] parameter [%s]",
		item->key,
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
