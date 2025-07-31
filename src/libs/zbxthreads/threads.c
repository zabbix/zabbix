/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "zbxthreads.h"

#if defined(_WINDOWS) || defined(__MINGW32__)
#include "zbxwin32.h"
#include "zbxlog.h"

static ZBX_THREAD_ENTRY(zbx_win_thread_entry, args)
{
	__try
	{
		zbx_thread_args_t	*thread_args = (zbx_thread_args_t *)args;

		return thread_args->entry(thread_args);
	}
	__except(zbx_win_seh_handler(GetExceptionInformation()))
	{
		zbx_thread_exit(EXIT_SUCCESS);
	}
}

void CALLBACK	ZBXEndThread(ULONG_PTR dwParam)
{
	_endthreadex(SUCCEED);
}
#else
/******************************************************************************
 *                                                                            *
 * Purpose: Flush stdout and stderr before forking.                           *
 *                                                                            *
 * Return value: same as system fork() function                               *
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
 * Purpose: fork from master process and set SIGCHLD handler                  *
 *                                                                            *
 * Parameters: pid - [OUT]                                                    *
 *                                                                            *
 * Comments: use this function only for forks from the main process           *
 *                                                                            *
 ******************************************************************************/
void	zbx_child_fork(pid_t *pid)
{
	sigset_t	mask, orig_mask;

	/* block signals during fork to avoid deadlock (we've seen one in __unregister_atfork()) */
	sigemptyset(&mask);
	sigaddset(&mask, SIGTERM);
	sigaddset(&mask, SIGUSR2);
	sigaddset(&mask, SIGHUP);
	sigaddset(&mask, SIGINT);
	sigaddset(&mask, SIGQUIT);
	sigaddset(&mask, SIGCHLD);

	zbx_sigmask(SIG_BLOCK, &mask, &orig_mask);

	/* set process id instead of returning, this is to avoid race condition when signal arrives before return */
	*pid = zbx_fork();

	zbx_sigmask(SIG_SETMASK, &orig_mask, NULL);

	/* ignore SIGCHLD to avoid problems with exiting scripts in zbx_execute() and other cases */
	if (0 == *pid)
		signal(SIGCHLD, SIG_DFL);
}

int	zbx_is_child_pid(pid_t pid, const pid_t *child_pids, size_t child_pids_num)
{
	size_t	i;

	if (NULL == child_pids)
		return FAIL;

	for (i = 0; i < child_pids_num; i++)
	{
		if (pid == child_pids[i])
			return SUCCEED;
	}

	return FAIL;
}
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: Start handler function as "thread".                               *
 *                                                                            *
 * Parameters: handler     - [IN] new thread starts execution from this       *
 *                                handler function                            *
 *             thread_args - [IN] arguments for thread function               *
 *             thread      - [OUT] handle to a newly created thread           *
 *                                                                            *
 * Comments: The zbx_thread_exit must be called from the handler!             *
 *                                                                            *
 ******************************************************************************/
