/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "config.h"

#ifdef HAVE_SQLITE3
#	error SQLite is not supported as a main Zabbix database backend.
#endif

#include "postinit/postinit.h"
#include "dbconfig/dbconfig_server.h"
#include "housekeeper/housekeeper_server.h"
#include "poller/poller_server.h"
#include "timer/timer.h"
#include "trapper/trapper_server.h"
#include "escalator/escalator.h"
#include "proxypoller/proxypoller.h"
#include "taskmanager/taskmanager_server.h"
#include "connector/connector_server.h"
#include "service/service_server.h"
#include "lld/lld_manager.h"
#include "lld/lld_worker.h"
#include "reporter/reporter.h"
#include "events/events.h"
#include "ha/ha.h"
#include "rtc/rtc_server.h"
#include "stats/stats_server.h"
#include "diag/diag_server.h"
#include "preproc/preproc_server.h"
#include "lld/lld_protocol.h"
#include "cachehistory/cachehistory_server.h"
#include "discovery/discovery_server.h"
#include "autoreg/autoreg_server.h"
#include "dbconfigworker/dbconfigworker.h"

#include "zbxdiscovery.h"
#include "zbxdiscoverer.h"
#include "zbxexport.h"
#include "zbxself.h"

#include "zbxcfg.h"
#include "zbxpinger.h"
#include "zbxtrapper.h"
#include "zbxdbupgrade.h"
#include "zbxlog.h"
#include "zbxgetopt.h"
#include "zbxmutexs.h"
#include "zbxmodules.h"
#include "zbxnix.h"
#include "zbxcomms.h"
#include "zbxcacheconfig.h"
#include "zbxdb.h"
#include "zbxdbhigh.h"
#include "zbxeval.h"
#include "zbxjson.h"
#include "zbxpreproc.h"
#include "zbxstr.h"
#include "zbxtime.h"
#include "zbxvmware.h"
#include "zbxalerter.h"
#include "zbxdbsyncer.h"
#include "zbxconnector.h"
#include "zbxcachevalue.h"
#include "zbxcachehistory.h"
#include "zbxhistory.h"
#include "zbxvault.h"
#include "zbxtrends.h"
#include "zbxrtc.h"
#include "zbxstats.h"
#include "zbxscripts.h"
#include "zbxsnmptrapper.h"

#ifdef HAVE_OPENIPMI
#	include "zbxipmi.h"
#endif

#include "pgmanager/pg_manager.h"
#include "zbxavailability.h"
#include "zbxdbwrap.h"
#include "zbxip.h"
#include "zbxsysinfo.h"
#include "zbx_rtc_constants.h"
#include "zbxthreads.h"
#include "zbxicmpping.h"
#include "zbxipcservice.h"
#include "zbxdiag.h"
#include "zbxpoller.h"
#include "zbxhttppoller.h"
#include "zbx_ha_constants.h"
#include "zbxescalations.h"
#include "zbxbincommon.h"

#ifdef HAVE_LIBCURL
#	include "zbxcurl.h"
#endif

ZBX_GET_CONFIG_VAR2(const char*, const char*, zbx_progname, NULL)

static const char	title_message[] = "zabbix_server";
static const char	syslog_app_name[] = "zabbix_server";
static const char	*usage_message[] = {
	"[-c config-file]", NULL,
	"[-c config-file]", "-R runtime-option", NULL,
	"[-c config-file]", "-T", NULL,
	"-h", NULL,
	"-V", NULL,
	NULL	/* end of text */
};

static const char	*help_message[] = {
	"The core daemon of Zabbix software.",
	"",
	"Options:",
	"  -c --config config-file        Path to the configuration file",
	"                                 (default: \"" DEFAULT_CONFIG_FILE "\")",
	"  -f --foreground                Run Zabbix server in foreground",
	"  -R --runtime-control runtime-option   Perform administrative functions",
	"",
	"    Runtime control options:",
	"      " ZBX_CONFIG_CACHE_RELOAD "             Reload configuration cache",
	"      " ZBX_HOUSEKEEPER_EXECUTE "             Execute the housekeeper",
	"      " ZBX_TRIGGER_HOUSEKEEPER_EXECUTE "     Execute the trigger housekeeper",
	"      " ZBX_LOG_LEVEL_INCREASE "=target       Increase log level, affects all processes if",
	"                                        target is not specified",
	"      " ZBX_LOG_LEVEL_DECREASE "=target       Decrease log level, affects all processes if",
	"                                        target is not specified",
	"      " ZBX_SNMP_CACHE_RELOAD "               Reload SNMP cache",
	"      " ZBX_SECRETS_RELOAD "                  Reload secrets from Vault",
	"      " ZBX_DIAGINFO "=section                Log internal diagnostic information of the",
	"                                        section (historycache, preprocessing, alerting,",
	"                                        lld, valuecache, locks, connector) or everything if section is",
	"                                        not specified",
	"      " ZBX_PROF_ENABLE "=target              Enable profiling, affects all processes if",
	"                                        target is not specified",
	"      " ZBX_PROF_DISABLE "=target             Disable profiling, affects all processes if",
	"                                        target is not specified",
	"      " ZBX_SERVICE_CACHE_RELOAD "             Reload service manager cache",
	"      " ZBX_HA_STATUS "                        Display HA cluster status",
	"      " ZBX_HA_REMOVE_NODE "=target            Remove the HA node specified by its name or ID",
	"      " ZBX_HA_SET_FAILOVER_DELAY "=delay      Set HA failover delay",
	"      " ZBX_PROXY_CONFIG_CACHE_RELOAD "[=name] Reload configuration cache on proxy by its name,",
	"                                        comma-separated list can be used to pass multiple names.",
	"                                        All proxies will be reloaded if no names were specified.",
	"",
	"      Log level control targets:",
	"        process-type              All processes of specified type",
	"                                  (alerter, alert manager, availability manager, browser poller,",
	"                                  configuration syncer, configuration syncer worker, connector manager,",
	"                                  connector worker, discovery manager, escalator, ha manager, history poller,",
	"                                  history syncer, housekeeper, http poller, icmp pinger, internal poller,",
	"                                  ipmi manager, ipmi poller, java poller, odbc poller, poller, agent poller,",
	"                                  http agent poller, snmp poller, preprocessing manager, proxy group manager",
	"                                  proxy poller,self-monitoring, service manager, snmp trapper,",
	"                                  task manager, timer, trapper, unreachable poller, vmware collector)",
	"        process-type,N            Process type and number (e.g., poller,3)",
	"        pid                       Process identifier",
	"",
	"      Profiling control targets:",
	"        process-type              All processes of specified type",
	"                                  (alerter, alert manager, availability manager, browser poller,",
	"                                  configuration syncer, configuration syncer worker, connector manager,",
	"                                  connector worker, discovery manager, escalator, ha manager, history poller,",
	"                                  history syncer, housekeeper, http poller, icmp pinger, internal poller,",
	"                                  ipmi manager, ipmi poller, java poller, odbc poller, poller, agent poller,",
	"                                  http agent poller, snmp poller, preprocessing manager, proxy group manager",
	"                                  proxy poller,self-monitoring, service manager, snmp trapper,",
	"                                  task manager, timer, trapper, unreachable poller, vmware collector)",
	"        process-type,N            Process type and number (e.g., history syncer,1)",
	"        pid                       Process identifier",
	"        scope                     Profiling scope",
	"                                  (rwlock, mutex, processing) can be used with process-type",
	"                                  (e.g., history syncer,1,processing)",
	"",
	"  -T --test-config                Validate configuration file and exit",
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
	{"test-config",		0,	NULL,	'T'},
	{"help",		0,	NULL,	'h'},
	{"version",		0,	NULL,	'V'},
	{0}
};

/* short options */
static char	shortopts[] = "c:hVR:Tf";

/* end of COMMAND LINE OPTIONS */

ZBX_GET_CONFIG_VAR(int, zbx_threads_num, 0)
ZBX_GET_CONFIG_VAR(pid_t*, zbx_threads, NULL)

static int	*threads_flags;

static int	ha_status = ZBX_NODE_STATUS_UNKNOWN;
static int	ha_failover_delay = ZBX_HA_DEFAULT_FAILOVER_DELAY;

static sigset_t	orig_mask;

ZBX_GET_CONFIG_VAR2(char *, const char *, zbx_config_pid_file, NULL)
ZBX_GET_CONFIG_VAR(zbx_export_file_t *, problems_export, NULL)
ZBX_GET_CONFIG_VAR(zbx_export_file_t *, history_export, NULL)
ZBX_GET_CONFIG_VAR(zbx_export_file_t *, trends_export, NULL)
ZBX_GET_CONFIG_VAR(unsigned char, zbx_program_type, ZBX_PROGRAM_TYPE_SERVER)

int	config_forks[ZBX_PROCESS_TYPE_COUNT] = {
	5, /* ZBX_PROCESS_TYPE_POLLER */
	1, /* ZBX_PROCESS_TYPE_UNREACHABLE */
	0, /* ZBX_PROCESS_TYPE_IPMIPOLLER */
	1, /* ZBX_PROCESS_TYPE_PINGER */
	0, /* ZBX_PROCESS_TYPE_JAVAPOLLER */
	1, /* ZBX_PROCESS_TYPE_HTTPPOLLER */
	5, /* ZBX_PROCESS_TYPE_TRAPPER */
	0, /* ZBX_PROCESS_TYPE_SNMPTRAPPER */
	1, /* ZBX_PROCESS_TYPE_PROXYPOLLER */
	1, /* ZBX_PROCESS_TYPE_ESCALATOR */
	4, /* ZBX_PROCESS_TYPE_HISTSYNCER */
	5, /* ZBX_PROCESS_TYPE_DISCOVERER */
	3, /* ZBX_PROCESS_TYPE_ALERTER */
	1, /* ZBX_PROCESS_TYPE_TIMER */
	1, /* ZBX_PROCESS_TYPE_HOUSEKEEPER */
	0, /* ZBX_PROCESS_TYPE_DATASENDER */
	1, /* ZBX_PROCESS_TYPE_CONFSYNCER */
	1, /* ZBX_PROCESS_TYPE_SELFMON */
	0, /* ZBX_PROCESS_TYPE_VMWARE */
	0, /* ZBX_PROCESS_TYPE_COLLECTOR */
	0, /* ZBX_PROCESS_TYPE_LISTENER */
	0, /* ZBX_PROCESS_TYPE_ACTIVE_CHECKS */
	1, /* ZBX_PROCESS_TYPE_TASKMANAGER */
	0, /* ZBX_PROCESS_TYPE_IPMIMANAGER */
	1, /* ZBX_PROCESS_TYPE_ALERTMANAGER */
	1, /* ZBX_PROCESS_TYPE_PREPROCMAN */
	16, /* ZBX_PROCESS_TYPE_PREPROCESSOR */
	1, /* ZBX_PROCESS_TYPE_LLDMANAGER */
	2, /* ZBX_PROCESS_TYPE_LLDWORKER */
	1, /* ZBX_PROCESS_TYPE_ALERTSYNCER */
	5, /* ZBX_PROCESS_TYPE_HISTORYPOLLER */
	1, /* ZBX_PROCESS_TYPE_AVAILMAN */
	0, /* ZBX_PROCESS_TYPE_REPORTMANAGER */
	0, /* ZBX_PROCESS_TYPE_REPORTWRITER */
	1, /* ZBX_PROCESS_TYPE_SERVICEMAN */
	1, /* ZBX_PROCESS_TYPE_TRIGGERHOUSEKEEPER */
	1, /* ZBX_PROCESS_TYPE_ODBCPOLLER */
	0, /* ZBX_PROCESS_TYPE_CONNECTORMANAGER */
	0, /* ZBX_PROCESS_TYPE_CONNECTORWORKER */
	0, /* ZBX_PROCESS_TYPE_DISCOVERYMANAGER */
	1, /* ZBX_PROCESS_TYPE_HTTPAGENT_POLLER */
	1, /* ZBX_PROCESS_TYPE_AGENT_POLLER */
	1, /* ZBX_PROCESS_TYPE_SNMP_POLLER */
	1, /* ZBX_PROCESS_TYPE_INTERNAL_POLLER */
	1, /* ZBX_PROCESS_TYPE_DBCONFIGWORKER */
	1, /* ZBX_PROCESS_TYPE_PG_MANAGER */
	1, /* ZBX_PROCESS_TYPE_BROWSERPOLLER */
	1, /* ZBX_PROCESS_TYPE_HA_MANAGER */
};

