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
#include "threads.h"

/******************************************************************************
 *                                                                            *
 * Function: app_title                                                        *
 *                                                                            *
 * Purpose: print title of application on stdout                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:  title_message - is global variable which must be initialized    *
 *                            in each zabbix application                      *
 *                                                                            *
 ******************************************************************************/
static void	app_title(void)
{
	printf("%s v%s (revision %s) (%s)\n", title_message, ZABBIX_VERSION, ZABBIX_REVISION, ZABBIX_REVDATE);
}

/******************************************************************************
 *                                                                            *
 * Function: version                                                          *
 *                                                                            *
 * Purpose: print version and compilation time of application on stdout       *
 *          by application request with parameter '-v'                        *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
void	version(void)
{
	app_title();
	printf("Compilation time: %s %s\n", __DATE__, __TIME__);
}

/******************************************************************************
 *                                                                            *
 * Function: usage                                                            *
 *                                                                            *
 * Purpose: print application parameters on stdout                            *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:  usage_message - is global variable which must be initialized    *
 *                            in each zabbix application                      *
 *                                                                            *
 ******************************************************************************/
void	usage(void)
{
	printf("usage: %s %s\n", progname, usage_message);
}

/******************************************************************************
 *                                                                            *
 * Function: help                                                             *
 *                                                                            *
 * Purpose: print help of application parameters on stdout by application     *
 *          request with parameter '-h'                                       *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:  help_message - is global variable which must be initialized     *
 *                            in each zabbix application                      *
 *                                                                            *
 ******************************************************************************/
