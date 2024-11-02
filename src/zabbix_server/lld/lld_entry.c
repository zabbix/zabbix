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

#include "lld.h"

#include "zbxalgo.h"
#include "zbxjson.h"

ZBX_VECTOR_IMPL(lld_macro, zbx_lld_macro_t);

static void	lld_macro_clear(zbx_lld_macro_t *macro)
{
	zbx_free(macro->macro);
	zbx_free(macro->value);
}

static int	lld_macro_compare(const void *d1, const void *d2)
{
	const zbx_lld_macro_t	*m1 = (const zbx_lld_macro_t *)d1;
	const zbx_lld_macro_t	*m2 = (const zbx_lld_macro_t *)d2;

	return strcmp(m1->macro, m2->macro);
}

/******************************************************************************
 *                                                                            *
 * Purpose: entries hashset support                                           *
 *                                                                            *
 ******************************************************************************/
zbx_hash_t	lld_entry_hash(const void *data)
{
	const zbx_lld_entry_t	*entry = (zbx_lld_entry_t *)data;
	zbx_hash_t		hash = 0;

	for (int i = 0; i < entry->macros.values_num; i++)
	{
		const char	*value = entry->macros.values[i].value;
		hash = ZBX_DEFAULT_STRING_HASH_ALGO(value, strlen(value), hash);
	}

	return hash;
}

