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

#include "zbxcommon.h"
#include "zbxalgo.h"
#include "zbxjson.h"
#include "zbxmutexs.h"
#include "zbxcacheconfig.h"
#include "zbx_host_constants.h"
#include "zbx_item_constants.h"
#include "zbxproxybuffer.h"
#include "proxybuffer.h"
#include "pb_history.h"

#include "zbx_pb_mock_stubs.h"

void	zbx_mock_test_entry(void **state)
{
	char			*error = NULL;
	zbx_pb_t		*pb;
	zbx_pb_history_data_t	*handle;
	zbx_pb_history_t	*row;
	const char		*value;
	int			i, rows_written, expected_rows, count;
	zbx_list_iterator_t	li;
	struct zbx_json		j;
	zbx_uint64_t		lastid = 0;
	int			more, rows_got;
	zbx_mock_handle_t	hparam;
	/* fixed fields written for every row; safe to hardcode since pb_history_check_age() only
	 * purges rows already in pb->history (empty here) and runs once, in zbx_pb_history_close()
	 * below */
	zbx_uint64_t		itemid = 100;
	zbx_timespec_t		ts = {1234567890, 555};
	int			state_val = 0, flags = 0;
	time_t			write_clock = 1234567890;

	ZBX_UNUSED(state);

	value = zbx_mock_get_parameter_string("in.value");
	rows_written = zbx_mock_get_parameter_int("in.rows");
	expected_rows = zbx_mock_get_parameter_int("out.rows");

	zbx_pb_mock_set_nextid(1);
	/* zbx_pb_history_get_rows() below always reaches pb_history_export(); this test is not
	 * exercising its item/host filtering, so declare the sane default explicitly */
	zbx_pb_mock_set_item_status(SUCCEED, ITEM_STATUS_ACTIVE, HOST_STATUS_MONITORED);

	zbx_mock_assert_result_eq("locks_create", SUCCEED, zbx_locks_create(&error));
	zbx_mock_assert_result_eq("pb_create", SUCCEED,
			zbx_pb_create(ZBX_PB_MODE_MEMORY, ZBX_MEBIBYTE, 0, 0, &error));
	zbx_pb_init();

	handle = zbx_pb_history_open();

	for (i = 0; i < rows_written; i++)
		zbx_pb_history_write_value(handle, itemid, state_val, value, &ts, flags, write_clock);

	/* optional: force the Nth shmem allocation made while flushing the queued rows to
	 * fail, to reach pb_history_add_row_mem()'s allocation-failure cleanup branches */
	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter("in.fail_alloc_at", &hparam))
	{
		int	fail_alloc_at;

		zbx_mock_int(hparam, &fail_alloc_at);
		zbx_pb_mock_fail_alloc_at(fail_alloc_at);
	}

	zbx_pb_history_close(handle);
	zbx_pb_mock_fail_alloc_at(0);

	pb = get_pb_data();

	/* handle registration/deregistration in pb->history_handleids must always be paired, on
	 * every exit path of zbx_pb_history_close() including the zero-rows one */
	zbx_mock_assert_int_eq("history_handleids after close", 0, pb->history_handleids.values_num);

	/* verify row fields in the memory buffer */
	count = 0;
	zbx_list_iterator_init(&pb->history, &li);

	while (SUCCEED == zbx_list_iterator_next(&li))
	{
		zbx_list_iterator_peek(&li, (void **)&row);
		zbx_mock_assert_uint64_eq("itemid", itemid, row->itemid);
		zbx_mock_assert_str_eq("value", value, row->value);
		zbx_mock_assert_str_eq("source", "", row->source);
		zbx_mock_assert_int_eq("state", state_val, row->state);
		zbx_mock_assert_int_eq("flags", flags, row->flags);
		zbx_mock_assert_int_eq("ts.sec", ts.sec, row->ts.sec);
		zbx_mock_assert_int_eq("ts.ns", ts.ns, row->ts.ns);
		zbx_mock_assert_int_eq("timestamp", 0, row->timestamp);
		zbx_mock_assert_int_eq("severity", 0, row->severity);
		zbx_mock_assert_int_eq("logeventid", 0, row->logeventid);
		zbx_mock_assert_uint64_eq("lastlogsize", 0, row->lastlogsize);
		zbx_mock_assert_int_eq("mtime", 0, row->mtime);
		zbx_mock_assert_time_eq("write_clock", write_clock, row->write_clock);
		count++;
	}

	zbx_mock_assert_int_eq("row count", expected_rows, count);

	/* test get_rows: must return the same count, and history_lastid_mem must match the last
	 * exported row id */
	zbx_json_init(&j, ZBX_KIBIBYTE * 16);
	rows_got = zbx_pb_history_get_rows(&j, &lastid, &more);
	zbx_mock_assert_int_eq("get_rows count", expected_rows, rows_got);
	zbx_json_free(&j);

	if (0 < expected_rows)
		zbx_mock_assert_uint64_eq("history_lastid_mem", lastid, pb->history_lastid_mem);

	/* optional: partial set_lastid — clear only row id=1, verify remainder */
	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter("out.rows_after_partial_clear", &hparam))
	{
		int	expected_partial;

		zbx_mock_int(hparam, &expected_partial);
		zbx_pb_set_history_lastid(1);

		zbx_json_init(&j, ZBX_KIBIBYTE * 16);
		rows_got = zbx_pb_history_get_rows(&j, &lastid, &more);
		zbx_mock_assert_int_eq("get_rows after partial clear", expected_partial, rows_got);
		zbx_json_free(&j);
	}

	/* test full set_lastid: must clear the buffer so a subsequent get_rows returns 0 */
	zbx_pb_set_history_lastid(lastid);

	zbx_json_init(&j, ZBX_KIBIBYTE * 16);
	rows_got = zbx_pb_history_get_rows(&j, &lastid, &more);
	zbx_mock_assert_int_eq("get_rows after set_lastid", 0, rows_got);
	zbx_json_free(&j);

	zbx_pb_destroy();
}
