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

#ifndef ZABBIX_ZBXSELF_H
#define ZABBIX_ZBXSELF_H

#define ZBX_PROCESS_STATE_IDLE		0
#define ZBX_PROCESS_STATE_BUSY		1
#define ZBX_PROCESS_STATE_COUNT		2	/* number of process states */

#define ZBX_AGGR_FUNC_ONE		0
#define ZBX_AGGR_FUNC_AVG		1
#define ZBX_AGGR_FUNC_MAX		2
#define ZBX_AGGR_FUNC_MIN		3

#define ZBX_SELFMON_DELAY		1

int	get_process_type_forks(unsigned char process_type);

#ifndef _WINDOWS
int	init_selfmon_collector(char **error);
void	free_selfmon_collector(void);
void	update_selfmon_counter(unsigned char state);
void	collect_selfmon_stats(void);
void	get_selfmon_stats(unsigned char process_type, unsigned char aggr_func, int process_num,
		unsigned char state, double *value);
void	zbx_sleep_loop(int sleeptime);
void	zbx_sleep_forever(void);
void	zbx_wakeup(void);
int	zbx_sleep_get_remainder(void);
#endif

#endif	/* ZABBIX_ZBXSELF_H */
