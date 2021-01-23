/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
#include "log.h"

#include "zbxalgo.h"
#include "../zbxalgo/vectorimpl.h"
#include "zbxvariant.h"
#include "zbxserialize.h"
#include "zbxserver.h"

/******************************************************************************
 *                                                                            *
 * Function: zbx_eval_get_functionids                                         *
 *                                                                            *
 * Purpose: extract functionids from the parsed expression                    *
 *                                                                            *
 * Parameters: ctx         - [IN] the evaluation context                      *
 *             functionids - [OUT] the extracted functionids                  *
 *                                                                            *
 ******************************************************************************/
void	zbx_eval_get_functionids(const zbx_eval_context_t *ctx, zbx_vector_uint64_t *functionids)
{
	int	i;

	for (i = 0; i < ctx->stack.values_num; i++)
	{
		zbx_eval_token_t	*token = &ctx->stack.values[i];

		if (ZBX_EVAL_TOKEN_FUNCTIONID == token->type)
		{
			zbx_uint64_t	functionid;

			if (SUCCEED == is_uint64_n(ctx->expression + token->loc.l + 1, token->loc.r - token->loc.l - 1,
					&functionid))
			{
				zbx_vector_uint64_append(functionids, functionid);
			}
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_eval_expand_user_macros                                      *
 *                                                                            *
 * Purpose: expand user macros in parsed expression                           *
 *                                                                            *
 * Parameters: ctx         - [IN] the evaluation context                      *
 *             hostids     - [IN] the linked hostids                          *
 *             hostids_num - [IN] the number of linked hostids                *
 *             resolver_cb - [IN] the resolver callback                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_eval_expand_user_macros(const zbx_eval_context_t *ctx, zbx_uint64_t *hostids, int hostids_num,
		zbx_macro_resolve_func_t resolver_cb)
{
	int	i;

	for (i = 0; i < ctx->stack.values_num; i++)
	{
		zbx_eval_token_t	*token = &ctx->stack.values[i];
		char			*value, *tmp;
		const char		*ptr;

		switch (token->type)
		{
			case ZBX_EVAL_TOKEN_VAR_USERMACRO:
				value = resolver_cb(ctx->expression + token->loc.l, token->loc.r - token->loc.l + 1,
						hostids, hostids_num);
				break;
			case ZBX_EVAL_TOKEN_VAR_STR:
				if (NULL == (ptr = strstr(ctx->expression + token->loc.l, "{$")) ||
						ptr >= ctx->expression + token->loc.r)
				{

					continue;
				}
				tmp = zbx_strloc_unquote_dyn(ctx->expression, &token->loc);
				value = resolver_cb(tmp, strlen(tmp), hostids, hostids_num);
				zbx_free(tmp);
				break;
			default:
				continue;
		}

		zbx_variant_set_str(&token->value, value);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_eval_set_exception                                           *
 *                                                                            *
 * Purpose: set eval context to exception that will be returned when executed *
 *                                                                            *
 * Parameters: ctx     - [IN] the evaluation context                          *
 *             message - [IN] the exception message (the memory is owned by   *
 *                       context)                                             *
 *                                                                            *
 ******************************************************************************/
void	zbx_eval_set_exception(zbx_eval_context_t *ctx, char *message)
{
	zbx_eval_token_t	*token;

	memset(ctx, 0, sizeof(zbx_eval_context_t));
	zbx_vector_eval_token_create(&ctx->stack);
	zbx_vector_eval_token_reserve(&ctx->stack, 2);

	token = ctx->stack.values;
	memset(token, 0, 2 * sizeof(zbx_eval_token_t));
	token->type = ZBX_EVAL_TOKEN_VAR_STR;
	zbx_variant_set_str(&token->value, message);
	(++token)->type = ZBX_EVAL_TOKEN_EXCEPTION;
}
