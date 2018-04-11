/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
#include "daemon.h"
#include "comms.h"
#include "zbxself.h"

#include "proxypoller.h"
#include "zbxserver.h"
#include "dbcache.h"
#include "db.h"
#include "zbxjson.h"
#include "log.h"
#include "proxy.h"
#include "../../libs/zbxcrypto/tls.h"
#include "../trapper/proxydata.h"

extern unsigned char	process_type, program_type;
extern int		server_num, process_num;

static int	connect_to_proxy(const DC_PROXY *proxy, zbx_socket_t *sock, int timeout)
{
	const char	*__function_name = "connect_to_proxy";

	int		ret = FAIL;
	const char	*tls_arg1, *tls_arg2;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() address:%s port:%hu timeout:%d conn:%u", __function_name, proxy->addr,
			proxy->port, timeout, (unsigned int)proxy->tls_connect);

	switch (proxy->tls_connect)
	{
		case ZBX_TCP_SEC_UNENCRYPTED:
			tls_arg1 = NULL;
			tls_arg2 = NULL;
			break;
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
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
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static int	send_data_to_proxy(const DC_PROXY *proxy, zbx_socket_t *sock, const char *data, size_t size)
{
	const char	*__function_name = "send_data_to_proxy";

	int		ret, flags = ZBX_TCP_PROTOCOL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() data:'%s'", __function_name, data);

	if (0 != proxy->auto_compress)
		flags |= ZBX_TCP_COMPRESS;

	if (FAIL == (ret = zbx_tcp_send_ext(sock, data, size, flags, 0)))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot send data to proxy \"%s\": %s", proxy->host, zbx_socket_strerror());

		ret = NETWORK_ERROR;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static int	recv_data_from_proxy(const DC_PROXY *proxy, zbx_socket_t *sock)
{
	const char	*__function_name = "recv_data_from_proxy";
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (FAIL == (ret = zbx_tcp_recv(sock)))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot obtain data from proxy \"%s\": %s", proxy->host,
				zbx_socket_strerror());
	}
	else
		zabbix_log(LOG_LEVEL_DEBUG, "obtained data from proxy \"%s\": [%s]", proxy->host, sock->buffer);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static void	disconnect_proxy(zbx_socket_t *sock)
{
	const char	*__function_name = "disconnect_proxy";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_tcp_close(sock);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: get_data_from_proxy                                              *
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
	const char	*__function_name = "get_data_from_proxy";

	zbx_socket_t	s;
	struct zbx_json	j;
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() request:'%s'", __function_name, request);

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addstring(&j, "request", request, ZBX_JSON_TYPE_STRING);

	if (SUCCEED == (ret = connect_to_proxy(proxy, &s, CONFIG_TRAPPER_TIMEOUT)))
	{
		/* get connection timestamp if required */
		if (NULL != ts)
			zbx_timespec(ts);

		if (SUCCEED == (ret = send_data_to_proxy(proxy, &s, j.buffer, j.buffer_size)))
		{
			if (SUCCEED == (ret = recv_data_from_proxy(proxy, &s)))
			{
				if (0 != (s.protocol & ZBX_TCP_COMPRESS))
						proxy->auto_compress = 1;

				ret = zbx_send_proxy_data_response(proxy, &s, NULL);

				if (SUCCEED == ret)
					*data = zbx_strdup(*data, s.buffer);
			}
		}

		disconnect_proxy(&s);
	}

	zbx_json_free(&j);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: proxy_send_configuration                                         *
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
	char		*error = NULL;
	int		ret;
	zbx_socket_t	s;
	struct zbx_json	j;

	zbx_json_init(&j, 512 * ZBX_KIBIBYTE);

	zbx_json_addstring(&j, ZBX_PROTO_TAG_REQUEST, ZBX_PROTO_VALUE_PROXY_CONFIG, ZBX_JSON_TYPE_STRING);
	zbx_json_addobject(&j, ZBX_PROTO_TAG_DATA);

	if (SUCCEED != (ret = get_proxyconfig_data(proxy->hostid, &j, &error)))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot collect configuration data for proxy \"%s\": %s",
				proxy->host, error);
		goto out;
	}

	if (SUCCEED != (ret = connect_to_proxy(proxy, &s, CONFIG_TRAPPER_TIMEOUT)))
		goto out;

	zabbix_log(LOG_LEVEL_WARNING, "sending configuration data to proxy \"%s\" at \"%s\", datalen " ZBX_FS_SIZE_T,
			proxy->host, s.peer, (zbx_fs_size_t)j.buffer_size);

	if (SUCCEED == (ret = send_data_to_proxy(proxy, &s, j.buffer, j.buffer_size)))
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
						" \"%s\" at \"%s\": %s", proxy->host, s.peer, zbx_json_strerror);
			}
			else
			{
				proxy->version = zbx_get_protocol_version(&jp);
				proxy->auto_compress = (0 != (s.protocol & ZBX_TCP_COMPRESS) ? 1 : 0);
				proxy->lastaccess = time(NULL);
			}
		}
	}

	disconnect_proxy(&s);
