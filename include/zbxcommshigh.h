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

#ifndef ZABBIX_COMMSHIGH_H
#define ZABBIX_COMMSHIGH_H

#include "zbxcomms.h"

int	zbx_connect_to_server(zbx_socket_t *sock, const char *source_ip, zbx_vector_ptr_t *addrs, int timeout,
		int connect_timeout, unsigned int tls_connect, int retry_interval, int level);
void	zbx_disconnect_from_server(zbx_socket_t *sock);

int	zbx_get_data_from_server(zbx_socket_t *sock, char **buffer, size_t buffer_size, size_t reserved, char **error);
int	zbx_put_data_to_server(zbx_socket_t *sock, char **buffer, size_t buffer_size, size_t reserved, char **error);

int	zbx_send_response_ext(zbx_socket_t *sock, int result, const char *info, const char *version, int protocol,
		int timeout);

#define zbx_send_response(sock, result, info, timeout) \
		zbx_send_response_ext(sock, result, info, NULL, ZBX_TCP_PROTOCOL, timeout)

#define zbx_send_response_same(sock, result, info, timeout) \
		zbx_send_response_ext(sock, result, info, NULL, sock->protocol, timeout)

#define zbx_send_proxy_response(sock, result, info, timeout) \
		zbx_send_response_ext(sock, result, info, ZABBIX_VERSION, ZBX_TCP_PROTOCOL | ZBX_TCP_COMPRESS, timeout)

int	zbx_recv_response(zbx_socket_t *sock, int timeout, char **error);

#endif // ZABBIX_COMMSHIGH_H
