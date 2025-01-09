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

#include "zbxcommon.h"
#include "zbxrtc.h"
#include "zbx_rtc_constants.h"

#include "zbxserialize.h"
#include "zbxjson.h"
#include "zbxnix.h"
#include "zbxdiag.h"
#include "zbxstr.h"
#include "zbxnum.h"
#include "zbxalgo.h"
#include "zbxipcservice.h"
#include "zbxprof.h"
#include "zbxtime.h"

ZBX_PTR_VECTOR_IMPL(rtc_sub, zbx_rtc_sub_t *)
ZBX_PTR_VECTOR_IMPL(rtc_hook, zbx_rtc_hook_t *)

/******************************************************************************
 *                                                                            *
 * Purpose: get runtime control option targets                                *
 *                                                                            *
 ******************************************************************************/
int	zbx_rtc_get_command_target(const char *data, pid_t *pid, int *proc_type, int *proc_num, int *scope,
		char **result)
{
	struct zbx_json_parse	jp;
	char			buf[MAX_STRING_LEN];

	if (FAIL == zbx_json_open(data, &jp))
	{
		*result = zbx_dsprintf(NULL, "Invalid parameters \"%s\"\n", data);
		return FAIL;
	}

	if (NULL != scope)
	{
		if (SUCCEED == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_SCOPE, buf, sizeof(buf), NULL))
			*scope = atoi(buf);
		else
			*scope = ZBX_PROF_UNKNOWN;
	}

	if (SUCCEED == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_PID, buf, sizeof(buf), NULL))
	{
		zbx_uint64_t	pid_ui64;

		if (SUCCEED != zbx_is_uint64(buf, &pid_ui64) || 0 == pid_ui64)
		{
			*result = zbx_dsprintf(NULL, "Invalid pid value \"%s\"\n", buf);
			return FAIL;
		}

		*pid = (pid_t)pid_ui64;
	}
	else
		*pid = 0;

	if (SUCCEED == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_PROCESS_NUM, buf, sizeof(buf), NULL))
		*proc_num = atoi(buf);
	else
		*proc_num = 0;

	if (SUCCEED == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_PROCESS_NAME, buf, sizeof(buf), NULL))
	{
		if (ZBX_PROCESS_TYPE_UNKNOWN == (*proc_type = get_process_type_by_name(buf)))
		{
			*result = zbx_dsprintf(NULL, "Invalid parameters \"%s\"\n", data);
			return FAIL;
		}
	}
	else
		*proc_type = ZBX_PROCESS_TYPE_UNKNOWN;

	return SUCCEED;
}

#if defined(HAVE_SIGQUEUE)

/******************************************************************************
 *                                                                            *
 * Purpose: change log level of service process                               *
 *                                                                            *
 ******************************************************************************/
static void	rtc_change_service_loglevel(zbx_uint32_t code)
{
	if (ZBX_RTC_LOG_LEVEL_INCREASE == code)
		zabbix_increase_log_level();
	else if (ZBX_RTC_LOG_LEVEL_DECREASE == code)
		zabbix_decrease_log_level();

	zabbix_report_log_level_change();
}


/******************************************************************************
 *                                                                            *
 * Purpose: dispatch log level change runtime control option                  *
 *                                                                            *
 ******************************************************************************/
static void	rtc_process_loglevel_option(zbx_rtc_t *rtc, zbx_uint32_t code, const char *data, char **result)
{
	pid_t	pid;
	int	proc_type, proc_num;

	if (SUCCEED != zbx_rtc_get_command_target(data, &pid, &proc_type, &proc_num, NULL, result))
		return;

	/* all processes */
	if (0 == pid && ZBX_PROCESS_TYPE_UNKNOWN == proc_type)
	{
		rtc_change_service_loglevel(code);
		zbx_rtc_notify(rtc, (unsigned char)proc_type, proc_num, code, data, (zbx_uint32_t)strlen(data) + 1);
		zbx_signal_process_by_pid(0, (int)ZBX_RTC_MAKE_MESSAGE(code, 0, 0), result);
		return;
	}

	if (0 != pid)
	{
		if (pid == getpid())
		{
			rtc_change_service_loglevel(code);
			/* temporary message, the signal forwarding command output will be changed later */
			*result = zbx_strdup(NULL, "Changed log level for the main process\n");
		}
		else
			zbx_signal_process_by_pid((int)pid, (int)ZBX_RTC_MAKE_MESSAGE(code, 0, 0), result);

		return;
	}

	if (0 == zbx_rtc_notify(rtc, (unsigned char)proc_type, proc_num, code, data, (zbx_uint32_t)strlen(data) + 1))
		zbx_signal_process_by_type(proc_type, proc_num, (int)ZBX_RTC_MAKE_MESSAGE(code, 0, 0), result);
}