out:
	zbx_free(error);
	zbx_json_free(&j);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: proxy_check_error_response                                       *
 *                                                                            *
 * Purpose: checks proxy response for error message                           *
 *                                                                            *
 * Parameters: jp    - [IN] the json data received form proxy                 *
 *             error - [OUT] the error message                                *
 *                                                                            *
 * Return value: SUCCEED - proxy response doesn't have error message          *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	proxy_check_error_response(const struct zbx_json_parse *jp, char **error)
{
	char	response[MAX_STRING_LEN], *info = NULL;
	size_t	info_alloc = 0;

	/* response tag will be set only in the case of errors */
	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_RESPONSE, response, sizeof(response)))
		return SUCCEED;

	if (0 != strcmp(response, ZBX_PROTO_VALUE_FAILED))
		return SUCCEED;

	if (SUCCEED == zbx_json_value_by_name_dyn(jp, ZBX_PROTO_TAG_INFO, &info, &info_alloc))
	{
		zbx_free(*error);
		*error = info;
	}
	else
		*error = zbx_strdup(*error, "Unknown error");

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: proxy_get_host_availability                                      *
 *                                                                            *
 * Purpose: gets host availability data from proxy                            *
 *          ('host availability' request)                                     *
 *                                                                            *
 * Parameters: proxy - [IN/OUT] proxy data                                    *
 *                                                                            *
 * Return value: SUCCEED - data were received and processed successfully      *
 *               other code - an error occurred                               *
 *                                                                            *
 * Comments: The proxy->version property is updated with the version number   *
 *           sent by proxy.                                                   *
 *                                                                            *
 ******************************************************************************/
