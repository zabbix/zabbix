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

#include "lld.h"
#include "zbxalgo.h"
#include "zbxexpression.h"

ZBX_VECTOR_IMPL(lld_ext_macro, zbx_lld_ext_macro_t)

typedef struct
{
	zbx_uint64_t			itemid;
	zbx_vector_lld_ext_macro_t	macros;
}
zbx_lld_rule_macros_t;

static int	lld_ext_macro_compare(const void *d1, const void *d2)
{
	const zbx_lld_ext_macro_t	*m1 = (const zbx_lld_ext_macro_t *)d1;
	const zbx_lld_ext_macro_t	*m2 = (const zbx_lld_ext_macro_t *)d2;

	return strcmp(m1->name, m2->name);
}

static void	lld_ext_macro_clear(zbx_lld_ext_macro_t *macro)
{
	zbx_free(macro->name);
	zbx_free(macro->value);
}

static void	lld_rule_macros_clear(void *d)
{
	zbx_lld_rule_macros_t	*rule_macros = (zbx_lld_rule_macros_t *)d;

	for (int i = 0; i < rule_macros->macros.values_num; i++)
		lld_ext_macro_clear(&rule_macros->macros.values[i]);

	zbx_vector_lld_ext_macro_destroy(&rule_macros->macros);
}

/******************************************************************************
 *                                                                            *
 * Purpose: merge exported macros for LLD rule                                *
 *                                                                            *
 * Parameters: rule_macros - [IN/OUT] LLD rule macros                         *
 *             entry       - [IN] LLD entry                                   *
 *                                                                            *
 * Return value: Merged list of macros containing:                            *
 *               1) macros to update (lld_macroid set, new name/value)        *
 *               2) macros to insert (lld_macroid 0, new name/value)          *
 *               3) macros to remove (lld_macroid set, NULL name/value)       *
 *                                                                            *
 ******************************************************************************/
static void	lld_rule_merge_exported_macros(zbx_lld_rule_macros_t *rule_macros,
		const zbx_lld_entry_t *entry)
{
	zbx_vector_lld_macro_t	macros;
	int			i, j;

	zbx_vector_lld_ext_macro_sort(&rule_macros->macros, lld_ext_macro_compare);

	zbx_vector_lld_macro_create(&macros);
	zbx_vector_lld_macro_append_array(&macros, entry->macros.values, entry->macros.values_num);

	for (i = 0; i < entry->exported_macros->values_num; i++)
	{
		if (FAIL == zbx_vector_lld_macro_search(&entry->macros, entry->exported_macros->values[i],
				lld_macro_compare))
		{
			zbx_vector_lld_macro_append(&macros, entry->exported_macros->values[i]);
		}
	}

	zbx_vector_lld_macro_sort(&macros, lld_macro_compare);

	/* remove matching macros from both vectors */
	for (i = macros.values_num - 1, j = rule_macros->macros.values_num - 1; 0 <= i && 0 <= j; )
	{
		int	ret;

		if (0 == (ret = strcmp(macros.values[i].macro, rule_macros->macros.values[j].name)))
		{
			if (0 == strcmp(macros.values[i].value, rule_macros->macros.values[j].value))
			{
				zbx_vector_lld_macro_remove(&macros, i);
				lld_ext_macro_clear(&rule_macros->macros.values[j]);
				zbx_vector_lld_ext_macro_remove(&rule_macros->macros, j);

				j--;
			}

			i--;
			continue;
		}

		if (0 < ret)
			i--;
		else
			j--;
	}

	/* free old data that will be either replaced or removed */
	for (j = 0; j < rule_macros->macros.values_num; j++)
	{
		zbx_free(rule_macros->macros.values[j].name);
		zbx_free(rule_macros->macros.values[j].value);
	}

	if (macros.values_num > rule_macros->macros.values_num)
	{
		zbx_lld_ext_macro_t	macro = {0};

		zbx_vector_lld_ext_macro_reserve(&rule_macros->macros, (size_t)macros.values_num);

		for (j = rule_macros->macros.values_num; j < macros.values_num; j++)
			zbx_vector_lld_ext_macro_append(&rule_macros->macros, macro);
	}

	for (i = 0; i < macros.values_num; i++)
	{
		rule_macros->macros.values[i].name = zbx_strdup(NULL, macros.values[i].macro);
		rule_macros->macros.values[i].value = zbx_strdup(NULL, macros.values[i].value);
	}

	zbx_vector_lld_macro_destroy(&macros);
}

/******************************************************************************
 *                                                                            *
 * Purpose: fetch exported macros for LLD rules from database                 *
 *                                                                            *
 * Parameters: ruleids   - [IN] vector of LLD rule IDs                        *
 *             lld_rules - [OUT] hashset to store fetched macros              *
 *                                                                            *
 ******************************************************************************/
