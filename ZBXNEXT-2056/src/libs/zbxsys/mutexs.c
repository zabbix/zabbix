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
 * Return value: If the function succeeds, then return SUCCEED,               *
 *               FAIL on an error                                             *
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
		return FAIL;
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
			return FAIL;
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
				return FAIL;

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
				exit(EXIT_FAILURE);
			}

			/* semaphore is successfully removed */
			ZBX_SEM_LIST_ID = -1;

			if (ZBX_MAX_ATTEMPTS < ++attempts)
			{
				zbx_error("cannot recreate Zabbix semaphores for IPC key 0x%lx: too many attempts",
						sem_key);
				exit(EXIT_FAILURE);
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
		return FAIL;
	}
	else
	{
		zbx_error("cannot create Semaphore: %s", zbx_strerror(errno));
		return FAIL;
	}
lbl_return:
	*mutex = name;
	mutexes++;

#endif	/* _WINDOWS */

	return SUCCEED;
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
		exit(EXIT_FAILURE);
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
			exit(EXIT_FAILURE);
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
		exit(EXIT_FAILURE);
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
			exit(EXIT_FAILURE);
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
		return SUCCEED;

	if (0 == CloseHandle(*mutex))
	{
		zbx_error("error on mutex destroying: %s", strerror_from_system(GetLastError()));
		return FAIL;
	}
#else
	if (0 == --mutexes)
		semctl(ZBX_SEM_LIST_ID, 0, IPC_RMID, 0);
#endif

	*mutex = ZBX_MUTEX_NULL;

	return SUCCEED;
}
