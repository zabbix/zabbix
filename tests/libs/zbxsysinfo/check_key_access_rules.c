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

#include "zbxcommon.h"
#include "zbxalgo.h"
#include "module.h"
#include "zbxsysinfo.h"

#include "../../../src/libs/zbxsysinfo/sysinfo.h"

static int	zbx_mock_str_to_key_access_type(const char *str)
{
	if (0 == strcmp(str, "ZBX_KEY_ACCESS_ALLOW"))
		return ZBX_KEY_ACCESS_ALLOW;

	if (0 == strcmp(str, "ZBX_KEY_ACCESS_DENY"))
		return ZBX_KEY_ACCESS_DENY;

	fail_msg("Unknown key access type \"%s\"", str);
	return ZBX_KEY_ACCESS_ALLOW;
}

void	zbx_mock_test_entry(void **state)
{
	zbx_mock_error_t	error;
	zbx_mock_handle_t	hrules, hrule, hmetrics, hmetric;
	const char		*type, *pattern, *key;
	int			expected_ret, actual_ret, exit_code = SUCCEED;
	zbx_uint64_t		rules;

	ZBX_UNUSED(state);

	hrules = zbx_mock_get_parameter_handle("in.rules");
	zbx_init_key_access_rules();

	while (ZBX_MOCK_SUCCESS == zbx_mock_vector_element(hrules, &hrule))
	{
		type = zbx_mock_get_object_member_string(hrule, "type");
		pattern = zbx_mock_get_object_member_string(hrule, "pattern");

		if (SUCCEED != zbx_add_key_access_rule("key", (char *)pattern, zbx_mock_str_to_key_access_type(type)))
		{
			zbx_free_key_access_rules();
			fail_msg("Bad key access rule definition");
		}
	}

	zbx_finalize_key_access_rules_configuration();

	if (ZBX_MOCK_NO_EXIT_CODE != (error = zbx_mock_exit_code(&exit_code)))
	{
		if (ZBX_MOCK_SUCCESS == error)
			fail_msg("exit(%d) expected", exit_code);
		else
			fail_msg("Cannot get exit code from test case data: %s", zbx_mock_error_string(error));
	}

	rules = zbx_mock_get_parameter_uint64("out.number_of_rules");

	if ((int)rules != (get_key_access_rules())->values_num)
	{
		fail_msg("Number of key access rules is %d, but %d expected", (get_key_access_rules())->values_num,
				(int)rules);
	}

	hmetrics = zbx_mock_get_parameter_handle("out.metrics");

	while (ZBX_MOCK_SUCCESS == zbx_mock_vector_element(hmetrics, &hmetric))
	{
		key = zbx_mock_get_object_member_string(hmetric, "metric");
		expected_ret = zbx_mock_str_to_key_access_type(zbx_mock_get_object_member_string(hmetric, "result"));

		actual_ret = zbx_check_key_access_rules(key);

		if (expected_ret != actual_ret)
			fail_msg("Unexpected result for metric \"%s\": %s, expected %s", key,
					actual_ret == ZBX_KEY_ACCESS_ALLOW ? "Allow" : "Deny",
					expected_ret == ZBX_KEY_ACCESS_ALLOW ? "Allow" : "Deny");
	}

	zbx_free_key_access_rules();
}
