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
#include "zbxcommon.h"
#include "zbxalgo.h"
#include "audit/zbxaudit.h"
#include "audit/zbxaudit_item.h"
#include "zbxdb.h"
#include "zbxdbhigh.h"
#include "zbx_item_constants.h"
#include "zbxpreproc.h"
#include "zbxeval.h"
#include "zbxnum.h"
#include "zbxstr.h"
#include "zbxtime.h"
#include "zbxvariant.h"
#include "zbxcacheconfig.h"
#include "zbxjson.h"
#include "zbxtypes.h"
#include "zbxexpr.h"

/* lld_override table columns */
#define LLD_OVERRIDE_COL_NAME			0
#define LLD_OVERRIDE_COL_EVALTYPE		3
#define LLD_OVERRIDE_COL_FORMULA		4

/* lld_override_operation table columns */
#define LLD_OVERRIDE_OPERATION_COL_OPERATIONOBJECT	0
#define LLD_OVERRIDE_OPERATION_COL_OPERATOR		1
#define LLD_OVERRIDE_OPERATION_COL_VALUE		2
#define LLD_OVERRIDE_OPERATION_COL_OFFSET		LLD_OVERRIDE_OPERATION_COL_VALUE + 1

#define LLD_OVERRIDE_OPERATION_UPDATE_MASK		((UINT64_C(1) << (LLD_OVERRIDE_OPERATION_COL_OFFSET)) - 1)

/* lld_override_op* data tables */
#define LLD_OVERRIDE_OPERATION_COL_DISCOVER		3
#define LLD_OVERRIDE_OPERATION_COL_HISTORY		4
#define LLD_OVERRIDE_OPERATION_COL_INVENTORY		5
#define LLD_OVERRIDE_OPERATION_COL_PERIOD		6
#define LLD_OVERRIDE_OPERATION_COL_SEVERITY		7
#define LLD_OVERRIDE_OPERATION_COL_STATUS		8
#define LLD_OVERRIDE_OPERATION_COL_TRENDS		9

/* lld_override_condition table columns */
#define LLD_OVERRIDE_CONDITION_COL_OPERATOR		0

/* lld_override_optag table columns */
#define LLD_OVERRIDE_OPTAG_COL_OPID		0
#define LLD_OVERRIDE_OPTAG_COL_TAG		1
#define LLD_OVERRIDE_OPTAG_COL_VALUE		2

/* lld_override_optemplate table columns */
#define LLD_OVERRIDE_OPTEMPLATE_COL_OPID	0
#define LLD_OVERRIDE_OPTEMPLATE_COL_TEMPLATE	1

typedef enum
{
	LLD_OVERRIDE_DATA_DISCOVER = 0,
	LLD_OVERRIDE_DATA_HISTORY,
	LLD_OVERRIDE_DATA_INVENTORY,
	LLD_OVERRIDE_DATA_PERIOD,
	LLD_OVERRIDE_DATA_SEVERITY,
	LLD_OVERRIDE_DATA_STATUS,
	LLD_OVERRIDE_DATA_TRENDS,
	LLD_OVERRIDE_DATA_TAG,
	LLD_OVERRIDE_DATA_TEMPLATE,
	LLD_OVERRIDE_DATA_CONDITION,
	LLD_OVERRIDE_DATA_OPERATION,
	LLD_OVERRIDE_DATA_COUNT
}
zbx_lld_override_data_index_t;

#define LLD_OVERRIDE_SYNC_UPDATE		0x40000000
#define LLD_OVERRIDE_SYNC_DELETE		0x80000000

