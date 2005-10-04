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

int	DBadd_new_host(char *server, int port, int status, int useip, char *ip, int disable_until, int available)
{
	char	sql[MAX_STRING_LEN];
	int	hostid;

	snprintf(sql, sizeof(sql)-1,"insert into hosts (host,port,status,useip,ip,disable_until,available) values ('%s',%d,%d,%d,'%d',%d,%d)", server, port, status, useip, ip, disable_until, available);
	if(FAIL == DBexecute(sql))
	{
		return FAIL;
	}

	hostid=DBinsert_id();

	if(hostid==0)
	{
		return FAIL;
	}

	return hostid;
}

int	DBhost_exists(char *server)
{
	DB_RESULT	*result;
	char	sql[MAX_STRING_LEN];
	int	ret = SUCCEED;

	snprintf(sql,sizeof(sql)-1,"select hostid from hosts where host='%s'", server);
	result = DBselect(sql);

	if(DBnum_rows(result) == 0)
	{
		ret = FAIL;
	}
	DBfree_result(result);

	return ret;
}

int	DBadd_templates_to_host(int hostid,int host_templateid)
{
	DB_RESULT	*result;
	char	sql[MAX_STRING_LEN];
	int	ret = SUCCEED;
	int	i;

	zabbix_log( LOG_LEVEL_WARNING, "In DBadd_templates_to_host(%d,%d)", hostid, host_templateid);

	snprintf(sql,sizeof(sql)-1,"select templateid,items,triggers,actions,graphs,screens from hosts_templates where hostid=%d", host_templateid);
	result = DBselect(sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		DBadd_template_linkage(hostid,atoi(DBget_field(result,i,0)),atoi(DBget_field(result,i,1)),
					atoi(DBget_field(result,i,2)), atoi(DBget_field(result,i,3)),
					atoi(DBget_field(result,i,4)), atoi(DBget_field(result,i,5)));
	}

	DBfree_result(result);
}

int	DBadd_template_linkage(int hostid,int templateid,int items,int triggers,int actions,int graphs,int screens)
{
	char	sql[MAX_STRING_LEN];

	zabbix_log( LOG_LEVEL_WARNING, "In DBadd_template_linkage(%d)", hostid);

	snprintf(sql,sizeof(sql)-1,"insert into hosts_templates (hostid,templateid,items,triggers,actions,graphs,screens) values (%d,%d,%d,%d,%d,%d,%d)",hostid, templateid, items, triggers, actions, graphs, screens);

	return DBexecute(sql);
}

int	DBsync_host_with_templates(int hostid)
{
	DB_RESULT	*result;
	char	sql[MAX_STRING_LEN];
	int	ret = SUCCEED;
	int	i;

	zabbix_log( LOG_LEVEL_WARNING, "In DBsync_host_with_templates(%d)", hostid);

	snprintf(sql,sizeof(sql)-1,"select templateid,items,triggers,actions,graphs,screens from hosts_templates where hostid=%d", hostid);
	result = DBselect(sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		DBsync_host_with_template(hostid,atoi(DBget_field(result,i,0)),atoi(DBget_field(result,i,1)),
					atoi(DBget_field(result,i,2)), atoi(DBget_field(result,i,3)),
					atoi(DBget_field(result,i,4)), atoi(DBget_field(result,i,5)));
	}

	DBfree_result(result);

	return SUCCEED;
}

int	DBsync_host_with_template(int hostid,int templateid,int items,int triggers,int actions,int graphs,int screens)
{
	DB_RESULT	*result;
	char	sql[MAX_STRING_LEN];
	int	ret = SUCCEED;
	int	i;

	zabbix_log( LOG_LEVEL_WARNING, "In DBsync_host_with_template(%d,%d)", hostid, templateid);

	/* Sync items */
	snprintf(sql,sizeof(sql)-1,"select itemid from items where hostid=%d", templateid);
	result = DBselect(sql);
	for(i=0;i<DBnum_rows(result);i++)
	{
		DBadd_item_to_linked_hosts(atoi(DBget_field(result,i,0)), hostid);
	}
	DBfree_result(result);

	/* Sync triggers */
	snprintf(sql,sizeof(sql)-1,"select distinct t.triggerid from hosts h, items i,triggers t,functions f where h.hostid=%d and h.hostid=i.hostid and t.triggerid=f.triggerid and i.itemid=f.itemid", templateid);
	result = DBselect(sql);
	for(i=0;i<DBnum_rows(result);i++)
	{
//		DBadd_trigger_to_linked_hosts(atoi(DBget_field(result,i,0)),hostid);
	}
	DBfree_result(result);

	/* Sync actions */
	snprintf(sql,sizeof(sql)-1,"select distinct a.actionid from actions a,hosts h, items i,triggers t,functions f where h.hostid=%d and h.hostid=i.hostid and t.triggerid=f.triggerid and i.itemid=f.itemid", templateid);
	result = DBselect(sql);
	for(i=0;i<DBnum_rows(result);i++)
	{
//		DBadd_action_to_linked_hosts(atoi(DBget_field(result,i,0)),hostid);
	}
	DBfree_result(result);

	/* Sync graphs */
	snprintf(sql,sizeof(sql)-1,"select distinct gi.gitemid from graphs g,graphs_items gi,items i where i.itemid=gi.itemid and i.hostid=%d and g.graphid=gi.graphid", templateid);
	result = DBselect(sql);
	for(i=0;i<DBnum_rows(result);i++)
	{
//		DBadd_graph_item_to_linked_hosts(atoi(DBget_field(result,i,0)),hostid);
	}
	DBfree_result(result);

	return SUCCEED;
}

