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

#include "proxyconfigread.h"

#include "zbxdbwrap.h"
#include "zbxdbhigh.h"
#include "zbxkvs.h"
#include "zbxvault.h"
#include "zbxcommshigh.h"
#include "zbxcompress.h"
#include "zbxcrypto.h"
#include "zbx_item_constants.h"
#include "zbxpgservice.h"
#include "zbxserialize.h"
#include "zbxalgo.h"
#include "zbxdb.h"
#include "zbxdbschema.h"
#include "zbxjson.h"
#include "zbxnum.h"
#include "zbxstr.h"
#include "zbxversion.h"
#include "zbxcomms.h"
#include "zbxipcservice.h"

typedef struct
{
	char		*path;
	zbx_hashset_t	keys;
}
zbx_keys_path_t;

ZBX_PTR_VECTOR_DECL(keys_path_ptr, zbx_keys_path_t *)
ZBX_PTR_VECTOR_IMPL(keys_path_ptr, zbx_keys_path_t *)

static int	keys_path_compare(const void *d1, const void *d2)
{
	const zbx_keys_path_t	*ptr1 = *((const zbx_keys_path_t * const *)d1);
	const zbx_keys_path_t	*ptr2 = *((const zbx_keys_path_t * const *)d2);

	return strcmp(ptr1->path, ptr2->path);
}

static zbx_hash_t	keys_hash(const void *data)
{
	return ZBX_DEFAULT_STRING_HASH_ALGO(*(const char * const *)data, strlen(*(const char * const *)data),
			ZBX_DEFAULT_HASH_SEED);
}

static int	keys_compare(const void *d1, const void *d2)
{
	return strcmp(*(const char * const *)d1, *(const char * const *)d2);
}

static void	key_path_free(zbx_keys_path_t *keys_path)
{
	zbx_hashset_iter_t	iter;
	char			**ptr;

	zbx_hashset_iter_reset(&keys_path->keys, &iter);
	while (NULL != (ptr = (char **)zbx_hashset_iter_next(&iter)))
		zbx_free(*ptr);
	zbx_hashset_destroy(&keys_path->keys);

	zbx_free(keys_path->path);
	zbx_free(keys_path);
}

static void	get_macro_secrets(const zbx_vector_keys_path_ptr_t *keys_paths, struct zbx_json *j,
		const zbx_config_vault_t *config_vault, const char *config_source_ip,
		const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location)
{
	zbx_kvs_t	kvs;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_kvs_create(&kvs, 100);

	zbx_json_addobject(j, ZBX_PROTO_TAG_MACRO_SECRETS);

	for (int i = 0; i < keys_paths->values_num; i++)
	{
		zbx_keys_path_t		*keys_path = keys_paths->values[i];
		char			*error = NULL, **ptr;
		zbx_hashset_iter_t	iter;

		if (FAIL == zbx_vault_kvs_get(keys_path->path, &kvs, config_vault, config_source_ip,
				config_ssl_ca_location, config_ssl_cert_location, config_ssl_key_location, &error))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot get secrets for path \"%s\": %s", keys_path->path, error);
			zbx_free(error);
			continue;
		}

		zbx_json_addobject(j, keys_path->path);

		zbx_hashset_iter_reset(&keys_path->keys, &iter);
		while (NULL != (ptr = (char **)zbx_hashset_iter_next(&iter)))
		{
			zbx_kv_t	*kv, kv_local;

			kv_local.key = *ptr;

			if (NULL != (kv = zbx_kvs_search(&kvs, &kv_local)))
				zbx_json_addstring(j, kv->key, kv->value, ZBX_JSON_TYPE_STRING);
		}
		zbx_json_close(j);

		zbx_kvs_clear(&kvs);
	}

	zbx_json_close(j);
	zbx_kvs_destroy(&kvs);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds database row to proxy config json data                       *
 *                                                                            *
 * Parameters: row    - [IN] database row to add                              *
 *             table  - [IN] table configuration                              *
 *             recids - [OUT] record identifiers (optional)                   *
 *             j      - [OUT] output json                                     *
 *                                                                            *
 ******************************************************************************/
static void	proxyconfig_add_row(const zbx_db_row_t row, const zbx_db_table_t *table,
		zbx_vector_uint64_t *recids, struct zbx_json *j)
{
	int	fld = 0;

	zbx_json_addstring(j, NULL, row[fld++], ZBX_JSON_TYPE_INT);

	if (NULL != recids)
	{
		zbx_uint64_t	recid;

		ZBX_STR2UINT64(recid, row[0]);
		zbx_vector_uint64_append(recids, recid);
	}

	for (int i = 0; 0 != table->fields[i].name; i++)
	{
		if (0 == (table->fields[i].flags & ZBX_PROXY))
			continue;

		switch (table->fields[i].type)
		{
			case ZBX_TYPE_INT:
			case ZBX_TYPE_UINT:
			case ZBX_TYPE_ID:
				if (SUCCEED != zbx_db_is_null(row[fld]))
					zbx_json_addstring(j, NULL, row[fld], ZBX_JSON_TYPE_INT);
				else
					zbx_json_addstring(j, NULL, NULL, ZBX_JSON_TYPE_NULL);
				break;
			default:
				zbx_json_addstring(j, NULL, row[fld], ZBX_JSON_TYPE_STRING);
				break;
		}
		fld++;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets table fields, adds them to output json and sql select        *
 *                                                                            *
 * Parameters: sql        - [IN/OUT] sql select string                        *
 *             sql_alloc  - [IN/OUT]                                          *
 *             sql_offset - [IN/OUT]                                          *
 *             table      - [IN]                                              *
 *             alias      - [IN] table alias                                  *
 *             j          - [OUT] output json                                 *
 *                                                                            *
 ******************************************************************************/
static void	proxyconfig_get_fields(char **sql, size_t *sql_alloc, size_t *sql_offset, const zbx_db_table_t *table,
		const char *alias, struct zbx_json *j)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "select %s%s", alias, table->recid);

	zbx_json_addarray(j, "fields");
	zbx_json_addstring(j, NULL, table->recid, ZBX_JSON_TYPE_STRING);

	for (int i = 0; 0 != table->fields[i].name; i++)
	{
		if (0 == (table->fields[i].flags & ZBX_PROXY))
			continue;

		zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, ',');
		zbx_strcpy_alloc(sql, sql_alloc, sql_offset, alias);
		zbx_strcpy_alloc(sql, sql_alloc, sql_offset, table->fields[i].name);

		zbx_json_addstring(j, NULL, table->fields[i].name, ZBX_JSON_TYPE_STRING);
	}

	zbx_json_close(j);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/**************************************************************************************
 *                                                                                    *
 * Purpose: gets global/host macro data of specified hosts from database              *
 *                                                                                    *
 * Parameters: table_name           - [IN] globalmacro/hostmacro                      *
 *             hostids              - [IN] target hostids for hostmacro table and     *
 *                                         NULL for globalmacro table                 *
 *             config_vault_db_path - [IN]                                            *
 *             keys_paths           - [OUT] vault macro path/key                      *
 *             j                    - [OUT] output json                               *
 *             error                - [OUT] error message                             *
 *                                                                                    *
 * Return value: SUCCEED - data was read successfully                                 *
 *               FAIL    - otherwise                                                  *
 *                                                                                    *
 **************************************************************************************/
