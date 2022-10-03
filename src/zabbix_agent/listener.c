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

#include "listener.h"

#include "comms.h"
#include "zbxconf.h"
#include "sysinfo.h"
#include "log.h"

extern unsigned char			program_type;
extern ZBX_THREAD_LOCAL unsigned char	process_type;
extern ZBX_THREAD_LOCAL int		server_num, process_num;

#if defined(ZABBIX_SERVICE)
#	include "service.h"
#elif defined(ZABBIX_DAEMON)
#	include "daemon.h"
#endif

#include "zbxcrypto.h"
#include "../libs/zbxcrypto/tls_tcp_active.h"

#ifndef _WINDOWS
static volatile sig_atomic_t	need_update_userparam;
#endif

static void	process_listener(zbx_socket_t *s)
{
	AGENT_RESULT	result;
	char		**value = NULL;
	int		ret;

	if (SUCCEED == (ret = zbx_tcp_recv_to(s, CONFIG_TIMEOUT)))
	{
		zbx_rtrim(s->buffer, "\r\n");

		zabbix_log(LOG_LEVEL_DEBUG, "Requested [%s]", s->buffer);

		init_result(&result);

		if (SUCCEED == process(s->buffer, PROCESS_WITH_ALIAS, &result))
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

				zabbix_log(LOG_LEVEL_DEBUG, "Sending back [" ZBX_NOTSUPPORTED ": %s]", *value);

				if (NULL == buffer)
					buffer = (char *)zbx_malloc(buffer, buffer_alloc);

				zbx_strncpy_alloc(&buffer, &buffer_alloc, &buffer_offset,
						ZBX_NOTSUPPORTED, ZBX_CONST_STRLEN(ZBX_NOTSUPPORTED));
				buffer_offset++;
				zbx_strcpy_alloc(&buffer, &buffer_alloc, &buffer_offset, *value);

				ret = zbx_tcp_send_bytes_to(s, buffer, buffer_offset, CONFIG_TIMEOUT);
			}
			else
			{
				zabbix_log(LOG_LEVEL_DEBUG, "Sending back [" ZBX_NOTSUPPORTED "]");

				ret = zbx_tcp_send_to(s, ZBX_NOTSUPPORTED, CONFIG_TIMEOUT);
			}
		}

		free_result(&result);
	}

	if (FAIL == ret)
		zabbix_log(LOG_LEVEL_DEBUG, "Process listener error: %s", zbx_socket_strerror());
}

#ifndef _WINDOWS
static void	zbx_listener_sigusr_handler(int flags)
{
	if (ZBX_RTC_USER_PARAMETERS_RELOAD == ZBX_RTC_GET_MSG(flags))
		need_update_userparam = 1;
}
#endif

ZBX_THREAD_ENTRY(listener_thread, args)
{
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	char		*msg = NULL;
#endif
	int		ret;
	zbx_socket_t	s;

	assert(args);
	assert(((zbx_thread_args_t *)args)->args);

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	memcpy(&s, ((zbx_thread_args_t *)args)->args, sizeof(zbx_socket_t));

	zbx_free(args);

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_init_child();
#endif

#ifndef _WINDOWS
	zbx_set_sigusr_handler(zbx_listener_sigusr_handler);
#endif

	while (ZBX_IS_RUNNING())
	{
#ifndef _WINDOWS
		if (1 == need_update_userparam)
		{
			zbx_setproctitle("listener #%d [reloading user parameters]", process_num);
			reload_user_parameters(process_type, process_num);
			need_update_userparam = 0;
		}
#endif

		zbx_setproctitle("listener #%d [waiting for connection]", process_num);
		ret = zbx_tcp_accept(&s, configured_tls_accept_modes);
		zbx_update_env(zbx_time());

		if (SUCCEED == ret)
		{
			zbx_setproctitle("listener #%d [processing request]", process_num);

			if ('\0' != *CONFIG_HOSTS_ALLOWED &&
					SUCCEED == (ret = zbx_tcp_check_allowed_peers(&s, CONFIG_HOSTS_ALLOWED)))
			{
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
				if (ZBX_TCP_SEC_TLS_CERT != s.connection_type ||
						SUCCEED == (ret = zbx_check_server_issuer_subject(&s, &msg)))
#endif
				{
					process_listener(&s);
				}
			}

			zbx_tcp_unaccept(&s);
		}

		if (SUCCEED == ret || EINTR == zbx_socket_last_error())
			continue;

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		if (NULL != msg)
		{
			zabbix_log(LOG_LEVEL_WARNING, "failed to accept an incoming connection: %s", msg);
			zbx_free(msg);
		}
		else
#endif
		{
			zabbix_log(LOG_LEVEL_WARNING, "failed to accept an incoming connection: %s",
					zbx_socket_strerror());
		}

		if (ZBX_IS_RUNNING())
			zbx_sleep(1);
	}

#ifdef _WINDOWS
	ZBX_DO_EXIT();

	zbx_thread_exit(EXIT_SUCCESS);
#else
	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
#endif
}