int 	DBadd_item_to_linked_hosts(int itemid, int hostid)
{
	DB_ITEM	item;
	DB_RESULT	*result,*result2,*result3;
	char	sql[MAX_STRING_LEN];
	int	ret = SUCCEED;
	int	i;

	zabbix_log( LOG_LEVEL_WARNING, "In DBadd_item_to_linked_hosts(%d,%d)", itemid, hostid);

	snprintf(sql,sizeof(sql)-1,"select description,key_,hostid,delay,history,status,type,snmp_community,snmp_oid,value_type,trapper_hosts,snmp_port,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,trends,logtimefmt from items where itemid=%d", itemid);
	result3=DBselect(sql);

	if(DBnum_rows(result3)==0)
	{
		DBfree_result(result3);
		return FAIL;
	}

	item.description=DBget_field(result3,0,0);
	item.key=DBget_field(result3,0,1);
	item.hostid=atoi(DBget_field(result3,0,2));
	item.delay=atoi(DBget_field(result3,0,3));
	item.history=atoi(DBget_field(result3,0,4));
	item.status=atoi(DBget_field(result3,0,5));
	item.type=atoi(DBget_field(result3,0,6));
	item.snmp_community=DBget_field(result3,0,7);
	item.snmp_oid=DBget_field(result3,0,8);
	item.value_type=atoi(DBget_field(result3,0,9));
	item.trapper_hosts=DBget_field(result3,0,10);
	item.snmp_port=atoi(DBget_field(result3,0,11));
	item.units=DBget_field(result3,0,12);
	item.multiplier=atoi(DBget_field(result3,0,13));
	item.delta=atoi(DBget_field(result3,0,14));
	item.snmpv3_securityname=DBget_field(result3,0,15);
	item.snmpv3_securitylevel=atoi(DBget_field(result3,0,16));
	item.snmpv3_authpassphrase=DBget_field(result3,0,17);
	item.snmpv3_privpassphrase=DBget_field(result3,0,18);
	item.formula=DBget_field(result3,0,19);
	item.trends=atoi(DBget_field(result3,0,20));
	item.logtimefmt=DBget_field(result3,0,21);

	zabbix_log( LOG_LEVEL_WARNING, "OK");

	/* Link with one host only */
	if(hostid!=0)
	{
		snprintf(sql,sizeof(sql)-1,"select hostid,templateid,items from hosts_templates where hostid=%d and templateid=%d", hostid, item.hostid);
	}
	else
	{
		snprintf(sql,sizeof(sql)-1,"select hostid,templateid,items from hosts_templates where templateid=%d", item.hostid);
	}

	result = DBselect(sql);
	for(i=0;i<DBnum_rows(result);i++)
	{
		if(atoi(DBget_field(result,i,2))&1 == 0)	continue;

		snprintf(sql,sizeof(sql)-1,"select itemid from items where key_='%s' and hostid=%d", item.key, atoi(DBget_field(result,i,0)));
		result2=DBselect(sql);
		if(DBnum_rows(result2)==0)
		{
			DBadd_item(item.description,item.key,item.hostid,item.delay,item.history,item.status,item.type,item.snmp_community,item.snmp_oid,item.value_type,item.trapper_hosts,item.snmp_port,item.units,item.multiplier,item.delta,item.snmpv3_securityname,item.snmpv3_securitylevel,item.snmpv3_authpassphrase,item.snmpv3_privpassphrase,item.formula,item.trends,item.logtimefmt);
		}
		DBfree_result(result2);
	}

	DBfree_result(result);
	DBfree_result(result3);
}

int	DBadd_item(char *description, char *key, int hostid, int delay, int history, int status, int type, char *snmp_community, char *snmp_oid,int value_type,char *trapper_hosts,int snmp_port,char *units,int multiplier,int delta, char *snmpv3_securityname,int snmpv3_securitylevel,char *snmpv3_authpassphrase,char *snmpv3_privpassphrase,char *formula,int trends,char *logtimefmt)
{
	char	sql[MAX_STRING_LEN];
	char	key_esc[MAX_STRING_LEN];
	char	description_esc[MAX_STRING_LEN];
	char	logtimefmt_esc[MAX_STRING_LEN];
	char	snmpv3_securityname_esc[MAX_STRING_LEN];
	char	snmpv3_authpassphrase_esc[MAX_STRING_LEN];
	char	snmpv3_privpassphrase_esc[MAX_STRING_LEN];

	zabbix_log( LOG_LEVEL_WARNING, "In DBadd_item()");

	DBescape_string(key,key_esc,MAX_STRING_LEN);
	DBescape_string(description,description_esc,MAX_STRING_LEN);
	DBescape_string(logtimefmt,logtimefmt_esc,MAX_STRING_LEN);
	DBescape_string(snmpv3_securityname,snmpv3_securityname_esc,MAX_STRING_LEN);
	DBescape_string(snmpv3_authpassphrase,snmpv3_authpassphrase_esc,MAX_STRING_LEN);
	DBescape_string(snmpv3_privpassphrase,snmpv3_privpassphrase_esc,MAX_STRING_LEN);

	snprintf(sql,sizeof(sql)-1,"insert into items (description,key_,hostid,delay,history,nextcheck,status,type,snmp_community,snmp_oid,value_type,trapper_hosts,snmp_port,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,trends,logtimefmt) values ('%s','%s',%d,%d,%d,0,%d,%d,'%s','%s',%d,'%s',%d,'%s',%d,%d,'%s',%d,'%s','%s','%s',%d,'%s')", description_esc, key_esc, hostid,delay,history,status,type,snmp_community,snmp_oid,value_type,trapper_hosts,snmp_port,units,multiplier,delta,snmpv3_securityname_esc,snmpv3_securitylevel,snmpv3_authpassphrase_esc,snmpv3_privpassphrase_esc,formula,trends,logtimefmt_esc);

	return DBexecute(sql);
}
