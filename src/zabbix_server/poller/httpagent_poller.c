#include "zbxcommon.h"
#include "zbxhttp.h"
#include "zbxcacheconfig.h"
#include <event.h>
#include <event2/thread.h>
#include "poller.h"
#include "zbxserver.h"
#include "zbx_item_constants.h"
#include "zbxpreproc.h"
#include "zbxself.h"
#include "zbxnix.h"
#include "zbx_rtc_constants.h"
#include "zbxrtc.h"

static int	get_context_http(zbx_dc_item_t *item, const char *config_source_ip, AGENT_RESULT *result,
		CURLM *curl_handle, zbx_poller_config_t	*poller_config)
{
	char			*error = NULL;
	int			ret = NOTSUPPORTED;
	zbx_http_context_t	*context = zbx_malloc(NULL, sizeof(zbx_http_context_t));

	zbx_http_context_create(context);

	context->item_context.itemid = item->itemid;
	context->item_context.hostid = item->host.hostid;
	context->item_context.value_type = item->value_type;
	context->item_context.flags = item->flags;
	context->item_context.state = item->state;
	context->posts = item->posts;
	context->poller_config = poller_config;
	item->posts = NULL;

	if (SUCCEED != (ret = zbx_http_request_prepare(context, item->request_method, item->url,
			item->query_fields, item->headers, context->posts, item->retrieve_mode, item->http_proxy,
			item->follow_redirects, item->timeout, 1, item->ssl_cert_file, item->ssl_key_file,
			item->ssl_key_password, item->verify_peer, item->verify_host, item->authtype, item->username,
			item->password, NULL, item->post_type, item->output_format, config_source_ip, &error)))
	{
		SET_MSG_RESULT(result, error);
		error = NULL;
		zbx_http_context_destory(context);
		zbx_free(context);

		return ret;
	}

	curl_easy_setopt(context->easyhandle, CURLOPT_PRIVATE, context);
	curl_multi_add_handle(curl_handle, context->easyhandle);

	return ret;
}

static void	add_items(evutil_socket_t fd, short events, void *arg)
{
	zbx_dc_item_t		item, *items;
	AGENT_RESULT		results[ZBX_MAX_POLLER_ITEMS];
	int			errcodes[ZBX_MAX_POLLER_ITEMS];
	zbx_timespec_t		timespec;
	int			i, num;
	zbx_poller_config_t	*poller_config = (zbx_poller_config_t *)arg;
	int			nextcheck;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	items = &item;
	num = zbx_dc_config_get_poller_items(poller_config->poller_type, poller_config->config_timeout, &items);

	if (0 == num)
	{
		nextcheck = zbx_dc_config_get_poller_nextcheck(poller_config->poller_type);
		goto exit;
	}

	zbx_prepare_items(items, errcodes, num, results, MACRO_EXPAND_YES);

	for (i = 0; i < num; i++)
	{
		if (SUCCEED != (errcodes[i] = get_context_http(&items[i],
				poller_config->config_source_ip, &results[i],
				poller_config->curl_handle, poller_config)))
		{
			continue;
		}
	}

	zbx_timespec(&timespec);

	/* process item values */
	for (i = 0; i < num; i++)
	{
		if (SUCCEED == errcodes[i])
		{
			continue;
		}
		else if (NOTSUPPORTED == errcodes[i] || AGENT_ERROR == errcodes[i] || CONFIG_ERROR == errcodes[i])
		{
			items[i].state = ITEM_STATE_NOTSUPPORTED;
			zbx_preprocess_item_value(items[i].itemid, items[i].host.hostid, items[i].value_type,
					items[i].flags, NULL, &timespec, items[i].state, results[i].msg);

			zbx_dc_poller_requeue_items(&items[i].itemid, &timespec.sec, &errcodes[i], 1,
					poller_config->poller_type, &nextcheck);
		}
	}

	zbx_preprocessor_flush();
	zbx_clean_items(items, num, results);
	zbx_dc_config_clean_items(items, NULL, num);

	if (items != &item)
		zbx_free(items);
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __func__, num);

	poller_config->num += num;
}

struct event_base	*base;
struct event		*curl_timeout;
CURLM			*curl_handle;

typedef struct
{
	struct event *event;
	curl_socket_t sockfd;
}
curl_context_t;

