/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

#include "cfg.h"
#include "db.h"
#include "log.h"
#include "zbxjson.h"

#include "comms.h"
#include "servercomms.h"

extern unsigned int	configured_tls_connect_mode;

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
extern char	*CONFIG_TLS_SERVER_CERT_ISSUER;
extern char	*CONFIG_TLS_SERVER_CERT_SUBJECT;
extern char	*CONFIG_TLS_PSK_IDENTITY;
#endif

int	connect_to_server(zbx_socket_t *sock, int timeout, int retry_interval)
{
	int	res, lastlogtime, now;
	char	*tls_arg1 = NULL, *tls_arg2 = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In connect_to_server() [%s]:%d [timeout:%d]",
			CONFIG_SERVER, CONFIG_SERVER_PORT, timeout);

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	if (ZBX_TCP_SEC_TLS_CERT == configured_tls_connect_mode)
	{
		tls_arg1 = CONFIG_TLS_SERVER_CERT_ISSUER;
		tls_arg2 = CONFIG_TLS_SERVER_CERT_SUBJECT;
	}
	else if (ZBX_TCP_SEC_TLS_PSK == configured_tls_connect_mode)
	{
		tls_arg1 = CONFIG_TLS_PSK_IDENTITY;	/* zbx_tls_connect() will find PSK */
	}

	/* do nothing if ZBX_TCP_SEC_UNENCRYPTED == configured_tls_connect_mode */
#endif
	if (FAIL == (res = zbx_tcp_connect(sock, CONFIG_SOURCE_IP, CONFIG_SERVER, CONFIG_SERVER_PORT, timeout,
			configured_tls_connect_mode, tls_arg1, tls_arg2)))
	{
		if (0 == retry_interval)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Unable to connect to the server [%s]:%d [%s]",
					CONFIG_SERVER, CONFIG_SERVER_PORT, zbx_socket_strerror());
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "Unable to connect to the server [%s]:%d [%s]. Will retry every"
					" %d second(s)", CONFIG_SERVER, CONFIG_SERVER_PORT, zbx_socket_strerror(),
					retry_interval);

			lastlogtime = (int)time(NULL);

			while (FAIL == (res = zbx_tcp_connect(sock, CONFIG_SOURCE_IP, CONFIG_SERVER, CONFIG_SERVER_PORT,
					timeout, configured_tls_connect_mode, tls_arg1, tls_arg2)))
			{
				now = (int)time(NULL);

				if (60 <= now - lastlogtime)
				{
					zabbix_log(LOG_LEVEL_WARNING, "Still unable to connect...");
					lastlogtime = now;
				}

				sleep(retry_interval);
			}

			zabbix_log(LOG_LEVEL_WARNING, "Connection restored.");
		}
	}

	return res;
}

void	disconnect_server(zbx_socket_t *sock)
{
	zbx_tcp_close(sock);
}

/******************************************************************************
 *                                                                            *
 * Function: get_data_from_server                                             *
 *                                                                            *
 * Purpose: get configuration and other data from server                      *
 *                                                                            *
 * Return value: SUCCEED - processed successfully                             *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 ******************************************************************************/
int	get_data_from_server(zbx_socket_t *sock, const char *request, char **error)
{
	const char	*__function_name = "get_data_from_server";

	int		ret = FAIL;
	struct zbx_json	j;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() request:'%s'", __function_name, request);

	zbx_json_init(&j, 128);
	zbx_json_addstring(&j, "request", request, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(&j, "host", CONFIG_HOSTNAME, ZBX_JSON_TYPE_STRING);

	if (SUCCEED != zbx_tcp_send(sock, j.buffer))
	{
		*error = zbx_strdup(*error, zbx_socket_strerror());
		goto exit;
	}

	if (SUCCEED != zbx_tcp_recv(sock))
	{
		*error = zbx_strdup(*error, zbx_socket_strerror());
		goto exit;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "Received [%s] from server", sock->buffer);

	ret = SUCCEED;
exit:
	zbx_json_free(&j);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: put_data_to_server                                               *
 *                                                                            *
 * Purpose: send data to server                                               *
 *                                                                            *
 * Return value: SUCCEED - processed successfully                             *
 *               FAIL - an error occurred                                     *
 *                                                                            *
 ******************************************************************************/
int	put_data_to_server(zbx_socket_t *sock, struct zbx_json *j, char **error)
{
	const char	*__function_name = "put_data_to_server";

	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() datalen:" ZBX_FS_SIZE_T, __function_name, (zbx_fs_size_t)j->buffer_size);

	if (SUCCEED != zbx_tcp_send(sock, j->buffer))
	{
		*error = zbx_strdup(*error, zbx_socket_strerror());
		goto out;
	}

	if (SUCCEED != zbx_recv_response(sock, 0, error))
		goto out;

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
