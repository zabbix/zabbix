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
#include "zbxmockdb.h"

#include "zbxjson.h"

#include "../../../src/zabbix_server/lld/lld.h"
#include "../../../src/zabbix_server/lld/lld_entry.c"
#include "lld_common.h"

void	zbx_mock_test_entry(void **state)
{
	char				*error,
					*data_expression = zbx_strdup(NULL ,zbx_mock_get_parameter_string("in.data")),
					*data_calc = zbx_strdup(NULL ,zbx_mock_get_parameter_string("in.data"));
	zbx_vector_lld_macro_path_ptr_t	macros;
	zbx_jsonobj_t			obj;
	zbx_lld_entry_t			entry;
	int				result_expression, result_calc;

	ZBX_UNUSED(state);

	const char	*expected_data = zbx_mock_get_parameter_string("out.data");

	zbx_vector_lld_macro_path_ptr_create(&macros);
	get_macros("in.macros", &macros);

	const char	*lld_row = zbx_mock_get_parameter_string("in.lld");

	if (SUCCEED != zbx_jsonobj_open(lld_row, &obj))
		fail_msg("invalid lld row parameter: %s", zbx_json_strerror());

	lld_entry_create(&entry, &obj, &macros);

	result_expression = zbx_substitute_expression_lld_macros(&data_expression, ZBX_EVAL_EXPRESSION_MACRO_LLD,
			&entry, &error);

	result_calc = zbx_substitute_expression_lld_macros(&data_calc, ZBX_EVAL_CALC_EXPRESSION_LLD, &entry, &error);

	zbx_mock_assert_result_eq("return value with rule ZBX_EVAL_EXPRESSION_MACRO_LLD", SUCCEED, result_expression);
	zbx_mock_assert_str_eq("data with rule ZBX_EVAL_EXPRESSION_MACRO_LLD", expected_data, data_expression);

	zbx_mock_assert_result_eq("return value with rule ZBX_EVAL_CALC_EXPRESSION_LLD", SUCCEED, result_calc);
	zbx_mock_assert_str_eq("data with rule ZBX_EVAL_CALC_EXPRESSION_LLD", expected_data, data_calc);

	zbx_vector_lld_macro_path_ptr_clear_ext(&macros, zbx_lld_macro_path_free);
	zbx_vector_lld_macro_path_ptr_destroy(&macros);
	zbx_jsonobj_clear(&obj);
	lld_entry_clear(&entry);
	zbx_free(data_expression);
	zbx_free(data_calc);
}
