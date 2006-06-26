/* 
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

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
#include "security.h"
#include "zabbix_agent.h"

#include "pid.h"
#include "log.h"
#include "cfg.h"
#include "stats.h"
#include "active.h"

#define	LISTENQ 1024

char *progname = NULL;
char title_message[] = "ZABBIX Agent (daemon)";
char usage_message[] = "[-vhp] [-c <file>] [-t <metric>]";
#ifndef HAVE_GETOPT_LONG
char *help_message[] = {
	"Options:",
	"  -c <file>    Specify configuration file",
	"  -h           give this help",
	"  -v           display version number",
	"  -p           print supported metrics and exit",
	"  -t <metric>  test specified metric and exit",
	0 /* end of text */
};
#else
char *help_message[] = {
	"Options:",
	"  -c --config <file>  Specify configuration file",
	"  -h --help           give this help",
	"  -v --version        display version number",
	"  -p --print          print supported metrics and exit",
	"  -t --test <metric>  test specified metric and exit",
	0 /* end of text */
};
#endif

struct option longopts[] =
{
	{"config",	1,	0,	'c'},
	{"help",	0,	0,	'h'},
	{"version",	0,	0,	'v'},
	{"print",	0,	0,	'p'},
	{"test",	1,	0,	't'},
	{0,0,0,0}
};

static	pid_t	*pids=NULL;
int	parent=0;
/* Number of processed requests */
int	stats_request=0;


char	*CONFIG_HOSTS_ALLOWED		= NULL;
char	*CONFIG_HOSTNAME		= NULL;
char	*CONFIG_FILE			= NULL;
char	*CONFIG_PID_FILE		= NULL;
char	*CONFIG_LOG_FILE		= NULL;
int	CONFIG_AGENTD_FORKS		= AGENTD_FORKS;
/*int	CONFIG_NOTIMEWAIT		= 0;*/
int	CONFIG_DISABLE_ACTIVE		= 0;
int	CONFIG_ENABLE_REMOTE_COMMANDS	= 0;
int	CONFIG_TIMEOUT			= AGENT_TIMEOUT;
int	CONFIG_LISTEN_PORT		= 10050;
int	CONFIG_SERVER_PORT		= 10051;
int	CONFIG_REFRESH_ACTIVE_CHECKS	= 120;
char	*CONFIG_LISTEN_IP		= NULL;
int	CONFIG_LOG_LEVEL		= LOG_LEVEL_WARNING;

void    init_config(void);

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
/* parent==1 is mandatory ! EXECUTE sends SIGCHLD as well (?) ... */
	else if( (SIGCHLD == sig) && (parent == 1) )
	{
		zabbix_log( LOG_LEVEL_WARNING, "One child process died. Exiting ...");
		uninit();
		exit( FAIL );
	}
	else if( SIGPIPE == sig)
	{
		zabbix_log( LOG_LEVEL_WARNING, "Got SIGPIPE. Where it came from???");
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
	if((getuid()==0) || (getgid()==0))
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
			fprintf(stderr,"Cannot setgid or setuid to zabbix [%s]\n", strerror(errno));
			exit(FAIL);
		}

#ifdef HAVE_FUNCTION_SETEUID
		if( (setegid(pwd->pw_gid) ==-1) || (seteuid(pwd->pw_uid) == -1) )
		{
			fprintf(stderr,"Cannot setegid or seteuid to zabbix [%s]\n", strerror(errno));
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
	umask(022);

	for(i=0;i<MAXFD;i++)
	{
		/* Do not close stderr */
		if(i != fileno(stderr)) close(i);
	}

/*	openlog("zabbix_agentd",LOG_LEVEL_PID,LOG_USER);
	setlogmask(LOG_UPTO(LOG_WARNING));*/


#ifdef HAVE_SYS_RESOURCE_SETPRIORITY
	if(setpriority(PRIO_PROCESS,0,5)!=0)
	{
		fprintf(stderr, "Unable to set process priority to 5. Leaving default.\n");
	}
#endif

}

