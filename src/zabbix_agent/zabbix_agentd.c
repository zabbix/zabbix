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

#include "agent_conf/agent_conf.h"

#include "zbxlog.h"
#include "zbxsysinfo.h"
#include "zbxcomms.h"
#include "zbxexpr.h"
#include "zbxgetopt.h"
#include "zbxip.h"
#include "zbxstr.h"
#include "zbxthreads.h"
#include "zbx_rtc_constants.h"

static char	*CONFIG_PID_FILE = NULL;

static char	*zbx_config_hosts_allowed	= NULL;
ZBX_GET_CONFIG_VAR2(char *, const char *, zbx_config_hostnames, NULL)
char	*CONFIG_HOSTNAME_ITEM		= NULL;
ZBX_GET_CONFIG_VAR2(char *, const char *, zbx_config_host_metadata, NULL)
ZBX_GET_CONFIG_VAR2(char *, const char *, zbx_config_host_metadata_item, NULL)
static char	*zbx_config_host_interface = NULL;
static char	*zbx_config_host_interface_item = NULL;
ZBX_GET_CONFIG_VAR2(ZBX_THREAD_LOCAL char *, const char *, zbx_config_hostname, NULL)
ZBX_GET_CONFIG_VAR(int, zbx_config_enable_remote_commands, 1)
ZBX_GET_CONFIG_VAR(int, zbx_config_log_remote_commands, 0)
ZBX_GET_CONFIG_VAR(int, zbx_config_unsafe_user_parameters, 0)

static int	zbx_config_listen_port			= ZBX_DEFAULT_AGENT_PORT;
static char	*zbx_config_listen_ip			= NULL;
static int	zbx_config_refresh_active_checks	= 5;

ZBX_GET_CONFIG_VAR2(char*, const char *, zbx_config_source_ip, NULL)

int	CONFIG_LOG_LEVEL		= LOG_LEVEL_WARNING;

int	zbx_config_buffer_size		= 100;
int	zbx_config_buffer_send		= 5;

static int	zbx_config_max_lines_per_second	= 20;
static int	zbx_config_eventlog_max_lines_per_second = 20;

char	*CONFIG_LOAD_MODULE_PATH	= NULL;

char	**CONFIG_ALIASES		= NULL;
char	**CONFIG_LOAD_MODULE		= NULL;
char	**zbx_config_user_parameters	= NULL;
char	*CONFIG_USER_PARAMETER_DIR	= NULL;
#if defined(_WINDOWS)
char	**CONFIG_PERF_COUNTERS		= NULL;
char	**CONFIG_PERF_COUNTERS_EN	= NULL;
#endif

char	*CONFIG_USER			= NULL;

/* SSL parameters */
char	*CONFIG_SSL_CA_LOCATION;
char	*CONFIG_SSL_CERT_LOCATION;
char	*CONFIG_SSL_KEY_LOCATION;

static zbx_config_tls_t	*zbx_config_tls = NULL;

int	CONFIG_TCP_MAX_BACKLOG_SIZE	= SOMAXCONN;

int	zbx_config_heartbeat_frequency	= 60;

#ifndef _WINDOWS
#	include "../libs/zbxnix/control.h"
#	include "zbxmodules.h"
#endif

#ifdef _WINDOWS
#	include "zbxwin32.h"
#else
#	include "zbxnix.h"
#endif
#include "active_checks/active_checks.h"
#include "listener/listener.h"

#if defined(ZABBIX_SERVICE)
#	include "zbxwinservice.h"
#elif defined(ZABBIX_DAEMON)
#	include "zbxnix.h"
#endif

#include "zbxcrypto.h"

const char	*progname = NULL;

/* application TITLE */
const char	title_message[] = "zabbix_agentd"
#if defined(_WIN64)
				" Win64"
#elif defined(_WIN32)
				" Win32"
#endif
#if defined(ZABBIX_SERVICE)
				" (service)"
#elif defined(ZABBIX_DAEMON)
				" (daemon)"
#endif
	;
/* end of application TITLE */

const char	syslog_app_name[] = "zabbix_agentd";

/* application USAGE message */
const char	*usage_message[] = {
	"[-c config-file]", NULL,
	"[-c config-file]", "-p", NULL,
	"[-c config-file]", "-t item-key", NULL,
#ifdef _WINDOWS
	"[-c config-file]", "-i", "[-m]", NULL,
	"[-c config-file]", "-d", "[-m]", NULL,
	"[-c config-file]", "-s", "[-m]", NULL,
	"[-c config-file]", "-x", "[-m]", NULL,
#else
	"[-c config-file]", "-R runtime-option", NULL,
#endif
	"-h", NULL,
	"-V", NULL,
	NULL	/* end of text */
};
/* end of application USAGE message */

/* application HELP message */
const char	*help_message[] = {
	"A Zabbix daemon for monitoring of various server parameters.",
	"",
	"Options:",
	"  -c --config config-file        Path to the configuration file",
#ifdef _WINDOWS
	"                                 (default: \"{DEFAULT_CONFIG_FILE}\")",
#else
	"                                 (default: \"" DEFAULT_CONFIG_FILE "\")",
#endif
	"  -f --foreground                Run Zabbix agent in foreground",
	"  -p --print                     Print known items and exit",
	"  -t --test item-key             Test specified item and exit",
#ifdef _WINDOWS
	"  -m --multiple-agents           For -i -d -s -x functions service name will",
	"                                 include Hostname parameter specified in",
	"                                 configuration file",
	"Functions:",
	"",
	"  -i --install                   Install Zabbix agent as service",
	"  -d --uninstall                 Uninstall Zabbix agent from service",

	"  -s --start                     Start Zabbix agent service",
	"  -x --stop                      Stop Zabbix agent service",
#else
	"  -R --runtime-control runtime-option   Perform administrative functions",
	"",
	"    Runtime control options:",
	"      " ZBX_USER_PARAMETERS_RELOAD "       Reload user parameters from the configuration file",
	"      " ZBX_LOG_LEVEL_INCREASE "=target  Increase log level, affects all processes if",
	"                                 target is not specified",
	"      " ZBX_LOG_LEVEL_DECREASE "=target  Decrease log level, affects all processes if",
	"                                 target is not specified",
	"",
	"      Log level control targets:",
	"        process-type             All processes of specified type (active checks,",
	"                                 collector, listener)",
	"        process-type,N           Process type and number (e.g., listener,3)",
	"        pid                      Process identifier, up to 65535. For larger",
	"                                 values specify target as \"process-type,N\"",
#endif
	"",
	"  -h --help                      Display this help message",
	"  -V --version                   Display version number",
	"",
#ifndef _WINDOWS
	"Default loadable module location:",
	"  LoadModulePath                 \"" DEFAULT_LOAD_MODULE_PATH "\"",
	"",
#endif
#ifdef _WINDOWS
	"Example: zabbix_agentd -c C:\\zabbix\\zabbix_agentd.conf",
#else
	"Example: zabbix_agentd -c /etc/zabbix/zabbix_agentd.conf",
#endif
	NULL	/* end of text */
};
/* end of application HELP message */

/* COMMAND LINE OPTIONS */
static struct zbx_option	longopts[] =
{
	{"config",		1,	NULL,	'c'},
	{"foreground",		0,	NULL,	'f'},
	{"help",		0,	NULL,	'h'},
	{"version",		0,	NULL,	'V'},
	{"print",		0,	NULL,	'p'},
	{"test",		1,	NULL,	't'},
#ifndef _WINDOWS
	{"runtime-control",	1,	NULL,	'R'},
#else
	{"install",		0,	NULL,	'i'},
	{"uninstall",		0,	NULL,	'd'},

