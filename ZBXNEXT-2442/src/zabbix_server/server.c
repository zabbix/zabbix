/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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
#include "zbxdbupgrade.h"
#include "log.h"
#include "zbxgetopt.h"
#include "mutexs.h"

#include "sysinfo.h"
#include "zbxmodules.h"
#include "zbxserver.h"

#include "daemon.h"
#include "zbxself.h"
#include "../libs/zbxnix/control.h"

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
#include "watchdog/watchdog.h"
#include "escalator/escalator.h"
#include "proxypoller/proxypoller.h"
#include "selfmon/selfmon.h"
#include "vmware/vmware.h"

#include "valuecache.h"
#include "setproctitle.h"

const char	*progname = NULL;
const char	title_message[] = "zabbix_server";
const char	syslog_app_name[] = "zabbix_server";
const char	*usage_message[] = {
	"[-c config-file]",
	"[-c config-file] -R runtime-option",
	"-h",
	"-V",
	NULL	/* end of text */
};

const char	*help_message[] = {
	"The core daemon of Zabbix software.",
	"",
	"Options:",
	"  -c --config config-file               Absolute path to the configuration file",
	"  -R --runtime-control runtime-option   Perform administrative functions",
	"",
	"    Runtime control options:",
	"      " ZBX_CONFIG_CACHE_RELOAD "               Reload configuration cache",
	"      " ZBX_HOUSEKEEPER_EXECUTE "               Execute the housekeeper",
	"      " ZBX_LOG_LEVEL_INCREASE "=target         Increase log level, affects all processes if target is not specified",
	"      " ZBX_LOG_LEVEL_DECREASE "=target         Decrease log level, affects all processes if target is not specified",
	"",
	"      Log level control targets:",
	"        pid                             Process identifier",
	"        process-type                    All processes of specified type (e.g., poller)",
	"        process-type,N                  Process type and number (e.g., poller,3)",
	"",
	"  -h --help                             Display this help message",
	"  -V --version                          Display version number",
	NULL	/* end of text */
};

/* COMMAND LINE OPTIONS */

