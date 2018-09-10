/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "log.h"

/******************************************************************************
 *                                                                            *
 * Function: zbx_http_url_encode                                              *
 *                                                                            *
 * Purpose: replaces unsafe characters with a '%' followed by two hexadecimal *
 *          digits (the only allowed exception is a space character that can  *
 *          be replaced with a plus (+) sign or with %20).to url encode       *
 *                                                                            *
 * Parameters:  source  - [IN] the value to encode                            *
 *              result  - [OUT] encoded string                                *
 *                                                                            *
 ******************************************************************************/
void	zbx_http_url_encode(const char *source, char **result)
{
	char		*target, *buffer;
	const char	*hex = "0123456789ABCDEF";

	buffer = (char *)zbx_malloc(NULL, strlen(source) * 3 + 1);
	target = buffer;

	while ('\0' != *source)
	{
		if (0 == isalnum(*source) && NULL == strchr("-._~", *source))
		{
			/* Percent-encoding */
			*target++ = '%';
			*target++ = hex[(unsigned char)*source >> 4];
			*target++ = hex[(unsigned char)*source & 15];
		}
		else
			*target++ = *source;

		source++;
	}

	*target = '\0';
	zbx_free(*result);
	*result = buffer;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_http_url_decode                                              *
 *                                                                            *
 * Purpose: replaces URL escape sequences ('+' or '%' followed by two         *
 *          hexadecimal digits) with matching characters.                     *
 *                                                                            *
 * Parameters:  source  - [IN] the value to decode                            *
 *              result  - [OUT] decoded string                                *
 *                                                                            *
 * Return value: SUCCEED - the source string was decoded successfully         *
 *               FAIL    - source string contains malformed percent-encoding  *
 *                                                                            *
 ******************************************************************************/
int	zbx_http_url_decode(const char *source, char **result)
{
	const char	*url = source;
	char		*target, *buffer = (char *)zbx_malloc(NULL, strlen(source) + 1);

	target = buffer;

	while ('\0' != *source)
	{
		if ('%' == *source)
		{
			/* Percent-decoding */
			if (FAIL == is_hex_n_range(source + 1, 2, target, sizeof(char), 0, 0xff))
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot perform URL decode of '%s' part of string '%s'",
						source, url);
				zbx_free(buffer);
				break;
			}
			else
				source += 2;
		}
		else if ('+' == *source)
			*target = ' ';
		else
			*target = *source;

		target++;
		source++;
	}

	if (NULL != buffer)
	{
		*target = '\0';
		zbx_free(*result);
		*result = buffer;

		return SUCCEED;
	}

	return FAIL;
}
