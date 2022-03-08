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

#ifndef ZABBIX_TLS_H
#define ZABBIX_TLS_H

#include "config.h"

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)

#if defined(HAVE_GNUTLS)
#	include <gnutls/gnutls.h>
#	include <gnutls/x509.h>
#elif defined(HAVE_OPENSSL)
#	include <openssl/ssl.h>
#	include <openssl/err.h>
#	include <openssl/rand.h>
#endif

#if defined(HAVE_OPENSSL) && OPENSSL_VERSION_NUMBER < 0x1010000fL || defined(LIBRESSL_VERSION_NUMBER)
#	if !defined(LIBRESSL_VERSION_NUMBER)
#		define OPENSSL_INIT_LOAD_SSL_STRINGS			0
#		define OPENSSL_INIT_LOAD_CRYPTO_STRINGS		0
#		define OPENSSL_VERSION					SSLEAY_VERSION
#	endif
#	define OpenSSL_version					SSLeay_version
#	define TLS_method					TLSv1_2_method
#	define TLS_client_method				TLSv1_2_client_method
#	define SSL_CTX_get_ciphers(ciphers)			((ciphers)->cipher_list)
#	if !defined(LIBRESSL_VERSION_NUMBER)
#		define SSL_CTX_set_min_proto_version(ctx, TLSv)	1
#	endif
#endif

#if defined(_WINDOWS)
/* Typical thread is long-running, if necessary, it initializes TLS for itself. Zabbix sender is an exception. If */
/* data is sent from a file or in real time then sender's 'main' thread starts the 'send_value' thread for each   */
/* 250 values to be sent. To avoid TLS initialization on every start of 'send_value' thread we initialize TLS in  */
/* 'main' thread and use this structure for passing minimum TLS variables into 'send_value' thread. */

struct zbx_thread_sendval_tls_args
{
#if defined(HAVE_GNUTLS)
	gnutls_certificate_credentials_t	my_cert_creds;
	gnutls_psk_client_credentials_t		my_psk_client_creds;
	gnutls_priority_t			ciphersuites_cert;
	gnutls_priority_t			ciphersuites_psk;
#elif defined(HAVE_OPENSSL)
	SSL_CTX			*ctx_cert;
#ifdef HAVE_OPENSSL_WITH_PSK
	SSL_CTX			*ctx_psk;
	const char		*psk_identity_for_cb;
	size_t			psk_identity_len_for_cb;
	char			*psk_for_cb;
	size_t			psk_len_for_cb;
#endif
#endif
};

#endif	/* #if defined(_WINDOWS) */

#endif	/* #if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL) */

#endif	/* ZABBIX_TLS_H */
