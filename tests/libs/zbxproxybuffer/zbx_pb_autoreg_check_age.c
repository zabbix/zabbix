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
#include "zbxproxybuffer.h"

#include "zbx_pb_mock_stubs.h"

/* Drives pb_autoreg_check_age()'s offline-buffer eviction loop through the public write API
 * only, by writing a stale row and then a fresh one: the second write's internal age check
 * inspects the first row and evicts it if older than "offline_buffer". max_age is left at 0
 * (disabled) throughout - past that point check_age's staleness check would return FAIL, and
 * through zbx_pb_autoreg_write_host() a FAIL unconditionally falls through to a real database
 * write for the row being added (see zbx_pb_mock_stubs.c), which these tests do not set up. */
void	zbx_mock_test_entry(void **state)
{
	char			*error = NULL;
	struct zbx_json		j;
	zbx_uint64_t		lastid = 0;
	int			offline_buffer, row_age, more, rows_got, expected_rows;

	ZBX_UNUSED(state);

	offline_buffer = zbx_mock_get_parameter_int("in.offline_buffer");
	row_age = zbx_mock_get_parameter_int("in.row_age");
	expected_rows = zbx_mock_get_parameter_int("out.rows");

	zbx_pb_mock_set_nextid(1);

	zbx_mock_assert_result_eq("locks_create", SUCCEED, zbx_locks_create(&error));
	zbx_mock_assert_result_eq("pb_create", SUCCEED,
			zbx_pb_create(ZBX_PB_MODE_MEMORY, ZBX_MEBIBYTE, 0, offline_buffer, &error));
	zbx_pb_init();

	/* pb->autoreg is empty for this first write, so its own age check trivially succeeds
	 * regardless of "row_age" - the second write below is what exercises eviction */
	zbx_pb_autoreg_write_host("stale", "127.0.0.1", "", 10050, 0, "", 0, (int)time(NULL) - row_age);
	zbx_pb_autoreg_write_host("fresh", "127.0.0.2", "", 10050, 0, "", 0, (int)time(NULL));

	zbx_json_init(&j, ZBX_KIBIBYTE * 16);
	rows_got = zbx_pb_autoreg_get_rows(&j, &lastid, &more);
	zbx_mock_assert_int_eq("rows after second write", expected_rows, rows_got);
	zbx_json_free(&j);

	zbx_pb_destroy();
}
