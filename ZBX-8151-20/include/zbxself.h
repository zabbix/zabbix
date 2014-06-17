/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#if defined(_WINDOWS)
#	error "This module allowed only for Unix OS"
#endif

#define ZBX_PROCESS_STATE_IDLE		0
#define ZBX_PROCESS_STATE_BUSY		1
#define ZBX_PROCESS_STATE_COUNT		2	/* number of process states */

#define ZBX_PROCESS_TYPE_POLLER		0
#define ZBX_PROCESS_TYPE_UNREACHABLE	1
#define ZBX_PROCESS_TYPE_IPMIPOLLER	2
#define ZBX_PROCESS_TYPE_PINGER		3
#define ZBX_PROCESS_TYPE_JAVAPOLLER	4
#define ZBX_PROCESS_TYPE_HTTPPOLLER	5
#define ZBX_PROCESS_TYPE_TRAPPER	6
#define ZBX_PROCESS_TYPE_SNMPTRAPPER	7
#define ZBX_PROCESS_TYPE_PROXYPOLLER	8
#define ZBX_PROCESS_TYPE_ESCALATOR	9
#define ZBX_PROCESS_TYPE_HISTSYNCER	10
#define ZBX_PROCESS_TYPE_DISCOVERER	11
#define ZBX_PROCESS_TYPE_ALERTER	12
#define ZBX_PROCESS_TYPE_TIMER		13
#define ZBX_PROCESS_TYPE_NODEWATCHER	14
#define ZBX_PROCESS_TYPE_HOUSEKEEPER	15
#define ZBX_PROCESS_TYPE_WATCHDOG	16
#define ZBX_PROCESS_TYPE_DATASENDER	17
#define ZBX_PROCESS_TYPE_CONFSYNCER	18
#define ZBX_PROCESS_TYPE_HEARTBEAT	19
#define ZBX_PROCESS_TYPE_SELFMON	20
#define ZBX_PROCESS_TYPE_COUNT		21	/* number of process types */
#define ZBX_PROCESS_TYPE_UNKNOWN	255

#define ZBX_AGGR_FUNC_ONE		0
#define ZBX_AGGR_FUNC_AVG		1
#define ZBX_AGGR_FUNC_MAX		2
#define ZBX_AGGR_FUNC_MIN		3

int	get_process_type_forks(unsigned char process_type);
const char	*get_process_type_string(unsigned char process_type);
void	init_selfmon_collector();
void	free_selfmon_collector();
void	update_selfmon_counter(unsigned char state);
void	collect_selfmon_stats();
void	get_selfmon_stats(unsigned char process_type, unsigned char aggr_func, int process_num,
		unsigned char state, double *value);
void	zbx_sleep_loop(int sleeptime);
void	zbx_wakeup();

#endif	/* ZABBIX_ZBXSELF_H */
