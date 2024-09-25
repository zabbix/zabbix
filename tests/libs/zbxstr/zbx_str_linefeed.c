/*
** Copyright (C) 2001-2024 Zabbix SIA
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
#include "zbxmockhelper.h"

#include "zbxstr.h"

void	zbx_mock_test_entry(void **state)
{
	const char	*scr = zbx_mock_get_parameter_string("in.scr");
	const char	*exp_result = zbx_mock_get_parameter_string("out.string");
	const char	*delim = zbx_mock_get_parameter_string("in.delim");
	size_t		maxline = zbx_mock_get_parameter_uint64("in.maxline");
	char		*act_result = zbx_str_linefeed(scr, maxline, delim);

	ZBX_UNUSED(state);

	zbx_mock_assert_str_eq("return value",  exp_result, act_result);
	zbx_free(act_result);
}
