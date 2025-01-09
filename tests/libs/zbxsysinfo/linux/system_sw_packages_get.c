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

#include "../../../../src/libs/zbxsysinfo/linux/software.c"

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"

void	zbx_mock_test_entry(void **state)
{
	zbx_mock_error_t	error;
	zbx_mock_handle_t	handle;
	const char		*manager = NULL, *input = NULL, *result = NULL;
	char			*line;
	struct zbx_json		json;
	int			manager_found = 0, i;

	ZBX_UNUSED(state);

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("manager", &handle)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(handle, &manager)))
	{
		fail_msg("Cannot get input 'manager' from test case data: %s", zbx_mock_error_string(error));
	}

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("input", &handle)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(handle, &input)))
	{
		fail_msg("Cannot get input 'input' from test case data: %s", zbx_mock_error_string(error));
	}

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("result", &handle)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(handle, &result)))
	{
		fail_msg("Cannot get input 'result' from test case data: %s", zbx_mock_error_string(error));
	}

	if (0 != setenv("TZ", "UTC", 1))
		fail_msg("Cannot set 'TZ' environment variable: %s", zbx_strerror(errno));

	tzset();

	zbx_json_initarray(&json, ZBX_KIBIBYTE);

	for (i = 0; NULL != package_managers[i].name; i++)
	{
		ZBX_PACKAGE_MANAGER	*mng = &package_managers[i];

		if (0 != strcmp(manager, mng->name))
			continue;

		manager_found = 1;

		line = strtok((char *)input, "\n");

		while (NULL != line)
		{
			mng->details_parser(mng->name, line, NULL, &json);

			line = strtok(NULL, "\n");
		}

		break;
	}

	if (0 == manager_found)
	{
		zbx_json_free(&json);
		fail_msg("The package manager '%s' specified in the test case is unknown", manager);
	}

	printf("%s\n", json.buffer);
	zbx_mock_assert_str_eq("Unexpected value", result, json.buffer);

	zbx_json_free(&json);
}
