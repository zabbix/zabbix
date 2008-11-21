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
#include "dbcache.h"

static zbx_process_t	zbx_process;
static int		pinger_num;

static ZBX_FPING_HOST	*hosts = NULL;
static int		hosts_allocated = 4;

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
static void process_value(char *key, ZBX_FPING_HOST *host, zbx_uint64_t *value_ui64, double *value_dbl, int now, int *items, int ping_result, const char *error)
{
	DB_RESULT	result;
	DB_ROW		row;
	DB_ITEM		item;
	AGENT_RESULT	value;
	char		istatus[16];

	assert(value_ui64 || value_dbl);

	zabbix_log(LOG_LEVEL_DEBUG, "In process_value(%s@%s)",
			key,
			host->addr);

	if (0 != CONFIG_REFRESH_UNSUPPORTED)
		zbx_snprintf(istatus, sizeof(istatus), "%d,%d",
				ITEM_STATUS_ACTIVE,
				ITEM_STATUS_NOTSUPPORTED);
	else
		zbx_snprintf(istatus, sizeof(istatus), "%d",
				ITEM_STATUS_ACTIVE);

	result = DBselect("select %s where " ZBX_SQL_MOD(h.hostid,%d) "=%d and h.status=%d and h.hostid=i.hostid"
			" and h.proxy_hostid=0 and h.useip=%d and h.%s='%s' and i.key_='%s' and i.status in (%s)"
			" and i.type=%d and i.nextcheck<=%d and (h.maintenance_status=%d or h.maintenance_type=%d)" DB_NODE,
			ZBX_SQL_ITEM_SELECT,
			CONFIG_PINGER_FORKS,
			pinger_num - 1,
			HOST_STATUS_MONITORED,
			host->useip,
			host->useip ? "ip" : "dns",
			host->addr,
			key,
			istatus,
			ITEM_TYPE_SIMPLE,
			now,
			HOST_MAINTENANCE_STATUS_OFF, MAINTENANCE_TYPE_NORMAL,
			DBnode_local("h.hostid"));

	while (NULL != (row = DBfetch(result))) {
		DBget_item_from_db(&item, row);

		if (ping_result == NOTSUPPORTED)
		{
			if (0 == CONFIG_DBSYNCER_FORKS)
			{
				DBbegin();
		
				DBupdate_item_status_to_notsupported(&item, now, error);

				DBcommit();
			}
			else
				DCadd_nextcheck(&item, now, 0, error);
		}
		else
		{
			init_result(&value);

			if (NULL != value_ui64)
			{
				SET_UI64_RESULT(&value, *value_ui64);
			}
			else
			{
				SET_DBL_RESULT(&value, *value_dbl);
			}

			if (0 == CONFIG_DBSYNCER_FORKS)
				DBbegin();

			switch (zbx_process) {
			case ZBX_PROCESS_SERVER:
				process_new_value(&item, &value, now);
				break;
			case ZBX_PROCESS_PROXY:
				proxy_process_new_value(&item, &value, now);
				break;
			}

			if (0 == CONFIG_DBSYNCER_FORKS)
				DBcommit();

			if (0 != CONFIG_DBSYNCER_FORKS)
				DCadd_nextcheck(&item, now, 0, NULL);

			free_result(&value);
		}

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
	int		i, items = 0, ping_result;
	char		error[ITEM_ERROR_LEN_MAX];

	zabbix_log(LOG_LEVEL_DEBUG, "In process_values()");

	zbx_setproctitle("pinger [pinging hosts]");

	ping_result = do_ping(hosts, hosts_count, error, sizeof(error));

	if (0 != CONFIG_DBSYNCER_FORKS)
		DCinit_nextchecks();

	for (i = 0; i < hosts_count; i++) {
		if (ping_result == NOTSUPPORTED)
			zabbix_log(LOG_LEVEL_DEBUG, "Host [%s] %s",
					hosts[i].addr,
					error);
		else
			zabbix_log(LOG_LEVEL_DEBUG, "Host [%s] alive [" ZBX_FS_UI64 "] " ZBX_FS_DBL " sec.",
					hosts[i].addr,
					hosts[i].alive,
					hosts[i].sec);

		process_value(SERVER_ICMPPING_KEY, &hosts[i], &hosts[i].alive, NULL, now, &items, ping_result, error);
		process_value(SERVER_ICMPPINGSEC_KEY, &hosts[i], NULL, &hosts[i].sec, now, &items, ping_result, error);
	}

	if (0 != CONFIG_DBSYNCER_FORKS)
		DCflush_nextchecks();

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
static int	get_pinger_hosts(int now)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		hosts_count = 0;
	char		istatus[16];

	zabbix_log(LOG_LEVEL_DEBUG, "In get_pinger_hosts()");

	if (0 != CONFIG_REFRESH_UNSUPPORTED)
		zbx_snprintf(istatus, sizeof(istatus), "%d,%d",
				ITEM_STATUS_ACTIVE,
				ITEM_STATUS_NOTSUPPORTED);
	else
		zbx_snprintf(istatus, sizeof(istatus), "%d",
				ITEM_STATUS_ACTIVE);

	/* Select hosts monitored by IP */
	result = DBselect("select distinct h.ip from hosts h,items i where " ZBX_SQL_MOD(h.hostid,%d) "=%d"
			" and i.hostid=h.hostid and h.proxy_hostid=0 and h.status=%d and i.key_ in ('%s','%s')"
			" and i.type=%d and i.status in (%s) and h.useip=1 and i.nextcheck<=%d"
			" and (h.maintenance_status=%d or h.maintenance_type=%d)" DB_NODE, 
			CONFIG_PINGER_FORKS,
			pinger_num - 1,
			HOST_STATUS_MONITORED,
			SERVER_ICMPPING_KEY, SERVER_ICMPPINGSEC_KEY,
			ITEM_TYPE_SIMPLE,
			istatus,
			now,
			HOST_MAINTENANCE_STATUS_OFF, MAINTENANCE_TYPE_NORMAL,
			DBnode_local("h.hostid"));

	while (NULL != (row = DBfetch(result))) {
		if (hosts_count == hosts_allocated) {
			hosts_allocated *= 2;
			hosts = zbx_realloc(hosts, hosts_allocated * sizeof(ZBX_FPING_HOST));
		}

		memset(&hosts[hosts_count], '\0', sizeof(ZBX_FPING_HOST));
		strscpy(hosts[hosts_count].addr, row[0]);
		hosts[hosts_count].useip = 1;

		zabbix_log(LOG_LEVEL_DEBUG, "IP [%s]", hosts[hosts_count].addr);

		hosts_count++;
	}
	DBfree_result(result);

	/* Select hosts monitored by hostname */
	result = DBselect("select distinct h.dns from hosts h,items i where " ZBX_SQL_MOD(h.hostid,%d) "=%d"
			" and i.hostid=h.hostid and h.proxy_hostid=0 and h.status=%d and i.key_ in ('%s','%s')"
			" and i.type=%d and i.status in (%s) and h.useip=0 and i.nextcheck<=%d"
			" and (h.maintenance_status=%d or h.maintenance_type=%d)" DB_NODE,
			CONFIG_PINGER_FORKS,
			pinger_num - 1,
			HOST_STATUS_MONITORED,
			SERVER_ICMPPING_KEY, SERVER_ICMPPINGSEC_KEY,
			ITEM_TYPE_SIMPLE,
			istatus,
			now,
			HOST_MAINTENANCE_STATUS_OFF, MAINTENANCE_TYPE_NORMAL,
			DBnode_local("h.hostid"));

	while (NULL != (row = DBfetch(result))) {
		if (hosts_count == hosts_allocated) {
			hosts_allocated *= 2;
			hosts = zbx_realloc(hosts, hosts_allocated * sizeof(ZBX_FPING_HOST));
		}

		memset(&hosts[hosts_count], '\0', sizeof(ZBX_FPING_HOST));
		strscpy(hosts[hosts_count].addr, row[0]);
		hosts[hosts_count].useip = 0;

		zabbix_log(LOG_LEVEL_DEBUG, "DNS name [%s]", hosts[hosts_count].addr);

		hosts_count++;
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of get_pinger_hosts():%d", hosts_count);

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
	char		istatus[16];

	zabbix_log(LOG_LEVEL_DEBUG, "In get_minnextcheck()");

	if (0 != CONFIG_REFRESH_UNSUPPORTED)
		zbx_snprintf(istatus, sizeof(istatus), "%d,%d",
				ITEM_STATUS_ACTIVE,
				ITEM_STATUS_NOTSUPPORTED);
	else
		zbx_snprintf(istatus, sizeof(istatus), "%d",
				ITEM_STATUS_ACTIVE);

	result = DBselect("select count(*),min(i.nextcheck) from items i,hosts h where " ZBX_SQL_MOD(h.hostid,%d) "=%d"
			" and h.status=%d and h.hostid=i.hostid and h.proxy_hostid=0 and i.key_ in ('%s','%s')"
			" and i.type=%d and i.status in (%s) and (h.maintenance_status=%d or h.maintenance_type=%d)" DB_NODE, 
			CONFIG_PINGER_FORKS,
			pinger_num - 1,
			HOST_STATUS_MONITORED,
			SERVER_ICMPPING_KEY, SERVER_ICMPPINGSEC_KEY,
			ITEM_TYPE_SIMPLE,
			istatus,
			HOST_MAINTENANCE_STATUS_OFF, MAINTENANCE_TYPE_NORMAL,
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
	int		hosts_count, items;
	double		sec;

	zabbix_log(LOG_LEVEL_DEBUG, "In main_pinger_loop(num:%d)",
			num);

	zbx_process = p;
	pinger_num = num;

	hosts = zbx_malloc(hosts, hosts_allocated * sizeof(ZBX_FPING_HOST));

	zbx_setproctitle("pinger [connecting to the database]");

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;)
	{
		now = time(NULL);
		sec = zbx_time();

		items = 0;
		if (0 < (hosts_count = get_pinger_hosts(now)))
			items = process_values(hosts, hosts_count, now); 
	
		sec = zbx_time() - sec;

		nextcheck = get_minnextcheck();

		if (FAIL == nextcheck)
			sleeptime = CONFIG_PINGER_FREQUENCY;
		else
		{
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
