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
	char		**value = NULL;
	int		ret;

	if (SUCCEED == (ret = zbx_tcp_recv_to(s, CONFIG_TIMEOUT)))
	{
		zbx_rtrim(s->buffer, "\r\n");

		zabbix_log(LOG_LEVEL_DEBUG, "Requested [%s]", s->buffer);

		init_result(&result);

		if (SUCCEED == process(s->buffer, 0, &result))
		{
			if (NULL != (value = GET_TEXT_RESULT(&result)))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "Sending back [%s]", *value);
				ret = zbx_tcp_send_to(s, *value, CONFIG_TIMEOUT);
			}
		}
		else
		{
			value = GET_MSG_RESULT(&result);

			if (NULL != value)
			{
				static char	*buffer = NULL;
				static size_t	buffer_alloc = 256;
				size_t		buffer_offset = 0;

				if (NULL == buffer)
					buffer = zbx_malloc(buffer, buffer_alloc);

				zbx_strncpy_alloc(&buffer, &buffer_alloc, &buffer_offset,
						ZBX_NOTSUPPORTED, sizeof(ZBX_NOTSUPPORTED) - 1);
				buffer_offset++;
				zbx_strcpy_alloc(&buffer, &buffer_alloc, &buffer_offset, *value);

				ret = zbx_tcp_send_bytes_to(s, buffer, buffer_offset, CONFIG_TIMEOUT);
			}
			else
				ret = zbx_tcp_send_to(s, ZBX_NOTSUPPORTED, CONFIG_TIMEOUT);
		}

		free_result(&result);
	}

	if (FAIL == ret)
		zabbix_log(LOG_LEVEL_DEBUG, "Process listener error: %s", zbx_tcp_strerror());
}

ZBX_THREAD_ENTRY(listener_thread, args)
{
	int		ret, local_request_failed = 0, thread_num, thread_num2;
	zbx_sock_t	s;

	assert(args);
	assert(((zbx_thread_args_t *)args)->args);

	thread_num = ((zbx_thread_args_t *)args)->thread_num;
	thread_num2 = ((zbx_thread_args_t *)args)->thread_num2;

	zabbix_log(LOG_LEVEL_INFORMATION, "agent #%d started [listener #%d]", thread_num, thread_num2);

	memcpy(&s, (zbx_sock_t *)((zbx_thread_args_t *)args)->args, sizeof(zbx_sock_t));

	zbx_free(args);

	while (ZBX_IS_RUNNING())
	{
		zbx_setproctitle("listener #%d [waiting for connection]", thread_num2);

		if (SUCCEED == (ret = zbx_tcp_accept(&s)))
		{
			local_request_failed = 0;     /* reset consecutive errors counter */

			zbx_setproctitle("listener #%d [processing request]", thread_num2);

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
	ZBX_DO_EXIT();

	zbx_thread_exit(EXIT_SUCCESS);
#endif
}
