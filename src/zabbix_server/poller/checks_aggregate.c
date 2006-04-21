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
	zbx_uint64_t	i;
	char	function_grp[MAX_STRING_LEN];
	char	key[MAX_STRING_LEN];
	char	*p;

	int 	ret = SUCCEED;


	zabbix_log( LOG_LEVEL_WARNING, "In get_value_affgregate([%s])",item->key);

	init_result(result);

	strscpy(key, item->key);
	p=strstr(key,"(");
	if(p!=NULL)
	{
		*p=0;
		strscpy(function_grp,key);
		*p='(';
		p++;
	}
	else	ret = NOTSUPPORTED;

	SET_UI64_RESULT(result, 0);

	zabbix_log( LOG_LEVEL_WARNING, "Evaluating aggregate [%s] grpfunc [%s]",item->key, function_grp);

/*	else
	{
		snprintf(error,MAX_STRING_LEN-1,"Internal check [%s] is not supported", item->key);
		zabbix_log( LOG_LEVEL_WARNING, error);
		SET_STR_RESULT(result, strdup(error));
		return NOTSUPPORTED;
	}*/

	return ret;
}
