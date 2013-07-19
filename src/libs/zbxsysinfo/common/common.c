/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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

static int	ONLY_ACTIVE(AGENT_REQUEST *request, AGENT_RESULT *result);
static int	SYSTEM_RUN(AGENT_REQUEST *request, AGENT_RESULT *result);

ZBX_METRIC	parameters_common[] =
/*      KEY                     FLAG		FUNCTION        	TEST PARAMETERS */
{
	{"system.localtime",	CF_HAVEPARAMS,	SYSTEM_LOCALTIME,	"utc"},
	{"system.run",		CF_HAVEPARAMS,	SYSTEM_RUN,	 	"echo test"},

	{"web.page.get",	CF_HAVEPARAMS,	WEB_PAGE_GET,	 	"localhost,,80"},
	{"web.page.perf",	CF_HAVEPARAMS,	WEB_PAGE_PERF,	 	"localhost,,80"},
	{"web.page.regexp",	CF_HAVEPARAMS,	WEB_PAGE_REGEXP,	"localhost,,80,OK"},

	{"vfs.file.size",	CF_HAVEPARAMS,	VFS_FILE_SIZE, 		VFS_TEST_FILE},
	{"vfs.file.time",	CF_HAVEPARAMS,	VFS_FILE_TIME,		VFS_TEST_FILE ",modify"},
	{"vfs.file.exists",	CF_HAVEPARAMS,	VFS_FILE_EXISTS,	VFS_TEST_FILE},
	{"vfs.file.contents",	CF_HAVEPARAMS,	VFS_FILE_CONTENTS,	VFS_TEST_FILE},
	{"vfs.file.regexp",	CF_HAVEPARAMS,	VFS_FILE_REGEXP,	VFS_TEST_FILE "," VFS_TEST_REGEXP},
	{"vfs.file.regmatch",	CF_HAVEPARAMS,	VFS_FILE_REGMATCH, 	VFS_TEST_FILE "," VFS_TEST_REGEXP},
	{"vfs.file.md5sum",	CF_HAVEPARAMS,	VFS_FILE_MD5SUM,	VFS_TEST_FILE},
	{"vfs.file.cksum",	CF_HAVEPARAMS,	VFS_FILE_CKSUM,		VFS_TEST_FILE},

	{"net.dns",		CF_HAVEPARAMS,	NET_DNS,		",zabbix.com"},
	{"net.dns.record",	CF_HAVEPARAMS,	NET_DNS_RECORD,		",zabbix.com"},
	{"net.tcp.dns",		CF_HAVEPARAMS,	NET_DNS,		",zabbix.com"}, /* deprecated */
	{"net.tcp.dns.query",	CF_HAVEPARAMS,	NET_DNS_RECORD,		",zabbix.com"}, /* deprecated */
	{"net.tcp.port",	CF_HAVEPARAMS,	NET_TCP_PORT,		",80"},

	{"system.users.num",	0,		SYSTEM_USERS_NUM,	NULL},

	{"log",			CF_HAVEPARAMS,	ONLY_ACTIVE, 		"logfile"},
	{"logrt",		CF_HAVEPARAMS,	ONLY_ACTIVE,		"logfile"},
	{"eventlog",		CF_HAVEPARAMS,	ONLY_ACTIVE, 		"system"},

	{NULL}
};

static int	ONLY_ACTIVE(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	SET_MSG_RESULT(result, zbx_strdup(NULL, "Accessible only as active check!"));

	return SYSINFO_RET_FAIL;
}

int	EXECUTE_USER_PARAMETER(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	int	ret;
	char	*command;

	if (1 != request->nparam)
		return SYSINFO_RET_FAIL;

	command = get_rparam(request, 0);

	ret = EXECUTE_STR(command, result);

	if (SYSINFO_RET_FAIL == ret && 0 == result->type)
	{
		/* only whitespace */

		SET_TEXT_RESULT(result, zbx_strdup(NULL, ""));
		ret = SYSINFO_RET_OK;
	}

	return ret;
}

int	EXECUTE_STR(const char *command, AGENT_RESULT *result)
{
	int	ret = SYSINFO_RET_FAIL;
	char	*cmd_result = NULL, error[MAX_STRING_LEN];

	assert(result);

	init_result(result);

	if (SUCCEED != zbx_execute(command, &cmd_result, error, sizeof(error), CONFIG_TIMEOUT))
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, error));
		goto lbl_exit;
	}

	zbx_rtrim(cmd_result, ZBX_WHITESPACE);

	zabbix_log(LOG_LEVEL_DEBUG, "Run remote command [%s] Result [%d] [%.20s]...",
			command, strlen(cmd_result), cmd_result);

	if ('\0' == *cmd_result)	/* we got whitespace only */
		goto lbl_exit;

	SET_TEXT_RESULT(result, zbx_strdup(NULL, cmd_result));

	ret = SYSINFO_RET_OK;
lbl_exit:
	zbx_free(cmd_result);

	return ret;
}

int	EXECUTE_DBL(const char *command, AGENT_RESULT *result)
{
	if (SYSINFO_RET_OK != EXECUTE_STR(command, result))
		return SYSINFO_RET_FAIL;

	if (NULL == GET_DBL_RESULT(result))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Remote command [%s] result is not double", command);
		return SYSINFO_RET_FAIL;
	}

	UNSET_RESULT_EXCLUDING(result, AR_DOUBLE);

	return SYSINFO_RET_OK;
}

int	EXECUTE_INT(const char *command, AGENT_RESULT *result)
{
	if (SYSINFO_RET_OK != EXECUTE_STR(command, result))
		return SYSINFO_RET_FAIL;

	if (NULL == GET_UI64_RESULT(result))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Remote command [%s] result is not unsigned integer", command);
		return SYSINFO_RET_FAIL;
	}

	UNSET_RESULT_EXCLUDING(result, AR_UINT64);

	return SYSINFO_RET_OK;
}

static int	SYSTEM_RUN(AGENT_REQUEST *request, AGENT_RESULT *result)
{
	char	*command, *flag;

	if (2 < request->nparam)
		return SYSINFO_RET_FAIL;

	command = get_rparam(request, 0);
	flag = get_rparam(request, 1);

	if (NULL == command || '\0' == *command)
		return SYSINFO_RET_FAIL;

	if (1 == CONFIG_LOG_REMOTE_COMMANDS)
		zabbix_log(LOG_LEVEL_WARNING, "Executing command '%s'", command);
	else
		zabbix_log(LOG_LEVEL_DEBUG, "Executing command '%s'", command);

	if (NULL == flag || '\0' == *flag || 0 == strcmp(flag, "wait"))	/* default parameter */
		return EXECUTE_STR(command, result);
	else if (0 != strcmp(flag, "nowait") || SUCCEED != zbx_execute_nowait(command))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, 1);

	return SYSINFO_RET_OK;
}
