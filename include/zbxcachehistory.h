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

#ifndef ZABBIX_CACHEHISTORY_H
#define ZABBIX_CACHEHISTORY_H

#include "zbxcacheconfig.h"
#include "zbxshmem.h"

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

void	zbx_sync_history_cache(const zbx_events_funcs_t *events_cbs, int *values_num, int *triggers_num, int *more);
void	zbx_log_sync_history_cache_progress(void);

#define ZBX_SYNC_NONE	0
#define ZBX_SYNC_ALL	1

typedef void (*zbx_history_sync_f)(int *values_num, int *triggers_num, const zbx_events_funcs_t *events_cbs, int *more);

int	zbx_init_database_cache(zbx_get_program_type_f get_program_type, zbx_history_sync_f sync_history,
		zbx_uint64_t history_cache_size, zbx_uint64_t history_index_cache_size,zbx_uint64_t *trends_cache_size,
		char **error);

void	zbx_free_database_cache(int sync, const zbx_events_funcs_t *events_cbs);

void	zbx_sync_server_history(int *values_num, int *triggers_num, const zbx_events_funcs_t *events_cbs, int *more);

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

void	*zbx_dc_get_stats(int request);
void	zbx_dc_get_stats_all(zbx_wcache_info_t *wcache_info);

zbx_uint64_t	zbx_dc_get_nextid(const char *table_name, int num);

void	zbx_dc_update_interfaces_availability(void);

void	zbx_hc_get_diag_stats(zbx_uint64_t *items_num, zbx_uint64_t *values_num);
void	zbx_hc_get_mem_stats(zbx_shmem_stats_t *data, zbx_shmem_stats_t *index);
void	zbx_hc_get_items(zbx_vector_uint64_pair_t *items);

int	zbx_db_trigger_queue_locked(void);
void	zbx_db_trigger_queue_unlock(void);

int	zbx_hc_check_proxy(zbx_uint64_t proxyid);

void	zbx_dc_add_history(zbx_uint64_t itemid, unsigned char item_value_type, unsigned char item_flags,
		AGENT_RESULT *result, const zbx_timespec_t *ts, unsigned char state, const char *error);
void	zbx_dc_add_history_variant(zbx_uint64_t itemid, unsigned char value_type, unsigned char item_flags,
		zbx_variant_t *value, zbx_timespec_t ts, const zbx_pp_value_opt_t *value_opt);
void	zbx_dc_flush_history(void);

#endif
