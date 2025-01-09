/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "zbxpinger.h"
#include "zbxexpression.h"

#include "zbxlog.h"
#include "zbxcacheconfig.h"
#include "zbxicmpping.h"
#include "zbxnix.h"
#include "zbxself.h"
#include "zbxtime.h"
#include "zbxnum.h"
#include "zbxsysinfo.h"
#include "zbx_item_constants.h"
#include "zbx_host_constants.h"
#include "zbxpreproc.h"
#include "zbxdbhigh.h"
#include "zbxthreads.h"
#include "zbxtimekeeper.h"

/******************************************************************************
 *                                                                            *
 * Purpose: processes new item value                                          *
 *                                                                            *
 ******************************************************************************/
static void	process_value(zbx_uint64_t itemid, zbx_uint64_t *value_ui64, double *value_dbl,	zbx_timespec_t *ts,
		int ping_result, char *error)
{
	zbx_dc_item_t	item;
	int		errcode;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_dc_config_get_items_by_itemids(&item, &itemid, &errcode, 1);

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
		AGENT_RESULT	value;

		zbx_init_agent_result(&value);

		if (NULL != value_ui64)
			SET_UI64_RESULT(&value, *value_ui64);
		else
			SET_DBL_RESULT(&value, *value_dbl);

		item.state = ITEM_STATE_NORMAL;
		zbx_preprocess_item_value(item.itemid, item.host.hostid, item.value_type, item.flags, &value, ts,
				item.state, NULL);

		zbx_free_agent_result(&value);
	}
clean:
	zbx_dc_requeue_items(&item.itemid, &ts->sec, &errcode, 1);

	zbx_dc_config_clean_items(&item, &errcode, 1);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes new item values                                         *
 *                                                                            *
 ******************************************************************************/
