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

#ifndef ZABBIX_TLS_TCP_ACTIVE_H
#define ZABBIX_TLS_TCP_ACTIVE_H

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
typedef struct
{
	const char	*psk_identity;
	size_t		psk_identity_len;
	char		issuer[HOST_TLS_ISSUER_LEN_MAX];
	char		subject[HOST_TLS_SUBJECT_LEN_MAX];
}
zbx_tls_conn_attr_t;

int		zbx_tls_get_attr_cert(const zbx_socket_t *s, zbx_tls_conn_attr_t *attr);
int		zbx_tls_get_attr_psk(const zbx_socket_t *s, zbx_tls_conn_attr_t *attr);
int		zbx_check_server_issuer_subject(zbx_socket_t *sock, char **error);
#endif

#endif	/* ZABBIX_TLS_TCP_ACTIVE_H */