int     add_parameter(char *value)
{
	char    *value2;

	value2=strstr(value,",");
	if(NULL == value2)
	{
		return  FAIL;
	}
	value2[0]=0;
	value2++;
	add_user_parameter(value, value2);
	return  SUCCEED;
}

void    init_config(void)
{
	struct cfg_line cfg[]=
	{
/*               PARAMETER      ,VAR    ,FUNC,  TYPE(0i,1s),MANDATORY,MIN,MAX
*/
		{"Server",&CONFIG_HOSTS_ALLOWED,0,TYPE_STRING,PARM_MAND,0,0},
		{"Hostname",&CONFIG_HOSTNAME,0,TYPE_STRING,PARM_OPT,0,0},
		{"PidFile",&CONFIG_PID_FILE,0,TYPE_STRING,PARM_OPT,0,0},
		{"LogFile",&CONFIG_LOG_FILE,0,TYPE_STRING,PARM_OPT,0,0},
/*		{"StatFile",&CONFIG_STAT_FILE,0,TYPE_STRING,PARM_OPT,0,0},*/
		{"DisableActive",&CONFIG_DISABLE_ACTIVE,0,TYPE_INT,PARM_OPT,0,1},
		{"EnableRemoteCommands",&CONFIG_ENABLE_REMOTE_COMMANDS,0,TYPE_INT,PARM_OPT,0,1},
		{"Timeout",&CONFIG_TIMEOUT,0,TYPE_INT,PARM_OPT,1,30},
/*		{"NoTimeWait",&CONFIG_NOTIMEWAIT,0,TYPE_INT,PARM_OPT,0,1},*/
		{"ListenPort",&CONFIG_LISTEN_PORT,0,TYPE_INT,PARM_OPT,1024,32767},
		{"ServerPort",&CONFIG_SERVER_PORT,0,TYPE_INT,PARM_OPT,1024,32767},
		{"ListenIP",&CONFIG_LISTEN_IP,0,TYPE_STRING,PARM_OPT,0,0},
		{"DebugLevel",&CONFIG_LOG_LEVEL,0,TYPE_INT,PARM_OPT,0,4},
		{"StartAgents",&CONFIG_AGENTD_FORKS,0,TYPE_INT,PARM_OPT,1,16},
		{"RefreshActiveChecks",&CONFIG_REFRESH_ACTIVE_CHECKS,0,TYPE_INT,PARM_OPT,60,3600},
		{0}
	};
	AGENT_RESULT	result;	

	memset(&result, 0, sizeof(AGENT_RESULT));
	
	if(CONFIG_FILE == NULL)
	{
		CONFIG_FILE = strdup("/etc/zabbix/zabbix_agentd.conf");
	}

	parse_cfg_file(CONFIG_FILE,cfg);

	if(CONFIG_PID_FILE == NULL)
	{
		CONFIG_PID_FILE=strdup("/tmp/zabbix_agentd.pid");
	}

	if(CONFIG_HOSTNAME == NULL)
	{
	  	if(SUCCEED == process("system.hostname", 0, &result))
		{
	        	if(result.type & AR_STRING)
			{
				CONFIG_HOSTNAME=strdup(result.str);
			}
		}
	        free_result(&result);

		if(CONFIG_HOSTNAME == NULL)
		{
			zabbix_log( LOG_LEVEL_CRIT, "Hostname is not defined");
			exit(1);
		}
	}

/*	if(CONFIG_STAT_FILE == NULL)
	{
		CONFIG_STAT_FILE=strdup("/tmp/zabbix_agentd.tmp");
	}*/
}

void    load_user_parameters(void)
{
	struct cfg_line cfg[]=
	{
/*               PARAMETER      ,VAR    ,FUNC,  TYPE(0i,1s),MANDATORY,MIN,MAX
*/
		{"UserParameter",0,&add_parameter,0,0,0,0},
		{0}
	};
	
	parse_cfg_file(CONFIG_FILE,cfg);
}

