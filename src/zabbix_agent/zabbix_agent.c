/*#define	TEST_PARAMETERS*/

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
#include "cfg.h"
#include "sysinfo.h"
#include "zabbix_agent.h"

static	char	*CONFIG_HOSTS_ALLOWED	= NULL;
static	int	CONFIG_TIMEOUT		= AGENT_TIMEOUT;

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
   

void	process_config_file(void)
{
	FILE	*file;
	char	line[1024];
	char	parameter[1024];
	char	*value;
	char	*value2;
	int	lineno;
	int	i;

	file=fopen("/etc/zabbix/zabbix_agent.conf","r");
	if(NULL == file)
	{
/*		syslog( LOG_CRIT, "Cannot open /etc/zabbix/zabbix_agentd.conf");*/
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
/*			syslog( LOG_CRIT, "Error in line [%s] Line %d", line, lineno);*/
			fclose(file);
			exit(1);
		}
		value++;
		value[strlen(value)-1]=0;

		parameter[value-line-1]=0;

/*		syslog( LOG_DEBUG, "Parameter [%s] Value [%s]", parameter, value);*/

		if(strcmp(parameter,"Server")==0)
		{
			CONFIG_HOSTS_ALLOWED=strdup(value);
		}
		else if(strcmp(parameter,"Timeout")==0)
		{
			i=atoi(value);
			if( (i<1) || (i>30) )
			{
/*				syslog( LOG_CRIT, "Wrong value of Timeout in line %d. Should be between 1 or 30.", lineno);*/
				fclose(file);
				exit(1);
			}
			CONFIG_TIMEOUT=i;
		}
		else if(strcmp(parameter,"UserParameter")==0)
		{
			value2=strstr(value,",");
			if(NULL == value2)
			{
/*				syslog( LOG_CRIT, "Error in line [%s] Line %d Symbol ',' expected", line, lineno);*/
				fclose(file);
				exit(1);
			}
			value2[0]=0;
			value2++;
/*			syslog( LOG_WARNING, "Added user-defined parameter [%s] Command [%s]", value, value2);*/
			add_user_parameter(value, value2);
		}
		else
		{
/*			syslog( LOG_CRIT, "Unsupported parameter [%s] Line %d", parameter, lineno);*/
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
	char	*s;
	char	*tmp;

	i=sizeof(name);
	if(getpeername(0,  (struct sockaddr *)&name, (size_t *)&i) == 0)
	{
		i=sizeof(struct sockaddr_in);

		sname=inet_ntoa(name.sin_addr);

		tmp=strdup(CONFIG_HOSTS_ALLOWED);
                s=(char *)strtok(tmp,",");
                while(s!=NULL)
                {
                        if(strcmp(sname, s)==0)
                        {
                                return  SUCCEED;
                        }
                        s=(char *)strtok(NULL,",");
                }
	}
        else
	{
/*		syslog( LOG_WARNING, "Error getpeername [%m]");*/
/*		syslog( LOG_WARNING, "Connection rejected");*/
		return FAIL;
	}
	return	FAIL;
}

char	*process_input()
{
	char	s[1024];

	fgets(s,1024,stdin);

	return	process(s);
}

int	main()
{
	char	*res;

#ifdef	TEST_PARAMETERS
	process_config_file();
	test_parameters();
	return	SUCCEED;
#endif

	signal( SIGINT,  signal_handler );
	signal( SIGQUIT, signal_handler );
	signal( SIGTERM, signal_handler );
	signal( SIGALRM, signal_handler );

	init_config();

	alarm(CONFIG_TIMEOUT);

	if(check_security() == FAIL)
	{
		exit(FAIL);
	}

	res=process_input();

	printf("%s\n",res);

	fflush(stdout);

	alarm(0);

	return SUCCEED;
}
