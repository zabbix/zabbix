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
#include "logfiles.h"
#include "log.h"

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
	if (0 != zbx_stat(filename, &buf))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Cannot open [%s] [%s]", filename, strerror(errno));
		return ret;
	}

	if (buf.st_size < *lastlogsize)
		*lastlogsize = 0;

	if (-1 == (f = zbx_open(filename, O_RDONLY)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Cannot open [%s] [%s]", filename, strerror(errno));
		return ret;
	}

	if ((off_t)-1 != lseek(f, (off_t)*lastlogsize, SEEK_SET))
	{
		if (-1 != (nbytes = zbx_read(f, buffer, sizeof(buffer), encoding)))
		{
			if (0 != nbytes)
			{
				*lastlogsize += nbytes;
				*value = convert_to_utf8(buffer, nbytes, encoding);
				zbx_rtrim(*value, "\r\n ");
			}
			ret = SUCCEED;
		}
		else
			zabbix_log(LOG_LEVEL_WARNING, "Cannot read from [%s] [%s]", filename, strerror(errno));
	}
	else
		zabbix_log(LOG_LEVEL_WARNING, "Cannot set position to [%li] for [%s] [%s]", *lastlogsize, filename, strerror(errno));

	close(f);

	return ret;
}
