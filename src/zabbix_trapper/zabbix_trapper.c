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

#include <syslog.h>

#include "common.h"
#include "db.h"
#include "functions.h"

char	*CONFIG_DBNAME		= NULL;
char	*CONFIG_DBUSER		= NULL;
char	*CONFIG_DBPASSWORD	= NULL;

void	signal_handler( int sig )
{
	if( SIGALRM == sig )
	{
		signal( SIGALRM, signal_handler );
 
//		fprintf(stderr,"Timeout while executing operation.");
	}
 
	if( SIGQUIT == sig || SIGINT == sig || SIGTERM == sig )
	{
//		fprintf(stderr,"\nGot QUIT or INT or TERM signal. Exiting..." );
	}
	exit( FAIL );
}


void	process_config_file(void)
{
	FILE	*file;
	char	line[1024];
	char	parameter[1024];
	char	*value;
	int	lineno;


	file=fopen("/etc/zabbix/zabbix_trapper.conf","r");
	if(NULL == file)
	{
		syslog( LOG_CRIT, "Cannot open /etc/zabbix/zabbix_trapper.conf");
		exit(1);
	}

	lineno=1;
	while(fgets(line,1024,file) != NULL)
	{
		if(line[0]=='#')	continue;
		if(strlen(line)==1)	continue;

		strcpy(parameter,line);

		value=strstr(line,"=");

		if(NULL == value)
		{
			syslog( LOG_CRIT, "Error in line [%s] Line %d", line, lineno);
			fclose(file);
			exit(1);
		}
		value++;
		value[strlen(value)-1]=0;

		parameter[value-line-1]=0;

		syslog( LOG_DEBUG, "Parameter [%s] Value [%s]", parameter, value);

		if(strcmp(parameter,"DebugLevel")==0)
		{
			if(strcmp(value,"1") == 0)
			{
				setlogmask(LOG_UPTO(LOG_CRIT));
			}
			else if(strcmp(value,"2") == 0)
			{
				setlogmask(LOG_UPTO(LOG_WARNING));
			}
			else if(strcmp(value,"3") == 0)
			{
				setlogmask(LOG_UPTO(LOG_DEBUG));
			}
			else
			{
				syslog( LOG_CRIT, "Wrong DebugLevel in line %d", lineno);
				fclose(file);
				exit(1);
			}
		}
		else if(strcmp(parameter,"DBName")==0)
		{
			CONFIG_DBNAME=(char *)malloc(strlen(value));
			strcpy(CONFIG_DBNAME,value);
		}
		else if(strcmp(parameter,"DBUser")==0)
		{
			CONFIG_DBUSER=(char *)malloc(strlen(value));
			strcpy(CONFIG_DBUSER,value);
		}
		else if(strcmp(parameter,"DBPassword")==0)
		{
			CONFIG_DBPASSWORD=(char *)malloc(strlen(value));
			strcpy(CONFIG_DBPASSWORD,value);
		}
		else
		{
			syslog( LOG_CRIT, "Unsupported parameter [%s] Line %d", parameter, lineno);
			fclose(file);
			exit(1);
		}

		lineno++;
	}
	fclose(file);
	
	if(CONFIG_DBNAME == NULL)
	{
		syslog( LOG_CRIT, "DBName not in config file");
		exit(1);
	}
}

int	main()
{
	char	*s,*p;
	char	*server,*key,*value_string;
	double	value;

	int	ret=SUCCEED;

	signal( SIGINT,  signal_handler );
	signal( SIGQUIT, signal_handler );
	signal( SIGTERM, signal_handler );
	signal( SIGALRM, signal_handler );

	s=(char *) malloc( 1024 );

	alarm(TRAPPER_TIMEOUT);

	openlog("zabbix_trapper",LOG_PID,LOG_USER);
	//	ret=setlogmask(LOG_UPTO(LOG_DEBUG));
	ret=setlogmask(LOG_UPTO(LOG_WARNING));

	process_config_file();
	
	fgets(s,1024,stdin);
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
	value=atof(value_string);


	DBconnect(CONFIG_DBNAME, CONFIG_DBUSER, CONFIG_DBPASSWORD);

	ret=process_data(server,key,value);

	alarm(0);

	if(SUCCEED == ret)
	{
		printf("OK\n");
	}

	free(s);

	return ret;
}
