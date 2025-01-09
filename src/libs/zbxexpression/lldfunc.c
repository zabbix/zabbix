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

#include "zbxexpression.h"
#include "expression.h"

#include "zbxexpr.h"
#include "zbxeval.h"
#include "zbxxml.h"
#include "zbxvariant.h"
#include "zbxregexp.h"
#include "zbxstr.h"
#include "zbxjson.h"
#include "zbxcacheconfig.h"

/******************************************************************************
 *                                                                            *
 * Purpose: expands discovery macro in expression                             *
 *                                                                            *
 * Parameters: data            - [IN/OUT] expression containing lld macro     *
 *             token           - [IN/OUT] token with lld macro location data  *
 *             flags           - [IN] flags passed to                         *
 *                                    subtitute_discovery_macros() function   *
 *             jp_row          - [IN] discovery data                          *
 *             lld_macro_paths - [IN]                                         *
 *             esc             - [IN] used in autoquoting for trigger         *
 *                                    prototypes                              *
 *                                                                            *
 ******************************************************************************/
static void	process_lld_macro_token(char **data, zbx_token_t *token, int flags, const struct zbx_json_parse *jp_row,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, int esc)
{
	char	c, *replace_to = NULL;
	int	l ,r;

	if (ZBX_TOKEN_LLD_FUNC_MACRO == token->type)
	{
		l = token->data.lld_func_macro.macro.l;
		r = token->data.lld_func_macro.macro.r;
	}
	else
	{
		l = token->loc.l;
		r = token->loc.r;
	}

	c = (*data)[r + 1];
	(*data)[r + 1] = '\0';

	if (SUCCEED != zbx_lld_macro_value_by_name(jp_row, lld_macro_paths, *data + l, &replace_to))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot substitute macro \"%s\": not found in value set", *data + l);

		(*data)[r + 1] = c;
		zbx_free(replace_to);

		return;
	}

	(*data)[r + 1] = c;

	if (ZBX_TOKEN_LLD_FUNC_MACRO == token->type)
	{
		if (SUCCEED != (zbx_calculate_macro_function(*data, &token->data.lld_func_macro, &replace_to)))
		{
			int	len = token->data.lld_func_macro.func.r - token->data.lld_func_macro.func.l + 1;

			zabbix_log(LOG_LEVEL_DEBUG, "cannot execute function \"%.*s\"", len,
					*data + token->data.lld_func_macro.func.l);

			zbx_free(replace_to);

			return;
		}
	}

	if (0 != (flags & ZBX_TOKEN_JSON))
	{
		zbx_json_escape(&replace_to);
	}
	else if (0 != (flags & ZBX_TOKEN_REGEXP))
	{
		zbx_regexp_escape(&replace_to);
	}
	else if (0 != (flags & ZBX_TOKEN_REGEXP_OUTPUT))
	{
		char	*replace_to_esc;

		replace_to_esc = zbx_dyn_escape_string(replace_to, "\\");
		zbx_free(replace_to);
		replace_to = replace_to_esc;
	}
	else if (0 != (flags & ZBX_TOKEN_XPATH))
	{
		zbx_xml_escape_xpath(&replace_to);
	}
	else if (0 != (flags & ZBX_TOKEN_PROMETHEUS))
	{
		char	*replace_to_esc;

		replace_to_esc = zbx_dyn_escape_string(replace_to, "\\\n\"");
		zbx_free(replace_to);
		replace_to = replace_to_esc;
	}
	else if (0 != (flags & ZBX_TOKEN_JSONPATH) && ZBX_TOKEN_LLD_MACRO == token->type)
	{
		char	*replace_to_esc;

		replace_to_esc = zbx_dyn_escape_string(replace_to, "\\\"");
		zbx_free(replace_to);
		replace_to = replace_to_esc;
	}
	else if (0 != (flags & ZBX_TOKEN_STRING))
	{
		if (1 == esc)
		{
			char	*replace_to_esc;

			replace_to_esc = zbx_dyn_escape_string(replace_to, "\\\"");
			zbx_free(replace_to);
			replace_to = replace_to_esc;
		}
	}
	else if (0 != (flags & ZBX_TOKEN_STR_REPLACE))
	{
		char	*replace_to_esc;

		replace_to_esc = zbx_str_printable_dyn(replace_to);

		zbx_free(replace_to);
		replace_to = replace_to_esc;
	}

	if (NULL != replace_to)
	{
		size_t	data_alloc, data_len;

		data_alloc = data_len = strlen(*data) + 1;
		token->loc.r += zbx_replace_mem_dyn(data, &data_alloc, &data_len, token->loc.l,
				token->loc.r - token->loc.l + 1, replace_to, strlen(replace_to));
		zbx_free(replace_to);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: expands discovery macro in user macro context                     *
 *                                                                            *
 * Parameters: data            - [IN/OUT] expression containing lld macro     *
 *             token           - [IN/OUT] token with user macro location data *
 *             jp_row          - [IN] discovery data                          *
 *             lld_macro_paths - [IN]                                         *
 *             error           - [OUT] error buffer                           *
 *             max_error_len   - [IN] size of error buffer                    *
 *                                                                            *
 ******************************************************************************/
static int	process_user_macro_token(char **data, zbx_token_t *token, const struct zbx_json_parse *jp_row,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, char *error, size_t max_error_len)
{
	int			force_quote, ret;
	size_t			context_r;
	char			*context, *context_esc, *errmsg = NULL;
	zbx_token_user_macro_t	*macro = &token->data.user_macro;

	/* user macro without context, nothing to replace */
	if (0 == token->data.user_macro.context.l)
		return SUCCEED;

	force_quote = ('"' == (*data)[macro->context.l]);
	context = zbx_user_macro_unquote_context_dyn(*data + macro->context.l, macro->context.r - macro->context.l + 1);

	/* substitute_lld_macros() can't fail with ZBX_TOKEN_LLD_MACRO or ZBX_TOKEN_LLD_FUNC_MACRO flags set */
	zbx_substitute_lld_macros(&context, jp_row, lld_macro_paths, ZBX_TOKEN_LLD_MACRO | ZBX_TOKEN_LLD_FUNC_MACRO,
			NULL, 0);

	if (NULL != (context_esc = zbx_user_macro_quote_context_dyn(context, force_quote, &errmsg)))
	{
		context_r = macro->context.r;
		zbx_replace_string(data, macro->context.l, &context_r, context_esc);

		token->loc.r += context_r - macro->context.r;

		zbx_free(context_esc);
		ret = SUCCEED;
	}
	else
	{
		zbx_strlcpy(error, errmsg, max_error_len);
		zbx_free(errmsg);
		ret = FAIL;
	}

	zbx_free(context);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: substitutes lld macros in calculated item query filter            *
 *                                                                            *
 * Parameters: filter          - [IN/OUT]                                     *
 *             jp_row          - [IN] lld data row                            *
 *             lld_macro_paths - [IN]                                         *
 *             error           - [OUT]                                        *
 *                                                                            *
 *  Return value: SUCCEED - macros were expanded successfully                 *
 *                FAIL    - otherwise                                         *
 *                                                                            *
 ******************************************************************************/
static int	substitute_query_filter_lld_macros(char **filter, const struct zbx_json_parse *jp_row,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, char **error)
{
	char			*errmsg = NULL, err[128], *new_filter = NULL;
	int			i, ret = FAIL;
	zbx_eval_context_t	ctx;

	if (SUCCEED != zbx_eval_parse_expression(&ctx, *filter,
			ZBX_EVAL_PARSE_QUERY_EXPRESSION | ZBX_EVAL_COMPOSE_QUOTE | ZBX_EVAL_PARSE_LLDMACRO, &errmsg))
	{
		*error = zbx_dsprintf(NULL, "cannot parse item query filter: %s", errmsg);
		zbx_free(errmsg);
		goto out;
	}

	for (i = 0; i < ctx.stack.values_num; i++)
	{
		zbx_eval_token_t	*token = &ctx.stack.values[i];
		char			*value;

		switch (token->type)
		{
			case ZBX_EVAL_TOKEN_VAR_LLDMACRO:
			case ZBX_EVAL_TOKEN_VAR_USERMACRO:
			case ZBX_EVAL_TOKEN_VAR_STR:
				value = zbx_substr_unquote(ctx.expression, token->loc.l, token->loc.r);

				if (FAIL == zbx_substitute_lld_macros(&value, jp_row, lld_macro_paths, ZBX_MACRO_ANY,
						err, sizeof(err)))
				{
					*error = zbx_strdup(NULL, err);
					zbx_free(value);

					goto clean;
				}
				break;
			default:
				continue;
		}

		zbx_variant_set_str(&token->value, value);
	}

	zbx_eval_compose_expression(&ctx, &new_filter);
	zbx_free(*filter);
	*filter = new_filter;

	ret = SUCCEED;
clean:
	zbx_eval_clear(&ctx);
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: substitutes lld macros in history function item query argument    *
 *          /host/key?[filter].                                               *
 *                                                                            *
 * Parameters: ctx             - [IN] calculated item formula                 *
 *             token           - [IN] item query token                        *
 *             jp_row          - [IN] lld data row                            *
 *             lld_macro_paths - [IN]                                         *
 *             itemquery       - [OUT] item query with expanded macros        *
 *             error           - [OUT] error message                          *
 *                                                                            *
 *  Return value: SUCCEED - macros were expanded successfully.                *
 *                FAIL    - otherwise.                                        *
 *                                                                            *
 ******************************************************************************/
static int	substitute_item_query_lld_macros(const zbx_eval_context_t *ctx, const zbx_eval_token_t *token,
		const struct zbx_json_parse *jp_row, const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths,
		char **itemquery, char **error)
{
	zbx_item_query_t	query;
	char			err[128];
	int			ret = FAIL;
	size_t			itemquery_alloc = 0, itemquery_offset = 0;

	if (0 == zbx_eval_parse_query(ctx->expression + token->loc.l, token->loc.r - token->loc.l + 1, &query))
	{
		*error = zbx_strdup(NULL, "invalid item reference");
		return FAIL;
	}

	if (SUCCEED != zbx_substitute_key_macros(&query.key, NULL, NULL, jp_row, lld_macro_paths,
			ZBX_MACRO_TYPE_ITEM_KEY, err, sizeof(err)))
	{
		*error = zbx_strdup(NULL, err);
		goto out;
	}

	if (NULL != query.filter && SUCCEED != substitute_query_filter_lld_macros(&query.filter, jp_row,
			lld_macro_paths, error))
	{
		goto out;
	}

	zbx_snprintf_alloc(itemquery, &itemquery_alloc, &itemquery_offset, "/%s/%s", ZBX_NULL2EMPTY_STR(query.host),
			query.key);
	if (NULL != query.filter)
		zbx_snprintf_alloc(itemquery, &itemquery_alloc, &itemquery_offset, "?[%s]", query.filter);

	ret = SUCCEED;
out:
	zbx_eval_clear_query(&query);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: substitutes lld macros in expression                              *
 *                                                                            *
 * Parameters: data            - [IN/OUT] expression                          *
 *             rules           - [IN] parsing rules                           *
 *             jp_row          - [IN] lld data row                            *
 *             lld_macro_paths - [IN]                                         *
 *             error           - [OUT]                                        *
 *                                                                            *
 ******************************************************************************/
int	zbx_substitute_expression_lld_macros(char **data, zbx_uint64_t rules, const struct zbx_json_parse *jp_row,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, char **error)
{
	char			*exp = NULL;
	int			i, ret = FAIL;
	zbx_eval_context_t	ctx;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() expression:%s", __func__, *data);

	if (SUCCEED != zbx_eval_parse_expression(&ctx, *data, rules, error))
		goto out;

	for (i = 0; i < ctx.stack.values_num; i++)
	{
		zbx_eval_token_t	*token = &ctx.stack.values[i];
		char			*value = NULL, err[128];

		switch(token->type)
		{
			case ZBX_EVAL_TOKEN_ARG_QUERY:
				if (FAIL == substitute_item_query_lld_macros(&ctx, token, jp_row, lld_macro_paths,
						&value, error))
				{
					goto clean;
				}
				break;
			case ZBX_EVAL_TOKEN_VAR_LLDMACRO:
			case ZBX_EVAL_TOKEN_VAR_USERMACRO:
			case ZBX_EVAL_TOKEN_VAR_STR:
			case ZBX_EVAL_TOKEN_VAR_NUM:
			case ZBX_EVAL_TOKEN_ARG_PERIOD:
				value = zbx_substr_unquote(ctx.expression, token->loc.l, token->loc.r);

				if (FAIL == zbx_substitute_lld_macros(&value, jp_row, lld_macro_paths, ZBX_MACRO_ANY,
						err, sizeof(err)))
				{
					*error = zbx_strdup(NULL, err);
					zbx_free(value);
					goto clean;
				}
				break;
			default:
				continue;
		}

		zbx_variant_clear(&token->value);
		zbx_variant_set_str(&token->value, value);
	}

	zbx_eval_compose_expression(&ctx, &exp);

	zbx_free(*data);
	*data = exp;
	exp = NULL;

	ret = SUCCEED;
clean:
	zbx_free(exp);
	zbx_eval_clear(&ctx);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() expression:%s", __func__, *data);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: expands discovery macro in expression macro.                      *
 *                                                                            *
 * Parameters: data            - [IN/OUT] expression containing macro         *
 *             token           - [IN/OUT] macro token                         *
 *             jp_row          - [IN] discovery data                          *
 *             lld_macro_paths - [IN]                                         *
 *             error           - [OUT] error message                          *
 *             error_len       - [IN] size of error buffer                    *
 *                                                                            *
 ******************************************************************************/
static int	process_expression_macro_token(char **data, zbx_token_t *token, const struct zbx_json_parse *jp_row,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, char *error, size_t error_len)
{
	char	*errmsg = NULL, *expression;
	size_t	right = token->data.expression_macro.expression.r;

	expression = zbx_substr(*data, token->data.expression_macro.expression.l,
			token->data.expression_macro.expression.r);

	if (FAIL == zbx_substitute_expression_lld_macros(&expression, ZBX_EVAL_EXPRESSION_MACRO_LLD, jp_row,
			lld_macro_paths, &errmsg))
	{
		zbx_free(expression);
		zbx_strlcpy(error, errmsg, error_len);
		zbx_free(errmsg);

		return FAIL;
	}

	zbx_replace_string(data, token->data.expression_macro.expression.l, &right, expression);
	token->loc.r += right - token->data.expression_macro.expression.r;
	zbx_free(expression);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: substitutes lld macros in function macro parameters               *
 *                                                                            *
 * Parameters: data            - [IN/OUT] pointer to buffer                   *
 *             token           - [IN/OUT] token with function macro location  *
 *                                        data                                *
 *             jp_row          - [IN] discovery data                          *
 *             lld_macro_paths - [IN]                                         *
 *             error           - [OUT] error buffer                           *
 *             max_error_len   - [IN] size of error buffer                    *
 *                                                                            *
 * Return value: SUCCEED - the lld macros were resolved successfully          *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	substitute_func_macro(char **data, zbx_token_t *token, const struct zbx_json_parse *jp_row,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, char *error, size_t max_error_len)
{
	int		ret, offset = 0;
	char		*exp = NULL;
	size_t		exp_alloc = 0, exp_offset = 0, right;
	size_t		par_l = token->data.func_macro.func_param.l, par_r = token->data.func_macro.func_param.r;
	zbx_token_t	tok;

	if (SUCCEED == zbx_token_find(*data, (int)token->data.func_macro.macro.l, &tok,
			ZBX_TOKEN_SEARCH_EXPRESSION_MACRO) && ZBX_TOKEN_EXPRESSION_MACRO == tok.type &&
			tok.loc.r <= token->data.func_macro.macro.r)
	{
		offset = (int)tok.loc.r;

		if (SUCCEED == process_expression_macro_token(data, &tok, jp_row, lld_macro_paths, error,
				max_error_len))
		{
			offset = tok.loc.r - offset;
		}
	}

	ret = zbx_substitute_function_lld_param(*data + par_l + offset + 1, par_r - (par_l + 1), 0, &exp, &exp_alloc,
			&exp_offset, jp_row, lld_macro_paths, ZBX_BACKSLASH_ESC_OFF, error, max_error_len);

	if (SUCCEED == ret)
	{
		right = par_r + offset - 1;
		zbx_replace_string(data, par_l + offset + 1, &right, exp);
		token->loc.r = right + 1;
	}

	zbx_free(exp);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Parameters: data            - [IN/OUT] pointer to buffer                   *
 *             jp_row          - [IN] discovery data                          *
 *             lld_macro_paths - [IN]                                         *
 *             flags           - [IN] ZBX_MACRO_ANY - all LLD macros will be  *
 *                                    resolved without validation of the      *
 *                                    value type.                             *
 *                                    ZBX_MACRO_NUMERIC - values for LLD      *
 *                                    macros should be numeric                *
 *                                    ZBX_MACRO_FUNC - function macros will   *
 *                                    be skipped (lld macros inside function  *
 *                                    macros will be ignored) for macros      *
 *                                    specified in func_macros array.         *
 *             error           - [OUT] should be not NULL if                  *
 *                                     ZBX_MACRO_NUMERIC flag is set          *
 *             max_error_len   - [IN] size of error buffer                    *
 *                                                                            *
 * Return value: Always SUCCEED if numeric flag is not set, otherwise SUCCEED *
 *               if all discovery macros resolved to numeric values,          *
 *               otherwise FAIL with an error message.                        *
 *                                                                            *
 ******************************************************************************/
int	zbx_substitute_lld_macros(char **data, const struct zbx_json_parse *jp_row,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, int flags, char *error, size_t max_error_len)
{
	int		ret = SUCCEED, pos = 0, prev_token_loc_r = -1, cur_token_inside_quote = 0;
	size_t		i;
	zbx_token_t	token;

	zabbix_log(LOG_LEVEL_TRACE, "In %s() data:'%s'", __func__, *data);

	while (SUCCEED == ret && SUCCEED == zbx_token_find(*data, pos, &token, ZBX_TOKEN_SEARCH_EXPRESSION_MACRO))
	{
		for (i = prev_token_loc_r + 1; i < token.loc.l; i++)
		{
			switch ((*data)[i])
			{
				case '\\':
					if (0 != cur_token_inside_quote)
						i++;
					break;
				case '"':
					cur_token_inside_quote = !cur_token_inside_quote;
					break;
			}
		}

		if (0 != (token.type & flags))
		{
			char	*m_ptr;

			switch (token.type)
			{
				case ZBX_TOKEN_LLD_MACRO:
				case ZBX_TOKEN_LLD_FUNC_MACRO:
					process_lld_macro_token(data, &token, flags, jp_row, lld_macro_paths,
							cur_token_inside_quote);
					pos = token.loc.r;
					break;
				case ZBX_TOKEN_USER_MACRO:
					ret = process_user_macro_token(data, &token, jp_row, lld_macro_paths, error,
							max_error_len);
					pos = token.loc.r;
					break;
				case ZBX_TOKEN_USER_FUNC_MACRO:
				case ZBX_TOKEN_FUNC_MACRO:
					if (NULL != (m_ptr = zbx_get_macro_from_func(*data, &token.data.func_macro,
							NULL)))
					{
						ret = substitute_func_macro(data, &token, jp_row, lld_macro_paths,
								error, max_error_len);
						pos = token.loc.r;
						zbx_free(m_ptr);
					}
					break;
				case ZBX_TOKEN_EXPRESSION_MACRO:
					if (SUCCEED == process_expression_macro_token(data, &token, jp_row,
							lld_macro_paths, error, max_error_len))
					{
						pos = token.loc.r;
					}
					break;
			}
		}
		prev_token_loc_r = token.loc.r;
		pos++;
	}

	zabbix_log(LOG_LEVEL_TRACE, "End of %s():%s data:'%s'", __func__, zbx_result_string(ret), *data);

	return ret;
}

/********************************************************************************
 *                                                                              *
 * Purpose: substitutes lld macros in function parameters.                      *
 *                                                                              *
 * Parameters: e               - [IN] function parameter list without           *
 *                                    enclosing parentheses:                    *
 *                                       <p1>, <p2>, ...<pN>                    *
 *             len             - [IN] length of function parameter list         *
 *             key_in_param    - [IN] 1 - first parameter must be host:key      *
 *                                    0 - otherwise                             *
 *             exp             - [IN/OUT] output buffer                         *
 *             exp_alloc       - [IN/OUT] size of output buffer                 *
 *             exp_offset      - [IN/OUT] current position in output buffer     *
 *             jp_row          - [IN] discovery data                            *
 *             lld_macro_paths - [IN]                                           *
 *             esc_flags       - [IN] character escaping flags                  *
 *             error           - [OUT] error message                            *
 *             max_error_len   - [IN] size of error buffer                      *
 *                                                                              *
 * Return value: SUCCEED - lld macros were resolved successfully                *
 *               FAIL - otherwise                                               *
 *                                                                              *
 ********************************************************************************/
int	zbx_substitute_function_lld_param(const char *e, size_t len, unsigned char key_in_param,
		char **exp, size_t *exp_alloc, size_t *exp_offset, const struct zbx_json_parse *jp_row,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, int esc_flags, char *error,
		size_t max_error_len)
{
	int		ret = SUCCEED;
	size_t		sep_pos;
	char		*param = NULL;
	const char	*p;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (0 == len)
	{
		zbx_strcpy_alloc(exp, exp_alloc, exp_offset, "");
		goto out;
	}

	for (p = e; p < len + e ; p += sep_pos + 1)
	{
		size_t	param_pos, param_len, rel_len = len - (p - e);
		int	quoted;

		zbx_lld_function_param_parse(p, esc_flags, &param_pos, &param_len, &sep_pos);

		/* copy what was before the parameter */
		zbx_strncpy_alloc(exp, exp_alloc, exp_offset, p, param_pos);

		/* prepare the parameter (macro substitutions and quoting) */

		zbx_free(param);
		param = zbx_function_param_unquote_dyn_ext(p + param_pos, param_len, &quoted, esc_flags);

		if (1 == key_in_param && p == e)
		{
			char	*key = NULL, *host = NULL;

			if (SUCCEED != zbx_parse_host_key(param, &host, &key) ||
					SUCCEED != substitute_key_macros_impl(&key, NULL, NULL, jp_row, lld_macro_paths,
							ZBX_MACRO_TYPE_ITEM_KEY, NULL, 0))
			{
				zbx_snprintf(error, max_error_len, "Invalid first parameter \"%s\"", param);
				zbx_free(host);
				zbx_free(key);
				ret = FAIL;
				goto out;
			}

			zbx_free(param);
			if (NULL != host)
			{
				param = zbx_dsprintf(NULL, "%s:%s", host, key);
				zbx_free(host);
				zbx_free(key);
			}
			else
				param = key;
		}
		else
			zbx_substitute_lld_macros(&param, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);

		if (SUCCEED != zbx_function_param_quote(&param, quoted, esc_flags))
		{
			zbx_snprintf(error, max_error_len, "Cannot quote parameter \"%s\"", param);
			ret = FAIL;
			goto out;
		}

		/* copy the parameter */
		zbx_strcpy_alloc(exp, exp_alloc, exp_offset, param);

		/* copy what was after the parameter (including separator) */
		if (sep_pos < rel_len)
			zbx_strncpy_alloc(exp, exp_alloc, exp_offset, p + param_pos + param_len,
					sep_pos - param_pos - param_len + 1);
	}
out:
	zbx_free(param);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: substitute LLD macros in JSON pairs.                              *
 *                                                                            *
 * Parameters: data            - [IN/OUT] pointer to a buffer that JSON pair  *
 *             jp_row          - [IN] discovery data for LLD macro            *
 *                                    substitution                            *
 *             lld_macro_paths - [IN]                                         *
 *             error           - [OUT] reason for JSON pair parsing failure   *
 *             maxerrlen       - [IN] size of error buffer                    *
 *                                                                            *
 * Return value: SUCCEED or FAIL if cannot parse JSON pair.                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_substitute_macros_in_json_pairs(char **data, const struct zbx_json_parse *jp_row,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, char *error, int maxerrlen)
{
	struct zbx_json_parse	jp_array, jp_object;
	struct zbx_json		json;
	const char		*member, *element = NULL;
	char			name[MAX_STRING_LEN], value[MAX_STRING_LEN], *p_name = NULL, *p_value = NULL;
	int			ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if ('\0' == **data)
		goto exit;

	if (SUCCEED != zbx_json_open(*data, &jp_array))
	{
		zbx_snprintf(error, maxerrlen, "cannot parse query fields: %s", zbx_json_strerror());
		ret = FAIL;
		goto exit;
	}

	if (NULL == (element = zbx_json_next(&jp_array, element)))
	{
		zbx_strlcpy(error, "cannot parse query fields: array is empty", maxerrlen);
		ret = FAIL;
		goto exit;
	}

	zbx_json_initarray(&json, ZBX_JSON_STAT_BUF_LEN);

	do
	{
		if (SUCCEED != zbx_json_brackets_open(element, &jp_object) ||
				NULL == (member = zbx_json_pair_next(&jp_object, NULL, name, sizeof(name))) ||
				NULL == zbx_json_decodevalue(member, value, sizeof(value), NULL))
		{
			zbx_snprintf(error, maxerrlen, "cannot parse query fields: %s", zbx_json_strerror());
			ret = FAIL;
			goto clean;
		}

		p_name = zbx_strdup(NULL, name);
		p_value = zbx_strdup(NULL, value);

		zbx_substitute_lld_macros(&p_name, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);
		zbx_substitute_lld_macros(&p_value, jp_row, lld_macro_paths, ZBX_MACRO_ANY, NULL, 0);

		zbx_json_addobject(&json, NULL);
		zbx_json_addstring(&json, p_name, p_value, ZBX_JSON_TYPE_STRING);
		zbx_json_close(&json);
		zbx_free(p_name);
		zbx_free(p_value);
	}
	while (NULL != (element = zbx_json_next(&jp_array, element)));

	zbx_free(*data);
	*data = zbx_strdup(NULL, json.buffer);
clean:
	zbx_json_free(&json);
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
