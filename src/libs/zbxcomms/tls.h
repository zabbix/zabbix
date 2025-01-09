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

#ifndef ZABBIX_TLS_H
#define ZABBIX_TLS_H

#include "zbxcomms.h"

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
int	zbx_tls_connect(zbx_socket_t *s, unsigned int tls_connect, const char *tls_arg1, const char *tls_arg2,
		const char *server_name, short *event, char **error);
int	zbx_tls_accept(zbx_socket_t *s, unsigned int tls_accept, char **error);
ssize_t	zbx_tls_write(zbx_socket_t *s, const char *buf, size_t len, short *event, char **error);
ssize_t	zbx_tls_read(zbx_socket_t *s, char *buf, size_t len, short *events, char **error);
void	zbx_tls_close(zbx_socket_t *s);

void	zbx_read_psk_file(const char *file_name, char **psk, size_t *psk_len);
void	zbx_check_psk_identity_len(size_t psk_identity_len);
void	zbx_psk_warn_misconfig(const char *psk_identity);

#endif	/* #if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL) */

#endif /* ZABBIX_TLS_H */
