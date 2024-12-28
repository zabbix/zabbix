/*
** Copyright (C) 2001-2024 Zabbix SIA
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

#include "zbxpoller.h"

#include "async_manager.h"

#ifdef HAVE_LIBCURL
#	include "async_httpagent.h"
#	include "zbxasynchttppoller.h"
#	include "zbxhttp.h"
#endif

#ifdef HAVE_NETSNMP
#	include "checks_snmp.h"
#endif

#include "zbxlog.h"
#include "zbxalgo.h"
#include "zbxtimekeeper.h"
#include "zbx_item_constants.h"
#include "zbxpreproc.h"
#include "zbxself.h"
#include "zbxnix.h"
#include "zbx_rtc_constants.h"
#include "zbxrtc.h"
#include "zbx_availability_constants.h"
#include "zbxcacheconfig.h"
#include "zbxcomms.h"
#include "zbxipcservice.h"
#include "zbxthreads.h"
#include "zbxtime.h"
#include "zbxtypes.h"
#include "zbxasyncpoller.h"

#include <event2/dns.h>

#ifndef EVDNS_BASE_INITIALIZE_NAMESERVERS
#	define EVDNS_BASE_INITIALIZE_NAMESERVERS	1
#endif

static void	process_async_result(zbx_dc_item_context_t *item, zbx_poller_config_t *poller_config)
{
	zbx_timespec_t		timespec;
	zbx_interface_status_t	*interface_status;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s' host:'%s' addr:'%s'", __func__, item->key, item->host,
			item->interface.addr);

	zbx_timespec(&timespec);

	/* don't try activating interface if there were no errors detected */
	if (SUCCEED != item->ret || ZBX_INTERFACE_AVAILABLE_TRUE != item->interface.available ||
			0 != item->interface.errors_from || item->version != item->interface.version)
	{
		if (NULL == (interface_status = zbx_hashset_search(&poller_config->interfaces,
				&item->interface.interfaceid)))
		{
			zbx_interface_status_t	interface_status_local = {.interface = item->interface};

			interface_status_local.interface.addr = NULL;
			interface_status = zbx_hashset_insert(&poller_config->interfaces,
					&interface_status_local, sizeof(interface_status_local));
		}
		else
			zabbix_log(LOG_LEVEL_DEBUG, "updating existing interface");

		zbx_free(interface_status->error);
		interface_status->errcode = item->ret;
		interface_status->itemid = item->itemid;
		zbx_strlcpy(interface_status->host, item->host, sizeof(interface_status->host));
		zbx_free(interface_status->key_orig);
		interface_status->key_orig = item->key_orig;
		item->key_orig = NULL;
		interface_status->version = item->version;
	}

	if (SUCCEED == item->ret)
	{
		if (ZBX_IS_RUNNING())
		{
			zbx_preprocess_item_value(item->itemid,
				item->hostid,item->value_type,item->flags,
				&item->result, &timespec, ITEM_STATE_NORMAL, NULL);
		}
	}
	else
	{
		if (ZBX_IS_RUNNING())
		{
			if (NOTSUPPORTED == item->ret || AGENT_ERROR == item->ret || CONFIG_ERROR == item->ret)
			{
				zbx_preprocess_item_value(item->itemid, item->hostid, item->value_type,
					item->flags, NULL, &timespec, ITEM_STATE_NOTSUPPORTED, item->result.msg);
			}
		}

		interface_status->error = item->result.msg;
		SET_MSG_RESULT(&item->result, NULL);
	}

	zbx_async_manager_requeue(poller_config->manager, item->itemid, item->ret, timespec.sec);

	poller_config->processing--;
	poller_config->processed++;

	zabbix_log(LOG_LEVEL_DEBUG, "finished processing itemid:" ZBX_FS_UI64 " processing:%d", item->itemid,
			poller_config->processing);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(item->ret));
}

