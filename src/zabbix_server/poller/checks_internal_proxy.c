/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#include "zbxcommon.h"
#include "zbxcachehistory.h"
#include "checks_internal.h"
#include "zbxproxydatacache.h"

/******************************************************************************
 *                                                                            *
 * Purpose: processes program type (proxy) specific internal checks           *
 *                                                                            *
 * Parameters: param1  - [IN] the first parameter                             *
 *             request - [IN] the request                                     *
 *             result  - [OUT] the result                                     *
 *                                                                            *
 * Return value: SUCCEED - data successfully retrieved and stored in result   *
 *               NOTSUPPORTED - requested item is not supported               *
 *               FAIL - not a proxy specific internal check                   *
 *                                                                            *
 * Comments: This function is used to process proxy specific internal checks  *
 *           before generic internal checks are processed.                    *
 *                                                                            *
 ******************************************************************************/
int	zbx_get_value_internal_ext(const char *param1, const AGENT_REQUEST *request, AGENT_RESULT *result)
{
	if (0 == strcmp(param1, "proxy_history"))
	{
		if (1 != get_rparams_num(request))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			return NOTSUPPORTED;
		}

		SET_UI64_RESULT(result, zbx_get_proxy_history_count());
	}
	if (0 == strcmp(param1, "data_cache"))
	{
		const char	*param2, *param3;
		int		params_num;
		zbx_pdc_stats_t	stats;
		char		*error = NULL;

		params_num = get_rparams_num(request);

		if (params_num < 2 || params_num > 3)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			return NOTSUPPORTED;
		}

		param2 = get_rparam(request, 1);
		param3 = get_rparam(request, 2);

		if (SUCCEED != zbx_pdc_get_stats(&stats, &error))
		{
			SET_MSG_RESULT(result, error);
			return NOTSUPPORTED;
		}

		if (0 == strcmp(param2, "buffer"))
		{
			if (NULL == param3 || '\0' == *param3 || 0 == strcmp(param3, "pfree"))
			{
				SET_DBL_RESULT(result, (double)(stats.mem_total - stats.mem_used) /
						(double)stats.mem_total * 100);
			}
			else if (0 == strcmp(param3, "free"))
			{
				SET_UI64_RESULT(result, stats.mem_total - stats.mem_used);
			}
			else if (0 == strcmp(param3, "total"))
			{
				SET_UI64_RESULT(result, stats.mem_total);
			}
			else if (0 == strcmp(param3, "used"))
			{
				SET_UI64_RESULT(result, stats.mem_used);
			}
			else if (0 == strcmp(param3, "pused"))
			{
				SET_DBL_RESULT(result, (double)stats.mem_used / (double)stats.mem_total * 100);
			}
			else
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
				return NOTSUPPORTED;
			}
		}
		else if (0 == strcmp(param2, "state"))
		{
			if (NULL == param3 || '\0' == *param3 || 0 == strcmp(param3, "current"))
			{
				SET_UI64_RESULT(result, (zbx_uint64_t)stats.state);
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
	else
		return FAIL;

	return SUCCEED;
}
