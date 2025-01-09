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

#include "trapper_proxy.h"

#include "../taskmanager/taskmanager_proxy.h"
#include "../proxyconfigwrite/proxyconfigwrite.h"

#include "zbxcomms.h"
#include "zbxcommshigh.h"
#include "zbxtasks.h"
#include "zbxmutexs.h"
#include "zbxdb.h"
#include "zbxdbwrap.h"
#include "zbxproxybuffer.h"
#include "zbxcompress.h"
#include "zbxcacheconfig.h"
#include "zbxjson.h"

#define	LOCK_PROXY_HISTORY	zbx_mutex_lock(proxy_lock)
#define	UNLOCK_PROXY_HISTORY	zbx_mutex_unlock(proxy_lock)

static zbx_mutex_t	proxy_lock = ZBX_MUTEX_NULL;

int	init_proxy_history_lock(unsigned char program_type, char **error)
{
	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY_PASSIVE))
		return zbx_mutex_create(&proxy_lock, ZBX_MUTEX_PROXY_HISTORY, error);

	return SUCCEED;
}

void	free_proxy_history_lock(unsigned char program_type)
{
	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY_PASSIVE))
		zbx_mutex_destroy(&proxy_lock);
}

static void	active_passive_misconfig(zbx_socket_t *sock, int config_timeout)
{
	char	*msg = NULL;

	msg = zbx_dsprintf(msg, "misconfiguration error: the proxy is running in the active mode but server at \"%s\""
			" sends requests to it as to proxy in passive mode", sock->peer);

	zabbix_log(LOG_LEVEL_WARNING, "%s", msg);
	zbx_send_proxy_response(sock, FAIL, msg, config_timeout);
	zbx_free(msg);
}

/******************************************************************************
 *                                                                            *
 * Purpose: sends data from proxy to server                                   *
 *                                                                            *
 * Parameters: sock            - [IN] connection socket                       *
 *             buffer          - [IN/OUT]                                     *
 *             buffer_size     - [IN]                                         *
 *             reserved        - [IN]                                         *
 *             config_timeout  - [IN]                                         *
 *             error           - [OUT] error message                          *
 *                                                                            *
 ******************************************************************************/
