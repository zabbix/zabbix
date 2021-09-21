/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

#include "log.h"

#include "comms.h"
#include "servercomms.h"
#include "daemon.h"

extern unsigned int	configured_tls_connect_mode;

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
extern char	*CONFIG_TLS_SERVER_CERT_ISSUER;
extern char	*CONFIG_TLS_SERVER_CERT_SUBJECT;
extern char	*CONFIG_TLS_PSK_IDENTITY;
#endif

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
int	get_data_from_server(zbx_socket_t *sock, char **buffer, size_t buffer_size, size_t reserved, char **error)
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

	zabbix_log(LOG_LEVEL_DEBUG, "Received [%s] from server", sock->buffer);

	ret = SUCCEED;
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

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
int	put_data_to_server(zbx_socket_t *sock, char **buffer, size_t buffer_size, size_t reserved, char **error)
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
