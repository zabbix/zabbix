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
#include <stdio.h>
#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockutil.h"
#include "zbxmockassert.h"
#include "zbxcommon.h"
#include "zbxvariant.h"

#include "zbxembed.h"
#include "libs/zbxpreproc/pp_execute.h"

void	zbx_mock_test_entry(void **state)
{
	zbx_variant_t		value, history_value_in, history_value_out;
	const char		*csv;
	const char		*exp_json;
	int			act_ret, exp_ret;
	zbx_pp_context_t	ctx;
	zbx_timespec_t		ts, history_ts;
	zbx_pp_step_t		step;

	ZBX_UNUSED(state);

	pp_context_init(&ctx);

	csv = zbx_mock_get_parameter_string("in.csv");
	exp_json = zbx_mock_get_parameter_string("out.result");
	zbx_variant_set_str(&value, zbx_strdup(NULL, csv));

	step.type = ZBX_PREPROC_CSV_TO_JSON;
	step.params = (char *)zbx_mock_get_parameter_string("in.params");
	step.error_handler = ZBX_PREPROC_FAIL_DEFAULT;

	zbx_variant_set_none(&history_value_in);
	zbx_variant_set_none(&history_value_out);

	zbx_timespec(&ts);

	act_ret = pp_execute_step(&ctx, NULL, NULL, 0, ITEM_VALUE_TYPE_TEXT, &value, ts, &step, &history_value_in,
			&history_value_out, &history_ts, get_zbx_config_source_ip());

	exp_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	zbx_mock_assert_int_eq("return value", exp_ret, act_ret);

	if (FAIL == act_ret)
		zbx_mock_assert_int_eq("result variant type", ZBX_VARIANT_ERR, value.type);
	else
		zbx_mock_assert_str_eq("result", exp_json, value.data.str);

	zbx_variant_clear(&value);
	pp_context_destroy(&ctx);
}
