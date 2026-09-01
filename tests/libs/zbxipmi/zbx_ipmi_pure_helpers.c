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

/* has_name_prefix(), get_domain_offset(), add_threshold_ipmi() and zbx_parse_ipmi_command() are pure    */
/* string/data functions with no OpenIPMI object involved at all - none of them need a real sensor,      */
/* control, or connection, so they're covered here directly, one cmocka binary testing all four via       */
/* 'in.func'. checks_ipmi.c is included directly to reach the three static ones.                          */
#include "../../../src/libs/zbxipmi/checks_ipmi.c"

#include "zbxmocktest.h"
#include "zbxmockdata.h"
#include "zbxmockutil.h"
#include "zbxmockassert.h"

static void	test_has_name_prefix(void)
{
	size_t		prefix_len;
	int		exp_ret = (0 == strcmp(zbx_mock_get_parameter_string("out.ret"), "1")) ? 1 : 0;
	int		ret = has_name_prefix(zbx_mock_get_parameter_string("in.str"), &prefix_len);

	zbx_mock_assert_int_eq("has_name_prefix() return value", exp_ret, ret);
	zbx_mock_assert_int_eq("prefix_len", zbx_mock_get_parameter_int("out.prefix_len"), (int)prefix_len);
}

static void	test_get_domain_offset(void)
{
	zbx_ipmi_host_t	h;
	size_t		offset;

	memset(&h, 0, sizeof(h));
	h.domain_nr = (unsigned int)zbx_mock_get_parameter_int("in.domain_nr");

	offset = get_domain_offset(&h, zbx_mock_get_parameter_string("in.full_name"));

	zbx_mock_assert_int_eq("offset", zbx_mock_get_parameter_int("out.offset"), (int)offset);
}

static void	test_add_threshold_ipmi(void)
{
	struct zbx_json			json;
	zbx_ipmi_sensor_threshold_t	threshold;

	int	exp_present = (0 == strcmp(zbx_mock_get_parameter_string("out.present"), "yes"));

	memset(&threshold, 0, sizeof(threshold));
	threshold.status = (0 == strcmp(zbx_mock_get_parameter_string("in.status"), "enabled")) ?
			ZBX_IPMI_THRESHOLD_STATUS_ENABLED : ZBX_IPMI_THRESHOLD_STATUS_DISABLED;
	threshold.val = (0 == strcmp(zbx_mock_get_parameter_string("in.val"), "infinity")) ?
			(double)INFINITY : zbx_mock_get_parameter_float("in.val");

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addobject(&json, NULL);
	add_threshold_ipmi(&json, "thr", &threshold);
	zbx_json_close(&json);

	if (exp_present)
		zbx_mock_assert_ptr_ne("threshold should be present in the JSON", NULL, strstr(json.buffer, "\"thr\""));
	else
		zbx_mock_assert_ptr_eq("threshold should not be present in the JSON", NULL,
				strstr(json.buffer, "\"thr\""));

	zbx_json_free(&json);
}

static void	test_parse_ipmi_command(void)
{
	char	c_name[ZBX_ITEM_IPMI_SENSOR_LEN_MAX];
	char	error[MAX_STRING_LEN];
	int	val = -1;
	int	ret;
	int	exp_ret = (0 == strcmp(zbx_mock_get_parameter_string("out.ret"), "SUCCEED")) ? SUCCEED : FAIL;

	c_name[0] = '\0';
	error[0] = '\0';

	ret = zbx_parse_ipmi_command(zbx_mock_get_parameter_string("in.command"), c_name, &val, error, sizeof(error));

	zbx_mock_assert_int_eq("zbx_parse_ipmi_command() return value", exp_ret, ret);

	if (SUCCEED == exp_ret)
	{
		zbx_mock_assert_str_eq("c_name", zbx_mock_get_parameter_string("out.c_name"), c_name);
		zbx_mock_assert_int_eq("val", zbx_mock_get_parameter_int("out.val"), val);
	}
	else if ('\0' == error[0])
		fail_msg("error should describe why parsing failed, got an empty string");
}

void	zbx_mock_test_entry(void **state)
{
	const char	*func = zbx_mock_get_parameter_string("in.func");

	ZBX_UNUSED(state);

	if (0 == strcmp(func, "has_name_prefix"))
		test_has_name_prefix();
	else if (0 == strcmp(func, "get_domain_offset"))
		test_get_domain_offset();
	else if (0 == strcmp(func, "add_threshold_ipmi"))
		test_add_threshold_ipmi();
	else if (0 == strcmp(func, "parse_ipmi_command"))
		test_parse_ipmi_command();
	else
		fail_msg("unknown 'in.func' value: %s", func);
}
