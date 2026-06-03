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

#include "mock_json.h"

void	zbx_mock_test_entry(void **state)
{
	const char		*json_str, *name;
	struct zbx_json_parse	jp;
	char			buffer[4096];
	zbx_json_type_t		type;
	int			ret, expected_ret;

	ZBX_UNUSED(state);

	json_str = zbx_mock_get_parameter_string("in.json");
	name     = zbx_mock_get_parameter_string("in.name");

	assert_int_equal(SUCCEED, zbx_json_open(json_str, &jp));

	ret          = zbx_json_value_by_name(&jp, name, buffer, sizeof(buffer), &type);
	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.ret"));

	zbx_mock_assert_int_eq("Return value", expected_ret, ret);

	if (SUCCEED == ret)
	{
		zbx_mock_assert_str_eq("Value", zbx_mock_get_parameter_string("out.value"), buffer);
		zbx_mock_assert_str_eq("JSON type", zbx_mock_get_parameter_string("out.type"),
				zbx_mock_json_type_to_str((int)type));
	}
}
