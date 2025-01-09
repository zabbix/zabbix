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

#include "zbxnum.h"

void	zbx_mock_test_entry(void **state)
{
	int		expected_result, actual_result;
	const char	*is_number;

	ZBX_UNUSED(state);

	is_number = zbx_mock_get_parameter_string("in.num");
	expected_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));

	if (expected_result != (actual_result = zbx_is_double(is_number, NULL)))
	{
		fail_msg("Got %s instead of %s as a result validation [%s].", zbx_result_string(actual_result),
			zbx_result_string(expected_result), is_number);
	}
}
