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

/******************************************************************************
 *                                                                            *
 * Function: comms_create_request                                             *
 *                                                                            *
 * Purpose: dinamical xml request generation                                  *
 *                                                                            *
 * Return value: XML request                                                  *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:  required free allocated string with function 'zbx_free'         *
 *                                                                            *
 ******************************************************************************/
char*	comms_create_request(
	const char		*host,
	const char		*key,
	const char		*data,
	long			*lastlogsize,
	unsigned long	*timestamp,
	const char		*source,
	unsigned short	*severity
	)
{
#define ADD_XML_DATA(tag_name, var) \
	data_b64[0] = '\0'; \
	str_base64_encode(var, data_b64, (int)strlen(var)); \
	request = zbx_strdcatf(request, "<" tag_name ">%s</" tag_name ">",	data_b64)

	char data_b64[ZBX_MAX_B64_LEN];
	char *tmp_str = NULL;
	char *request = NULL;
	
	assert(host);
	assert(key);
	assert(data);

	/* zabbix_log(LOG_LEVEL_DEBUG, "comms_create_request host [%s] key [%s] data [%s]",host,key,data); */

	memset(data_b64,0,sizeof(data_b64));

	request = zbx_dsprintf(NULL,"%s", "<req>");
	
	ADD_XML_DATA("host",	host);
	ADD_XML_DATA("key",		key);
	ADD_XML_DATA("data",	data);

	if(lastlogsize)
	{
		tmp_str = zbx_dsprintf(NULL, "%li", *lastlogsize);
		ADD_XML_DATA("lastlogsize",	tmp_str);
		zbx_free(tmp_str);
	}

	if(timestamp)
	{
		assert(source);
		assert(severity);
		
		tmp_str = zbx_dsprintf(NULL, "%lu", *timestamp);
		ADD_XML_DATA("timestamp",	tmp_str);
		zbx_free(tmp_str);

		ADD_XML_DATA("source",		source);

		tmp_str = zbx_dsprintf(NULL, "%u", *severity);
		ADD_XML_DATA("severity",	tmp_str);
		zbx_free(tmp_str);
	}

	return zbx_strdcat(request, "</req>");
}

int	comms_parse_response(char *xml,char *host,char *key, char *data, char *lastlogsize, char *timestamp,
	       char *source, char *severity, int maxlen)
{
	int ret = SUCCEED;
	int i;

	char host_b64[MAX_STRING_LEN];
	char key_b64[MAX_STRING_LEN];
	char data_b64[MAX_STRING_LEN];
	char lastlogsize_b64[MAX_STRING_LEN];
	char timestamp_b64[MAX_STRING_LEN];
	char source_b64[ZBX_MAX_B64_LEN];
	char severity_b64[MAX_STRING_LEN];

	assert(key);
	assert(host);
	assert(data);
	assert(lastlogsize);
	assert(timestamp);
	assert(source);
	assert(severity);

	memset(host_b64,0,sizeof(host_b64));
	memset(key_b64,0,sizeof(key_b64));
	memset(data_b64,0,sizeof(data_b64));
	memset(lastlogsize_b64,0,sizeof(lastlogsize_b64));
	memset(timestamp_b64,0,sizeof(timestamp_b64));
	memset(source_b64,0,sizeof(source_b64));
	memset(severity_b64,0,sizeof(severity_b64));

	xml_get_data(xml, "host", host_b64, sizeof(host_b64)-1);
	xml_get_data(xml, "key", key_b64, sizeof(key_b64)-1);
	xml_get_data(xml, "data", data_b64, sizeof(data_b64)-1);
	xml_get_data(xml, "lastlogsize", lastlogsize_b64, sizeof(lastlogsize_b64)-1);
	xml_get_data(xml, "timestamp", timestamp_b64, sizeof(timestamp_b64)-1);
	xml_get_data(xml, "source", source_b64, sizeof(source_b64)-1);
	xml_get_data(xml, "severity", severity_b64, sizeof(severity_b64)-1);

	memset(key,0,maxlen);
	memset(host,0,maxlen);
	memset(data,0,maxlen);
	memset(lastlogsize,0,maxlen);
	memset(timestamp,0,maxlen);
	memset(source,0,maxlen);
	memset(severity,0,maxlen);

	str_base64_decode(host_b64, host, &i);
	str_base64_decode(key_b64, key, &i);
	str_base64_decode(data_b64, data, &i);
	str_base64_decode(lastlogsize_b64, lastlogsize, &i);
	str_base64_decode(timestamp_b64, timestamp, &i);
	str_base64_decode(source_b64, source, &i);
	str_base64_decode(severity_b64, severity, &i);

	return ret;
}

void    *zbx_malloc2(char *filename, int line, void *old, size_t size)
{
	register int max_attempts;
	void *ptr = NULL;

/*	Old pointer must be NULL */
	if(old != NULL)
	{
		zabbix_log(LOG_LEVEL_CRIT,"[file:%s,line:%d] zbx_malloc: allocating already allocated memory. Please report this to ZABBIX developers.",
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

	if(ptr) return ptr;

	zabbix_log(LOG_LEVEL_CRIT,"zbx_malloc: out of memory. requested '%lu' bytes.", size);
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
void    *zbx_realloc(void *src, size_t size)
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

	if(ptr) return ptr;

	zabbix_log(LOG_LEVEL_CRIT,"zbx_realloc: out of memory. requested '%lu' bytes.", size);
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
