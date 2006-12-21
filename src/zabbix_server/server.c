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

#include <stdio.h>
#include <stdlib.h>
#include <sys/stat.h>

#include <string.h>


/* Required for getpwuid */
#include <pwd.h>

#include <signal.h>
#include <errno.h>

#include <time.h>
/* getopt() */
#include <unistd.h>

#include <sys/types.h>
#include <sys/socket.h>
#include <netinet/in.h>

#include "cfg.h"
#include "pid.h"
#include "db.h"
#include "log.h"
#include "zlog.h"

#include "common.h"
#include "functions.h"
#include "expression.h"
#include "sysinfo.h"

#include "alerter/alerter.h"
#include "housekeeper/housekeeper.h"
#include "pinger/pinger.h"
#include "poller/poller.h"
#include "poller/checks_snmp.h"
#include "timer/timer.h"
#include "trapper/trapper.h"

#define       LISTENQ 1024

char *progname = NULL;
char title_message[] = "ZABBIX Server (daemon)";
char usage_message[] = "[-hv] [-c <file>]";

#ifndef HAVE_GETOPT_LONG
char *help_message[] = {
        "Options:",
        "  -c <file>       Specify configuration file",
        "  -h              give this help",
        "  -v              display version number",
        0 /* end of text */
};
#else
char *help_message[] = {
        "Options:",
        "  -c --config <file>    Specify configuration file",
        "  -h --help             give this help",
        "  -v --version          display version number",
        0 /* end of text */
};
#endif

struct option longopts[] =
{
	{"config",	1,	0,	'c'},
	{"help",	0,	0,	'h'},
	{"version",	0,	0,	'v'},
	{0,0,0,0}
};


pid_t	*pids=NULL;

int	server_num=0;

int	CONFIG_POLLER_FORKS		= POLLER_FORKS;
/* For trapper */
int	CONFIG_TRAPPERD_FORKS		= TRAPPERD_FORKS;
int	CONFIG_LISTEN_PORT		= 10051;
char	*CONFIG_LISTEN_IP		= NULL;
int	CONFIG_TRAPPER_TIMEOUT		= TRAPPER_TIMEOUT;
/**/
/*int	CONFIG_NOTIMEWAIT		=0;*/
int	CONFIG_TIMEOUT			= POLLER_TIMEOUT;
int	CONFIG_HOUSEKEEPING_FREQUENCY	= 1;
int	CONFIG_SENDER_FREQUENCY		= 30;
int	CONFIG_PINGER_FREQUENCY		= 60;
int	CONFIG_DISABLE_PINGER		= 0;
int	CONFIG_DISABLE_HOUSEKEEPING	= 0;
int	CONFIG_UNREACHABLE_PERIOD	= 45;
int	CONFIG_UNREACHABLE_DELAY	= 15;
int	CONFIG_UNAVAILABLE_DELAY	= 60;
int	CONFIG_LOG_LEVEL		= LOG_LEVEL_WARNING;
char	*CONFIG_FILE			= NULL;
char	*CONFIG_PID_FILE		= NULL;
char	*CONFIG_LOG_FILE		= NULL;
char	*CONFIG_ALERT_SCRIPTS_PATH	= NULL;
char	*CONFIG_FPING_LOCATION		= NULL;
char	*CONFIG_DBHOST			= NULL;
char	*CONFIG_DBNAME			= NULL;
char	*CONFIG_DBUSER			= NULL;
char	*CONFIG_DBPASSWORD		= NULL;
char	*CONFIG_DBSOCKET		= NULL;
int	CONFIG_DBPORT			= 3306;
int	CONFIG_ENABLE_REMOTE_COMMANDS	= 0;

/* From table config */
int	CONFIG_REFRESH_UNSUPPORTED	= 0;

