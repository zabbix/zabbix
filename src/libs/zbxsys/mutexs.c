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

#include "common.h"
#include "mutexs.h"
#include "log.h"

#ifndef _WINDOWS
#	if !HAVE_SEMUN
		union semun
		{
			int			val;	/* value for SETVAL */
			struct semid_ds		*buf;	/* buffer for IPC_STAT & IPC_SET */
			unsigned short int	*array;	/* array for GETALL & SETALL */
			struct seminfo		*__buf;	/* buffer for IPC_INFO */
		};

#		undef HAVE_SEMUN
#		define HAVE_SEMUN 1
#	endif	/* HAVE_SEMUN */

#	include "cfg.h"
#	include "threads.h"

	static int		ZBX_SEM_LIST_ID = -1;
	static unsigned char	mutexes = 0;
#endif

/******************************************************************************
 *                                                                            *
 * Function: zbx_mutex_create_ext                                             *
 *                                                                            *
 * Purpose: Create the mutex                                                  *
 *                                                                            *
 * Parameters:  mutex - handle of mutex                                       *
 *              name - name of mutex (index for nix system)                   *
 *              forced - remove mutex if exists (only for nix)                *
 *                                                                            *
 * Return value: If the function succeeds, then return ZBX_MUTEX_OK,          *
 *               ZBX_MUTEX_ERROR on an error                                  *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: use alias 'zbx_mutex_create' and 'zbx_mutex_create_force'        *
 *                                                                            *
 ******************************************************************************/
