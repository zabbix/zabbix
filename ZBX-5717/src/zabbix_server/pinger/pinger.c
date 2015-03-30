/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"

#include "dbcache.h"
#include "log.h"
#include "zbxserver.h"
#include "zbxicmpping.h"
#include "daemon.h"
#include "zbxself.h"

#include "pinger.h"

/* defines for `fping' and `fping6' to successfully process pings */
#define MIN_COUNT	1
#define MAX_COUNT	10000
#define MIN_INTERVAL	20
#define MIN_SIZE	24
#define MAX_SIZE	65507
#define MIN_TIMEOUT	50

#define MAX_ITEMS	128

extern unsigned char	process_type;
extern int		process_num;

/******************************************************************************
 *                                                                            *
 * Function: process_value                                                    *
 *                                                                            *
 * Purpose: process new item value                                            *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev, Alexander Vladishev                              *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	process_value(zbx_uint64_t itemid, zbx_uint64_t *value_ui64, double *value_dbl,	zbx_timespec_t *ts,
		int ping_result, char *error)
{
	const char	*__function_name = "process_value";
	DC_ITEM		item;
	int		errcode;
	AGENT_RESULT	value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	DCconfig_get_items_by_itemids(&item, &itemid, &errcode, 1);

	if (SUCCEED != errcode)
		goto clean;

	if (NOTSUPPORTED == ping_result)
	{
		item.status = ITEM_STATUS_NOTSUPPORTED;
		dc_add_history(item.itemid, item.value_type, item.flags, NULL, ts,
				item.status, error, 0, NULL, 0, 0, 0, 0);
	}
	else
	{
		init_result(&value);

		if (NULL != value_ui64)
			SET_UI64_RESULT(&value, *value_ui64);
		else
			SET_DBL_RESULT(&value, *value_dbl);

		item.status = ITEM_STATUS_ACTIVE;
		dc_add_history(item.itemid, item.value_type, item.flags, &value, ts,
				item.status, NULL, 0, NULL, 0, 0, 0, 0);

		free_result(&value);
	}

	DCrequeue_items(&item.itemid, &item.status, &ts->sec, &errcode, 1);
clean:
	DCconfig_clean_items(&item, &errcode, 1);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: process_values                                                   *
 *                                                                            *
 * Purpose: process new item values                                           *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev, Alexander Vladishev                              *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	process_values(icmpitem_t *items, int first_index, int last_index, ZBX_FPING_HOST *hosts,
		int hosts_count, zbx_timespec_t *ts, int ping_result, char *error)
{
	const char	*__function_name = "process_values";
	int		i, h;
	zbx_uint64_t	value_uint64;
	double		value_dbl;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (h = 0; h < hosts_count; h++)
	{
		const ZBX_FPING_HOST	*host = &hosts[h];

		if (NOTSUPPORTED == ping_result)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "host [%s] %s", host->addr, error);
		}
		else
		{
			zabbix_log(LOG_LEVEL_DEBUG, "host [%s] cnt=%d rcv=%d"
					" min=" ZBX_FS_DBL " max=" ZBX_FS_DBL " sum=" ZBX_FS_DBL,
					host->addr, host->cnt, host->rcv, host->min, host->max, host->sum);
		}

		for (i = first_index; i < last_index; i++)
		{
			const icmpitem_t	*item = &items[i];

			if (0 != strcmp(item->addr, host->addr))
				continue;

			if (NOTSUPPORTED == ping_result)
			{
				process_value(item->itemid, NULL, NULL, ts, NOTSUPPORTED, error);
				continue;
			}

			if (0 == host->cnt)
			{
				process_value(item->itemid, NULL, NULL, ts, NOTSUPPORTED,
						"Cannot send ICMP ping packets to this host.");
				continue;
			}

			switch (item->icmpping)
			{
				case ICMPPING:
					value_uint64 = (0 != host->rcv ? 1 : 0);
					process_value(item->itemid, &value_uint64, NULL, ts, SUCCEED, NULL);
					break;
				case ICMPPINGSEC:
					switch (item->type)
					{
						case ICMPPINGSEC_MIN:
							value_dbl = host->min;
							break;
						case ICMPPINGSEC_MAX:
							value_dbl = host->max;
							break;
						case ICMPPINGSEC_AVG:
							value_dbl = (0 != host->rcv ? host->sum / host->rcv : 0);
							break;
					}
					process_value(item->itemid, NULL, &value_dbl, ts, SUCCEED, NULL);
					break;
				case ICMPPINGLOSS:
					value_dbl = (100 * (host->cnt - host->rcv)) / (double)host->cnt;
					process_value(item->itemid, NULL, &value_dbl, ts, SUCCEED, NULL);
					break;
			}
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

static int	parse_key_params(const char *key, const char *host_addr, icmpping_t *icmpping, char **addr, int *count,
		int *interval, int *size, int *timeout, icmppingsec_type_t *type, char *error, int max_error_len)
{
	char	cmd[16], params[MAX_STRING_LEN], buffer[MAX_STRING_LEN];
	int	num_params;

	if (0 == parse_command(key, cmd, sizeof(cmd), params, sizeof(params)))
		return NOTSUPPORTED;

	if (0 == strcmp(cmd, SERVER_ICMPPING_KEY))
	{
		*icmpping = ICMPPING;
	}
	else if (0 == strcmp(cmd, SERVER_ICMPPINGLOSS_KEY))
	{
		*icmpping = ICMPPINGLOSS;
	}
	else if (0 == strcmp(cmd, SERVER_ICMPPINGSEC_KEY))
	{
		*icmpping = ICMPPINGSEC;
	}
	else
	{
		zbx_snprintf(error, max_error_len, "unsupported pinger key");
		return NOTSUPPORTED;
	}

	num_params = num_param(params);

	if (6 < num_params || (ICMPPINGSEC != *icmpping && 5 < num_params))
	{
		zbx_snprintf(error, max_error_len, "too many arguments");
		return NOTSUPPORTED;
	}

	if (0 != get_param(params, 2, buffer, sizeof(buffer)) || '\0' == *buffer)
	{
		*count = 3;
	}
	else if (FAIL == is_uint(buffer) || MIN_COUNT > (*count = atoi(buffer)) || *count > MAX_COUNT)
	{
		zbx_snprintf(error, max_error_len, "number of packets [%s] is not between %d and %d",
				buffer, MIN_COUNT, MAX_COUNT);
		return NOTSUPPORTED;
	}

	if (0 != get_param(params, 3, buffer, sizeof(buffer)) || '\0' == *buffer)
	{
		*interval = 0;
	}
	else if (FAIL == is_uint(buffer) || MIN_INTERVAL > (*interval = atoi(buffer)))
	{
		zbx_snprintf(error, max_error_len, "interval [%s] should be at least %d", buffer, MIN_INTERVAL);
		return NOTSUPPORTED;
	}

	if (0 != get_param(params, 4, buffer, sizeof(buffer)) || '\0' == *buffer)
	{
		*size = 0;
	}
	else if (FAIL == is_uint(buffer) || MIN_SIZE > (*size = atoi(buffer)) || *size > MAX_SIZE)
	{
		zbx_snprintf(error, max_error_len, "packet size [%s] is not between %d and %d",
				buffer, MIN_SIZE, MAX_SIZE);
		return NOTSUPPORTED;
	}

	if (0 != get_param(params, 5, buffer, sizeof(buffer)) || '\0' == *buffer)
		*timeout = 0;
	else if (FAIL == is_uint(buffer) || MIN_TIMEOUT > (*timeout = atoi(buffer)))
	{
		zbx_snprintf(error, max_error_len, "timeout [%s] should be at least %d", buffer, MIN_TIMEOUT);
		return NOTSUPPORTED;
	}

	if (0 != get_param(params, 6, buffer, sizeof(buffer)) || '\0' == *buffer)
		*type = ICMPPINGSEC_AVG;
	else
	{
		if (0 == strcmp(buffer, "min"))
		{
			*type = ICMPPINGSEC_MIN;
		}
		else if (0 == strcmp(buffer, "avg"))
		{
			*type = ICMPPINGSEC_AVG;
		}
		else if (0 == strcmp(buffer, "max"))
		{
			*type = ICMPPINGSEC_MAX;
		}
		else
		{
			zbx_snprintf(error, max_error_len, "mode [%s] is not supported", buffer);
			return NOTSUPPORTED;
		}
	}

	if (0 != get_param(params, 1, buffer, sizeof(buffer)) || '\0' == *buffer)
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

static void	add_icmpping_item(icmpitem_t **items, int *items_alloc, int *items_count, int count, int interval,
		int size, int timeout, zbx_uint64_t itemid, char *addr, icmpping_t icmpping, icmppingsec_type_t type)
{
	const char	*__function_name = "add_icmpping_item";
	int		index;
	icmpitem_t	*item;
	size_t		sz;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() addr:'%s' count:%d interval:%d size:%d timeout:%d",
			__function_name, addr, count, interval, size, timeout);

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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: get_pinger_hosts                                                 *
 *                                                                            *
 * Purpose: creates buffer which contains list of hosts to ping               *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCEED - the file was created successfully                  *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Author: Alexei Vladishev, Alexander Vladishev                              *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	get_pinger_hosts(icmpitem_t **icmp_items, int *icmp_items_alloc, int *icmp_items_count)
{
	const char		*__function_name = "get_pinger_hosts";
	DC_ITEM			items[MAX_ITEMS];
	int			i, num, count, interval, size, timeout, rc, errcode = SUCCEED;
	char			error[MAX_STRING_LEN], *addr = NULL;
	icmpping_t		icmpping;
	icmppingsec_type_t	type;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	num = DCconfig_get_poller_items(ZBX_POLLER_TYPE_PINGER, items, MAX_ITEMS);

	for (i = 0; i < num; i++)
	{
		ZBX_STRDUP(items[i].key, items[i].key_orig);
		rc = substitute_key_macros(&items[i].key, NULL, &items[i], NULL, MACRO_TYPE_ITEM_KEY,
				error, sizeof(error));

		if (SUCCEED == rc)
		{
			rc = parse_key_params(items[i].key, items[i].interface.addr, &icmpping, &addr, &count,
					&interval, &size, &timeout, &type, error, sizeof(error));
		}

		if (SUCCEED == rc)
		{
			add_icmpping_item(icmp_items, icmp_items_alloc, icmp_items_count, count, interval, size,
				timeout, items[i].itemid, addr, icmpping, type);
		}
		else
		{
			zbx_timespec_t	ts;

			zbx_timespec(&ts);

			items[i].status = ITEM_STATUS_NOTSUPPORTED;
			dc_add_history(items[i].itemid, items[i].value_type, items[i].flags, NULL, &ts,
					items[i].status, error, 0, NULL, 0, 0, 0, 0);

			DCrequeue_items(&items[i].itemid, &items[i].status, &ts.sec, &errcode, 1);
		}

		zbx_free(items[i].key);
	}

	DCconfig_clean_items(items, NULL, num);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, *icmp_items_count);
}

static void	free_hosts(icmpitem_t **items, int *items_count)
{
	int	i;

	for (i = 0; i < *items_count; i++)
		zbx_free((*items)[i].addr);

	*items_count = 0;
}

static void	add_pinger_host(ZBX_FPING_HOST **hosts, int *hosts_alloc, int *hosts_count, char *addr)
{
	const char	*__function_name = "add_pinger_host";

	int		i;
	size_t		sz;
	ZBX_FPING_HOST	*h;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() addr:'%s'", __function_name, addr);

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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	process_pinger_hosts(icmpitem_t *items, int items_count)
{
	const char		*__function_name = "process_pinger_hosts";
	int			i, first_index = 0, ping_result;
	char			error[ITEM_ERROR_LEN_MAX];
	static ZBX_FPING_HOST	*hosts = NULL;
	static int		hosts_alloc = 4;
	int			hosts_count = 0;
	zbx_timespec_t		ts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (NULL == hosts)
		hosts = zbx_malloc(hosts, sizeof(ZBX_FPING_HOST) * hosts_alloc);

	for (i = 0; i < items_count; i++)
	{
		add_pinger_host(&hosts, &hosts_alloc, &hosts_count, items[i].addr);

		if (i == items_count - 1 || items[i].count != items[i + 1].count || items[i].interval != items[i + 1].interval ||
				items[i].size != items[i + 1].size || items[i].timeout != items[i + 1].timeout)
		{
			zbx_setproctitle("%s [pinging hosts]", get_process_type_string(process_type));

			zbx_timespec(&ts);

			ping_result = do_ping(hosts, hosts_count,
						items[i].count, items[i].interval, items[i].size, items[i].timeout,
						error, sizeof(error));

			process_values(items, first_index, i + 1, hosts, hosts_count, &ts, ping_result, error);

			hosts_count = 0;
			first_index = i + 1;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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
void	main_pinger_loop()
{
	int			nextcheck, sleeptime;
	double			sec;
	static icmpitem_t	*items = NULL;
	static int		items_alloc = 4;
	int			items_count = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In main_pinger_loop() process_num:%d", process_num);

	if (NULL == items)
		items = zbx_malloc(items, sizeof(icmpitem_t) * items_alloc);

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;)
	{
		zbx_setproctitle("%s [getting values]", get_process_type_string(process_type));

		sec = zbx_time();
		get_pinger_hosts(&items, &items_alloc, &items_count);
		process_pinger_hosts(items, items_count);
		sec = zbx_time() - sec;

		zabbix_log(LOG_LEVEL_DEBUG, "%s #%d spent " ZBX_FS_DBL " seconds while processing %d items",
				get_process_type_string(process_type), process_num, sec, items_count);

		free_hosts(&items, &items_count);

		nextcheck = DCconfig_get_poller_nextcheck(ZBX_POLLER_TYPE_PINGER);
		sleeptime = calculate_sleeptime(nextcheck, POLLER_DELAY);

		zbx_sleep_loop(sleeptime);
	}
}
