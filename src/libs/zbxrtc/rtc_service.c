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

#include "zbxrtc.h"
#include "rtc.h"

#include "zbxserialize.h"
#include "zbxjson.h"
#include "zbxnix.h"
#include "log.h"
#include "zbxdiag.h"

ZBX_PTR_VECTOR_IMPL(rtc_sub, zbx_rtc_sub_t *)
ZBX_PTR_VECTOR_IMPL(rtc_hook, zbx_rtc_hook_t *)

#if defined(HAVE_SIGQUEUE)

/******************************************************************************
 *                                                                            *
 * Purpose: change log level of service process                               *
 *                                                                            *
 ******************************************************************************/
static void	rtc_change_service_loglevel(int code)
{
	if (ZBX_RTC_LOG_LEVEL_INCREASE == code)
	{
		if (SUCCEED != zabbix_increase_log_level())
		{
			zabbix_log(LOG_LEVEL_INFORMATION, "cannot increase log level:"
					" maximum level has been already set");
		}
		else
		{
			zabbix_log(LOG_LEVEL_INFORMATION, "log level has been increased to %s",
					zabbix_get_log_level_string());
		}
	}
	else
	{
		if (SUCCEED != zabbix_decrease_log_level())
		{
			zabbix_log(LOG_LEVEL_INFORMATION, "cannot decrease log level:"
					" minimum level has been already set");
		}
		else
		{
			zabbix_log(LOG_LEVEL_INFORMATION, "log level has been decreased to %s",
					zabbix_get_log_level_string());
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: process loglevel runtime control option                           *
 *                                                                            *
 * Parameters: code   - [IN] the runtime control request code                 *
 *             data   - [IN] the runtime control parameter (optional)         *
 *             result - [OUT] the runtime control result                      *
 *                                                                            *
 ******************************************************************************/
static void	rtc_process_loglevel(int code, const char *data, char **result)
{
	struct zbx_json_parse	jp;
	char			buf[MAX_STRING_LEN];
	int			process_num = 0, process_type;

	if (NULL == data)
	{
		rtc_change_service_loglevel(code);
		zbx_signal_process_by_pid(0, ZBX_RTC_MAKE_MESSAGE(code, 0, 0), result);
		return;
	}

	if (FAIL == zbx_json_open(data, &jp))
	{
		*result = zbx_dsprintf(NULL, "Invalid parameters \"%s\"\n", data);
		return;
	}

	if (SUCCEED == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_PID, buf, sizeof(buf), NULL))
	{
		zbx_uint64_t	pid;

		if (SUCCEED != is_uint64(buf, &pid) || 0 == pid)
		{
			*result = zbx_dsprintf(NULL, "Invalid pid value \"%s\"\n", buf);
			return;
		}

		if ((pid_t)pid == getpid())
		{
			rtc_change_service_loglevel(code);
			/* temporary message, the signal forwarding command output will be changed later */
			*result = zbx_strdup(NULL, "Changed log level for the main process\n");
		}
		else
			zbx_signal_process_by_pid((int)pid, ZBX_RTC_MAKE_MESSAGE(code, 0, 0), result);

		return;
	}

	if (SUCCEED == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_PROCESS_NUM, buf, sizeof(buf), NULL))
		process_num = atoi(buf);

	if (SUCCEED != zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_PROCESS_NAME, buf, sizeof(buf), NULL))
	{
		*result = zbx_dsprintf(NULL, "Invalid parameters \"%s\"\n", data);
		return;
	}

	if (ZBX_PROCESS_TYPE_UNKNOWN == (process_type = get_process_type_by_name(buf)))
	{
		*result = zbx_dsprintf(NULL, "Invalid parameters \"%s\"\n", data);
		return;
	}

	zbx_signal_process_by_type(process_type, process_num, ZBX_RTC_MAKE_MESSAGE(code, 0, 0), result);
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
		scope = (1 << ZBX_DIAGINFO_HISTORYCACHE) | (1 << ZBX_DIAGINFO_PREPROCESSING) | (1 << ZBX_DIAGINFO_LOCKS);
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

void	rtc_notify(zbx_rtc_t *rtc, unsigned char process_type, int process_num, zbx_uint32_t code,
		const unsigned char *data, zbx_uint32_t size)
{
	int	i;

	for (i = 0; i < rtc->subs.values_num; i++)
	{
		if (ZBX_PROCESS_TYPE_UNKNOWN != process_type && process_type != rtc->subs.values[i]->process_type)
			continue;

		if (0 != process_num && process_num != rtc->subs.values[i]->process_num)
			continue;

		if (FAIL == zbx_ipc_client_send(rtc->subs.values[i]->client, code, data, size))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot send RTC notification to process \"%s\" #%d",
					get_process_type_string(rtc->subs.values[i]->process_type),
					rtc->subs.values[i]->process_num);
		}
	}
}

