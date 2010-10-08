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

#include "common.h"

#include "cfg.h"
#include "pid.h"
#include "db.h"
#include "dbcache.h"
#include "log.h"
#include "zlog.h"
#include "zbxgetopt.h"
#include "mutexs.h"

#include "sysinfo.h"
#include "zbxserver.h"

#include "daemon.h"

#include "alerter/alerter.h"
#include "dbsyncer/dbsyncer.h"
#include "dbconfig/dbconfig.h"
#include "discoverer/discoverer.h"
#include "httppoller/httppoller.h"
#include "housekeeper/housekeeper.h"
#include "pinger/pinger.h"
#include "poller/poller.h"
#include "poller/checks_ipmi.h"
#include "timer/timer.h"
#include "trapper/trapper.h"
#include "nodewatcher/nodewatcher.h"
#include "watchdog/watchdog.h"
#include "utils/nodechange.h"
#include "escalator/escalator.h"
#include "proxypoller/proxypoller.h"

const char	*progname = NULL;
const char	title_message[] = "Zabbix Server";
const char	usage_message[] = "[-hV] [-c <file>] [-n <nodeid>]";

#ifndef HAVE_GETOPT_LONG
const char	*help_message[] = {
        "Options:",
        "  -c <file>       Specify configuration file",
        "  -h              give this help",
        "  -n <nodeid>     convert database data to new nodeid",
        "  -V              display version number",
        0 /* end of text */
};
#else
const char	*help_message[] = {
        "Options:",
        "  -c --config <file>       Specify configuration file",
        "  -h --help                give this help",
        "  -n --new-nodeid <nodeid> convert database data to new nodeid",
        "  -V --version             display version number",
        0 /* end of text */
};
#endif

/* COMMAND LINE OPTIONS */

/* long options */

static struct zbx_option longopts[] =
{
	{"config",	1,	0,	'c'},
	{"help",	0,	0,	'h'},
	{"new-nodeid",	1,	0,	'n'},
	{"version",	0,	0,	'V'},
	{0,0,0,0}
};

/* short options */

static char	shortopts[] = "c:n:hV";

/* end of COMMAND LINE OPTIONS */

pid_t	*threads = NULL;

int	CONFIG_ALERTER_FORKS		= 1;
int	CONFIG_DISCOVERER_FORKS		= 1;
int	CONFIG_HOUSEKEEPER_FORKS	= 1;
int	CONFIG_NODEWATCHER_FORKS	= 1;
int	CONFIG_PINGER_FORKS		= 1;
int	CONFIG_POLLER_FORKS		= 5;
int	CONFIG_UNREACHABLE_POLLER_FORKS	= 1;
int	CONFIG_HTTPPOLLER_FORKS		= 1;
int	CONFIG_IPMIPOLLER_FORKS		= 0;
int	CONFIG_TIMER_FORKS		= 1;
int	CONFIG_TRAPPER_FORKS		= 5;
int	CONFIG_ESCALATOR_FORKS		= 1;

int	CONFIG_LISTEN_PORT		= 10051;
char	*CONFIG_LISTEN_IP		= NULL;
char	*CONFIG_SOURCE_IP		= NULL;
int	CONFIG_TRAPPER_TIMEOUT		= 300;

int	CONFIG_HOUSEKEEPING_FREQUENCY	= 1;
int	CONFIG_MAX_HOUSEKEEPER_DELETE	= 500;		/* applies for every separate field value */
int	CONFIG_SENDER_FREQUENCY		= 30;
int	CONFIG_DBSYNCER_FORKS		= 4;
int	CONFIG_DBSYNCER_FREQUENCY	= 5;
int	CONFIG_DBCONFIG_FORKS		= 1;
int	CONFIG_DBCONFIG_FREQUENCY	= 60;
int	CONFIG_DBCONFIG_SIZE		= 8388608;	/* 8MB */
int	CONFIG_HISTORY_CACHE_SIZE	= 8388608;	/* 8MB */
int	CONFIG_TRENDS_CACHE_SIZE	= 4194304;	/* 4MB */
int	CONFIG_TEXT_CACHE_SIZE		= 16777216;	/* 16MB */
int	CONFIG_DISABLE_HOUSEKEEPING	= 0;
int	CONFIG_UNREACHABLE_PERIOD	= 45;
int	CONFIG_UNREACHABLE_DELAY	= 15;
int	CONFIG_UNAVAILABLE_DELAY	= 60;
int	CONFIG_LOG_LEVEL		= LOG_LEVEL_WARNING;
char	*CONFIG_ALERT_SCRIPTS_PATH	= NULL;
char	*CONFIG_EXTERNALSCRIPTS		= NULL;
char	*CONFIG_TMPDIR			= NULL;
char	*CONFIG_FPING_LOCATION		= NULL;
#ifdef HAVE_IPV6
char	*CONFIG_FPING6_LOCATION		= NULL;
#endif /* HAVE_IPV6 */
char	*CONFIG_DBHOST			= NULL;
char	*CONFIG_DBNAME			= NULL;
char	*CONFIG_DBUSER			= NULL;
char	*CONFIG_DBPASSWORD		= NULL;
char	*CONFIG_DBSOCKET		= NULL;
int	CONFIG_DBPORT			= 0;
int	CONFIG_ENABLE_REMOTE_COMMANDS	= 0;
int	CONFIG_LOG_REMOTE_COMMANDS	= 0;
int	CONFIG_UNSAFE_USER_PARAMETERS	= 0;

