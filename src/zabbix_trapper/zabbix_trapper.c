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
      
void	process_config_file(void)
{
	FILE	*file;
	char	line[MAX_STRING_LEN+1];
	char	parameter[MAX_STRING_LEN+1];
	char	*value;
	int	lineno;
	int	i;

	file=fopen("/etc/zabbix/zabbix_trapper.conf","r");
	if(NULL == file)
	{
		zabbix_log( LOG_LEVEL_CRIT, "Cannot open /etc/zabbix/zabbix_trapper.conf");
		exit(1);
	}

	lineno=0;
	while(fgets(line,MAX_STRING_LEN,file) != NULL)
	{
		lineno++;

		if(line[0]=='#')	continue;
		if(strlen(line)==1)	continue;

		strncpy(parameter,line,MAX_STRING_LEN);

		value=strstr(line,"=");

		if(NULL == value)
		{
			zabbix_log( LOG_LEVEL_CRIT, "Error in line [%s] Line %d", line, lineno);
			fclose(file);
			exit(1);
		}
		value++;
		value[strlen(value)-1]=0;

		parameter[value-line-1]=0;

		zabbix_log( LOG_LEVEL_DEBUG, "Parameter [%s] Value [%s]", parameter, value);

		if(strcmp(parameter,"DebugLevel")==0)
		{
			if(strcmp(value,"1") == 0)
			{
				CONFIG_LOG_LEVEL=LOG_LEVEL_CRIT;
			}
			else if(strcmp(value,"2") == 0)
			{
				CONFIG_LOG_LEVEL=LOG_LEVEL_WARNING;
			}
			else if(strcmp(value,"3") == 0)
			{
				CONFIG_LOG_LEVEL=LOG_LEVEL_DEBUG;
			}
			else
			{
/*				zabbix_log( LOG_LEVEL_CRIT, "Wrong DebugLevel in line %d", lineno);*/
				fclose(file);
				exit(1);
			}
		}
		else if(strcmp(parameter,"Timeout")==0)
		{
			i=atoi(value);
			if( (i<1) || (i>30) )
			{
				zabbix_log( LOG_LEVEL_CRIT, "Wrong value of Timeout in line %d. Should be between 1 and 30.", lineno);
				fclose(file);
				exit(1);
			}
			CONFIG_TIMEOUT=i;
		}
		else if(strcmp(parameter,"LogFile")==0)
		{
			CONFIG_LOG_FILE=strdup(value);
		}
		else if(strcmp(parameter,"DBName")==0)
		{
			CONFIG_DBNAME=strdup(value);
		}
		else if(strcmp(parameter,"DBUser")==0)
		{
			CONFIG_DBUSER=strdup(value);
		}
		else if(strcmp(parameter,"DBPassword")==0)
		{
			CONFIG_DBPASSWORD=strdup(value);
		}
		else if(strcmp(parameter,"DBSocket")==0)
		{
			CONFIG_DBSOCKET=strdup(value);
		}
		else
		{
			zabbix_log( LOG_LEVEL_CRIT, "Unsupported parameter [%s] Line %d", parameter, lineno);
			fclose(file);
			exit(1);
		}
	}
	fclose(file);
	
	if(CONFIG_DBNAME == NULL)
	{
		zabbix_log( LOG_LEVEL_CRIT, "DBName not in config file");
		exit(1);
	}
}

int	main()
{
	static	char	s[MAX_STRING_LEN+1];
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

/*	process_config_file();*/
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


	DBconnect(CONFIG_DBNAME, CONFIG_DBUSER, CONFIG_DBPASSWORD, CONFIG_DBSOCKET);

	ret=process_data(server,key,value_string);

	alarm(0);

	if(SUCCEED == ret)
	{
		printf("OK\n");
	}
	else
	{
		printf("OK\n");
	}

	return ret;
}
