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

#include "zbxsysinc.h"

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)

#include "tls.h"
#include "zbxcomms.h"
#include "zbxthreads.h"
#include "log.h"
#include "zbxcrypto.h"
#include "tls_tcp.h"
#include "tls_tcp_active.h"

#if defined(HAVE_OPENSSL) && OPENSSL_VERSION_NUMBER < 0x1010000fL || defined(LIBRESSL_VERSION_NUMBER)
/* for OpenSSL 1.0.1/1.0.2 (before 1.1.0) or LibreSSL */

/* mutexes for multi-threaded OpenSSL (see "man 3ssl threads" and example in crypto/threads/mttest.c) */

#ifdef _WINDOWS
#include "zbxmutexs.h"

static zbx_mutex_t	*crypto_mutexes = NULL;

static void	zbx_openssl_locking_cb(int mode, int n, const char *file, int line)
{
	if (0 != (mode & CRYPTO_LOCK))
		__zbx_mutex_lock(file, line, *(crypto_mutexes + n));
	else
		__zbx_mutex_unlock(file, line, *(crypto_mutexes + n));
}

static void	zbx_openssl_thread_setup(void)
{
	int	i, num_locks;

	num_locks = CRYPTO_num_locks();

	if (NULL == (crypto_mutexes = zbx_malloc(crypto_mutexes, num_locks * sizeof(zbx_mutex_t))))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot allocate mutexes for OpenSSL library");
		exit(EXIT_FAILURE);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() creating %d mutexes", __func__, num_locks);

	for (i = 0; i < num_locks; i++)
	{
		char	*error = NULL;

		if (SUCCEED != zbx_mutex_create(crypto_mutexes + i, NULL, &error))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot create mutex #%d for OpenSSL library: %s", i, error);
			zbx_free(error);
			exit(EXIT_FAILURE);
		}
	}

	CRYPTO_set_locking_callback((void (*)(int, int, const char *, int))zbx_openssl_locking_cb);

	/* do not register our own threadid_func() callback, use OpenSSL default one */
}

static void	zbx_openssl_thread_cleanup(void)
{
	int	i, num_locks;

	CRYPTO_set_locking_callback(NULL);

	num_locks = CRYPTO_num_locks();

	for (i = 0; i < num_locks; i++)
		zbx_mutex_destroy(crypto_mutexes + i);

	zbx_free(crypto_mutexes);
}
#endif	/* _WINDOWS */

static int	zbx_openssl_init_ssl(int opts, void *settings)
{
#if defined(HAVE_OPENSSL) && OPENSSL_VERSION_NUMBER < 0x1010000fL
	ZBX_UNUSED(opts);
	ZBX_UNUSED(settings);

	SSL_load_error_strings();
	ERR_load_BIO_strings();
	SSL_library_init();
#endif
#ifdef _WINDOWS
	ZBX_UNUSED(opts);
	ZBX_UNUSED(settings);
	zbx_openssl_thread_setup();
#endif
	return 1;
}

static void	OPENSSL_cleanup(void)
{
	RAND_cleanup();
	ERR_free_strings();
#ifdef _WINDOWS
	zbx_openssl_thread_cleanup();
#endif
}
#endif	/* defined(HAVE_OPENSSL) && OPENSSL_VERSION_NUMBER < 0x1010000fL || defined(LIBRESSL_VERSION_NUMBER) */

#if defined(HAVE_OPENSSL) && OPENSSL_VERSION_NUMBER >= 0x1010000fL && !defined(LIBRESSL_VERSION_NUMBER)
/* OpenSSL 1.1.0 or newer, not LibreSSL */
static int	zbx_openssl_init_ssl(int opts, void *settings)
{
	return OPENSSL_init_ssl(opts, settings);
}
#endif

struct zbx_tls_context
{
#if defined(HAVE_GNUTLS)
	gnutls_session_t		ctx;
	gnutls_psk_client_credentials_t	psk_client_creds;
	gnutls_psk_server_credentials_t	psk_server_creds;
#elif defined(HAVE_OPENSSL)
	SSL				*ctx;
#endif
};

extern unsigned int			configured_tls_connect_mode;
extern unsigned int			configured_tls_accept_modes;

extern unsigned char			program_type;

extern int				CONFIG_PASSIVE_FORKS;
extern int				CONFIG_ACTIVE_FORKS;

extern char				*CONFIG_TLS_CONNECT;
extern char				*CONFIG_TLS_ACCEPT;
extern char				*CONFIG_TLS_CA_FILE;
extern char				*CONFIG_TLS_CRL_FILE;
extern char				*CONFIG_TLS_SERVER_CERT_ISSUER;
extern char				*CONFIG_TLS_SERVER_CERT_SUBJECT;
extern char				*CONFIG_TLS_CERT_FILE;
extern char				*CONFIG_TLS_KEY_FILE;
extern char				*CONFIG_TLS_PSK_IDENTITY;
extern char				*CONFIG_TLS_PSK_FILE;

extern char	*CONFIG_TLS_CIPHER_CERT13;	/* parameter 'TLSCipherCert13' from server/proxy/agent config file */
extern char	*CONFIG_TLS_CIPHER_CERT;	/* parameter 'TLSCipherCert' from server/proxy/agent config file */
extern char	*CONFIG_TLS_CIPHER_PSK13;	/* parameter 'TLSCipherPSK13' from server/proxy/agent config file */
extern char	*CONFIG_TLS_CIPHER_PSK;		/* parameter 'TLSCipherPSK' from server/proxy/agent config file */
extern char	*CONFIG_TLS_CIPHER_ALL13;	/* parameter 'TLSCipherAll13' from server/proxy/agent config file */
extern char	*CONFIG_TLS_CIPHER_ALL;		/* parameter 'TLSCipherAll' from server/proxy/agent config file */
extern char	*CONFIG_TLS_CIPHER_CMD13;	/* parameter '--tls-cipher13' from sender or zabbix_get command line */
extern char	*CONFIG_TLS_CIPHER_CMD;		/* parameter '--tls-cipher' from sender or zabbix_get command line */

static ZBX_THREAD_LOCAL char		*my_psk_identity	= NULL;
static ZBX_THREAD_LOCAL size_t		my_psk_identity_len	= 0;
static ZBX_THREAD_LOCAL char		*my_psk			= NULL;
static ZBX_THREAD_LOCAL size_t		my_psk_len		= 0;

/* Pointer to DCget_psk_by_identity() initialized at runtime. This is a workaround for linking. */
/* Server and proxy link with src/libs/zbxdbcache/dbconfig.o where DCget_psk_by_identity() resides */
/* but other components (e.g. agent) do not link dbconfig.o. */
size_t	(*find_psk_in_cache)(const unsigned char *, unsigned char *, unsigned int *) = NULL;

/* variable for passing information from callback functions if PSK was found among host PSKs or autoregistration PSK */
static unsigned int	psk_usage;

#if defined(HAVE_GNUTLS)
static ZBX_THREAD_LOCAL gnutls_certificate_credentials_t	my_cert_creds		= NULL;
static ZBX_THREAD_LOCAL gnutls_psk_client_credentials_t		my_psk_client_creds	= NULL;
static ZBX_THREAD_LOCAL gnutls_psk_server_credentials_t		my_psk_server_creds	= NULL;
static ZBX_THREAD_LOCAL gnutls_priority_t			ciphersuites_cert	= NULL;
static ZBX_THREAD_LOCAL gnutls_priority_t			ciphersuites_psk	= NULL;
static ZBX_THREAD_LOCAL gnutls_priority_t			ciphersuites_all	= NULL;
static int							init_done 		= 0;
#elif defined(HAVE_OPENSSL)
static ZBX_THREAD_LOCAL const SSL_METHOD	*method			= NULL;
static ZBX_THREAD_LOCAL SSL_CTX			*ctx_cert		= NULL;
#ifdef HAVE_OPENSSL_WITH_PSK
static ZBX_THREAD_LOCAL SSL_CTX			*ctx_psk		= NULL;
static ZBX_THREAD_LOCAL SSL_CTX			*ctx_all		= NULL;
/* variables for passing required PSK identity and PSK info to client callback function */
static ZBX_THREAD_LOCAL const char		*psk_identity_for_cb	= NULL;
static ZBX_THREAD_LOCAL size_t			psk_identity_len_for_cb	= 0;
static ZBX_THREAD_LOCAL char			*psk_for_cb		= NULL;
static ZBX_THREAD_LOCAL size_t			psk_len_for_cb		= 0;
#endif
static int					init_done 		= 0;
#ifdef HAVE_OPENSSL_WITH_PSK
/* variables for capturing PSK identity from server callback function */
static ZBX_THREAD_LOCAL int			incoming_connection_has_psk = 0;
static ZBX_THREAD_LOCAL char			incoming_connection_psk_id[PSK_MAX_IDENTITY_LEN + 1];
#endif
/* buffer for messages produced by zbx_openssl_info_cb() */
ZBX_THREAD_LOCAL char				info_buf[256];
#endif

#if defined(HAVE_GNUTLS)
/******************************************************************************
 *                                                                            *
 * Purpose: write a GnuTLS debug message into Zabbix log                      *
 *                                                                            *
 * Comments:                                                                  *
 *     This is a callback function, its arguments are defined in GnuTLS.      *
 *                                                                            *
 ******************************************************************************/
static void	zbx_gnutls_debug_cb(int level, const char *str)
{
	char	msg[1024];

	/* remove '\n' from the end of debug message */
	zbx_strlcpy(msg, str, sizeof(msg));
	zbx_rtrim(msg, "\n");
	zabbix_log(LOG_LEVEL_TRACE, "GnuTLS debug: level=%d \"%s\"", level, msg);
}

/******************************************************************************
 *                                                                            *
 * Purpose: write a GnuTLS audit message into Zabbix log                      *
 *                                                                            *
 * Comments:                                                                  *
 *     This is a callback function, its arguments are defined in GnuTLS.      *
 *                                                                            *
 ******************************************************************************/
static void	zbx_gnutls_audit_cb(gnutls_session_t session, const char *str)
{
	char	msg[1024];

	ZBX_UNUSED(session);

	/* remove '\n' from the end of debug message */
	zbx_strlcpy(msg, str, sizeof(msg));
	zbx_rtrim(msg, "\n");

	zabbix_log(LOG_LEVEL_WARNING, "GnuTLS audit: \"%s\"", msg);
}
#endif	/* defined(HAVE_GNUTLS) */

#if defined(HAVE_OPENSSL)
/******************************************************************************
 *                                                                            *
 * Purpose: get state, alert, error information on TLS connection             *
 *                                                                            *
 * Comments:                                                                  *
 *     This is a callback function, its arguments are defined in OpenSSL.     *
 *                                                                            *
 ******************************************************************************/
static void	zbx_openssl_info_cb(const SSL *ssl, int where, int ret)
{
	/* There was an idea of using SSL_CB_LOOP and SSL_state_string_long() to write state changes into Zabbix log */
	/* if logging level is LOG_LEVEL_TRACE. Unfortunately if OpenSSL for security is compiled without SSLv3 */
	/* (i.e. OPENSSL_NO_SSL3 is defined) then SSL_state_string_long() does not provide descriptions of many */
	/* states anymore. The idea was dropped but the code is here for debugging hard problems. */
#if 0
	if (0 != (where & SSL_CB_LOOP))
	{
		zabbix_log(LOG_LEVEL_TRACE, "OpenSSL debug: state=0x%x \"%s\"", (unsigned int)SSL_state(ssl),
				SSL_state_string_long(ssl));
	}
#else
	ZBX_UNUSED(ssl);
#endif
	if (0 != (where & SSL_CB_ALERT))	/* alert sent or received */
	{
		const char	*handshake, *direction, *rw;

		if (0 != (where & SSL_CB_EXIT))
			handshake = " handshake";
		else
			handshake = "";

		if (0 != (where & SSL_ST_CONNECT))
			direction = " connect";
		else if (0 != (where & SSL_ST_ACCEPT))
			direction = " accept";
		else
			direction = "";

		if (0 != (where & SSL_CB_READ))
			rw = " read";
		else if (0 != (where & SSL_CB_WRITE))
			rw = " write";
		else
			rw = "";

		zbx_snprintf(info_buf, sizeof(info_buf), ": TLS%s%s%s %s alert \"%s\"", handshake, direction, rw,
				SSL_alert_type_string_long(ret), SSL_alert_desc_string_long(ret));
	}
}
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: compose a TLS error message                                       *
 *                                                                            *
 ******************************************************************************/
#if defined(HAVE_OPENSSL)
static void	zbx_tls_error_msg(char **error, size_t *error_alloc, size_t *error_offset)
{
	unsigned long	error_code;
	const char	*file, *data;
	int		line, flags;
	char		err[1024];

	/* concatenate all error messages in the queue into one string */
#if defined(OPENSSL_VERSION_MAJOR) && OPENSSL_VERSION_NUMBER >=	3	/* OpenSSL 3.0.0 or newer */
	const char	*func;

	while (0 != (error_code = ERR_get_error_all(&file, &line, &func, &data, &flags)))
#else									/* OpenSSL 1.x.x or LibreSSL */
	while (0 != (error_code = ERR_get_error_line_data(&file, &line, &data, &flags)))
#endif
	{
		ERR_error_string_n(error_code, err, sizeof(err));

#if defined(OPENSSL_VERSION_MAJOR) && OPENSSL_VERSION_NUMBER >=	3	/* OpenSSL 3.0.0 or newer */
		zbx_snprintf_alloc(error, error_alloc, error_offset, " file %s line %d func %s: %s",
				file, line, func, err);
#else									/* OpenSSL 1.x.x or LibreSSL */
		zbx_snprintf_alloc(error, error_alloc, error_offset, " file %s line %d: %s", file, line, err);
#endif
		if (NULL != data && 0 != (flags & ERR_TXT_STRING))
			zbx_snprintf_alloc(error, error_alloc, error_offset, ": %s", data);
	}
}
#endif

/******************************************************************************
 *                                                                            *
 * Purpose:                                                                   *
 *     return the name of a configuration file or command line parameter that *
 *     the value of the given parameter comes from                            *
 *                                                                            *
 * Parameters:                                                                *
 *     type  - [IN] type of parameter (file or command line)                  *
 *     param - [IN] address of the global parameter variable                  *
 *                                                                            *
 ******************************************************************************/
#define ZBX_TLS_PARAMETER_CONFIG_FILE	0
#define ZBX_TLS_PARAMETER_COMMAND_LINE	1
static const char	*zbx_tls_parameter_name(int type, char **param)
{
	if (&CONFIG_TLS_CONNECT == param)
		return ZBX_TLS_PARAMETER_CONFIG_FILE == type ? "TLSConnect" : "--tls-connect";

	if (&CONFIG_TLS_ACCEPT == param)
		return "TLSAccept";

	if (&CONFIG_TLS_CA_FILE == param)
		return ZBX_TLS_PARAMETER_CONFIG_FILE == type ? "TLSCAFile" : "--tls-ca-file";

	if (&CONFIG_TLS_CRL_FILE == param)
		return ZBX_TLS_PARAMETER_CONFIG_FILE == type ? "TLSCRLFile" : "--tls-crl-file";

	if (&CONFIG_TLS_SERVER_CERT_ISSUER == param)
	{
		if (ZBX_TLS_PARAMETER_CONFIG_FILE == type)
			return "TLSServerCertIssuer";

		if (0 != (program_type & ZBX_PROGRAM_TYPE_GET))
			return "--tls-agent-cert-issuer";
		else
			return "--tls-server-cert-issuer";
	}

	if (&CONFIG_TLS_SERVER_CERT_SUBJECT == param)
	{
		if (ZBX_TLS_PARAMETER_CONFIG_FILE == type)
			return "TLSServerCertSubject";

		if (0 != (program_type & ZBX_PROGRAM_TYPE_GET))
			return "--tls-agent-cert-subject";
		else
			return "--tls-server-cert-subject";
	}

	if (&CONFIG_TLS_CERT_FILE == param)
		return ZBX_TLS_PARAMETER_CONFIG_FILE == type ? "TLSCertFile" : "--tls-cert-file";

	if (&CONFIG_TLS_KEY_FILE == param)
		return ZBX_TLS_PARAMETER_CONFIG_FILE == type ? "TLSKeyFile" : "--tls-key-file";

	if (&CONFIG_TLS_PSK_IDENTITY == param)
		return ZBX_TLS_PARAMETER_CONFIG_FILE == type ? "TLSPSKIdentity" : "--tls-psk-identity";

	if (&CONFIG_TLS_PSK_FILE == param)
		return ZBX_TLS_PARAMETER_CONFIG_FILE == type ? "TLSPSKFile" : "--tls-psk-file";

	if (&CONFIG_TLS_CIPHER_CERT13 == param)
		return "TLSCipherCert13";

	if (&CONFIG_TLS_CIPHER_CERT == param)
		return "TLSCipherCert";

	if (&CONFIG_TLS_CIPHER_PSK13 == param)
		return "TLSCipherPSK13";

	if (&CONFIG_TLS_CIPHER_PSK == param)
		return "TLSCipherPSK";

	if (&CONFIG_TLS_CIPHER_ALL13 == param)
		return "TLSCipherAll13";

	if (&CONFIG_TLS_CIPHER_ALL == param)
		return "TLSCipherAll";

	if (&CONFIG_TLS_CIPHER_CMD13 == param)
		return "--tls-cipher13";

	if (&CONFIG_TLS_CIPHER_CMD == param)
		return "--tls-cipher";

	THIS_SHOULD_NEVER_HAPPEN;

	zbx_tls_free();
	exit(EXIT_FAILURE);
}

/******************************************************************************
 *                                                                            *
 * Purpose:                                                                   *
 *     Helper function: check if a configuration parameter is defined it must *
 *     not be empty. Otherwise log error and exit.                            *
 *                                                                            *
 * Parameters:                                                                *
 *     param - [IN] address of the global parameter variable                  *
 *                                                                            *
 ******************************************************************************/
static void	zbx_tls_parameter_not_empty(char **param)
{
	const char	*value = *param;

	if (NULL != value)
	{
		while ('\0' != *value)
		{
			if (0 == isspace(*value++))
				return;
		}

		if (0 != (program_type & ZBX_PROGRAM_TYPE_SENDER))
		{
			const char	*name1, *name2;

			name1 = zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param);
			name2 = zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param);

			if (0 != strcmp(name1, name2))
			{
				zabbix_log(LOG_LEVEL_CRIT, "configuration parameter \"%s\" or \"%s\" is defined but"
						" empty", name1, name2);
			}
			else
			{
				zabbix_log(LOG_LEVEL_CRIT, "configuration parameter \"%s\" is defined but empty",
						name1);
			}
		}
		else if (0 != (program_type & ZBX_PROGRAM_TYPE_GET))
		{
			zabbix_log(LOG_LEVEL_CRIT, "configuration parameter \"%s\" is defined but empty",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param));
		}
		else
		{
			zabbix_log(LOG_LEVEL_CRIT, "configuration parameter \"%s\" is defined but empty",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param));
		}

		zbx_tls_free();
		exit(EXIT_FAILURE);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose:                                                                   *
 *     Helper function: log error message depending on program type and exit. *
 *                                                                            *
 * Parameters:                                                                *
 *     type   - [IN] type of TLS validation error                             *
 *     param1 - [IN] address of the first global parameter variable           *
 *     param2 - [IN] address of the second global parameter variable (if any) *
 *                                                                            *
 ******************************************************************************/
#define ZBX_TLS_VALIDATION_INVALID	0
#define ZBX_TLS_VALIDATION_DEPENDENCY	1
#define ZBX_TLS_VALIDATION_REQUIREMENT	2
#define ZBX_TLS_VALIDATION_UTF8		3
#define ZBX_TLS_VALIDATION_NO_PSK	4
static void	zbx_tls_validation_error(int type, char **param1, char **param2)
{
	if (ZBX_TLS_VALIDATION_INVALID == type)
	{
		if (0 != (program_type & ZBX_PROGRAM_TYPE_SENDER))
		{
			zabbix_log(LOG_LEVEL_CRIT, "invalid value of \"%s\" or \"%s\" parameter",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param1),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param1));
		}
		else if (0 != (program_type & ZBX_PROGRAM_TYPE_GET))
		{
			zabbix_log(LOG_LEVEL_CRIT, "invalid value of \"%s\" parameter",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param1));
		}
		else
		{
			zabbix_log(LOG_LEVEL_CRIT, "invalid value of \"%s\" parameter",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param1));
		}
	}
	else if (ZBX_TLS_VALIDATION_DEPENDENCY == type)
	{
		if (0 != (program_type & ZBX_PROGRAM_TYPE_SENDER))
		{
			zabbix_log(LOG_LEVEL_CRIT, "parameter \"%s\" or \"%s\" is defined,"
					" but neither \"%s\" nor \"%s\" is defined",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param1),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param1),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param2),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param2));
		}
		else if (0 != (program_type & ZBX_PROGRAM_TYPE_GET))
		{
			zabbix_log(LOG_LEVEL_CRIT, "parameter \"%s\" is defined, but \"%s\" is not defined",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param1),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param2));
		}
		else
		{
			zabbix_log(LOG_LEVEL_CRIT, "parameter \"%s\" is defined, but \"%s\" is not defined",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param1),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param2));
		}
	}
	else if (ZBX_TLS_VALIDATION_REQUIREMENT == type)
	{
		if (0 != (program_type & ZBX_PROGRAM_TYPE_SENDER))
		{
			zabbix_log(LOG_LEVEL_CRIT, "parameter \"%s\" or \"%s\" value requires \"%s\" or \"%s\","
					" but neither of them is defined",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param1),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param1),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param2),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param2));
		}
		else if (0 != (program_type & ZBX_PROGRAM_TYPE_GET))
		{
			zabbix_log(LOG_LEVEL_CRIT, "parameter \"%s\" value requires \"%s\", but it is not defined",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param1),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param2));
		}
		else
		{
			zabbix_log(LOG_LEVEL_CRIT, "parameter \"%s\" value requires \"%s\", but it is not defined",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param1),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param2));
		}
	}
	else if (ZBX_TLS_VALIDATION_UTF8 == type)
	{
		if (0 != (program_type & ZBX_PROGRAM_TYPE_SENDER))
		{
			zabbix_log(LOG_LEVEL_CRIT, "parameter \"%s\" or \"%s\" value is not a valid UTF-8 string",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param1),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param1));
		}
		else if (0 != (program_type & ZBX_PROGRAM_TYPE_GET))
		{
			zabbix_log(LOG_LEVEL_CRIT, "parameter \"%s\" value is not a valid UTF-8 string",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param1));
		}
		else
		{
			zabbix_log(LOG_LEVEL_CRIT, "parameter \"%s\" value is not a valid UTF-8 string",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param1));
		}
	}
	else if (ZBX_TLS_VALIDATION_NO_PSK == type)
	{
		if (0 != (program_type & ZBX_PROGRAM_TYPE_SENDER))
		{
			zabbix_log(LOG_LEVEL_CRIT, "value of parameter \"%s\" or \"%s\" requires support of encrypted"
					" connection with PSK but support for PSK was not compiled in",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param1),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param1));
		}
		else if (0 != (program_type & ZBX_PROGRAM_TYPE_GET))
		{
			zabbix_log(LOG_LEVEL_CRIT, "value of parameter \"%s\" requires support of encrypted"
					" connection with PSK but support for PSK was not compiled in",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param1));
		}
		else
		{
			zabbix_log(LOG_LEVEL_CRIT, "value of parameter \"%s\" requires support of encrypted"
					" connection with PSK but support for PSK was not compiled in",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param1));
		}
	}
	else
		THIS_SHOULD_NEVER_HAPPEN;

	zbx_tls_free();
	exit(EXIT_FAILURE);
}

