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

#include "proxydata.h"
#include "zbxdbhigh.h"
#include "log.h"
#include "proxy.h"

#include "zbxtasks.h"
#include "zbxmutexs.h"
#include "zbxnix.h"
#include "zbxcompress.h"
#include "zbxcommshigh.h"
#include "zbxavailability.h"

extern unsigned char	program_type;
static zbx_mutex_t	proxy_lock = ZBX_MUTEX_NULL;

#define	LOCK_PROXY_HISTORY	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY_PASSIVE)) zbx_mutex_lock(proxy_lock)
#define	UNLOCK_PROXY_HISTORY	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY_PASSIVE)) zbx_mutex_unlock(proxy_lock)

int	zbx_send_proxy_data_response(const DC_PROXY *proxy, zbx_socket_t *sock, const char *info, int status,
		int upload_status)
{
	struct zbx_json		json;
	zbx_vector_ptr_t	tasks;
	int			ret, flags = ZBX_TCP_PROTOCOL;

	zbx_vector_ptr_create(&tasks);

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);

	switch (upload_status)
	{
		case ZBX_PROXY_UPLOAD_DISABLED:
			zbx_json_addstring(&json, ZBX_PROTO_TAG_PROXY_UPLOAD, ZBX_PROTO_VALUE_PROXY_UPLOAD_DISABLED,
					ZBX_JSON_TYPE_STRING);
			break;
		case ZBX_PROXY_UPLOAD_ENABLED:
			zbx_json_addstring(&json, ZBX_PROTO_TAG_PROXY_UPLOAD, ZBX_PROTO_VALUE_PROXY_UPLOAD_ENABLED,
					ZBX_JSON_TYPE_STRING);
			break;
	}

	if (SUCCEED == status)
	{
		zbx_json_addstring(&json, ZBX_PROTO_TAG_RESPONSE, ZBX_PROTO_VALUE_SUCCESS, ZBX_JSON_TYPE_STRING);
		zbx_tm_get_remote_tasks(&tasks, proxy->hostid);
	}
	else
		zbx_json_addstring(&json, ZBX_PROTO_TAG_RESPONSE, ZBX_PROTO_VALUE_FAILED, ZBX_JSON_TYPE_STRING);

	if (NULL != info && '\0' != *info)
		zbx_json_addstring(&json, ZBX_PROTO_TAG_INFO, info, ZBX_JSON_TYPE_STRING);

	if (0 != tasks.values_num)
		zbx_tm_json_serialize_tasks(&json, &tasks);

	if (0 != proxy->auto_compress)
		flags |= ZBX_TCP_COMPRESS;

	if (SUCCEED == (ret = zbx_tcp_send_ext(sock, json.buffer, strlen(json.buffer), 0, flags, 0)))
	{
		if (0 != tasks.values_num)
			zbx_tm_update_task_status(&tasks, ZBX_TM_STATUS_INPROGRESS);
	}

	zbx_json_free(&json);

	zbx_vector_ptr_clear_ext(&tasks, (zbx_clean_func_t)zbx_tm_task_free);
	zbx_vector_ptr_destroy(&tasks);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if the 'proxy data' packet has historical data              *
 *                                                                            *
 * Return value: SUCCEED - the 'proxy data' contains no historical records    *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	proxy_data_no_history(const struct zbx_json_parse *jp)
{
	struct zbx_json_parse	jp_data;

	if (SUCCEED == zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_HISTORY_DATA, &jp_data))
		return FAIL;

	if (SUCCEED == zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_DISCOVERY_DATA, &jp_data))
		return FAIL;

	if (SUCCEED == zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_AUTOREGISTRATION, &jp_data))
		return FAIL;

	if (SUCCEED == zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_INTERFACE_AVAILABILITY, &jp_data))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: receive 'proxy data' request from proxy                           *
 *                                                                            *
 * Parameters: sock - [IN] the connection socket                              *
 *             jp   - [IN] the received JSON data                             *
 *             ts   - [IN] the connection timestamp                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_recv_proxy_data(zbx_socket_t *sock, struct zbx_json_parse *jp, zbx_timespec_t *ts)
{
	int			ret = FAIL, upload_status = 0, status, version, responded = 0;
	char			*error = NULL;
	DC_PROXY		proxy;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != (status = get_active_proxy_from_request(jp, &proxy, &error)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse proxy data from active proxy at \"%s\": %s",
				sock->peer, error);
		goto out;
	}

	if (SUCCEED != (status = zbx_proxy_check_permissions(&proxy, sock, &error)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot accept connection from proxy \"%s\" at \"%s\", allowed address:"
				" \"%s\": %s", proxy.host, sock->peer, proxy.proxy_address, error);
		goto out;
	}

	version = zbx_get_proxy_protocol_version(jp);

	if (SUCCEED != zbx_check_protocol_version(&proxy, version))
	{
		goto out;
	}

	if (FAIL == (ret = zbx_hc_check_proxy(proxy.hostid)))
	{
		upload_status = ZBX_PROXY_UPLOAD_DISABLED;
		ret = proxy_data_no_history(jp);
	}
	else
		upload_status = ZBX_PROXY_UPLOAD_ENABLED;

	if (SUCCEED == ret)
	{
		if (SUCCEED != (ret = process_proxy_data(&proxy, jp, ts, HOST_STATUS_PROXY_ACTIVE, NULL, &error)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "received invalid proxy data from proxy \"%s\" at \"%s\": %s",
					proxy.host, sock->peer, error);
			goto out;
		}
	}

	if (!ZBX_IS_RUNNING())
	{
		error = zbx_strdup(error, "Zabbix server shutdown in progress");
		zabbix_log(LOG_LEVEL_WARNING, "cannot process proxy data from active proxy at \"%s\": %s",
				sock->peer, error);
		ret = FAIL;
		goto out;
	}

	zbx_send_proxy_data_response(&proxy, sock, error, ret, upload_status);
	responded = 1;

