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
#include "sysinfo.h"

#include "cfg.h"
#include "log.h"
#include "zbxconf.h"
#include "zbxgetopt.h"
#include "comms.h"
#include "alias.h"

#include "stats.h"
#include "perfstat.h"
#include "active.h"
#include "listener.h"

#include "symbols.h"

#if defined(ZABBIX_SERVICE)
#	include "service.h"
#elif defined(ZABBIX_DAEMON)
#	include "daemon.h"
#endif

const char	*progname = NULL;

/* Default config file location */
#ifdef _WINDOWS
	static char	DEFAULT_CONFIG_FILE[]	= "C:\\zabbix_agentd.conf";
#else
	static char	DEFAULT_CONFIG_FILE[]	= "/etc/zabbix/zabbix_agentd.conf";
#endif

/* application TITLE */
const char	title_message[] = APPLICATION_NAME
#if defined(_WIN64)
				" Win64"
#elif defined(WIN32)
				" Win32"
#endif
#if defined(ZABBIX_SERVICE)
				" (service)"
#elif defined(ZABBIX_DAEMON)
				" (daemon)"
#endif
	;
/* end of application TITLE */

/* application USAGE message */
const char	usage_message[] =
	"[-Vhp]"
#ifdef _WINDOWS
	" [-idsx] [-m]"
#endif
	" [-c <config-file>] [-t <item key>]";
/* end of application USAGE message */

/* application HELP message */
const char	*help_message[] = {
	"Options:",
	"",
	"  -c --config <config-file>  absolute path to the configuration file",
	"  -h --help                  give this help",
	"  -V --version               display version number",
	"  -p --print                 print known items and exit",
	"  -t --test <item key>       test specified item and exit",
#ifdef _WINDOWS
	"",
	"Functions:",
	"",
	"  -i --install          install Zabbix agent as service",
	"  -d --uninstall        uninstall Zabbix agent from service",

	"  -s --start            start Zabbix agent service",
	"  -x --stop             stop Zabbix agent service",

	"  -m --multiple-agents  service name will include hostname",
#endif
	NULL	/* end of text */
};
/* end of application HELP message */

/* COMMAND LINE OPTIONS */
static struct zbx_option	longopts[] =
{
	{"config",		1,	NULL,	'c'},
	{"help",		0,	NULL,	'h'},
	{"version",		0,	NULL,	'V'},
	{"print",		0,	NULL,	'p'},
	{"test",		1,	NULL,	't'},
#ifdef _WINDOWS
	{"install",		0,	NULL,	'i'},
	{"uninstall",		0,	NULL,	'd'},

	{"start",		0,	NULL,	's'},
	{"stop",		0,	NULL,	'x'},

	{"multiple-agents",	0,	NULL,	'm'},
#endif
	{NULL}
};

static char	shortopts[] =
	"c:hVpt:"
#ifdef _WINDOWS
	"idsxm"
#endif
	;
/* end of COMMAND LINE OPTIONS */

static char		*TEST_METRIC = NULL;
int			threads_num = 0;
ZBX_THREAD_HANDLE	*threads = NULL;

unsigned char	daemon_type = ZBX_DAEMON_TYPE_AGENT;

unsigned char	process_type = 255;	/* ZBX_PROCESS_TYPE_UNKNOWN */

static void	parse_commandline(int argc, char **argv, ZBX_TASK_EX *t)
{
	char	ch = '\0';

	t->task = ZBX_TASK_START;

	/* parse the command-line */
	while ((char)EOF != (ch = (char)zbx_getopt_long(argc, argv, shortopts, longopts, NULL)))
	{
		switch (ch)
		{
			case 'c':
				CONFIG_FILE = strdup(zbx_optarg);
				break;
			case 'h':
				help();
				exit(FAIL);
				break;
			case 'V':
				version();
#ifdef _AIX
				tl_version();
#endif
				exit(FAIL);
				break;
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
				t->flags = ZBX_TASK_FLAG_MULTIPLE_AGENTS;
				break;
#endif
			default:
				t->task = ZBX_TASK_SHOW_USAGE;
				break;
		}
	}

	if (NULL == CONFIG_FILE)
		CONFIG_FILE = DEFAULT_CONFIG_FILE;
}

/******************************************************************************
 *                                                                            *
 * Function: set_defaults                                                     *
 *                                                                            *
 * Purpose: set configuration defaults                                        *
 *                                                                            *
 * Author: Vladimir Levijev, Rudolfs Kreicbergs                               *
 *                                                                            *
 ******************************************************************************/