int	CONFIG_NODEID			= 0;
int	CONFIG_MASTER_NODEID		= 0;
int	CONFIG_NODE_NOEVENTS		= 0;
int	CONFIG_NODE_NOHISTORY		= 0;

char	*CONFIG_SSH_KEY_LOCATION	= NULL;

int	CONFIG_LOG_SLOW_QUERIES		= 0;	/* ms; 0 - disable */

/* Global variable to control if we should write warnings to log[] */
int	CONFIG_ENABLE_LOG		= 1;

/* From table config */
int	CONFIG_REFRESH_UNSUPPORTED	= 0;

/* Zabbix server startup time */
int     CONFIG_SERVER_STARTUP_TIME      = 0;

/* Parameters for passive proxies */
int	CONFIG_PROXYPOLLER_FORKS	= 1;
/* How often Zabbix Server sends configuration data to Proxy in seconds */
int	CONFIG_PROXYCONFIG_FREQUENCY	= 3600; /* 1h */
int	CONFIG_PROXYDATA_FREQUENCY	= 1; /* 1s */

/* Mutex for node syncs */
ZBX_MUTEX	node_sync_access;

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
		{"StartDBSyncers",&CONFIG_DBSYNCER_FORKS,0,TYPE_INT,PARM_OPT,1,64},
		{"StartDiscoverers",&CONFIG_DISCOVERER_FORKS,0,TYPE_INT,PARM_OPT,0,255},
		{"StartHTTPPollers",&CONFIG_HTTPPOLLER_FORKS,0,TYPE_INT,PARM_OPT,0,255},
		{"StartPingers",&CONFIG_PINGER_FORKS,0,TYPE_INT,PARM_OPT,0,255},
		{"StartPollers",&CONFIG_POLLER_FORKS,0,TYPE_INT,PARM_OPT,0,255},
		{"StartPollersUnreachable",&CONFIG_UNREACHABLE_POLLER_FORKS,0,TYPE_INT,PARM_OPT,0,255},
		{"StartIPMIPollers",&CONFIG_IPMIPOLLER_FORKS,0,TYPE_INT,PARM_OPT,0,255},
		{"StartTrappers",&CONFIG_TRAPPER_FORKS,0,TYPE_INT,PARM_OPT,0,255},
		{"CacheSize",&CONFIG_DBCONFIG_SIZE,0,TYPE_INT,PARM_OPT,128*1024,1024*1024*1024},
		{"HistoryCacheSize",&CONFIG_HISTORY_CACHE_SIZE,0,TYPE_INT,PARM_OPT,128*1024,1024*1024*1024},
		{"TrendCacheSize",&CONFIG_TRENDS_CACHE_SIZE,0,TYPE_INT,PARM_OPT,128*1024,1024*1024*1024},
		{"HistoryTextCacheSize",&CONFIG_TEXT_CACHE_SIZE,0,TYPE_INT,PARM_OPT,128*1024,1024*1024*1024},
		{"CacheUpdateFrequency",&CONFIG_DBCONFIG_FREQUENCY,0,TYPE_INT,PARM_OPT,1,3600},
		{"HousekeepingFrequency",&CONFIG_HOUSEKEEPING_FREQUENCY,0,TYPE_INT,PARM_OPT,1,24},
		{"MaxHousekeeperDelete",&CONFIG_MAX_HOUSEKEEPER_DELETE,0,TYPE_INT,PARM_OPT,0,1000000},
		{"SenderFrequency",&CONFIG_SENDER_FREQUENCY,0,TYPE_INT,PARM_OPT,5,3600},
		{"TmpDir",&CONFIG_TMPDIR,0,TYPE_STRING,PARM_OPT,0,0},
		{"FpingLocation",&CONFIG_FPING_LOCATION,0,TYPE_STRING,PARM_OPT,0,0},
