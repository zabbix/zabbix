/* #define	TEST_PARAMETERS */

#include "config.h"

#include <stdlib.h>
#include <stdio.h>

#include <unistd.h>
#include <signal.h>

#include <errno.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <arpa/inet.h>

/* For config file operations */
#include <sys/types.h>
#include <sys/stat.h>
#include <fcntl.h>

#include "common.h"
#include "sysinfo.h"
#include "zabbix_agent.h"

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

int	check_security(void)
{
	char	*sname;
	char	*config;
	struct	sockaddr_in name;
	int	i;
	int	file;

	if(getpeername(0,  (struct sockaddr *)&name, (size_t *)&i) == 0)
	{
		config=(char *)malloc(16);

		file=open("/etc/zabbix/zabbix_agent.conf",O_RDONLY);
		if(file == -1)
		{
			return FAIL;
		}
		i=read(file, config, 16);
		config[i-1]=0;
		close(file);

		i=sizeof(struct sockaddr_in);

		sname=inet_ntoa(name.sin_addr);
		if(strcmp(sname,config)!=0)
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
	if(check_security() == FAIL)
	{
		exit(FAIL);
	}

	signal( SIGINT,  signal_handler );
	signal( SIGQUIT, signal_handler );
	signal( SIGTERM, signal_handler );
	signal( SIGALRM, signal_handler );

	alarm(AGENT_TIMEOUT);

	printf("%f\n",process_input());

	fflush(stdout);

	alarm(0);

	return SUCCEED;
}