static int	send_data_to_server(zbx_socket_t *sock, char **buffer, size_t buffer_size, size_t reserved,
		int config_timeout, char **error)
{
	if (SUCCEED != zbx_tcp_send_ext(sock, *buffer, buffer_size, reserved, ZBX_TCP_PROTOCOL | ZBX_TCP_COMPRESS,
			config_timeout))
	{
		*error = zbx_strdup(*error, zbx_socket_strerror());
		return FAIL;
	}

	zbx_free(*buffer);

	if (SUCCEED != zbx_recv_response(sock, config_timeout, error))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: sends 'proxy data' request to server                              *
 *                                                                            *
 * Parameters: sock                - [IN] connection socket                   *
 *             ts                  - [IN] connection timestamp                *
 *             config_comms        - [IN] proxy configuration for             *
 *                                        communication with server           *
 *             get_program_type_cb - [IN] callback to get program type        *
 *                                                                            *
 ******************************************************************************/
static void	send_proxy_data(zbx_socket_t *sock, const zbx_timespec_t *ts,
		const zbx_config_comms_args_t *config_comms, zbx_get_program_type_f get_program_type_cb)
{
	struct zbx_json		j;
	zbx_uint64_t		areg_lastid = 0, history_lastid = 0, discovery_lastid = 0;
	char			*error = NULL, *buffer = NULL;
	int			availability_ts, more_history, more_discovery, more_areg, proxy_delay, more;
	zbx_vector_tm_task_t	tasks;
	struct zbx_json_parse	jp, jp_tasks;
	size_t			buffer_size, reserved;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != zbx_check_access_passive_proxy(sock, ZBX_DO_NOT_SEND_RESPONSE, "proxy data request",
			config_comms->config_tls, config_comms->config_timeout, config_comms->server))
	{
		/* do not send any reply to server in this case as the server expects proxy data */
		goto out;
	}

	if (0 != (get_program_type_cb() & ZBX_PROGRAM_TYPE_PROXY_PASSIVE))
		LOCK_PROXY_HISTORY;

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addstring(&j, ZBX_PROTO_TAG_SESSION, zbx_dc_get_session_token(), ZBX_JSON_TYPE_STRING);
	zbx_get_interface_availability_data(&j, &availability_ts);
	zbx_pb_history_get_rows(&j, &history_lastid, &more_history);
	zbx_pb_discovery_get_rows(&j, &discovery_lastid, &more_discovery);
	zbx_pb_autoreg_get_rows(&j, &areg_lastid, &more_areg);
	zbx_proxy_get_host_active_availability(&j);

	zbx_vector_tm_task_create(&tasks);
	zbx_tm_get_remote_tasks(&tasks, 0, 0);

	if (0 != tasks.values_num)
		zbx_tm_json_serialize_tasks(&j, &tasks);

	if (ZBX_PROXY_DATA_MORE == more_history || ZBX_PROXY_DATA_MORE == more_discovery ||
			ZBX_PROXY_DATA_MORE == more_areg)
	{
		zbx_json_adduint64(&j, ZBX_PROTO_TAG_MORE, ZBX_PROXY_DATA_MORE);
		more = ZBX_PROXY_DATA_MORE;
	}
	else
		more = ZBX_PROXY_DATA_DONE;

	zbx_json_addstring(&j, ZBX_PROTO_TAG_VERSION, ZABBIX_VERSION, ZBX_JSON_TYPE_STRING);
	zbx_json_addint64(&j, ZBX_PROTO_TAG_CLOCK, ts->sec);
	zbx_json_addint64(&j, ZBX_PROTO_TAG_NS, ts->ns);

	if (0 != history_lastid && 0 != (proxy_delay = zbx_proxy_get_delay(history_lastid)))
		zbx_json_addint64(&j, ZBX_PROTO_TAG_PROXY_DELAY, proxy_delay);

	if (SUCCEED != zbx_compress(j.buffer, j.buffer_size, &buffer, &buffer_size))
	{
		zabbix_log(LOG_LEVEL_ERR,"cannot compress data: %s", zbx_compress_strerror());
		goto clean;
	}

	reserved = j.buffer_size;
	zbx_json_free(&j);	/* json buffer can be large, free as fast as possible */

	if (SUCCEED == send_data_to_server(sock, &buffer, buffer_size, reserved, config_comms->config_timeout,
			&error))
	{
		zbx_set_availability_diff_ts(availability_ts);

		zbx_db_begin();

		if (0 != history_lastid)
			zbx_pb_set_history_lastid(history_lastid);

		if (0 != discovery_lastid)
			zbx_pb_discovery_set_lastid(discovery_lastid);

		if (0 != areg_lastid)
			zbx_pb_autoreg_set_lastid(areg_lastid);

		if (0 != tasks.values_num)
		{
			zbx_tm_update_task_status(&tasks, ZBX_TM_STATUS_DONE);
			zbx_vector_tm_task_clear_ext(&tasks, zbx_tm_task_free);
		}

		if (SUCCEED == zbx_json_open(sock->buffer, &jp))
		{
			if (SUCCEED == zbx_json_brackets_by_name(&jp, ZBX_PROTO_TAG_TASKS, &jp_tasks))
			{
				zbx_tm_json_deserialize_tasks(&jp_tasks, &tasks);
				zbx_tm_save_tasks(&tasks);
			}
		}

		zbx_db_commit();

		zbx_pb_update_state(more);

		zbx_dc_set_proxy_lastonline(ts->sec);
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot send proxy data to server at \"%s\": %s", sock->peer, error);
		zbx_free(error);
	}
clean:
	zbx_vector_tm_task_clear_ext(&tasks, zbx_tm_task_free);
	zbx_vector_tm_task_destroy(&tasks);

	zbx_json_free(&j);

	if (0 != (get_program_type_cb() & ZBX_PROGRAM_TYPE_PROXY_PASSIVE))
		UNLOCK_PROXY_HISTORY;
out:
	zbx_free(buffer);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: sends 'task data' request to server                               *
 *                                                                            *
 * Parameters: sock         - [IN] connection socket                          *
 *             ts           - [IN] connection timestamp                       *
 *             config_comms - [IN] proxy configuration for communication with *
 *                                 server                                     *
 *                                                                            *
 ******************************************************************************/
