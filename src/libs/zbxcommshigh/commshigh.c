/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#include "zbxcommshigh.h"

#include "zbxjson.h"
#include "zbxlog.h"
#include "zbxip.h"
#include "zbxcomms.h"
#include "zbxcrypto.h"
#include "zbxnum.h"

#if !defined(_WINDOWS) && !defined(__MINGW32)
#include "zbxnix.h"
#endif

#include "zbxcfg.h"

void	zbx_addrs_failover(zbx_vector_addr_ptr_t *addrs)
{
	if (1 < addrs->values_num)
	{
		zbx_addr_t	*addr = addrs->values[0];

		zbx_vector_addr_ptr_remove(addrs, 0);
		zbx_vector_addr_ptr_append(addrs, addr);
	}
}

static int	zbx_tcp_connect_failover(zbx_socket_t *s, const char *source_ip, zbx_vector_addr_ptr_t *addrs,
		int timeout, int connect_timeout, unsigned int tls_connect, const char *tls_arg1, const char *tls_arg2,
		int loglevel)
{
	int	i, ret = FAIL;

	for (i = 0; i < addrs->values_num; i++)
	{
		zbx_addr_t	*addr;

		addr = (zbx_addr_t *)addrs->values[0];

		if (FAIL != (ret = zbx_tcp_connect(s, source_ip, addr->ip, addr->port, connect_timeout, tls_connect,
				tls_arg1, tls_arg2, ZBX_DNS_FAILOVER_ENABLED)))
		{
			zbx_socket_set_deadline(s, timeout);
			break;
		}

		zabbix_log(loglevel, "Unable to connect to [%s]:%d [%s]",
				((zbx_addr_t *)addrs->values[0])->ip, ((zbx_addr_t *)addrs->values[0])->port,
				zbx_socket_strerror());

		zbx_addrs_failover(addrs);
	}

	return ret;
}

int	zbx_connect_to_server(zbx_socket_t *sock, const char *source_ip, zbx_vector_addr_ptr_t *addrs, int timeout,
		int connect_timeout, int retry_interval, int level, const zbx_config_tls_t *config_tls)
{
	int		res = FAIL;
	const char	*tls_arg1, *tls_arg2;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() [%s]:%d [timeout:%d, connection timeout:%d]", __func__,
			addrs->values[0]->ip, addrs->values[0]->port, timeout, connect_timeout);

	switch (config_tls->connect_mode)
	{
		case ZBX_TCP_SEC_UNENCRYPTED:
			tls_arg1 = NULL;
			tls_arg2 = NULL;
			break;
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		case ZBX_TCP_SEC_TLS_CERT:
			tls_arg1 = config_tls->server_cert_issuer;
			tls_arg2 = config_tls->server_cert_subject;
			break;
		case ZBX_TCP_SEC_TLS_PSK:
			tls_arg1 = config_tls->psk_identity;
			tls_arg2 = NULL;	/* zbx_tls_connect() will find PSK */
			break;
#endif
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			goto out;
	}

	if (FAIL == (res = zbx_tcp_connect_failover(sock, source_ip, addrs, timeout, connect_timeout,
			config_tls->connect_mode, tls_arg1, tls_arg2, level)))
	{
		if (0 != retry_interval)
		{
#if !defined(_WINDOWS) && !defined(__MINGW32)
			int	lastlogtime = (int)time(NULL);

			zabbix_log(LOG_LEVEL_WARNING, "Will try to reconnect every %d second(s)",
					retry_interval);

			while (ZBX_IS_RUNNING() && FAIL == (res = zbx_tcp_connect_failover(sock, source_ip, addrs,
					timeout, connect_timeout, config_tls->connect_mode, tls_arg1,
					tls_arg2, LOG_LEVEL_DEBUG)))
			{
				int	now = (int)time(NULL), retries_num = retry_interval;

				if (ZBX_LOG_ENTRY_INTERVAL_DELAY <= now - lastlogtime)
				{
					zabbix_log(LOG_LEVEL_WARNING, "Still unable to connect...");
					lastlogtime = now;
				}

				while (ZBX_IS_RUNNING() && 0 < retries_num)
				{
					sleep(1);
					retries_num--;
				}
			}

			if (FAIL != res)
				zabbix_log(LOG_LEVEL_WARNING, "Connection restored.");
#else
			zabbix_log(LOG_LEVEL_WARNING, "Could not to connect to server.");
#endif
		}
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(res));

	return res;
}

