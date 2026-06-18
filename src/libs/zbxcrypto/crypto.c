/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#include "zbxcrypto.h"

#include "zbxtime.h"
#include "zbxhash.h"

/******************************************************************************
 *                                                                            *
 * Purpose: converts ASCII hex digit string to binary representation (byte    *
 *          string)                                                           *
 *                                                                            *
 * Parameters:                                                                *
 *     p_hex   - [IN] null-terminated input string                            *
 *     buf     - [OUT] output buffer                                          *
 *     buf_len - [IN] output buffer size                                      *
 *                                                                            *
 * Return value:                                                              *
 *     Number of bytes written into 'buf' on successful conversion.           *
 *     -1 - an error occurred.                                                *
 *                                                                            *
 * Comments:                                                                  *
 *     In case of error incomplete useless data may be written into 'buf'.    *
 *                                                                            *
 ******************************************************************************/
int	zbx_hex2bin(const unsigned char *p_hex, unsigned char *buf, int buf_len)
{
	unsigned char	*q = buf;
	int		len = 0;

	while ('\0' != *p_hex)
	{
		if (0 != isxdigit(*p_hex) && 0 != isxdigit(*(p_hex + 1)) && buf_len > len)
		{
			unsigned char	hi = *p_hex & 0x0f;
			unsigned char	lo;

			if ('9' < *p_hex++)
				hi = (unsigned char)(hi + 9u);

			lo = *p_hex & 0x0f;

			if ('9' < *p_hex++)
				lo = (unsigned char)(lo + 9u);

			*q++ = (unsigned char)(hi << 4 | lo);
			len++;
		}
		else
			return -1;
	}

	return len;
}

/******************************************************************************
 *                                                                            *
 * Purpose: converts binary data to hex string                                *
 *                                                                            *
 * Parameters: bin     - [IN] data to convert                                 *
 *             bin_len - [IN] number of bytes to convert                      *
 *             out     - [OUT] output buffer                                  *
 *             out_len - [IN] size of output buffer (should be at least       *
 *                             2 * bin_len + 1)                               *
 *                                                                            *
 * Return value: The number of bytes written (excluding terminating zero).    *
 *                                                                            *
 ******************************************************************************/
int    zbx_bin2hex(const unsigned char *bin, size_t bin_len, char *out, size_t out_len)
{
	const char	*hex = "0123456789abcdef";
	size_t		i;

	if (bin_len * 2 + 1 > out_len)
		bin_len = (out_len - 1) / 2;

	for (i = 0; i < bin_len; i++)
	{
		*out++ = hex[bin[i] >> 4];
		*out++ = hex[bin[i] & 15];
	}

	*out = '\0';

	return bin_len * 2;
}

/******************************************************************************
 *                                                                            *
 * Purpose: creates semi-unique token based on seed and current timestamp     *
 *                                                                            *
 * Parameters:  seed - [IN]                                                   *
 *                                                                            *
 * Return value: Hexadecimal token string, must be freed by caller.           *
 *                                                                            *
 * Comments: if you change token creation algorithm do not forget to adjust   *
 *           ZBX_SESSION_TOKEN_SIZE macro                                     *
 *                                                                            *
 ******************************************************************************/
char	*zbx_create_token(zbx_uint64_t seed)
{
	const char	*hex = "0123456789abcdef";
	zbx_timespec_t	ts;
	md5_state_t	state;
	md5_byte_t	hash[ZBX_MD5_DIGEST_SIZE];
	int		i;
	char		*token, *ptr;

	ptr = token = (char *)zbx_malloc(NULL, ZBX_SESSION_TOKEN_SIZE + 1);

	zbx_timespec(&ts);

	zbx_md5_init(&state);
	zbx_md5_append(&state, (const md5_byte_t *)&seed, (int)sizeof(seed));
	zbx_md5_append(&state, (const md5_byte_t *)&ts, (int)sizeof(ts));
	zbx_md5_finish(&state, hash);

	for (i = 0; i < ZBX_MD5_DIGEST_SIZE; i++)
	{
		*ptr++ = hex[hash[i] >> 4];
		*ptr++ = hex[hash[i] & 15];
	}

	*ptr = '\0';

	return token;
}

