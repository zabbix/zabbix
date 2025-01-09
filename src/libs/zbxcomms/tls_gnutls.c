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

#include "zbxcomms.h"

#include "comms.h"
#include "tls.h"

#include "zbxsysinc.h"
#include "zbxlog.h"
#include "zbxstr.h"
#include "zbxcrypto.h"

static zbx_get_program_type_f		zbx_get_program_type_cb = NULL;

static ZBX_THREAD_LOCAL const char	*my_psk_identity	= NULL;
static ZBX_THREAD_LOCAL size_t		my_psk_identity_len	= 0;
static ZBX_THREAD_LOCAL char		*my_psk			= NULL;
static ZBX_THREAD_LOCAL size_t		my_psk_len		= 0;

/* Pointer to zbx_dc_get_psk_by_identity() initialized at runtime. This is a workaround for linking. */
/* Server and proxy link with src/libs/zbxdbcache/dbconfig.o where zbx_dc_get_psk_by_identity() resides */
/* but other components (e.g. agent) do not link dbconfig.o. */
static zbx_find_psk_in_cache_f	find_psk_in_cache_cb = NULL;

/* variable for passing information from callback functions if PSK was found among host PSKs or autoregistration PSK */
static unsigned int	psk_usage;

static zbx_tls_status_t	tls_status = ZBX_TLS_INIT_NONE;

static ZBX_THREAD_LOCAL gnutls_certificate_credentials_t	my_cert_creds		= NULL;
static ZBX_THREAD_LOCAL gnutls_psk_client_credentials_t		my_psk_client_creds	= NULL;
static ZBX_THREAD_LOCAL gnutls_psk_server_credentials_t		my_psk_server_creds	= NULL;
static ZBX_THREAD_LOCAL gnutls_priority_t			ciphersuites_cert	= NULL;
static ZBX_THREAD_LOCAL gnutls_priority_t			ciphersuites_psk	= NULL;
static ZBX_THREAD_LOCAL gnutls_priority_t			ciphersuites_all	= NULL;

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

static void	tls_socket_event(gnutls_session_t session, ssize_t err, short *event)
{
	ZBX_UNUSED(err);
	*event = (0 == gnutls_record_get_direction(session) ? POLLIN : POLLOUT);
}

/******************************************************************************
 *                                                                            *
 * Purpose: wait for socket to be available for read or write depending on    *
 *          unfinished operation                                              *
 *                                                                            *
 ******************************************************************************/
static int	tls_socket_wait(ZBX_SOCKET s, gnutls_session_t session, ssize_t err)
{
	zbx_pollfd_t	pd;
	int		ret;
	short		event;

	ZBX_UNUSED(err);

	pd.fd = s;
	tls_socket_event(session, err, &pd.events);
	event = pd.events;

	if (0 > (ret = zbx_socket_poll(&pd, 1, ZBX_SOCKET_POLL_TIMEOUT)))
	{
		if (SUCCEED != zbx_socket_had_nonblocking_error())
			return FAIL;

		return SUCCEED;
	}

	if (1 == ret && 0 == (pd.revents & event))
		return FAIL;

	return SUCCEED;
}