void	zbx_disconnect_from_server(zbx_socket_t *sock)
{
	zbx_tcp_close(sock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get configuration and other data from server                      *
 *                                                                            *
 * Return value: SUCCEED - processed successfully                             *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 ******************************************************************************/
unsigned char	zbx_negotiate_proxy_reply_flags(int peer_protocol)
{
	unsigned char	flags = ZBX_TCP_PROTOCOL;

	if (0 != (peer_protocol & (ZBX_TCP_COMPRESS_ZSTD | ZBX_TCP_BLAKE3)))
	{
		flags |= (unsigned char)(peer_protocol & ZBX_TCP_BLAKE3);

		if (0 != (peer_protocol & ZBX_TCP_COMPRESS_MASK))
			flags |= (unsigned char)(peer_protocol & ZBX_TCP_COMPRESS_MASK);
		else
			flags |= ZBX_TCP_COMPRESS_EXT;

		return flags;
	}

	return ZBX_TCP_PROTOCOL | ZBX_TCP_COMPRESS;
}

int	zbx_get_data_from_server_ext(zbx_socket_t *sock, char **buffer, size_t buffer_size, size_t reserved,
		unsigned char flags, char **error)
{
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != zbx_tcp_send_ext(sock, *buffer, buffer_size, reserved, flags, 0))
	{
		*error = zbx_strdup(*error, zbx_socket_strerror());
		goto exit;
	}

	zbx_free(*buffer);

	if (SUCCEED != zbx_tcp_recv_large(sock))
	{
		*error = zbx_strdup(*error, zbx_socket_strerror());
		goto exit;
	}

	if (ZBX_PROTO_ERROR == zbx_tcp_read_close_notify(sock, 0, NULL))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot gracefully close connection: %s",
				zbx_socket_strerror());
	}

#ifdef ZBX_DEBUG
	zabbix_log(LOG_LEVEL_DEBUG, "Received [%s] from server", sock->buffer);
#else
	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_TRACE))
	{
		char	*log_json;

		if (NULL != (log_json = zbx_sanitize_proxyconfig_json(sock->buffer)))
		{
			zabbix_log(LOG_LEVEL_TRACE, "%s() configuration: %s", __func__, log_json);
			zbx_free(log_json);
		}
		else
			zabbix_log(LOG_LEVEL_TRACE, "%s() configuration: invalid JSON", __func__);
	}
#endif

	ret = SUCCEED;
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

int	zbx_get_data_from_server(zbx_socket_t *sock, char **buffer, size_t buffer_size, size_t reserved, char **error)
{
	return zbx_get_data_from_server_ext(sock, buffer, buffer_size, reserved, ZBX_TCP_PROXY_PROTOCOL, error);
}

/******************************************************************************
 *                                                                            *
 * Purpose: send data to server with explicit transport flags                 *
 *                                                                            *
 * Return value: SUCCEED - processed successfully                             *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 ******************************************************************************/
