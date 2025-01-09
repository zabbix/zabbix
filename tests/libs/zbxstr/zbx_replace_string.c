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
#include "zbxmockhelper.h"

#include "zbxstr.h"

void	zbx_mock_test_entry(void **state)
{
	char		*src = zbx_strdup(NULL,zbx_mock_get_parameter_string("in.data"));
	const char	*value = zbx_mock_get_parameter_string("in.value");
	const char	*exp_result = zbx_mock_get_parameter_string("out.return");
	size_t		left = zbx_mock_get_parameter_uint64("in.l");
	size_t		right = zbx_mock_get_parameter_uint64("in.r");

	ZBX_UNUSED(state);

	zbx_replace_string(&src, left, &right, value);
	zbx_mock_assert_str_eq("return value", exp_result, src);
	zbx_free(src);
}