/******************************************************************************
 *                                                                            *
 * Purpose:                                                                   *
 *     Helper function: log error message depending on program type and exit  *
 *                                                                            *
 * Parameters:                                                                *
 *     type   - [IN] type of TLS validation error                             *
 *     param1 - [IN] address of the first global parameter variable           *
 *     param2 - [IN] address of the second global parameter variable          *
 *     param3 - [IN] address of the third global parameter variable           *
 *                                                                            *
 ******************************************************************************/
static void	zbx_tls_validation_error2(int type, char **param1, char **param2, char **param3)
{
	if (ZBX_TLS_VALIDATION_DEPENDENCY == type)
	{
		if (0 != (program_type & ZBX_PROGRAM_TYPE_AGENTD))
		{
			zabbix_log(LOG_LEVEL_CRIT, "parameter \"%s\" is defined,"
					" but neither \"%s\" nor \"%s\" is defined",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param1),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param2),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param3));
		}
		else if (0 != (program_type & ZBX_PROGRAM_TYPE_GET))
		{
			zabbix_log(LOG_LEVEL_CRIT, "parameter \"%s\" is defined,"
					" but neither \"%s\" nor \"%s\" is defined",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param1),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param2),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param3));
		}
		else if (0 != (program_type & ZBX_PROGRAM_TYPE_SENDER))
		{
			zabbix_log(LOG_LEVEL_CRIT, "parameter \"%s\" is defined,"
					" but neither \"%s\", nor \"%s\", nor \"%s\", nor \"%s\" is defined",
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param1),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param2),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param2),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_CONFIG_FILE, param3),
					zbx_tls_parameter_name(ZBX_TLS_PARAMETER_COMMAND_LINE, param3));
		}
	}
	else
		THIS_SHOULD_NEVER_HAPPEN;

	zbx_tls_free();
	exit(EXIT_FAILURE);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check for allowed combinations of TLS configuration parameters    *
 *                                                                            *
 * Comments:                                                                  *
 *     Valid combinations:                                                    *
 *         - either all 3 certificate parameters - CONFIG_TLS_CERT_FILE,      *
 *           CONFIG_TLS_KEY_FILE, CONFIG_TLS_CA_FILE  - are defined and not   *
 *           empty or none of them. Parameter CONFIG_TLS_CRL_FILE is optional *
 *           but may be defined only together with the 3 certificate          *
 *           parameters,                                                      *
 *         - either both PSK parameters - CONFIG_TLS_PSK_IDENTITY and         *
 *           CONFIG_TLS_PSK_FILE - are defined and not empty or none of them, *
 *           (if CONFIG_TLS_PSK_IDENTITY is defined it must be a valid UTF-8  *
 *           string),                                                         *
 *         - in active agent, active proxy, zabbix_get, and zabbix_sender the *
 *           certificate and PSK parameters must match the value of           *
 *           CONFIG_TLS_CONNECT parameter,                                    *
 *         - in passive agent and passive proxy the certificate and PSK       *
 *           parameters must match the value of CONFIG_TLS_ACCEPT parameter.  *
 *                                                                            *
 ******************************************************************************/
void	zbx_tls_validate_config(void)
{
	zbx_tls_parameter_not_empty(&CONFIG_TLS_CONNECT);
	zbx_tls_parameter_not_empty(&CONFIG_TLS_ACCEPT);
	zbx_tls_parameter_not_empty(&CONFIG_TLS_CA_FILE);
	zbx_tls_parameter_not_empty(&CONFIG_TLS_CRL_FILE);
	zbx_tls_parameter_not_empty(&CONFIG_TLS_SERVER_CERT_ISSUER);
	zbx_tls_parameter_not_empty(&CONFIG_TLS_SERVER_CERT_SUBJECT);
	zbx_tls_parameter_not_empty(&CONFIG_TLS_CERT_FILE);
	zbx_tls_parameter_not_empty(&CONFIG_TLS_KEY_FILE);
	zbx_tls_parameter_not_empty(&CONFIG_TLS_PSK_IDENTITY);
	zbx_tls_parameter_not_empty(&CONFIG_TLS_PSK_FILE);

	zbx_tls_parameter_not_empty(&CONFIG_TLS_CIPHER_CERT13);
	zbx_tls_parameter_not_empty(&CONFIG_TLS_CIPHER_PSK13);
	zbx_tls_parameter_not_empty(&CONFIG_TLS_CIPHER_ALL13);
	zbx_tls_parameter_not_empty(&CONFIG_TLS_CIPHER_CMD13);

	zbx_tls_parameter_not_empty(&CONFIG_TLS_CIPHER_CERT);
	zbx_tls_parameter_not_empty(&CONFIG_TLS_CIPHER_PSK);
	zbx_tls_parameter_not_empty(&CONFIG_TLS_CIPHER_ALL);
	zbx_tls_parameter_not_empty(&CONFIG_TLS_CIPHER_CMD);

	/* parse and validate 'TLSConnect' parameter (in zabbix_proxy.conf, zabbix_agentd.conf) and '--tls-connect' */
	/* parameter (in zabbix_get and zabbix_sender) */

	if (NULL != CONFIG_TLS_CONNECT)
	{
		/* 'configured_tls_connect_mode' is shared between threads on MS Windows */

		if (0 == strcmp(CONFIG_TLS_CONNECT, ZBX_TCP_SEC_UNENCRYPTED_TXT))
			configured_tls_connect_mode = ZBX_TCP_SEC_UNENCRYPTED;
		else if (0 == strcmp(CONFIG_TLS_CONNECT, ZBX_TCP_SEC_TLS_CERT_TXT))
			configured_tls_connect_mode = ZBX_TCP_SEC_TLS_CERT;
		else if (0 == strcmp(CONFIG_TLS_CONNECT, ZBX_TCP_SEC_TLS_PSK_TXT))
#if defined(HAVE_GNUTLS) || (defined(HAVE_OPENSSL) && defined(HAVE_OPENSSL_WITH_PSK))
			configured_tls_connect_mode = ZBX_TCP_SEC_TLS_PSK;
#else
			zbx_tls_validation_error(ZBX_TLS_VALIDATION_NO_PSK, &CONFIG_TLS_CONNECT, NULL);
#endif
		else
			zbx_tls_validation_error(ZBX_TLS_VALIDATION_INVALID, &CONFIG_TLS_CONNECT, NULL);
	}

	/* parse and validate 'TLSAccept' parameter (in zabbix_proxy.conf, zabbix_agentd.conf) */

	if (NULL != CONFIG_TLS_ACCEPT)
	{
		char		*s, *p, *delim;
		unsigned int	accept_modes_tmp = 0;	/* 'configured_tls_accept_modes' is shared between threads on */
							/* MS Windows. To avoid races make a local temporary */
							/* variable, modify it and write into */
							/* 'configured_tls_accept_modes' when done. */

		p = s = zbx_strdup(NULL, CONFIG_TLS_ACCEPT);

		while (1)
		{
			delim = strchr(p, ',');

			if (NULL != delim)
				*delim = '\0';

			if (0 == strcmp(p, ZBX_TCP_SEC_UNENCRYPTED_TXT))
				accept_modes_tmp |= ZBX_TCP_SEC_UNENCRYPTED;
			else if (0 == strcmp(p, ZBX_TCP_SEC_TLS_CERT_TXT))
				accept_modes_tmp |= ZBX_TCP_SEC_TLS_CERT;
			else if (0 == strcmp(p, ZBX_TCP_SEC_TLS_PSK_TXT))
#if defined(HAVE_GNUTLS) || (defined(HAVE_OPENSSL) && defined(HAVE_OPENSSL_WITH_PSK))
				accept_modes_tmp |= ZBX_TCP_SEC_TLS_PSK;
#else
				zbx_tls_validation_error(ZBX_TLS_VALIDATION_NO_PSK, &CONFIG_TLS_ACCEPT, NULL);
#endif
			else
			{
				zbx_free(s);
				zbx_tls_validation_error(ZBX_TLS_VALIDATION_INVALID, &CONFIG_TLS_ACCEPT, NULL);
			}

			if (NULL == delim)
				break;

			p = delim + 1;
		}

		configured_tls_accept_modes = accept_modes_tmp;

		zbx_free(s);
	}

	/* either both a certificate and a private key must be defined or none of them */

	if (NULL != CONFIG_TLS_CERT_FILE && NULL == CONFIG_TLS_KEY_FILE)
		zbx_tls_validation_error(ZBX_TLS_VALIDATION_DEPENDENCY, &CONFIG_TLS_CERT_FILE, &CONFIG_TLS_KEY_FILE);

	if (NULL != CONFIG_TLS_KEY_FILE && NULL == CONFIG_TLS_CERT_FILE)
		zbx_tls_validation_error(ZBX_TLS_VALIDATION_DEPENDENCY, &CONFIG_TLS_KEY_FILE, &CONFIG_TLS_CERT_FILE);

	/* CA file must be defined only together with a certificate */

	if (NULL != CONFIG_TLS_CERT_FILE && NULL == CONFIG_TLS_CA_FILE)
		zbx_tls_validation_error(ZBX_TLS_VALIDATION_DEPENDENCY, &CONFIG_TLS_CERT_FILE, &CONFIG_TLS_CA_FILE);

	if (NULL != CONFIG_TLS_CA_FILE && NULL == CONFIG_TLS_CERT_FILE)
		zbx_tls_validation_error(ZBX_TLS_VALIDATION_DEPENDENCY, &CONFIG_TLS_CA_FILE, &CONFIG_TLS_CERT_FILE);

	/* CRL file is optional but must be defined only together with a certificate */

	if (NULL == CONFIG_TLS_CERT_FILE && NULL != CONFIG_TLS_CRL_FILE)
		zbx_tls_validation_error(ZBX_TLS_VALIDATION_DEPENDENCY, &CONFIG_TLS_CRL_FILE, &CONFIG_TLS_CERT_FILE);

	/* Server certificate issuer is optional but must be defined only together with a certificate */

	if (NULL == CONFIG_TLS_CERT_FILE && NULL != CONFIG_TLS_SERVER_CERT_ISSUER)
	{
		zbx_tls_validation_error(ZBX_TLS_VALIDATION_DEPENDENCY, &CONFIG_TLS_SERVER_CERT_ISSUER,
				&CONFIG_TLS_CERT_FILE);
	}

	/* Server certificate subject is optional but must be defined only together with a certificate */

	if (NULL == CONFIG_TLS_CERT_FILE && NULL != CONFIG_TLS_SERVER_CERT_SUBJECT)
	{
		zbx_tls_validation_error(ZBX_TLS_VALIDATION_DEPENDENCY, &CONFIG_TLS_SERVER_CERT_SUBJECT,
				&CONFIG_TLS_CERT_FILE);
	}

	/* either both a PSK and a PSK identity must be defined or none of them */

	if (NULL != CONFIG_TLS_PSK_FILE && NULL == CONFIG_TLS_PSK_IDENTITY)
		zbx_tls_validation_error(ZBX_TLS_VALIDATION_DEPENDENCY, &CONFIG_TLS_PSK_FILE, &CONFIG_TLS_PSK_IDENTITY);

	if (NULL != CONFIG_TLS_PSK_IDENTITY && NULL == CONFIG_TLS_PSK_FILE)
		zbx_tls_validation_error(ZBX_TLS_VALIDATION_DEPENDENCY, &CONFIG_TLS_PSK_IDENTITY, &CONFIG_TLS_PSK_FILE);

	/* PSK identity must be a valid UTF-8 string (RFC 4279 says Unicode) */
	if (NULL != CONFIG_TLS_PSK_IDENTITY && SUCCEED != zbx_is_utf8(CONFIG_TLS_PSK_IDENTITY))
		zbx_tls_validation_error(ZBX_TLS_VALIDATION_UTF8, &CONFIG_TLS_PSK_IDENTITY, NULL);

	/* active agentd, active proxy, zabbix_get, and zabbix_sender specific validation */

	if ((0 != (program_type & ZBX_PROGRAM_TYPE_AGENTD) && 0 != CONFIG_ACTIVE_FORKS) ||
			(0 != (program_type & (ZBX_PROGRAM_TYPE_PROXY_ACTIVE | ZBX_PROGRAM_TYPE_GET |
					ZBX_PROGRAM_TYPE_SENDER))))
	{
		/* 'TLSConnect' is the master parameter to be matched by certificate and PSK parameters. */

		if (NULL != CONFIG_TLS_CERT_FILE && NULL == CONFIG_TLS_CONNECT)
		{
			zbx_tls_validation_error(ZBX_TLS_VALIDATION_DEPENDENCY, &CONFIG_TLS_CERT_FILE,
					&CONFIG_TLS_CONNECT);
		}

		if (NULL != CONFIG_TLS_PSK_FILE && NULL == CONFIG_TLS_CONNECT)
		{
			zbx_tls_validation_error(ZBX_TLS_VALIDATION_DEPENDENCY, &CONFIG_TLS_PSK_FILE,
					&CONFIG_TLS_CONNECT);
		}

		if (0 != (configured_tls_connect_mode & ZBX_TCP_SEC_TLS_CERT) && NULL == CONFIG_TLS_CERT_FILE)
		{
			zbx_tls_validation_error(ZBX_TLS_VALIDATION_REQUIREMENT, &CONFIG_TLS_CONNECT,
					&CONFIG_TLS_CERT_FILE);
		}

		if (0 != (configured_tls_connect_mode & ZBX_TCP_SEC_TLS_PSK) && NULL == CONFIG_TLS_PSK_FILE)
		{
			zbx_tls_validation_error(ZBX_TLS_VALIDATION_REQUIREMENT, &CONFIG_TLS_CONNECT,
					&CONFIG_TLS_PSK_FILE);
		}
	}

	/* passive agentd and passive proxy specific validation */

	if ((0 != (program_type & ZBX_PROGRAM_TYPE_AGENTD) && 0 != CONFIG_PASSIVE_FORKS) ||
			0 != (program_type & ZBX_PROGRAM_TYPE_PROXY_PASSIVE))
	{
		/* 'TLSAccept' is the master parameter to be matched by certificate and PSK parameters */

		if (NULL != CONFIG_TLS_CERT_FILE && NULL == CONFIG_TLS_ACCEPT)
		{
			zbx_tls_validation_error(ZBX_TLS_VALIDATION_DEPENDENCY, &CONFIG_TLS_CERT_FILE,
					&CONFIG_TLS_ACCEPT);
		}

		if (NULL != CONFIG_TLS_PSK_FILE && NULL == CONFIG_TLS_ACCEPT)
		{
			zbx_tls_validation_error(ZBX_TLS_VALIDATION_DEPENDENCY, &CONFIG_TLS_PSK_FILE,
					&CONFIG_TLS_ACCEPT);
		}

		if (0 != (configured_tls_accept_modes & ZBX_TCP_SEC_TLS_CERT) && NULL == CONFIG_TLS_CERT_FILE)
		{
			zbx_tls_validation_error(ZBX_TLS_VALIDATION_REQUIREMENT, &CONFIG_TLS_ACCEPT,
					&CONFIG_TLS_CERT_FILE);
		}

		if (0 != (configured_tls_accept_modes & ZBX_TCP_SEC_TLS_PSK) && NULL == CONFIG_TLS_PSK_FILE)
		{
			zbx_tls_validation_error(ZBX_TLS_VALIDATION_REQUIREMENT, &CONFIG_TLS_ACCEPT,
					&CONFIG_TLS_PSK_FILE);
		}
	}

	/* TLSCipher* and --tls-cipher* parameter validation */

	/* parameters 'TLSCipherCert13' and 'TLSCipherCert' can be used only with certificate */

	if (NULL != CONFIG_TLS_CIPHER_CERT13 && NULL == CONFIG_TLS_CERT_FILE)
	{
		zbx_tls_validation_error(ZBX_TLS_VALIDATION_DEPENDENCY, &CONFIG_TLS_CIPHER_CERT13,
				&CONFIG_TLS_CERT_FILE);
	}

	if (NULL != CONFIG_TLS_CIPHER_CERT && NULL == CONFIG_TLS_CERT_FILE)
		zbx_tls_validation_error(ZBX_TLS_VALIDATION_DEPENDENCY, &CONFIG_TLS_CIPHER_CERT, &CONFIG_TLS_CERT_FILE);

	/* For server and proxy 'TLSCipherPSK13' and 'TLSCipherPSK' are optional and do not depend on other */
	/* TLS parameters. Validate only in case of agent, zabbix_get and sender. */

	if (0 != (program_type & (ZBX_PROGRAM_TYPE_AGENTD | ZBX_PROGRAM_TYPE_GET | ZBX_PROGRAM_TYPE_SENDER)))
	{
		if (NULL != CONFIG_TLS_CIPHER_PSK13 && NULL == CONFIG_TLS_PSK_IDENTITY)
		{
			zbx_tls_validation_error(ZBX_TLS_VALIDATION_DEPENDENCY, &CONFIG_TLS_CIPHER_PSK13,
					&CONFIG_TLS_PSK_IDENTITY);

		}

		if (NULL != CONFIG_TLS_CIPHER_PSK && NULL == CONFIG_TLS_PSK_IDENTITY)
		{
			zbx_tls_validation_error(ZBX_TLS_VALIDATION_DEPENDENCY, &CONFIG_TLS_CIPHER_PSK,
					&CONFIG_TLS_PSK_IDENTITY);
		}
	}

	/* Parameters 'TLSCipherAll13' and 'TLSCipherAll' are used only for incoming connections if a combined list */
	/* of certificate- and PSK-based ciphersuites is used. They may be defined without other TLS parameters on */
	/* server and proxy (at least some hosts may be connecting with PSK). */
	/* 'zabbix_get' and sender do not use these parameters. Validate only in case of agent. */

	if (0 != (program_type & ZBX_PROGRAM_TYPE_AGENTD) &&
			NULL == CONFIG_TLS_CERT_FILE && NULL == CONFIG_TLS_PSK_IDENTITY)
	{
		if (NULL != CONFIG_TLS_CIPHER_ALL13)
		{
			zbx_tls_validation_error2(ZBX_TLS_VALIDATION_DEPENDENCY, &CONFIG_TLS_CIPHER_ALL13,
					&CONFIG_TLS_CERT_FILE, &CONFIG_TLS_PSK_IDENTITY);
		}

		if (NULL != CONFIG_TLS_CIPHER_ALL)
		{
			zbx_tls_validation_error2(ZBX_TLS_VALIDATION_DEPENDENCY, &CONFIG_TLS_CIPHER_ALL,
					&CONFIG_TLS_CERT_FILE, &CONFIG_TLS_PSK_IDENTITY);
		}
	}

	/* Parameters '--tls-cipher13' and '--tls-cipher' can be used only in zabbix_get and sender with */
	/* certificate or PSK. */

	if (0 != (program_type & (ZBX_PROGRAM_TYPE_GET | ZBX_PROGRAM_TYPE_SENDER)) &&
			NULL == CONFIG_TLS_CERT_FILE && NULL == CONFIG_TLS_PSK_IDENTITY)
	{
		if (NULL != CONFIG_TLS_CIPHER_CMD13)
		{
			zbx_tls_validation_error2(ZBX_TLS_VALIDATION_DEPENDENCY, &CONFIG_TLS_CIPHER_CMD13,
					&CONFIG_TLS_CERT_FILE, &CONFIG_TLS_PSK_IDENTITY);
		}

		if (NULL != CONFIG_TLS_CIPHER_CMD)
		{
			zbx_tls_validation_error2(ZBX_TLS_VALIDATION_DEPENDENCY, &CONFIG_TLS_CIPHER_CMD,
					&CONFIG_TLS_CERT_FILE, &CONFIG_TLS_PSK_IDENTITY);
		}
	}
}

static void	zbx_psk_warn_misconfig(const char *psk_identity)
{
	zabbix_log(LOG_LEVEL_WARNING, "same PSK identity \"%s\" but different PSK values used in proxy configuration"
			" file, for host or for autoregistration; autoregistration will not be allowed", psk_identity);
}

#if defined(HAVE_GNUTLS)
/******************************************************************************
 *                                                                            *
 * Purpose:                                                                   *
 *     find and set the requested pre-shared key upon GnuTLS request          *
 *                                                                            *
 * Parameters:                                                                *
 *     session      - [IN] not used                                           *
 *     psk_identity - [IN] PSK identity for which the PSK should be searched  *
 *                         and set                                            *
 *     key          - [OUT pre-shared key allocated and set                   *
 *                                                                            *
 * Return value:                                                              *
 *     0  - required PSK successfully found and set                           *
 *     -1 - an error occurred                                                 *
 *                                                                            *
 * Comments:                                                                  *
 *     A callback function, its arguments are defined in GnuTLS.              *
 *     Used in all programs accepting connections.                            *
 *                                                                            *
 ******************************************************************************/
static int	zbx_psk_cb(gnutls_session_t session, const char *psk_identity, gnutls_datum_t *key)
{
	char		*psk;
	size_t		psk_len = 0;
	int		psk_bin_len;
	unsigned char	tls_psk_hex[HOST_TLS_PSK_LEN_MAX], psk_buf[HOST_TLS_PSK_LEN / 2];

	ZBX_UNUSED(session);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() requested PSK identity \"%s\"", __func__, psk_identity);

	psk_usage = 0;

	if (0 != (program_type & (ZBX_PROGRAM_TYPE_PROXY | ZBX_PROGRAM_TYPE_SERVER)))
	{
		/* call the function DCget_psk_by_identity() by pointer */
		if (0 < find_psk_in_cache((const unsigned char *)psk_identity, tls_psk_hex, &psk_usage))
		{
			/* The PSK is in configuration cache. Convert PSK to binary form. */
			if (0 >= (psk_bin_len = zbx_hex2bin(tls_psk_hex, psk_buf, sizeof(psk_buf))))
			{
				/* this should have been prevented by validation in frontend or API */
				zabbix_log(LOG_LEVEL_WARNING, "cannot convert PSK to binary form for PSK identity"
						" \"%s\"", psk_identity);
				return -1;	/* fail */
			}

			psk = (char *)psk_buf;
			psk_len = (size_t)psk_bin_len;
		}

		if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY) &&
				0 < my_psk_identity_len &&
				0 == strcmp(my_psk_identity, psk_identity))
		{
			/* the PSK is in proxy configuration file */
			psk_usage |= ZBX_PSK_FOR_PROXY;

			if (0 < psk_len && (psk_len != my_psk_len || 0 != memcmp(psk, my_psk, psk_len)))
			{
				/* PSK was also found in configuration cache but with different value */
				zbx_psk_warn_misconfig(psk_identity);
				psk_usage &= ~(unsigned int)ZBX_PSK_FOR_AUTOREG;
			}

			psk = my_psk;	/* prefer PSK from proxy configuration file */
			psk_len = my_psk_len;
		}

		if (0 == psk_len)
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot find requested PSK identity \"%s\"", psk_identity);
			return -1;	/* fail */
		}
	}
	else if (0 != (program_type & ZBX_PROGRAM_TYPE_AGENTD))
	{
		if (0 < my_psk_identity_len)
		{
			if (0 == strcmp(my_psk_identity, psk_identity))
			{
				psk = my_psk;
				psk_len = my_psk_len;
			}
			else
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot find requested PSK identity \"%s\", available PSK"
						" identity \"%s\"", psk_identity, my_psk_identity);
				return -1;	/* fail */
			}
		}
	}

	if (0 < psk_len)
	{
		if (NULL == (key->data = gnutls_malloc(psk_len)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot allocate " ZBX_FS_SIZE_T " bytes of memory for PSK with"
					" identity \"%s\"", (zbx_fs_size_t)psk_len, psk_identity);
			return -1;	/* fail */
		}

		memcpy(key->data, psk, psk_len);
		key->size = (unsigned int)psk_len;

		return 0;	/* success */
	}

	return -1;	/* PSK not found */
}
#elif defined(HAVE_OPENSSL) && defined(HAVE_OPENSSL_WITH_PSK)
/******************************************************************************
 *                                                                            *
 * Purpose:                                                                   *
 *     set pre-shared key for outgoing TLS connection upon OpenSSL request    *
 *                                                                            *
 * Parameters:                                                                *
 *     ssl              - [IN] not used                                       *
 *     hint             - [IN] not used                                       *
 *     identity         - [OUT] buffer to write PSK identity into             *
 *     max_identity_len - [IN] size of the 'identity' buffer                  *
 *     psk              - [OUT] buffer to write PSK into                      *
 *     max_psk_len      - [IN] size of the 'psk' buffer                       *
 *                                                                            *
 * Return value:                                                              *
 *     > 0 - length of PSK in bytes                                           *
 *       0 - an error occurred                                                *
 *                                                                            *
 * Comments:                                                                  *
 *     A callback function, its arguments are defined in OpenSSL.             *
 *     Used in all programs making outgoing TLS PSK connections.              *
 *                                                                            *
 *     As a client we use different PSKs depending on connection to be made.  *
 *     Apparently there is no simple way to specify which PSK should be set   *
 *     by this callback function. We use global variables to pass this info.  *
 *                                                                            *
 ******************************************************************************/
