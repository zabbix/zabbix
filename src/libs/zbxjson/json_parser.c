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

#include "log.h"

static int	json_parse_value(const char *start, char **error);
static int	json_parse_object(const char *start, char **error);

/******************************************************************************
 *                                                                            *
 * Function: json_error                                                       *
 *                                                                            *
 * Purpose: Prepares JSON parsing error message                               *
 *                                                                            *
 * Parameters: message     - [IN] the error message                           *
 *             json_buffer - [IN] the failing data fragment                   *
 *             error       - [OUT] the parsing error message (can be NULL)    *
 *                                                                            *
 * Return value: 0 - the json_error() function always returns 0 value         *
 *                      so it can be used to return from failed parses        *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 ******************************************************************************/
static int	json_error(const char *message, const char* json_buffer, char** error)
{
	size_t	size = 1024, offset = 0;

	if (NULL != error)
	{
		*error = zbx_malloc(*error, size);

		if (NULL != json_buffer)
			zbx_snprintf_alloc(error, &size, &offset, "%s at: '%s'", message, json_buffer);
		else
			zbx_snprintf_alloc(error, &size, &offset, "%s", message);
	}

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: json_parse_string                                                *
 *                                                                            *
 * Purpose: Parses JSON string value or object name                           *
 *                                                                            *
 * Parameters: start - [IN] the JSON data without leading whitespace          *
 *             error - [OUT] the parsing error message (can be NULL)          *
 *                                                                            *
 * Return value: the number of characters parsed. On error 0 is returned and  *
 *               error parameter (if not NULL) contains allocated error       *
 *               message.                                                     *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 ******************************************************************************/
static int	json_parse_string(const char *start, char **error)
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

	return ptr - start + 1;
}

/******************************************************************************
 *                                                                            *
 * Function: json_parse_array                                                 *
 *                                                                            *
 * Purpose: Parses JSON array value                                           *
 *                                                                            *
 * Parameters: start - [IN] the JSON data without leading whitespace          *
 *             error - [OUT] the parsing error message (can be NULL)          *
 *                                                                            *
 * Return value: the number of characters parsed. On error 0 is returned and  *
 *               error parameter (if not NULL) contains allocated error       *
 *               message.                                                     *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 ******************************************************************************/
static int	json_parse_array(const char *start, char **error)
{
	const char	*ptr = start;
	int		len;

	ptr++;
	SKIP_WHITESPACE(ptr);

	if (']' != *ptr)
	{
		while (1)
		{
			/* json_parse_value strips leading whitespace, so we don't have to do it here */
			if (0 == (len = json_parse_value(ptr, error)))
				return 0;

			ptr += len;
			SKIP_WHITESPACE(ptr);

			if (',' != *ptr)
				break;

			ptr++;
		}

		/* no closing ], failing */
		if (']' != *ptr)
			return json_error("invalid array format, expected closing character ']'", ptr, error);
	}

	return ptr - start + 1;
}

/******************************************************************************
 *                                                                            *
 * Function: json_parse_number                                                *
 *                                                                            *
 * Purpose: Parses JSON number value                                          *
 *                                                                            *
 * Parameters: start - [IN] the JSON data without leading whitespace          *
 *             error - [OUT] the parsing error message (can be NULL)          *
 *                                                                            *
 * Return value: the number of characters parsed. On error 0 is returned and  *
 *               error parameter (if not NULL) contains allocated error       *
 *               message.                                                     *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 ******************************************************************************/
static int	json_parse_number(const char *start, char **error)
{
	const char	*ptr = start;
	char		first_digit;
	int		point = 0, digit = 0;

	if ('-' == *ptr)
		ptr++;

	first_digit = *ptr;

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
		if (0 == point)
			digit++;
	}

	/* number does not contain any digits, failing */
	if (0 == digit)
		return json_error("invalid numeric value format", start, error);

	/* number has zero leading digit following by other digits, failing */
	if ('0' == first_digit && 1 < digit)
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

		if (0 == isdigit(*ptr))
			return json_error("invalid power value of number in E notation", ptr, error);

		while ('\0' != *(++ptr))
		{
			if (0 == isdigit(*ptr))
				break;
		}
	}

	return ptr - start;
}

/******************************************************************************
 *                                                                            *
 * Function: json_parse_literal                                               *
 *                                                                            *
 * Purpose: Parses the specified literal value                                *
 *                                                                            *
 * Parameters: start - [IN] the JSON data without leading whitespace          *
 *             text  - [IN] the literal value to parse                        *
 *             error - [OUT] the parsing error message (can be NULL)          *
 *                                                                            *
 * Return value: the number of characters parsed. On error 0 is returned and  *
 *               error parameter (if not NULL) contains allocated error       *
 *               message.                                                     *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 * Comments: This function is used to parse JSON literal values null, true    *
 *           false.                                                           *
 *                                                                            *
 ******************************************************************************/
