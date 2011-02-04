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
#include "zbxconf.h"

#include "cfg.h"
#include "log.h"
#include "alias.h"
#include "sysinfo.h"
#include "perfstat.h"

#if defined(ZABBIX_DAEMON)
/* use pid file configuration */
#	include "daemon.h"
#endif	/* ZABBIX_DAEMON */

#if defined(WITH_PLUGINS)
/* use Zabbix plugins configurations */
#	include "zbxplugin.h"
#endif	/* WITH_PLUGINS */

char	*CONFIG_HOSTS_ALLOWED		= NULL;
char	*CONFIG_HOSTNAME		= NULL;

int	CONFIG_DISABLE_ACTIVE		= 0;
int	CONFIG_DISABLE_PASSIVE		= 0;
int	CONFIG_ENABLE_REMOTE_COMMANDS	= 0;
int	CONFIG_LOG_REMOTE_COMMANDS	= 0;
int	CONFIG_UNSAFE_USER_PARAMETERS	= 0;
int	CONFIG_LISTEN_PORT		= 10050;
int	CONFIG_SERVER_PORT		= 10051;
int	CONFIG_REFRESH_ACTIVE_CHECKS	= 120;
char	*CONFIG_LISTEN_IP		= NULL;
char	*CONFIG_SOURCE_IP		= NULL;
int	CONFIG_LOG_LEVEL		= LOG_LEVEL_WARNING;

int	CONFIG_BUFFER_SIZE		= 100;
int	CONFIG_BUFFER_SEND		= 5;

int	CONFIG_MAX_LINES_PER_SECOND	= 100;

void	load_config()
{
	AGENT_RESULT	result;
	char		**value = NULL;

	struct cfg_line	cfg[] =
	{
		/* PARAMETER,		VAR,				FUNC,
			TYPE,		MANDATORY,	MIN,		MAX */
		{"Server",		&CONFIG_HOSTS_ALLOWED,		NULL,
			TYPE_STRING,	PARM_MAND,	0,		0},
		{"Hostname",		&CONFIG_HOSTNAME,		NULL,
			TYPE_STRING,	PARM_OPT,	0,		0},
		{"BufferSize",		&CONFIG_BUFFER_SIZE,		NULL,
			TYPE_INT,	PARM_OPT,	2,		65535},
		{"BufferSend",		&CONFIG_BUFFER_SEND,		NULL,
			TYPE_INT,	PARM_OPT,	1,		SEC_PER_HOUR},
#ifdef USE_PID_FILE
		{"PidFile",		&CONFIG_PID_FILE,		NULL,
			TYPE_STRING,	PARM_OPT,	0,		0},
#endif	/* USE_PID_FILE */
		{"LogFile",		&CONFIG_LOG_FILE,		NULL,
			TYPE_STRING,	PARM_OPT,	0,		0},
		{"LogFileSize",		&CONFIG_LOG_FILE_SIZE,		NULL,
			TYPE_INT,	PARM_OPT,	0,		1024},
		{"DisableActive",	&CONFIG_DISABLE_ACTIVE,		NULL,
			TYPE_INT,	PARM_OPT,	0,		1},
		{"DisablePassive",	&CONFIG_DISABLE_PASSIVE,	NULL,
			TYPE_INT,	PARM_OPT,	0,		1},
		{"Timeout",		&CONFIG_TIMEOUT,		NULL,
			TYPE_INT,	PARM_OPT,	1,		30},
		{"ListenPort",		&CONFIG_LISTEN_PORT,		NULL,
			TYPE_INT,	PARM_OPT,	1024,		32767},
		{"ServerPort",		&CONFIG_SERVER_PORT,		NULL,
			TYPE_INT,	PARM_OPT,	1024,		32767},
		{"ListenIP",		&CONFIG_LISTEN_IP,		NULL,
			TYPE_STRING,	PARM_OPT,	0,		0},
		{"SourceIP",		&CONFIG_SOURCE_IP,		NULL,
			TYPE_STRING,	PARM_OPT,	0,		0},
		{"DebugLevel",		&CONFIG_LOG_LEVEL,		NULL,
			TYPE_INT,	PARM_OPT,	0,		4},
		{"StartAgents",		&CONFIG_ZABBIX_FORKS,		NULL,
			TYPE_INT,	PARM_OPT,	1,		16},
		{"RefreshActiveChecks",	&CONFIG_REFRESH_ACTIVE_CHECKS,	NULL,
			TYPE_INT,	PARM_OPT,	SEC_PER_MIN,	SEC_PER_HOUR},
		{"MaxLinesPerSecond",	&CONFIG_MAX_LINES_PER_SECOND,	NULL,
			TYPE_INT,	PARM_OPT,	1,		1000},
		{"AllowRoot",		&CONFIG_ALLOW_ROOT,		NULL,
			TYPE_INT,	PARM_OPT,	0,		1},
		{NULL}
	};

	memset(&result, 0, sizeof(AGENT_RESULT));

	parse_cfg_file(CONFIG_FILE, cfg);

#ifdef USE_PID_FILE
	if (NULL == CONFIG_PID_FILE)
	{
		CONFIG_PID_FILE = "/tmp/zabbix_agentd.pid";
	}
#endif	/* USE_PID_FILE */

	if (NULL == CONFIG_HOSTNAME || '\0'== *CONFIG_HOSTNAME)
	{
		if (NULL != CONFIG_HOSTNAME)
			zbx_free(CONFIG_HOSTNAME);

		if (SUCCEED == process("system.hostname", 0, &result))
		{
			if (NULL != (value = GET_STR_RESULT(&result)))
			{
				CONFIG_HOSTNAME = strdup(*value);
				if (strlen(CONFIG_HOSTNAME) > 64)
					CONFIG_HOSTNAME[64] = '\0';
			}
		}
		free_result(&result);

		if (NULL == CONFIG_HOSTNAME)
		{
			zabbix_log(LOG_LEVEL_CRIT, "Hostname is not defined");
			exit(1);
		}
	}
	else
	{
		if (strlen(CONFIG_HOSTNAME) > 64)
		{
			zabbix_log(LOG_LEVEL_CRIT, "Hostname too long");
			exit(1);
		}
	}

	if (1 == CONFIG_DISABLE_ACTIVE && 1 == CONFIG_DISABLE_PASSIVE)
	{
		zabbix_log(LOG_LEVEL_CRIT, "Either active or passive checks must be enabled");
		exit(1);
	}
}

