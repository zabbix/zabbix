/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
#include "zbxmockutil.h"

#include "common.h"

void	zbx_mock_test_entry(void **state)
{
	int		expected_result, actual_result;
	const char	*is_number;

	ZBX_UNUSED(state);

	is_number = zbx_mock_get_parameter_string("in.num");
	expected_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));

	if (expected_result != (actual_result = is_double_suffix(is_number, ZBX_FLAG_DOUBLE_SUFFIX)))
	{
		fail_msg("Got %s instead of %s as a result validation [%s].", zbx_result_string(actual_result),
			zbx_result_string(expected_result), is_number);
	}
}
