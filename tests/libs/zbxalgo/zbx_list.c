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

static void	mock_read_values(zbx_mock_handle_t hdata, zbx_vector_ptr_t *values)
{
	zbx_mock_error_t	err;
	zbx_mock_handle_t	hvalue;

	while (ZBX_MOCK_END_OF_VECTOR != (err = (zbx_mock_vector_element(hdata, &hvalue))))
	{
		zbx_uint64_t	value;

		if (ZBX_MOCK_SUCCESS != (err = zbx_mock_uint64(hvalue, &value)))
		{
			fail_msg("Cannot read vector member: %s", zbx_mock_error_string(err));
		}

		zbx_vector_ptr_append(values, (void *)value);
	}
}

static void	test_list_range_values(int iterations, zbx_vector_ptr_t *values)
{
	zbx_list_t	list;
	void		*ptr;
	int		i, j;

	zbx_list_create(&list);

	for (i = 0; i < iterations; i++)
	{
		for (j = 0; j < values->values_num; j++)
		{
			(void)zbx_list_append(&list, values->values[j], NULL);
		}

		for (j = 0; j < values->values_num; j++)
		{
			if (FAIL == zbx_list_pop(&list, &ptr))
			{
				fail_msg("cannot pop list element: %d", j);
			}
			zbx_mock_assert_ptr_eq("value", ptr, values->values[j]);
		}

		if (SUCCEED == zbx_list_pop(&list, ptr))
		{
			fail_msg("succeeded to pop empty list");
		}
		zbx_mock_assert_ptr_eq("value", NULL, ptr);
	}

	zbx_list_destroy(&list);
}

#define	ZBX_LIST_TEST_ITERATIONS	117

static void	test_list_range(void)
{
	zbx_vector_ptr_t	values;

	zbx_vector_ptr_create(&values);
	mock_read_values(zbx_mock_get_parameter_handle("in.values"), &values);
	test_list_range_values(ZBX_LIST_TEST_ITERATIONS, &values);
	zbx_vector_ptr_destroy(&values);
}

#undef	ZBX_LIST_TEST_ITERATIONS


static void	test_list_iterator_equal(void)
{
	zbx_list_t		list;
	zbx_vector_ptr_t	values;
	zbx_list_iterator_t	iterator_first, iterator_second;
	int			exp_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.result"));
	int			steps = zbx_mock_get_parameter_int("in.steps");

	zbx_vector_ptr_create(&values);
	zbx_mock_extract_yaml_values_ptr(zbx_mock_get_parameter_handle("in.values"), &values);
	zbx_list_create(&list);
	vector_to_list(&list, values);

	zbx_list_iterator_init(&list, &iterator_first);
	zbx_list_iterator_init(&list, &iterator_second);

	for (int i = 0; i < steps; i++)
	{
		if (SUCCEED != zbx_list_iterator_next(&iterator_second))
			fail_msg("failed iterate next");
	}

	zbx_mock_assert_int_eq("return value", exp_result, zbx_list_iterator_equal(&iterator_first, &iterator_second));
	zbx_vector_ptr_destroy(&values);
	zbx_list_destroy(&list);
}

static void	test_list_iterator_init_with(void)
{
	zbx_list_t		list;
	zbx_vector_ptr_t	values;
	zbx_list_iterator_t	iterator;
	uint64_t		exp_value = zbx_mock_get_parameter_uint64("out.value");
	int			exp_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.result"));
	int			steps = zbx_mock_get_parameter_int("in.steps");

	zbx_vector_ptr_create(&values);
	zbx_mock_extract_yaml_values_ptr(zbx_mock_get_parameter_handle("in.values"), &values);
	zbx_list_create(&list);
	vector_to_list(&list, values);

	zbx_list_item_t	*specific_item = list.head;

	for (int i = 0; i < steps; i++)
		specific_item = specific_item->next;

	zbx_mock_assert_int_eq("return value", exp_result, zbx_list_iterator_init_with(&list, specific_item,
			&iterator));
	zbx_mock_assert_ptr_eq("return value", (void *)(uintptr_t)exp_value, iterator.current->data);
	zbx_vector_ptr_destroy(&values);
	zbx_list_destroy(&list);
}

