/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

#	define ZBX_MUTEX_ERROR		0
#	define ZBX_MUTEX_OK		1

#	define ZBX_MUTEX_NAME		wchar_t *

#	define ZBX_MUTEX_LOG		TEXT("ZBX_MUTEX_LOG")
#	define ZBX_MUTEX_PERFSTAT	TEXT("ZBX_MUTEX_PERFSTAT")

#else	/* not _WINDOWS */

#	define ZBX_MUTEX		int
#	define ZBX_MUTEX_NULL		-1

#	define ZBX_MUTEX_ERROR		-1
#	define ZBX_MUTEX_OK		1

#	define ZBX_MUTEX_NAME		int

#	define ZBX_NO_MUTEX		-1
#	define ZBX_MUTEX_LOG		0
#	define ZBX_MUTEX_NODE_SYNC	1
#	define ZBX_MUTEX_CACHE		2
#	define ZBX_MUTEX_TRENDS		3
#	define ZBX_MUTEX_CACHE_IDS	4
#	define ZBX_MUTEX_CONFIG		5
#	define ZBX_MUTEX_SELFMON	6
#	define ZBX_MUTEX_CPUSTATS	7
#	define ZBX_MUTEX_DISKSTATS	8
#	define ZBX_MUTEX_ITSERVICES	9
#	define ZBX_MUTEX_COUNT		10

#	define ZBX_MUTEX_MAX_TRIES	20	/* seconds */

#endif	/* _WINDOWS */

#define zbx_mutex_create(mutex, name)		zbx_mutex_create_ext(mutex, name, 0)
#define zbx_mutex_create_force(mutex, name)	zbx_mutex_create_ext(mutex, name, 1)
#define zbx_mutex_lock(mutex)			__zbx_mutex_lock(__FILE__, __LINE__, mutex)
#define zbx_mutex_unlock(mutex)			__zbx_mutex_unlock(__FILE__, __LINE__, mutex)

int	zbx_mutex_create_ext(ZBX_MUTEX *mutex, ZBX_MUTEX_NAME name, unsigned char forced);
void	__zbx_mutex_lock(const char *filename, int line, ZBX_MUTEX *mutex);
void	__zbx_mutex_unlock(const char *filename, int line, ZBX_MUTEX *mutex);
int	zbx_mutex_destroy(ZBX_MUTEX *mutex);

#if defined(HAVE_SQLITE3)

/*********************************************************/
/*** PHP Semaphore functions using System V semaphores ***/
/*********************************************************/

#define PHP_MUTEX_OK	1
#define PHP_MUTEX_ERROR	0

typedef struct
{
	int	semid;
	int	count;
}
PHP_MUTEX;

int	php_sem_get(PHP_MUTEX *sem_ptr, const char *path_name);
int	php_sem_acquire(PHP_MUTEX *sem_ptr);
int	php_sem_release(PHP_MUTEX *sem_ptr);
int	php_sem_remove(PHP_MUTEX *sem_ptr);

#endif	/* HAVE_SQLITE3 */

#endif	/* ZABBIX_MUTEXS_H */
