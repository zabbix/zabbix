/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#include "zbxdbwrap.h"

#include "cfg.h"
#include "zbxdbhigh.h"
#include "zbxcacheconfig.h"
#include "zbxcachehistory.h"
#include "zbxcachehistory_proxy.h"
#include "zbxdbupgrade.h"
#include "zbxlog.h"
#include "zbxgetopt.h"
#include "zbxmutexs.h"

#include "zbxsysinfo.h"
#include "zbxmodules.h"

#include "zbxnix.h"
#include "zbxself.h"

#include "zbxdbsyncer.h"
#include "../zabbix_server/discoverer/discoverer.h"
#include "../zabbix_server/httppoller/httppoller.h"
#include "housekeeper/housekeeper.h"
#include "../zabbix_server/pinger/pinger.h"
#include "../zabbix_server/poller/poller.h"
#include "../zabbix_server/trapper/trapper.h"
#include "../zabbix_server/trapper/trapper_request.h"
#include "proxyconfig/proxyconfig.h"
#include "datasender/datasender.h"
#include "taskmanager/taskmanager_proxy.h"
#include "../zabbix_server/vmware/vmware.h"
#include "zbxcomms.h"
#include "zbxvault.h"
#include "zbxdiag.h"
#include "diag/diag_proxy.h"
#include "zbxrtc.h"
#include "rtc/rtc_proxy.h"
#include "zbxstats.h"
#include "stats/zabbix_stats.h"
#include "zbxip.h"
#include "zbxthreads.h"
#include "zbx_rtc_constants.h"
#include "zbxicmpping.h"
#include "zbxipcservice.h"
#include "preproc/preproc_proxy.h"
#include "zbxdiscovery.h"
#include "zbxproxybuffer.h"
#include "zbxscripts.h"
#include "zbxsnmptrapper.h"

#ifdef HAVE_OPENIPMI
#include "zbxipmi.h"
#endif

const char	*progname = NULL;
const char	title_message[] = "zabbix_proxy";
const char	syslog_app_name[] = "zabbix_proxy";
const char	*usage_message[] = {
	"[-c config-file]", NULL,
	"[-c config-file]", "-R runtime-option", NULL,
	"-h", NULL,
	"-V", NULL,
	NULL	/* end of text */
};

