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

#include "zbxrtc.h"
#include "zbx_rtc_constants.h"
#include "rtc.h"

#include "zbxtimekeeper.h"
#include "zbxipcservice.h"
#include "zbxserialize.h"
#include "zbxself.h"
#include "zbxthreads.h"
#include "zbxjson.h"

/******************************************************************************
 *                                                                            *
 * Purpose: parse runtime control option target parameter                     *
 *                                                                            *
 * Parameters: opt        - [IN] the runtime control option                   *
 *             len        - [IN] the runtime control option length without    *
 *                               parameter                                    *
 *             size_total - [IN] total number of parsed bytes (optional)      *
 *             j          - [OUT] the runtime control option result           *
 *             error      - [OUT] error message                               *
 *                                                                            *
 * Return value: SUCCEED - the runtime control option was processed           *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	rtc_parse_target_parameter(const char *opt, size_t len, size_t *size_total, struct zbx_json *j,
		char **error)
{
	const char	*param = opt + len;
	char		*err_reason = NULL;
	int		proc_num, proc_type, ret = FAIL;
	pid_t		pid;
	size_t		size;

	if (SUCCEED != rtc_option_get_parameter(param, &size, &err_reason))
		goto out;

	if (0 == size)
	{
		ret = SUCCEED;
		goto out;
	}

	param += size;

	if (SUCCEED != rtc_option_get_pid(param, &size, &pid, &err_reason))
		goto out;

	if (0 != size)
	{
		param += size;
		zbx_json_addint64(j, ZBX_PROTO_TAG_PID, (zbx_uint64_t)pid);
		ret = SUCCEED;
		goto out;
	}

	if (SUCCEED != rtc_option_get_process_type(param, &size, &proc_type, &err_reason))
		goto out;

	zbx_json_addstring(j, ZBX_PROTO_TAG_PROCESS_NAME, get_process_type_string((unsigned char)proc_type),
			ZBX_JSON_TYPE_STRING);

	param += size;

	if (SUCCEED != rtc_option_get_process_num(param, &size, &proc_num, &err_reason))
		goto out;

	if (0 != size)
	{
		zbx_json_addint64(j, ZBX_PROTO_TAG_PROCESS_NUM, proc_num);
		param += size;
	}

	if ('\0' == *param)
		ret = SUCCEED;
	else
		err_reason = zbx_dsprintf(NULL, "unrecognized parameter part \"%s\"", param);
out:
	if (NULL != size_total)
		*size_total = (size_t)(param - opt);

	if (SUCCEED != ret)
	{
		*error = zbx_dsprintf(NULL, "invalid parameter: %s", err_reason);
		zbx_free(err_reason);
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse profiler runtime control option                             *
 *                                                                            *
 * Parameters: opt   - [IN] the runtime control option                        *
 *             len   - [IN] the runtime control option length without         *
 *                          parameter                                         *
 *             j     - [OUT] the runtime control option result                *
 *             error - [OUT] error message                                    *
 *                                                                            *
 * Return value: SUCCEED - the runtime control option was processed           *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	rtc_parse_profiler_parameter(const char *opt, size_t len, struct zbx_json *j, char **error)
{
	int	ret = FAIL, scope;
	size_t	size;

	ret = rtc_parse_target_parameter(opt, len, &size, j, error);

	if ('\0' != opt[size])
	{
		if (SUCCEED == (ret = rtc_option_get_prof_scope(opt + size, size - len, &scope)))
			zbx_json_addint64(j, ZBX_PROTO_TAG_SCOPE, scope);
	}

	if (SUCCEED != ret)
	{
		/* hard to give specific error reason because scope can be used in any part */
		*error = zbx_dsprintf(NULL, "invalid parameter \"%s\"", opt);
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse runtime control options and create a runtime control        *
 *          message                                                           *
 *                                                                            *
 * Return value: SUCCEED - the message was created successfully               *
 *               FAIL    - an error occurred                                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_rtc_parse_options(const char *opt, zbx_uint32_t *code, struct zbx_json *j, char **error)
{
	if (0 == strncmp(opt, ZBX_LOG_LEVEL_INCREASE, ZBX_CONST_STRLEN(ZBX_LOG_LEVEL_INCREASE)))
	{
		*code = ZBX_RTC_LOG_LEVEL_INCREASE;

		return rtc_parse_target_parameter(opt, ZBX_CONST_STRLEN(ZBX_LOG_LEVEL_INCREASE), NULL, j, error);
	}

	if (0 == strncmp(opt, ZBX_LOG_LEVEL_DECREASE, ZBX_CONST_STRLEN(ZBX_LOG_LEVEL_DECREASE)))
	{
		*code = ZBX_RTC_LOG_LEVEL_DECREASE;

		return rtc_parse_target_parameter(opt, ZBX_CONST_STRLEN(ZBX_LOG_LEVEL_DECREASE), NULL, j, error);
	}

	if (0 == strncmp(opt, ZBX_PROF_ENABLE, ZBX_CONST_STRLEN(ZBX_PROF_ENABLE)))
	{
		*code = ZBX_RTC_PROF_ENABLE;

		return rtc_parse_profiler_parameter(opt, ZBX_CONST_STRLEN(ZBX_PROF_ENABLE), j, error);
	}

	if (0 == strncmp(opt, ZBX_PROF_DISABLE, ZBX_CONST_STRLEN(ZBX_PROF_DISABLE)))
	{
		*code = ZBX_RTC_PROF_DISABLE;

		return rtc_parse_profiler_parameter(opt, ZBX_CONST_STRLEN(ZBX_PROF_DISABLE), j, error);
	}

	if (0 == strcmp(opt, ZBX_CONFIG_CACHE_RELOAD))
	{
		*code = ZBX_RTC_CONFIG_CACHE_RELOAD;
		return SUCCEED;
	}

	if (0 == strcmp(opt, ZBX_HOUSEKEEPER_EXECUTE))
	{
		*code = ZBX_RTC_HOUSEKEEPER_EXECUTE;
		return SUCCEED;
	}

	if (0 == strcmp(opt, ZBX_SNMP_CACHE_RELOAD))
	{
#ifdef HAVE_NETSNMP
		*code = ZBX_RTC_SNMP_CACHE_RELOAD;
		return SUCCEED;
#else
		*error = zbx_strdup(NULL, "invalid runtime control option - no SNMP support enabled");
		return FAIL;
#endif
	}

	if (0 == strncmp(opt, ZBX_DIAGINFO, ZBX_CONST_STRLEN(ZBX_DIAGINFO)))
	{
		const char	*param = opt + ZBX_CONST_STRLEN(ZBX_DIAGINFO);

		if ('=' == *param)
			param++;
		else if ('\0' == *param)
			param = "all";
		else
			param = NULL;

		if (NULL != param)
		{
			*code = ZBX_RTC_DIAGINFO;
			zbx_json_addstring(j, ZBX_PROTO_TAG_SECTION, param, ZBX_JSON_TYPE_STRING);

			return SUCCEED;
		}
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: notify RTC service about finishing initial sync                   *
 *                                                                            *
 * Parameters:                                                                *
 *      config_timeout - [IN]                                                 *
 *      code           - [IN]  the RTC code to be sent                        *
 *      process_name   - [IN]  the process name to be logged                  *
 *      rtc            - [OUT] the RTC notification subscription socket       *
 *                                                                            *
 ******************************************************************************/
void	zbx_rtc_notify_finished_sync(int config_timeout, zbx_uint32_t code, const char *process_name,
		zbx_ipc_async_socket_t *rtc)
{
	if (FAIL == zbx_ipc_async_socket_send(rtc, code, NULL, 0))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send %s notification", process_name);
		exit(EXIT_FAILURE);
	}

	if (FAIL == zbx_ipc_async_socket_flush(rtc, config_timeout))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot flush %s notification", process_name);
		exit(EXIT_FAILURE);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: subscribe process for RTC notifications                           *
 *                                                                            *
 * Parameters:                                                                *
 *      proc_type      - [IN]                                                 *
 *      proc_num       - [IN]                                                 *
 *      msgs            - [IN] the RTC notifications to subscribe for         *
 *      msgs_num        - [IN] the number of RTC notifications                *
 *      config_timeout - [IN]                                                 *
 *      rtc            - [OUT] the RTC notification subscription socket       *
 *                                                                            *
 ******************************************************************************/
void	zbx_rtc_subscribe(unsigned char proc_type, int proc_num, zbx_uint32_t *msgs, int msgs_num, int config_timeout,
		zbx_ipc_async_socket_t *rtc)
{
	const zbx_uint32_t	size = (zbx_uint32_t)(sizeof(unsigned char) + sizeof(int) + sizeof(int) +
				sizeof(zbx_uint32_t) * (zbx_uint32_t)msgs_num);
	unsigned char		*data, *ptr;
	char			*error = NULL;

	if (FAIL == zbx_ipc_async_socket_open(rtc, ZBX_IPC_SERVICE_RTC, config_timeout, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot connect to RTC service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	ptr = data = (unsigned char *)zbx_malloc(NULL, size);

	ptr += zbx_serialize_value(ptr, proc_type);
	ptr += zbx_serialize_value(ptr, proc_num);
	ptr += zbx_serialize_value(ptr, msgs_num);

	for (int i = 0; i < msgs_num; i++)
		ptr += zbx_serialize_value(ptr, msgs[i]);

	if (FAIL == zbx_ipc_async_socket_send(rtc, ZBX_RTC_SUBSCRIBE, data, size))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send RTC notification subscribe request");
		exit(EXIT_FAILURE);
	}

	if (FAIL == zbx_ipc_async_socket_flush(rtc, config_timeout))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot flush RTC notification subscribe request");
		exit(EXIT_FAILURE);
	}

	zbx_free(data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: subscribe process for RTC notifications                           *
 *                                                                            *
 * Parameters:                                                                *
 *      proc_type      - [IN]                                                 *
 *      proc_num       - [IN]                                                 *
 *      msgs           - [IN] the RTC notifications to subscribe for          *
 *      msgs_num       - [IN] the number of RTC notifications                 *
 *      config_timeout - [IN]                                                 *
 *      service        - [IN] the subscriber IPC service                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_rtc_subscribe_service(unsigned char proc_type, int proc_num, zbx_uint32_t *msgs, int msgs_num,
		int config_timeout, const char *service)
{
	unsigned char		*data, *ptr;
	zbx_uint32_t		data_len = 0, service_len;
	char			*error = NULL;
	zbx_ipc_socket_t	sock;

	if (FAIL == zbx_ipc_socket_open(&sock, ZBX_IPC_SERVICE_RTC, config_timeout, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot connect to RTC service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	zbx_serialize_prepare_value(data_len, proc_type);
	zbx_serialize_prepare_value(data_len, proc_num);
	zbx_serialize_prepare_value(data_len, msgs_num);

	for (int i = 0; i < msgs_num; i++)
		zbx_serialize_prepare_value(data_len, msgs[i]);

	zbx_serialize_prepare_str_len(data_len, service, service_len);

	ptr = data = (unsigned char *)zbx_malloc(NULL, (size_t)data_len);

	ptr += zbx_serialize_value(ptr, proc_type);
	ptr += zbx_serialize_value(ptr, proc_num);
	ptr += zbx_serialize_value(ptr, msgs_num);

	for (int i = 0; i < msgs_num; i++)
		ptr += zbx_serialize_value(ptr, msgs[i]);

	(void)zbx_serialize_str(ptr, service, service_len);

	if (FAIL == zbx_ipc_socket_write(&sock, ZBX_RTC_SUBSCRIBE_SERVICE, data, data_len))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send RTC notification service subscribe request");
		exit(EXIT_FAILURE);
	}

	zbx_free(data);
	zbx_ipc_socket_close(&sock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: wait for RTC notification                                         *
 *                                                                            *
 * Parameters: rtc     - [IN] the RTC notification subscription socket        *
 *             info    - [IN] caller process info                             *
 *             cmd     - [OUT] the RTC notification code                      *
 *             data    - [OUT] the RTC notification data                      *
 *             timeout - [OUT] the timeout                                    *
 *                                                                            *
 * Return value: SUCCEED - a notification was received or timeout occurred    *
 *               FAIL    - communication error                                *
 *                                                                            *
 ******************************************************************************/
int	zbx_rtc_wait(zbx_ipc_async_socket_t *rtc, const zbx_thread_info_t *info, zbx_uint32_t *cmd,
		unsigned char **data, int timeout)
{
	zbx_ipc_message_t	*message;
	int			ret;

	if (0 != timeout)
		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_IDLE);

	ret = zbx_ipc_async_socket_recv(rtc, timeout, &message);

	if (0 != timeout)
		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

	if (FAIL == ret)
		return FAIL;

	if (NULL != message)
	{
		*cmd = message->code;
		*data = message->data;
		zbx_free(message);
	}
	else
		*cmd = 0;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: notify RTC service about finishing initial configuration sync     *
 *                                                                            *
 * Parameters: error - [OUT] error message                                    *
 *                                                                            *
 * Return value: SUCCEED - the notification was sent successfully             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_rtc_reload_config_cache(char **error)
{
	unsigned char	*result = NULL;

	if (SUCCEED != zbx_ipc_async_exchange(ZBX_IPC_SERVICE_RTC, ZBX_RTC_CONFIG_CACHE_RELOAD_WAIT,
			ZBX_IPC_WAIT_FOREVER, NULL, 0, &result, error))
	{
		return FAIL;
	}

	zbx_free(result);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: exchange RTC data                                                 *
 *                                                                            *
 * Parameters: data           - [IN/OUT]                                      *
 *             code           - [IN]     message code                         *
 *             config_timeout - [IN]                                          *
 *             error          - [OUT]     error message                       *
 *                                                                            *
 * Return value: SUCCEED - successfully sent message and received response    *
 *               FAIL    - error occurred                                     *
 *                                                                            *
 ******************************************************************************/
int	zbx_rtc_async_exchange(char **data, zbx_uint32_t code, int config_timeout, char **error)
{
	zbx_uint32_t	size = 0;
	unsigned char	*result = NULL;
	int				ret;

#if !defined(HAVE_SIGQUEUE)
	switch (code)
	{
		/* allow only socket based runtime control options */
		case ZBX_RTC_LOG_LEVEL_DECREASE:
		case ZBX_RTC_LOG_LEVEL_INCREASE:
		case ZBX_RTC_PROF_ENABLE:
		case ZBX_RTC_PROF_DISABLE:
			*error = zbx_dsprintf(NULL, "operation is not supported on the given operating system");
			return FAIL;
	}
#endif

	if (NULL != *data)
		size = (zbx_uint32_t)strlen(*data) + 1;

	if (SUCCEED == (ret = zbx_ipc_async_exchange(ZBX_IPC_SERVICE_RTC, code, config_timeout,
			(const unsigned char *)*data, size, &result, error)))
	{
		if (NULL != result)
		{
			printf("%s", result);
			zbx_free(result);
		}
		else
			printf("No response\n");

	}

	zbx_free(*data);

	return ret;
}
