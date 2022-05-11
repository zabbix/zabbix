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

#include "common.h"

#include "dbcache.h"
#include "log.h"

/******************************************************************************
 *                                                                            *
 * Purpose: sorting function to sort LLD macros by unique name                *
 *                                                                            *
 ******************************************************************************/
int	zbx_lld_macro_paths_compare(const void *d1, const void *d2)
{
	const zbx_lld_macro_path_t	*r1 = *(const zbx_lld_macro_path_t **)d1;
	const zbx_lld_macro_path_t	*r2 = *(const zbx_lld_macro_path_t **)d2;

	return strcmp(r1->lld_macro, r2->lld_macro);
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve list of LLD macros                                       *
 *                                                                            *
 * Parameters: lld_ruleid      - [IN] LLD id                                  *
 *             lld_macro_paths - [OUT] use json path to extract from jp_row   *
 *             error           - [OUT] in case json path is invalid           *
 *                                                                            *
 ******************************************************************************/
int	zbx_lld_macro_paths_get(zbx_uint64_t lld_ruleid, zbx_vector_ptr_t *lld_macro_paths, char **error)
{
	DB_RESULT		result;
	DB_ROW			row;
	zbx_lld_macro_path_t	*lld_macro_path;
	int			ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = DBselect(
			"select lld_macro,path"
			" from lld_macro_path"
			" where itemid=" ZBX_FS_UI64,
			lld_ruleid);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_jsonpath_t	path;

		if (SUCCEED != (ret = zbx_jsonpath_compile(row[1], &path)))
		{
			*error = zbx_dsprintf(*error, "Cannot process LLD macro \"%s\": %s.\n", row[0],
					zbx_json_strerror());
			break;
		}

		zbx_jsonpath_clear(&path);

		lld_macro_path = (zbx_lld_macro_path_t *)zbx_malloc(NULL, sizeof(zbx_lld_macro_path_t));
		lld_macro_path->lld_macro = zbx_strdup(NULL, row[0]);
		lld_macro_path->path = zbx_strdup(NULL, row[1]);

		zbx_vector_ptr_append(lld_macro_paths, lld_macro_path);
	}
	DBfree_result(result);

	zbx_vector_ptr_sort(lld_macro_paths, zbx_lld_macro_paths_compare);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: release resources allocated by lld macro path                     *
 *                                                                            *
 * Parameters: lld_macro_path - [IN] json path to extract from lld_row        *
 *                                                                            *
 ******************************************************************************/
void	zbx_lld_macro_path_free(zbx_lld_macro_path_t *lld_macro_path)
{
	zbx_free(lld_macro_path->path);
	zbx_free(lld_macro_path->lld_macro);
	zbx_free(lld_macro_path);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get value of LLD macro using json path if available or by         *
 *          searching for such key in key value pairs of array entry          *
 *                                                                            *
 * Parameters: jp_row          - [IN] the lld data row                        *
 *             lld_macro_paths - [IN] use json path to extract from jp_row    *
 *             macro           - [IN] LLD macro                               *
 *             value           - [OUT] value extracted from jp_row            *
 *                                                                            *
 ******************************************************************************/
int	zbx_lld_macro_value_by_name(const struct zbx_json_parse *jp_row, const zbx_vector_ptr_t *lld_macro_paths,
		const char *macro, char **value)
{
	zbx_lld_macro_path_t	lld_macro_path_local, *lld_macro_path;
	int			index;
	size_t			value_alloc = 0;
	zbx_json_type_t		type;

	lld_macro_path_local.lld_macro = (char *)macro;

	if (FAIL != (index = zbx_vector_ptr_bsearch(lld_macro_paths, &lld_macro_path_local,
			zbx_lld_macro_paths_compare)))
	{
		lld_macro_path = (zbx_lld_macro_path_t *)lld_macro_paths->values[index];

		if (SUCCEED == zbx_jsonpath_query(jp_row, lld_macro_path->path, value) && NULL != *value)
			return SUCCEED;

		return FAIL;
	}

	if (FAIL != (zbx_json_value_by_name_dyn(jp_row, macro, value, &value_alloc, &type)) &&
			ZBX_JSON_TYPE_NULL != type)
		return SUCCEED;

	return FAIL;
}
