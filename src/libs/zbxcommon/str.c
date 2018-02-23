/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
#include "module.h"

#ifdef HAVE_ICONV
#	include <iconv.h>
#endif

static const char	copyright_message[] =
	"Copyright (C) 2018 Zabbix SIA\n"
	"License GPLv2+: GNU GPL version 2 or later <http://gnu.org/licenses/gpl.html>.\n"
	"This is free software: you are free to change and redistribute it according to\n"
	"the license. There is NO WARRANTY, to the extent permitted by law.";

static const char	help_message_footer[] =
	"Report bugs to: <https://support.zabbix.com>\n"
	"Zabbix home page: <http://www.zabbix.com>\n"
	"Documentation: <https://www.zabbix.com/documentation>";

/******************************************************************************
 *                                                                            *
 * Function: version                                                          *
 *                                                                            *
 * Purpose: print version and compilation time of application on stdout       *
 *          by application request with parameter '-V'                        *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:  title_message - is global variable which must be initialized    *
 *                            in each zabbix application                      *
 *                                                                            *
 ******************************************************************************/
void	version(void)
{
	printf("%s (Zabbix) %s\n", title_message, ZABBIX_VERSION);
	printf("Revision %s %s, compilation time: %s %s\n\n", ZABBIX_REVISION, ZABBIX_REVDATE, __DATE__, __TIME__);
	puts(copyright_message);
}

/******************************************************************************
 *                                                                            *
 * Function: usage                                                            *
 *                                                                            *
 * Purpose: print application parameters on stdout with layout suitable for   *
 *          80-column terminal                                                *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:  usage_message - is global variable which must be initialized    *
 *                            in each zabbix application                      *
 *                                                                            *
 ******************************************************************************/
void	usage(void)
{
#define ZBX_MAXCOL	79
#define ZBX_SPACE1	"  "			/* left margin for the first line */
#define ZBX_SPACE2	"               "	/* left margin for subsequent lines */
	const char	**p = usage_message;

	if (NULL != *p)
		printf("usage:\n");

	while (NULL != *p)
	{
		size_t	pos;

		printf("%s%s", ZBX_SPACE1, progname);
		pos = ZBX_CONST_STRLEN(ZBX_SPACE1) + strlen(progname);

		while (NULL != *p)
		{
			size_t	len;

			len = strlen(*p);

			if (ZBX_MAXCOL > pos + len)
			{
				pos += len + 1;
				printf(" %s", *p);
			}
			else
			{
				pos = ZBX_CONST_STRLEN(ZBX_SPACE2) + len + 1;
				printf("\n%s %s", ZBX_SPACE2, *p);
			}

			p++;
		}

		printf("\n");
		p++;
	}
#undef ZBX_MAXCOL
#undef ZBX_SPACE1
#undef ZBX_SPACE2
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

	usage();
	printf("\n");

	while (NULL != *p)
		printf("%s\n", *p++);

	printf("\n");
	puts(help_message_footer);
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
		*str = (char *)zbx_malloc(*str, *alloc_len);
	}

	avail_len = *alloc_len - *offset;
	va_start(args, fmt);
	written_len = zbx_vsnprintf(*str + *offset, avail_len, fmt, args);
	va_end(args);

	if (written_len == avail_len - 1)
	{
		*alloc_len *= 2;
		*str = (char *)zbx_realloc(*str, *alloc_len);

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
		*str = (char *)zbx_malloc(*str, *alloc_len);
	}
	else if (*offset + n >= *alloc_len)
	{
		while (*offset + n >= *alloc_len)
			*alloc_len *= 2;
		*str = (char *)zbx_realloc(*str, *alloc_len);
	}

	while (0 != n && '\0' != *src)
	{
		(*str)[(*offset)++] = *src++;
		n--;
	}

	(*str)[*offset] = '\0';
}

