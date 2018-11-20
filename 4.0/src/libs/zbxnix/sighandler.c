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
#include "sighandler.h"

#include "log.h"
#include "fatal.h"
#include "sigcommon.h"
#include "../../libs/zbxcrypto/tls.h"

int	sig_parent_pid = -1;
int	sig_exiting = 0;

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
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_free_on_signal();
#endif
	exit(EXIT_FAILURE);
}

/******************************************************************************
 *                                                                            *
 * Function: fatal_signal_handler                                             *
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
 * Function: metric_thread_signal_handler                                     *
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
 * Function: alarm_signal_handler                                             *
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
 * Function: terminate_signal_handler                                         *
 *                                                                            *
 * Purpose: handle terminate signals: SIGQUIT, SIGINT, SIGTERM                *
 *                                                                            *
 ******************************************************************************/
static void	terminate_signal_handler(int sig, siginfo_t *siginfo, void *context)
{
	SIG_CHECK_PARAMS(sig, siginfo, context);

	if (!SIG_PARENT_PROCESS)
	{
		zabbix_log(sig_parent_pid == SIG_CHECKED_FIELD(siginfo, si_pid) || SIGINT == sig ?
				LOG_LEVEL_DEBUG : LOG_LEVEL_WARNING,
				"Got signal [signal:%d(%s),sender_pid:%d,sender_uid:%d,"
				"reason:%d]. %s ...",
				sig, get_signal_name(sig),
				SIG_CHECKED_FIELD(siginfo, si_pid),
				SIG_CHECKED_FIELD(siginfo, si_uid),
				SIG_CHECKED_FIELD(siginfo, si_code),
				SIGINT == sig ? "Ignoring" : "Exiting");

		/* ignore interrupt signal in children - the parent */
		/* process will send terminate signals instead      */
		if (SIGINT == sig)
			return;

		exit_with_failure();
	}
	else
	{
		if (0 == sig_exiting)
		{
			sig_exiting = 1;
			zabbix_log(sig_parent_pid == SIG_CHECKED_FIELD(siginfo, si_pid) ?
					LOG_LEVEL_DEBUG : LOG_LEVEL_WARNING,
					"Got signal [signal:%d(%s),sender_pid:%d,sender_uid:%d,"
					"reason:%d]. Exiting ...",
					sig, get_signal_name(sig),
					SIG_CHECKED_FIELD(siginfo, si_pid),
					SIG_CHECKED_FIELD(siginfo, si_uid),
					SIG_CHECKED_FIELD(siginfo, si_code));

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
			zbx_tls_free_on_signal();
#endif
			zbx_on_exit();
		}
	}
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
	SIG_CHECK_PARAMS(sig, siginfo, context);

	if (!SIG_PARENT_PROCESS)
		exit_with_failure();

	if (0 == sig_exiting)
	{
		sig_exiting = 1;
		zabbix_log(LOG_LEVEL_CRIT, "One child process died (PID:%d,exitcode/signal:%d). Exiting ...",
				SIG_CHECKED_FIELD(siginfo, si_pid), SIG_CHECKED_FIELD(siginfo, si_status));

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		zbx_tls_free_on_signal();
#endif
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
void	zbx_set_common_signal_handlers(void)
{
	struct sigaction	phan;

	sig_parent_pid = (int)getpid();

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
 * Function: zbx_set_child_signal_handler                                     *
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

/******************************************************************************
 *                                                                            *
 * Function: zbx_set_metric_thread_signal_handler                             *
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
