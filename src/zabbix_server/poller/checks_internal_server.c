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

#include "poller_server.h"

#include "../ha/ha.h"
#include "../lld/lld_protocol.h"

#include "zbxcachevalue.h"
#include "zbxcacheconfig.h"
#include "zbxconnector.h"
#include "zbxproxybuffer.h"
#include "zbxpgservice.h"
#include "zbxalgo.h"
#include "zbx_host_constants.h"

static int	get_proxy_group_stat(const zbx_pg_stats_t *stats, const char *option, AGENT_RESULT *result)
{
	if (0 == strcmp(option, "state"))
	{
		SET_UI64_RESULT(result, stats->status);
		return SUCCEED;
	}

	if (0 == strcmp(option, "available"))
	{
		SET_UI64_RESULT(result, stats->proxy_online_num);
		return SUCCEED;
	}

	if (0 == strcmp(option, "pavailable"))
	{
		double	perc;

		if (0 != stats->proxyids.values_num)
			perc = (double)stats->proxy_online_num / stats->proxyids.values_num * 100;
		else
			perc = 0;

		SET_DBL_RESULT(result, perc);
		return SUCCEED;
	}

	if (0 == strcmp(option, "proxies"))
	{
		char	*out = NULL, *error = NULL;

		if (FAIL == zbx_proxy_proxy_list_discovery_get(&stats->proxyids, &out, &error))
		{
			SET_MSG_RESULT(result, error);
			return NOTSUPPORTED;
		}

		SET_TEXT_RESULT(result, out);
		return SUCCEED;
	}

	SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid third parameter."));

	return NOTSUPPORTED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes program type (server) specific internal checks          *
 *                                                                            *
 * Parameters: item    - [IN] item to process                                 *
 *             param1  - [IN] first parameter                                 *
 *             request - [IN]                                                 *
 *             result  - [OUT]                                                *
 *                                                                            *
 * Return value: SUCCEED - data successfully retrieved and stored in result   *
 *               NOTSUPPORTED - requested item is not supported               *
 *               FAIL - not server specific internal check                    *
 *                                                                            *
 * Comments: This function is used to process server specific internal checks *
 *           before generic internal checks are processed.                    *
 *                                                                            *
 ******************************************************************************/
int	zbx_get_value_internal_ext_server(const zbx_dc_item_t *item, const char *param1, const AGENT_REQUEST *request,
		AGENT_RESULT *result)
{
	int		nparams, ret = NOTSUPPORTED;
	const char	*param2, *param3;

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
			char	*data;

			param2 = get_rparam(request, 1);

			if (0 != strcmp(param2, "discovery"))
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
				goto out;
			}

			zbx_proxy_discovery_get(&data);
			SET_STR_RESULT(result, data);
		}
		else
		{
			time_t	value;

			param3 = get_rparam(request, 2);

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
						SUCCEED == (res = zbx_dc_get_proxy_lastaccess_by_name(param2,
						&lastaccess, &error)))
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
	/* zabbix["proxy group","discovery"]                                                      */
	/* zabbix["proxy group",<groupname>,"state" OR "available" OR "pavailable" OR "proxies" ] */
	else if (0 == strcmp(param1, "proxy group"))
	{
		char		*error = NULL;
		zbx_pg_stats_t	stats;
		char		*data;

		/* this item is always processed by server */

		if (2 != nparams && 3 != nparams)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			goto out;
		}

		param2 = get_rparam(request, 1);

		if (3 == nparams)
		{
			if (FAIL == zbx_pg_get_stats(param2, &stats, &error))
			{
				SET_MSG_RESULT(result, zbx_dsprintf(NULL, "Cannot obtain proxy group statistics: %s",
						error));
				zbx_free(error);
				goto out;
			}

			param3 = get_rparam(request, 2);

			ret = get_proxy_group_stat(&stats, param3, result);
			zbx_vector_uint64_destroy(&stats.proxyids);

			goto out;
		}

		if (0 != strcmp(param2, "discovery"))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
			goto out;
		}

		zbx_proxy_group_discovery_get(&data);
		SET_STR_RESULT(result, data);
	}
	else if (0 == strcmp(param1, "host")) /* zabbix["host",*] */
	{
		if (3 != nparams)
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid number of parameters."));
			goto out;
		}

		param3 = get_rparam(request, 2);

		if (0 == strcmp(param3, "maintenance"))	/* zabbix["host",,"maintenance"] */
		{
			/* this item is always processed by server */
			if (NULL != (param2 = get_rparam(request, 1)) && '\0' != *param2)
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
				goto out;
			}

			if (HOST_MAINTENANCE_STATUS_ON == item->host.maintenance_status)
				SET_UI64_RESULT(result, item->host.maintenance_type + 1);
			else
				SET_UI64_RESULT(result, 0);
		}
		else if (0 == strcmp(param3, "items"))	/* zabbix["host",,"items"] */
		{
			/* this item is always processed by server */
			if (NULL != (param2 = get_rparam(request, 1)) && '\0' != *param2)
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
				goto out;
			}

			SET_UI64_RESULT(result, zbx_dc_get_item_count(item->host.hostid));
		}
		else if (0 == strcmp(param3, "items_unsupported"))	/* zabbix["host",,"items_unsupported"] */
		{
			/* this item is always processed by server */
			if (NULL != (param2 = get_rparam(request, 1)) && '\0' != *param2)
			{
				SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
				goto out;
			}

			SET_UI64_RESULT(result, zbx_dc_get_item_unsupported_count(item->host.hostid));
		}
		else
		{
			ret = FAIL;
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
