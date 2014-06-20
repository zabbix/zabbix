/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#if defined(_WINDOWS) && defined(_UNICODE)
int	__zbx_stat(const char *path, struct stat *buf)
{
	int	ret;
	wchar_t	*wpath;

	wpath = zbx_utf8_to_unicode(path);
	ret = _wstat64(wpath, buf);
	zbx_free(wpath);

	return ret;
}

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

/*
 * Reads in at most one less than size characters from a file descriptor and stores them into the buffer pointed to by s.
 * Reading stops after a newline. If a newline is read, it is stored into the buffer.
 *
 * On success, the number of bytes read is returned (zero indicates end of file).
 * On error, -1 is returned, and errno is set appropriately.
 */
int	zbx_read(int fd, char *buf, size_t count, const char *encoding)
{
	size_t		i, szbyte;
	const char	*cr, *lf;
	int		nbytes;
#ifdef _WINDOWS
	__int64		offset;
#else
	off_t		offset;
#endif

#ifdef _WINDOWS
	offset = _lseeki64(fd, 0, SEEK_CUR);
#else
	offset = lseek(fd, 0, SEEK_CUR);
#endif

	if (0 >= (nbytes = (int)read(fd, buf, count)))
		return nbytes;

	if (0 == strcasecmp(encoding, "UNICODE") || 0 == strcasecmp(encoding, "UNICODELITTLE") ||
			0 == strcasecmp(encoding, "UTF-16") || 0 == strcasecmp(encoding, "UTF-16LE") ||
			0 == strcasecmp(encoding, "UTF16") || 0 == strcasecmp(encoding, "UTF16LE"))
	{
		cr = "\r\0";
		lf = "\n\0";
		szbyte = 2;
	}
	else if (0 == strcasecmp(encoding, "UNICODEBIG") || 0 == strcasecmp(encoding, "UNICODEFFFE") ||
			0 == strcasecmp(encoding, "UTF-16BE") || 0 == strcasecmp(encoding, "UTF16BE"))
	{
		cr = "\0\r";
		lf = "\0\n";
		szbyte = 2;
	}
	else if (0 == strcasecmp(encoding, "UTF-32") || 0 == strcasecmp(encoding, "UTF-32LE") ||
			0 == strcasecmp(encoding, "UTF32") || 0 == strcasecmp(encoding, "UTF32LE"))
	{
		cr = "\r\0\0\0";
		lf = "\n\0\0\0";
		szbyte = 4;
	}
	else if (0 == strcasecmp(encoding, "UTF-32BE") || 0 == strcasecmp(encoding, "UTF32BE"))
	{
		cr = "\0\0\0\r";
		lf = "\0\0\0\n";
		szbyte = 4;
	}
	else	/* Single or Multi Byte Character Sets */
	{
		cr = "\r";
		lf = "\n";
		szbyte = 1;
	}

	for (i = 0; i + szbyte <= (size_t)nbytes; i += szbyte)
	{
		if (0 == memcmp(&buf[i], lf, szbyte))	/* LF (Unix) */
		{
			i += szbyte;
			break;
		}

		if (0 == memcmp(&buf[i], cr, szbyte))	/* CR (Mac) */
		{
			if (i + szbyte < (size_t)nbytes && 0 == memcmp(&buf[i + szbyte], lf, szbyte))	/* CR+LF (Windows) */
				i += szbyte;
			i += szbyte;
			break;
		}
	}

#ifdef _WINDOWS
	_lseeki64(fd, offset + i, SEEK_SET);
#else
	lseek(fd, offset + i, SEEK_SET);
#endif

	return (int)i;
}

int	zbx_is_regular_file(const char *path)
{
	struct stat	st;

	if (0 == zbx_stat(path, &st) && 0 != S_ISREG(st.st_mode))
		return SUCCEED;

	return FAIL;
}