int	zbx_put_data_to_server_ext(zbx_socket_t *sock, char **buffer, size_t buffer_size, size_t reserved,
		unsigned char flags, char **error)
{
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() datalen:" ZBX_FS_SIZE_T, __func__, (zbx_fs_size_t)buffer_size);

	if (SUCCEED != zbx_tcp_send_ext(sock, *buffer, buffer_size, reserved, flags, 0))
	{
		*error = zbx_strdup(*error, zbx_socket_strerror());
		goto out;
	}

	if (SUCCEED != zbx_recv_response(sock, 0, error))
		goto out;

	zbx_free(*buffer);
	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

int	zbx_put_data_to_server(zbx_socket_t *sock, char **buffer, size_t buffer_size, size_t reserved, char **error)
{
	return zbx_put_data_to_server_ext(sock, buffer, buffer_size, reserved, ZBX_TCP_PROXY_PROTOCOL, error);
}

/******************************************************************************
 *                                                                            *
 * Purpose: send json SUCCEED or FAIL to socket along with an info message    *
 *                                                                            *
 * Parameters: sock     - [IN] socket descriptor                              *
 *             result   - [IN] SUCCEED or FAIL                                *
 *             info     - [IN] info message (optional)                        *
 *             version  - [IN] the version data (optional)                    *
 *             protocol - [IN] the transport protocol                         *
 *             timeout  - [IN] timeout for this operation                     *
 *             ext      - [IN] additional data to merge into response json    *
 *                                                                            *
 * Return value: SUCCEED - data successfully transmitted                      *
 *               NETWORK_ERROR - network related error occurred               *
 *                                                                            *
 ******************************************************************************/
int	zbx_send_response_json(zbx_socket_t *sock, int result, const char *info, const char *version, int protocol,
		int timeout, const char *ext)
{
	struct zbx_json	json;
	const char	*resp;
	int		ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_json_init_with(&json, ext, NULL == ext ? 0 : strlen(ext));

	resp = SUCCEED == result ? ZBX_PROTO_VALUE_SUCCESS : ZBX_PROTO_VALUE_FAILED;

	zbx_json_addstring(&json, ZBX_PROTO_TAG_RESPONSE, resp, ZBX_JSON_TYPE_STRING);

	if (NULL != info && '\0' != *info)
		zbx_json_addstring(&json, ZBX_PROTO_TAG_INFO, info, ZBX_JSON_TYPE_STRING);

	if (NULL != version)
		zbx_json_addstring(&json, ZBX_PROTO_TAG_VERSION, version, ZBX_JSON_TYPE_STRING);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() '%s'", __func__, json.buffer);

	if (FAIL == (ret = zbx_tcp_send_ext(sock, json.buffer, strlen(json.buffer), 0, (unsigned char)protocol,
			timeout)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Error sending result back: %s", zbx_socket_strerror());
		ret = NETWORK_ERROR;
	}

	zbx_json_free(&json);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: send json SUCCEED or FAIL to socket along with an info message    *
 *                                                                            *
 * Parameters: sock     - [IN] socket descriptor                              *
 *             result   - [IN] SUCCEED or FAIL                                *
 *             info     - [IN] info message (optional)                        *
 *             version  - [IN] the version data (optional)                    *
 *             protocol - [IN] the transport protocol                         *
 *             timeout  - [IN] timeout for this operation                     *
 *                                                                            *
 * Return value: SUCCEED - data successfully transmitted                      *
 *               NETWORK_ERROR - network related error occurred               *
 *                                                                            *
 ******************************************************************************/
int	zbx_send_response_ext(zbx_socket_t *sock, int result, const char *info, const char *version, int protocol,
		int timeout)
{
	return zbx_send_response_json(sock, result, info, version, protocol, timeout, NULL);
}

/******************************************************************************
 *                                                                            *
 * Purpose: read a response message (in JSON format) from socket, optionally  *
 *          extract "info" value.                                             *
 *                                                                            *
 * Parameters: sock    - [IN] socket descriptor                               *
 *             timeout - [IN] timeout for this operation                      *
 *             error   - [OUT] pointer to error message                       *
 *                                                                            *
 * Return value: SUCCEED - "response":"success" successfully retrieved        *
 *               FAIL    - otherwise                                          *
 * Comments:                                                                  *
 *     Allocates memory.                                                      *
 *                                                                            *
 *     If an error occurs, the function allocates dynamic memory for an error *
 *     message and writes its address into location pointed to by "error"     *
 *     parameter.                                                             *
 *                                                                            *
 *     When the "info" value is present in the response message then function *
 *     copies the "info" value into the "error" buffer as additional          *
 *     information                                                            *
 *                                                                            *
 *     IMPORTANT: it is a responsibility of the caller to release the         *
 *                "error" memory !                                            *
 *                                                                            *
 ******************************************************************************/
int	zbx_recv_response(zbx_socket_t *sock, int timeout, char **error)
{
	struct zbx_json_parse	jp;
	char			value[16];
	int			ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != zbx_tcp_recv_to(sock, timeout))
	{
		/* since we have successfully sent data earlier, we assume the other */
		/* side is just too busy processing our data if there is no response */
		*error = zbx_strdup(*error, zbx_socket_strerror());
		goto out;
	}

	if (ZBX_PROTO_ERROR == zbx_tcp_read_close_notify(sock, timeout, NULL))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot gracefully close connection to proxy: %s",
				zbx_socket_strerror());
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() '%s'", __func__, sock->buffer);

	/* deal with empty string here because zbx_json_open() does not produce an error message in this case */
	if ('\0' == *sock->buffer)
	{
		*error = zbx_strdup(*error, "empty string received");
		goto out;
	}

	if (SUCCEED != zbx_json_open(sock->buffer, &jp))
	{
		*error = zbx_strdup(*error, zbx_json_strerror());
		goto out;
	}

	if (SUCCEED != zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_RESPONSE, value, sizeof(value), NULL))
	{
		*error = zbx_strdup(*error, "no \"" ZBX_PROTO_TAG_RESPONSE "\" tag");
		goto out;
	}

	if (0 != strcmp(value, ZBX_PROTO_VALUE_SUCCESS))
	{
		char	*info = NULL;
		size_t	info_alloc = 0;

		if (SUCCEED == zbx_json_value_by_name_dyn(&jp, ZBX_PROTO_TAG_INFO, &info, &info_alloc, NULL))
			*error = zbx_strdup(*error, info);
		else
			*error = zbx_dsprintf(*error, "negative response \"%s\"", value);
		zbx_free(info);
		goto out;
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add redirection information to json response                      *
 *                                                                            *
 * Parameters: json     - [IN/OUT] json response                              *
 *             redirect - [IN] redirection information                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_add_redirect_response(struct zbx_json *json, const zbx_comms_redirect_t *redirect)
{
	zbx_json_addobject(json, ZBX_PROTO_TAG_REDIRECT);
	if (ZBX_REDIRECT_RESET != redirect->reset)
	{
		zbx_json_adduint64(json, ZBX_PROTO_TAG_REVISION, redirect->revision);
		zbx_json_addstring(json, ZBX_PROTO_TAG_ADDRESS, redirect->address, ZBX_JSON_TYPE_STRING);
	}
	else
		zbx_json_addstring(json, ZBX_PROTO_TAG_RESET, "true", ZBX_JSON_TYPE_TRUE);

	zbx_json_close(json);
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse redirect block                                              *
 *                                                                            *
 ******************************************************************************/
int	zbx_parse_redirect_response(struct zbx_json_parse *jp, char **host, unsigned short *port,
		zbx_uint64_t *revision, unsigned char *reset)
{
	struct zbx_json_parse	jp_redirect;
	char			buf[MAX_STRING_LEN];

	if (FAIL == zbx_json_brackets_by_name(jp, ZBX_PROTO_TAG_REDIRECT, &jp_redirect))
		return FAIL;

	if (SUCCEED == zbx_json_value_by_name(&jp_redirect, ZBX_PROTO_TAG_RESET, buf, sizeof(buf), NULL) &&
			0 == strcmp(buf, ZBX_PROTO_VALUE_TRUE))
	{
		*reset = ZBX_REDIRECT_RESET;
		return SUCCEED;
	}
	else
		*reset = ZBX_REDIRECT_NONE;

	if (FAIL == zbx_json_value_by_name(&jp_redirect, ZBX_PROTO_TAG_REVISION, buf, sizeof(buf), NULL))
		return FAIL;

	if (FAIL == zbx_is_uint64(buf, revision))
		return FAIL;

	if (FAIL == zbx_json_value_by_name(&jp_redirect, ZBX_PROTO_TAG_ADDRESS, buf, sizeof(buf), NULL))
		return FAIL;

	if (FAIL == zbx_parse_serveractive_element(buf, host, port, 0))
		return FAIL;

	if (0 == *port)
		*port = ZBX_DEFAULT_SERVER_PORT;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check response for redirect tag                                   *
 *                                                                            *
 * Parameters: data  - [IN] response                                          *
 *             addrs - [IN/OUT] address list                                  *
 *                                                                            *
 * Return value: ZBX_REDIRECT_RESET - redirect reset, probably proxy lost     *
 *                                    connection to server                    *
 *               ZBX_REDIRECT_RETRY - normal redirect with retry              *
 *               ZBX_REDIRECT_NONE  - redirect with revision older than used  *
 *               ZBX_REDIRECT_FAIL  - wrong or non-redirect message           *
 *                                                                            *
 * Comments: In the case of valid and fresh redirect information either the   *
 *           existing redirect address is updated and moved at the start of   *
 *           address list or a new address is created and inserted at the     *
 *           start of address list.                                           *
 *                                                                            *
 ******************************************************************************/
static int	comms_check_redirect(const char *data, zbx_vector_addr_ptr_t *addrs)
{
	zbx_json_parse_t	jp;
	char			buf[MAX_STRING_LEN], *host = NULL;
	zbx_uint64_t		revision;
	int			i;
	zbx_addr_t		*addr;
	unsigned short		port;
	unsigned char		reset;

	if (FAIL == zbx_json_open(data, &jp))
		return ZBX_REDIRECT_FAIL;

	if (FAIL == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_RESPONSE, buf, sizeof(buf), NULL))
		return ZBX_REDIRECT_FAIL;

	if (0 != strcmp(buf, ZBX_PROTO_VALUE_FAILED))
		return ZBX_REDIRECT_FAIL;

	if (SUCCEED != zbx_parse_redirect_response(&jp, &host, &port, &revision, &reset))
		return ZBX_REDIRECT_FAIL;

	if (ZBX_REDIRECT_RESET == reset)
	{
		zbx_free(host);

		/* move redirected address at the end of address list */
		zbx_vector_addr_ptr_append(addrs, addrs->values[0]);
		zbx_vector_addr_ptr_remove(addrs, 0);

		return ZBX_REDIRECT_RESET;
	}

	/* check if new revision is bigger than stored redirected proxy revision */
	for (i = 0; i < addrs->values_num; i++)
	{
		if (addrs->values[i]->revision > revision)
		{
			zbx_free(host);
			return ZBX_REDIRECT_NONE;
		}

		/* remove old revision of redirection */
		if (0 != addrs->values[i]->revision)
		{
			addr = addrs->values[i];
			zbx_vector_addr_ptr_remove(addrs, i);
			zbx_free(addr->ip);
			zbx_free(addr);
			break;
		}
	}

	/* Add redirected address to beginning of list */
	addr = (zbx_addr_t *)zbx_malloc(NULL, sizeof(zbx_addr_t));
	addr->ip = host;
	addr->revision = revision;
	addr->port = (0 == port ? ZBX_DEFAULT_SERVER_PORT : port);
	zbx_vector_addr_ptr_insert(addrs, addr, 0);

	return ZBX_REDIRECT_RETRY;
}

/******************************************************************************
 *                                                                            *
 * Purpose: sends data to socket and receives response                        *
 *                                                                            *
 * Parameters: sock  - [IN] socket descriptor                                 *
 *             data  - [IN] data to send                                      *
 *             addr  - [IN] address information for logging                   *
 *             out   - [IN] wait for response if not NULL                     *
 *             error - [OUT] error message in case of failure                 *
 *                                                                            *
 * Return value: SUCCEED - data successfully exchanged                        *
 *               SEND_ERROR - failed to send data                             *
 *               RECV_ERROR - failed to receive response                      *
 *                                                                            *
 ******************************************************************************/
static int	zbx_comms_exchange_data(zbx_socket_t *sock, const char *data, zbx_addr_t *addr,
		char **out, char **error)
{
	zabbix_log(LOG_LEVEL_DEBUG, "sending to [%s]:%d: %s", addr->ip, addr->port, data);

	if (SUCCEED != zbx_tcp_send(sock, data))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "unable to send to [%s]:%d: %s",
					addr->ip, addr->port, zbx_socket_strerror());

		if (NULL != error)
			*error = zbx_strdup(NULL, zbx_socket_strerror());

		return SEND_ERROR;
	}

	if (SUCCEED != zbx_tcp_recv(sock))
	{
		/* if no data is expected then recv failure means */
		/* the other side closed connection as expected   */
		if (NULL == out)
			return SUCCEED;

		zabbix_log(LOG_LEVEL_DEBUG, "unable to receive from [%s]:%d: %s",
					addr->ip, addr->port, zbx_socket_strerror());

		if (NULL != error)
			*error = zbx_strdup(NULL, zbx_socket_strerror());

		return RECV_ERROR;
	}
	else
	{
		if (ZBX_PROTO_ERROR == zbx_tcp_read_close_notify(sock, 0, NULL))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot gracefully close connection: %s",
					zbx_socket_strerror());
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "received: %s", sock->buffer);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: connect to a host and exchange data                               *
 *                                                                            *
 * Return value: SUCCEED - data was exchanged successfully                    *
 *               CONNECT_ERROR - connection error                             *
 *               SEND_ERROR - request sending error                           *
 *               RECV_ERROR - response reading error                          *
 *                                                                            *
 * Comments: If response contains valid redirect block the address list will  *
 *           be updated accordingly and connection will be retried with the   *
 *           new address.                                                     *
 *                                                                            *
 ******************************************************************************/
int	zbx_comms_exchange_with_redirect(const char *source_ip, zbx_vector_addr_ptr_t *addrs, int timeout,
		int connect_timeout, int retry_interval, int loglevel, const zbx_config_tls_t *config_tls,
		const char *data, char *(*connect_callback)(void *), void *cb_data, char **out, char **error)
{
	zbx_socket_t		sock;
	int			conn_ret, ret = FAIL, retries = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);
	conn_ret = zbx_connect_to_server(&sock, source_ip, addrs, timeout, connect_timeout, retry_interval,
			loglevel, config_tls);

	for (;;)
	{
		if (SUCCEED != conn_ret)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "unable to connect to [%s]:%d: %s",
					addrs->values[0]->ip, addrs->values[0]->port, zbx_socket_strerror());

			if (NULL != error)
				*error = zbx_strdup(NULL, zbx_socket_strerror());

			ret = CONNECT_ERROR;

			goto out;
		}

		if (NULL != connect_callback)
			data = connect_callback(cb_data);

		if (SUCCEED == (ret = zbx_comms_exchange_data(&sock, data, addrs->values[0], out, error)))
		{
			if (ZBX_REDIRECT_FAIL != (conn_ret = comms_check_redirect(sock.buffer, addrs)))
			{
				if (0 != retries)
				{
					if (NULL != error)
						*error = zbx_strdup(NULL, "sequential redirect responses detected");

					ret = CONNECT_ERROR;
					zbx_tcp_close(&sock);

					goto out;
				}
				retries++;
			}

			if (ZBX_REDIRECT_RESET == conn_ret)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s() redirect response found, retrying to: [%s]:%hu",
						__func__, addrs->values[0]->ip, addrs->values[0]->port);

				zbx_tcp_close(&sock);

				conn_ret = zbx_connect_to_server(&sock, source_ip, addrs, timeout, connect_timeout,
						retry_interval, loglevel, config_tls);

				continue;
			}
			else if (ZBX_REDIRECT_RETRY == conn_ret)
			{
				zbx_vector_addr_ptr_t	addrs_tmp;

				zbx_tcp_close(&sock);

				zabbix_log(LOG_LEVEL_DEBUG, "%s() redirect connection without failover to: [%s]:%hu",
						__func__, addrs->values[0]->ip, addrs->values[0]->port);

				zbx_vector_addr_ptr_create(&addrs_tmp);
				zbx_vector_addr_ptr_append(&addrs_tmp, addrs->values[0]);

				/* one host in addrs list will try connection without failover logic */
				conn_ret = zbx_connect_to_server(&sock, source_ip, &addrs_tmp, timeout, connect_timeout,
						0, loglevel, config_tls);

				zbx_vector_addr_ptr_destroy(&addrs_tmp);

				continue;
			}
			else if (ZBX_REDIRECT_NONE == conn_ret)
			{
				/* redirect with revision older than used */
				if (NULL != error)
					*error = zbx_strdup(NULL,
						"connection was reset because of service being offline");
			}
		}

		break;
	}

	if (SUCCEED != ret)
		zbx_addrs_failover(addrs);
	else if (NULL != out)
		*out = zbx_socket_detach_buffer(&sock);

	zbx_tcp_close(&sock);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

