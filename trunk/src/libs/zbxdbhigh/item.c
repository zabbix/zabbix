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
	DB_ROW		row;
	int	ret = SUCCEED;

	zabbix_log( LOG_LEVEL_DEBUG, "In DBget_item_by_itemid(%d)", itemid);

	result = DBselect("select i.itemid,i.key_,h.hostid from items i,hosts h where h.hostid=i.hostid and i.itemid=%d", itemid);
	row = DBfetch(result);

	if(!row)
	{
		ret = FAIL;
	}
	else
	{
		item->itemid=atoi(row[0]);
		strscpy(item->key,row[1]);
		item->hostid=atoi(row[2]);
	}

	DBfree_result(result);

	return ret;
}

int 	DBadd_item_to_linked_hosts(int itemid, int hostid)
{
	DB_ITEM	item;
	DB_RESULT	result;
	DB_ROW		row;
	DB_RESULT	result2;
	DB_ROW		row2;
	DB_RESULT	result3;
	DB_ROW		row3;

	zabbix_log( LOG_LEVEL_DEBUG, "In DBadd_item_to_linked_hosts(%d,%d)", itemid, hostid);

	result3 = DBselect("select description,key_,hostid,delay,history,status,type,snmp_community,snmp_oid,value_type,trapper_hosts,snmp_port,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,trends,logtimefmt from items where itemid=%d", itemid);

	row3=DBfetch(result3);

	if(!row3)
	{
		DBfree_result(result3);
		return FAIL;
	}

	item.description=row3[0];
	strscpy(item.key,row3[1]);
	item.hostid=atoi(row3[2]);
	item.delay=atoi(row3[3]);
	item.history=atoi(row3[4]);
	item.status=atoi(row3[5]);
	item.type=atoi(row3[6]);
	item.snmp_community=row3[7];
	item.snmp_oid=row3[8];
	item.value_type=atoi(row3[9]);
	item.trapper_hosts=row3[10];
	item.snmp_port=atoi(row3[11]);
	item.units=row3[12];
	item.multiplier=atoi(row3[13]);
	item.delta=atoi(row3[14]);
	item.snmpv3_securityname=row3[15];
	item.snmpv3_securitylevel=atoi(row3[16]);
	item.snmpv3_authpassphrase=row3[17];
	item.snmpv3_privpassphrase=row3[18];
	item.formula=row3[19];
	item.trends=atoi(row3[20]);
	item.logtimefmt=row3[21];

	zabbix_log( LOG_LEVEL_DEBUG, "OK");

	/* Link with one host only */
	if(hostid!=0)
	{
		result = DBselect("select hostid,templateid,items from hosts_templates where hostid=%d and templateid=%d", hostid, item.hostid);
	}
	else
	{
		result = DBselect("select hostid,templateid,items from hosts_templates where templateid=%d", item.hostid);
	}

	while((row=DBfetch(result)))
	{
		if( (atoi(row[2])&1) == 0)	continue;

		result2 = DBselect("select itemid from items where key_='%s' and hostid=%d", item.key, atoi(row[0]));
		row2=DBfetch(result2);
		if(!row2)
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

	return DBexecute("insert into items (description,key_,hostid,delay,history,nextcheck,status,type,snmp_community,snmp_oid,value_type,trapper_hosts,snmp_port,units,multiplier,delta,snmpv3_securityname,snmpv3_securitylevel,snmpv3_authpassphrase,snmpv3_privpassphrase,formula,trends,logtimefmt) values ('%s','%s',%d,%d,%d,0,%d,%d,'%s','%s',%d,'%s',%d,'%s',%d,%d,'%s',%d,'%s','%s','%s',%d,'%s')", description_esc, key_esc, hostid,delay,history,status,type,snmp_community,snmp_oid,value_type,trapper_hosts,snmp_port,units,multiplier,delta,snmpv3_securityname_esc,snmpv3_securitylevel,snmpv3_authpassphrase_esc,snmpv3_privpassphrase_esc,formula,trends,logtimefmt_esc);
}
