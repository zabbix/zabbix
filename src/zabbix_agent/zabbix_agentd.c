#include "config.h"

#include <netdb.h>

#include <syslog.h>

#include <stdlib.h>
#include <stdio.h>

#include <unistd.h>
#include <signal.h>

#include <errno.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <arpa/inet.h>

/* No warning for bzero */
#include <string.h>
#include <strings.h>

/* For config file operations */
#include <sys/types.h>
#include <sys/stat.h>
#include <fcntl.h>

/* For setpriority */
#include <sys/time.h>
#include <sys/resource.h>

/* Required for getpwuid */
#include <pwd.h>

#include "common.h"
#include "sysinfo.h"
#include "zabbix_agent.h"

#define	LISTENQ 1024

static	pid_t	*pids;

char	*config_host_allowed=NULL;
int	config_agentd_forks=AGENTD_FORKS;

void	signal_handler( int sig )
{
	if( SIGALRM == sig )
	{
		signal( SIGALRM, signal_handler );
		syslog( LOG_WARNING, "Timeout while answering request");
	}
 
	if( SIGQUIT == sig || SIGINT == sig || SIGTERM == sig )
	{
		syslog( LOG_WARNING, "Got signal. Exiting ...");
		exit( FAIL );
	}
}

void    daemon_init(void)
{
	int     i;
	pid_t   pid;
	struct passwd   *pwd;

	/* running as root ?*/
	if((getuid()==0) || (getuid()==0))
	{
		pwd = getpwnam("zabbix");
		if ( pwd == NULL )
		{
			fprintf(stderr,"User zabbix does not exist.\n");
			fprintf(stderr, "Cannot run as root !\n");
			exit(FAIL);
		}
		if( (setgid(pwd->pw_gid) ==-1) || (setuid(pwd->pw_uid) == -1) )
		{
			fprintf(stderr,"Cannot setgid or setuid to zabbix\n");
			exit(FAIL);
		}
/*
Is not supported on HP-UX

		if( (setegid(pwd->pw_gid) ==-1) || (seteuid(pwd->pw_uid) == -1) )
		{
			fprintf(stderr,"Cannot setegid or seteuid to zabbix\n");
			exit(FAIL);
		}
*/
	}

	if( (pid = fork()) != 0 )
	{
		exit( 0 );
	}

	setsid();
	
	signal( SIGHUP, SIG_IGN );

	if( (pid = fork()) !=0 )
	{
		exit( 0 );
	}

	chdir("/");
	umask(0);

	for(i=0;i<MAXFD;i++)
	{
		close(i);
	}

        openlog("zabbix_agentd",LOG_PID,LOG_USER);
	/*	setlogmask(LOG_UPTO(LOG_DEBUG)); */
	setlogmask(LOG_UPTO(LOG_WARNING));

	if(setpriority(PRIO_PROCESS,0,5)!=0)
	{
		syslog( LOG_WARNING, "Unable to set process priority to 5. Leaving default.");
	}

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

	file=fopen("/etc/zabbix/zabbix_agentd.conf","r");
	if(NULL == file)
	{
		syslog( LOG_CRIT, "Cannot open /etc/zabbix/zabbix_agentd.conf");
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

		if(strcmp(parameter,"Server")==0)
		{
			config_host_allowed=strdup(value);
		}
		else if(strcmp(parameter,"StartAgents")==0)
		{
			i=atoi(value);
			if( (i<1) || (i>16) )
			{
				syslog( LOG_CRIT, "Wrong value of StartAgents in line %d. Should be between 1 and 16.", lineno);
				fclose(file);
				exit(1);
			}
			config_agentd_forks=i;
		}
		else if(strcmp(parameter,"DebugLevel")==0)
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
		else if(strcmp(parameter,"UserParameter")==0)
		{
			value2=strstr(value,",");
			if(NULL == value2)
			{
				syslog( LOG_CRIT, "Error in line [%s] Line %d Symbol ',' expected", line, lineno);
				fclose(file);
				exit(1);
			}
			value2[0]=0;
			value2++;
			syslog( LOG_WARNING, "Added user-defined parameter [%s] Command [%s]", value, value2);
			add_user_parameter(value, value2);
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
}

int	check_security(int sockfd)
{
	char	*sname;
	struct	sockaddr_in name;
	int	i;

	if(getpeername(sockfd,  (struct sockaddr *)&name, (size_t *)&i) == 0)
	{
		i=sizeof(struct sockaddr_in);

		sname=inet_ntoa(name.sin_addr);

		if(strcmp(sname, config_host_allowed)!=0)
		{
			syslog( LOG_WARNING, "Connection from [%s] rejected. Allowed server is [%s] ",sname, config_host_allowed);
			return	FAIL;
		}
	}
	return	SUCCEED;
}

void	process_child(int sockfd)
{
	ssize_t	nread;
	char	line[1024];
	char	result[1024];
	double	res;

        static struct  sigaction phan;

	phan.sa_handler = &signal_handler; /* set up sig handler using sigaction() */
	sigemptyset(&phan.sa_mask);
	phan.sa_flags = 0;
	sigaction(SIGALRM, &phan, NULL);

	alarm(AGENT_TIMEOUT);

	syslog( LOG_DEBUG, "Before read()");
	if( (nread = read(sockfd, line, 1024)) < 0)
	{
		if(errno == EINTR)
		{
			syslog( LOG_DEBUG, "Read timeout");
		}
		else
		{
			syslog( LOG_DEBUG, "read() failed.");
		}
		syslog( LOG_DEBUG, "After read() 1");
		alarm(0);
		return;
	}
	syslog( LOG_DEBUG, "After read() 2 [%d]",nread);

	line[nread-1]=0;

	syslog( LOG_DEBUG, "Got line:%s", line);

	res=process(line);

	sprintf(result,"%f",res);
	syslog( LOG_DEBUG, "Sending back:%s", result);
	write(sockfd,result,strlen(result));

	alarm(0);
}

int	tcp_listen(const char *host, const char *serv, socklen_t *addrlenp)
{
	int			sockfd;
	struct sockaddr_in	serv_addr;

	if ( (sockfd = socket(AF_INET, SOCK_STREAM, 0)) < 0)
	{
		syslog( LOG_CRIT, "Unable to create socket");
		exit(1);
	}

	bzero((char *) &serv_addr, sizeof(serv_addr));
	serv_addr.sin_family      = AF_INET;
	serv_addr.sin_addr.s_addr = htonl(INADDR_ANY);
	serv_addr.sin_port        = htons(10000);

	if (bind(sockfd, (struct sockaddr *) &serv_addr, sizeof(serv_addr)) < 0)
	{
		syslog( LOG_CRIT, "Cannot bind to port %d. Another zabbix_agentd already running ?",10000);
		exit(1);
	}

	if(listen(sockfd, LISTENQ) != 0)
	{
		syslog( LOG_CRIT, "Listen failed");
		exit(1);
	}

	*addrlenp = sizeof(serv_addr);

	return	sockfd;
}

void	child_main(int i,int listenfd, int addrlen)
{
	int	connfd;
	socklen_t	clilen;
	struct sockaddr *cliaddr;

	cliaddr=malloc(addrlen);

	syslog( LOG_WARNING, "zabbix_agentd %ld started",(long)getpid());

	for(;;)
	{
		clilen = addrlen;
		connfd=accept(listenfd,cliaddr, &clilen);
		if( check_security(connfd) == SUCCEED)
		{
			process_child(connfd);
		}
		close(connfd);
	}
}

pid_t	child_make(int i,int listenfd, int addrlen)
{
	pid_t	pid;

	if((pid = fork()) >0)
	{
			return (pid);
	}

	/* never returns */
	child_main(i, listenfd, addrlen);

	/* avoid compilator warning */
	return 0;
}

int	main()
{
	int		listenfd;
	socklen_t	addrlen;
	int		i;

	char		host[128];
	char		*port="10000";

        static struct  sigaction phan;

	daemon_init();

	phan.sa_handler = &signal_handler;
	sigemptyset(&phan.sa_mask);
	phan.sa_flags = 0;
	sigaction(SIGINT, &phan, NULL);
	sigaction(SIGQUIT, &phan, NULL);
	sigaction(SIGTERM, &phan, NULL);

	process_config_file();

	syslog( LOG_WARNING, "zabbix_agentd started");

	if(gethostname(host,127) != 0)
	{
		syslog( LOG_CRIT, "gethostname() failed");
		exit(FAIL);
	}

	listenfd = tcp_listen(host,port,&addrlen);

	pids = calloc(config_agentd_forks, sizeof(pid_t));

	for(i = 0; i<config_agentd_forks; i++)
	{
		pids[i] = child_make(i, listenfd, addrlen);
/*		syslog( LOG_WARNING, "zabbix_agentd #%d started", pids[i]);*/
	}

	for(;;)
	{
			pause();
	}

	return SUCCEED;
}
