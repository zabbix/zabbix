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
#include <sys/stat.h>

#include <string.h>


/* Required for getpwuid */
#include <pwd.h>

#include <signal.h>
#include <errno.h>

#include <time.h>
/* getopt() */
#include <unistd.h>

#include "cfg.h"
#include "pid.h"
#include "db.h"
#include "log.h"
#include "zlog.h"

#include "common.h"
#include "../functions.h"
#include "../expression.h"
#include "poller.h"

#include "checks_agent.h"
#include "checks_internal.h"
#include "checks_simple.h"
#include "checks_snmp.h"

AGENT_RESULT    result;

int	get_value(DB_ITEM *item, AGENT_RESULT *result)
{
	int res=FAIL;

	struct	sigaction phan;

	phan.sa_handler = &signal_handler;
	sigemptyset(&phan.sa_mask);
	phan.sa_flags = 0;
	sigaction(SIGALRM, &phan, NULL);

	alarm(CONFIG_TIMEOUT);

	if(item->type == ITEM_TYPE_ZABBIX)
	{
		res=get_value_agent(item, result);
	}
	else if( (item->type == ITEM_TYPE_SNMPv1) || (item->type == ITEM_TYPE_SNMPv2c))
	{
#ifdef HAVE_SNMP
		res=get_value_snmp(item, result);
#else
		zabbix_log(LOG_LEVEL_WARNING, "Support of SNMP parameters was no compiled in");
		zabbix_syslog("Support of SNMP parameters was no compiled in. Cannot process [%s:%s]", item->host, item->key);
		res=NOTSUPPORTED;
#endif
	}
	else if(item->type == ITEM_TYPE_SIMPLE)
	{
		res=get_value_simple(item, result);
	}
	else if(item->type == ITEM_TYPE_INTERNAL)
	{
		res=get_value_internal(item, result);
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "Not supported item type:%d",item->type);
		zabbix_syslog("Not supported item type:%d",item->type);
		res=NOTSUPPORTED;
	}
	alarm(0);

	return res;
}

static int get_minnextcheck(int now)
{
	char		sql[MAX_STRING_LEN];

	DB_RESULT	*result;

	int		res;

/* Host status	0 == MONITORED
		1 == NOT MONITORED
		2 == UNREACHABLE */ 
	snprintf(sql,sizeof(sql)-1,"select count(*),min(nextcheck) from items i,hosts h where ((h.status=%d and h.available!=%d) or (h.status=%d and h.available=%d and h.disable_until<%d)) and h.hostid=i.hostid and i.status=%d and i.type not in (%d,%d) and i.itemid%%%d=%d and i.key_ not in ('%s','%s','%s','%s') and i.serverid=%d", HOST_STATUS_MONITORED, HOST_AVAILABLE_FALSE,HOST_STATUS_MONITORED, HOST_AVAILABLE_FALSE, now, ITEM_STATUS_ACTIVE, ITEM_TYPE_TRAPPER, ITEM_TYPE_ZABBIX_ACTIVE, CONFIG_SUCKERD_FORKS-5,server_num-5,SERVER_STATUS_KEY, SERVER_ICMPPING_KEY, SERVER_ICMPPINGSEC_KEY,SERVER_ZABBIXLOG_KEY,CONFIG_SERVERD_ID);
	result = DBselect(sql);

	if( DBnum_rows(result) == 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "No items to update for minnextcheck.");
		res = FAIL; 
	}
	else
	{
		if( atoi(DBget_field(result,0,0)) == 0)
		{
			res = FAIL;
		}
		else
		{
			res = atoi(DBget_field(result,0,1));
		}
	}
	DBfree_result(result);

	return	res;
}

