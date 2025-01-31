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
#include "zbxmockutil.h"
#include "zbxmockassert.h"

#include "zbxexpr.h"
#include "zbxstr.h"

void	zbx_mock_test_entry(void **state)
{
	const char	*exp_result, *exp_final_result, *source = zbx_mock_get_parameter_string("in.source"),
			*func_type =  zbx_mock_get_parameter_string("in.func_type");
	char		*result_decode = NULL, *result_encode = NULL;
	int		decode_return, exp_return;

	ZBX_UNUSED(state);

	if (SUCCEED == zbx_strcmp_natural(func_type, "encode"))
	{
		result_encode = zbx_malloc(result_encode, MAX_STRING_LEN);
		zbx_url_encode(source, &result_encode);
		exp_result = zbx_mock_get_parameter_string("out.result");
		zbx_mock_assert_str_eq("return value encode", exp_result, result_encode);
		zbx_free(result_encode);
	}

	if (SUCCEED == zbx_strcmp_natural(func_type, "decode"))
	{
		result_decode = zbx_malloc(result_decode, MAX_STRING_LEN);
		decode_return = zbx_url_decode(source, &result_decode);
		exp_return = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
		zbx_mock_assert_int_eq("return value", exp_return, decode_return);

		if (SUCCEED == zbx_mock_parameter_exists("out.result"))
		{
			exp_result = zbx_mock_get_parameter_string("out.result");
			zbx_mock_assert_str_eq("return value decode str", exp_result, result_decode);
		}

		zbx_free(result_decode);
	}

	if (SUCCEED == zbx_strcmp_natural(func_type, "encode_decode"))
	{
		result_encode = zbx_malloc(result_encode, MAX_STRING_LEN);
		zbx_url_encode(source, &result_encode);
		exp_result = zbx_mock_get_parameter_string("out.result");
		zbx_mock_assert_str_eq("return value encode", exp_result, result_encode);
		result_decode = zbx_malloc(result_decode, MAX_STRING_LEN);
		decode_return = zbx_url_decode(source, &result_decode);
		exp_final_result = zbx_mock_get_parameter_string("out.final_result");
		zbx_mock_assert_str_eq("return value decode", exp_final_result, result_decode);
		zbx_free(result_encode);
		zbx_free(result_decode);
	}
}
