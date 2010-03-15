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

#include "../zabbix_server/dbsyncer/dbsyncer.h"
#include "../zabbix_server/dbconfig/dbconfig.h"
#include "../zabbix_server/discoverer/discoverer.h"
#include "../zabbix_server/httppoller/httppoller.h"
#include "housekeeper/housekeeper.h"
#include "../zabbix_server/pinger/pinger.h"
#include "../zabbix_server/poller/poller.h"
#include "../zabbix_server/poller/checks_ipmi.h"
#include "../zabbix_server/trapper/trapper.h"
#include "proxyconfig/proxyconfig.h"
#include "datasender/datasender.h"
#include "heart/heart.h"

char *progname = NULL;
char title_message[] = "Zabbix Proxy";
char usage_message[] = "[-hV] [-c <file>]";

#ifndef HAVE_GETOPT_LONG
char *help_message[] = {
        "Options:",
        "  -c <file>       Specify configuration file",
        "  -h              give this help",
        "  -V              display version number",
        0 /* end of text */
};
#else
char *help_message[] = {
        "Options:",
        "  -c --config <file>       Specify configuration file",
        "  -h --help                give this help",
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
	{"version",	0,	0,	'V'},

#if defined (_WINDOWS)

	{"install",	0,	0,	'i'},
	{"uninstall",	0,	0,	'd'},

	{"start",	0,	0,	's'},
	{"stop",	0,	0,	'x'},

#endif /* _WINDOWS */

	{0,0,0,0}
};

/* short options */

static char	shortopts[] =
	"c:n:hV"
#if defined (_WINDOWS)
	"idsx"
#endif /* _WINDOWS */
	;

/* end of COMMAND LINE OPTIONS*/

pid_t	*threads=NULL;

int	CONFIG_CONFSYNCER_FORKS		= 1;
int	CONFIG_DATASENDER_FORKS		= 1;
int	CONFIG_DISCOVERER_FORKS		= 1;
int	CONFIG_HOUSEKEEPER_FORKS	= 1;
int	CONFIG_PINGER_FORKS		= 1;
int	CONFIG_POLLER_FORKS		= 5;
int	CONFIG_HTTPPOLLER_FORKS		= 1;
int	CONFIG_IPMIPOLLER_FORKS		= 0;
int	CONFIG_TRAPPERD_FORKS		= 5;
int	CONFIG_UNREACHABLE_POLLER_FORKS	= 1;

int	CONFIG_LISTEN_PORT		= 10051;
char	*CONFIG_LISTEN_IP		= NULL;
char	*CONFIG_SOURCE_IP		= NULL;
int	CONFIG_TRAPPER_TIMEOUT		= ZABBIX_TRAPPER_TIMEOUT;
/**/
/*int	CONFIG_NOTIMEWAIT		= 0;*/
int	CONFIG_DISABLE_HOUSEKEEPING	= 0;
int	CONFIG_HOUSEKEEPING_FREQUENCY	= 1; /* h */
int	CONFIG_PROXY_LOCAL_BUFFER	= 0; /* 24h */
int	CONFIG_PROXY_OFFLINE_BUFFER	= 1; /* 720h */

int	CONFIG_HEARTBEAT_FREQUENCY      = 60;

int	CONFIG_PROXYCONFIG_FREQUENCY    = 3600; /* 1h */

int	CONFIG_DATASENDER_FREQUENCY     = 1;

int	CONFIG_SENDER_FREQUENCY		= 30;
int	CONFIG_DBSYNCER_FORKS		= 1;
int	CONFIG_DBSYNCER_FREQUENCY	= 5;
int	CONFIG_DBCONFIG_FORKS		= 1;
int	CONFIG_DBCONFIG_FREQUENCY	= 60;
int	CONFIG_DBCONFIG_SIZE		= 8388608;	/* 8MB */
int	CONFIG_HISTORY_CACHE_SIZE	= 8388608;	/* 8MB */
int	CONFIG_TRENDS_CACHE_SIZE	= 4194304;	/* 4MB */
int	CONFIG_TEXT_CACHE_SIZE		= 16777216;	/* 16MB */
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

