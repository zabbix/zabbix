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
	const char		*value, *source;
	int			timestamp, logeventid, severity, mtime, count;
	zbx_uint64_t		lastlogsize;
	zbx_list_iterator_t	li;
	struct zbx_json		j;
	zbx_uint64_t		lastid = 0, itemid = 100;
	int			more, rows_got;
	zbx_timespec_t		ts = {1234567890, 0};
	int			flags = ZBX_PROXY_HISTORY_FLAG_META;

	ZBX_UNUSED(state);

	value = zbx_mock_get_parameter_string("in.value");
	source = zbx_mock_get_parameter_string("in.source");
	timestamp = zbx_mock_get_parameter_int("in.timestamp");
	logeventid = zbx_mock_get_parameter_int("in.logeventid");
	severity = zbx_mock_get_parameter_int("in.severity");
	mtime = zbx_mock_get_parameter_int("in.mtime");
	lastlogsize = zbx_mock_get_parameter_uint64("in.lastlogsize");

	zbx_pb_mock_set_nextid(1);
	/* zbx_pb_history_get_rows() below always reaches pb_history_export(); this test is not
	 * exercising its item/host filtering, so declare the sane default explicitly */
	zbx_pb_mock_set_item_status(SUCCEED, ITEM_STATUS_ACTIVE, HOST_STATUS_MONITORED);

	zbx_mock_assert_result_eq("locks_create", SUCCEED, zbx_locks_create(&error));
	zbx_mock_assert_result_eq("pb_create", SUCCEED,
			zbx_pb_create(ZBX_PB_MODE_MEMORY, ZBX_MEBIBYTE, 0, 0, &error));
	zbx_pb_init();

	handle = zbx_pb_history_open();
	zbx_pb_history_write_meta_value(handle, itemid, 0, value, &ts, flags, lastlogsize, mtime, timestamp,
			logeventid, severity, source, 1234567890);
	zbx_pb_history_close(handle);

	pb = get_pb_data();

	count = 0;
	zbx_list_iterator_init(&pb->history, &li);

	while (SUCCEED == zbx_list_iterator_next(&li))
	{
		zbx_list_iterator_peek(&li, (void **)&row);
		zbx_mock_assert_str_eq("value", value, row->value);
		zbx_mock_assert_str_eq("source", source, row->source);
		zbx_mock_assert_int_eq("flags", flags, row->flags);
		zbx_mock_assert_int_eq("timestamp", timestamp, row->timestamp);
		zbx_mock_assert_int_eq("logeventid", logeventid, row->logeventid);
		zbx_mock_assert_int_eq("severity", severity, row->severity);
		zbx_mock_assert_int_eq("mtime", mtime, row->mtime);
		zbx_mock_assert_uint64_eq("lastlogsize", lastlogsize, row->lastlogsize);
		count++;
	}

	zbx_mock_assert_int_eq("row count", 1, count);

	zbx_json_init(&j, ZBX_KIBIBYTE * 16);
	rows_got = zbx_pb_history_get_rows(&j, &lastid, &more);
	zbx_mock_assert_int_eq("get_rows count", 1, rows_got);
	zbx_json_free(&j);

	zbx_pb_destroy();
}
