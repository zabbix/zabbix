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
#include "zbxmutexs.h"
#include "zbxproxybuffer.h"
#include "proxybuffer.h"

#include "zbx_pb_mock_stubs.h"

void	zbx_mock_test_entry(void **state)
{
	char		*error = NULL;
	zbx_pb_t	*pb;
	zbx_uint64_t	lastid_sent, lastid_db, lastid_mem, expected_unsent;

	ZBX_UNUSED(state);

	lastid_sent = zbx_mock_get_parameter_uint64("in.lastid_sent");
	lastid_db = zbx_mock_get_parameter_uint64("in.lastid_db");
	lastid_mem = zbx_mock_get_parameter_uint64("in.lastid_mem");
	expected_unsent = zbx_mock_get_parameter_uint64("out.unsent");

	zbx_mock_assert_result_eq("locks_create", SUCCEED, zbx_locks_create(&error));
	zbx_mock_assert_result_eq("pb_create", SUCCEED,
			zbx_pb_create(ZBX_PB_MODE_MEMORY, ZBX_MEBIBYTE, 0, 0, &error));
	zbx_pb_init();

	pb = get_pb_data();
	pb->history_lastid_sent = lastid_sent;
	pb->history_lastid_db = lastid_db;
	pb->history_lastid_mem = lastid_mem;

	zbx_mock_assert_uint64_eq("unsent", expected_unsent, zbx_pb_history_get_unsent_num());

	zbx_pb_destroy();
}