static void	check_multi_info(void)
{
	CURLMsg			*message;
	int			pending;
	CURL			*easy_handle;
	zbx_http_context_t	*context;
	zbx_timespec_t		timespec;
	long			response_code;
	int			ret;
	char			*error, *out = NULL;
	AGENT_RESULT		result;

	zbx_timespec(&timespec);

	while (NULL != (message = curl_multi_info_read(curl_handle, &pending)))
	{
		switch(message->msg)
		{
			case CURLMSG_DONE:
				easy_handle = message->easy_handle;

				curl_easy_getinfo(easy_handle, CURLINFO_PRIVATE, &context);
				printf("DONE\n");

				zbx_init_agent_result(&result);
				if (SUCCEED == (ret = zbx_http_handle_response(context->easyhandle, context, message->data.result, &response_code, &out, &error)))
				{
					/*if ('\0' != *item->status_codes && FAIL == zbx_int_in_list(context->status_codes, (int)response_code))
					{
						if (NULL != out)
						{
							SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Response code \"%ld\" did not match any of the"
									" required status codes \"%s\"\n%s", response_code, item->status_codes, out));
						}
						else
						{
							SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Response code \"%ld\" did not match any of the"
									" required status codes \"%s\"", response_code, item->status_codes));
						}
					}
					else*/
					{
						SET_TEXT_RESULT(&result, out);
						out = NULL;
					}
				}
				else
				{
					SET_MSG_RESULT(&result, error);
					error = NULL;
					ret = NOTSUPPORTED;
				}

				if (SUCCEED == ret)
				{
					zbx_preprocess_item_value(context->item_context.itemid, context->item_context.hostid,context->item_context.value_type,
							context->item_context.flags, &result, &timespec, ITEM_STATE_NORMAL, NULL);
				}
				else
				{
					zbx_preprocess_item_value(context->item_context.itemid, context->item_context.hostid,context->item_context.value_type,
							context->item_context.flags, NULL, &timespec, ITEM_STATE_NOTSUPPORTED, result.msg);
				}

				int	errcode = SUCCEED;
				int	nextcheck;
				zbx_dc_poller_requeue_items(&context->item_context.itemid, &timespec.sec, &errcode, 1,
						ZBX_POLLER_TYPE_HTTPAGENT, &nextcheck);
				zbx_free_agent_result(&result);

				if (FAIL != nextcheck && nextcheck <= time(NULL))
					event_active(context->poller_config->add_items_timer, 0, 0);

				curl_multi_remove_handle(curl_handle, easy_handle);
				zbx_http_context_destory(context);
				zbx_free(context);
				break;
			default:
				fprintf(stderr, "CURLMSG default\n");
				break;
		}
	}
}

static void on_timeout(evutil_socket_t fd, short events, void *arg)
{
	int running_handles;

	printf("on_timeout\n");
	curl_multi_socket_action(curl_handle, CURL_SOCKET_TIMEOUT, 0, &running_handles);
	check_multi_info();
}

static int	start_timeout(CURLM *multi, long timeout_ms, void *userp)
{
	if(timeout_ms < 0)
	{
		evtimer_del(curl_timeout);
	}
	else
	{
		struct timeval tv;

		if(timeout_ms == 0)
			timeout_ms = 1;	/* 0 means directly call socket_action, but we will do it in a bit */

		tv.tv_sec = timeout_ms / 1000;
		tv.tv_usec = (timeout_ms % 1000) * 1000;
		evtimer_del(curl_timeout);
		evtimer_add(curl_timeout, &tv);
	}

	return 0;
}

static void	curl_perform(int fd, short event, void *arg);

static curl_context_t	*create_curl_context(curl_socket_t sockfd)
{
	curl_context_t *context;

	context = (curl_context_t *) malloc(sizeof(*context));

	context->sockfd = sockfd;

	context->event = event_new(base, sockfd, 0, curl_perform, context);

	return context;
}

static void	destroy_curl_context(curl_context_t *context)
{
	event_del(context->event);
	event_free(context->event);
	free(context);
}

static void	curl_perform(int fd, short event, void *arg)
{
	int		running_handles;
	int		flags = 0;
	curl_context_t	*context;

	if(event & EV_READ)
		flags |= CURL_CSELECT_IN;
	if(event & EV_WRITE)
		flags |= CURL_CSELECT_OUT;

	context = (curl_context_t *) arg;
	curl_multi_socket_action(curl_handle, context->sockfd, flags, &running_handles);

	check_multi_info();
}


static int handle_socket(CURL *easy, curl_socket_t s, int action, void *userp, void *socketp)
{
	curl_context_t *curl_context;
	int		events = 0;
	zabbix_log(LOG_LEVEL_TRACE, "action:%d", action);
	switch(action)
	{
		case CURL_POLL_IN:
		case CURL_POLL_OUT:
		case CURL_POLL_INOUT:
			curl_context = socketp ? (curl_context_t *) socketp : create_curl_context(s);

			curl_multi_assign(curl_handle, s, (void *) curl_context);

			if(action != CURL_POLL_IN)
				events |= EV_WRITE;
			if(action != CURL_POLL_OUT)
				events |= EV_READ;

			events |= EV_PERSIST;

			event_del(curl_context->event);
			event_assign(curl_context->event, base, curl_context->sockfd, events, curl_perform,
					curl_context);
			event_add(curl_context->event, NULL);

		break;
	case CURL_POLL_REMOVE:
		if(socketp)
		{
			event_del(((curl_context_t*) socketp)->event);
			destroy_curl_context((curl_context_t*) socketp);
			curl_multi_assign(curl_handle, s, NULL);
		}
		break;
	default:
		break;

	}

	return 0;
}

