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
#include <stdlib.h>

#include "zbxalgo.h"

#define ZBX_CHANNEL_TEST_ITERATIONS 117

#define RANGE           1
#define COMPACT_TH      2
#define COMPACT_HT      3

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

static void	test_channel_range_values(int iterations, int msg_size, zbx_vector_ptr_t *values)
{
	zbx_channel_t	chan;
	void		*ptr;
	int		i, j, ret;

	unsigned short	value_offset = (unsigned short)((sizeof(zbx_uint64_t) - msg_size) << 8);
	size_t		offset = *((unsigned char *)&value_offset);

	zbx_chan_init(&chan, msg_size, 0);

	/* Test sending/receiving values from channel. Channel buffer size is always larger than the */
	/* number of stored values, therefore sending and receiving N values from the channel will    */
	/* have different head/tail positions for each iteration, resulting in good brute force test  */
	/* with using various positions.                                                              */
	for (j = 0; j < iterations; j++)
	{
		for (i = 0; i < values->values_num; i++)
		{
			zbx_chan_send(&chan, (unsigned char *)&values->values[i] + offset);
			zbx_mock_assert_int_eq("quantity", zbx_chan_msg_num(&chan), i + 1);
		}

		for (i = 0; i < values->values_num; i++)
		{
			ptr = NULL;
			ret = zbx_chan_recv_batch(&chan, &ptr, 1);

			zbx_mock_assert_int_eq("return", ret, 1);
			zbx_mock_assert_ptr_eq("value", ptr, values->values[i]);
		}

		ret = zbx_chan_recv_batch(&chan, &ptr, 1);
		zbx_mock_assert_int_eq("return", ret, 0);
	}

	zbx_chan_destroy(&chan);
}

static void	test_channel_range(void)
{
	zbx_vector_ptr_t	values;

	zbx_vector_ptr_create(&values);
	mock_read_values(zbx_mock_get_parameter_handle("in.values"), &values);

	for (int i = 1; i <= (int)sizeof(void *); i++)
		test_channel_range_values(ZBX_CHANNEL_TEST_ITERATIONS, i, &values);

	zbx_vector_ptr_destroy(&values);
}

static void test_channel_compact_head_tail(void)
{
	zbx_vector_ptr_t	values;
	zbx_channel_t		chan;
	void			*ptr;
	int			i;

	zbx_vector_ptr_create(&values);
	mock_read_values(zbx_mock_get_parameter_handle("in.values"), &values);

	zbx_chan_init(&chan, sizeof(void *), 0);

	/* fill the channel */
	for (i = 0; i < values.values_num; i++)
	{
		zbx_chan_send(&chan, &values.values[i]);
		zbx_mock_assert_int_eq("quantity", zbx_chan_msg_num(&chan), i + 1);
	}

	/* receive all elements, compacting channel after each receive */
	for (i = 0; i < values.values_num; i++)
	{
		int ret = zbx_chan_recv_batch(&chan, &ptr, 1);
		zbx_mock_assert_int_eq("recv_result", ret, 1);
		zbx_mock_assert_ptr_eq("value", ptr, values.values[i]);

		zbx_chan_compact(&chan, 0);
		zbx_mock_assert_int_eq("allocated memory", zbx_chan_capacity(&chan),
				(int)(values.values_num - i - 1));
	}

	zbx_chan_destroy(&chan);
	zbx_vector_ptr_destroy(&values);
}

static void test_channel_compact_tail_head(void)
{
	zbx_vector_ptr_t	values;
	zbx_channel_t		chan;
	void			*ptr;
	int			i, initial_capacity;

	zbx_vector_ptr_create(&values);
	mock_read_values(zbx_mock_get_parameter_handle("in.values"), &values);

	initial_capacity = (int)(values.values_num * 1.5);
	zbx_chan_init(&chan, sizeof(void *), initial_capacity);
	zbx_mock_assert_int_eq("allocated memory", zbx_chan_capacity(&chan), initial_capacity);

	/* fill channel partially to move head/tail, then drain and refill to create wraparound */
	for (i = 0; i < values.values_num / 2; i++)
	{
		zbx_chan_send(&chan, &values.values[i]);
	}
	for (i = 0; i < values.values_num / 2; i++)
	{
		zbx_chan_recv_batch(&chan, &ptr, 1);
	}

	/* fill the channel - this should cause wraparound */
	for (i = 0; i < values.values_num; i++)
	{
		zbx_chan_send(&chan, &values.values[i]);
		zbx_mock_assert_int_eq("quantity", zbx_chan_msg_num(&chan), i + 1);
	}

	/* compact the channel by removing the free slots */
	zbx_chan_compact(&chan, values.values_num);
	zbx_mock_assert_int_eq("allocated memory", zbx_chan_capacity(&chan), values.values_num);

	/* verify the data */
	for (i = 0; i < values.values_num; i++)
	{
		int ret = zbx_chan_recv_batch(&chan, &ptr, 1);
		zbx_mock_assert_int_eq("recv_result", ret, 1);
		zbx_mock_assert_ptr_eq("value", ptr, values.values[i]);

		zbx_chan_compact(&chan, 0);
		zbx_mock_assert_int_eq("allocated memory", zbx_chan_capacity(&chan),
				(int)(values.values_num - i - 1));
	}

	zbx_chan_destroy(&chan);
	zbx_vector_ptr_destroy(&values);
}

static int get_step_type_int(const char *str)
{
	if (0 == strcmp(str, "RANGE"))
		return RANGE;
	if (0 == strcmp(str, "COMPACT_TH"))
		return COMPACT_TH;
	if (0 == strcmp(str, "COMPACT_HT"))
		return COMPACT_HT;

	fail_msg("unknown cmocka step type: %s", str);
	return FAIL;
}

void	zbx_mock_test_entry(void **state)
{
	ZBX_UNUSED(state);

	switch (get_step_type_int(zbx_mock_get_parameter_string("in.type")))
	{
		case RANGE:
			test_channel_range();
			break;
		case COMPACT_TH:
			test_channel_compact_tail_head();
			break;
		case COMPACT_HT:
			test_channel_compact_head_tail();
			break;
		default:
			fail_msg("unknown cmocka step type: %s", zbx_mock_get_parameter_string("in.type"));
	}
}
