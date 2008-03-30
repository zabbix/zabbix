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

#include "cfg.h"
#include "db.h"
#include "log.h"
#include "zlog.h"
#include "sysinfo.h"
#include "zbxserver.h"
#include "zbxicmpping.h"

#include "pinger.h"

static zbx_process_t	zbx_process;
static int		pinger_num;

/******************************************************************************
 *                                                                            *
 * Function: process_value                                                    *
 *                                                                            *
 * Purpose: process new item value                                            *
 *                                                                            *
 * Parameters: key - item key                                                 *
 *             host - host name                                               *
 *             value - new value of the item                                  *
 *                                                                            *
 * Return value: SUCCEED - new value sucesfully processed                     *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: can be done in process_data()                                    *
 *                                                                            *
 ******************************************************************************/
static void process_value(char *key, ZBX_FPING_HOST *host, AGENT_RESULT *value, int now, int *items)
{
	DB_RESULT	result;
	DB_ROW		row;
	DB_ITEM		item;

	zabbix_log(LOG_LEVEL_DEBUG, "In process_value(%s@%s)",
			key,
			host->addr);

	result = DBselect("select %s where " ZBX_SQL_MOD(h.hostid,%d) "=%d and h.status=%d and h.hostid=i.hostid"
			" and h.proxy_hostid=0 and h.useip=%d and h.%s='%s' and i.key_='%s' and i.status=%d"
			" and i.type=%d and i.nextcheck<=%d" DB_NODE,
			ZBX_SQL_ITEM_SELECT,
			CONFIG_PINGER_FORKS,
			pinger_num - 1,
			HOST_STATUS_MONITORED,
			host->useip,
			host->useip ? "ip" : "dns",
			host->addr,
			key,
			ITEM_STATUS_ACTIVE,
			ITEM_TYPE_SIMPLE,
			now,
			DBnode_local("h.hostid"));

	while (NULL != (row = DBfetch(result))) {
		DBget_item_from_db(&item, row);

		DBbegin();
		switch (zbx_process) {
		case ZBX_PROCESS_SERVER:
			process_new_value(&item, value, now);
			update_triggers(item.itemid);
			break;
		case ZBX_PROCESS_PROXY:
			proxy_process_new_value(&item, value, now);
			break;
		}
		DBcommit();

		(*items)++;
	}
	DBfree_result(result);
}

/******************************************************************************
 *                                                                            *
 * Function: process_values                                                   *
 *                                                                            *
 * Purpose: process new item value                                            *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: successfully processed items                                 *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: can be done in process_data()                                    *
 *                                                                            *
 ******************************************************************************/
static int process_values(ZBX_FPING_HOST *hosts, int hosts_count, int now)
{
	int		i, items = 0;
	AGENT_RESULT	value;

	zabbix_log(LOG_LEVEL_DEBUG, "In process_values()");

	for (i = 0; i < hosts_count; i++) {
		zabbix_log(LOG_LEVEL_DEBUG, "Host [%s] alive [%d] " ZBX_FS_DBL " sec.",
				hosts[i].addr,
				hosts[i].alive,
				hosts[i].sec);

		init_result(&value);
		SET_UI64_RESULT(&value, hosts[i].alive);
		process_value(SERVER_ICMPPING_KEY, &hosts[i], &value, now, &items);
		free_result(&value);
				
		init_result(&value);
		SET_DBL_RESULT(&value, hosts[i].sec);
		process_value(SERVER_ICMPPINGSEC_KEY, &hosts[i], &value, now, &items);
		free_result(&value);
	}

	return items;
}

/******************************************************************************
 *                                                                            *
 * Function: get_pinger_hosts                                                 *
 *                                                                            *
 * Purpose: creates buffer which contains list of hosts to ping               *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCEED - the file was created succesfully                   *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Author: Alexei Vladishev, Aleksander Vladishev                             *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int get_pinger_hosts(ZBX_FPING_HOST **hosts, int *hosts_allocated, int now)
{
	DB_RESULT	result;
	DB_ROW		row;
	ZBX_FPING_HOST	*host;
	int		hosts_count = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In get_pinger_hosts()");

	/* Select hosts monitored by IP */
	result = DBselect("select distinct h.ip from hosts h,items i where " ZBX_SQL_MOD(h.hostid,%d) "=%d"
			" and i.hostid=h.hostid and h.proxy_hostid=0 and h.status=%d and i.key_ in ('%s','%s')"
			" and i.type=%d and i.status=%d and h.useip=1 and i.nextcheck<=%d" DB_NODE, 
			CONFIG_PINGER_FORKS,
			pinger_num - 1,
			HOST_STATUS_MONITORED,
			SERVER_ICMPPING_KEY, SERVER_ICMPPINGSEC_KEY,
			ITEM_TYPE_SIMPLE,
			ITEM_STATUS_ACTIVE,
			now,
			DBnode_local("h.hostid"));

	while (NULL != (row = DBfetch(result))) {
		if (hosts_count == *hosts_allocated) {
			*hosts_allocated *= 2;
			*hosts = zbx_realloc(*hosts, *hosts_allocated * sizeof(ZBX_FPING_HOST));
		}

		host = &(*hosts)[hosts_count];

		memset(host, '\0', sizeof(ZBX_FPING_HOST));
		strscpy(host->addr, row[0]);
		host->useip = 1;

		hosts_count++;

		zabbix_log(LOG_LEVEL_DEBUG, "IP [%s]", host->addr);
	}
	DBfree_result(result);

	/* Select hosts monitored by hostname */
	result = DBselect("select distinct h.dns from hosts h,items i where " ZBX_SQL_MOD(h.hostid,%d) "=%d"
			" and i.hostid=h.hostid and h.proxy_hostid=0 and h.status=%d and i.key_ in ('%s','%s')"
			" and i.type=%d and i.status=%d and h.useip=0 and i.nextcheck<=%d" DB_NODE,
			CONFIG_PINGER_FORKS,
			pinger_num - 1,
			HOST_STATUS_MONITORED,
			SERVER_ICMPPING_KEY, SERVER_ICMPPINGSEC_KEY,
			ITEM_TYPE_SIMPLE,
			ITEM_STATUS_ACTIVE,
			now,
			DBnode_local("h.hostid"));

	while (NULL != (row = DBfetch(result))) {
		if (hosts_count == *hosts_allocated) {
			*hosts_allocated *= 2;
			*hosts = zbx_realloc(*hosts, *hosts_allocated * sizeof(ZBX_FPING_HOST));
		}

		host = &(*hosts)[hosts_count];

		memset(host, '\0', sizeof(ZBX_FPING_HOST));
		strscpy(host->addr, row[0]);
		host->useip = 0;

		hosts_count++;

		zabbix_log(LOG_LEVEL_DEBUG, "DNS name [%s]", host->addr);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of get_pinger_hosts()");

	return hosts_count;
}

