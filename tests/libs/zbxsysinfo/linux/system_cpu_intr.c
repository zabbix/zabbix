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
#include "zbxmockhelper.h"
#include "zbxmockutil.h"

#include "module.h"
#include "zbxsysinfo.h"
#include "../../../../src/libs/zbxsysinfo/sysinfo.h"

static int	read_yaml_ret(void)
{
	zbx_mock_handle_t	handle;
	zbx_mock_error_t	error;
	const char		*str;

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_out_parameter("ret", &handle)))
		fail_msg("Cannot get return code: %s", zbx_mock_error_string(error));

	if (ZBX_MOCK_SUCCESS != (error = zbx_mock_string(handle, &str)))
		fail_msg("Cannot read return code: %s", zbx_mock_error_string(error));

	if (0 == strcasecmp(str, "succeed"))
		return SYSINFO_RET_OK;

	if (0 != strcasecmp(str, "fail"))
		fail_msg("Incorrect return code '%s'", str);

	return SYSINFO_RET_FAIL;
}

void	zbx_mock_test_entry(void **state)
{
	const char	*itemkey = "system.cpu.intr";
	AGENT_RESULT	result;
	AGENT_REQUEST	request;
	int		ret;

	ZBX_UNUSED(state);

	zbx_init_agent_result(&result);
	zbx_init_agent_request(&request);

	zbx_init_library_sysinfo(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

	if (SUCCEED != zbx_parse_item_key(itemkey, &request))
		fail_msg("Invalid item key format '%s'", itemkey);

	if (read_yaml_ret() != (ret = system_cpu_intr(&request, &result)))
		fail_msg("unexpected return code '%s'", zbx_sysinfo_ret_string(ret));

	if (SYSINFO_RET_OK == ret)
	{
		zbx_uint64_t	interr;

		if (NULL == ZBX_GET_UI64_RESULT(&result))
			fail_msg("result does not contain numeric unsigned value");

		if ((interr = zbx_mock_get_parameter_uint64("out.interrupts_since_boot")) != result.ui64)
			fail_msg("expected:" ZBX_FS_UI64 " actual:" ZBX_FS_UI64, interr, result.ui64);
	}
	else if (NULL == ZBX_GET_MSG_RESULT(&result))
		fail_msg("result does not contain failure message");

	zbx_free_agent_request(&request);
	zbx_free_agent_result(&result);
}