static int	proxyconfig_get_macro_updates(const char *table_name, const zbx_vector_uint64_t *hostids,
		const char *config_vault_db_path, zbx_vector_keys_path_ptr_t *keys_paths, struct zbx_json *j,
		char **error)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	const zbx_db_table_t	*table;
	char			*sql;
	size_t			sql_alloc =  4 * ZBX_KIBIBYTE, sql_offset = 0;
	int			offset, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	table = zbx_db_get_table(table_name);
	zbx_json_addobject(j, table->table);

	sql = (char *)zbx_malloc(NULL, sql_alloc);

	proxyconfig_get_fields(&sql, &sql_alloc, &sql_offset, table, "", j);
	zbx_json_addarray(j, "data");

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " from %s", table->table);

	if (NULL != hostids)
	{
		/* no hosts to get macros from, send empty data */
		if (0 == hostids->values_num)
			goto end;

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " where");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids->values,
				hostids->values_num);
		offset = 1;
	}
	else
		offset = 0;

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by ");
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, table->recid);

	if (NULL == (result = zbx_db_select("%s", sql)))
	{
		*error = zbx_dsprintf(*error, "failed to get data from table \"%s\"", table->table);
		goto out;
	}

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " from %s", table->table);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_keys_path_t	*keys_path, keys_path_local;
		unsigned char	type;
		char		*path, *key;
		int		i;

		zbx_json_addarray(j, NULL);
		proxyconfig_add_row(row, table, NULL, j);
		zbx_json_close(j);

		ZBX_STR2UCHAR(type, row[3 + offset]);

		if (ZBX_MACRO_VALUE_VAULT != type)
			continue;

		zbx_strsplit_last(row[2 + offset], ':', &path, &key);

		if (NULL == key)
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot parse macro \"%s\" value \"%s\"",
					row[1 + offset], row[2 + offset]);
			goto next;
		}

		if (NULL != config_vault_db_path && 0 == strcasecmp(config_vault_db_path, path) &&
				(0 == strcasecmp(key, ZBX_PROTO_TAG_PASSWORD)
						|| 0 == strcasecmp(key, ZBX_PROTO_TAG_USERNAME)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot parse macro \"%s\" value \"%s\":"
					" database credentials should not be used with Vault macros",
					row[1 + offset], row[2 + offset]);
			goto next;
		}

		keys_path_local.path = path;

		if (FAIL == (i = zbx_vector_keys_path_ptr_search(keys_paths, &keys_path_local, keys_path_compare)))
		{
			keys_path = zbx_malloc(NULL, sizeof(zbx_keys_path_t));
			keys_path->path = path;

			zbx_hashset_create(&keys_path->keys, 0, keys_hash, keys_compare);
			zbx_hashset_insert(&keys_path->keys, &key, sizeof(key));

			zbx_vector_keys_path_ptr_append(keys_paths, keys_path);
			path = key = NULL;
		}
		else
		{
			keys_path = keys_paths->values[i];
			if (NULL == zbx_hashset_search(&keys_path->keys, &key))
			{
				zbx_hashset_insert(&keys_path->keys, &key, sizeof(key));
				key = NULL;
			}
		}
next:
		zbx_free(key);
		zbx_free(path);
	}
	zbx_db_free_result(result);
end:
	zbx_json_close(j);
	zbx_json_close(j);

	ret = SUCCEED;
out:
	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

static int	proxyconfig_get_config_table_data(const zbx_dc_proxy_t *proxy, struct zbx_json *j, char **error)
{
	zbx_db_result_t			result;
	zbx_db_row_t			row;
	const zbx_db_table_t		*table;
	char				*sql = NULL;
	size_t				sql_alloc =  4 * ZBX_KIBIBYTE, sql_offset = 0;
	int				ret = FAIL, fld = 0;
	const char			*alias = "t.", *alias_from = " t";
	zbx_dc_item_type_timeouts_t	timeouts;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	table = zbx_db_get_table("config");
	zbx_json_addobject(j, table->table);

	sql = (char *)zbx_malloc(NULL, sql_alloc);
	proxyconfig_get_fields(&sql, &sql_alloc, &sql_offset, table, alias, j);

	zbx_json_addarray(j, ZBX_PROTO_TAG_DATA);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " from %s%s", table->table, alias_from);

	if (NULL == (result = zbx_db_select("%s", sql)))
	{
		*error = zbx_dsprintf(*error, "failed to get data from table \"config\"");
		goto out;
	}

	zbx_dc_get_proxy_timeouts(proxy->proxyid, &timeouts);

	if (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_json_addarray(j, NULL);

		zbx_json_addstring(j, NULL, row[fld++], ZBX_JSON_TYPE_INT);

		for (int i = 0; 0 != table->fields[i].name; i++)
		{
			if (0 == (table->fields[i].flags & ZBX_PROXY))
				continue;

			if (0 == strncmp(table->fields[i].name, "timeout_", ZBX_CONST_STRLEN("timeout_")))
			{
				char		*timeout_value;
				const char	*item_type;

				item_type = table->fields[i].name + ZBX_CONST_STRLEN("timeout_");

				if (0 == strcmp(item_type, "zabbix_agent"))
				{
					timeout_value = timeouts.agent;
				}
				else if (0 == strcmp(item_type, "simple_check"))
				{
					timeout_value = timeouts.simple;
				}
				else if (0 == strcmp(item_type, "snmp_agent"))
				{
					timeout_value = timeouts.snmp;
				}
				else if (0 == strcmp(item_type, "external_check"))
				{
					timeout_value = timeouts.external;
				}
				else if (0 == strcmp(item_type, "db_monitor"))
				{
					timeout_value = timeouts.odbc;
				}
				else if (0 == strcmp(item_type, "ssh_agent"))
				{
					timeout_value = timeouts.ssh;
				}
				else if (0 == strcmp(item_type, "http_agent"))
				{
					timeout_value = timeouts.http;
				}
				else if (0 == strcmp(item_type, "telnet_agent"))
				{
					timeout_value = timeouts.telnet;
				}
				else if (0 == strcmp(item_type, "script"))
				{
					timeout_value = timeouts.script;
				}
				else if (0 == strcmp(item_type, "browser"))
				{
					timeout_value = timeouts.browser;
				}
				else
				{
					*error = zbx_dsprintf(*error, "unknown item type timeout field \"%s\"",
							table->fields[i].name);

					goto out;
				}

				zbx_json_addstring(j, NULL, timeout_value, ZBX_JSON_TYPE_STRING);

				continue;
			}

			switch (table->fields[i].type)
			{
				case ZBX_TYPE_INT:
				case ZBX_TYPE_UINT:
				case ZBX_TYPE_ID:
					if (SUCCEED != zbx_db_is_null(row[fld]))
						zbx_json_addstring(j, NULL, row[fld], ZBX_JSON_TYPE_INT);
					else
						zbx_json_addstring(j, NULL, NULL, ZBX_JSON_TYPE_NULL);
					break;
				default:
					zbx_json_addstring(j, NULL, row[fld], ZBX_JSON_TYPE_STRING);
					break;
			}

			fld++;
		}

		zbx_json_close(j);
	}

	zbx_json_close(j);
	zbx_json_close(j);

	ret = SUCCEED;
