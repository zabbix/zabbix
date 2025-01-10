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

#define ZBX_QUEUE_TEST_ITERATIONS	117

#define	RANGE		1
#define	COMPACT_TH	2
#define	COMPACT_HT	3
#define	REMOVE_TH	4
#define	REMOVE_HT	5

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

static void	test_queue_range_values(int iterations, zbx_vector_ptr_t *values)
{
	zbx_queue_ptr_t	queue;
	void		*ptr;
	int		i, j;

	zbx_queue_ptr_create(&queue);

	/* Test pushing/popping values from queue. Queue buffer size is always larger than the number    */
	/* of stored values, therefore pushing and popping N values from the queue will have different   */
	/* head/tail positions for each iteration, resulting in good brute force test with using various */
	/* positions.                                                                                    */
	for (j = 0; j < iterations; j++)
	{
		for (i = 0; i < values->values_num; i++)
		{
			zbx_queue_ptr_push(&queue, values->values[i]);
			zbx_mock_assert_int_eq("quantity", zbx_queue_ptr_values_num(&queue), i + 1);
		}

		for (i = 0; i < values->values_num; i++)
		{
			ptr = zbx_queue_ptr_pop(&queue);
			zbx_mock_assert_ptr_eq("value", ptr, values->values[i]);
		}

		ptr = zbx_queue_ptr_pop(&queue);
		zbx_mock_assert_ptr_eq("value", NULL, ptr);
	}

	zbx_queue_ptr_destroy(&queue);
}

static void test_queue_range(void)
{
	zbx_vector_ptr_t	values;

	zbx_vector_ptr_create(&values);
	mock_read_values(zbx_mock_get_parameter_handle("in.values"), &values);
	test_queue_range_values(ZBX_QUEUE_TEST_ITERATIONS, &values);
	zbx_vector_ptr_destroy(&values);
}

static void test_queue_ptr_compact_tail_head(void)
{
	zbx_vector_ptr_t	values;
	zbx_queue_ptr_t		queue;
	void			*ptr;
	int			i;

	zbx_vector_ptr_create(&values);
	mock_read_values(zbx_mock_get_parameter_handle("in.values"), &values);

	zbx_queue_ptr_create(&queue);

	/* fill the queue */
	for (i = 0; i < values.values_num; i++)
	{
		zbx_queue_ptr_push(&queue, values.values[i]);
		zbx_mock_assert_int_eq("quantity", zbx_queue_ptr_values_num(&queue), i + 1);
	}

	/* pop all elements, compacting queue after each pop */
	for (i = 0; i < values.values_num; i++)
	{
		ptr = zbx_queue_ptr_pop(&queue);
		zbx_mock_assert_ptr_eq("value", ptr, values.values[i]);

		zbx_queue_ptr_compact(&queue);
		zbx_mock_assert_int_eq("allocated memory", queue.alloc_num, (int)(values.values_num - i));
	}

	zbx_queue_ptr_destroy(&queue);
	zbx_vector_ptr_destroy(&values);
}

static void test_queue_ptr_compact_head_tail(void)
{
	zbx_vector_ptr_t	values;
	zbx_queue_ptr_t		queue;
	void			*ptr;
	int			i;

	zbx_vector_ptr_create(&values);
	mock_read_values(zbx_mock_get_parameter_handle("in.values"), &values);

	zbx_queue_ptr_create(&queue);
	zbx_queue_ptr_reserve(&queue, (int)(values.values_num * 1.5));
	zbx_mock_assert_int_eq("allocated memory", queue.alloc_num, (int)(values.values_num * 1.5 + 1));

	/* move tail/head positions towards the end of queue buffer so that pushing all values will */
	/* result in data wraparound                                                                */
	queue.tail_pos = values.values_num;
	queue.head_pos = queue.tail_pos;

	/* fill the queue */
	for (i = 0; i < values.values_num; i++)
	{
		zbx_queue_ptr_push(&queue, values.values[i]);
		zbx_mock_assert_int_eq("quantity", zbx_queue_ptr_values_num(&queue), i + 1);
	}

	/* compact the queue by removing the free slots in the middle of its buffer */
	zbx_queue_ptr_compact(&queue);
	zbx_mock_assert_int_eq("allocated memory", queue.alloc_num, values.values_num + 1);

	/* verify the data */
	for (i = 0; i < values.values_num; i++)
	{
		ptr = zbx_queue_ptr_pop(&queue);
		zbx_mock_assert_ptr_eq("value", ptr, values.values[i]);

		zbx_queue_ptr_compact(&queue);
		zbx_mock_assert_int_eq("allocated memory", queue.alloc_num, (int)(values.values_num - i));
	}

	zbx_queue_ptr_destroy(&queue);
	zbx_vector_ptr_destroy(&values);
}