/******************************************************************************
 *                                                                            *
 * Function: uninit                                                           *
 *                                                                            *
 * Purpose: kill all child processes, if any, and exit                        *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	uninit(void)
{
	int i;

	if(server_num == 0)
	{
		if(pids != NULL)
		{
			for(i=0;i<CONFIG_POLLER_FORKS+CONFIG_TRAPPERD_FORKS-1;i++)
			{
				if(kill(pids[i],SIGTERM) !=0 )
				{
					zabbix_log( LOG_LEVEL_WARNING, "Cannot kill process. PID=[%d] [%s]", pids[i], strerror(errno));
				}
				else
				{
					zabbix_log( LOG_LEVEL_DEBUG, "%d. Killing PID=[%d]", i, pids[i]);
				}
			}
		}

		if(unlink(CONFIG_PID_FILE) != 0)
		{
			zabbix_log( LOG_LEVEL_DEBUG, "Cannot remove PID file [%s] [%s]",
				CONFIG_PID_FILE, strerror(errno));
		}
		zabbix_log( LOG_LEVEL_CRIT, "ZABBIX server is down.");
	}
	exit(FAIL);
}

/******************************************************************************
 *                                                                            *
 * Function: signal_handler                                                   *
 *                                                                            *
 * Purpose: handle signals                                                    *
 *                                                                            *
 * Parameters: sig - signal id                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	signal_handler( int sig )
{
	if( SIGALRM == sig )
	{
		signal( SIGALRM, signal_handler );
 
		zabbix_log( LOG_LEVEL_DEBUG, "Timeout while executing operation." );
	}
	else if( SIGQUIT == sig || SIGINT == sig || SIGTERM == sig || SIGPIPE == sig )
	{
		zabbix_log( LOG_LEVEL_DEBUG, "Server [%d]. Got QUIT or INT or TERM or PIPE signal. Exiting...", server_num );
		uninit();
	}
        else if( (SIGCHLD == sig) && (server_num == 0) )
	{
		zabbix_log( LOG_LEVEL_CRIT, "One server process died. Shutting down...");
		uninit();
	}
/*	else if( SIGCHLD == sig )
	{
		zabbix_log( LOG_LEVEL_ERR, "One child died. Exiting ..." );
		uninit();
		exit( FAIL );
	}*/
	else if( SIGPIPE == sig)
	{
		zabbix_log( LOG_LEVEL_WARNING, "Got SIGPIPE. Where it came from???");
	}
	else
	{
		zabbix_log( LOG_LEVEL_WARNING, "Got signal [%d]. Ignoring ...", sig);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: daemon-init                                                      *
 *                                                                            *
 * Purpose: init process as daemon                                            *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: it doesn't allow running under 'root'                            *
 *                                                                            *
 ******************************************************************************/
void	daemon_init(void)
{
	int		i;
	pid_t		pid;
	struct passwd	*pwd;

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
			fprintf(stderr,"Cannot setgid or setuid to zabbix [%s]", strerror(errno));
			exit(FAIL);
		}

#ifdef HAVE_FUNCTION_SETEUID
		if( setegid(pwd->pw_gid) ==-1)
		{
			fprintf(stderr,"Cannot setegid to zabbix [%s]\n", strerror(errno));
			exit(FAIL);
		}
		if( seteuid(pwd->pw_uid) ==-1)
		{
			fprintf(stderr,"Cannot seteuid to zabbix [%s]\n", strerror(errno));
			exit(FAIL);
		}
#endif
/*		fprintf(stderr,"UID [%d] GID [%d] EUID[%d] GUID[%d]\n", getuid(), getgid(), geteuid(), getegid());*/

	}

	if(CONFIG_LOG_FILE == NULL)
	{
		zabbix_open_log(LOG_TYPE_SYSLOG,CONFIG_LOG_LEVEL,NULL);
	}
	else
	{
		zabbix_open_log(LOG_TYPE_FILE,CONFIG_LOG_LEVEL,CONFIG_LOG_FILE);
	}

	if( (pid = zbx_fork()) != 0 )
	{
		exit( 0 );
	}
	setsid();

	signal( SIGHUP, SIG_IGN );

	if( (pid = zbx_fork()) !=0 )
	{
		exit( 0 );
	}

	chdir("/");

/*	umask(0022);*/
	umask(0002);

	for(i=0; i<MAXFD; i++)	close(i);

	open("/dev/null", O_RDONLY);    /* stdin */

	if(CONFIG_LOG_FILE)
	{
		fopen(CONFIG_LOG_FILE, "a+");   /* stdout */
		fopen(CONFIG_LOG_FILE, "a+");   /* stderr */
	}
	else
	{
		open("/dev/null", O_RDWR);      /* stdout */
		open("/dev/null", O_RDWR);      /* stderr */
	}
}

/******************************************************************************
 *                                                                            *
 * Function: init_config                                                      *
 *                                                                            *
 * Purpose: parse config file and update configuration parameters             *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: will terminate process if parsing fails                          *
 *                                                                            *
 ******************************************************************************/