static unsigned int	zbx_psk_client_cb(SSL *ssl, const char *hint, char *identity,
		unsigned int max_identity_len, unsigned char *psk, unsigned int max_psk_len)
{
	ZBX_UNUSED(ssl);
	ZBX_UNUSED(hint);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() requested PSK identity \"%s\"", __func__, psk_identity_for_cb);

	if (max_identity_len < psk_identity_len_for_cb + 1)	/* 1 byte for terminating '\0' */
	{
		zabbix_log(LOG_LEVEL_WARNING, "requested PSK identity \"%s\" does not fit into %u-byte buffer",
				psk_identity_for_cb, max_identity_len);
		return 0;
	}

	if (max_psk_len < psk_len_for_cb)
	{
		zabbix_log(LOG_LEVEL_WARNING, "PSK associated with PSK identity \"%s\" does not fit into %u-byte"
				" buffer", psk_identity_for_cb, max_psk_len);
		return 0;
	}

	zbx_strlcpy(identity, psk_identity_for_cb, max_identity_len);
	memcpy(psk, psk_for_cb, psk_len_for_cb);

	return (unsigned int)psk_len_for_cb;
}

/******************************************************************************
 *                                                                            *
 * Purpose:                                                                   *
 *     set pre-shared key for incoming TLS connection upon OpenSSL request    *
 *                                                                            *
 * Parameters:                                                                *
 *     ssl              - [IN] not used                                       *
 *     identity         - [IN] PSK identity sent by client                    *
 *     psk              - [OUT] buffer to write PSK into                      *
 *     max_psk_len      - [IN] size of the 'psk' buffer                       *
 *                                                                            *
 * Return value:                                                              *
 *     > 0 - length of PSK in bytes                                           *
 *       0 - PSK identity not found                                           *
 *                                                                            *
 * Comments:                                                                  *
 *     A callback function, its arguments are defined in OpenSSL.             *
 *     Used in all programs accepting incoming TLS PSK connections.           *
 *                                                                            *
 ******************************************************************************/
static unsigned int	zbx_psk_server_cb(SSL *ssl, const char *identity, unsigned char *psk,
		unsigned int max_psk_len)
{
	char		*psk_loc;
	size_t		psk_len = 0;
	int		psk_bin_len;
	unsigned char	tls_psk_hex[HOST_TLS_PSK_LEN_MAX], psk_buf[HOST_TLS_PSK_LEN / 2];

	ZBX_UNUSED(ssl);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() requested PSK identity \"%s\"", __func__, identity);

	incoming_connection_has_psk = 1;
	psk_usage = 0;

	if (0 != (program_type & (ZBX_PROGRAM_TYPE_PROXY | ZBX_PROGRAM_TYPE_SERVER)))
	{
		/* call the function DCget_psk_by_identity() by pointer */
		if (0 < find_psk_in_cache((const unsigned char *)identity, tls_psk_hex, &psk_usage))
		{
			/* The PSK is in configuration cache. Convert PSK to binary form. */
			if (0 >= (psk_bin_len = zbx_hex2bin(tls_psk_hex, psk_buf, sizeof(psk_buf))))
			{
				/* this should have been prevented by validation in frontend or API */
				zabbix_log(LOG_LEVEL_WARNING, "cannot convert PSK to binary form for PSK identity"
						" \"%s\"", identity);
				goto fail;
			}

			psk_loc = (char *)psk_buf;
			psk_len = (size_t)psk_bin_len;
		}

		if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY) &&
				0 < my_psk_identity_len &&
				0 == strcmp(my_psk_identity, identity))
		{
			/* the PSK is in proxy configuration file */
			psk_usage |= ZBX_PSK_FOR_PROXY;

			if (0 < psk_len && (psk_len != my_psk_len || 0 != memcmp(psk_loc, my_psk, psk_len)))
			{
				/* PSK was also found in configuration cache but with different value */
				zbx_psk_warn_misconfig(identity);
				psk_usage &= ~(unsigned int)ZBX_PSK_FOR_AUTOREG;
			}

			psk_loc = my_psk;	/* prefer PSK from proxy configuration file */
			psk_len = my_psk_len;
		}

		if (0 == psk_len)
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot find requested PSK identity \"%s\"", identity);
			goto fail;
		}
	}
	else if (0 != (program_type & ZBX_PROGRAM_TYPE_AGENTD))
	{
		if (0 < my_psk_identity_len)
		{
			if (0 == strcmp(my_psk_identity, identity))
			{
				psk_loc = my_psk;
				psk_len = my_psk_len;
			}
			else
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot find requested PSK identity \"%s\", available PSK"
						" identity \"%s\"", identity, my_psk_identity);
				goto fail;
			}
		}
	}

	if (0 < psk_len)
	{
		if ((size_t)max_psk_len < psk_len)
		{
			zabbix_log(LOG_LEVEL_WARNING, "PSK associated with PSK identity \"%s\" does not fit into"
					" %u-byte buffer", identity, max_psk_len);
			goto fail;
		}

		memcpy(psk, psk_loc, psk_len);
		zbx_strlcpy(incoming_connection_psk_id, identity, sizeof(incoming_connection_psk_id));

		return (unsigned int)psk_len;	/* success */
	}
fail:
	incoming_connection_psk_id[0] = '\0';
	return 0;	/* PSK not found */
}
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: Check PSK identity length. Exit if length exceeds the maximum.    *
 *                                                                            *
 ******************************************************************************/
static void	zbx_check_psk_identity_len(size_t psk_identity_len)
{
	if (HOST_TLS_PSK_IDENTITY_LEN < psk_identity_len)
	{
		zabbix_log(LOG_LEVEL_CRIT, "PSK identity length " ZBX_FS_SIZE_T " exceeds the maximum length of %d"
				" bytes.", (zbx_fs_size_t)psk_identity_len, HOST_TLS_PSK_IDENTITY_LEN);
		zbx_tls_free();
		exit(EXIT_FAILURE);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose:                                                                   *
 *     read a pre-shared key from a configured file and convert it from       *
 *     textual representation (ASCII hex digit string) to a binary            *
 *     representation (byte string)                                           *
 *                                                                            *
 * Comments:                                                                  *
 *     Maximum length of PSK hex-digit string is defined by HOST_TLS_PSK_LEN. *
 *     Currently it is 512 characters, which encodes a 2048-bit PSK and is    *
 *     supported by GnuTLS and OpenSSL libraries (compiled with default       *
 *     parameters). If the key is longer an error message                     *
 *     "ssl_set_psk(): SSL - Bad input parameters to function" will be logged *
 *     at runtime.                                                            *
 *                                                                            *
 ******************************************************************************/
static void	zbx_read_psk_file(void)
{
	FILE		*f;
	size_t		len;
	int		len_bin, ret = FAIL;
	char		buf[HOST_TLS_PSK_LEN_MAX + 2];	/* up to 512 bytes of hex-digits, maybe 1-2 bytes for '\n', */
							/* 1 byte for terminating '\0' */
	char		buf_bin[HOST_TLS_PSK_LEN / 2];	/* up to 256 bytes of binary PSK */

	if (NULL == (f = fopen(CONFIG_TLS_PSK_FILE, "r")))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot open file \"%s\": %s", CONFIG_TLS_PSK_FILE, zbx_strerror(errno));
		goto out;
	}

	if (NULL == fgets(buf, (int)sizeof(buf), f))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot read from file \"%s\" or file empty", CONFIG_TLS_PSK_FILE);
		goto out;
	}

	buf[strcspn(buf, "\r\n")] = '\0';	/* discard newline at the end of string */

	if (0 == (len = strlen(buf)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "file \"%s\" is empty", CONFIG_TLS_PSK_FILE);
		goto out;
	}

	if (HOST_TLS_PSK_LEN_MIN > len)
	{
		zabbix_log(LOG_LEVEL_CRIT, "PSK in file \"%s\" is too short. Minimum is %d hex-digits",
				CONFIG_TLS_PSK_FILE, HOST_TLS_PSK_LEN_MIN);
		goto out;
	}

	if (HOST_TLS_PSK_LEN < len)
	{
		zabbix_log(LOG_LEVEL_CRIT, "PSK in file \"%s\" is too long. Maximum is %d hex-digits",
				CONFIG_TLS_PSK_FILE, HOST_TLS_PSK_LEN);
		goto out;
	}

	if (0 >= (len_bin = zbx_hex2bin((unsigned char *)buf, (unsigned char *)buf_bin, sizeof(buf_bin))))
	{
		zabbix_log(LOG_LEVEL_CRIT, "invalid PSK in file \"%s\"", CONFIG_TLS_PSK_FILE);
		goto out;
	}

	my_psk_len = (size_t)len_bin;
	my_psk = zbx_malloc(my_psk, my_psk_len);
	memcpy(my_psk, buf_bin, my_psk_len);

	ret = SUCCEED;
out:
	if (NULL != f && 0 != fclose(f))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot close file \"%s\": %s", CONFIG_TLS_PSK_FILE, zbx_strerror(errno));
		ret = FAIL;
	}

	if (SUCCEED == ret)
		return;

	zbx_tls_free();
	exit(EXIT_FAILURE);
}

#if defined(HAVE_GNUTLS)
/******************************************************************************
 *                                                                            *
 * Purpose: write names of enabled GnuTLS ciphersuites into Zabbix log for    *
 *          debugging                                                         *
 *                                                                            *
 * Parameters:                                                                *
 *     title1  - [IN] name of the calling function                            *
 *     title2  - [IN] name of the group of ciphersuites                       *
 *     ciphers - [IN] list of ciphersuites                                    *
 *                                                                            *
 ******************************************************************************/
static void	zbx_log_ciphersuites(const char *title1, const char *title2, gnutls_priority_t ciphers)
{
	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
	{
		char		*msg = NULL;
		size_t		msg_alloc = 0, msg_offset = 0;
		int		res;
		unsigned int	idx = 0, sidx;
		const char	*name;

		zbx_snprintf_alloc(&msg, &msg_alloc, &msg_offset, "%s() %s ciphersuites:", title1, title2);

		while (1)
		{
			if (GNUTLS_E_SUCCESS == (res = gnutls_priority_get_cipher_suite_index(ciphers, idx++, &sidx)))
			{
				if (NULL != (name = gnutls_cipher_suite_info(sidx, NULL, NULL, NULL, NULL, NULL)))
					zbx_snprintf_alloc(&msg, &msg_alloc, &msg_offset, " %s", name);
			}
			else if (GNUTLS_E_REQUESTED_DATA_NOT_AVAILABLE == res)
			{
				break;
			}

			/* ignore the 3rd possibility GNUTLS_E_UNKNOWN_CIPHER_SUITE */
			/* (see "man gnutls_priority_get_cipher_suite_index") */
		}

		zabbix_log(LOG_LEVEL_DEBUG, "%s", msg);
		zbx_free(msg);
	}
}
#elif defined(HAVE_OPENSSL)
/******************************************************************************
 *                                                                            *
 * Purpose: write names of enabled OpenSSL ciphersuites into Zabbix log for   *
 *          debugging                                                         *
 *                                                                            *
 * Parameters:                                                                *
 *     title1  - [IN] name of the calling function                            *
 *     title2  - [IN] name of the group of ciphersuites                       *
 *     ciphers - [IN] stack of ciphersuites                                   *
 *                                                                            *
 ******************************************************************************/
static void	zbx_log_ciphersuites(const char *title1, const char *title2, SSL_CTX *ciphers)
{
	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
	{
		char			*msg = NULL;
		size_t			msg_alloc = 0, msg_offset = 0;
		int			i, num;
		STACK_OF(SSL_CIPHER)	*cipher_list;

		zbx_snprintf_alloc(&msg, &msg_alloc, &msg_offset, "%s() %s ciphersuites:", title1, title2);

		cipher_list = SSL_CTX_get_ciphers(ciphers);
		num = sk_SSL_CIPHER_num(cipher_list);

		for (i = 0; i < num; i++)
		{
			zbx_snprintf_alloc(&msg, &msg_alloc, &msg_offset, " %s",
					SSL_CIPHER_get_name(sk_SSL_CIPHER_value(cipher_list, i)));
		}

		zabbix_log(LOG_LEVEL_DEBUG, "%s", msg);
		zbx_free(msg);
	}
}
#endif

#if defined(HAVE_GNUTLS)
/******************************************************************************
 *                                                                            *
 * Purpose:                                                                   *
 *     print an RDN (relative distinguished name) value into buffer           *
 *                                                                            *
 * Parameters:                                                                *
 *     value - [IN] pointer to RDN value                                      *
 *     len   - [IN] number of bytes in the RDN value                          *
 *     buf   - [OUT] output buffer                                            *
 *     size  - [IN] output buffer size                                        *
 *     error - [OUT] dynamically allocated memory with error message.         *
 *                   Initially '*error' must be NULL.                         *
 *                                                                            *
 * Return value:                                                              *
 *     number of bytes written into 'buf'                                     *
 *     '*error' is not NULL if an error occurred                              *
 *                                                                            *
 ******************************************************************************/
static size_t	zbx_print_rdn_value(const unsigned char *value, size_t len, unsigned char *buf, size_t size,
		char **error)
{
	const unsigned char	*p_in;
	unsigned char		*p_out, *p_out_end;

	p_in = value;
	p_out = buf;
	p_out_end = buf + size;

	while (value + len > p_in)
	{
		if (0 == (*p_in & 0x80))			/* ASCII */
		{
			if (0x1f < *p_in && *p_in < 0x7f)	/* printable character */
			{
				if (p_out_end - 1 > p_out)
				{
					/* According to RFC 4514:                                                   */
					/*    - escape characters '"' (U+0022), '+' U+002B, ',' U+002C, ';' U+003B, */
					/*      '<' U+003C, '>' U+003E, '\' U+005C  anywhere in string.             */
					/*    - escape characters space (' ' U+0020) or number sign ('#' U+0023) at */
					/*      the beginning of string.                                            */
					/*    - escape character space (' ' U+0020) at the end of string.           */
					/*    - escape null (U+0000) character anywhere. <--- we do not allow null. */

					if ((0x20 == (*p_in & 0x70) && ('"' == *p_in || '+' == *p_in || ',' == *p_in))
							|| (0x30 == (*p_in & 0x70) && (';' == *p_in || '<' == *p_in ||
							'>' == *p_in)) || '\\' == *p_in ||
							(' ' == *p_in && (value == p_in || (value + len - 1) == p_in))
							|| ('#' == *p_in && value == p_in))
					{
						*p_out++ = '\\';
					}
				}
				else
					goto small_buf;

				if (p_out_end - 1 > p_out)
					*p_out++ = *p_in++;
				else
					goto small_buf;
			}
			else if (0 == *p_in)
			{
				*error = zbx_strdup(*error, "null byte in certificate, could be an attack");
				break;
			}
			else
			{
				*error = zbx_strdup(*error, "non-printable character in certificate");
				break;
			}
		}
		else if (0xc0 == (*p_in & 0xe0))	/* 11000010-11011111 starts a 2-byte sequence */
		{
			if (p_out_end - 2 > p_out)
			{
				*p_out++ = *p_in++;
				*p_out++ = *p_in++;
			}
			else
				goto small_buf;
		}
		else if (0xe0 == (*p_in & 0xf0))	/* 11100000-11101111 starts a 3-byte sequence */
		{
			if (p_out_end - 3 > p_out)
			{
				*p_out++ = *p_in++;
				*p_out++ = *p_in++;
				*p_out++ = *p_in++;
			}
			else
				goto small_buf;
		}
		else if (0xf0 == (*p_in & 0xf8))	/* 11110000-11110100 starts a 4-byte sequence */
		{
			if (p_out_end - 4 > p_out)
			{
				*p_out++ = *p_in++;
				*p_out++ = *p_in++;
				*p_out++ = *p_in++;
				*p_out++ = *p_in++;
			}
			else
				goto small_buf;
		}
		else				/* not a valid UTF-8 character */
		{
			*error = zbx_strdup(*error, "invalid UTF-8 character");
			break;
		}
	}

	*p_out = '\0';

	return (size_t)(p_out - buf);
small_buf:
	*p_out = '\0';
	*error = zbx_strdup(*error, "output buffer too small");

	return (size_t)(p_out - buf);
}
#endif

#if defined(HAVE_GNUTLS)
/******************************************************************************
 *                                                                            *
 * Purpose:                                                                   *
 *     Print distinguished name (i.e. issuer, subject) into buffer. Intended  *
 *     to use as an alternative to GnuTLS gnutls_x509_crt_get_issuer_dn() and *
 *     gnutls_x509_crt_get_dn() to meet application needs.                    *
 *                                                                            *
 * Parameters:                                                                *
 *     dn    - [IN] pointer to distinguished name                             *
 *     buf   - [OUT] output buffer                                            *
 *     size  - [IN] output buffer size                                        *
 *     error - [OUT] dynamically allocated memory with error message          *
 *                                                                            *
 * Return value:                                                              *
 *     SUCCEED - no errors, FAIL - an error occurred                          *
 *                                                                            *
 * Comments:                                                                  *
 *     Multi-valued RDNs are not supported currently (only the first value is *
 *     printed).                                                              *
 *                                                                            *
 ******************************************************************************/
static int	zbx_x509_dn_gets(const gnutls_x509_dn_t dn, char *buf, size_t size, char **error)
{
#define ZBX_AVA_BUF_SIZE	20	/* hopefully no more than 20 RDNs */

	int			res, i_max = 0, i = 0, ava_dyn_size = 0;
	char			*p, *p_end;
	gnutls_x509_ava_st	*ava, *ava_dyn = NULL;
	char			oid_str[128];		/* size equal to MAX_OID_SIZE, internally defined in GnuTLS */
	gnutls_x509_ava_st	ava_stat[ZBX_AVA_BUF_SIZE];

	/* Find index of the last RDN in distinguished name. Remember pointers to RDNs to minimize calling of */
	/* gnutls_x509_dn_get_rdn_ava() as it seems a bit expensive. */

	while (1)
	{
		if (ZBX_AVA_BUF_SIZE > i)	/* most common case: small number of RDNs, fits in fixed buffer */
		{
			ava = &ava_stat[i];
		}
		else if (NULL == ava_dyn)	/* fixed buffer full, copy data to dynamic buffer */
		{
			ava_dyn_size = 2 * ZBX_AVA_BUF_SIZE;
			ava_dyn = zbx_malloc(NULL, (size_t)ava_dyn_size * sizeof(gnutls_x509_ava_st));

			memcpy(ava_dyn, ava_stat, ZBX_AVA_BUF_SIZE * sizeof(gnutls_x509_ava_st));
			ava = ava_dyn + ZBX_AVA_BUF_SIZE;
		}
		else if (ava_dyn_size > i)	/* fits in dynamic buffer */
		{
			ava = ava_dyn + i;
		}
		else				/* expand dynamic buffer */
		{
			ava_dyn_size += ZBX_AVA_BUF_SIZE;
			ava_dyn = zbx_realloc(ava_dyn, (size_t)ava_dyn_size * sizeof(gnutls_x509_ava_st));
			ava = ava_dyn + i;
		}

		if (0 == (res = gnutls_x509_dn_get_rdn_ava(dn, i, 0, ava)))	/* RDN with index 'i' exists */
		{
			i++;
		}
		else if (GNUTLS_E_ASN1_ELEMENT_NOT_FOUND == res)
		{
			i_max = i;
			break;
		}
		else
		{
			*error = zbx_dsprintf(*error, "zbx_x509_dn_gets(): gnutls_x509_dn_get_rdn_ava() failed: %d %s",
					res, gnutls_strerror(res));
			zbx_free(ava_dyn);
			return FAIL;
		}
	}

	/* "print" RDNs in reverse order (recommended by RFC 4514) */

	if (NULL == ava_dyn)
		ava = &ava_stat[0];
	else
		ava = ava_dyn;

	p = buf;
	p_end = buf + size;

	for (i = i_max - 1, ava += i; i >= 0; i--, ava--)
	{
		if (sizeof(oid_str) <= ava->oid.size)
		{
			THIS_SHOULD_NEVER_HAPPEN;
			zbx_free(ava_dyn);
			return FAIL;
		}

		memcpy(oid_str, ava->oid.data, ava->oid.size);
		oid_str[ava->oid.size] = '\0';

		if (buf != p)			/* not the first RDN being printed */
		{
			if (p_end - 1 == p)
				goto small_buf;

			p += zbx_strlcpy(p, ",", (size_t)(p_end - p));	/* separator between RDNs */
		}

		/* write attribute name */

		if (p_end - 1 == p)
			goto small_buf;

		p += zbx_strlcpy(p, gnutls_x509_dn_oid_name(oid_str, GNUTLS_X509_DN_OID_RETURN_OID),
				(size_t)(p_end - p));

		if (p_end - 1 == p)
			goto small_buf;

		p += zbx_strlcpy(p, "=", (size_t)(p_end - p));

		/* write attribute value */

		if (p_end - 1 == p)
			goto small_buf;

		p += zbx_print_rdn_value(ava->value.data, ava->value.size, (unsigned char *)p, (size_t)(p_end - p),
				error);

		if (NULL != *error)
			break;
	}

	zbx_free(ava_dyn);

	if (NULL == *error)
		return SUCCEED;
	else
		return FAIL;
small_buf:
	zbx_free(ava_dyn);
	*error = zbx_strdup(*error, "output buffer too small");

	return FAIL;

#undef ZBX_AVA_BUF_SIZE
}
#elif defined(HAVE_OPENSSL)
/******************************************************************************
 *                                                                            *
 * Purpose:                                                                   *
 *     Print distinguished name (i.e. issuer, subject) into buffer. Intended  *
 *     to use as an alternative to OpenSSL X509_NAME_oneline() and to meet    *
 *     application needs.                                                     *
 *                                                                            *
 * Parameters:                                                                *
 *     dn    - [IN] pointer to distinguished name                             *
 *     buf   - [OUT] output buffer                                            *
 *     size  - [IN] output buffer size                                        *
 *     error - [OUT] dynamically allocated memory with error message          *
 *                                                                            *
 * Return value:                                                              *
 *     SUCCEED - no errors, FAIL - an error occurred                          *
 *                                                                            *
 * Comments:                                                                  *
 *     Examples often use OpenSSL X509_NAME_oneline() to print certificate    *
 *     issuer and subject into memory buffers but it is a legacy function and *
 *     strongly discouraged in new applications. So, we have to use functions *
 *     writing into BIOs and then turn results into memory buffers.           *
 *                                                                            *
 ******************************************************************************/
