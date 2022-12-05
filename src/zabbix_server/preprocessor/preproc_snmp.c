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
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "item_preproc.h"
#include "preproc_snmp.h"
#include <zbxjson.h>

ZBX_VECTOR_IMPL(snmp_walk_to_json_param, zbx_snmp_walk_to_json_param_t)
ZBX_PTR_VECTOR_IMPL(snmp_walk_to_json_output_val, zbx_snmp_walk_json_output_value_t *)
ZBX_PTR_VECTOR_IMPL(snmp_value_pair, zbx_snmp_value_pair_t *)

static char	zbx_snmp_init_done;

static zbx_hash_t	snmp_value_pair_hash_func(const void *d)
{
	const zbx_snmp_value_pair_t	*s;
	s = (const zbx_snmp_value_pair_t *)d;

	return ZBX_DEFAULT_STRING_HASH_FUNC(s->oid);
}

static int	snmp_value_pair_compare_func(const void *d1, const void *d2)
{
	const zbx_snmp_value_pair_t	*s1 = (const zbx_snmp_value_pair_t *)d1;
	const zbx_snmp_value_pair_t	*s2 = (const zbx_snmp_value_pair_t *)d2;

	return strcmp(s1->oid, s2->oid);
}

static void snmp_value_pair_free(zbx_snmp_value_pair_t	*p)
{
	zbx_free(p->oid);
	zbx_free(p->value);
	zbx_free(p);
}

static void snmp_walk_json_output_obj_free(zbx_snmp_walk_json_output_obj_t *obj)
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

static int	preproc_snmp_translate_oid(const char *oid_in, char **oid_out)
{
	char			buffer[MAX_OID_LEN];
	oid			oid[MAX_OID_LEN];
	size_t			oid_len = MAX_OID_LEN;

	if (0 != get_node(oid_in, oid, &oid_len))
	{
		snprint_objid(buffer, sizeof(buffer), oid, oid_len);
		*oid_out = zbx_strdup(NULL, buffer);
		return SUCCEED;
	}

	return FAIL;
}

static int	preproc_snmp_walk_to_json_params(const char *params, zbx_vector_snmp_walk_to_json_param_t *parsed_params)
{
	char	*token = NULL, *saveptr, *field_name, *params2;
	int	idx = 0;

	if (NULL == params || '\0' == *params)
		return FAIL;

	params2 = zbx_strdup(NULL, params);
	token = strtok_r(params2, "\n", &saveptr);

	while (NULL != token)
	{
		if (0 == idx % 2)
		{
			field_name = token;
		}
		else
		{
			zbx_snmp_walk_to_json_param_t	parsed_param;
#ifdef HAVE_NETSNMP
			char				*oid_tr_tmp = NULL;

			if (SUCCEED == preproc_snmp_translate_oid(token, &oid_tr_tmp))
				parsed_param.oid_prefix = oid_tr_tmp;
			else
				parsed_param.oid_prefix = zbx_strdup(NULL, token);
#else
			parsed_param.oid_prefix = zbx_strdup(NULL, token);
#endif
			parsed_param.field_name = zbx_strdup(NULL, field_name);

			zbx_vector_snmp_walk_to_json_param_append(parsed_params, parsed_param);
		}

		token = strtok_r(NULL, "\n", &saveptr);
		idx++;
	}

	zbx_free(params2);

	if (0 != idx % 2)
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

	if (0 != (len = ptr - start))
	{
		p->oid = malloc(len + 1);
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

	len = ptr - start;
	*type = malloc(len + 1);
	memcpy(*type, start, len);
	(*type)[len] = '\0';

	return len;
}

static size_t	preproc_snmp_parse_value(const char *ptr, const char *type, int json, char **output)
{
	const char	*start = ptr;
	size_t 		len;

	if ('"' != *ptr)
	{

		if (NULL == (ptr = strchr(ptr, '\n')))
		{
			len = strlen(start);
		}
		else
			len = ptr - start;

		if (NULL != type && 0 == strcmp(type, "Hex-STRING") && 1 == json)
		{
			char	*buffer = zbx_malloc(NULL, len + 2);
			char	*new_str;

			zbx_strlcpy(buffer, start, len + 1);

			new_str = zbx_string_replace(buffer, " ", "\\x");
			*output = zbx_dsprintf(NULL, "\\x%s", new_str);
			zbx_free(new_str);
			zbx_free(buffer);
		}
		else
		{
			*output = malloc(len + 1);
			memcpy(*output, start, len);
			(*output)[len] = '\0';
		}

		return len;
	}
	else
	{
		char	*out;
		ptr++;

		while ('"' != *ptr)
		{
			if ('\0' == *ptr)
				return 0;
			if ('\\' == *ptr)
			{
				if ('\0' == *(++ptr))
					return 0;
			}
			ptr++;
		}

		len = ++ptr - start;
		out = *output = malloc(len - 1);
		ptr = start + 1;

		while ('"' != *ptr)
		{
			if ('\\' == *ptr)
			{
				ptr++;
				continue;
			}
			*out++ = *ptr++;
		}
		*out = '\0';
		return len;
	}
}

static int	preproc_snmp_parse_line(const char *data, zbx_snmp_value_pair_t *p, size_t *line_len, int json,
		char **error)
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

	len = preproc_snmp_parse_value(data, type, json, &p->value);
	data += len;

	if ('\0' == *data)
	{
		*line_len = data - start;
		ret = SUCCEED;
		goto out;
	}

	if ('\n' != *data)
	{
		zbx_free(p->value);
		*error = strdup("invalid text following value");
		goto out;
	}

	*line_len = data + 1 - start;
	ret = 0;
out:
	zbx_free(type);

	if (FAIL == ret)
		zbx_free(p->oid);

	return ret;
}

