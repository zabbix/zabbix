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

#include "item_preproc.h"
#include "preproc_snmp.h"

#ifdef HAVE_NETSNMP
#	include "pp_manager.h"
#endif

#include "zbxjson.h"
#include "zbxcrypto.h"
#include "zbxstr.h"
#include "zbxvariant.h"

ZBX_VECTOR_IMPL(snmp_walk_to_json_param, zbx_snmp_walk_to_json_param_t)
ZBX_PTR_VECTOR_IMPL(snmp_walk_to_json_output_val, zbx_snmp_walk_json_output_value_t *)
ZBX_VECTOR_IMPL(snmp_value_pair, zbx_snmp_value_pair_t)

#define ZBX_PREPROC_SNMP_UTF8_FROM_HEX	1
#define ZBX_PREPROC_SNMP_MAC_FROM_HEX	2
#define ZBX_PREPROC_SNMP_UINT_FROM_BITS	3

#ifdef HAVE_NETSNMP
static char	zbx_snmp_init_done;

static int	preproc_snmp_translate_oid(const char *oid_in, char **oid_out)
{
	char	buffer[MAX_OID_LEN];
	oid	oid_tmp[MAX_OID_LEN];
	size_t	oid_len = MAX_OID_LEN;

	if (0 != get_node(oid_in, oid_tmp, &oid_len))
	{
		snprint_objid(buffer, sizeof(buffer), oid_tmp, oid_len);
		*oid_out = zbx_strdup(NULL, buffer);
		return SUCCEED;
	}

	return FAIL;
}
#endif

static zbx_hash_t	snmp_value_pair_hash_func(const void *d)
{
	const zbx_snmp_value_pair_t	*s = (const zbx_snmp_value_pair_t *)d;

	return ZBX_DEFAULT_STRING_HASH_FUNC(s->oid);
}

static int	snmp_value_pair_compare_func(const void *d1, const void *d2)
{
	const zbx_snmp_value_pair_t	*s1 = (const zbx_snmp_value_pair_t *)d1;
	const zbx_snmp_value_pair_t	*s2 = (const zbx_snmp_value_pair_t *)d2;

	return strcmp(s1->oid, s2->oid);
}

static void	snmp_value_pair_clear(zbx_snmp_value_pair_t *p)
{
	zbx_free(p->oid);
	zbx_free(p->value);
}

static void	snmp_walk_json_output_obj_clear(zbx_snmp_walk_json_output_obj_t *obj)
{
	zbx_free(obj->key);

	for (int i = 0; i < obj->values.values_num; i++)
		snmp_value_pair_clear(&obj->values.values[i]);

	zbx_vector_snmp_value_pair_destroy(&obj->values);
}

static void	snmp_walk_json_output_obj_clear_wrapper(void *data)
{
	snmp_walk_json_output_obj_clear((zbx_snmp_walk_json_output_obj_t*)data);
}

static zbx_hash_t	snmp_walk_json_output_obj_hash_func(const void *d)
{
	const zbx_snmp_walk_json_output_obj_t	*s;
	s = (const zbx_snmp_walk_json_output_obj_t *)d;

	return ZBX_DEFAULT_STRING_HASH_FUNC(s->key);
}

static int	snmp_walk_json_output_obj_compare_func(const void *d1, const void *d2)
{
	const zbx_snmp_walk_json_output_obj_t	*s1 = (const zbx_snmp_walk_json_output_obj_t *)d1;
	const zbx_snmp_walk_json_output_obj_t	*s2 = (const zbx_snmp_walk_json_output_obj_t *)d2;

	return strcmp(s1->key, s2->key);
}

static void	snmp_parse_context_clear(zbx_snmp_parse_context_t *ctx)
{
	zbx_free(ctx->oid_buf);
	zbx_free(ctx->type_buf);
	zbx_free(ctx->val_buf);
}

static int	snmp_hex_to_utf8(const unsigned char *value, unsigned char *out, int size)
{
	int	len;

	if (FAIL == (len = zbx_hex2bin(value, out, size)))
		return FAIL;

	out[len] = '\0';
	zbx_replace_invalid_utf8((char *)out);

	return SUCCEED;
}

