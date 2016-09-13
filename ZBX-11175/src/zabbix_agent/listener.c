/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

#include "comms.h"
#include "cfg.h"
#include "zbxconf.h"
#include "stats.h"
#include "sysinfo.h"
#include "log.h"

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
	int		ret, local_request_failed = 0;
	zbx_sock_t	s;

	assert(args);
	assert(((zbx_thread_args_t *)args)->args);

	zabbix_log(LOG_LEVEL_INFORMATION, "agent #%d started [listener]", ((zbx_thread_args_t *)args)->thread_num);

	memcpy(&s, (zbx_sock_t *)((zbx_thread_args_t *)args)->args, sizeof(zbx_sock_t));

	zbx_free(args);

	while (ZBX_IS_RUNNING())
	{
		zbx_setproctitle("listener [waiting for connection]");

		if (SUCCEED == (ret = zbx_tcp_accept(&s)))
		{
			local_request_failed = 0;     /* reset consecutive errors counter */

			zbx_setproctitle("listener [processing request]");
			zabbix_log(LOG_LEVEL_DEBUG, "Processing request.");

			if (SUCCEED == (ret = zbx_tcp_check_security(&s, CONFIG_HOSTS_ALLOWED, 0)))
				process_listener(&s);

			zbx_tcp_unaccept(&s);
		}

		if (SUCCEED == ret)
			continue;

		zabbix_log(LOG_LEVEL_DEBUG, "Listener error: %s", zbx_tcp_strerror());

		if (local_request_failed++ > 1000)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Too many consecutive errors on accept() call.");
			local_request_failed = 0;
		}

		if (ZBX_IS_RUNNING())
			zbx_sleep(1);
	}

#ifdef _WINDOWS
	zabbix_log(LOG_LEVEL_INFORMATION, "zabbix_agentd listener stopped");

	ZBX_DO_EXIT();

	zbx_thread_exit(0);
#endif
}
