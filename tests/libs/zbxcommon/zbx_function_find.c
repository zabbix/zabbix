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
	const char		*expected_param_value_string, *tmp;
	int			expected_result = FAIL, actual_result = FAIL;
	size_t 			func_pos, par_l, par_r;
	size_t 			func_pos_exp, par_l_exp, par_r_exp;
	const int 		max_error_len = MAX_STRING_LEN;
	char 			error_text[MAX_STRING_LEN];

	*error_text = '\0';

	ZBX_UNUSED(state);

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("param", &param_handle)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(param_handle, &init_param)))
	{
		fail_msg("Cannot get input 'param' from test case data: %s", zbx_mock_error_string(error));
	}

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("return", &param_handle)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(param_handle,&tmp)))
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
		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("func_pos", &param_handle)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(param_handle,&tmp)))
		{
			fail_msg("Cannot get expected 'func_pos' parameter from test case data: %s",
				zbx_mock_error_string(error));
		}
		else if(SUCCEED != is_uint64(tmp, &func_pos_exp))
		{
			fail_msg("func_pos parameter \"%s\" is not numeric.", tmp);
		}

		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("par_l", &param_handle)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(param_handle,&tmp)))
		{
			fail_msg("Cannot get expected 'par_l' parameter from test case data: %s",
				zbx_mock_error_string(error));
		}
		else if(SUCCEED != is_uint64(tmp, &par_l_exp))
		{
			fail_msg("par_l parameter \"%s\" is not numeric.", tmp);
		}

		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("par_r", &param_handle)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(param_handle,&tmp)))
		{
			fail_msg("Cannot get expected 'par_r' parameter from test case data: %s",
				zbx_mock_error_string(error));
		}
		else if(SUCCEED != is_uint64(tmp, &par_r_exp))
		{
			fail_msg("par_r parameter \"%s\" is not numeric.", tmp);
		}
	}

	if (FAIL == expected_result && (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("error", &param_handle)) ||
		ZBX_MOCK_SUCCESS != (error = zbx_mock_string(param_handle, &expected_param_value_string))))
	{
		fail_msg("Cannot get expected 'error' parameters from test case data: %s",
				zbx_mock_error_string(error));
	}

	if (expected_result != (actual_result = zbx_function_find(init_param, &func_pos, &par_l, &par_r, error_text,
			max_error_len)))
	{
		fail_msg("Got %s instead of %s as a result. Error: %s", zbx_sysinfo_ret_string(actual_result),
			zbx_sysinfo_ret_string(expected_result), error_text);
	}

	if (SUCCEED == expected_result)
	{
		if (func_pos != func_pos_exp)
		{
			fail_msg("Position "ZBX_FS_SIZE_T" of 'function' not equal expected "ZBX_FS_SIZE_T". Error:%s",
				func_pos, func_pos_exp, error_text);
		}

		if (par_l != par_l_exp)
		{
			fail_msg("Position "ZBX_FS_SIZE_T" of left '(' not equal expected "ZBX_FS_SIZE_T". Error:%s",
				par_l, par_l_exp, error_text);
		}

		if (par_r != par_r_exp)
		{
			fail_msg("Position "ZBX_FS_SIZE_T" of right ')' not equal expected "ZBX_FS_SIZE_T". Error:%s",
				par_r, par_r_exp, error_text);
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
