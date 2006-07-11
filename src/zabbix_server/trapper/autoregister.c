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

#include "config.h"

#include <stdio.h>
#include <stdlib.h>

#include <string.h>

#include "cfg.h"
#include "pid.h"
#include "db.h"
#include "log.h"
#include "zlog.h"

#include <sys/types.h>
#include <regex.h>

#include "common.h"
#include "autoregister.h"

static void	register_new_host(char *server, int host_templateid);

int	autoregister(char *server)
{
	DB_RESULT	result;
	DB_ROW		row;

	int	ret=SUCCEED;
	char	sql[MAX_STRING_LEN];
	char	*pattern;
	int	len;
	int	hostid;
	
	zabbix_log( LOG_LEVEL_DEBUG, "In autoregister(%s)",server);

	if(DBhost_exists(server) == SUCCEED)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "Host [%s] already exists. Do nothing.", server);
		return FAIL;
	}

	zbx_snprintf(sql,sizeof(sql),"select id,pattern,hostid from autoreg order by priority");

	result = DBselect(sql);

	while((row=DBfetch(result)))
	{
		pattern=row[1];
		hostid=atoi(row[2]);

		if(zbx_regexp_match(server, pattern, &len) != 0)
		{
			zabbix_log( LOG_LEVEL_DEBUG, "Matched [%s] [%s]",server,pattern);
			register_new_host(server, hostid);
			break;
		}
		else
		{
			zabbix_log( LOG_LEVEL_DEBUG, "No match [%s] [%s]",server,pattern);
		}
	}

	DBfree_result(result);

	return ret;
}

static void	register_new_host(char *server, int host_templateid)
{
	int	hostid;

	zabbix_log( LOG_LEVEL_DEBUG, "In register_new_host(%s,%d)", server, host_templateid);

	hostid = DBadd_host(server, 10050, HOST_STATUS_MONITORED, 0, "", 0, HOST_AVAILABLE_UNKNOWN);

	zabbix_log( LOG_LEVEL_DEBUG, "Added new host with hostid [%d]", hostid);

	/* Use hostid as a template */
	if( (hostid>0) && (host_templateid!=0))
	{
		zabbix_log( LOG_LEVEL_DEBUG, "Using hostid [%d] as a template", host_templateid);

		DBadd_templates_to_host(hostid,host_templateid);
		DBsync_host_with_templates(hostid);

	}
}
