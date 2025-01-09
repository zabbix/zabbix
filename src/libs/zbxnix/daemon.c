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

#include "zbxnix.h"

#include "nix_internal.h"
#include "sigcommon.h"
#include "pid.h"

#include "zbxprof.h"
#include "zbxlog.h"
#include "zbx_rtc_constants.h"
#include "zbxthreads.h"

#if defined(__linux__)
#define ZBX_PID_FILE_TIMEOUT 20
#define ZBX_PID_FILE_SLEEP_TIME 100000000
#endif

static int	parent_pid = -1;

/* pointer to function for getting caller's PID file location */
static zbx_get_config_str_f		get_pid_file_pathname_cb = NULL;
static zbx_get_threads_f		get_threads_func_cb;
static zbx_get_config_int_f		get_threads_num_func_cb;

static zbx_signal_handler_f	sigusr_handler;
static zbx_signal_redirect_f	signal_redirect_handler;

#ifdef HAVE_SIGQUEUE
/******************************************************************************
 *                                                                            *
 * Purpose: common SIGUSR1 handler for Zabbix processes                       *
 *                                                                            *
 ******************************************************************************/
static void	common_sigusr_handler(int flags)
{
	switch (ZBX_RTC_GET_MSG(flags))
	{
		case ZBX_RTC_LOG_LEVEL_INCREASE:
			zabbix_increase_log_level();
			break;
		case ZBX_RTC_PROF_ENABLE:
			zbx_prof_enable(ZBX_RTC_GET_SCOPE(flags));
			break;
		case ZBX_RTC_PROF_DISABLE:
			zbx_prof_disable();
			break;
		case ZBX_RTC_LOG_LEVEL_DECREASE:
			zabbix_decrease_log_level();
			break;
		default:
			if (NULL != sigusr_handler)
				sigusr_handler(flags);
			break;
	}
}

void	zbx_signal_process_by_type(int proc_type, int proc_num, int flags, char **out)
{
	int		process_num, found = 0, i, failed_num = 0;
	union sigval	s;
	unsigned char	process_type;
	size_t		out_alloc = 0, out_offset = 0;

	s.sival_ptr = NULL;
	s.ZBX_SIVAL_INT = flags;

	int	threads_num = get_threads_num_func_cb();
	for (i = 0; i < threads_num; i++)
	{
		if (FAIL == nix_get_process_info_by_thread_func_cb()(i + 1, &process_type, &process_num))
			break;

		if (proc_type != process_type)
		{
			/* check if we have already checked processes of target type */
			if (1 == found)
				break;

			continue;
		}

		if (0 != proc_num && proc_num != process_num)
			continue;

		found = 1;

		if (-1 != sigqueue((get_threads_func_cb())[i], SIGUSR1, s))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "the signal was redirected to \"%s\" process"
					" pid:%d", get_process_type_string(process_type), (get_threads_func_cb())[i]);
		}
		else
		{
			zabbix_log(LOG_LEVEL_ERR, "cannot redirect signal: %s", zbx_strerror(errno));
			failed_num++;
		}
	}

	if (0 == found)
	{
		if (0 == proc_num)
		{
			zbx_strlog_alloc(LOG_LEVEL_ERR, out, &out_alloc, &out_offset, "cannot redirect signal:"
					" \"%s\" process does not exist",
					get_process_type_string(proc_type));
		}
		else
		{
			zbx_strlog_alloc(LOG_LEVEL_ERR, out, &out_alloc, &out_offset, "cannot redirect signal:"
					" \"%s #%d\" process does not exist",
					get_process_type_string(proc_type), proc_num);
		}
	}
	else
	{
		if (0 != failed_num && NULL != out)
			*out = zbx_strdup(*out, "failed to redirect remote control signal(s)");
	}
}

