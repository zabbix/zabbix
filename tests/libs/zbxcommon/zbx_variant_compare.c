/*
** Zabbix
** Copyright (C) 2001-2026 Zabbix SIA
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

#include "zbxvariant.h"

#include "zbx_variant_common.h"

void	zbx_mock_test_entry(void **state)
{
	zbx_variant_t	value1, value2;
	int		ret;
	const char	*returned_result;

	ZBX_UNUSED(state);

	ZBX_DOUBLE_EPSILON = 0.000001;

	mock_read_variant("in.value1", &value1);
	mock_read_variant("in.value2", &value2);

	ret = zbx_variant_compare(&value1, &value2);

	if (ret < 0)
		returned_result = "less";
	else if (ret > 0)
		returned_result = "greater";
	else
		returned_result = "equal";

	zbx_mock_assert_str_eq("zbx_variant_compare() return", zbx_mock_get_parameter_string("out.return"),
			returned_result);

	zbx_variant_clear(&value1);
	zbx_variant_clear(&value2);
}
