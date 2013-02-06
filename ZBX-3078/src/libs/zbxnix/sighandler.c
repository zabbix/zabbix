/*
** Zabbix
** Copyright (C) 2000-2013 Zabbix SIA
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
#include "sighandler.h"

#include "log.h"
#include "threads.h"
#include "fatal.h"

static int	parent_pid = -1;
static int	exiting = 0;

#define CHECKED_FIELD(siginfo, field)			(NULL == siginfo ? -1 : siginfo->field)
#define CHECKED_FIELD_TYPE(siginfo, field, type)	(NULL == siginfo ? (type)-1 : siginfo->field)
#define PARENT_PROCESS					(parent_pid == (int)getpid())

/******************************************************************************
 *                                                                            *
 * Function: check_signal                                                     *
 *                                                                            *
 * Purpose: check parameters passsed to a signal handler function             *
 *                                                                            *
 ******************************************************************************/
static void	check_signal(int sig, siginfo_t *siginfo, void *context)
{
	if (NULL == siginfo)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "received [signal:%d(%s)] with NULL siginfo",
				sig, get_signal_name(sig));
	}

	if (NULL == context)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "received [signal:%d(%s)] with NULL context",
				sig, get_signal_name(sig));
	}
}

/******************************************************************************
 *                                                                            *
 * Function: fatal_signal_handler                                             *
 *                                                                            *
 * Purpose: handle fatal signals: SIGILL, SIGILL, SIGSEGV, SIGBUS             *
 *                                                                            *
 ******************************************************************************/
static void	fatal_signal_handler(int sig, siginfo_t *siginfo, void *context)
{
	check_signal(sig, siginfo, context);

	zabbix_log(LOG_LEVEL_CRIT, "Got signal [signal:%d(%s),reason:%d,refaddr:%p]. Crashing ...",
			sig, get_signal_name(sig),
			CHECKED_FIELD(siginfo, si_code),
			CHECKED_FIELD_TYPE(siginfo, si_addr, void *));
	print_fatal_info(sig, siginfo, context);
	exit(FAIL);
}

/******************************************************************************
 *                                                                            *
 * Function: alarm_signal_handler                                             *
 *                                                                            *
 * Purpose: handle alarm signal SIGALRM                                       *
 *                                                                            *
 ******************************************************************************/
static void	alarm_signal_handler(int sig, siginfo_t *siginfo, void *context)
{
	check_signal(sig, siginfo, context);
}

/******************************************************************************
 *                                                                            *
 * Function: terminate_signal_handler                                         *
 *                                                                            *
 * Purpose: handle terminate signals: SIGQUIT, SIGINT, SIGTERM                *
 *                                                                            *
 ******************************************************************************/
