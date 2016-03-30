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

#ifndef ZABBIX_TLS_H
#define ZABBIX_TLS_H

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)

#if defined(_WINDOWS)
/* Typical thread is long-running, if necessary, it initializes TLS for itself. Zabbix sender is an exception. If */
/* data is sent from a file or in real time then sender's 'main' thread starts the 'send_value' thread for each   */
/* 250 values to be sent. To avoid TLS initialization on every start of 'send_value' thread we initialize TLS in  */
/* 'main' thread and use this structure for passing minimum TLS variables into 'send_value' thread. */

#if defined(HAVE_POLARSSL)
#	if defined(_WINDOWS) && defined(uint32_t)
#		undef uint32_t
#	endif
#	include <polarssl/entropy.h>
#	include <polarssl/ctr_drbg.h>
#	include <polarssl/ssl.h>
#elif defined(HAVE_GNUTLS)
#	include <gnutls/gnutls.h>
#elif defined(HAVE_OPENSSL)
#	include <openssl/ssl.h>
#endif

typedef struct
{
#if defined(HAVE_POLARSSL)
	char			*my_psk;
	size_t			my_psk_len;
	char			*my_psk_identity;
	size_t			my_psk_identity_len;
	x509_crt		*ca_cert;
	x509_crl		*crl;
	x509_crt		*my_cert;
	pk_context		*my_priv_key;
	entropy_context		*entropy;
	ctr_drbg_context	*ctr_drbg;
	int			*ciphersuites_cert;
	int			*ciphersuites_psk;
#elif defined(HAVE_GNUTLS)
	gnutls_certificate_credentials_t	my_cert_creds;
	gnutls_psk_client_credentials_t		my_psk_client_creds;
	gnutls_priority_t			ciphersuites_cert;
	gnutls_priority_t			ciphersuites_psk;
#elif defined(HAVE_OPENSSL)
	SSL_CTX			*ctx_cert;
	SSL_CTX			*ctx_psk;
	char			*psk_identity_for_cb;
	size_t			psk_identity_len_for_cb;
	char			*psk_for_cb;
	size_t			psk_len_for_cb;
#endif
}
ZBX_THREAD_SENDVAL_TLS_ARGS;

void	zbx_tls_pass_vars(ZBX_THREAD_SENDVAL_TLS_ARGS *args);
void	zbx_tls_take_vars(ZBX_THREAD_SENDVAL_TLS_ARGS *args);
#endif	/* #if defined(_WINDOWS) */

void	zbx_tls_validate_config(void);
void	zbx_tls_library_deinit(void);
void	zbx_tls_init_parent(void);
void	zbx_tls_init_child(void);
void	zbx_tls_free(void);
void	zbx_tls_free_on_signal(void);

#endif	/* #if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL) */

#endif	/* ZABBIX_TLS_H */
