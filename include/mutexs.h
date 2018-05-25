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
#define ZBX_PTHREAD
typedef enum
{
	ZBX_MUTEX_LOG = 0,
	ZBX_MUTEX_CACHE,
	ZBX_MUTEX_TRENDS,
	ZBX_MUTEX_CACHE_IDS,
	ZBX_MUTEX_SELFMON,
	ZBX_MUTEX_CPUSTATS,
	ZBX_MUTEX_DISKSTATS,
	ZBX_MUTEX_ITSERVICES,
	ZBX_MUTEX_VALUECACHE,
	ZBX_MUTEX_VMWARE,
	ZBX_MUTEX_SQLITE3,
	ZBX_MUTEX_PROCSTAT,
	ZBX_MUTEX_PROXY_HISTORY,
#ifndef ZBX_PTHREAD
	ZBX_MUTEX_CONFIG,
#endif
	ZBX_MUTEX_COUNT,
	ZBX_MUTEX_NULL
}
zbx_mutex_lock_type_t;

#	define ZBX_MUTEX		zbx_mutex_lock_type_t
#	define ZBX_MUTEX_NAME		zbx_mutex_lock_type_t

#ifdef ZBX_PTHREAD
typedef enum
{
	ZBX_RWLOCK_CONFIG = 0,
	ZBX_RWLOCK_COUNT,
	ZBX_RWLOCK_NULL
}
zbx_rwlock_lock_type_t;

#	define ZBX_RWLOCK		zbx_rwlock_lock_type_t
#	define ZBX_RWLOCK_NAME		zbx_rwlock_lock_type_t
#else
#	define ZBX_RWLOCK		zbx_mutex_lock_type_t
#	define ZBX_RWLOCK_NAME		zbx_mutex_lock_type_t
#	define ZBX_RWLOCK_CONFIG	ZBX_MUTEX_CONFIG
#	define ZBX_RWLOCK_NULL		ZBX_MUTEX_NULL
#endif

#endif	/* _WINDOWS */

int	zbx_locks_create(char **error);

#define zbx_mutex_lock(mutex)		__zbx_mutex_lock(__FILE__, __LINE__, mutex)
#define zbx_mutex_unlock(mutex)		__zbx_mutex_unlock(__FILE__, __LINE__, mutex)

#ifdef ZBX_PTHREAD
#define zbx_rwlock_wrlock(mutex)		__zbx_rwlock_wrlock(__FILE__, __LINE__, mutex)
#define zbx_rwlock_rdlock(mutex)		__zbx_rwlock_rdlock(__FILE__, __LINE__, mutex)
#define zbx_rwlock_unlock(mutex)		__zbx_rwlock_unlock(__FILE__, __LINE__, mutex)

int	zbx_rwlock_create(ZBX_RWLOCK *rwlock, ZBX_RWLOCK name, char **error);
void	__zbx_rwlock_wrlock(const char *filename, int line, const ZBX_RWLOCK_NAME *mutex);
void	__zbx_rwlock_rdlock(const char *filename, int line, const ZBX_RWLOCK_NAME *mutex);
void	__zbx_rwlock_unlock(const char *filename, int line, const ZBX_RWLOCK_NAME *mutex);
void	zbx_rwlock_destroy(ZBX_RWLOCK *mutex);
void	zbx_locks_disable(void);
#else
#define zbx_rwlock_create(rwlock, name, error)	zbx_mutex_create(rwlock, name, error)
#define zbx_rwlock_wrlock(mutex)		__zbx_mutex_lock(__FILE__, __LINE__, mutex)
#define zbx_rwlock_rdlock(mutex)		__zbx_mutex_lock(__FILE__, __LINE__, mutex)
#define zbx_rwlock_unlock(mutex)		__zbx_mutex_unlock(__FILE__, __LINE__, mutex)
#define zbx_rwlock_destroy(mutex)		zbx_mutex_destroy(mutex)
#endif

int	zbx_mutex_create(ZBX_MUTEX *mutex, ZBX_MUTEX_NAME name, char **error);
void	__zbx_mutex_lock(const char *filename, int line, ZBX_MUTEX *mutex);
void	__zbx_mutex_unlock(const char *filename, int line, ZBX_MUTEX *mutex);
void	zbx_mutex_destroy(ZBX_MUTEX *mutex);

#ifdef _WINDOWS
ZBX_MUTEX_NAME	zbx_mutex_create_per_process_name(const ZBX_MUTEX_NAME prefix);
#endif

#endif	/* ZABBIX_MUTEXS_H */
