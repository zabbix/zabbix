/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#if defined(HAVE_POLARSSL)
#	include <polarssl/entropy.h>
#	include <polarssl/ctr_drbg.h>
#elif defined(HAVE_GNUTLS)
#	include <gnutls/gnutls.h>
#elif defined(HAVE_OPENSSL)
#	include <openssl/ssl.h>
#endif

#if defined(HAVE_POLARSSL)
static entropy_context	entropy;
static ctr_drbg_context	ctr_drbg;

/******************************************************************************
 *                                                                            *
 * Function: zbx_tls_make_personalization_string                              *
 *                                                                            *
 * Purpose: provide additional entropy for initialization of crypto library   *
 *                                                                            *
 * Comments:                                                                  *
 *     For more information about why and how to use personalization strings  *
 *     see                                                                    *
 *     https://polarssl.org/module-level-design-rng                           *
 *     http://csrc.nist.gov/publications/nistpubs/800-90A/SP800-90A.pdf       *
 *                                                                            *
 ******************************************************************************/
static void	zbx_tls_make_personalization_string(char **pers, size_t *len)
{
	/* TODO: follow recommendations in http://csrc.nist.gov/publications/nistpubs/800-90A/SP800-90A.pdf */
	/* and add more entropy into the personalization string (e.g. process PID, microseconds of current time etc.) */
	/* Pay attention to the personalization string length as described in SP800-90A.pdf */

	/* For demo purposes only. TODO: replace with production code. */
#define DEMO_PERS_STRING	"Zabbix TLSZabbix TLSZabbix TLSZabbix TLSZabbix TLS"
	*pers = zbx_strdup(*pers, DEMO_PERS_STRING);
	*len = strlen(DEMO_PERS_STRING);
}
#endif

/******************************************************************************
 *                                                                            *
 * Function: zbx_tls_init_parent                                              *
 *                                                                            *
 * Purpose: initialize TLS library in a parent process                        *
 *                                                                            *
 ******************************************************************************/
int	zbx_tls_init_parent(void)
{
	const char	*__function_name = "zbx_tls_init_parent";
	int		ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);
	/* TODO fill in implementation */
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tls_init_child                                               *
 *                                                                            *
 * Purpose: initialize TLS library in a child process                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_tls_init_child(void)
{
	const char	*__function_name = "zbx_tls_init_child";
	int		ret = SUCCEED;

#if defined(HAVE_POLARSSL)
	char	*pers = NULL;
	size_t	pers_len = 0;
#endif

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

#if defined(HAVE_POLARSSL)
	entropy_init(&entropy);
	zbx_tls_make_personalization_string(&pers, &pers_len);

	if (0 != (ret = ctr_drbg_init(&ctr_drbg, entropy_func, &entropy, (unsigned char *)pers, pers_len)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize random number generator: %d", ret);
		exit(EXIT_FAILURE);
	}

	zbx_guaranteed_memset(pers, 0, pers_len);
	zbx_free(pers);
#elif defined(HAVE_GNUTLS)
	if (GNUTLS_E_SUCCESS != gnutls_global_init())
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize GnuTLS library");
		exit(EXIT_FAILURE);
	}
#elif defined(HAVE_OPENSSL)
	SSL_load_error_strings();
	SSL_library_init();
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tls_free                                                     *
 *                                                                            *
 * Purpose: release TLS library resources allocated in zbx_tls_init_parent()  *
 *          and zbx_tls_init_child()                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_tls_free(void)
{
	const char	*__function_name = "zbx_tls_free";
	int		ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

#if defined(HAVE_POLARSSL)
	ctr_drbg_free(&ctr_drbg);
	entropy_free(&entropy);
#elif defined(HAVE_GNUTLS)
	gnutls_global_deinit();
#elif defined(HAVE_OPENSSL)
	/* TODO there is no ERR_free_strings() in my libssl. Commented out temporarily. */
	/* ERR_free_strings(); */
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
