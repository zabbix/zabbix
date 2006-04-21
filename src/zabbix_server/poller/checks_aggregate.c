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
 
	DB_RESULT	*result;

	int		i;

	zabbix_log( LOG_LEVEL_WARNING, "In evaluate_aggregate('%s','%s','%s','%s','%s')",grpfunc,hostgroup,itemkey,itemfunc,param);

	if(strcmp(itemfunc,"last") == 0)
	{
		snprintf(sql,sizeof(sql)-1,"select items.lastvalue,items.value_type from items,hosts_groups,hosts,groups where groups.groupid=1 and hosts_groups.groupid=groups.groupid and items.hostid=hosts.hostid and hosts_groups.hostid=hosts.hostid and items.lastvalue!=NULL and groups.name='%s'",hostgroup);
	}
	zabbix_log( LOG_LEVEL_WARNING, "SQL [%s]",sql);

	result = DBselect(sql);
	
	for(i=0;i<DBnum_rows(result);i++)
	{
		zabbix_log( LOG_LEVEL_WARNING, "Item value([%s])",DBget_field(result,i,0));
	}

	DBfree_result(result);

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

	SET_UI64_RESULT(result, 0);

	zabbix_log( LOG_LEVEL_WARNING, "Evaluating aggregate[%s] grpfunc[%s] group[%s] itemkey[%s] itemfunc [%s] parameter [%s]",item->key, function_grp, group, itemkey, function_item, parameter);

	evaluate_aggregate(result,function_grp, group, itemkey, function_item, parameter);

	return ret;
}
