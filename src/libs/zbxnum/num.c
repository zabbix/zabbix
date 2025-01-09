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

#include "zbxnum.h"

/******************************************************************************
 *                                                                            *
 * Purpose: Checks if the string is an unsigned integer within the specified  *
 *          range and optionally stores it into the value parameter.          *
 *                                                                            *
 * Parameters: str   - [IN] string to check                                   *
 *             n     - [IN] string length or ZBX_MAX_UINT64_LEN               *
 *             value - [OUT] pointer to output buffer where converted value   *
 *                           is to be written (optional, can be NULL)         *
 *             size  - [IN] size of output buffer (optional)                  *
 *             min   - [IN] minimum acceptable value                          *
 *             max   - [IN] maximum acceptable value                          *
 *                                                                            *
 * Return value:  SUCCEED - string is unsigned integer                        *
 *                FAIL    - string is not number or its value is outside      *
 *                          specified range                                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_is_uint_n_range(const char *str, size_t n, void *value, size_t size, zbx_uint64_t min, zbx_uint64_t max)
{
	zbx_uint64_t		value_uint64 = 0, c;
	const zbx_uint64_t	max_uint64 = ~__UINT64_C(0);

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
 * Purpose: Checks if the string is an unsigned hexadecimal integer within    *
 *          the specified range and optionally stores it into the value       *
 *          parameter.                                                        *
 *                                                                            *
 * Parameters: str   - [IN] string to check                                   *
 *             n     - [IN] string length                                     *
 *             value - [OUT] pointer to output buffer where converted value   *
 *                           is to be written (optional, can be NULL)         *
 *             size  - [IN] size of output buffer (optional)                  *
 *             min   - [IN] minimum acceptable value                          *
 *             max   - [IN] maximum acceptable value                          *
 *                                                                            *
 * Return value:  SUCCEED - string is unsigned integer                        *
 *                FAIL    - string is not hexadecimal number or its value is  *
 *                          outside specified range                           *
 *                                                                            *
 ******************************************************************************/