ZBX_THREAD_ENTRY(httpagent_poller_thread, args)
{
	zbx_thread_poller_args	*poller_args_in = (zbx_thread_poller_args *)(((zbx_thread_args_t *)args)->args);

	int			nextcheck = 0, sleeptime = -1, processed = 0, old_processed = 0;
	double			sec, total_sec = 0.0, old_total_sec = 0.0;
	time_t			last_stat_time;
	unsigned char		poller_type;
	zbx_ipc_async_socket_t	rtc;
	const zbx_thread_info_t	*info = &((zbx_thread_args_t *)args)->info;
	int			server_num = ((zbx_thread_args_t *)args)->info.server_num;
	int			process_num = ((zbx_thread_args_t *)args)->info.process_num;
	unsigned char		process_type = ((zbx_thread_args_t *)args)->info.process_type;
	struct event		*add_items_timer;
	struct timeval		tv = {1, 0};

#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	poller_type = (poller_args_in->poller_type);

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			server_num, get_process_type_string(process_type), process_num);

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

	zbx_setproctitle("%s #%d started", get_process_type_string(process_type), process_num);
	last_stat_time = time(NULL);

	if (0 == curl_global_init(CURL_GLOBAL_ALL))
		zabbix_log(LOG_LEVEL_ERR, "cannot initialize cURL");

	if (NULL == (curl_handle = curl_multi_init()))
		zabbix_log(LOG_LEVEL_ERR, "cannot initialize cURL multi session");

	if (NULL == (base = event_base_new()))
		zabbix_log(LOG_LEVEL_ERR, "cannot initialize event base");

	curl_timeout = evtimer_new(base, on_timeout, NULL);

	curl_multi_setopt(curl_handle, CURLMOPT_SOCKETFUNCTION, handle_socket);
	curl_multi_setopt(curl_handle, CURLMOPT_TIMERFUNCTION, start_timeout);

	zbx_poller_config_t	poller_config;

	poller_config.config_source_ip = poller_args_in->config_comms->config_source_ip;
	poller_config.config_timeout = poller_args_in->config_comms->config_timeout;
	poller_config.poller_type = poller_type;
	poller_config.curl_handle = curl_handle;
	poller_config.base = base;
	add_items_timer = evtimer_new(base, add_items, &poller_config);
	poller_config.add_items_timer = add_items_timer;

	while (ZBX_IS_RUNNING())
	{
		zbx_uint32_t	rtc_cmd;
		unsigned char	*rtc_data;

		sec = zbx_time();
		zbx_update_env(get_process_type_string(process_type), sec);

		if (0 != sleeptime)
		{
			zbx_setproctitle("%s #%d [got %d values in " ZBX_FS_DBL " sec, getting values]",
					get_process_type_string(process_type), process_num, old_processed,
					old_total_sec);
		}

		if (0 == evtimer_pending(add_items_timer, NULL))
			evtimer_add(add_items_timer, &tv);

		event_base_loop(base, EVLOOP_ONCE);

		sleeptime = 0;
		

		total_sec += zbx_time() - sec;

		if (0 != sleeptime || STAT_INTERVAL <= time(NULL) - last_stat_time)
		{
			if (0 == sleeptime)
			{
				zbx_setproctitle("%s #%d [got %d values in " ZBX_FS_DBL " sec, getting values]",
					get_process_type_string(process_type), process_num, processed, total_sec);
			}
			else
			{
				zbx_setproctitle("%s #%d [got %d values in " ZBX_FS_DBL " sec, idle %d sec]",
					get_process_type_string(process_type), process_num, processed, total_sec,
					sleeptime);
				old_processed = processed;
				old_total_sec = total_sec;
			}
			processed = 0;
			total_sec = 0.0;
			last_stat_time = time(NULL);
		}

		if (SUCCEED == zbx_rtc_wait(&rtc, info, &rtc_cmd, &rtc_data, sleeptime) && 0 != rtc_cmd)
		{
			if (ZBX_RTC_SHUTDOWN == rtc_cmd)
				break;
		}
	}


	curl_multi_cleanup(curl_handle);
	event_free(curl_timeout);
	event_base_free(base);

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
#undef STAT_INTERVAL
}