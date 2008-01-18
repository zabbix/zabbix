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


#include <stdio.h>
#include <string.h>
#include <stdarg.h>
#include <syslog.h>

#include <sys/types.h>
#include <sys/stat.h>
#include <unistd.h>

#include <time.h>

#include "common.h"
#include "functions.h"
#include "log.h"
#include "zlog.h"

/******************************************************************************
 *                                                                            *
 * Function: zabbix_syslog                                                    *
 *                                                                            *
 * Purpose: save internal warning or error message in item zabbix[log]        *
 *                                                                            *
 * Parameters: va_list arguments                                              *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: do nothing if no zabbix[log] items                               *
 *                                                                            *
 ******************************************************************************/
void __zbx_zabbix_syslog(const char *fmt, ...)
{ 
	va_list		ap;
	char		value_str[MAX_STRING_LEN];

	DB_ITEM		item;
	DB_RESULT	result;
	DB_ROW	row;

	AGENT_RESULT	agent;

	zabbix_log(LOG_LEVEL_DEBUG, "In zabbix_log()");

	/* This is made to disable writing to database for watchdog */
	if(CONFIG_ENABLE_LOG == 0)	return;

	result = DBselect("select %s where h.hostid=i.hostid and i.key_='%s' and i.value_type=%d and" ZBX_COND_NODEID,
		ZBX_SQL_ITEM_SELECT,
		SERVER_ZABBIXLOG_KEY,
		ITEM_VALUE_TYPE_STR,
		LOCAL_NODE("h.hostid"));

	while((row=DBfetch(result)))
	{
		DBget_item_from_db(&item,row);

		va_start(ap,fmt);
		vsnprintf(value_str,sizeof(value_str),fmt,ap);
		value_str[MAX_STRING_LEN-1]=0;
		va_end(ap);

		init_result(&agent);
		SET_STR_RESULT(&agent, strdup(value_str));
		process_new_value(&item,&agent);
		free_result(&agent);

		update_triggers(item.itemid);
	}

	DBfree_result(result);
}