#ifdef HAVE_IPV6
		{"Fping6Location",&CONFIG_FPING6_LOCATION,0,TYPE_STRING,PARM_OPT,0,0},
#endif /* HAVE_IPV6 */
		{"Timeout",&CONFIG_TIMEOUT,0,TYPE_INT,PARM_OPT,1,30},
		{"TrapperTimeout",&CONFIG_TRAPPER_TIMEOUT,0,TYPE_INT,PARM_OPT,1,300},
		{"UnreachablePeriod",&CONFIG_UNREACHABLE_PERIOD,0,TYPE_INT,PARM_OPT,1,3600},
		{"UnreachableDelay",&CONFIG_UNREACHABLE_DELAY,0,TYPE_INT,PARM_OPT,1,3600},
		{"UnavailableDelay",&CONFIG_UNAVAILABLE_DELAY,0,TYPE_INT,PARM_OPT,1,3600},
		{"ListenIP",&CONFIG_LISTEN_IP,0,TYPE_STRING,PARM_OPT,0,0},
		{"ListenPort",&CONFIG_LISTEN_PORT,0,TYPE_INT,PARM_OPT,1024,32767},
		{"SourceIP",&CONFIG_SOURCE_IP,0,TYPE_STRING,PARM_OPT,0,0},
		{"DisableHousekeeping",&CONFIG_DISABLE_HOUSEKEEPING,0,TYPE_INT,PARM_OPT,0,1},
		{"DebugLevel",&CONFIG_LOG_LEVEL,0,TYPE_INT,PARM_OPT,0,4},
		{"PidFile",&CONFIG_PID_FILE,0,TYPE_STRING,PARM_OPT,0,0},
		{"LogFile",&CONFIG_LOG_FILE,0,TYPE_STRING,PARM_OPT,0,0},
		{"LogFileSize",&CONFIG_LOG_FILE_SIZE,0,TYPE_INT,PARM_OPT,0,1024},
		{"AlertScriptsPath",&CONFIG_ALERT_SCRIPTS_PATH,0,TYPE_STRING,PARM_OPT,0,0},
		{"ExternalScripts",&CONFIG_EXTERNALSCRIPTS,0,TYPE_STRING,PARM_OPT,0,0},
		{"DBHost",&CONFIG_DBHOST,0,TYPE_STRING,PARM_OPT,0,0},
		{"DBName",&CONFIG_DBNAME,0,TYPE_STRING,PARM_MAND,0,0},
		{"DBUser",&CONFIG_DBUSER,0,TYPE_STRING,PARM_OPT,0,0},
		{"DBPassword",&CONFIG_DBPASSWORD,0,TYPE_STRING,PARM_OPT,0,0},
		{"DBSocket",&CONFIG_DBSOCKET,0,TYPE_STRING,PARM_OPT,0,0},
		{"DBPort",&CONFIG_DBPORT,0,TYPE_INT,PARM_OPT,1024,65535},
		{"NodeID",&CONFIG_NODEID,0,TYPE_INT,PARM_OPT,0,65535},
		{"NodeNoEvents",&CONFIG_NODE_NOEVENTS,0,TYPE_INT,PARM_OPT,0,1},
		{"NodeNoHistory",&CONFIG_NODE_NOHISTORY,0,TYPE_INT,PARM_OPT,0,1},
		{"SSHKeyLocation",&CONFIG_SSH_KEY_LOCATION,0,TYPE_STRING,PARM_OPT,0,0},
		{"LogSlowQueries",&CONFIG_LOG_SLOW_QUERIES,0,TYPE_INT,PARM_OPT,0,3600000},
		{"StartProxyPollers",&CONFIG_PROXYPOLLER_FORKS,0,TYPE_INT,PARM_OPT,0,255},
		{"ProxyConfigFrequency",&CONFIG_PROXYCONFIG_FREQUENCY,0,TYPE_INT,PARM_OPT,1,3600*7*24},
		{"ProxyDataFrequency",&CONFIG_PROXYDATA_FREQUENCY,0,TYPE_INT,PARM_OPT,1,3600},
		{0}
	};

	CONFIG_SERVER_STARTUP_TIME = time(NULL);

	parse_cfg_file(CONFIG_FILE, cfg);

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
	if(CONFIG_TMPDIR == NULL)
	{
		CONFIG_TMPDIR=strdup("/tmp");
	}
	if(CONFIG_FPING_LOCATION == NULL)
	{
		CONFIG_FPING_LOCATION=strdup("/usr/sbin/fping");
	}
