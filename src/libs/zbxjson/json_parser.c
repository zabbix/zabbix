/*
** Zabbix
** Copyright (C) 2000-2013 Zabbix SIA
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

#include "common.h"

#include "zbxjson.h"
#include "json_parser.h"

static int	json_parse_value(const char **start, const char **end, char **error);

/******************************************************************************
 *                                                                            *
 * Function: json_error                                                       *
 *                                                                            *
 * Purpose: Prepares JSON parsing error message                               *
 *                                                                            *
 * Parameters: message     - [IN] the error message                           *
 *             json_buffer - [IN] the failing data fragment                   *
 *             error       - [OUT] the parsing error message                  *
 *                                                                            *
 * Return value: FAIL - the json_error() function always returns FAIL value   *
 *                      so it can be used to return from failed parses        *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 ******************************************************************************/
static int	json_error(const char *message, const char* json_buffer, char** error)
{
	size_t	size = 1024, offset = 0;

	*error = zbx_malloc(NULL, size);

	if (json_buffer)
		zbx_snprintf_alloc(error, &size, &offset, "%s at: \"%s\"", message, json_buffer);
	else
		zbx_snprintf_alloc(error, &size, &offset, "%s", message);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: json_parse_string                                                *
 *                                                                            *
 * Purpose: Parses JSON string value or object name                           *
 *                                                                            *
 * Parameters: start - [IN] the JSON data without leading whitespace          *
 *             end   - [OUT] the reference to the string ending character '"' *
 *             error - [OUT] the parsing error message                        *
 *                                                                            *
 * Return value: SUCCEED - the string was parsed successfully                 *
 *               FAIL    - an error occurred during parsing, the error        *
 *                         parameter contains allocated error message         *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 ******************************************************************************/
static int	json_parse_string(const char *start, const char **end, char **error)
{
	const char	*ptr = start;

	/* skip starting '"' */
	ptr++;

	while ('"' != *ptr)
	{
		/* unexpected end of string data, failing */
		if ('\0' == *ptr)
			return json_error("unexpected end of string data", NULL, error);

		if ('\\' == *ptr)
		{
			const char	*escape_start = ptr;
			int		i;

			/* unexpected end of string data, failing */
			if ('\0' == *(++ptr))
				return json_error("invalid escape sequence in string", escape_start, error);

			switch (*ptr)
			{
				case '"':
				case '\\':
				case '/':
				case 'b':
				case 'f':
				case 'n':
				case 'r':
				case 't':
					break;
				case 'u':
					/* check if the \u is followed with 4 hex digits */
					for (i = 0; i < 4; i++)
					{
						if (0 == isxdigit(*(++ptr)))
						{
							return json_error("invalid escape sequence in string",
									escape_start, error);
						}
					}

					break;
				default:
					return json_error("invalid escape sequence in string data",
							escape_start, error);
			}
		}

		/* found control character in string, failing */
		if (0 != iscntrl(*ptr))
			return json_error("invalid control character in string data", ptr, error);

		ptr++;
	}
	*end = ptr;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: json_parse_array                                                 *
 *                                                                            *
 * Purpose: Parses JSON array value                                           *
 *                                                                            *
 * Parameters: start - [IN] the JSON data without leading whitespace          *
 *             end   - [OUT] the reference to the array ending character ']'  *
 *             error - [OUT] the parsing error message                        *
 *                                                                            *
 * Return value: SUCCEED - the array was parsed successfully                  *
 *               FAIL    - an error occurred during parsing, the error        *
 *                         parameter contains allocated error message         *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 ******************************************************************************/
static int	json_parse_array(const char *start, const char **end, char **error)
{
	const char	*ptr = start;

	do
	{
		/* skip the opening bracket '[' or element separator ',' */
		ptr++;

		/* json_parse_value strips leading whitespace, so we don't have to do it here */
		if (FAIL == json_parse_value(&ptr, end, error))
			return FAIL;

		ptr = *end + 1;

		STRIP_WHITESPACE(ptr);
	} while (',' == *ptr);

	/* no closing ], failing */
	if (']' != *ptr)
		return json_error("invalid array format, expected closing character ']'", ptr, error);

	*end = ptr;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: json_parse_number                                                *
 *                                                                            *
 * Purpose: Parses JSON number value                                          *
 *                                                                            *
 * Parameters: start - [IN] the JSON data without leading whitespace          *
 *             end   - [OUT] the reference to the number ending character     *
 *                           (last valid numeric value character              *
 *             error - [OUT] the parsing error message                        *
 *                                                                            *
 * Return value: SUCCEED - the number was parsed successfully                 *
 *               FAIL    - an error occurred during parsing, the error        *
 *                         parameter contains allocated error message         *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 ******************************************************************************/
static int	json_parse_number(const char *start, const char **end, char **error)
{
	const char	*ptr = start;
	int		point = 0, digit = 0;

	if ('-' == *ptr)
		ptr++;

	while ('\0' != *ptr)
	{
		if ('.' == *ptr)
		{
			if (0 != point)
				break;
			point = 1;
		}
		else if (0 == isdigit(*ptr))
			break;

		ptr++;
		digit++;
	}
	/* number does not contain any digits, failing */
	if (0 == digit)
		return json_error("invalid numeric value format", start, error);

	if ('e' == *ptr || 'E' == *ptr)
	{
		if ('\0' == *(++ptr))
			return json_error("unexpected end of numeric value", NULL, error);

		if ('+' == *ptr || '-' == *ptr)
		{
			if ('\0' == *(++ptr))
				return json_error("unexpected end of numeric value", NULL, error);
		}

		while ('\0' != *ptr)
		{
			if (0 == isdigit(*ptr))
				break;

			ptr++;
		}
	}
	*end = ptr - 1;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: json_parse_literal                                               *
 *                                                                            *
 * Purpose: Parses the specified literal value                                *
 *                                                                            *
 * Parameters: start - [IN] the JSON data without leading whitespace          *
 *             end   - [OUT] the reference to the literal ending character    *
 *             error - [OUT] the parsing error message                        *
 *                                                                            *
 * Return value: SUCCEED - the literal was parsed successfully                *
 *               FAIL    - an error occurred during parsing, the error        *
 *                         parameter contains allocated error message         *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 * Comments: This function is used to parse JSON literal values null, true    *
 *           false.                                                           *
 *                                                                            *
 ******************************************************************************/
static int	json_parse_literal(const char *start, const char **end, const char *text, char **error)
{
	const char	*ptr = start;

	while ('\0' != *text)
	{
		if (*ptr != *text)
			return json_error("invalid literal value", start, error);
		ptr++;
		text++;
	}
	*end = ptr - 1;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: json_parse_value                                                 *
 *                                                                            *
 * Purpose: Parses JSON object value                                          *
 *                                                                            *
 * Parameters: start - [IN/OUT] the JSON data; returns the reference the real *
 *                     data (without spaces)                                  *
 *             end   - [OUT] the reference to the value ending character      *
 *             error - [OUT] the parsing error message                        *
 *                                                                            *
 * Return value: SUCCEED - the value was parsed successfully                  *
 *               FAIL    - an error occurred during parsing, the error        *
 *                         parameter contains allocated error message         *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 ******************************************************************************/
static int	json_parse_value(const char **start, const char **end, char **error)
{
	const char	*ptr = *start;

	STRIP_WHITESPACE(ptr);

	*start = ptr;

	switch (*ptr)
	{
		case '\0':
			return json_error("unexpected end of object value", NULL, error);
		case '"':
			if (FAIL == json_parse_string(ptr, end, error))
				return FAIL;
			break;
		case '{':
			if (FAIL == json_parse_object(&ptr, end, error))
				return FAIL;
			break;
		case '[':
			if (FAIL == json_parse_array(ptr, end, error))
				return FAIL;
			break;
		case 't':
			if (FAIL == json_parse_literal(ptr, end, "true", error))
				return FAIL;
			break;
		case 'f':
			if (FAIL == json_parse_literal(ptr, end, "false", error))
				return FAIL;
			break;
		case 'n':
			if (FAIL == json_parse_literal(ptr, end, "null", error))
				return FAIL;
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
		case '-':
			if (FAIL == json_parse_number(ptr, end, error))
				return FAIL;
			break;
		default:
			return json_error("invalid JSON object value starting character", ptr, error);
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: json_parse_object                                                *
 *                                                                            *
 * Purpose: Parses JSON object                                                *
 *                                                                            *
 * Parameters: start - [IN/OUT] the JSON data; returns the reference the real *
 *                     data (without spaces)                                  *
 *             end   - [OUT] the reference to the object ending character '}' *
 *             error - [OUT] the parsing error message                        *
 *                                                                            *
 * Return value: SUCCEED - the object was parsed successfully                 *
 *               FAIL    - an error occurred during parsing, the error        *
 *                         parameter contains allocated error message         *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 ******************************************************************************/
int	json_parse_object(const char **start, const char **end, char **error)
{
	const char	*ptr = *start;

	/* parse object name */
	STRIP_WHITESPACE(ptr);

	*start = ptr;

	/* not an object, failing */
	if ('{' != *ptr)
		return json_error("invalid object format, expected opening character '{'", ptr, error);

	do
	{
		/* skip the opening bracket '{' or element separator ',' */
		ptr++;

		STRIP_WHITESPACE(ptr);

		/* cannot parse object name, failing */
		if (FAIL == json_parse_string(ptr, end, error))
			return FAIL;

		ptr = *end + 1;

		/* parse name:value separator */
		STRIP_WHITESPACE(ptr);

		if (':' != *ptr)
			return json_error("invalid object name/value separator", ptr, error);
		ptr++;

		if (FAIL == json_parse_value(&ptr, end, error))
			return FAIL;

		ptr = *end + 1;

		STRIP_WHITESPACE(ptr);
	}
	while (',' == *ptr);

	/* object is not properly closed, failing */
	if ('}' != *ptr)
		return json_error("invalid object format, expected closing character '}'", ptr, error);

	*end = ptr;

	return SUCCEED;
}