static void	test_list_iterator_next(void)
{
	zbx_list_t		list;
	zbx_vector_ptr_t	values;
	zbx_list_iterator_t	iterator;
	int			steps = zbx_mock_get_parameter_int("in.steps");
	int			exp_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.result"));
	int			result = 0;

	zbx_list_create(&list);
	zbx_vector_ptr_create(&values);
	zbx_mock_extract_yaml_values_ptr(zbx_mock_get_parameter_handle("in.values"), &values);
	vector_to_list(&list, values);
	zbx_list_iterator_init(&list, &iterator);

	for (int i = 0; i < steps; i++)
		result = zbx_list_iterator_next(&iterator);

	zbx_mock_assert_int_eq("return value", exp_result, result);

	if (SUCCEED != zbx_mock_parameter_exists("out.no_value"))
	{
		uint64_t	exp_value = zbx_mock_get_parameter_uint64("out.value");
		zbx_mock_assert_ptr_eq("return value data", (void *)(uintptr_t)exp_value, iterator.current->data);
	}

	zbx_vector_ptr_destroy(&values);
	zbx_list_destroy(&list);
	zbx_list_iterator_clear(&iterator);
}

static void	test_list_iterator_peek(void)
{
	zbx_list_t		list;
	zbx_vector_ptr_t	values;
	zbx_list_iterator_t	iterator;
	void			*value;
	zbx_uint64_t		exp_value = zbx_mock_get_parameter_uint64("out.value");
	int			exp_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.result"));
	int			steps = zbx_mock_get_parameter_int("in.steps");

	zbx_vector_ptr_create(&values);
	zbx_mock_extract_yaml_values_ptr(zbx_mock_get_parameter_handle("in.values"), &values);
	zbx_list_create(&list);
	vector_to_list(&list, values);

	zbx_list_iterator_init(&list, &iterator);

	for (int i = 0; i < steps; i++)
	{
		if (SUCCEED != zbx_list_iterator_next(&iterator))
			fail_msg("failed iterate next");
	}

	int	result = zbx_list_iterator_peek(&iterator, &value);

	zbx_mock_assert_int_eq("return value", exp_result, result);
	zbx_mock_assert_ptr_eq("return value", (void *)(uintptr_t)exp_value, value);

	zbx_vector_ptr_destroy(&values);
	zbx_list_destroy(&list);
}

static void	test_list_iterator_remove_next(void)
{
	zbx_list_t		list;
	zbx_vector_ptr_t	values;
	zbx_list_iterator_t	iterator;
	int			exp_value = zbx_mock_get_parameter_int("out.value");
	uint64_t		exp_result = zbx_mock_get_parameter_uint64("out.result");
	int			steps = zbx_mock_get_parameter_int("in.steps");

	zbx_vector_ptr_create(&values);
	zbx_mock_extract_yaml_values_ptr(zbx_mock_get_parameter_handle("in.values"), &values);
	zbx_list_create(&list);
	vector_to_list(&list, values);

	zbx_list_iterator_init(&list, &iterator);

	for (int i = 0; i < steps; i++)
	{
		if (SUCCEED != zbx_list_iterator_next(&iterator))
			fail_msg("failed iterator next");
	}

	zbx_mock_assert_ptr_eq("return value", (void *)(uintptr_t)exp_result, zbx_list_iterator_remove_next(&iterator));
	zbx_mock_assert_ptr_eq("return value", (void *)(uintptr_t)exp_value, iterator.current->data);

	zbx_vector_ptr_destroy(&values);
	zbx_list_destroy(&list);
}

static void	test_list_peek(void)
{
	zbx_list_t		list;
	zbx_vector_ptr_t	values;
	void			*value;
	int			exp_value = zbx_mock_get_parameter_int("out.value");
	int			exp_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.result"));

	zbx_list_create(&list);
	zbx_vector_ptr_create(&values);

	if (SUCCEED != zbx_mock_parameter_exists("in.is_empty"))
	{
		zbx_mock_extract_yaml_values_ptr(zbx_mock_get_parameter_handle("in.values"), &values);
		vector_to_list(&list, values);
	}

	int	result = zbx_list_peek(&list, &value);

	zbx_mock_assert_int_eq("return value", exp_result, result);

	if (SUCCEED != zbx_mock_parameter_exists("in.is_empty"))
		zbx_mock_assert_ptr_eq("return value", (void *)(uintptr_t)exp_value, list.head->data);

	zbx_vector_ptr_destroy(&values);
	zbx_list_destroy(&list);
}

