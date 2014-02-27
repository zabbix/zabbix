/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"

#include "cfg.h"
#include "pid.h"
#include "db.h"
#include "dbcache.h"
#include "log.h"
#include "zbxgetopt.h"
#include "mutexs.h"

#include "sysinfo.h"
#include "zbxserver.h"

#include "daemon.h"
#include "zbxself.h"

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
#include "snmptrapper/snmptrapper.h"
#include "nodewatcher/nodewatcher.h"
#include "watchdog/watchdog.h"
#include "utils/nodechange.h"
#include "escalator/escalator.h"
#include "proxypoller/proxypoller.h"
#include "selfmon/selfmon.h"

#define INIT_SERVER(type, count)								\
	process_type = type;									\
	process_num = server_num - server_count + count;					\
	zabbix_log(LOG_LEVEL_INFORMATION, "server #%d started [%s #%d]",			\
			server_num, get_process_type_string(process_type), process_num)

const char	*progname = NULL;
const char	title_message[] = "Zabbix server";
const char	syslog_app_name[] = "zabbix_server";
const char	usage_message[] = "[-hV] [-c <file>] [-n <nodeid>] [-R <option>]";

const char	*help_message[] = {
	"Options:",
	"  -c --config <file>              Absolute path to the configuration file",
	"  -n --new-nodeid <nodeid>        Convert database data to new nodeid",
	"  -R --runtime-control <option>   Perform administrative functions",
	"",
	"Runtime control options:",
	"  " ZBX_CONFIG_CACHE_RELOAD "             Reload configuration cache",
	"",
	"Other options:",
	"  -h --help                       Give this help",
	"  -V --version                    Display version number",
	NULL	/* end of text */
};

/* COMMAND LINE OPTIONS */

/* long options */
static struct zbx_option	longopts[] =
{
	{"config",		1,	NULL,	'c'},
	{"new-nodeid",		1,	NULL,	'n'},
	{"runtime-control",	1,	NULL,	'R'},
	{"help",		0,	NULL,	'h'},
	{"version",		0,	NULL,	'V'},
	{NULL}
};

/* short options */
static char	shortopts[] = "c:n:hVR:";

/* end of COMMAND LINE OPTIONS */

int	threads_num = 0;
pid_t	*threads = NULL;

unsigned char	daemon_type		= ZBX_DAEMON_TYPE_SERVER;

int		process_num		= 0;
unsigned char	process_type		= ZBX_PROCESS_TYPE_UNKNOWN;

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
int	CONFIG_SNMPTRAPPER_FORKS	= 0;
int	CONFIG_JAVAPOLLER_FORKS		= 0;
int	CONFIG_ESCALATOR_FORKS		= 1;
int	CONFIG_SELFMON_FORKS		= 1;
int	CONFIG_WATCHDOG_FORKS		= 1;
int	CONFIG_DATASENDER_FORKS		= 0;
int	CONFIG_HEARTBEAT_FORKS		= 0;

int	CONFIG_LISTEN_PORT		= ZBX_DEFAULT_SERVER_PORT;
char	*CONFIG_LISTEN_IP		= NULL;
char	*CONFIG_SOURCE_IP		= NULL;
int	CONFIG_TRAPPER_TIMEOUT		= 300;

int	CONFIG_HOUSEKEEPING_FREQUENCY	= 1;
int	CONFIG_MAX_HOUSEKEEPER_DELETE	= 500;		/* applies for every separate field value */
int	CONFIG_SENDER_FREQUENCY		= 30;
int	CONFIG_HISTSYNCER_FORKS		= 4;
int	CONFIG_HISTSYNCER_FREQUENCY	= 5;
int	CONFIG_CONFSYNCER_FORKS		= 1;
int	CONFIG_CONFSYNCER_FREQUENCY	= 60;
int	CONFIG_CONF_CACHE_SIZE		= 8 * ZBX_MEBIBYTE;
int	CONFIG_HISTORY_CACHE_SIZE	= 8 * ZBX_MEBIBYTE;
int	CONFIG_TRENDS_CACHE_SIZE	= 4 * ZBX_MEBIBYTE;
int	CONFIG_TEXT_CACHE_SIZE		= 16 * ZBX_MEBIBYTE;
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
#endif
char	*CONFIG_DBHOST			= NULL;
char	*CONFIG_DBNAME			= NULL;
char	*CONFIG_DBSCHEMA		= NULL;
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

