/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "common.h"

void	zbx_mock_test_entry(void **state)
{
	const char		*src, *expected_result, *escape_chars;
	char			*actual_result;

	ZBX_UNUSED(state);

	src = zbx_mock_get_parameter_string("in.data");
	expected_result = zbx_mock_get_parameter_string("out.result");
	escape_chars = zbx_mock_get_parameter_string("in.escape_chars");

	actual_result = zbx_dyn_escape_string(src, escape_chars);

	if (0 != strcmp(expected_result, actual_result))
	{
		fail_msg("Actual: \"%s\" != expected: \"%s\"", actual_result, expected_result);
	}

	zbx_free(actual_result);
}
