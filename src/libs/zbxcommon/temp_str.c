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

#include "common.h"

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

#define ZBX_MACRO_REGEX_PREFIX		"regex:"

/******************************************************************************
 *                                                                            *
 * Purpose:                                                                   *
 *     parses user macro and finds its end position and context location      *
 *                                                                            *
 * Parameters:                                                                *
 *     macro     - [IN] the macro to parse                                    *
 *     macro_r   - [OUT] the position of ending '}' character                 *
 *     context_l - [OUT] the position of context start character (first non   *
 *                       space character after context separator ':')         *
 *                       0 if macro does not have context specified.          *
 *     context_r - [OUT] the position of context end character (either the    *
 *                       ending '"' for quoted context values or the last     *
 *                       character before the ending '}' character)           *
 *                       0 if macro does not have context specified.          *
 *     context_op - [OUT] the context matching operator (optional):           *
 *                          CONDITION_OPERATOR_EQUAL                          *
 *                          CONDITION_OPERATOR_REGEXP                         *
 *                                                                            *
 * Return value:                                                              *
 *     SUCCEED - the macro was parsed successfully.                           *
 *     FAIL    - the macro parsing failed, the content of output variables    *
 *               is not defined.                                              *
 *                                                                            *
 ******************************************************************************/
int	zbx_user_macro_parse(const char *macro, int *macro_r, int *context_l, int *context_r, unsigned char *context_op)
{
	int	i;

	/* find the end of macro name by skipping {$ characters and iterating through */
	/* valid macro name characters                                                */
	for (i = 2; SUCCEED == is_macro_char(macro[i]); i++)
		;

	/* check for empty macro name */
	if (2 == i)
		return FAIL;

	if ('}' == macro[i])
	{
		/* no macro context specified, parsing done */
		*macro_r = i;
		*context_l = 0;
		*context_r = 0;

		if (NULL != context_op)
			*context_op = CONDITION_OPERATOR_EQUAL;

		return SUCCEED;
	}

	/* fail if the next character is not a macro context separator */
	if  (':' != macro[i])
		return FAIL;

	i++;
	if (NULL != context_op)
	{
		if (0 == strncmp(macro + i, ZBX_MACRO_REGEX_PREFIX, ZBX_CONST_STRLEN(ZBX_MACRO_REGEX_PREFIX)))
		{
			*context_op = CONDITION_OPERATOR_REGEXP;
			i += ZBX_CONST_STRLEN(ZBX_MACRO_REGEX_PREFIX);
		}
		else
			*context_op = CONDITION_OPERATOR_EQUAL;
	}

	/* skip the whitespace after macro context separator */
	while (' ' == macro[i])
		i++;

	*context_l = i;

	if ('"' == macro[i])
	{
		i++;

		/* process quoted context */
		for (; '"' != macro[i]; i++)
		{
			if ('\0' == macro[i])
				return FAIL;

			if ('\\' == macro[i] && '"' == macro[i + 1])
				i++;
		}

		*context_r = i;

		while (' ' == macro[++i])
			;
	}
	else
	{
		/* process unquoted context */
		for (; '}' != macro[i]; i++)
		{
			if ('\0' == macro[i])
				return FAIL;
		}

		*context_r = i - 1;
	}

	if ('}' != macro[i])
		return FAIL;

	*macro_r = i;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose:                                                                   *
 *     parses user macro {$MACRO:<context>} into {$MACRO} and <context>       *
 *     strings                                                                *
 *                                                                            *
 * Parameters:                                                                *
 *     macro   - [IN] the macro to parse                                      *
 *     name    - [OUT] the macro name without context                         *
 *     context - [OUT] the unquoted macro context, NULL for macros without    *
 *                     context                                                *
 *     length  - [OUT] the length of parsed macro (optional)                  *
 *     context_op - [OUT] the context matching operator (optional):           *
 *                          CONDITION_OPERATOR_EQUAL                          *
 *                          CONDITION_OPERATOR_REGEXP                         *
 *                                                                            *
 * Return value:                                                              *
 *     SUCCEED - the macro was parsed successfully                            *
 *     FAIL    - the macro parsing failed, invalid parameter syntax           *
 *                                                                            *
 ******************************************************************************/
int	zbx_user_macro_parse_dyn(const char *macro, char **name, char **context, int *length, unsigned char *context_op)
{
	const char	*ptr;
	int		macro_r, context_l, context_r;
	size_t		len;

	if (SUCCEED != zbx_user_macro_parse(macro, &macro_r, &context_l, &context_r, context_op))
		return FAIL;

	zbx_free(*context);

	if (0 != context_l)
	{
		ptr = macro + context_l;

		/* find the context separator ':' by stripping spaces before context */
		while (' ' == *(--ptr))
			;

		/* remove regex: prefix from macro name for regex contexts */
		if (NULL != context_op && CONDITION_OPERATOR_REGEXP == *context_op)
			ptr -= ZBX_CONST_STRLEN(ZBX_MACRO_REGEX_PREFIX);

		/* extract the macro name and close with '}' character */
		len = ptr - macro + 1;
		*name = (char *)zbx_realloc(*name, len + 1);
		memcpy(*name, macro, len - 1);
		(*name)[len - 1] = '}';
		(*name)[len] = '\0';

		*context = zbx_user_macro_unquote_context_dyn(macro + context_l, context_r - context_l + 1);
	}
	else
	{
		*name = (char *)zbx_realloc(*name, macro_r + 2);
		zbx_strlcpy(*name, macro, macro_r + 2);
	}

	if (NULL != length)
		*length = macro_r + 1;

	return SUCCEED;
}

#undef ZBX_MACRO_REGEX_PREFIX

/******************************************************************************
 *                                                                            *
 * Purpose:                                                                   *
 *     extracts the macro context unquoting if necessary                      *
 *                                                                            *
 * Parameters:                                                                *
 *     context - [IN] the macro context inside a user macro                   *
 *     len     - [IN] the macro context length (including quotes for quoted   *
 *                    contexts)                                               *
 *                                                                            *
 * Return value:                                                              *
 *     A string containing extracted macro context. This string must be freed *
 *     by the caller.                                                         *
 *                                                                            *
 ******************************************************************************/
char	*zbx_user_macro_unquote_context_dyn(const char *context, int len)
{
	int	quoted = 0;
	char	*buffer, *ptr;

	ptr = buffer = (char *)zbx_malloc(NULL, len + 1);

	if ('"' == *context)
	{
		quoted = 1;
		context++;
		len--;
	}

	while (0 < len)
	{
		if (1 == quoted && '\\' == *context && '"' == context[1])
		{
			context++;
			len--;
		}

		*ptr++ = *context++;
		len--;
	}

	if (1 == quoted)
		ptr--;

	*ptr = '\0';

	return buffer;
}

/******************************************************************************
 *                                                                            *
 * Purpose:                                                                   *
 *     quotes user macro context if necessary                                 *
 *                                                                            *
 * Parameters:                                                                *
 *     context     - [IN] the macro context                                   *
 *     force_quote - [IN] if non zero then context quoting is enforced        *
 *     error       - [OUT] the error message                                  *
 *                                                                            *
 * Return value:                                                              *
 *     A string containing quoted macro context on success, NULL on error.    *
 *                                                                            *
 ******************************************************************************/
char	*zbx_user_macro_quote_context_dyn(const char *context, int force_quote, char **error)
{
	int		len, quotes = 0;
	char		*buffer, *ptr_buffer;
	const char	*ptr_context = context, *start = context;

	if ('"' == *ptr_context || ' ' == *ptr_context)
		force_quote = 1;

	for (; '\0' != *ptr_context; ptr_context++)
	{
		if ('}' == *ptr_context)
			force_quote = 1;

		if ('"' == *ptr_context)
			quotes++;
	}

	if (0 == force_quote)
		return zbx_strdup(NULL, context);

	len = (int)strlen(context) + 2 + quotes;
	ptr_buffer = buffer = (char *)zbx_malloc(NULL, len + 1);

	*ptr_buffer++ = '"';

	while ('\0' != *context)
	{
		if ('"' == *context)
			*ptr_buffer++ = '\\';

		*ptr_buffer++ = *context++;
	}

	if ('\\' == *(ptr_buffer - 1))
	{
		*error = zbx_dsprintf(*error, "quoted context \"%s\" cannot end with '\\' character", start);
		zbx_free(buffer);
		return NULL;
	}

	*ptr_buffer++ = '"';
	*ptr_buffer++ = '\0';

	return buffer;
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
 * Purpose: If there is no '\0' byte among the first n bytes of src,          *
 *          then all n bytes will be placed into the dest buffer.             *
 *          In other case only strlen() bytes will be placed there.           *
 *          Add zero character at the end of string.                          *
 *          Reallocs memory if not enough.                                    *
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
 * Purpose: replace data block with 'value'                                   *
 *                                                                            *
 * Parameters: data  - [IN/OUT] pointer to the string                         *
 *             l     - [IN] left position of the block                        *
 *             r     - [IN/OUT] right position of the block                   *
 *             value - [IN] the string to replace the block with              *
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

/******************************************************************************
 *                                                                            *
 * Purpose: Copy src to string dst of size siz. At most siz - 1 characters    *
 *          will be copied. Always null terminates (unless siz == 0).         *
 *                                                                            *
 * Return value: the number of characters copied (excluding the null byte)    *
 *                                                                            *
 ******************************************************************************/
size_t	zbx_strlcpy(char *dst, const char *src, size_t siz)
{
	const char	*s = src;

	if (0 != siz)
	{
		while (0 != --siz && '\0' != *s)
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
retry:
	if (NULL == *str)
	{
		/* zbx_vsnprintf() returns bytes actually written instead of bytes to write, */
		/* so we have to use the standard function                                   */
		va_start(args, fmt);
		*alloc_len = vsnprintf(NULL, 0, fmt, args) + 2;	/* '\0' + one byte to prevent the operation retry */
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


/******************************************************************************
 *                                                                            *
 * Purpose: validate parameters and give position of terminator if found and  *
 *          not quoted                                                        *
 *                                                                            *
 * Parameters: expr       - [IN] string to parse that contains parameters     *
 *                                                                            *
 *             terminator - [IN] use ')' if parameters end with               *
 *                               parenthesis or '\0' if ends with NULL        *
 *                               terminator                                   *
 *             par_r      - [OUT] position of the terminator if found         *
 *             lpp_offset - [OUT] offset of the last parsed parameter         *
 *             lpp_len    - [OUT] length of the last parsed parameter         *
 *                                                                            *
 * Return value: SUCCEED -  closing parenthesis was found or other custom     *
 *                          terminator and not quoted and return info about a *
 *                          last processed parameter.                         *
 *               FAIL    -  does not look like a valid function parameter     *
 *                          list and return info about a last processed       *
 *                          parameter.                                        *
 *                                                                            *
 ******************************************************************************/
static int	function_validate_parameters(const char *expr, char terminator, size_t *par_r, size_t *lpp_offset,
		size_t *lpp_len)
{
#define ZBX_FUNC_PARAM_NEXT		0
#define ZBX_FUNC_PARAM_QUOTED		1
#define ZBX_FUNC_PARAM_UNQUOTED		2
#define ZBX_FUNC_PARAM_POSTQUOTED	3

	const char	*ptr;
	int		state = ZBX_FUNC_PARAM_NEXT;

	*lpp_offset = 0;

	for (ptr = expr; '\0' != *ptr; ptr++)
	{
		if (terminator == *ptr && ZBX_FUNC_PARAM_QUOTED != state)
		{
			*par_r = ptr - expr;
			return SUCCEED;
		}

		switch (state)
		{
			case ZBX_FUNC_PARAM_NEXT:
				*lpp_offset = ptr - expr;
				if ('"' == *ptr)
					state = ZBX_FUNC_PARAM_QUOTED;
				else if (' ' != *ptr && ',' != *ptr)
					state = ZBX_FUNC_PARAM_UNQUOTED;
				break;
			case ZBX_FUNC_PARAM_QUOTED:
				if ('"' == *ptr && '\\' != *(ptr - 1))
					state = ZBX_FUNC_PARAM_POSTQUOTED;
				break;
			case ZBX_FUNC_PARAM_UNQUOTED:
				if (',' == *ptr)
					state = ZBX_FUNC_PARAM_NEXT;
				break;
			case ZBX_FUNC_PARAM_POSTQUOTED:
				if (',' == *ptr)
				{
					state = ZBX_FUNC_PARAM_NEXT;
				}
				else if (' ' != *ptr)
				{
					*lpp_len = ptr - (expr + *lpp_offset);
					return FAIL;
				}
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
		}
	}

	*lpp_len = ptr - (expr + *lpp_offset);

	if (terminator == *ptr && ZBX_FUNC_PARAM_QUOTED != state)
	{
		*par_r = ptr - expr;
		return SUCCEED;
	}

	return FAIL;

#undef ZBX_FUNC_PARAM_NEXT
#undef ZBX_FUNC_PARAM_QUOTED
#undef ZBX_FUNC_PARAM_UNQUOTED
#undef ZBX_FUNC_PARAM_POSTQUOTED
}

/******************************************************************************
 *                                                                            *
 * Purpose: validate parameters that end with '\0'                            *
 *                                                                            *
 * Parameters: expr       - [IN] string to parse that contains parameters     *
 *             length     - [OUT] length of parameters                        *
 *                                                                            *
 * Return value: SUCCEED -  null termination encountered when quotes are      *
 *                          closed and no other error                         *
 *               FAIL    -  does not look like a valid                        *
 *                          function parameter list                           *
 *                                                                            *
 ******************************************************************************/
int	zbx_function_validate_parameters(const char *expr, size_t *length)
{
	size_t offset, len;

	return function_validate_parameters(expr, '\0', length, &offset, &len);
}

/******************************************************************************
 *                                                                            *
 * Purpose: given the position of opening function parenthesis find the       *
 *          position of a closing one                                         *
 *                                                                            *
 * Parameters: expr       - [IN] string to parse                              *
 *             par_l      - [IN] position of the opening parenthesis          *
 *             par_r      - [OUT] position of the closing parenthesis         *
 *             lpp_offset - [OUT] offset of the last parsed parameter         *
 *             lpp_len    - [OUT] length of the last parsed parameter         *
 *                                                                            *
 * Return value: SUCCEED - closing parenthesis was found                      *
 *               FAIL    - string after par_l does not look like a valid      *
 *                         function parameter list                            *
 *                                                                            *
 ******************************************************************************/
static int	function_match_parenthesis(const char *expr, size_t par_l, size_t *par_r, size_t *lpp_offset,
		size_t *lpp_len)
{
	if (SUCCEED == function_validate_parameters(expr + par_l + 1, ')', par_r, lpp_offset, lpp_len))
	{
		*par_r += par_l + 1;
		return SUCCEED;
	}

	*lpp_offset += par_l + 1;
	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parses function name                                              *
 *                                                                            *
 * Parameters: expr     - [IN] the function expression: func(p1, p2,...)      *
 *             length   - [OUT] the function name length or the amount of     *
 *                              characters that can be safely skipped         *
 *                                                                            *
 * Return value: SUCCEED - the function name was successfully parsed          *
 *               FAIL    - failed to parse function name                      *
 *                                                                            *
 ******************************************************************************/
static int	function_parse_name(const char *expr, size_t *length)
{
	const char	*ptr;

	for (ptr = expr; SUCCEED == is_function_char(*ptr); ptr++)
		;

	*length = ptr - expr;

	return ptr != expr && '(' == *ptr ? SUCCEED : FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check whether expression starts with a valid function             *
 *                                                                            *
 * Parameters: expr          - [IN] string to parse                           *
 *             par_l         - [OUT] position of the opening parenthesis      *
 *                                   or the amount of characters to skip      *
 *             par_r         - [OUT] position of the closing parenthesis      *
 *             error         - [OUT] error message                            *
 *             max_error_len - [IN] error size                                *
 *                                                                            *
 * Return value: SUCCEED - string starts with a valid function                *
 *               FAIL    - string does not start with a function and par_l    *
 *                         characters can be safely skipped                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_function_validate(const char *expr, size_t *par_l, size_t *par_r, char *error, int max_error_len)
{
	size_t	lpp_offset, lpp_len;

	/* try to validate function name */
	if (SUCCEED == function_parse_name(expr, par_l))
	{
		/* now we know the position of '(', try to find ')' */
		if (SUCCEED == function_match_parenthesis(expr, *par_l, par_r, &lpp_offset, &lpp_len))
			return SUCCEED;

		if (NULL != error && *par_l > *par_r)
		{
			zbx_snprintf(error, max_error_len, "Incorrect function '%.*s' expression. "
				"Check expression part starting from: %.*s",
				(int)*par_l, expr, (int)lpp_len, expr + lpp_offset);

			return FAIL;
		}
	}

	if (NULL != error)
		zbx_snprintf(error, max_error_len, "Incorrect function expression: %s", expr);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if the string is unsigned hexadecimal integer within the    *
 *          specified range and optionally store it into value parameter      *
 *                                                                            *
 * Parameters: str   - [IN] string to check                                   *
 *             n     - [IN] string length                                     *
 *             value - [OUT] a pointer to output buffer where the converted   *
 *                     value is to be written (optional, can be NULL)         *
 *             size  - [IN] size of the output buffer (optional)              *
 *             min   - [IN] the minimum acceptable value                      *
 *             max   - [IN] the maximum acceptable value                      *
 *                                                                            *
 * Return value:  SUCCEED - the string is unsigned integer                    *
 *                FAIL - the string is not a hexadecimal number or its value  *
 *                       is outside the specified range                       *
 *                                                                            *
 ******************************************************************************/
int	is_hex_n_range(const char *str, size_t n, void *value, size_t size, zbx_uint64_t min, zbx_uint64_t max)
{
	zbx_uint64_t		value_uint64 = 0, c;
	const zbx_uint64_t	max_uint64 = ~(zbx_uint64_t)__UINT64_C(0);
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
 * Purpose: check if the string is a non-negative integer with or without     *
 *          supported time suffix                                             *
 *                                                                            *
 * Parameters: str    - [IN] string to check                                  *
 *             value  - [OUT] a pointer to converted value (optional)         *
 *             length - [IN] number of characters to validate, pass           *
 *                      ZBX_LENGTH_UNLIMITED to validate full string          *
 *                                                                            *
 * Return value: SUCCEED - the string is valid and within reasonable limits   *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: the function automatically processes suffixes s, m, h, d, w      *
 *                                                                            *
 ******************************************************************************/
int	is_time_suffix(const char *str, int *value, int length)
{
	const int	max = 0x7fffffff;	/* minimum acceptable value for INT_MAX is 2 147 483 647 */
	int		len = length;
	int		value_tmp = 0, c, factor = 1;

	if ('\0' == *str || 0 >= len || 0 == isdigit(*str))
		return FAIL;

	while ('\0' != *str && 0 < len && 0 != isdigit(*str))
	{
		c = (int)(unsigned char)(*str - '0');

		if ((max - c) / 10 < value_tmp)
			return FAIL;	/* overflow */

		value_tmp = value_tmp * 10 + c;

		str++;
		len--;
	}

	if ('\0' != *str && 0 < len)
	{
		switch (*str)
		{
			case 's':
				break;
			case 'm':
				factor = SEC_PER_MIN;
				break;
			case 'h':
				factor = SEC_PER_HOUR;
				break;
			case 'd':
				factor = SEC_PER_DAY;
				break;
			case 'w':
				factor = SEC_PER_WEEK;
				break;
			default:
				return FAIL;
		}

		str++;
		len--;
	}

	if ((ZBX_LENGTH_UNLIMITED == length && '\0' != *str) || (ZBX_LENGTH_UNLIMITED != length && 0 != len))
		return FAIL;

	if (max / factor < value_tmp)
		return FAIL;	/* overflow */

	if (NULL != value)
		*value = value_tmp * factor;

	return SUCCEED;
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
int	is_double(const char *str, double *value)
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
