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
#include "zbxalgo.h"
#include "zbxexpr.h"

typedef struct
{
	zbx_uint64_t		itemid;
	icmpping_t		icmpping;
	icmppingsec_type_t	type;
	char			*addr;
}
zbx_pinger_item_t;

ZBX_VECTOR_DECL(pinger_item, zbx_pinger_item_t)
ZBX_VECTOR_IMPL(pinger_item, zbx_pinger_item_t)

typedef struct
{
	unsigned char			allow_redirect;
	int				count;
	int				interval;
	int				size;
	int				timeout;
	int				retries;
	double				backoff;

	zbx_vector_pinger_item_t	items;
}
zbx_pinger_t;

static zbx_hash_t	pinger_hash(const void *d)
{
	const zbx_pinger_t	*pinger = (const zbx_pinger_t *)d;
	zbx_hash_t		hash = 0;

	hash = ZBX_DEFAULT_HASH_ALGO(&pinger->allow_redirect, sizeof(pinger->allow_redirect), hash);
	hash = ZBX_DEFAULT_HASH_ALGO(&pinger->count, sizeof(pinger->count), hash);
	hash = ZBX_DEFAULT_HASH_ALGO(&pinger->interval, sizeof(pinger->interval), hash);
	hash = ZBX_DEFAULT_HASH_ALGO(&pinger->size, sizeof(pinger->size), hash);
	hash = ZBX_DEFAULT_HASH_ALGO(&pinger->timeout, sizeof(pinger->timeout), hash);
	hash = ZBX_DEFAULT_HASH_ALGO(&pinger->retries, sizeof(pinger->retries), hash);
	hash = ZBX_DEFAULT_HASH_ALGO(&pinger->backoff, sizeof(pinger->backoff), hash);

	return hash;
}