/* long options */
static struct zbx_option	longopts[] =
{
	{"config",		1,	NULL,	'c'},
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
unsigned char	process_type		= ZBX_PROCESS_TYPE_UNKNOWN;
int		process_num		= 0;
int		server_num		= 0;

int	CONFIG_EMAIL_ALERTER_FORKS		= 1;
int	CONFIG_SCRIPT_ALERTER_FORKS		= 1;
int	CONFIG_SMS_ALERTER_FORKS		= 1;
int	CONFIG_JABBER_ALERTER_FORKS		= 1;
int	CONFIG_EZ_TEXTING_ALERTER_FORKS		= 1;
int	CONFIG_DISCOVERER_FORKS			= 1;
int	CONFIG_HOUSEKEEPER_FORKS		= 1;
int	CONFIG_PINGER_FORKS			= 1;
int	CONFIG_POLLER_FORKS			= 5;
int	CONFIG_UNREACHABLE_POLLER_FORKS		= 1;
int	CONFIG_HTTPPOLLER_FORKS			= 1;
int	CONFIG_IPMIPOLLER_FORKS			= 0;
int	CONFIG_TIMER_FORKS			= 1;
int	CONFIG_TRAPPER_FORKS			= 5;
int	CONFIG_SNMPTRAPPER_FORKS		= 0;
int	CONFIG_JAVAPOLLER_FORKS			= 0;
int	CONFIG_ESCALATOR_FORKS			= 1;
int	CONFIG_SELFMON_FORKS			= 1;
int	CONFIG_WATCHDOG_FORKS			= 1;
int	CONFIG_DATASENDER_FORKS			= 0;
int	CONFIG_HEARTBEAT_FORKS			= 0;
int	CONFIG_COLLECTOR_FORKS			= 0;
int	CONFIG_PASSIVE_FORKS			= 0;
int	CONFIG_ACTIVE_FORKS			= 0;

int	CONFIG_LISTEN_PORT		= ZBX_DEFAULT_SERVER_PORT;
char	*CONFIG_LISTEN_IP		= NULL;
char	*CONFIG_SOURCE_IP		= NULL;
int	CONFIG_TRAPPER_TIMEOUT		= 300;

int	CONFIG_HOUSEKEEPING_FREQUENCY	= 1;
int	CONFIG_MAX_HOUSEKEEPER_DELETE	= 5000;		/* applies for every separate field value */
int	CONFIG_SENDER_FREQUENCY		= 30;
int	CONFIG_HISTSYNCER_FORKS		= 4;
int	CONFIG_HISTSYNCER_FREQUENCY	= 5;
int	CONFIG_CONFSYNCER_FORKS		= 1;
int	CONFIG_CONFSYNCER_FREQUENCY	= 60;

int	CONFIG_VMWARE_FORKS		= 0;
int	CONFIG_VMWARE_FREQUENCY		= 60;
int	CONFIG_VMWARE_PERF_FREQUENCY	= 60;
int	CONFIG_VMWARE_TIMEOUT		= 10;

zbx_uint64_t	CONFIG_CONF_CACHE_SIZE		= 8 * ZBX_MEBIBYTE;
zbx_uint64_t	CONFIG_HISTORY_CACHE_SIZE	= 8 * ZBX_MEBIBYTE;
zbx_uint64_t	CONFIG_TRENDS_CACHE_SIZE	= 4 * ZBX_MEBIBYTE;
zbx_uint64_t	CONFIG_TEXT_CACHE_SIZE		= 16 * ZBX_MEBIBYTE;
zbx_uint64_t	CONFIG_VALUE_CACHE_SIZE		= 8 * ZBX_MEBIBYTE;
zbx_uint64_t	CONFIG_VMWARE_CACHE_SIZE	= 8 * ZBX_MEBIBYTE;

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

char	*CONFIG_LOAD_MODULE_PATH	= NULL;
char	**CONFIG_LOAD_MODULE		= NULL;

char	*CONFIG_USER			= NULL;

/* web monitoring */
#ifdef HAVE_LIBCURL
char	*CONFIG_SSL_CA_LOCATION		= NULL;
char	*CONFIG_SSL_CERT_LOCATION	= NULL;
char	*CONFIG_SSL_KEY_LOCATION	= NULL;
#endif

int	get_process_info_by_thread(int local_server_num, unsigned char *local_process_type, int *local_process_num);

int	get_process_info_by_thread(int local_server_num, unsigned char *local_process_type, int *local_process_num)
{
	int	server_count = 0;

	if (0 == local_server_num)
	{
		/* fail if the main process is queried */
		return FAIL;
	}
	else if (local_server_num <= (server_count += CONFIG_CONFSYNCER_FORKS))
	{
		*local_process_type = ZBX_PROCESS_TYPE_CONFSYNCER;
		*local_process_num = local_server_num - server_count + CONFIG_CONFSYNCER_FORKS;
	}
	else if (local_server_num <= (server_count += CONFIG_WATCHDOG_FORKS))
	{
		*local_process_type = ZBX_PROCESS_TYPE_WATCHDOG;
		*local_process_num = local_server_num - server_count + CONFIG_WATCHDOG_FORKS;
	}
	else if (local_server_num <= (server_count += CONFIG_POLLER_FORKS))
	{
		*local_process_type = ZBX_PROCESS_TYPE_POLLER;
		*local_process_num = local_server_num - server_count + CONFIG_POLLER_FORKS;
	}
	else if (local_server_num <= (server_count += CONFIG_UNREACHABLE_POLLER_FORKS))
	{
		*local_process_type = ZBX_PROCESS_TYPE_UNREACHABLE;
		*local_process_num = local_server_num - server_count + CONFIG_UNREACHABLE_POLLER_FORKS;
	}
	else if (local_server_num <= (server_count += CONFIG_TRAPPER_FORKS))
	{
		*local_process_type = ZBX_PROCESS_TYPE_TRAPPER;
		*local_process_num = local_server_num - server_count + CONFIG_TRAPPER_FORKS;
	}
	else if (local_server_num <= (server_count += CONFIG_PINGER_FORKS))
	{
		*local_process_type = ZBX_PROCESS_TYPE_PINGER;
		*local_process_num = local_server_num - server_count + CONFIG_PINGER_FORKS;
	}
	else if (local_server_num <= (server_count += CONFIG_EMAIL_ALERTER_FORKS))
	{
		*local_process_type = ZBX_PROCESS_TYPE_EMAIL_ALERTER;
		*local_process_num = local_server_num - server_count + CONFIG_EMAIL_ALERTER_FORKS;
	}
	else if (local_server_num <= (server_count += CONFIG_SCRIPT_ALERTER_FORKS))
	{
		*local_process_type = ZBX_PROCESS_TYPE_SCRIPT_ALERTER;
		*local_process_num = local_server_num - server_count + CONFIG_SCRIPT_ALERTER_FORKS;
	}
	else if (local_server_num <= (server_count += CONFIG_SMS_ALERTER_FORKS))
	{
		*local_process_type = ZBX_PROCESS_TYPE_SMS_ALERTER;
		*local_process_num = local_server_num - server_count + CONFIG_SMS_ALERTER_FORKS;
	}
	else if (local_server_num <= (server_count += CONFIG_JABBER_ALERTER_FORKS))
	{
		*local_process_type = ZBX_PROCESS_TYPE_JABBER_ALERTER;
		*local_process_num = local_server_num - server_count + CONFIG_JABBER_ALERTER_FORKS;
	}
	else if (local_server_num <= (server_count += CONFIG_EZ_TEXTING_ALERTER_FORKS))
	{
		*local_process_type = ZBX_PROCESS_TYPE_EZ_TEXTING_ALERTER;
		*local_process_num = local_server_num - server_count + CONFIG_EZ_TEXTING_ALERTER_FORKS;
	}
	else if (local_server_num <= (server_count += CONFIG_HOUSEKEEPER_FORKS))
	{
		*local_process_type = ZBX_PROCESS_TYPE_HOUSEKEEPER;
		*local_process_num = local_server_num - server_count + CONFIG_HOUSEKEEPER_FORKS;
	}
	else if (local_server_num <= (server_count += CONFIG_TIMER_FORKS))
	{
		*local_process_type = ZBX_PROCESS_TYPE_TIMER;
		*local_process_num = local_server_num - server_count + CONFIG_TIMER_FORKS;
	}
	else if (local_server_num <= (server_count += CONFIG_HTTPPOLLER_FORKS))
	{
		*local_process_type = ZBX_PROCESS_TYPE_HTTPPOLLER;
		*local_process_num = local_server_num - server_count + CONFIG_HTTPPOLLER_FORKS;
	}
	else if (local_server_num <= (server_count += CONFIG_DISCOVERER_FORKS))
	{
		*local_process_type = ZBX_PROCESS_TYPE_DISCOVERER;
		*local_process_num = local_server_num - server_count + CONFIG_DISCOVERER_FORKS;
	}
	else if (local_server_num <= (server_count += CONFIG_HISTSYNCER_FORKS))
	{
		*local_process_type = ZBX_PROCESS_TYPE_HISTSYNCER;
		*local_process_num = local_server_num - server_count + CONFIG_HISTSYNCER_FORKS;
	}
	else if (local_server_num <= (server_count += CONFIG_ESCALATOR_FORKS))
	{
		*local_process_type = ZBX_PROCESS_TYPE_ESCALATOR;
		*local_process_num = local_server_num - server_count + CONFIG_ESCALATOR_FORKS;
	}
	else if (local_server_num <= (server_count += CONFIG_IPMIPOLLER_FORKS))
	{
		*local_process_type = ZBX_PROCESS_TYPE_IPMIPOLLER;
		*local_process_num = local_server_num - server_count + CONFIG_IPMIPOLLER_FORKS;
	}
	else if (local_server_num <= (server_count += CONFIG_JAVAPOLLER_FORKS))
	{
		*local_process_type = ZBX_PROCESS_TYPE_JAVAPOLLER;
		*local_process_num = local_server_num - server_count + CONFIG_JAVAPOLLER_FORKS;
	}
	else if (local_server_num <= (server_count += CONFIG_SNMPTRAPPER_FORKS))
	{
		*local_process_type = ZBX_PROCESS_TYPE_SNMPTRAPPER;
		*local_process_num = local_server_num - server_count + CONFIG_SNMPTRAPPER_FORKS;
	}
	else if (local_server_num <= (server_count += CONFIG_PROXYPOLLER_FORKS))
	{
		*local_process_type = ZBX_PROCESS_TYPE_PROXYPOLLER;
		*local_process_num = local_server_num - server_count + CONFIG_PROXYPOLLER_FORKS;
	}
	else if (local_server_num <= (server_count += CONFIG_SELFMON_FORKS))
	{
		*local_process_type = ZBX_PROCESS_TYPE_SELFMON;
		*local_process_num = local_server_num - server_count + CONFIG_SELFMON_FORKS;
	}
	else if (local_server_num <= (server_count += CONFIG_VMWARE_FORKS))
	{
		*local_process_type = ZBX_PROCESS_TYPE_VMWARE;
		*local_process_num = local_server_num - server_count + CONFIG_VMWARE_FORKS;
	}
	else
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_set_defaults                                                 *
 *                                                                            *
 * Purpose: set configuration defaults                                        *
 *                                                                            *
 * Author: Vladimir Levijev                                                   *
 *                                                                            *
 ******************************************************************************/
static void	zbx_set_defaults(void)
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

	if (NULL == CONFIG_LOAD_MODULE_PATH)
		CONFIG_LOAD_MODULE_PATH = zbx_strdup(CONFIG_LOAD_MODULE_PATH, LIBDIR "/modules");

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
#ifdef HAVE_LIBCURL
	if (NULL == CONFIG_SSL_CERT_LOCATION)
		CONFIG_SSL_CERT_LOCATION = zbx_strdup(CONFIG_SSL_CERT_LOCATION, DATADIR "/zabbix/ssl/certs");

	if (NULL == CONFIG_SSL_KEY_LOCATION)
		CONFIG_SSL_KEY_LOCATION = zbx_strdup(CONFIG_SSL_KEY_LOCATION, DATADIR "/zabbix/ssl/keys");
#endif

#ifdef HAVE_SQLITE3
	CONFIG_MAX_HOUSEKEEPER_DELETE = 0;
#endif
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
static void	zbx_validate_config(void)
{
	if (0 == CONFIG_UNREACHABLE_POLLER_FORKS && 0 != CONFIG_POLLER_FORKS + CONFIG_IPMIPOLLER_FORKS +
			CONFIG_JAVAPOLLER_FORKS)
	{
		zabbix_log(LOG_LEVEL_CRIT, "\"StartPollersUnreachable\" configuration parameter must not be 0"
				" if regular, IPMI or Java pollers are started");
		exit(EXIT_FAILURE);
	}

	if ((NULL == CONFIG_JAVA_GATEWAY || '\0' == *CONFIG_JAVA_GATEWAY) && CONFIG_JAVAPOLLER_FORKS > 0)
	{
		zabbix_log(LOG_LEVEL_CRIT, "\"JavaGateway\" configuration parameter is not specified or empty");
		exit(EXIT_FAILURE);
	}

	if (0 != CONFIG_VALUE_CACHE_SIZE && 128 * ZBX_KIBIBYTE > CONFIG_VALUE_CACHE_SIZE)
	{
		zabbix_log(LOG_LEVEL_CRIT, "\"ValueCacheSize\" configuration parameter must be either 0"
				" or greater than 128KB");
		exit(EXIT_FAILURE);
	}

	if (NULL != CONFIG_SOURCE_IP && ('\0' == *CONFIG_SOURCE_IP || SUCCEED != is_ip(CONFIG_SOURCE_IP)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "invalid \"SourceIP\" configuration parameter: '%s'", CONFIG_SOURCE_IP);
		exit(EXIT_FAILURE);
	}
#if !defined(HAVE_LIBXML2) || !defined(HAVE_LIBCURL)
	if (0 != CONFIG_VMWARE_FORKS)
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start vmware collector because Zabbix server is built without VMware"
				" support");
		exit(EXIT_FAILURE);
	}
#endif
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
static void	zbx_load_config(void)
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
		{"StartTimers",			&CONFIG_TIMER_FORKS,			TYPE_INT,
			PARM_OPT,	1,			1000},
		{"StartTrappers",		&CONFIG_TRAPPER_FORKS,			TYPE_INT,
			PARM_OPT,	0,			1000},
		{"StartJavaPollers",		&CONFIG_JAVAPOLLER_FORKS,		TYPE_INT,
			PARM_OPT,	0,			1000},
		{"StartEmailAlerters",		&CONFIG_EMAIL_ALERTER_FORKS,		TYPE_INT,
			PARM_OPT,	0,			1000},
		{"StartScriptAlerters",		&CONFIG_SCRIPT_ALERTER_FORKS,		TYPE_INT,
			PARM_OPT,	0,			1000},
		{"StartSMSAlerters",		&CONFIG_SMS_ALERTER_FORKS,		TYPE_INT,
			PARM_OPT,	0,			1000},
		{"StartJabberAlerters",		&CONFIG_JABBER_ALERTER_FORKS,		TYPE_INT,
			PARM_OPT,	0,			1000},
		{"StartEzTextingAlerters",	&CONFIG_EZ_TEXTING_ALERTER_FORKS,	TYPE_INT,
			PARM_OPT,	0,			1000},
		{"JavaGateway",			&CONFIG_JAVA_GATEWAY,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"JavaGatewayPort",		&CONFIG_JAVA_GATEWAY_PORT,		TYPE_INT,
			PARM_OPT,	1024,			32767},
		{"SNMPTrapperFile",		&CONFIG_SNMPTRAP_FILE,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"StartSNMPTrapper",		&CONFIG_SNMPTRAPPER_FORKS,		TYPE_INT,
			PARM_OPT,	0,			1},
		{"CacheSize",			&CONFIG_CONF_CACHE_SIZE,		TYPE_UINT64,
			PARM_OPT,	128 * ZBX_KIBIBYTE,	__UINT64_C(8) * ZBX_GIBIBYTE},
		{"HistoryCacheSize",		&CONFIG_HISTORY_CACHE_SIZE,		TYPE_UINT64,
			PARM_OPT,	128 * ZBX_KIBIBYTE,	__UINT64_C(2) * ZBX_GIBIBYTE},
		{"TrendCacheSize",		&CONFIG_TRENDS_CACHE_SIZE,		TYPE_UINT64,
			PARM_OPT,	128 * ZBX_KIBIBYTE,	__UINT64_C(2) * ZBX_GIBIBYTE},
		{"HistoryTextCacheSize",	&CONFIG_TEXT_CACHE_SIZE,		TYPE_UINT64,
			PARM_OPT,	128 * ZBX_KIBIBYTE,	__UINT64_C(2) * ZBX_GIBIBYTE},
		{"ValueCacheSize",		&CONFIG_VALUE_CACHE_SIZE,		TYPE_UINT64,
			PARM_OPT,	0,			__UINT64_C(64) * ZBX_GIBIBYTE},
		{"CacheUpdateFrequency",	&CONFIG_CONFSYNCER_FREQUENCY,		TYPE_INT,
			PARM_OPT,	1,			SEC_PER_HOUR},
		{"HousekeepingFrequency",	&CONFIG_HOUSEKEEPING_FREQUENCY,		TYPE_INT,
			PARM_OPT,	0,			24},
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
		{"ListenIP",			&CONFIG_LISTEN_IP,			TYPE_STRING_LIST,
			PARM_OPT,	0,			0},
		{"ListenPort",			&CONFIG_LISTEN_PORT,			TYPE_INT,
			PARM_OPT,	1024,			32767},
		{"SourceIP",			&CONFIG_SOURCE_IP,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"DebugLevel",			&CONFIG_LOG_LEVEL,			TYPE_INT,
			PARM_OPT,	0,			5},
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
		{"LoadModulePath",		&CONFIG_LOAD_MODULE_PATH,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"LoadModule",			&CONFIG_LOAD_MODULE,			TYPE_MULTISTRING,
			PARM_OPT,	0,			0},
		{"StartVMwareCollectors",	&CONFIG_VMWARE_FORKS,			TYPE_INT,
			PARM_OPT,	0,			250},
		{"VMwareFrequency",		&CONFIG_VMWARE_FREQUENCY,		TYPE_INT,
			PARM_OPT,	10,			SEC_PER_DAY},
		{"VMwarePerfFrequency",		&CONFIG_VMWARE_PERF_FREQUENCY,		TYPE_INT,
			PARM_OPT,	10,			SEC_PER_DAY},
		{"VMwareCacheSize",		&CONFIG_VMWARE_CACHE_SIZE,		TYPE_UINT64,
			PARM_OPT,	256 * ZBX_KIBIBYTE,	__UINT64_C(2) * ZBX_GIBIBYTE},
		{"VMwareTimeout",		&CONFIG_VMWARE_TIMEOUT,			TYPE_INT,
			PARM_OPT,	1,			300},
		{"AllowRoot",			&CONFIG_ALLOW_ROOT,			TYPE_INT,
			PARM_OPT,	0,			1},
		{"User",			&CONFIG_USER,				TYPE_STRING,
			PARM_OPT,	0,			0},
#ifdef HAVE_LIBCURL
		{"SSLCALocation",		&CONFIG_SSL_CA_LOCATION,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"SSLCertLocation",		&CONFIG_SSL_CERT_LOCATION,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"SSLKeyLocation",		&CONFIG_SSL_KEY_LOCATION,		TYPE_STRING,
			PARM_OPT,	0,			0},
#endif
		{NULL}
	};

	/* initialize multistrings */
	zbx_strarr_init(&CONFIG_LOAD_MODULE);

	parse_cfg_file(CONFIG_FILE, cfg, ZBX_CFG_FILE_REQUIRED, ZBX_CFG_STRICT);

	zbx_set_defaults();

	zbx_validate_config();
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_free_config                                                  *
 *                                                                            *
 * Purpose: free configuration memory                                         *
 *                                                                            *
 ******************************************************************************/
