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

#include "connector_server.h"

#ifdef HAVE_LIBCURL
#	include "zbxhttp.h"
#	include "zbxnum.h"
#endif

#include "zbxtimekeeper.h"
#include "zbxthreads.h"
#include "zbxnix.h"
#include "zbxself.h"
#include "zbxlog.h"
#include "zbxipcservice.h"
#include "zbxconnector.h"
#include "zbxtime.h"
#include "zbxcacheconfig.h"
#include "zbxjson.h"
#include "zbxstr.h"

static int	connector_object_compare_func(const void *d1, const void *d2)
{
	return zbx_timespec_compare(&((const zbx_connector_data_point_t *)d1)->ts,
			&((const zbx_connector_data_point_t *)d2)->ts);
}

static void	worker_process_request(zbx_ipc_socket_t *socket, const char *config_source_ip,
		const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, zbx_ipc_message_t *message,
		zbx_vector_connector_data_point_t *connector_data_points, zbx_uint64_t *processed_num)
{
	zbx_connector_t	connector;
	char		*str = NULL, *out = NULL, *error = NULL;
	size_t		str_alloc = 0, str_offset = 0;

	zbx_connector_deserialize_connector_and_data_point(message->data, message->size, &connector,
			connector_data_points);

	zbx_vector_connector_data_point_sort(connector_data_points, connector_object_compare_func);
	for (int i = 0; i < connector_data_points->values_num; i++)
	{
		zbx_strcpy_alloc(&str, &str_alloc, &str_offset, connector_data_points->values[i].str);
		zbx_chrcpy_alloc(&str, &str_alloc, &str_offset, '\n');
	}

	*processed_num += (zbx_uint64_t)connector_data_points->values_num;

	zbx_vector_connector_data_point_clear_ext(connector_data_points, zbx_connector_data_point_free);
#ifdef HAVE_LIBCURL
#define ATTEMPT_DELAY_MAX	10
	char			query_fields[] = "", headers[] = "", status_codes[] = "200,201,202,203,204";
	zbx_http_context_t	context;
	int			ret, timeout_seconds, attempt_interval_sec;

	zbx_http_context_create(&context);

	if (FAIL == zbx_is_time_suffix(connector.timeout, &timeout_seconds, (int)strlen(connector.timeout)))
	{
		error = zbx_dsprintf(NULL, "Invalid timeout: %s", connector.timeout);
		ret = FAIL;
		goto skip;
	}

	if (FAIL == zbx_is_time_suffix(connector.attempt_interval, &attempt_interval_sec,
			(int)strlen(connector.attempt_interval)) || ATTEMPT_DELAY_MAX < attempt_interval_sec)
	{
		error = zbx_dsprintf(NULL, "Invalid attempt delay: %s", connector.attempt_interval);
		ret = FAIL;
		goto skip;
	}

	if (SUCCEED == (ret = zbx_http_request_prepare(&context, HTTP_REQUEST_POST, connector.url, headers,
			query_fields, str, ZBX_RETRIEVE_MODE_CONTENT, connector.http_proxy, 0, timeout_seconds,
			connector.max_attempts, connector.ssl_cert_file, connector.ssl_key_file,
			connector.ssl_key_password, connector.verify_peer, connector.verify_host, connector.authtype,
			connector.username, connector.password, connector.token, ZBX_POSTTYPE_NDJSON,
			HTTP_STORE_RAW, config_source_ip, config_ssl_ca_location, config_ssl_cert_location,
			config_ssl_key_location, &error)))
	{
		long		response_code;
		CURLcode	err;

		if (!ZBX_IS_RUNNING())
			attempt_interval_sec = 0;

		err = zbx_http_request_sync_perform(context.easyhandle, &context, attempt_interval_sec,
				ZBX_HTTP_CHECK_RESPONSE_CODE);

		if (SUCCEED == (ret = zbx_http_handle_response(context.easyhandle, &context, err, &response_code,
				&out, &error)))
		{
			if (FAIL == (ret = zbx_int_in_list(status_codes, (int)response_code)))
			{
				error = zbx_dsprintf(NULL, "Response code \"%ld\" did not match any of the"
						" required status codes \"%s\"", response_code, status_codes);
			}
		}
	}
skip:
	if (FAIL == ret)
	{
		char	*info = NULL;

		if (NULL != out)
		{
			struct zbx_json_parse	jp;
			size_t			info_alloc = 0;

			if (SUCCEED != zbx_json_open(out, &jp))
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot retrieve error from \"%s\": %s response: %s",
						connector.url, zbx_json_strerror(), out);
			}
			else
			{
				if (SUCCEED != zbx_json_value_by_name_dyn(&jp, ZBX_PROTO_TAG_ERROR, &info, &info_alloc,
					NULL))
				{
					zabbix_log(LOG_LEVEL_WARNING, "cannot find error tag in response from \"%s\""
							" response: %s", connector.url, out);
					info = NULL;
				}
			}
		}

		if (NULL != info)
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot send data to \"%s\": %s: %s", connector.url,
					error, info);
		}
		else
			zabbix_log(LOG_LEVEL_WARNING, "cannot send data to \"%s\": %s", connector.url, error);

		zbx_free(info);
	}

	zbx_http_context_destroy(&context);
