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
#include "listener.h"
#include "zbxself.h"

#include "comms.h"
#include "cfg.h"
#include "zbxconf.h"
#include "stats.h"
#include "sysinfo.h"
#include "log.h"

extern unsigned char process_type;

#if defined(ZABBIX_SERVICE)
#	include "service.h"
#elif defined(ZABBIX_DAEMON)
#	include "daemon.h"
#endif

static void	process_listener(zbx_sock_t *s)
{
	AGENT_RESULT	result;
	char		*command;
	char		**value = NULL;
	int		ret;

	if (SUCCEED == (ret = zbx_tcp_recv_to(s, &command, CONFIG_TIMEOUT)))
	{
		zbx_rtrim(command, "\r\n");

		zabbix_log(LOG_LEVEL_DEBUG, "Requested [%s]", command);

		init_result(&result);
		process(command, 0, &result);

		if (NULL == (value = GET_TEXT_RESULT(&result)))
			value = GET_MSG_RESULT(&result);

		if (NULL != value)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Sending back [%s]", *value);
			ret = zbx_tcp_send_to(s, *value, CONFIG_TIMEOUT);
		}

		free_result(&result);
	}

	if (FAIL == ret)
		zabbix_log(LOG_LEVEL_DEBUG, "Process listener error: %s", zbx_tcp_strerror());
}

ZBX_THREAD_ENTRY(listener_thread, args)
{
	int		ret, local_request_failed = 0, server_num, process_num;
	zbx_sock_t	s;
#ifndef _WINDOWS
	sigset_t	mask, orig_mask;
#endif

	assert(args);
	assert(((zbx_thread_args_t *)args)->args);

	process_type = ZBX_PROCESS_TYPE_LISTENER;

	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "agent #%d started [listener #%d]", server_num, process_num);

	memcpy(&s, (zbx_sock_t *)((zbx_thread_args_t *)args)->args, sizeof(zbx_sock_t));

	zbx_free(args);

#ifndef _WINDOWS
	sigemptyset (&mask);
	sigaddset (&mask, SIGUSR1);
#endif

	while (ZBX_IS_RUNNING())
	{
#ifndef _WINDOWS
		if (sigprocmask(SIG_BLOCK, &mask, &orig_mask) < 0)
			zabbix_log(LOG_LEVEL_DEBUG, "could not set sigprocmask to block the user signal in listener process");
#endif

		zbx_setproctitle("listener #%d [waiting for connection]", process_num);

		if (SUCCEED == (ret = zbx_tcp_accept(&s)))
		{
			local_request_failed = 0;     /* reset consecutive errors counter */

			zbx_setproctitle("listener #%d [processing request]", process_num);

			if (SUCCEED == (ret = zbx_tcp_check_security(&s, CONFIG_HOSTS_ALLOWED, 0)))
				process_listener(&s);

			zbx_tcp_unaccept(&s);
		}

		if (SUCCEED == ret)
			goto unblock;

		zabbix_log(LOG_LEVEL_DEBUG, "Listener error: %s", zbx_tcp_strerror());

		if (local_request_failed++ > 1000)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Too many consecutive errors on accept() call.");
			local_request_failed = 0;
		}

		if (ZBX_IS_RUNNING())
			zbx_sleep(1);

unblock:
#ifndef _WINDOWS
		if (sigprocmask(SIG_SETMASK, &orig_mask, NULL) < 0)
			zabbix_log(LOG_LEVEL_DEBUG, "could not restore sigprocmask");
#endif
	}

#ifdef _WINDOWS
	ZBX_DO_EXIT();

	zbx_thread_exit(0);
#endif
}
