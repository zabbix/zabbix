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

#include "zbxexpr.h"
#include "zbxtime.h"

time_t	__wrap_time(time_t *ptr);

static zbx_timespec_t	vcmock_ts;

/******************************************************************************
 *                                                                            *
 * Purpose: sets the current time. The key must be present in input data or   *
 *          the test case will fail                                           *
 *                                                                            *
 ******************************************************************************/
static void	vcmock_set_time(zbx_mock_handle_t hitem, const char *key)
{
	zbx_mock_error_t	err;
	const char		*data;

	data = zbx_mock_get_object_member_string(hitem, key);

	if (ZBX_MOCK_SUCCESS != (err = zbx_strtime_to_timespec(data, &vcmock_ts)))
		fail_msg("Cannot read \"%s\" parameter", key);
}

/*
 * time() emulation
 */
time_t	__wrap_time(time_t *ptr)
{
	if (NULL != ptr)
		*ptr = vcmock_ts.sec;

	return vcmock_ts.sec;
}

void	zbx_mock_test_entry(void **state)
{
	const size_t		macro_pos = 1, macro_pos_end = 6, func_pos = 8, func_param_pos = 15;
	int			expected_ret, returned_ret, err;
	char			*returned_value = NULL, macro_expr[MAX_STRING_LEN];
	const char		*expected_value;
	zbx_token_func_macro_t	token;
	zbx_mock_handle_t	handle;
	struct tm		ltm;
	time_t			time_new;

	ZBX_UNUSED(state);

	if (0 != setenv("TZ", zbx_mock_get_parameter_string("in.timezone"), 1))
		fail_msg("Cannot set 'TZ' environment variable: %s", zbx_strerror(errno));

	handle = zbx_mock_get_parameter_handle("in");
	vcmock_set_time(handle, "time");

	zbx_snprintf(macro_expr, MAX_STRING_LEN, "{{TIME}.fmttime(%s, %s)}",
			zbx_mock_get_parameter_string("in.fmt"), zbx_mock_get_parameter_string("in.period"));

	token = (zbx_token_func_macro_t)
		{
			.macro		= { macro_pos, macro_pos_end },
			.func		= { func_pos, strlen(macro_expr) - 2 },
			.func_param	= { func_param_pos, strlen(macro_expr) - 2 }
		};

#define FMTTIME_INPUT_SIZE	20
	time_new = time(&time_new);
	localtime_r(&time_new, &ltm);
	returned_value = zbx_malloc(returned_value, FMTTIME_INPUT_SIZE);
	zbx_snprintf(returned_value, FMTTIME_INPUT_SIZE, "%.4d-%.2d-%.2dT%.2d:%.2d:%.2d", ltm.tm_year + 1900,
			ltm.tm_mon + 1, ltm.tm_mday, ltm.tm_hour, ltm.tm_min, ltm.tm_sec);

	returned_ret = zbx_calculate_macro_function(macro_expr, &token, &returned_value);
	expected_ret = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));
	zbx_mock_assert_result_eq("return value", expected_ret, returned_ret);

	if (SUCCEED == expected_ret)
	{
		handle = zbx_mock_get_parameter_handle("out.value");

		if (ZBX_MOCK_SUCCESS != (err = zbx_mock_string_ex(handle, &expected_value)))
			fail_msg("Cannot read output value: %s", zbx_mock_error_string(err));

		zbx_mock_assert_str_eq("fmttime result", expected_value, returned_value);
	}

	zbx_free(returned_value);
#undef FMTTIME_INPUT_SIZE
}
