/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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
#include "zbxjson.h"

void	zbx_mock_test_entry(void **state)
{
	const char		*json, *path, *result, *value;
	struct zbx_json_parse	jp, jp_out;
	char			*buffer = NULL;
	int			ret;
	size_t			size = 0;

	ZBX_UNUSED(state);

	json = zbx_mock_get_parameter_string("in.json");
	path = zbx_mock_get_parameter_string("in.path");
	result = zbx_mock_get_parameter_string("out.result");

	ret = zbx_json_open(json, &jp);
	zbx_mock_assert_result_eq("Invalid zbx_json_open() return value", SUCCEED, ret);

	if (FAIL == (ret = zbx_json_path_open(&jp, path, &jp_out)))
	{
		zbx_mock_assert_str_eq("Invalid zbx_json_path_open() return value", result, "fail");
		return;
	}

	zbx_mock_assert_result_eq("Invalid zbx_json_path_open() return value", SUCCEED, ret);
	zbx_mock_assert_str_eq("Invalid zbx_json_path_open() return value", result, "succeed");

	zbx_json_value_dyn(&jp_out, &buffer, &size);

	value = zbx_mock_get_parameter_string("out.value");
	zbx_mock_assert_str_eq("Invalid value", value, buffer);

	zbx_free(buffer);
}
