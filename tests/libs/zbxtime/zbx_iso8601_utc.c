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
#include <stdlib.h>

#include "zbxtime.h"

void	zbx_mock_test_entry(void **state)
{
	const char	*param;
	int		expected_ret, returned_ret;
	time_t		ut;

	ZBX_UNUSED(state);

	param = zbx_mock_get_parameter_string("in.param");

	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	returned_ret = zbx_iso8601_utc(param, &ut);

	zbx_mock_assert_result_eq("zbx_iso8601_datetime()", expected_ret, returned_ret);

	if (SUCCEED == returned_ret)
	{
		zbx_timespec_t	dt;
		time_t		t_out;

		if (ZBX_MOCK_SUCCESS != zbx_strtime_to_timespec(zbx_mock_get_parameter_string("out.datetime"), &dt))
			fail_msg("Invalid date time format");

		t_out = dt.sec;
		zbx_mock_assert_time_eq("zbx_iso8601_datetime() incorrect parsing:", t_out, ut);
	}
}
