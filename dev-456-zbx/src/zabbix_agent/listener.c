/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"
#include "listener.h"

#include "comms.h"
#include "cfg.h"
#include "zbxconf.h"
#include "stats.h"
#include "sysinfo.h"
#include "log.h"

#if defined(ZABBIX_SERVICE)
#	include "service.h"
#elif defined(ZABBIX_DAEMON) /* ZABBIX_SERVICE */
#	include "daemon.h"
#endif /* ZABBIX_DAEMON */

static void	process_listener(zbx_sock_t *s)
{
	AGENT_RESULT	result;

	char	*command;
	char	**value = NULL;
	int		ret;

	if( SUCCEED == (ret = zbx_tcp_recv(s, &command)) )
	{
		zbx_rtrim(command, "\r\n\0");

		zabbix_log(LOG_LEVEL_DEBUG, "Requested [%s]", command);

		init_result(&result);
		process(command, 0, &result);

		if( NULL == (value = GET_TEXT_RESULT(&result)) )
			value = GET_MSG_RESULT(&result);

		if(value)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Sending back [%s]", *value);
			ret = zbx_tcp_send(s, *value);
		}

		free_result(&result);
	}

	if( FAIL == ret )
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Process listener error: %s", zbx_tcp_strerror());
	}
}

ZBX_THREAD_ENTRY(listener_thread, pSock)
{
#if defined(ZABBIX_DAEMON)
	struct	sigaction phan;
#endif
	int
		ret,
		local_request_failed = 0;

	zbx_sock_t	s;

	assert(pSock);

	zabbix_log( LOG_LEVEL_INFORMATION, "zabbix_agentd listener started");

#if defined(ZABBIX_DAEMON)
/*	phan.sa_handler = child_signal_handler;*/
        phan.sa_sigaction = child_signal_handler;
	sigemptyset(&phan.sa_mask);
	phan.sa_flags = SA_SIGINFO;
	sigaction(SIGALRM, &phan, NULL);

#endif

	memcpy(&s, ((zbx_sock_t *)pSock), sizeof(zbx_sock_t));

	while(ZBX_IS_RUNNING)
	{
		zbx_setproctitle("waiting for connection");

		if( SUCCEED == (ret = zbx_tcp_accept(&s)) )
		{
			local_request_failed = 0;     /* Reset consecutive errors counter */

			zbx_setproctitle("processing request");

			zabbix_log(LOG_LEVEL_DEBUG, "Processing request.");
			if( SUCCEED == (ret = zbx_tcp_check_security(&s, CONFIG_HOSTS_ALLOWED, 0)) )
			{
				process_listener(&s);
			}

			zbx_tcp_unaccept(&s);
		}

		if( SUCCEED == ret )	continue;

		zabbix_log(LOG_LEVEL_DEBUG, "Listener error: %s", zbx_tcp_strerror());

		if (local_request_failed++ > 1000)
		{
			zabbix_log( LOG_LEVEL_WARNING, "Too many consecutive errors on accept() call.");
			local_request_failed = 0;
		}

		if(ZBX_IS_RUNNING)
			zbx_sleep(1);
	}

	zabbix_log( LOG_LEVEL_INFORMATION, "zabbix_agentd listener stopped");

	ZBX_DO_EXIT();

	zbx_thread_exit(0);
}