char	*CONFIG_SNMPTRAP_FILE		= NULL;

char	*CONFIG_JAVA_GATEWAY		= NULL;
int	CONFIG_JAVA_GATEWAY_PORT	= ZBX_DEFAULT_GATEWAY_PORT;

char	*CONFIG_SSH_KEY_LOCATION	= NULL;

int	CONFIG_LOG_SLOW_QUERIES		= 0;	/* ms; 0 - disable */

int	CONFIG_SERVER_STARTUP_TIME	= 0;	/* zabbix server startup time */

int	CONFIG_PROXYPOLLER_FORKS	= 1;	/* parameters for passive proxies */

/* how often zabbix server sends configuration data to proxy, in seconds */
int	CONFIG_PROXYCONFIG_FREQUENCY	= 3600;	/* 1h */
int	CONFIG_PROXYDATA_FREQUENCY	= 1;	/* 1s */

/* mutex for node syncs */
ZBX_MUTEX	node_sync_access;

/******************************************************************************
 *                                                                            *
 * Function: zbx_set_defaults                                                 *
 *                                                                            *
 * Purpose: set configuration defaults                                        *
 *                                                                            *
 * Author: Vladimir Levijev                                                   *
 *                                                                            *
 ******************************************************************************/
static void	zbx_set_defaults()
{
	CONFIG_SERVER_STARTUP_TIME = time(NULL);

	if (NULL == CONFIG_DBHOST)
		CONFIG_DBHOST = zbx_strdup(CONFIG_DBHOST, "localhost");

	if (NULL == CONFIG_SNMPTRAP_FILE)
		CONFIG_SNMPTRAP_FILE = zbx_strdup(CONFIG_SNMPTRAP_FILE, "/tmp/zabbix_traps.tmp");

	if (NULL == CONFIG_PID_FILE)
		CONFIG_PID_FILE = zbx_strdup(CONFIG_PID_FILE, "/tmp/zabbix_server.pid");

	if (NULL == CONFIG_ALERT_SCRIPTS_PATH)
		CONFIG_ALERT_SCRIPTS_PATH = zbx_strdup(CONFIG_ALERT_SCRIPTS_PATH, DATADIR "/zabbix/alertscripts");

	if (NULL == CONFIG_TMPDIR)
		CONFIG_TMPDIR = zbx_strdup(CONFIG_TMPDIR, "/tmp");

	if (NULL == CONFIG_FPING_LOCATION)
		CONFIG_FPING_LOCATION = zbx_strdup(CONFIG_FPING_LOCATION, "/usr/sbin/fping");

#ifdef HAVE_IPV6
	if (NULL == CONFIG_FPING6_LOCATION)
		CONFIG_FPING6_LOCATION = zbx_strdup(CONFIG_FPING6_LOCATION, "/usr/sbin/fping6");
#endif

	if (NULL == CONFIG_EXTERNALSCRIPTS)
		CONFIG_EXTERNALSCRIPTS = zbx_strdup(CONFIG_EXTERNALSCRIPTS, DATADIR "/zabbix/externalscripts");

	if (0 == CONFIG_NODEID)
		CONFIG_NODEWATCHER_FORKS = 0;

#ifdef HAVE_SQLITE3
	CONFIG_MAX_HOUSEKEEPER_DELETE = 0;
#endif

	if (1 == CONFIG_DISABLE_HOUSEKEEPING)
		CONFIG_HOUSEKEEPER_FORKS = 0;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_validate_config                                              *
 *                                                                            *
 * Purpose: validate configuration parameters                                 *
 *                                                                            *
 * Author: Vladimir Levijev                                                   *
 *                                                                            *
 ******************************************************************************/
static void	zbx_validate_config()
{
	if ((NULL == CONFIG_JAVA_GATEWAY || '\0' == *CONFIG_JAVA_GATEWAY) && CONFIG_JAVAPOLLER_FORKS > 0)
	{
		zabbix_log(LOG_LEVEL_CRIT, "JavaGateway not specified in config file or empty");
		exit(1);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_load_config                                                  *
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
static void	zbx_load_config()
{
	static struct cfg_line	cfg[] =
	{
		/* PARAMETER,			VAR,					TYPE,
			MANDATORY,	MIN,			MAX */
		{"StartDBSyncers",		&CONFIG_HISTSYNCER_FORKS,		TYPE_INT,
			PARM_OPT,	1,			100},
		{"StartDiscoverers",		&CONFIG_DISCOVERER_FORKS,		TYPE_INT,
			PARM_OPT,	0,			250},
		{"StartHTTPPollers",		&CONFIG_HTTPPOLLER_FORKS,		TYPE_INT,
			PARM_OPT,	0,			1000},
		{"StartPingers",		&CONFIG_PINGER_FORKS,			TYPE_INT,
			PARM_OPT,	0,			1000},
		{"StartPollers",		&CONFIG_POLLER_FORKS,			TYPE_INT,
			PARM_OPT,	0,			1000},
		{"StartPollersUnreachable",	&CONFIG_UNREACHABLE_POLLER_FORKS,	TYPE_INT,
			PARM_OPT,	0,			1000},
		{"StartIPMIPollers",		&CONFIG_IPMIPOLLER_FORKS,		TYPE_INT,
			PARM_OPT,	0,			1000},
		{"StartTrappers",		&CONFIG_TRAPPER_FORKS,			TYPE_INT,
			PARM_OPT,	0,			1000},
		{"StartJavaPollers",		&CONFIG_JAVAPOLLER_FORKS,		TYPE_INT,
			PARM_OPT,	0,			1000},
		{"JavaGateway",			&CONFIG_JAVA_GATEWAY,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"JavaGatewayPort",		&CONFIG_JAVA_GATEWAY_PORT,		TYPE_INT,
			PARM_OPT,	1024,			32767},
		{"SNMPTrapperFile",		&CONFIG_SNMPTRAP_FILE,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"StartSNMPTrapper",		&CONFIG_SNMPTRAPPER_FORKS,		TYPE_INT,
			PARM_OPT,	0,			1},
		{"CacheSize",			&CONFIG_CONF_CACHE_SIZE,		TYPE_INT,
			PARM_OPT,	128 * ZBX_KIBIBYTE,	0x7fffffff},	/* just below 2GB */
		{"HistoryCacheSize",		&CONFIG_HISTORY_CACHE_SIZE,		TYPE_INT,
			PARM_OPT,	128 * ZBX_KIBIBYTE,	0x7fffffff},	/* just below 2GB */
		{"TrendCacheSize",		&CONFIG_TRENDS_CACHE_SIZE,		TYPE_INT,
			PARM_OPT,	128 * ZBX_KIBIBYTE,	0x7fffffff},	/* just below 2GB */
		{"HistoryTextCacheSize",	&CONFIG_TEXT_CACHE_SIZE,		TYPE_INT,
			PARM_OPT,	128 * ZBX_KIBIBYTE,	0x7fffffff},	/* just below 2GB */
		{"CacheUpdateFrequency",	&CONFIG_CONFSYNCER_FREQUENCY,		TYPE_INT,
			PARM_OPT,	1,			SEC_PER_HOUR},
		{"HousekeepingFrequency",	&CONFIG_HOUSEKEEPING_FREQUENCY,		TYPE_INT,
			PARM_OPT,	1,			24},
		{"MaxHousekeeperDelete",	&CONFIG_MAX_HOUSEKEEPER_DELETE,		TYPE_INT,
			PARM_OPT,	0,			1000000},
		{"SenderFrequency",		&CONFIG_SENDER_FREQUENCY,		TYPE_INT,
			PARM_OPT,	5,			SEC_PER_HOUR},
		{"TmpDir",			&CONFIG_TMPDIR,				TYPE_STRING,
			PARM_OPT,	0,			0},
		{"FpingLocation",		&CONFIG_FPING_LOCATION,			TYPE_STRING,
			PARM_OPT,	0,			0},
#ifdef HAVE_IPV6
		{"Fping6Location",		&CONFIG_FPING6_LOCATION,		TYPE_STRING,
			PARM_OPT,	0,			0},
#endif
		{"Timeout",			&CONFIG_TIMEOUT,			TYPE_INT,
			PARM_OPT,	1,			30},
		{"TrapperTimeout",		&CONFIG_TRAPPER_TIMEOUT,		TYPE_INT,
			PARM_OPT,	1,			300},
		{"UnreachablePeriod",		&CONFIG_UNREACHABLE_PERIOD,		TYPE_INT,
			PARM_OPT,	1,			SEC_PER_HOUR},
		{"UnreachableDelay",		&CONFIG_UNREACHABLE_DELAY,		TYPE_INT,
			PARM_OPT,	1,			SEC_PER_HOUR},
		{"UnavailableDelay",		&CONFIG_UNAVAILABLE_DELAY,		TYPE_INT,
			PARM_OPT,	1,			SEC_PER_HOUR},
		{"ListenIP",			&CONFIG_LISTEN_IP,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"ListenPort",			&CONFIG_LISTEN_PORT,			TYPE_INT,
			PARM_OPT,	1024,			32767},
		{"SourceIP",			&CONFIG_SOURCE_IP,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"DisableHousekeeping",		&CONFIG_DISABLE_HOUSEKEEPING,		TYPE_INT,
			PARM_OPT,	0,			1},
		{"DebugLevel",			&CONFIG_LOG_LEVEL,			TYPE_INT,
			PARM_OPT,	0,			4},
		{"PidFile",			&CONFIG_PID_FILE,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"LogFile",			&CONFIG_LOG_FILE,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"LogFileSize",			&CONFIG_LOG_FILE_SIZE,			TYPE_INT,
			PARM_OPT,	0,			1024},
		{"AlertScriptsPath",		&CONFIG_ALERT_SCRIPTS_PATH,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"ExternalScripts",		&CONFIG_EXTERNALSCRIPTS,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"DBHost",			&CONFIG_DBHOST,				TYPE_STRING,
			PARM_OPT,	0,			0},
		{"DBName",			&CONFIG_DBNAME,				TYPE_STRING,
			PARM_MAND,	0,			0},
		{"DBSchema",			&CONFIG_DBSCHEMA,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"DBUser",			&CONFIG_DBUSER,				TYPE_STRING,
			PARM_OPT,	0,			0},
		{"DBPassword",			&CONFIG_DBPASSWORD,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"DBSocket",			&CONFIG_DBSOCKET,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"DBPort",			&CONFIG_DBPORT,				TYPE_INT,
			PARM_OPT,	1024,			65535},
		{"NodeID",			&CONFIG_NODEID,				TYPE_INT,
			PARM_OPT,	0,			999},
		{"NodeNoEvents",		&CONFIG_NODE_NOEVENTS,			TYPE_INT,
			PARM_OPT,	0,			1},
		{"NodeNoHistory",		&CONFIG_NODE_NOHISTORY,			TYPE_INT,
			PARM_OPT,	0,			1},
		{"SSHKeyLocation",		&CONFIG_SSH_KEY_LOCATION,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"LogSlowQueries",		&CONFIG_LOG_SLOW_QUERIES,		TYPE_INT,
			PARM_OPT,	0,			3600000},
		{"StartProxyPollers",		&CONFIG_PROXYPOLLER_FORKS,		TYPE_INT,
			PARM_OPT,	0,			250},
		{"ProxyConfigFrequency",	&CONFIG_PROXYCONFIG_FREQUENCY,		TYPE_INT,
			PARM_OPT,	1,			SEC_PER_WEEK},
		{"ProxyDataFrequency",		&CONFIG_PROXYDATA_FREQUENCY,		TYPE_INT,
			PARM_OPT,	1,			SEC_PER_HOUR},
		{NULL}
	};

	parse_cfg_file(CONFIG_FILE, cfg, ZBX_CFG_FILE_REQUIRED, ZBX_CFG_STRICT);

	zbx_set_defaults();

	zbx_validate_config();
}

#ifdef HAVE_SIGQUEUE
void	zbx_sigusr_handler(zbx_task_t task)
{
	switch (task)
	{
		case ZBX_TASK_CONFIG_CACHE_RELOAD:
			if (ZBX_PROCESS_TYPE_CONFSYNCER == process_type)
			{
				zabbix_log(LOG_LEVEL_WARNING, "forced reloading of the configuration cache");
				zbx_wakeup();
			}
			break;
		default:
			break;
	}
}
#endif

/******************************************************************************
 *                                                                            *
 * Function: main                                                             *
 *                                                                            *
 * Purpose: executes server processes                                         *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
int	main(int argc, char **argv)
{
	zbx_task_t	task = ZBX_TASK_START;
	char		ch = '\0';
	int		nodeid = 0;

	progname = get_program_name(argv[0]);

	/* parse the command-line */
	while ((char)EOF != (ch = (char)zbx_getopt_long(argc, argv, shortopts, longopts, NULL)))
	{
		switch (ch)
		{
			case 'c':
				CONFIG_FILE = zbx_strdup(CONFIG_FILE, zbx_optarg);
				break;
			case 'R':
				if (0 == strcmp(zbx_optarg, ZBX_CONFIG_CACHE_RELOAD))
					task = ZBX_TASK_CONFIG_CACHE_RELOAD;
				else
				{
					printf("invalid runtime control option: %s\n", zbx_optarg);
					exit(EXIT_FAILURE);
				}
				break;
			case 'h':
				help();
				exit(-1);
				break;
			case 'n':
				nodeid = (NULL == zbx_optarg ? 0 : atoi(zbx_optarg));
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

	if (NULL == CONFIG_FILE)
		CONFIG_FILE = zbx_strdup(CONFIG_FILE, SYSCONFDIR "/zabbix_server.conf");

	/* required for simple checks */
	init_metrics();

	zbx_load_config();

	if (ZBX_TASK_CONFIG_CACHE_RELOAD == task)
		exit(SUCCEED == zbx_sigusr_send(ZBX_TASK_CONFIG_CACHE_RELOAD) ? EXIT_SUCCESS : EXIT_FAILURE);

#ifdef HAVE_OPENIPMI
	init_ipmi_handler();
#endif

	switch (task)
	{
		case ZBX_TASK_CHANGE_NODEID:
			exit(SUCCEED == change_nodeid(0, nodeid) ? EXIT_SUCCESS : EXIT_FAILURE);
			break;
		default:
			break;
	}

	return daemon_start(CONFIG_ALLOW_ROOT);
}

int	MAIN_ZABBIX_ENTRY()
{
	DB_RESULT	result;
	DB_ROW		row;
	pid_t		pid;
	zbx_sock_t	listen_sock;
	int		i, server_num = 0, server_count = 0;

	if (NULL == CONFIG_LOG_FILE || '\0' == *CONFIG_LOG_FILE)
		zabbix_open_log(LOG_TYPE_SYSLOG, CONFIG_LOG_LEVEL, NULL);
	else
		zabbix_open_log(LOG_TYPE_FILE, CONFIG_LOG_LEVEL, CONFIG_LOG_FILE);

#ifdef	HAVE_SNMP
#	define SNMP_FEATURE_STATUS	"YES"
#else
#	define SNMP_FEATURE_STATUS	" NO"
#endif
#ifdef	HAVE_OPENIPMI
#	define IPMI_FEATURE_STATUS	"YES"
#else
#	define IPMI_FEATURE_STATUS	" NO"
#endif
#ifdef	HAVE_LIBCURL
#	define LIBCURL_FEATURE_STATUS	"YES"
#else
#	define LIBCURL_FEATURE_STATUS	" NO"
#endif
#ifdef	HAVE_JABBER
#	define JABBER_FEATURE_STATUS	"YES"
#else
#	define JABBER_FEATURE_STATUS	" NO"
#endif
#ifdef	HAVE_ODBC
#	define ODBC_FEATURE_STATUS	"YES"
#else
#	define ODBC_FEATURE_STATUS	" NO"
#endif
#ifdef	HAVE_SSH2
#	define SSH2_FEATURE_STATUS	"YES"
#else
#	define SSH2_FEATURE_STATUS	" NO"
#endif
#ifdef	HAVE_IPV6
#	define IPV6_FEATURE_STATUS	"YES"
#else
#	define IPV6_FEATURE_STATUS	" NO"
#endif

	zabbix_log(LOG_LEVEL_INFORMATION, "Starting Zabbix Server. Zabbix %s (revision %s).",
			ZABBIX_VERSION, ZABBIX_REVISION);

	zabbix_log(LOG_LEVEL_INFORMATION, "****** Enabled features ******");
	zabbix_log(LOG_LEVEL_INFORMATION, "SNMP monitoring:           " SNMP_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "IPMI monitoring:           " IPMI_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "WEB monitoring:            " LIBCURL_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "Jabber notifications:      " JABBER_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "Ez Texting notifications:  " LIBCURL_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "ODBC:                      " ODBC_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "SSH2 support:              " SSH2_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "IPv6 support:              " IPV6_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "******************************");

	if (0 != CONFIG_NODEID)
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "NodeID:                    %3d", CONFIG_NODEID);
		zabbix_log(LOG_LEVEL_INFORMATION, "******************************");
	}

#ifdef	HAVE_SQLITE3
	zbx_create_sqlite3_mutex(CONFIG_DBNAME);
#endif

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	if (0 != CONFIG_NODEID)
	{
		result = DBselect("select masterid from nodes where nodeid=%d", CONFIG_NODEID);

		if (NULL != (row = DBfetch(result)) && SUCCEED != DBis_null(row[0]))
			CONFIG_MASTER_NODEID = atoi(row[0]);
		DBfree_result(result);
	}

	init_database_cache();
	init_configuration_cache();
	init_selfmon_collector();

	zbx_create_itservices_lock();

	DCload_config();

	/* need to set trigger status to UNKNOWN since last run */
	DBupdate_triggers_status_after_restart();

	/* make initial configuration sync before worker processes are forked */
	DCsync_configuration();

	DBclose();

	if (ZBX_MUTEX_ERROR == zbx_mutex_create_force(&node_sync_access, ZBX_MUTEX_NODE_SYNC))
	{
		zbx_error("Unable to create mutex for node syncs");
		exit(FAIL);
	}

	threads_num = CONFIG_CONFSYNCER_FORKS + CONFIG_WATCHDOG_FORKS + CONFIG_POLLER_FORKS
			+ CONFIG_UNREACHABLE_POLLER_FORKS + CONFIG_TRAPPER_FORKS + CONFIG_PINGER_FORKS
			+ CONFIG_ALERTER_FORKS + CONFIG_HOUSEKEEPER_FORKS + CONFIG_TIMER_FORKS
			+ CONFIG_NODEWATCHER_FORKS + CONFIG_HTTPPOLLER_FORKS + CONFIG_DISCOVERER_FORKS
			+ CONFIG_HISTSYNCER_FORKS + CONFIG_ESCALATOR_FORKS + CONFIG_IPMIPOLLER_FORKS
			+ CONFIG_JAVAPOLLER_FORKS + CONFIG_SNMPTRAPPER_FORKS + CONFIG_PROXYPOLLER_FORKS
			+ CONFIG_SELFMON_FORKS;
	threads = zbx_calloc(threads, threads_num, sizeof(pid_t));

	if (0 < CONFIG_TRAPPER_FORKS)
	{
		if (FAIL == zbx_tcp_listen(&listen_sock, CONFIG_LISTEN_IP, (unsigned short)CONFIG_LISTEN_PORT))
		{
			zabbix_log(LOG_LEVEL_CRIT, "listener failed: %s", zbx_tcp_strerror());
			exit(1);
		}
	}

	for (i = 0; i < threads_num; i++)
	{
		if (0 == (pid = zbx_child_fork()))
		{
			server_num = i + 1;	/* child processes are numbered starting from 1 */
			break;
		}
		else
			threads[i] = pid;
	}

	if (0 == server_num)
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "server #0 started [main process]");

		while (-1 == wait(&i))	/* wait for any child to exit */
		{
			if (EINTR != errno)
			{
				zabbix_log(LOG_LEVEL_ERR, "failed to wait on child processes: %s", zbx_strerror(errno));
				break;
			}
		}

		/* all exiting child processes should be caught by signal handlers */
		THIS_SHOULD_NEVER_HAPPEN;

		zbx_on_exit();
	}
	else if (server_num <= (server_count += CONFIG_CONFSYNCER_FORKS))
	{
		/* !!! configuration syncer must be server #1 - child_signal_handler() uses threads[0] !!! */

		INIT_SERVER(ZBX_PROCESS_TYPE_CONFSYNCER, CONFIG_CONFSYNCER_FORKS);

		main_dbconfig_loop();
	}
	else if (server_num <= (server_count += CONFIG_WATCHDOG_FORKS))
	{
		INIT_SERVER(ZBX_PROCESS_TYPE_WATCHDOG, CONFIG_WATCHDOG_FORKS);

		main_watchdog_loop();
	}
	else if (server_num <= (server_count += CONFIG_POLLER_FORKS))
	{
#ifdef HAVE_SNMP
		init_snmp("zabbix_server");
#endif

		INIT_SERVER(ZBX_PROCESS_TYPE_POLLER, CONFIG_POLLER_FORKS);

		main_poller_loop(ZBX_POLLER_TYPE_NORMAL);
	}
	else if (server_num <= (server_count += CONFIG_UNREACHABLE_POLLER_FORKS))
	{
#ifdef HAVE_SNMP
		init_snmp("zabbix_server");
#endif

		INIT_SERVER(ZBX_PROCESS_TYPE_UNREACHABLE, CONFIG_UNREACHABLE_POLLER_FORKS);

		main_poller_loop(ZBX_POLLER_TYPE_UNREACHABLE);
	}
	else if (server_num <= (server_count += CONFIG_TRAPPER_FORKS))
	{
		INIT_SERVER(ZBX_PROCESS_TYPE_TRAPPER, CONFIG_TRAPPER_FORKS);

		main_trapper_loop(&listen_sock);
	}
	else if (server_num <= (server_count += CONFIG_PINGER_FORKS))
	{
		INIT_SERVER(ZBX_PROCESS_TYPE_PINGER, CONFIG_PINGER_FORKS);

		main_pinger_loop();
	}
	else if (server_num <= (server_count += CONFIG_ALERTER_FORKS))
	{
		INIT_SERVER(ZBX_PROCESS_TYPE_ALERTER, CONFIG_ALERTER_FORKS);

		main_alerter_loop();
	}
	else if (server_num <= (server_count += CONFIG_HOUSEKEEPER_FORKS))
	{
		INIT_SERVER(ZBX_PROCESS_TYPE_HOUSEKEEPER, CONFIG_HOUSEKEEPER_FORKS);

		main_housekeeper_loop();
	}
	else if (server_num <= (server_count += CONFIG_TIMER_FORKS))
	{
		INIT_SERVER(ZBX_PROCESS_TYPE_TIMER, CONFIG_TIMER_FORKS);

		main_timer_loop();
	}
	else if (server_num <= (server_count += CONFIG_NODEWATCHER_FORKS))
	{
		INIT_SERVER(ZBX_PROCESS_TYPE_NODEWATCHER, CONFIG_NODEWATCHER_FORKS);

		main_nodewatcher_loop();
	}
	else if (server_num <= (server_count += CONFIG_HTTPPOLLER_FORKS))
	{
		INIT_SERVER(ZBX_PROCESS_TYPE_HTTPPOLLER, CONFIG_HTTPPOLLER_FORKS);

		main_httppoller_loop();
	}
	else if (server_num <= (server_count += CONFIG_DISCOVERER_FORKS))
	{
#ifdef HAVE_SNMP
		init_snmp("zabbix_server");
#endif

		INIT_SERVER(ZBX_PROCESS_TYPE_DISCOVERER, CONFIG_DISCOVERER_FORKS);

		main_discoverer_loop();
	}
	else if (server_num <= (server_count += CONFIG_HISTSYNCER_FORKS))
	{
		INIT_SERVER(ZBX_PROCESS_TYPE_HISTSYNCER, CONFIG_HISTSYNCER_FORKS);

		main_dbsyncer_loop();
	}
	else if (server_num <= (server_count += CONFIG_ESCALATOR_FORKS))
	{
		INIT_SERVER(ZBX_PROCESS_TYPE_ESCALATOR, CONFIG_ESCALATOR_FORKS);

		main_escalator_loop();
	}
	else if (server_num <= (server_count += CONFIG_IPMIPOLLER_FORKS))
	{
		INIT_SERVER(ZBX_PROCESS_TYPE_IPMIPOLLER, CONFIG_IPMIPOLLER_FORKS);

		main_poller_loop(ZBX_POLLER_TYPE_IPMI);
	}
	else if (server_num <= (server_count += CONFIG_JAVAPOLLER_FORKS))
	{
		INIT_SERVER(ZBX_PROCESS_TYPE_JAVAPOLLER, CONFIG_JAVAPOLLER_FORKS);

		main_poller_loop(ZBX_POLLER_TYPE_JAVA);
	}
	else if (server_num <= (server_count += CONFIG_SNMPTRAPPER_FORKS))
	{
		INIT_SERVER(ZBX_PROCESS_TYPE_SNMPTRAPPER, CONFIG_SNMPTRAPPER_FORKS);

		main_snmptrapper_loop();
	}
	else if (server_num <= (server_count += CONFIG_PROXYPOLLER_FORKS))
	{
		INIT_SERVER(ZBX_PROCESS_TYPE_PROXYPOLLER, CONFIG_PROXYPOLLER_FORKS);

		main_proxypoller_loop();
	}
	else if (server_num <= (server_count += CONFIG_SELFMON_FORKS))
	{
		INIT_SERVER(ZBX_PROCESS_TYPE_SELFMON, CONFIG_SELFMON_FORKS);

		main_selfmon_loop();
	}

	return SUCCEED;
}