char	*zbx_sanitize_proxyconfig_json(char *jbuffer)
{
	zbx_jsonobj_t	jobj;

	if (SUCCEED == zbx_jsonobj_open(jbuffer, &jobj))
	{
		char			*str = NULL;
		size_t			str_alloc = 0, str_offset = 0;
		zbx_jsonobj_t		*data_obj;

		zbx_jsonobj_remove_value(&jobj, "macro.secrets");

		if (NULL != (data_obj = zbx_jsonobj_get_value(&jobj, "data")))
		{
			zbx_jsonobj_remove_value(data_obj, "globalmacro");
			zbx_jsonobj_remove_value(data_obj, "hostmacro");
		}

		zbx_jsonobj_to_string(&str, &str_alloc, &str_offset, &jobj);
		zbx_jsonobj_clear(&jobj);

		return str;
	}

	return NULL;
}

static void	blake3_feed_json_tag_slice(zbx_blake3_hasher_t *hasher, const char *tag, const char *data, size_t len)
{
	zbx_uint64_t	len_le = zbx_htole_uint64((zbx_uint64_t)len);

	zbx_blake3_update(hasher, tag, strlen(tag) + 1);
	zbx_blake3_update(hasher, &len_le, sizeof(len_le));
	if (0 < len)
		zbx_blake3_update(hasher, data, len);
}

