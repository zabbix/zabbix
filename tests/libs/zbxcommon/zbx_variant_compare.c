/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

static void	mock_read_variant(const char *path, zbx_variant_t *variant)
{
	zbx_mock_handle_t	hvalue;
	const char		*type;
	const char		*value;

	hvalue = zbx_mock_get_parameter_handle(path);
	type = zbx_mock_get_object_member_string(hvalue, "type");

	if (0 == strcmp(type, "ZBX_VARIANT_NONE"))
	{
		zbx_variant_set_none(variant);
		return;
	}

	value = zbx_mock_get_object_member_string(hvalue, "value");

	if (0 == strcmp(type, "ZBX_VARIANT_STR"))
	{
		zbx_variant_set_str(variant, zbx_strdup(NULL, value));
		return;
	}

	if (0 == strcmp(type, "ZBX_VARIANT_DBL"))
	{
		zbx_variant_set_dbl(variant, atof(value));
		return;
	}


	if (0 == strcmp(type, "ZBX_VARIANT_UI64"))
	{
		zbx_uint64_t	value_ui64;

		if (SUCCEED != is_uint64(value, &value_ui64))
			fail_msg("Cannot convert value %s to uint64", value);

		zbx_variant_set_ui64(variant, value_ui64);
		return;
	}

	fail_msg("Invalid variant type: %s", type);
}


void	zbx_mock_test_entry(void **state)
{
	zbx_variant_t	value1, value2;
	int		ret;
	const char	*returned_result;

	ZBX_UNUSED(state);

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