static void	terminate_signal_handler(int sig, siginfo_t *siginfo, void *context)
{
	check_signal(sig, siginfo, context);

	if (!PARENT_PROCESS)
	{
		zabbix_log(parent_pid == CHECKED_FIELD(siginfo, si_pid) ?
				LOG_LEVEL_DEBUG : LOG_LEVEL_WARNING,
				"Got signal [signal:%d(%s),sender_pid:%d,sender_uid:%d,"
				"reason:%d]. Exiting ...",
				sig, get_signal_name(sig),
				CHECKED_FIELD(siginfo, si_pid),
				CHECKED_FIELD(siginfo, si_uid),
				CHECKED_FIELD(siginfo, si_code));
		exit(FAIL);
	}
	else
	{
		if (0 == exiting)
		{
			exiting = 1;
			zabbix_log(parent_pid == CHECKED_FIELD(siginfo, si_pid) ?
					LOG_LEVEL_DEBUG : LOG_LEVEL_WARNING,
					"Got signal [signal:%d(%s),sender_pid:%d,sender_uid:%d,"
					"reason:%d]. Exiting ...",
					sig, get_signal_name(sig),
					CHECKED_FIELD(siginfo, si_pid),
					CHECKED_FIELD(siginfo, si_uid),
					CHECKED_FIELD(siginfo, si_code));
			zbx_on_exit();
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: user1_signal_handler                                             *
 *                                                                            *
 * Purpose: handle user signal SIGUSR1                                        *
 *                                                                            *
 ******************************************************************************/
static void	user1_signal_handler(int sig, siginfo_t *siginfo, void *context)
{
	check_signal(sig, siginfo, context);

	zabbix_log(LOG_LEVEL_DEBUG, "Got signal [signal:%d(%s),sender_pid:%d,sender_uid:%d,value_int:%d].",
			sig, get_signal_name(sig),
			CHECKED_FIELD(siginfo, si_pid),
			CHECKED_FIELD(siginfo, si_uid),
			CHECKED_FIELD(siginfo, si_value.ZBX_SIVAL_INT));
#ifdef HAVE_SIGQUEUE
	if (!PARENT_PROCESS)
	{
		extern void	zbx_sigusr_handler(zbx_task_t task);

		zbx_sigusr_handler(CHECKED_FIELD(siginfo, si_value.ZBX_SIVAL_INT));
	}
	else if (ZBX_TASK_CONFIG_CACHE_RELOAD == CHECKED_FIELD(siginfo, si_value.ZBX_SIVAL_INT))
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
			if (-1 != sigqueue(threads[0], SIGUSR1, s))
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
	check_signal(sig, siginfo, context);

	zabbix_log(LOG_LEVEL_DEBUG, "Got signal [signal:%d(%s),sender_pid:%d]. Ignoring ...",
			sig, get_signal_name(sig),
			CHECKED_FIELD(siginfo, si_pid));
}

/******************************************************************************
 *                                                                            *
 * Function: child_signal_handler                                             *
 *                                                                            *
 * Purpose: handle child signal SIGCHLD                                       *
 *                                                                            *
 ******************************************************************************/
static void	child_signal_handler(int sig, siginfo_t *siginfo, void *context)
{
	check_signal(sig, siginfo, context);

	if (!PARENT_PROCESS)
		exit(FAIL);

	if (0 == exiting)
	{
		exiting = 1;
		zabbix_log(LOG_LEVEL_CRIT, "One child process died (PID:%d,exitcode/signal:%d). Exiting ...",
				CHECKED_FIELD(siginfo, si_pid), CHECKED_FIELD(siginfo, si_status));
		zbx_on_exit();
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_set_common_signal_handlers                                   *
 *                                                                            *
 * Purpose: set the commonly used signal handlers                             *
 *                                                                            *
 ******************************************************************************/
void	zbx_set_common_signal_handlers()
{
	struct sigaction	phan;

	parent_pid = (int)getpid();

	sigemptyset(&phan.sa_mask);
	phan.sa_flags = SA_SIGINFO;

	phan.sa_sigaction = terminate_signal_handler;
	sigaction(SIGINT, &phan, NULL);
	sigaction(SIGQUIT, &phan, NULL);
	sigaction(SIGTERM, &phan, NULL);

	phan.sa_sigaction = fatal_signal_handler;
	sigaction(SIGILL, &phan, NULL);
	sigaction(SIGFPE, &phan, NULL);
	sigaction(SIGSEGV, &phan, NULL);
	sigaction(SIGBUS, &phan, NULL);

	phan.sa_sigaction = alarm_signal_handler;
	sigaction(SIGALRM, &phan, NULL);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_set_daemon_signal_handlers                                   *
 *                                                                            *
 * Purpose: set the signal handlers used by daemons                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_set_daemon_signal_handlers()
{
	struct sigaction	phan;

	parent_pid = (int)getpid();

	sigemptyset(&phan.sa_mask);
	phan.sa_flags = SA_SIGINFO;

	phan.sa_sigaction = user1_signal_handler;
	sigaction(SIGUSR1, &phan, NULL);

	phan.sa_sigaction = pipe_signal_handler;
	sigaction(SIGPIPE, &phan, NULL);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_set_child_signal_handler                                     *
 *                                                                            *
 * Purpose: set the handlers for child process signals                        *
 *                                                                            *
 ******************************************************************************/

void 	zbx_set_child_signal_handler()
{
	struct sigaction	phan;

	parent_pid = (int)getpid();

	sigemptyset(&phan.sa_mask);
	phan.sa_flags = SA_SIGINFO;

	phan.sa_sigaction = child_signal_handler;
	sigaction(SIGCHLD, &phan, NULL);
}
