/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#include "zbxexpr.h"

#include "zbxnum.h"
#include "zbxstr.h"

/******************************************************************************
 *                                                                            *
 * Return value:  SUCCEED - the char is allowed in the trigger function       *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Comments: in trigger function allowed characters: 'a-z'                    *
 *           !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_is_function_char(unsigned char c)
{
	if (0 != islower(c))
		return SUCCEED;

	return FAIL;
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

	for (ptr = expr; SUCCEED == zbx_is_function_char(*ptr); ptr++)
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
 * Purpose: count calculated item (prototype) formula characters that can be  *
 *          skipped without the risk of missing a function                    *
 *                                                                            *
 ******************************************************************************/
static size_t	zbx_no_function(const char *expr)
{
	const char	*ptr = expr;
	int		inside_quote = 0, len, c_l, c_r;
	zbx_token_t	token;

	while ('\0' != *ptr)
	{
		switch  (*ptr)
		{
			case '\\':
				if (0 != inside_quote)
					ptr++;
				break;
			case '"':
				inside_quote = !inside_quote;
				ptr++;
				continue;
		}

		if (inside_quote)
		{
			if ('\0' == *ptr)
				break;
			ptr++;
			continue;
		}

		if ('{' == *ptr && '$' == *(ptr + 1) && SUCCEED == zbx_user_macro_parse(ptr, &len, &c_l, &c_r, NULL))
		{
			ptr += len + 1;	/* skip to the position after user macro */
		}
		else if ('{' == *ptr && '{' == *(ptr + 1) && '#' == *(ptr + 2) &&
				SUCCEED == zbx_token_parse_nested_macro(ptr, ptr, 0, &token))
		{
			ptr += token.loc.r - token.loc.l + 1;
		}
		else if (SUCCEED != zbx_is_function_char(*ptr))
		{
			ptr++;	/* skip one character which cannot belong to function name */
		}
		else if ((0 == strncmp("and", ptr, len = ZBX_CONST_STRLEN("and")) ||
				0 == strncmp("not", ptr, len = ZBX_CONST_STRLEN("not")) ||
				0 == strncmp("or", ptr, len = ZBX_CONST_STRLEN("or"))) &&
				NULL != strchr("()" ZBX_WHITESPACE, ptr[len]))
		{
			ptr += len;	/* skip to the position after and/or/not operator */
		}
		else if (ptr > expr && 0 != isdigit(*(ptr - 1)) && NULL != strchr(ZBX_UNIT_SYMBOLS, *ptr))
		{
			ptr++;	/* skip unit suffix symbol if it's preceded by a digit */
		}
		else
			break;
	}

	return ptr - expr;
}


/******************************************************************************
 *                                                                            *
 * Purpose: find the location of the next function and its parameters in      *
 *          calculated item (prototype) formula                               *
 *                                                                            *
 * Parameters: expr          - [IN] string to parse                           *
 *             func_pos      - [OUT] function position in the string          *
 *             par_l         - [OUT] position of the opening parenthesis      *
 *             par_r         - [OUT] position of the closing parenthesis      *
 *             error         - [OUT] error message                            *
 *             max_error_len - [IN] error size                                *
 *                                                                            *
 * Return value: SUCCEED - function was found at func_pos                     *
 *               FAIL    - there are no functions in the expression           *
 *                                                                            *
 ******************************************************************************/
