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

int	DBget_item_by_itemid(int itemid,DB_ITEM *item)
{
	DB_RESULT	result;
	char	sql[MAX_STRING_LEN];
	int	ret = SUCCEED;

	zabbix_log( LOG_LEVEL_DEBUG, "In DBget_item_by_itemid(%d)", itemid);

	snprintf(sql,sizeof(sql)-1,"select i.itemid,i.key_,h.hostid from items i,hosts h where h.hostid=i.hostid and i.itemid=%d", itemid);
	result=DBselect(sql);

	if(DBnum_rows(result)==0)
	{
		ret = FAIL;
	}
	else
	{
		item->itemid=atoi(DBget_field(result,0,0));
		strscpy(item->key,DBget_field(result,0,1));
		item->hostid=atoi(DBget_field(result,0,2));
	}

	DBfree_result(result);

	return ret;
}

int 	DBadd_item_to_linked_hosts(int itemid, int hostid)
{
	DB_ITEM	item;
	DB_RESULT	result;
	DB_RESULT	result2;
	DB_RESULT	result3;
	char	sql[MAX_STRING_LEN];
	int	i;

	zabbix_log( LOG_LEVEL_DEBUG, "In DBadd_item_to_linked_hosts(%d,%d)", itemid, hostid);

	snprintf(sql,sizeof(sql)-1,"select description,key_,hostid,delay,history,status,type,snmp_community,snmp_oid,value_type,trapper_hosts,snmp_port,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,trends,logtimefmt from items where itemid=%d", itemid);
	result3=DBselect(sql);

	if(DBnum_rows(result3)==0)
	{
		DBfree_result(result3);
		return FAIL;
	}

	item.description=DBget_field(result3,0,0);
	strscpy(item.key,DBget_field(result3,0,1));
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

	zabbix_log( LOG_LEVEL_DEBUG, "OK");

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
		if( (atoi(DBget_field(result,i,2))&1) == 0)	continue;

		snprintf(sql,sizeof(sql)-1,"select itemid from items where key_='%s' and hostid=%d", item.key, atoi(DBget_field(result,i,0)));
		result2=DBselect(sql);
		if(DBnum_rows(result2)==0)
		{
			DBadd_item(item.description,item.key,hostid,item.delay,item.history,item.status,item.type,item.snmp_community,item.snmp_oid,item.value_type,item.trapper_hosts,item.snmp_port,item.units,item.multiplier,item.delta,item.snmpv3_securityname,item.snmpv3_securitylevel,item.snmpv3_authpassphrase,item.snmpv3_privpassphrase,item.formula,item.trends,item.logtimefmt);
		}
		DBfree_result(result2);
	}

	DBfree_result(result);
	DBfree_result(result3);

	return SUCCEED;
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

	zabbix_log( LOG_LEVEL_DEBUG, "In DBadd_item()");

	DBescape_string(key,key_esc,MAX_STRING_LEN);
	DBescape_string(description,description_esc,MAX_STRING_LEN);
	DBescape_string(logtimefmt,logtimefmt_esc,MAX_STRING_LEN);
	DBescape_string(snmpv3_securityname,snmpv3_securityname_esc,MAX_STRING_LEN);
	DBescape_string(snmpv3_authpassphrase,snmpv3_authpassphrase_esc,MAX_STRING_LEN);
	DBescape_string(snmpv3_privpassphrase,snmpv3_privpassphrase_esc,MAX_STRING_LEN);

	snprintf(sql,sizeof(sql)-1,"insert into items (description,key_,hostid,delay,history,nextcheck,status,type,snmp_community,snmp_oid,value_type,trapper_hosts,snmp_port,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,trends,logtimefmt) values ('%s','%s',%d,%d,%d,0,%d,%d,'%s','%s',%d,'%s',%d,'%s',%d,%d,'%s',%d,'%s','%s','%s',%d,'%s')", description_esc, key_esc, hostid,delay,history,status,type,snmp_community,snmp_oid,value_type,trapper_hosts,snmp_port,units,multiplier,delta,snmpv3_securityname_esc,snmpv3_securitylevel,snmpv3_authpassphrase_esc,snmpv3_privpassphrase_esc,formula,trends,logtimefmt_esc);

	return DBexecute(sql);
}