static int	snmp_hex_to_utf8_dyn(const char *value, char **out)
{
	int	ret = SUCCEED;
	size_t	len;
	char	*buf = NULL;

	if ('\0' == *value)
		return FAIL;

	buf = zbx_strdup(NULL, value);
	zbx_remove_chars(buf, " ");
	len = strlen(buf);

	if (0 != len % 2)
	{
		ret = FAIL;
		goto out;
	}

	*out = (char *)zbx_malloc(NULL, len);

	if (FAIL == (ret = snmp_hex_to_utf8((unsigned char *)buf, (unsigned char *)*out, (int)len)))
	{
		zbx_free(*out);
	}
out:
	zbx_free(buf);

	return ret;
}

static int	snmp_hex_to_mac(char *out)
{
	if (NULL == out)
		return FAIL;

	while ('\0' != *out)
	{
		if (0 == isxdigit((unsigned char)out[0]) || 0 == isxdigit((unsigned char)out[1]))
			return FAIL;

		if ('\0' == out[2])
			break;

		if (' ' != out[2])
			return FAIL;

		out[2] = ':';
		out += 3;
	}

	return SUCCEED;
}

static int	snmp_hex_to_mac_dyn(const char *value, char **out)
{
	*out = zbx_strdup(NULL, value);

	if (SUCCEED != snmp_hex_to_mac(*out))
	{
		zbx_free(*out);
		return FAIL;
	}

	return SUCCEED;
}

static int	preproc_snmp_walk_to_json_params(const char *params,
		zbx_vector_snmp_walk_to_json_param_t *parsed_params)
{
	char	*token = NULL, *saveptr, *field_name, *params2, *oid_prefix = NULL;
	int	format_flag , idx = 0;

	if (NULL == params || '\0' == *params)
		return FAIL;

	params2 = zbx_strdup(NULL, params);
	token = strtok_r(params2, "\n", &saveptr);

	while (NULL != token)
	{
		if (0 == idx % 3)
		{
			field_name = token;
		}
		else if (2 == idx % 3)
		{
			format_flag = atoi(token);

			zbx_snmp_walk_to_json_param_t	parsed_param;

			parsed_param.field_name = zbx_strdup(NULL, field_name);
			parsed_param.format_flag = format_flag;
			parsed_param.oid_prefix = oid_prefix;
			oid_prefix = NULL;

			zbx_vector_snmp_walk_to_json_param_append(parsed_params, parsed_param);
		}
		else
		{
#ifdef HAVE_NETSNMP
			char	*oid_tr_tmp = NULL;

			if (SUCCEED == preproc_snmp_translate_oid(token, &oid_tr_tmp))
				oid_prefix = oid_tr_tmp;
			else
				oid_prefix = zbx_strdup(NULL, token);
#else
			oid_prefix = zbx_strdup(NULL, token);
#endif
		}

		token = strtok_r(NULL, "\n", &saveptr);
		idx++;
	}

	zbx_free(params2);
	zbx_free(oid_prefix);

	if (0 != idx % 3)
		return FAIL;

	return SUCCEED;
}

static size_t	preproc_snmp_pair_parse_oid(const char *ptr, zbx_snmp_parse_context_t *ctx)
{
	const char	*start = ptr;
	size_t		len;

	while (1)
	{
		if ('.' != *ptr && 0 == isdigit((unsigned char)*ptr))
			break;
		ptr++;
	}

	if (0 != (len = (size_t)(ptr - start)))
	{
		size_t	buf_offset = 0;
		zbx_strncpy_alloc(&ctx->oid_buf, &ctx->oid_buf_alloc, &buf_offset, start, len);
	}

	return len;
}

static size_t	preproc_snmp_parse_type(const char *ptr, zbx_snmp_parse_context_t *ctx)
{
	const char	*start = ptr++;
	size_t		len, type_buf_offset = 0;

	while (0 != isalnum(*ptr) || '-' == *ptr)
	{
		ptr++;
	}

	len = (size_t)(ptr - start);
	zbx_strncpy_alloc(&ctx->type_buf, &ctx->type_buf_alloc, &type_buf_offset, start, len);

	return len;
}