static void	process_agent_result(void *data)
{
	zbx_agent_context	*agent_context = (zbx_agent_context *)data;
	zbx_poller_config_t	*poller_config = (zbx_poller_config_t *)agent_context->arg;

	process_async_result(&agent_context->item, poller_config);

	zbx_async_check_agent_clean(agent_context);
	zbx_free(agent_context);
}
#ifdef HAVE_NETSNMP
static void	process_snmp_result(void *data)
{
	zbx_snmp_context_t	*snmp_context = (zbx_snmp_context_t *)data;
	zbx_poller_config_t	*poller_config = (zbx_poller_config_t *)zbx_async_check_snmp_get_arg(snmp_context);

	process_async_result(zbx_async_check_snmp_get_item_context(snmp_context), poller_config);

	zbx_async_check_snmp_clean(snmp_context);
}
#endif
#ifdef HAVE_LIBCURL
static void	process_httpagent_result(CURL *easy_handle, CURLcode err, void *arg)
{
	long				response_code;
	char				*status_codes, *error, *out = NULL;
	AGENT_RESULT			result;
	zbx_httpagent_context		*httpagent_context;
	zbx_dc_httpitem_context_t	*item_context;
	zbx_timespec_t			timespec;
	zbx_poller_config_t		*poller_config;
	CURLcode			err_info;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	poller_config = (zbx_poller_config_t *)arg;

	if (CURLE_OK != (err_info = curl_easy_getinfo(easy_handle, CURLINFO_PRIVATE, &httpagent_context)))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		zabbix_log(LOG_LEVEL_CRIT, "Cannot get pointer to private data: %s", curl_easy_strerror(err_info));

		goto fail;
	}

	zbx_timespec(&timespec);

	zbx_init_agent_result(&result);
	status_codes = httpagent_context->item_context.status_codes;
	item_context = &httpagent_context->item_context;

	if (SUCCEED == zbx_http_handle_response(easy_handle, &httpagent_context->http_context, err, &response_code,
			&out, &error) && SUCCEED == zbx_handle_response_code(status_codes, response_code, out, &error))
	{
		SET_TEXT_RESULT(&result, out);
		out = NULL;
		if (ZBX_IS_RUNNING())
		{
			zbx_preprocess_item_value(item_context->itemid, item_context->hostid,item_context->value_type,
					item_context->flags, &result, &timespec, ITEM_STATE_NORMAL, NULL);
		}
	}
	else
	{
		SET_MSG_RESULT(&result, error);
		if (ZBX_IS_RUNNING())
		{
			zbx_preprocess_item_value(item_context->itemid, item_context->hostid, item_context->value_type,
					item_context->flags, NULL, &timespec, ITEM_STATE_NOTSUPPORTED, result.msg);
		}
	}

	zbx_free_agent_result(&result);
	zbx_free(out);

	zbx_async_manager_requeue(poller_config->manager, httpagent_context->item_context.itemid, SUCCEED,
			timespec.sec);

	poller_config->processing--;
	poller_config->processed++;

	zabbix_log(LOG_LEVEL_DEBUG, "finished processing itemid:" ZBX_FS_UI64, httpagent_context->item_context.itemid);

	curl_multi_remove_handle(poller_config->curl_handle, easy_handle);
	zbx_async_check_httpagent_clean(httpagent_context);
	zbx_free(httpagent_context);
fail:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
#endif

static void	async_wake(evutil_socket_t fd, short events, void *arg)
{
	ZBX_UNUSED(fd);
	ZBX_UNUSED(events);
	ZBX_UNUSED(arg);
}

