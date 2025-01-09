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

#include "zbxvariant.h"

#include "zbx_variant_common.h"

void	zbx_mock_test_entry(void **state)
{
	zbx_variant_t	value;
	int		ret;
	unsigned char	value_type;
	char		*error = NULL;

	ZBX_UNUSED(state);

	zbx_update_epsilon_to_float_precision();
	mock_read_variant("in.value", &value);
	value_type = zbx_mock_str_to_value_type(zbx_mock_get_parameter_string("in.value_type"));
	ret = zbx_variant_to_value_type(&value, value_type, &error);

	zbx_mock_assert_str_eq("zbx_variant_to_value_type() return", zbx_mock_get_parameter_string("out.return"),
			error);
	zbx_mock_assert_int_eq("Return value", zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.ret")),
			ret);
	zbx_variant_clear(&value);
	zbx_free(error);
}
