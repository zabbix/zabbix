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

#include "daemon.h"

#include "common.h"
#include "pid.h"
#include "cfg.h"
#include "log.h"
#include "control.h"

#include "fatal.h"
#include "sighandler.h"
#include "sigcommon.h"

#if defined(__linux__)
#define ZBX_PID_FILE_TIMEOUT 20
#define ZBX_PID_FILE_SLEEP_TIME 100000000
#endif

char		*CONFIG_PID_FILE = NULL;
static int	parent_pid = -1;

extern pid_t	*threads;
extern int	threads_num;

#ifdef HAVE_SIGQUEUE
extern unsigned char	program_type;
#endif

extern int	get_process_info_by_thread(int local_server_num, unsigned char *local_process_type,
		int *local_process_num);

static void	(*zbx_sigusr_handler)(int flags);

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
			if (SUCCEED != zabbix_increase_log_level())
			{
				zabbix_log(LOG_LEVEL_INFORMATION, "cannot increase log level:"
						" maximum level has been already set");
			}
			else
			{
				zabbix_log(LOG_LEVEL_INFORMATION, "log level has been increased to %s",
						zabbix_get_log_level_string());
			}
			break;
		case ZBX_RTC_LOG_LEVEL_DECREASE:
			if (SUCCEED != zabbix_decrease_log_level())
			{
				zabbix_log(LOG_LEVEL_INFORMATION, "cannot decrease log level:"
						" minimum level has been already set");
			}
			else
			{
				zabbix_log(LOG_LEVEL_INFORMATION, "log level has been decreased to %s",
						zabbix_get_log_level_string());
			}
			break;
		default:
			if (NULL != zbx_sigusr_handler)
				zbx_sigusr_handler(flags);
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

	for (i = 0; i < threads_num; i++)
	{
		if (FAIL == get_process_info_by_thread(i + 1, &process_type, &process_num))
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

		if (-1 != sigqueue(threads[i], SIGUSR1, s))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "the signal was redirected to \"%s\" process"
					" pid:%d", get_process_type_string(process_type), threads[i]);
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

	for (i = 0; i < threads_num; i++)
	{
		if ((0 != pid && threads[i] != pid) || 0 == threads[i])
			continue;

		found = 1;

		if (-1 != sigqueue(threads[i], SIGUSR1, s))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "the signal was redirected to process pid:%d",	threads[i]);
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

void	zbx_set_sigusr_handler(void (*handler)(int flags))
{
	zbx_sigusr_handler = handler;
}

/******************************************************************************
 *                                                                            *
 * Purpose: handle user signal SIGUSR1                                        *
 *                                                                            *
 ******************************************************************************/
static void	user1_signal_handler(int sig, siginfo_t *siginfo, void *context)
{
#ifdef HAVE_SIGQUEUE
	int	flags;
	int	scope;
#endif
	SIG_CHECK_PARAMS(sig, siginfo, context);

	zabbix_log(LOG_LEVEL_DEBUG, "Got signal [signal:%d(%s),sender_pid:%d,sender_uid:%d,value_int:%d(0x%08x)].",
			sig, get_signal_name(sig),
			SIG_CHECKED_FIELD(siginfo, si_pid),
			SIG_CHECKED_FIELD(siginfo, si_uid),
			SIG_CHECKED_FIELD(siginfo, si_value.ZBX_SIVAL_INT),
			(unsigned int)SIG_CHECKED_FIELD(siginfo, si_value.ZBX_SIVAL_INT));
#ifdef HAVE_SIGQUEUE
	flags = SIG_CHECKED_FIELD(siginfo, si_value.ZBX_SIVAL_INT);

	if (!SIG_PARENT_PROCESS)
	{
		common_sigusr_handler(flags);
		return;
	}

	if (NULL == threads)
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot redirect signal: server is either shutting down"
				" or is running in standby mode");
		return;
	}

	if (0 == (program_type & ZBX_PROGRAM_TYPE_AGENTD))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot redirect signal: runtime control signals are supported only by agent");
		return;
	}

	switch (ZBX_RTC_GET_MSG(flags))
	{
		case ZBX_RTC_LOG_LEVEL_INCREASE:
		case ZBX_RTC_LOG_LEVEL_DECREASE:
			scope = ZBX_RTC_GET_SCOPE(flags);

			if ((ZBX_RTC_LOG_SCOPE_FLAG | ZBX_RTC_LOG_SCOPE_PID) == scope)
			{
				zbx_signal_process_by_pid(ZBX_RTC_GET_DATA(flags), flags, NULL);
			}
			else
			{
				if (scope < ZBX_PROCESS_TYPE_EXT_FIRST)
				{
					zbx_signal_process_by_type(ZBX_RTC_GET_SCOPE(flags), ZBX_RTC_GET_DATA(flags),
							flags, NULL);
				}
			}

			/* call custom sigusr handler to handle log level changes for non worker processes */
			if (NULL != zbx_sigusr_handler)
				zbx_sigusr_handler(flags);

			break;
		case ZBX_RTC_USER_PARAMETERS_RELOAD:
			zbx_signal_process_by_type(ZBX_PROCESS_TYPE_ACTIVE_CHECKS, ZBX_RTC_GET_DATA(flags), flags, NULL);
			zbx_signal_process_by_type(ZBX_PROCESS_TYPE_LISTENER, ZBX_RTC_GET_DATA(flags), flags, NULL);
			break;
		default:
			if (NULL != zbx_sigusr_handler)
				zbx_sigusr_handler(flags);
	}
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: handle pipe signal SIGPIPE                                        *
 *                                                                            *
 ******************************************************************************/
