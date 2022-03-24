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

#include "zbxcrypto.h"

#include "zbxhash.h"
#include "common.h"

/******************************************************************************
 *                                                                            *
 * Purpose:                                                                   *
 *     convert ASCII hex digit string to a binary representation (byte        *
 *     string)                                                                *
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
 * Purpose: calculate UUID version 4 as string of 32 symbols                  *
 *                                                                            *
 * Parameters: seed    - [IN] string for seed calculation                     *
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
	md5_byte_t	hash[MD5_DIGEST_SIZE];

#define ZBX_UUID_VERSION	4
#define ZBX_UUID_VARIANT	2

	ptr = uuid = (char *)zbx_malloc(NULL, 2 * MD5_DIGEST_SIZE + 1);

	zbx_md5_init(&state);
	zbx_md5_append(&state, (const md5_byte_t *)seed, (int)strlen(seed));
	zbx_md5_finish(&state, hash);

	hash[6] = (md5_byte_t)((hash[6] & 0xf) | (ZBX_UUID_VERSION << 4));
	hash[8] = (md5_byte_t)((hash[8] & 0x3f) | (ZBX_UUID_VARIANT << 6));

	for (i = 0; i < MD5_DIGEST_SIZE; i++)
	{
		*ptr++ = hex[(hash[i] >> 4) & 0xf];
		*ptr++ = hex[hash[i] & 0xf];
	}

	*ptr = '\0';

	return uuid;
}
