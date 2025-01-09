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

#include "zbxstats.h"

#include "zbxalgo.h"
#include "zbxcacheconfig.h"
#include "zbxcachehistory.h"
#include "zbxjson.h"
#include "zbxself.h"
#include "zbxproxybuffer.h"

static zbx_get_program_type_f			get_program_type_cb;
static zbx_vector_stats_ext_func_t		stats_ext_funcs;
static zbx_vector_stats_ext_func_t		stats_data_funcs;
static zbx_zabbix_stats_procinfo_func_t		procinfo_funcs[ZBX_PROCESS_TYPE_COUNT];

ZBX_PTR_VECTOR_IMPL(stats_ext_func, zbx_stats_ext_func_entry_t *)

void	zbx_init_library_stats(zbx_get_program_type_f get_program_type)
{
	get_program_type_cb = get_program_type;

	zbx_vector_stats_ext_func_create(&stats_data_funcs);
	zbx_vector_stats_ext_func_create(&stats_ext_funcs);
}

/******************************************************************************
 *                                                                            *
 * Purpose: register callback to add information to main element              *
 *                                                                            *
 * Parameters: stats_ext_get_cb - [IN] statistics extension callback          *
 *             arg              - [IN] argument passed to callback            *
 *                                                                            *
 ******************************************************************************/
void	zbx_register_stats_ext_func(zbx_zabbix_stats_ext_get_func_t stats_ext_get_cb, const void *arg)
{
	zbx_stats_ext_func_entry_t	*entry;

	entry = (zbx_stats_ext_func_entry_t *)zbx_malloc(NULL, sizeof(zbx_stats_ext_func_entry_t));
	entry->arg = arg;
	entry->stats_ext_get_cb = stats_ext_get_cb;

	zbx_vector_stats_ext_func_append(&stats_ext_funcs, entry);
}

/******************************************************************************
 *                                                                            *
 * Purpose: register callback to add information to data sub-element          *
 *                                                                            *
 * Parameters: stats_ext_get_cb - [IN] statistics extension callback          *
 *             arg              - [IN] argument passed to callback            *
 *                                                                            *
 ******************************************************************************/
void	zbx_register_stats_data_func(zbx_zabbix_stats_ext_get_func_t stats_ext_get_cb, const void *arg)
{
	zbx_stats_ext_func_entry_t	*entry;

	entry = (zbx_stats_ext_func_entry_t *)zbx_malloc(NULL, sizeof(zbx_stats_ext_func_entry_t));
	entry->arg = arg;
	entry->stats_ext_get_cb = stats_ext_get_cb;

	zbx_vector_stats_ext_func_append(&stats_data_funcs, entry);
}

/******************************************************************************
 *                                                                            *
 * Purpose: register process information callback for the specified process   *
 *          type                                                              *
 *                                                                            *
 * Parameters: proc_type   - [IN] the process type                            *
 *             procinfo_cb - [IN] the process information callback            *
 *                                                                            *
 ******************************************************************************/
