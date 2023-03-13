/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#include "zbxconf.h"
#include "zbxsysinfo.h"
#include "log.h"
#include "zbxstr.h"
#include "zbxtime.h"
#include "zbx_rtc_constants.h"

#if defined(ZABBIX_SERVICE)
#	include "zbxwinservice.h"
#elif defined(ZABBIX_DAEMON)
#	include "zbxnix.h"
#endif

#ifndef _WINDOWS
static volatile sig_atomic_t	need_update_userparam;
#endif

static void	process_listener(zbx_socket_t *s, int config_timeout)
{
	AGENT_RESULT	result;
	char		**value = NULL;
	int		ret;

	if (SUCCEED == (ret = zbx_tcp_recv_to(s, config_timeout)))
	{
		zbx_rtrim(s->buffer, "\r\n");

		zabbix_log(LOG_LEVEL_DEBUG, "Requested [%s]", s->buffer);

		zbx_init_agent_result(&result);

		if (SUCCEED == zbx_execute_agent_check(s->buffer, ZBX_PROCESS_WITH_ALIAS, &result))
		{
			if (NULL != (value = ZBX_GET_TEXT_RESULT(&result)))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "Sending back [%s]", *value);
				ret = zbx_tcp_send_to(s, *value, config_timeout);
			}
		}
		else
		{
			value = ZBX_GET_MSG_RESULT(&result);

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

				ret = zbx_tcp_send_bytes_to(s, buffer, buffer_offset, config_timeout);
			}
			else
			{
				zabbix_log(LOG_LEVEL_DEBUG, "Sending back [" ZBX_NOTSUPPORTED "]");

				ret = zbx_tcp_send_to(s, ZBX_NOTSUPPORTED, config_timeout);
			}
		}

		zbx_free_agent_result(&result);
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
	char				*msg = NULL;
#endif
	int				ret;
	zbx_socket_t			s;
	zbx_thread_listener_args	*init_child_args_in;
	zbx_thread_info_t		*info = &((zbx_thread_args_t *)args)->info;
	unsigned char			process_type = ((zbx_thread_args_t *)args)->info.process_type;
	int				server_num = ((zbx_thread_args_t *)args)->info.server_num;
	int				process_num = ((zbx_thread_args_t *)args)->info.process_num;

	init_child_args_in = (zbx_thread_listener_args *)((((zbx_thread_args_t *)args))->args);

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			server_num, get_process_type_string(process_type), process_num);

	memcpy(&s, init_child_args_in->listen_sock, sizeof(zbx_socket_t));

	zbx_free(args);

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_init_child(init_child_args_in->zbx_config_tls, init_child_args_in->zbx_get_program_type_cb_arg);
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
			reload_user_parameters(process_type, process_num, init_child_args_in->config_file);
			need_update_userparam = 0;
		}
#endif

		zbx_setproctitle("listener #%d [waiting for connection]", process_num);
		ret = zbx_tcp_accept(&s, init_child_args_in->zbx_config_tls->accept_modes,
				init_child_args_in->config_timeout);
		zbx_update_env(get_process_type_string(process_type), zbx_time());

		if (SUCCEED == ret)
		{
			zbx_setproctitle("listener #%d [processing request]", process_num);

			if ('\0' != *CONFIG_HOSTS_ALLOWED &&
					SUCCEED == (ret = zbx_tcp_check_allowed_peers(&s, CONFIG_HOSTS_ALLOWED)))
			{
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
				if (ZBX_TCP_SEC_TLS_CERT != s.connection_type ||
						SUCCEED == (ret = zbx_check_server_issuer_subject(&s,
						init_child_args_in->zbx_config_tls->server_cert_issuer,
						init_child_args_in->zbx_config_tls->server_cert_subject,
						&msg)))
#endif
				{
					process_listener(&s, init_child_args_in->config_timeout);
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
