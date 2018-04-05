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

#include "../zbxcunit/zbxcunit.h"

#define ZBX_QUEUE_TEST_ITERATIONS		117

static int	cu_init_empty()
{
	return CUE_SUCCESS;
}

static int	cu_clean_empty()
{
	return CUE_SUCCESS;
}

static void	test_queue_range_values(int iterations, zbx_vector_ptr_t *values)
{
	zbx_queue_ptr_t	queue;
	void		*ptr;
	int		i, j;

	ZBX_CU_LEAK_CHECK_START();

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
			ZBX_CU_ASSERT_INT_EQ_FATAL(NULL, zbx_queue_ptr_values_num(&queue), i + 1);
		}

		for (i = 0; i < values->values_num; i++)
		{
			ptr = zbx_queue_ptr_pop(&queue);
			ZBX_CU_ASSERT_UINT64_EQ(NULL, (zbx_uint64_t)ptr, (zbx_uint64_t)values->values[i]);
		}

		ptr = zbx_queue_ptr_pop(&queue);
		ZBX_CU_ASSERT_PTR_NULL_FATAL(NULL, ptr);
	}

	zbx_queue_ptr_destroy(&queue);

	ZBX_CU_LEAK_CHECK_END();
}

static void	test_queue_range(int iterations, ...)
{
	zbx_vector_ptr_t	values;
	va_list			args;
	int			value;

	zbx_vector_ptr_create(&values);

	va_start(args, iterations);

	while (0 != (value = va_arg(args, int)))
		zbx_vector_ptr_append(&values, (void *)(zbx_uint64_t)value);

	test_queue_range_values(iterations, &values);

	zbx_vector_ptr_destroy(&values);
	va_end(args);
}

static void	test_queue_ptr_basic()
{
	test_queue_range(ZBX_QUEUE_TEST_ITERATIONS, 1, 0);
	test_queue_range(ZBX_QUEUE_TEST_ITERATIONS, 1, 2, 0);
	test_queue_range(ZBX_QUEUE_TEST_ITERATIONS, 1, 2, 3, 0);
	test_queue_range(ZBX_QUEUE_TEST_ITERATIONS, 1, 2, 3, 4, 0);
	test_queue_range(ZBX_QUEUE_TEST_ITERATIONS, 1, 2, 3, 4, 5, 0);
	test_queue_range(ZBX_QUEUE_TEST_ITERATIONS, 1, 2, 3, 4, 5, 6, 0);
	test_queue_range(ZBX_QUEUE_TEST_ITERATIONS, 1, 2, 3, 4, 5, 6, 7, 0);
}

static void	test_queue_ptr_compact()
{
	zbx_queue_ptr_t	queue;
	void		*ptr;
	int		i;
	void		*values[] = {(void *)1, (void *)2, (void *)3, (void *)4, (void *)5, (void *)6, (void *)7};

	ZBX_CU_LEAK_CHECK_START();

	zbx_queue_ptr_create(&queue);

	/* test compacting when tail is before head (no data wraparound) */

	/* fill the queue */
	for (i = 0; i < (int)ARRSIZE(values); i++)
	{
		zbx_queue_ptr_push(&queue, values[i]);
		ZBX_CU_ASSERT_INT_EQ_FATAL(NULL, zbx_queue_ptr_values_num(&queue), i + 1);
	}

	/* pop all elements, compacting queue after each pop */
	for (i = 0; i < (int)ARRSIZE(values); i++)
	{
		ptr = zbx_queue_ptr_pop(&queue);
		ZBX_CU_ASSERT_UINT64_EQ(NULL, (zbx_uint64_t)ptr, (zbx_uint64_t)values[i]);

		zbx_queue_ptr_compact(&queue);
		ZBX_CU_ASSERT_INT_EQ_FATAL(NULL, queue.alloc_num, (int)(ARRSIZE(values) - i));
	}

	zbx_queue_ptr_destroy(&queue);

	/* test compacting when head is before tail (data wraparound with empty space in the middle */

	zbx_queue_ptr_create(&queue);
	zbx_queue_ptr_reserve(&queue, ARRSIZE(values) * 1.5);
	ZBX_CU_ASSERT_INT_EQ_FATAL(NULL, queue.alloc_num, (int)(ARRSIZE(values) * 1.5 + 1));

	/* move tail/head positions towards the end of queue buffer so that pushing all values will */
	/* result in data wraparound                                                                */
	queue.tail_pos = ARRSIZE(values);
	queue.head_pos = queue.tail_pos;

	/* fill the queue */
	for (i = 0; i < (int)ARRSIZE(values); i++)
	{
		zbx_queue_ptr_push(&queue, values[i]);
		ZBX_CU_ASSERT_INT_EQ_FATAL(NULL, zbx_queue_ptr_values_num(&queue), i + 1);
	}

	/* compact the queue by removing the free slots in the middle of its buffer */
	zbx_queue_ptr_compact(&queue);
	ZBX_CU_ASSERT_INT_EQ_FATAL(NULL, queue.alloc_num, (int)ARRSIZE(values) + 1);

	/* verify the data */
	for (i = 0; i < (int)ARRSIZE(values); i++)
	{
		ptr = zbx_queue_ptr_pop(&queue);
		ZBX_CU_ASSERT_UINT64_EQ(NULL, (zbx_uint64_t)ptr, (zbx_uint64_t)values[i]);

		zbx_queue_ptr_compact(&queue);
		ZBX_CU_ASSERT_INT_EQ_FATAL(NULL, queue.alloc_num, (int)(ARRSIZE(values) - i));
	}

	zbx_queue_ptr_destroy(&queue);

	ZBX_CU_LEAK_CHECK_END();
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
		ZBX_CU_ASSERT_UINT64_EQ(NULL, (zbx_uint64_t)ptr, (zbx_uint64_t)values[i]);
	}
}

