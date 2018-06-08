/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

#include "zbxalgo.h"

void	zbx_mock_test_entry(void **state)
{
	zbx_mock_error_t	error;
	zbx_mock_handle_t	param_handle;

	double			expected_value, actual_value;
	const char		*tmp, *expected_param_value_string, *expression;
	char			actual_error[256];
	int			expected_result = FAIL, actual_result = FAIL;

	ZBX_UNUSED(state);

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("expression", &param_handle)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(param_handle, &expression)))
	{
		fail_msg("Cannot get input 'expression' from test case data: %s", zbx_mock_error_string(error));
	}

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("return", &param_handle)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(param_handle, &tmp)))
	{
		fail_msg("Cannot get expected 'return' parameter from test case data: %s",
				zbx_mock_error_string(error));
	}
	else
	{
		if (0 == strcmp("SUCCEED", tmp))
			expected_result = SUCCEED;
		else if (0 == strcmp("FAIL", tmp))
			expected_result = FAIL;
		else
			fail_msg("Get unexpected 'return' parameter from test case data: %s", tmp);
	}

	if (SUCCEED == expected_result)
	{
		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("value", &param_handle)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(param_handle, &tmp)))
		{
			fail_msg("Cannot get expected 'value' parameter from test case data: %s",
				zbx_mock_error_string(error));
		}
		else if (SUCCEED != is_double(tmp))
		{
			fail_msg("func_pos parameter \"%s\" is not double.", tmp);
		}

		expected_value = str2double(tmp);
	}

	if (FAIL == expected_result && (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("error", &param_handle)) ||
		ZBX_MOCK_SUCCESS != (error = zbx_mock_string(param_handle, &expected_param_value_string))))
	{
		fail_msg("Cannot get expected 'error' parameters from test case data: %s",
				zbx_mock_error_string(error));
	}

	if (expected_result != (actual_result = evaluate(&actual_value, expression, actual_error,
			sizeof(actual_error), NULL)))
	{
		fail_msg("Got %s instead of %s as a result. Error: %s", zbx_sysinfo_ret_string(actual_result),
			zbx_sysinfo_ret_string(expected_result), actual_error);
	}

	if (SUCCEED == expected_result)
	{
		if (0 != zbx_double_compare(actual_value, expected_value))
		{
			fail_msg("Value %f not equal expected %f. Error:%s", actual_value, expected_value,
					actual_error);
		}
	}
	else /* SYSINFO_RET_FAIL == expected_result */
	{
		if (0 != strcmp(actual_error, expected_param_value_string))
			fail_msg("Got\n'%s' instead of\n'%s' as a value.", actual_error, expected_param_value_string);
	}
}