static void	set_defaults()
{
	AGENT_RESULT	result;
	char		**value = NULL;

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
				zabbix_log(LOG_LEVEL_WARNING, "hostname truncated to [%s])", *value);
			}

			CONFIG_HOSTNAME = zbx_strdup(CONFIG_HOSTNAME, *value);
		}
		else
			zabbix_log(LOG_LEVEL_WARNING, "failed to get system hostname from [%s])", CONFIG_HOSTNAME_ITEM);

		free_result(&result);
	}
	else if (NULL != CONFIG_HOSTNAME_ITEM)
		zabbix_log(LOG_LEVEL_WARNING, "both Hostname and HostnameItem defined, using [%s]", CONFIG_HOSTNAME);

#ifdef USE_PID_FILE
	if (NULL == CONFIG_PID_FILE)
		CONFIG_PID_FILE = "/tmp/zabbix_agentd.pid";
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
static void	zbx_validate_config()
{
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

	/* make sure active or passive check is enabled */
	if (1 == CONFIG_DISABLE_ACTIVE && 1 == CONFIG_DISABLE_PASSIVE)
	{
		zabbix_log(LOG_LEVEL_CRIT, "either active or passive checks must be enabled");
		exit(FAIL);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_load_config                                                  *
 *                                                                            *
 * Purpose: load configuration from config file                               *
 *                                                                            *
 * Parameters: optional - do not produce error if config file missing         *
 *                                                                            *
 ******************************************************************************/
static void	zbx_load_config(int optional)
{
	struct cfg_line	cfg[] =
	{
		/* PARAMETER,			VAR,					TYPE,
			MANDATORY,	MIN,			MAX */
		{"Server",			&CONFIG_HOSTS_ALLOWED,			TYPE_STRING,
			PARM_MAND,	0,			0},
		{"Hostname",			&CONFIG_HOSTNAME,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"HostnameItem",		&CONFIG_HOSTNAME_ITEM,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"BufferSize",			&CONFIG_BUFFER_SIZE,			TYPE_INT,
			PARM_OPT,	2,			65535},
		{"BufferSend",			&CONFIG_BUFFER_SEND,			TYPE_INT,
			PARM_OPT,	1,			SEC_PER_HOUR},
#ifdef USE_PID_FILE
		{"PidFile",			&CONFIG_PID_FILE,			TYPE_STRING,
			PARM_OPT,	0,			0},
#endif
		{"LogFile",			&CONFIG_LOG_FILE,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"LogFileSize",			&CONFIG_LOG_FILE_SIZE,			TYPE_INT,
			PARM_OPT,	0,			1024},
		{"DisableActive",		&CONFIG_DISABLE_ACTIVE,			TYPE_INT,
			PARM_OPT,	0,			1},
		{"DisablePassive",		&CONFIG_DISABLE_PASSIVE,		TYPE_INT,
			PARM_OPT,	0,			1},
		{"Timeout",			&CONFIG_TIMEOUT,			TYPE_INT,
			PARM_OPT,	1,			30},
		{"ListenPort",			&CONFIG_LISTEN_PORT,			TYPE_INT,
			PARM_OPT,	1024,			32767},
		{"ServerPort",			&CONFIG_SERVER_PORT,			TYPE_INT,
			PARM_OPT,	1024,			32767},
		{"ListenIP",			&CONFIG_LISTEN_IP,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"SourceIP",			&CONFIG_SOURCE_IP,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"DebugLevel",			&CONFIG_LOG_LEVEL,			TYPE_INT,
			PARM_OPT,	0,			4},
		{"StartAgents",			&CONFIG_ZABBIX_FORKS,			TYPE_INT,
			PARM_OPT,	1,			100},
		{"RefreshActiveChecks",		&CONFIG_REFRESH_ACTIVE_CHECKS,		TYPE_INT,
			PARM_OPT,	SEC_PER_MIN,		SEC_PER_HOUR},
		{"MaxLinesPerSecond",		&CONFIG_MAX_LINES_PER_SECOND,		TYPE_INT,
			PARM_OPT,	1,			1000},
		{"AllowRoot",			&CONFIG_ALLOW_ROOT,			TYPE_INT,
			PARM_OPT,	0,			1},
		{"EnableRemoteCommands",	&CONFIG_ENABLE_REMOTE_COMMANDS,		TYPE_INT,
			PARM_OPT,	0,			1},
		{"LogRemoteCommands",		&CONFIG_LOG_REMOTE_COMMANDS,		TYPE_INT,
			PARM_OPT,	0,			1},
		{"UnsafeUserParameters",	&CONFIG_UNSAFE_USER_PARAMETERS,		TYPE_INT,
			PARM_OPT,	0,			1},
		{"Alias",			&CONFIG_ALIASES,			TYPE_MULTISTRING,
			PARM_OPT,	0,			0},
		{"UserParameter",		&CONFIG_USER_PARAMETERS,		TYPE_MULTISTRING,
			PARM_OPT,	0,			0},
#ifdef _WINDOWS
		{"PerfCounter",			&CONFIG_PERF_COUNTERS,			TYPE_MULTISTRING,
			PARM_OPT,	0,			0},
#endif
		{NULL}
	};

	/* initialize multistrings */
	zbx_strarr_init(&CONFIG_ALIASES);
	zbx_strarr_init(&CONFIG_USER_PARAMETERS);
#ifdef _WINDOWS
	zbx_strarr_init(&CONFIG_PERF_COUNTERS);
#endif

	parse_cfg_file(CONFIG_FILE, cfg, optional, ZBX_CFG_STRICT);

	set_defaults();

	if (ZBX_CFG_FILE_REQUIRED == optional)
		zbx_validate_config();
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_free_config                                                  *
 *                                                                            *
 * Purpose: free configuration memory                                         *
 *                                                                            *
 * Author: Vladimir Levijev                                                   *
 *                                                                            *
 ******************************************************************************/
static void	zbx_free_config()
{
	zbx_strarr_free(CONFIG_ALIASES);
	zbx_strarr_free(CONFIG_USER_PARAMETERS);
#ifdef _WINDOWS
	zbx_strarr_free(CONFIG_PERF_COUNTERS);
#endif
}

#ifdef _WINDOWS
static int	zbx_exec_service_task(const char *name, const ZBX_TASK_EX *t)
{
	int	ret;

	switch (t->task)
	{
		case ZBX_TASK_INSTALL_SERVICE:
			ret = ZabbixCreateService(name, t->flags & ZBX_TASK_FLAG_MULTIPLE_AGENTS);
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

int	MAIN_ZABBIX_ENTRY()
{
	zbx_thread_args_t		*thread_args;
	ZBX_THREAD_ACTIVECHK_ARGS	activechk_args;
	zbx_sock_t			listen_sock;
	int				i, thread_num = 0;
#ifdef _WINDOWS
	DWORD				res;
#endif

	if (NULL == CONFIG_LOG_FILE || '\0' == *CONFIG_LOG_FILE)
		zabbix_open_log(LOG_TYPE_SYSLOG, CONFIG_LOG_LEVEL, NULL);
	else
		zabbix_open_log(LOG_TYPE_FILE, CONFIG_LOG_LEVEL, CONFIG_LOG_FILE);

	zabbix_log(LOG_LEVEL_INFORMATION, "Starting Zabbix Agent [%s]. Zabbix %s (revision %s).",
			CONFIG_HOSTNAME, ZABBIX_VERSION, ZABBIX_REVISION);

	if (0 == CONFIG_DISABLE_PASSIVE)
	{
		if (FAIL == zbx_tcp_listen(&listen_sock, CONFIG_LISTEN_IP, (unsigned short)CONFIG_LISTEN_PORT))
		{
			zabbix_log(LOG_LEVEL_CRIT, "listener failed: %s", zbx_tcp_strerror());
			exit(1);
		}
	}

	/* collector data must be initiated by user 'zabbix' */
	init_collector_data();

#ifdef _WINDOWS
	load_perf_counters(CONFIG_PERF_COUNTERS);
#endif
	load_user_parameters(CONFIG_USER_PARAMETERS);
	load_aliases(CONFIG_ALIASES);

	zbx_free_config();

	/* --- START THREADS ---*/

	if (1 == CONFIG_DISABLE_PASSIVE)
	{
		/* only main process and active checks will be started */
		CONFIG_ZABBIX_FORKS = 0;	/* listeners are not needed for passive checks */
	}

	/* allocate memory for a collector, all listeners and an active check */
	threads_num = 1 + CONFIG_ZABBIX_FORKS + (0 == CONFIG_DISABLE_ACTIVE ? 1 : 0);
	threads = zbx_calloc(threads, threads_num, sizeof(ZBX_THREAD_HANDLE));

	/* start the collector thread */
	thread_args = (zbx_thread_args_t *)zbx_malloc(NULL, sizeof(zbx_thread_args_t));
	thread_args->thread_num = thread_num;
	thread_args->args = NULL;
	threads[thread_num++] = zbx_thread_start(collector_thread, thread_args);

	/* start listeners */
	for (i = 0; i < CONFIG_ZABBIX_FORKS; i++)
	{
		thread_args = (zbx_thread_args_t *)zbx_malloc(NULL, sizeof(zbx_thread_args_t));
		thread_args->thread_num = thread_num;
		thread_args->args = &listen_sock;
		threads[thread_num++] = zbx_thread_start(listener_thread, thread_args);
	}

	/* start active check */
	if (0 == CONFIG_DISABLE_ACTIVE)
	{
		activechk_args.host = CONFIG_HOSTS_ALLOWED;
		activechk_args.port = (unsigned short)CONFIG_SERVER_PORT;

		thread_args = (zbx_thread_args_t *)zbx_malloc(NULL, sizeof(zbx_thread_args_t));
		thread_args->thread_num = thread_num;
		thread_args->args = &activechk_args;
		threads[thread_num++] = zbx_thread_start(active_checks_thread, thread_args);
	}

#ifdef _WINDOWS
	set_parent_signal_handler();	/* must be called after all threads are created */

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
		/* wait for the service worker thread to terminate us */
		zbx_sleep(2);
		THIS_SHOULD_NEVER_HAPPEN;
	}
#else
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
#endif

	zbx_on_exit();

	return SUCCEED;
}

void	zbx_on_exit()
{
	zabbix_log(LOG_LEVEL_DEBUG, "zbx_on_exit() called");

	if (NULL != threads)
	{
		int		i;
#if !defined(_WINDOWS)
		sigset_t	set;

		/* ignore SIGCHLD signals in order for zbx_sleep() to work */
		sigemptyset(&set);
		sigaddset(&set, SIGCHLD);
		sigprocmask(SIG_BLOCK, &set, NULL);
#endif

		for (i = 0; i < threads_num; i++)
		{
			if (threads[i])
			{
				zbx_thread_kill(threads[i]);
				threads[i] = ZBX_THREAD_HANDLE_NULL;
			}
		}

		zbx_free(threads);
	}

#ifdef USE_PID_FILE
	daemon_stop();
#endif

#if !defined(_WINDOWS)
	zbx_sleep(2);	/* wait for all processes to exit */
#endif

	zabbix_log(LOG_LEVEL_INFORMATION, "Zabbix Agent stopped. Zabbix %s (revision %s).",
			ZABBIX_VERSION, ZABBIX_REVISION);

	zabbix_close_log();

	free_metrics();
	alias_list_free();
	free_collector_data();

	exit(SUCCEED);
}

#if defined(HAVE_SIGQUEUE) && defined(ZABBIX_DAEMON)
void	zbx_sigusr_handler(zbx_task_t task)
{
	/* nothing to do */
}
#endif

int	main(int argc, char **argv)
{
	ZBX_TASK_EX	t;
#ifdef _WINDOWS
	int		ret;

	/* Provide, so our process handles errors instead of the system itself. */
	/* Attention!!! */
	/* The system does not display the critical-error-handler message box. */
	/* Instead, the system sends the error to the calling process.*/
	SetErrorMode(SEM_FAILCRITICALERRORS);
#endif

	memset(&t, 0, sizeof(t));
	t.task = ZBX_TASK_START;

	progname = get_program_name(argv[0]);

	parse_commandline(argc, argv, &t);

	import_symbols();

	/* this is needed to set default hostname in zbx_load_config() */
	init_metrics();

	switch (t.task)
	{
		case ZBX_TASK_SHOW_USAGE:
			usage();
			exit(FAIL);
			break;
#ifdef _WINDOWS
		case ZBX_TASK_INSTALL_SERVICE:
		case ZBX_TASK_UNINSTALL_SERVICE:
		case ZBX_TASK_START_SERVICE:
		case ZBX_TASK_STOP_SERVICE:
			zbx_load_config(ZBX_CFG_FILE_REQUIRED);
			zbx_free_config();

			if (t.flags & ZBX_TASK_FLAG_MULTIPLE_AGENTS)
			{
				zbx_snprintf(ZABBIX_SERVICE_NAME, sizeof(ZABBIX_SERVICE_NAME), "%s [%s]",
						APPLICATION_NAME, CONFIG_HOSTNAME);
				zbx_snprintf(ZABBIX_EVENT_SOURCE, sizeof(ZABBIX_EVENT_SOURCE), "%s [%s]",
						APPLICATION_NAME, CONFIG_HOSTNAME);
			}

			ret = zbx_exec_service_task(argv[0], &t);
			free_metrics();
			exit(ret);
			break;
#endif
		case ZBX_TASK_TEST_METRIC:
		case ZBX_TASK_PRINT_SUPPORTED:
			zbx_load_config(ZBX_CFG_FILE_OPTIONAL);
#ifdef _WINDOWS
			init_collector_data();	/* required for reading PerfCounter */
			load_perf_counters(CONFIG_PERF_COUNTERS);
#endif
			load_user_parameters(CONFIG_USER_PARAMETERS);
			load_aliases(CONFIG_ALIASES);
			zbx_free_config();
			if (ZBX_TASK_TEST_METRIC == t.task)
				test_parameter(TEST_METRIC, PROCESS_TEST);
			else
				test_parameters();
#ifdef _WINDOWS
			free_collector_data();
#endif
			free_metrics();
			alias_list_free();
			exit(SUCCEED);
			break;
		default:
			zbx_load_config(ZBX_CFG_FILE_REQUIRED);
			break;
	}

	START_MAIN_ZABBIX_ENTRY(CONFIG_ALLOW_ROOT);

	exit(SUCCEED);
}