static int	zbx_x509_dn_gets(X509_NAME *dn, char *buf, size_t size, char **error)
{
	BIO		*bio;
	const char	*data;
	size_t		len;
	int		ret = FAIL;

	if (NULL == (bio = BIO_new(BIO_s_mem())))
	{
		*error = zbx_strdup(*error, "cannot create BIO");
		goto out;
	}

	/* XN_FLAG_RFC2253 - RFC 2253 is outdated, it was replaced by RFC 4514 "Lightweight Directory Access Protocol */
	/* (LDAP): String Representation of Distinguished Names" */

	if (0 > X509_NAME_print_ex(bio, dn, 0, XN_FLAG_RFC2253 & ~ASN1_STRFLGS_ESC_MSB))
	{
		*error = zbx_strdup(*error, "cannot print distinguished name");
		goto out;
	}

	if (size <= (len = (size_t)BIO_get_mem_data(bio, &data)))
	{
		*error = zbx_strdup(*error, "output buffer too small");
		goto out;
	}

	zbx_strlcpy(buf, data, len + 1);
	ret = SUCCEED;
out:
	if (NULL != bio)
	{
		/* ensure that associated memory buffer will be freed by BIO_vfree() */
		(void)BIO_set_close(bio, BIO_CLOSE);
		BIO_vfree(bio);
	}

	return ret;
}
#endif

#if defined(HAVE_GNUTLS)
/******************************************************************************
 *                                                                            *
 * Purpose: get peer certificate from session                                 *
 *                                                                            *
 * Parameters:                                                                *
 *     session - [IN] session context                                         *
 *     error   - [OUT] dynamically allocated memory with error message        *
 *                                                                            *
 * Return value:                                                              *
 *     pointer to peer certificate - success                                  *
 *     NULL - an error occurred                                               *
 *                                                                            *
 * Comments:                                                                  *
 *     In case of success it is a responsibility of caller to deallocate      *
 *     the instance of certificate using gnutls_x509_crt_deinit().            *
 *                                                                            *
 ******************************************************************************/
static gnutls_x509_crt_t	zbx_get_peer_cert(const gnutls_session_t session, char **error)
{
	if (GNUTLS_CRT_X509 == gnutls_certificate_type_get(session))
	{
		int			res;
		unsigned int		cert_list_size = 0;
		const gnutls_datum_t	*cert_list;
		gnutls_x509_crt_t	cert;

		if (NULL == (cert_list = gnutls_certificate_get_peers(session, &cert_list_size)))
		{
			*error = zbx_dsprintf(*error, "%s(): gnutls_certificate_get_peers() returned NULL", __func__);
			return NULL;
		}

		if (0 == cert_list_size)
		{
			*error = zbx_dsprintf(*error, "%s(): gnutls_certificate_get_peers() returned 0 certificates",
					__func__);
			return NULL;
		}

		if (GNUTLS_E_SUCCESS != (res = gnutls_x509_crt_init(&cert)))
		{
			*error = zbx_dsprintf(*error, "%s(): gnutls_x509_crt_init() failed: %d %s", __func__,
					res, gnutls_strerror(res));
			return NULL;
		}

		/* the 1st element of the list is peer certificate */

		if (GNUTLS_E_SUCCESS != (res = gnutls_x509_crt_import(cert, &cert_list[0], GNUTLS_X509_FMT_DER)))
		{
			*error = zbx_dsprintf(*error, "%s(): gnutls_x509_crt_import() failed: %d %s", __func__,
					res, gnutls_strerror(res));
			gnutls_x509_crt_deinit(cert);
			return NULL;
		}

		return cert;	/* success */
	}
	else
	{
		*error = zbx_dsprintf(*error, "%s(): not an X509 certificate", __func__);
		return NULL;
	}
}
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: write peer certificate information into Zabbix log for debugging  *
 *                                                                            *
 * Parameters:                                                                *
 *     function_name - [IN] caller function name                              *
 *     tls_ctx       - [IN] TLS context                                       *
 *                                                                            *
 ******************************************************************************/
static void	zbx_log_peer_cert(const char *function_name, const zbx_tls_context_t *tls_ctx)
{
	char			*error = NULL;
#if defined(HAVE_GNUTLS)
	gnutls_x509_crt_t	cert;
	int			res;
	gnutls_datum_t		cert_print;
#elif defined(HAVE_OPENSSL)
	X509			*cert;
	char			issuer[HOST_TLS_ISSUER_LEN_MAX], subject[HOST_TLS_SUBJECT_LEN_MAX];
#endif

	if (SUCCEED != ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
		return;
#if defined(HAVE_GNUTLS)
	if (NULL == (cert = zbx_get_peer_cert(tls_ctx->ctx, &error)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s(): cannot obtain peer certificate: %s", function_name, error);
	}
	else if (GNUTLS_E_SUCCESS != (res = gnutls_x509_crt_print(cert, GNUTLS_CRT_PRINT_ONELINE, &cert_print)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s(): gnutls_x509_crt_print() failed: %d %s", function_name, res,
				gnutls_strerror(res));
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s(): peer certificate: %s", function_name, cert_print.data);
		gnutls_free(cert_print.data);
	}

	if (NULL != cert)
		gnutls_x509_crt_deinit(cert);
#elif defined(HAVE_OPENSSL)
	if (NULL == (cert = SSL_get_peer_certificate(tls_ctx->ctx)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() cannot obtain peer certificate", function_name);
	}
	else if (SUCCEED != zbx_x509_dn_gets(X509_get_issuer_name(cert), issuer, sizeof(issuer), &error))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() cannot obtain peer certificate issuer: %s", function_name, error);
	}
	else if (SUCCEED != zbx_x509_dn_gets(X509_get_subject_name(cert), subject, sizeof(subject), &error))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() cannot obtain peer certificate subject: %s", function_name, error);
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() peer certificate issuer:\"%s\" subject:\"%s\"",
				function_name, issuer, subject);
	}

	if (NULL != cert)
		X509_free(cert);
#endif
	zbx_free(error);
}

#if defined(HAVE_GNUTLS)
/******************************************************************************
 *                                                                            *
 * Purpose: basic verification of peer certificate                            *
 *                                                                            *
 * Return value:                                                              *
 *     SUCCEED - verification successful                                      *
 *     FAIL - invalid certificate or an error occurred                        *
 *                                                                            *
 ******************************************************************************/
static int	zbx_verify_peer_cert(const gnutls_session_t session, char **error)
{
	int		res;
	unsigned int	status;
	gnutls_datum_t	status_print;

	if (GNUTLS_E_SUCCESS != (res = gnutls_certificate_verify_peers2(session, &status)))
	{
		*error = zbx_dsprintf(*error, "%s(): gnutls_certificate_verify_peers2() failed: %d %s",
				__func__, res, gnutls_strerror(res));
		return FAIL;
	}

	if (GNUTLS_E_SUCCESS != (res = gnutls_certificate_verification_status_print(status,
			gnutls_certificate_type_get(session), &status_print, 0)))
	{
		*error = zbx_dsprintf(*error, "%s(): gnutls_certificate_verification_status_print() failed: %d"
				" %s", __func__, res, gnutls_strerror(res));
		return FAIL;
	}

	if (0 != status)
		*error = zbx_dsprintf(*error, "invalid peer certificate: %s", status_print.data);

	gnutls_free(status_print.data);

	if (0 == status)
		return SUCCEED;
	else
		return FAIL;
}
#endif

/******************************************************************************
 *                                                                            *
 * Purpose:                                                                   *
 *     verify peer certificate issuer and subject of the given TLS context    *
 *                                                                            *
 * Parameters:                                                                *
 *     tls_ctx      - [IN] TLS context to verify                              *
 *     issuer       - [IN] required issuer (ignore if NULL or empty string)   *
 *     subject      - [IN] required subject (ignore if NULL or empty string)  *
 *     error        - [OUT] dynamically allocated memory with error message   *
 *                                                                            *
 * Return value:                                                              *
 *     SUCCEED or FAIL                                                        *
 *                                                                            *
 ******************************************************************************/
static int	zbx_verify_issuer_subject(const zbx_tls_context_t *tls_ctx, const char *issuer, const char *subject,
		char **error)
{
	char			tls_issuer[HOST_TLS_ISSUER_LEN_MAX], tls_subject[HOST_TLS_SUBJECT_LEN_MAX];
	int			issuer_mismatch = 0, subject_mismatch = 0;
	size_t			error_alloc = 0, error_offset = 0;
#if defined(HAVE_GNUTLS)
	gnutls_x509_crt_t	cert;
	gnutls_x509_dn_t	dn;
	int			res;
#elif defined(HAVE_OPENSSL)
	X509			*cert;
#endif

	if ((NULL == issuer || '\0' == *issuer) && (NULL == subject || '\0' == *subject))
		return SUCCEED;

	tls_issuer[0] = '\0';
	tls_subject[0] = '\0';

#if defined(HAVE_GNUTLS)
	if (NULL == (cert = zbx_get_peer_cert(tls_ctx->ctx, error)))
		return FAIL;

	if (NULL != issuer && '\0' != *issuer)
	{
		if (0 != (res = gnutls_x509_crt_get_issuer(cert, &dn)))
		{
			*error = zbx_dsprintf(*error, "gnutls_x509_crt_get_issuer() failed: %d %s", res,
					gnutls_strerror(res));
			return FAIL;
		}

		if (SUCCEED != zbx_x509_dn_gets(dn, tls_issuer, sizeof(tls_issuer), error))
			return FAIL;
	}

	if (NULL != subject && '\0' != *subject)
	{
		if (0 != (res = gnutls_x509_crt_get_subject(cert, &dn)))
		{
			*error = zbx_dsprintf(*error, "gnutls_x509_crt_get_subject() failed: %d %s", res,
					gnutls_strerror(res));
			return FAIL;
		}

		if (SUCCEED != zbx_x509_dn_gets(dn, tls_subject, sizeof(tls_subject), error))
			return FAIL;
	}

	gnutls_x509_crt_deinit(cert);
#elif defined(HAVE_OPENSSL)
	if (NULL == (cert = SSL_get_peer_certificate(tls_ctx->ctx)))
	{
		*error = zbx_strdup(*error, "cannot obtain peer certificate");
		return FAIL;
	}

	if (NULL != issuer && '\0' != *issuer)
	{
		if (SUCCEED != zbx_x509_dn_gets(X509_get_issuer_name(cert), tls_issuer, sizeof(tls_issuer), error))
			return FAIL;
	}

	if (NULL != subject && '\0' != *subject)
	{
		if (SUCCEED != zbx_x509_dn_gets(X509_get_subject_name(cert), tls_subject, sizeof(tls_subject), error))
			return FAIL;
	}

	X509_free(cert);
#endif
	/* simplified match, not compliant with RFC 4517, 4518 */

	if (NULL != issuer && '\0' != *issuer)
		issuer_mismatch = strcmp(tls_issuer, issuer);

	if (NULL != subject && '\0' != *subject)
		subject_mismatch = strcmp(tls_subject, subject);

	if (0 == issuer_mismatch && 0 == subject_mismatch)
		return SUCCEED;

	if (0 != issuer_mismatch)
	{
		zbx_snprintf_alloc(error, &error_alloc, &error_offset, "issuer: peer: \"%s\", required: \"%s\"",
				tls_issuer, issuer);
	}

	if (0 != subject_mismatch)
	{
		if (0 != issuer_mismatch)
			zbx_strcpy_alloc(error, &error_alloc, &error_offset, ", ");

		zbx_snprintf_alloc(error, &error_alloc, &error_offset, "subject: peer: \"%s\", required: \"%s\"",
				tls_subject, subject);
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose:                                                                   *
 *     check server certificate issuer and subject (for passive proxies and   *
 *     agent passive checks)                                                  *
 *                                                                            *
 * Parameters:                                                                *
 *     sock  - [IN] certificate to verify                                     *
 *     error - [OUT] dynamically allocated memory with error message          *
 *                                                                            *
 * Return value:                                                              *
 *     SUCCEED or FAIL                                                        *
 *                                                                            *
 ******************************************************************************/
int	zbx_check_server_issuer_subject(zbx_socket_t *sock, char **error)
{
	zbx_tls_conn_attr_t	attr;

	if (SUCCEED != zbx_tls_get_attr_cert(sock, &attr))
	{
		THIS_SHOULD_NEVER_HAPPEN;

		*error = zbx_dsprintf(*error, "cannot get connection attributes for connection from %s", sock->peer);
		return FAIL;
	}

	/* simplified match, not compliant with RFC 4517, 4518 */
	if (NULL != CONFIG_TLS_SERVER_CERT_ISSUER && 0 != strcmp(CONFIG_TLS_SERVER_CERT_ISSUER, attr.issuer))
	{
		*error = zbx_dsprintf(*error, "certificate issuer does not match for %s", sock->peer);
		return FAIL;
	}

	/* simplified match, not compliant with RFC 4517, 4518 */
	if (NULL != CONFIG_TLS_SERVER_CERT_SUBJECT && 0 != strcmp(CONFIG_TLS_SERVER_CERT_SUBJECT, attr.subject))
	{
		*error = zbx_dsprintf(*error, "certificate subject does not match for %s", sock->peer);
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize TLS library, log library version                       *
 *                                                                            *
 * Comments:                                                                  *
 *     Some crypto libraries require initialization. On Unix the              *
 *     initialization is done separately in each child process which uses     *
 *     crypto libraries. On MS Windows it is done in the first thread.        *
 *                                                                            *
 *     Flag 'init_done' is used to prevent library deinitialzation on exit if *
 *     it was not yet initialized (can happen if termination signal is        *
 *     received).                                                             *
 *                                                                            *
 ******************************************************************************/
static void	zbx_tls_library_init(void)
{
#if defined(HAVE_GNUTLS)
	if (GNUTLS_E_SUCCESS != gnutls_global_init())
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize GnuTLS library");
		exit(EXIT_FAILURE);
	}

	init_done = 1;

	zabbix_log(LOG_LEVEL_DEBUG, "GnuTLS library (version %s) initialized", gnutls_check_version(NULL));
#elif defined(HAVE_OPENSSL)
#if !defined(LIBRESSL_VERSION_NUMBER)	/* LibreSSL does not require initialization */
	if (1 != zbx_openssl_init_ssl(OPENSSL_INIT_LOAD_SSL_STRINGS | OPENSSL_INIT_LOAD_CRYPTO_STRINGS, NULL))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize OpenSSL library");
		exit(EXIT_FAILURE);
	}
#endif
	init_done = 1;

	zabbix_log(LOG_LEVEL_DEBUG, "OpenSSL library (version %s) initialized", OpenSSL_version(OPENSSL_VERSION));
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: deinitialize TLS library                                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_tls_library_deinit(void)
{
#if defined(HAVE_GNUTLS)
	if (1 == init_done)
	{
		init_done = 0;
		gnutls_global_deinit();
	}
#elif defined(HAVE_OPENSSL)
	if (1 == init_done)
	{
		init_done = 0;
		OPENSSL_cleanup();
	}
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize TLS library in a parent process                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_tls_init_parent(void)
{
#if defined(_WINDOWS)
	zbx_tls_library_init();		/* on MS Windows initialize crypto libraries in parent thread */
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: read available configuration parameters and initialize TLS        *
 *          library in a child process                                        *
 *                                                                            *
 ******************************************************************************/
#if defined(HAVE_GNUTLS)
static void	zbx_gnutls_priority_init_or_exit(gnutls_priority_t *ciphersuites, const char *priority_str,
		const char *err_msg)
{
	const char	*err_pos;
	int		res;

	if (GNUTLS_E_SUCCESS != (res = gnutls_priority_init(ciphersuites, priority_str, &err_pos)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "gnutls_priority_init() for %s failed: %d: %s: error occurred at position:"
				" \"%s\"", err_msg, res, gnutls_strerror(res), ZBX_NULL2STR(err_pos));
		zbx_tls_free();
		exit(EXIT_FAILURE);
	}
}

void	zbx_tls_init_child(void)
{
	int		res;
#ifndef _WINDOWS
	sigset_t	mask, orig_mask;
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

#ifndef _WINDOWS
	/* Invalid TLS parameters will cause exit. Once one process exits the parent process will send SIGHUP to */
	/* child processes which may be on their way to exit on their own - do not interrupt them, block signal */
	/* SIGHUP and unblock it when TLS parameters are good and libraries are initialized. */
	sigemptyset(&mask);
	sigaddset(&mask, SIGTERM);
	sigaddset(&mask, SIGHUP);
	sigaddset(&mask, SIGUSR2);
	sigaddset(&mask, SIGQUIT);
	sigprocmask(SIG_BLOCK, &mask, &orig_mask);

	zbx_tls_library_init();		/* on Unix initialize crypto libraries in child processes */
#endif
	/* need to allocate certificate credentials store? */

	if (NULL != CONFIG_TLS_CERT_FILE)
	{
		if (GNUTLS_E_SUCCESS != (res = gnutls_certificate_allocate_credentials(&my_cert_creds)))
		{
			zabbix_log(LOG_LEVEL_CRIT, "gnutls_certificate_allocate_credentials() failed: %d: %s", res,
					gnutls_strerror(res));
			zbx_tls_free();
			exit(EXIT_FAILURE);
		}
	}

	/* 'TLSCAFile' parameter (in zabbix_server.conf, zabbix_proxy.conf, zabbix_agentd.conf) */
	if (NULL != CONFIG_TLS_CA_FILE)
	{
		if (0 < (res = gnutls_certificate_set_x509_trust_file(my_cert_creds, CONFIG_TLS_CA_FILE,
				GNUTLS_X509_FMT_PEM)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s() loaded %d CA certificate(s) from file \"%s\"",
					__func__, res, CONFIG_TLS_CA_FILE);
		}
		else if (0 == res)
		{
			zabbix_log(LOG_LEVEL_WARNING, "no CA certificate(s) in file \"%s\"", CONFIG_TLS_CA_FILE);
		}
		else
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot parse CA certificate(s) in file \"%s\": %d: %s",
				CONFIG_TLS_CA_FILE, res, gnutls_strerror(res));
			zbx_tls_free();
			exit(EXIT_FAILURE);
		}
	}

	/* 'TLSCRLFile' parameter (in zabbix_server.conf, zabbix_proxy.conf, zabbix_agentd.conf). */
	/* Load CRL (certificate revocation list) file. */
	if (NULL != CONFIG_TLS_CRL_FILE)
	{
		if (0 < (res = gnutls_certificate_set_x509_crl_file(my_cert_creds, CONFIG_TLS_CRL_FILE,
				GNUTLS_X509_FMT_PEM)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s() loaded %d CRL(s) from file \"%s\"", __func__, res,
					CONFIG_TLS_CRL_FILE);
		}
		else if (0 == res)
		{
			zabbix_log(LOG_LEVEL_WARNING, "no CRL(s) in file \"%s\"", CONFIG_TLS_CRL_FILE);
		}
		else
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot parse CRL file \"%s\": %d: %s", CONFIG_TLS_CRL_FILE, res,
					gnutls_strerror(res));
			zbx_tls_free();
			exit(EXIT_FAILURE);
		}
	}

	/* 'TLSCertFile' and 'TLSKeyFile' parameters (in zabbix_server.conf, zabbix_proxy.conf, zabbix_agentd.conf). */
	/* Load certificate and private key. */
	if (NULL != CONFIG_TLS_CERT_FILE)
	{
		if (GNUTLS_E_SUCCESS != (res = gnutls_certificate_set_x509_key_file(my_cert_creds, CONFIG_TLS_CERT_FILE,
				CONFIG_TLS_KEY_FILE, GNUTLS_X509_FMT_PEM)))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot load certificate or private key from file \"%s\" or \"%s\":"
					" %d: %s", CONFIG_TLS_CERT_FILE, CONFIG_TLS_KEY_FILE, res,
					gnutls_strerror(res));
			zbx_tls_free();
			exit(EXIT_FAILURE);
		}
		else
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s() loaded certificate from file \"%s\"", __func__,
					CONFIG_TLS_CERT_FILE);
			zabbix_log(LOG_LEVEL_DEBUG, "%s() loaded private key from file \"%s\"", __func__,
					CONFIG_TLS_KEY_FILE);
		}
	}

	/* 'TLSPSKIdentity' and 'TLSPSKFile' parameters (in zabbix_proxy.conf, zabbix_agentd.conf). */
	/* Load pre-shared key and identity to be used with the pre-shared key. */
	if (NULL != CONFIG_TLS_PSK_FILE)
	{
		gnutls_datum_t	key;

		my_psk_identity = CONFIG_TLS_PSK_IDENTITY;
		my_psk_identity_len = strlen(my_psk_identity);

		zbx_check_psk_identity_len(my_psk_identity_len);

		zbx_read_psk_file();

		key.data = (unsigned char *)my_psk;
		key.size = (unsigned int)my_psk_len;

		/* allocate here only PSK credential stores which do not change (e.g. for proxy communication with */
		/* server) */

		if (0 != (program_type & (ZBX_PROGRAM_TYPE_PROXY_ACTIVE | ZBX_PROGRAM_TYPE_AGENTD |
				ZBX_PROGRAM_TYPE_SENDER | ZBX_PROGRAM_TYPE_GET)))
		{
			if (GNUTLS_E_SUCCESS != (res = gnutls_psk_allocate_client_credentials(&my_psk_client_creds)))
			{
				zabbix_log(LOG_LEVEL_CRIT, "gnutls_psk_allocate_client_credentials() failed: %d: %s",
						res, gnutls_strerror(res));
				zbx_tls_free();
				exit(EXIT_FAILURE);
			}

			/* Simplified. 'my_psk_identity' should have been prepared as required by RFC 4518. */
			if (GNUTLS_E_SUCCESS != (res = gnutls_psk_set_client_credentials(my_psk_client_creds,
					my_psk_identity, &key, GNUTLS_PSK_KEY_RAW)))
			{
				zabbix_log(LOG_LEVEL_CRIT, "gnutls_psk_set_client_credentials() failed: %d: %s", res,
						gnutls_strerror(res));
				zbx_tls_free();
				exit(EXIT_FAILURE);
			}
		}

		if (0 != (program_type & (ZBX_PROGRAM_TYPE_PROXY_PASSIVE | ZBX_PROGRAM_TYPE_AGENTD)))
		{
			if (0 != (res = gnutls_psk_allocate_server_credentials(&my_psk_server_creds)))
			{
				zabbix_log(LOG_LEVEL_CRIT, "gnutls_psk_allocate_server_credentials() failed: %d: %s",
						res, gnutls_strerror(res));
				zbx_tls_free();
				exit(EXIT_FAILURE);
			}

			/* Apparently GnuTLS does not provide API for setting up static server credentials (with a */
			/* fixed PSK identity and key) for a passive proxy and agentd. The only possibility seems to */
			/* be to set up credentials dynamically for each incoming connection using a callback */
			/* function. */
			gnutls_psk_set_server_credentials_function(my_psk_server_creds, zbx_psk_cb);
		}

		zabbix_log(LOG_LEVEL_DEBUG, "%s() loaded PSK identity \"%s\"", __func__, CONFIG_TLS_PSK_IDENTITY);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() loaded PSK from file \"%s\"", __func__, CONFIG_TLS_PSK_FILE);
	}

	/* Certificate always comes from configuration file. Set up ciphersuites. */
	if (NULL != my_cert_creds)
	{
		const char	*priority_str;

		if (NULL == CONFIG_TLS_CIPHER_CERT)
		{
			/* for GnuTLS 3.1.18 and up */
			priority_str = "NONE:+VERS-TLS1.2:+ECDHE-RSA:+RSA:+AES-128-GCM:+AES-128-CBC:+AEAD:+SHA256:"
					"+SHA1:+CURVE-ALL:+COMP-NULL:+SIGN-ALL:+CTYPE-X.509";

			zbx_gnutls_priority_init_or_exit(&ciphersuites_cert, priority_str,
					"\"ciphersuites_cert\" with built-in default value");
		}
		else
		{
			priority_str = CONFIG_TLS_CIPHER_CERT;

			zbx_gnutls_priority_init_or_exit(&ciphersuites_cert, priority_str,
					"\"ciphersuites_cert\" with TLSCipherCert or --tls-cipher parameter");
		}

		zbx_log_ciphersuites(__func__, "certificate", ciphersuites_cert);
	}

	/* PSK can come from configuration file (in proxy, agentd) and later from database (in server, proxy). */
	/* Configure ciphersuites just in case they will be used. */
	if (NULL != my_psk_client_creds || NULL != my_psk_server_creds ||
			0 != (program_type & (ZBX_PROGRAM_TYPE_SERVER | ZBX_PROGRAM_TYPE_PROXY)))
	{
		const char	*priority_str;

		if (NULL == CONFIG_TLS_CIPHER_PSK)
		{
			/* for GnuTLS 3.1.18 and up */
			priority_str = "NONE:+VERS-TLS1.2:+ECDHE-PSK:+PSK:+AES-128-GCM:+AES-128-CBC:+AEAD:+SHA256:"
					"+SHA1:+CURVE-ALL:+COMP-NULL:+SIGN-ALL";

			zbx_gnutls_priority_init_or_exit(&ciphersuites_psk, priority_str,
					"\"ciphersuites_psk\" with built-in default value");
		}
		else
		{
			priority_str = CONFIG_TLS_CIPHER_PSK;

			zbx_gnutls_priority_init_or_exit(&ciphersuites_psk, priority_str,
					"\"ciphersuites_psk\" with TLSCipherPSK or --tls-cipher parameter");
		}

		zbx_log_ciphersuites(__func__, "PSK", ciphersuites_psk);
	}

	/* Sometimes we need to be ready for both certificate and PSK whichever comes in. Set up a combined list of */
	/* ciphersuites. */
	if (NULL != my_cert_creds && (NULL != my_psk_client_creds || NULL != my_psk_server_creds ||
			0 != (program_type & (ZBX_PROGRAM_TYPE_SERVER | ZBX_PROGRAM_TYPE_PROXY))))
	{
		const char	*priority_str;

		if (NULL == CONFIG_TLS_CIPHER_ALL)
		{
			/* for GnuTLS 3.1.18 and up */
			priority_str = "NONE:+VERS-TLS1.2:+ECDHE-RSA:"
				"+RSA:+ECDHE-PSK:+PSK:+AES-128-GCM:+AES-128-CBC:+AEAD:+SHA256:+SHA1:+CURVE-ALL:"
				"+COMP-NULL:+SIGN-ALL:+CTYPE-X.509";

			zbx_gnutls_priority_init_or_exit(&ciphersuites_all, priority_str,
					"\"ciphersuites_all\" with built-in default value");
		}
		else
		{
			priority_str = CONFIG_TLS_CIPHER_ALL;

			zbx_gnutls_priority_init_or_exit(&ciphersuites_all, priority_str,
					"\"ciphersuites_all\" with TLSCipherAll parameter");
		}

		zbx_log_ciphersuites(__func__, "certificate and PSK", ciphersuites_all);
	}

