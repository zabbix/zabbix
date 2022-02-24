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
#include "zbxeval.h"
#include "log.h"
#include "mock_eval.h"

void	zbx_mock_test_entry(void **state)
{
	zbx_eval_context_t	ctx;
	char			*error = NULL;
	zbx_uint64_t		rules;
	int			expected_ret, returned_ret;
	zbx_variant_t		value;
	zbx_mock_handle_t	htime;
	zbx_timespec_t		ts, *pts = NULL;

	ZBX_UNUSED(state);

	rules = mock_eval_read_rules("in.rules");
	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.result"));

	if (SUCCEED != zbx_eval_parse_expression(&ctx, zbx_mock_get_parameter_string("in.expression"), rules, &error))
	{
		if (SUCCEED != expected_ret)
			goto out;

		fail_msg("failed to parse expression: %s", error);
	}

	mock_eval_read_values(&ctx, "in.replace");

	if (ZBX_MOCK_SUCCESS == zbx_mock_parameter("in.time", &htime))
	{
		const char	*str;

		if (ZBX_MOCK_SUCCESS != zbx_mock_string(htime, &str))
			fail_msg("invalid in.time field");

		if (ZBX_MOCK_SUCCESS != zbx_strtime_to_timespec(str, &ts))
			fail_msg("Invalid in.time format");

		if (0 != setenv("TZ", zbx_mock_get_parameter_string("in.timezone"), 1))
				fail_msg("Cannot set 'TZ' environment variable: %s", zbx_strerror(errno));

		pts = &ts;
	}

	returned_ret = zbx_eval_execute(&ctx, pts, &value, &error);

	if (SUCCEED != returned_ret)
		printf("ERROR: %s\n", error);

	zbx_mock_assert_result_eq("return value", expected_ret, returned_ret);

	if (SUCCEED == expected_ret)
	{
		/* use custom epsilon for floating point values to account for */
		/* rounding differences with various systems/libs              */
		if (ZBX_VARIANT_DBL == value.type)
		{
			double	expected_value;

			expected_value = atof(zbx_mock_get_parameter_string("out.value"));

			if (1e-12 < fabs(value.data.dbl - expected_value))
				fail_msg("Expected value \"%f\" while got \"%f\"", expected_value, value.data.dbl);
		}
		else
		{
			zbx_mock_assert_str_eq("output value", zbx_mock_get_parameter_string("out.value"),
				zbx_variant_value_desc(&value));
		}

		zbx_variant_clear(&value);
	}
out:
	zbx_free(error);
	zbx_eval_clear(&ctx);
}
