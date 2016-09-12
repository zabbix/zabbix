/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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
#include "proxy.h"

#include "sysinfo.h"
#include "zbxserver.h"

#include "daemon.h"
#include "zbxself.h"
#include "rsm.h"

#include "../zabbix_server/dbsyncer/dbsyncer.h"
#include "../zabbix_server/discoverer/discoverer.h"
#include "../zabbix_server/httppoller/httppoller.h"
#include "housekeeper/housekeeper.h"
#include "../zabbix_server/pinger/pinger.h"
#include "../zabbix_server/poller/poller.h"
#include "../zabbix_server/poller/checks_ipmi.h"
#include "../zabbix_server/trapper/trapper.h"
#include "../zabbix_server/snmptrapper/snmptrapper.h"
#include "proxyconfig/proxyconfig.h"
#include "datasender/datasender.h"
#include "heart/heart.h"
#include "../zabbix_server/selfmon/selfmon.h"

#define INIT_PROXY(type, count)									\
	process_type = type;									\
	process_num = proxy_num - proxy_count + count;						\
	zabbix_log(LOG_LEVEL_INFORMATION, "proxy #%d started [%s #%d]",				\
			proxy_num, get_process_type_string(process_type), process_num)

const char	*progname = NULL;
const char	title_message[] = "Zabbix proxy";
const char	usage_message[] = "[-hVr] [-c <file>] [-R <option>]";

const char	*help_message[] = {
	"Options:",
	"  -c --config <file>              Absolute path to the configuration file",
	"  -R --runtime-control <option>   Perform administrative functions",
	"",
	"Runtime control options:",
	"  " ZBX_CONFIG_CACHE_RELOAD "             Reload configuration cache",
	"",
	"Other options:",
	"  -r --rsm                        Enable Registry SLA Monitoring (RSM) support",
	"  -h --help                       Give this help",
	"  -V --version                    Display version number",
	NULL	/* end of text */
};

/* COMMAND LINE OPTIONS */

/* long options */
static struct zbx_option	longopts[] =
{
	{"config",		1,	NULL,	'c'},
	{"runtime-control",	1,	NULL,	'R'},
	{"help",		0,	NULL,	'h'},
	{"rsm",			0,	NULL,	'r'},
	{"version",		0,	NULL,	'V'},
	{NULL}
};

/* short options */
static char	shortopts[] = "c:n:hrVR:";

/* end of COMMAND LINE OPTIONS */

int	threads_num = 0;
pid_t	*threads = NULL;

unsigned char	daemon_type		= ZBX_DAEMON_TYPE_PROXY_ACTIVE;

int		process_num		= 0;
unsigned char	process_type		= ZBX_PROCESS_TYPE_UNKNOWN;

int	CONFIG_PROXYMODE		= ZBX_PROXYMODE_ACTIVE;
int	CONFIG_DATASENDER_FORKS		= 1;
int	CONFIG_DISCOVERER_FORKS		= 1;
int	CONFIG_HOUSEKEEPER_FORKS	= 1;
int	CONFIG_PINGER_FORKS		= 1;
int	CONFIG_POLLER_FORKS		= 5;
int	CONFIG_UNREACHABLE_POLLER_FORKS	= 1;
int	CONFIG_HTTPPOLLER_FORKS		= 1;
int	CONFIG_IPMIPOLLER_FORKS		= 0;
int	CONFIG_TRAPPER_FORKS		= 5;
int	CONFIG_SNMPTRAPPER_FORKS	= 0;
int	CONFIG_JAVAPOLLER_FORKS		= 0;
int	CONFIG_SELFMON_FORKS		= 0;
int	CONFIG_PROXYPOLLER_FORKS	= 0;
int	CONFIG_ESCALATOR_FORKS		= 0;
int	CONFIG_ALERTER_FORKS		= 0;
int	CONFIG_TIMER_FORKS		= 0;
int	CONFIG_NODEWATCHER_FORKS	= 0;
int	CONFIG_WATCHDOG_FORKS		= 0;
int	CONFIG_HEARTBEAT_FORKS		= 1;

int	CONFIG_LISTEN_PORT		= ZBX_DEFAULT_SERVER_PORT;
char	*CONFIG_LISTEN_IP		= NULL;
char	*CONFIG_SOURCE_IP		= NULL;
int	CONFIG_TRAPPER_TIMEOUT		= 300;

int	CONFIG_HOUSEKEEPING_FREQUENCY	= 1;
int	CONFIG_PROXY_LOCAL_BUFFER	= 0;
int	CONFIG_PROXY_OFFLINE_BUFFER	= 1;

int	CONFIG_HEARTBEAT_FREQUENCY	= 60;

