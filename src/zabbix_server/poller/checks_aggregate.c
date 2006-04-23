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

/*
 * grpfunc: grpmax, grpmin, grpsum, grpavg
 * itemfunc: last, min, max, avg, sum
 */
static int	evaluate_aggregate(AGENT_RESULT *res,char *grpfunc, char *hostgroup, char *itemkey, char *itemfunc, char *param)
{
	char		sql[MAX_STRING_LEN];
	char		hostgroup_esc[MAX_STRING_LEN],itemkey_esc[MAX_STRING_LEN];
 
	DB_RESULT	*result;

	int		i,valuetype;
	double		d = 0, value;
	int		num = 0;
	int		now;
	char		items[MAX_STRING_LEN],items2[MAX_STRING_LEN];

	now=time(NULL);

	zabbix_log( LOG_LEVEL_WARNING, "In evaluate_aggregate('%s','%s','%s','%s','%s')",grpfunc,hostgroup,itemkey,itemfunc,param);
	init_result(res);

	DBescape_string(itemkey,itemkey_esc,MAX_STRING_LEN);
	DBescape_string(hostgroup,hostgroup_esc,MAX_STRING_LEN);
/* Get list of affected item IDs */
	strscpy(items,"0");
	snprintf(sql,sizeof(sql)-1,"select itemid from items,hosts_groups,hosts,groups where hosts_groups.groupid=groups.groupid and items.hostid=hosts.hostid and hosts_groups.hostid=hosts.hostid and groups.name='%s' and items.key_='%s'",hostgroup_esc, itemkey_esc);
	result = DBselect(sql);
	
	for(i=0;i<DBnum_rows(result);i++)
	{
		snprintf(items2,sizeof(items2)-1,"%s,%s",items, DBget_field(result,i,0));
/*		zabbix_log( LOG_LEVEL_WARNING, "ItemIDs items2[%s])",items2);*/
		strscpy(items,items2);
/*		zabbix_log( LOG_LEVEL_WARNING, "ItemIDs items[%s])",items2);*/
	}
	DBfree_result(result);
	zabbix_log( LOG_LEVEL_WARNING, "ItemIDs ALL [%s])",items);

	if(strcmp(itemfunc,"last") == 0)
	{
		snprintf(sql,sizeof(sql)-1,"select itemid,value_type,lastvalue from items where lastvalue is not NULL and items.itemid in (%s)",items);
	}
	else if(strcmp(itemfunc,"min") == 0)
	{
		/* The SQL works very very slow on MySQL 4.0. That's why it has been split into two. */
/*		snprintf(sql,sizeof(sql)-1,"select items.itemid,items.value_type,min(history.value) from items,hosts_groups,hosts,groups,history where history.itemid=items.itemid and hosts_groups.groupid=groups.groupid and items.hostid=hosts.hostid and hosts_groups.hostid=hosts.hostid and groups.name='%s' and items.key_='%s' and history.clock>%d group by 1,2",hostgroup_esc, itemkey_esc, now - atoi(param));*/
		snprintf(sql,sizeof(sql)-1,"select history.itemid,items.value_type,min(history.value) from items,history where history.itemid=items.itemid and history.itemid in (%s) and history.clock>%d group by 1,2",items, now - atoi(param));
	}
	else
	{
		zabbix_log( LOG_LEVEL_WARNING, "No values for group[%s] key[%s])",hostgroup,itemkey);
		DBfree_result(result);
		return FAIL;
	}
	zabbix_log( LOG_LEVEL_WARNING, "SQL [%s]",sql);

	result = DBselect(sql);

	
	for(i=0;i<DBnum_rows(result);i++)
	{
		valuetype = atoi(DBget_field(result,i,1));
		if(valuetype == ITEM_VALUE_TYPE_FLOAT)
		{
			value = atof(DBget_field(result,i,2));
		}
		else if(valuetype == ITEM_VALUE_TYPE_UINT64)
		{
#ifdef HAVE_ATOLL
				value = (double)atoll(DBget_field(result,i,2));
#else
				value = (double)atol(DBget_field(result,i,2));
#endif
		}
		if(strcmp(grpfunc,"grpsum") == 0)
		{
			d+=value;
			num++;
		}
		else if(strcmp(grpfunc,"grpavg") == 0)
		{
			d+=value;
			num++;
		}
		else if(strcmp(grpfunc,"grpmin") == 0)
		{
			if(num==0)
			{
				d=value;
			}
			else if(value<d)
			{
				d=value;
			}
			num++;
		}
		else if(strcmp(grpfunc,"grpmax") == 0)
		{
			if(num==0)
			{
				d=value;
			}
			else if(value>d)
			{
				d=value;
			}
			num++;
		}
		zabbix_log( LOG_LEVEL_WARNING, "Item value([%s])",DBget_field(result,i,0));
	}

	DBfree_result(result);

	if(num==0)
	{
		zabbix_log( LOG_LEVEL_WARNING, "No values for group[%s] key[%s])",hostgroup,itemkey);
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

	zabbix_log( LOG_LEVEL_WARNING, "Result([%f])",d);
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


	zabbix_log( LOG_LEVEL_WARNING, "In get_value_aggregate([%s])",item->key);

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
			zabbix_log( LOG_LEVEL_WARNING, "p2[%s]",p2);
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

	zabbix_log( LOG_LEVEL_WARNING, "Evaluating aggregate[%s] grpfunc[%s] group[%s] itemkey[%s] itemfunc [%s] parameter [%s]",item->key, function_grp, group, itemkey, function_item, parameter);

	if( (ret == SUCCEED) &&
		(evaluate_aggregate(result,function_grp, group, itemkey, function_item, parameter) != SUCCEED)
	)
	{
		ret = NOTSUPPORTED;
	}

	return ret;
}