static void	async_initiate_queued_checks(zbx_poller_config_t *poller_config, const char *zbx_progname)
{
	zbx_dc_item_t			*items = NULL;
	AGENT_RESULT			*results;
	int				*errcodes, total = 0;
	zbx_timespec_t			timespec;
	zbx_vector_poller_item_t	poller_items;

	zbx_vector_poller_item_create(&poller_items);
#ifdef HAVE_NETSNMP
	if (1 == poller_config->clear_cache)
	{
		if (0 != poller_config->processing)
		{
			goto exit;
		}
		else
		{
			zbx_unset_snmp_bulkwalk_options();
			zbx_clear_cache_snmp(ZBX_PROCESS_TYPE_SNMP_POLLER, poller_config->process_num);
			zbx_set_snmp_bulkwalk_options(zbx_progname);
			poller_config->clear_cache = 0;
		}
	}
#else
	ZBX_UNUSED(zbx_progname);
#endif

	zbx_async_manager_queue_get(poller_config->manager, &poller_items);

	if (0 != poller_items.values_num)
		zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (int j = 0; j < poller_items.values_num; j++)
	{
		int	num;

		items = poller_items.values[j]->items;
		results = poller_items.values[j]->results;
		errcodes = poller_items.values[j]->errcodes;
		num = poller_items.values[j]->num;

		total += num;

		for (int i = 0; i < num; i++)
		{
			if (SUCCEED != errcodes[i])
				continue;

			if (ITEM_TYPE_HTTPAGENT == items[i].type)
			{
	#ifdef HAVE_LIBCURL
				errcodes[i] = zbx_async_check_httpagent(&items[i], &results[i],
						poller_config->config_source_ip, poller_config->config_ssl_ca_location,
						poller_config->config_ssl_cert_location,
						poller_config->config_ssl_key_location, poller_config->curl_handle);
	#else
				errcodes[i] = NOTSUPPORTED;
				SET_MSG_RESULT(&results[i], zbx_strdup(NULL, "Support for HTTP agent was not compiled"
						" in: missing cURL library"));
	#endif
			}
			else if (ITEM_TYPE_ZABBIX == items[i].type)
			{
				errcodes[i] = zbx_async_check_agent(&items[i], &results[i], process_agent_result,
						poller_config, poller_config, poller_config->base,
						poller_config->dnsbase, poller_config->config_source_ip,
						ZABBIX_ASYNC_RESOLVE_REVERSE_DNS_NO);
			}
			else
			{
	#ifdef HAVE_NETSNMP
				zbx_set_snmp_bulkwalk_options(zbx_progname);

				errcodes[i] = zbx_async_check_snmp(&items[i], &results[i], process_snmp_result,
						poller_config, poller_config, poller_config->base,
						poller_config->dnsbase, poller_config->config_source_ip,
						ZABBIX_ASYNC_RESOLVE_REVERSE_DNS_NO, ZBX_SNMP_DEFAULT_NUMBER_OF_RETRIES);
	#else
				errcodes[i] = NOTSUPPORTED;
				SET_MSG_RESULT(&results[i], zbx_strdup(NULL, "Support for SNMP checks was not compiled"
						"in."));
	#endif
			}

			if (SUCCEED == errcodes[i])
				poller_config->processing++;
		}

		zbx_timespec(&timespec);

		/* process item values */
		for (int i = 0; i < num; i++)
		{
			if (SUCCEED != errcodes[i])
			{
				if (ZBX_IS_RUNNING())
				{
					zbx_preprocess_item_value(items[i].itemid, items[i].host.hostid,
							items[i].value_type, items[i].flags, NULL, &timespec,
							ITEM_STATE_NOTSUPPORTED, results[i].msg);
				}

				zbx_async_manager_requeue(poller_config->manager, items[i].itemid, errcodes[i],
						timespec.sec);
			}
		}

		zbx_poller_item_free(poller_items.values[j]);
	}
#ifdef HAVE_NETSNMP
exit:
#endif
	if (0 != total)
		zabbix_log(LOG_LEVEL_DEBUG, "End of %s(): num:%d", __func__, total);

	poller_config->queued += total;

	zbx_vector_poller_item_destroy(&poller_items);
}

static void	async_wake_cb(void *data)
{
	event_active((struct event *)data, 0, 0);
}

static void	async_timer(evutil_socket_t fd, short events, void *arg)
{
	zbx_poller_config_t	*poller_config = (zbx_poller_config_t *)arg;

	ZBX_UNUSED(fd);
	ZBX_UNUSED(events);

	if (ZBX_IS_RUNNING())
		zbx_async_manager_queue_sync(poller_config->manager);
}