static size_t	preproc_snmp_parse_value(const char *ptr, zbx_snmp_parse_context_t *ctx)
{
	const char	*start = ptr;
	size_t		len, val_buf_offset = 0;

	if ('"' != *ptr)
	{
		if (NULL == (ptr = strchr(ptr, '\n')))
		{
			len = strlen(start);
		}
		else
		{
			if (ZBX_SNMP_TYPE_HEX == ctx->type || ZBX_SNMP_TYPE_BITS == ctx->type)
			{
				while ('.' != *(ptr + 1) && '\0' != *(ptr + 1))
					ptr++;
			}
			else if (ZBX_SNMP_TYPE_STRING == ctx->type)
			{
				while ('\0' != *(ptr + 1) &&
						('\n' != *ptr || '.' != *(ptr + 1) || 0 == isdigit(*(ptr + 2))))
					ptr++;
			}

			len = (size_t)(ptr - start);
		}

		zbx_strncpy_alloc(&ctx->val_buf, &ctx->val_buf_alloc, &val_buf_offset, start, len);

		if (ZBX_SNMP_TYPE_HEX == ctx->type || ZBX_SNMP_TYPE_BITS == ctx->type)
		{
			zbx_remove_chars(ctx->val_buf, "\n");
			zbx_rtrim(ctx->val_buf, " ");
		}

		return len;
	}
	else
	{
		char	*out;
		int	escape = 0;

		ptr++;

		while ('"' != *ptr || 0 != escape)
		{
			if ('\0' == *ptr)
				return 0;

			if (0 == escape)
			{
				if ('\\' == *ptr)
					escape = 1;
			}
			else
			{
				escape = 0;
			}

			ptr++;
		}

		len = (size_t)(++ptr - start);
		zbx_strncpy_alloc(&ctx->val_buf, &ctx->val_buf_alloc, &val_buf_offset, start, len - 1);
		out = ctx->val_buf;

		ptr = start + 1;

		while ('"' != *ptr)
		{
			if ('\\' == *ptr)
			{
				ptr++;
			}
			*out++ = *ptr++;
		}
		*out = '\0';

		return len;
	}
}

static int	preproc_snmp_parse_line(const char *data, size_t *line_len, zbx_snmp_parse_context_t *ctx, char **error)
{
	int		ret = FAIL;
	size_t		len;
	const char	*start = data;

	if (NULL == data || '\0' == *data)
		return FAIL;

	if (0 == (len = preproc_snmp_pair_parse_oid(data, ctx)))
	{
		*error = strdup("invalid OID format");
		return FAIL;
	}

	data += len;
	while (' ' == *data)
		data++;

	if ('=' != *data)
	{
		*error = strdup("invalid value separator following OID");
		goto out;
	}

	data++;
reparse_type:
	while (' ' == *data)
		data++;

	if (0 != isupper((unsigned char)*data))
	{
		len = preproc_snmp_parse_type(data, ctx);
		data += len;

		if (':' != *data)
		{
			if (0 == strcmp(ctx->type_buf, "No") &&
					0 == strncmp(data, " more variables", ZBX_CONST_STRLEN(" more variables")))
			{
				while ('\n' != *data && '\0' != *data)
					data++;

				goto eol;
			}

			if (0 == strcmp(ctx->type_buf, "NULL") && ('\n' == *data || '\0' == *data))
			{
				ctx->type = ZBX_SNMP_TYPE_UNDEFINED;
				zbx_free(ctx->val_buf);
				ctx->val_buf_alloc = 0;

				goto eol;
			}

			if (0 == strcmp(ctx->type_buf, "Wrong") &&
					0 == strncmp(data, " Type", ZBX_CONST_STRLEN(" Type")))
			{
				while (':' != *data && '\0' != *data && '\n' != *data)
					data++;

				if (':' == *data)
				{
					data++;
					goto reparse_type;
				}
			}

			*error = strdup("invalid value type format");
			goto out;
		}

		data++;
		while (' ' == *data)
			data++;

		if (0 == strcmp(ctx->type_buf, "Opaque"))
			goto reparse_type;
	}

	if (NULL != ctx->type_buf)
	{
		if (0 == strcmp(ctx->type_buf, "Hex-STRING"))
		{
			ctx->type = ZBX_SNMP_TYPE_HEX;
		}
		else if (0 == strcmp(ctx->type_buf, "BITS"))
		{
			ctx->type = ZBX_SNMP_TYPE_BITS;
		}
		else if (0 == strcmp(ctx->type_buf, "STRING"))
		{
			ctx->type = ZBX_SNMP_TYPE_STRING;
		}
		else
			ctx->type = ZBX_SNMP_TYPE_UNDEFINED;
	}
	else
		ctx->type = ZBX_SNMP_TYPE_UNDEFINED;

	len = preproc_snmp_parse_value(data, ctx);

	data += len;
eol:
	if ('\0' == *data)
	{
		*line_len = (size_t)(data - start);
		ret = SUCCEED;
		goto out;
	}

	if ('\n' != *data)
	{
		*error = strdup("invalid text following value");
		goto out;
	}

	*line_len = (size_t)(data + 1 - start);
	ret = 0;
out:
	return ret;
}

