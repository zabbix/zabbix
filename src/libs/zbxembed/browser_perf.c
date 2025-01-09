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

#include "browser_perf.h"
#include "zbxjson.h"

#ifdef HAVE_LIBCURL

#include "zbxalgo.h"
#include "zbxnum.h"
#include "zbxstr.h"

#define WD_PERF_TAG_ENTRY_TYPE	"entryType"

#define WD_PERF_ENTRY_NAVIGATION	"navigation"
#define WD_PERF_ENTRY_RESOURCE		"resource"
#define WD_PERF_ENTRY_MARK		"mark"
#define WD_PERF_ENTRY_MEASURE		"measure"

#define WD_PERF_ATTR_COUNT				"count"
#define WD_PERF_ATTR_REDIRECT_TIME			"redirect_time"
#define WD_PERF_ATTR_REDIRECT_COUNT			"redirect_count"
#define WD_PERF_ATTR_DNS_LOOKUP_TIME			"dns_lookup_time"
#define WD_PERF_ATTR_TCP_HANDSHAKE_TIME			"tcp_handshake_time"
#define WD_PERF_ATTR_TLS_NEGOTIATION_TIME		"tls_negotiation_time"
#define WD_PERF_ATTR_REQUEST_TIME			"request_time"
#define WD_PERF_ATTR_RESPONSE_TIME			"response_time"
#define WD_PERF_ATTR_RESOURCE_FETCH_TIME		"resource_fetch_time"
#define WD_PERF_ATTR_UNLOAD_EVENT_HANDLER_TIME		"unload_event_handler_time"
#define WD_PERF_ATTR_LOAD_EVENT_HANDLER_TIME		"load_event_handler_time"
#define WD_PERF_ATTR_DOM_CONTENT_LOADING_TIME		"dom_content_loading_time"
#define WD_PERF_ATTR_TRANSFERRED_SIZE			"transferred_size"
#define WD_PERF_ATTR_ENCODED_SIZE			"encoded_size"
#define WD_PERF_ATTR_TOTAL_SIZE				"total_size"
#define WD_PERF_ATTR_LOAD_FINISHED			"load_finished"
#define WD_PERF_ATTR_MIN_PROTOCOL			"min_protocol"

ZBX_PTR_VECTOR_IMPL(wd_attr_ptr, zbx_wd_attr_t *)
ZBX_PTR_VECTOR_IMPL(wd_perf_entry_ptr, zbx_wd_perf_entry_t *)
ZBX_VECTOR_IMPL(wd_perf_details, zbx_wd_perf_details_t)
ZBX_VECTOR_IMPL(wd_perf_bookmark, zbx_wd_perf_bookmark_t)

