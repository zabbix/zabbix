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

#include "zbxprometheus.h"
#include "zbxlog.h"

void	zbx_mock_test_entry(void **state)
{
	const char	*data, *params, *output, *request;
	char		*ret_err = NULL, *ret_output = NULL;
	int		ret, expected_ret;

	ZBX_UNUSED(state);

	data = zbx_mock_get_parameter_string("in.data");
	params = zbx_mock_get_parameter_string("in.params");
	output = zbx_mock_get_parameter_string("in.output");
	request = zbx_mock_get_parameter_string("in.request");

	if (SUCCEED != (ret = zbx_prometheus_pattern(data, params, request, output, &ret_output, &ret_err)))
		printf("Error: %s\n", ret_err);

	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.result"));
	zbx_mock_assert_result_eq("Invalid zbx_prometheus_pattern() return value", expected_ret, ret);

	if (SUCCEED == ret)
	{
		output = zbx_mock_get_parameter_string("out.output");
		zbx_mock_assert_str_eq("Invalid zbx_prometheus_pattern() returned output", output, ret_output);
		zbx_free(ret_output);
	}
	else
		zbx_free(ret_err);
}
