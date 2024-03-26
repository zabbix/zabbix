/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockutil.h"


#include "zbxcommon.h"
#include <stdarg.h>

static void	zbx_log_impl(int level, const char *fmt, va_list args)
{
	ZBX_UNUSED(level);
	ZBX_UNUSED(fmt);
	ZBX_UNUSED(args);
}

ZBX_GET_CONFIG_VAR2(const char *, const char *, zbx_progname, "common_mock_progname")

void	zbx_mock_test_entry(void **state)
{
	ZBX_UNUSED(state);

	zbx_init_library_common(zbx_log_impl, get_zbx_progname);
}