out:
	zbx_free(sql);
	zbx_db_free_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets table data from database                                     *
 *                                                                            *
 * Parameters: table_name  - [IN]                                             *
 *             key_name    - [IN] key field name used to select rows          *
 *                                (all rows selected when NULL)               *
 *             key_ids     - [IN] key values used to select rows (optional)   *
 *             condition   - [IN] custom condition to apply when selecting    *
 *                                rows (optional)                             *
 *             join        - [IN] custom join to apply when selecting rows    *
 *                                (optional)                                  *
 *             ids_filter  - [IN] key values used to filter rows              *
 *                               (optional)                                   *
 *             filter_name - [IN] filter field name used to filter rows       *
 *                                (optional)                                  *
 *             recids      - [OUT] selected record identifiers, sorted        *
 *             j           - [OUT] output json                                *
 *             error       - [OUT] error message                              *
 *                                                                            *
 * Return value: SUCCEED - data was read successfully                         *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	proxyconfig_get_table_data_ext(const char *table_name, const char *key_name,
		const zbx_vector_uint64_t *key_ids, const char *condition, const char *join,
		const zbx_hashset_t *ids_filter, const char *filter_name, zbx_vector_uint64_t *recids,
		struct zbx_json *j, char **error)
{
	const zbx_db_table_t	*table;
	char			*sql = NULL;
	size_t			sql_alloc =  4 * ZBX_KIBIBYTE, sql_offset = 0;
	int			ret = FAIL, fld_ids_filter = -1;
	const char		*alias = "t.", *alias_from = " t";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	table = zbx_db_get_table(table_name);
	zbx_json_addobject(j, table->table);

	if (NULL != ids_filter)
	{
		for (int i = 0; 0 != table->fields[i].name; i++)
		{
			if (0 == strcmp(filter_name, table->fields[i].name))
			{
				fld_ids_filter = i;
				break;
			}
		}

		if (-1 == fld_ids_filter)
			THIS_SHOULD_NEVER_HAPPEN;
	}

	sql = (char *)zbx_malloc(NULL, sql_alloc);
	proxyconfig_get_fields(&sql, &sql_alloc, &sql_offset, table, alias, j);

	zbx_json_addarray(j, ZBX_PROTO_TAG_DATA);

	if ((NULL == key_ids || 0 != key_ids->values_num) && (NULL == ids_filter || 0 != ids_filter->num_data))
	{
		zbx_db_result_t	result;
		zbx_db_row_t	row;

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " from %s%s", table->table, alias_from);

		if (NULL != join)
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, join);

		if (NULL != key_ids || NULL != condition)
		{
			const char	*keyword = " where";

			if (NULL != key_ids)
			{
				char	*key_name_aliased = zbx_dsprintf(NULL, "%s%s", alias, key_name);

				zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, keyword);
				zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, key_name_aliased,
						key_ids->values, key_ids->values_num);
				keyword = " and";

				zbx_free(key_name_aliased);
			}

			if (NULL != condition)
			{
				zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, keyword);
				zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, condition);
			}
		}

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " order by ");
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, alias);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, table->recid);

		if (NULL == (result = zbx_db_select("%s", sql)))
		{
			*error = zbx_dsprintf(*error, "failed to get data from table \"%s\"", table->table);
			goto out;
		}

		while (NULL != (row = zbx_db_fetch(result)))
		{
			if (-1 != fld_ids_filter)
			{
				zbx_uint64_t	recid;

				ZBX_STR2UINT64(recid, row[fld_ids_filter]);

				if (NULL == zbx_hashset_search(ids_filter, &recid))
					continue;
			}

			zbx_json_addarray(j, NULL);
			proxyconfig_add_row(row, table, recids, j);
			zbx_json_close(j);
		}
		zbx_db_free_result(result);
	}

	zbx_json_close(j);
	zbx_json_close(j);

	if (NULL != recids)
		zbx_vector_uint64_sort(recids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	ret = SUCCEED;
out:
	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

static int	proxyconfig_get_table_data(const char *table_name, const char *key_name,
		const zbx_vector_uint64_t *key_ids, const char *condition, zbx_vector_uint64_t *recids,
		struct zbx_json *j, char **error)
{
	return proxyconfig_get_table_data_ext(table_name, key_name, key_ids, condition, NULL, NULL, NULL, recids, j,
			error);
}

typedef struct
{
	zbx_uint64_t	itemid;
	zbx_uint64_t	master_itemid;
	zbx_db_row_t	row;
	int		cols_num;
}
zbx_proxyconfig_dep_item_t;

ZBX_PTR_VECTOR_DECL(proxyconfig_dep_item_ptr, zbx_proxyconfig_dep_item_t *)
ZBX_PTR_VECTOR_IMPL(proxyconfig_dep_item_ptr, zbx_proxyconfig_dep_item_t *)

static void	proxyconfig_dep_item_free(zbx_proxyconfig_dep_item_t *item)
{
	for (int i = 0; i < item->cols_num; i++)
		zbx_free(item->row[i]);

	zbx_free(item->row);
	zbx_free(item);
}

static zbx_proxyconfig_dep_item_t	*proxyconfig_dep_item_create(zbx_uint64_t itemid, zbx_uint64_t master_itemid,
		const zbx_db_row_t row, int cols_num)
{
	zbx_proxyconfig_dep_item_t	*item = (zbx_proxyconfig_dep_item_t *)
			zbx_malloc(NULL, sizeof(zbx_proxyconfig_dep_item_t));

	item->itemid = itemid;
	item->master_itemid = master_itemid;
	item->cols_num = cols_num;
	item->row = (zbx_db_row_t)zbx_malloc(NULL, sizeof(char *) * (size_t)cols_num);

	for (int i = 0; i < cols_num; i++)
	{
		if (NULL == row[i])
			item->row[i] = NULL;
		else
			item->row[i] = zbx_strdup(NULL, row[i]);
	}

	return item;
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets item data from items table                                   *
 *                                                                            *
 * Parameters: hostids - [IN] target host identifiers                         *
 *             items   - [IN] selected item identifiers                       *
 *             j       - [OUT] output json                                    *
 *             error   - [OUT] error message                                  *
 *                                                                            *
 * Return value: SUCCEED - data was read successfully                         *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	proxyconfig_get_item_data(const zbx_vector_uint64_t *hostids, zbx_hashset_t *items, struct zbx_json *j,
		char **error)
{
	const zbx_db_table_t	*table;
	char			*sql;
	size_t			sql_alloc = 4 * ZBX_KIBIBYTE, sql_offset = 0;
	int			fld = 1, ret = FAIL, fld_key = -1, fld_type = -1, fld_master_itemid = -1;

	zbx_vector_proxyconfig_dep_item_ptr_t	dep_items;
	zbx_proxyconfig_dep_item_t		*dep_item;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_proxyconfig_dep_item_ptr_create(&dep_items);

	table = zbx_db_get_table("items");

	/* get type, key_ field indexes used to check if item is processed by server */
	for (int i = 0; 0 != table->fields[i].name; i++)
	{
		if (0 == (table->fields[i].flags & ZBX_PROXY))
			continue;

		if (0 == strcmp(table->fields[i].name, "type"))
			fld_type = fld;
		else if (0 == strcmp(table->fields[i].name, "key_"))
			fld_key = fld;
		else if (0 == strcmp(table->fields[i].name, "master_itemid"))
			fld_master_itemid = fld;
		fld++;
	}

	if (-1 == fld_type || -1 == fld_key || -1 == fld_master_itemid)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}

	zbx_json_addobject(j, table->table);

	sql = (char *)zbx_malloc(NULL, sql_alloc);
	proxyconfig_get_fields(&sql, &sql_alloc, &sql_offset, table, "", j);

	zbx_json_addarray(j, ZBX_PROTO_TAG_DATA);

	if (0 != hostids->values_num)
	{
		zbx_db_result_t	result;
		zbx_db_row_t	row;

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " from %s where", table->table);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "hostid", hostids->values,
				hostids->values_num);
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and flags<>%d and type<>%d",
				ZBX_FLAG_DISCOVERY_PROTOTYPE, ITEM_TYPE_CALCULATED);

		if (NULL == (result = zbx_db_select("%s", sql)))
		{
			*error = zbx_dsprintf(*error, "failed to get data from table \"%s\"", table->table);
			goto out;
		}

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " from %s", table->table);

		while (NULL != (row = zbx_db_fetch(result)))
		{
			unsigned char	type;
			zbx_uint64_t	itemid, master_itemid;

			ZBX_STR2UCHAR(type, row[fld_type]);
			if (SUCCEED == zbx_is_item_processed_by_server(type, row[fld_key]))
					continue;

			ZBX_DBROW2UINT64(itemid, row[0]);

			if (ITEM_TYPE_DEPENDENT != atoi(row[fld_type]))
			{
				zbx_json_addarray(j, NULL);
				proxyconfig_add_row(row, table, NULL, j);
				zbx_json_close(j);

				zbx_hashset_insert(items, &itemid, sizeof(itemid));
			}
			else
			{
				ZBX_DBROW2UINT64(master_itemid, row[fld_master_itemid]);
				dep_item = proxyconfig_dep_item_create(itemid, master_itemid, row, fld);
				zbx_vector_proxyconfig_dep_item_ptr_append(&dep_items, dep_item);
			}
		}
		zbx_db_free_result(result);

		/* add dependent items processed by proxy */
		if (0 != dep_items.values_num)
		{
			int	dep_items_num;

			do
			{
				dep_items_num = dep_items.values_num;

				for (int i = 0; i < dep_items.values_num; )
				{
					dep_item = dep_items.values[i];

					if (NULL != zbx_hashset_search(items, &dep_item->master_itemid))
					{
						zbx_json_addarray(j, NULL);
						proxyconfig_add_row(dep_item->row, table, NULL, j);
						zbx_json_close(j);

						zbx_hashset_insert(items, &dep_item->itemid, sizeof(zbx_uint64_t));
						proxyconfig_dep_item_free(dep_item);
						zbx_vector_proxyconfig_dep_item_ptr_remove_noorder(&dep_items, i);
					}
					else
						i++;
				}
			}
			while (dep_items_num != dep_items.values_num);
		}
	}

	zbx_json_close(j);
	zbx_json_close(j);

	ret = SUCCEED;