/******************************************************************************
 *                                                                            *
 * Purpose: generates UUID version 4 as string of 32 symbols                  *
 *                                                                            *
 * Parameters: seed - [IN] string for seed calculation                        *
 *                                                                            *
 * Return value: uuid string                                                  *
 *                                                                            *
 ******************************************************************************/
char	*zbx_gen_uuid4(const char *seed)
{
	size_t		i;
	const char	*hex = "0123456789abcdef";
	char		*ptr, *uuid;
	md5_state_t	state;
	md5_byte_t	hash[ZBX_MD5_DIGEST_SIZE];

#define ZBX_UUID_VERSION	4
#define ZBX_UUID_VARIANT	2

	ptr = uuid = (char *)zbx_malloc(NULL, 2 * ZBX_MD5_DIGEST_SIZE + 1);

	zbx_md5_init(&state);
	zbx_md5_append(&state, (const md5_byte_t *)seed, (int)strlen(seed));
	zbx_md5_finish(&state, hash);

	hash[6] = (md5_byte_t)((hash[6] & 0xf) | (ZBX_UUID_VERSION << 4));
	hash[8] = (md5_byte_t)((hash[8] & 0x3f) | (ZBX_UUID_VARIANT << 6));

	for (i = 0; i < ZBX_MD5_DIGEST_SIZE; i++)
	{
		*ptr++ = hex[(hash[i] >> 4) & 0xf];
		*ptr++ = hex[hash[i] & 0xf];
	}

	*ptr = '\0';

	return uuid;
}

/******************************************************************************
 *                                                                            *
 * Purpose: generates UUID version 7 as string of 36 symbols (with hyphens)   *
 *                                                                            *
 * Return value: uuid string: e.g. 019da0d3-3fc7-710d-adfc-48abece93fc2       *
 *                                                                            *
 * Comments: Current implementation is minimal, developed for generating      *
 *           server ID.                                                       *
 *           DO NOT use it for generation of multiple identifiers where       *
 *           cryptographically secure randomness and monotonic time-based     *
 *           ordering is required                                             *
 *           (see https://www.rfc-editor.org/rfc/rfc9562).                    *
 *                                                                            *
 ******************************************************************************/
char	*zbx_gen_uuid7_hyphenated(void)
{
#define ZBX_UUID7_BYTES      16
	const char	*hex = "0123456789abcdef";
	zbx_timespec_t	ts;
	unsigned char	uuid7[ZBX_UUID7_BYTES];
	char		*out, *ptr;
	uint64_t	ts_ms;

	ptr = out = (char *)zbx_malloc(NULL, 2 * ZBX_UUID7_BYTES +
			4 +	/* hyphens */
			1);	/* terminating '\0' */

	zbx_timespec(&ts);
	srand((unsigned int)time(NULL) + (unsigned int)getpid());

	ts_ms = (uint64_t)ts.sec * 1000 + (uint64_t)ts.ns / 1000000;

	/* 6 bytes Unix timestamp */
	uuid7[0] = (ts_ms >> 40) & 0xff;
	uuid7[1] = (ts_ms >> 32) & 0xff;
	uuid7[2] = (ts_ms >> 24) & 0xff;
	uuid7[3] = (ts_ms >> 16) & 0xff;
	uuid7[4] = (ts_ms >> 8) & 0xff;
	uuid7[5] = ts_ms & 0xff;

	/* 10 remaining random bytes */
	for (int i = 6; i < ZBX_UUID7_BYTES; i++)
		uuid7[i] = (unsigned char)(rand() & 0xff);

	/* version */
	uuid7[6] = (uuid7[6] & 0x0f) | 0x70;

	/* variant */
	uuid7[8] = (uuid7[8] & 0x3f) | 0x80;

	/* convert to hex */
	for (int i = 0; i < ZBX_UUID7_BYTES; i++)
	{
		*ptr++ = hex[(uuid7[i] >> 4) & 0xf];
		*ptr++ = hex[uuid7[i] & 0xf];

		if (3 == i || 5 == i || 7 == i || 9 == i)
			*ptr++ = '-';
	}

	*ptr = '\0';

	return out;
#undef ZBX_UUID7_BYTES
}
