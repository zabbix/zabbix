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

#include "log.h"
#include "zbxdiag.h"
#include "zbxjson.h"
#include "zbxha.h"
#include "zbxtypes.h"
#include "common.h"

#include "rtc.h"
#include "zbxservice.h"

int	rtc_parse_options_ex(const char *opt, zbx_uint32_t *code, char **data, char **error)
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
			struct zbx_json	j;

			*code = ZBX_RTC_HA_REMOVE_NODE;

			zbx_json_init(&j, 1024);
			zbx_json_addstring(&j, ZBX_PROTO_TAG_NODE, param + 1, ZBX_JSON_TYPE_STRING);
			*data = zbx_strdup(NULL, j.buffer);
			zbx_json_clean(&j);

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
			if (SUCCEED == is_time_suffix(param + 1, &delay, ZBX_LENGTH_UNLIMITED))
			{
				struct zbx_json	j;

				if (delay < 10 || delay > 15 * SEC_PER_MIN)
				{
					*error = zbx_strdup(NULL, "failover delay must be in range from 10s to 15m");
					return FAIL;
				}

				*code = ZBX_RTC_HA_SET_FAILOVER_DELAY;

				zbx_json_init(&j, 1024);
				zbx_json_addint64(&j, ZBX_PROTO_TAG_FAILOVER_DELAY, delay);
				*data = zbx_strdup(NULL, j.buffer);
				zbx_json_clean(&j);

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
static int	rtc_process_loglevel(int direction, const char *data, char **result)
{
	struct zbx_json_parse	jp;
	char			buf[MAX_STRING_LEN];
	int			process_num = 0;

	if (NULL == data)
	{
		(void)zbx_ha_change_loglevel(direction, result);
		return FAIL;
	}

	if (FAIL == zbx_json_open(data, &jp))
	{
		*result = zbx_dsprintf(NULL, "Invalid parameters \"%s\"\n", data);
		return SUCCEED;
	}

	if (SUCCEED == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_PROCESS_NUM, buf, sizeof(buf), NULL))
		process_num = atoi(buf);

	if (SUCCEED != zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_PROCESS_NAME, buf, sizeof(buf), NULL))
	{
		return FAIL;
	}

	if (0 == strcmp(buf, "ha manager"))
	{
		if (0 != process_num && 1 != process_num)
		{
			*result = zbx_dsprintf(NULL, "Invalid option parameter \"%d\"\n", process_num);
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
 ******************************************************************************/
static void	rtc_process_diaginfo(const char *data, char **result)
{
	struct zbx_json_parse	jp;
	char			buf[MAX_STRING_LEN];
	unsigned int		scope;

	if (FAIL == zbx_json_open(data, &jp) ||
			SUCCEED != zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_SECTION, buf, sizeof(buf), NULL))
	{
		*result = zbx_dsprintf(NULL, "Invalid parameter \"%s\"\n", data);
		return;
	}

	if (0 == strcmp(buf, "all"))
		scope = (1 << ZBX_DIAGINFO_VALUECACHE) | (1 << ZBX_DIAGINFO_LLD) | (1 << ZBX_DIAGINFO_ALERTING);
	else if (0 == strcmp(buf, ZBX_DIAG_VALUECACHE))
		scope = 1 << ZBX_DIAGINFO_VALUECACHE;
	else if (0 == strcmp(buf, ZBX_DIAG_LLD))
		scope = 1 << ZBX_DIAGINFO_LLD;
	else if (0 == strcmp(buf, ZBX_DIAG_ALERTING))
		scope = 1 << ZBX_DIAGINFO_ALERTING;
	else
		return;

	zbx_diag_log_info(scope, result);
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
int	rtc_process_request_ex(zbx_rtc_t *rtc, int code, const unsigned char *data, char **result)
{
	ZBX_UNUSED(data);

	switch (code)
	{
#if defined(HAVE_SIGQUEUE)
		case ZBX_RTC_LOG_LEVEL_INCREASE:
			return rtc_process_loglevel(1, (const char *)data, result);
		case ZBX_RTC_LOG_LEVEL_DECREASE:
			return rtc_process_loglevel(-1, (const char *)data, result);
#endif
		case ZBX_RTC_CONFIG_CACHE_RELOAD:
			zbx_service_reload_cache();
			return FAIL;
		case ZBX_RTC_SERVICE_CACHE_RELOAD:
			zbx_service_reload_cache();
			return SUCCEED;
		case ZBX_RTC_SECRETS_RELOAD:
			rtc_notify(rtc, ZBX_PROCESS_TYPE_CONFSYNCER, 0, ZBX_RTC_SECRETS_RELOAD, NULL, 0);
			return SUCCEED;
		case ZBX_RTC_TRIGGER_HOUSEKEEPER_EXECUTE:
			rtc_notify(rtc, ZBX_PROCESS_TYPE_TRIGGERHOUSEKEEPER, 0, ZBX_RTC_TRIGGER_HOUSEKEEPER_EXECUTE,
					NULL, 0);
			return SUCCEED;
		case ZBX_RTC_DIAGINFO:
			rtc_process_diaginfo((const char *)data, result);
			return FAIL;
		case ZBX_RTC_HA_STATUS:
			rtc_ha_status(result);
			return SUCCEED;
		case ZBX_RTC_HA_SET_FAILOVER_DELAY:
			rtc_ha_failover_delay((const char *)data, result);
			return SUCCEED;
		case ZBX_RTC_HA_REMOVE_NODE:
			rtc_ha_remove_node((const char *)data, result);
			return SUCCEED;
	}

	return FAIL;
}
