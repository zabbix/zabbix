/*
** Copyright (C) 2001-2024 Zabbix SIA
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

#include "zbxstr.h"

#ifdef HAVE_ICONV
#	include <iconv.h>
#endif

/* Has to be rewritten to avoid malloc */
char	*zbx_string_replace(const char *str, const char *sub_str1, const char *sub_str2)
{
	char		*t, *new_str = NULL;
	const char	*p, *q, *r;
	long		len, diff, count = 0;

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
	new_str = (char *)zbx_malloc(new_str, (strlen(str) + (size_t)(count * diff) + 1) * sizeof(char));

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

int	zbx_is_ascii_string(const char *str)
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
 * Purpose: strips characters from end of string                              *
 *                                                                            *
 * Parameters: str      - [IN/OUT] string for processing                      *
 *             charlist - [IN] null terminated list of characters             *
 *                                                                            *
 * Return value: number of trimmed characters                                 *
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
 * Purpose: strips characters from beginning of string                        *
 *                                                                            *
 * Parameters: str      - [IN/OUT] string for processing                      *
 *             charlist - [IN]     null terminated list of characters         *
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
 * Purpose: removes leading and trailing characters from specified character  *
 *          string                                                            *
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
 * Purpose: removes characters 'charlist' from whole string                   *
 *                                                                            *
 * Parameters: str      - [IN/OUT] string for processing                      *
 *             charlist - [IN] null terminated list of characters             *
 *                                                                            *
 ******************************************************************************/
void	zbx_remove_chars(char *str, const char *charlist)
{
	char	*p;

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
 * Purpose: converts text to printable string by converting special           *
 *          characters to escape sequences                                    *
 *                                                                            *
 * Parameters: text - [IN] text to convert                                    *
 *                                                                            *
 * Return value: text converted in printable format                           *
 *                                                                            *
 ******************************************************************************/
char	*zbx_str_printable_dyn(const char *text)
{
	size_t		out_alloc = 0;
	const char	*pin;
	char		*out, *pout;

	for (pin = text; '\0' != *pin; pin++)
	{
		switch (*pin)
		{
			case '\n':
			case '\t':
			case '\r':
				out_alloc += 2;
				break;
			default:
				out_alloc++;
				break;
		}
	}

	out = zbx_malloc(NULL, ++out_alloc);

	for (pin = text, pout = out; '\0' != *pin; pin++)
	{
		switch (*pin)
		{
			case '\n':
				*pout++ = '\\';
				*pout++ = 'n';
				break;
			case '\t':
				*pout++ = '\\';
				*pout++ = 't';
				break;
			case '\r':
				*pout++ = '\\';
				*pout++ = 'r';
				break;
			default:
				*pout++ = *pin;
				break;
		}
	}
	*pout = '\0';

	return out;
}

/******************************************************************************
 *                                                                            *
 * Purpose: deletes all right '0' and '.' for string                          *
 *                                                                            *
 * Parameters: s - [IN/OUT] string to trim '0'                                *
 *                                                                            *
 * Return value: string without right '0'                                     *
 *                                                                            *
 * Comments: 10.0100 => 10.01, 10. => 10                                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_del_zeros(char *s)
{
	int	trim = 0;
	size_t	len = 0;

	while ('\0' != s[len])
	{
		if ('e' == s[len] || 'E' == s[len])
		{
			/* don't touch numbers that are written in scientific notation */
			return;
		}

		if ('.' == s[len])
		{
			/* number has decimal part */

			if (1 == trim)
			{
				/* don't touch invalid numbers with more than one decimal separator */
				return;
			}

			trim = 1;
		}

		len++;
	}

	if (1 == trim)
	{
		size_t	i;

		for (i = len - 1; ; i--)
		{
			if ('0' == s[i])
			{
				s[i] = '\0';
			}
			else if ('.' == s[i])
			{
				s[i] = '\0';
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
 * Purpose: calculates required size for escaped string                       *
 *                                                                            *
 * Parameters: src      - [IN] null terminated source string                  *
 *             charlist - [IN] null terminated to-be-escaped character list   *
 *                                                                            *
 * Return value: size of escaped string                                       *
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
 * Purpose: escapes characters in source string                               *
 *                                                                            *
 * Parameters: src      - [IN] null terminated source string                  *
 *             charlist - [IN] null terminated to-be-escaped character list   *
 *                                                                            *
 * Return value: escaped string                                               *
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

/******************************************************************************
 *                                                                            *
 * Purpose: escapes characters in source string to fixed output buffer        *
 *                                                                            *
 * Parameters: dst      - [OUT] output buffer                                 *
 *             len      - [IN] output buffer size                             *
 *             src      - [IN] null terminated source string                  *
 *             charlist - [IN] null terminated to-be-escaped character list   *
 *                                                                            *
 * Return value: SUCCEED - string was escaped successfully                    *
 *               FAIL    - output buffer is too small                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_escape_string(char *dst, size_t len, const char *src, const char *charlist)
{
	for (; '\0' != *src; src++)
	{
		if (NULL != strchr(charlist, *src))
		{
			if (0 == --len)
				return FAIL;
			*dst++ = '\\';
		}
		else
		{
			if (0 == --len)
				return FAIL;
		}

		*dst++ = *src;
	}

	*dst = '\0';

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if string is contained in list of delimited strings        *
 *                                                                            *
 * Parameters: list      - [IN] strings a,b,ccc,ddd                           *
 *             value     - [IN]                                               *
 *             delimiter - [IN]                                               *
 *                                                                            *
 * Return value: SUCCEED - string is in list, FAIL - otherwise                *
 *                                                                            *
 ******************************************************************************/
int	zbx_str_in_list(const char *list, const char *value, char delimiter)
{
	return zbx_str_n_in_list(list, value, strlen(value), delimiter);
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if string is contained in list of delimited strings        *
 *                                                                            *
 * Parameters: list      - [IN] strings a,b,ccc,ddd                           *
 *             value     - [IN]                                               *
 *             len       - [IN] value length                                  *
 *             delimiter - [IN]                                               *
 *                                                                            *
 * Return value: SUCCEED - string is in list, FAIL - otherwise                *
 *                                                                            *
 ******************************************************************************/
int	zbx_str_n_in_list(const char *list, const char *value, size_t len, char delimiter)
{
	const char	*end;
	size_t		token_len, next = 1;

	while ('\0' != *list)
	{
		if (NULL != (end = strchr(list, delimiter)))
		{
			token_len = end - list;
			next = 1;
		}
		else
		{
			token_len = strlen(list);
			next = 0;
		}

		if (len == token_len && 0 == memcmp(list, value, len))
			return SUCCEED;

		list += token_len + next;
	}

	if (1 == next && 0 == len)
		return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: wraps long string at specified position with linefeeds            *
 *                                                                            *
 * Parameters: src     - [IN] input string                                    *
 *             maxline - [IN] maximum length of line                          *
 *             delim   - [IN] delimiter to use as linefeed                    *
 *                            (default "\n" if NULL)                          *
 *                                                                            *
 * Return value: newly allocated copy of input string with linefeeds          *
 *                                                                            *
 * Comments: allocates memory                                                 *
 *                                                                            *
 ******************************************************************************/
char	*zbx_str_linefeed(const char *src, size_t maxline, const char *delim)
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
 * Purpose: initializes dynamic string array                                  *
 *                                                                            *
 * Parameters: arr - [IN/OUT] pointer to array of strings                     *
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
 * Purpose: adds string to dynamic string array                               *
 *                                                                            *
 * Parameters: arr   - [IN/OUT] pointer to array of strings                   *
 *             entry - [IN] string to add                                     *
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
 * Purpose: frees dynamic string array memory                                 *
 *                                                                            *
 * Parameters: arr - [IN/OUT] array of strings                                *
 *                                                                            *
 ******************************************************************************/
void	zbx_strarr_free(char ***arr)
{
	char	**p;

	for (p = *arr; NULL != *p; p++)
		zbx_free(*p);
	zbx_free(*arr);
}

void	zbx_strcpy_alloc(char **str, size_t *alloc_len, size_t *offset, const char *src)
{
	zbx_strncpy_alloc(str, alloc_len, offset, src, strlen(src));
}

void	zbx_chrcpy_alloc(char **str, size_t *alloc_len, size_t *offset, char c)
{
	zbx_strncpy_alloc(str, alloc_len, offset, &c, 1);
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

void	zbx_strquote_alloc_opt(char **str, size_t *str_alloc, size_t *str_offset, const char *value_str, int option)
{
	size_t		size;
	const char	*src;
	char		*dst;

	for (size = 2, src = value_str; '\0' != *src; src++)
	{
		switch (*src)
		{
			case '\\':
				if (ZBX_STRQUOTE_SKIP_BACKSLASH == option)
					break;
				ZBX_FALLTHROUGH;
			case '"':
				size++;
		}
		size++;
	}

	if (*str_alloc <= *str_offset + size)
	{
		if (0 == *str_alloc)
			*str_alloc = size;

		do
		{
			*str_alloc *= 2;
		}
		while (*str_alloc - *str_offset <= size);

		*str = zbx_realloc(*str, *str_alloc);
	}

	dst = *str + *str_offset;
	*dst++ = '"';

	for (src = value_str; '\0' != *src; src++, dst++)
	{
		switch (*src)
		{
			case '\\':
				if (ZBX_STRQUOTE_SKIP_BACKSLASH == option)
					break;
				ZBX_FALLTHROUGH;
			case '"':
				*dst++ = '\\';
				break;
		}

		*dst = *src;
	}

	*dst++ = '"';
	*dst = '\0';
	*str_offset += size;
}

/******************************************************************************
 *                                                                            *
 * Parameters: src       - [IN] source string                                 *
 *             delimiter - [IN]                                               *
 *             last      - [IN] split after last delimiter                    *
 *             left      - [IN/OUT] first part of string                      *
 *             right     - [IN/OUT] second part of string or NULL, if         *
 *                                  delimiter was not found                   *
 *                                                                            *
 ******************************************************************************/
static void	zbx_string_split(const char *src, char delimiter, unsigned char last, char **left, char **right)
{
	char	*delimiter_ptr;

	if (NULL == (delimiter_ptr = (0 == last ? strchr(src, delimiter) : strrchr(src, delimiter))))
	{
		*left = zbx_strdup(NULL, src);
		*right = NULL;
	}
	else
	{
		size_t	left_size;
		size_t	right_size;

		left_size = (size_t)(delimiter_ptr - src) + 1;
		right_size = strlen(src) - (size_t)(delimiter_ptr - src);

		*left = zbx_malloc(NULL, left_size);
		*right = zbx_malloc(NULL, right_size);

		memcpy(*left, src, left_size - 1);
		(*left)[left_size - 1] = '\0';
		memcpy(*right, delimiter_ptr + 1, right_size);
	}
}

void	zbx_strsplit_first(const char *src, char delimiter, char **left, char **right)
{
	zbx_string_split(src, delimiter, 0, left, right);
}

void	zbx_strsplit_last(const char *src, char delimiter, char **left, char **right)
{
	zbx_string_split(src, delimiter, 1, left, right);
}

/******************************************************************************
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
 * Purpose: calculates number of bytes in utf8 text limited by maxlen bytes   *
 *                                                                            *
 ******************************************************************************/
static size_t	strlen_utf8_nbytes(const char *text, size_t maxlen)
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
 * Purpose: copies utf-8 string + terminating zero character into specified   *
 *          buffer                                                            *
 *                                                                            *
 * Return value: number of copied bytes excluding terminating zero character  *
 *                                                                            *
 * Comments: If the source string is larger than destination buffer then the  *
 *           string is truncated after last valid utf-8 character rather than *
 *           byte.                                                            *
 *                                                                            *
 ******************************************************************************/
size_t	zbx_strlcpy_utf8(char *dst, const char *src, size_t size)
{
	size = strlen_utf8_nbytes(src, size - 1);
	memcpy(dst, src, size);
	dst[size] = '\0';

	return size;
}

/******************************************************************************
 *                                                                            *
 * Purpose: dynamical cating of strings                                       *
 *                                                                            *
 * Return value: new pointer to string                                        *
 *                                                                            *
 * Comments: returns pointer to allocated memory                              *
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
 * Purpose: dynamical cating of formatted strings                             *
 *                                                                            *
 * Return value: new pointer to string                                        *
 *                                                                            *
 * Comments: returns pointer to allocated memory                              *
 *                                                                            *
 ******************************************************************************/
char	*zbx_strdcatf(char *dest, const char *f, ...)
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
 * Purpose: Checks the item key characters length and, if the length exceeds  *
 *          max allowable characters length, truncates the item key, while    *
 *          maintaining the right square bracket.                             *
 *                                                                            *
 * Parameters: key      - [IN] item key for processing                        *
 *             char_max - [IN] item key max characters length                 *
 *             buf      - [IN/OUT] buffer for short version of item key       *
 *             buf_len  - [IN] buffer size for short version of item key      *
 *                                                                            *
 * Return value: item key that does not exceed passed length                  *
 *                                                                            *
 ******************************************************************************/
const char	*zbx_truncate_itemkey(const char *key, const size_t char_max, char *buf, const size_t buf_len)
{
#	define ZBX_SUFFIX	"..."
#	define ZBX_BSUFFIX	"[...]"

	size_t	key_byte_count, key_char_total;
	int	is_bracket = 0;
	char	*bracket_l;

	if (char_max >= (key_char_total = zbx_strlen_utf8(key)))
		return key;

	if (NULL != (bracket_l = strchr(key, '[')))
		is_bracket = 1;

	if (char_max < ZBX_CONST_STRLEN(ZBX_SUFFIX) + 2 * is_bracket)	/* [...] or ... */
		return key;

	if (0 != is_bracket)
	{
		size_t	key_char_count, param_char_count, param_byte_count;

		key_char_count = zbx_charcount_utf8_nbytes(key, bracket_l - key);
		param_char_count = key_char_total - key_char_count;

		if (param_char_count <= ZBX_CONST_STRLEN(ZBX_BSUFFIX))
		{
			if (char_max < param_char_count + ZBX_CONST_STRLEN(ZBX_SUFFIX))
				return key;

			key_byte_count = 1 + zbx_strlen_utf8_nchars(key, char_max - param_char_count -
					ZBX_CONST_STRLEN(ZBX_SUFFIX));
			param_byte_count = 1 + zbx_strlen_utf8_nchars(bracket_l, key_char_count);

			if (buf_len < key_byte_count + ZBX_CONST_STRLEN(ZBX_SUFFIX) + param_byte_count - 1)
				return key;

			key_byte_count = zbx_strlcpy_utf8(buf, key, key_byte_count);
			key_byte_count += zbx_strlcpy_utf8(&buf[key_byte_count], ZBX_SUFFIX, sizeof(ZBX_SUFFIX));
			zbx_strlcpy_utf8(&buf[key_byte_count], bracket_l, param_byte_count);

			return buf;
		}

		if (key_char_count + ZBX_CONST_STRLEN(ZBX_BSUFFIX) > char_max)
		{
			if (char_max <= ZBX_CONST_STRLEN(ZBX_SUFFIX) + ZBX_CONST_STRLEN(ZBX_BSUFFIX))
				return key;

			key_byte_count = 1 + zbx_strlen_utf8_nchars(key, char_max - ZBX_CONST_STRLEN(ZBX_SUFFIX) -
					ZBX_CONST_STRLEN(ZBX_BSUFFIX));

			if (buf_len < key_byte_count + ZBX_CONST_STRLEN(ZBX_SUFFIX) + ZBX_CONST_STRLEN(ZBX_BSUFFIX))
				return key;

			key_byte_count = zbx_strlcpy_utf8(buf, key, key_byte_count);
			key_byte_count += zbx_strlcpy_utf8(&buf[key_byte_count], ZBX_SUFFIX, sizeof(ZBX_SUFFIX));
			zbx_strlcpy_utf8(&buf[key_byte_count], ZBX_BSUFFIX, sizeof(ZBX_BSUFFIX));

			return buf;
		}
	}

	key_byte_count = 1 + zbx_strlen_utf8_nchars(key, char_max - (ZBX_CONST_STRLEN(ZBX_SUFFIX) + is_bracket));

	if (buf_len < key_byte_count + ZBX_CONST_STRLEN(ZBX_SUFFIX) + is_bracket)
		return key;

	key_byte_count = zbx_strlcpy_utf8(buf, key, key_byte_count);
	zbx_strlcpy_utf8(&buf[key_byte_count], ZBX_SUFFIX, sizeof(ZBX_SUFFIX));

	if (0 != is_bracket)
		zbx_strlcpy_utf8(&buf[key_byte_count + ZBX_CONST_STRLEN(ZBX_SUFFIX)], "]", sizeof("]"));

	return buf;

#	undef ZBX_SUFFIX
#	undef ZBX_BSUFFIX
}

/******************************************************************************
 *                                                                            *
 * Purpose: Checks the value characters length and, if the length exceeds     *
 *          max allowable characters length, truncates the value.             *
 *                                                                            *
 * Parameters: val      - [IN] value for processing                           *
 *             char_max - [IN] value max characters length                    *
 *             buf      - [IN/OUT] buffer for short version of value          *
 *             buf_len  - [IN] buffer size for short version of value         *
 *                                                                            *
 * Return value: value that does not exceed passed length                     *
 *                                                                            *
 ******************************************************************************/
const char	*zbx_truncate_value(const char *val, const size_t char_max, char *buf, const size_t buf_len)
{
#	define ZBX_SUFFIX	"..."

	size_t	key_byte_count;

	if (char_max >= zbx_strlen_utf8(val))
		return val;

	key_byte_count = 1 + zbx_strlen_utf8_nchars(val, char_max - ZBX_CONST_STRLEN(ZBX_SUFFIX));

	if (buf_len < key_byte_count + ZBX_CONST_STRLEN(ZBX_SUFFIX))
		return val;

	key_byte_count = zbx_strlcpy_utf8(buf, val, key_byte_count);
	zbx_strlcpy_utf8(&buf[key_byte_count], ZBX_SUFFIX, sizeof(ZBX_SUFFIX));

	return buf;

#	undef ZBX_SUFFIX
}

/************************************************************************************************
 *                                                                                              *
 * Comments: Note, that although the input 'string' was const, the return is not, as the caller *
 *           owns it and can modify it. This is similar to strstr() and strcasestr() functions. *
 *           We may need to find a way how to silence the resulting '-Wcast-qual' warning.      *
 *                                                                                              *
 ************************************************************************************************/
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

#if defined(_WINDOWS) || defined(__MINGW32__)
#include "zbxlog.h"
/******************************************************************************
 *                                                                            *
 * Parameters: encoding - [IN] non-empty string, code page identifier         *
 *                             (as in libiconv or Windows SDK docs)           *
 *             codepage - [OUT] code page number                              *
 *                                                                            *
 * Return value: SUCCEED or FAIL                                              *
 *                                                                            *
 ******************************************************************************/
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
			{1200, "UTF-16LE"}, {1201, "UNICODEFFFE"}, {1201, "UTF-16BE"}, {1250, "WINDOWS-1250"},
			{1251, "WINDOWS-1251"}, {1252, "WINDOWS-1252"}, {1253, "WINDOWS-1253"}, {1254, "WINDOWS-1254"},
			{1255, "WINDOWS-1255"}, {1256, "WINDOWS-1256"}, {1257, "WINDOWS-1257"}, {1258, "WINDOWS-1258"},
			{1361, "JOHAB"}, {10000, "MACINTOSH"}, {10001, "X-MAC-JAPANESE"}, {10002, "X-MAC-CHINESETRAD"},
			{10003, "X-MAC-KOREAN"}, {10004, "X-MAC-ARABIC"}, {10005, "X-MAC-HEBREW"},
			{10006, "X-MAC-GREEK"}, {10007, "X-MAC-CYRILLIC"}, {10008, "X-MAC-CHINESESIMP"},
			{10010, "X-MAC-ROMANIAN"}, {10017, "X-MAC-UKRAINIAN"}, {10021, "X-MAC-THAI"},
			{10029, "X-MAC-CE"}, {10079, "X-MAC-ICELANDIC"}, {10081, "X-MAC-TURKISH"},
			{10082, "X-MAC-CROATIAN"}, {12000, "UTF-32"}, {12001, "UTF-32BE"}, {20000, "X-CHINESE_CNS"},
			{20001, "X-CP20001"}, {20002, "X_CHINESE-ETEN"}, {20003, "X-CP20003"}, {20004, "X-CP20004"},
			{20005, "X-CP20005"}, {20105, "X-IA5"}, {20106, "X-IA5-GERMAN"}, {20107, "X-IA5-SWEDISH"},
			{20108, "X-IA5-NORWEGIAN"}, {20127, "US-ASCII"}, {20261, "X-CP20261"}, {20269, "X-CP20269"},
			{20273, "IBM273"}, {20277, "IBM277"}, {20278, "IBM278"}, {20280, "IBM280"}, {20284, "IBM284"},
			{20285, "IBM285"}, {20290, "IBM290"}, {20297, "IBM297"}, {20420, "IBM420"}, {20423, "IBM423"},
			{20424, "IBM424"}, {20833, "X-EBCDIC-KOREANEXTENDED"}, {20838, "IBM-THAI"}, {20866, "KOI8-R"},
			{20871, "IBM871"}, {20880, "IBM880"}, {20905, "IBM905"}, {20924, "IBM00924"}, {20932, "EUC-JP"},
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

const char	*zbx_get_bom_econding(char *in, size_t in_size)
{
	if (3 <= in_size && 0 == strncmp("\xef\xbb\xbf", in, 3))
	{
		return "UTF-8";
	}
	else if (2 <= in_size && 0 == strncmp("\xff\xfe", in, 2))
	{
		return "UTF-16LE";
	}
	else if (2 <= in_size && 0 == strncmp("\xfe\xff", in, 2))
	{
		return "UTF-16BE";
	}

	return "";
}

#if defined(_WINDOWS) || defined(__MINGW32__)
char	*zbx_convert_to_utf8(char *in, size_t in_size, const char *encoding, char **error)
{
#define STATIC_SIZE	1024
	char		*out_utf8_string = NULL;
	wchar_t		wide_string_static[STATIC_SIZE], *wide_string = NULL;
	int		wide_size, utf8_size, bom_detected = 0;
	unsigned int	codepage;

	/* try to guess encoding using BOM if it exists */
	if (3 <= in_size && 0 == strncmp("\xef\xbb\xbf", in, 3))
	{
		bom_detected = 1;

		if ('\0' == *encoding)
			encoding = "UTF-8";
	}
	else if (2 <= in_size && 0 == strncmp("\xff\xfe", in, 2))
	{
		bom_detected = 1;

		if ('\0' == *encoding)
			encoding = "UTF-16";
	}
	else if (2 <= in_size && 0 == strncmp("\xfe\xff", in, 2))
	{
		bom_detected = 1;

		if ('\0' == *encoding)
			encoding = "UNICODEFFFE";
	}

	if ('\0' == *encoding)
	{
		utf8_size = (int)in_size + 1;
		out_utf8_string = zbx_malloc(out_utf8_string, utf8_size);
		memcpy(out_utf8_string, in, in_size);
		out_utf8_string[in_size] = '\0';

		goto out;
	}

	if (FAIL == get_codepage(encoding, &codepage))
	{
		*error = zbx_dsprintf(NULL, "Failed to convert from encoding %s to utf8. Failed to get codepage.",
				encoding);

		goto out;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "zbx_convert_to_utf8() in_size:%d encoding:'%s' codepage:%u", in_size, encoding,
			codepage);

	if (65001 == codepage)
	{
		/* remove BOM */
		if (bom_detected)
			in += 3;
	}

	if (1200 == codepage)		/* Unicode UTF-16, little-endian byte order */
	{
		wide_size = (int)in_size / 2;

		/* remove BOM */
		if (1 == bom_detected)
		{
			in += 2;
			wide_size--;
		}

		wide_string = (wchar_t *)in;

	}
	else if (1201 == codepage)	/* unicodeFFFE UTF-16, big-endian byte order */
	{
		wchar_t *wide_string_be;
		int	i;

		wide_size = (int)in_size / 2;

		/* remove BOM */
		if (1 == bom_detected)
		{
			in += 2;
			wide_size--;
		}

		wide_string_be = (wchar_t *)in;

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
		if (0 == MultiByteToWideChar(codepage, 0, in, (int)in_size, wide_string, wide_size))
			goto utf8_convert_fail;
	}

	if (0 == (utf8_size = WideCharToMultiByte(CP_UTF8, 0, wide_string, wide_size, NULL, 0, NULL, NULL)))
		goto utf8_convert_fail;

	out_utf8_string = (char *)zbx_malloc(out_utf8_string, (size_t)utf8_size + 1/* '\0' */);

	/* convert from 'wide_string' to 'utf8_string' */
	if (0 == WideCharToMultiByte(CP_UTF8, 0, wide_string, wide_size, out_utf8_string, utf8_size, NULL, NULL))
		goto utf8_convert_fail;

	out_utf8_string[utf8_size] = '\0';

	if (wide_string != wide_string_static && wide_string != (wchar_t *)in)
		zbx_free(wide_string);

	goto out;
utf8_convert_fail:
	zbx_free(out_utf8_string);
	out_utf8_string = NULL;
	*error = zbx_dsprintf(NULL, "Failed to convert from encoding %s to utf8. Error: %s.", encoding,
			zbx_strerror_from_system(GetLastError()));
out:
	return out_utf8_string;
}
#elif defined(HAVE_ICONV)
char	*zbx_convert_to_utf8(char *in, size_t in_size, const char *encoding, char **error)
{
	iconv_t		cd;
	size_t		in_size_left, out_size_left, sz, out_alloc = 0;
	const char	to_code[] = "UTF-8";
	char		*p, *out_utf8_string = NULL;

	out_alloc = in_size + 1;
	p = out_utf8_string = (char *)zbx_malloc(out_utf8_string, out_alloc);

	/* try to guess encoding using BOM if it exists */
	if ('\0' == *encoding)
		encoding = zbx_get_bom_econding(in, in_size);

	if ('\0' == *encoding )
	{
		memcpy(out_utf8_string, in, in_size);
		out_utf8_string[in_size] = '\0';
		goto out;
	}

	if ((iconv_t)-1 == (cd = iconv_open(to_code, encoding)))
		goto utf8_convert_fail;

	in_size_left = in_size;
	out_size_left = out_alloc - 1;

	while ((size_t)(-1) == iconv(cd, &in, &in_size_left, &p, &out_size_left))
	{
		if (E2BIG != errno)
			break;

		sz = (size_t)(p - out_utf8_string);
		out_alloc += in_size;
		out_size_left += in_size;
		p = out_utf8_string = (char *)zbx_realloc(out_utf8_string, out_alloc);
		p += sz;
	}

	*p = '\0';

	if (0 != iconv_close(cd))
		goto utf8_convert_fail;

	/* remove BOM */
	if (3 <= p - out_utf8_string && 0 == strncmp("\xef\xbb\xbf", out_utf8_string, 3))
		memmove(out_utf8_string, out_utf8_string + 3, (size_t)(p - out_utf8_string - 2));

	goto out;
utf8_convert_fail:
	zbx_free(out_utf8_string);
	out_utf8_string = NULL;
	*error = zbx_dsprintf(NULL, "Failed to convert from encoding %s to utf8. Error: %s.", encoding,
			zbx_strerror(errno));
out:
	return out_utf8_string;
}
#endif	/* HAVE_ICONV */

/******************************************************************************
 *                                                                            *
 * Purpose: Returns the size (in bytes) of a UTF-8 encoded character or 0     *
 *          if the character is not a valid UTF-8.                            *
 *                                                                            *
 * Parameters: text - [IN] pointer to 1st byte of UTF-8 character             *
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
#if ZBX_MAX_BYTES_IN_UTF8_CHAR != 4
#	error "zbx_utf8_char_len() is not synchronized with ZBX_MAX_BYTES_IN_UTF8_CHAR"
#endif
	return 0;				/* not a valid UTF-8 character */
}

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

char	*zbx_strshift_utf8(char *text, size_t num)
{
	while ('\0' != *text && 0 < num)
	{
		if (0x80 != (0xc0 & *(++text)))
			num--;
	}

	return text;
}

/******************************************************************************
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
 * Purpose: calculates number of chars in utf8 text limited by maxlen bytes   *
 *                                                                            *
 ******************************************************************************/
size_t	zbx_charcount_utf8_nbytes(const char *text, size_t maxlen)
{
	size_t	n = 0;

	maxlen = strlen_utf8_nbytes(text, maxlen);

	while ('\0' != *text && maxlen > 0)
	{
		if (0x80 != (0xc0 & *text++))
			n++;

		maxlen--;
	}

	return n;
}

#define ZBX_UTF8_REPLACE_CHAR	'?'
#define ZBX_CTRL_REPLACE_CHAR	'.'

/******************************************************************************
 *                                                                            *
 * Purpose: checks UTF-8 sequences                                            *
 *                                                                            *
 * Parameters: text - [IN] pointer to string                                  *
 *                                                                            *
 * Return value: SUCCEED if string is valid or FAIL otherwise                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_is_utf8(const char *text)
{
	unsigned int		utf32;
	const unsigned char	*utf8;
	size_t			i, mb_len, expecting_bytes = 0;

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

		utf8 = (const unsigned char *)text;

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
 * Purpose: checks ascii characters to be printable                           *
 *                                                                            *
 * Parameters: text - [IN] pointer to string                                  *
 *                                                                            *
 * Return value: SUCCEED if string is valid or FAIL otherwise                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_is_ascii_printable(const char *text)
{
	while ('\0' != *text)
	{
		/* single ASCII character */
		if (0 == (*text & 0x80) && 0 == isprint(*text) && 0 == isspace(*text))
			return FAIL;

		text++;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: replaces invalid UTF-8 sequences of bytes with '?' character      *
 *                                                                            *
 * Parameters: text - [IN/OUT] pointer to first char                          *
 *             replace_nonprintable - [IN] 0 - leave control characters       *
 *                                         1 - replace control characters     *
 *                                                                            *
 ******************************************************************************/
static void	zbx_replace_invalid_utf8_impl(char *text, int replace_nonprintable)
{
	char	*out = text;

	while ('\0' != *text)
	{
		if (0 == (*text & 0x80))			/* single ASCII character */
		{
			if (0 == replace_nonprintable || 0 == iscntrl((int)*text))
				*out++ = *text;
			else
				*out++ = ZBX_CTRL_REPLACE_CHAR;
			text++;
		}
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

			mb_len = (size_t)(out - (char *)utf8);

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
				else if (0 != replace_nonprintable && 0x80 <= utf32 && 0x9f >= utf32)
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

#undef ZBX_UTF8_REPLACE_CHAR
#undef ZBX_CTRL_REPLACE_CHAR

/******************************************************************************
 *                                                                            *
 * Purpose: replaces invalid UTF-8 sequences of bytes with '?' character      *
 *                                                                            *
 * Parameters: text - [IN/OUT] pointer to first char                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_replace_invalid_utf8(char *text)
{
	zbx_replace_invalid_utf8_impl(text, 0);
}

/******************************************************************************
 *                                                                            *
 * Purpose: replaces invalid UTF-8 sequences of bytes with '?' character and  *
 *          non-printable control characters with '.'                         *
 *                                                                            *
 * Parameters: text - [IN/OUT] pointer to first char                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_replace_invalid_utf8_and_nonprintable(char *text)
{
	zbx_replace_invalid_utf8_impl(text, 1);
}

void	zbx_dos2unix(char *str)
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

/******************************************************************************
 *                                                                            *
 * Purpose: replaces memory block and allocates more memory if needed         *
 *                                                                            *
 * Parameters: data       - [IN/OUT] allocated memory                         *
 *             data_alloc - [IN/OUT] allocated memory size                    *
 *             data_len   - [IN/OUT] used memory size                         *
 *             offset     - [IN] offset of memory block to be replaced        *
 *             sz_to      - [IN] size of block that need to be replaced       *
 *             from       - [IN] what to replace with                         *
 *             sz_from    - [IN] size of new block                            *
 *                                                                            *
 * Return value: once data is replaced offset can become smaller, bigger or   *
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
		memmove(to + sz_from, to + sz_to, *data_len - (size_t)(to - *data) - sz_from);
	}

	memcpy(*data + offset, from, sz_from);

	return (int)sz_changed;
}

/******************************************************************************
 *                                                                            *
 * Purpose: removes whitespace surrounding string list item delimiters        *
 *                                                                            *
 * Parameters: list      - [IN/OUT] list (string containing items separated   *
 *                                  by delimiter)                             *
 *             delimiter - [IN] list delimiter                                *
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
 * Purpose: compares two strings where any of them can be NULL pointer        *
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
 * Purpose: escapes single quote in shell command arguments                   *
 *                                                                            *
 * Parameters: arg - [IN] argument to escape                                  *
 *                                                                            *
 * Return value: escaped argument                                             *
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
 * Purpose: performs natural comparison of two strings                        *
 *                                                                            *
 * Parameters: s1 - [IN] first string                                         *
 *             s2 - [IN] second string                                        *
 *                                                                            *
 * Return value:  0: strings are equal                                        *
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

/************************************************************************************
 *                                                                                  *
 * Purpose: extracts value from string, unquoting if necessary                      *
 *                                                                                  *
 * Parameters: text  - [IN] text containing value to extract                        *
 *             len   - [IN] Length (in bytes) of the value to extract. It can be 0. *
 *                          It must not exceed length of 'text' string.             *
 *             value - [OUT] extracted value                                        *
 *                                                                                  *
 * Return value: SUCCEED - value was extracted successfully                         *
 *               FAIL    - otherwise                                                *
 *                                                                                  *
 * Comments: When unquoting value only " and \ character escapes are accepted.      *
 *                                                                                  *
 ************************************************************************************/
int	zbx_str_extract(const char *text, size_t len, char **value)
{
	char		*tmp, *out;
	const char	*in;

	tmp = zbx_malloc(NULL, len + 1);

	if (0 == len)
	{
		*tmp = '\0';
		*value = tmp;
		return SUCCEED;
	}

	if ('"' != *text)
	{
		memcpy(tmp, text, len);
		tmp[len] = '\0';
		*value = tmp;
		return SUCCEED;
	}

	if (2 > len)
		goto fail;

	for (out = tmp, in = text + 1; '"' != *in; in++)
	{
		if ((size_t)(in - text) >= len - 1)
			goto fail;

		if ('\\' == *in)
		{
			if ((size_t)(++in - text) >= len - 1)
				goto fail;

			if ('"' != *in && '\\' != *in)
				goto fail;
		}
		*out++ = *in;
	}

	if ((size_t)(in - text) != len - 1)
		goto fail;

	*out = '\0';
	*value = tmp;
	return SUCCEED;
fail:
	zbx_free(tmp);
	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: extracts substring at specified location                          *
 *                                                                            *
 * Parameters: src   - [IN] source string                                     *
 *             left  - [IN] left substring position (start)                   *
 *             right - [IN] right substring position (end)                    *
 *                                                                            *
 * Return value: unquoted and copied substring                                *
 *                                                                            *
 ******************************************************************************/
char	*zbx_substr(const char *src, size_t left, size_t right)
{
	char	*str;

	str = zbx_malloc(NULL, right - left + 2);
	memcpy(str, src + left, right - left + 1);
	str[right - left + 1] = '\0';

	return str;
}

/******************************************************************************
 *                                                                            *
 * Purpose: unquotes valid substring at specified location                    *
 *                                                                            *
 * Parameters: src   - [IN] source string                                     *
 *             left  - [IN] left substring position (start)                   *
 *             right - [IN] right substring position (end)                    *
 *                                                                            *
 * Return value: unquoted and copied substring                                *
 *                                                                            *
 ******************************************************************************/
char	*zbx_substr_unquote(const char *src, size_t left, size_t right)
{
	char	*str, *ptr;

	if ('"' == src[left])
	{
		src += left + 1;
		str = zbx_malloc(NULL, right - left);
		ptr = str;

		while ('"' != *src)
		{
			if ('\\' == *src)
			{
				switch (*(++src))
				{
					case '\\':
						*ptr++ = '\\';
						break;
					case '"':
						*ptr++ = '"';
						break;
					case '\0':
						THIS_SHOULD_NEVER_HAPPEN;
						*ptr = '\0';
						return str;
				}
			}
			else
				*ptr++ = *src;
			src++;
		}
		*ptr = '\0';
	}
	else
	{
		str = zbx_malloc(NULL, right - left + 2);
		memcpy(str, src + left, right - left + 1);
		str[right - left + 1] = '\0';
	}

	return str;
}

/******************************************************************************
 *                                                                            *
 * Purpose: returns pointer to next utf-8 character                           *
 *                                                                            *
 * Parameters: str - [IN]                                                     *
 *                                                                            *
 * Return value: pointer to next utf-8 character                              *
 *                                                                            *
 ******************************************************************************/
static const char	*utf8_chr_next(const char *str)
{
	++str;

	while (0x80 == (0xc0 & (unsigned char)*str))
		str++;

	return str;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if string contains utf-8 character                         *
 *                                                                            *
 * Parameters: seq  - [IN]                                                    *
 *             c    - [IN] utf-8 character to look for                        *
 *                                                                            *
 * Return value: SUCCEED - string contains specified character                *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	strchr_utf8(const char *seq, const char *c)
{
	size_t	len, c_len;

	if (0 == (c_len = zbx_utf8_char_len(c)))
		return FAIL;

	if (1 == c_len)
		return (NULL == strchr(seq, *c) ? FAIL : SUCCEED);

	/* check for broken utf-8 sequence in character */
	if (c + c_len != utf8_chr_next(c))
		return FAIL;

	while ('\0' != *seq)
	{
		len = (size_t)(utf8_chr_next(seq) - seq);

		if (len == c_len && 0 == memcmp(seq, c, len))
			return SUCCEED;

		seq += len;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: trims specified utf-8 characters from left side of input string   *
 *                                                                            *
 * Parameters: str      - [IN] input string                                   *
 *             charlist - [IN] characters to trim                             *
 *                                                                            *
 ******************************************************************************/
void	zbx_ltrim_utf8(char *str, const char *charlist)
{
	const char	*next;

	for (next = str; '\0' != *next; next = utf8_chr_next(next))
	{
		if (SUCCEED != strchr_utf8(charlist, next))
			break;
	}

	if (next != str)
	{
		size_t	len;

		if (0 != (len = strlen(next)))
			memmove(str, next, len);

		str[len] = '\0';
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: returns pointer to previous utf-8 character                       *
 *                                                                            *
 * Parameters: str   - [IN]                                                   *
 *             start - [IN] start of initial string                           *
 *                                                                            *
 * Return value: pointer to previous utf-8 character                          *
 *                                                                            *
 ******************************************************************************/
static char	*utf8_chr_prev(char *str, const char *start)
{
	do
	{
		if (--str < start)
			return NULL;
	}
	while (0x80 == (0xc0 & (unsigned char)*str));

	return str;
}

/******************************************************************************
 *                                                                            *
 * Purpose: trims specified utf-8 characters from right side of input string  *
 *                                                                            *
 * Parameters: str      - [IN]                                                *
 *             charlist - [IN] characters to trim                             *
 *                                                                            *
 ******************************************************************************/
void	zbx_rtrim_utf8(char *str, const char *charlist)
{
	char	*prev, *last;

	for (last = str + strlen(str), prev = last; NULL != prev; prev = utf8_chr_prev(prev, str))
	{
		if (SUCCEED != strchr_utf8(charlist, prev))
			break;

		if ((last = prev) <= str)
			break;
	}

	*last = '\0';
}

/******************************************************************************
 *                                                                            *
 * Purpose: If there is no '\0' byte among the first n bytes of src, then all *
 *          n bytes will be placed into the dest buffer. In other case, only  *
 *          strlen() bytes will be placed there. Adds zero character at the   *
 *          end of string. Reallocs memory if not enough.                     *
 *                                                                            *
 * Parameters: str       - [IN/OUT] destination buffer pointer                *
 *             alloc_len - [IN/OUT] already allocated memory                  *
 *             offset    - [IN/OUT] offset for writing                        *
 *             src       - [IN] copied string                                 *
 *             n         - [IN] maximum number of bytes to copy               *
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
		if (0 == *alloc_len)
		{
			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
		}

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

/******************************************************************************
 *                                                                            *
 * Purpose: replaces data block with 'value'                                  *
 *                                                                            *
 * Parameters: data  - [IN/OUT] pointer to string                             *
 *             l     - [IN] left position of block                            *
 *             r     - [IN/OUT] right position of block                       *
 *             value - [IN] string to replace block with                      *
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
