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

#include "zbxnum.h"
#include "zbxstr.h"
#include "zbxexpr.h"

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
		zbx_uint32_t	num;

		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("func_pos", &param_handle)) ||
				ZBX_MOCK_SUCCESS != (error = zbx_mock_string(param_handle, &tmp)))
		{
			num = 0;
			fail_msg("Cannot get expected 'func_pos' parameter from test case data: %s",
				zbx_mock_error_string(error));
		}
		else if (SUCCEED != zbx_is_uint32(tmp, &num))
		{
			fail_msg("func_pos parameter \"%s\" is not numeric.", tmp);
		}

		func_pos_exp = num;

		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("par_l", &param_handle)) ||
				ZBX_MOCK_SUCCESS != (error = zbx_mock_string(param_handle, &tmp)))
		{
			fail_msg("Cannot get expected 'par_l' parameter from test case data: %s",
					zbx_mock_error_string(error));
		}
		else if (SUCCEED != zbx_is_uint32(tmp, &num))
		{
			fail_msg("par_l parameter \"%s\" is not numeric.", tmp);
		}

		par_l_exp = num;

		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("par_r", &param_handle)) ||
				ZBX_MOCK_SUCCESS != (error = zbx_mock_string(param_handle, &tmp)))
		{
			fail_msg("Cannot get expected 'par_r' parameter from test case data: %s",
					zbx_mock_error_string(error));
		}
		else if (SUCCEED != zbx_is_uint32(tmp, &num))
		{
			fail_msg("par_r parameter \"%s\" is not numeric.", tmp);
		}

		par_r_exp = num;
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
		fail_msg("Got %s instead of %s as a result. Error: %s", zbx_result_string(actual_result),
				zbx_result_string(expected_result), error_text);
	}

	if (SUCCEED == expected_result)
	{
		if (func_pos != func_pos_exp)
		{
			fail_msg("Position "ZBX_FS_SIZE_T" of 'function' not equal expected "ZBX_FS_SIZE_T". Error:%s",
					(zbx_fs_size_t)func_pos, (zbx_fs_size_t)func_pos_exp, error_text);
		}

		if (par_l != par_l_exp)
		{
			fail_msg("Position "ZBX_FS_SIZE_T" of left '(' not equal expected "ZBX_FS_SIZE_T". Error:%s",
					(zbx_fs_size_t)par_l, (zbx_fs_size_t)par_l_exp, error_text);
		}

		if (par_r != par_r_exp)
		{
			fail_msg("Position "ZBX_FS_SIZE_T" of right ')' not equal expected "ZBX_FS_SIZE_T". Error:%s",
					(zbx_fs_size_t)par_r, (zbx_fs_size_t)par_r_exp, error_text);
		}
	}
	else /* FAIL == expected_result */
	{
		if (0 != strcmp(expected_param_value_string, error_text))
		{
			fail_msg("Got\n'%s' instead of\n'%s' as a value.", error_text,
					expected_param_value_string);
		}
	}
}