/******************************************************************************
 *                                                                            *
 * Function: get_minnextcheck                                                 *
 *                                                                            *
 * Purpose: calculate when we have to process earliest simple check           *
 *                                                                            *
 * Parameters: now - current timestamp                                        *
 *                                                                            *
 * Return value: timestamp of earliest check or -1 if not found               *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int get_minnextcheck()
{
	DB_RESULT	result;
	DB_ROW		row;
	int		res;

	zabbix_log(LOG_LEVEL_DEBUG, "In get_minnextcheck()");

	result = DBselect("select count(*),min(i.nextcheck) from items i,hosts h where " ZBX_SQL_MOD(h.hostid,%d) "=%d"
			" and h.status=%d and h.hostid=i.hostid and h.proxy_hostid=0 and i.key_ in ('%s','%s')"
			" and i.type=%d and i.status=%d" DB_NODE, 
			CONFIG_PINGER_FORKS,
			pinger_num - 1,
			HOST_STATUS_MONITORED,
			SERVER_ICMPPING_KEY, SERVER_ICMPPINGSEC_KEY,
			ITEM_TYPE_SIMPLE,
			ITEM_STATUS_ACTIVE,
			DBnode_local("h.hostid"));

	if (NULL == (row = DBfetch(result)) || DBis_null(row[0]) == SUCCEED || DBis_null(row[1]) == SUCCEED)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "No items to update for minnextcheck.");
		res = FAIL; 
	}
	else
	{
		if (atoi(row[0]) == 0)
		{
			res = FAIL;
		}
		else
		{
			res = atoi(row[1]);
		}
	}
	DBfree_result(result);

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: main_pinger_loop                                                 *
 *                                                                            *
 * Purpose: periodically perform ICMP pings                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: never returns                                                    *
 *                                                                            *
 ******************************************************************************/
void main_pinger_loop(zbx_process_t p, int num)
{
	int		now, nextcheck, sleeptime;
	ZBX_FPING_HOST	*hosts = NULL;
	int		hosts_allocated = 8, hosts_count, items;
	double		sec;

	zabbix_log(LOG_LEVEL_DEBUG, "In main_pinger_loop(num:%d)",
			num);

	zbx_process = p;
	pinger_num = num;

	hosts = zbx_malloc(hosts, hosts_allocated * sizeof(ZBX_FPING_HOST));

	zbx_setproctitle("pinger [connecting to the database]");

	DBconnect(ZBX_DB_CONNECT_NORMAL);
	
	for (;;) {
		now = time(NULL);
		sec = zbx_time();

		items = 0;
		if (0 < (hosts_count = get_pinger_hosts(&hosts, &hosts_allocated, now))) {
			zbx_setproctitle("pinger [pinging hosts]");

			if (SUCCEED == do_ping(hosts, hosts_count))
				items = process_values(hosts, hosts_count, now); 
		}
	
		sec = zbx_time() - sec;

		nextcheck = get_minnextcheck();

		if (FAIL == nextcheck)
			sleeptime = CONFIG_PINGER_FREQUENCY;
		else {
			sleeptime = nextcheck - time(NULL);
			if (sleeptime < 0)
				sleeptime = 0;
			else if (sleeptime > CONFIG_PINGER_FREQUENCY)
				sleeptime = CONFIG_PINGER_FREQUENCY;
		}

		zabbix_log(LOG_LEVEL_DEBUG, "Pinger spent " ZBX_FS_DBL " seconds while processing %d items."
				" Nextcheck after %d sec.",
				sec,
				items,
				sleeptime);

		if (sleeptime > 0) {
			zbx_setproctitle("pinger [sleeping for %d seconds]", 
					sleeptime);

			sleep(sleeptime);
		}
	}

	/* Never reached */
	DBclose();
}