static int	preproc_snmp_walk_to_pairs(zbx_hashset_t *pairs, const char *data, int json, char **error)
{
	size_t			len;
	zbx_snmp_value_pair_t	p;

	memset(&p, 0, sizeof(zbx_snmp_value_pair_t));

	while (FAIL != preproc_snmp_parse_line(data, &p, &len, json, error))
	{
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
	int	i;

	for (i = 0; i < v->values_num; i++)
	{
		zbx_free(v->values[i].field_name);
		zbx_free(v->values[i].oid_prefix);
	}

	zbx_vector_snmp_walk_to_json_param_clear(v);
}

static int	preproc_snmp_value_from_walk(const char *data, const char *oid_needle, char **output, char **error)
{
	int			ret = FAIL;
	size_t			len, offset;
	zbx_snmp_value_pair_t	p;
#ifdef HAVE_NETSNMP
	char			*oid_tr_tmp = NULL;
	const char		*oid_tr;

	if (SUCCEED == preproc_snmp_translate_oid(oid_needle, &oid_tr_tmp))
		oid_tr = oid_tr_tmp;
	else
		oid_tr = oid_needle;

	offset = ('.' == oid_tr[0] ? 0 : 1);
#else
	offset = ('.' == oid_needle[0] ? 0 : 1);
#endif

	while (FAIL != preproc_snmp_parse_line(data, &p, &len, 0, error))
	{
#ifdef HAVE_NETSNMP
		if (0 == strcmp(oid_tr, p.oid + offset))
#else
		if (0 == strcmp(oid_needle, p.oid + offset))
#endif
		{
			zbx_free(p.oid);
			*output = p.value;
			ret = SUCCEED;
			goto out;
		}
		data += len;
		zbx_free(p.oid);
		zbx_free(p.value);
	}

	*error = zbx_strdup(NULL, "no data was found");
out:
#ifdef HAVE_NETSNMP
	zbx_free(oid_tr_tmp);
#endif
	return ret;
}

static int	snmp_prepend_oid_dot(const char *oid_in, zbx_snmp_value_pair_t *p)
{
	if ('.' != oid_in[0])
	{
		p->oid = zbx_dsprintf(p->oid, ".%s", oid_in);
		return 1;
	}

	return 0;
}

static int	snmp_value_from_cached_walk(zbx_snmp_value_cache_t *cache, const char *oid_needle, char **output, char **error)
{
	int			ret;
	zbx_snmp_value_pair_t	*pair_cached, pair_local;
	int			transformed = 0;
#ifdef HAVE_NETSNMP
	char			*oid_tr_tmp = NULL;

	if (SUCCEED == preproc_snmp_translate_oid(oid_needle, &oid_tr_tmp))
	{
		pair_local.oid = oid_tr_tmp;
		transformed = 1;
	}
	else
		transformed = snmp_prepend_oid_dot(oid_needle, &pair_local);
#else
	transformed = snmp_prepend_oid_dot(oid_needle, &pair_local);
#endif

	if (NULL == (pair_cached = (zbx_snmp_value_pair_t *)zbx_hashset_search(&cache->pairs, &pair_local)))
	{
		*error = zbx_strdup(NULL, "no data was found");
		ret = FAIL;
	}
	else
	{
		*output = zbx_strdup(NULL, pair_cached->value);
		ret = SUCCEED;
	}

	if (1 == transformed)
		zbx_free(pair_local.oid);

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

	if (FAIL == preproc_snmp_walk_to_pairs(&cache->pairs, data, 0, error))
		return FAIL;

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

		snmp_walk_json_output_obj_free(outobj);
		zbx_json_close(&json);
	}

	zbx_json_close(&json);
	*result = zbx_strdup(NULL, json.buffer);
	zbx_json_free(&json);
}