	{"start",		0,	NULL,	's'},
	{"stop",		0,	NULL,	'x'},

	{"multiple-agents",	0,	NULL,	'm'},
#endif
	{NULL}
};

static char	shortopts[] =
	"c:hVpt:f"
#ifndef _WINDOWS
	"R:"
#else
	"idsxm"
#endif
	;
/* end of COMMAND LINE OPTIONS */

static char		*TEST_METRIC = NULL;
int			threads_num = 0;
ZBX_THREAD_HANDLE	*threads = NULL;
static int		*threads_flags;

unsigned char  program_type = ZBX_PROGRAM_TYPE_AGENTD;

static unsigned char	get_program_type(void)
{
	return program_type;
}

#if defined(_WINDOWS) || defined(__MINGW32__)
static const char	*get_progname(void)
{
	return progname;
}
#endif

ZBX_GET_CONFIG_VAR(int, zbx_config_timeout, 3)

static zbx_thread_activechk_args	*config_active_args = NULL;

int	CONFIG_FORKS[ZBX_PROCESS_TYPE_COUNT] = {
	0, /* ZBX_PROCESS_TYPE_POLLER */
	0, /* ZBX_PROCESS_TYPE_UNREACHABLE */
	0, /* ZBX_PROCESS_TYPE_IPMIPOLLER */
	0, /* ZBX_PROCESS_TYPE_PINGER */
	0, /* ZBX_PROCESS_TYPE_JAVAPOLLER */
	0, /* ZBX_PROCESS_TYPE_HTTPPOLLER */
	0, /* ZBX_PROCESS_TYPE_TRAPPER */
	0, /* ZBX_PROCESS_TYPE_SNMPTRAPPER */
	0, /* ZBX_PROCESS_TYPE_PROXYPOLLER */
	0, /* ZBX_PROCESS_TYPE_ESCALATOR */
	0, /* ZBX_PROCESS_TYPE_HISTSYNCER */
	0, /* ZBX_PROCESS_TYPE_DISCOVERER */
	0, /* ZBX_PROCESS_TYPE_ALERTER */
	0, /* ZBX_PROCESS_TYPE_TIMER */
	0, /* ZBX_PROCESS_TYPE_HOUSEKEEPER */
	0, /* ZBX_PROCESS_TYPE_DATASENDER */
	0, /* ZBX_PROCESS_TYPE_CONFSYNCER */
	0, /* ZBX_PROCESS_TYPE_SELFMON */
	0, /* ZBX_PROCESS_TYPE_VMWARE */
	1, /* ZBX_PROCESS_TYPE_COLLECTOR */
	3, /* ZBX_PROCESS_TYPE_LISTENER */
	0, /* ZBX_PROCESS_TYPE_ACTIVE_CHECKS */
	0, /* ZBX_PROCESS_TYPE_TASKMANAGER */
	0, /* ZBX_PROCESS_TYPE_IPMIMANAGER */
	0, /* ZBX_PROCESS_TYPE_ALERTMANAGER */
	0, /* ZBX_PROCESS_TYPE_PREPROCMAN */
	0, /* ZBX_PROCESS_TYPE_PREPROCESSOR */
	0, /* ZBX_PROCESS_TYPE_LLDMANAGER */
	0, /* ZBX_PROCESS_TYPE_LLDWORKER */
	0, /* ZBX_PROCESS_TYPE_ALERTSYNCER */
	0, /* ZBX_PROCESS_TYPE_HISTORYPOLLER */
	0, /* ZBX_PROCESS_TYPE_AVAILMAN */
	0, /* ZBX_PROCESS_TYPE_REPORTMANAGER */
	0, /* ZBX_PROCESS_TYPE_REPORTWRITER */
	0, /* ZBX_PROCESS_TYPE_SERVICEMAN */
	0, /* ZBX_PROCESS_TYPE_TRIGGERHOUSEKEEPER */
	0, /* ZBX_PROCESS_TYPE_ODBCPOLLER */
	0, /* ZBX_PROCESS_TYPE_CONNECTORWORKER*/
	0, /* ZBX_PROCESS_TYPE_HTTPAGENT_POLLER */
	0 /* ZBX_PROCESS_TYPE_AGENT_POLLER */
};

static char	*config_file	= NULL;
static int	config_allow_root	= 0;

static zbx_config_log_t	log_file_cfg	= {NULL, NULL, ZBX_LOG_TYPE_UNDEFINED, 1};

char	*opt = NULL;

#ifdef _WINDOWS
void	zbx_co_uninitialize();
#endif

int	get_process_info_by_thread(int local_server_num, unsigned char *local_process_type, int *local_process_num);
void	zbx_free_service_resources(int ret);

