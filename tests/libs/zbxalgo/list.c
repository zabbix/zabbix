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
#include <stdlib.h>

#include "zbxalgo.h"

#define ZBX_LIST_TEST_ITERATIONS	117

#define	RANGE		1

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

	for (j = 0; j < iterations; j++)
	{
		for (i = 0; i < values->values_num; i++)
		{
			(void)zbx_list_append(&list, values->values[i], NULL);
		}

		for (i = 0; i < values->values_num; i++)
		{
			if (FAIL == zbx_list_pop(&list, &ptr))
			{
				fail_msg("cannot pop list element: %d", i);
			}
			zbx_mock_assert_ptr_eq("value", ptr, values->values[i]);
		}

		if (SUCCEED == zbx_list_pop(&list, ptr))
		{
			fail_msg("succeeded to pop empty list");
		}
		zbx_mock_assert_ptr_eq("value", NULL, ptr);
	}

	zbx_list_destroy(&list);
}

static void test_list_range(void)
{
	zbx_vector_ptr_t	values;

	zbx_vector_ptr_create(&values);
	mock_read_values(zbx_mock_get_parameter_handle("in.values"), &values);
	test_list_range_values(ZBX_LIST_TEST_ITERATIONS, &values);
	zbx_vector_ptr_destroy(&values);
}

static int get_type(const char *str)
{
	if (0 == strcmp(str, "RANGE"))
		return RANGE;

	fail_msg("unknown cmocka step type: %s", str);
	return FAIL;
}

void	zbx_mock_test_entry(void **state)
{
	ZBX_UNUSED(state);

	switch (get_type(zbx_mock_get_parameter_string("in.type")))
	{
		case RANGE:
			test_list_range();
			break;
		default:
			fail_msg("unknown cmocka step type: %s", zbx_mock_get_parameter_string("in.type"));
	}
}
