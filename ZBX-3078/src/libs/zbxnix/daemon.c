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

char		*CONFIG_PID_FILE = NULL;
static int	parent_pid = -1;

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
	zbx_set_daemon_signal_handlers();

	/* Set SIGCHLD now to avoid race conditions when a child process is created before */
	/* sigaction() is called. To avoid problems when scripts exit in zbx_execute() and */
	/* other cases, SIGCHLD is set to SIG_DFL in zbx_child_fork(). */
	zbx_set_child_signal_handler();

	zbx_setproctitle("main process");

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