static int	json_parse_literal(const char *start, const char *text, char **error)
{
	const char	*ptr = start;

	while ('\0' != *text)
	{
		if (*ptr != *text)
			return json_error("invalid literal value", start, error);
		ptr++;
		text++;
	}

	return ptr - start;
}

/******************************************************************************
 *                                                                            *
 * Function: json_parse_value                                                 *
 *                                                                            *
 * Purpose: Parses JSON object value                                          *
 *                                                                            *
 * Parameters: start - [IN/OUT] the JSON data; returns the reference the real *
 *                     data (without spaces)                                  *
 *             error - [OUT] the parsing error message (can be NULL)          *
 *                                                                            *
 * Return value: the number of characters parsed. On error 0 is returned and  *
 *               error parameter (if not NULL) contains allocated error       *
 *               message.                                                     *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 ******************************************************************************/
static int	json_parse_value(const char *start, char **error)
{
	const char	*ptr = start;
	int		len = 0;

	SKIP_WHITESPACE(ptr);

	switch (*ptr)
	{
		case '\0':
			return json_error("unexpected end of object value", NULL, error);
		case '"':
			if (0 == (len = json_parse_string(ptr, error)))
				return 0;
			break;
		case '{':
			if (0 == (len = json_parse_object(ptr, error)))
				return 0;
			break;
		case '[':
			if (0 == (len = json_parse_array(ptr, error)))
				return 0;
			break;
		case 't':
			if (0 == (len = json_parse_literal(ptr, "true", error)))
				return 0;
			break;
		case 'f':
			if (0 == (len = json_parse_literal(ptr, "false", error)))
				return 0;
			break;
		case 'n':
			if (0 == (len = json_parse_literal(ptr, "null", error)))
				return 0;
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
			if (0 == (len = json_parse_number(ptr, error)))
				return 0;
			break;
		default:
			return json_error("invalid JSON object value starting character", ptr, error);
	}

	return ptr - start + len;
}

/******************************************************************************
 *                                                                            *
 * Function: json_parse_object                                                *
 *                                                                            *
 * Purpose: Parses JSON object                                                *
 *                                                                            *
 * Parameters: start - [IN/OUT] the JSON data; returns the reference the real *
 *                     data (without spaces)                                  *
 *             error - [OUT] the parsing error message (can be NULL)          *
 *                                                                            *
 * Return value: the number of characters parsed. On error 0 is returned and  *
 *               error parameter (if not NULL) contains allocated error       *
 *               message.                                                     *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 ******************************************************************************/
static int	json_parse_object(const char *start, char **error)
{
	const char	*ptr = start;
	int		len;

	/* parse object name */
	SKIP_WHITESPACE(ptr);

	/* not an object, failing */
	if ('{' != *ptr)
		return json_error("invalid object format, expected opening character '{'", ptr, error);

	ptr++;
	SKIP_WHITESPACE(ptr);

	if ('}' != *ptr)
	{
		while (1)
		{
			if ('"' != *ptr)
				return json_error("invalid object name", ptr, error);

			/* cannot parse object name, failing */
			if (0 == (len = json_parse_string(ptr, error)))
				return 0;

			ptr += len;

			/* parse name:value separator */
			SKIP_WHITESPACE(ptr);

			if (':' != *ptr)
				return json_error("invalid object name/value separator", ptr, error);
			ptr++;

			if (0 == (len = json_parse_value(ptr, error)))
				return 0;

			ptr += len;

			SKIP_WHITESPACE(ptr);

			if (',' != *ptr)
				break;

			ptr++;
			SKIP_WHITESPACE(ptr);
		}

		/* object is not properly closed, failing */
		if ('}' != *ptr)
			return json_error("invalid object format, expected closing character '}'", ptr, error);
	}

	return ptr - start + 1;
}


/******************************************************************************
 *                                                                            *
 * Function: zbx_json_validate                                                *
 *                                                                            *
 * Purpose: Validates JSON object                                             *
 *                                                                            *
 * Parameters: start  - [IN]  the string to validate                          *
 *             error  - [OUT] the parse error message. If the error value is  *
 *                            set it must be freed by caller after it has     *
 *                            been used (can be NULL).                        *
 *                                                                            *
 * Return value: the number of characters parsed. On error 0 is returned and  *
 *               error parameter (if not NULL) contains allocated error       *
 *               message.                                                     *
 *                                                                            *
 * Author: Andris Zeila                                                       *
 *                                                                            *
 ******************************************************************************/
int	zbx_json_validate(const char *start, char **error)
{
	int	len;

	if (0 == (len = json_parse_object(start, error)))
		return 0;

	start += len;
	SKIP_WHITESPACE(start);

	if ('\0' != *start)
		return json_error("invalid character following JSON object", start, error);

	return len;
}

