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

#ifndef ZBX_PB_MOCK_STUBS_H
#define ZBX_PB_MOCK_STUBS_H

#include "zbxcommon.h"

/* Sentinel for zbx_pb_parse_mode tests: a value outside the valid ZBX_PB_MODE_*
 * range used to detect whether the function wrote to *mode on failure. */
#define ZBX_PB_MODE_UNSET	(-99)

/* Monotonic ID counter used by zbx_dc_get_nextid stub.
 * Tests that write rows must call zbx_pb_mock_set_nextid(1) at the start of
 * zbx_mock_test_entry; tests that never write rows can skip this. */
void	zbx_pb_mock_set_nextid(zbx_uint64_t id);

/* Deterministic allocation-failure injection for the proxy memory buffer.
 * Makes the call_no'th call (1-based, counted from this call) to the shmem
 * allocator underlying pb_malloc()/pb_strdup() return NULL; every other call
 * behaves normally. Pass 0 to disable (the default). */
void	zbx_pb_mock_fail_alloc_at(int call_no);

#endif
