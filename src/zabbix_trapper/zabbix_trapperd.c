#include "config.h"

#include <string.h>

#include <netdb.h>

#include <syslog.h>

#include <stdlib.h>
#include <stdio.h>

#include <time.h>

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
#include "db.h"
#include "functions.h"

#define	LISTENQ 1024

static pid_t *pids;

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

int	process(char *s)
{
	char	*p;
	char	*server,*key,*value_string;
	double	value;

	int	ret=SUCCEED;

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

	ret=process_data(server,key,value);

	return ret;
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

void	process_child(int sockfd)
{
	ssize_t	nread;
	char	line[1024];
	char	result[1024];
	static struct  sigaction phan;

	phan.sa_handler = &signal_handler;
	sigemptyset(&phan.sa_mask);
	phan.sa_flags = 0;
	sigaction(SIGALRM, &phan, NULL);

//	for(;;)
//	{
//		sigfunc = signal( SIGALRM, signal_handler );
		alarm(TRAPPER_TIMEOUT);

		if( (nread = read(sockfd, line, 1024)) < 0)
		{
			if(errno == EINTR)
			{
				syslog( LOG_WARNING, "Timeout while waiting for parameter");
			}
			else
			{
				syslog( LOG_DEBUG, "read() error");
			}
			return;
		}

		alarm(0);
//		signal(SIGALRM, sigfunc);

		line[nread-1]=0;

//		printf("Got line:{%s}\n",line);

		syslog( LOG_DEBUG, "Got line:%s", line);
		if( SUCCEED == process(line) )
		{
			sprintf(result,"OK\n");
		}
		else
		{
			sprintf(result,"NOT OK\n");
		}
		syslog( LOG_DEBUG, "Sending back:%s", result);
		write(sockfd,result,strlen(result));
//	}
}

int	tcp_listen(const char *host, const char *serv, socklen_t *addrlenp)
{
	int	sockfd;
	struct	sockaddr_in      serv_addr;

	if ( (sockfd = socket(AF_INET, SOCK_STREAM, 0)) < 0)
	{
		syslog( LOG_CRIT, "socket()");
		exit(1);
	}

	bzero((char *) &serv_addr, sizeof(serv_addr));
	serv_addr.sin_family      = AF_INET;
	serv_addr.sin_addr.s_addr = htonl(INADDR_ANY);
	serv_addr.sin_port        = htons(10001);

	if (bind(sockfd, (struct sockaddr *) &serv_addr, sizeof(serv_addr)) < 0)
	{
		syslog( LOG_CRIT, "bind()");
		exit(1);
	}
	
	if(listen(sockfd, LISTENQ) !=0 )
	{
		syslog( LOG_CRIT, "listen()");
		exit(1);
	}

	*addrlenp = sizeof(serv_addr);

	return  sockfd;
}

/*
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
		syslog( LOG_CRIT, "getaddrinfo()");
		exit(1);
	}

	ressave = res;

	do {
		listenfd = socket(res->ai_family, res->ai_socktype, res->ai_protocol);
		if( listenfd <0)
				continue;
		if(setsockopt(listenfd, SOL_SOCKET, SO_REUSEADDR, &on, sizeof(on)) !=0 )
		{
			syslog( LOG_CRIT, "setsockopt()");
			exit(1);
		}
		if(bind(listenfd,res->ai_addr,res->ai_addrlen) == 0)
			break;
		close(listenfd);
	} while ((res = res ->ai_next) != NULL);

	if (res == NULL)
	{
		syslog( LOG_CRIT, "tcp_listen()");
		exit(1);
	}

	if(listen(listenfd, LISTENQ) !=0 )
	{
		syslog( LOG_CRIT, "listen()");
		exit(1);
	}

	if(addrlenp)
		*addrlenp = res->ai_addrlen;

	freeaddrinfo(ressave);

	return	(listenfd);
}
*/
void	child_main(int i,int listenfd, int addrlen)
{
	int	connfd;
	socklen_t	clilen;
	struct sockaddr *cliaddr;

	cliaddr=malloc(addrlen);

//	printf("child %ld started\n",(long)getpid());
	DBconnect();

	for(;;)
	{
		clilen = addrlen;
		connfd=accept(listenfd,cliaddr, &clilen);

		process_child(connfd);

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
	char		*port="10001";
	static struct  sigaction phan;

	daemon_init();

	phan.sa_handler = &signal_handler;
	sigemptyset(&phan.sa_mask);
	phan.sa_flags = 0;
	sigaction(SIGINT, &phan, NULL);
	sigaction(SIGQUIT, &phan, NULL);
	sigaction(SIGTERM, &phan, NULL);

	signal( SIGINT,  signal_handler );
	signal( SIGQUIT, signal_handler );
	signal( SIGTERM, signal_handler );
//	signal( SIGALRM, signal_handler );

        openlog("zabbix_trapperd",LOG_PID,LOG_USER);
//	ret=setlogmask(LOG_UPTO(LOG_DEBUG));
	ret=setlogmask(LOG_UPTO(LOG_WARNING));

	syslog( LOG_WARNING, "zabbix_trapperd started");

	if(gethostname(host,127) != 0)
	{
		syslog( LOG_CRIT, "gethostname() failed");
		exit(FAIL);
	}

	listenfd = tcp_listen(host,port,&addrlen);

	pids = calloc(TRAPPERD_FORKS, sizeof(pid_t));

	for(i = 0; i< TRAPPERD_FORKS; i++)
	{
		pids[i] = child_make(i, listenfd, addrlen);
		syslog( LOG_WARNING, "zabbix_trapperd #%d started", pids[i]);
	}

	for(;;)
	{
			pause();
	}

	return SUCCEED;
}
