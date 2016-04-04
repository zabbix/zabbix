/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

#ifndef ZABBIX_IPC_H
#define ZABBIX_IPC_H

#if defined(_WINDOWS)
#	error "This module allowed only for Unix OS"
#endif

#define ZBX_IPC_CONFIG_ID		'g'
#define ZBX_IPC_HISTORY_ID		'h'
#define ZBX_IPC_HISTORY_TEXT_ID		'x'
#define ZBX_IPC_TREND_ID		't'
#define ZBX_IPC_STRPOOL_ID		's'
#define ZBX_IPC_COLLECTOR_ID		'l'
#define ZBX_IPC_COLLECTOR_DISKSTAT	'm'
#define ZBX_IPC_SELFMON_ID		'S'

key_t	zbx_ftok(char *path, int id);
int	zbx_shmget(key_t key, size_t size);

#endif
