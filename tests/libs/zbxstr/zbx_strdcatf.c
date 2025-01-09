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
#include "zbxmockassert.h"

#include "zbxstr.h"

void	zbx_mock_test_entry(void **state)
{
	char		*dest = NULL;
	const char 	*argument_str = zbx_mock_get_parameter_string("in.arg_str");
	int		argument_int = atoi(zbx_mock_get_parameter_string("in.arg_int"));
	const char	*exp_result = zbx_mock_get_parameter_string("out.val");
	int		argument_number = atoi(zbx_mock_get_parameter_string("in.arg_number"));

	ZBX_UNUSED(state);

	switch(argument_number)
	{
		case 1:
			dest = zbx_strdcatf(dest, "This is string - %s", argument_str);
			break;
		case 2:
			dest = zbx_strdcatf(dest, "This is a %s, there are %d rooms", argument_str, argument_int);
			break;
		case 3:
			dest = zbx_strdcatf(dest, "I have a %s. It is %d years old. This is a big %s", argument_str,
				argument_int, argument_str);
			break;
		default:
			fail_msg("Expected argument_number 1-3");
			break;
	}

	zbx_mock_assert_str_eq("return value", exp_result, dest);
	zbx_free(dest);
}