#ifndef _WINDOWS
	sigprocmask(SIG_SETMASK, &orig_mask, NULL);
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
#elif defined(HAVE_OPENSSL)
static const char	*zbx_ctx_name(SSL_CTX *param)
{
	if (ctx_cert == param)
		return "certificate-based encryption";

#if defined(HAVE_OPENSSL_WITH_PSK)
	if (ctx_psk == param)
		return "PSK-based encryption";

	if (ctx_all == param)
		return "certificate and PSK-based encryption";
#endif
	THIS_SHOULD_NEVER_HAPPEN;
	return ZBX_NULL2STR(NULL);
}

static int	zbx_set_ecdhe_parameters(SSL_CTX *ctx)
{
	const char	*msg = "Perfect Forward Secrecy ECDHE ciphersuites will not be available for";
	long		res;
	int		ret = SUCCEED;
#if defined(OPENSSL_VERSION_MAJOR) && OPENSSL_VERSION_NUMBER >= 3	/* OpenSSL 3.0.0 or newer */
	int		grp_list[1] = { NID_X9_62_prime256v1 };	/* use curve secp256r1/prime256v1/NIST P-256 */

	if (1 != (res = SSL_CTX_set1_groups(ctx, grp_list, ARRSIZE(grp_list))))
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s() SSL_CTX_set1_groups() returned %ld. %s %s",
				__func__, res, msg, zbx_ctx_name(ctx));
		ret = FAIL;
	}
#else									/* OpenSSL 1.x.x or LibreSSL */
	EC_KEY	*ecdh;

	/* use curve secp256r1/prime256v1/NIST P-256 */
	if (NULL == (ecdh = EC_KEY_new_by_curve_name(NID_X9_62_prime256v1)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s() EC_KEY_new_by_curve_name() failed. %s %s",
				__func__, msg, zbx_ctx_name(ctx));
		return FAIL;
	}

	SSL_CTX_set_options(ctx, SSL_OP_SINGLE_ECDH_USE);

	if (1 != (res = SSL_CTX_set_tmp_ecdh(ctx, ecdh)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s() SSL_CTX_set_tmp_ecdh() returned %ld. %s %s",
				__func__, res, msg, zbx_ctx_name(ctx));
		ret = FAIL;
	}

	EC_KEY_free(ecdh);
#endif
	return ret;
}

void	zbx_tls_init_child(void)
{
#define ZBX_CIPHERS_CERT_ECDHE		"EECDH+aRSA+AES128:"
#define ZBX_CIPHERS_CERT		"RSA+aRSA+AES128"

#if defined(HAVE_OPENSSL_WITH_PSK)
#if OPENSSL_VERSION_NUMBER >= 0x1010100fL	/* OpenSSL 1.1.1 or newer */
	/* TLS_AES_256_GCM_SHA384 is excluded from client ciphersuite list for PSK based connections. */
	/* By default, in TLS 1.3 only *-SHA256 ciphersuites work with PSK. */
#	define ZBX_CIPHERS_PSK_TLS13	"TLS_CHACHA20_POLY1305_SHA256:TLS_AES_128_GCM_SHA256"
#endif
#if OPENSSL_VERSION_NUMBER >= 0x1010000fL	/* OpenSSL 1.1.0 or newer */
#	define ZBX_CIPHERS_PSK_ECDHE	"kECDHEPSK+AES128:"
#	define ZBX_CIPHERS_PSK		"kPSK+AES128"
#else
#	define ZBX_CIPHERS_PSK_ECDHE	""
#	define ZBX_CIPHERS_PSK		"PSK-AES128-CBC-SHA"
#endif
#endif

	char	*error = NULL;
	size_t	error_alloc = 0, error_offset = 0;
#ifndef _WINDOWS
	sigset_t	mask, orig_mask;
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

#ifndef _WINDOWS
	/* Invalid TLS parameters will cause exit. Once one process exits the parent process will send SIGHUP to */
	/* child processes which may be on their way to exit on their own - do not interrupt them, block signal */
	/* SIGHUP and unblock it when TLS parameters are good and libraries are initialized. */
	sigemptyset(&mask);
	sigaddset(&mask, SIGTERM);
	sigaddset(&mask, SIGHUP);
	sigaddset(&mask, SIGUSR2);
	sigaddset(&mask, SIGQUIT);
	sigprocmask(SIG_BLOCK, &mask, &orig_mask);

	zbx_tls_library_init();		/* on Unix initialize crypto libraries in child processes */
#endif
	if (1 != RAND_status())		/* protect against not properly seeded PRNG */
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize PRNG");
		zbx_tls_free();
		exit(EXIT_FAILURE);
	}

	/* set protocol version to TLS 1.2 */

	if (0 != (program_type & (ZBX_PROGRAM_TYPE_SENDER | ZBX_PROGRAM_TYPE_GET)))
		method = TLS_client_method();
	else	/* ZBX_PROGRAM_TYPE_SERVER | ZBX_PROGRAM_TYPE_PROXY | ZBX_PROGRAM_TYPE_AGENTD */
		method = TLS_method();

	/* create context for certificate-only authentication if certificate is configured */
	if (NULL != CONFIG_TLS_CERT_FILE)
	{
		if (NULL == (ctx_cert = SSL_CTX_new(method)))
			goto out_method;

		if (1 != SSL_CTX_set_min_proto_version(ctx_cert, TLS1_2_VERSION))
			goto out_method;
	}
#if defined(HAVE_OPENSSL_WITH_PSK)
	/* Create context for PSK-only authentication. PSK can come from configuration file (in proxy, agentd) */
	/* and later from database (in server, proxy). */
	if (NULL != CONFIG_TLS_PSK_FILE || 0 != (program_type & (ZBX_PROGRAM_TYPE_SERVER | ZBX_PROGRAM_TYPE_PROXY)))
	{
		if (NULL == (ctx_psk = SSL_CTX_new(method)))
			goto out_method;

		if (1 != SSL_CTX_set_min_proto_version(ctx_psk, TLS1_2_VERSION))
			goto out_method;
	}

	/* Sometimes we need to be ready for both certificate and PSK whichever comes in. Set up a universal context */
	/* for certificate and PSK authentication to prepare for both. */
	if (NULL != ctx_cert && NULL != ctx_psk)
	{
		if (NULL == (ctx_all = SSL_CTX_new(method)))
			goto out_method;

		if (1 != SSL_CTX_set_min_proto_version(ctx_all, TLS1_2_VERSION))
			goto out_method;
	}
#endif
	/* 'TLSCAFile' parameter (in zabbix_server.conf, zabbix_proxy.conf, zabbix_agentd.conf) */
	if (NULL != CONFIG_TLS_CA_FILE)
	{
#if defined(HAVE_OPENSSL_WITH_PSK)
		if (1 != SSL_CTX_load_verify_locations(ctx_cert, CONFIG_TLS_CA_FILE, NULL) ||
				(NULL != ctx_all && 1 != SSL_CTX_load_verify_locations(ctx_all, CONFIG_TLS_CA_FILE,
				NULL)))
#else
		if (1 != SSL_CTX_load_verify_locations(ctx_cert, CONFIG_TLS_CA_FILE, NULL))
#endif
		{
			zbx_snprintf_alloc(&error, &error_alloc, &error_offset, "cannot load CA certificate(s) from"
					" file \"%s\":", CONFIG_TLS_CA_FILE);
			goto out;
		}

		zabbix_log(LOG_LEVEL_DEBUG, "%s() loaded CA certificate(s) from file \"%s\"", __func__,
				CONFIG_TLS_CA_FILE);

		/* It is possible to limit the length of certificate chain being verified. For example: */
		/* SSL_CTX_set_verify_depth(ctx_cert, 2); */
		/* Currently use the default depth 100. */

		SSL_CTX_set_verify(ctx_cert, SSL_VERIFY_PEER | SSL_VERIFY_FAIL_IF_NO_PEER_CERT, NULL);

#if defined(HAVE_OPENSSL_WITH_PSK)
		if (NULL != ctx_all)
			SSL_CTX_set_verify(ctx_all, SSL_VERIFY_PEER | SSL_VERIFY_FAIL_IF_NO_PEER_CERT, NULL);
#endif
	}

	/* 'TLSCRLFile' parameter (in zabbix_server.conf, zabbix_proxy.conf, zabbix_agentd.conf). */
	/* Load CRL (certificate revocation list) file. */
	if (NULL != CONFIG_TLS_CRL_FILE)
	{
		X509_STORE	*store_cert;
		X509_LOOKUP	*lookup_cert;
		int		count_cert;

		store_cert = SSL_CTX_get_cert_store(ctx_cert);

		if (NULL == (lookup_cert = X509_STORE_add_lookup(store_cert, X509_LOOKUP_file())))
		{
			zbx_snprintf_alloc(&error, &error_alloc, &error_offset, "X509_STORE_add_lookup() #%d failed"
					" when loading CRL(s) from file \"%s\":", 1, CONFIG_TLS_CRL_FILE);
			goto out;
		}

		if (0 >= (count_cert = X509_load_crl_file(lookup_cert, CONFIG_TLS_CRL_FILE, X509_FILETYPE_PEM)))
		{
			zbx_snprintf_alloc(&error, &error_alloc, &error_offset, "cannot load CRL(s) from file \"%s\":",
					CONFIG_TLS_CRL_FILE);
			goto out;
		}

		if (1 != X509_STORE_set_flags(store_cert, X509_V_FLAG_CRL_CHECK | X509_V_FLAG_CRL_CHECK_ALL))
		{
			zbx_snprintf_alloc(&error, &error_alloc, &error_offset, "X509_STORE_set_flags() #%d failed when"
					" loading CRL(s) from file \"%s\":", 1, CONFIG_TLS_CRL_FILE);
			goto out;
		}
#if defined(HAVE_OPENSSL_WITH_PSK)
		if (NULL != ctx_all)
		{
			X509_STORE	*store_all;
			X509_LOOKUP	*lookup_all;
			int		count_all;

			store_all = SSL_CTX_get_cert_store(ctx_all);

			if (NULL == (lookup_all = X509_STORE_add_lookup(store_all, X509_LOOKUP_file())))
			{
				zbx_snprintf_alloc(&error, &error_alloc, &error_offset, "X509_STORE_add_lookup() #%d"
						" failed when loading CRL(s) from file \"%s\":", 2,
						CONFIG_TLS_CRL_FILE);
				goto out;
			}

			if (0 >= (count_all = X509_load_crl_file(lookup_all, CONFIG_TLS_CRL_FILE, X509_FILETYPE_PEM)))
			{
				zbx_snprintf_alloc(&error, &error_alloc, &error_offset, "cannot load CRL(s) from file"
						" \"%s\":", CONFIG_TLS_CRL_FILE);
				goto out;
			}

			if (count_cert != count_all)
			{
				zbx_snprintf_alloc(&error, &error_alloc, &error_offset, "number of CRL(s) loaded from"
						" file \"%s\" does not match: %d and %d", CONFIG_TLS_CRL_FILE,
						count_cert, count_all);
				goto out1;
			}

			if (1 != X509_STORE_set_flags(store_all, X509_V_FLAG_CRL_CHECK | X509_V_FLAG_CRL_CHECK_ALL))
			{
				zbx_snprintf_alloc(&error, &error_alloc, &error_offset, "X509_STORE_set_flags() #%d"
						" failed when loading CRL(s) from file \"%s\":", 2,
						CONFIG_TLS_CRL_FILE);
				goto out;
			}
		}
#endif
		zabbix_log(LOG_LEVEL_DEBUG, "%s() loaded %d CRL(s) from file \"%s\"", __func__, count_cert,
				CONFIG_TLS_CRL_FILE);
	}

	/* 'TLSCertFile' parameter (in zabbix_server.conf, zabbix_proxy.conf, zabbix_agentd.conf). */
	/* Load certificate. */
	if (NULL != CONFIG_TLS_CERT_FILE)
	{
#if defined(HAVE_OPENSSL_WITH_PSK)
		if (1 != SSL_CTX_use_certificate_chain_file(ctx_cert, CONFIG_TLS_CERT_FILE) || (NULL != ctx_all &&
				1 != SSL_CTX_use_certificate_chain_file(ctx_all, CONFIG_TLS_CERT_FILE)))
#else
		if (1 != SSL_CTX_use_certificate_chain_file(ctx_cert, CONFIG_TLS_CERT_FILE))
#endif
		{
			zbx_snprintf_alloc(&error, &error_alloc, &error_offset, "cannot load certificate(s) from file"
					" \"%s\":", CONFIG_TLS_CERT_FILE);
			goto out;
		}

		zabbix_log(LOG_LEVEL_DEBUG, "%s() loaded certificate(s) from file \"%s\"", __func__,
				CONFIG_TLS_CERT_FILE);
	}

	/* 'TLSKeyFile' parameter (in zabbix_server.conf, zabbix_proxy.conf, zabbix_agentd.conf). */
	/* Load private key. */
	if (NULL != CONFIG_TLS_KEY_FILE)
	{
#if defined(HAVE_OPENSSL_WITH_PSK)
		if (1 != SSL_CTX_use_PrivateKey_file(ctx_cert, CONFIG_TLS_KEY_FILE, SSL_FILETYPE_PEM) ||
				(NULL != ctx_all && 1 != SSL_CTX_use_PrivateKey_file(ctx_all, CONFIG_TLS_KEY_FILE,
				SSL_FILETYPE_PEM)))
#else
		if (1 != SSL_CTX_use_PrivateKey_file(ctx_cert, CONFIG_TLS_KEY_FILE, SSL_FILETYPE_PEM))
#endif
		{
			zbx_snprintf_alloc(&error, &error_alloc, &error_offset, "cannot load private key from file"
					" \"%s\":", CONFIG_TLS_KEY_FILE);
			goto out;
		}

		zabbix_log(LOG_LEVEL_DEBUG, "%s() loaded private key from file \"%s\"", __func__, CONFIG_TLS_KEY_FILE);

		if (1 != SSL_CTX_check_private_key(ctx_cert))
		{
			zbx_snprintf_alloc(&error, &error_alloc, &error_offset, "certificate and private key do not"
					" match:");
			goto out;
		}
	}

	/* 'TLSPSKIdentity' and 'TLSPSKFile' parameters (in zabbix_proxy.conf, zabbix_agentd.conf). */
	/*  Load pre-shared key and identity to be used with the pre-shared key. */
	if (NULL != CONFIG_TLS_PSK_FILE)
	{
		my_psk_identity = CONFIG_TLS_PSK_IDENTITY;
		my_psk_identity_len = strlen(my_psk_identity);

		zbx_check_psk_identity_len(my_psk_identity_len);

		zabbix_log(LOG_LEVEL_DEBUG, "%s() loaded PSK identity \"%s\"", __func__, CONFIG_TLS_PSK_IDENTITY);

		zbx_read_psk_file();

		zabbix_log(LOG_LEVEL_DEBUG, "%s() loaded PSK from file \"%s\"", __func__, CONFIG_TLS_PSK_FILE);
	}
#if defined(HAVE_OPENSSL_WITH_PSK)
	/* set up PSK global variables for client callback if PSK comes only from configuration file or command line */

	if (NULL != ctx_psk && 0 != (program_type & (ZBX_PROGRAM_TYPE_AGENTD | ZBX_PROGRAM_TYPE_SENDER |
			ZBX_PROGRAM_TYPE_GET)))
	{
		psk_identity_for_cb = my_psk_identity;
		psk_identity_len_for_cb = my_psk_identity_len;
		psk_for_cb = my_psk;
		psk_len_for_cb = my_psk_len;
	}
#endif
	if (NULL != ctx_cert)
	{
		const char	*ciphers;

		SSL_CTX_set_info_callback(ctx_cert, zbx_openssl_info_cb);

		/* we're using blocking sockets, deal with renegotiations automatically */
		SSL_CTX_set_mode(ctx_cert, SSL_MODE_AUTO_RETRY);

		/* use server ciphersuite preference, do not use RFC 4507 ticket extension */
		SSL_CTX_set_options(ctx_cert, SSL_OP_CIPHER_SERVER_PREFERENCE | SSL_OP_NO_TICKET);

		/* do not connect to unpatched servers */
		SSL_CTX_clear_options(ctx_cert, SSL_OP_LEGACY_SERVER_CONNECT);

		/* disable session caching */
		SSL_CTX_set_session_cache_mode(ctx_cert, SSL_SESS_CACHE_OFF);

		/* try to enable ECDH ciphersuites */
		if (SUCCEED == zbx_set_ecdhe_parameters(ctx_cert))
			ciphers = ZBX_CIPHERS_CERT_ECDHE ZBX_CIPHERS_CERT;
		else
			ciphers = ZBX_CIPHERS_CERT;

		/* override TLS 1.3 certificate ciphersuites with user-defined settings */
		if (NULL != CONFIG_TLS_CIPHER_CERT13 || NULL != CONFIG_TLS_CIPHER_CMD13)
		{
#if OPENSSL_VERSION_NUMBER >= 0x1010100fL && !defined(LIBRESSL_VERSION_NUMBER)	/* only OpenSSL 1.1.1 or newer */
			const char	*override_ciphers = CONFIG_TLS_CIPHER_CERT13;	/* can be NULL */

			if (NULL != CONFIG_TLS_CIPHER_CMD13 && ZBX_TCP_SEC_TLS_CERT == configured_tls_connect_mode)
				override_ciphers = CONFIG_TLS_CIPHER_CMD13;

			if (NULL != override_ciphers && 1 != SSL_CTX_set_ciphersuites(ctx_cert, override_ciphers))
			{
				zbx_snprintf_alloc(&error, &error_alloc, &error_offset, "cannot set list of TLS 1.3"
						" certificate ciphersuites from \"TLSCipherCert13\" or"
						" \"--tls-cipher13\" parameter:");
				goto out;
			}
#else
			zbx_snprintf_alloc(&error, &error_alloc, &error_offset, "cannot set list of TLS 1.3"
					" certificate ciphersuites: compiled with OpenSSL version older than 1.1.1 or"
					" with LibreSSL. Consider not using parameters \"TLSCipherCert13\" or"
					" \"--tls-cipher13\"");
			goto out1;
#endif
		}

		/* override TLS 1.2 certificate ciphersuites with user-defined settings */
		if (NULL != CONFIG_TLS_CIPHER_CERT || NULL != CONFIG_TLS_CIPHER_CMD)
		{
			const char	*override_ciphers = CONFIG_TLS_CIPHER_CERT;	/* can be NULL */

			if (NULL != CONFIG_TLS_CIPHER_CMD && ZBX_TCP_SEC_TLS_CERT == configured_tls_connect_mode)
				override_ciphers = CONFIG_TLS_CIPHER_CMD;

			if (NULL != override_ciphers && 1 != SSL_CTX_set_cipher_list(ctx_cert, override_ciphers))
			{
				zbx_snprintf_alloc(&error, &error_alloc, &error_offset, "cannot set list of TLS 1.2"
						" certificate ciphersuites from \"TLSCipherCert\" or \"--tls-cipher\""
						" parameter:");
				goto out;
			}
		}
		else if (1 != SSL_CTX_set_cipher_list(ctx_cert, ciphers))
		{
			zbx_snprintf_alloc(&error, &error_alloc, &error_offset, "cannot set list of certificate"
					" ciphersuites:");
			goto out;
		}

		zbx_log_ciphersuites(__func__, "certificate", ctx_cert);
	}
