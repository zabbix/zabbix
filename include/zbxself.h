/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

#ifndef ZABBIX_ZBXSELF_H
#define ZABBIX_ZBXSELF_H

#define ZBX_PROCESS_STATE_IDLE		0
#define ZBX_PROCESS_STATE_BUSY		1
#define ZBX_PROCESS_STATE_COUNT		2	/* number of process states */

#define ZBX_SELFMON_AGGR_FUNC_ONE	0
#define ZBX_SELFMON_AGGR_FUNC_AVG	1
#define ZBX_SELFMON_AGGR_FUNC_MAX	2
#define ZBX_SELFMON_AGGR_FUNC_MIN	3

#define ZBX_SELFMON_DELAY		1

#ifndef _WINDOWS
#include "zbxcommon.h"
#include "zbxthreads.h"
#include "zbxstats.h"

ZBX_THREAD_ENTRY(zbx_selfmon_thread, args);

int	zbx_init_selfmon_collector(zbx_get_config_forks_f get_config_forks, char **error);
void	zbx_free_selfmon_collector(void);
void	zbx_update_selfmon_counter(const zbx_thread_info_t *info, unsigned char state);
void	zbx_get_selfmon_stats(unsigned char proc_type, unsigned char aggr_func, int proc_num, unsigned char state,
		double *value);
int	zbx_get_all_process_stats(zbx_process_info_t *stats);
void	zbx_sleep_loop(const zbx_thread_info_t *info, int sleeptime);
#endif

#endif	/* ZABBIX_ZBXSELF_H */
