 #define	TEST_PARAMETERS 

#include "config.h"

#include <stdlib.h>
#include <stdio.h>

#include <unistd.h>
#include <signal.h>

#include <errno.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <arpa/inet.h>

/* For bcopy */
#include <string.h>

/* For config file operations */
#include <sys/types.h>
#include <sys/stat.h>
#include <fcntl.h>

#include "common.h"
#include "sysinfo.h"
#include "zabbix_agent.h"

char	*config_host_allowed=NULL;

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

void	process_config_file(void)
{
	FILE	*file;
	char	line[1024];
	char	parameter[1024];
	char	*value;
	char	*value2;
	int	lineno;

	file=fopen("/etc/zabbix/zabbix_agent.conf","r");
	if(NULL == file)
	{
//		syslog( LOG_CRIT, "Cannot open /etc/zabbix/zabbix_agentd.conf");
		exit(1);
	}

	lineno=0;
	while(fgets(line,1024,file) != NULL)
	{
		lineno++;

		if(line[0]=='#')	continue;
		if(strlen(line)==1)	continue;

		strcpy(parameter,line);

		value=strstr(line,"=");

		if(NULL == value)
		{
//			syslog( LOG_CRIT, "Error in line [%s] Line %d", line, lineno);
			fclose(file);
			exit(1);
		}
		value++;
		value[strlen(value)-1]=0;

		parameter[value-line-1]=0;

//		syslog( LOG_DEBUG, "Parameter [%s] Value [%s]", parameter, value);

		if(strcmp(parameter,"Server")==0)
		{
			config_host_allowed=strdup(value);
		}
		else if(strcmp(parameter,"UserParameter")==0)
		{
			value2=strstr(value,",");
			if(NULL == value2)
			{
//				syslog( LOG_CRIT, "Error in line [%s] Line %d Symbol ',' expected", line, lineno);
				fclose(file);
				exit(1);
			}
			value2[0]=0;
			value2++;
//			syslog( LOG_WARNING, "Added user-defined parameter [%s] Command [%s]", value, value2);
			add_user_parameter(value, value2);
		}
		else
		{
//			syslog( LOG_CRIT, "Unsupported parameter [%s] Line %d", parameter, lineno);
			fclose(file);
			exit(1);
		}
	}
	fclose(file);
}

int	check_security(void)
{
	char	*sname;
	struct	sockaddr_in name;
	int	i;

	if(getpeername(0,  (struct sockaddr *)&name, (size_t *)&i) == 0)
	{
		i=sizeof(struct sockaddr_in);

		sname=inet_ntoa(name.sin_addr);
		if(strcmp(sname,config_host_allowed)!=0)
		{
			return	FAIL;
		}
	}
	return	SUCCEED;
}

float	process_input()
{
	char	s[1024];

	fgets(s,1024,stdin);

	return	process(s);
}

int	main()
{
#ifdef	TEST_PARAMETERS
	test_parameters();
	return	SUCCEED;
#endif

	signal( SIGINT,  signal_handler );
	signal( SIGQUIT, signal_handler );
	signal( SIGTERM, signal_handler );
	signal( SIGALRM, signal_handler );

	alarm(AGENT_TIMEOUT);

	process_config_file();

	if(check_security() == FAIL)
	{
		exit(FAIL);
	}

	printf("%f\n",process_input());

	fflush(stdout);

	alarm(0);

	return SUCCEED;
}