#if defined(HAVE_OPENSSL_WITH_PSK)
	if (NULL != ctx_psk)
	{
		const char	*ciphers;

		SSL_CTX_set_info_callback(ctx_psk, zbx_openssl_info_cb);

		if (0 != (program_type & (ZBX_PROGRAM_TYPE_SERVER | ZBX_PROGRAM_TYPE_PROXY | ZBX_PROGRAM_TYPE_AGENTD |
				ZBX_PROGRAM_TYPE_SENDER | ZBX_PROGRAM_TYPE_GET)))
		{
			SSL_CTX_set_psk_client_callback(ctx_psk, zbx_psk_client_cb);
		}

		if (0 != (program_type & (ZBX_PROGRAM_TYPE_SERVER | ZBX_PROGRAM_TYPE_PROXY | ZBX_PROGRAM_TYPE_AGENTD)))
			SSL_CTX_set_psk_server_callback(ctx_psk, zbx_psk_server_cb);

		SSL_CTX_set_mode(ctx_psk, SSL_MODE_AUTO_RETRY);
		SSL_CTX_set_options(ctx_psk, SSL_OP_CIPHER_SERVER_PREFERENCE | SSL_OP_NO_TICKET);
		SSL_CTX_clear_options(ctx_psk, SSL_OP_LEGACY_SERVER_CONNECT);
		SSL_CTX_set_session_cache_mode(ctx_psk, SSL_SESS_CACHE_OFF);

		if ('\0' != *ZBX_CIPHERS_PSK_ECDHE && SUCCEED == zbx_set_ecdhe_parameters(ctx_psk))
			ciphers = ZBX_CIPHERS_PSK_ECDHE ZBX_CIPHERS_PSK;
		else
			ciphers = ZBX_CIPHERS_PSK;

		/* override TLS 1.3 PSK ciphersuites with user-defined settings */
		if (NULL != CONFIG_TLS_CIPHER_PSK13 || NULL != CONFIG_TLS_CIPHER_CMD13)
		{
#if OPENSSL_VERSION_NUMBER >= 0x1010100fL	/* OpenSSL 1.1.1 or newer */
			const char	*override_ciphers = CONFIG_TLS_CIPHER_PSK13;	/* can be NULL */

			if (NULL != CONFIG_TLS_CIPHER_CMD13 && ZBX_TCP_SEC_TLS_PSK == configured_tls_connect_mode)
				override_ciphers = CONFIG_TLS_CIPHER_CMD13;

			if (NULL != override_ciphers && 1 != SSL_CTX_set_ciphersuites(ctx_psk, override_ciphers))
			{
				zbx_snprintf_alloc(&error, &error_alloc, &error_offset, "cannot set list of TLS 1.3"
						" PSK ciphersuites from \"TLSCipherPSK13\" or \"--tls-cipher13\""
						" parameter:");
				goto out;
			}
#else
			zbx_snprintf_alloc(&error, &error_alloc, &error_offset, "cannot set list of TLS 1.3"
					" PSK ciphersuites: compiled with OpenSSL version older than 1.1.1."
					" Consider not using parameters \"TLSCipherPSK13\" or \"--tls-cipher13\"");
			goto out1;
#endif
		}
#if OPENSSL_VERSION_NUMBER >= 0x1010100fL	/* OpenSSL 1.1.1 or newer */
		else if (1 != SSL_CTX_set_ciphersuites(ctx_psk, ZBX_CIPHERS_PSK_TLS13))
		{
			zbx_snprintf_alloc(&error, &error_alloc, &error_offset, "cannot set list of PSK TLS 1.3"
					"  ciphersuites:");
			goto out;
		}
#endif
		/* override TLS 1.2 PSK ciphersuites with user-defined settings */
		if (NULL != CONFIG_TLS_CIPHER_PSK || NULL != CONFIG_TLS_CIPHER_CMD)
		{
			const char	*override_ciphers = CONFIG_TLS_CIPHER_PSK;	/* can be NULL */

			if (NULL != CONFIG_TLS_CIPHER_CMD && ZBX_TCP_SEC_TLS_PSK == configured_tls_connect_mode)
				override_ciphers = CONFIG_TLS_CIPHER_CMD;

			if (NULL != override_ciphers && 1 != SSL_CTX_set_cipher_list(ctx_psk, override_ciphers))
			{
				zbx_snprintf_alloc(&error, &error_alloc, &error_offset, "cannot set list of TLS 1.2"
						" PSK ciphersuites from \"TLSCipherPSK\" or \"--tls-cipher\""
						" parameter:");
				goto out;
			}
		}
		else if (1 != SSL_CTX_set_cipher_list(ctx_psk, ciphers))
		{
			zbx_snprintf_alloc(&error, &error_alloc, &error_offset, "cannot set list of PSK ciphersuites:");
			goto out;
		}

		zbx_log_ciphersuites(__func__, "PSK", ctx_psk);
	}

	if (NULL != ctx_all)
	{
		const char	*ciphers;

		SSL_CTX_set_info_callback(ctx_all, zbx_openssl_info_cb);

		if (0 != (program_type & (ZBX_PROGRAM_TYPE_SERVER | ZBX_PROGRAM_TYPE_PROXY | ZBX_PROGRAM_TYPE_AGENTD)))
			SSL_CTX_set_psk_server_callback(ctx_all, zbx_psk_server_cb);

		SSL_CTX_set_mode(ctx_all, SSL_MODE_AUTO_RETRY);
		SSL_CTX_set_options(ctx_all, SSL_OP_CIPHER_SERVER_PREFERENCE | SSL_OP_NO_TICKET);
		SSL_CTX_clear_options(ctx_all, SSL_OP_LEGACY_SERVER_CONNECT);
		SSL_CTX_set_session_cache_mode(ctx_all, SSL_SESS_CACHE_OFF);

		if (SUCCEED == zbx_set_ecdhe_parameters(ctx_all))
			ciphers = ZBX_CIPHERS_CERT_ECDHE ZBX_CIPHERS_CERT ":" ZBX_CIPHERS_PSK_ECDHE ZBX_CIPHERS_PSK;
		else
			ciphers = ZBX_CIPHERS_CERT ":" ZBX_CIPHERS_PSK;

		/* override TLS 1.3 ciphersuites with user-defined setting */
		if (NULL != CONFIG_TLS_CIPHER_ALL13)
		{
#if OPENSSL_VERSION_NUMBER >= 0x1010100fL	/* OpenSSL 1.1.1 or newer */
			if (1 != SSL_CTX_set_ciphersuites(ctx_all, CONFIG_TLS_CIPHER_ALL13))
			{
				zbx_snprintf_alloc(&error, &error_alloc, &error_offset, "cannot set list of TLS 1.3"
						" ciphersuites from \"TLSCipherAll13\" parameter:");
				goto out;
			}
#else
			zbx_snprintf_alloc(&error, &error_alloc, &error_offset, "cannot set list of TLS 1.3"
					" ciphersuites: compiled with OpenSSL version older than 1.1.1."
					" Consider not using parameter \"TLSCipherAll13\"");
			goto out1;
#endif
		}

		/* override TLS 1.2 ciphersuites with user-defined setting */
		if (NULL != CONFIG_TLS_CIPHER_ALL)
		{
			if (1 != SSL_CTX_set_cipher_list(ctx_all, CONFIG_TLS_CIPHER_ALL))
			{
				zbx_snprintf_alloc(&error, &error_alloc, &error_offset, "cannot set list of TLS 1.2"
						" ciphersuites from \"TLSCipherAll\" parameter:");
				goto out;
			}
		}
		else if (1 != SSL_CTX_set_cipher_list(ctx_all, ciphers))
		{
			zbx_snprintf_alloc(&error, &error_alloc, &error_offset, "cannot set list of all ciphersuites:");
			goto out;
		}

		zbx_log_ciphersuites(__func__, "certificate and PSK", ctx_all);
	}

	if (NULL == ctx_psk)
	{
		/* cannot override TLS 1.3 PSK ciphersuites */
		if (NULL != CONFIG_TLS_CIPHER_PSK13)
		{
#if OPENSSL_VERSION_NUMBER >= 0x1010100fL	/* OpenSSL 1.1.1 or newer */
			zbx_snprintf_alloc(&error, &error_alloc, &error_offset, "parameter \"TLSCipherPSK13\" cannot"
					" be applied: the list of PSK ciphersuites is not used");
#else
			zbx_snprintf_alloc(&error, &error_alloc, &error_offset, "parameter \"TLSCipherPSK13\" cannot"
					" be applied: compiled with OpenSSL version older than 1.1.1");
#endif
			goto out1;
		}

		/* cannot override TLS 1.2 PSK ciphersuites */
		if (NULL != CONFIG_TLS_CIPHER_PSK)
		{
			zbx_snprintf_alloc(&error, &error_alloc, &error_offset, "parameter \"TLSCipherPSK\" cannot"
					" be applied: the list of PSK ciphersuites is not used");
			goto out1;
		}
	}

	if (NULL == ctx_all)
	{
		/* cannot override TLS 1.3 ciphersuites */
		if (NULL != CONFIG_TLS_CIPHER_ALL13)
		{
#if OPENSSL_VERSION_NUMBER >= 0x1010100fL	/* OpenSSL 1.1.1 or newer */
			zbx_snprintf_alloc(&error, &error_alloc, &error_offset, "parameter \"TLSCipherAll13\" cannot"
					" be applied: the combined list of certificate and PSK ciphersuites is"
					" not used. Most likely parameters \"TLSCipherCert13\" and/or \"TLSCipherPSK13\""
					" are sufficient");
#else
			zbx_snprintf_alloc(&error, &error_alloc, &error_offset, "parameter \"TLSCipherAll13\" cannot"
					" be applied: compiled with OpenSSL version older than 1.1.1");
#endif
			goto out1;
		}

		/* cannot override TLS 1.2 ciphersuites */
		if (NULL != CONFIG_TLS_CIPHER_ALL)
		{
			zbx_snprintf_alloc(&error, &error_alloc, &error_offset, "parameter \"TLSCipherAll\" cannot"
					" be applied: the combined list of certificate and PSK ciphersuites is"
					" not used. Most likely parameters \"TLSCipherCert\" and/or \"TLSCipherPSK\""
					" are sufficient");
			goto out1;
		}
	}
#else	/* HAVE_OPENSSL_WITH_PSK is not defined */
	/* cannot use TLSCipherPSK13, TLSCipherPSK, TLSCipherAll13 and TLSCipherAll13 parameters */
	/* if PSK is not supported by crypto library */
	if (NULL != CONFIG_TLS_CIPHER_PSK13 || NULL != CONFIG_TLS_CIPHER_PSK ||
			NULL != CONFIG_TLS_CIPHER_ALL13 || NULL != CONFIG_TLS_CIPHER_ALL)
	{
		zbx_snprintf_alloc(&error, &error_alloc, &error_offset, "at least one of parameters TLSCipherPSK13,"
				" TLSCipherPSK, TLSCipherAll13 or TLSCipherAll is defined. These parameters must not"
				" be defined because the program is compiled with OpenSSL without PSK support or"
				" LibreSSL");
		goto out1;
	}
#endif /* defined(HAVE_OPENSSL_WITH_PSK) */
#ifndef _WINDOWS
	sigprocmask(SIG_SETMASK, &orig_mask, NULL);
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return;

out_method:
	zbx_snprintf_alloc(&error, &error_alloc, &error_offset, "cannot initialize TLS method:");
out:
	zbx_tls_error_msg(&error, &error_alloc, &error_offset);
out1:
	zabbix_log(LOG_LEVEL_CRIT, "%s", error);
	zbx_free(error);
	zbx_tls_free();
	exit(EXIT_FAILURE);

#undef ZBX_CIPHERS_CERT_ECDHE
#undef ZBX_CIPHERS_CERT
#undef ZBX_CIPHERS_PSK_ECDHE
#undef ZBX_CIPHERS_PSK
#undef ZBX_CIPHERS_PSK_TLS13
}
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: TLS cleanup for using in signal handlers                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_tls_free_on_signal(void)
{
	if (NULL != my_psk)
		zbx_guaranteed_memset(my_psk, 0, my_psk_len);
}

/******************************************************************************
 *                                                                            *
 * Purpose: release TLS library resources allocated in zbx_tls_init_parent()  *
 *          and zbx_tls_init_child()                                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_tls_free(void)
{
#if defined(HAVE_GNUTLS)
	if (NULL != my_cert_creds)
	{
		gnutls_certificate_free_credentials(my_cert_creds);
		my_cert_creds = NULL;
	}

	if (NULL != my_psk_client_creds)
	{
		gnutls_psk_free_client_credentials(my_psk_client_creds);
		my_psk_client_creds = NULL;
	}

	if (NULL != my_psk_server_creds)
	{
		gnutls_psk_free_server_credentials(my_psk_server_creds);
		my_psk_server_creds = NULL;
	}

	/* In GnuTLS versions 2.8.x (RHEL 6 uses v.2.8.5 ?) gnutls_priority_init() in case of error does not release */
	/* memory allocated for 'ciphersuites_psk' but releasing it by gnutls_priority_deinit() causes crash. In     */
	/* GnuTLS versions 3.0.x - 3.1.x (RHEL 7 uses v.3.1.18 ?) gnutls_priority_init() in case of error does       */
	/* release memory allocated for 'ciphersuites_psk' but does not set pointer to NULL. Newer GnuTLS versions   */
	/* (e.g. 3.3.8) in case of error in gnutls_priority_init() do release memory and set pointer to NULL.        */
	/* Therefore we cannot reliably release this memory using the pointer. So, we leave the memory to be cleaned */
	/* up by OS - we are in the process of exiting and the data is not secret. */

	/* do not release 'ciphersuites_cert', 'ciphersuites_psk' and 'ciphersuites_all' here using */
	/* gnutls_priority_deinit() */

	if (NULL != my_psk)
	{
		zbx_guaranteed_memset(my_psk, 0, my_psk_len);
		my_psk_len = 0;
		zbx_free(my_psk);
	}

#if !defined(_WINDOWS)
	zbx_tls_library_deinit();
#endif
#elif defined(HAVE_OPENSSL)
	if (NULL != ctx_cert)
		SSL_CTX_free(ctx_cert);

#if defined(HAVE_OPENSSL_WITH_PSK)
	if (NULL != ctx_psk)
		SSL_CTX_free(ctx_psk);

	if (NULL != ctx_all)
		SSL_CTX_free(ctx_all);
#endif
	if (NULL != my_psk)
	{
		zbx_guaranteed_memset(my_psk, 0, my_psk_len);
		my_psk_len = 0;
		zbx_free(my_psk);
	}

#if !defined(_WINDOWS)
	zbx_tls_library_deinit();
#endif
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: establish a TLS connection over an established TCP connection     *
 *                                                                            *
 * Parameters:                                                                *
 *     s           - [IN] socket with opened connection                       *
 *     error       - [OUT] dynamically allocated memory with error message    *
 *     tls_connect - [IN] how to connect. Allowed values:                     *
 *                        ZBX_TCP_SEC_TLS_CERT, ZBX_TCP_SEC_TLS_PSK.          *
 *     tls_arg1    - [IN] required issuer of peer certificate (may be NULL    *
 *                        or empty string if not important) or PSK identity   *
 *                        to connect with depending on value of               *
 *                        'tls_connect'.                                      *
 *     tls_arg2    - [IN] required subject of peer certificate (may be NULL   *
 *                        or empty string if not important) or PSK            *
 *                        (in hex-string) to connect with depending on value  *
 *                        of 'tls_connect'.                                   *
 *     server_name - [IN] optional server name indication for TLS             *
 *                                                                            *
 * Return value:                                                              *
 *     SUCCEED - successful TLS handshake with a valid certificate or PSK     *
 *     FAIL - an error occurred                                               *
 *                                                                            *
 ******************************************************************************/
#if defined(HAVE_GNUTLS)
int	zbx_tls_connect(zbx_socket_t *s, unsigned int tls_connect, const char *tls_arg1, const char *tls_arg2,
		const char *server_name, char **error)
{
	int	ret = FAIL, res;
#if defined(_WINDOWS)
	double	sec;
#endif

	if (ZBX_TCP_SEC_TLS_CERT == tls_connect)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "In %s(): issuer:\"%s\" subject:\"%s\"", __func__,
				ZBX_NULL2EMPTY_STR(tls_arg1), ZBX_NULL2EMPTY_STR(tls_arg2));
	}
	else if (ZBX_TCP_SEC_TLS_PSK == tls_connect)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "In %s(): psk_identity:\"%s\"", __func__, ZBX_NULL2EMPTY_STR(tls_arg1));
	}
	else
	{
		*error = zbx_strdup(*error, "invalid connection parameters");
		THIS_SHOULD_NEVER_HAPPEN;
		goto out1;
	}

	/* set up TLS context */

	s->tls_ctx = zbx_malloc(s->tls_ctx, sizeof(zbx_tls_context_t));
	s->tls_ctx->ctx = NULL;
	s->tls_ctx->psk_client_creds = NULL;
	s->tls_ctx->psk_server_creds = NULL;

	if (GNUTLS_E_SUCCESS != (res = gnutls_init(&s->tls_ctx->ctx, GNUTLS_CLIENT | GNUTLS_NO_EXTENSIONS)))
			/* GNUTLS_NO_EXTENSIONS is used because we do not currently support extensions (e.g. session */
			/* tickets and OCSP) */
	{
		*error = zbx_dsprintf(*error, "gnutls_init() failed: %d %s", res, gnutls_strerror(res));
		goto out;
	}

	if (ZBX_TCP_SEC_TLS_CERT == tls_connect)
	{
		if (NULL == ciphersuites_cert)
		{
			*error = zbx_strdup(*error, "cannot connect with TLS and certificate: no valid certificate"
					" loaded");
			goto out;
		}

		if (GNUTLS_E_SUCCESS != (res = gnutls_priority_set(s->tls_ctx->ctx, ciphersuites_cert)))
		{
			*error = zbx_dsprintf(*error, "gnutls_priority_set() for 'ciphersuites_cert' failed: %d %s",
					res, gnutls_strerror(res));
			goto out;
		}

		if (GNUTLS_E_SUCCESS != (res = gnutls_credentials_set(s->tls_ctx->ctx, GNUTLS_CRD_CERTIFICATE,
				my_cert_creds)))
		{
			*error = zbx_dsprintf(*error, "gnutls_credentials_set() for certificate failed: %d %s", res,
					gnutls_strerror(res));
			goto out;
		}
	}
	else	/* use a pre-shared key */
	{
		if (NULL == ciphersuites_psk)
		{
			*error = zbx_strdup(*error, "cannot connect with TLS and PSK: no valid PSK loaded");
			goto out;
		}

		if (GNUTLS_E_SUCCESS != (res = gnutls_priority_set(s->tls_ctx->ctx, ciphersuites_psk)))
		{
			*error = zbx_dsprintf(*error, "gnutls_priority_set() for 'ciphersuites_psk' failed: %d %s", res,
					gnutls_strerror(res));
			goto out;
		}

		if (NULL == tls_arg2)	/* PSK is not set from DB */
		{
			/* set up the PSK from a configuration file (always in agentd and a case in active proxy */
			/* when it connects to server) */

			if (GNUTLS_E_SUCCESS != (res = gnutls_credentials_set(s->tls_ctx->ctx, GNUTLS_CRD_PSK,
					my_psk_client_creds)))
			{
				*error = zbx_dsprintf(*error, "gnutls_credentials_set() for psk failed: %d %s", res,
						gnutls_strerror(res));
				goto out;
			}
		}
		else
		{
			/* PSK comes from a database (case for a server/proxy when it connects to an agent for */
			/* passive checks, for a server when it connects to a passive proxy) */

			gnutls_datum_t	key;
			int		psk_len;
			unsigned char	psk_buf[HOST_TLS_PSK_LEN / 2];

			if (0 >= (psk_len = zbx_hex2bin((const unsigned char *)tls_arg2, psk_buf, sizeof(psk_buf))))
			{
				*error = zbx_strdup(*error, "invalid PSK");
				goto out;
			}

			if (GNUTLS_E_SUCCESS != (res = gnutls_psk_allocate_client_credentials(
					&s->tls_ctx->psk_client_creds)))
			{
				*error = zbx_dsprintf(*error, "gnutls_psk_allocate_client_credentials() failed: %d %s",
						res, gnutls_strerror(res));
				goto out;
			}

			key.data = psk_buf;
			key.size = (unsigned int)psk_len;

			/* Simplified. 'tls_arg1' (PSK identity) should have been prepared as required by RFC 4518. */
			if (GNUTLS_E_SUCCESS != (res = gnutls_psk_set_client_credentials(s->tls_ctx->psk_client_creds,
					tls_arg1, &key, GNUTLS_PSK_KEY_RAW)))
			{
				*error = zbx_dsprintf(*error, "gnutls_psk_set_client_credentials() failed: %d %s", res,
						gnutls_strerror(res));
				goto out;
			}

			if (GNUTLS_E_SUCCESS != (res = gnutls_credentials_set(s->tls_ctx->ctx, GNUTLS_CRD_PSK,
					s->tls_ctx->psk_client_creds)))
			{
				*error = zbx_dsprintf(*error, "gnutls_credentials_set() for psk failed: %d %s", res,
						gnutls_strerror(res));
				goto out;
			}
		}
	}

	if (NULL != server_name && ZBX_TCP_SEC_UNENCRYPTED != tls_connect && GNUTLS_E_SUCCESS != gnutls_server_name_set(
			s->tls_ctx->ctx, GNUTLS_NAME_DNS, server_name, strlen(server_name)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot set %s tls host name", server_name);
	}

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_TRACE))
	{
		/* set our own debug callback function */
		gnutls_global_set_log_function(zbx_gnutls_debug_cb);

		/* for Zabbix LOG_LEVEL_TRACE, GnuTLS debug level 4 seems the best */
		/* (the highest GnuTLS debug level is 9) */
		gnutls_global_set_log_level(4);
	}
	else
		gnutls_global_set_log_level(0);		/* restore default log level */

	/* set our own callback function to log issues into Zabbix log */
	gnutls_global_set_audit_log_function(zbx_gnutls_audit_cb);

	gnutls_transport_set_int(s->tls_ctx->ctx, ZBX_SOCKET_TO_INT(s->socket));

	/* TLS handshake */

#if defined(_WINDOWS)
	zbx_alarm_flag_clear();
	sec = zbx_time();
