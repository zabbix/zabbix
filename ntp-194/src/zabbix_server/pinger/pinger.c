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
#include "sysinfo.h"
#include "threads.h"

#include "pinger.h"

static int		pinger_num;

extern char	*CONFIG_FPING_LOCATION;
#ifdef HAVE_IPV6
extern char	*CONFIG_FPING6_LOCATION;
#endif /* HAVE_IPV6 */

#define ZBX_FPING_HOST struct zbx_fipng_host

ZBX_FPING_HOST
{
	char		*addr;
	double		min, avg, max;
	int		rcv;
};

typedef enum
{
	ICMPPING = 0,
	ICMPPINGSEC,
	ICMPPINGLOSS
} icmpping_t;

typedef enum
{
	ICMPPINGSEC_MIN = 0,
	ICMPPINGSEC_AVG,
	ICMPPINGSEC_MAX
} icmppingsec_type_t;

typedef struct
{
	int			count;
	int			interval;
	int			size;
	int			timeout;
	zbx_uint64_t		itemid;
	char			*addr;
	icmpping_t		icmpping;
	icmppingsec_type_t	type;
} icmpitem_t;

static int	process_ping(ZBX_FPING_HOST *hosts, int hosts_count, int count, int interval, int size, int timeout, char *error, int max_error_len)
{
	FILE		*f;
	char		filename[MAX_STRING_LEN], tmp[MAX_STRING_LEN],
			*c, *c2, params[64];
	int		i;
	ZBX_FPING_HOST	*host;
	double		sec;

	assert(hosts);

	zabbix_log(LOG_LEVEL_DEBUG, "In process_ping() [hosts_count:%d]",
			hosts_count);

	i = zbx_snprintf(params, sizeof(params), "-q -C%d", count);
	if (0 != interval)
		i += zbx_snprintf(params + i, sizeof(params) - i, " -p%d", interval);
	if (0 != size)
		i += zbx_snprintf(params + i, sizeof(params) - i, " -b%d", size);
	if (0 != timeout)
		i += zbx_snprintf(params + i, sizeof(params) - i, " -t%d", timeout);

	zbx_snprintf(filename, sizeof(filename), "/tmp/zabbix_server_%li.pinger",
			zbx_get_thread_id());

	if (access(CONFIG_FPING_LOCATION, F_OK|X_OK) == -1)
	{
		zbx_snprintf(error, max_error_len, "%s: [%d] %s", CONFIG_FPING_LOCATION, errno, strerror(errno));
		return NOTSUPPORTED;
	}

#ifdef HAVE_IPV6
	if (access(CONFIG_FPING6_LOCATION, F_OK|X_OK) == -1)
	{
		zbx_snprintf(error, max_error_len, "%s: [%d] %s", CONFIG_FPING6_LOCATION, errno, strerror(errno));
		return NOTSUPPORTED;
	}

	zbx_snprintf(tmp, sizeof(tmp), "%s %s 2>&1 <%s;%s %s 2>&1 <%s",
			CONFIG_FPING_LOCATION,
			params,
			filename,
			CONFIG_FPING6_LOCATION,
			params,
			filename);
#else /* HAVE_IPV6 */
	zbx_snprintf(tmp, sizeof(tmp), "%s %s 2>&1 <%s",
			CONFIG_FPING_LOCATION,
			params,
			filename);
#endif /* HAVE_IPV6 */

	if (NULL == (f = fopen(filename, "w"))) {
		zbx_snprintf(error, max_error_len, "%s: [%d] %s", filename, errno, strerror(errno));
		return NOTSUPPORTED;
	}

	for (i = 0; i < hosts_count; i++)
		fprintf(f, "%s\n", hosts[i].addr);

	fclose(f);

	zabbix_log(LOG_LEVEL_DEBUG, "%s", tmp);

	if (0 == (f = popen(tmp, "r"))) {
		zbx_snprintf(error, max_error_len, "%s: [%d] %s", tmp, errno, strerror(errno));

		unlink(filename);

		return NOTSUPPORTED;
	}

	while (NULL != fgets(tmp, sizeof(tmp), f)) {
		zbx_rtrim(tmp, "\n");

		zabbix_log(LOG_LEVEL_DEBUG, "%s", tmp);

		/* 192.168.3.64 : 0.91 0.86 0.81 */
		/* 192.168.3.5 : 0.51 - - */

		host = NULL;

		if (NULL != (c = strchr(tmp, ' ')))
		{
			*c = '\0';
			for (i = 0; i < hosts_count; i++)
				if (0 == strcmp(tmp, hosts[i].addr))
				{
					host = &hosts[i];
					break;
				}
			*c = ' ';
		}

		if (NULL == host)
			continue;

		if (NULL == (c = strstr(tmp, " : ")))
			continue;

		c += 3;

		do {
			if (NULL != (c2 = strchr(c, ' ')))
				*c2 = '\0';

			if (0 != strcmp(c, "-"))
			{
				sec = atof(c);

				if (host->rcv == 0 || host->min > sec)
					host->min = sec;
				if (host->rcv == 0 || host->max < sec)
					host->max = sec;
				host->avg = (host->avg * host->rcv + sec)/(host->rcv + 1);
				host->rcv++;
			}

			if (NULL != c2)
				*c2++ = ' ';
		} while (NULL != (c = c2));
	}
	pclose(f);

	unlink(filename);

	zabbix_log(LOG_LEVEL_DEBUG, "End of process_ping()");

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
 * Return value: => 0 - successfully processed items                          *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: use external binary 'fping' to avoid superuser priviledges       *
 *                                                                            *
 ******************************************************************************/
int	do_ping(ZBX_FPING_HOST *hosts, int hosts_count, int count, int interval, int size, int timeout, char *error, int max_error_len)
{
	int res;

	if (NOTSUPPORTED == (res = process_ping(hosts, hosts_count, count, interval, size, timeout, error, max_error_len)))
	{
		zabbix_log(LOG_LEVEL_ERR, "%s", error);
		zabbix_syslog("%s", error);
	}

	return res;
}
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
static void	process_value(zbx_uint64_t itemid, zbx_uint64_t *value_ui64, double *value_dbl, struct timeb *tp, int ping_result, char *error)
{
	DB_RESULT	result;
	DB_ROW		row;
	DB_ITEM		item;
	AGENT_RESULT	value;

	assert(value_ui64 || value_dbl);

	zabbix_log(LOG_LEVEL_DEBUG, "In process_value()");

	result = DBselect("select %s where h.hostid=i.hostid and i.itemid=" ZBX_FS_UI64,
			ZBX_SQL_ITEM_SELECT,
			itemid);

	if (NULL != (row = DBfetch(result))) {
		DBget_item_from_db(&item, row);

		DBbegin();
		if (ping_result == NOTSUPPORTED)
			DBupdate_item_status_to_notsupported(item.itemid, error);
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

			process_new_value(&item, &value, tp->time, tp->millitm);

			free_result(&value);
		}
		DBcommit();
	}
	DBfree_result(result);
}