static void	test_list_prepend(void)
{
	zbx_list_t		list;
	zbx_list_item_t		*new_item;
	zbx_vector_ptr_t	values;
	int			value = zbx_mock_get_parameter_int("in.value");
	int			exp_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.result"));

	zbx_vector_ptr_create(&values);
	zbx_mock_extract_yaml_values_ptr(zbx_mock_get_parameter_handle("in.values"), &values);
	zbx_list_create(&list);
	vector_to_list(&list, values);

	int	result = zbx_list_prepend(&list, (void *)(intptr_t)value, &new_item);

	zbx_mock_assert_int_eq("return value", exp_result, result);
	zbx_mock_assert_ptr_eq("return value", (void *)(uintptr_t)value, list.head->data);
	zbx_vector_ptr_destroy(&values);
	zbx_list_destroy(&list);
}

#define	TEST_RANGE				1
#define	TEST_ITERATOR_EQUAL			2
#define	TEST_LIST_ITERATOR_INIT_WITH		3
#define	TEST_LIST_ITERATOR_NEXT			4
#define	TEST_TEST_LIST_ITERATOR_PEEK		5
#define	TEST_LIST_ITERATOR_REMOVE_NEXT		6
#define	TEST_LIST_PEEK				7
#define	TEST_LIST_PREPEND			8

static int	get_step_type_int(const char *str)
{
	if (0 == strcmp(str, "RANGE"))
		return TEST_RANGE;
	if (0 == strcmp(str, "ITERATOR_EQUAL"))
		return TEST_ITERATOR_EQUAL;
	if (0 == strcmp(str, "LIST_ITERATOR_INIT_WITH"))
		return TEST_LIST_ITERATOR_INIT_WITH;
	if (0 == strcmp(str, "LIST_ITERATOR_NEXT"))
		return TEST_LIST_ITERATOR_NEXT;
	if (0 == strcmp(str, "TEST_LIST_ITERATOR_PEEK"))
		return TEST_TEST_LIST_ITERATOR_PEEK;
	if (0 == strcmp(str, "LIST_ITERATOR_REMOVE_NEXT"))
		return TEST_LIST_ITERATOR_REMOVE_NEXT;
	if (0 == strcmp(str, "LIST_PEEK"))
		return TEST_LIST_PEEK;
	if (0 == strcmp(str, "LIST_PREPEND"))
		return TEST_LIST_PREPEND;

	fail_msg("unknown cmocka step type: %s", str);

	return FAIL;
}

void	zbx_mock_test_entry(void **state)
{
	ZBX_UNUSED(state);

	switch (get_step_type_int(zbx_mock_get_parameter_string("in.type")))
	{
		case TEST_RANGE:
			test_list_range();
			break;
		case TEST_ITERATOR_EQUAL:
			test_list_iterator_equal();
			break;
		case TEST_LIST_ITERATOR_INIT_WITH:
			test_list_iterator_init_with();
			break;
		case TEST_LIST_ITERATOR_NEXT:
			test_list_iterator_next();
			break;
		case TEST_TEST_LIST_ITERATOR_PEEK:
			test_list_iterator_peek();
			break;
		case TEST_LIST_ITERATOR_REMOVE_NEXT:
			test_list_iterator_remove_next();
			break;
		case TEST_LIST_PEEK:
			test_list_peek();
			break;
		case TEST_LIST_PREPEND:
			test_list_prepend();
			break;
		default:
			fail_msg("unknown func type: %s", zbx_mock_get_parameter_string("in.type"));
	}
}

#undef	TEST_RANGE
#undef	TEST_ITERATOR_EQUAL
#undef	TEST_LIST_ITERATOR_INIT_WITH
#undef	TEST_LIST_ITERATOR_NEXT
#undef	TEST_TEST_LIST_ITERATOR_PEEK
#undef	TEST_LIST_ITERATOR_REMOVE_NEXT
#undef	TEST_LIST_PEEK
#undef	TEST_LIST_PREPEND