static void	test_queue_ptr_remove()
{
	void		*values[] = {(void *)1, (void *)2, (void *)3, (void *)4, (void *)5, (void *)6, (void *)7};
	int		i, j;
	zbx_queue_ptr_t	queue;

	ZBX_CU_LEAK_CHECK_START();

	/* test removal when tail is before head (no data wraparound) */

	/* try removing value that is not in queue */

	zbx_queue_ptr_create(&queue);

	for (i = 0; i < (int)ARRSIZE(values); i++)
		zbx_queue_ptr_push(&queue, values[i]);

	test_queue_ptr_remove_value(&queue, values, ARRSIZE(values), (void *)10);

	zbx_queue_ptr_destroy(&queue);

	/* try removing every value from queue */

	for (j = 0; j < (int)ARRSIZE(values); j++)
	{
		zbx_queue_ptr_create(&queue);

		for (i = 0; i < (int)ARRSIZE(values); i++)
			zbx_queue_ptr_push(&queue, values[i]);

		test_queue_ptr_remove_value(&queue, values, ARRSIZE(values), values[j]);

		zbx_queue_ptr_destroy(&queue);
	}

	/* test removal when tail is after head (data wraparound) */

	/* try removing value that is not in queue */

	zbx_queue_ptr_create(&queue);
	zbx_queue_ptr_reserve(&queue, ARRSIZE(values) * 1.5);
	queue.tail_pos = ARRSIZE(values);
	queue.head_pos = queue.tail_pos;

	for (i = 0; i < (int)ARRSIZE(values); i++)
		zbx_queue_ptr_push(&queue, values[i]);

	test_queue_ptr_remove_value(&queue, values, ARRSIZE(values), (void *)10);

	zbx_queue_ptr_destroy(&queue);

	/* try removing every value from queue */

	for (j = 0; j < (int)ARRSIZE(values); j++)
	{
		zbx_queue_ptr_create(&queue);
		zbx_queue_ptr_reserve(&queue, ARRSIZE(values) * 1.5);
		queue.tail_pos = ARRSIZE(values);
		queue.head_pos = queue.tail_pos;

		for (i = 0; i < (int)ARRSIZE(values); i++)
			zbx_queue_ptr_push(&queue, values[i]);

		test_queue_ptr_remove_value(&queue, values, ARRSIZE(values), values[j]);

		zbx_queue_ptr_destroy(&queue);
	}

	ZBX_CU_LEAK_CHECK_END();
}

int	ZBX_CU_DECLARE(queue)
{
	CU_pSuite	suite = NULL;

	/* test suite: zbx_user_macro_parse() */
	if (NULL == (suite = CU_add_suite("zbx_queue_ptr_t", cu_init_empty, cu_clean_empty)))
		return CU_get_error();

	ZBX_CU_ADD_TEST(suite, test_queue_ptr_basic);
	ZBX_CU_ADD_TEST(suite, test_queue_ptr_compact);
	ZBX_CU_ADD_TEST(suite, test_queue_ptr_remove);

	return CUE_SUCCESS;
}
