/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "config.h"

#ifdef HAVE_SQLITE3
#	error SQLite is not supported as a main Zabbix database backend.
#endif

#include "export.h"
#include "zbxself.h"
#include "sighandler.h"

#include "cfg.h"
#include "zbxdbupgrade.h"
#include "log.h"
#include "zbxgetopt.h"
#include "mutexs.h"
#include "zbxmodules.h"
#include "zbxnix.h"
#include "daemon.h"

#include "alerter/alerter.h"
#include "alerter/alert_manager.h"
#include "alerter/alert_syncer.h"
#include "dbsyncer/dbsyncer.h"
#include "dbconfig/dbconfig.h"
#include "discoverer/discoverer.h"
#include "httppoller/httppoller.h"
#include "housekeeper/housekeeper.h"
#include "pinger/pinger.h"
#include "poller/poller.h"
#include "timer/timer.h"
#include "trapper/trapper.h"
#include "snmptrapper/snmptrapper.h"
#include "escalator/escalator.h"
#include "proxypoller/proxypoller.h"
#include "selfmon/selfmon.h"
#include "vmware/vmware.h"
#include "taskmanager/taskmanager.h"
#include "preprocessor/preproc_manager.h"
#include "preprocessor/preproc_worker.h"
#include "availability/avail_manager.h"
#include "service/service_manager.h"
#include "housekeeper/trigger_housekeeper.h"
#include "lld/lld_manager.h"
#include "lld/lld_worker.h"
#include "reporter/report_manager.h"
#include "reporter/report_writer.h"
#include "events.h"
#include "../libs/zbxdbcache/valuecache.h"
#include "setproctitle.h"
#include "zbxcrypto.h"
#include "zbxhistory.h"
#include "postinit.h"
#include "zbxvault.h"
#include "zbxtrends.h"
#include "ha/ha.h"
#include "zbxrtc.h"
#include "zbxha.h"

#ifdef HAVE_OPENIPMI
#include "ipmi/ipmi_manager.h"
#include "ipmi/ipmi_poller.h"
#endif

const char	*progname = NULL;
const char	title_message[] = "zabbix_server";
const char	syslog_app_name[] = "zabbix_server";
const char	*usage_message[] = {
	"[-c config-file]", NULL,
	"[-c config-file]", "-R runtime-option", NULL,
	"-h", NULL,
	"-V", NULL,
	NULL	/* end of text */
};

const char	*help_message[] = {
	"The core daemon of Zabbix software.",
	"",
	"Options:",
	"  -c --config config-file        Path to the configuration file",
	"                                 (default: \"" DEFAULT_CONFIG_FILE "\")",
	"  -f --foreground                Run Zabbix server in foreground",
	"  -R --runtime-control runtime-option   Perform administrative functions",
	"",
	"    Runtime control options:",
	"      " ZBX_CONFIG_CACHE_RELOAD "         Reload configuration cache",
	"      " ZBX_HOUSEKEEPER_EXECUTE "         Execute the housekeeper",
	"      " ZBX_TRIGGER_HOUSEKEEPER_EXECUTE " Execute the trigger housekeeper",
	"      " ZBX_LOG_LEVEL_INCREASE "=target   Increase log level, affects all processes if",
	"                                  target is not specified",
	"      " ZBX_LOG_LEVEL_DECREASE "=target   Decrease log level, affects all processes if",
	"                                  target is not specified",
	"      " ZBX_SNMP_CACHE_RELOAD "           Reload SNMP cache",
	"      " ZBX_SECRETS_RELOAD "              Reload secrets from Vault",
	"      " ZBX_DIAGINFO "=section            Log internal diagnostic information of the",
	"                                  section (historycache, preprocessing, alerting,",
	"                                  lld, valuecache, locks) or everything if section is",
	"                                  not specified",
	"      " ZBX_SERVICE_CACHE_RELOAD "        Reload service manager cache",
	"      " ZBX_HA_STATUS "                   Display HA cluster status",
	"      " ZBX_HA_REMOVE_NODE "=target       Remove the HA node specified by its name or ID",
	"      " ZBX_HA_SET_FAILOVER_DELAY "=delay Set HA failover delay",
	"",
	"      Log level control targets:",
	"        process-type              All processes of specified type",
	"                                  (alerter, alert manager, configuration syncer,",
	"                                  discoverer, escalator, ha manager, history syncer,",
	"                                  housekeeper, http poller, icmp pinger,",
	"                                  ipmi manager, ipmi poller, java poller,",
	"                                  poller, preprocessing manager,",
	"                                  preprocessing worker, proxy poller,",
	"                                  self-monitoring, snmp trapper, task manager,",
	"                                  timer, trapper, unreachable poller,",
	"                                  vmware collector, history poller,",
	"                                  availability manager, service manager, odbc poller)",
	"        process-type,N            Process type and number (e.g., poller,3)",
	"        pid                       Process identifier",
	"",
	"  -h --help                       Display this help message",
	"  -V --version                    Display version number",
	"",
	"Some configuration parameter default locations:",
	"  AlertScriptsPath                \"" DEFAULT_ALERT_SCRIPTS_PATH "\"",
	"  ExternalScripts                 \"" DEFAULT_EXTERNAL_SCRIPTS_PATH "\"",
#ifdef HAVE_LIBCURL
	"  SSLCertLocation                 \"" DEFAULT_SSL_CERT_LOCATION "\"",
	"  SSLKeyLocation                  \"" DEFAULT_SSL_KEY_LOCATION "\"",
#endif
	"  LoadModulePath                  \"" DEFAULT_LOAD_MODULE_PATH "\"",
	NULL	/* end of text */
};

/* COMMAND LINE OPTIONS */

/* long options */
static struct zbx_option	longopts[] =
{
	{"config",		1,	NULL,	'c'},
	{"foreground",		0,	NULL,	'f'},
	{"runtime-control",	1,	NULL,	'R'},
	{"help",		0,	NULL,	'h'},
	{"version",		0,	NULL,	'V'},
	{NULL}
};

/* short options */
static char	shortopts[] = "c:hVR:f";

/* end of COMMAND LINE OPTIONS */

int		threads_num = 0;
pid_t		*threads = NULL;
static int	*threads_flags;

static int	ha_status = ZBX_NODE_STATUS_UNKNOWN;
static int	ha_failover_delay = ZBX_HA_DEFAULT_FAILOVER_DELAY;
zbx_cuid_t	ha_sessionid;


unsigned char			program_type	= ZBX_PROGRAM_TYPE_SERVER;
ZBX_THREAD_LOCAL unsigned char	process_type	= ZBX_PROCESS_TYPE_UNKNOWN;
ZBX_THREAD_LOCAL int		process_num	= 0;
ZBX_THREAD_LOCAL int		server_num	= 0;

int	CONFIG_ALERTER_FORKS		= 3;
int	CONFIG_DISCOVERER_FORKS		= 1;
int	CONFIG_HOUSEKEEPER_FORKS	= 1;
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
int	CONFIG_DATASENDER_FORKS		= 0;
int	CONFIG_HEARTBEAT_FORKS		= 0;
int	CONFIG_COLLECTOR_FORKS		= 0;
int	CONFIG_PASSIVE_FORKS		= 0;
int	CONFIG_ACTIVE_FORKS		= 0;
int	CONFIG_TASKMANAGER_FORKS	= 1;
int	CONFIG_IPMIMANAGER_FORKS	= 0;
int	CONFIG_ALERTMANAGER_FORKS	= 1;
int	CONFIG_PREPROCMAN_FORKS		= 1;
int	CONFIG_PREPROCESSOR_FORKS	= 3;
int	CONFIG_LLDMANAGER_FORKS		= 1;
int	CONFIG_LLDWORKER_FORKS		= 2;
int	CONFIG_ALERTDB_FORKS		= 1;
int	CONFIG_HISTORYPOLLER_FORKS	= 5;
int	CONFIG_AVAILMAN_FORKS		= 1;
int	CONFIG_REPORTMANAGER_FORKS	= 0;
int	CONFIG_REPORTWRITER_FORKS	= 0;
int	CONFIG_SERVICEMAN_FORKS		= 1;
int	CONFIG_TRIGGERHOUSEKEEPER_FORKS = 1;
int	CONFIG_ODBCPOLLER_FORKS		= 1;

int	CONFIG_LISTEN_PORT		= ZBX_DEFAULT_SERVER_PORT;
char	*CONFIG_LISTEN_IP		= NULL;
char	*CONFIG_SOURCE_IP		= NULL;
int	CONFIG_TRAPPER_TIMEOUT		= 300;
char	*CONFIG_SERVER			= NULL;		/* not used in zabbix_server, required for linking */

