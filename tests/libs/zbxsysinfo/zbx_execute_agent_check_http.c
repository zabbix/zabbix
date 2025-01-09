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

#include "zbxdbhigh.h"
#include "zbxsysinfo.h"
#include "../../../src/libs/zbxsysinfo/sysinfo.h"
#include "zbxnum.h"
#include "zbxstr.h"

static char	*called_key = NULL;

int	__wrap_web_page_get(const char *command, AGENT_RESULT *result);
int	__wrap_web_page_perf(const char *command, AGENT_RESULT *result);
int	__wrap_web_page_regexp(const char *command, AGENT_RESULT *result);

void	zbx_mock_test_entry(void **state)
{
	zbx_mock_error_t	error;
	zbx_mock_handle_t	in_command, in_flags;
	const char		*in_command_string, *flags_string, *p;
	char			key[ZBX_ITEM_KEY_LEN];
	unsigned		flags_uint32;
	AGENT_RESULT		result;

	ZBX_UNUSED(state);

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("in_command", &in_command)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(in_command, &in_command_string)))
	{
		fail_msg("Cannot get in_command from test case data: %s", zbx_mock_error_string(error));
	}

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_in_parameter("flags", &in_flags)) ||
			ZBX_MOCK_SUCCESS != (error = zbx_mock_string(in_flags, &flags_string)))
	{
		fail_msg("Cannot get flags from test case data: %s", zbx_mock_error_string(error));
	}
	if (SUCCEED != zbx_is_uint32(flags_string, &flags_uint32))
		fail_msg("Cannot convert flags to unsigned 32 bit integer.");

	zbx_init_library_sysinfo(NULL, get_zbx_config_enable_remote_commands, NULL, NULL, NULL, NULL, NULL, NULL,
			NULL, NULL);

	zbx_init_metrics();

	zbx_execute_agent_check(in_command_string, flags_uint32, &result, 3);

	if (NULL != (p = strchr(in_command_string, '[')))
		zbx_strlcpy(key, in_command_string, p - in_command_string + 1);

	if (called_key == NULL || 0 != strcmp((NULL == p ? in_command_string : key), called_key))
	{
		fail_msg("Unexpected called item '%s' instead of '%s' as a key.",
				ZBX_NULL2STR(called_key), in_command_string);
	}
}

int	__wrap_web_page_get(const char *command, AGENT_RESULT *result)
{
	ZBX_UNUSED(command);
	ZBX_UNUSED(result);

	called_key = "web.page.get";

	return SUCCEED;
}

int	__wrap_web_page_perf(const char *command, AGENT_RESULT *result)
{
	ZBX_UNUSED(command);
	ZBX_UNUSED(result);

	called_key = "web.page.perf";

	return SUCCEED;
}

int	__wrap_web_page_regexp(const char *command, AGENT_RESULT *result)
{
	ZBX_UNUSED(command);
	ZBX_UNUSED(result);

	called_key = "web.page.regexp";

	return SUCCEED;
}
