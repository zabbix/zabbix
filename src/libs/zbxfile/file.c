/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#include "log.h"

#include "zbxfile.h"

void	zbx_find_cr_lf_szbyte(const char *encoding, const char **cr, const char **lf, size_t *szbyte)
{
	/* default is single-byte character set */
	*cr = "\r";
	*lf = "\n";
	*szbyte = 1;

	if ('\0' != *encoding)
	{
		if (0 == strcasecmp(encoding, "UNICODE") || 0 == strcasecmp(encoding, "UNICODELITTLE") ||
				0 == strcasecmp(encoding, "UTF-16") || 0 == strcasecmp(encoding, "UTF-16LE") ||
				0 == strcasecmp(encoding, "UTF16") || 0 == strcasecmp(encoding, "UTF16LE") ||
				0 == strcasecmp(encoding, "UCS-2") || 0 == strcasecmp(encoding, "UCS-2LE"))
		{
			*cr = "\r\0";
			*lf = "\n\0";
			*szbyte = 2;
		}
		else if (0 == strcasecmp(encoding, "UNICODEBIG") || 0 == strcasecmp(encoding, "UNICODEFFFE") ||
				0 == strcasecmp(encoding, "UTF-16BE") || 0 == strcasecmp(encoding, "UTF16BE") ||
				0 == strcasecmp(encoding, "UCS-2BE"))
		{
			*cr = "\0\r";
			*lf = "\0\n";
			*szbyte = 2;
		}
		else if (0 == strcasecmp(encoding, "UTF-32") || 0 == strcasecmp(encoding, "UTF-32LE") ||
				0 == strcasecmp(encoding, "UTF32") || 0 == strcasecmp(encoding, "UTF32LE"))
		{
			*cr = "\r\0\0\0";
			*lf = "\n\0\0\0";
			*szbyte = 4;
		}
		else if (0 == strcasecmp(encoding, "UTF-32BE") || 0 == strcasecmp(encoding, "UTF32BE"))
		{
			*cr = "\0\0\0\r";
			*lf = "\0\0\0\n";
			*szbyte = 4;
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: Read one text line from a file descriptor into buffer             *
 *                                                                            *
 * Parameters: fd       - [IN] file descriptor to read from                   *
 *             buf      - [OUT] buffer to read into                           *
 *             count    - [IN] buffer size in bytes                           *
 *             encoding - [IN] pointer to a text string describing encoding.  *
 *                        See function zbx_find_cr_lf_szbyte() for supported  *
 *                        encodings.                                          *
 *                        "" (empty string) means a single-byte character set.*
 *                                                                            *
 * Return value: On success, the number of bytes read is returned (0 (zero)   *
 *               indicates end of file).                                      *
 *               On error, -1 is returned and errno is set appropriately.     *
 *                                                                            *
 * Comments: Reading stops after a newline. If the newline is read, it is     *
 *           stored into the buffer.                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_read(int fd, char *buf, size_t count, const char *encoding)
{
	size_t		i, szbyte;
	ssize_t		nbytes;
	const char	*cr, *lf;
	zbx_offset_t	offset;

	if ((zbx_offset_t)-1 == (offset = zbx_lseek(fd, 0, SEEK_CUR)))
		return -1;

	zabbix_log(LOG_LEVEL_INFORMATION, "BADGER zbx_read 2");

	if (0 >= (nbytes = read(fd, buf, count)))
		return (int)nbytes;


	zabbix_log(LOG_LEVEL_INFORMATION, "BADGER zbx_read 3");

	zbx_find_cr_lf_szbyte(encoding, &cr, &lf, &szbyte);

	zabbix_log(LOG_LEVEL_INFORMATION, "BADGER cr: ->%s<-", cr);
	zabbix_log(LOG_LEVEL_INFORMATION, "BADGER lf ->%s<-", lf);
	zabbix_log(LOG_LEVEL_INFORMATION, "BADGER szbyte ->%lu<-", szbyte);
	int	lf_found = 0;
	for (i = 0; i <= (size_t)nbytes - szbyte; i += szbyte)
	{

	zabbix_log(LOG_LEVEL_INFORMATION, "BADGER i ->%d<-", i);

	zabbix_log(LOG_LEVEL_INFORMATION, "BADGER buf ->%d<- and ->%d<-", buf[i], buf[i+1]);
	zabbix_log(LOG_LEVEL_INFORMATION, "BADGER lf ->%d<- and ->%d<-", lf[0], lf[1]);
		if (0 == memcmp(&buf[i], lf, szbyte))	/* LF (Unix) */
		{
			i += szbyte;
			lf_found = 1;
			break;
		}

		if (0 == memcmp(&buf[i], cr, szbyte))	/* CR (Mac) */
		{
			/* CR+LF (Windows) ? */
			if (i < (size_t)nbytes - szbyte && 0 == memcmp(&buf[i + szbyte], lf, szbyte))
				i += szbyte;

			i += szbyte;
			lf_found = 1;
			break;
		}
	}

	zabbix_log(LOG_LEVEL_INFORMATION, "BADGER AFTER i ->%lu<-", i);

	zabbix_log(LOG_LEVEL_INFORMATION, "BADGER AFTER nbytes ->%lu<-", nbytes);
	zabbix_log(LOG_LEVEL_INFORMATION, "BADGER AFTER nbytes-szbyte ->%lu<-", nbytes-szbyte);
	zabbix_log(LOG_LEVEL_INFORMATION, "BADGER AFTER szbyte ->%lu<-", szbyte);

	if ((0 == lf_found) &&
			(0 == strcasecmp(encoding, "UNICODE") || 0 == strcasecmp(encoding, "UNICODELITTLE") ||
			0 == strcasecmp(encoding, "UTF-16") || 0 == strcasecmp(encoding, "UTF-16LE") ||
			0 == strcasecmp(encoding, "UTF16") || 0 == strcasecmp(encoding, "UTF16LE") ||
			0 == strcasecmp(encoding, "UCS-2") || 0 == strcasecmp(encoding, "UCS-2LE") ||
				0 == strcasecmp(encoding, "UNICODEBIG") || 0 == strcasecmp(encoding, "UNICODEFFFE") ||
				0 == strcasecmp(encoding, "UTF-16BE") || 0 == strcasecmp(encoding, "UTF16BE") ||
				0 == strcasecmp(encoding, "UCS-2BE") ||
				0 == strcasecmp(encoding, "UTF-32") || 0 == strcasecmp(encoding, "UTF-32LE") ||
				0 == strcasecmp(encoding, "UTF32") || 0 == strcasecmp(encoding, "UTF32LE") ||
				0 == strcasecmp(encoding, "UTF-32BE") || 0 == strcasecmp(encoding, "UTF32BE")))
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "BADGER no line feed");
		return -2;
	}

	zabbix_log(LOG_LEVEL_INFORMATION, "BADGER zbx_read 33");

	if ((zbx_offset_t)-1 == zbx_lseek(fd, offset + (zbx_offset_t)i, SEEK_SET))
		return -1;

	zabbix_log(LOG_LEVEL_INFORMATION, "BADGER zbx_read 4; i: %d", i);

	return (int)i;
}

int	zbx_is_regular_file(const char *path)
{
	zbx_stat_t	st;

	if (0 == zbx_stat(path, &st) && 0 != S_ISREG(st.st_mode))
		return SUCCEED;

	return FAIL;
}

#if !(defined(_WINDOWS) || defined(__MINGW32__))
int	zbx_get_file_time(const char *path, int sym, zbx_file_time_t *time)
{
	zbx_stat_t	buf;

	if (0 != sym)
	{
		if (0 != lstat(path, &buf))
			return FAIL;
	}
	else
	{
		if (0 != zbx_stat(path, &buf))
			return FAIL;
	}

	time->access_time = (zbx_fs_time_t)buf.st_atime;
	time->modification_time = (zbx_fs_time_t)buf.st_mtime;
	time->change_time = (zbx_fs_time_t)buf.st_ctime;

	return SUCCEED;
}

char	*zbx_fgets(char *buffer, int size, FILE *fp)
{
	char	*s;

	do
	{
		errno = 0;
		s = fgets(buffer, size, fp);
	}
	while (EINTR == errno && NULL == s);

	return s;
}

/******************************************************************************
 *                                                                            *
 * Purpose: call write in a loop, iterating until all the data is written.    *
 *                                                                            *
 * Parameters: fd      - [IN] descriptor                                      *
 *             buf     - [IN] buffer to write                                 *
 *             n       - [IN] bytes count to write                            *
 *                                                                            *
 * Return value: SUCCEED - n bytes successfully written                       *
 *               FAIL    - less than n bytes are written                      *
 *                                                                            *
 ******************************************************************************/
int	zbx_write_all(int fd, const char *buf, size_t n)
{
	while (0 < n)
	{
		ssize_t	ret;

		if (-1 != (ret = write(fd, buf, n)))
		{
			buf += ret;
			n -= (size_t)ret;
		}
		else if (EINTR != errno)
			return FAIL;
	}

	return SUCCEED;
}

#endif	/* not _WINDOWS */
