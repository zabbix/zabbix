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

/******************************************************************************
 *                                                                            *
 * Parameters: expression - [IN]                                              *
 *             macro      - [IN] beginning of the token                       *
 *             token      - [OUT] token data                                  *
 *                                                                            *
 * Return value: SUCCEED - user macro was parsed successfully                 *
 *               FAIL    - macro does not point at valid user macro           *
 *                                                                            *
 * Comments: If the macro points at valid user macro in the expression then   *
 *           the generic token fields are set and the token->data.user_macro  *
 *           structure is filled with user macro specific data.               *
 *                                                                            *
 ******************************************************************************/
static int	token_parse_user_macro(const char *expression, const char *macro, zbx_token_t *token)
{
	size_t			offset;
	int			macro_r, context_l, context_r;
	zbx_token_user_macro_t	*data;

	if (SUCCEED != zbx_user_macro_parse(macro, &macro_r, &context_l, &context_r, NULL))
		return FAIL;

	offset = macro - expression;

	/* initialize token */
	token->type = ZBX_TOKEN_USER_MACRO;
	token->loc.l = offset;
	token->loc.r = offset + macro_r;

	/* initialize token data */
	data = &token->data.user_macro;
	data->name.l = offset + 2;

	if (0 != context_l)
	{
		const char *ptr = macro + context_l;

		/* find the context separator ':' by stripping spaces before context */
		while (' ' == *(--ptr))
			;

		data->name.r = offset + (ptr - macro) - 1;

		data->context.l = offset + context_l;
		data->context.r = offset + context_r;
	}
	else
	{
		data->name.r = token->loc.r - 1;
		data->context.l = 0;
		data->context.r = 0;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Parameters: expression - [IN]                                              *
 *             macro      - [IN] beginning of token                           *
 *             token      - [OUT]                                             *
 *                                                                            *
 * Return value: SUCCEED - lld macro was parsed successfully                  *
 *               FAIL    - macro does not point at valid lld macro            *
 *                                                                            *
 * Comments: If the macro points at valid lld macro in the expression then    *
 *           the generic token fields are set and the token->data.lld_macro   *
 *           structure is filled with lld macro specific data.                *
 *                                                                            *
 ******************************************************************************/
static int	token_parse_lld_macro(const char *expression, const char *macro, zbx_token_t *token)
{
	const char		*ptr;
	size_t			offset;
	zbx_token_macro_t	*data;

	/* find the end of lld macro by validating its name until the closing bracket } */
	for (ptr = macro + 2; '}' != *ptr; ptr++)
	{
		if ('\0' == *ptr)
			return FAIL;

		if (SUCCEED != zbx_is_macro_char(*ptr))
			return FAIL;
	}

	/* empty macro name */
	if (2 == ptr - macro)
		return FAIL;

	offset = macro - expression;

	/* initialize token */
	token->type = ZBX_TOKEN_LLD_MACRO;
	token->loc.l = offset;
	token->loc.r = offset + (ptr - macro);

	/* initialize token data */
	data = &token->data.lld_macro;
	data->name.l = offset + 2;
	data->name.r = token->loc.r - 1;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Parameters: expression    - [IN]                                           *
 *             macro         - [IN] beginning of the token                    *
 *             token_search  - [IN] specify if references will be searched    *
 *             token         - [OUT]                                          *
 *                                                                            *
 * Return value: SUCCEED - expression macro was parsed successfully           *
 *               FAIL    - macro does not point at valid expression macro     *
 *                                                                            *
 * Comments: If the macro points at valid expression macro in the expression  *
 *           then the generic token fields are set and the                    *
 *           token->data.expression_macro structure is filled with expression *
 *           macro specific data. Contents of macro are not validated because *
 *           the expression macro may contain user macro contexts and item    *
 *           keys with string arguments.                                      *
 *                                                                            *
 ******************************************************************************/
static int	token_parse_expression_macro(const char *expression, const char *macro, zbx_token_search_t token_search,
		zbx_token_t *token)
{
	const char			*ptr;
	size_t				offset;
	zbx_token_expression_macro_t	*data;
	int				quoted = 0;

	for (ptr = macro + 2; '\0' != *ptr ; ptr++)
	{
		if (1 == quoted)
		{
			if ('\\' == *ptr)
			{
				if ('\0' == *(++ptr))
					break;
				continue;
			}

			if ('"' == *ptr)
				quoted = 0;

			continue;
		}

		if ('{' == *ptr)
		{
			zbx_token_t	tmp;

			/* nested expression macros are not supported */
			if ('?' == ptr[1])
				continue;

			token_search &= ~ZBX_TOKEN_SEARCH_EXPRESSION_MACRO;
			if (SUCCEED == zbx_token_find(ptr, 0, &tmp, token_search))
			{
				switch (tmp.type)
				{
					case ZBX_TOKEN_MACRO:
					case ZBX_TOKEN_LLD_MACRO:
					case ZBX_TOKEN_LLD_FUNC_MACRO:
					case ZBX_TOKEN_USER_MACRO:
					case ZBX_TOKEN_SIMPLE_MACRO:
						ptr += tmp.loc.r;
						break;
				}
			}
		}
		else if ('}' == *ptr)
		{
			/* empty macro */
			if (ptr == macro + 2)
				return FAIL;

			offset = macro - expression;

			/* initialize token */
			token->type = ZBX_TOKEN_EXPRESSION_MACRO;
			token->loc.l = offset;
			token->loc.r = offset + (ptr - macro);

			/* initialize token data */
			data = &token->data.expression_macro;
			data->expression.l = offset + 2;
			data->expression.r = token->loc.r - 1;

			return SUCCEED;
		}
		else if ('"' == *ptr)
			quoted = 1;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Parameters: expression - [IN]                                              *
 *             macro      - [IN] beginning of token                           *
 *             token      - [OUT]                                             *
 *                                                                            *
 * Return value: SUCCEED - object id was parsed successfully                  *
 *               FAIL    - macro does not point at valid object id            *
 *                                                                            *
 * Comments: If the macro points at valid object id in the expression then    *
 *           the generic token fields are set and the token->data.objectid    *
 *           structure is filled with object id specific data.                *
 *                                                                            *
 ******************************************************************************/
static int	token_parse_objectid(const char *expression, const char *macro, zbx_token_t *token)
{
	const char		*ptr;
	size_t			offset;
	zbx_token_macro_t	*data;

	/* find the end of object id by checking if it contains digits until the closing bracket } */
	for (ptr = macro + 1; '}' != *ptr; ptr++)
	{
		if ('\0' == *ptr)
			return FAIL;

		if (0 == isdigit(*ptr))
			return FAIL;
	}

	/* empty object id */
	if (1 == ptr - macro)
		return FAIL;

	offset = macro - expression;

	/* initialize token */
	token->type = ZBX_TOKEN_OBJECTID;
	token->loc.l = offset;
	token->loc.r = offset + (ptr - macro);

	/* initialize token data */
	data = &token->data.objectid;
	data->name.l = offset + 1;
	data->name.r = token->loc.r - 1;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parses macro name segment                                         *
 *                                                                            *
 * Parameters: expression - [IN]                                              *
 *             segment    - [IN] segment start                                *
 *             strict     - [OUT] 1 - macro contains only standard characters *
 *                                    (upper case alphanumeric characters,    *
 *                                     dots and underscores)                  *
 *                                0 - last segment contains lowercase or      *
 *                                    quoted characters                       *
 *             next       - [OUT] offset of next character after the segment  *
 *                                                                            *
 * Return value: SUCCEED - segment was parsed successfully                    *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	token_parse_macro_segment(const char *expression, const char *segment, int *strict, int *next)
{
	const char	*ptr = segment;

	if ('"' != *ptr)
	{
		for (*strict = 1; '\0' != *ptr; ptr++)
		{
			if (0 != isalpha((unsigned char)*ptr))
			{
				if (0 == isupper((unsigned char)*ptr))
					*strict = 0;
				continue;
			}

			if (0 != isdigit((unsigned char)*ptr))
				continue;

			if ('_' == *ptr)
				continue;

			break;
		}

		/* check for empty segment */
		if (ptr == segment)
			return FAIL;

		*next = ptr - expression;
	}
	else
	{
		for (*strict = 0, ptr++; '"' != *ptr; ptr++)
		{
			if ('\0' == *ptr)
				return FAIL;

			if ('\\' == *ptr)
			{
				ptr++;
				if ('\\' != *ptr && '"' != *ptr)
					return FAIL;
			}
		}

		/* check for empty segment */
		if (1 == ptr - segment)
			return FAIL;

		*next = ptr - expression + 1;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Parameters: expression - [IN]                                              *
 *             ptr        - [IN] beginning of macro name                      *
 *             loc        - [OUT] macro name location                         *
 *                                                                            *
 * Return value: SUCCEED - simple macro was parsed successfully               *
 *               FAIL    - macro does not point at valid macro                *
 *                                                                            *
 * Comments: Note that the character following macro name must be inspected   *
 *           to draw any conclusions. For example for normal macros it must   *
 *           be '}' or it's not a valid macro.                                *
 *                                                                            *
 ******************************************************************************/
static int	token_parse_macro_name(const char *expression, const char *ptr, zbx_strloc_t *loc)
{
	int	strict, offset, ret;

	loc->l = ptr - expression;

	while (SUCCEED == (ret = token_parse_macro_segment(expression, ptr, &strict, &offset)))
	{
		if (0 == strict && expression + loc->l == ptr)
			return FAIL;

		ptr = expression + offset;

		if ('.' != *ptr || 0 == strict)
		{
			loc->r = ptr - expression - 1;
			break;
		}
		ptr++;
	}
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parses normal macro token                                         *
 *                                                                            *
 * Parameters: expression - [IN]                                              *
 *             macro      - [IN] beginning of the token                       *
 *             token      - [OUT]                                             *
 *                                                                            *
 * Return value: SUCCEED - simple macro was parsed successfully               *
 *               FAIL    - macro does not point at valid macro                *
 *                                                                            *
 * Comments: If the macro points at valid macro in the expression then        *
 *           the generic token fields are set and the token->data.macro       *
 *           structure is filled with simple macro specific data.             *
 *                                                                            *
 ******************************************************************************/
static int	token_parse_macro(const char *expression, const char *macro, zbx_token_t *token)
{
	zbx_strloc_t		loc;
	zbx_token_macro_t	*data;

	if (SUCCEED != token_parse_macro_name(expression, macro + 1, &loc))
		return FAIL;

	if ('}' != expression[loc.r + 1])
		return FAIL;

	/* initialize token */
	token->type = ZBX_TOKEN_MACRO;
	token->loc.l = loc.l - 1;
	token->loc.r = loc.r + 1;

	/* initialize token data */
	data = &token->data.macro;
	data->name = loc;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parses variable macro token                                       *
 *                                                                            *
 * Parameters: expression - [IN]                                              *
 *             macro      - [IN] beginning of the token                       *
 *             token      - [OUT]                                             *
 *                                                                            *
 * Return value: SUCCEED - simple macro was parsed successfully               *
 *               FAIL    - macro does not point at valid macro                *
 *                                                                            *
 * Comments: If the macro points at valid macro in the expression then        *
 *           the generic token fields are set and the token->data.var_macro   *
 *           structure is filled with simple macro specific data.             *
 *                                                                            *
 ******************************************************************************/
static int	token_parse_var_macro(const char *expression, const char *macro, zbx_token_t *token)
{
	zbx_token_macro_t	*data;
	const char		*ptr;

	for (ptr = macro + 1; '}' != *ptr; ptr++)
	{
		if ('\0' == *ptr)
			return FAIL;
	}
	/* initialize token */
	token->type = ZBX_TOKEN_VAR_MACRO;
	token->loc.l = macro - expression;
	token->loc.r = ptr - expression;

	/* initialize token data */
	data = &token->data.var_macro;
	data->name.l = token->loc.l + 1;
	data->name.r = token->loc.r - 1;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parses function inside token                                      *
 *                                                                            *
 * Parameters: expression - [IN]                                              *
 *             func       - [IN] beginning of the function                    *
 *             func_loc   - [OUT] function location relative to the           *
 *                                expression (including parameters)           *
 *             func_param - [OUT]                                             *
 *                                                                            *
 * Return value: SUCCEED - function was parsed successfully                   *
 *               FAIL    - func does not point at valid function              *
 *                                                                            *
 ******************************************************************************/
static int	token_parse_function(const char *expression, const char *func,
		zbx_strloc_t *func_loc, zbx_strloc_t *func_param)
{
	size_t	par_l, par_r;

	if (SUCCEED != zbx_function_validate(func, &par_l, &par_r, NULL, 0))
		return FAIL;

	func_loc->l = func - expression;
	func_loc->r = func_loc->l + par_r;

	func_param->l = func_loc->l + par_l;
	func_param->r = func_loc->l + par_r;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Parameters: expression - [IN]                                              *
 *             macro      - [IN] beginning of the token                       *
 *             func       - [IN] beginning of the macro function in token     *
 *             token      - [OUT]                                             *
 *             token_type - [IN] type flag ZBX_TOKEN_FUNC_MACRO,              *
 *                               ZBX_TOKEN_USER_FUNC_MACRO or                 *
 *                               ZBX_TOKEN_LLD_FUNC_MACRO                     *
 *                                                                            *
 * Return value: SUCCEED - function macro was parsed successfully             *
 *               FAIL    - macro does not point at valid function macro       *
 *                                                                            *
 * Comments: If the macro points at valid function macro in the expression    *
 *           then the generic token fields are set and the                    *
 *           token->data.func_macro or token->data.lld_func_macro structures  *
 *           depending on token type flag are filled with function macro      *
 *           specific data.                                                   *
 *                                                                            *
 ******************************************************************************/
static int	token_parse_func_macro(const char *expression, const char *macro, const char *func,
		zbx_token_t *token, int token_type)
{
	zbx_strloc_t		func_loc, func_param;
	zbx_token_func_macro_t	*data;
	const char		*ptr;
	size_t			offset;

	if ('\0' == *func)
		return FAIL;

	if (SUCCEED != token_parse_function(expression, func, &func_loc, &func_param))
		return FAIL;

	ptr = expression + func_loc.r + 1;

	/* skip trailing whitespace and verify that token ends with } */

	while (' ' == *ptr)
		ptr++;

	if ('}' != *ptr)
		return FAIL;

	offset = macro - expression;

	/* initialize token */
	token->type = token_type;
	token->loc.l = offset;
	token->loc.r = ptr - expression;

	/* initialize token data */
	switch (token_type)
	{
		case ZBX_TOKEN_FUNC_MACRO:
		case ZBX_TOKEN_USER_FUNC_MACRO:
			data = &token->data.func_macro;
			break;
		case ZBX_TOKEN_LLD_FUNC_MACRO:
			data = &token->data.lld_func_macro;
			break;
		case ZBX_TOKEN_VAR_FUNC_MACRO:
			data = &token->data.var_func_macro;
			break;
		default:
			return FAIL;
	}

	data->macro.l = offset + 1;
	data->macro.r = func_loc.l - 2;

	data->func = func_loc;
	data->func_param = func_param;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: parses simple macro token with given key                          *
 *                                                                            *
 * Parameters: expression - [IN]                                              *
 *             macro      - [IN] beginning of token                           *
 *             key        - [IN] beginning of host key inside token           *
 *             token      - [OUT]                                             *
 *                                                                            *
 * Return value: SUCCEED - function macro was parsed successfully             *
 *               FAIL    - macro does not point at valid simple macro         *
 *                                                                            *
 * Comments: Simple macros have format {<host>:<key>.<func>(<params>)}        *
 *           {HOST.HOSTn} macro can be used for host name and {ITEM.KEYn}     *
 *           macro can be used for item key.                                  *
 *                                                                            *
 *           If the macro points at valid simple macro in the expression      *
 *           then the generic token fields are set and the                    *
 *           token->data.simple_macro structure is filled with simple macro   *
 *           specific data.                                                   *
 *                                                                            *
 ******************************************************************************/
static int	token_parse_simple_macro_key(const char *expression, const char *macro, const char *key,
		zbx_token_t *token)
{
	size_t				offset;
	zbx_token_simple_macro_t	*data;
	const char			*ptr = key;
	zbx_strloc_t			key_loc, func_loc, func_param;

	if (SUCCEED != zbx_parse_key(&ptr))
	{
		zbx_token_t	key_token;

		if (SUCCEED != token_parse_macro(expression, key, &key_token))
			return FAIL;

		ptr = expression + key_token.loc.r + 1;
	}

	/* If the key is without parameters, then zbx_parse_key() will move cursor past function name - */
	/* at the start of its parameters. In this case move cursor back before function.           */
	if ('(' == *ptr)
	{
		while ('.' != *(--ptr))
			;
	}

	/* check for empty key */
	if (0 == ptr - key)
		return FAIL;

	if (SUCCEED != token_parse_function(expression, ptr + 1, &func_loc, &func_param))
		return FAIL;

	key_loc.l = key - expression;
	key_loc.r = ptr - expression - 1;

	ptr = expression + func_loc.r + 1;

	/* skip trailing whitespace and verify that token ends with } */

	while (' ' == *ptr)
		ptr++;

	if ('}' != *ptr)
		return FAIL;

	offset = macro - expression;

	/* initialize token */
	token->type = ZBX_TOKEN_SIMPLE_MACRO;
	token->loc.l = offset;
	token->loc.r = ptr - expression;

	/* initialize token data */
	data = &token->data.simple_macro;
	data->host.l = offset + 1;
	data->host.r = offset + (key - macro) - 2;

	data->key = key_loc;
	data->func = func_loc;
	data->func_param = func_param;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Parameters: expression - [IN]                                              *
 *             macro      - [IN] beginning of the token                       *
 *             token      - [OUT]                                             *
 *                                                                            *
 * Return value: SUCCEED - simple macro was parsed successfully               *
 *               FAIL    - macro does not point at valid simple macro         *
 *                                                                            *
 * Comments: Simple macros have format {<host>:<key>.<func>(<params>)}        *
 *           {HOST.HOSTn} macro can be used for host name and {ITEM.KEYn}     *
 *           macro can be used for item key.                                  *
 *                                                                            *
 *           If the macro points at valid simple macro in the expression      *
 *           then the generic token fields are set and the                    *
 *           token->data.simple_macro structure is filled with simple macro   *
 *           specific data.                                                   *
 *                                                                            *
 ******************************************************************************/
static int	token_parse_simple_macro(const char *expression, const char *macro, zbx_token_t *token)
{
	const char	*ptr;

	/* Find the end of host name by validating its name until the closing bracket }.          */
	/* {HOST.HOSTn} macro usage in the place of host name is handled by nested macro parsing. */
	for (ptr = macro + 1; ':' != *ptr; ptr++)
	{
		if ('\0' == *ptr)
			return FAIL;

		if (SUCCEED != zbx_is_hostname_char(*ptr))
			return FAIL;
	}

	/* check for empty host name */
	if (1 == ptr - macro)
		return FAIL;

	return token_parse_simple_macro_key(expression, macro, ptr + 1, token);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Finds token {} inside expression starting at the specified        *
 *          position and also searches for reference if requested.            *
 *                                                                            *
 * Parameters: expression   - [IN]                                            *
 *             pos          - [IN] starting position                          *
 *             token        - [OUT]                                           *
 *             token_search - [IN] specify if references will be searched     *
 *                                                                            *
 * Return value: SUCCEED - token was parsed successfully                      *
 *               FAIL    - expression does not contain valid token.           *
 *                                                                            *
 * Comments: The token field locations are specified as offsets from the      *
 *           beginning of the expression.                                     *
 *                                                                            *
 *           Simply iterating through tokens can be done with:                *
 *                                                                            *
 *           zbx_token_t token = {0};                                         *
 *                                                                            *
 *           while (SUCCEED == zbx_token_find(expression, token.loc.r + 1,    *
 *                       &token))                                             *
 *           {                                                                *
 *                   process_token(expression, &token);                       *
 *           }                                                                *
 *                                                                            *
 ******************************************************************************/
int	zbx_token_find(const char *expression, int pos, zbx_token_t *token, zbx_token_search_t token_search)
{
	int		ret = FAIL;
	const char	*ptr = expression + pos, *dollar = ptr;

	while (SUCCEED != ret)
	{
		int	 quoted = 0;

		/* skip macros in string constants when looking for functionid */
		for (; '{' != *ptr || 0 != quoted; ptr++)
		{
			if ('\0' == *ptr)
				break;

			if (0 != (token_search & ZBX_TOKEN_SEARCH_FUNCTIONID))
			{
				switch (*ptr)
				{
					case '\\':
						if (0 != quoted)
						{
							if ('\0' == *(++ptr))
								return FAIL;
						}
						break;
					case '"':
						quoted = !quoted;
						break;
				}
			}
		}

		if (0 != (token_search & ZBX_TOKEN_SEARCH_REFERENCES))
		{
			while (NULL != (dollar = strchr(dollar, '$')) && ptr > dollar)
			{
				if (0 == isdigit(dollar[1]))
				{
					dollar++;
					continue;
				}

				token->data.reference.index = dollar[1] - '0';
				token->type = ZBX_TOKEN_REFERENCE;
				token->loc.l = dollar - expression;
				token->loc.r = token->loc.l + 1;
				return SUCCEED;
			}

			if (NULL == dollar)
				token_search &= ~ZBX_TOKEN_SEARCH_REFERENCES;
		}

		if ('\0' == *ptr)
			return FAIL;

		if ('\0' == ptr[1])
			return FAIL;

		/* ZBX_TOKEN_SEARCH_VAR_MACRO will never return other macro types (except var func macro) */
		if (0 != (token_search & ZBX_TOKEN_SEARCH_VAR_MACRO) && '{' != ptr[1])
		{
			if (SUCCEED == (ret = token_parse_var_macro(expression, ptr, token)))
				continue;
		}

		switch (ptr[1])
		{
			case '$':
				ret = token_parse_user_macro(expression, ptr, token);
				break;
			case '#':
				ret = token_parse_lld_macro(expression, ptr, token);
				break;
			case '?':
				if (0 != (token_search & ZBX_TOKEN_SEARCH_EXPRESSION_MACRO))
					ret = token_parse_expression_macro(expression, ptr, token_search, token);
				break;
			case '{':
				ret = zbx_token_parse_nested_macro(expression, ptr, token_search, token);
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
				if (SUCCEED == (ret = token_parse_objectid(expression, ptr, token)))
					break;
				ZBX_FALLTHROUGH;
			default:
				if (SUCCEED != (ret = token_parse_macro(expression, ptr, token)) &&
						0 != (token_search & ZBX_TOKEN_SEARCH_SIMPLE_MACRO))
				{
					ret = token_parse_simple_macro(expression, ptr, token);
				}
		}

		ptr++;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: public wrapper for token_parse_user_macro() function              *
 *                                                                            *
 ******************************************************************************/
int	zbx_token_parse_user_macro(const char *expression, const char *macro, zbx_token_t *token)
{
	return token_parse_user_macro(expression, macro, token);
}

/******************************************************************************
 *                                                                            *
 * Purpose: public wrapper for token_parse_macro() function                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_token_parse_macro(const char *expression, const char *macro, zbx_token_t *token)
{
	return token_parse_macro(expression, macro, token);
}

/******************************************************************************
 *                                                                            *
 * Purpose: public wrapper for token_parse_objectid() function                *
 *                                                                            *
 ******************************************************************************/
int	zbx_token_parse_objectid(const char *expression, const char *macro, zbx_token_t *token)
{
	return token_parse_objectid(expression, macro, token);
}

/******************************************************************************
 *                                                                            *
 * Purpose: public wrapper for token_parse_lld_macro() function               *
 *                                                                            *
 ******************************************************************************/
int	zbx_token_parse_lld_macro(const char *expression, const char *macro, zbx_token_t *token)
{
	return token_parse_lld_macro(expression, macro, token);
}

/******************************************************************************
 *                                                                            *
 * Purpose: parses token with nested macros                                   *
 *                                                                            *
 * Parameters: expression   - [IN]                                            *
 *             macro        - [IN] beginning of token                         *
 *             token_search - [IN] specify if references will be searched     *
 *             token        - [OUT]                                           *
 *                                                                            *
 * Return value: SUCCEED - token was parsed successfully                      *
 *               FAIL    - macro does not point at valid function or simple   *
 *                         macro                                              *
 *                                                                            *
 * Comments: This function parses token with a macro inside it. There are     *
 *           four types of nested macros - low-level discovery function       *
 *           macros, built-in function macros, user macros  and a specific    *
 *           case of simple macros where {HOST.HOSTn} macro is used as host   *
 *           name.                                                            *
 *                                                                            *
 *           If the macro points at valid macro in the expression then        *
 *           the generic token fields are set and either the                  *
 *           token->data.lld_func_macro, token->data.func_macro or            *
 *           token->data.simple_macro (depending on token type) structure is  *
 *           filled with macro specific data.                                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_token_parse_nested_macro(const char *expression, const char *macro, zbx_token_search_t token_search,
		zbx_token_t *token)
{
	const char	*ptr;
	int		token_type = ZBX_TOKEN_UNKNOWN;
	zbx_token_t	inner_token;

	if (0 != (token_search & ZBX_TOKEN_SEARCH_VAR_MACRO))
	{
		if (SUCCEED != token_parse_var_macro(expression, macro + 1, &inner_token))
			return FAIL;

		token_type = ZBX_TOKEN_VAR_FUNC_MACRO;
		ptr = expression + inner_token.loc.r;
	}
	else if ('#' == macro[2])
	{
		/* find the end of the nested macro by validating its name until the closing bracket '}' */
		for (ptr = macro + 3; '}' != *ptr; ptr++)
		{
			if ('\0' == *ptr)
				return FAIL;

			if (SUCCEED != zbx_is_macro_char(*ptr))
				return FAIL;
		}

		/* empty macro name */
		if (3 == ptr - macro)
			return FAIL;

		token_type = ZBX_TOKEN_LLD_FUNC_MACRO;
	}
	else if ('?' == macro[2])
	{
		zbx_token_t	expr_token;

		if (0 == (token_search & ZBX_TOKEN_SEARCH_EXPRESSION_MACRO))
			return FAIL;

		if (SUCCEED != token_parse_expression_macro(expression, macro + 1, token_search, &expr_token))
			return FAIL;

		ptr = expression + expr_token.loc.r;
		token_type = ZBX_TOKEN_FUNC_MACRO;
	}
	else if ('$' == macro[2])
	{
		zbx_token_t	expr_token;

		if (SUCCEED != token_parse_user_macro(expression, macro + 1, &expr_token))
			return FAIL;

		ptr = expression + expr_token.loc.r;
		token_type = ZBX_TOKEN_USER_FUNC_MACRO;
	}
	else
	{
		if (SUCCEED != token_parse_macro(expression, macro + 1, &inner_token))
			return FAIL;

		token_type = ZBX_TOKEN_FUNC_MACRO;

		ptr = expression + inner_token.loc.r;
	}

	/* Determine the token type.                                                   */
	/* Nested macros formats:                                                      */
	/*               low-level discovery function macros  {{#MACRO}.function()}    */
	/*               function macros                      {{MACRO}.function()}     */
	/*               simple macros                        {{MACRO}:key.function()} */
	if ('.' == ptr[1])
	{
		return token_parse_func_macro(expression, macro, ptr + 2, token, token_type);
	}
	else if (0 != (token_search & ZBX_TOKEN_SEARCH_SIMPLE_MACRO) && '#' != macro[2] && ':' == ptr[1])
		return token_parse_simple_macro_key(expression, macro, ptr + 2, token);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compares substring at the specified location with the specified   *
 *          text                                                              *
 *                                                                            *
 * Parameters: src      - [IN] the source string                              *
 *             loc      - [IN] the substring location                         *
 *             text     - [IN] the text to compare with                       *
 *             text_len - [IN] the text length                                *
 *                                                                            *
 * Return value: -1 - the substring is less than the specified text           *
 *                0 - the substring is equal to the specified text            *
 *                1 - the substring is greater than the specified text        *
 *                                                                            *
 ******************************************************************************/
int	zbx_strloc_cmp(const char *src, const zbx_strloc_t *loc, const char *text, size_t text_len)
{
	size_t	src_len = loc->r - loc->l + 1;

	if (src_len < text_len)
		return -1;

	if (src_len > text_len)
		return 1;

	return memcmp(src + loc->l, text, text_len);
}

