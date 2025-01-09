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
#include "rtc_server.h"

#include "zbxlog.h"
#include "zbxdiag.h"
#include "zbxtypes.h"
#include "zbxcommon.h"
#include "zbxservice.h"
#include "zbx_rtc_constants.h"
#include "zbxjson.h"
#include "zbxtime.h"
#include "../ha/ha.h"

static int	rtc_parse_options_server(const char *opt, zbx_uint32_t *code, struct zbx_json *j, char **error)
{
	const char	*param;

	if (0 == strcmp(opt, ZBX_SECRETS_RELOAD))
	{
		*code = ZBX_RTC_SECRETS_RELOAD;
		return SUCCEED;
	}

	if (0 == strcmp(opt, ZBX_SERVICE_CACHE_RELOAD))
	{
		*code = ZBX_RTC_SERVICE_CACHE_RELOAD;
		return SUCCEED;
	}

	if (0 == strcmp(opt, ZBX_TRIGGER_HOUSEKEEPER_EXECUTE))
	{
		*code = ZBX_RTC_TRIGGER_HOUSEKEEPER_EXECUTE;
		return SUCCEED;
	}

	if (0 == strcmp(opt, ZBX_HA_STATUS))
	{
		*code = ZBX_RTC_HA_STATUS;
		return SUCCEED;
	}

	if (0 == strncmp(opt, ZBX_HA_REMOVE_NODE, ZBX_CONST_STRLEN(ZBX_HA_REMOVE_NODE)))
	{
		param = opt + ZBX_CONST_STRLEN(ZBX_HA_REMOVE_NODE);

		if ('=' == *param)
		{
			*code = ZBX_RTC_HA_REMOVE_NODE;
			zbx_json_addstring(j, ZBX_PROTO_TAG_NODE, param + 1, ZBX_JSON_TYPE_STRING);

			return SUCCEED;
		}

		if ('\0' == *param)
		{
			*error = zbx_strdup(NULL, "missing node cuid or name parameter");
			return FAIL;
		}

		/* not ha_remove_node runtime control option */
	}

	if (0 == strncmp(opt, ZBX_HA_SET_FAILOVER_DELAY, ZBX_CONST_STRLEN(ZBX_HA_SET_FAILOVER_DELAY)))
	{
		int	delay;

		param = opt + ZBX_CONST_STRLEN(ZBX_HA_SET_FAILOVER_DELAY);

		if ('=' == *param)
		{
			if (SUCCEED == zbx_is_time_suffix(param + 1, &delay, ZBX_LENGTH_UNLIMITED))
			{
				if (delay < 10 || delay > 15 * SEC_PER_MIN)
				{
					*error = zbx_strdup(NULL, "failover delay must be in range from 10s to 15m");
					return FAIL;
				}

				*code = ZBX_RTC_HA_SET_FAILOVER_DELAY;
				zbx_json_addint64(j, ZBX_PROTO_TAG_FAILOVER_DELAY, delay);

				return SUCCEED;
			}
			else
			{
				*error = zbx_dsprintf(NULL, "invalid HA failover delay parameter: %s\n", param + 1);
				return FAIL;
			}
		}

		if ('\0' == *param)
		{
			*error = zbx_strdup(NULL, "missing failover delay parameter");
			return FAIL;
		}
	}

	if (0 == strncmp(opt, ZBX_PROXY_CONFIG_CACHE_RELOAD, ZBX_CONST_STRLEN(ZBX_PROXY_CONFIG_CACHE_RELOAD)))
	{
		param = opt + ZBX_CONST_STRLEN(ZBX_PROXY_CONFIG_CACHE_RELOAD);

		if ('=' == *param)
		{
			char	*token, *p;

			if ('\0' == *(param + 1))
			{
				*error = zbx_strdup(NULL, "missing proxy name(s)");
				return FAIL;
			}

			zbx_json_addarray(j, ZBX_PROTO_TAG_PROXY_NAMES);

			p = zbx_strdup(NULL, param + 1);
			token = strtok(p, ",");

			while (NULL != token)
			{
				zbx_json_addstring(j, NULL, token, ZBX_JSON_TYPE_STRING);
				token = strtok(NULL, ",");
			}

			zbx_free(p);

			*code = ZBX_RTC_PROXY_CONFIG_CACHE_RELOAD;

			return SUCCEED;
		}

		if ('\0' == *param)
		{
			*code = ZBX_RTC_PROXY_CONFIG_CACHE_RELOAD;

			return SUCCEED;
		}
	}

	return SUCCEED;
}

#if defined(HAVE_SIGQUEUE)