int	get_process_info_by_thread(int local_server_num, unsigned char *local_process_type, int *local_process_num)
{
	int	server_count = 0;

	if (0 == local_server_num)
	{
		/* fail if the main process is queried */
		return FAIL;
	}
	else if (local_server_num <= (server_count += CONFIG_FORKS[ZBX_PROCESS_TYPE_COLLECTOR]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_COLLECTOR;
		*local_process_num = local_server_num - server_count + CONFIG_FORKS[ZBX_PROCESS_TYPE_COLLECTOR];
	}
	else if (local_server_num <= (server_count += CONFIG_FORKS[ZBX_PROCESS_TYPE_LISTENER]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_LISTENER;
		*local_process_num = local_server_num - server_count + CONFIG_FORKS[ZBX_PROCESS_TYPE_LISTENER];

	}
	else if (local_server_num <= (server_count += CONFIG_FORKS[ZBX_PROCESS_TYPE_ACTIVE_CHECKS]))
	{
		*local_process_type = ZBX_PROCESS_TYPE_ACTIVE_CHECKS;
		*local_process_num = local_server_num - server_count + CONFIG_FORKS[ZBX_PROCESS_TYPE_ACTIVE_CHECKS];
	}
	else
		return FAIL;

	return SUCCEED;
}

static int	parse_commandline(int argc, char **argv, ZBX_TASK_EX *t)
{
	int		i, ret = SUCCEED;
	char		ch;
#ifdef _WINDOWS
	unsigned int	opt_mask = 0;
#endif
	unsigned short	opt_count[256] = {0};

	/* see description of 'optarg' in 'man 3 getopt' */
	char		*zbx_optarg = NULL;

	/* see description of 'optind' in 'man 3 getopt' */
	int		zbx_optind = 0;

	t->task = ZBX_TASK_START;

	/* parse the command-line */
	while ((char)EOF != (ch = (char)zbx_getopt_long(argc, argv, shortopts, longopts, NULL, &zbx_optarg,
			&zbx_optind)))
	{
		opt_count[(unsigned char)ch]++;

		switch (ch)
		{
			case 'c':
				if (NULL == config_file)
					config_file = strdup(zbx_optarg);
				break;
#ifndef _WINDOWS
			case 'R':
				if (SUCCEED != zbx_parse_rtc_options(zbx_optarg, &t->data))
					exit(EXIT_FAILURE);

				t->task = ZBX_TASK_RUNTIME_CONTROL;
				break;
#endif
			case 'h':
				t->task = ZBX_TASK_SHOW_HELP;
#ifdef _WINDOWS
				goto cf_out;
#else
				goto out;
#endif

			case 'V':
				t->task = ZBX_TASK_SHOW_VERSION;
				goto out;
			case 'p':
				if (ZBX_TASK_START == t->task)
					t->task = ZBX_TASK_PRINT_SUPPORTED;
				break;
			case 't':
				if (ZBX_TASK_START == t->task)
				{
					t->task = ZBX_TASK_TEST_METRIC;
					TEST_METRIC = strdup(zbx_optarg);
				}
				break;
			case 'f':
				t->flags |= ZBX_TASK_FLAG_FOREGROUND;
				break;
#ifdef _WINDOWS
			case 'i':
				t->task = ZBX_TASK_INSTALL_SERVICE;
				break;
			case 'd':
				t->task = ZBX_TASK_UNINSTALL_SERVICE;
				break;
			case 's':
				t->task = ZBX_TASK_START_SERVICE;
				break;
			case 'x':
				t->task = ZBX_TASK_STOP_SERVICE;
				break;
			case 'm':
				t->flags |= ZBX_TASK_FLAG_MULTIPLE_AGENTS;
				break;
#endif
			default:
				t->task = ZBX_TASK_SHOW_USAGE;
				goto out;
		}
	}

#ifdef _WINDOWS
	switch (t->task)
	{
		case ZBX_TASK_START:
			break;
		case ZBX_TASK_INSTALL_SERVICE:
		case ZBX_TASK_UNINSTALL_SERVICE:
		case ZBX_TASK_START_SERVICE:
		case ZBX_TASK_STOP_SERVICE:
			if (0 != (t->flags & ZBX_TASK_FLAG_FOREGROUND))
			{
				zbx_error("foreground option cannot be used with Zabbix agent services");
				ret = FAIL;
				goto out;
			}
			break;
		default:
			if (0 != (t->flags & ZBX_TASK_FLAG_MULTIPLE_AGENTS))
			{
				zbx_error("multiple agents option can be used only with Zabbix agent services");
				ret = FAIL;
				goto out;
			}
	}
#endif

	/* every option may be specified only once */

	for (i = 0; NULL != longopts[i].name; i++)
	{
		ch = (char)longopts[i].val;

		if ('h' == ch || 'V' == ch)
			continue;

		if (1 < opt_count[(unsigned char)ch])
		{
			if (NULL == strchr(shortopts, ch))
				zbx_error("option \"--%s\" specified multiple times", longopts[i].name);
			else
				zbx_error("option \"-%c\" or \"--%s\" specified multiple times", ch, longopts[i].name);

			ret = FAIL;
		}
	}

	if (FAIL == ret)
		goto out;

#ifdef _WINDOWS
	/* check for mutually exclusive options */
	/* Allowed option combinations.		*/
	/* Option 'c' is always optional.	*/
	/*   p  t  i  d  s  x  m    opt_mask	*/
	/* ---------------------    --------	*/
	/*   -  -  -  -  -  -  -	0x00	*/
	/*   p  -  -  -  -  -  -	0x40	*/
	/*   -  t  -  -  -  -  -	0x20	*/
	/*   -  -  i  -  -  -  -	0x10	*/
	/*   -  -  -  d  -  -  -	0x08	*/
	/*   -  -  -  -  s  -  -	0x04	*/
	/*   -  -  -  -  -  x  -	0x02	*/
	/*   -  -  i  -  -  -  m	0x11	*/
	/*   -  -  -  d  -  -  m	0x09	*/
	/*   -  -  -  -  s  -  m	0x05	*/
	/*   -  -  -  -  -  x  m	0x03	*/
	/*   -  -  -  -  -  -  m	0x01 special case required for starting as a service with '-m' option */

	if (0 < opt_count['p'])
		opt_mask |= 0x40;
	if (0 < opt_count['t'])
		opt_mask |= 0x20;
	if (0 < opt_count['i'])
		opt_mask |= 0x10;
	if (0 < opt_count['d'])
		opt_mask |= 0x08;
	if (0 < opt_count['s'])
		opt_mask |= 0x04;
	if (0 < opt_count['x'])
		opt_mask |= 0x02;
	if (0 < opt_count['m'])
		opt_mask |= 0x01;

	switch (opt_mask)
	{
		case 0x00:
		case 0x01:
		case 0x02:
		case 0x03:
		case 0x04:
		case 0x05:
		case 0x08:
		case 0x09:
		case 0x10:
		case 0x11:
		case 0x20:
		case 0x40:
			break;
		default:
			zbx_error("mutually exclusive options used");
			zbx_usage();
			ret = FAIL;
			goto out;
	}
#else
	/* check for mutually exclusive options */
	if (1 < opt_count['p'] + opt_count['t'] + opt_count['R'])
	{
		zbx_error("only one of options \"-p\" or \"--print\", \"-t\" or \"--test\","
				" \"-R\" or \"--runtime-control\" can be used");
		ret = FAIL;
		goto out;
	}
#endif
	/* Parameters which are not option values are invalid. The check relies on zbx_getopt_internal() which */
	/* always permutes command line arguments regardless of POSIXLY_CORRECT environment variable. */
	if (argc > zbx_optind)
	{
		for (i = zbx_optind; i < argc; i++)
			zbx_error("invalid parameter \"%s\"", argv[i]);

		ret = FAIL;
		goto out;
	}

#ifdef _WINDOWS
#define PATH_BUF_LEN	4096
cf_out:
	if (NULL == config_file)
	{
		char	*ptr, *process_path = NULL;
		wchar_t	szProcessName[PATH_BUF_LEN];

		if (0 == GetModuleFileNameEx(GetCurrentProcess(), NULL, szProcessName, ARRSIZE(szProcessName)))
		{
			zbx_error("failed to get Zabbix agent executable file path while initializing default config"
					" path");
			goto skip;
		}

		process_path = zbx_unicode_to_utf8(szProcessName);

		if (NULL == (ptr = get_program_name(process_path)))
		{
			zbx_error("got unexpected Zabbix agent executable file path '%s' while initializing"
					" default config path", ptr);
			goto skip;
		}

		*ptr = '\0';
		config_file = zbx_dsprintf(config_file, "%s%s", process_path, get_program_name(DEFAULT_CONFIG_FILE));
skip:
		zbx_free(process_path);
	}
#undef PATH_BUF_LEN
#endif
	if (NULL == config_file)
		config_file = zbx_strdup(NULL, DEFAULT_CONFIG_FILE);
out:
	if (FAIL == ret)
	{
		zbx_free(TEST_METRIC);
		zbx_free(config_file);
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: set configuration defaults                                        *
 *                                                                            *
 ******************************************************************************/
static void	set_defaults(void)
{
	AGENT_RESULT	result;
	char		**value = NULL;

	if (NULL == zbx_config_hostnames)
	{
		if (NULL == CONFIG_HOSTNAME_ITEM)
			CONFIG_HOSTNAME_ITEM = zbx_strdup(CONFIG_HOSTNAME_ITEM, "system.hostname");

		zbx_init_agent_result(&result);

		if (SUCCEED == zbx_execute_agent_check(CONFIG_HOSTNAME_ITEM, ZBX_PROCESS_LOCAL_COMMAND |
				ZBX_PROCESS_WITH_ALIAS, &result, ZBX_CHECK_TIMEOUT_UNDEFINED) &&
				NULL != (value = ZBX_GET_STR_RESULT(&result)))
		{
			assert(*value);
			zbx_trim_str_list(*value, ',');

			if (NULL == strchr(*value, ',') && ZBX_MAX_HOSTNAME_LEN < strlen(*value))
			{
				(*value)[ZBX_MAX_HOSTNAME_LEN] = '\0';
				zabbix_log(LOG_LEVEL_WARNING, "hostname truncated to [%s])", *value);
			}

			zbx_config_hostnames = zbx_strdup(zbx_config_hostnames, *value);
		}
		else
			zabbix_log(LOG_LEVEL_WARNING, "failed to get system hostname from [%s])", CONFIG_HOSTNAME_ITEM);

		zbx_free_agent_result(&result);
	}
	else if (NULL != CONFIG_HOSTNAME_ITEM)
	{
		zabbix_log(LOG_LEVEL_WARNING, "both Hostname and HostnameItem defined, using [%s]",
				zbx_config_hostnames);
	}

	if (NULL != zbx_config_host_metadata && NULL != zbx_config_host_metadata_item)
	{
		zabbix_log(LOG_LEVEL_WARNING, "both HostMetadata and HostMetadataItem defined, using [%s]",
				zbx_config_host_metadata);
	}

	if (NULL != zbx_config_host_interface && NULL != zbx_config_host_interface_item)
	{
		zabbix_log(LOG_LEVEL_WARNING, "both HostInterface and HostInterfaceItem defined, using [%s]",
				zbx_config_host_interface);
	}

#ifndef _WINDOWS
	if (NULL == CONFIG_LOAD_MODULE_PATH)
		CONFIG_LOAD_MODULE_PATH = zbx_strdup(CONFIG_LOAD_MODULE_PATH, DEFAULT_LOAD_MODULE_PATH);

	if (NULL == CONFIG_PID_FILE)
		CONFIG_PID_FILE = (char *)"/tmp/zabbix_agentd.pid";
#endif
	if (NULL == log_file_cfg.log_type_str)
		log_file_cfg.log_type_str = zbx_strdup(log_file_cfg.log_type_str, ZBX_OPTION_LOGTYPE_FILE);
}

/******************************************************************************
 *                                                                            *
 * Purpose: validate listed host names                                        *
 *                                                                            *
 ******************************************************************************/
static void	zbx_validate_config_hostnames(zbx_vector_str_t *hostnames)
{
	char	*ch_error;
	int	i;

	if (0 == hostnames->values_num)
	{
		zabbix_log(LOG_LEVEL_CRIT, "\"Hostname\" configuration parameter is not defined");
		exit(EXIT_FAILURE);
	}

	for (i = 0; i < hostnames->values_num; i++)
	{
		if (FAIL == zbx_check_hostname(hostnames->values[i], &ch_error))
		{
			zabbix_log(LOG_LEVEL_CRIT, "invalid \"Hostname\" configuration parameter: '%s': %s",
					hostnames->values[i], ch_error);
			zbx_free(ch_error);
			exit(EXIT_FAILURE);
		}
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

	if (0 != CONFIG_FORKS[ZBX_PROCESS_TYPE_LISTENER])
	{
		if (NULL == zbx_config_hosts_allowed)
		{
			zabbix_log(LOG_LEVEL_CRIT, "StartAgents is not 0, parameter \"Server\" must be defined");
			err = 1;
		}
		else if (SUCCEED != zbx_validate_peer_list(zbx_config_hosts_allowed, &ch_error))
		{
			zabbix_log(LOG_LEVEL_CRIT, "invalid entry in \"Server\" configuration parameter: %s", ch_error);
			zbx_free(ch_error);
			err = 1;
		}
	}

	if (NULL != zbx_config_host_interface && HOST_INTERFACE_LEN < zbx_strlen_utf8(zbx_config_host_interface))
	{
		zabbix_log(LOG_LEVEL_CRIT, "the value of \"HostInterface\" configuration parameter cannot be longer"
				" than %d characters", HOST_INTERFACE_LEN);
		err = 1;
	}

	/* make sure active or passive check is enabled */
	if (0 == CONFIG_FORKS[ZBX_PROCESS_TYPE_ACTIVE_CHECKS] && 0 == CONFIG_FORKS[ZBX_PROCESS_TYPE_LISTENER])
	{
		zabbix_log(LOG_LEVEL_CRIT, "either active or passive checks must be enabled");
		err = 1;
	}

	if (NULL != zbx_config_source_ip && SUCCEED != zbx_is_supported_ip(zbx_config_source_ip))
	{
		zabbix_log(LOG_LEVEL_CRIT, "invalid \"SourceIP\" configuration parameter: '%s'", zbx_config_source_ip);
		err = 1;
	}

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

	if (0 != err)
		exit(EXIT_FAILURE);

	zbx_config_eventlog_max_lines_per_second = zbx_config_max_lines_per_second;
}

static int	add_serveractive_host_cb(const zbx_vector_addr_ptr_t *addrs, zbx_vector_str_t *hostnames, void *data)
{
	int	i, forks, new_forks;

	ZBX_UNUSED(data);
	/* add at least one fork */
	new_forks = 0 < hostnames->values_num ? hostnames->values_num : 1;

	forks = CONFIG_FORKS[ZBX_PROCESS_TYPE_ACTIVE_CHECKS];
	CONFIG_FORKS[ZBX_PROCESS_TYPE_ACTIVE_CHECKS] += new_forks;
	config_active_args = (zbx_thread_activechk_args *)zbx_realloc(config_active_args,
			sizeof(zbx_thread_activechk_args) * (size_t)CONFIG_FORKS[ZBX_PROCESS_TYPE_ACTIVE_CHECKS]);

	for (i = 0; i < new_forks; i++, forks++)
	{
		zbx_vector_addr_ptr_create(&config_active_args[forks].addrs);
		zbx_addr_copy(&config_active_args[forks].addrs, addrs);

		config_active_args[forks].zbx_config_tls = zbx_config_tls;
		config_active_args[forks].zbx_get_program_type_cb_arg = get_program_type;
		config_active_args[forks].config_file = config_file;
		config_active_args[forks].config_timeout = zbx_config_timeout;
		config_active_args[forks].config_source_ip = zbx_config_source_ip;
		config_active_args[forks].config_listen_ip = zbx_config_listen_ip;
		config_active_args[forks].config_listen_port = zbx_config_listen_port;
		config_active_args[forks].config_hostname = zbx_config_hostname = zbx_strdup(NULL,
				0 < hostnames->values_num ? hostnames->values[i] : "");
		config_active_args[forks].config_host_metadata = zbx_config_host_metadata;
		config_active_args[forks].config_host_metadata_item = zbx_config_host_metadata_item;
		config_active_args[forks].config_heartbeat_frequency = zbx_config_heartbeat_frequency;
		config_active_args[forks].config_host_interface = zbx_config_host_interface;
		config_active_args[forks].config_host_interface_item = zbx_config_host_interface_item;
		config_active_args[forks].config_buffer_send = zbx_config_buffer_send;
		config_active_args[forks].config_buffer_size = zbx_config_buffer_size;
		config_active_args[forks].config_eventlog_max_lines_per_second =
				zbx_config_eventlog_max_lines_per_second;
		config_active_args[forks].config_max_lines_per_second = zbx_config_max_lines_per_second;
		config_active_args[forks].config_refresh_active_checks = zbx_config_refresh_active_checks;
		config_active_args[forks].config_user_parameters = zbx_config_user_parameters;
	}

	return SUCCEED;
}

static void	parse_hostnames(const char *hostname_param, zbx_vector_str_t *hostnames)
{
	char		*p2, *hostname;
	const char	*p1 = hostname_param;

	if (NULL == hostname_param)
		return;

	do
	{
		if (NULL != (p2 = strchr(p1, ',')))
		{
			hostname = zbx_dsprintf(NULL, "%.*s", (int)(p2 - p1), p1);
			p1 = p2 + 1;
		}
		else
			hostname = zbx_strdup(NULL, p1);

		if (FAIL != zbx_vector_str_search(hostnames, hostname, ZBX_DEFAULT_STR_COMPARE_FUNC))
		{
			zbx_error("error parsing the \"Hostname\" parameter: host \"%s\" specified more than"
					" once", hostname);
			zbx_free(hostname);
			exit(EXIT_FAILURE);
		}

		zbx_vector_str_append(hostnames, hostname);
	}
	while (NULL != p2);
}

/******************************************************************************
 *                                                                            *
 * Purpose: aliases EnableRemoteCommands parameter to                         *
 *          Allow/DenyKey=system.run[*]                                       *
 *                                                                            *
 * Parameters: value - [IN] key access rule parameter value                   *
 *             cfg   - [IN] configuration parameter information               *
 *                                                                            *
 * Return value: SUCCEED - successful execution                               *
 *               FAIL    - failed to add rule                                 *
 *                                                                            *
 ******************************************************************************/
static int	load_enable_remote_commands(const char *value, const struct cfg_line *cfg)
{
	unsigned char	rule_type;
	char		sysrun[] = "system.run[*]";

	if (0 == strcmp(value, "1"))
		rule_type = ZBX_KEY_ACCESS_ALLOW;
	else if (0 == strcmp(value, "0"))
		rule_type = ZBX_KEY_ACCESS_DENY;
	else
		return FAIL;

	zabbix_log(LOG_LEVEL_WARNING, "EnableRemoteCommands parameter is deprecated,"
				" use AllowKey=system.run[*] or DenyKey=system.run[*] instead");

	return zbx_add_key_access_rule(cfg->parameter, sysrun, rule_type);
}

/******************************************************************************
 *                                                                            *
 * Purpose: load configuration from config file                               *
 *                                                                            *
 * Parameters: requirement - produce error if config file missing or not      *
 *                                                                            *
 ******************************************************************************/
static void	zbx_load_config(int requirement, ZBX_TASK_EX *task)
{
#define MIN_ACTIVE_CHECKS_REFRESH_FREQUENCY	1
#define MAX_ACTIVE_CHECKS_REFRESH_FREQUENCY	SEC_PER_DAY
	static char			*active_hosts;
	zbx_vector_str_t		hostnames;
	cfg_custom_parameter_parser_t	parser_load_enable_remove_commands, parser_load_key_access_rule;

	struct cfg_line	cfg[] =
	{
		/* PARAMETER,			VAR,					TYPE,
			MANDATORY,	MIN,			MAX */
		{"Server",			&zbx_config_hosts_allowed,			TYPE_STRING_LIST,
			PARM_OPT,	0,			0},
		{"ServerActive",		&active_hosts,				TYPE_STRING_LIST,
			PARM_OPT,	0,			0},
		{"Hostname",			&zbx_config_hostnames,			TYPE_STRING_LIST,
			PARM_OPT,	0,			0},
		{"HostnameItem",		&CONFIG_HOSTNAME_ITEM,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"HostMetadata",		&zbx_config_host_metadata,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"HostMetadataItem",		&zbx_config_host_metadata_item,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"HostInterface",		&zbx_config_host_interface,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"HostInterfaceItem",		&zbx_config_host_interface_item,	TYPE_STRING,
			PARM_OPT,	0,			0},
		{"BufferSize",			&zbx_config_buffer_size,		TYPE_INT,
			PARM_OPT,	2,			65535},
		{"BufferSend",			&zbx_config_buffer_send,		TYPE_INT,
			PARM_OPT,	1,			SEC_PER_HOUR},
#ifndef _WINDOWS
		{"PidFile",			&CONFIG_PID_FILE,			TYPE_STRING,
			PARM_OPT,	0,			0},
#endif
		{"LogType",			&log_file_cfg.log_type_str,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"LogFile",			&log_file_cfg.log_file_name,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"LogFileSize",			&log_file_cfg.log_file_size,		TYPE_INT,
			PARM_OPT,	0,			1024},
		{"Timeout",			&zbx_config_timeout,			TYPE_INT,
			PARM_OPT,	1,			30},
		{"ListenPort",			&zbx_config_listen_port,	       	TYPE_INT,
			PARM_OPT,	1024,			32767},
		{"ListenIP",			&zbx_config_listen_ip,			TYPE_STRING_LIST,
			PARM_OPT,	0,			0},
		{"SourceIP",			&zbx_config_source_ip,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"DebugLevel",			&CONFIG_LOG_LEVEL,			TYPE_INT,
			PARM_OPT,	0,			5},
		{"StartAgents",			&CONFIG_FORKS[ZBX_PROCESS_TYPE_LISTENER],TYPE_INT,
			PARM_OPT,	0,			100},
		{"RefreshActiveChecks",		&zbx_config_refresh_active_checks,	TYPE_INT,
			PARM_OPT,	MIN_ACTIVE_CHECKS_REFRESH_FREQUENCY,
			MAX_ACTIVE_CHECKS_REFRESH_FREQUENCY},
		{"MaxLinesPerSecond",		&zbx_config_max_lines_per_second,	TYPE_INT,
			PARM_OPT,	1,			1000},
		{"EnableRemoteCommands",	&parser_load_enable_remove_commands,	TYPE_CUSTOM,
			PARM_OPT,	0,			1},
		{"LogRemoteCommands",		&zbx_config_log_remote_commands,	TYPE_INT,
			PARM_OPT,	0,			1},
		{"UnsafeUserParameters",	&zbx_config_unsafe_user_parameters,	TYPE_INT,
			PARM_OPT,	0,			1},
		{"Alias",			&CONFIG_ALIASES,			TYPE_MULTISTRING,
			PARM_OPT,	0,			0},
		{"UserParameter",		&zbx_config_user_parameters,		TYPE_MULTISTRING,
			PARM_OPT,	0,			0},
		{"UserParameterDir",		&CONFIG_USER_PARAMETER_DIR,		TYPE_STRING,
			PARM_OPT,	0,			0},
#ifndef _WINDOWS
		{"LoadModulePath",		&CONFIG_LOAD_MODULE_PATH,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"LoadModule",			&CONFIG_LOAD_MODULE,			TYPE_MULTISTRING,
			PARM_OPT,	0,			0},
		{"AllowRoot",			&config_allow_root,			TYPE_INT,
			PARM_OPT,	0,			1},
		{"User",			&CONFIG_USER,				TYPE_STRING,
			PARM_OPT,	0,			0},
#endif
#ifdef _WINDOWS
		{"PerfCounter",			&CONFIG_PERF_COUNTERS,			TYPE_MULTISTRING,
			PARM_OPT,	0,			0},
		{"PerfCounterEn",		&CONFIG_PERF_COUNTERS_EN,		TYPE_MULTISTRING,
			PARM_OPT,	0,			0},
#endif
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
		{"AllowKey",			&parser_load_key_access_rule,		TYPE_CUSTOM,
			PARM_OPT,	0,			0},
		{"DenyKey",			&parser_load_key_access_rule,		TYPE_CUSTOM,
			PARM_OPT,	0,			0},
		{"ListenBacklog",		&CONFIG_TCP_MAX_BACKLOG_SIZE,		TYPE_INT,
			PARM_OPT,	0,			INT_MAX},
		{"HeartbeatFrequency",		&zbx_config_heartbeat_frequency,	TYPE_INT,
			PARM_OPT,	0,			3600},
		{NULL}
	};

	parser_load_enable_remove_commands.cfg_custom_parameter_parser_func = load_enable_remote_commands;
	parser_load_key_access_rule.cfg_custom_parameter_parser_func = load_key_access_rule;

	/* initialize multistrings */
	zbx_strarr_init(&CONFIG_ALIASES);
	zbx_strarr_init(&zbx_config_user_parameters);
#ifndef _WINDOWS
	zbx_strarr_init(&CONFIG_LOAD_MODULE);
#endif
#ifdef _WINDOWS
	zbx_strarr_init(&CONFIG_PERF_COUNTERS);
	zbx_strarr_init(&CONFIG_PERF_COUNTERS_EN);
#endif
	parse_cfg_file(config_file, cfg, requirement, ZBX_CFG_STRICT, ZBX_CFG_EXIT_FAILURE);

	zbx_finalize_key_access_rules_configuration();

	set_defaults();

	log_file_cfg.log_type = zbx_get_log_type(log_file_cfg.log_type_str);

	zbx_vector_str_create(&hostnames);
	parse_hostnames(zbx_config_hostnames, &hostnames);

	if (NULL != active_hosts && '\0' != *active_hosts)
	{
		char	*error;

		if (FAIL == zbx_set_data_destination_hosts(active_hosts, ZBX_DEFAULT_SERVER_PORT, "ServerActive",
				add_serveractive_host_cb, &hostnames, NULL, &error))
		{
			zbx_error("%s", error);
			exit(EXIT_FAILURE);
		}
	}

	zbx_free(active_hosts);

	if (ZBX_CFG_FILE_REQUIRED == requirement)
	{
		zbx_validate_config_hostnames(&hostnames);
		zbx_validate_config(task);
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		zbx_tls_validate_config(zbx_config_tls, CONFIG_FORKS[ZBX_PROCESS_TYPE_ACTIVE_CHECKS],
				CONFIG_FORKS[ZBX_PROCESS_TYPE_LISTENER], get_program_type);
#endif
	}

	zbx_vector_str_clear_ext(&hostnames, zbx_str_free);
	zbx_vector_str_destroy(&hostnames);
#undef MIN_ACTIVE_CHECKS_REFRESH_FREQUENCY
#undef MAX_ACTIVE_CHECKS_REFRESH_FREQUENCY
}

/******************************************************************************
 *                                                                            *
 * Purpose: free configuration memory                                         *
 *                                                                            *
 ******************************************************************************/
static void	zbx_free_config(void)
{
	zbx_strarr_free(&CONFIG_ALIASES);
	zbx_strarr_free(&zbx_config_user_parameters);
#ifndef _WINDOWS
	zbx_strarr_free(&CONFIG_LOAD_MODULE);
#endif
#ifdef _WINDOWS
	zbx_strarr_free(&CONFIG_PERF_COUNTERS);
	zbx_strarr_free(&CONFIG_PERF_COUNTERS_EN);
#endif
}

#if defined(ZABBIX_DAEMON)
/******************************************************************************
 *                                                                            *
 * Purpose: callback function for providing PID file path to libraries        *
 *                                                                            *
 ******************************************************************************/
static const char	*get_pid_file_path(void)
{
	return CONFIG_PID_FILE;
}
#endif

#ifdef _WINDOWS
static int	zbx_exec_service_task(const char *name, const ZBX_TASK_EX *t)
{
	int	ret;

	switch (t->task)
	{
		case ZBX_TASK_INSTALL_SERVICE:
			ret = ZabbixCreateService(name, t->flags & ZBX_TASK_FLAG_MULTIPLE_AGENTS, config_file);
			break;
		case ZBX_TASK_UNINSTALL_SERVICE:
			ret = ZabbixRemoveService();
			break;
		case ZBX_TASK_START_SERVICE:
			ret = ZabbixStartService();
			break;
		case ZBX_TASK_STOP_SERVICE:
			ret = ZabbixStopService();
			break;
		default:
			/* there can not be other choice */
			assert(0);
	}

	return ret;
}
#endif	/* _WINDOWS */

static void	zbx_on_exit(int ret)
{
	zabbix_log(LOG_LEVEL_DEBUG, "zbx_on_exit() called with ret:%d", ret);

	zbx_free_service_resources(ret);

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_free();
	zbx_tls_library_deinit(ZBX_TLS_INIT_THREADS);	/* deinitialize crypto library from parent thread */
#endif
	zbx_config_tls_free(zbx_config_tls);
	zbx_setproctitle_deinit();
#ifdef _WINDOWS
	while (0 == WSACleanup())
		;
#endif
	exit(EXIT_SUCCESS);
}

#ifdef ZABBIX_DAEMON
static void	signal_redirect_cb(int flags, zbx_signal_handler_f sigusr_handler)
{
#ifdef HAVE_SIGQUEUE
	int	scope;

	switch (ZBX_RTC_GET_MSG(flags))
	{
		case ZBX_RTC_LOG_LEVEL_INCREASE:
		case ZBX_RTC_LOG_LEVEL_DECREASE:
			scope = ZBX_RTC_GET_SCOPE(flags);

			if ((ZBX_RTC_LOG_SCOPE_FLAG | ZBX_RTC_LOG_SCOPE_PID) == scope)
			{
				zbx_signal_process_by_pid(ZBX_RTC_GET_DATA(flags), flags, NULL);
			}
			else
			{
				if (scope < ZBX_PROCESS_TYPE_EXT_FIRST)
				{
					zbx_signal_process_by_type(ZBX_RTC_GET_SCOPE(flags), ZBX_RTC_GET_DATA(flags),
							flags, NULL);
				}
			}

			/* call custom sigusr handler to handle log level changes for non worker processes */
			if (NULL != sigusr_handler)
				sigusr_handler(flags);

			break;
		case ZBX_RTC_USER_PARAMETERS_RELOAD:
			zbx_signal_process_by_type(ZBX_PROCESS_TYPE_ACTIVE_CHECKS, ZBX_RTC_GET_DATA(flags), flags,
					NULL);
			zbx_signal_process_by_type(ZBX_PROCESS_TYPE_LISTENER, ZBX_RTC_GET_DATA(flags), flags, NULL);
			break;
		default:
			if (NULL != sigusr_handler)
				sigusr_handler(flags);
	}
#endif
}
#endif

int	MAIN_ZABBIX_ENTRY(int flags)
{
	zbx_socket_t	listen_sock;
	char		*error = NULL;
	int		i, j = 0, ret = SUCCEED;
#ifdef _WINDOWS
	DWORD		res;

#ifdef _M_X64
	if (NULL == AddVectoredExceptionHandler(0, (PVECTORED_EXCEPTION_HANDLER)&zbx_win_veh_handler))
		zabbix_log(LOG_LEVEL_TRACE, "failed to register vectored exception handler");
#endif /* _M_X64 */
#endif
	if (0 != (flags & ZBX_TASK_FLAG_FOREGROUND))
	{
		printf("Starting Zabbix Agent [%s]. Zabbix %s (revision %s).\nPress Ctrl+C to exit.\n\n",
				zbx_config_hostnames, ZABBIX_VERSION, ZABBIX_REVISION);
	}
#ifndef _WINDOWS
	if (SUCCEED != zbx_locks_create(&error))
	{
		zbx_error("cannot create locks: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}
#endif
	if (SUCCEED != zbx_open_log(&log_file_cfg, CONFIG_LOG_LEVEL, &error))
	{
		zbx_error("cannot open log: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}

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

	zabbix_log(LOG_LEVEL_INFORMATION, "Starting Zabbix Agent [%s]. Zabbix %s (revision %s).",
			zbx_config_hostnames, ZABBIX_VERSION, ZABBIX_REVISION);

	zabbix_log(LOG_LEVEL_INFORMATION, "**** Enabled features ****");
	zabbix_log(LOG_LEVEL_INFORMATION, "IPv6 support:          " IPV6_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "TLS support:           " TLS_FEATURE_STATUS);
	zabbix_log(LOG_LEVEL_INFORMATION, "**************************");

	zabbix_log(LOG_LEVEL_INFORMATION, "using configuration file: %s", config_file);

#if !defined(_WINDOWS) && (defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL))
	if (SUCCEED != zbx_coredump_disable())
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot disable core dump, exiting...");
		zbx_free_service_resources(FAIL);
		exit(EXIT_FAILURE);
	}
#endif
#ifndef _WINDOWS
	if (FAIL == zbx_load_modules(CONFIG_LOAD_MODULE_PATH, CONFIG_LOAD_MODULE, zbx_config_timeout, 1))
	{
		zabbix_log(LOG_LEVEL_CRIT, "loading modules failed, exiting...");
		zbx_free_service_resources(FAIL);
		exit(EXIT_FAILURE);
	}
#endif

	if (FAIL == load_user_parameters(zbx_config_user_parameters, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot load user parameters: %s", error);
		zbx_free(error);
		zbx_free_service_resources(FAIL);
		exit(EXIT_FAILURE);
	}

	if (0 != CONFIG_FORKS[ZBX_PROCESS_TYPE_LISTENER])
	{
		if (FAIL == zbx_tcp_listen(&listen_sock, zbx_config_listen_ip, (unsigned short)zbx_config_listen_port,
				zbx_config_timeout))
		{
			zabbix_log(LOG_LEVEL_CRIT, "listener failed: %s", zbx_socket_strerror());
			zbx_free_service_resources(FAIL);
			exit(EXIT_FAILURE);
		}
	}

	if (SUCCEED != zbx_init_modbus(&error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize modbus: %s", error);
		zbx_free(error);
		zbx_free_service_resources(FAIL);
		exit(EXIT_FAILURE);
	}

	if (SUCCEED != zbx_init_collector_data(&error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize collector: %s", error);
		zbx_free(error);
		zbx_free_service_resources(FAIL);
		exit(EXIT_FAILURE);
	}

#ifdef _WINDOWS
	if (SUCCEED != zbx_init_perf_collector(ZBX_MULTI_THREADED, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot initialize performance counter collector: %s", error);
		zbx_free(error);
	}
	else
		load_perf_counters(CONFIG_PERF_COUNTERS, CONFIG_PERF_COUNTERS_EN);
#endif
	zbx_free_config();

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_init_parent(get_program_type);
#endif
	/* --- START THREADS ---*/

	for (threads_num = 0, i = 0; i < ZBX_PROCESS_TYPE_COUNT; i++)
		threads_num += CONFIG_FORKS[i];

#ifdef _WINDOWS
	if (MAXIMUM_WAIT_OBJECTS < threads_num)
	{
		zabbix_log(LOG_LEVEL_CRIT, "Too many agent threads. Please reduce the StartAgents configuration"
				" parameter or the number of active servers in ServerActive configuration parameter.");
		zbx_free_service_resources(FAIL);
		exit(EXIT_FAILURE);
	}
#endif
	threads = (ZBX_THREAD_HANDLE *)zbx_calloc(threads, (size_t)threads_num, sizeof(ZBX_THREAD_HANDLE));
	threads_flags = (int *)zbx_calloc(threads_flags, (size_t)threads_num, sizeof(int));

	zabbix_log(LOG_LEVEL_INFORMATION, "agent #0 started [main process]");

	for (i = 0; i < threads_num; i++)
	{
		zbx_thread_args_t		*thread_args;
		zbx_thread_info_t		*thread_info;
		zbx_thread_listener_args	listener_args = {&listen_sock, zbx_config_tls, get_program_type,
								config_file, zbx_config_timeout,
								zbx_config_hosts_allowed, zbx_config_user_parameters};

		thread_args = (zbx_thread_args_t *)zbx_malloc(NULL, sizeof(zbx_thread_args_t));
		thread_info = &thread_args->info;

		if (FAIL == get_process_info_by_thread(i + 1, &thread_info->process_type, &thread_info->process_num))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
		}

		thread_info->program_type = program_type;
		thread_info->server_num = i + 1;
		thread_args->args = NULL;

		switch (thread_info->process_type)
		{
			case ZBX_PROCESS_TYPE_COLLECTOR:
				zbx_thread_start(collector_thread, thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_LISTENER:
				thread_args->args = &listener_args;
				zbx_thread_start(listener_thread, thread_args, &threads[i]);
				break;
			case ZBX_PROCESS_TYPE_ACTIVE_CHECKS:
				thread_args->args = &config_active_args[j++];
				zbx_thread_start(active_checks_thread, thread_args, &threads[i]);
				break;
		}
#ifndef _WINDOWS
		zbx_free(thread_args);
#endif
	}

#ifdef _WINDOWS
	zbx_set_parent_signal_handler(zbx_on_exit);	/* must be called after all threads are created */

	/* wait for an exiting thread */
	res = WaitForMultipleObjectsEx(threads_num, threads, FALSE, INFINITE, FALSE);

	if (ZBX_IS_RUNNING())
	{
		/* Zabbix agent service should either be stopped by the user in ServiceCtrlHandler() or */
		/* crash. If some thread has terminated normally, it means something is terribly wrong. */

		zabbix_log(LOG_LEVEL_CRIT, "One thread has terminated unexpectedly (code:%lu). Exiting ...", res);
		THIS_SHOULD_NEVER_HAPPEN;

		/* notify other threads and allow them to terminate */
		ZBX_DO_EXIT();
		zbx_sleep(1);
	}
	else
	{
		zbx_tcp_close(&listen_sock);

		/* Wait for the service worker thread to terminate us. Listener threads may not exit up to */
		/* config_timeout seconds if they're waiting for external processes to finish / timeout */
		zbx_sleep(zbx_config_timeout);

		THIS_SHOULD_NEVER_HAPPEN;
	}
#else
	zbx_unset_exit_on_terminate();

	while (ZBX_IS_RUNNING() && -1 == wait(&i))	/* wait for any child to exit */
	{
		if (EINTR != errno)
		{
			zabbix_log(LOG_LEVEL_ERR, "failed to wait on child processes: %s", zbx_strerror(errno));
			zbx_set_exiting_with_fail();
			break;
		}
	}

	zbx_log_exit_signal();

	ret = ZBX_EXIT_STATUS();

#endif
	zbx_on_exit(ret);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: free service resources allocated by main thread                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_free_service_resources(int ret)
{
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
	zbx_alias_list_free();
	zbx_free_collector_data();
	zbx_deinit_modbus();
#ifdef _WINDOWS
	zbx_free_perf_collector();
	zbx_co_uninitialize();
#endif
#ifndef _WINDOWS
	zbx_unload_modules();
#endif
	zabbix_log(LOG_LEVEL_INFORMATION, "Zabbix Agent stopped. Zabbix %s (revision %s).",
			ZABBIX_VERSION, ZABBIX_REVISION);

	zbx_close_log();

#ifndef _WINDOWS
	zbx_locks_destroy();
#endif
}

int	main(int argc, char **argv)
{
	ZBX_TASK_EX	t = {ZBX_TASK_START};
	char		*error = NULL;
#ifdef _WINDOWS
	int		ret;
#endif
	zbx_init_library_common(zbx_log_impl);
	zbx_init_library_sysinfo(get_zbx_config_timeout, get_zbx_config_enable_remote_commands,
			get_zbx_config_log_remote_commands, get_zbx_config_unsafe_user_parameters,
			get_zbx_config_source_ip, get_zbx_config_hostname, get_zbx_config_hostnames,
			get_zbx_config_host_metadata, get_zbx_config_host_metadata_item);
#if defined(_WINDOWS) || defined(__MINGW32__)
	zbx_init_library_win32(&get_progname);
#endif
#ifdef _WINDOWS
	/* Provide, so our process handles errors instead of the system itself. */
	/* Attention!!! */
	/* The system does not display the critical-error-handler message box. */
	/* Instead, the system sends the error to the calling process.*/
	SetErrorMode(SEM_FAILCRITICALERRORS);
#endif
	zbx_config_tls = zbx_config_tls_new();
	argv = zbx_setproctitle_init(argc, argv);
	progname = get_program_name(argv[0]);

	if (SUCCEED != parse_commandline(argc, argv, &t))
		exit(EXIT_FAILURE);

#ifdef _WINDOWS
	/* if agent is started as windows service then try to log errors */
	/* into windows event log while zabbix_log is not ready */
	if (ZBX_TASK_START == t.task && 0 == (t.flags & ZBX_TASK_FLAG_FOREGROUND))
	{
		zbx_config_log_t	log_cfg	= {NULL, NULL, ZBX_LOG_TYPE_SYSTEM, 1};

		zbx_open_log(&log_cfg, LOG_LEVEL_WARNING, NULL);
	}
#endif

#if defined(_WINDOWS) || defined(__MINGW32__)
	zbx_import_symbols();
#endif

#ifdef _WINDOWS
	if (ZBX_TASK_SHOW_USAGE != t.task && ZBX_TASK_SHOW_VERSION != t.task && ZBX_TASK_SHOW_HELP != t.task &&
			SUCCEED != zbx_socket_start(&error))
	{
		zabbix_log(LOG_LEVEL_CRIT, error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}
#endif

	zbx_init_library_cfg(program_type, config_file);

	/* this is needed to set default hostname in zbx_load_config() */
	zbx_init_metrics();

	switch (t.task)
	{
		case ZBX_TASK_SHOW_USAGE:
			zbx_usage();
			exit(EXIT_FAILURE);
			break;
#ifndef _WINDOWS
		case ZBX_TASK_RUNTIME_CONTROL:
			zbx_load_config(ZBX_CFG_FILE_REQUIRED, &t);
			exit(SUCCEED == zbx_sigusr_send(t.data, CONFIG_PID_FILE) ? EXIT_SUCCESS : EXIT_FAILURE);
			break;
#else
		case ZBX_TASK_INSTALL_SERVICE:
		case ZBX_TASK_UNINSTALL_SERVICE:
		case ZBX_TASK_START_SERVICE:
		case ZBX_TASK_STOP_SERVICE:
			if (t.flags & ZBX_TASK_FLAG_MULTIPLE_AGENTS)
			{
				char	*p, *first_hostname;

				zbx_load_config(ZBX_CFG_FILE_REQUIRED, &t);

				first_hostname = NULL != (p = strchr(zbx_config_hostnames, ',')) ? zbx_dsprintf(NULL,
						"%.*s", (int)(p - zbx_config_hostnames), zbx_config_hostnames) :
						zbx_strdup(NULL, zbx_config_hostnames);
				zbx_snprintf(ZABBIX_SERVICE_NAME, sizeof(ZABBIX_SERVICE_NAME), "%s [%s]",
						APPLICATION_NAME, first_hostname);
				zbx_snprintf(ZABBIX_EVENT_SOURCE, sizeof(ZABBIX_EVENT_SOURCE), "%s [%s]",
						APPLICATION_NAME, first_hostname);
				zbx_free(first_hostname);
			}
			else
				zbx_load_config(ZBX_CFG_FILE_OPTIONAL, &t);

			zbx_free_config();

			ret = zbx_exec_service_task(argv[0], &t);

			while (0 == WSACleanup())
				;

			zbx_free_metrics();
			exit(SUCCEED == ret ? EXIT_SUCCESS : EXIT_FAILURE);
			break;
#endif
		case ZBX_TASK_TEST_METRIC:
		case ZBX_TASK_PRINT_SUPPORTED:
			zbx_load_config(ZBX_CFG_FILE_OPTIONAL, &t);
#ifdef _WINDOWS
			if (SUCCEED != zbx_init_perf_collector(ZBX_SINGLE_THREADED, &error))
			{
				zabbix_log(LOG_LEVEL_WARNING, "cannot initialize performance counter collector: %s",
						error);
				zbx_free(error);
			}
			else
				load_perf_counters(CONFIG_PERF_COUNTERS, CONFIG_PERF_COUNTERS_EN);
#else
			zbx_set_common_signal_handlers(zbx_on_exit);
#endif
#ifndef _WINDOWS
			if (FAIL == zbx_load_modules(CONFIG_LOAD_MODULE_PATH, CONFIG_LOAD_MODULE, zbx_config_timeout,
					0))
			{
				zabbix_log(LOG_LEVEL_CRIT, "loading modules failed, exiting...");
				exit(EXIT_FAILURE);
			}
#endif
			zbx_set_user_parameter_dir(CONFIG_USER_PARAMETER_DIR);

			if (FAIL == load_user_parameters(zbx_config_user_parameters, &error))
			{
				zabbix_log(LOG_LEVEL_CRIT, "cannot load user parameters: %s", error);
				zbx_free(error);
				exit(EXIT_FAILURE);
			}

			load_aliases(CONFIG_ALIASES);
			zbx_free_config();
			if (ZBX_TASK_TEST_METRIC == t.task)
				zbx_test_parameter(TEST_METRIC);
			else
				zbx_test_parameters();
#ifdef _WINDOWS
			zbx_free_perf_collector();	/* cpu_collector must be freed before perf_collector is freed */

			while (0 == WSACleanup())
				;

			zbx_co_uninitialize();
#endif
#ifndef _WINDOWS
			zbx_unload_modules();
#endif
			zbx_free_metrics();
			zbx_alias_list_free();
			exit(EXIT_SUCCESS);
			break;
		case ZBX_TASK_SHOW_VERSION:
			zbx_version();
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
			printf("\n");
			zbx_tls_version();
#endif
#ifdef _AIX
			printf("\n");
			tl_version();
#endif
			exit(EXIT_SUCCESS);
			break;
		case ZBX_TASK_SHOW_HELP:
			zbx_help(config_file);
			exit(EXIT_SUCCESS);
			break;
		default:
			zbx_load_config(ZBX_CFG_FILE_REQUIRED, &t);
			zbx_set_user_parameter_dir(CONFIG_USER_PARAMETER_DIR);
			load_aliases(CONFIG_ALIASES);
#ifdef _WINDOWS
			if (0 == (t.flags & ZBX_TASK_FLAG_FOREGROUND))
				zbx_close_log();
#endif
			break;
	}

#if defined(ZABBIX_SERVICE)
	zbx_service_start(t.flags);
#elif defined(ZABBIX_DAEMON)
	zbx_daemon_start(config_allow_root, CONFIG_USER, t.flags, get_pid_file_path, zbx_on_exit,
			log_file_cfg.log_type, log_file_cfg.log_file_name, signal_redirect_cb);
#endif
	exit(EXIT_SUCCESS);
}