out:
	if (SUCCEED == status)	/* moved the unpredictable long operation to the end */
				/* we are trying to save info about lastaccess to detect communication problem */
	{
		int	lastaccess;

		if (ZBX_PROXY_UPLOAD_DISABLED == upload_status)
			lastaccess = time(NULL);
		else
			lastaccess = ts->sec;

		zbx_update_proxy_data(&proxy, version, lastaccess,
				(0 != (sock->protocol & ZBX_TCP_COMPRESS) ? 1 : 0), 0);
	}

	if (0 == responded)
	{
		int	flags = ZBX_TCP_PROTOCOL;

		if (0 != (sock->protocol & ZBX_TCP_COMPRESS))
			flags |= ZBX_TCP_COMPRESS;

		zbx_send_response_ext(sock, ret, error, NULL, flags, CONFIG_TIMEOUT);
	}

	zbx_free(error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));
}

/******************************************************************************
 *                                                                            *
 * Purpose: sends data from proxy to server                                   *
 *                                                                            *
 * Parameters: sock  - [IN] the connection socket                             *
 *             data  - [IN] the data to send                                  *
 *             error - [OUT] the error message                                *
 *                                                                            *
 ******************************************************************************/
static int	send_data_to_server(zbx_socket_t *sock, char **buffer, size_t buffer_size, size_t reserved,
		char **error)
{
	if (SUCCEED != zbx_tcp_send_ext(sock, *buffer, buffer_size, reserved, ZBX_TCP_PROTOCOL | ZBX_TCP_COMPRESS,
			CONFIG_TIMEOUT))
	{
		*error = zbx_strdup(*error, zbx_socket_strerror());
		return FAIL;
	}

	zbx_free(*buffer);

	if (SUCCEED != zbx_recv_response(sock, CONFIG_TIMEOUT, error))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: sends 'proxy data' request to server                              *
 *                                                                            *
 * Parameters: sock - [IN] the connection socket                              *
 *             ts   - [IN] the connection timestamp                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_send_proxy_data(zbx_socket_t *sock, zbx_timespec_t *ts)
{
	struct zbx_json		j;
	zbx_uint64_t		areg_lastid = 0, history_lastid = 0, discovery_lastid = 0;
	char			*error = NULL, *buffer = NULL;
	int			availability_ts, more_history, more_discovery, more_areg, proxy_delay;
	zbx_vector_ptr_t	tasks;
	struct zbx_json_parse	jp, jp_tasks;
	size_t			buffer_size, reserved;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != check_access_passive_proxy(sock, ZBX_DO_NOT_SEND_RESPONSE, "proxy data request"))
	{
		/* do not send any reply to server in this case as the server expects proxy data */
		goto out;
	}

	LOCK_PROXY_HISTORY;
	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addstring(&j, ZBX_PROTO_TAG_SESSION, zbx_dc_get_session_token(), ZBX_JSON_TYPE_STRING);
	get_interface_availability_data(&j, &availability_ts);
	proxy_get_hist_data(&j, &history_lastid, &more_history);
	proxy_get_dhis_data(&j, &discovery_lastid, &more_discovery);
	proxy_get_areg_data(&j, &areg_lastid, &more_areg);
	proxy_get_host_active_availability(&j);

	zbx_vector_ptr_create(&tasks);
	zbx_tm_get_remote_tasks(&tasks, 0);

	if (0 != tasks.values_num)
		zbx_tm_json_serialize_tasks(&j, &tasks);

	if (ZBX_PROXY_DATA_MORE == more_history || ZBX_PROXY_DATA_MORE == more_discovery ||
			ZBX_PROXY_DATA_MORE == more_areg)
	{
		zbx_json_adduint64(&j, ZBX_PROTO_TAG_MORE, ZBX_PROXY_DATA_MORE);
	}

	zbx_json_addstring(&j, ZBX_PROTO_TAG_VERSION, ZABBIX_VERSION, ZBX_JSON_TYPE_STRING);
	zbx_json_adduint64(&j, ZBX_PROTO_TAG_CLOCK, ts->sec);
	zbx_json_adduint64(&j, ZBX_PROTO_TAG_NS, ts->ns);

	if (0 != history_lastid && 0 != (proxy_delay = proxy_get_delay(history_lastid)))
		zbx_json_adduint64(&j, ZBX_PROTO_TAG_PROXY_DELAY, proxy_delay);

	if (SUCCEED != zbx_compress(j.buffer, j.buffer_size, &buffer, &buffer_size))
	{
		zabbix_log(LOG_LEVEL_ERR,"cannot compress data: %s", zbx_compress_strerror());
		goto clean;
	}

	reserved = j.buffer_size;
	zbx_json_free(&j);	/* json buffer can be large, free as fast as possible */

	if (SUCCEED == send_data_to_server(sock, &buffer, buffer_size, reserved, &error))
	{
		zbx_set_availability_diff_ts(availability_ts);

		DBbegin();

		if (0 != history_lastid)
		{
			zbx_uint64_t	history_maxid;
			DB_RESULT	result;
			DB_ROW		row;

			result = DBselect("select max(id) from proxy_history");

			if (NULL == (row = DBfetch(result)) || SUCCEED == DBis_null(row[0]))
				history_maxid = history_lastid;
			else
				ZBX_STR2UINT64(history_maxid, row[0]);

			DBfree_result(result);

			reset_proxy_history_count(history_maxid - history_lastid);
			proxy_set_hist_lastid(history_lastid);
		}

		if (0 != discovery_lastid)
			proxy_set_dhis_lastid(discovery_lastid);

		if (0 != areg_lastid)
			proxy_set_areg_lastid(areg_lastid);

		if (0 != tasks.values_num)
		{
			zbx_tm_update_task_status(&tasks, ZBX_TM_STATUS_DONE);
			zbx_vector_ptr_clear_ext(&tasks, (zbx_clean_func_t)zbx_tm_task_free);
		}

		if (SUCCEED == zbx_json_open(sock->buffer, &jp))
		{
			if (SUCCEED == zbx_json_brackets_by_name(&jp, ZBX_PROTO_TAG_TASKS, &jp_tasks))
			{
				zbx_tm_json_deserialize_tasks(&jp_tasks, &tasks);
				zbx_tm_save_tasks(&tasks);
			}
		}

		DBcommit();
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot send proxy data to server at \"%s\": %s", sock->peer, error);
		zbx_free(error);
	}