/******************************************************************************
 *                                                                            *
 * Purpose: computes canonical BLAKE3 digest over proxy data batch sections   *
 *                                                                            *
 * Return value: SUCCEED - batch contains data sections and hash was computed *
 *               FAIL    - batch has no data sections (e.g. empty poll)       *
 *                                                                            *
 ******************************************************************************/
int	zbx_proxy_batch_compute_blake3(const struct zbx_json_parse *jp, unsigned char digest_out[ZBX_BLAKE3_OUT_LEN],
		char hex_out[ZBX_BLAKE3_HEX_LEN])
{
	static const char	*batch_tags[] = {
		ZBX_PROTO_TAG_INTERFACE_AVAILABILITY,
		ZBX_PROTO_TAG_HISTORY_DATA,
		ZBX_PROTO_TAG_DISCOVERY_DATA,
		ZBX_PROTO_TAG_AUTOREGISTRATION,
		ZBX_PROTO_TAG_PROXY_ACTIVE_AVAIL_DATA,
		ZBX_PROTO_TAG_TASKS
	};

	zbx_blake3_hasher_t	hasher;
	char			session[MAX_STRING_LEN];
	int			has_data = 0;
	size_t			i;

	zbx_blake3_init(&hasher);

	if (SUCCEED == zbx_json_value_by_name(jp, ZBX_PROTO_TAG_SESSION, session, sizeof(session), NULL))
		blake3_feed_json_tag_slice(&hasher, ZBX_PROTO_TAG_SESSION, session, strlen(session));

	for (i = 0; i < ARRSIZE(batch_tags); i++)
	{
		struct zbx_json_parse	jp_sub;

		if (SUCCEED == zbx_json_brackets_by_name(jp, batch_tags[i], &jp_sub) && jp_sub.end >= jp_sub.start)
		{
			size_t	slice_len = (size_t)(jp_sub.end - jp_sub.start + 1);

			blake3_feed_json_tag_slice(&hasher, batch_tags[i], jp_sub.start, slice_len);
			has_data = 1;
		}
	}

	if (0 == has_data)
	{
		if (NULL != hex_out)
			hex_out[0] = '\0';
		return FAIL;
	}

	zbx_blake3_finalize(&hasher, digest_out, ZBX_BLAKE3_OUT_LEN);

	if (NULL != hex_out)
		zbx_blake3_buf2hex(digest_out, hex_out);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: extracts and verifies 'batch_blake3' tag against batch contents   *
 *                                                                            *
 ******************************************************************************/
int	zbx_proxy_batch_verify_blake3(const struct zbx_json_parse *jp, unsigned char digest_out[ZBX_BLAKE3_OUT_LEN],
		char hex_out[ZBX_BLAKE3_HEX_LEN], int *has_blake3, char **error)
{
	char		recv_hex[ZBX_BLAKE3_HEX_LEN + 8];
	unsigned char	recv_digest[ZBX_BLAKE3_OUT_LEN];

	*has_blake3 = 0;
	if (NULL != hex_out)
		hex_out[0] = '\0';

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_BATCH_BLAKE3, recv_hex, sizeof(recv_hex), NULL))
		return SUCCEED;

	if (SUCCEED != zbx_blake3_hex2buf(recv_hex, recv_digest))
	{
		*error = zbx_strdup(*error, "invalid batch_blake3 hexadecimal digest format");
		return FAIL;
	}

	if (SUCCEED != zbx_proxy_batch_compute_blake3(jp, digest_out, hex_out))
	{
		*error = zbx_strdup(*error, "batch_blake3 tag present on empty proxy batch");
		return FAIL;
	}

	if (0 != memcmp(recv_digest, digest_out, ZBX_BLAKE3_OUT_LEN))
	{
		*error = zbx_dsprintf(*error, "batch BLAKE3 coherence check failed (expected %s, computed %s)",
				recv_hex, hex_out);
		return FAIL;
	}

	*has_blake3 = 1;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: verifies echoed 'batch_blake3' in server ACK response             *
 *                                                                            *
 ******************************************************************************/
