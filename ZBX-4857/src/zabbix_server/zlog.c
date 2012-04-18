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
#include "sysinfo.h"
#include "log.h"
#include "dbcache.h"

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
void	__zbx_zabbix_syslog(const char *fmt, ...)
{
	const char	*__function_name = "zabbix_log";
	va_list		ap;
	char		value_str[MAX_STRING_LEN];
	DC_ITEM		*items = NULL;
	int		i, num, now;
	AGENT_RESULT	agent;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	/* this is made to disable writing to database for watchdog */
	if (0 == CONFIG_ENABLE_LOG)
		return;

	init_result(&agent);

	now = (int)time(NULL);

	va_start(ap,fmt);
	zbx_vsnprintf(value_str, sizeof(value_str), fmt, ap);
	va_end(ap);

	SET_STR_RESULT(&agent, strdup(value_str));

	num = DCconfig_get_items(0, SERVER_ZABBIXLOG_KEY, &items);
	for (i = 0; i < num; i++)
		dc_add_history(items[i].itemid, items[i].value_type, &agent, now,
				ITEM_STATUS_ACTIVE, NULL, 0, NULL, 0, 0, 0, 0);

	zbx_free(items);
	free_result(&agent);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
