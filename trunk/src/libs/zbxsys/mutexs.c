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

#include "common.h"
#include "mutexs.h"

#if !defined(_WINDOWS)

#	if !defined(semun)
		union semun
		{
			int val;			/* <= value for SETVAL */
			struct semid_ds *buf;		/* <= buffer for IPC_STAT & IPC_SET */
			unsigned short int *array;	/* <= array for GETALL & SETALL */
			struct seminfo *__buf;		/* <= buffer for IPC_INFO */
		};
#	endif /* semun */

#	include "cfg.h"
#	include "threads.h"

	static int	ZBX_SEM_LIST_ID = -1;

#endif /* not _WINDOWS */

#include "log.h"

/******************************************************************************
 *                                                                            *
 * Function: zbx_mutex_create                                                 *
 *                                                                            *
 * Purpose: Create the mutex                                                  *
 *                                                                            *
 * Parameters:  mutex - handle of mutex                                       *
 *                                                                            *
 * Return value: If the function succeeds, the return ZBX_MUTEX_OK,           *
 *               ZBX_MUTEX_ERROR on an error                                  *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int zbx_mutex_create(ZBX_MUTEX *mutex, ZBX_MUTEX_NAME name)
{
#if defined(_WINDOWS)	

	if(NULL == ((*mutex) = CreateMutex(NULL, FALSE, name)))
	{
		zbx_error("Error on mutex creating. [%s]", strerror_from_system(GetLastError()));
		return ZBX_MUTEX_ERROR;
	}

	/* NOTE: if(ERROR_ALREADY_EXISTS == GetLastError()) info("Successfully opened existed mutex!"); */

#else /* not _WINDOWS */
	int	i;
	key_t	sem_key;
	union semun semopts;
	struct semid_ds seminfo;

	if( -1 == (sem_key = ftok(CONFIG_FILE, (int)'z') ))
	{
		zbx_error("Can not create IPC key for path '%s', try to create for path '.' [%s]", CONFIG_FILE, strerror(errno));
		if( -1 == (sem_key = ftok(".", (int)'z') ))
		{
			zbx_error("Can not create IPC key for path '.' [%s]", strerror(errno));
			return ZBX_MUTEX_ERROR;
		}
	}			

	if ( -1 != (ZBX_SEM_LIST_ID = semget(sem_key, ZBX_MUTEX_COUNT, IPC_CREAT | IPC_EXCL | 0666 /* 0022 */)) )
	{
		/* set default semaphore value */
		semopts.val = 1;
		
		for ( i = 0; i < ZBX_MUTEX_COUNT; i++ )
		{
			if(-1 == semctl(ZBX_SEM_LIST_ID, i, SETVAL, semopts))
			{
				zbx_error("Semaphore [%i] error in semctl(SETVAL)", name);
				return ZBX_MUTEX_ERROR;
			}

			zbx_mutex_lock(&i);	/* call semop to update sem_otime */
			zbx_mutex_unlock(&i);	/* release semaphore */
		}
	}
	else if(errno == EEXIST)
	{
		ZBX_SEM_LIST_ID = semget(sem_key, ZBX_MUTEX_COUNT, 0666 /* 0022 */);
		semopts.buf = &seminfo;
		
		/* wait for initialization */
		for ( i = 0; i < ZBX_MUTEX_MAX_TRIES; i++)
		{
			if( -1 == semctl(ZBX_SEM_LIST_ID, 0, IPC_STAT, semopts))
			{
				zbx_error("Semaphore [%i] error in semctl(IPC_STAT)", name);
				break;
			}
			if(semopts.buf->sem_otime !=0 ) goto lbl_return;
			zbx_sleep(1);
		}
		
		zbx_error("Semaphore [%i] not initialized", name);
		return ZBX_MUTEX_ERROR;
	}
	else
	{
		zbx_error("Can not create Semaphore [%s]", strerror(errno));
		return ZBX_MUTEX_ERROR;
	}
	
lbl_return:

	*mutex = name;
	
#endif /* _WINDOWS */

	return ZBX_MUTEX_OK;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_mutex_lock                                                   *
 *                                                                            *
 * Purpose: Waits until the mutex is in the signaled state                    *
 *                                                                            *
 * Parameters: mutex - handle of mutex                                        *
 *                                                                            *
 * Return value: If the function succeeds, the return 1, 0 on an error        *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/

int zbx_mutex_lock(ZBX_MUTEX *mutex)
{
#if defined(_WINDOWS)	

	if(!*mutex) return ZBX_MUTEX_OK;
	
	if(WaitForSingleObject(*mutex, INFINITE) != WAIT_OBJECT_0)
	{
		zbx_error("Error on mutex locking. [%s]", strerror_from_system(GetLastError()));
		return ZBX_MUTEX_ERROR;
	}

#else /* not _WINDOWS */

	struct sembuf sem_lock = { *mutex, -1, 0 };

	if(!*mutex) return ZBX_MUTEX_OK;
	
	if (-1 == (semop(ZBX_SEM_LIST_ID, &sem_lock, 1)))
	{
		zbx_error("Lock failed [%s]", strerror(errno));
		return ZBX_MUTEX_ERROR;
	}
	
#endif /* _WINDOWS */

	return ZBX_MUTEX_OK;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_mutex_unlock                                                 *
 *                                                                            *
 * Purpose: Unlock the mutex                                                  *
 *                                                                            *
 * Parameters: mutex - handle of mutex                                        *
 *                                                                            *
 * Return value: If the function succeeds, the return 1, 0 on an error        *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/

int zbx_mutex_unlock(ZBX_MUTEX *mutex)
{
#if defined(_WINDOWS)	

	if(!*mutex) return ZBX_MUTEX_OK;

	if(ReleaseMutex(*mutex) == 0)
	{
		zbx_error("Error on mutex UNlocking. [%s]", strerror_from_system(GetLastError()));
		return ZBX_MUTEX_ERROR;
	}

#else /* not _WINDOWS */

	struct sembuf sem_unlock = { *mutex, 1, 0};

	if(!*mutex) return ZBX_MUTEX_OK;

	if ((semop(ZBX_SEM_LIST_ID, &sem_unlock, 1)) == -1)
	{
		zbx_error("Unlock failed [%s]", strerror(errno));
		return ZBX_MUTEX_ERROR;
	}
	
#endif /* _WINDOWS */

	return ZBX_MUTEX_OK;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_mutex_destroy                                                *
 *                                                                            *
 * Purpose: Destroy the mutex                                                 *
 *                                                                            *
 * Parameters: mutex - handle of mutex                                        *
 *                                                                            *
 * Return value: If the function succeeds, the return 1, 0 on an error        *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/

int zbx_mutex_destroy(ZBX_MUTEX *mutex)
{
	
#if defined(_WINDOWS)	

	if(!*mutex) return ZBX_MUTEX_OK;

	if(CloseHandle(*mutex) == 0)
	{
		zbx_error("Error on mutex destroying. [%s]", strerror_from_system(GetLastError()));
		return ZBX_MUTEX_ERROR;
	}

#else /* not _WINDOWS */
	
	if(!*mutex) return ZBX_MUTEX_OK;

	semctl(ZBX_SEM_LIST_ID, 0, IPC_RMID, 0);

#endif /* _WINDOWS */
	
	*mutex = (ZBX_MUTEX)NULL;

	return ZBX_MUTEX_OK;
}

