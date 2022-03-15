/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "punycode.h"

#include "zbxhttp.h"

/******************************************************************************
 *                                                                            *
 * Purpose: after each delta is encoded or decoded, bias should be set for    *
 *          the next delta (should be adapted)                                *
 *                                                                            *
 * Parameters: delta      - [IN] punycode delta (generalized variable-length  *
 *                               integer)                                     *
 *             count      - [IN] is the total number of code points encoded / *
 *                               decoded so far                               *
 *             divisor    - [IN] delta divisor (to avoid overflow)            *
 *                                                                            *
 * Return value: adapted bias                                                 *
 *                                                                            *
 ******************************************************************************/
static zbx_uint32_t	punycode_adapt(zbx_uint32_t delta, int count, int divisor)
{
	zbx_uint32_t	i;

	delta /= divisor;
	delta += delta / count;

	for (i = 0; PUNYCODE_BIAS_LIMIT < delta; i += PUNYCODE_BASE)
		delta /= PUNYCODE_BASE_MAX;

	return ((PUNYCODE_BASE * delta) / (delta + PUNYCODE_SKEW)) + i;
}

/******************************************************************************
 *                                                                            *
 * Purpose: encodes punycode digit into ansi character [a-z0-9]               *
 *                                                                            *
 * Parameters: digit      - [IN] digit to encode                              *
 *                                                                            *
 * Return value: encoded character                                            *
 *                                                                            *
 ******************************************************************************/
static char	punycode_encode_digit(int digit)
{
	if (0 <= digit && 25 >= digit)
		return digit + 'a';
	else if (25 < digit && PUNYCODE_BASE > digit)
		return digit + 22;

	THIS_SHOULD_NEVER_HAPPEN;
	return '\0';
}

/******************************************************************************
 *                                                                            *
 * Purpose: encodes array of unicode codepoints into punycode (RFC 3492)      *
 *                                                                            *
 * Parameters: codepoints      - [IN] codepoints to encode                    *
 *             count           - [IN] codepoint count                         *
 *             output          - [OUT] encoded result                         *
 *             length          - [IN] length of result buffer                 *
 *                                                                            *
 * Return value: SUCCEED if encoding was successful. FAIL on error.           *
 *                                                                            *
 ******************************************************************************/
