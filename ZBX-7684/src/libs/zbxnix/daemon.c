/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
#include "daemon.h"

#include "pid.h"
#include "cfg.h"
#include "log.h"
#include "zbxself.h"

#include "fatal.h"
#include "sighandler.h"
#include "sigcommon.h"

char		*CONFIG_PID_FILE = NULL;
static int	parent_pid = -1;

/******************************************************************************
 *                                                                            *
 * Function: user1_signal_handler                                             *
 *                                                                            *
 * Purpose: handle user signal SIGUSR1                                        *
 *                                                                            *
 ******************************************************************************/
static void	user1_signal_handler(int sig, siginfo_t *siginfo, void *context)
{
	SIG_CHECK_PARAMS(sig, siginfo, context);

	zabbix_log(LOG_LEVEL_DEBUG, "Got signal [signal:%d(%s),sender_pid:%d,sender_uid:%d,value_int:%d].",
			sig, get_signal_name(sig),
			SIG_CHECKED_FIELD(siginfo, si_pid),
			SIG_CHECKED_FIELD(siginfo, si_uid),
			SIG_CHECKED_FIELD(siginfo, si_value.ZBX_SIVAL_INT));
#ifdef HAVE_SIGQUEUE
	if (!SIG_PARENT_PROCESS)
	{
		extern void	zbx_sigusr_handler(zbx_task_t task);

		zbx_sigusr_handler(SIG_CHECKED_FIELD(siginfo, si_value.ZBX_SIVAL_INT));
	}
	else if (ZBX_TASK_CONFIG_CACHE_RELOAD == SIG_CHECKED_FIELD(siginfo, si_value.ZBX_SIVAL_INT))
	{
		extern unsigned char	daemon_type;

		if (0 != (daemon_type & ZBX_DAEMON_TYPE_PROXY_PASSIVE))
		{
			zabbix_log(LOG_LEVEL_WARNING, "forced reloading of the configuration cache"
					" cannot be performed for a passive proxy");
		}
		else
		{
			union sigval	s;
			extern pid_t	*threads;

			s.ZBX_SIVAL_INT = ZBX_TASK_CONFIG_CACHE_RELOAD;

			/* threads[0] is configuration syncer (it is set in proxy.c and server.c) */
			if (NULL != threads && -1 != sigqueue(threads[0], SIGUSR1, s))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "the signal is redirected to"
						" the configuration syncer");
			}
			else
			{
				zabbix_log(LOG_LEVEL_ERR, "failed to redirect signal: %s",
						zbx_strerror(errno));
			}
		}
	}
#endif
}


/******************************************************************************
 *                                                                            *
 * Function: pipe_signal_handler                                              *
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
 * Function: zbx_set_daemon_signal_handlers                                   *
 *                                                                            *
 * Purpose: set the signal handlers used by daemons                           *
 *                                                                            *
 ******************************************************************************/
static void	set_daemon_signal_handlers()
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
 * Function: daemon_start                                                     *
 *                                                                            *
 * Purpose: init process as daemon                                            *
 *                                                                            *
 * Parameters: allow_root - allow root permission for application             *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: it doesn't allow running under 'root' if allow_root is zero      *
 *                                                                            *
 ******************************************************************************/
int	daemon_start(int allow_root)
{
	pid_t			pid;
	struct passwd		*pwd;
	char			user[7] = "zabbix";

	if (0 == allow_root && (0 == getuid() || 0 == getgid()))	/* running as root? */
	{
		pwd = getpwnam(user);

		if (NULL == pwd)
		{
			zbx_error("user %s does not exist", user);
			zbx_error("cannot run as root!");
			exit(FAIL);
		}

		if (-1 == setgid(pwd->pw_gid))
		{
			zbx_error("cannot setgid to %s: %s", user, zbx_strerror(errno));
			exit(FAIL);
		}

#ifdef HAVE_FUNCTION_INITGROUPS
		if (-1 == initgroups(user, pwd->pw_gid))
		{
			zbx_error("cannot initgroups to %s: %s", user, zbx_strerror(errno));
			exit(FAIL);
		}
#endif

		if (-1 == setuid(pwd->pw_uid))
		{
			zbx_error("cannot setuid to %s: %s", user, zbx_strerror(errno));
			exit(FAIL);
		}

#ifdef HAVE_FUNCTION_SETEUID
		if (-1 == setegid(pwd->pw_gid) || -1 == seteuid(pwd->pw_uid))
		{
			zbx_error("cannot setegid or seteuid to %s: %s", user, zbx_strerror(errno));
			exit(FAIL);
		}
#endif
	}

	if (0 != (pid = zbx_fork()))
		exit(0);

	setsid();

	signal(SIGHUP, SIG_IGN);

	if (0 != (pid = zbx_fork()))
		exit(0);

	if (-1 == chdir("/"))	/* this is to eliminate warning: ignoring return value of chdir */
		assert(0);

	umask(0002);

	redirect_std(CONFIG_LOG_FILE);

	if (FAIL == create_pid_file(CONFIG_PID_FILE))
		exit(FAIL);

	atexit(daemon_stop);

	parent_pid = (int)getpid();

	zbx_set_common_signal_handlers();
	set_daemon_signal_handlers();

	/* Set SIGCHLD now to avoid race conditions when a child process is created before */
	/* sigaction() is called. To avoid problems when scripts exit in zbx_execute() and */
	/* other cases, SIGCHLD is set to SIG_DFL in zbx_child_fork(). */
	zbx_set_child_signal_handler();

	return MAIN_ZABBIX_ENTRY();
}

void	daemon_stop()
{
	/* this function is registered using atexit() to be called when we terminate */
	/* there should be nothing like logging or calls to exit() beyond this point */

	if (parent_pid != (int)getpid())
		return;

	drop_pid_file(CONFIG_PID_FILE);
}

int	zbx_sigusr_send(zbx_task_t task)
{
	int	ret = FAIL;
	char	error[256];
#ifdef HAVE_SIGQUEUE
	pid_t	pid;

	if (SUCCEED == read_pid_file(CONFIG_PID_FILE, &pid, error, sizeof(error)))
	{
		union sigval	s;

		s.ZBX_SIVAL_INT = task;

		if (-1 != sigqueue(pid, SIGUSR1, s))
		{
			printf("command sent successfully\n");
			ret = SUCCEED;
		}
		else
		{
			zbx_snprintf(error, sizeof(error), "cannot send command to PID [%d]: %s",
					(int)pid, zbx_strerror(errno));
		}
	}
#else
	zbx_snprintf(error, sizeof(error), "operation is not supported on the given operating system");
#endif

	if (SUCCEED != ret)
		printf("%s\n", error);

	return ret;
}