static void	send_task_data(zbx_socket_t *sock, const zbx_timespec_t *ts,
		const zbx_config_comms_args_t *config_comms)
{
	struct zbx_json		j;
	char			*error = NULL, *buffer = NULL;
	zbx_vector_tm_task_t	tasks;
	struct zbx_json_parse	jp, jp_tasks;
	size_t			buffer_size, reserved;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != zbx_check_access_passive_proxy(sock, ZBX_DO_NOT_SEND_RESPONSE, "proxy data request",
			config_comms->config_tls, config_comms->config_timeout, config_comms->server))
	{
		/* do not send any reply to server in this case as the server expects proxy data */
		goto out;
	}

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	zbx_vector_tm_task_create(&tasks);
	zbx_tm_get_remote_tasks(&tasks, 0, 0);

	if (0 != tasks.values_num)
		zbx_tm_json_serialize_tasks(&j, &tasks);

	zbx_json_addstring(&j, ZBX_PROTO_TAG_VERSION, ZABBIX_VERSION, ZBX_JSON_TYPE_STRING);
	zbx_json_addint64(&j, ZBX_PROTO_TAG_CLOCK, ts->sec);
	zbx_json_addint64(&j, ZBX_PROTO_TAG_NS, ts->ns);

	if (SUCCEED != zbx_compress(j.buffer, j.buffer_size, &buffer, &buffer_size))
	{
		zabbix_log(LOG_LEVEL_ERR,"cannot compress data: %s", zbx_compress_strerror());
		goto clean;
	}

	reserved = j.buffer_size;
	zbx_json_free(&j);	/* json buffer can be large, free as fast as possible */

	if (SUCCEED == send_data_to_server(sock, &buffer, buffer_size, reserved, config_comms->config_timeout,
			&error))
	{
		zbx_db_begin();

		if (0 != tasks.values_num)
		{
			zbx_tm_update_task_status(&tasks, ZBX_TM_STATUS_DONE);
			zbx_vector_tm_task_clear_ext(&tasks, zbx_tm_task_free);
		}

		if (SUCCEED == zbx_json_open(sock->buffer, &jp))
		{
			if (SUCCEED == zbx_json_brackets_by_name(&jp, ZBX_PROTO_TAG_TASKS, &jp_tasks))
			{
				zbx_tm_json_deserialize_tasks(&jp_tasks, &tasks);
				zbx_tm_save_tasks(&tasks);
			}
		}

		zbx_db_commit();

		zbx_dc_set_proxy_lastonline(ts->sec);
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot send task data to server at \"%s\": %s", sock->peer, error);
		zbx_free(error);
	}
clean:
	zbx_vector_tm_task_clear_ext(&tasks, zbx_tm_task_free);
	zbx_vector_tm_task_destroy(&tasks);

	zbx_json_free(&j);
out:
	zbx_free(buffer);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/*******************************************************************************
 *                                                                             *
 * Purpose: processes proxy specific trapper requests                          *
 *                                                                             *
 * Comments: Some of proxy specific request processing was moved into separate *
 *           library to split server/proxy code dependencies.                  *
 *                                                                             *
 *******************************************************************************/
int	trapper_process_request_proxy(const char *request, zbx_socket_t *sock, const struct zbx_json_parse *jp,
		const zbx_timespec_t *ts, const zbx_config_comms_args_t *config_comms,
		const zbx_config_vault_t *config_vault, int proxydata_frequency,
		zbx_get_program_type_f get_program_type_cb, const zbx_events_funcs_t *events_cbs,
		zbx_get_config_forks_f get_config_forks)
{
	ZBX_UNUSED(jp);
	ZBX_UNUSED(ts);
	ZBX_UNUSED(proxydata_frequency);
	ZBX_UNUSED(events_cbs);
	ZBX_UNUSED(get_config_forks);

	if (0 == strcmp(request, ZBX_PROTO_VALUE_PROXY_CONFIG))
	{
		if (0 != (get_program_type_cb() & ZBX_PROGRAM_TYPE_PROXY_PASSIVE))
		{
			zbx_recv_proxyconfig(sock, config_comms->config_tls, config_vault, config_comms->config_timeout,
					config_comms->config_trapper_timeout, config_comms->config_source_ip,
					config_comms->config_ssl_ca_location, config_comms->config_ssl_cert_location,
					config_comms->config_ssl_key_location, config_comms->server);
			return SUCCEED;
		}
		else if (0 != (get_program_type_cb() & ZBX_PROGRAM_TYPE_PROXY_ACTIVE))
		{
			/* This is a misconfiguration: the proxy is configured in active mode */
			/* but server sends requests to it as to a proxy in passive mode. To  */
			/* prevent logging of this problem for every request we report it     */
			/* only when the server sends configuration to the proxy and ignore   */
			/* it for other requests.                                             */
			active_passive_misconfig(sock, config_comms->config_timeout);
			return SUCCEED;
		}
	}
	else if (0 == strcmp(request, ZBX_PROTO_VALUE_PROXY_DATA))
	{
		if (0 != (get_program_type_cb() & ZBX_PROGRAM_TYPE_PROXY_PASSIVE))
		{
			send_proxy_data(sock, ts, config_comms, get_program_type_cb);
			return SUCCEED;
		}
		return FAIL;
	}
	else if (0 == strcmp(request, ZBX_PROTO_VALUE_PROXY_TASKS))
	{
		if (0 != (get_program_type_cb() & ZBX_PROGRAM_TYPE_PROXY_PASSIVE))
		{
			send_task_data(sock, ts, config_comms);
			return SUCCEED;
		}
		return FAIL;
	}
	return FAIL;
}