int	CONFIG_HOUSEKEEPING_FREQUENCY	= 1;
int	CONFIG_MAX_HOUSEKEEPER_DELETE	= 5000;		/* applies for every separate field value */
int	CONFIG_HISTSYNCER_FORKS		= 4;
int	CONFIG_HISTSYNCER_FREQUENCY	= 1;
int	CONFIG_CONFSYNCER_FORKS		= 1;
int	CONFIG_CONFSYNCER_FREQUENCY	= 60;

int	CONFIG_PROBLEMHOUSEKEEPING_FREQUENCY = 60;

int	CONFIG_VMWARE_FORKS		= 0;
int	CONFIG_VMWARE_FREQUENCY		= 60;
int	CONFIG_VMWARE_PERF_FREQUENCY	= 60;
int	CONFIG_VMWARE_TIMEOUT		= 10;

zbx_uint64_t	CONFIG_CONF_CACHE_SIZE		= 32 * ZBX_MEBIBYTE;
zbx_uint64_t	CONFIG_HISTORY_CACHE_SIZE	= 16 * ZBX_MEBIBYTE;
zbx_uint64_t	CONFIG_HISTORY_INDEX_CACHE_SIZE	= 4 * ZBX_MEBIBYTE;
zbx_uint64_t	CONFIG_TRENDS_CACHE_SIZE	= 4 * ZBX_MEBIBYTE;
zbx_uint64_t	CONFIG_TREND_FUNC_CACHE_SIZE	= 4 * ZBX_MEBIBYTE;
zbx_uint64_t	CONFIG_VALUE_CACHE_SIZE		= 8 * ZBX_MEBIBYTE;
zbx_uint64_t	CONFIG_VMWARE_CACHE_SIZE	= 8 * ZBX_MEBIBYTE;
zbx_uint64_t	CONFIG_EXPORT_FILE_SIZE		= ZBX_GIBIBYTE;

int	CONFIG_UNREACHABLE_PERIOD	= 45;
int	CONFIG_UNREACHABLE_DELAY	= 15;
int	CONFIG_UNAVAILABLE_DELAY	= 60;
int	CONFIG_LOG_LEVEL		= LOG_LEVEL_WARNING;
char	*CONFIG_ALERT_SCRIPTS_PATH	= NULL;
char	*CONFIG_EXTERNALSCRIPTS		= NULL;
char	*CONFIG_TMPDIR			= NULL;
char	*CONFIG_FPING_LOCATION		= NULL;
char	*CONFIG_FPING6_LOCATION		= NULL;
char	*CONFIG_DBHOST			= NULL;
char	*CONFIG_DBNAME			= NULL;
char	*CONFIG_DBSCHEMA		= NULL;
char	*CONFIG_DBUSER			= NULL;
char	*CONFIG_DBPASSWORD		= NULL;
char	*CONFIG_VAULTTOKEN		= NULL;
char	*CONFIG_VAULTURL		= NULL;
char	*CONFIG_VAULTDBPATH		= NULL;
char	*CONFIG_DBSOCKET		= NULL;
char	*CONFIG_DB_TLS_CONNECT		= NULL;
char	*CONFIG_DB_TLS_CERT_FILE	= NULL;
char	*CONFIG_DB_TLS_KEY_FILE		= NULL;
char	*CONFIG_DB_TLS_CA_FILE		= NULL;
char	*CONFIG_DB_TLS_CIPHER		= NULL;
char	*CONFIG_DB_TLS_CIPHER_13	= NULL;
char	*CONFIG_EXPORT_DIR		= NULL;
char	*CONFIG_EXPORT_TYPE		= NULL;
int	CONFIG_DBPORT			= 0;
int	CONFIG_ALLOW_UNSUPPORTED_DB_VERSIONS = 0;
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

/* how often Zabbix server sends configuration data to proxy, in seconds */
int	CONFIG_PROXYCONFIG_FREQUENCY	= SEC_PER_HOUR;
int	CONFIG_PROXYDATA_FREQUENCY	= 1;	/* 1s */

char	*CONFIG_LOAD_MODULE_PATH	= NULL;
char	**CONFIG_LOAD_MODULE		= NULL;

char	*CONFIG_USER			= NULL;

/* web monitoring */
char	*CONFIG_SSL_CA_LOCATION		= NULL;
char	*CONFIG_SSL_CERT_LOCATION	= NULL;
char	*CONFIG_SSL_KEY_LOCATION	= NULL;

/* TLS parameters */
unsigned int	configured_tls_connect_mode = ZBX_TCP_SEC_UNENCRYPTED;	/* not used in server, defined for linking */
									/* with tls.c */
unsigned int	configured_tls_accept_modes = ZBX_TCP_SEC_UNENCRYPTED;	/* not used in server, defined for linking */
									/* with tls.c */
char	*CONFIG_TLS_CA_FILE		= NULL;
char	*CONFIG_TLS_CRL_FILE		= NULL;
char	*CONFIG_TLS_CERT_FILE		= NULL;
char	*CONFIG_TLS_KEY_FILE		= NULL;
char	*CONFIG_TLS_CIPHER_CERT13	= NULL;
char	*CONFIG_TLS_CIPHER_CERT		= NULL;
char	*CONFIG_TLS_CIPHER_PSK13	= NULL;
char	*CONFIG_TLS_CIPHER_PSK		= NULL;
char	*CONFIG_TLS_CIPHER_ALL13	= NULL;
char	*CONFIG_TLS_CIPHER_ALL		= NULL;
char	*CONFIG_TLS_CIPHER_CMD13	= NULL;	/* not used in server, defined for linking with tls.c */
char	*CONFIG_TLS_CIPHER_CMD		= NULL;	/* not used in server, defined for linking with tls.c */
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
/* the following TLS parameters are not used in server, they are defined for linking with tls.c */
char	*CONFIG_TLS_CONNECT		= NULL;
char	*CONFIG_TLS_ACCEPT		= NULL;
char	*CONFIG_TLS_SERVER_CERT_ISSUER	= NULL;
char	*CONFIG_TLS_SERVER_CERT_SUBJECT	= NULL;
char	*CONFIG_TLS_PSK_IDENTITY	= NULL;
char	*CONFIG_TLS_PSK_FILE		= NULL;
#endif

char	*CONFIG_HA_NODE_NAME		= NULL;
char	*CONFIG_NODE_ADDRESS	= NULL;

static char	*CONFIG_SOCKET_PATH	= NULL;

char	*CONFIG_HISTORY_STORAGE_URL		= NULL;
char	*CONFIG_HISTORY_STORAGE_OPTS		= NULL;
int	CONFIG_HISTORY_STORAGE_PIPELINES	= 0;

char	*CONFIG_STATS_ALLOWED_IP	= NULL;
int	CONFIG_TCP_MAX_BACKLOG_SIZE	= SOMAXCONN;

int	CONFIG_DOUBLE_PRECISION		= ZBX_DB_DBL_PRECISION_ENABLED;

char	*CONFIG_WEBSERVICE_URL	= NULL;

int	CONFIG_SERVICEMAN_SYNC_FREQUENCY	= 60;

#if defined(HAVE_POSTGRESQL)
extern int	ZBX_TSDB_VERSION;
#endif

struct zbx_db_version_info_t	db_version_info;

int	get_process_info_by_thread(int local_server_num, unsigned char *local_process_type, int *local_process_num);