static void	zbx_free_config(void)
{
	zbx_strarr_free(CONFIG_LOAD_MODULE);
}

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
	ZBX_TASK_EX	t = {ZBX_TASK_START};
	char		ch = '\0';
	int		opt_c = 0, opt_r = 0;

#if defined(PS_OVERWRITE_ARGV) || defined(PS_PSTAT_ARGV)
	argv = setproctitle_save_env(argc, argv);
#endif
	progname = get_program_name(argv[0]);

	/* parse the command-line */
	while ((char)EOF != (ch = (char)zbx_getopt_long(argc, argv, shortopts, longopts, NULL)))
	{
		switch (ch)
		{
			case 'c':
				opt_c++;
				if (NULL == CONFIG_FILE)
					CONFIG_FILE = zbx_strdup(CONFIG_FILE, zbx_optarg);
				break;
			case 'R':
				opt_r++;
				if (SUCCEED != parse_rtc_options(zbx_optarg, daemon_type, &t.flags))
					exit(EXIT_FAILURE);

				t.task = ZBX_TASK_RUNTIME_CONTROL;
				break;
			case 'h':
				help();
				exit(EXIT_SUCCESS);
				break;
			case 'V':
				version();
				exit(EXIT_SUCCESS);
				break;
			default:
				usage();
				exit(EXIT_FAILURE);
				break;
		}
	}

	/* every option may be specified only once */
	if (1 < opt_c || 1 < opt_r)
	{
		if (1 < opt_c)
			zbx_error("option \"-c\" specified multiple times");
		if (1 < opt_r)
			zbx_error("option \"-R\" specified multiple times");

		exit(EXIT_FAILURE);
	}

	/* Parameters which are not option values are invalid. The check relies on zbx_getopt_internal() which */
	/* always permutes command line arguments regardless of POSIXLY_CORRECT environment variable. */
	if (argc > zbx_optind)
	{
		int	i;

		for (i = zbx_optind; i < argc; i++)
			zbx_error("invalid parameter \"%s\"", argv[i]);

		exit(EXIT_FAILURE);
	}

	if (NULL == CONFIG_FILE)
		CONFIG_FILE = zbx_strdup(CONFIG_FILE, SYSCONFDIR "/zabbix_server.conf");

	/* required for simple checks */
	init_metrics();

	zbx_load_config();

	if (ZBX_TASK_RUNTIME_CONTROL == t.task)
		exit(SUCCEED == zbx_sigusr_send(t.flags) ? EXIT_SUCCESS : EXIT_FAILURE);