/******************************************************************************
 *                                                                            *
 * Purpose: dispatch profiler runtime control option                          *
 *                                                                            *
 ******************************************************************************/
static void	rtc_process_profiler_option(zbx_uint32_t code, const char *data, char **result)
{
	pid_t	pid;
	int	proc_type, proc_num, scope;

	if (SUCCEED != zbx_rtc_get_command_target(data, &pid, &proc_type, &proc_num, &scope, result))
		return;

	/* all processes */
	if (0 == pid && ZBX_PROCESS_TYPE_UNKNOWN == proc_type)
	{
		zbx_signal_process_by_pid(0, (int)ZBX_RTC_MAKE_MESSAGE(code, 0, 0), result);
		return;
	}

	if (0 != pid)
	{
		if (pid == getpid())
			*result = zbx_strdup(NULL, "Cannot use profiler with main process\n");
		else
			zbx_signal_process_by_pid((int)pid, (int)ZBX_RTC_MAKE_MESSAGE(code, scope, 0), result);

		return;
	}

	zbx_signal_process_by_type(proc_type, proc_num, (int)ZBX_RTC_MAKE_MESSAGE(code, scope, 0), result);
}
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: process diaginfo runtime control option                           *
 *                                                                            *
 * Parameters: data   - [IN] the runtime control parameter (optional)         *
 *             result - [OUT] the runtime control result                      *
 *                                                                            *
 ******************************************************************************/
static void	rtc_process_diaginfo(const char *data, char **result)
{
	struct zbx_json_parse	jp;
	char			buf[MAX_STRING_LEN];
	unsigned int		scope;

	if (FAIL == zbx_json_open(data, &jp) ||
			SUCCEED != zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_SECTION, buf, sizeof(buf), NULL))
	{
		*result = zbx_dsprintf(NULL, "Invalid parameters \"%s\"\n", data);
		return;
	}

	if (0 == strcmp(buf, "all"))
	{
		scope = (1 << ZBX_DIAGINFO_HISTORYCACHE) | (1 << ZBX_DIAGINFO_PREPROCESSING) |
				(1 << ZBX_DIAGINFO_LOCKS);
	}
	else if (0 == strcmp(buf, ZBX_DIAG_HISTORYCACHE))
	{
		scope = 1 << ZBX_DIAGINFO_HISTORYCACHE;
	}
	else if (0 == strcmp(buf, ZBX_DIAG_PREPROCESSING))
	{
		scope = 1 << ZBX_DIAGINFO_PREPROCESSING;
	}
	else if (0 == strcmp(buf, ZBX_DIAG_LOCKS))
	{
		scope = 1 << ZBX_DIAGINFO_LOCKS;
	}
	else
	{
		if (NULL == *result)
			*result = zbx_dsprintf(NULL, "Unknown diaginfo section \"%s\"\n", buf);
		return;
	}

	zbx_diag_log_info(scope, result);
}

/******************************************************************************
 *                                                                            *
 * Purpose: notify client based subscribers                                   *
 *                                                                            *
 ******************************************************************************/
static void	rtc_notify_client(zbx_rtc_sub_t *sub, zbx_uint32_t code, const unsigned char *data, zbx_uint32_t size)
{
	if (FAIL == zbx_ipc_client_send(sub->source.client, code, data, size))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot send RTC notification to client \"%s\" #%d",
				get_process_type_string(sub->process_type), sub->process_num);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: notify service based subscribers                                  *
 *                                                                            *
 ******************************************************************************/
