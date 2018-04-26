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

#ifndef ZABBIX_MUTEXS_H
#define ZABBIX_MUTEXS_H

#ifdef _WINDOWS

#	define ZBX_MUTEX		HANDLE
#	define ZBX_MUTEX_NULL		NULL

#	define ZBX_MUTEX_NAME		wchar_t *

#	define ZBX_MUTEX_LOG		zbx_mutex_create_per_process_name(L"ZBX_MUTEX_LOG")
#	define ZBX_MUTEX_PERFSTAT	zbx_mutex_create_per_process_name(L"ZBX_MUTEX_PERFSTAT")

#else	/* not _WINDOWS */

#	define ZBX_MUTEX		int
#	define ZBX_MUTEX_NULL		-1

#	define ZBX_MUTEX_NAME		int

#	define ZBX_MUTEX_LOG		0
#	define ZBX_MUTEX_CACHE		1
#	define ZBX_MUTEX_TRENDS		2
#	define ZBX_MUTEX_CACHE_IDS	3
#	define ZBX_MUTEX_CONFIG		4
#	define ZBX_MUTEX_SELFMON	5
#	define ZBX_MUTEX_CPUSTATS	6
#	define ZBX_MUTEX_DISKSTATS	7
#	define ZBX_MUTEX_ITSERVICES	8
#	define ZBX_MUTEX_VALUECACHE	9
#	define ZBX_MUTEX_VMWARE		10
#	define ZBX_MUTEX_SQLITE3	11
#	define ZBX_MUTEX_PROCSTAT	12
#	define ZBX_MUTEX_PROXY_HISTORY	13
#	define ZBX_MUTEX_COUNT		14

#endif	/* _WINDOWS */

#define zbx_mutex_lock(mutex)			__zbx_mutex_lock(__FILE__, __LINE__, mutex)
#define zbx_mutex_unlock(mutex)			__zbx_mutex_unlock(__FILE__, __LINE__, mutex)

int	zbx_mutex_create(ZBX_MUTEX *mutex, ZBX_MUTEX_NAME name, char **error);
void	__zbx_mutex_lock(const char *filename, int line, ZBX_MUTEX *mutex);
void	__zbx_mutex_unlock(const char *filename, int line, ZBX_MUTEX *mutex);
void	zbx_mutex_destroy(ZBX_MUTEX *mutex);

#ifdef _WINDOWS
ZBX_MUTEX_NAME	zbx_mutex_create_per_process_name(const ZBX_MUTEX_NAME prefix);
#endif

#endif	/* ZABBIX_MUTEXS_H */
