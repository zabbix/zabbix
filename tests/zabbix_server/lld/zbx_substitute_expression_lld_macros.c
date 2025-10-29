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

#include "zbxcommon.h"

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "zbxjson.h"
#include "zbxexpression.h"

#include "../../../src/zabbix_server/lld/lld.h"
#include "../../../src/zabbix_server/lld/lld_entry.c"

static void	get_macros(const char *path, zbx_vector_lld_macro_path_ptr_t *macros)
{
	zbx_lld_macro_path_t	*macro;
	zbx_mock_handle_t	hmacros, hmacro;
	int			macros_num = 1;
	zbx_mock_error_t	err;

	hmacros = zbx_mock_get_parameter_handle(path);
	while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(hmacros, &hmacro))))
	{
		if (ZBX_MOCK_SUCCESS != err)
			fail_msg("Cannot read macro #%d: %s", macros_num, zbx_mock_error_string(err));

		macro = (zbx_lld_macro_path_t *)zbx_malloc(NULL, sizeof(zbx_lld_macro_path_t));
		macro->lld_macro = zbx_strdup(NULL, zbx_mock_get_object_member_string(hmacro, "macro"));
		macro->path = zbx_strdup(NULL, zbx_mock_get_object_member_string(hmacro, "path"));
		zbx_vector_lld_macro_path_ptr_append(macros, macro);

		macros_num++;
	}
}


void	zbx_mock_test_entry(void **state)
{
	char				*error, *data = zbx_strdup(NULL ,zbx_mock_get_parameter_string("in.data"));
	const char			*lld_row;
	zbx_vector_lld_macro_path_ptr_t	macros;
	zbx_jsonobj_t			obj;
	zbx_lld_entry_t			entry;
	int				max_error_len = ZBX_MAX_B64_LEN;

	ZBX_UNUSED(state);

	const char	*expected_data = zbx_mock_get_parameter_string("out.data");

	zbx_vector_lld_macro_path_ptr_create(&macros);
	get_macros("in.macros", &macros);

	lld_row = zbx_mock_get_parameter_string("in.lld");

	if (SUCCEED != zbx_jsonobj_open(lld_row, &obj))
		fail_msg("invalid lld row parameter: %s", zbx_json_strerror());

	lld_entry_create(&entry, &obj, &macros);

	int	result = zbx_substitute_expression_lld_macros(&data, ZBX_EVAL_EXPRESSION_MACRO_LLD, &entry, &error);

	zbx_mock_assert_result_eq("return value", SUCCEED, result);
	zbx_mock_assert_str_eq("data", expected_data, data);
}
