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
#include "zbxmockdb.h"

#include "common.h"
#include "zbxalgo.h"
#include "db.h"

void	zbx_mock_test_entry(void **state)
{
	zbx_mock_error_t	error;
	zbx_mock_handle_t	in_sql, expected_results, expected_result;
	const char		*sql;
	zbx_vector_uint64_t	actual_results;
	int			i;

	ZBX_UNUSED(state);

	zbx_mockdb_init();

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("sql", &in_sql)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(in_sql, &sql)))
	{
		fail_msg("Cannot get SQL query from test case data: %s", zbx_mock_error_string(error));
	}

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("uint64", &expected_results)))
		fail_msg("Cannot get expected results from test case data: %s", zbx_mock_error_string(error));

	zbx_vector_uint64_create(&actual_results);
	DBselect_uint64(sql, &actual_results);

	for (i = 0; ZBX_MOCK_SUCCESS == (error = zbx_mock_vector_element(expected_results, &expected_result)); i++)
	{
		const char	*expected_result_string;
		zbx_uint64_t	expected_result_uint64;

		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_string(expected_result, &expected_result_string)))
			break;

		if (SUCCEED != is_uint64(expected_result_string, &expected_result_uint64))
			fail_msg("Cannot convert expected result #%d to unsigned 64 bit integer.", i + 1);

		if (i >= actual_results.values_num)
			fail_msg("There are fewer actual results (%d) than expected.", actual_results.values_num);

		if (expected_result_uint64 != actual_results.values[i])
		{
			fail_msg("Unexpected result #%d: " ZBX_FS_UI64 " instead of " ZBX_FS_UI64 ".", i + 1,
					actual_results.values[i], expected_result_uint64);
		}
	}

	if (ZBX_MOCK_END_OF_VECTOR != error)
		fail_msg("Cannot get expected result #%d from test case data: %s", i + 1, zbx_mock_error_string(error));

	if (i < actual_results.values_num)
		fail_msg("There are more actual results (%d) than expected (%d).", i, actual_results.values_num);

	zbx_vector_uint64_destroy(&actual_results);

	zbx_mockdb_destroy();
}
