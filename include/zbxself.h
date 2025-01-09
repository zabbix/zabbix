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

#ifndef ZABBIX_ZBXSELF_H
#define ZABBIX_ZBXSELF_H

#define ZBX_SELFMON_AGGR_FUNC_ONE	0
#define ZBX_SELFMON_AGGR_FUNC_AVG	1
#define ZBX_SELFMON_AGGR_FUNC_MAX	2
#define ZBX_SELFMON_AGGR_FUNC_MIN	3

#define ZBX_SELFMON_DELAY		1

#ifndef _WINDOWS
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