/******************************************************************************
 *                                                                            *
 * Purpose: process loglevel runtime control option                           *
 *                                                                            *
 * Parameters: direction - [IN] the loglevel change direction:                *
 *                               (1) - increase, (-1) - decrease              *
 *             data      - [IN] the runtime control parameter (optional)      *
 *             result    - [OUT] the runtime control result                   *
 *                                                                            *
 * Return value: SUCCEED - the loglevel command was processed                 *
 *               FAIL    - the loglevel command must be processed by the      *
 *                         default loglevel command handler                   *
 *                                                                            *
 ******************************************************************************/
static int	rtc_process_server_loglevel_option(int direction, const char *data, char **result)
{
	int	proc_num, proc_type;
	pid_t	pid;

	if (SUCCEED != zbx_rtc_get_command_target(data, &pid, &proc_type, &proc_num, NULL, result))
		return SUCCEED;

	/* change loglevel for all processes */
	if (0 == pid && ZBX_PROCESS_TYPE_UNKNOWN == proc_type)
	{
		(void)zbx_ha_change_loglevel(direction, result);
		return FAIL;
	}

	if (ZBX_PROCESS_TYPE_HA_MANAGER == proc_type)
	{
		if (0 != proc_num && 1 != proc_num)
		{
			*result = zbx_dsprintf(NULL, "Invalid option parameter \"%d\"\n", proc_num);
		}
		else
		{
			(void)zbx_ha_change_loglevel(direction, result);
			*result = zbx_strdup(NULL, "Changed HA manager log level\n");

		}
		return SUCCEED;
	}

	return FAIL;
}

#endif

/******************************************************************************
 *                                                                            *
 * Purpose: process diaginfo runtime control option                           *
 *                                                                            *
 * Parameters: data   - [IN] the runtime control parameter (optional)         *
 *             result - [OUT] the runtime control result                      *
 *                                                                            *
 * Return value: SUCCEED - the rtc command was processed                      *
 *               FAIL    - the rtc command must be processed by the default   *
 *                         rtc command handler                                *
 *                                                                            *
 ******************************************************************************/
static int	rtc_process_diaginfo(const char *data, char **result)
{
	struct zbx_json_parse	jp;
	char			buf[MAX_STRING_LEN];
	unsigned int		scope = 0;
	int			ret = FAIL;

	if (FAIL == zbx_json_open(data, &jp) ||
			SUCCEED != zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_SECTION, buf, sizeof(buf), NULL))
	{
		*result = zbx_dsprintf(NULL, "Invalid parameter \"%s\"\n", data);
		return FAIL;
	}

	if (0 == strcmp(buf, "all"))
	{
		scope = (1 << ZBX_DIAGINFO_VALUECACHE) | (1 << ZBX_DIAGINFO_LLD) | (1 << ZBX_DIAGINFO_ALERTING) |
				(1 << ZBX_DIAGINFO_CONNECTOR);
	}
	else if (0 == strcmp(buf, ZBX_DIAG_VALUECACHE))
	{
		scope = 1 << ZBX_DIAGINFO_VALUECACHE;
		ret = SUCCEED;
	}
	else if (0 == strcmp(buf, ZBX_DIAG_LLD))
	{
		scope = 1 << ZBX_DIAGINFO_LLD;
		ret = SUCCEED;
	}
	else if (0 == strcmp(buf, ZBX_DIAG_ALERTING))
	{
		scope = 1 << ZBX_DIAGINFO_ALERTING;
		ret = SUCCEED;
	}
	else if (0 == strcmp(buf, ZBX_DIAG_CONNECTOR))
	{
		scope = 1 << ZBX_DIAGINFO_CONNECTOR;
		ret = SUCCEED;
	}

	if (0 != scope)
		zbx_diag_log_info(scope, result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: process ha_status runtime command                                 *
 *                                                                            *
 ******************************************************************************/
static void	rtc_ha_status(char **out)
{
	char			*nodes = NULL, *error = NULL;
	struct zbx_json_parse	jp, jp_node;
	size_t			out_alloc = 0, out_offset = 0;
	int			failover_delay;

	if (SUCCEED != zbx_ha_get_failover_delay(&failover_delay, &error))
	{
		zbx_strlog_alloc(LOG_LEVEL_ERR, out, &out_alloc, &out_offset, "cannot get failover delay: %s",
				error);
		zbx_free(error);
		return;
	}

	if (SUCCEED != zbx_ha_get_nodes(&nodes, &error))
	{
		zbx_strlog_alloc(LOG_LEVEL_ERR, out, &out_alloc, &out_offset, "cannot get HA node information: %s",
				error);
		zbx_free(error);
		return;
	}

#define ZBX_HA_REPORT_FMT	"%-25s %-25s %-30s %-11s %s"

	if (SUCCEED == zbx_json_open(nodes, &jp))
	{
		const char	*pnext;
		char		name[256], address[261], id[26], buffer[256];
		int		status, lastaccess_age, index = 1;

		zbx_strlog_alloc(LOG_LEVEL_INFORMATION, out, &out_alloc, &out_offset, "failover delay: %d seconds",
				failover_delay);

		zbx_strlog_alloc(LOG_LEVEL_INFORMATION, out, &out_alloc, &out_offset, "cluster status:");
		zbx_strlog_alloc(LOG_LEVEL_INFORMATION, out, &out_alloc, &out_offset, "  %2s  " ZBX_HA_REPORT_FMT, "#",
				"ID", "Name", "Address", "Status", "Last Access");

		for (pnext = NULL; NULL != (pnext = zbx_json_next(&jp, pnext));)
		{
			if (FAIL == zbx_json_brackets_open(pnext, &jp_node))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			if (SUCCEED != zbx_json_value_by_name(&jp_node, ZBX_PROTO_TAG_ID, id, sizeof(id), NULL))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			if (SUCCEED != zbx_json_value_by_name(&jp_node, ZBX_PROTO_TAG_NAME, name, sizeof(name),
					NULL))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			if (SUCCEED != zbx_json_value_by_name(&jp_node, ZBX_PROTO_TAG_STATUS, buffer,
					sizeof(buffer), NULL))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}
			status = atoi(buffer);

			if (SUCCEED != zbx_json_value_by_name(&jp_node, ZBX_PROTO_TAG_LASTACCESS_AGE, buffer,
					sizeof(buffer), NULL))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}
			lastaccess_age = atoi(buffer);

			if (SUCCEED != zbx_json_value_by_name(&jp_node, ZBX_PROTO_TAG_ADDRESS, address,
					sizeof(address), NULL))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			zbx_strlog_alloc(LOG_LEVEL_INFORMATION, out, &out_alloc, &out_offset, "  %2d. "
					ZBX_HA_REPORT_FMT, index++, id, '\0' != *name ? name : "<standalone server>",
					address, zbx_ha_status_str(status), zbx_age2str(lastaccess_age));
		}
	}
	else
	{
		zbx_strlog_alloc(LOG_LEVEL_ERR, out, &out_alloc, &out_offset, "invalid response: %s",
				nodes);
	}
	zbx_free(nodes);

