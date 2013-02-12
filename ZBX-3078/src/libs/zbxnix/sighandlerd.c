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
#include "sigcommon.h"

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
void	zbx_set_daemon_signal_handlers()
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
