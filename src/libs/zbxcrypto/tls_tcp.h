/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

#ifndef ZABBIX_TLS_TCP_H
#define ZABBIX_TLS_TCP_H

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
int	zbx_tls_connect(zbx_socket_t *s, char **error, unsigned int tls_connect, char *tls_arg1, char *tls_arg2);
int	zbx_tls_accept(zbx_socket_t *s, char **error, unsigned int tls_accept);
void	zbx_tls_close(zbx_socket_t *s);
#endif

#if defined(HAVE_OPENSSL)
void	zbx_tls_error_msg(char **error, size_t *error_alloc, size_t *error_offset);
#endif

#endif	/* ZABBIX_TLS_TCP_H */
