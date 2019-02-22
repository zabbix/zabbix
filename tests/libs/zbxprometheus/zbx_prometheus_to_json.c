/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "zbxprometheus.h"

void	zbx_mock_test_entry(void **state)
{
	const char	*data, *params, *output, *err, *result;
	char		*ret_err = NULL, *ret_output = NULL;
	int		ret;

	ZBX_UNUSED(state);

	data = zbx_mock_get_parameter_string("in.data");
	params = zbx_mock_get_parameter_string("in.params");
	result = zbx_mock_get_parameter_string("out.result");

	if (SUCCEED == (ret = zbx_prometheus_to_json(data, params, &ret_output, &ret_err)))
	{
		/* Check result and output */
		zbx_mock_assert_result_eq("Invalid zbx_prometheus_to_json() return value", SUCCEED, ret);
		zbx_mock_assert_str_eq("Invalid zbx_prometheus_to_json() returned result", result, "succeed");
		output = zbx_mock_get_parameter_string("out.output");
		zbx_mock_assert_str_eq("Invalid zbx_prometheus_to_json() returned wrong output", output, ret_output);
	}
	else
	{
		/* Check if the test case was expected to fail and got appropriate error description */
		zbx_mock_assert_result_eq("Invalid zbx_prometheus_to_json() return value", FAIL, ret);
		zbx_mock_assert_str_eq("Invalid zbx_prometheus_to_json() returned result", result, "fail");
		err = zbx_mock_get_parameter_string("out.error");
		zbx_mock_assert_str_eq("Invalid zbx_prometheus_to_json() returned error description", err, ret_err);
	}
}
