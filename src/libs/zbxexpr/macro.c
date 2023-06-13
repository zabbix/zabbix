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

/******************************************************************************
 *                                                                            *
 * Return value:  SUCCEED - char is allowed in macro name                     *
 *                FAIL    - otherwise                                         *
 *                                                                            *
 * Comments: allowed characters in macro names: '0-9A-Z._'                    *
 *           !!! Don't forget to sync the code with PHP !!!                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_is_macro_char(unsigned char c)
{
	if (0 != isupper(c))
		return SUCCEED;

	if ('.' == c || '_' == c)
		return SUCCEED;

	if (0 != isdigit(c))
		return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if name is valid discovery macro                           *
 *                                                                            *
 * Return value:  SUCCEED - name is valid discovery macro                     *
 *                FAIL    - otherwise                                         *
 *                                                                            *
 ******************************************************************************/
int	zbx_is_discovery_macro(const char *name)
{
	if ('{' != *name++ || '#' != *name++)
		return FAIL;

	do
	{
		if (SUCCEED != zbx_is_macro_char(*name++))
			return FAIL;

	} while ('}' != *name);

	if ('\0' != name[1])
		return FAIL;

	return SUCCEED;
}

#define ZBX_MACRO_REGEX_PREFIX		"regex:"

/***********************************************************************************
 *                                                                                 *
 * Purpose: parses user macro and finds its end position and context location      *
 *                                                                                 *
 * Parameters: macro      - [IN] macro to parse                                    *
 *             macro_r    - [OUT] position of ending '}' character                 *
 *             context_l  - [OUT] Position of the context start character (first   *
 *                                non space character after context separator ':') *
 *                                0 if macro does not have context specified.      *
 *             context_r  - [OUT] Position of the context end character (either    *
 *                                the ending '"' for quoted context values or the  *
 *                                last character before the ending '}' character)  *
 *                                0 if macro does not have context specified.      *
 *             context_op - [OUT] context matching operator (optional):            *
 *                                ZBX_CONDITION_OPERATOR_EQUAL                     *
 *                                ZBX_CONDITION_OPERATOR_REGEXP                    *
 *                                                                                 *
 * Return value:                                                                   *
 *     SUCCEED - Macro was parsed successfully.                                    *
 *     FAIL    - Macro parsing failed, the content of output variables             *
 *               is not defined.                                                   *
 *                                                                                 *
 ***********************************************************************************/
int	zbx_user_macro_parse(const char *macro, int *macro_r, int *context_l, int *context_r, unsigned char *context_op)
{
	int	i;

	/* find the end of macro name by skipping {$ characters and iterating through */
	/* valid macro name characters                                                */
	for (i = 2; SUCCEED == zbx_is_macro_char(macro[i]); i++)
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
			*context_op = ZBX_CONDITION_OPERATOR_EQUAL;

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
			*context_op = ZBX_CONDITION_OPERATOR_REGEXP;
			i += ZBX_CONST_STRLEN(ZBX_MACRO_REGEX_PREFIX);
		}
		else
			*context_op = ZBX_CONDITION_OPERATOR_EQUAL;
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
 * Purpose: parses user macro {$MACRO:<context>} into {$MACRO} and <context>  *
 *          strings                                                           *
 *                                                                            *
 * Parameters: macro      - [IN] macro to parse                               *
 *             name       - [OUT] macro name without context                  *
 *             context    - [OUT] unquoted macro context, NULL for macros     *
 *                                without context                             *
 *             length     - [OUT] length of parsed macro (optional)           *
 *             context_op - [OUT] context matching operator (optional):       *
 *                                ZBX_CONDITION_OPERATOR_EQUAL                *
 *                                ZBX_CONDITION_OPERATOR_REGEXP               *
 *                                                                            *
 * Return value:                                                              *
 *     SUCCEED - macro was parsed successfully                                *
 *     FAIL    - macro parsing failed, invalid parameter syntax               *
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
		if (NULL != context_op && ZBX_CONDITION_OPERATOR_REGEXP == *context_op)
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
 * Purpose: extracts macro context unquoting if necessary                     *
 *                                                                            *
 * Parameters: context - [IN] macro context inside user macro                 *
 *             len     - [IN] macro context length (including quotes for      *
 *                            quoted contexts)                                *
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

/********************************************************************************
 *                                                                              *
 * Purpose: quotes user macro context if necessary                              *
 *                                                                              *
 * Parameters: context     - [IN] macro context                                 *
 *             force_quote - [IN] if non zero then context quoting is enforced  *
 *             error       - [OUT] error message                                *
 *                                                                              *
 * Return value:                                                                *
 *     A string containing quoted macro context on success, NULL on error.      *
 *                                                                              *
 ********************************************************************************/
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
