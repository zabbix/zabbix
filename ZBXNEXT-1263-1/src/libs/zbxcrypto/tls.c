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
#	include <polarssl/ssl.h>
#	include <polarssl/error.h>
#elif defined(HAVE_GNUTLS)
#	include <gnutls/gnutls.h>
#elif defined(HAVE_OPENSSL)
#	include <openssl/ssl.h>
#endif

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
extern char	*CONFIG_TLS_CA_FILE;
extern char	*CONFIG_TLS_CA_PATH;
extern char	*CONFIG_TLS_CERT_FILE;
extern char	*CONFIG_TLS_KEY_FILE;
extern char	*CONFIG_TLS_PSK_FILE;
extern char	*CONFIG_TLS_PSK_IDENTITY;
#endif

#if defined(HAVE_POLARSSL)
static x509_crt		*ca_cert		= NULL;
static x509_crt		*my_cert		= NULL;
static pk_context	*my_priv_key		= NULL;
static unsigned char	*my_psk			= NULL;
static size_t		my_psk_len		= 0;
static char		*my_psk_identity	= NULL;
static entropy_context	*entropy		= NULL;
static ctr_drbg_context	*ctr_drbg		= NULL;
static char		*err_msg		= NULL;
#endif

#if defined(HAVE_POLARSSL)
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

#if defined(HAVE_POLARSSL)
/******************************************************************************
 *                                                                            *
 * Function: polarssl_debug                                                   *
 *                                                                            *
 * Purpose: write a PolarSSL debug message into Zabbix log                    *
 *                                                                            *
 * Comments:                                                                  *
 *     Parameter 'tls_ctx' is not used but cannot be removed because this is  *
 *     a callback function, its arguments are defined in PolarSSL.            *
 ******************************************************************************/
static void	polarssl_debug(ssl_context *tls_ctx, int level, const char *str)
{
	char	msg[1024];	/* Apparently 1024 bytes is the longest message which can come from PolarSSL 1.3.9 */

	/* TODO code can be improved by allocating a dynamic buffer if message is longer than 1024 bytes */
	/* remove '\n' from the end of debug message */
	zbx_strlcpy(msg, str, sizeof(msg));
	zbx_rtrim(msg, "\n");
	zabbix_log(LOG_LEVEL_DEBUG, "PolarSSL debug: level=%d \"%s\"", level, msg);
}
#endif

#if defined(HAVE_POLARSSL)
/******************************************************************************
 *                                                                            *
 * Function: zbx_tls_error_msg                                                *
 *                                                                            *
 * Purpose: compose a TLS error message                                       *
 *                                                                            *
 ******************************************************************************/
