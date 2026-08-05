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
#include "zbxjson.h"
#include "zbxmutexs.h"
#include "zbxcacheconfig.h"
#include "zbxproxybuffer.h"
#include "proxybuffer.h"
#include "pb_history.h"

#include "zbx_pb_mock_stubs.h"

void	zbx_mock_test_entry(void **state)
{
	char			*error = NULL;
	zbx_pb_history_data_t	*handle;
	struct zbx_json		j;
	zbx_uint64_t		lastid = 0;
	int			more, rows_got, expected_rows, i, rows_written, item_status, host_status,
				errcode, flags;
	zbx_timespec_t		ts = {1234567890, 0};

	ZBX_UNUSED(state);

	rows_written = zbx_mock_get_parameter_int("in.rows");
	flags = (0 != zbx_mock_get_parameter_int("in.novalue")) ? ZBX_PROXY_HISTORY_FLAG_NOVALUE : 0;
	errcode = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("in.errcode"));
	item_status = zbx_mock_get_parameter_int("in.item_status");
	host_status = zbx_mock_get_parameter_int("in.host_status");
	expected_rows = zbx_mock_get_parameter_int("out.rows");

	zbx_pb_mock_set_nextid(1);
	/* pb_history_export() reaches this in every mode - configure per test case what it should
	 * report for every row's item/host, since the default (active item, monitored host) would
	 * otherwise mask the filtering branches under test here */
	zbx_pb_mock_set_item_status(errcode, item_status, host_status);

	zbx_mock_assert_result_eq("locks_create", SUCCEED, zbx_locks_create(&error));
	zbx_mock_assert_result_eq("pb_create", SUCCEED,
			zbx_pb_create(ZBX_PB_MODE_MEMORY, ZBX_MEBIBYTE, 0, 0, &error));
	zbx_pb_init();

	handle = zbx_pb_history_open();

	/* same itemid for every row: exercises pb_history_export()'s novalue-duplicate filtering */
	for (i = 0; i < rows_written; i++)
		zbx_pb_history_write_value(handle, 1, 0, "42", &ts, flags, 1234567890);

	zbx_pb_history_close(handle);

	zbx_json_init(&j, ZBX_KIBIBYTE * 16);
	rows_got = zbx_pb_history_get_rows(&j, &lastid, &more);
	zbx_mock_assert_int_eq("get_rows count", expected_rows, rows_got);
	zbx_json_free(&j);

	zbx_pb_destroy();
}
