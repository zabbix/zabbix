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

#ifndef ZABBIX_CACHEHISTORY_H
#define ZABBIX_CACHEHISTORY_H

#include "zbxtrends.h"
#include "zbxhistory.h"
#include "zbxcacheconfig.h"
#include "zbxshmem.h"
#include "zbxipcservice.h"

#define ZBX_HC_PROXYQUEUE_STATE_NORMAL 0
#define ZBX_HC_PROXYQUEUE_STATE_WAIT 1

/* the maximum time spent synchronizing history */
#define ZBX_HC_SYNC_TIME_MAX	10

/* the maximum number of items in one synchronization batch */
#define ZBX_HC_SYNC_MAX		1000
#define ZBX_HC_TIMER_MAX	(ZBX_HC_SYNC_MAX / 2)
#define ZBX_HC_TIMER_SOFT_MAX	(ZBX_HC_TIMER_MAX - 10)

#define ZBX_SYNC_DONE		0
#define	ZBX_SYNC_MORE		1

typedef struct
{
	zbx_uint64_t	history_counter;	/* the total number of processed values */
	zbx_uint64_t	history_float_counter;	/* the number of processed float values */
	zbx_uint64_t	history_uint_counter;	/* the number of processed uint values */
	zbx_uint64_t	history_str_counter;	/* the number of processed str values */
	zbx_uint64_t	history_log_counter;	/* the number of processed log values */
	zbx_uint64_t	history_text_counter;	/* the number of processed text values */
	zbx_uint64_t	history_bin_counter;	/* the number of processed bin values */
	zbx_uint64_t	notsupported_counter;	/* the number of processed not supported items */
}
zbx_dc_stats_t;

/* the write cache statistics */
typedef struct
{
	zbx_dc_stats_t	stats;
	zbx_uint64_t	history_free;
	zbx_uint64_t	history_total;
	zbx_uint64_t	index_free;
	zbx_uint64_t	index_total;
	zbx_uint64_t	trend_free;
	zbx_uint64_t	trend_total;
}
zbx_wcache_info_t;

#define ZBX_SYNC_NONE	0
#define ZBX_SYNC_ALL	1

#define ZBX_STATS_HISTORY_COUNTER	0
#define ZBX_STATS_HISTORY_FLOAT_COUNTER	1
#define ZBX_STATS_HISTORY_UINT_COUNTER	2
#define ZBX_STATS_HISTORY_STR_COUNTER	3
#define ZBX_STATS_HISTORY_LOG_COUNTER	4
#define ZBX_STATS_HISTORY_TEXT_COUNTER	5
#define ZBX_STATS_NOTSUPPORTED_COUNTER	6
#define ZBX_STATS_HISTORY_TOTAL		7
#define ZBX_STATS_HISTORY_USED		8
#define ZBX_STATS_HISTORY_FREE		9
#define ZBX_STATS_HISTORY_PUSED		10
#define ZBX_STATS_HISTORY_PFREE		11
#define ZBX_STATS_TREND_TOTAL		12
#define ZBX_STATS_TREND_USED		13
#define ZBX_STATS_TREND_FREE		14
#define ZBX_STATS_TREND_PUSED		15
#define ZBX_STATS_TREND_PFREE		16
#define ZBX_STATS_HISTORY_INDEX_TOTAL	17
#define ZBX_STATS_HISTORY_INDEX_USED	18
#define ZBX_STATS_HISTORY_INDEX_FREE	19
#define ZBX_STATS_HISTORY_INDEX_PUSED	20
#define ZBX_STATS_HISTORY_INDEX_PFREE	21
#define ZBX_STATS_HISTORY_BIN_COUNTER	22

/* 'zbx_pp_value_opt_t' element 'flags' values */
#define ZBX_PP_VALUE_OPT_NONE		0x0000	/* 'zbx_pp_value_opt_t' has no data */
#define ZBX_PP_VALUE_OPT_META		0x0001	/* 'zbx_pp_value_opt_t' has log metadata ('mtime' and 'lastlogsize') */
#define ZBX_PP_VALUE_OPT_LOG		0x0002	/* 'zbx_pp_value_opt_t' has 'timestamp', 'severity', 'logeventid' and */
						/* 'source' data */
#define ZBX_DC_FLAGS_NOT_FOR_HISTORY	(ZBX_DC_FLAG_NOVALUE | ZBX_DC_FLAG_UNDEF | ZBX_DC_FLAG_NOHISTORY)
#define ZBX_DC_FLAGS_NOT_FOR_TRENDS	(ZBX_DC_FLAG_NOVALUE | ZBX_DC_FLAG_UNDEF | ZBX_DC_FLAG_NOTRENDS)
#define ZBX_DC_FLAGS_NOT_FOR_MODULES	(ZBX_DC_FLAGS_NOT_FOR_HISTORY | ZBX_DC_FLAG_LLD)
#define ZBX_DC_FLAGS_NOT_FOR_EXPORT	(ZBX_DC_FLAG_NOVALUE | ZBX_DC_FLAG_UNDEF)