static void	process_values(icmpitem_t *items, int first_index, int last_index, zbx_fping_host_t *hosts,
		int hosts_count, zbx_timespec_t *ts, int ping_result, char *error)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (int h = 0; h < hosts_count; h++)
	{
		const zbx_fping_host_t	*host = &hosts[h];

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

		for (int i = first_index; i < last_index; i++)
		{
			zbx_uint64_t		value_uint64;
			double			value_dbl;
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

					if (0 < value_dbl && zbx_get_float_epsilon() > value_dbl)
						value_dbl = zbx_get_float_epsilon();

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

static int	zbx_parse_key_params(const char *key, const char *host_addr, icmpping_t *icmpping, char **addr,
		int *count, int *interval, int *size, int *timeout, icmppingsec_type_t *type,
		unsigned char *allow_redirect, char *error, int max_error_len)
{
/* defines for `fping' and `fping6' to successfully process pings */
#define MIN_COUNT	1
#define MAX_COUNT	10000
#define MIN_INTERVAL	20
#define MIN_SIZE	24
#define MAX_SIZE	65507
#define MIN_TIMEOUT	50
	const char	*tmp;
	int		ret = NOTSUPPORTED;
	AGENT_REQUEST	request;

	zbx_init_agent_request(&request);

	if (SUCCEED != zbx_parse_item_key(key, &request))
	{
		zbx_snprintf(error, (size_t)max_error_len, "Invalid item key format.");
		goto out;
	}

	if (0 == strcmp(get_rkey(&request), ZBX_SERVER_ICMPPING_KEY))
	{
		*icmpping = ICMPPING;
	}
	else if (0 == strcmp(get_rkey(&request), ZBX_SERVER_ICMPPINGLOSS_KEY))
	{
		*icmpping = ICMPPINGLOSS;
	}
	else if (0 == strcmp(get_rkey(&request), ZBX_SERVER_ICMPPINGSEC_KEY))
	{
		*icmpping = ICMPPINGSEC;
	}
	else
	{
		zbx_snprintf(error, (size_t)max_error_len, "Unsupported pinger key.");
		goto out;
	}

	if (7 < get_rparams_num(&request) || (ICMPPINGSEC != *icmpping && 6 < get_rparams_num(&request)))
	{
		zbx_snprintf(error, (size_t)max_error_len, "Too many arguments.");
		goto out;
	}

	if (NULL == (tmp = get_rparam(&request, 1)) || '\0' == *tmp)
	{
		*count = 3;
	}
	else if (FAIL == zbx_is_uint31(tmp, count) || MIN_COUNT > *count || *count > MAX_COUNT)
	{
		zbx_snprintf(error, (size_t)max_error_len, "Number of packets \"%s\" is not between %d and %d.",
				tmp, MIN_COUNT, MAX_COUNT);
		goto out;
	}

	if (NULL == (tmp = get_rparam(&request, 2)) || '\0' == *tmp)
	{
		*interval = 0;
	}
	else if (FAIL == zbx_is_uint31(tmp, interval) || MIN_INTERVAL > *interval)
	{
		zbx_snprintf(error, (size_t)max_error_len, "Interval \"%s\" should be at least %d.", tmp, MIN_INTERVAL);
		goto out;
	}

	if (NULL == (tmp = get_rparam(&request, 3)) || '\0' == *tmp)
	{
		*size = 0;
	}
	else if (FAIL == zbx_is_uint31(tmp, size) || MIN_SIZE > *size || *size > MAX_SIZE)
	{
		zbx_snprintf(error, (size_t)max_error_len, "Packet size \"%s\" is not between %d and %d.",
				tmp, MIN_SIZE, MAX_SIZE);
		goto out;
	}

	if (NULL == (tmp = get_rparam(&request, 4)) || '\0' == *tmp)
	{
		*timeout = 0;
	}
	else if (FAIL == zbx_is_uint31(tmp, timeout) || MIN_TIMEOUT > *timeout)
	{
		zbx_snprintf(error, (size_t)max_error_len, "Timeout \"%s\" should be at least %d.", tmp, MIN_TIMEOUT);
		goto out;
	}


	if (ICMPPINGSEC != *icmpping || NULL == (tmp = get_rparam(&request, 5)) || '\0' == *tmp)
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
			zbx_snprintf(error, (size_t)max_error_len, "Mode \"%s\" is not supported.", tmp);
			goto out;
		}
	}

	if (NULL == (tmp = get_rparam(&request, ((ICMPPINGSEC == *icmpping) ? 6 : 5))) || '\0' == *tmp)
	{
		*allow_redirect = 0;
	}
	else if (0 == strcmp(tmp, "allow_redirect"))
	{
		*allow_redirect = 1;
	}
	else
	{
		zbx_snprintf(error, (size_t)max_error_len, "\"%s\" is not supported as the \"options\" parameter value"
				".", tmp);
		goto out;
	}

	if (NULL == (tmp = get_rparam(&request, 0)) || '\0' == *tmp)
	{
		if (NULL == host_addr || '\0' == *host_addr)
		{
			zbx_snprintf(error, (size_t)max_error_len,
						"Ping item must have target or host interface specified.");
			goto out;
		}
		*addr = strdup(host_addr);
	}
	else
		*addr = strdup(tmp);

	ret = SUCCEED;
out:
	zbx_free_agent_request(&request);

	return ret;
#undef MIN_COUNT
#undef MAX_COUNT
#undef MIN_INTERVAL
#undef MIN_SIZE
#undef MAX_SIZE
#undef MIN_TIMEOUT
}

static int	get_icmpping_nearestindex(icmpitem_t *items, int items_count, int count, int interval, int size,
		int timeout)
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

		if (item->count == count && item->interval == interval && item->size == size &&
				item->timeout == timeout)
		{
			return index;
		}
		else if (last_index == first_index)
		{
			if (item->count < count ||
					(item->count == count && item->interval < interval) ||
					(item->count == count && item->interval == interval && item->size < size) ||
					(item->count == count && item->interval == interval && item->size == size &&
					item->timeout < timeout))
			{
				index++;
			}

			return index;
		}
		else if (item->count < count ||
				(item->count == count && item->interval < interval) ||
				(item->count == count && item->interval == interval && item->size < size) ||
				(item->count == count && item->interval == interval && item->size == size &&
				item->timeout < timeout))
		{
			first_index = index + 1;
		}
		else
		{
			last_index = index;
		}
	}
}