static void	zbx_tls_error_msg(int error_code, const char *msg, char **error)
{
	char	err[128];	/* 128 bytes are enough for PolarSSL error messages */

	polarssl_strerror(error_code, err, sizeof(err));
	*error = zbx_dsprintf(*error, "%s%s", msg, err);
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
	int		ret = SUCCEED, res;

#if defined(HAVE_POLARSSL)
	char	*pers = NULL;
	size_t	pers_len = 0;
#endif

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);
#if defined(HAVE_POLARSSL)
	/* try to configure the CA path first, it overrides the CA file parameter */
	if (NULL != CONFIG_TLS_CA_PATH && '\0' != *CONFIG_TLS_CA_PATH)
	{
		ca_cert = zbx_malloc(ca_cert, sizeof(x509_crt));
		x509_crt_init(ca_cert);

		if (0 != (res = x509_crt_parse_path(ca_cert, CONFIG_TLS_CA_PATH)))
		{
			if (0 > res)
			{
				zbx_tls_error_msg(res, "", &err_msg);
				zabbix_log(LOG_LEVEL_CRIT, "cannot parse CA certificate(s) in directory \"%s\": %s",
						CONFIG_TLS_CA_PATH, err_msg);
				zbx_free(err_msg);
			}
			else
			{
				zabbix_log(LOG_LEVEL_CRIT, "cannot parse %d CA certificate(s) in directory \"%s\"", res,
						CONFIG_TLS_CA_PATH);
			}

			zbx_tls_free();
			exit(EXIT_FAILURE);
		}

		zabbix_log(LOG_LEVEL_DEBUG, "%s(): successfully loaded CA certificate(s) from directory",
				__function_name);
	}

	/* load CA file if CA path is not configured */
	if (NULL == ca_cert && NULL != CONFIG_TLS_CA_FILE && '\0' != *CONFIG_TLS_CA_FILE)
	{
		ca_cert = zbx_malloc(ca_cert, sizeof(x509_crt));
		x509_crt_init(ca_cert);

		if (0 != (res = x509_crt_parse_file(ca_cert, CONFIG_TLS_CA_FILE)))
		{
			if (0 > res)
			{
				zbx_tls_error_msg(res, "", &err_msg);
				zabbix_log(LOG_LEVEL_CRIT, "cannot parse CA certificate(s) in file \"%s\": %s",
						CONFIG_TLS_CA_FILE, err_msg);
				zbx_free(err_msg);
			}
			else
			{
				zabbix_log(LOG_LEVEL_CRIT, "cannot parse %d CA certificate(s) in file \"%s\"", res,
						CONFIG_TLS_CA_FILE);
			}

			zbx_tls_free();
			exit(EXIT_FAILURE);
		}

		zabbix_log(LOG_LEVEL_DEBUG, "%s(): successfully loaded CA certificate(s) from file", __function_name);
	}

	/* load certificate */
	if (NULL != CONFIG_TLS_CERT_FILE && '\0' != *CONFIG_TLS_CERT_FILE)
	{
		my_cert = zbx_malloc(my_cert, sizeof(x509_crt));
		x509_crt_init(my_cert);

		if (0 != (res = x509_crt_parse_file(my_cert, CONFIG_TLS_CERT_FILE)))
		{
			if (0 > res)
			{
				zbx_tls_error_msg(res, "", &err_msg);
				zabbix_log(LOG_LEVEL_CRIT, "cannot parse certificate(s) in file \"%s\": %s",
						CONFIG_TLS_CERT_FILE, err_msg);
				zbx_free(err_msg);
			}
			else
			{
				zabbix_log(LOG_LEVEL_CRIT, "cannot parse %d certificate(s) in file \"%s\"", res,
						CONFIG_TLS_CERT_FILE);
			}

			zbx_tls_free();
			exit(EXIT_FAILURE);
		}

		zabbix_log(LOG_LEVEL_DEBUG, "%s(): successfully loaded certificate", __function_name);
	}

	/* load private key */
	if (NULL != CONFIG_TLS_KEY_FILE && '\0' != *CONFIG_TLS_KEY_FILE)
	{
		my_priv_key = zbx_malloc(my_priv_key, sizeof(pk_context));
		pk_init(my_priv_key);

		/* The 3rd argument of pk_parse_keyfile() is password for decrypting the private key. */
		/* Currently the password is not used, it is empty. */
		if (0 > (res = pk_parse_keyfile(my_priv_key, CONFIG_TLS_KEY_FILE, "")))
		{
			zbx_tls_error_msg(res, "", &err_msg);
			zabbix_log(LOG_LEVEL_CRIT, "cannot parse the private key in file \"%s\": %s",
					CONFIG_TLS_KEY_FILE, err_msg);
			zbx_free(err_msg);
			zbx_tls_free();
			exit(EXIT_FAILURE);
		}

		zabbix_log(LOG_LEVEL_DEBUG, "%s(): successfully loaded private key", __function_name);
	}

	/* load pre-shared key */
	if (NULL != CONFIG_TLS_PSK_FILE && '\0' != *CONFIG_TLS_PSK_FILE)
	{
		int	fd;
		ssize_t	nbytes;

		my_psk = zbx_malloc(my_psk, POLARSSL_PSK_MAX_LEN);

		if (-1 == (fd = zbx_open(CONFIG_TLS_PSK_FILE, O_RDONLY)))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot open file \"%s\": %s", CONFIG_TLS_PSK_FILE,
					zbx_strerror(errno));
			zbx_tls_free();
			exit(EXIT_FAILURE);
		}

		nbytes = read(fd, my_psk, (size_t)POLARSSL_PSK_MAX_LEN);

		if (-1 == nbytes)
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot read from file \"%s\": %s", CONFIG_TLS_PSK_FILE,
					zbx_strerror(errno));
			zbx_tls_free();
			exit(EXIT_FAILURE);
		}

		if (0 == nbytes)
		{
			zabbix_log(LOG_LEVEL_CRIT, "file \"%s\" is empty", CONFIG_TLS_PSK_FILE);
			zbx_tls_free();
			exit(EXIT_FAILURE);
		}

		my_psk_len = (size_t)nbytes;

		if (0 != close(fd))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot close file \"%s\": %s", CONFIG_TLS_PSK_FILE,
					zbx_strerror(errno));
			zbx_tls_free();
			exit(EXIT_FAILURE);
		}

		zabbix_log(LOG_LEVEL_DEBUG, "%s(): successfully loaded pre-shared key", __function_name);

		/* configure identity to be used with the pre-shared key */
		if (NULL != CONFIG_TLS_PSK_IDENTITY && '\0' != *CONFIG_TLS_PSK_IDENTITY)
		{
			/* TODO implement validation for PSK identity */
			my_psk_identity = CONFIG_TLS_PSK_IDENTITY;
		}
	}

	entropy = zbx_malloc(entropy, sizeof(entropy_context));
	entropy_init(entropy);
	zbx_tls_make_personalization_string(&pers, &pers_len);

	ctr_drbg = zbx_malloc(ctr_drbg, sizeof(ctr_drbg_context));
	if (0 != (res = ctr_drbg_init(ctr_drbg, entropy_func, entropy, (unsigned char *)pers, pers_len)))
	{
		zbx_tls_error_msg(res, "", &err_msg);
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize random number generator: %s", err_msg);
		zbx_free(err_msg);
		zbx_tls_free();
		exit(EXIT_FAILURE);
	}

	zbx_guaranteed_memset(pers, 0, pers_len);
	zbx_free(pers);

	/* TODO validate configuration parameters (allowed combinations etc.) */

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
	if (NULL != ctr_drbg)
	{
		ctr_drbg_free(ctr_drbg);
		zbx_free(ctr_drbg);
	}

	if (NULL != entropy)
	{
		entropy_free(entropy);
		zbx_free(entropy);
	}

	if (NULL != my_psk)
	{
		zbx_guaranteed_memset(my_psk, 0, my_psk_len);
		my_psk_len = 0;
		zbx_free(my_psk);
	}

	if (NULL != my_priv_key)
	{
		pk_free(my_priv_key);
		zbx_free(my_priv_key);
	}

	if (NULL != my_cert)
	{
		x509_crt_free(my_cert);
		zbx_free(my_cert);
	}

	if (NULL != ca_cert)
	{
		x509_crt_free(ca_cert);
		zbx_free(ca_cert);
	}
#elif defined(HAVE_GNUTLS)
	gnutls_global_deinit();
#elif defined(HAVE_OPENSSL)
	/* TODO there is no ERR_free_strings() in my libssl. Commented out temporarily. */
	/* ERR_free_strings(); */
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