#undef ATTEMPT_DELAY_MAX
#else
	ZBX_UNUSED(config_source_ip);
	ZBX_UNUSED(config_ssl_ca_location);
	ZBX_UNUSED(config_ssl_cert_location);
	ZBX_UNUSED(config_ssl_key_location);

	zabbix_log(LOG_LEVEL_WARNING, "Support for connectors was not compiled in: missing cURL library");
#endif
	zbx_free(str);
	zbx_free(error);
	zbx_free(out);

	zbx_free(connector.url);
	zbx_free(connector.timeout);
	zbx_free(connector.token);
	zbx_free(connector.http_proxy);
	zbx_free(connector.username);
	zbx_free(connector.password);
	zbx_free(connector.ssl_cert_file);
	zbx_free(connector.ssl_key_file);
	zbx_free(connector.ssl_key_password);
	zbx_free(connector.attempt_interval);

	if (FAIL == zbx_ipc_socket_write(socket, ZBX_IPC_CONNECTOR_RESULT, NULL, 0))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot send preprocessing result");
		exit(EXIT_FAILURE);
	}
}

ZBX_THREAD_ENTRY(connector_worker_thread, args)
{
#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */
	pid_t					ppid;
	char					*error = NULL;
	zbx_ipc_socket_t			socket;
	zbx_ipc_message_t			message;
	double					time_stat, time_idle = 0, time_now, time_read;
	const zbx_thread_info_t			*info = &((zbx_thread_args_t *)args)->info;
	int					server_num = ((zbx_thread_args_t *)args)->info.server_num,
						process_num = ((zbx_thread_args_t *)args)->info.process_num;
	unsigned char				process_type = ((zbx_thread_args_t *)args)->info.process_type;
	zbx_vector_connector_data_point_t	connector_data_points;
	zbx_uint64_t				processed_num = 0, connections_num = 0;

	const zbx_thread_connector_worker_args	*connector_worker_args_in = (const zbx_thread_connector_worker_args *)
						(((zbx_thread_args_t *)args)->args);

	zbx_setproctitle("%s #%d starting", get_process_type_string(info->program_type), process_num);

	zbx_ipc_message_init(&message);

	if (FAIL == zbx_ipc_socket_open(&socket, ZBX_IPC_SERVICE_CONNECTOR, SEC_PER_MIN, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot connect to connector service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	ppid = getppid();
	zbx_ipc_socket_write(&socket, ZBX_IPC_CONNECTOR_WORKER, (unsigned char *)&ppid, sizeof(ppid));

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			server_num, get_process_type_string(process_type), process_num);

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

	zbx_setproctitle("%s #%d started", get_process_type_string(process_type), process_num);

	zbx_vector_connector_data_point_create(&connector_data_points);

	time_stat = zbx_time();

	for (;;)
	{

		time_now = zbx_time();

		if (STAT_INTERVAL < time_now - time_stat)
		{
			zbx_setproctitle("%s #%d [processed values " ZBX_FS_UI64 ", connections " ZBX_FS_UI64 ", idle "
					ZBX_FS_DBL " sec during " ZBX_FS_DBL " sec]",
					get_process_type_string(process_type), process_num, processed_num,
					connections_num, time_idle, time_now - time_stat);

			time_stat = time_now;
			time_idle = 0;
			processed_num = 0;
			connections_num = 0;
		}

		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_IDLE);

		if (SUCCEED != zbx_ipc_socket_read(&socket, &message))
		{
			if (ZBX_IS_RUNNING())
			{
				zabbix_log(LOG_LEVEL_CRIT, "cannot read connector service request");
				exit(EXIT_FAILURE);
			}

			break;
		}

		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

		time_read = zbx_time();
		time_idle += time_read - time_now;

		zbx_update_env(get_process_type_string(process_type), time_read);

		switch (message.code)
		{
			case ZBX_IPC_CONNECTOR_REQUEST:
				worker_process_request(&socket, connector_worker_args_in->config_source_ip,
						connector_worker_args_in->config_ssl_ca_location,
						connector_worker_args_in->config_ssl_cert_location,
						connector_worker_args_in->config_ssl_key_location,
						&message, &connector_data_points, &processed_num);
				connections_num++;
				break;
		}

		zbx_ipc_message_clean(&message);
	}

	zbx_vector_connector_data_point_destroy(&connector_data_points);
	exit(EXIT_SUCCESS);
}
