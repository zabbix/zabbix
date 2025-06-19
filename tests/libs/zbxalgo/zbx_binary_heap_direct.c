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

#include "zbxalgo.h"

#include "zbx_algo_common.h"

void	zbx_mock_test_entry(void **state)
{
	size_t			upd_key = zbx_mock_get_parameter_uint64("in.upd_key"),
				upd_data = zbx_mock_get_parameter_uint64("in.upd_data");

	zbx_binary_heap_t	heap_in, heap_out;

	ZBX_UNUSED(state);

	zbx_binary_heap_create(&heap_in, binary_heap_elem_compare, ZBX_BINARY_HEAP_OPTION_DIRECT);
	zbx_binary_heap_create(&heap_out, binary_heap_elem_compare, ZBX_BINARY_HEAP_OPTION_DIRECT);

	zbx_binary_heap_elem_t	update_element;

	zbx_mock_error_t	error;
	zbx_mock_handle_t	handle_in, handle_out;

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("heap_in", &handle_in)))
	{
		fail_msg("failed to extract input heap %s", zbx_mock_error_string(error));
		return;
	}

	if (SUCCEED == zbx_mock_parameter_exists("in.elements"))
	{
		int	elements = zbx_mock_get_parameter_int("in.elements");

		for (int i = 0; elements < i; i++)
		{
			extract_binary_heap_from_yaml_int(&heap_in, &handle_in);
		}
	}
	else
		extract_binary_heap_from_yaml_int(&heap_in, &handle_in);

	int	exit_code;

	if (ZBX_MOCK_NO_EXIT_CODE == zbx_mock_exit_code(&exit_code))
	{
		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("heap_out", &handle_out)))
		{
			fail_msg("failed to extract expected heap %s", zbx_mock_error_string(error));
			return;
		}
	}

	if (SUCCEED == zbx_mock_parameter_exists("in.elements"))
	{
		int	elements = zbx_mock_get_parameter_int("in.elements");

		for (int i = 0; elements < i; i++)
		{
			extract_binary_heap_from_yaml_int(&heap_out, &handle_out);
		}
	}
	else
		extract_binary_heap_from_yaml_int(&heap_out, &handle_out);

	update_element.key = upd_key;
	update_element.data = (void *)(uintptr_t)upd_data;

	if (SUCCEED != zbx_mock_parameter_exists("in.elements"))
		zbx_binary_heap_update_direct(&heap_in, &update_element);

	if (ZBX_MOCK_NO_EXIT_CODE != (error = zbx_mock_exit_code(&exit_code)))
	{
		if (ZBX_MOCK_SUCCESS == error)
			fail_msg("exit(%d) expected", exit_code);
		else
			fail_msg("Cannot get exit code from test case data: %s", zbx_mock_error_string(error));
	}

	if (SUCCEED != binary_heaps_are_same(&heap_in, &heap_out))
	{
		dump_binary_heap("resulting heap", &heap_in);
		dump_binary_heap("expected heap", &heap_out);
		fail_msg("heaps are different");
	}

	zbx_binary_heap_clear(&heap_in);
	zbx_free(heap_in.elems);
	zbx_binary_heap_destroy(&heap_in);

	zbx_binary_heap_clear(&heap_out);
	zbx_free(heap_out.elems);
	zbx_binary_heap_destroy(&heap_out);
}
