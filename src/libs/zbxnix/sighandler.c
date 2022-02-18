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

#include "sighandler.h"

#include "common.h"
#include "log.h"
#include "fatal.h"
#include "sigcommon.h"
#include "zbxcrypto.h"
#include "daemon.h"

int			sig_parent_pid = -1;
volatile sig_atomic_t	sig_exiting;
static volatile sig_atomic_t	sig_exit_on_terminate = 1;

static void	log_fatal_signal(int sig, siginfo_t *siginfo, void *context)
{
	SIG_CHECK_PARAMS(sig, siginfo, context);

	zabbix_log(LOG_LEVEL_CRIT, "Got signal [signal:%d(%s),reason:%d,refaddr:%p]. Crashing ...",
			sig, get_signal_name(sig),
			SIG_CHECKED_FIELD(siginfo, si_code),
			SIG_CHECKED_FIELD_TYPE(siginfo, si_addr, void *));
}

static void	exit_with_failure(void)
{
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_free_on_signal();
#endif
	_exit(EXIT_FAILURE);
}

/******************************************************************************
 *                                                                            *
 * Purpose: handle fatal signals: SIGILL, SIGFPE, SIGSEGV, SIGBUS             *
 *                                                                            *
 ******************************************************************************/
static void	fatal_signal_handler(int sig, siginfo_t *siginfo, void *context)
{
	log_fatal_signal(sig, siginfo, context);
	zbx_log_fatal_info(context, ZBX_FATAL_LOG_FULL_INFO);

	exit_with_failure();
}

/******************************************************************************
 *                                                                            *
 * Purpose: same as fatal_signal_handler() but customized for metric thread - *
 *          does not log memory map                                           *
 *                                                                            *
 ******************************************************************************/
static void	metric_thread_signal_handler(int sig, siginfo_t *siginfo, void *context)
{
	log_fatal_signal(sig, siginfo, context);
	zbx_log_fatal_info(context, (ZBX_FATAL_LOG_PC_REG_SF | ZBX_FATAL_LOG_BACKTRACE));

	exit_with_failure();
}

/******************************************************************************
 *                                                                            *
 * Purpose: handle alarm signal SIGALRM                                       *
 *                                                                            *
 ******************************************************************************/
static void	alarm_signal_handler(int sig, siginfo_t *siginfo, void *context)
{
	SIG_CHECK_PARAMS(sig, siginfo, context);

	zbx_alarm_flag_set();	/* set alarm flag */
}

/******************************************************************************
 *                                                                            *
 * Purpose: handle terminate signals: SIGHUP, SIGINT, SIGTERM, SIGUSR2        *
 *                                                                            *
 ******************************************************************************/
