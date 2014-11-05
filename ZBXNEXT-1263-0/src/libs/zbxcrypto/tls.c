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
#       include <polarssl/entropy.h>
#       include <polarssl/ctr_drbg.h>
#elif defined(HAVE_GNUTLS)
#	include <gnutls/gnutls.h>
#endif

#if defined(HAVE_POLARSSL)
	entropy_context		entropy;
	ctr_drbg_context	ctr_drbg;
#endif

#if defined(HAVE_POLARSSL)
/******************************************************************************
 *                                                                            *
 * Function: zbx_tls_make_personalization_string                              *
 *                                                                            *
 * Purpose: initialize crypto library.                                        *
 *                                                                            *
 * Comments:                                                                  *
 *     For more information about why and how to use personalization strings  *
 *     see                                                                    *
 *     https://polarssl.org/module-level-design-rng                           *
 *     http://csrc.nist.gov/publications/nistpubs/800-90A/SP800-90A.pdf       *
 *                                                                            *
 ******************************************************************************/
static char	*zbx_tls_make_personalization_string(void)
{
	char	*pers_str = NULL;

	/* TODO: follow recommendations in http://csrc.nist.gov/publications/nistpubs/800-90A/SP800-90A.pdf */
	/* and add more entropy into the personalization string (e.g. process PID, microseconds of current time etc.) */
	/* Pay attention to the personalization string length as described in SP800-90A.pdf */

	pers_str = zbx_strdup(pers_str, "Zabbix TLS"); /* For demo purposes only. TODO: replace with production code. */

	return pers_str;
}
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

#if defined(HAVE_POLARSSL)
	char	*pers = NULL;
#endif

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

#if defined(HAVE_POLARSSL)
	entropy_init(&entropy);

	pers = zbx_tls_make_personalization_string();

	if (0 != (ret = ctr_drbg_init(&ctr_drbg, entropy_func, &entropy, (unsigned char *)pers, strlen(pers))))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize random number generator: %d", ret);
		exit(EXIT_FAILURE);
	}
	zbx_free(pers);		/* TODO Can we free the personalization string immediately after instantiation ? */
#elif defined(HAVE_GNUTLS)
	if (GNUTLS_E_SUCCESS != gnutls_global_init())
		ret = FAIL;
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

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

#if defined(HAVE_POLARSSL)
	ctr_drbg_free(&ctr_drbg);
	entropy_free(&entropy);
#elif defined(HAVE_GNUTLS)
	gnutls_global_deinit();
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