static void	test_queue_ptr_remove_value(zbx_queue_ptr_t *queue, void **values, int values_num, void *value)
{
	int	i;
	void	*ptr;

	/* remove single value from queue and check the queue contents */

	zbx_queue_ptr_remove_value(queue, value);

	for (i = 0; i < values_num; i++)
	{
		if (values[i] == value)
			continue;

		ptr = zbx_queue_ptr_pop(queue);
		zbx_mock_assert_ptr_eq("remove value", ptr, values[i]);
	}
}

static void test_queue_ptr_remove_tail_head(void)
{
	zbx_vector_ptr_t	values;
	zbx_queue_ptr_t		queue;
	int			i, j;

	zbx_vector_ptr_create(&values);
	mock_read_values(zbx_mock_get_parameter_handle("in.values"), &values);

	zbx_queue_ptr_create(&queue);

	/* try removing value that is not in queue */

	zbx_queue_ptr_create(&queue);

	for (i = 0; i < values.values_num; i++)
		zbx_queue_ptr_push(&queue, values.values[i]);

	test_queue_ptr_remove_value(&queue, values.values, values.values_num, (void *)10);

	zbx_queue_ptr_destroy(&queue);

	/* try removing every value from queue */

	for (j = 0; j < values.values_num; j++)
	{
		zbx_queue_ptr_create(&queue);

		for (i = 0; i < values.values_num; i++)
			zbx_queue_ptr_push(&queue, values.values[i]);

		test_queue_ptr_remove_value(&queue, values.values, values.values_num, values.values[j]);

		zbx_queue_ptr_destroy(&queue);
	}

	zbx_vector_ptr_destroy(&values);
}

static void test_queue_ptr_remove_head_tail(void)
{
	zbx_vector_ptr_t	values;
	zbx_queue_ptr_t		queue;
	int			i, j;

	zbx_vector_ptr_create(&values);
	mock_read_values(zbx_mock_get_parameter_handle("in.values"), &values);

	/* try removing value that is not in queue */

	zbx_queue_ptr_create(&queue);
	zbx_queue_ptr_reserve(&queue, (int)(values.values_num * 1.5));
	queue.tail_pos = values.values_num;
	queue.head_pos = queue.tail_pos;

	for (i = 0; i < values.values_num; i++)
		zbx_queue_ptr_push(&queue, values.values[i]);

	test_queue_ptr_remove_value(&queue, values.values, values.values_num, (void *)10);

	zbx_queue_ptr_destroy(&queue);

	/* try removing every value from queue */

	for (j = 0; j < values.values_num; j++)
	{
		zbx_queue_ptr_create(&queue);
		zbx_queue_ptr_reserve(&queue, (int)(values.values_num * 1.5));
		queue.tail_pos = values.values_num;
		queue.head_pos = queue.tail_pos;

		for (i = 0; i < values.values_num; i++)
			zbx_queue_ptr_push(&queue, values.values[i]);

		test_queue_ptr_remove_value(&queue, values.values, values.values_num, values.values[j]);

		zbx_queue_ptr_destroy(&queue);
	}

	zbx_vector_ptr_destroy(&values);
}

static int	get_step_type_int(const char *str)
{
	if (0 == strcmp(str, "RANGE"))
		return RANGE;
	if (0 == strcmp(str, "COMPACT_TH"))
		return COMPACT_TH;
	if (0 == strcmp(str, "COMPACT_HT"))
		return COMPACT_HT;
	if (0 == strcmp(str, "REMOVE_TH"))
		return REMOVE_TH;
	if (0 == strcmp(str, "REMOVE_HT"))
		return REMOVE_HT;

	fail_msg("unknown cmocka step type: %s", str);
	return FAIL;
}

void	zbx_mock_test_entry(void **state)
{
	ZBX_UNUSED(state);

	switch (get_step_type_int(zbx_mock_get_parameter_string("in.type")))
	{
		case RANGE:
			test_queue_range();
			break;
		case COMPACT_TH:
			test_queue_ptr_compact_tail_head();
			break;
		case COMPACT_HT:
			test_queue_ptr_compact_head_tail();
			break;
		case REMOVE_TH:
			test_queue_ptr_remove_tail_head();
			break;
		case REMOVE_HT:
			test_queue_ptr_remove_head_tail();
			break;
		default:
			fail_msg("unknown cmocka step type: %s", zbx_mock_get_parameter_string("in.type"));
	}
}