int	item_preproc_snmp_walk_to_value(zbx_preproc_cache_t *cache, zbx_variant_t *value, const char *params,
		char **errmsg)
{
	char	*value_out = NULL, *err = NULL;
	int	ret = FAIL;

	if (NULL == params || '\0' == *params)
	{
		*errmsg = zbx_strdup(*errmsg, "parameter should be set");
		return FAIL;
	}

	if (NULL == cache)
	{
		if (FAIL == item_preproc_convert_value(value, ZBX_VARIANT_STR, errmsg))
			return FAIL;

		ret = preproc_snmp_value_from_walk(value->data.str, params, &value_out, &err);
	}
	else
	{
		zbx_snmp_value_cache_t	*snmp_cache;

		if (NULL == (snmp_cache = (zbx_snmp_value_cache_t *)zbx_preproc_cache_get(cache,
				ZBX_PREPROC_SNMP_WALK_TO_VALUE)))
		{
			if (FAIL == item_preproc_convert_value(value, ZBX_VARIANT_STR, errmsg))
				return FAIL;

			snmp_cache = (zbx_snmp_value_cache_t *)zbx_malloc(NULL, sizeof(zbx_snmp_value_cache_t));

			if (SUCCEED != zbx_snmp_value_cache_init(snmp_cache, value->data.str, &err))
			{
				zbx_free(snmp_cache);
				goto out;
			}

			zbx_preproc_cache_put(cache, ZBX_PREPROC_SNMP_WALK_TO_VALUE, snmp_cache);
		}

		ret = snmp_value_from_cached_walk(snmp_cache, params, &value_out, &err);
	}
out:
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

	zbx_hashset_create(&grouped_prefixes, 100, snmp_walk_json_output_obj_hash_func,
			snmp_walk_json_output_obj_compare_func);

	if (FAIL == preproc_snmp_walk_to_json_params(params, &parsed_params))
	{
		*errmsg = zbx_dsprintf(*errmsg, "failed to parse step parameters");
		ret = FAIL;
		goto out;
	}

	data = value->data.str;
	memset(&p, 0, sizeof(zbx_snmp_value_pair_t));

	while (FAIL != preproc_snmp_parse_line(data, &p, &len, 1, errmsg))
	{
		int				i;
		zbx_snmp_walk_to_json_param_t	param_field;
		size_t				prefix_len;

		for (i = 0; i < parsed_params.values_num; i++)
		{
			zbx_snmp_walk_json_output_obj_t		*oobj_cached, oobj_local;
			zbx_snmp_value_pair_t			*output_value;

			param_field = parsed_params.values[i];
			prefix_len = strlen(param_field.oid_prefix);

			if ('.' != param_field.oid_prefix[0])
			{
				if (0 != strncmp(param_field.oid_prefix, p.oid + 1, prefix_len))
					continue;

				prefix_len++;
			}
			else if (0 != strncmp(param_field.oid_prefix, p.oid, prefix_len))
				continue;

			oobj_local.key = zbx_strdup(NULL, prefix_len + p.oid + 1);
			zbx_rtrim(oobj_local.key, " ");

			output_value = (zbx_snmp_value_pair_t *)zbx_malloc(NULL,
					sizeof(zbx_snmp_value_pair_t));

			output_value->oid = zbx_strdup(NULL, param_field.field_name);
			output_value->value = zbx_strdup(NULL, p.value);

			if (NULL == (oobj_cached = zbx_hashset_search(&grouped_prefixes, &oobj_local)))
			{
				zbx_vector_snmp_value_pair_create(&oobj_local.values);
				zbx_vector_snmp_value_pair_append(&oobj_local.values, output_value);
				zbx_hashset_insert(&grouped_prefixes, &oobj_local, sizeof(oobj_local));
			}
			else
			{
				zbx_free(oobj_local.key);
				zbx_vector_snmp_value_pair_append(&oobj_cached->values, output_value);
			}
		}

		data += len;
		zbx_free(p.oid);
		zbx_free(p.value);
	}

	if (NULL != *errmsg)
	{
		zbx_free(p.oid);
		zbx_free(p.value);
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
	sigprocmask(SIG_BLOCK, &mask, &orig_mask);

	init_snmp(progname);
	netsnmp_init_mib();
	zbx_snmp_init_done = 1;

	sigprocmask(SIG_SETMASK, &orig_mask, NULL);
}

void	zbx_preproc_init_snmp(void)
{
	zbx_init_snmp();
	netsnmp_ds_set_boolean(NETSNMP_DS_LIBRARY_ID, NETSNMP_DS_LIB_PRINT_NUMERIC_OIDS, 1);
	netsnmp_ds_set_int(NETSNMP_DS_LIBRARY_ID, NETSNMP_DS_LIB_OID_OUTPUT_FORMAT, NETSNMP_OID_OUTPUT_NUMERIC);
}

void	zbx_preproc_shutdown_snmp(void)
{
	sigset_t	mask, orig_mask;

	sigemptyset(&mask);
	sigaddset(&mask, SIGTERM);
	sigaddset(&mask, SIGUSR2);
	sigaddset(&mask, SIGHUP);
	sigaddset(&mask, SIGQUIT);
	sigprocmask(SIG_BLOCK, &mask, &orig_mask);

	snmp_shutdown(progname);
	zbx_snmp_init_done = 0;

	sigprocmask(SIG_SETMASK, &orig_mask, NULL);
}
