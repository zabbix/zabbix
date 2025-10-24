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
 *               On error, -1 (ZBX_READ_ERR) is returned and errno is set     *
 *               appropriately.                                               *
 *               If the wrong decoding is detected, -2                        *
 *               (ZBX_READ_WRONG_ENCODING) is returned.                       *
 *                                                                            *
 * Comments: Reading stops after a newline. If the newline is read, it is     *
 *           stored into the buffer.                                          *
 *                                                                            *
 * Note: This function is left for testing purposes.                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_read_text_line_from_file(int fd, char *buf, size_t count, const char *encoding)
{
	size_t		i, szbyte;
	ssize_t		nbytes;
	const char	*cr, *lf;
	zbx_offset_t	offset;

	if ((zbx_offset_t)-1 == (offset = zbx_lseek(fd, 0, SEEK_CUR)))
		return ZBX_READ_ERR;

	if (0 >= (nbytes = read(fd, buf, count)))
		return (int)nbytes;

	zbx_find_cr_lf_szbyte(encoding, &cr, &lf, &szbyte);

	/* nbytes can be smaller than szbyte. If the target file was encoded in UTF-8 and contained a single */
	/* character, but the target encoding was mistakenly set to UTF-32. Then nbytes will be 1 and szbyte */
	/* will be 4. Similarly, if bytes read produces a remainder that does not fit szbyte - we can safely */
	/* assume the file contains the encoding different from the one provided to us.*/
	if ((size_t)nbytes < szbyte || ((size_t)nbytes % szbyte != 0))
		return ZBX_READ_WRONG_ENCODING;

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
		return ZBX_READ_ERR;

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

/******************************************************************************
 *                                                                            *
 * Purpose: find next newline in buffer using newline encoding                *
 *                                                                            *
 * Parameters: p       - [IN] pointer to buffer to look for newline (nonnull) *
 *             p_next  - [OUT] location of start of next newline              *
 *             p_end   - [IN] pointer to end of buffer p                      *
 *             cr      - [IN] carriage return string                          *
 *             lf      - [IN] line feed string                                *
 *             szbyte  - [IN] size of newline strings                         *
 *                                                                            *
 * Comment: This function replaces '\0' symbols with '?'.                     *
 *                                                                            *
 * Return value: pointer to end of line (before newline string) or            *
 *               NULL if newline is not found.                                *
 *                                                                            *
 ******************************************************************************/
char	*zbx_find_buf_newline(char *p, char **p_next, const char *p_end, const char *cr, const char *lf, size_t szbyte)
{
	if (1 == szbyte)	/* single-byte character set */
	{
		for (; p < p_end; p++)
		{
			/* detect NULL byte and replace it with '?' character */
			if (0x0 == *p)
			{
				*p = '?';
				continue;
			}

			if (0xd < *p || 0xa > *p)
				continue;

			if (0xa == *p)  /* LF (Unix) */
			{
				*p_next = p + 1;
				return p;
			}

			if (0xd == *p)	/* CR (Mac) */
			{
				if (p < p_end - 1 && 0xa == *(p + 1))   /* CR+LF (Windows) */
				{
					*p_next = p + 2;
					return p;
				}

				*p_next = p + 1;
				return p;
			}
		}
		return (char *)NULL;
	}
	else
	{
		while (p <= p_end - szbyte)
		{
			/* detect NULL byte in UTF-16 encoding and replace it with '?' character */
			if (2 == szbyte && 0x0 == *p && 0x0 == *(p + 1))
			{
				if (0x0 == *cr)			/* Big-endian */
					p[1] = '?';
				else				/* Little-endian */
					*p = '?';
			}

			if (0 == memcmp(p, lf, szbyte))		/* LF (Unix) */
			{
				*p_next = p + szbyte;
				return p;
			}

			if (0 == memcmp(p, cr, szbyte))		/* CR (Mac) */
			{
				if (p <= p_end - szbyte - szbyte && 0 == memcmp(p + szbyte, lf, szbyte))
				{
					/* CR+LF (Windows) */
					*p_next = p + szbyte + szbyte;
					return p;
				}

				*p_next = p + szbyte;
				return p;
			}

			p += szbyte;
		}
		return (char *)NULL;
	}
}

/* Helper context for zbx_buf_readln */
struct buf_read_save {
	zbx_offset_t	offset;		/* offset in file where buffer is read from */

	char		*p_start;	/* start position of current line */
	char		*p_end;		/* end position of data */
	char		*p_next;	/* position of next line after newline */

	const char	*cr, *lf;	/* pointers to newline characters */
	size_t		szbyte;		/* size of newline characters */
};

/*******************************************************************************
 *                                                                             *
 * Purpose: reads file line-by-line in a buffered manner                       *
 *                                                                             *
 * Parameters:                                                                 *
 *         fd       - [IN] file descriptor to read from                        *
 *         buf      - [IN] buffer to read into                                 *
 *         bufsz    - [IN] buffer size in bytes. Must divisible by 4.          *
 *         encoding - [IN] pointer to a text string describing encoding.       *
 *                        See function zbx_find_cr_lf_szbyte() for supported   *
 *                        encodings.                                           *
 *                        "" (empty string) means a single-byte character set. *
 *         value    - [OUT] resulting pointer to start of line                 *
 *         saveptr  - [IN/OUT] pointer to context. This pointer should be NULL *
 *                        on first call of the function. Caller must free      *
 *                        this pointer after usage.                            *
 *                                                                             *
 * Comment: This function does not add NULL character at end of line.          *
 *                                                                             *
 * Return value: On success, number of bytes read is returned (0 (zero)        *
 *               indicated end of file).                                       *
 *               On error, -1 (ZBX_READ_ERR) is returned and errno is set      *
 *               appropriately.                                                *
 *               If the wrong decoding is detected, -2                         *
 *               (ZBX_READ_WRONG_ENCODING) is returned.                        *
 *                                                                             *
 ******************************************************************************/
ssize_t	zbx_buf_readln(int fd, char *buf, size_t bufsz, const char *encoding, char **value, void **saveptr)
{
	char			*p_nl;
	struct buf_read_save	*save = (struct buf_read_save *)*saveptr;

	if (NULL == *saveptr)
	{
		ssize_t	nbytes;

		*saveptr = malloc(sizeof(struct buf_read_save));
		save = (struct buf_read_save *)*saveptr;
		memset(save, 0, sizeof(*save));

		zbx_find_cr_lf_szbyte(encoding, &save->cr, &save->lf, &save->szbyte);

read_buf:	/* refill buffer */
		if ((zbx_offset_t)-1 == (save->offset = zbx_lseek(fd, save->offset, SEEK_SET)))
			return ZBX_READ_ERR;	/* cannot set position to 0 */

		if (0 >= (nbytes = read(fd, buf, bufsz)))
			return nbytes;

		/* nbytes can be smaller than szbyte. If the target file was encoded in UTF-8 and contained a single */
		/* character, but the target encoding was mistakenly set to UTF-32. Then nbytes will be 1 and szbyte */
		/* will be 4. Similarly, if bytes read produces a remainder that does not fit szbyte - we can safely */
		/* assume the file contains the encoding different from the one provided to us.*/
		if ((size_t)nbytes < save->szbyte || ((size_t)nbytes % save->szbyte != 0))
			return ZBX_READ_WRONG_ENCODING;

		save->p_start = buf;			/* beginning of current line */
		save->p_end = buf + (size_t)nbytes;	/* no data from this position */
	}
	else
		save->p_start = save->p_next;		/* jump to next line */

	while (NULL == (p_nl = zbx_find_buf_newline(save->p_start, &save->p_next, save->p_end, save->cr, save->lf,
			save->szbyte)))
	{
		/* incomplete line */

		/* Note. This logic should work the same as zbx_read_text_line_from_file */
		if (save->p_end != save->p_next)
		{
			char	tmp;

			/* test for EOF */
			if (0 == read(fd, &tmp, 1))
			{
				p_nl = save->p_end - 1;
				save->p_next = save->p_end;
				break;	/* line end with EOF - just return line */
			}
		}

		if ((ssize_t)bufsz == save->p_end - save->p_start)
		{
			p_nl = save->p_end - 1;
			save->p_next = save->p_end;
			break;	/* line is split but it is bigger than buffer - just return line */
		}

		/* read next buffer window from start of this line */
		save->offset += save->p_start - buf;
		goto read_buf;
	}

	*value = save->p_start;

	return p_nl - save->p_start + 1;
}
