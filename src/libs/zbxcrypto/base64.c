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

#include "zbxcrypto.h"

#include <assert.h>
#include "zbxcommon.h"

/**********************************************************************
 *  Purpose: Check if supplied character is a valid base64 character. *
 *           Not a complete check, since it ignores equal sign '='    *
 *	     as it depends on the context where this is placed.       *
 *	     Corresponds to the [A-Za-z0-9+\\/] block validation from *
 *	     zbx_base64_validate() function below.                    *
 **********************************************************************/
static int	is_valid_base64_char(const char c)
{
	if (('A' <= c && 'Z' >= c) ||
		('a' <= c && 'z' >= c) ||
		('/' <= c && '9' >= c) ||
		'+' == c)
	{
		return SUCCEED;
	}

	return FAIL;
}

/*************************************************************************************************
 *                                                                                               *
 * Purpose: Checks if the string is a valid Base64 encoded string.                               *
 *          Check is based on RFC 4648, based on the following regexp:                           *
 *   "^(?:[A-Za-z0-9+\\/]{4})*(?:[A-Za-z0-9+\\/]{2}==|[A-Za-z0-9+\\/]{3}=|[A-Za-z0-9+\\/]{4})$"  *
 *                                                                                               *
 *          Note, that pcre regexp matching cannot be used, because it would exceed max          *
 *          stack frame limit when recursively checking long strings,                            *
 *          (check compute_recursion_limit() for more details).                                  *
 *                                                                                               *
 * Parameters: p_str - [IN] string to validate                                                   *
 *                                                                                               *
 * Return value: SUCCEED - string is a valid Base64 encoded string                               *
 *               FAIL    - otherwise                                                             *
 *                                                                                               *
 *************************************************************************************************/