static void	add_icmpping_item(icmpitem_t **items, int *items_alloc, int *items_count, int count, int interval,
		int size, int timeout, zbx_uint64_t itemid, char *addr, icmpping_t icmpping, icmppingsec_type_t type,
		unsigned char allow_redirect)
{
	int		index;
	icmpitem_t	*item;
	size_t		sz;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() addr:'%s' count:%d interval:%d size:%d timeout:%d allow_redirect:%u",
			__func__, addr, count, interval, size, timeout, allow_redirect);

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
	item->allow_redirect = allow_redirect;

	(*items_count)++;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: creates buffer which contains list of hosts to ping               *
 *                                                                            *
 * Return value: SUCCEED - file was created successfully                      *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static void	get_pinger_hosts(icmpitem_t **icmp_items, int *icmp_items_alloc, int *icmp_items_count,
		int config_timeout)
{
	zbx_dc_item_t		item, *items;
	int			num, count, interval, size, timeout, errcode = SUCCEED;
	char			error[MAX_STRING_LEN], *addr = NULL;
	unsigned char		allow_redirect;
	icmpping_t		icmpping;
	icmppingsec_type_t	type;
	zbx_dc_um_handle_t	*um_handle;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	um_handle = zbx_dc_open_user_macros();

	items = &item;
	num = zbx_dc_config_get_poller_items(ZBX_POLLER_TYPE_PINGER, config_timeout, 0, 0, &items);

	for (int i = 0; i < num; i++)
	{
		ZBX_STRDUP(items[i].key, items[i].key_orig);
		int	rc = zbx_substitute_key_macros(&items[i].key, NULL, &items[i], NULL, NULL,
				ZBX_MACRO_TYPE_ITEM_KEY, error, sizeof(error));

		if (SUCCEED == rc)
		{
			rc = zbx_parse_key_params(items[i].key, items[i].interface.addr, &icmpping, &addr, &count,
					&interval, &size, &timeout, &type, &allow_redirect, error, sizeof(error));
		}

		if (SUCCEED == rc)
		{
			add_icmpping_item(icmp_items, icmp_items_alloc, icmp_items_count, count, interval, size,
				timeout, items[i].itemid, addr, icmpping, type, allow_redirect);
		}
		else
		{
			zbx_timespec_t	ts;

			zbx_timespec(&ts);

			items[i].state = ITEM_STATE_NOTSUPPORTED;
			zbx_preprocess_item_value(items[i].itemid, items[i].host.hostid, items[i].value_type,
					items[i].flags, NULL, &ts, items[i].state, error);

			zbx_dc_requeue_items(&items[i].itemid, &ts.sec, &errcode, 1);
		}

		zbx_free(items[i].key);
	}

	zbx_dc_config_clean_items(items, NULL, num);

	if (items != &item)
		zbx_free(items);

	zbx_preprocessor_flush();

	zbx_dc_close_user_macros(um_handle);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __func__, *icmp_items_count);
}

static void	free_hosts(icmpitem_t **items, int *items_count)
{
	for (int i = 0; i < *items_count; i++)
		zbx_free((*items)[i].addr);

	*items_count = 0;
}

