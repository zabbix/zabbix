/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

#include "common.h"
#include "zbxalgo.h"
#include "db.h"
#include "module.h"
#include "sysinfo.h"

static char	*called_key = NULL;

void	zbx_mock_test_entry(void **state)
{
	zbx_mock_error_t	error;
	zbx_mock_handle_t	in_command, in_flags;
	const char		*in_command_string, *flags_string, *p;
	char			key[ITEM_KEY_LEN];
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
	if (SUCCEED != is_uint32(flags_string, &flags_uint32))
		fail_msg("Cannot convert flags to unsigned 32 bit integer.");

	init_metrics();

	process(in_command_string, flags_uint32, &result);

	if (NULL != (p = strchr(in_command_string, '[')))
		zbx_strlcpy(key, in_command_string, p - in_command_string + 1);

	if (called_key == NULL || 0 != strcmp((NULL == p ? in_command_string : key), called_key))
	{
		fail_msg("Unexpected called item '%s' instead of '%s' as a key.",
				ZBX_NULL2STR(called_key), in_command_string);
	}
}

int	__wrap_SYSTEM_LOCALTIME(const char *command, AGENT_RESULT *result)
{
	ZBX_UNUSED(command);
	ZBX_UNUSED(result);

	called_key = "system.localtime";

	return SUCCEED;
}

int	__wrap_WEB_PAGE_GET(const char *command, AGENT_RESULT *result)
{
	ZBX_UNUSED(command);
	ZBX_UNUSED(result);

	called_key = "web.page.get";

	return SUCCEED;
}

int	__wrap_WEB_PAGE_PERF(const char *command, AGENT_RESULT *result)
{
	ZBX_UNUSED(command);
	ZBX_UNUSED(result);

	called_key = "web.page.perf";

	return SUCCEED;
}

int	__wrap_WEB_PAGE_REGEXP(const char *command, AGENT_RESULT *result)
{
	ZBX_UNUSED(command);
	ZBX_UNUSED(result);

	called_key = "web.page.regexp";

	return SUCCEED;
}

int	__wrap_VFS_FILE_SIZE(const char *command, AGENT_RESULT *result)
{
	ZBX_UNUSED(command);
	ZBX_UNUSED(result);

	called_key = "vfs.file.size";

	return SUCCEED;
}

int	__wrap_VFS_FILE_TIME(const char *command, AGENT_RESULT *result)
{
	ZBX_UNUSED(command);
	ZBX_UNUSED(result);

	called_key = "vfs.file.time";

	return SUCCEED;
}

int	__wrap_VFS_FILE_EXISTS(const char *command, AGENT_RESULT *result)
{
	ZBX_UNUSED(command);
	ZBX_UNUSED(result);

	called_key = "vfs.file.exists";

	return SUCCEED;
}

int	__wrap_VFS_FILE_CONTENTS(const char *command, AGENT_RESULT *result)
{
	ZBX_UNUSED(command);
	ZBX_UNUSED(result);

	called_key = "vfs.file.contents";

	return SUCCEED;
}

int	__wrap_VFS_FILE_REGEXP(const char *command, AGENT_RESULT *result)
{
	ZBX_UNUSED(command);
	ZBX_UNUSED(result);

	called_key = "vfs.file.regexp";

	return SUCCEED;
}

int	__wrap_VFS_FILE_REGMATCH(const char *command, AGENT_RESULT *result)
{
	ZBX_UNUSED(command);
	ZBX_UNUSED(result);

	called_key = "vfs.file.regmatch";

	return SUCCEED;
}

int	__wrap_VFS_FILE_MD5SUM(const char *command, AGENT_RESULT *result)
{
	ZBX_UNUSED(command);
	ZBX_UNUSED(result);

	called_key = "vfs.file.md5sum";

	return SUCCEED;
}

int	__wrap_VFS_FILE_CKSUM(const char *command, AGENT_RESULT *result)
{
	ZBX_UNUSED(command);
	ZBX_UNUSED(result);

	called_key = "vfs.file.cksum";

	return SUCCEED;
}

int	__wrap_VFS_DIR_SIZE(const char *command, AGENT_RESULT *result)
{
	ZBX_UNUSED(command);
	ZBX_UNUSED(result);

	called_key = "vfs.dir.size";

	return SUCCEED;
}

int	__wrap_NET_DNS(const char *command, AGENT_RESULT *result)
{
	ZBX_UNUSED(command);
	ZBX_UNUSED(result);

	called_key = "net.dns";

	return SUCCEED;
}

int	__wrap_NET_DNS_RECORD(const char *command, AGENT_RESULT *result)
{
	ZBX_UNUSED(command);
	ZBX_UNUSED(result);

	called_key = "net.dns.record";

	return SUCCEED;
}

int	__wrap_NET_TCP_PORT(const char *command, AGENT_RESULT *result)
{
	ZBX_UNUSED(command);
	ZBX_UNUSED(result);

	called_key = "net.tcp.port";

	return SUCCEED;
}

int	__wrap_SYSTEM_USERS_NUM(const char *command, AGENT_RESULT *result)
{
	ZBX_UNUSED(command);
	ZBX_UNUSED(result);

	called_key = "system.users.num";

	return SUCCEED;
}
