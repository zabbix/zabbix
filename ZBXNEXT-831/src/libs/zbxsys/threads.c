/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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

#include "log.h" /* required for strerror_from_system() on Windows */

/******************************************************************************
 *                                                                            *
 * Function: zbx_fork                                                         *
 *                                                                            *
 * Purpose: Flush stdout and stderr before forking                            *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: same as system fork function                                 *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *          Use this function instead of system fork function!                *
 *                                                                            *
 ******************************************************************************/
#if !defined(_WINDOWS)
int	zbx_fork()
{
	fflush(stdout);
	fflush(stderr);
	return fork();
}
#endif

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
 *          The zbx_thread_exit must be called from the handler!              *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_HANDLE	zbx_thread_start(ZBX_THREAD_ENTRY_POINTER(handler), zbx_thread_args_t *thread_args)
{
	ZBX_THREAD_HANDLE	thread = ZBX_THREAD_HANDLE_NULL;

#ifdef _WINDOWS
	unsigned	thrdaddr;

	/* NOTE: _beginthreadex returns 0 on failure, rather than 1 */
	if (0 == (thread = (ZBX_THREAD_HANDLE)_beginthreadex(NULL, 0, handler, thread_args, 0, &thrdaddr)))
	{
		zbx_error("failed to create a thread: %s", strerror_from_system(GetLastError()));
		thread = (ZBX_THREAD_HANDLE)ZBX_THREAD_ERROR;
	}

#else

	if (0 == (thread = zbx_fork()))	/* child process */
	{
		(*handler)(thread_args);

		/* The zbx_thread_exit must be called from the handler. */
		/* And in normal case the program will never reach this point. */
		zbx_thread_exit(0);
		/* program will never reach this point */
	}
	else if (-1 == thread)
	{
		zbx_error("failed to fork: %s", zbx_strerror(errno));
		thread = (ZBX_THREAD_HANDLE)ZBX_THREAD_ERROR;
	}
#endif

	return thread;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_thread_wait                                                  *
 *                                                                            *
 * Purpose: Waits until the "thread" is in the signalled state                *
 *                                                                            *
 * Parameters: "thread" handle                                                *
 *                                                                            *
 * Return value: process or thread exit code                                  *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_thread_wait(ZBX_THREAD_HANDLE thread)
{
	int	status = 0; /* significant 8 bits of the status */

#ifdef _WINDOWS

	if (WAIT_OBJECT_0 != WaitForSingleObject(thread, INFINITE))
	{
		zbx_error("Error on thread waiting. [%s]", strerror_from_system(GetLastError()));
		return ZBX_THREAD_ERROR;
	}

	if (0 == GetExitCodeThread(thread, &status))
	{
		zbx_error("Error on thread exit code receiving. [%s]", strerror_from_system(GetLastError()));
		return ZBX_THREAD_ERROR;
	}

	if (0 == CloseHandle(thread))
	{
		zbx_error("Error on thread closing. [%s]", strerror_from_system(GetLastError()));
		return ZBX_THREAD_ERROR;
	}

#else	/* not _WINDOWS */

	if (0 >= waitpid(thread, &status, 0))
	{
		zbx_error("Error on thread waiting.");
		return ZBX_THREAD_ERROR;
	}

	status = WEXITSTATUS(status);

#endif	/* _WINDOWS */

	return status;
}

long int	zbx_get_thread_id()
{
#ifdef _WINDOWS
	return (long int)GetCurrentThreadId();
#else
	return (long int)getpid();
#endif
}