int	zbx_verify_batch_blake3_ack_ext(const char *response_buf, int peer_protocol, const char *expected_hex,
		char **error)
{
	struct zbx_json_parse	jp;
	char			ack_hex[ZBX_BLAKE3_HEX_LEN + 8];

	if (NULL == expected_hex || '\0' == *expected_hex)
		return SUCCEED;

	if (NULL == response_buf || '\0' == *response_buf || SUCCEED != zbx_json_open(response_buf, &jp))
	{
		*error = zbx_strdup(*error, "cannot parse server ACK JSON for BLAKE3 verification");
		return FAIL;
	}

	if (SUCCEED != zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_BATCH_BLAKE3, ack_hex, sizeof(ack_hex), NULL))
	{
		if (0 == (peer_protocol & ZBX_TCP_BLAKE3))
			return SUCCEED;

		*error = zbx_strdup(*error, "server ACK is missing batch_blake3 confirmation");
		return FAIL;
	}

	if (0 != strcmp(ack_hex, expected_hex))
	{
		*error = zbx_dsprintf(*error, "server ACK batch_blake3 mismatch (sent %s, received %s)",
				expected_hex, ack_hex);
		return FAIL;
	}

	return SUCCEED;
}

int	zbx_verify_batch_blake3_ack(const char *response_buf, const char *expected_hex, char **error)
{
	return zbx_verify_batch_blake3_ack_ext(response_buf, ZBX_TCP_BLAKE3, expected_hex, error);
}
