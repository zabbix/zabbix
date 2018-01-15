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

#include "common.h"

void	zbx_mock_test_entry(void **state)
{
	zbx_mock_error_t	error;
	const char		*init_param;
	zbx_mock_handle_t	param_handle;
	const char		*expected_param_value_string, *expected_return_string;
	int			expected_result = FAIL, actual_result = FAIL;
	size_t 			par_l, par_r;
	const int 		max_error_len = 255;
	char 			error_text[max_error_len];

	ZBX_UNUSED(state);

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("param", &param_handle)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(param_handle, &init_param)))
	{
		fail_msg("Cannot get input 'param' from test case data: %s", zbx_mock_error_string(error));
	}

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("return", &param_handle)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(param_handle,&expected_return_string)))
	{
		fail_msg("Cannot get expected 'return' parameter from test case data: %s",
				zbx_mock_error_string(error));
	}
	else
	{
		if (0 == strcmp("SUCCEED", expected_return_string))
			expected_result = SUCCEED;
		else if (0 == strcmp("FAIL", expected_return_string))
			expected_result = FAIL;
		else
			fail_msg("Get unexpected 'return' parameter from test case data: %s", expected_return_string);
	}

	if (FAIL == expected_result && (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("error", &param_handle)) ||
		ZBX_MOCK_SUCCESS != (error = zbx_mock_string(param_handle, &expected_param_value_string))))
	{
		fail_msg("Cannot get expected 'error' parameters from test case data: %s",
				zbx_mock_error_string(error));
	}

	if (expected_result != (actual_result = zbx_function_validate(init_param, &par_l, &par_r, error_text,
			max_error_len)))
	{
		fail_msg("Got %s instead of %s as a result. Error: %s", zbx_sysinfo_ret_string(actual_result),
			zbx_sysinfo_ret_string(expected_result), error_text);
	}

	if (SUCCEED == expected_result)
	{
		if (par_l > par_r)
		{
			fail_msg("Position %i of left '(' greater than position %i of right ')'. Error:%s", par_l,
				par_r, error_text);
		}
	}
	else /* SYSINFO_RET_FAIL == expected_result */
	{
		if (0 != strcmp(expected_param_value_string, error_text))
		{
				fail_msg("Got\n'%s' instead of\n'%s' as a value.", error_text,
					expected_param_value_string);
		}
	}
}
