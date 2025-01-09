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

#include "module.h"
#include "zbxsysinfo.h"
#include "zbxstr.h"
#include "zbxnum.h"
#include "zbxalgo.h"
#include "zbxdbhigh.h"

static char	*called_key = NULL;

int	__wrap_system_localtime(const char *command, AGENT_RESULT *result);
int	__wrap_vfs_file_size(const char *command, AGENT_RESULT *result);
int	__wrap_vfs_file_time(const char *command, AGENT_RESULT *result);
int	__wrap_vfs_file_exists(const char *command, AGENT_RESULT *result);
int	__wrap_vfs_file_contents(const char *command, AGENT_RESULT *result);
int	__wrap_vfs_file_regexp(const char *command, AGENT_RESULT *result);
int	__wrap_vfs_file_regmatch(const char *command, AGENT_RESULT *result);
int	__wrap_vfs_file_md5sum(const char *command, AGENT_RESULT *result);
int	__wrap_vfs_file_cksum(const char *command, AGENT_RESULT *result);
int	__wrap_vfs_dir_size(const char *command, AGENT_RESULT *result);
int	__wrap_vfs_dev_discovery(const char *command, AGENT_RESULT *result);
int	__wrap_vfs_dev_read(const char *command, AGENT_RESULT *result);
int	__wrap_vfs_dev_write(const char *command, AGENT_RESULT *result);
int	__wrap_vfs_dir_count(const char *command, AGENT_RESULT *result);
int	__wrap_vfs_dir_get(const char *command, AGENT_RESULT *result);
int	__wrap_vfs_file_get(const char *command, AGENT_RESULT *result);
int	__wrap_vfs_file_owner(const char *command, AGENT_RESULT *result);
int	__wrap_vfs_file_permissions(const char *command, AGENT_RESULT *result);
int	__wrap_vfs_fs_discovery(const char *command, AGENT_RESULT *result);
int	__wrap_vfs_fs_get(const char *command, AGENT_RESULT *result);
int	__wrap_vfs_fs_inode(const char *command, AGENT_RESULT *result);
int	__wrap_vfs_fs_size(const char *command, AGENT_RESULT *result);
int	__wrap_net_dns(const char *command, AGENT_RESULT *result);
int	__wrap_net_dns_record(const char *command, AGENT_RESULT *result);
int	__wrap_net_dns_perf(const char *command, AGENT_RESULT *result);
int	__wrap_net_tcp_port(const char *command, AGENT_RESULT *result);
int	__wrap_system_users_num(const char *command, AGENT_RESULT *result);

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

#define	WRAP_HELPER(func_postfix, key)						\
int	__wrap_##func_postfix(const char *command, AGENT_RESULT *result)	\
{										\
	ZBX_UNUSED(command);							\
	ZBX_UNUSED(result);							\
										\
	called_key = key;							\
										\
	return SUCCEED;								\
}

WRAP_HELPER(system_localtime, "system.localtime")
WRAP_HELPER(vfs_dev_discovery, "vfs.dev.discovery")
WRAP_HELPER(vfs_dev_read, "vfs.dev.read")
WRAP_HELPER(vfs_dev_write, "vfs.dev.write")
WRAP_HELPER(vfs_dir_count, "vfs.dir.count")
WRAP_HELPER(vfs_dir_get, "vfs.dir.get")
WRAP_HELPER(vfs_dir_size, "vfs.dir.size")
WRAP_HELPER(vfs_file_cksum, "vfs.file.cksum")
WRAP_HELPER(vfs_file_contents, "vfs.file.contents")
WRAP_HELPER(vfs_file_exists, "vfs.file.exists")
WRAP_HELPER(vfs_file_get, "vfs.file.get")
WRAP_HELPER(vfs_file_md5sum, "vfs.file.md5sum")
WRAP_HELPER(vfs_file_owner, "vfs.file.owner")
WRAP_HELPER(vfs_file_permissions, "vfs.file.permissions")
WRAP_HELPER(vfs_file_regexp, "vfs.file.regexp")
WRAP_HELPER(vfs_file_regmatch, "vfs.file.regmatch")
WRAP_HELPER(vfs_file_size, "vfs.file.size")
WRAP_HELPER(vfs_file_time, "vfs.file.time")
WRAP_HELPER(vfs_fs_discovery, "vfs.fs.discovery")
WRAP_HELPER(vfs_fs_get, "vfs.fs.get")
WRAP_HELPER(vfs_fs_inode, "vfs.fs.inode")
WRAP_HELPER(vfs_fs_size, "vfs.fs.size")
WRAP_HELPER(net_dns, "net.dns")
WRAP_HELPER(net_dns_record, "net.dns.record")
WRAP_HELPER(net_dns_perf, "net.dns.perf")
WRAP_HELPER(net_tcp_port, "net.tcp.port")
WRAP_HELPER(system_users_num, "system.users.num")
#undef	WRAP_HELPER
