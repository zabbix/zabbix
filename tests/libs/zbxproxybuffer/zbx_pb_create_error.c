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
#include "zbxmockdb.h"

#include "zbxcommon.h"
#include "zbxmutexs.h"
#include "zbxproxybuffer.h"

#include "zbx_pb_mock_stubs.h"

void	zbx_mock_test_entry(void **state)
{
	char			*error = NULL;
	int			mode, create_result, expected_create_result;
	zbx_uint64_t		size;
	zbx_mock_handle_t	hparam;

	ZBX_UNUSED(state);

	mode = zbx_mock_get_parameter_int("in.mode");
	size = zbx_mock_get_parameter_uint64("in.size");
	expected_create_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.create_result"));

	zbx_mock_assert_result_eq("locks_create", SUCCEED, zbx_locks_create(&error));

	create_result = zbx_pb_create(mode, size, 0, 0, &error);
	zbx_mock_assert_result_eq("create result", expected_create_result, create_result);

	if (SUCCEED == create_result)
	{
		zbx_pb_mem_info_t	mem_info;
		zbx_pb_state_info_t	state_info;
		char			*mem_error = NULL;

		/* zbx_pb_init() queries the DB in disk/hybrid mode */
		zbx_mockdb_init();
		zbx_pb_init();
		zbx_mockdb_destroy();

		/* test get_mem_info */
		if (ZBX_MOCK_SUCCESS == zbx_mock_parameter("out.mem_info_result", &hparam))
		{
			int		mem_result, expected_mem_result;
			const char	*mem_result_str;

			zbx_mock_string(hparam, &mem_result_str);
			expected_mem_result = zbx_mock_str_to_return_code(mem_result_str);

			mem_result = zbx_pb_get_mem_info(&mem_info, &mem_error);
			zbx_mock_assert_result_eq("mem_info result", expected_mem_result, mem_result);

			if (SUCCEED == mem_result)
			{
				/* memory mode: buffer must report non-zero usage */
				zbx_mock_assert_uint64_ne("mem_total", 0, mem_info.mem_total);
				zbx_mock_assert_uint64_ne("mem_used", 0, mem_info.mem_used);
			}
			else
			{
				/* disk mode: error message must be set */
				zbx_mock_assert_ptr_ne("mem_error not NULL", NULL, mem_error);
				zbx_free(mem_error);
			}
		}

		/* test get_state_info */
		if (ZBX_MOCK_SUCCESS == zbx_mock_parameter("out.state", &hparam))
		{
			int	expected_state;

			zbx_mock_int(hparam, &expected_state);
			zbx_pb_get_state_info(&state_info);
			zbx_mock_assert_int_eq("state", expected_state, state_info.state);
		}

		zbx_pb_destroy();
	}
	else
	{
		/* create failure: error message must be set */
		zbx_mock_assert_ptr_ne("error not NULL on failure", NULL, error);
		zbx_free(error);
	}
}
