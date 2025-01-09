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
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "zbxregexp.h"

void	zbx_mock_test_entry(void **state)
{
	const char		*pattern, *str;
	zbx_mock_handle_t	hvalues, hvalue;
	int			ret, expected_ret;

	ZBX_UNUSED(state);

	pattern = zbx_mock_get_parameter_string("in.pattern");
	hvalues = zbx_mock_get_parameter_handle("out.values");

	while (ZBX_MOCK_SUCCESS == zbx_mock_vector_element(hvalues, &hvalue))
	{
		str = zbx_mock_get_object_member_string(hvalue, "value");
		expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_object_member_string(hvalue, "result"));
		ret = (1 == zbx_wildcard_match(str, pattern)) ? SUCCEED : FAIL;

		if (ret != expected_ret)
			fail_msg("String \"%s\" unexpectedly %s wildcard \"%s\"",
					str, 1 == ret ? "match" : "doesn't match", pattern);
	}
}