void	init_config(void)
{
	static struct cfg_line cfg[]=
	{
/*		 PARAMETER	,VAR	,FUNC,	TYPE(0i,1s),MANDATORY,MIN,MAX	*/
		{"StartPollers",&CONFIG_POLLER_FORKS,0,TYPE_INT,PARM_OPT,6,255},
		{"HousekeepingFrequency",&CONFIG_HOUSEKEEPING_FREQUENCY,0,TYPE_INT,PARM_OPT,1,24},
		{"SenderFrequency",&CONFIG_SENDER_FREQUENCY,0,TYPE_INT,PARM_OPT,5,3600},
		{"PingerFrequency",&CONFIG_PINGER_FREQUENCY,0,TYPE_INT,PARM_OPT,1,3600},
		{"FpingLocation",&CONFIG_FPING_LOCATION,0,TYPE_STRING,PARM_OPT,0,0},
		{"Timeout",&CONFIG_TIMEOUT,0,TYPE_INT,PARM_OPT,1,30},
		{"StartTrappers",&CONFIG_TRAPPERD_FORKS,0,TYPE_INT,PARM_OPT,2,255},
		{"TrapperTimeout",&CONFIG_TRAPPER_TIMEOUT,0,TYPE_INT,PARM_OPT,1,30},
		{"UnreachablePeriod",&CONFIG_UNREACHABLE_PERIOD,0,TYPE_INT,PARM_OPT,1,3600},
		{"UnreachableDelay",&CONFIG_UNREACHABLE_DELAY,0,TYPE_INT,PARM_OPT,1,3600},
		{"UnavailableDelay",&CONFIG_UNAVAILABLE_DELAY,0,TYPE_INT,PARM_OPT,1,3600},
		{"ListenIP",&CONFIG_LISTEN_IP,0,TYPE_STRING,PARM_OPT,0,0},
		{"ListenPort",&CONFIG_LISTEN_PORT,0,TYPE_INT,PARM_OPT,1024,32768},
/*		{"NoTimeWait",&CONFIG_NOTIMEWAIT,0,TYPE_INT,PARM_OPT,0,1},*/
		{"DisablePinger",&CONFIG_DISABLE_PINGER,0,TYPE_INT,PARM_OPT,0,1},
		{"DisableHousekeeping",&CONFIG_DISABLE_HOUSEKEEPING,0,TYPE_INT,PARM_OPT,0,1},
		{"DebugLevel",&CONFIG_LOG_LEVEL,0,TYPE_INT,PARM_OPT,0,4},
		{"PidFile",&CONFIG_PID_FILE,0,TYPE_STRING,PARM_OPT,0,0},
		{"LogFile",&CONFIG_LOG_FILE,0,TYPE_STRING,PARM_OPT,0,0},
		{"AlertScriptsPath",&CONFIG_ALERT_SCRIPTS_PATH,0,TYPE_STRING,PARM_OPT,0,0},
		{"DBHost",&CONFIG_DBHOST,0,TYPE_STRING,PARM_OPT,0,0},
		{"DBName",&CONFIG_DBNAME,0,TYPE_STRING,PARM_MAND,0,0},
		{"DBUser",&CONFIG_DBUSER,0,TYPE_STRING,PARM_OPT,0,0},
		{"DBPassword",&CONFIG_DBPASSWORD,0,TYPE_STRING,PARM_OPT,0,0},
		{"DBSocket",&CONFIG_DBSOCKET,0,TYPE_STRING,PARM_OPT,0,0},
		{"DBPort",&CONFIG_DBPORT,0,TYPE_INT,PARM_OPT,1024,65535},
		{0}
	};


	if(CONFIG_FILE == NULL)
	{
		CONFIG_FILE=strdup("/etc/zabbix/zabbix_server.conf");
	}

	parse_cfg_file(CONFIG_FILE,cfg);

	if(CONFIG_DBNAME == NULL)
	{
		zabbix_log( LOG_LEVEL_CRIT, "DBName not in config file");
		exit(1);
	}
	if(CONFIG_PID_FILE == NULL)
	{
		CONFIG_PID_FILE=strdup("/tmp/zabbix_server.pid");
	}
	if(CONFIG_ALERT_SCRIPTS_PATH == NULL)
	{
		CONFIG_ALERT_SCRIPTS_PATH=strdup("/home/zabbix/bin");
	}
	if(CONFIG_FPING_LOCATION == NULL)
	{
		CONFIG_FPING_LOCATION=strdup("/usr/sbin/fping");
	}

}

