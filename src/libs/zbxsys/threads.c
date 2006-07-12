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

#include "log.h"

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
 *          The zbx_tread_exit must be called from the handler!               *
 *                                                                            *
 ******************************************************************************/

ZBX_THREAD_HANDLE zbx_thread_start(ZBX_THREAD_ENTRY_POINTER(handler), void *args)
{
	ZBX_THREAD_HANDLE thread = 0;

#if defined(WIN32)

	unsigned thrdaddr;

	if(0 == (thread = (ZBX_THREAD_HANDLE)_beginthreadex(NULL,0,handler,args,0,&thrdaddr))) /* NOTE: _beginthreadex returns 0 on failure, rather than –1 */
	{
		zbx_error("Error on thread creation. [%s]", strerror_from_system(GetLastError()));
		thread = (ZBX_THREAD_HANDLE)(ZBX_THREAD_ERROR);
	}

#else /* not WIN32 */

	thread = fork();

	if(thread == 0) /* child process */
	{
		(*handler)(args);

		/* The zbx_tread_exit must be called from the handler */
		/* And in normal case the program will never reach this point. */
		zbx_tread_exit(0);
		/* Program will never reach this point. */
	} 
	else if(thread < 0)
	{
		zbx_error("Error on thread creation.");
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
	{
		zbx_error("Error on thread waiting. [%s]", strerror_from_system(GetLastError()));
		return (0);
	}

	if(CloseHandle(thread) == 0)
	{
		zbx_error("Error on thread closing. [%s]", strerror_from_system(GetLastError()));
		return (0);
	}

#else /* not WIN32 */

	if(waitpid(thread, (int *)0, 0) <= 0)
	{
		zbx_error("Error on thread waiting.");
		return (0);
	}

#endif /* WIN32 */

	return (1);
}

long int zbx_get_thread_id(void)
{
#if defined(WIN32)	

	return (long int) GetCurrentThreadId();

#else /* not WIN32 */

	return (long int) getpid();

#endif /* WIN32 */
}

