/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

#if defined(_WINDOWS)
int	__zbx_open(const char *pathname, int flags)
{
	int	ret;
	wchar_t	*wpathname;

	wpathname = zbx_utf8_to_unicode(pathname);
	ret = _wopen(wpathname, flags);
	zbx_free(wpathname);

	return ret;
}
#endif

void	find_cr_lf_szbyte(const char *encoding, const char **cr, const char **lf, size_t *szbyte)
{
	/* default is single-byte character set */
	*cr = "\r";
	*lf = "\n";
	*szbyte = 1;

	if ('\0' != *encoding)
	{
		if (0 == strcasecmp(encoding, "UNICODE") || 0 == strcasecmp(encoding, "UNICODELITTLE") ||
				0 == strcasecmp(encoding, "UTF-16") || 0 == strcasecmp(encoding, "UTF-16LE") ||
				0 == strcasecmp(encoding, "UTF16") || 0 == strcasecmp(encoding, "UTF16LE"))
		{
			*cr = "\r\0";
			*lf = "\n\0";
			*szbyte = 2;
		}
		else if (0 == strcasecmp(encoding, "UNICODEBIG") || 0 == strcasecmp(encoding, "UNICODEFFFE") ||
				0 == strcasecmp(encoding, "UTF-16BE") || 0 == strcasecmp(encoding, "UTF16BE"))
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
 * Function: zbx_read                                                         *
 *                                                                            *
 * Purpose: Read one text line from a file descriptor into buffer             *
 *                                                                            *
 * Parameters: fd       - [IN] file descriptor to read from                   *
 *             buf      - [IN] buffer to read into                            *
 *             count    - [IN] buffer size in bytes                           *
 *             encoding - [IN] pointer to a text string describing encoding.  *
 *                        The following encodings are recognized:             *
 *                          "UNICODE"                                         *
 *                          "UNICODEBIG"                                      *
 *                          "UNICODEFFFE"                                     *
 *                          "UNICODELITTLE"                                   *
 *                          "UTF-16"   "UTF16"                                *
 *                          "UTF-16BE" "UTF16BE"                              *
 *                          "UTF-16LE" "UTF16LE"                              *
 *                          "UTF-32"   "UTF32"                                *
 *                          "UTF-32BE" "UTF32BE"                              *
 *                          "UTF-32LE" "UTF32LE".                             *
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
	const char	*cr, *lf;
	int		nbytes;
	zbx_offset_t	offset;

	if ((zbx_offset_t)-1 == (offset = zbx_lseek(fd, 0, SEEK_CUR)))
		return -1;

	if (0 >= (nbytes = (int)read(fd, buf, count)))
		return nbytes;

	find_cr_lf_szbyte(encoding, &cr, &lf, &szbyte);

	for (i = 0; i <= (size_t)nbytes - szbyte; i += szbyte)
	{
		if (0 == memcmp(&buf[i], lf, szbyte))	/* LF (Unix) */
		{
			i += szbyte;
			break;
		}

		if (0 == memcmp(&buf[i], cr, szbyte))	/* CR (Mac) */
		{
			/* CR+LF (Windows) ? */
			if (i < (size_t)nbytes - szbyte && 0 == memcmp(&buf[i + szbyte], lf, szbyte))
				i += szbyte;

			i += szbyte;
			break;
		}
	}

	if ((zbx_offset_t)-1 == zbx_lseek(fd, offset + (zbx_offset_t)i, SEEK_SET))
		return -1;

	return (int)i;
}

int	zbx_is_regular_file(const char *path)
{
	zbx_stat_t	st;

	if (0 == zbx_stat(path, &st) && 0 != S_ISREG(st.st_mode))
		return SUCCEED;

	return FAIL;
}
