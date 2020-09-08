/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "common.h"
#include "zbxtrends.h"

void	zbx_mock_test_entry(void **state)
{
	const char	*period, *shift;
	int		expected_ret, returned_ret, start, end;
	char		*error = NULL;

	ZBX_UNUSED(state);

	period = zbx_mock_get_parameter_string("in.period");
	shift = zbx_mock_get_parameter_string("in.shift");

	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	returned_ret = zbx_trends_parse_range(period, shift, &start, &end, &error);

	if (FAIL == returned_ret)
	{
		printf("zbx_trends_parse_range() error: %s\n", error);
		zbx_free(error);
	}
	zbx_mock_assert_result_eq("zbx_trends_parse_range()", expected_ret, returned_ret);

}