static int	tls_is_nonblocking_error(ssize_t err)
{
	if (GNUTLS_E_INTERRUPTED == err || GNUTLS_E_AGAIN == err)
		return SUCCEED;

	return FAIL;
}

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

	if (0 != (zbx_get_program_type_cb() & (ZBX_PROGRAM_TYPE_PROXY | ZBX_PROGRAM_TYPE_SERVER)))
	{
		/* call the function zbx_dc_get_psk_by_identity() by pointer */
		if (0 < find_psk_in_cache_cb((const unsigned char *)psk_identity, tls_psk_hex, &psk_usage))
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

		if (0 != (zbx_get_program_type_cb() & ZBX_PROGRAM_TYPE_PROXY) && 0 < my_psk_identity_len && 0 ==
				strcmp(my_psk_identity, psk_identity))
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
	else if (0 != (zbx_get_program_type_cb() & ZBX_PROGRAM_TYPE_AGENTD))
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
	gnutls_x509_crt_t	cert;
	int			res;
	gnutls_datum_t		cert_print;

	if (SUCCEED != ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_DEBUG))
		return;

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

	zbx_free(error);
}

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
	gnutls_x509_crt_t	cert;
	gnutls_x509_dn_t	dn;
	int			res;

	if ((NULL == issuer || '\0' == *issuer) && (NULL == subject || '\0' == *subject))
		return SUCCEED;

	tls_issuer[0] = '\0';
	tls_subject[0] = '\0';

	if (NULL == (cert = zbx_get_peer_cert(tls_ctx->ctx, error)))
		return FAIL;

	if (NULL != issuer && '\0' != *issuer)
	{
		if (0 != (res = gnutls_x509_crt_get_issuer(cert, &dn)))
		{
			*error = zbx_dsprintf(*error, "gnutls_x509_crt_get_issuer() failed: %d %s", res,
					gnutls_strerror(res));
			gnutls_x509_crt_deinit(cert);
			return FAIL;
		}

		if (SUCCEED != zbx_x509_dn_gets(dn, tls_issuer, sizeof(tls_issuer), error))
		{
			gnutls_x509_crt_deinit(cert);
			return FAIL;
		}
	}

	if (NULL != subject && '\0' != *subject)
	{
		if (0 != (res = gnutls_x509_crt_get_subject(cert, &dn)))
		{
			*error = zbx_dsprintf(*error, "gnutls_x509_crt_get_subject() failed: %d %s", res,
					gnutls_strerror(res));
			gnutls_x509_crt_deinit(cert);
			return FAIL;
		}

		if (SUCCEED != zbx_x509_dn_gets(dn, tls_subject, sizeof(tls_subject), error))
		{
			gnutls_x509_crt_deinit(cert);
			return FAIL;
		}
	}

	gnutls_x509_crt_deinit(cert);

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
 * Purpose: initialize TLS library, log library version                       *
 *                                                                            *
 * Comments:                                                                  *
 *     Some crypto libraries require initialization. On Unix the              *
 *     initialization is done separately in each child process which uses     *
 *     crypto libraries. On MS Windows it is done in the first thread.        *
 *                                                                            *
 *     Flag 'init_status' is used to prevent library deinitialization on exit *
 *     if it was not yet initialized (can happen if termination signal is     *
 *     received) and library initialization in threads when it was done in    *
 *     parent process.                                                        *
 *                                                                            *
 ******************************************************************************/
static void	zbx_tls_library_init(zbx_tls_status_t status)
{
	/* skip initialization in threads if it was already done in parent process */
	if (ZBX_TLS_INIT_NONE != tls_status)
		return;

	if (GNUTLS_E_SUCCESS != gnutls_global_init())
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize GnuTLS library");
		exit(EXIT_FAILURE);
	}

	tls_status = status;

	zabbix_log(LOG_LEVEL_DEBUG, "GnuTLS library (version %s) initialized", gnutls_check_version(NULL));
}

/******************************************************************************
 *                                                                            *
 * Purpose: deinitialize TLS library                                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_tls_library_deinit(zbx_tls_status_t status)
{
	if (status == tls_status)
	{
		tls_status = 0;
		gnutls_global_deinit();
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize TLS library in a parent process                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_tls_init_parent(zbx_get_program_type_f zbx_get_program_type_cb_arg)
{
	zbx_get_program_type_cb = zbx_get_program_type_cb_arg;

	zbx_tls_library_init(ZBX_TLS_INIT_THREADS);
}

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

/******************************************************************************
 *                                                                            *
 * Purpose: read available configuration parameters and initialize TLS        *
 *          library in a child process                                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_tls_init_child(const zbx_config_tls_t *config_tls, zbx_get_program_type_f zbx_get_program_type_cb_arg,
		zbx_find_psk_in_cache_f zbx_find_psk_in_cache_cb_arg)
{
	int			res;
#ifndef _WINDOWS
	sigset_t		mask, orig_mask;
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_get_program_type_cb = zbx_get_program_type_cb_arg;
	find_psk_in_cache_cb = zbx_find_psk_in_cache_cb_arg;
#ifndef _WINDOWS
	/* Invalid TLS parameters will cause exit. Once one process exits the parent process will send SIGHUP to */
	/* child processes which may be on their way to exit on their own - do not interrupt them, block signal */
	/* SIGHUP and unblock it when TLS parameters are good and libraries are initialized. */
	sigemptyset(&mask);
	sigaddset(&mask, SIGTERM);
	sigaddset(&mask, SIGHUP);
	sigaddset(&mask, SIGUSR2);
	sigaddset(&mask, SIGQUIT);
	zbx_sigmask(SIG_BLOCK, &mask, &orig_mask);
