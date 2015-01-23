/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
#include "log.h"

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

static void	find_cr_lf_szbyte(const char *encoding, const char **cr, const char **lf, size_t *szbyte)
{
	/* default is single-byte character set */
	*cr = "\r";
	*lf = "\n";
	*szbyte = 1;

	if ('\0' != *encoding)
	{
		if (0 == strcmp(encoding, "UNICODE") || 0 == strcmp(encoding, "UNICODELITTLE") ||
				0 == strcmp(encoding, "UTF-16") || 0 == strcmp(encoding, "UTF-16LE") ||
				0 == strcmp(encoding, "UTF16") || 0 == strcmp(encoding, "UTF16LE"))
		{
			*cr = "\r\0";
			*lf = "\n\0";
			*szbyte = 2;
		}
		else if (0 == strcmp(encoding, "UNICODEBIG") || 0 == strcmp(encoding, "UNICODEFFFE") ||
				0 == strcmp(encoding, "UTF-16BE") || 0 == strcmp(encoding, "UTF16BE"))
		{
			*cr = "\0\r";
			*lf = "\0\n";
			*szbyte = 2;
		}
		else if (0 == strcmp(encoding, "UTF-32") || 0 == strcmp(encoding, "UTF-32LE") ||
				0 == strcmp(encoding, "UTF32") || 0 == strcmp(encoding, "UTF32LE"))
		{
			*cr = "\r\0\0\0";
			*lf = "\n\0\0\0";
			*szbyte = 4;
		}
		else if (0 == strcmp(encoding, "UTF-32BE") || 0 == strcmp(encoding, "UTF32BE"))
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

static char	*buf_find_newline(char *p, char **p_next, const char *p_end, const char *cr, const char *lf,
		size_t szbyte)
{
	if (1 == szbyte)	/* single-byte character set */
	{
		for (; p < p_end; p++)
		{
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

int	zbx_read2(int fd, zbx_uint64_t *lastlogsize, int *mtime, int *big_rec, int *incomplete, const char *encoding,
		ZBX_REGEXP *regexps, int regexps_num, const char *pattern, int *p_count, int *s_count,
		zbx_process_value_func_t process_value, const char *server, unsigned short port,
		const char *hostname, const char *key)
{
	int		ret, nbytes;
	const char	*cr, *lf, *p_end;
	char		*p_start, *p, *p_nl, *p_next;
	size_t		szbyte;
	zbx_offset_t	offset;
	static char	*buf = NULL;
	int		send_err;
	zbx_uint64_t	lastlogsize1;

#define BUF_SIZE	(256 * ZBX_KIBIBYTE)	/* The longest encodings use 4-bytes for every character. To send */
						/* up to 64 k characters to the Zabbix server a 256 kB buffer might */
						/* be required. */
	if (NULL == buf)
	{
		buf = zbx_malloc(buf, (size_t)(BUF_SIZE + 1));
	}

	find_cr_lf_szbyte(encoding, &cr, &lf, &szbyte);

	for (;;)
	{
		if (0 >= *p_count || 0 >= *s_count)
		{
			/* limit on number of processed or sent-to-server lines reached */
			ret = SUCCEED;
			goto out;
		}

		if ((zbx_offset_t)-1 == (offset = zbx_lseek(fd, 0, SEEK_CUR)))
		{
			*big_rec = 0;
			ret = FAIL;
			goto out;
		}

		nbytes = (int)read(fd, buf, (size_t)BUF_SIZE);

		if (-1 == nbytes)
		{
			/* error on read */
			*big_rec = 0;
			ret = FAIL;
			goto out;
		}

		if (0 == nbytes)
		{
			/* end of file reached */
			ret = SUCCEED;
			goto out;
		}

		p_start = buf;			/* beginning of current line */
		p = buf;			/* current byte */
		p_end = buf + (size_t)nbytes;	/* no data from this position */

		if (NULL == (p_nl = buf_find_newline(p, &p_next, p_end, cr, lf, szbyte)))
		{
			if (p_end > p)
				*incomplete = 1;

			if (BUF_SIZE > nbytes)
			{
				/* Buffer is not full (no more data available) and there is no "newline" in it. */
				/* Do not analyze it now, keep the same position in the file and wait the next check, */
				/* maybe more data will come. */

				*lastlogsize = (zbx_uint64_t)offset;
				ret = SUCCEED;
				goto out;
			}
			else
			{
				/* buffer is full and there is no "newline" in it */

				if (0 == *big_rec)
				{
					/* It is the first, beginning part of a long record. Match it against the */
					/* regexp now (our buffer length corresponds to what we can save in the */
					/* database). */

					char	*value = NULL;

					buf[BUF_SIZE] = '\0';

					if ('\0' != *encoding)
						value = convert_to_utf8(buf, (size_t)BUF_SIZE, encoding);
					else
						value = buf;

					zabbix_log(LOG_LEVEL_WARNING, "Logfile contains a large record: \"%.64s\""
							" (showing only the first 64 characters). Only the first 64 kB"
							" will be analyzed, the rest will be ignored while Zabbix agent"
							" is running.", value);

					lastlogsize1 = (size_t)offset + (size_t)nbytes;
					send_err = SUCCEED;

					if (SUCCEED == regexp_match_ex(regexps, regexps_num, value, pattern,
							ZBX_CASE_SENSITIVE))
					{
						send_err = process_value(server, port, hostname, key, value,
								&lastlogsize1, mtime, NULL, NULL, NULL, NULL, 1);
						if (SUCCEED == send_err)
							(*s_count)--;
					}
					(*p_count)--;

					if (SUCCEED == send_err)
					{
						*lastlogsize = lastlogsize1;
						*big_rec = 1;		/* ignore the rest of this record */
					}

					if ('\0' != *encoding)
						zbx_free(value);
				}
				else
				{
					/* It is a middle part of a long record. Ignore it. We have already */
					/* checked the first part against the regexp. */
					*lastlogsize = (size_t)offset + (size_t)nbytes;
				}
			}
		}
		else
		{
			/* the "newline" was found, so there is at least one complete record */
			/* (or trailing part of a large record) in the buffer */
			*incomplete = 0;

			for (;;)
			{
				if (0 >= *p_count || 0 >= *s_count)
				{
					/* limit on number of processed or sent-to-server lines reached */
					ret = SUCCEED;
					goto out;
				}

				if (0 == *big_rec)
				{
					char	*value = NULL;

					*p_nl = '\0';

					if ('\0' != *encoding)
						value = convert_to_utf8(p_start, (size_t)(p_nl - p_start), encoding);
					else
						value = p_start;

					lastlogsize1 = (size_t)offset + (size_t)(p_next - buf);
					send_err = SUCCEED;

					if (SUCCEED == regexp_match_ex(regexps, regexps_num, value, pattern,
							ZBX_CASE_SENSITIVE))
					{
						send_err = process_value(server, port, hostname, key, value,
								&lastlogsize1, mtime, NULL, NULL, NULL, NULL, 1);
						if (SUCCEED == send_err)
							(*s_count)--;
					}
					(*p_count)--;

					if (SUCCEED == send_err)
						*lastlogsize = lastlogsize1;

					if ('\0' != *encoding)
						zbx_free(value);
				}
				else
				{
					/* skip the trailing part of a long record */
					*lastlogsize = (size_t)offset + (size_t)(p_next - buf);
					*big_rec = 0;
				}

				/* move to the next record in the buffer */
				p_start = p_next;
				p = p_next;

				if (NULL == (p_nl = buf_find_newline(p, &p_next, p_end, cr, lf, szbyte)))
				{
					/* There are no complete records in the buffer. */
					/* Try to read more data from this position if available. */
					if (p_end > p)
						*incomplete = 1;

					if ((zbx_offset_t)-1 == zbx_lseek(fd, *lastlogsize, SEEK_SET))
					{
						ret = FAIL;
						goto out;
					}
					else
						break;
				}
				else
					*incomplete = 0;
			}
		}
	}
out:
	return ret;

#undef BUF_SIZE
}

int	zbx_is_regular_file(const char *path)
{
	zbx_stat_t	st;

	if (0 == zbx_stat(path, &st) && 0 != S_ISREG(st.st_mode))
		return SUCCEED;

	return FAIL;
}
