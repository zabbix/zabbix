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

#include "zbxcommon.h"

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)

#include "zbxcomms.h"

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
