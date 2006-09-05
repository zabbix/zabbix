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


#include <stdlib.h>
#include <stdio.h>

#include <string.h>
#include <strings.h>

#include "db.h"
#include "log.h"
#include "zlog.h"
#include "common.h"

int	DBadd_host(char *server, int port, int status, int useip, char *ip, int disable_until, int available)
{
	char	sql[MAX_STRING_LEN];
	int	hostid;

	snprintf(sql, sizeof(sql)-1,"insert into hosts (host,port,status,useip,ip,disable_until,available) values ('%s',%d,%d,%d,'%s',%d,%d)", server, port, status, useip, ip, disable_until, available);

	hostid = DBinsert_id(DBexecute(sql), "hosts", "hostid");

	if(hostid==0)
	{
		return FAIL;
	}

	return hostid;
}

int	DBhost_exists(char *server)
{
	DB_RESULT	result;
	DB_ROW		row;
	char	sql[MAX_STRING_LEN];
	int	ret = SUCCEED;

	snprintf(sql,sizeof(sql)-1,"select hostid from hosts where host='%s'", server);
	result = DBselect(sql);
	row = DBfetch(result);

	if(!row)
	{
		ret = FAIL;
	}
	DBfree_result(result);

	return ret;
}

int	DBadd_templates_to_host(int hostid,int host_templateid)
{
	DB_RESULT	result;
	DB_ROW		row;
	char	sql[MAX_STRING_LEN];

	zabbix_log( LOG_LEVEL_DEBUG, "In DBadd_templates_to_host(%d,%d)", hostid, host_templateid);

	snprintf(sql,sizeof(sql)-1,"select templateid,items,triggers,graphs from hosts_templates where hostid=%d", host_templateid);
	result = DBselect(sql);

	while((row=DBfetch(result)))
	{
		DBadd_template_linkage(hostid,atoi(row[0]),atoi(row[1]),
					atoi(row[2]), atoi(row[3]));
	}

	DBfree_result(result);

	return SUCCEED;
}

int	DBadd_template_linkage(int hostid,int templateid,int items,int triggers,int graphs)
{
	char	sql[MAX_STRING_LEN];

	zabbix_log( LOG_LEVEL_DEBUG, "In DBadd_template_linkage(%d)", hostid);

	snprintf(sql,sizeof(sql)-1,"insert into hosts_templates (hostid,templateid,items,triggers,graphs) values (%d,%d,%d,%d,%d)",hostid, templateid, items, triggers, graphs);

	return DBexecute(sql);
}

int	DBsync_host_with_templates(int hostid)
{
	DB_RESULT	result;
	DB_ROW		row;
	char	sql[MAX_STRING_LEN];

	zabbix_log( LOG_LEVEL_DEBUG, "In DBsync_host_with_templates(%d)", hostid);

	snprintf(sql,sizeof(sql)-1,"select templateid,items,triggers,graphs from hosts_templates where hostid=%d", hostid);
	result = DBselect(sql);

	while((row=DBfetch(result)))
	{
		DBsync_host_with_template(hostid,atoi(row[0]),atoi(row[1]),
					atoi(row[2]), atoi(row[3]));
	}

	DBfree_result(result);

	return SUCCEED;
}

int	DBsync_host_with_template(int hostid,int templateid,int items,int triggers,int graphs)
{
	DB_RESULT	result;
	DB_ROW		row;
	char	sql[MAX_STRING_LEN];

	zabbix_log( LOG_LEVEL_DEBUG, "In DBsync_host_with_template(%d,%d)", hostid, templateid);

	/* Sync items */
	snprintf(sql,sizeof(sql)-1,"select itemid from items where hostid=%d", templateid);
	result = DBselect(sql);

	while((row=DBfetch(result)))
	{
		DBadd_item_to_linked_hosts(atoi(row[0]), hostid);
	}
	DBfree_result(result);

	/* Sync triggers */
	snprintf(sql,sizeof(sql)-1,"select distinct t.triggerid from hosts h, items i,triggers t,functions f where h.hostid=%d and h.hostid=i.hostid and t.triggerid=f.triggerid and i.itemid=f.itemid", templateid);
	result = DBselect(sql);
	while((row=DBfetch(result)))
	{
		DBadd_trigger_to_linked_hosts(atoi(row[0]),hostid);
	}
	DBfree_result(result);

	/* Sync graphs */
	snprintf(sql,sizeof(sql)-1,"select distinct gi.gitemid from graphs g,graphs_items gi,items i where i.itemid=gi.itemid and i.hostid=%d and g.graphid=gi.graphid", templateid);
	result = DBselect(sql);
	while((row=DBfetch(result)))
	{
		DBadd_graph_item_to_linked_hosts(atoi(row[0]),hostid);
	}
	DBfree_result(result);

	return SUCCEED;
}

int	DBget_host_by_hostid(int hostid,DB_HOST *host)
{
	DB_RESULT	result;
	DB_ROW		row;
	char	sql[MAX_STRING_LEN];
	int	ret = SUCCEED;

	zabbix_log( LOG_LEVEL_DEBUG, "In DBget_host_by_hostid(%d)", hostid);

	snprintf(sql,sizeof(sql)-1,"select hostid,host,useip,ip,port,status,disable_until,errors_from,error,available from hosts where hostid=%d", hostid);
	result=DBselect(sql);

	row=DBfetch(result);
	if(!row)
	{
		ret = FAIL;
	}
	else
	{
		host->hostid=atoi(row[0]);
		strscpy(host->host,row[1]);
		host->useip=atoi(row[2]);
		strscpy(host->ip,row[3]);
		host->port=atoi(row[4]);
		host->status=atoi(row[5]);
		host->disable_until=atoi(row[6]);
		host->errors_from=atoi(row[7]);
		strscpy(host->error,row[8]);
		host->available=atoi(row[9]);
	}

	DBfree_result(result);

	zabbix_log( LOG_LEVEL_DEBUG, "End of DBget_host_by_hostid");

	return ret;
}
