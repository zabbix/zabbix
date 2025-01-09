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
#include "zbxmockutil.h"
#include "zbxmockassert.h"

#include "zbxstr.h"

void	zbx_mock_test_entry(void **state)
{
	const char	*s1 = zbx_mock_get_parameter_string("in.string1");
	const char	*s2 = zbx_mock_get_parameter_string("in.string2");
	const char	*exp_result = zbx_mock_get_parameter_string("out.val");
	int		act_value;

	ZBX_UNUSED(state);

	act_value = zbx_strcmp_natural(s1,s2);

	const char	*returned_result;

	if (0 > act_value)
		returned_result = "less";
	else if (0 < act_value)
		returned_result = "greater";
	else
		returned_result = "equal";

	zbx_mock_assert_str_eq("return value", exp_result, returned_result);
}
