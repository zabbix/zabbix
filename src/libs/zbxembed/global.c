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
** but WITHOUT ANY WARRANTY; without even the envied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "zbxembed.h"
#include "embed.h"
#include "global.h"
#include "duktape.h"
#include "base64.h"

/******************************************************************************
 *                                                                            *
 * Function: es_btoa                                                          *
 *                                                                            *
 * Purpose: encodes parameter to base64 string                                *
 *                                                                            *
 * Parameters: ctx - [IN] pointer to duk_context                              *
 *                                                                            *
 * Comments: Throws an error:                                                 *
 *               - if the top value at ctx value stack is not a string        *
 *               - if the value stack is empty                                *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_btoa(duk_context *ctx)
{
	duk_size_t	byte_len = 0;
	const char	*str = NULL;
	char		*b64str = NULL;

	str = duk_require_lstring(ctx, 0, &byte_len);
	str_base64_encode_dyn(str, &b64str, (int)strlen(str));
	duk_push_string(ctx, b64str);
	zbx_free(b64str);
	return 1;
}

/******************************************************************************
 *                                                                            *
 * Function: es_atob                                                          *
 *                                                                            *
 * Purpose: decodes base64 string                                             *
 *                                                                            *
 * Parameters: ctx - [IN] pointer to duk_context                              *
 *                                                                            *
 * Comments: Throws an error:                                                 *
 *               - if the top value at ctx value stack is not a string        *
 *               - if the value stack is empty                                *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_atob(duk_context *ctx)
{
	char		*buffer = NULL;
	const char	*str;
	int		out_size, buffer_size;
	duk_size_t	byte_len;

	str = duk_require_lstring(ctx, 0, &byte_len);
	buffer_size = (int)strlen(str) * 3 / 4 + 1;
	buffer = zbx_malloc(buffer, (size_t)buffer_size);
	str_base64_decode(str, buffer, buffer_size, &out_size);
	duk_push_lstring(ctx, buffer, (duk_size_t)out_size);
	zbx_free(buffer);
	return 1;
}

/******************************************************************************
 *                                                                            *
 * Function: es_init_global_functions                                         *
 *                                                                            *
 * Purpose: initializes additional global functions                           *
 *                                                                            *
 * Parameters: es - [IN] the embedded scripting engine                        *
 *                                                                            *
 ******************************************************************************/
void	es_init_global_functions(zbx_es_t *es)
{
	duk_push_c_function(es->env->ctx, es_atob, 1);
	duk_put_global_string(es->env->ctx, "atob");

	duk_push_c_function(es->env->ctx, es_btoa, 1);
	duk_put_global_string(es->env->ctx, "btoa");
}
