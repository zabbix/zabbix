/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "zbxmutexs.h"

#include "log.h"

#ifdef _WINDOWS
#	include "sysinfo.h"
#else
#ifdef HAVE_PTHREAD_PROCESS_SHARED
typedef struct
{
	pthread_mutex_t		mutexes[ZBX_MUTEX_COUNT];
	pthread_rwlock_t	rwlocks[ZBX_RWLOCK_COUNT];
}
zbx_shared_lock_t;

static zbx_shared_lock_t	*shared_lock;
static int			shm_id, locks_disabled;
#else
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
#	include "zbxthreads.h"

	static int		ZBX_SEM_LIST_ID = -1;
	static unsigned char	mutexes;
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: if pthread mutexes and read-write locks can be shared between     *
 *          processes then create them, otherwise fallback to System V        *
 *          semaphore operations                                              *
 *                                                                            *
 * Parameters: error - dynamically allocated memory with error message.       *
 *                                                                            *
 * Return value: SUCCEED if mutexes successfully created, otherwise FAIL      *
 *                                                                            *
 ******************************************************************************/
int	zbx_locks_create(char **error)
{
#ifdef HAVE_PTHREAD_PROCESS_SHARED
	int			i;
	pthread_mutexattr_t	mta;
	pthread_rwlockattr_t	rwa;

	if (-1 == (shm_id = shmget(IPC_PRIVATE, ZBX_SIZE_T_ALIGN8(sizeof(zbx_shared_lock_t)),
			IPC_CREAT | IPC_EXCL | 0600)))
	{
		*error = zbx_dsprintf(*error, "cannot allocate shared memory for locks");
		return FAIL;
	}

	if ((void *)(-1) == (shared_lock = (zbx_shared_lock_t *)shmat(shm_id, NULL, 0)))
	{
		*error = zbx_dsprintf(*error, "cannot attach shared memory for locks: %s", zbx_strerror(errno));
		return FAIL;
	}

	memset(shared_lock, 0, sizeof(zbx_shared_lock_t));

	/* immediately mark the new shared memory for destruction after attaching to it */
	if (-1 == shmctl(shm_id, IPC_RMID, 0))
	{
		*error = zbx_dsprintf(*error, "cannot mark the new shared memory for destruction: %s",
				zbx_strerror(errno));
		return FAIL;
	}

	if (0 != pthread_mutexattr_init(&mta))
	{
		*error = zbx_dsprintf(*error, "cannot initialize mutex attribute: %s", zbx_strerror(errno));
		return FAIL;
	}

	if (0 != pthread_mutexattr_setpshared(&mta, PTHREAD_PROCESS_SHARED))
	{
		*error = zbx_dsprintf(*error, "cannot set shared mutex attribute: %s", zbx_strerror(errno));
		return FAIL;
	}

	for (i = 0; i < ZBX_MUTEX_COUNT; i++)
	{
		if (0 != pthread_mutex_init(&shared_lock->mutexes[i], &mta))
		{
			*error = zbx_dsprintf(*error, "cannot create mutex: %s", zbx_strerror(errno));
			return FAIL;
		}
	}

	if (0 != pthread_rwlockattr_init(&rwa))
	{
		*error = zbx_dsprintf(*error, "cannot initialize read write lock attribute: %s", zbx_strerror(errno));
		return FAIL;
	}

	if (0 != pthread_rwlockattr_setpshared(&rwa, PTHREAD_PROCESS_SHARED))
	{
		*error = zbx_dsprintf(*error, "cannot set shared read write lock attribute: %s", zbx_strerror(errno));
		return FAIL;
	}

	for (i = 0; i < ZBX_RWLOCK_COUNT; i++)
	{
		if (0 != pthread_rwlock_init(&shared_lock->rwlocks[i], &rwa))
		{
			*error = zbx_dsprintf(*error, "cannot create rwlock: %s", zbx_strerror(errno));
			return FAIL;
		}
	}
#else
	union semun	semopts;
	int		i;

	if (-1 == (ZBX_SEM_LIST_ID = semget(IPC_PRIVATE, ZBX_MUTEX_COUNT + ZBX_RWLOCK_COUNT, 0600)))
	{
		*error = zbx_dsprintf(*error, "cannot create semaphore set: %s", zbx_strerror(errno));
		return FAIL;
	}

	/* set default semaphore value */

	semopts.val = 1;
	for (i = 0; ZBX_MUTEX_COUNT + ZBX_RWLOCK_COUNT > i; i++)
	{
		if (-1 != semctl(ZBX_SEM_LIST_ID, i, SETVAL, semopts))
			continue;

		*error = zbx_dsprintf(*error, "cannot initialize semaphore: %s", zbx_strerror(errno));

		if (-1 == semctl(ZBX_SEM_LIST_ID, 0, IPC_RMID, 0))
			zbx_error("cannot remove semaphore set %d: %s", ZBX_SEM_LIST_ID, zbx_strerror(errno));

		ZBX_SEM_LIST_ID = -1;

		return FAIL;
	}
#endif
	return SUCCEED;
}