#endif
	while (GNUTLS_E_SUCCESS != (res = gnutls_handshake(s->tls_ctx->ctx)))
	{
#if defined(_WINDOWS)
		if (s->timeout < zbx_time() - sec)
			zbx_alarm_flag_set();
#endif
		if (SUCCEED == zbx_alarm_timed_out())
		{
			*error = zbx_strdup(*error, "gnutls_handshake() timed out");
			goto out;
		}

		if (GNUTLS_E_INTERRUPTED == res || GNUTLS_E_AGAIN == res)
		{
			continue;
		}
		else if (GNUTLS_E_WARNING_ALERT_RECEIVED == res || GNUTLS_E_FATAL_ALERT_RECEIVED == res)
		{
			const char	*msg;
			int		alert;

			/* server sent an alert to us */
			alert = gnutls_alert_get(s->tls_ctx->ctx);

			if (NULL == (msg = gnutls_alert_get_name(alert)))
				msg = "unknown";

			if (GNUTLS_E_WARNING_ALERT_RECEIVED == res)
			{
				zabbix_log(LOG_LEVEL_WARNING, "%s() gnutls_handshake() received a warning alert: %d %s",
						__func__, alert, msg);
				continue;
			}
			else	/* GNUTLS_E_FATAL_ALERT_RECEIVED */
			{
				*error = zbx_dsprintf(*error, "%s(): gnutls_handshake() failed with fatal alert: %d %s",
						__func__, alert, msg);
				goto out;
			}
		}
		else
		{
			int	level;

			/* log "peer has closed connection" case with debug level */
			level = (GNUTLS_E_PREMATURE_TERMINATION == res ? LOG_LEVEL_DEBUG : LOG_LEVEL_WARNING);

			if (SUCCEED == ZBX_CHECK_LOG_LEVEL(level))
			{
				zabbix_log(level, "%s() gnutls_handshake() returned: %d %s",
						__func__, res, gnutls_strerror(res));
			}

			if (0 != gnutls_error_is_fatal(res))
			{
				*error = zbx_dsprintf(*error, "%s(): gnutls_handshake() failed: %d %s",
						__func__, res, gnutls_strerror(res));
				goto out;
			}
		}
	}

	if (ZBX_TCP_SEC_TLS_CERT == tls_connect)
	{
		/* log peer certificate information for debugging */
		zbx_log_peer_cert(__func__, s->tls_ctx);

		/* perform basic verification of peer certificate */
		if (SUCCEED != zbx_verify_peer_cert(s->tls_ctx->ctx, error))
		{
			zbx_tls_close(s);
			goto out1;
		}

		/* if required verify peer certificate Issuer and Subject */
		if (SUCCEED != zbx_verify_issuer_subject(s->tls_ctx, tls_arg1, tls_arg2, error))
		{
			zbx_tls_close(s);
			goto out1;
		}
	}

	s->connection_type = tls_connect;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():SUCCEED (established %s %s-%s-%s-" ZBX_FS_SIZE_T ")", __func__,
			gnutls_protocol_get_name(gnutls_protocol_get_version(s->tls_ctx->ctx)),
			gnutls_kx_get_name(gnutls_kx_get(s->tls_ctx->ctx)),
			gnutls_cipher_get_name(gnutls_cipher_get(s->tls_ctx->ctx)),
			gnutls_mac_get_name(gnutls_mac_get(s->tls_ctx->ctx)),
			(zbx_fs_size_t)gnutls_mac_get_key_size(gnutls_mac_get(s->tls_ctx->ctx)));

	return SUCCEED;

out:	/* an error occurred */
	if (NULL != s->tls_ctx->ctx)
	{
		gnutls_credentials_clear(s->tls_ctx->ctx);
		gnutls_deinit(s->tls_ctx->ctx);
	}

	if (NULL != s->tls_ctx->psk_client_creds)
		gnutls_psk_free_client_credentials(s->tls_ctx->psk_client_creds);

	zbx_free(s->tls_ctx);
out1:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s error:'%s'", __func__, zbx_result_string(ret),
			ZBX_NULL2EMPTY_STR(*error));
	return ret;
}
#elif defined(HAVE_OPENSSL)
static int	zbx_tls_get_error(const SSL *s, int res, const char *func, size_t *error_alloc, size_t *error_offset,
		char **error)
{
	int	result_code;

	result_code = SSL_get_error(s, res);

	switch (result_code)
	{
		case SSL_ERROR_NONE:		/* handshake successful */
			return SUCCEED;
		case SSL_ERROR_ZERO_RETURN:
			zbx_snprintf_alloc(error, error_alloc, error_offset,
					"%s() TLS connection has been closed during read", func);
			return FAIL;
		case SSL_ERROR_SYSCALL:
			if (0 == ERR_peek_error())
			{
				if (0 == res)
				{
					zbx_snprintf_alloc(error, error_alloc, error_offset,
							"%s() connection closed by peer", func);
				}
				else if (-1 == res)
				{
					zbx_snprintf_alloc(error, error_alloc, error_offset, "%s()"
							" I/O error: %s", func,
							strerror_from_system(zbx_socket_last_error()));
				}
				else
				{
					/* "man SSL_get_error" describes only res == 0 and res == -1 for */
					/* SSL_ERROR_SYSCALL case */
					zbx_snprintf_alloc(error, error_alloc, error_offset, "%s()"
							" returned undocumented code %d", func, res);
				}
			}
			else
			{
				zbx_snprintf_alloc(error, error_alloc, error_offset, "%s() set"
						" result code to SSL_ERROR_SYSCALL:", func);
				zbx_tls_error_msg(error, error_alloc, error_offset);
				zbx_snprintf_alloc(error, error_alloc, error_offset, "%s", info_buf);
			}
			return FAIL;
		case SSL_ERROR_SSL:
			zbx_snprintf_alloc(error, error_alloc, error_offset, "%s() set"
					" result code to SSL_ERROR_SSL:", func);
			zbx_tls_error_msg(error, error_alloc, error_offset);
			zbx_snprintf_alloc(error, error_alloc, error_offset, "%s", info_buf);
			return FAIL;
		default:
			zbx_snprintf_alloc(error, error_alloc, error_offset, "%s() set result code"
					" to %d", func, result_code);
			zbx_tls_error_msg(error, error_alloc, error_offset);
			zbx_snprintf_alloc(error, error_alloc, error_offset, "%s", info_buf);
			return FAIL;
	}
}

int	zbx_tls_connect(zbx_socket_t *s, unsigned int tls_connect, const char *tls_arg1, const char *tls_arg2,
		const char *server_name, char **error)
{
	int	ret = FAIL, res;
	size_t	error_alloc = 0, error_offset = 0;
#if defined(_WINDOWS)
	double	sec;
#endif
#if defined(HAVE_OPENSSL_WITH_PSK)
	char	psk_buf[HOST_TLS_PSK_LEN / 2];
#endif

	s->tls_ctx = zbx_malloc(s->tls_ctx, sizeof(zbx_tls_context_t));
	s->tls_ctx->ctx = NULL;

	if (ZBX_TCP_SEC_TLS_CERT == tls_connect)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "In %s(): issuer:\"%s\" subject:\"%s\"", __func__,
				ZBX_NULL2EMPTY_STR(tls_arg1), ZBX_NULL2EMPTY_STR(tls_arg2));

		if (NULL == ctx_cert)
		{
			*error = zbx_strdup(*error, "cannot connect with TLS and certificate: no valid certificate"
					" loaded");
			goto out;
		}

		if (NULL == (s->tls_ctx->ctx = SSL_new(ctx_cert)))
		{
			zbx_snprintf_alloc(error, &error_alloc, &error_offset, "cannot create connection context:");
			zbx_tls_error_msg(error, &error_alloc, &error_offset);
			goto out;
		}
	}
	else if (ZBX_TCP_SEC_TLS_PSK == tls_connect)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "In %s(): psk_identity:\"%s\"", __func__, ZBX_NULL2EMPTY_STR(tls_arg1));

#if defined(HAVE_OPENSSL_WITH_PSK)
		if (NULL == ctx_psk)
		{
			*error = zbx_strdup(*error, "cannot connect with TLS and PSK: no valid PSK loaded");
			goto out;
		}

		if (NULL == (s->tls_ctx->ctx = SSL_new(ctx_psk)))
		{
			zbx_snprintf_alloc(error, &error_alloc, &error_offset, "cannot create connection context:");
			zbx_tls_error_msg(error, &error_alloc, &error_offset);
			goto out;
		}

		if (NULL == tls_arg2)	/* PSK is not set from DB */
		{
			/* Set up PSK global variables from a configuration file (always in agentd and a case when */
			/* active proxy connects to server). Here we set it only in case of active proxy */
			/* because for other programs it has already been set in zbx_tls_init_child(). */

			if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY_ACTIVE))
			{
				psk_identity_for_cb = my_psk_identity;
				psk_identity_len_for_cb = my_psk_identity_len;
				psk_for_cb = my_psk;
				psk_len_for_cb = my_psk_len;
			}
		}
		else
		{
			/* PSK comes from a database (case for a server/proxy when it connects to an agent for */
			/* passive checks, for a server when it connects to a passive proxy) */

			int	psk_len;

			if (0 >= (psk_len = zbx_hex2bin((const unsigned char *)tls_arg2, (unsigned char *)psk_buf,
					sizeof(psk_buf))))
			{
				*error = zbx_strdup(*error, "invalid PSK");
				goto out;
			}

			/* some data reside in stack but it will be available at the time when a PSK client callback */
			/* function copies the data into buffers provided by OpenSSL within the callback */
			psk_identity_for_cb = tls_arg1;			/* string is on stack */
			/* NULL check to silence analyzer warning */
			psk_identity_len_for_cb = (NULL == tls_arg1 ? 0 : strlen(tls_arg1));
			psk_for_cb = psk_buf;				/* buffer is on stack */
			psk_len_for_cb = (size_t)psk_len;
		}
#else
		*error = zbx_strdup(*error, "cannot connect with TLS and PSK: support for PSK was not compiled in");
		goto out;
#endif
	}
	else
	{
		*error = zbx_strdup(*error, "invalid connection parameters");
		THIS_SHOULD_NEVER_HAPPEN;
		goto out1;
	}

	if (NULL != server_name && ZBX_TCP_SEC_UNENCRYPTED != tls_connect && 1 != SSL_set_tlsext_host_name(
			s->tls_ctx->ctx, server_name))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot set %s tls host name", server_name);
	}

	/* set our connected TCP socket to TLS context */
	if (1 != SSL_set_fd(s->tls_ctx->ctx, s->socket))
	{
		*error = zbx_strdup(*error, "cannot set socket for TLS context");
		goto out;
	}

	/* TLS handshake */

	info_buf[0] = '\0';	/* empty buffer for zbx_openssl_info_cb() messages */
#if defined(_WINDOWS)
	zbx_alarm_flag_clear();
	sec = zbx_time();
#endif
	if (1 != (res = SSL_connect(s->tls_ctx->ctx)))
	{
#if defined(_WINDOWS)
		if (s->timeout < zbx_time() - sec)
			zbx_alarm_flag_set();
#endif
		if (SUCCEED == zbx_alarm_timed_out())
		{
			*error = zbx_strdup(*error, "SSL_connect() timed out");
			goto out;
		}

		if (ZBX_TCP_SEC_TLS_CERT == tls_connect)
		{
			long	verify_result;

			/* In case of certificate error SSL_get_verify_result() provides more helpful diagnostics */
			/* than other methods. Include it as first but continue with other diagnostics. */
			if (X509_V_OK != (verify_result = SSL_get_verify_result(s->tls_ctx->ctx)))
			{
				zbx_snprintf_alloc(error, &error_alloc, &error_offset, "%s: ",
						X509_verify_cert_error_string(verify_result));
			}
		}

		if (FAIL == zbx_tls_get_error(s->tls_ctx->ctx, res, "SSL_connect", &error_alloc, &error_offset, error))
			goto out;
	}

	if (ZBX_TCP_SEC_TLS_CERT == tls_connect)
	{
		long	verify_result;

		/* log peer certificate information for debugging */
		zbx_log_peer_cert(__func__, s->tls_ctx);

		/* perform basic verification of peer certificate */
		if (X509_V_OK != (verify_result = SSL_get_verify_result(s->tls_ctx->ctx)))
		{
			zbx_snprintf_alloc(error, &error_alloc, &error_offset, "%s",
					X509_verify_cert_error_string(verify_result));
			zbx_tls_close(s);
			goto out1;
		}

		/* if required verify peer certificate Issuer and Subject */
		if (SUCCEED != zbx_verify_issuer_subject(s->tls_ctx, tls_arg1, tls_arg2, error))
		{
			zbx_tls_close(s);
			goto out1;
		}
	}

	s->connection_type = tls_connect;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():SUCCEED (established %s %s)", __func__,
			SSL_get_version(s->tls_ctx->ctx), SSL_get_cipher(s->tls_ctx->ctx));

	return SUCCEED;

out:	/* an error occurred */
	if (NULL != s->tls_ctx->ctx)
		SSL_free(s->tls_ctx->ctx);

	zbx_free(s->tls_ctx);
out1:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s error:'%s'", __func__, zbx_result_string(ret),
			ZBX_NULL2EMPTY_STR(*error));
	return ret;
}
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: establish a TLS connection over an accepted TCP connection        *
 *                                                                            *
 * Parameters:                                                                *
 *     s          - [IN] socket with opened connection                        *
 *     error      - [OUT] dynamically allocated memory with error message     *
 *     tls_accept - [IN] type of connection to accept. Can be be either       *
 *                       ZBX_TCP_SEC_TLS_CERT or ZBX_TCP_SEC_TLS_PSK, or      *
 *                       a bitwise 'OR' of both.                              *
 *                                                                            *
 * Return value:                                                              *
 *     SUCCEED - successful TLS handshake with a valid certificate or PSK     *
 *     FAIL - an error occurred                                               *
 *                                                                            *
 ******************************************************************************/
#if defined(HAVE_GNUTLS)
int	zbx_tls_accept(zbx_socket_t *s, unsigned int tls_accept, char **error)
{
	int				ret = FAIL, res;
	gnutls_credentials_type_t	creds;
#if defined(_WINDOWS)
	double				sec;
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	/* set up TLS context */

	s->tls_ctx = zbx_malloc(s->tls_ctx, sizeof(zbx_tls_context_t));
	s->tls_ctx->ctx = NULL;
	s->tls_ctx->psk_client_creds = NULL;
	s->tls_ctx->psk_server_creds = NULL;

	if (GNUTLS_E_SUCCESS != (res = gnutls_init(&s->tls_ctx->ctx, GNUTLS_SERVER)))
	{
		*error = zbx_dsprintf(*error, "gnutls_init() failed: %d %s", res, gnutls_strerror(res));
		goto out;
	}

	/* prepare to accept with certificate */

	if (0 != (tls_accept & ZBX_TCP_SEC_TLS_CERT))
	{
		if (NULL != my_cert_creds && GNUTLS_E_SUCCESS != (res = gnutls_credentials_set(s->tls_ctx->ctx,
				GNUTLS_CRD_CERTIFICATE, my_cert_creds)))
		{
			*error = zbx_dsprintf(*error, "gnutls_credentials_set() for certificate failed: %d %s", res,
					gnutls_strerror(res));
			goto out;
		}

		/* client certificate is mandatory unless pre-shared key is used */
		gnutls_certificate_server_set_request(s->tls_ctx->ctx, GNUTLS_CERT_REQUIRE);
	}

	/* prepare to accept with pre-shared key */

	if (0 != (tls_accept & ZBX_TCP_SEC_TLS_PSK))
	{
		/* for agentd the only possibility is a PSK from configuration file */
		if (0 != (program_type & ZBX_PROGRAM_TYPE_AGENTD) &&
				GNUTLS_E_SUCCESS != (res = gnutls_credentials_set(s->tls_ctx->ctx, GNUTLS_CRD_PSK,
				my_psk_server_creds)))
		{
			*error = zbx_dsprintf(*error, "gnutls_credentials_set() for my_psk_server_creds failed: %d %s",
					res, gnutls_strerror(res));
			goto out;
		}
		else if (0 != (program_type & (ZBX_PROGRAM_TYPE_PROXY | ZBX_PROGRAM_TYPE_SERVER)))
		{
			/* For server or proxy a PSK can come from configuration file or database. */
			/* Set up a callback function for finding the requested PSK. */
			if (GNUTLS_E_SUCCESS != (res = gnutls_psk_allocate_server_credentials(
					&s->tls_ctx->psk_server_creds)))
			{
				*error = zbx_dsprintf(*error, "gnutls_psk_allocate_server_credentials() for"
						" psk_server_creds failed: %d %s", res, gnutls_strerror(res));
				goto out;
			}

			gnutls_psk_set_server_credentials_function(s->tls_ctx->psk_server_creds, zbx_psk_cb);

			if (GNUTLS_E_SUCCESS != (res = gnutls_credentials_set(s->tls_ctx->ctx, GNUTLS_CRD_PSK,
					s->tls_ctx->psk_server_creds)))
			{
				*error = zbx_dsprintf(*error, "gnutls_credentials_set() for psk_server_creds failed"
						": %d %s", res, gnutls_strerror(res));
				goto out;
			}
		}
	}

	/* set up ciphersuites */

	if ((ZBX_TCP_SEC_TLS_CERT | ZBX_TCP_SEC_TLS_PSK) == (tls_accept & (ZBX_TCP_SEC_TLS_CERT | ZBX_TCP_SEC_TLS_PSK)))
	{
		/* common case in trapper - be ready for all types of incoming connections */
		if (NULL != my_cert_creds)
		{
			/* it can also be a case in agentd listener - when both certificate and PSK is allowed, e.g. */
			/* for switching of TLS connections from PSK to using a certificate */
			if (GNUTLS_E_SUCCESS != (res = gnutls_priority_set(s->tls_ctx->ctx, ciphersuites_all)))
			{
				*error = zbx_dsprintf(*error, "gnutls_priority_set() for 'ciphersuites_all' failed: %d"
						" %s", res, gnutls_strerror(res));
				goto out;
			}
		}
		else
		{
			/* assume PSK, although it is not yet known will there be the right PSK available */
			if (GNUTLS_E_SUCCESS != (res = gnutls_priority_set(s->tls_ctx->ctx, ciphersuites_psk)))
			{
				*error = zbx_dsprintf(*error, "gnutls_priority_set() for 'ciphersuites_psk' failed: %d"
						" %s", res, gnutls_strerror(res));
				goto out;
			}
		}
	}
	else if (0 != (tls_accept & ZBX_TCP_SEC_TLS_CERT) && NULL != my_cert_creds)
	{
		if (GNUTLS_E_SUCCESS != (res = gnutls_priority_set(s->tls_ctx->ctx, ciphersuites_cert)))
		{
			*error = zbx_dsprintf(*error, "gnutls_priority_set() for 'ciphersuites_cert' failed: %d %s",
					res, gnutls_strerror(res));
			goto out;
		}
	}
	else if (0 != (tls_accept & ZBX_TCP_SEC_TLS_PSK))
	{
		if (GNUTLS_E_SUCCESS != (res = gnutls_priority_set(s->tls_ctx->ctx, ciphersuites_psk)))
		{
			*error = zbx_dsprintf(*error, "gnutls_priority_set() for 'ciphersuites_psk' failed: %d %s", res,
					gnutls_strerror(res));
			goto out;
		}
	}

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_TRACE))
	{
		/* set our own debug callback function */
		gnutls_global_set_log_function(zbx_gnutls_debug_cb);

		/* for Zabbix LOG_LEVEL_TRACE, GnuTLS debug level 4 seems the best */
		/* (the highest GnuTLS debug level is 9) */
		gnutls_global_set_log_level(4);
	}
	else
		gnutls_global_set_log_level(0);		/* restore default log level */

	/* set our own callback function to log issues into Zabbix log */
	gnutls_global_set_audit_log_function(zbx_gnutls_audit_cb);

	gnutls_transport_set_int(s->tls_ctx->ctx, ZBX_SOCKET_TO_INT(s->socket));

	/* TLS handshake */

#if defined(_WINDOWS)
	zbx_alarm_flag_clear();
	sec = zbx_time();
#endif
	while (GNUTLS_E_SUCCESS != (res = gnutls_handshake(s->tls_ctx->ctx)))
	{
#if defined(_WINDOWS)
		if (s->timeout < zbx_time() - sec)
			zbx_alarm_flag_set();
#endif
		if (SUCCEED == zbx_alarm_timed_out())
		{
			*error = zbx_strdup(*error, "gnutls_handshake() timed out");
			goto out;
		}

		if (GNUTLS_E_INTERRUPTED == res || GNUTLS_E_AGAIN == res)
		{
			continue;
		}
		else if (GNUTLS_E_WARNING_ALERT_RECEIVED == res || GNUTLS_E_FATAL_ALERT_RECEIVED == res ||
				GNUTLS_E_GOT_APPLICATION_DATA == res)
		{
			const char	*msg;
			int		alert;

			/* client sent an alert to us */
			alert = gnutls_alert_get(s->tls_ctx->ctx);

			if (NULL == (msg = gnutls_alert_get_name(alert)))
				msg = "unknown";

			if (GNUTLS_E_WARNING_ALERT_RECEIVED == res)
			{
				zabbix_log(LOG_LEVEL_WARNING, "%s() gnutls_handshake() received a warning alert: %d %s",
						__func__, alert, msg);
				continue;
			}
			else if (GNUTLS_E_GOT_APPLICATION_DATA == res)
					/* if rehandshake request deal with it as with error */
			{
				*error = zbx_dsprintf(*error, "%s(): gnutls_handshake() returned"
						" GNUTLS_E_GOT_APPLICATION_DATA", __func__);
				goto out;
			}
			else	/* GNUTLS_E_FATAL_ALERT_RECEIVED */
			{
				*error = zbx_dsprintf(*error, "%s(): gnutls_handshake() failed with fatal alert: %d %s",
						__func__, alert, msg);
				goto out;
			}
		}
		else
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s() gnutls_handshake() returned: %d %s",
					__func__, res, gnutls_strerror(res));

			if (0 != gnutls_error_is_fatal(res))
			{
				*error = zbx_dsprintf(*error, "%s(): gnutls_handshake() failed: %d %s",
						__func__, res, gnutls_strerror(res));
				goto out;
			}
		}
	}

	/* Is this TLS connection using certificate or PSK? */

	if (GNUTLS_CRD_CERTIFICATE == (creds = gnutls_auth_get_type(s->tls_ctx->ctx)))
	{
		s->connection_type = ZBX_TCP_SEC_TLS_CERT;

		/* log peer certificate information for debugging */
		zbx_log_peer_cert(__func__, s->tls_ctx);

		/* perform basic verification of peer certificate */
		if (SUCCEED != zbx_verify_peer_cert(s->tls_ctx->ctx, error))
		{
			zbx_tls_close(s);
			goto out1;
		}

		/* Issuer and Subject will be verified later, after receiving sender type and host name */
	}
	else if (GNUTLS_CRD_PSK == creds)
	{
		s->connection_type = ZBX_TCP_SEC_TLS_PSK;

		if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
		{
			const char	*psk_identity;

			if (NULL != (psk_identity = gnutls_psk_server_get_username(s->tls_ctx->ctx)))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "%s() PSK identity: \"%s\"", __func__, psk_identity);
			}
		}
	}
	else
	{
		THIS_SHOULD_NEVER_HAPPEN;
		zbx_tls_close(s);
		return FAIL;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():SUCCEED (established %s %s-%s-%s-" ZBX_FS_SIZE_T ")", __func__,
			gnutls_protocol_get_name(gnutls_protocol_get_version(s->tls_ctx->ctx)),
			gnutls_kx_get_name(gnutls_kx_get(s->tls_ctx->ctx)),
			gnutls_cipher_get_name(gnutls_cipher_get(s->tls_ctx->ctx)),
			gnutls_mac_get_name(gnutls_mac_get(s->tls_ctx->ctx)),
			(zbx_fs_size_t)gnutls_mac_get_key_size(gnutls_mac_get(s->tls_ctx->ctx)));

	return SUCCEED;