void	zbx_register_stats_procinfo_func(int proc_type, zbx_zabbix_stats_procinfo_func_t procinfo_cb)
{
	if (0 <= proc_type && proc_type < ZBX_PROCESS_TYPE_COUNT)
	{
		procinfo_funcs[proc_type] = procinfo_cb;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: add process information to json                                   *
 *                                                                            *
 ******************************************************************************/
static void	stats_add_procinfo(struct zbx_json *json, int proc_type, zbx_process_info_t *info)
{
	if (0 == info->count)
		return;

	zbx_json_addobject(json, get_process_type_string((unsigned char)proc_type));
	zbx_json_addobject(json, "busy");
	zbx_json_addfloat(json, "avg", info->busy_avg);
	zbx_json_addfloat(json, "max", info->busy_max);
	zbx_json_addfloat(json, "min", info->busy_min);
	zbx_json_close(json);
	zbx_json_addobject(json, "idle");
	zbx_json_addfloat(json, "avg", info->idle_avg);
	zbx_json_addfloat(json, "max", info->idle_max);
	zbx_json_addfloat(json, "min", info->idle_min);
	zbx_json_close(json);
	zbx_json_addint64(json, "count", info->count);
	zbx_json_close(json);
}

/******************************************************************************
 *                                                                            *
 * Purpose: collects all metrics required for Zabbix stats request            *
 *                                                                            *
 * Parameters: json                - [OUT] resulting json structure           *
 *             config_startup_time - [IN] program startup time                *
 *                                                                            *
 ******************************************************************************/
void	zbx_zabbix_stats_get(struct zbx_json *json, int config_startup_time)
{
	int			i;
	zbx_config_cache_info_t	count_stats;
	zbx_wcache_info_t	wcache_info;
	zbx_process_info_t	process_stats[ZBX_PROCESS_TYPE_COUNT];
	int			proc_type;

	zbx_dc_get_count_stats_all(&count_stats);

	/* zabbix[boottime] */
	zbx_json_addint64(json, "boottime", config_startup_time);

	/* zabbix[uptime] */
	zbx_json_addint64(json, "uptime", time(NULL) - config_startup_time);

	/* zabbix[hosts] */
	zbx_json_adduint64(json, "hosts", count_stats.hosts);

	/* zabbix[items] */
	zbx_json_adduint64(json, "items", count_stats.items);

	/* zabbix[items_unsupported] */
	zbx_json_adduint64(json, "items_unsupported", count_stats.items_unsupported);

	/* zabbix[requiredperformance] */
	zbx_json_addfloat(json, "requiredperformance", count_stats.requiredperformance);

	for (i = 0; i < stats_data_funcs.values_num; i++)
	{
		stats_data_funcs.values[i]->stats_ext_get_cb(json, stats_data_funcs.values[i]->arg);
	}

	/* zabbix[rcache,<cache>,<mode>] */
	zbx_json_addobject(json, "rcache");
	zbx_json_adduint64(json, "total", *(zbx_uint64_t *)zbx_dc_config_get_stats(ZBX_CONFSTATS_BUFFER_TOTAL));
	zbx_json_adduint64(json, "free", *(zbx_uint64_t *)zbx_dc_config_get_stats(ZBX_CONFSTATS_BUFFER_FREE));
	zbx_json_addfloat(json, "pfree", *(double *)zbx_dc_config_get_stats(ZBX_CONFSTATS_BUFFER_PFREE));
	zbx_json_adduint64(json, "used", *(zbx_uint64_t *)zbx_dc_config_get_stats(ZBX_CONFSTATS_BUFFER_USED));
	zbx_json_addfloat(json, "pused", *(double *)zbx_dc_config_get_stats(ZBX_CONFSTATS_BUFFER_PUSED));
	zbx_json_close(json);

	/* zabbix[version] */
	zbx_json_addstring(json, "version", ZABBIX_VERSION, ZBX_JSON_TYPE_STRING);

	/* zabbix[wcache,<cache>,<mode>] */
	zbx_dc_get_stats_all(&wcache_info);
	zbx_json_addobject(json, "wcache");

	zbx_json_addobject(json, "values");
	zbx_json_adduint64(json, "all", wcache_info.stats.history_counter);
	zbx_json_adduint64(json, "float", wcache_info.stats.history_float_counter);
	zbx_json_adduint64(json, "uint", wcache_info.stats.history_uint_counter);
	zbx_json_adduint64(json, "str", wcache_info.stats.history_str_counter);
	zbx_json_adduint64(json, "log", wcache_info.stats.history_log_counter);
	zbx_json_adduint64(json, "text", wcache_info.stats.history_text_counter);
	zbx_json_adduint64(json, "bin", wcache_info.stats.history_bin_counter);
	zbx_json_adduint64(json, "not supported", wcache_info.stats.notsupported_counter);
	zbx_json_close(json);

	zbx_json_addobject(json, "history");
	zbx_json_addfloat(json, "pfree", 100 * (double)wcache_info.history_free / (double)wcache_info.history_total);
	zbx_json_adduint64(json, "free", wcache_info.history_free);
	zbx_json_adduint64(json, "total", wcache_info.history_total);
	zbx_json_adduint64(json, "used", wcache_info.history_total - wcache_info.history_free);
	zbx_json_addfloat(json, "pused", 100 * (double)(wcache_info.history_total - wcache_info.history_free) /
			(double)wcache_info.history_total);
	zbx_json_close(json);

	zbx_json_addobject(json, "index");
	zbx_json_addfloat(json, "pfree", 100 * (double)wcache_info.index_free / (double)wcache_info.index_total);
	zbx_json_adduint64(json, "free", wcache_info.index_free);
	zbx_json_adduint64(json, "total", wcache_info.index_total);
	zbx_json_adduint64(json, "used", wcache_info.index_total - wcache_info.index_free);
	zbx_json_addfloat(json, "pused", 100 * (double)(wcache_info.index_total - wcache_info.index_free) /
			(double)wcache_info.index_total);
	zbx_json_close(json);

	if (0 != (get_program_type_cb() & ZBX_PROGRAM_TYPE_SERVER))
	{
		zbx_json_addobject(json, "trend");
		zbx_json_addfloat(json, "pfree", 100 * (double)wcache_info.trend_free / (double)wcache_info.trend_total);
		zbx_json_adduint64(json, "free", wcache_info.trend_free);
		zbx_json_adduint64(json, "total", wcache_info.trend_total);
		zbx_json_adduint64(json, "used", wcache_info.trend_total - wcache_info.trend_free);
		zbx_json_addfloat(json, "pused", 100 * (double)(wcache_info.trend_total - wcache_info.trend_free) /
				(double)wcache_info.trend_total);
		zbx_json_close(json);
	}

	if (0 != (get_program_type_cb() & ZBX_PROGRAM_TYPE_PROXY))
	{
		zbx_pb_mem_info_t	mem;
		zbx_pb_state_info_t	state;
		char			*error = NULL;

		if (SUCCEED == zbx_pb_get_mem_info(&mem, &error))
		{
			zbx_json_addobject(json, "proxy buffer");

			zbx_json_addobject(json, "memory");
			zbx_json_addfloat(json, "pfree", 100 * (double)(mem.mem_total - mem.mem_used) /
					(double)mem.mem_total);
			zbx_json_adduint64(json, "free", mem.mem_total - mem.mem_used);
			zbx_json_adduint64(json, "total", mem.mem_total);
			zbx_json_adduint64(json, "used", mem.mem_used);
			zbx_json_addfloat(json, "pused", 100 * (double)mem.mem_used / (double)mem.mem_total);
			zbx_json_close(json);

			zbx_pb_get_state_info(&state);
			zbx_json_addint64(json, "state", state.state);
			zbx_json_adduint64(json, "state change", state.changes_num);

			zbx_json_close(json);
		}
		else
			zbx_free(error);
	}

	zbx_json_close(json);

	for (i = 0; i < stats_ext_funcs.values_num; i++)
	{
		stats_ext_funcs.values[i]->stats_ext_get_cb(json, stats_ext_funcs.values[i]->arg);
	}

	/* zabbix[process,<type>,<mode>,<state>] */
	zbx_json_addobject(json, "process");

	if (SUCCEED == zbx_get_all_process_stats(process_stats))
	{
		for (proc_type = 0; proc_type < ZBX_PROCESS_TYPE_COUNT; proc_type++)
		{
			if (NULL != procinfo_funcs[proc_type])
			{
				zbx_process_info_t	info;

				procinfo_funcs[proc_type](&info);
				stats_add_procinfo(json, proc_type, &info);
			}
			else
				stats_add_procinfo(json, proc_type, &process_stats[proc_type]);
		}
	}

	zbx_json_close(json);

	zbx_json_close(json);
}
