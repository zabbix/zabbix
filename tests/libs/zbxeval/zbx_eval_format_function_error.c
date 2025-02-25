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
	char		*result, *exp_result = zbx_strdup(NULL, zbx_mock_get_parameter_string("out.result"));
	const char	*function = zbx_mock_get_parameter_string("in.func"),
			*parameter = zbx_mock_get_parameter_string("in.param"),
			*error = zbx_mock_get_parameter_string("in.error");

	ZBX_UNUSED(state);

	if (SUCCEED == zbx_mock_parameter_exists("in.host"))
	{
		const char	*host = zbx_mock_get_parameter_string("in.host"),
				*key = zbx_mock_get_parameter_string("in.key");

		result = zbx_eval_format_function_error(function, host, key, parameter, error);
	}
	else
		result = zbx_eval_format_function_error(function, NULL, NULL, parameter, error);

	zbx_mock_assert_str_eq("returned value:", exp_result, result);
	zbx_free(exp_result);
	zbx_free(result);
}