out:
	zbx_free(sql);

	zbx_vector_proxyconfig_dep_item_ptr_clear_ext(&dep_items, proxyconfig_dep_item_free);
	zbx_vector_proxyconfig_dep_item_ptr_destroy(&dep_items);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets host and related table data from database                    *
 *                                                                            *
 * Parameters: hostids    - [IN] target host identifiers                      *
 *             j          - [OUT] output json                                 *
 *             error      - [OUT] error message                               *
 *                                                                            *
 * Return value: SUCCEED - data was read successfully                         *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	proxyconfig_get_host_data(const zbx_vector_uint64_t *hostids, struct zbx_json *j, char **error)
{
	zbx_vector_uint64_t	interfaceids;
	int			ret = FAIL;
	char			*sql = NULL;
	size_t			sql_alloc =  0, sql_offset = 0;
	zbx_hashset_t		items;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&interfaceids);
	zbx_hashset_create(&items, 1000, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	if (SUCCEED != proxyconfig_get_table_data("hosts", "hostid", hostids, NULL, NULL, j, error))
		goto out;

	if (SUCCEED != proxyconfig_get_table_data("interface",  "hostid", hostids, NULL, &interfaceids, j, error))
		goto out;

	if (SUCCEED != proxyconfig_get_table_data("interface_snmp",  "interfaceid", &interfaceids, NULL, NULL,
			j, error))
	{
		goto out;
	}

	if (SUCCEED != proxyconfig_get_table_data("host_inventory", "hostid", hostids, NULL, NULL, j, error))
		goto out;

	if (SUCCEED != proxyconfig_get_item_data(hostids, &items, j, error))
		goto out;

	if (0 != items.num_data)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " join items i on i.itemid=t.itemid and");
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "i.hostid", hostids->values,
				hostids->values_num);
	}

	if (SUCCEED != proxyconfig_get_table_data_ext("item_rtdata", NULL, NULL, NULL, sql, &items, "itemid", NULL, j,
			error))
	{
		goto out;
	}

	if (SUCCEED != proxyconfig_get_table_data_ext("item_preproc", NULL, NULL, NULL, sql, &items, "itemid", NULL, j,
			error))
	{
		goto out;
	}

	if (SUCCEED != proxyconfig_get_table_data_ext("item_parameter", NULL, NULL, NULL, sql, &items, "itemid", NULL,
			j, error))
	{
		goto out;
	}

	ret = SUCCEED;
out:
	zbx_vector_uint64_destroy(&interfaceids);
	zbx_hashset_destroy(&items);
	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets discovery rule and checks data from database                 *
 *                                                                            *
 * Parameters: proxy - [IN] target proxy                                      *
 *             j     - [OUT] output json                                      *
 *             error - [OUT] error message                                    *
 *                                                                            *
 * Return value: SUCCEED - data was read successfully                         *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	proxyconfig_get_drules_data(const zbx_dc_proxy_t *proxy, struct zbx_json *j, char **error)
{
	zbx_vector_uint64_t	druleids;
	zbx_vector_uint64_t	proxyids;
	int			ret = FAIL;
	char			*filter = NULL;
	size_t			filter_alloc = 0, filter_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&druleids);
	zbx_vector_uint64_create(&proxyids);

	zbx_vector_uint64_append(&proxyids, proxy->proxyid);

	zbx_snprintf_alloc(&filter, &filter_alloc, &filter_offset, " status=%d", DRULE_STATUS_MONITORED);

	if (SUCCEED != proxyconfig_get_table_data("drules", "proxyid", &proxyids, filter, &druleids, j,
			error))
	{
		goto out;
	}

	if (SUCCEED != proxyconfig_get_table_data("dchecks", "druleid", &druleids, NULL, NULL, j, error))
		goto out;

	ret = SUCCEED;
