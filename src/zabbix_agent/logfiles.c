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
#include "logfiles.h"

static size_t	get_line(char *buffer, size_t size, long *lastlogsize, const char *encoding)
{
	size_t		i, szbyte;
	const char	*cr, *lf;

	if (0 == strcmp(encoding, "UNICODE") || 0 == strcmp(encoding, "UNICODELITTLE") ||
			0 == strcmp(encoding, "UTF-16") || 0 == strcmp(encoding, "UTF-16LE") ||
			0 == strcmp(encoding, "UTF16") || 0 == strcmp(encoding, "UTF16LE"))
	{
		cr = "\r\0";
		lf = "\n\0";
		szbyte = 2;
	}
	else if (0 == strcmp(encoding, "UNICODEBIG") ||
			0 == strcmp(encoding, "UTF-16BE") || 0 == strcmp(encoding, "UTF16BE"))
	{
		cr = "\0\r";
		lf = "\0\n";
		szbyte = 2;
	}
	else if (0 == strcmp(encoding, "UTF-32") || 0 == strcmp(encoding, "UTF-32LE") ||
			0 == strcmp(encoding, "UTF32") || 0 == strcmp(encoding, "UTF32LE"))
	{
		cr = "\r\0\0\0";
		lf = "\n\0\0\0";
		szbyte = 4;
	}
	else if (0 == strcmp(encoding, "UTF-32BE") || 0 == strcmp(encoding, "UTF32BE"))
	{
		cr = "\0\0\0\r";
		lf = "\0\0\0\n";
		szbyte = 4;
	}
	else
	{
		cr = "\r";
		lf = "\n";
		szbyte = 1;
	}

	for (i = 0; i < size; i += szbyte)
	{
		*lastlogsize += szbyte;

		if (0 == memcmp(&buffer[i], lf, szbyte))	/* LF (Unix) */
			break;

		if (0 == memcmp(&buffer[i], cr, szbyte))	/* CR (Mac) */
		{
			if (i + szbyte < size && 0 == memcmp(&buffer[i + szbyte], lf, szbyte))	/* CR+LF (Windows) */
				*lastlogsize += szbyte;
			break;
		}
	}

	return i;
}

/******************************************************************************
 *                                                                            *
 * Function: process_log                                                      *
 *                                                                            *
 * Purpose: Get message from logfile                                          *
 *                                                                            *
 * Parameters: filename - logfile name                                        *
 *             lastlogsize - offset for message                               *
 *             value - pointer for logged message                             *
 *                                                                            *
 * Return value: returns SUCCEED on succesful reading,                        *
 *               FAIL on other cases                                          *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *    This function allocates memory for 'value', because use zbx_free.       *
 *    Return SUCCEED and NULL value if end of file received.                  *
 *                                                                            *
 *                                                                            *
 ******************************************************************************/
int	process_log(char *filename, long *lastlogsize, char **value, const char *encoding)
{
	int		f;
	struct stat	buf;
	int		nbytes, ret = FAIL;
	char		buffer[MAX_BUF_LEN];
	assert(filename);
	assert(lastlogsize);
	assert(value);
	assert(encoding);

	zabbix_log(LOG_LEVEL_DEBUG, "In process_log() filename:'%s' lastlogsize:%li", filename, *lastlogsize);

	/* Handling of file shrinking */
	if (0 != stat(filename, &buf))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Cannot open [%s] [%s]", filename, strerror(errno));
		return ret;
	}

	if (buf.st_size < *lastlogsize)
		*lastlogsize = 0;

	if (-1 == (f = open(filename, O_RDONLY)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Cannot open [%s] [%s]", filename, strerror(errno));
		return ret;
	}

	if ((off_t)-1 != lseek(f, (off_t)*lastlogsize, SEEK_SET))
	{
		if (-1 != (nbytes = read(f, buffer, sizeof(buffer))))
		{
			if (0 != nbytes)
			{
				nbytes = get_line(buffer, nbytes, lastlogsize, encoding);

				*value = convert_to_utf8(buffer, nbytes, encoding);
			}
			ret = SUCCEED;
		}
		else
			zabbix_log(LOG_LEVEL_WARNING, "Cannot open [%s] [%s]", filename, strerror(errno));
	}
	else
		zabbix_log(LOG_LEVEL_WARNING, "Cannot set position to [%li] for [%s] [%s]", *lastlogsize, filename, strerror(errno));

	close(f);

	return ret;
}
