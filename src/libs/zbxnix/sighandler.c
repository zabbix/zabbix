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
#include "zbxthreads.h"

#include "fatal.h"
#include "sigcommon.h"

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
#	include "zbxcomms.h"
#endif

#define ZBX_EXIT_NONE		0
#define ZBX_EXIT_SUCCESS	1
#define ZBX_EXIT_FAILURE	2

static int	sig_parent_pid = -1;

static const pid_t	*child_pids = NULL;
static size_t		child_pid_count = 0;

void	set_sig_parent_pid(int in)
{
	sig_parent_pid = in;
}

int	get_sig_parent_pid(void)
{
	return sig_parent_pid;
}

typedef struct
{
	int	sig;
	int	pid;
	int	uid;
	int	code;
	int	status;
}
zbx_siginfo_t;

static volatile sig_atomic_t	sig_exiting;
static volatile sig_atomic_t	sig_exit_on_terminate = 1;
static zbx_on_exit_t		zbx_on_exit_cb = NULL;
static void 			*zbx_on_exit_args = NULL;

static zbx_siginfo_t	siginfo_exit = {-1, -1, -1, -1, -1};

static void	set_siginfo_exit(int sig, siginfo_t *siginfo)
{
	siginfo_exit.sig = sig;
	siginfo_exit.pid = SIG_CHECKED_FIELD(siginfo, si_pid);
	siginfo_exit.uid = SIG_CHECKED_FIELD(siginfo, si_uid);
	siginfo_exit.code = SIG_CHECKED_FIELD(siginfo, si_code);
	siginfo_exit.status = SIG_CHECKED_FIELD(siginfo, si_status);
}

void	zbx_set_exiting_with_fail(void)
{
	sig_exiting = ZBX_EXIT_FAILURE;
}

void	zbx_set_exiting_with_succeed(void)
{
	sig_exiting = ZBX_EXIT_SUCCESS;
}

int	ZBX_IS_RUNNING(void)
{
	return ZBX_EXIT_NONE == sig_exiting;
}

int	ZBX_EXIT_STATUS(void)
{
	return ZBX_EXIT_SUCCESS == sig_exiting ? SUCCEED : FAIL;
}

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
	ZBX_UNUSED(sig);
	ZBX_UNUSED(siginfo);
	ZBX_UNUSED(context);

	zbx_alarm_flag_set();	/* set alarm flag */
}

/******************************************************************************
 *                                                                            *
 * Purpose: log signal information if it was shutdown cause                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_log_exit_signal(void)
{
	int	zbx_log_level_temp;

	switch (siginfo_exit.sig)
	{
		case -1:
			return;
		case SIGCHLD:
			zabbix_log(LOG_LEVEL_CRIT, "One child process died (PID:%d,exitcode/signal:%d). Exiting ...",
					siginfo_exit.pid, siginfo_exit.status);
			return;
		case SIGINT:
		case SIGQUIT:
		case SIGHUP:
		case SIGTERM:
		case SIGUSR2:
			/* temporary variable is used to avoid compiler warning */
			zbx_log_level_temp = sig_parent_pid == siginfo_exit.pid ? LOG_LEVEL_DEBUG : LOG_LEVEL_WARNING;

			zabbix_log(zbx_log_level_temp,
					"Got signal [signal:%d(%s),sender_pid:%d,sender_uid:%d,"
					"reason:%d]. Exiting ...",
					siginfo_exit.sig, get_signal_name(siginfo_exit.sig), siginfo_exit.pid,
					siginfo_exit.uid, siginfo_exit.code);

			return;
		default:
			zabbix_log(LOG_LEVEL_WARNING,
					"Got signal [signal:%d(%s),sender_pid:%d,sender_uid:%d,"
					"reason:%d]. Exiting ...",
					siginfo_exit.sig, get_signal_name(siginfo_exit.sig), siginfo_exit.pid,
					siginfo_exit.uid, siginfo_exit.code);
			return;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: handle terminate signals: SIGHUP, SIGINT, SIGTERM, SIGUSR2        *
 *                                                                            *
 ******************************************************************************/
static void	terminate_signal_handler(int sig, siginfo_t *siginfo, void *context)
{
	ZBX_UNUSED(context);

	if (!SIG_PARENT_PROCESS)
	{
		/* the parent process can either politely ask a child process to finish it's work and perform cleanup */
		/* by sending SIGUSR2 or terminate child process immediately without cleanup by sending SIGHUP        */
		if (SIGHUP == sig)
			exit_with_failure();

		if (SIGUSR2 == sig)
			sig_exiting = ZBX_EXIT_SUCCESS;
	}
	else
	{
		if (ZBX_EXIT_NONE == sig_exiting)
		{
			sig_exiting = ZBX_EXIT_SUCCESS;

			if (-1 == siginfo_exit.sig)
				set_siginfo_exit(sig, siginfo);

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
			zbx_tls_free_on_signal();
#endif
			if (0 != sig_exit_on_terminate)
			{
				zbx_log_exit_signal();
				zbx_on_exit_cb(SUCCEED, zbx_on_exit_args);
			}
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

	if (FAIL == zbx_is_child_pid(siginfo->si_pid, child_pids, child_pid_count))
		return;

	if (!SIG_PARENT_PROCESS)
		exit_with_failure();

	if (ZBX_EXIT_NONE == sig_exiting)
	{
		sig_exiting = ZBX_EXIT_FAILURE;

		if (-1 == siginfo_exit.sig)
			set_siginfo_exit(sig, siginfo);

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		zbx_tls_free_on_signal();
#endif
	}
}

#undef ZBX_EXIT_NONE
#undef ZBX_EXIT_SUCCESS
#undef ZBX_EXIT_FAILURE

/******************************************************************************
 *                                                                            *
 * Purpose: set the commonly used signal handlers and the callback function   *
 *          which would run when terminating signal handler                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_set_common_signal_handlers(zbx_on_exit_t zbx_on_exit_cb_arg)
{
	struct sigaction	phan;

	zbx_on_exit_cb = zbx_on_exit_cb_arg;
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

	if (0 > zbx_sigmask(SIG_BLOCK, &mask, orig_mask))
		zabbix_log(LOG_LEVEL_WARNING, "cannot set signal mask to block the signal");
}

/******************************************************************************
 *                                                                            *
 * Purpose: unblock signals after blocking                                    *
 *                                                                            *
 ******************************************************************************/
void	zbx_unblock_signals(const sigset_t *orig_mask)
{
	if (0 > zbx_sigmask(SIG_SETMASK, orig_mask, NULL))
		zabbix_log(LOG_LEVEL_WARNING,"cannot restore signal mask");
}

void	zbx_set_on_exit_args(void *args)
{
	zbx_on_exit_args = args;
}

void	zbx_set_child_pids(const pid_t *pids, size_t pid_num)
{
	child_pids = pids;
	child_pid_count = pid_num;
}
