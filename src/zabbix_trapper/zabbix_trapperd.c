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

#include <pwd.h>

#include "common.h"
#include "db.h"
#include "functions.h"

#define	LISTENQ 1024

static pid_t *pids;

int	parent=0;

int	CONFIG_TRAPPERD_FORKS		= TRAPPERD_FORKS;
int	CONFIG_LISTEN_PORT		= 10001;
int	CONFIG_TIMEOUT			= TRAPPER_TIMEOUT;
char	*CONFIG_PID_FILE		= NULL;
char	*CONFIG_DBNAME			= NULL;
char	*CONFIG_DBUSER			= NULL;
char	*CONFIG_DBPASSWORD		= NULL;
char	*CONFIG_DBSOCKET		= NULL;

void	uninit(void)
{
	int i;

	if(parent == 1)
	{
		if(pids != NULL)
		{
			for(i = 0; i<CONFIG_TRAPPERD_FORKS; i++)
			{
				kill(pids[i],SIGTERM);
			}
		}

		if( unlink(CONFIG_PID_FILE) != 0)
		{
			syslog( LOG_WARNING, "Cannot remove PID file [%s]",
				CONFIG_PID_FILE);
		}
	}
}

void	create_pid_file(void)
{
	FILE	*f;

/* Check if PID file already exists */
	f = fopen(CONFIG_PID_FILE, "r");
	if(f != NULL)
	{
		syslog( LOG_CRIT, "File [%s] exists. Is zabbix_agentd already running ?",
			CONFIG_PID_FILE);
		fclose(f);
		exit(-1);
	}

	f = fopen(CONFIG_PID_FILE, "w");

	if( f == NULL)
	{
		syslog( LOG_CRIT, "Cannot create PID file [%s]. Errno [%d]",
			CONFIG_PID_FILE, errno);
		uninit();
		exit(-1);
	}

	fprintf(f,"%d",getpid());
	fclose(f);
}

void	signal_handler( int sig )
{
	if( SIGALRM == sig )
	{
		signal( SIGALRM, signal_handler );
		syslog( LOG_WARNING, "Timeout while answering request");
	}
	else if( SIGQUIT == sig || SIGINT == sig || SIGTERM == sig )
	{
		syslog( LOG_WARNING, "Got signal. Exiting ...");
		uninit();
		exit( FAIL );
	}
	else if( SIGCHLD == sig )
	{
		syslog( LOG_WARNING, "One child process died. Exiting ...");
		uninit();
		exit( FAIL );
	}
	else
	{
		syslog( LOG_WARNING, "Got signal [%d]. Ignoring ...", sig);
	}
}

void	process_config_file(void)
{
	FILE	*file;
	char	line[1024];
	char	parameter[1024];
	char	*value;
	int	lineno;
	int	i;


	file=fopen("/etc/zabbix/zabbix_trapperd.conf","r");
	if(NULL == file)
	{
		syslog( LOG_CRIT, "Cannot open /etc/zabbix/zabbix_trapperd.conf");
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
			syslog( LOG_CRIT, "Error in line [%s] Line %d", line, lineno);
			fclose(file);
			exit(1);
		}
		value++;
		value[strlen(value)-1]=0;

		parameter[value-line-1]=0;

		syslog( LOG_DEBUG, "Parameter [%s] Value [%s]", parameter, value);

		if(strcmp(parameter,"StartTrappers")==0)
		{
			i=atoi(value);
			if( (i<2) || (i>255) )
			{
				syslog( LOG_CRIT, "Wrong value of StartTrappers in line %d. Should be between 2 and 255.", lineno);
				fclose(file);
				exit(1);
			}
			CONFIG_TRAPPERD_FORKS=i;
		}
		else if(strcmp(parameter,"Timeout")==0)
		{
			i=atoi(value);
			if( (i<1) || (i>30) )
			{
				syslog( LOG_CRIT, "Wrong value of Timeout in line %d. Should be between 1 and 30.", lineno);
				fclose(file);
				exit(1);
			}
			CONFIG_TIMEOUT=i;
		}
		else if(strcmp(parameter,"ListenPort")==0)
		{
			i=atoi(value);
			if( (i<=1024) || (i>32767) )
			{
				syslog( LOG_CRIT, "Wrong value of ListenPort in line %d. Should be between 1024 and 32768.", lineno);
				fclose(file);
				exit(1);
			}
			CONFIG_LISTEN_PORT=i;
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
		else if(strcmp(parameter,"PidFile")==0)
		{
			CONFIG_PID_FILE=strdup(value);
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
			syslog( LOG_CRIT, "Unsupported parameter [%s] Line %d", parameter, lineno);
			fclose(file);
			exit(1);
		}
	}
	fclose(file);
	
	if(CONFIG_DBNAME == NULL)
	{
		syslog( LOG_CRIT, "DBName not in config file");
		exit(1);
	}
	if(CONFIG_PID_FILE == NULL)
	{
		CONFIG_PID_FILE=strdup("/tmp/zabbix_trapperd.pid");
	}
	// Check, if we are able to connect
	DBconnect(CONFIG_DBNAME, CONFIG_DBUSER, CONFIG_DBPASSWORD, CONFIG_DBSOCKET);
}