static void	add_pinger_host(ZBX_FPING_HOST **hosts, int *hosts_alloc, int *hosts_count, char *addr)
{
	int		i;
	size_t		sz;
	ZBX_FPING_HOST	*h;

	zabbix_log(LOG_LEVEL_DEBUG, "In add_pinger_host() addr=%s", addr);

	for (i = 0; i < *hosts_count; i ++)
		if (0 == strcmp(addr, (*hosts)[i].addr))
			return;

	(*hosts_count)++;

	if (*hosts_alloc < *hosts_count)
	{
		*hosts_alloc += 4;
		sz = *hosts_alloc * sizeof(ZBX_FPING_HOST);
		*hosts = zbx_realloc(*hosts, sz);
	}

	h = &(*hosts)[*hosts_count - 1];
	memset(h, 0, sizeof(ZBX_FPING_HOST));
	h->addr = addr;
}

/******************************************************************************
 *                                                                            *
 * Function: process_values                                                   *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	process_values(icmpitem_t *items, int first_index, int last_index, ZBX_FPING_HOST *hosts, int hosts_count, struct timeb *tp, int ping_result, char *error)
{
	int		i, h;
	zbx_uint64_t	value_uint64;
	double		value_dbl;

	zabbix_log(LOG_LEVEL_DEBUG, "In process_values()");

	for (h = 0; h < hosts_count; h++)
	{
		if (ping_result == NOTSUPPORTED)
			zabbix_log(LOG_LEVEL_DEBUG, "Host [%s] %s",
					hosts[h].addr,
					error);
		else
			zabbix_log(LOG_LEVEL_DEBUG, "Host [%s] rcv=%d min/max/avg=" ZBX_FS_DBL "/" ZBX_FS_DBL "/" ZBX_FS_DBL,
					hosts[h].addr,
					hosts[h].rcv,
					hosts[h].min,
					hosts[h].max,
					hosts[h].avg);

		for (i = first_index; i < last_index; i++)
			if (0 == strcmp(items[i].addr, hosts[h].addr))
			{
				switch (items[i].icmpping) {
					case ICMPPING:
						value_uint64 = hosts[h].rcv ? 1 : 0;
						process_value(items[i].itemid, &value_uint64, NULL, tp, ping_result, error);
						break;
					case ICMPPINGSEC:
						switch (items[i].type) {
							case ICMPPINGSEC_MIN : value_dbl = hosts[h].min; break;
							case ICMPPINGSEC_MAX : value_dbl = hosts[h].max; break;
							case ICMPPINGSEC_AVG : value_dbl = hosts[h].avg; break;
						}
						process_value(items[i].itemid, NULL, &value_dbl, tp, ping_result, error);
						break;
					case ICMPPINGLOSS:
						value_dbl = 100 * (1 - (double)hosts[h].rcv / (double)items[i].count);
						process_value(items[i].itemid, NULL, &value_dbl, tp, ping_result, error);
						break;
				}
			}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: process_pinger_hosts                                             *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	process_pinger_hosts(icmpitem_t *items, int items_count)
{
	int			i, first_index = 0, ping_result;
	char			error[ITEM_ERROR_LEN_MAX];
	static ZBX_FPING_HOST	*hosts = NULL;
	static int		hosts_alloc = 4;
	int			hosts_count = 0;
	struct timeb		tp;

	zabbix_log(LOG_LEVEL_DEBUG, "In process_pinger_hosts()");

	if (NULL == hosts)
		hosts = zbx_malloc(hosts, sizeof(ZBX_FPING_HOST) * hosts_alloc);

	for (i = 0; i < items_count; i++)
	{
		add_pinger_host(&hosts, &hosts_alloc, &hosts_count, items[i].addr);

		if (i == items_count - 1 || items[i].count != items[i + 1].count || items[i].interval != items[i + 1].interval ||
				items[i].size != items[i + 1].size || items[i].timeout != items[i + 1].timeout)
		{
			zbx_setproctitle("pinger [pinging hosts]");

			ftime(&tp);
			ping_result = do_ping(hosts, hosts_count, items[i].count, items[i].interval, items[i].size, items[i].timeout, error, sizeof(error));

			process_values(items, first_index, i + 1, hosts, hosts_count, &tp, ping_result, error);

			hosts_count = 0;
			first_index = i + 1;
		}
	}
}

static int	parse_key_params(const char *key, const char *host_addr, icmpping_t *icmpping, char **addr, int *count, int *interval, int *size,
		int *timeout, icmppingsec_type_t *type)
{
	char	cmd[MAX_STRING_LEN], params[MAX_STRING_LEN], buffer[MAX_STRING_LEN];
	int	num_params;
	
	if (0 == parse_command(key, cmd, sizeof(cmd), params, sizeof(params)))
		return NOTSUPPORTED;

	num_params = num_param(params);

	if (0 == strcmp(cmd, "icmpping"))
	{
		if (num_params > 5)
			return NOTSUPPORTED;
		*icmpping = ICMPPING;
	}
	else if (0 == strcmp(cmd, "icmppingloss"))
	{
		if (num_params > 5)
			return NOTSUPPORTED;
		*icmpping = ICMPPINGLOSS;
	}
	else if (0 == strcmp(cmd, "icmppingsec"))
	{
		if (num_params > 6)
			return NOTSUPPORTED;
		*icmpping = ICMPPINGSEC;
	}
	else
		return NOTSUPPORTED;

	if (0 != get_param(params, 2, buffer, sizeof(buffer)) || *buffer == '\0')
		*count = 3;
	else
		*count = atoi(buffer);

	if (0 != get_param(params, 3, buffer, sizeof(buffer)) || *buffer == '\0')
		*interval = 0;
	else
		*interval = atoi(buffer);

	if (0 != get_param(params, 4, buffer, sizeof(buffer)) || *buffer == '\0')
		*size = 0;
	else
		*size = atoi(buffer);

	if (0 != get_param(params, 5, buffer, sizeof(buffer)) || *buffer == '\0')
		*timeout = 0;
	else
		*timeout = atoi(buffer);

	if (0 != get_param(params, 6, buffer, sizeof(buffer)) || *buffer == '\0')
		*type = ICMPPINGSEC_AVG;
	else
	{
		if (0 == strcmp(buffer, "min"))
			*type = ICMPPINGSEC_MIN;
		else if (0 == strcmp(buffer, "avg"))
			*type = ICMPPINGSEC_AVG;
		else if (0 == strcmp(buffer, "max"))
			*type = ICMPPINGSEC_MAX;
		else
			return NOTSUPPORTED;
	}

	if (0 != get_param(params, 1, buffer, sizeof(buffer)) || *buffer == '\0')
		*addr = strdup(host_addr);
	else
		*addr = strdup(buffer);

	return SUCCEED;
}

static int	get_icmpping_nearestindex(icmpitem_t *items, int items_count, int count, int interval, int size, int timeout)
{
	int		first_index, last_index, index;
	icmpitem_t	*item;

	if (items_count == 0)
		return 0;

	first_index = 0;
	last_index = items_count - 1;
	while (1)
	{
		index = first_index + (last_index - first_index) / 2;
		item = &items[index];

		if (item->count == count && item->interval == interval && item->size == size && item->timeout == timeout)
			return index;
		else if (last_index == first_index)
		{
			if (item->count < count ||
					(item->count == count && item->interval < interval) ||
					(item->count == count && item->interval == interval && item->size < size) ||
					(item->count == count && item->interval == interval && item->size == size && item->timeout < timeout))
				index++;
			return index;
		}
		else if (item->count < count ||
				(item->count == count && item->interval < interval) ||
				(item->count == count && item->interval == interval && item->size < size) ||
				(item->count == count && item->interval == interval && item->size == size && item->timeout < timeout))
			first_index = index + 1;
		else
			last_index = index;
	}
}

static void	add_icmpping_item(icmpitem_t **items, int *items_alloc, int *items_count, int count, int interval, int size, int timeout,
		zbx_uint64_t itemid, char *addr, icmpping_t icmpping, icmppingsec_type_t type)
{
	int		index;
	icmpitem_t	*item;
	size_t		sz;

	zabbix_log(LOG_LEVEL_DEBUG, "In add_icmpping_item() addr=%s count=%d interval=%d size=%d timeout=%d",
			addr, count, interval, size, timeout);

	index = get_icmpping_nearestindex(*items, *items_count, count, interval, size, timeout);

	if (*items_alloc == *items_count)
	{
		*items_alloc += 4;
		sz = *items_alloc * sizeof(icmpitem_t);
		*items = zbx_realloc(*items, sz);
	}

	memmove(&(*items)[index + 1], &(*items)[index], sizeof(icmpitem_t) * (*items_count - index));

	item = &(*items)[index];
	item->count	= count;
	item->interval	= interval;
	item->size	= size;
	item->timeout	= timeout;
	item->itemid	= itemid;
	item->addr	= addr;
	item->icmpping	= icmpping;
	item->type	= type;

	(*items_count)++;
}

static void	free_hosts(icmpitem_t **items, int *items_count)
{
	int	i;

	zabbix_log(LOG_LEVEL_DEBUG, "In free_hosts()");

	for (i = 0; i < *items_count; i++)
		zbx_free((*items)[i].addr);

	*items_count = 0;
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
static void	get_pinger_hosts(icmpitem_t **items, int *items_alloc, int *items_count, int now)
{
	DB_RESULT		result;
	DB_ROW			row;
	char			istatus[16], *addr = NULL;
	int			count, interval, size, timeout;
	icmpping_t		icmpping;
	icmppingsec_type_t	type;
	zbx_uint64_t		itemid;

	zabbix_log(LOG_LEVEL_DEBUG, "In get_pinger_hosts()");

	if (0 != CONFIG_REFRESH_UNSUPPORTED)
		zbx_snprintf(istatus, sizeof(istatus), "%d,%d",
				ITEM_STATUS_ACTIVE,
				ITEM_STATUS_NOTSUPPORTED);
	else
		zbx_snprintf(istatus, sizeof(istatus), "%d",
				ITEM_STATUS_ACTIVE);

	result = DBselect("select i.itemid,i.key_,case when h.useip=1 then h.ip else h.dns end"
			" from hosts h,items i where " ZBX_SQL_MOD(h.hostid,%d) "=%d"
			" and i.hostid=h.hostid and h.status=%d and i.key_ like '%s%%'"
			" and i.type=%d and i.status in (%s) and i.nextcheck<=%d"
			" and (h.maintenance_status=%d or h.maintenance_type=%d) and" ZBX_COND_NODEID, 
			CONFIG_PINGER_FORKS,
			pinger_num - 1,
			HOST_STATUS_MONITORED,
			SERVER_ICMPPING_KEY,
			ITEM_TYPE_SIMPLE,
			istatus,
			now,
			HOST_MAINTENANCE_STATUS_OFF, MAINTENANCE_TYPE_NORMAL,
			LOCAL_NODE("h.hostid"));

	while (NULL != (row = DBfetch(result))) {
		itemid = zbx_atoui64(row[0]);

		if (SUCCEED == parse_key_params(row[1], row[2], &icmpping, &addr, &count, &interval, &size, &timeout, &type))
			add_icmpping_item(items, items_alloc, items_count, count, interval, size, timeout, itemid, addr, icmpping, type);
		else
		{
			DBbegin();
			DBupdate_item_status_to_notsupported(itemid, NULL);
			DBcommit();
		}
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of get_pinger_hosts():%d", *items_count);
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
			" and h.status=%d and h.hostid=i.hostid and i.key_ like '%s%%'"
			" and i.type=%d and i.status in (%s) and (h.maintenance_status=%d or h.maintenance_type=%d) and" ZBX_COND_NODEID, 
			CONFIG_PINGER_FORKS,
			pinger_num - 1,
			HOST_STATUS_MONITORED,
			SERVER_ICMPPING_KEY,
			ITEM_TYPE_SIMPLE,
			istatus,
			HOST_MAINTENANCE_STATUS_OFF, MAINTENANCE_TYPE_NORMAL,
			LOCAL_NODE("h.hostid"));

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
void main_pinger_loop(int num)
{
	int			now, nextcheck, sleeptime;
	double			sec;
	static icmpitem_t	*items = NULL;
	static int		items_alloc = 4;
	int			items_count = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In main_pinger_loop(num:%d)",
			num);

	pinger_num = num;

	zbx_setproctitle("pinger [connecting to the database]");

	if (NULL == items)
		items = zbx_malloc(items, sizeof(icmpitem_t) * items_alloc);

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;)
	{
		now = time(NULL);
		sec = zbx_time();

		get_pinger_hosts(&items, &items_alloc, &items_count, now);
		process_pinger_hosts(items, items_count);
	
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
				items_count,
				sleeptime);

		free_hosts(&items, &items_count);

		if (sleeptime > 0) {
			zbx_setproctitle("pinger [sleeping for %d seconds]", 
					sleeptime);

			sleep(sleeptime);
		}
	}

	/* Never reached */
	DBclose();
}
