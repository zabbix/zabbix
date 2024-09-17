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

#include "zbxstr.h"

void	zbx_mock_test_entry(void **state)
{
	ZBX_UNUSED(state);
//zbx_strlcat(char *dst, const char *src, size_t siz)
	const char	*src = zbx_mock_get_parameter_string("in.src");
	size_t		size = zbx_mock_get_parameter_uint64("in.size");
    char		dst[size];
    size_t		size2 = size - 1;
	const char	*exp_result = zbx_mock_get_parameter_string("out.result");
    memset(dst, 0, sizeof(dst));
	zbx_strlcat(dst, src, size2);
    zbx_replace_invalid_utf8(dst);
	zbx_mock_assert_str_eq("return value",  exp_result, dst);
}
