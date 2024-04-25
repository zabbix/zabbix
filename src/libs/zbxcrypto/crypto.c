/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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
 * Purpose: convert binary data to hex string                                 *
 *                                                                            *
 * Parameters: bin     - [IN] the data to convert                             *
 *             bin_len - [IN] the number of bytes to convert                  *
 *             out     - [OUT] the output buffer                              *
 *             out_len - [IN] the size of output buffer (should be at least   *
 *                            2 * bin_len + 1)                                *
 *                                                                            *
 * Return value: The number of bytes written (excluding terminating zero)     *
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
