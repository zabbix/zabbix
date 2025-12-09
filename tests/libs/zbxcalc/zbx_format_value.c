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
#include "zbxmockdb.h"

#include "zbxcalc.h"

void	zbx_mock_test_entry(void **state)
{
	const char	*in_value = zbx_mock_get_parameter_string("in.value"),
			*units = zbx_mock_get_parameter_string("in.units");
	zbx_uint64_t	valuemapid = zbx_mock_get_parameter_uint64("in.valuemapid");
	unsigned char	value_type = (unsigned char)zbx_mock_get_parameter_uint64("in.value_type");

	ZBX_UNUSED(state);

	if (ITEM_VALUE_TYPE_STR == value_type || ITEM_VALUE_TYPE_UINT64 == value_type)
		zbx_mockdb_init();

	char	*value = (char *)zbx_malloc(NULL, MAX_STRING_LEN);

	zbx_strlcpy(value, in_value, MAX_STRING_LEN);

	zbx_format_value(value, MAX_STRING_LEN, valuemapid, units, value_type);

	zbx_mock_assert_str_eq("Formatted value mismatch", zbx_mock_get_parameter_string("out.value"), value);

	zbx_mockdb_destroy();
	zbx_free(value);
}
