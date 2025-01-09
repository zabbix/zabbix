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

#include "zbxserialize.h"

#include "string.h"
#include "zbxtypes.h"

/******************************************************************************
 *                                                                            *
 * Purpose: serialize 31 bit unsigned integer into utf-8 like byte stream     *
 *                                                                            *
 * Parameters: ptr   - [OUT] the output buffer                                *
 *             value - [IN] the value to serialize                            *
 *                                                                            *
 * Return value: The number of bytes written to the buffer.                   *
 *                                                                            *
 * Comments: This serialization method should be used with variables usually  *
 *           having small value while still supporting larger values.         *
 *                                                                            *
 ******************************************************************************/
zbx_uint32_t	zbx_serialize_uint31_compact(unsigned char *ptr, zbx_uint32_t value)
{
	if (0x7f >= value)
	{
		ptr[0] = (unsigned char)value;
		return 1;
	}
	else
	{
		unsigned char	buf[6];
		zbx_uint32_t	len, pos = (zbx_uint32_t)(sizeof(buf) - 1);

		while (value > (zbx_uint32_t)(0x7f >> (sizeof(buf) - pos)))
		{
			buf[pos] = (unsigned char)(0x80 | (value & 0x3f));
			value >>= 6;
			pos--;
		}

		buf[pos] = (unsigned char)(value | (0xfe << (pos + 1)));

		len = (zbx_uint32_t)(sizeof(buf) - pos);
		memcpy(ptr, buf + pos, len);
		return len;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: deserialize 31 bit unsigned integer from utf-8 like byte stream   *
 *                                                                            *
 * Parameters: ptr   - [IN] the byte stream                                   *
 *             value - [OUT] the deserialized value                           *
 *                                                                            *
 * Return value: The number of bytes read from byte stream.                   *
 *                                                                            *
 ******************************************************************************/
zbx_uint32_t	zbx_deserialize_uint31_compact(const unsigned char *ptr, zbx_uint32_t *value)
{
	if (0 == (*ptr & 0x80))
	{
		*value = *ptr;
		return 1;
	}
	else
	{
		zbx_uint32_t	pos = 2, i;

		while (0 != (*ptr & (0x80 >> pos)))
			pos++;

		*value = *ptr & (0xff >> (pos + 1));

		for (i = 1; i < pos; i++)
		{
			*value <<= 6;
			*value |= (*(++ptr)) & 0x3f;
		}

		return pos;
	}
}
