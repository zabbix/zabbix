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
#include "zbxpreproc.h"

#define ZBX_SYNC_DONE		0
#define	ZBX_SYNC_MORE		1

extern zbx_uint64_t	CONFIG_HISTORY_CACHE_SIZE;
extern zbx_uint64_t	CONFIG_HISTORY_INDEX_CACHE_SIZE;
extern zbx_uint64_t	CONFIG_TRENDS_CACHE_SIZE;

typedef struct
{
	zbx_uint64_t	history_counter;	/* the total number of processed values */
	zbx_uint64_t	history_float_counter;	/* the number of processed float values */
	zbx_uint64_t	history_uint_counter;	/* the number of processed uint values */
	zbx_uint64_t	history_str_counter;	/* the number of processed str values */
	zbx_uint64_t	history_log_counter;	/* the number of processed log values */
	zbx_uint64_t	history_text_counter;	/* the number of processed text values */
	zbx_uint64_t	notsupported_counter;	/* the number of processed not supported items */
}
ZBX_DC_STATS;

/* the write cache statistics */
typedef struct
{
	ZBX_DC_STATS	stats;
	zbx_uint64_t	history_free;
	zbx_uint64_t	history_total;
	zbx_uint64_t	index_free;
	zbx_uint64_t	index_total;
	zbx_uint64_t	trend_free;
	zbx_uint64_t	trend_total;
}
zbx_wcache_info_t;

void	zbx_sync_history_cache(int *values_num, int *triggers_num, int *more);
void	zbx_log_sync_history_cache_progress(void);

#define ZBX_SYNC_NONE	0
#define ZBX_SYNC_ALL	1

int	init_database_cache(char **error);
void	free_database_cache(int);

void	change_proxy_history_count(int change_count);
void	reset_proxy_history_count(int reset);
int	get_proxy_history_count(void);

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
void	*DCget_stats(int request);
void	DCget_stats_all(zbx_wcache_info_t *wcache_info);

zbx_uint64_t	DCget_nextid(const char *table_name, int num);

void	DCupdate_interfaces_availability(void);

void	zbx_hc_get_diag_stats(zbx_uint64_t *items_num, zbx_uint64_t *values_num);
void	zbx_hc_get_mem_stats(zbx_shmem_stats_t *data, zbx_shmem_stats_t *index);
void	zbx_hc_get_items(zbx_vector_uint64_pair_t *items);

int	zbx_db_trigger_queue_locked(void);
void	zbx_db_trigger_queue_unlock(void);

int	zbx_hc_check_proxy(zbx_uint64_t proxyid);

void	dc_add_history(zbx_uint64_t itemid, unsigned char item_value_type, unsigned char item_flags,
		AGENT_RESULT *result, const zbx_timespec_t *ts, unsigned char state, const char *error);
void	dc_add_history_variant(zbx_uint64_t itemid, unsigned char value_type, unsigned char item_flags,
		zbx_variant_t *value, zbx_timespec_t ts, const zbx_pp_value_opt_t *value_opt);
void	dc_flush_history(void);

#endif
