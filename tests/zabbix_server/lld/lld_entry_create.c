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

#include "../../../src/zabbix_server/lld/lld_entry.c"
#include "zbxmocktest.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"
#include "zbxmockdata.h"
#include "zbxmockdb.h"
#include "zbxcommon.h"

#include "zbxcacheconfig.h"


static void	load_macro_paths(zbx_vector_lld_macro_path_ptr_t *macro_paths)
{
	zbx_mock_error_t 	error;
	zbx_mock_handle_t	vector, element;

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("macros", &vector)))
		fail_msg("Cannot get in.macros handle: %s", zbx_mock_error_string(error));

	while (ZBX_MOCK_SUCCESS == (error = zbx_mock_vector_element(vector, &element)))
	{
		const char		*macro, *path;
		zbx_lld_macro_path_t	*macro_path;

		macro = zbx_mock_get_object_member_string(element, "macro");
		path = zbx_mock_get_object_member_string(element, "path");

		macro_path = (zbx_lld_macro_path_t *)zbx_malloc(NULL, sizeof(zbx_lld_macro_path_t));
		macro_path->lld_macro = zbx_strdup(NULL, macro);
		macro_path->path = zbx_strdup(NULL, path);

		zbx_vector_lld_macro_path_ptr_append(macro_paths, macro_path);
	}
}

static void	validate_lld_entry(const zbx_lld_entry_t *entry)
{
	zbx_mock_error_t 	error;
	zbx_mock_handle_t	vector, element;
	int			i = 0;

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("macros", &vector)))
		fail_msg("Cannot get out.macros handle: %s", zbx_mock_error_string(error));

	while (ZBX_MOCK_SUCCESS == (error = zbx_mock_vector_element(vector, &element)))
	{
		const char	*macro, *value;

		zabbix_log(LOG_LEVEL_DEBUG, "  validate macro %s:%s",
				entry->macros.values[i].macro, entry->macros.values[i].value);

		if (i >= entry->macros.values_num)
			fail_msg("not enough macros in entry, expected %d, got %d", entry->macros.values_num, i);

		macro = zbx_mock_get_object_member_string(element, "macro");
		value = zbx_mock_get_object_member_string(element, "value");

		zbx_mock_assert_str_eq("macro name", macro, entry->macros.values[i].macro);
		zbx_mock_assert_str_eq("macro value", value, entry->macros.values[i].value);

		i++;
	}

	if (i != entry->macros.values_num)
		fail_msg("too many macros in entry, expected %d, got %d", entry->macros.values_num, i);
}

void	zbx_mock_test_entry(void **state)
{
	const char			*in;
	zbx_vector_lld_macro_path_ptr_t	macro_paths;
	zbx_lld_entry_t			entry;
	zbx_jsonobj_t			json_obj;

	ZBX_UNUSED(state);

	zbx_vector_lld_macro_path_ptr_create(&macro_paths);
	in = zbx_mock_get_parameter_string("in.entry");

	zbx_mock_assert_result_eq("jsonobj_parse", zbx_jsonobj_open(in, &json_obj), SUCCEED);

	load_macro_paths(&macro_paths);

	lld_entry_create(&entry, &json_obj, &macro_paths);
	validate_lld_entry(&entry);

	lld_entry_clear(&entry);
	zbx_jsonobj_clear(&json_obj);

	zbx_vector_lld_macro_path_ptr_clear_ext(&macro_paths, zbx_lld_macro_path_free);
	zbx_vector_lld_macro_path_ptr_destroy(&macro_paths);

}
