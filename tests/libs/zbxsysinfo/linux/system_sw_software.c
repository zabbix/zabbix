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
#include "zbxmockutil.h"
#include "zbxmockhelper.h"

#include "zbxsysinfo.h"
#include "../../../../src/libs/zbxsysinfo/sysinfo.h"

FILE	*custom_fopen_mock(const char *__filename, const char *__modes);
int	__wrap_uname(struct utsname *buf);

FILE	*custom_fopen_mock(const char *__filename, const char *__modes)
{
	const char	*str;
	size_t		f_size;

	ZBX_UNUSED(__modes);

	if (0 == strcmp(__filename, "/proc/version"))
		str = zbx_mock_get_parameter_string("in.proc_version");
	else if (0 == strcmp(__filename, "/proc/version_signature"))
		str = zbx_mock_get_parameter_string("in.proc_version_sign");
	else if (0 == strcmp(__filename, "/etc/issue.net"))
		str = zbx_mock_get_parameter_string("in.issue_net");
	else if (0 == strcmp(__filename, "/etc/os-release"))
		str = zbx_mock_get_parameter_string("in.os_release");
	else
		return NULL;

	f_size = strlen(str);

	return fmemopen((void *)str, f_size * sizeof(char), "r");
}

int	__wrap_uname(struct utsname *buf)
{
	const char	*release, *machine;
	int		ret;

	ret = (int)zbx_mock_get_parameter_uint64("in.uname.return");

	if (0 == ret) {
		release = zbx_mock_get_parameter_string("in.uname.release");
		buf->release[0] = '\0';
		buf->machine[0] = '\0';

		if (sizeof(buf->release) < strlen(release) * sizeof(char))
			fail_msg("Uname release string is too large, maximum length is: %lu bytes", sizeof(buf->release));
		else
			strcat(buf->release, release);

		machine = zbx_mock_get_parameter_string("in.uname.machine");

		if (sizeof(buf->machine) < strlen(machine) * sizeof(char))
			fail_msg("Uname machine string is too large, maximum length is: %lu bytes", sizeof(buf->release));
		else
			strcat(buf->machine, machine);
	}

	return ret;
}

void	zbx_mock_test_entry(void **state)
{
	AGENT_REQUEST	request;
	AGENT_RESULT	result;
	const char	*key, *expected_value, *actual_value, *test, *f_name;
	int		expected_result, actual_result;
	int		(*f_test)(AGENT_REQUEST *, AGENT_RESULT *) = NULL;

	ZBX_UNUSED(state);
	zbx_set_fopen_mock_callback(&custom_fopen_mock);

	zbx_init_agent_request(&request);
	zbx_init_agent_result(&result);

	test = zbx_mock_get_parameter_string("in.type");

	if (0 == strcmp(test, "os"))
	{
		f_name = "system_sw_os";
		f_test = system_sw_os;
	}
	else if (0 == strcmp(test, "get"))
	{
		f_name = "system_sw_os_get";
		f_test = system_sw_os_get;
	}
	else
		fail_msg("Unknown test type of '%s'.", test);

	key = zbx_mock_get_parameter_string("in.key");
	expected_value = zbx_mock_get_parameter_string("out.value");
	expected_result = zbx_mock_str_to_return_code(zbx_mock_get_parameter_string("out.return"));

	if (SUCCEED != zbx_parse_item_key(key, &request))
		fail_msg("Cannot parse item key: %s", key);

	if (expected_result != (actual_result = (*f_test)(&request, &result)))
	{
		fail_msg("Unexpected return code from %s(): expected %s, got %s", f_name,
				zbx_sysinfo_ret_string(expected_result), zbx_sysinfo_ret_string(actual_result));
	}

	if (SYSINFO_RET_OK == expected_result)
	{
		if (NULL == ZBX_GET_STR_RESULT(&result))
			fail_msg("Got 'NULL' instead of '%s' as a value.", expected_value);

		actual_value = *ZBX_GET_STR_RESULT(&result);
		if (0 != strcmp(expected_value, actual_value))
			fail_msg("Got '%s' instead of '%s' as a value.", actual_value, expected_value);
	}

	zbx_free_agent_request(&request);
	zbx_free_agent_result(&result);
}
