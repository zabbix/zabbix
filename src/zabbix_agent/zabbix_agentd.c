#include "config.h"

#include <string.h>

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
#include <strings.h>

/* For config file operations */
#include <sys/types.h>
#include <sys/stat.h>
#include <fcntl.h>

#include "common.h"
#include "sysinfo.h"
#include "zabbix_agent.h"

#define	LISTENQ 1024

static pid_t *pids;

char	host_allowed[16];

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
}

void	init_security(void)
{
	int file;
	int	i;

	file=open("/etc/zabbix/zabbix_agent.conf",O_RDONLY);
	if(file == -1)
	{
		syslog( LOG_CRIT, "Cannot open /etc/zabbix/zabbix_agent.conf");
		exit(1);
	}
	i=read(file, host_allowed, 16);
	host_allowed[i-1]=0;
	close(file);
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
		if(strcmp(sname, host_allowed)!=0)
		{
			syslog( LOG_WARNING, "Connection from [%s] rejected",sname);
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

	for(;;)
	{
//		sigfunc = signal( SIGALRM, signal_handler );
//		alarm(AGENT_TIMEOUT);

		if( (nread = read(sockfd, line, 1024)) == 0)
			return;

//		alarm(0);
//		signal(SIGALRM, sigfunc);

		line[nread-1]=0;

//		printf("Got line:{%s}\n",line);

		syslog( LOG_DEBUG, "Got line:%s", line);
		res=process(line);
		sprintf(result,"%f",res);
		syslog( LOG_DEBUG, "Sending back:%s", result);
		write(sockfd,result,strlen(result));
	}
}

int	tcp_listen(const char *host, const char *serv, socklen_t *addrlenp)
{
	int		listenfd, n;
	const int	on=1;
	struct addrinfo	hints, *res, *ressave;

	bzero(&hints,sizeof(struct addrinfo));
	hints.ai_flags = AI_PASSIVE;
	hints.ai_family = AF_UNSPEC;
	hints.ai_socktype = SOCK_STREAM;

	if( (n = getaddrinfo(host,serv, &hints, &res)) != 0)
	{
		perror("getaddrinfo()");
		exit(1);
	}

	ressave = res;

	do {
		listenfd = socket(res->ai_family, res->ai_socktype, res->ai_protocol);
		if( listenfd <0)
				continue;
		if(setsockopt(listenfd, SOL_SOCKET, SO_REUSEADDR, &on, sizeof(on)) !=0 )
		{
			perror("setsockopt()");
			exit(1);
		}
		if(bind(listenfd,res->ai_addr,res->ai_addrlen) == 0)
			break;
		close(listenfd);
	} while ((res = res ->ai_next) != NULL);

	if (res == NULL)
	{
		perror("tcp_listen");
		exit(1);
	}

	if(listen(listenfd, LISTENQ) !=0 )
	{
		perror("listen()");
		exit(1);
	}

	if(addrlenp)
		*addrlenp = res->ai_addrlen;

	freeaddrinfo(ressave);

	return	(listenfd);
}

void	child_main(int i,int listenfd, int addrlen)
{
	int	connfd;
	socklen_t	clilen;
	struct sockaddr *cliaddr;

	cliaddr=malloc(addrlen);

	printf("child %ld started\n",(long)getpid());

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
	int		i, ret;

	char		host[128];
	char		*port="10000";
	
	daemon_init();

	signal( SIGINT,  signal_handler );
	signal( SIGQUIT, signal_handler );
	signal( SIGTERM, signal_handler );
	signal( SIGALRM, signal_handler );

	init_security();

        openlog("zabbix_agentd",LOG_PID,LOG_USER);
//	ret=setlogmask(LOG_UPTO(LOG_DEBUG));
	ret=setlogmask(LOG_UPTO(LOG_WARNING));

	syslog( LOG_WARNING, "zabbix_agentd started");

	if(gethostname(host,127) != 0)
	{
		syslog( LOG_CRIT, "gethostname() failed");
		exit(FAIL);
	}

	listenfd = tcp_listen(host,port,&addrlen);

	pids = calloc(10, sizeof(pid_t));

	for(i = 0; i< 10; i++)
	{
		pids[i] = child_make(i, listenfd, addrlen);
		syslog( LOG_WARNING, "zabbix_agentd #%d started", pids[i]);
	}

	for(;;)
	{
			pause();
	}

	return SUCCEED;
}
