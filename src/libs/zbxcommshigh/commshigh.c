/*
** Copyright (C) 2001-2024 Zabbix SIA
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
				tls_arg1, tls_arg2)))
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
				int	now = (int)time(NULL);

				if (ZBX_LOG_ENTRY_INTERVAL_DELAY <= now - lastlogtime)
				{
					zabbix_log(LOG_LEVEL_WARNING, "Still unable to connect...");
					lastlogtime = now;
				}

				sleep((unsigned int)retry_interval);
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
int	zbx_get_data_from_server(zbx_socket_t *sock, char **buffer, size_t buffer_size, size_t reserved, char **error)
{
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != zbx_tcp_send_ext(sock, *buffer, buffer_size, reserved, ZBX_TCP_PROTOCOL | ZBX_TCP_COMPRESS, 0))
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

	zabbix_log(LOG_LEVEL_DEBUG, "Received [%s] from server", sock->buffer);

	ret = SUCCEED;
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: send data to server                                               *
 *                                                                            *
 * Return value: SUCCEED - processed successfully                             *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 ******************************************************************************/
int	zbx_put_data_to_server(zbx_socket_t *sock, char **buffer, size_t buffer_size, size_t reserved, char **error)
{
	int	ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() datalen:" ZBX_FS_SIZE_T, __func__, (zbx_fs_size_t)buffer_size);

	if (SUCCEED != zbx_tcp_send_ext(sock, *buffer, buffer_size, reserved, ZBX_TCP_PROTOCOL | ZBX_TCP_COMPRESS, 0))
	{
		*error = zbx_strdup(*error, zbx_socket_strerror());
		goto out;
	}

	zbx_free(*buffer);

	if (SUCCEED != zbx_recv_response(sock, 0, error))
		goto out;

	ret = SUCCEED;
out:
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
 *             retry - [OUT] ZBX_REDIRECT_RETRY - redirection data was        *
 *                          updated, connection must be retried               *
 *                                                                            *
 * Return value: SUCCEED - response has redirect information                  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: In the case of valid and fresh redirect information either the   *
 *           existing redirect address is updated and moved at the start of   *
 *           address list or a new address is created and inserted at the     *
 *           start of address list.                                           *
 *                                                                            *
 ******************************************************************************/
static int	comms_check_redirect(const char *data, zbx_vector_addr_ptr_t *addrs, int *retry)
{
	zbx_json_parse_t	jp;
	char			buf[MAX_STRING_LEN], *host = NULL;
	zbx_uint64_t		revision;
	int			i;
	zbx_addr_t		*addr;
	unsigned short		port;
	unsigned char		reset;

	if (FAIL == zbx_json_open(data, &jp))
		return FAIL;

	if (FAIL == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_RESPONSE, buf, sizeof(buf), NULL))
		return FAIL;

	if (0 != strcmp(buf, ZBX_PROTO_VALUE_FAILED))
		return FAIL;

	if (SUCCEED != zbx_parse_redirect_response(&jp, &host, &port, &revision, &reset))
		return FAIL;

	if (ZBX_REDIRECT_RESET == reset)
	{
		/* can't reset if the current address is not redirected */
		if (0 == addrs->values[0]->revision)
			return SUCCEED;

		/* move redirected address at the end of address list */
		zbx_vector_addr_ptr_append(addrs, addrs->values[0]);
		zbx_vector_addr_ptr_remove(addrs, 0);

		*retry = ZBX_REDIRECT_RETRY;
		return SUCCEED;
	}

	for (i = 0; i < addrs->values_num; i++)
	{
		if (0 != addrs->values[i]->revision)
			break;
	}

	if (i < addrs->values_num)
	{
		if (revision < addrs->values[i]->revision)
		{
			zbx_free(host);

			if (0 == i)
			{
				/* move redirected address at the end of address list */
				zbx_vector_addr_ptr_append(addrs, addrs->values[0]);
				zbx_vector_addr_ptr_remove(addrs, 0);
			}

			*retry = ZBX_REDIRECT_RETRY;
			return SUCCEED;
		}

		addr = addrs->values[i];
		zbx_vector_addr_ptr_remove(addrs, i);
		zbx_free(addr->ip);
	}
	else
	{
		addr = (zbx_addr_t *)zbx_malloc(NULL, sizeof(zbx_addr_t));
		addr->ip = NULL;
	}

	addr->ip = host;
	addr->revision = revision;
	addr->port = (0 == port ? ZBX_DEFAULT_SERVER_PORT : port);
	zbx_vector_addr_ptr_insert(addrs, addr, 0);

	*retry = ZBX_REDIRECT_RETRY;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: connect to a host and exchange data                               *
 *                                                                            *
 * Return value: SUCCEED - data was exchanged successfully                    *
 *               CONNECT_ERROR - connection error                             *
 *               SEND_ERROR - request sending error                           *
 *               READ_ERROR - response reading error                          *
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
	int			ret = FAIL, retries = 0, retry = ZBX_REDIRECT_NONE;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);
retry:
	if (SUCCEED != zbx_connect_to_server(&sock, source_ip, addrs, timeout, connect_timeout, retry_interval,
			loglevel, config_tls))
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

	zabbix_log(LOG_LEVEL_DEBUG, "%s() sending: %s", __func__, data);

	if (SUCCEED != zbx_tcp_send(&sock, data))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "unable to send to [%s]:%d: %s",
				addrs->values[0]->ip, addrs->values[0]->port, zbx_socket_strerror());
		if (NULL != error)
			*error = zbx_strdup(NULL, zbx_socket_strerror());
		ret = SEND_ERROR;

		goto cleanup;
	}

	if (SUCCEED != zbx_tcp_recv(&sock))
	{
		/* if no data is expected then recv failure means */
		/* the other side closed connection as expected   */
		if (NULL == out)
			goto success;

		zabbix_log(loglevel, "unable to receive from [%s]:%d: %s",
				addrs->values[0]->ip, addrs->values[0]->port, zbx_socket_strerror());
		if (NULL != error)
			*error = zbx_strdup(NULL, zbx_socket_strerror());
		ret = RECV_ERROR;

		goto cleanup;
	}
	else
	{
		if (ZBX_PROTO_ERROR == zbx_tcp_read_close_notify(&sock, 0, NULL))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot gracefully close connection: %s",
					zbx_socket_strerror());
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() received: %s", __func__, sock.buffer);

	if (SUCCEED == comms_check_redirect(sock.buffer, addrs, &retry))
	{
		if (0 == retries && ZBX_REDIRECT_RETRY == retry)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s() redirect response found, retrying to: [%s]:%hu", __func__,
					addrs->values[0]->ip, addrs->values[0]->port);
			retries++;
			zbx_tcp_close(&sock);

			goto retry;
		}

		if (NULL != error)
		{
			if (ZBX_REDIRECT_RETRY == retry)
				*error = zbx_strdup(NULL, "sequential redirect responses detected");
			else
				*error = zbx_strdup(NULL, "connection was reset because of service being offline");
		}

		goto cleanup;
	}

	if (NULL != out)
		*out = zbx_socket_detach_buffer(&sock);
success:
	ret = SUCCEED;
cleanup:
	if (SUCCEED != ret)
		zbx_addrs_failover(addrs);

	zbx_tcp_close(&sock);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
