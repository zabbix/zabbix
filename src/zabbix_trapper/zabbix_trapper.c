/* 
** Zabbix
** Copyright (C) 2000,2001,2002,2003,2004 Alexei Vladishev
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


#include <stdlib.h>
#include <stdio.h>

#include <unistd.h>
#include <signal.h>

#include <errno.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <arpa/inet.h>

/* For strtok */
#include <string.h>

/* For config file operations */
#include <sys/types.h>
#include <sys/stat.h>
#include <fcntl.h>

#include "config.h"

#include <time.h>

#include "common.h"
#include "db.h"
#include "log.h"
#include "cfg.h"
#include "functions.h"

int	CONFIG_TIMEOUT		= TRAPPER_TIMEOUT;
int	CONFIG_LOG_LEVEL	= LOG_LEVEL_WARNING;
char	*CONFIG_LOG_FILE	= NULL;
char	*CONFIG_DBHOST		= NULL;
char	*CONFIG_DBNAME		= NULL;
char	*CONFIG_DBUSER		= NULL;
char	*CONFIG_DBPASSWORD	= NULL;
char	*CONFIG_DBSOCKET	= NULL;

void	signal_handler( int sig )
{
	if( SIGALRM == sig )
	{
		signal( SIGALRM, signal_handler );
 
/*		fprintf(stderr,"Timeout while executing operation.");*/
	}
 
	if( SIGQUIT == sig || SIGINT == sig || SIGTERM == sig )
	{
/*		fprintf(stderr,"\nGot QUIT or INT or TERM signal. Exiting..." );*/
	}
	exit( FAIL );
}

void    init_config(void)
{
        static struct cfg_line cfg[]=
        {
/*               PARAMETER      ,VAR    ,FUNC,  TYPE(0i,1s),MANDATORY,MIN,MAX
*/
                {"Timeout",&CONFIG_TIMEOUT,0,TYPE_INT,PARM_OPT,1,30},
                {"DebugLevel",&CONFIG_LOG_LEVEL,0,TYPE_INT,PARM_OPT,1,3},
                {"LogFile",&CONFIG_LOG_FILE,0,TYPE_STRING,PARM_OPT,0,0},
                {"DBHost",&CONFIG_DBHOST,0,TYPE_STRING,PARM_OPT,0,0},
                {"DBName",&CONFIG_DBNAME,0,TYPE_STRING,PARM_MAND,0,0},
                {"DBUser",&CONFIG_DBUSER,0,TYPE_STRING,PARM_OPT,0,0},
                {"DBPassword",&CONFIG_DBPASSWORD,0,TYPE_STRING,PARM_OPT,0,0},
                {"DBSocket",&CONFIG_DBSOCKET,0,TYPE_STRING,PARM_OPT,0,0},
                {0}
	};
	parse_cfg_file("/etc/zabbix/zabbix_trapper.conf",cfg);

	if(CONFIG_DBNAME == NULL)
	{
		zabbix_log( LOG_LEVEL_CRIT, "DBName not in config file");
		exit(1);
	}
}
      
int	main()
{
	static	char	s[MAX_STRING_LEN];
	char	*p;

	char	*server,*key,*value_string;

	int	ret=SUCCEED;

	signal( SIGINT,  signal_handler );
	signal( SIGQUIT, signal_handler );
	signal( SIGTERM, signal_handler );
	signal( SIGALRM, signal_handler );

	alarm(CONFIG_TIMEOUT);

	if(CONFIG_LOG_FILE == NULL)
	{
		zabbix_open_log(LOG_TYPE_SYSLOG,CONFIG_LOG_LEVEL,NULL);
	}
	else
	{
		zabbix_open_log(LOG_TYPE_FILE,CONFIG_LOG_LEVEL,CONFIG_LOG_FILE);
	}

	init_config();

	fgets(s,MAX_STRING_LEN,stdin);
	for( p=s+strlen(s)-1; p>s && ( *p=='\r' || *p =='\n' || *p == ' ' ); --p );
	p[1]=0;

	server=(char *)strtok(s,":");
	if(NULL == server)
	{
		return FAIL;
	}

	key=(char *)strtok(NULL,":");
	if(NULL == key)
	{
		return FAIL;
	}

	value_string=(char *)strtok(NULL,":");
	if(NULL == value_string)
	{
		return FAIL;
	}
/*	???
	value=atof(value_string);*/

	DBconnect();
	ret=process_data(0,server,key,value_string);

	alarm(0);

	if(SUCCEED == ret)
	{
		printf("OK\n");
	}

	return ret;
}
