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
#include "zbxmockdata.h"
#include "zbxmockassert.h"
#include "zbxmockutil.h"

#include "common.h"
#include "zbxjson.h"
#include "dbcache.h"
#include "log.h"

#include "../../../src/zabbix_server/preprocessor/item_preproc.h"

void	zbx_mock_test_entry(void **state)
{
	zbx_variant_t			value;
	zbx_timespec_t			ts;
	zbx_preproc_op_t		op;
	int				returned_ret, expected_ret;
	char				*error = NULL;
	zbx_item_history_value_t	history_value;
	const char			*expected_value;
	zbx_mock_handle_t		handle;

	ZBX_UNUSED(state);

	zbx_variant_set_str(&value, zbx_strdup(NULL, zbx_mock_get_parameter_string("in.data")));
	op.params = (char *)zbx_mock_get_parameter_string("in.path");
	op.type = ZBX_PREPROC_JSONPATH;

	zbx_timespec(&ts);
	memset(&history_value, 0, sizeof(history_value));
	zbx_variant_set_none(&history_value.value);

	returned_ret = zbx_item_preproc(ITEM_VALUE_TYPE_STR, &value, &ts, &op, &history_value, &error);

	if (SUCCEED != returned_ret)
		zabbix_log(LOG_LEVEL_DEBUG, "Preprocessing error: %s", error);

	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	zbx_mock_assert_result_eq("zbx_item_preproc() return", expected_ret, returned_ret);

	if (SUCCEED == returned_ret)
	{
		if (ZBX_MOCK_SUCCESS == zbx_mock_parameter("out.value", &handle))
		{
			if (ZBX_VARIANT_NONE == value.type)
				fail_msg("preprocessing result was empty value");

			if (ZBX_MOCK_SUCCESS != zbx_mock_string(handle, &expected_value))
				fail_msg("Invalid output parameter 'out.value'");

			zbx_mock_assert_str_eq("processed value", expected_value, zbx_variant_value_desc(&value));
		}
		else
		{
			if (ZBX_VARIANT_NONE != value.type)
				fail_msg("expected empty value, but got %s", zbx_variant_value_desc(&value));
		}
	}
	else
		zbx_mock_assert_ptr_ne("error message", NULL, error);

	zbx_variant_clear(&value);
	zbx_free(error);
}