void	zbx_signal_process_by_pid(int pid, int flags, char **out)
{
	union sigval	s;
	int		i, found = 0, failed_num = 0;
	size_t		out_alloc = 0, out_offset = 0;

	s.sival_ptr = NULL;
	s.ZBX_SIVAL_INT = flags;

	int	threads_num = get_threads_num_func_cb();
	for (i = 0; i < threads_num; i++)
	{
		int	thread_pid = get_threads_func_cb()[i];

		if ((0 != pid && thread_pid != pid) || 0 == thread_pid)
			continue;

		found = 1;

		if (-1 != sigqueue(thread_pid, SIGUSR1, s))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "the signal was redirected to process pid:%d", thread_pid);
		}
		else
		{
			zabbix_log(LOG_LEVEL_ERR, "cannot redirect signal: %s", zbx_strerror(errno));
			failed_num++;
		}
	}

	if (0 != pid && 0 == found)
	{
		zbx_strlog_alloc(LOG_LEVEL_DEBUG, out, &out_alloc, &out_offset,
				"cannot redirect signal: process pid:%d is not a Zabbix child process", pid);
	}
	else
	{
		if (0 != failed_num && NULL != out)
			*out = zbx_strdup(*out, "failed to redirect remote control signal(s)");
	}
}

#endif

void	zbx_set_sigusr_handler(zbx_signal_handler_f handler)
{
	sigusr_handler = handler;
}

/******************************************************************************
 *                                                                            *
 * Purpose: handle user signal SIGUSR1                                        *
 *                                                                            *
 ******************************************************************************/