static int	get_config_forks(unsigned char process_type)
{
	if (ZBX_PROCESS_TYPE_COUNT > process_type)
		return config_forks[process_type];

	return 0;
}

ZBX_GET_CONFIG_VAR2(char *, const char *, zbx_config_source_ip, NULL)
ZBX_GET_CONFIG_VAR2(char *, const char *, zbx_config_tmpdir, NULL)
ZBX_GET_CONFIG_VAR2(char *, const char *, zbx_config_fping_location, NULL)
ZBX_GET_CONFIG_VAR2(char *, const char *, zbx_config_fping6_location, NULL)
ZBX_GET_CONFIG_VAR2(char *, const char *, zbx_config_alert_scripts_path, NULL)
ZBX_GET_CONFIG_VAR(int, zbx_config_timeout, 3)
int	zbx_config_trapper_timeout = 300;

static int	config_startup_time		= 0;
static int	config_unavailable_delay	= 60;
static int	config_histsyncer_frequency	= 1;

static int	zbx_config_listen_port		= ZBX_DEFAULT_SERVER_PORT;
static char	*zbx_config_listen_ip		= NULL;
static char	*config_server		= NULL;		/* not used in zabbix_server, required for linking */

static int	config_housekeeping_frequency	= 1;
static int	config_max_housekeeper_delete	= 5000;		/* applies for every separate field value */
static int	config_confsyncer_frequency	= 10;

static int	config_problemhousekeeping_frequency = 60;

static int	config_vmware_frequency		= 60;
static int	config_vmware_perf_frequency	= 60;
static int	config_vmware_timeout		= 10;

static zbx_uint64_t	config_conf_cache_size		= 32 * ZBX_MEBIBYTE;
static zbx_uint64_t	config_history_cache_size	= 16 * ZBX_MEBIBYTE;
static zbx_uint64_t	config_history_index_cache_size	= 4 * ZBX_MEBIBYTE;
static zbx_uint64_t	config_trends_cache_size	= 4 * ZBX_MEBIBYTE;
static zbx_uint64_t	config_trend_func_cache_size	= 4 * ZBX_MEBIBYTE;
static zbx_uint64_t	config_value_cache_size		= 8 * ZBX_MEBIBYTE;
static zbx_uint64_t	config_vmware_cache_size	= 8 * ZBX_MEBIBYTE;

static int	config_unreachable_period		= 45;
static int	config_unreachable_delay		= 15;
static int	config_max_concurrent_checks_per_poller	= 1000;
static int	config_log_level		= LOG_LEVEL_WARNING;
static char	*config_externalscripts		= NULL;
static int	config_allow_unsupported_db_versions = 0;

ZBX_GET_CONFIG_VAR(int, zbx_config_enable_remote_commands, 0)
ZBX_GET_CONFIG_VAR(int, zbx_config_log_remote_commands, 0)
ZBX_GET_CONFIG_VAR(int, zbx_config_unsafe_user_parameters, 0)

static char	*zbx_config_snmptrap_file	= NULL;
static char	*config_java_gateway		= NULL;
static int	config_java_gateway_port	= ZBX_DEFAULT_GATEWAY_PORT;
static char	*config_ssh_key_location	= NULL;

/* how often Zabbix server sends configuration data to passive proxy, in seconds */
static int	config_proxyconfig_frequency	= 10;
static int	config_proxydata_frequency	= 1;	/* 1s */

static char	*CONFIG_LOAD_MODULE_PATH	= NULL;
static char	**CONFIG_LOAD_MODULE	= NULL;

static char	*CONFIG_USER		= NULL;

/* web monitoring */
static char	*config_ssl_ca_location = NULL;
static char	*config_ssl_cert_location = NULL;
static char	*config_ssl_key_location = NULL;

/* browser item */
static char	*config_webdriver_url = NULL;

static zbx_config_tls_t		*zbx_config_tls = NULL;
static zbx_config_export_t	zbx_config_export = {NULL, NULL, ZBX_GIBIBYTE};
static zbx_config_vault_t	zbx_config_vault = {NULL, NULL, NULL, NULL, NULL, NULL, NULL};

static zbx_db_config_t		*zbx_db_config = NULL;

char	*CONFIG_HA_NODE_NAME		= NULL;
char	*CONFIG_NODE_ADDRESS	= NULL;

static char	*CONFIG_SOCKET_PATH	= NULL;

static char	*config_history_storage_url		= NULL;
static char	*config_history_storage_opts		= NULL;
static int	config_history_storage_pipelines	= 0;
static char	*config_stats_allowed_ip		= NULL;
static int	config_tcp_max_backlog_size		= SOMAXCONN;
static char	*zbx_config_webservice_url		= NULL;
static int	config_service_manager_sync_frequency	= 60;
static int	config_vps_limit			= 0;
static int	config_vps_overcommit_limit		= 0;
static char	*config_file				= NULL;
static int	config_allow_root			= 0;
static int	config_enable_global_scripts		= 1;
static int	config_allow_software_update_check	= 1;
static char	*config_sms_devices			= NULL;
static zbx_config_log_t	log_file_cfg			= {NULL, NULL, ZBX_LOG_TYPE_UNDEFINED, 1};

struct zbx_db_version_info_t	db_version_info;

static	const zbx_events_funcs_t	events_cbs = {
	.add_event_cb			= zbx_add_event,
	.process_events_cb		= zbx_process_events,
	.clean_events_cb		= zbx_clean_events,
	.reset_event_recovery_cb	= zbx_reset_event_recovery,
	.export_events_cb		= zbx_export_events,
	.events_update_itservices_cb	= zbx_events_update_itservices
};

typedef struct
{
	zbx_rtc_t	*rtc;
	zbx_socket_t	*listen_sock;
}
zbx_on_exit_args_t;