void	process_child(int sockfd)
{
	ssize_t	nread;
	char	line[MAX_STRING_LEN];
	char	value[MAX_STRING_LEN];
	int	i;

        static struct  	sigaction phan;
	AGENT_RESULT	result;

	memset(&result, 0, sizeof(AGENT_RESULT));
	
	phan.sa_handler = &signal_handler; /* set up sig handler using sigaction() */
	sigemptyset(&phan.sa_mask);
	phan.sa_flags = 0;
	sigaction(SIGALRM, &phan, NULL);


	alarm(CONFIG_TIMEOUT);

	zabbix_log( LOG_LEVEL_DEBUG, "Before read()");
	if( (nread = read(sockfd, line, MAX_STRING_LEN)) < 0)
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

  	process(line, 0, &result);
        if(result.type & AR_DOUBLE)
                 snprintf(value, MAX_STRING_LEN-1, "%f", result.dbl);
        else if(result.type & AR_UINT64)
                 snprintf(value, MAX_STRING_LEN-1, ZBX_FS_UI64, result.ui64);
        else if(result.type & AR_STRING)
                 snprintf(value, MAX_STRING_LEN-1, "%s", result.str);
        else if(result.type & AR_TEXT)
                 snprintf(value, MAX_STRING_LEN-1, "%s", result.text);
        else if(result.type & AR_MESSAGE)
                 snprintf(value, MAX_STRING_LEN-1, "%s", result.msg);
        free_result(&result);
					
	zabbix_log( LOG_LEVEL_DEBUG, "Sending back:%s", value);
	i=write(sockfd, value, strlen(value));
	if(i == -1)
	{
		zabbix_log( LOG_LEVEL_WARNING, "Error writing to socket [%s]",
			strerror(errno));
	}

	alarm(0);
}

