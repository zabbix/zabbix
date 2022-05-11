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

#include "common.h"
#include "zbxjson.h"

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "mock_json.h"

void	zbx_mock_test_entry(void **state)
{
	const char	*data, *result;
	size_t		size;
	char		*buffer;
	int		expected_offset;
	zbx_json_type_t	returned_type;

	ZBX_UNUSED(state);

	data = zbx_mock_get_parameter_string("in.data");
	size = zbx_mock_get_parameter_uint64("in.size");
	buffer = (0 != size ? (char *)zbx_malloc(NULL, size) : NULL);

	result = zbx_json_decodevalue_dyn(data, &buffer, &size, &returned_type);

	expected_offset = atoi(zbx_mock_get_parameter_string("out.offset"));

	if (0 == expected_offset)
		zbx_mock_assert_ptr_eq("Returned value", NULL, result);
	else
		zbx_mock_assert_ptr_ne("Returned value", NULL, result);

	if (NULL != result)
	{
		zbx_mock_assert_int_eq("Returned value offset", expected_offset, result - data);
		zbx_mock_assert_str_eq("Returned json type", zbx_mock_get_parameter_string("out.type"),
				zbx_mock_json_type_to_str(returned_type));
		zbx_mock_assert_str_eq("Returned value", buffer, zbx_mock_get_parameter_string("out.value"));
	}

	zbx_free(buffer);
}

