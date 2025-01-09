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

#include "json_parser.h"

#include "json.h"
#include "jsonobj.h"

#include "zbxalgo.h"

/******************************************************************************
 *                                                                            *
 * Purpose: Prepares JSON parsing error message                               *
 *                                                                            *
 * Parameters: message - [IN] the error message                               *
 *             ptr     - [IN] the failing data fragment                       *
 *             error   - [OUT] the parsing error message (can be NULL)        *
 *                                                                            *
 * Return value: 0 - the json_error() function always returns 0 value         *
 *                      so it can be used to return from failed parses        *
 *                                                                            *
 ******************************************************************************/
zbx_int64_t	json_error(const char *message, const char *ptr, char **error)
{
	if (NULL != error)
	{
		if (NULL != ptr)
		{
			if (128 < strlen(ptr))
				*error = zbx_dsprintf(*error, "%s at: '%128s...'", message, ptr);
			else
				*error = zbx_dsprintf(*error, "%s at: '%s'", message, ptr);
		}
		else
			*error = zbx_strdup(*error, message);
	}

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Parses JSON string value or object name                           *
 *                                                                            *
 * Parameters: start - [IN] the JSON data without leading whitespace          *
 *             str   - [OUT] the parsed unquoted string (can be NULL)         *
 *             error - [OUT] the parsing error message (can be NULL)          *
 *                                                                            *
 * Return value: The number of characters parsed. On error 0 is returned and  *
 *               error parameter (if not NULL) contains allocated error       *
 *               message.                                                     *
 *                                                                            *
 ******************************************************************************/
static zbx_int64_t	json_parse_string(const char *start, char **str, char **error)
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
			unsigned char	uc[4];	/* decoded Unicode character takes 1-4 bytes in UTF-8 */

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
					if (0 == zbx_json_decode_character(&ptr, uc))
					{
						return json_error("invalid escape sequence in string",
								escape_start, error);
					}

					continue;
				default:
					return json_error("invalid escape sequence in string data",
							escape_start, error);
			}
		}

		/* Control character U+0000 - U+001F? It should have been escaped according to RFC 8259. */
		if (0x1f >= (unsigned char)*ptr)
			return json_error("invalid control character in string data", ptr, error);

		ptr++;
	}

	if (NULL != str)
	{
		*str = (char *)zbx_malloc(NULL, (size_t)(ptr - start));

		if (NULL == json_copy_string(start, *str, (size_t)(ptr - start)))
		{
			zbx_free(*str);
			return json_error("invalid string data", start, error);
		}
	}

	return ptr - start + 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Parses JSON array value                                           *
 *                                                                            *
 * Parameters: start - [IN] the JSON data without leading whitespace          *
 *             obj   - [IN/OUT] the JSON object (can be NULL)                 *
 *             depth - [IN]                                                   *
 *             error - [OUT] the parsing error message (can be NULL)          *
 *                                                                            *
 * Return value: The number of characters parsed. On error 0 is returned and  *
 *               error parameter (if not NULL) contains allocated error       *
 *               message.                                                     *
 *                                                                            *
 ******************************************************************************/