static void	pipe_signal_handler(int sig, siginfo_t *siginfo, void *context)
{
	SIG_CHECK_PARAMS(sig, siginfo, context);

	zabbix_log(LOG_LEVEL_DEBUG, "Got signal [signal:%d(%s),sender_pid:%d]. Ignoring ...",
			sig, get_signal_name(sig),
			SIG_CHECKED_FIELD(siginfo, si_pid));
}

/******************************************************************************
 *                                                                            *
 * Purpose: set the signal handlers used by daemons                           *
 *                                                                            *
 ******************************************************************************/
static void	set_daemon_signal_handlers(void)
{
	struct sigaction	phan;

	sig_parent_pid = (int)getpid();

	sigemptyset(&phan.sa_mask);
	phan.sa_flags = SA_SIGINFO;

	phan.sa_sigaction = user1_signal_handler;
	sigaction(SIGUSR1, &phan, NULL);

	phan.sa_sigaction = pipe_signal_handler;
	sigaction(SIGPIPE, &phan, NULL);
}

/******************************************************************************
 *                                                                            *
 * Purpose: init process as daemon                                            *
 *                                                                            *
 * Parameters: allow_root - allow root permission for application             *
 *             user       - user on the system to which to drop the           *
 *                          privileges                                        *
 *             flags      - daemon startup flags                              *
 *                                                                            *
 * Comments: it doesn't allow running under 'root' if allow_root is zero      *
 *                                                                            *
 ******************************************************************************/
int	daemon_start(int allow_root, const char *user, unsigned int flags)
{
	struct passwd	*pwd;

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
				while (0 < pid_file_timeout && 0 != zbx_stat(CONFIG_PID_FILE, &stat_buff))
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
			assert(0);

		if (FAIL == zbx_redirect_stdio(LOG_TYPE_FILE == CONFIG_LOG_TYPE ? CONFIG_LOG_FILE : NULL))
			exit(EXIT_FAILURE);
	}

	if (FAIL == create_pid_file(CONFIG_PID_FILE))
		exit(EXIT_FAILURE);

	atexit(daemon_stop);

	parent_pid = (int)getpid();

	zbx_set_common_signal_handlers();
	set_daemon_signal_handlers();

	/* Set SIGCHLD now to avoid race conditions when a child process is created before */
	/* sigaction() is called. To avoid problems when scripts exit in zbx_execute() and */
	/* other cases, SIGCHLD is set to SIG_DFL in zbx_child_fork(). */
	zbx_set_child_signal_handler();

	return MAIN_ZABBIX_ENTRY(flags);
}

void	daemon_stop(void)
{
	/* this function is registered using atexit() to be called when we terminate */
	/* there should be nothing like logging or calls to exit() beyond this point */

	if (parent_pid != (int)getpid())
		return;

	drop_pid_file(CONFIG_PID_FILE);
}

int	zbx_sigusr_send(int flags)
{
	int	ret = FAIL;
	char	error[256];
#ifdef HAVE_SIGQUEUE
	pid_t	pid;

	if (SUCCEED == read_pid_file(CONFIG_PID_FILE, &pid, error, sizeof(error)))
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