#undef ZBX_HA_REPORT_FMT
}

/******************************************************************************
 *                                                                            *
 * Purpose: process ha_remove_node runtime command                            *
 *                                                                            *
 ******************************************************************************/
static void	rtc_ha_remove_node(const char *data, char **out)
{
	char			*error = NULL;
	struct zbx_json_parse	jp;
	char			buf[MAX_STRING_LEN];
	size_t			out_alloc = 0, out_offset = 0;

	if (FAIL == zbx_json_open(data, &jp))
	{
		*out = zbx_dsprintf(NULL, "Invalid parameter format \"%s\"\n", data);
		return;
	}

	if (SUCCEED != zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_NODE, buf, sizeof(buf), NULL))
	{
		*out = zbx_dsprintf(NULL, "Missing node parameter \"%s\"\n", data);
		return;
	}

	if (SUCCEED != zbx_ha_remove_node(buf, out, &error))
	{
		zbx_strlog_alloc(LOG_LEVEL_ERR, out, &out_alloc, &out_offset, "cannot remove HA node: %s", error);
		zbx_free(error);
		return;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: process ha_failover_delay runtime command                         *
 *                                                                            *
 ******************************************************************************/
static void	rtc_ha_failover_delay(const char *data, char **out)
{
	char			*error = NULL;
	struct zbx_json_parse	jp;
	char			buf[MAX_STRING_LEN];
	int			failover_delay;
	size_t			out_alloc = 0, out_offset = 0;

	if (FAIL == zbx_json_open(data, &jp))
	{
		*out = zbx_dsprintf(NULL, "Invalid parameter format \"%s\"\n", data);
		return;
	}

	if (SUCCEED != zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_FAILOVER_DELAY, buf, sizeof(buf), NULL))
	{
		*out = zbx_dsprintf(NULL, "Missing failover_delay parameter \"%s\"\n", data);
		return;
	}

	if (10 > (failover_delay = atoi(buf)) || 15 * SEC_PER_MIN < failover_delay)
	{
		*out = zbx_dsprintf(NULL, "Invalid failover delay value \"%s\"\n", buf);
		return;
	}

	if (SUCCEED != zbx_ha_set_failover_delay(failover_delay, &error))
	{
		zbx_strlog_alloc(LOG_LEVEL_ERR, out, &out_alloc, &out_offset, "cannot set HA failover delay: %s", error);
		zbx_free(error);
		return;
	}

	*out = zbx_dsprintf(NULL, "HA failover delay set to %d seconds\n", failover_delay);

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
 * Return value: SUCCEED - the rtc command was processed                      *
 *               FAIL    - the rtc command must be processed by the default   *
 *                         rtc command handler                                *
 *                                                                            *
 ******************************************************************************/
int	rtc_process_request_ex_server(zbx_rtc_t *rtc, zbx_uint32_t code, const unsigned char *data, char **result)
{
	switch (code)
	{
#if defined(HAVE_SIGQUEUE)
		case ZBX_RTC_LOG_LEVEL_INCREASE:
			return rtc_process_server_loglevel_option(1, (const char *)data, result);
		case ZBX_RTC_LOG_LEVEL_DECREASE:
			return rtc_process_server_loglevel_option(-1, (const char *)data, result);
#endif
		case ZBX_RTC_CONFIG_CACHE_RELOAD:
			zbx_service_reload_cache();
			return FAIL;
		case ZBX_RTC_SERVICE_CACHE_RELOAD:
			zbx_service_reload_cache();
			return SUCCEED;
		case ZBX_RTC_SECRETS_RELOAD:
			zbx_rtc_notify(rtc, ZBX_PROCESS_TYPE_CONFSYNCER, 0, ZBX_RTC_SECRETS_RELOAD, NULL, 0);
			return SUCCEED;
		case ZBX_RTC_TRIGGER_HOUSEKEEPER_EXECUTE:
			zbx_rtc_notify(rtc, ZBX_PROCESS_TYPE_TRIGGERHOUSEKEEPER, 0, ZBX_RTC_TRIGGER_HOUSEKEEPER_EXECUTE,
					NULL, 0);
			return SUCCEED;
		case ZBX_RTC_DIAGINFO:
			return rtc_process_diaginfo((const char *)data, result);
		case ZBX_RTC_HA_STATUS:
			rtc_ha_status(result);
			return SUCCEED;
		case ZBX_RTC_HA_SET_FAILOVER_DELAY:
			rtc_ha_failover_delay((const char *)data, result);
			return SUCCEED;
		case ZBX_RTC_HA_REMOVE_NODE:
			rtc_ha_remove_node((const char *)data, result);
			return SUCCEED;
		case ZBX_RTC_PROXY_CONFIG_CACHE_RELOAD:
			zbx_rtc_notify(rtc, ZBX_PROCESS_TYPE_TASKMANAGER, 0, ZBX_RTC_PROXY_CONFIG_CACHE_RELOAD,
					(const char *)data, (zbx_uint32_t)strlen((const char *)data) + 1);
			return SUCCEED;
		case ZBX_RTC_PROXYPOLLER_PROCESS:
			zbx_rtc_notify(rtc, ZBX_PROCESS_TYPE_PROXYPOLLER, 0, ZBX_RTC_PROXYPOLLER_PROCESS, NULL, 0);
			return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: process runtime control option and print result                   *
 *                                                                            *
 * Parameters: option            - [IN] runtime control option                *
 *             config_timeout    - [IN]                                       *
 *             error             - [OUT] error message                        *
 *                                                                            *
 * Return value: SUCCEED - the runtime control option was processed           *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	rtc_process(const char *option, int config_timeout, char **error)
{
	zbx_uint32_t	code = ZBX_RTC_UNKNOWN;
	char		*data;
	int		ret = FAIL;
	struct zbx_json	j;

	zbx_json_init(&j, 1024);

	if (SUCCEED != zbx_rtc_parse_options(option, &code, &j, error))
		goto out;

	if (ZBX_RTC_UNKNOWN == code)
	{
		if (SUCCEED != rtc_parse_options_server(option, &code, &j, error))
			goto out;

		if (ZBX_RTC_UNKNOWN == code)
		{
			*error = zbx_dsprintf(NULL, "unknown option \"%s\"", option);
			goto out;
		}
	}

	data = zbx_strdup(NULL, j.buffer);
	ret = zbx_rtc_async_exchange(&data, code, config_timeout, error);
out:
	zbx_json_free(&j);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: reset the RTC service state by removing subscriptions and hooks   *
 *                                                                            *
 ******************************************************************************/
void	rtc_reset(zbx_rtc_t *rtc)
{
	int	i;

	zbx_vector_rtc_sub_clear_ext(&rtc->subs, zbx_rtc_sub_free);

	for (i = 0; i < rtc->hooks.values_num; i++)
		zbx_free(rtc->hooks.values[i]);

	zbx_vector_rtc_hook_clear(&rtc->hooks);
}

int	rtc_open(zbx_ipc_async_socket_t *asocket, int timeout, char **error)
{
	if (FAIL == zbx_ipc_async_socket_open(asocket, ZBX_IPC_SERVICE_RTC, timeout, error))
		return FAIL;

	return SUCCEED;
}