int	zbx_base64_validate(const char *p_str)
{
	size_t	i;

	/* consider empty strings - valid Base64 encodings */
	if ('\0' == p_str[0])
		return SUCCEED;

	for (i = 1; '\0' != p_str[i] || (0 == i % 4); i++)
	{
		if (0 != i % 4)
			continue;

		/* validate first/repeated block: (?:[A-Za-z0-9+\\/]{4}) */
		if (SUCCEED == is_valid_base64_char(p_str[i - 4]) &&
				SUCCEED == is_valid_base64_char(p_str[i - 3]) &&
				SUCCEED == is_valid_base64_char(p_str[i - 2]) &&
				SUCCEED == is_valid_base64_char(p_str[i - 1]))
		{
			if ('\0' == p_str[i])
				return SUCCEED;
			else
				continue;
		}
		/* validate second/final block: (?:[A-Za-z0-9+\\/]{2}==|[A-Za-z0-9+\\/]{3}=|[A-Za-z0-9+\\/]{4}) */
		else if ('\0' == p_str[i])
		{
			if (SUCCEED == is_valid_base64_char(p_str[i - 4]) &&
					SUCCEED == is_valid_base64_char(p_str[i - 3]) && '=' == p_str[i - 2] &&
					'=' == p_str[i - 1])
			{
					return SUCCEED;
			}
			else if (SUCCEED == is_valid_base64_char(p_str[i - 4]) &&
					SUCCEED == is_valid_base64_char(p_str[i - 3]) &&
					SUCCEED == is_valid_base64_char(p_str[i - 2]) &&
					'=' == p_str[i - 1])
			{
				return SUCCEED;
			}
			else if (SUCCEED == is_valid_base64_char(p_str[i - 4]) &&
					SUCCEED == is_valid_base64_char(p_str[i - 3]) &&
					SUCCEED == is_valid_base64_char(p_str[i - 2]) &&
					SUCCEED == is_valid_base64_char(p_str[i - 1]))
			{
				return SUCCEED;
			}
			else
				return FAIL;
		}
		return FAIL;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: is the character passed in a base64 character?                    *
 *                                                                            *
 * Parameters: c - [IN] character to test                                     *
 *                                                                            *
 * Return value: SUCCEED - character is a base64 character                    *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	is_base64(char c)
{
	if ((c >= 'A' && c <= 'Z') ||
			(c >= 'a' && c <= 'z') ||
			(c >= '0' && c <= '9') ||
			c == '+' ||
			c == '/' ||
			c == '=')

	{
		return SUCCEED;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: encodes 6 bits into a base64 character                            *
 *                                                                            *
 * Parameters: uc - [IN] Character to encode. Its value must be 0 ... 63.     *
 *                                                                            *
 * Return value: byte encoded into a base64 character                         *
 *                                                                            *
 ******************************************************************************/
static char	char_base64_encode(unsigned char uc)
{
	static const char	base64_set[] = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";

	return base64_set[uc];
}

/******************************************************************************
 *                                                                            *
 * Purpose: decodes base64 character into byte                                *
 *                                                                            *
 * Parameters: c - [IN] character to decode                                   *
 *                                                                            *
 * Return value: base64 character decoded into byte                           *
 *                                                                            *
 ******************************************************************************/
static unsigned char	char_base64_decode(char c)
{
	if (c >= 'A' && c <= 'Z')
	{
		return c - 'A';
	}

	if (c >= 'a' && c <= 'z')
	{
		return c - 'a' + 26;
	}

	if (c >= '0' && c <= '9')
	{
		return c - '0' + 52;
	}

	if (c == '+')
	{
		return 62;
	}

	return 63;
}

/******************************************************************************
 *                                                                            *
 * Purpose: encodes string into base64 string                                 *
 *                                                                            *
 * Parameters: p_str    - [IN] string to encode                               *
 *             p_b64str - [OUT] encoded str to return                         *
 *             in_size  - [IN] size (length) of input str                     *
 *                                                                            *
 ******************************************************************************/
void	zbx_base64_encode(const char *p_str, char *p_b64str, int in_size)
{
	int		i;
	unsigned char	from1, from2, from3;
	unsigned char	to1, to2, to3, to4;
	char		*p;

	if (0 == in_size)
	{
		return;
	}

	assert(p_str);
	assert(p_b64str);

	p = p_b64str;

	for (i = 0; i < in_size; i += 3)
	{
		if (p - p_b64str > ZBX_MAX_B64_LEN - 5)
			break;

		from1 = p_str[i];
		from2 = 0;
		from3 = 0;

		if (i+1 < in_size)
			from2 = p_str[i+1];
		if (i+2 < in_size)
			from3 = p_str[i+2];

		to1 = (from1 >> 2) & 0x3f;
		to2 = ((from1 & 0x3) << 4) | (from2 >> 4);
		to3 = ((from2 & 0xf) << 2) | (from3 >> 6);
		to4 = from3 & 0x3f;

		*p++ = char_base64_encode(to1);
		*p++ = char_base64_encode(to2);

		if (i+1 < in_size)
			*p++ = char_base64_encode(to3);
		else
			*p++ = '=';	/* padding */

		if (i+2 < in_size)
			*p++ = char_base64_encode(to4);
		else
			*p++ = '=';	/* padding */
	}

	*p = '\0';

	return;
}

/******************************************************************************
 *                                                                            *
 * Purpose: encodes string into base64 string with dynamic memory allocation  *
 *                                                                            *
 * Parameters: p_str    - [IN] string to encode                               *
 *             p_b64str - [OUT] pointer to encoded str to return              *
 *             in_size  - [IN] size (length) of input str                     *
 *                                                                            *
 * Comments: allocates memory                                                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_base64_encode_dyn(const char *p_str, char **p_b64str, int in_size)
{
	const char 	*pc;
	char		*pc_r;
	int		c_per_block;	/* number of bytes which can be encoded to place in the buffer per time */
	int		b_per_block;	/* bytes in the buffer to store 'c_per_block' encoded bytes */
	int		full_block_num;
	int		bytes_left;	/* less than 'c_per_block' bytes left */
	int		bytes_for_left;	/* bytes in the buffer to store 'bytes_left' encoded bytes */

	assert(p_str);
	assert(p_b64str);
	assert(!*p_b64str);	/* expect a pointer will NULL value, do not know whether allowed to free that memory */

	*p_b64str = (char *)zbx_malloc(*p_b64str, (in_size + 2) / 3 * 4 + 1);
	c_per_block = (ZBX_MAX_B64_LEN - 1) / 4 * 3;
	b_per_block = c_per_block / 3 * 4;
	full_block_num = in_size / c_per_block;
	bytes_left = in_size % c_per_block;
	bytes_for_left = (bytes_left + 2) / 3 * 4;

	for (pc = p_str, pc_r = *p_b64str; 0 != full_block_num; pc += c_per_block, pc_r += b_per_block, full_block_num--)
		zbx_base64_encode(pc, pc_r, c_per_block);

	if (0 != bytes_left)
	{
		zbx_base64_encode(pc, pc_r, bytes_left);
		pc_r += bytes_for_left;
	}

	*pc_r = '\0';
}

/******************************************************************************
 *                                                                            *
 * Purpose: decodes base64 string into string                                 *
 *                                                                            *
 * Parameters: p_b64str   - [IN] base64 string to decode                      *
 *             p_str      - [OUT] decoded str to return                       *
 *             maxsize    - [IN] size of p_str buffer                         *
 *             p_out_size - [OUT] size (length) of str decoded                *
 *                                                                            *
 ******************************************************************************/
void	zbx_base64_decode(const char *p_b64str, char *p_str, size_t maxsize, size_t *p_out_size)
{
	const char	*p;
	char		from[4];
	unsigned char	to[4];
	int		i = 0, j = 0;
	int		lasti = -1;	/* index of the last filled-in element of from[] */
	int		finished = 0;

	assert(p_b64str);
	assert(p_str);
	assert(p_out_size);
	assert(maxsize > 0);

	*p_out_size = 0;
	p = p_b64str;

	while (1)
	{
		if ('\0' != *p)
		{
			/* skip non-base64 characters */
			if (FAIL == is_base64(*p))
			{
				p++;
				continue;
			}

			/* collect up to 4 characters */
			from[i] = *p++;
			lasti = i;
			if (i < 3)
			{
				i++;
				continue;
			}
			else
				i = 0;
		}
		else	/* no more data to read */
		{
			finished = 1;
			for (j = lasti + 1; j < 4; j++)
				from[j] = 'A';
		}

		if (-1 != lasti)
		{
			/* decode a 4-character block */
			for (j = 0; j < 4; j++)
				to[j] = char_base64_decode(from[j]);

			if (1 <= lasti)	/* from[0], from[1] available */
			{
				*p_str++ = ((to[0] << 2) | (to[1] >> 4));
				if (++(*p_out_size) == maxsize)
					break;
			}

			if (2 <= lasti && '=' != from[2])	/* from[2] available */
			{
				*p_str++ = (((to[1] & 0xf) << 4) | (to[2] >> 2));
				if (++(*p_out_size) == maxsize)
					break;
			}

			if (3 == lasti && '=' != from[3])	/* from[3] available */
			{
				*p_str++ = (((to[2] & 0x3) << 6) | to[3]);
				if (++(*p_out_size) == maxsize)
					break;
			}
			lasti = -1;
		}

		if (1 == finished)
			break;
	}
}