void	zbx_locks_destroy(void)
{
#ifdef HAVE_PTHREAD_PROCESS_SHARED
	int	i;

	if (NULL == shared_lock)
		return;

	for (i = 0; i < ZBX_MUTEX_COUNT; i++)
		(void)pthread_mutex_destroy(&shared_lock->mutexes[i]);

	for (i = 0; i < ZBX_RWLOCK_COUNT; i++)
		(void)pthread_rwlock_destroy(&shared_lock->rwlocks[i]);

	shmdt(shared_lock);
	shared_lock = NULL;
	shm_id = 0;
#else
	(void)semctl(ZBX_SEM_LIST_ID, 0, IPC_RMID, 0);
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: acquire address of the mutex                                      *
 *                                                                            *
 * Parameters: mutex_name - name of the mutex to return address for           *
 *                                                                            *
 * Return value: address of the mutex                                         *
 *                                                                            *
 ******************************************************************************/
zbx_mutex_t	zbx_mutex_addr_get(zbx_mutex_name_t mutex_name)
{
#ifdef HAVE_PTHREAD_PROCESS_SHARED
	return &shared_lock->mutexes[mutex_name];
#else
	return mutex_name;
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: acquire address of the rwlock                                     *
 *                                                                            *
 * Parameters: rwlock_name - name of the rwlock to return address for         *
 *                                                                            *
 * Return value: address of the rwlock                                        *
 *                                                                            *
 ******************************************************************************/
zbx_rwlock_t	zbx_rwlock_addr_get(zbx_rwlock_name_t rwlock_name)
{
#ifdef HAVE_PTHREAD_PROCESS_SHARED
	return &shared_lock->rwlocks[rwlock_name];
#else
	return rwlock_name + ZBX_MUTEX_COUNT;
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: read-write locks are created using zbx_locks_create() function    *
 *          this is only to obtain handle, if read write locks are not        *
 *          supported, then outputs numeric handle of mutex that can be used  *
 *          with mutex handling functions                                     *
 *                                                                            *
 * Parameters:  rwlock - read-write lock handle if supported, otherwise mutex *
 *              name - name of read-write lock (index for nix system)         *
 *              error - unused                                                *
 *                                                                            *
 * Return value: SUCCEED if mutexes successfully created, otherwise FAIL      *
 *                                                                            *
 ******************************************************************************/
int	zbx_rwlock_create(zbx_rwlock_t *rwlock, zbx_rwlock_name_t name, char **error)
{
	ZBX_UNUSED(error);
#ifdef HAVE_PTHREAD_PROCESS_SHARED
	*rwlock = &shared_lock->rwlocks[name];
#else
	*rwlock = name + ZBX_MUTEX_COUNT;
	mutexes++;
#endif
	return SUCCEED;
}
#ifdef HAVE_PTHREAD_PROCESS_SHARED
/******************************************************************************
 *                                                                            *
 * Purpose: acquire write lock for read-write lock (exclusive access)         *
 *                                                                            *
 * Parameters: rwlock - handle of read-write lock                             *
 *                                                                            *
 ******************************************************************************/
void	__zbx_rwlock_wrlock(const char *filename, int line, zbx_rwlock_t rwlock)
{
	if (ZBX_RWLOCK_NULL == rwlock)
		return;

	if (0 != locks_disabled)
		return;

	if (0 != pthread_rwlock_wrlock(rwlock))
	{
		zbx_error("[file:'%s',line:%d] write lock failed: %s", filename, line, zbx_strerror(errno));
		exit(EXIT_FAILURE);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: acquire read lock for read-write lock (there can be many readers) *
 *                                                                            *
 * Parameters: rwlock - handle of read-write lock                             *
 *                                                                            *
 ******************************************************************************/
void	__zbx_rwlock_rdlock(const char *filename, int line, zbx_rwlock_t rwlock)
{
	if (ZBX_RWLOCK_NULL == rwlock)
		return;

	if (0 != locks_disabled)
		return;

	if (0 != pthread_rwlock_rdlock(rwlock))
	{
		zbx_error("[file:'%s',line:%d] read lock failed: %s", filename, line, zbx_strerror(errno));
		exit(EXIT_FAILURE);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: unlock read-write lock                                            *
 *                                                                            *
 * Parameters: rwlock - handle of read-write lock                             *
 *                                                                            *
 ******************************************************************************/
void	__zbx_rwlock_unlock(const char *filename, int line, zbx_rwlock_t rwlock)
{
	if (ZBX_RWLOCK_NULL == rwlock)
		return;

	if (0 != locks_disabled)
		return;

	if (0 != pthread_rwlock_unlock(rwlock))
	{
		zbx_error("[file:'%s',line:%d] read-write lock unlock failed: %s", filename, line, zbx_strerror(errno));
		exit(EXIT_FAILURE);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: Destroy read-write lock                                           *
 *                                                                            *
 * Parameters: rwlock - handle of read-write lock                             *
 *                                                                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_rwlock_destroy(zbx_rwlock_t *rwlock)
{
	if (ZBX_RWLOCK_NULL == *rwlock)
		return;

	*rwlock = ZBX_RWLOCK_NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose:  disable locks                                                    *
 *                                                                            *
 ******************************************************************************/
void	zbx_locks_disable(void)
{
	/* attempting to destroy a locked pthread mutex results in undefined behavior */
	locks_disabled = 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose:  enable locks                                                     *
 *                                                                            *
 ******************************************************************************/
void	zbx_locks_enable(void)
{
	/* attempting to destroy a locked pthread mutex results in undefined behavior */
	locks_disabled = 0;
}

#endif
#endif	/* _WINDOWS */

/******************************************************************************
 *                                                                            *
 * Purpose: Create the mutex                                                  *
 *                                                                            *
 * Parameters:  mutex - handle of mutex                                       *
 *              name - name of mutex (index for nix system)                   *
 *                                                                            *
 * Return value: If the function succeeds, then return SUCCEED,               *
 *               FAIL on an error                                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_mutex_create(zbx_mutex_t *mutex, zbx_mutex_name_t name, char **error)
{
#ifdef _WINDOWS
	if (NULL == (*mutex = CreateMutex(NULL, FALSE, name)))
	{
		*error = zbx_dsprintf(*error, "error on mutex creating: %s", strerror_from_system(GetLastError()));
		return FAIL;
	}
#else
	ZBX_UNUSED(error);
#ifdef	HAVE_PTHREAD_PROCESS_SHARED
	*mutex = &shared_lock->mutexes[name];
#else
	mutexes++;
	*mutex = name;
#endif
#endif
	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Waits until the mutex is in the signalled state                   *
 *                                                                            *
 * Parameters: mutex - handle of mutex                                        *
 *                                                                            *
 ******************************************************************************/
void	__zbx_mutex_lock(const char *filename, int line, zbx_mutex_t mutex)
{
#ifndef _WINDOWS
#ifndef	HAVE_PTHREAD_PROCESS_SHARED
	struct sembuf	sem_lock;
#endif
#else
	DWORD   dwWaitResult;
#endif

	if (ZBX_MUTEX_NULL == mutex)
		return;

#ifdef _WINDOWS
#ifdef ZABBIX_AGENT
	if (0 != (ZBX_MUTEX_THREAD_DENIED & get_thread_global_mutex_flag()))
	{
		zbx_error("[file:'%s',line:%d] lock failed: ZBX_MUTEX_THREAD_DENIED is set for thread with id = %d",
				filename, line, zbx_get_thread_id());
		exit(EXIT_FAILURE);
	}
#endif
	dwWaitResult = WaitForSingleObject(mutex, INFINITE);

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
#ifdef	HAVE_PTHREAD_PROCESS_SHARED
	if (0 != locks_disabled)
		return;

	if (0 != pthread_mutex_lock(mutex))
	{
		zbx_error("[file:'%s',line:%d] lock failed: %s", filename, line, zbx_strerror(errno));
		exit(EXIT_FAILURE);
	}
#else
	sem_lock.sem_num = mutex;
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
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: Unlock the mutex                                                  *
 *                                                                            *
 * Parameters: mutex - handle of mutex                                        *
 *                                                                            *
 ******************************************************************************/
void	__zbx_mutex_unlock(const char *filename, int line, zbx_mutex_t mutex)
{
#ifndef _WINDOWS
#ifndef	HAVE_PTHREAD_PROCESS_SHARED
	struct sembuf	sem_unlock;
#endif
#endif

	if (ZBX_MUTEX_NULL == mutex)
		return;

#ifdef _WINDOWS
	if (0 == ReleaseMutex(mutex))
	{
		zbx_error("[file:'%s',line:%d] unlock failed: %s",
				filename, line, strerror_from_system(GetLastError()));
		exit(EXIT_FAILURE);
	}
#else
#ifdef	HAVE_PTHREAD_PROCESS_SHARED
	if (0 != locks_disabled)
		return;

	if (0 != pthread_mutex_unlock(mutex))
	{
		zbx_error("[file:'%s',line:%d] unlock failed: %s", filename, line, zbx_strerror(errno));
		exit(EXIT_FAILURE);
	}
#else
	sem_unlock.sem_num = mutex;
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
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: Destroy the mutex                                                 *
 *                                                                            *
 * Parameters: mutex - handle of mutex                                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_mutex_destroy(zbx_mutex_t *mutex)
{
#ifdef _WINDOWS
	if (ZBX_MUTEX_NULL == *mutex)
		return;

	if (0 == CloseHandle(*mutex))
		zbx_error("error on mutex destroying: %s", strerror_from_system(GetLastError()));
#endif
	*mutex = ZBX_MUTEX_NULL;
}

#ifdef _WINDOWS
/******************************************************************************
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
zbx_mutex_name_t	zbx_mutex_create_per_process_name(const zbx_mutex_name_t prefix)
{
	zbx_mutex_name_t	name = ZBX_MUTEX_NULL;
	int			size;
	wchar_t			*format = L"%s_PID_%lx";
	DWORD			pid = GetCurrentProcessId();

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
