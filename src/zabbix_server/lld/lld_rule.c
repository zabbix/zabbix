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
	zbx_hashset_t				item_index;
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

	zbx_hashset_destroy(&rule_prototype->item_index);

	zbx_vector_str_destroy(&rule_prototype->keys);

	zbx_free(rule_prototype);
}

static int	lld_rule_prototype_compare_func(const void *d1, const void *d2)
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
				lld_rule_prototype_compare_func)))
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
				lld_rule_prototype_compare_func)))
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

static void	lld_rule_fetch_prototypes(zbx_uint64_t lld_ruleid, zbx_vector_lld_rule_prototype_ptr_t *rule_prototypes)
{
	zbx_db_result_t			result;
	zbx_db_row_t			row;
	zbx_lld_rule_prototype_t	*rule_prototype;
	zbx_uint64_t			itemid;
	int				index;
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
		zbx_vector_str_create(&rule_prototype->keys);
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
		zbx_hashset_create(&rule_prototype->item_index, 0, lld_item_ref_key_hash_func,
				lld_item_ref_key_compare_func);

		zbx_vector_lld_rule_prototype_ptr_append(rule_prototypes, rule_prototype);
	}
	zbx_db_free_result(result);

	zbx_vector_lld_rule_prototype_ptr_sort(rule_prototypes, lld_rule_prototype_compare_func);

	if (0 == rule_prototypes->values_num)
		goto out;

	lld_rule_fetch_prototype_preproc(rule_prototypes, &protoids);
	lld_rule_fetch_prototype_params(rule_prototypes, &protoids);;

out:
	zbx_vector_uint64_destroy(&protoids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d prototypes", __func__, rule_prototypes->values_num);
}


int	lld_update_rules(zbx_uint64_t hostid, zbx_uint64_t lld_ruleid, zbx_vector_lld_row_ptr_t *lld_rows,
		char **error, const zbx_lld_lifetime_t *lifetime, const zbx_lld_lifetime_t *enabled_lifetime,
		int lastcheck)
{
	zbx_vector_lld_rule_prototype_ptr_t	rule_prototypes;

	zbx_vector_lld_rule_prototype_ptr_create(&rule_prototypes);


	lld_rule_fetch_prototypes(lld_ruleid, &rule_prototypes);


	zbx_vector_lld_rule_prototype_ptr_clear_ext(&rule_prototypes, lld_rule_prototpe_free);
	zbx_vector_lld_rule_prototype_ptr_destroy(&rule_prototypes);

	return SUCCEED;
}