static void	async_poller_init(zbx_poller_config_t *poller_config, zbx_thread_poller_args *poller_args_in,
		int process_num)
{
	struct timeval	tv = {1, 0};
	char		*error = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_hashset_create_ext(&poller_config->interfaces, 100, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC, (zbx_clean_func_t)zbx_interface_status_clean,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	if (NULL == (poller_config->base = event_base_new()))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot initialize event base");
		exit(EXIT_FAILURE);
	}

	poller_config->config_source_ip = poller_args_in->config_comms->config_source_ip;
	poller_config->config_timeout = poller_args_in->config_comms->config_timeout;
	poller_config->config_ssl_ca_location = poller_args_in->config_comms->config_ssl_ca_location;
	poller_config->config_ssl_cert_location = poller_args_in->config_comms->config_ssl_cert_location;
	poller_config->config_ssl_key_location = poller_args_in->config_comms->config_ssl_key_location;
	poller_config->poller_type = poller_args_in->poller_type;
	poller_config->config_unavailable_delay = poller_args_in->config_unavailable_delay;
	poller_config->config_unreachable_delay = poller_args_in->config_unreachable_delay;
	poller_config->config_unreachable_period = poller_args_in->config_unreachable_period;
	poller_config->config_max_concurrent_checks_per_poller =
			poller_args_in->config_max_concurrent_checks_per_poller;
	poller_config->clear_cache = 0;
	poller_config->process_num = process_num;

	if (NULL == (poller_config->async_wake_timer = event_new(poller_config->base, -1, EV_PERSIST, async_wake,
			poller_config)))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot create async items timer event");
		exit(EXIT_FAILURE);
	}

	evtimer_add(poller_config->async_wake_timer, &tv);

	if (NULL == (poller_config->async_timer = event_new(poller_config->base, -1, EV_PERSIST, async_timer,
			poller_config)))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot create async timer event");
		exit(EXIT_FAILURE);
	}

	evtimer_add(poller_config->async_timer, &tv);

	if (NULL == (poller_config->manager = zbx_async_manager_create(1, async_wake_cb,
			(void *)poller_config->async_wake_timer, poller_args_in, &error)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize async manager: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	async_poller_dns_init(zbx_poller_config_t *poller_config, zbx_thread_poller_args *poller_args_in)
{
	char	*timeout;

	if (NULL == (poller_config->dnsbase = evdns_base_new(poller_config->base, EVDNS_BASE_INITIALIZE_NAMESERVERS)))
	{
		int	ret;

		zabbix_log(LOG_LEVEL_ERR, "cannot initialize asynchronous DNS library with resolv.conf");

		if (NULL == (poller_config->dnsbase = evdns_base_new(poller_config->base, 0)))
		{
			zabbix_log(LOG_LEVEL_ERR, "cannot initialize asynchronous DNS library");
			exit(EXIT_FAILURE);
		}

		if (0 != (ret = evdns_base_resolv_conf_parse(poller_config->dnsbase, DNS_OPTIONS_ALL,
				ZBX_RES_CONF_FILE)))
		{
			zabbix_log(LOG_LEVEL_ERR, "cannot parse resolv.conf result: %s", zbx_resolv_conf_errstr(ret));
		}
	}

	timeout = zbx_dsprintf(NULL, "%d", poller_args_in->config_comms->config_timeout);

	if (0 != evdns_base_set_option(poller_config->dnsbase, "timeout:", timeout))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot set timeout to asynchronous DNS library");
		exit(EXIT_FAILURE);
	}

	zbx_free(timeout);
}

static void	async_poller_dns_destroy(zbx_poller_config_t *poller_config)
{
	evdns_base_free(poller_config->dnsbase, 1);
}

static void	async_poller_stop(zbx_poller_config_t *poller_config)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	evtimer_del(poller_config->async_timer);
	evtimer_del(poller_config->async_wake_timer);
	event_base_dispatch(poller_config->base);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	async_poller_destroy(zbx_poller_config_t *poller_config)
{
	zbx_async_manager_free(poller_config->manager);
	event_base_free(poller_config->base);
	zbx_hashset_clear(&poller_config->interfaces);
	zbx_hashset_destroy(&poller_config->interfaces);
}

#ifdef HAVE_LIBCURL
static void	poller_update_selfmon_counter(void *arg)
{
	zbx_poller_config_t	*poller_config = (zbx_poller_config_t *)arg;

	if (ZBX_PROCESS_STATE_IDLE == poller_config->state)
	{
		zbx_update_selfmon_counter(poller_config->info, ZBX_PROCESS_STATE_BUSY);
		poller_config->state = ZBX_PROCESS_STATE_BUSY;
	}
}
#endif

static void	socket_read_event_cb(evutil_socket_t fd, short what, void *arg)
{
	ZBX_UNUSED(fd);
	ZBX_UNUSED(what);
	ZBX_UNUSED(arg);
}

ZBX_THREAD_ENTRY(zbx_async_poller_thread, args)
{
	zbx_thread_poller_args		*poller_args_in = (zbx_thread_poller_args *)(((zbx_thread_args_t *)args)->args);

	time_t				last_stat_time;
#ifdef HAVE_NETSNMP
	time_t				last_snmp_engineid_hk_time = 0;
#endif
	zbx_ipc_async_socket_t		rtc;
	const zbx_thread_info_t		*info = &((zbx_thread_args_t *)args)->info;
	int				msgs_num, server_num = ((zbx_thread_args_t *)args)->info.server_num,
					process_num = ((zbx_thread_args_t *)args)->info.process_num;
	unsigned char			process_type = ((zbx_thread_args_t *)args)->info.process_type,
					poller_type = poller_args_in->poller_type;
	zbx_poller_config_t		poller_config = {.queued = 0, .processed = 0};
	struct event			*rtc_event;
	zbx_uint32_t			rtc_msgs[] = {ZBX_RTC_SNMP_CACHE_RELOAD};
#ifdef HAVE_LIBCURL
	zbx_asynchttppoller_config	*asynchttppoller_config = NULL;
#endif
	msgs_num = ZBX_POLLER_TYPE_SNMP == poller_type ? 1 : 0;

#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			server_num, get_process_type_string(process_type), process_num);

	zbx_setproctitle("%s #%d started", get_process_type_string(process_type), process_num);
	last_stat_time = time(NULL);

	zbx_rtc_subscribe(process_type, process_num, rtc_msgs, msgs_num, poller_args_in->config_comms->config_timeout,
			&rtc);

	async_poller_init(&poller_config, poller_args_in, process_num);
	rtc_event = event_new(poller_config.base, zbx_ipc_client_get_fd(rtc.client), EV_READ | EV_PERSIST,
			socket_read_event_cb, NULL);
	event_add(rtc_event, NULL);

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);
	poller_config.state = ZBX_PROCESS_STATE_BUSY;
	poller_config.info = info;

	if (ZBX_POLLER_TYPE_HTTPAGENT == poller_type)
	{
#ifdef HAVE_LIBCURL
		char	*error = NULL;

		zbx_async_httpagent_init();

		if (NULL == (asynchttppoller_config = zbx_async_httpagent_create(poller_config.base, process_httpagent_result,
				poller_update_selfmon_counter, &poller_config, &error)))
		{
			zabbix_log(LOG_LEVEL_ERR, "zbx_async_httpagent_create() error: %s", error);
			zbx_free(error);
			exit(EXIT_FAILURE);
		}

		poller_config.curl_handle = asynchttppoller_config->curl_handle;
#endif
	}
	else if (ZBX_POLLER_TYPE_AGENT == poller_type)
	{
		async_poller_dns_init(&poller_config, poller_args_in);
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		zbx_tls_init_child(poller_args_in->config_comms->config_tls,
				poller_args_in->zbx_get_program_type_cb_arg,
				zbx_dc_get_psk_by_identity);
#endif
	}
	else
	{
		async_poller_dns_init(&poller_config, poller_args_in);

#ifdef HAVE_NETSNMP
		if (ZBX_POLLER_TYPE_SNMP == poller_type)
		{
			zbx_init_snmp_engineid_cache();
			last_snmp_engineid_hk_time = time(NULL);
		}
#endif
	}

	while (ZBX_IS_RUNNING())
	{
		zbx_uint32_t	rtc_cmd;
		unsigned char	*rtc_data;

		if (ZBX_PROCESS_STATE_BUSY == poller_config.state &&
				poller_config.processing < poller_config.config_max_concurrent_checks_per_poller)
		{
			zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_IDLE);
			poller_config.state = ZBX_PROCESS_STATE_IDLE;
		}

		event_base_loop(poller_config.base, EVLOOP_ONCE);

		if (ZBX_IS_RUNNING())
		{
			if (FAIL == zbx_vps_monitor_capped())
				async_initiate_queued_checks(&poller_config, poller_args_in->progname);

			zbx_async_manager_requeue_flush(poller_config.manager);
			zbx_async_manager_interfaces_flush(poller_config.manager, &poller_config.interfaces);
		}

		if (ZBX_IS_RUNNING())
			zbx_preprocessor_flush();

		if (STAT_INTERVAL <= time(NULL) - last_stat_time)
		{
			zbx_update_env(get_process_type_string(process_type), zbx_time());

			zbx_setproctitle("%s #%d [got %d values, queued %d in 5 sec, awaiting %d%s]",
				get_process_type_string(process_type), process_num, poller_config.processed,
				poller_config.queued, poller_config.processing, zbx_vps_monitor_status());

			poller_config.processed = 0;
			poller_config.queued = 0;
			last_stat_time = time(NULL);
		}

		if (SUCCEED == zbx_rtc_wait(&rtc, info, &rtc_cmd, &rtc_data, 0) && 0 != rtc_cmd)
		{
			if (ZBX_RTC_SHUTDOWN == rtc_cmd)
				break;
#ifdef HAVE_NETSNMP
			switch (rtc_cmd)
			{
				case ZBX_RTC_SNMP_CACHE_RELOAD:

					if (ZBX_POLLER_TYPE_SNMP == poller_type)
						poller_config.clear_cache = 1;
					break;
			}
#endif
		}

#ifdef HAVE_NETSNMP
#define	SNMP_ENGINEID_HK_INTERVAL	86400
		if (ZBX_POLLER_TYPE_SNMP == poller_type && time(NULL) >=
				SNMP_ENGINEID_HK_INTERVAL + last_snmp_engineid_hk_time)
		{
			last_snmp_engineid_hk_time = time(NULL);
			zbx_housekeep_snmp_engineid_cache();
			poller_config.clear_cache = 1;
		}
#undef SNMP_ENGINEID_HK_INTERVAL
#endif
		if (ZBX_POLLER_TYPE_HTTPAGENT != poller_type)
			zbx_async_dns_update_host_addresses(poller_config.dnsbase);
	}

	if (ZBX_POLLER_TYPE_HTTPAGENT != poller_type)
	{
		async_poller_dns_destroy(&poller_config);
	}

	event_del(rtc_event);
	async_poller_stop(&poller_config);

	if (ZBX_POLLER_TYPE_HTTPAGENT == poller_type)
	{
#ifdef HAVE_LIBCURL
		zbx_async_httpagent_clean(asynchttppoller_config);
		zbx_free(asynchttppoller_config);
#endif
	}

	async_poller_destroy(&poller_config);

#ifdef HAVE_NETSNMP
	if (ZBX_POLLER_TYPE_SNMP == poller_type)
		zbx_destroy_snmp_engineid_cache();
#endif

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
#undef STAT_INTERVAL
}
