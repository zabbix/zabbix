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


#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <netinet/in.h>
#include <netdb.h>

#include <string.h>

/* OpenBSD*/
#ifdef HAVE_SYS_SOCKET_H
	#include <sys/socket.h>
#endif

#include <signal.h>
#include <time.h>

#include "config.h"
#include "common.h"
#include "log.h"
#include "cfg.h"

char *progname = NULL;
char title_message[] = "ZABBIX SNMP trapper";
char usage_message[] = "[<Zabbix server> <port> <server:key> <value>]";
char *help_message[] = {
	"",
	"  If no arguments are given, zabbix_sender expects list of parameters",
	"  from standard input.",
	"",
        0 /* end of text */
};

int     CONFIG_SUCKERD_FORKS            =SUCKER_FORKS;
int     CONFIG_NOTIMEWAIT               =0;
int     CONFIG_TIMEOUT                  =SUCKER_TIMEOUT;
int     CONFIG_HOUSEKEEPING_FREQUENCY   = 1;
int     CONFIG_SENDER_FREQUENCY         = 30;
int     CONFIG_PINGER_FREQUENCY         = 60;
int     CONFIG_DISABLE_HOUSEKEEPING     = 0;
int     CONFIG_LOG_LEVEL                = LOG_LEVEL_WARNING;
char    *CONFIG_PID_FILE                = NULL;
char    *CONFIG_LOG_FILE                = NULL;
char    *CONFIG_ALERT_SCRIPTS_PATH      = NULL;
char    *CONFIG_DBHOST                  = NULL;
char    *CONFIG_DBNAME			= NULL;
char    *CONFIG_DBUSER			= NULL;
char    *CONFIG_DBPASSWORD		= NULL;
char    *CONFIG_DBSOCKET		= NULL;
char    *CONFIG_SERVER			= NULL;
int     CONFIG_SERVER_PORT		= 10001;

void    signal_handler( int sig )
{
	if( SIGALRM == sig )
	{
		signal( SIGALRM, signal_handler );
		fprintf(stderr,"Timeout while executing operation.\n");
	}
 
	if( SIGQUIT == sig || SIGINT == sig || SIGTERM == sig )
	{
/*		fprintf(stderr,"\nGot QUIT or INT or TERM signal. Exiting..." ); */
	}
	exit( FAIL );
}


void	init_config(void)
{
	static struct cfg_line cfg[]=
	{
/*		 PARAMETER	,VAR	,FUNC,	TYPE(0i,1s),MANDATORY,MIN,MAX	*/
		{"StartSuckers",&CONFIG_SUCKERD_FORKS,0,TYPE_INT,PARM_OPT,5,255},
		{"HousekeepingFrequency",&CONFIG_HOUSEKEEPING_FREQUENCY,0,TYPE_INT,PARM_OPT,1,24},
		{"SenderFrequency",&CONFIG_SENDER_FREQUENCY,0,TYPE_INT,PARM_OPT,5,3600},
		{"PingerFrequency",&CONFIG_PINGER_FREQUENCY,0,TYPE_INT,PARM_OPT,10,3600},
		{"Timeout",&CONFIG_TIMEOUT,0,TYPE_INT,PARM_OPT,1,30},
		{"NoTimeWait",&CONFIG_NOTIMEWAIT,0,TYPE_INT,PARM_OPT,0,1},
		{"DisableHousekeeping",&CONFIG_DISABLE_HOUSEKEEPING,0,TYPE_INT,PARM_OPT,0,1},
		{"DebugLevel",&CONFIG_LOG_LEVEL,0,TYPE_INT,PARM_OPT,0,4},
		{"PidFile",&CONFIG_PID_FILE,0,TYPE_STRING,PARM_OPT,0,0},
		{"LogFile",&CONFIG_LOG_FILE,0,TYPE_STRING,PARM_OPT,0,0},
		{"AlertScriptsPath",&CONFIG_ALERT_SCRIPTS_PATH,0,TYPE_STRING,PARM_OPT,0,0},
		{"DBHost",&CONFIG_DBHOST,0,TYPE_STRING,PARM_OPT,0,0},
		{"DBName",&CONFIG_DBNAME,0,TYPE_STRING,PARM_MAND,0,0},
		{"DBUser",&CONFIG_DBUSER,0,TYPE_STRING,PARM_OPT,0,0},
		{"DBPassword",&CONFIG_DBPASSWORD,0,TYPE_STRING,PARM_OPT,0,0},
		{"DBSocket",&CONFIG_DBSOCKET,0,TYPE_STRING,PARM_OPT,0,0},
		{"Server",&CONFIG_SERVER,0,TYPE_STRING,PARM_MAND,0,0},
		{"ServerPort",&CONFIG_SERVER_PORT,0,TYPE_INT,PARM_OPT,1,65535},
		{0}
	};

	parse_cfg_file("/etc/zabbix/zabbix_snmptrapper.conf",cfg);

	if(CONFIG_DBNAME == NULL)
	{
		zabbix_log( LOG_LEVEL_CRIT, "DBName not in config file");
		exit(1);
	}
	if(CONFIG_PID_FILE == NULL)
	{
		CONFIG_PID_FILE=strdup("/tmp/zabbix_suckerd.pid");
	}
	if(CONFIG_ALERT_SCRIPTS_PATH == NULL)
	{
		CONFIG_ALERT_SCRIPTS_PATH=strdup("/home/zabbix/bin");
	}
}