int	get_process_info_by_thread(int local_server_num, unsigned char *local_process_type, int *local_process_num)
{
	int	server_count = 0;

	if (0 == local_server_num)
	{
		/* fail if the main process is queried */
		return FAIL;
	}
	else if (local_server_num <= (server_count += CONFIG_SERVICEMAN_FORKS))
	{
		/* start service manager process and load configuration cache in parallel */
		*local_process_type = ZBX_PROCESS_TYPE_SERVICEMAN;
		*local_process_num = local_server_num - server_count + CONFIG_SERVICEMAN_FORKS;
	}
	else if (local_server_num <= (server_count += CONFIG_CONFSYNCER_FORKS))
	{
		/* make initial configuration sync before worker processes are forked */
		*local_process_type = ZBX_PROCESS_TYPE_CONFSYNCER;
		*local_process_num = local_server_num - server_count + CONFIG_CONFSYNCER_FORKS;
	}
	else if (local_server_num <= (server_count += CONFIG_ALERTMANAGER_FORKS))
	{
		/* data collection processes might utilize CPU fully, start manager and worker processes beforehand */
		*local_process_type = ZBX_PROCESS_TYPE_ALERTMANAGER;
		*local_process_num = local_server_num - server_count + CONFIG_ALERTMANAGER_FORKS;
	}
	else if (local_server_num <= (server_count += CONFIG_ALERTER_FORKS))
	{
		*local_process_type = ZBX_PROCESS_TYPE_ALERTER;
		*local_process_num = local_server_num - server_count + CONFIG_ALERTER_FORKS;
	}
	else if (local_server_num <= (server_count += CONFIG_PREPROCMAN_FORKS))
	{
		*local_process_type = ZBX_PROCESS_TYPE_PREPROCMAN;
		*local_process_num = local_server_num - server_count + CONFIG_PREPROCMAN_FORKS;
	}
	else if (local_server_num <= (server_count += CONFIG_PREPROCESSOR_FORKS))
	{
		*local_process_type = ZBX_PROCESS_TYPE_PREPROCESSOR;
		*local_process_num = local_server_num - server_count + CONFIG_PREPROCESSOR_FORKS;
	}
	else if (local_server_num <= (server_count += CONFIG_LLDMANAGER_FORKS))
	{
		*local_process_type = ZBX_PROCESS_TYPE_LLDMANAGER;
		*local_process_num = local_server_num - server_count + CONFIG_LLDMANAGER_FORKS;
	}
	else if (local_server_num <= (server_count += CONFIG_LLDWORKER_FORKS))
	{
		*local_process_type = ZBX_PROCESS_TYPE_LLDWORKER;
		*local_process_num = local_server_num - server_count + CONFIG_LLDWORKER_FORKS;
	}
	else if (local_server_num <= (server_count += CONFIG_IPMIMANAGER_FORKS))
	{
		*local_process_type = ZBX_PROCESS_TYPE_IPMIMANAGER;
		*local_process_num = local_server_num - server_count + CONFIG_TASKMANAGER_FORKS;
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
	else if (local_server_num <= (server_count += CONFIG_TASKMANAGER_FORKS))
	{
		*local_process_type = ZBX_PROCESS_TYPE_TASKMANAGER;
		*local_process_num = local_server_num - server_count + CONFIG_TASKMANAGER_FORKS;
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
	else if (local_server_num <= (server_count += CONFIG_ALERTDB_FORKS))
	{
		*local_process_type = ZBX_PROCESS_TYPE_ALERTSYNCER;
		*local_process_num = local_server_num - server_count + CONFIG_ALERTDB_FORKS;
	}
	else if (local_server_num <= (server_count += CONFIG_HISTORYPOLLER_FORKS))
	{
		*local_process_type = ZBX_PROCESS_TYPE_HISTORYPOLLER;
		*local_process_num = local_server_num - server_count + CONFIG_HISTORYPOLLER_FORKS;
	}
	else if (local_server_num <= (server_count += CONFIG_AVAILMAN_FORKS))
	{
		*local_process_type = ZBX_PROCESS_TYPE_AVAILMAN;
		*local_process_num = local_server_num - server_count + CONFIG_AVAILMAN_FORKS;
	}
	else if (local_server_num <= (server_count += CONFIG_REPORTMANAGER_FORKS))
	{
		*local_process_type = ZBX_PROCESS_TYPE_REPORTMANAGER;
		*local_process_num = local_server_num - server_count + CONFIG_REPORTMANAGER_FORKS;
	}
	else if (local_server_num <= (server_count += CONFIG_REPORTWRITER_FORKS))
	{
		*local_process_type = ZBX_PROCESS_TYPE_REPORTWRITER;
		*local_process_num = local_server_num - server_count + CONFIG_REPORTWRITER_FORKS;
	}
	else if (local_server_num <= (server_count += CONFIG_TRIGGERHOUSEKEEPER_FORKS))
	{
		/* start service manager process and load configuration cache in parallel */
		*local_process_type = ZBX_PROCESS_TYPE_TRIGGERHOUSEKEEPER;
		*local_process_num = local_server_num - server_count + CONFIG_TRIGGERHOUSEKEEPER_FORKS;
	}
	else if (local_server_num <= (server_count += CONFIG_ODBCPOLLER_FORKS))
	{
		*local_process_type = ZBX_PROCESS_TYPE_ODBCPOLLER;
		*local_process_num = local_server_num - server_count + CONFIG_ODBCPOLLER_FORKS;
	}
	else
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: set configuration defaults                                        *
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
		CONFIG_ALERT_SCRIPTS_PATH = zbx_strdup(CONFIG_ALERT_SCRIPTS_PATH, DEFAULT_ALERT_SCRIPTS_PATH);

	if (NULL == CONFIG_LOAD_MODULE_PATH)
		CONFIG_LOAD_MODULE_PATH = zbx_strdup(CONFIG_LOAD_MODULE_PATH, DEFAULT_LOAD_MODULE_PATH);

	if (NULL == CONFIG_TMPDIR)
		CONFIG_TMPDIR = zbx_strdup(CONFIG_TMPDIR, "/tmp");

	if (NULL == CONFIG_FPING_LOCATION)
		CONFIG_FPING_LOCATION = zbx_strdup(CONFIG_FPING_LOCATION, "/usr/sbin/fping");
#ifdef HAVE_IPV6
	if (NULL == CONFIG_FPING6_LOCATION)
		CONFIG_FPING6_LOCATION = zbx_strdup(CONFIG_FPING6_LOCATION, "/usr/sbin/fping6");
#endif
	if (NULL == CONFIG_EXTERNALSCRIPTS)
		CONFIG_EXTERNALSCRIPTS = zbx_strdup(CONFIG_EXTERNALSCRIPTS, DEFAULT_EXTERNAL_SCRIPTS_PATH);
#ifdef HAVE_LIBCURL
	if (NULL == CONFIG_SSL_CERT_LOCATION)
		CONFIG_SSL_CERT_LOCATION = zbx_strdup(CONFIG_SSL_CERT_LOCATION, DEFAULT_SSL_CERT_LOCATION);

	if (NULL == CONFIG_SSL_KEY_LOCATION)
		CONFIG_SSL_KEY_LOCATION = zbx_strdup(CONFIG_SSL_KEY_LOCATION, DEFAULT_SSL_KEY_LOCATION);

	if (NULL == CONFIG_HISTORY_STORAGE_OPTS)
		CONFIG_HISTORY_STORAGE_OPTS = zbx_strdup(CONFIG_HISTORY_STORAGE_OPTS, "uint,dbl,str,log,text");
#endif

#ifdef HAVE_SQLITE3
	CONFIG_MAX_HOUSEKEEPER_DELETE = 0;
#endif

	if (NULL == CONFIG_LOG_TYPE_STR)
		CONFIG_LOG_TYPE_STR = zbx_strdup(CONFIG_LOG_TYPE_STR, ZBX_OPTION_LOGTYPE_FILE);

	if (NULL == CONFIG_SOCKET_PATH)
		CONFIG_SOCKET_PATH = zbx_strdup(CONFIG_SOCKET_PATH, "/tmp");

	if (0 != CONFIG_IPMIPOLLER_FORKS)
		CONFIG_IPMIMANAGER_FORKS = 1;

	if (NULL == CONFIG_VAULTURL)
		CONFIG_VAULTURL = zbx_strdup(CONFIG_VAULTURL, "https://127.0.0.1:8200");

	if (0 != CONFIG_REPORTWRITER_FORKS)
		CONFIG_REPORTMANAGER_FORKS = 1;

	if (NULL == CONFIG_NODE_ADDRESS)
		CONFIG_NODE_ADDRESS = zbx_strdup(CONFIG_NODE_ADDRESS, "localhost");
}

/******************************************************************************
 *                                                                            *
 * Purpose: validate configuration parameters                                 *
 *                                                                            *
 ******************************************************************************/
static void	zbx_validate_config(ZBX_TASK_EX *task)
{
	char		*ch_error, *address = NULL;
	int		err = 0;
	unsigned short	port;

	if (0 == CONFIG_UNREACHABLE_POLLER_FORKS && 0 != CONFIG_POLLER_FORKS + CONFIG_JAVAPOLLER_FORKS)
	{
		zabbix_log(LOG_LEVEL_CRIT, "\"StartPollersUnreachable\" configuration parameter must not be 0"
				" if regular or Java pollers are started");
		err = 1;
	}

	if ((NULL == CONFIG_JAVA_GATEWAY || '\0' == *CONFIG_JAVA_GATEWAY) && 0 < CONFIG_JAVAPOLLER_FORKS)
	{
		zabbix_log(LOG_LEVEL_CRIT, "\"JavaGateway\" configuration parameter is not specified or empty");
		err = 1;
	}

	if (0 != CONFIG_VALUE_CACHE_SIZE && 128 * ZBX_KIBIBYTE > CONFIG_VALUE_CACHE_SIZE)
	{
		zabbix_log(LOG_LEVEL_CRIT, "\"ValueCacheSize\" configuration parameter must be either 0"
				" or greater than 128KB");
		err = 1;
	}

	if (0 != CONFIG_TREND_FUNC_CACHE_SIZE && 128 * ZBX_KIBIBYTE > CONFIG_TREND_FUNC_CACHE_SIZE)
	{
		zabbix_log(LOG_LEVEL_CRIT, "\"TrendFunctionCacheSize\" configuration parameter must be either 0"
				" or greater than 128KB");
		err = 1;
	}

	if (NULL != CONFIG_SOURCE_IP && SUCCEED != is_supported_ip(CONFIG_SOURCE_IP))
	{
		zabbix_log(LOG_LEVEL_CRIT, "invalid \"SourceIP\" configuration parameter: '%s'", CONFIG_SOURCE_IP);
		err = 1;
	}

	if (NULL != CONFIG_STATS_ALLOWED_IP && FAIL == zbx_validate_peer_list(CONFIG_STATS_ALLOWED_IP, &ch_error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "invalid entry in \"StatsAllowedIP\" configuration parameter: %s", ch_error);
		zbx_free(ch_error);
		err = 1;
	}

	if (SUCCEED != zbx_validate_export_type(CONFIG_EXPORT_TYPE, NULL))
	{
		zabbix_log(LOG_LEVEL_CRIT, "invalid \"ExportType\" configuration parameter: %s", CONFIG_EXPORT_TYPE);
		err = 1;
	}

	if (FAIL == parse_serveractive_element(CONFIG_NODE_ADDRESS, &address, &port, 10051) ||
			(FAIL == is_supported_ip(address) && FAIL == zbx_validate_hostname(address)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "invalid \"NodeAddress\" configuration parameter: address \"%s\""
				" is invalid", CONFIG_NODE_ADDRESS);
		err = 1;
	}
	zbx_free(address);

#if !defined(HAVE_IPV6)
	err |= (FAIL == check_cfg_feature_str("Fping6Location", CONFIG_FPING6_LOCATION, "IPv6 support"));
#endif
#if !defined(HAVE_LIBCURL)
	err |= (FAIL == check_cfg_feature_str("SSLCALocation", CONFIG_SSL_CA_LOCATION, "cURL library"));
	err |= (FAIL == check_cfg_feature_str("SSLCertLocation", CONFIG_SSL_CERT_LOCATION, "cURL library"));
	err |= (FAIL == check_cfg_feature_str("SSLKeyLocation", CONFIG_SSL_KEY_LOCATION, "cURL library"));
	err |= (FAIL == check_cfg_feature_str("HistoryStorageURL", CONFIG_HISTORY_STORAGE_URL, "cURL library"));
	err |= (FAIL == check_cfg_feature_str("HistoryStorageTypes", CONFIG_HISTORY_STORAGE_OPTS, "cURL library"));
	err |= (FAIL == check_cfg_feature_int("HistoryStorageDateIndex", CONFIG_HISTORY_STORAGE_PIPELINES,
			"cURL library"));
	err |= (FAIL == check_cfg_feature_str("VaultToken", CONFIG_VAULTTOKEN, "cURL library"));
	err |= (FAIL == check_cfg_feature_str("VaultDBPath", CONFIG_VAULTDBPATH, "cURL library"));

	err |= (FAIL == check_cfg_feature_int("StartReportWriters", CONFIG_REPORTWRITER_FORKS, "cURL library"));
#endif

#if !defined(HAVE_LIBXML2) || !defined(HAVE_LIBCURL)
	err |= (FAIL == check_cfg_feature_int("StartVMwareCollectors", CONFIG_VMWARE_FORKS, "VMware support"));

	/* parameters VMwareFrequency, VMwarePerfFrequency, VMwareCacheSize, VMwareTimeout are not checked here */
	/* because they have non-zero default values */
#endif

	if (SUCCEED != zbx_validate_log_parameters(task))
		err = 1;

#if !(defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL))
	err |= (FAIL == check_cfg_feature_str("TLSCAFile", CONFIG_TLS_CA_FILE, "TLS support"));
	err |= (FAIL == check_cfg_feature_str("TLSCRLFile", CONFIG_TLS_CRL_FILE, "TLS support"));
	err |= (FAIL == check_cfg_feature_str("TLSCertFile", CONFIG_TLS_CERT_FILE, "TLS support"));
	err |= (FAIL == check_cfg_feature_str("TLSKeyFile", CONFIG_TLS_KEY_FILE, "TLS support"));
#endif
#if !(defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL))
	err |= (FAIL == check_cfg_feature_str("TLSCipherCert", CONFIG_TLS_CIPHER_CERT, "GnuTLS or OpenSSL"));
	err |= (FAIL == check_cfg_feature_str("TLSCipherPSK", CONFIG_TLS_CIPHER_PSK, "GnuTLS or OpenSSL"));
	err |= (FAIL == check_cfg_feature_str("TLSCipherAll", CONFIG_TLS_CIPHER_ALL, "GnuTLS or OpenSSL"));
#endif
#if !defined(HAVE_OPENSSL)
	err |= (FAIL == check_cfg_feature_str("TLSCipherCert13", CONFIG_TLS_CIPHER_CERT13, "OpenSSL 1.1.1 or newer"));
	err |= (FAIL == check_cfg_feature_str("TLSCipherPSK13", CONFIG_TLS_CIPHER_PSK13, "OpenSSL 1.1.1 or newer"));
	err |= (FAIL == check_cfg_feature_str("TLSCipherAll13", CONFIG_TLS_CIPHER_ALL13, "OpenSSL 1.1.1 or newer"));
#endif

#if !defined(HAVE_OPENIPMI)
	err |= (FAIL == check_cfg_feature_int("StartIPMIPollers", CONFIG_IPMIPOLLER_FORKS, "IPMI support"));
#endif

	err |= (FAIL == zbx_db_validate_config_features());

	if (0 != CONFIG_REPORTWRITER_FORKS && NULL == CONFIG_WEBSERVICE_URL)
	{
		zabbix_log(LOG_LEVEL_CRIT, "\"WebServiceURL\" configuration parameter must be set when "
				" setting \"StartReportWriters\" configuration parameter");
	}

	if (0 != err)
		exit(EXIT_FAILURE);
}

/******************************************************************************
 *                                                                            *
 * Purpose: parse config file and update configuration parameters             *
 *                                                                            *
 * Comments: will terminate process if parsing fails                          *
 *                                                                            *
 ******************************************************************************/
static void	zbx_load_config(ZBX_TASK_EX *task)
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
		{"StartEscalators",		&CONFIG_ESCALATOR_FORKS,		TYPE_INT,
			PARM_OPT,	1,			100},
		{"JavaGateway",			&CONFIG_JAVA_GATEWAY,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"JavaGatewayPort",		&CONFIG_JAVA_GATEWAY_PORT,		TYPE_INT,
			PARM_OPT,	1024,			32767},
		{"SNMPTrapperFile",		&CONFIG_SNMPTRAP_FILE,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"StartSNMPTrapper",		&CONFIG_SNMPTRAPPER_FORKS,		TYPE_INT,
			PARM_OPT,	0,			1},
		{"CacheSize",			&CONFIG_CONF_CACHE_SIZE,		TYPE_UINT64,
			PARM_OPT,	128 * ZBX_KIBIBYTE,	__UINT64_C(64) * ZBX_GIBIBYTE},
		{"HistoryCacheSize",		&CONFIG_HISTORY_CACHE_SIZE,		TYPE_UINT64,
			PARM_OPT,	128 * ZBX_KIBIBYTE,	__UINT64_C(2) * ZBX_GIBIBYTE},
		{"HistoryIndexCacheSize",	&CONFIG_HISTORY_INDEX_CACHE_SIZE,	TYPE_UINT64,
			PARM_OPT,	128 * ZBX_KIBIBYTE,	__UINT64_C(2) * ZBX_GIBIBYTE},
		{"TrendCacheSize",		&CONFIG_TRENDS_CACHE_SIZE,		TYPE_UINT64,
			PARM_OPT,	128 * ZBX_KIBIBYTE,	__UINT64_C(2) * ZBX_GIBIBYTE},
		{"TrendFunctionCacheSize",	&CONFIG_TREND_FUNC_CACHE_SIZE,		TYPE_UINT64,
			PARM_OPT,	0,			__UINT64_C(2) * ZBX_GIBIBYTE},
		{"ValueCacheSize",		&CONFIG_VALUE_CACHE_SIZE,		TYPE_UINT64,
			PARM_OPT,	0,			__UINT64_C(64) * ZBX_GIBIBYTE},
		{"CacheUpdateFrequency",	&CONFIG_CONFSYNCER_FREQUENCY,		TYPE_INT,
			PARM_OPT,	1,			SEC_PER_HOUR},
		{"HousekeepingFrequency",	&CONFIG_HOUSEKEEPING_FREQUENCY,		TYPE_INT,
			PARM_OPT,	0,			24},
		{"MaxHousekeeperDelete",	&CONFIG_MAX_HOUSEKEEPER_DELETE,		TYPE_INT,
			PARM_OPT,	0,			1000000},
		{"TmpDir",			&CONFIG_TMPDIR,				TYPE_STRING,
			PARM_OPT,	0,			0},
		{"FpingLocation",		&CONFIG_FPING_LOCATION,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"Fping6Location",		&CONFIG_FPING6_LOCATION,		TYPE_STRING,
			PARM_OPT,	0,			0},
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
		{"LogType",			&CONFIG_LOG_TYPE_STR,			TYPE_STRING,
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
		{"VaultToken",			&CONFIG_VAULTTOKEN,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"VaultURL",			&CONFIG_VAULTURL,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"VaultDBPath",			&CONFIG_VAULTDBPATH,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"DBSocket",			&CONFIG_DBSOCKET,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"DBPort",			&CONFIG_DBPORT,				TYPE_INT,
			PARM_OPT,	1024,			65535},
		{"AllowUnsupportedDBVersions",	&CONFIG_ALLOW_UNSUPPORTED_DB_VERSIONS,	TYPE_INT,
			PARM_OPT,	0,			1},
		{"DBTLSConnect",		&CONFIG_DB_TLS_CONNECT,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"DBTLSCertFile",		&CONFIG_DB_TLS_CERT_FILE,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"DBTLSKeyFile",		&CONFIG_DB_TLS_KEY_FILE,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"DBTLSCAFile",			&CONFIG_DB_TLS_CA_FILE,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"DBTLSCipher",			&CONFIG_DB_TLS_CIPHER,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"DBTLSCipher13",		&CONFIG_DB_TLS_CIPHER_13,		TYPE_STRING,
			PARM_OPT,	0,			0},
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
		{"SSLCALocation",		&CONFIG_SSL_CA_LOCATION,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"SSLCertLocation",		&CONFIG_SSL_CERT_LOCATION,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"SSLKeyLocation",		&CONFIG_SSL_KEY_LOCATION,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSCAFile",			&CONFIG_TLS_CA_FILE,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSCRLFile",			&CONFIG_TLS_CRL_FILE,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSCertFile",			&CONFIG_TLS_CERT_FILE,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSKeyFile",			&CONFIG_TLS_KEY_FILE,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSCipherCert13",		&CONFIG_TLS_CIPHER_CERT13,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSCipherCert",		&CONFIG_TLS_CIPHER_CERT,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSCipherPSK13",		&CONFIG_TLS_CIPHER_PSK13,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSCipherPSK",		&CONFIG_TLS_CIPHER_PSK,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSCipherAll13",		&CONFIG_TLS_CIPHER_ALL13,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSCipherAll",		&CONFIG_TLS_CIPHER_ALL,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"SocketDir",			&CONFIG_SOCKET_PATH,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"StartAlerters",		&CONFIG_ALERTER_FORKS,			TYPE_INT,
			PARM_OPT,	1,			100},
		{"StartPreprocessors",		&CONFIG_PREPROCESSOR_FORKS,		TYPE_INT,
			PARM_OPT,	1,			1000},
		{"HistoryStorageURL",		&CONFIG_HISTORY_STORAGE_URL,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"HistoryStorageTypes",		&CONFIG_HISTORY_STORAGE_OPTS,		TYPE_STRING_LIST,
			PARM_OPT,	0,			0},
		{"HistoryStorageDateIndex",	&CONFIG_HISTORY_STORAGE_PIPELINES,	TYPE_INT,
			PARM_OPT,	0,			1},
		{"ExportDir",			&CONFIG_EXPORT_DIR,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"ExportType",			&CONFIG_EXPORT_TYPE,			TYPE_STRING_LIST,
			PARM_OPT,	0,			0},
		{"ExportFileSize",		&CONFIG_EXPORT_FILE_SIZE,		TYPE_UINT64,
			PARM_OPT,	ZBX_MEBIBYTE,	ZBX_GIBIBYTE},
		{"StartLLDProcessors",		&CONFIG_LLDWORKER_FORKS,		TYPE_INT,
			PARM_OPT,	1,			100},
		{"StatsAllowedIP",		&CONFIG_STATS_ALLOWED_IP,		TYPE_STRING_LIST,
			PARM_OPT,	0,			0},
		{"StartHistoryPollers",		&CONFIG_HISTORYPOLLER_FORKS,		TYPE_INT,
			PARM_OPT,	0,			1000},
		{"StartReportWriters",		&CONFIG_REPORTWRITER_FORKS,		TYPE_INT,
			PARM_OPT,	0,			100},
		{"WebServiceURL",		&CONFIG_WEBSERVICE_URL,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"ProblemHousekeepingFrequency",	&CONFIG_PROBLEMHOUSEKEEPING_FREQUENCY,	TYPE_INT,
			PARM_OPT,	1,			3600},
		{"ServiceManagerSyncFrequency",	&CONFIG_SERVICEMAN_SYNC_FREQUENCY,	TYPE_INT,
			PARM_OPT,	1,			3600},
		{"ListenBacklog",		&CONFIG_TCP_MAX_BACKLOG_SIZE,		TYPE_INT,
			PARM_OPT,	0,			INT_MAX},
		{"HANodeName",			&CONFIG_HA_NODE_NAME,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"NodeAddress",			&CONFIG_NODE_ADDRESS,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"StartODBCPollers",		&CONFIG_ODBCPOLLER_FORKS,		TYPE_INT,
			PARM_OPT,	0,			1000},
		{NULL}
	};

	/* initialize multistrings */
	zbx_strarr_init(&CONFIG_LOAD_MODULE);

	parse_cfg_file(CONFIG_FILE, cfg, ZBX_CFG_FILE_REQUIRED, ZBX_CFG_STRICT, ZBX_CFG_EXIT_FAILURE);

	zbx_set_defaults();

	CONFIG_LOG_TYPE = zbx_get_log_type(CONFIG_LOG_TYPE_STR);

	zbx_validate_config(task);
#if defined(HAVE_MYSQL) || defined(HAVE_POSTGRESQL)
	zbx_db_validate_config();
#endif
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_validate_config();
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: free configuration memory                                         *
 *                                                                            *
 ******************************************************************************/
static void	zbx_free_config(void)
{
	zbx_strarr_free(&CONFIG_LOAD_MODULE);
}

/******************************************************************************
 *                                                                            *
 * Purpose: executes server processes                                         *
 *                                                                            *
 ******************************************************************************/
int	main(int argc, char **argv)
{
	ZBX_TASK_EX	t = {ZBX_TASK_START};
	char		ch;
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
				t.opts = zbx_strdup(t.opts, zbx_optarg);
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
			case 'f':
				t.flags |= ZBX_TASK_FLAG_FOREGROUND;
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
			zbx_error("option \"-c\" or \"--config\" specified multiple times");
		if (1 < opt_r)
			zbx_error("option \"-R\" or \"--runtime-control\" specified multiple times");

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
		CONFIG_FILE = zbx_strdup(NULL, DEFAULT_CONFIG_FILE);

	/* required for simple checks */
	init_metrics();

	zbx_load_config(&t);

	if (ZBX_TASK_RUNTIME_CONTROL == t.task)
	{
		int	ret;
		char	*error = NULL;

		if (FAIL == zbx_ipc_service_init_env(CONFIG_SOCKET_PATH, &error))
		{
			zbx_error("cannot initialize IPC services: %s", error);
			zbx_free(error);
			exit(EXIT_FAILURE);
		}

		if (SUCCEED != (ret = zbx_rtc_process(t.opts, &error)))
		{
			zbx_error("Cannot perform runtime control command: %s", error);
			zbx_free(error);
		}

		exit(SUCCEED == ret ? EXIT_SUCCESS : EXIT_FAILURE);
	}

	return daemon_start(CONFIG_ALLOW_ROOT, CONFIG_USER, t.flags);
}

static void	zbx_db_version_info_clear(void)
{
	zbx_free(db_version_info.friendly_current_version);
	zbx_free(db_version_info.extension);
	zbx_free(db_version_info.ext_friendly_current_version);
	zbx_free(db_version_info.ext_lic);
}

static void	zbx_check_db(void)
{
	struct zbx_json	db_version_json;
	int		result = SUCCEED;

	memset(&db_version_info, 0, sizeof(db_version_info));
	result = zbx_db_check_version_info(&db_version_info, CONFIG_ALLOW_UNSUPPORTED_DB_VERSIONS);

	if (SUCCEED == result)
	{
		zbx_db_extract_dbextension_info(&db_version_info);
	}

	if (SUCCEED == result && (
#ifdef HAVE_POSTGRESQL
			SUCCEED != zbx_db_check_tsdb_capabilities(&db_version_info, CONFIG_ALLOW_UNSUPPORTED_DB_VERSIONS) ||
#endif
			SUCCEED != DBcheck_version()))
	{
		result = FAIL;
	}

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	if (SUCCEED == DBfield_exists("config", "dbversion_status"))
	{
		zbx_json_initarray(&db_version_json, ZBX_JSON_STAT_BUF_LEN);

		if (SUCCEED == DBpk_exists("history"))
		{
			db_version_info.history_pk = 1;
		}
		else
		{
			db_version_info.history_pk = 0;
			zabbix_log(LOG_LEVEL_WARNING, "database could be upgraded to use primary keys in history tables");
		}

#if defined(HAVE_POSTGRESQL)
		if (ZBX_TSDB_VERSION > 0)
			zbx_tsdb_update_dbversion_info(&db_version_info);
#endif

		zbx_db_version_json_create(&db_version_json, &db_version_info);

		if (SUCCEED == result)
			zbx_history_check_version(&db_version_json, &result);

		zbx_db_flush_version_requirements(db_version_json.buffer);
		zbx_json_free(&db_version_json);
	}

	DBclose();

	if (SUCCEED != result)
	{
		zbx_db_version_info_clear();
		exit(EXIT_FAILURE);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize shared resources and start processes                   *
 *                                                                            *
 ******************************************************************************/
static int	server_startup(zbx_socket_t *listen_sock, int *ha_stat, int *ha_failover, zbx_rtc_t *rtc)
{
	int	i, ret = SUCCEED;
	char	*error = NULL;

	if (SUCCEED != init_database_cache(&error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize database cache: %s", error);
		zbx_free(error);
		return FAIL;
	}

	if (SUCCEED != init_configuration_cache(&error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize configuration cache: %s", error);
		zbx_free(error);
		return FAIL;
	}

	if (SUCCEED != init_selfmon_collector(&error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize self-monitoring: %s", error);
		zbx_free(error);
		return FAIL;
	}

	if (0 != CONFIG_VMWARE_FORKS && SUCCEED != zbx_vmware_init(&error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize VMware cache: %s", error);
		zbx_free(error);
		return FAIL;
	}

	if (SUCCEED != zbx_vc_init(&error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize history value cache: %s", error);
		zbx_free(error);
		return FAIL;
	}

	if (SUCCEED != zbx_tfc_init(&error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize trends read cache: %s", error);
		zbx_free(error);
		return FAIL;
	}

	if (0 != CONFIG_TRAPPER_FORKS)
	{
		if (FAIL == zbx_tcp_listen(listen_sock, CONFIG_LISTEN_IP, (unsigned short)CONFIG_LISTEN_PORT))
		{
			zabbix_log(LOG_LEVEL_CRIT, "listener failed: %s", zbx_socket_strerror());
			return FAIL;
		}
	}

	threads_num = CONFIG_CONFSYNCER_FORKS + CONFIG_POLLER_FORKS
			+ CONFIG_UNREACHABLE_POLLER_FORKS + CONFIG_TRAPPER_FORKS + CONFIG_PINGER_FORKS
			+ CONFIG_ALERTER_FORKS + CONFIG_HOUSEKEEPER_FORKS + CONFIG_TIMER_FORKS
			+ CONFIG_HTTPPOLLER_FORKS + CONFIG_DISCOVERER_FORKS + CONFIG_HISTSYNCER_FORKS
			+ CONFIG_ESCALATOR_FORKS + CONFIG_IPMIPOLLER_FORKS + CONFIG_JAVAPOLLER_FORKS
			+ CONFIG_SNMPTRAPPER_FORKS + CONFIG_PROXYPOLLER_FORKS + CONFIG_SELFMON_FORKS
			+ CONFIG_VMWARE_FORKS + CONFIG_TASKMANAGER_FORKS + CONFIG_IPMIMANAGER_FORKS
			+ CONFIG_ALERTMANAGER_FORKS + CONFIG_PREPROCMAN_FORKS + CONFIG_PREPROCESSOR_FORKS
			+ CONFIG_LLDMANAGER_FORKS + CONFIG_LLDWORKER_FORKS + CONFIG_ALERTDB_FORKS
			+ CONFIG_HISTORYPOLLER_FORKS + CONFIG_AVAILMAN_FORKS + CONFIG_REPORTMANAGER_FORKS
			+ CONFIG_REPORTWRITER_FORKS + CONFIG_SERVICEMAN_FORKS + CONFIG_TRIGGERHOUSEKEEPER_FORKS
			+ CONFIG_ODBCPOLLER_FORKS;
	threads = (pid_t *)zbx_calloc(threads, (size_t)threads_num, sizeof(pid_t));
	threads_flags = (int *)zbx_calloc(threads_flags, (size_t)threads_num, sizeof(int));

	zabbix_log(LOG_LEVEL_INFORMATION, "server #0 started [main process]");

	zbx_set_exit_on_terminate();

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
			case ZBX_PROCESS_TYPE_SERVICEMAN:
				threads_flags[i] = ZBX_THREAD_PRIORITY_SECOND;
				zbx_thread_start(service_manager_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_CONFSYNCER:
				zbx_thread_start(dbconfig_thread, &thread_args, &threads[i]);
				if (FAIL == (ret = zbx_rtc_wait_config_sync(rtc)))
					goto out;

				if (SUCCEED != (ret = zbx_ha_get_status(ha_stat, ha_failover, &error)))
				{
					zabbix_log(LOG_LEVEL_CRIT, "cannot obtain HA status: %s", error);
					zbx_free(error);
					goto out;
				}

				if (ZBX_NODE_STATUS_ACTIVE != *ha_stat)
					goto out;

				DBconnect(ZBX_DB_CONNECT_NORMAL);

				if (SUCCEED != zbx_check_postinit_tasks(&error))
				{
					zabbix_log(LOG_LEVEL_CRIT, "cannot complete post initialization tasks: %s",
							error);
					zbx_free(error);
					DBclose();

					ret = FAIL;
					goto out;
				}

				/* update maintenance states */
				zbx_dc_update_maintenances();

				DBclose();

				zbx_vc_enable();
				break;
			case ZBX_PROCESS_TYPE_POLLER:
				poller_type = ZBX_POLLER_TYPE_NORMAL;
				thread_args.args = &poller_type;
				zbx_thread_start(poller_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_UNREACHABLE:
				poller_type = ZBX_POLLER_TYPE_UNREACHABLE;
				thread_args.args = &poller_type;
				zbx_thread_start(poller_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_TRAPPER:
				thread_args.args = listen_sock;
				zbx_thread_start(trapper_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_PINGER:
				zbx_thread_start(pinger_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_ALERTER:
				zbx_thread_start(alerter_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_HOUSEKEEPER:
				zbx_thread_start(housekeeper_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_TIMER:
				zbx_thread_start(timer_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_HTTPPOLLER:
				zbx_thread_start(httppoller_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_DISCOVERER:
				zbx_thread_start(discoverer_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_HISTSYNCER:
				threads_flags[i] = ZBX_THREAD_PRIORITY_FIRST;
				zbx_thread_start(dbsyncer_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_ESCALATOR:
				zbx_thread_start(escalator_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_JAVAPOLLER:
				poller_type = ZBX_POLLER_TYPE_JAVA;
				thread_args.args = &poller_type;
				zbx_thread_start(poller_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_SNMPTRAPPER:
				zbx_thread_start(snmptrapper_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_PROXYPOLLER:
				zbx_thread_start(proxypoller_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_SELFMON:
				zbx_thread_start(selfmon_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_VMWARE:
				zbx_thread_start(vmware_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_TASKMANAGER:
				zbx_thread_start(taskmanager_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_PREPROCMAN:
				zbx_thread_start(preprocessing_manager_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_PREPROCESSOR:
				zbx_thread_start(preprocessing_worker_thread, &thread_args, &threads[i]);
				break;
#ifdef HAVE_OPENIPMI
			case ZBX_PROCESS_TYPE_IPMIMANAGER:
				zbx_thread_start(ipmi_manager_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_IPMIPOLLER:
				zbx_thread_start(ipmi_poller_thread, &thread_args, &threads[i]);
				break;
#endif
			case ZBX_PROCESS_TYPE_ALERTMANAGER:
				zbx_thread_start(alert_manager_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_LLDMANAGER:
				zbx_thread_start(lld_manager_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_LLDWORKER:
				zbx_thread_start(lld_worker_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_ALERTSYNCER:
				zbx_thread_start(alert_syncer_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_HISTORYPOLLER:
				poller_type = ZBX_POLLER_TYPE_HISTORY;
				thread_args.args = &poller_type;
				zbx_thread_start(poller_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_AVAILMAN:
				threads_flags[i] = ZBX_THREAD_PRIORITY_FIRST;
				zbx_thread_start(availability_manager_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_REPORTMANAGER:
				zbx_thread_start(report_manager_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_REPORTWRITER:
				zbx_thread_start(report_writer_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_TRIGGERHOUSEKEEPER:
				zbx_thread_start(trigger_housekeeper_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_ODBCPOLLER:
				poller_type = ZBX_POLLER_TYPE_ODBC;
				thread_args.args = &poller_type;
				zbx_thread_start(poller_thread, &thread_args, &threads[i]);
				break;
		}
	}

	/* startup/postinit tasks can take a long time, update status */
	if (SUCCEED != (ret = zbx_ha_get_status(ha_stat, ha_failover, &error)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot obtain HA status: %s", error);
		zbx_free(error);
	}
out:
	zbx_unset_exit_on_terminate();

	return ret;
}

static int	server_restart_logger(char **error)
{
	zabbix_close_log();
	zbx_locks_destroy();

	if (SUCCEED != zbx_locks_create(error))
		return FAIL;

	if (SUCCEED != zabbix_open_log(CONFIG_LOG_TYPE, CONFIG_LOG_LEVEL, CONFIG_LOG_FILE, error))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: terminate processes and destroy shared resources                  *
 *                                                                            *
 ******************************************************************************/
static void	server_teardown(zbx_rtc_t *rtc, zbx_socket_t *listen_sock)
{
	int	i;
	char	*error = NULL;

	/* hard kill all zabbix processes, no logging or other  */

	zbx_unset_child_signal_handler();

	zbx_rtc_reset(rtc);

#ifdef HAVE_PTHREAD_PROCESS_SHARED
	/* Disable locks so main process doesn't hang on logging if a process was              */
	/* killed during logging. The locks will be re-enabled after logger is reinitialized   */
	zbx_locks_disable();
#endif
	zbx_ha_kill();

	for (i = 0; i < threads_num; i++)
	{
		if (!threads[i])
			continue;

		kill(threads[i], SIGKILL);
	}

	for (i = 0; i < threads_num; i++)
	{
		if (!threads[i])
			continue;

		zbx_thread_wait(threads[i]);
	}

	zbx_free(threads);
	zbx_free(threads_flags);

	zbx_set_child_signal_handler();

	/* restart logger because it could have been stuck in lock */
	if (SUCCEED != server_restart_logger(&error))
	{
		zbx_error("cannot restart logger: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	if (NULL != listen_sock)
		zbx_tcp_unlisten(listen_sock);

	/* destroy shared caches */
	zbx_tfc_destroy();
	zbx_vc_destroy();
	zbx_vmware_destroy();
	free_selfmon_collector();
	free_configuration_cache();
	free_database_cache(ZBX_SYNC_NONE);

#ifdef HAVE_PTHREAD_PROCESS_SHARED
	zbx_locks_enable();
#endif

	if (SUCCEED != zbx_ha_start(rtc, ZBX_NODE_STATUS_STANDBY, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start HA manager: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: restart HA manager when working in standby mode                   *
 *                                                                            *
 ******************************************************************************/
static void	server_restart_ha(zbx_rtc_t *rtc)
{
	char	*error = NULL;

	zbx_unset_child_signal_handler();

#ifdef HAVE_PTHREAD_PROCESS_SHARED
	/* Disable locks so main process doesn't hang on logging if a process was              */
	/* killed during logging. The locks will be re-enabled after logger is reinitialized   */
	zbx_locks_disable();
#endif
	zbx_ha_kill();

	zbx_set_child_signal_handler();

	/* restart logger because it could have been stuck in lock */
	if (SUCCEED != server_restart_logger(&error))
	{
		zbx_error("cannot restart logger: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

#ifdef HAVE_PTHREAD_PROCESS_SHARED
	zbx_locks_enable();
#endif

	if (SUCCEED != zbx_ha_start(rtc, ZBX_NODE_STATUS_STANDBY, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start HA manager: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	ha_status = ZBX_NODE_STATUS_STANDBY;
}


int	MAIN_ZABBIX_ENTRY(int flags)
{
	char		*error = NULL;
	int		i, db_type, ret, ha_status_old;

	zbx_socket_t	listen_sock;
	time_t		standby_warning_time;
	zbx_rtc_t	rtc;
	zbx_timespec_t	rtc_timeout = {1, 0};

	if (0 != (flags & ZBX_TASK_FLAG_FOREGROUND))
	{
		printf("Starting Zabbix Server. Zabbix %s (revision %s).\nPress Ctrl+C to exit.\n\n",
				ZABBIX_VERSION, ZABBIX_REVISION);
	}

	if (FAIL == zbx_ipc_service_init_env(CONFIG_SOCKET_PATH, &error))
	{
		zbx_error("cannot initialize IPC services: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	if (SUCCEED != zbx_locks_create(&error))
	{
		zbx_error("cannot create locks: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	if (SUCCEED != zabbix_open_log(CONFIG_LOG_TYPE, CONFIG_LOG_LEVEL, CONFIG_LOG_FILE, &error))
	{
		zbx_error("cannot open log: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	zbx_new_cuid(ha_sessionid.str);

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
#ifdef HAVE_UNIXODBC
#	define ODBC_FEATURE_STATUS	"YES"
#else
#	define ODBC_FEATURE_STATUS	" NO"
#endif
#if defined(HAVE_SSH2) || defined(HAVE_SSH)
#	define SSH_FEATURE_STATUS	"YES"
#else
#	define SSH_FEATURE_STATUS	" NO"
#endif
#ifdef HAVE_IPV6
#	define IPV6_FEATURE_STATUS	"YES"
#else
#	define IPV6_FEATURE_STATUS	" NO"
#endif
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
#	define TLS_FEATURE_STATUS	"YES"
#else
#	define TLS_FEATURE_STATUS	" NO"
#endif

	zabbix_log(LOG_LEVEL_INFORMATION, "Starting Zabbix Server. Zabbix %s (revision %s).",
			ZABBIX_VERSION, ZABBIX_REVISION);

	zabbix_log(LOG_LEVEL_INFORMATION, "****** Enabled features ******");
	zabbix_log(LOG_LEVEL_INFORMATION, "SNMP monitoring:           " SNMP_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "IPMI monitoring:           " IPMI_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "Web monitoring:            " LIBCURL_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "VMware monitoring:         " VMWARE_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "SMTP authentication:       " SMTP_AUTH_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "ODBC:                      " ODBC_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "SSH support:               " SSH_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "IPv6 support:              " IPV6_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "TLS support:               " TLS_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "******************************");

	zabbix_log(LOG_LEVEL_INFORMATION, "using configuration file: %s", CONFIG_FILE);

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	if (SUCCEED != zbx_coredump_disable())
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot disable core dump, exiting...");
		exit(EXIT_FAILURE);
	}
#endif

	zbx_initialize_events();

	if (FAIL == zbx_load_modules(CONFIG_LOAD_MODULE_PATH, CONFIG_LOAD_MODULE, CONFIG_TIMEOUT, 1))
	{
		zabbix_log(LOG_LEVEL_CRIT, "loading modules failed, exiting...");
		exit(EXIT_FAILURE);
	}

	zbx_free_config();

	if (SUCCEED != zbx_rtc_init(&rtc, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize runtime control service: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	if (SUCCEED != zbx_vault_init_token_from_env(&error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize vault token: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	if (SUCCEED != zbx_vault_init_db_credentials(&error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize database credentials from vault: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	if (ZBX_DB_UNKNOWN == (db_type = zbx_db_get_database_type()))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot use database \"%s\": database is not a Zabbix database",
				CONFIG_DBNAME);
		exit(EXIT_FAILURE);
	}
	else if (ZBX_DB_SERVER != db_type)
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot use database \"%s\": its \"users\" table is empty (is this the"
				" Zabbix proxy database?)", CONFIG_DBNAME);
		exit(EXIT_FAILURE);
	}

	if (SUCCEED != init_database_cache(&error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize database cache: %s", error);
		zbx_free(error);
		return FAIL;
	}

	DBcheck_character_set();
	zbx_check_db();

	if (SUCCEED != DBcheck_double_type())
	{
		CONFIG_DOUBLE_PRECISION = ZBX_DB_DBL_PRECISION_DISABLED;
		ZBX_DOUBLE_EPSILON = 0.000001;
		zabbix_log(LOG_LEVEL_WARNING, "database is not upgraded to use double precision values");
	}

	if (SUCCEED != zbx_db_check_instanceid())
		exit(EXIT_FAILURE);

	if (FAIL == zbx_export_init(&error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize export: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	if (SUCCEED != zbx_history_init(&error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize history storage: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	zbx_unset_exit_on_terminate();

	if (SUCCEED != zbx_ha_start(&rtc, ZBX_NODE_STATUS_UNKNOWN, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start HA manager: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	if (SUCCEED == zbx_is_export_enabled(ZBX_FLAG_EXPTYPE_EVENTS))
		zbx_problems_export_init("main-process", 0);

	if (SUCCEED == zbx_is_export_enabled(ZBX_FLAG_EXPTYPE_HISTORY))
		zbx_history_export_init("main-process", 0);

	if (SUCCEED == zbx_is_export_enabled(ZBX_FLAG_EXPTYPE_TRENDS))
		zbx_trends_export_init("main-process", 0);

	if (SUCCEED != zbx_ha_get_status(&ha_status, &ha_failover_delay, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start server: %s", error);
		zbx_free(error);
		sig_exiting = ZBX_EXIT_FAILURE;
	}

	if (ZBX_NODE_STATUS_ACTIVE == ha_status)
	{
		if (SUCCEED != server_startup(&listen_sock, &ha_status, &ha_failover_delay, &rtc))
		{
			sig_exiting = ZBX_EXIT_FAILURE;
			ha_status = ZBX_NODE_STATUS_ERROR;
		}
		else
		{
			/* check if the HA status has not been changed during startup process */
			if (ZBX_NODE_STATUS_ACTIVE != ha_status)
				server_teardown(&rtc, &listen_sock);
		}
	}

	if (ZBX_NODE_STATUS_ERROR != ha_status)
	{
		if (ZBX_HA_IS_CLUSTER())
		{
			zabbix_log(LOG_LEVEL_INFORMATION, "\"%s\" node started in \"%s\" mode", CONFIG_HA_NODE_NAME,
					zbx_ha_status_str(ha_status));

		}
	}

	ha_status_old = ha_status;

	if (ZBX_NODE_STATUS_STANDBY == ha_status)
		standby_warning_time = time(NULL);

	while (ZBX_IS_RUNNING())
	{
		time_t			now;
		zbx_ipc_client_t	*client;
		zbx_ipc_message_t	*message;

		(void)zbx_ipc_service_recv(&rtc.service, &rtc_timeout, &client, &message);

		if (NULL == message || ZBX_IPC_SERVICE_HA_RTC_FIRST <= message->code)
		{
			if (SUCCEED != zbx_ha_dispatch_message(message, &ha_status, &ha_failover_delay, &error))
			{
				zabbix_log(LOG_LEVEL_CRIT, "HA manager error: %s", error);
				sig_exiting = ZBX_EXIT_FAILURE;
			}
		}
		else
		{
			if (ZBX_NODE_STATUS_ACTIVE == ha_status || ZBX_RTC_LOG_LEVEL_DECREASE == message->code ||
					ZBX_RTC_LOG_LEVEL_INCREASE == message->code)
			{
				zbx_rtc_dispatch(&rtc, client, message);
			}
			else
			{
				const char	*result = "Runtime commands can be executed only in active mode\n";
				zbx_ipc_client_send(client, message->code, (const unsigned char *)result,
						(zbx_uint32_t)strlen(result) + 1);
			}
		}

		zbx_ipc_message_free(message);

		if (NULL != client)
			zbx_ipc_client_release(client);

		if (ZBX_NODE_STATUS_ERROR == ha_status)
			break;

		if (ZBX_NODE_STATUS_HATIMEOUT == ha_status)
		{
			zabbix_log(LOG_LEVEL_INFORMATION, "HA manager is not responding in standby mode, "
					"restarting it.");
			server_restart_ha(&rtc);
			continue;
		}

		now = time(NULL);

		if (ZBX_NODE_STATUS_UNKNOWN != ha_status && ha_status != ha_status_old)
		{
			ha_status_old = ha_status;
			zabbix_log(LOG_LEVEL_INFORMATION, "\"%s\" node switched to \"%s\" mode",
					ZBX_NULL2EMPTY_STR(CONFIG_HA_NODE_NAME), zbx_ha_status_str(ha_status));

			switch (ha_status)
			{
				case ZBX_NODE_STATUS_ACTIVE:
					if (SUCCEED != server_startup(&listen_sock, &ha_status, &ha_failover_delay, &rtc))
					{
						sig_exiting = ZBX_EXIT_FAILURE;
						ha_status = ZBX_NODE_STATUS_ERROR;
						continue;
					}

					if (ZBX_NODE_STATUS_ACTIVE != ha_status)
					{
						server_teardown(&rtc, &listen_sock);
						ha_status_old = ha_status;
					}
					break;
				case ZBX_NODE_STATUS_STANDBY:
					server_teardown(&rtc, &listen_sock);
					standby_warning_time = now;
					break;
				default:
					zabbix_log(LOG_LEVEL_CRIT, "unsupported status %d received from HA manager",
							ha_status);
					sig_exiting = ZBX_EXIT_FAILURE;
					continue;
			}
		}

		if (ZBX_NODE_STATUS_STANDBY == ha_status)
		{
			if (standby_warning_time + SEC_PER_HOUR <= now)
			{
				zabbix_log(LOG_LEVEL_INFORMATION, "\"%s\" node is working in \"%s\" mode",
						CONFIG_HA_NODE_NAME, zbx_ha_status_str(ha_status));
				standby_warning_time = now;
			}
		}

		if (0 < (ret = waitpid((pid_t)-1, &i, WNOHANG)))
		{
			zabbix_log(LOG_LEVEL_CRIT, "PROCESS EXIT: %d", ret);
			sig_exiting = ZBX_EXIT_FAILURE;
			break;
		}

		if (-1 == ret && EINTR != errno)
		{
			zabbix_log(LOG_LEVEL_ERR, "failed to wait on child processes: %s", zbx_strerror(errno));
			sig_exiting = ZBX_EXIT_FAILURE;
			break;
		}
	}

	zbx_db_version_info_clear();

	if (SUCCEED == ZBX_EXIT_STATUS())
		zbx_rtc_shutdown_subs(&rtc);

	if (SUCCEED != zbx_ha_pause(&error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot pause HA manager: %s", error);
		zbx_free(error);
	}

	zbx_on_exit(ZBX_EXIT_STATUS());

	return SUCCEED;
}

void	zbx_on_exit(int ret)
{
	char	*error = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "zbx_on_exit() called with ret:%d", ret);

	if (NULL != threads)
	{
		zbx_threads_wait(threads, threads_flags, threads_num, ret);	/* wait for all child processes to exit */
		zbx_free(threads);
		zbx_free(threads_flags);
	}

#ifdef HAVE_PTHREAD_PROCESS_SHARED
		zbx_locks_disable();
#endif

	if (ZBX_NODE_STATUS_ACTIVE == ha_status)
	{
		DBconnect(ZBX_DB_CONNECT_EXIT);
		free_database_cache(ZBX_SYNC_ALL);
		DBclose();
	}

	if (SUCCEED != zbx_ha_stop(&error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot stop HA manager: %s", error);
		zbx_free(error);
		zbx_ha_kill();
	}

	if (ZBX_NODE_STATUS_ACTIVE == ha_status)
	{
		free_metrics();
		zbx_ipc_service_free_env();
		free_configuration_cache();

		/* free history value cache */
		zbx_vc_destroy();

		/* free vmware support */
		zbx_vmware_destroy();

		free_selfmon_collector();
	}

	zbx_uninitialize_events();

	zbx_unload_modules();

	zabbix_log(LOG_LEVEL_INFORMATION, "Zabbix Server stopped. Zabbix %s (revision %s).",
			ZABBIX_VERSION, ZABBIX_REVISION);

	zabbix_close_log();

	zbx_locks_destroy();

#if defined(PS_OVERWRITE_ARGV)
	setproctitle_free_env();
#endif

	exit(EXIT_SUCCESS);
}
