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

#include "threads.h"

/******************************************************************************
 *                                                                            *
 * Function: zbx_thread_start                                                 *
 *                                                                            *
 * Purpose: Start the handled function as "thread"                            *
 *                                                                            *
 * Parameters: "thread" handle                                                *
 *                                                                            *
 * Return value: returns a handle to the newly created "thread",              *
 *               ZBX_THREAD_ERROR on an error                                 *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/

ZBX_THREAD_HANDLE zbx_thread_start(ZBX_THREAD_ENTRY_POINTER(handler), void *args)
{
	ZBX_THREAD_HANDLE thread = 0;

#if defined(WIN32)

	unsigned thrdaddr;

	if(_beginthreadex(NULL,0,handler,args,0,&thrdaddr) == 0)
	{
		thread = (ZBX_THREAD_HANDLE)(ZBX_THREAD_ERROR);
	}
	else
	{
		thread = (ZBX_THREAD_HANDLE)(thrdaddr);
	}

#else /* not WIN32 */

	thread = fork();

	if(thread == 0) /* child process */
	{
		(*handler)(args);

		/* The ZBX_TREAD_EXIT must be called from the handler */
		/* And in normal case the program will never reach this point. */
		ZBX_TREAD_EXIT(0);
		/* Program will never reach this point. */
	} 
	else if(thread < 0)
	{
		thread = (ZBX_THREAD_HANDLE)(ZBX_THREAD_ERROR);
	}

#endif /* WIN32 */

	return (ZBX_THREAD_HANDLE)(thread);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_thread_wait                                                  *
 *                                                                            *
 * Purpose: Waits until the "thread" is in the signaled state                 *
 *                                                                            *
 * Parameters: "thread" handle                                                *
 *                                                                            *
 * Return value: If the function succeeds, the return 1,0 on an error         *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/

int zbx_thread_wait(ZBX_THREAD_HANDLE thread)
{
#if defined(WIN32)	

	if(WaitForSingleObject(thread, INFINITE) != WAIT_OBJECT_0)
		return (0);

	if(CloseHandle(thread) == 0)
		return (0);

#else /* not WIN32 */

	if(waitpid(thread, (int *)0, 0) <= 0)
	{
		return (0);
	}

#endif /* WIN32 */

	return (1);
}


/******************************************************************************
 *                                                                            *
 * Function: zbx_semaphore_create                                             *
 *                                                                            *
 * Purpose: Create the "semaphore"                                            *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: Returns a handle to the newly created "semaphore",           *
 *               ZBX_SEM_ERROR on an error                                    *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/

ZBX_SEM_HANDLE zbx_semaphore_create(void)
{
	ZBX_SEM_HANDLE semaphore = 0;
#if defined(WIN32)	

	semaphore = CreateSemaphore(
		NULL,        // no security attributes
		0,           // initial count
		1,   // maximum count
		NULL);

	if(semaphore == 0)  
		semaphore = ZBX_SEM_ERROR;

#else /* not WIN32 */

	semaphore = sem_init(
		sem,   // handle to the event semaphore
		0,     // not shared
		0);    // initially set to non signaled state

	if(semaphore < 0)
		semaphore = ZBX_SEM_ERROR;

#endif /* WIN32 */

	return semaphore;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_semaphore_wait                                               *
 *                                                                            *
 * Purpose: Waits until the "semaphore" is in the signaled state              *
 *                                                                            *
 * Parameters: "semaphore" handle                                             *
 *                                                                            *
 * Return value: If the function succeeds, the return 1, 0 on an error        *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/

int zbx_semaphore_wait(ZBX_SEM_HANDLE *semaphore)
{
#if defined(WIN32)	

	if(WaitForSingleObject(*semaphore, INFINITE) != WAIT_OBJECT_0)
		return (0);

#else /* not WIN32 */

	if(sem_wait(semaphore) < 0)
		return (0);

#endif /* WIN32 */

	return (1);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_semaphore_destr                                              *
 *                                                                            *
 * Purpose: Destroy the "semaphore"                                           *
 *                                                                            *
 * Parameters: "semaphore" handle                                             *
 *                                                                            *
 * Return value: If the function succeeds, the return 1, 0 on an error        *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/

int zbx_semaphore_destr(ZBX_SEM_HANDLE *semaphore)
{
#if defined(WIN32)	

	if(CloseHandle(*semaphore) == 0)
		return (0);

#else /* not WIN32 */

	if(sem_destroy(semaphore) < 0)
		return (0);

#endif /* WIN32 */

	return (1);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_semaphore_unloc                                              *
 *                                                                            *
 * Purpose: Unlock the "semaphore"                                            *
 *                                                                            *
 * Parameters: "semaphore" handle                                             *
 *                                                                            *
 * Return value: If the function succeeds, the return 1, 0 on an error        *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/

int zbx_semaphore_unloc(ZBX_SEM_HANDLE *semaphore)
{
#if defined(WIN32)	

	if(SetEvent(*semaphore) == 0)
		return (0);

#else /* not WIN32 */

	if(sem_post(semaphore) < 0)
		return (0);

#endif /* WIN32 */

	return (1);
}