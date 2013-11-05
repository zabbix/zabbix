/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "lld.h"
#include "db.h"
#include "log.h"
#include "zbxserver.h"
#include "zbxregexp.h"

int	lld_check_record(struct zbx_json_parse *jp_row, const char *f_macro, const char *f_regexp,
		zbx_vector_ptr_t *regexps)
{
	const char	*__function_name = "lld_check_record";

	char		*value = NULL;
	size_t		value_alloc = 0;
	int		res = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() jp_row:'%.*s'", __function_name,
			jp_row->end - jp_row->start + 1, jp_row->start);

	if (NULL == f_macro || NULL == f_regexp)
		goto out;

	if (SUCCEED == zbx_json_value_by_name_dyn(jp_row, f_macro, &value, &value_alloc))
		res = regexp_match_ex(regexps, value, f_regexp, ZBX_CASE_SENSITIVE);

	zbx_free(value);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: DBlld_get_item                                                   *
 *                                                                            *
 * Purpose: finds item in the selected host by an item prototype key and      *
 *          discovered data                                                   *
 *                                                                            *
 ******************************************************************************/
int	DBlld_get_item(zbx_uint64_t hostid, const char *tmpl_key, struct zbx_json_parse *jp_row, zbx_uint64_t *itemid)
{
	const char	*__function_name = "DBlld_get_item";

	DB_RESULT	result;
	DB_ROW		row;
	char		*key = NULL, *key_esc;
	int		res = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (NULL != jp_row)
	{
		key = zbx_strdup(key, tmpl_key);
		substitute_key_macros(&key, NULL, NULL, jp_row, MACRO_TYPE_ITEM_KEY, NULL, 0);
		key_esc = DBdyn_escape_string_len(key, ITEM_KEY_LEN);
	}
	else
		key_esc = DBdyn_escape_string_len(tmpl_key, ITEM_KEY_LEN);

	result = DBselect(
			"select itemid"
			" from items"
			" where hostid=" ZBX_FS_UI64
				" and key_='%s'",
			hostid, key_esc);

	zbx_free(key_esc);
	zbx_free(key);

	if (NULL == (row = DBfetch(result)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() cannot find item [%s] on the host",
				__function_name, key);
		res = FAIL;
	}
	else
		ZBX_STR2UINT64(*itemid, row[0]);

	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}