const char	*help_message[] = {
	"A Zabbix daemon that collects monitoring data from devices and sends it to",
	"Zabbix server.",
	"",
	"Options:",
	"  -c --config config-file        Path to the configuration file",
	"                                 (default: \"" DEFAULT_CONFIG_FILE "\")",
	"  -f --foreground                Run Zabbix proxy in foreground",
	"  -R --runtime-control runtime-option   Perform administrative functions",
	"",
	"    Runtime control options:",
	"      " ZBX_CONFIG_CACHE_RELOAD "        Reload configuration cache",
	"      " ZBX_HOUSEKEEPER_EXECUTE "        Execute the housekeeper",
	"      " ZBX_LOG_LEVEL_INCREASE "=target  Increase log level, affects all processes if",
	"                                   target is not specified",
	"      " ZBX_LOG_LEVEL_DECREASE "=target  Decrease log level, affects all processes if",
	"                                   target is not specified",
	"      " ZBX_SNMP_CACHE_RELOAD "          Reload SNMP cache",
	"      " ZBX_DIAGINFO "=section           Log internal diagnostic information of the",
	"                                 section (historycache, preprocessing, locks) or",
	"                                 everything if section is not specified",
	"      " ZBX_PROF_ENABLE "=target         Enable profiling, affects all processes if",
	"                                   target is not specified",
	"      " ZBX_PROF_DISABLE "=target        Disable profiling, affects all processes if",
	"                                   target is not specified",
	"",
	"      Log level control targets:",
	"        process-type             All processes of specified type",
	"                                 (availability manager, configuration syncer, data sender,",
	"                                 discovery manager, history syncer, housekeeper, http poller,",
	"                                 icmp pinger, ipmi manager, ipmi poller, java poller,",
	"                                 odbc poller, poller, agent poller, http agent poller,",
	"                                 snmp poller, preprocessing manager, self-monitoring, snmp trapper,",
	"                                 task manager, trapper, unreachable poller, vmware collector)",
	"        process-type,N           Process type and number (e.g., poller,3)",
	"        pid                      Process identifier",
	"",
	"      Profiling control targets:",
	"        process-type             All processes of specified type",
	"                                 (availability manager, configuration syncer, data sender,",
	"                                 discovery manager, history syncer, housekeeper, http poller,",
	"                                 icmp pinger, ipmi manager, ipmi poller, java poller,",
	"                                 odbc poller, poller, agent poller, http agent poller,",
	"                                 snmp poller, preprocessing manager, self-monitoring, snmp trapper,",
	"                                 task manager, trapper, unreachable poller, vmware collector)",
	"        process-type,N           Process type and number (e.g., history syncer,1)",
	"        pid                      Process identifier",
	"        scope                    Profiling scope",
	"                                 (rwlock, mutex, processing) can be used with process-type",
	"                                 (e.g., history syncer,1,processing)",
	"",
	"  -h --help                      Display this help message",
	"  -V --version                   Display version number",
	"",
	"Some configuration parameter default locations:",
	"  ExternalScripts                \"" DEFAULT_EXTERNAL_SCRIPTS_PATH "\"",
#ifdef HAVE_LIBCURL
	"  SSLCertLocation                \"" DEFAULT_SSL_CERT_LOCATION "\"",
	"  SSLKeyLocation                 \"" DEFAULT_SSL_KEY_LOCATION "\"",
#endif
	"  LoadModulePath                 \"" DEFAULT_LOAD_MODULE_PATH "\"",
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

unsigned char		program_type	= ZBX_PROGRAM_TYPE_PROXY_ACTIVE;
static unsigned char	get_program_type(void)
{
	return program_type;
}

ZBX_GET_CONFIG_VAR2(char *, const char *, zbx_config_source_ip, NULL)

ZBX_GET_CONFIG_VAR2(char *, const char *, zbx_config_pid_file, NULL)
ZBX_GET_CONFIG_VAR2(char *, const char *, zbx_config_tmpdir, NULL)
ZBX_GET_CONFIG_VAR2(char *, const char *, zbx_config_fping_location, NULL)
ZBX_GET_CONFIG_VAR2(char *, const char *, zbx_config_fping6_location, NULL)

static int	config_proxymode		= ZBX_PROXYMODE_ACTIVE;

int	CONFIG_FORKS[ZBX_PROCESS_TYPE_COUNT] = {
	5, /* ZBX_PROCESS_TYPE_POLLER */
	1, /* ZBX_PROCESS_TYPE_UNREACHABLE */
	0, /* ZBX_PROCESS_TYPE_IPMIPOLLER */
	1, /* ZBX_PROCESS_TYPE_PINGER */
	0, /* ZBX_PROCESS_TYPE_JAVAPOLLER */
	1, /* ZBX_PROCESS_TYPE_HTTPPOLLER */
	5, /* ZBX_PROCESS_TYPE_TRAPPER */
	0, /* ZBX_PROCESS_TYPE_SNMPTRAPPER */
	0, /* ZBX_PROCESS_TYPE_PROXYPOLLER */
	0, /* ZBX_PROCESS_TYPE_ESCALATOR */
	4, /* ZBX_PROCESS_TYPE_HISTSYNCER */
	5, /* ZBX_PROCESS_TYPE_DISCOVERER */
	0, /* ZBX_PROCESS_TYPE_ALERTER */
	0, /* ZBX_PROCESS_TYPE_TIMER */
	1, /* ZBX_PROCESS_TYPE_HOUSEKEEPER */
	1, /* ZBX_PROCESS_TYPE_DATASENDER */
	1, /* ZBX_PROCESS_TYPE_CONFSYNCER */
	1, /* ZBX_PROCESS_TYPE_SELFMON */
	0, /* ZBX_PROCESS_TYPE_VMWARE */
	0, /* ZBX_PROCESS_TYPE_COLLECTOR */
	0, /* ZBX_PROCESS_TYPE_LISTENER */
	0, /* ZBX_PROCESS_TYPE_ACTIVE_CHECKS */
	1, /* ZBX_PROCESS_TYPE_TASKMANAGER */
	0, /* ZBX_PROCESS_TYPE_IPMIMANAGER */
	0, /* ZBX_PROCESS_TYPE_ALERTMANAGER */
	1, /* ZBX_PROCESS_TYPE_PREPROCMAN */
	3, /* ZBX_PROCESS_TYPE_PREPROCESSOR */
	0, /* ZBX_PROCESS_TYPE_LLDMANAGER */
	0, /* ZBX_PROCESS_TYPE_LLDWORKER */
	0, /* ZBX_PROCESS_TYPE_ALERTSYNCER */
	0, /* ZBX_PROCESS_TYPE_HISTORYPOLLER */
	1, /* ZBX_PROCESS_TYPE_AVAILMAN */
	0, /* ZBX_PROCESS_TYPE_REPORTMANAGER */
	0, /* ZBX_PROCESS_TYPE_REPORTWRITER */
	0, /* ZBX_PROCESS_TYPE_SERVICEMAN */
	0, /* ZBX_PROCESS_TYPE_TRIGGERHOUSEKEEPER */
	1, /* ZBX_PROCESS_TYPE_ODBCPOLLER */
	0, /* ZBX_PROCESS_TYPE_CONNECTORMANAGER */
	0, /* ZBX_PROCESS_TYPE_CONNECTORWORKER */
	0, /* ZBX_PROCESS_TYPE_DISCOVERYMANAGER */
	1, /* ZBX_PROCESS_TYPE_HTTPAGENT_POLLER */
	1, /* ZBX_PROCESS_TYPE_AGENT_POLLER */
	1, /* ZBX_PROCESS_TYPE_SNMP_POLLER */
	1 /* ZBX_PROCESS_TYPE_INTERNAL_POLLER */
};

static int	get_config_forks(unsigned char process_type)
{
	if (ZBX_PROCESS_TYPE_COUNT > process_type)
		return CONFIG_FORKS[process_type];

	return 0;
}

ZBX_GET_CONFIG_VAR(int, zbx_config_timeout, 3)
static int	zbx_config_trapper_timeout	= 300;
static int	config_startup_time		= 0;
static int	config_unavailable_delay	= 60;
static int	config_housekeeping_frequency	= 1;
static int	config_proxy_local_buffer	= 0;
static int	config_proxy_offline_buffer	= 1;
static int	config_histsyncer_frequency	= 1;

static int	config_listen_port		= ZBX_DEFAULT_SERVER_PORT;
static char	*config_listen_ip		= NULL;

static int	config_heartbeat_frequency	= -1;

/* how often active Zabbix proxy requests configuration data from server, in seconds */
static int	config_proxyconfig_frequency	= 0;	/* will be set to default 5 seconds if not configured */
static int	config_proxydata_frequency	= 1;
static int	config_confsyncer_frequency	= 0;

static int	config_vmware_frequency		= 60;
static int	config_vmware_perf_frequency	= 60;
static int	config_vmware_timeout		= 10;

static zbx_uint64_t	config_conf_cache_size		= 8 * ZBX_MEBIBYTE;
static zbx_uint64_t	config_history_cache_size	= 16 * ZBX_MEBIBYTE;
static zbx_uint64_t	config_history_index_cache_size	= 4 * ZBX_MEBIBYTE;
static zbx_uint64_t	config_trends_cache_size	= 0;
static zbx_uint64_t	config_vmware_cache_size	= 8 * ZBX_MEBIBYTE;

static int	config_unreachable_period		= 45;
static int	config_unreachable_delay		= 15;
static int	config_max_concurrent_checks_per_poller	= 1000;

static int	config_log_level		= LOG_LEVEL_WARNING;

char	*CONFIG_EXTERNALSCRIPTS		= NULL;
int	CONFIG_ALLOW_UNSUPPORTED_DB_VERSIONS = 0;

ZBX_GET_CONFIG_VAR(int, zbx_config_enable_remote_commands, 0)
ZBX_GET_CONFIG_VAR(int, zbx_config_log_remote_commands, 0)
ZBX_GET_CONFIG_VAR(int, zbx_config_unsafe_user_parameters, 0)

static char	*config_server		= NULL;
static int	config_server_port;
static char	*config_hostname	= NULL;
static char	*config_hostname_item	= NULL;

char	*zbx_config_snmptrap_file	= NULL;

char	*CONFIG_JAVA_GATEWAY		= NULL;
int	CONFIG_JAVA_GATEWAY_PORT	= ZBX_DEFAULT_GATEWAY_PORT;

char	*CONFIG_SSH_KEY_LOCATION	= NULL;

static int	config_log_slow_queries	= 0;	/* ms; 0 - disable */

static char	*config_load_module_path= NULL;
static char	**config_load_module	= NULL;

static char	*config_user		= NULL;

/* web monitoring */
char	*CONFIG_SSL_CA_LOCATION		= NULL;
char	*CONFIG_SSL_CERT_LOCATION	= NULL;
char	*CONFIG_SSL_KEY_LOCATION	= NULL;

static zbx_config_tls_t		*zbx_config_tls = NULL;
static zbx_config_dbhigh_t	*zbx_config_dbhigh = NULL;
static zbx_config_vault_t	zbx_config_vault = {NULL, NULL, NULL, NULL, NULL, NULL};

static char	*config_socket_path	= NULL;

char	*CONFIG_HISTORY_STORAGE_URL		= NULL;
char	*CONFIG_HISTORY_STORAGE_OPTS		= NULL;
int	CONFIG_HISTORY_STORAGE_PIPELINES	= 0;

char	*CONFIG_STATS_ALLOWED_IP	= NULL;
int	CONFIG_TCP_MAX_BACKLOG_SIZE	= SOMAXCONN;

static char	*config_file		= NULL;
static int	config_allow_root	= 0;

static zbx_config_log_t	log_file_cfg = {NULL, NULL, ZBX_LOG_TYPE_UNDEFINED, 1};

static zbx_vector_addr_ptr_t	config_server_addrs;

#define ZBX_CONFIG_DATA_CACHE_SIZE_MIN		(ZBX_KIBIBYTE * 128)
#define ZBX_CONFIG_DATA_CACHE_AGE_MIN		(SEC_PER_MIN * 10)

static char		*config_proxy_buffer_mode_str = NULL;
static int		config_proxy_buffer_mode	= 0;
static zbx_uint64_t	config_proxy_memory_buffer_size	= 0;
static int		config_proxy_memory_buffer_age	= 0;

/* proxy has no any events processing */
static const zbx_events_funcs_t	events_cbs = {
	.add_event_cb			= NULL,
	.process_events_cb		= NULL,
	.clean_events_cb		= NULL,
	.reset_event_recovery_cb	= NULL,
	.export_events_cb		= NULL,
	.events_update_itservices_cb	= NULL
};

int	get_process_info_by_thread(int local_server_num, unsigned char *local_process_type, int *local_process_num);

int	get_process_info_by_thread(int local_server_num, unsigned char *local_process_type, int *local_process_num)
{
	int	server_count = 0;

	if (0 == local_server_num)
	{
		/* fail if the main process is queried */
		return FAIL;
	}
	else if (local_server_num <= (server_count += CONFIG_FORKS[ZBX_PROCESS_TYPE_CONFSYNCER]))
	{
		/* make initial configuration sync before worker processes are forked on active Zabbix proxy */
		*local_process_type = ZBX_PROCESS_TYPE_CONFSYNCER;
		*local_process_num = local_server_num - server_count + CONFIG_FORKS[ZBX_PROCESS_TYPE_CONFSYNCER];
	}
	else if (local_server_num <= (server_count += CONFIG_FORKS[ZBX_PROCESS_TYPE_TRAPPER]))
	{
		/* make initial configuration sync before worker processes are forked on passive Zabbix proxy */
		*local_process_type = ZBX_PROCESS_TYPE_TRAPPER;
		*local_process_num = local_server_num - server_count + CONFIG_FORKS[ZBX_PROCESS_TYPE_TRAPPER];
	}
	else if (local_server_num <= (server_count += CONFIG_FORKS[ZBX_PROCESS_TYPE_PREPROCMAN]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_PREPROCMAN;
		*local_process_num = local_server_num - server_count + CONFIG_FORKS[ZBX_PROCESS_TYPE_PREPROCMAN];
	}
	else if (local_server_num <= (server_count += CONFIG_FORKS[ZBX_PROCESS_TYPE_DATASENDER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_DATASENDER;
		*local_process_num = local_server_num - server_count + CONFIG_FORKS[ZBX_PROCESS_TYPE_DATASENDER];
	}
	else if (local_server_num <= (server_count += CONFIG_FORKS[ZBX_PROCESS_TYPE_IPMIMANAGER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_IPMIMANAGER;
		*local_process_num = local_server_num - server_count + CONFIG_FORKS[ZBX_PROCESS_TYPE_IPMIMANAGER];
	}
	else if (local_server_num <= (server_count += CONFIG_FORKS[ZBX_PROCESS_TYPE_HOUSEKEEPER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_HOUSEKEEPER;
		*local_process_num = local_server_num - server_count + CONFIG_FORKS[ZBX_PROCESS_TYPE_HOUSEKEEPER];
	}
	else if (local_server_num <= (server_count += CONFIG_FORKS[ZBX_PROCESS_TYPE_HTTPPOLLER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_HTTPPOLLER;
		*local_process_num = local_server_num - server_count + CONFIG_FORKS[ZBX_PROCESS_TYPE_HTTPPOLLER];
	}
	else if (local_server_num <= (server_count += CONFIG_FORKS[ZBX_PROCESS_TYPE_DISCOVERYMANAGER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_DISCOVERYMANAGER;
		*local_process_num = local_server_num - server_count + CONFIG_FORKS[ZBX_PROCESS_TYPE_DISCOVERYMANAGER];
	}
	else if (local_server_num <= (server_count += CONFIG_FORKS[ZBX_PROCESS_TYPE_HISTSYNCER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_HISTSYNCER;
		*local_process_num = local_server_num - server_count + CONFIG_FORKS[ZBX_PROCESS_TYPE_HISTSYNCER];
	}
	else if (local_server_num <= (server_count += CONFIG_FORKS[ZBX_PROCESS_TYPE_IPMIPOLLER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_IPMIPOLLER;
		*local_process_num = local_server_num - server_count + CONFIG_FORKS[ZBX_PROCESS_TYPE_IPMIPOLLER];
	}
	else if (local_server_num <= (server_count += CONFIG_FORKS[ZBX_PROCESS_TYPE_JAVAPOLLER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_JAVAPOLLER;
		*local_process_num = local_server_num - server_count + CONFIG_FORKS[ZBX_PROCESS_TYPE_JAVAPOLLER];
	}
	else if (local_server_num <= (server_count += CONFIG_FORKS[ZBX_PROCESS_TYPE_SNMPTRAPPER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_SNMPTRAPPER;
		*local_process_num = local_server_num - server_count + CONFIG_FORKS[ZBX_PROCESS_TYPE_SNMPTRAPPER];
	}
	else if (local_server_num <= (server_count += CONFIG_FORKS[ZBX_PROCESS_TYPE_SELFMON]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_SELFMON;
		*local_process_num = local_server_num - server_count + CONFIG_FORKS[ZBX_PROCESS_TYPE_SELFMON];
	}
	else if (local_server_num <= (server_count += CONFIG_FORKS[ZBX_PROCESS_TYPE_VMWARE]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_VMWARE;
		*local_process_num = local_server_num - server_count + CONFIG_FORKS[ZBX_PROCESS_TYPE_VMWARE];
	}
	else if (local_server_num <= (server_count += CONFIG_FORKS[ZBX_PROCESS_TYPE_TASKMANAGER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_TASKMANAGER;
		*local_process_num = local_server_num - server_count + CONFIG_FORKS[ZBX_PROCESS_TYPE_TASKMANAGER];
	}
	else if (local_server_num <= (server_count += CONFIG_FORKS[ZBX_PROCESS_TYPE_POLLER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_POLLER;
		*local_process_num = local_server_num - server_count + CONFIG_FORKS[ZBX_PROCESS_TYPE_POLLER];
	}
	else if (local_server_num <= (server_count += CONFIG_FORKS[ZBX_PROCESS_TYPE_UNREACHABLE]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_UNREACHABLE;
		*local_process_num = local_server_num - server_count + CONFIG_FORKS[ZBX_PROCESS_TYPE_UNREACHABLE];
	}
	else if (local_server_num <= (server_count += CONFIG_FORKS[ZBX_PROCESS_TYPE_PINGER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_PINGER;
		*local_process_num = local_server_num - server_count + CONFIG_FORKS[ZBX_PROCESS_TYPE_PINGER];
	}
	else if (local_server_num <= (server_count += CONFIG_FORKS[ZBX_PROCESS_TYPE_AVAILMAN]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_AVAILMAN;
		*local_process_num = local_server_num - server_count + CONFIG_FORKS[ZBX_PROCESS_TYPE_AVAILMAN];
	}
	else if (local_server_num <= (server_count += CONFIG_FORKS[ZBX_PROCESS_TYPE_ODBCPOLLER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_ODBCPOLLER;
		*local_process_num = local_server_num - server_count + CONFIG_FORKS[ZBX_PROCESS_TYPE_ODBCPOLLER];
	}
	else if (local_server_num <= (server_count += CONFIG_FORKS[ZBX_PROCESS_TYPE_HTTPAGENT_POLLER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_HTTPAGENT_POLLER;
		*local_process_num = local_server_num - server_count + CONFIG_FORKS[ZBX_PROCESS_TYPE_HTTPAGENT_POLLER];
	}
	else if (local_server_num <= (server_count += CONFIG_FORKS[ZBX_PROCESS_TYPE_AGENT_POLLER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_AGENT_POLLER;
		*local_process_num = local_server_num - server_count + CONFIG_FORKS[ZBX_PROCESS_TYPE_AGENT_POLLER];
	}
	else if (local_server_num <= (server_count += CONFIG_FORKS[ZBX_PROCESS_TYPE_SNMP_POLLER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_SNMP_POLLER;
		*local_process_num = local_server_num - server_count + CONFIG_FORKS[ZBX_PROCESS_TYPE_SNMP_POLLER];
	}
	else if (local_server_num <= (server_count += CONFIG_FORKS[ZBX_PROCESS_TYPE_INTERNAL_POLLER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_INTERNAL_POLLER;
		*local_process_num = local_server_num - server_count + CONFIG_FORKS[ZBX_PROCESS_TYPE_INTERNAL_POLLER];
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
	AGENT_RESULT	result;
	char		**value = NULL;

	config_startup_time = time(NULL);

	if (NULL == config_hostname)
	{
		if (NULL == config_hostname_item)
			config_hostname_item = zbx_strdup(config_hostname_item, "system.hostname");

		zbx_init_agent_result(&result);

		if (SUCCEED == zbx_execute_agent_check(config_hostname_item, ZBX_PROCESS_LOCAL_COMMAND, &result,
				ZBX_CHECK_TIMEOUT_UNDEFINED) && NULL != (value = ZBX_GET_STR_RESULT(&result)))
		{
			assert(*value);

			if (ZBX_MAX_HOSTNAME_LEN < strlen(*value))
			{
				(*value)[ZBX_MAX_HOSTNAME_LEN] = '\0';
				zabbix_log(LOG_LEVEL_WARNING, "proxy name truncated to [%s])", *value);
			}

			config_hostname = zbx_strdup(config_hostname, *value);
		}
		else
			zabbix_log(LOG_LEVEL_WARNING, "failed to get proxy name from [%s])", config_hostname_item);

		zbx_free_agent_result(&result);
	}
	else if (NULL != config_hostname_item)
	{
		zabbix_log(LOG_LEVEL_WARNING, "both Hostname and HostnameItem defined, using [%s]", config_hostname);
	}

	if (NULL == zbx_config_dbhigh->config_dbhost)
		zbx_config_dbhigh->config_dbhost = zbx_strdup(zbx_config_dbhigh->config_dbhost, "localhost");

	if (NULL == zbx_config_snmptrap_file)
		zbx_config_snmptrap_file = zbx_strdup(zbx_config_snmptrap_file, "/tmp/zabbix_traps.tmp");

	if (NULL == zbx_config_pid_file)
		zbx_config_pid_file = zbx_strdup(zbx_config_pid_file, "/tmp/zabbix_proxy.pid");

	if (NULL == zbx_config_tmpdir)
		zbx_config_tmpdir = zbx_strdup(zbx_config_tmpdir, "/tmp");

	if (NULL == zbx_config_fping_location)
		zbx_config_fping_location = zbx_strdup(zbx_config_fping_location, "/usr/sbin/fping");
#ifdef HAVE_IPV6
	if (NULL == zbx_config_fping6_location)
		zbx_config_fping6_location = zbx_strdup(zbx_config_fping6_location, "/usr/sbin/fping6");
#endif
	if (NULL == CONFIG_EXTERNALSCRIPTS)
		CONFIG_EXTERNALSCRIPTS = zbx_strdup(CONFIG_EXTERNALSCRIPTS, DEFAULT_EXTERNAL_SCRIPTS_PATH);

	if (NULL == config_load_module_path)
		config_load_module_path = zbx_strdup(config_load_module_path, DEFAULT_LOAD_MODULE_PATH);
#ifdef HAVE_LIBCURL
	if (NULL == CONFIG_SSL_CERT_LOCATION)
		CONFIG_SSL_CERT_LOCATION = zbx_strdup(CONFIG_SSL_CERT_LOCATION, DEFAULT_SSL_CERT_LOCATION);

	if (NULL == CONFIG_SSL_KEY_LOCATION)
		CONFIG_SSL_KEY_LOCATION = zbx_strdup(CONFIG_SSL_KEY_LOCATION, DEFAULT_SSL_KEY_LOCATION);
#endif
	if (ZBX_PROXYMODE_PASSIVE == config_proxymode)
	{
		CONFIG_FORKS[ZBX_PROCESS_TYPE_DATASENDER] = 0;
		program_type = ZBX_PROGRAM_TYPE_PROXY_PASSIVE;
	}

	if (NULL == log_file_cfg.log_type_str)
		log_file_cfg.log_type_str = zbx_strdup(log_file_cfg.log_type_str, ZBX_OPTION_LOGTYPE_FILE);

	if (NULL == config_socket_path)
		config_socket_path = zbx_strdup(config_socket_path, "/tmp");

	if (0 != CONFIG_FORKS[ZBX_PROCESS_TYPE_IPMIPOLLER])
		CONFIG_FORKS[ZBX_PROCESS_TYPE_IPMIMANAGER] = 1;

	if (0 != CONFIG_FORKS[ZBX_PROCESS_TYPE_DISCOVERER])
		CONFIG_FORKS[ZBX_PROCESS_TYPE_DISCOVERYMANAGER] = 1;

	if (NULL == zbx_config_vault.url)
		zbx_config_vault.url = zbx_strdup(zbx_config_vault.url, "https://127.0.0.1:8200");

	if (-1 != config_heartbeat_frequency)
		zabbix_log(LOG_LEVEL_WARNING, "HeartbeatFrequency parameter is deprecated, and has no effect");

	if (0 == config_server_port)
	{
		config_server_port = ZBX_DEFAULT_SERVER_PORT;
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "ServerPort parameter is deprecated,"
					" please specify port in Server parameter separated by ':' instead");
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: validate configuration parameters                                 *
 *                                                                            *
 ******************************************************************************/
static void	zbx_validate_config(ZBX_TASK_EX *task)
{
	char	*ch_error;
	int	err = 0;

	if (NULL == config_hostname)
	{
		zabbix_log(LOG_LEVEL_CRIT, "\"Hostname\" configuration parameter is not defined");
		err = 1;
	}
	else if (FAIL == zbx_check_hostname(config_hostname, &ch_error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "invalid \"Hostname\" configuration parameter '%s': %s", config_hostname,
				ch_error);
		zbx_free(ch_error);
		err = 1;
	}

	if (0 == CONFIG_FORKS[ZBX_PROCESS_TYPE_UNREACHABLE] &&
			0 != CONFIG_FORKS[ZBX_PROCESS_TYPE_POLLER] + CONFIG_FORKS[ZBX_PROCESS_TYPE_JAVAPOLLER])
	{
		zabbix_log(LOG_LEVEL_CRIT, "\"StartPollersUnreachable\" configuration parameter must not be 0"
				" if regular or Java pollers are started");
		err = 1;
	}

	if ((NULL == CONFIG_JAVA_GATEWAY || '\0' == *CONFIG_JAVA_GATEWAY) &&
			0 < CONFIG_FORKS[ZBX_PROCESS_TYPE_JAVAPOLLER])
	{
		zabbix_log(LOG_LEVEL_CRIT, "\"JavaGateway\" configuration parameter is not specified or empty");
		err = 1;
	}

	if (ZBX_PROXYMODE_ACTIVE == config_proxymode)
	{
		if (NULL != strchr(config_server, ','))
		{
			zabbix_log(LOG_LEVEL_CRIT, "\"Server\" configuration parameter must not contain comma");
			err = 1;
		}
	}
	else if (ZBX_PROXYMODE_PASSIVE == config_proxymode && FAIL == zbx_validate_peer_list(config_server,
			&ch_error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "invalid entry in \"Server\" configuration parameter: %s", ch_error);
		zbx_free(ch_error);
		err = 1;
	}

	if (NULL != zbx_config_source_ip && SUCCEED != zbx_is_supported_ip(zbx_config_source_ip))
	{
		zabbix_log(LOG_LEVEL_CRIT, "invalid \"SourceIP\" configuration parameter: '%s'", zbx_config_source_ip);
		err = 1;
	}

	if (NULL != CONFIG_STATS_ALLOWED_IP && FAIL == zbx_validate_peer_list(CONFIG_STATS_ALLOWED_IP, &ch_error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "invalid entry in \"StatsAllowedIP\" configuration parameter: %s", ch_error);
		zbx_free(ch_error);
		err = 1;
	}
#if !defined(HAVE_IPV6)
	err |= (FAIL == check_cfg_feature_str("Fping6Location", zbx_config_fping6_location, "IPv6 support"));
#endif
#if !defined(HAVE_LIBCURL)
	err |= (FAIL == check_cfg_feature_str("SSLCALocation", CONFIG_SSL_CA_LOCATION, "cURL library"));
	err |= (FAIL == check_cfg_feature_str("SSLCertLocation", CONFIG_SSL_CERT_LOCATION, "cURL library"));
	err |= (FAIL == check_cfg_feature_str("SSLKeyLocation", CONFIG_SSL_KEY_LOCATION, "cURL library"));
	err |= (FAIL == check_cfg_feature_str("Vault", zbx_config_vault.name, "cURL library"));
	err |= (FAIL == check_cfg_feature_str("VaultToken", zbx_config_vault.token, "cURL library"));
	err |= (FAIL == check_cfg_feature_str("VaultDBPath", zbx_config_vault.db_path, "cURL library"));
#endif
#if !defined(HAVE_LIBXML2) || !defined(HAVE_LIBCURL)
	err |= (FAIL == check_cfg_feature_int("StartVMwareCollectors", CONFIG_FORKS[ZBX_PROCESS_TYPE_VMWARE],
			"VMware support"));

	/* parameters VMwareFrequency, VMwarePerfFrequency, VMwareCacheSize, VMwareTimeout are not checked here */
	/* because they have non-zero default values */
#endif

	if (SUCCEED != zbx_validate_log_parameters(task, &log_file_cfg))
		err = 1;

#if !(defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL))
	err |= (FAIL == check_cfg_feature_str("TLSConnect", zbx_config_tls->connect, "TLS support"));
	err |= (FAIL == check_cfg_feature_str("TLSAccept", zbx_config_tls->accept, "TLS support"));
	err |= (FAIL == check_cfg_feature_str("TLSCAFile", zbx_config_tls->ca_file, "TLS support"));
	err |= (FAIL == check_cfg_feature_str("TLSCRLFile", zbx_config_tls->crl_file, "TLS support"));
	err |= (FAIL == check_cfg_feature_str("TLSServerCertIssuer", zbx_config_tls->server_cert_issuer,
			"TLS support"));
	err |= (FAIL == check_cfg_feature_str("TLSServerCertSubject", zbx_config_tls->server_cert_subject,
			"TLS support"));
	err |= (FAIL == check_cfg_feature_str("TLSCertFile", zbx_config_tls->cert_file, "TLS support"));
	err |= (FAIL == check_cfg_feature_str("TLSKeyFile", zbx_config_tls->key_file, "TLS support"));
	err |= (FAIL == check_cfg_feature_str("TLSPSKIdentity", zbx_config_tls->psk_identity,
			"TLS support"));
	err |= (FAIL == check_cfg_feature_str("TLSPSKFile", zbx_config_tls->psk_file, "TLS support"));
#endif
#if !(defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL))
	err |= (FAIL == check_cfg_feature_str("TLSCipherCert", zbx_config_tls->cipher_cert,
			"GnuTLS or OpenSSL"));
	err |= (FAIL == check_cfg_feature_str("TLSCipherPSK", zbx_config_tls->cipher_psk,
			"GnuTLS or OpenSSL"));
	err |= (FAIL == check_cfg_feature_str("TLSCipherAll", zbx_config_tls->cipher_all,
			"GnuTLS or OpenSSL"));
#endif
#if !defined(HAVE_OPENSSL)
	err |= (FAIL == check_cfg_feature_str("TLSCipherCert13", zbx_config_tls->cipher_cert13,
			"OpenSSL 1.1.1 or newer"));
	err |= (FAIL == check_cfg_feature_str("TLSCipherPSK13", zbx_config_tls->cipher_psk13,
			"OpenSSL 1.1.1 or newer"));
	err |= (FAIL == check_cfg_feature_str("TLSCipherAll13", zbx_config_tls->cipher_all13,
			"OpenSSL 1.1.1 or newer"));
#endif

#if !defined(HAVE_OPENIPMI)
	err |= (FAIL == check_cfg_feature_int("StartIPMIPollers", CONFIG_FORKS[ZBX_PROCESS_TYPE_IPMIPOLLER],
			"IPMI support"));
#endif
	if (0 != config_confsyncer_frequency)
	{
		if (0 != config_proxyconfig_frequency)
		{
			zabbix_log(LOG_LEVEL_CRIT, "Deprecated \"ConfigFrequency\" configuration parameter cannot"
					" be used together with \"ProxyConfigFrequency\" parameter");
			err = 1;
		}
		else
		{
			config_proxyconfig_frequency = config_confsyncer_frequency;
			zabbix_log(LOG_LEVEL_WARNING, "\"ConfigFrequency\" configuration parameter is deprecated, "
					"use \"ProxyConfigFrequency\" instead");
		}
	}

	/* assign default ProxyConfigFrequency value if not configured */
	if (0 == config_proxyconfig_frequency)
		config_proxyconfig_frequency = 10;

	if (FAIL == zbx_pb_parse_mode(config_proxy_buffer_mode_str, &config_proxy_buffer_mode))
	{
		zabbix_log(LOG_LEVEL_CRIT, "Invalid \"ProxyBufferMode\" configuration parameter value");
		err = 1;
	}

	if (ZBX_PB_MODE_DISK != config_proxy_buffer_mode)
	{
		if (0 != config_proxy_local_buffer)
		{
			zabbix_log(LOG_LEVEL_CRIT, "\"ProxyBufferMode\" configuration parameter cannot be"
					" \"memory\" or \"hybrid\" when \"ProxyLocalBuffer\" parameter is set");
			err = 1;
		}

		if (0 == config_proxy_memory_buffer_size)
		{
			zabbix_log(LOG_LEVEL_CRIT, "\"ProxyMemoryBufferSize\" configuration parameter must be set when"
					" \"ProxyBufferMode\" parameter is \"memory\" or \"hybrid\"");
			err = 1;
		}

		if (0 != config_proxy_memory_buffer_age)
		{
			if (ZBX_CONFIG_DATA_CACHE_AGE_MIN > config_proxy_memory_buffer_age)
			{
				zabbix_log(LOG_LEVEL_CRIT, "wrong value of \"ProxyMemoryBufferAge\" configuration"
						" parameter");
				err = 1;
			}

			if (config_proxy_memory_buffer_age >= config_proxy_offline_buffer * SEC_PER_HOUR)
			{
				zabbix_log(LOG_LEVEL_CRIT, "\"ProxyMemoryBufferAge\" configuration parameter cannot be"
						" greater than \"ProxyOfflineBuffer\" parameter");
				err = 1;
			}
		}

		if (0 != config_proxy_memory_buffer_size &&
				ZBX_CONFIG_DATA_CACHE_SIZE_MIN > config_proxy_memory_buffer_size)
		{
			zabbix_log(LOG_LEVEL_CRIT, "wrong value of \"ProxyMemoryBufferSize\" configuration parameter");
			err = 1;

		}
	}
	else
	{
		if (0 != config_proxy_memory_buffer_size)
		{
			zabbix_log(LOG_LEVEL_CRIT, "\"ProxyMemoryBufferSize\" configuration parameter can be set only"
					" when \"ProxyBufferMode\" is \"memory\" or \"hybrid\"");
			err = 1;
		}
	}

	if (ZBX_PB_MODE_HYBRID != config_proxy_buffer_mode)
	{
		if (0 != config_proxy_memory_buffer_age)
		{
			zabbix_log(LOG_LEVEL_CRIT, "\"ProxyMemoryBufferAge\" configuration parameter can be set only"
					" when \"ProxyBufferMode\" is \"hybrid\"");
			err = 1;
		}
	}

	err |= (FAIL == zbx_db_validate_config_features(program_type, zbx_config_dbhigh));

	if (0 != err)
		exit(EXIT_FAILURE);
}

static int	proxy_add_serveractive_host_cb(const zbx_vector_addr_ptr_t *addrs, zbx_vector_str_t *hostnames, void *data)
{
	ZBX_UNUSED(hostnames);
	ZBX_UNUSED(data);

	zbx_addr_copy(&config_server_addrs, addrs);

	return SUCCEED;
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
	struct cfg_line	cfg[] =
	{
		/* PARAMETER,			VAR,					TYPE,
			MANDATORY,	MIN,			MAX */
		{"ProxyMode",			&config_proxymode,			TYPE_INT,
			PARM_OPT,	ZBX_PROXYMODE_ACTIVE,	ZBX_PROXYMODE_PASSIVE},
		{"Server",			&config_server,				TYPE_STRING,
			PARM_MAND,	0,			0},
		{"ServerPort",			&config_server_port,			TYPE_INT,
			PARM_OPT,	1024,			32767},
		{"Hostname",			&config_hostname,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"HostnameItem",		&config_hostname_item,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"StartDBSyncers",		&CONFIG_FORKS[ZBX_PROCESS_TYPE_HISTSYNCER],		TYPE_INT,
			PARM_OPT,	1,			100},
		{"StartDiscoverers",		&CONFIG_FORKS[ZBX_PROCESS_TYPE_DISCOVERER],		TYPE_INT,
			PARM_OPT,	0,			1000},
		{"StartHTTPPollers",		&CONFIG_FORKS[ZBX_PROCESS_TYPE_HTTPPOLLER],		TYPE_INT,
			PARM_OPT,	0,			1000},
		{"StartPingers",		&CONFIG_FORKS[ZBX_PROCESS_TYPE_PINGER],			TYPE_INT,
			PARM_OPT,	0,			1000},
		{"StartPollers",		&CONFIG_FORKS[ZBX_PROCESS_TYPE_POLLER],			TYPE_INT,
			PARM_OPT,	0,			1000},
		{"StartPollersUnreachable",	&CONFIG_FORKS[ZBX_PROCESS_TYPE_UNREACHABLE],	TYPE_INT,
			PARM_OPT,	0,			1000},
		{"StartIPMIPollers",		&CONFIG_FORKS[ZBX_PROCESS_TYPE_IPMIPOLLER],		TYPE_INT,
			PARM_OPT,	0,			1000},
		{"StartTrappers",		&CONFIG_FORKS[ZBX_PROCESS_TYPE_TRAPPER],			TYPE_INT,
			PARM_OPT,	0,			1000},
		{"StartJavaPollers",		&CONFIG_FORKS[ZBX_PROCESS_TYPE_JAVAPOLLER],		TYPE_INT,
			PARM_OPT,	0,			1000},
		{"JavaGateway",			&CONFIG_JAVA_GATEWAY,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"JavaGatewayPort",		&CONFIG_JAVA_GATEWAY_PORT,		TYPE_INT,
			PARM_OPT,	1024,			32767},
		{"SNMPTrapperFile",		&zbx_config_snmptrap_file,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"StartSNMPTrapper",		&CONFIG_FORKS[ZBX_PROCESS_TYPE_SNMPTRAPPER],		TYPE_INT,
			PARM_OPT,	0,			1},
		{"CacheSize",			&config_conf_cache_size,		TYPE_UINT64,
			PARM_OPT,	128 * ZBX_KIBIBYTE,	__UINT64_C(64) * ZBX_GIBIBYTE},
		{"HistoryCacheSize",		&config_history_cache_size,		TYPE_UINT64,
			PARM_OPT,	128 * ZBX_KIBIBYTE,	__UINT64_C(2) * ZBX_GIBIBYTE},
		{"HistoryIndexCacheSize",	&config_history_index_cache_size,	TYPE_UINT64,
			PARM_OPT,	128 * ZBX_KIBIBYTE,	__UINT64_C(2) * ZBX_GIBIBYTE},
		{"HousekeepingFrequency",	&config_housekeeping_frequency,		TYPE_INT,
			PARM_OPT,	0,			24},
		{"ProxyLocalBuffer",		&config_proxy_local_buffer,		TYPE_INT,
			PARM_OPT,	0,			720},
		{"ProxyOfflineBuffer",		&config_proxy_offline_buffer,		TYPE_INT,
			PARM_OPT,	1,			720},
		{"HeartbeatFrequency",		&config_heartbeat_frequency,		TYPE_INT,
			PARM_OPT,	0,			ZBX_PROXY_HEARTBEAT_FREQUENCY_MAX},
		{"ConfigFrequency",		&config_confsyncer_frequency,		TYPE_INT,
			PARM_OPT,	1,			SEC_PER_WEEK},
		{"ProxyConfigFrequency",	&config_proxyconfig_frequency,		TYPE_INT,
			PARM_OPT,	1,			SEC_PER_WEEK},
		{"DataSenderFrequency",		&config_proxydata_frequency,		TYPE_INT,
			PARM_OPT,	1,			SEC_PER_HOUR},
		{"TmpDir",			&zbx_config_tmpdir,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"FpingLocation",		&zbx_config_fping_location,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"Fping6Location",		&zbx_config_fping6_location,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"Timeout",			&zbx_config_timeout,			TYPE_INT,
			PARM_OPT,	1,			30},
		{"TrapperTimeout",		&zbx_config_trapper_timeout,		TYPE_INT,
			PARM_OPT,	1,			300},
		{"UnreachablePeriod",		&config_unreachable_period,		TYPE_INT,
			PARM_OPT,	1,			SEC_PER_HOUR},
		{"UnreachableDelay",		&config_unreachable_delay,		TYPE_INT,
			PARM_OPT,	1,			SEC_PER_HOUR},
		{"UnavailableDelay",		&config_unavailable_delay,		TYPE_INT,
			PARM_OPT,	1,			SEC_PER_HOUR},
		{"ListenIP",			&config_listen_ip,			TYPE_STRING_LIST,
			PARM_OPT,	0,			0},
		{"ListenPort",			&config_listen_port,			TYPE_INT,
			PARM_OPT,	1024,			32767},
		{"SourceIP",			&zbx_config_source_ip,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"DebugLevel",			&config_log_level,			TYPE_INT,
			PARM_OPT,	0,			5},
		{"PidFile",			&zbx_config_pid_file,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"LogType",			&log_file_cfg.log_type_str,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"LogFile",			&log_file_cfg.log_file_name,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"LogFileSize",			&log_file_cfg.log_file_size,		TYPE_INT,
			PARM_OPT,	0,			1024},
		{"ExternalScripts",		&CONFIG_EXTERNALSCRIPTS,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"DBHost",			&(zbx_config_dbhigh->config_dbhost),	TYPE_STRING,
			PARM_OPT,	0,			0},
		{"DBName",			&(zbx_config_dbhigh->config_dbname),	TYPE_STRING,
			PARM_MAND,	0,			0},
		{"DBSchema",			&(zbx_config_dbhigh->config_dbschema),	TYPE_STRING,
			PARM_OPT,	0,			0},
		{"DBUser",			&(zbx_config_dbhigh->config_dbuser),	TYPE_STRING,
			PARM_OPT,	0,			0},
		{"DBPassword",			&(zbx_config_dbhigh->config_dbpassword),	TYPE_STRING,
			PARM_OPT,	0,			0},
		{"VaultToken",			&zbx_config_vault.token,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"Vault",			&zbx_config_vault.name,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"VaultTLSCertFile",		&zbx_config_vault.tls_cert_file,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"VaultTLSKeyFile",		&zbx_config_vault.tls_key_file,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"VaultURL",			&zbx_config_vault.url,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"VaultDBPath",			&zbx_config_vault.db_path,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"DBSocket",			&(zbx_config_dbhigh->config_dbsocket),	TYPE_STRING,
			PARM_OPT,	0,			0},
		{"DBPort",			&(zbx_config_dbhigh->config_dbport),	TYPE_INT,
			PARM_OPT,	1024,			65535},
		{"AllowUnsupportedDBVersions",	&CONFIG_ALLOW_UNSUPPORTED_DB_VERSIONS,	TYPE_INT,
			PARM_OPT,	0,			1},
		{"DBTLSConnect",		&(zbx_config_dbhigh->config_db_tls_connect),	TYPE_STRING,
			PARM_OPT,	0,			0},
		{"DBTLSCertFile",		&(zbx_config_dbhigh->config_db_tls_cert_file),	TYPE_STRING,
			PARM_OPT,	0,			0},
		{"DBTLSKeyFile",		&(zbx_config_dbhigh->config_db_tls_key_file),	TYPE_STRING,
			PARM_OPT,	0,			0},
		{"DBTLSCAFile",			&(zbx_config_dbhigh->config_db_tls_ca_file),	TYPE_STRING,
			PARM_OPT,	0,			0},
		{"DBTLSCipher",			&(zbx_config_dbhigh->config_db_tls_cipher),	TYPE_STRING,
			PARM_OPT,	0,			0},
		{"DBTLSCipher13",		&(zbx_config_dbhigh->config_db_tls_cipher_13),	TYPE_STRING,
			PARM_OPT,	0,			0},
		{"SSHKeyLocation",		&CONFIG_SSH_KEY_LOCATION,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"LogSlowQueries",		&config_log_slow_queries,		TYPE_INT,
			PARM_OPT,	0,			3600000},
		{"LoadModulePath",		&config_load_module_path,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"LoadModule",			&config_load_module,			TYPE_MULTISTRING,
			PARM_OPT,	0,			0},
		{"StartVMwareCollectors",	&CONFIG_FORKS[ZBX_PROCESS_TYPE_VMWARE],			TYPE_INT,
			PARM_OPT,	0,			250},
		{"VMwareFrequency",		&config_vmware_frequency,		TYPE_INT,
			PARM_OPT,	10,			SEC_PER_DAY},
		{"VMwarePerfFrequency",		&config_vmware_perf_frequency,		TYPE_INT,
			PARM_OPT,	10,			SEC_PER_DAY},
		{"VMwareCacheSize",		&config_vmware_cache_size,		TYPE_UINT64,
			PARM_OPT,	256 * ZBX_KIBIBYTE,	__UINT64_C(2) * ZBX_GIBIBYTE},
		{"VMwareTimeout",		&config_vmware_timeout,			TYPE_INT,
			PARM_OPT,	1,			300},
		{"AllowRoot",			&config_allow_root,			TYPE_INT,
			PARM_OPT,	0,			1},
		{"User",			&config_user,				TYPE_STRING,
			PARM_OPT,	0,			0},
		{"SSLCALocation",		&CONFIG_SSL_CA_LOCATION,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"SSLCertLocation",		&CONFIG_SSL_CERT_LOCATION,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"SSLKeyLocation",		&CONFIG_SSL_KEY_LOCATION,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSConnect",			&(zbx_config_tls->connect),		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSAccept",			&(zbx_config_tls->accept),		TYPE_STRING_LIST,
			PARM_OPT,	0,			0},
		{"TLSCAFile",			&(zbx_config_tls->ca_file),		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSCRLFile",			&(zbx_config_tls->crl_file),		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSServerCertIssuer",		&(zbx_config_tls->server_cert_issuer),	TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSServerCertSubject",	&(zbx_config_tls->server_cert_subject),	TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSCertFile",			&(zbx_config_tls->cert_file),		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSKeyFile",			&(zbx_config_tls->key_file),		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSPSKIdentity",		&(zbx_config_tls->psk_identity),	TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSPSKFile",			&(zbx_config_tls->psk_file),		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSCipherCert13",		&(zbx_config_tls->cipher_cert13),	TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSCipherCert",		&(zbx_config_tls->cipher_cert),		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSCipherPSK13",		&(zbx_config_tls->cipher_psk13),	TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSCipherPSK",		&(zbx_config_tls->cipher_psk),		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSCipherAll13",		&(zbx_config_tls->cipher_all13),	TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSCipherAll",		&(zbx_config_tls->cipher_all),		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"SocketDir",			&config_socket_path,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"EnableRemoteCommands",	&zbx_config_enable_remote_commands,	TYPE_INT,
			PARM_OPT,	0,			1},
		{"LogRemoteCommands",		&zbx_config_log_remote_commands,	TYPE_INT,
			PARM_OPT,	0,			1},
		{"StatsAllowedIP",		&CONFIG_STATS_ALLOWED_IP,		TYPE_STRING_LIST,
			PARM_OPT,	0,			0},
		{"StartPreprocessors",		&CONFIG_FORKS[ZBX_PROCESS_TYPE_PREPROCESSOR],		TYPE_INT,
			PARM_OPT,	1,			1000},
		{"ListenBacklog",		&CONFIG_TCP_MAX_BACKLOG_SIZE,		TYPE_INT,
			PARM_OPT,	0,			INT_MAX},
		{"StartODBCPollers",		&CONFIG_FORKS[ZBX_PROCESS_TYPE_ODBCPOLLER],	TYPE_INT,
			PARM_OPT,	0,			1000},
		{"ProxyMemoryBufferSize",	&config_proxy_memory_buffer_size,	TYPE_UINT64,
			PARM_OPT,	0,	__UINT64_C(2) * ZBX_GIBIBYTE},
		{"ProxyMemoryBufferAge",	&config_proxy_memory_buffer_age,	TYPE_INT,
			PARM_OPT,	0,	SEC_PER_DAY * 10},
		{"ProxyBufferMode",		&config_proxy_buffer_mode_str,		TYPE_STRING,
			PARM_OPT,	0,	0},
		{"StartHTTPAgentPollers",	&CONFIG_FORKS[ZBX_PROCESS_TYPE_HTTPAGENT_POLLER],	TYPE_INT,
			PARM_OPT,	0,			1000},
		{"StartAgentPollers",		&CONFIG_FORKS[ZBX_PROCESS_TYPE_AGENT_POLLER],	TYPE_INT,
			PARM_OPT,	0,			1000},
		{"StartSNMPPollers",		&CONFIG_FORKS[ZBX_PROCESS_TYPE_SNMP_POLLER],	TYPE_INT,
			PARM_OPT,	0,			1000},
		{"MaxConcurrentChecksPerPoller",	&config_max_concurrent_checks_per_poller,	TYPE_INT,
			PARM_OPT,	1,			1000},
		{NULL}
	};

	/* initialize multistrings */
	zbx_strarr_init(&config_load_module);

	parse_cfg_file(config_file, cfg, ZBX_CFG_FILE_REQUIRED, ZBX_CFG_STRICT, ZBX_CFG_EXIT_FAILURE);

	zbx_set_defaults();

	log_file_cfg.log_type = zbx_get_log_type(log_file_cfg.log_type_str);

	zbx_validate_config(task);

	zbx_vector_addr_ptr_create(&config_server_addrs);

	if (ZBX_PROXYMODE_PASSIVE != config_proxymode)
	{
		char	*error;

		if (FAIL == zbx_set_data_destination_hosts(config_server, (unsigned short)config_server_port, "Server",
				proxy_add_serveractive_host_cb, NULL, NULL, &error))
		{
			zbx_error("%s", error);
			exit(EXIT_FAILURE);
		}
	}

#if defined(HAVE_MYSQL) || defined(HAVE_POSTGRESQL)
	zbx_db_validate_config(zbx_config_dbhigh);
#endif
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_validate_config(zbx_config_tls, CONFIG_FORKS[ZBX_PROCESS_TYPE_ACTIVE_CHECKS],
			CONFIG_FORKS[ZBX_PROCESS_TYPE_LISTENER], get_program_type);
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: free configuration memory                                         *
 *                                                                            *
 ******************************************************************************/
static void	zbx_free_config(void)
{
	zbx_strarr_free(&config_load_module);
}

static void	zbx_on_exit(int ret)
{
	zabbix_log(LOG_LEVEL_DEBUG, "zbx_on_exit() called with ret:%d", ret);

	zbx_pb_disable();

	if (NULL != threads)
	{
		/* wait for all child processes to exit */
		zbx_threads_kill_and_wait(threads, threads_flags, threads_num, ret);

		zbx_free(threads);
		zbx_free(threads_flags);
	}
#ifdef HAVE_PTHREAD_PROCESS_SHARED
	zbx_locks_disable();
#endif
	zbx_free_metrics();
	zbx_ipc_service_free_env();

	zbx_db_connect(ZBX_DB_CONNECT_EXIT);
	zbx_free_database_cache(ZBX_SYNC_ALL, &events_cbs);
	zbx_pb_flush();
	zbx_pb_destroy();
	zbx_free_configuration_cache();
	zbx_db_close();

	zbx_db_deinit();

	zbx_deinit_remote_commands_cache();

	/* free vmware support */
	zbx_vmware_destroy();

	zbx_free_selfmon_collector();
	free_proxy_history_lock();

	zbx_unload_modules();

	zabbix_log(LOG_LEVEL_INFORMATION, "Zabbix Proxy stopped. Zabbix %s (revision %s).",
			ZABBIX_VERSION, ZABBIX_REVISION);

	zbx_close_log();

	zbx_locks_destroy();

	zbx_setproctitle_deinit();

	zbx_config_tls_free(zbx_config_tls);
	zbx_config_dbhigh_free(zbx_config_dbhigh);

	exit(EXIT_SUCCESS);
}

/******************************************************************************
 *                                                                            *
 * Purpose: executes proxy processes                                          *
 *                                                                            *
 ******************************************************************************/
int	main(int argc, char **argv)
{
	static zbx_config_icmpping_t	config_icmpping = {
		get_zbx_config_source_ip,
		get_zbx_config_fping_location,
		get_zbx_config_fping6_location,
		get_zbx_config_tmpdir};

	ZBX_TASK_EX			t = {ZBX_TASK_START};
	char				ch;
	int				opt_c = 0, opt_r = 0;

	/* see description of 'optarg' in 'man 3 getopt' */
	char				*zbx_optarg = NULL;

	/* see description of 'optind' in 'man 3 getopt' */
	int				zbx_optind = 0;

	zbx_init_library_common(zbx_log_impl);
	zbx_config_tls = zbx_config_tls_new();
	zbx_config_dbhigh = zbx_config_dbhigh_new();
	argv = zbx_setproctitle_init(argc, argv);
	progname = get_program_name(argv[0]);

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
			case 'h':
				zbx_help(NULL);
				exit(EXIT_SUCCESS);
				break;
			case 'V':
				zbx_version();
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
				printf("\n");
				zbx_tls_version();
#endif
				exit(EXIT_SUCCESS);
				break;
			case 'f':
				t.flags |= ZBX_TASK_FLAG_FOREGROUND;
				break;
			default:
				zbx_usage();
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

	if (NULL == config_file)
		config_file = zbx_strdup(NULL, DEFAULT_CONFIG_FILE);

	/* required for simple checks */
	zbx_init_metrics();
	zbx_init_library_cfg(program_type, config_file);

	zbx_load_config(&t);

	zbx_init_library_dbupgrade(get_program_type, get_zbx_config_timeout);
	zbx_init_library_dbwrap(NULL, zbx_preprocess_item_value, zbx_preprocessor_flush);
	zbx_init_library_icmpping(&config_icmpping);
	zbx_init_library_ipcservice(program_type);
	zbx_init_library_sysinfo(get_zbx_config_timeout, get_zbx_config_enable_remote_commands,
			get_zbx_config_log_remote_commands, get_zbx_config_unsafe_user_parameters,
			get_zbx_config_source_ip, NULL, NULL, NULL, NULL);
	zbx_init_library_stats(get_program_type);
	zbx_init_library_dbhigh(zbx_config_dbhigh);
	zbx_init_library_preproc(preproc_flush_value_proxy);
	zbx_init_library_eval(zbx_dc_get_expressions_by_name);

	if (ZBX_TASK_RUNTIME_CONTROL == t.task)
	{
		int	ret;
		char	*error = NULL;

		if (FAIL == zbx_ipc_service_init_env(config_socket_path, &error))
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

	return zbx_daemon_start(config_allow_root, config_user, t.flags, get_zbx_config_pid_file, zbx_on_exit,
			log_file_cfg.log_type, log_file_cfg.log_file_name, NULL);
}

static void	zbx_check_db(void)
{
	struct zbx_db_version_info_t	db_version_info;

	if (FAIL == zbx_db_check_version_info(&db_version_info, CONFIG_ALLOW_UNSUPPORTED_DB_VERSIONS, program_type))
	{
		zbx_free(db_version_info.friendly_current_version);
		exit(EXIT_FAILURE);
	}

	zbx_free(db_version_info.friendly_current_version);
}

static void	proxy_db_init(void)
{
	char		*error = NULL;
	int		db_type, version_check;
#ifdef HAVE_SQLITE3
	zbx_stat_t	db_stat;
#endif

	if (SUCCEED != zbx_db_init(zbx_dc_get_nextid, config_log_slow_queries, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize database: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	zbx_db_init_autoincrement_options();

	if (ZBX_DB_UNKNOWN == (db_type = zbx_db_get_database_type()))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot use database \"%s\": database is not a Zabbix database",
				zbx_config_dbhigh->config_dbname);
		exit(EXIT_FAILURE);
	}
	else if (ZBX_DB_PROXY != db_type)
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot use database \"%s\": Zabbix proxy cannot work with a"
				" Zabbix server database", zbx_config_dbhigh->config_dbname);
		exit(EXIT_FAILURE);
	}

	zbx_db_check_character_set();
	zbx_check_db();

	if (SUCCEED != (version_check = zbx_db_check_version_and_upgrade(ZBX_HA_MODE_STANDALONE)))
	{
#ifdef HAVE_SQLITE3
		if (NOTSUPPORTED == version_check)
			exit(EXIT_FAILURE);

		zabbix_log(LOG_LEVEL_WARNING, "removing database file: \"%s\"", zbx_config_dbhigh->config_dbname);
		zbx_db_deinit();

		if (0 != unlink(zbx_config_dbhigh->config_dbname))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot remove database file \"%s\": %s, exiting...",
					zbx_config_dbhigh->config_dbname, zbx_strerror(errno));
			exit(EXIT_FAILURE);
		}

		proxy_db_init();
#else
		ZBX_UNUSED(version_check);
		exit(EXIT_FAILURE);
#endif
	}

#ifdef HAVE_ORACLE
	zbx_db_connect(ZBX_DB_CONNECT_NORMAL);
	zbx_db_table_prepare("items", NULL);
	zbx_db_table_prepare("item_preproc", NULL);
	zbx_db_close();
#endif
}

int	MAIN_ZABBIX_ENTRY(int flags)
{
	zbx_socket_t				listen_sock;
	char					*error = NULL;
	int					i, ret;
	zbx_rtc_t				rtc;
	zbx_timespec_t				rtc_timeout = {1, 0};

	zbx_config_comms_args_t			config_comms = {zbx_config_tls, config_hostname, config_server,
								config_proxymode, zbx_config_timeout,
								zbx_config_trapper_timeout, zbx_config_source_ip};
	zbx_thread_args_t			thread_args;
	zbx_thread_poller_args			poller_args = {&config_comms, get_program_type, ZBX_NO_POLLER,
								config_startup_time, config_unavailable_delay,
								config_unreachable_period, config_unreachable_delay,
								config_max_concurrent_checks_per_poller};
	zbx_thread_proxyconfig_args		proxyconfig_args = {zbx_config_tls, &zbx_config_vault,
								get_program_type, zbx_config_timeout,
								&config_server_addrs, config_hostname,
								zbx_config_source_ip, config_proxyconfig_frequency};
	zbx_thread_datasender_args		datasender_args = {zbx_config_tls, get_program_type, zbx_config_timeout,
								&config_server_addrs, zbx_config_source_ip,
								config_hostname, config_proxydata_frequency};
	zbx_thread_taskmanager_args		taskmanager_args = {&config_comms, get_program_type,
								config_startup_time, zbx_config_enable_remote_commands,
								zbx_config_log_remote_commands, config_hostname,
								get_config_forks};
	zbx_thread_httppoller_args		httppoller_args = {zbx_config_source_ip};
	zbx_thread_discoverer_args		discoverer_args = {zbx_config_tls, get_program_type, zbx_config_timeout,
								CONFIG_FORKS[ZBX_PROCESS_TYPE_DISCOVERER],
								zbx_config_source_ip, &events_cbs};
	zbx_thread_trapper_args			trapper_args = {&config_comms, &zbx_config_vault, get_program_type,
								&events_cbs, &listen_sock, config_startup_time,
								config_proxydata_frequency, get_config_forks};
	zbx_thread_proxy_housekeeper_args	housekeeper_args = {zbx_config_timeout, config_housekeeping_frequency,
								config_proxy_local_buffer, config_proxy_offline_buffer};
	zbx_thread_pinger_args			pinger_args = {zbx_config_timeout};
#ifdef HAVE_OPENIPMI
	zbx_thread_ipmi_manager_args		ipmimanager_args = {zbx_config_timeout, config_unavailable_delay,
								config_unreachable_period, config_unreachable_delay};
#endif
	zbx_thread_pp_manager_args		preproc_man_args = {
							.workers_num = CONFIG_FORKS[ZBX_PROCESS_TYPE_PREPROCESSOR],
							.config_timeout = zbx_config_timeout,
							zbx_config_source_ip};
	zbx_thread_dbsyncer_args		dbsyncer_args = {&events_cbs, config_histsyncer_frequency};
	zbx_thread_vmware_args			vmware_args = {zbx_config_source_ip, config_vmware_frequency,
								config_vmware_perf_frequency, config_vmware_timeout};
	zbx_thread_snmptrapper_args		snmptrapper_args = {zbx_config_snmptrap_file};

	zbx_rtc_process_request_ex_func_t	rtc_process_request_func = NULL;

	if (0 != (flags & ZBX_TASK_FLAG_FOREGROUND))
	{
		printf("Starting Zabbix Proxy (%s) [%s]. Zabbix %s (revision %s).\nPress Ctrl+C to exit.\n\n",
				ZBX_PROXYMODE_PASSIVE == config_proxymode ? "passive" : "active",
				config_hostname, ZABBIX_VERSION, ZABBIX_REVISION);
	}

	if (FAIL == zbx_ipc_service_init_env(config_socket_path, &error))
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

	if (SUCCEED != zbx_open_log(&log_file_cfg, config_log_level, &error))
	{
		zbx_error("cannot open log:%s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

#ifdef HAVE_NETSNMP
#	define SNMP_FEATURE_STATUS 	"YES"
#else
#	define SNMP_FEATURE_STATUS 	" NO"
#endif
#ifdef HAVE_OPENIPMI
#	define IPMI_FEATURE_STATUS 	"YES"
#else
#	define IPMI_FEATURE_STATUS 	" NO"
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
#ifdef HAVE_UNIXODBC
#	define ODBC_FEATURE_STATUS 	"YES"
#else
#	define ODBC_FEATURE_STATUS 	" NO"
#endif
#if defined(HAVE_SSH2) || defined(HAVE_SSH)
#	define SSH_FEATURE_STATUS 	"YES"
#else
#	define SSH_FEATURE_STATUS 	" NO"
#endif
#ifdef HAVE_IPV6
#	define IPV6_FEATURE_STATUS 	"YES"
#else
#	define IPV6_FEATURE_STATUS 	" NO"
#endif
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
#	define TLS_FEATURE_STATUS	"YES"
#else
#	define TLS_FEATURE_STATUS	" NO"
#endif

	zabbix_log(LOG_LEVEL_INFORMATION, "Starting Zabbix Proxy (%s) [%s]. Zabbix %s (revision %s).",
			ZBX_PROXYMODE_PASSIVE == config_proxymode ? "passive" : "active",
			config_hostname, ZABBIX_VERSION, ZABBIX_REVISION);

	zabbix_log(LOG_LEVEL_INFORMATION, "**** Enabled features ****");
	zabbix_log(LOG_LEVEL_INFORMATION, "SNMP monitoring:       " SNMP_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "IPMI monitoring:       " IPMI_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "Web monitoring:        " LIBCURL_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "VMware monitoring:     " VMWARE_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "ODBC:                  " ODBC_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "SSH support:           " SSH_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "IPv6 support:          " IPV6_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "TLS support:           " TLS_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "**************************");

	zabbix_log(LOG_LEVEL_INFORMATION, "using configuration file: %s", config_file);

#ifdef HAVE_ORACLE
	zabbix_log(LOG_LEVEL_INFORMATION, "Support for Oracle DB is deprecated since Zabbix 7.0 and will be removed in "
			"future versions");
#endif

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	if (SUCCEED != zbx_coredump_disable())
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot disable core dump, exiting...");
		exit(EXIT_FAILURE);
	}
#endif
	if (FAIL == zbx_load_modules(config_load_module_path, config_load_module, zbx_config_timeout, 1))
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

	if (SUCCEED != zbx_init_database_cache(get_program_type, zbx_sync_proxy_history, config_history_cache_size,
			config_history_index_cache_size, &config_trends_cache_size, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize database cache: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	if (SUCCEED != init_proxy_history_lock(&error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize lock for passive proxy history: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	if (SUCCEED != zbx_init_configuration_cache(get_program_type, get_config_forks, config_conf_cache_size, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize configuration cache: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	if (SUCCEED != zbx_init_selfmon_collector(get_config_forks, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize self-monitoring: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	if (0 != CONFIG_FORKS[ZBX_PROCESS_TYPE_VMWARE] && SUCCEED != zbx_vmware_init(&config_vmware_cache_size, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize VMware cache: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

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

	if (SUCCEED != zbx_vault_db_credentials_get(&zbx_config_vault, &zbx_config_dbhigh->config_dbuser,
			&zbx_config_dbhigh->config_dbpassword, zbx_config_source_ip, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize database credentials from vault: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	if (FAIL == zbx_pb_create(config_proxy_buffer_mode, config_proxy_memory_buffer_size,
			config_proxy_memory_buffer_age, config_proxy_offline_buffer * SEC_PER_HOUR, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize proxy buffer: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

	proxy_db_init();

	zbx_pb_init();

	if (0 != CONFIG_FORKS[ZBX_PROCESS_TYPE_DISCOVERYMANAGER])
		zbx_discoverer_init();

	for (threads_num = 0, i = 0; i < ZBX_PROCESS_TYPE_COUNT; i++)
	{
		/* skip threaded components */
		switch (i)
		{
			case ZBX_PROCESS_TYPE_PREPROCESSOR:
			case ZBX_PROCESS_TYPE_DISCOVERER:
				continue;
		}

		threads_num += CONFIG_FORKS[i];
	}

	threads = (pid_t *)zbx_calloc(threads, (size_t)threads_num, sizeof(pid_t));
	threads_flags = (int *)zbx_calloc(threads_flags, (size_t)threads_num, sizeof(int));

	if (0 != CONFIG_FORKS[ZBX_PROCESS_TYPE_TRAPPER])
	{
		if (FAIL == zbx_tcp_listen(&listen_sock, config_listen_ip, (unsigned short)config_listen_port,
				zbx_config_timeout))
		{
			zabbix_log(LOG_LEVEL_CRIT, "listener failed: %s", zbx_socket_strerror());
			exit(EXIT_FAILURE);
		}

		if (SUCCEED != zbx_init_remote_commands_cache(&error))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot initialize commands cache: %s", error);
			zbx_free(error);
			exit(EXIT_FAILURE);
		}
	}

	/* not running zbx_tls_init_parent() since proxy is only run on Unix*/

	zabbix_log(LOG_LEVEL_INFORMATION, "proxy #0 started [main process]");

	zbx_register_stats_data_func(zbx_preproc_stats_ext_get, NULL);
	zbx_register_stats_data_func(zbx_discovery_stats_ext_get, NULL);
	zbx_register_stats_data_func(zbx_proxy_stats_ext_get, &config_comms);
	zbx_register_stats_ext_func(zbx_vmware_stats_ext_get, NULL);
	zbx_register_stats_procinfo_func(ZBX_PROCESS_TYPE_PREPROCESSOR, zbx_preprocessor_get_worker_info);
	zbx_register_stats_procinfo_func(ZBX_PROCESS_TYPE_DISCOVERER, zbx_discovery_get_worker_info);
	zbx_diag_init(diag_add_section_info);

	thread_args.info.program_type = program_type;

	if (ZBX_PROXYMODE_PASSIVE == config_proxymode)
		rtc_process_request_func = rtc_process_request_ex_proxy_passive;
	else
		rtc_process_request_func = rtc_process_request_ex_proxy;

	for (i = 0; i < threads_num; i++)
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
			case ZBX_PROCESS_TYPE_CONFSYNCER:
				thread_args.args = &proxyconfig_args;
				zbx_thread_start(proxyconfig_thread, &thread_args, &threads[i]);
				if (FAIL == zbx_rtc_wait_config_sync(&rtc, rtc_process_request_func))
					goto out;
				break;
			case ZBX_PROCESS_TYPE_TRAPPER:
				thread_args.args = &trapper_args;
				zbx_thread_start(trapper_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_DATASENDER:
				thread_args.args = &datasender_args;
				zbx_thread_start(datasender_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_POLLER:
				poller_args.poller_type = ZBX_POLLER_TYPE_NORMAL;
				thread_args.args = &poller_args;
				zbx_thread_start(poller_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_UNREACHABLE:
				poller_args.poller_type = ZBX_POLLER_TYPE_UNREACHABLE;
				thread_args.args = &poller_args;
				zbx_thread_start(poller_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_PINGER:
				thread_args.args = &pinger_args;
				zbx_thread_start(pinger_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_HOUSEKEEPER:
				thread_args.args = &housekeeper_args;
				zbx_thread_start(housekeeper_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_HTTPPOLLER:
				thread_args.args = &httppoller_args;
				zbx_thread_start(httppoller_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_DISCOVERYMANAGER:
				threads_flags[i] = ZBX_THREAD_PRIORITY_FIRST;
				thread_args.args = &discoverer_args;
				zbx_thread_start(discoverer_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_HISTSYNCER:
				threads_flags[i] = ZBX_THREAD_PRIORITY_FIRST;
				thread_args.args = &dbsyncer_args;
				zbx_thread_start(zbx_dbsyncer_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_JAVAPOLLER:
				poller_args.poller_type = ZBX_POLLER_TYPE_JAVA;
				thread_args.args = &poller_args;
				zbx_thread_start(poller_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_SNMPTRAPPER:
				thread_args.args = &snmptrapper_args;
				zbx_thread_start(zbx_snmptrapper_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_SELFMON:
				zbx_thread_start(zbx_selfmon_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_VMWARE:
				thread_args.args = &vmware_args;
				zbx_thread_start(vmware_thread, &thread_args, &threads[i]);
				break;
#ifdef HAVE_OPENIPMI
			case ZBX_PROCESS_TYPE_IPMIMANAGER:
				thread_args.args = &ipmimanager_args;
				zbx_thread_start(zbx_ipmi_manager_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_IPMIPOLLER:
				zbx_thread_start(zbx_ipmi_poller_thread, &thread_args, &threads[i]);
				break;
#endif
			case ZBX_PROCESS_TYPE_TASKMANAGER:
				thread_args.args = &taskmanager_args;
				zbx_thread_start(taskmanager_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_PREPROCMAN:
				threads_flags[i] = ZBX_THREAD_PRIORITY_FIRST;
				thread_args.args = &preproc_man_args;
				zbx_thread_start(zbx_pp_manager_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_AVAILMAN:
				threads_flags[i] = ZBX_THREAD_PRIORITY_FIRST;
				zbx_thread_start(zbx_availability_manager_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_ODBCPOLLER:
				poller_args.poller_type = ZBX_POLLER_TYPE_ODBC;
				thread_args.args = &poller_args;
				zbx_thread_start(poller_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_HTTPAGENT_POLLER:
				poller_args.poller_type = ZBX_POLLER_TYPE_HTTPAGENT;
				thread_args.args = &poller_args;
				zbx_thread_start(async_poller_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_AGENT_POLLER:
				poller_args.poller_type = ZBX_POLLER_TYPE_AGENT;
				thread_args.args = &poller_args;
				zbx_thread_start(async_poller_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_SNMP_POLLER:
				poller_args.poller_type = ZBX_POLLER_TYPE_SNMP;
				thread_args.args = &poller_args;
				zbx_thread_start(async_poller_thread, &thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_INTERNAL_POLLER:
				poller_args.poller_type = ZBX_POLLER_TYPE_INTERNAL;
				thread_args.args = &poller_args;
				zbx_thread_start(poller_thread, &thread_args, &threads[i]);
		}
	}

	zbx_unset_exit_on_terminate();

	while (ZBX_IS_RUNNING())
	{
		zbx_ipc_client_t	*client;
		zbx_ipc_message_t	*message;

		(void)zbx_ipc_service_recv(&rtc.service, &rtc_timeout, &client, &message);

		if (NULL != message)
		{
			zbx_rtc_dispatch(&rtc, client, message, rtc_process_request_func);
			zbx_ipc_message_free(message);
		}

		if (NULL != client)
			zbx_ipc_client_release(client);

		if (0 < (ret = waitpid((pid_t)-1, &i, WNOHANG)))
		{
			zbx_set_exiting_with_fail();
			break;
		}

		if (-1 == ret && EINTR != errno)
		{
			zabbix_log(LOG_LEVEL_ERR, "failed to wait on child processes: %s", zbx_strerror(errno));
			zbx_set_exiting_with_fail();
			break;
		}
	}
out:
	zbx_log_exit_signal();

	if (SUCCEED == ZBX_EXIT_STATUS())
		zbx_rtc_shutdown_subs(&rtc);

	zbx_on_exit(ZBX_EXIT_STATUS());

	return SUCCEED;
}
