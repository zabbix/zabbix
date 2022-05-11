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
** but WITHOUT ANY WARRANTY; without even the envied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "global.h"

#include "common.h"
#include "embed.h"
#include "duktape.h"
#include "base64.h"
#include "sha256crypt.h"

/******************************************************************************
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
	char	*str = NULL, *b64str = NULL;

	if (SUCCEED != zbx_cesu8_to_utf8(duk_require_string(ctx, 0), &str))
		return duk_error(ctx, DUK_RET_TYPE_ERROR, "cannot convert value to utf8");

	str_base64_encode_dyn(str, &b64str, (int)strlen(str));
	duk_push_string(ctx, b64str);
	zbx_free(str);
	zbx_free(b64str);
	return 1;
}

/******************************************************************************
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
	char	*buffer = NULL, *str = NULL;
	int	out_size, buffer_size;

	if (SUCCEED != zbx_cesu8_to_utf8(duk_require_string(ctx, 0), &str))
		return duk_error(ctx, DUK_RET_TYPE_ERROR, "cannot convert value to utf8");

	buffer_size = (int)strlen(str) * 3 / 4 + 1;
	buffer = zbx_malloc(buffer, (size_t)buffer_size);
	str_base64_decode(str, buffer, buffer_size, &out_size);
	duk_push_lstring(ctx, buffer, (duk_size_t)out_size);
	zbx_free(str);
	zbx_free(buffer);
	return 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: convert binary data to hex string                                 *
 *                                                                            *
 * Parameters: bin - [IN] the data to convert                                 *
 *             len - [IN] the number of bytes to convert                      *
 *             out - [OUT] the output buffer (must be 2x + 1 of input len)    *
 *                                                                            *
 ******************************************************************************/
static void	es_bin_to_hex(const unsigned char *bin, size_t len, char *out)
{
	const char	*hex = "0123456789abcdef";
	size_t		i;

	for (i = 0; i < len; i++)
	{
		*out++ = hex[bin[i] >> 4];
		*out++ = hex[bin[i] & 15];
	}

	*out = '\0';
}

/******************************************************************************
 *                                                                            *
 * Purpose: compute a md5 checksum                                            *
 *                                                                            *
 * Parameters: ctx - [IN] pointer to duk_context                              *
 *                                                                            *
 * Comments: Throws an error:                                                 *
 *               - if the top value at ctx value stack is not a string        *
 *               - if the value stack is empty                                *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_md5(duk_context *ctx)
{
	const char	*str;
	md5_state_t	state;
	md5_byte_t	hash[MD5_DIGEST_SIZE];
	char		*md5sum;
	duk_size_t	len;

	str = duk_require_lstring(ctx, 0, &len);

	md5sum = (char *)zbx_malloc(NULL, MD5_DIGEST_SIZE * 2 + 1);

	zbx_md5_init(&state);
	zbx_md5_append(&state, (const md5_byte_t *)str, (int)len);
	zbx_md5_finish(&state, hash);

	es_bin_to_hex(hash, MD5_DIGEST_SIZE, md5sum);

	duk_push_string(ctx, md5sum);
	zbx_free(md5sum);
	return 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compute a sha256 checksum                                         *
 *                                                                            *
 * Parameters: ctx - [IN] pointer to duk_context                              *
 *                                                                            *
 * Comments: Throws an error:                                                 *
 *               - if the top value at ctx value stack is not a string        *
 *               - if the value stack is empty                                *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_sha256(duk_context *ctx)
{
	const char	*str;
	char		hash_res[ZBX_SHA256_DIGEST_SIZE], hash_res_stringhexes[ZBX_SHA256_DIGEST_SIZE * 2 + 1];
	duk_size_t	len;

	str = duk_require_lstring(ctx, 0, &len);

	zbx_sha256_hash_len(str, len, hash_res);
	es_bin_to_hex((const unsigned char *)hash_res, ZBX_SHA256_DIGEST_SIZE, hash_res_stringhexes);

	duk_push_string(ctx, hash_res_stringhexes);
	return 1;
}

/******************************************************************************
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

	duk_push_c_function(es->env->ctx, es_md5, 1);
	duk_put_global_string(es->env->ctx, "md5");

	duk_push_c_function(es->env->ctx, es_sha256, 1);
	duk_put_global_string(es->env->ctx, "sha256");
}
