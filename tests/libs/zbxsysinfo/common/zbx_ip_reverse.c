/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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
#include <stdlib.h>

#include "../../../../src/libs/zbxsysinfo/common/ip_reverse.h"

void	zbx_mock_test_entry(void **state)
{
	ZBX_UNUSED(state);

	const char	*in_ip = zbx_mock_get_parameter_string("in.ip");

	int	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	char	*reversed_ip = NULL, *error = NULL;

	int	returned_ret = zbx_ip_reverse(in_ip, &reversed_ip, &error);

	zbx_mock_assert_result_eq("zbx_ip_reverse()", expected_ret, returned_ret);

	if (SUCCEED == returned_ret)
		zbx_mock_assert_str_eq("zbx_ip_reverse()", zbx_mock_get_parameter_string("out.ip"), reversed_ip);
	else
		zbx_mock_assert_str_eq("zbx_ip_reverse()", zbx_mock_get_parameter_string("out.error"), error);

	zbx_free(reversed_ip);
	zbx_free(error);
}