void	zbx_thread_start(ZBX_THREAD_ENTRY_POINTER(handler), zbx_thread_args_t *thread_args, ZBX_THREAD_HANDLE *thread)
{
#if defined(_WINDOWS) || defined(__MINGW32__)
	unsigned	thrdaddr;

	thread_args->entry = handler;
	/* NOTE: _beginthreadex returns 0 on failure, rather than 1 */
	if (0 == (*thread = (ZBX_THREAD_HANDLE)_beginthreadex(NULL, 0, zbx_win_thread_entry, thread_args, 0,
			&thrdaddr)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "failed to create a thread: %s", zbx_strerror_from_system(GetLastError()));
		*thread = (ZBX_THREAD_HANDLE)ZBX_THREAD_ERROR;
	}
#else
	zbx_child_fork(thread);

	if (0 == *thread)	/* child process */
	{
		(*handler)(thread_args);

		/* The zbx_thread_exit must be called from the handler. */
		/* And in normal case the program will never reach this point. */
		THIS_SHOULD_NEVER_HAPPEN;
		/* program will never reach this point */
	}
	else if (-1 == *thread)
	{
		zbx_error("failed to fork: %s", zbx_strerror(errno));
		*thread = (ZBX_THREAD_HANDLE)ZBX_THREAD_ERROR;
	}
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: Waits until the thread is in the signalled state.                 *
 *                                                                            *
 * Parameters: thread - [IN] thread handle                                    *
 *                                                                            *
 * Return value: process or thread exit code                                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_thread_wait(ZBX_THREAD_HANDLE thread)
{
	int	status = 0;	/* significant 8 bits of the status */

#if defined(_WINDOWS) || defined(__MINGW32__)
	DWORD	dwstatus;

	if (WAIT_OBJECT_0 != WaitForSingleObject(thread, INFINITE))
	{
		zbx_error("Error on thread waiting. [%s]", zbx_strerror_from_system(GetLastError()));
		return ZBX_THREAD_ERROR;
	}

	if (0 == GetExitCodeThread(thread, &dwstatus))
	{
		zbx_error("Error on thread exit code receiving. [%s]", zbx_strerror_from_system(GetLastError()));
		return ZBX_THREAD_ERROR;
	}

	if (0 == CloseHandle(thread))
	{
		zbx_error("Error on thread closing. [%s]", zbx_strerror_from_system(GetLastError()));
		return ZBX_THREAD_ERROR;
	}
	status = dwstatus;

#else	/* not _WINDOWS */
	pid_t	pid;

	do
	{
		pid = waitpid(thread, &status, 0);
	}
	while (pid == -1 && EINTR == errno);

	if (0 >= pid)
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
 * Purpose: sends termination signal to threads                               *
 *                                                                            *
 * Parameters: threads       - [IN] handles to threads or processes           *
 *             threads_num   - [IN] number of handles                         *
 *             threads_flags - [IN] thread priority flags                     *
 *             priority      - [IN] terminate threads with specified priority *
 *             ret           - [IN] terminate thread politely on SUCCEED or   *
 *                                  ask all threads to exit immediately on    *
 *                                  FAIL                                      *
 *                                                                            *
 ******************************************************************************/
static void	threads_kill(ZBX_THREAD_HANDLE *threads, int threads_num, const int *threads_flags, int priority,
		int ret)
{
	for (int i = 0; i < threads_num; i++)
	{
		if (!threads[i])
			continue;

		if (SUCCEED != ret)
		{
			zbx_thread_kill_fatal(threads[i]);
			continue;
		}

		if (priority != threads_flags[i])
			continue;

		zbx_thread_kill(threads[i]);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: Kills and waits until the threads are in the signalled state.     *
 *                                                                            *
 * Parameters: threads       - [IN] handles to threads or processes           *
 *             threads_flags - [IN] thread priority flags                     *
 *             threads_num   - [IN] number of handles                         *
 *             ret           - [IN] terminate thread politely on SUCCEED or   *
 *                                  ask all threads to exit immediately on    *
 *                                  FAIL                                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_threads_kill_and_wait(ZBX_THREAD_HANDLE *threads, const int *threads_flags, int threads_num, int ret)
{
#if !defined(_WINDOWS) && !defined(__MINGW32__)
	sigset_t	set;

	/* ignore SIGCHLD signals in order for zbx_sleep() to work */
	sigemptyset(&set);
	sigaddset(&set, SIGCHLD);
	zbx_sigmask(SIG_BLOCK, &set, NULL);

	/* signal all threads to go into idle state and wait for threads with higher priority to exit */
	threads_kill(threads, threads_num, threads_flags, ZBX_THREAD_PRIORITY_NONE, ret);

	for (int j = ZBX_THREAD_PRIORITY_FIRST; j < ZBX_THREAD_PRIORITY_COUNT; j++)
	{
		threads_kill(threads, threads_num, threads_flags, j, ret);

		for (int i = 0; i < threads_num; i++)
		{
			if (!threads[i] || j != threads_flags[i])
				continue;

			zbx_thread_wait(threads[i]);

			threads[i] = ZBX_THREAD_HANDLE_NULL;
		}
	}

	/* signal idle threads to exit */
	threads_kill(threads, threads_num, threads_flags, ZBX_THREAD_PRIORITY_NONE, FAIL);
#else
	/* wait for threads to finish first; although listener threads will never end */
	WaitForMultipleObjectsEx(threads_num, threads, TRUE, 1000, FALSE);
	threads_kill(threads, threads_num, threads_flags, ZBX_THREAD_PRIORITY_NONE, ret);
#endif

	for (int i = 0; i < threads_num; i++)
	{
		if (!threads[i])
			continue;

		zbx_thread_wait(threads[i]);

		threads[i] = ZBX_THREAD_HANDLE_NULL;
	}
}

#if !defined(_WINDOWS) && !defined(__MINGW32__)
void	zbx_pthread_init_attr(pthread_attr_t *attr)
{
	if (0 != pthread_attr_init(attr))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize thread attributes: %s", zbx_strerror(errno));
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}

#ifdef HAVE_STACKSIZE
	if (0 != pthread_attr_setstacksize(attr, HAVE_STACKSIZE * ZBX_KIBIBYTE))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot set thread stack size: %s", zbx_strerror(errno));
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}
#endif
}
#endif
