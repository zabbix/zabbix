/*
** Copyright (C) 2001-2026 Zabbix SIA
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
#include "zbxmutexs.h"
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

#if !defined(_WINDOWS) && !defined(__MINGW32__)
#define ZBX_THREADS_POLLS_PER_SEC	10

typedef struct
{
	int	running_num;	/* number of running processes at last poll */
	int	value;		/* value returned by progress callback at last poll */
	int	idle_polls;	/* number of polls without progress */
}
zbx_threads_progress_t;

/******************************************************************************
 *                                                                            *
 * Purpose: kills processes immediately and waits until they have exited      *
 *                                                                            *
 * Parameters: threads     - [IN/OUT] handles to processes, reset when        *
 *                                    process has exited                      *
 *             threads_num - [IN] number of handles                           *
 *                                                                            *
 * Comments: This function locks the log mutex first, so that processes to    *
 *           be terminated cannot be holding that mutex and cause deadlock,   *
 *           and keeps it locked until they have exited. SIGKILL is used,     *
 *           because processes waiting for log mutex block SIGHUP.            *
 *                                                                            *
 ******************************************************************************/
static void	threads_kill_fatal(ZBX_THREAD_HANDLE *threads, int threads_num)
{
	sigset_t	mask, orig_mask;
	zbx_mutex_t	log_mutex = zbx_mutex_addr_get(ZBX_MUTEX_LOG);

	/* signal handlers can write to log, block them while log mutex is locked */
	sigemptyset(&mask);
	sigaddset(&mask, SIGUSR1);
	sigaddset(&mask, SIGUSR2);
	sigaddset(&mask, SIGTERM);
	sigaddset(&mask, SIGINT);
	sigaddset(&mask, SIGQUIT);
	sigaddset(&mask, SIGHUP);
	zbx_sigmask(SIG_BLOCK, &mask, &orig_mask);

	zbx_mutex_lock(log_mutex);

	for (int i = 0; i < threads_num; i++)
	{
		if (ZBX_THREAD_HANDLE_NULL != threads[i])
			kill(threads[i], SIGKILL);
	}

	for (int i = 0; i < threads_num; i++)
	{
		if (ZBX_THREAD_HANDLE_NULL == threads[i])
			continue;

		(void)zbx_thread_wait(threads[i]);
		threads[i] = ZBX_THREAD_HANDLE_NULL;
	}

	zbx_mutex_unlock(log_mutex);
	zbx_sigmask(SIG_SETMASK, &orig_mask, NULL);
}
#endif

#define ZBX_THREADS_STOP_GRACEFUL	0
#define ZBX_THREADS_STOP_IMMEDIATE	1

/******************************************************************************
 *                                                                            *
 * Purpose: sends termination signal to threads                               *
 *                                                                            *
 * Parameters: threads       - [IN] handles to threads or processes           *
 *             threads_num   - [IN] number of handles                         *
 *             threads_flags - [IN] thread priority flags                     *
 *             priority      - [IN] terminate threads with specified priority *
 *             stop_mode     - [IN] ZBX_THREADS_STOP_GRACEFUL - terminate     *
 *                                  threads with specified priority politely, *
 *                                  ZBX_THREADS_STOP_IMMEDIATE - ask all      *
 *                                  threads to exit immediately               *
 *                                                                            *
 ******************************************************************************/
static void	threads_kill(ZBX_THREAD_HANDLE *threads, int threads_num, const int *threads_flags, int priority,
		int stop_mode)
{
	for (int i = 0; i < threads_num; i++)
	{
		if (ZBX_THREAD_HANDLE_NULL == threads[i])
			continue;

		if (ZBX_THREADS_STOP_IMMEDIATE == stop_mode)
		{
			zbx_thread_kill_fatal(threads[i]);
			continue;
		}

		if (priority != threads_flags[i])
			continue;

		zbx_thread_kill(threads[i]);
	}
}
#if !defined(_WINDOWS) && !defined(__MINGW32__)
int	zbx_child_cleanup(pid_t pid, pid_t *threads, size_t threads_num)
{
	size_t	i;

	if (NULL == threads)
		return FAIL;

	for (i = 0; i < threads_num; i++)
	{
		if (pid == threads[i])
		{
			threads[i] = ZBX_THREAD_HANDLE_NULL;
			return SUCCEED;
		}
	}

	return FAIL;
}

static int	zbx_thread_count(pid_t *threads, size_t threads_num, const int *threads_flags, int priority)
{
	int	count = 0;

	for (int i = 0; i < (int)threads_num; i++)
	{
		if (ZBX_THREAD_HANDLE_NULL == threads[i] || priority != threads_flags[i])
			continue;

		count++;
	}

	return count;
}

