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

#ifndef ZABBIX_TIMEKEEPER_H
#define ZABBIX_TIMEKEEPER_H

#include "zbxalgo.h"

#define ZBX_TIMEKEEPER_DELAY	1

#define ZBX_TIMEKEEPER_AGGR_FUNC_ONE	0
#define ZBX_TIMEKEEPER_AGGR_FUNC_AVG	1
#define ZBX_TIMEKEEPER_AGGR_FUNC_MAX	2
#define ZBX_TIMEKEEPER_AGGR_FUNC_MIN	3

#define ZBX_PROCESS_STATE_IDLE		0
#define ZBX_PROCESS_STATE_BUSY		1
#define ZBX_PROCESS_STATE_COUNT		2	/* number of process states */

typedef struct zbx_timekeeper zbx_timekeeper_t;

typedef struct
{
	double	busy_max;
	double	busy_min;
	double	busy_avg;
	double	idle_max;
	double	idle_min;
	double	idle_avg;
}
zbx_timekeeper_stats_t;

typedef struct
{
	unsigned short	counter[ZBX_PROCESS_STATE_COUNT];
}
zbx_timekeeper_state_t;

typedef void (*zbx_timekeeper_sync_func_t)(void *data);

typedef struct
{
	zbx_timekeeper_sync_func_t	lock;
	zbx_timekeeper_sync_func_t	unlock;
	void				*data;
}
zbx_timekeeper_sync_t;

void	zbx_timekeeper_sync_init(zbx_timekeeper_sync_t *sync, zbx_timekeeper_sync_func_t lock,
		zbx_timekeeper_sync_func_t unlock, void *data);

size_t	zbx_timekeeper_get_memmalloc_size(int units_num);

zbx_timekeeper_t	*zbx_timekeeper_create_ext(int units_num, zbx_timekeeper_sync_t *sync,
		zbx_mem_malloc_func_t mem_malloc_func, zbx_mem_realloc_func_t mem_realloc_func,
		zbx_mem_free_func_t mem_free_func);
zbx_timekeeper_t	*zbx_timekeeper_create(int units_num, zbx_timekeeper_sync_t *sync);
void	zbx_timekeeper_free(zbx_timekeeper_t *timekeeper);

void	zbx_timekeeper_update(zbx_timekeeper_t *timekeeper, int index, unsigned char state);
void	zbx_timekeeper_collect(zbx_timekeeper_t *timekeeper);

int	zbx_timekeeper_get_stat(zbx_timekeeper_t *timekeeper, int unit_index, int count, unsigned char aggr_func,
		unsigned char state, double *value, char **error);
zbx_timekeeper_state_t	*zbx_timekeeper_get_counters(zbx_timekeeper_t *timekeeper);

int	zbx_timekeeper_get_usage(zbx_timekeeper_t *timekeeper, zbx_vector_dbl_t *usage);

#endif