void	zbx_str_memcpy_alloc(char **str, size_t *alloc_len, size_t *offset, const char *src, size_t n)
{
	if (NULL == *str)
	{
		*alloc_len = n + 1;
		*offset = 0;
		*str = (char *)zbx_malloc(*str, *alloc_len);
	}
	else if (*offset + n >= *alloc_len)
	{
		while (*offset + n >= *alloc_len)
			*alloc_len *= 2;
		*str = (char *)zbx_realloc(*str, *alloc_len);
	}

	memcpy(*str + *offset, src, n);
	*offset += n;
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

	if (0 == count)
		return zbx_strdup(NULL, str);

	diff = (long)strlen(sub_str2) - len;

        /* allocate new memory */
	new_str = (char *)zbx_malloc(new_str, (size_t)(strlen(str) + count*diff + 1)*sizeof(char));

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
 * Function: zbx_strlcpy                                                      *
 *                                                                            *
 * Purpose: Copy src to string dst of size siz. At most siz - 1 characters    *
 *          will be copied. Always null terminates (unless siz == 0).         *
 *                                                                            *
 * Return value: the number of characters copied (excluding the null byte)    *
 *                                                                            *
 ******************************************************************************/
size_t	zbx_strlcpy(char *dst, const char *src, size_t siz)
{
	const char	*s = src;

	if (0 != siz)
	{
		while (0 != --siz && '\0' != *s)
			*dst++ = *s++;

		*dst = '\0';
	}

	return s - src;	/* count does not include null */
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_strlcat                                                      *
 *                                                                            *
 * Purpose: Appends src to string dst of size siz (unlike strncat, size is    *
 *          the full size of dst, not space left). At most siz - 1 characters *
 *          will be copied. Always null terminates (unless                    *
 *          siz <= strlen(dst)).                                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_strlcat(char *dst, const char *src, size_t siz)
{
	while ('\0' != *dst)
	{
		dst++;
		siz--;
	}

	zbx_strlcpy(dst, src, siz);
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
		string = (char *)zbx_malloc(string, size);

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

	dest = (char *)zbx_realloc(dest, len_dest + len_src + 1);

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
 *             error - pointer to the error message (can be NULL)             *
 *                                                                            *
 * Return value: return SUCCEED if hostname is valid                          *
 *               or FAIL if hostname contains invalid chars, is empty         *
 *               or is longer than MAX_ZBX_HOSTNAME_LEN                       *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
int	zbx_check_hostname(const char *hostname, char **error)
{
	int	len = 0;

	while ('\0' != hostname[len])
	{
		if (FAIL == is_hostname_char(hostname[len]))
		{
			if (NULL != error)
				*error = zbx_dsprintf(NULL, "name contains invalid character '%c'", hostname[len]);
			return FAIL;
		}

		len++;
	}

	if (0 == len)
	{
		if (NULL != error)
			*error = zbx_strdup(NULL, "name is empty");
		return FAIL;
	}

	if (MAX_ZBX_HOSTNAME_LEN < len)
	{
		if (NULL != error)
			*error = zbx_dsprintf(NULL, "name is too long (max %d characters)", MAX_ZBX_HOSTNAME_LEN);
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: parse_key                                                        *
 *                                                                            *
 * Purpose: advances pointer to first invalid character in string             *
 *          ensuring that everything before it is a valid key                 *
 *                                                                            *
 *  e.g., system.run[cat /etc/passwd | awk -F: '{ print $1 }']                *
 *                                                                            *
 * Parameters: exp - [IN/OUT] pointer to the first char of key                *
 *                                                                            *
 *  e.g., {host:system.run[cat /etc/passwd | awk -F: '{ print $1 }'].last(0)} *
 *              ^                                                             *
 * Return value: returns FAIL only if no key is present (length 0),           *
 *               or the whole string is invalid. SUCCEED otherwise.           *
 *                                                                            *
 * Author: Aleksandrs Saveljevs                                               *
 *                                                                            *
 ******************************************************************************/
int	parse_key(char **exp)
{
	char	*s;

	for (s = *exp; SUCCEED == is_key_char(*s); s++)
		;

	if (*exp == s)	/* the key is empty */
		return FAIL;

	if ('[' == *s)	/* for instance, net.tcp.port[,80] */
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
		s++;
	}

	*exp = s;
	return SUCCEED;
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
	int	array, idx = 1;
	size_t	buf_i = 0;

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
				else if ('\\' == *p && '"' == p[1])
				{
					if (idx == num && 0 != array)
						ZBX_ASSIGN_PARAM;

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
			else if ('\\' == *p && '"' == p[1])
			{
				if (idx == num && 0 != array)
					(*sz)++;

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

	buf = (char *)zbx_malloc(buf, sz + 1);

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
static int	replace_key_param(char **data, int key_type, size_t l, size_t *r, int level, int num, int quoted,
		replace_key_param_f cb, void *cb_data)
{
	char	c = (*data)[*r], *param = NULL;
	int	ret;

	(*data)[*r] = '\0';
	ret = cb(*data + l, key_type, level, num, quoted, cb_data, &param);
	(*data)[*r] = c;

	if (NULL != param)
	{
		(*r)--;
		zbx_replace_string(data, l, r, param);
		(*r)++;

		zbx_free(param);
	}

	return ret;
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

	ret = replace_key_param(data, key_type, 0, &i, level, num, 0, cb, cb_data);

	for (; '\0' != (*data)[i] && FAIL != ret; i++)
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
						ret = replace_key_param(data, key_type, i, &i, level, num, 0, cb,
								cb_data);
						if (1 == level)
							num++;
						break;
					case '[':
						level++;
						if (1 == level)
							num++;
						break;
					case ']':
						ret = replace_key_param(data, key_type, i, &i, level, num, 0, cb,
								cb_data);
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
					ret = replace_key_param(data, key_type, l, &i, level, num, 0, cb, cb_data);

					i--;
					state = ZBX_STATE_END;
				}
				break;
			case ZBX_STATE_QUOTED:	/* a quoted parameter */
				if ('"' == (*data)[i] && '\\' != (*data)[i - 1])
				{
					i++;
					ret = replace_key_param(data, key_type, l, &i, level, num, 1, cb, cb_data);
					i--;

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
void	remove_param(char *param, int num)
{
	int	state = 0;	/* 0 - unquoted parameter, 1 - quoted parameter */
	int	idx = 1, skip_char = 0;
	char	*p;

	for (p = param; '\0' != *p; p++)
	{
		switch (state)
		{
			case 0:			/* in unquoted parameter */
				if (',' == *p)
				{
					if (1 == idx && 1 == num)
						skip_char = 1;
					idx++;
				}
				else if ('"' == *p)
					state = 1;
				break;
			case 1:			/* in quoted parameter */
				if ('"' == *p && '\\' != *(p - 1))
					state = 0;
				break;
		}
		if (idx != num && 0 == skip_char)
			*param++ = *p;

		skip_char = 0;
	}

	*param = '\0';
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
			ret = (len == (size_t)(end - list) && 0 == strncmp(list, value, len) ? SUCCEED : FAIL);
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

	dst = (char *)zbx_malloc(dst, sz);

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

	zbx_snprintf(buffer + offset, sizeof(buffer) - offset, "%dm", minutes);

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

int	zbx_strncasecmp(const char *s1, const char *s2, size_t n)
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

int	cmp_key_id(const char *key_1, const char *key_2)
{
	const char	*p, *q;

	for (p = key_1, q = key_2; *p == *q && '\0' != *q && '[' != *q; p++, q++)
		;

	return ('\0' == *p || '[' == *p) && ('\0' == *q || '[' == *q) ? SUCCEED : FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: get_process_type_string                                          *
 *                                                                            *
 * Purpose: Returns process name                                              *
 *                                                                            *
 * Parameters: process_type - [IN] process type; ZBX_PROCESS_TYPE_*           *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: used in internals checks zabbix["process",...], process titles   *
 *           and log files                                                    *
 *                                                                            *
 ******************************************************************************/
const char	*get_process_type_string(unsigned char proc_type)
{
	switch (proc_type)
	{
		case ZBX_PROCESS_TYPE_POLLER:
			return "poller";
		case ZBX_PROCESS_TYPE_UNREACHABLE:
			return "unreachable poller";
		case ZBX_PROCESS_TYPE_IPMIPOLLER:
			return "ipmi poller";
		case ZBX_PROCESS_TYPE_PINGER:
			return "icmp pinger";
		case ZBX_PROCESS_TYPE_JAVAPOLLER:
			return "java poller";
		case ZBX_PROCESS_TYPE_HTTPPOLLER:
			return "http poller";
		case ZBX_PROCESS_TYPE_TRAPPER:
			return "trapper";
		case ZBX_PROCESS_TYPE_SNMPTRAPPER:
			return "snmp trapper";
		case ZBX_PROCESS_TYPE_PROXYPOLLER:
			return "proxy poller";
		case ZBX_PROCESS_TYPE_ESCALATOR:
			return "escalator";
		case ZBX_PROCESS_TYPE_HISTSYNCER:
			return "history syncer";
		case ZBX_PROCESS_TYPE_DISCOVERER:
			return "discoverer";
		case ZBX_PROCESS_TYPE_ALERTER:
			return "alerter";
		case ZBX_PROCESS_TYPE_TIMER:
			return "timer";
		case ZBX_PROCESS_TYPE_HOUSEKEEPER:
			return "housekeeper";
		case ZBX_PROCESS_TYPE_DATASENDER:
			return "data sender";
		case ZBX_PROCESS_TYPE_CONFSYNCER:
			return "configuration syncer";
		case ZBX_PROCESS_TYPE_HEARTBEAT:
			return "heartbeat sender";
		case ZBX_PROCESS_TYPE_SELFMON:
			return "self-monitoring";
		case ZBX_PROCESS_TYPE_VMWARE:
			return "vmware collector";
		case ZBX_PROCESS_TYPE_COLLECTOR:
			return "collector";
		case ZBX_PROCESS_TYPE_LISTENER:
			return "listener";
		case ZBX_PROCESS_TYPE_ACTIVE_CHECKS:
			return "active checks";
		case ZBX_PROCESS_TYPE_TASKMANAGER:
			return "task manager";
		case ZBX_PROCESS_TYPE_IPMIMANAGER:
			return "ipmi manager";
		case ZBX_PROCESS_TYPE_ALERTMANAGER:
			return "alert manager";
		case ZBX_PROCESS_TYPE_PREPROCMAN:
			return "preprocessing manager";
		case ZBX_PROCESS_TYPE_PREPROCESSOR:
			return "preprocessing worker";
	}

	THIS_SHOULD_NEVER_HAPPEN;
	exit(EXIT_FAILURE);
}

int	get_process_type_by_name(const char *proc_type_str)
{
	int	i;

	for (i = 0; i < ZBX_PROCESS_TYPE_COUNT; i++)
	{
		if (0 == strcmp(proc_type_str, get_process_type_string(i)))
			return i;
	}

	return ZBX_PROCESS_TYPE_UNKNOWN;
}

const char	*get_program_type_string(unsigned char program_type)
{
	switch (program_type)
	{
		case ZBX_PROGRAM_TYPE_SERVER:
			return "server";
		case ZBX_PROGRAM_TYPE_PROXY_ACTIVE:
		case ZBX_PROGRAM_TYPE_PROXY_PASSIVE:
			return "proxy";
		case ZBX_PROGRAM_TYPE_AGENTD:
			return "agent";
		case ZBX_PROGRAM_TYPE_SENDER:
			return "sender";
		case ZBX_PROGRAM_TYPE_GET:
			return "get";
		default:
			return "unknown";
	}
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

const char	*zbx_sysinfo_ret_string(int ret)
{
	switch (ret)
	{
		case SYSINFO_RET_OK:
			return "SYSINFO_SUCCEED";
		case SYSINFO_RET_FAIL:
			return "SYSINFO_FAIL";
		default:
			return "SYSINFO_UNKNOWN";
	}
}

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
		case ITEM_LOGTYPE_CRITICAL:
			return "Critical";
		case ITEM_LOGTYPE_VERBOSE:
			return "Verbose";
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
	{
		switch (value)
		{
			case EVENT_STATUS_PROBLEM:
				return "PROBLEM";
			case EVENT_STATUS_RESOLVED:
				return "RESOLVED";
			default:
				return "unknown";
		}
	}

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
static wchar_t	*zbx_to_unicode(unsigned int codepage, const char *cp_string)
{
	wchar_t	*wide_string = NULL;
	int	wide_size;

	wide_size = MultiByteToWideChar(codepage, 0, cp_string, -1, NULL, 0);
	wide_string = (wchar_t *)zbx_malloc(wide_string, (size_t)wide_size * sizeof(wchar_t));

	/* convert from cp_string to wide_string */
	MultiByteToWideChar(codepage, 0, cp_string, -1, wide_string, wide_size);

	return wide_string;
}

/* convert from Windows ANSI code page to unicode */
wchar_t	*zbx_acp_to_unicode(const char *acp_string)
{
	return zbx_to_unicode(CP_ACP, acp_string);
}

/* convert from Windows OEM code page to unicode */
wchar_t	*zbx_oemcp_to_unicode(const char *oemcp_string)
{
	return zbx_to_unicode(CP_OEMCP, oemcp_string);
}

int	zbx_acp_to_unicode_static(const char *acp_string, wchar_t *wide_string, int wide_size)
{
	/* convert from acp_string to wide_string */
	if (0 == MultiByteToWideChar(CP_ACP, 0, acp_string, -1, wide_string, wide_size))
		return FAIL;

	return SUCCEED;
}

/* convert from UTF-8 to unicode */
wchar_t	*zbx_utf8_to_unicode(const char *utf8_string)
{
	return zbx_to_unicode(CP_UTF8, utf8_string);
}

/* convert from unicode to utf8 */
char	*zbx_unicode_to_utf8(const wchar_t *wide_string)
{
	char	*utf8_string = NULL;
	int	utf8_size;

	utf8_size = WideCharToMultiByte(CP_UTF8, 0, wide_string, -1, NULL, 0, NULL, NULL);
	utf8_string = (char *)zbx_malloc(utf8_string, (size_t)utf8_size);

	/* convert from wide_string to utf8_string */
	WideCharToMultiByte(CP_UTF8, 0, wide_string, -1, utf8_string, utf8_size, NULL, NULL);

	return utf8_string;
}

/* convert from unicode to utf8 */
char	*zbx_unicode_to_utf8_static(const wchar_t *wide_string, char *utf8_string, int utf8_size)
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
	wchar_t		wide_string_static[STATIC_SIZE], *wide_string = NULL;
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

	zabbix_log(LOG_LEVEL_DEBUG, "convert_to_utf8() in_size:%d encoding:'%s' codepage:%u", in_size, encoding,
			codepage);

	if (1200 == codepage)		/* Unicode UTF-16, little-endian byte order */
	{
		wide_string = (wchar_t *)in;
		wide_size = (int)in_size / 2;
	}
	else if (1201 == codepage)	/* unicodeFFFE UTF-16, big-endian byte order */
	{
		wchar_t	*wide_string_be = (wchar_t *)in;
		int	i;

		wide_size = (int)in_size / 2;


		if (wide_size > STATIC_SIZE)
			wide_string = (wchar_t *)zbx_malloc(wide_string, (size_t)wide_size * sizeof(wchar_t));
		else
			wide_string = wide_string_static;

		/* convert from big-endian 'in' to little-endian 'wide_string' */
		for (i = 0; i < wide_size; i++)
			wide_string[i] = ((wide_string_be[i] << 8) & 0xff00) | ((wide_string_be[i] >> 8) & 0xff);
	}
	else
	{
		wide_size = MultiByteToWideChar(codepage, 0, in, (int)in_size, NULL, 0);

		if (wide_size > STATIC_SIZE)
			wide_string = (wchar_t *)zbx_malloc(wide_string, (size_t)wide_size * sizeof(wchar_t));
		else
			wide_string = wide_string_static;

		/* convert from 'in' to 'wide_string' */
		MultiByteToWideChar(codepage, 0, in, (int)in_size, wide_string, wide_size);
	}

	utf8_size = WideCharToMultiByte(CP_UTF8, 0, wide_string, wide_size, NULL, 0, NULL, NULL);
	utf8_string = (char *)zbx_malloc(utf8_string, (size_t)utf8_size + 1/* '\0' */);

	/* convert from 'wide_string' to 'utf8_string' */
	WideCharToMultiByte(CP_UTF8, 0, wide_string, wide_size, utf8_string, utf8_size, NULL, NULL);
	utf8_string[utf8_size] = '\0';

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
	p = out = (char *)zbx_malloc(out, out_alloc);

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
		p = out = (char *)zbx_realloc(out, out_alloc);
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

	out = p = (char *)zbx_malloc(NULL, strlen(text) + 1);

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
	dst = (char *)zbx_malloc(dst, dst_size);

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
	*arr = (char **)zbx_malloc(*arr, sizeof(char *));
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

	*arr = (char **)zbx_realloc(*arr, sizeof(char *) * (i + 2));

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
			*data = (char *)zbx_realloc(*data, sz_data + 1);

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
	/* NB! strchr(3): "terminating null byte is considered part of the string" */
	const char	*whitespace = " \t";
	char		*out, *in;

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
 * Function: zbx_strcmp_null                                                  *
 *                                                                            *
 * Purpose:                                                                   *
 *     compares two strings where any of them can be a NULL pointer           *
 *                                                                            *
 * Parameters: same as strcmp() except NULL values are allowed                *
 *                                                                            *
 * Return value: same as strcmp()                                             *
 *                                                                            *
 * Comments: NULL is less than any string                                     *
 *                                                                            *
 ******************************************************************************/
int	zbx_strcmp_null(const char *s1, const char *s2)
{
	if (NULL == s1)
		return NULL == s2 ? 0 : -1;

	if (NULL == s2)
		return 1;

	return strcmp(s1, s2);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_user_macro_parse                                             *
 *                                                                            *
 * Purpose:                                                                   *
 *     parses user macro and finds its end position and context location      *
 *                                                                            *
 * Parameters:                                                                *
 *     macro     - [IN] the macro to parse                                    *
 *     macro_r   - [OUT] the position of ending '}' character                 *
 *     context_l - [OUT] the position of context start character (first non   *
 *                       space character after context separator ':')         *
 *                       0 if macro does not have context specified.          *
 *     context_r - [OUT] the position of context end character (either the    *
 *                       ending '"' for quoted context values or the last     *
 *                       character before the ending '}' character)           *
 *                       0 if macro does not have context specified.          *
 *                                                                            *
 * Return value:                                                              *
 *     SUCCEED - the macro was parsed successfully.                           *
 *     FAIL    - the macro parsing failed, the content of output variables    *
 *               is not defined.                                              *
 *                                                                            *
 ******************************************************************************/
int	zbx_user_macro_parse(const char *macro, int *macro_r, int *context_l, int *context_r)
{
	int	i;

	/* find the end of macro name by skipping {$ characters and iterating through */
	/* valid macro name characters                                                */
	for (i = 2; SUCCEED == is_macro_char(macro[i]); i++)
		;

	/* check for empty macro name */
	if (2 == i)
		return FAIL;

	if ('}' == macro[i])
	{
		/* no macro context specified, parsing done */
		*macro_r = i;
		*context_l = 0;
		*context_r = 0;
		return SUCCEED;
	}

	/* fail if the next character is not a macro context separator */
	if  (':' != macro[i])
		return FAIL;

	/* skip the whitespace after macro context separator */
	while (' ' == macro[++i])
		;

	*context_l = i;

	if ('"' == macro[i])
	{
		i++;

		/* process quoted context */
		for (; '"' != macro[i]; i++)
		{
			if ('\0' == macro[i])
				return FAIL;

			if ('\\' == macro[i] && '"' == macro[i + 1])
				i++;
		}

		*context_r = i;

		while (' ' == macro[++i])
			;
	}
	else
	{
		/* process unquoted context */
		for (; '}' != macro[i]; i++)
		{
			if ('\0' == macro[i])
				return FAIL;
		}

		*context_r = i - 1;
	}

	if ('}' != macro[i])
		return FAIL;

	*macro_r = i;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_user_macro_parse_dyn                                         *
 *                                                                            *
 * Purpose:                                                                   *
 *     parses user macro {$MACRO:<context>} into {$MACRO} and <context>       *
 *     strings                                                                *
 *                                                                            *
 * Parameters:                                                                *
 *     macro   - [IN] the macro to parse                                      *
 *     name    - [OUT] the macro name without context                         *
 *     context - [OUT] the unquoted macro context, NULL for macros without    *
 *                     context                                                *
 *     length  - [OUT] the length of parsed macro (optional)                  *
 *                                                                            *
 * Return value:                                                              *
 *     SUCCEED - the macro was parsed successfully                            *
 *     FAIL    - the macro parsing failed, invalid parameter syntax           *
 *                                                                            *
 ******************************************************************************/
int	zbx_user_macro_parse_dyn(const char *macro, char **name, char **context, int *length)
{
	const char	*ptr;
	int		macro_r, context_l, context_r;
	size_t		len;

	if (SUCCEED != zbx_user_macro_parse(macro, &macro_r, &context_l, &context_r))
		return FAIL;

	zbx_free(*context);

	if (0 != context_l)
	{
		ptr = macro + context_l;

		/* find the context separator ':' by stripping spaces before context */
		while (' ' == *(--ptr))
			;

		/* extract the macro name and close with '}' character */
		len = ptr - macro + 1;
		*name = (char *)zbx_realloc(*name, len + 1);
		memcpy(*name, macro, len - 1);
		(*name)[len - 1] = '}';
		(*name)[len] = '\0';

		*context = zbx_user_macro_unquote_context_dyn(macro + context_l, context_r - context_l + 1);
	}
	else
	{
		*name = (char *)zbx_realloc(*name, macro_r + 2);
		zbx_strlcpy(*name, macro, macro_r + 2);
	}

	if (NULL != length)
		*length = macro_r + 1;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_user_macro_unquote_context_dyn                               *
 *                                                                            *
 * Purpose:                                                                   *
 *     extracts the macro context unquoting if necessary                      *
 *                                                                            *
 * Parameters:                                                                *
 *     context - [IN] the macro context inside a user macro                   *
 *     len     - [IN] the macro context length (including quotes for quoted   *
 *                    contexts)                                               *
 *                                                                            *
 * Return value:                                                              *
 *     A string containing extracted macro context. This string must be freed *
 *     by the caller.                                                         *
 *                                                                            *
 ******************************************************************************/
char	*zbx_user_macro_unquote_context_dyn(const char *context, int len)
{
	int	quoted = 0;
	char	*buffer, *ptr;

	ptr = buffer = (char *)zbx_malloc(NULL, len + 1);

	if ('"' == *context)
	{
		quoted = 1;
		context++;
		len--;
	}

	while (0 < len)
	{
		if (1 == quoted && '\\' == *context && '"' == context[1])
		{
			context++;
			len--;
		}

		*ptr++ = *context++;
		len--;
	}

	if (1 == quoted)
		ptr--;

	*ptr = '\0';

	return buffer;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_user_macro_quote_context_dyn                                 *
 *                                                                            *
 * Purpose:                                                                   *
 *     quotes user macro context if necessary                                 *
 *                                                                            *
 * Parameters:                                                                *
 *     context     - [IN] the macro context                                   *
 *     force_quote - [IN] if non zero then context quoting is enforced        *
 *                                                                            *
 * Return value:                                                              *
 *     A string containing quoted macro context. This string must be freed by *
 *     the caller.                                                            *
 *                                                                            *
 ******************************************************************************/
char	*zbx_user_macro_quote_context_dyn(const char *context, int force_quote)
{
	int		len, quotes = 0;
	char		*buffer, *ptr_buffer;
	const char	*ptr_context = context;

	if ('"' == *ptr_context || ' ' == *ptr_context)
		force_quote = 1;

	for (; '\0' != *ptr_context; ptr_context++)
	{
		if ('}' == *ptr_context)
			force_quote = 1;

		if ('"' == *ptr_context)
			quotes++;
	}

	if (0 == force_quote)
		return zbx_strdup(NULL, context);

	len = (int)strlen(context) + 2 + quotes;
	ptr_buffer = buffer = (char *)zbx_malloc(NULL, len + 1);

	*ptr_buffer++ = '"';

	while ('\0' != *context)
	{
		if ('"' == *context)
			*ptr_buffer++ = '\\';

		*ptr_buffer++ = *context++;
	}

	*ptr_buffer++ = '"';
	*ptr_buffer++ = '\0';

	return buffer;
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

	pout = arg_esc = (char *)zbx_malloc(NULL, len);

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
 *             length   - [OUT] the function name length or the amount of     *
 *                              characters that can be safely skipped         *
 *                                                                            *
 * Return value: SUCCEED - the function name was successfully parsed          *
 *               FAIL    - failed to parse function name                      *
 *                                                                            *
 ******************************************************************************/
static int	function_parse_name(const char *expr, size_t *length)
{
	const char	*ptr;

	for (ptr = expr; SUCCEED == is_function_char(*ptr); ptr++)
		;

	*length = ptr - expr;

	return ptr != expr && '(' == *ptr ? SUCCEED : FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_function_param_parse                                         *
 *                                                                            *
 * Purpose: parses function parameter                                         *
 *                                                                            *
 * Parameters: expr      - [IN] pre-validated function parameter list         *
 *             param_pos - [OUT] the parameter position, excluding leading    *
 *                               whitespace                                   *
 *             length    - [OUT] the parameter length including trailing      *
 *                               whitespace for unquoted parameter            *
 *             sep_pos   - [OUT] the parameter separator character            *
 *                               (',' or '\0' or ')') position                *
 *                                                                            *
 ******************************************************************************/
void	zbx_function_param_parse(const char *expr, size_t *param_pos, size_t *length, size_t *sep_pos)
{
	const char	*ptr = expr;

	/* skip the leading whitespace */
	while (' ' == *ptr)
		ptr++;

	*param_pos = ptr - expr;

	if ('"' == *ptr)	/* quoted parameter */
	{
		for (ptr++; '"' != *ptr || '\\' == *(ptr - 1); ptr++)
			;

		*length = ++ptr - expr - *param_pos;

		/* skip trailing whitespace to find the next parameter */
		while (' ' == *ptr)
			ptr++;
	}
	else	/* unquoted parameter */
	{
		for (ptr = expr; '\0' != *ptr && ')' != *ptr && ',' != *ptr; ptr++)
			;

		*length = ptr - expr - *param_pos;
	}

	*sep_pos = ptr - expr;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_function_param_unquote_dyn                                   *
 *                                                                            *
 * Purpose: unquotes function parameter                                       *
 *                                                                            *
 * Parameters: param -  [IN] the parameter to unquote                         *
 *             len   -  [IN] the parameter length                             *
 *             quoted - [OUT] the flag that specifies whether parameter was   *
 *                            quoted before extraction                        *
 *                                                                            *
 * Return value: The unquoted parameter. This value must be freed by the      *
 *               caller.                                                      *
 *                                                                            *
 ******************************************************************************/
char	*zbx_function_param_unquote_dyn(const char *param, size_t len, int *quoted)
{
	char	*out;

	out = (char *)zbx_malloc(NULL, len + 1);

	if (0 == (*quoted = (0 != len && '"' == *param)))
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
 * Function: zbx_function_param_quote                                         *
 *                                                                            *
 * Purpose: quotes function parameter                                         *
 *                                                                            *
 * Parameters: param   - [IN/OUT] function parameter                          *
 *             forced  - [IN] 1 - enclose parameter in " even if it does not  *
 *                                contain any special characters              *
 *                            0 - do nothing if the parameter does not        *
 *                                contain any special characters              *
 *                                                                            *
 * Return value: SUCCEED - if parameter was successfully quoted or quoting    *
 *                         was not necessary                                  *
 *               FAIL    - if parameter needs to but cannot be quoted due to  *
 *                         backslash in the end                               *
 *                                                                            *
 ******************************************************************************/
int	zbx_function_param_quote(char **param, int forced)
{
	size_t	sz_src, sz_dst;

	if (0 == forced && '"' != **param && ' ' != **param && NULL == strchr(*param, ',') &&
			NULL == strchr(*param, ')'))
	{
		return SUCCEED;
	}

	if (0 != (sz_src = strlen(*param)) && '\\' == (*param)[sz_src - 1])
		return FAIL;

	sz_dst = zbx_get_escape_string_len(*param, "\"") + 3;

	*param = (char *)zbx_realloc(*param, sz_dst);

	(*param)[--sz_dst] = '\0';
	(*param)[--sz_dst] = '"';

	while (0 < sz_src)
	{
		(*param)[--sz_dst] = (*param)[--sz_src];
		if ('"' == (*param)[sz_src])
			(*param)[--sz_dst] = '\\';
	}
	(*param)[--sz_dst] = '"';

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_no_function                                                  *
 *                                                                            *
 * Purpose: count calculated item (prototype) formula characters that can be  *
 *          skipped without the risk of missing a function                    *
 *                                                                            *
 ******************************************************************************/
static size_t	zbx_no_function(const char *expr)
{
	const char	*ptr = expr;
	int		len, c_l, c_r;

	while ('\0' != *ptr)
	{
		if ('{' == *ptr && '$' == *(ptr + 1) && SUCCEED == zbx_user_macro_parse(ptr, &len, &c_l, &c_r))
		{
			ptr += len + 1;	/* skip to the position after user macro */
		}
		else if (SUCCEED != is_function_char(*ptr))
		{
			ptr++;	/* skip one character which cannot belong to function name */
		}
		else if ((0 == strncmp("and", ptr, len = ZBX_CONST_STRLEN("and")) ||
				0 == strncmp("not", ptr, len = ZBX_CONST_STRLEN("not")) ||
				0 == strncmp("or", ptr, len = ZBX_CONST_STRLEN("or"))) &&
				NULL != strchr("()" ZBX_WHITESPACE, ptr[len]))
		{
			ptr += len;	/* skip to the position after and/or/not operator */
		}
		else
			break;
	}

	return ptr - expr;
}

/******************************************************************************
 *                                                                            *
 * Function: function_validate_parameters                                     *
 *                                                                            *
 * Purpose: validate parameters and give position of terminator if found and  *
 *          not quoted                                                        *
 *                                                                            *
 * Parameters: expr       - [IN] string to parse that contains parameters     *
 *             terminator - [IN] use ')' if parameters end with parenthesis   *
 *                               or '\0' if ends with NULL terminator         *
 *             par_r      - [OUT] position of the terminator if found         *
 *                                                                            *
 * Return value: SUCCEED -  closing parenthesis was found or other custom     *
 *                          terminator and not quoted                         *
 *               FAIL    -  does not look like a valid                        *
 *                          function parameter list                           *
 *                                                                            *
 ******************************************************************************/
static int	function_validate_parameters(const char *expr, char terminator, size_t *par_r)
{
#define ZBX_FUNC_PARAM_NEXT		0
#define ZBX_FUNC_PARAM_QUOTED		1
#define ZBX_FUNC_PARAM_UNQUOTED		2
#define ZBX_FUNC_PARAM_POSTQUOTED	3

	const char	*ptr;
	int		state = ZBX_FUNC_PARAM_NEXT;

	for (ptr = expr; '\0' != *ptr; ptr++)
	{
		if (terminator == *ptr && ZBX_FUNC_PARAM_QUOTED != state)
		{
			*par_r = ptr - expr;
			return SUCCEED;
		}

		switch (state)
		{
			case ZBX_FUNC_PARAM_NEXT:
				if ('"' == *ptr)
					state = ZBX_FUNC_PARAM_QUOTED;
				else if (' ' != *ptr && ',' != *ptr)
					state = ZBX_FUNC_PARAM_UNQUOTED;
				break;
			case ZBX_FUNC_PARAM_QUOTED:
				if ('"' == *ptr && '\\' != *(ptr - 1))
					state = ZBX_FUNC_PARAM_POSTQUOTED;
				break;
			case ZBX_FUNC_PARAM_UNQUOTED:
				if (',' == *ptr)
					state = ZBX_FUNC_PARAM_NEXT;
				break;
			case ZBX_FUNC_PARAM_POSTQUOTED:
				if (',' == *ptr)
					state = ZBX_FUNC_PARAM_NEXT;
				else if (' ' != *ptr)
					return FAIL;
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
		}
	}

	if (terminator == *ptr && ZBX_FUNC_PARAM_QUOTED != state)
	{
		*par_r = ptr - expr;
		return SUCCEED;
	}

	return FAIL;

#undef ZBX_FUNC_PARAM_NEXT
#undef ZBX_FUNC_PARAM_QUOTED
#undef ZBX_FUNC_PARAM_UNQUOTED
#undef ZBX_FUNC_PARAM_POSTQUOTED
}

/******************************************************************************
 *                                                                            *
 * Function: function_match_parenthesis                                       *
 *                                                                            *
 * Purpose: given the position of opening function parenthesis find the       *
 *          position of a closing one                                         *
 *                                                                            *
 * Parameters: expr     - [IN] string to parse                                *
 *             par_l    - [IN] position of the opening parenthesis            *
 *             par_r    - [OUT] position of the closing parenthesis           *
 *                                                                            *
 * Return value: SUCCEED - closing parenthesis was found                      *
 *               FAIL    - string after par_l does not look like a valid      *
 *                         function parameter list                            *
 *                                                                            *
 ******************************************************************************/
static int	function_match_parenthesis(const char *expr, size_t par_l, size_t *par_r)
{
	if (SUCCEED == function_validate_parameters(expr + par_l + 1, ')', par_r))
	{
		*par_r += par_l + 1;
		return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_function_validate_parameters                                 *
 *                                                                            *
 * Purpose: validate parameters that end with '\0'                            *
 *                                                                            *
 * Parameters: expr       - [IN] string to parse that contains parameters     *
 *             length     - [OUT] length of parameters                        *
 *                                                                            *
 * Return value: SUCCEED -  null termination encountered when quotes are      *
 *                          closed and no other error                         *
 *               FAIL    -  does not look like a valid                        *
 *                          function parameter list                           *
 *                                                                            *
 ******************************************************************************/
int	zbx_function_validate_parameters(const char *expr, size_t *length)
{
	return function_validate_parameters(expr, '\0', length);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_function_validate                                            *
 *                                                                            *
 * Purpose: check whether expression starts with a valid function             *
 *                                                                            *
 * Parameters: expr     - [IN] string to parse                                *
 *             par_l    - [OUT] position of the opening parenthesis or the    *
 *                              amount of characters to skip                  *
 *             par_r    - [OUT] position of the closing parenthesis           *
 *                                                                            *
 * Return value: SUCCEED - string starts with a valid function                *
 *               FAIL    - string does not start with a function and par_l    *
 *                         characters can be safely skipped                   *
 *                                                                            *
 ******************************************************************************/
static int	zbx_function_validate(const char *expr, size_t *par_l, size_t *par_r)
{
	/* try to validate function name */
	if (SUCCEED != function_parse_name(expr, par_l))
		return FAIL;

	/* now we know the position of '(', try to find ')' */
	return function_match_parenthesis(expr, *par_l, par_r);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_function_find                                                *
 *                                                                            *
 * Purpose: find the location of the next function and its parameters in      *
 *          calculated item (prototype) formula                               *
 *                                                                            *
 * Parameters: expr     - [IN] string to parse                                *
 *             func_pos - [OUT] function position in the string               *
 *             par_l    - [OUT] position of the opening parenthesis           *
 *             par_r    - [OUT] position of the closing parenthesis           *
 *                                                                            *
 * Return value: SUCCEED - function was found at func_pos                     *
 *               FAIL    - there are no functions in the expression           *
 *                                                                            *
 ******************************************************************************/
int	zbx_function_find(const char *expr, size_t *func_pos, size_t *par_l, size_t *par_r)
{
	const char	*ptr;

	for (ptr = expr; '\0' != *ptr; ptr += *par_l)
	{
		/* skip the part of expression that is definitely not a function */
		ptr += zbx_no_function(ptr);

		/* try to validate function candidate */
		if (SUCCEED != zbx_function_validate(ptr, par_l, par_r))
			continue;

		*func_pos = ptr - expr;
		*par_l += *func_pos;
		*par_r += *func_pos;
		return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_strcmp_natural                                               *
 *                                                                            *
 * Purpose: performs natural comparison of two strings                        *
 *                                                                            *
 * Parameters: s1 - [IN] the first string                                     *
 *             s2 - [IN] the second string                                    *
 *                                                                            *
 * Return value:  0: the strings are equal                                    *
 *               <0: s1 < s2                                                  *
 *               >0: s1 > s2                                                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_strcmp_natural(const char *s1, const char *s2)
{
	int	ret, value1, value2;

	for (;'\0' != *s1 && '\0' != *s2; s1++, s2++)
	{
		if (0 == isdigit(*s1) || 0 == isdigit(*s2))
		{
			if (0 != (ret = *s1 - *s2))
				return ret;

			continue;
		}

		value1 = 0;
		while (0 != isdigit(*s1))
			value1 = value1 * 10 + *s1++ - '0';

		value2 = 0;
		while (0 != isdigit(*s2))
			value2 = value2 * 10 + *s2++ - '0';

		if (0 != (ret = value1 - value2))
			return ret;

		if ('\0' == *s1 || '\0' == *s2)
			break;
	}

	return *s1 - *s2;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_token_parse_user_macro                                       *
 *                                                                            *
 * Purpose: parses user macro token                                           *
 *                                                                            *
 * Parameters: expression - [IN] the expression                               *
 *             macro      - [IN] the beginning of the token                   *
 *             token      - [OUT] the token data                              *
 *                                                                            *
 * Return value: SUCCEED - the user macro was parsed successfully             *
 *               FAIL    - macro does not point at valid user macro           *
 *                                                                            *
 * Comments: If the macro points at valid user macro in the expression then   *
 *           the generic token fields are set and the token->data.user_macro  *
 *           structure is filled with user macro specific data.               *
 *                                                                            *
 ******************************************************************************/
static int	zbx_token_parse_user_macro(const char *expression, const char *macro, zbx_token_t *token)
{
	size_t			offset;
	int			macro_r, context_l, context_r;
	zbx_token_user_macro_t	*data;

	if (SUCCEED != zbx_user_macro_parse(macro, &macro_r, &context_l, &context_r))
		return FAIL;

	offset = macro - expression;

	/* initialize token */
	token->type = ZBX_TOKEN_USER_MACRO;
	token->token.l = offset;
	token->token.r = offset + macro_r;

	/* initialize token data */
	data = &token->data.user_macro;
	data->name.l = offset + 2;

	if (0 != context_l)
	{
		const char *ptr = macro + context_l;

		/* find the context separator ':' by stripping spaces before context */
		while (' ' == *(--ptr))
			;

		data->name.r = offset + (ptr - macro) - 1;

		data->context.l = offset + context_l;
		data->context.r = offset + context_r;
	}
	else
	{
		data->name.r = token->token.r - 1;
		data->context.l = 0;
		data->context.r = 0;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_token_parse_lld_macro                                        *
 *                                                                            *
 * Purpose: parses lld macro token                                            *
 *                                                                            *
 * Parameters: expression - [IN] the expression                               *
 *             macro      - [IN] the beginning of the token                   *
 *             token      - [OUT] the token data                              *
 *                                                                            *
 * Return value: SUCCEED - the lld macro was parsed successfully              *
 *               FAIL    - macro does not point at valid lld macro            *
 *                                                                            *
 * Comments: If the macro points at valid lld macro in the expression then    *
 *           the generic token fields are set and the token->data.lld_macro   *
 *           structure is filled with lld macro specific data.                *
 *                                                                            *
 ******************************************************************************/
static int	zbx_token_parse_lld_macro(const char *expression, const char *macro, zbx_token_t *token)
{
	const char		*ptr;
	size_t			offset;
	zbx_token_macro_t	*data;

	/* find the end of lld macro by validating its name until the closing bracket } */
	for (ptr = macro + 2; '}' != *ptr; ptr++)
	{
		if ('\0' == *ptr)
			return FAIL;

		if (SUCCEED != is_macro_char(*ptr))
			return FAIL;
	}

	/* empty macro name */
	if (2 == ptr - macro)
		return FAIL;

	offset = macro - expression;

	/* initialize token */
	token->type = ZBX_TOKEN_LLD_MACRO;
	token->token.l = offset;
	token->token.r = offset + (ptr - macro);

	/* initialize token data */
	data = &token->data.lld_macro;
	data->name.l = offset + 2;
	data->name.r = token->token.r - 1;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_token_parse_objectid                                         *
 *                                                                            *
 * Purpose: parses object id token                                            *
 *                                                                            *
 * Parameters: expression - [IN] the expression                               *
 *             macro      - [IN] the beginning of the token                   *
 *             token      - [OUT] the token data                              *
 *                                                                            *
 * Return value: SUCCEED - the object id was parsed successfully              *
 *               FAIL    - macro does not point at valid object id            *
 *                                                                            *
 * Comments: If the macro points at valid object id in the expression then    *
 *           the generic token fields are set and the token->data.objectid    *
 *           structure is filled with object id specific data.                *
 *                                                                            *
 ******************************************************************************/
static int	zbx_token_parse_objectid(const char *expression, const char *macro, zbx_token_t *token)
{
	const char		*ptr;
	size_t			offset;
	zbx_token_macro_t	*data;

	/* find the end of object id by checking if it contains digits until the closing bracket } */
	for (ptr = macro + 1; '}' != *ptr; ptr++)
	{
		if ('\0' == *ptr)
			return FAIL;

		if (0 == isdigit(*ptr))
			return FAIL;
	}

	/* empty object id */
	if (1 == ptr - macro)
		return FAIL;


	offset = macro - expression;

	/* initialize token */
	token->type = ZBX_TOKEN_OBJECTID;
	token->token.l = offset;
	token->token.r = offset + (ptr - macro);

	/* initialize token data */
	data = &token->data.objectid;
	data->name.l = offset + 1;
	data->name.r = token->token.r - 1;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_token_parse_macro                                            *
 *                                                                            *
 * Purpose: parses normal macro token                                         *
 *                                                                            *
 * Parameters: expression - [IN] the expression                               *
 *             macro      - [IN] the beginning of the token                   *
 *             token      - [OUT] the token data                              *
 *                                                                            *
 * Return value: SUCCEED - the simple macro was parsed successfully           *
 *               FAIL    - macro does not point at valid macro                *
 *                                                                            *
 * Comments: If the macro points at valid macro in the expression then        *
 *           the generic token fields are set and the token->data.macro       *
 *           structure is filled with simple macro specific data.             *
 *                                                                            *
 ******************************************************************************/
static int	zbx_token_parse_macro(const char *expression, const char *macro, zbx_token_t *token)
{
	const char		*ptr;
	size_t			offset;
	zbx_token_macro_t	*data;

	/* find the end of simple macro by validating its name until the closing bracket } */
	for (ptr = macro + 1; '}' != *ptr; ptr++)
	{
		if ('\0' == *ptr)
			return FAIL;

		if (SUCCEED != is_macro_char(*ptr))
			return FAIL;
	}

	/* empty macro name */
	if (1 == ptr - macro)
		return FAIL;

	offset = macro - expression;

	/* initialize token */
	token->type = ZBX_TOKEN_MACRO;
	token->token.l = offset;
	token->token.r = offset + (ptr - macro);

	/* initialize token data */
	data = &token->data.macro;
	data->name.l = offset + 1;
	data->name.r = token->token.r - 1;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_token_parse_function                                         *
 *                                                                            *
 * Purpose: parses function inside token                                      *
 *                                                                            *
 * Parameters: expression - [IN] the expression                               *
 *             func       - [IN] the beginning of the function                *
 *             func_loc   - [OUT] the function location relative to the       *
 *                                expression (including parameters)           *
 *                                                                            *
 * Return value: SUCCEED - the function was parsed successfully               *
 *               FAIL    - func does not point at valid function              *
 *                                                                            *
 ******************************************************************************/
static int	zbx_token_parse_function(const char *expression, const char *func,
		zbx_strloc_t *func_loc, zbx_strloc_t *func_param)
{
	size_t	par_l, par_r;

	if (SUCCEED != zbx_function_validate(func, &par_l, &par_r))
		return FAIL;

	func_loc->l = func - expression;
	func_loc->r = func_loc->l + par_r;

	func_param->l = func_loc->l + par_l;
	func_param->r = func_loc->l + par_r;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_token_parse_func_macro                                       *
 *                                                                            *
 * Purpose: parses function macro token                                       *
 *                                                                            *
 * Parameters: expression - [IN] the expression                               *
 *             macro      - [IN] the beginning of the token                   *
 *             func       - [IN] the beginning of the macro function in the   *
 *                               token                                        *
 *             token      - [OUT] the token data                              *
 *                                                                            *
 * Return value: SUCCEED - the function macro was parsed successfully         *
 *               FAIL    - macro does not point at valid function macro       *
 *                                                                            *
 * Comments: If the macro points at valid function macro in the expression    *
 *           then the generic token fields are set and the                    *
 *           token->data.func_macro structure is filled with function macro   *
 *           specific data.                                                   *
 *                                                                            *
 ******************************************************************************/
static int	zbx_token_parse_func_macro(const char *expression, const char *macro, const char *func,
		zbx_token_t *token)
{
	zbx_strloc_t		func_loc, func_param;
	zbx_token_func_macro_t	*data;
	const char		*ptr;
	size_t			offset;

	if ('\0' == *func)
		return FAIL;

	if (SUCCEED != zbx_token_parse_function(expression, func, &func_loc, &func_param))
		return FAIL;

	ptr = expression + func_loc.r + 1;

	/* skip trailing whitespace and verify that token ends with } */

	while (' ' == *ptr)
		ptr++;

	if ('}' != *ptr)
		return FAIL;

	offset = macro - expression;

	/* initialize token */
	token->type = ZBX_TOKEN_FUNC_MACRO;
	token->token.l = offset;
	token->token.r = ptr - expression;

	/* initialize token data */
	data = &token->data.func_macro;
	data->macro.l = offset + 1;
	data->macro.r = func_loc.l - 2;

	data->func = func_loc;
	data->func_param = func_param;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_token_parse_simple_macro_key                                 *
 *                                                                            *
 * Purpose: parses simple macro token with given key                          *
 *                                                                            *
 * Parameters: expression - [IN] the expression                               *
 *             macro      - [IN] the beginning of the token                   *
 *             key        - [IN] the beginning of host key inside the token   *
 *             token      - [OUT] the token data                              *
 *                                                                            *
 * Return value: SUCCEED - the function macro was parsed successfully         *
 *               FAIL    - macro does not point at valid simple macro         *
 *                                                                            *
 * Comments: Simple macros have format {<host>:<key>.<func>(<params>)}        *
 *           {HOST.HOSTn} macro can be used for host name and {ITEM.KEYn}     *
 *           macro can be used for item key.                                  *
 *                                                                            *
 *           If the macro points at valid simple macro in the expression      *
 *           then the generic token fields are set and the                    *
 *           token->data.simple_macro structure is filled with simple macro   *
 *           specific data.                                                   *
 *                                                                            *
 ******************************************************************************/
static int	zbx_token_parse_simple_macro_key(const char *expression, const char *macro, const char *key,
		zbx_token_t *token)
{
	size_t				offset;
	zbx_token_simple_macro_t	*data;
	const char			*ptr;
	zbx_strloc_t			key_loc, func_loc, func_param;

	ptr = key;

	if (SUCCEED != parse_key((char **)&ptr))
	{
		zbx_token_t	key_token;

		if (SUCCEED != zbx_token_parse_macro(expression, key, &key_token))
			return FAIL;

		ptr = expression + key_token.token.r + 1;
	}

	/* If the key is without parameters, then parse_key() will move cursor past function name - */
	/* at the start of its parameters. In this case move cursor back before function.           */
	if ('(' == *ptr)
	{
		while ('.' != *(--ptr))
			;
	}

	/* check for empty key */
	if (0 == ptr - key)
		return FAIL;

	if (SUCCEED != zbx_token_parse_function(expression, ptr + 1, &func_loc, &func_param))
		return FAIL;

	key_loc.l = key - expression;
	key_loc.r = ptr - expression - 1;

	ptr = expression + func_loc.r + 1;

	/* skip trailing whitespace and verify that token ends with } */

	while (' ' == *ptr)
		ptr++;

	if ('}' != *ptr)
		return FAIL;

	offset = macro - expression;

	/* initialize token */
	token->type = ZBX_TOKEN_SIMPLE_MACRO;
	token->token.l = offset;
	token->token.r = ptr - expression;

	/* initialize token data */
	data = &token->data.simple_macro;
	data->host.l = offset + 1;
	data->host.r = offset + (key - macro) - 2;

	data->key = key_loc;
	data->func = func_loc;
	data->func_param = func_param;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_token_parse_simple_macro                                     *
 *                                                                            *
 * Purpose: parses simple macro token                                         *
 *                                                                            *
 * Parameters: expression - [IN] the expression                               *
 *             macro      - [IN] the beginning of the token                   *
 *             token      - [OUT] the token data                              *
 *                                                                            *
 * Return value: SUCCEED - the simple macro was parsed successfully           *
 *               FAIL    - macro does not point at valid simple macro         *
 *                                                                            *
 * Comments: Simple macros have format {<host>:<key>.<func>(<params>)}        *
 *           {HOST.HOSTn} macro can be used for host name and {ITEM.KEYn}     *
 *           macro can be used for item key.                                  *
 *                                                                            *
 *           If the macro points at valid simple macro in the expression      *
 *           then the generic token fields are set and the                    *
 *           token->data.simple_macro structure is filled with simple macro   *
 *           specific data.                                                   *
 *                                                                            *
 ******************************************************************************/
static int	zbx_token_parse_simple_macro(const char *expression, const char *macro, zbx_token_t *token)
{
	const char	*ptr;

	/* Find the end of host name by validating its name until the closing bracket }.          */
	/* {HOST.HOSTn} macro usage in the place of host name is handled by nested macro parsing. */
	for (ptr = macro + 1; ':' != *ptr; ptr++)
	{
		if ('\0' == *ptr)
			return FAIL;

		if (SUCCEED != is_hostname_char(*ptr))
			return FAIL;
	}

	/* check for empty host name */
	if (1 == ptr - macro)
		return FAIL;

	return zbx_token_parse_simple_macro_key(expression, macro, ptr + 1, token);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_token_parse_nested_macro                                     *
 *                                                                            *
 * Purpose: parses token with nested macros                                   *
 *                                                                            *
 * Parameters: expression - [IN] the expression                               *
 *             macro      - [IN] the beginning of the token                   *
 *             token      - [OUT] the token data                              *
 *                                                                            *
 * Return value: SUCCEED - the token was parsed successfully                  *
 *               FAIL    - macro does not point at valid function or simple   *
 *                         macro                                              *
 *                                                                            *
 * Comments: This function parses token with a macro inside it. There are two *
 *           types of nested macros - function macros and a specific case     *
 *           of simple macros where {HOST.HOSTn} macro is used as host name.  *
 *           In both cases another macro is found at the beginning of token.  *
 *                                                                            *
 *           If the macro points at valid macro in the expression then        *
 *           the generic token fields are set and either the                  *
 *           token->data.func_macro or token->data.simple_macro (depending on *
 *           token type) structure is filled with macro specific data.        *
 *                                                                            *
 ******************************************************************************/
static int	zbx_token_parse_nested_macro(const char *expression, const char *macro, zbx_token_t *token)
{
	const char		*ptr;

	/* find the end of the nested macro by validating its name until the closing bracket } */
	for (ptr = macro + 2; '}' != *ptr; ptr++)
	{
		if ('\0' == *ptr)
			return FAIL;

		if (SUCCEED != is_macro_char(*ptr))
			return FAIL;
	}

	/* empty macro name */
	if (2 == ptr - macro)
		return FAIL;

	/* Determine the token type by checking the next character after nested macro. */
	/* Function macros have format {{MACRO}.function()} while simple macros        */
	/* have format {{MACRO}:key.function()}.                                       */
	if ('.' == ptr[1])
		return zbx_token_parse_func_macro(expression, macro, ptr + 2, token);
	else if (':' == ptr[1])
		return zbx_token_parse_simple_macro_key(expression, macro, ptr + 2, token);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_token_find                                                   *
 *                                                                            *
 * Purpose: finds token {} inside expression starting at specified position   *
 *          also searches for reference if requested                          *
 *                                                                            *
 * Parameters: expression   - [IN] the expression                             *
 *             pos          - [IN] the starting position                      *
 *             token        - [OUT] the token data                            *
 *             token_search - [IN] specify if references will be searched     *
 *                                                                            *
 * Return value: SUCCEED - the token was parsed successfully                  *
 *               FAIL    - expression does not contain valid token.           *
 *                                                                            *
 * Comments: The token field locations are specified as offsets from the      *
 *           beginning of the expression.                                     *
 *                                                                            *
 *           Simply iterating through tokens can be done with:                *
 *                                                                            *
 *           zbx_token_t token = {0};                                         *
 *                                                                            *
 *           while (SUCCEED == zbx_token_find(expression, token.token.r + 1,  *
 *                       &token))                                             *
 *           {                                                                *
 *                   process_token(expression, &token);                       *
 *           }                                                                *
 *                                                                            *
 ******************************************************************************/
int	zbx_token_find(const char *expression, int pos, zbx_token_t *token, zbx_token_search_t token_search)
{
	int		ret = FAIL;
	const char	*ptr = expression + pos, *dollar = ptr;

	while (SUCCEED != ret)
	{
		ptr = strchr(ptr, '{');

		switch (token_search)
		{
			case ZBX_TOKEN_SEARCH_BASIC:
				break;
			case ZBX_TOKEN_SEARCH_REFERENCES:
				while (NULL != (dollar = strchr(dollar, '$')) && (NULL == ptr || ptr > dollar))
				{
					if (0 == isdigit(dollar[1]))
					{
						dollar++;
						continue;
					}

					token->data.reference.index = dollar[1] - '0';
					token->type = ZBX_TOKEN_REFERENCE;
					token->token.l = dollar - expression;
					token->token.r = token->token.l + 1;
					return SUCCEED;
				}

				if (NULL == dollar)
					token_search = ZBX_TOKEN_SEARCH_BASIC;

				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
		}

		if (NULL == ptr)
			return FAIL;

		if ('\0' == ptr[1])
			return FAIL;

		switch (ptr[1])
		{
			case '$':
				ret = zbx_token_parse_user_macro(expression, ptr, token);
				break;
			case '#':
				ret = zbx_token_parse_lld_macro(expression, ptr, token);
				break;

			case '{':
				ret = zbx_token_parse_nested_macro(expression, ptr, token);
				break;
			case '0':
			case '1':
			case '2':
			case '3':
			case '4':
			case '5':
			case '6':
			case '7':
			case '8':
			case '9':
				if (SUCCEED == (ret = zbx_token_parse_objectid(expression, ptr, token)))
					break;
				/* break; is not missing here */
			default:
				if (SUCCEED != (ret = zbx_token_parse_macro(expression, ptr, token)))
					ret = zbx_token_parse_simple_macro(expression, ptr, token);
		}

		ptr++;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_strmatch_condition                                           *
 *                                                                            *
 * Purpose: check if pattern matches the specified value                      *
 *                                                                            *
 * Parameters: value    - [IN] the value to match                             *
 *             pattern  - [IN] the pattern to match                           *
 *             op       - [IN] the matching operator                          *
 *                                                                            *
 * Return value: SUCCEED - matches, FAIL - otherwise                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_strmatch_condition(const char *value, const char *pattern, unsigned char op)
{
	int	ret = FAIL;

	switch (op)
	{
		case CONDITION_OPERATOR_EQUAL:
			if (0 == strcmp(value, pattern))
				ret = SUCCEED;
			break;
		case CONDITION_OPERATOR_NOT_EQUAL:
			if (0 != strcmp(value, pattern))
				ret = SUCCEED;
			break;
		case CONDITION_OPERATOR_LIKE:
			if (NULL != strstr(value, pattern))
				ret = SUCCEED;
			break;
		case CONDITION_OPERATOR_NOT_LIKE:
			if (NULL == strstr(value, pattern))
				ret = SUCCEED;
			break;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_number_parse                                                 *
 *                                                                            *
 * Purpose: parse a suffixed number like "12.345K"                            *
 *                                                                            *
 * Parameters: number - [IN] start of number                                  *
 *             len    - [OUT] length of parsed number                         *
 *                                                                            *
 * Return value: SUCCEED - the number was parsed successfully                 *
 *               FAIL    - invalid number                                     *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *           The token field locations are specified as offsets from the      *
 *           beginning of the expression.                                     *
 *                                                                            *
 ******************************************************************************/
int	zbx_number_parse(const char *number, int *len)
{
	int	digits = 0, dots = 0;

	*len = 0;

	while (1)
	{
		if (0 != isdigit(number[*len]))
		{
			(*len)++;
			digits++;
			continue;
		}

		if ('.' == number[*len])
		{
			(*len)++;
			dots++;
			continue;
		}

		if (1 > digits || 1 < dots)
			return FAIL;

		if (0 != isalpha(number[*len]) && NULL != strchr(ZBX_UNIT_SYMBOLS, number[*len]))
			(*len)++;

		return SUCCEED;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_number_find                                                  *
 *                                                                            *
 * Purpose: finds number inside expression starting at specified position     *
 *                                                                            *
 * Parameters: str        - [IN] the expression                               *
 *             pos        - [IN] the starting position                        *
 *             number_loc - [OUT] the number location                         *
 *                                                                            *
 * Return value: SUCCEED - the number was parsed successfully                 *
 *               FAIL    - expression does not contain number                 *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *           The token field locations are specified as offsets from the      *
 *           beginning of the expression.                                     *
 *                                                                            *
 ******************************************************************************/
int	zbx_number_find(const char *str, size_t pos, zbx_strloc_t *number_loc)
{
	const char	*s, *e;
	int		len;

	for (s = str + pos; '\0' != *s; s++)	/* find start of number */
	{
		if (0 == isdigit(*s) && ('.' != *s || 0 == isdigit(s[1])))
			continue;

		if (s != str && '{' == *(s - 1) && NULL != (e = strchr(s, '}')))
		{
			/* skip functions '{65432}' */
			s = e;
			continue;
		}

		if (SUCCEED != zbx_number_parse(s, &len))
			continue;

		/* number found */

		number_loc->r = s + len - str - 1;

		/* check for minus before number */
		if (s > str + pos && '-' == *(s - 1))
		{
			/* and make sure it's unary */
			if (s - 1 > str)
			{
				e = s - 2;

				if (e > str && NULL != strchr(ZBX_UNIT_SYMBOLS, *e))
					e--;

				/* check that minus is not preceded by function, parentheses or (suffixed) number */
				if ('}' != *e && ')' != *e && '.' != *e && 0 == isdigit(*e))
					s--;
			}
			else	/* nothing before minus, it's definitely unary */
				s--;
		}

		number_loc->l = s - str;

		return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_replace_mem_dyn                                              *
 *                                                                            *
 * Purpose: to replace memory block and allocate more memory if needed        *
 *                                                                            *
 * Parameters: data       - [IN/OUT] allocated memory                         *
 *             data_alloc - [IN/OUT] allocated memory size                    *
 *             data_len   - [IN/OUT] used memory size                         *
 *             offset     - [IN] offset of memory block to be replaced        *
 *             sz_to      - [IN] size of block that need to be replaced       *
 *             from       - [IN] what to replace with                         *
 *             sz_from    - [IN] size of new block                            *
 *                                                                            *
 * Return value: once data is replaced offset can become less, bigger or      *
 *               remain unchanged                                             *
 ******************************************************************************/
int	zbx_replace_mem_dyn(char **data, size_t *data_alloc, size_t *data_len, size_t offset, size_t sz_to,
		const char *from, size_t sz_from)
{
	size_t	sz_changed = sz_from - sz_to;

	if (0 != sz_changed)
	{
		char	*to;

		*data_len += sz_changed;

		if (*data_len > *data_alloc)
		{
			while (*data_len > *data_alloc)
				*data_alloc *= 2;

			*data = (char *)zbx_realloc(*data, *data_alloc);
		}

		to = *data + offset;
		memmove(to + sz_from, to + sz_to, *data_len - (to - *data) - sz_from);
	}

	memcpy(*data + offset, from, sz_from);

	return (int)sz_changed;
}
