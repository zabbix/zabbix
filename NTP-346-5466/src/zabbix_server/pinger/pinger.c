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
#include "../functions.h"
#include "log.h"
#include "zlog.h"
#include "threads.h"

#include "pinger.h"

int pinger_num;

#define ZBX_FPING_HOST struct zbx_fipng_host
ZBX_FPING_HOST
{
	char		ip[HOST_IP_LEN_MAX];
	char		dns[HOST_DNS_LEN_MAX];
	int		alive, useip;
	double		mseconds;
};

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
static int process_value(char *key, ZBX_FPING_HOST *host, AGENT_RESULT *value)
{
	DB_RESULT	result;
	DB_ROW		row;
	DB_ITEM		item;
	struct timeb    tp;

	zabbix_log(LOG_LEVEL_DEBUG, "In process_value(%s@%s)",
			key,
			host->useip ? host->ip : host->dns);

	result = DBselect("select %s where " ZBX_SQL_MOD(h.hostid,%d) "=%d and h.status=%d and h.hostid=i.hostid"
			" and h.useip=%d and h.%s='%s' and i.key_='%s' and i.status=%d"
			" and i.type=%d and" ZBX_COND_NODEID,
			ZBX_SQL_ITEM_SELECT,
			CONFIG_PINGER_FORKS,
			pinger_num - 1,
			HOST_STATUS_MONITORED,
			host->useip,
			host->useip ? "ip" : "dns",
			host->useip ? host->ip : host->dns,
			key,
			ITEM_STATUS_ACTIVE,
			ITEM_TYPE_SIMPLE,
			LOCAL_NODE("h.hostid"));

	while (NULL != (row = DBfetch(result))) {
		DBget_item_from_db(&item, row);

		DBbegin();
		ftime(&tp);
		process_new_value(&item, value, tp.time, tp.millitm);
		update_triggers(item.itemid, tp.time, tp.millitm);
		DBcommit();
	}
	DBfree_result(result);

	return SUCCEED;
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
static int get_pinger_hosts(ZBX_FPING_HOST **hosts, int *hosts_allocated, int *hosts_count)
{
	DB_RESULT	result;
	DB_ROW		row;
	ZBX_FPING_HOST	*host;

	zabbix_log(LOG_LEVEL_DEBUG, "In get_pinger_hosts()");

	/* Select hosts monitored by IP */
	result = DBselect("select distinct h.ip from hosts h,items i where " ZBX_SQL_MOD(h.hostid,%d) "=%d"
			" and i.hostid=h.hostid and h.status=%d and (i.key_='%s' or i.key_='%s')"
			" and i.type=%d and i.status=%d and h.useip=1 and" ZBX_COND_NODEID,
			CONFIG_PINGER_FORKS,
			pinger_num - 1,
			HOST_STATUS_MONITORED,
			SERVER_ICMPPING_KEY,
			SERVER_ICMPPINGSEC_KEY,
			ITEM_TYPE_SIMPLE,
			ITEM_STATUS_ACTIVE,
			LOCAL_NODE("h.hostid"));

	while (NULL != (row = DBfetch(result))) {
		if (*hosts_count == *hosts_allocated) {
			*hosts_allocated *= 2;
			*hosts = zbx_realloc(*hosts, *hosts_allocated * sizeof(ZBX_FPING_HOST));
		}

		host = &(*hosts)[*hosts_count];

		memset(host, '\0', sizeof(ZBX_FPING_HOST));
		strscpy(host->ip, row[0]);
		host->useip = 1;

		(*hosts_count)++;

		zabbix_log(LOG_LEVEL_DEBUG, "IP [%s]", host->ip);
	}
	DBfree_result(result);

	/* Select hosts monitored by hostname */
	result = DBselect("select distinct h.dns from hosts h,items i where " ZBX_SQL_MOD(h.hostid,%d) "=%d"
			" and i.hostid=h.hostid and h.status=%d and (i.key_='%s' or i.key_='%s')"
			" and i.type=%d and i.status=%d and h.useip=0 and" ZBX_COND_NODEID,
			CONFIG_PINGER_FORKS,
			pinger_num - 1,
			HOST_STATUS_MONITORED,
			SERVER_ICMPPING_KEY,
			SERVER_ICMPPINGSEC_KEY,
			ITEM_TYPE_SIMPLE,
			ITEM_STATUS_ACTIVE,
			LOCAL_NODE("h.hostid"));

	while (NULL != (row = DBfetch(result))) {
		if (*hosts_count == *hosts_allocated) {
			*hosts_allocated *= 2;
			*hosts = zbx_realloc(*hosts, *hosts_allocated * sizeof(ZBX_FPING_HOST));
		}

		host = &(*hosts)[*hosts_count];

		memset(host, '\0', sizeof(ZBX_FPING_HOST));
		strscpy(host->dns, row[0]);
		host->useip = 0;

		(*hosts_count)++;

		zabbix_log(LOG_LEVEL_DEBUG, "DNS name [%s]", host->dns);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of get_pinger_hosts()");

	return SUCCEED;
}


/******************************************************************************
 *                                                                            *
 * Function: do_ping                                                          *
 *                                                                            *
 * Purpose: ping hosts listed in the host files                               *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCEED - successfully processed                             *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: use external binary 'fping' to avoid superuser priviledges       *
 *                                                                            *
 ******************************************************************************/
static int do_ping(ZBX_FPING_HOST *hosts, int hosts_count)
{
	FILE		*f;
	char		filename[MAX_STRING_LEN];
	char		tmp[MAX_STRING_LEN];
	int		i;
	char		*c;
	ZBX_FPING_HOST	*host;
	AGENT_RESULT	value;

	zabbix_log(LOG_LEVEL_DEBUG, "In do_ping() [hosts_count:%d]",
			hosts_count);

	zbx_snprintf(filename, sizeof(filename), "/tmp/zabbix_server_%li.pinger",
			zbx_get_thread_id());

	if (NULL == (f = fopen(filename, "w"))) {
		zabbix_log(LOG_LEVEL_ERR, "Cannot open file [%s] [%s]",
				filename,
				strerror(errno));
		zabbix_syslog("Cannot open file [%s] [%s]",
				filename,
				strerror(errno));
		return FAIL;
	}

	for (i = 0; i < hosts_count; i++)
		fprintf(f, "%s\n", hosts[i].useip ? hosts[i].ip : hosts[i].dns);

	fclose(f);

	zbx_snprintf(tmp, sizeof(tmp), "%s -e 2>/dev/null <%s",
			CONFIG_FPING_LOCATION,
			filename);

	if (0 == (f = popen(tmp, "r"))) {
		zabbix_log(LOG_LEVEL_ERR, "Cannot execute [%s] [%s]",
				CONFIG_FPING_LOCATION,
				strerror(errno));
		zabbix_syslog("Cannot execute [%s] [%s]",
				CONFIG_FPING_LOCATION,
				strerror(errno));
		return FAIL;
	}

	while (NULL != fgets(tmp, sizeof(tmp), f)) {
		zabbix_log(LOG_LEVEL_DEBUG, "Update IP [%s]",
				tmp);

		host = NULL;

		c = strchr(tmp, ' ');
		if (c != NULL) {
			*c = '\0';
			for (i = 0; i < hosts_count; i++)
				if (0 == strcmp(tmp, hosts[i].useip ? hosts[i].ip : hosts[i].dns)) {
					host = &hosts[i];
					break;
				}
		}

		if (NULL != host) {
			c++;
			
			if (strstr(c, "alive") != NULL) {
				host->alive = 1;
				sscanf(c, "is alive (%lf ms)",
						&host->mseconds);
				zabbix_log(LOG_LEVEL_DEBUG, "Mseconds [%lf]",
						host->mseconds);
			}
		}
	}
	pclose(f);

	unlink(filename);

	for (i = 0; i < hosts_count; i++) {
		zabbix_log(LOG_LEVEL_DEBUG, "Host [%s] alive [%d]",
				hosts[i].useip ? hosts[i].ip : hosts[i].dns,
				hosts[i].alive);

		init_result(&value);
		SET_UI64_RESULT(&value, hosts[i].alive);
		process_value(SERVER_ICMPPING_KEY, &hosts[i], &value);
		free_result(&value);
				
		init_result(&value);
		SET_DBL_RESULT(&value, hosts[i].mseconds/1000);
		process_value(SERVER_ICMPPINGSEC_KEY, &hosts[i], &value);
		free_result(&value);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of do_ping()");

	return SUCCEED;
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
void main_pinger_loop(int num)
{
	int		start, sleeptime;
	ZBX_FPING_HOST	*hosts = NULL;
	int		hosts_allocated = 16, hosts_count;

	zabbix_log(LOG_LEVEL_DEBUG, "In main_pinger_loop(num:%d)",
			num);

	hosts = zbx_malloc(hosts, hosts_allocated * sizeof(ZBX_FPING_HOST));

	pinger_num = num;

	for(;;) {
		start = time(NULL);

		zbx_setproctitle("connecting to the database");

		DBconnect(ZBX_DB_CONNECT_NORMAL);
	
		hosts_count = 0;
		if (SUCCEED == get_pinger_hosts(&hosts, &hosts_allocated, &hosts_count)) {
			zbx_setproctitle("pinging hosts");

			do_ping(hosts, hosts_count);
		}
	
		DBclose();

		sleeptime = CONFIG_PINGER_FREQUENCY - (time(NULL) - start);

		if (sleeptime > 0) {
			zbx_setproctitle("pinger [sleeping for %d seconds]",
					sleeptime);
			zabbix_log(LOG_LEVEL_DEBUG, "Sleeping for %d seconds",
					sleeptime);
			sleep(sleeptime);
		}
	}

	/* Never reached */
}
