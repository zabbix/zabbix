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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "base64.h"

int	comms_parse_response(char *xml, char *host, size_t host_len, char *key, size_t key_len,
		char *data, size_t data_len, char *lastlogsize, size_t lastlogsize_len,
		char *timestamp, size_t timestamp_len, char *source, size_t source_len,
		char *severity, size_t severity_len)
{
	int	i, ret = SUCCEED;
	char	*data_b64 = NULL;

	assert(NULL != host && 0 != host_len);
	assert(NULL != key && 0 != key_len);
	assert(NULL != data && 0 != data_len);
	assert(NULL != lastlogsize && 0 != lastlogsize_len);
	assert(NULL != timestamp && 0 != timestamp_len);
	assert(NULL != source && 0 != source_len);
	assert(NULL != severity && 0 != severity_len);

	if (SUCCEED == xml_get_data_dyn(xml, "host", &data_b64))
	{
		str_base64_decode(data_b64, host, (int)host_len - 1, &i);
		host[i] = '\0';
		xml_free_data_dyn(&data_b64);
	}
	else
	{
		*host = '\0';
		ret = FAIL;
	}

	if (SUCCEED == xml_get_data_dyn(xml, "key", &data_b64))
	{
		str_base64_decode(data_b64, key, (int)key_len - 1, &i);
		key[i] = '\0';
		xml_free_data_dyn(&data_b64);
	}
	else
	{
		*key = '\0';
		ret = FAIL;
	}

	if (SUCCEED == xml_get_data_dyn(xml, "data", &data_b64))
	{
		str_base64_decode(data_b64, data, (int)data_len - 1, &i);
		data[i] = '\0';
		xml_free_data_dyn(&data_b64);
	}
	else
	{
		*data = '\0';
		ret = FAIL;
	}

	if (SUCCEED == xml_get_data_dyn(xml, "lastlogsize", &data_b64))
	{
		str_base64_decode(data_b64, lastlogsize, (int)lastlogsize_len - 1, &i);
		lastlogsize[i] = '\0';
		xml_free_data_dyn(&data_b64);
	}
	else
		*lastlogsize = '\0';

	if (SUCCEED == xml_get_data_dyn(xml, "timestamp", &data_b64))
	{
		str_base64_decode(data_b64, timestamp, (int)timestamp_len - 1, &i);
		timestamp[i] = '\0';
		xml_free_data_dyn(&data_b64);
	}
	else
		*timestamp = '\0';

	if (SUCCEED == xml_get_data_dyn(xml, "source", &data_b64))
	{
		str_base64_decode(data_b64, source, (int)source_len - 1, &i);
		source[i] = '\0';
		xml_free_data_dyn(&data_b64);
	}
	else
		*source = '\0';

	if (SUCCEED == xml_get_data_dyn(xml, "severity", &data_b64))
	{
		str_base64_decode(data_b64, severity, (int)severity_len - 1, &i);
		severity[i] = '\0';
		xml_free_data_dyn(&data_b64);
	}
	else
		*severity = '\0';

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_htole_uint64                                                 *
 *                                                                            *
 * Purpose: convert unsigned integer 64 bit                                   *
 *          from host byte order                                              *
 *          to little-endian byte order format                                *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: unsigned integer 64 bit in little-endian byte order format   *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
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
 * Function: zbx_letoh_uint64                                                 *
 *                                                                            *
 * Purpose: convert unsigned integer 64 bit                                   *
 *          from little-endian byte order format                              *
 *          to host byte order                                                *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: unsigned integer 64 bit in host byte order                   *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
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
