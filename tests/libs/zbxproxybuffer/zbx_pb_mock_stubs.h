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

/* Monotonic ID counter used by zbx_dc_get_nextid stub.
 * Tests that write rows must set g_nextid = 1 at the start of
 * zbx_mock_test_entry; tests that never write rows can leave it at 0. */
extern zbx_uint64_t	g_nextid;

#endif