static int	wd_perf_attr_compare(const void *d1, const void *d2)
{
	const char *n1 = *(const char * const *)d1;
	const char *n2 = *(const char * const *)d2;

	return strcmp(n1, n2);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if the attribute contains time based metric                 *
 *                                                                            *
 * Return value: SUCCEED - attribute contains time based metric               *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	wd_perf_is_time_based_attribute(const char *name)
{
	static const char	*attributes[] = {"activation_start", "connect_end", "connect_start",
					"critical_ch_restart", "dom_complete", "dom_content_loaded_event_end",
					"dom_content_loaded_event_start", "dom_interactive", "domain_lookup_end",
					"domain_lookup_start", "duration", "fetch_start",
					"first_interim_response_start", "load_event_end", "load_event_start",
					"redirect_end", "redirect_start", "request_start", "response_end",
					"response_start", "secure_connection_start", "start_time", "unload_event_end",
					"unload_event_start", "worker_start"};
	static int		sorted = 0;

	if (0 == sorted)
	{
		/* attributes MUST be sorted as they are used in binary search, sort */
		/* it once to avoid possible sorting mistakes in array declaration   */

		qsort(attributes, ARRSIZE(attributes), sizeof(attributes[0]), wd_perf_attr_compare);
		sorted = 1;
	}


	if (NULL == bsearch(&name, attributes, ARRSIZE(attributes), sizeof(attributes[0]), wd_perf_attr_compare))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: extract attribute from json key,value pair                        *
 *                                                                            *
 ******************************************************************************/
static int	wd_perf_init_attribute_from_json(zbx_wd_attr_t *attr, const char *name, const char *p)
{
	char		*value = NULL;
	size_t		value_alloc = 0;
	zbx_json_type_t	value_type;

	if (NULL == zbx_json_decodevalue_dyn(p, &value, &value_alloc, &value_type))
	{
		struct zbx_json_parse	jp_value;
		void			*json_raw;

		if (FAIL == zbx_json_brackets_open(p, &jp_value))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot parse attribute \"%s\" value: %s",
					name, zbx_json_strerror());

			return FAIL;
		}

		json_raw = zbx_variant_data_bin_create(jp_value.start,
				(zbx_uint32_t)(jp_value.end - jp_value.start + 1));
		zbx_replace_invalid_utf8(json_raw);
		zbx_variant_set_bin(&attr->value, json_raw);
	}
	else
	{
		double	value_dbl;

		switch (value_type)
		{
			case ZBX_JSON_TYPE_INT:
			case ZBX_JSON_TYPE_NUMBER:
				(void)zbx_is_double(value, &value_dbl);

				/* convert time based attribute values to seconds */
				if (SUCCEED == wd_perf_is_time_based_attribute(name))
					value_dbl /= 1000;

				zbx_variant_set_dbl(&attr->value, value_dbl);
				zbx_free(value);
				break;
			case ZBX_JSON_TYPE_STRING:
			case ZBX_JSON_TYPE_TRUE:
			case ZBX_JSON_TYPE_FALSE:
				zbx_replace_invalid_utf8(value);
				zbx_variant_set_str(&attr->value, value);
				break;
			case ZBX_JSON_TYPE_NULL:
				zbx_variant_set_none(&attr->value);
				zbx_free(value);
				break;
			default:
				zabbix_log(LOG_LEVEL_DEBUG, "invalid attribute \"%s\" value \"%s\"", name, value);
				zbx_free(value);

				return FAIL;
		}
	}

	attr->name = zbx_strdup(NULL, name);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize attribute of floating point type                       *
 *                                                                            *
 ******************************************************************************/
static void	wd_perf_init_attribute_from_dbl(zbx_wd_attr_t *attr, const char *name, double value)
{
	attr->name = zbx_strdup(NULL, name);
	zbx_variant_set_dbl(&attr->value, value);
}

/******************************************************************************
 *                                                                            *
 * Purpose: set performance entry attribute                                   *
 *                                                                            *
 * Comments: The performance entry takes over attribute ownership.            *
 *           If the attribute has been already set its value is overwritten   *
 *           (freeing old resources).                                         *
 *                                                                            *
 ******************************************************************************/
static void	wd_perf_entry_set_attribute(zbx_wd_perf_entry_t *entry, zbx_wd_attr_t *attr_local)
{
	zbx_wd_attr_t	*attr;

	attr = (zbx_wd_attr_t *)zbx_hashset_insert(&entry->attrs, attr_local, sizeof(zbx_wd_attr_t));

	/* check if the attribute was actually inserted or existing one was returned */
	if (attr->name != attr_local->name)
	{
		/* overwrite existing attribute value */
		zbx_variant_clear(&attr->value);
		attr->value = attr_local->value;
		zbx_free(attr_local->name);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: get performance entry attribute                                   *
 *                                                                            *
 * Comments: The performance entry takes over attribute ownership.            *
 *           If the attribute has been already set its value is overwritten   *
 *           (freeing old resources).                                         *
 *                                                                            *
 ******************************************************************************/
static zbx_wd_attr_t	*wd_perf_entry_get_attribute(zbx_wd_perf_entry_t *entry, const char *name)
{
	zbx_wd_attr_t	attr_local;

	attr_local.name = (char *)(uintptr_t)name;

	return (zbx_wd_attr_t *)zbx_hashset_search(&entry->attrs, &attr_local);
}

/******************************************************************************
 *                                                                            *
 * Purpose: attribute hashset support functions                               *
 *                                                                            *
 ******************************************************************************/
static zbx_hash_t	wd_attr_hash(const void *d)
{
	const zbx_wd_attr_t	*attr = (const zbx_wd_attr_t *)d;

	return ZBX_DEFAULT_STRING_HASH_FUNC(attr->name);
}

static int	wd_attr_compare(const void *d1, const void *d2)
{
	const zbx_wd_attr_t	*attr1 = (const zbx_wd_attr_t *)d1;
	const zbx_wd_attr_t	*attr2 = (const zbx_wd_attr_t *)d2;

	return strcmp(attr1->name, attr2->name);
}

/******************************************************************************
 *                                                                            *
 * Purpose: clear attribute freeing its resources                             *
 *                                                                            *
 ******************************************************************************/
static void	wd_attr_clear(void *d)
{
	zbx_wd_attr_t	*attr = (zbx_wd_attr_t *)d;

	zbx_free(attr->name);
	zbx_variant_clear(&attr->value);
}

/******************************************************************************
 *                                                                            *
 * Purpose: create empty performance entry                                    *
 *                                                                            *
 ******************************************************************************/
static zbx_wd_perf_entry_t	*wd_perf_entry_create(void)
{
	zbx_wd_perf_entry_t	*entry;

	entry = (zbx_wd_perf_entry_t *)zbx_malloc(NULL, sizeof(zbx_wd_perf_entry_t));

	zbx_hashset_create_ext(&entry->attrs, 0, wd_attr_hash, wd_attr_compare, wd_attr_clear,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	return entry;
}

/******************************************************************************
 *                                                                            *
 * Purpose: convert lower camel case attribute name to lower snake case       *
 *                                                                            *
 * Comments: The name will be converted inside input buffer if possible.      *
 *           If the input buffer is too small, then the return value will be  *
 *           allocated and must be freed by caller.                           *
 *                                                                            *
 ******************************************************************************/
static char	*wd_convert_attribute_name(char *buf, size_t size)
{
	char	*in, *out;
	size_t	new_size = 0;
	int	upper_num = 0, lower_num = 0;

	for (out = buf; '\0' != *out; out++)
	{
		if (0 != isupper((int)*out))
		{
			if (0 == upper_num++ && 0 != lower_num)
				new_size++;
		}
		else
		{
			lower_num++;

			if (1 < upper_num)
				new_size++;

			upper_num = 0;
		}
	}

	in = out;
	new_size += (size_t)(out - buf + 1);

	if (new_size > size)
		buf = (char *)zbx_malloc(NULL, new_size);

	out = buf + new_size - 1;
	upper_num = 0;
	lower_num = 0;

	/* copy terminating zero */
	*out-- = *in--;

	/* copy the rest of data */
	while (out > buf && out != in)
	{
		if (0 != isupper((int)*in))
		{
			*out = (char)tolower((int)*in);

			if (0 == upper_num++ && 0 != lower_num)
				*--out = '_';
		}
		else
		{
			if (1 < upper_num)
				*out-- = '_';

			upper_num = 0;
			lower_num++;
			*out = *in;
		}

		in--;
		out--;
	}

	return buf;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create performance entry from retrieved entry in json format      *
 *                                                                            *
 ******************************************************************************/
static zbx_wd_perf_entry_t	*wd_perf_entry_create_from_json(const struct zbx_json_parse *jp)
{
	zbx_wd_perf_entry_t	*entry;
	const char		*p = NULL;
	char			buf[MAX_STRING_LEN], *name;

	entry = wd_perf_entry_create();

	while (NULL != (p = zbx_json_pair_next(jp, p, buf, sizeof(buf))))
	{
		zbx_wd_attr_t	attr_local;

		name = wd_convert_attribute_name(buf, sizeof(buf));

		if (SUCCEED == wd_perf_init_attribute_from_json(&attr_local, name, p))
			wd_perf_entry_set_attribute(entry, &attr_local);

		if (buf != name)
			zbx_free(name);
	}

	return entry;
}

/******************************************************************************
 *                                                                            *
 * Purpose: free performance entry                                            *
 *                                                                            *
 ******************************************************************************/
static void	wd_perf_entry_free(zbx_wd_perf_entry_t *entry)
{
	if (NULL != entry)
	{
		zbx_hashset_destroy(&entry->attrs);
		zbx_free(entry);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: merge attribute src into dst                                      *
 *                                                                            *
 * Comments: numeric values are added                                         *
 *           string/error values are concatenated with newline separator      *
 *           vectors are appended                                             *
 *                                                                            *
 ******************************************************************************/
static void	wd_attr_merge(zbx_wd_attr_t *dst, const zbx_wd_attr_t *src)
{
	char	*tmp = NULL;
	size_t	tmp_alloc = 0, tmp_offset = 0;

	if (dst->value.type != src->value.type)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot merge attribute \"%s\" values of different types \"%s\" and \"%s\"",
				dst->name, zbx_variant_type_desc(&dst->value), zbx_variant_type_desc(&src->value));
		return;
	}

	switch (dst->value.type)
	{
		case ZBX_VARIANT_STR:
			zbx_strcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, dst->value.data.str);
			zbx_chrcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, '\n');
			zbx_strcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, src->value.data.str);
			zbx_variant_clear(&dst->value);
			zbx_variant_set_str(&dst->value, tmp);
			break;
		case ZBX_VARIANT_DBL:
			dst->value.data.dbl += src->value.data.dbl;
			break;
		case ZBX_VARIANT_UI64:
			dst->value.data.ui64 += src->value.data.ui64;
			break;
		case ZBX_VARIANT_VECTOR:
			for (int i = 0; i < src->value.data.vector->values_num; i++)
			{
				zbx_variant_t	var;

				zbx_variant_copy(&var, &src->value.data.vector->values[i]);
				zbx_vector_var_append(dst->value.data.vector, var);
			}
			break;
		case ZBX_VARIANT_ERR:
			zbx_strcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, dst->value.data.err);
			zbx_chrcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, '\n');
			zbx_strcpy_alloc(&tmp, &tmp_alloc, &tmp_offset, src->value.data.err);
			zbx_variant_clear(&dst->value);
			zbx_variant_set_str(&dst->value, tmp);
			break;
		default:
			zabbix_log(LOG_LEVEL_DEBUG, "cannot merge attribute \"%s\" values of type \"%s\"",
							dst->name, zbx_variant_type_desc(&dst->value));
			break;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: merge src performance entry into dst by merging existing          *
 *          attributes and copying over new ones                              *
 *                                                                            *
 ******************************************************************************/
static void	wd_perf_entry_merge(zbx_wd_perf_entry_t *dst, zbx_wd_perf_entry_t *src)
{
	zbx_hashset_iter_t	iter;
	zbx_wd_attr_t		*attr_src, *attr_dst, attr_local;

	zbx_hashset_iter_reset(&src->attrs, &iter);
	while (NULL != (attr_src = (zbx_wd_attr_t *)zbx_hashset_iter_next(&iter)))
	{
		if (NULL == (attr_dst = (zbx_wd_attr_t *)zbx_hashset_search(&dst->attrs, attr_src)))
		{
			attr_local.name = zbx_strdup(NULL, attr_src->name);
			zbx_variant_copy(&attr_local.value, &attr_src->value);
			zbx_hashset_insert(&dst->attrs, &attr_local, sizeof(attr_local));
		}
		else
			wd_attr_merge(attr_dst, attr_src);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: copy attribute from src performance entry into dst                *
 *                                                                            *
 * Parameters: dst      - [IN] destination performance entry                  *
 *             dst_name - [IN] destination attribute name                     *
 *             src      - [IN] source performance entry                       *
 *             src_name - [IN] source attribute name                          *
 *                                                                            *
 ******************************************************************************/
static void	wd_perf_entry_copy_attr(zbx_wd_perf_entry_t *dst, const char *dst_name, zbx_wd_perf_entry_t *src,
		const char *src_name)
{
	zbx_wd_attr_t	attr_local, *attr;

	attr_local.name = zbx_strdup(NULL, dst_name);

	if (NULL != (attr = wd_perf_entry_get_attribute(src, src_name)))
		zbx_variant_copy(&attr_local.value, &attr->value);
	else
		zbx_variant_set_dbl(&attr_local.value, 0.0);

	wd_perf_entry_set_attribute(dst, &attr_local);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get attribute value as floating point or return 0 if it           *
 *          cannot be converted                                               *
 *                                                                            *
 ******************************************************************************/
static double	wd_attr_get_value_dbl(zbx_wd_attr_t *attr)
{
	zbx_variant_t	var;
	double		value;

	zbx_variant_copy(&var, &attr->value);

	if (SUCCEED == zbx_variant_convert(&var, ZBX_VARIANT_DBL))
		value = var.data.dbl;
	else
		value = 0;

	zbx_variant_clear(&var);

	return value;
}

/******************************************************************************
 *                                                                            *
 * Purpose: store difference between end and start attributes from src        *
 *          performance entry to dst_name attribute into dst                  *
 *                                                                            *
 * Parameters: dst      - [IN] destination performance entry                  *
 *             dst_name - [IN] destination attribute name                     *
 *             src      - [IN] source performance entry                       *
 *             start    - [IN] start attribute name                           *
 *             end      - [IN] end attribute name                             *
 *                                                                            *
 ******************************************************************************/
static void	wd_perf_entry_diff_attrs(zbx_wd_perf_entry_t *dst, const char *dst_name, zbx_wd_perf_entry_t *src,
		const char *start, const char *end)
{
	zbx_wd_attr_t	attr_local, *attr;
	double		value;

	attr_local.name = zbx_strdup(NULL, dst_name);

	if (NULL != (attr = wd_perf_entry_get_attribute(src, end)))
		value = wd_attr_get_value_dbl(attr);
	else
		value = 0;

	zbx_variant_set_dbl(&attr_local.value, value);

	if (NULL != (attr = wd_perf_entry_get_attribute(src, start)))
		value = wd_attr_get_value_dbl(attr);
	else
		value = 0;

	attr_local.value.data.dbl -= value;

	wd_perf_entry_set_attribute(dst, &attr_local);
}

/******************************************************************************
 *                                                                            *
 * Purpose: set minimum protocol version attribute                            *
 *                                                                            *
 * Parameters: dst - [IN] destination performance entry                       *
 *             src - [IN] source performance entry                            *
 *                                                                            *
 * Comments: Compare min_protocol attribute in dst and src performance        *
 *           entries and set the less value to dst min_protocol attribute     *
 *                                                                            *
 ******************************************************************************/
static void	wd_perf_entry_set_min_protocol(zbx_wd_perf_entry_t *dst, zbx_wd_perf_entry_t *src)
{
	zbx_wd_attr_t	*attr_src, *attr_dst, attr_local;

	if (NULL == (attr_src = wd_perf_entry_get_attribute(src, "next_hop_protocol")))
	{
		if (NULL == (attr_src = wd_perf_entry_get_attribute(src, WD_PERF_ATTR_MIN_PROTOCOL)))
			return;
	}

	if (NULL == (attr_dst = wd_perf_entry_get_attribute(dst, WD_PERF_ATTR_MIN_PROTOCOL)))
	{
		attr_local.name = zbx_strdup(NULL, WD_PERF_ATTR_MIN_PROTOCOL);
		zbx_variant_copy(&attr_local.value, &attr_src->value);
		wd_perf_entry_set_attribute(dst, &attr_local);
	}
	else
	{
		if (0 > zbx_variant_compare(&attr_src->value, &attr_dst->value))
		{
			zbx_variant_clear(&attr_dst->value);
			zbx_variant_copy(&attr_dst->value, &attr_src->value);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: return new performance entry with aggregated data                 *
 *                                                                            *
 * Parameters: in - [IN] performance entry with attributes to aggregate       *
 *                                                                            *
 * Return value: new performance entry with aggregated data                   *
 *                                                                            *
 ******************************************************************************/
static zbx_wd_perf_entry_t	*wd_perf_entry_aggregate_common_data(zbx_wd_perf_entry_t *in)
{
	zbx_wd_perf_entry_t	*out;
	zbx_wd_attr_t		attr;

	out = wd_perf_entry_create();

	wd_perf_init_attribute_from_dbl(&attr, WD_PERF_ATTR_COUNT, 1.0);
	wd_perf_entry_set_attribute(out, &attr);

	wd_perf_entry_copy_attr(out, WD_PERF_ATTR_REDIRECT_COUNT, in, "redirect_count");
	wd_perf_entry_copy_attr(out, WD_PERF_ATTR_TRANSFERRED_SIZE, in, "transfer_size");
	wd_perf_entry_copy_attr(out, WD_PERF_ATTR_ENCODED_SIZE, in, "encoded_body_size");
	wd_perf_entry_copy_attr(out, WD_PERF_ATTR_TOTAL_SIZE, in, "decoded_body_size");
	wd_perf_entry_copy_attr(out, WD_PERF_ATTR_LOAD_FINISHED, in, "load_event_end");

	wd_perf_entry_diff_attrs(out, WD_PERF_ATTR_REDIRECT_TIME, in, "redirect_start", "redirect_end");
	wd_perf_entry_diff_attrs(out, WD_PERF_ATTR_DNS_LOOKUP_TIME, in, "domain_lookup_start", "domain_lookup_end");
	wd_perf_entry_diff_attrs(out, WD_PERF_ATTR_TCP_HANDSHAKE_TIME, in, "connect_start", "connect_end");
	wd_perf_entry_diff_attrs(out, WD_PERF_ATTR_REQUEST_TIME, in, "request_start", "response_start");
	wd_perf_entry_diff_attrs(out, WD_PERF_ATTR_RESPONSE_TIME, in, "response_start", "response_end");
	wd_perf_entry_diff_attrs(out, WD_PERF_ATTR_RESOURCE_FETCH_TIME, in, "fetch_start", "response_end");
	wd_perf_entry_diff_attrs(out, WD_PERF_ATTR_UNLOAD_EVENT_HANDLER_TIME, in, "unload_event_start",
			"unload_event_end");
	wd_perf_entry_diff_attrs(out, WD_PERF_ATTR_LOAD_EVENT_HANDLER_TIME, in, "load_event_start", "load_event_end");
	wd_perf_entry_diff_attrs(out, WD_PERF_ATTR_DOM_CONTENT_LOADING_TIME, in, "dom_content_loaded_event_start",
			"dom_content_loaded_event_end");

	return out;
}

/******************************************************************************
 *                                                                            *
 * Purpose: return new performance entry with aggregated navigation data      *
 *                                                                            *
 * Parameters: in - [IN] performance entry with attributes to aggregate       *
 *                                                                            *
 * Return value: new performance entry with aggregated data                   *
 *                                                                            *
 ******************************************************************************/
static zbx_wd_perf_entry_t	*wd_perf_entry_aggregate_navigation_data(zbx_wd_perf_entry_t *in)
{
	zbx_wd_perf_entry_t	*out;

	out = wd_perf_entry_aggregate_common_data(in);
	wd_perf_entry_copy_attr(out, WD_PERF_ATTR_TLS_NEGOTIATION_TIME, in, WD_PERF_ATTR_TLS_NEGOTIATION_TIME);

	return out;
}

static int	wd_attr_ptr_compare(const void *d1, const void *d2)
{
	const zbx_wd_attr_t	*a1 = *(const zbx_wd_attr_t * const *)d1;
	const zbx_wd_attr_t	*a2 = *(const zbx_wd_attr_t * const *)d2;

	return strcmp(a1->name, a2->name);
}

/******************************************************************************
 *                                                                            *
 * Purpose: dump performance entry contents into log                          *
 *                                                                            *
 ******************************************************************************/
static void	wd_perf_dump_entry(const char *name, zbx_wd_perf_entry_t *entry)
{
	zbx_hashset_iter_t		iter;
	zbx_vector_wd_attr_ptr_t	attrs;
	zbx_wd_attr_t			*attr;

	if (NULL == entry)
		return;

	zbx_vector_wd_attr_ptr_create(&attrs);
	zbx_hashset_iter_reset(&entry->attrs, &iter);
	while (NULL != (attr = (zbx_wd_attr_t *)zbx_hashset_iter_next(&iter)))
		zbx_vector_wd_attr_ptr_append(&attrs, attr);

	zbx_vector_wd_attr_ptr_sort(&attrs, wd_attr_ptr_compare);

	zabbix_log(LOG_LEVEL_TRACE, "    %s", name);
	for (int i = 0; i < attrs.values_num; i++)
	{
		attr = attrs.values[i];
		zabbix_log(LOG_LEVEL_TRACE, "      %s: %s (%s)", attr->name, zbx_variant_value_desc(&attr->value),
				zbx_variant_type_desc(&attr->value));
	}

	zbx_vector_wd_attr_ptr_destroy(&attrs);
}

/******************************************************************************
 *                                                                            *
 * Purpose: dump collected performance data into log                          *
 *                                                                            *
 ******************************************************************************/
static void	wd_perf_dump(zbx_wd_perf_t *perf)
{
	zabbix_log(LOG_LEVEL_TRACE, "browser performance data");
	zabbix_log(LOG_LEVEL_TRACE, "details:");

	for (int i = 0; i < perf->details.values_num; i++)
	{
		zabbix_log(LOG_LEVEL_TRACE, "  %d.", i);
		wd_perf_dump_entry("navigation", perf->details.values[i].navigation);
		wd_perf_dump_entry("resource", perf->details.values[i].resource);


		zabbix_log(LOG_LEVEL_TRACE, "  user:");
		for (int j = 0; j < perf->details.values[i].user.values_num; j++)
			wd_perf_dump_entry("    ", perf->details.values[i].user.values[j]);
	}

	zabbix_log(LOG_LEVEL_TRACE, "bookmarks:");
	for (int i = 0; i < perf->bookmarks.values_num; i++)
	{
		zabbix_log(LOG_LEVEL_TRACE, "  %s", perf->bookmarks.values[i].name);
		wd_perf_dump_entry("navigation", perf->bookmarks.values[i].details->navigation);
		wd_perf_dump_entry("resource", perf->bookmarks.values[i].details->resource);
	}


	zabbix_log(LOG_LEVEL_TRACE, "summaries:");
	wd_perf_dump_entry("navigation", perf->navigation_summary);
	wd_perf_dump_entry("resource", perf->resource_summary);
}

/******************************************************************************
 *                                                                            *
 * Purpose: collect performance entries from json data                        *
 *                                                                            *
 ******************************************************************************/
int	wd_perf_collect(zbx_wd_perf_t *perf, const char *bookmark_name, const struct zbx_json_parse *jp, char **error)
{
#define WD_PERF_MAX_ENTRY_COUNT		1000
#define WD_PERF_MAX_BOOKMARK_LENGTH	1000
	const char		*p = NULL;
	zbx_wd_perf_entry_t	*entry, *resource;
	zbx_wd_perf_details_t	details = {0};
	zbx_wd_attr_t	attr;

	if (WD_PERF_MAX_ENTRY_COUNT <= perf->details.values_num)
	{
		*error = zbx_dsprintf(*error, "maximum count of performance entries has been reached (%d)",
				WD_PERF_MAX_ENTRY_COUNT);

		return FAIL;
	}

	if (NULL != bookmark_name && WD_PERF_MAX_BOOKMARK_LENGTH < zbx_strlen_utf8(bookmark_name))
	{
		*error = zbx_dsprintf(*error, "maximum allowed mark name length exceeded (%d)",
				WD_PERF_MAX_BOOKMARK_LENGTH);
		return FAIL;
	}

	zbx_vector_wd_perf_entry_ptr_create(&details.user);

	details.resource =  wd_perf_entry_create();
	wd_perf_init_attribute_from_dbl(&attr, WD_PERF_ATTR_COUNT, 0.0);
	wd_perf_entry_set_attribute(details.resource, &attr);

	while (NULL != (p = zbx_json_next(jp, p)))
	{
		struct zbx_json_parse	jp_entry;
		char			buf[MAX_STRING_LEN];

		if (SUCCEED != zbx_json_brackets_open(p, &jp_entry))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot open performance entry object");
			continue;
		}

		if (SUCCEED != zbx_json_value_by_name(&jp_entry, WD_PERF_TAG_ENTRY_TYPE, buf, sizeof(buf), NULL))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot find entryType tag in performance entry");
			continue;
		}

		if (0 == strcmp(buf, WD_PERF_ENTRY_NAVIGATION))
		{
			if (NULL != details.navigation)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "duplicate navigation entry type found in performance"
						" data");
				continue;
			}
			details.navigation = wd_perf_entry_create_from_json(&jp_entry);

			wd_perf_entry_diff_attrs(details.navigation, WD_PERF_ATTR_TLS_NEGOTIATION_TIME,
					details.navigation, "secure_connection_start", "request_start");

			entry = wd_perf_entry_aggregate_navigation_data(details.navigation);
			wd_perf_entry_merge(perf->navigation_summary, entry);
			wd_perf_entry_free(entry);

			wd_perf_entry_set_min_protocol(perf->navigation_summary, details.navigation);
		}
		else if (0 == strcmp(buf, WD_PERF_ENTRY_RESOURCE))
		{
			resource = wd_perf_entry_create_from_json(&jp_entry);

			entry = wd_perf_entry_aggregate_common_data(resource);
			wd_perf_entry_merge(perf->resource_summary, entry);
			wd_perf_entry_set_min_protocol(perf->resource_summary, resource);

			wd_perf_entry_merge(details.resource, entry);
			wd_perf_entry_free(entry);

			wd_perf_entry_set_min_protocol(details.resource, resource);
			wd_perf_entry_free(resource);
		}
		else if (0 == strcmp(buf, WD_PERF_ENTRY_MARK) || 0 == strcmp(buf, WD_PERF_ENTRY_MEASURE))
		{
			entry = wd_perf_entry_create_from_json(&jp_entry);
			zbx_vector_wd_perf_entry_ptr_append(&details.user, entry);
		}
	}

	zbx_vector_wd_perf_details_append(&perf->details, details);

	if (NULL != bookmark_name)
	{
		zbx_wd_perf_bookmark_t	bookmark;

		bookmark.name = zbx_strdup(NULL, bookmark_name);
		bookmark.details = &perf->details.values[perf->details.values_num - 1];
		zbx_vector_wd_perf_bookmark_append(&perf->bookmarks, bookmark);
	}

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_TRACE))
		wd_perf_dump(perf);

	return SUCCEED;
#undef WD_PERF_MAX_ENTRY_COUNT
#undef WD_PERF_MAX_BOOKMARK_LENGTH
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize performance collector                                  *
 *                                                                            *
 ******************************************************************************/
void	wd_perf_init(zbx_wd_perf_t *perf)
{
	zbx_vector_wd_perf_details_create(&perf->details);
	zbx_vector_wd_perf_bookmark_create(&perf->bookmarks);

	perf->navigation_summary = wd_perf_entry_create();
	perf->resource_summary = wd_perf_entry_create();

	zbx_wd_attr_t	attr;

	wd_perf_init_attribute_from_dbl(&attr, WD_PERF_ATTR_COUNT, 0.0);
	wd_perf_entry_set_attribute(perf->navigation_summary, &attr);

	wd_perf_init_attribute_from_dbl(&attr, WD_PERF_ATTR_COUNT, 0.0);
	wd_perf_entry_set_attribute(perf->resource_summary, &attr);
}

/******************************************************************************
 *                                                                            *
 * Purpose: destroy performance collector                                     *
 *                                                                            *
 ******************************************************************************/
void	wd_perf_destroy(zbx_wd_perf_t *perf)
{
	for (int i = 0; i < perf->bookmarks.values_num; i++)
		zbx_free(perf->bookmarks.values[i].name);

	zbx_vector_wd_perf_bookmark_destroy(&perf->bookmarks);

	for (int i = 0; i < perf->details.values_num; i++)
	{
		wd_perf_entry_free(perf->details.values[i].navigation);
		wd_perf_entry_free(perf->details.values[i].resource);

		zbx_vector_wd_perf_entry_ptr_clear_ext(&perf->details.values[i].user, wd_perf_entry_free);
		zbx_vector_wd_perf_entry_ptr_destroy(&perf->details.values[i].user);
	}

	zbx_vector_wd_perf_details_destroy(&perf->details);


	wd_perf_entry_free(perf->navigation_summary);
	wd_perf_entry_free(perf->resource_summary);
}

#endif
