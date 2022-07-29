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

#include "proxypoller.h"

#include "common.h"
#include "zbxnix.h"
#include "zbxself.h"
#include "zbxserver.h"
#include "zbxdbhigh.h"
#include "log.h"
#include "proxy.h"
#include "zbxcrypto.h"
#include "../trapper/proxydata.h"
#include "zbxcompress.h"
#include "zbxrtc.h"
#include "zbxcommshigh.h"

extern ZBX_THREAD_LOCAL unsigned char	process_type;
extern unsigned char			program_type;
extern ZBX_THREAD_LOCAL int		server_num, process_num;

static int	connect_to_proxy(const DC_PROXY *proxy, zbx_socket_t *sock, int timeout)
{
	int		ret = FAIL;
	const char	*tls_arg1, *tls_arg2;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() address:%s port:%hu timeout:%d conn:%u", __func__, proxy->addr,
			proxy->port, timeout, (unsigned int)proxy->tls_connect);

	switch (proxy->tls_connect)
	{
		case ZBX_TCP_SEC_UNENCRYPTED:
			tls_arg1 = NULL;
			tls_arg2 = NULL;
			break;
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		case ZBX_TCP_SEC_TLS_CERT:
			tls_arg1 = proxy->tls_issuer;
			tls_arg2 = proxy->tls_subject;
			break;
		case ZBX_TCP_SEC_TLS_PSK:
			tls_arg1 = proxy->tls_psk_identity;
			tls_arg2 = proxy->tls_psk;
			break;
#else
		case ZBX_TCP_SEC_TLS_CERT:
		case ZBX_TCP_SEC_TLS_PSK:
			zabbix_log(LOG_LEVEL_ERR, "TLS connection is configured to be used with passive proxy \"%s\""
					" but support for TLS was not compiled into %s.", proxy->host,
					get_program_type_string(program_type));
			ret = CONFIG_ERROR;
			goto out;
#endif
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			goto out;
	}

	if (FAIL == (ret = zbx_tcp_connect(sock, CONFIG_SOURCE_IP, proxy->addr, proxy->port, timeout,
			proxy->tls_connect, tls_arg1, tls_arg2)))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot connect to proxy \"%s\": %s", proxy->host, zbx_socket_strerror());
		ret = NETWORK_ERROR;
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int	send_data_to_proxy(const DC_PROXY *proxy, zbx_socket_t *sock, const char *data, size_t size,
		size_t reserved, int flags)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() data:'%s'", __func__, data);

	if (FAIL == (ret = zbx_tcp_send_ext(sock, data, size, reserved, flags, 0)))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot send data to proxy \"%s\": %s", proxy->host, zbx_socket_strerror());

		ret = NETWORK_ERROR;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int	recv_data_from_proxy(const DC_PROXY *proxy, zbx_socket_t *sock)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (FAIL == (ret = zbx_tcp_recv(sock)))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot obtain data from proxy \"%s\": %s", proxy->host,
				zbx_socket_strerror());
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "obtained data from proxy \"%s\": [%s]", proxy->host, sock->buffer);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static void	disconnect_proxy(zbx_socket_t *sock)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_tcp_close(sock);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get historical data from proxy                                    *
 *                                                                            *
 * Parameters: proxy   - [IN/OUT] proxy data                                  *
 *             request - [IN] requested data type                             *
 *             data    - [OUT] data received from proxy                       *
 *             ts      - [OUT] timestamp when the proxy connection was        *
 *                             established                                    *
 *             tasks   - [IN] proxy task response flag                        *
 *                                                                            *
 * Return value: SUCCESS - processed successfully                             *
 *               other code - an error occurred                               *
 *                                                                            *
 * Comments: The proxy->compress property is updated depending on the         *
 *           protocol flags sent by proxy.                                    *
 *                                                                            *
 ******************************************************************************/