ZBX_PTR_VECTOR_IMPL(lld_override_data_ptr, zbx_lld_override_data_t *)

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

	for (int i = 0; i < entry->macros.values_num; i++)
	{
		const zbx_lld_macro_t	*macro = &entry->macros.values[i];

		zbx_sync_rowset_add_row(&src, NULL, macro->macro, macro->value);
	}

	zbx_sync_rowset_sort_by_rows(&src);
	zbx_sync_rowset_sort_by_rows(&rule_macros->macros);

	zbx_sync_rowset_merge(&rule_macros->macros, &src);

	for (int i = 0; i < rule_macros->macros.rows.values_num; )
	{
		zbx_sync_row_t	*row = rule_macros->macros.rows.values[i];

		if (ZBX_SYNC_ROW_NONE == row->flags)
		{
			zbx_sync_row_free(row);
			zbx_vector_sync_row_ptr_remove(&rule_macros->macros.rows, i);
		}
		else
			i++;
	}

	zbx_sync_rowset_sort_by_id(&rule_macros->macros);

	zbx_sync_rowset_clear(&src);
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

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select lld_macro_exportid,itemid,lld_macro,value"
			" from lld_macro_export where");
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

	zbx_db_insert_prepare(&db_insert, "lld_macro_export", "lld_macro_exportid", "itemid", "lld_macro", "value",
			NULL);

	zbx_hashset_iter_reset(lld_rules, &iter);
	while (NULL != (rule_macros = (zbx_lld_rule_macros_t *)zbx_hashset_iter_next(&iter)))
	{
		for (int i = 0; i < rule_macros->macros.rows.values_num; i++)
		{
			zbx_sync_row_t	*row = rule_macros->macros.rows.values[i];

			if (ZBX_SYNC_ROW_NONE == row->flags)
				continue;

			if (0 != (row->flags & ZBX_SYNC_ROW_INSERT))
			{
				zbx_db_insert_add_values(&db_insert, row->rowid, rule_macros->itemid,
						row->cols[0], row->cols[1]);
				continue;
			}

			if (0 != (row->flags & ZBX_SYNC_ROW_DELETE))
			{
				zbx_vector_uint64_append(&deleted_ids, row->rowid);
				continue;
			}

			const char	*fields[] = {"lld_macro", "value"};
			char		delim = ' ';

			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update lld_macro_export set");

			for (int j = 0; j < row->cols_num; j++)
			{
				if (0 == (row->flags & (UINT32_C(1) << j)))
					continue;

				char	*value_esc;

				value_esc = zbx_db_dyn_escape_string(row->cols[j]);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%c%s='%s'", delim, fields[j],
						value_esc);

				delim = ',';

				zbx_free(value_esc);
			}

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where lld_macro_exportid=" ZBX_FS_UI64
					";\n", row->rowid);

			zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
		}
	}

	(void)zbx_db_flush_overflowed_sql(sql, sql_offset);

	if (0 != deleted_ids.values_num)
		zbx_db_execute_multiple_query("delete from lld_macro_export where", "lld_macro_exportid", &deleted_ids);

	if (0 != zbx_db_insert_get_row_count(&db_insert))
	{
		zbx_db_insert_autoincrement(&db_insert, "lld_macro_exportid");
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

	result = zbx_db_select("select lld_macro,value from lld_macro_export where itemid=" ZBX_FS_UI64, ruleid);

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
 * Purpose: updates existing LLD rule macro paths and creates new ones based  *
 *          on rule prototypes                                                *
 *                                                                            *
 * Parameters: items - [IN/OUT] sorted list of items                          *
 *                                                                            *
 ******************************************************************************/
void	lld_rule_macro_paths_make(zbx_vector_lld_item_full_ptr_t *items)
{
#define LLD_PROTOTYPE_MACRO_PATH_COL_PATH	1

	zbx_sync_rowset_t	macro_paths_subst;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_sync_rowset_init(&macro_paths_subst, ZBX_LLD_ITEM_PROTOTYPE_MACRO_PATH_COLS_NUM);

	for (int i = 0; i < items->values_num; i++)
	{
		zbx_lld_item_full_t	*item = items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		if (0 == (item->prototype->item_flags & ZBX_FLAG_DISCOVERY_RULE))
			continue;

		zbx_sync_rowset_copy(&macro_paths_subst, &item->prototype->macro_paths);

		for (int j = 0; j < macro_paths_subst.rows.values_num; j++)
		{
			zbx_sync_row_t	*row = macro_paths_subst.rows.values[j];

			zbx_substitute_lld_macros(&row->cols[LLD_PROTOTYPE_MACRO_PATH_COL_PATH], item->lld_row->data,
					ZBX_MACRO_ANY, NULL, 0);
		}

		zbx_sync_rowset_merge(&item->macro_paths, &macro_paths_subst);
		zbx_vector_sync_row_ptr_clear_ext(&macro_paths_subst.rows, zbx_sync_row_free);
	}

	zbx_sync_rowset_clear(&macro_paths_subst);
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

#undef LLD_PROTOTYPE_MACRO_PATH_COL_PATH
}

/******************************************************************************
 *                                                                            *
 * Purpose: saves/update/remove lld rule macro paths                          *
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
		zbx_audit_entry_t	*audit_entry = NULL;

		item = items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		if (0 == (item->prototype->item_flags & ZBX_FLAG_DISCOVERY_RULE))
			continue;

		for (int j = 0; j < item->macro_paths.rows.values_num; j++)
		{
			zbx_sync_row_t	*row = item->macro_paths.rows.values[j];

			if (0 != (row->flags & ZBX_SYNC_ROW_DELETE))
			{
				zbx_vector_uint64_append(&deleteids, row->rowid);

				if (NULL == audit_entry)
					audit_entry = zbx_audit_item_get_entry(ZBX_AUDIT_LLD_CONTEXT, item->itemid);

				zbx_audit_audit_entry_update_json_delete_lld_macro_path(audit_entry, row->rowid);
			}
			else if (0 != (row->flags & ZBX_SYNC_ROW_INSERT))
			{
				new_num++;
			}
			else if (0 != (row->flags & ZBX_SYNC_ROW_UPDATE))
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
		zbx_audit_entry_t	*audit_entry = NULL;

		item = items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		for (int j = 0; j < item->macro_paths.rows.values_num; j++)
		{
			#define KEY(s)	zbx_audit_lldrule_macro_path(row->rowid, s, key, sizeof(key))

			char		key[AUDIT_DETAILS_KEY_LEN], delim = ' ';
			zbx_sync_row_t	*row = item->macro_paths.rows.values[j];

			if (0 == (row->flags & (ZBX_SYNC_ROW_INSERT | ZBX_SYNC_ROW_UPDATE)))
				continue;

			if (NULL == audit_entry)
				audit_entry = zbx_audit_item_get_entry(ZBX_AUDIT_LLD_CONTEXT, item->itemid);

			if (0 != (row->flags & ZBX_SYNC_ROW_INSERT))
			{
				zbx_db_insert_add_values(&db_insert, new_macroid, item->itemid, row->cols[0],
						row->cols[1]);

				zbx_audit_entry_update_json_add_lld_macro_path(audit_entry, new_macroid, row->cols[0],
						row->cols[1]);
				new_macroid++;

				continue;
			}

			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update lld_macro_path set");

			const char	*fields[] = {"lld_macro", "path"};

			for (int k = 0; k < row->cols_num; k++)
			{
				if (0 == (row->flags & (UINT32_C(1) << k)))
					continue;

				char	*value_esc;

				value_esc = zbx_db_dyn_escape_string(row->cols[k]);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%c%s='%s'", delim, fields[k],
						value_esc);
				zbx_free(value_esc);
				delim = ',';

				zbx_audit_entry_update_string(audit_entry, KEY(fields[k]), row->cols_orig[k],
						row->cols[k]);

			}

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where lld_macro_pathid=" ZBX_FS_UI64 ";\n",
					row->rowid);

			zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);

			#undef KEY
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

/******************************************************************************
 *                                                                            *
 * Purpose: assign new IDs to LLD rule filters that are being inserted        *
 *                                                                            *
 * Parameters: items - [IN/OUT] vector of LLD item structures                 *
 *                                                                            *
 ******************************************************************************/
static void	lld_rule_filters_set_ids(zbx_vector_lld_item_full_ptr_t *items)
{
	int	new_num = 0;

	for (int i = 0; i < items->values_num; i++)
	{
		zbx_lld_item_full_t	*item = items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		for (int j = 0; j < item->filters.rows.values_num; j++)
		{
			zbx_sync_row_t	*row = item->filters.rows.values[j];

			if (0 != (row->flags & ZBX_SYNC_ROW_INSERT))
				new_num++;
		}
	}

	if (0 != new_num)
	{
		zbx_uint64_t	new_conditionid = zbx_db_get_maxid_num("item_condition", new_num);

		for (int i = 0; i < items->values_num; i++)
		{
			zbx_lld_item_full_t	*item = items->values[i];

			if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
				continue;

			for (int j = 0; j < item->filters.rows.values_num; j++)
			{
				zbx_sync_row_t	*row = item->filters.rows.values[j];

				if (0 != (ZBX_SYNC_ROW_INSERT & row->flags))
					row->rowid = new_conditionid++;
			}
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: revert changes made to LLD rule filter                            *
 *                                                                            *
 * Parameters: item - [IN/OUT] LLD item structure                             *
 *                                                                            *
 * Comments: New LLD rules are marked at not discovered instead.              *
 *                                                                            *
 ******************************************************************************/
static void	lld_rule_rollback_filter(zbx_lld_item_full_t *item)
{
	if (0 != item->itemid)
	{
		item->flags &= ~ZBX_FLAG_LLD_ITEM_DISCOVERED;
	}
	else
	{
		item->flags &= ~(ZBX_FLAG_LLD_ITEM_UPDATE_FORMULA | ZBX_FLAG_LLD_ITEM_UPDATE_EVALTYPE);
		for (int i = 0; i < item->filters.rows.values_num; i++)
			item->filters.rows.values[i]->flags = ZBX_SYNC_ROW_NONE;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: update LLD rule filter formulas by replacing prototype condition  *
 *          IDs with actual condition IDs                                     *
 *                                                                            *
 * Parameters: items           - [IN/OUT] vector of LLD items                 *
 *             info            - [OUT] LLD error message                      *
 *                                                                            *
 ******************************************************************************/
static void	lld_rule_update_item_formula(zbx_vector_lld_item_full_ptr_t *items, char **info)
{
	for (int i = 0; i < items->values_num; i++)
	{
		zbx_lld_item_full_t	*item = items->values[i];
		int			j;
		zbx_eval_context_t	ctx;
		char			*error = NULL;

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		if (ZBX_CONDITION_EVAL_TYPE_EXPRESSION != item->prototype->evaltype)
		{
			if (0 != strcmp(item->formula, item->prototype->formula))
			{
				item->formula_orig = item->formula;
				item->formula = zbx_strdup(NULL, item->prototype->formula);
				item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_FORMULA;
			}

			continue;
		}

		/* update custom LLD filter formula */

		if (SUCCEED != zbx_eval_parse_expression(&ctx, item->prototype->formula,
				ZBX_EVAL_PARSE_LLD_FILTER_EXPRESSION, &error))
		{
			*info = zbx_strdcatf(*info, "Cannot parse LLD filter expression for item \"%s\": %s.\n",
					item->name, error);
			zbx_free(error);

			lld_rule_rollback_filter(item);

			continue;
		}

		for (j = 0; j < ctx.stack.values_num; j++)
		{
			zbx_eval_token_t	*token = &ctx.stack.values[j];
			zbx_uint64_t		conditionid;
			int			k;

			if (ZBX_EVAL_TOKEN_FUNCTIONID != token->type)
				continue;

			if (SUCCEED != zbx_is_uint64_n(ctx.expression + token->loc.l + 1,
					token->loc.r - token->loc.l - 1, &conditionid))
			{
				*info = zbx_strdcatf(*info, "Invalid LLD filter formula starting with \"%s\".\n",
						ctx.expression + token->loc.l);

				lld_rule_rollback_filter(item);
				break;
			}

			for (k = 0; k < item->filters.rows.values_num; k++)
			{
				zbx_sync_row_t        *row = item->filters.rows.values[k];

				if (row->parent_rowid == conditionid)
				{
					zbx_variant_set_str(&token->value, zbx_dsprintf(NULL, "{" ZBX_FS_UI64 "}",
							row->rowid));
					break;
				}
			}

			if (k == item->filters.rows.values_num)
			{
				*info = zbx_strdcat(*info, "Cannot update custom LLD filter.\n");
				lld_rule_rollback_filter(item);
				break;
			}
		}

		if (j == ctx.stack.values_num)
		{
			char	*formula = NULL;

			zbx_eval_compose_expression(&ctx, &formula);

			if (0 != strcmp(item->formula, formula))
			{
				item->formula_orig = item->formula;
				item->formula = formula;
				item->flags |= ZBX_FLAG_LLD_ITEM_UPDATE_FORMULA;
			}
			else
				zbx_free(formula);
		}

		zbx_eval_clear(&ctx);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: update existing LLD rule filters and creates new ones based       *
 *          on rule prototypes.                                               *
 *                                                                            *
 * Parameters: items           - [IN/OUT] sorted list of items                *
 *             info            - [OUT] error message                          *
 *                                                                            *
 ******************************************************************************/
void	lld_rule_filters_make(zbx_vector_lld_item_full_ptr_t *items, char **info)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	for (int i = 0; i < items->values_num; i++)
	{
		zbx_lld_item_full_t	*item = items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		if (0 == (item->prototype->item_flags & ZBX_FLAG_DISCOVERY_RULE))
			continue;

		zbx_sync_rowset_merge(&item->filters, &item->prototype->filters);
	}

	lld_rule_filters_set_ids(items);
	lld_rule_update_item_formula(items, info);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: save/update/remove LLD rule filters                               *
 *                                                                            *
 * Parameters: hostid      - [IN] parent host id                              *
 *             items       - [IN]                                             *
 *             host_locked - [IN/OUT] host record is locked                   *
 *                                                                            *
 ******************************************************************************/
int	lld_rule_filters_save(zbx_uint64_t hostid, zbx_vector_lld_item_full_ptr_t *items, int *host_locked)
{
	int			ret = SUCCEED, new_num = 0, update_num = 0, delete_num = 0;
	zbx_lld_item_full_t	*item;
	zbx_vector_uint64_t	deleteids;
	zbx_db_insert_t		db_insert;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&deleteids);

	for (int i = 0; i < items->values_num; i++)
	{
		zbx_audit_entry_t	*audit_entry = NULL;

		item = items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		if (0 == (item->prototype->item_flags & ZBX_FLAG_DISCOVERY_RULE))
			continue;

		for (int j = 0; j < item->filters.rows.values_num; j++)
		{
			zbx_sync_row_t	*row = item->filters.rows.values[j];

			if (0 != (row->flags & ZBX_SYNC_ROW_DELETE))
			{
				zbx_vector_uint64_append(&deleteids, row->rowid);

				if (NULL == audit_entry)
					audit_entry = zbx_audit_item_get_entry(ZBX_AUDIT_LLD_CONTEXT, item->itemid);

				zbx_audit_entry_update_json_delete_filter_conditions(audit_entry, row->rowid);
			}
			else if (0 != (row->flags & ZBX_SYNC_ROW_INSERT))
			{
				new_num++;
			}
			else if (0 != (row->flags & ZBX_SYNC_ROW_UPDATE))
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
		zbx_db_insert_prepare(&db_insert, "item_condition", "item_conditionid", "itemid", "operator",
				"macro", "value", (char *)NULL);
	}

	for (int i = 0; i < items->values_num; i++)
	{
		zbx_audit_entry_t	*audit_entry = NULL;

		item = items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		for (int j = 0; j < item->filters.rows.values_num; j++)
		{
			#define KEY(s)	zbx_audit_lldrule_filter_condition(row->rowid, s, key, sizeof(key))

			char		key[AUDIT_DETAILS_KEY_LEN], delim = ' ';
			zbx_sync_row_t	*row = item->filters.rows.values[j];

			if (0 == (row->flags & (ZBX_SYNC_ROW_INSERT | ZBX_SYNC_ROW_UPDATE)))
				continue;

			if (NULL == audit_entry)
				audit_entry = zbx_audit_item_get_entry(ZBX_AUDIT_LLD_CONTEXT, item->itemid);

			if (0 != (row->flags & ZBX_SYNC_ROW_INSERT))
			{
				zbx_db_insert_add_values(&db_insert, row->rowid, item->itemid, atoi(row->cols[0]),
						row->cols[1], row->cols[2]);

				zbx_audit_entry_update_json_add_filter_conditions(audit_entry, row->rowid,
						atoi(row->cols[0]), row->cols[1], row->cols[2]);

				continue;
			}

			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update item_condition set");

			const char	*fields[] = {"operator", "macro", "value"};

			if (0 != (row->flags & (UINT32_C(1) << 0)))
			{
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%coperator=%s", delim, row->cols[0]);
				delim = ',';

				zbx_audit_entry_update_int(audit_entry, KEY(fields[0]), atoi(row->cols_orig[0]),
						atoi(row->cols[0]));

			}

			for (int k = 1; k < row->cols_num; k++)
			{
				if (0 == (row->flags & (UINT32_C(1) << k)))
					continue;

				char	*value_esc;

				value_esc = zbx_db_dyn_escape_string(row->cols[k]);
				zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%c%s='%s'", delim, fields[k],
						value_esc);
				zbx_free(value_esc);

				delim = ',';

				zbx_audit_entry_update_string(audit_entry, KEY(fields[k]), row->cols_orig[k],
						row->cols[k]);
			}

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where item_conditionid=" ZBX_FS_UI64 ";\n",
					row->rowid);

			zbx_db_execute_overflowed_sql(&sql, &sql_alloc, &sql_offset);

			#undef KEY
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
		zbx_db_execute_multiple_query("delete from item_condition where", "item_conditionid", &deleteids);
		delete_num = deleteids.values_num;
	}
out:
	zbx_free(sql);
	zbx_vector_uint64_destroy(&deleteids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() added:%d updated:%d removed:%d", __func__, new_num,
			update_num, delete_num);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get synchronization flags for lld_override_op* tables             *
 *                                                                            *
 * Parameters: row   - [IN] row                                               *
 *             index - [IN] column index                                      *
 *                                                                            *
 * Return value: synchronization flags                                        *
 *                                                                            *
 ******************************************************************************/
static zbx_uint64_t	lld_operation_get_col_sync_flags(zbx_sync_row_t *row, int index)
{
	int	col = index + LLD_OVERRIDE_OPERATION_COL_OFFSET;

	if (0 != (row->flags & ZBX_SYNC_ROW_DELETE))
	{
		if (NULL != row->cols[col])
			return LLD_OVERRIDE_SYNC_DELETE;

		return 0;
	}

	if (0 != (row->flags & ZBX_SYNC_ROW_INSERT))
	{
		if (NULL != row->cols[col])
			return UINT64_C(1) << index;

		return 0;
	}

	if (0 != (row->flags & (UINT64_C(1) << col)))
	{
		if (NULL == row->cols[col])
			return LLD_OVERRIDE_SYNC_DELETE;

		if (NULL == row->cols_orig[col])
			return UINT64_C(1) << index;

		return LLD_OVERRIDE_SYNC_UPDATE;
	}

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get synchronization flags for LLD override data                   *
 *                                                                            *
 * Parameters: data              - [IN] override data                         *
 *             itemid            - [IN] item ID                               *
 *             delids_condition  - [OUT] condition IDs to delete              *
 *             delids_operation  - [OUT] operation IDs to delete              *
 *             delids_optag      - [OUT] operation tag IDs to delete          *
 *             delids_optemplate - [OUT] operation template IDs to delete     *
 *                                                                            *
 * Return value: synchronization flags                                        *
 *               - For table specific inserts - corresponding                 *
 *                 LLD_OVERRIDE_DATA_* enum bit set                           *
 *               - For any updates: LLD_OVERRIDE_SYNC_UPDATE bit set          *
 *               - For any deletes: LLD_OVERRIDE_SYNC_DELETE bit set          *
 *                                                                            *
 ******************************************************************************/
static zbx_uint64_t	lld_override_data_get_sync_flags(const zbx_lld_override_data_t *data, zbx_uint64_t itemid,
		zbx_vector_uint64_t *delids_condition, zbx_vector_uint64_t *delids_operation,
		zbx_vector_uint64_t *delids_optag, zbx_vector_uint64_t *delids_optemplate)
{
	zbx_audit_entry_t	*audit_entry = zbx_audit_item_get_entry(ZBX_AUDIT_LLD_CONTEXT, itemid);

	zbx_uint64_t	flags = 0;

	for (int i = 0; i < data->conditions.rows.values_num; i++)
	{
		zbx_sync_row_t	*row = data->conditions.rows.values[i];

		if (0 != (row->flags & ZBX_SYNC_ROW_INSERT))
		{
			flags |= UINT64_C(1) << LLD_OVERRIDE_DATA_CONDITION;
		}
		else if (0 != (row->flags & ZBX_SYNC_ROW_UPDATE))
		{
			flags |= LLD_OVERRIDE_SYNC_UPDATE;
		}
		else if (0 != (row->flags & ZBX_SYNC_ROW_DELETE))
		{
			flags |= LLD_OVERRIDE_SYNC_DELETE;
			zbx_vector_uint64_append(delids_condition, row->rowid);

			zbx_audit_entry_update_json_delete_lld_override_filter(audit_entry, data->overrideid,
					row->rowid);
		}
	}

	for (int i = 0; i < data->operations.rows.values_num; i++)
	{
		zbx_sync_row_t	*row = data->operations.rows.values[i];

		if (0 != (row->flags & ZBX_SYNC_ROW_INSERT))
		{
			flags |= UINT64_C(1) << LLD_OVERRIDE_DATA_OPERATION;
		}
		else if (0 != (row->flags & ZBX_SYNC_ROW_UPDATE))
		{
			flags |= LLD_OVERRIDE_SYNC_UPDATE;
		}
		else if (0 != (row->flags & ZBX_SYNC_ROW_DELETE))
		{
			flags |= LLD_OVERRIDE_SYNC_DELETE;
			zbx_vector_uint64_append(delids_operation, row->rowid);

			zbx_audit_entry_update_json_delete_lld_override_operation(audit_entry, data->overrideid,
					row->rowid);
			continue;
		}

		for (int j = 0; j <= LLD_OVERRIDE_DATA_TRENDS; j++)
			flags |= lld_operation_get_col_sync_flags(row, j);
	}

	for (int i = 0; i < data->optags.rows.values_num; i++)
	{
		zbx_sync_row_t	*row = data->optags.rows.values[i];

		if (0 != (row->flags & ZBX_SYNC_ROW_INSERT))
		{
			flags |= UINT64_C(1) << LLD_OVERRIDE_DATA_TAG;
		}
		else if (0 != (row->flags & ZBX_SYNC_ROW_UPDATE))
		{
			flags |= LLD_OVERRIDE_SYNC_UPDATE;
		}
		else if (0 != (row->flags & ZBX_SYNC_ROW_DELETE))
		{
			zbx_uint64_t	operationid;

			flags |= LLD_OVERRIDE_SYNC_DELETE;
			zbx_vector_uint64_append(delids_optag, row->rowid);

			ZBX_STR2UINT64(operationid, row->cols[LLD_OVERRIDE_OPTAG_COL_OPID]);

			zbx_audit_entry_update_json_delete_lld_override_operation_optag(audit_entry, data->overrideid,
					operationid, row->rowid);
		}
	}

	for (int i = 0; i < data->optemplates.rows.values_num; i++)
	{
		zbx_sync_row_t	*row = data->optemplates.rows.values[i];

		if (0 != (row->flags & ZBX_SYNC_ROW_INSERT))
		{
			flags |= UINT64_C(1) << LLD_OVERRIDE_DATA_TEMPLATE;
		}
		else if (0 != (row->flags & ZBX_SYNC_ROW_UPDATE))
		{
			flags |= LLD_OVERRIDE_SYNC_UPDATE;
		}
		else if (0 != (row->flags & ZBX_SYNC_ROW_DELETE))
		{
			zbx_uint64_t	operationid;

			flags |= LLD_OVERRIDE_SYNC_DELETE;
			zbx_vector_uint64_append(delids_optemplate, row->rowid);

			ZBX_STR2UINT64(operationid, row->cols[LLD_OVERRIDE_OPTEMPLATE_COL_OPID]);

			zbx_audit_entry_update_json_delete_lld_override_operation_optemplate(audit_entry,
					data->overrideid, operationid, row->rowid);
		}
	}

	return flags;
}

/******************************************************************************
 *                                                                            *
 * Purpose: save new/updated LLD override conditions to database              *
 *                                                                            *
 * Parameters: itemid         - [IN] item ID                                  *
 *             lld_overrideid - [IN] LLD override ID                          *
 *             conditions     - [IN] conditions to save                       *
 *             db_insert      - [IN] prepared database insert                 *
 *             sql            - [IN/OUT] sql buffer                           *
 *             sql_alloc      - [IN/OUT] sql buffer allocated size            *
 *             sql_offset     - [IN/OUT] sql buffer used size                 *
 *                                                                            *
 ******************************************************************************/
static void	lld_override_data_save_conditions(zbx_uint64_t itemid, zbx_uint64_t lld_overrideid,
		const zbx_sync_rowset_t *conditions, zbx_db_insert_t *db_insert, char **sql, size_t *sql_alloc,
		size_t *sql_offset)
{
	zbx_audit_entry_t	*audit_entry = zbx_audit_item_get_entry(ZBX_AUDIT_LLD_CONTEXT, itemid);

	for (int i = 0; i < conditions->rows.values_num; i++)
	{
		#define KEY(s) zbx_audit_lldrule_override_filter_condition(lld_overrideid, row->rowid, s, key, \
				sizeof(key))
		char	key[AUDIT_DETAILS_KEY_LEN];

		zbx_sync_row_t	*row = conditions->rows.values[i];

		if (0 != (row->flags & ZBX_SYNC_ROW_INSERT))
		{
			zbx_db_insert_add_values(db_insert, row->rowid, lld_overrideid, atoi(row->cols[0]),
					row->cols[1], row->cols[2]);

			zbx_audit_entry_update_json_add_lld_override_condition(audit_entry, lld_overrideid, row->rowid,
					atoi(row->cols[0]), row->cols[1], row->cols[2]);
			continue;
		}

		if (0 == (row->flags & ZBX_SYNC_ROW_UPDATE))
			continue;

		const char	*fields[] = {"operator", "macro", "value"};
		char		delim = ' ';

		zbx_strcpy_alloc(sql, sql_alloc, sql_offset, "update lld_override_condition set");

		for (int k = 0; k < row->cols_num; k++)
		{
			if (0 == (row->flags & (UINT32_C(1) << k)))
				continue;

			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%c%s=", delim, fields[k]);
			delim = ',';


			if (LLD_OVERRIDE_CONDITION_COL_OPERATOR != k)
			{
				char	*value_esc;

				value_esc = zbx_db_dyn_escape_string(row->cols[k]);
				zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "'%s'", value_esc);
				zbx_free(value_esc);
			}
			else
				zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s", row->cols[k]);

			zbx_audit_entry_update_string(audit_entry, KEY(fields[k]), row->cols_orig[k], row->cols[k]);
		}

		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, " where lld_override_conditionid=" ZBX_FS_UI64 ";\n",
				row->rowid);
		zbx_db_execute_overflowed_sql(sql, sql_alloc, sql_offset);

		#undef KEY
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: save new/updated LLD override operations to database              *
 *                                                                            *
 * Parameters: itemid         - [IN] item ID                                  *
 *             lld_overrideid - [IN] LLD override ID                          *
 *             operations     - [IN] operations to save                       *
 *             db_insert      - [IN] prepared database insert                 *
 *             sql            - [IN/OUT] sql buffer                           *
 *             sql_alloc      - [IN/OUT] sql buffer allocated size            *
 *             sql_offset     - [IN/OUT] sql buffer used size                 *
 *                                                                            *
 ******************************************************************************/
static void	lld_override_data_save_operations(zbx_uint64_t itemid, zbx_uint64_t lld_overrideid,
		const zbx_sync_rowset_t *operations, zbx_db_insert_t *db_insert, char **sql, size_t *sql_alloc,
		size_t *sql_offset)
{
	zbx_audit_entry_t	*audit_entry = zbx_audit_item_get_entry(ZBX_AUDIT_LLD_CONTEXT, itemid);

	for (int i = 0; i < operations->rows.values_num; i++)
	{
		#define KEY(s) zbx_audit_lldrule_override_operation(lld_overrideid, row->rowid, s, key, \
				sizeof(key))
		char	key[AUDIT_DETAILS_KEY_LEN];

		zbx_sync_row_t	*row = operations->rows.values[i];

		if (0 != (row->flags & ZBX_SYNC_ROW_INSERT))
		{
			zbx_db_insert_add_values(db_insert, row->rowid, lld_overrideid, atoi(row->cols[0]),
					atoi(row->cols[1]), row->cols[2]);

			zbx_audit_entry_update_json_add_lld_override_operation(audit_entry, lld_overrideid, row->rowid,
					atoi(row->cols[0]), atoi(row->cols[1]), row->cols[2]);
			continue;
		}

		if (0 == (row->flags & LLD_OVERRIDE_OPERATION_UPDATE_MASK))
			continue;

		const char	*fields[] = {"operationobject", "operator", "value"};
		char		delim = ' ';

		zbx_strcpy_alloc(sql, sql_alloc, sql_offset, "update lld_override_operation set");

		for (int k = 0; k <= LLD_OVERRIDE_OPERATION_COL_VALUE; k++)
		{
			if (0 == (row->flags & (UINT32_C(1) << k)))
				continue;

			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%c%s=", delim, fields[k]);
			delim = ',';

			if (LLD_OVERRIDE_OPERATION_COL_VALUE == k)
			{
				char	*value_esc;

				value_esc = zbx_db_dyn_escape_string(row->cols[k]);
				zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "'%s'", value_esc);

				zbx_free(value_esc);
			}
			else
				zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s", row->cols[k]);

			zbx_audit_entry_update_string(audit_entry, KEY(fields[k]), row->cols_orig[k], row->cols[k]);
		}

		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, " where lld_override_operationid=" ZBX_FS_UI64 ";\n",
				row->rowid);
		zbx_db_execute_overflowed_sql(sql, sql_alloc, sql_offset);

		#undef KEY
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: save new/updated LLD override operation tags to database          *
 *                                                                            *
 * Parameters: itemid         - [IN] item ID                                  *
 *             lld_overrideid - [IN] LLD override ID                          *
 *             optags     - [IN] operation tags to save                       *
 *             db_insert  - [IN] prepared database insert                     *
 *             sql        - [IN/OUT] sql buffer                               *
 *             sql_alloc  - [IN/OUT] sql buffer allocated size                *
 *             sql_offset - [IN/OUT] sql buffer used size                     *
 *                                                                            *
 ******************************************************************************/
static void	lld_override_data_save_optags(zbx_uint64_t itemid, zbx_uint64_t overrideid,
		const zbx_sync_rowset_t *optags, zbx_db_insert_t *db_insert, char **sql, size_t *sql_alloc,
		size_t *sql_offset)
{
	zbx_audit_entry_t	*audit_entry = zbx_audit_item_get_entry(ZBX_AUDIT_LLD_CONTEXT, itemid);

	for (int i = 0; i < optags->rows.values_num; i++)
	{
		#define KEY(s)	zbx_audit_lldrule_override_operation_optag(overrideid, operationid, row->rowid, s, \
				key, sizeof(key))

		char		key[AUDIT_DETAILS_KEY_LEN];
		zbx_uint64_t	operationid;
		zbx_sync_row_t	*row = optags->rows.values[i];

		if (0 != (row->flags & ZBX_SYNC_ROW_INSERT))
		{
			ZBX_DBROW2UINT64(operationid, row->cols[LLD_OVERRIDE_OPTAG_COL_OPID]);
			zbx_db_insert_add_values(db_insert, row->rowid, operationid,
					row->cols[LLD_OVERRIDE_OPTAG_COL_TAG], row->cols[LLD_OVERRIDE_OPTAG_COL_VALUE]);

			zbx_audit_entry_update_json_add_lld_override_optag(audit_entry, overrideid, operationid,
					row->rowid, row->cols[LLD_OVERRIDE_OPTAG_COL_TAG],
					row->cols[LLD_OVERRIDE_OPTAG_COL_VALUE]);
			continue;
		}

		if (0 == (row->flags & ZBX_SYNC_ROW_UPDATE))
			continue;

		const char	*fields[] = {"lld_override_operationid", "tag", "value"};
		char		delim = ' ';

		if (NULL != row->cols_orig[LLD_OVERRIDE_OPTAG_COL_OPID])
			ZBX_STR2UINT64(operationid, row->cols_orig[LLD_OVERRIDE_OPTAG_COL_OPID]);
		else
			ZBX_STR2UINT64(operationid, row->cols[LLD_OVERRIDE_OPTAG_COL_OPID]);

		zbx_strcpy_alloc(sql, sql_alloc, sql_offset, "update lld_override_optag set");

		for (int k = 0; k < row->cols_num; k++)
		{
			if (0 == (row->flags & (UINT32_C(1) << k)))
				continue;

			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%c%s=", delim, fields[k]);
			delim = ',';

			if (LLD_OVERRIDE_OPTAG_COL_OPID != k)
			{
				char	*value_esc;

				value_esc = zbx_db_dyn_escape_string(row->cols[k]);
				zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "'%s'", value_esc);

				zbx_free(value_esc);
			}
			else
				zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s", row->cols[k]);

			zbx_audit_entry_update_string(audit_entry, KEY(fields[k]), row->cols_orig[k], row->cols[k]);
		}

		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, " where lld_override_optagid=" ZBX_FS_UI64 ";\n",
				row->rowid);
		zbx_db_execute_overflowed_sql(sql, sql_alloc, sql_offset);

		#undef KEY
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: save new/updated LLD override operation template links to database*
 *                                                                            *
 * Parameters: itemid         - [IN] item ID                                  *
 *             lld_overrideid - [IN] LLD override ID                          *
 *             optemplates - [IN] operation templates to save                 *
 *             db_insert   - [IN] prepared database insert                    *
 *             sql         - [IN/OUT] sql buffer                              *
 *             sql_alloc   - [IN/OUT] sql buffer allocated size               *
 *             sql_offset  - [IN/OUT] sql buffer used size                    *
 *                                                                            *
 ******************************************************************************/
static void	lld_override_data_save_optemplates(zbx_uint64_t itemid, zbx_uint64_t overrideid,
		const zbx_sync_rowset_t *optemplates, zbx_db_insert_t *db_insert, char **sql, size_t *sql_alloc,
		size_t *sql_offset)
{
	zbx_audit_entry_t	*audit_entry = zbx_audit_item_get_entry(ZBX_AUDIT_LLD_CONTEXT, itemid);

	for (int i = 0; i < optemplates->rows.values_num; i++)
	{
		#define KEY(s)	zbx_audit_lldrule_override_operation_optemplate(overrideid, operationid, row->rowid, \
				s, key, sizeof(key))

		char		key[AUDIT_DETAILS_KEY_LEN];
		zbx_uint64_t	operationid;
		zbx_sync_row_t	*row = optemplates->rows.values[i];

		if (0 != (row->flags & ZBX_SYNC_ROW_INSERT))
		{
			zbx_uint64_t	templateid;

			ZBX_DBROW2UINT64(operationid, row->cols[LLD_OVERRIDE_OPTEMPLATE_COL_OPID]);
			ZBX_DBROW2UINT64(templateid, row->cols[LLD_OVERRIDE_OPTEMPLATE_COL_TEMPLATE]);
			zbx_db_insert_add_values(db_insert, row->rowid, operationid, templateid);

			zbx_audit_entry_update_json_add_lld_override_optemplate(audit_entry, overrideid, operationid,
					row->rowid, templateid);

			continue;
		}

		if (0 == (row->flags & ZBX_SYNC_ROW_UPDATE))
			continue;

		const char	*fields[] = {"lld_override_operationid", "templateid"};
		char		delim = ' ';

		if (NULL != row->cols_orig[LLD_OVERRIDE_OPTEMPLATE_COL_OPID])
			ZBX_STR2UINT64(operationid, row->cols_orig[LLD_OVERRIDE_OPTEMPLATE_COL_OPID]);
		else
			ZBX_STR2UINT64(operationid, row->cols[LLD_OVERRIDE_OPTEMPLATE_COL_OPID]);

		zbx_strcpy_alloc(sql, sql_alloc, sql_offset, "update lld_override_optemplate set");

		for (int k = 0; k < row->cols_num; k++)
		{
			zbx_uint64_t	id;

			if (0 == (row->flags & (UINT32_C(1) << k)))
				continue;

			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%c%s=", delim, fields[k]);
			delim = ',';

			ZBX_DBROW2UINT64(id, row->cols[k]);
			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, ZBX_FS_UI64, id);

			zbx_audit_entry_update_string(audit_entry, KEY(fields[k]), row->cols_orig[k], row->cols[k]);
		}

		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, " where lld_override_optemplateid=" ZBX_FS_UI64 ";\n",
				row->rowid);
		zbx_db_execute_overflowed_sql(sql, sql_alloc, sql_offset);

		#undef KEY
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: save or update integer operation data for LLD override            *
 *                                                                            *
 * Parameters: itemid     - [IN] associated item ID                           *
 *             overrideid - [IN] override ID                                  *
 *             row        - [IN] row data                                     *
 *             col        - [IN] column index                                 *
 *             table_name - [IN] target table name                            *
 *             field_name - [IN] field name in the target table               *
 *             resource   - [IN] resource name for auditing                   *
 *             db_insert  - [IN] database insert object                       *
 *             sql        - [IN/OUT] SQL query buffer                         *
 *             sql_alloc  - [IN/OUT] allocated SQL buffer size                *
 *             sql_offset - [IN/OUT] current position in SQL buffer           *
 *                                                                            *
 ******************************************************************************/
static void	lld_override_data_save_opint(zbx_uint64_t itemid, zbx_uint64_t overrideid, zbx_sync_row_t *row,
		int col, const char *table_name, const char *field_name, const char *resource,
		zbx_db_insert_t *db_insert, char **sql, size_t *sql_alloc, size_t *sql_offset)
{
	if (0 == (row->flags & (UINT32_C(1) << col)) &&
			(0 == (row->flags & ZBX_SYNC_ROW_INSERT) || NULL == row->cols[col]))
	{
		return;
	}

	#define KEY(s)	zbx_audit_lldrule_override_operation(overrideid, row->rowid, s, key, sizeof(key))

	char			key[AUDIT_DETAILS_KEY_LEN];
	zbx_audit_entry_t	*audit_entry = zbx_audit_item_get_entry(ZBX_AUDIT_LLD_CONTEXT, itemid);

	if (NULL == row->cols_orig[col])
	{
		zbx_db_insert_add_values(db_insert, row->rowid, atoi(row->cols[col]));

		zbx_audit_entry_add(audit_entry, KEY(resource));
		zbx_audit_entry_add_string(audit_entry, table_name, field_name, KEY(resource), row->cols[col]);
	}
	else if (NULL != row->cols[col])
	{
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "update %s set %s=%d"
				" where lld_override_operationid=" ZBX_FS_UI64 ";\n",
				table_name, field_name, atoi(row->cols[col]), row->rowid);
		zbx_db_execute_overflowed_sql(sql, sql_alloc, sql_offset);

		zbx_audit_entry_update_string(audit_entry, KEY(resource), row->cols_orig[col], row->cols[col]);
	}
	else
	{
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "delete from %s"
				" where lld_override_operationid=" ZBX_FS_UI64 ";\n",
				table_name, row->rowid);
		zbx_db_execute_overflowed_sql(sql, sql_alloc, sql_offset);

		zbx_audit_entry_delete(audit_entry, KEY(resource));
	}

	#undef KEY
}

/******************************************************************************
 *                                                                            *
 * Purpose: save or update string operation data for LLD override             *
 *                                                                            *
 * Parameters: itemid     - [IN] associated item ID                           *
 *             overrideid - [IN] override ID                                  *
 *             row        - [IN] row data                                     *
 *             col        - [IN] column index                                 *
 *             table_name - [IN] target table name                            *
 *             field_name - [IN] field name in the target table               *
 *             resource   - [IN] resource name for auditing                   *
 *             db_insert  - [IN] database insert object                       *
 *             sql        - [IN/OUT] SQL query buffer                         *
 *             sql_alloc  - [IN/OUT] allocated SQL buffer size                *
 *             sql_offset - [IN/OUT] current position in SQL buffer           *
 *                                                                            *
 ******************************************************************************/
static void	lld_override_data_save_opstr(zbx_uint64_t itemid, zbx_uint64_t overrideid, zbx_sync_row_t *row,
		int col, const char *table_name, const char *field_name, const char *resource,
		zbx_db_insert_t *db_insert, char **sql, size_t *sql_alloc, size_t *sql_offset)
{
	if (0 == (row->flags & (UINT32_C(1) << col)) &&
			(0 == (row->flags & ZBX_SYNC_ROW_INSERT) || NULL == row->cols[col]))
	{
		return;
	}

	#define KEY(s)	zbx_audit_lldrule_override_operation(overrideid, row->rowid, s, key, sizeof(key))

	char			key[AUDIT_DETAILS_KEY_LEN];
	zbx_audit_entry_t	*audit_entry = zbx_audit_item_get_entry(ZBX_AUDIT_LLD_CONTEXT, itemid);

	if (NULL == row->cols_orig[col])
	{
		zbx_db_insert_add_values(db_insert, row->rowid, row->cols[col]);

		zbx_audit_entry_add(audit_entry, KEY(resource));
		zbx_audit_entry_add_string(audit_entry, table_name, field_name, KEY(resource), row->cols[col]);
	}
	else if (NULL != row->cols[col])
	{
		char	*value_esc = zbx_db_dyn_escape_string(row->cols[col]);

		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "update %s set %s='%s'"
				" where lld_override_operationid=" ZBX_FS_UI64 ";\n",
				table_name, field_name, value_esc, row->rowid);

		zbx_free(value_esc);
		zbx_db_execute_overflowed_sql(sql, sql_alloc, sql_offset);

		zbx_audit_entry_update_string(audit_entry, KEY(resource), row->cols_orig[col], row->cols[col]);
	}
	else
	{
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "delete from %s"
				" where lld_override_operationid=" ZBX_FS_UI64 ";\n",
				table_name, row->rowid);
		zbx_db_execute_overflowed_sql(sql, sql_alloc, sql_offset);

		zbx_audit_entry_delete(audit_entry, KEY(resource));
	}

	#undef KEY
}

/******************************************************************************
 *                                                                            *
 * Purpose: save LLD override data to database                                *
 *                                                                            *
 * Parameters: lld_overrideid  - [IN] LLD override identifier                 *
 *              itemid         - [IN] item ID                                 *
 *             data            - [IN] override data to save                   *
 *             db_insert_data  - [IN] prepared database inserts               *
 *             sql             - [IN/OUT] sql buffer                          *
 *             sql_alloc       - [IN/OUT] sql buffer allocated size           *
 *             sql_offset      - [IN/OUT] sql buffer used size                *
 *                                                                            *
 ******************************************************************************/
static void 	lld_override_data_save(zbx_uint64_t itemid, const zbx_lld_override_data_t *data,
		zbx_db_insert_t *db_insert_data, char **sql, size_t *sql_alloc, size_t *sql_offset)
{
	lld_override_data_save_conditions(itemid, data->overrideid, &data->conditions,
			&db_insert_data[LLD_OVERRIDE_DATA_CONDITION], sql, sql_alloc, sql_offset);

	lld_override_data_save_operations(itemid, data->overrideid, &data->operations,
			&db_insert_data[LLD_OVERRIDE_DATA_OPERATION], sql, sql_alloc, sql_offset);

	lld_override_data_save_optags(itemid, data->overrideid, &data->optags, &db_insert_data[LLD_OVERRIDE_DATA_TAG],
			sql, sql_alloc, sql_offset);

	lld_override_data_save_optemplates(itemid, data->overrideid, &data->optemplates,
			&db_insert_data[LLD_OVERRIDE_DATA_TEMPLATE], sql, sql_alloc, sql_offset);

	for (int i = 0; i < data->operations.rows.values_num; i++)
	{
		zbx_sync_row_t	*row = data->operations.rows.values[i];

		if (0 == (row->flags & (ZBX_SYNC_ROW_INSERT | ZBX_SYNC_ROW_UPDATE)))
			continue;

		lld_override_data_save_opint(itemid, data->overrideid, row,
				LLD_OVERRIDE_OPERATION_COL_DISCOVER, "lld_override_opdiscover", "discover",
				"opdiscover", &db_insert_data[LLD_OVERRIDE_DATA_DISCOVER], sql, sql_alloc, sql_offset);

		lld_override_data_save_opstr(itemid, data->overrideid, row,
				LLD_OVERRIDE_OPERATION_COL_HISTORY, "lld_override_ophistory", "history", "ophistory",
				&db_insert_data[LLD_OVERRIDE_DATA_HISTORY], sql, sql_alloc, sql_offset);

		lld_override_data_save_opint(itemid, data->overrideid, row,
				LLD_OVERRIDE_OPERATION_COL_INVENTORY, "lld_override_opinventory", "inventory_mode",
				"opinventory", &db_insert_data[LLD_OVERRIDE_DATA_INVENTORY], sql, sql_alloc,
				sql_offset);

		lld_override_data_save_opstr(itemid, data->overrideid, row,
				LLD_OVERRIDE_OPERATION_COL_PERIOD, "lld_override_opperiod", "delay", "opperiod",
				&db_insert_data[LLD_OVERRIDE_DATA_PERIOD], sql, sql_alloc, sql_offset);

		lld_override_data_save_opint(itemid, data->overrideid, row,
				LLD_OVERRIDE_OPERATION_COL_SEVERITY, "lld_override_opseverity", "severity",
				"opseverity", &db_insert_data[LLD_OVERRIDE_DATA_SEVERITY], sql, sql_alloc, sql_offset);

		lld_override_data_save_opint(itemid, data->overrideid, row,
				LLD_OVERRIDE_OPERATION_COL_STATUS, "lld_override_opstatus", "status", "opstatus",
				&db_insert_data[LLD_OVERRIDE_DATA_STATUS], sql, sql_alloc, sql_offset);

		lld_override_data_save_opstr(itemid, data->overrideid, row,
				LLD_OVERRIDE_OPERATION_COL_TRENDS, "lld_override_optrends", "trends", "optrends",
				&db_insert_data[LLD_OVERRIDE_DATA_TRENDS], sql, sql_alloc, sql_offset);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: update LLD override in database                                   *
 *                                                                            *
 * Parameters: sql        - [IN/OUT] SQL string buffer                        *
 *             sql_alloc  - [IN/OUT] allocated size of SQL string buffer      *
 *             sql_offset - [IN/OUT] current position in SQL string buffer    *
 *             itemid     - [IN] LLD rule ID                                  *
 *             row        - [IN] override row                                 *
 *                                                                            *
 ******************************************************************************/
static void	lld_rule_override_update_sql(char **sql, size_t *sql_alloc, size_t *sql_offset, zbx_uint64_t itemid,
		zbx_sync_row_t *row)
{
	#define KEY(s)	zbx_audit_lldrule_override(row->rowid, s, key, sizeof(key))
	#define KEY_FILTER(s)	zbx_audit_lldrule_override_filter(row->rowid, s, key, sizeof(key))

	char			key[AUDIT_DETAILS_KEY_LEN], delim = ' ';
	zbx_audit_entry_t	*audit_entry;

	audit_entry = zbx_audit_item_get_entry(ZBX_AUDIT_LLD_CONTEXT, itemid);

	zbx_strcpy_alloc(sql, sql_alloc, sql_offset, "update lld_override set");

	const char	*fields[] = {"name", "step", "stop", "evaltype", "formula"};

	for (int i = 0; i < row->cols_num; i++)
	{
		if (0 == (row->flags & (UINT32_C(1) << i)))
			continue;

		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%c%s=", delim, fields[i]);
		delim = ',';

		if (LLD_OVERRIDE_COL_NAME == i || LLD_OVERRIDE_COL_FORMULA == i)
		{
			char	*value_esc;

			value_esc = zbx_db_dyn_escape_string(row->cols[i]);
			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "'%s'", value_esc);
			zbx_free(value_esc);

			zbx_audit_entry_update_string(audit_entry, KEY(fields[i]), row->cols_orig[i], row->cols[i]);
		}
		else
		{
			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%d", atoi(row->cols[i]));
			zbx_audit_entry_update_string(audit_entry, KEY_FILTER(fields[i]), row->cols_orig[i],
					row->cols[i]);
		}

		zbx_audit_entry_update_string(audit_entry, KEY(fields[i]), row->cols_orig[i], row->cols[i]);
	}

	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, " where lld_overrideid=" ZBX_FS_UI64
			";\n", row->rowid);

	zbx_db_execute_overflowed_sql(sql, sql_alloc, sql_offset);

#undef KEY
}

/******************************************************************************
 *                                                                            *
 * Purpose: save/update/remove lld rule overrides                             *
 *                                                                            *
 * Parameters: hostid      - [IN] parent host id                              *
 *             items       - [IN]                                             *
 *             host_locked - [IN/OUT] host record is locked                   *
 *                                                                            *
 ******************************************************************************/
int	lld_items_overrides_save(zbx_uint64_t hostid, zbx_vector_lld_item_full_ptr_t *items, int *host_locked)
{
	int			ret = SUCCEED, new_num = 0, update_num = 0, delete_num = 0;
	zbx_lld_item_full_t	*item;
	zbx_vector_uint64_t	delids_override, delids_condition, delids_operation, delids_optag, delids_optemplate;
	zbx_db_insert_t		db_insert, db_insert_data[LLD_OVERRIDE_DATA_COUNT];
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t		overrideid = 0, data_flags = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_uint64_create(&delids_override);
	zbx_vector_uint64_create(&delids_condition);
	zbx_vector_uint64_create(&delids_operation);
	zbx_vector_uint64_create(&delids_optag);
	zbx_vector_uint64_create(&delids_optemplate);

	for (int i = 0; i < items->values_num; i++)
	{
		zbx_audit_entry_t	*audit_entry = NULL;

		item = items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		if (0 == (item->prototype->item_flags & ZBX_FLAG_DISCOVERY_RULE))
			continue;

		for (int j = 0; j < item->overrides.rows.values_num; j++)
		{
			zbx_sync_row_t	*row = item->overrides.rows.values[j];

			if (0 != (row->flags & ZBX_SYNC_ROW_DELETE))
			{
				zbx_vector_uint64_append(&delids_override, row->rowid);

				if (NULL == audit_entry)
					audit_entry = zbx_audit_item_get_entry(ZBX_AUDIT_LLD_CONTEXT, item->itemid);

				zbx_audit_entry_update_json_delete_lld_override(audit_entry, row->rowid);
			}
			else if (0 != (row->flags & ZBX_SYNC_ROW_INSERT))
			{
				new_num++;
			}
			else if (0 != (row->flags & ZBX_SYNC_ROW_UPDATE))
				update_num++;

			data_flags |= lld_override_data_get_sync_flags((zbx_lld_override_data_t *)row->data,
					item->itemid, &delids_condition, &delids_operation, &delids_optag,
					&delids_optemplate);
		}
	}

	if (0 == update_num && 0 == new_num && 0 == delids_override.values_num && 0 == data_flags)
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
		overrideid = zbx_db_get_maxid_num("lld_override", new_num);
		zbx_db_insert_prepare(&db_insert, "lld_override", "lld_overrideid", "itemid", "name", "step", "stop",
				"evaltype", "formula", (char *)NULL);
	}

	if (0 != (data_flags & (UINT64_C(1) << LLD_OVERRIDE_DATA_DISCOVER)))
	{
		zbx_db_insert_prepare(&db_insert_data[LLD_OVERRIDE_DATA_DISCOVER], "lld_override_opdiscover",
				"lld_override_operationid", "discover", NULL);
	}

	if (0 != (data_flags & (UINT64_C(1) << LLD_OVERRIDE_DATA_HISTORY)))
	{
		zbx_db_insert_prepare(&db_insert_data[LLD_OVERRIDE_DATA_HISTORY], "lld_override_ophistory",
				"lld_override_operationid", "history", NULL);
	}

	if (0 != (data_flags & (UINT64_C(1) << LLD_OVERRIDE_DATA_INVENTORY)))
	{
		zbx_db_insert_prepare(&db_insert_data[LLD_OVERRIDE_DATA_INVENTORY], "lld_override_opinventory",
				"lld_override_operationid", "inventory_mode", NULL);
	}

	if (0 != (data_flags & (UINT64_C(1) << LLD_OVERRIDE_DATA_PERIOD)))
	{
		zbx_db_insert_prepare(&db_insert_data[LLD_OVERRIDE_DATA_PERIOD], "lld_override_opperiod",
				"lld_override_operationid", "delay", NULL);
	}

	if (0 != (data_flags & (UINT64_C(1) << LLD_OVERRIDE_DATA_SEVERITY)))
	{
		zbx_db_insert_prepare(&db_insert_data[LLD_OVERRIDE_DATA_SEVERITY], "lld_override_opseverity",
				"lld_override_operationid", "severity", NULL);
	}

	if (0 != (data_flags & (UINT64_C(1) << LLD_OVERRIDE_DATA_STATUS)))
	{
		zbx_db_insert_prepare(&db_insert_data[LLD_OVERRIDE_DATA_STATUS], "lld_override_opstatus",
				"lld_override_operationid", "status", NULL);
	}

	if (0 != (data_flags & (UINT64_C(1) << LLD_OVERRIDE_DATA_TRENDS)))
	{
		zbx_db_insert_prepare(&db_insert_data[LLD_OVERRIDE_DATA_TRENDS], "lld_override_optrends",
				"lld_override_operationid", "trends", NULL);
	}

	if (0 != (data_flags & (UINT64_C(1) << LLD_OVERRIDE_DATA_TAG)))
	{
		zbx_db_insert_prepare(&db_insert_data[LLD_OVERRIDE_DATA_TAG], "lld_override_optag",
				"lld_override_optagid", "lld_override_operationid", "tag", "value", NULL);
		zbx_db_insert_autoincrement(&db_insert_data[LLD_OVERRIDE_DATA_TAG], "lld_override_optagid");
	}

	if (0 != (data_flags & (UINT64_C(1) << LLD_OVERRIDE_DATA_TEMPLATE)))
	{
		zbx_db_insert_prepare(&db_insert_data[LLD_OVERRIDE_DATA_TEMPLATE], "lld_override_optemplate",
				"lld_override_optemplateid", "lld_override_operationid", "templateid", NULL);
		zbx_db_insert_autoincrement(&db_insert_data[LLD_OVERRIDE_DATA_TEMPLATE], "lld_override_optemplateid");
	}

	if (0 != (data_flags & (UINT64_C(1) << LLD_OVERRIDE_DATA_CONDITION)))
	{
		zbx_db_insert_prepare(&db_insert_data[LLD_OVERRIDE_DATA_CONDITION], "lld_override_condition",
				"lld_override_conditionid", "lld_overrideid", "operator", "macro", "value", NULL);
	}

	if (0 != (data_flags & (UINT64_C(1) << LLD_OVERRIDE_DATA_OPERATION)))
	{
		zbx_db_insert_prepare(&db_insert_data[LLD_OVERRIDE_DATA_OPERATION], "lld_override_operation",
				"lld_override_operationid", "lld_overrideid", "operationobject", "operator", "value",
				NULL);
	}

	for (int i = 0; i < items->values_num; i++)
	{
		item = items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		for (int j = 0; j < item->overrides.rows.values_num; j++)
		{
			zbx_sync_row_t	*row = item->overrides.rows.values[j];

			if (0 != (row->flags & ZBX_SYNC_ROW_INSERT))
			{
				zbx_audit_entry_t	*audit_entry;

				row->rowid = ((zbx_lld_override_data_t *)row->data)->overrideid = overrideid++;

				zbx_db_insert_add_values(&db_insert, row->rowid, item->itemid, row->cols[0],
						atoi(row->cols[1]), atoi(row->cols[2]), atoi(row->cols[3]),
						row->cols[4]);

				audit_entry = zbx_audit_item_get_entry(ZBX_AUDIT_LLD_CONTEXT, item->itemid);
				zbx_audit_entry_update_json_add_lld_override(audit_entry, row->rowid, row->cols[0],
						atoi(row->cols[1]), atoi(row->cols[2]));
			}
			else if (0 != (row->flags & ZBX_SYNC_ROW_UPDATE))
			{
				lld_rule_override_update_sql(&sql, &sql_alloc, &sql_offset, item->itemid, row);
			}

			lld_override_data_save(item->itemid, (zbx_lld_override_data_t *)row->data, db_insert_data,
					&sql, &sql_alloc, &sql_offset);
		}
	}

	if (0 != update_num || 0 != (data_flags & LLD_OVERRIDE_SYNC_UPDATE))
		(void)zbx_db_flush_overflowed_sql(sql, sql_offset);

	if (0 != new_num)
	{
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}

	if (0 != (data_flags & (UINT64_C(1) << LLD_OVERRIDE_DATA_CONDITION)))
	{
		zbx_db_insert_execute(&db_insert_data[LLD_OVERRIDE_DATA_CONDITION]);
		zbx_db_insert_clean(&db_insert_data[LLD_OVERRIDE_DATA_CONDITION]);
	}

	if (0 != (data_flags & (UINT64_C(1) << LLD_OVERRIDE_DATA_OPERATION)))
	{
		zbx_db_insert_execute(&db_insert_data[LLD_OVERRIDE_DATA_OPERATION]);
		zbx_db_insert_clean(&db_insert_data[LLD_OVERRIDE_DATA_OPERATION]);
	}

	for (int i = 0; i <= LLD_OVERRIDE_DATA_TEMPLATE; i++)
	{
		if (0 != (data_flags & (UINT64_C(1) << i)))
		{
			zbx_db_insert_execute(&db_insert_data[i]);
			zbx_db_insert_clean(&db_insert_data[i]);
		}
	}

	if (0 != delids_condition.values_num)
	{
		zbx_vector_uint64_sort(&delids_condition, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_db_execute_multiple_query("delete from lld_override_condition where", "lld_override_conditionid",
				&delids_condition);
	}

	if (0 != delids_operation.values_num)
	{
		zbx_vector_uint64_sort(&delids_operation, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_db_execute_multiple_query("delete from lld_override_operation where", "lld_override_operationid",
				&delids_operation);

	}

	if (0 != delids_optag.values_num)
	{
		zbx_vector_uint64_sort(&delids_optag, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_db_execute_multiple_query("delete from lld_override_optag where", "lld_override_optagid",
				&delids_optag);
	}

	if (0 != delids_optemplate.values_num)
	{
		zbx_vector_uint64_sort(&delids_optemplate, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_db_execute_multiple_query("delete from lld_override_optemplate where", "lld_override_optemplateid",
				&delids_optemplate);
	}

	if (0 != delids_override.values_num)
	{
		zbx_vector_uint64_sort(&delids_override, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_db_execute_multiple_query("delete from lld_override where", "lld_overrideid", &delids_override);
		delete_num = delids_override.values_num;
	}
out:
	zbx_free(sql);
	zbx_vector_uint64_destroy(&delids_optemplate);
	zbx_vector_uint64_destroy(&delids_optag);
	zbx_vector_uint64_destroy(&delids_operation);
	zbx_vector_uint64_destroy(&delids_condition);
	zbx_vector_uint64_destroy(&delids_override);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() added:%d updated:%d removed:%d", __func__, new_num,
			update_num, delete_num);

	return ret;
}

zbx_lld_override_data_t *lld_override_data_create(zbx_uint64_t overrideid)
{
	zbx_lld_override_data_t	*data;

	data = (zbx_lld_override_data_t *)zbx_malloc(NULL, sizeof(zbx_lld_override_data_t));

	data->overrideid = overrideid;
	zbx_sync_rowset_init(&data->conditions, 3);
	zbx_sync_rowset_init(&data->operations, 10);
	zbx_sync_rowset_init(&data->optags, 3);
	zbx_sync_rowset_init(&data->optemplates, 2);

	return data;
}

void	lld_override_data_free(void *v)
{
	zbx_lld_override_data_t	*data = (zbx_lld_override_data_t *)v;

	zbx_sync_rowset_clear(&data->conditions);
	zbx_sync_rowset_clear(&data->operations);
	zbx_sync_rowset_clear(&data->optags);
	zbx_sync_rowset_clear(&data->optemplates);

	zbx_free(data);
}

int	lld_override_data_compare(const void *v1, const void *v2)
{
	const zbx_lld_override_data_t        *sync1 = *(const zbx_lld_override_data_t * const *)v1;
	const zbx_lld_override_data_t        *sync2 = *(const zbx_lld_override_data_t * const *)v2;

	ZBX_RETURN_IF_NOT_EQUAL(sync1->overrideid, sync2->overrideid);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: set IDs for new LLD rule override conditions and operations       *
 *                                                                            *
 * Parameters: items - [IN/OUT] vector of LLD items                           *
 *                                                                            *
 ******************************************************************************/
static void	lld_rule_overrides_set_base_ids(zbx_vector_lld_item_full_ptr_t *items)
{
	int	new_conditions_num = 0, new_operations_num = 0;

	/* calculate the number of new conditions and operations */
	for (int i = 0; i < items->values_num; i++)
	{
		zbx_lld_item_full_t	*item = items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		for (int j = 0; j < item->overrides.rows.values_num; j++)
		{
			zbx_lld_override_data_t	*data;

			data = (zbx_lld_override_data_t *)item->overrides.rows.values[j]->data;

			for (int k = 0; k < data->conditions.rows.values_num; k++)
			{
				zbx_sync_row_t 	*row = data->conditions.rows.values[k];

				if (0 != (row->flags & ZBX_SYNC_ROW_INSERT))
					new_conditions_num++;
			}

			for (int k = 0; k < data->operations.rows.values_num; k++)
			{
				zbx_sync_row_t 	*row = data->operations.rows.values[k];

				if (0 != (row->flags & ZBX_SYNC_ROW_INSERT))
					new_operations_num++;
			}
		}
	}

	/* reserve and assign IDs for new conditions, operations */
	if (0 != new_conditions_num || 0 != new_operations_num)
	{
		zbx_uint64_t	new_conditionid, new_operationid;

		if (0 != new_conditions_num)
			new_conditionid = zbx_db_get_maxid_num("lld_override_condition", new_conditions_num);

		if (0 != new_operations_num)
			new_operationid = zbx_db_get_maxid_num("lld_override_operation", new_operations_num);

		for (int i = 0; i < items->values_num; i++)
		{
			zbx_lld_item_full_t	*item = items->values[i];

			if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
				continue;

			for (int j = 0; j < item->overrides.rows.values_num; j++)
			{
				zbx_lld_override_data_t	*data;

				data = (zbx_lld_override_data_t *)item->overrides.rows.values[j]->data;

				for (int k = 0; k < data->conditions.rows.values_num; k++)
				{
					zbx_sync_row_t	*row = data->conditions.rows.values[k];

					if (0 != (row->flags & ZBX_SYNC_ROW_INSERT))
						row->rowid = new_conditionid++;
				}

				for (int k = 0; k < data->operations.rows.values_num; k++)
				{
					zbx_sync_row_t	*row = data->operations.rows.values[k];

					if (0 != (row->flags & ZBX_SYNC_ROW_INSERT))
						row->rowid = new_operationid++;
				}
			}
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: set IDs for new LLD rule override operation tags and templates    *
 *                                                                            *
 * Parameters: items - [IN/OUT] vector of LLD items                           *
 *                                                                            *
 ******************************************************************************/
static void	lld_rule_overrides_set_dep_ids(zbx_vector_lld_item_full_ptr_t *items)
{
	int	new_optags_num = 0, new_optemplates_num = 0;

	/* calculate the number of new conditions and operations */
	for (int i = 0; i < items->values_num; i++)
	{
		zbx_lld_item_full_t	*item = items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		for (int j = 0; j < item->overrides.rows.values_num; j++)
		{
			zbx_lld_override_data_t	*data;

			data = (zbx_lld_override_data_t *)item->overrides.rows.values[j]->data;

			for (int k = 0; k < data->optags.rows.values_num; k++)
			{
				zbx_sync_row_t         *row = data->optags.rows.values[k];

				if (0 != (row->flags & ZBX_SYNC_ROW_INSERT))
					new_optags_num++;
			}

			for (int k = 0; k < data->optemplates.rows.values_num; k++)
			{
				zbx_sync_row_t         *row = data->optemplates.rows.values[k];

				if (0 != (row->flags & ZBX_SYNC_ROW_INSERT))
					new_optemplates_num++;
			}
		}
	}

	/* reserve and assign IDs for new optags and optemplates */
	if (0 != new_optags_num || 0 != new_optemplates_num)
	{
		zbx_uint64_t	new_optagid, new_optemplateid;

		if (0 != new_optags_num)
			new_optagid = zbx_db_get_maxid_num("lld_override_optag", new_optags_num);

		if (0 != new_optemplates_num)
			new_optemplateid = zbx_db_get_maxid_num("lld_override_optemplate", new_optemplates_num);

		for (int i = 0; i < items->values_num; i++)
		{
			zbx_lld_item_full_t	*item = items->values[i];

			if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
				continue;

			for (int j = 0; j < item->overrides.rows.values_num; j++)
			{
				zbx_lld_override_data_t	*data;

				data = (zbx_lld_override_data_t *)item->overrides.rows.values[j]->data;

				for (int k = 0; k < data->optags.rows.values_num; k++)
				{
					zbx_sync_row_t         *row = data->optags.rows.values[k];

					if (0 != (row->flags & ZBX_SYNC_ROW_INSERT))
						row->rowid = new_optagid++;
				}

				for (int k = 0; k < data->optemplates.rows.values_num; k++)
				{
					zbx_sync_row_t         *row = data->optemplates.rows.values[k];

					if (0 != (row->flags & ZBX_SYNC_ROW_INSERT))
						row->rowid = new_optemplateid++;
				}
			}
		}
	}
}

/* helper structure to remap prototype operation IDs to actual LLD rule operation IDs */
typedef struct
{
	zbx_uint64_t	src_opid;
	char		dst_opid[MAX_ID_LEN];
}
zbx_lld_operation_map_t;

/******************************************************************************
 *                                                                            *
 * Purpose: remap prototype operation ID to actual LLD rule operation ID      *
 *                                                                            *
 * Parameters: operations     - [IN] hashset with operation ID mappings       *
 *             operationid_db - [IN] source operation ID to remap             *
 *             info           - [OUT] error message                           *
 *                                                                            *
 * Return value: remapped operation ID or NULL if remapping failed            *
 *                                                                            *
 ******************************************************************************/
static const char	*lld_override_remap_operationid(const zbx_hashset_t *operations, const char *operationid_db,
		char **info)
{
	zbx_uint64_t		operationid;
	zbx_lld_operation_map_t	*opmap;

	if (SUCCEED != zbx_is_uint64(operationid_db, &operationid))
	{
		*info = zbx_strdcatf(*info, "Invalid LLD override operation id \"%s\".\n", operationid_db);
		return NULL;
	}

	if (NULL == (opmap = (zbx_lld_operation_map_t *)zbx_hashset_search(operations, &operationid)))
	{
		*info = zbx_strdcat(*info, "Cannot update LLD override operation id.\n");
		return NULL;
	}

	return opmap->dst_opid;
}

/******************************************************************************
 *                                                                            *
 * Purpose: merge LLD base override data from source to destination row       *
 *                                                                            *
 * Parameters: dst_row - [IN/OUT] destination row                             *
 *             src_row - [IN] source row                                      *
 *                                                                            *
 ******************************************************************************/
static void	lld_rule_override_merge_base(zbx_sync_row_t *dst_row, const zbx_sync_row_t *src_row)
{
	zbx_lld_override_data_t	*dst, *src;

	if (NULL == dst_row->data)
	{
		dst = lld_override_data_create(0);
		dst_row->data = dst;
	}
	else
		dst = (zbx_lld_override_data_t *)dst_row->data;

	src = (zbx_lld_override_data_t *)src_row->data;

	zbx_sync_rowset_merge(&dst->conditions, &src->conditions);
	zbx_sync_rowset_merge(&dst->operations, &src->operations);
}

static void	lld_rule_override_merge_rowset(zbx_sync_rowset_t *dst, const zbx_sync_rowset_t *src,
		int col, const zbx_hashset_t *opmap, char **info)
{
	zbx_sync_rowset_t	rowset;

	/* before merging make temporary rowset and replace prototype operation IDs with real operation IDs */

	zbx_sync_rowset_init(&rowset, dst->cols_num);
	zbx_sync_rowset_copy(&rowset, src);

	for (int i = 0; i < rowset.rows.values_num; i++)
	{
		zbx_sync_row_t	*row = rowset.rows.values[i];
		const char	*depid;

		if (0 == (row->flags & (ZBX_SYNC_ROW_INSERT | ZBX_SYNC_ROW_UPDATE)))
			continue;

		if (NULL == (depid = lld_override_remap_operationid(opmap, row->cols[col], info)))
			return;

		row->cols[col] = zbx_strdup(row->cols[col], depid);
	}

	zbx_sync_rowset_sort_by_rows(&rowset);
	zbx_sync_rowset_merge(dst, &rowset);
	zbx_sync_rowset_clear(&rowset);
}

/******************************************************************************
 *                                                                            *
 * Purpose: merge dependent LLD override data from source to destination row  *
 *                                                                            *
 * Parameters: dst_row - [IN/OUT] destination row                             *
 *             src_row - [IN] source row                                      *
 *                                                                            *
 ******************************************************************************/
static void	lld_rule_override_merge_deps(zbx_sync_row_t *dst_row, const zbx_sync_row_t *src_row, char **info)
{
	zbx_lld_override_data_t		*dst = (zbx_lld_override_data_t *)dst_row->data;
	const zbx_lld_override_data_t	*src = (const zbx_lld_override_data_t *)src_row->data;
	zbx_hashset_t			opmap;

	zbx_hashset_create(&opmap, (size_t)dst->operations.rows.values_num, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	for (int i = 0; i < dst->operations.rows.values_num; i++)
	{
		zbx_sync_row_t		*row = dst->operations.rows.values[i];
		zbx_lld_operation_map_t	map_local ;

		map_local.src_opid = row->parent_rowid;
		zbx_snprintf(map_local.dst_opid, sizeof(map_local.dst_opid), ZBX_FS_UI64, row->rowid);
		zbx_hashset_insert(&opmap, &map_local, sizeof(map_local));
	}

	lld_rule_override_merge_rowset(&dst->optags, &src->optags, LLD_OVERRIDE_OPTAG_COL_OPID, &opmap, info);
	lld_rule_override_merge_rowset(&dst->optemplates, &src->optemplates, LLD_OVERRIDE_OPTEMPLATE_COL_OPID, &opmap,
			info);

	zbx_hashset_destroy(&opmap);
}

/******************************************************************************
 *                                                                            *
 * Purpose: roll back changes to LLD override formula and related data        *
 *                                                                            *
 * Parameters: row - [IN/OUT] LLD override row to roll back                   *
 *                                                                            *
 * Return value: SUCCEED - formula was rolled back                            *
 *               FAIL    - formula cannot be rolled back (new override)       *
 *                                                                            *
 ******************************************************************************/
static int	lld_override_rollback_formula(zbx_sync_row_t *row)
{
	zbx_lld_override_data_t        *data = (zbx_lld_override_data_t *)row->data;

	if (0 != (row->flags & ZBX_SYNC_ROW_INSERT))
		return FAIL;

	row->flags = ZBX_SYNC_ROW_NONE;
	zbx_sync_rowset_rollback(&data->operations);
	zbx_sync_rowset_rollback(&data->conditions);
	zbx_sync_rowset_rollback(&data->optags);
	zbx_sync_rowset_rollback(&data->optemplates);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: update LLD override formula with remapped condition IDs           *
 *                                                                            *
 * Parameters: dst_row - [IN/OUT] destination override row                    *
 *             src_row - [IN] source override row                             *
 *             info    - [OUT] error message                                  *
 *                                                                            *
 * Return value: SUCCEED - formula was updated successfully                   *
 *               FAIL    - formula update failed                              *
 *                                                                            *
 * Comments: This function takes override formula copied from LLD rule        *
 *           prototype and replaces prototype condition IDs with actual LLD   *
 *           rule condition IDs. If the new formula matches the old, then     *
 *           the formula column update is discarded.                          *
 *                                                                            *
 ******************************************************************************/
static int	lld_override_update_formula(zbx_sync_row_t *dst_row, const zbx_sync_row_t *src_row, char **info)
{
	zbx_lld_override_data_t	*dst = (zbx_lld_override_data_t *)dst_row->data;
	char			*error = NULL, *formula = NULL;
	zbx_eval_context_t	ctx;
	int			ret;

	if (ZBX_CONDITION_EVAL_TYPE_EXPRESSION != atoi(src_row->cols[LLD_OVERRIDE_COL_EVALTYPE]))
		return SUCCEED;

	if (SUCCEED != zbx_eval_parse_expression(&ctx, src_row->cols[LLD_OVERRIDE_COL_FORMULA],
			ZBX_EVAL_PARSE_LLD_FILTER_EXPRESSION, &error))
	{
		*info = zbx_strdcatf(*info, "Cannot parse LLD override \"%s\" filter formula: %s.\n",
				src_row->cols[LLD_OVERRIDE_COL_NAME], error);
		zbx_free(error);

		return lld_override_rollback_formula(dst_row);
	}

	for (int j = 0; j < ctx.stack.values_num; j++)
	{
		zbx_eval_token_t	*token = &ctx.stack.values[j];
		zbx_uint64_t		conditionid;
		int			k;

		if (ZBX_EVAL_TOKEN_FUNCTIONID != token->type)
			continue;

		if (SUCCEED != zbx_is_uint64_n(ctx.expression + token->loc.l + 1,
				token->loc.r - token->loc.l - 1, &conditionid))
		{
			*info = zbx_strdcatf(*info, "Invalid LLD override filter formula starting with \"%s\".\n",
					ctx.expression + token->loc.l);

			ret = lld_override_rollback_formula(dst_row);
			goto out;
		}

		for (k = 0; k < dst->conditions.rows.values_num; k++)
		{
			zbx_sync_row_t        *row = dst->conditions.rows.values[k];

			if (row->parent_rowid == conditionid)
			{
				zbx_variant_set_str(&token->value, zbx_dsprintf(NULL, "{" ZBX_FS_UI64 "}", row->rowid));
				break;
			}
		}

		if (k == dst->conditions.rows.values_num)
		{
			*info = zbx_strdcat(*info, "Cannot update custom LLD filter.\n");
			ret = lld_override_rollback_formula(dst_row);
			goto out;
		}
	}

	zbx_eval_compose_expression(&ctx, &formula);

	/* when syncing rowsets the formula was copied from prototype, so need to check the original value */
	if (NULL == dst_row->cols_orig[LLD_OVERRIDE_COL_FORMULA] ||
			0 != strcmp(dst_row->cols_orig[LLD_OVERRIDE_COL_FORMULA], formula))
	{
		zbx_free(dst_row->cols[LLD_OVERRIDE_COL_FORMULA]);
		dst_row->cols[LLD_OVERRIDE_COL_FORMULA] = formula;
	}
	else
	{
		zbx_sync_row_rollback_col(dst_row, LLD_OVERRIDE_COL_FORMULA);
		zbx_free(formula);
	}

	ret = SUCCEED;
out:
	zbx_eval_clear(&ctx);

	return ret;
}

typedef struct
{
	zbx_sync_row_t		*dst;
	const zbx_sync_row_t	*src;
}
zbx_sync_row_pair_t;

ZBX_VECTOR_DECL(sync_row_pair, zbx_sync_row_pair_t)
ZBX_VECTOR_IMPL(sync_row_pair, zbx_sync_row_pair_t)

/******************************************************************************
 *                                                                            *
 * Purpose: updates existing LLD rule overrides and creates new ones based    *
 *          on rule prototypes.                                               *
 *                                                                            *
 * Parameters: items           - [IN/OUT] sorted list of items                *
 *             info            - [OUT] error message                          *
 *                                                                            *
 ******************************************************************************/
void	lld_rule_overrides_make(zbx_vector_lld_item_full_ptr_t *items, char **info)
{
	zbx_vector_sync_row_pair_t	overrides;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_sync_row_pair_create(&overrides);

	for (int i = 0; i < items->values_num; i++)
	{
		zbx_lld_item_full_t	*item = items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		if (0 == (item->prototype->item_flags & ZBX_FLAG_DISCOVERY_RULE))
			continue;

		zbx_sync_rowset_merge(&item->overrides, &item->prototype->overrides);

		for (int j = 0; j < item->prototype->overrides.rows.values_num; j++)
		{
			zbx_sync_row_pair_t	pair;

			pair.src = item->prototype->overrides.rows.values[j];

			if (NULL == (pair.dst = zbx_sync_rowset_search_by_parent(&item->overrides, pair.src->rowid)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			zbx_vector_sync_row_pair_append(&overrides, pair);
		}
	}

	for (int i = 0; i < overrides.values_num; i++)
		lld_rule_override_merge_base(overrides.values[i].dst, overrides.values[i].src);

	lld_rule_overrides_set_base_ids(items);

	for (int i = 0; i < overrides.values_num; i++)
	{
		lld_override_update_formula(overrides.values[i].dst, overrides.values[i].src, info);
		lld_rule_override_merge_deps(overrides.values[i].dst, overrides.values[i].src, info);
	}

	lld_rule_overrides_set_dep_ids(items);

	zbx_vector_sync_row_pair_destroy(&overrides);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: fetch LLD rule prototype macro paths from database                *
 *                                                                            *
 * Parameters: item_prototypes - [IN/OUT] vector of LLD rule prototypes       *
 *             protoids        - [IN] vector of prototype IDs to fetch        *
 *                                                                            *
 ******************************************************************************/
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

	for (int i = 0; i < item_prototypes->values_num; i++)
		zbx_sync_rowset_sort_by_rows(&item_prototypes->values[i]->macro_paths);
}

/******************************************************************************
 *                                                                            *
 * Purpose: fetch LLD rule prototype filters from database                    *
 *                                                                            *
 * Parameters: item_prototypes - [IN/OUT] vector of LLD rule prototypes       *
 *             protoids        - [IN] vector of prototype IDs to fetch        *
 *                                                                            *
 ******************************************************************************/
void	lld_rule_get_prototype_filters(zbx_vector_lld_item_prototype_ptr_t *item_prototypes,
		zbx_vector_uint64_t *protoids)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select item_conditionid,itemid,operator,macro,value"
			" from item_condition where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", protoids->values, protoids->values_num);

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

		zbx_sync_rowset_add_row(&item_prototypes->values[index]->filters, row[0], row[2], row[3], row[4]);
	}
	zbx_db_free_result(result);

	for (int i = 0; i < item_prototypes->values_num; i++)
		zbx_sync_rowset_sort_by_rows(&item_prototypes->values[i]->filters);

}

/******************************************************************************
 *                                                                            *
 * Purpose: fetch additional LLD override data from database                  *
 *                                                                            *
 * Parameters: overrides - [IN/OUT] vector of override data to populate       *
 *                                                                            *
 ******************************************************************************/
void	lld_rule_fetch_override_data(zbx_vector_lld_override_data_ptr_t *overrides)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	overrideids;

	zbx_vector_uint64_create(&overrideids);

	zbx_vector_lld_override_data_ptr_sort(overrides, lld_override_data_compare);

	for (int i = 0; i < overrides->values_num; i++)
		zbx_vector_uint64_append(&overrideids, overrides->values[i]->overrideid);

	/* fetch override conditions */

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select lld_override_conditionid,lld_overrideid,operator,"
			"macro,value from lld_override_condition where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "lld_overrideid", overrideids.values,
			overrideids.values_num);

	result = zbx_db_select("%s", sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_lld_override_data_t	override_local;
		int			index;

		ZBX_STR2UINT64(override_local.overrideid, row[1]);
		if (FAIL == (index = zbx_vector_lld_override_data_ptr_bsearch(overrides, &override_local,
				lld_override_data_compare)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		zbx_sync_rowset_add_row(&overrides->values[index]->conditions, row[0], row[2], row[3], row[4]);
	}
	zbx_db_free_result(result);

	/* fetch override operations */

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select o.lld_override_operationid,o.lld_overrideid,o.operationobject,o.operator,o.value,"
				"od.discover,oh.history,oi.inventory_mode,op.delay,os.severity,ost.status,ot.trends"
			" from lld_override_operation o"
				" left join lld_override_opdiscover od"
					" on o.lld_override_operationid=od.lld_override_operationid"
				" left join lld_override_ophistory oh"
					" on o.lld_override_operationid=oh.lld_override_operationid"
				" left join lld_override_opinventory oi"
					" on o.lld_override_operationid=oi.lld_override_operationid"
				" left join lld_override_opperiod op"
					" on o.lld_override_operationid=op.lld_override_operationid"
				" left join lld_override_opseverity os"
					" on o.lld_override_operationid=os.lld_override_operationid"
				" left join lld_override_opstatus ost"
					" on o.lld_override_operationid=ost.lld_override_operationid"
				" left join lld_override_optrends ot"
					" on o.lld_override_operationid=ot.lld_override_operationid"
			" where");

	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "lld_overrideid", overrideids.values,
			overrideids.values_num);

	result = zbx_db_select("%s", sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_lld_override_data_t	override_local;
		int			index;

		ZBX_STR2UINT64(override_local.overrideid, row[1]);
		if (FAIL == (index = zbx_vector_lld_override_data_ptr_bsearch(overrides, &override_local,
				lld_override_data_compare)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		zbx_sync_rowset_add_row(&overrides->values[index]->operations, row[0], row[2], row[3], row[4], row[5],
				row[6], row[7], row[8], row[9], row[10], row[11]);
	}
	zbx_db_free_result(result);

	/* fetch operation tags */

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select ot.lld_override_optagid,o.lld_overrideid,ot.lld_override_operationid,ot.tag,ot.value"
			" from lld_override_operation o"
				" join lld_override_optag ot"
					" on o.lld_override_operationid=ot.lld_override_operationid"
			" where");

	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "o.lld_overrideid", overrideids.values,
			overrideids.values_num);

	result = zbx_db_select("%s", sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_lld_override_data_t	override_local;
		int			index;

		ZBX_STR2UINT64(override_local.overrideid, row[1]);
		if (FAIL == (index = zbx_vector_lld_override_data_ptr_bsearch(overrides, &override_local,
				lld_override_data_compare)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		zbx_sync_rowset_add_row(&overrides->values[index]->optags, row[0], row[2], row[3], row[4]);
	}
	zbx_db_free_result(result);

	/* fetch operation templates */

	sql_offset = 0;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select ot.lld_override_optemplateid,o.lld_overrideid,ot.lld_override_operationid,ot.templateid"
			" from lld_override_operation o"
				" join lld_override_optemplate ot"
					" on o.lld_override_operationid=ot.lld_override_operationid"
			" where");

	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "o.lld_overrideid", overrideids.values,
			overrideids.values_num);

	result = zbx_db_select("%s", sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_lld_override_data_t	override_local;
		int			index;

		ZBX_STR2UINT64(override_local.overrideid, row[1]);
		if (FAIL == (index = zbx_vector_lld_override_data_ptr_bsearch(overrides, &override_local,
				lld_override_data_compare)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		zbx_sync_rowset_add_row(&overrides->values[index]->optemplates, row[0], row[2], row[3]);
	}
	zbx_db_free_result(result);

	for (int i = 0; i < overrides->values_num; i++)
	{
		zbx_sync_rowset_sort_by_rows(&overrides->values[i]->conditions);
		zbx_sync_rowset_sort_by_rows(&overrides->values[i]->operations);
		zbx_sync_rowset_sort_by_rows(&overrides->values[i]->optags);
		zbx_sync_rowset_sort_by_rows(&overrides->values[i]->optemplates);
	}

	zbx_free(sql);
	zbx_vector_uint64_destroy(&overrideids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: fetch LLD rule prototype overrides from database                  *
 *                                                                            *
 * Parameters: item_prototypes - [IN/OUT] vector of LLD rule prototypes       *
 *             protoids        - [IN] vector of prototype IDs to fetch        *
 *                                                                            *
 ******************************************************************************/
void	lld_rule_get_prototype_overrides(zbx_vector_lld_item_prototype_ptr_t *item_prototypes,
		zbx_vector_uint64_t *protoids)
{
	zbx_db_result_t				result;
	zbx_db_row_t				row;
	char					*sql = NULL;
	size_t					sql_alloc = 0, sql_offset = 0;
	zbx_vector_lld_override_data_ptr_t	overrides;

	zbx_vector_lld_override_data_ptr_create(&overrides);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select lld_overrideid,itemid,name,step,stop,evaltype,formula"
			" from lld_override where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "itemid", protoids->values, protoids->values_num);

	result = zbx_db_select("%s", sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_lld_item_prototype_t	item_prototype_local;
		int				index;
		zbx_sync_row_t			*sync_row;
		zbx_lld_override_data_t		*data;

		ZBX_STR2UINT64(item_prototype_local.itemid, row[1]);

		if (FAIL == (index = zbx_vector_lld_item_prototype_ptr_bsearch(item_prototypes, &item_prototype_local,
				lld_item_prototype_compare_func)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		sync_row = zbx_sync_rowset_add_row(&item_prototypes->values[index]->overrides, row[0], row[2], row[3],
				row[4], row[5], row[6]);
		data = lld_override_data_create(sync_row->rowid);
		sync_row->data = data;

		zbx_vector_lld_override_data_ptr_append(&overrides, data);
	}
	zbx_db_free_result(result);

	for (int i = 0; i < item_prototypes->values_num; i++)
		zbx_sync_rowset_sort_by_rows(&item_prototypes->values[i]->overrides);

	if (0 != overrides.values_num)
		lld_rule_fetch_override_data(&overrides);

	zbx_free(sql);
	zbx_vector_lld_override_data_ptr_destroy(&overrides);
}

static zbx_hash_t	lld_row_index_hash(const void *v)
{
	const zbx_lld_row_ruleid_t	*index = (const zbx_lld_row_ruleid_t *)v;

	return ZBX_DEFAULT_STRING_HASH_ALGO(&index->data, sizeof(index->data), 0);
}

static int	lld_row_index_compare(const void *v1, const void *v2)
{
	const zbx_lld_row_ruleid_t        *index1 = (const zbx_lld_row_ruleid_t *)v1;
	const zbx_lld_row_ruleid_t        *index2 = (const zbx_lld_row_ruleid_t *)v2;

	ZBX_RETURN_IF_NOT_EQUAL(index1->data, index2->data);

	return 0;
}

typedef struct
{
	zbx_uint64_t			itemid;
	zbx_hashset_t			rule_index;
	zbx_vector_lld_row_ptr_t	lld_rows;
	const zbx_lld_item_prototype_t	*prototype;
}
zbx_lld_prototype_rules_t;

ZBX_PTR_VECTOR_DECL(lld_prototype_rules_ptr, zbx_lld_prototype_rules_t *)
ZBX_PTR_VECTOR_IMPL(lld_prototype_rules_ptr, zbx_lld_prototype_rules_t *)

static int	lld_prototype_rules_compare(const void *v1, const void *v2)
{
	const zbx_lld_prototype_rules_t        *pi1 = *(const zbx_lld_prototype_rules_t * const *)v1;
	const zbx_lld_prototype_rules_t        *pi2 = *(const zbx_lld_prototype_rules_t * const *)v2;

	ZBX_RETURN_IF_NOT_EQUAL(pi1->itemid, pi2->itemid);

	return 0;
}

static void	lld_prototype_rules_clear(void *v)
{
	zbx_lld_prototype_rules_t	*pi = (zbx_lld_prototype_rules_t *)v;

	zbx_hashset_destroy(&pi->rule_index);

	zbx_vector_lld_row_ptr_clear_ext(&pi->lld_rows, lld_row_free);
	zbx_vector_lld_row_ptr_destroy(&pi->lld_rows);
}

/* lld rule index by lld rows for macro export */
typedef struct
{
	const zbx_lld_row_t	*lld_row;
	zbx_vector_uint64_t	itemids;
}
zbx_lld_row_itemids_t;

static zbx_hash_t	lld_row_itemids_hash(const void *v)
{
	const zbx_lld_row_itemids_t	*index = (const zbx_lld_row_itemids_t *)v;

	return ZBX_DEFAULT_STRING_HASH_ALGO(&index->lld_row, sizeof(index->lld_row), 0);
}

static int	lld_row_itemids_compare(const void *v1, const void *v2)
{
	const zbx_lld_row_itemids_t        *index1 = (const zbx_lld_row_itemids_t *)v1;
	const zbx_lld_row_itemids_t        *index2 = (const zbx_lld_row_itemids_t *)v2;

	ZBX_RETURN_IF_NOT_EQUAL(index1->lld_row, index2->lld_row);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: export LLD macros for discovered LLD rules                        *
 *                                                                            *
 * Parameters: items - [IN] vector of discovered LLD rules                    *
 *                                                                            *
 ******************************************************************************/
static void	lld_rule_export_lld_macros(const zbx_vector_lld_item_full_ptr_t *items)
{
	zbx_hashset_t		exported;
	zbx_hashset_iter_t	iter;
	zbx_lld_row_itemids_t	index_local, *index;

	zbx_hashset_create(&exported, (size_t )items->values_num, lld_row_itemids_hash, lld_row_itemids_compare);

	for (int i = 0; i < items->values_num; i++)
	{
		const zbx_lld_item_full_t	*item = items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		if (0 == (item->prototype->item_flags & ZBX_FLAG_DISCOVERY_RULE))
			continue;

		index_local.lld_row = item->lld_row;
		if (NULL == (index = (zbx_lld_row_itemids_t *)zbx_hashset_search(&exported, &index_local)))
		{
			index = (zbx_lld_row_itemids_t *)zbx_hashset_insert(&exported, &index_local,
					sizeof(index_local));
			zbx_vector_uint64_create(&index->itemids);
		}

		zbx_vector_uint64_append(&index->itemids, item->itemid);
	}

	zbx_hashset_iter_reset(&exported, &iter);
	while (NULL != (index = (zbx_lld_row_itemids_t *)zbx_hashset_iter_next(&iter)))
	{
		lld_sync_exported_macros(&index->itemids, index->lld_row->data);
		zbx_vector_uint64_destroy(&index->itemids);
	}

	zbx_hashset_destroy(&exported);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process nested LLD rules                                          *
 *                                                                            *
 * Parameters: items           - [IN] vector of discovered LLD items          *
 *                                                                            *
 * Comments: Nested LLD in this scope is an LLD rule of nested item type      *
 *                                                                            *
 ******************************************************************************/
static void	lld_rule_process_nested_rules(const zbx_vector_lld_item_full_ptr_t *items)
{
	for (int i = 0; i < items->values_num; i++)
	{
		const zbx_lld_item_full_t	*item = items->values[i];

		if (0 == (item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		if (ITEM_TYPE_NESTED_LLD != item->prototype->type ||
				0 == (item->prototype->item_flags & ZBX_FLAG_DISCOVERY_RULE))
		{
			continue;
		}

		lld_rule_process_nested_rule(item->itemid, item->lld_row);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: perform LLD rule discovery                                        *
 *                                                                            *
 * Parameters: hostid           - [IN] host identifier                        *
 *             lld_ruleid       - [IN] LLD rule identifier                    *
 *             lld_rows         - [IN] discovery data rows                    *
 *             error            - [OUT] error message                         *
 *             lastcheck        - [IN] timestamp of the last check            *
 *             rule_index       - [IN] mapping of LLD rows to discovered LLD  *
 *                                    rules                                   *
 *                                                                            *
 * Return value: SUCCEED - rules updated successfully                         *
 *               FAIL    - an error occurred                                  *
 *                                                                            *
 ******************************************************************************/
int	lld_rule_discover_prototypes(zbx_uint64_t hostid, const zbx_vector_lld_row_ptr_t *lld_rows,
		const zbx_vector_lld_item_prototype_ptr_t *item_prototypes, zbx_vector_lld_item_full_ptr_t *items,
		char **error, int lastcheck, zbx_hashset_t *items_index)
{
	int					ret = SUCCEED;
	zbx_hashset_t				prototype_rules;
	zbx_hashset_iter_t			iter;
	zbx_vector_lld_prototype_rules_ptr_t	prototype_rules_sorted;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	zbx_vector_lld_prototype_rules_ptr_create(&prototype_rules_sorted);

	zbx_hashset_create_ext(&prototype_rules, (size_t)item_prototypes->values_num, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC, lld_prototype_rules_clear,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	lld_rule_export_lld_macros(items);
	lld_rule_process_nested_rules(items);

	/* prepare remapping of prototype ids to lld rule ids for discovered item prototypes */

	zbx_lld_item_index_t	*item_index;

	zbx_hashset_iter_reset(items_index, &iter);
	while (NULL != (item_index = (zbx_lld_item_index_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_lld_prototype_rules_t	prules_local, *prules;
		zbx_lld_row_ruleid_t		row_ruleid_local;

		if (0 == (item_index->item->flags & ZBX_FLAG_LLD_ITEM_DISCOVERED))
			continue;

		if (0 == (item_index->item->prototype->item_flags & ZBX_FLAG_DISCOVERY_RULE))
			continue;

		prules_local.itemid = item_index->parent_itemid;
		if (NULL == (prules = (zbx_lld_prototype_rules_t *)zbx_hashset_search(&prototype_rules, &prules_local)))
		{
			prules = (zbx_lld_prototype_rules_t *)zbx_hashset_insert(&prototype_rules, &prules_local,
					sizeof(prules_local));
			zbx_hashset_create(&prules->rule_index, (size_t )lld_rows->values_num, lld_row_index_hash,
					lld_row_index_compare);

			prules->prototype = item_index->item->prototype;
			zbx_vector_lld_row_ptr_create(&prules->lld_rows);
			zbx_vector_lld_row_ptr_reserve(&prules->lld_rows, (size_t)lld_rows->values_num);

			zbx_vector_lld_prototype_rules_ptr_append(&prototype_rules_sorted, prules);
		}

		row_ruleid_local.ruleid = item_index->item->itemid;
		row_ruleid_local.data = item_index->lld_row->data;

		zbx_lld_row_t		*copy;

		copy = (zbx_lld_row_t *)zbx_malloc(NULL, sizeof(zbx_lld_row_t));
		copy->data = item_index->lld_row->data;
		zbx_vector_lld_item_link_ptr_create(&copy->item_links);
		zbx_vector_lld_override_ptr_create(&copy->overrides);
		zbx_vector_lld_row_ptr_append(&prules->lld_rows, copy);

		zbx_hashset_insert(&prules->rule_index, &row_ruleid_local, sizeof(row_ruleid_local));
	}

	/* discovery corresponding LLD rule prototypes */

	zbx_lld_lifetime_t	lifetime = {ZBX_LLD_LIFETIME_TYPE_IMMEDIATELY, 0},
				enabled_lifetime = {ZBX_LLD_LIFETIME_TYPE_NEVER, 0};
	zbx_vector_uint64_t	ruleids;

	zbx_vector_uint64_create(&ruleids);

	zbx_vector_lld_prototype_rules_ptr_sort(&prototype_rules_sorted, lld_prototype_rules_compare);
	for (int i = 0; i < prototype_rules_sorted.values_num; i++)
	{
		zbx_lld_prototype_rules_t	*prules = prototype_rules_sorted.values[i];
		const zbx_lld_item_prototype_t	*item_prototype = prules->prototype;
		zbx_lld_row_ruleid_t		*row_ruleid;

		zbx_hashset_iter_reset(&prules->rule_index, &iter);
		while (NULL != (row_ruleid = (zbx_lld_row_ruleid_t *)zbx_hashset_iter_next(&iter)))
			zbx_vector_uint64_append(&ruleids, row_ruleid->ruleid);

		zbx_vector_uint64_sort(&ruleids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		if (FAIL == lld_update_items(hostid, item_prototype->itemid,  &prules->lld_rows, error, &lifetime,
				&enabled_lifetime, lastcheck, ZBX_FLAG_DISCOVERY_PROTOTYPE, &prules->rule_index,
				&ruleids))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot update/add items because parent host was removed while"
					" processing lld rule");
			goto out;
		}

		lld_item_links_sort(&prules->lld_rows);

		if (SUCCEED != lld_update_triggers(hostid, item_prototype->itemid, &prules->lld_rows, error, &lifetime,
				&enabled_lifetime, lastcheck, ZBX_FLAG_DISCOVERY_PROTOTYPE, &ruleids))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot update/add triggers because parent host was removed while"
					" processing lld rule");
			goto out;
		}

		if (SUCCEED != lld_update_graphs(hostid, item_prototype->itemid, &prules->lld_rows, error, &lifetime,
				lastcheck, ZBX_FLAG_DISCOVERY_PROTOTYPE, &ruleids))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "cannot update/add graphs because parent host was removed while"
					" processing lld rule");
			goto out;
		}

		lld_update_hosts(item_prototype->itemid, &prules->lld_rows, error, &lifetime, &enabled_lifetime,
				lastcheck, ZBX_FLAG_DISCOVERY_PROTOTYPE, &prules->rule_index, &ruleids);

		zbx_vector_uint64_clear(&ruleids);
	}

	ret = SUCCEED;
out:
	zbx_vector_uint64_destroy(&ruleids);

	zbx_hashset_destroy(&prototype_rules);
	zbx_vector_lld_prototype_rules_ptr_destroy(&prototype_rules_sorted);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Process a nseted LLD rule                                         *
 *                                                                            *
 * Parameters: itemid  - [IN] ID of the item to process                       *
 *             lld_row - [IN] LLD row containing discovery data               *
 *                                                                            *
 * Comments: Nested LLD in this scope is an LLD rule of nested item type      *
 *                                                                            *
 ******************************************************************************/
void	lld_rule_process_nested_rule(zbx_uint64_t itemid, const zbx_lld_row_t *lld_row)
{
	char		*value = NULL;
	size_t		value_alloc = 0, value_offset = 0;
	AGENT_RESULT	result;
	zbx_timespec_t	ts;

	zbx_jsonobj_to_string(&value, &value_alloc, &value_offset, lld_row->data->source);

	zbx_init_agent_result(&result);
	SET_TEXT_RESULT(&result, value);

	zbx_timespec(&ts);

	zbx_preprocess_item_value(itemid, ITEM_VALUE_TYPE_TEXT, ZBX_FLAG_DISCOVERY_RULE,
			ZBX_ITEM_REQUIRES_PREPROCESSING_YES, &result, &ts, ITEM_STATE_NORMAL, NULL);
	zbx_preprocessor_flush();

	zbx_free(value);
}
