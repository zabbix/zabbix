/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"
#include "log.h"
#include "base64.h"

#if defined (_WINDOWS)
char ZABBIX_SERVICE_NAME[ZBX_SERVICE_NAME_LEN] = {APPLICATION_NAME};
char ZABBIX_EVENT_SOURCE[ZBX_SERVICE_NAME_LEN] = {APPLICATION_NAME};
#endif /* _WINDOWS */

int	comms_parse_response(char *xml, char *host, int host_len, char *key, int key_len, char *data, int data_len,
		char *lastlogsize, int lastlogsize_len, char *timestamp, int timestamp_len,
		char *source, int source_len, char *severity, int severity_len)
{
	int	i, ret = SUCCEED;
	char	*data_b64 = NULL;

	assert(key);
	assert(host);
	assert(data);
	assert(lastlogsize);
	assert(timestamp);
	assert(source);
	assert(severity);

	if (SUCCEED == xml_get_data_dyn(xml, "host", &data_b64))
	{
		str_base64_decode(data_b64, host, host_len - 1, &i);
		host[i] = '\0';
		xml_free_data_dyn(&data_b64);
	}

	if (SUCCEED == xml_get_data_dyn(xml, "key", &data_b64))
	{
		str_base64_decode(data_b64, key, key_len - 1, &i);
		key[i] = '\0';
		xml_free_data_dyn(&data_b64);
	}

	if (SUCCEED == xml_get_data_dyn(xml, "data", &data_b64))
	{
		str_base64_decode(data_b64, data, data_len - 1, &i);
		data[i] = '\0';
		xml_free_data_dyn(&data_b64);
	}

	if (SUCCEED == xml_get_data_dyn(xml, "lastlogsize", &data_b64))
	{
		str_base64_decode(data_b64, lastlogsize, lastlogsize_len - 1, &i);
		lastlogsize[i] = '\0';
		xml_free_data_dyn(&data_b64);
	}

	if (SUCCEED == xml_get_data_dyn(xml, "timestamp", &data_b64))
	{
		str_base64_decode(data_b64, timestamp, timestamp_len - 1, &i);
		timestamp[i] = '\0';
		xml_free_data_dyn(&data_b64);
	}

	if (SUCCEED == xml_get_data_dyn(xml, "source", &data_b64))
	{
		str_base64_decode(data_b64, source, source_len - 1, &i);
		source[i] = '\0';
		xml_free_data_dyn(&data_b64);
	}

	if (SUCCEED == xml_get_data_dyn(xml, "severity", &data_b64))
	{
		str_base64_decode(data_b64, severity, severity_len - 1, &i);
		severity[i] = '\0';
		xml_free_data_dyn(&data_b64);
	}

	return ret;
}

void    *zbx_malloc2(char *filename, int line, void *old, size_t size)
{
	register int max_attempts;
	void *ptr = NULL;

/*	Old pointer must be NULL */
	if(old != NULL)
	{
		zabbix_log(LOG_LEVEL_CRIT,"[file:%s,line:%d] zbx_malloc: allocating already allocated memory. Please report this to Zabbix developers.",
			filename,
			line);
		/* Exit if defined DEBUG. Ignore otherwise. */
		zbx_dbg_assert(0);
	}

/*	zabbix_log(LOG_LEVEL_DEBUG,"In zbx_malloc(size:%d)", size); */

	for(
		max_attempts = 10, size = MAX(size, 1);
		max_attempts > 0 && !ptr;
		ptr = malloc(size),
		max_attempts--
	);

	if (ptr)
	{
/*		fprintf(stderr, "%-6li => [file:%s,line:%d] zbx_malloc: %p %lu bytes\n", (long int)getpid(), filename, line, ptr, size);*/
		return ptr;
	}

	zabbix_log(LOG_LEVEL_CRIT,"[file:%s,line:%d] zbx_malloc: out of memory. requested '%lu' bytes.", filename, line, size);
	exit(FAIL);

	/* Program will never reach this point. */
	return ptr;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_realloc                                                      *
 *                                                                            *
 * Purpose: changes the size of the memory block pointed to by src            *
 *          to size bytes.                                                    *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: returns a pointer to the newly allocated memory              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void    *zbx_realloc2(char *filename, int line, void *src, size_t size)
{
	register int max_attempts;
	void *ptr = NULL;

/*	zabbix_log(LOG_LEVEL_DEBUG,"In zbx_realloc(size:%d)", size); */

	for(
		max_attempts = 10, size = MAX(size, 1);
		max_attempts > 0 && !ptr;
		ptr = realloc(src, size),
		max_attempts--
	);

	if (ptr)
	{
/*		fprintf(stderr, "%-6li => [file:%s,line:%d] zbx_realloc: %p %lu bytes\n", (long int)getpid(), filename, line, ptr, size);*/
		return ptr;
	}

	zabbix_log(LOG_LEVEL_CRIT, "[file:%s,line:%d] zbx_realloc: out of memory. requested '%lu' bytes.", filename, line, size);
	exit(FAIL);

	/* Program will never reach this point. */
	return ptr;
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
zbx_uint64_t	zbx_htole_uint64(
		zbx_uint64_t	data
	)
{
	unsigned char buf[8];

	buf[0] = (unsigned char) (data);	data >>= 8;
	buf[1] = (unsigned char) (data);	data >>= 8;
	buf[2] = (unsigned char) (data);	data >>= 8;
	buf[3] = (unsigned char) (data);	data >>= 8;
	buf[4] = (unsigned char) (data);	data >>= 8;
	buf[5] = (unsigned char) (data);	data >>= 8;
	buf[6] = (unsigned char) (data);	data >>= 8;
	buf[7] = (unsigned char) (data);

	memcpy(&data, buf, sizeof(buf));

	return  data;
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
zbx_uint64_t	zbx_letoh_uint64(
		zbx_uint64_t	data
	)
{
	unsigned char buf[8];

	memset(buf, 0, sizeof(buf));
	memcpy(buf, &data, sizeof(buf));

	data = 0;

	data  = (zbx_uint64_t) buf[7];		data <<= 8;
	data |= (zbx_uint64_t) buf[6];		data <<= 8;
	data |= (zbx_uint64_t) buf[5];		data <<= 8;
	data |= (zbx_uint64_t) buf[4];		data <<= 8;
	data |= (zbx_uint64_t) buf[3];		data <<= 8;
	data |= (zbx_uint64_t) buf[2];		data <<= 8;
	data |= (zbx_uint64_t) buf[1];		data <<= 8;
	data |= (zbx_uint64_t) buf[0];

	return	data;
}