static void	user1_signal_handler(int sig, siginfo_t *siginfo, void *context)
{
	ZBX_UNUSED(sig);
	ZBX_UNUSED(context);

#ifdef HAVE_SIGQUEUE
	int	flags = SIG_CHECKED_FIELD(siginfo, si_value.ZBX_SIVAL_INT);

	if (!SIG_PARENT_PROCESS)
	{
		common_sigusr_handler(flags);
		return;
	}

	if (NULL == get_threads_func_cb())
		return;

	if (signal_redirect_handler != NULL)
		signal_redirect_handler(flags, sigusr_handler);
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: set the signal handlers used by daemons                           *
 *                                                                            *
 ******************************************************************************/
static void	set_daemon_signal_handlers(zbx_signal_redirect_f signal_redirect_cb)
{
	struct sigaction	phan;

	signal_redirect_handler = signal_redirect_cb;
	set_sig_parent_pid((int)getpid());

	sigemptyset(&phan.sa_mask);
	phan.sa_flags = SA_SIGINFO;

	phan.sa_sigaction = user1_signal_handler;
	sigaction(SIGUSR1, &phan, NULL);

	phan.sa_handler = SIG_IGN;
	sigaction(SIGPIPE, &phan, NULL);
}

/******************************************************************************
 *                                                                            *
 * Purpose: init process as daemon                                            *
 *                                                                            *
 * Parameters: allow_root         - [IN] allow root permission for            *
 *                                       application                          *
 *             user               - [IN] user on system to which to drop      *
 *                                       privileges                           *
 *             flags              - [IN] daemon startup flags                 *
 *             get_pid_file_cb    - [IN] callback function for getting        *
 *                                       absolute path and name of PID file   *
 *             zbx_on_exit_cb_arg - [IN] callback function called when        *
 *                                       terminating signal handler           *
 *             config_log_type    - [IN]                                      *
 *             config_log_file    - [IN]                                      *
 *             signal_redirect_cb - [IN] USR1 handling callback               *
 *             get_threads_cb     - [IN]                                      *
 *             get_threads_num_cb - [IN]                                      *
 *                                                                            *
 * Comments: it doesn't allow running under 'root' if allow_root is zero      *
 *                                                                            *
 ******************************************************************************/
int	zbx_daemon_start(int allow_root, const char *user, unsigned int flags,
		zbx_get_config_str_f get_pid_file_cb, zbx_on_exit_t zbx_on_exit_cb_arg, int config_log_type,
		const char *config_log_file, zbx_signal_redirect_f signal_redirect_cb,
		zbx_get_threads_f get_threads_cb, zbx_get_config_int_f get_threads_num_cb)
{
	struct passwd	*pwd;

	get_pid_file_pathname_cb = get_pid_file_cb;
	get_threads_func_cb = get_threads_cb;
	get_threads_num_func_cb = get_threads_num_cb;

	if (0 == allow_root && 0 == getuid())	/* running as root? */
	{
		if (NULL == user)
			user = "zabbix";

		pwd = getpwnam(user);

		if (NULL == pwd)
		{
			zbx_error("user %s does not exist", user);
			zbx_error("cannot run as root!");
			exit(EXIT_FAILURE);
		}

		if (0 == pwd->pw_uid)
		{
			zbx_error("User=%s contradicts AllowRoot=0", user);
			zbx_error("cannot run as root!");
			exit(EXIT_FAILURE);
		}

		if (-1 == setgid(pwd->pw_gid))
		{
			zbx_error("cannot setgid to %s: %s", user, zbx_strerror(errno));
			exit(EXIT_FAILURE);
		}

#ifdef HAVE_FUNCTION_INITGROUPS
		if (-1 == initgroups(user, pwd->pw_gid))
		{
			zbx_error("cannot initgroups to %s: %s", user, zbx_strerror(errno));
			exit(EXIT_FAILURE);
		}
#endif

		if (-1 == setuid(pwd->pw_uid))
		{
			zbx_error("cannot setuid to %s: %s", user, zbx_strerror(errno));
			exit(EXIT_FAILURE);
		}

#ifdef HAVE_FUNCTION_SETEUID
		if (-1 == setegid(pwd->pw_gid) || -1 == seteuid(pwd->pw_uid))
		{
			zbx_error("cannot setegid or seteuid to %s: %s", user, zbx_strerror(errno));
			exit(EXIT_FAILURE);
		}
#endif
	}

	umask(0002);

	if (0 == (flags & ZBX_TASK_FLAG_FOREGROUND))
	{
		pid_t	child_pid;

		if(0 != (child_pid = zbx_fork()))
		{
#if defined(__linux__)
			if (0 < child_pid)
			{
				int		pid_file_timeout = ZBX_PID_FILE_TIMEOUT;
				zbx_stat_t	stat_buff;
				struct timespec	ts = {0, ZBX_PID_FILE_SLEEP_TIME};

				/* wait for the forked child to create pid file */
				while (0 < pid_file_timeout && 0 != zbx_stat(get_pid_file_cb(), &stat_buff))
				{
					pid_file_timeout--;
					nanosleep(&ts, NULL);
				}
			}
#else
			ZBX_UNUSED(child_pid);
#endif
			exit(EXIT_SUCCESS);
		}

		setsid();

		signal(SIGHUP, SIG_IGN);

		if (0 != zbx_fork())
			exit(EXIT_SUCCESS);

		if (-1 == chdir("/"))	/* this is to eliminate warning: ignoring return value of chdir */
		{
			zbx_this_should_never_happen_backtrace();
			assert(0);
		}

		if (FAIL == zbx_redirect_stdio(ZBX_LOG_TYPE_FILE == config_log_type ? config_log_file : NULL))
			exit(EXIT_FAILURE);
	}

	if (FAIL == create_pid_file(get_pid_file_cb()))
		exit(EXIT_FAILURE);

	atexit(zbx_daemon_stop);

	parent_pid = (int)getpid();

	zbx_set_common_signal_handlers(zbx_on_exit_cb_arg);
	set_daemon_signal_handlers(signal_redirect_cb);

	/* Set SIGCHLD now to avoid race conditions when a child process is created before */
	/* sigaction() is called. To avoid problems when scripts exit in zbx_execute() and */
	/* other cases, SIGCHLD is set to SIG_DFL in zbx_child_fork(). */
	zbx_set_child_signal_handler();

	return MAIN_ZABBIX_ENTRY(flags);
}

void	zbx_daemon_stop(void)
{
	/* this function is registered using atexit() to be called when we terminate */
	/* there should be nothing like logging or calls to exit() beyond this point */

	if (parent_pid != (int)getpid())
		return;

	drop_pid_file(get_pid_file_pathname_cb());
}

int	zbx_sigusr_send(int flags, const char *pid_file_pathname)
{
	int	ret = FAIL;
	char	error[256];
#ifdef HAVE_SIGQUEUE
	pid_t	pid;

	if (SUCCEED == read_pid_file(pid_file_pathname, &pid, error, sizeof(error)))
	{
		union sigval	s;

		s.sival_ptr = NULL;
		s.ZBX_SIVAL_INT = flags;

		if (-1 != sigqueue(pid, SIGUSR1, s))
		{
			zbx_error("command sent successfully");
			ret = SUCCEED;
		}
		else
		{
			zbx_snprintf(error, sizeof(error), "cannot send command to PID [%d]: %s",
					(int)pid, zbx_strerror(errno));
		}
	}
#else
	ZBX_UNUSED(flags);
	zbx_snprintf(error, sizeof(error), "operation is not supported on the given operating system");
#endif
	if (SUCCEED != ret)
		zbx_error("%s", error);

	return ret;
}