int	process(char *s)
{
	char	*p;
	char	*server,*key,*value_string;

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
//	???
//	value=atof(value_string);

	ret=process_data(server,key,value_string);

	return ret;
}

void    daemon_init(void)
{
	int     i;
	pid_t   pid;
	struct passwd *pwd;

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
			fprintf(stderr,"Cannot setgid or setuid to zabbix");
			exit(FAIL);
		}

#ifdef HAVE_FUNCTION_SETEUID
		if( (setegid(pwd->pw_gid) ==-1) || (seteuid(pwd->pw_uid) == -1) )
		{
			fprintf(stderr,"Cannot setegid or seteuid to zabbix");
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

	openlog("zabbix_trapperd",LOG_PID,LOG_USER);
/*	setlogmask(LOG_UPTO(LOG_DEBUG));*/ 
	setlogmask(LOG_UPTO(LOG_WARNING));
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

	alarm(CONFIG_TIMEOUT);

	syslog( LOG_DEBUG, "Before read()");
	if( (nread = read(sockfd, line, 1024)) < 0)
	{
		if(errno == EINTR)
		{
			syslog( LOG_DEBUG, "Read timeout");
		}
		else
		{
			syslog( LOG_DEBUG, "read() failed");
		}
		syslog( LOG_DEBUG, "After read() 1");
		alarm(0);
		return;
	}

	syslog( LOG_DEBUG, "After read() 2 [%d]",nread);

	line[nread-1]=0;

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
	alarm(0);
}

int	tcp_listen(const char *host, int port, socklen_t *addrlenp)
{
	int	sockfd;
	struct	sockaddr_in      serv_addr;

	if ( (sockfd = socket(AF_INET, SOCK_STREAM, 0)) < 0)
	{
		syslog( LOG_CRIT, "Cannot create socket");
		exit(1);
	}

	bzero((char *) &serv_addr, sizeof(serv_addr));
	serv_addr.sin_family      = AF_INET;
	serv_addr.sin_addr.s_addr = htonl(INADDR_ANY);
	serv_addr.sin_port        = htons(port);

	if (bind(sockfd, (struct sockaddr *) &serv_addr, sizeof(serv_addr)) < 0)
	{
		syslog( LOG_CRIT, "Cannot bind to port %d. Another zabbix_trapperd running ?", port);
		exit(1);
	}
	
	if(listen(sockfd, LISTENQ) !=0 )
	{
		syslog( LOG_CRIT, "listen() failed");
		exit(1);
	}

	*addrlenp = sizeof(serv_addr);

	return  sockfd;
}

void	child_main(int i,int listenfd, int addrlen)
{
	int	connfd;
	socklen_t	clilen;
	struct sockaddr *cliaddr;

	cliaddr=malloc(addrlen);

	syslog( LOG_WARNING, "zabbix_trapperd %ld started",(long)getpid());

	DBconnect(CONFIG_DBNAME, CONFIG_DBUSER, CONFIG_DBPASSWORD, CONFIG_DBSOCKET);

	for(;;)
	{
		clilen = addrlen;
#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("waiting for connection");
#endif
		connfd=accept(listenfd,cliaddr, &clilen);
#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("processing data");
#endif

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
	int		i;

	char		host[128];

	static struct  sigaction phan;

	daemon_init();

	process_config_file();

	create_pid_file();

	phan.sa_handler = &signal_handler;
	sigemptyset(&phan.sa_mask);
	phan.sa_flags = 0;
	sigaction(SIGINT, &phan, NULL);
	sigaction(SIGQUIT, &phan, NULL);
	sigaction(SIGTERM, &phan, NULL);
	sigaction(SIGCHLD, &phan, NULL);

	syslog( LOG_WARNING, "zabbix_trapperd started");

	if(gethostname(host,127) != 0)
	{
		syslog( LOG_CRIT, "gethostname() failed");
		exit(FAIL);
	}

	listenfd = tcp_listen(host,CONFIG_LISTEN_PORT,&addrlen);

	pids = calloc(CONFIG_TRAPPERD_FORKS, sizeof(pid_t));

	for(i = 0; i< CONFIG_TRAPPERD_FORKS; i++)
	{
		pids[i] = child_make(i, listenfd, addrlen);
/*		syslog( LOG_WARNING, "zabbix_trapperd #%d started", pids[i]);*/
	}

#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("main process");
#endif

	parent=1;

	for(;;)
	{
			pause();
	}

	return SUCCEED;
}
