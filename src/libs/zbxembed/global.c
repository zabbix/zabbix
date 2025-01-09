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

#include "global.h"

#include "embed.h"
#include "duktape.h"

#include "zbxcrypto.h"
#include "zbxjson.h"
#include "zbxhash.h"

/******************************************************************************
 *                                                                            *
 * Purpose: export duktape data at the index into buffer                      *
 *                                                                            *
 * Return value: allocated buffer with exported data or NULL on error         *
 *                                                                            *
 * Comments: Throws an error:                                                 *
 *               - if the top value at ctx value stack is non buffer object   *
 *               - if the value stack is empty                                *
 *           The returned buffer must be freed by the caller.                 *
 *                                                                            *
 ******************************************************************************/
char	*es_get_buffer_dyn(duk_context *ctx, int index, duk_size_t *len)
{
	duk_int_t	type;
	const char	*ptr;
	char		*buf = NULL;

	*len = 0;

	type = duk_get_type(ctx, index);

	switch (type)
	{
		case DUK_TYPE_UNDEFINED:
		case DUK_TYPE_NONE:
		case DUK_TYPE_NULL:
			return NULL;
	}

	if (NULL != (ptr = duk_get_buffer_data(ctx, index, len)))
	{
		buf = zbx_malloc(NULL, *len);
		memcpy(buf, ptr, *len);

		return buf;
	}

	if (type == DUK_TYPE_BUFFER)
		return NULL;

	if (SUCCEED == es_duktape_string_decode(duk_safe_to_string(ctx, index), &buf))
		*len = strlen(buf);

	return buf;
}

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
	char		*str, *b64str = NULL;
	duk_size_t	len;

	if (NULL == (str = es_get_buffer_dyn(ctx, 0, &len)))
		return duk_error(ctx, DUK_RET_TYPE_ERROR, "cannot obtain parameter");

	zbx_base64_encode_dyn(str, &b64str, (int)len);
	es_push_result_string(ctx, b64str, strlen(b64str));
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
	char	*buffer = NULL, *str = NULL, *ptr;
	size_t	out_size, buffer_size;

	if (SUCCEED != es_duktape_string_decode(duk_require_string(ctx, 0), &str))
		return duk_error(ctx, DUK_RET_TYPE_ERROR, "cannot convert value to utf8");

	buffer_size = strlen(str) * 3 / 4 + 1;
	buffer = zbx_malloc(buffer, buffer_size);
	zbx_base64_decode(str, buffer, buffer_size, &out_size);
	ptr = duk_push_fixed_buffer(ctx, out_size);
	memcpy(ptr, buffer, out_size);

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
	char		*str, *md5sum;
	md5_state_t	state;
	md5_byte_t	hash[ZBX_MD5_DIGEST_SIZE];
	duk_size_t	len;

	if (NULL == (str = es_get_buffer_dyn(ctx, 0, &len)))
		return duk_error(ctx, DUK_RET_TYPE_ERROR, "cannot obtain parameter");

	md5sum = (char *)zbx_malloc(NULL, ZBX_MD5_DIGEST_SIZE * 2 + 1);

	zbx_md5_init(&state);
	zbx_md5_append(&state, (const md5_byte_t *)str, (int)len);
	zbx_md5_finish(&state, hash);

	es_bin_to_hex(hash, ZBX_MD5_DIGEST_SIZE, md5sum);

	es_push_result_string(ctx, md5sum, ZBX_MD5_DIGEST_SIZE * 2);
	zbx_free(md5sum);
	zbx_free(str);

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
	char		*str;
	char		hash_res[ZBX_SHA256_DIGEST_SIZE], hash_res_stringhexes[ZBX_SHA256_DIGEST_SIZE * 2 + 1];
	duk_size_t	len;

	if (NULL == (str = es_get_buffer_dyn(ctx, 0, &len)))
		return duk_error(ctx, DUK_RET_TYPE_ERROR, "cannot obtain parameter");

	zbx_sha256_hash_len(str, len, hash_res);
	es_bin_to_hex((const unsigned char *)hash_res, ZBX_SHA256_DIGEST_SIZE, hash_res_stringhexes);

	es_push_result_string(ctx, hash_res_stringhexes, ZBX_SHA256_DIGEST_SIZE * 2);

	zbx_free(str);

	return 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compute hmac using specified hash type                            *
 *                                                                            *
 * Parameters: ctx - [IN] pointer to duk_context                              *
 *                                                                            *
 * Comments: Throws an error:                                                 *
 *               - if the top value at ctx value stack is not a string        *
 *               - if the value stack is empty                                *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_hmac(duk_context *ctx)
{
	char			*out = NULL, *key, *text;
	const char		*type;
	duk_size_t		key_len, text_len;
	zbx_crypto_hash_t	hash_type;
	int			ret;

	type = duk_require_string(ctx, 0);
	if (0 == strcmp(type, "md5"))
		hash_type = ZBX_HASH_MD5;
	else if (0 == strcmp(type, "sha256"))
		hash_type = ZBX_HASH_SHA256;
	else
		return duk_error(ctx, DUK_RET_TYPE_ERROR, "unsupported hash function");

	if (NULL == (key = es_get_buffer_dyn(ctx, 1, &key_len)))
		return duk_error(ctx, DUK_RET_TYPE_ERROR, "cannot obtain second parameter");

	if (NULL == (text = es_get_buffer_dyn(ctx, 2, &text_len)))
	{
		zbx_free(key);
		return duk_error(ctx, DUK_RET_TYPE_ERROR, "cannot obtain third parameter");
	}

	ret = zbx_hmac(hash_type, key, key_len, text, text_len, &out);

	zbx_free(text);
	zbx_free(key);

	if (SUCCEED != ret)
		return duk_error(ctx, DUK_RET_TYPE_ERROR, "cannot calculate HMAC");

	es_push_result_string(ctx, out, strlen(out));
	zbx_free(out);

	return 1;
}

