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

#ifndef ZABBIX_DBSYNCER_H
#define ZABBIX_DBSYNCER_H

#include "zbxdbhigh.h"
#include "zbxhistory.h"

/* the maximum time spent synchronizing history */
#define ZBX_HC_SYNC_TIME_MAX	10

/* the maximum number of items in one synchronization batch */
#define ZBX_HC_SYNC_MAX		1000
#define ZBX_HC_TIMER_MAX	(ZBX_HC_SYNC_MAX / 2)
#define ZBX_HC_TIMER_SOFT_MAX	(ZBX_HC_TIMER_MAX - 10)

void	dbcache_lock(void);
void	dbcache_unlock(void);

void	hc_pop_items(zbx_vector_ptr_t *history_items);
void	hc_push_items(zbx_vector_ptr_t *history_items);
void	hc_get_item_values(zbx_dc_history_t *history, zbx_vector_ptr_t *history_items);
int	hc_queue_get_size(void);
void	hc_free_item_values(zbx_dc_history_t *history, int history_num);

void	dc_history_clean_value(zbx_dc_history_t *history);

void	dbcache_set_history_num(int num);
int	dbcache_get_history_num(void);

#endif