clean:
	zbx_vector_ptr_clear_ext(&tasks, (zbx_clean_func_t)zbx_tm_task_free);
	zbx_vector_ptr_destroy(&tasks);

	zbx_json_free(&j);
	UNLOCK_PROXY_HISTORY;
out:
	zbx_free(buffer);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: sends 'proxy data' request to server                              *
 *                                                                            *
 * Parameters: sock - [IN] the connection socket                              *
 *             ts   - [IN] the connection timestamp                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_send_task_data(zbx_socket_t *sock, zbx_timespec_t *ts)
{
	struct zbx_json		j;
	char			*error = NULL, *buffer = NULL;
	zbx_vector_ptr_t	tasks;
	struct zbx_json_parse	jp, jp_tasks;
	size_t			buffer_size, reserved;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != check_access_passive_proxy(sock, ZBX_DO_NOT_SEND_RESPONSE, "proxy data request"))
	{
		/* do not send any reply to server in this case as the server expects proxy data */
		goto out;
	}

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	zbx_vector_ptr_create(&tasks);
	zbx_tm_get_remote_tasks(&tasks, 0);

	if (0 != tasks.values_num)
		zbx_tm_json_serialize_tasks(&j, &tasks);

	zbx_json_addstring(&j, ZBX_PROTO_TAG_VERSION, ZABBIX_VERSION, ZBX_JSON_TYPE_STRING);
	zbx_json_adduint64(&j, ZBX_PROTO_TAG_CLOCK, ts->sec);
	zbx_json_adduint64(&j, ZBX_PROTO_TAG_NS, ts->ns);

	if (SUCCEED != zbx_compress(j.buffer, j.buffer_size, &buffer, &buffer_size))
	{
		zabbix_log(LOG_LEVEL_ERR,"cannot compress data: %s", zbx_compress_strerror());
		goto clean;
	}

	reserved = j.buffer_size;
	zbx_json_free(&j);	/* json buffer can be large, free as fast as possible */

	if (SUCCEED == send_data_to_server(sock, &buffer, buffer_size, reserved, &error))
	{
		DBbegin();

		if (0 != tasks.values_num)
		{
			zbx_tm_update_task_status(&tasks, ZBX_TM_STATUS_DONE);
			zbx_vector_ptr_clear_ext(&tasks, (zbx_clean_func_t)zbx_tm_task_free);
		}

		if (SUCCEED == zbx_json_open(sock->buffer, &jp))
		{
			if (SUCCEED == zbx_json_brackets_by_name(&jp, ZBX_PROTO_TAG_TASKS, &jp_tasks))
			{
				zbx_tm_json_deserialize_tasks(&jp_tasks, &tasks);
				zbx_tm_save_tasks(&tasks);
			}
		}

		DBcommit();
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot send task data to server at \"%s\": %s", sock->peer, error);
		zbx_free(error);
	}
clean:
	zbx_vector_ptr_clear_ext(&tasks, (zbx_clean_func_t)zbx_tm_task_free);
	zbx_vector_ptr_destroy(&tasks);

	zbx_json_free(&j);
out:
	zbx_free(buffer);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

int	init_proxy_history_lock(char **error)
{
	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY_PASSIVE))
		return zbx_mutex_create(&proxy_lock, ZBX_MUTEX_PROXY_HISTORY, error);

	return SUCCEED;
}

void	free_proxy_history_lock(void)
{
	if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY_PASSIVE))
		zbx_mutex_destroy(&proxy_lock);
}
