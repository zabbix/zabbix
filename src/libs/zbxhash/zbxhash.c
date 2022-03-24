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

#include "zbxhash.h"

/******************************************************************************
 *                                                                            *
 * Purpose: get a textual representation of md5 sum                           *
 *                                                                            *
 * Parameters:                                                                *
 *          md5 - [IN] buffer with md5 sum                                    *
 *          str - [OUT] preallocated string with a text representation of MD5 *
 *                     sum. String size must be at least                      *
 *                     ZBX_MD5_PRINT_BUF_LEN bytes.                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_md5buf2str(const md5_byte_t *md5, char *str)
{
	const char	*hex = "0123456789abcdef";
	char		*p = str;
	int		i;

	for (i = 0; i < MD5_DIGEST_SIZE; i++)
	{
		*p++ = hex[md5[i] >> 4];
		*p++ = hex[md5[i] & 15];
	}

	*p = '\0';
}

/******************************************************************************
 *                                                                            *
 * Purpose: creates semi-unique token based on the seed and current timestamp *
 *                                                                            *
 * Parameters:  seed - [IN] the seed                                          *
 *                                                                            *
 * Return value: Hexadecimal token string, must be freed by caller            *
 *                                                                            *
 * Comments: if you change token creation algorithm do not forget to adjust   *
 *           ZBX_DATA_SESSION_TOKEN_SIZE definition                           *
 *                                                                            *
 ******************************************************************************/
char	*zbx_create_token(zbx_uint64_t seed)
{
	const char	*hex = "0123456789abcdef";
	zbx_timespec_t	ts;
	md5_state_t	state;
	md5_byte_t	hash[MD5_DIGEST_SIZE];
	int		i;
	char		*token, *ptr;

	ptr = token = (char *)zbx_malloc(NULL, ZBX_DATA_SESSION_TOKEN_SIZE + 1);

	zbx_timespec(&ts);

	zbx_md5_init(&state);
	zbx_md5_append(&state, (const md5_byte_t *)&seed, (int)sizeof(seed));
	zbx_md5_append(&state, (const md5_byte_t *)&ts, (int)sizeof(ts));
	zbx_md5_finish(&state, hash);

	for (i = 0; i < MD5_DIGEST_SIZE; i++)
	{
		*ptr++ = hex[hash[i] >> 4];
		*ptr++ = hex[hash[i] & 15];
	}

	*ptr = '\0';

	return token;
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