#ifdef HAVE_IPV6
	if(CONFIG_FPING6_LOCATION == NULL)
	{
		CONFIG_FPING6_LOCATION=strdup("/usr/sbin/fping6");
	}
#endif /* HAVE_IPV6 */
	if(CONFIG_EXTERNALSCRIPTS == NULL)
	{
		CONFIG_EXTERNALSCRIPTS=strdup("/etc/zabbix/externalscripts");
	}
#ifndef	HAVE_LIBCURL
	CONFIG_HTTPPOLLER_FORKS = 0;
#endif
	if (CONFIG_NODEID == 0)
	{
		CONFIG_NODEWATCHER_FORKS = 0;
	}
#ifdef HAVE_SQLITE3
	CONFIG_MAX_HOUSEKEEPER_DELETE = 0;
#endif
}

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
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	main(int argc, char **argv)
{
	zbx_task_t	task = ZBX_TASK_START;
	char		ch = '\0';

	int	nodeid = 0;

	progname = get_program_name(argv[0]);

	/* Parse the command-line. */
	while ((ch = (char)zbx_getopt_long(argc, argv, shortopts, longopts,NULL)) != (char)EOF)
	{
		switch (ch)
		{
			case 'c':
				CONFIG_FILE = strdup(zbx_optarg);
				break;
			case 'h':
				help();
				exit(-1);
				break;
			case 'n':
				nodeid=0;
				if(zbx_optarg)	nodeid = atoi(zbx_optarg);
				task = ZBX_TASK_CHANGE_NODEID;
				break;
			case 'V':
				version();
				exit(-1);
				break;
			default:
				usage();
				exit(-1);
				break;
		}
	}

	if(CONFIG_FILE == NULL)
	{
		CONFIG_FILE=strdup("/etc/zabbix/zabbix_server.conf");
	}

	/* Required for simple checks */
	init_metrics();

	init_config();

#ifdef HAVE_OPENIPMI
	init_ipmi_handler();
#endif

	switch (task)
	{
		case ZBX_TASK_CHANGE_NODEID:
			change_nodeid(0,nodeid);
			exit(-1);
			break;
		default:
			break;
	}

	return daemon_start(CONFIG_ALLOW_ROOT);
}

