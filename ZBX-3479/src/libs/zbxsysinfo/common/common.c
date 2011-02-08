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
#include "threads.h"

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
#endif /* _WINDOWS */

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

	{"net.tcp.dns",		CF_USEUPARAM,	NET_TCP_DNS,		0,	",localhost"},
	{"net.tcp.dns.query",	CF_USEUPARAM,	NET_TCP_DNS_QUERY,	0,	",localhost"},
	{"net.tcp.port",	CF_USEUPARAM,	NET_TCP_PORT,		0,	",80"},

	{"system.hostname",	0,		SYSTEM_HOSTNAME,	0,	0},
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
	char	*t;
	char	c[MAX_STRING_LEN];
	int	i;
	double	value = 0;

	if(NULL == (f = fopen(file,"r")))
	{
		return SYSINFO_RET_FAIL;
	}

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

	switch (zbx_execute(param, &cmd_result, error, sizeof(error), CONFIG_TIMEOUT))
	{
		case SUCCEED:
			ret = SYSINFO_RET_OK;
			break;
		default:
			SET_MSG_RESULT(result, zbx_strdup(NULL, error));
			ret = SYSINFO_RET_FAIL;
	}

	if (SYSINFO_RET_OK == ret)
	{
		zbx_rtrim(cmd_result, ZBX_WHITESPACE);

		/* we got whitespace only */
		if ('\0' == *cmd_result)
		{
			ret = SYSINFO_RET_FAIL;
			goto lbl_exit;
		}
	}
	else
		goto lbl_exit;

	zabbix_log(LOG_LEVEL_DEBUG, "Run remote command [%s] Result [%d] [%.20s]...",
				param, strlen(cmd_result), cmd_result);

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

#if defined (_WINDOWS)
	STARTUPINFO		si;
	PROCESS_INFORMATION	pi;
	char			full_command[MAX_STRING_LEN];
	LPTSTR			wcommand;
	int			ret = SYSINFO_RET_FAIL;
#else /* not _WINDOWS */
	pid_t			pid;
#endif

	if (1 != CONFIG_ENABLE_REMOTE_COMMANDS)
	{
		SET_MSG_RESULT(result, zbx_strdup(NULL, "ZBX_NOTSUPPORTED"));
		return SYSINFO_RET_FAIL;
	}

	if (num_param(param) > 2)
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
	else if (0 != strcmp(flag, "nowait"))
		return SYSINFO_RET_FAIL;

#if defined(_WINDOWS)

	zbx_snprintf(full_command, sizeof(full_command), "cmd /C \"%s\"", command);

	GetStartupInfo(&si);

	zabbix_log(LOG_LEVEL_DEBUG, "Executing full command '%s'", full_command);

	wcommand = zbx_utf8_to_unicode(full_command);

	if (!CreateProcess(
		NULL,	/* No module name (use command line) */
		wcommand,/* Name of app to launch */
		NULL,	/* Default process security attributes */
		NULL,	/* Default thread security attributes */
		FALSE,	/* Don't inherit handles from the parent */
		0,	/* Normal priority */
		NULL,	/* Use the same environment as the parent */
		NULL,	/* Launch in the current directory */
		&si,	/* Startup information */
		&pi))	/* Process information stored upon return */
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Creation of the process failed");
		goto lbl_exit;
	}

	ret = SYSINFO_RET_OK;

	SET_UI64_RESULT(result, 1);
lbl_exit:
	zbx_free(wcommand);

	return ret;

#else /* not _WINDOWS */

	pid = zbx_fork(); /* run new thread 1 */
	switch(pid)
	{
	case -1:
		zabbix_log(LOG_LEVEL_WARNING, "fork failed for command '%s'",command);
		return SYSINFO_RET_FAIL;
	case 0:
		pid = zbx_fork(); /* run new thread 2 to replace by command */
		switch(pid)
		{
		case -1:
			zabbix_log(LOG_LEVEL_WARNING, "fork2 failed for '%s'",command);
			return SYSINFO_RET_FAIL;
		case 0:
			/*
			 * DON'T REMOVE SLEEP
			 * sleep needed to return server result as "1"
			 * then we can run "execl"
			 * otherwise command print result into socket with STDOUT id
			 */
			sleep(3);
			/**/

			/* replace thread 2 by the execution of command */
			if(execl("/bin/sh", "sh", "-c", command, (char *)0))
			{
				zabbix_log(LOG_LEVEL_WARNING, "execl failed for command '%s'",command);
			}
			/* In normal case the program will never reach this point */
			exit(0);
		default:
			waitpid(pid, NULL, WNOHANG); /* NO WAIT can be used for thread 2 closing */
			exit(0); /* close thread 1 and transmit thread 2 to system (solve zombie state) */
			break;
		}
	default:
		waitpid(pid, NULL, 0); /* wait thread 1 closing */
		break;
	}

	SET_UI64_RESULT(result, 1);

	return SYSINFO_RET_OK;

#endif /* _WINDOWS */
}
