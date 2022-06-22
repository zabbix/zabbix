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
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "log.h"

#include "zbxnum.h"

double	ZBX_DOUBLE_EPSILON = 2.22e-16;

double	zbx_get_double_epsilon(void)
{
	return ZBX_DOUBLE_EPSILON;
}

void	zbx_update_epsilon_to_not_use_double_precision(void)
{
	ZBX_DOUBLE_EPSILON = 0.000001;
}

void	zbx_update_epsilon_to_python_compatible_precision(void)
{
	ZBX_DOUBLE_EPSILON = 0.0001;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if integer matches a list of integers                       *
 *                                                                            *
 * Parameters: list  - integers [i1-i2,i3,i4,i5-i6] (10-25,45,67-699)         *
 *             value - integer to check                                       *
 *                                                                            *
 * Return value: FAIL - out of period, SUCCEED - within the period            *
 *                                                                            *
 ******************************************************************************/
int	int_in_list(char *list, int value)
{
	char	*start = NULL, *end = NULL, c = '\0';
	int	i1, i2, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() list:'%s' value:%d", __func__, list, value);

	for (start = list; '\0' != *start;)
	{
		if (NULL != (end = strchr(start, ',')))
		{
			c = *end;
			*end = '\0';
		}

		if (2 == sscanf(start, "%d-%d", &i1, &i2))
		{
			if (i1 <= value && value <= i2)
			{
				ret = SUCCEED;
				break;
			}
		}
		else
		{
			if (value == atoi(start))
			{
				ret = SUCCEED;
				break;
			}
		}

		if (NULL != end)
		{
			*end = c;
			start = end + 1;
		}
		else
			break;
	}

	if (NULL != end)
		*end = c;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

int	zbx_double_compare(double a, double b)
{
	return fabs(a - b) <= ZBX_DOUBLE_EPSILON ? SUCCEED : FAIL;
}


static int	is_double_valid_syntax(const char *str)
{
	int	len;

	/* Valid syntax is a decimal number optionally followed by a decimal exponent. */
	/* Leading and trailing white space, NAN, INF and hexadecimal notation are not allowed. */

	if ('-' == *str || '+' == *str)		/* check leading sign */
		str++;

	if (FAIL == zbx_number_parse(str, &len))
		return FAIL;

	return '\0' == *(str + len) ? SUCCEED : FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: validate and optionally convert a string to a number of type      *
 *         'double'                                                           *
 *                                                                            *
 * Parameters: str   - [IN] string to check                                   *
 *             value - [OUT] output buffer where to write the converted value *
 *                     (optional, can be NULL)                                *
 *                                                                            *
 * Return value:  SUCCEED - the string can be converted to 'double' and       *
 *                          was converted if 'value' is not NULL              *
 *                FAIL - the string does not represent a valid 'double' or    *
 *                       its value is outside of valid range                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_is_double(const char *str, double *value)
{
	double	tmp;
	char	*endptr;

	/* Not all strings accepted by strtod() can be accepted in Zabbix. */
	/* Therefore additional, more strict syntax check is used before strtod(). */

	if (SUCCEED != is_double_valid_syntax(str))
		return FAIL;

	errno = 0;
	tmp = strtod(str, &endptr);

	if ('\0' != *endptr || HUGE_VAL == tmp || -HUGE_VAL == tmp || EDOM == errno)
		return FAIL;

	if (NULL != value)
		*value = tmp;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if the string is double                                     *
 *                                                                            *
 * Parameters: str   - string to check                                        *
 *             flags - extra options including:                               *
 *                       ZBX_FLAG_DOUBLE_SUFFIX - allow suffixes              *
 *                                                                            *
 * Return value:  SUCCEED - the string is double                              *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Comments: the function automatically processes suffixes K, M, G, T and     *
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
 * Purpose: converts double value to string and truncates insignificant       *
 *          precision                                                         *
 *                                                                            *
 * Parameters: buffer - [OUT] the output buffer                               *
 *             size   - [IN] the output buffer size                           *
 *             val    - [IN] double value to be converted                     *
 *                                                                            *
 * Return value: the output buffer with printed value                         *
 *                                                                            *
 ******************************************************************************/
const char	*zbx_print_double(char *buffer, size_t size, double val)
{
	zbx_snprintf(buffer, size, "%.15G", val);

	if (atof(buffer) != val)
		zbx_snprintf(buffer, size, ZBX_FS_DBL64, val);

	return buffer;
}

#if defined(_WINDOWS) || defined(__MINGW32__)
int	_wis_uint(const wchar_t *wide_string)
{
	const wchar_t	*wide_char = wide_string;

	if (L'\0' == *wide_char)
		return FAIL;

	while (L'\0' != *wide_char)
	{
		if (0 != iswdigit(*wide_char))
		{
			wide_char++;
			continue;
		}
		return FAIL;
	}

	return SUCCEED;
}
#endif


/******************************************************************************
 *                                                                            *
 * Purpose: parse a number like "12.345"                                      *
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

		if ('e' == number[*len] || 'E' == number[*len])
		{
			(*len)++;

			if ('-' == number[*len] || '+' == number[*len])
				(*len)++;

			if (0 == isdigit(number[*len]))
				return FAIL;

			while (0 != isdigit(number[++(*len)]));

			if ('.' == number[*len] ||'e' == number[*len] || 'E' == number[*len])
				return FAIL;
		}

		if (1 > digits || 1 < dots)
			return FAIL;

		return SUCCEED;
	}
}

/******************************************************************************
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
 * Purpose: check if the string is unsigned integer within the specified      *
 *          range and optionally store it into value parameter                *
 *                                                                            *
 * Parameters: str   - [IN] string to check                                   *
 *             n     - [IN] string length or ZBX_MAX_UINT64_LEN               *
 *             value - [OUT] a pointer to output buffer where the converted   *
 *                     value is to be written (optional, can be NULL)         *
 *             size  - [IN] size of the output buffer (optional)              *
 *             min   - [IN] the minimum acceptable value                      *
 *             max   - [IN] the maximum acceptable value                      *
 *                                                                            *
 * Return value:  SUCCEED - the string is unsigned integer                    *
 *                FAIL - the string is not a number or its value is outside   *
 *                       the specified range                                  *
 *                                                                            *
 ******************************************************************************/
int	is_uint_n_range(const char *str, size_t n, void *value, size_t size, zbx_uint64_t min, zbx_uint64_t max)
{
	zbx_uint64_t		value_uint64 = 0, c;
	const zbx_uint64_t	max_uint64 = ~(zbx_uint64_t)__UINT64_C(0);

	if ('\0' == *str || 0 == n || sizeof(zbx_uint64_t) < size || (0 == size && NULL != value))
		return FAIL;

	while ('\0' != *str && 0 < n--)
	{
		if (0 == isdigit(*str))
			return FAIL;	/* not a digit */

		c = (zbx_uint64_t)(unsigned char)(*str - '0');

		if ((max_uint64 - c) / 10 < value_uint64)
			return FAIL;	/* maximum value exceeded */

		value_uint64 = value_uint64 * 10 + c;

		str++;
	}

	if (min > value_uint64 || value_uint64 > max)
		return FAIL;

	if (NULL != value)
	{
		/* On little endian architecture the output value will be stored starting from the first bytes */
		/* of 'value' buffer while on big endian architecture it will be stored starting from the last */
		/* bytes. We handle it by storing the offset in the most significant byte of short value and   */
		/* then use the first byte as source offset.                                                   */
		unsigned short	value_offset = (unsigned short)((sizeof(zbx_uint64_t) - size) << 8);

		memcpy(value, (unsigned char *)&value_uint64 + *((unsigned char *)&value_offset), size);
	}

	return SUCCEED;
}


/******************************************************************************
 *                                                                            *
 * Purpose: convert string to 64bit unsigned integer                          *
 *                                                                            *
 * Parameters: str   - string to convert                                      *
 *             value - a pointer to converted value                           *
 *                                                                            *
 * Return value:  SUCCEED - the string is unsigned integer                    *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Comments: the function automatically processes suffixes K, M, G, T         *
 *                                                                            *
 ******************************************************************************/
int	str2uint64(const char *str, const char *suffixes, zbx_uint64_t *value)
{
	size_t		sz;
	const char	*p;
	int		ret;
	zbx_uint64_t	factor = 1;

	sz = strlen(str);
	p = str + sz - 1;

	if (NULL != strchr(suffixes, *p))
	{
		factor = suffix2factor(*p);

		sz--;
	}

	if (SUCCEED == (ret = is_uint64_n(str, sz, value)))
		*value *= factor;

	return ret;
}