static int	get_data_from_proxy(DC_PROXY *proxy, const char *request, char **data, zbx_timespec_t *ts)
{
	zbx_socket_t	s;
	struct zbx_json	j;
	int		ret, flags = ZBX_TCP_PROTOCOL;
	char		*buffer = NULL;
	size_t		buffer_size, reserved = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() request:'%s'", __func__, request);

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addstring(&j, "request", request, ZBX_JSON_TYPE_STRING);

	if (0 != proxy->auto_compress)
	{
		if (SUCCEED != zbx_compress(j.buffer, j.buffer_size, &buffer, &buffer_size))
		{
			zabbix_log(LOG_LEVEL_ERR,"cannot compress data: %s", zbx_compress_strerror());
			ret = FAIL;
			goto out;
		}

		flags |= ZBX_TCP_COMPRESS;
		reserved = j.buffer_size;
		zbx_json_free(&j);	/* json buffer can be large, free as fast as possible */
	}

	if (SUCCEED == (ret = connect_to_proxy(proxy, &s, CONFIG_TRAPPER_TIMEOUT)))
	{
		/* get connection timestamp if required */
		if (NULL != ts)
			zbx_timespec(ts);

		if (0 != proxy->auto_compress)
		{
			ret = send_data_to_proxy(proxy, &s, buffer, buffer_size, reserved, flags);
			zbx_free(buffer);
		}
		else
		{
			ret = send_data_to_proxy(proxy, &s, j.buffer, j.buffer_size, 0, flags);
			zbx_json_free(&j);
		}

		if (SUCCEED == ret)
		{
			if (SUCCEED == (ret = recv_data_from_proxy(proxy, &s)))
			{
				if (0 != (s.protocol & ZBX_TCP_COMPRESS))
					proxy->auto_compress = 1;

				if (!ZBX_IS_RUNNING())
				{
					int	flags_response = ZBX_TCP_PROTOCOL;

					if (0 != (s.protocol & ZBX_TCP_COMPRESS))
						flags_response |= ZBX_TCP_COMPRESS;

					zbx_send_response_ext(&s, FAIL, "Zabbix server shutdown in progress", NULL,
							flags_response, CONFIG_TIMEOUT);

					zabbix_log(LOG_LEVEL_WARNING, "cannot process proxy data from passive proxy at"
							" \"%s\": Zabbix server shutdown in progress", s.peer);
					ret = FAIL;
				}
				else
				{
					ret = zbx_send_proxy_data_response(proxy, &s, NULL, SUCCEED,
							ZBX_PROXY_UPLOAD_UNDEFINED);

					if (SUCCEED == ret)
						*data = zbx_strdup(*data, s.buffer);
				}
			}
		}

		disconnect_proxy(&s);
	}
out:
	zbx_json_free(&j);
	zbx_free(buffer);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: sends configuration data to proxy                                 *
 *                                                                            *
 * Parameters: proxy - [IN/OUT] proxy data                                    *
 *                                                                            *
 * Return value: SUCCEED - processed successfully                             *
 *               other code - an error occurred                               *
 *                                                                            *
 * Comments: This function updates proxy version, compress and lastaccess     *
 *           properties.                                                      *
 *                                                                            *
 ******************************************************************************/
static int	proxy_send_configuration(DC_PROXY *proxy)
{
	char		*error = NULL, *buffer = NULL;
	int		ret, flags = ZBX_TCP_PROTOCOL;
	zbx_socket_t	s;
	struct zbx_json	j;
	size_t		buffer_size, reserved = 0;

	zbx_json_init(&j, 512 * ZBX_KIBIBYTE);

	zbx_json_addstring(&j, ZBX_PROTO_TAG_REQUEST, ZBX_PROTO_VALUE_PROXY_CONFIG, ZBX_JSON_TYPE_STRING);
	zbx_json_addobject(&j, ZBX_PROTO_TAG_DATA);

	if (SUCCEED != (ret = get_proxyconfig_data(proxy->hostid, &j, &error)))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot collect configuration data for proxy \"%s\": %s",
				proxy->host, error);
		goto out;
	}

	if (0 != proxy->auto_compress)
	{
		if (SUCCEED != zbx_compress(j.buffer, j.buffer_size, &buffer, &buffer_size))
		{
			zabbix_log(LOG_LEVEL_ERR,"cannot compress data: %s", zbx_compress_strerror());
			ret = FAIL;
			goto out;
		}

		flags |= ZBX_TCP_COMPRESS;
		reserved = j.buffer_size;
		zbx_json_free(&j);	/* json buffer can be large, free as fast as possible */
	}

	if (SUCCEED != (ret = connect_to_proxy(proxy, &s, CONFIG_TRAPPER_TIMEOUT)))
		goto out;

	if (0 != proxy->auto_compress)
	{
		zabbix_log(LOG_LEVEL_WARNING, "sending configuration data to proxy \"%s\" at \"%s\", datalen "
				ZBX_FS_SIZE_T ", bytes " ZBX_FS_SIZE_T " with compression ratio %.1f", proxy->host,
				s.peer, (zbx_fs_size_t)reserved, (zbx_fs_size_t)buffer_size,
				(double)reserved / buffer_size);

		ret = send_data_to_proxy(proxy, &s, buffer, buffer_size, reserved, flags);
		zbx_free(buffer);		/* json buffer can be large, free as fast as possible */
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "sending configuration data to proxy \"%s\" at \"%s\", datalen "
				ZBX_FS_SIZE_T, proxy->host, s.peer, (zbx_fs_size_t)j.buffer_size);

		ret = send_data_to_proxy(proxy, &s, j.buffer, j.buffer_size, reserved, flags);
		zbx_json_free(&j);	/* json buffer can be large, free as fast as possible */
	}

	if (SUCCEED == ret)
	{
		if (SUCCEED != (ret = zbx_recv_response(&s, 0, &error)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot send configuration data to proxy"
					" \"%s\" at \"%s\": %s", proxy->host, s.peer, error);
		}
		else
		{
			struct zbx_json_parse	jp;

			if (SUCCEED != zbx_json_open(s.buffer, &jp))
			{
				zabbix_log(LOG_LEVEL_WARNING, "invalid configuration data response received from proxy"
						" \"%s\" at \"%s\": %s", proxy->host, s.peer, zbx_json_strerror());
			}
			else
			{
				proxy->version = zbx_get_proxy_protocol_version(&jp);
				proxy->auto_compress = (0 != (s.protocol & ZBX_TCP_COMPRESS) ? 1 : 0);
				proxy->lastaccess = time(NULL);
			}
		}
	}

	disconnect_proxy(&s);
