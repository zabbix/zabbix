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

#include "stats_server.h"

#include "../lld/lld_protocol.h"
#include "../ha/ha.h"

#include "zbxcacheconfig.h"
#include "zbxcachevalue.h"
#include "zbxtrends.h"
#include "zbxconnector.h"
#include "zbxjson.h"

/******************************************************************************
 *                                                                            *
 * Purpose: gets program type (server) specific internal statistics           *
 *                                                                            *
 * Parameters: json         - [IN/OUT]                                        *
 *             arg          - [IN] anonymous argument provided by register    *
 *                                                                            *
 * Comments: This function is used to gather server specific internal         *
 *           statistics.                                                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_server_stats_ext_get(struct zbx_json *json, const void *arg)
{
	zbx_vc_stats_t			vc_stats;
	zbx_uint64_t			queue_size, connector_queue_size;
	char				*value, *error = NULL;
	zbx_tfc_stats_t			tcache_stats;

	ZBX_UNUSED(arg);

	/* zabbix[lld_queue] */
	if (SUCCEED == zbx_lld_get_queue_size(&queue_size, &error))
	{
		zbx_json_adduint64(json, "lld_queue", queue_size);
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot get LLD queue size: %s", error);
		zbx_free(error);
	}

	/* zabbix[connector_queue] */
	if (SUCCEED == zbx_connector_get_queue_size(&connector_queue_size, &error))
	{
		zbx_json_adduint64(json, "connector_queue", queue_size);
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot get connector queue size: %s", error);
		zbx_free(error);
	}

	/* zabbix[triggers] */
	zbx_json_adduint64(json, "triggers", zbx_dc_get_trigger_count());

	/* zabbix[vcache,...] */
	if (SUCCEED == zbx_vc_get_statistics(&vc_stats))
	{
		zbx_json_addobject(json, "vcache");

		zbx_json_addobject(json, "buffer");
		zbx_json_adduint64(json, "total", vc_stats.total_size);
		zbx_json_adduint64(json, "free", vc_stats.free_size);
		zbx_json_addfloat(json, "pfree", (double)vc_stats.free_size / vc_stats.total_size * 100);
		zbx_json_adduint64(json, "used", vc_stats.total_size - vc_stats.free_size);
		zbx_json_addfloat(json, "pused", (double)(vc_stats.total_size - vc_stats.free_size) /
				vc_stats.total_size * 100);
		zbx_json_close(json);

		zbx_json_addobject(json, "cache");
		zbx_json_adduint64(json, "requests", vc_stats.hits + vc_stats.misses);
		zbx_json_adduint64(json, "hits", vc_stats.hits);
		zbx_json_adduint64(json, "misses", vc_stats.misses);
		zbx_json_addint64(json, "mode", vc_stats.mode);
		zbx_json_close(json);

		zbx_json_close(json);
	}

	/* zabbix[tcache,cache,<parameters>] */
	if (SUCCEED == zbx_tfc_get_stats(&tcache_stats, NULL))
	{
		zbx_uint64_t	total;

		zbx_json_addobject(json, "tcache");

		total = tcache_stats.hits + tcache_stats.misses;
		zbx_json_adduint64(json, "hits", tcache_stats.hits);
		zbx_json_adduint64(json, "misses", tcache_stats.misses);
		zbx_json_adduint64(json, "all", total);
		zbx_json_addfloat(json, "phits", (0 == total ? 0 : (double)tcache_stats.hits / total * 100));
		zbx_json_addfloat(json, "pmisses", (0 == total ? 0 : (double)tcache_stats.misses / total * 100));

		total = tcache_stats.items_num + tcache_stats.requests_num;
		zbx_json_adduint64(json, "items", tcache_stats.items_num);
		zbx_json_adduint64(json, "requests", tcache_stats.requests_num);
		zbx_json_addfloat(json, "pitems", (0 == total ? 0 : (double)tcache_stats.items_num / total * 100));

		zbx_json_close(json);
	}

	if (SUCCEED == zbx_ha_get_nodes(&value, &error))
	{
		zbx_json_addraw(json, "ha", value);
		zbx_free(value);
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot get HA node data: %s", error);
		zbx_free(error);
	}

	zbx_proxy_discovery_get(&value);
	zbx_json_addraw(json, "proxy", value);
	zbx_free(value);

	zbx_vps_monitor_stats_t	vps_stats;

	zbx_vps_monitor_get_stats(&vps_stats);

	zbx_json_addobject(json, "vps");
	zbx_json_adduint64(json, "status", (SUCCEED == zbx_vps_monitor_capped() ? 1 : 0));
	zbx_json_adduint64(json, "written_total", vps_stats.written_num);
	zbx_json_adduint64(json, "limit", vps_stats.values_limit);

	if (0 != vps_stats.values_limit)
	{
		zbx_json_addobject(json, "overcommit");
		zbx_json_adduint64(json, "limit", vps_stats.overcommit_limit);
		zbx_json_adduint64(json, "available", vps_stats.overcommit_limit - vps_stats.overcommit);
		zbx_json_addfloat(json, "pavailable", (double)(vps_stats.overcommit_limit - vps_stats.overcommit) *
				100 / vps_stats.overcommit_limit);
		zbx_json_close(json);
	}

	zbx_json_close(json);
}
