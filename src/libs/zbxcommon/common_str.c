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

/* this file is for the minimal possible set of string related functions that are used by libzbxcommon.a */
/* or libraries/files it depends on */

#include "zbxcommon.h"

/******************************************************************************
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

int	zbx_vsnprintf_check_len(const char *fmt, va_list args)
{
	int	rv;

	if (0 > (rv = vsnprintf(NULL, 0, fmt, args)))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}

	return rv;
}

/******************************************************************************
 *                                                                            *
 * Purpose: dynamical formatted output conversion                             *
 *                                                                            *
 * Return value: formatted string                                             *
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
 * Purpose: dynamical formatted output conversion                             *
 *                                                                            *
 * Return value: formatted string                                             *
 *                                                                            *
 * Comments: returns a pointer to allocated memory                            *
 *                                                                            *
 ******************************************************************************/
char	*zbx_dsprintf(char *dest, const char *f, ...)
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
 * Purpose: Copy src to string dst of size size. At most size - 1 characters  *
 *          will be copied. Always null terminates (unless size == 0).        *
 *                                                                            *
 * Return value: the number of characters copied (excluding the null byte)    *
 *                                                                            *
 ******************************************************************************/
size_t	zbx_strlcpy(char *dst, const char *src, size_t size)
{
	const char	*s = src;

	if (0 != size)
	{
		while (0 != --size && '\0' != *s)
			*dst++ = *s++;

		*dst = '\0';
	}

	return s - src;	/* count does not include null */
}

/******************************************************************************
 *                                                                            *
 * Purpose: Secure version of snprintf function.                              *
 *          Add zero character at the end of string.                          *
 *                                                                            *
 * Parameters: str - destination buffer pointer                               *
 *             count - size of destination buffer                             *
 *             fmt - format                                                   *
 *                                                                            *
 ******************************************************************************/
size_t	zbx_snprintf(char *str, size_t count, const char *fmt, ...)
{
	size_t	written_len;
	va_list	args;

	va_start(args, fmt);
	written_len = zbx_vsnprintf(str, count, fmt, args);
	va_end(args);

	return written_len;
}

#if defined(__hpux)
#include "zbxlog.h"
	/* On HP-UX 11.23 vsnprintf(NULL, 0, fmt, args) cannot be used to     */
	/* determine the required buffer size - the result is program crash   */
	/* (ZBX-23404). Also, it returns -1 if buffer is too small.           */
	/* On HP-UX 11.31 vsnprintf() works as expected.                      */
	/* Agent can be compiled on HP-UX 11.23 but can be running on         */
	/* HP-UX 11.31 - it needs to adapt to vsnprintf() at runtime.         */

static int	vsnprintf_small_buf_test(const char *fmt, ...)
{
	char	buf[4];	/* large enough to store "ABC"+'\0' without corrupting stack */
	int	res;
	va_list	args;

	va_start(args, fmt);
	/* vsnprintf() with too small buffer, only "A"+'\0' can fit */
	res = vsnprintf(buf, sizeof(buf) - 2, fmt, args);
	va_end(args);

	return res;
}

#define VSNPRINTF_UNKNOWN	-1
#define VSNPRINTF_NOT_C99	0
#define VSNPRINTF_IS_C99	1

static int	test_vsnprintf(void)
{
	int	res = vsnprintf_small_buf_test("%s", "ABC");

	zabbix_log(LOG_LEVEL_DEBUG, "vsnprintf() returned %d", res);

	if (0 < res)
		return VSNPRINTF_IS_C99;
	else if (-1 == res)
		return VSNPRINTF_NOT_C99;

	zabbix_log(LOG_LEVEL_CRIT, "vsnprintf() returned %d", res);

	THIS_SHOULD_NEVER_HAPPEN;
	exit(EXIT_FAILURE);
}

int	zbx_hpux_vsnprintf_is_c99(void)
{
	static int	is_c99_vsnprintf = VSNPRINTF_UNKNOWN;

	if (VSNPRINTF_UNKNOWN == is_c99_vsnprintf)
		is_c99_vsnprintf = test_vsnprintf();

	return (VSNPRINTF_IS_C99 == is_c99_vsnprintf) ? SUCCEED : FAIL;
}
#undef VSNPRINTF_UNKNOWN
#undef VSNPRINTF_NOT_C99
#undef VSNPRINTF_IS_C99
#endif

/******************************************************************************
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
 ******************************************************************************/
void	zbx_snprintf_alloc(char **str, size_t *alloc_len, size_t *offset, const char *fmt, ...)
{
	va_list	args;
	size_t	avail_len, written_len;

#if defined(__hpux)
	if (SUCCEED != zbx_hpux_vsnprintf_is_c99())
	{
#define INITIAL_ALLOC_LEN	128
		int	bytes_written = 0;

		if (NULL == *str)
		{
			*alloc_len = INITIAL_ALLOC_LEN;
			*str = (char *)zbx_malloc(NULL, *alloc_len);
			*offset = 0;
		}

		while (1)
		{
			avail_len = *alloc_len - *offset;
			va_start(args, fmt);
			bytes_written = vsnprintf(*str + *offset, avail_len, fmt, args);
			va_end(args);

			if (0 <= bytes_written)
				break;

			if (-1 == bytes_written)
			{
				*alloc_len *= 2;
				*str = (char *)zbx_realloc(*str, *alloc_len);
				continue;
			}

			zabbix_log(LOG_LEVEL_CRIT, "vsnprintf() returned %d", bytes_written);

			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
		}

		*offset += bytes_written;

		return;
#undef INITIAL_ALLOC_LEN
	}
	/* HP-UX vsnprintf() looks C99-compliant, proceed with common implementation */
#endif
retry:
	if (NULL == *str)
	{
		va_start(args, fmt);

		/* zbx_vsnprintf_check_len() cannot return negative result. */
		/* '\0' + one byte to prevent operation retry. */
		*alloc_len = (size_t)zbx_vsnprintf_check_len(fmt, args) + 2;

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

#if defined(_WINDOWS) || defined(__MINGW32__)
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

/* convert from UTF-8 to unicode */
wchar_t	*zbx_utf8_to_unicode(const char *utf8_string)
{
	return zbx_to_unicode(CP_UTF8, utf8_string);
}

/* convert from Windows OEM code page to unicode */
wchar_t	*zbx_oemcp_to_unicode(const char *oemcp_string)
{
	return zbx_to_unicode(CP_OEMCP, oemcp_string);
}
#endif