static void	add_pinger_host(zbx_fping_host_t **hosts, int *hosts_alloc, int *hosts_count, char *addr)
{
	zbx_fping_host_t	*h;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() addr:'%s'", __func__, addr);

	for (int i = 0; i < *hosts_count; i++)
	{
		if (0 == strcmp(addr, (*hosts)[i].addr))
			return;
	}

	(*hosts_count)++;

	if (*hosts_alloc < *hosts_count)
	{
		size_t	sz;

		*hosts_alloc += 4;
		sz = *hosts_alloc * sizeof(zbx_fping_host_t);
		*hosts = (zbx_fping_host_t *)zbx_realloc(*hosts, sz);
	}

	h = &(*hosts)[*hosts_count - 1];
	memset(h, 0, sizeof(zbx_fping_host_t));
	h->addr = addr;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	process_pinger_hosts(icmpitem_t *items, int items_count, int process_num, int process_type)
{
	int			ping_result, first_index = 0;
	char			error[ZBX_ITEM_ERROR_LEN_MAX];
	static zbx_fping_host_t	*hosts = NULL;
	static int		hosts_alloc = 4;
	int			hosts_count = 0;
	zbx_timespec_t		ts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL == hosts)
		hosts = (zbx_fping_host_t *)zbx_malloc(hosts, sizeof(zbx_fping_host_t) * hosts_alloc);

	for (int i = 0; i < items_count && ZBX_IS_RUNNING(); i++)
	{
		add_pinger_host(&hosts, &hosts_alloc, &hosts_count, items[i].addr);

		if (i == items_count - 1 || items[i].count != items[i + 1].count ||
				items[i].interval != items[i + 1].interval || items[i].size != items[i + 1].size ||
				items[i].timeout != items[i + 1].timeout ||
				items[i].allow_redirect != items[i + 1].allow_redirect)
		{
			zbx_setproctitle("%s #%d [pinging hosts]", get_process_type_string(process_type), process_num);

			zbx_timespec(&ts);

			ping_result = zbx_ping(hosts, hosts_count,
						items[i].count, items[i].interval, items[i].size, items[i].timeout,
						items[i].allow_redirect, 0, error, sizeof(error));

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
 * Purpose: periodically performs ICMP pings                                  *
 *                                                                            *
 * Comments: never returns                                                    *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_ENTRY(zbx_pinger_thread, args)
{
	int			nextcheck, sleeptime, itc, items_count = 0,
				server_num = ((zbx_thread_args_t *)args)->info.server_num,
				process_num = ((zbx_thread_args_t *)args)->info.process_num;
	double			sec;
	static icmpitem_t	*items = NULL;
	static int		items_alloc = 4;
	const zbx_thread_info_t	*info = &((zbx_thread_args_t *)args)->info;
	unsigned char		process_type = ((zbx_thread_args_t *)args)->info.process_type;
	zbx_thread_pinger_args	*pinger_args_in = (zbx_thread_pinger_args *)(((zbx_thread_args_t *)args)->args);

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			server_num, get_process_type_string(process_type), process_num);

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);
	zbx_init_icmpping_env(get_process_type_string(process_type), zbx_get_thread_id());

	if (NULL == items)
		items = (icmpitem_t *)zbx_malloc(items, sizeof(icmpitem_t) * items_alloc);

	while (ZBX_IS_RUNNING())
	{
		sec = zbx_time();
		zbx_update_env(get_process_type_string(process_type), sec);

		if (FAIL == zbx_vps_monitor_capped())
		{
			zbx_setproctitle("%s #%d [getting values]", get_process_type_string(process_type), process_num);

			get_pinger_hosts(&items, &items_alloc, &items_count, pinger_args_in->config_timeout);
			process_pinger_hosts(items, items_count, process_num, process_type);
			sec = zbx_time() - sec;
			itc = items_count;

			free_hosts(&items, &items_count);

			nextcheck = zbx_dc_config_get_poller_nextcheck(ZBX_POLLER_TYPE_PINGER);
			sleeptime = zbx_calculate_sleeptime(nextcheck, POLLER_DELAY);
		}
		else
		{
			sec = 0;
			itc = 0;
			sleeptime = POLLER_DELAY;
		}

		zbx_setproctitle("%s #%d [got %d values in " ZBX_FS_DBL " sec, idle %d sec%s]",
				get_process_type_string(process_type), process_num, itc, sec, sleeptime,
				zbx_vps_monitor_status());

		zbx_sleep_loop(info, sleeptime);
	}

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
}