zbx_int64_t	json_parse_array(const char *start, zbx_jsonobj_t *obj, int depth, char **error)
{
	const char	*ptr = start;
	zbx_int64_t	len;

	if (NULL != obj)
		jsonobj_init(obj, ZBX_JSON_TYPE_ARRAY);

	ptr++;
	SKIP_WHITESPACE(ptr);

	if (']' != *ptr)
	{
		while (1)
		{
			zbx_jsonobj_t	*value;

			if (NULL != obj)
			{
				value = zbx_malloc(NULL, sizeof(zbx_jsonobj_t));
				jsonobj_init(value, ZBX_JSON_TYPE_UNKNOWN);
			}
			else
				value = NULL;

			/* json_parse_value strips leading whitespace, so we don't have to do it here */
			if (0 == (len = json_parse_value(ptr, value, depth, error)))
			{
				if (NULL != obj)
				{
					zbx_jsonobj_clear(value);
					zbx_free(value);
				}
				return 0;
			}

			if (NULL != obj)
				zbx_vector_jsonobj_ptr_append(&obj->data.array, value);

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
 * Purpose: Parses JSON number value                                          *
 *                                                                            *
 * Parameters: start  - [IN] the JSON data without leading whitespace         *
 *             number - [OUT] the parsed number (can be NULL)                 *
 *             error  - [OUT] the parsing error message (can be NULL)         *
 *                                                                            *
 * Return value: The number of characters parsed. On error 0 is returned and  *
 *               error parameter (if not NULL) contains allocated error       *
 *               message.                                                     *
 *                                                                            *
 ******************************************************************************/
static zbx_int64_t	json_parse_number(const char *start, double *number, char **error)
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
		else if (0 == isdigit((unsigned char)*ptr))
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

		if (0 == isdigit((unsigned char)*ptr))
			return json_error("invalid power value of number in E notation", ptr, error);

		while ('\0' != *(++ptr))
		{
			if (0 == isdigit((unsigned char)*ptr))
				break;
		}
	}

	if (NULL != number)
		*number = atof(start);

	return ptr - start;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Parses the specified literal value                                *
 *                                                                            *
 * Parameters: start - [IN] the JSON data without leading whitespace          *
 *             text  - [IN] the literal value to parse                        *
 *             error - [OUT] the parsing error message (can be NULL)          *
 *                                                                            *
 * Return value: The number of characters parsed. On error 0 is returned and  *
 *               error parameter (if not NULL) contains allocated error       *
 *               message.                                                     *
 *                                                                            *
 * Comments: This function is used to parse JSON literal values null, true    *
 *           false.                                                           *
 *                                                                            *
 ******************************************************************************/
static zbx_int64_t	json_parse_literal(const char *start, const char *text, char **error)
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
 * Purpose: Parses JSON object value                                          *
 *                                                                            *
 * Parameters: start - [IN] the JSON data                                     *
 *             obj   - [IN/OUT] JSON object (can be NULL)                     *
 *             depth - [IN]                                                   *
 *             error - [OUT] the parsing error message (can be NULL)          *
 *                                                                            *
 * Return value: The number of characters parsed. On error 0 is returned and  *
 *               error parameter (if not NULL) contains allocated error       *
 *               message.                                                     *
 *                                                                            *
 ******************************************************************************/
zbx_int64_t	json_parse_value(const char *start, zbx_jsonobj_t *obj, int depth, char **error)
{
#define ZBX_MAX_JSON_DEPTH	64
	const char	*ptr = start;
	zbx_int64_t	len;
	char		*str = NULL;
	double		number;

	if (ZBX_MAX_JSON_DEPTH < depth)
	{
		char	err_buf[MAX_STRING_LEN];

		zbx_snprintf(err_buf, sizeof(err_buf), "JSON depth exceeds %d", ZBX_MAX_JSON_DEPTH);
		return json_error(err_buf, ptr, error);
	}

	depth++;

	SKIP_WHITESPACE(ptr);

	switch (*ptr)
	{
		case '\0':
			return json_error("unexpected end of object value", NULL, error);
		case '"':
			if (0 == (len = json_parse_string(ptr, (NULL != obj ? &str : NULL), error)))
				return 0;

			if (NULL != obj)
				jsonobj_set_string(obj, str);
			break;
		case '{':
			if (0 == (len = json_parse_object(ptr, obj, depth, error)))
				return 0;
			break;
		case '[':
			if (0 == (len = json_parse_array(ptr, obj, depth, error)))
				return 0;
			break;
		case 't':
			if (0 == (len = json_parse_literal(ptr, "true", error)))
				return 0;

			if (NULL != obj)
				jsonobj_set_true(obj);
			break;
		case 'f':
			if (0 == (len = json_parse_literal(ptr, "false", error)))
				return 0;

			if (NULL != obj)
				jsonobj_set_false(obj);
			break;
		case 'n':
			if (0 == (len = json_parse_literal(ptr, "null", error)))
				return 0;

			if (NULL != obj)
				jsonobj_set_null(obj);
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
			if (0 == (len = json_parse_number(ptr, (NULL != obj ? &number : NULL), error)))
				return 0;

			if (NULL != obj)
				jsonobj_set_number(obj, number);

			break;
		default:
			return json_error("invalid JSON object value starting character", ptr, error);
	}

	return ptr - start + len;
#undef ZBX_MAX_JSON_DEPTH
}

/******************************************************************************
 *                                                                            *
 * Purpose: Parses JSON object                                                *
 *                                                                            *
 * Parameters: start - [IN] the JSON data                                     *
 *             obj   - [IN/OUT] the JSON object (can be NULL)                 *
 *             depth - [IN]                                                   *
 *             error - [OUT] the parsing error message (can be NULL)          *
 *                                                                            *
 * Return value: The number of characters parsed. On error 0 is returned and  *
 *               error parameter (if not NULL) contains allocated error       *
 *               message.                                                     *
 *                                                                            *
 ******************************************************************************/
zbx_int64_t	json_parse_object(const char *start, zbx_jsonobj_t *obj, int depth, char **error)
{
	const char		*ptr = start;
	zbx_int64_t		len;

	if (NULL != obj)
		jsonobj_init(obj, ZBX_JSON_TYPE_OBJECT);

	/* parse object name */
	SKIP_WHITESPACE(ptr);

	ptr++;
	SKIP_WHITESPACE(ptr);

	if ('}' != *ptr)
	{
		while (1)
		{
			zbx_jsonobj_el_t	el;

			if ('"' != *ptr)
				return json_error("invalid object name", ptr, error);

			jsonobj_el_init(&el);

			/* cannot parse object name, failing */
			if (0 == (len = json_parse_string(ptr, (NULL != obj ? &el.name : NULL), error)))
				return 0;

			ptr += len;

			/* parse name:value separator */
			SKIP_WHITESPACE(ptr);

			if (':' != *ptr)
			{
				jsonobj_el_clear(&el);
				return json_error("invalid object name/value separator", ptr, error);
			}

			ptr++;

			if (0 == (len = json_parse_value(ptr, (NULL != obj ? &el.value : NULL), depth, error)))
			{
				jsonobj_el_clear(&el);
				return 0;
			}

			if (NULL != obj)
			{
				zbx_jsonobj_el_t	*pel;

				pel = (zbx_jsonobj_el_t *)zbx_hashset_insert(&obj->data.object, &el, sizeof(el));

				/* check if they element was inserted, if not solve the conflict */
				/* by overwriting old data                                       */
				if (pel->name != el.name)
				{
					zbx_free(pel->name);
					zbx_jsonobj_clear(&pel->value);
					*pel = el;
				}
			}

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
 * Purpose: Validates JSON object                                             *
 *                                                                            *
 * Parameters: start - [IN]  the string to validate                           *
 *             error - [OUT] the parse error message. If the error value is   *
 *                           set it must be freed by caller after it has      *
 *                           been used (can be NULL).                         *
 *                                                                            *
 * Return value: The number of characters parsed. On error 0 is returned and  *
 *               error parameter (if not NULL) contains allocated error       *
 *               message.                                                     *
 *                                                                            *
 ******************************************************************************/
zbx_int64_t	zbx_json_validate(const char *start, char **error)
{
	zbx_int64_t	len;

	/* parse object name */
	SKIP_WHITESPACE(start);

	switch (*start)
	{
		case '{':
			if (0 == (len = json_parse_object(start, NULL, 0, error)))
				return 0;
			break;
		case '[':
			if (0 == (len = json_parse_array(start, NULL, 0, error)))
				return 0;
			break;
		default:
			/* not json data, failing */
			return json_error("invalid object format, expected opening character '{' or '['", start, error);
	}

	start += len;
	SKIP_WHITESPACE(start);

	if ('\0' != *start)
		return json_error("invalid character following JSON object", start, error);

	return len;
}
