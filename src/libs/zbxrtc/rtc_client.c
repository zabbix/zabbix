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

#include "rtc.h"

#include "common.h"
#include "zbxserialize.h"
#include "zbxjson.h"
#include "zbxself.h"
#include "log.h"

extern int	CONFIG_TIMEOUT;

/******************************************************************************
 *                                                                            *
 * Purpose: parse loglevel runtime control option                             *
 *                                                                            *
 * Parameters: opt   - [IN] the runtime control option                        *
 *             len   - [IN] the runtime control option length without         *
 *                          parameter                                         *
 *             data  - [OUT] the runtime control option result                *
 *             error - [OUT] error message                                    *
 *                                                                            *
 * Return value: SUCCEED - the runtime control option was processed           *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	rtc_parse_log_level_parameter(const char *opt, size_t len, char **data, char **error)
{
	struct 	zbx_json	j;
	const char		*proc_name;
	int			pid = 0, proc_num = 0, proc_type = ZBX_PROCESS_TYPE_UNKNOWN;

	if (SUCCEED != zbx_rtc_parse_loglevel_option(opt, len, &pid, &proc_type, &proc_num, error))
		return FAIL;

	if (0 != pid)
	{
		zbx_json_init(&j, 1024);
		zbx_json_addint64(&j, ZBX_PROTO_TAG_PID, pid);
		goto finish;
	}

	if (ZBX_PROCESS_TYPE_UNKNOWN == proc_type)
		return SUCCEED;

	proc_name = get_process_type_string((unsigned char)proc_type);

	zbx_json_init(&j, 1024);
	zbx_json_addstring(&j, ZBX_PROTO_TAG_PROCESS_NAME, proc_name, ZBX_JSON_TYPE_STRING);

	if (0 != proc_num)
		zbx_json_addint64(&j, ZBX_PROTO_TAG_PROCESS_NUM, proc_num);

finish:
	*data = zbx_strdup(NULL, j.buffer);
	zbx_json_clean(&j);

	return SUCCEED;
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
static int	rtc_parse_options(const char *opt, zbx_uint32_t *code, char **data, char **error)
{
	if (0 == strncmp(opt, ZBX_LOG_LEVEL_INCREASE, ZBX_CONST_STRLEN(ZBX_LOG_LEVEL_INCREASE)))
	{
		*code = ZBX_RTC_LOG_LEVEL_INCREASE;

		return rtc_parse_log_level_parameter(opt, ZBX_CONST_STRLEN(ZBX_LOG_LEVEL_INCREASE), data, error);
	}

	if (0 == strncmp(opt, ZBX_LOG_LEVEL_DECREASE, ZBX_CONST_STRLEN(ZBX_LOG_LEVEL_DECREASE)))
	{
		*code = ZBX_RTC_LOG_LEVEL_DECREASE;

		return rtc_parse_log_level_parameter(opt, ZBX_CONST_STRLEN(ZBX_LOG_LEVEL_DECREASE), data, error);
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
			struct 	zbx_json	j;

			*code = ZBX_RTC_DIAGINFO;

			zbx_json_init(&j, 1024);
			zbx_json_addstring(&j, ZBX_PROTO_TAG_SECTION, param, ZBX_JSON_TYPE_STRING);
			*data = zbx_strdup(NULL, j.buffer);
			zbx_json_clean(&j);

			return SUCCEED;
		}
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: process runtime control option and print result                   *
 *                                                                            *
 * Parameters: option   - [IN] the runtime control option                     *
 *             error    - [OUT] error message                                 *
 *                                                                            *
 * Return value: SUCCEED - the runtime control option was processed           *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_rtc_process(const char *option, char **error)
{
	zbx_uint32_t	code = ZBX_RTC_UNKNOWN, size = 0;
	char		*data = NULL;
	unsigned char	*result = NULL;
	int		ret;

	if (SUCCEED != rtc_parse_options(option, &code, &data, error))
		return FAIL;

	if (ZBX_RTC_UNKNOWN == code)
	{
		if (SUCCEED != rtc_parse_options_ex(option, &code, &data, error))
			return FAIL;

		if (ZBX_RTC_UNKNOWN == code)
		{
			*error = zbx_dsprintf(NULL, "unknown option \"%s\"", option);
			return FAIL;
		}
	}

#if !defined(HAVE_SIGQUEUE)
	switch (code)
	{
		/* allow only socket based runtime control options */
		case ZBX_RTC_LOG_LEVEL_DECREASE:
		case ZBX_RTC_LOG_LEVEL_INCREASE:
			*error = zbx_dsprintf(NULL, "operation is not supported on the given operating system");
			return FAIL;
	}
