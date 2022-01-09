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

#ifndef ZABBIX_ZBXCRYPTO_H
#define ZABBIX_ZBXCRYPTO_H

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)

#if defined(_WINDOWS)

typedef struct zbx_thread_sendval_tls_args ZBX_THREAD_SENDVAL_TLS_ARGS;

void	zbx_tls_pass_vars(ZBX_THREAD_SENDVAL_TLS_ARGS *args);
void	zbx_tls_take_vars(ZBX_THREAD_SENDVAL_TLS_ARGS *args);

#endif	/* #if defined(_WINDOWS) */

void	zbx_tls_validate_config(void);
void	zbx_tls_library_deinit(void);
void	zbx_tls_init_parent(void);
void	zbx_tls_init_child(void);
void	zbx_tls_free(void);
void	zbx_tls_free_on_signal(void);
void	zbx_tls_version(void);

#endif	/* #if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL) */

#endif /* ZABBIX_DISK_H */
