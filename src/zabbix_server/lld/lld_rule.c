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

static void	lld_rule_macros_clear(void *d)
{
	zbx_lld_rule_macros_t	*rule_macros = (zbx_lld_rule_macros_t *)d;

	for (int i = 0; i < rule_macros->macros.values_num; i++)
	{
		zbx_free(rule_macros->macros.values[i].name);
		zbx_free(rule_macros->macros.values[i].value);
	}

	zbx_vector_lld_ext_macro_destroy(&rule_macros->macros);
}

/******************************************************************************
 *                                                                            *
 * Purpose: merge exported macros for LLD rule                                *
 *                                                                            *
 * Parameters: rule_macros - [IN/OUT] LLD rule macros                         *
 *             entry       - [IN] LLD entry macros                            *
 *                                                                            *
 * Return value: Merged list of macros containing:                            *
 *               1) macros to update (lld_macroid set, new name/value)        *
 *               2) macros to insert (lld_macroid 0, new name/value)          *
 *               3) macros to remove (lld_macroid set, NULL name/value)       *
 *                                                                            *
 ******************************************************************************/
static void	lld_rule_merge_exported_macros(zbx_vector_lld_ext_macro_t *rule_macros, zbx_vector_lld_macro_t *entry)
{
	zbx_vector_lld_macro_t	macros;
	int			i, j;

	zbx_vector_lld_ext_macro_sort(rule_macros, lld_ext_macro_compare);

	zbx_vector_lld_macro_create(&macros);
	zbx_vector_lld_macro_append_array(&macros, entry->values, entry->values_num);

	/* remove matching macros from both vectors */
	for (i = macros.values_num - 1, j = rule_macros->values_num - 1; 0 <= i && 0 <= j; )
	{
		int	ret;

		if (0 == (ret = strcmp(macros.values[i].macro, rule_macros->values[j].name)))
		{
			if (0 == strcmp(macros.values[i].value, rule_macros->values[j].value))
			{
				zbx_vector_lld_macro_remove(&macros, i);
				zbx_vector_lld_ext_macro_remove(rule_macros, j);

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
	for (j = 0; j < rule_macros->values_num; j++)
	{
		zbx_free(rule_macros->values[j].name);
		zbx_free(rule_macros->values[j].value);
	}

	if (macros.values_num > rule_macros->values_num)
	{
		zbx_vector_lld_ext_macro_reserve(rule_macros, (size_t)macros.values_num);

		for (i = rule_macros->values_num; i < macros.values_num; i++)
		{
			zbx_lld_ext_macro_t	macro = {0};

			zbx_vector_lld_ext_macro_append(rule_macros, macro);
		}
	}

	for (i = 0; i < macros.values_num; i++)
	{
		rule_macros->values[i].name = zbx_strdup(NULL, macros.values[i].macro);
		rule_macros->values[i].value = zbx_strdup(NULL, macros.values[i].value);
	}

	zbx_vector_lld_macro_destroy(&macros);
}


void	lld_sync_exported_macros(const zbx_vector_uint64_t *ruleids, zbx_lld_entry_t *entry)
{
	zbx_db_row_t		row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_hashset_t		lld_rules;
	zbx_db_large_query_t	query;
	zbx_hashset_iter_t	iter;
	zbx_lld_rule_macros_t	*rule_macros;

	zbx_hashset_create_ext(&lld_rules, (size_t)ruleids->values_num, ZBX_DEFAULT_UINT64_HASH_FUNC,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC, lld_rule_macros_clear, ZBX_DEFAULT_MEM_MALLOC_FUNC,
			ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select lld_macroid,itemid,name,value from lld_macro where");

	zbx_db_large_query_prepare_uint(&query, &sql, &sql_alloc, &sql_offset, "itemid", ruleids);

	while (NULL != (row = zbx_db_large_query_fetch(&query)))
	{
		zbx_lld_rule_macros_t	rm_local;
		int			num_data = lld_rules.num_data;
		zbx_lld_ext_macro_t	macro;

		ZBX_STR2UINT64(rm_local.itemid, row[1]);
		rule_macros = (zbx_lld_rule_macros_t *)zbx_hashset_insert(&lld_rules, &rm_local, sizeof(rm_local));
		if (num_data != lld_rules.num_data)
			zbx_vector_lld_ext_macro_create(&rule_macros->macros);

		ZBX_STR2UINT64(macro.lld_macroid, row[0]);
		macro.name = zbx_strdup(NULL, row[2]);
		macro.value = zbx_strdup(NULL, row[3]);

		zbx_vector_lld_ext_macro_append(&rule_macros->macros, macro);
	}
	zbx_db_large_query_clear(&query);
	zbx_free(sql);

	zbx_hashset_iter_reset(&lld_rules, &iter);
	while (NULL != (rule_macros = (zbx_lld_rule_macros_t *)zbx_hashset_iter_next(&iter)))
		lld_rule_merge_exported_macros(&rule_macros->macros, &entry->macros);

	zbx_hashset_destroy(&lld_rules);
}
