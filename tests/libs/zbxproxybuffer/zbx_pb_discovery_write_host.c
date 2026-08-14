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
#include "zbxmutexs.h"
#include "zbxproxybuffer.h"
#include "proxybuffer.h"

#include "zbx_pb_mock_stubs.h"

void	zbx_mock_test_entry(void **state)
{
	char			*error = NULL;
	zbx_pb_t		*pb;
	zbx_pb_discovery_data_t	*handle;
	zbx_pb_discovery_t	*row;
	zbx_list_iterator_t	li;
	const char		*ip, *dns, *err;
	int			i, rows_written, expected_rows, count, fail_alloc_at;
	zbx_uint64_t		druleid = 1;
	int			status = DOBJECT_STATUS_DOWN, clock = 1234567890;

	ZBX_UNUSED(state);

	ip = zbx_mock_get_parameter_string("in.ip");
	dns = zbx_mock_get_parameter_string("in.dns");
	err = zbx_mock_get_parameter_string("in.error");
	rows_written = zbx_mock_get_parameter_int("in.rows");
	fail_alloc_at = zbx_mock_get_parameter_int("in.fail_alloc_at");
	expected_rows = zbx_mock_get_parameter_int("out.rows");

	zbx_pb_mock_set_nextid(1);

	zbx_mock_assert_result_eq("locks_create", SUCCEED, zbx_locks_create(&error));
	zbx_mock_assert_result_eq("pb_create", SUCCEED,
			zbx_pb_create(ZBX_PB_MODE_MEMORY, ZBX_MEBIBYTE, 0, 0, &error));
	zbx_pb_init();

	handle = zbx_pb_discovery_open();

	for (i = 0; i < rows_written; i++)
		zbx_pb_discovery_write_host(handle, druleid, ip, dns, status, clock, err);

	/* rows are copied into the proxy memory buffer only when the handle is closed, */
	/* so the allocation failure must be armed right before the close               */
	zbx_pb_mock_fail_alloc_at(fail_alloc_at);
	zbx_pb_discovery_close(handle);
	zbx_pb_mock_fail_alloc_at(0);

	pb = get_pb_data();

	count = 0;
	zbx_list_iterator_init(&pb->discovery, &li);

	while (SUCCEED == zbx_list_iterator_next(&li))
	{
		zbx_list_iterator_peek(&li, (void **)&row);
		zbx_mock_assert_str_eq("ip", ip, row->ip);
		zbx_mock_assert_str_eq("dns", dns, row->dns);
		zbx_mock_assert_str_eq("value", "", row->value);
		zbx_mock_assert_str_eq("error", err, row->error);
		zbx_mock_assert_uint64_eq("druleid", druleid, row->druleid);
		zbx_mock_assert_uint64_eq("dcheckid", 0, row->dcheckid);
		zbx_mock_assert_int_eq("port", 0, row->port);
		zbx_mock_assert_int_eq("status", status, row->status);
		zbx_mock_assert_int_eq("clock", clock, row->clock);
		count++;
	}

	zbx_mock_assert_int_eq("row count", expected_rows, count);

	zbx_pb_destroy();
}