out:
	zbx_free(filter);
	zbx_vector_uint64_destroy(&proxyids);
	zbx_vector_uint64_destroy(&druleids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets global regular expression (regexps/expressions) data from    *
 *          database                                                          *
 *                                                                            *
 * Parameters: j     - [OUT] output json                                      *
 *             error - [OUT] error message                                    *
 *                                                                            *
 * Return value: SUCCEED - data was read successfully                         *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	proxyconfig_get_expression_data(struct zbx_json *j, char **error)
{
	zbx_vector_uint64_t	regexpids;
	int			ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&regexpids);

	if (SUCCEED != proxyconfig_get_table_data("regexps", NULL, NULL, NULL, &regexpids, j, error))
		goto out;

	if (SUCCEED != proxyconfig_get_table_data("expressions", "regexpid", &regexpids, NULL, NULL, j, error))
		goto out;

	ret = SUCCEED;
out:
	zbx_vector_uint64_destroy(&regexpids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: gets httptest and related data from database                      *
 *                                                                            *
 * Parameters: httptestids - [IN] httptest identifiers                        *
 *             j           - [OUT] output json                                *
 *             error       - [OUT] error message                              *
 *                                                                            *
 * Return value: SUCCEED - data was read successfully                         *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	proxyconfig_get_httptest_data(const zbx_vector_uint64_t *httptestids, struct zbx_json *j, char **error)
{
	zbx_vector_uint64_t	httpstepids;
	int			ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&httpstepids);

	if (SUCCEED != proxyconfig_get_table_data("httptest", "httptestid", httptestids, NULL, NULL, j, error))
		goto out;

	if (SUCCEED != proxyconfig_get_table_data("httptestitem", "httptestid", httptestids, NULL, NULL, j, error))
		goto out;

	if (SUCCEED != proxyconfig_get_table_data("httptest_field", "httptestid", httptestids, NULL, NULL, j, error))
		goto out;

	if (SUCCEED != proxyconfig_get_table_data("httpstep", "httptestid", httptestids, NULL, &httpstepids, j, error))
		goto out;

	if (SUCCEED != proxyconfig_get_table_data("httpstepitem", "httpstepid", &httpstepids, NULL, NULL, j, error))
		goto out;

	if (SUCCEED != proxyconfig_get_table_data("httpstep_field", "httpstepid", &httpstepids, NULL, NULL, j, error))
		goto out;

	ret = SUCCEED;
out:
	zbx_vector_uint64_destroy(&httpstepids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get proxy group updates from proxy group service to sync with     *
 *          a proxy                                                           *
 *                                                                            *
 * Parameters: proxy            - [IN] syncing proxy                          *
 *             proxy_hostmap_revision - [IN/OUT] proxy host mapping revision  *
 *             sync_mode        - [OUT] sync mode, see ZBX_PG_PROXY_SYNC_     *
 *                                      defines                               *
 *             failover_delay   - [OUT] proxy group failover delay            *
 *                                      (must be freed by caller)             *
 *             del_hostproxyids - [OUT] identifiers of deleted host_proxy     *
 *                                      records                               *
 *                                                                            *
 * Return value: SUCCEED - the data was read successfully                     *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static void	proxyconfig_get_proxy_group_updates(const zbx_dc_proxy_t *proxy, zbx_uint64_t *proxy_hostmap_revision,
		unsigned char *sync_mode, char **failover_delay, zbx_vector_uint64_t *del_hostproxyids)
{
	static zbx_ipc_socket_t	pg_socket = {.fd = -1};

	if (-1 == pg_socket.fd)
	{
		char	*error = NULL;

		if (FAIL == zbx_ipc_socket_open(&pg_socket, ZBX_IPC_SERVICE_PGSERVICE, ZBX_PG_SERVICE_TIMEOUT, &error))
		{
			zabbix_log(LOG_LEVEL_WARNING, "Cannot connect to proxy group manager service: %s", error);
			*sync_mode = ZBX_PROXY_SYNC_FULL;
			return;
		}
	}

	unsigned char	data[sizeof(zbx_uint64_t) * 2], *ptr = data;

	ptr += zbx_serialize_value(ptr, proxy->proxyid);
	(void)zbx_serialize_value(ptr, *proxy_hostmap_revision);

	if (FAIL == zbx_ipc_socket_write(&pg_socket, ZBX_IPC_PGM_GET_PROXY_SYNC_DATA, data, sizeof(data)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Cannot send request to proxy group manager service");
		*sync_mode = ZBX_PROXY_SYNC_FULL;
		return;
	}

	zbx_ipc_message_t	message;
	zbx_uint32_t		failover_delay_len;

	if (FAIL == zbx_ipc_socket_read(&pg_socket, &message))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Cannot send request to proxy group manager service");
		*sync_mode = ZBX_PROXY_SYNC_FULL;
		return;
	}

	ptr = message.data;

	ptr += zbx_deserialize_value(ptr, sync_mode);
	ptr += zbx_deserialize_value(ptr, proxy_hostmap_revision);
	ptr += zbx_deserialize_str(ptr, failover_delay, failover_delay_len);

	if (ZBX_PROXY_SYNC_PARTIAL == *sync_mode)
	{
		int	hostproxyids_num;

		ptr += zbx_deserialize_value(ptr, &hostproxyids_num);

		if (0 < hostproxyids_num)
		{
			zbx_uint64_t	hostproxyid;

			zbx_vector_uint64_reserve(del_hostproxyids, (size_t)hostproxyids_num);

			for (int i = 0; i < hostproxyids_num; i++)
			{
				ptr += zbx_deserialize_value(ptr, &hostproxyid);
				zbx_vector_uint64_append(del_hostproxyids, hostproxyid);
			}
		}
	}

	zbx_ipc_message_clean(&message);
}

/******************************************************************************
 *                                                                            *
 * Purpose: fetch host_proxy records to sync with proxy                       *
 *                                                                            *
 * Parameters: proxy            - [IN] syncing proxy                          *
 *             revision         - [IN] host mapping revision                  *
 *             j                - [OUT] output json                           *
 *             error            - [OUT] error message                         *
 *                                                                            *
 ******************************************************************************/
static int	proxyconfig_get_hostmap(const zbx_dc_proxy_t *proxy, zbx_uint64_t revision, struct zbx_json *j,
		char **error)
{
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	const zbx_db_table_t	*table;
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	int			ret = FAIL;

	table = zbx_db_get_table("host_proxy");
	zbx_json_addobject(j, table->table);

	proxyconfig_get_fields(&sql, &sql_alloc, &sql_offset, table, "", j);
	sql_offset = 0;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select hp.hostproxyid,h.host,hp.proxyid,hp.revision,h.tls_accept,h.tls_issuer,h.tls_subject,"
				"h.tls_psk_identity,h.tls_psk"
			" from proxy p,host_proxy hp"
			" join hosts h on"
				" h.hostid=hp.hostid"
			" where hp.proxyid=p.proxyid"
				" and h.proxy_groupid=" ZBX_FS_UI64
				" and p.proxy_groupid=" ZBX_FS_UI64,
				proxy->proxy_groupid, proxy->proxy_groupid);

	if (0 < revision)
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " and revision>" ZBX_FS_UI64, revision);

	if (NULL == (result = zbx_db_select("%s", sql)))
	{
		*error = zbx_dsprintf(*error, "failed to get data from table \"%s\"", table->table);
		goto out;
	}

	zbx_json_addarray(j, ZBX_PROTO_TAG_DATA);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_json_addarray(j, NULL);
		proxyconfig_add_row(row, table, NULL, j);
		zbx_json_close(j);
	}
	zbx_db_free_result(result);

	zbx_json_close(j);
	zbx_json_close(j);

	ret = SUCCEED;
out:
	zbx_free(sql);

	return ret;
}