static void	log_exit_status(pid_t pid, int status)
{
	if (0 == WIFEXITED(status) || 0 != WEXITSTATUS(status))
	{
		if (WIFEXITED(status))
		{
			zbx_error("child process exited (PID:%d,exitcode:%d).",
					pid, WEXITSTATUS(status));
		}
		else if (WIFSIGNALED(status))
		{
			zbx_error("child process killed by signal"
					" (PID:%d,signal:%d).", pid, WTERMSIG(status));
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if shutdown is making progress                             *
 *                                                                            *
 * Parameters: threads     - [IN] handles to processes                        *
 *             threads_num - [IN] number of handles                           *
 *             progress_cb - [IN] optional callback returning value that      *
 *                                changes while processes make progress       *
 *             progress    - [IN/OUT] progress state of previous poll         *
 *                                                                            *
 * Return value: number of polls without progress                             *
 *                                                                            *
 * Comments: Changed number of running processes (e.g. exit of any process)   *
 *           also counts as progress.                                         *
 *                                                                            *
 ******************************************************************************/
static int	threads_poll_progress(const ZBX_THREAD_HANDLE *threads, int threads_num,
		zbx_threads_progress_f progress_cb, zbx_threads_progress_t *progress)
{
	int	running_num = 0, value = (NULL != progress_cb ? progress_cb() : 0);

	for (int i = 0; i < threads_num; i++)
	{
		if (ZBX_THREAD_HANDLE_NULL != threads[i])
			running_num++;
	}

	if (running_num != progress->running_num || value != progress->value)
	{
		progress->running_num = running_num;
		progress->value = value;
		progress->idle_polls = 0;
	}
	else
		progress->idle_polls++;

	return progress->idle_polls;
}

/******************************************************************************
 *                                                                            *
 * Purpose: waits for threads with specified priority to exit                 *
 *                                                                            *
 * Parameters: threads       - [IN/OUT] handles to processes, reset when      *
 *                                      process has exited                    *
 *             threads_flags - [IN] thread priority flags                     *
 *             threads_num   - [IN] number of handles                         *
 *             priority      - [IN] priority of threads to wait for           *
 *             stop_mode     - [IN] ZBX_THREADS_STOP_GRACEFUL - threads were  *
 *                                  asked to exit gracefully, exit status is  *
 *                                  logged,                                   *
 *                                  ZBX_THREADS_STOP_IMMEDIATE - threads were *
 *                                  asked to exit immediately, exit status is *
 *                                  not logged                                *
 *             timeout       - [IN] seconds without progress before giving    *
 *                                  up, 0 - wait without timeout              *
 *             progress_cb   - [IN] optional callback returning value that    *
 *                                  changes while threads make progress       *
 *             progress      - [IN/OUT] progress state, shared by waits of    *
 *                                      all priorities                        *
 *                                                                            *
 * Return value: SUCCEED       - threads exited                               *
 *               TIMEOUT_ERROR - no progress was made for timeout seconds     *
 *                                                                            *
 * Comments: Time is counted in polling intervals, so that system clock       *
 *           changes do not affect it.                                        *
 *                                                                            *
 ******************************************************************************/
static int	threads_wait(ZBX_THREAD_HANDLE *threads, const int *threads_flags, int threads_num, int priority,
		int stop_mode, int timeout, zbx_threads_progress_f progress_cb, zbx_threads_progress_t *progress)
{
	pid_t	pid;

	for (int i = 0; 0 != zbx_thread_count(threads, threads_num, threads_flags, priority); i++)
	{
		int	status;

		struct timespec	poll_delay = {0, 1000000000 / ZBX_THREADS_POLLS_PER_SEC};

		if (i != 0)
		{
			if (0 != timeout && timeout * ZBX_THREADS_POLLS_PER_SEC <= threads_poll_progress(threads,
					threads_num, progress_cb, progress))
			{
				for (int j = 0; j < threads_num; j++)
				{
					if (ZBX_THREAD_HANDLE_NULL == threads[j] || priority != threads_flags[j])
						continue;

					zabbix_log(LOG_LEVEL_WARNING, "process with PID %d made no progress in %d"
							" seconds after shutdown request, killing it",
							(int)threads[j], timeout);
				}

				zbx_error("timed out while waiting for child processes");
				return TIMEOUT_ERROR;
			}

			nanosleep(&poll_delay, NULL);
		}

		while (0 < (pid = waitpid((pid_t)-1, &status, WNOHANG)))
		{
			if (SUCCEED != zbx_child_cleanup(pid, threads, threads_num))
				continue;

			if (ZBX_THREADS_STOP_IMMEDIATE == stop_mode)
				continue;

			log_exit_status(pid, status);
		}

		if (-1 == pid && EINTR != errno)
		{
			if (0 != zbx_thread_count(threads, threads_num, threads_flags, priority))
				zbx_error("failed to wait on child processes: %s", zbx_strerror(errno));
			break;
		}
	}

	return SUCCEED;
}

static int	threads_kill_and_wait(ZBX_THREAD_HANDLE *threads, const int *threads_flags, int threads_num,
		int priority, int stop_mode, int timeout, zbx_threads_progress_f progress_cb,
		zbx_threads_progress_t *progress)
{
	threads_kill(threads, threads_num, threads_flags, priority, stop_mode);

	return threads_wait(threads, threads_flags, threads_num, priority, stop_mode, timeout, progress_cb,
			progress);
}
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: Kills and waits until the threads are in the signalled state.     *
 *                                                                            *
 * Parameters: threads       - [IN] handles to threads or processes           *
 *             threads_flags - [IN] thread priority flags                     *
 *             threads_num   - [IN] number of handles                         *
 *             timeout       - [IN] seconds without shutdown progress after   *
 *                                  which threads are terminated immediately, *
 *                                  0 - wait without timeout                  *
 *             progress_cb   - [IN] optional callback returning value that    *
 *                                  changes while threads are making progress *
 *                                                                            *
 * Return value: SUCCEED - all threads exited                                 *
 *               FAIL    - threads made no progress for timeout seconds and   *
 *                         were killed                                        *
 *                                                                            *
 * Comments: Timeout case only: if no progress is made for timeout seconds,   *
 *           all remaining processes are killed at once, ignoring priority    *
 *           order, and FAIL is returned so that history cache is not synced. *
 *           Killed process may have left shared memory half-updated or lock  *
 *           held (pthread mutexes may deadlock, with SysV semaphores lock is *
 *           released, but subsequent sync could read inconsistent caches and *
 *           write them to database).                                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_threads_kill_and_wait(ZBX_THREAD_HANDLE *threads, const int *threads_flags, int threads_num, int timeout,
		zbx_threads_progress_f progress_cb)
{
	int	ret = SUCCEED;
#if !defined(_WINDOWS) && !defined(__MINGW32__)
	sigset_t		set;
	int			supervisor_ret;
	zbx_threads_progress_t	progress = {.running_num = -1, .value = 0, .idle_polls = 0};

	/* ignore SIGCHLD signals in order for zbx_sleep() to work */
	sigemptyset(&set);
	sigaddset(&set, SIGCHLD);
	zbx_sigmask(SIG_BLOCK, &set, NULL);

	/* signal non priority threads to go into idle state and wait for threads with higher priority to exit */
	threads_kill(threads, threads_num, threads_flags, ZBX_THREAD_PRIORITY_NONE, ZBX_THREADS_STOP_GRACEFUL);

	if (TIMEOUT_ERROR == threads_kill_and_wait(threads, threads_flags, threads_num, ZBX_THREAD_PRIORITY_COLLECTOR,
			ZBX_THREADS_STOP_GRACEFUL, timeout, progress_cb, &progress))
	{
		threads_kill_fatal(threads, threads_num);
		ret = FAIL;
	}

	/* supervisor units wait for history syncers, so supervisor is killed only after them */
	supervisor_ret = threads_kill_and_wait(threads, threads_flags, threads_num, ZBX_THREAD_PRIORITY_SUPERVISOR,
		ZBX_THREADS_STOP_GRACEFUL, timeout, progress_cb, &progress);

	if (TIMEOUT_ERROR == threads_kill_and_wait(threads, threads_flags, threads_num, ZBX_THREAD_PRIORITY_SYNCER,
			ZBX_THREADS_STOP_GRACEFUL, timeout, progress_cb, &progress))
	{
		threads_kill_fatal(threads, threads_num);
		ret = FAIL;
	}

	if (TIMEOUT_ERROR == threads_kill_and_wait(threads, threads_flags, threads_num, ZBX_THREAD_PRIORITY_WORKER,
			ZBX_THREADS_STOP_GRACEFUL, timeout, progress_cb, &progress))
	{
		threads_kill_fatal(threads, threads_num);
		ret = FAIL;
	}

	if (TIMEOUT_ERROR == supervisor_ret)
	{
		threads_kill_fatal(threads, threads_num);
		ret = FAIL;
	}

	/* signal idle threads to exit */
	threads_kill_and_wait(threads, threads_flags, threads_num, ZBX_THREAD_PRIORITY_NONE, ZBX_THREADS_STOP_IMMEDIATE,
			0, NULL, &progress);
#else
	ZBX_UNUSED(timeout);
	ZBX_UNUSED(progress_cb);

	/* wait for threads to finish first; although listener threads will never end */
	WaitForMultipleObjectsEx(threads_num, threads, TRUE, 1000, FALSE);
	threads_kill(threads, threads_num, threads_flags, ZBX_THREAD_PRIORITY_NONE, ZBX_THREADS_STOP_IMMEDIATE);

	for (int i = 0; i < threads_num; i++)
	{
		if (ZBX_THREAD_HANDLE_NULL == threads[i])
			continue;

		zbx_thread_wait(threads[i]);

		threads[i] = ZBX_THREAD_HANDLE_NULL;
	}
#endif
	return ret;
}

#if !defined(_WINDOWS) && !defined(__MINGW32__)
/******************************************************************************
 *                                                                            *
 * Purpose: executes function in child process and waits for it to exit,      *
 *          kills it if no progress is made                                   *
 *                                                                            *
 * Parameters: func        - [IN] function to execute                         *
 *             timeout     - [IN] seconds without progress before child       *
 *                                process is killed                           *
 *             progress_cb - [IN] optional callback returning value that      *
 *                                changes while function makes progress       *
 *                                                                            *
 * Comments: Used on shutdown to write cached data to database without risk   *
 *           of hanging on unavailable database. If child process cannot be   *
 *           created, function is not executed.                               *
 *                                                                            *
 ******************************************************************************/
void	zbx_thread_exec_and_wait(void (*func)(void), int timeout, zbx_threads_progress_f progress_cb)
{
	ZBX_THREAD_HANDLE	pid;
	int			flag = ZBX_THREAD_PRIORITY_COLLECTOR;
	zbx_threads_progress_t	progress = {.running_num = -1, .value = 0, .idle_polls = 0};

	zbx_child_fork(&pid);

	if (0 == pid)
	{
		func();
		_exit(EXIT_SUCCESS);
	}

	if (-1 == pid)
	{
		/* func() is not called in the parent process - it could hang shutdown if database is unavailable */
		zabbix_log(LOG_LEVEL_WARNING, "cannot create process: %s, cached data will not be synced to database",
				zbx_strerror(errno));

		return;
	}

	if (TIMEOUT_ERROR == threads_wait(&pid, &flag, 1, flag, ZBX_THREADS_STOP_GRACEFUL, timeout, progress_cb,
			&progress))
		threads_kill_fatal(&pid, 1);
}

void	zbx_pthread_init_attr(pthread_attr_t *attr)
{
	if (0 != pthread_attr_init(attr))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize thread attributes: %s", zbx_strerror(errno));
		THIS_SHOULD_NEVER_HAPPEN;
		zbx_exit(EXIT_FAILURE);
	}

#ifdef HAVE_STACKSIZE
	if (0 != pthread_attr_setstacksize(attr, HAVE_STACKSIZE * ZBX_KIBIBYTE))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot set thread stack size: %s", zbx_strerror(errno));
		THIS_SHOULD_NEVER_HAPPEN;
		zbx_exit(EXIT_FAILURE);
	}
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: check for terminated direct child processes without blocking      *
 *                                                                            *
 * Parameters: threads     - [IN/OUT] array of direct process pids            *
 *             threads_num - [IN] number of pids in the array                 *
 *                                                                            *
 * Return value: SUCCEED - a terminated child process was found               *
 *               FAIL    - no child processes available                       *
 *                                                                            *
 * Comments: logs error messages for processes that exit with non-zero status *
 *           or are terminated by signals                                     *
 *                                                                            *
 ******************************************************************************/
int	zbx_waitpid_nohang(ZBX_THREAD_HANDLE *threads, size_t threads_num)
{
	int	status;
	pid_t	pid;

	if (0 >= (pid = waitpid((pid_t)-1, &status, WNOHANG)))
		return FAIL;

	if (SUCCEED != zbx_child_cleanup(pid, threads, threads_num))
		return FAIL;

	log_exit_status(pid, status);

	return SUCCEED;
}
#endif

#undef ZBX_THREADS_STOP_IMMEDIATE
#undef ZBX_THREADS_STOP_GRACEFUL