static int	proxy_get_host_availability(DC_PROXY *proxy)
{
	char			*answer = NULL, *error = NULL;
	struct zbx_json_parse	jp;
	int			ret = FAIL;

	if (SUCCEED != (ret = get_data_from_proxy(proxy, ZBX_PROTO_VALUE_HOST_AVAILABILITY, &answer, NULL)))
	{
		goto out;
	}

	if ('\0' == *answer)
	{
		zabbix_log(LOG_LEVEL_WARNING, "proxy \"%s\" at \"%s\" returned no host availability data:"
				" check allowed connection types and access rights", proxy->host, proxy->addr);
		goto out;
	}

	if (SUCCEED != zbx_json_open(answer, &jp))
	{
		zabbix_log(LOG_LEVEL_WARNING, "proxy \"%s\" at \"%s\" returned invalid host availability data:"
				" %s", proxy->host, proxy->addr, zbx_json_strerror());
		goto out;
	}

	proxy->version = zbx_get_protocol_version(&jp);

	if (SUCCEED != proxy_check_error_response(&jp, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "proxy \"%s\" at \"%s\" returned invalid host availability data:"
				" %s", proxy->host, proxy->addr, error);
		goto out;
	}

	if (SUCCEED != process_host_availability(&jp, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "proxy \"%s\" at \"%s\" returned invalid host availability data:"
				" %s", proxy->host, proxy->addr, error);
		goto out;
	}

	ret = SUCCEED;
out:
	zbx_free(error);
	zbx_free(answer);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: proxy_get_history_data                                           *
 *                                                                            *
 * Purpose: gets historical data from proxy                                   *
 *          ('history data' request)                                          *
 *                                                                            *
 * Parameters: proxy - [IN/OUT] proxy data                                    *
 *                                                                            *
 * Return value: SUCCEED - data were received and processed successfully      *
 *               other code - an error occurred                               *
 *                                                                            *
 * Comments: The proxy->version property is updated with the version number   *
 *           sent by proxy.                                                   *
 *                                                                            *
 ******************************************************************************/
static int	proxy_get_history_data(DC_PROXY *proxy)
{
	char			*answer = NULL, *error = NULL;
	struct zbx_json_parse	jp, jp_data;
	int			ret = FAIL;
	zbx_timespec_t		ts;

	while (SUCCEED == (ret = get_data_from_proxy(proxy, ZBX_PROTO_VALUE_HISTORY_DATA, &answer, &ts)))
	{
		if ('\0' == *answer)
		{
			zabbix_log(LOG_LEVEL_WARNING, "proxy \"%s\" at \"%s\" returned no history"
					" data: check allowed connection types and access rights",
					proxy->host, proxy->addr);
			break;
		}

		if (SUCCEED != zbx_json_open(answer, &jp))
		{
			zabbix_log(LOG_LEVEL_WARNING, "proxy \"%s\" at \"%s\" returned invalid"
					" history data: %s", proxy->host, proxy->addr, zbx_json_strerror());
			break;
		}

		proxy->version = zbx_get_protocol_version(&jp);

		if (SUCCEED != proxy_check_error_response(&jp, &error))
		{
			zabbix_log(LOG_LEVEL_WARNING, "proxy \"%s\" at \"%s\" returned invalid history data:"
					" %s", proxy->host, proxy->addr, error);
			break;
		}

		if (SUCCEED != process_proxy_history_data(proxy, &jp, &ts, &error))
		{
			zabbix_log(LOG_LEVEL_WARNING, "proxy \"%s\" at \"%s\" returned invalid"
					" history data: %s", proxy->host, proxy->addr, error);
			break;
		}

		if (SUCCEED == zbx_json_brackets_by_name(&jp, ZBX_PROTO_TAG_DATA, &jp_data))
		{
			if (ZBX_MAX_HRECORDS > zbx_json_count(&jp_data))
			{
				ret = SUCCEED;
				break;
			}
		}
	}

	zbx_free(error);
	zbx_free(answer);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: proxy_get_discovery_data                                         *
 *                                                                            *
 * Purpose: gets discovery data from proxy                                    *
 *          ('discovery data' request)                                        *
 *                                                                            *
 * Parameters: proxy - [IN/OUT] proxy data                                    *
 *                                                                            *
 * Return value: SUCCEED - data were received and processed successfully      *
 *               other code - an error occurred                               *
 *                                                                            *
 * Comments: The proxy->version property is updated with the version number   *
 *           sent by proxy.                                                   *
 *                                                                            *
 ******************************************************************************/
static int	proxy_get_discovery_data(DC_PROXY *proxy)
{
	char			*answer = NULL, *error = NULL;
	struct zbx_json_parse	jp, jp_data;
	int			ret = FAIL;
	zbx_timespec_t		ts;

	while (SUCCEED == (ret = get_data_from_proxy(proxy, ZBX_PROTO_VALUE_DISCOVERY_DATA, &answer, &ts)))
	{
		if ('\0' == *answer)
		{
			zabbix_log(LOG_LEVEL_WARNING, "proxy \"%s\" at \"%s\" returned no discovery"
					" data: check allowed connection types and access rights",
					proxy->host, proxy->addr);
			break;
		}

		if (SUCCEED != zbx_json_open(answer, &jp))
		{
			zabbix_log(LOG_LEVEL_WARNING, "proxy \"%s\" at \"%s\" returned invalid"
					" discovery data: %s", proxy->host, proxy->addr,
					zbx_json_strerror());
			break;
		}

		proxy->version = zbx_get_protocol_version(&jp);

		if (SUCCEED != proxy_check_error_response(&jp, &error))
		{
			zabbix_log(LOG_LEVEL_WARNING, "proxy \"%s\" at \"%s\" returned invalid discovery data:"
					" %s", proxy->host, proxy->addr, error);
			break;
		}

		if (SUCCEED != process_discovery_data(&jp, &ts, &error))
		{
			zabbix_log(LOG_LEVEL_WARNING, "proxy \"%s\" at \"%s\" returned invalid"
					" discovery data: %s", proxy->host, proxy->addr, error);
			break;
		}

		if (SUCCEED == zbx_json_brackets_by_name(&jp, ZBX_PROTO_TAG_DATA, &jp_data))
		{
			if (ZBX_MAX_HRECORDS > zbx_json_count(&jp_data))
			{
				ret = SUCCEED;
				break;
			}
		}
	}

	zbx_free(error);
	zbx_free(answer);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: proxy_get_auto_registration                                      *
 *                                                                            *
 * Purpose: gets auto registration data from proxy                            *
 *          ('auto registration' request)                                     *
 *                                                                            *
 * Parameters: proxy - [IN/OUT] proxy data                                    *
 *                                                                            *
 * Return value: SUCCEED - data were received and processed successfully      *
 *               other code - an error occurred                               *
 *                                                                            *
 * Comments: The proxy->version property is updated with the version number   *
 *           sent by proxy.                                                   *
 *                                                                            *
 ******************************************************************************/
static int	proxy_get_auto_registration(DC_PROXY *proxy)
{
	char			*answer = NULL, *error = NULL;
	struct zbx_json_parse	jp, jp_data;
	int			ret = FAIL;
	zbx_timespec_t		ts;

	while (SUCCEED == (ret = get_data_from_proxy(proxy, ZBX_PROTO_VALUE_AUTO_REGISTRATION_DATA, &answer, &ts)))
	{
		if ('\0' == *answer)
		{
			zabbix_log(LOG_LEVEL_WARNING, "proxy \"%s\" at \"%s\" returned no auto"
					" registration data: check allowed connection types and"
					" access rights", proxy->host, proxy->addr);
			break;
		}

		if (SUCCEED != zbx_json_open(answer, &jp))
		{
			zabbix_log(LOG_LEVEL_WARNING, "proxy \"%s\" at \"%s\" returned invalid"
					" auto registration data: %s", proxy->host, proxy->addr,
					zbx_json_strerror());
			break;
		}

		proxy->version = zbx_get_protocol_version(&jp);

		if (SUCCEED != proxy_check_error_response(&jp, &error))
		{
			zabbix_log(LOG_LEVEL_WARNING, "proxy \"%s\" at \"%s\" returned invalid auto registration data:"
					" %s", proxy->host, proxy->addr, error);
			break;
		}

		if (SUCCEED != process_auto_registration(&jp, proxy->hostid, &ts, &error))
		{
			zabbix_log(LOG_LEVEL_WARNING, "proxy \"%s\" at \"%s\" returned invalid"
					" auto registration data: %s", proxy->host, proxy->addr, error);
			break;
		}

		if (SUCCEED == zbx_json_brackets_by_name(&jp, ZBX_PROTO_TAG_DATA, &jp_data))
		{
			if (ZBX_MAX_HRECORDS > zbx_json_count(&jp_data))
			{
				ret = SUCCEED;
				break;
			}
		}
	}

	zbx_free(error);
	zbx_free(answer);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: proxy_process_proxy_data                                         *
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
	const char		*__function_name = "proxy_process_proxy_data";

	struct zbx_json_parse	jp;
	char			*error = NULL;
	int			ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

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

	proxy->version = zbx_get_protocol_version(&jp);

	if (SUCCEED != (ret = process_proxy_data(proxy, &jp, ts, &error)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "proxy \"%s\" at \"%s\" returned invalid proxy data: %s",
				proxy->host, proxy->addr, error);
	}
	else
	{
		char	value[MAX_STRING_LEN];

		if (SUCCEED == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_MORE, value, sizeof(value)))
			*more = atoi(value);
	}
out:
	zbx_free(error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: proxy_get_data                                                   *
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
	const char	*__function_name = "proxy_get_data";

	char		*answer = NULL;
	int		ret;
	zbx_timespec_t	ts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (0 == proxy->version)
	{
		if (SUCCEED != (ret = get_data_from_proxy(proxy, ZBX_PROTO_VALUE_PROXY_DATA, &answer, &ts)))
			goto out;

		if ('\0' == *answer)
		{
			proxy->version = ZBX_COMPONENT_VERSION(3, 2);
			zbx_free(answer);
		}
	}

	if (ZBX_COMPONENT_VERSION(3, 2) == proxy->version)
	{
		if (SUCCEED != (ret = proxy_get_host_availability(proxy)))
			goto out;

		proxy->lastaccess = time(NULL);

		if (SUCCEED != (ret = proxy_get_history_data(proxy)))
			goto out;

		proxy->lastaccess = time(NULL);

		if (SUCCEED != (ret = proxy_get_discovery_data(proxy)))
			goto out;

		proxy->lastaccess = time(NULL);

		if (SUCCEED != (ret = proxy_get_auto_registration(proxy)))
			goto out;

		proxy->lastaccess = time(NULL);

		/* the above functions will retrieve all available data for 3.2 and older proxies */
		*more = ZBX_PROXY_DATA_DONE;
		goto out;
	}

	if (NULL == answer && SUCCEED != (ret = get_data_from_proxy(proxy, ZBX_PROTO_VALUE_PROXY_DATA, &answer, &ts)))
		goto out;

	proxy->lastaccess = time(NULL);

	ret = proxy_process_proxy_data(proxy, answer, &ts, more);

	zbx_free(answer);
out:
	if (SUCCEED == ret)
		zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s more:%d", __function_name, zbx_result_string(ret), *more);
	else
		zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: proxy_get_tasks                                                  *
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
	const char	*__function_name = "proxy_get_tasks";

	char		*answer = NULL;
	int		ret = FAIL, more;
	zbx_timespec_t	ts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (ZBX_COMPONENT_VERSION(3, 2) >= proxy->version)
		goto out;

	if (SUCCEED != (ret = get_data_from_proxy(proxy, ZBX_PROTO_VALUE_PROXY_TASKS, &answer, &ts)))
		goto out;

	proxy->lastaccess = time(NULL);

	ret = proxy_process_proxy_data(proxy, answer, &ts, &more);

	zbx_free(answer);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: process_proxy                                                    *
 *                                                                            *
 * Purpose: retrieve values of metrics from monitored hosts                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	process_proxy(void)
{
	const char	*__function_name = "process_proxy";

	DC_PROXY	proxy, proxy_old;
	int		num, i;
	time_t		now;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (0 == (num = DCconfig_get_proxypoller_hosts(&proxy, 1)))
		goto exit;

	now = time(NULL);

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

			proxy.addr = proxy.addr_orig;

			port = zbx_strdup(port, proxy.port_orig);
			substitute_simple_macros(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
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

			if (proxy.proxy_data_nextcheck <= now)
			{
				int	more;

				do
				{
					if (SUCCEED != (ret = proxy_get_data(&proxy, &more)))
						goto error;
				}
				while (ZBX_PROXY_DATA_MORE == more);
			}
			else if (proxy.proxy_tasks_nextcheck <= now)
			{
				if (SUCCEED != (ret = proxy_get_tasks(&proxy)))
					goto error;
			}
		}
error:
		if (proxy_old.version != proxy.version || proxy_old.auto_compress != proxy.auto_compress ||
				proxy_old.lastaccess != proxy.lastaccess)
		{
			zbx_update_proxy_data(&proxy_old, proxy.version, proxy.lastaccess, proxy.auto_compress);
		}

		DCrequeue_proxy(proxy.hostid, update_nextcheck, ret);
	}
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return num;
}

ZBX_THREAD_ENTRY(proxypoller_thread, args)
{
	int	nextcheck, sleeptime = -1, processed = 0, old_processed = 0;
	double	sec, total_sec = 0.0, old_total_sec = 0.0;
	time_t	last_stat_time;

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

#define STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_init_child();
#endif
	zbx_setproctitle("%s #%d [connecting to the database]", get_process_type_string(process_type), process_num);
	last_stat_time = time(NULL);

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;)
	{
		zbx_handle_log();

		if (0 != sleeptime)
		{
			zbx_setproctitle("%s #%d [exchanged data with %d proxies in " ZBX_FS_DBL " sec,"
					" exchanging data]", get_process_type_string(process_type), process_num,
					old_processed, old_total_sec);
		}

		sec = zbx_time();
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

		zbx_sleep_loop(sleeptime);

#if !defined(_WINDOWS) && defined(HAVE_RESOLV_H)
		zbx_update_resolver_conf();	/* handle /etc/resolv.conf update */
#endif
	}
#undef STAT_INTERVAL
}