void	zbx_on_exit()
{
	zabbix_log(LOG_LEVEL_DEBUG, "zbx_on_exit() called");

	if (SUCCEED == DBtxn_ongoing())
		DBrollback();

	if (NULL != threads)
	{
		int		i;
		sigset_t	set;

		/* ignore SIGCHLD signals in order for zbx_sleep() to work */
		sigemptyset(&set);
		sigaddset(&set, SIGCHLD);
		sigprocmask(SIG_BLOCK, &set, NULL);

		for (i = 0; i < threads_num; i++)
		{
			if (threads[i])
			{
				kill(threads[i], SIGTERM);
				threads[i] = ZBX_THREAD_HANDLE_NULL;
			}
		}

		zbx_free(threads);
	}

	free_metrics();

	zbx_sleep(2);	/* wait for all child processes to exit */

	DBconnect(ZBX_DB_CONNECT_EXIT);
	free_database_cache();
	free_configuration_cache();

	zbx_destroy_itservices_lock();

	DBclose();

	zbx_mutex_destroy(&node_sync_access);

#ifdef HAVE_OPENIPMI
	free_ipmi_handler();
#endif

#ifdef HAVE_SQLITE3
	zbx_remove_sqlite3_mutex();
#endif

	free_selfmon_collector();

	zabbix_log(LOG_LEVEL_INFORMATION, "Zabbix Server stopped. Zabbix %s (revision %s).",
			ZABBIX_VERSION, ZABBIX_REVISION);

	zabbix_close_log();

	exit(SUCCEED);
}
