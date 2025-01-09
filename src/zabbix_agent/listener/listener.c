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

#include "listener.h"

#include "../agent_conf/agent_conf.h"

#include "zbxsysinfo.h"
#include "zbxlog.h"
#include "zbxstr.h"
#include "zbxtime.h"
#include "zbx_rtc_constants.h"
#include "zbxjson.h"
#include "zbxcfg.h"

#if defined(ZABBIX_SERVICE)
#	include "zbxwinservice.h"
#elif !defined(_WINDOWS)
#	include "zbxnix.h"
#endif

#ifndef _WINDOWS
static volatile sig_atomic_t	need_update_userparam;
#endif
static int	process_passive_checks_json(zbx_socket_t *s, int config_timeout, struct zbx_json_parse *jp)
{
	struct zbx_json_parse	jp_data, jp_row;
	const char		*p = NULL;
	size_t			key_alloc = 0;
	char			tmp[MAX_STRING_LEN], error_tmp[MAX_STRING_LEN], *key = NULL, *error = NULL;
	int			timeout, ret = SUCCEED;
	struct zbx_json		j;
	AGENT_RESULT		result;
	char			**value;

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addstring(&j, ZBX_PROTO_TAG_VERSION, ZABBIX_VERSION, ZBX_JSON_TYPE_STRING);
	zbx_json_addint64(&j, ZBX_PROTO_TAG_VARIANT, ZBX_PROGRAM_VARIANT_AGENT);

	if (FAIL == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_REQUEST, tmp, sizeof(tmp), NULL))
	{
		error = zbx_dsprintf(NULL, "cannot find the \"%s\" object in the received JSON object: %s",
				ZBX_PROTO_TAG_REQUEST, zbx_json_strerror());
		goto fail;
	}

	if (0 != strcmp(ZBX_PROTO_VALUE_GET_PASSIVE_CHECKS, tmp))
	{
		error = zbx_dsprintf(NULL, "unknown request \"%s\"", tmp);
		goto fail;
	}

	if (FAIL == zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_DATA, &jp_data))
	{
		error = zbx_dsprintf(NULL, "cannot find the \"%s\" array in the received JSON object: %s",
				ZBX_PROTO_TAG_DATA, zbx_json_strerror());
		goto fail;
	}

	if (NULL == (p = zbx_json_next(&jp_data, p)))
	{
		error = zbx_dsprintf(NULL, "received empty \"%s\" tag", ZBX_PROTO_TAG_DATA);
		goto fail;
	}

	if (FAIL == zbx_json_brackets_open(p, &jp_row))
	{
		error = zbx_dsprintf(NULL, "%s", zbx_json_strerror());
		goto fail;
	}

	if (FAIL == zbx_json_value_by_name(&jp_row, ZBX_PROTO_TAG_TIMEOUT, tmp, sizeof(tmp), NULL))
	{
		error = zbx_dsprintf(NULL, "cannot find the \"%s\" object in the received JSON object: %s",
				ZBX_PROTO_TAG_TIMEOUT, zbx_json_strerror());
		goto fail;
	}

	if (FAIL == zbx_json_value_by_name_dyn(&jp_row, ZBX_PROTO_TAG_KEY, &key, &key_alloc,
			NULL))
	{
		error = zbx_dsprintf(NULL, "cannot find the \"%s\" object in the received JSON object: %s",
				ZBX_PROTO_TAG_KEY, zbx_json_strerror());
		goto fail;
	}

	zbx_init_agent_result(&result);

	zbx_json_addarray(&j, ZBX_PROTO_TAG_DATA);
	zbx_json_addobject(&j, NULL);

	if (FAIL == zbx_validate_item_timeout(tmp, &timeout, error_tmp, sizeof(error_tmp)))
	{
		zbx_json_addstring(&j, ZBX_PROTO_TAG_ERROR, error_tmp, ZBX_JSON_TYPE_STRING);
	}
	else
	{
		if (SUCCEED == zbx_execute_agent_check(key, ZBX_PROCESS_WITH_ALIAS, &result, timeout))
		{
			if (NULL != (value = ZBX_GET_TEXT_RESULT(&result)))
				zbx_json_addstring(&j, ZBX_PROTO_TAG_VALUE, *value, ZBX_JSON_TYPE_STRING);
			else
				zbx_json_addraw(&j, ZBX_PROTO_TAG_VALUE, "null");
		}
		else
		{
			if (NULL != (value = ZBX_GET_MSG_RESULT(&result)))
				zbx_json_addstring(&j, ZBX_PROTO_TAG_ERROR, *value, ZBX_JSON_TYPE_STRING);
			else
				zbx_json_addstring(&j, ZBX_PROTO_TAG_ERROR, ZBX_NOTSUPPORTED, ZBX_JSON_TYPE_STRING);
		}
	}

	zbx_json_close(&j);
	zbx_json_close(&j);

	zbx_free_agent_result(&result);
