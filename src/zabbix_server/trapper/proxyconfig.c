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

#include "proxyconfig.h"

#include "zbxdbhigh.h"
#include "log.h"
#include "proxy.h"
#include "zbxrtc.h"
#include "zbxcommshigh.h"

#include "zbxcompress.h"

/******************************************************************************
 *                                                                            *
 * Purpose: send configuration tables to the proxy from server                *
 *          (for active proxies)                                              *
 *                                                                            *
 ******************************************************************************/
void	send_proxyconfig(zbx_socket_t *sock, struct zbx_json_parse *jp)
{
	char		*error = NULL, *buffer = NULL;
	struct zbx_json	j;
	DC_PROXY	proxy;
	int		ret, flags = ZBX_TCP_PROTOCOL;
	size_t		buffer_size, reserved = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != get_active_proxy_from_request(jp, &proxy, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse proxy configuration data request from active proxy at"
				" \"%s\": %s", sock->peer, error);
		goto out;
	}

	if (SUCCEED != zbx_proxy_check_permissions(&proxy, sock, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot accept connection from proxy \"%s\" at \"%s\", allowed address:"
				" \"%s\": %s", proxy.host, sock->peer, proxy.proxy_address, error);
		goto out;
	}

	zbx_update_proxy_data(&proxy, zbx_get_proxy_protocol_version(jp), (int)time(NULL),
			(0 != (sock->protocol & ZBX_TCP_COMPRESS) ? 1 : 0), ZBX_FLAGS_PROXY_DIFF_UPDATE_CONFIG);

	if (0 != proxy.auto_compress)
		flags |= ZBX_TCP_COMPRESS;

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	if (SUCCEED != proxyconfig_get_data(&proxy, jp, &j, &error))
	{
		zbx_send_response_ext(sock, FAIL, error, NULL, flags, CONFIG_TIMEOUT);
		zabbix_log(LOG_LEVEL_WARNING, "cannot collect configuration data for proxy \"%s\" at \"%s\": %s",
				proxy.host, sock->peer, error);
		goto clean;
	}

	if (0 != proxy.auto_compress)
	{
		if (SUCCEED != zbx_compress(j.buffer, j.buffer_size, &buffer, &buffer_size))
		{
			zabbix_log(LOG_LEVEL_ERR,"cannot compress data: %s", zbx_compress_strerror());
			goto clean;
		}

		reserved = j.buffer_size;

		zbx_json_free(&j);	/* json buffer can be large, free as fast as possible */

		zabbix_log(LOG_LEVEL_WARNING, "sending configuration data to proxy \"%s\" at \"%s\", datalen "
				ZBX_FS_SIZE_T ", bytes " ZBX_FS_SIZE_T " with compression ratio %.1f", proxy.host,
				sock->peer, (zbx_fs_size_t)reserved, (zbx_fs_size_t)buffer_size,
				(double)reserved / (double)buffer_size);

		ret = zbx_tcp_send_ext(sock, buffer, buffer_size, reserved, (unsigned char)flags,
				CONFIG_TRAPPER_TIMEOUT);
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "sending configuration data to proxy \"%s\" at \"%s\", datalen "
				ZBX_FS_SIZE_T, proxy.host, sock->peer, (zbx_fs_size_t)j.buffer_size);

		ret = zbx_tcp_send_ext(sock, j.buffer, strlen(j.buffer), 0, (unsigned char)flags,
				CONFIG_TRAPPER_TIMEOUT);
	}

	if (SUCCEED != ret)
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot send configuration data to proxy \"%s\" at \"%s\": %s",
				proxy.host, sock->peer, zbx_socket_strerror());
	}
clean:
	zbx_json_free(&j);
out:
	zbx_free(error);
	zbx_free(buffer);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: receive configuration tables from server (passive proxies)        *
 *                                                                            *
 ******************************************************************************/
void	recv_proxyconfig(zbx_socket_t *sock, const zbx_config_tls_t *zbx_config_tls)
{
	struct zbx_json_parse	jp_config, jp_kvs_paths = {0};
	int			ret;
	struct zbx_json		j;
	char			*error = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != check_access_passive_proxy(sock, ZBX_SEND_RESPONSE, "configuration update", zbx_config_tls))
		goto out;

	zbx_json_init(&j, 1024);
	zbx_json_addstring(&j, ZBX_PROTO_TAG_VERSION, ZABBIX_VERSION, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&j, ZBX_PROTO_TAG_SESSION, zbx_dc_get_session_token(), ZBX_JSON_TYPE_STRING);
	zbx_json_adduint64(&j, ZBX_PROTO_TAG_CONFIG_REVISION, (zbx_uint64_t)zbx_dc_get_received_revision());

	if (SUCCEED != (ret = zbx_tcp_send_ext(sock, j.buffer, j.buffer_size, 0, sock->protocol, CONFIG_TIMEOUT)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot send proxy configuration information to sever at \"%s\": %s",
				sock->peer, zbx_json_strerror());
		goto out;
	}

	if (FAIL == (ret = zbx_tcp_recv_ext(sock, CONFIG_TRAPPER_TIMEOUT, ZBX_TCP_LARGE)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot receive proxy configuration data from server at \"%s\": %s",
				sock->peer, zbx_json_strerror());
		goto out;
	}

	if (NULL != sock->buffer && SUCCEED != (ret = zbx_json_open(sock->buffer, &jp_config)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse proxy configuration data received from server at"
				" \"%s\": %s", sock->peer, zbx_json_strerror());
		zbx_send_proxy_response(sock, ret, zbx_json_strerror(), CONFIG_TIMEOUT);
		goto out;
	}

	zabbix_log(LOG_LEVEL_WARNING, "received configuration data from server at \"%s\", datalen " ZBX_FS_SIZE_T,
			sock->peer, (zbx_fs_size_t)(jp_config.end - jp_config.start + 1));

	if (SUCCEED == (ret = proxyconfig_process(&jp_config, &error)))
	{
		if (SUCCEED == zbx_rtc_reload_config_cache(&error))
		{
			if (SUCCEED == zbx_json_brackets_by_name(&jp_config, ZBX_PROTO_TAG_MACRO_SECRETS, &jp_kvs_paths))
				DCsync_kvs_paths(&jp_kvs_paths);
		}
		else
		{
			THIS_SHOULD_NEVER_HAPPEN;
			zabbix_log(LOG_LEVEL_WARNING, "cannot send message to configuration syncer: %s", error);
			zbx_free(error);
		}
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot process proxy onfiguration data received from server at"
				" \"%s\": %s", sock->peer, error);
	}
	zbx_send_proxy_response(sock, ret, error, CONFIG_TIMEOUT);
	zbx_free(error);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
