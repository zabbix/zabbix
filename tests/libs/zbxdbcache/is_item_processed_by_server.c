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
#include "dbcache.h"

void	zbx_mock_test_entry(void **state)
{
	int		type, expected_result;
	const char	*key, *data;

	ZBX_UNUSED(state);

	key = zbx_mock_get_parameter_string("in.key");
	data = zbx_mock_get_parameter_string("in.type");
	if (FAIL == (type = zbx_mock_str_to_item_type(data)))
		type = atoi(data);

	expected_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));

	zbx_mock_assert_result_eq("is_item_processed_by_server() return code", expected_result,
			is_item_processed_by_server((unsigned char)type, key));
}
