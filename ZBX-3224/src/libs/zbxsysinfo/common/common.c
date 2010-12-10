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

#if !defined(_WINDOWS)
#	define VFS_TEST_FILE "/etc/passwd"
#	define VFS_TEST_REGEXP "root"
#else
#	define VFS_TEST_FILE "c:\\windows\\win.ini"
#	define VFS_TEST_REGEXP "fonts"
#endif /* _WINDOWS */

static int	AGENT_PING(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
static int	AGENT_VERSION(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);
static int	ONLY_ACTIVE(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result);

ZBX_METRIC	parameters_common[] =
/*      KEY                     FLAG		FUNCTION        ADD_PARAM       TEST_PARAM */
{
	{"agent.ping",		0,		AGENT_PING, 		0,	0},
	{"agent.version",	0,		AGENT_VERSION,		0,	0},

	{"system.localtime",	0,		SYSTEM_LOCALTIME,	0,	0},
	{"system.run",		CF_USEUPARAM,	RUN_COMMAND,	 	0,	"echo test"},

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
	SET_MSG_RESULT(result, strdup("Accessible only as active check!"));

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
		return	SYSINFO_RET_FAIL;
	}

	for(i=1; i<=lineno; i++)
	{
		if(NULL == fgets(c,MAX_STRING_LEN,f))
		{
			zbx_fclose(f);
			return	SYSINFO_RET_FAIL;
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
	SET_STR_RESULT(result, strdup(ZABBIX_VERSION));

	return SYSINFO_RET_OK;
}

int	EXECUTE_STR(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{

#if defined(_WINDOWS)

	STARTUPINFO si = {0};
	PROCESS_INFORMATION pi = {0};
	SECURITY_ATTRIBUTES sa;
	HANDLE hWrite=NULL, hRead=NULL;
	LPTSTR	wcommand;

#else /* not _WINDOWS */

	FILE	*hRead = NULL;

#endif /* _WINDOWS */

	int	ret = SYSINFO_RET_FAIL;

	char	stat_buf[128];
	char	*cmd_result=NULL;
	char	*command=NULL;
	int	len;

	assert(result);

	init_result(result);

	cmd_result = zbx_dsprintf(cmd_result,"");
	memset(stat_buf, 0, sizeof(stat_buf));

#if defined(_WINDOWS)

	/* Set the bInheritHandle flag so pipe handles are inherited */
	sa.nLength = sizeof(SECURITY_ATTRIBUTES);
	sa.bInheritHandle = TRUE;
	sa.lpSecurityDescriptor = NULL;

	/* Create a pipe for the child process's STDOUT */
	if (! CreatePipe(&hRead, &hWrite, &sa, sizeof(cmd_result)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Unable to create pipe [%s]", strerror_from_system(GetLastError()));
		ret = SYSINFO_RET_FAIL;
		goto lbl_exit;
	}

	/* Fill in process startup info structure */
	memset(&si,0,sizeof(STARTUPINFO));
	si.cb		= sizeof(STARTUPINFO);
	si.dwFlags	= STARTF_USESTDHANDLES;
	si.hStdInput	= GetStdHandle(STD_INPUT_HANDLE);
	si.hStdOutput	= hWrite;
	si.hStdError	= hWrite;

	command = zbx_dsprintf(command, "cmd /C \"%s\"", param);

	wcommand = zbx_utf8_to_unicode(command);

	/* Create new process */
	if (!CreateProcess(NULL,wcommand,NULL,NULL,TRUE,0,NULL,NULL,&si,&pi))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Unable to create process: '%s' [%s]", command, strerror_from_system(GetLastError()));

		ret = SYSINFO_RET_FAIL;
		zbx_free(wcommand);
		goto lbl_exit;
	}
	zbx_free(wcommand);
	CloseHandle(hWrite);	hWrite = NULL;

	/* Read process output */
	while( ReadFile(hRead, stat_buf, sizeof(stat_buf)-1, &len, NULL) && len > 0 )
	{
		cmd_result = zbx_strdcat(cmd_result, stat_buf);
		memset(stat_buf, 0, sizeof(stat_buf));
	}

	/* Don't wait child process exiting. */
	/* WaitForSingleObject( pi.hProcess, INFINITE ); */

	/* Terminate child process */
	/* TerminateProcess(pi.hProcess, 0); */

	CloseHandle(pi.hProcess);
	CloseHandle(pi.hThread);

	CloseHandle(hRead);	hRead = NULL;


#else /* not _WINDOWS */
	command = zbx_dsprintf(command, "%s", param);

	if(0 == (hRead = popen(command,"r")))
	{
		switch (errno)
		{
			case	EINTR:
				ret = SYSINFO_RET_TIMEOUT;
				break;
			default:
				ret = SYSINFO_RET_FAIL;
				break;
		}
		goto lbl_exit;
	}

	;
	/* Read process output */
	while( (len = fread(stat_buf, 1, sizeof(stat_buf)-1, hRead)) > 0 )
	{
		cmd_result = zbx_strdcat(cmd_result, stat_buf);
		memset(stat_buf, 0, sizeof(stat_buf));
	}

	if(0 != ferror(hRead))
	{
		switch (errno)
		{
			case	EINTR:
				ret = SYSINFO_RET_TIMEOUT;
				break;
			default:
				ret = SYSINFO_RET_FAIL;
				break;
		}
		goto lbl_exit;
	}

	if(pclose(hRead) == -1)
	{
		switch (errno)
		{
			case	EINTR:
				ret = SYSINFO_RET_TIMEOUT;
				break;
			default:
				ret = SYSINFO_RET_FAIL;
				break;
		}
		goto lbl_exit;
	}

	hRead = NULL;

#endif /* _WINDOWS */

	zabbix_log(LOG_LEVEL_DEBUG, "Before");

	zbx_rtrim(cmd_result, "\r\n");

	/* We got EOL only */
	if(cmd_result[0] == '\0')
	{
		ret = SYSINFO_RET_FAIL;
		goto lbl_exit;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "Run remote command [%s] Result [%d] [%.20s]...", command, strlen(cmd_result), cmd_result);

	SET_TEXT_RESULT(result, strdup(cmd_result));

	ret = SYSINFO_RET_OK;

lbl_exit:

#if defined(_WINDOWS)
	if ( hWrite )	{ CloseHandle(hWrite);	hWrite = NULL; }
	if ( hRead)	{ CloseHandle(hRead);	hRead = NULL; }
#else /* not _WINDOWS */
	if ( hRead )	{ pclose(hRead);	hRead = NULL; }
#endif /* _WINDOWS */

	zbx_free(command)
	zbx_free(cmd_result);

	return ret;
}

int	EXECUTE_INT(const char *cmd, const char *command, unsigned flags, AGENT_RESULT *result)
{
	int	ret	= SYSINFO_RET_FAIL;

	ret = EXECUTE_STR(cmd,command,flags,result);

	if(SYSINFO_RET_OK == ret)
	{
		if( NULL == GET_DBL_RESULT(result) )
		{
			zabbix_log(LOG_LEVEL_WARNING, "Remote command [%s] result is not double", command);
			ret = SYSINFO_RET_FAIL;
		}
		UNSET_RESULT_EXCLUDING(result, AR_DOUBLE);
	}

	return ret;
}

int	RUN_COMMAND(const char *cmd, const char *param, unsigned flags, AGENT_RESULT *result)
{
#define MAX_FLAG_LEN 10

	char	command[MAX_STRING_LEN];
	char	flag[MAX_FLAG_LEN];

#if defined (_WINDOWS)
	STARTUPINFO    si;
	PROCESS_INFORMATION  pi;

	char	full_command[MAX_STRING_LEN];
	LPTSTR	wcommand;
	int	ret = SYSINFO_RET_FAIL;
#else /* not _WINDOWS */
	pid_t	pid;
#endif

	if (CONFIG_ENABLE_REMOTE_COMMANDS != 1)
	{
		SET_MSG_RESULT(result, strdup("ZBX_NOTSUPPORTED"));
		return SYSINFO_RET_FAIL;
	}

	if (num_param(param) > 2)
		return SYSINFO_RET_FAIL;

	if (0 != get_param(param, 1, command, sizeof(command)))
		return SYSINFO_RET_FAIL;

	if (*command == '\0')
		return SYSINFO_RET_FAIL;

	if (CONFIG_LOG_REMOTE_COMMANDS == 1)
		zabbix_log(LOG_LEVEL_WARNING, "Executing command '%s'", command);
	else
		zabbix_log(LOG_LEVEL_DEBUG, "Executing command '%s'", command);

	if (0 != get_param(param, 2, flag, sizeof(flag)))
		*flag = '\0';

	if (*flag == '\0')
		zbx_snprintf(flag, sizeof(flag), "wait");

	if (0 == strcmp(flag, "wait"))
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
		&si,	/* Startup Information */
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