static int	punycode_encode_codepoints(zbx_uint32_t *codepoints, size_t count, char *output, size_t length)
{
	int		ret = FAIL;
	zbx_uint32_t	n, delta = 0, bias, max_codepoint, q, k, t;
	size_t		h = 0, out = 0, offset, j;

	n = PUNYCODE_INITIAL_N;
	bias = PUNYCODE_INITIAL_BIAS;

	for (j = 0; j < count; j++)
	{
		if (0x80 > codepoints[j])
		{
			if (2 > length - out)
				goto out;	/* overflow */

			output[out++] = (char)codepoints[j];
		}
	}

	offset = out;
	h = offset;

	if (0 < out)
		output[out++] = '-';

	while (h < count)
	{
		max_codepoint = PUNYCODE_MAX_UINT32;

		for (j = 0; j < count; j++)
		{
			if (codepoints[j] >= n && codepoints[j] < max_codepoint)
				max_codepoint = codepoints[j];
		}

		if (max_codepoint - n > (PUNYCODE_MAX_UINT32 - delta) / (h + 1))
			goto out;	/* overflow */

		delta += (max_codepoint - n) * (h + 1);
		n = max_codepoint;

		for (j = 0; j < count; j++)
		{
			if (codepoints[j] < n && 0 == ++delta)
				goto out;	/* overflow */

			if (codepoints[j] == n)
			{
				q = delta;
				k = PUNYCODE_BASE;

				while (1)
				{
					if (out >= length)
						goto out;	/* out of memory */

					if (k <= bias)
						t = PUNYCODE_TMIN;
					else if (k >= bias + PUNYCODE_TMAX)
						t = PUNYCODE_TMAX;
					else
						t = k - bias;

					if (q < t)
						break;

					output[out++] = punycode_encode_digit(t + (q - t) % (PUNYCODE_BASE - t));
					q = (q - t) / (PUNYCODE_BASE - t);

					k += PUNYCODE_BASE;
				}

				output[out++] = punycode_encode_digit(q);
				bias = punycode_adapt(delta, h + 1, (h == offset) ? PUNYCODE_DAMP : 2);
				delta = 0;
				++h;
			}
		}

		delta++;
		n++;
	}

	if (out >= length)
		goto out;	/* out of memory */

	output[out] = '\0';
	ret = SUCCEED;
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: encodes unicode domain name part into punycode (RFC 3492)         *
 *          domain is being split in parts by punycode_encode by using        *
 *          character '.' as part separator                                   *
 *                                                                            *
 * Parameters: codepoints      - [IN] codepoints to encode                    *
 *             count           - [IN] codepoint count                         *
 *             output          - [IN/OUT] encoded result                      *
 *             size            - [IN/OUT] memory size allocated for result    *
 *             offset          - [IN/OUT] offset within result buffer         *
 *                                                                            *
 * Return value: SUCCEED if encoding was successful. FAIL on error.           *
 *                                                                            *
 ******************************************************************************/
static int	punycode_encode_part(zbx_uint32_t *codepoints, zbx_uint32_t count, char **output, size_t *size,
		size_t *offset)
{
	char		buffer[MAX_STRING_LEN];
	zbx_uint32_t	i, ansi = 1;

	if (0 == count)
		return SUCCEED;

	if (MAX_STRING_LEN <= count)
		return FAIL;

	for (i = 0; i < count; i++)
	{
		if (0x80 <= codepoints[i])
		{
			ansi = 0;
			break;
		}
		else
			buffer[i] = (char)(codepoints[i]);
	}

	if (0 == ansi)
	{
		zbx_strcpy_alloc(output, size, offset, "xn--");
		if (SUCCEED != punycode_encode_codepoints(codepoints, count, buffer, MAX_STRING_LEN))
			return FAIL;
	}
	else
		buffer[count] = '\0';

	zbx_strcpy_alloc(output, size, offset, buffer);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: encodes unicode domain names into punycode (RFC 3492)             *
 *                                                                            *
 * Parameters: text            - [IN] text to encode                          *
 *             output          - [OUT] encoded text                           *
 *                                                                            *
 * Return value: SUCCEED if encoding was successful. FAIL on error.           *
 *                                                                            *
 ******************************************************************************/
static int	zbx_http_punycode_encode(const char *text, char **output)
{
	int		ret = FAIL;
	size_t		offset = 0, size = 0;
	zbx_uint32_t	n, tmp, count = 0, *codepoints;

	zbx_free(*output);
	codepoints = (zbx_uint32_t *)zbx_malloc(NULL, strlen(text) * sizeof(zbx_uint32_t));

	while ('\0' != *text)
	{
		if (0 == (*text & 0x80))
			n = 0;
		else if (0xc0 == (*text & 0xe0))
			n = 1;
		else if (0xe0 == (*text & 0xf0))
			n = 2;
		else if (0xf0 == (*text & 0xf8))
			n = 3;
		else
			goto out;

		if (0 != n)
		{
			tmp = ((zbx_uint32_t)((*text) & (0x3f >> n))) << 6 * n;
			text++;

			while (0 < n)
			{
				n--;
				if ('\0' == *text || 0x80 != ((*text) & 0xc0))
					goto out;

				tmp |= ((zbx_uint32_t)((*text) & 0x3f)) << 6 * n;
				text++;
			}

			codepoints[count++] = tmp;
		}
		else
		{
			if ('.' == *text)
			{
				if (SUCCEED != punycode_encode_part(codepoints, count, output, &size, &offset))
					goto out;

				zbx_chrcpy_alloc(output, &size, &offset, *text++);
				count = 0;
			}
			else
				codepoints[count++] = *text++;
		}
	}

	ret = punycode_encode_part(codepoints, count, output, &size, &offset);
out:
	if (SUCCEED != ret)
		zbx_free(*output);

	zbx_free(codepoints);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: encodes unicode domain name in URL into punycode                  *
 *                                                                            *
 * Parameters: url - [IN/OUT] URL to encode                                   *
 *                                                                            *
 * Return value: SUCCEED if encoding was successful. FAIL on error.           *
 *                                                                            *
 ******************************************************************************/
int	zbx_http_punycode_encode_url(char **url)
{
	char	*domain, *ptr, ascii = 1, delimiter, *iri = NULL;
	size_t	url_alloc, url_len;

	if (NULL == (domain = strchr(*url, '@')))
	{
		if (NULL == (domain = strstr(*url, "://")))
			domain = *url;
		else
			domain += ZBX_CONST_STRLEN("://");
	}
	else
		domain++;

	ptr = domain;

	while ('\0' != *ptr && ':' != *ptr && '/' != *ptr)
	{
		if (0 != ((*ptr) & 0x80))
			ascii = 0;
		ptr++;
	}

	if (1 == ascii)
		return SUCCEED;

	if ('\0' != (delimiter = *ptr))
		*ptr = '\0';

	if (FAIL == zbx_http_punycode_encode(domain, &iri))
	{
		*ptr = delimiter;
		return FAIL;
	}

	*ptr = delimiter;

	url_alloc = url_len = strlen(*url) + 1;

	zbx_replace_mem_dyn(url, &url_alloc, &url_len, domain - *url, ptr - domain, iri, strlen(iri));

	zbx_free(iri);

	return SUCCEED;
}