out:	/* an error occurred */
	if (NULL != s->tls_ctx->ctx)
	{
		gnutls_credentials_clear(s->tls_ctx->ctx);
		gnutls_deinit(s->tls_ctx->ctx);
	}

	if (NULL != s->tls_ctx->psk_server_creds)
		gnutls_psk_free_server_credentials(s->tls_ctx->psk_server_creds);

	zbx_free(s->tls_ctx);
out1:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s error:'%s'", __func__, zbx_result_string(ret),
			ZBX_NULL2EMPTY_STR(*error));
	return ret;
}
#elif defined(HAVE_OPENSSL)
int	zbx_tls_accept(zbx_socket_t *s, unsigned int tls_accept, char **error)
{
	const char	*cipher_name;
	int		ret = FAIL, res;
	size_t		error_alloc = 0, error_offset = 0;
	long		verify_result;
#if defined(_WINDOWS)
	double		sec;
#endif
#if OPENSSL_VERSION_NUMBER >= 0x1010100fL	/* OpenSSL 1.1.1 or newer, or LibreSSL */
	const unsigned char	session_id_context[] = {'Z', 'b', 'x'};
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	s->tls_ctx = zbx_malloc(s->tls_ctx, sizeof(zbx_tls_context_t));
	s->tls_ctx->ctx = NULL;

#if defined(HAVE_OPENSSL_WITH_PSK)
	incoming_connection_has_psk = 0;	/* assume certificate-based connection by default */
#endif
	if ((ZBX_TCP_SEC_TLS_CERT | ZBX_TCP_SEC_TLS_PSK) == (tls_accept & (ZBX_TCP_SEC_TLS_CERT | ZBX_TCP_SEC_TLS_PSK)))
	{
#if defined(HAVE_OPENSSL_WITH_PSK)
		/* common case in trapper - be ready for all types of incoming connections but possible also in */
		/* agentd listener */

		if (NULL != ctx_all)
		{
			if (NULL == (s->tls_ctx->ctx = SSL_new(ctx_all)))
			{
				zbx_snprintf_alloc(error, &error_alloc, &error_offset, "cannot create context to accept"
						" connection:");
				zbx_tls_error_msg(error, &error_alloc, &error_offset);
				goto out;
			}
		}
#else
		if (0 != (program_type & (ZBX_PROGRAM_TYPE_PROXY | ZBX_PROGRAM_TYPE_SERVER)))
		{
			/* server or proxy running with OpenSSL or LibreSSL without PSK support */
			if (NULL != ctx_cert)
			{
				if (NULL == (s->tls_ctx->ctx = SSL_new(ctx_cert)))
				{
					zbx_snprintf_alloc(error, &error_alloc, &error_offset, "cannot create context"
							" to accept connection:");
					zbx_tls_error_msg(error, &error_alloc, &error_offset);
					goto out;
				}
			}
			else
			{
				*error = zbx_strdup(*error, "not ready for certificate-based incoming connection:"
						" certificate not loaded. PSK support not compiled in.");
				goto out;
			}
		}
#endif
		else if (0 != (program_type & ZBX_PROGRAM_TYPE_AGENTD))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			goto out;
		}
#if defined(HAVE_OPENSSL_WITH_PSK)
		else if (NULL != ctx_psk)
		{
			/* Server or proxy with no certificate configured. PSK is always assumed to be configured on */
			/* server or proxy because PSK can come from database. */

			if (NULL == (s->tls_ctx->ctx = SSL_new(ctx_psk)))
			{
				zbx_snprintf_alloc(error, &error_alloc, &error_offset, "cannot create context to accept"
						" connection:");
				zbx_tls_error_msg(error, &error_alloc, &error_offset);
				goto out;
			}
		}
		else
		{
			THIS_SHOULD_NEVER_HAPPEN;
			goto out;
		}
#endif
	}
	else if (0 != (tls_accept & ZBX_TCP_SEC_TLS_CERT))
	{
		if (NULL != ctx_cert)
		{
			if (NULL == (s->tls_ctx->ctx = SSL_new(ctx_cert)))
			{
				zbx_snprintf_alloc(error, &error_alloc, &error_offset, "cannot create context to accept"
						" connection:");
				zbx_tls_error_msg(error, &error_alloc, &error_offset);
				goto out;
			}
		}
		else
		{
			*error = zbx_strdup(*error, "not ready for certificate-based incoming connection: certificate"
					" not loaded");
			goto out;
		}
	}
	else	/* PSK */
	{
#if defined(HAVE_OPENSSL_WITH_PSK)
		if (NULL != ctx_psk)
		{
			if (NULL == (s->tls_ctx->ctx = SSL_new(ctx_psk)))
			{
				zbx_snprintf_alloc(error, &error_alloc, &error_offset, "cannot create context to accept"
						" connection:");
				zbx_tls_error_msg(error, &error_alloc, &error_offset);
				goto out;
			}
		}
		else
		{
			*error = zbx_strdup(*error, "not ready for PSK-based incoming connection: PSK not loaded");
			goto out;
		}
#else
		*error = zbx_strdup(*error, "support for PSK was not compiled in");
		goto out;
#endif
	}

#if OPENSSL_VERSION_NUMBER >= 0x1010100fL	/* OpenSSL 1.1.1 or newer, or LibreSSL */
	if (1 != SSL_set_session_id_context(s->tls_ctx->ctx, session_id_context, sizeof(session_id_context)))
	{
		*error = zbx_strdup(*error, "cannot set session_id_context");
		goto out;
	}
#endif
	if (1 != SSL_set_fd(s->tls_ctx->ctx, s->socket))
	{
		*error = zbx_strdup(*error, "cannot set socket for TLS context");
		goto out;
	}

	/* TLS handshake */

	info_buf[0] = '\0';	/* empty buffer for zbx_openssl_info_cb() messages */
#if defined(_WINDOWS)
	zbx_alarm_flag_clear();
	sec = zbx_time();
#endif
	if (1 != (res = SSL_accept(s->tls_ctx->ctx)))
	{
		int	result_code;

#if defined(_WINDOWS)
		if (s->timeout < zbx_time() - sec)
			zbx_alarm_flag_set();
#endif
		if (SUCCEED == zbx_alarm_timed_out())
		{
			*error = zbx_strdup(*error, "SSL_accept() timed out");
			goto out;
		}

		/* In case of certificate error SSL_get_verify_result() provides more helpful diagnostics */
		/* than other methods. Include it as first but continue with other diagnostics. Should be */
		/* harmless in case of PSK. */

		if (X509_V_OK != (verify_result = SSL_get_verify_result(s->tls_ctx->ctx)))
		{
			zbx_snprintf_alloc(error, &error_alloc, &error_offset, "%s: ",
					X509_verify_cert_error_string(verify_result));
		}

		result_code = SSL_get_error(s->tls_ctx->ctx, res);

		if (0 == res)
		{
			zbx_snprintf_alloc(error, &error_alloc, &error_offset, "TLS connection has been closed during"
					" handshake:");
		}
		else
		{
			zbx_snprintf_alloc(error, &error_alloc, &error_offset, "TLS handshake set result code to %d:",
					result_code);
		}

		zbx_tls_error_msg(error, &error_alloc, &error_offset);
		zbx_snprintf_alloc(error, &error_alloc, &error_offset, "%s", info_buf);
		goto out;
	}

	/* Is this TLS connection using certificate or PSK? */

	cipher_name = SSL_get_cipher(s->tls_ctx->ctx);

#if defined(HAVE_OPENSSL_WITH_PSK)
	if (1 == incoming_connection_has_psk)
	{
		s->connection_type = ZBX_TCP_SEC_TLS_PSK;
	}
	else if (0 != strncmp("(NONE)", cipher_name, ZBX_CONST_STRLEN("(NONE)")))
#endif
	{
		s->connection_type = ZBX_TCP_SEC_TLS_CERT;

		/* log peer certificate information for debugging */
		zbx_log_peer_cert(__func__, s->tls_ctx);

		/* perform basic verification of peer certificate */
		if (X509_V_OK != (verify_result = SSL_get_verify_result(s->tls_ctx->ctx)))
		{
			zbx_snprintf_alloc(error, &error_alloc, &error_offset, "%s",
					X509_verify_cert_error_string(verify_result));
			zbx_tls_close(s);
			goto out1;
		}

		/* Issuer and Subject will be verified later, after receiving sender type and host name */
	}
#if defined(HAVE_OPENSSL_WITH_PSK)
	else
	{
		THIS_SHOULD_NEVER_HAPPEN;
		zbx_tls_close(s);
		return FAIL;
	}
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():SUCCEED (established %s %s)", __func__,
			SSL_get_version(s->tls_ctx->ctx), cipher_name);

	return SUCCEED;

out:	/* an error occurred */
	if (NULL != s->tls_ctx->ctx)
		SSL_free(s->tls_ctx->ctx);

	zbx_free(s->tls_ctx);
out1:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s error:'%s'", __func__, zbx_result_string(ret),
			ZBX_NULL2EMPTY_STR(*error));
	return ret;
}
#endif

#if defined(HAVE_GNUTLS)
#	define ZBX_TLS_WRITE(ctx, buf, len)	gnutls_record_send(ctx, buf, len)
#	define ZBX_TLS_READ(ctx, buf, len)	gnutls_record_recv(ctx, buf, len)
#	define ZBX_TLS_WRITE_FUNC_NAME		"gnutls_record_send"
#	define ZBX_TLS_READ_FUNC_NAME		"gnutls_record_recv"
#	define ZBX_TLS_WANT_WRITE(res)		(GNUTLS_E_INTERRUPTED == (res) || GNUTLS_E_AGAIN == (res) ? SUCCEED : FAIL)
#	define ZBX_TLS_WANT_READ(res)		(GNUTLS_E_INTERRUPTED == (res) || GNUTLS_E_AGAIN == (res) ? SUCCEED : FAIL)
#elif defined(HAVE_OPENSSL)
#	define ZBX_TLS_WRITE(ctx, buf, len)	SSL_write(ctx, buf, (int)(len))
#	define ZBX_TLS_READ(ctx, buf, len)	SSL_read(ctx, buf, (int)(len))
#	define ZBX_TLS_WRITE_FUNC_NAME		"SSL_write"
#	define ZBX_TLS_READ_FUNC_NAME		"SSL_read"
#	define ZBX_TLS_WANT_WRITE(res)		FAIL
#	define ZBX_TLS_WANT_READ(res)		FAIL
/* SSL_ERROR_WANT_READ or SSL_ERROR_WANT_WRITE should not be returned here because we set */
/* SSL_MODE_AUTO_RETRY flag in zbx_tls_init_child() */
#endif

ssize_t	zbx_tls_write(zbx_socket_t *s, const char *buf, size_t len, char **error)
{
#if defined(HAVE_GNUTLS)
	ssize_t	res;
#elif defined(HAVE_OPENSSL)
	int	res;
#endif

#if defined(HAVE_OPENSSL)
	info_buf[0] = '\0';	/* empty buffer for zbx_openssl_info_cb() messages */
#endif
	do
	{
		res = ZBX_TLS_WRITE(s->tls_ctx->ctx, buf, len);
#if !defined(_WINDOWS)
		if (SUCCEED == zbx_alarm_timed_out())
		{
			*error = zbx_strdup(*error, ZBX_TLS_WRITE_FUNC_NAME "() timed out");
			return ZBX_PROTO_ERROR;
		}
#endif
	}
	while (SUCCEED == ZBX_TLS_WANT_WRITE(res));

#if defined(HAVE_GNUTLS)
	if (0 > res)
	{
		*error = zbx_dsprintf(*error, "gnutls_record_send() failed: " ZBX_FS_SSIZE_T " %s",
				(zbx_fs_ssize_t)res, gnutls_strerror(res));

		return ZBX_PROTO_ERROR;
	}
#elif defined(HAVE_OPENSSL)
	if (0 >= res)
	{
		size_t	error_alloc = 0, error_offset = 0;

		if (SUCCEED == zbx_tls_get_error(s->tls_ctx->ctx, res, ZBX_TLS_WRITE_FUNC_NAME, &error_alloc,
				&error_offset, error))
		{
			*error = zbx_strdup(*error, ZBX_TLS_WRITE_FUNC_NAME "() unexpected result code");
		}

		return ZBX_PROTO_ERROR;
	}
#endif

	return (ssize_t)res;
}

ssize_t	zbx_tls_read(zbx_socket_t *s, char *buf, size_t len, char **error)
{
#if defined(HAVE_GNUTLS)
	ssize_t	res;
#elif defined(HAVE_OPENSSL)
	int	res;
#endif

#if defined(HAVE_OPENSSL)
	info_buf[0] = '\0';	/* empty buffer for zbx_openssl_info_cb() messages */
#endif
	do
	{
		res = ZBX_TLS_READ(s->tls_ctx->ctx, buf, len);
#if !defined(_WINDOWS)
		if (SUCCEED == zbx_alarm_timed_out())
		{
			*error = zbx_strdup(*error, ZBX_TLS_READ_FUNC_NAME "() timed out");
			return ZBX_PROTO_ERROR;
		}
#endif
	}
	while (SUCCEED == ZBX_TLS_WANT_READ(res));

#if defined(HAVE_GNUTLS)
	if (0 > res)
	{
		/* in case of rehandshake a GNUTLS_E_REHANDSHAKE will be returned, deal with it as with error */
		*error = zbx_dsprintf(*error, "gnutls_record_recv() failed: " ZBX_FS_SSIZE_T " %s",
				(zbx_fs_ssize_t)res, gnutls_strerror(res));

		return ZBX_PROTO_ERROR;
	}
#elif defined(HAVE_OPENSSL)
	if (0 >= res)
	{
		size_t	error_alloc = 0, error_offset = 0;

		if (SUCCEED == zbx_tls_get_error(s->tls_ctx->ctx, res, ZBX_TLS_READ_FUNC_NAME, &error_alloc,
				&error_offset, error))
		{
			*error = zbx_strdup(*error, ZBX_TLS_READ_FUNC_NAME "() unexpected result code");
		}

		return ZBX_PROTO_ERROR;
	}
#endif

	return (ssize_t)res;
}

/******************************************************************************
 *                                                                            *
 * Purpose: close a TLS connection before closing a TCP socket                *
 *                                                                            *
 ******************************************************************************/
void	zbx_tls_close(zbx_socket_t *s)
{
	int	res;

	if (NULL == s->tls_ctx)
		return;
#if defined(HAVE_GNUTLS)
	if (NULL != s->tls_ctx->ctx)
	{
#if defined(_WINDOWS)
		double	sec;

		zbx_alarm_flag_clear();
		sec = zbx_time();
#endif
		/* shutdown TLS connection */
		while (GNUTLS_E_SUCCESS != (res = gnutls_bye(s->tls_ctx->ctx, GNUTLS_SHUT_WR)))
		{
#if defined(_WINDOWS)
			if (s->timeout < zbx_time() - sec)
				zbx_alarm_flag_set();
#endif
			if (SUCCEED == zbx_alarm_timed_out())
				break;

			if (GNUTLS_E_INTERRUPTED == res || GNUTLS_E_AGAIN == res)
				continue;

			zabbix_log(LOG_LEVEL_WARNING, "gnutls_bye() with %s returned error code: %d %s",
					s->peer, res, gnutls_strerror(res));

			if (0 != gnutls_error_is_fatal(res))
				break;
		}

		gnutls_credentials_clear(s->tls_ctx->ctx);
		gnutls_deinit(s->tls_ctx->ctx);
	}

	if (NULL != s->tls_ctx->psk_client_creds)
		gnutls_psk_free_client_credentials(s->tls_ctx->psk_client_creds);

	if (NULL != s->tls_ctx->psk_server_creds)
		gnutls_psk_free_server_credentials(s->tls_ctx->psk_server_creds);
#elif defined(HAVE_OPENSSL)
	if (NULL != s->tls_ctx->ctx)
	{
		info_buf[0] = '\0';	/* empty buffer for zbx_openssl_info_cb() messages */

		/* After TLS shutdown the TCP connection will be closed. So, there is no need to do a bidirectional */
		/* TLS shutdown - unidirectional shutdown is ok. */
		if (0 > (res = SSL_shutdown(s->tls_ctx->ctx)))
		{
			int	result_code;
			char	*error = NULL;
			size_t	error_alloc = 0, error_offset = 0;

			result_code = SSL_get_error(s->tls_ctx->ctx, res);
			zbx_tls_error_msg(&error, &error_alloc, &error_offset);
			zabbix_log(LOG_LEVEL_WARNING, "SSL_shutdown() with %s set result code to %d:%s%s",
					s->peer, result_code, ZBX_NULL2EMPTY_STR(error), info_buf);
			zbx_free(error);
		}

		SSL_free(s->tls_ctx->ctx);
	}
#endif
	zbx_free(s->tls_ctx);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get certificate attributes from the context of established        *
 *          connection                                                        *
 *                                                                            *
 ******************************************************************************/
int	zbx_tls_get_attr_cert(const zbx_socket_t *s, zbx_tls_conn_attr_t *attr)
{
	char			*error = NULL;
#if defined(HAVE_GNUTLS)
	gnutls_x509_crt_t	peer_cert;
	gnutls_x509_dn_t	dn;
	int			res;
#elif defined(HAVE_OPENSSL)
	X509			*peer_cert;
#endif

#if defined(HAVE_GNUTLS)
	/* here is some inefficiency - we do not know will it be required to verify peer certificate issuer */
	/* and subject - but we prepare for it */
	if (NULL == (peer_cert = zbx_get_peer_cert(s->tls_ctx->ctx, &error)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot get peer certificate: %s", error);
		zbx_free(error);
		return FAIL;
	}

	if (0 != (res = gnutls_x509_crt_get_issuer(peer_cert, &dn)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "gnutls_x509_crt_get_issuer() failed: %d %s", res,
				gnutls_strerror(res));
		gnutls_x509_crt_deinit(peer_cert);
		return FAIL;
	}

	if (SUCCEED != zbx_x509_dn_gets(dn, attr->issuer, sizeof(attr->issuer), &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "zbx_x509_dn_gets() failed: %s", error);
		zbx_free(error);
		gnutls_x509_crt_deinit(peer_cert);
		return FAIL;
	}

	if (0 != (res = gnutls_x509_crt_get_subject(peer_cert, &dn)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "gnutls_x509_crt_get_subject() failed: %d %s", res,
				gnutls_strerror(res));
		gnutls_x509_crt_deinit(peer_cert);
		return FAIL;
	}

	if (SUCCEED != zbx_x509_dn_gets(dn, attr->subject, sizeof(attr->subject), &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "zbx_x509_dn_gets() failed: %s", error);
		zbx_free(error);
		gnutls_x509_crt_deinit(peer_cert);
		return FAIL;
	}

	gnutls_x509_crt_deinit(peer_cert);
#elif defined(HAVE_OPENSSL)
	if (NULL == (peer_cert = SSL_get_peer_certificate(s->tls_ctx->ctx)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "no peer certificate, SSL_get_peer_certificate() returned NULL");
		return FAIL;
	}

	if (SUCCEED != zbx_x509_dn_gets(X509_get_issuer_name(peer_cert), attr->issuer, sizeof(attr->issuer), &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "error while getting issuer name: \"%s\"", error);
		zbx_free(error);
		X509_free(peer_cert);
		return FAIL;
	}

	if (SUCCEED != zbx_x509_dn_gets(X509_get_subject_name(peer_cert), attr->subject, sizeof(attr->subject), &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "error while getting subject name: \"%s\"", error);
		zbx_free(error);
		X509_free(peer_cert);
		return FAIL;
	}

	X509_free(peer_cert);
#endif

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get PSK attributes from the context of established connection     *
 *                                                                            *
 * Comments:                                                                  *
 *     This function can be used only on server-side of TLS connection.       *
 *     GnuTLS makes it asymmetric - see documentation for                     *
 *     gnutls_psk_server_get_username() and gnutls_psk_client_get_hint()      *
 *     (the latter function is not used in Zabbix).                           *
 *     Implementation for OpenSSL is server-side only, too.                   *
 *                                                                            *
 ******************************************************************************/
#if defined(HAVE_GNUTLS)
int	zbx_tls_get_attr_psk(const zbx_socket_t *s, zbx_tls_conn_attr_t *attr)
{
	if (NULL == (attr->psk_identity = gnutls_psk_server_get_username(s->tls_ctx->ctx)))
		return FAIL;

	attr->psk_identity_len = strlen(attr->psk_identity);
	return SUCCEED;
}
#elif defined(HAVE_OPENSSL) && defined(HAVE_OPENSSL_WITH_PSK)
int	zbx_tls_get_attr_psk(const zbx_socket_t *s, zbx_tls_conn_attr_t *attr)
{
	ZBX_UNUSED(s);

	/* SSL_get_psk_identity() is not used here. It works with TLS 1.2, */
	/* but returns NULL with TLS 1.3 in OpenSSL 1.1.1 */
	if ('\0' == incoming_connection_psk_id[0])
		return FAIL;

	attr->psk_identity = incoming_connection_psk_id;
	attr->psk_identity_len = strlen(attr->psk_identity);
	return SUCCEED;
}
#endif

#if defined(_WINDOWS)
/******************************************************************************
 *                                                                            *
 * Purpose: pass some TLS variables from one thread to other                  *
 *                                                                            *
 * Comments: used in Zabbix sender on MS Windows                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_tls_pass_vars(ZBX_THREAD_SENDVAL_TLS_ARGS *args)
{
#if defined(HAVE_GNUTLS)
	args->my_cert_creds = my_cert_creds;
	args->my_psk_client_creds = my_psk_client_creds;
	args->ciphersuites_cert = ciphersuites_cert;
	args->ciphersuites_psk = ciphersuites_psk;
#elif defined(HAVE_OPENSSL)
	args->ctx_cert = ctx_cert;
#if defined(HAVE_OPENSSL_WITH_PSK)
	args->ctx_psk = ctx_psk;
	args->psk_identity_for_cb = psk_identity_for_cb;
	args->psk_identity_len_for_cb = psk_identity_len_for_cb;
	args->psk_for_cb = psk_for_cb;
	args->psk_len_for_cb = psk_len_for_cb;
#endif
#endif	/* defined(HAVE_OPENSSL) */
}

/******************************************************************************
 *                                                                            *
 * Purpose: pass some TLS variables from one thread to other                  *
 *                                                                            *
 * Comments: used in Zabbix sender on MS Windows                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_tls_take_vars(ZBX_THREAD_SENDVAL_TLS_ARGS *args)
{
#if defined(HAVE_GNUTLS)
	my_cert_creds = args->my_cert_creds;
	my_psk_client_creds = args->my_psk_client_creds;
	ciphersuites_cert = args->ciphersuites_cert;
	ciphersuites_psk = args->ciphersuites_psk;
#elif defined(HAVE_OPENSSL)
	ctx_cert = args->ctx_cert;
#if defined(HAVE_OPENSSL_WITH_PSK)
	ctx_psk = args->ctx_psk;
	psk_identity_for_cb = args->psk_identity_for_cb;
	psk_identity_len_for_cb = args->psk_identity_len_for_cb;
	psk_for_cb = args->psk_for_cb;
	psk_len_for_cb = args->psk_len_for_cb;
#endif
#endif	/* defined(HAVE_OPENSSL) */
}
#endif

unsigned int	zbx_tls_get_psk_usage(void)
{
	return	psk_usage;
}
#endif