int zbx_mutex_create_ext(ZBX_MUTEX *mutex, ZBX_MUTEX_NAME name, unsigned char forced)
{
#ifdef _WINDOWS

	if (NULL == (*mutex = CreateMutex(NULL, FALSE, name)))
	{
		zbx_error("error on mutex creating: %s", strerror_from_system(GetLastError()));
		return ZBX_MUTEX_ERROR;
	}

#else

#define ZBX_MAX_ATTEMPTS	10
	int		attempts = 0, i;
	key_t		sem_key;
	union semun	semopts;
	struct semid_ds	seminfo;

	if (-1 == (sem_key = ftok(CONFIG_FILE, (int)'z')))
	{
		zbx_error("cannot create IPC key for path '%s', try to create for path '.': %s",
				CONFIG_FILE, zbx_strerror(errno));

		if (-1 == (sem_key = ftok(".", (int)'z')))
		{
			zbx_error("cannot create IPC key for path '.': %s", zbx_strerror(errno));
			return ZBX_MUTEX_ERROR;
		}
	}
lbl_create:
	if (-1 != ZBX_SEM_LIST_ID || -1 != (ZBX_SEM_LIST_ID = semget(sem_key, ZBX_MUTEX_COUNT, IPC_CREAT | IPC_EXCL | 0600 /* 0022 */)) )
	{
		/* set default semaphore value */

		semopts.val = 1;
		for (i = 0; ZBX_MUTEX_COUNT > i; i++)
		{
			if (-1 == semctl(ZBX_SEM_LIST_ID, i, SETVAL, semopts))
			{
				zbx_error("semaphore [%i] error in semctl(SETVAL): %s", name, zbx_strerror(errno));
				return ZBX_MUTEX_ERROR;

			}

			zbx_mutex_lock(&i);	/* call semop to update sem_otime */
			zbx_mutex_unlock(&i);	/* release semaphore */
		}
	}
	else if (EEXIST == errno)
	{
		ZBX_SEM_LIST_ID = semget(sem_key, 0 /* get reference */, 0600 /* 0022 */);

		if (1 == forced)
		{
			if (0 != semctl(ZBX_SEM_LIST_ID, 0, IPC_RMID, 0))
			{
				zbx_error("cannot recreate Zabbix semaphores for IPC key 0x%lx Semaphore ID %ld: %s",
						sem_key, ZBX_SEM_LIST_ID, zbx_strerror(errno));
				exit(FAIL);
			}

			/* semaphore is successfully removed */
			ZBX_SEM_LIST_ID = -1;

			if (ZBX_MAX_ATTEMPTS < ++attempts)
			{
				zbx_error("cannot recreate Zabbix semaphores for IPC key 0x%lx: too many attempts",
						sem_key);
				exit(FAIL);
			}

			if ((ZBX_MAX_ATTEMPTS / 2) < attempts)
				zbx_sleep(1);

			goto lbl_create;
		}

		semopts.buf = &seminfo;
		/* wait for initialization */
		for (i = 0; ZBX_MUTEX_MAX_TRIES > i; i++)
		{
			if (-1 == semctl(ZBX_SEM_LIST_ID, 0, IPC_STAT, semopts))
			{
				zbx_error("semaphore [%i] error in semctl(IPC_STAT): %s",
						name, zbx_strerror(errno));
				break;
			}

			if (0 != semopts.buf->sem_otime)
				goto lbl_return;

			zbx_sleep(1);
		}

		zbx_error("semaphore [%i] not initialized", name);
		return ZBX_MUTEX_ERROR;
	}
	else
	{
		zbx_error("cannot create Semaphore: %s", zbx_strerror(errno));
		return ZBX_MUTEX_ERROR;
	}
lbl_return:
	*mutex = name;
	mutexes++;

#endif	/* _WINDOWS */

	return ZBX_MUTEX_OK;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_mutex_lock                                                   *
 *                                                                            *
 * Purpose: Waits until the mutex is in the signalled state                   *
 *                                                                            *
 * Parameters: mutex - handle of mutex                                        *
 *                                                                            *
 * Author: Eugene Grigorjev, Alexander Vladishev                              *
 *                                                                            *
 ******************************************************************************/
void	__zbx_mutex_lock(const char *filename, int line, ZBX_MUTEX *mutex)
{
#ifndef _WINDOWS
	struct sembuf	sem_lock;
#endif

	if (ZBX_MUTEX_NULL == *mutex)
		return;

#ifdef _WINDOWS
	if (WAIT_OBJECT_0 != WaitForSingleObject(*mutex, INFINITE))
	{
		zbx_error("[file:'%s',line:%d] lock failed: %s",
				filename, line, strerror_from_system(GetLastError()));
		exit(FAIL);
	}
#else
	sem_lock.sem_num = *mutex;
	sem_lock.sem_op = -1;
	sem_lock.sem_flg = SEM_UNDO;

	while (-1 == semop(ZBX_SEM_LIST_ID, &sem_lock, 1))
	{
		if (EINTR != errno)
		{
			zbx_error("[file:'%s',line:%d] lock failed: %s", filename, line, zbx_strerror(errno));
			exit(FAIL);
		}
	}
#endif
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_mutex_unlock                                                 *
 *                                                                            *
 * Purpose: Unlock the mutex                                                  *
 *                                                                            *
 * Parameters: mutex - handle of mutex                                        *
 *                                                                            *
 * Author: Eugene Grigorjev, Alexander Vladishev                              *
 *                                                                            *
 ******************************************************************************/
void	__zbx_mutex_unlock(const char *filename, int line, ZBX_MUTEX *mutex)
{
#ifndef _WINDOWS
	struct sembuf	sem_unlock;
#endif

	if (ZBX_MUTEX_NULL == *mutex)
		return;

#ifdef _WINDOWS
	if (0 == ReleaseMutex(*mutex))
	{
		zbx_error("[file:'%s',line:%d] unlock failed: %s",
				filename, line, strerror_from_system(GetLastError()));
		exit(FAIL);
	}
#else
	sem_unlock.sem_num = *mutex;
	sem_unlock.sem_op = 1;
	sem_unlock.sem_flg = SEM_UNDO;

	while (-1 == semop(ZBX_SEM_LIST_ID, &sem_unlock, 1))
	{
		if (EINTR != errno)
		{
			zbx_error("[file:'%s',line:%d] unlock failed: %s", filename, line, zbx_strerror(errno));
			exit(FAIL);
		}
	}
#endif
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_mutex_destroy                                                *
 *                                                                            *
 * Purpose: Destroy the mutex                                                 *
 *                                                                            *
 * Parameters: mutex - handle of mutex                                        *
 *                                                                            *
 * Return value: If the function succeeds, then return 1, 0 on an error       *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_mutex_destroy(ZBX_MUTEX *mutex)
{
#ifdef _WINDOWS
	if (ZBX_MUTEX_NULL == *mutex)
		return ZBX_MUTEX_OK;

	if (0 == CloseHandle(*mutex))
	{
		zbx_error("error on mutex destroying: %s", strerror_from_system(GetLastError()));
		return ZBX_MUTEX_ERROR;
	}
#else
	if (0 == --mutexes)
		semctl(ZBX_SEM_LIST_ID, 0, IPC_RMID, 0);
#endif

	*mutex = ZBX_MUTEX_NULL;

	return ZBX_MUTEX_OK;
}

#if defined(HAVE_SQLITE3)

/*
   +----------------------------------------------------------------------+
   | PHP Version 5                                                        |
   +----------------------------------------------------------------------+
   | Copyright (c) 1997-2006 The PHP Group                                |
   +----------------------------------------------------------------------+
   | This part of source file is subject to version 3.01 of the           |
   | PHP license, that is bundled with this package in the file LICENSE,  |
   | and is available through the world-wide-web at the following url:    |
   | http://www.php.net/license/3_01.txt                                  |
   | If you did not receive a copy of the PHP license and are unable to   |
   | obtain it through the world-wide-web, please send a note to          |
   | license@php.net so we can mail you a copy immediately.               |
   +----------------------------------------------------------------------+
   | Authors: Tom May <tom@go2net.com>                                    |
   |          Gavin Sherry <gavin@linuxworld.com.au>                      |
   +----------------------------------------------------------------------+
 */

/* Semaphore functions using System V semaphores.  Each semaphore
 * actually consists of three semaphores allocated as a unit under the
 * same key.  Semaphore 0 (SYSVSEM_SEM) is the actual semaphore, it is
 * initialized to max_acquire and decremented as processes acquire it.
 * The value of semaphore 1 (SYSVSEM_USAGE) is a count of the number
 * of processes using the semaphore.  After calling semget(), if a
 * process finds that the usage count is 1, it will set the value of
 * SYSVSEM_SEM to max_acquire.  This allows max_acquire to be set and
 * track the PHP code without having a global init routine or external
 * semaphore init code.  Except see the bug regarding a race condition
 * php_sysvsem_get().  Semaphore 2 (SYSVSEM_SETVAL) serializes the
 * calls to GETVAL SYSVSEM_USAGE and SETVAL SYSVSEM_SEM.  It can be
 * acquired only when it is zero.
 */

#define SYSVSEM_SEM	0
#define SYSVSEM_USAGE	1
#define SYSVSEM_SETVAL	2

int	php_sem_get(PHP_MUTEX *sem_ptr, const char *path_name)
{
	const char	*__function_name = "php_sem_get";
	int		max_acquire = 1, count, semid;
	key_t		sem_key;
	struct sembuf	sop[3];

	assert(sem_ptr);
	assert(path_name);

	sem_ptr->semid = -1;
	sem_ptr->count = 0;

	if (-1 == (sem_key = ftok(path_name, (int)'z')))
	{
		zbx_error("%s(): cannot create IPC key for path '%s': %s",
				__function_name, path_name, zbx_strerror(errno));
		return PHP_MUTEX_ERROR;
	}

	/* Get/create the semaphore.  Note that we rely on the semaphores
	 * being zeroed when they are created.  Despite the fact that
	 * the(?)  Linux semget() man page says they are not initialized,
	 * the kernel versions 2.0.x and 2.1.z do in fact zero them.
	 */

	if (-1 == (semid = semget(sem_key, 3, 0660 | IPC_CREAT)))
	{
		zbx_error("%s(): failed for key 0x%lx: %s", __function_name, sem_key, zbx_strerror(errno));
		return PHP_MUTEX_ERROR;
	}

	/* Find out how many processes are using this semaphore.  Note
	 * that on Linux (at least) there is a race condition here because
	 * semaphore undo on process exit is not atomic, so we could
	 * acquire SYSVSEM_SETVAL before a crashed process has decremented
	 * SYSVSEM_USAGE in which case count will be greater than it
	 * should be and we won't set max_acquire.  Fortunately this
	 * doesn't actually matter in practice.
	 */

	/* Wait for sem 1 to be zero . . . */

	sop[0].sem_num = SYSVSEM_SETVAL;
	sop[0].sem_op = 0;
	sop[0].sem_flg = 0;

	/* . . . and increment it so it becomes non-zero . . . */

	sop[1].sem_num = SYSVSEM_SETVAL;
	sop[1].sem_op = 1;
	sop[1].sem_flg = SEM_UNDO;

	/* . . . and increment the usage count. */

	sop[2].sem_num = SYSVSEM_USAGE;
	sop[2].sem_op = 1;
	sop[2].sem_flg = SEM_UNDO;

	while (-1 == semop(semid, sop, 3))
	{
		if (EINTR != errno)
		{
			zbx_error("%s(): failed acquiring SYSVSEM_SETVAL for key 0x%lx: %s",
					__function_name, sem_key, zbx_strerror(errno));
			break;
		}
	}

	/* Get the usage count. */
	if (-1 == (count = semctl(semid, SYSVSEM_USAGE, GETVAL, NULL)))
		zbx_error("%s(): failed for key 0x%lx: %s", __function_name, sem_key, zbx_strerror(errno));

	/* If we are the only user, then take this opportunity to set the max. */

	if (1 == count)
	{
		/* This is correct for Linux which has union semun. */
		union semun	semarg;

		semarg.val = max_acquire;
		if (-1 == semctl(semid, SYSVSEM_SEM, SETVAL, semarg))
			zbx_error("%s(): failed for key 0x%lx: %s", __function_name, sem_key, zbx_strerror(errno));
	}

	/* Set semaphore 1 back to zero. */

	sop[0].sem_num = SYSVSEM_SETVAL;
	sop[0].sem_op = -1;
	sop[0].sem_flg = SEM_UNDO;

	while (-1 == semop(semid, sop, 1))
	{
		if (EINTR != errno)
		{
			zbx_error("%s(): failed releasing SYSVSEM_SETVAL for key 0x%lx: %s",
					__function_name, sem_key, zbx_strerror(errno));
			break;
		}
	}

	sem_ptr->semid = semid;

	return PHP_MUTEX_OK;
}

static int	php_sysvsem_semop(PHP_MUTEX *sem_ptr, int acquire)
{
	struct sembuf sop;

	assert(sem_ptr);

	if (-1 == sem_ptr->semid)
		return PHP_MUTEX_OK;

	if (0 == acquire && 0 == sem_ptr->count)
	{
		zbx_error("SysV semaphore (id %d) is not currently acquired", sem_ptr->semid);
		return PHP_MUTEX_ERROR;
	}

	sop.sem_num = SYSVSEM_SEM;
	sop.sem_op = (acquire ? -1 : 1);
	sop.sem_flg = SEM_UNDO;

	while (-1 == semop(sem_ptr->semid, &sop, 1))
	{
		if (EINTR != errno)
		{
			zbx_error("php_sysvsem_semop(): failed to %s semaphore (id %d): %s",
					(acquire ? "acquire" : "release"), sem_ptr->semid, zbx_strerror(errno));
			return PHP_MUTEX_ERROR;
		}
	}

	sem_ptr->count -= (acquire ? -1 : 1);

	return PHP_MUTEX_OK;
}

int	php_sem_acquire(PHP_MUTEX *sem_ptr)
{
	return php_sysvsem_semop(sem_ptr, 1);
}

int	php_sem_release(PHP_MUTEX *sem_ptr)
{
	return php_sysvsem_semop(sem_ptr, 0);
}

int	php_sem_remove(PHP_MUTEX *sem_ptr)
{
	const char	*__function_name = "php_sem_remove";
	union semun	un;
	struct semid_ds	buf;
	struct sembuf	sop[2];
	int		opcnt = 1;

	assert(sem_ptr);

	if (-1 == sem_ptr->semid)
		return PHP_MUTEX_OK;

	/* Decrement the usage count. */

	sop[0].sem_num = SYSVSEM_USAGE;
	sop[0].sem_op = -1;
	sop[0].sem_flg = SEM_UNDO;

	if (sem_ptr->count)
	{
		sop[1].sem_num = SYSVSEM_SEM;
		sop[1].sem_op = sem_ptr->count;
		sop[1].sem_flg = SEM_UNDO;

		opcnt++;
	}

	if (-1 == semop(sem_ptr->semid, sop, opcnt))
	{
		zbx_error("%s(): failed for (id %d): %s", __function_name, sem_ptr->semid, zbx_strerror(errno));
		return PHP_MUTEX_ERROR;
	}

	un.buf = &buf;
	if (-1 == semctl(sem_ptr->semid, 0, IPC_STAT, un))
	{
		zbx_error("%s(): SysV semaphore (id %d) does not (any longer) exist", __function_name, sem_ptr->semid);
		return PHP_MUTEX_ERROR;
	}

	if (-1 == semctl(sem_ptr->semid, 0, IPC_RMID, un))
		return PHP_MUTEX_ERROR;

	sem_ptr->semid = -1;

	return PHP_MUTEX_OK;
}

#endif	/* HAVE_SQLITE3 */
