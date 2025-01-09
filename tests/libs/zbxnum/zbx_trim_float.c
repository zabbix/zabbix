/*
** Copyright (C) 2001-2025 Zabbix SIA
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
#include "zbxmockutil.h"

#include "zbxnum.h"
#include "zbxstr.h"

void	zbx_mock_test_entry(void **state)
{
	const char	*in_str;
	const char	*expected;
	char		buffer[256];

	ZBX_UNUSED(state);

	in_str = zbx_mock_get_parameter_string("in.str");
	expected = zbx_mock_get_parameter_string("out.str");

	zbx_strlcpy(buffer, in_str, sizeof(buffer));

	zbx_trim_float(buffer);

	if (0 != strcmp(expected, buffer))
	{
		fail_msg("Got '%s' instead of '%s'.", buffer, expected);
	}
}