int	zbx_is_hex_n_range(const char *str, size_t n, void *value, size_t size, zbx_uint64_t min, zbx_uint64_t max)
{
	zbx_uint64_t		value_uint64 = 0, c;
	const zbx_uint64_t	max_uint64 = ~__UINT64_C(0);
	int			len = 0;

	if ('\0' == *str || 0 == n || sizeof(zbx_uint64_t) < size || (0 == size && NULL != value))
		return FAIL;

	while ('\0' != *str && 0 < n--)
	{
		if ('0' <= *str && *str <= '9')
			c = *str - '0';
		else if ('a' <= *str && *str <= 'f')
			c = 10 + (*str - 'a');
		else if ('A' <= *str && *str <= 'F')
			c = 10 + (*str - 'A');
		else
			return FAIL;	/* not a hexadecimal digit */

		if (16 < ++len && (max_uint64 >> 4) < value_uint64)
			return FAIL;	/* maximum value exceeded */

		value_uint64 = (value_uint64 << 4) + c;

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

static double	ZBX_FLOAT_EPSILON = 0.0001;
static double	ZBX_DOUBLE_EPSILON = 2.22e-16;

double	zbx_get_float_epsilon(void)
{
	return ZBX_FLOAT_EPSILON;
}

double	zbx_get_double_epsilon(void)
{
	return ZBX_DOUBLE_EPSILON;
}

void	zbx_update_epsilon_to_float_precision(void)
{
	ZBX_DOUBLE_EPSILON = 0.000001;
}

void	zbx_update_epsilon_to_python_compatible_precision(void)
{
	ZBX_DOUBLE_EPSILON = 0.0001;
}

int	zbx_double_compare(double a, double b)
{
	return fabs(a - b) <= ZBX_DOUBLE_EPSILON ? SUCCEED : FAIL;
}

int	zbx_validate_value_dbl(double value)
{
	if (value < -1e+308 || value > 1e+308)
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if integer matches list of integers                        *
 *                                                                            *
 * Parameters: list  - [IN] integers [i1-i2,i3,i4,i5-i6] (10-25,45,67-699)    *
 *             value - [IN] integer to check                                  *
 *                                                                            *
 * Return value: FAIL - out of period, SUCCEED - within period                *
 *                                                                            *
 ******************************************************************************/
int	zbx_int_in_list(char *list, int value)
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
 * Purpose: validates and optionally converts string to number of type        *
 *          'double'                                                          *
 *                                                                            *
 * Parameters: str   - [IN] string to check                                   *
 *             value - [OUT] output buffer where to write converted value     *
 *                           (optional, can be NULL)                          *
 *                                                                            *
 * Return value:  SUCCEED - string can be converted to 'double' and           *
 *                          was converted if 'value' is not NULL              *
 *                FAIL    - string does not represent valid 'double' or its   *
 *                          value is outside of valid range                   *
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

#if defined(_WINDOWS) || defined(__MINGW32__)
int	zbx_wis_uint(const wchar_t *wide_string)
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
 * Purpose: converts double value to string and truncates insignificant       *
 *          precision                                                         *
 *                                                                            *
 * Parameters: buffer - [OUT]                                                 *
 *             size   - [IN] output buffer size                               *
 *             val    - [IN] double value to be converted                     *
 *                                                                            *
 * Return value: output buffer with printed value                             *
 *                                                                            *
 ******************************************************************************/
const char	*zbx_print_double(char *buffer, size_t size, double val)
{
	double	ipart;

	if (0.0 == modf(val, &ipart))
	{
		zbx_snprintf(buffer, size, ZBX_FS_DBL64, val);
	}
	else
	{
		zbx_snprintf(buffer, size, "%.15G", val);

		if (atof(buffer) != val)
			zbx_snprintf(buffer, size, ZBX_FS_DBL64, val);
	}

	return buffer;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parses number like "12.345"                                       *
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
 * Purpose: converts string to 64bit unsigned integer                         *
 *                                                                            *
 * Parameters: str      - [IN] string to convert                              *
 *             suffixes - [IN]                                                *
 *             value    - [OUT] pointer to converted value                    *
 *                                                                            *
 * Return value:  SUCCEED - string is unsigned integer                        *
 *                FAIL    - otherwise                                         *
 *                                                                            *
 * Comments: function automatically processes suffixes K, M, G, T             *
 *                                                                            *
 ******************************************************************************/
int	zbx_str2uint64(const char *str, const char *suffixes, zbx_uint64_t *value)
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

	if (SUCCEED == (ret = zbx_is_uint64_n(str, sz, value)))
		*value *= factor;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Removes spaces from both ends of the string, then unquotes it if  *
 *          double quotation mark is present on both ends of the string. If   *
 *          strip_plus_sign is non-zero, then removes single "+" sign from    *
 *          the beginning of the trimmed and unquoted string.                 *
 *                                                                            *
 *          This function does not guarantee that the resulting string        *
 *          contains numeric value. It is meant to be used for removing       *
 *          "valid" characters from the value that is expected to be numeric  *
 *          before checking if value is numeric.                              *
 *                                                                            *
 * Parameters: str             - [IN/OUT] string for processing               *
 *             strip_plus_sign - [IN] non-zero if "+" should be stripped      *
 *                                                                            *
 ******************************************************************************/
static void	zbx_trim_number(char *str, int strip_plus_sign)
{
	char	*left = str;			/* pointer to the first character */
	char	*right = strchr(str, '\0') - 1; /* pointer to the last character, not including terminating null-char */

	if (left > right)
	{
		/* string is empty before any trimming */
		return;
	}

	while (' ' == *left)
	{
		left++;
	}

	while (' ' == *right && left < right)
	{
		right--;
	}

	if ('"' == *left && '"' == *right && left < right)
	{
		left++;
		right--;
	}

	if (0 != strip_plus_sign && '+' == *left)
	{
		left++;
	}

	if (left > right)
	{
		/* string is empty after trimming */
		*str = '\0';
		return;
	}

	if (str < left)
	{
		while (left <= right)
		{
			*str++ = *left++;
		}
		*str = '\0';
	}
	else
	{
		*(right + 1) = '\0';
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: Removes spaces from both ends of the string, then unquotes it if  *
 *          double quotation mark is present on both ends of the string, then *
 *          removes single "+" sign from the beginning of the trimmed and     *
 *          unquoted string.                                                  *
 *                                                                            *
 *          This function does not guarantee that the resulting string        *
 *          contains integer value. It is meant to be used for removing       *
 *          "valid" characters from the value that is expected to be numeric  *
 *          before checking if value is numeric.                              *
 *                                                                            *
 * Parameters: str - [IN/OUT] string for processing                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_trim_integer(char *str)
{
	zbx_trim_number(str, 1);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Removes spaces from both ends of the string, then unquotes it if  *
 *          double quotation mark is present on both ends of the string.      *
 *                                                                            *
 *          This function does not guarantee that the resulting string        *
 *          contains floating-point number. It is meant to be used for        *
 *          removing "valid" characters from the value that is expected to be *
 *          numeric before checking if value is numeric.                      *
 *                                                                            *
 * Parameters: str - [IN/OUT] string for processing                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_trim_float(char *str)
{
	zbx_trim_number(str, 0);
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if the string is a hexadecimal representation of data in   *
 *          the form "F4 CE 46 01 0C 44 8B F4\nA0 2C 29 74 5D 3F 13 49\n"     *
 *                                                                            *
 * Parameters: str - [IN] string to check                                     *
 *                                                                            *
 * Return value:  SUCCEED - string is formatted like the example above        *
 *                FAIL    - otherwise                                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_is_hex_string(const char *str)
{
	if ('\0' == *str)
		return FAIL;

	while ('\0' != *str)
	{
		if (0 == isxdigit(*str))
			return FAIL;

		if (0 == isxdigit(*(str + 1)))
			return FAIL;

		if ('\0' == *(str + 2))
			break;

		if (' ' != *(str + 2) && '\n' != *(str + 2))
			return FAIL;

		str += 3;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: validates and optionally converts string to number of type 'int'  *
 *                                                                            *
 * Parameters: str   - [IN] string to check                                   *
 *             value - [OUT] output buffer where to write converted value     *
 *                           (optional, can be NULL)                          *
 *                                                                            *
 * Return value:  SUCCEED - string can be converted to 'int' and              *
 *                          was converted if 'value' is not NULL              *
 *                FAIL    - string does not represent valid 'int' or          *
 *                          its value is outside of valid range               *
 *                                                                            *
 ******************************************************************************/
int	zbx_is_int(const char *str, int *value)
{
	const char	*ptr;
	zbx_uint32_t	value_ui32;
	int		sign;

	if ('-' == *(ptr = str))
	{
		ptr++;
		sign = -1;
	}
	else
		sign = 1;

	if (SUCCEED != zbx_is_uint31(ptr, &value_ui32))
		return FAIL;

	if (NULL != value)
		*value = ((int)value_ui32 * sign);

	return SUCCEED;
}