static int	proxyconfig_get_tables(zbx_dc_proxy_t *proxy, zbx_uint64_t proxy_config_revision,
		const zbx_dc_revision_t *dc_revision, unsigned char hostmap_sync, zbx_uint64_t proxy_hostmap_revision,
		zbx_uint64_t hostmap_revision, const char *failover_delay, const zbx_vector_uint64_t *del_hostproxyids,
		const zbx_config_vault_t *config_vault, const char *config_source_ip,
		const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, struct zbx_json *j, zbx_proxyconfig_status_t *status, char **error)
{
#define ZBX_PROXYCONFIG_SYNC_HOSTS		0x0001
#define ZBX_PROXYCONFIG_SYNC_GMACROS		0x0002
#define ZBX_PROXYCONFIG_SYNC_HMACROS		0x0004
#define ZBX_PROXYCONFIG_SYNC_DRULES		0x0008
#define ZBX_PROXYCONFIG_SYNC_EXPRESSIONS	0x0010
#define ZBX_PROXYCONFIG_SYNC_CONFIG		0x0020
#define ZBX_PROXYCONFIG_SYNC_HTTPTESTS		0x0040
#define ZBX_PROXYCONFIG_SYNC_AUTOREG		0x0080
#define ZBX_PROXYCONFIG_SYNC_PROXY_LIST		0x0100
#define ZBX_PROXYCONFIG_SYNC_HOSTMAP		0x0200

#define ZBX_PROXYCONFIG_SYNC_ALL	(ZBX_PROXYCONFIG_SYNC_HOSTS | ZBX_PROXYCONFIG_SYNC_GMACROS | 		\
					ZBX_PROXYCONFIG_SYNC_HMACROS |ZBX_PROXYCONFIG_SYNC_DRULES |		\
					ZBX_PROXYCONFIG_SYNC_EXPRESSIONS | ZBX_PROXYCONFIG_SYNC_CONFIG | 	\
					ZBX_PROXYCONFIG_SYNC_HTTPTESTS | ZBX_PROXYCONFIG_SYNC_AUTOREG |		\
					ZBX_PROXYCONFIG_SYNC_PROXY_LIST | ZBX_PROXYCONFIG_SYNC_HOSTMAP)

	zbx_vector_uint64_t		hostids, httptestids, updated_hostids, removed_hostids, del_macro_hostids,
					macro_hostids;
	zbx_vector_keys_path_ptr_t	keys_paths;
	int				global_macros = FAIL, ret = FAIL;
	zbx_uint64_t			flags = 0, proxy_group_revision = 0;

	zbx_vector_uint64_create(&hostids);
	zbx_vector_uint64_create(&updated_hostids);
	zbx_vector_uint64_create(&removed_hostids);
	zbx_vector_uint64_create(&httptestids);
	zbx_vector_uint64_create(&macro_hostids);
	zbx_vector_uint64_create(&del_macro_hostids);
	zbx_vector_keys_path_ptr_create(&keys_paths);

	if (proxy_config_revision < proxy->revision || proxy_config_revision < proxy->macro_revision)
	{
		zbx_vector_uint64_reserve(&hostids, 1000);
		zbx_vector_uint64_reserve(&updated_hostids, 1000);
		zbx_vector_uint64_reserve(&removed_hostids, 100);
		zbx_vector_uint64_reserve(&httptestids, 100);
		zbx_vector_uint64_reserve(&macro_hostids, 1000);
		zbx_vector_uint64_reserve(&del_macro_hostids, 100);

		zbx_dc_get_proxy_config_updates(proxy->proxyid, proxy_config_revision, &hostids, &updated_hostids,
				&removed_hostids, &httptestids, &proxy_group_revision);

		zbx_dc_get_macro_updates(&hostids, &updated_hostids, proxy_config_revision, &macro_hostids,
				&global_macros, &del_macro_hostids);
	}
	else if (0 != proxy->proxy_groupid)
		proxy_group_revision = zbx_dc_get_proxy_group_revision(proxy->proxy_groupid);

	if (0 == proxy_group_revision)
		proxy->proxy_groupid = 0;

	if (0 != proxy_config_revision)
	{
		if (0 != updated_hostids.values_num)
			flags |= ZBX_PROXYCONFIG_SYNC_HOSTS;

		if (SUCCEED == global_macros)
			flags |= ZBX_PROXYCONFIG_SYNC_GMACROS;

		if(0 != macro_hostids.values_num)
			flags |= ZBX_PROXYCONFIG_SYNC_HMACROS;

		/* config sync is required because of possible proxy timeout changes overriding global timeouts */
		if (proxy_config_revision < proxy->revision)
			flags |= ZBX_PROXYCONFIG_SYNC_DRULES | ZBX_PROXYCONFIG_SYNC_CONFIG;

		if (proxy_config_revision < dc_revision->expression)
			flags |= ZBX_PROXYCONFIG_SYNC_EXPRESSIONS;

		if (proxy_config_revision < dc_revision->config_table)
			flags |= ZBX_PROXYCONFIG_SYNC_CONFIG;

		if (0 != httptestids.values_num)
			flags |= ZBX_PROXYCONFIG_SYNC_HTTPTESTS;

		if (proxy_config_revision < dc_revision->autoreg_tls)
			flags |= ZBX_PROXYCONFIG_SYNC_AUTOREG;

		if (0 != proxy->proxy_groupid)
		{
			if (proxy_config_revision < proxy_group_revision)
				flags |= ZBX_PROXYCONFIG_SYNC_PROXY_LIST;
		}
	}
	else
		flags = ZBX_PROXYCONFIG_SYNC_ALL;

	if (ZBX_PROXY_SYNC_NONE != hostmap_sync && 0 != proxy->proxy_groupid)
		flags |= ZBX_PROXYCONFIG_SYNC_PROXY_LIST | ZBX_PROXYCONFIG_SYNC_HOSTMAP;

	zbx_json_addobject(j, ZBX_PROTO_TAG_DATA);

	if (0 != flags)
	{
		zbx_db_begin();

		if (0 != (flags & ZBX_PROXYCONFIG_SYNC_HOSTS) &&
				SUCCEED != proxyconfig_get_host_data(&updated_hostids, j, error))
		{
			goto out;
		}

		if (0 != (flags & ZBX_PROXYCONFIG_SYNC_GMACROS) && SUCCEED !=
				proxyconfig_get_macro_updates("globalmacro", NULL, config_vault->db_path, &keys_paths,
				j, error))
		{
			goto out;
		}

		if (0 != (flags & ZBX_PROXYCONFIG_SYNC_HMACROS))
		{
			if (SUCCEED != proxyconfig_get_table_data("hosts_templates", "hostid", &macro_hostids, NULL,
					NULL, j, error))
			{
				goto out;
			}

			if (SUCCEED != proxyconfig_get_macro_updates("hostmacro", &macro_hostids, config_vault->db_path,
					&keys_paths, j, error))
			{
				goto out;
			}
		}

		if (0 != (flags & ZBX_PROXYCONFIG_SYNC_DRULES) &&
				SUCCEED != proxyconfig_get_drules_data(proxy, j, error))
		{
			goto out;
		}

		if (0 != (flags & ZBX_PROXYCONFIG_SYNC_EXPRESSIONS) &&
				SUCCEED != proxyconfig_get_expression_data(j, error))
		{
			goto out;
		}

		if (0 != (flags & ZBX_PROXYCONFIG_SYNC_CONFIG) &&
				SUCCEED != proxyconfig_get_config_table_data(proxy, j, error))
		{
			goto out;
		}

		if (0 != (flags & ZBX_PROXYCONFIG_SYNC_HTTPTESTS) &&
				SUCCEED != proxyconfig_get_httptest_data(&httptestids, j, error))
		{
			goto out;
		}

		if (0 != (flags & ZBX_PROXYCONFIG_SYNC_AUTOREG) &&
				SUCCEED != proxyconfig_get_table_data("config_autoreg_tls", NULL, NULL, NULL, NULL, j,
						error))
		{
			goto out;
		}

		if (0 != (flags & ZBX_PROXYCONFIG_SYNC_PROXY_LIST))
		{
			zbx_vector_uint64_t	proxy_groupids;

			zbx_vector_uint64_create(&proxy_groupids);
			zbx_vector_uint64_append(&proxy_groupids, proxy->proxy_groupid);

			if (SUCCEED != proxyconfig_get_table_data("proxy", "proxy_groupid", &proxy_groupids,
					NULL, NULL, j, error))
			{
				zbx_vector_uint64_destroy(&proxy_groupids);
				goto out;
			}

			zbx_vector_uint64_destroy(&proxy_groupids);
		}

		if (0 != (flags & ZBX_PROXYCONFIG_SYNC_HOSTMAP))
		{
			zbx_uint64_t	rev;

			rev = (ZBX_PROXY_SYNC_FULL == hostmap_sync ? 0 : proxy_hostmap_revision);

			if (FAIL == proxyconfig_get_hostmap(proxy, rev, j, error))
				goto out;
		}
	}

	zbx_json_close(j);

	if (0 != proxy->proxy_groupid)
	{
		zbx_json_addobject(j, ZBX_PROTO_TAG_PROXY_GROUP);

		if (ZBX_PROXY_SYNC_FULL == hostmap_sync)
			zbx_json_addint64(j, ZBX_PROTO_TAG_FULL_SYNC, 1);

		zbx_json_adduint64(j, ZBX_PROTO_TAG_HOSTMAP_REVISION, hostmap_revision);
		zbx_json_addstring(j, ZBX_PROTO_TAG_FAILOVER_DELAY, failover_delay, ZBX_JSON_TYPE_STRING);

		if (0 != del_hostproxyids->values_num)
		{
			zbx_json_addarray(j, ZBX_PROTO_TAG_DEL_HOSTPROXYIDS);

			for (int i = 0; i < del_hostproxyids->values_num; i++)
				zbx_json_adduint64(j, NULL, del_hostproxyids->values[i]);

			zbx_json_close(j);
		}

		zbx_json_close(j);
	}

	if (0 != removed_hostids.values_num)
	{
		zbx_json_addarray(j, ZBX_PROTO_TAG_REMOVED_HOSTIDS);

		for (int i = 0; i < removed_hostids.values_num; i++)
			zbx_json_adduint64(j, NULL, removed_hostids.values[i]);

		zbx_json_close(j);
	}

	if (0 != del_macro_hostids.values_num)
	{
		zbx_json_addarray(j, ZBX_PROTO_TAG_REMOVED_MACRO_HOSTIDS);

		for (int i = 0; i < del_macro_hostids.values_num; i++)
			zbx_json_adduint64(j, NULL, del_macro_hostids.values[i]);

		zbx_json_close(j);
	}

	if (0 != keys_paths.values_num)
	{
		get_macro_secrets(&keys_paths, j, config_vault, config_source_ip, config_ssl_ca_location,
				config_ssl_cert_location, config_ssl_key_location);
	}

	if (0 == flags && 0 == removed_hostids.values_num && 0 == del_macro_hostids.values_num)
		*status = ZBX_PROXYCONFIG_STATUS_EMPTY;
	else
		*status = ZBX_PROXYCONFIG_STATUS_DATA;

	ret = SUCCEED;
