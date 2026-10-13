/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#include "poller_proxy.h"
#include "zbx_apm_client.h"
#include "zbxproxybuffer.h"
#include "zbxcacheconfig.h"

static int	get_apm_export_stats(AGENT_RESULT *result)
{
	zbx_apm_stats_t	stats;
	char		*error = NULL;

	if (FAIL == zbx_apm_get_stats(&stats, &error))
	{
		SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot get APM statistics: %s", error));
		zbx_free(error);

		return NOTSUPPORTED;
	}

	zbx_json_t	j;

	zbx_json_init(&j, 1024);

	zbx_json_addobject(&j, "rows");
	zbx_json_adduint64(&j, "logs", stats.written_logs);
	zbx_json_adduint64(&j, "traces", stats.written_traces);
	zbx_json_adduint64(&j, "metrics_gauge", stats.written_metrics_gauge);
	zbx_json_adduint64(&j, "metrics_sum", stats.written_metrics_sum);
	zbx_json_adduint64(&j, "metrics_histogram", stats.written_metrics_histogram);
	zbx_json_adduint64(&j, "metrics_exponential_histogram", stats.written_metrics_exponential_histogram);
	zbx_json_adduint64(&j, "metrics_summary", stats.written_metrics_summary);
	zbx_json_close(&j);
	zbx_json_addobject(&j, "requests");
	zbx_json_adduint64(&j, "accepted", stats.accepted_requests);
	zbx_json_adduint64(&j, "dropped", stats.dropped_requests);

	SET_TEXT_RESULT(result, zbx_strdup(NULL, j.buffer));

	zbx_json_free(&j);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes program type (proxy) specific internal checks           *
 *                                                                            *
 * Parameters: item    - [IN] item to process                                 *
 *             param1  - [IN] first parameter                                 *
 *             request - [IN]                                                 *
 *             result  - [OUT]                                                *
 *                                                                            *
 * Return value: SUCCEED - data successfully retrieved and stored in result   *
 *               NOTSUPPORTED - requested item is not supported               *
 *               FAIL - not proxy specific internal check                     *
 *                                                                            *
 * Comments: This function is used to process proxy specific internal checks  *
 *           before generic internal checks are processed.                    *
 *                                                                            *
 ******************************************************************************/
int	zbx_get_value_internal_ext_proxy(const zbx_dc_item_t *item, const char *param1,
		const AGENT_REQUEST *request, AGENT_RESULT *result)
{
	ZBX_UNUSED(item);

	if (0 == strcmp(param1, "proxy_history"))
	{
		if (1 != get_rparams_num(request))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			return NOTSUPPORTED;
		}

		SET_UI64_RESULT(result, zbx_pb_history_get_unsent_num());
		return SUCCEED;
	}
	else if (0 == strcmp(param1, "proxy_buffer"))
	{
		const char	*param2, *param3;
		int		params_num;
		char		*error = NULL;

		params_num = get_rparams_num(request);

		if (params_num < 2 || params_num > 3)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			return NOTSUPPORTED;
		}

		param2 = get_rparam(request, 1);
		param3 = get_rparam(request, 2);

		if (0 == strcmp(param2, "buffer"))
		{
			zbx_pb_mem_info_t	info;

			if (SUCCEED != zbx_pb_get_mem_info(&info, &error))
			{
				SET_MSG_RESULT(result, error);
				return NOTSUPPORTED;
			}

			if (NULL == param3 || '\0' == *param3 || 0 == strcmp(param3, "pfree"))
			{
				SET_DBL_RESULT(result, (double)(info.mem_total - info.mem_used) /
						(double)info.mem_total * 100);
			}
			else if (0 == strcmp(param3, "free"))
			{
				SET_UI64_RESULT(result, info.mem_total - info.mem_used);
			}
			else if (0 == strcmp(param3, "total"))
			{
				SET_UI64_RESULT(result, info.mem_total);
			}
			else if (0 == strcmp(param3, "used"))
			{
				SET_UI64_RESULT(result, info.mem_used);
			}
			else if (0 == strcmp(param3, "pused"))
			{
				SET_DBL_RESULT(result, (double)info.mem_used / (double)info.mem_total * 100);
			}
			else
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
				return NOTSUPPORTED;
			}
		}
		else if (0 == strcmp(param2, "state"))
		{
			zbx_pb_state_info_t	info;

			zbx_pb_get_state_info(&info);

			if (NULL == param3 || '\0' == *param3 || 0 == strcmp(param3, "current"))
			{
				SET_UI64_RESULT(result, (zbx_uint64_t)info.state);
			}
			else if (0 == strcmp(param3, "changes"))
			{
				SET_UI64_RESULT(result, (zbx_uint64_t)info.changes_num);
			}
			else
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
				return NOTSUPPORTED;
			}
		}
		else
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
			return SUCCEED;
		}

	}
	else if (0 == strcmp(param1, "apm"))
	{
		const char	*param2;
		int		params_num;

		params_num = get_rparams_num(request);

		if (2 != params_num)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			return NOTSUPPORTED;
		}

		param2 = get_rparam(request, 1);
		if (0 == strcmp(param2, "export"))
		{
			return get_apm_export_stats(result);
		}
		else
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
			return NOTSUPPORTED;
		}
	}
	else
		return FAIL;

	return SUCCEED;
}