int	zbx_function_find(const char *expr, size_t *func_pos, size_t *par_l, size_t *par_r, char *error,
		int max_error_len)
{
	const char	*ptr;

	for (ptr = expr; '\0' != *ptr; ptr += *par_l)
	{
		/* skip the part of expression that is definitely not a function */
		ptr += zbx_no_function(ptr);
		*par_r = 0;

		/* try to validate function candidate */
		if (SUCCEED != zbx_function_validate(ptr, par_l, par_r, error, max_error_len))
		{
			if (*par_l > *par_r)
				return FAIL;

			continue;
		}

		*func_pos = ptr - expr;
		*par_l += *func_pos;
		*par_r += *func_pos;
		return SUCCEED;
	}

	zbx_snprintf(error, max_error_len, "Incorrect function expression: %s", expr);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parses function parameter                                         *
 *                                                                            *
 * Parameters: expr      - [IN] pre-validated function parameter list         *
 *             param_pos - [OUT] the parameter position, excluding leading    *
 *                               whitespace                                   *
 *             length    - [OUT] the parameter length including trailing      *
 *                               whitespace for unquoted parameter            *
 *             sep_pos   - [OUT] the parameter separator character            *
 *                               (',' or '\0' or ')') position                *
 *                                                                            *
 ******************************************************************************/
void	zbx_function_param_parse(const char *expr, size_t *param_pos, size_t *length, size_t *sep_pos)
{
	const char	*ptr = expr;

	/* skip the leading whitespace */
	while (' ' == *ptr)
		ptr++;

	*param_pos = ptr - expr;

	if ('"' == *ptr)	/* quoted parameter */
	{
		for (ptr++; '"' != *ptr || '\\' == *(ptr - 1); ptr++)
		{
			if ('\0' == *ptr)
			{
				*length = ptr - expr - *param_pos;
				goto out;
			}
		}

		*length = ++ptr - expr - *param_pos;

		/* skip trailing whitespace to find the next parameter */
		while (' ' == *ptr)
			ptr++;
	}
	else	/* unquoted parameter */
	{
		for (ptr = expr; '\0' != *ptr && ')' != *ptr && ',' != *ptr; ptr++)
			;

		*length = ptr - expr - *param_pos;
	}
out:
	*sep_pos = ptr - expr;
}

/******************************************************************************
 *                                                                            *
 * Purpose: unquotes function parameter                                       *
 *                                                                            *
 * Parameters: param -  [IN] the parameter to unquote                         *
 *             len   -  [IN] the parameter length                             *
 *             quoted - [OUT] the flag that specifies whether parameter was   *
 *                            quoted before extraction                        *
 *                                                                            *
 * Return value: The unquoted parameter. This value must be freed by the      *
 *               caller.                                                      *
 *                                                                            *
 ******************************************************************************/
char	*zbx_function_param_unquote_dyn(const char *param, size_t len, int *quoted)
{
	char	*out;

	out = (char *)zbx_malloc(NULL, len + 1);

	if (0 == (*quoted = (0 != len && '"' == *param)))
	{
		/* unquoted parameter - simply copy it */
		memcpy(out, param, len);
		out[len] = '\0';
	}
	else
	{
		/* quoted parameter - remove enclosing " and replace \" with " */
		const char	*pin;
		char		*pout = out;

		for (pin = param + 1; (size_t)(pin - param) < len - 1; pin++)
		{
			if ('\\' == pin[0] && '"' == pin[1])
				pin++;

			*pout++ = *pin;
		}

		*pout = '\0';
	}

	return out;
}

/******************************************************************************
 *                                                                            *
 * Purpose: quotes function parameter                                         *
 *                                                                            *
 * Parameters: param   - [IN/OUT] function parameter                          *
 *             forced  - [IN] 1 - enclose parameter in " even if it does not  *
 *                                contain any special characters              *
 *                            0 - do nothing if the parameter does not        *
 *                                contain any special characters              *
 *                                                                            *
 * Return value: SUCCEED - if parameter was successfully quoted or quoting    *
 *                         was not necessary                                  *
 *               FAIL    - if parameter needs to but cannot be quoted due to  *
 *                         backslash in the end                               *
 *                                                                            *
 ******************************************************************************/
int	zbx_function_param_quote(char **param, int forced)
{
	size_t	sz_src, sz_dst;

	if (0 == forced && '"' != **param && ' ' != **param && NULL == strchr(*param, ',') &&
			NULL == strchr(*param, ')'))
	{
		return SUCCEED;
	}

	if (0 != (sz_src = strlen(*param)) && '\\' == (*param)[sz_src - 1])
		return FAIL;

	sz_dst = zbx_get_escape_string_len(*param, "\"") + 3;

	*param = (char *)zbx_realloc(*param, sz_dst);

	(*param)[--sz_dst] = '\0';
	(*param)[--sz_dst] = '"';

	while (0 < sz_src)
	{
		(*param)[--sz_dst] = (*param)[--sz_src];
		if ('"' == (*param)[sz_src])
			(*param)[--sz_dst] = '\\';
	}
	(*param)[--sz_dst] = '"';

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: return parameter by index (Nparam) from parameter list (params)   *
 *                                                                            *
 * Parameters:                                                                *
 *      params - [IN] parameter list                                          *
 *      Nparam - [IN] requested parameter index (from 1)                      *
 *                                                                            *
 * Return value:                                                              *
 *      NULL - requested parameter missing                                    *
 *      otherwise - requested parameter                                       *
 *                                                                            *
 ******************************************************************************/
char	*zbx_function_get_param_dyn(const char *params, int Nparam)
{
	const char	*ptr;
	size_t		sep_pos, params_len;
	char		*out = NULL;
	int		idx = 0;

	params_len = strlen(params) + 1;

	for (ptr = params; ++idx <= Nparam && ptr < params + params_len; ptr += sep_pos + 1)
	{
		size_t	param_pos, param_len;
		int	quoted;

		zbx_function_param_parse(ptr, &param_pos, &param_len, &sep_pos);

		if (idx == Nparam)
			out = zbx_function_param_unquote_dyn(ptr + param_pos, param_len, &quoted);
	}

	return out;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Returns function type based on its name                           *
 *                                                                            *
 * Return value:  Function type.                                              *
 *                                                                            *
 ******************************************************************************/
zbx_function_type_t	zbx_get_function_type(const char *func)
{
	if (0 == strncmp(func, "trend", 5))
		return ZBX_FUNCTION_TYPE_TRENDS;

	if (0 == strncmp(func, "baseline", 8))
		return ZBX_FUNCTION_TYPE_TRENDS;

	if (0 == strcmp(func, "nodata"))
		return ZBX_FUNCTION_TYPE_TIMER;

	return ZBX_FUNCTION_TYPE_HISTORY;
}
