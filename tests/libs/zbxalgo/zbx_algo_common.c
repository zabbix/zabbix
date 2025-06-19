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

void	vector_to_list(zbx_list_t *list, zbx_vector_ptr_t values)
{
	for (int i = 0; i < values.values_num; i++)
		zbx_list_append(list, values.values[i], NULL);
}

void	extract_yaml_values_uint64(const char *path, zbx_vector_uint64_t *values)
{
	zbx_mock_handle_t	hvalues, hvalue;
	zbx_mock_error_t	err;
	int			value_num = 0;

	hvalues = zbx_mock_get_parameter_handle(path);

	while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(hvalues, &hvalue))))
	{
		size_t	value;

		if (ZBX_MOCK_SUCCESS != zbx_mock_uint64(hvalue, &value))
		{
			value = 1;
			fail_msg("Cannot read value #%d", value_num);
		}

		zbx_vector_uint64_append(values, value);

		value_num++;
	}
}

int	binary_heap_elem_compare(const void *d1, const void *d2)
{
	const zbx_binary_heap_elem_t	*e1 = (const zbx_binary_heap_elem_t *)d1;
	const zbx_binary_heap_elem_t	*e2 = (const zbx_binary_heap_elem_t *)d2;

	if (e1->data < e2->data)
		return -1;
	else if (e1->data > e2->data)
		return 1;
	else
		return 0;
}

void	dump_binary_heap(const char *name, const zbx_binary_heap_t *heap_in)
{
	printf("heap: %s\t", name);

	zbx_binary_heap_t	heap = *heap_in;
	int			orig_heap_size = heap.elems_num;

	for (int i = 0; SUCCEED != zbx_binary_heap_empty(&heap); i++)
	{
		zbx_binary_heap_elem_t	*min_heap = zbx_binary_heap_find_min(&heap);

		printf("[%lu, %d]", min_heap->key, (int)(uintptr_t)min_heap->data);

		if (orig_heap_size - 1 != i)
			printf(", ");

		zbx_binary_heap_remove_min(&heap);
	}

	printf("\n");

}

int	binary_heaps_are_same(const zbx_binary_heap_t *heap1_in, const zbx_binary_heap_t *heap2_in)
{
	if (heap1_in->elems_num != heap2_in->elems_num)
		return FAIL;

	zbx_binary_heap_t	heap1, heap2;

	heap1 = *heap1_in;
	heap2 = *heap2_in;

	for (int i = 0; i < heap1.elems_num; i++)
	{
		zbx_binary_heap_elem_t	*min_heap1, *min_heap2;

		min_heap1 = zbx_binary_heap_find_min(&heap1);
		min_heap2 = zbx_binary_heap_find_min(&heap2);

		if (0 != heap1.compare_func(min_heap1, min_heap2))
			return FAIL;

		if (0 != heap2.compare_func(min_heap1, min_heap2))
			return FAIL;

		zbx_binary_heap_remove_min(&heap1);
		zbx_binary_heap_remove_min(&heap2);
	}

	return SUCCEED;
}

void	extract_binary_heap_from_yaml_int(zbx_binary_heap_t *heap, zbx_mock_handle_t *handle)
{
	zbx_mock_error_t	error;
	zbx_mock_handle_t	vec_handle;

	while (ZBX_MOCK_SUCCESS == zbx_mock_vector_element(*handle, &vec_handle))
	{
		int			val;
		zbx_uint64_t		key;
		zbx_mock_handle_t	vec_elem_handle;

		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_vector_element(vec_handle, &vec_elem_handle)))
		{
			fail_msg("failed to get first vector element %s", zbx_mock_error_string(error));
			return;
		}

		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_uint64(vec_elem_handle, &key)))
		{
			fail_msg("failed to extract key %s", zbx_mock_error_string(error));
			return;
		}

		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_vector_element(vec_handle, &vec_elem_handle)))
		{
			fail_msg("failed to get second vector element %s", zbx_mock_error_string(error));
			return;
		}

		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_int(vec_elem_handle, &val)))
		{
			fail_msg("failed to extract value %s", zbx_mock_error_string(error));
			return;
		}

		zbx_binary_heap_elem_t	elem;
		elem.key = key;
		elem.data = (void *)(uintptr_t)val;
		zbx_binary_heap_insert(heap, &elem);
	}
}
