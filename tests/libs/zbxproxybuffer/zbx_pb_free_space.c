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
#include "zbx_host_constants.h"
#include "zbx_item_constants.h"
#include "zbxproxybuffer.h"

#include "zbx_pb_mock_stubs.h"

/* pb_free_space() discards the oldest record across all three row types, and is only reached
 * (in production) when a memory-mode write hits genuine shmem allocator exhaustion. Rather than
 * calling it directly or sizing the buffer to force real exhaustion (which would couple this
 * test to shmem chunk-overhead internals), this drives it through the real code path: writing
 * one row of each type, then forcing the *first* allocation of one more autoreg write to fail
 * via zbx_pb_mock_fail_alloc_at() - the same deterministic injection the write/close tests use.
 * That failure makes pb_autoreg_add_row_mem() return FAIL, which sends
 * pb_autoreg_write_host_mem()'s retry loop into a real call to pb_free_space(), evicting
 * whichever of the three already-written rows is genuinely oldest before the retry succeeds. */
void	zbx_mock_test_entry(void **state)
{
	char			*error = NULL;
	zbx_pb_discovery_data_t	*dhandle;
	zbx_pb_history_data_t	*hhandle;
	struct zbx_json		j;
	zbx_timespec_t		ts;
	zbx_uint64_t		lastid = 0;
	int			autoreg_clock, discovery_clock, history_clock, more, rows_got;
	int			expected_autoreg, expected_discovery, expected_history;

	ZBX_UNUSED(state);

	/* clocks are relative to "now", not absolute epoch values: pb_autoreg_check_age() (run at
	 * the start of every zbx_pb_autoreg_write_host() call) would otherwise see an ancient
	 * absolute clock as far older than offline_buffer and evict it on its own, before the
	 * allocation-failure injection below gets a chance to exercise pb_free_space() at all */
	autoreg_clock = (int)time(NULL) - zbx_mock_get_parameter_int("in.autoreg_age");
	discovery_clock = (int)time(NULL) - zbx_mock_get_parameter_int("in.discovery_age");
	history_clock = (int)time(NULL) - zbx_mock_get_parameter_int("in.history_age");
	expected_autoreg = zbx_mock_get_parameter_int("out.autoreg_rows");
	expected_discovery = zbx_mock_get_parameter_int("out.discovery_rows");
	expected_history = zbx_mock_get_parameter_int("out.history_rows");

	zbx_pb_mock_set_nextid(1);
	/* zbx_pb_history_get_rows() below always reaches pb_history_export(); this test is not
	 * exercising its item/host filtering, so declare the sane default explicitly */
	zbx_pb_mock_set_item_status(SUCCEED, ITEM_STATUS_ACTIVE, HOST_STATUS_MONITORED);

	zbx_mock_assert_result_eq("locks_create", SUCCEED, zbx_locks_create(&error));
	/* offline_buffer large enough that pb_*_check_age() never evicts these rows on its own -
	 * only the forced allocation failure below should trigger any eviction */
	zbx_mock_assert_result_eq("pb_create", SUCCEED,
			zbx_pb_create(ZBX_PB_MODE_MEMORY, ZBX_MEBIBYTE, 0, 3600, &error));
	zbx_pb_init();

	zbx_pb_autoreg_write_host("hostA", "1.2.3.5", "", 10050, 0, "", 0, autoreg_clock);

	dhandle = zbx_pb_discovery_open();
	zbx_pb_discovery_write_service(dhandle, 1, 1, "1.2.3.4", "", 0, 0, "", discovery_clock);
	zbx_pb_discovery_close(dhandle);

	ts.sec = history_clock;
	ts.ns = 0;
	hhandle = zbx_pb_history_open();
	/* a long value: pb_free_space() frees space by evicting whole records, so each of the
	 * three rows here must individually free at least as much as hostD (below) needs, or
	 * evicting the single oldest one would not be enough and a second record would be
	 * evicted too - the short "42" used by other tests is not enough for this one */
	zbx_pb_history_write_value(hhandle, 1, 0,
			"the quick brown fox jumps over the lazy dog, repeated for length: the quick brown fox",
			&ts, 0, history_clock);
	zbx_pb_history_close(hhandle);

	/* fail the first shmem allocation made while writing this row, forcing a real
	 * pb_free_space() call that must pick the globally oldest of the three rows above */
	zbx_pb_mock_fail_alloc_at(1);
	zbx_pb_autoreg_write_host("hostD", "1.2.3.6", "", 10050, 0, "", 0, (int)time(NULL));
	zbx_pb_mock_fail_alloc_at(0);

	zbx_json_init(&j, ZBX_KIBIBYTE * 16);
	rows_got = zbx_pb_autoreg_get_rows(&j, &lastid, &more);
	zbx_mock_assert_int_eq("autoreg rows", expected_autoreg, rows_got);
	zbx_json_free(&j);

	zbx_json_init(&j, ZBX_KIBIBYTE * 16);
	rows_got = zbx_pb_discovery_get_rows(&j, &lastid, &more);
	zbx_mock_assert_int_eq("discovery rows", expected_discovery, rows_got);
	zbx_json_free(&j);

	zbx_json_init(&j, ZBX_KIBIBYTE * 16);
	rows_got = zbx_pb_history_get_rows(&j, &lastid, &more);
	zbx_mock_assert_int_eq("history rows", expected_history, rows_got);
	zbx_json_free(&j);

	zbx_pb_destroy();
}