int        lld_entry_compare(const void *d1, const void *d2)
{
	const zbx_lld_entry_t	*e1 = (zbx_lld_entry_t *)d1;
	const zbx_lld_entry_t	*e2 = (zbx_lld_entry_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(e1->macros.values_num, e2->macros.values_num);

	for (int i = 0; i < e1->macros.values_num; i++)
	{
		int	ret;

		if (0 != (ret = strcmp(e1->macros.values[i].macro, e2->macros.values[i].macro)))
			return ret;

		if (0 != (ret = strcmp(e1->macros.values[i].value, e2->macros.values[i].value)))
			return ret;
	}

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create lld entry (row)                                            *
 *                                                                            *
 ******************************************************************************/
static void	lld_entry_create(zbx_lld_entry_t *entry, const zbx_jsonobj_t *obj,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths)
{
	size_t	size;

	if (0 < lld_macro_paths->values_num)
		size = lld_macro_paths->values_num;
	else
		size = 5;

	zbx_vector_lld_macro_create(&entry->macros);
	zbx_vector_lld_macro_reserve(&entry->macros, size);

	for (int i = 0; i < lld_macro_paths->values_num; i++)
	{
		zbx_lld_macro_t			lld_macro;
		const zbx_lld_macro_path_t	*macro_path = lld_macro_paths->values[i];
		char				*value = NULL;

		if (SUCCEED != zbx_jsonobj_query(obj, macro_path->path, &value) || NULL == value)
			continue;

		lld_macro.macro = zbx_strdup(NULL, macro_path->lld_macro);
		lld_macro.value = value;

		zbx_vector_lld_macro_append(&entry->macros, lld_macro);
	}

	if (ZBX_JSON_TYPE_OBJECT == obj->type)
	{
		zbx_hashset_const_iter_t	iter;
		const zbx_jsonobj_el_t		*el;
		zbx_lld_macro_t			lld_macro;
		char				buf[ZBX_MAX_DOUBLE_LEN + 1];

		zbx_hashset_const_iter_reset(&obj->data.object, &iter);
		while (NULL != (el = (zbx_jsonobj_el_t *)zbx_hashset_const_iter_next(&iter)))
		{
			if (SUCCEED != zbx_is_discovery_macro(el->name))
				continue;

			switch (el->value.type)
			{
			case ZBX_JSON_TYPE_NUMBER:
				zbx_print_double(buf, sizeof(buf), el->value.data.number);
				lld_macro.value = zbx_strdup(NULL, buf);
				break;
			case ZBX_JSON_TYPE_STRING:
				lld_macro.value = zbx_strdup(NULL, el->value.data.string);
				break;
			default:
				continue;
			}

			lld_macro.macro = zbx_strdup(NULL, el->name);
			zbx_vector_lld_macro_append(&entry->macros, lld_macro);
		}
	}

	zbx_vector_lld_macro_sort(&entry->macros, lld_macro_compare);
}

/******************************************************************************
 *                                                                            *
 * Purpose: clear lld entry (row)                                             *
 *                                                                            *
 ******************************************************************************/
void	lld_entry_clear(zbx_lld_entry_t *entry)
{
	for (int i = 0; i < entry->macros.values_num; i++)
		lld_macro_clear(&entry->macros.values[i]);

	zbx_vector_lld_macro_destroy(&entry->macros);
}

/******************************************************************************
 *                                                                            *
 * Function: lld_entry_get_macro                                              *
 *                                                                            *
 * Purpose: retrieve macro value from lld entry                               *
 *                                                                            *
 * Return value: macro value if found, NULL otherwise                         *
 *                                                                            *
 ******************************************************************************/
const char        *lld_entry_get_macro(const zbx_lld_entry_t *entry, const char *macro)
{
	int			i;
	zbx_lld_macro_t 	lld_macro = {.macro = (char *)macro};

	if (FAIL == (i = zbx_vector_lld_macro_search(&entry->macros, lld_macro, lld_macro_compare)))
		return NULL;

	return entry->macros.values[i].value;
}


/******************************************************************************
 *                                                                            *
 * Purpose: extract lld entries from lld json                                 *
 *                                                                            *
 ******************************************************************************/
int	lld_extract_entries(zbx_hashset_t *entries, const zbx_jsonobj_t *lld_obj,
		const zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, char **error)
{
	const zbx_jsonobj_t	*lld_array;

	if (ZBX_JSON_TYPE_ARRAY == lld_obj->type)
	{
		lld_array = lld_obj;
	}
	else
	{
		if (NULL == (lld_array = zbx_jsonobj_get_value(lld_obj, ZBX_PROTO_TAG_DATA)) ||
				ZBX_JSON_TYPE_ARRAY != lld_array->type)
		{
			*error = zbx_dsprintf(*error, "Cannot find the \"%s\" array in the received JSON object.",
					ZBX_PROTO_TAG_DATA);
			return FAIL;
		}
	}

	for (int i = 0; i < lld_array->data.array.values_num; i++)
	{
		zbx_lld_entry_t	entry_local;

		if (ZBX_JSON_TYPE_OBJECT != lld_array->data.array.values[i]->type)
			continue;

		lld_entry_create(&entry_local, lld_array->data.array.values[i], lld_macro_paths);
		zbx_hashset_insert(entries, &entry_local, sizeof(entry_local));
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: compare two sets of lld entries                                   *
 *                                                                            *
 ******************************************************************************/
int	lld_compare_entries(const zbx_hashset_t *entries1, const zbx_hashset_t *entries2)
{
	zbx_hashset_const_iter_t	iter;
	const zbx_lld_entry_t		*entry;

	if (entries1->num_data != entries2->num_data)
		return FAIL;

	zbx_hashset_const_iter_reset(entries1, &iter);
	while (NULL != (entry = (zbx_lld_entry_t *)zbx_hashset_const_iter_next(&iter)))
	{
		if (NULL == zbx_hashset_search(entries2, entry))
			return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: print entry contents as comma delimited macro:value string        *
 *                                                                            *
 ******************************************************************************/
void	lld_entry_snprintf_alloc(const zbx_lld_entry_t *entry, char **str, size_t *str_alloc, size_t *str_offset)
{
	for (int i = 0; i < entry->macros.values_num; i++)
	{
		if (0 != i)
			zbx_strcpy_alloc(str, str_alloc, str_offset, ", ");

		zbx_snprintf_alloc(str, str_alloc, str_offset, "%s:%s", entry->macros.values[i].macro,
				entry->macros.values[i].value);
	}
}
