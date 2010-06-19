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
#endif /* ZABBIX_DAEMON */

#if defined(WITH_PLUGINS)
/* use Zabbix plugins configurations */
#	include "zbxplugin.h"
#endif /* WITH_PLUGINS */


#ifdef USE_PID_FILE
	static char	DEFAULT_PID_FILE[]	= "/tmp/zabbix_agentd.pid";
#endif /* USE_PID_FILE */

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
char	CONFIG_LOG_UNRES_SYMB		= 0;

int	CONFIG_BUFFER_SIZE		= 100;
int	CONFIG_BUFFER_SEND		= 5;

int	CONFIG_MAX_LINES_PER_SECOND	= 100;

void    load_config()
{
	struct cfg_line cfg[]=
	{
/*               PARAMETER      ,VAR    ,FUNC,  TYPE(0i,1s), MANDATORY, MIN, MAX
*/
		{"Server",		&CONFIG_HOSTS_ALLOWED,	0,TYPE_STRING,	PARM_MAND,	0,0},
		{"Hostname",		&CONFIG_HOSTNAME,	0,TYPE_STRING,	PARM_OPT,	0,0},
		{"BufferSize",		&CONFIG_BUFFER_SIZE,	0,TYPE_INT,	PARM_OPT,	2,65535},
		{"BufferSend",		&CONFIG_BUFFER_SEND,	0,TYPE_INT,	PARM_OPT,	1,3600},

#ifdef USE_PID_FILE
		{"PidFile",		&APP_PID_FILE,		0,TYPE_STRING,	PARM_OPT,	0,0},
#endif /* USE_PID_FILE */

		{"LogFile",		&CONFIG_LOG_FILE,	0,TYPE_STRING,	PARM_OPT,	0,0},
		{"LogFileSize",		&CONFIG_LOG_FILE_SIZE,	0,TYPE_INT,	PARM_OPT,	0,1024},
		{"DisableActive",	&CONFIG_DISABLE_ACTIVE,	0,TYPE_INT,	PARM_OPT,	0,1},
		{"DisablePassive",	&CONFIG_DISABLE_PASSIVE,0,TYPE_INT,	PARM_OPT,	0,1},
		{"Timeout",		&CONFIG_TIMEOUT,	0,TYPE_INT,	PARM_OPT,	1,30},
		{"ListenPort",		&CONFIG_LISTEN_PORT,	0,TYPE_INT,	PARM_OPT,	1024,32767},
		{"ServerPort",		&CONFIG_SERVER_PORT,	0,TYPE_INT,	PARM_OPT,	1024,32767},
		{"ListenIP",		&CONFIG_LISTEN_IP,	0,TYPE_STRING,	PARM_OPT,	0,0},
		{"SourceIP",		&CONFIG_SOURCE_IP,	0,TYPE_STRING,	PARM_OPT,	0,0},

		{"DebugLevel",		&CONFIG_LOG_LEVEL,	0,TYPE_INT,	PARM_OPT,	0,4},

		{"StartAgents",		&CONFIG_ZABBIX_FORKS,		0,TYPE_INT,	PARM_OPT,	1,16},
		{"RefreshActiveChecks",	&CONFIG_REFRESH_ACTIVE_CHECKS,	0,TYPE_INT,	PARM_OPT,60,3600},
		{"MaxLinesPerSecond",	&CONFIG_MAX_LINES_PER_SECOND,	0,TYPE_INT,	PARM_OPT,1,1000},
		{"AllowRoot",		&CONFIG_ALLOW_ROOT,		0,TYPE_INT,	PARM_OPT,0,1},

		{"LogUnresolvedSymbols",&CONFIG_LOG_UNRES_SYMB,		0,	TYPE_STRING,PARM_OPT,0,1},

		{0}
	};

	AGENT_RESULT	result;
	char		**value = NULL;

	memset(&result, 0, sizeof(AGENT_RESULT));

	parse_cfg_file(CONFIG_FILE, cfg);

#ifdef USE_PID_FILE
	if(APP_PID_FILE == NULL)
	{
		APP_PID_FILE = DEFAULT_PID_FILE;
	}
#endif /* USE_PID_FILE */

	if(CONFIG_HOSTNAME == NULL || *CONFIG_HOSTNAME == '\0')
	{
		if(CONFIG_HOSTNAME != NULL)
			zbx_free(CONFIG_HOSTNAME);

	  	if(SUCCEED == process("system.hostname", 0, &result))
		{
			if( NULL != (value = GET_STR_RESULT(&result)) )
			{
				CONFIG_HOSTNAME = strdup(*value);

				/* If auto registration is used, our CONFIG_HOSTNAME will make it into the  */
				/* server's database, where it is limited by HOST_HOST_LEN (currently, 64), */
				/* so to make it work properly we need to truncate our hostname.            */
				if (strlen(CONFIG_HOSTNAME) > 64)
					CONFIG_HOSTNAME[64] = '\0';
			}
		}
	        free_result(&result);

		if(CONFIG_HOSTNAME == NULL)
		{
			zabbix_log( LOG_LEVEL_CRIT, "Hostname is not defined");
			exit(1);
		}
	}
	else
	{
		if(strlen(CONFIG_HOSTNAME) > 64)
		{
			zabbix_log( LOG_LEVEL_CRIT, "Hostname too long");
			exit(1);
		}
	}

	if(CONFIG_DISABLE_ACTIVE == 1 && CONFIG_DISABLE_PASSIVE == 1)
	{
		zabbix_log( LOG_LEVEL_CRIT, "Either active or passive checks must be enabled");
		exit(1);
	}
}

static int     add_parameter(char *key)
{
	char	*command;

	if (NULL == (command = strchr(key, ',')))
		return  FAIL;

	*command++ = '\0';

	return add_user_parameter(key, command);
}

void    load_user_parameters(int optional)
{
	struct cfg_line cfg[]=
	{
/*               PARAMETER,		VAR,	FUNC,		TYPE(0i,1s), MANDATORY,MIN,MAX
*/
		{"EnableRemoteCommands",&CONFIG_ENABLE_REMOTE_COMMANDS,	0,TYPE_INT,	PARM_OPT,0,1},
		{"LogRemoteCommands",&CONFIG_LOG_REMOTE_COMMANDS,	0,TYPE_INT,	PARM_OPT,0,1},
		{"UnsafeUserParameters",&CONFIG_UNSAFE_USER_PARAMETERS,	0,TYPE_INT,	PARM_OPT,0,1},

		{"Alias",		0,	&add_alias_from_config,	TYPE_STRING,PARM_OPT,0,0},
		{"UserParameter",	0,	&add_parameter,		0,	0,	0,	0},

#if defined(_WINDOWS)
		{"PerfCounter",		0,	&add_perfs_from_config,	TYPE_STRING,PARM_OPT,0,0},
#endif /* _WINDOWS */

#if defined(WITH_PLUGINS)
		{"Plugin",		0,	&add_plugin,	TYPE_STRING,PARM_OPT,0,0},
#endif /* ZABBIX_DAEMON */
		{0}
	};

	if (!optional)
		parse_cfg_file(CONFIG_FILE, cfg);
	else
		parse_opt_cfg_file(CONFIG_FILE, cfg);
}
