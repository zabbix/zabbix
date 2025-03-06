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
