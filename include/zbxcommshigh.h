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

#ifndef ZABBIX_COMMSHIGH_H
#define ZABBIX_COMMSHIGH_H

#include "zbxcomms.h"
#include "zbxcfg.h"
#include "zbxjson.h"

int	zbx_connect_to_server(zbx_socket_t *sock, const char *source_ip, zbx_vector_addr_ptr_t *addrs, int timeout,
		int connect_timeout, int retry_interval, int level, const zbx_config_tls_t *config_tls);
void	zbx_disconnect_from_server(zbx_socket_t *sock);

int	zbx_get_data_from_server(zbx_socket_t *sock, char **buffer, size_t buffer_size, size_t reserved, char **error);
int	zbx_put_data_to_server(zbx_socket_t *sock, char **buffer, size_t buffer_size, size_t reserved, char **error);

int	zbx_send_response_ext(zbx_socket_t *sock, int result, const char *info, const char *version, int protocol,
		int timeout);

int	zbx_send_response_json(zbx_socket_t *sock, int result, const char *info, const char *version, int protocol,
		int timeout, const char *ext);

#define zbx_send_response(sock, result, info, timeout) \
		zbx_send_response_ext(sock, result, info, NULL, ZBX_TCP_PROTOCOL, timeout)

#define zbx_send_response_same(sock, result, info, timeout) \
		zbx_send_response_ext(sock, result, info, NULL, sock->protocol, timeout)

#define zbx_send_proxy_response(sock, result, info, timeout) \
		zbx_send_response_ext(sock, result, info, ZABBIX_VERSION, ZBX_TCP_PROTOCOL | ZBX_TCP_COMPRESS, timeout)

int	zbx_recv_response(zbx_socket_t *sock, int timeout, char **error);

void	zbx_add_redirect_response(struct zbx_json *json, const zbx_comms_redirect_t *redirect);
int	zbx_parse_redirect_response(struct zbx_json_parse *jp, char **host, unsigned short *port,
		zbx_uint64_t *revision, unsigned char *reset);

int	zbx_comms_exchange_with_redirect(const char *source_ip, zbx_vector_addr_ptr_t *addrs, int timeout,
		int connect_timeout, int retry_interval, int loglevel, const zbx_config_tls_t *config_tls,
		const char *data, char *(*connect_callback)(void *), void *cb_data, char **out, char **error);

void	zbx_addrs_failover(zbx_vector_addr_ptr_t *addrs);

#endif // ZABBIX_COMMSHIGH_H
