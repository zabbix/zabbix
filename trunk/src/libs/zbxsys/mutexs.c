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

#include "common.h"
#include "log.h"
#include "mutexs.h"

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
 * Function: zbx_mutex_create                                                 *
 *                                                                            *
 * Purpose: Create the mutex                                                  *
 *                                                                            *
 * Parameters:  mutex - handle of mutex                                       *
 *              name - name of mutex (index for nix system)                   *
 *                                                                            *
 * Return value: If the function succeeds, then return SUCCEED,               *
 *               FAIL on an error                                             *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_mutex_create(ZBX_MUTEX *mutex, ZBX_MUTEX_NAME name, char **error)
{
#ifdef _WINDOWS
	if (NULL == (*mutex = CreateMutex(NULL, FALSE, name)))
	{
		*error = zbx_dsprintf(*error, "error on mutex creating: %s", strerror_from_system(GetLastError()));
		return FAIL;
	}
#else
	if (-1 == ZBX_SEM_LIST_ID)
	{
		union semun	semopts;
		int		i;

		if (-1 == (ZBX_SEM_LIST_ID = semget(IPC_PRIVATE, ZBX_MUTEX_COUNT, 0600)))
		{
			*error = zbx_dsprintf(*error, "cannot create semaphore set: %s", zbx_strerror(errno));
			return FAIL;
		}

		/* set default semaphore value */

		semopts.val = 1;
		for (i = 0; ZBX_MUTEX_COUNT > i; i++)
		{
			if (-1 != semctl(ZBX_SEM_LIST_ID, i, SETVAL, semopts))
				continue;

			*error = zbx_dsprintf(*error, "cannot initialize semaphore: %s", zbx_strerror(errno));

			if (-1 == semctl(ZBX_SEM_LIST_ID, 0, IPC_RMID, 0))
				zbx_error("cannot remove semaphore set %d: %s", ZBX_SEM_LIST_ID, zbx_strerror(errno));

			ZBX_SEM_LIST_ID = -1;

			return FAIL;
		}
	}

	*mutex = name;
	mutexes++;
#endif
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
#else
	DWORD   dwWaitResult;
#endif

	if (ZBX_MUTEX_NULL == *mutex)
		return;

#ifdef _WINDOWS
	dwWaitResult = WaitForSingleObject(*mutex, INFINITE);

	switch (dwWaitResult)
	{
		case WAIT_OBJECT_0:
			break;
		case WAIT_ABANDONED:
			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
		default:
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
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_mutex_destroy(ZBX_MUTEX *mutex)
{
#ifdef _WINDOWS
	if (ZBX_MUTEX_NULL == *mutex)
		return;

	if (0 == CloseHandle(*mutex))
		zbx_error("error on mutex destroying: %s", strerror_from_system(GetLastError()));
#else
	if (0 == --mutexes && -1 == semctl(ZBX_SEM_LIST_ID, 0, IPC_RMID, 0))
		zbx_error("cannot remove semaphore set %d: %s", ZBX_SEM_LIST_ID, zbx_strerror(errno));
#endif
	*mutex = ZBX_MUTEX_NULL;
}

#ifdef _WINDOWS
/******************************************************************************
 *                                                                            *
 * Function: zbx_mutex_create_per_process_name                                *
 *                                                                            *
 * Purpose: Appends PID to the prefix of the mutex                            *
 *                                                                            *
 * Parameters: prefix - mutex type                                            *
 *                                                                            *
 * Return value: Dynamically allocated, NUL terminated name of the mutex      *
 *                                                                            *
 * Comments: The mutex name must be shorter than MAX_PATH characters,         *
 *           otherwise the function calls exit()                              *
 *                                                                            *
 ******************************************************************************/
ZBX_MUTEX_NAME  zbx_mutex_create_per_process_name(const ZBX_MUTEX_NAME prefix)
{
	ZBX_MUTEX_NAME	name = ZBX_MUTEX_NULL;
	int		size;
	wchar_t		*format = L"%s_PID_%lx";
	DWORD		pid = GetCurrentProcessId();

	/* exit if the mutex name length exceed the maximum allowed */
	size = _scwprintf(format, prefix, pid);
	if (MAX_PATH < size)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}

	size = size + 1; /* for terminating '\0' */

	name = zbx_malloc(NULL, sizeof(wchar_t) * size);
	(void)_snwprintf_s(name, size, size - 1, format, prefix, pid);
	name[size - 1] = L'\0';

	return name;
}
#endif