int	MAIN_ZABBIX_ENTRY(void)
{
        DB_RESULT       result;
        DB_ROW          row;

	int	i;
	pid_t	pid;

	zbx_sock_t	listen_sock;

	int		server_num = 0;

	if(CONFIG_LOG_FILE == NULL || ('\0' == *CONFIG_LOG_FILE))
	{
		zabbix_open_log(LOG_TYPE_SYSLOG,CONFIG_LOG_LEVEL,NULL);
	}
	else
	{
		zabbix_open_log(LOG_TYPE_FILE,CONFIG_LOG_LEVEL,CONFIG_LOG_FILE);
	}

#ifdef  HAVE_SNMP
#	define SNMP_FEATURE_STATUS "YES"
#else
#	define SNMP_FEATURE_STATUS " NO"
#endif
#ifdef	HAVE_OPENIPMI
#	define IPMI_FEATURE_STATUS "YES"
#else
#	define IPMI_FEATURE_STATUS " NO"
#endif
#ifdef  HAVE_LIBCURL
#	define LIBCURL_FEATURE_STATUS "YES"
#else
#	define LIBCURL_FEATURE_STATUS " NO"
#endif
#ifdef  HAVE_JABBER
#	define JABBER_FEATURE_STATUS "YES"
#else
#	define JABBER_FEATURE_STATUS " NO"
#endif
#ifdef  HAVE_ODBC
#	define ODBC_FEATURE_STATUS "YES"
#else
#	define ODBC_FEATURE_STATUS " NO"
#endif
#ifdef	HAVE_SSH2
#       define SSH2_FEATURE_STATUS "YES"
#else
#       define SSH2_FEATURE_STATUS " NO"
#endif
#ifdef  HAVE_IPV6
#	define IPV6_FEATURE_STATUS "YES"
#else
#	define IPV6_FEATURE_STATUS " NO"
#endif

	zabbix_log(LOG_LEVEL_WARNING, "Starting Zabbix Server. Zabbix %s (revision %s).",
			ZABBIX_VERSION,
			ZABBIX_REVISION);

	zabbix_log(LOG_LEVEL_WARNING, "****** Enabled features ******");
	zabbix_log(LOG_LEVEL_WARNING, "SNMP monitoring:           " SNMP_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_WARNING, "IPMI monitoring:           " IPMI_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_WARNING, "WEB monitoring:            " LIBCURL_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_WARNING, "Jabber notifications:      " JABBER_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_WARNING, "Ez Texting notifications:  " LIBCURL_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_WARNING, "ODBC:                      " ODBC_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_WARNING, "SSH2 support:              " SSH2_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_WARNING, "IPv6 support:              " IPV6_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_WARNING, "******************************");

#ifdef  HAVE_SQLITE3
	if(ZBX_MUTEX_ERROR == php_sem_get(&sqlite_access, CONFIG_DBNAME))
	{
		zbx_error("Unable to create mutex for sqlite");
		exit(FAIL);
	}
#endif /* HAVE_SQLITE3 */

	DBconnect(ZBX_DB_CONNECT_EXIT);

	result = DBselect("select refresh_unsupported from config where 1=1" DB_NODE,
		DBnode_local("configid"));
	row = DBfetch(result);

	if( (row != NULL) && DBis_null(row[0]) != SUCCEED)
	{
		CONFIG_REFRESH_UNSUPPORTED = atoi(row[0]);
	}
	DBfree_result(result);

	result = DBselect("select masterid from nodes where nodeid=%d",
		CONFIG_NODEID);
	row = DBfetch(result);

	if( (row != NULL) && DBis_null(row[0]) != SUCCEED)
	{
		CONFIG_MASTER_NODEID = atoi(row[0]);
	}
	DBfree_result(result);

	init_database_cache(ZBX_PROCESS_SERVER);
	init_configuration_cache();

/* Need to set trigger status to UNKNOWN since last run */
/* DBconnect() already made in init_config() */
/*	DBconnect();*/
	DBupdate_triggers_status_after_restart();
	DBclose();

/* To make sure that we can connect to the database before forking new processes */
/*	DBconnect(ZBX_DB_CONNECT_EXIT);*/
/* Do not close database. It is required for database cache */
/*	DBclose();*/

	if (ZBX_MUTEX_ERROR == zbx_mutex_create_force(&node_sync_access, ZBX_MUTEX_NODE_SYNC))
	{
		zbx_error("Unable to create mutex for node syncs");
		exit(FAIL);
	}

/*#define CALC_TREND*/

#ifdef CALC_TREND
	trend();
	return 0;
#endif
	threads = calloc(1 + CONFIG_DBCONFIG_FORKS + CONFIG_POLLER_FORKS + CONFIG_UNREACHABLE_POLLER_FORKS
			+ CONFIG_TRAPPER_FORKS + CONFIG_PINGER_FORKS + CONFIG_ALERTER_FORKS
			+ CONFIG_HOUSEKEEPER_FORKS + CONFIG_TIMER_FORKS + CONFIG_NODEWATCHER_FORKS
			+ CONFIG_HTTPPOLLER_FORKS + CONFIG_DISCOVERER_FORKS + CONFIG_DBSYNCER_FORKS
			+ CONFIG_ESCALATOR_FORKS + CONFIG_IPMIPOLLER_FORKS + CONFIG_PROXYPOLLER_FORKS, sizeof(pid_t));

	if (CONFIG_TRAPPER_FORKS > 0)
	{
		if (FAIL == zbx_tcp_listen(&listen_sock, CONFIG_LISTEN_IP, (unsigned short)CONFIG_LISTEN_PORT))
		{
			zabbix_log(LOG_LEVEL_CRIT, "Listener failed with error: %s.", zbx_tcp_strerror());
			exit(1);
		}
	}

	for (i = 1; i <= CONFIG_DBCONFIG_FORKS + CONFIG_POLLER_FORKS + CONFIG_UNREACHABLE_POLLER_FORKS
			+ CONFIG_TRAPPER_FORKS + CONFIG_PINGER_FORKS + CONFIG_ALERTER_FORKS
			+ CONFIG_HOUSEKEEPER_FORKS + CONFIG_TIMER_FORKS + CONFIG_NODEWATCHER_FORKS
			+ CONFIG_HTTPPOLLER_FORKS + CONFIG_DISCOVERER_FORKS + CONFIG_DBSYNCER_FORKS
			+ CONFIG_ESCALATOR_FORKS + CONFIG_IPMIPOLLER_FORKS + CONFIG_PROXYPOLLER_FORKS; i++)
	{
		if (0 == (pid = zbx_fork()))
		{
			server_num = i;
			break;
		}
		else
			threads[i] = pid;
	}

	/* Main process */
	if (server_num == 0)
	{
		init_main_process();
		zabbix_log(LOG_LEVEL_WARNING, "server #%d started [Watchdog]",
			server_num);

		main_watchdog_loop();
	}
	else if (server_num <= CONFIG_DBCONFIG_FORKS)
	{
		zabbix_log(LOG_LEVEL_WARNING, "server #%d started [DB Cache]",
				server_num);

		main_dbconfig_loop();
	}
	else if (server_num <= CONFIG_DBCONFIG_FORKS + CONFIG_POLLER_FORKS)
	{
#ifdef HAVE_SNMP
		init_snmp("zabbix_server");
#endif /* HAVE_SNMP */

		zabbix_log(LOG_LEVEL_WARNING, "server #%d started [Poller. SNMP:"SNMP_FEATURE_STATUS"]",
				server_num);

		main_poller_loop(ZBX_PROCESS_SERVER, ZBX_POLLER_TYPE_NORMAL, server_num
				- CONFIG_DBCONFIG_FORKS);
	}
	else if (server_num <= CONFIG_DBCONFIG_FORKS + CONFIG_POLLER_FORKS
			+ CONFIG_UNREACHABLE_POLLER_FORKS)
	{
#ifdef HAVE_SNMP
		init_snmp("zabbix_server");
#endif /* HAVE_SNMP */

		zabbix_log(LOG_LEVEL_WARNING, "server #%d started [Poller for unreachable hosts. SNMP:"SNMP_FEATURE_STATUS"]",
				server_num);

		main_poller_loop(ZBX_PROCESS_SERVER, ZBX_POLLER_TYPE_UNREACHABLE, server_num
				- CONFIG_DBCONFIG_FORKS - CONFIG_POLLER_FORKS);
	}
	else if (server_num <= CONFIG_DBCONFIG_FORKS + CONFIG_POLLER_FORKS
			+ CONFIG_UNREACHABLE_POLLER_FORKS + CONFIG_TRAPPER_FORKS)
	{
		zabbix_log(LOG_LEVEL_WARNING, "server #%d started [Trapper]",
				server_num);

		child_trapper_main(ZBX_PROCESS_SERVER, &listen_sock);
	}
	else if (server_num <= CONFIG_DBCONFIG_FORKS + CONFIG_POLLER_FORKS
			+ CONFIG_UNREACHABLE_POLLER_FORKS + CONFIG_TRAPPER_FORKS
			+ CONFIG_PINGER_FORKS)
	{
		zabbix_log(LOG_LEVEL_WARNING, "server #%d started [ICMP pinger]",
				server_num);

		main_pinger_loop(server_num
				- CONFIG_DBCONFIG_FORKS - CONFIG_POLLER_FORKS
				- CONFIG_UNREACHABLE_POLLER_FORKS - CONFIG_TRAPPER_FORKS);
	}
	else if (server_num <= CONFIG_DBCONFIG_FORKS + CONFIG_POLLER_FORKS
			+ CONFIG_UNREACHABLE_POLLER_FORKS + CONFIG_TRAPPER_FORKS
			+ CONFIG_PINGER_FORKS + CONFIG_ALERTER_FORKS)
	{
		zabbix_log(LOG_LEVEL_WARNING, "server #%d started [Alerter]",
				server_num);

		main_alerter_loop();
	}
	else if (server_num <= CONFIG_DBCONFIG_FORKS + CONFIG_POLLER_FORKS
			+ CONFIG_UNREACHABLE_POLLER_FORKS + CONFIG_TRAPPER_FORKS
			+ CONFIG_PINGER_FORKS + CONFIG_ALERTER_FORKS
			+ CONFIG_HOUSEKEEPER_FORKS)
	{
		zabbix_log(LOG_LEVEL_WARNING, "server #%d started [Housekeeper]",
				server_num);

		main_housekeeper_loop();
	}
	else if (server_num <= CONFIG_DBCONFIG_FORKS + CONFIG_POLLER_FORKS
			+ CONFIG_UNREACHABLE_POLLER_FORKS + CONFIG_TRAPPER_FORKS
			+ CONFIG_PINGER_FORKS + CONFIG_ALERTER_FORKS
			+ CONFIG_HOUSEKEEPER_FORKS + CONFIG_TIMER_FORKS)
	{
		zabbix_log(LOG_LEVEL_WARNING, "server #%d started [Timer]",
				server_num);

		main_timer_loop();
	}
	else if (server_num <= CONFIG_DBCONFIG_FORKS + CONFIG_POLLER_FORKS
			+ CONFIG_UNREACHABLE_POLLER_FORKS + CONFIG_TRAPPER_FORKS
			+ CONFIG_PINGER_FORKS + CONFIG_ALERTER_FORKS
			+ CONFIG_HOUSEKEEPER_FORKS + CONFIG_TIMER_FORKS
			+ CONFIG_NODEWATCHER_FORKS)
	{
		zabbix_log(LOG_LEVEL_WARNING, "server #%d started [Node watcher. Node ID:%d]",
				server_num,
				CONFIG_NODEID);

		main_nodewatcher_loop();
	}
	else if (server_num <= CONFIG_DBCONFIG_FORKS + CONFIG_POLLER_FORKS
			+ CONFIG_UNREACHABLE_POLLER_FORKS + CONFIG_TRAPPER_FORKS
			+ CONFIG_PINGER_FORKS + CONFIG_ALERTER_FORKS
			+ CONFIG_HOUSEKEEPER_FORKS + CONFIG_TIMER_FORKS
			+ CONFIG_NODEWATCHER_FORKS + CONFIG_HTTPPOLLER_FORKS)
	{
		zabbix_log(LOG_LEVEL_WARNING, "server #%d started [HTTP Poller]",
				server_num);

		main_httppoller_loop(server_num
				- CONFIG_DBCONFIG_FORKS - CONFIG_POLLER_FORKS
				- CONFIG_UNREACHABLE_POLLER_FORKS - CONFIG_TRAPPER_FORKS
				- CONFIG_PINGER_FORKS - CONFIG_ALERTER_FORKS
				- CONFIG_HOUSEKEEPER_FORKS - CONFIG_TIMER_FORKS
				- CONFIG_NODEWATCHER_FORKS);
	}
	else if (server_num <= CONFIG_DBCONFIG_FORKS + CONFIG_POLLER_FORKS
			+ CONFIG_UNREACHABLE_POLLER_FORKS + CONFIG_TRAPPER_FORKS
			+ CONFIG_PINGER_FORKS + CONFIG_ALERTER_FORKS
			+ CONFIG_HOUSEKEEPER_FORKS + CONFIG_TIMER_FORKS
			+ CONFIG_NODEWATCHER_FORKS + CONFIG_HTTPPOLLER_FORKS
			+ CONFIG_DISCOVERER_FORKS)
	{
#ifdef HAVE_SNMP
		init_snmp("zabbix_server");
#endif /* HAVE_SNMP */

		zabbix_log(LOG_LEVEL_WARNING, "server #%d started [Discoverer. SNMP:"SNMP_FEATURE_STATUS"]",
				server_num);

		main_discoverer_loop(ZBX_PROCESS_SERVER, server_num
				- CONFIG_DBCONFIG_FORKS - CONFIG_POLLER_FORKS
				- CONFIG_UNREACHABLE_POLLER_FORKS - CONFIG_TRAPPER_FORKS
				- CONFIG_PINGER_FORKS - CONFIG_ALERTER_FORKS
				- CONFIG_HOUSEKEEPER_FORKS - CONFIG_TIMER_FORKS
				- CONFIG_NODEWATCHER_FORKS - CONFIG_HTTPPOLLER_FORKS);
	}
	else if (server_num <= CONFIG_DBCONFIG_FORKS + CONFIG_POLLER_FORKS
			+ CONFIG_UNREACHABLE_POLLER_FORKS + CONFIG_TRAPPER_FORKS
			+ CONFIG_PINGER_FORKS + CONFIG_ALERTER_FORKS
			+ CONFIG_HOUSEKEEPER_FORKS + CONFIG_TIMER_FORKS
			+ CONFIG_NODEWATCHER_FORKS + CONFIG_HTTPPOLLER_FORKS
			+ CONFIG_DISCOVERER_FORKS + CONFIG_DBSYNCER_FORKS)
	{
		zabbix_log(LOG_LEVEL_WARNING, "server #%d started [DB Syncer]",
				server_num);

		main_dbsyncer_loop();
	}
	else if (server_num <= CONFIG_DBCONFIG_FORKS + CONFIG_POLLER_FORKS
			+ CONFIG_UNREACHABLE_POLLER_FORKS + CONFIG_TRAPPER_FORKS
			+ CONFIG_PINGER_FORKS + CONFIG_ALERTER_FORKS
			+ CONFIG_HOUSEKEEPER_FORKS + CONFIG_TIMER_FORKS
			+ CONFIG_NODEWATCHER_FORKS + CONFIG_HTTPPOLLER_FORKS
			+ CONFIG_DISCOVERER_FORKS + CONFIG_DBSYNCER_FORKS
			+ CONFIG_ESCALATOR_FORKS)
	{
		zabbix_log(LOG_LEVEL_WARNING, "server #%d started [Escalator]",
				server_num);

		main_escalator_loop();
	}
	else if (server_num <= CONFIG_DBCONFIG_FORKS + CONFIG_POLLER_FORKS
			+ CONFIG_UNREACHABLE_POLLER_FORKS + CONFIG_TRAPPER_FORKS
			+ CONFIG_PINGER_FORKS + CONFIG_ALERTER_FORKS
			+ CONFIG_HOUSEKEEPER_FORKS + CONFIG_TIMER_FORKS
			+ CONFIG_NODEWATCHER_FORKS + CONFIG_HTTPPOLLER_FORKS
			+ CONFIG_DISCOVERER_FORKS + CONFIG_DBSYNCER_FORKS
			+ CONFIG_ESCALATOR_FORKS + CONFIG_IPMIPOLLER_FORKS)
	{
		zabbix_log(LOG_LEVEL_WARNING, "server #%d started [IPMI Poller]",
				server_num);

		main_poller_loop(ZBX_PROCESS_SERVER, ZBX_POLLER_TYPE_IPMI, server_num
				- CONFIG_DBCONFIG_FORKS - CONFIG_POLLER_FORKS
				- CONFIG_UNREACHABLE_POLLER_FORKS - CONFIG_TRAPPER_FORKS
				- CONFIG_PINGER_FORKS - CONFIG_ALERTER_FORKS
				- CONFIG_HOUSEKEEPER_FORKS - CONFIG_TIMER_FORKS
				- CONFIG_NODEWATCHER_FORKS - CONFIG_HTTPPOLLER_FORKS
				- CONFIG_DISCOVERER_FORKS - CONFIG_DBSYNCER_FORKS
				- CONFIG_ESCALATOR_FORKS);
	}
	else if (server_num <= CONFIG_DBCONFIG_FORKS + CONFIG_POLLER_FORKS
			+ CONFIG_UNREACHABLE_POLLER_FORKS + CONFIG_TRAPPER_FORKS
			+ CONFIG_PINGER_FORKS + CONFIG_ALERTER_FORKS
			+ CONFIG_HOUSEKEEPER_FORKS + CONFIG_TIMER_FORKS
			+ CONFIG_NODEWATCHER_FORKS + CONFIG_HTTPPOLLER_FORKS
			+ CONFIG_DISCOVERER_FORKS + CONFIG_DBSYNCER_FORKS
			+ CONFIG_ESCALATOR_FORKS + CONFIG_IPMIPOLLER_FORKS
			+ CONFIG_PROXYPOLLER_FORKS)
	{
		zabbix_log(LOG_LEVEL_WARNING, "server #%d started [Proxy Poller]",
				server_num);

		main_proxypoller_loop();
	}

	return SUCCEED;
}

