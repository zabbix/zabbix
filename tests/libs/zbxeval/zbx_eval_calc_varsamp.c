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

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "zbxcommon.h"
#include "zbxeval.h"
#include "mock_eval.h"

void	zbx_mock_test_entry(void **state)
{
	double			function_result, exp_function_result;
	zbx_vector_dbl_t	values;
	char			*error = NULL;
	int			result;

	ZBX_UNUSED(state);

	zbx_vector_dbl_create(&values);
	extract_yaml_values_dbl(zbx_mock_get_parameter_handle("in.values"), &values);
	result = zbx_eval_calc_varsamp(&values, &function_result, &error);

	zbx_mock_assert_int_eq("return value", result,
			zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return")));

	if (SUCCEED == result)
	{
		exp_function_result = zbx_mock_get_parameter_float("out.result");
		zbx_mock_assert_double_eq("return function value", exp_function_result, function_result);
	}

	zbx_vector_dbl_destroy(&values);
	zbx_free(error);
}
