/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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
#include "zbxmockutil.h"
#include "zbxmockassert.h"

void	zbx_mock_test_entry(void **state)
{
	size_t		len;
	char		*buf;
	const char	*key, *exp_result, *act_result;

	ZBX_UNUSED(state);

	key = zbx_mock_get_parameter_string("in.key");

	len = zbx_mock_get_parameter_uint64("in.len");

	buf = (char *)zbx_malloc(NULL, len * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1);

	exp_result = zbx_mock_get_parameter_string("out.key");

	act_result = zbx_truncate_itemkey(key, len, len * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1, &buf);

	zbx_mock_assert_str_eq("return value", exp_result, act_result);

	zbx_free(buf);
}