#endif
	zbx_tls_library_init(ZBX_TLS_INIT_PROCESS);

	/* need to allocate certificate credentials store? */

	if (NULL != config_tls->cert_file)
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
	if (NULL != config_tls->ca_file)
	{
		if (0 < (res = gnutls_certificate_set_x509_trust_file(my_cert_creds, config_tls->ca_file,
				GNUTLS_X509_FMT_PEM)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s() loaded %d CA certificate(s) from file \"%s\"",
					__func__, res, config_tls->ca_file);
		}
		else if (0 == res)
		{
			zabbix_log(LOG_LEVEL_WARNING, "no CA certificate(s) in file \"%s\"", config_tls->ca_file);
		}
		else
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot parse CA certificate(s) in file \"%s\": %d: %s",
					config_tls->ca_file, res, gnutls_strerror(res));
			zbx_tls_free();
			exit(EXIT_FAILURE);
		}
	}

	/* 'TLSCRLFile' parameter (in zabbix_server.conf, zabbix_proxy.conf, zabbix_agentd.conf). */
	/* Load CRL (certificate revocation list) file. */
	if (NULL != config_tls->crl_file)
	{
		if (0 < (res = gnutls_certificate_set_x509_crl_file(my_cert_creds, config_tls->crl_file,
				GNUTLS_X509_FMT_PEM)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s() loaded %d CRL(s) from file \"%s\"", __func__, res,
					config_tls->crl_file);
		}
		else if (0 == res)
		{
			zabbix_log(LOG_LEVEL_WARNING, "no CRL(s) in file \"%s\"", config_tls->crl_file);
		}
		else
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot parse CRL file \"%s\": %d: %s", config_tls->crl_file,
					res, gnutls_strerror(res));
			zbx_tls_free();
			exit(EXIT_FAILURE);
		}
	}

	/* 'TLSCertFile' and 'TLSKeyFile' parameters (in zabbix_server.conf, zabbix_proxy.conf, zabbix_agentd.conf). */
	/* Load certificate and private key. */
	if (NULL != config_tls->cert_file)
	{
		if (GNUTLS_E_SUCCESS != (res = gnutls_certificate_set_x509_key_file(my_cert_creds,
				config_tls->cert_file, config_tls->key_file, GNUTLS_X509_FMT_PEM)))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot load certificate or private key from file \"%s\" or \"%s\":"
					" %d: %s", config_tls->cert_file, config_tls->key_file, res,
					gnutls_strerror(res));
			zbx_tls_free();
			exit(EXIT_FAILURE);
		}
		else
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s() loaded certificate from file \"%s\"", __func__,
					config_tls->cert_file);
			zabbix_log(LOG_LEVEL_DEBUG, "%s() loaded private key from file \"%s\"", __func__,
					config_tls->key_file);
		}
	}

	/* 'TLSPSKIdentity' and 'TLSPSKFile' parameters (in zabbix_proxy.conf, zabbix_agentd.conf). */
	/* Load pre-shared key and identity to be used with the pre-shared key. */
	if (NULL != config_tls->psk_file)
	{
		gnutls_datum_t	key;

		my_psk_identity = config_tls->psk_identity;
		my_psk_identity_len = strlen(my_psk_identity);

		zbx_check_psk_identity_len(my_psk_identity_len);

		zbx_read_psk_file(config_tls->psk_file, &my_psk, &my_psk_len);

		key.data = (unsigned char *)my_psk;
		key.size = (unsigned int)my_psk_len;

		/* allocate here only PSK credential stores which do not change (e.g. for proxy communication with */
		/* server) */

		if (0 != (zbx_get_program_type_cb() & (ZBX_PROGRAM_TYPE_PROXY_ACTIVE | ZBX_PROGRAM_TYPE_AGENTD |
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

		if (0 != (zbx_get_program_type_cb() & (ZBX_PROGRAM_TYPE_PROXY_PASSIVE | ZBX_PROGRAM_TYPE_AGENTD)))
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

		zabbix_log(LOG_LEVEL_DEBUG, "%s() loaded PSK identity \"%s\"", __func__, config_tls->psk_identity);
		zabbix_log(LOG_LEVEL_DEBUG, "%s() loaded PSK from file \"%s\"", __func__, config_tls->psk_file);
	}

	/* Certificate always comes from configuration file. Set up ciphersuites. */
	if (NULL != my_cert_creds)
	{
		const char	*priority_str;

		if (NULL == config_tls->cipher_cert)
		{
			/* for GnuTLS 3.1.18 and up */
			priority_str = "NONE:+VERS-TLS1.2:+ECDHE-RSA:+RSA:+AES-128-GCM:+AES-128-CBC:+AEAD:+SHA256:"
					"+SHA1:+CURVE-ALL:+COMP-NULL:+SIGN-ALL:+CTYPE-X.509";

			zbx_gnutls_priority_init_or_exit(&ciphersuites_cert, priority_str,
					"\"ciphersuites_cert\" with built-in default value");
		}
		else
		{
			priority_str = config_tls->cipher_cert;

			zbx_gnutls_priority_init_or_exit(&ciphersuites_cert, priority_str,
					"\"ciphersuites_cert\" with TLSCipherCert or --tls-cipher parameter");
		}

		zbx_log_ciphersuites(__func__, "certificate", ciphersuites_cert);
	}

	/* PSK can come from configuration file (in proxy, agentd) and later from database (in server, proxy). */
	/* Configure ciphersuites just in case they will be used. */
	if (NULL != my_psk_client_creds || NULL != my_psk_server_creds ||
			0 != (zbx_get_program_type_cb() & (ZBX_PROGRAM_TYPE_SERVER | ZBX_PROGRAM_TYPE_PROXY)))
	{
		const char	*priority_str;

		if (NULL == config_tls->cipher_psk)
		{
			/* for GnuTLS 3.1.18 and up */
			priority_str = "NONE:+VERS-TLS1.2:+ECDHE-PSK:+PSK:+AES-128-GCM:+AES-128-CBC:+AEAD:+SHA256:"
					"+SHA1:+CURVE-ALL:+COMP-NULL:+SIGN-ALL";

			zbx_gnutls_priority_init_or_exit(&ciphersuites_psk, priority_str,
					"\"ciphersuites_psk\" with built-in default value");
		}
		else
		{
			priority_str = config_tls->cipher_psk;

			zbx_gnutls_priority_init_or_exit(&ciphersuites_psk, priority_str,
					"\"ciphersuites_psk\" with TLSCipherPSK or --tls-cipher parameter");
		}

		zbx_log_ciphersuites(__func__, "PSK", ciphersuites_psk);
	}

	/* Sometimes we need to be ready for both certificate and PSK whichever comes in. Set up a combined list of */
	/* ciphersuites. */
	if (NULL != my_cert_creds && (NULL != my_psk_client_creds || NULL != my_psk_server_creds ||
			0 != (zbx_get_program_type_cb() & (ZBX_PROGRAM_TYPE_SERVER | ZBX_PROGRAM_TYPE_PROXY))))
	{
		const char	*priority_str;

		if (NULL == config_tls->cipher_all)
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
			priority_str = config_tls->cipher_all;

			zbx_gnutls_priority_init_or_exit(&ciphersuites_all, priority_str,
					"\"ciphersuites_all\" with TLSCipherAll parameter");
		}

		zbx_log_ciphersuites(__func__, "certificate and PSK", ciphersuites_all);
	}
#ifndef _WINDOWS
	zbx_sigmask(SIG_SETMASK, &orig_mask, NULL);
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: release TLS library resources allocated in zbx_tls_init_parent()  *
 *          and zbx_tls_init_child()                                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_tls_free(void)
{
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

	zbx_tls_library_deinit(ZBX_TLS_INIT_PROCESS);
}

static const char	*tls_error_string(int err)
{
	switch(err)
	{
		case GNUTLS_E_INTERRUPTED:
			return "GNUTLS_E_INTERRUPTED";
		case GNUTLS_E_AGAIN:
			return "GNUTLS_E_AGAIN";
		default:
			return "unknown error";
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: establish a TLS connection over an established TCP connection     *
 *                                                                            *
 * Parameters:                                                                *
 *     s           - [IN] socket with opened connection                       *
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
 *     event       - [OUT] may be NULL for blocking TLS handshake, otherwise  *
 *                         informs caller to wait for POLLIN or POLLOUT and   *
 *                         retry function to complete async TLS handshake     *
 *     error       - [OUT] dynamically allocated memory with error message    *
 *                                                                            *
 * Return value:                                                              *
 *     SUCCEED - successful TLS handshake with a valid certificate or PSK     *
 *     FAIL - an error occurred or retry is needed if event is filled         *
 *                                                                            *
 ******************************************************************************/
int	zbx_tls_connect(zbx_socket_t *s, unsigned int tls_connect, const char *tls_arg1, const char *tls_arg2,
		const char *server_name, short *event, char **error)
{
	int	ret = FAIL, res;

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

	if (NULL == s->tls_ctx)
	{
		s->tls_ctx = zbx_malloc(s->tls_ctx, sizeof(zbx_tls_context_t));
		s->tls_ctx->ctx = NULL;
		s->tls_ctx->psk_client_creds = NULL;
		s->tls_ctx->psk_server_creds = NULL;
		s->tls_ctx->close_notify_received = 0;

		if (GNUTLS_E_SUCCESS != (res = gnutls_init(&s->tls_ctx->ctx, GNUTLS_CLIENT | GNUTLS_NO_EXTENSIONS)))
				/* GNUTLS_NO_EXTENSIONS is used because we do not currently support extensions (e.g. */
				/* session tickets and OCSP) */
		{
			*error = zbx_dsprintf(*error, "gnutls_init() failed: %d %s", res, gnutls_strerror(res));
			goto out;
		}

		if (ZBX_TCP_SEC_TLS_CERT == tls_connect)
		{
			if (NULL == ciphersuites_cert)
			{
				*error = zbx_strdup(*error, "cannot connect with TLS and certificate: no valid"
						" certificate loaded");
				goto out;
			}

			if (GNUTLS_E_SUCCESS != (res = gnutls_priority_set(s->tls_ctx->ctx, ciphersuites_cert)))
			{
				*error = zbx_dsprintf(*error, "gnutls_priority_set() for 'ciphersuites_cert' failed:"
						" %d %s", res, gnutls_strerror(res));
				goto out;
			}

			if (GNUTLS_E_SUCCESS != (res = gnutls_credentials_set(s->tls_ctx->ctx, GNUTLS_CRD_CERTIFICATE,
					my_cert_creds)))
			{
				*error = zbx_dsprintf(*error, "gnutls_credentials_set() for certificate failed: %d %s",
						res, gnutls_strerror(res));
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
				*error = zbx_dsprintf(*error, "gnutls_priority_set() for 'ciphersuites_psk' failed: %d"
						" %s", res, gnutls_strerror(res));
				goto out;
			}

			if (NULL == tls_arg2)	/* PSK is not set from DB */
			{
				/* set up the PSK from a configuration file (always in agentd and a case in active  */
				/* proxy when it connects to server) */

				if (GNUTLS_E_SUCCESS != (res = gnutls_credentials_set(s->tls_ctx->ctx, GNUTLS_CRD_PSK,
						my_psk_client_creds)))
				{
					*error = zbx_dsprintf(*error, "gnutls_credentials_set() for psk failed: %d %s",
							res, gnutls_strerror(res));
					goto out;
				}
			}
			else
			{
				/* PSK comes from a database (case for a server/proxy when it connects to an agent */
				/* for passive checks, for a server when it connects to a passive proxy) */

				gnutls_datum_t	key;
				int		psk_len;

				if (0 >= (psk_len = zbx_hex2bin((const unsigned char *)tls_arg2, s->tls_ctx->psk_buf,
						sizeof(s->tls_ctx->psk_buf))))
				{
					*error = zbx_strdup(*error, "invalid PSK");
					goto out;
				}

				if (GNUTLS_E_SUCCESS != (res = gnutls_psk_allocate_client_credentials(
						&s->tls_ctx->psk_client_creds)))
				{
					*error = zbx_dsprintf(*error, "gnutls_psk_allocate_client_credentials() failed:"
							" %d %s", res, gnutls_strerror(res));
					goto out;
				}

				key.data = s->tls_ctx->psk_buf;
				key.size = (unsigned int)psk_len;

				/* Simplified. 'tls_arg1' (PSK identity) should have been prepared as required by */
				/* RFC 4518.*/
				if (GNUTLS_E_SUCCESS != (res = gnutls_psk_set_client_credentials(
						s->tls_ctx->psk_client_creds, tls_arg1, &key, GNUTLS_PSK_KEY_RAW)))
				{
					*error = zbx_dsprintf(*error, "gnutls_psk_set_client_credentials() failed: %d"
							" %s", res, gnutls_strerror(res));
					goto out;
				}

				if (GNUTLS_E_SUCCESS != (res = gnutls_credentials_set(s->tls_ctx->ctx, GNUTLS_CRD_PSK,
						s->tls_ctx->psk_client_creds)))
				{
					*error = zbx_dsprintf(*error, "gnutls_credentials_set() for psk failed: %d %s",
							res, gnutls_strerror(res));
					goto out;
				}
			}
		}

		if (NULL != server_name && ZBX_TCP_SEC_UNENCRYPTED != tls_connect &&
				GNUTLS_E_SUCCESS != gnutls_server_name_set( s->tls_ctx->ctx, GNUTLS_NAME_DNS,
				server_name, strlen(server_name)))
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
	}

	/* TLS handshake */

	while (GNUTLS_E_SUCCESS != (res = gnutls_handshake(s->tls_ctx->ctx)))
	{
		if (SUCCEED == tls_is_nonblocking_error(res))
		{
			if (NULL != event)
			{
				tls_socket_event(s->tls_ctx->ctx, 0, event);
				zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, tls_error_string(res));
				return FAIL;
			}

			if (FAIL == tls_socket_wait(s->socket, s->tls_ctx->ctx, 0))
			{
				*error = zbx_dsprintf(*error, "cannot wait for TLS handshake: %s",
						zbx_strerror_from_system(zbx_socket_last_error()));
				goto out;
			}

			if (SUCCEED != zbx_socket_check_deadline(s))
			{
				*error = zbx_strdup(*error, "gnutls_handshake() timed out");
				goto out;
			}

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
int	zbx_tls_accept(zbx_socket_t *s, unsigned int tls_accept, char **error)
{
	int				ret = FAIL, res;
	gnutls_credentials_type_t	creds;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	/* set up TLS context */

	s->tls_ctx = zbx_malloc(s->tls_ctx, sizeof(zbx_tls_context_t));
	s->tls_ctx->ctx = NULL;
	s->tls_ctx->psk_client_creds = NULL;
	s->tls_ctx->psk_server_creds = NULL;
	s->tls_ctx->close_notify_received = 0;

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
		if (0 != (zbx_get_program_type_cb() & ZBX_PROGRAM_TYPE_AGENTD) &&
				GNUTLS_E_SUCCESS != (res = gnutls_credentials_set(s->tls_ctx->ctx, GNUTLS_CRD_PSK,
				my_psk_server_creds)))
		{
			*error = zbx_dsprintf(*error, "gnutls_credentials_set() for my_psk_server_creds failed: %d %s",
					res, gnutls_strerror(res));
			goto out;
		}
		else if (0 != (zbx_get_program_type_cb() & (ZBX_PROGRAM_TYPE_PROXY | ZBX_PROGRAM_TYPE_SERVER)))
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

	while (GNUTLS_E_SUCCESS != (res = gnutls_handshake(s->tls_ctx->ctx)))
	{
		if (GNUTLS_E_INTERRUPTED == res || GNUTLS_E_AGAIN == res)
		{
			if (FAIL == tls_socket_wait(s->socket, s->tls_ctx->ctx, 0))
			{
				*error = zbx_dsprintf(*error, "cannot wait for TLS handshake: %s",
						zbx_strerror_from_system(zbx_socket_last_error()));
				goto out;
			}

			if (SUCCEED != zbx_socket_check_deadline(s))
			{
				*error = zbx_strdup(*error, "gnutls_handshake() timed out");
				goto out;
			}

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

ssize_t	zbx_tls_write(zbx_socket_t *s, const char *buf, size_t len, short *event, char **error)
{
	ssize_t		offset = 0, n;

	while (1)
	{
		if (0 == (n = (ssize_t)gnutls_record_send(s->tls_ctx->ctx, buf + offset, len - (size_t)offset)))
			break;

		if (0 > n)
		{
			if (SUCCEED != tls_is_nonblocking_error(n))
				break;

			if (NULL != event)
			{
				tls_socket_event(s->tls_ctx->ctx, n, event);
				return offset;
			}

			if (FAIL == tls_socket_wait(s->socket, s->tls_ctx->ctx, n))
			{
				*error = zbx_dsprintf(*error, "cannot wait socket: %s",
						zbx_strerror_from_system(zbx_socket_last_error()));
				return ZBX_PROTO_ERROR;
			}
		}
		else
		{
			offset += n;

			if (offset == (ssize_t)len)
				break;
		}

		if (SUCCEED != zbx_socket_check_deadline(s))
		{
			*error = zbx_strdup(*error, "write timeout");
			return ZBX_PROTO_ERROR;
		}
	}

	if (0 == n)
	{
		*error = zbx_dsprintf(*error, "connection closed");
		return ZBX_PROTO_ERROR;
	}

	if (0 > n)
	{
		*error = zbx_dsprintf(*error, "gnutls_record_send() failed: " ZBX_FS_SSIZE_T " %s",
				(zbx_fs_ssize_t)n, gnutls_strerror((int)n));

		return ZBX_PROTO_ERROR;
	}

	return offset;
}

ssize_t	zbx_tls_read(zbx_socket_t *s, char *buf, size_t len, short *events, char **error)
{
	ssize_t		n = 0;

	while (1)
	{
		if (0 <= (n = (ssize_t)gnutls_record_recv(s->tls_ctx->ctx, buf, len)))
			break;

		if (SUCCEED != tls_is_nonblocking_error(n))
			break;

		if (NULL != events)
		{
			tls_socket_event(s->tls_ctx->ctx, n, events);
			return ZBX_PROTO_ERROR;
		}

		if (FAIL == tls_socket_wait(s->socket, s->tls_ctx->ctx, n))
		{
			*error = zbx_dsprintf(*error, "cannot wait socket: %s",
					zbx_strerror_from_system(zbx_socket_last_error()));
			return ZBX_PROTO_ERROR;
		}

		if (SUCCEED != zbx_socket_check_deadline(s))
		{
			*error = zbx_strdup(*error, "read timeout");
			return ZBX_PROTO_ERROR;
		}
	}

	if (0 == n)
	{
		s->tls_ctx->close_notify_received = 1;
		return n;
	}

	if (0 > n)
	{
		/* in case of rehandshake a GNUTLS_E_REHANDSHAKE will be returned, deal with it as with error */
		*error = zbx_dsprintf(*error, "gnutls_record_recv() failed: " ZBX_FS_SSIZE_T " %s",
				(zbx_fs_ssize_t)n, gnutls_strerror((int)n));

		return ZBX_PROTO_ERROR;
	}

	return n;
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

	zbx_socket_set_deadline(s, s->timeout);

	if (NULL != s->tls_ctx->ctx)
	{
		/* shutdown TLS connection */
		while (0 == s->tls_ctx->close_notify_received &&
				GNUTLS_E_SUCCESS != (res = gnutls_bye(s->tls_ctx->ctx, GNUTLS_SHUT_WR)))
		{
			if (SUCCEED != tls_is_nonblocking_error(res))
			{
				zabbix_log(LOG_LEVEL_WARNING, "gnutls_bye() with %s returned error code: %d %s",
						s->peer, res, gnutls_strerror(res));

				if (0 != gnutls_error_is_fatal(res))
					break;
			}

			if (FAIL == tls_socket_wait(s->socket, s->tls_ctx->ctx, res))
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot wait socket: %s",
						zbx_strerror_from_system(zbx_socket_last_error()));
				break;
			}

			if (SUCCEED != zbx_socket_check_deadline(s))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "timeout while closing tls connection");
				break;
			}
		}

		gnutls_credentials_clear(s->tls_ctx->ctx);
		gnutls_deinit(s->tls_ctx->ctx);
	}

	if (NULL != s->tls_ctx->psk_client_creds)
		gnutls_psk_free_client_credentials(s->tls_ctx->psk_client_creds);

	if (NULL != s->tls_ctx->psk_server_creds)
		gnutls_psk_free_server_credentials(s->tls_ctx->psk_server_creds);
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
	gnutls_x509_crt_t	peer_cert;
	gnutls_x509_dn_t	dn;
	int			res;

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
int	zbx_tls_get_attr_psk(const zbx_socket_t *s, zbx_tls_conn_attr_t *attr)
{
	if (NULL == (attr->psk_identity = gnutls_psk_server_get_username(s->tls_ctx->ctx)))
		return FAIL;

	attr->psk_identity_len = strlen(attr->psk_identity);
	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get PSK attributes or certificate attributes from the context of  *
 *          established connection                                            *
 *                                                                            *
 * Comments:                                                                  *
 *     This function can be used only on server-side of TLS connection.       *
 *     GnuTLS makes it asymmetric - see documentation for                     *
 *     gnutls_psk_server_get_username() and gnutls_psk_client_get_hint()      *
 *     (the latter function is not used in Zabbix).                           *
 *     Implementation for OpenSSL is server-side only, too.                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_tls_get_attr(const zbx_socket_t *sock, zbx_tls_conn_attr_t *attr, char **error)
{
	if (ZBX_TCP_SEC_TLS_CERT == sock->connection_type)
	{
		if (SUCCEED != zbx_tls_get_attr_cert(sock, attr))
		{
			*error = zbx_strdup(*error, "internal error: cannot get connection attributes");
			THIS_SHOULD_NEVER_HAPPEN;
			return FAIL;
		}
	}
	else if (ZBX_TCP_SEC_TLS_PSK == sock->connection_type)
	{
		if (SUCCEED != zbx_tls_get_attr_psk(sock, attr))
		{
			*error = zbx_strdup(*error, "internal error: cannot get connection attributes");
			THIS_SHOULD_NEVER_HAPPEN;
			return FAIL;
		}
	}
	else if (ZBX_TCP_SEC_UNENCRYPTED != sock->connection_type)
	{
		*error = zbx_strdup(*error, "internal error: invalid connection type");
		THIS_SHOULD_NEVER_HAPPEN;
		return FAIL;
	}

	attr->connection_type = sock->connection_type;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: validate PSK attributes or certificate attributes from the        *
 *          context of established connection                                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_tls_validate_attr(const zbx_tls_conn_attr_t *attr, const char *tls_issuer,
		const char *tls_subject, const char *tls_psk_identity, const char **msg)
{
	if (ZBX_TCP_SEC_TLS_CERT == attr->connection_type)
	{
		/* simplified match, not compliant with RFC 4517, 4518 */
		if ('\0' != *tls_issuer && 0 != strcmp(tls_issuer, attr->issuer))
		{
			*msg = "certificate issuer does not match";
			return FAIL;
		}

		/* simplified match, not compliant with RFC 4517, 4518 */
		if ('\0' != *tls_subject && 0 != strcmp(tls_subject, attr->subject))
		{
			*msg = "certificate subject does not match";
			return FAIL;
		}
	}
	else if (ZBX_TCP_SEC_TLS_PSK == attr->connection_type)
	{
		if (NULL != tls_psk_identity)
		{
			if (strlen(tls_psk_identity) != attr->psk_identity_len ||
					0 != memcmp(tls_psk_identity, attr->psk_identity,
					attr->psk_identity_len))
			{
				*msg = "false PSK identity";
				return FAIL;
			}
		}
		else
		{
			*msg = "missing PSK";
			return FAIL;
		}
	}

	return SUCCEED;
}

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
	args->my_cert_creds = my_cert_creds;
	args->my_psk_client_creds = my_psk_client_creds;
	args->ciphersuites_cert = ciphersuites_cert;
	args->ciphersuites_psk = ciphersuites_psk;
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
	my_cert_creds = args->my_cert_creds;
	my_psk_client_creds = args->my_psk_client_creds;
	ciphersuites_cert = args->ciphersuites_cert;
	ciphersuites_psk = args->ciphersuites_psk;
}
#endif

unsigned int	zbx_tls_get_psk_usage(void)
{
	return	psk_usage;
}

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
