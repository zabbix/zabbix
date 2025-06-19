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
#include "zbxstr.h"

#include "zbx_algo_common.h"

static void	read_yaml_and_compare_binary_heap_elem(zbx_binary_heap_elem_t *elem)
{
	zbx_uint64_t	key = zbx_mock_get_parameter_uint64("out.key");
	int		data = zbx_mock_get_parameter_int("out.data");

	if (elem->data != (void *)(uintptr_t)data || elem->key != key)
	{

	fail_msg("wrong element. Expected: key " ZBX_FS_SIZE_T " data %d\nGot key " ZBX_FS_SIZE_T " data "
			ZBX_FS_SIZE_T, key, data, (size_t)elem->key, (size_t)elem->data);

	}
}

void	zbx_mock_test_entry(void **state)
{
	const char		*func = zbx_mock_get_parameter_string("in.func");

	zbx_binary_heap_t	heap_in, heap_out;

	ZBX_UNUSED(state);

	zbx_binary_heap_create(&heap_in, binary_heap_elem_compare, 0);
	zbx_binary_heap_create(&heap_out, binary_heap_elem_compare, 0);

	zbx_binary_heap_elem_t	*min_element;

	zbx_mock_error_t	error;
	zbx_mock_handle_t	handle_in, handle_out;

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("heap_in", &handle_in)))
	{
		fail_msg("failed to extract input heap %s", zbx_mock_error_string(error));
		return;
	}
	extract_binary_heap_from_yaml_int(&heap_in, &handle_in);

	if (SUCCEED == zbx_strcmp_natural(func, "find_min"))
	{
		min_element = zbx_binary_heap_find_min(&heap_in);
		read_yaml_and_compare_binary_heap_elem(min_element);
	}
	else if (SUCCEED == zbx_strcmp_natural(func, "remove_min"))
	{
		zbx_binary_heap_remove_min(&heap_in);

		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("heap_out", &handle_out)))
		{
			fail_msg("failed to extract expected heap %s", zbx_mock_error_string(error));
			return;
		}
		extract_binary_heap_from_yaml_int(&heap_out, &handle_out);

		if (SUCCEED != binary_heaps_are_same(&heap_in, &heap_out))
		{
			dump_binary_heap("resulting heap", &heap_in);
			dump_binary_heap("expected heap", &heap_out);
			fail_msg("heaps are different");
		}
	}

	zbx_binary_heap_clear(&heap_in);
	zbx_free(heap_in.elems);
	zbx_binary_heap_destroy(&heap_in);

	zbx_binary_heap_clear(&heap_out);
	zbx_free(heap_out.elems);
	zbx_binary_heap_destroy(&heap_out);
}
