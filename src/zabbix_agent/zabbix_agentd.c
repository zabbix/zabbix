#include "config.h"

#include <netdb.h>

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

#include "log.h"

#define	LISTENQ 1024

static	pid_t	*pids=NULL;
int	parent=0;
/* Number of processed requests */
int	stats_request=0;

static	char	*CONFIG_HOST_ALLOWED=NULL;
static	char	*CONFIG_PID_FILE=NULL;
static	char	*CONFIG_LOG_FILE=NULL;
static	int	CONFIG_AGENTD_FORKS=AGENTD_FORKS;
static	int	CONFIG_NOTIMEWAIT=0;
static	int	CONFIG_TIMEOUT=AGENT_TIMEOUT;
static	int	CONFIG_LISTEN_PORT=10000;

void	uninit(void)
{
	int i;

	if(parent == 1)
	{
		if(pids != NULL)
		{
			for(i = 0; i<CONFIG_AGENTD_FORKS; i++)
			{
				kill(pids[i],SIGTERM);
			}
		}

		if( unlink(CONFIG_PID_FILE) != 0)
		{
			zabbix_log( LOG_LEVEL_WARNING, "Cannot remove PID file [%s]",
				CONFIG_PID_FILE);
		}
	}
}

void	signal_handler( int sig )
{
	if( SIGALRM == sig )
	{
		signal( SIGALRM, signal_handler );
		zabbix_log( LOG_LEVEL_WARNING, "Timeout while answering request");
	}
	else if( SIGQUIT == sig || SIGINT == sig || SIGTERM == sig )
	{
		zabbix_log( LOG_LEVEL_WARNING, "Got signal. Exiting ...");
		uninit();
		exit( FAIL );
	}
/* parent==1 is mandatory ! EXECUTE sends SIGCHLD as well ... */
	else if( (SIGCHLD == sig) && (parent == 1) )
	{
		zabbix_log( LOG_LEVEL_WARNING, "One child process died. Exiting ...");
		uninit();
		exit( FAIL );
	}
	else
	{
		zabbix_log( LOG_LEVEL_WARNING, "Got signal [%d]. Ignoring ...", sig);
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

#ifdef HAVE_FUNCTION_SETEUID
		if( (setegid(pwd->pw_gid) ==-1) || (seteuid(pwd->pw_uid) == -1) )
		{
			fprintf(stderr,"Cannot setegid or seteuid to zabbix\n");
			exit(FAIL);
		}
#endif

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

/*        openlog("zabbix_agentd",LOG_LEVEL_PID,LOG_USER);
	setlogmask(LOG_UPTO(LOG_WARNING));*/

	if(CONFIG_LOG_FILE == NULL)
	{
		zabbix_open_log(LOG_TYPE_SYSLOG,LOG_LEVEL_WARNING,NULL);
	}
	else
	{
		zabbix_open_log(LOG_TYPE_FILE,LOG_LEVEL_WARNING,CONFIG_LOG_FILE);
	}

	if(setpriority(PRIO_PROCESS,0,5)!=0)
	{
		zabbix_log( LOG_LEVEL_WARNING, "Unable to set process priority to 5. Leaving default.");
	}

}

void	create_pid_file(void)
{
	FILE	*f;

/* Check if PID file already exists */
	f = fopen(CONFIG_PID_FILE, "r");
	if(f != NULL)
	{
		zabbix_log( LOG_LEVEL_CRIT, "File [%s] exists. Is zabbix_agentd already running ?",
			CONFIG_PID_FILE);
		fclose(f);
		exit(-1);
	}

	f = fopen(CONFIG_PID_FILE, "w");

	if( f == NULL)
	{
		zabbix_log( LOG_LEVEL_CRIT, "Cannot create PID file [%s] [%s]",
			CONFIG_PID_FILE, strerror(errno));
		uninit();
		exit(-1);
	}

	fprintf(f,"%d",getpid());
	fclose(f);
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
		zabbix_log( LOG_LEVEL_CRIT, "Cannot open /etc/zabbix/zabbix_agentd.conf");
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
			zabbix_log( LOG_LEVEL_CRIT, "Error in line [%s] Line %d", line, lineno);
			fclose(file);
			exit(1);
		}
		value++;
		value[strlen(value)-1]=0;

		parameter[value-line-1]=0;

		zabbix_log( LOG_LEVEL_WARNING, "Parameter [%s] Value [%s]", parameter, value);

		if(strcmp(parameter,"Server")==0)
		{
			CONFIG_HOST_ALLOWED=strdup(value);
		}
		else if(strcmp(parameter,"PidFile")==0)
		{
			CONFIG_PID_FILE=strdup(value);
		}
		else if(strcmp(parameter,"LogFile")==0)
		{
			CONFIG_LOG_FILE=strdup(value);
		}
		else if(strcmp(parameter,"Timeout")==0)
		{
			i=atoi(value);
			if( (i<1) || (i>30) )
			{
				zabbix_log( LOG_LEVEL_CRIT, "Wrong value of Timeout in line %d. Should be between 1 or 30.", lineno);
				fclose(file);
				exit(1);
			}
			CONFIG_TIMEOUT=i;
		}
		else if(strcmp(parameter,"NoTimeWait")==0)
		{
			i=atoi(value);
			if( (i<0) || (i>1) )
			{
				zabbix_log( LOG_LEVEL_CRIT, "Wrong value of NoTimeWait in line %d. Should be either 0 or 1.", lineno);
				fclose(file);
				exit(1);
			}
			CONFIG_NOTIMEWAIT=i;
		}
		else if(strcmp(parameter,"StartAgents")==0)
		{
			i=atoi(value);
			if( (i<1) || (i>16) )
			{
				zabbix_log( LOG_LEVEL_CRIT, "Wrong value of StartAgents in line %d. Should be between 1 and 16.", lineno);
				fclose(file);
				exit(1);
			}
			CONFIG_AGENTD_FORKS=i;
		}
		else if(strcmp(parameter,"ListenPort")==0)
		{
			i=atoi(value);
			if( (i<=1024) || (i>32767) )
			{
				zabbix_log( LOG_LEVEL_CRIT, "Wrong value of ListenPort in line %d. Should be between 1024 and 32767.", lineno);
				fclose(file);
				exit(1);
			}
			CONFIG_LISTEN_PORT=i;
		}
		else if(strcmp(parameter,"DebugLevel")==0)
		{
			if(strcmp(value,"1") == 0)
			{
//				setlogmask(LOG_LEVEL_UPTO(LOG_CRIT));
			}
			else if(strcmp(value,"2") == 0)
			{
//				setlogmask(LOG_UPTO(LOG_WARNING));
			}
			else if(strcmp(value,"3") == 0)
			{
//				setlogmask(LOG_UPTO(LOG_DEBUG));
				zabbix_log( LOG_LEVEL_WARNING, "DebugLevel -[%s]",value);
				zabbix_log( LOG_LEVEL_DEBUG, "DebugLevel --[%s]",value);
			}
			else
			{
				zabbix_log( LOG_LEVEL_CRIT, "Wrong DebugLevel in line %d", lineno);
				fclose(file);
				exit(1);
			}
			zabbix_log( LOG_LEVEL_WARNING, "DebugLevel is set to [%s]", value);
		}
		else if(strcmp(parameter,"UserParameter")==0)
		{
			value2=strstr(value,",");
			if(NULL == value2)
			{
				zabbix_log( LOG_LEVEL_CRIT, "Error in line [%s] Line %d Symbol ',' expected", line, lineno);
				fclose(file);
				exit(1);
			}
			value2[0]=0;
			value2++;
			zabbix_log( LOG_LEVEL_WARNING, "Added user-defined parameter [%s] Command [%s]", value, value2);
			add_user_parameter(value, value2);
		}
		else
		{
			zabbix_log( LOG_LEVEL_CRIT, "Unsupported parameter [%s] Line %d", parameter, lineno);
			fclose(file);
			exit(1);
		}

	}

	if(CONFIG_PID_FILE == NULL)
	{
		CONFIG_PID_FILE=strdup("/tmp/zabbix_agentd.pid");
	}
	fclose(file);
}

