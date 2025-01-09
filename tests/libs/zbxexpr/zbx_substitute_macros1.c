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

#include "zbxexpr.h"

static int	macro_resolv_func(zbx_macro_resolv_data_t *p, va_list args, char **replace_to, char **data, char *error,
		size_t maxerrlen)
{
	/* Passed arguments */
	const char	*param1 = va_arg(args, const char *);

	ZBX_UNUSED(data);
	ZBX_UNUSED(error);
	ZBX_UNUSED(maxerrlen);

	if (NULL == param1)
		param1 = "(null)";

	if (0 == strcmp(p->macro, "{VALUE}"))
		*replace_to = zbx_strdup(*replace_to, param1);

	return SUCCEED;
}

void	zbx_mock_test_entry(void **state)
{
	const char	*expression, *param1;
	char		*result = NULL, error[MAX_BUFFER_LEN] = {0};
	int		ret, expected_ret;

	ZBX_UNUSED(state);

	expression = zbx_mock_get_parameter_string("in.expression");
	param1 = zbx_mock_get_optional_parameter_string("in.param1");
	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));

	result = strdup(expression);

	ret = zbx_substitute_macros(&result, error, sizeof(error), macro_resolv_func, param1);

	zbx_mock_assert_result_eq("zbx_substitute_macros() return code", expected_ret, ret);

	if (SUCCEED == ret)
	{
		const char	*expected_result = zbx_mock_get_parameter_string("out.result");

		zbx_mock_assert_str_eq("zbx_substitute_macros() result", expected_result, result);
	}
	else
	{
		const char	*expected_error = zbx_mock_get_parameter_string("out.error");

		zbx_mock_assert_str_eq("zbx_substitute_macros() expected error", expected_error, error);
	}

	zbx_free(result);
}
