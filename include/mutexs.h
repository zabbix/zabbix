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
#ifdef HAVE_PTHREAD_PROCESS_SHARED
#	define ZBX_MUTEX		pthread_mutex_t *
#	define ZBX_MUTEX_NULL		NULL

#	define ZBX_MUTEX_NAME		zbx_mutex_lock_type_t

#	define ZBX_RWLOCK		pthread_rwlock_t *
#	define ZBX_RWLOCK_NULL		NULL

#	define ZBX_RWLOCK_NAME		zbx_rwlock_lock_type_t

typedef enum
{
	ZBX_RWLOCK_CONFIG = 0,
	ZBX_RWLOCK_COUNT,
}
zbx_rwlock_lock_type_t;

#else
#	define ZBX_MUTEX		zbx_mutex_lock_type_t
#	define ZBX_MUTEX_NULL		255

#	define ZBX_MUTEX_NAME		zbx_mutex_lock_type_t

#	define ZBX_RWLOCK		zbx_mutex_lock_type_t
#	define ZBX_RWLOCK_NULL		ZBX_MUTEX_NULL

#	define ZBX_RWLOCK_NAME		zbx_mutex_lock_type_t

#	define ZBX_RWLOCK_CONFIG	ZBX_MUTEX_CONFIG

#endif

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
#ifndef HAVE_PTHREAD_PROCESS_SHARED
	ZBX_MUTEX_CONFIG,
#endif
	ZBX_MUTEX_COUNT,
}
zbx_mutex_lock_type_t;

int	zbx_locks_create(char **error);

#ifdef HAVE_PTHREAD_PROCESS_SHARED
#define zbx_rwlock_wrlock(rwlock)		__zbx_rwlock_wrlock(__FILE__, __LINE__, rwlock)
#define zbx_rwlock_rdlock(rwlock)		__zbx_rwlock_rdlock(__FILE__, __LINE__, rwlock)
#define zbx_rwlock_unlock(rwlock)		__zbx_rwlock_unlock(__FILE__, __LINE__, rwlock)

int	zbx_rwlock_create(ZBX_RWLOCK *rwlock, ZBX_RWLOCK_NAME name, char **error);
void	__zbx_rwlock_wrlock(const char *filename, int line, ZBX_RWLOCK *rwlock);
void	__zbx_rwlock_rdlock(const char *filename, int line, ZBX_RWLOCK *rwlock);
void	__zbx_rwlock_unlock(const char *filename, int line, ZBX_RWLOCK *rwlock);
void	zbx_rwlock_destroy(ZBX_RWLOCK *rwlock);
void	zbx_locks_disable(void);
#else
#define zbx_rwlock_create(rwlock, name, error)	zbx_mutex_create(rwlock, name, error)
#define zbx_rwlock_wrlock(rwlock)		__zbx_mutex_lock(__FILE__, __LINE__, rwlock)
#define zbx_rwlock_rdlock(rwlock)		__zbx_mutex_lock(__FILE__, __LINE__, rwlock)
#define zbx_rwlock_unlock(rwlock)		__zbx_mutex_unlock(__FILE__, __LINE__, rwlock)
#define zbx_rwlock_destroy(rwlock)		zbx_mutex_destroy(rwlock)
#endif
#endif	/* _WINDOWS */

#define zbx_mutex_lock(mutex)		__zbx_mutex_lock(__FILE__, __LINE__, mutex)
#define zbx_mutex_unlock(mutex)		__zbx_mutex_unlock(__FILE__, __LINE__, mutex)

int	zbx_mutex_create(ZBX_MUTEX *mutex, ZBX_MUTEX_NAME name, char **error);
void	__zbx_mutex_lock(const char *filename, int line, ZBX_MUTEX *mutex);
void	__zbx_mutex_unlock(const char *filename, int line, ZBX_MUTEX *mutex);
void	zbx_mutex_destroy(ZBX_MUTEX *mutex);

#ifdef _WINDOWS
ZBX_MUTEX_NAME	zbx_mutex_create_per_process_name(const ZBX_MUTEX_NAME prefix);
#endif

#endif	/* ZABBIX_MUTEXS_H */