#if defined(HAVE_OPENSSL) || defined(HAVE_GNUTLS)
static void	unescape_newlines(const char *in, size_t *len, char *out)
{
	const char	*end = in + *len;

	for (; in < end; in++, out++)
	{
		if ('\\' == *in)
		{
			if ('n' == *(++in))
			{
				*out = '\n';
				*len = *len - 1;
			}
			else
				*out = *(--in);
		}
		else
			*out = *in;
	}

	*out = '\0';
}
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: sign data using RSA with SHA-256                                  *
 *                                                                            *
 * Parameters: ctx - [IN] pointer to duk_context                              *
 *                                                                            *
 * Comments: Throws an error:                                                 *
 *               - if the top value at ctx value stack is not a string        *
 *               - if the value stack is empty                                *
 *                                                                            *
 ******************************************************************************/
static duk_ret_t	es_rsa_sign(duk_context *ctx)
{
#if !defined(HAVE_OPENSSL) && !defined(HAVE_GNUTLS)
	ZBX_UNUSED(ctx);

	return duk_error(ctx, DUK_RET_TYPE_ERROR, "encryption support was not compiled in");
#else
	char		*key_unesc = NULL, *data = NULL, *error = NULL;
	unsigned char	*raw_sig = NULL;
	const char	*key_ptr;
	duk_size_t	key_len, data_len;
	duk_int_t	arg_type;
	size_t		raw_sig_len, key_unesc_alloc = 0;
	int		err_index = -1;

	if (0 != strcmp(duk_require_string(ctx, 0), "sha256"))
		return duk_error(ctx, DUK_RET_TYPE_ERROR, "unsupported hash function, only 'sha256' is supported");

	if (DUK_TYPE_UNDEFINED == (arg_type = duk_get_type(ctx, 1)))
	{
		err_index = duk_push_error_object(ctx, DUK_RET_TYPE_ERROR, "parameter 'key' is missing or is undefined");
		goto out;
	}
	else
	{
		if (DUK_TYPE_BUFFER == arg_type || DUK_TYPE_OBJECT == arg_type)
		{
			key_ptr = duk_buffer_to_string(ctx, 1);
			key_len = strlen(key_ptr);
		}
		else
			key_ptr = duk_require_lstring(ctx, 1, &key_len);

		if ('\0' == key_ptr[0])
		{
			err_index = duk_push_error_object(ctx, DUK_RET_EVAL_ERROR, "private key cannot be empty");
			goto out;
		}
	}

	data = es_get_buffer_dyn(ctx, 2, &data_len);

	if (0 == data_len)
	{
		err_index = duk_push_error_object(ctx, DUK_RET_EVAL_ERROR, "data cannot be empty");
		goto out;
	}

	if (NULL != zbx_json_decodevalue_dyn(key_ptr, &key_unesc, &key_unesc_alloc, NULL))
	{
		key_len = strlen(key_unesc);
	}
	else if (NULL != strstr(key_ptr, "\\n"))
	{
		key_unesc = zbx_calloc(NULL, key_len + 1, sizeof(char));
		unescape_newlines(key_ptr, &key_len, key_unesc);
	}
	else
	{
		key_unesc = zbx_strdup(NULL, key_ptr);
		zbx_normalize_pem(&key_unesc, &key_len);
	}

	if (SUCCEED == zbx_rs256_sign(key_unesc, key_len, data, data_len, &raw_sig, &raw_sig_len, &error))
	{
		size_t	hex_sig_len;
		char	*out = NULL;

		hex_sig_len = raw_sig_len * 2 + 1;
		out = (char *)zbx_malloc(NULL, hex_sig_len);
		zbx_bin2hex(raw_sig, raw_sig_len, out, hex_sig_len);
		zbx_free(raw_sig);

		duk_push_string(ctx, out);
		zbx_free(out);
	}
	else
		err_index = duk_push_error_object(ctx, DUK_RET_EVAL_ERROR, error);
out:
	zbx_free(error);
	zbx_free(data);
	zbx_free(key_unesc);

	if (-1 != err_index)
		return duk_throw(ctx);

	return 1;
#endif
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

	duk_push_c_function(es->env->ctx, es_hmac, 3);
	duk_put_global_string(es->env->ctx, "hmac");

	duk_push_c_function(es->env->ctx, es_rsa_sign, 3);
	duk_put_global_string(es->env->ctx, "sign");
}