/* Update special host's item - "status" */
static void update_key_status(int hostid,int host_status)
{
	char		sql[MAX_STRING_LEN];
	char		value_str[MAX_STRING_LEN];

	DB_ITEM		item;
	DB_RESULT	*result;

	zabbix_log(LOG_LEVEL_DEBUG, "In update_key_status()");

	snprintf(sql,sizeof(sql)-1,"select i.itemid,i.key_,h.host,h.port,i.delay,i.description,i.nextcheck,i.type,i.snmp_community,i.snmp_oid,h.useip,h.ip,i.history,i.lastvalue,i.prevvalue,i.hostid,h.status,i.value_type,h.network_errors,i.snmp_port,i.delta,i.prevorgvalue,i.lastclock,i.units,i.multiplier,i.snmpv3_securityname,i.snmpv3_securitylevel,i.snmpv3_authpassphrase,i.snmpv3_privpassphrase,i.formula,h.available from items i,hosts h where h.hostid=i.hostid and h.hostid=%d and i.key_='%s' and i.serverid=%d", hostid,SERVER_STATUS_KEY,CONFIG_SERVERD_ID);
	result = DBselect(sql);

	if( DBnum_rows(result) == 0)
	{
		zabbix_log( LOG_LEVEL_DEBUG, "No items to update.");
	}
	else
	{
		DBget_item_from_db(&item,result,0);
	
		snprintf(value_str,sizeof(value_str)-1,"%d",host_status);

		process_new_value(&item,value_str);
		update_triggers(item.itemid);
	}

	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: get_values                                                       *
 *                                                                            *
 * Purpose: retrieve values of metrics from monitored hosts                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: always SUCCEED                                                   *
 *                                                                            *
 ******************************************************************************/
int get_values(void)
{
	char		sql[MAX_STRING_LEN];

	DB_RESULT	*result;

	int		i;
	int		now;
	int		res;
	DB_ITEM		item;
	AGENT_RESULT	agent;
	int	stop;

	now = time(NULL);

	snprintf(sql,sizeof(sql)-1,"select i.itemid,i.key_,h.host,h.port,i.delay,i.description,i.nextcheck,i.type,i.snmp_community,i.snmp_oid,h.useip,h.ip,i.history,i.lastvalue,i.prevvalue,i.hostid,h.status,i.value_type,h.network_errors,i.snmp_port,i.delta,i.prevorgvalue,i.lastclock,i.units,i.multiplier,i.snmpv3_securityname,i.snmpv3_securitylevel,i.snmpv3_authpassphrase,i.snmpv3_privpassphrase,i.formula,h.available from items i,hosts h where i.nextcheck<=%d and i.status=%d and i.type not in (%d,%d) and ((h.status=%d and h.available!=%d) or (h.status=%d and h.available=%d and h.disable_until<=%d)) and h.hostid=i.hostid and i.itemid%%%d=%d and i.key_ not in ('%s','%s','%s','%s') and i.serverid=%d order by i.nextcheck", now, ITEM_STATUS_ACTIVE, ITEM_TYPE_TRAPPER, ITEM_TYPE_ZABBIX_ACTIVE, HOST_STATUS_MONITORED, HOST_AVAILABLE_FALSE, HOST_STATUS_MONITORED, HOST_AVAILABLE_FALSE, now, CONFIG_SUCKERD_FORKS-5,server_num-5,SERVER_STATUS_KEY, SERVER_ICMPPING_KEY, SERVER_ICMPPINGSEC_KEY,SERVER_ZABBIXLOG_KEY,CONFIG_SERVERD_ID);
	result = DBselect(sql);

	for(stop=i=0;i<DBnum_rows(result)&&stop==0;i++)
	{
		DBget_item_from_db(&item,result, i);

		init_result(&agent);
		res = get_value(&item, &agent);
		zabbix_log( LOG_LEVEL_DEBUG, "GOT VALUE TYPE [%s]", agent.type);
		
		if(res == SUCCEED )
		{
			process_new_value(&item,&agent);

			if(item.host_network_errors>0)
			{
				snprintf(sql,sizeof(sql)-1,"update hosts set network_errors=0,error='' where hostid=%d and network_errors>0", item.hostid);
				DBexecute(sql);
			}

/*			if(HOST_STATUS_UNREACHABLE == item.host_status)*/
			if(HOST_AVAILABLE_TRUE != item.host_available)
			{
				item.host_available=HOST_AVAILABLE_TRUE;
				zabbix_log( LOG_LEVEL_WARNING, "Enabling host [%s]", item.host );
				zabbix_syslog("Enabling host [%s]", item.host );
				DBupdate_host_availability(item.hostid,HOST_AVAILABLE_TRUE,now,error);
				update_key_status(item.hostid,HOST_STATUS_MONITORED);

/* Why this break??? Trigger needs to be updated anyway!
				break;*/
			}
		       	update_triggers(item.itemid);
		}
		else if(res == NOTSUPPORTED)
		{
			zabbix_log( LOG_LEVEL_WARNING, "Parameter [%s] is not supported by agent on host [%s]", item.key, item.host );
			zabbix_syslog("Parameter [%s] is not supported by agent on host [%s]", item.key, item.host );
			DBupdate_item_status_to_notsupported(item.itemid, error);
/*			if(HOST_STATUS_UNREACHABLE == item.host_status)*/
			if(HOST_AVAILABLE_TRUE != item.host_available)
			{
				item.host_available=HOST_AVAILABLE_TRUE;
				zabbix_log( LOG_LEVEL_WARNING, "Enabling host [%s]", item.host );
				zabbix_syslog("Enabling host [%s]", item.host );
				DBupdate_host_availability(item.hostid,HOST_AVAILABLE_TRUE,now,error);
				update_key_status(item.hostid,HOST_STATUS_MONITORED);	

				stop=1;
			}
		}
		else if(res == NETWORK_ERROR)
		{
			item.host_network_errors++;
			if(item.host_network_errors>=3)
			{
				zabbix_log( LOG_LEVEL_WARNING, "Host [%s] will be checked after [%d] seconds", item.host, DELAY_ON_NETWORK_FAILURE );
				zabbix_syslog("Host [%s] will be checked after [%d] seconds", item.host, DELAY_ON_NETWORK_FAILURE );
				DBupdate_host_availability(item.hostid,HOST_AVAILABLE_FALSE,now,error);
				update_key_status(item.hostid,HOST_AVAILABLE_FALSE);	

				snprintf(sql,sizeof(sql)-1,"update hosts set network_errors=3 where hostid=%d", item.hostid);
				DBexecute(sql);
			}
			else
			{
				snprintf(sql,sizeof(sql)-1,"update hosts set network_errors=%d where hostid=%d", item.host_network_errors, item.hostid);
				DBexecute(sql);
			}

			stop=1;
		}
/* Possibly, other logic required? */
		else if(res == AGENT_ERROR)
		{
			zabbix_log( LOG_LEVEL_WARNING, "Getting value of [%s] from host [%s] failed (ZBX_ERROR)", item.key, item.host );
			zabbix_syslog("Getting value of [%s] from host [%s] failed (ZBX_ERROR)", item.key, item.host );
			zabbix_log( LOG_LEVEL_WARNING, "The value is not stored in database.");

			stop=1;
		}
		else
		{
			zabbix_log( LOG_LEVEL_WARNING, "Getting value of [%s] from host [%s] failed", item.key, item.host );
			zabbix_syslog("Getting value of [%s] from host [%s] failed", item.key, item.host );
			zabbix_log( LOG_LEVEL_WARNING, "The value is not stored in database.");
		}
		free(result(&agent));
	}

	DBfree_result(result);
	return SUCCEED;
}

void main_poller_loop()
{
	int	now;
	int	nextcheck,sleeptime;

	DBconnect();

	for(;;)
	{
#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("poller [getting values]");
#endif
		now=time(NULL);
		get_values();

		zabbix_log( LOG_LEVEL_DEBUG, "Spent %d seconds while updating values", (int)time(NULL)-now );

		nextcheck=get_minnextcheck(now);
		zabbix_log( LOG_LEVEL_DEBUG, "Nextcheck:%d Time:%d", nextcheck, (int)time(NULL) );

		if( FAIL == nextcheck)
		{
			sleeptime=SUCKER_DELAY;
		}
		else
		{
			sleeptime=nextcheck-time(NULL);
			if(sleeptime<0)
			{
				sleeptime=0;
			}
		}
		if(sleeptime>0)
		{
			if(sleeptime > SUCKER_DELAY)
			{
				sleeptime = SUCKER_DELAY;
			}
			zabbix_log( LOG_LEVEL_DEBUG, "Sleeping for %d seconds",
					sleeptime );
#ifdef HAVE_FUNCTION_SETPROCTITLE
			setproctitle("poller [sleeping for %d seconds]", 
					sleeptime);
#endif
			sleep( sleeptime );
		}
		else
		{
			zabbix_log( LOG_LEVEL_DEBUG, "No sleeping" );
		}
	}
}