char	*CONFIG_SERVER			= NULL;
int	CONFIG_SERVER_PORT		= 10051;
char	*CONFIG_HOSTNAME		= NULL;
int	CONFIG_NODEID			= -1;
int	CONFIG_MASTER_NODEID		= 0;
int	CONFIG_NODE_NOHISTORY		= 0;

char	*CONFIG_SSH_KEY_LOCATION	= NULL;

int	CONFIG_LOG_SLOW_QUERIES		= 0;	/* ms; 0 - disable */

/* Global variable to control if we should write warnings to log[] */
int	CONFIG_ENABLE_LOG		= 1;

int	CONFIG_REFRESH_UNSUPPORTED	= 600;

/* Zabbix server startup time */
int     CONFIG_SERVER_STARTUP_TIME      = 0;

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
	AGENT_RESULT	result;
	char		**value = NULL;

	static struct cfg_line cfg[]=
	{
/*		 PARAMETER	,VAR	,FUNC,	TYPE(0i,1s),MANDATORY,MIN,MAX	*/
		{"Server",&CONFIG_SERVER,0,TYPE_STRING,PARM_MAND,0,0},
		{"ServerPort",&CONFIG_SERVER_PORT,0,TYPE_INT,PARM_OPT,1024,32767},
		{"Hostname",&CONFIG_HOSTNAME,0,TYPE_STRING,PARM_OPT,0,0},

		{"StartDiscoverers",&CONFIG_DISCOVERER_FORKS,0,TYPE_INT,PARM_OPT,0,255},
		{"StartHTTPPollers",&CONFIG_HTTPPOLLER_FORKS,0,TYPE_INT,PARM_OPT,0,255},
		{"StartPingers",&CONFIG_PINGER_FORKS,0,TYPE_INT,PARM_OPT,0,255},
		{"StartPollers",&CONFIG_POLLER_FORKS,0,TYPE_INT,PARM_OPT,0,255},
		{"StartIPMIPollers",&CONFIG_IPMIPOLLER_FORKS,0,TYPE_INT,PARM_OPT,0,255},
		{"StartPollersUnreachable",&CONFIG_UNREACHABLE_POLLER_FORKS,0,TYPE_INT,PARM_OPT,0,255},
		{"StartTrappers",&CONFIG_TRAPPERD_FORKS,0,TYPE_INT,PARM_OPT,0,255},

		{"CacheSize",&CONFIG_DBCONFIG_SIZE,0,TYPE_INT,PARM_OPT,128*1024,1024*1024*1024},
		{"HistoryCacheSize",&CONFIG_HISTORY_CACHE_SIZE,0,TYPE_INT,PARM_OPT,128*1024,1024*1024*1024},
		{"TrendCacheSize",&CONFIG_TRENDS_CACHE_SIZE,0,TYPE_INT,PARM_OPT,128*1024,1024*1024*1024},
		{"HistoryTextCacheSize",&CONFIG_TEXT_CACHE_SIZE,0,TYPE_INT,PARM_OPT,128*1024,1024*1024*1024},
		{"CacheUpdateFrequency",&CONFIG_DBCONFIG_FREQUENCY,0,TYPE_INT,PARM_OPT,1,3600},

		{"HousekeepingFrequency",&CONFIG_HOUSEKEEPING_FREQUENCY,0,TYPE_INT,PARM_OPT,1,24},
		{"ProxyLocalBuffer",&CONFIG_PROXY_LOCAL_BUFFER,0,TYPE_INT,PARM_OPT,0,720},
		{"ProxyOfflineBuffer",&CONFIG_PROXY_OFFLINE_BUFFER,0,TYPE_INT,PARM_OPT,1,720},

		{"HeartbeatFrequency",&CONFIG_HEARTBEAT_FREQUENCY,0,TYPE_INT,PARM_OPT,0,3600},
		{"ConfigFrequency",&CONFIG_PROXYCONFIG_FREQUENCY,0,TYPE_INT,PARM_OPT,1,3600*7*24},
		{"DataSenderFrequency",&CONFIG_DATASENDER_FREQUENCY,0,TYPE_INT,PARM_OPT,1,3600},

/*		{"SenderFrequency",&CONFIG_SENDER_FREQUENCY,0,TYPE_INT,PARM_OPT,5,3600},*/
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
/*		{"NoTimeWait",&CONFIG_NOTIMEWAIT,0,TYPE_INT,PARM_OPT,0,1},*/
/*		{"DisablePinger",&CONFIG_DISABLE_PINGER,0,TYPE_INT,PARM_OPT,0,1},*/
		{"DebugLevel",&CONFIG_LOG_LEVEL,0,TYPE_INT,PARM_OPT,0,4},
		{"PidFile",&APP_PID_FILE,0,TYPE_STRING,PARM_OPT,0,0},
		{"LogFile",&CONFIG_LOG_FILE,0,TYPE_STRING,PARM_OPT,0,0},
		{"LogFileSize",&CONFIG_LOG_FILE_SIZE,0,TYPE_INT,PARM_OPT,0,1024},
/*		{"AlertScriptsPath",&CONFIG_ALERT_SCRIPTS_PATH,0,TYPE_STRING,PARM_OPT,0,0},*/
		{"ExternalScripts",&CONFIG_EXTERNALSCRIPTS,0,TYPE_STRING,PARM_OPT,0,0},
		{"DBHost",&CONFIG_DBHOST,0,TYPE_STRING,PARM_OPT,0,0},
		{"DBName",&CONFIG_DBNAME,0,TYPE_STRING,PARM_MAND,0,0},
		{"DBUser",&CONFIG_DBUSER,0,TYPE_STRING,PARM_OPT,0,0},
		{"DBPassword",&CONFIG_DBPASSWORD,0,TYPE_STRING,PARM_OPT,0,0},
		{"DBSocket",&CONFIG_DBSOCKET,0,TYPE_STRING,PARM_OPT,0,0},
		{"DBPort",&CONFIG_DBPORT,0,TYPE_INT,PARM_OPT,1024,65535},
		{"SSHKeyLocation",&CONFIG_SSH_KEY_LOCATION,0,TYPE_STRING,PARM_OPT,0,0},
		{"LogSlowQueries",&CONFIG_LOG_SLOW_QUERIES,0,TYPE_INT,PARM_OPT,0,3600000},
		{0}
	};

	CONFIG_SERVER_STARTUP_TIME = time(NULL);

	parse_cfg_file(CONFIG_FILE,cfg);

	if(CONFIG_HOSTNAME == NULL)
	{
	  	if(SUCCEED == process("system.hostname", 0, &result))
		{
			if( NULL != (value = GET_STR_RESULT(&result)) )
			{
				CONFIG_HOSTNAME = strdup(*value);
			}
		}
	        free_result(&result);

		if(CONFIG_HOSTNAME == NULL)
		{
			zabbix_log( LOG_LEVEL_CRIT, "Hostname is not defined");
			exit(1);
		}
	}
	if(CONFIG_DBNAME == NULL)
	{
		zabbix_log( LOG_LEVEL_CRIT, "DBName not in config file");
		exit(1);
	}
	if(APP_PID_FILE == NULL)
	{
		APP_PID_FILE=strdup("/tmp/zabbix_proxy.pid");
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
int main(int argc, char **argv)
{
	char    ch      = '\0';

	progname = argv[0];

	/* Parse the command-line. */
	while ((ch = (char)zbx_getopt_long(argc, argv, shortopts, longopts,NULL)) != (char)EOF)
	switch (ch) {
		case 'c':
			CONFIG_FILE = strdup(zbx_optarg);
			break;
		case 'h':
			help();
			exit(-1);
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

	if(CONFIG_FILE == NULL)
	{
		CONFIG_FILE=strdup("/etc/zabbix/zabbix_proxy.conf");
	}

	/* Required for simple checks */
	init_metrics();

	init_config();

#ifdef HAVE_OPENIPMI
	init_ipmi_handler();
#endif

	return daemon_start(CONFIG_ALLOW_ROOT);
}

int MAIN_ZABBIX_ENTRY(void)
{
	int	i;
	pid_t	pid;

	zbx_sock_t	listen_sock;

	int		server_num = 0;

	if(CONFIG_LOG_FILE == NULL || '\0' == *CONFIG_LOG_FILE)
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
#ifdef  HAVE_ODBC
#	define ODBC_FEATURE_STATUS "YES"
#else
#	define ODBC_FEATURE_STATUS " NO"
#endif
#ifdef	HAVE_SSH2
#	define SSH2_FEATURE_STATUS "YES"
#else
#	define SSH2_FEATURE_STATUS " NO"
#endif
#ifdef  HAVE_IPV6
#       define IPV6_FEATURE_STATUS "YES"
#else
#       define IPV6_FEATURE_STATUS " NO"
#endif

	zabbix_log( LOG_LEVEL_WARNING, "Starting Zabbix Proxy. Zabbix %s (revision %s).",
			ZABBIX_VERSION,
			ZABBIX_REVISION);

	zabbix_log( LOG_LEVEL_WARNING, "**** Enabled features ****");
	zabbix_log( LOG_LEVEL_WARNING, "SNMP monitoring:       " SNMP_FEATURE_STATUS	);
	zabbix_log( LOG_LEVEL_WARNING, "IPMI monitoring:       " IPMI_FEATURE_STATUS	);
	zabbix_log( LOG_LEVEL_WARNING, "WEB monitoring:        " LIBCURL_FEATURE_STATUS	);
	zabbix_log( LOG_LEVEL_WARNING, "ODBC:                  " ODBC_FEATURE_STATUS	);
	zabbix_log( LOG_LEVEL_WARNING, "SSH2 support:          " SSH2_FEATURE_STATUS	);
	zabbix_log( LOG_LEVEL_WARNING, "IPv6 support:          " IPV6_FEATURE_STATUS	);
	zabbix_log( LOG_LEVEL_WARNING, "**************************");

#ifdef  HAVE_SQLITE3
	if(ZBX_MUTEX_ERROR == php_sem_get(&sqlite_access, CONFIG_DBNAME))
	{
		zbx_error("Unable to create mutex for sqlite");
		exit(FAIL);
	}
#endif /* HAVE_SQLITE3 */

	DBinit();

	init_database_cache(ZBX_PROCESS_PROXY);
	init_configuration_cache();

	threads = calloc(1 + CONFIG_DBCONFIG_FORKS + CONFIG_CONFSYNCER_FORKS + CONFIG_DATASENDER_FORKS
			+ CONFIG_POLLER_FORKS + CONFIG_TRAPPERD_FORKS + CONFIG_PINGER_FORKS
			+ CONFIG_HOUSEKEEPER_FORKS + CONFIG_UNREACHABLE_POLLER_FORKS
			+ CONFIG_HTTPPOLLER_FORKS + CONFIG_DISCOVERER_FORKS + CONFIG_DBSYNCER_FORKS
			+ CONFIG_IPMIPOLLER_FORKS, sizeof(pid_t));

	if (CONFIG_TRAPPERD_FORKS > 0) {
		if (FAIL == zbx_tcp_listen(&listen_sock, CONFIG_LISTEN_IP, (unsigned short)CONFIG_LISTEN_PORT)) {
			zabbix_log(LOG_LEVEL_CRIT, "Listener failed with error: %s.", zbx_tcp_strerror());
			exit(1);
		}
	}

	for (i = 1; i <= CONFIG_DBCONFIG_FORKS + CONFIG_CONFSYNCER_FORKS + CONFIG_DATASENDER_FORKS
			+ CONFIG_POLLER_FORKS + CONFIG_TRAPPERD_FORKS + CONFIG_PINGER_FORKS
			+ CONFIG_HOUSEKEEPER_FORKS + CONFIG_UNREACHABLE_POLLER_FORKS
			+ CONFIG_HTTPPOLLER_FORKS + CONFIG_DISCOVERER_FORKS + CONFIG_DBSYNCER_FORKS
			+ CONFIG_IPMIPOLLER_FORKS;
		i++)
	{
		if ((pid = zbx_fork()) == 0) {
			server_num = i;
			break;
		} else
			threads[i] = pid;
	}

	/* Main process */
	if (server_num == 0) {
		init_main_process();

		zabbix_log(LOG_LEVEL_WARNING, "server #%d started [Heartbeat sender]",
				server_num);

		main_heart_loop();

		for (;;)
			zbx_sleep(3600);
	}
	else if (server_num <= CONFIG_DBCONFIG_FORKS)
	{
		zabbix_log(LOG_LEVEL_WARNING, "server #%d started [DB Cache]",
				server_num);

		main_dbconfig_loop();
	}
	else if (server_num <= CONFIG_DBCONFIG_FORKS + CONFIG_CONFSYNCER_FORKS)
	{
		zabbix_log(LOG_LEVEL_WARNING, "server #%d started [Configuration syncer]",
				server_num);

		main_proxyconfig_loop(server_num - CONFIG_DBCONFIG_FORKS);
	}
	else if (server_num <= CONFIG_DBCONFIG_FORKS + CONFIG_CONFSYNCER_FORKS + CONFIG_DATASENDER_FORKS)
	{
		zabbix_log(LOG_LEVEL_WARNING, "server #%d started [Datasender]",
				server_num);

		main_datasender_loop();
	}
	else if (server_num <= CONFIG_DBCONFIG_FORKS + CONFIG_CONFSYNCER_FORKS + CONFIG_DATASENDER_FORKS
			+ CONFIG_POLLER_FORKS)
	{
#ifdef HAVE_SNMP
		init_snmp("zabbix_server");
#endif /* HAVE_SNMP */

		zabbix_log(LOG_LEVEL_WARNING, "server #%d started [Poller. SNMP:"SNMP_FEATURE_STATUS"]",
				server_num);

		main_poller_loop(ZBX_PROCESS_PROXY, ZBX_POLLER_TYPE_NORMAL, server_num
				- CONFIG_DBCONFIG_FORKS - CONFIG_CONFSYNCER_FORKS
				- CONFIG_DATASENDER_FORKS);
	}
	else if (server_num <= CONFIG_DBCONFIG_FORKS + CONFIG_CONFSYNCER_FORKS + CONFIG_DATASENDER_FORKS
			+ CONFIG_POLLER_FORKS + CONFIG_TRAPPERD_FORKS)
	{
		zabbix_log(LOG_LEVEL_WARNING, "server #%d started [Trapper]",
				server_num);

		child_trapper_main(ZBX_PROCESS_PROXY, &listen_sock);
	}
	else if (server_num <= CONFIG_DBCONFIG_FORKS + CONFIG_CONFSYNCER_FORKS + CONFIG_DATASENDER_FORKS
			+ CONFIG_POLLER_FORKS + CONFIG_TRAPPERD_FORKS + CONFIG_PINGER_FORKS)
	{
		zabbix_log(LOG_LEVEL_WARNING, "server #%d started [ICMP pinger]",
				server_num);

		main_pinger_loop(server_num - CONFIG_DBCONFIG_FORKS - CONFIG_CONFSYNCER_FORKS
				- CONFIG_DATASENDER_FORKS - CONFIG_POLLER_FORKS - CONFIG_TRAPPERD_FORKS);
	}
	else if (server_num <= CONFIG_DBCONFIG_FORKS + CONFIG_CONFSYNCER_FORKS + CONFIG_DATASENDER_FORKS
			+ CONFIG_POLLER_FORKS + CONFIG_TRAPPERD_FORKS + CONFIG_PINGER_FORKS
			+ CONFIG_HOUSEKEEPER_FORKS)
	{
		zabbix_log(LOG_LEVEL_WARNING, "server #%d started [Housekeeper]",
				server_num);

		main_housekeeper_loop();
	}
	else if (server_num <= CONFIG_DBCONFIG_FORKS + CONFIG_CONFSYNCER_FORKS + CONFIG_DATASENDER_FORKS
			+ CONFIG_POLLER_FORKS + CONFIG_TRAPPERD_FORKS + CONFIG_PINGER_FORKS
			+ CONFIG_HOUSEKEEPER_FORKS + CONFIG_UNREACHABLE_POLLER_FORKS)
	{
#ifdef HAVE_SNMP
		init_snmp("zabbix_server");
#endif /* HAVE_SNMP */

		zabbix_log(LOG_LEVEL_WARNING, "server #%d started [Poller for unreachable hosts. SNMP:"SNMP_FEATURE_STATUS"]",
				server_num);

		main_poller_loop(ZBX_PROCESS_PROXY, ZBX_POLLER_TYPE_UNREACHABLE, server_num
				- CONFIG_DBCONFIG_FORKS - CONFIG_CONFSYNCER_FORKS
				- CONFIG_DATASENDER_FORKS - CONFIG_POLLER_FORKS
				- CONFIG_TRAPPERD_FORKS - CONFIG_PINGER_FORKS
				- CONFIG_HOUSEKEEPER_FORKS);
	}
	else if (server_num <= CONFIG_DBCONFIG_FORKS + CONFIG_CONFSYNCER_FORKS + CONFIG_DATASENDER_FORKS
			+ CONFIG_POLLER_FORKS + CONFIG_TRAPPERD_FORKS + CONFIG_PINGER_FORKS
			+ CONFIG_HOUSEKEEPER_FORKS + CONFIG_UNREACHABLE_POLLER_FORKS
			+ CONFIG_HTTPPOLLER_FORKS)
	{
		zabbix_log(LOG_LEVEL_WARNING, "server #%d started [HTTP Poller]",
				server_num);

		main_httppoller_loop(server_num - CONFIG_DBCONFIG_FORKS - CONFIG_CONFSYNCER_FORKS
				- CONFIG_DATASENDER_FORKS - CONFIG_POLLER_FORKS - CONFIG_TRAPPERD_FORKS
				- CONFIG_PINGER_FORKS - CONFIG_HOUSEKEEPER_FORKS
				- CONFIG_UNREACHABLE_POLLER_FORKS);
	}
	else if (server_num <= CONFIG_DBCONFIG_FORKS + CONFIG_CONFSYNCER_FORKS + CONFIG_DATASENDER_FORKS
			+ CONFIG_POLLER_FORKS + CONFIG_TRAPPERD_FORKS + CONFIG_PINGER_FORKS
			+ CONFIG_HOUSEKEEPER_FORKS + CONFIG_UNREACHABLE_POLLER_FORKS
			+ CONFIG_HTTPPOLLER_FORKS + CONFIG_DISCOVERER_FORKS)
	{
#ifdef HAVE_SNMP
		init_snmp("zabbix_server");
#endif /* HAVE_SNMP */

		zabbix_log(LOG_LEVEL_WARNING, "server #%d started [Discoverer. SNMP:"SNMP_FEATURE_STATUS"]",
				server_num);

		main_discoverer_loop(ZBX_PROCESS_PROXY, server_num - CONFIG_DBCONFIG_FORKS
				- CONFIG_CONFSYNCER_FORKS - CONFIG_DATASENDER_FORKS - CONFIG_POLLER_FORKS
				- CONFIG_TRAPPERD_FORKS - CONFIG_PINGER_FORKS - CONFIG_HOUSEKEEPER_FORKS
				- CONFIG_UNREACHABLE_POLLER_FORKS - CONFIG_HTTPPOLLER_FORKS);
	}
	else if (server_num <= CONFIG_DBCONFIG_FORKS + CONFIG_CONFSYNCER_FORKS + CONFIG_DATASENDER_FORKS
			+ CONFIG_POLLER_FORKS + CONFIG_TRAPPERD_FORKS + CONFIG_PINGER_FORKS
			+ CONFIG_HOUSEKEEPER_FORKS + CONFIG_UNREACHABLE_POLLER_FORKS
			+ CONFIG_HTTPPOLLER_FORKS + CONFIG_DISCOVERER_FORKS + CONFIG_DBSYNCER_FORKS)
	{
		zabbix_log(LOG_LEVEL_WARNING, "server #%d started [DB Syncer]",
				server_num);

		main_dbsyncer_loop();
	}
	else if (server_num <= CONFIG_DBCONFIG_FORKS + CONFIG_CONFSYNCER_FORKS + CONFIG_DATASENDER_FORKS
			+ CONFIG_POLLER_FORKS + CONFIG_TRAPPERD_FORKS + CONFIG_PINGER_FORKS
			+ CONFIG_HOUSEKEEPER_FORKS + CONFIG_UNREACHABLE_POLLER_FORKS
			+ CONFIG_HTTPPOLLER_FORKS + CONFIG_DISCOVERER_FORKS + CONFIG_DBSYNCER_FORKS
			+ CONFIG_IPMIPOLLER_FORKS)
	{
		zabbix_log(LOG_LEVEL_WARNING, "server #%d started [IPMI Poller]",
				server_num);

		main_poller_loop(ZBX_PROCESS_PROXY, ZBX_POLLER_TYPE_IPMI, server_num
				- CONFIG_DBCONFIG_FORKS - CONFIG_CONFSYNCER_FORKS
				- CONFIG_DATASENDER_FORKS - CONFIG_POLLER_FORKS
				- CONFIG_TRAPPERD_FORKS - CONFIG_PINGER_FORKS
				- CONFIG_HOUSEKEEPER_FORKS - CONFIG_UNREACHABLE_POLLER_FORKS
				- CONFIG_HTTPPOLLER_FORKS - CONFIG_DISCOVERER_FORKS
				- CONFIG_DBSYNCER_FORKS);
	}

	return SUCCEED;
}

void	zbx_on_exit()
{
	zabbix_log(LOG_LEVEL_INFORMATION, "zbx_on_exit()");
#if !defined(_WINDOWS)

	int i = 0;

	if (threads != NULL) {
		for (i = 1; i <= CONFIG_DBCONFIG_FORKS + CONFIG_CONFSYNCER_FORKS + CONFIG_DATASENDER_FORKS
				+ CONFIG_POLLER_FORKS + CONFIG_TRAPPERD_FORKS + CONFIG_PINGER_FORKS
				+ CONFIG_HOUSEKEEPER_FORKS + CONFIG_UNREACHABLE_POLLER_FORKS
				+ CONFIG_HTTPPOLLER_FORKS + CONFIG_DISCOVERER_FORKS
				+ CONFIG_DBSYNCER_FORKS + CONFIG_IPMIPOLLER_FORKS; i++)
		{
			if (threads[i]) {
				kill(threads[i], SIGTERM);
				threads[i] = (ZBX_THREAD_HANDLE)NULL;
			}
		}
		zbx_free(threads);
	}

#endif /* not _WINDOWS */

#ifdef USE_PID_FILE

	daemon_stop();

#endif /* USE_PID_FILE */

	free_metrics();

	zbx_sleep(2); /* wait for all threads closing */

	DBconnect(ZBX_DB_CONNECT_EXIT);
	free_database_cache();
	free_configuration_cache();
	DBclose();
/*	zbx_mutex_destroy(&node_sync_access);*/

#ifdef HAVE_OPENIPMI
	free_ipmi_handler();
#endif

	zabbix_close_log();

#ifdef  HAVE_SQLITE3
	php_sem_remove(&sqlite_access);
#endif /* HAVE_SQLITE3 */

	zabbix_log(LOG_LEVEL_INFORMATION, "Zabbix Proxy stopped. Zabbix %s (revision %s).",
		ZABBIX_VERSION,
		ZABBIX_REVISION);

	exit(SUCCEED);
}
