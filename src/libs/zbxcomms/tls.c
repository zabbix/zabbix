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

#include "tls.h"

#include "zbxcrypto.h"

void	zbx_psk_warn_misconfig(const char *psk_identity)
{
	zabbix_log(LOG_LEVEL_WARNING, "same PSK identity \"%s\" but different PSK values used in proxy configuration"
			" file, for host or for autoregistration; autoregistration will not be allowed", psk_identity);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Check PSK identity length. Exit if length exceeds the maximum.    *
 *                                                                            *
 ******************************************************************************/
void	zbx_check_psk_identity_len(size_t psk_identity_len)
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
 *     read a pre-shared key from a file and convert it from                  *
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
void	zbx_read_psk_file(const char *file_name, char **psk, size_t *psk_len)
{
	FILE		*f;
	size_t		len;
	int		len_bin, ret = FAIL;
	char		buf[HOST_TLS_PSK_LEN_MAX + 2];	/* up to 512 bytes of hex-digits, maybe 1-2 bytes for '\n', */
							/* 1 byte for terminating '\0' */
	char		buf_bin[HOST_TLS_PSK_LEN / 2];	/* up to 256 bytes of binary PSK */

	if (NULL == (f = fopen(file_name, "r")))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot open file \"%s\": %s", file_name, zbx_strerror(errno));
		goto out;
	}

	if (NULL == fgets(buf, (int)sizeof(buf), f))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot read from file \"%s\" or file empty", file_name);
		goto out;
	}

	buf[strcspn(buf, "\r\n")] = '\0';	/* discard newline at the end of string */

	if (0 == (len = strlen(buf)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "file \"%s\" is empty", file_name);
		goto out;
	}

	if (HOST_TLS_PSK_LEN_MIN > len)
	{
		zabbix_log(LOG_LEVEL_CRIT, "PSK in file \"%s\" is too short. Minimum is %d hex-digits",
				file_name, HOST_TLS_PSK_LEN_MIN);
		goto out;
	}

	if (HOST_TLS_PSK_LEN < len)
	{
		zabbix_log(LOG_LEVEL_CRIT, "PSK in file \"%s\" is too long. Maximum is %d hex-digits",
				file_name, HOST_TLS_PSK_LEN);
		goto out;
	}

	if (0 >= (len_bin = zbx_hex2bin((unsigned char *)buf, (unsigned char *)buf_bin, sizeof(buf_bin))))
	{
		zabbix_log(LOG_LEVEL_CRIT, "invalid PSK in file \"%s\"", file_name);
		goto out;
	}

	*psk_len = (size_t)len_bin;
	*psk = zbx_malloc(*psk, *psk_len);
	memcpy(*psk, buf_bin, *psk_len);

	ret = SUCCEED;
out:
	if (NULL != f && 0 != fclose(f))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot close file \"%s\": %s", file_name, zbx_strerror(errno));
		ret = FAIL;
	}

	if (SUCCEED == ret)
		return;

	zbx_tls_free();
	exit(EXIT_FAILURE);
}

/******************************************************************************
 *                                                                            *
 * Purpose:                                                                   *
 *     check server certificate issuer and subject (for passive proxies and   *
 *     agent passive checks)                                                  *
 *                                                                            *
 * Parameters:                                                                *
 *     sock            - [IN] connected socket to get certificate from        *
 *     allowed_issuer  - [IN] certificate must be issued by this CA authority *
 *                            (can be NULL if not configured)                 *
 *     allowed_subject - [IN] required certificate subject (can be NULL if    *
 *                            not configured)                                 *
 *     error           - [OUT] dynamically allocated memory with error        *
 *                            message                                         *
 *                                                                            *
 * Return value: SUCCEED (issuer and subject match allowed values) or FAIL    *
 *                                                                            *
 ******************************************************************************/
int	zbx_check_server_issuer_subject(const zbx_socket_t *sock, const char *allowed_issuer,
		const char *allowed_subject, char **error)
{
	zbx_tls_conn_attr_t	attr;

	if (SUCCEED != zbx_tls_get_attr_cert(sock, &attr))
	{
		THIS_SHOULD_NEVER_HAPPEN;

		*error = zbx_dsprintf(*error, "cannot get connection attributes for connection from %s", sock->peer);
		return FAIL;
	}

	/* simplified match, not compliant with RFC 4517, 4518 */
	if (NULL != allowed_issuer && 0 != strcmp(allowed_issuer, attr.issuer))
	{
		*error = zbx_dsprintf(*error, "certificate issuer does not match for %s", sock->peer);
		return FAIL;
	}

	/* simplified match, not compliant with RFC 4517, 4518 */
	if (NULL != allowed_subject && 0 != strcmp(allowed_subject, attr.subject))
	{
		*error = zbx_dsprintf(*error, "certificate subject does not match for %s", sock->peer);
		return FAIL;
	}

	return SUCCEED;
}
