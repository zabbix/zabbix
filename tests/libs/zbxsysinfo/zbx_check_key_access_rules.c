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

#include "zbxcommon.h"
#include "zbxalgo.h"
#include "module.h"
#include "zbxsysinfo.h"

#include "../../../src/libs/zbxsysinfo/sysinfo.h"

typedef struct
{
	int	type;
	int	is_regexp;
}
zbx_mock_key_access_rule_type_t;

static int	zbx_mock_str_to_key_access_result(const char *str)
{
	if (0 == strcmp(str, "ZBX_KEY_ACCESS_ALLOW"))
		return ZBX_KEY_ACCESS_ALLOW;

	if (0 == strcmp(str, "ZBX_KEY_ACCESS_DENY"))
		return ZBX_KEY_ACCESS_DENY;

	if (0 == strcmp(str, "ZBX_KEY_ACCESS_ALLOW_REGEXP") || 0 == strcmp(str, "ZBX_KEY_ACCESS_DENY_REGEXP"))
		fail_msg("Invalid out.metrics[].result \"%s\": use ZBX_KEY_ACCESS_ALLOW or ZBX_KEY_ACCESS_DENY. "
				"*_REGEXP is valid only for in.rules[].type.", str);

	fail_msg("Unknown key access type \"%s\"", str);
	return ZBX_KEY_ACCESS_ALLOW;
}

static zbx_mock_key_access_rule_type_t	zbx_mock_str_to_key_access_rule_type(const char *str)
{
	zbx_mock_key_access_rule_type_t	ret;

	if (0 == strcmp(str, "ZBX_KEY_ACCESS_ALLOW"))
	{
		ret.type = ZBX_KEY_ACCESS_ALLOW;
		ret.is_regexp = 0;
		return ret;
	}

	if (0 == strcmp(str, "ZBX_KEY_ACCESS_DENY"))
	{
		ret.type = ZBX_KEY_ACCESS_DENY;
		ret.is_regexp = 0;
		return ret;
	}

	if (0 == strcmp(str, "ZBX_KEY_ACCESS_ALLOW_REGEXP"))
	{
		ret.type = ZBX_KEY_ACCESS_ALLOW;
		ret.is_regexp = 1;
		return ret;
	}

	if (0 == strcmp(str, "ZBX_KEY_ACCESS_DENY_REGEXP"))
	{
		ret.type = ZBX_KEY_ACCESS_DENY;
		ret.is_regexp = 1;
		return ret;
	}

	fail_msg("Unknown key access type \"%s\"", str);
	ret.type = ZBX_KEY_ACCESS_ALLOW;
	ret.is_regexp = 0;
	return ret;
}

void	zbx_mock_test_entry(void **state)
{
	zbx_mock_error_t		error;
	zbx_mock_handle_t		hrules, hrule, hmetrics, hmetric, hparam;
	const char			*type, *pattern, *key, *tmp;
	int				expected_ret, actual_ret, exit_code = SUCCEED, expect_rule_add_failure = 0;
	zbx_uint64_t			rules;
	zbx_mock_key_access_rule_type_t	rule_type;

	ZBX_UNUSED(state);

	hrules = zbx_mock_get_parameter_handle("in.rules");
	zbx_init_key_access_rules();

	error = zbx_mock_parameter("in.rule_add_failure", &hparam);

	if (ZBX_MOCK_SUCCESS == error)
	{
		if (ZBX_MOCK_SUCCESS != (error = zbx_mock_string(hparam, &tmp)))
			fail_msg("Cannot get in.rule_add_failure value: %s", zbx_mock_error_string(error));

		if (0 == strcmp(tmp, "yes"))
			expect_rule_add_failure = 1;
		else if (0 != strcmp(tmp, "no"))
			fail_msg("Invalid in.rule_add_failure value \"%s\": expected \"yes\" or \"no\".", tmp);
	}
	else if (ZBX_MOCK_NO_PARAMETER != error && ZBX_MOCK_NO_SUCH_MEMBER != error)
	{
		fail_msg("Cannot get in.rule_add_failure parameter: %s", zbx_mock_error_string(error));
	}

	while (ZBX_MOCK_SUCCESS == zbx_mock_vector_element(hrules, &hrule))
	{
		type = zbx_mock_get_object_member_string(hrule, "type");
		pattern = zbx_mock_get_object_member_string(hrule, "pattern");
		rule_type = zbx_mock_str_to_key_access_rule_type(type);

		if (0 == rule_type.is_regexp)
			actual_ret = zbx_add_key_access_rule("key", (char *)pattern, rule_type.type);
		else
			actual_ret = zbx_add_key_access_rule_regexp("key", (char *)pattern, rule_type.type);

		if (SUCCEED != actual_ret)
		{
			if (0 != expect_rule_add_failure)
			{
				zbx_free_key_access_rules();
				return;
			}

			zbx_free_key_access_rules();
			fail_msg("Bad key access rule definition");
		}
	}

	if (0 != expect_rule_add_failure)
	{
		zbx_free_key_access_rules();
		fail_msg("Expected key access rule add failure, but all rules were added successfully");
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
		expected_ret = zbx_mock_str_to_key_access_result(zbx_mock_get_object_member_string(hmetric, "result"));

		actual_ret = zbx_check_key_access_rules(key);

		if (expected_ret != actual_ret)
			fail_msg("Unexpected result for metric \"%s\": %s, expected %s", key,
					actual_ret == ZBX_KEY_ACCESS_ALLOW ? "Allow" : "Deny",
					expected_ret == ZBX_KEY_ACCESS_ALLOW ? "Allow" : "Deny");
	}

	zbx_free_key_access_rules();
}
