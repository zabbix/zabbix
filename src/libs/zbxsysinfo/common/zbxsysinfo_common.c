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

#include "zbxsysinfo_common.h"
#include "zbxsysinfo.h"

#include "../sysinfo.h"
#include "vfs_file.h"
#include "dir.h"
#include "net.h"
#include "dns.h"
#include "system.h"
#include "zabbix_stats.h"
#include "zbxexec.h"
#include "zbxstr.h"

#if !defined(_WINDOWS)
#	define VFS_TEST_FILE "/etc/passwd"
#	define VFS_TEST_REGEXP "root"
#	define VFS_TEST_DIR  "/var/log"
#else
#	define VFS_TEST_FILE "c:\\windows\\win.ini"
#	define VFS_TEST_REGEXP "fonts"
#	define VFS_TEST_DIR  "c:\\windows"
#endif

static int	only_active(AGENT_REQUEST *request, AGENT_RESULT *result);
static int	system_run(AGENT_REQUEST *request, AGENT_RESULT *result);
static int	system_run_no_remote(AGENT_REQUEST *request, AGENT_RESULT *result);

static zbx_metric_t	parameters_common_local[] =
/*	KEY			FLAG		FUNCTION		TEST PARAMETERS */
{
	{"system.run",		CF_HAVEPARAMS,	system_run_no_remote, 	"echo test"},
	{0}
};

zbx_metric_t	*get_parameters_common_local(void)
{
	return &parameters_common_local[0];
}

static zbx_metric_t	parameters_common[] =
/*	KEY			FLAG		FUNCTION		TEST PARAMETERS */
{
	{"system.localtime",	CF_HAVEPARAMS,	system_localtime,	"utc"},
	{"system.run",		CF_HAVEPARAMS,	system_run,		"echo test"},

	{"vfs.file.size",	CF_HAVEPARAMS,	vfs_file_size,		VFS_TEST_FILE},
	{"vfs.file.time",	CF_HAVEPARAMS,	vfs_file_time,		VFS_TEST_FILE ",modify"},
	{"vfs.file.exists",	CF_HAVEPARAMS,	vfs_file_exists,	VFS_TEST_FILE},
	{"vfs.file.contents",	CF_HAVEPARAMS,	vfs_file_contents,	VFS_TEST_FILE},
	{"vfs.file.regexp",	CF_HAVEPARAMS,	vfs_file_regexp,	VFS_TEST_FILE "," VFS_TEST_REGEXP},
	{"vfs.file.regmatch",	CF_HAVEPARAMS,	vfs_file_regmatch,	VFS_TEST_FILE "," VFS_TEST_REGEXP},
	{"vfs.file.md5sum",	CF_HAVEPARAMS,	vfs_file_md5sum,	VFS_TEST_FILE},
	{"vfs.file.cksum",	CF_HAVEPARAMS,	vfs_file_cksum,		VFS_TEST_FILE},
	{"vfs.file.owner",	CF_HAVEPARAMS,	vfs_file_owner,		VFS_TEST_FILE ",user,name"},
	{"vfs.file.permissions",CF_HAVEPARAMS,	vfs_file_permissions,	VFS_TEST_FILE},
	{"vfs.file.get",	CF_HAVEPARAMS,	vfs_file_get,		VFS_TEST_FILE},

	{"vfs.dir.size",	CF_HAVEPARAMS,	vfs_dir_size,		VFS_TEST_DIR},
	{"vfs.dir.count",	CF_HAVEPARAMS,	vfs_dir_count,		VFS_TEST_DIR},
	{"vfs.dir.get",		CF_HAVEPARAMS,	vfs_dir_get,		VFS_TEST_DIR},

	{"net.dns",		CF_HAVEPARAMS,	net_dns,		NULL},
	{"net.dns.record",	CF_HAVEPARAMS,	net_dns_record,		NULL},
	{"net.dns.perf",	CF_HAVEPARAMS,	net_dns_perf,		NULL},
	{"net.tcp.dns",		CF_HAVEPARAMS,	net_dns,		",zabbix.com"}, /* deprecated */
	{"net.tcp.dns.query",	CF_HAVEPARAMS,	net_dns_record,		",zabbix.com"}, /* deprecated */
	{"net.tcp.port",	CF_HAVEPARAMS,	net_tcp_port,		",80"},

	{"system.users.num",	0,		system_users_num,	NULL},

	{"log",			CF_HAVEPARAMS,	only_active,		"logfile"},
	{"log.count",		CF_HAVEPARAMS,	only_active,		"logfile"},
	{"logrt",		CF_HAVEPARAMS,	only_active,		"logfile"},
	{"logrt.count",		CF_HAVEPARAMS,	only_active,		"logfile"},
	{"eventlog",		CF_HAVEPARAMS,	only_active,		"system"},
	{"eventlog.count",	CF_HAVEPARAMS,	only_active,		"system"},

	{"zabbix.stats",	CF_HAVEPARAMS,	zabbix_stats,		"127.0.0.1,10051"},

	{0}
};

