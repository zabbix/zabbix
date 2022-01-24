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

#include "common.h"

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)

#include "zbxcrypto.h"
#include "tls.h"

#if defined(HAVE_GNUTLS)
#	include <gnutls/gnutls.h>
#	include <gnutls/x509.h>
#elif defined(HAVE_OPENSSL)
#	include <openssl/ssl.h>
#	include <openssl/err.h>
#	include <openssl/rand.h>
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: print tls library version on stdout by application request with   *
 *          parameter '-V'                                                    *
 *                                                                            *
 ******************************************************************************/
void	zbx_tls_version(void)
{
#if defined(HAVE_GNUTLS)
	printf("Compiled with GnuTLS %s\nRunning with GnuTLS %s\n", GNUTLS_VERSION, gnutls_check_version(NULL));
#elif defined(HAVE_OPENSSL)
	printf("This product includes software developed by the OpenSSL Project\n"
			"for use in the OpenSSL Toolkit (http://www.openssl.org/).\n\n");
	printf("Compiled with %s\nRunning with %s\n", OPENSSL_VERSION_TEXT, OpenSSL_version(OPENSSL_VERSION));
#endif
}

#endif