static void	lld_fetch_exported_macros(const zbx_vector_uint64_t *ruleids, zbx_hashset_t *lld_rules)
{
	zbx_db_row_t		row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_db_large_query_t	query;

	for (int i = 0; i < ruleids->values_num; i++)
	{
		zbx_lld_rule_macros_t	rule_macros_local;

		rule_macros_local.itemid = ruleids->values[i];
		zbx_vector_lld_ext_macro_create(&rule_macros_local.macros);
		zbx_hashset_insert(lld_rules, &rule_macros_local, sizeof(rule_macros_local));
	}

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select lld_macroid,itemid,name,value from lld_macro where");

	zbx_db_large_query_prepare_uint(&query, &sql, &sql_alloc, &sql_offset, "itemid", ruleids);

	while (NULL != (row = zbx_db_large_query_fetch(&query)))
	{
		zbx_lld_rule_macros_t	*rule_macros, rule_macros_local;
		zbx_lld_ext_macro_t	macro;

		ZBX_STR2UINT64(rule_macros_local.itemid, row[1]);
		if (NULL == (rule_macros = (zbx_lld_rule_macros_t *)zbx_hashset_search(lld_rules, &rule_macros_local)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		ZBX_STR2UINT64(macro.lld_macroid, row[0]);
		macro.name = zbx_strdup(NULL, row[2]);
		macro.value = zbx_strdup(NULL, row[3]);

		zbx_vector_lld_ext_macro_append(&rule_macros->macros, macro);
	}
	zbx_db_large_query_clear(&query);
	zbx_free(sql);
}

/******************************************************************************
 *                                                                            *
 * Purpose: flush exported macros for LLD rules to database                   *
 *                                                                            *
 * Parameters: lld_rules - [IN] rule based hashset with macros to be flushed  *
 *                                                                            *
 ******************************************************************************/
static void	lld_flush_exported_macros(zbx_hashset_t *lld_rules)
{
	zbx_db_insert_t		db_insert;
	char			*sql = NULL, *name_esc, *value_esc;
	size_t			sql_alloc = 1024, sql_offset = 0;
	zbx_hashset_iter_t	iter;
	zbx_lld_rule_macros_t	*rule_macros;
	zbx_vector_uint64_t	deleted_ids;

	sql = (char *)zbx_malloc(NULL, sql_alloc);
	zbx_vector_uint64_create(&deleted_ids);

	zbx_db_insert_prepare(&db_insert, "lld_macro", "lld_macroid", "itemid", "name", "value", NULL);

	zbx_hashset_iter_reset(lld_rules, &iter);
	while (NULL != (rule_macros = (zbx_lld_rule_macros_t *)zbx_hashset_iter_next(&iter)))
	{
		for (int i = 0; i < rule_macros->macros.values_num; i++)
		{
			zbx_lld_ext_macro_t	*macro = &rule_macros->macros.values[i];

			if (0 == macro->lld_macroid)
			{
				zbx_db_insert_add_values(&db_insert, macro->lld_macroid, rule_macros->itemid,
						macro->name, macro->value);
				continue;
			}

			if (NULL == macro->name)
			{
				zbx_vector_uint64_append(&deleted_ids, macro->lld_macroid);
				continue;
			}

			name_esc = zbx_db_dyn_escape_string(macro->name);
			value_esc = zbx_db_dyn_escape_string(macro->value);

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update lld_macro set name='%s',value='%s'"
					" where lld_macroid=" ZBX_FS_UI64 ";\n",
					name_esc, value_esc, macro->lld_macroid);

			zbx_free(value_esc);
			zbx_free(name_esc);

			zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
		}
	}

	zbx_db_flush_overflowed_sql(sql, sql_offset);

	if (0 != deleted_ids.values_num)
		zbx_db_execute_multiple_query("delete from lld_macro where", "lld_macroid", &deleted_ids);

	if (0 != zbx_db_insert_get_row_count(&db_insert))
	{
		zbx_db_insert_autoincrement(&db_insert, "lld_macroid");
		zbx_db_insert_execute(&db_insert);
	}

	zbx_db_insert_clean(&db_insert);

	zbx_vector_uint64_destroy(&deleted_ids);
	zbx_free(sql);
}

/******************************************************************************
 *                                                                            *
 * Purpose: synchronize exported macros for LLD rules with database           *
 *                                                                            *
 * Parameters: ruleids - [IN] vector of LLD rule IDs                          *
 *             entry   - [IN/OUT] LLD entry                                   *
 *                                                                            *
 ******************************************************************************/
void	lld_sync_exported_macros(const zbx_vector_uint64_t *ruleids, const zbx_lld_entry_t *entry)
{
	zbx_hashset_t		lld_rules;
	zbx_hashset_iter_t	iter;
	zbx_lld_rule_macros_t	*rule_macros;

	zbx_hashset_create_ext(&lld_rules, (size_t)ruleids->values_num, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC, lld_rule_macros_clear, ZBX_DEFAULT_MEM_MALLOC_FUNC,
			ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	lld_fetch_exported_macros(ruleids, &lld_rules);

	zbx_hashset_iter_reset(&lld_rules, &iter);
	while (NULL != (rule_macros = (zbx_lld_rule_macros_t *)zbx_hashset_iter_next(&iter)))
	{
		lld_rule_merge_exported_macros(rule_macros, entry);

		if (0 == rule_macros->macros.values_num)
			zbx_hashset_iter_remove(&iter);
	}

	if (0 != lld_rules.num_data)
		lld_flush_exported_macros(&lld_rules);

	zbx_hashset_destroy(&lld_rules);
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve exported macros for a specific LLD rule                  *
 *                                                                            *
 * Parameters: ruleid - [IN] ID of the LLD rule                               *
 *             macros - [OUT] vector to store retrieved macros                *
 *                                                                            *
 ******************************************************************************/
void	lld_rule_get_exported_macros(zbx_uint64_t ruleid, zbx_vector_lld_macro_t *macros)
{
	zbx_db_row_t	row;
	zbx_db_result_t	result;

	result = zbx_db_select("select name,value from lld_macro where itemid=" ZBX_FS_UI64, ruleid);

	while (NULL!= (row = zbx_db_fetch(result)))
	{
		zbx_lld_macro_t	macro;

		macro.macro = zbx_strdup(NULL, row[0]);
		macro.value = zbx_strdup(NULL, row[1]);

		zbx_vector_lld_macro_append(macros, macro);
	}
	zbx_db_free_result(result);

	zbx_vector_lld_macro_sort(macros, lld_macro_compare);
}

typedef struct
{
	zbx_uint64_t	item_conditionid;
	int		operator;
	char		*macro;
	char		*value;
}
zbx_lld_rule_filter_t;

ZBX_PTR_VECTOR_DECL(lld_rule_filter_ptr, zbx_lld_rule_filter_t *)
ZBX_PTR_VECTOR_IMPL(lld_rule_filter_ptr, zbx_lld_rule_filter_t *)

static zbx_lld_rule_filter_t	*lld_rule_filter_create(zbx_uint64_t item_conditionid, int operator, const char *macro,
		const char *value)
{
	zbx_lld_rule_filter_t	*filter;

	filter = zbx_malloc(NULL, sizeof(zbx_lld_rule_filter_t));

	filter->item_conditionid = item_conditionid;
	filter->operator = operator;
	filter->macro = zbx_strdup(NULL, macro);
	filter->value = zbx_strdup(NULL, value);

	return filter;
}

static void	lld_rule_filter_free(zbx_lld_rule_filter_t *filter)
{
	zbx_free(filter->macro);
	zbx_free(filter->value);
	zbx_free(filter);
}

typedef struct
{
	zbx_uint64_t	itemid;
	zbx_uint64_t	parent_itemid;
	zbx_uint64_t	master_itemid;
	zbx_uint64_t	interfaceid_orig;
	char		*key;
	char		*key_proto;
	char		*key_orig;
	char		*name;
	char		*name_proto;
	char		*delay;
	char		*delay_orig;
	char		*trapper_hosts;
	char		*trapper_hosts_orig;
	char		*formula;
	char		*formula_orig;
	char		*params;
	char		*params_orig;
	char		*ipmi_sensor;
	char		*ipmi_sensor_orig;
	char		*snmp_oid;
	char		*snmp_oid_orig;
	char		*username;
	char		*username_orig;
	char		*password;
	char		*password_orig;
	char		*publickey_orig;
	char		*privatekey_orig;
	char		*description;
	char		*description_orig;
	char		*jmx_endpoint;
	char		*jmx_endpoint_orig;
	char		*timeout;
	char		*timeout_orig;
	char		*url;
	char		*url_orig;
	char		*query_fields;
	char		*query_fields_orig;
	char		*posts;
	char		*posts_orig;
	char		*status_codes;
	char		*status_codes_orig;
	char		*http_proxy;
	char		*http_proxy_orig;
	char		*headers;
	char		*headers_orig;
	char		*ssl_cert_file;
	char		*ssl_cert_file_orig;
	char		*ssl_key_file;
	char		*ssl_key_file_orig;
	char		*ssl_key_password;
	char		*ssl_key_password_orig;
	char		*lifetime;
	char		*lifetime_orig;
	char		*enabled_lifetime;
	char 		*enabled_lifetime_orig;
	int		lastcheck;
	int		ts_delete;
	int		ts_disable;
	int		lifetime_type_orig;
	int		enabled_lifetime_type_orig;
	unsigned char	discovery_status;
	unsigned char	disable_source;
	unsigned char	type;
	unsigned char	type_orig;
	unsigned char	authtype_orig;
	unsigned char	follow_redirects_orig;
	unsigned char	post_type_orig;
	unsigned char	retrieve_mode_orig;
	unsigned char	request_method_orig;
	unsigned char	output_format_orig;
	unsigned char	verify_peer_orig;
	unsigned char	verify_host_orig;
	unsigned char	allow_traps_orig;
	unsigned char	status;

	zbx_vector_lld_item_preproc_ptr_t	preproc_ops;
	zbx_vector_item_param_ptr_t		item_params;

	const zbx_lld_row_t	*lld_row;


#define ZBX_FLAG_LLD_RULE_UNSET				0x00000000
#define ZBX_FLAG_LLD_RULE_DISCOVERED                    0x00000001
#define ZBX_FLAG_LLD_RULE_UPDATE_TYPE                   0x00000002
#define ZBX_FLAG_LLD_RULE_UPDATE_TRAPPER_HOSTS          0x00000004
#define ZBX_FLAG_LLD_RULE_UPDATE_FORMULA                0x00000008
#define ZBX_FLAG_LLD_RULE_UPDATE_AUTHTYPE               0x00000010
#define ZBX_FLAG_LLD_RULE_UPDATE_PUBLICKEY              0x00000020
#define ZBX_FLAG_LLD_RULE_UPDATE_PRIVATEKEY             0x00000040
#define ZBX_FLAG_LLD_RULE_UPDATE_INTERFACEID            0x00000080
#define ZBX_FLAG_LLD_RULE_UPDATE_FOLLOW_REDIRECTS       0x00000100
#define ZBX_FLAG_LLD_RULE_UPDATE_POST_TYPE              0x00000200
#define ZBX_FLAG_LLD_RULE_UPDATE_RETRIEVE_MODE          0x00000400
#define ZBX_FLAG_LLD_RULE_UPDATE_REQUEST_METHOD         0x00000800
#define ZBX_FLAG_LLD_RULE_UPDATE_OUTPUT_FORMAT          0x00001000
#define ZBX_FLAG_LLD_RULE_UPDATE_VERIFY_PEER            0x00002000
#define ZBX_FLAG_LLD_RULE_UPDATE_VERIFY_HOST            0x00004000
#define ZBX_FLAG_LLD_RULE_UPDATE_ALLOW_TRAPS            0x00008000
#define ZBX_FLAG_LLD_RULE_UPDATE_LIFETIME_TYPE          0x00010000
#define ZBX_FLAG_LLD_RULE_UPDATE_ENABLED_LIFETIME_TYPE  0x00020000

	zbx_uint64_t	flags;
}
zbx_lld_rule_db_t;

ZBX_PTR_VECTOR_DECL(lld_rule_db_ptr, zbx_lld_rule_db_t *)
ZBX_PTR_VECTOR_IMPL(lld_rule_db_ptr, zbx_lld_rule_db_t *)

/* reference to an item either by its id (existing items) or structure (new items) */
typedef struct
{
	zbx_uint64_t		itemid;
	zbx_lld_rule_db_t	*rule;
}
zbx_lld_rule_ref_t;

/* rules index hashset support functions */
static zbx_hash_t	lld_rule_ref_key_hash(const void *data)
{
	const zbx_lld_rule_ref_t	*ref = (const zbx_lld_rule_ref_t *)data;

	return ZBX_DEFAULT_STRING_HASH_FUNC(ref->rule->key);
}

static int	lld_rule_ref_key_compare(const void *d1, const void *d2)
{
	const zbx_lld_rule_ref_t	*ref1 = (const zbx_lld_rule_ref_t *)d1;
	const zbx_lld_rule_ref_t	*ref2 = (const zbx_lld_rule_ref_t *)d2;

	return strcmp(ref1->rule->key, ref2->rule->key);
}

/* item index by prototype (parent) id and lld row */
typedef struct
{
	zbx_uint64_t		parent_itemid;
	zbx_lld_row_t		*lld_row;
	zbx_lld_rule_db_t	*rule;
}
zbx_lld_rule_index_t;

static zbx_hash_t	lld_rule_index_hash(const void *data)
{
	const zbx_lld_rule_index_t	*rule_index = (const zbx_lld_rule_index_t *)data;
	zbx_hash_t			hash;

	hash = ZBX_DEFAULT_UINT64_HASH_ALGO(&rule_index->parent_itemid,
			sizeof(rule_index->parent_itemid), ZBX_DEFAULT_HASH_SEED);
	return ZBX_DEFAULT_PTR_HASH_ALGO(&rule_index->lld_row, sizeof(rule_index->lld_row), hash);
}

static int	lld_rule_index_compare(const void *d1, const void *d2)
{
	const zbx_lld_rule_index_t	*i1 = (const zbx_lld_rule_index_t *)d1;
	const zbx_lld_rule_index_t	*i2 = (const zbx_lld_rule_index_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(i1->parent_itemid, i2->parent_itemid);
	ZBX_RETURN_IF_NOT_EQUAL(i1->lld_row, i2->lld_row);

	return 0;
}

typedef struct
{
	zbx_uint64_t				itemid;
	zbx_uint64_t				interfaceid;
	zbx_uint64_t				master_itemid;
	char					*name;
	char					*key;
	char					*delay;
	char					*trapper_hosts;
	char					*formula;
	char					*params;
	char					*ipmi_sensor;
	char					*snmp_oid;
	char					*username;
	char					*password;
	char					*publickey;
	char					*privatekey;
	char					*description;
	char					*jmx_endpoint;
	char					*timeout;
	char					*url;
	char					*query_fields;
	char					*posts;
	char					*status_codes;
	char					*http_proxy;
	char					*headers;
	char					*ssl_cert_file;
	char					*ssl_key_file;
	char					*ssl_key_password;
	char					*lifetime;
	char					*enabled_lifetime;
	int					lifetime_type;
	int					enabled_lifetime_type;
	int					evaltype;
	unsigned char				verify_peer;
	unsigned char				verify_host;
	unsigned char				follow_redirects;
	unsigned char				post_type;
	unsigned char				retrieve_mode;
	unsigned char				request_method;
	unsigned char				output_format;
	unsigned char				type;
	unsigned char				status;
	unsigned char				authtype;
	unsigned char				allow_traps;
	unsigned char				discover;
	zbx_vector_lld_row_ptr_t		lld_rows;
	zbx_vector_lld_item_preproc_ptr_t	preproc_ops;
	zbx_vector_item_param_ptr_t		item_params;
	zbx_vector_lld_rule_filter_ptr_t	filters;
	zbx_vector_lld_macro_path_ptr_t		macro_paths;
	zbx_vector_lld_override_ptr_t		overrides;

	zbx_hashset_t				rule_index;
	zbx_vector_str_t			keys;		/* keys used to create ll rules from this prototype */
}
zbx_lld_rule_prototype_t;

ZBX_PTR_VECTOR_DECL(lld_rule_prototype_ptr, zbx_lld_rule_prototype_t *)
ZBX_PTR_VECTOR_IMPL(lld_rule_prototype_ptr, zbx_lld_rule_prototype_t *)

static void	lld_rule_prototpe_free(zbx_lld_rule_prototype_t *rule_prototype)
{
	zbx_free(rule_prototype->name);
	zbx_free(rule_prototype->key);
	zbx_free(rule_prototype->delay);
	zbx_free(rule_prototype->trapper_hosts);
	zbx_free(rule_prototype->formula);
	zbx_free(rule_prototype->params);
	zbx_free(rule_prototype->ipmi_sensor);
	zbx_free(rule_prototype->snmp_oid);
	zbx_free(rule_prototype->username);
	zbx_free(rule_prototype->password);
	zbx_free(rule_prototype->publickey);
	zbx_free(rule_prototype->privatekey);
	zbx_free(rule_prototype->description);
	zbx_free(rule_prototype->jmx_endpoint);
	zbx_free(rule_prototype->timeout);
	zbx_free(rule_prototype->url);
	zbx_free(rule_prototype->query_fields);
	zbx_free(rule_prototype->posts);
	zbx_free(rule_prototype->status_codes);
	zbx_free(rule_prototype->http_proxy);
	zbx_free(rule_prototype->headers);
	zbx_free(rule_prototype->ssl_cert_file);
	zbx_free(rule_prototype->ssl_key_file);
	zbx_free(rule_prototype->ssl_key_password);
	zbx_free(rule_prototype->lifetime);
	zbx_free(rule_prototype->enabled_lifetime);

	zbx_vector_lld_row_ptr_destroy(&rule_prototype->lld_rows);

	zbx_vector_lld_item_preproc_ptr_clear_ext(&rule_prototype->preproc_ops, lld_item_preproc_free);
	zbx_vector_lld_item_preproc_ptr_destroy(&rule_prototype->preproc_ops);

	zbx_vector_item_param_ptr_clear_ext(&rule_prototype->item_params, zbx_item_param_free);
	zbx_vector_item_param_ptr_destroy(&rule_prototype->item_params);

	zbx_vector_lld_rule_filter_ptr_clear_ext(&rule_prototype->filters, lld_rule_filter_free);
	zbx_vector_lld_rule_filter_ptr_destroy(&rule_prototype->filters);

	zbx_vector_lld_macro_path_ptr_clear_ext(&rule_prototype->macro_paths, zbx_lld_macro_path_free);
	zbx_vector_lld_macro_path_ptr_destroy(&rule_prototype->macro_paths);

	zbx_vector_lld_override_ptr_clear_ext(&rule_prototype->overrides, zbx_lld_override_free);
	zbx_vector_lld_override_ptr_destroy(&rule_prototype->overrides);

	zbx_hashset_destroy(&rule_prototype->rule_index);

	zbx_vector_str_destroy(&rule_prototype->keys);

	zbx_free(rule_prototype);
}

static int	lld_rule_prototype_compare(const void *d1, const void *d2)
{
	const zbx_lld_rule_prototype_t	*proto_1 = *(const zbx_lld_rule_prototype_t **)d1;
	const zbx_lld_rule_prototype_t	*proto_2 = *(const zbx_lld_rule_prototype_t **)d2;

	ZBX_RETURN_IF_NOT_EQUAL(proto_1->itemid, proto_2->itemid);

	return 0;
}

static void	lld_rule_fetch_prototype_preproc(zbx_vector_lld_rule_prototype_ptr_t *rule_prototypes,
		const zbx_vector_uint64_t *protoids)
{
	zbx_db_result_t			result;
	zbx_db_row_t			row;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_lld_rule_prototype_t	*rule_prototype;

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select itemid,step,type,params,error_handler,error_handler_params from item_preproc where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", protoids->values, protoids->values_num);
	result = zbx_db_select("%s", sql);
	zbx_free(sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_lld_rule_prototype_t	rule_prototype_local;
		zbx_lld_item_preproc_t		*preproc_op;
		int				index;

		ZBX_STR2UINT64(rule_prototype_local.itemid, row[0]);

		if (FAIL == (index = zbx_vector_lld_rule_prototype_ptr_bsearch(rule_prototypes, &rule_prototype_local,
				lld_rule_prototype_compare)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		rule_prototype = rule_prototypes->values[index];
		preproc_op = lld_item_preproc_create(0, ZBX_FLAG_LLD_ITEM_PREPROC_UNSET, atoi(row[1]), atoi(row[2]),
				row[3], atoi(row[4]), row[5]);
		zbx_vector_lld_item_preproc_ptr_append(&rule_prototype->preproc_ops, preproc_op);
	}
	zbx_db_free_result(result);

	for (int i = 0; i < rule_prototypes->values_num; i++)
	{
		rule_prototype = rule_prototypes->values[i];
		zbx_vector_lld_item_preproc_ptr_sort(&rule_prototype->preproc_ops, lld_item_preproc_sort_by_step);
	}
}

static void	lld_rule_fetch_prototype_params(zbx_vector_lld_rule_prototype_ptr_t *rule_prototypes,
		const zbx_vector_uint64_t *protoids)
{
	zbx_db_result_t			result;
	zbx_db_row_t			row;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_lld_rule_prototype_t	*rule_prototype;

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select itemid,name,value from item_parameter where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", protoids->values, protoids->values_num);
	result = zbx_db_select("%s", sql);
	zbx_free(sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_lld_rule_prototype_t	rule_prototype_local;
		zbx_item_param_t		*item_param;
		int				index;

		ZBX_STR2UINT64(rule_prototype_local.itemid, row[0]);

		if (FAIL == (index = zbx_vector_lld_rule_prototype_ptr_bsearch(rule_prototypes, &rule_prototype_local,
				lld_rule_prototype_compare)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		rule_prototype = rule_prototypes->values[index];

		item_param = zbx_item_param_create(row[1], row[2]);
		zbx_vector_item_param_ptr_append(&rule_prototype->item_params, item_param);
	}
	zbx_db_free_result(result);
}

static void	lld_rule_fetch_prototype_filters(zbx_vector_lld_rule_prototype_ptr_t *rule_prototypes,
		const zbx_vector_uint64_t *protoids)
{
	zbx_db_result_t			result;
	zbx_db_row_t			row;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_lld_rule_prototype_t	*rule_prototype;

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select item_conditionid,itemid,operator,macro,value from"
			" item_condition where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", protoids->values, protoids->values_num);
	result = zbx_db_select("%s", sql);
	zbx_free(sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_lld_rule_prototype_t	rule_prototype_local;
		zbx_lld_rule_filter_t		*filter;
		int				index;
		zbx_uint64_t			item_conditionid;

		ZBX_STR2UINT64(rule_prototype_local.itemid, row[1]);

		if (FAIL == (index = zbx_vector_lld_rule_prototype_ptr_bsearch(rule_prototypes, &rule_prototype_local,
				lld_rule_prototype_compare)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		rule_prototype = rule_prototypes->values[index];

		ZBX_STR2UINT64(item_conditionid, row[0]);
		filter = lld_rule_filter_create(item_conditionid, atoi(row[2]), row[3], row[4]);
		zbx_vector_lld_rule_filter_ptr_append(&rule_prototype->filters, filter);
	}
	zbx_db_free_result(result);
}

static void	lld_rule_fetch_prototype_macro_paths(zbx_vector_lld_rule_prototype_ptr_t *rule_prototypes,
		const zbx_vector_uint64_t *protoids)
{
	zbx_db_result_t			result;
	zbx_db_row_t			row;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	zbx_lld_rule_prototype_t	*rule_prototype;

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select itemid,lld_macro,path from lld_macro_path where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", protoids->values, protoids->values_num);
	result = zbx_db_select("%s", sql);
	zbx_free(sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_lld_rule_prototype_t	rule_prototype_local;
		zbx_lld_macro_path_t		*macro_path;
		int				index;

		ZBX_STR2UINT64(rule_prototype_local.itemid, row[0]);

		if (FAIL == (index = zbx_vector_lld_rule_prototype_ptr_bsearch(rule_prototypes, &rule_prototype_local,
				lld_rule_prototype_compare)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		rule_prototype = rule_prototypes->values[index];

		macro_path = lld_macro_path_create(row[1], row[2]);
		zbx_vector_lld_macro_path_ptr_append(&rule_prototype->macro_paths, macro_path);
	}
	zbx_db_free_result(result);
}

static void	lld_rule_fetch_prototype_overrides(zbx_vector_lld_rule_prototype_ptr_t *rule_prototypes,
		const zbx_vector_uint64_t *protoids)
{
	zbx_db_result_t				result;
	zbx_db_row_t				row;
	char					*sql = NULL;
	size_t					sql_alloc = 0, sql_offset = 0;
	zbx_lld_rule_prototype_t		*rule_prototype;
	zbx_vector_uint64_t			overrideids;
	zbx_vector_lld_override_ptr_t		overrides;
	zbx_vector_lld_override_operation_t	ops;

	zbx_vector_uint64_create(&overrideids);
	zbx_vector_lld_override_ptr_create(&overrides);
	zbx_vector_lld_override_operation_create(&ops);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select lld_overrideid,itemid,name,step,evaltype,formula,stop"
			" from lld_override where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", protoids->values, protoids->values_num);
	result = zbx_db_select("%s", sql);
	zbx_free(sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_lld_rule_prototype_t	rule_prototype_local;
		int				index;
		zbx_lld_override_t		*override;

		ZBX_STR2UINT64(rule_prototype_local.itemid, row[1]);

		if (FAIL == (index = zbx_vector_lld_rule_prototype_ptr_bsearch(rule_prototypes, &rule_prototype_local,
				lld_rule_prototype_compare)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		rule_prototype = rule_prototypes->values[index];

		override = zbx_lld_override_create(row[0], row[1], row[2], row[3], row[4], row[5], row[6]);
		zbx_vector_lld_override_ptr_append(&rule_prototype->overrides, override);
		zbx_vector_lld_override_ptr_append(&overrides, override);
		zbx_vector_uint64_append(&overrideids, override->overrideid);

	}
	zbx_db_free_result(result);

	zbx_vector_lld_override_ptr_sort(&overrides, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	/* fetch override conditions */

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select lld_overrideid,lld_override_conditionid,macro,value,operator"
			" from lld_override_condition"
			" where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "lld_overrideid", overrideids.values,
			overrideids.values_num);

	result = zbx_db_select("%s", sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		int			index;
		zbx_lld_condition_t	*condition;
		zbx_lld_override_t	override_local;

		ZBX_STR2UINT64(override_local.overrideid, row[0]);

		if (FAIL == (index = zbx_vector_lld_override_ptr_bsearch(&overrides, &override_local,
				zbx_lld_override_compare_func)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		condition = zbx_lld_filter_condition_create(row[1], row[2], row[3], row[4]);
		zbx_vector_lld_condition_ptr_append(&overrides.values[index]->filter.conditions, condition);
	}
	zbx_db_free_result(result);

	/* fetch override operations */

	zbx_load_lld_override_operations(&overrideids, &sql, &sql_alloc, &ops);

	for (int i = 0; i < ops.values_num; i++)
	{
		zbx_lld_override_operation_t	*op = ops.values[i];
		zbx_lld_override_t		override_local;
		int				index;

		override_local.overrideid = op->overrideid;

		if (FAIL == (index = zbx_vector_lld_override_ptr_bsearch(&overrides, &override_local,
				zbx_lld_override_compare_func)))
		{
			zbx_lld_override_operation_free(op);
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		zbx_vector_lld_override_operation_append(&overrides.values[index]->override_operations, op);
	}

	zbx_free(sql);
	zbx_vector_lld_override_operation_destroy(&ops);
	zbx_vector_lld_override_ptr_destroy(&overrides);
	zbx_vector_uint64_destroy(&overrideids);
}

static int	lld_rule_fetch_prototypes(zbx_uint64_t lld_ruleid, zbx_vector_lld_rule_prototype_ptr_t *rule_prototypes)
{
	zbx_db_result_t			result;
	zbx_db_row_t			row;
	zbx_lld_rule_prototype_t	*rule_prototype;
	zbx_vector_uint64_t		protoids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&protoids);

	result = zbx_db_select(
			"select i.itemid,i.name,i.key_,i.type,i.delay,i.status,i.trapper_hosts,i.formula,"
				"i.params,i.ipmi_sensor,i.snmp_oid,i.authtype,"
				"i.username,i.password,i.publickey,i.privatekey,i.description,i.interfaceid,"
				"i.jmx_endpoint,i.master_itemid,i.timeout,i.url,i.query_fields,"
				"i.posts,i.status_codes,i.follow_redirects,i.post_type,i.http_proxy,i.headers,"
				"i.retrieve_mode,i.request_method,i.output_format,i.ssl_cert_file,i.ssl_key_file,"
				"i.ssl_key_password,i.verify_peer,i.verify_host,i.allow_traps,i.discover,"
				"i.evaltype,i.lifetime,i.lifetime_type,i.enabled_lifetime_type,i.enabled_lifetime"
			" from items i,item_discovery id"
			" where i.itemid=id.itemid"
				" and id.parent_itemid=" ZBX_FS_UI64
				" and i.flags&%d<>0",
			lld_ruleid, ZBX_FLAG_DISCOVERY_RULE);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		rule_prototype = (zbx_lld_rule_prototype_t *)zbx_malloc(NULL, sizeof(zbx_lld_rule_prototype_t));

		ZBX_STR2UINT64(rule_prototype->itemid, row[0]);
		rule_prototype->name = zbx_strdup(NULL, row[1]);
		rule_prototype->key = zbx_strdup(NULL, row[2]);
		ZBX_STR2UCHAR(rule_prototype->type, row[3]);
		rule_prototype->delay = zbx_strdup(NULL, row[4]);
		ZBX_STR2UCHAR(rule_prototype->status, row[5]);
		rule_prototype->trapper_hosts = zbx_strdup(NULL, row[6]);
		rule_prototype->formula = zbx_strdup(NULL, row[7]);
		rule_prototype->params = zbx_strdup(NULL, row[8]);
		rule_prototype->ipmi_sensor = zbx_strdup(NULL, row[9]);
		rule_prototype->snmp_oid = zbx_strdup(NULL, row[10]);
		ZBX_STR2UCHAR(rule_prototype->authtype, row[11]);
		rule_prototype->username = zbx_strdup(NULL, row[12]);
		rule_prototype->password = zbx_strdup(NULL, row[13]);
		rule_prototype->publickey = zbx_strdup(NULL, row[14]);
		rule_prototype->privatekey = zbx_strdup(NULL, row[15]);
		rule_prototype->description = zbx_strdup(NULL, row[16]);
		ZBX_DBROW2UINT64(rule_prototype->interfaceid, row[17]);
		rule_prototype->jmx_endpoint = zbx_strdup(NULL, row[18]);
		ZBX_DBROW2UINT64(rule_prototype->master_itemid, row[19]);
		rule_prototype->timeout = zbx_strdup(NULL, row[20]);
		rule_prototype->url = zbx_strdup(NULL, row[21]);
		rule_prototype->query_fields = zbx_strdup(NULL, row[22]);
		rule_prototype->posts = zbx_strdup(NULL, row[23]);
		rule_prototype->status_codes = zbx_strdup(NULL, row[24]);
		ZBX_STR2UCHAR(rule_prototype->follow_redirects, row[25]);
		ZBX_STR2UCHAR(rule_prototype->post_type, row[26]);
		rule_prototype->http_proxy = zbx_strdup(NULL, row[27]);
		rule_prototype->headers = zbx_strdup(NULL, row[28]);
		ZBX_STR2UCHAR(rule_prototype->retrieve_mode, row[29]);
		ZBX_STR2UCHAR(rule_prototype->request_method, row[30]);
		ZBX_STR2UCHAR(rule_prototype->output_format, row[31]);
		rule_prototype->ssl_cert_file = zbx_strdup(NULL, row[32]);
		rule_prototype->ssl_key_file = zbx_strdup(NULL, row[33]);
		rule_prototype->ssl_key_password = zbx_strdup(NULL, row[34]);
		ZBX_STR2UCHAR(rule_prototype->verify_peer, row[35]);
		ZBX_STR2UCHAR(rule_prototype->verify_host, row[36]);
		ZBX_STR2UCHAR(rule_prototype->allow_traps, row[37]);
		ZBX_STR2UCHAR(rule_prototype->discover, row[38]);
		rule_prototype->evaltype = atoi(row[39]);
		rule_prototype->lifetime = zbx_strdup(NULL, row[40]);
		rule_prototype->lifetime_type = atoi(row[41]);
		rule_prototype->enabled_lifetime_type = atoi(row[42]);
		rule_prototype->enabled_lifetime = zbx_strdup(NULL, row[43]);

		zbx_vector_lld_row_ptr_create(&rule_prototype->lld_rows);
		zbx_vector_lld_item_preproc_ptr_create(&rule_prototype->preproc_ops);
		zbx_vector_item_param_ptr_create(&rule_prototype->item_params);
		zbx_vector_lld_rule_filter_ptr_create(&rule_prototype->filters);
		zbx_vector_lld_macro_path_ptr_create(&rule_prototype->macro_paths);
		zbx_vector_lld_override_ptr_create(&rule_prototype->overrides);

		zbx_hashset_create(&rule_prototype->rule_index, 0, lld_rule_ref_key_hash, lld_rule_ref_key_compare);
		zbx_vector_str_create(&rule_prototype->keys);

		zbx_vector_lld_rule_prototype_ptr_append(rule_prototypes, rule_prototype);

		zbx_vector_uint64_append(&protoids, rule_prototype->itemid);
	}
	zbx_db_free_result(result);

	zbx_vector_lld_rule_prototype_ptr_sort(rule_prototypes, lld_rule_prototype_compare);

	if (0 == rule_prototypes->values_num)
		goto out;

	lld_rule_fetch_prototype_preproc(rule_prototypes, &protoids);
	lld_rule_fetch_prototype_params(rule_prototypes, &protoids);
	lld_rule_fetch_prototype_filters(rule_prototypes, &protoids);
	lld_rule_fetch_prototype_macro_paths(rule_prototypes, &protoids);
	lld_rule_fetch_prototype_overrides(rule_prototypes, &protoids);

out:
	zbx_vector_uint64_destroy(&protoids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d prototypes", __func__, rule_prototypes->values_num);

	return rule_prototypes->values_num;
}

static void	lld_rule_prototype_dump(zbx_lld_rule_prototype_t *rule_prototype)
{
	zabbix_log(LOG_LEVEL_TRACE, "Prototype:" ZBX_FS_UI64, rule_prototype->itemid);
	zabbix_log(LOG_LEVEL_TRACE, "  key:%s name:%s", rule_prototype->key, rule_prototype->name);
	zabbix_log(LOG_LEVEL_TRACE, "  type:%u delay:%s status:%u trapper_hosts:%s allow_traps:%u discover:%u",
			rule_prototype->type, rule_prototype->delay, rule_prototype->status,
			rule_prototype->trapper_hosts, rule_prototype->allow_traps, rule_prototype->discover);
	zabbix_log(LOG_LEVEL_TRACE, "  params:%s ipmi_sensor:%s snmp_oid:%s authtype:%u username:%s password:%s"
			" publickey:%s privatekey:%s",
			rule_prototype->params, rule_prototype->ipmi_sensor, rule_prototype->snmp_oid,
			rule_prototype->authtype, rule_prototype->username, rule_prototype->password,
			rule_prototype->publickey, rule_prototype->privatekey);
	zabbix_log(LOG_LEVEL_TRACE, "  description:%s", rule_prototype->description);
	zabbix_log(LOG_LEVEL_TRACE, "  interfaceid:" ZBX_FS_UI64 " master_itemid:" ZBX_FS_UI64,
			rule_prototype->interfaceid, rule_prototype->master_itemid);
	zabbix_log(LOG_LEVEL_TRACE, "  jmx_endpoint:%s timeout:%s",
			rule_prototype->jmx_endpoint, rule_prototype->timeout);
	zabbix_log(LOG_LEVEL_TRACE, "  url:%s query_fields:%s posts:%s"
			" status_codes:%s follow_redirects:%u post_type:%u",
			rule_prototype->url, rule_prototype->query_fields, rule_prototype->posts,
			rule_prototype->status_codes, rule_prototype->follow_redirects,
			rule_prototype->post_type);
	zabbix_log(LOG_LEVEL_TRACE, "  http_proxy:%s headers:%s retrieve_mode:%u request_method:%u output_format:%u",
			rule_prototype->http_proxy, rule_prototype->headers, rule_prototype->retrieve_mode,
			rule_prototype->request_method, rule_prototype->output_format);
	zabbix_log(LOG_LEVEL_TRACE, "  ssl_cert_file:%s ssl_key_file:%s ssl_key_password:%s verify_peer:%u"
			" verify_host:%u", rule_prototype->ssl_cert_file, rule_prototype->ssl_key_file,
			rule_prototype->ssl_key_password, rule_prototype->verify_peer, rule_prototype->verify_host);
	zabbix_log(LOG_LEVEL_TRACE, "  evaltype:%d  formula:%s lifetime_type:%d lifetime:%s"
			" enabled_lifetime_type:%d enabled_lifetime:%s",
			rule_prototype->evaltype, rule_prototype->formula,
			rule_prototype->lifetime_type, rule_prototype->lifetime,
			rule_prototype->enabled_lifetime_type, rule_prototype->enabled_lifetime);

	zabbix_log(LOG_LEVEL_TRACE, "  preprocessing:");
	for (int i = 0; i < rule_prototype->preproc_ops.values_num; i++)
	{
		zbx_lld_item_preproc_t	*preproc_op = rule_prototype->preproc_ops.values[i];
		char			*params;

		params = zbx_string_replace(preproc_op->params, "\n", "\\n");
		zabbix_log(LOG_LEVEL_TRACE, "    item_preprocid:" ZBX_FS_UI64 "    step:%d type:%d error_handler:%d"
				" params:%s error_handler_params:%s", preproc_op->item_preprocid, preproc_op->step,
				preproc_op->type, preproc_op->error_handler, params, preproc_op->error_handler_params);

		zbx_free(params);
	}

	zabbix_log(LOG_LEVEL_TRACE, "  parameters:");
	for (int i = 0; i < rule_prototype->item_params.values_num; i++)
	{
		zbx_item_param_t	*param = rule_prototype->item_params.values[i];

		zabbix_log(LOG_LEVEL_TRACE, "    item_parameterid:" ZBX_FS_UI64 "    name:%s value:%s",
				param->item_parameterid, param->name, param->value);
	}

	zabbix_log(LOG_LEVEL_TRACE, "  filters:");
	for (int i = 0; i < rule_prototype->filters.values_num; i++)
	{
		zbx_lld_rule_filter_t        *filter = rule_prototype->filters.values[i];

		zabbix_log(LOG_LEVEL_TRACE, "    item_conditionid:" ZBX_FS_UI64 " operator:%d macro:%s value:%s",
				filter->item_conditionid, filter->operator, filter->macro, filter->value);
	}

	zabbix_log(LOG_LEVEL_TRACE, "  macro_paths:");
	for (int i = 0; i < rule_prototype->macro_paths.values_num; i++)
	{
		zbx_lld_macro_path_t        *macro_path = rule_prototype->macro_paths.values[i];

		zabbix_log(LOG_LEVEL_TRACE, "    lld_macro:%s path:%s", macro_path->lld_macro, macro_path->path);
	}

	zabbix_log(LOG_LEVEL_TRACE, "  overrides:");
	for (int i = 0; i < rule_prototype->overrides.values_num; i++)
	{
		zbx_lld_override_t        *override = rule_prototype->overrides.values[i];

		zabbix_log(LOG_LEVEL_TRACE, "    lld_overrideid:" ZBX_FS_UI64, override->overrideid);
		zabbix_log(LOG_LEVEL_TRACE, "      name:%s step:%d stop:%u", override->name, override->step,
				override->stop);

		zabbix_log(LOG_LEVEL_TRACE, "      evaltype:%d formula:%s", override->filter.evaltype,
				override->filter.expression);

		zabbix_log(LOG_LEVEL_TRACE, "      conditions:");
		for (int j = 0; j < override->filter.conditions.values_num; j++)
		{
			zbx_lld_condition_t        *condition = override->filter.conditions.values[j];

			zabbix_log(LOG_LEVEL_TRACE, "          item_conditionid:" ZBX_FS_UI64 " operator:%d macro:%s"
					" value:%s", condition->id, condition->op, condition->macro, condition->regexp);
		}

		zabbix_log(LOG_LEVEL_TRACE, "      operations:");
		for (int j = 0; j < override->override_operations.values_num; j++)
		{
			zbx_lld_override_operation_t        *op = override->override_operations.values[j];

			zabbix_log(LOG_LEVEL_TRACE, "        override_operationid:" ZBX_FS_UI64,
					op->override_operationid);
			zabbix_log(LOG_LEVEL_TRACE, "          type:%u operator:%u value:%s", op->operationtype,
					op->operator, op->value);
			zabbix_log(LOG_LEVEL_TRACE, "          status:%u severity:%u discover:%u inventory_mode:%d",
					op->status, op->severity, op->discover, op->inventory_mode);
			zabbix_log(LOG_LEVEL_TRACE, "          delay:%s history:%s trends:%s",
					ZBX_NULL2EMPTY_STR(op->delay), ZBX_NULL2EMPTY_STR(op->history),
					ZBX_NULL2EMPTY_STR(op->trends));

			zabbix_log(LOG_LEVEL_TRACE, "          tags:");
			for (int k = 0; k < op->tags.values_num; k++)
			{
				zbx_db_tag_t	*tag = op->tags.values[k];

				zabbix_log(LOG_LEVEL_TRACE, "            tag:%s value:%s", tag->tag, tag->value);
			}

			zabbix_log(LOG_LEVEL_TRACE, "          templateids:");
			for (int k = 0; k < op->templateids.values_num; k++)
			{
				zabbix_log(LOG_LEVEL_TRACE, "            templateid:" ZBX_FS_UI64,
						op->templateids.values[k]);
			}
		}
	}
}

static void	lld_rule_db_free(zbx_lld_rule_db_t *rule_db)
{
	zbx_free(rule_db->key);
	zbx_free(rule_db->key_proto);
	zbx_free(rule_db->key_orig);
	zbx_free(rule_db->name);
	zbx_free(rule_db->name_proto);
	zbx_free(rule_db->delay);
	zbx_free(rule_db->delay_orig);
	zbx_free(rule_db->trapper_hosts);
	zbx_free(rule_db->trapper_hosts_orig);
	zbx_free(rule_db->formula);
	zbx_free(rule_db->formula_orig);
	zbx_free(rule_db->params);
	zbx_free(rule_db->params_orig);
	zbx_free(rule_db->ipmi_sensor);
	zbx_free(rule_db->ipmi_sensor_orig);
	zbx_free(rule_db->snmp_oid);
	zbx_free(rule_db->snmp_oid_orig);
	zbx_free(rule_db->username);
	zbx_free(rule_db->username_orig);
	zbx_free(rule_db->password);
	zbx_free(rule_db->password_orig);
	zbx_free(rule_db->publickey_orig);
	zbx_free(rule_db->privatekey_orig);
	zbx_free(rule_db->description);
	zbx_free(rule_db->description_orig);
	zbx_free(rule_db->jmx_endpoint);
	zbx_free(rule_db->jmx_endpoint_orig);
	zbx_free(rule_db->timeout);
	zbx_free(rule_db->timeout_orig);
	zbx_free(rule_db->url);
	zbx_free(rule_db->url_orig);
	zbx_free(rule_db->query_fields);
	zbx_free(rule_db->query_fields_orig);
	zbx_free(rule_db->posts);
	zbx_free(rule_db->posts_orig);
	zbx_free(rule_db->status_codes);
	zbx_free(rule_db->status_codes_orig);
	zbx_free(rule_db->http_proxy);
	zbx_free(rule_db->http_proxy_orig);
	zbx_free(rule_db->headers);
	zbx_free(rule_db->headers_orig);
	zbx_free(rule_db->ssl_cert_file);
	zbx_free(rule_db->ssl_cert_file_orig);
	zbx_free(rule_db->ssl_key_file);
	zbx_free(rule_db->ssl_key_file_orig);
	zbx_free(rule_db->ssl_key_password);
	zbx_free(rule_db->ssl_key_password_orig);
	zbx_free(rule_db->lifetime);
	zbx_free(rule_db->lifetime_orig);
	zbx_free(rule_db->enabled_lifetime);
	zbx_free(rule_db->enabled_lifetime_orig);

	zbx_vector_lld_item_preproc_ptr_clear_ext(&rule_db->preproc_ops, lld_item_preproc_free);
	zbx_vector_lld_item_preproc_ptr_destroy(&rule_db->preproc_ops);

	zbx_vector_item_param_ptr_clear_ext(&rule_db->item_params, zbx_item_param_free);
	zbx_vector_item_param_ptr_destroy(&rule_db->item_params);

	zbx_free(rule_db);
}

int	lld_rule_db_compare(const void *d1, const void *d2)
{
	const zbx_lld_rule_db_t	*r1 = *(const zbx_lld_rule_db_t * const *)d1;
	const zbx_lld_rule_db_t	*r2 = *(const zbx_lld_rule_db_t * const *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(r1->itemid, r2->itemid);

	return 0;
}

static void	lld_rule_db_dump(const zbx_lld_rule_db_t *rule)
{
	zabbix_log(LOG_LEVEL_TRACE, "itemid:" ZBX_FS_UI64, rule->itemid);
	zabbix_log(LOG_LEVEL_TRACE, "  flags:%08lx", rule->flags);
	zabbix_log(LOG_LEVEL_TRACE, "  parent_itemid:" ZBX_FS_UI64, rule->parent_itemid);
	zabbix_log(LOG_LEVEL_TRACE, "  key_proto:%s key:%s key_orig:%s", ZBX_NULL2EMPTY_STR(rule->key_proto),
			ZBX_NULL2EMPTY_STR(rule->key), ZBX_NULL2EMPTY_STR(rule->key_orig));
	zabbix_log(LOG_LEVEL_TRACE, "  name:%s name_proto:%s", ZBX_NULL2EMPTY_STR(rule->name),
			ZBX_NULL2EMPTY_STR(rule->name_proto));
	zabbix_log(LOG_LEVEL_TRACE, "  delay:%s delay_orig:%s", ZBX_NULL2EMPTY_STR(rule->delay),
			ZBX_NULL2EMPTY_STR(rule->delay_orig));
	zabbix_log(LOG_LEVEL_TRACE, "  trapper_hosts:%s trapper_hosts_orig:%s", ZBX_NULL2EMPTY_STR(rule->trapper_hosts),
			ZBX_NULL2EMPTY_STR(rule->trapper_hosts_orig));
	zabbix_log(LOG_LEVEL_TRACE, "  formula:%s formula_orig:%s", ZBX_NULL2EMPTY_STR(rule->formula),
			ZBX_NULL2EMPTY_STR(rule->formula_orig));
	zabbix_log(LOG_LEVEL_TRACE, "  params:%s params_orig:%s", ZBX_NULL2EMPTY_STR(rule->params),
			ZBX_NULL2EMPTY_STR(rule->params_orig));
	zabbix_log(LOG_LEVEL_TRACE, "  ipmi_sensor:%s ipmi_sensor_orig:%s", ZBX_NULL2EMPTY_STR(rule->ipmi_sensor),
			ZBX_NULL2EMPTY_STR(rule->ipmi_sensor_orig));
	zabbix_log(LOG_LEVEL_TRACE, "  snmp_oid:%s snmp_oid_orig:%s", ZBX_NULL2EMPTY_STR(rule->snmp_oid),
			ZBX_NULL2EMPTY_STR(rule->snmp_oid_orig));
	zabbix_log(LOG_LEVEL_TRACE, "  username:%s username_orig:%s", ZBX_NULL2EMPTY_STR(rule->username),
			ZBX_NULL2EMPTY_STR(rule->username_orig));
	zabbix_log(LOG_LEVEL_TRACE, "  password:%s password_orig:%s", ZBX_NULL2EMPTY_STR(rule->password),
			ZBX_NULL2EMPTY_STR(rule->password_orig));
	zabbix_log(LOG_LEVEL_TRACE, "  publickey_orig:%s privatekey_orig:%s", ZBX_NULL2EMPTY_STR(rule->publickey_orig),
			ZBX_NULL2EMPTY_STR(rule->privatekey_orig));
	zabbix_log(LOG_LEVEL_TRACE, "  description:%s description_orig:%s", ZBX_NULL2EMPTY_STR(rule->description),
			ZBX_NULL2EMPTY_STR(rule->description_orig));
	zabbix_log(LOG_LEVEL_TRACE, "  jmx_endpoint:%s jmx_endpoint_orig:%s", ZBX_NULL2EMPTY_STR(rule->jmx_endpoint),
			ZBX_NULL2EMPTY_STR(rule->jmx_endpoint_orig));
	zabbix_log(LOG_LEVEL_TRACE, "  timeout:%s timeout_orig:%s", ZBX_NULL2EMPTY_STR(rule->timeout),
			ZBX_NULL2EMPTY_STR(rule->timeout_orig));
	zabbix_log(LOG_LEVEL_TRACE, "  url:%s url_orig:%s", ZBX_NULL2EMPTY_STR(rule->url),
			ZBX_NULL2EMPTY_STR(rule->url_orig));
	zabbix_log(LOG_LEVEL_TRACE, "  query_fields:%s query_fields_orig:%s", ZBX_NULL2EMPTY_STR(rule->query_fields),
			ZBX_NULL2EMPTY_STR(rule->query_fields_orig));
	zabbix_log(LOG_LEVEL_TRACE, "  posts:%s posts_orig:%s", ZBX_NULL2EMPTY_STR(rule->posts),
			ZBX_NULL2EMPTY_STR(rule->posts_orig));
	zabbix_log(LOG_LEVEL_TRACE, "  status_codes:%s status_codes_orig:%s", ZBX_NULL2EMPTY_STR(rule->status_codes),
			ZBX_NULL2EMPTY_STR(rule->status_codes_orig));
	zabbix_log(LOG_LEVEL_TRACE, "  http_proxy:%s http_proxy_orig:%s", ZBX_NULL2EMPTY_STR(rule->http_proxy),
			ZBX_NULL2EMPTY_STR(rule->http_proxy_orig));
	zabbix_log(LOG_LEVEL_TRACE, "  headers:%s headers_orig:%s", ZBX_NULL2EMPTY_STR(rule->headers),
			ZBX_NULL2EMPTY_STR(rule->headers_orig));
	zabbix_log(LOG_LEVEL_TRACE, "  ssl_cert_file:%s ssl_cert_file_orig:%s", ZBX_NULL2EMPTY_STR(rule->ssl_cert_file),
			ZBX_NULL2EMPTY_STR(rule->ssl_cert_file_orig));
	zabbix_log(LOG_LEVEL_TRACE, "  ssl_key_file:%s ssl_key_file_orig:%s", ZBX_NULL2EMPTY_STR(rule->ssl_key_file),
			ZBX_NULL2EMPTY_STR(rule->ssl_key_file_orig));
	zabbix_log(LOG_LEVEL_TRACE, "  ssl_key_password:%s ssl_key_password_orig:%s",
			ZBX_NULL2EMPTY_STR(rule->ssl_key_password), ZBX_NULL2EMPTY_STR(rule->ssl_key_password_orig));
	zabbix_log(LOG_LEVEL_TRACE, "  lifetime:%s lifetime_orig:%s", ZBX_NULL2EMPTY_STR(rule->lifetime),
			ZBX_NULL2EMPTY_STR(rule->lifetime_orig));
	zabbix_log(LOG_LEVEL_TRACE, "  enabled_lifetime:%s enabled_lifetime_orig:%s",
			ZBX_NULL2EMPTY_STR(rule->enabled_lifetime), ZBX_NULL2EMPTY_STR(rule->enabled_lifetime_orig));
	zabbix_log(LOG_LEVEL_TRACE, "  type:%u type_orig:%u", rule->type, rule->type_orig);
	zabbix_log(LOG_LEVEL_TRACE, "  authtype_orig:%u follow_redirects_orig:%u", rule->authtype_orig,
			rule->follow_redirects_orig);
	zabbix_log(LOG_LEVEL_TRACE, "  post_type_orig:%u retrieve_mode_orig:%u",rule->post_type_orig,
			rule->retrieve_mode_orig);
	zabbix_log(LOG_LEVEL_TRACE, "  request_method_orig:%u output_format_orig:%u", rule->request_method_orig,
			rule->output_format_orig);
	zabbix_log(LOG_LEVEL_TRACE, "  verify_peer_orig:%u verify_host_orig:%u", rule->verify_peer_orig,
			rule->verify_host_orig);
	zabbix_log(LOG_LEVEL_TRACE, "  allow_traps_orig:%u", rule->allow_traps_orig);
	zabbix_log(LOG_LEVEL_TRACE, "  lifetime_type_orig:%d enabled_lifetime_type_orig:%d", rule->lifetime_type_orig,
			rule->enabled_lifetime_type_orig);
	zabbix_log(LOG_LEVEL_TRACE, "  discovery_status:%u", rule->discovery_status);
	zabbix_log(LOG_LEVEL_TRACE, "  disable_source:%u", rule->disable_source);
	zabbix_log(LOG_LEVEL_TRACE, "  status:%u", rule->status);
}

typedef struct
{
	zbx_uint64_t			itemid;
	zbx_uint64_t			parent_itemid;
	const zbx_lld_rule_prototype_t	*prototype;
	char				*key_proto;
	int				lastcheck;
	unsigned char			discovery_status;
	int				ts_delete;
	int				ts_disable;
	unsigned char			disable_source;
}
zbx_lld_rule_discovery_t;

static int	lld_update_uchar(const char *value_db, unsigned char value_new, unsigned char *value_old)
{
	unsigned char	value;

	ZBX_STR2UCHAR(value, value_db);
	if (value == value_new)
		return FAIL;

	*value_old = value;

	return SUCCEED;
}

static int	lld_update_int(const char *value_db, int value_new, int *value_old)
{
	int	value;

	value = atoi(value_db);
	if (value == value_new)
		return FAIL;

	*value_old = value;

	return SUCCEED;
}

static int	lld_update_ui64(const char *value_db, zbx_uint64_t value_new, zbx_uint64_t *value_old)
{
	zbx_uint64_t	value;


	ZBX_DBROW2UINT64(value, value_db);
	if (value == value_new)
		return FAIL;

	*value_old = value;

	return SUCCEED;
}

static int	lld_update_str(const char *value_db, const char *value_new, char **value_old)
{
	if (0 == strcmp(value_db, value_new))
		return FAIL;

	*value_old = zbx_strdup(NULL, value_db);

	return SUCCEED;
}

static void	lld_rules_fetch_parameters(zbx_vector_lld_rule_db_ptr_t *rules, zbx_vector_uint64_t *itemids)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select item_parameterid,itemid,name,value"
			" from item_parameter"
			" where");

	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", itemids->values, itemids->values_num);
	result = zbx_db_select("%s", sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_item_param_t	*item_param;
		zbx_lld_rule_db_t	rule_local;
		int			index;

		ZBX_STR2UINT64(rule_local.itemid, row[1]);
		if (FAIL == (index = zbx_vector_lld_rule_db_ptr_bsearch(rules, &rule_local, lld_rule_db_compare)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		item_param = zbx_item_param_create(row[2], row[3]);
		ZBX_STR2UINT64(item_param->item_parameterid, row[0]);
		zbx_vector_item_param_ptr_append(&rules->values[index]->item_params, item_param);
	}
	zbx_db_free_result(result);
}

static void	lld_rules_fetch_preprocessing(zbx_vector_lld_rule_db_ptr_t *rules, zbx_vector_uint64_t *itemids)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select item_preprocid,itemid,step,type,params,error_handler,error_handler_params"
			" from item_preproc"
			" where");

	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", itemids->values, itemids->values_num);
	result = zbx_db_select("%s", sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_lld_item_preproc_t	*preproc_op;
		zbx_lld_rule_db_t	rule_local;
		int			index;
		zbx_uint64_t		item_preprocid;

		ZBX_STR2UINT64(rule_local.itemid, row[1]);
		if (FAIL == (index = zbx_vector_lld_rule_db_ptr_bsearch(rules, &rule_local, lld_rule_db_compare)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		ZBX_STR2UINT64(item_preprocid, row[0]);
		preproc_op = lld_item_preproc_create(item_preprocid, ZBX_FLAG_LLD_ITEM_PREPROC_UNSET,
				atoi(row[2]), atoi(row[3]), row[4], atoi(row[5]), row[6]);
		zbx_vector_lld_item_preproc_ptr_append(&rules->values[index]->preproc_ops, preproc_op);
	}
	zbx_db_free_result(result);
}

static void	lld_rule_fetch_rules(const zbx_vector_lld_rule_prototype_ptr_t *rule_prototypes,
		zbx_vector_lld_rule_db_ptr_t *rules)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_vector_uint64_t	parent_itemids, itemids;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;

	zbx_vector_uint64_create(&parent_itemids);
	zbx_vector_uint64_reserve(&parent_itemids, rule_prototypes->values_num);
	zbx_vector_uint64_create(&itemids);

	for (int i = 0; i < rule_prototypes->values_num; i++)
		zbx_vector_uint64_append(&parent_itemids, rule_prototypes->values[i]->itemid);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select d.itemid,d.key_,d.lastcheck,d.status,d.ts_delete,d.ts_disable,d.disable_source,"
			"d.parent_itemid,i.name,i.key_,i.type,i.delay,i.trapper_hosts,i.formula,"
			"i.params,i.ipmi_sensor,i.snmp_oid,i.authtype,i.username,i.password,i.publickey,i.privatekey,"
			"i.description,i.interfaceid,i.jmx_endpoint,i.master_itemid,i.timeout,i.url,i.query_fields,"
			"i.posts,i.status_codes,i.follow_redirects,i.post_type,i.http_proxy,i.headers,i.retrieve_mode,"
			"i.request_method,i.output_format,i.ssl_cert_file,i.ssl_key_file,i.ssl_key_password,"
			"i.verify_peer,i.verify_host,i.allow_traps,i.status,i.lifetime,i.lifetime_type,"
			"i.enabled_lifetime,i.enabled_lifetype_type"
			" from item_discovery d join items i on i.itemid=d.itemid"
			" where");

	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "d.parent_itemid", parent_itemids.values,
			parent_itemids.values_num);


	result = zbx_db_select("%s", sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_lld_rule_db_t		*rule;
		zbx_lld_rule_prototype_t	*rule_prototype, rule_prototype_local;
		int				index;

		ZBX_DBROW2UINT64(rule_prototype_local.itemid, row[7]);

		if (FAIL == (index = zbx_vector_lld_rule_prototype_ptr_bsearch(rule_prototypes, &rule_prototype_local,
				lld_rule_prototype_compare)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}
		rule_prototype = rule_prototypes->values[index];

		rule = (zbx_lld_rule_db_t *)zbx_malloc(NULL, sizeof(zbx_lld_rule_db_t));
		memset(rule, 0, sizeof(zbx_lld_rule_db_t));

		ZBX_STR2UINT64(rule->itemid, row[0]);
		rule->key_proto = zbx_strdup(NULL, row[1]);
		rule->lastcheck = atoi(row[2]);
		ZBX_STR2UCHAR(rule->discovery_status, row[3]);
		rule->ts_delete = atoi(row[4]);
		rule->ts_disable = atoi(row[5]);
		ZBX_STR2UCHAR(rule->disable_source, row[6]);
		rule->parent_itemid = rule_prototype_local.itemid;

		rule->name = zbx_strdup(NULL, row[8]);
		rule->key = zbx_strdup(NULL, row[9]);
		rule->flags = ZBX_FLAG_LLD_ITEM_UNSET;

		rule->type = rule_prototype->type;

		if (SUCCEED == lld_update_uchar(row[10], rule_prototype->type, &rule->type_orig))
			rule->flags |= ZBX_FLAG_LLD_RULE_UPDATE_TYPE;

		rule->delay = zbx_strdup(NULL, row[11]);

		if (SUCCEED == lld_update_str(row[12], rule_prototype->trapper_hosts, &rule->trapper_hosts_orig))
			rule->flags |= ZBX_FLAG_LLD_RULE_UPDATE_TRAPPER_HOSTS;


		if (SUCCEED == lld_update_str(row[13], rule_prototype->formula, &rule->formula_orig))
			rule->flags |= ZBX_FLAG_LLD_RULE_UPDATE_FORMULA;

		rule->params = zbx_strdup(NULL, row[14]);
		rule->ipmi_sensor = zbx_strdup(NULL, row[15]);
		rule->snmp_oid = zbx_strdup(NULL, row[16]);

		if (SUCCEED == lld_update_uchar(row[17], rule_prototype->authtype, &rule->authtype_orig))
			rule->flags |= ZBX_FLAG_LLD_RULE_UPDATE_AUTHTYPE;

		rule->username = zbx_strdup(NULL, row[18]);
		rule->password = zbx_strdup(NULL, row[19]);

		if (SUCCEED == lld_update_str(row[20], rule_prototype->publickey, &rule->publickey_orig))
			rule->flags |= ZBX_FLAG_LLD_RULE_UPDATE_PUBLICKEY;

		if (SUCCEED == lld_update_str(row[21], rule_prototype->privatekey, &rule->privatekey_orig))
			rule->flags |= ZBX_FLAG_LLD_RULE_UPDATE_PRIVATEKEY;

		rule->description = zbx_strdup(NULL, row[22]);

		if (SUCCEED == lld_update_ui64(row[23], rule_prototype->interfaceid, &rule->interfaceid_orig))
			rule->flags |= ZBX_FLAG_LLD_RULE_UPDATE_INTERFACEID;

		rule->jmx_endpoint = zbx_strdup(NULL, row[24]);
		rule->jmx_endpoint_orig = NULL;

		ZBX_DBROW2UINT64(rule->master_itemid, row[25]);

		rule->timeout = zbx_strdup(NULL, row[26]);
		rule->timeout_orig = NULL;

		rule->url = zbx_strdup(NULL, row[27]);
		rule->url_orig = NULL;

		rule->query_fields = zbx_strdup(NULL, row[28]);
		rule->query_fields_orig = NULL;

		rule->posts = zbx_strdup(NULL, row[29]);
		rule->posts_orig = NULL;

		rule->status_codes = zbx_strdup(NULL, row[30]);
		rule->status_codes_orig = NULL;

		if (SUCCEED == lld_update_uchar(row[31], rule_prototype->follow_redirects,
				&rule->follow_redirects_orig))
		{
			rule->flags |= ZBX_FLAG_LLD_RULE_UPDATE_FOLLOW_REDIRECTS;
		}

		if (SUCCEED == lld_update_uchar(row[32], rule_prototype->post_type, &rule->post_type_orig))
			rule->flags |= ZBX_FLAG_LLD_RULE_UPDATE_POST_TYPE;

		rule->http_proxy = zbx_strdup(NULL, row[33]);
		rule->headers = zbx_strdup(NULL, row[34]);

		if (SUCCEED == lld_update_uchar(row[35], rule_prototype->retrieve_mode, &rule->retrieve_mode_orig))
			rule->flags |= ZBX_FLAG_LLD_RULE_UPDATE_RETRIEVE_MODE;

		if (SUCCEED == lld_update_uchar(row[36], rule_prototype->request_method, &rule->request_method_orig))
			rule->flags |= ZBX_FLAG_LLD_RULE_UPDATE_REQUEST_METHOD;

		if (SUCCEED == lld_update_uchar(row[37], rule_prototype->output_format, &rule->output_format_orig))
			rule->flags |= ZBX_FLAG_LLD_RULE_UPDATE_OUTPUT_FORMAT;

		rule->ssl_cert_file = zbx_strdup(NULL, row[38]);
		rule->ssl_key_file = zbx_strdup(NULL, row[39]);
		rule->ssl_key_password = zbx_strdup(NULL, row[40]);

		if (SUCCEED == lld_update_uchar(row[41], rule_prototype->verify_peer, &rule->verify_peer_orig))
			rule->flags |= ZBX_FLAG_LLD_RULE_UPDATE_VERIFY_PEER;

		if (SUCCEED == lld_update_uchar(row[42], rule_prototype->verify_host, &rule->verify_host_orig))
			rule->flags |= ZBX_FLAG_LLD_RULE_UPDATE_VERIFY_HOST;

		if (SUCCEED == lld_update_uchar(row[43], rule_prototype->allow_traps, &rule->allow_traps_orig))
			rule->flags |= ZBX_FLAG_LLD_RULE_UPDATE_ALLOW_TRAPS;

		ZBX_STR2UCHAR(rule->status, row[44]);

		rule->lifetime = zbx_strdup(NULL, row[45]);
		rule->enabled_lifetime = zbx_strdup(NULL, row[47]);

		if (SUCCEED == lld_update_int(row[46], rule_prototype->lifetime_type, &rule->lifetime_type_orig))
			rule->flags |= ZBX_FLAG_LLD_RULE_UPDATE_LIFETIME_TYPE;


		if (SUCCEED == lld_update_int(row[48], rule_prototype->enabled_lifetime_type,
				&rule->enabled_lifetime_type_orig))
		{
			rule->flags |= ZBX_FLAG_LLD_RULE_UPDATE_ENABLED_LIFETIME_TYPE;
		}

		zbx_vector_lld_item_preproc_ptr_create(&rule->preproc_ops);
		zbx_vector_item_param_ptr_create(&rule->item_params);

		rule->lld_row = NULL;

		zbx_vector_lld_rule_db_ptr_append(rules, rule);
		zbx_vector_uint64_append(&itemids, rule->itemid);

	}
	zbx_db_free_result(result);

	if (0 == rules->values_num)
		goto out;

	zbx_vector_lld_rule_db_ptr_sort(rules, lld_rule_db_compare);

	lld_rules_fetch_parameters(rules, &itemids);
	lld_rules_fetch_preprocessing(rules, &itemids);

	/* TODO: fetch lld_macros, filters and overrides */

out:
	zbx_free(sql);
	zbx_vector_uint64_destroy(&parent_itemids);
	zbx_vector_uint64_destroy(&itemids);
}

static void	lld_rules_make(const zbx_vector_lld_rule_prototype_ptr_t *rule_prototypes,
		zbx_vector_lld_row_ptr_t *lld_rows, zbx_vector_lld_rule_db_ptr_t *rules, zbx_hashset_t *rules_index,
		int lastcheck, char **error)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	/* index rules/prototypes */

	for (int i = 0; i < rule_prototypes->values_num; i++)
	{
		for (int j = 0; j < lld_rows->values_num; j++)
			zbx_vector_lld_row_ptr_append(&rule_prototypes->values[i]->lld_rows, lld_rows->values[j]);
	}

	for (int i = 0; i < rules->values_num; i++)
	{
		zbx_lld_rule_ref_t		ref_local;
		zbx_lld_rule_db_t		*rule = rules->values[i];
		zbx_lld_rule_prototype_t	rule_prototype_local, *rule_prototype;
		int				index;

		rule_prototype_local.itemid = rule->parent_itemid;

		if (FAIL == (index = zbx_vector_lld_rule_prototype_ptr_bsearch(rule_prototypes, &rule_prototype_local,
				lld_rule_prototype_compare)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		rule_prototype = rule_prototypes->values[index];

		if (0 == rule_prototype->rule_index.num_slots)
			zbx_hashset_reserve(&rule_prototype->rule_index, rules->values_num);

		ref_local.rule = rule;

		zbx_hashset_insert(&rule_prototype->rule_index, &ref_local, sizeof(ref_local));

		if (FAIL == zbx_vector_str_search(&rule_prototype->keys, rule->key_proto, ZBX_DEFAULT_STR_COMPARE_FUNC))
			zbx_vector_str_append(&rule_prototype->keys, rule->key_proto);
	}

	for (int i = 0; i < rule_prototypes->values_num; i++)
	{
		zbx_lld_rule_db_t		rule_local = {0};
		zbx_lld_rule_ref_t		*ref, ref_local = {.rule = &rule_local};
		zbx_lld_rule_prototype_t	*rule_prototype = rule_prototypes->values[i];

		for (int j = rule_prototype->lld_rows.values_num - 1; j >= 0; j--)
		{
			zbx_lld_row_t	*lld_row = rule_prototype->lld_rows.values[j];

			for (int k = 0; k < rule_prototype->keys.values_num; k++)
			{
				zbx_lld_rule_index_t	*rule_index, rule_index_local;

				rule_local.key = zbx_strdup(rule_local.key, rule_prototype->keys.values[k]);

				if (SUCCEED != zbx_substitute_key_macros(&rule_local.key, NULL, NULL,
						lld_resolve_macros, lld_row->data, ZBX_MACRO_TYPE_ITEM_KEY, NULL, 0))
				{
					continue;
				}

				if (NULL == (ref = (zbx_lld_rule_ref_t *)zbx_hashset_search(&rule_prototype->rule_index,
						&ref_local)))
				{
					continue;
				}

				if (SUCCEED != lld_validate_item_override_no_discover(&lld_row->overrides,
						ref->rule->name, rule_prototype->discover))
				{
					continue;
				}

				rule_index_local.parent_itemid = ref->rule->parent_itemid;
				rule_index_local.lld_row = lld_row;
				rule_index_local.rule = ref->rule;
				zbx_hashset_insert(rules_index, &rule_index_local, sizeof(rule_index_local));

				zbx_vector_lld_row_ptr_remove_noorder(&rule_prototype->lld_rows, j);
				zbx_hashset_remove_direct(&rule_prototype->rule_index, ref);

				break;
			}
		}

		zbx_free(rule_local.key);
	}


}

int	lld_update_rules_scrap(zbx_uint64_t hostid, zbx_uint64_t lld_ruleid, zbx_vector_lld_row_ptr_t *lld_rows,
		char **error, const zbx_lld_lifetime_t *lifetime, const zbx_lld_lifetime_t *enabled_lifetime,
		int lastcheck)
{
	zbx_vector_lld_rule_prototype_ptr_t	rule_prototypes;
	zbx_vector_lld_rule_db_ptr_t		rules;
	zbx_hashset_t				rules_index;

	// WDN
	zabbix_increase_log_level();
	zabbix_increase_log_level();

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() lld_ruleid:" ZBX_FS_UI64, __func__, lld_ruleid);

	zbx_vector_lld_rule_prototype_ptr_create(&rule_prototypes);

	if (0 == lld_rule_fetch_prototypes(lld_ruleid, &rule_prototypes))
		goto finish;

	zbx_vector_lld_rule_db_ptr_create(&rules);

	zbx_hashset_create(&rules_index, (size_t)(rule_prototypes.values_num * lld_rows->values_num),
			lld_rule_index_hash, lld_rule_index_compare);

	lld_rule_fetch_rules(&rule_prototypes, &rules);

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_TRACE))
	{
		zabbix_log(LOG_LEVEL_TRACE, "LLD rule prototypes:");

		for (int i = 0; i < rule_prototypes.values_num; i++)
			lld_rule_prototype_dump(rule_prototypes.values[i]);

		zabbix_log(LOG_LEVEL_TRACE, "discovered LLD rules:");

		for (int i = 0; i < rules.values_num; i++)
			lld_rule_db_dump(rules.values[i]);
	}

out:
	zbx_hashset_destroy(&rules_index);

	zbx_vector_lld_rule_db_ptr_clear_ext(&rules, lld_rule_db_free);
	zbx_vector_lld_rule_db_ptr_destroy(&rules);

finish:
	zbx_vector_lld_rule_prototype_ptr_clear_ext(&rule_prototypes, lld_rule_prototpe_free);
	zbx_vector_lld_rule_prototype_ptr_destroy(&rule_prototypes);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	zabbix_decrease_log_level();
	zabbix_decrease_log_level();

	return SUCCEED;
}

// WDN: remove deprecated code above

/******************************************************************************
 *                                                                            *
 * Purpose: Updates existing lld rule macro paths and creates new ones based  *
 *          on rule prototypes.                                               *
 *                                                                            *
 * Parameters: item_prototypes - [IN]                                         *
 *             items           - [IN/OUT] sorted list of items                *
 *                                                                            *
 ******************************************************************************/
void	lld_rule_macro_paths_make(const zbx_vector_lld_item_prototype_ptr_t *item_prototypes,
		zbx_vector_lld_item_full_ptr_t *items)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (int i = 0; i < items->values_num; i++)
	{
		zbx_lld_item_full_t	*item = items->values[i];
		int			index;

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		zbx_lld_item_prototype_t	cmp = {.itemid = item->parent_itemid};

		if (FAIL == (index = zbx_vector_lld_item_prototype_ptr_bsearch(item_prototypes, &cmp,
				lld_item_prototype_compare_func)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		zbx_sync_rowset_merge(&item->macro_paths, &item_prototypes->values[index]->macro_paths);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	lld_rule_get_prototype_macro_paths(zbx_vector_lld_item_prototype_ptr_t *item_prototypes,
		zbx_vector_uint64_t *protoids)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select lld_macro_pathid,itemid,lld_macro,path"
			" from lld_macro_path where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", protoids->values, protoids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by lld_macro_pathid");

	result = zbx_db_select("%s", sql);
	zbx_free(sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_lld_item_prototype_t	item_prototype_local;
		int				index;

		ZBX_STR2UINT64(item_prototype_local.itemid, row[1]);

		if (FAIL == (index = zbx_vector_lld_item_prototype_ptr_bsearch(item_prototypes, &item_prototype_local,
				lld_item_prototype_compare_func)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		zbx_sync_rowset_add_row(&item_prototypes->values[index]->macro_paths, row[0], row[2], row[3]);
	}
	zbx_db_free_result(result);
}
