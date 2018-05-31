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
#include "threads.h"

#if !defined(_WINDOWS)
/******************************************************************************
 *                                                                            *
 * Function: zbx_fork                                                         *
 *                                                                            *
 * Purpose: Flush stdout and stderr before forking                            *
 *                                                                            *
 * Return value: same as system fork() function                               *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_fork(void)
{
	fflush(stdout);
	fflush(stderr);
	return fork();
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_child_fork                                                   *
 *                                                                            *
 * Purpose: fork from master process and set SIGCHLD handler                  *
 *                                                                            *
 * Return value: same as system fork() function                               *
 *                                                                            *
 * Author: Rudolfs Kreicbergs                                                 *
 *                                                                            *
 * Comments: use this function only for forks from the main process           *
 *                                                                            *
 ******************************************************************************/
int	zbx_child_fork(void)
{
	pid_t		pid;
	sigset_t	mask, orig_mask;

	/* block SIGTERM, SIGINT and SIGCHLD during fork to avoid deadlock (we've seen one in __unregister_atfork()) */
	sigemptyset(&mask);
	sigaddset(&mask, SIGTERM);
	sigaddset(&mask, SIGINT);
	sigaddset(&mask, SIGCHLD);
	sigprocmask(SIG_BLOCK, &mask, &orig_mask);

	pid = zbx_fork();

	sigprocmask(SIG_SETMASK, &orig_mask, NULL);

	/* ignore SIGCHLD to avoid problems with exiting scripts in zbx_execute() and other cases */
	if (0 == pid)
		signal(SIGCHLD, SIG_DFL);

	return pid;
}
#else
int	zbx_win_exception_filter(unsigned int code, struct _EXCEPTION_POINTERS *ep);

static ZBX_THREAD_ENTRY(zbx_win_thread_entry, args)
{
	__try
	{
		zbx_thread_args_t	*thread_args = (zbx_thread_args_t *)args;

		return thread_args->entry(thread_args);
	}
	__except(zbx_win_exception_filter(GetExceptionCode(), GetExceptionInformation()))
	{
		zbx_thread_exit(EXIT_SUCCESS);
	}
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
 * Comments: The zbx_thread_exit must be called from the handler!             *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_HANDLE	zbx_thread_start(ZBX_THREAD_ENTRY_POINTER(handler), zbx_thread_args_t *thread_args)
{
	ZBX_THREAD_HANDLE	thread = ZBX_THREAD_HANDLE_NULL;
#ifdef _WINDOWS
	unsigned		thrdaddr;

	thread_args->entry = handler;
	/* NOTE: _beginthreadex returns 0 on failure, rather than 1 */
	if (0 == (thread = (ZBX_THREAD_HANDLE)_beginthreadex(NULL, 0, zbx_win_thread_entry, thread_args, 0, &thrdaddr)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "failed to create a thread: %s", strerror_from_system(GetLastError()));
		thread = (ZBX_THREAD_HANDLE)ZBX_THREAD_ERROR;
	}
#else
	if (0 == (thread = zbx_child_fork()))	/* child process */
	{
		(*handler)(thread_args);

		/* The zbx_thread_exit must be called from the handler. */
		/* And in normal case the program will never reach this point. */
		zbx_thread_exit(EXIT_SUCCESS);
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
 ******************************************************************************/
int	zbx_thread_wait(ZBX_THREAD_HANDLE thread)
{
	int	status = 0;	/* significant 8 bits of the status */

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
		zbx_error("Error waiting for process with PID %d: %s", (int)thread, zbx_strerror(errno));
		return ZBX_THREAD_ERROR;
	}

	status = WEXITSTATUS(status);

#endif	/* _WINDOWS */

	return status;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_threads_wait                                                 *
 *                                                                            *
 * Purpose: Waits until the "threads" are in the signalled state              *
 *                                                                            *
 * Parameters: "threads" handles                                              *
 *                                                                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_threads_wait(ZBX_THREAD_HANDLE *threads, int threads_num)
{
	int		i;
#if !defined(_WINDOWS)
	sigset_t	set;

	/* ignore SIGCHLD signals in order for zbx_sleep() to work */
	sigemptyset(&set);
	sigaddset(&set, SIGCHLD);
	sigprocmask(SIG_BLOCK, &set, NULL);
#else
	/* wait for threads to finish first. although listener threads will never end */
	WaitForMultipleObjectsEx(threads_num, threads, TRUE, 1000, FALSE);
#endif
	for (i = 0; i < threads_num; i++)
	{
		if (threads[i])
			zbx_thread_kill(threads[i]);
	}

	for (i = 0; i < threads_num; i++)
	{
		if (threads[i])
			zbx_thread_wait(threads[i]);

		threads[i] = ZBX_THREAD_HANDLE_NULL;
	}
}

long int	zbx_get_thread_id(void)
{
#ifdef _WINDOWS
	return (long int)GetCurrentThreadId();
#else
	return (long int)getpid();
#endif
}