out:
	if (0 != flags)
		zbx_db_commit();

	zbx_vector_keys_path_ptr_clear_ext(&keys_paths, key_path_free);
	zbx_vector_keys_path_ptr_destroy(&keys_paths);
	zbx_vector_uint64_destroy(&httptestids);
	zbx_vector_uint64_destroy(&macro_hostids);
	zbx_vector_uint64_destroy(&del_macro_hostids);
	zbx_vector_uint64_destroy(&removed_hostids);
	zbx_vector_uint64_destroy(&updated_hostids);
	zbx_vector_uint64_destroy(&hostids);

	return ret;

#undef ZBX_PROXYCONFIG_SYNC_HOSTS
#undef ZBX_PROXYCONFIG_SYNC_GMACROS
#undef ZBX_PROXYCONFIG_SYNC_HMACROS
#undef ZBX_PROXYCONFIG_SYNC_DRULES
#undef ZBX_PROXYCONFIG_SYNC_EXPRESSIONS
#undef ZBX_PROXYCONFIG_SYNC_CONFIG
#undef ZBX_PROXYCONFIG_SYNC_HTTPTESTS
#undef ZBX_PROXYCONFIG_SYNC_AUTOREG
#undef ZBX_PROXYCONFIG_SYNC_ALL
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepares proxy configuration data                                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_proxyconfig_get_data(zbx_dc_proxy_t *proxy, const struct zbx_json_parse *jp_request, struct zbx_json *j,
		zbx_proxyconfig_status_t *status, const zbx_config_vault_t *config_vault,
		const char *config_source_ip, const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, char **error)
{
	int			ret = FAIL;
	char			token[ZBX_SESSION_TOKEN_SIZE + 1], tmp[ZBX_MAX_UINT64_LEN + 1], *failover_delay = NULL;
	zbx_uint64_t		proxy_config_revision, proxy_hostmap_revision, hostmap_revision;
	zbx_dc_revision_t	dc_revision;
	zbx_vector_uint64_t	del_hostproxyids;
	unsigned char		hostmap_sync;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() proxyid:" ZBX_FS_UI64, __func__, proxy->proxyid);

	zbx_vector_uint64_create(&del_hostproxyids);

	if (SUCCEED != zbx_json_value_by_name(jp_request, ZBX_PROTO_TAG_SESSION, token, sizeof(token), NULL))
	{
		*error = zbx_strdup(NULL, "cannot get session from proxy configuration request");
		goto out;
	}

	if (SUCCEED != zbx_json_value_by_name(jp_request, ZBX_PROTO_TAG_CONFIG_REVISION, tmp, sizeof(tmp), NULL))
	{
		*error = zbx_strdup(NULL, "cannot get revision from proxy configuration request");
		goto out;
	}

	if (SUCCEED != zbx_is_uint64(tmp, &proxy_config_revision))
	{
		*error = zbx_dsprintf(NULL, "invalid proxy configuration revision: %s", tmp);
		goto out;
	}

	if (SUCCEED == zbx_json_value_by_name(jp_request, ZBX_PROTO_TAG_HOSTMAP_REVISION, tmp, sizeof(tmp), NULL))
	{
		if (SUCCEED != zbx_is_uint64(tmp, &proxy_hostmap_revision))
		{
			*error = zbx_dsprintf(NULL, "invalid proxy host-proxy map revision: %s", tmp);
			goto out;
		}
	}
	else
		proxy_hostmap_revision = 0;

	if (0 != zbx_dc_register_config_session(proxy->proxyid, token, proxy_config_revision, &dc_revision) ||
			0 == proxy_config_revision)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() forcing full proxy configuration sync", __func__);
		proxy_config_revision = 0;
		proxy_hostmap_revision = 0;
		zbx_json_addint64(j, ZBX_PROTO_TAG_FULL_SYNC, 1);
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() updating proxy configuration " ZBX_FS_UI64 "->" ZBX_FS_UI64,
				__func__, proxy_config_revision, dc_revision.config);
	}

	hostmap_sync = ZBX_PROXY_SYNC_NONE;
	hostmap_revision = proxy_hostmap_revision;

	if (0 != proxy->proxy_groupid)
	{
		proxyconfig_get_proxy_group_updates(proxy, &hostmap_revision, &hostmap_sync, &failover_delay,
				&del_hostproxyids);
	}

	if (proxy_config_revision != dc_revision.config || proxy_hostmap_revision != hostmap_revision)
	{
		if (SUCCEED != (ret = proxyconfig_get_tables(proxy, proxy_config_revision, &dc_revision,
				hostmap_sync, proxy_hostmap_revision, hostmap_revision, failover_delay,
				&del_hostproxyids, config_vault, config_source_ip, config_ssl_ca_location,
				config_ssl_cert_location, config_ssl_key_location, j, status, error)))
		{
			goto out;
		}

		zbx_json_adduint64(j, ZBX_PROTO_TAG_CONFIG_REVISION, dc_revision.config);

		zabbix_log(LOG_LEVEL_TRACE, "%s() configuration: %s", __func__, j->buffer);
	}
	else
	{
		*status = ZBX_PROXYCONFIG_STATUS_EMPTY;
		ret = SUCCEED;
	}