int	CONFIG_PROXYCONFIG_FREQUENCY	= SEC_PER_HOUR;
int	CONFIG_PROXYDATA_FREQUENCY	= 1;

int	CONFIG_SENDER_FREQUENCY		= 30;
int	CONFIG_HISTSYNCER_FORKS		= 4;
int	CONFIG_HISTSYNCER_FREQUENCY	= 5;
int	CONFIG_CONFSYNCER_FORKS		= 1;

zbx_uint64_t	CONFIG_CONF_CACHE_SIZE		= 8 * ZBX_MEBIBYTE;
zbx_uint64_t	CONFIG_HISTORY_CACHE_SIZE	= 8 * ZBX_MEBIBYTE;
zbx_uint64_t	CONFIG_TRENDS_CACHE_SIZE	= 0;
zbx_uint64_t	CONFIG_TEXT_CACHE_SIZE		= 16 * ZBX_MEBIBYTE;

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

char	*CONFIG_SERVER			= NULL;
int	CONFIG_SERVER_PORT		= ZBX_DEFAULT_SERVER_PORT;
char	*CONFIG_HOSTNAME		= NULL;
char	*CONFIG_HOSTNAME_ITEM		= NULL;
int	CONFIG_NODEID			= -1;
int	CONFIG_MASTER_NODEID		= 0;
int	CONFIG_NODE_NOEVENTS		= 0;
int	CONFIG_NODE_NOHISTORY		= 0;

char	*CONFIG_SNMPTRAP_FILE		= NULL;

char	*CONFIG_JAVA_GATEWAY		= NULL;
int	CONFIG_JAVA_GATEWAY_PORT	= ZBX_DEFAULT_GATEWAY_PORT;

char	*CONFIG_SSH_KEY_LOCATION	= NULL;

int	CONFIG_LOG_SLOW_QUERIES		= 0;	/* ms; 0 - disable */

/* zabbix server startup time */
int	CONFIG_SERVER_STARTUP_TIME	= 0;

/* mutex for node syncs; not used in proxy */
ZBX_MUTEX	node_sync_access;

/* a passphrase for EPP data encryption used in proxy poller */
char	epp_passphrase[128]		= "";

/* latency mode: delay sending collected data from proxy to server */
int			enable_latency	= 0;
zbx_latency_config_t	latency_config;

/******************************************************************************
 *                                                                            *
 * Function: zbx_set_defaults                                                 *
 *                                                                            *
 * Purpose: set configuration defaults                                        *
 *                                                                            *
 * Author: Rudolfs Kreicbergs                                                 *
 *                                                                            *
 ******************************************************************************/