static int preproc_snmp_walk_to_pairs(zbx_hashset_t *pairs, const char *data, char **error)
{
	zbx_snmp_parse_context_t	ctx = {0};
	size_t				len;
	int				ret;

	while (FAIL != (ret = preproc_snmp_parse_line(data, &len, &ctx, error)))
	{
		zbx_snmp_value_pair_t	p_copy;
		int			num_old;

		p_copy.oid = zbx_strdup(NULL, ctx.oid_buf);
		p_copy.value = (NULL != ctx.val_buf ? zbx_strdup(NULL, ctx.val_buf) : zbx_strdup(NULL, "NULL"));
		p_copy.type = ctx.type;

		num_old = pairs->num_data;

		if (NULL == zbx_hashset_insert(pairs, &p_copy, sizeof(zbx_snmp_value_pair_t)))
		{
			THIS_SHOULD_NEVER_HAPPEN_MSG("Cannot insert value pair into a hashset, allocation failed");
		}

		if (num_old == pairs->num_data)
		{
			zabbix_log(LOG_LEVEL_TRACE, "duplicate OID detected: %s", p_copy.oid);

			zbx_free(p_copy.oid);
			zbx_free(p_copy.value);
		}

		data += len;
	}

	if (NULL == *error)
		ret = SUCCEED;

	snmp_parse_context_clear(&ctx);

	return ret;
}

static void	zbx_vector_snmp_walk_to_json_param_clear_ext(zbx_vector_snmp_walk_to_json_param_t *v)
{
	for (int i = 0; i < v->values_num; i++)
	{
		zbx_free(v->values[i].field_name);
		zbx_free(v->values[i].oid_prefix);
	}

	zbx_vector_snmp_walk_to_json_param_clear(v);
}

static int	preproc_parse_value_from_walk_params(const char *params, char **oid_needle, int *format_flag)
{
	char	*delim_ptr;
	size_t	delim_offset, alloc_offset = 0, alloc_len = 0;

	if (NULL == (delim_ptr = strchr(params, '\n')))
		return FAIL;

	if (0 == (delim_offset = (size_t)(delim_ptr - params)))
		return FAIL;

	zbx_strncpy_alloc(oid_needle, &alloc_offset, &alloc_len, params, delim_offset);
	*format_flag = atoi(params + delim_offset + 1);

	return SUCCEED;
}

static int	preproc_snmp_convert_bits_value(const char *in, char **out, int format, char **error)
{
#define SNMP_UINT_FROM_BITS_MAX_BYTES	(8 * 2)
#define HEX_CONV(x)			(x > '9' ? x - 'A' + 10 : x - '0')

	if (ZBX_PREPROC_SNMP_UINT_FROM_BITS == format)
	{
		zbx_uint64_t	iout = 0;
		size_t		len;

		zbx_remove_chars((char *)in, " ");
		len = strlen(in);

		if (0 != len % 2)
		{
			*error = zbx_dsprintf(NULL, "Cannot convert bit value '%s' to unsigned integer", in);
			return FAIL;
		}

		if (SNMP_UINT_FROM_BITS_MAX_BYTES < len)
			len = SNMP_UINT_FROM_BITS_MAX_BYTES;

		for (int i = 0; i < (int)len; i += 2)
		{
			char	b1, b2;

			b1 = (char)toupper(in[i]);
			b2 = (char)toupper(in[i + 1]);

			if (0 == isxdigit((unsigned char)b1) || 0 == isxdigit((unsigned char)b2))
			{
				*error = zbx_dsprintf(NULL, "Cannot convert bit value '%s' to unsigned integer", in);

				return FAIL;
			}

			iout += (zbx_uint64_t)(HEX_CONV(b1) * 16 + HEX_CONV(b2)) << (i * 4);
		}

		*out = zbx_dsprintf(*out, ZBX_FS_UI64, iout);
	}
	else
	{
		if (NULL != in)
			*out = zbx_strdup(NULL, in);
	}

	return SUCCEED;
#undef HEX_CONV
#undef SNMP_UINT_FROM_BITS_MAX_BYTES
}