static void	terminate_signal_handler(int sig, siginfo_t *siginfo, void *context)
{
	int zbx_log_level_temp;

	if (!SIG_PARENT_PROCESS)
	{
		/* the parent process can either politely ask a child process to finish it's work and perform cleanup */
		/* by sending SIGUSR2 or terminate child process immediately without cleanup by sending SIGHUP        */
		if (SIGHUP == sig)
			exit_with_failure();

		if (SIGUSR2 == sig)
			sig_exiting = 1;
	}
	else
	{
		SIG_CHECK_PARAMS(sig, siginfo, context);

		if (ZBX_EXIT_NONE == sig_exiting)
		{
			sig_exiting = ZBX_EXIT_SUCCESS;

			/* temporary variable is used to avoid compiler warning */
			zbx_log_level_temp = sig_parent_pid == SIG_CHECKED_FIELD(siginfo, si_pid) ?
					LOG_LEVEL_DEBUG : LOG_LEVEL_WARNING;
			zabbix_log(zbx_log_level_temp,
					"Got signal [signal:%d(%s),sender_pid:%d,sender_uid:%d,"
					"reason:%d]. Exiting ...",
					sig, get_signal_name(sig),
					SIG_CHECKED_FIELD(siginfo, si_pid),
					SIG_CHECKED_FIELD(siginfo, si_uid),
					SIG_CHECKED_FIELD(siginfo, si_code));

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
			zbx_tls_free_on_signal();
#endif
			if (0 != sig_exit_on_terminate)
				zbx_on_exit(SUCCEED);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: handle child signal SIGCHLD                                       *
 *                                                                            *
 ******************************************************************************/
static void	child_signal_handler(int sig, siginfo_t *siginfo, void *context)
{
	SIG_CHECK_PARAMS(sig, siginfo, context);

	if (!SIG_PARENT_PROCESS)
		exit_with_failure();

	if (ZBX_EXIT_NONE == sig_exiting)
	{
		sig_exiting = ZBX_EXIT_FAILURE;
		zabbix_log(LOG_LEVEL_CRIT, "One child process died (PID:%d,exitcode/signal:%d). Exiting ...",
				SIG_CHECKED_FIELD(siginfo, si_pid), SIG_CHECKED_FIELD(siginfo, si_status));

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		zbx_tls_free_on_signal();
#endif
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: set the commonly used signal handlers                             *
 *                                                                            *
 ******************************************************************************/
void	zbx_set_common_signal_handlers(void)
{
	struct sigaction	phan;

	sig_parent_pid = (int)getpid();

	sigemptyset(&phan.sa_mask);
	phan.sa_flags = SA_SIGINFO;

	phan.sa_sigaction = terminate_signal_handler;
	sigaction(SIGINT, &phan, NULL);
	sigaction(SIGQUIT, &phan, NULL);
	sigaction(SIGHUP, &phan, NULL);
	sigaction(SIGTERM, &phan, NULL);
	sigaction(SIGUSR2, &phan, NULL);

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
 * Purpose: make main process to exit on terminate signals                    *
 *                                                                            *
 ******************************************************************************/
void	zbx_set_exit_on_terminate(void)
{
	sig_exit_on_terminate = 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: make main process to set exit flag and continue to work on        *
 *          terminate signals                                                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_unset_exit_on_terminate(void)
{
	sig_exit_on_terminate = 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: set the handlers for child process signals                        *
 *                                                                            *
 ******************************************************************************/
void 	zbx_set_child_signal_handler(void)
{
	struct sigaction	phan;

	sig_parent_pid = (int)getpid();

	sigemptyset(&phan.sa_mask);
	phan.sa_flags = SA_SIGINFO | SA_NOCLDSTOP;

	phan.sa_sigaction = child_signal_handler;
	sigaction(SIGCHLD, &phan, NULL);
}

void	zbx_unset_child_signal_handler(void)
{
	signal(SIGCHLD, SIG_DFL);
}

/******************************************************************************
 *                                                                            *
 * Purpose: set the handlers for child process signals                        *
 *                                                                            *
 ******************************************************************************/
void 	zbx_set_metric_thread_signal_handler(void)
{
	struct sigaction	phan;

	sig_parent_pid = (int)getpid();

	sigemptyset(&phan.sa_mask);
	phan.sa_flags = SA_SIGINFO;

	phan.sa_sigaction = metric_thread_signal_handler;
	sigaction(SIGILL, &phan, NULL);
	sigaction(SIGFPE, &phan, NULL);
	sigaction(SIGSEGV, &phan, NULL);
	sigaction(SIGBUS, &phan, NULL);
}

/******************************************************************************
 *                                                                            *
 * Purpose: block signals to avoid interruption                               *
 *                                                                            *
 ******************************************************************************/
void	zbx_block_signals(sigset_t *orig_mask)
{
	sigset_t	mask;

	sigemptyset(&mask);
	sigaddset(&mask, SIGUSR1);
	sigaddset(&mask, SIGUSR2);
	sigaddset(&mask, SIGTERM);
	sigaddset(&mask, SIGINT);
	sigaddset(&mask, SIGQUIT);

	if (0 > sigprocmask(SIG_BLOCK, &mask, orig_mask))
		zabbix_log(LOG_LEVEL_WARNING, "cannot set sigprocmask to block the signal");
}

/******************************************************************************
 *                                                                            *
 * Purpose: unblock signals after blocking                                    *
 *                                                                            *
 ******************************************************************************/
void	zbx_unblock_signals(const sigset_t *orig_mask)
{
	if (0 > sigprocmask(SIG_SETMASK, orig_mask, NULL))
		zabbix_log(LOG_LEVEL_WARNING,"cannot restore sigprocmask");
}
