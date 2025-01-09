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

#include "zbxhash.h"

/******************************************************************************
 *                                                                            *
 * Purpose: gets textual representation of md5 sum                            *
 *                                                                            *
 * Parameters:                                                                *
 *          md5 - [IN]  buffer with md5 sum                                   *
 *          str - [OUT] Preallocated string with a text representation of MD5 *
 *                      sum. String size must be at least                     *
 *                      ZBX_MD5_PRINT_BUF_LEN bytes.                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_md5buf2str(const md5_byte_t *md5, char *str)
{
	const char	*hex = "0123456789abcdef";
	char		*p = str;
	int		i;

	for (i = 0; i < ZBX_MD5_DIGEST_SIZE; i++)
	{
		*p++ = hex[md5[i] >> 4];
		*p++ = hex[md5[i] & 15];
	}

	*p = '\0';
}