int	send_value(char *server,int port,char *shortname,char *value)
{
	int	i,s;
	char	tosend[1024];
	char	result[1024];
	struct hostent *hp;

	struct sockaddr_in myaddr_in;
	struct sockaddr_in servaddr_in;

/*	struct linger ling;*/

	servaddr_in.sin_family=AF_INET;
	hp=gethostbyname(server);

	if(hp==NULL)
	{
		return	FAIL;
	}

	servaddr_in.sin_addr.s_addr=((struct in_addr *)(hp->h_addr))->s_addr;

	servaddr_in.sin_port=htons(port);

	s=socket(AF_INET,SOCK_STREAM,0);
	if(s == -1)
	{
		return	FAIL;
	}

	myaddr_in.sin_family = AF_INET;
	myaddr_in.sin_port=0;
	myaddr_in.sin_addr.s_addr=INADDR_ANY;

	if( connect(s,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)) == -1 )
	{
		close(s);
		return	FAIL;
	}

	zbx_snprintf(tosend, sizeof(tosend), "%s:%s\n",shortname,value);

	if( sendto(s,tosend,strlen(tosend),0,(struct sockaddr *)&servaddr_in,sizeof(struct sockaddr_in)) == -1 )
	{
		perror("sendto");
		close(s);
		return	FAIL;
	} 
	i=sizeof(struct sockaddr_in);
/*	i=recvfrom(s,result,1023,0,(struct sockaddr *)&servaddr_in,(size_t *)&i);*/
	i=recvfrom(s,result,1023,0,(struct sockaddr *)&servaddr_in,(socklen_t *)&i);
	if(s==-1)
	{
		perror("recfrom");
		close(s);
		return	FAIL;
	}

	result[i-1]=0;

	if(strcmp(result,"OK") == 0)
	{
		printf("OK\n");
	}
 
	if( close(s)!=0 )
	{
		perror("close");
		
	}

	return SUCCEED;
}

int main(int argc, char **argv)
{
	int	port;
	int	ret=SUCCEED;
	char	line[MAX_STRING_LEN];
	char	port_str[MAX_STRING_LEN];
	char	zabbix_server[MAX_STRING_LEN];
	char	server_key[MAX_STRING_LEN];
	char	value[MAX_STRING_LEN];
	char	str[MAX_STRING_LEN];

	char	*hostname;
	char	*ip;
	char	*uptime;
	char	*oid;
	char	*address;
	char	*community;
	char	*enterprise;

	printf("The binary is no ready yet! To be available in Zabbix 1.0beta11.\n");
	exit(-1);

	progname = argv[0];

	signal( SIGINT,  signal_handler );
	signal( SIGQUIT, signal_handler );
	signal( SIGTERM, signal_handler );
	signal( SIGALRM, signal_handler );
/*
	read hostname
	read ip
	read uptime
	read oid
	read address
	read community
	read enterprise

	oid=`echo $oid|cut -f2 -d' '`
	address=`echo $address|cut -f2 -d' '`
	community=`echo $community|cut -f2 -d' '`
	enterprise=`echo $enterprise|cut -f2 -d' '`

	oid=`echo $oid|cut -f11 -d'.'`
	community=`echo $community|cut -f2 -d'"'`

	str="$hostname $address $community $enterprise $oid"

	$ZABBIX_SERVER $ZABBIX_PORT $HOST:$KEY "$str"
*/

	init_config();

	if(argc == 6)
	{
		hostname =	argv[1];
		ip =		argv[2];
		uptime =	argv[3];
		oid =		argv[4];
		address =	argv[5];
		community =	argv[6];
		enterprise =	argv[7];


		port=atoi(argv[2]);

		alarm(SNMPTRAPPER_TIMEOUT);

		zbx_snprintf(str, sizeof(str), "%s(%s)", hostname, ip);

		ret = send_value(CONFIG_SERVER, CONFIG_SERVER_PORT, argv[3],argv[4]);

		alarm(0);
	}
/* No parameters are given */	
	else
	{
		help();
		ret = FAIL;
	}

	return ret;
}
