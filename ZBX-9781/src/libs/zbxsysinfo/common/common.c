/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

#include "common.h"
#include "sysinfo.h"

#include "log.h"

#include "file.h"
#include "http.h"
#include "net.h"
#include "system.h"
#include "zbxexec.h"

#if !defined(_WINDOWS)
#	define VFS_TEST_FILE "/etc/passwd"
#	define VFS_TEST_REGEXP "root"
#else
#	define VFS_TEST_FILE "c:\\windows\\win.ini"
#	define VFS_TEST_REGEXP "fonts"
#endif

extern int	CONFIG_TIMEOUT;

static int	ONLY_ACTIVE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
static int	SYSTEM_RUN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);

ZBX_METRIC	parameters_common[] =
/*      KEY                     FLAG		FUNCTION        ADD_PARAM       TEST_PARAM */
{
	{"system.localtime",	CF_USEUPARAM,	SYSTEM_LOCALTIME,	0,	"utc"},
	{"system.run",		CF_USEUPARAM,	SYSTEM_RUN,	 	0,	"echo test"},

	{"web.page.get",	CF_USEUPARAM,	WEB_PAGE_GET,	 	0,	"localhost,,80"},
	{"web.page.perf",	CF_USEUPARAM,	WEB_PAGE_PERF,	 	0,	"localhost,,80"},
	{"web.page.regexp",	CF_USEUPARAM,	WEB_PAGE_REGEXP,	0,	"localhost,,80,OK"},

	{"vfs.file.size",	CF_USEUPARAM,	VFS_FILE_SIZE, 		0,	VFS_TEST_FILE},
	{"vfs.file.time",	CF_USEUPARAM,	VFS_FILE_TIME,		0,	VFS_TEST_FILE ",modify"},
	{"vfs.file.exists",	CF_USEUPARAM,	VFS_FILE_EXISTS,	0,	VFS_TEST_FILE},
	{"vfs.file.contents",	CF_USEUPARAM,	VFS_FILE_CONTENTS,	0,	VFS_TEST_FILE},
	{"vfs.file.regexp",	CF_USEUPARAM,	VFS_FILE_REGEXP,	0,	VFS_TEST_FILE "," VFS_TEST_REGEXP},
	{"vfs.file.regmatch",	CF_USEUPARAM,	VFS_FILE_REGMATCH, 	0,	VFS_TEST_FILE "," VFS_TEST_REGEXP},
	{"vfs.file.md5sum",	CF_USEUPARAM,	VFS_FILE_MD5SUM,	0,	VFS_TEST_FILE},
	{"vfs.file.cksum",	CF_USEUPARAM,	VFS_FILE_CKSUM,		0,	VFS_TEST_FILE},

	{"net.dns",		CF_USEUPARAM,	NET_DNS,		0,	",zabbix.com"},
	{"net.dns.record",	CF_USEUPARAM,	NET_DNS_RECORD,		0,	",zabbix.com"},
	{"net.tcp.dns",		CF_USEUPARAM,	NET_DNS,		0,	",zabbix.com"}, /* deprecated */
	{"net.tcp.dns.query",	CF_USEUPARAM,	NET_DNS_RECORD,		0,	",zabbix.com"}, /* deprecated */
	{"net.tcp.port",	CF_USEUPARAM,	NET_TCP_PORT,		0,	",80"},

	{"system.hostname",	CF_USEUPARAM,	SYSTEM_HOSTNAME,	0,	0},
	{"system.uname",	0,		SYSTEM_UNAME,		0,	0},

	{"system.users.num",	0,		SYSTEM_USERS_NUM,	0,	0},

	{"log",			CF_USEUPARAM,	ONLY_ACTIVE, 		0,	"logfile"},
	{"logrt",		CF_USEUPARAM,	ONLY_ACTIVE,		0,	"logfile"},
	{"eventlog",		CF_USEUPARAM,	ONLY_ACTIVE, 		0,	"system"},

	{0}
};

static int	ONLY_ACTIVE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	SET_MSG_RESULT(result, zbx_strdup(NULL, "Accessible only as active check!"));

	return SYSINFO_RET_FAIL;
}

int	EXECUTE_USER_PARAMETER(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	int	ret;

	ret = EXECUTE_STR(cmd, param, flags, result);

	if (SYSINFO_RET_FAIL == ret && 0 == result->type)
	{
		/* only whitespace */

		SET_TEXT_RESULT(result, zbx_strdup(NULL, ""));
		ret = SYSINFO_RET_OK;
	}

	return ret;
}

int	EXECUTE_STR(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	int	ret = SYSINFO_RET_FAIL;
	char	*cmd_result = NULL, error[MAX_STRING_LEN];

	assert(result);

	init_result(result);

	if (SUCCEED != zbx_execute(param, &cmd_result, error, sizeof(error), CONFIG_TIMEOUT))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, error));
		goto lbl_exit;
	}

	zbx_rtrim(cmd_result, ZBX_WHITESPACE);

	zabbix_log(LOG_LEVEL_DEBUG, "Run remote command [%s] Result [%d] [%.20s]...",
			param, strlen(cmd_result), cmd_result);

	if ('\0' == *cmd_result)	/* we got whitespace only */
		goto lbl_exit;

	SET_TEXT_RESULT(result, zbx_strdup(NULL, cmd_result));

	ret = SYSINFO_RET_OK;
lbl_exit:
	zbx_free(cmd_result);

	return ret;
}

int	EXECUTE_DBL(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	if (SYSINFO_RET_OK != EXECUTE_STR(cmd, param, flags, result))
		return SYSINFO_RET_FAIL;

	if (NULL == GET_DBL_RESULT(result))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Remote command [%s] result is not double", param);
		return SYSINFO_RET_FAIL;
	}

	UNSET_RESULT_EXCLUDING(result, AR_DOUBLE);

	return SYSINFO_RET_OK;
}

int	EXECUTE_INT(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	if (SYSINFO_RET_OK != EXECUTE_STR(cmd, param, flags, result))
		return SYSINFO_RET_FAIL;

	if (NULL == GET_UI64_RESULT(result))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Remote command [%s] result is not unsigned integer", param);
		return SYSINFO_RET_FAIL;
	}

	UNSET_RESULT_EXCLUDING(result, AR_UINT64);

	return SYSINFO_RET_OK;
}

static int	SYSTEM_RUN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	char	*command = NULL, flag[9];
	int	ret = SYSINFO_RET_FAIL;

	if (1 != CONFIG_ENABLE_REMOTE_COMMANDS && 0 == (flags & PROCESS_LOCAL_COMMAND))
		goto out;

	if (2 < num_param(param))
		goto out;

	if (NULL == (command = get_param_dyn(param, 1)))
		goto out;

	if ('\0' == *command)
		goto out;

	if (1 == CONFIG_LOG_REMOTE_COMMANDS)
		zabbix_log(LOG_LEVEL_WARNING, "Executing command '%s'", command);
	else
		zabbix_log(LOG_LEVEL_DEBUG, "Executing command '%s'", command);

	if (0 != get_param(param, 2, flag, sizeof(flag)))
		*flag = '\0';

	if ('\0' == *flag || 0 == strcmp(flag, "wait"))	/* default parameter */
	{
		ret = EXECUTE_STR(cmd, command, flags, result);
		goto out;
	}
	else if (0 != strcmp(flag, "nowait") || SUCCEED != zbx_execute_nowait(command))
		goto out;

	SET_UI64_RESULT(result, 1);

	ret = SYSINFO_RET_OK;
out:
	zbx_free(command);

	return ret;
}
