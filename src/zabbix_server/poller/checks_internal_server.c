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
#include "zbxcachevalue.h"
#include "zbxcacheconfig.h"
#include "zbxjson.h"
#include "zbxtime.h"
#include "zbxconnector.h"
#include "../ha/ha.h"
#include "zbxproxybuffer.h"

#include "checks_internal.h"
#include "../lld/lld_protocol.h"

/******************************************************************************
 *                                                                            *
 * Purpose: processes program type (server) specific internal checks          *
 *                                                                            *
 * Parameters: param1  - [IN] the first parameter                             *
 *             request - [IN] the request                                     *
 *             result  - [OUT] the result                                     *
 *                                                                            *
 * Return value: SUCCEED - data successfully retrieved and stored in result   *
 *               NOTSUPPORTED - requested item is not supported               *
 *               FAIL - not a server specific internal check                  *
 *                                                                            *
 * Comments: This function is used to process server specific internal checks *
 *           before generic internal checks are processed.                    *
 *                                                                            *
 ******************************************************************************/
int	zbx_get_value_internal_ext(const char *param1, const AGENT_REQUEST *request, AGENT_RESULT *result)
{
	int		nparams, ret = NOTSUPPORTED;
	const char	*param2;

	nparams = get_rparams_num(request);

	if (0 == strcmp(param1, "triggers"))			/* zabbix["triggers"] */
	{
		if (1 != nparams)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			goto out;
		}

		SET_UI64_RESULT(result, zbx_dc_get_trigger_count());
	}
	else if (0 == strcmp(param1, "proxy"))			/* zabbix["proxy",<hostname>,"lastaccess" OR "delay"] */
	{							/* zabbix["proxy","discovery"]                        */
		int	res;
		char	*error = NULL;

		/* this item is always processed by server */

		if (2 > nparams || 3 < nparams)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			goto out;
		}

		if (2 == nparams)
		{
			param2 = get_rparam(request, 1);

			if (0 == strcmp(param2, "discovery"))
			{
				char	*data;

				if (SUCCEED == (res = zbx_proxy_discovery_get(&data, &error)))
					SET_STR_RESULT(result, data);
				else
					SET_MSG_RESULT(result, error);

				if (SUCCEED != res)
					goto out;
			}
			else
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
				goto out;
			}
		}
		else
		{
			time_t		value;
			const char	*param3 = get_rparam(request, 2);

			if (0 == strcmp(param3, "lastaccess"))
			{
				res = zbx_dc_get_proxy_lastaccess_by_name(get_rparam(request, 1), &value, &error);
			}
			else if (0 == strcmp(param3, "delay"))
			{
				time_t	lastaccess;
				int	tmp;

				param2 = get_rparam(request, 1);

				if (SUCCEED == (res = zbx_dc_get_proxy_delay_by_name(param2, &tmp, &error)) &&
						SUCCEED == (res = zbx_dc_get_proxy_lastaccess_by_name(param2, &lastaccess,
						&error)))
				{
					value = tmp + time(NULL) - lastaccess;
				}
			}
			else
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
				goto out;
			}

			if (SUCCEED != res)
			{
				SET_MSG_RESULT(result, error);
				goto out;
			}

			SET_UI64_RESULT(result, value);
		}
	}
	else if (0 == strcmp(param1, "vcache"))
	{
		const char	*param3;
		zbx_vc_stats_t	stats;

		if (FAIL == zbx_vc_get_statistics(&stats))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Value cache is disabled."));
			goto out;
		}

		if (2 > nparams || nparams > 3)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			goto out;
		}

		param2 = get_rparam(request, 1);

		if (NULL == (param3 = get_rparam(request, 2)))
			param3 = "";

		if (0 == strcmp(param2, "buffer"))
		{
			if (0 == strcmp(param3, "free"))
				SET_UI64_RESULT(result, stats.free_size);
			else if (0 == strcmp(param3, "pfree"))
				SET_DBL_RESULT(result, (double)stats.free_size / stats.total_size * 100);
			else if (0 == strcmp(param3, "total"))
				SET_UI64_RESULT(result, stats.total_size);
			else if (0 == strcmp(param3, "used"))
				SET_UI64_RESULT(result, stats.total_size - stats.free_size);
			else if (0 == strcmp(param3, "pused"))
				SET_DBL_RESULT(result, (double)(stats.total_size - stats.free_size) /
						stats.total_size * 100);
			else
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
				goto out;
			}
		}
		else if (0 == strcmp(param2, "cache"))
		{
			if (0 == strcmp(param3, "hits"))
				SET_UI64_RESULT(result, stats.hits);
			else if (0 == strcmp(param3, "requests"))
				SET_UI64_RESULT(result, stats.hits + stats.misses);
			else if (0 == strcmp(param3, "misses"))
				SET_UI64_RESULT(result, stats.misses);
			else if (0 == strcmp(param3, "mode"))
				SET_UI64_RESULT(result, stats.mode);
			else
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
				goto out;
			}
		}
		else
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
			goto out;
		}
	}
	else if (0 == strcmp(param1, "lld_queue"))
	{
		zbx_uint64_t	value;
		char		*error = NULL;

		if (1 != nparams)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			goto out;
		}

		if (FAIL == zbx_lld_get_queue_size(&value, &error))
		{
			SET_MSG_RESULT(result, error);
			goto out;
		}

		SET_UI64_RESULT(result, value);
	}
	else if (0 == strcmp(param1, "connector_queue"))
	{
		zbx_uint64_t	value;
		char		*error = NULL;

		if (1 != nparams)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			goto out;
		}

		if (FAIL == zbx_connector_get_queue_size(&value, &error))
		{
			SET_MSG_RESULT(result, error);
			goto out;
		}

		SET_UI64_RESULT(result, value);
	}
	else if (0 == strcmp(param1, "cluster"))
	{
		char	*nodes = NULL, *error = NULL;

		if (3 != nparams)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			goto out;
		}

		param2 = get_rparam(request, 1);
		if (0 != strcmp(param2, "discovery"))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
			goto out;
		}

		param2 = get_rparam(request, 2);
		if (0 != strcmp(param2, "nodes"))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
			goto out;
		}

		if (SUCCEED != zbx_ha_get_nodes(&nodes, &error))
		{
			SET_MSG_RESULT(result, error);
			goto out;
		}

		SET_TEXT_RESULT(result, nodes);
	}
	else if (0 == strcmp(param1, "vps"))
	{
		zbx_vps_monitor_stats_t	stats;
		const char		*param3;

		zbx_vps_monitor_get_stats(&stats);

		param2 = get_rparam(request, 1);

		if (2 == nparams)
		{
			if (0 == strcmp(param2, "status"))
			{
				zbx_uint64_t	value = (SUCCEED == zbx_vps_monitor_capped() ? 1 : 0);
				SET_UI64_RESULT(result, value);
				ret = SUCCEED;

				goto out;
			}
			else if (0 == strcmp(param2, "limit"))
			{
				SET_UI64_RESULT(result, stats.values_limit);
				ret = SUCCEED;

				goto out;
			}
		}

		if (3 < nparams)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			goto out;
		}

		param3 = get_rparam(request, 2);

		if (0 == strcmp(param2, "written"))
		{
			if (NULL == param3 || '\0' == *param3 || 0 == strcmp(param3, "total"))
			{
				SET_UI64_RESULT(result, stats.written_num);
				ret = SUCCEED;
			}
			else
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));

			goto out;
		}
		else if (0 != strcmp(param2, "overcommit"))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
			goto out;
		}

		if (0 == stats.values_limit)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "VPS throttling is disabled."));
			goto out;
		}

		if (NULL == param3 || '\0' == *param3 || 0 == strcmp(param3, "pavailable"))
		{
			SET_DBL_RESULT(result, (double)(stats.overcommit_limit - stats.overcommit) * 100 /
					stats.overcommit_limit);
		}
		else if (0 == strcmp(param3, "available"))
		{
			SET_UI64_RESULT(result, stats.overcommit_limit - stats.overcommit);
		}
		else if (0 == strcmp(param3, "limit"))
		{
			SET_UI64_RESULT(result, stats.overcommit_limit);
		}
		else
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));
			goto out;
		}
	}
	else
	{
		ret = FAIL;
		goto out;
	}

	ret = SUCCEED;

out:
	return ret;
}

int	zbx_pb_get_mem_info(zbx_pb_mem_info_t *info, char **error)
{
	ZBX_UNUSED(error);

	memset(info, 0, sizeof(zbx_pb_mem_info_t));

	return SUCCEED;
}

void	zbx_pb_get_state_info(zbx_pb_state_info_t *info)
{
	memset(info, 0, sizeof(zbx_pb_state_info_t));
}