static void	zbx_set_defaults()
{
	AGENT_RESULT	result;
	char		**value = NULL;

	CONFIG_SERVER_STARTUP_TIME = time(NULL);

	if (NULL == CONFIG_HOSTNAME)
	{
		if (NULL == CONFIG_HOSTNAME_ITEM)
			CONFIG_HOSTNAME_ITEM = zbx_strdup(CONFIG_HOSTNAME_ITEM, "system.hostname");

		init_result(&result);

		if (SUCCEED == process(CONFIG_HOSTNAME_ITEM, PROCESS_LOCAL_COMMAND, &result) &&
				NULL != (value = GET_STR_RESULT(&result)))
		{
			assert(*value);

			if (MAX_ZBX_HOSTNAME_LEN < strlen(*value))
			{
				(*value)[MAX_ZBX_HOSTNAME_LEN] = '\0';
				zabbix_log(LOG_LEVEL_WARNING, "proxy name truncated to [%s])", *value);
			}

			CONFIG_HOSTNAME = zbx_strdup(CONFIG_HOSTNAME, *value);
		}
		else
			zabbix_log(LOG_LEVEL_WARNING, "failed to get proxy name from [%s])", CONFIG_HOSTNAME_ITEM);

		free_result(&result);
	}
	else if (NULL != CONFIG_HOSTNAME_ITEM)
		zabbix_log(LOG_LEVEL_WARNING, "both Hostname and HostnameItem defined, using [%s]", CONFIG_HOSTNAME);

	if (NULL == CONFIG_DBHOST)
		CONFIG_DBHOST = zbx_strdup(CONFIG_DBHOST, "localhost");

	if (NULL == CONFIG_SNMPTRAP_FILE)
		CONFIG_SNMPTRAP_FILE = zbx_strdup(CONFIG_SNMPTRAP_FILE, "/tmp/zabbix_traps.tmp");

	if (NULL == CONFIG_PID_FILE)
		CONFIG_PID_FILE = zbx_strdup(CONFIG_PID_FILE, "/tmp/zabbix_proxy.pid");

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

	if (ZBX_PROXYMODE_ACTIVE != CONFIG_PROXYMODE || 0 == CONFIG_HEARTBEAT_FREQUENCY)
		CONFIG_HEARTBEAT_FORKS = 0;

	if (ZBX_PROXYMODE_PASSIVE == CONFIG_PROXYMODE)
	{
		CONFIG_CONFSYNCER_FORKS = CONFIG_DATASENDER_FORKS = 0;
		daemon_type = ZBX_DAEMON_TYPE_PROXY_PASSIVE;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_validate_config                                              *
 *                                                                            *
 * Purpose: validate configuration parameters                                 *
 *                                                                            *
 * Author: Alexei Vladishev, Rudolfs Kreicbergs                               *
 *                                                                            *
 ******************************************************************************/
static void	zbx_validate_config()
{
	if ((NULL == CONFIG_JAVA_GATEWAY || '\0' == *CONFIG_JAVA_GATEWAY) && 0 < CONFIG_JAVAPOLLER_FORKS)
	{
		zabbix_log(LOG_LEVEL_CRIT, "JavaGateway not in config file or empty");
		exit(1);
	}

	if (ZBX_PROXYMODE_ACTIVE == CONFIG_PROXYMODE &&	NULL == CONFIG_SERVER)
	{
		zabbix_log(LOG_LEVEL_CRIT, "missing active proxy mandatory parameter [Server] in config file [%s]", CONFIG_FILE);
		exit(FAIL);
	}

	if (NULL == CONFIG_HOSTNAME)
	{
		zabbix_log(LOG_LEVEL_CRIT, "hostname is not defined");
		exit(FAIL);
	}

	if (FAIL == zbx_check_hostname(CONFIG_HOSTNAME))
	{
		zabbix_log(LOG_LEVEL_CRIT, "invalid host name: [%s]", CONFIG_HOSTNAME);
		exit(FAIL);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_load_config                                                  *
 *                                                                            *
 * Purpose: parse config file and update configuration parameters             *
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
		{"ProxyMode",			&CONFIG_PROXYMODE,			TYPE_INT,
			PARM_OPT,	ZBX_PROXYMODE_ACTIVE,	ZBX_PROXYMODE_PASSIVE},
		{"Server",			&CONFIG_SERVER,				TYPE_STRING,
			PARM_OPT,	0,			0},
		{"ServerPort",			&CONFIG_SERVER_PORT,			TYPE_INT,
			PARM_OPT,	1024,			32767},
		{"Hostname",			&CONFIG_HOSTNAME,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"HostnameItem",		&CONFIG_HOSTNAME_ITEM,			TYPE_STRING,
			PARM_OPT,	0,			0},
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
		{"CacheSize",			&CONFIG_CONF_CACHE_SIZE,		TYPE_UINT64,
			PARM_OPT,	128 * ZBX_KIBIBYTE,	__UINT64_C(8) * ZBX_GIBIBYTE},
		{"HistoryCacheSize",		&CONFIG_HISTORY_CACHE_SIZE,		TYPE_UINT64,
			PARM_OPT,	128 * ZBX_KIBIBYTE,	__UINT64_C(2) * ZBX_GIBIBYTE},
		{"HistoryTextCacheSize",	&CONFIG_TEXT_CACHE_SIZE,		TYPE_UINT64,
			PARM_OPT,	128 * ZBX_KIBIBYTE,	__UINT64_C(2) * ZBX_GIBIBYTE},
		{"HousekeepingFrequency",	&CONFIG_HOUSEKEEPING_FREQUENCY,		TYPE_INT,
			PARM_OPT,	1,			24},
		{"ProxyLocalBuffer",		&CONFIG_PROXY_LOCAL_BUFFER,		TYPE_INT,
			PARM_OPT,	0,			720},
		{"ProxyOfflineBuffer",		&CONFIG_PROXY_OFFLINE_BUFFER,		TYPE_INT,
			PARM_OPT,	1,			720},
		{"HeartbeatFrequency",		&CONFIG_HEARTBEAT_FREQUENCY,		TYPE_INT,
			PARM_OPT,	0,			SEC_PER_HOUR},
		{"ConfigFrequency",		&CONFIG_PROXYCONFIG_FREQUENCY,		TYPE_INT,
			PARM_OPT,	1,			SEC_PER_WEEK},
		{"DataSenderFrequency",		&CONFIG_PROXYDATA_FREQUENCY,		TYPE_INT,
			PARM_OPT,	1,			SEC_PER_HOUR},
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
		{"DebugLevel",			&CONFIG_LOG_LEVEL,			TYPE_INT,
			PARM_OPT,	0,			4},
		{"PidFile",			&CONFIG_PID_FILE,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"LogFile",			&CONFIG_LOG_FILE,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"LogFileSize",			&CONFIG_LOG_FILE_SIZE,			TYPE_INT,
			PARM_OPT,	0,			1024},
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
		{NULL}
	};

	parse_cfg_file(CONFIG_FILE, cfg, ZBX_CFG_FILE_REQUIRED, ZBX_CFG_STRICT);

	zbx_set_defaults();

	zbx_validate_config();
}

#ifdef HAVE_SIGQUEUE
static int	set_enable_latency(int value)
{
	if (0 == value)
	{
		if (value == enable_latency)
		{
			zabbix_log(LOG_LEVEL_WARNING, "latency mode already disabled");
			return FAIL;
		}

		zabbix_log(LOG_LEVEL_WARNING, "disabled latency mode");
	}
	else if (1 == value)
	{
		if (value == enable_latency)	/* enabling once again re-reads latency config */
			return SUCCEED;

		zabbix_log(LOG_LEVEL_WARNING, "enabled latency mode");
	}
	else
		THIS_SHOULD_NEVER_HAPPEN;

	enable_latency = value;

	return SUCCEED;
}

static char	*read_latency_config(char *err, size_t err_len)
{
	char		*filename = "/opt/zabbix/etc/latency.conf", *encoding = "UTF-8", read_buf[MAX_BUFFER_LEN],
			*contents = NULL;
	size_t          contents_alloc = 512, contents_offset = 0;
	struct stat     stat_buf;
	double          ts;
	int             nbytes, flen = 0, f = -1, ret = FAIL;

	ts = zbx_time();

	if (0 != zbx_stat(filename, &stat_buf))
	{
		zbx_snprintf(err, err_len, "%s: %s", filename, zbx_strerror(errno));
		goto out;
	}

	if (CONFIG_TIMEOUT < zbx_time() - ts)
	{
		zbx_snprintf(err, err_len, "%s: timeout while trying to access", filename);
		goto out;
	}

	if (-1 == (f = zbx_open(filename, O_RDONLY)))
	{
		zbx_snprintf(err, err_len, "%s: %s", filename, zbx_strerror(errno));
		goto out;
	}

	if (CONFIG_TIMEOUT < zbx_time() - ts)
	{
		zbx_snprintf(err, err_len, "%s: timeout while trying to read", filename);
		goto out;
	}

	contents = zbx_malloc(contents, contents_alloc);

	while (0 < (nbytes = zbx_read(f, read_buf, sizeof(read_buf), encoding)))
	{
		if (CONFIG_TIMEOUT < zbx_time() - ts)
		{
			zbx_snprintf(err, err_len, "%s: timeout while trying to read", filename);
			zbx_free(contents);
			goto out;
		}

		zbx_strncpy_alloc(&contents, &contents_alloc, &contents_offset, read_buf, nbytes);
	}

	if (-1 == nbytes)	/* error occurred */
	{
		zbx_snprintf(err, err_len, "%s: %s", filename, zbx_strerror(errno));
		zbx_free(contents);
		goto out;
	}

	if (0 == contents_offset)	/* empty file */
	{
		zbx_snprintf(err, err_len, "%s: the file is empty", filename);
		zbx_free(contents);
		goto out;
	}
out:
	if (-1 != f)
		close(f);

	return contents;
}

static void	destroy_latency_config()
{
	zbx_tld_config_t	*tldc;
	int			i;

	for (i = 0; i < latency_config.tlds.values_num; i++)
	{
		tldc = (zbx_tld_config_t *)latency_config.tlds.values[i];

		zbx_free(tldc->tld);

		zbx_free(tldc);
	}

	zbx_vector_ptr_destroy(&latency_config.tlds);

	latency_config.delay = 0;
}

static int	tld_compare_func(const void *d1, const void *d2)
{
	return strcmp((*(const zbx_tld_config_t **)d1)->tld, (*(const zbx_tld_config_t **)d2)->tld);
}

static int	get_latency_config()
{
	char			err[256], tmp[32], *contents = NULL;
	struct zbx_json_parse	jp, jp_tlds, jp_row;
	const char		*p = NULL;
	int			delay, ret = FAIL;

	if (NULL == (contents = read_latency_config(err, sizeof(err))))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot read latency configuration: %s", err);
		goto out;
	}

	if (0 != latency_config.delay)
		destroy_latency_config();

	if (SUCCEED != (ret = zbx_json_open(contents, &jp)))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot read latency configuration: invalid json");
		goto out;
	}

	if (SUCCEED != (ret = zbx_json_brackets_by_name(&jp, "tlds", &jp_tlds)))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot read latency configuration: \"tlds\" tag is missing");
		goto out;
	}

	if (SUCCEED == zbx_json_object_is_empty(&jp_tlds))
	{
		ret = FAIL;
		zabbix_log(LOG_LEVEL_ERR, "cannot read latency configuration: \"tlds\" tag is missing");
		goto out;
	}

	if (SUCCEED != (ret = zbx_json_value_by_name(&jp, "delay", tmp, sizeof(tmp))))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot read latency configuration: \"delay\" tag is missing");
		goto out;
	}

	latency_config.delay = atoi(tmp);

	if (1 > latency_config.delay)
	{
		ret = FAIL;
		zabbix_log(LOG_LEVEL_ERR, "cannot read latency configuration: invalid \"delay\": %s", tmp);
		goto out;
	}

	zbx_vector_ptr_create(&latency_config.tlds);

	zabbix_log(LOG_LEVEL_WARNING, "delay: %d", latency_config.delay);

	while (NULL != (p = zbx_json_next(&jp_tlds, p)))
	{
		zbx_tld_config_t	*tldc;
		struct zbx_json_parse	jp_services;
		const char		*p2 = NULL;
		char			tld[128], service[32];

		if (SUCCEED != (ret = zbx_json_brackets_open(p, &jp_row)))
		{
			zabbix_log(LOG_LEVEL_ERR, "cannot read latency configuration: %s", zbx_json_strerror());
			goto out;
		}

		if (SUCCEED != (ret = zbx_json_value_by_name(&jp_row, "tld", tld, sizeof(tld))))
		{
			zabbix_log(LOG_LEVEL_ERR, "cannot read latency configuration: \"tld\" tag is missing");
			goto out;
		}

		if (SUCCEED != (ret = zbx_json_brackets_by_name(&jp_row, "services", &jp_services)))
		{
			zabbix_log(LOG_LEVEL_ERR, "cannot read latency configuration:"
					" \"services\" tag of TLD \"%s\" is missing", tld);
			goto out;
		}

		tldc = zbx_malloc(NULL, sizeof(*tldc));
		tldc->tld = zbx_strdup(NULL, tld);
		tldc->services = 0;

		while (NULL != (p2 = zbx_json_next_value(&jp_services, p2, service, sizeof(service), NULL)))
		{
			if (0 == strcmp(service, "dns"))
				tldc->services |= ZBX_TLD_SERVICE_DNS;
			else if (0 == strcmp(service, "rdds"))
				tldc->services |= ZBX_TLD_SERVICE_RDDS;
			else if (0 == strcmp(service, "epp"))
				tldc->services |= ZBX_TLD_SERVICE_EPP;
			else
				zabbix_log(LOG_LEVEL_WARNING, "error in latency configuration:"
						" TLD \"%s\" has unknown service: \"%s\"", tld,
						service);
		}

		if (0 == tldc->services)
		{
			ret = FAIL;
			zabbix_log(LOG_LEVEL_ERR, "cannot read latency configuration:"
					" TLD \"%s\" doesn't have any known services listed", tld);
			goto out;
		}

		zbx_vector_ptr_append(&latency_config.tlds, tldc);

		zabbix_log(LOG_LEVEL_WARNING, "tld: %s (dns:%s rdds:%s epp:%s)", tldc->tld,
				(ZBX_TLD_SERVICE_DNS & tldc->services) ? "on" : "off",
				(ZBX_TLD_SERVICE_RDDS & tldc->services) ? "on" : "off",
				(ZBX_TLD_SERVICE_EPP & tldc->services) ? "on" : "off");
	}
