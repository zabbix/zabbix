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

#include "zbxcrypto.h"

/******************************************************************************
 *                                                                            *
 * Purpose: Converts unsigned integer 64 bit from host byte order to          *
 *          little-endian byte order format.                                  *
 *                                                                            *
 * Return value: unsigned integer 64 bit in little-endian byte order format   *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	zbx_htole_uint64(zbx_uint64_t data)
{
	unsigned char	buf[8];

	buf[0] = (unsigned char)data;	data >>= 8;
	buf[1] = (unsigned char)data;	data >>= 8;
	buf[2] = (unsigned char)data;	data >>= 8;
	buf[3] = (unsigned char)data;	data >>= 8;
	buf[4] = (unsigned char)data;	data >>= 8;
	buf[5] = (unsigned char)data;	data >>= 8;
	buf[6] = (unsigned char)data;	data >>= 8;
	buf[7] = (unsigned char)data;

	memcpy(&data, buf, sizeof(buf));

	return data;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Converts unsigned integer 64 bit from little-endian byte order    *
 *          format to host byte order.                                        *
 *                                                                            *
 * Return value: unsigned integer 64 bit in host byte order                   *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	zbx_letoh_uint64(zbx_uint64_t data)
{
	unsigned char	buf[8];

	memcpy(buf, &data, sizeof(buf));

	data  = (zbx_uint64_t)buf[7];	data <<= 8;
	data |= (zbx_uint64_t)buf[6];	data <<= 8;
	data |= (zbx_uint64_t)buf[5];	data <<= 8;
	data |= (zbx_uint64_t)buf[4];	data <<= 8;
	data |= (zbx_uint64_t)buf[3];	data <<= 8;
	data |= (zbx_uint64_t)buf[2];	data <<= 8;
	data |= (zbx_uint64_t)buf[1];	data <<= 8;
	data |= (zbx_uint64_t)buf[0];

	return data;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Converts unsigned integer 32 bit from host byte order to          *
 *          little-endian byte order format.                                  *
 *                                                                            *
 * Return value: unsigned integer 32 bit in little-endian byte order format   *
 *                                                                            *
 ******************************************************************************/
zbx_uint32_t	zbx_htole_uint32(zbx_uint32_t data)
{
	unsigned char	buf[4];

	buf[0] = (unsigned char)data;	data >>= 8;
	buf[1] = (unsigned char)data;	data >>= 8;
	buf[2] = (unsigned char)data;	data >>= 8;
	buf[3] = (unsigned char)data;	data >>= 8;

	memcpy(&data, buf, sizeof(buf));

	return data;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Converts unsigned integer 32 bit from little-endian byte order    *
 *          format to host byte order.                                        *
 *                                                                            *
 * Return value: unsigned integer 32 bit in host byte order                   *
 *                                                                            *
 ******************************************************************************/
zbx_uint32_t	zbx_letoh_uint32(zbx_uint32_t data)
{
	unsigned char	buf[4];

	memcpy(buf, &data, sizeof(buf));

	data = (zbx_uint32_t)buf[3];	data <<= 8;
	data |= (zbx_uint32_t)buf[2];	data <<= 8;
	data |= (zbx_uint32_t)buf[1];	data <<= 8;
	data |= (zbx_uint32_t)buf[0];

	return data;
}
