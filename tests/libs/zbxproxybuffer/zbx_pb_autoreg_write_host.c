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
#include "zbxcomms.h"
#include "zbxjson.h"
#include "zbxmutexs.h"
#include "zbxproxybuffer.h"
#include "proxybuffer.h"

#include "zbx_pb_mock_stubs.h"

void	zbx_mock_test_entry(void **state)
{
	char			*error = NULL;
	zbx_pb_t		*pb;
	zbx_pb_autoreg_t	*row;
	const char		*host, *ip, *dns, *host_metadata;
	int			i, rows_written, expected_rows, count, port, flags;
	int			connection_type = ZBX_TCP_SEC_UNENCRYPTED;
	zbx_list_iterator_t	li;
	struct zbx_json		j;
	zbx_uint64_t		lastid = 0;
	int			more, rows_got, age = 0, offline_buffer = 0, stale_offset = 0;
	zbx_pb_mem_info_t	mem_info;
	zbx_pb_state_info_t	state_info;
	zbx_mock_handle_t	hparam;

	ZBX_UNUSED(state);

	host = zbx_mock_get_parameter_string("in.host");
	ip = zbx_mock_get_parameter_string("in.ip");
	dns = zbx_mock_get_parameter_string("in.dns");
	host_metadata = zbx_mock_get_parameter_string("in.host_metadata");
	port = zbx_mock_get_parameter_int("in.port");
	flags = zbx_mock_get_parameter_int("in.flags");
	rows_written = zbx_mock_get_parameter_int("in.rows");
	expected_rows = zbx_mock_get_parameter_int("out.rows");

	/* optional: max_age/offline_buffer coverage for pb_autoreg_check_age(); the first row is */
	/* written stale_offset seconds in the past so a later write can push it past max_age      */
	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter("in.age", &hparam))
		zbx_mock_int(hparam, &age);
	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter("in.offline_buffer", &hparam))
		zbx_mock_int(hparam, &offline_buffer);
	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter("in.stale_offset", &hparam))
		zbx_mock_int(hparam, &stale_offset);

	zbx_pb_mock_set_nextid(1);

	zbx_mock_assert_result_eq("locks_create", SUCCEED, zbx_locks_create(&error));
	zbx_mock_assert_result_eq("pb_create", SUCCEED,
			zbx_pb_create(ZBX_PB_MODE_MEMORY, ZBX_MEBIBYTE, age, offline_buffer, &error));
	zbx_pb_init();

	/* verify mem/state info after buffer creation */
	zbx_mock_assert_result_eq("get_mem_info", SUCCEED, zbx_pb_get_mem_info(&mem_info, &error));
	zbx_mock_assert_uint64_ne("mem_total", 0, mem_info.mem_total);
	zbx_mock_assert_uint64_ne("mem_used", 0, mem_info.mem_used);
	zbx_pb_get_state_info(&state_info);
	zbx_mock_assert_int_eq("state_info.state", 1, state_info.state);

	/* optional: force the Nth shmem allocation made while writing the rows below to
	 * fail, to reach pb_autoreg_add_row_mem()'s allocation-failure cleanup branches */
	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter("in.fail_alloc_at", &hparam))
	{
		int	fail_alloc_at;

		zbx_mock_int(hparam, &fail_alloc_at);
		zbx_pb_mock_fail_alloc_at(fail_alloc_at);
	}

	for (i = 0; i < rows_written; i++)
	{
		/* use current time: pb_autoreg_check_age() purges rows where now - clock > offline_buffer; */
		/* a stale hardcoded clock would cause every row written after the first to immediately     */
		/* evict its predecessor. the first row is backdated by stale_offset (0 unless the test case */
		/* asks for it) so a later write's check_age() call can exercise the max_age gate instead.   */
		int	clock = (0 == i ? (int)time(NULL) - stale_offset : (int)time(NULL));

		zbx_pb_autoreg_write_host(host, ip, dns, (unsigned short)port,
				connection_type, host_metadata, flags, clock);
	}

	zbx_pb_mock_fail_alloc_at(0);

	pb = get_pb_data();

	/* verify row fields in the memory buffer */
	count = 0;
	zbx_list_iterator_init(&pb->autoreg, &li);

	while (SUCCEED == zbx_list_iterator_next(&li))
	{
		zbx_list_iterator_peek(&li, (void **)&row);
		zbx_mock_assert_str_eq("host", host, row->host);
		zbx_mock_assert_str_eq("listen_ip", ip, row->listen_ip);
		zbx_mock_assert_str_eq("listen_dns", dns, row->listen_dns);
		zbx_mock_assert_str_eq("host_metadata", host_metadata, row->host_metadata);
		zbx_mock_assert_int_eq("listen_port", port, row->listen_port);
		zbx_mock_assert_int_eq("flags", flags, row->flags);
		zbx_mock_assert_int_eq("tls_accepted", connection_type, row->tls_accepted);
		count++;
	}

	zbx_mock_assert_int_eq("row count", expected_rows, count);

	/* re-check state: pb_autoreg_check_age() may have fallen back to database mode once the */
	/* buffered row aged past max_age (state_info.state is 1 for memory dst, 0 for database) */
	zbx_pb_get_state_info(&state_info);

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter("out.state", &hparam))
	{
		int	expected_state;

		zbx_mock_int(hparam, &expected_state);
		zbx_mock_assert_int_eq("state_info.state after writes", expected_state, state_info.state);
	}

	/* once the buffer has fallen back to database mode, get_rows()/set_lastid() read and clear */
	/* through the DB-backed path (pb_autoreg_get_db()), which this test does not mock          */
	if (1 == state_info.state)
	{
		/* test get_rows: must return the same count */
		zbx_json_init(&j, ZBX_KIBIBYTE * 16);
		rows_got = zbx_pb_autoreg_get_rows(&j, &lastid, &more);
		zbx_mock_assert_int_eq("get_rows count", expected_rows, rows_got);
		zbx_json_free(&j);

		/* optional: partial set_lastid — clear only row id=1, verify remainder */
		if (ZBX_MOCK_SUCCESS == zbx_mock_parameter("out.rows_after_partial_clear", &hparam))
		{
			int	expected_partial;

			zbx_mock_int(hparam, &expected_partial);
			zbx_pb_autoreg_set_lastid(1);

			zbx_json_init(&j, ZBX_KIBIBYTE * 16);
			rows_got = zbx_pb_autoreg_get_rows(&j, &lastid, &more);
			zbx_mock_assert_int_eq("get_rows after partial clear", expected_partial, rows_got);
			zbx_json_free(&j);
		}

		/* test full set_lastid: must clear the buffer so a subsequent get_rows returns 0 */
		zbx_pb_autoreg_set_lastid(lastid);

		zbx_json_init(&j, ZBX_KIBIBYTE * 16);
		rows_got = zbx_pb_autoreg_get_rows(&j, &lastid, &more);
		zbx_mock_assert_int_eq("get_rows after set_lastid", 0, rows_got);
		zbx_json_free(&j);
	}

	zbx_pb_destroy();
}
