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
