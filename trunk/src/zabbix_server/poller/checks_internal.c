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
#include "checks_internal.h"

/******************************************************************************
 *                                                                            *
 * Function: get_value_internal                                               *
 *                                                                            *
 * Purpose: retrieve data from ZABBIX server (internally supported intems)    *
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
int	get_value_internal(DB_ITEM *item, AGENT_RESULT *result)
{
	zbx_uint64_t	i;
	char		error[MAX_STRING_LEN];

	init_result(result);

	if(strcmp(item->key,"zabbix[triggers]")==0)
	{
		i = (zbx_uint64_t)DBget_triggers_count();
		SET_UI64_RESULT(result, i);
	}
	else if(strcmp(item->key,"zabbix[items]")==0)
	{
		i = (zbx_uint64_t)DBget_items_count();
		SET_UI64_RESULT(result, i);
	}
	else if(strcmp(item->key,"zabbix[items_unsupported]")==0)
	{
		i=DBget_items_unsupported_count();
		SET_UI64_RESULT(result, i);
	}
	else if(strcmp(item->key,"zabbix[history]")==0)
	{
		i=DBget_history_count();
		SET_UI64_RESULT(result, i);
	}
	else if(strcmp(item->key,"zabbix[history_str]")==0)
	{
		i=DBget_history_str_count();
		SET_UI64_RESULT(result, i);
	}
	else if(strcmp(item->key,"zabbix[trends]")==0)
	{
		i=DBget_trends_count();
		SET_UI64_RESULT(result, i);
	}
	else if(strcmp(item->key,"zabbix[queue]")==0)
	{
		i=DBget_queue_count();
		SET_UI64_RESULT(result, i);
	}
	else
	{
		zbx_snprintf(error,sizeof(error),"Internal check [%s] is not supported", item->key);
		zabbix_log( LOG_LEVEL_WARNING, "%s", error);
		SET_STR_RESULT(result, strdup(error));
		return NOTSUPPORTED;
	}

	return SUCCEED;
}
