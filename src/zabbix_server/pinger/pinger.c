/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "pinger.h"

#include "log.h"
#include "zbxserver.h"
#include "zbxicmpping.h"
#include "daemon.h"
#include "zbxself.h"
#include "preproc.h"

/* defines for `fping' and `fping6' to successfully process pings */
#define MIN_COUNT	1
#define MAX_COUNT	10000
#define MIN_INTERVAL	20
#define MIN_SIZE	24
#define MAX_SIZE	65507
#define MIN_TIMEOUT	50

extern ZBX_THREAD_LOCAL unsigned char	process_type;
extern unsigned char			program_type;
extern ZBX_THREAD_LOCAL int		server_num, process_num;

/******************************************************************************
 *                                                                            *
 * Purpose: process new item value                                            *
 *                                                                            *
 ******************************************************************************/
static void	process_value(zbx_uint64_t itemid, zbx_uint64_t *value_ui64, double *value_dbl,	zbx_timespec_t *ts,
		int ping_result, char *error)
{
	DC_ITEM		item;
	int		errcode;
	AGENT_RESULT	value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	DCconfig_get_items_by_itemids(&item, &itemid, &errcode, 1);

	if (SUCCEED != errcode)
		goto clean;

	if (ITEM_STATUS_ACTIVE != item.status)
		goto clean;

	if (HOST_STATUS_MONITORED != item.host.status)
		goto clean;

	if (NOTSUPPORTED == ping_result)
	{
		item.state = ITEM_STATE_NOTSUPPORTED;
		zbx_preprocess_item_value(item.itemid, item.host.hostid, item.value_type, item.flags, NULL, ts,
				item.state, error);
	}
	else
	{
		init_result(&value);

		if (NULL != value_ui64)
			SET_UI64_RESULT(&value, *value_ui64);
		else
			SET_DBL_RESULT(&value, *value_dbl);

		item.state = ITEM_STATE_NORMAL;
		zbx_preprocess_item_value(item.itemid, item.host.hostid, item.value_type, item.flags, &value, ts,
				item.state, NULL);

		free_result(&value);
	}
clean:
	DCrequeue_items(&item.itemid, &ts->sec, &errcode, 1);

	DCconfig_clean_items(&item, &errcode, 1);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process new item values                                           *
 *                                                                            *
 ******************************************************************************/
static void	process_values(icmpitem_t *items, int first_index, int last_index, ZBX_FPING_HOST *hosts,
		int hosts_count, zbx_timespec_t *ts, int ping_result, char *error)
{
	int		i, h;
	zbx_uint64_t	value_uint64;
	double		value_dbl;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

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
						(char *)"Cannot send ICMP ping packets to this host.");
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

					if (0 < value_dbl && ZBX_FLOAT_PRECISION > value_dbl)
						value_dbl = ZBX_FLOAT_PRECISION;

					process_value(item->itemid, NULL, &value_dbl, ts, SUCCEED, NULL);
					break;
				case ICMPPINGLOSS:
					value_dbl = (100 * (host->cnt - host->rcv)) / (double)host->cnt;
					process_value(item->itemid, NULL, &value_dbl, ts, SUCCEED, NULL);
					break;
			}
		}
	}

	zbx_preprocessor_flush();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static int	parse_key_params(const char *key, const char *host_addr, icmpping_t *icmpping, char **addr, int *count,
		int *interval, int *size, int *timeout, icmppingsec_type_t *type, char *error, int max_error_len)
{
	const char	*tmp;
	int		ret = NOTSUPPORTED;
	AGENT_REQUEST	request;

	init_request(&request);

	if (SUCCEED != parse_item_key(key, &request))
	{
		zbx_snprintf(error, max_error_len, "Invalid item key format.");
		goto out;
	}

	if (0 == strcmp(get_rkey(&request), SERVER_ICMPPING_KEY))
	{
		*icmpping = ICMPPING;
	}
	else if (0 == strcmp(get_rkey(&request), SERVER_ICMPPINGLOSS_KEY))
	{
		*icmpping = ICMPPINGLOSS;
	}
	else if (0 == strcmp(get_rkey(&request), SERVER_ICMPPINGSEC_KEY))
	{
		*icmpping = ICMPPINGSEC;
	}
	else
	{
		zbx_snprintf(error, max_error_len, "Unsupported pinger key.");
		goto out;
	}

	if (6 < get_rparams_num(&request) || (ICMPPINGSEC != *icmpping && 5 < get_rparams_num(&request)))
	{
		zbx_snprintf(error, max_error_len, "Too many arguments.");
		goto out;
	}

	if (NULL == (tmp = get_rparam(&request, 1)) || '\0' == *tmp)
	{
		*count = 3;
	}
	else if (FAIL == is_uint31(tmp, count) || MIN_COUNT > *count || *count > MAX_COUNT)
	{
		zbx_snprintf(error, max_error_len, "Number of packets \"%s\" is not between %d and %d.",
				tmp, MIN_COUNT, MAX_COUNT);
		goto out;
	}

	if (NULL == (tmp = get_rparam(&request, 2)) || '\0' == *tmp)
	{
		*interval = 0;
	}
	else if (FAIL == is_uint31(tmp, interval) || MIN_INTERVAL > *interval)
	{
		zbx_snprintf(error, max_error_len, "Interval \"%s\" should be at least %d.", tmp, MIN_INTERVAL);
		goto out;
	}

	if (NULL == (tmp = get_rparam(&request, 3)) || '\0' == *tmp)
	{
		*size = 0;
	}
	else if (FAIL == is_uint31(tmp, size) || MIN_SIZE > *size || *size > MAX_SIZE)
	{
		zbx_snprintf(error, max_error_len, "Packet size \"%s\" is not between %d and %d.",
				tmp, MIN_SIZE, MAX_SIZE);
		goto out;
	}

	if (NULL == (tmp = get_rparam(&request, 4)) || '\0' == *tmp)
	{
		*timeout = 0;
	}
	else if (FAIL == is_uint31(tmp, timeout) || MIN_TIMEOUT > *timeout)
	{
		zbx_snprintf(error, max_error_len, "Timeout \"%s\" should be at least %d.", tmp, MIN_TIMEOUT);
		goto out;
	}

	if (NULL == (tmp = get_rparam(&request, 5)) || '\0' == *tmp)
	{
		*type = ICMPPINGSEC_AVG;
	}
	else
	{
		if (0 == strcmp(tmp, "min"))
		{
			*type = ICMPPINGSEC_MIN;
		}
		else if (0 == strcmp(tmp, "avg"))
		{
			*type = ICMPPINGSEC_AVG;
		}
		else if (0 == strcmp(tmp, "max"))
		{
			*type = ICMPPINGSEC_MAX;
		}
		else
		{
			zbx_snprintf(error, max_error_len, "Mode \"%s\" is not supported.", tmp);
			goto out;
		}
	}

	if (NULL == (tmp = get_rparam(&request, 0)) || '\0' == *tmp)
		*addr = strdup(host_addr);
	else
		*addr = strdup(tmp);

	ret = SUCCEED;
out:
	free_request(&request);

	return ret;
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
	int		index;
	icmpitem_t	*item;
	size_t		sz;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() addr:'%s' count:%d interval:%d size:%d timeout:%d",
			__func__, addr, count, interval, size, timeout);

	index = get_icmpping_nearestindex(*items, *items_count, count, interval, size, timeout);

	if (*items_alloc == *items_count)
	{
		*items_alloc += 4;
		sz = *items_alloc * sizeof(icmpitem_t);
		*items = (icmpitem_t *)zbx_realloc(*items, sz);
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

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: creates buffer which contains list of hosts to ping               *
 *                                                                            *
 * Return value: SUCCEED - the file was created successfully                  *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static void	get_pinger_hosts(icmpitem_t **icmp_items, int *icmp_items_alloc, int *icmp_items_count)
{
	DC_ITEM			item, *items;
	int			i, num, count, interval, size, timeout, rc, errcode = SUCCEED;
	char			error[MAX_STRING_LEN], *addr = NULL;
	icmpping_t		icmpping;
	icmppingsec_type_t	type;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	items = &item;
	num = DCconfig_get_poller_items(ZBX_POLLER_TYPE_PINGER, &items);

	for (i = 0; i < num; i++)
	{
		ZBX_STRDUP(items[i].key, items[i].key_orig);
		rc = substitute_key_macros(&items[i].key, NULL, &items[i], NULL, NULL, MACRO_TYPE_ITEM_KEY, error,
				sizeof(error));

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

			items[i].state = ITEM_STATE_NOTSUPPORTED;
			zbx_preprocess_item_value(items[i].itemid, items[i].host.hostid, items[i].value_type,
					items[i].flags, NULL, &ts, items[i].state, error);

			DCrequeue_items(&items[i].itemid, &ts.sec, &errcode, 1);
		}

		zbx_free(items[i].key);
	}

	DCconfig_clean_items(items, NULL, num);

	if (items != &item)
		zbx_free(items);

	zbx_preprocessor_flush();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __func__, *icmp_items_count);
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
	int		i;
	size_t		sz;
	ZBX_FPING_HOST	*h;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() addr:'%s'", __func__, addr);

	for (i = 0; i < *hosts_count; i++)
	{
		if (0 == strcmp(addr, (*hosts)[i].addr))
			return;
	}

	(*hosts_count)++;

	if (*hosts_alloc < *hosts_count)
	{
		*hosts_alloc += 4;
		sz = *hosts_alloc * sizeof(ZBX_FPING_HOST);
		*hosts = (ZBX_FPING_HOST *)zbx_realloc(*hosts, sz);
	}

	h = &(*hosts)[*hosts_count - 1];
	memset(h, 0, sizeof(ZBX_FPING_HOST));
	h->addr = addr;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	process_pinger_hosts(icmpitem_t *items, int items_count)
{
	int			i, first_index = 0, ping_result;
	char			error[ITEM_ERROR_LEN_MAX];
	static ZBX_FPING_HOST	*hosts = NULL;
	static int		hosts_alloc = 4;
	int			hosts_count = 0;
	zbx_timespec_t		ts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL == hosts)
		hosts = (ZBX_FPING_HOST *)zbx_malloc(hosts, sizeof(ZBX_FPING_HOST) * hosts_alloc);

	for (i = 0; i < items_count && ZBX_IS_RUNNING(); i++)
	{
		add_pinger_host(&hosts, &hosts_alloc, &hosts_count, items[i].addr);

		if (i == items_count - 1 || items[i].count != items[i + 1].count || items[i].interval != items[i + 1].interval ||
				items[i].size != items[i + 1].size || items[i].timeout != items[i + 1].timeout)
		{
			zbx_setproctitle("%s #%d [pinging hosts]", get_process_type_string(process_type), process_num);

			zbx_timespec(&ts);

			ping_result = zbx_ping(hosts, hosts_count,
						items[i].count, items[i].interval, items[i].size, items[i].timeout,
						error, sizeof(error));

			if (FAIL != ping_result)
				process_values(items, first_index, i + 1, hosts, hosts_count, &ts, ping_result, error);

			hosts_count = 0;
			first_index = i + 1;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: periodically perform ICMP pings                                   *
 *                                                                            *
 * Comments: never returns                                                    *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_ENTRY(pinger_thread, args)
{
	int			nextcheck, sleeptime, items_count = 0, itc;
	double			sec;
	static icmpitem_t	*items = NULL;
	static int		items_alloc = 4;

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

	if (NULL == items)
		items = (icmpitem_t *)zbx_malloc(items, sizeof(icmpitem_t) * items_alloc);

	while (ZBX_IS_RUNNING())
	{
		sec = zbx_time();
		zbx_update_env(sec);

		zbx_setproctitle("%s #%d [getting values]", get_process_type_string(process_type), process_num);

		get_pinger_hosts(&items, &items_alloc, &items_count);
		process_pinger_hosts(items, items_count);
		sec = zbx_time() - sec;
		itc = items_count;

		free_hosts(&items, &items_count);

		nextcheck = DCconfig_get_poller_nextcheck(ZBX_POLLER_TYPE_PINGER);
		sleeptime = calculate_sleeptime(nextcheck, POLLER_DELAY);

		zbx_setproctitle("%s #%d [got %d values in " ZBX_FS_DBL " sec, idle %d sec]",
				get_process_type_string(process_type), process_num, itc, sec, sleeptime);

		zbx_sleep_loop(sleeptime);
	}

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
}
