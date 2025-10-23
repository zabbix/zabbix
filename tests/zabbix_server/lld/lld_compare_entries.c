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

void	zbx_mock_test_entry(void **state)
{
	const char			*entry1, *entry2;
	char				*error = NULL;
	zbx_vector_lld_macro_path_ptr_t	macro_paths;
	zbx_jsonobj_t			obj1, obj2;
	zbx_hashset_t			entries1, entries2;
	int				expected_ret, returned_ret;

	ZBX_UNUSED(state);

	entry1 = zbx_mock_get_parameter_string("in.entry1");
	entry2 = zbx_mock_get_parameter_string("in.entry2");

	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.result"));

	zbx_mock_assert_result_eq("jsonobj_parse(entry1)", zbx_jsonobj_open(entry1, &obj1), SUCCEED);
	zbx_mock_assert_result_eq("jsonobj_parse(entry2)", zbx_jsonobj_open(entry2, &obj2), SUCCEED);

	zbx_vector_lld_macro_path_ptr_create(&macro_paths);

	zbx_hashset_create_ext(&entries1, 0, lld_entry_hash, lld_entry_compare, lld_entry_clear_wrapper,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	zbx_hashset_create_ext(&entries2, 0, lld_entry_hash, lld_entry_compare, lld_entry_clear_wrapper,
			ZBX_DEFAULT_MEM_MALLOC_FUNC, ZBX_DEFAULT_MEM_REALLOC_FUNC, ZBX_DEFAULT_MEM_FREE_FUNC);

	if (SUCCEED != lld_extract_entries(&entries1, NULL, &obj1, &macro_paths, &error))
		fail_msg("lld_extract_entries(entries1): %s", error);

	if (SUCCEED != lld_extract_entries(&entries2, NULL, &obj2, &macro_paths, &error))
		fail_msg("lld_extract_entries(entries2): %s", error);

	returned_ret = lld_compare_entries(&entries1, &entries2);

	zbx_mock_assert_result_eq("lld_compare_entries", expected_ret, returned_ret);

	zbx_hashset_destroy(&entries1);
	zbx_hashset_destroy(&entries2);

	zbx_jsonobj_clear(&obj1);
	zbx_jsonobj_clear(&obj2);

	zbx_vector_lld_macro_path_ptr_destroy(&macro_paths);

}
