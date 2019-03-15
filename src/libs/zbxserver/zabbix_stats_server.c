/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

#include "common.h"
#include "zbxjson.h"
#include "dbcache.h"
#include "valuecache.h"
#include "preproc.h"
#include "zbxlld.h"
#include "log.h"

#include "zabbix_stats.h"

/******************************************************************************
 *                                                                            *
 * Function: zbx_get_zabbix_stats_ext                                         *
 *                                                                            *
 * Purpose: get program type (server) specific internal statistics            *
 *                                                                            *
 * Parameters: param1  - [IN/OUT] the json data                               *
 *                                                                            *
 * Comments: This function is used to gather server specific internal         *
 *           statistics.                                                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_get_zabbix_stats_ext(struct zbx_json *json)
{
	zbx_vc_stats_t	vc_stats;
	zbx_uint64_t	queue_size;
	char		*error = NULL;

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

	/* zabbix[triggers] */
	zbx_json_adduint64(json, "triggers", DCget_trigger_count());

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
		zbx_json_adduint64(json, "mode", vc_stats.mode);
		zbx_json_close(json);

		zbx_json_close(json);
	}
}
