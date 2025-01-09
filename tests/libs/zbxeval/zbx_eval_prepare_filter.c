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
#include "zbxlog.h"
#include "mock_eval.h"

void	zbx_mock_test_entry(void **state)
{
	zbx_eval_context_t	ctx;
	char			*error = NULL;
	int			expected_ret, returned_ret;

	ZBX_UNUSED(state);

	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.result"));
	if (SUCCEED != (returned_ret = zbx_eval_parse_expression(&ctx, zbx_mock_get_parameter_string("in.expression"),
			ZBX_EVAL_PARSE_QUERY_EXPRESSION, &error)))
	{
		printf("ERROR: %s\n", error);
	}

	zbx_mock_assert_result_eq("return value", expected_ret, returned_ret);

	if (SUCCEED == expected_ret)
	{
		zbx_eval_prepare_filter(&ctx);
		mock_compare_stack(&ctx, "out.stack");
	}

	zbx_free(error);
	zbx_eval_clear(&ctx);
}