static int	preproc_snmp_convert_hex_value(const char *in, char **out, int format, char **error)
{
	char	*n = NULL;

	switch (format)
	{
		case ZBX_PREPROC_SNMP_UTF8_FROM_HEX:
			if (SUCCEED != snmp_hex_to_utf8_dyn(in, &n))
			{
				*error = zbx_dsprintf(NULL, "Cannot convert hex value '%s' to utf-8 string", in);
				return FAIL;
			}

			*out = n;
			break;
		case ZBX_PREPROC_SNMP_MAC_FROM_HEX:
			if (SUCCEED != snmp_hex_to_mac_dyn(in, &n))
			{
				*error = zbx_dsprintf(NULL, "Cannot convert hex value '%s' to mac address", in);
				return FAIL;
			}

			*out = n;
			break;
		default:
			if (NULL != in)
				*out = zbx_strdup(NULL, in);
			break;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: format SNMP value according to its type and specified format      *
 *                                                                            *
 * Parameters: in     - [IN] the value to convert                             *
 *             out    - [OUT] the output value, allocated by function         *
 *             type   - [IN] the value type returned by SNMP                  *
 *             format - [IN] the value format specified by configuration      *
 *             error  - [OUT] the error message                               *
 *                                                                            *
 * Return value: SUCCEED - the value was converted successfully               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	preproc_snmp_convert_value(const char *in, char **out, zbx_snmp_type_t type, int format, char **error)
{
	if (ZBX_SNMP_TYPE_HEX == type)
		return preproc_snmp_convert_hex_value(in, out, format, error);

	if (ZBX_SNMP_TYPE_BITS == type)
		return preproc_snmp_convert_bits_value(in, out, format, error);

	if (NULL != in)
		*out = zbx_strdup(NULL, in);

	return SUCCEED;
}

static int	preproc_snmp_value_from_walk(const char *data, const char *params, char **output, char **error)
{
	int				ret = FAIL;
	size_t				len, offset;
	zbx_snmp_parse_context_t	ctx = {0};
	char				*oid_needle = NULL;
	int				format_flag = 0;
#ifdef HAVE_NETSNMP
	char				*oid_tr_tmp = NULL;
	const char			*oid_tr;
#endif
	if (FAIL == preproc_parse_value_from_walk_params(params, &oid_needle, &format_flag))
	{
		*error = zbx_strdup(NULL, "failed to parse params");
		return FAIL;
	}

#ifdef HAVE_NETSNMP
	if (SUCCEED == preproc_snmp_translate_oid(oid_needle, &oid_tr_tmp))
		oid_tr = oid_tr_tmp;
	else
		oid_tr = oid_needle;

	offset = ('.' == oid_tr[0] ? 0 : 1);
#else
	offset = ('.' == oid_needle[0] ? 0 : 1);
#endif

	while (FAIL != preproc_snmp_parse_line(data, &len, &ctx, error))
	{
#ifdef HAVE_NETSNMP
		if (0 == strcmp(oid_tr, ctx.oid_buf + offset))
#else
		if (0 == strcmp(oid_needle, ctx.oid_buf + offset))
#endif
		{
			if (NULL == ctx.val_buf)
			{
				*output = zbx_strdup(NULL, "NULL");
				ret = SUCCEED;
				goto out;
			}

			if (SUCCEED != preproc_snmp_convert_value(ctx.val_buf, output, ctx.type, format_flag, error))
				goto out;

			ret = SUCCEED;
			goto out;
		}

		data += len;
	}

	if (NULL == *error)
		*error = zbx_strdup(NULL, "no data was found");
out:
	snmp_parse_context_clear(&ctx);
	zbx_free(oid_needle);
#ifdef HAVE_NETSNMP
	zbx_free(oid_tr_tmp);
#endif
	return ret;
}

static void	snmp_prepend_oid_dot(const char *oid_in, zbx_snmp_value_pair_t *p)
{
	if ('.' != oid_in[0])
		p->oid = zbx_dsprintf(p->oid, ".%s", oid_in);
	else
		p->oid = zbx_strdup(NULL, oid_in);
}

static int	snmp_value_from_cached_walk(zbx_snmp_value_cache_t *cache, const char *params, char **output,
		char **error)
{
	int			ret = FAIL;
	zbx_snmp_value_pair_t	*pair_cached, pair_local = {0};
	char			*oid_needle = NULL;
	int			format_flag = 0;
#ifdef HAVE_NETSNMP
	char			*oid_tr_tmp = NULL;
#endif
	if (FAIL == preproc_parse_value_from_walk_params(params, &oid_needle, &format_flag))
	{
		*error = zbx_strdup(NULL, "failed to parse params");
		return FAIL;
	}

#ifdef HAVE_NETSNMP
	if (SUCCEED == preproc_snmp_translate_oid(oid_needle, &oid_tr_tmp))
		pair_local.oid = oid_tr_tmp;
	else
		snmp_prepend_oid_dot(oid_needle, &pair_local);
#else
	snmp_prepend_oid_dot(oid_needle, &pair_local);
#endif

	if (NULL == (pair_cached = (zbx_snmp_value_pair_t *)zbx_hashset_search(&cache->pairs, &pair_local)))
	{
		*error = zbx_strdup(NULL, "no data was found");
		goto out;
	}

	if (SUCCEED != preproc_snmp_convert_value(pair_cached->value, output, pair_cached->type, format_flag, error))
		goto out;

	if (NULL == *output)
		*output = zbx_strdup(NULL, pair_cached->value);

	ret = SUCCEED;
out:
	zbx_free(pair_local.oid);
	zbx_free(oid_needle);

	return ret;
}

void	zbx_snmp_value_cache_clear(zbx_snmp_value_cache_t *cache)
{
	zbx_hashset_iter_t		iter;
	zbx_snmp_value_pair_t		*pair;

	zbx_hashset_iter_reset(&cache->pairs, &iter);

	while (NULL != (pair = (zbx_snmp_value_pair_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_free(pair->oid);
		zbx_free(pair->value);
	}

	zbx_hashset_destroy(&cache->pairs);
}

int	zbx_snmp_value_cache_init(zbx_snmp_value_cache_t *cache, const char *data, char **error)
{
	zbx_hashset_create(&cache->pairs, 100, snmp_value_pair_hash_func,
			snmp_value_pair_compare_func);

	if (FAIL == preproc_snmp_walk_to_pairs(&cache->pairs, data, error))
	{
		zbx_snmp_value_cache_clear(cache);
		return FAIL;
	}

	return SUCCEED;
}

static void	snmp_walk_serialize_json(zbx_hashset_t *grouped_prefixes, char **result)
{
	struct zbx_json			json;
	zbx_hashset_iter_t		iter;
	zbx_snmp_walk_json_output_obj_t	*outobj = NULL;

	zbx_json_initarray(&json, ZBX_JSON_STAT_BUF_LEN);

	zbx_hashset_iter_reset(grouped_prefixes, &iter);

	while (NULL != (outobj = (zbx_snmp_walk_json_output_obj_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_json_addobject(&json, NULL);
		zbx_json_addstring(&json, "{#SNMPINDEX}", outobj->key, ZBX_JSON_TYPE_STRING);

		for (int k = 0; k < outobj->values.values_num; k++)
		{
			zbx_snmp_value_pair_t	*vv = &outobj->values.values[k];

			zbx_json_addstring(&json, vv->oid, vv->value, ZBX_JSON_TYPE_STRING);
		}

		zbx_json_close(&json);
	}

	zbx_json_close(&json);
	*result = zbx_strdup(NULL, json.buffer);
	zbx_json_free(&json);
}

int	item_preproc_snmp_walk_to_value(zbx_pp_cache_t *cache, zbx_variant_t *value, const char *params,
		char **errmsg)
{
	char	*value_out = NULL, *err = NULL;
	int	ret = FAIL;

	if (NULL == params || '\0' == *params)
	{
		*errmsg = zbx_strdup(*errmsg, "parameter should be set");
		return FAIL;
	}

	if (NULL == cache || ZBX_PREPROC_SNMP_WALK_VALUE != cache->type)
	{
		if (FAIL == item_preproc_convert_value(value, ZBX_VARIANT_STR, errmsg))
			return FAIL;

		ret = preproc_snmp_value_from_walk(value->data.str, params, &value_out, &err);
	}
	else
	{
		zbx_snmp_value_cache_t	*snmp_cache;

		if (NULL != cache->error)
		{
			*errmsg = zbx_strdup(NULL, cache->error);
			return FAIL;
		}

		if (NULL == (snmp_cache = (zbx_snmp_value_cache_t *)cache->data))
		{
			if (FAIL == item_preproc_convert_value(value, ZBX_VARIANT_STR, errmsg))
				return FAIL;

			snmp_cache = (zbx_snmp_value_cache_t *)zbx_malloc(NULL, sizeof(zbx_snmp_value_cache_t));

			if (SUCCEED != zbx_snmp_value_cache_init(snmp_cache, value->data.str, &cache->error))
			{
				zbx_free(snmp_cache);
				*errmsg = zbx_strdup(NULL, cache->error);
				return FAIL;
			}

			cache->data = (void *)snmp_cache;
		}

		ret = snmp_value_from_cached_walk(snmp_cache, params, &value_out, &err);
	}

	if (FAIL == ret)
	{
		*errmsg = zbx_dsprintf(*errmsg, "unable to extract value for given OID: %s", err);
		zbx_free(err);
		return FAIL;
	}

	zbx_variant_clear(value);
	zbx_variant_set_str(value, value_out);

	return SUCCEED;
}

int	item_preproc_snmp_get_to_value(zbx_variant_t *value, const char *params, char **errmsg)
{
	char	*err = NULL, *output = NULL;
	int	ret = FAIL, format;

	if (FAIL == item_preproc_convert_value(value, ZBX_VARIANT_STR, errmsg))
		return FAIL;

	zbx_remove_chars(value->data.str, "\r\n");
	zbx_rtrim(value->data.str, " ");

	switch ((format = atoi(params)))
	{
		case ZBX_PREPROC_SNMP_UTF8_FROM_HEX:
		case ZBX_PREPROC_SNMP_MAC_FROM_HEX:
			ret = preproc_snmp_convert_hex_value(value->data.str, &output, format, &err);
			break;
		case ZBX_PREPROC_SNMP_UINT_FROM_BITS:
			ret = preproc_snmp_convert_bits_value(value->data.str, &output, format, &err);
			break;
		default:
			*errmsg = zbx_dsprintf(*errmsg, "unknown parameter '%s'", params);
			return FAIL;
	}

	if (FAIL == ret)
	{
		*errmsg = zbx_dsprintf(*errmsg, "cannot extract value: %s", err);
		zbx_free(err);
	}
	else
	{
		zbx_free(value->data.str);
		value->data.str = output;
	}

	return ret;
}

int	item_preproc_snmp_walk_to_json(zbx_variant_t *value, const char *params, char **errmsg)
{
	int					ret = SUCCEED;
	char					*result = NULL, *data;
	zbx_hashset_t				grouped_prefixes;
	size_t					len;
	zbx_vector_snmp_walk_to_json_param_t	parsed_params;
	zbx_snmp_parse_context_t		ctx = {0};

	if (FAIL == item_preproc_convert_value(value, ZBX_VARIANT_STR, errmsg))
		return FAIL;

	zbx_vector_snmp_walk_to_json_param_create(&parsed_params);

	zbx_hashset_create_ext(&grouped_prefixes, 100, snmp_walk_json_output_obj_hash_func,
			snmp_walk_json_output_obj_compare_func, snmp_walk_json_output_obj_clear_wrapper,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	if ('\0' == *(data = value->data.str))
	{
		result = zbx_strdup(NULL, "[]");
		goto out;
	}

	if (FAIL == preproc_snmp_walk_to_json_params(params, &parsed_params))
	{
		*errmsg = zbx_dsprintf(*errmsg, "failed to parse step parameters");
		ret = FAIL;
		goto out;
	}

	data = value->data.str;

	while (FAIL != preproc_snmp_parse_line(data, &len, &ctx, errmsg))
	{
		zbx_snmp_walk_to_json_param_t	param_field;
		size_t				prefix_len;

		for (int i = 0; i < parsed_params.values_num; i++)
		{
			zbx_snmp_walk_json_output_obj_t	*oobj_cached, oobj_local;
			zbx_snmp_value_pair_t		output_value = {0};

			param_field = parsed_params.values[i];
			prefix_len = strlen(param_field.oid_prefix);

			if ('.' == param_field.oid_prefix[prefix_len - 1])
			{
				param_field.oid_prefix[prefix_len - 1] = '\0';
				prefix_len--;
			}

			if ('.' != param_field.oid_prefix[0])
			{
				if (0 != strncmp(param_field.oid_prefix, ctx.oid_buf + 1, prefix_len))
					continue;

				prefix_len++;
			}
			else if (0 != strncmp(param_field.oid_prefix, ctx.oid_buf, prefix_len))
				continue;

			if ('.' != ctx.oid_buf[prefix_len])
				continue;

			if (SUCCEED != preproc_snmp_convert_value(ctx.val_buf, &output_value.value, ctx.type,
					param_field.format_flag, errmsg))
			{
				ret = FAIL;
				goto out;
			}

			output_value.oid = param_field.field_name;

			oobj_local.key = prefix_len + ctx.oid_buf + 1;
			zbx_rtrim(oobj_local.key, " ");

			if (NULL == (oobj_cached = zbx_hashset_search(&grouped_prefixes, &oobj_local)))
			{
				zbx_vector_snmp_value_pair_create(&oobj_local.values);

				output_value.oid = zbx_strdup(NULL, param_field.field_name);
				zbx_vector_snmp_value_pair_append(&oobj_local.values, output_value);

				oobj_local.key = zbx_strdup(NULL, prefix_len + ctx.oid_buf + 1);
				zbx_hashset_insert(&grouped_prefixes, &oobj_local, sizeof(oobj_local));
			}
			else
			{
				for (int j = 0; j < oobj_cached->values.values_num; j++)
				{
					zbx_snmp_value_pair_t *vp = &oobj_cached->values.values[j];

					if (0 == strcmp(vp->oid, output_value.oid))
					{
						zbx_free(output_value.value);
						goto skip;
					}
				}

				output_value.oid = zbx_strdup(NULL, param_field.field_name);
				zbx_vector_snmp_value_pair_append(&oobj_cached->values, output_value);
			}
		}
skip:
		data += len;
	}

	if (NULL != *errmsg)
	{
		ret = FAIL;
		goto out;
	}

	if (0 < grouped_prefixes.num_data)
	{
		snmp_walk_serialize_json(&grouped_prefixes, &result);
	}
	else
	{
		*errmsg = zbx_dsprintf(*errmsg, "no data was found");
		ret = FAIL;
	}
out:
	zbx_vector_snmp_walk_to_json_param_clear_ext(&parsed_params);
	zbx_vector_snmp_walk_to_json_param_destroy(&parsed_params);
	zbx_hashset_destroy(&grouped_prefixes);

	snmp_parse_context_clear(&ctx);

	if (SUCCEED == ret)
	{
		zbx_variant_clear(value);
		zbx_variant_set_str(value, result);
	}

	return ret;
}

#ifdef HAVE_NETSNMP
/* This function has to be moved to separate SNMP library when such refactoring will be done in future */
static void	zbx_init_snmp(void)
{
	sigset_t	mask, orig_mask;

	if (1 == zbx_snmp_init_done)
		return;

	sigemptyset(&mask);
	sigaddset(&mask, SIGTERM);
	sigaddset(&mask, SIGUSR2);
	sigaddset(&mask, SIGHUP);
	sigaddset(&mask, SIGQUIT);
	zbx_sigmask(SIG_BLOCK, &mask, &orig_mask);

	netsnmp_ds_set_boolean(NETSNMP_DS_LIBRARY_ID, NETSNMP_DS_LIB_DISABLE_PERSISTENT_LOAD, 1);
	netsnmp_ds_set_boolean(NETSNMP_DS_LIBRARY_ID, NETSNMP_DS_LIB_DISABLE_PERSISTENT_SAVE, 1);

	init_snmp(preproc_get_progname_cb()());

	netsnmp_init_mib();
	zbx_snmp_init_done = 1;

	zbx_sigmask(SIG_SETMASK, &orig_mask, NULL);
}

void	preproc_init_snmp(void)
{
	zbx_init_snmp();
	netsnmp_ds_set_boolean(NETSNMP_DS_LIBRARY_ID, NETSNMP_DS_LIB_PRINT_NUMERIC_OIDS, 1);
	netsnmp_ds_set_int(NETSNMP_DS_LIBRARY_ID, NETSNMP_DS_LIB_OID_OUTPUT_FORMAT, NETSNMP_OID_OUTPUT_NUMERIC);
}

void	preproc_shutdown_snmp(void)
{
	sigset_t	mask, orig_mask;

	sigemptyset(&mask);
	sigaddset(&mask, SIGTERM);
	sigaddset(&mask, SIGUSR2);
	sigaddset(&mask, SIGHUP);
	sigaddset(&mask, SIGQUIT);
	zbx_sigmask(SIG_BLOCK, &mask, &orig_mask);

	snmp_shutdown(preproc_get_progname_cb()());
	zbx_snmp_init_done = 0;

	zbx_sigmask(SIG_SETMASK, &orig_mask, NULL);
}
#endif
