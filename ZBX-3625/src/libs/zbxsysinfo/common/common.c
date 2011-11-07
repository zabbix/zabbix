/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
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

static int	AGENT_PING(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
static int	AGENT_VERSION(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
static int	ONLY_ACTIVE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
static int	SYSTEM_RUN(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);

ZBX_METRIC	parameters_common[] =
/*      KEY                     FLAG		FUNCTION        ADD_PARAM       TEST_PARAM */
{
	{"agent.ping",		0,		AGENT_PING, 		0,	0},
	{"agent.version",	0,		AGENT_VERSION,		0,	0},

	{"system.localtime",	0,		SYSTEM_LOCALTIME,	0,	0},
	{"system.run",		CF_USEUPARAM,	SYSTEM_RUN,	 	0,	"echo test"},

	{"web.page.get",	CF_USEUPARAM,	WEB_PAGE_GET,	 	0,	"localhost,,80"},
	{"web.page.perf",	CF_USEUPARAM,	WEB_PAGE_PERF,	 	0,	"localhost,,80"},
	{"web.page.regexp",	CF_USEUPARAM,	WEB_PAGE_REGEXP,	0,	"localhost,,80,OK"},

	{"vfs.file.exists",	CF_USEUPARAM,	VFS_FILE_EXISTS,	0,	VFS_TEST_FILE},
	{"vfs.file.time",	CF_USEUPARAM,	VFS_FILE_TIME,		0,	VFS_TEST_FILE ",modify"},
	{"vfs.file.size",	CF_USEUPARAM,	VFS_FILE_SIZE, 		0,	VFS_TEST_FILE},
	{"vfs.file.regexp",	CF_USEUPARAM,	VFS_FILE_REGEXP,	0,	VFS_TEST_FILE "," VFS_TEST_REGEXP},
	{"vfs.file.regmatch",	CF_USEUPARAM,	VFS_FILE_REGMATCH, 	0,	VFS_TEST_FILE "," VFS_TEST_REGEXP},
	{"vfs.file.cksum",	CF_USEUPARAM,	VFS_FILE_CKSUM,		0,	VFS_TEST_FILE},
	{"vfs.file.md5sum",	CF_USEUPARAM,	VFS_FILE_MD5SUM,	0,	VFS_TEST_FILE},

	{"net.tcp.dns",		CF_USEUPARAM,	NET_TCP_DNS,		0,	",zabbix.com"},
	{"net.tcp.dns.query",	CF_USEUPARAM,	NET_TCP_DNS_QUERY,	0,	",zabbix.com"},
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

int	getPROC(char *file, int lineno, int fieldno, unsigned flags, AGENT_RESULT *result)
{
#ifdef	HAVE_PROC
	FILE	*f;
	char	*t, c[MAX_STRING_LEN];
	int	i;
	double	value = 0;

	if (NULL == (f = fopen(file,"r")))
		return SYSINFO_RET_FAIL;

	for(i=1; i<=lineno; i++)
	{
		if(NULL == fgets(c,MAX_STRING_LEN,f))
		{
			zbx_fclose(f);
			return SYSINFO_RET_FAIL;
		}
	}

	t=(char *)strtok(c," ");
	for(i=2; i<=fieldno; i++)
	{
		t=(char *)strtok(NULL," ");
	}

	zbx_fclose(f);

	sscanf(t, "%lf", &value);
	SET_DBL_RESULT(result, value);

	return SYSINFO_RET_OK;
#else
	return SYSINFO_RET_FAIL;
#endif /* HAVE_PROC */
}

static int	AGENT_PING(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	SET_UI64_RESULT(result, 1);

	return SYSINFO_RET_OK;
}

static int	AGENT_VERSION(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
	SET_STR_RESULT(result, zbx_strdup(NULL, ZABBIX_VERSION));

	return SYSINFO_RET_OK;
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
	char	command[MAX_STRING_LEN], flag[9];

	if (1 != CONFIG_ENABLE_REMOTE_COMMANDS && 0 == (flags & PROCESS_LOCAL_COMMAND))
		return SYSINFO_RET_FAIL;

	if (2 < num_param(param))
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, command, sizeof(command)))
		return SYSINFO_RET_FAIL;

	if ('\0' == *command)
		return SYSINFO_RET_FAIL;

	if (1 == CONFIG_LOG_REMOTE_COMMANDS)
		zabbix_log(LOG_LEVEL_WARNING, "Executing command '%s'", command);
	else
		zabbix_log(LOG_LEVEL_DEBUG, "Executing command '%s'", command);

	if (0 != get_param(param, 2, flag, sizeof(flag)))
		*flag = '\0';

	if ('\0' == *flag || 0 == strcmp(flag, "wait"))	/* default parameter */
		return EXECUTE_STR(cmd, command, flags, result);
	else if (0 != strcmp(flag, "nowait") || SUCCEED != zbx_execute_nowait(command))
		return SYSINFO_RET_FAIL;

	SET_UI64_RESULT(result, 1);

	return SYSINFO_RET_OK;
}