void	zbx_on_exit()
{
	zabbix_log(LOG_LEVEL_DEBUG, "zbx_on_exit() called");

	if (threads != NULL)
	{
		int	i;

		for (i = 1; i <= CONFIG_DBCONFIG_FORKS + CONFIG_POLLER_FORKS
				+ CONFIG_UNREACHABLE_POLLER_FORKS + CONFIG_TRAPPER_FORKS
				+ CONFIG_PINGER_FORKS + CONFIG_ALERTER_FORKS
				+ CONFIG_HOUSEKEEPER_FORKS + CONFIG_TIMER_FORKS
				+ CONFIG_NODEWATCHER_FORKS + CONFIG_HTTPPOLLER_FORKS
				+ CONFIG_DISCOVERER_FORKS + CONFIG_DBSYNCER_FORKS
				+ CONFIG_ESCALATOR_FORKS + CONFIG_IPMIPOLLER_FORKS
				+ CONFIG_PROXYPOLLER_FORKS; i++)
		{
			if (threads[i])
			{
				kill(threads[i], SIGTERM);
				threads[i] = ZBX_THREAD_HANDLE_NULL;
			}
		}

		zbx_free(threads);
	}

#ifdef USE_PID_FILE

	daemon_stop();

#endif /* USE_PID_FILE */

	free_metrics();

	zbx_sleep(2); /* wait for all threads closing */

	DBconnect(ZBX_DB_CONNECT_EXIT);
	free_database_cache();
	free_configuration_cache();
	DBclose();

	zbx_mutex_destroy(&node_sync_access);

#ifdef HAVE_OPENIPMI
	free_ipmi_handler();
#endif

#ifdef  HAVE_SQLITE3
	php_sem_remove(&sqlite_access);
#endif /* HAVE_SQLITE3 */

	zabbix_log(LOG_LEVEL_INFORMATION, "Zabbix Server stopped. Zabbix %s (revision %s).",
			ZABBIX_VERSION,
			ZABBIX_REVISION);

	zabbix_close_log();

	exit(SUCCEED);
}
