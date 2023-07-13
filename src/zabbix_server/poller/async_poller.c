#include "zbxlog.h"
#include "zbxalgo.h"
#include "zbxcommon.h"
#include "poller.h"
#include "zbxserver.h"
#include "zbx_item_constants.h"
#include "zbxpreproc.h"
#include "zbxself.h"
#include "zbxnix.h"
#include "zbx_rtc_constants.h"
#include "zbxrtc.h"
#include "async_httpagent.h"
#include "async_agent.h"
#include "zbx_availability_constants.h"
#include "zbxasynchttppoller.h"
#include "zbxavailability.h"
#include "zbxcacheconfig.h"
#include "zbxcomms.h"
#include "zbxhttp.h"
#include "zbxipcservice.h"
#include "zbxthreads.h"
#include "zbxtime.h"

ZBX_VECTOR_IMPL(int32, int)

typedef struct
{
	zbx_dc_interface_t	interface;
	int			errcode;
	char			*error;
	zbx_uint64_t		itemid;
	char			host[ZBX_HOSTNAME_BUF_LEN];
	char			*key_orig;
}
zbx_interface_status_t;

static void	process_agent_result(void *data)
{
	zbx_agent_context	*agent_context = (zbx_agent_context *)data;
	zbx_timespec_t		timespec;
	zbx_interface_status_t	*interface_status;
	int			ret;
	zbx_poller_config_t	*poller_config = (zbx_poller_config_t *)agent_context->arg;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() key:'%s' host:'%s' addr:'%s' conn:'%s'", __func__, agent_context->key,
			agent_context->host, agent_context->interface.addr,
			zbx_tcp_connection_type_name(agent_context->tls_connect));

	zbx_timespec(&timespec);

	/* don't try activating interface if there were no errors detected */
	if (SUCCEED != agent_context->ret || ZBX_INTERFACE_AVAILABLE_TRUE != agent_context->interface.available ||
			0 != agent_context->interface.errors_from)
	{
		if (NULL == (interface_status = zbx_hashset_search(&poller_config->interfaces,
				&agent_context->interface.interfaceid)))
		{
			zbx_interface_status_t	interface_status_local = {.interface = agent_context->interface};

			interface_status_local.interface.addr = NULL;
			interface_status = zbx_hashset_insert(&poller_config->interfaces,
					&interface_status_local, sizeof(interface_status_local));
		}
		else
			zabbix_log(LOG_LEVEL_DEBUG, "updating existing interface");

		zbx_free(interface_status->error);
		interface_status->errcode = agent_context->ret;
		interface_status->itemid = agent_context->itemid;
		zbx_strlcpy(interface_status->host, agent_context->host, sizeof(interface_status->host));
		zbx_free(interface_status->key_orig);
		interface_status->key_orig = agent_context->key_orig;
		agent_context->key_orig = NULL;
	}

	if (SUCCEED == agent_context->ret)
	{
		if (ZBX_IS_RUNNING())
		{
			zbx_preprocess_item_value(agent_context->itemid,
				agent_context->hostid,agent_context->value_type, agent_context->flags,
				&agent_context->result, &timespec, ITEM_STATE_NORMAL, NULL);
		}
	}
	else
	{
		if (ZBX_IS_RUNNING())
		{
			zbx_preprocess_item_value(agent_context->itemid, agent_context->hostid,
					agent_context->value_type, agent_context->flags, NULL, &timespec,
					ITEM_STATE_NOTSUPPORTED, agent_context->result.msg);
		}
		interface_status->error = agent_context->result.msg;
		SET_MSG_RESULT(&agent_context->result, NULL);
	}

	zbx_vector_uint64_append(&poller_config->itemids, agent_context->itemid);
	zbx_vector_int32_append(&poller_config->errcodes, agent_context->ret);
	zbx_vector_int32_append(&poller_config->lastclocks, timespec.sec);

	poller_config->processing--;
	poller_config->processed++;

	zabbix_log(LOG_LEVEL_DEBUG, "finished processing itemid:" ZBX_FS_UI64, agent_context->itemid);
	ret = agent_context->ret;

	zbx_async_check_agent_clean(agent_context);
	zbx_free(agent_context);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));
}
#ifdef HAVE_LIBCURL
static void	process_httpagent_result(CURL *easy_handle, CURLcode err, void *arg)
{
	long			response_code;
	char			*error, *out = NULL;
	AGENT_RESULT		result;
	char			*status_codes;
	zbx_httpagent_context	*httpagent_context;
	zbx_dc_item_context_t	*item_context;
	zbx_timespec_t		timespec;
	zbx_poller_config_t	*poller_config;
	CURLcode		err_info;

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

	zbx_vector_uint64_append(&poller_config->itemids, httpagent_context->item_context.itemid);
	zbx_vector_int32_append(&poller_config->errcodes, SUCCEED);
	zbx_vector_int32_append(&poller_config->lastclocks, timespec.sec);

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

static void	poller_update_interfaces(zbx_poller_config_t *poller_config)
{
	zbx_hashset_iter_t	iter;
	zbx_interface_status_t	*interface_status;
	unsigned char		*data = NULL;
	size_t			data_alloc = 0, data_offset = 0;
	zbx_timespec_t		timespec;

	if (0 == poller_config->interfaces.num_data)
		return;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() num:%d", __func__, poller_config->interfaces.num_data);

	zbx_timespec(&timespec);

	zbx_hashset_iter_reset(&poller_config->interfaces, &iter);

	while (NULL != (interface_status = (zbx_interface_status_t *)zbx_hashset_iter_next(&iter)))
	{
		switch (interface_status->errcode)
		{
			case SUCCEED:
			case NOTSUPPORTED:
			case AGENT_ERROR:
				zbx_activate_item_interface(&timespec, &interface_status->interface,
						interface_status->itemid, ITEM_TYPE_ZABBIX,
						interface_status->host, &data, &data_alloc, &data_offset);
				break;
			case NETWORK_ERROR:
			case GATEWAY_ERROR:
			case TIMEOUT_ERROR:
				zbx_deactivate_item_interface(&timespec, &interface_status->interface,
						interface_status->itemid,
						ITEM_TYPE_ZABBIX, interface_status->host,
						interface_status->key_orig, &data, &data_alloc, &data_offset,
						poller_config->config_unavailable_delay,
						poller_config->config_unreachable_period,
						poller_config->config_unreachable_delay,
						interface_status->error);
				break;
			case CONFIG_ERROR:
				/* nothing to do */
				break;
			case SIG_ERROR:
				/* nothing to do, execution was forcibly interrupted by signal */
				break;
			default:
				zbx_error("unknown response code returned: %d", interface_status->errcode);
				THIS_SHOULD_NEVER_HAPPEN;
		}

	}

	zbx_hashset_clear(&poller_config->interfaces);

	if (NULL != data)
	{
		zbx_availability_send(ZBX_IPC_AVAILABILITY_REQUEST, data, (zbx_uint32_t)data_offset, NULL);
		zbx_free(data);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	async_check_items(evutil_socket_t fd, short events, void *arg)
{
	zbx_dc_item_t		*items = NULL;
	AGENT_RESULT		*results;
	int			*errcodes;
	zbx_timespec_t		timespec;
	int			i, num;
	zbx_poller_config_t	*poller_config = (zbx_poller_config_t *)arg;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	ZBX_UNUSED(fd);
	ZBX_UNUSED(events);

	num = zbx_dc_config_get_poller_items(poller_config->poller_type, poller_config->config_timeout,
			poller_config->processing, poller_config->config_max_concurrent_checks_per_poller, &items);

	if (0 == num)
		goto exit;

	results = zbx_malloc(NULL, (size_t)num * sizeof(AGENT_RESULT));
	errcodes = zbx_malloc(NULL, (size_t)num * sizeof(int));

	zbx_prepare_items(items, errcodes, num, results, MACRO_EXPAND_YES);

	for (i = 0; i < num; i++)
	{
		if (ITEM_TYPE_HTTPAGENT == items[i].type)
		{
#ifdef HAVE_LIBCURL
			errcodes[i] = zbx_async_check_httpagent(&items[i], &results[i],
					poller_config->config_source_ip, poller_config->curl_handle);
#else
			errcodes[i] = NOTSUPPORTED;
			SET_MSG_RESULT(&results[i], zbx_strdup(NULL,"Support for HTTP agent was not compiled in:"
					" missing cURL library"));
#endif
		}
		else
			errcodes[i] = zbx_async_check_agent(&items[i], &results[i], process_agent_result,
					poller_config, poller_config, poller_config->base,
					poller_config->config_timeout, poller_config->config_source_ip);

		if (SUCCEED == errcodes[i])
			poller_config->processing++;
	}

	zbx_timespec(&timespec);

	/* process item values */
	for (i = 0; i < num; i++)
	{
		if (NOTSUPPORTED == errcodes[i] || CONFIG_ERROR == errcodes[i])
		{
			if (ZBX_IS_RUNNING())
			{
				zbx_preprocess_item_value(items[i].itemid, items[i].host.hostid, items[i].value_type,
						items[i].flags, NULL, &timespec, ITEM_STATE_NOTSUPPORTED,
						results[i].msg);
			}

			zbx_vector_uint64_append(&poller_config->itemids, items[i].itemid);
			zbx_vector_int32_append(&poller_config->errcodes, errcodes[i]);
			zbx_vector_int32_append(&poller_config->lastclocks, timespec.sec);
		}
	}

	zbx_clean_items(items, num, results);
	zbx_dc_config_clean_items(items, NULL, (size_t)num);
	zbx_free(results);
	zbx_free(errcodes);
exit:
	zbx_free(items);
	if (ZBX_IS_RUNNING())
	{
		zbx_preprocessor_flush();
		poller_update_interfaces(poller_config);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __func__, num);

	poller_config->queued += num;
}

static void	poller_requeue_items(zbx_poller_config_t *poller_config)
{
	int	nextcheck;

	if (0 == poller_config->itemids.values_num)
		return;

	zbx_dc_poller_requeue_items(poller_config->itemids.values, poller_config->lastclocks.values,
			poller_config->errcodes.values, (size_t)poller_config->itemids.values_num,
			poller_config->poller_type, &nextcheck);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() requeued:%d", __func__, poller_config->itemids.values_num);

	zbx_vector_uint64_clear(&poller_config->itemids);
	zbx_vector_int32_clear(&poller_config->lastclocks);
	zbx_vector_int32_clear(&poller_config->errcodes);

	if (FAIL != nextcheck && nextcheck <= time(NULL))
		event_active(poller_config->async_check_items_timer, 0, 0);
}

static void	zbx_interface_status_clean(zbx_interface_status_t *interface_status)
{
	zbx_free(interface_status->key_orig);
	zbx_free(interface_status->error);
}

static void	async_poller_init(zbx_poller_config_t *poller_config, zbx_thread_poller_args *poller_args_in,
		event_callback_fn async_check_items_callback)
{
	struct timeval	tv = {1, 0};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_hashset_create_ext(&poller_config->interfaces, 100, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC, (zbx_clean_func_t)zbx_interface_status_clean,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);
	zbx_vector_uint64_create(&poller_config->itemids);
	zbx_vector_int32_create(&poller_config->lastclocks);
	zbx_vector_int32_create(&poller_config->errcodes);

	if (NULL == (poller_config->base = event_base_new()))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot initialize event base");
		exit(EXIT_FAILURE);
	}

	poller_config->config_source_ip = poller_args_in->config_comms->config_source_ip;
	poller_config->config_timeout = poller_args_in->config_comms->config_timeout;
	poller_config->poller_type = poller_args_in->poller_type;
	poller_config->config_unavailable_delay = poller_args_in->config_unavailable_delay;
	poller_config->config_unreachable_delay = poller_args_in->config_unreachable_delay;
	poller_config->config_unreachable_period = poller_args_in->config_unreachable_period;
	poller_config->config_max_concurrent_checks_per_poller = poller_args_in->config_max_concurrent_checks_per_poller;

	if (NULL == (poller_config->async_check_items_timer = event_new(poller_config->base, -1, EV_PERSIST,
		async_check_items_callback, poller_config)))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot create async items timer event");
		exit(EXIT_FAILURE);
	}

	evtimer_add(poller_config->async_check_items_timer, &tv);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	async_poller_stop(zbx_poller_config_t *poller_config)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	evtimer_del(poller_config->async_check_items_timer);
	event_base_dispatch(poller_config->base);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

static void	async_poller_destroy(zbx_poller_config_t *poller_config)
{
	event_base_free(poller_config->base);
	zbx_vector_uint64_clear(&poller_config->itemids);
	zbx_vector_int32_clear(&poller_config->lastclocks);
	zbx_vector_int32_clear(&poller_config->errcodes);
	zbx_vector_uint64_destroy(&poller_config->itemids);
	zbx_vector_int32_destroy(&poller_config->lastclocks);
	zbx_vector_int32_destroy(&poller_config->errcodes);
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

ZBX_THREAD_ENTRY(async_poller_thread, args)
{
	zbx_thread_poller_args	*poller_args_in = (zbx_thread_poller_args *)(((zbx_thread_args_t *)args)->args);

	time_t				last_stat_time;
	zbx_ipc_async_socket_t		rtc;
	const zbx_thread_info_t		*info = &((zbx_thread_args_t *)args)->info;
	int				server_num = ((zbx_thread_args_t *)args)->info.server_num;
	int				process_num = ((zbx_thread_args_t *)args)->info.process_num;
	unsigned char			process_type = ((zbx_thread_args_t *)args)->info.process_type;
	unsigned char			poller_type = poller_args_in->poller_type;
	zbx_poller_config_t		poller_config = {.queued = 0, .processed = 0};
	struct event			*rtc_event;
#ifdef HAVE_LIBCURL
	zbx_asynchttppoller_config	*asynchttppoller_config;
#endif

#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			server_num, get_process_type_string(process_type), process_num);

	zbx_setproctitle("%s #%d started", get_process_type_string(process_type), process_num);
	last_stat_time = time(NULL);

	zbx_rtc_subscribe(process_type, process_num, NULL, 0, poller_args_in->config_comms->config_timeout, &rtc);

	async_poller_init(&poller_config, poller_args_in, async_check_items);
	rtc_event = event_new(poller_config.base, zbx_ipc_client_get_fd(rtc.client), EV_READ | EV_PERSIST,
			socket_read_event_cb, NULL);
	event_add(rtc_event, NULL);

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);
	poller_config.state = ZBX_PROCESS_STATE_BUSY;
	poller_config.info = info;

	if (ZBX_POLLER_TYPE_HTTPAGENT == poller_type)
	{
#ifdef HAVE_LIBCURL
		asynchttppoller_config = zbx_async_httpagent_create(poller_config.base, process_httpagent_result,
				poller_update_selfmon_counter, &poller_config);
		poller_config.curl_handle = asynchttppoller_config->curl_handle;
#endif
	}
	else
	{
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		zbx_tls_init_child(poller_args_in->config_comms->config_tls, poller_args_in->zbx_get_program_type_cb_arg);
#endif
	}

	while (ZBX_IS_RUNNING())
	{
		zbx_uint32_t	rtc_cmd;
		unsigned char	*rtc_data;

		if (ZBX_PROCESS_STATE_BUSY == poller_config.state)
		{
			zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_IDLE);
			poller_config.state = ZBX_PROCESS_STATE_IDLE;
		}

		event_base_loop(poller_config.base, EVLOOP_ONCE);

		poller_requeue_items(&poller_config);

		if (STAT_INTERVAL <= time(NULL) - last_stat_time)
		{
			zbx_update_env(get_process_type_string(process_type), zbx_time());

			zbx_setproctitle("%s #%d [got %d values, queued %d in 5 sec]",
				get_process_type_string(process_type), process_num, poller_config.processed,
				poller_config.queued);

			poller_config.processed = 0;
			poller_config.queued = 0;
			last_stat_time = time(NULL);
		}

		if (SUCCEED == zbx_rtc_wait(&rtc, info, &rtc_cmd, &rtc_data, 0) && 0 != rtc_cmd)
		{
			if (ZBX_RTC_SHUTDOWN == rtc_cmd)
				break;
		}
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

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
#undef STAT_INTERVAL
}