static void	rtc_subscribe(zbx_rtc_t *rtc, zbx_ipc_client_t *client, const unsigned char *data)
{
	zbx_rtc_sub_t	*sub;

	sub = (zbx_rtc_sub_t *)zbx_malloc(NULL, sizeof(zbx_rtc_sub_t));

	sub->client = client;
	data += zbx_deserialize_value(data, &sub->process_type);
	(void)zbx_deserialize_value(data, &sub->process_num);

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
static void	rtc_process_request(zbx_rtc_t *rtc, int code, const unsigned char *data,
		char **result)
{
	switch (code)
	{
#if defined(HAVE_SIGQUEUE)
		case ZBX_RTC_LOG_LEVEL_INCREASE:
		case ZBX_RTC_LOG_LEVEL_DECREASE:
			rtc_process_loglevel(code, (const char *)data, result);
			return;
#endif
		case ZBX_RTC_HOUSEKEEPER_EXECUTE:
			rtc_notify(rtc, ZBX_PROCESS_TYPE_HOUSEKEEPER, 0, ZBX_RTC_HOUSEKEEPER_EXECUTE, NULL, 0);
			return;
		case ZBX_RTC_CONFIG_CACHE_RELOAD:
			rtc_notify(rtc, ZBX_PROCESS_TYPE_CONFSYNCER, 0, ZBX_RTC_CONFIG_CACHE_RELOAD, NULL, 0);
			return;
		case ZBX_RTC_SNMP_CACHE_RELOAD:
#ifdef HAVE_NETSNMP
			rtc_notify(rtc, ZBX_PROCESS_TYPE_POLLER, 0, ZBX_RTC_SNMP_CACHE_RELOAD, NULL, 0);
			rtc_notify(rtc, ZBX_PROCESS_TYPE_UNREACHABLE, 0, ZBX_RTC_SNMP_CACHE_RELOAD, NULL, 0);
			rtc_notify(rtc, ZBX_PROCESS_TYPE_TRAPPER, 0, ZBX_RTC_SNMP_CACHE_RELOAD, NULL, 0);
			rtc_notify(rtc, ZBX_PROCESS_TYPE_DISCOVERER, 0, ZBX_RTC_SNMP_CACHE_RELOAD, NULL, 0);
			rtc_notify(rtc, ZBX_PROCESS_TYPE_TASKMANAGER, 0, ZBX_RTC_SNMP_CACHE_RELOAD, NULL, 0);
#else
			*result = zbx_strdup(NULL, "Invalid runtime control option: no SNMP support enabled\n");
#endif
			return;
		case ZBX_RTC_DIAGINFO:
			rtc_process_diaginfo((const char *)data, result);
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
static void	rtc_process(zbx_rtc_t *rtc, zbx_ipc_client_t *client, int code, const unsigned char *data)
{
	char		*result = NULL, *result_ex = NULL;
	zbx_uint32_t	size = 0;

	if (FAIL == rtc_process_request_ex(rtc, code, data, &result_ex))
		rtc_process_request(rtc, code, data, &result);

	if (NULL != result_ex)
		result = zbx_strdcat(result, result_ex);

	if (NULL == result)
	{
		/* generate default success message if no specific success or error messages were returned */
		result = zbx_strdup(NULL, "Runtime control command was forwarded successfully\n");
	}

	size = (zbx_uint32_t)strlen(result) + 1;
	zbx_ipc_client_send(client, (zbx_uint32_t)code, (unsigned char *)result, size);
	zbx_free(result);

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
void	zbx_rtc_dispatch(zbx_rtc_t *rtc, zbx_ipc_client_t *client, zbx_ipc_message_t *message)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s() code:%u size:%u", __func__, message->code, message->size);

	switch (message->code)
	{
		case ZBX_RTC_SUBSCRIBE:
			rtc_subscribe(rtc, client, message->data);
			break;
		case ZBX_RTC_CONFIG_CACHE_RELOAD_WAIT:
			rtc_add_control_hook(rtc, client, ZBX_RTC_CONFIG_SYNC_NOTIFY);
			rtc_notify(rtc, ZBX_PROCESS_TYPE_CONFSYNCER, 0, ZBX_RTC_CONFIG_CACHE_RELOAD, NULL, 0);
			break;
		case ZBX_RTC_CONFIG_SYNC_NOTIFY:
			rtc_notify_hooks(rtc, message->code, message->data, message->size);
			break;
		default:
			rtc_process(rtc, client, (int)message->code, message->data);
			break;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: wait for configuration sync notification while optionally         *
 *          dispatching runtime control commands                              *
 *                                                                            *
 ******************************************************************************/
int	zbx_rtc_wait_config_sync(zbx_rtc_t *rtc)
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
					sync = 1;
					break;
				case ZBX_RTC_LOG_LEVEL_DECREASE:
				case ZBX_RTC_LOG_LEVEL_INCREASE:
				case ZBX_RTC_SUBSCRIBE:
					zbx_rtc_dispatch(rtc, client, message);
					break;
				default:
					if (ZBX_IPC_RTC_MAX >= message->code)
					{
						const char *rtc_error = "Cannot perform specified runtime control"
								" command during initial configuration cache sync\n";
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
	rtc_notify(rtc, ZBX_PROCESS_TYPE_UNKNOWN, 0, ZBX_RTC_SHUTDOWN, NULL, 0);
}

/******************************************************************************
 *                                                                            *
 * Purpose: reset the RTC service state by removing subscriptions and hooks   *
 *                                                                            *
 ******************************************************************************/
void	zbx_rtc_reset(zbx_rtc_t *rtc)
{
	int	i;

	for (i = 0; i < rtc->subs.values_num; i++)
	{
		zbx_ipc_client_close(rtc->subs.values[i]->client);
		zbx_free(rtc->subs.values[i]);
	}
	zbx_vector_rtc_sub_clear(&rtc->subs);

	for (i = 0; i < rtc->hooks.values_num; i++)
		zbx_free(rtc->hooks.values[i]);

	zbx_vector_rtc_hook_clear(&rtc->hooks);
}
