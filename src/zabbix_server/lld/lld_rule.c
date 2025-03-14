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
#include "lld_audit.h"
#include "audit/zbxaudit.h"
#include "audit/zbxaudit_item.h"
#include "zbxdbhigh.h"

typedef struct
{
	zbx_uint64_t		itemid;
	zbx_sync_rowset_t	macros;
}
zbx_lld_rule_macros_t;

static void    lld_rule_macros_clear(void *d)
{
	zbx_lld_rule_macros_t   *rule_macros = (zbx_lld_rule_macros_t *)d;

	zbx_sync_rowset_clear(&rule_macros->macros);
}

/******************************************************************************
 *                                                                            *
 * Purpose: merge exported macros for LLD rule                                *
 *                                                                            *
 * Parameters: rule_macros - [IN/OUT] LLD rule macros                         *
 *             entry       - [IN] LLD entry                                   *
 *                                                                            *
 ******************************************************************************/
static void	lld_rule_merge_exported_macros(zbx_lld_rule_macros_t *rule_macros,
		const zbx_lld_entry_t *entry)
{
	zbx_sync_rowset_t	src;

	zbx_sync_rowset_init(&src, 2);

	for (int i = 0; i < entry->exported_macros->values_num; i++)
	{
		const zbx_lld_macro_t	*macro = &entry->exported_macros->values[i];

		zbx_sync_rowset_add_row(&src, NULL, macro->macro, macro->value);
	}

	zbx_sync_rowset_sort_by_rows(&src);
	zbx_sync_rowset_sort_by_rows(&rule_macros->macros);

	zbx_sync_rowset_merge(&rule_macros->macros, &src);

	for (int i = 0; i < rule_macros->macros.rows.values_num; )
	{
		zbx_sync_row_t	*row = rule_macros->macros.rows.values[i];

		if (0 == row->update_num)
		{
			zbx_sync_row_free(row);
			zbx_vector_sync_row_ptr_remove(&rule_macros->macros.rows, i);
		}
		else
			i++;
	}

	zbx_sync_rowset_sort_by_id(&rule_macros->macros);
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
		zbx_sync_rowset_init(&rule_macros_local.macros, 2);
		zbx_hashset_insert(lld_rules, &rule_macros_local, sizeof(rule_macros_local));
	}

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select lld_macroid,itemid,name,value from lld_macro where");

	zbx_db_large_query_prepare_uint(&query, &sql, &sql_alloc, &sql_offset, "itemid", ruleids);

	while (NULL != (row = zbx_db_large_query_fetch(&query)))
	{
		zbx_lld_rule_macros_t	*rule_macros, rule_macros_local;

		ZBX_STR2UINT64(rule_macros_local.itemid, row[1]);
		if (NULL == (rule_macros = (zbx_lld_rule_macros_t *)zbx_hashset_search(lld_rules, &rule_macros_local)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		zbx_sync_rowset_add_row(&rule_macros->macros, row[0], row[2], row[3]);
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
	char			*sql = NULL;
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
		for (int i = 0; i < rule_macros->macros.rows.values_num; i++)
		{
			zbx_sync_row_t	*row = rule_macros->macros.rows.values[i];

			if (0 == row->update_num)
				continue;

			if (0 == row->rowid)
			{
				zbx_db_insert_add_values(&db_insert, row->rowid, rule_macros->itemid,
						row->cols[0], row->cols[1]);
				continue;
			}

			if (-1 == row->update_num)
			{
				zbx_vector_uint64_append(&deleted_ids, row->rowid);
				continue;
			}

			const char	*fields[] = {"name", "value"};
			char		delim = ' ';

			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update lld_macro set");

			for (int j = row->cols_num - row->update_num; j < row->cols_num; j++)
			{
				char	*value_esc;

				value_esc = zbx_db_dyn_escape_string(row->cols[j]);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%c%s='%s'", delim, fields[j],
						value_esc);

				delim = ',';

				zbx_free(value_esc);
			}

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where lld_macroid=" ZBX_FS_UI64 ";\n",
					row->rowid);

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

		if (0 == rule_macros->macros.rows.values_num)
			zbx_hashset_iter_remove(&iter);
	}

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

/******************************************************************************
 *                                                                            *
 * Purpose: saves/updates/removes lld rule macro paths                        *
 *                                                                            *
 * Parameters: hostid      - [IN] parent host id                              *
 *             items       - [IN]                                             *
 *             host_locked - [IN/OUT] host record is locked                   *
 *                                                                            *
 ******************************************************************************/
int	lld_rule_macro_paths_save(zbx_uint64_t hostid, zbx_vector_lld_item_full_ptr_t *items, int *host_locked)
{
	int			ret = SUCCEED, new_num = 0, update_num = 0, delete_num = 0;
	zbx_lld_item_full_t	*item;
	zbx_vector_uint64_t	deleteids;
	zbx_db_insert_t		db_insert;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t		new_macroid = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&deleteids);

	for (int i = 0; i < items->values_num; i++)
	{
		item = items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		for (int j = 0; j < item->macro_paths.rows.values_num; j++)
		{
			zbx_sync_row_t	*row = item->macro_paths.rows.values[j];

			if (-1 == row->update_num)
			{
				zbx_vector_uint64_append(&deleteids, row->rowid);

				zbx_audit_discovery_rule_update_json_delete_lld_macro_path(ZBX_AUDIT_LLD_CONTEXT,
						item->itemid, row->rowid);
			}
			else if (0 == row->rowid)
			{
				new_num++;
			}
			else
				update_num++;

		}
	}

	if (0 == update_num && 0 == new_num && 0 == deleteids.values_num)
		goto out;

	if (0 == *host_locked)
	{
		if (SUCCEED != zbx_db_lock_hostid(hostid))
		{
			/* the host was removed while processing lld rule */
			ret = FAIL;
			goto out;
		}

		*host_locked = 1;
	}

	if (0 != new_num)
	{
		new_macroid = zbx_db_get_maxid_num("lld_macro_path", new_num);
		zbx_db_insert_prepare(&db_insert, "lld_macro_path", "lld_macro_pathid", "itemid", "lld_macro", "path",
				(char *)NULL);
	}

	for (int i = 0; i < items->values_num; i++)
	{
		item = items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		for (int j = 0; j < item->macro_paths.rows.values_num; j++)
		{
			char		delim = ' ';
			zbx_sync_row_t	*row = item->macro_paths.rows.values[j];

			if (0 == row->rowid)
			{
				zbx_db_insert_add_values(&db_insert, new_macroid, item->itemid, row->cols[0],
						row->cols[1]);

				zbx_audit_discovery_rule_update_json_add_lld_macro_path(ZBX_AUDIT_LLD_CONTEXT,
						item->itemid, new_macroid, row->cols[0], row->cols[1]);
				new_macroid++;

				continue;
			}

			if (0 >= row->update_num)
				continue;

			zbx_audit_discovery_rule_update_json_lld_macro_path_create_update_entry(ZBX_AUDIT_LLD_CONTEXT,
					item->itemid, row->rowid);

			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update lld_macro_path set");

			const char	*fields[] = {"lld_macro", "path"};

			for (int k = row->cols_num - row->update_num; k < row->cols_num; k++)
			{
				char	*value_esc;

				value_esc = zbx_db_dyn_escape_string(row->cols[k]);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%c%s='%s'", delim, fields[k],
						value_esc);

				delim = ',';

				zbx_audit_discovery_rule_update_json_update_lld_macro_path_lld_macro(
						ZBX_AUDIT_LLD_CONTEXT, item->itemid, row->rowid, row->cols_orig[k],
						row->cols[k]);

				zbx_free(value_esc);
			}

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where lld_macro_pathid=" ZBX_FS_UI64 ";\n",
					row->rowid);

			zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
		}
	}

	if (0 != update_num)
		(void)zbx_db_flush_overflowed_sql(sql, sql_offset);

	if (0 != new_num)
	{
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}

	if (0 != deleteids.values_num)
	{
		zbx_vector_uint64_sort(&deleteids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_db_execute_multiple_query("delete from lld_macro_path where", "lld_macro_pathid", &deleteids);
		delete_num = deleteids.values_num;
	}
out:
	zbx_free(sql);
	zbx_vector_uint64_destroy(&deleteids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() added:%d updated:%d removed:%d", __func__, new_num,
			update_num, delete_num);

	return ret;
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

int	lld_update_rules(zbx_uint64_t hostid, zbx_uint64_t lld_ruleid, zbx_vector_lld_row_ptr_t *lld_rows,
		char **error, const zbx_lld_lifetime_t *lifetime, const zbx_lld_lifetime_t *enabled_lifetime,
		int lastcheck)
{
	zbx_vector_lld_item_prototype_ptr_t	item_prototypes;
	zbx_hashset_t				items_index;
	int					ret = SUCCEED, host_record_is_locked = 0;
	zbx_vector_lld_item_full_ptr_t		items;

	// WDN
	zabbix_increase_log_level();
	zabbix_increase_log_level();

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_lld_item_prototype_ptr_create(&item_prototypes);

	lld_item_prototypes_get(lld_ruleid, &item_prototypes, ZBX_FLAG_DISCOVERY_RULE);

	if (0 == item_prototypes.values_num)
		goto out;

	zbx_vector_lld_item_full_ptr_create(&items);
	zbx_hashset_create(&items_index, item_prototypes.values_num * lld_rows->values_num, lld_item_index_hash_func,
			lld_item_index_compare_func);

	zbx_db_begin();
	lld_items_get(&item_prototypes, &items, ZBX_FLAG_DISCOVERY_RULE);
	zbx_db_commit();

	lld_items_make(&item_prototypes, lld_rows, &items, &items_index, lastcheck, error);
	lld_items_preproc_make(&item_prototypes, &items);
	lld_items_param_make(&item_prototypes, &items, error);
	lld_rule_macro_paths_make(&item_prototypes, &items);

	if (SUCCEED == ZBX_CHECK_LOG_LEVEL(LOG_LEVEL_TRACE))
	{
		zabbix_log(LOG_LEVEL_TRACE, "LLD rule prototypes:");

		for (int i = 0; i < item_prototypes.values_num; i++)
			lld_item_prototype_dump(item_prototypes.values[i]);
	}

	lld_items_validate(hostid, &items, error);

	zbx_db_begin();

	if (SUCCEED == lld_items_save(hostid, &item_prototypes, &items, &items_index, &host_record_is_locked,
			ZBX_FLAG_DISCOVERY_RULE) &&
			SUCCEED == lld_items_param_save(hostid, &items, &host_record_is_locked) &&
			SUCCEED == lld_items_preproc_save(hostid, &items, &host_record_is_locked) &&
			SUCCEED == lld_rule_macro_paths_save(hostid, &items, &host_record_is_locked))
	{
		if (ZBX_DB_OK != zbx_db_commit())
		{
			ret = FAIL;
			goto clean;
		}
	}
	else
	{
		zbx_db_rollback();
		goto clean;
	}

	lld_process_lost_items(&items, lifetime, enabled_lifetime, lastcheck);
clean:
	zbx_hashset_destroy(&items_index);

	zbx_vector_lld_item_full_ptr_clear_ext(&items, lld_item_full_free);
	zbx_vector_lld_item_full_ptr_destroy(&items);

	zbx_vector_lld_item_prototype_ptr_clear_ext(&item_prototypes, lld_item_prototype_free);
out:
	zbx_vector_lld_item_prototype_ptr_destroy(&item_prototypes);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	zabbix_decrease_log_level();
	zabbix_decrease_log_level();

	return ret;
}

