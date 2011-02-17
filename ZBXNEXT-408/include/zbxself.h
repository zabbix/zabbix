/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#ifndef ZABBIX_ZBXSELF_H
#define ZABBIX_ZBXSELF_H

#if defined(_WINDOWS)
#	error "This module allowed only for Unix OS"
#endif	/* _WINDOWS */

#define ZBX_PROCESS_STATE_IDLE	0
#define ZBX_PROCESS_STATE_BUSY	1
#define ZBX_PROCESS_STATE_COUNT	2	/* number of process states */

#define ZBX_PROCESS_TYPE_POLLER		0
#define ZBX_PROCESS_TYPE_UNREACHABLE	1
#define ZBX_PROCESS_TYPE_IPMIPOLLER	2
#define ZBX_PROCESS_TYPE_PINGER		3
#define ZBX_PROCESS_TYPE_HTTPPOLLER	4
#define ZBX_PROCESS_TYPE_TRAPPER	5
#define ZBX_PROCESS_TYPE_PROXYPOLLER	6
#define ZBX_PROCESS_TYPE_ESCALATOR	7
#define ZBX_PROCESS_TYPE_DBSYNCER	8
#define ZBX_PROCESS_TYPE_DISCOVERER	9
#define ZBX_PROCESS_TYPE_ALERTER	10
#define ZBX_PROCESS_TYPE_TIMER		11
#define ZBX_PROCESS_TYPE_COUNT		12	/* number of process types */
#define ZBX_PROCESS_TYPE_UNKNOWN	255

int	get_process_forks(unsigned char process_type);
void	init_sm_collector();
void	free_sm_collector();
void	update_sm_counter(unsigned char state);
void	collect_selfstats();
int	get_selfstats(unsigned char process_type, int process_num, unsigned char state, double *value);

#endif	/* ZABBIX_ZBXSELF_H */
