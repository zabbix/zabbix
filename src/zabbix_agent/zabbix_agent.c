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

#include "config.h"

#include "common.h"
#include "cfg.h"
#include "log.h"
#include "sysinfo.h"
#include "security.h"
#include "zabbix_agent.h"

static	char	*CONFIG_HOSTS_ALLOWED	= NULL;
static	int	CONFIG_TIMEOUT		= AGENT_TIMEOUT;
int		CONFIG_ENABLE_REMOTE_COMMANDS	= 0;

void	signal_handler( int sig )
{
	if( SIGALRM == sig )
	{
		signal( SIGALRM, signal_handler );
	}
 
	if( SIGQUIT == sig || SIGINT == sig || SIGTERM == sig )
	{
	}
	exit( FAIL );
}

int	add_parameter(char *value)
{
	char	*value2;

	value2=strstr(value,",");
	if(NULL == value2)
	{
		return	FAIL;
	}
	value2[0]=0;
	value2++;
	add_user_parameter(value, value2);
	return	SUCCEED;
}

void    init_config(void)
{
	struct cfg_line cfg[]=
	{
/*               PARAMETER      ,VAR    ,FUNC,  TYPE(0i,1s),MANDATORY,MIN,MAX
*/
		{"Server",&CONFIG_HOSTS_ALLOWED,0,TYPE_STRING,PARM_MAND,0,0},
		{"Timeout",&CONFIG_TIMEOUT,0,TYPE_INT,PARM_OPT,1,30},
		{"UserParameter",0,&add_parameter,0,0,0,0},
		{0}
	};

	parse_cfg_file("/etc/zabbix/zabbix_agent.conf",cfg);
}

int	main()
{
	char	s[MAX_STRING_LEN];
	char	value[MAX_STRING_LEN];
	AGENT_RESULT	result;
	
	memset(&result, 0, sizeof(AGENT_RESULT));

#ifdef	TEST_PARAMETERS
	init_metrics();
/*	init_config();*/
	test_parameters();
	return	SUCCEED;
#endif

	signal( SIGINT,  signal_handler );
	signal( SIGQUIT, signal_handler );
	signal( SIGTERM, signal_handler );
	signal( SIGALRM, signal_handler );

/* Must be before init_config() */
	init_metrics();
	init_config();

/* Do not create debug files */
	zabbix_open_log(LOG_TYPE_SYSLOG,LOG_LEVEL_EMPTY,NULL);

	alarm(CONFIG_TIMEOUT);

	if(check_security(0,CONFIG_HOSTS_ALLOWED,0) == FAIL)
	{
		exit(FAIL);
	}

	fgets(s,MAX_STRING_LEN,stdin);
	
	process(s, 0, &result);
	if(result.type & AR_DOUBLE)
		snprintf(value, MAX_STRING_LEN-1, "%f", result.dbl);
	else if(result.type & AR_UINT64)
		snprintf(value, MAX_STRING_LEN-1, ZBX_FS_UI64, result.ui64);
	else if(result.type & AR_STRING)
		snprintf(value, MAX_STRING_LEN-1, "%s", result.str);
	else if(result.type & AR_MESSAGE)
		snprintf(value, MAX_STRING_LEN-1, "%s", result.msg);
	free_result(&result);
  
	printf("%s\n",value);

	fflush(stdout);

	alarm(0);

	return SUCCEED;
}