out:
	zbx_free(buffer);
	zbx_free(error);
	zbx_json_free(&j);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes proxy data request                                      *
 *                                                                            *
 * Parameters: proxy  - [IN/OUT] proxy data                                   *
 *             answer - [IN] data received from proxy                         *
 *             ts     - [IN] timestamp when the proxy connection was          *
 *                           established                                      *
 *             more   - [OUT] available data flag                             *
 *                                                                            *
 * Return value: SUCCEED - data were received and processed successfully      *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Comments: The proxy->version property is updated with the version number   *
 *           sent by proxy.                                                   *
 *                                                                            *
 ******************************************************************************/
static int	proxy_process_proxy_data(DC_PROXY *proxy, const char *answer, zbx_timespec_t *ts, int *more)
{
	struct zbx_json_parse	jp;
	char			*error = NULL;
	int			ret = FAIL, version;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	*more = ZBX_PROXY_DATA_DONE;

	if ('\0' == *answer)
	{
		zabbix_log(LOG_LEVEL_WARNING, "proxy \"%s\" at \"%s\" returned no proxy data:"
				" check allowed connection types and access rights", proxy->host, proxy->addr);
		goto out;
	}

	if (SUCCEED != zbx_json_open(answer, &jp))
	{
		zabbix_log(LOG_LEVEL_WARNING, "proxy \"%s\" at \"%s\" returned invalid proxy data: %s",
				proxy->host, proxy->addr, zbx_json_strerror());
		goto out;
	}

	version = zbx_get_proxy_protocol_version(&jp);

	if (SUCCEED != zbx_check_protocol_version(proxy, version))
	{
		goto out;
	}

	proxy->version = version;

	if (SUCCEED != (ret = process_proxy_data(proxy, &jp, ts, HOST_STATUS_PROXY_PASSIVE, more, &error)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "proxy \"%s\" at \"%s\" returned invalid proxy data: %s",
				proxy->host, proxy->addr, error);
	}

out:
	zbx_free(error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets data from proxy ('proxy data' request)                       *
 *                                                                            *
 * Parameters: proxy  - [IN] proxy data                                       *
 *             more   - [OUT] available data flag                             *
 *                                                                            *
 * Return value: SUCCEED - data were received and processed successfully      *
 *               other code - an error occurred                               *
 *                                                                            *
 * Comments: This function updates proxy version, compress and lastaccess     *
 *           properties.                                                      *
 *                                                                            *
 ******************************************************************************/
static int	proxy_get_data(DC_PROXY *proxy, int *more)
{
	char		*answer = NULL;
	int		ret;
	zbx_timespec_t	ts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != (ret = get_data_from_proxy(proxy, ZBX_PROTO_VALUE_PROXY_DATA, &answer, &ts)))
		goto out;

	/* handle pre 3.4 proxies that did not support proxy data request */
	if ('\0' == *answer)
	{
		proxy->version = ZBX_COMPONENT_VERSION(3, 2);
		zbx_free(answer);
		ret = FAIL;
		goto out;
	}

	proxy->lastaccess = time(NULL);
	ret = proxy_process_proxy_data(proxy, answer, &ts, more);
	zbx_free(answer);
out:
	if (SUCCEED == ret)
		zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s more:%d", __func__, zbx_result_string(ret), *more);
	else
		zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets data from proxy ('proxy data' request)                       *
 *                                                                            *
 * Parameters: proxy - [IN/OUT] the proxy data                                *
 *                                                                            *
 * Return value: SUCCEED - data were received and processed successfully      *
 *               other code - an error occurred                               *
 *                                                                            *
 * Comments: This function updates proxy version, compress and lastaccess     *
 *           properties.                                                      *
 *                                                                            *
 ******************************************************************************/
static int	proxy_get_tasks(DC_PROXY *proxy)
{
	char		*answer = NULL;
	int		ret = FAIL, more;
	zbx_timespec_t	ts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (ZBX_COMPONENT_VERSION(3, 2) >= proxy->version)
		goto out;

	if (SUCCEED != (ret = get_data_from_proxy(proxy, ZBX_PROTO_VALUE_PROXY_TASKS, &answer, &ts)))
		goto out;

	proxy->lastaccess = time(NULL);

	ret = proxy_process_proxy_data(proxy, answer, &ts, &more);

	zbx_free(answer);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve values of metrics from monitored hosts                   *
 *                                                                            *
 ******************************************************************************/
static int	process_proxy(void)
{
	DC_PROXY		proxy, proxy_old;
	int			num, i;
	time_t			now;
	zbx_dc_um_handle_t	*um_handle;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 == (num = DCconfig_get_proxypoller_hosts(&proxy, 1)))
		goto exit;

	now = time(NULL);

	um_handle = zbx_dc_open_user_macros();

	for (i = 0; i < num; i++)
	{
		int		ret = FAIL;
		unsigned char	update_nextcheck = 0;

		memcpy(&proxy_old, &proxy, sizeof(DC_PROXY));

		if (proxy.proxy_config_nextcheck <= now)
			update_nextcheck |= ZBX_PROXY_CONFIG_NEXTCHECK;
		if (proxy.proxy_data_nextcheck <= now)
			update_nextcheck |= ZBX_PROXY_DATA_NEXTCHECK;
		if (proxy.proxy_tasks_nextcheck <= now)
			update_nextcheck |= ZBX_PROXY_TASKS_NEXTCHECK;

		/* Check if passive proxy has been misconfigured on the server side. If it has happened more */
		/* recently than last synchronisation of cache then there is no point to retry connecting to */
		/* proxy again. The next reconnection attempt will happen after cache synchronisation. */
		if (proxy.last_cfg_error_time < DCconfig_get_last_sync_time())
		{
			char	*port = NULL;
			int	check_tasks = 0;

			proxy.addr = proxy.addr_orig;

			port = zbx_strdup(port, proxy.port_orig);
			zbx_substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
					&port, MACRO_TYPE_COMMON, NULL, 0);
			if (FAIL == is_ushort(port, &proxy.port))
			{
				zabbix_log(LOG_LEVEL_ERR, "invalid proxy \"%s\" port: \"%s\"", proxy.host, port);
				ret = CONFIG_ERROR;
				zbx_free(port);
				goto error;
			}
			zbx_free(port);

			if (proxy.proxy_config_nextcheck <= now)
			{
				if (SUCCEED != (ret = proxy_send_configuration(&proxy)))
					goto error;
			}

			if (proxy.proxy_tasks_nextcheck <= now)
				check_tasks = 1;

			if (proxy.proxy_data_nextcheck <= now)
			{
				int	more;

				do
				{
					if (FAIL == zbx_hc_check_proxy(proxy.hostid))
						break;

					if (SUCCEED != (ret = proxy_get_data(&proxy, &more)))
						goto error;

					check_tasks = 0;
				}
				while (ZBX_PROXY_DATA_MORE == more);
			}

			if (1 == check_tasks)
			{
				if (SUCCEED != (ret = proxy_get_tasks(&proxy)))
					goto error;
			}
		}
error:

		if (proxy_old.version != proxy.version || proxy_old.auto_compress != proxy.auto_compress ||
				proxy_old.lastaccess != proxy.lastaccess)
		{
			zbx_update_proxy_data(&proxy_old, proxy.version, proxy.lastaccess, proxy.auto_compress, 0);
		}

		DCrequeue_proxy(proxy.hostid, update_nextcheck, ret);
	}

	zbx_dc_close_user_macros(um_handle);
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return num;
}

ZBX_THREAD_ENTRY(proxypoller_thread, args)
{
	int			nextcheck, sleeptime = -1, processed = 0, old_processed = 0;
	double			sec, total_sec = 0.0, old_total_sec = 0.0;
	time_t			last_stat_time;
	zbx_ipc_async_socket_t	rtc;

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

	update_selfmon_counter(ZBX_PROCESS_STATE_BUSY);

#define STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_init_child();
#endif
	zbx_setproctitle("%s #%d [connecting to the database]", get_process_type_string(process_type), process_num);
	last_stat_time = time(NULL);

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	zbx_rtc_subscribe(&rtc, process_type, process_num);

	while (ZBX_IS_RUNNING())
	{
		zbx_uint32_t	rtc_cmd;
		unsigned char	*rtc_data;

		sec = zbx_time();
		zbx_update_env(sec);

		if (0 != sleeptime)
		{
			zbx_setproctitle("%s #%d [exchanged data with %d proxies in " ZBX_FS_DBL " sec,"
					" exchanging data]", get_process_type_string(process_type), process_num,
					old_processed, old_total_sec);
		}

		processed += process_proxy();
		total_sec += zbx_time() - sec;

		nextcheck = DCconfig_get_proxypoller_nextcheck();
		sleeptime = calculate_sleeptime(nextcheck, POLLER_DELAY);

		if (0 != sleeptime || STAT_INTERVAL <= time(NULL) - last_stat_time)
		{
			if (0 == sleeptime)
			{
				zbx_setproctitle("%s #%d [exchanged data with %d proxies in " ZBX_FS_DBL " sec,"
						" exchanging data]", get_process_type_string(process_type), process_num,
						processed, total_sec);
			}
			else
			{
				zbx_setproctitle("%s #%d [exchanged data with %d proxies in " ZBX_FS_DBL " sec,"
						" idle %d sec]", get_process_type_string(process_type), process_num,
						processed, total_sec, sleeptime);
				old_processed = processed;
				old_total_sec = total_sec;
			}
			processed = 0;
			total_sec = 0.0;
			last_stat_time = time(NULL);
		}

		if (SUCCEED == zbx_rtc_wait(&rtc, &rtc_cmd, &rtc_data, sleeptime) && 0 != rtc_cmd)
		{
			if (ZBX_RTC_SHUTDOWN == rtc_cmd)
				break;
		}
	}

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
#undef STAT_INTERVAL
}