#ifdef HAVE_OPENIPMI
	init_ipmi_handler();
#endif

	return daemon_start(CONFIG_ALLOW_ROOT, CONFIG_USER);
}

int	MAIN_ZABBIX_ENTRY()
{
	zbx_socket_t	listen_sock;
	int		i, db_type;

	if (NULL == CONFIG_LOG_FILE || '\0' == *CONFIG_LOG_FILE)
		zabbix_open_log(LOG_TYPE_SYSLOG, CONFIG_LOG_LEVEL, NULL);
	else
		zabbix_open_log(LOG_TYPE_FILE, CONFIG_LOG_LEVEL, CONFIG_LOG_FILE);

#ifdef HAVE_NETSNMP
#	define SNMP_FEATURE_STATUS	"YES"
#else
#	define SNMP_FEATURE_STATUS	" NO"
#endif
#ifdef HAVE_OPENIPMI
#	define IPMI_FEATURE_STATUS	"YES"
#else
#	define IPMI_FEATURE_STATUS	" NO"
#endif
#ifdef HAVE_LIBCURL
#	define LIBCURL_FEATURE_STATUS	"YES"
#else
#	define LIBCURL_FEATURE_STATUS	" NO"
#endif
#if defined(HAVE_LIBCURL) && defined(HAVE_LIBXML2)
#	define VMWARE_FEATURE_STATUS	"YES"
#else
#	define VMWARE_FEATURE_STATUS	" NO"
#endif
#ifdef HAVE_SMTP_AUTHENTICATION
#	define SMTP_AUTH_FEATURE_STATUS	"YES"
#else
#	define SMTP_AUTH_FEATURE_STATUS	" NO"
#endif
#ifdef HAVE_JABBER
#	define JABBER_FEATURE_STATUS	"YES"
#else
#	define JABBER_FEATURE_STATUS	" NO"
#endif
#ifdef HAVE_UNIXODBC
#	define ODBC_FEATURE_STATUS	"YES"
#else
#	define ODBC_FEATURE_STATUS	" NO"
#endif
#ifdef HAVE_SSH2
#	define SSH2_FEATURE_STATUS	"YES"
#else
#	define SSH2_FEATURE_STATUS	" NO"
#endif
#ifdef HAVE_IPV6
#	define IPV6_FEATURE_STATUS	"YES"
#else
#	define IPV6_FEATURE_STATUS	" NO"
#endif

	zabbix_log(LOG_LEVEL_INFORMATION, "Starting Zabbix Server. Zabbix %s (revision %s).",
			ZABBIX_VERSION, ZABBIX_REVISION);

	zabbix_log(LOG_LEVEL_INFORMATION, "****** Enabled features ******");
	zabbix_log(LOG_LEVEL_INFORMATION, "SNMP monitoring:           " SNMP_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "IPMI monitoring:           " IPMI_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "Web monitoring:            " LIBCURL_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "VMware monitoring:         " VMWARE_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "SMTP authentication:       " SMTP_AUTH_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "Jabber notifications:      " JABBER_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "Ez Texting notifications:  " LIBCURL_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "ODBC:                      " ODBC_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "SSH2 support:              " SSH2_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "IPv6 support:              " IPV6_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "******************************");

	zabbix_log(LOG_LEVEL_INFORMATION, "using configuration file: %s", CONFIG_FILE);

	if (FAIL == load_modules(CONFIG_LOAD_MODULE_PATH, CONFIG_LOAD_MODULE, CONFIG_TIMEOUT, 1))
	{
		zabbix_log(LOG_LEVEL_CRIT, "loading modules failed, exiting...");
		exit(EXIT_FAILURE);
	}

	zbx_free_config();

	init_database_cache();
	init_configuration_cache();
	init_selfmon_collector();

	/* initialize vmware support */
	if (0 != CONFIG_VMWARE_FORKS)
		zbx_vmware_init();

	/* initialize history value cache */
	zbx_vc_init();

	zbx_create_itservices_lock();

#ifdef	HAVE_SQLITE3
	zbx_create_sqlite3_mutex();
#endif

	if (ZBX_DB_UNKNOWN == (db_type = zbx_db_get_database_type()))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot use database \"%s\": database is not a Zabbix database",
				CONFIG_DBNAME);
		exit(EXIT_FAILURE);
	}
	else if (ZBX_DB_SERVER != db_type)
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot use database \"%s\": Zabbix server cannot work with a"
				" Zabbix proxy database", CONFIG_DBNAME);
		exit(EXIT_FAILURE);
	}

	if (SUCCEED != DBcheck_version())
		exit(EXIT_FAILURE);

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	DCload_config();

	/* make initial configuration sync before worker processes are forked */
	DCsync_configuration();

	DBclose();

	threads_num = CONFIG_CONFSYNCER_FORKS + CONFIG_WATCHDOG_FORKS + CONFIG_POLLER_FORKS
			+ CONFIG_UNREACHABLE_POLLER_FORKS + CONFIG_TRAPPER_FORKS + CONFIG_PINGER_FORKS
			+ CONFIG_EMAIL_ALERTER_FORKS + CONFIG_SCRIPT_ALERTER_FORKS + CONFIG_SMS_ALERTER_FORKS
			+ CONFIG_JABBER_ALERTER_FORKS + CONFIG_EZ_TEXTING_ALERTER_FORKS
			+ CONFIG_HOUSEKEEPER_FORKS + CONFIG_TIMER_FORKS + CONFIG_HTTPPOLLER_FORKS
			+ CONFIG_DISCOVERER_FORKS + CONFIG_HISTSYNCER_FORKS + CONFIG_ESCALATOR_FORKS
			+ CONFIG_IPMIPOLLER_FORKS + CONFIG_JAVAPOLLER_FORKS + CONFIG_SNMPTRAPPER_FORKS
			+ CONFIG_PROXYPOLLER_FORKS + CONFIG_SELFMON_FORKS + CONFIG_VMWARE_FORKS;
	threads = zbx_calloc(threads, threads_num, sizeof(pid_t));

	if (0 != CONFIG_TRAPPER_FORKS)
	{
		if (FAIL == zbx_tcp_listen(&listen_sock, CONFIG_LISTEN_IP, (unsigned short)CONFIG_LISTEN_PORT))
		{
			zabbix_log(LOG_LEVEL_CRIT, "listener failed: %s", zbx_socket_strerror());
			exit(EXIT_FAILURE);
		}
	}

	zabbix_log(LOG_LEVEL_INFORMATION, "server #0 started [main process]");

	for (i = 0; i < threads_num; i++)
	{
		zbx_thread_args_t	thread_args;
		unsigned char		poller_type;

		if (FAIL == get_process_info_by_thread(i + 1, &thread_args.process_type, &thread_args.process_num))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
		}

		thread_args.server_num = i + 1;
		thread_args.args = NULL;

		switch (thread_args.process_type)
		{
			case ZBX_PROCESS_TYPE_CONFSYNCER:
				threads[i] = zbx_thread_start(dbconfig_thread, &thread_args);
				break;
			case ZBX_PROCESS_TYPE_WATCHDOG:
				threads[i] = zbx_thread_start(watchdog_thread, &thread_args);
				break;
			case ZBX_PROCESS_TYPE_POLLER:
				poller_type = ZBX_PROCESS_TYPE_POLLER;
				thread_args.args = &poller_type;
				threads[i] = zbx_thread_start(poller_thread, &thread_args);
				break;
			case ZBX_PROCESS_TYPE_UNREACHABLE:
				poller_type = ZBX_PROCESS_TYPE_UNREACHABLE;
				thread_args.args = &poller_type;
				threads[i] = zbx_thread_start(poller_thread, &thread_args);
				break;
			case ZBX_PROCESS_TYPE_TRAPPER:
				thread_args.args = &listen_sock;
				threads[i] = zbx_thread_start(trapper_thread, &thread_args);
				break;
			case ZBX_PROCESS_TYPE_PINGER:
				threads[i] = zbx_thread_start(pinger_thread, &thread_args);
				break;
			case ZBX_PROCESS_TYPE_EMAIL_ALERTER:
			case ZBX_PROCESS_TYPE_SCRIPT_ALERTER:
			case ZBX_PROCESS_TYPE_SMS_ALERTER:
			case ZBX_PROCESS_TYPE_JABBER_ALERTER:
			case ZBX_PROCESS_TYPE_EZ_TEXTING_ALERTER:
				threads[i] = zbx_thread_start(alerter_thread, &thread_args);
				break;
			case ZBX_PROCESS_TYPE_HOUSEKEEPER:
				threads[i] = zbx_thread_start(housekeeper_thread, &thread_args);
				break;
			case ZBX_PROCESS_TYPE_TIMER:
				threads[i] = zbx_thread_start(timer_thread, &thread_args);
				break;
			case ZBX_PROCESS_TYPE_HTTPPOLLER:
				threads[i] = zbx_thread_start(httppoller_thread, &thread_args);
				break;
			case ZBX_PROCESS_TYPE_DISCOVERER:
				threads[i] = zbx_thread_start(discoverer_thread, &thread_args);
				break;
			case ZBX_PROCESS_TYPE_HISTSYNCER:
				threads[i] = zbx_thread_start(dbsyncer_thread, &thread_args);
				break;
			case ZBX_PROCESS_TYPE_ESCALATOR:
				threads[i] = zbx_thread_start(escalator_thread, &thread_args);
				break;
			case ZBX_PROCESS_TYPE_IPMIPOLLER:
				poller_type = ZBX_PROCESS_TYPE_IPMIPOLLER;
				thread_args.args = &poller_type;
				threads[i] = zbx_thread_start(poller_thread, &thread_args);
				break;
			case ZBX_PROCESS_TYPE_JAVAPOLLER:
				poller_type = ZBX_PROCESS_TYPE_JAVAPOLLER;
				thread_args.args = &poller_type;
				threads[i] = zbx_thread_start(poller_thread, &thread_args);
				break;
			case ZBX_PROCESS_TYPE_SNMPTRAPPER:
				threads[i] = zbx_thread_start(snmptrapper_thread, &thread_args);
				break;
			case ZBX_PROCESS_TYPE_PROXYPOLLER:
				threads[i] = zbx_thread_start(proxypoller_thread, &thread_args);
				break;
			case ZBX_PROCESS_TYPE_SELFMON:
				threads[i] = zbx_thread_start(selfmon_thread, &thread_args);
				break;
			case ZBX_PROCESS_TYPE_VMWARE:
				threads[i] = zbx_thread_start(vmware_thread, &thread_args);
				break;
		}
	}

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

	return SUCCEED;
}

void	zbx_on_exit(void)
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

	DBclose();

	free_configuration_cache();

	/* free history value cache */
	zbx_vc_destroy();

	zbx_destroy_itservices_lock();

#ifdef HAVE_OPENIPMI
	free_ipmi_handler();
#endif

#ifdef HAVE_SQLITE3
	zbx_remove_sqlite3_mutex();
#endif

	/* free vmware support */
	if (0 != CONFIG_VMWARE_FORKS)
		zbx_vmware_destroy();

	free_selfmon_collector();

	unload_modules();

	zabbix_log(LOG_LEVEL_INFORMATION, "Zabbix Server stopped. Zabbix %s (revision %s).",
			ZABBIX_VERSION, ZABBIX_REVISION);

	zabbix_close_log();

#if defined(PS_OVERWRITE_ARGV)
	setproctitle_free_env();
#endif

	exit(EXIT_SUCCESS);
}