int	check_security(int sockfd)
{
	char	*sname;
	struct	sockaddr_in name;
	int	i;

	i=sizeof(name);

	if(getpeername(sockfd,  (struct sockaddr *)&name, (size_t *)&i) == 0)
	{
		i=sizeof(struct sockaddr_in);

		sname=inet_ntoa(name.sin_addr);

		zabbix_log( LOG_LEVEL_DEBUG, "Connection from [%s]. Allowed server is [%s] ",sname, CONFIG_HOST_ALLOWED);
		if(strcmp(sname, CONFIG_HOST_ALLOWED)!=0)
		{
			zabbix_log( LOG_LEVEL_WARNING, "Connection from [%s] rejected. Allowed server is [%s] ",sname, CONFIG_HOST_ALLOWED);
			return	FAIL;
		}
	}
	else
	{
		zabbix_log( LOG_LEVEL_WARNING, "Error getpeername [%s]",strerror(errno));
		zabbix_log( LOG_LEVEL_WARNING, "Connection rejected");
		return FAIL;
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

	alarm(CONFIG_TIMEOUT);

	zabbix_log( LOG_LEVEL_DEBUG, "Before read()");
	if( (nread = read(sockfd, line, 1024)) < 0)
	{
		if(errno == EINTR)
		{
			zabbix_log( LOG_LEVEL_DEBUG, "Read timeout");
		}
		else
		{
			zabbix_log( LOG_LEVEL_DEBUG, "read() failed.");
		}
		zabbix_log( LOG_LEVEL_DEBUG, "After read() 1");
		alarm(0);
		return;
	}
	zabbix_log( LOG_LEVEL_DEBUG, "After read() 2 [%d]",nread);

	line[nread-1]=0;

	zabbix_log( LOG_LEVEL_DEBUG, "Got line:%s", line);

	res=process(line);

	sprintf(result,"%f",res);
	zabbix_log( LOG_LEVEL_DEBUG, "Sending back:%s", result);
	write(sockfd,result,strlen(result));

	alarm(0);
}

int	tcp_listen(const char *host, int port, socklen_t *addrlenp)
{
	int			sockfd;
	struct sockaddr_in	serv_addr;

	struct linger ling;

	if ( (sockfd = socket(AF_INET, SOCK_STREAM, 0)) < 0)
	{
		zabbix_log( LOG_LEVEL_CRIT, "Unable to create socket");
		exit(1);
	}

	if(CONFIG_NOTIMEWAIT == 1)
	{
		ling.l_onoff=1;
	        ling.l_linger=0;
		if(setsockopt(sockfd,SOL_SOCKET,SO_LINGER,&ling,sizeof(ling))==-1)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Cannot setsockopt SO_LINGER [%s]", strerror(errno));
		}
	}


	bzero((char *) &serv_addr, sizeof(serv_addr));
	serv_addr.sin_family      = AF_INET;
	serv_addr.sin_addr.s_addr = htonl(INADDR_ANY);
	serv_addr.sin_port        = htons(port);

	if (bind(sockfd, (struct sockaddr *) &serv_addr, sizeof(serv_addr)) < 0)
	{
		zabbix_log( LOG_LEVEL_CRIT, "Cannot bind to port %d. Another zabbix_agentd already running ?", port);
		exit(1);
	}

	if(listen(sockfd, LISTENQ) != 0)
	{
		zabbix_log( LOG_LEVEL_CRIT, "Listen failed");
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

	zabbix_log( LOG_LEVEL_WARNING, "zabbix_agentd %ld started",(long)getpid());

	for(;;)
	{
		clilen = addrlen;
#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("waiting for connection. Requests [%d]", stats_request++);
#endif
		connfd=accept(listenfd,cliaddr, &clilen);
#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("processing request");
#endif
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

        static struct  sigaction phan;

	daemon_init();

	phan.sa_handler = &signal_handler;
	sigemptyset(&phan.sa_mask);
	phan.sa_flags = 0;
	sigaction(SIGINT, &phan, NULL);
	sigaction(SIGQUIT, &phan, NULL);
	sigaction(SIGTERM, &phan, NULL);

	process_config_file();

	create_pid_file();

	zabbix_log( LOG_LEVEL_WARNING, "zabbix_agentd started");

	if(gethostname(host,127) != 0)
	{
		zabbix_log( LOG_LEVEL_CRIT, "gethostname() failed");
		exit(FAIL);
	}

	listenfd = tcp_listen(host,CONFIG_LISTEN_PORT,&addrlen);

	pids = calloc(CONFIG_AGENTD_FORKS, sizeof(pid_t));

	for(i = 0; i<CONFIG_AGENTD_FORKS; i++)
	{
		pids[i] = child_make(i, listenfd, addrlen);
/*		zabbix_log( LOG_LEVEL_WARNING, "zabbix_agentd #%d started", pids[i]);*/
	}

	parent=1;

/* For parent only. To avoid problems with EXECUTE */
	sigaction(SIGCHLD, &phan, NULL);

#ifdef HAVE_FUNCTION_SETPROCTITLE
	setproctitle("main process");
#endif
	for(;;)
	{
			pause();
	}

	return SUCCEED;
}