/* This structure is complementary data if value comes from preprocessing. */
typedef struct
{
	zbx_uint32_t	flags;
	int		mtime;
	int		timestamp;
	int		severity;
	int		logeventid;
	zbx_uint64_t	lastlogsize;
	char		*source;
}
zbx_pp_value_opt_t;

void	zbx_pp_value_opt_clear(zbx_pp_value_opt_t *opt);
void	zbx_dc_get_stats_all(zbx_wcache_info_t *wcache_info);
void	*zbx_dc_get_stats(int request);
void	zbx_trend_add_new_items(const zbx_vector_uint64_t *itemids);
void	zbx_dc_update_trends(zbx_vector_uint64_pair_t *trends_diff);
void	zbx_db_flush_trends(ZBX_DC_TREND *trends, int *trends_num, zbx_vector_uint64_pair_t *trends_diff);
void	zbx_dc_mass_update_trends(const zbx_dc_history_t *history, int history_num, ZBX_DC_TREND **trends,
		int *trends_num, int compression_age);
int	zbx_trend_compare(const void *d1, const void *d2);
void	zbx_dc_export_history_and_trends(const zbx_dc_history_t *history, int history_num,
		const zbx_vector_uint64_t *itemids, zbx_history_sync_item_t *items, const int *errcodes,
		const ZBX_DC_TREND *trends, int trends_num, int history_export_enabled,
		zbx_vector_connector_filter_t *connector_filters, unsigned char **data, size_t *data_alloc,
		size_t *data_offset);
void	zbx_dc_history_clean_value(zbx_dc_history_t *history);
void	zbx_hc_free_item_values(zbx_dc_history_t *history, int history_num);
void	zbx_db_mass_update_items(const zbx_vector_item_diff_ptr_t *item_diff,
		const zbx_vector_inventory_value_ptr_t *inventory_values);
void	zbx_log_sync_history_cache_progress(void);
void	zbx_sync_history_cache(const zbx_events_funcs_t *events_cbs, zbx_ipc_async_socket_t *rtc,
		int config_history_storage_pipelines, int *values_num, int *triggers_num, int *more);
void	zbx_dc_add_history(zbx_uint64_t itemid, unsigned char item_value_type, unsigned char item_flags,
		AGENT_RESULT *result, const zbx_timespec_t *ts, unsigned char state, const char *error);
void	zbx_dc_add_history_variant(zbx_uint64_t itemid, unsigned char value_type, unsigned char item_flags,
		zbx_variant_t *value, zbx_timespec_t ts, const zbx_pp_value_opt_t *value_opt);
size_t	zbx_dc_flush_history(void);
void	zbx_hc_pop_items(zbx_vector_hc_item_ptr_t *history_items);
void	zbx_hc_get_item_values(zbx_dc_history_t *history, zbx_vector_hc_item_ptr_t *history_items);
void	zbx_hc_push_items(zbx_vector_hc_item_ptr_t *history_items);
int	zbx_hc_queue_get_size(void);
int	zbx_hc_get_history_compression_age(void);
double	zbx_hc_mem_pused(void);
double	zbx_hc_mem_pused_lock(void);

typedef void (*zbx_history_sync_f)(int *values_num, int *triggers_num, const zbx_events_funcs_t *events_cbs,
		zbx_ipc_async_socket_t *rtc, int config_history_storage_pipelines, int *more);

int	zbx_init_database_cache(zbx_get_program_type_f get_program_type, zbx_history_sync_f sync_history,
		zbx_uint64_t history_cache_size, zbx_uint64_t history_index_cache_size, zbx_uint64_t *trends_cache_size,
		char **error);

void	zbx_free_database_cache(int sync, const zbx_events_funcs_t *events_cbs, int config_history_storage_pipelines);

zbx_uint64_t	zbx_dc_get_nextid(const char *table_name, int num);

void	zbx_dc_update_interfaces_availability(void);
void	zbx_hc_get_diag_stats(zbx_uint64_t *items_num, zbx_uint64_t *values_num);
void	zbx_hc_get_mem_stats(zbx_shmem_stats_t *data, zbx_shmem_stats_t *index);
void	zbx_hc_get_items(zbx_vector_uint64_pair_t *items);
int	zbx_db_trigger_queue_locked(void);
void	zbx_db_trigger_queue_unlock(void);
zbx_uint64_t	zbx_hc_proxyqueue_peek(void);
void	zbx_hc_proxyqueue_enqueue(zbx_uint64_t proxyid);
int	zbx_hc_proxyqueue_dequeue(zbx_uint64_t proxyid);
void	zbx_hc_proxyqueue_clear(void);
void	zbx_dbcache_lock(void);
void	zbx_dbcache_unlock(void);
void	zbx_dbcache_set_history_num(int num);
int	zbx_dbcache_get_history_num(void);

zbx_shmem_info_t	*zbx_dbcache_get_hc_mem(void);

void	zbx_dbcache_setproxyqueue_state(int proxyqueue_state);
int	zbx_dbcache_getproxyqueue_state(void);
#endif
