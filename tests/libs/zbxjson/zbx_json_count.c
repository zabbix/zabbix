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

#include "zbxcommon.h"
#include "zbxjson.h"

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

void	zbx_mock_test_entry(void **state)
{
	const char		*json_str;
	struct zbx_json_parse	jp;
	int			count, expected_count;

	ZBX_UNUSED(state);

	json_str = zbx_mock_get_parameter_string("in.json");

	assert_int_equal(SUCCEED, zbx_json_open(json_str, &jp));

	count          = zbx_json_count(&jp);
	expected_count = atoi(zbx_mock_get_parameter_string("out.count"));

	zbx_mock_assert_int_eq("Element count", expected_count, count);
}
