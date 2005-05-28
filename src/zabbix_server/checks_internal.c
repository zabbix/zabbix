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

int	get_value_internal(double *result,char *result_str,DB_ITEM *item,char *error,int max_error_len)
{
	if(strcmp(item->key,"zabbix[triggers]")==0)
	{
		*result=DBget_triggers_count();
	}
	else if(strcmp(item->key,"zabbix[items]")==0)
	{
		*result=DBget_items_count();
	}
	else if(strcmp(item->key,"zabbix[items_unsupported]")==0)
	{
		*result=DBget_items_unsupported_count();
	}
	else if(strcmp(item->key,"zabbix[history]")==0)
	{
		*result=DBget_history_count();
	}
	else if(strcmp(item->key,"zabbix[history_str]")==0)
	{
		*result=DBget_history_str_count();
	}
	else if(strcmp(item->key,"zabbix[trends]")==0)
	{
		*result=DBget_trends_count();
	}
	else if(strcmp(item->key,"zabbix[queue]")==0)
	{
		*result=DBget_queue_count();
	}
	else
	{
		zabbix_log( LOG_LEVEL_WARNING, "Internal check [%s] is not supported", item->key);
		snprintf(error,max_error_len-1,"Internal check [%s] is not supported", item->key);
		return NOTSUPPORTED;
	}

	snprintf(result_str,MAX_STRING_LEN-1,"%f",*result);

	zabbix_log( LOG_LEVEL_DEBUG, "INTERNAL [%s] [%f]", result_str, *result);
	return SUCCEED;
}
