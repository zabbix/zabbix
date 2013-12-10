/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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
#include "log.h"
#include "tls.h"

#if defined(HAVE_OPENSSL)
#	include <openssl/ssl.h>
#elif defined(HAVE_GNUTLS)
#	include <gnutls/gnutls.h>
#endif

/******************************************************************************
 *                                                                            *
 * Function: zbx_tls_init                                                     *
 *                                                                            *
 * Purpose: initialize crypto library.                                        *
 *                                                                            *
 ******************************************************************************/
int	zbx_tls_init(void)
{
	const char	*__function_name = "zbx_tls_init";
	int		ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

#if defined(HAVE_OPENSSL)
	SSL_load_error_strings();
	SSL_library_init();
#elif defined(HAVE_GNUTLS)
	if (GNUTLS_E_SUCCESS != gnutls_global_init())
		ret = FAIL;
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tls_free                                                     *
 *                                                                            *
 * Purpose: release crypto library resources.                                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_tls_free(void)
{
	const char	*__function_name = "zbx_tls_free";
	int		ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

#if defined(HAVE_OPENSSL)
	ERR_free_strings();
#elif defined(HAVE_GNUTLS)
	gnutls_global_deinit();
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
	return ret;
}