static int	get_process_info_by_thread(int local_server_num, unsigned char *local_process_type,
		int *local_process_num)
{
	int	server_count = 0;

	if (0 == local_server_num)
	{
		/* fail if the main process is queried */
		return FAIL;
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_SERVICEMAN]))
	{
		/* start service manager process and load configuration cache in parallel */
		*local_process_type = ZBX_PROCESS_TYPE_SERVICEMAN;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_SERVICEMAN];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_CONFSYNCER]))
	{
		/* make initial configuration sync before worker processes are forked */
		*local_process_type = ZBX_PROCESS_TYPE_CONFSYNCER;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_CONFSYNCER];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_ALERTMANAGER]))
	{
		/* data collection processes might utilize CPU fully, start manager and worker processes beforehand */
		*local_process_type = ZBX_PROCESS_TYPE_ALERTMANAGER;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_ALERTMANAGER];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_ALERTER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_ALERTER;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_ALERTER];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_PREPROCMAN]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_PREPROCMAN;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_PREPROCMAN];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_LLDMANAGER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_LLDMANAGER;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_LLDMANAGER];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_LLDWORKER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_LLDWORKER;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_LLDWORKER];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_IPMIMANAGER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_IPMIMANAGER;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_IPMIMANAGER];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_HOUSEKEEPER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_HOUSEKEEPER;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_HOUSEKEEPER];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_TIMER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_TIMER;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_TIMER];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_HTTPPOLLER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_HTTPPOLLER;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_HTTPPOLLER];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_BROWSERPOLLER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_BROWSERPOLLER;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_BROWSERPOLLER];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_DISCOVERYMANAGER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_DISCOVERYMANAGER;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_DISCOVERYMANAGER];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_HISTSYNCER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_HISTSYNCER;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_HISTSYNCER];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_ESCALATOR]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_ESCALATOR;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_ESCALATOR];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_IPMIPOLLER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_IPMIPOLLER;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_IPMIPOLLER];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_JAVAPOLLER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_JAVAPOLLER;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_JAVAPOLLER];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_SNMPTRAPPER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_SNMPTRAPPER;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_SNMPTRAPPER];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_PROXYPOLLER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_PROXYPOLLER;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_PROXYPOLLER];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_SELFMON]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_SELFMON;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_SELFMON];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_VMWARE]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_VMWARE;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_VMWARE];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_TASKMANAGER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_TASKMANAGER;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_TASKMANAGER];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_POLLER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_POLLER;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_POLLER];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_UNREACHABLE]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_UNREACHABLE;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_UNREACHABLE];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_TRAPPER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_TRAPPER;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_TRAPPER];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_PINGER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_PINGER;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_PINGER];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_ALERTSYNCER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_ALERTSYNCER;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_ALERTSYNCER];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_HISTORYPOLLER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_HISTORYPOLLER;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_HISTORYPOLLER];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_AVAILMAN]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_AVAILMAN;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_AVAILMAN];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_REPORTMANAGER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_REPORTMANAGER;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_REPORTMANAGER];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_REPORTWRITER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_REPORTWRITER;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_REPORTWRITER];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_TRIGGERHOUSEKEEPER]))
	{
		/* start service manager process and load configuration cache in parallel */
		*local_process_type = ZBX_PROCESS_TYPE_TRIGGERHOUSEKEEPER;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_TRIGGERHOUSEKEEPER];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_ODBCPOLLER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_ODBCPOLLER;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_ODBCPOLLER];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_CONNECTORMANAGER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_CONNECTORMANAGER;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_CONNECTORMANAGER];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_CONNECTORWORKER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_CONNECTORWORKER;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_CONNECTORWORKER];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_HTTPAGENT_POLLER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_HTTPAGENT_POLLER;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_HTTPAGENT_POLLER];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_AGENT_POLLER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_AGENT_POLLER;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_AGENT_POLLER];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_SNMP_POLLER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_SNMP_POLLER;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_SNMP_POLLER];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_DBCONFIGWORKER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_DBCONFIGWORKER;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_DBCONFIGWORKER];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_INTERNAL_POLLER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_INTERNAL_POLLER;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_INTERNAL_POLLER];
	}
	else if (local_server_num <= (server_count += config_forks[ZBX_PROCESS_TYPE_PG_MANAGER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_PG_MANAGER;
		*local_process_num = local_server_num - server_count + config_forks[ZBX_PROCESS_TYPE_PG_MANAGER];
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
	config_startup_time = (int)time(NULL);

	if (NULL != CONFIG_HA_NODE_NAME && '\0' != *CONFIG_HA_NODE_NAME)
		zbx_db_config->read_only_recoverable = 1;

	if (NULL == zbx_db_config->dbhost)
		zbx_db_config->dbhost = zbx_strdup(zbx_db_config->dbhost, "localhost");

	if (NULL == zbx_config_snmptrap_file)
		zbx_config_snmptrap_file = zbx_strdup(zbx_config_snmptrap_file, "/tmp/zabbix_traps.tmp");

	if (NULL == zbx_config_pid_file)
		zbx_config_pid_file = zbx_strdup(zbx_config_pid_file, "/tmp/zabbix_server.pid");

	if (NULL == zbx_config_alert_scripts_path)
		zbx_config_alert_scripts_path = zbx_strdup(zbx_config_alert_scripts_path, DEFAULT_ALERT_SCRIPTS_PATH);

	if (NULL == CONFIG_LOAD_MODULE_PATH)
		CONFIG_LOAD_MODULE_PATH = zbx_strdup(CONFIG_LOAD_MODULE_PATH, DEFAULT_LOAD_MODULE_PATH);

	if (NULL == zbx_config_tmpdir)
		zbx_config_tmpdir = zbx_strdup(zbx_config_tmpdir, "/tmp");

	if (NULL == zbx_config_fping_location)
		zbx_config_fping_location = zbx_strdup(zbx_config_fping_location, "/usr/sbin/fping");
#ifdef HAVE_IPV6
	if (NULL == zbx_config_fping6_location)
		zbx_config_fping6_location = zbx_strdup(zbx_config_fping6_location, "/usr/sbin/fping6");
#endif
	if (NULL == config_externalscripts)
		config_externalscripts = zbx_strdup(config_externalscripts, DEFAULT_EXTERNAL_SCRIPTS_PATH);
#ifdef HAVE_LIBCURL
	if (NULL == config_ssl_cert_location)
		config_ssl_cert_location = zbx_strdup(config_ssl_cert_location, DEFAULT_SSL_CERT_LOCATION);

	if (NULL == config_ssl_key_location)
		config_ssl_key_location = zbx_strdup(config_ssl_key_location, DEFAULT_SSL_KEY_LOCATION);

	if (NULL == config_history_storage_opts)
		config_history_storage_opts = zbx_strdup(config_history_storage_opts, "uint,dbl,str,log,text");
#endif

#ifdef HAVE_SQLITE3
	config_max_housekeeper_delete = 0;
#endif

	if (NULL == log_file_cfg.log_type_str)
		log_file_cfg.log_type_str = zbx_strdup(log_file_cfg.log_type_str, ZBX_OPTION_LOGTYPE_FILE);

	if (NULL == CONFIG_SOCKET_PATH)
		CONFIG_SOCKET_PATH = zbx_strdup(CONFIG_SOCKET_PATH, "/tmp");

	if (0 != config_forks[ZBX_PROCESS_TYPE_IPMIPOLLER])
		config_forks[ZBX_PROCESS_TYPE_IPMIMANAGER] = 1;

	if (NULL == zbx_config_vault.url)
		zbx_config_vault.url = zbx_strdup(zbx_config_vault.url, "https://127.0.0.1:8200");

	if (0 != config_forks[ZBX_PROCESS_TYPE_REPORTWRITER])
		config_forks[ZBX_PROCESS_TYPE_REPORTMANAGER] = 1;

	if (0 != config_forks[ZBX_PROCESS_TYPE_CONNECTORWORKER])
		config_forks[ZBX_PROCESS_TYPE_CONNECTORMANAGER] = 1;

	if (0 != config_forks[ZBX_PROCESS_TYPE_DISCOVERER])
		config_forks[ZBX_PROCESS_TYPE_DISCOVERYMANAGER] = 1;
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

	if (0 == config_forks[ZBX_PROCESS_TYPE_UNREACHABLE] &&
			0 != config_forks[ZBX_PROCESS_TYPE_POLLER] + config_forks[ZBX_PROCESS_TYPE_JAVAPOLLER])
	{
		zabbix_log(LOG_LEVEL_CRIT, "\"StartPollersUnreachable\" configuration parameter must not be 0"
				" if regular or Java pollers are started");
		err = 1;
	}

	if ((NULL == config_java_gateway || '\0' == *config_java_gateway) &&
			0 < config_forks[ZBX_PROCESS_TYPE_JAVAPOLLER])
	{
		zabbix_log(LOG_LEVEL_CRIT, "\"JavaGateway\" configuration parameter is not specified or empty");
		err = 1;
	}

	if (0 != config_value_cache_size && 128 * ZBX_KIBIBYTE > config_value_cache_size)
	{
		zabbix_log(LOG_LEVEL_CRIT, "\"ValueCacheSize\" configuration parameter must be either 0"
				" or greater than 128KB");
		err = 1;
	}

	if (0 != config_trend_func_cache_size && 128 * ZBX_KIBIBYTE > config_trend_func_cache_size)
	{
		zabbix_log(LOG_LEVEL_CRIT, "\"TrendFunctionCacheSize\" configuration parameter must be either 0"
				" or greater than 128KB");
		err = 1;
	}

	if (NULL != zbx_config_source_ip && SUCCEED != zbx_is_supported_ip(zbx_config_source_ip))
	{
		zabbix_log(LOG_LEVEL_CRIT, "invalid \"SourceIP\" configuration parameter: '%s'", zbx_config_source_ip);
		err = 1;
	}

	if (NULL != config_stats_allowed_ip && FAIL == zbx_validate_peer_list(config_stats_allowed_ip, &ch_error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "invalid entry in \"StatsAllowedIP\" configuration parameter: %s", ch_error);
		zbx_free(ch_error);
		err = 1;
	}

	if (SUCCEED != zbx_validate_export_type(zbx_config_export.type, NULL))
	{
		zabbix_log(LOG_LEVEL_CRIT, "invalid \"ExportType\" configuration parameter: %s",
				zbx_config_export.type);
		err = 1;
	}

	if (NULL != CONFIG_NODE_ADDRESS &&
			(FAIL == zbx_parse_serveractive_element(CONFIG_NODE_ADDRESS, &address, &port, 10051) ||
			(FAIL == zbx_is_supported_ip(address) && FAIL == zbx_validate_hostname(address))))
	{
		zabbix_log(LOG_LEVEL_CRIT, "invalid \"NodeAddress\" configuration parameter: address \"%s\""
				" is invalid", CONFIG_NODE_ADDRESS);
		err = 1;
	}
	zbx_free(address);

#if !defined(HAVE_IPV6)
	err |= (FAIL == zbx_check_cfg_feature_str("Fping6Location", zbx_config_fping6_location, "IPv6 support"));
#endif
#if !defined(HAVE_LIBCURL)
	err |= (FAIL == zbx_check_cfg_feature_str("SSLCALocation", config_ssl_ca_location, "cURL library"));
	err |= (FAIL == zbx_check_cfg_feature_str("SSLCertLocation", config_ssl_cert_location, "cURL library"));
	err |= (FAIL == zbx_check_cfg_feature_str("SSLKeyLocation", config_ssl_key_location, "cURL library"));
	err |= (FAIL == zbx_check_cfg_feature_str("HistoryStorageURL", config_history_storage_url, "cURL library"));
	err |= (FAIL == zbx_check_cfg_feature_str("HistoryStorageTypes", config_history_storage_opts, "cURL library"));
	err |= (FAIL == zbx_check_cfg_feature_int("HistoryStorageDateIndex", config_history_storage_pipelines,
			"cURL library"));
	err |= (FAIL == zbx_check_cfg_feature_str("Vault", zbx_config_vault.name, "cURL library"));
	err |= (FAIL == zbx_check_cfg_feature_str("VaultToken", zbx_config_vault.token, "cURL library"));
	err |= (FAIL == zbx_check_cfg_feature_str("VaultDBPath", zbx_config_vault.db_path, "cURL library"));

	err |= (FAIL == zbx_check_cfg_feature_int("StartReportWriters", config_forks[ZBX_PROCESS_TYPE_REPORTWRITER],
			"cURL library"));
#else
	if (SUCCEED != zbx_curl_has_ssl(NULL))
	{
		err |= (FAIL == zbx_check_cfg_feature_str("SSLCALocation", config_ssl_ca_location,
				"cURL library that supports SSL/TLS"));
		/* can't check SSLCertLocation and SSLKeyLocation because they have defaults */
		err |= (FAIL == zbx_check_cfg_feature_str("Vault", zbx_config_vault.name,
				"cURL library that supports SSL/TLS"));
		err |= (FAIL == zbx_check_cfg_feature_str("VaultToken", zbx_config_vault.token,
				"cURL library that supports SSL/TLS"));
		err |= (FAIL == zbx_check_cfg_feature_str("VaultDBPath", zbx_config_vault.db_path,
				"cURL library that supports SSL/TLS"));
	}
#endif

#if !defined(HAVE_LIBXML2) || !defined(HAVE_LIBCURL)
	err |= (FAIL == zbx_check_cfg_feature_int("StartVMwareCollectors", config_forks[ZBX_PROCESS_TYPE_VMWARE],
			"VMware support"));

	/* parameters VMwareFrequency, VMwarePerfFrequency, VMwareCacheSize, VMwareTimeout are not checked here */
	/* because they have non-zero default values */
#endif

	if (SUCCEED != zbx_validate_log_parameters(task, &log_file_cfg))
		err = 1;

#if !(defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL))
	err |= (FAIL == zbx_check_cfg_feature_str("TLSCAFile", zbx_config_tls->ca_file, "TLS support"));
	err |= (FAIL == zbx_check_cfg_feature_str("TLSCRLFile", zbx_config_tls->crl_file, "TLS support"));
	err |= (FAIL == zbx_check_cfg_feature_str("TLSCertFile", zbx_config_tls->cert_file, "TLS support"));
	err |= (FAIL == zbx_check_cfg_feature_str("TLSKeyFile", zbx_config_tls->key_file, "TLS support"));
#endif
#if !(defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL))
	err |= (FAIL == zbx_check_cfg_feature_str("TLSCipherCert", zbx_config_tls->cipher_cert,
			"GnuTLS or OpenSSL"));
	err |= (FAIL == zbx_check_cfg_feature_str("TLSCipherPSK", zbx_config_tls->cipher_psk,
			"GnuTLS or OpenSSL"));
	err |= (FAIL == zbx_check_cfg_feature_str("TLSCipherAll", zbx_config_tls->cipher_all,
			"GnuTLS or OpenSSL"));
#endif
#if !defined(HAVE_OPENSSL)
	err |= (FAIL == zbx_check_cfg_feature_str("TLSCipherCert13", zbx_config_tls->cipher_cert13,
			"OpenSSL 1.1.1 or newer"));
	err |= (FAIL == zbx_check_cfg_feature_str("TLSCipherPSK13", zbx_config_tls->cipher_psk13,
			"OpenSSL 1.1.1 or newer"));
	err |= (FAIL == zbx_check_cfg_feature_str("TLSCipherAll13", zbx_config_tls->cipher_all13,
			"OpenSSL 1.1.1 or newer"));
#endif

#if !defined(HAVE_OPENIPMI)
	err |= (FAIL == zbx_check_cfg_feature_int("StartIPMIPollers", config_forks[ZBX_PROCESS_TYPE_IPMIPOLLER],
			"IPMI support"));
#endif

	err |= (FAIL == zbx_db_config_validate_features(zbx_db_config, zbx_program_type));

	if (0 != config_forks[ZBX_PROCESS_TYPE_REPORTWRITER] && NULL == zbx_config_webservice_url)
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
	zbx_cfg_line_t	cfg[] =
	{
		/* PARAMETER,			VAR,					TYPE,
				MANDATORY,		MIN,			MAX */
		{"StartDBSyncers",		&config_forks[ZBX_PROCESS_TYPE_HISTSYNCER],
											ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	1,			100},
		{"StartDiscoverers",		&config_forks[ZBX_PROCESS_TYPE_DISCOVERER],
											ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	0,			1000},
		{"StartHTTPPollers",		&config_forks[ZBX_PROCESS_TYPE_HTTPPOLLER],
											ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	0,			1000},
		{"StartPingers",		&config_forks[ZBX_PROCESS_TYPE_PINGER],
											ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	0,			1000},
		{"StartPollers",		&config_forks[ZBX_PROCESS_TYPE_POLLER],
											ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	0,			1000},
		{"StartPollersUnreachable",	&config_forks[ZBX_PROCESS_TYPE_UNREACHABLE],
											ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	0,			1000},
		{"StartIPMIPollers",		&config_forks[ZBX_PROCESS_TYPE_IPMIPOLLER],
											ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	0,			1000},
		{"StartTimers",			&config_forks[ZBX_PROCESS_TYPE_TIMER],	ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	1,			1000},
		{"StartTrappers",		&config_forks[ZBX_PROCESS_TYPE_TRAPPER],
											ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	0,			1000},
		{"StartJavaPollers",		&config_forks[ZBX_PROCESS_TYPE_JAVAPOLLER],
											ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	0,			1000},
		{"StartEscalators",		&config_forks[ZBX_PROCESS_TYPE_ESCALATOR],
											ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	1,			100},
		{"JavaGateway",			&config_java_gateway,			ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"JavaGatewayPort",		&config_java_gateway_port,		ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	1024,			32767},
		{"SNMPTrapperFile",		&zbx_config_snmptrap_file,		ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"StartSNMPTrapper",		&config_forks[ZBX_PROCESS_TYPE_SNMPTRAPPER],
											ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	0,			1},
		{"CacheSize",			&config_conf_cache_size,		ZBX_CFG_TYPE_UINT64,
				ZBX_CONF_PARM_OPT,	128 * ZBX_KIBIBYTE,	__UINT64_C(64) * ZBX_GIBIBYTE},
		{"HistoryCacheSize",		&config_history_cache_size,		ZBX_CFG_TYPE_UINT64,
				ZBX_CONF_PARM_OPT,	128 * ZBX_KIBIBYTE,	__UINT64_C(2) * ZBX_GIBIBYTE},
		{"HistoryIndexCacheSize",	&config_history_index_cache_size,	ZBX_CFG_TYPE_UINT64,
				ZBX_CONF_PARM_OPT,	128 * ZBX_KIBIBYTE,	__UINT64_C(2) * ZBX_GIBIBYTE},
		{"TrendCacheSize",		&config_trends_cache_size,		ZBX_CFG_TYPE_UINT64,
				ZBX_CONF_PARM_OPT,	128 * ZBX_KIBIBYTE,	__UINT64_C(2) * ZBX_GIBIBYTE},
		{"TrendFunctionCacheSize",	&config_trend_func_cache_size,		ZBX_CFG_TYPE_UINT64,
				ZBX_CONF_PARM_OPT,	0,			__UINT64_C(2) * ZBX_GIBIBYTE},
		{"ValueCacheSize",		&config_value_cache_size,		ZBX_CFG_TYPE_UINT64,
				ZBX_CONF_PARM_OPT,	0,			__UINT64_C(64) * ZBX_GIBIBYTE},
		{"CacheUpdateFrequency",	&config_confsyncer_frequency,		ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	1,			SEC_PER_HOUR},
		{"HousekeepingFrequency",	&config_housekeeping_frequency,		ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	0,			24},
		{"MaxHousekeeperDelete",	&config_max_housekeeper_delete,		ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	0,			1000000},
		{"TmpDir",			&zbx_config_tmpdir,			ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"FpingLocation",		&zbx_config_fping_location,		ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"Fping6Location",		&zbx_config_fping6_location,		ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"Timeout",			&zbx_config_timeout,			ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	1,			30},
		{"TrapperTimeout",		&zbx_config_trapper_timeout,		ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	1,			300},
		{"UnreachablePeriod",		&config_unreachable_period,		ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	1,			SEC_PER_HOUR},
		{"UnreachableDelay",		&config_unreachable_delay,		ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	1,			SEC_PER_HOUR},
		{"UnavailableDelay",		&config_unavailable_delay,		ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	1,			SEC_PER_HOUR},
		{"ListenIP",			&zbx_config_listen_ip,			ZBX_CFG_TYPE_STRING_LIST,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"ListenPort",			&zbx_config_listen_port,		ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	1024,			32767},
		{"SourceIP",			&zbx_config_source_ip,			ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"DebugLevel",			&config_log_level,			ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	0,			5},
		{"PidFile",			&zbx_config_pid_file,			ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"LogType",			&log_file_cfg.log_type_str,		ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"LogFile",			&log_file_cfg.log_file_name,		ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"LogFileSize",			&log_file_cfg.log_file_size,		ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	0,			1024},
		{"AlertScriptsPath",		&zbx_config_alert_scripts_path,		ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"ExternalScripts",		&config_externalscripts,		ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"DBHost",			&(zbx_db_config->dbhost),	ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"DBName",			&(zbx_db_config->dbname),	ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_MAND,	0,			0},
		{"DBSchema",			&(zbx_db_config->dbschema),	ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"DBUser",			&(zbx_db_config->dbuser),	ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"DBPassword",			&(zbx_db_config->dbpassword),
											ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"VaultToken",			&(zbx_config_vault.token),		ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"Vault",			&(zbx_config_vault.name),		ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"VaultTLSCertFile",		&(zbx_config_vault.tls_cert_file),	ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"VaultTLSKeyFile",		&(zbx_config_vault.tls_key_file),	ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"VaultURL",			&(zbx_config_vault.url),		ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"VaultPrefix",			&(zbx_config_vault.prefix),		ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"VaultDBPath",			&(zbx_config_vault.db_path),		ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"DBSocket",			&(zbx_db_config->dbsocket),	ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"DBPort",			&(zbx_db_config->dbport),	ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	1024,			65535},
		{"AllowUnsupportedDBVersions",	&config_allow_unsupported_db_versions,	ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	0,			1},
		{"DBTLSConnect",		&(zbx_db_config->db_tls_connect),
											ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"DBTLSCertFile",		&(zbx_db_config->db_tls_cert_file),
											ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"DBTLSKeyFile",		&(zbx_db_config->db_tls_key_file),
											ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"DBTLSCAFile",			&(zbx_db_config->db_tls_ca_file),
											ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"DBTLSCipher",			&(zbx_db_config->db_tls_cipher),
											ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"DBTLSCipher13",		&(zbx_db_config->db_tls_cipher_13),
											ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"SSHKeyLocation",		&config_ssh_key_location,		ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"LogSlowQueries",		&(zbx_db_config->log_slow_queries),	ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	0,			3600000},
		{"StartProxyPollers",		&config_forks[ZBX_PROCESS_TYPE_PROXYPOLLER],
											ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	0,			250},
		{"ProxyConfigFrequency",	&config_proxyconfig_frequency,		ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	1,			SEC_PER_WEEK},
		{"ProxyDataFrequency",		&config_proxydata_frequency,		ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	1,			SEC_PER_HOUR},
		{"LoadModulePath",		&CONFIG_LOAD_MODULE_PATH,		ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"LoadModule",			&CONFIG_LOAD_MODULE,			ZBX_CFG_TYPE_MULTISTRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"StartVMwareCollectors",	&config_forks[ZBX_PROCESS_TYPE_VMWARE],	ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	0,			250},
		{"VMwareFrequency",		&config_vmware_frequency,		ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	10,			SEC_PER_DAY},
		{"VMwarePerfFrequency",		&config_vmware_perf_frequency,		ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	10,			SEC_PER_DAY},
		{"VMwareCacheSize",		&config_vmware_cache_size,		ZBX_CFG_TYPE_UINT64,
				ZBX_CONF_PARM_OPT,	256 * ZBX_KIBIBYTE,	__UINT64_C(2) * ZBX_GIBIBYTE},
		{"VMwareTimeout",		&config_vmware_timeout,			ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	1,			300},
		{"AllowRoot",			&config_allow_root,			ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	0,			1},
		{"User",			&CONFIG_USER,				ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"SSLCALocation",		&config_ssl_ca_location,		ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"SSLCertLocation",		&config_ssl_cert_location,		ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"SSLKeyLocation",		&config_ssl_key_location,		ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"TLSCAFile",			&(zbx_config_tls->ca_file),		ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"TLSCRLFile",			&(zbx_config_tls->crl_file),		ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"TLSCertFile",			&(zbx_config_tls->cert_file),		ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"TLSKeyFile",			&(zbx_config_tls->key_file),		ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"TLSCipherCert13",		&(zbx_config_tls->cipher_cert13),	ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"TLSCipherCert",		&(zbx_config_tls->cipher_cert),		ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"TLSCipherPSK13",		&(zbx_config_tls->cipher_psk13),	ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"TLSCipherPSK",		&(zbx_config_tls->cipher_psk),		ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"TLSCipherAll13",		&(zbx_config_tls->cipher_all13),	ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"TLSCipherAll",		&(zbx_config_tls->cipher_all),		ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"SocketDir",			&CONFIG_SOCKET_PATH,			ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"StartAlerters",		&config_forks[ZBX_PROCESS_TYPE_ALERTER],
											ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	1,			100},
		{"StartPreprocessors",		&config_forks[ZBX_PROCESS_TYPE_PREPROCESSOR],
											ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	1,			1000},
		{"HistoryStorageURL",		&config_history_storage_url,		ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"HistoryStorageTypes",		&config_history_storage_opts,		ZBX_CFG_TYPE_STRING_LIST,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"HistoryStorageDateIndex",	&config_history_storage_pipelines,	ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	0,			1},
		{"ExportDir",			&(zbx_config_export.dir),		ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"ExportType",			&(zbx_config_export.type),		ZBX_CFG_TYPE_STRING_LIST,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"ExportFileSize",		&(zbx_config_export.file_size),		ZBX_CFG_TYPE_UINT64,
				ZBX_CONF_PARM_OPT,	ZBX_MEBIBYTE,		ZBX_GIBIBYTE},
		{"StartLLDProcessors",		&config_forks[ZBX_PROCESS_TYPE_LLDWORKER],
											ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	1,			100},
		{"StatsAllowedIP",		&config_stats_allowed_ip,		ZBX_CFG_TYPE_STRING_LIST,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"StartHistoryPollers",		&config_forks[ZBX_PROCESS_TYPE_HISTORYPOLLER],
											ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	0,			1000},
		{"StartReportWriters",		&config_forks[ZBX_PROCESS_TYPE_REPORTWRITER],
											ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	0,			100},
		{"WebServiceURL",		&zbx_config_webservice_url,		ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"ProblemHousekeepingFrequency",
						&config_problemhousekeeping_frequency,
											ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	1,			3600},
		{"ServiceManagerSyncFrequency",	&config_service_manager_sync_frequency,	ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	1,			3600},
		{"ListenBacklog",		&config_tcp_max_backlog_size,		ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	0,			INT_MAX},
		{"HANodeName",			&CONFIG_HA_NODE_NAME,			ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"NodeAddress",			&CONFIG_NODE_ADDRESS,			ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"StartODBCPollers",		&config_forks[ZBX_PROCESS_TYPE_ODBCPOLLER],
											ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	0,			1000},
		{"StartConnectors",		&config_forks[ZBX_PROCESS_TYPE_CONNECTORWORKER],
											ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	0,			1000},
		{"StartHTTPAgentPollers",	&config_forks[ZBX_PROCESS_TYPE_HTTPAGENT_POLLER],
											ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	0,			1000},
		{"StartAgentPollers",		&config_forks[ZBX_PROCESS_TYPE_AGENT_POLLER],
											ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	0,			1000},
		{"StartSNMPPollers",		&config_forks[ZBX_PROCESS_TYPE_SNMP_POLLER],
											ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	0,			1000},
		{"MaxConcurrentChecksPerPoller",
						&config_max_concurrent_checks_per_poller,
											ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	1,			1000},
		{"VPSLimit",			&config_vps_limit,			ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	0,			ZBX_MEBIBYTE},
		{"VPSOvercommitLimit",		&config_vps_overcommit_limit,		ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	0,			ZBX_MEBIBYTE},
		{"EnableGlobalScripts",		&config_enable_global_scripts,		ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	0,			1},
		{"AllowSoftwareUpdateCheck",	&config_allow_software_update_check,	ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	0,			1},
		{"StartBrowserPollers",		&config_forks[ZBX_PROCESS_TYPE_BROWSERPOLLER], ZBX_CFG_TYPE_INT,
				ZBX_CONF_PARM_OPT,	0,			1000},
		{"WebDriverURL",		&config_webdriver_url,			ZBX_CFG_TYPE_STRING,
				ZBX_CONF_PARM_OPT,	0,			0},
		{"SMSDevices",			&config_sms_devices,			ZBX_CFG_TYPE_STRING_LIST,
				ZBX_CONF_PARM_OPT,	0,			1},
		{0}
	};

	/* initialize multistrings */
	zbx_strarr_init(&CONFIG_LOAD_MODULE);
	zbx_parse_cfg_file(config_file, cfg, ZBX_CFG_FILE_REQUIRED, ZBX_CFG_STRICT, ZBX_CFG_EXIT_FAILURE,
			ZBX_CFG_ENVVAR_USE);
	zbx_set_defaults();

	log_file_cfg.log_type = zbx_get_log_type(log_file_cfg.log_type_str);

	zbx_validate_config(task);
#if defined(HAVE_MYSQL) || defined(HAVE_POSTGRESQL)
	zbx_db_config_validate(zbx_db_config);
#endif
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_validate_config(zbx_config_tls, config_forks[ZBX_PROCESS_TYPE_ACTIVE_CHECKS],
			config_forks[ZBX_PROCESS_TYPE_LISTENER], get_zbx_program_type);
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

static void	zbx_on_exit(int ret, void *on_exit_args)
{
	char	*error = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "zbx_on_exit() called with ret:%d", ret);

	if (NULL != zbx_threads)
	{
		/* wait for all child processes to exit */
		zbx_threads_kill_and_wait(zbx_threads, threads_flags, zbx_threads_num, ret);

		zbx_free(zbx_threads);
		zbx_free(threads_flags);
	}

#ifdef HAVE_PTHREAD_PROCESS_SHARED
		zbx_locks_disable();
#endif
	if (SUCCEED != zbx_ha_stop(&error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot stop HA manager: %s", error);
		zbx_free(error);
		zbx_ha_kill();
	}

	if (ZBX_NODE_STATUS_ACTIVE == ha_status)
	{
		zbx_free_metrics();
		zbx_ipc_service_free_env();

		zbx_db_connect(ZBX_DB_CONNECT_EXIT);
		zbx_free_database_cache(ZBX_SYNC_ALL, &events_cbs, config_history_storage_pipelines);
		zbx_db_close();

		zbx_free_configuration_cache();

		/* free history value cache */
		zbx_vc_destroy();

		zbx_deinit_remote_commands_cache();

		/* free vmware support */
		zbx_vmware_destroy();
	}

	zbx_free_selfmon_collector();

	zbx_uninitialize_events();

	zbx_unload_modules();

	zabbix_log(LOG_LEVEL_INFORMATION, "Zabbix Server stopped. Zabbix %s (revision %s).",
			ZABBIX_VERSION, ZABBIX_REVISION);

	if (NULL != on_exit_args)
	{
		zbx_on_exit_args_t	*args = (zbx_on_exit_args_t *)on_exit_args;

		if (NULL != args->listen_sock)
			zbx_tcp_unlisten(args->listen_sock);

		if (NULL != args->rtc)
			zbx_ipc_service_close(&args->rtc->service);
	}

	zbx_close_log();

	zbx_locks_destroy();

	zbx_setproctitle_deinit();

	if (SUCCEED == zbx_is_export_enabled(ZBX_FLAG_EXPTYPE_EVENTS))
		zbx_export_deinit(problems_export);

	if (SUCCEED == zbx_is_export_enabled(ZBX_FLAG_EXPTYPE_HISTORY))
		zbx_export_deinit(history_export);

	if (SUCCEED == zbx_is_export_enabled(ZBX_FLAG_EXPTYPE_TRENDS))
		zbx_export_deinit(trends_export);

	zbx_config_tls_free(zbx_config_tls);
	zbx_db_config_free(zbx_db_config);
	zbx_deinit_library_export();

	exit(EXIT_SUCCESS);
}

/******************************************************************************
 *                                                                            *
 * Purpose: executes server processes                                         *
 *                                                                            *
 ******************************************************************************/
int	main(int argc, char **argv)
{
	static zbx_config_icmpping_t	config_icmpping = {
		get_zbx_config_source_ip,
		get_zbx_config_fping_location,
		get_zbx_config_fping6_location,
		get_zbx_config_tmpdir,
		get_zbx_progname};

	ZBX_TASK_EX			t = {ZBX_TASK_START, 0, 0, NULL};
	char				ch;
	int				opt_c = 0, opt_r = 0, opt_t = 0, opt_f = 0;

	/* see description of 'optarg' in 'man 3 getopt' */
	char				*zbx_optarg = NULL;

	/* see description of 'optind' in 'man 3 getopt' */
	int				zbx_optind = 0;

	argv = zbx_setproctitle_init(argc, argv);
	zbx_progname = get_program_name(argv[0]);
	zbx_config_tls = zbx_config_tls_new();
	zbx_db_config = zbx_db_config_create();

	/* initialize libraries before using */
	zbx_init_library_common(zbx_log_impl, get_zbx_progname, zbx_backtrace);
	zbx_init_library_nix(get_zbx_progname, get_process_info_by_thread);
	zbx_init_library_dbupgrade(get_zbx_program_type, get_zbx_config_timeout);
	zbx_init_library_dbwrap(zbx_lld_process_agent_result, zbx_preprocess_item_value, zbx_preprocessor_flush);
	zbx_init_library_icmpping(&config_icmpping);
	zbx_init_library_ipcservice(zbx_program_type);
	zbx_init_library_stats(get_zbx_program_type);
	zbx_init_library_sysinfo(get_zbx_config_timeout, get_zbx_config_enable_remote_commands,
			get_zbx_config_log_remote_commands, get_zbx_config_unsafe_user_parameters,
			get_zbx_config_source_ip, NULL, NULL, NULL, NULL, NULL);
	zbx_init_library_db(zbx_db_config);
	zbx_init_library_preproc(preproc_prepare_value_server, preproc_flush_value_server, get_zbx_progname);
	zbx_init_library_eval(zbx_dc_get_expressions_by_name);

	/* parse the command-line */
	while ((char)EOF != (ch = (char)zbx_getopt_long(argc, argv, shortopts, longopts, NULL, &zbx_optarg,
			&zbx_optind)))
	{
		switch (ch)
		{
			case 'c':
				opt_c++;
				if (NULL == config_file)
					config_file = zbx_strdup(config_file, zbx_optarg);
				break;
			case 'R':
				opt_r++;
				t.opts = zbx_strdup(t.opts, zbx_optarg);
				t.task = ZBX_TASK_RUNTIME_CONTROL;
				break;
			case 'T':
				opt_t++;
				t.task = ZBX_TASK_TEST_CONFIG;
				break;
			case 'h':
				zbx_print_help(zbx_progname, help_message, usage_message, NULL);
				exit(EXIT_SUCCESS);
				break;
			case 'V':
				zbx_print_version(title_message);
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
				printf("\n");
				zbx_tls_version();
#endif
				exit(EXIT_SUCCESS);
				break;
			case 'f':
				opt_f++;
				t.flags |= ZBX_TASK_FLAG_FOREGROUND;
				break;
			default:
				zbx_print_usage(zbx_progname, usage_message);
				exit(EXIT_FAILURE);
				break;
		}
	}

	/* every option may be specified only once */
	if (1 < opt_c || 1 < opt_r || 1 < opt_t || 1 < opt_f)
	{
		if (1 < opt_c)
			zbx_error("option \"-c\" or \"--config\" specified multiple times");
		if (1 < opt_r)
			zbx_error("option \"-R\" or \"--runtime-control\" specified multiple times");
		if (1 < opt_t)
			zbx_error("option \"-T\" or \"--test-config\" specified multiple times");
		if (1 < opt_f)
			zbx_error("option \"-f\" or \"--foreground\" specified multiple times");

		exit(EXIT_FAILURE);
	}

	if (0 != opt_t && 0 != opt_r)
	{
		zbx_error("option \"-T\" or \"--test-config\" cannot be specified with \"-R\"");
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

	if (NULL == config_file)
		config_file = zbx_strdup(NULL, DEFAULT_CONFIG_FILE);

	/* required for simple checks */
	zbx_init_metrics();
	zbx_init_library_cfg(zbx_program_type, config_file);

	if (ZBX_TASK_TEST_CONFIG == t.task)
		printf("Validating configuration file \"%s\"\n", config_file);

	zbx_load_config(&t);

	if (ZBX_TASK_TEST_CONFIG == t.task)
	{
		printf("Validation successful\n");
		exit(EXIT_SUCCESS);
	}

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

		if (SUCCEED != (ret = rtc_process(t.opts, zbx_config_timeout, &error)))
		{
			zbx_error("Cannot perform runtime control command: %s", error);
			zbx_free(error);
		}

		exit(SUCCEED == ret ? EXIT_SUCCESS : EXIT_FAILURE);
	}

	zbx_init_escalations(config_forks[ZBX_PROCESS_TYPE_ESCALATOR], zbx_rtc_notify_generic);

	return zbx_daemon_start(config_allow_root, CONFIG_USER, t.flags, get_zbx_config_pid_file, zbx_on_exit,
			log_file_cfg.log_type, log_file_cfg.log_file_name, NULL, get_zbx_threads, get_zbx_threads_num);
}

static int	zbx_check_db(void)
{
	struct zbx_json	db_version_json;
	int		ret;

	memset(&db_version_info, 0, sizeof(db_version_info));
	ret = zbx_db_check_version_info(&db_version_info, config_allow_unsupported_db_versions, zbx_program_type);

	if (SUCCEED == ret)
		ret = zbx_db_check_extension(&db_version_info, config_allow_unsupported_db_versions);

	if (SUCCEED == ret)
	{
		zbx_ha_mode_t	ha_mode;

		if (NULL != CONFIG_HA_NODE_NAME && '\0' != *CONFIG_HA_NODE_NAME)
			ha_mode = ZBX_HA_MODE_CLUSTER;
		else
			ha_mode = ZBX_HA_MODE_STANDALONE;

		if (SUCCEED != (ret = zbx_db_check_version_and_upgrade(ha_mode)))
			goto out;
	}

	if (SUCCEED == zbx_db_field_exists("config", "dbversion_status"))
	{
		zbx_json_initarray(&db_version_json, ZBX_JSON_STAT_BUF_LEN);

		if (SUCCEED == zbx_db_pk_exists("history"))
		{
			db_version_info.history_pk = 1;
		}
		else
		{
			db_version_info.history_pk = 0;
			zabbix_log(LOG_LEVEL_WARNING, "database could be upgraded to use primary keys in history tables");
		}

		zbx_db_version_json_create(&db_version_json, &db_version_info);

		if (SUCCEED == ret)
		{
			zbx_history_check_version(&db_version_json, &ret, config_allow_unsupported_db_versions,
					config_history_storage_url);
		}

		zbx_db_flush_version_requirements(db_version_json.buffer);
		zbx_json_free(&db_version_json);
	}
out:
	if (SUCCEED != ret)
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "Zabbix Server stopped. Zabbix %s (revision %s).",
				ZABBIX_VERSION, ZABBIX_REVISION);
		zbx_db_version_info_clear(&db_version_info);
	}
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: save Zabbix server status to database                             *
 *                                                                            *
 ******************************************************************************/
static void	zbx_db_save_server_status(void)
{
	struct zbx_json	json;

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);

	zbx_json_addstring(&json, "version", ZABBIX_VERSION, ZBX_JSON_TYPE_STRING);

	zbx_json_addobject(&json, "configuration");
	zbx_json_addstring(&json, "enable_global_scripts", (1 == config_enable_global_scripts ? "true" : "false"),
			ZBX_JSON_TYPE_INT);
	zbx_json_addstring(&json, "allow_software_update_check",
			(1 == config_allow_software_update_check ? "true" : "false"), ZBX_JSON_TYPE_INT);

	zbx_json_close(&json);

	zbx_json_close(&json);

	if (ZBX_DB_OK > zbx_db_execute("update config set server_status='%s'", json.buffer))
		zabbix_log(LOG_LEVEL_WARNING, "Failed to save server status to database");

	zbx_json_free(&json);
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize shared resources and start processes                   *
 *                                                                            *
 ******************************************************************************/
static int	server_startup(zbx_socket_t *listen_sock, int *ha_stat, int *ha_failover, zbx_rtc_t *rtc,
		zbx_on_exit_args_t *exit_args)
{
	int				i, ret = SUCCEED;
	char				*error = NULL;

	zbx_config_comms_args_t		config_comms = {zbx_config_tls, NULL, config_server, 0, zbx_config_timeout,
							zbx_config_trapper_timeout, zbx_config_source_ip,
							config_ssl_ca_location, config_ssl_cert_location,
							config_ssl_key_location};

	zbx_thread_args_t		thread_args;

	zbx_thread_poller_args		poller_args = {&config_comms, get_zbx_program_type, zbx_progname,
							ZBX_NO_POLLER, config_startup_time, config_unavailable_delay,
							config_unreachable_period, config_unreachable_delay,
							config_max_concurrent_checks_per_poller, get_config_forks,
							config_java_gateway, config_java_gateway_port,
							config_externalscripts, zbx_get_value_internal_ext_server,
							config_ssh_key_location, config_webdriver_url};
	zbx_thread_trapper_args		trapper_args = {&config_comms, &zbx_config_vault, get_zbx_program_type,
							zbx_progname, &events_cbs, listen_sock, config_startup_time,
							config_proxydata_frequency, get_config_forks,
							config_stats_allowed_ip, config_java_gateway,
							config_java_gateway_port, config_externalscripts,
							config_enable_global_scripts, zbx_get_value_internal_ext_server,
							config_ssh_key_location, config_webdriver_url,
							zbx_trapper_process_request_server,
							zbx_autoreg_update_host_server};
	zbx_thread_escalator_args	escalator_args = {zbx_config_tls, get_zbx_program_type, zbx_config_timeout,
							zbx_config_trapper_timeout, zbx_config_source_ip,
							config_ssh_key_location, get_config_forks,
							config_enable_global_scripts};
	zbx_thread_proxy_poller_args	proxy_poller_args = {zbx_config_tls, &zbx_config_vault, get_zbx_program_type,
							zbx_config_timeout, zbx_config_trapper_timeout,
							zbx_config_source_ip, config_ssl_ca_location,
							config_ssl_cert_location, config_ssl_key_location,
							&events_cbs, config_proxyconfig_frequency,
							config_proxydata_frequency};
	zbx_thread_httppoller_args	httppoller_args = {zbx_config_source_ip, config_ssl_ca_location,
							config_ssl_cert_location, config_ssl_key_location};
	zbx_thread_discoverer_args	discoverer_args = {zbx_config_tls, get_zbx_program_type, get_zbx_progname,
							zbx_config_timeout, config_forks[ZBX_PROCESS_TYPE_DISCOVERER],
							zbx_config_source_ip, &events_cbs, zbx_discovery_open_server,
							zbx_discovery_close_server, zbx_discovery_find_host_server,
							zbx_discovery_update_host_server,
							zbx_discovery_update_service_server,
							zbx_discovery_update_service_down_server,
							zbx_discovery_update_drule_server};
	zbx_thread_report_writer_args	report_writer_args = {zbx_config_tls->ca_file, zbx_config_tls->cert_file,
							zbx_config_tls->key_file, zbx_config_source_ip,
							zbx_config_webservice_url};
	zbx_thread_housekeeper_args	housekeeper_args = {&db_version_info, zbx_config_timeout,
							config_housekeeping_frequency, config_max_housekeeper_delete};
	zbx_thread_server_trigger_housekeeper_args	trigger_housekeeper_args = {zbx_config_timeout,
							config_problemhousekeeping_frequency};
	zbx_thread_taskmanager_args	taskmanager_args = {zbx_config_timeout, config_startup_time};
	zbx_thread_dbconfig_args	dbconfig_args = {&zbx_config_vault, zbx_config_timeout,
							config_proxyconfig_frequency, config_proxydata_frequency,
							config_confsyncer_frequency, zbx_config_source_ip,
							config_ssl_ca_location, config_ssl_cert_location,
							config_ssl_key_location};
	zbx_thread_alerter_args		alerter_args = {zbx_config_source_ip, config_ssl_ca_location,
							config_sms_devices};
	zbx_thread_pinger_args		pinger_args = {zbx_config_timeout};
	zbx_thread_pp_manager_args	preproc_man_args = {
						.workers_num = config_forks[ZBX_PROCESS_TYPE_PREPROCESSOR],
						.config_timeout = zbx_config_timeout,
						zbx_config_source_ip};
#ifdef HAVE_OPENIPMI
	zbx_thread_ipmi_manager_args	ipmi_manager_args = {zbx_config_timeout, config_unavailable_delay,
							config_unreachable_period, config_unreachable_delay,
							get_config_forks};
#endif
	zbx_thread_connector_worker_args	connector_worker_args = {zbx_config_source_ip, config_ssl_ca_location,
									config_ssl_cert_location,
									config_ssl_key_location};
	zbx_thread_report_manager_args	report_manager_args = {get_config_forks};
	zbx_thread_alert_syncer_args	alert_syncer_args = {config_confsyncer_frequency};
	zbx_thread_alert_manager_args	alert_manager_args = {get_config_forks, get_zbx_config_alert_scripts_path,
								zbx_db_config, zbx_config_source_ip};
	zbx_thread_lld_manager_args	lld_manager_args = {get_config_forks};
	zbx_thread_connector_manager_args	connector_manager_args = {get_config_forks};
	zbx_thread_dbsyncer_args		dbsyncer_args = {&events_cbs, config_histsyncer_frequency,
								zbx_config_timeout, config_history_storage_pipelines};
	zbx_thread_vmware_args			vmware_args = {zbx_config_source_ip, config_vmware_frequency,
								config_vmware_perf_frequency, config_vmware_timeout};
	zbx_thread_timer_args		timer_args = {get_config_forks};
	zbx_thread_snmptrapper_args	snmptrapper_args = {.config_snmptrap_file = zbx_config_snmptrap_file,
								.config_ha_node_name = CONFIG_HA_NODE_NAME};
	zbx_thread_service_manager_args	service_manager_args = {.config_timeout = zbx_config_timeout,
								.config_service_manager_sync_frequency =
								config_service_manager_sync_frequency};

	if (SUCCEED != zbx_init_database_cache(get_zbx_program_type, zbx_sync_server_history, config_history_cache_size,
			config_history_index_cache_size, &config_trends_cache_size, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize database cache: %s", error);
		zbx_free(error);
		return FAIL;
	}

	if (SUCCEED != zbx_init_configuration_cache(get_zbx_program_type, get_config_forks, config_conf_cache_size,
			NULL, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize configuration cache: %s", error);
		zbx_free(error);
		return FAIL;
	}

	zbx_vps_monitor_init(config_vps_limit, config_vps_overcommit_limit);

	if (0 != config_forks[ZBX_PROCESS_TYPE_VMWARE] && SUCCEED != zbx_vmware_init(&config_vmware_cache_size, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize VMware cache: %s", error);
		zbx_free(error);
		return FAIL;
	}

	if (SUCCEED != zbx_vc_init(config_value_cache_size, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize history value cache: %s", error);
		zbx_free(error);
		return FAIL;
	}

	if (SUCCEED != zbx_tfc_init(config_trend_func_cache_size, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize trends read cache: %s", error);
		zbx_free(error);
		return FAIL;
	}

	if (0 != config_forks[ZBX_PROCESS_TYPE_CONNECTORMANAGER])
		zbx_connector_init();

	if (0 != config_forks[ZBX_PROCESS_TYPE_DISCOVERYMANAGER])
		zbx_discoverer_init();

	if (0 != config_forks[ZBX_PROCESS_TYPE_TRAPPER])
	{
		exit_args->listen_sock = listen_sock;
		zbx_block_signals(&orig_mask);

		if (FAIL == zbx_tcp_listen(listen_sock, zbx_config_listen_ip, (unsigned short)zbx_config_listen_port,
				zbx_config_timeout, config_tcp_max_backlog_size))
		{
			zabbix_log(LOG_LEVEL_CRIT, "listener failed: %s", zbx_socket_strerror());
			return FAIL;
		}

		if (SUCCEED != zbx_init_remote_commands_cache(&error))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot initialize commands cache: %s", error);
			zbx_free(error);
			return FAIL;
		}
		zbx_unblock_signals(&orig_mask);
	}

	for (zbx_threads_num = 0, i = 0; i < ZBX_PROCESS_TYPE_COUNT; i++)
	{
		/* skip HA manager that is started separately and threaded components */
		switch (i)
		{
			case ZBX_PROCESS_TYPE_PREPROCESSOR:
			case ZBX_PROCESS_TYPE_DISCOVERER:
			case ZBX_PROCESS_TYPE_HA_MANAGER:
				continue;
		}

		zbx_threads_num += config_forks[i];
	}

	zbx_threads = (pid_t *)zbx_calloc(zbx_threads, (size_t)zbx_threads_num, sizeof(pid_t));
	threads_flags = (int *)zbx_calloc(threads_flags, (size_t)zbx_threads_num, sizeof(int));

	zabbix_log(LOG_LEVEL_INFORMATION, "server #0 started [main process]");

	zbx_set_exit_on_terminate();

	thread_args.info.program_type = zbx_program_type;

	zbx_set_child_pids(zbx_threads, zbx_threads_num);

	for (i = 0; i < zbx_threads_num; i++)
	{
		if (FAIL == get_process_info_by_thread(i + 1, &thread_args.info.process_type,
				&thread_args.info.process_num))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
		}

		thread_args.info.server_num = i + 1;
		thread_args.args = NULL;

		switch (thread_args.info.process_type)
		{
			case ZBX_PROCESS_TYPE_SERVICEMAN:
				threads_flags[i] = ZBX_THREAD_PRIORITY_SECOND;
				thread_args.args = &service_manager_args;
				zbx_thread_start(service_manager_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_CONFSYNCER:
				zbx_vc_enable();
				thread_args.args = &dbconfig_args;
				zbx_thread_start(dbconfig_thread, &thread_args, &zbx_threads[i]);

				/* wait for service manager startup */
				if (FAIL == (ret = zbx_rtc_wait_for_sync_finish(rtc, rtc_process_request_ex_server)))
					goto out;

				/* wait for configuration sync */
				if (FAIL == (ret = zbx_rtc_wait_for_sync_finish(rtc, rtc_process_request_ex_server)))
					goto out;

				if (SUCCEED != (ret = zbx_ha_get_status(CONFIG_HA_NODE_NAME, ha_stat, ha_failover,
						&error)))
				{
					zabbix_log(LOG_LEVEL_CRIT, "cannot obtain HA status: %s", error);
					zbx_free(error);
					goto out;
				}

				if (ZBX_NODE_STATUS_ACTIVE != *ha_stat)
					goto out;

				zbx_db_connect(ZBX_DB_CONNECT_NORMAL);

				if (SUCCEED != zbx_check_postinit_tasks(&error))
				{
					zabbix_log(LOG_LEVEL_CRIT, "cannot complete post initialization tasks: %s",
							error);
					zbx_free(error);
					zbx_db_close();

					ret = FAIL;
					goto out;
				}

				/* update maintenance states */
				zbx_dc_update_maintenances(MAINTENANCE_TIMER_PENDING);

				zbx_db_close();
				break;
			case ZBX_PROCESS_TYPE_POLLER:
				poller_args.poller_type = ZBX_POLLER_TYPE_NORMAL;
				thread_args.args = &poller_args;
				zbx_thread_start(zbx_poller_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_UNREACHABLE:
				poller_args.poller_type = ZBX_POLLER_TYPE_UNREACHABLE;
				thread_args.args = &poller_args;
				zbx_thread_start(zbx_poller_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_TRAPPER:
				thread_args.args = &trapper_args;
				zbx_thread_start(zbx_trapper_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_PINGER:
				thread_args.args = &pinger_args;
				zbx_thread_start(zbx_pinger_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_ALERTER:
				thread_args.args = &alerter_args;
				zbx_thread_start(zbx_alerter_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_HOUSEKEEPER:
				thread_args.args = &housekeeper_args;
				zbx_thread_start(housekeeper_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_TIMER:
				thread_args.args = &timer_args;
				zbx_thread_start(timer_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_HTTPPOLLER:
				thread_args.args = &httppoller_args;
				zbx_thread_start(zbx_httppoller_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_DISCOVERYMANAGER:
				threads_flags[i] = ZBX_THREAD_PRIORITY_FIRST;
				thread_args.args = &discoverer_args;
				zbx_thread_start(zbx_discoverer_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_HISTSYNCER:
				threads_flags[i] = ZBX_THREAD_PRIORITY_FIRST;
				thread_args.args = &dbsyncer_args;
				zbx_thread_start(zbx_dbsyncer_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_ESCALATOR:
				thread_args.args = &escalator_args;
				zbx_thread_start(escalator_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_JAVAPOLLER:
				poller_args.poller_type = ZBX_POLLER_TYPE_JAVA;
				thread_args.args = &poller_args;
				zbx_thread_start(zbx_poller_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_SNMPTRAPPER:
				thread_args.args = &snmptrapper_args;
				zbx_thread_start(zbx_snmptrapper_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_PROXYPOLLER:
				thread_args.args = &proxy_poller_args;
				zbx_thread_start(proxypoller_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_SELFMON:
				zbx_thread_start(zbx_selfmon_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_VMWARE:
				thread_args.args = &vmware_args;
				zbx_thread_start(zbx_vmware_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_TASKMANAGER:
				thread_args.args = &taskmanager_args;
				zbx_thread_start(taskmanager_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_PREPROCMAN:
				threads_flags[i] = ZBX_THREAD_PRIORITY_FIRST;
				thread_args.args = &preproc_man_args;
				zbx_thread_start(zbx_pp_manager_thread, &thread_args, &zbx_threads[i]);
				break;
#ifdef HAVE_OPENIPMI
			case ZBX_PROCESS_TYPE_IPMIMANAGER:
				thread_args.args = &ipmi_manager_args;
				zbx_thread_start(zbx_ipmi_manager_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_IPMIPOLLER:
				zbx_thread_start(zbx_ipmi_poller_thread, &thread_args, &zbx_threads[i]);
				break;
#endif
			case ZBX_PROCESS_TYPE_ALERTMANAGER:
				thread_args.args = &alert_manager_args;
				zbx_thread_start(zbx_alert_manager_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_LLDMANAGER:
				thread_args.args = &lld_manager_args;
				zbx_thread_start(lld_manager_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_LLDWORKER:
				zbx_thread_start(lld_worker_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_ALERTSYNCER:
				thread_args.args = &alert_syncer_args;
				zbx_thread_start(zbx_alert_syncer_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_HISTORYPOLLER:
				poller_args.poller_type = ZBX_POLLER_TYPE_HISTORY;
				thread_args.args = &poller_args;
				zbx_thread_start(zbx_poller_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_AVAILMAN:
				threads_flags[i] = ZBX_THREAD_PRIORITY_FIRST;
				zbx_thread_start(zbx_availability_manager_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_CONNECTORMANAGER:
				threads_flags[i] = ZBX_THREAD_PRIORITY_SECOND;
				thread_args.args = &connector_manager_args;
				zbx_thread_start(connector_manager_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_CONNECTORWORKER:
				thread_args.args = &connector_worker_args;
				zbx_thread_start(connector_worker_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_DBCONFIGWORKER:
				threads_flags[i] = ZBX_THREAD_PRIORITY_SECOND;
				zbx_thread_start(zbx_dbconfig_worker_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_REPORTMANAGER:
				thread_args.args = &report_manager_args;
				zbx_thread_start(report_manager_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_REPORTWRITER:
				thread_args.args = &report_writer_args;
				zbx_thread_start(report_writer_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_TRIGGERHOUSEKEEPER:
				thread_args.args = &trigger_housekeeper_args;
				zbx_thread_start(trigger_housekeeper_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_ODBCPOLLER:
				poller_args.poller_type = ZBX_POLLER_TYPE_ODBC;
				thread_args.args = &poller_args;
				zbx_thread_start(zbx_poller_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_HTTPAGENT_POLLER:
				poller_args.poller_type = ZBX_POLLER_TYPE_HTTPAGENT;
				thread_args.args = &poller_args;
				zbx_thread_start(zbx_async_poller_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_AGENT_POLLER:
				poller_args.poller_type = ZBX_POLLER_TYPE_AGENT;
				thread_args.args = &poller_args;
				zbx_thread_start(zbx_async_poller_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_SNMP_POLLER:
				poller_args.poller_type = ZBX_POLLER_TYPE_SNMP;
				thread_args.args = &poller_args;
				zbx_thread_start(zbx_async_poller_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_INTERNAL_POLLER:
				poller_args.poller_type = ZBX_POLLER_TYPE_INTERNAL;
				thread_args.args = &poller_args;
				zbx_thread_start(zbx_poller_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_PG_MANAGER:
				poller_args.poller_type = ZBX_PROCESS_TYPE_PG_MANAGER;
				thread_args.args = &poller_args;
				zbx_thread_start(pg_manager_thread, &thread_args, &zbx_threads[i]);
				break;
			case ZBX_PROCESS_TYPE_BROWSERPOLLER:
				poller_args.poller_type = ZBX_POLLER_TYPE_BROWSER;
				thread_args.args = &poller_args;
				zbx_thread_start(zbx_poller_thread, &thread_args, &zbx_threads[i]);
				break;
		}
	}

	/* startup/postinit tasks can take a long time, update status */
	if (SUCCEED != (ret = zbx_ha_get_status(CONFIG_HA_NODE_NAME, ha_stat, ha_failover, &error)))
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
	zbx_close_log();
	zbx_locks_destroy();

	if (SUCCEED != zbx_locks_create(error))
		return FAIL;

	if (SUCCEED != zbx_open_log(&log_file_cfg, config_log_level, syslog_app_name, NULL, error))
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
	int		i;
	char		*error = NULL;
	zbx_ha_config_t	*ha_config = zbx_malloc(NULL, sizeof(zbx_ha_config_t));

	/* hard kill all zabbix processes, no logging or other  */

	zbx_unset_child_signal_handler();

	rtc_reset(rtc);

#ifdef HAVE_PTHREAD_PROCESS_SHARED
	/* Disable locks so main process doesn't hang on logging if a process was              */
	/* killed during logging. The locks will be re-enabled after logger is reinitialized   */
	zbx_locks_disable();
#endif
	zbx_ha_kill();

	for (i = 0; i < zbx_threads_num; i++)
	{
		if (!zbx_threads[i])
			continue;

		kill(zbx_threads[i], SIGKILL);
	}

	for (i = 0; i < zbx_threads_num; i++)
	{
		if (!zbx_threads[i])
			continue;

		zbx_thread_wait(zbx_threads[i]);
	}

	zbx_set_child_pids(NULL, 0);
	zbx_free(zbx_threads);
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
	zbx_free_configuration_cache();
	zbx_free_database_cache(ZBX_SYNC_NONE, &events_cbs, config_history_storage_pipelines);
	zbx_deinit_remote_commands_cache();
#ifdef HAVE_PTHREAD_PROCESS_SHARED
	zbx_locks_enable();
#endif
	ha_config->ha_node_name =	CONFIG_HA_NODE_NAME;
	ha_config->ha_node_address =	CONFIG_NODE_ADDRESS;
	ha_config->default_node_ip =	zbx_config_listen_ip;
	ha_config->default_node_port =	zbx_config_listen_port;
	ha_config->ha_status =		ZBX_NODE_STATUS_STANDBY;

	if (SUCCEED != zbx_ha_start(rtc, ha_config, &error))
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
	char		*error = NULL;
	zbx_ha_config_t	*ha_config = zbx_malloc(NULL, sizeof(zbx_ha_config_t));

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

	ha_config->ha_node_name =	CONFIG_HA_NODE_NAME;
	ha_config->ha_node_address =	CONFIG_NODE_ADDRESS;
	ha_config->default_node_ip =	zbx_config_listen_ip;
	ha_config->default_node_port =	zbx_config_listen_port;
	ha_config->ha_status =		ZBX_NODE_STATUS_STANDBY;

	if (SUCCEED != zbx_ha_start(rtc, ha_config, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start HA manager: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	ha_status = ZBX_NODE_STATUS_STANDBY;
}

int	MAIN_ZABBIX_ENTRY(int flags)
{
	char	*error = NULL, *smtp_auth_feature_status = NULL;
	int	i, db_type, ha_status_old;
	pid_t	pid;

	zbx_socket_t		listen_sock = {0};
	time_t			standby_warning_time;
	zbx_rtc_t		rtc;
	zbx_timespec_t		rtc_timeout = {1, 0};
	zbx_ha_config_t		*ha_config = zbx_malloc(NULL, sizeof(zbx_ha_config_t));
	zbx_on_exit_args_t	exit_args = {.rtc = NULL, .listen_sock = NULL};

	if (0 != (flags & ZBX_TASK_FLAG_FOREGROUND))
	{
		printf("Starting Zabbix Server. Zabbix %s (revision %s).\nPress Ctrl+C to exit.\n\n",
				ZABBIX_VERSION, ZABBIX_REVISION);
	}

	zbx_block_signals(&orig_mask);

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

	if (SUCCEED != zbx_open_log(&log_file_cfg, config_log_level, syslog_app_name, NULL, &error))
	{
		zbx_error("cannot open log: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	zbx_init_library_ha();

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
#ifdef HAVE_LIBCURL
	if (SUCCEED == zbx_curl_has_smtp_auth(NULL))
		smtp_auth_feature_status = zbx_strdup(smtp_auth_feature_status, "YES");
	else
		smtp_auth_feature_status = zbx_strdup(smtp_auth_feature_status, " NO");
#else
	smtp_auth_feature_status = zbx_strdup(smtp_auth_feature_status, " NO");
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
	zabbix_log(LOG_LEVEL_INFORMATION, "SMTP authentication:       %s", smtp_auth_feature_status);
	zabbix_log(LOG_LEVEL_INFORMATION, "ODBC:                      " ODBC_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "SSH support:               " SSH_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "IPv6 support:              " IPV6_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "TLS support:               " TLS_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "******************************");

	zbx_free(smtp_auth_feature_status);

	zabbix_log(LOG_LEVEL_INFORMATION, "using configuration file: %s", config_file);

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	if (SUCCEED != zbx_coredump_disable())
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot disable core dump, exiting...");
		exit(EXIT_FAILURE);
	}
#endif
	zbx_initialize_events();

	if (FAIL == zbx_load_modules(CONFIG_LOAD_MODULE_PATH, CONFIG_LOAD_MODULE, zbx_config_timeout, 1))
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

	exit_args.rtc = &rtc;
	zbx_set_on_exit_args(&exit_args);

	if (SUCCEED != zbx_vault_token_from_env_get(&(zbx_config_vault.token), &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize vault token: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	if (SUCCEED != zbx_vault_init(&zbx_config_vault, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize vault: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	zbx_unblock_signals(&orig_mask);

	if (SUCCEED != zbx_vault_db_credentials_get(&zbx_config_vault, &zbx_db_config->dbuser,
			&zbx_db_config->dbpassword, zbx_config_source_ip, config_ssl_ca_location,
			config_ssl_cert_location, config_ssl_key_location, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize database credentials from vault: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	if (SUCCEED != zbx_db_init(&error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize database: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}
	zbx_db_connect(ZBX_DB_CONNECT_NORMAL);

	if (ZBX_DB_UNKNOWN == (db_type = zbx_db_get_database_type()))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot use database \"%s\": database is not a Zabbix database",
				zbx_db_config->dbname);
		goto out;
	}
	else if (ZBX_DB_SERVER != db_type)
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot use database \"%s\": its \"users\" table is empty (is this the"
				" Zabbix proxy database?)", zbx_db_config->dbname);
		goto out;
	}

	if (SUCCEED != zbx_init_database_cache(get_zbx_program_type, zbx_sync_server_history, config_history_cache_size,
			config_history_index_cache_size, &config_trends_cache_size, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize database cache: %s", error);
		zbx_free(error);
		goto out;
	}

	zbx_db_check_character_set();
	if (SUCCEED != zbx_check_db())
		goto out;

	if (1 == config_allow_software_update_check)
	{
		if (SUCCEED != zbx_db_update_software_update_checkid())
			goto out;
	}

	zbx_db_save_server_status();

	if (SUCCEED != zbx_db_check_instanceid())
		goto out;

	zbx_db_close();

	if (FAIL == zbx_init_library_export(&zbx_config_export, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize export: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	if (SUCCEED != zbx_history_init(config_history_storage_url, config_history_storage_opts,
			zbx_db_config->log_slow_queries, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize history storage: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	if (SUCCEED != zbx_init_selfmon_collector(get_config_forks, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize self-monitoring: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	zbx_unset_exit_on_terminate();

	ha_config->ha_node_name =	CONFIG_HA_NODE_NAME;
	ha_config->ha_node_address =	CONFIG_NODE_ADDRESS;
	ha_config->default_node_ip =	zbx_config_listen_ip;
	ha_config->default_node_port =	zbx_config_listen_port;
	ha_config->ha_status =		ZBX_NODE_STATUS_UNKNOWN;

	if (SUCCEED != zbx_ha_start(&rtc, ha_config, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start HA manager: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	if (SUCCEED == zbx_is_export_enabled(ZBX_FLAG_EXPTYPE_EVENTS))
		problems_export = zbx_problems_export_init(get_problems_export, "main-process", 0);

	if (SUCCEED == zbx_is_export_enabled(ZBX_FLAG_EXPTYPE_HISTORY))
		history_export = zbx_history_export_init(get_history_export, "main-process", 0);

	if (SUCCEED == zbx_is_export_enabled(ZBX_FLAG_EXPTYPE_TRENDS))
		trends_export = zbx_trends_export_init(get_trends_export, "main-process", 0);

	if (SUCCEED != zbx_ha_get_status(CONFIG_HA_NODE_NAME, &ha_status, &ha_failover_delay, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start server: %s", error);
		zbx_free(error);
		zbx_set_exiting_with_fail();
	}

	zbx_register_stats_data_func(zbx_preproc_stats_ext_get, NULL);
	zbx_register_stats_data_func(zbx_discovery_stats_ext_get, NULL);
	zbx_register_stats_data_func(zbx_server_stats_ext_get, NULL);
	zbx_register_stats_ext_func(zbx_vmware_stats_ext_get, NULL);
	zbx_register_stats_procinfo_func(ZBX_PROCESS_TYPE_PREPROCESSOR, zbx_preprocessor_get_worker_info);
	zbx_register_stats_procinfo_func(ZBX_PROCESS_TYPE_DISCOVERER, zbx_discovery_get_worker_info);
	zbx_diag_init(diag_add_section_info);

	if (ZBX_NODE_STATUS_ACTIVE == ha_status)
	{
		if (SUCCEED != server_startup(&listen_sock, &ha_status, &ha_failover_delay, &rtc, &exit_args))
		{
			zbx_set_exiting_with_fail();
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
		if (NULL != CONFIG_HA_NODE_NAME && '\0' != *CONFIG_HA_NODE_NAME)
		{
			zabbix_log(LOG_LEVEL_INFORMATION, "\"%s\" node started in \"%s\" mode", CONFIG_HA_NODE_NAME,
					zbx_ha_status_str(ha_status));
		}
	}

	ha_status_old = ha_status;

	if (ZBX_NODE_STATUS_ACTIVE == ha_status)
	{
		/* reset ha dispatcher heartbeat timings */
		zbx_ha_dispatch_message(CONFIG_HA_NODE_NAME, NULL, ZBX_HA_RTC_STATE_RESET, NULL, NULL, NULL);
	}
	else if (ZBX_NODE_STATUS_STANDBY == ha_status)
		standby_warning_time = time(NULL);

	while (ZBX_IS_RUNNING())
	{
		time_t			now;
		zbx_ipc_client_t	*client;
		zbx_ipc_message_t	*message;
		int			rtc_state;

		rtc_state = zbx_ipc_service_recv(&rtc.service, &rtc_timeout, &client, &message);

		if (NULL == message || ZBX_IPC_SERVICE_HA_RTC_FIRST <= message->code)
		{
			if (SUCCEED != zbx_ha_dispatch_message(CONFIG_HA_NODE_NAME, message, rtc_state, &ha_status,
					&ha_failover_delay, &error))
			{
				zabbix_log(LOG_LEVEL_CRIT, "HA manager error: %s", error);
				zbx_set_exiting_with_fail();
			}
		}
		else
		{
			if (ZBX_NODE_STATUS_ACTIVE == ha_status || ZBX_RTC_LOG_LEVEL_DECREASE == message->code ||
					ZBX_RTC_LOG_LEVEL_INCREASE == message->code)
			{
				zbx_rtc_dispatch(&rtc, client, message, rtc_process_request_ex_server);
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
					if (SUCCEED != server_startup(&listen_sock, &ha_status, &ha_failover_delay, &rtc, &exit_args))
					{
						zbx_set_exiting_with_fail();
						ha_status = ZBX_NODE_STATUS_ERROR;
						continue;
					}

					if (ZBX_NODE_STATUS_ACTIVE != ha_status)
					{
						server_teardown(&rtc, &listen_sock);
						ha_status_old = ha_status;
					}
					else
					{
						/* reset ha dispatcher heartbeat timings */
						zbx_ha_dispatch_message(CONFIG_HA_NODE_NAME, NULL,
								ZBX_HA_RTC_STATE_RESET, NULL, NULL, NULL);
					}

					break;
				case ZBX_NODE_STATUS_STANDBY:
					server_teardown(&rtc, &listen_sock);
					standby_warning_time = now;
					break;
				default:
					zabbix_log(LOG_LEVEL_CRIT, "unsupported status %d received from HA manager",
							ha_status);
					zbx_set_exiting_with_fail();
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

		if (0 < (pid = waitpid((pid_t)-1, &i, WNOHANG)))
		{
			if (SUCCEED == zbx_is_child_pid(pid, zbx_threads, zbx_threads_num))
			{
				zbx_set_exiting_with_fail();
				break;
			}
			else
				zabbix_log(LOG_LEVEL_TRACE, "indirect child process exited");
		}

		if (-1 == pid && EINTR != errno)
		{
			zabbix_log(LOG_LEVEL_ERR, "failed to wait on child processes: %s", zbx_strerror(errno));
			zbx_set_exiting_with_fail();
			break;
		}

		zbx_vault_renew_token(&zbx_config_vault, zbx_config_source_ip, config_ssl_ca_location,
				config_ssl_cert_location, config_ssl_key_location);
	}

	zbx_log_exit_signal();

	if (SUCCEED == ZBX_EXIT_STATUS())
		zbx_rtc_shutdown_subs(&rtc);

	if (SUCCEED != zbx_ha_pause(&error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot pause HA manager: %s", error);
		zbx_free(error);
	}

	zbx_db_version_info_clear(&db_version_info);

	zbx_on_exit(ZBX_EXIT_STATUS(), &exit_args);

	return SUCCEED;
out:
	zbx_db_close();
	exit(EXIT_FAILURE);
}
