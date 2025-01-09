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

#include "trapper_server.h"
#include "proxydata.h"

#include "taskmanager/taskmanager_server.h"
#include "cachehistory/cachehistory_server.h"
#include "discovery/discovery_server.h"
#include "autoreg/autoreg_server.h"

#include "zbxdbwrap.h"
#include "zbxnix.h"
#include "zbxcommshigh.h"
#include "zbxjson.h"
#include "zbxtasks.h"
#include "zbxcacheconfig.h"

int	zbx_send_proxy_data_response(const zbx_dc_proxy_t *proxy, zbx_socket_t *sock, const char *info, int status,
		int upload_status, int config_timeout)
{
	struct zbx_json		json;
	zbx_vector_tm_task_t	tasks;
	int			ret;
	unsigned char		flags = ZBX_TCP_PROTOCOL;

	zbx_vector_tm_task_create(&tasks);

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);

	switch (upload_status)
	{
		case ZBX_PROXY_UPLOAD_DISABLED:
			zbx_json_addstring(&json, ZBX_PROTO_TAG_HISTORY_UPLOAD, ZBX_PROTO_VALUE_HISTORY_UPLOAD_DISABLED,
					ZBX_JSON_TYPE_STRING);
			break;
		case ZBX_PROXY_UPLOAD_ENABLED:
			zbx_json_addstring(&json, ZBX_PROTO_TAG_HISTORY_UPLOAD, ZBX_PROTO_VALUE_HISTORY_UPLOAD_ENABLED,
					ZBX_JSON_TYPE_STRING);
			break;
	}

	if (SUCCEED == status)
	{
		zbx_json_addstring(&json, ZBX_PROTO_TAG_RESPONSE, ZBX_PROTO_VALUE_SUCCESS, ZBX_JSON_TYPE_STRING);
		zbx_tm_get_remote_tasks(&tasks, proxy->proxyid, proxy->compatibility);
	}
	else
		zbx_json_addstring(&json, ZBX_PROTO_TAG_RESPONSE, ZBX_PROTO_VALUE_FAILED, ZBX_JSON_TYPE_STRING);

	if (NULL != info && '\0' != *info)
		zbx_json_addstring(&json, ZBX_PROTO_TAG_INFO, info, ZBX_JSON_TYPE_STRING);

	if (0 != tasks.values_num)
		zbx_tm_json_serialize_tasks(&json, &tasks);

	flags |= ZBX_TCP_COMPRESS;

	if (SUCCEED == (ret = zbx_tcp_send_ext(sock, json.buffer, strlen(json.buffer), 0, flags, config_timeout)))
	{
		if (0 != tasks.values_num)
			zbx_tm_update_task_status(&tasks, ZBX_TM_STATUS_INPROGRESS);
	}

	zbx_json_free(&json);

	zbx_vector_tm_task_clear_ext(&tasks, zbx_tm_task_free);
	zbx_vector_tm_task_destroy(&tasks);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if 'proxy data' packet has historical data                 *
 *                                                                            *
 * Return value: SUCCEED - 'proxy data' contains no historical records        *
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
 * Purpose: receives 'proxy data' request from proxy                          *
 *                                                                            *
 * Parameters: sock                - [IN] connection socket                   *
 *             jp                  - [IN] received JSON data                  *
 *             ts                  - [IN] connection timestamp                *
 *             events_cbs          - [IN]                                     *
 *             config_timeout      - [IN]                                     *
 *             proxydata_frequency - [IN]                                     *
 *                                                                            *
 ******************************************************************************/
void	recv_proxy_data(zbx_socket_t *sock, const struct zbx_json_parse *jp, const zbx_timespec_t *ts,
		const zbx_events_funcs_t *events_cbs, int config_timeout, int proxydata_frequency)
{
	int			ret = FAIL, upload_status = 0, status, version_int, responded = 0;
	char			*error = NULL, *version_str = NULL;
	zbx_dc_proxy_t		proxy;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != (status = zbx_get_active_proxy_from_request(jp, &proxy, &error)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse proxy data from active proxy at \"%s\": %s",
				sock->peer, error);
		goto out;
	}

	if (SUCCEED != (status = zbx_proxy_check_permissions(&proxy, sock, &error)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot accept connection from proxy \"%s\" at \"%s\", allowed address:"
				" \"%s\": %s", proxy.name, sock->peer, proxy.allowed_addresses, error);
		goto out;
	}

	version_str = zbx_get_proxy_protocol_version_str(jp);
	version_int = zbx_get_proxy_protocol_version_int(version_str);

	if (SUCCEED != zbx_check_protocol_version(&proxy, version_int))
	{
		upload_status = ZBX_PROXY_UPLOAD_DISABLED;
		error = zbx_strdup(error, "current proxy version is not supported by server");
		goto reply;
	}

	if (FAIL == (ret = zbx_hc_check_proxy(proxy.proxyid)) || SUCCEED == zbx_vps_monitor_capped())
	{
		upload_status = ZBX_PROXY_UPLOAD_DISABLED;
		ret = proxy_data_no_history(jp);
	}
	else
		upload_status = ZBX_PROXY_UPLOAD_ENABLED;

	if (SUCCEED == ret)
	{
		if (SUCCEED != (ret = zbx_process_proxy_data(&proxy, jp, ts, PROXY_OPERATING_MODE_ACTIVE, events_cbs,
				proxydata_frequency, zbx_discovery_update_host_server,
				zbx_discovery_update_service_server, zbx_discovery_update_service_down_server,
				zbx_discovery_find_host_server, zbx_discovery_update_drule_server,
				zbx_autoreg_host_free_server, zbx_autoreg_flush_hosts_server,
				zbx_autoreg_prepare_host_server, NULL, &error)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "received invalid proxy data from proxy \"%s\" at \"%s\": %s",
					proxy.name, sock->peer, error);
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
reply:
	zbx_send_proxy_data_response(&proxy, sock, error, ret, upload_status, config_timeout);
	responded = 1;
out:
	if (SUCCEED == status)	/* moved the unpredictable long operation to the end */
				/* we are trying to save info about lastaccess to detect communication problem */
	{
		time_t	lastaccess;

		if (ZBX_PROXY_UPLOAD_DISABLED == upload_status)
			lastaccess = (int)time(NULL);
		else
			lastaccess = ts->sec;

		zbx_update_proxy_data(&proxy, version_str, version_int, lastaccess, 0);
	}

	if (0 == responded)
	{
		int	flags = ZBX_TCP_PROTOCOL;

		if (0 != (sock->protocol & ZBX_TCP_COMPRESS))
			flags |= ZBX_TCP_COMPRESS;

		zbx_send_response_ext(sock, ret, error, NULL, flags, config_timeout);
	}

	zbx_free(error);
	zbx_free(version_str);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));
}