void	help(void)
{
	const char	**p = help_message;

	app_title();
	printf("\n");

	usage();
	printf("\n");

	while (NULL != *p)
		printf("%s\n", *p++);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_error                                                        *
 *                                                                            *
 * Purpose: Print error text to the stderr                                    *
 *                                                                            *
 * Parameters: fmt - format of message                                        *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
void	__zbx_zbx_error(const char *fmt, ...)
{
	va_list	args;

	va_start(args, fmt);

	fprintf(stderr, "%s [%li]: ", progname, zbx_get_thread_id());
	vfprintf(stderr, fmt, args);
	fprintf(stderr, "\n");
	fflush(stderr);

	va_end(args);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_snprintf                                                     *
 *                                                                            *
 * Purpose: Secure version of snprintf function.                              *
 *          Add zero character at the end of string.                          *
 *                                                                            *
 * Parameters: str - destination buffer pointer                               *
 *             count - size of destination buffer                             *
 *             fmt - format                                                   *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
size_t	__zbx_zbx_snprintf(char *str, size_t count, const char *fmt, ...)
{
	size_t	written_len;
	va_list	args;

	va_start(args, fmt);
	written_len = zbx_vsnprintf(str, count, fmt, args);
	va_end(args);

	return written_len;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_snprintf_alloc                                               *
 *                                                                            *
 * Purpose: Secure version of snprintf function.                              *
 *          Add zero character at the end of string.                          *
 *          Reallocs memory if not enough.                                    *
 *                                                                            *
 * Parameters: str       - [IN/OUT] destination buffer pointer                *
 *             alloc_len - [IN/OUT] already allocated memory                  *
 *             offset    - [IN/OUT] offset for writing                        *
 *             fmt       - [IN] format                                        *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev, Alexander Vladishev                              *
 *                                                                            *
 ******************************************************************************/
void	__zbx_zbx_snprintf_alloc(char **str, size_t *alloc_len, size_t *offset, const char *fmt, ...)
{
	va_list	args;
	size_t	avail_len, written_len;
retry:
	if (NULL == *str)
	{
		/* zbx_vsnprintf() returns bytes actually written instead of bytes to write, */
		/* so we have to use the standard function                                   */
		va_start(args, fmt);
		*alloc_len = vsnprintf(NULL, 0, fmt, args) + 2;	/* '\0' + one byte to prevent the operation retry */
		va_end(args);
		*offset = 0;
		*str = zbx_malloc(*str, *alloc_len);
	}

	avail_len = *alloc_len - *offset;
	va_start(args, fmt);
	written_len = zbx_vsnprintf(*str + *offset, avail_len, fmt, args);
	va_end(args);

	if (written_len == avail_len - 1)
	{
		*alloc_len *= 2;
		*str = zbx_realloc(*str, *alloc_len);

		goto retry;
	}

	*offset += written_len;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_vsnprintf                                                    *
 *                                                                            *
 * Purpose: Secure version of vsnprintf function.                             *
 *          Add zero character at the end of string.                          *
 *                                                                            *
 * Parameters: str   - [IN/OUT] destination buffer pointer                    *
 *             count - [IN] size of destination buffer                        *
 *             fmt   - [IN] format                                            *
 *                                                                            *
 * Return value: the number of characters in the output buffer                *
 *               (not including the trailing '\0')                            *
 *                                                                            *
 * Author: Alexei Vladishev (see also zbx_snprintf)                           *
 *                                                                            *
 ******************************************************************************/
size_t	zbx_vsnprintf(char *str, size_t count, const char *fmt, va_list args)
{
	int	written_len = 0;

	if (0 < count)
	{
		if (0 > (written_len = vsnprintf(str, count, fmt, args)))
			written_len = (int)count - 1;		/* count an output error as a full buffer */
		else
			written_len = MIN(written_len, (int)count - 1);		/* result could be truncated */
	}
	str[written_len] = '\0';	/* always write '\0', even if buffer size is 0 or vsnprintf() error */

	return (size_t)written_len;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_strncpy_alloc, zbx_strcpy_alloc, zbx_chrcpy_alloc            *
 *                                                                            *
 * Purpose: If there is no '\0' byte among the first n bytes of src,          *
 *          then all n bytes will be placed into the dest buffer.             *
 *          In other case only strlen() bytes will be placed there.           *
 *          Add zero character at the end of string.                          *
 *          Reallocs memory if not enough.                                    *
 *                                                                            *
 * Parameters: str       - [IN/OUT] destination buffer pointer                *
 *             alloc_len - [IN/OUT] already allocated memory                  *
 *             offset    - [IN/OUT] offset for writing                        *
 *             src       - [IN] copied string                                 *
 *             n         - [IN] maximum number of bytes to copy               *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	zbx_strncpy_alloc(char **str, size_t *alloc_len, size_t *offset, const char *src, size_t n)
{
	if (NULL == *str)
	{
		*alloc_len = n + 1;
		*offset = 0;
		*str = zbx_malloc(*str, *alloc_len);
	}
	else if (*offset + n >= *alloc_len)
	{
		while (*offset + n >= *alloc_len)
			*alloc_len *= 2;
		*str = zbx_realloc(*str, *alloc_len);
	}

	while (0 != n && '\0' != *src)
	{
		(*str)[(*offset)++] = *src++;
		n--;
	}

	(*str)[*offset] = '\0';
}

void	zbx_strcpy_alloc(char **str, size_t *alloc_len, size_t *offset, const char *src)
{
	zbx_strncpy_alloc(str, alloc_len, offset, src, strlen(src));
}

void	zbx_chrcpy_alloc(char **str, size_t *alloc_len, size_t *offset, char c)
{
	zbx_strncpy_alloc(str, alloc_len, offset, &c, 1);
}

/* Has to be rewritten to avoid malloc */
char	*string_replace(const char *str, const char *sub_str1, const char *sub_str2)
{
	char *new_str = NULL;
	const char *p;
	const char *q;
	const char *r;
	char *t;
	long len;
	long diff;
	unsigned long count = 0;

	assert(str);
	assert(sub_str1);
	assert(sub_str2);

	len = (long)strlen(sub_str1);

	/* count the number of occurrences of sub_str1 */
	for ( p=str; (p = strstr(p, sub_str1)); p+=len, count++ );

	if ( 0 == count )	return strdup(str);

	diff = (long)strlen(sub_str2) - len;

        /* allocate new memory */
        new_str = zbx_malloc(new_str, (size_t)(strlen(str) + count*diff + 1)*sizeof(char));

        for (q=str,t=new_str,p=str; (p = strstr(p, sub_str1)); )
        {
                /* copy until next occurrence of sub_str1 */
                for ( ; q < p; *t++ = *q++);
                q += len;
                p = q;
                for ( r = sub_str2; (*t++ = *r++); );
                --t;
        }
        /* copy the tail of str */
        for( ; *q ; *t++ = *q++ );

	*t = '\0';

	return new_str;
}

/******************************************************************************
 *                                                                            *
 * Function: del_zeroes                                                       *
 *                                                                            *
 * Purpose: delete all right '0' and '.' for the string                       *
 *                                                                            *
 * Parameters: s - string to trim '0'                                         *
 *                                                                            *
 * Return value: string without right '0'                                     *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: 10.0100 => 10.01, 10. => 10                                      *
 *                                                                            *
 ******************************************************************************/
void	del_zeroes(char *s)
{
	int     i;

	if(strchr(s,'.')!=NULL)
	{
		for(i = (int)strlen(s)-1;;i--)
		{
			if(s[i]=='0')
			{
				s[i]=0;
			}
			else if(s[i]=='.')
			{
				s[i]=0;
				break;
			}
			else
			{
				break;
			}
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_rtrim                                                        *
 *                                                                            *
 * Purpose: Strip characters from the end of a string                         *
 *                                                                            *
 * Parameters: str - string for processing                                    *
 *             charlist - null terminated list of characters                  *
 *                                                                            *
 * Return value: number of trimmed characters                                 *
 *                                                                            *
 * Author: Eugene Grigorjev, Aleksandrs Saveljevs                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_rtrim(char *str, const char *charlist)
{
	char	*p;
	int	count = 0;

	if (NULL == str || '\0' == *str)
		return count;

	for (p = str + strlen(str) - 1; p >= str && NULL != strchr(charlist, *p); p--)
	{
		*p = '\0';
		count++;
	}

	return count;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_ltrim                                                        *
 *                                                                            *
 * Purpose: Strip characters from the beginning of a string                   *
 *                                                                            *
 * Parameters: str - string for processing                                    *
 *             charlist - null terminated list of characters                  *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_ltrim(char *str, const char *charlist)
{
	char	*p;

	if (NULL == str || '\0' == *str)
		return;

	for (p = str; '\0' != *p && NULL != strchr(charlist, *p); p++)
		;

	if (p == str)
		return;

	while ('\0' != *p)
		*str++ = *p++;

	*str = '\0';
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_lrtrim                                                       *
 *                                                                            *
 * Purpose: Removes leading and trailing characters from the specified        *
 *          character string                                                  *
 *                                                                            *
 * Parameters: str      - [IN/OUT] string for processing                      *
 *             charlist - [IN] null terminated list of characters             *
 *                                                                            *
 ******************************************************************************/
void	zbx_lrtrim(char *str, const char *charlist)
{
	zbx_rtrim(str, charlist);
	zbx_ltrim(str, charlist);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_remove_chars                                                 *
 *                                                                            *
 * Purpose: Remove characters 'charlist' from the whole string                *
 *                                                                            *
 * Parameters: str - string for processing                                    *
 *             charlist - null terminated list of characters                  *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	zbx_remove_chars(register char *str, const char *charlist)
{
	register char *p;

	if (NULL == str || NULL == charlist || '\0' == *str || '\0' == *charlist)
		return;

	for (p = str; '\0' != *p; p++)
	{
		if (NULL == strchr(charlist, *p))
			*str++ = *p;
	}

	*str = '\0';
}

/******************************************************************************
 *                                                                            *
 * Function: compress_signs                                                   *
 *                                                                            *
 * Purpose: convert all repeating pluses and minuses                          *
 *                                                                            *
 * Parameters: c - string to convert                                          *
 *                                                                            *
 * Return value: string without minuses                                       *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: -3*--8+5-7*-4+++5 -> N3*8+5+N7*N4+5                              *
 *                                                                            *
 ******************************************************************************/
void	compress_signs(char *str)
{
	int	i, j, len, loop = 1;
	char	cur, next, prev;

	/* compress '--' '+-' '++' '-+' */
	while (1 == loop)
	{
		loop = 0;

		for (i = 0; '\0' != str[i]; i++)
		{
			cur = str[i];
			next = str[i + 1];

			if (('-' == cur && '-' == next) || ('+' == cur && '+' == next))
			{
				str[i] = '+';
				for (j = i + 1; '\0' != str[j]; j++)
					str[j] = str[j + 1];
				loop = 1;
			}

			if (('-' == cur && '+' == next) || ('+' == cur && '-' == next))
			{
				str[i] = '-';
				for (j = i + 1; '\0' != str[j]; j++)
					str[j] = str[j + 1];
				loop = 1;
			}
		}
	}

	/* remove '-', '+' where needed, convert -123 to +N123 */
	for (i = 0; '\0' != str[i]; i++)
	{
		cur = str[i];

		if ('+' == cur)
		{
			/* plus is the first sign in the expression */
			if (0 == i)
			{
				for (j = i; '\0' != str[j]; j++)
					str[j] = str[j + 1];
			}
			else
			{
				prev = str[i - 1];

				if (0 == isdigit(prev) && '.' != prev && NULL == strchr("KMGTsmhdw", prev))
				{
					for (j = i; '\0' != str[j]; j++)
						str[j] = str[j + 1];
				}
			}
		}
		else if ('-' == cur)
		{
			/* minus is the first sign in the expression */
			if (0 == i)
			{
				str[i] = 'N';
			}
			else
			{
				prev = str[i - 1];

				if (0 == isdigit(prev) && '.' != prev && NULL == strchr("KMGTsmhdw", prev))
				{
					str[i] = 'N';
				}
				else
				{
					len = (int)strlen(str);

					for (j = len; j > i; j--)
						str[j] = str[j - 1];

					str[i] = '+';
					str[i + 1] = 'N';
					str[len + 1] = '\0';
					i++;
				}
			}
		}
	}
}

/*
 * Function: strlcpy, strlcat
 * Copyright (c) 1998 Todd C. Miller <Todd.Miller@courtesan.com>
 *
 * Permission to use, copy, modify, and distribute this software for any
 * purpose with or without fee is hereby granted, provided that the above
 * copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
 * WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
 * ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
 * WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
 * ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
 * OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

/******************************************************************************
 *                                                                            *
 * Function: zbx_strlcpy                                                      *
 *                                                                            *
 * Purpose: replacement of insecure strncpy, same as OpenBSD's strlcpy        *
 *                                                                            *
 * Copy src to string dst of size siz.  At most siz-1 characters              *
 * will be copied.  Always NUL terminates (unless siz == 0).                  *
 * Returns strlen(src); if retval >= siz, truncation occurred.                *
 *                                                                            *
 * Author: Todd C. Miller <Todd.Miller@courtesan.com>                         *
 *                                                                            *
 ******************************************************************************/
size_t	zbx_strlcpy(char *dst, const char *src, size_t siz)
{
	char		*d = dst;
	const char	*s = src;
	size_t		n = siz;

	/* copy as many bytes as will fit */
	if (0 != n)
	{
		while (0 != --n)
		{
			if ('\0' == (*d++ = *s++))
				break;
		}
	}

	/* not enough room in dst, add NUL and traverse rest of src */
	if (0 == n)
	{
		if (0 != siz)
			*d = '\0';	/* NUL-terminate dst */
		while ('\0' != *s++)
			;
	}

	return s - src - 1;	/* count does not include NUL */
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_strlcat                                                      *
 *                                                                            *
 * Purpose: replacement of insecure strncat, same as OpenBSD's strlcat        *
 *                                                                            *
 * Appends src to string dst of size siz (unlike strncat, size is the         *
 * full size of dst, not space left).  At most siz-1 characters               *
 * will be copied.  Always NUL terminates (unless siz <= strlen(dst)).        *
 * Returns strlen(src) + MIN(siz, strlen(initial dst)).                       *
 * If retval >= siz, truncation occurred.                                     *
 *                                                                            *
 * Author: Todd C. Miller <Todd.Miller@courtesan.com>                         *
 *                                                                            *
 ******************************************************************************/
size_t	zbx_strlcat(char *dst, const char *src, size_t siz)
{
	char		*d = dst;
	const char	*s = src;
	size_t		n = siz;
	size_t		dlen;

	/* find the end of dst and adjust bytes left but don't go past end */
	while (0 != n-- && '\0' != *d)
		d++;
	dlen = d - dst;
	n = siz - dlen;

	if (0 == n)
		return dlen + strlen(s);

	while ('\0' != *s)
	{
		if (1 != n)
		{
			*d++ = *s;
			n--;
		}
		s++;
	}

	*d = '\0';

	return dlen + (s - src);	/* count does not include NUL */
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_strlcpy_utf8                                                 *
 *                                                                            *
 * Purpose: copies utf-8 string + terminating zero character into specified   *
 *          buffer                                                            *
 *                                                                            *
 * Return value: the number of copied bytes excluding terminating zero        *
 *               character.                                                   *
 *                                                                            *
 * Comments: If the source string is larger than destination buffer then the  *
 *           string is truncated after last valid utf-8 character rather than *
 *           byte.                                                            *
 *                                                                            *
 ******************************************************************************/
size_t	zbx_strlcpy_utf8(char *dst, const char *src, size_t size)
{
	size = zbx_strlen_utf8_nbytes(src, size - 1);
	memcpy(dst, src, size);
	dst[size] = '\0';

	return size;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dvsprintf                                                    *
 *                                                                            *
 * Purpose: dynamical formatted output conversion                             *
 *                                                                            *
 * Return value: formatted string                                             *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: returns a pointer to allocated memory                            *
 *                                                                            *
 ******************************************************************************/
char	*zbx_dvsprintf(char *dest, const char *f, va_list args)
{
	char	*string = NULL;
	int	n, size = MAX_STRING_LEN >> 1;

	va_list curr;

	while (1)
	{
		string = zbx_malloc(string, size);

		va_copy(curr, args);
		n = vsnprintf(string, size, f, curr);
		va_end(curr);

		if (0 <= n && n < size)
			break;

		/* result was truncated */
		if (-1 == n)
			size = size * 3 / 2 + 1;	/* the length is unknown */
		else
			size = n + 1;	/* n bytes + trailing '\0' */

		zbx_free(string);
	}

	zbx_free(dest);

	return string;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dsprintf                                                     *
 *                                                                            *
 * Purpose: dynamical formatted output conversion                             *
 *                                                                            *
 * Return value: formatted string                                             *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: returns a pointer to allocated memory                            *
 *                                                                            *
 ******************************************************************************/
char	*__zbx_zbx_dsprintf(char *dest, const char *f, ...)
{
	char	*string;
	va_list args;

	va_start(args, f);

	string = zbx_dvsprintf(dest, f, args);

	va_end(args);

	return string;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_strdcat                                                      *
 *                                                                            *
 * Purpose: dynamical cating of strings                                       *
 *                                                                            *
 * Return value: new pointer of string                                        *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: returns a pointer to allocated memory                            *
 *           zbx_strdcat(NULL, "") will return "", not NULL!                  *
 *                                                                            *
 ******************************************************************************/
char	*zbx_strdcat(char *dest, const char *src)
{
	size_t	len_dest, len_src;

	if (NULL == src)
		return dest;

	if (NULL == dest)
		return zbx_strdup(NULL, src);

	len_dest = strlen(dest);
	len_src = strlen(src);

	dest = zbx_realloc(dest, len_dest + len_src + 1);

	zbx_strlcpy(dest + len_dest, src, len_src + 1);

	return dest;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_strdcatf                                                     *
 *                                                                            *
 * Purpose: dynamical cating of formated strings                              *
 *                                                                            *
 * Return value: new pointer of string                                        *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: returns a pointer to allocated memory                            *
 *                                                                            *
 ******************************************************************************/
char	*__zbx_zbx_strdcatf(char *dest, const char *f, ...)
{
	char	*string, *result;
	va_list	args;

	va_start(args, f);
	string = zbx_dvsprintf(NULL, f, args);
	va_end(args);

	result = zbx_strdcat(dest, string);

	zbx_free(string);

	return result;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_check_hostname                                               *
 *                                                                            *
 * Purpose: check a byte stream for a valid hostname                          *
 *                                                                            *
 * Parameters: hostname - pointer to the first char of hostname               *
 *                                                                            *
 * Return value: return SUCCEED if hostname is valid                          *
 *               or FAIL if hostname contains invalid chars, is empty         *
 *               or is longer than MAX_ZBX_HOSTNAME_LEN                       *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
int	zbx_check_hostname(const char *hostname)
{
	int	len = 0;

	while ('\0' != hostname[len])
	{
		if (FAIL == is_hostname_char(hostname[len++]))
			return FAIL;
	}

	if (0 == len || MAX_ZBX_HOSTNAME_LEN < len)
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: parse_host                                                       *
 *                                                                            *
 * Purpose: parse hostname                                                    *
 *                                                                            *
 *  e.g., Zabbix server                                                       *
 *                                                                            *
 * Parameters: exp - pointer to the first char of hostname                    *
 *             host - optional pointer to resulted hostname                   *
 *                                                                            *
 *  e.g., {Zabbix server:agent.ping.last(0)}                                  *
 *         ^                                                                  *
 *                                                                            *
 * Return value: return SUCCEED and move exp to the next char after hostname  *
 *               or FAIL and move exp at the failed character                 *
 *                                                                            *
 * Author: Aleksandrs Saveljevs                                               *
 *                                                                            *
 ******************************************************************************/
int	parse_host(char **exp, char **host)
{
	char	*p, *s;

	p = *exp;

	for (s = *exp; SUCCEED == is_hostname_char(*s); s++)
		;

	*exp = s;

	if (p == s)
		return FAIL;

	if (NULL != host)
	{
		char	c;

		c = *s;
		*s = '\0';
		*host = strdup(p);
		*s = c;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: parse_key                                                        *
 *                                                                            *
 * Purpose: return key with parameters (if present)                           *
 *                                                                            *
 *  e.g., system.run[cat /etc/passwd | awk -F: '{ print $1 }']                *
 *                                                                            *
 * Parameters: exp - pointer to the first char of key                         *
 *             key - pointer to the resulted key                              *
 *                                                                            *
 *  e.g., {host:system.run[cat /etc/passwd | awk -F: '{ print $1 }'].last(0)} *
 *              ^                                                             *
 *                                                                            *
 * Return value: return SUCCEED and move exp to the next character after key  *
 *               or FAIL and move exp to incorrect character                  *
 *                                                                            *
 * Author: Aleksandrs Saveljevs                                               *
 *                                                                            *
 ******************************************************************************/
int	parse_key(char **exp, char **key)
{
	char	c;
	char	*p, *r, *s;

	p = *exp;

	for (s = *exp; SUCCEED == is_key_char(*s); s++)
		;

	if ('\0' == *s)		/* no function specified? */
	{
		*key = strdup(p);
		*exp = s;
		return SUCCEED;
	}
	else if ('(' == *s)	/* for instance, ssh,22.last(0) */
	{
		for (r = s - 1; p <= r && '.' != *r; r--)
			;

		if (r <= p)
		{
			*exp = s;
			return FAIL;
		}

		*r = '\0';
		*key = strdup(p);
		*r = '.';

		*exp = r;
		return SUCCEED;
	}
	else if ('[' == *s)	/* for instance, net.tcp.port[,80] */
	{
		int	state = 0;	/* 0 - init, 1 - inside quoted param, 2 - inside unquoted param */
		int	array = 0;	/* array nest level */

		for (s++; '\0' != *s; s++)
		{
			switch (state)
			{
				/* init state */
				case 0:
					if (',' == *s)
						;
					else if (']' == *s && '[' == s[1] && 0 == array)	/* Zapcat */
						s++;
					else if ('"' == *s)
						state = 1;
					else if ('[' == *s)
						array++;
					else if (']' == *s && 0 != array)
					{
						array--;

						/* skip spaces */
						while (' ' == s[1])
							s++;

						if (0 == array && ']' == s[1])
						{
							s++;
							goto succeed;
						}

						if (',' != s[1] && !(0 != array && ']' == s[1]))
						{
							s++;
							goto fail;	/* incorrect syntax */
						}
					}
					else if (']' == *s && 0 == array)
						goto succeed;
					else if (' ' != *s)
						state = 2;
					break;
				/* quoted */
				case 1:
					if ('"' == *s)
					{
						/* skip spaces */
						while (' ' == s[1])
							s++;

						if (0 == array && ']' == s[1] && '[' == s[2])	/* Zapcat */
						{
							state = 0;
							break;
						}

						if (0 == array && ']' == s[1])
						{
							s++;
							goto succeed;
						}

						if (',' != s[1] && !(0 != array && ']' == s[1]))
						{
							s++;
							goto fail;	/* incorrect syntax */
						}

						state = 0;
					}
					else if ('\\' == *s && '"' == s[1])
						s++;
					break;
				/* unquoted */
				case 2:
					if (0 == array && ']' == *s && '[' == s[1])	/* Zapcat */
					{
						s--;
						state = 0;
					}
					else if (',' == *s || (']' == *s && 0 != array))
					{
						s--;
						state = 0;
					}
					else if (']' == *s && 0 == array)
						goto succeed;
					break;
			}
		}
fail:
		*exp = s;
		return FAIL;
succeed:
		c = *(++s);
		*s = '\0';
		*key = strdup(p);
		*s = c;

		*exp = s;
		return SUCCEED;
	}
	else
	{
		*exp = s;
		return FAIL;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: parse_function                                                   *
 *                                                                            *
 * Purpose: return function and function parameters                           *
 *          func(param,...)                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *         exp - pointer to the first char of function                        *
 *                last("host:key[key params]",#1)                             *
 *                ^                                                           *
 *         func - optional pointer to resulted function                       *
 *         params - optional pointer to resulted function parameters          *
 *                                                                            *
 * Return value: return SUCCEED and move exp to the next char after right ')' *
 *               or FAIL and move exp to incorrect character                  *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
int	parse_function(char **exp, char **func, char **params)
{
	char		*p, *s;
	int		state;		/* 0 - init
					 * 1 - function name/params
					 */
	unsigned char	flags = 0x00;	/* 0x01 - function OK
					 * 0x02 - params OK
					 */

	for (p = *exp, s = *exp, state = 0; '\0' != *p; p++)	/* check for function */
	{
		if (SUCCEED == is_function_char(*p))
		{
			state = 1;
			continue;
		}

		if (0 == state)
			goto error;

		if ('(' == *p)	/* key parameters
				 * last("hostname:vfs.fs.size[\"/\",\"total\"]",0)}
				 * ----^
				 */
		{
			int	state;	/* 0 - init
					 * 1 - inside quoted param
					 * 2 - inside unquoted param
					 * 3 - end of params
					 */

			if (NULL != func)
			{
				*p = '\0';
				*func = zbx_strdup(NULL, s);
				*p++ = '(';
			}
			flags |= 0x01;

			for (s = p, state = 0; '\0' != *p; p++)
			{
				switch (state) {
				/* init state */
				case 0:
					if (',' == *p)
						;
					else if ('"' == *p)
						state = 1;
					else if (')' == *p)
						state = 3;
					else if (' ' != *p)
						state = 2;
					break;
				/* quoted */
				case 1:
					if ('"' == *p)
					{
						if ('"' != p[1])
							state = 0;
						else
							goto error;
					}
					else if ('\\' == *p && '"' == p[1])
						p++;
					break;
				/* unquoted */
				case 2:
					if (',' == *p)
						state = 0;
					else if (')' == *p)
						state = 3;
					break;
				}

				if (3 == state)
					break;
			}

			if (3 == state)
			{
				if (NULL != params)
				{
					*p = '\0';
					*params = zbx_strdup(NULL, s);
					*p = ')';
				}
				flags |= 0x02;
			}
			else
				goto error;
		}
		else
			goto error;

		break;
	}

	if (0x03 != flags)
		goto error;

	*exp = p + 1;

	return SUCCEED;
error:
	if (NULL != func)
		zbx_free(*func);
	if (NULL != params)
		zbx_free(*params);

	*exp = p;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: parse_host_key                                                   *
 *                                                                            *
 * Purpose: return hostname and key                                           *
 *          <hostname:>key                                                    *
 *                                                                            *
 * Parameters:                                                                *
 *         exp - pointer to the first char of hostname                        *
 *                host:key[key params]                                        *
 *                ^                                                           *
 *                                                                            *
 * Return value: return SUCCEED or FAIL                                       *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
int	parse_host_key(char *exp, char **host, char **key)
{
	char	*p, *s;

	if (NULL == exp || '\0' == *exp)
		return FAIL;

	for (p = exp, s = exp; '\0' != *p; p++)	/* check for optional hostname */
	{
		if (':' == *p)	/* hostname:vfs.fs.size[/,total]
				 * --------^
				 */
		{
			*p = '\0';
			*host = zbx_strdup(NULL, s);
			*p++ = ':';

			s = p;
			break;
		}

		if (SUCCEED != is_hostname_char(*p))
			break;
	}

	*key = zbx_strdup(NULL, s);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: num_param                                                        *
 *                                                                            *
 * Purpose: calculate count of parameters from parameter list (param)         *
 *                                                                            *
 * Parameters:                                                                *
 *      param  - parameter list                                               *
 *                                                                            *
 * Return value: count of parameters                                          *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:  delimeter for parameters is ','                                 *
 *                                                                            *
 ******************************************************************************/
int	num_param(const char *p)
{
/* 0 - init, 1 - inside quoted param, 2 - inside unquoted param */
	int	ret = 1, state, array;

	if (p == NULL)
		return 0;

	for (state = 0, array = 0; '\0' != *p; p++)
	{
		switch (state) {
		/* Init state */
		case 0:
			if (',' == *p)
			{
				if (0 == array)
					ret++;
			}
			else if ('"' == *p)
				state = 1;
			else if ('[' == *p)
				array++;
			else if (']' == *p && 0 != array)
			{
				array--;

				/* skip spaces */
				while (' ' == p[1])
					p++;

				if (',' != p[1] && '\0' != p[1] && (0 == array || ']' != p[1]))
					return 0;	/* incorrect syntax */
			}
			else if (' ' != *p)
				state = 2;
			break;
		/* Quoted */
		case 1:
			if ('"' == *p)
			{
				/* skip spaces */
				while (' ' == p[1])
					p++;

				if (',' != p[1] && '\0' != p[1] && (0 == array || ']' != p[1]))
					return 0;	/* incorrect syntax */

				state = 0;
			}
			else if ('\\' == *p && '"' == p[1])
				p++;
			break;
		/* Unquoted */
		case 2:
			if (',' == *p || (']' == *p && 0 != array))
			{
				p--;
				state = 0;
			}
			break;
		}
	}

	/* missing terminating '"' character */
	if (state == 1)
		return 0;

	/* missing terminating ']' character */
	if (array != 0)
		return 0;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: get_param                                                        *
 *                                                                            *
 * Purpose: return parameter by index (num) from parameter list (param)       *
 *                                                                            *
 * Parameters:                                                                *
 *      p       - parameter list                                              *
 *      num     - requested parameter index                                   *
 *      buf     - pointer of output buffer                                    *
 *      max_len - size of output buffer                                       *
 *                                                                            *
 * Return value:                                                              *
 *      1 - requested parameter missing or buffer overflow                    *
 *      0 - requested parameter found (value - 'buf' can be empty string)     *
 *                                                                            *
 * Author: Eugene Grigorjev, rewritten by Alexei Vladishev                    *
 *                                                                            *
 * Comments:  delimeter for parameters is ','                                 *
 *                                                                            *
 ******************************************************************************/
int	get_param(const char *p, int num, char *buf, size_t max_len)
{
#define ZBX_ASSIGN_PARAM				\
{							\
	if (buf_i == max_len)				\
		return 1;	/* buffer overflow */	\
	buf[buf_i++] = *p;				\
}

	int	state;	/* 0 - init, 1 - inside quoted param, 2 - inside unquoted param */
	int	array, idx = 1, buf_i = 0;

	if (0 == max_len)
		return 1;	/* buffer overflow */

	max_len--;	/* '\0' */

	for (state = 0, array = 0; '\0' != *p && idx <= num; p++)
	{
		switch (state)
		{
			/* init state */
			case 0:
				if (',' == *p)
				{
					if (0 == array)
						idx++;
					else if (idx == num)
						ZBX_ASSIGN_PARAM;
				}
				else if ('"' == *p)
				{
					state = 1;
					if (0 != array && idx == num)
						ZBX_ASSIGN_PARAM;
				}
				else if ('[' == *p)
				{
					if (0 != array && idx == num)
						ZBX_ASSIGN_PARAM;
					array++;
				}
				else if (']' == *p && 0 != array)
				{
					array--;
					if (0 != array && idx == num)
						ZBX_ASSIGN_PARAM;

					/* skip spaces */
					while (' ' == p[1])
						p++;

					if (',' != p[1] && '\0' != p[1] && (0 == array || ']' != p[1]))
						return 1;	/* incorrect syntax */
				}
				else if (' ' != *p)
				{
					if (idx == num)
						ZBX_ASSIGN_PARAM;
					state = 2;
				}
				break;
			case 1:
				/* quoted */

				if ('"' == *p)
				{
					if (0 != array && idx == num)
						ZBX_ASSIGN_PARAM;

					/* skip spaces */
					while (' ' == p[1])
						p++;

					if (',' != p[1] && '\0' != p[1] && (0 == array || ']' != p[1]))
						return 1;	/* incorrect syntax */

					state = 0;
				}
				else if ('\\' == *p && '"' == p[1] && 0 == array)
				{
					p++;
					if (idx == num)
						ZBX_ASSIGN_PARAM;
				}
				else if (idx == num)
					ZBX_ASSIGN_PARAM;
				break;
			case 2:
				/* unquoted */

				if (',' == *p || (']' == *p && 0 != array))
				{
					p--;
					state = 0;
				}
				else if (idx == num)
					ZBX_ASSIGN_PARAM;
				break;
		}

		if (idx > num)
			break;
	}
#undef ZBX_ASSIGN_PARAM

	/* missing terminating '"' character */
	if (1 == state)
		return 1;

	/* missing terminating ']' character */
	if (0 != array)
		return 1;

	buf[buf_i] = '\0';

	if (idx >= num)
		return 0;

	return 1;
}

/******************************************************************************
 *                                                                            *
 * Function: get_param_len                                                    *
 *                                                                            *
 * Purpose: return length of the parameter by index (num)                     *
 *          from parameter list (param)                                       *
 *                                                                            *
 * Parameters:                                                                *
 *      p   - [IN]  parameter list                                            *
 *      num - [IN]  requested parameter index                                 *
 *      sz  - [OUT] length of requested parameter                             *
 *                                                                            *
 * Return value:                                                              *
 *      1 - requested parameter missing                                       *
 *      0 - requested parameter found                                         *
 *          (for first parameter result is always 0)                          *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: delimeter for parameters is ','                                  *
 *                                                                            *
 ******************************************************************************/
static int	get_param_len(const char *p, int num, size_t *sz)
{
/* 0 - init, 1 - inside quoted param, 2 - inside unquoted param */
	int	state, array, idx = 1;

	*sz = 0;

	for (state = 0, array = 0; '\0' != *p && idx <= num; p++)
	{
		switch (state) {
		/* Init state */
		case 0:
			if (',' == *p)
			{
				if (0 == array)
					idx++;
				else if (idx == num)
					(*sz)++;
			}
			else if ('"' == *p)
			{
				state = 1;
				if (0 != array && idx == num)
					(*sz)++;
			}
			else if ('[' == *p)
			{
				if (0 != array && idx == num)
					(*sz)++;
				array++;
			}
			else if (']' == *p && 0 != array)
			{
				array--;
				if (0 != array && idx == num)
					(*sz)++;

				/* skip spaces */
				while (' ' == p[1])
					p++;

				if (',' != p[1] && '\0' != p[1] && (0 == array || ']' != p[1]))
					return 1;	/* incorrect syntax */
			}
			else if (' ' != *p)
			{
				if (idx == num)
					(*sz)++;
				state = 2;
			}
			break;
		/* Quoted */
		case 1:
			if ('"' == *p)
			{
				if (0 != array && idx == num)
					(*sz)++;

				/* skip spaces */
				while (' ' == p[1])
					p++;

				if (',' != p[1] && '\0' != p[1] && (0 == array || ']' != p[1]))
					return 1;	/* incorrect syntax */

				state = 0;
			}
			else if ('\\' == *p && '"' == p[1] && 0 == array)
			{
				p++;
				if (idx == num)
					(*sz)++;
			}
			else if (idx == num)
				(*sz)++;
			break;
		/* Unquoted */
		case 2:
			if (',' == *p || (']' == *p && 0 != array))
			{
				p--;
				state = 0;
			}
			else if (idx == num)
				(*sz)++;
			break;
		}

		if (idx > num)
			break;
	}

	/* missing terminating '"' character */
	if (state == 1)
		return 1;

	/* missing terminating ']' character */
	if (array != 0)
		return 1;

	if (idx >= num)
		return 0;

	return 1;
}

/******************************************************************************
 *                                                                            *
 * Function: get_param_dyn                                                    *
 *                                                                            *
 * Purpose: return parameter by index (num) from parameter list (param)       *
 *                                                                            *
 * Parameters:                                                                *
 *      p   - [IN] parameter list                                             *
 *      num - [IN] requested parameter index                                  *
 *                                                                            *
 * Return value:                                                              *
 *      NULL - requested parameter missing                                    *
 *      otherwise - requested parameter                                       *
 *          (for first parameter result is not NULL)                          *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:  delimeter for parameters is ','                                 *
 *                                                                            *
 ******************************************************************************/
char	*get_param_dyn(const char *p, int num)
{
	char	*buf = NULL;
	size_t	sz;

	if (0 != get_param_len(p, num, &sz))
		return buf;

	buf = zbx_malloc(buf, sz + 1);

	if (0 != get_param(p, num, buf, sz + 1))
		zbx_free(buf);

	return buf;
}

/******************************************************************************
 *                                                                            *
 * Function: replace_key_param                                                *
 *                                                                            *
 * Purpose: replaces an item key, SNMP OID or their parameters when callback  *
 *          function returns a new string                                     *
 *                                                                            *
 * Comments: auxiliary function for replace_key_params_dyn()                  *
 *                                                                            *
 ******************************************************************************/
static void	replace_key_param(char **data, int key_type, size_t l, size_t *r, int level, int num, int quoted,
		replace_key_param_f cb, void *cb_data)
{
	char	c = (*data)[*r], *param;

	(*data)[*r] = '\0';
	param = cb(*data + l, key_type, level, num, quoted, cb_data);
	(*data)[*r] = c;

	if (NULL != param)
	{
		(*r)--;
		zbx_replace_string(data, l, r, param);
		(*r)++;

		zbx_free(param);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: replace_key_params_dyn                                           *
 *                                                                            *
 * Purpose: replaces an item key, SNMP OID or their parameters by using       *
 *          callback function                                                 *
 *                                                                            *
 * Parameters:                                                                *
 *      data      - [IN/OUT] item key or SNMP OID                             *
 *      key_type  - [IN] ZBX_KEY_TYPE_*                                       *
 *      cb        - [IN] callback function                                    *
 *      cb_data   - [IN] callback function custom data                        *
 *      error     - [OUT] error messsage                                      *
 *      maxerrlen - [IN] error size                                           *
 *                                                                            *
 * Return value: SUCCEED - function executed successfully                     *
 *               FAIL - otherwise, error will contain error message           *
 *                                                                            *
 ******************************************************************************/
int	replace_key_params_dyn(char **data, int key_type, replace_key_param_f cb, void *cb_data, char *error,
		size_t maxerrlen)
{
	typedef enum
	{
		ZBX_STATE_NEW,
		ZBX_STATE_END,
		ZBX_STATE_UNQUOTED,
		ZBX_STATE_QUOTED
	}
	zbx_parser_state_t;

	size_t			i, l = 0;
	int			level = 0, num = 0, ret = SUCCEED;
	zbx_parser_state_t	state = ZBX_STATE_END;

	if (ZBX_KEY_TYPE_ITEM == key_type)
	{
		for (i = 0; SUCCEED == is_key_char((*data)[i]) && '\0' != (*data)[i]; i++)
			;

		if (0 == i)
			goto clean;

		if ('[' != (*data)[i] && '\0' != (*data)[i])
			goto clean;
	}
	else
	{
		for (i = 0; '[' != (*data)[i] && '\0' != (*data)[i]; i++)
			;
	}

	replace_key_param(data, key_type, 0, &i, level, num, 0, cb, cb_data);

	for (; '\0' != (*data)[i]; i++)
	{
		if (0 == level)
		{
			/* first square bracket + Zapcat compatibility */
			if (ZBX_STATE_END == state && '[' == (*data)[i])
				state = ZBX_STATE_NEW;
			else
				break;
		}

		switch (state)
		{
			case ZBX_STATE_NEW:	/* a new parameter started */
				switch ((*data)[i])
				{
					case ' ':
						break;
					case ',':
						replace_key_param(data, key_type, i, &i, level, num, 0, cb, cb_data);
						if (1 == level)
							num++;
						break;
					case '[':
						level++;
						if (1 == level)
							num++;
						break;
					case ']':
						replace_key_param(data, key_type, i, &i, level, num, 0, cb, cb_data);
						level--;
						state = ZBX_STATE_END;
						break;
					case '"':
						state = ZBX_STATE_QUOTED;
						l = i;
						break;
					default:
						state = ZBX_STATE_UNQUOTED;
						l = i;
				}
				break;
			case ZBX_STATE_END:	/* end of parameter */
				switch ((*data)[i])
				{
					case ' ':
						break;
					case ',':
						state = ZBX_STATE_NEW;
						if (1 == level)
							num++;
						break;
					case ']':
						level--;
						break;
					default:
						goto clean;
				}
				break;
			case ZBX_STATE_UNQUOTED:	/* an unquoted parameter */
				if (']' == (*data)[i] || ',' == (*data)[i])
				{
					replace_key_param(data, key_type, l, &i, level, num, 0, cb, cb_data);

					i--;
					state = ZBX_STATE_END;
				}
				break;
			case ZBX_STATE_QUOTED:	/* a quoted parameter */
				if ('"' == (*data)[i] && '\\' != (*data)[i - 1])
				{
					i++; replace_key_param(data, key_type, l, &i, level, num, 1, cb, cb_data); i--;

					state = ZBX_STATE_END;
				}
				break;
		}
	}
clean:
	if (0 == i || '\0' != (*data)[i] || 0 != level)
	{
		if (NULL != error)
		{
			zbx_snprintf(error, maxerrlen, "Invalid %s at position " ZBX_FS_SIZE_T,
					(ZBX_KEY_TYPE_ITEM == key_type ? "item key" : "SNMP OID"), (zbx_fs_size_t)i);
		}
		ret = FAIL;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: remove_param                                                     *
 *                                                                            *
 * Purpose: remove parameter by index (num) from parameter list (param)       *
 *                                                                            *
 * Parameters:                                                                *
 *      param  - parameter list                                               *
 *      num    - requested parameter index                                    *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Comments: delimiter for parameters is ','                                  *
 *                                                                            *
 ******************************************************************************/
void	remove_param(char *p, int num)
{
	int	state = 0;	/* 0 - unquoted parameter, 1 - quoted parameter */
	int	idx = 1;
	char	*buf;

	for (buf = p; '\0' != *p; p++)
	{
		switch (state)
		{
			case 0:			/* in unquoted parameter */
				if (',' == *p)
				{
					if (1 == idx && 1 == num)
						p++;
					idx++;
				}
				else if ('"' == *p)
					state = 1;
				break;
			case 1:			/* in quoted param */
				if ('"' == *p)
					state = 0;
				else if ('\\' == *p && '"' == p[1])
					p++;
				break;
		}
		if (idx != num)
			*buf++ = *p;
	}

	*buf = '\0';
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_num2hex                                                      *
 *                                                                            *
 * Purpose: convert parameter c (0-15) to hexadecimal value ('0'-'f')         *
 *                                                                            *
 * Parameters:                                                                *
 *      c - number 0-15                                                       *
 *                                                                            *
 * Return value:                                                              *
 *      '0'-'f'                                                               *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
char	zbx_num2hex(u_char c)
{
	if (c >= 10)
		return c + 0x57; /* a-f */
	else
		return c + 0x30; /* 0-9 */
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_hex2num                                                      *
 *                                                                            *
 * Purpose: convert hexit c ('0'-'9''a'-'f') to number (0-15)                 *
 *                                                                            *
 * Parameters:                                                                *
 *      c - char ('0'-'9''a'-'f')                                             *
 *                                                                            *
 * Return value:                                                              *
 *      0-15                                                                  *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
u_char	zbx_hex2num(char c)
{
	if (c >= 'a')
		return c - 0x57; /* a-f */
	else
		return c - 0x30; /* 0-9 */
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_binary2hex                                                   *
 *                                                                            *
 * Purpose: convert binary buffer input to hexadecimal string                 *
 *                                                                            *
 * Parameters:                                                                *
 *      input - binary data                                                   *
 *      ilen - binary data length                                             *
 *      output - pointer to output buffer                                     *
 *      olen - output buffer length                                           *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
size_t	zbx_binary2hex(const u_char *input, size_t ilen, char **output, size_t *olen)
{
	const u_char	*i = input;
	char		*o;
	size_t		len;

	assert(input);
	assert(output);
	assert(*output);
	assert(olen);

	len = 2 * ilen + 1;

	if (*olen < len)
	{
		*olen = len;
		*output = zbx_realloc(*output, *olen);
	}
	o = *output;

	while ((size_t)(i - input) < ilen)
	{
		*o++ = zbx_num2hex((*i >> 4) & 0xf);
		*o++ = zbx_num2hex(*i & 0xf);
		i++;
	}
	*o = '\0';

	return len - 1;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_hex2binary                                                   *
 *                                                                            *
 * Purpose: convert hexadecimal string to binary buffer                       *
 *                                                                            *
 * Parameters:                                                                *
 *      io - hexadecimal string                                               *
 *                                                                            *
 * Return value:                                                              *
 *      size of buffer                                                        *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
size_t	zbx_hex2binary(char *io)
{
	const char	*i = io;
	char		*o = io;
	u_char		c;

	assert(io);

	while(*i != '\0') {
		c = zbx_hex2num( *i++ ) << 4;
		c += zbx_hex2num( *i++ );
		*o++ = (char)c;
	}
	*o = '\0';

	return (int)(o - io);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_get_next_field                                               *
 *                                                                            *
 * Purpose: return current field of character separated string                *
 *                                                                            *
 * Parameters:                                                                *
 *      line - null terminated, character separated string                    *
 *      output - output buffer (current field)                                *
 *      olen - allocated output buffer size                                   *
 *      separator - fields separator                                          *
 *                                                                            *
 * Return value: pointer to the next field                                    *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
size_t	zbx_get_next_field(const char **line, char **output, size_t *olen, char separator)
{
	char	*ret;
	size_t	flen;

	assert(line);

	if (NULL == *line)
	{
		(*output)[0] = '\0';
		return 0;
	}

	if (NULL != (ret = strchr(*line, separator)))
	{
		flen = ret - *line;
		ret++;
	}
	else
		flen = strlen(*line);

	if (*olen < flen + 1)
	{
		*olen = flen * 2;
		*output = zbx_realloc(*output, *olen);
	}
	memcpy(*output, *line, flen);
	(*output)[flen] = '\0';

	*line = ret;

	return flen;
}

/******************************************************************************
 *                                                                            *
 * Function: str_in_list                                                      *
 *                                                                            *
 * Purpose: check if string is contained in a list of delimited strings       *
 *                                                                            *
 * Parameters: list      - strings a,b,ccc,ddd                                *
 *             value     - value                                              *
 *             delimiter - delimiter                                          *
 *                                                                            *
 * Return value: SUCCEED - string is in the list, FAIL - otherwise            *
 *                                                                            *
 * Author: Alexei Vladishev, Aleksandrs Saveljevs                             *
 *                                                                            *
 ******************************************************************************/
int	str_in_list(const char *list, const char *value, char delimiter)
{
	const char	*end;
	int		ret = FAIL;
	size_t		len;

	len = strlen(value);

	while (SUCCEED != ret)
	{
		if (NULL != (end = strchr(list, delimiter)))
		{
			ret = (len == end - list && 0 == strncmp(list, value, len) ? SUCCEED : FAIL);
			list = end + 1;
		}
		else
		{
			ret = (0 == strcmp(list, value) ? SUCCEED : FAIL);
			break;
		}
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: get_key_param                                                    *
 *                                                                            *
 * Purpose: return parameter by index (num) from parameter list (param)       *
 *          to be used for keys: key[param1,param2]                           *
 *                                                                            *
 * Parameters:                                                                *
 *      param   - parameter list                                              *
 *      num     - requested parameter index                                   *
 *      buf     - pointer of output buffer                                    *
 *      max_len - size of output buffer                                       *
 *                                                                            *
 * Return value:                                                              *
 *      1 - requested parameter missing                                       *
 *      0 - requested parameter found (value - 'buf' can be empty string)     *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:  delimeter for parameters is ','                                 *
 *                                                                            *
 ******************************************************************************/
int	get_key_param(char *param, int num, char *buf, size_t max_len)
{
	int	ret;
	char	*pl, *pr;

	pl = strchr(param, '[');
	pr = strrchr(param, ']');

	if (NULL == pl || NULL == pr || pl > pr)
		return 1;

	*pr = '\0';
	ret = get_param(pl + 1, num, buf, max_len);
	*pr = ']';

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: num_key_param                                                    *
 *                                                                            *
 * Purpose: calculate count of parameters from parameter list (param)         *
 *          to be used for keys: key[param1,param2]                           *
 *                                                                            *
 * Parameters:                                                                *
 *      param  - parameter list                                               *
 *                                                                            *
 * Return value: count of parameters                                          *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:  delimeter for parameters is ','                                 *
 *                                                                            *
 ******************************************************************************/
int	num_key_param(char *param)
{
	int	ret;
	char	*pl, *pr;

	if (NULL == param)
		return 0;

	pl = strchr(param, '[');
	pr = strrchr(param, ']');

	if (NULL == pl || NULL == pr || pl > pr)
		return 0;

	*pr = '\0';
	ret = num_param(pl + 1);
	*pr = ']';

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_get_escape_string_len                                        *
 *                                                                            *
 * Purpose: calculate the required size for the escaped string                *
 *                                                                            *
 * Parameters: src - [IN] null terminated source string                       *
 *             charlist - [IN] null terminated to-be-escaped character list   *
 *                                                                            *
 * Return value: size of the escaped string                                   *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
size_t	zbx_get_escape_string_len(const char *src, const char *charlist)
{
	size_t	sz = 0;

	for (; '\0' != *src; src++, sz++)
	{
		if (NULL != strchr(charlist, *src))
			sz++;
	}

	return sz;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dyn_escape_string                                            *
 *                                                                            *
 * Purpose: escape characters in the source string                            *
 *                                                                            *
 * Parameters: src - [IN] null terminated source string                       *
 *             charlist - [IN] null terminated to-be-escaped character list   *
 *                                                                            *
 * Return value: the escaped string                                           *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
char	*zbx_dyn_escape_string(const char *src, const char *charlist)
{
	size_t	sz;
	char	*d, *dst = NULL;

	sz = zbx_get_escape_string_len(src, charlist) + 1;

	dst = zbx_malloc(dst, sz);

	for (d = dst; '\0' != *src; src++)
	{
		if (NULL != strchr(charlist, *src))
			*d++ = '\\';

		*d++ = *src;
	}

	*d = '\0';

	return dst;
}

char	*zbx_age2str(int age)
{
	size_t		offset = 0;
	int		days, hours, minutes;
	static char	buffer[32];

	days = (int)((double)age / SEC_PER_DAY);
	hours = (int)((double)(age - days * SEC_PER_DAY) / SEC_PER_HOUR);
	minutes	= (int)((double)(age - days * SEC_PER_DAY - hours * SEC_PER_HOUR) / SEC_PER_MIN);

	if (0 != days)
		offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "%dd ", days);
	if (0 != days || 0 != hours)
		offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "%dh ", hours);
	offset += zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "%dm", minutes);

	return buffer;
}

char	*zbx_date2str(time_t date)
{
	static char	buffer[11];
	struct tm	*tm;

	tm = localtime(&date);
	zbx_snprintf(buffer, sizeof(buffer), "%.4d.%.2d.%.2d",
			tm->tm_year + 1900,
			tm->tm_mon + 1,
			tm->tm_mday);

	return buffer;
}

char	*zbx_time2str(time_t time)
{
	static char	buffer[9];
	struct tm	*tm;

	tm = localtime(&time);
	zbx_snprintf(buffer, sizeof(buffer), "%.2d:%.2d:%.2d",
			tm->tm_hour,
			tm->tm_min,
			tm->tm_sec);
	return buffer;
}

static int	zbx_strncasecmp(const char *s1, const char *s2, size_t n)
{
	if (NULL == s1 && NULL == s2)
		return 0;

	if (NULL == s1)
		return 1;

	if (NULL == s2)
		return -1;

	while (0 != n && '\0' != *s1 && '\0' != *s2 &&
			tolower((unsigned char)*s1) == tolower((unsigned char)*s2))
	{
		s1++;
		s2++;
		n--;
	}

	return 0 == n ? 0 : tolower((unsigned char)*s1) - tolower((unsigned char)*s2);
}

char	*zbx_strcasestr(const char *haystack, const char *needle)
{
	size_t		sz_h, sz_n;
	const char	*p;

	if (NULL == needle || '\0' == *needle)
		return (char *)haystack;

	if (NULL == haystack || '\0' == *haystack)
		return NULL;

	sz_h = strlen(haystack);
	sz_n = strlen(needle);
	if (sz_h < sz_n)
		return NULL;

	for (p = haystack; p <= &haystack[sz_h - sz_n]; p++)
	{
		if (0 == zbx_strncasecmp(p, needle, sz_n))
			return (char *)p;
	}

	return NULL;
}

int	zbx_mismatch(const char *s1, const char *s2)
{
	int	i = 0;

	while (s1[i] == s2[i])
	{
		if ('\0' == s1[i++])
			return FAIL;
	}

	return i;
}

int	starts_with(const char *str, const char *prefix)
{
	const char	*p, *q;

	for (p = str, q = prefix; *p == *q && *q != '\0'; p++, q++);

	return (*q == '\0' ? SUCCEED : FAIL);
}

int	cmp_key_id(const char *key_1, const char *key_2)
{
	const char	*p, *q;

	for (p = key_1, q = key_2; *p == *q && '\0' != *q && '[' != *q; p++, q++)
		;

	return ('\0' == *p || '[' == *p) && ('\0' == *q || '[' == *q) ? SUCCEED : FAIL;
}

const char	*zbx_permission_string(int perm)
{
	switch (perm)
	{
		case PERM_DENY:
			return "dn";
		case PERM_READ:
			return "r";
		case PERM_READ_WRITE:
			return "rw";
		default:
			return "unknown";
	}
}

const char	*zbx_agent_type_string(zbx_item_type_t item_type)
{
	switch (item_type)
	{
		case ITEM_TYPE_ZABBIX:
			return "Zabbix agent";
		case ITEM_TYPE_SNMPv1:
		case ITEM_TYPE_SNMPv2c:
		case ITEM_TYPE_SNMPv3:
			return "SNMP agent";
		case ITEM_TYPE_IPMI:
			return "IPMI agent";
		case ITEM_TYPE_JMX:
			return "JMX agent";
		default:
			return "generic";
	}
}

const char	*zbx_item_value_type_string(zbx_item_value_type_t value_type)
{
	switch (value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			return "Numeric (float)";
		case ITEM_VALUE_TYPE_STR:
			return "Character";
		case ITEM_VALUE_TYPE_LOG:
			return "Log";
		case ITEM_VALUE_TYPE_UINT64:
			return "Numeric (unsigned)";
		case ITEM_VALUE_TYPE_TEXT:
			return "Text";
		default:
			return "unknown";
	}
}

const char	*zbx_item_data_type_string(zbx_item_data_type_t data_type)
{
	switch (data_type)
	{
		case ITEM_DATA_TYPE_DECIMAL:
			return "Decimal";
		case ITEM_DATA_TYPE_OCTAL:
			return "Octal";
		case ITEM_DATA_TYPE_HEXADECIMAL:
			return "Hexadecimal";
		case ITEM_DATA_TYPE_BOOLEAN:
			return "Boolean";
		default:
			return "unknown";
	}
}

const char	*zbx_interface_type_string(zbx_interface_type_t type)
{
	switch (type)
	{
		case INTERFACE_TYPE_AGENT:
			return "Zabbix agent";
		case INTERFACE_TYPE_SNMP:
			return "SNMP";
		case INTERFACE_TYPE_IPMI:
			return "IPMI";
		case INTERFACE_TYPE_JMX:
			return "JMX";
		case INTERFACE_TYPE_ANY:
			return "any";
		case INTERFACE_TYPE_UNKNOWN:
		default:
			return "unknown";
	}
}

const int	INTERFACE_TYPE_PRIORITY[INTERFACE_TYPE_COUNT] =
{
	INTERFACE_TYPE_AGENT,
	INTERFACE_TYPE_SNMP,
	INTERFACE_TYPE_JMX,
	INTERFACE_TYPE_IPMI
};

const char	*zbx_result_string(int result)
{
	switch (result)
	{
		case SUCCEED:
			return "SUCCEED";
		case FAIL:
			return "FAIL";
		case CONFIG_ERROR:
			return "CONFIG_ERROR";
		case NOTSUPPORTED:
			return "NOTSUPPORTED";
		case NETWORK_ERROR:
			return "NETWORK_ERROR";
		case TIMEOUT_ERROR:
			return "TIMEOUT_ERROR";
		case AGENT_ERROR:
			return "AGENT_ERROR";
		case GATEWAY_ERROR:
			return "GATEWAY_ERROR";
		default:
			return "unknown";
	}
}

const char	*zbx_item_logtype_string(unsigned char logtype)
{
	switch (logtype)
	{
		case ITEM_LOGTYPE_INFORMATION:
			return "Information";
		case ITEM_LOGTYPE_WARNING:
			return "Warning";
		case ITEM_LOGTYPE_ERROR:
			return "Error";
		case ITEM_LOGTYPE_FAILURE_AUDIT:
			return "Failure Audit";
		case ITEM_LOGTYPE_SUCCESS_AUDIT:
			return "Success Audit";
		default:
			return "unknown";
	}
}

const char	*zbx_dservice_type_string(zbx_dservice_type_t service)
{
	switch (service)
	{
		case SVC_SSH:
			return "SSH";
		case SVC_LDAP:
			return "LDAP";
		case SVC_SMTP:
			return "SMTP";
		case SVC_FTP:
			return "FTP";
		case SVC_HTTP:
			return "HTTP";
		case SVC_POP:
			return "POP";
		case SVC_NNTP:
			return "NNTP";
		case SVC_IMAP:
			return "IMAP";
		case SVC_TCP:
			return "TCP";
		case SVC_AGENT:
			return "Zabbix agent";
		case SVC_SNMPv1:
			return "SNMPv1 agent";
		case SVC_SNMPv2c:
			return "SNMPv2c agent";
		case SVC_SNMPv3:
			return "SNMPv3 agent";
		case SVC_ICMPPING:
			return "ICMP ping";
		case SVC_HTTPS:
			return "HTTPS";
		case SVC_TELNET:
			return "Telnet";
		default:
			return "unknown";
	}
}

const char	*zbx_nodetype_string(unsigned char nodetype)
{
	switch (nodetype)
	{
		case ZBX_NODE_MASTER:
			return "master";
		case ZBX_NODE_SLAVE:
			return "slave";
		default:
			return "unknown";
	}
}

const char	*zbx_alert_type_string(unsigned char type)
{
	switch (type)
	{
		case ALERT_TYPE_MESSAGE:
			return "message";
		default:
			return "script";
	}
}

const char	*zbx_alert_status_string(unsigned char type, unsigned char status)
{
	switch (status)
	{
		case ALERT_STATUS_SENT:
			return (ALERT_TYPE_MESSAGE == type ? "sent" : "executed");
		case ALERT_STATUS_NOT_SENT:
			return "in progress";
		default:
			return "failed";
	}
}

const char	*zbx_escalation_status_string(unsigned char status)
{
	switch (status)
	{
		case ESCALATION_STATUS_ACTIVE:
			return "active";
		case ESCALATION_STATUS_RECOVERY:
			return "recovery";
		case ESCALATION_STATUS_SLEEP:
			return "sleep";
		case ESCALATION_STATUS_COMPLETED:
			return "completed";
		default:
			return "unknown";
	}
}

const char	*zbx_trigger_value_string(unsigned char value)
{
	switch (value)
	{
		case TRIGGER_VALUE_PROBLEM:
			return "PROBLEM";
		case TRIGGER_VALUE_OK:
			return "OK";
		default:
			return "unknown";
	}
}

const char	*zbx_trigger_state_string(unsigned char state)
{
	switch (state)
	{
		case TRIGGER_STATE_NORMAL:
			return "Normal";
		case TRIGGER_STATE_UNKNOWN:
			return "Unknown";
		default:
			return "unknown";
	}
}

const char	*zbx_item_state_string(unsigned char state)
{
	switch (state)
	{
		case ITEM_STATE_NORMAL:
			return "Normal";
		case ITEM_STATE_NOTSUPPORTED:
			return "Not supported";
		default:
			return "unknown";
	}
}

const char	*zbx_event_value_string(unsigned char source, unsigned char object, unsigned char value)
{
	if (EVENT_SOURCE_TRIGGERS == source)
		return zbx_trigger_value_string(value);

	if (EVENT_SOURCE_INTERNAL == source)
	{
		switch (object)
		{
			case EVENT_OBJECT_TRIGGER:
				return zbx_trigger_state_string(value);
			case EVENT_OBJECT_ITEM:
			case EVENT_OBJECT_LLDRULE:
				return zbx_item_state_string(value);
		}
	}

	return "unknown";
}

#ifdef _WINDOWS
static int	get_codepage(const char *encoding, unsigned int *codepage)
{
	typedef struct
	{
		unsigned int	codepage;
		const char	*name;
	}
	codepage_t;

	int		i;
	char		buf[16];
	codepage_t	cp[] = {{0, "ANSI"}, {37, "IBM037"}, {437, "IBM437"}, {500, "IBM500"}, {708, "ASMO-708"},
			{709, NULL}, {710, NULL}, {720, "DOS-720"}, {737, "IBM737"}, {775, "IBM775"}, {850, "IBM850"},
			{852, "IBM852"}, {855, "IBM855"}, {857, "IBM857"}, {858, "IBM00858"}, {860, "IBM860"},
			{861, "IBM861"}, {862, "DOS-862"}, {863, "IBM863"}, {864, "IBM864"}, {865, "IBM865"},
			{866, "CP866"}, {869, "IBM869"}, {870, "IBM870"}, {874, "WINDOWS-874"}, {875, "CP875"},
			{932, "SHIFT_JIS"}, {936, "GB2312"}, {949, "KS_C_5601-1987"}, {950, "BIG5"}, {1026, "IBM1026"},
			{1047, "IBM01047"}, {1140, "IBM01140"}, {1141, "IBM01141"}, {1142, "IBM01142"},
			{1143, "IBM01143"}, {1144, "IBM01144"}, {1145, "IBM01145"}, {1146, "IBM01146"},
			{1147, "IBM01147"}, {1148, "IBM01148"}, {1149, "IBM01149"}, {1200, "UTF-16"},
			{1201, "UNICODEFFFE"}, {1250, "WINDOWS-1250"}, {1251, "WINDOWS-1251"}, {1252, "WINDOWS-1252"},
			{1253, "WINDOWS-1253"}, {1254, "WINDOWS-1254"}, {1255, "WINDOWS-1255"}, {1256, "WINDOWS-1256"},
			{1257, "WINDOWS-1257"}, {1258, "WINDOWS-1258"}, {1361, "JOHAB"}, {10000, "MACINTOSH"},
			{10001, "X-MAC-JAPANESE"}, {10002, "X-MAC-CHINESETRAD"}, {10003, "X-MAC-KOREAN"},
			{10004, "X-MAC-ARABIC"}, {10005, "X-MAC-HEBREW"}, {10006, "X-MAC-GREEK"},
			{10007, "X-MAC-CYRILLIC"}, {10008, "X-MAC-CHINESESIMP"}, {10010, "X-MAC-ROMANIAN"},
			{10017, "X-MAC-UKRAINIAN"}, {10021, "X-MAC-THAI"}, {10029, "X-MAC-CE"},
			{10079, "X-MAC-ICELANDIC"}, {10081, "X-MAC-TURKISH"}, {10082, "X-MAC-CROATIAN"},
			{12000, "UTF-32"}, {12001, "UTF-32BE"}, {20000, "X-CHINESE_CNS"}, {20001, "X-CP20001"},
			{20002, "X_CHINESE-ETEN"}, {20003, "X-CP20003"}, {20004, "X-CP20004"}, {20005, "X-CP20005"},
			{20105, "X-IA5"}, {20106, "X-IA5-GERMAN"}, {20107, "X-IA5-SWEDISH"}, {20108, "X-IA5-NORWEGIAN"},
			{20127, "US-ASCII"}, {20261, "X-CP20261"}, {20269, "X-CP20269"}, {20273, "IBM273"},
			{20277, "IBM277"}, {20278, "IBM278"}, {20280, "IBM280"}, {20284, "IBM284"}, {20285, "IBM285"},
			{20290, "IBM290"}, {20297, "IBM297"}, {20420, "IBM420"}, {20423, "IBM423"}, {20424, "IBM424"},
			{20833, "X-EBCDIC-KOREANEXTENDED"}, {20838, "IBM-THAI"}, {20866, "KOI8-R"}, {20871, "IBM871"},
			{20880, "IBM880"}, {20905, "IBM905"}, {20924, "IBM00924"}, {20932, "EUC-JP"},
			{20936, "X-CP20936"}, {20949, "X-CP20949"}, {21025, "CP1025"}, {21027, NULL}, {21866, "KOI8-U"},
			{28591, "ISO-8859-1"}, {28592, "ISO-8859-2"}, {28593, "ISO-8859-3"}, {28594, "ISO-8859-4"},
			{28595, "ISO-8859-5"}, {28596, "ISO-8859-6"}, {28597, "ISO-8859-7"}, {28598, "ISO-8859-8"},
			{28599, "ISO-8859-9"}, {28603, "ISO-8859-13"}, {28605, "ISO-8859-15"}, {29001, "X-EUROPA"},
			{38598, "ISO-8859-8-I"}, {50220, "ISO-2022-JP"}, {50221, "CSISO2022JP"}, {50222, "ISO-2022-JP"},
			{50225, "ISO-2022-KR"}, {50227, "X-CP50227"}, {50229, NULL}, {50930, NULL}, {50931, NULL},
			{50933, NULL}, {50935, NULL}, {50936, NULL}, {50937, NULL}, {50939, NULL}, {51932, "EUC-JP"},
			{51936, "EUC-CN"}, {51949, "EUC-KR"}, {51950, NULL}, {52936, "HZ-GB-2312"}, {54936, "GB18030"},
			{57002, "X-ISCII-DE"}, {57003, "X-ISCII-BE"}, {57004, "X-ISCII-TA"}, {57005, "X-ISCII-TE"},
			{57006, "X-ISCII-AS"}, {57007, "X-ISCII-OR"}, {57008, "X-ISCII-KA"}, {57009, "X-ISCII-MA"},
			{57010, "X-ISCII-GU"}, {57011, "X-ISCII-PA"}, {65000, "UTF-7"}, {65001, "UTF-8"}, {0, NULL}};

	if ('\0' == *encoding)
	{
		*codepage = 0;	/* ANSI */
		return SUCCEED;
	}

	/* by name */
	for (i = 0; 0 != cp[i].codepage || NULL != cp[i].name; i++)
	{
		if (NULL == cp[i].name)
			continue;

		if (0 == strcmp(encoding, cp[i].name))
		{
			*codepage = cp[i].codepage;
			return SUCCEED;
		}
	}

	/* by number */
	for (i = 0; 0 != cp[i].codepage || NULL != cp[i].name; i++)
	{
		_itoa_s(cp[i].codepage, buf, sizeof(buf), 10);
		if (0 == strcmp(encoding, buf))
		{
			*codepage = cp[i].codepage;
			return SUCCEED;
		}
	}

	/* by 'cp' + number */
	for (i = 0; 0 != cp[i].codepage || NULL != cp[i].name; i++)
	{
		zbx_snprintf(buf, sizeof(buf), "cp%li", cp[i].codepage);
		if (0 == strcmp(encoding, buf))
		{
			*codepage = cp[i].codepage;
			return SUCCEED;
		}
	}

	return FAIL;
}

/* convert from selected code page to unicode */
static LPTSTR	zbx_to_unicode(unsigned int codepage, LPCSTR cp_string)
{
	LPTSTR	wide_string = NULL;
	int	wide_size;

	wide_size = MultiByteToWideChar(codepage, 0, cp_string, -1, NULL, 0);
	wide_string = (LPTSTR)zbx_malloc(wide_string, (size_t)wide_size * sizeof(TCHAR));

	/* convert from cp_string to wide_string */
	MultiByteToWideChar(codepage, 0, cp_string, -1, wide_string, wide_size);

	return wide_string;
}

/* convert from Windows ANSI code page to unicode */
LPTSTR	zbx_acp_to_unicode(LPCSTR acp_string)
{
	return zbx_to_unicode(CP_ACP, acp_string);
}

/* convert from Windows OEM code page to unicode */
LPTSTR	zbx_oemcp_to_unicode(LPCSTR oemcp_string)
{
	return zbx_to_unicode(CP_OEMCP, oemcp_string);
}

int	zbx_acp_to_unicode_static(LPCSTR acp_string, LPTSTR wide_string, int wide_size)
{
	/* convert from acp_string to wide_string */
	if (0 == MultiByteToWideChar(CP_ACP, 0, acp_string, -1, wide_string, wide_size))
		return FAIL;

	return SUCCEED;
}

/* convert from UTF-8 to unicode */
LPTSTR	zbx_utf8_to_unicode(LPCSTR utf8_string)
{
	return zbx_to_unicode(CP_UTF8, utf8_string);
}

/* convert from unicode to utf8 */
LPSTR	zbx_unicode_to_utf8(LPCTSTR wide_string)
{
	LPSTR	utf8_string = NULL;
	int	utf8_size;

	utf8_size = WideCharToMultiByte(CP_UTF8, 0, wide_string, -1, NULL, 0, NULL, NULL);
	utf8_string = (LPSTR)zbx_malloc(utf8_string, (size_t)utf8_size);

	/* convert from wide_string to utf8_string */
	WideCharToMultiByte(CP_UTF8, 0, wide_string, -1, utf8_string, utf8_size, NULL, NULL);

	return utf8_string;
}

/* convert from unicode to utf8 */
LPSTR	zbx_unicode_to_utf8_static(LPCTSTR wide_string, LPSTR utf8_string, int utf8_size)
{
	/* convert from wide_string to utf8_string */
	if (0 == WideCharToMultiByte(CP_UTF8, 0, wide_string, -1, utf8_string, utf8_size, NULL, NULL))
		*utf8_string = '\0';

	return utf8_string;
}
#endif

void	zbx_strlower(char *str)
{
	for (; '\0' != *str; str++)
		*str = tolower(*str);
}

void	zbx_strupper(char *str)
{
	for (; '\0' != *str; str++)
		*str = toupper(*str);
}

#ifdef _WINDOWS
#include "log.h"
char	*convert_to_utf8(char *in, size_t in_size, const char *encoding)
{
#define STATIC_SIZE	1024
	wchar_t	wide_string_static[STATIC_SIZE], *wide_string = NULL;
	int		wide_size;
	char		*utf8_string = NULL;
	int		utf8_size;
	unsigned int	codepage;

	if ('\0' == *encoding || FAIL == get_codepage(encoding, &codepage))
	{
		utf8_size = (int)in_size + 1;
		utf8_string = zbx_malloc(utf8_string, utf8_size);
		memcpy(utf8_string, in, in_size);
		utf8_string[in_size] = '\0';
		return utf8_string;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "convert_to_utf8() in_size:%d encoding:'%s' codepage:%u", in_size, encoding, codepage);

	if (1200 != codepage)	/* UTF-16 */
	{
		wide_size = MultiByteToWideChar(codepage, 0, in, (int)in_size, NULL, 0);
		if (wide_size > STATIC_SIZE)
			wide_string = (LPTSTR)zbx_malloc(wide_string, (size_t)wide_size * sizeof(TCHAR));
		else
			wide_string = wide_string_static;

		/* convert from in to wide_string */
		MultiByteToWideChar(codepage, 0, in, (int)in_size, wide_string, wide_size);
	}
	else
	{
		wide_string = (wchar_t *)in;
		wide_size = (int)in_size / 2;
	}

	utf8_size = WideCharToMultiByte(CP_UTF8, 0, wide_string, wide_size, NULL, 0, NULL, NULL);
	utf8_string = (LPSTR)zbx_malloc(utf8_string, (size_t)utf8_size + 1/* '\0' */);

	/* convert from wide_string to utf8_string */
	WideCharToMultiByte(CP_UTF8, 0, wide_string, wide_size, utf8_string, utf8_size, NULL, NULL);
	utf8_string[utf8_size] = '\0';
	zabbix_log(LOG_LEVEL_DEBUG, "convert_to_utf8() utf8_size:%d utf8_string:'%s'", utf8_size, utf8_string);

	if (wide_string != wide_string_static && wide_string != (wchar_t *)in)
		zbx_free(wide_string);

	return utf8_string;
}
#elif defined(HAVE_ICONV)
char	*convert_to_utf8(char *in, size_t in_size, const char *encoding)
{
	iconv_t		cd;
	size_t		in_size_left, out_size_left, sz, out_alloc = 0;
	const char	to_code[] = "UTF-8";
	char		*out = NULL, *p;

	out_alloc = in_size + 1;
	p = out = zbx_malloc(out, out_alloc);

	if ('\0' == *encoding || (iconv_t)-1 == (cd = iconv_open(to_code, encoding)))
	{
		memcpy(out, in, in_size);
		out[in_size] = '\0';
		return out;
	}

	in_size_left = in_size;
	out_size_left = out_alloc - 1;

	while ((size_t)(-1) == iconv(cd, &in, &in_size_left, &p, &out_size_left))
	{
		if (E2BIG != errno)
			break;

		sz = (size_t)(p - out);
		out_alloc += in_size;
		out_size_left += in_size;
		p = out = zbx_realloc(out, out_alloc);
		p += sz;
	}

	*p = '\0';

	iconv_close(cd);

	return out;
}
#endif	/* HAVE_ICONV */

size_t	zbx_strlen_utf8(const char *text)
{
	size_t	n = 0;

	while ('\0' != *text)
	{
		if (0x80 != (0xc0 & *text++))
			n++;
	}

	return n;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_utf8_char_len                                                *
 *                                                                            *
 * Purpose: Returns the size (in bytes) of an UTF-8 encoded character or 0    *
 *          if the character is not a valid UTF-8.                            *
 *                                                                            *
 * Parameters: text - [IN] pointer to the 1st byte of UTF-8 character         *
 *                                                                            *
 ******************************************************************************/
size_t	zbx_utf8_char_len(const char *text)
{
	if (0 == (*text & 0x80))		/* ASCII */
		return 1;
	else if (0xc0 == (*text & 0xe0))	/* 11000010-11011111 starts a 2-byte sequence */
		return 2;
	else if (0xe0 == (*text & 0xf0))	/* 11100000-11101111 starts a 3-byte sequence */
		return 3;
	else if (0xf0 == (*text & 0xf8))	/* 11110000-11110100 starts a 4-byte sequence */
		return 4;
	return 0;				/* not a valid UTF-8 character */
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_strlen_utf8_nchars                                           *
 *                                                                            *
 * Purpose: calculates number of bytes in utf8 text limited by utf8_maxlen    *
 *          characters                                                        *
 *                                                                            *
 ******************************************************************************/
size_t	zbx_strlen_utf8_nchars(const char *text, size_t utf8_maxlen)
{
	size_t		sz = 0, csz = 0;
	const char	*next;

	while ('\0' != *text && 0 < utf8_maxlen && 0 != (csz = zbx_utf8_char_len(text)))
	{
		next = text + csz;
		while (next > text)
		{
			if ('\0' == *text++)
				return sz;
		}
		sz += csz;
		utf8_maxlen--;
	}

	return sz;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_strlen_utf8_nbytes                                           *
 *                                                                            *
 * Purpose: calculates number of bytes in utf8 text limited by maxlen bytes   *
 *                                                                            *
 ******************************************************************************/
size_t	zbx_strlen_utf8_nbytes(const char *text, size_t maxlen)
{
	size_t	sz;

	sz = strlen(text);

	if (sz > maxlen)
	{
		sz = maxlen;

		/* ensure that the string is not cut in the middle of UTF-8 sequence */
		while (0x80 == (0xc0 & text[sz]) && 0 < sz)
			sz--;
	}

	return sz;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_replace_utf8                                                 *
 *                                                                            *
 * Purpose: replace non-ASCII UTF-8 characters with '?' character             *
 *                                                                            *
 * Parameters: text - [IN] pointer to the first char                          *
 *                                                                            *
 * Author: Aleksandrs Saveljevs                                               *
 *                                                                            *
 ******************************************************************************/
char	*zbx_replace_utf8(const char *text)
{
	int	n;
	char	*out, *p;

	out = p = zbx_malloc(NULL, strlen(text) + 1);

	while ('\0' != *text)
	{
		if (0 == (*text & 0x80))		/* ASCII */
			n = 1;
		else if (0xc0 == (*text & 0xe0))	/* 11000010-11011111 is a start of 2-byte sequence */
			n = 2;
		else if (0xe0 == (*text & 0xf0))	/* 11100000-11101111 is a start of 3-byte sequence */
			n = 3;
		else if (0xf0 == (*text & 0xf8))	/* 11110000-11110100 is a start of 4-byte sequence */
			n = 4;
		else
			goto bad;

		if (1 == n)
			*p++ = *text++;
		else
		{
			*p++ = ZBX_UTF8_REPLACE_CHAR;

			while (0 != n)
			{
				if ('\0' == *text)
					goto bad;
				n--;
				text++;
			}
		}
	}

	*p = '\0';
	return out;
bad:
	zbx_free(out);
	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_is_utf8                                                      *
 *                                                                            *
 * Purpose: check UTF-8 sequences                                             *
 *                                                                            *
 * Parameters: text - [IN] pointer to the string                              *
 *                                                                            *
 * Return value: SUCCEED if string is valid or FAIL otherwise                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_is_utf8(const char *text)
{
	unsigned int	utf32;
	unsigned char	*utf8;
	size_t		i, mb_len, expecting_bytes = 0;

	while ('\0' != *text)
	{
		/* single ASCII character */
		if (0 == (*text & 0x80))
		{
			text++;
			continue;
		}

		/* unexpected continuation byte or invalid UTF-8 bytes '\xfe' & '\xff' */
		if (0x80 == (*text & 0xc0) || 0xfe == (*text & 0xfe))
			return FAIL;

		/* multibyte sequence */

		utf8 = (unsigned char *)text;

		if (0xc0 == (*text & 0xe0))		/* 2-bytes multibyte sequence */
			expecting_bytes = 1;
		else if (0xe0 == (*text & 0xf0))	/* 3-bytes multibyte sequence */
			expecting_bytes = 2;
		else if (0xf0 == (*text & 0xf8))	/* 4-bytes multibyte sequence */
			expecting_bytes = 3;
		else if (0xf8 == (*text & 0xfc))	/* 5-bytes multibyte sequence */
			expecting_bytes = 4;
		else if (0xfc == (*text & 0xfe))	/* 6-bytes multibyte sequence */
			expecting_bytes = 5;

		mb_len = expecting_bytes + 1;
		text++;

		for (; 0 != expecting_bytes; expecting_bytes--)
		{
			/* not a continuation byte */
			if (0x80 != (*text++ & 0xc0))
				return FAIL;
		}

		/* overlong sequence */
		if (0xc0 == (utf8[0] & 0xfe) ||
				(0xe0 == utf8[0] && 0x00 == (utf8[1] & 0x20)) ||
				(0xf0 == utf8[0] && 0x00 == (utf8[1] & 0x30)) ||
				(0xf8 == utf8[0] && 0x00 == (utf8[1] & 0x38)) ||
				(0xfc == utf8[0] && 0x00 == (utf8[1] & 0x3c)))
		{
			return FAIL;
		}

		utf32 = 0;

		if (0xc0 == (utf8[0] & 0xe0))
			utf32 = utf8[0] & 0x1f;
		else if (0xe0 == (utf8[0] & 0xf0))
			utf32 = utf8[0] & 0x0f;
		else if (0xf0 == (utf8[0] & 0xf8))
			utf32 = utf8[0] & 0x07;
		else if (0xf8 == (utf8[0] & 0xfc))
			utf32 = utf8[0] & 0x03;
		else if (0xfc == (utf8[0] & 0xfe))
			utf32 = utf8[0] & 0x01;

		for (i = 1; i < mb_len; i++)
		{
			utf32 <<= 6;
			utf32 += utf8[i] & 0x3f;
		}

		/* according to the Unicode standard the high and low
		 * surrogate halves used by UTF-16 (U+D800 through U+DFFF)
		 * and values above U+10FFFF are not legal
		 */
		if (utf32 > 0x10ffff || 0xd800 == (utf32 & 0xf800))
			return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_replace_invalid_utf8                                         *
 *                                                                            *
 * Purpose: replace invalid UTF-8 sequences of bytes with '?' character       *
 *                                                                            *
 * Parameters: text - [IN/OUT] pointer to the first char                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_replace_invalid_utf8(char *text)
{
	char	*out = text;

	while ('\0' != *text)
	{
		if (0 == (*text & 0x80))			/* single ASCII character */
			*out++ = *text++;
		else if (0x80 == (*text & 0xc0) ||		/* unexpected continuation byte */
				0xfe == (*text & 0xfe))		/* invalid UTF-8 bytes '\xfe' & '\xff' */
		{
			*out++ = ZBX_UTF8_REPLACE_CHAR;
			text++;
		}
		else						/* multibyte sequence */
		{
			unsigned int	utf32;
			unsigned char	*utf8 = (unsigned char *)out;
			size_t		i, mb_len, expecting_bytes = 0;
			int		ret = SUCCEED;

			if (0xc0 == (*text & 0xe0))		/* 2-bytes multibyte sequence */
				expecting_bytes = 1;
			else if (0xe0 == (*text & 0xf0))	/* 3-bytes multibyte sequence */
				expecting_bytes = 2;
			else if (0xf0 == (*text & 0xf8))	/* 4-bytes multibyte sequence */
				expecting_bytes = 3;
			else if (0xf8 == (*text & 0xfc))	/* 5-bytes multibyte sequence */
				expecting_bytes = 4;
			else if (0xfc == (*text & 0xfe))	/* 6-bytes multibyte sequence */
				expecting_bytes = 5;

			*out++ = *text++;

			for (; 0 != expecting_bytes; expecting_bytes--)
			{
				if (0x80 != (*text & 0xc0))	/* not a continuation byte */
				{
					ret = FAIL;
					break;
				}

				*out++ = *text++;
			}

			mb_len = out - (char *)utf8;

			if (SUCCEED == ret)
			{
				if (0xc0 == (utf8[0] & 0xfe) ||	/* overlong sequence */
						(0xe0 == utf8[0] && 0x00 == (utf8[1] & 0x20)) ||
						(0xf0 == utf8[0] && 0x00 == (utf8[1] & 0x30)) ||
						(0xf8 == utf8[0] && 0x00 == (utf8[1] & 0x38)) ||
						(0xfc == utf8[0] && 0x00 == (utf8[1] & 0x3c)))
				{
					ret = FAIL;
				}
			}

			if (SUCCEED == ret)
			{
				utf32 = 0;

				if (0xc0 == (utf8[0] & 0xe0))
					utf32 = utf8[0] & 0x1f;
				else if (0xe0 == (utf8[0] & 0xf0))
					utf32 = utf8[0] & 0x0f;
				else if (0xf0 == (utf8[0] & 0xf8))
					utf32 = utf8[0] & 0x07;
				else if (0xf8 == (utf8[0] & 0xfc))
					utf32 = utf8[0] & 0x03;
				else if (0xfc == (utf8[0] & 0xfe))
					utf32 = utf8[0] & 0x01;

				for (i = 1; i < mb_len; i++)
				{
					utf32 <<= 6;
					utf32 += utf8[i] & 0x3f;
				}

				/* according to the Unicode standard the high and low
				 * surrogate halves used by UTF-16 (U+D800 through U+DFFF)
				 * and values above U+10FFFF are not legal
				 */
				if (utf32 > 0x10ffff || 0xd800 == (utf32 & 0xf800))
					ret = FAIL;
			}

			if (SUCCEED != ret)
			{
				out -= mb_len;
				*out++ = ZBX_UTF8_REPLACE_CHAR;
			}
		}
	}

	*out = '\0';
}

void	dos2unix(char *str)
{
	char	*o = str;

	while ('\0' != *str)
	{
		if ('\r' == str[0] && '\n' == str[1])	/* CR+LF (Windows) */
			str++;
		*o++ = *str++;
	}
	*o = '\0';
}

int	is_ascii_string(const char *str)
{
	while ('\0' != *str)
	{
		if (0 != ((1 << 7) & *str))	/* check for range 0..127 */
			return FAIL;

		str++;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: str_linefeed                                                     *
 *                                                                            *
 * Purpose: wrap long string at specified position with linefeeds             *
 *                                                                            *
 * Parameters: src     - input string                                         *
 *             maxline - maximum length of a line                             *
 *             delim   - delimiter to use as linefeed (default "\n" if NULL)  *
 *                                                                            *
 * Return value: newly allocated copy of input string with linefeeds          *
 *                                                                            *
 * Author: Vladimir Levijev                                                   *
 *                                                                            *
 * Comments: allocates memory                                                 *
 *                                                                            *
 ******************************************************************************/
char	*str_linefeed(const char *src, size_t maxline, const char *delim)
{
	size_t		src_size, dst_size, delim_size, left;
	int		feeds;		/* number of feeds */
	char		*dst = NULL;	/* output with linefeeds */
	const char	*p_src;
	char		*p_dst;

	assert(NULL != src);
	assert(0 < maxline);

	/* default delimiter */
	if (NULL == delim)
		delim = "\n";

	src_size = strlen(src);
	delim_size = strlen(delim);

	/* make sure we don't feed the last line */
	feeds = (int)(src_size / maxline - (0 != src_size % maxline || 0 == src_size ? 0 : 1));

	left = src_size - feeds * maxline;
	dst_size = src_size + feeds * delim_size + 1;

	/* allocate memory for output */
	dst = zbx_malloc(dst, dst_size);

	p_src = src;
	p_dst = dst;

	/* copy chunks appending linefeeds */
	while (0 < feeds--)
	{
		memcpy(p_dst, p_src, maxline);
		p_src += maxline;
		p_dst += maxline;

		memcpy(p_dst, delim, delim_size);
		p_dst += delim_size;
	}

	if (0 < left)
	{
		/* copy what's left */
		memcpy(p_dst, p_src, left);
		p_dst += left;
	}

	*p_dst = '\0';

	return dst;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_strarr_init                                                  *
 *                                                                            *
 * Purpose: initialize dynamic string array                                   *
 *                                                                            *
 * Parameters: arr - a pointer to array of strings                            *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Vladimir Levijev                                                   *
 *                                                                            *
 * Comments: allocates memory, calls assert() if that fails                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_strarr_init(char ***arr)
{
	*arr = zbx_malloc(*arr, sizeof(char *));
	**arr = NULL;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_strarr_add                                                   *
 *                                                                            *
 * Purpose: add a string to dynamic string array                              *
 *                                                                            *
 * Parameters: arr - a pointer to array of strings                            *
 *             entry - string to add                                          *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Vladimir Levijev                                                   *
 *                                                                            *
 * Comments: allocates memory, calls assert() if that fails                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_strarr_add(char ***arr, const char *entry)
{
	int	i;

	assert(entry);

	for (i = 0; NULL != (*arr)[i]; i++)
		;

	*arr = zbx_realloc(*arr, sizeof(char *) * (i + 2));

	(*arr)[i] = zbx_strdup((*arr)[i], entry);
	(*arr)[++i] = NULL;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_strarr_free                                                  *
 *                                                                            *
 * Purpose: free dynamic string array memory                                  *
 *                                                                            *
 * Parameters: arr - array of strings                                         *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Vladimir Levijev                                                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_strarr_free(char **arr)
{
	char	**p;

	for (p = arr; NULL != *p; p++)
		zbx_free(*p);
	zbx_free(arr);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_replace_string                                               *
 *                                                                            *
 * Purpose: replace data block with 'value'                                   *
 *                                                                            *
 * Parameters: data  - [IN/OUT] pointer to the string                         *
 *             l     - [IN] left position of the block                        *
 *             r     - [IN/OUT] right position of the block                   *
 *             value - [IN] the string to replace the block with              *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	zbx_replace_string(char **data, size_t l, size_t *r, const char *value)
{
	size_t	sz_data, sz_block, sz_value;
	char	*src, *dst;

	sz_value = strlen(value);
	sz_block = *r - l + 1;

	if (sz_value != sz_block)
	{
		sz_data = *r + strlen(*data + *r);
		sz_data += sz_value - sz_block;

		if (sz_value > sz_block)
			*data = zbx_realloc(*data, sz_data + 1);

		src = *data + l + sz_block;
		dst = *data + l + sz_value;

		memmove(dst, src, sz_data - l - sz_value + 1);

		*r = l + sz_value - 1;
	}

	memcpy(&(*data)[l], value, sz_value);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_trim_str_list                                                *
 *                                                                            *
 * Purpose: remove whitespace surrounding a string list item delimiters       *
 *                                                                            *
 * Parameters: list      - the list (a string containing items separated by   *
 *                         delimiter)                                         *
 *             delimiter - the list delimiter                                 *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_trim_str_list(char *list, char delimiter)
{
	char	*whitespace = " \t";	/* NB! strchr(3): "terminating null byte is considered part of the string" */
	char	*out, *in;

	if (NULL == list || '\0' == *list)
		return;

	out = in = list;

	while ('\0' != *in)
	{
		/* trim leading spaces from list item */
		while ('\0' != *in && NULL != strchr(whitespace, *in))
			in++;

		/* copy list item */
		while (delimiter != *in && '\0' != *in)
			*out++ = *in++;

		/* trim trailing spaces from list item */
		if (out > list)
		{
			while (NULL != strchr(whitespace, *(--out)))
				;
			out++;
		}
		if (delimiter == *in)
			*out++ = *in++;
	}
	*out = '\0';
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_dyn_escape_shell_single_quote                                *
 *                                                                            *
 * Purpose: escape single quote in shell command arguments                    *
 *                                                                            *
 * Parameters: arg - [IN] the argument to escape                              *
 *                                                                            *
 * Return value: The escaped argument.                                        *
 *                                                                            *
 ******************************************************************************/
char	*zbx_dyn_escape_shell_single_quote(const char *arg)
{
	int		len = 1; /* include terminating zero character */
	const char	*pin;
	char		*arg_esc, *pout;

	for (pin = arg; '\0' != *pin; pin++)
	{
		if ('\'' == *pin)
			len += 3;
		len++;
	}

	pout = arg_esc = zbx_malloc(NULL, len);

	for (pin = arg; '\0' != *pin; pin++)
	{
		if ('\'' == *pin)
		{
			*pout++ = '\'';
			*pout++ = '\\';
			*pout++ = '\'';
			*pout++ = '\'';
		}
		else
			*pout++ = *pin;
	}

	*pout = '\0';

	return arg_esc;
}

/******************************************************************************
 *                                                                            *
 * Function: function_parse_name                                              *
 *                                                                            *
 * Purpose: parses function name                                              *
 *                                                                            *
 * Parameters: expr     - [IN] the function expression: func(p1, p2,...)      *
 *             length   - [OUT] the function name length                      *
 *             next_pos - [OUT] position of the next character after function *
 *                              name. For valid expressions it should be '('. *
 *                                                                            *
 * Return value: SUCCEED - the function name was successfully parsed          *
 *               FAIL    - failed to parse function name                      *
 *                                                                            *
 ******************************************************************************/
static int	function_parse_name(const char *expr, size_t *length, size_t *next_pos)
{
	const char	*ptr;
	int		ret = FAIL;

	for (ptr = expr; SUCCEED == is_function_char(*ptr); ptr++)
		;

	if (ptr != expr && '(' == *ptr)
	{
		*length = ptr - expr;
		ret = SUCCEED;
	}
	else
	{
		/* function name was not found */
		*length = 0;
	}

	*next_pos = ptr - expr;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: function_parse_quoted_param                                      *
 *                                                                            *
 * Purpose: parses quoted parameter                                           *
 *                                                                            *
 * Parameters: expr      - [IN] the function expression, starting with the    *
 *                             parameter to parse: "p1",p2 ...                *
 *             length    - [OUT] the parameter length including enclosing     *
 *                              quotes and excluding trailing whitespace      *
 *             sep_pos   - [OUT] the parameter separator character            *
 *                               (',' or ')') position.                       *
 *                                                                            *
 * Return value: SUCCEED - the parameter was successfully parsed              *
 *               FAIL    - failed to parse parameter                          *
 *                                                                            *
 ******************************************************************************/
static int	function_parse_quoted_param(const char *expr, size_t *length, size_t *sep_pos)
{
	const char	*ptr;

	for (ptr = expr + 1; '"' != *ptr; ptr++)
	{
		switch (*ptr)
		{
			case '\0':
				return FAIL;
			case '\\':
				if ('"' == ptr[1])
					ptr++;
				break;

		}
	}

	*length = ++ptr - expr;

	/* skip trailing whitespace to find the next parameter */
	while (' ' == *ptr)
		ptr++;

	switch (*ptr)
	{
		case ',':
		case ')':
			*sep_pos = ptr - expr;
			break;
		default:
			return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: function_parse_unquoted_param                                    *
 *                                                                            *
 * Purpose: parses unquoted parameter                                         *
 *                                                                            *
 * Parameters: expr      - [IN] the function expression, starting with the    *
 *                             parameter to parse: p1,p2 ...                  *
 *             length    - [OUT] the parameter length excluding the trailing  *
 *                              whitespace                                    *
 *             sep_pos   - [OUT] the parameter separator character            *
 *                               (',' or ')') position.                       *
 *                               0 if there are no more parameters to parse.  *
 *                                                                            *
 * Return value: SUCCEED - the parameter was successfully parsed              *
 *               FAIL    - failed to parse parameter                          *
 *                                                                            *
 ******************************************************************************/
static int	function_parse_unquoted_param(const char *expr, size_t *length, size_t *sep_pos)
{
	const char	*ptr;

	for (ptr = expr; ; ptr++)
	{
		switch (*ptr)
		{
			case '\0':
				return FAIL;
			case ',':
			case ')':
				*sep_pos = ptr - expr;
				goto out;
		}
	}
out:
	/* trim the trailing whitespace */
	while (ptr > expr && ' ' == *(ptr - 1) )
		ptr--;

	*length = ptr - expr;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: function_parse_param                                             *
 *                                                                            *
 * Purpose: parses function parameter                                         *
 *                                                                            *
 * Parameters: expr      - [IN] the function expression, starting with the    *
 *                             parameter separator: (p1,p2 ... or ,p2,p3 ...  *
 *             param_pos - [OUT] the parameter position, excluding leading    *
 *                               whitespace                                   *
 *             length    - [OUT] the parameter length excluding trailing      *
 *                               whitespace                                   *
 *             sep_pos   - [OUT] the parameter separator character            *
 *                               (',' or ')') position.                       *
 *                               0 if there are no more parameters to parse.  *
 *                                                                            *
 * Return value: SUCCEED - the parameter was successfully parsed              *
 *               FAIL    - failed to parse parameter                          *
 *                                                                            *
 ******************************************************************************/
static int	function_parse_param(const char *expr, size_t *param_pos, size_t *length, size_t *sep_pos)
{
	int		ret;
	const char	*ptr = expr;

	/* skip the leading whitespace */
	while (' ' == *(++ptr))
		;

	*param_pos = ptr - expr;

	/* parse the parameter */
	if ('"' == *ptr)
		ret = function_parse_quoted_param(ptr, length, sep_pos);
	else
		ret = function_parse_unquoted_param(ptr, length, sep_pos);

	/* adjust next_pos to be relative from expression, not parameter start */
	*sep_pos += *param_pos;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: function_unquote_param_dyn                                       *
 *                                                                            *
 * Purpose: unquotes function parameter                                       *
 *                                                                            *
 * Parameters: param - [IN] the parameter to unquote                          *
 *             len   - [IN] the parameter length                              *
 *                                                                            *
 * Return value: The unquoted parameter. This value must be freed by the      *
 *               caller.                                                      *
 *                                                                            *
 ******************************************************************************/
static char	*function_unquote_param_dyn(const char *param, size_t len)
{
	char	*out;

	out = (char *)zbx_malloc(NULL, len + 1);

	if (0 == len || '"' != *param)
	{
		/* unquoted parameter - simply copy it */
		memcpy(out, param, len);
		out[len] = '\0';
	}
	else
	{
		/* quoted parameter - remove enclosing " and replace \" with " */
		const char	*pin;
		char		*pout = out;

		for (pin = param + 1; (size_t)(pin - param) < len - 1; pin++)
		{
			if ('\\' == pin[0] && '"' == pin[1])
				pin++;

			*pout++ = *pin;
		}

		*pout = '\0';
	}

	return out;
}

/******************************************************************************
 *                                                                            *
 * Function: function_quoted_param_len                                        *
 *                                                                            *
 * Purpose: calculates length of the function parameter                       *
 *                                                                            *
 * Parameters: param  - [IN] the parameter                                    *
 *             quoted - [IN/OUT] 1 - the parameter must be quoted,            *
 *                               0 - otherwise                                *
 *                                                                            *
 * Return value: The length of quoted (if necessary) parameter. The quoting   *
 *               is applied either if it was initially forced with quoted     *
 *               parameter, or if it starts with ' ' or '"' character, or     *
 *               contains ',' or ')' characters.                              *
 *                                                                            *
 ******************************************************************************/
static int	function_quoted_param_len(const char *param, int *quoted)
{
	int	len = 0, quotes = 0;

	if (' ' == *param || '"' == *param)
		*quoted = 1;

	for(;'\0' != *param; param++)
	{
		switch (*param)
		{
			case '"':
				quotes++;
				break;
			case ',':
			case ')':
				*quoted = 1;
				break;
		}

		len++;
	}

	if (0 != quoted)
		len += 2 + quotes;

	return len;
}

/******************************************************************************
 *                                                                            *
 * Function: function_quote_param_dyn                                         *
 *                                                                            *
 * Purpose: quotes function parameter                                         *
 *                                                                            *
 * Parameters: param  - [IN] the parameter to unquote                         *
 *             quoted - [IN] 1 - the parameter must be quoted,                *
 *                           0 - the quoting is optional                      *
 *                                                                            *
 * Return value: The quoted parameter. This value must be freed by the caller.*
 *                                                                            *
 * Comments: The 'param' is quoted if either of the following is true:        *
 *           * the 'quoted' parameter is 1;                                   *
 *           * the 'param' starts with ' ' or '"' character;                  *
 *           * the 'param' contains ',' or ')' character.                     *
 *                                                                            *
 ******************************************************************************/
static char	*function_quote_param_dyn(const char *param, int quoted)
{
	int	len;
	char	*out;

	len = function_quoted_param_len(param, &quoted);

	out = zbx_malloc(NULL, len + 1);

	if (0 == quoted)
	{
		/* quoting is not required, simply copy the parameter */
		memcpy(out, param, len);
		out[len] = '\0';
	}
	else
	{
		/* quoting is required - apply the enclosing quotes and escape " with \" sequences */
		const char	*pin = param;
		char		*pout = out;

		*pout++ = '"';

		while ('\0' != *pin)
		{
			if ('"' == *pin)
				*pout++ = '\\';
			*pout++ = *pin++;
		}

		*pout++ = '"';
		*pout = '\0';
	}

	return out;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_function_clean                                               *
 *                                                                            *
 * Purpose: frees resources allocated by the function data                    *
 *                                                                            *
 * Parameters: func - [IN] the function data                                  *
 *                                                                            *
 ******************************************************************************/
void	zbx_function_clean(zbx_function_t *func)
{
	int	i;

	zbx_free(func->name);

	for (i = 0; i < func->nparam; i++)
		zbx_free(func->params[i]);

	zbx_free(func->params);
	func->nparam = 0;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_function_parse                                               *
 *                                                                            *
 * Purpose: parses expression into function data                              *
 *                                                                            *
 * Parameters: func   - [OUT] the function data                               *
 *             expr   - [IN] the expression to parse                          *
 *             length - [OUT] the length of parsed data.                      *
 *                                                                            *
 * Return value: SUCCEED - the expression was successfully parsed             *
 *               FAIL    - the expression does not start with a function      *
 *                                                                            *
 * Comments: If the function fails, the number of characters returned in      *
 *           length are guaranteed not to be a part of another function.      *
 *           So even if zbx_function_parse() failed the cursor can be safely  *
 *           moved by length characters.                                      *
 *                                                                            *
 ******************************************************************************/
int	zbx_function_parse(zbx_function_t *func, const char *expr, size_t *length)
{
	size_t		alloc = 0, offset = 0, param_pos, next_pos, len, next_func;
	const char	*ptr = expr;
	int		params_alloc = 8;

	memset(func, '\0', sizeof(zbx_function_t));

	/* FAIL if the expression doesn't start with a function name */
	if (FAIL == function_parse_name(ptr, &len, &next_pos))
	{
		*length = next_pos + 1;
		return FAIL;
	}

	next_func = next_pos;

	/* copy the function name from expression to the function data */
	zbx_strncpy_alloc(&func->name, &alloc, &offset, ptr, len);

	/* initial allocation for function data parameters */
	func->params = (char **)zbx_malloc(NULL, sizeof(char *) * params_alloc);

	/* parse and prepare (quote, escape, copy to function data) the function parameters */
	do
	{
		ptr += next_pos;

		if (SUCCEED != function_parse_param(ptr, &param_pos, &len, &next_pos))
		{
			zbx_function_clean(func);
			*length = next_func;
			return FAIL;
		}

		/* if the only parameter is empty - it's a function without parameters */
		if (0 == len && ')' == ptr[next_pos] && 0 == func->nparam)
			break;

		if (params_alloc == func->nparam)
		{
			params_alloc *= 2;
			func->params = (char **)zbx_realloc(func->params, sizeof(char *) * params_alloc);
		}

		func->params[func->nparam++] = function_unquote_param_dyn(ptr + param_pos, len);
	}
	while (')' != ptr[next_pos]);

	*length = ptr - expr + next_pos + 1;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_function_tostr                                               *
 *                                                                            *
 * Purpose: converts function data into string format based on the specified  *
 *          expression                                                        *
 *                                                                            *
 * Parameters: func     - [IN] the function data                              *
 *             expr     - [IN] the template expression                        *
 *             expr_len - [IN] the expression length                          *
 *             out      - [OUT] the function data in string format            *
 *                                                                            *
 * Return value: SUCCEED - the function was successfully converted            *
 *               FAIL    - failed to parse expression                         *
 *                                                                            *
 * Comments: The function data is converted by replacing function name and    *
 *           parameters with name and corresponding parameters from function  *
 *           data.                                                            *
 *                                                                            *
 ******************************************************************************/
int	zbx_function_tostr(const zbx_function_t *func, const char *expr, size_t expr_len, char **out)
{
	int		ret = FAIL, index = 0, quoted;
	size_t		right, len, offset, next_pos, param_pos, next_offset;
	char		*param;

	*out = zbx_malloc(NULL, expr_len + 1);
	memcpy(*out, expr, expr_len);
	(*out)[expr_len] = '\0';

	if (FAIL == function_parse_name(*out, &len, &next_pos))
		goto out;

	/* calculate offset from the last name character to the first parameter */
	offset = next_pos - len;
	right = len - 1;
	zbx_replace_string(out, 0, &right, func->name);

	/* recalculate first parameter position in the updated expression */
	next_pos = right + offset + 1;

	do
	{
		if (SUCCEED != function_parse_param(*out + next_pos, &param_pos, &len, &next_offset))
			goto out;

		/* the param_pos and next_offset values are relative offsets from next_pos, */
		/* convert to the absolute positions from the expression start              */
		param_pos += next_pos;
		next_pos += next_offset;

		/* if the only parameter is empty - it's a function without parameters */
		if (')' == (*out)[next_pos] && 0 == len &&  0 == func->nparam)
			break;

		/* calculate parameter last character position and the offset to the next parameter */
		right = param_pos + len - 1;
		offset = next_pos - right;

		quoted = ('"' == (*out)[param_pos] ? 1 : 0);

		param = function_quote_param_dyn(func->params[index++], quoted);

		zbx_replace_string(out, param_pos, &right, param);
		zbx_free(param);

		/* recalculate next parameter position in the updated expression */
		next_pos = right + offset;
	}
	while (')' != (*out)[next_pos]);

	ret = SUCCEED;
out:
	if (FAIL == ret)
		zbx_free(*out);

	return ret;
}
