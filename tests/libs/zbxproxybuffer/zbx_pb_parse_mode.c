/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#include "zbxcommon.h"
#include "zbxproxybuffer.h"

#include "zbx_pb_mock_stubs.h"

void	zbx_mock_test_entry(void **state)
{
	zbx_mock_handle_t	hparam;
	const char		*mode_str;
	int			mode, expected_result, result;

	ZBX_UNUSED(state);

	mode_str = (ZBX_MOCK_SUCCESS == zbx_mock_parameter("in.mode_str", &hparam)) ?
			zbx_mock_get_parameter_string("in.mode_str") : NULL;

	expected_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.result"));

	mode = ZBX_PB_MODE_UNSET;
	result = zbx_pb_parse_mode(mode_str, &mode);

	zbx_mock_assert_result_eq("result", expected_result, result);

	if (SUCCEED == result)
	{
		int	expected_mode = zbx_mock_get_parameter_int("out.mode");

		zbx_mock_assert_int_eq("mode", expected_mode, mode);
	}
}
