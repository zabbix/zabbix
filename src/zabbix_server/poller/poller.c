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
#include "checks_aggregate.h"
#include "checks_internal.h"
#include "checks_simple.h"
#include "checks_snmp.h"

#include "daemon.h"

AGENT_RESULT    result;

static int my_server_num = 0;

int	get_value(DB_ITEM *item, AGENT_RESULT *result)
{
	int res=FAIL;

	struct	sigaction phan;

	phan.sa_handler = &child_signal_handler;
	sigemptyset(&phan.sa_mask);
	phan.sa_flags = 0;
	sigaction(SIGALRM, &phan, NULL);

	alarm(CONFIG_TIMEOUT);

	if(item->type == ITEM_TYPE_ZABBIX)
	{
		res=get_value_agent(item, result);
	}
	else if( (item->type == ITEM_TYPE_SNMPv1) || (item->type == ITEM_TYPE_SNMPv2c) || (item->type == ITEM_TYPE_SNMPv3))
	{
#ifdef HAVE_SNMP
		res=get_value_snmp(item, result);
#else
		zabbix_log(LOG_LEVEL_WARNING, "Support of SNMP parameters was not compiled in");
		zabbix_syslog("Support of SNMP parameters was not compiled in. Cannot process [%s:%s]", item->host, item->key);
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
	else if(item->type == ITEM_TYPE_AGGREGATE)
	{
		res=get_value_aggregate(item, result);
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

	DB_RESULT	result;
	DB_ROW		row;

	int		res;

/* Host status	0 == MONITORED
		1 == NOT MONITORED
		2 == UNREACHABLE */
	if(my_server_num == 4)
	{
		zbx_snprintf(sql,sizeof(sql),"select count(*),min(nextcheck) from items i,hosts h where i.nextcheck<=%d and i.status in (%d) and i.type not in (%d,%d) and h.status=%d and h.disable_until<=%d and h.errors_from!=0 and h.hostid=i.hostid and i.key_ not in ('%s','%s','%s','%s') order by i.nextcheck", now, ITEM_STATUS_ACTIVE, ITEM_TYPE_TRAPPER, ITEM_TYPE_ZABBIX_ACTIVE, HOST_STATUS_MONITORED, now, SERVER_STATUS_KEY, SERVER_ICMPPING_KEY, SERVER_ICMPPINGSEC_KEY,SERVER_ZABBIXLOG_KEY);
	}
	else
	{
		if(CONFIG_REFRESH_UNSUPPORTED != 0)
		{
			zbx_snprintf(sql,sizeof(sql),"select count(*),min(nextcheck) from items i,hosts h where h.status=%d and h.disable_until<%d and h.errors_from=0 and h.hostid=i.hostid and i.status in (%d,%d) and i.type not in (%d,%d) and mod(i.itemid,%d)=%d and i.key_ not in ('%s','%s','%s','%s')", HOST_STATUS_MONITORED, now, ITEM_STATUS_ACTIVE, ITEM_STATUS_NOTSUPPORTED, ITEM_TYPE_TRAPPER, ITEM_TYPE_ZABBIX_ACTIVE, CONFIG_POLLER_FORKS-5,my_server_num-5,SERVER_STATUS_KEY, SERVER_ICMPPING_KEY, SERVER_ICMPPINGSEC_KEY,SERVER_ZABBIXLOG_KEY);
		}
		else
		{
			zbx_snprintf(sql,sizeof(sql),"select count(*),min(nextcheck) from items i,hosts h where h.status=%d and h.disable_until<%d and h.errors_from=0 and h.hostid=i.hostid and i.status in (%d,%d) and i.type not in (%d) and mod(i.itemid,%d)=%d and i.key_ not in ('%s','%s','%s','%s')", HOST_STATUS_MONITORED, now, ITEM_STATUS_ACTIVE, ITEM_TYPE_TRAPPER, ITEM_TYPE_ZABBIX_ACTIVE, CONFIG_POLLER_FORKS-5,my_server_num-5,SERVER_STATUS_KEY, SERVER_ICMPPING_KEY, SERVER_ICMPPINGSEC_KEY,SERVER_ZABBIXLOG_KEY);
		}
	}

	result = DBselect(sql);
	row=DBfetch(result);

	if(!row || DBis_null(row[0])==SUCCEED || DBis_null(row[1])==SUCCEED)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "No items to update for minnextcheck.");
		res = FAIL; 
	}
	else
	{
		if( atoi(row[0]) == 0)
		{
			res = FAIL;
		}
		else
		{
			res = atoi(row[1]);
		}
	}
	DBfree_result(result);

	return	res;
}