out:
	zbx_free(contents);

	if (SUCCEED != ret)
		destroy_latency_config();
	else
		zbx_vector_ptr_sort(&latency_config.tlds,  tld_compare_func);

	return ret;
}

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
		case ZBX_TASK_LATENCY_ENABLE:
			if (ZBX_PROCESS_TYPE_HISTSYNCER == process_type || ZBX_PROCESS_TYPE_POLLER == process_type ||
					ZBX_PROCESS_TYPE_TRAPPER == process_type)
			{
				int	i;

				if (SUCCEED != get_latency_config())
					break;

				set_enable_latency(1);
			}

			break;
		case ZBX_TASK_LATENCY_DISABLE:
			if (ZBX_PROCESS_TYPE_HISTSYNCER == process_type || ZBX_PROCESS_TYPE_POLLER == process_type ||
					ZBX_PROCESS_TYPE_TRAPPER == process_type)
			{
				if (0 != latency_config.delay)
					destroy_latency_config();

				set_enable_latency(0);
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
 * Purpose: executes proxy processes                                          *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 ******************************************************************************/
int	main(int argc, char **argv)
{
	zbx_task_t	task = ZBX_TASK_START;
	char		ch;

	latency_config.delay = 0;

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
				else if (0 == strcmp(zbx_optarg, ZBX_LATENCY_ENABLE))
					task = ZBX_TASK_LATENCY_ENABLE;
				else if (0 == strcmp(zbx_optarg, ZBX_LATENCY_DISABLE))
					task = ZBX_TASK_LATENCY_DISABLE;
				else
				{
					printf("invalid runtime control option: %s\n", zbx_optarg);
					exit(EXIT_FAILURE);
				}
				break;
			case 'r':
				if (SUCCEED != zbx_read_stdin("Enter a passphrase for EPP data encryption: ",
						epp_passphrase, sizeof(epp_passphrase), NULL, 0))
				{
					printf("an error occured while requesting EPP passphrase\n");
					exit(EXIT_FAILURE);
				}
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
	}

	if (NULL == CONFIG_FILE)
		CONFIG_FILE = zbx_strdup(CONFIG_FILE, SYSCONFDIR "/zabbix_proxy.conf");

	/* required for simple checks */
	init_metrics();

	zbx_load_config();

	if (ZBX_TASK_CONFIG_CACHE_RELOAD == task)
		exit(SUCCEED == zbx_sigusr_send(ZBX_TASK_CONFIG_CACHE_RELOAD) ? EXIT_SUCCESS : EXIT_FAILURE);
	else if (ZBX_TASK_LATENCY_ENABLE == task)
		exit(SUCCEED == zbx_sigusr_send(ZBX_TASK_LATENCY_ENABLE) ? EXIT_SUCCESS : EXIT_FAILURE);
	else if (ZBX_TASK_LATENCY_DISABLE == task)
		exit(SUCCEED == zbx_sigusr_send(ZBX_TASK_LATENCY_DISABLE) ? EXIT_SUCCESS : EXIT_FAILURE);

#ifdef HAVE_OPENIPMI
	init_ipmi_handler();
#endif

	return daemon_start(CONFIG_ALLOW_ROOT);
}

