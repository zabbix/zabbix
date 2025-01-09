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

#include "zbxexpr.h"

#include "zbxnum.h"

/******************************************************************************
 *                                                                            *
 * Return value:  SUCCEED - char is allowed in item key                       *
 *                FAIL    - otherwise                                         *
 *                                                                            *
 * Comments: allowed characters in key are: '0-9a-zA-Z._-'                    *
 *           !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_is_key_char(unsigned char c)
{
	if (0 != isalnum(c))
		return SUCCEED;

	if (c == '.' || c == '_' || c == '-')
		return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Advances pointer to first invalid character in string             *
 *          ensuring that everything before it is a valid key.                *
 *                                                                            *
 *  e.g., system.run[cat /etc/passwd | awk -F: '{ print $1 }']                *
 *                                                                            *
 * Parameters: exp - [IN/OUT] pointer to first char of key                    *
 *                                                                            *
 *  e.g., {host:system.run[cat /etc/passwd | awk -F: '{ print $1 }'].last(0)} *
 *              ^                                                             *
 * Return value: Returns FAIL only if no key is present (length 0),           *
 *               or the whole string is invalid. SUCCEED otherwise.           *
 *                                                                            *
 * Comments: The pointer is advanced to the first invalid character even if   *
 *           FAIL is returned (meaning there is a syntax error in item key).  *
 *           If necessary, the caller must keep a copy of pointer original    *
 *           value.                                                           *
 *                                                                            *
 ******************************************************************************/
int	zbx_parse_key(const char **exp)
{
	const char	*s;

	for (s = *exp; SUCCEED == zbx_is_key_char(*s); s++)
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
					else if ('"' == *s)
						state = 1;
					else if ('[' == *s)
					{
						if (0 == array)
							array = 1;
						else
							goto fail;	/* incorrect syntax: multi-level array */
					}
					else if (']' == *s && 0 != array)
					{
						array = 0;
						s++;

						while (' ' == *s)	/* skip trailing spaces after closing ']' */
							s++;

						if (']' == *s)
							goto succeed;

						if (',' != *s)
							goto fail;	/* incorrect syntax */
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
						while (' ' == s[1])	/* skip trailing spaces after closing quotes */
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

						state = 0;
					}
					else if ('\\' == *s && '"' == s[1])
						s++;
					break;
				/* unquoted */
				case 2:
					if (',' == *s || (']' == *s && 0 != array))
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
 * Purpose: checks if string is double                                        *
 *                                                                            *
 * Parameters: str   - [IN] string to check                                   *
 *             flags - [IN] extra options including:                          *
 *                          ZBX_FLAG_DOUBLE_SUFFIX - allow suffixes           *
 *                                                                            *
 * Return value:  SUCCEED - string is double                                  *
 *                FAIL    - otherwise                                         *
 *                                                                            *
 * Comments: function automatically processes suffixes K, M, G, T and         *
 *           s, m, h, d, w                                                    *
 *                                                                            *
 ******************************************************************************/
int	zbx_is_double_suffix(const char *str, unsigned char flags)
{
	int	len;

	if ('-' == *str)	/* check leading sign */
		str++;

	if (FAIL == zbx_number_parse(str, &len))
		return FAIL;

	if ('\0' != *(str += len) && 0 != (flags & ZBX_FLAG_DOUBLE_SUFFIX) && NULL != strchr(ZBX_UNIT_SYMBOLS, *str))
		str++;		/* allow valid suffix if flag is enabled */

	return '\0' == *str ? SUCCEED : FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: converts string to double                                         *
 *                                                                            *
 * Parameters: str - [IN] string to convert                                   *
 *                                                                            *
 * Return value: converted double value                                       *
 *                                                                            *
 * Comments: the function automatically processes suffixes K, M, G, T and     *
 *           s, m, h, d, w                                                    *
 *                                                                            *
 ******************************************************************************/
double	zbx_str2double(const char *str)
{
	size_t	sz;

	sz = strlen(str) - 1;

	return atof(str) * suffix2factor(str[sz]);
}

/******************************************************************************
 *                                                                            *
 * Purpose: parses a suffixed number like "12.345K"                           *
 *                                                                            *
 * Parameters: number - [IN] start of number                                  *
 *             len    - [OUT] length of parsed number                         *
 *                                                                            *
 * Return value: SUCCEED - number was parsed successfully                     *
 *               FAIL    - invalid number                                     *
 *                                                                            *
 * Comments: !!! Don't forget to sync the code with PHP !!!                   *
 *           The token field locations are specified as offsets from the      *
 *           beginning of the expression.                                     *
 *                                                                            *
 ******************************************************************************/
int	zbx_suffixed_number_parse(const char *number, int *len)
{
	if (FAIL == zbx_number_parse(number, len))
		return FAIL;

	if (0 != isalpha(number[*len]) && NULL != strchr(ZBX_UNIT_SYMBOLS, number[*len]))
		(*len)++;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if pattern matches specified value                         *
 *                                                                            *
 * Parameters: value    - [IN] value to match                                 *
 *             pattern  - [IN] pattern to match                               *
 *             op       - [IN] matching operator                              *
 *                                                                            *
 * Return value: SUCCEED - matches, FAIL - otherwise                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_strmatch_condition(const char *value, const char *pattern, unsigned char op)
{
	int	ret = FAIL;

	switch (op)
	{
		case ZBX_CONDITION_OPERATOR_EQUAL:
			if (0 == strcmp(value, pattern))
				ret = SUCCEED;
			break;
		case ZBX_CONDITION_OPERATOR_NOT_EQUAL:
			if (0 != strcmp(value, pattern))
				ret = SUCCEED;
			break;
		case ZBX_CONDITION_OPERATOR_LIKE:
			if (NULL != strstr(value, pattern))
				ret = SUCCEED;
			break;
		case ZBX_CONDITION_OPERATOR_NOT_LIKE:
			if (NULL == strstr(value, pattern))
				ret = SUCCEED;
			break;
	}

	return ret;
}

int	zbx_uint64match_condition(zbx_uint64_t value, zbx_uint64_t pattern, unsigned char op)
{
	int	ret = FAIL;

	switch (op)
	{
		case ZBX_CONDITION_OPERATOR_EQUAL:
			if (value == pattern)
				ret = SUCCEED;
			break;
		case ZBX_CONDITION_OPERATOR_NOT_EQUAL:
			if (value != pattern)
				ret = SUCCEED;
			break;
	}

	return ret;
}