out:
	zbx_vector_uint64_destroy(&del_hostproxyids);

	zbx_free(failover_delay);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: sends configuration tables to proxy from server                   *
 *          (for active proxies)                                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_send_proxyconfig(zbx_socket_t *sock, const struct zbx_json_parse *jp,
		const zbx_config_vault_t *config_vault, int config_timeout, int config_trapper_timeout,
		const char *config_source_ip, const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location)
{
	char				*error = NULL, *buffer = NULL, *version_str = NULL;
	struct zbx_json			j;
	zbx_dc_proxy_t			proxy;
	int				ret, flags = ZBX_TCP_PROTOCOL, loglevel, version_int;
	size_t				buffer_size, reserved = 0;
	zbx_proxyconfig_status_t	status = ZBX_PROXYCONFIG_STATUS_DATA;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (SUCCEED != zbx_get_active_proxy_from_request(jp, &proxy, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot parse proxy configuration data request from active proxy at"
				" \"%s\": %s", sock->peer, error);
		goto out;
	}

	if (SUCCEED != zbx_proxy_check_permissions(&proxy, sock, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot accept connection from proxy \"%s\" at \"%s\", allowed address:"
				" \"%s\": %s", proxy.name, sock->peer, proxy.allowed_addresses, error);
		goto out;
	}

	version_str = zbx_get_proxy_protocol_version_str(jp);
	version_int = zbx_get_proxy_protocol_version_int(version_str);

	zbx_update_proxy_data(&proxy, version_str, version_int, time(NULL), ZBX_FLAGS_PROXY_DIFF_UPDATE_CONFIG);

	flags |= ZBX_TCP_COMPRESS;

	if (ZBX_PROXY_VERSION_CURRENT != proxy.compatibility)
	{
		error = zbx_strdup(error, "proxy and server major versions do not match");
		(void)zbx_send_response_ext(sock, NOTSUPPORTED, error, ZABBIX_VERSION, flags, config_timeout);
		zabbix_log(LOG_LEVEL_WARNING, "configuration update is disabled for this version of proxy \"%s\" at"
				" \"%s\": %s", proxy.name, sock->peer, error);
		goto out;
	}

	zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

	if (SUCCEED != zbx_proxyconfig_get_data(&proxy, jp, &j, &status, config_vault, config_source_ip,
			config_ssl_ca_location, config_ssl_cert_location, config_ssl_key_location, &error))
	{
		(void)zbx_send_response_ext(sock, FAIL, error, NULL, flags, config_timeout);
		zabbix_log(LOG_LEVEL_WARNING, "cannot collect configuration data for proxy \"%s\" at \"%s\": %s",
				proxy.name, sock->peer, error);
		goto clean;
	}

	loglevel = (ZBX_PROXYCONFIG_STATUS_DATA == status ? LOG_LEVEL_WARNING : LOG_LEVEL_DEBUG);

	if (SUCCEED != zbx_compress(j.buffer, j.buffer_size, &buffer, &buffer_size))
	{
		zabbix_log(LOG_LEVEL_ERR,"cannot compress data: %s", zbx_compress_strerror());
		goto clean;
	}

	reserved = j.buffer_size;

	zbx_json_free(&j);	/* json buffer can be large, free as fast as possible */

	zabbix_log(loglevel, "sending configuration data to proxy \"%s\" at \"%s\", datalen "
			ZBX_FS_SIZE_T ", bytes " ZBX_FS_SIZE_T " with compression ratio %.1f", proxy.name,
			sock->peer, (zbx_fs_size_t)reserved, (zbx_fs_size_t)buffer_size,
			(double)reserved / (double)buffer_size);

	ret = zbx_tcp_send_ext(sock, buffer, buffer_size, reserved, (unsigned char)flags,
			config_trapper_timeout);

	if (SUCCEED != ret)
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot send configuration data to proxy \"%s\" at \"%s\": %s",
				proxy.name, sock->peer, zbx_socket_strerror());
	}
clean:
	zbx_json_free(&j);
out:
	zbx_free(error);
	zbx_free(buffer);
	zbx_free(version_str);
#ifdef	HAVE_MALLOC_TRIM
	/* avoid memory not being released back to the system if large proxy configuration is retrieved from database */
	if (ZBX_PROXYCONFIG_STATUS_DATA == status)
		malloc_trim(ZBX_MALLOC_TRIM);
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}