int	MAIN_ZABBIX_ENTRY()
{
	pid_t		pid;
	zbx_sock_t	listen_sock;
	int		i, proxy_num = 0, proxy_count = 0;

	if (NULL == CONFIG_LOG_FILE || '\0' == *CONFIG_LOG_FILE)
		zabbix_open_log(LOG_TYPE_SYSLOG, CONFIG_LOG_LEVEL, NULL);
	else
		zabbix_open_log(LOG_TYPE_FILE, CONFIG_LOG_LEVEL, CONFIG_LOG_FILE);

#ifdef	HAVE_SNMP
#	define SNMP_FEATURE_STATUS 	"YES"
#else
#	define SNMP_FEATURE_STATUS 	" NO"
#endif
#ifdef	HAVE_OPENIPMI
#	define IPMI_FEATURE_STATUS 	"YES"
#else
#	define IPMI_FEATURE_STATUS 	" NO"
#endif
#ifdef	HAVE_LIBCURL
#	define LIBCURL_FEATURE_STATUS	"YES"
#else
#	define LIBCURL_FEATURE_STATUS	" NO"
#endif
#ifdef	HAVE_ODBC
#	define ODBC_FEATURE_STATUS 	"YES"
#else
#	define ODBC_FEATURE_STATUS 	" NO"
#endif
#ifdef	HAVE_SSH2
#	define SSH2_FEATURE_STATUS 	"YES"
#else
#	define SSH2_FEATURE_STATUS 	" NO"
#endif
#ifdef	HAVE_IPV6
#	define IPV6_FEATURE_STATUS 	"YES"
#else
#	define IPV6_FEATURE_STATUS 	" NO"
#endif

	zabbix_log(LOG_LEVEL_INFORMATION, "Starting Zabbix Proxy (%s) [%s]. Zabbix %s (revision %s).",
			ZBX_PROXYMODE_PASSIVE == CONFIG_PROXYMODE ? "passive" : "active",
			CONFIG_HOSTNAME, ZABBIX_VERSION, ZABBIX_REVISION);

	zabbix_log(LOG_LEVEL_INFORMATION, "**** Enabled features ****");
	zabbix_log(LOG_LEVEL_INFORMATION, "SNMP monitoring:       " SNMP_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "IPMI monitoring:       " IPMI_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "WEB monitoring:        " LIBCURL_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "ODBC:                  " ODBC_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "SSH2 support:          " SSH2_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "IPv6 support:          " IPV6_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "**************************");

	DBinit();

	init_database_cache();
	init_configuration_cache();
	init_selfmon_collector();

	DBconnect(ZBX_DB_CONNECT_NORMAL);
	DCsync_configuration();
	DBclose();

	threads_num = CONFIG_CONFSYNCER_FORKS + CONFIG_HEARTBEAT_FORKS + CONFIG_DATASENDER_FORKS
			+ CONFIG_POLLER_FORKS + CONFIG_UNREACHABLE_POLLER_FORKS + CONFIG_TRAPPER_FORKS
			+ CONFIG_PINGER_FORKS + CONFIG_HOUSEKEEPER_FORKS + CONFIG_HTTPPOLLER_FORKS
			+ CONFIG_DISCOVERER_FORKS + CONFIG_HISTSYNCER_FORKS + CONFIG_IPMIPOLLER_FORKS
			+ CONFIG_JAVAPOLLER_FORKS + CONFIG_SNMPTRAPPER_FORKS + CONFIG_SELFMON_FORKS;
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
			proxy_num = i + 1;	/* child processes are numbered starting from 1 */
			break;
		}
		else
			threads[i] = pid;
	}

	if (0 == proxy_num)
	{
		/* does not need EPP passphrase */
		if ('\0' != *epp_passphrase)
			memset(epp_passphrase, 0, strlen(epp_passphrase));

		zabbix_log(LOG_LEVEL_INFORMATION, "proxy #0 started [main process]");

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
	else if (proxy_num <= (proxy_count += CONFIG_CONFSYNCER_FORKS))
	{
		/* !!! configuration syncer must be proxy #1 - child_signal_handler() uses threads[0] !!! */

		/* does not need EPP passphrase */
		if ('\0' != *epp_passphrase)
			memset(epp_passphrase, 0, strlen(epp_passphrase));

		INIT_PROXY(ZBX_PROCESS_TYPE_CONFSYNCER, CONFIG_CONFSYNCER_FORKS);

		main_proxyconfig_loop();
	}
	else if (proxy_num <= (proxy_count += CONFIG_HEARTBEAT_FORKS))
	{
		/* does not need EPP passphrase */
		if ('\0' != *epp_passphrase)
			memset(epp_passphrase, 0, strlen(epp_passphrase));

		INIT_PROXY(ZBX_PROCESS_TYPE_HEARTBEAT, CONFIG_HEARTBEAT_FORKS);

		main_heart_loop();
	}
	else if (proxy_num <= (proxy_count += CONFIG_DATASENDER_FORKS))
	{
		/* does not need EPP passphrase */
		if ('\0' != *epp_passphrase)
			memset(epp_passphrase, 0, strlen(epp_passphrase));

		INIT_PROXY(ZBX_PROCESS_TYPE_DATASENDER, CONFIG_DATASENDER_FORKS);

		main_datasender_loop();
	}
	else if (proxy_num <= (proxy_count += CONFIG_POLLER_FORKS))
	{
#ifdef HAVE_SNMP
		init_snmp("zabbix_proxy");
#endif

		INIT_PROXY(ZBX_PROCESS_TYPE_POLLER, CONFIG_POLLER_FORKS);

		main_poller_loop(ZBX_POLLER_TYPE_NORMAL);
	}
	else if (proxy_num <= (proxy_count += CONFIG_UNREACHABLE_POLLER_FORKS))
	{
#ifdef HAVE_SNMP
		init_snmp("zabbix_proxy");
#endif
		/* does not need EPP passphrase */
		if ('\0' != *epp_passphrase)
			memset(epp_passphrase, 0, strlen(epp_passphrase));

		INIT_PROXY(ZBX_PROCESS_TYPE_UNREACHABLE, CONFIG_UNREACHABLE_POLLER_FORKS);

		main_poller_loop(ZBX_POLLER_TYPE_UNREACHABLE);
	}
	else if (proxy_num <= (proxy_count += CONFIG_TRAPPER_FORKS))
	{
		/* does not need EPP passphrase */
		if ('\0' != *epp_passphrase)
			memset(epp_passphrase, 0, strlen(epp_passphrase));

		INIT_PROXY(ZBX_PROCESS_TYPE_TRAPPER, CONFIG_TRAPPER_FORKS);

		main_trapper_loop(&listen_sock);
	}
	else if (proxy_num <= (proxy_count += CONFIG_PINGER_FORKS))
	{
		/* does not need EPP passphrase */
		if ('\0' != *epp_passphrase)
			memset(epp_passphrase, 0, strlen(epp_passphrase));

		INIT_PROXY(ZBX_PROCESS_TYPE_PINGER, CONFIG_PINGER_FORKS);

		main_pinger_loop();
	}
	else if (proxy_num <= (proxy_count += CONFIG_HOUSEKEEPER_FORKS))
	{
		/* does not need EPP passphrase */
		if ('\0' != *epp_passphrase)
			memset(epp_passphrase, 0, strlen(epp_passphrase));

		INIT_PROXY(ZBX_PROCESS_TYPE_HOUSEKEEPER, CONFIG_HOUSEKEEPER_FORKS);

		main_housekeeper_loop();
	}
	else if (proxy_num <= (proxy_count += CONFIG_HTTPPOLLER_FORKS))
	{
		/* does not need EPP passphrase */
		if ('\0' != *epp_passphrase)
			memset(epp_passphrase, 0, strlen(epp_passphrase));

		INIT_PROXY(ZBX_PROCESS_TYPE_HTTPPOLLER, CONFIG_HTTPPOLLER_FORKS);

		main_httppoller_loop();
	}
	else if (proxy_num <= (proxy_count += CONFIG_DISCOVERER_FORKS))
	{
#ifdef HAVE_SNMP
		init_snmp("zabbix_proxy");
#endif
		/* does not need EPP passphrase */
		if ('\0' != *epp_passphrase)
			memset(epp_passphrase, 0, strlen(epp_passphrase));

		INIT_PROXY(ZBX_PROCESS_TYPE_DISCOVERER, CONFIG_DISCOVERER_FORKS);

		main_discoverer_loop();
	}
	else if (proxy_num <= (proxy_count += CONFIG_HISTSYNCER_FORKS))
	{
		/* does not need EPP passphrase */
		if ('\0' != *epp_passphrase)
			memset(epp_passphrase, 0, strlen(epp_passphrase));

		INIT_PROXY(ZBX_PROCESS_TYPE_HISTSYNCER, CONFIG_HISTSYNCER_FORKS);

		main_dbsyncer_loop();
	}
	else if (proxy_num <= (proxy_count += CONFIG_IPMIPOLLER_FORKS))
	{
		/* does not need EPP passphrase */
		if ('\0' != *epp_passphrase)
			memset(epp_passphrase, 0, strlen(epp_passphrase));

		INIT_PROXY(ZBX_PROCESS_TYPE_IPMIPOLLER, CONFIG_IPMIPOLLER_FORKS);

		main_poller_loop(ZBX_POLLER_TYPE_IPMI);
	}
	else if (proxy_num <= (proxy_count += CONFIG_JAVAPOLLER_FORKS))
	{
		/* does not need EPP passphrase */
		if ('\0' != *epp_passphrase)
			memset(epp_passphrase, 0, strlen(epp_passphrase));

		INIT_PROXY(ZBX_PROCESS_TYPE_JAVAPOLLER, CONFIG_JAVAPOLLER_FORKS);

		main_poller_loop(ZBX_POLLER_TYPE_JAVA);
	}
	else if (proxy_num <= (proxy_count += CONFIG_SNMPTRAPPER_FORKS))
	{
		/* does not need EPP passphrase */
		if ('\0' != *epp_passphrase)
			memset(epp_passphrase, 0, strlen(epp_passphrase));

		INIT_PROXY(ZBX_PROCESS_TYPE_SNMPTRAPPER, CONFIG_SNMPTRAPPER_FORKS);

		main_snmptrapper_loop();
	}
	else if (proxy_num <= (proxy_count += CONFIG_SELFMON_FORKS))
	{
		/* does not need EPP passphrase */
		if ('\0' != *epp_passphrase)
			memset(epp_passphrase, 0, strlen(epp_passphrase));

		INIT_PROXY(ZBX_PROCESS_TYPE_SELFMON, CONFIG_SELFMON_FORKS);

		main_selfmon_loop();
	}

	return SUCCEED;
}

void	zbx_on_exit()
{
	zabbix_log(LOG_LEVEL_DEBUG, "zbx_on_exit() called");

	if (NULL != threads)
	{
		int		i;
		sigset_t	set;

		/* ignore SIGCHLD signals in order for zbx_sleep() to work  */
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
	DBclose();

#ifdef HAVE_OPENIPMI
	free_ipmi_handler();
#endif

#ifdef HAVE_SQLITE3
	zbx_remove_sqlite3_mutex();
#endif

	free_selfmon_collector();

	zabbix_log(LOG_LEVEL_INFORMATION, "Zabbix Proxy stopped. Zabbix %s (revision %s).",
			ZABBIX_VERSION, ZABBIX_REVISION);

	zabbix_close_log();

	exit(SUCCEED);
}