int	tcp_listen(const char *host, int port, socklen_t *addrlenp)
{
	int			sockfd;
	struct sockaddr_in	serv_addr;
	int			on;

/*	struct linger ling;*/

	if ( (sockfd = socket(AF_INET, SOCK_STREAM, 0)) == -1)
	{
		zabbix_log( LOG_LEVEL_CRIT, "Unable to create socket");
		exit(1);
	}

	/* Enable address reuse */
	/* This is to immediately use the address even if it is in TIME_WAIT state */
	/* http://www-128.ibm.com/developerworks/linux/library/l-sockpit/index.html */
	on = 1;
	if( -1 == setsockopt( sockfd, SOL_SOCKET, SO_REUSEADDR, &on, sizeof(on) ))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Cannot setsockopt SO_REUSEADDR [%s]", strerror(errno));
	}

	/*
	if(CONFIG_NOTIMEWAIT == 1)
	{
		ling.l_onoff=1;
	        ling.l_linger=0;
		if(setsockopt(sockfd,SOL_SOCKET,SO_LINGER,&ling,sizeof(ling))==-1)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Cannot setsockopt SO_LINGER [%s]", strerror(errno));
		}
	}*/


	bzero((char *) &serv_addr, sizeof(serv_addr));
	serv_addr.sin_family      = AF_INET;
	if(CONFIG_LISTEN_IP == NULL)
	{
		serv_addr.sin_addr.s_addr = htonl(INADDR_ANY);
	}
	else
	{
		serv_addr.sin_addr.s_addr = inet_addr(CONFIG_LISTEN_IP);
	}
	serv_addr.sin_port        = htons(port);

	if (bind(sockfd, (struct sockaddr *) &serv_addr, sizeof(serv_addr)) < 0)
	{
		zabbix_log( LOG_LEVEL_CRIT, "Cannot bind to port %d. Error [%s]. Another zabbix_agentd already running ?",
				port, strerror(errno));
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

void	child_passive_main(int i,int listenfd, int addrlen)
{
	int	connfd;
	socklen_t	clilen;
	struct sockaddr cliaddr;

	zabbix_log( LOG_LEVEL_WARNING, "zabbix_agentd %ld started",(long)getpid());

	for(;;)
	{
		clilen = addrlen;
#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("waiting for connection. Requests [%d]", stats_request++);
#endif
		connfd=accept(listenfd,&cliaddr, &clilen);
#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("processing request");
#endif
		if( check_security(connfd, CONFIG_HOSTS_ALLOWED, 0) == SUCCEED)
		{
			process_child(connfd);
		}
		close(connfd);
	}
}

pid_t	child_passive_make(int i,int listenfd, int addrlen)
{
	pid_t	pid;

	if((pid = fork()) >0)
	{
			return (pid);
	}

	/* never returns */
	child_passive_main(i, listenfd, addrlen);

	/* avoid compilator warning */
	return 0;
}

int	main(int argc, char **argv)
{
	int		listenfd;
	socklen_t	addrlen;
	int		i;

	char		host[128];
	int		ch;
	char		*s;
	int		task = ZBX_TASK_START;
	char		*TEST_METRIC = NULL;

        static struct  sigaction phan;

	progname = argv[0];

/* Parse the command-line. */
	while ((ch = getopt_long(argc, argv, "c:hvpt:", longopts, NULL)) != EOF)
		switch ((char) ch) {
		case 'c':
			CONFIG_FILE = optarg;
			break;
		case 'h':
			help();
			exit(-1);
			break;
		case 'v':
			version();
			exit(-1);
			break;
		case 'p':
			if(task == ZBX_TASK_START)
				task = ZBX_TASK_PRINT_SUPPORTED;
			break;
		case 't':
			if(task == ZBX_TASK_START) 
			{
				task = ZBX_TASK_TEST_METRIC;
				TEST_METRIC = optarg;
			}
			break;
		default:
			task = ZBX_TASK_SHOW_USAGE;
			break;
	}

/* Must be before init_config() */
	init_metrics();
	init_config();
	
	if(CONFIG_LOG_FILE == NULL)
		zabbix_open_log(LOG_TYPE_SYSLOG,CONFIG_LOG_LEVEL,NULL);
	else
		zabbix_open_log(LOG_TYPE_FILE,CONFIG_LOG_LEVEL,CONFIG_LOG_FILE);
	
	load_user_parameters();
	
	switch(task)
	{
		case ZBX_TASK_PRINT_SUPPORTED:
			test_parameters();
			exit(-1);
			break;
		case ZBX_TASK_TEST_METRIC:
			test_parameter(TEST_METRIC);
			exit(-1);
			break;
		case ZBX_TASK_SHOW_USAGE:
			usage();
			exit(-1);
			break;
	}
	
	daemon_init();

	phan.sa_handler = &signal_handler;
	sigemptyset(&phan.sa_mask);
	phan.sa_flags = 0;
	sigaction(SIGINT, &phan, NULL);
	sigaction(SIGQUIT, &phan, NULL);
	sigaction(SIGTERM, &phan, NULL);
	sigaction(SIGPIPE, &phan, NULL);

	if( FAIL == create_pid_file(CONFIG_PID_FILE))
	{
		return -1;
	}

	zabbix_log( LOG_LEVEL_WARNING, "zabbix_agentd started. ZABBIX %s.", ZABBIX_VERSION);

	if(gethostname(host,127) != 0)
	{
		zabbix_log( LOG_LEVEL_CRIT, "gethostname() failed");
		exit(FAIL);
	}

	listenfd = tcp_listen(host,CONFIG_LISTEN_PORT,&addrlen);

	pids = calloc(CONFIG_AGENTD_FORKS, sizeof(pid_t));

	for(i = 0; i<CONFIG_AGENTD_FORKS-1; i++)
	{
		pids[i] = child_passive_make(i, listenfd, addrlen);
	}

	/* Initialize thread for active checks */
	if(CONFIG_DISABLE_ACTIVE==0)
	{
		s=strtok(CONFIG_HOSTS_ALLOWED,",");
		pids[CONFIG_AGENTD_FORKS-1] = child_active_make(CONFIG_AGENTD_FORKS-1, s, CONFIG_SERVER_PORT);
	}

	parent=1;

/* For parent only. To avoid problems with EXECUTE */
	sigaction(SIGCHLD, &phan, NULL);

#ifdef HAVE_FUNCTION_SETPROCTITLE
	setproctitle("main process");
#endif

	collect_statistics();

/*
#ifdef HAVE_PROC_NET_DEV
	collect_statistics();
#else
	for(;;)
	{
		pause();
	}
#endif
*/

	return SUCCEED;
}
