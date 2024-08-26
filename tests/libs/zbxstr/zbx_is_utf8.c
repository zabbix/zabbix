/*
** Copyright (C) 2001-2024 Zabbix SIA
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
#include "zbxmockutil.h"
#include "zbxmockassert.h"

#include "zbxstr.h"

void	zbx_mock_test_entry(void **state)
{
	ZBX_UNUSED(state);

	/*
	size_t		in_buffer_length = zbx_mock_get_parameter_uint64("in.buffer_length");
	printf("1111");
	int		exp_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	printf("2222");
	char	*text = zbx_yaml_assemble_binary_sequence("in.buffer", &in_buffer_length);

	printf("%d", in_buffer_length);

	printf("3333");
	int		act_result = zbx_is_utf8(text);
	printf("4444");
	zbx_mock_assert_int_eq("return value", exp_result, act_result);
	printf("5555");
	zbx_free(text);
	printf("6666");
*/
	const char	*text = zbx_mock_get_parameter_string("in.string");
	int		exp_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	int		act_result = zbx_is_utf8(text);

	zbx_mock_assert_int_eq("return value", exp_result, act_result);
}
