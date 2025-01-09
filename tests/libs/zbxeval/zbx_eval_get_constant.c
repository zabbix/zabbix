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

#include "zbxexpression.h"
#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"

void	zbx_mock_test_entry(void **state)
{
	zbx_mock_error_t	error;
	zbx_mock_handle_t	param_handle;
	zbx_uint64_t		index = 0;
	const char		*expected_result = NULL, *expression = NULL;
	char			*actual_result = NULL, *errmsg = NULL;
	zbx_eval_context_t	ctx;

	ZBX_UNUSED(state);

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("expression", &param_handle)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(param_handle, &expression)))
	{
		fail_msg("Cannot get input 'expression' from test case data: %s", zbx_mock_error_string(error));
	}

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("index", &param_handle)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_uint64(param_handle, &index)))
	{
		fail_msg("Cannot get input 'index' from test case data: %s", zbx_mock_error_string(error));
	}

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("return", &param_handle)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(param_handle, &expected_result)))
	{
		fail_msg("Cannot get expected 'return' parameter from test case data: %s",
				zbx_mock_error_string(error));
	}

	if (SUCCEED != zbx_eval_parse_expression(&ctx, expression, ZBX_EVAL_TRIGGER_EXPRESSION, &errmsg))
		fail_msg("Cannot parse expression: %s", errmsg);

	zbx_eval_get_constant(&ctx, index, &actual_result);
	if (NULL == actual_result)
		actual_result = zbx_strdup(NULL, "");

	zbx_mock_assert_str_eq("Invalid result", expected_result, actual_result);

	zbx_free(actual_result);
	zbx_eval_clear(&ctx);
}
