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

#include "zbxstr.h"

void	zbx_mock_test_entry(void **state)
{
	const char	*text, *len, *expected_value;
	int		expected_ret, returned_ret;
	char		*returned_value;

	ZBX_UNUSED(state);

	text = zbx_mock_get_parameter_string("in.text");
	len = zbx_mock_get_parameter_string("in.len");
	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	returned_ret = zbx_str_extract(text, (size_t)atoi(len), &returned_value);

	zbx_mock_assert_result_eq("zbx_str_extract() return", expected_ret, returned_ret);

	if (SUCCEED == expected_ret)
	{
		expected_value = zbx_mock_get_parameter_string("out.value");
		zbx_mock_assert_str_eq("extracted value", expected_value, returned_value);
		zbx_free(returned_value);
	}
}