static void	rtc_notify_service(zbx_rtc_sub_t *sub, zbx_uint32_t code, const unsigned char *data, zbx_uint32_t size)
{
	zbx_ipc_socket_t	sock;
	char			*error = NULL;

	if (FAIL == zbx_ipc_socket_open(&sock, sub->source.service, SEC_PER_MIN, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot send RTC notification to service \"%s\" #%d: %s",
				get_process_type_string(sub->process_type), sub->process_num, error);
		zbx_free(error);
		return;
	}

	if (FAIL == zbx_ipc_socket_write(&sock, code, data, size))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot send RTC notification to service \"%s\" #%d",
				get_process_type_string(sub->process_type), sub->process_num);
	}

	zbx_ipc_socket_close(&sock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: match RTC message                                                 *
 *                                                                            *
 ******************************************************************************/
static int	rtc_match_message(const zbx_vector_uint32_t *msgs, zbx_uint32_t code)
{
	for (int i = 0; i < msgs->values_num; i++)
	{
		if (msgs->values[i] == code)
			return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: notify subscribers                                                *
 *                                                                            *
 ******************************************************************************/
int	zbx_rtc_notify(zbx_rtc_t *rtc, unsigned char process_type, int process_num, zbx_uint32_t code,
		const char *data, zbx_uint32_t size)
{
	int	i, notified_num = 0;

	for (i = 0; i < rtc->subs.values_num; i++)
	{
		if (ZBX_PROCESS_TYPE_UNKNOWN != process_type && process_type != rtc->subs.values[i]->process_type)
			continue;

		if (0 != process_num && 0 != rtc->subs.values[i]->process_num &&
				process_num != rtc->subs.values[i]->process_num)
		{
			continue;
		}

		if (SUCCEED != rtc_match_message(&rtc->subs.values[i]->msgs, code))
			continue;

		switch (rtc->subs.values[i]->type)
		{
			case ZBX_RTC_SUB_CLIENT:
				rtc_notify_client(rtc->subs.values[i], code, (const unsigned char *)data, size);
				break;
			case ZBX_RTC_SUB_SERVICE:
				rtc_notify_service(rtc->subs.values[i], code, (const unsigned char *)data, size);
		}

		notified_num++;
	}

	return notified_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: notifies remote subscribers                                       *
 *                                                                            *
 ******************************************************************************/
int	zbx_rtc_notify_generic(zbx_ipc_async_socket_t *rtc, unsigned char process_type, int process_num,
		zbx_uint32_t code, const char *data, zbx_uint32_t size)
{
	unsigned char	*notify_data, *ptr;
	zbx_uint32_t	notify_data_size;
	int		ret = FAIL;

	/* <process type:uchar><process num:int><code:uint32><data size:uint32><data> */
	notify_data_size = (zbx_uint32_t)(sizeof(process_type) + sizeof(process_num) +  sizeof(code) + 2 * sizeof(size))
			+ size;
	notify_data = (unsigned char *)zbx_malloc(NULL, notify_data_size);

	ptr = notify_data;
	ptr += zbx_serialize_value(ptr, process_type);
	ptr += zbx_serialize_int(ptr, process_num);
	ptr += zbx_serialize_value(ptr, code);
	ptr += zbx_serialize_value(ptr, size);
	(void)zbx_serialize_str(ptr, data, size);

	if (FAIL == zbx_ipc_async_socket_send(rtc, ZBX_RTC_NOTIFY, notify_data, notify_data_size))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send %s notification", get_process_type_string(process_type));
		goto out;
	}

	ret = (int)notify_data_size;
out:
	zbx_free(notify_data);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: deserialize subscription target notification codes                *
 *                                                                            *
 ******************************************************************************/
static size_t	rtc_deserialize_msgs(const unsigned char *data, zbx_vector_uint32_t *msgs)
{
	int			msgs_num;
	const unsigned char	*ptr = data;
	zbx_uint32_t		msg;

	ptr += zbx_deserialize_value(ptr, &msgs_num);

	/* reserve additional slot for shutdown notification */
	zbx_vector_uint32_reserve(msgs, (size_t)msgs_num + 1);

	for (int i = 0; i < msgs_num; i++)
	{
		ptr += zbx_deserialize_value(ptr, &msg);
		zbx_vector_uint32_append(msgs, msg);
	}

	/* automatically subscribe also for shutdown notifications */
	zbx_vector_uint32_append(msgs, ZBX_RTC_SHUTDOWN);

	return (size_t)(ptr - data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: subscribe for client based RTC notifications                      *
 *                                                                            *
 ******************************************************************************/
static void	rtc_subscribe(zbx_rtc_t *rtc, zbx_ipc_client_t *client, const unsigned char *data)
{
	zbx_rtc_sub_t	*sub;

	sub = (zbx_rtc_sub_t *)zbx_malloc(NULL, sizeof(zbx_rtc_sub_t));
	zbx_vector_uint32_create(&sub->msgs);

	sub->type = ZBX_RTC_SUB_CLIENT;
	sub->source.client = client;
	data += zbx_deserialize_value(data, &sub->process_type);
	data += zbx_deserialize_value(data, &sub->process_num);
	(void)rtc_deserialize_msgs(data, &sub->msgs);

	zbx_vector_rtc_sub_append(&rtc->subs, sub);
}

/******************************************************************************
 *                                                                            *
 * Purpose: subscribe for service based RTC notifications                     *
 *                                                                            *
 ******************************************************************************/
static void	rtc_subscribe_service(zbx_rtc_t *rtc, const unsigned char *data)
{
	zbx_rtc_sub_t	*sub;
	zbx_uint32_t	service_len;

	sub = (zbx_rtc_sub_t *)zbx_malloc(NULL, sizeof(zbx_rtc_sub_t));
	zbx_vector_uint32_create(&sub->msgs);

	sub->type = ZBX_RTC_SUB_SERVICE;
	data += zbx_deserialize_value(data, &sub->process_type);
	data += zbx_deserialize_value(data, &sub->process_num);
	data += rtc_deserialize_msgs(data, &sub->msgs);
	(void)zbx_deserialize_str(data, &sub->source.service, service_len);

	zbx_vector_rtc_sub_append(&rtc->subs, sub);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process runtime control option                                    *
 *                                                                            *
 * Parameters: rtc    - [IN] the RTC service                                  *
 *             code   - [IN] the request code                                 *
 *             data   - [IN] the runtime control parameter (optional)         *
 *             result - [OUT] the runtime control result                      *
 *                                                                            *
 ******************************************************************************/
static void	rtc_process_request(zbx_rtc_t *rtc, zbx_uint32_t code, const unsigned char *data,
		char **result)
{
	zbx_uint32_t	notify_code, notify_size;
	int		process_num;
	char		*notify_data = NULL;
	unsigned char	process_type;

	switch (code)
	{
#if defined(HAVE_SIGQUEUE)
		case ZBX_RTC_LOG_LEVEL_INCREASE:
		case ZBX_RTC_LOG_LEVEL_DECREASE:
			rtc_process_loglevel_option(rtc, code, (const char *)data, result);
			return;
		case ZBX_RTC_PROF_ENABLE:
		case ZBX_RTC_PROF_DISABLE:
			rtc_process_profiler_option(code, (const char *)data, result);
			return;
#endif
		case ZBX_RTC_HOUSEKEEPER_EXECUTE:
			zbx_rtc_notify(rtc, ZBX_PROCESS_TYPE_HOUSEKEEPER, 0, ZBX_RTC_HOUSEKEEPER_EXECUTE, NULL, 0);
			return;
		case ZBX_RTC_CONFIG_CACHE_RELOAD:
			zbx_rtc_notify(rtc, ZBX_PROCESS_TYPE_CONFSYNCER, 0, ZBX_RTC_CONFIG_CACHE_RELOAD, NULL, 0);
			return;
		case ZBX_RTC_SNMP_CACHE_RELOAD:
#ifdef HAVE_NETSNMP
			zbx_rtc_notify(rtc, ZBX_PROCESS_TYPE_SNMP_POLLER, 0, ZBX_RTC_SNMP_CACHE_RELOAD, NULL, 0);
			zbx_rtc_notify(rtc, ZBX_PROCESS_TYPE_POLLER, 0, ZBX_RTC_SNMP_CACHE_RELOAD, NULL, 0);
			zbx_rtc_notify(rtc, ZBX_PROCESS_TYPE_UNREACHABLE, 0, ZBX_RTC_SNMP_CACHE_RELOAD, NULL, 0);
			zbx_rtc_notify(rtc, ZBX_PROCESS_TYPE_TRAPPER, 0, ZBX_RTC_SNMP_CACHE_RELOAD, NULL, 0);
			zbx_rtc_notify(rtc, ZBX_PROCESS_TYPE_DISCOVERYMANAGER, 0, ZBX_RTC_SNMP_CACHE_RELOAD, NULL, 0);
			zbx_rtc_notify(rtc, ZBX_PROCESS_TYPE_TASKMANAGER, 0, ZBX_RTC_SNMP_CACHE_RELOAD, NULL, 0);
#else
			*result = zbx_strdup(NULL, "Invalid runtime control option: no SNMP support enabled\n");
#endif
			return;
		case ZBX_RTC_DIAGINFO:
			rtc_process_diaginfo((const char *)data, result);
			return;
		case ZBX_RTC_NOTIFY:
			data += zbx_deserialize_value(data, &process_type);
			data += zbx_deserialize_int(data, &process_num);
			data += zbx_deserialize_value(data, &notify_code);
			data += zbx_deserialize_value(data, &notify_size);
			(void)zbx_deserialize_str(data, &notify_data, notify_size);
			zbx_rtc_notify(rtc, process_type, process_num, notify_code, notify_data, notify_size);
			zbx_free(notify_data);
			return;
		default:
			*result = zbx_strdup(*result, "Unknown runtime control option\n");
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: add hook for the specified control code                           *
 *                                                                            *
 ******************************************************************************/
static void	rtc_add_control_hook(zbx_rtc_t *rtc, zbx_ipc_client_t *client, zbx_uint32_t code)
{
	zbx_rtc_hook_t	*hook;

	hook = (zbx_rtc_hook_t *)zbx_malloc(NULL, sizeof(zbx_rtc_hook_t));
	hook->client = client;
	hook->code = code;
	zbx_vector_rtc_hook_append(&rtc->hooks, hook);
}

/******************************************************************************
 *                                                                            *
 * Purpose: notify matching hooks and remove them                             *
 *                                                                            *
 ******************************************************************************/
static void	rtc_notify_hooks(zbx_rtc_t *rtc, zbx_uint32_t code, unsigned char *data, zbx_uint32_t size)
{
	int	i;

	for (i = 0; i < rtc->hooks.values_num;)
	{
		if (rtc->hooks.values[i]->code == code)
		{
			(void)zbx_ipc_client_send(rtc->hooks.values[i]->client, code, data, size);
			zbx_free(rtc->hooks.values[i]);
			zbx_vector_rtc_hook_remove_noorder(&rtc->hooks, i);
			continue;
		}
		i++;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: process runtime control option                                    *
 *                                                                            *
 ******************************************************************************/
static void	rtc_process(zbx_rtc_t *rtc, zbx_ipc_client_t *client, zbx_uint32_t code, const unsigned char *data,
		zbx_rtc_process_request_ex_func_t cb_proc_req)
{
	char		*result = NULL, *result_ex = NULL;
	zbx_uint32_t	size = 0;

	if (NULL == cb_proc_req || FAIL == cb_proc_req(rtc, code, data, &result_ex))
		rtc_process_request(rtc, code, data, &result);

	if (ZBX_RTC_NOTIFY != code)
	{
		if (NULL != result_ex)
			result = zbx_strdcat(result, result_ex);

		if (NULL == result)
		{
			/* generate default success message if no specific success or error messages were returned */
			result = zbx_strdup(NULL, "Runtime control command was forwarded successfully\n");
		}

		size = (zbx_uint32_t)strlen(result) + 1;
		zbx_ipc_client_send(client, code, (unsigned char *)result, size);
	}

	zbx_free(result_ex);
	zbx_free(result);
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize runtime control service                                *
 *                                                                            *
 ******************************************************************************/
int	zbx_rtc_init(zbx_rtc_t *rtc ,char **error)
{
	zbx_vector_rtc_sub_create(&rtc->subs);
	zbx_vector_rtc_hook_create(&rtc->hooks);
	return zbx_ipc_service_start(&rtc->service, ZBX_IPC_SERVICE_RTC, error);
}

/******************************************************************************
 *                                                                            *
 * Purpose: accept and process runtime control request                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_rtc_dispatch(zbx_rtc_t *rtc, zbx_ipc_client_t *client, zbx_ipc_message_t *message,
		zbx_rtc_process_request_ex_func_t cb_proc_req)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() code:%u size:%u", __func__, message->code, message->size);

	switch (message->code)
	{
		case ZBX_RTC_SUBSCRIBE:
			rtc_subscribe(rtc, client, message->data);
			break;
		case ZBX_RTC_SUBSCRIBE_SERVICE:
			rtc_subscribe_service(rtc, message->data);
			break;
		case ZBX_RTC_CONFIG_CACHE_RELOAD_WAIT:
			rtc_add_control_hook(rtc, client, ZBX_RTC_CONFIG_SYNC_NOTIFY);
			zbx_rtc_notify(rtc, ZBX_PROCESS_TYPE_CONFSYNCER, 0, ZBX_RTC_CONFIG_CACHE_RELOAD, NULL, 0);
			break;
		case ZBX_RTC_CONFIG_SYNC_NOTIFY:
		case ZBX_RTC_SERVICE_SYNC_NOTIFY:
			rtc_notify_hooks(rtc, message->code, message->data, message->size);
			break;
		default:
			rtc_process(rtc, client, message->code, message->data, cb_proc_req);
			break;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: wait for sync notification while optionally dispatching runtime   *
 * control commands                                                           *
 *                                                                            *
 ******************************************************************************/
int	zbx_rtc_wait_for_sync_finish(zbx_rtc_t *rtc, zbx_rtc_process_request_ex_func_t cb_proc_req)
{
	zbx_timespec_t	rtc_timeout = {1, 0};
	int		sync = 0;

	while (ZBX_IS_RUNNING() && 0 == sync)
	{
		zbx_ipc_client_t	*client;
		zbx_ipc_message_t	*message;

		(void)zbx_ipc_service_recv(&rtc->service, &rtc_timeout, &client, &message);

		if (NULL != message)
		{
			switch (message->code)
			{
				case ZBX_RTC_CONFIG_SYNC_NOTIFY:
				case ZBX_RTC_SERVICE_SYNC_NOTIFY:
					sync = 1;
					break;
				case ZBX_RTC_LOG_LEVEL_DECREASE:
				case ZBX_RTC_LOG_LEVEL_INCREASE:
				case ZBX_RTC_SUBSCRIBE:
					zbx_rtc_dispatch(rtc, client, message, cb_proc_req);
					break;
				default:
					if (ZBX_IPC_RTC_MAX >= message->code)
					{
						const char *rtc_error = "Cannot perform specified runtime control"
								" command during startup\n";
						zbx_ipc_client_send(client, message->code,
								(const unsigned char *)rtc_error,
								(zbx_uint32_t)strlen(rtc_error) + 1);
					}
			}
			zbx_ipc_message_free(message);
		}

		if (NULL != client)
			zbx_ipc_client_release(client);
	}

	return !ZBX_IS_RUNNING() && FAIL == ZBX_EXIT_STATUS() ? FAIL : SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: send shutdown signal to all subscribers                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_rtc_shutdown_subs(zbx_rtc_t *rtc)
{
	zbx_rtc_notify(rtc, ZBX_PROCESS_TYPE_UNKNOWN, 0, ZBX_RTC_SHUTDOWN, NULL, 0);
}

/******************************************************************************
 *                                                                            *
 * Purpose: free rtc subscription                                             *
 *                                                                            *
 ******************************************************************************/
void	zbx_rtc_sub_free(zbx_rtc_sub_t *sub)
{
	switch (sub->type)
	{
		case ZBX_RTC_SUB_CLIENT:
			zbx_ipc_client_close(sub->source.client);
			break;
		case ZBX_RTC_SUB_SERVICE:
			zbx_free(sub->source.service);
			break;
	}

	zbx_vector_uint32_destroy(&sub->msgs);
	zbx_free(sub);
}
