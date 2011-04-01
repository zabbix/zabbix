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

#define ZBX_SYSLOG_MODE_NORMAL	0
#define ZBX_SYSLOG_MODE_MASS	1
static char	**zbx_syslog = NULL;
static int	zbx_syslog_alloc = 0, zbx_syslog_num = 0, zbx_syslog_mode = ZBX_SYSLOG_MODE_NORMAL;

void	init_mass_zabbix_syslog()
{
	zbx_syslog_mode = ZBX_SYSLOG_MODE_MASS;
}

void	flush_mass_zabbix_syslog()
{
	const char	*__function_name = "flush_mass_zabbix_syslog";
	int		i, s, num, now;
	AGENT_RESULT	agent;
	DC_ITEM		*items = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() zbx_syslog_num:%d", __function_name, zbx_syslog_num);

	if (NULL == zbx_syslog)
		goto out;

	init_result(&agent);

	num = DCconfig_get_items(0, SERVER_ZABBIXLOG_KEY, &items);

	for (s = 0; s < zbx_syslog_num; s++)
	{
		now = (int)time(NULL);

		SET_STR_RESULT(&agent, zbx_syslog[s]);

		for (i = 0; i < num; i++)
			dc_add_history(items[i].itemid, items[i].value_type, &agent, now, 0, NULL, 0, 0, 0, 0);

		free_result(&agent);
	}

	zbx_free(items);

	zbx_free(zbx_syslog);
	zbx_syslog_alloc = 0;
	zbx_syslog_num = 0;
	zbx_syslog_mode = ZBX_SYSLOG_MODE_NORMAL;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

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
	const char	*__function_name = "zabbix_syslog";
	char		value_str[MAX_STRING_LEN];
	va_list		ap;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	/* this is made to disable writing to database for watchdog */
	if (0 == CONFIG_ENABLE_LOG)
		return;

	va_start(ap, fmt);
	vsnprintf(value_str, sizeof(value_str), fmt, ap);
	va_end(ap);

	if (ZBX_SYSLOG_MODE_MASS == zbx_syslog_mode)
	{
		if (zbx_syslog_num == zbx_syslog_alloc)
		{
			zbx_syslog_alloc += 16;
			zbx_syslog = zbx_realloc(zbx_syslog, zbx_syslog_alloc * sizeof(char *));
		}

		zbx_syslog[zbx_syslog_num++] = zbx_strdup(NULL, value_str);
	}
	else
	{
		zbx_syslog_alloc = 1;
		zbx_syslog = zbx_malloc(zbx_syslog, zbx_syslog_alloc * sizeof(char *));
		zbx_syslog[zbx_syslog_num++] = zbx_strdup(NULL, value_str);

		flush_mass_zabbix_syslog();
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}
