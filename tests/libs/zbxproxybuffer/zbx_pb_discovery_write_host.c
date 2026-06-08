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
#include "zbxproxybuffer.h"
#include "proxybuffer.h"
#include "pb_discovery.h"

#include "zbx_pb_mock_stubs.h"

void	zbx_mock_test_entry(void **state)
{
	char				*error = NULL;
	zbx_pb_t			*pb;
	zbx_pb_discovery_data_t		*handle;
	zbx_pb_discovery_t		*row;
	const char			*ip, *dns, *err;
	int				i, rows_written, expected_rows, count;
	zbx_list_iterator_t		li;
	struct zbx_json			j;
	zbx_uint64_t			lastid = 0;
	int				more, rows_got;
	zbx_pb_mem_info_t		mem_info;
	zbx_pb_state_info_t		state_info;
	zbx_mock_handle_t		hparam;
	zbx_uint64_t			buffer_size;

	ZBX_UNUSED(state);

	buffer_size = (zbx_uint64_t)(1024 * 1024);

	ip = zbx_mock_get_parameter_string("in.ip");
	dns = zbx_mock_get_parameter_string("in.dns");
	err = zbx_mock_get_parameter_string("in.error");
	rows_written = zbx_mock_get_parameter_int("in.rows");
	expected_rows = zbx_mock_get_parameter_int("out.rows");

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter("in.buffer_size", &hparam))
		zbx_mock_uint64(hparam, &buffer_size);

	g_nextid = 1;

	zbx_mock_assert_result_eq("locks_create", SUCCEED, zbx_locks_create(&error));
	zbx_mock_assert_result_eq("pb_create", SUCCEED,
			zbx_pb_create(ZBX_PB_MODE_MEMORY, buffer_size, 0, 0, &error));
	zbx_pb_init();

	/* verify mem/state info after buffer creation */
	zbx_mock_assert_result_eq("get_mem_info", SUCCEED, zbx_pb_get_mem_info(&mem_info, &error));
	zbx_mock_assert_int_eq("mem_total > 0", 1, mem_info.mem_total > 0 ? 1 : 0);
	zbx_mock_assert_int_eq("mem_used > 0",  1, mem_info.mem_used  > 0 ? 1 : 0);
	zbx_pb_get_state_info(&state_info);
	zbx_mock_assert_int_eq("state_info.state", 1, state_info.state);

	handle = zbx_pb_discovery_open();

	for (i = 0; i < rows_written; i++)
		zbx_pb_discovery_write_host(handle, 1, ip, dns, 1, 1234567890, err);

	zbx_pb_discovery_close(handle);

	pb = get_pb_data();

	/* verify row fields in the memory buffer */
	count = 0;
	zbx_list_iterator_init(&pb->discovery, &li);

	while (SUCCEED == zbx_list_iterator_next(&li))
	{
		zbx_list_iterator_peek(&li, (void **)&row);
		zbx_mock_assert_str_eq("ip",       ip,  row->ip);
		zbx_mock_assert_str_eq("dns",      dns, row->dns);
		zbx_mock_assert_str_eq("value",    "",  row->value);
		zbx_mock_assert_str_eq("error",    err, row->error);
		zbx_mock_assert_uint64_eq("dcheckid", 0, row->dcheckid);
		zbx_mock_assert_int_eq("port",     0,   row->port);
		count++;
	}

	zbx_mock_assert_int_eq("row count", expected_rows, count);

	/* test get_rows: must return the same count and a valid lastid */
	zbx_json_init(&j, ZBX_KIBIBYTE * 16);
	rows_got = zbx_pb_discovery_get_rows(&j, &lastid, &more);
	zbx_mock_assert_int_eq("get_rows count", expected_rows, rows_got);
	zbx_json_free(&j);

	/* optional: partial set_lastid — clear only row id=1, verify remainder */
	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter("out.rows_after_partial_clear", &hparam))
	{
		int	expected_partial;

		zbx_mock_int(hparam, &expected_partial);
		zbx_pb_discovery_set_lastid(1);

		zbx_json_init(&j, ZBX_KIBIBYTE * 16);
		rows_got = zbx_pb_discovery_get_rows(&j, &lastid, &more);
		zbx_mock_assert_int_eq("get_rows after partial clear", expected_partial, rows_got);
		zbx_json_free(&j);
	}

	/* test full set_lastid: must clear the buffer so a subsequent get_rows returns 0 */
	zbx_pb_discovery_set_lastid(lastid);

	zbx_json_init(&j, ZBX_KIBIBYTE * 16);
	rows_got = zbx_pb_discovery_get_rows(&j, &lastid, &more);
	zbx_mock_assert_int_eq("get_rows after set_lastid", 0, rows_got);
	zbx_json_free(&j);

	zbx_pb_destroy();
}
