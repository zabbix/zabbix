/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#include "zbxvariant.h"

#include "zbx_variant_common.h"

void	zbx_mock_test_entry(void **state)
{
	zbx_variant_t	value;
	int		type, ret, expected_ret;

	ZBX_UNUSED(state);

	zbx_update_epsilon_to_float_precision();

	mock_read_variant("in.value", &value);
	type = mock_str_to_variant_type(zbx_mock_get_parameter_string("in.type"));

	ret = zbx_variant_convert(&value, type);

	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.ret"));
	zbx_mock_assert_int_eq("Return value", expected_ret, ret);

	if (SUCCEED == ret)
	{
		zbx_mock_assert_str_eq("Converted value", zbx_mock_get_parameter_string("out.value"),
				zbx_variant_value_desc(&value));
	}

	zbx_variant_clear(&value);
}
