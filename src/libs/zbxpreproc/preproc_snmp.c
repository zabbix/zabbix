/*
** Copyright (C) 2001-2024 Zabbix SIA
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
ZBX_PTR_VECTOR_IMPL(snmp_value_pair, zbx_snmp_value_pair_t *)

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

static void	snmp_value_pair_free(zbx_snmp_value_pair_t *p)
{
	zbx_free(p->oid);
	zbx_free(p->value);
	zbx_free(p);
}

static void	snmp_walk_json_output_obj_clear(zbx_snmp_walk_json_output_obj_t *obj)
{
	zbx_free(obj->key);
	zbx_vector_snmp_value_pair_clear_ext(&obj->values, snmp_value_pair_free);
	zbx_vector_snmp_value_pair_destroy(&obj->values);
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

static int	snmp_hex_to_utf8(unsigned char *value, unsigned char *out, int size)
{
	int	len;

	zbx_remove_chars((char *)value, " ");

	if (FAIL == (len = zbx_hex2bin(value, out, size)))
		return FAIL;

	out[len] = '\0';
	zbx_replace_invalid_utf8((char *)out);

	return SUCCEED;
}

static int	snmp_hex_to_utf8_dyn(char *value, char **out)
{
	size_t	len;

	if ('\0' == *value)
		return FAIL;

	len = strlen(value) / 3 + 2;
	*out = (char *)zbx_malloc(NULL, len);

	if (FAIL == snmp_hex_to_utf8((unsigned char *)value, (unsigned char *)*out, (int)len))
	{
		zbx_free(*out);
		return FAIL;
	}

	return SUCCEED;
}

static int	snmp_hex_to_mac(char *out)
{
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

static size_t	preproc_snmp_pair_parse_oid(const char *ptr, zbx_snmp_value_pair_t *p)
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
		p->oid = zbx_malloc(NULL, len + 1);
		memcpy(p->oid, start, len);
		p->oid[len] = '\0';
	}

	return len;
}

static size_t	preproc_snmp_parse_type(const char *ptr, char **type)
{
	const char	*start = ptr++;
	size_t		len;

	while (0 != isalnum(*ptr) || '-' == *ptr)
	{
		ptr++;
	}

	len = (size_t)(ptr - start);
	*type = zbx_malloc(NULL, len + 1);
	memcpy(*type, start, len);
	(*type)[len] = '\0';

	return len;
}

static size_t	preproc_snmp_parse_value(const char *ptr, zbx_snmp_value_pair_t *p)
{
	const char	*start = ptr;
	size_t		len;

	if ('"' != *ptr)
	{
		if (NULL == (ptr = strchr(ptr, '\n')))
		{
			len = strlen(start);
		}
		else
		{
			if (ZBX_SNMP_TYPE_HEX == p->type || ZBX_SNMP_TYPE_BITS == p->type)
			{
				while ('.' != *(ptr + 1) && '\0' != *(ptr + 1))
					ptr++;
			}
			else if (ZBX_SNMP_TYPE_STRING == p->type)
			{
				while ('\0' != *(ptr + 1) &&
						('\n' != *ptr || '.' != *(ptr + 1) || 0 == isdigit(*(ptr + 2))))
					ptr++;
			}

			len = (size_t)(ptr - start);
		}

		p->value = zbx_malloc(NULL, len + 1);
		memcpy(p->value, start, len);
		(p->value)[len] = '\0';

		if (ZBX_SNMP_TYPE_HEX == p->type || ZBX_SNMP_TYPE_BITS == p->type)
		{
			zbx_remove_chars(p->value, "\n");
			zbx_rtrim(p->value, " ");
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
		out = p->value = zbx_malloc(NULL, len - 1);
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

static int	preproc_snmp_parse_line(const char *data, zbx_snmp_value_pair_t *p, size_t *line_len, char **error)
{
	int		ret = FAIL;
	size_t		len;
	const char	*start = data;
	char		*type = NULL;

	if (NULL == data || '\0' == *data)
		return FAIL;

	if (0 == (len = preproc_snmp_pair_parse_oid(data, p)))
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
		len = preproc_snmp_parse_type(data, &type);
		data += len;

		if (':' != *data)
		{
			if (0 == strcmp(type, "No") &&
					0 == strncmp(data, " more variables", ZBX_CONST_STRLEN(" more variables")))
			{
				while ('\n' != *data && '\0' != *data)
					data++;

				goto eol;
			}

			if (0 == strcmp(type, "NULL") && ('\n' == *data || '\0' == *data))
			{
				p->type = ZBX_SNMP_TYPE_UNDEFINED;

				goto eol;
			}

			*error = strdup("invalid value type format");
			goto out;
		}

		data++;
		while (' ' == *data)
			data++;

		if (0 == strcmp(type, "Opaque"))
		{
			zbx_free(type);
			goto reparse_type;
		}
	}

	if (NULL != type)
	{
		if (0 == strcmp(type, "Hex-STRING"))
		{
			p->type = ZBX_SNMP_TYPE_HEX;
		}
		else if (0 == strcmp(type, "BITS"))
		{
			p->type = ZBX_SNMP_TYPE_BITS;
		}
		else if (0 == strcmp(type, "STRING"))
		{
			p->type = ZBX_SNMP_TYPE_STRING;
		}
		else
			p->type = ZBX_SNMP_TYPE_UNDEFINED;
	}
	else
		p->type = ZBX_SNMP_TYPE_UNDEFINED;

	len = preproc_snmp_parse_value(data, p);
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
		zbx_free(p->value);
		*error = strdup("invalid text following value");
		goto out;
	}

	*line_len = (size_t)(data + 1 - start);
	ret = 0;
out:
	zbx_free(type);

	if (FAIL == ret)
		zbx_free(p->oid);

	return ret;
}

static int	preproc_snmp_walk_to_pairs(zbx_hashset_t *pairs, const char *data, char **error)
{
	size_t			len;
	zbx_snmp_value_pair_t	p;

	memset(&p, 0, sizeof(zbx_snmp_value_pair_t));

	while (FAIL != preproc_snmp_parse_line(data, &p, &len, error))
	{
		if (NULL == p.value)
			p.value = zbx_strdup(NULL, "NULL");

		if (NULL == zbx_hashset_insert(pairs, &p, sizeof(zbx_snmp_value_pair_t)))
		{
			*error = zbx_dsprintf(*error, "duplicate OID detected: %s", p.oid);
			zbx_free(p.oid);
			zbx_free(p.value);
			return FAIL;
		}

		data += len;
		memset(&p, 0, sizeof(zbx_snmp_value_pair_t));
	}

	if (NULL != *error)
		return FAIL;

	return SUCCEED;
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

static int	preproc_snmp_convert_bits_value(char **value, int format, char **error)
{
#define SNMP_UINT_FROM_BITS_MAX_BYTES	(8 * 2)
#define HEX_CONV(x)			(x > '9' ? x - 'A' + 10 : x - '0')

	if (ZBX_PREPROC_SNMP_UINT_FROM_BITS == format)
	{
		zbx_uint64_t	iout = 0;
		char		*v;
		size_t		len;

		v = *value;
		zbx_remove_chars(v, " ");
		len = strlen(v);

		if (0 != len % 2)
		{
			*error = zbx_dsprintf(NULL, "Cannot convert bit value '%s' to unsigned integer", *value);
			return FAIL;
		}

		if (SNMP_UINT_FROM_BITS_MAX_BYTES < len)
			len = SNMP_UINT_FROM_BITS_MAX_BYTES;

		for (int i = 0; i < (int)len; i += 2)
		{
			char	b1, b2;

			b1 = (char)toupper(v[i]);
			b2 = (char)toupper(v[i + 1]);

			if (0 == isxdigit((unsigned char)b1) || 0 == isxdigit((unsigned char)b2))
			{
				*error = zbx_dsprintf(NULL, "Cannot convert bit value '%s' to unsigned integer",
						*value);

				return FAIL;
			}

			iout += (zbx_uint64_t)(HEX_CONV(b1) * 16 + HEX_CONV(b2)) << (i * 4);
		}

		*value = zbx_dsprintf(*value, ZBX_FS_UI64, iout);
	}

	return SUCCEED;
#undef HEX_CONV
#undef SNMP_UINT_FROM_BITS_MAX_BYTES
}

static int	preproc_snmp_convert_hex_value(char **value, int format, char **error)
{
	char	*out = NULL;

	switch (format)
	{
		case ZBX_PREPROC_SNMP_UTF8_FROM_HEX:
			if (SUCCEED != snmp_hex_to_utf8_dyn(*value, &out))
			{
				*error = zbx_dsprintf(NULL, "Cannot convert hex value '%s' to utf-8 string",
						*value);
				return FAIL;
			}

			break;
		case ZBX_PREPROC_SNMP_MAC_FROM_HEX:
			if (SUCCEED != snmp_hex_to_mac_dyn(*value, &out))
			{
				*error = zbx_dsprintf(NULL, "Cannot convert hex value '%s' to mac address",
						*value);
				return FAIL;
			}
			break;
		default:
			return SUCCEED;
	}

	zbx_free(*value);
	*value = out;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: format SNMP value according to its type and specified format      *
 *                                                                            *
 * Parameters: value  - [IN/OUT] the value to convert. If conversion succeeds *
 *                               the old value is freed and a new one is      *
 *                               allocated                                    *
 *             type   - [IN] the value type returned by SNMP                  *
 *             format - [IN] the value format specified by configuration      *
 *             error  - [OUT] the error message                               *
 *                                                                            *
 * Return value: SUCCEED - the value was converted successfully               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	preproc_snmp_convert_value(char **value, zbx_snmp_type_t type, int format, char **error)
{
	switch (type)
	{
		case ZBX_SNMP_TYPE_HEX:
			return preproc_snmp_convert_hex_value(value, format, error);
		case ZBX_SNMP_TYPE_BITS:
			return preproc_snmp_convert_bits_value(value, format, error);
		case ZBX_SNMP_TYPE_UNDEFINED:
		default:
			return SUCCEED;
	}
}

static int	preproc_snmp_value_from_walk(const char *data, const char *params, char **output, char **error)
{
	int			ret = FAIL;
	size_t			len, offset;
	zbx_snmp_value_pair_t	p;
	char			*oid_needle = NULL;
	int			format_flag = 0;
#ifdef HAVE_NETSNMP
	char			*oid_tr_tmp = NULL;
	const char		*oid_tr;
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
	memset(&p, 0, sizeof(zbx_snmp_value_pair_t));

	while (FAIL != preproc_snmp_parse_line(data, &p, &len, error))
	{
#ifdef HAVE_NETSNMP
		if (0 == strcmp(oid_tr, p.oid + offset))
#else
		if (0 == strcmp(oid_needle, p.oid + offset))
#endif
		{

			if (SUCCEED != preproc_snmp_convert_value(&p.value, p.type, format_flag, error))
			{
				zbx_free(p.oid);
				zbx_free(p.value);
				goto out;
			}

			zbx_free(p.oid);
			*output = (NULL != p.value ? p.value : zbx_strdup(NULL, "NULL"));
			ret = SUCCEED;
			goto out;
		}

		data += len;
		zbx_free(p.oid);
		zbx_free(p.value);
	}

	if (NULL == *error)
		*error = zbx_strdup(NULL, "no data was found");
out:
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

	*output = zbx_strdup(NULL, pair_cached->value);

	if (SUCCEED != preproc_snmp_convert_value(output, pair_cached->type, format_flag, error))
	{
		zbx_free(*output);
		goto out;
	}

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
			zbx_snmp_value_pair_t	*vv = outobj->values.values[k];

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
	char	*err = NULL;
	int	ret = FAIL, format;

	if (FAIL == item_preproc_convert_value(value, ZBX_VARIANT_STR, errmsg))
		return FAIL;

	zbx_remove_chars(value->data.str, "\r\n");
	zbx_rtrim(value->data.str, " ");

	switch ((format = atoi(params)))
	{
		case ZBX_PREPROC_SNMP_UTF8_FROM_HEX:
		case ZBX_PREPROC_SNMP_MAC_FROM_HEX:
			ret = preproc_snmp_convert_hex_value(&value->data.str, format, &err);
			break;
		case ZBX_PREPROC_SNMP_UINT_FROM_BITS:
			ret = preproc_snmp_convert_bits_value(&value->data.str, format, &err);
			break;
		default:
			*errmsg = zbx_dsprintf(*errmsg, "unknown parameter '%s'", params);
			return FAIL;
	}

	if (FAIL == ret)
	{
		*errmsg = zbx_dsprintf(*errmsg, "cannot extract value: %s", err);
		zbx_free(err);
		return FAIL;
	}

	return SUCCEED;
}

int	item_preproc_snmp_walk_to_json(zbx_variant_t *value, const char *params, char **errmsg)
{
	int					ret = SUCCEED;
	char					*result = NULL, *data;
	zbx_hashset_t				grouped_prefixes;
	size_t					len;
	zbx_vector_snmp_walk_to_json_param_t	parsed_params;
	zbx_snmp_value_pair_t			p;

	if (FAIL == item_preproc_convert_value(value, ZBX_VARIANT_STR, errmsg))
		return FAIL;

	zbx_vector_snmp_walk_to_json_param_create(&parsed_params);

	zbx_hashset_create_ext(&grouped_prefixes, 100, snmp_walk_json_output_obj_hash_func,
			snmp_walk_json_output_obj_compare_func, (zbx_clean_func_t)snmp_walk_json_output_obj_clear,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	if (FAIL == preproc_snmp_walk_to_json_params(params, &parsed_params))
	{
		*errmsg = zbx_dsprintf(*errmsg, "failed to parse step parameters");
		ret = FAIL;
		goto out;
	}

	data = value->data.str;
	memset(&p, 0, sizeof(zbx_snmp_value_pair_t));

	while (FAIL != preproc_snmp_parse_line(data, &p, &len, errmsg))
	{
		zbx_snmp_walk_to_json_param_t	param_field;
		size_t				prefix_len;

		for (int i = 0; i < parsed_params.values_num; i++)
		{
			zbx_snmp_walk_json_output_obj_t	*oobj_cached, oobj_local;
			zbx_snmp_value_pair_t		*output_value;

			param_field = parsed_params.values[i];
			prefix_len = strlen(param_field.oid_prefix);

			if ('.' == param_field.oid_prefix[prefix_len - 1])
			{
				param_field.oid_prefix[prefix_len - 1] = '\0';
				prefix_len--;
			}

			if ('.' != param_field.oid_prefix[0])
			{
				if (0 != strncmp(param_field.oid_prefix, p.oid + 1, prefix_len))
					continue;

				prefix_len++;
			}
			else if (0 != strncmp(param_field.oid_prefix, p.oid, prefix_len))
				continue;

			if ('.' != p.oid[prefix_len])
				continue;

			if (SUCCEED != preproc_snmp_convert_value(&p.value, p.type, param_field.format_flag, errmsg))
			{
				zbx_free(p.oid);
				zbx_free(p.value);
				ret = FAIL;
				goto out;
			}

			oobj_local.key = zbx_strdup(NULL, prefix_len + p.oid + 1);
			zbx_rtrim(oobj_local.key, " ");

			output_value = (zbx_snmp_value_pair_t *)zbx_malloc(NULL,
					sizeof(zbx_snmp_value_pair_t));

			output_value->oid = zbx_strdup(NULL, param_field.field_name);
			output_value->value = NULL == p.value ? NULL : zbx_strdup(NULL, p.value);

			if (NULL == (oobj_cached = zbx_hashset_search(&grouped_prefixes, &oobj_local)))
			{
				zbx_vector_snmp_value_pair_create(&oobj_local.values);
				zbx_vector_snmp_value_pair_append(&oobj_local.values, output_value);
				zbx_hashset_insert(&grouped_prefixes, &oobj_local, sizeof(oobj_local));
			}
			else
			{
				zbx_free(oobj_local.key);

				for (int j = 0; j < oobj_cached->values.values_num; j++)
				{
					zbx_snmp_value_pair_t *vp = oobj_cached->values.values[j];

					if (0 == strcmp(vp->oid, output_value->oid))
					{
						snmp_value_pair_free(output_value);
						goto skip;
					}
				}

				zbx_vector_snmp_value_pair_append(&oobj_cached->values, output_value);
			}
		}
skip:
		data += len;
		zbx_free(p.oid);
		zbx_free(p.value);
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
