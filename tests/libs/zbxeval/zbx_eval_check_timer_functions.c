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
	int			returned_ret, result;
	zbx_eval_context_t	ctx;
	char			*error = NULL;
	zbx_uint64_t		rules;
	int			exp_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));

	ZBX_UNUSED(state);

	rules = mock_eval_read_rules("in.rules");
	returned_ret = zbx_eval_parse_expression(&ctx, zbx_mock_get_parameter_string("in.expression"), rules, &error);

	if (SUCCEED != returned_ret)
		printf("ERROR: %s\n", error);
	else
		mock_dump_stack(&ctx);

	result = zbx_eval_check_timer_functions(&ctx);
	zbx_mock_assert_int_eq("return value", exp_result, result);
	zbx_eval_clear(&ctx);
	zbx_free(error);
}