#endif

	if (NULL != data)
		size = (zbx_uint32_t)strlen(data) + 1;

	if (SUCCEED == (ret = zbx_ipc_async_exchange(ZBX_IPC_SERVICE_RTC, code, CONFIG_TIMEOUT, (unsigned char *)data,
			size, &result, error)))
	{
		if (NULL != result)
		{
			printf("%s", result);
			zbx_free(result);
		}
		else
			printf("No response\n");

	}

	zbx_free(data);

	return ret;
}

int	zbx_rtc_open(zbx_ipc_async_socket_t *asocket, int timeout, char **error)
{
	if (FAIL == zbx_ipc_async_socket_open(asocket, ZBX_IPC_SERVICE_RTC, timeout, error))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: notify RTC service about finishing initial configuration sync     *
 *                                                                            *
 * Parameters: rtc   - [OUT] the RTC notification subscription socket         *
 *                                                                            *
 ******************************************************************************/
void	zbx_rtc_notify_config_sync(zbx_ipc_async_socket_t *rtc)
{
	if (FAIL == zbx_ipc_async_socket_send(rtc, ZBX_RTC_CONFIG_SYNC_NOTIFY, NULL, 0))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send configuration syncer notification");
		exit(EXIT_FAILURE);
	}

	if (FAIL == zbx_ipc_async_socket_flush(rtc, CONFIG_TIMEOUT))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot flush configuration syncer notification");
		exit(EXIT_FAILURE);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: subscribe process for RTC notifications                           *
 *                                                                            *
 * Parameters: rtc   - [OUT] the RTC notification subscription socket         *
 *                                                                            *
 ******************************************************************************/
void	zbx_rtc_subscribe(zbx_ipc_async_socket_t *rtc, unsigned char proc_type, int proc_num)
{
	unsigned char		data[sizeof(int) + sizeof(unsigned char)];
	const zbx_uint32_t	size = (zbx_uint32_t)(sizeof(int) + sizeof(unsigned char));
	char			*error = NULL;

	if (FAIL == zbx_ipc_async_socket_open(rtc, ZBX_IPC_SERVICE_RTC, CONFIG_TIMEOUT, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot connect to RTC service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	(void)zbx_serialize_value(data, proc_type);
	(void)zbx_serialize_value(data + sizeof(proc_type), proc_num);

	if (FAIL == zbx_ipc_async_socket_send(rtc, ZBX_RTC_SUBSCRIBE, data, size))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send RTC notification subscribe request");
		exit(EXIT_FAILURE);
	}

	if (FAIL == zbx_ipc_async_socket_flush(rtc, CONFIG_TIMEOUT))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot flush RTC notification subscribe request");
		exit(EXIT_FAILURE);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: wait for RTC notification                                         *
 *                                                                            *
 * Parameters: rtc     - [IN] the RTC notification subscription socket        *
 *             cmd     - [OUT] the RTC notification code                      *
 *             data    - [OUT] the RTC notification data                      *
 *             timeout - [OUT] the timeout                                    *
 *             error   - [OUT] error message                                  *
 *                                                                            *
 * Return value: SUCCEED - a notification was received or timeout occurred    *
 *               FAIL    - communication error                                *
 *                                                                            *
 ******************************************************************************/
int	zbx_rtc_wait(zbx_ipc_async_socket_t *rtc, zbx_uint32_t *cmd, unsigned char **data, int timeout)
{
	zbx_ipc_message_t	*message;
	int			ret;

	if (0 != timeout)
		update_selfmon_counter(ZBX_PROCESS_STATE_IDLE);

	ret = zbx_ipc_async_socket_recv(rtc, timeout, &message);

	if (0 != timeout)
		update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

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
