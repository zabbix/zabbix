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

#ifndef ZABBIX_MUTEXS_H
#define ZABBIX_MUTEXS_H

#include "zbxcommon.h"
#include "zbxprof.h"

#ifdef _WINDOWS
#	define ZBX_MUTEX_NULL		NULL

#	define ZBX_MUTEX_LOG		zbx_mutex_create_per_process_name(L"ZBX_MUTEX_LOG")
#	define ZBX_MUTEX_PERFSTAT	zbx_mutex_create_per_process_name(L"ZBX_MUTEX_PERFSTAT")

typedef wchar_t * zbx_mutex_name_t;
typedef HANDLE zbx_mutex_t;

#	define zbx_mutex_lock(mutex)		__zbx_mutex_lock(__FILE__, __LINE__, mutex)
#	define zbx_mutex_unlock(mutex)		__zbx_mutex_unlock(__FILE__, __LINE__, mutex)
#else	/* not _WINDOWS */
typedef enum
{
	ZBX_MUTEX_LOG = 0,
	ZBX_MUTEX_CACHE,
	ZBX_MUTEX_TRENDS,
	ZBX_MUTEX_CACHE_IDS,
	ZBX_MUTEX_SELFMON,
	ZBX_MUTEX_CPUSTATS,
	ZBX_MUTEX_DISKSTATS,
	ZBX_MUTEX_VALUECACHE,
	ZBX_MUTEX_VMWARE,
	ZBX_MUTEX_SQLITE3,
	ZBX_MUTEX_PROCSTAT,
	ZBX_MUTEX_PROXY_HISTORY,
#ifdef HAVE_VMINFO_T_UPDATES
	ZBX_MUTEX_KSTAT,
#endif
	ZBX_MUTEX_MODBUS,
	ZBX_MUTEX_TREND_FUNC,
	ZBX_MUTEX_REMOTE_COMMANDS,
	ZBX_MUTEX_PROXY_BUFFER,
	ZBX_MUTEX_VPS_MONITOR,
	/* NOTE: Do not forget to sync changes here with mutex names in diag_add_locks_info()! */
	ZBX_MUTEX_COUNT
}
zbx_mutex_name_t;

typedef enum
{
	ZBX_RWLOCK_CONFIG = 0,
	ZBX_RWLOCK_CONFIG_HISTORY,
	ZBX_RWLOCK_VALUECACHE,
	ZBX_RWLOCK_COUNT,
}
zbx_rwlock_name_t;

#ifdef HAVE_PTHREAD_PROCESS_SHARED
#	define ZBX_MUTEX_NULL			NULL
#	define ZBX_RWLOCK_NULL			NULL

#	define zbx_rwlock_wrlock(rwlock)				\
									\
	do								\
	{								\
		zbx_prof_start(__func__, ZBX_PROF_RWLOCK);		\
		__zbx_rwlock_wrlock(__FILE__, __LINE__, rwlock);	\
		zbx_prof_end_wait();					\
	}								\
	while (0)

#	define zbx_rwlock_rdlock(rwlock)				\
									\
	do								\
	{								\
		zbx_prof_start(__func__, ZBX_PROF_RWLOCK);		\
		__zbx_rwlock_rdlock(__FILE__, __LINE__, rwlock);	\
		zbx_prof_end_wait();					\
	}								\
	while(0)

#	define zbx_rwlock_unlock(rwlock)				\
									\
	do								\
	{								\
		__zbx_rwlock_unlock(__FILE__, __LINE__, rwlock);	\
		zbx_prof_end();						\
	}								\
	while(0)

typedef pthread_mutex_t * zbx_mutex_t;
typedef pthread_rwlock_t * zbx_rwlock_t;

void	__zbx_rwlock_wrlock(const char *filename, int line, zbx_rwlock_t rwlock);
void	__zbx_rwlock_rdlock(const char *filename, int line, zbx_rwlock_t rwlock);
void	__zbx_rwlock_unlock(const char *filename, int line, zbx_rwlock_t rwlock);
void	zbx_rwlock_destroy(zbx_rwlock_t *rwlock);
void	zbx_locks_disable(void);
void	zbx_locks_enable(void);
#else	/* fallback to semaphores if read-write locks are not available */
#	define ZBX_RWLOCK_NULL				-1
#	define ZBX_MUTEX_NULL				-1

#	define zbx_rwlock_wrlock(mutex)					\
									\
	do								\
	{								\
		zbx_prof_start(__func__, ZBX_PROF_MUTEX);		\
		__zbx_mutex_lock(__FILE__, __LINE__, mutex);		\
		zbx_prof_end_wait();					\
	}								\
	while(0)

#	define zbx_rwlock_rdlock(mutex)					\
									\
	do								\
	{								\
		zbx_prof_start(__func__, ZBX_PROF_MUTEX);		\
		__zbx_mutex_lock(__FILE__, __LINE__, mutex);		\
		zbx_prof_end_wait();					\
	}								\
	while(0)

#	define zbx_rwlock_unlock(mutex)					\
									\
	do								\
	{								\
		__zbx_mutex_unlock(__FILE__, __LINE__, mutex);		\
		zbx_prof_end();						\
	}								\
	while(0)

#	define zbx_rwlock_destroy(rwlock)		zbx_mutex_destroy(rwlock)

typedef int zbx_mutex_t;
typedef int zbx_rwlock_t;
#endif
int		zbx_locks_create(char **error);
void		zbx_locks_destroy(void);
int		zbx_rwlock_create(zbx_rwlock_t *rwlock, zbx_rwlock_name_t name, char **error);
zbx_mutex_t	zbx_mutex_addr_get(zbx_mutex_name_t mutex_name);
zbx_rwlock_t	zbx_rwlock_addr_get(zbx_rwlock_name_t rwlock_name);

#	define zbx_mutex_lock(mutex)					\
									\
	do								\
	{								\
		zbx_prof_start(__func__, ZBX_PROF_MUTEX);		\
		__zbx_mutex_lock(__FILE__, __LINE__, mutex);		\
		zbx_prof_end_wait();					\
	}								\
	while(0)

#	define zbx_mutex_unlock(mutex)					\
									\
	do								\
	{								\
		__zbx_mutex_unlock(__FILE__, __LINE__, mutex);		\
		zbx_prof_end();						\
	}								\
	while(0)
#endif	/* _WINDOWS */

int	zbx_mutex_create(zbx_mutex_t *mutex, zbx_mutex_name_t name, char **error);
void	__zbx_mutex_lock(const char *filename, int line, zbx_mutex_t mutex);
void	__zbx_mutex_unlock(const char *filename, int line, zbx_mutex_t mutex);
void	zbx_mutex_destroy(zbx_mutex_t *mutex);

#ifdef _WINDOWS
zbx_mutex_name_t	zbx_mutex_create_per_process_name(const zbx_mutex_name_t prefix);
#endif

#endif	/* ZABBIX_MUTEXS_H */