static int	add_parameter(char *key)
{
	char	*command;

	if (NULL == (command = strchr(key, ',')))
		return FAIL;

	*command++ = '\0';

	return add_user_parameter(key, command);
}

void	load_user_parameters(int optional)
{
	struct cfg_line	cfg[] =
	{
		/* PARAMETER,		VAR,				FUNC,
			TYPE,		MANDATORY,	MIN,	MAX */
		{"EnableRemoteCommands",&CONFIG_ENABLE_REMOTE_COMMANDS,	NULL,
			TYPE_INT,	PARM_OPT,	0,	1},
		{"LogRemoteCommands",	&CONFIG_LOG_REMOTE_COMMANDS,	NULL,
			TYPE_INT,	PARM_OPT,	0,	1},
		{"UnsafeUserParameters",&CONFIG_UNSAFE_USER_PARAMETERS,	NULL,
			TYPE_INT,	PARM_OPT,	0,	1},
		{"Alias",		NULL,				&add_alias_from_config,
			TYPE_STRING,	PARM_OPT,	0,	0},
		{"UserParameter",	NULL,				&add_parameter,
			0,		0,		0,	0},
#ifdef _WINDOWS
		{"PerfCounter",		NULL,				&add_perfs_from_config,
			TYPE_STRING,	PARM_OPT,	0,	0},
#endif	/* _WINDOWS */
#ifdef WITH_PLUGINS
		{"Plugin",		NULL,				&add_plugin,
			TYPE_STRING,	PARM_OPT,	0,	0},
#endif	/* ZABBIX_DAEMON */
		{NULL}
	};

	if (!optional)
		parse_cfg_file(CONFIG_FILE, cfg);
	else
		parse_opt_cfg_file(CONFIG_FILE, cfg);
}

#ifdef _AIX
void	tl_version()
{
#ifdef _AIXVERSION_610
#	define ZBX_AIX_TL	"6100 and above"
#elif _AIXVERSION_530
#	ifdef HAVE_AIXOSLEVEL_530006
#		define ZBX_AIX_TL	"5300-06 and above"
#	else
#		define ZBX_AIX_TL	"5300-00,01,02,03,04,05"
#	endif
#elif _AIXVERSION_520
#	define ZBX_AIX_TL	"5200"
#elif _AIXVERSION_510
#	define ZBX_AIX_TL	"5100"
#endif
#ifdef ZBX_AIX_TL
	printf("Supported technology levels: %s\n", ZBX_AIX_TL);
#endif /* ZBX_AIX_TL */
#undef ZBX_AIX_TL
}
#endif /* _AIX */
