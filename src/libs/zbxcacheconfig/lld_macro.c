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

#include "zbxcacheconfig.h"

#include "zbxalgo.h"
#include "zbxdb.h"
#include "zbxjson.h"

ZBX_PTR_VECTOR_IMPL(lld_macro_path_ptr, zbx_lld_macro_path_t *)

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
int	zbx_lld_macro_paths_get(zbx_uint64_t lld_ruleid, zbx_vector_lld_macro_path_ptr_t *lld_macro_paths, char **error)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_lld_macro_path_t	*lld_macro_path;
	int			ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	result = zbx_db_select(
			"select lld_macro,path"
			" from lld_macro_path"
			" where itemid=" ZBX_FS_UI64,
			lld_ruleid);

	while (NULL != (row = zbx_db_fetch(result)))
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

		zbx_vector_lld_macro_path_ptr_append(lld_macro_paths, lld_macro_path);
	}
	zbx_db_free_result(result);

	zbx_vector_lld_macro_path_ptr_sort(lld_macro_paths, zbx_lld_macro_paths_compare);

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

