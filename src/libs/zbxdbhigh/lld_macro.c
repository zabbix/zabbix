/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
 * Function: lld_macro_paths_compare                                          *
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
 * Function: lld_macro_paths_get                                              *
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
	const char		*__function_name = "lld_macro_paths_get";

	DB_RESULT		result;
	DB_ROW			row;
	zbx_lld_macro_path_t	*lld_macro_path;
	int			ret = SUCCEED;
	char			err[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select lld_macro,path"
			" from lld_macro_path"
			" where itemid=" ZBX_FS_UI64
			" order by lld_macro",
			lld_ruleid);

	while (NULL != (row = DBfetch(result)))
	{
		if (SUCCEED != (ret = zbx_json_path_check(row[1], err, sizeof(err))))
		{
			*error = zbx_dsprintf(*error, "Cannot process LLD macro \"%s\": %s.\n", row[0], err);
			break;
		}

		lld_macro_path = (zbx_lld_macro_path_t *)zbx_malloc(NULL, sizeof(zbx_lld_macro_path_t));

		lld_macro_path->lld_macro = zbx_strdup(NULL, row[0]);
		lld_macro_path->path = zbx_strdup(NULL, row[1]);

		zbx_vector_ptr_append(lld_macro_paths, lld_macro_path);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: lld_macro_path_free                                              *
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
 * Function: zbx_lld_macro_value_by_name                                      *
 *                                                                            *
 * Purpose: get value of LLD macro using json path if available or by         *
 *          searching for such key in key value pairs of array entry          *
 *                                                                            *
 * Parameters: jp_row          - [IN] the lld data row                        *
 *             lld_macro_paths - [IN] use json path to extract from jp_row    *
 *             macro           - [IN] LLD macro                               *
 *             value           - [OUT] value extracted from jp_row            *
 *             value_alloc     - [OUT] allocated memory size for value        *
 *                                                                            *
 ******************************************************************************/
int	zbx_lld_macro_value_by_name(const struct zbx_json_parse *jp_row, const zbx_vector_ptr_t *lld_macro_paths,
		const char *macro, char **value, size_t *value_alloc)
{
	zbx_lld_macro_path_t	lld_macro_path_local, *lld_macro_path;
	int			index;
	struct zbx_json_parse	jp_out;
	int			ret;

	lld_macro_path_local.lld_macro = (char *)macro;

	if (FAIL != (index = zbx_vector_ptr_bsearch(lld_macro_paths, &lld_macro_path_local,
			zbx_lld_macro_paths_compare)))
	{
		lld_macro_path = (zbx_lld_macro_path_t *)lld_macro_paths->values[index];

		if (FAIL != (ret = zbx_json_path_open(jp_row, lld_macro_path->path, &jp_out)))
			zbx_json_value_dyn(&jp_out, value, value_alloc);
	}
	else
		ret = zbx_json_value_by_name_dyn(jp_row, macro, value, value_alloc);

	return ret;
}