zbx_metric_t	*get_parameters_common(void)
{
	return &parameters_common[0];
}

static const char	*user_parameter_dir = NULL;

void	zbx_set_user_parameter_dir(const char *path)
{
	user_parameter_dir = path;
}

static int	only_active(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	ZBX_UNUSED(request);

	SET_MSG_RESULT(result, zbx_strdup(NULL, "Accessible only as active check."));

	return SYSINFO_RET_FAIL;
}

static int	execute_str_local(const char *command, AGENT_RESULT *result, const char* dir, int timeout)
{
	int		ret = SYSINFO_RET_FAIL;
	char		*cmd_result = NULL, error[MAX_STRING_LEN];

	if (SUCCEED != zbx_execute(command, &cmd_result, error, sizeof(error), timeout,
			ZBX_EXIT_CODE_CHECKS_DISABLED, dir))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, error));
		goto out;
	}

	zbx_rtrim(cmd_result, ZBX_WHITESPACE);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() command:'%s' len:" ZBX_FS_SIZE_T " cmd_result:'%.20s'",
			__func__, command, (zbx_fs_size_t)strlen(cmd_result), cmd_result);

	SET_TEXT_RESULT(result, zbx_strdup(NULL, cmd_result));

	ret = SYSINFO_RET_OK;
out:
	zbx_free(cmd_result);

	return ret;
}

int	execute_user_parameter(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	if (1 != request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	return execute_str_local(get_rparam(request, 0), result, user_parameter_dir, request->timeout);
}

int	execute_str(const char *command, AGENT_RESULT *result, int timeout)
{
	return execute_str_local(command, result, NULL, timeout);
}

int	execute_dbl(const char *command, AGENT_RESULT *result, int timeout)
{
	if (SYSINFO_RET_OK != execute_str(command, result, timeout))
		return SYSINFO_RET_FAIL;

	if (NULL == ZBX_GET_DBL_RESULT(result))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Remote command [%s] result is not double", command);
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid result. Double is expected."));
		return SYSINFO_RET_FAIL;
	}

	ZBX_UNSET_RESULT_EXCLUDING(result, AR_DOUBLE);

	return SYSINFO_RET_OK;
}

int	execute_int(const char *command, AGENT_RESULT *result, int timeout)
{
	if (SYSINFO_RET_OK != execute_str(command, result, timeout))
		return SYSINFO_RET_FAIL;

	if (NULL == ZBX_GET_UI64_RESULT(result))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Remote command [%s] result is not unsigned integer", command);
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid result. Unsigned integer is expected."));
		return SYSINFO_RET_FAIL;
	}

	ZBX_UNSET_RESULT_EXCLUDING(result, AR_UINT64);

	return SYSINFO_RET_OK;
}

static int	system_run_local(AGENT_REQUEST *request, AGENT_RESULT *result, int level)
{
	char	*command, *flag;

	if (2 < request->nparam)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Too many parameters."));
		return SYSINFO_RET_FAIL;
	}

	command = get_rparam(request, 0);
	flag = get_rparam(request, 1);

	if (NULL == command || '\0' == *command)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid first parameter."));
		return SYSINFO_RET_FAIL;
	}

	zabbix_log(level, "Executing command '%s'", command);

	if (NULL == flag || '\0' == *flag || 0 == strcmp(flag, "wait"))	/* default parameter */
	{
		return execute_str(command, result, request->timeout);
	}
	else if (0 == strcmp(flag, "nowait"))
	{
		if (SUCCEED != zbx_execute_nowait(command))
		{
			SET_MSG_RESULT(result, zbx_strdup(NULL, "Cannot execute command."));
			return SYSINFO_RET_FAIL;
		}
	}
	else
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "Invalid second parameter."));
		return SYSINFO_RET_FAIL;
	}

	SET_UI64_RESULT(result, 1);

	return SYSINFO_RET_OK;
}

static int	system_run(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	int	level = LOG_LEVEL_DEBUG;

	if (0 != sysinfo_get_config_log_remote_commands())
		level = LOG_LEVEL_WARNING;

	return system_run_local(request, result, level);
}

static int	system_run_no_remote(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	return system_run_local(request, result, LOG_LEVEL_DEBUG);
}