/* Update special host's item - "status" */
static void update_key_status(int hostid,int host_status)
{
	char		sql[MAX_STRING_LEN];
/*	char		value_str[MAX_STRING_LEN];*/
	AGENT_RESULT	agent;

	DB_ITEM		item;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In update_key_status(%d,%d)",hostid,host_status);

	zbx_snprintf(sql,sizeof(sql),"select %s where h.hostid=i.hostid and h.hostid=%d and i.key_='%s'", ZBX_SQL_ITEM_SELECT, hostid,SERVER_STATUS_KEY);
	zabbix_log(LOG_LEVEL_DEBUG, "SQL [%s]", sql);
	result = DBselect(sql);

	row = DBfetch(result);

	if(row)
	{
		DBget_item_from_db(&item,row);

/* Do not process new value for status, if previous status is the same */
		zabbix_log( LOG_LEVEL_DEBUG, "item.lastvalue[%f] new host status[%d]",item.lastvalue,host_status);
		if( (item.lastvalue_null==1) || (cmp_double(item.lastvalue, (double)host_status) == 1))
		{
			init_result(&agent);
			SET_UI64_RESULT(&agent, host_status);
			process_new_value(&item,&agent);
			free_result(&agent);

			update_triggers(item.itemid);
		}
	}
	else
	{
		zabbix_log( LOG_LEVEL_DEBUG, "No items to update.");
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

	DB_RESULT	result;
	DB_ROW	row;

	int		now;
	int		res;
	DB_ITEM		item;
	AGENT_RESULT	agent;
	int	stop=0;

	now = time(NULL);

	/* Poller for unreachable hosts */
	if(my_server_num == 4)
	{
		zbx_snprintf(sql,sizeof(sql),"select %s where i.nextcheck<=%d and i.status in (%d) and i.type not in (%d,%d) and h.status=%d and h.disable_until<=%d and h.errors_from!=0 and h.hostid=i.hostid and i.key_ not in ('%s','%s','%s','%s') order by i.nextcheck", ZBX_SQL_ITEM_SELECT, now, ITEM_STATUS_ACTIVE, ITEM_TYPE_TRAPPER, ITEM_TYPE_ZABBIX_ACTIVE, HOST_STATUS_MONITORED, now, SERVER_STATUS_KEY, SERVER_ICMPPING_KEY, SERVER_ICMPPINGSEC_KEY,SERVER_ZABBIXLOG_KEY);
	}
	else
	{
		if(CONFIG_REFRESH_UNSUPPORTED != 0)
		{
			zbx_snprintf(sql,sizeof(sql),"select %s where i.nextcheck<=%d and i.status in (%d,%d) and i.type not in (%d,%d) and h.status=%d and h.disable_until<=%d and h.errors_from=0 and h.hostid=i.hostid and mod(i.itemid,%d)=%d and i.key_ not in ('%s','%s','%s','%s') order by i.nextcheck", ZBX_SQL_ITEM_SELECT, now, ITEM_STATUS_ACTIVE, ITEM_STATUS_NOTSUPPORTED, ITEM_TYPE_TRAPPER, ITEM_TYPE_ZABBIX_ACTIVE, HOST_STATUS_MONITORED, now, CONFIG_POLLER_FORKS-5,my_server_num-5,SERVER_STATUS_KEY, SERVER_ICMPPING_KEY, SERVER_ICMPPINGSEC_KEY,SERVER_ZABBIXLOG_KEY);
		}
		else
		{
			zbx_snprintf(sql,sizeof(sql),"select %s where i.nextcheck<=%d and i.status in (%d) and i.type not in (%d,%d) and h.status=%d and h.disable_until<=%d and h.errors_from=0 and h.hostid=i.hostid and mod(i.itemid,%d)=%d and i.key_ not in ('%s','%s','%s','%s') order by i.nextcheck", ZBX_SQL_ITEM_SELECT, now, ITEM_STATUS_ACTIVE, ITEM_TYPE_TRAPPER, ITEM_TYPE_ZABBIX_ACTIVE, HOST_STATUS_MONITORED, now, CONFIG_POLLER_FORKS-5,my_server_num-5,SERVER_STATUS_KEY, SERVER_ICMPPING_KEY, SERVER_ICMPPINGSEC_KEY,SERVER_ZABBIXLOG_KEY);
		}
	}
	result = DBselect(sql);

	while((row=DBfetch(result))&&(stop==0))
	{
		DBget_item_from_db(&item,row);

		init_result(&agent);
		zabbix_log( LOG_LEVEL_DEBUG, "GOT VALUE TYPE [0x%X]", agent.type);
		res = get_value(&item, &agent);
		
		if(res == SUCCEED )
		{
			process_new_value(&item,&agent);

/*			if(HOST_STATUS_UNREACHABLE == item.host_status)*/
			if(HOST_AVAILABLE_TRUE != item.host_available)
			{
				zabbix_log( LOG_LEVEL_WARNING, "Enabling host [%s]", item.host );
				zabbix_syslog("Enabling host [%s]", item.host );
				DBupdate_host_availability(item.hostid,HOST_AVAILABLE_TRUE,now,agent.msg);
				update_key_status(item.hostid, HOST_STATUS_MONITORED); /* 0 */
				item.host_available=HOST_AVAILABLE_TRUE;

				stop=1;
			}
			if(item.host_errors_from!=0)
			{
				zbx_snprintf(sql,sizeof(sql),"update hosts set errors_from=0 where hostid=%d", item.hostid);
				zabbix_log( LOG_LEVEL_DEBUG, "SQL [%s]", sql);
				DBexecute(sql);

				stop=1;
			}
		       	update_triggers(item.itemid);
		}
		else if(res == NOTSUPPORTED)
		{
			if(item.status == ITEM_STATUS_NOTSUPPORTED)
			{
				zbx_snprintf(sql,sizeof(sql),"update items set nextcheck=%d, lastclock=%d where itemid=%d",calculate_item_nextcheck(CONFIG_REFRESH_UNSUPPORTED,now), now, item.itemid);
				DBexecute(sql);
			}
			else
			{
				zabbix_log( LOG_LEVEL_WARNING, "Parameter [%s] is not supported by agent on host [%s] Old status [%d]", item.key, item.host, item.status);
				zabbix_syslog("Parameter [%s] is not supported by agent on host [%s]", item.key, item.host );
				DBupdate_item_status_to_notsupported(item.itemid, agent.str);
	/*			if(HOST_STATUS_UNREACHABLE == item.host_status)*/
				if(HOST_AVAILABLE_TRUE != item.host_available)
				{
					zabbix_log( LOG_LEVEL_WARNING, "Enabling host [%s]", item.host );
					zabbix_syslog("Enabling host [%s]", item.host );
					DBupdate_host_availability(item.hostid,HOST_AVAILABLE_TRUE,now,agent.msg);
					update_key_status(item.hostid, HOST_STATUS_MONITORED);	/* 0 */
					item.host_available=HOST_AVAILABLE_TRUE;
	
					stop=1;
				}
			}
		}
		else if(res == NETWORK_ERROR)
		{
			/* First error */
			if(item.host_errors_from==0)
			{
				zabbix_log( LOG_LEVEL_WARNING, "Host [%s]: first network error, wait for %d seconds", item.host, CONFIG_UNREACHABLE_DELAY);
				zabbix_syslog("Host [%s]: first network error, wait for %d seconds", item.host, CONFIG_UNREACHABLE_DELAY);

				item.host_errors_from=now;
				zbx_snprintf(sql,sizeof(sql),"update hosts set errors_from=%d,disable_until=%d where hostid=%d", now, now+CONFIG_UNREACHABLE_DELAY, item.hostid);
				zabbix_log( LOG_LEVEL_DEBUG, "SQL [%s]", sql);
				DBexecute(sql);
			}
			else
			{
				if(now-item.host_errors_from>CONFIG_UNREACHABLE_PERIOD)
				{
					zabbix_log( LOG_LEVEL_WARNING, "Host [%s] will be checked after %d seconds", item.host, CONFIG_UNAVAILABLE_DELAY);
					zabbix_syslog("Host [%s] will be checked after %d seconds", item.host, CONFIG_UNAVAILABLE_DELAY);

					DBupdate_host_availability(item.hostid,HOST_AVAILABLE_FALSE,now,agent.msg);
					update_key_status(item.hostid,HOST_AVAILABLE_FALSE); /* 2 */
					item.host_available=HOST_AVAILABLE_FALSE;

					zbx_snprintf(sql,sizeof(sql),"update hosts set disable_until=%d where hostid=%d", now+CONFIG_UNAVAILABLE_DELAY, item.hostid);
					zabbix_log( LOG_LEVEL_DEBUG, "SQL [%s]", sql);
					DBexecute(sql);
				}
				/* Still unavailable, but won't change status to UNAVAILABLE yet */
				else
				{
					zabbix_log( LOG_LEVEL_WARNING, "Host [%s]: another network error, wait for %d seconds", item.host, CONFIG_UNREACHABLE_DELAY);
					zabbix_syslog("Host [%s]: another network error, wait for %d seconds", item.host, CONFIG_UNREACHABLE_DELAY);

					zbx_snprintf(sql,sizeof(sql),"update hosts set disable_until=%d where hostid=%d", now+CONFIG_UNREACHABLE_DELAY, item.hostid);
					zabbix_log( LOG_LEVEL_DEBUG, "SQL [%s]", sql);
					DBexecute(sql);
				}
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
		free_result(&agent);
	}

	DBfree_result(result);
	return SUCCEED;
}

void main_poller_loop(int _server_num)
{
	int	now;
	int	nextcheck,sleeptime;

	my_server_num = _server_num;

	DBconnect();

	for(;;)
	{
		zbx_setproctitle("poller [getting values]");

		now=time(NULL);
		get_values();

		zabbix_log( LOG_LEVEL_DEBUG, "Spent %d seconds while updating values", (int)time(NULL)-now );

		nextcheck=get_minnextcheck(now);
		zabbix_log( LOG_LEVEL_DEBUG, "Nextcheck:%d Time:%d", nextcheck, (int)time(NULL) );

		if( FAIL == nextcheck)
		{
			sleeptime=POLLER_DELAY;
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
			if(sleeptime > POLLER_DELAY)
			{
				sleeptime = POLLER_DELAY;
			}
			zabbix_log( LOG_LEVEL_DEBUG, "Sleeping for %d seconds",
					sleeptime );

			zbx_setproctitle("poller [sleeping for %d seconds]", 
					sleeptime);

			sleep( sleeptime );
		}
		else
		{
			zabbix_log( LOG_LEVEL_DEBUG, "No sleeping" );
		}
	}
}