static int	pinger_compare(const void *d1, const void *d2)
{
	const zbx_pinger_t	*pinger1 = (const zbx_pinger_t *)d1;
	const zbx_pinger_t	*pinger2 = (const zbx_pinger_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(pinger1->allow_redirect, pinger2->allow_redirect);
	ZBX_RETURN_IF_NOT_EQUAL(pinger1->count, pinger2->count);
	ZBX_RETURN_IF_NOT_EQUAL(pinger1->interval, pinger2->interval);
	ZBX_RETURN_IF_NOT_EQUAL(pinger1->size, pinger2->size);
	ZBX_RETURN_IF_NOT_EQUAL(pinger1->timeout, pinger2->timeout);
	ZBX_RETURN_IF_NOT_EQUAL(pinger1->retries, pinger2->retries);
	ZBX_RETURN_IF_DBL_NOT_EQUAL(pinger1->backoff, pinger2->backoff);

	return 0;
}

static void	pinger_clear(void *d)
{
	zbx_pinger_t	*pinger = (zbx_pinger_t *)d;

	for (int i = 0; i < pinger->items.values_num; i++)
		zbx_free(pinger->items.values[i].addr);

	zbx_vector_pinger_item_destroy(&pinger->items);
}

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
		zbx_preprocess_item_value(item.itemid, item.value_type, item.flags, item.preprocessing, NULL, ts,
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
		zbx_preprocess_item_value(item.itemid, item.value_type, item.flags, item.preprocessing, &value, ts,
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
static void	process_values(const zbx_vector_pinger_item_t *items, zbx_fping_host_t *hosts, int hosts_count,
		zbx_timespec_t *ts, int ping_result, char *error)
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

		for (int i = 0; i < items->values_num; i++)
		{
			zbx_uint64_t		value_uint64;
			double			value_dbl;
			const zbx_pinger_item_t	*item = &items->values[i];

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
				case ICMPPINGRETRY:
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

static int	pinger_parse_key_params(const char *key, const char *host_addr, zbx_pinger_t *pinger,
		icmpping_t *icmpping, char **addr, icmppingsec_type_t *type, char **error)
{
/* defines for `fping' and `fping6' to successfully process pings */
#define MIN_COUNT	1
#define MAX_COUNT	10000
#define MIN_INTERVAL	20
#define MIN_SIZE	24
#define MAX_SIZE	65507
#define MIN_TIMEOUT	50
#define DEFAULT_RETRIES	1
#define MIN_BACKOFF	1.0
#define MAX_BACKOFF	5.0
#define DEFAULT_BACKOFF	1.0

	const char	*tmp;
	int		ret = NOTSUPPORTED;
	AGENT_REQUEST	request;

	zbx_init_agent_request(&request);

	if (SUCCEED != zbx_parse_item_key(key, &request))
	{
		*error = zbx_strdup(NULL, "Invalid item key format.");
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
	else if (0 == strcmp(get_rkey(&request), ZBX_SERVER_ICMPPINGRETRY_KEY))
	{
		*icmpping = ICMPPINGRETRY;
	}
	else
	{
		*error = zbx_strdup(NULL, "Unsupported pinger key.");
		goto out;
	}

	if (7 < get_rparams_num(&request) || (ICMPPINGSEC != *icmpping && 6 < get_rparams_num(&request)))
	{
		*error = zbx_strdup(NULL, "Too many arguments.");
		goto out;
	}

	if (ICMPPINGRETRY != *icmpping)
	{
		if (NULL == (tmp = get_rparam(&request, 1)) || '\0' == *tmp)
		{
			pinger->count = 3;
		}
		else if (FAIL == zbx_is_uint31(tmp, &pinger->count) || MIN_COUNT > pinger->count ||
				pinger->count > MAX_COUNT)
		{
			*error = zbx_dsprintf(NULL, "Number of packets \"%s\" is not between %d and %d.",
					tmp, MIN_COUNT, MAX_COUNT);
			goto out;
		}

		if (NULL == (tmp = get_rparam(&request, 2)) || '\0' == *tmp)
		{
			pinger->interval = 0;
		}
		else if (FAIL == zbx_is_uint31(tmp, &pinger->interval) || MIN_INTERVAL > pinger->interval)
		{
			*error = zbx_dsprintf(NULL, "Interval \"%s\" should be at least %d.", tmp, MIN_INTERVAL);
			goto out;
		}

		pinger->retries = -1;
		pinger->backoff = -1;
	}
	else
	{
		if (NULL == (tmp = get_rparam(&request, 1)) || '\0' == *tmp)
		{
			pinger->retries = DEFAULT_RETRIES;
		}
		else if (FAIL == zbx_is_uint31(tmp, &pinger->retries))
		{
			*error = zbx_dsprintf(NULL, "Number of retries \"%s\" must be greater or equal to zero.", tmp);
			goto out;
		}

		if (NULL == (tmp = get_rparam(&request, 2)) || '\0' == *tmp)
		{
			pinger->backoff = DEFAULT_BACKOFF;
		}
		else if (SUCCEED != zbx_is_double(tmp, &pinger->backoff) || MIN_BACKOFF > pinger->backoff ||
				MAX_BACKOFF < pinger->backoff)
		{
			*error = zbx_dsprintf(NULL, "Backoff \"%s\" is not between %.1f and %.1f.", tmp,
					MIN_BACKOFF, MAX_BACKOFF);
			goto out;
		}

		pinger->count = -1;
		pinger->interval = -1;
	}

	if (NULL == (tmp = get_rparam(&request, 3)) || '\0' == *tmp)
	{
		pinger->size = 0;
	}
	else if (FAIL == zbx_is_uint31(tmp, &pinger->size) || MIN_SIZE > pinger->size || pinger->size > MAX_SIZE)
	{
		*error = zbx_dsprintf(NULL, "Packet size \"%s\" is not between %d and %d.",
				tmp, MIN_SIZE, MAX_SIZE);
		goto out;
	}

	if (NULL == (tmp = get_rparam(&request, 4)) || '\0' == *tmp)
	{
		pinger->timeout = 0;
	}
	else if (FAIL == zbx_is_uint31(tmp, &pinger->timeout) || MIN_TIMEOUT > pinger->timeout)
	{
		*error = zbx_dsprintf(NULL, "Timeout \"%s\" should be at least %d.", tmp, MIN_TIMEOUT);
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
			*error = zbx_dsprintf(NULL, "Mode \"%s\" is not supported.", tmp);
			goto out;
		}
	}

	if (NULL == (tmp = get_rparam(&request, ((ICMPPINGSEC == *icmpping) ? 6 : 5))) || '\0' == *tmp)
	{
		pinger->allow_redirect = 0;
	}
	else if (0 == strcmp(tmp, "allow_redirect"))
	{
		pinger->allow_redirect = 1;
	}
	else
	{
		*error = zbx_dsprintf(NULL, "\"%s\" is not supported as the \"options\" parameter value"
				".", tmp);
		goto out;
	}

	if (NULL == (tmp = get_rparam(&request, 0)) || '\0' == *tmp)
	{
		if (NULL == host_addr || '\0' == *host_addr)
		{
			*error = zbx_strdup(NULL, "Ping item must have target or host interface specified.");
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
#undef DEFAULT_RETRIES
#undef MIN_BACKOFF
#undef MAX_BACKOFF
#undef DEFAULT_BACKOFF
}

static void	add_icmpping_item(zbx_hashset_t *pinger_items, zbx_pinger_t *pinger_local, zbx_uint64_t itemid,
		char *addr, icmpping_t icmpping, icmppingsec_type_t type)
{
	int			num;
	zbx_pinger_item_t	item;
	zbx_pinger_t		*pinger;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() addr:'%s' count:%d interval:%d size:%d timeout:%d retries:%d backoff:%.1f"
			" allow_redirect:%u",
			__func__, addr, pinger_local->count, pinger_local->interval, pinger_local->size,
			pinger_local->timeout, pinger_local->retries, pinger_local->backoff,
			pinger_local->allow_redirect);

	num = pinger_items->num_data;

	pinger = (zbx_pinger_t *)zbx_hashset_insert(pinger_items, pinger_local, sizeof(zbx_pinger_t));

	if (pinger_items->num_data != num)
	{
		/* new entry was added, initialize */
		zbx_vector_pinger_item_create(&pinger->items);
	}

	item.itemid = itemid;
	item.addr = addr;
	item.icmpping = icmpping;
	item.type = type;
	zbx_vector_pinger_item_append_ptr(&pinger->items, &item);

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
static void	get_pinger_hosts(zbx_hashset_t *pinger_items, int config_timeout)
{
	zbx_dc_item_t		item, *items;
	int			num, errcode = SUCCEED, items_count = 0;
	char			error[MAX_STRING_LEN], *addr = NULL, *errmsg = NULL;
	icmpping_t		icmpping;
	icmppingsec_type_t	type;
	zbx_dc_um_handle_t	*um_handle;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	um_handle = zbx_dc_open_user_macros_masked();

	items = &item;
	num = zbx_dc_config_get_poller_items(ZBX_POLLER_TYPE_PINGER, config_timeout, 0, 0, &items);

	for (int i = 0; i < num; i++)
	{
		zbx_pinger_t	pinger_local;

		ZBX_STRDUP(items[i].key, items[i].key_orig);
		int	rc = zbx_substitute_item_key_params(&items[i].key, error, sizeof(error),
				zbx_item_key_subst_cb, um_handle, &items[i]);

		if (SUCCEED == rc)
		{
			rc = pinger_parse_key_params(items[i].key, items[i].interface.addr, &pinger_local, &icmpping,
					&addr, &type, &errmsg);
		}
		else
			errmsg = zbx_strdup(NULL, error);

		if (SUCCEED == rc)
		{
			add_icmpping_item(pinger_items, &pinger_local, items[i].itemid, addr, icmpping, type);
			items_count++;
		}
		else
		{
			zbx_timespec_t	ts;

			zbx_timespec(&ts);

			items[i].state = ITEM_STATE_NOTSUPPORTED;
			zbx_preprocess_item_value(items[i].itemid, items[i].value_type, items[i].flags,
					items[i].preprocessing, NULL, &ts, items[i].state, errmsg);

			zbx_dc_requeue_items(&items[i].itemid, &ts.sec, &errcode, 1);
			zbx_free(errmsg);
		}

		zbx_free(items[i].key);
	}

	zbx_dc_config_clean_items(items, NULL, num);

	if (items != &item)
		zbx_free(items);

	zbx_preprocessor_flush();

	zbx_dc_close_user_macros(um_handle);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __func__, items_count);
}

static int	fping_host_compare(const void *d1, const void *d2)
{
	const zbx_fping_host_t        *h1 = (const zbx_fping_host_t *)d1;
	const zbx_fping_host_t        *h2 = (const zbx_fping_host_t *)d2;

	return strcmp(h1->addr, h2->addr);
}

static void	add_pinger_host(zbx_vector_fping_host_t *hosts, char *addr)
{
	zbx_fping_host_t	host = {.addr = addr};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() addr:'%s'", __func__, addr);

	if (FAIL == zbx_vector_fping_host_search(hosts, host, fping_host_compare))
		zbx_vector_fping_host_append_ptr(hosts, &host);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static int	process_pinger_hosts(zbx_hashset_t *pinger_items, int process_num, int process_type)
{
	int				ping_result, processed_num = 0;
	char				error[ZBX_ITEM_ERROR_LEN_MAX];
	zbx_vector_fping_host_t		hosts;
	zbx_timespec_t			ts;
	zbx_hashset_iter_t		iter;
	zbx_pinger_t			*pinger;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_fping_host_create(&hosts);
	zbx_vector_fping_host_reserve(&hosts, pinger_items->num_data);

	zbx_hashset_iter_reset(pinger_items, &iter);
	while (NULL != (pinger = (zbx_pinger_t *)zbx_hashset_iter_next(&iter)) && ZBX_IS_RUNNING())
	{
		for (int i = 0; i < pinger->items.values_num; i++)
			add_pinger_host(&hosts, pinger->items.values[i].addr);

		processed_num += pinger->items.values_num;

		zbx_setproctitle("%s #%d [pinging hosts]", get_process_type_string(process_type), process_num);
		zbx_timespec(&ts);

		ping_result = zbx_ping(hosts.values, hosts.values_num, pinger->count, pinger->interval, pinger->size,
				pinger->timeout, pinger->retries, pinger->backoff, pinger->allow_redirect, 0, error,
				sizeof(error));

		if (FAIL != ping_result)
			process_values(&pinger->items, hosts.values, hosts.values_num, &ts, ping_result, error);

		zbx_vector_fping_host_clear(&hosts);
	}

	zbx_vector_fping_host_destroy(&hosts);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return processed_num;
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
	int			nextcheck, sleeptime, itc,
				server_num = ((zbx_thread_args_t *)args)->info.server_num,
				process_num = ((zbx_thread_args_t *)args)->info.process_num;
	double			sec;
	const zbx_thread_info_t	*info = &((zbx_thread_args_t *)args)->info;
	unsigned char		process_type = ((zbx_thread_args_t *)args)->info.process_type;
	zbx_thread_pinger_args	*pinger_args_in = (zbx_thread_pinger_args *)(((zbx_thread_args_t *)args)->args);
	zbx_hashset_t		pinger_items;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			server_num, get_process_type_string(process_type), process_num);

	zbx_setproctitle("%s #%d [starting]", get_process_type_string(process_type), process_num);

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);
	zbx_init_icmpping_env(get_process_type_string(process_type), zbx_get_thread_id());

	zbx_hashset_create_ext(&pinger_items, ZBX_MAX_PINGER_ITEMS, pinger_hash, pinger_compare, pinger_clear,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	while (ZBX_IS_RUNNING())
	{
		sec = zbx_time();
		zbx_update_env(get_process_type_string(process_type), sec);

		if (FAIL == zbx_vps_monitor_capped())
		{
			zbx_setproctitle("%s #%d [getting values]", get_process_type_string(process_type), process_num);

			get_pinger_hosts(&pinger_items, pinger_args_in->config_timeout);
			itc = process_pinger_hosts(&pinger_items, process_num, process_type);
			zbx_hashset_clear(&pinger_items);
			sec = zbx_time() - sec;

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