fail:
	if (NULL != error)
		zbx_json_addstring(&j, ZBX_PROTO_TAG_ERROR, error, ZBX_JSON_TYPE_STRING);

	zbx_json_close(&j);

	zabbix_log(LOG_LEVEL_DEBUG, "Sending back [%s]", j.buffer);
	ret = zbx_tcp_send_bytes_to(s, j.buffer, j.buffer_size, config_timeout);

	zbx_free(key);
	zbx_json_free(&j);
	zbx_free(error);

	return ret;
}


static void	process_listener(zbx_socket_t *s, int config_timeout)
{
	int	ret;

	if (SUCCEED == (ret = zbx_tcp_recv_to(s, config_timeout)))
	{
		struct zbx_json_parse	jp;

		zbx_rtrim(s->buffer, "\r\n");

		zabbix_log(LOG_LEVEL_DEBUG, "Requested [%s]", s->buffer);

		if (SUCCEED == zbx_json_open(s->buffer, &jp))
		{
			ret = process_passive_checks_json(s, config_timeout, &jp);
		}
		else
		{
			AGENT_RESULT	result;
			char		**value = NULL;

			zbx_init_agent_result(&result);

			if (SUCCEED == zbx_execute_agent_check(s->buffer, ZBX_PROCESS_WITH_ALIAS, &result,
					config_timeout))
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
#define POLL_TIMEOUT		1
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	char				*msg = NULL;
#endif
	zbx_socket_t			s;
	zbx_thread_listener_args	*init_child_args_in;
	zbx_thread_info_t		*info = &((zbx_thread_args_t *)args)->info;
	unsigned char			process_type = ((zbx_thread_args_t *)args)->info.process_type;
	int				ret, server_num = ((zbx_thread_args_t *)args)->info.server_num,
					process_num = ((zbx_thread_args_t *)args)->info.process_num;

	init_child_args_in = (zbx_thread_listener_args *)((((zbx_thread_args_t *)args))->args);

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			server_num, get_process_type_string(process_type), process_num);

	memcpy(&s, init_child_args_in->listen_sock, sizeof(zbx_socket_t));

	zbx_free(args);

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_init_child(init_child_args_in->zbx_config_tls, init_child_args_in->zbx_get_program_type_cb_arg, NULL);
#endif

#ifndef _WINDOWS
	zbx_set_sigusr_handler(zbx_listener_sigusr_handler);
#endif
	zbx_cfg_set_process_num(process_num);

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
		ret = zbx_tcp_accept(&s, init_child_args_in->zbx_config_tls->accept_modes, POLL_TIMEOUT);
		zbx_update_env(get_process_type_string(process_type), zbx_time());

		if (TIMEOUT_ERROR == ret)
			continue;

		if (SUCCEED == ret)
		{
			zbx_setproctitle("listener #%d [processing request]", process_num);

			if ('\0' != *(init_child_args_in->config_hosts_allowed) &&
					SUCCEED == (ret = zbx_tcp_check_allowed_peers(&s,
					init_child_args_in->config_hosts_allowed)))
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

			if (SUCCEED == ret)
				continue;
		}

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
#undef POLL_TIMEOUT
}