/*
void	trend(void)
{
	char		sql[MAX_STRING_LEN];
 
	DB_RESULT	result;
	DB_RESULT	result2;

	int		i,j;

	snprintf(sql,sizeof(sql)-1,"select itemid from items");
	result2 = DBselect(sql);
	for(i=0;i<DBnum_rows(result2);i++)
	{
		snprintf(sql,sizeof(sql)-1,"select clock-clock%%3600, count(*),min(value),avg(value),max(value) from history where itemid=%d group by 1",atoi(DBget_field(result2,i,0)));
		result = DBselect(sql);
	
		for(j=0;j<DBnum_rows(result);j++)
		{
			snprintf(sql,sizeof(sql)-1,"insert into trends (itemid, clock, num, value_min, value_avg, value_max) values (%d,%d,%d,%f,%f,%f)",atoi(DBget_field(result2,i,0)), atoi(DBget_field(result,j,0)),atoi(DBget_field(result,j,1)),atof(DBget_field(result,j,2)),atof(DBget_field(result,j,3)),atof(DBget_field(result,j,4)));
			DBexecute(sql);
		}
		DBfree_result(result);
	}
	DBfree_result(result2);
}
*/

int	tcp_listen(const char *host, int port, socklen_t *addrlenp)
{
	int	sockfd;
	struct	sockaddr_in      serv_addr;
	int	on;
/*	struct linger ling;*/

	if ( (sockfd = socket(AF_INET, SOCK_STREAM, 0)) == -1)
	{
		zabbix_log( LOG_LEVEL_CRIT, "Cannot create socket");
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

/*	if(CONFIG_NOTIMEWAIT == 1)
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
		zabbix_log( LOG_LEVEL_CRIT, "Cannot bind to port %d. Another zabbix_server running? Shutting down...", port);
		uninit();
	}
	
	if(listen(sockfd, LISTENQ) !=0 )
	{
		zabbix_log( LOG_LEVEL_CRIT, "listen() failed");
		uninit();
	}

	*addrlenp = sizeof(serv_addr);

	return  sockfd;
}

/******************************************************************************
 *                                                                            *
 * Function: test                                                             *
 *                                                                            *
 * Purpose: test a custom developed functions                                 *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
/*
#define USE_TEST_FUNCTION 1 
*/

#ifdef USE_TEST_FUNCTION

void    run_commands(DB_TRIGGER *trigger,DB_ACTION *action);

void test()
{
	printf("-= Test Started =-\n");

	printf("-= Test completed =-\n");
}
#endif

/******************************************************************************
 *                                                                            *
 * Function: main                                                             *
 *                                                                            *
 * Purpose: executes server processes                                         *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int main(int argc, char **argv)
{
	int	ch;
	int	i;
	pid_t	pid;

	struct	sigaction phan;

	int		listenfd;
	socklen_t	addrlen;

	char		host[128];

	char sql[MAX_STRING_LEN];
	DB_RESULT	result;
	DB_ROW		row;
#ifdef HAVE_ZZZ
	const char ** v;
#endif



#ifdef HAVE_ZZZ
	init_config();

	DBconnect();
//	result = DBselect("select * from history where itemid=20272");
	result = DBselect("select NULL from history where itemid=20272222");
	row=DBfetch(result);
	if(!row) printf("OK");
	exit(0);
	while((row=DBfetch(result)))
	{
		printf("[%s]\n",row[0]);
	}
	DBfree_result(result);
	DBclose();
	return 0;
#endif
#ifdef HAVE_ZZZ
/* */
	DBconnect();
/*	DBexecute("update history set value=value-10 where itemid=20272");*/
	result = DBselect("select itemid,key_,description from items");
	while ( SQLO_SUCCESS == sqlo_fetch(result, 1))
	{
		v = sqlo_values(result, NULL, 1);
		printf("%s %s %s\n",v[0],v[1],v[2]);
	}
	DBfree_result(result);
	DBclose();
/* */
	return 0;
#endif

	progname = argv[0];

/* Parse the command-line. */
	while ((ch = getopt_long(argc, argv, "c:hv",longopts,NULL)) != EOF)
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
		default:
			usage();
			exit(-1);
			break;
        }

	init_metrics();

	init_config();

#ifdef USE_TEST_FUNCTION
	if(CONFIG_LOG_FILE == NULL)
	{
		zabbix_open_log(LOG_TYPE_SYSLOG,CONFIG_LOG_LEVEL,NULL);
	}
	else
	{
		zabbix_open_log(LOG_TYPE_FILE,CONFIG_LOG_LEVEL,CONFIG_LOG_FILE);
	}

	zabbix_log( LOG_LEVEL_WARNING, "Starting zabbix_server. ZABBIX %s.", ZABBIX_VERSION);
	DBconnect();
	test();
	DBclose();
	return 0;
#endif
	
	daemon_init();

	phan.sa_handler = &signal_handler; /* set up sig handler using sigaction() */
	sigemptyset(&phan.sa_mask);
	phan.sa_flags = 0;
	sigaction(SIGINT, &phan, NULL);
	sigaction(SIGQUIT, &phan, NULL);
	sigaction(SIGTERM, &phan, NULL);
	sigaction(SIGPIPE, &phan, NULL);

/* Moved to daemon_init() */
/*	if(CONFIG_LOG_FILE == NULL)
	{
		zabbix_open_log(LOG_TYPE_SYSLOG,CONFIG_LOG_LEVEL,NULL);
	}
	else
	{
		zabbix_open_log(LOG_TYPE_FILE,CONFIG_LOG_LEVEL,CONFIG_LOG_FILE);
	}*/

	if( FAIL == create_pid_file(CONFIG_PID_FILE))
	{
		exit(FAIL);
	}

	zabbix_log( LOG_LEVEL_WARNING, "Starting zabbix_server. ZABBIX %s.", ZABBIX_VERSION);

	DBconnect();

	snprintf(sql,sizeof(sql)-1,"select refresh_unsupported from config");
	result = DBselect(sql);
	row = DBfetch(result);

	if(row && DBis_null(row[0]) != SUCCEED)
	{
		CONFIG_REFRESH_UNSUPPORTED = atoi(row[0]);
	}
	DBfree_result(result);

/* Need to set trigger status to UNKNOWN since last run */
/* DBconnect() already made in init_config() */
/*	DBconnect();*/
	DBupdate_triggers_status_after_restart();

/*#define CALC_TREND*/

#ifdef CALC_TREND
	trend();
	return 0;
#endif
	DBclose();
	pids=calloc(CONFIG_POLLER_FORKS+CONFIG_TRAPPERD_FORKS-1,sizeof(pid_t));

	for(i=1;i<CONFIG_POLLER_FORKS;i++)
	{
		if((pid = zbx_fork()) == 0)
		{
			server_num=i;
			break;
		}
		else
		{
			pids[i-1]=pid;
		}
	}

/*	zabbix_log( LOG_LEVEL_WARNING, "zabbix_server #%d started",server_num);*/

	if( server_num == 0)
	{
		sigaction(SIGCHLD, &phan, NULL);
/* Run trapper processes then do housekeeping */
		if(gethostname(host,127) != 0)
		{
			zabbix_log( LOG_LEVEL_CRIT, "gethostname() failed");
			exit(FAIL);
		}

		listenfd = tcp_listen(host,CONFIG_LISTEN_PORT,&addrlen);

		for(i = CONFIG_POLLER_FORKS; i< CONFIG_POLLER_FORKS+CONFIG_TRAPPERD_FORKS; i++)
		{
			pids[i-1] = child_trapper_make(i, listenfd, addrlen);
		}

/* First instance of zabbix_server performs housekeeping procedures */
		zabbix_log( LOG_LEVEL_WARNING, "server #%d started [Housekeeper]",server_num);

		for(i=0;i<CONFIG_POLLER_FORKS+CONFIG_TRAPPERD_FORKS-1;i++)
		{
				zabbix_log( LOG_LEVEL_DEBUG, "%d. PID=[%d]", i, pids[i]);
		}
		zabbix_log( LOG_LEVEL_CRIT, "ZABBIX server is up.");

		main_housekeeper_loop();
	}
	else if(server_num == 1)
	{
/* Second instance of zabbix_server sends alerts to users */
		zabbix_log( LOG_LEVEL_WARNING, "server #%d started [Alerter]",server_num);
		main_alerter_loop();
	}
	else if(server_num == 2)
	{
/* Third instance of zabbix_server periodically re-calculates time-related functions */
		zabbix_log( LOG_LEVEL_WARNING, "server #%d started [Timer]",server_num);
		main_timer_loop();
	}
	else if(server_num == 3)
	{
/* Fourth instance of zabbix_server periodically pings hosts */
		zabbix_log( LOG_LEVEL_WARNING, "server #%d started [ICMP pinger]",server_num);
		main_pinger_loop();
	}
	else if(server_num == 4)
	{
#ifdef HAVE_SNMP
		init_snmp("zabbix_server");
		zabbix_log( LOG_LEVEL_WARNING, "server #%d started [Poller for unreachable hosts. SNMP:ON]",server_num);
#else
		zabbix_log( LOG_LEVEL_WARNING, "server #%d started [Poller for unreachable hosts. SNMP:OFF]",server_num);
#endif

		main_poller_loop();
	}
	else
	{
#ifdef HAVE_SNMP
		init_snmp("zabbix_server");
		zabbix_log( LOG_LEVEL_WARNING, "server #%d started [Poller. SNMP:ON]",server_num);
#else
		zabbix_log( LOG_LEVEL_WARNING, "server #%d started [Poller. SNMP:OFF]",server_num);
#endif

		main_poller_loop();
	}

	return SUCCEED;
}
