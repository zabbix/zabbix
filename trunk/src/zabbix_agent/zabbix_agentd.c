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
#include "security.h"
#include "zabbix_agent.h"

#include "cfg.h"
#include "log.h"
#include "zbxconf.h"
#include "zbxgetopt.h"
#include "zbxsock.h"
#include "mutexs.h"
#include "alias.h"

#include "stats.h"
#include "active.h"
#include "listener.h"

#include "symbols.h"

#if defined(ZABBIX_SERVICE)
#	include "service.h"
#elif defined(ZABBIX_DAEMON) /* ZABBIX_SERVICE */
#	include "daemon.h"
#endif /* ZABBIX_DAEMON */


char *progname = NULL;


/* application TITLE */

char title_message[] = APPLICATION_NAME
#if defined(_WIN64)		
				" Win64"
#elif defined(WIN32)		
				" Win32"
#endif /* WIN32 */
#if defined(ZABBIX_SERVICE)	
				" (service)"
#elif defined(ZABBIX_DAEMON)	
				" (daemon)"
#endif /* ZABBIX_SERVICE */
	;
/* end of application TITLE */


/* application USAGE message */

char usage_message[] = 
	"[-vhp]"
#if defined(_WINDOWS)
	" [-idsx]"
#endif /* _WINDOWS */
	" [-c <file>] [-t <metric>]";

/*end of application USAGE message */



/* application HELP message */

char *help_message[] = {
	"Options:",
	"",
	"  -c --config <file>  Specify configuration file",
	"  -h --help           give this help",
	"  -v --version        display version number",
	"  -p --print          print supported metrics and exit",
	"  -t --test <metric>  test specified metric and exit",
/*	"  -u --usage <metric> test specified metric and exit",	*/ /* !!! TODO - print metric usage !!! */

#if defined (_WINDOWS)

	"",
	"Functions:",
	"",
	"  -i --install        install ZABIX agent as service",
	"  -d --uninstall      uninstall ZABIX agent from service",
	
	"  -s --start          start ZABIX agent service",
	"  -x --stop           stop ZABIX agent service",

#endif /* _WINDOWS */

	0 /* end of text */
};

/* end of application HELP message */



/* COMMAND LINE OPTIONS */

/* long options */

static struct zbx_option longopts[] =
{
	{"config",	1,	0,	'c'},
	{"help",	0,	0,	'h'},
	{"version",	0,	0,	'v'},
	{"print",	0,	0,	'p'},
	{"test",	1,	0,	't'},

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
	"c:hvpt:"
#if defined (_WINDOWS)
	"idsx"
#endif /* _WINDOWS */
	;

/* end of COMMAND LINE OPTIONS*/



static char	*TEST_METRIC = NULL;

static ZBX_THREAD_HANDLE	*threads = NULL;

static int parse_commandline(int argc, char **argv)
{
	zbx_task_t	task	= ZBX_TASK_START;
	char	ch	= '\0';

	/* Parse the command-line. */
	while ((ch = zbx_getopt_long(argc, argv, shortopts, longopts, NULL)) != EOF)
		switch ((char) ch) {
		case 'c':
			CONFIG_FILE = strdup(zbx_optarg);
			break;
		case 'h':
			help();
			exit(-1);
			break;
		case 'v':
			version();
			exit(-1);
			break;
		case 'p':
			if(task == ZBX_TASK_START)
				task = ZBX_TASK_PRINT_SUPPORTED;
			break;
		case 't':
			if(task == ZBX_TASK_START) 
			{
				task = ZBX_TASK_TEST_METRIC;
				TEST_METRIC = strdup(zbx_optarg);
			}
			break;

#if defined (_WINDOWS)
		case 'i':
			task = ZBX_TASK_INSTALL_SERVICE;
			break;
		case 'd':
			task = ZBX_TASK_UNINSTALL_SERVICE;
			break;
		case 's':
			task = ZBX_TASK_START_SERVICE;
			break;
		case 'x':
			task = ZBX_TASK_STOP_SERVICE;
			break;

#endif /* _WINDOWS */

		default:
			task = ZBX_TASK_SHOW_USAGE;
			break;
	}

	return task;
}

static ZBX_SOCKET tcp_listen(void)
{
	ZBX_SOCKET sock;
	ZBX_SOCKADDR serv_addr;
	int	on;

	if ((sock = (ZBX_SOCKET)socket(AF_INET, SOCK_STREAM, 0)) == INVALID_SOCKET)
	{
		zabbix_log( LOG_LEVEL_CRIT, "Unable to create socket. [%s]", strerror_from_system(zbx_sock_last_error()));
		exit(1);
	}
	
	/* Enable address reuse */
	/* This is to immediately use the address even if it is in TIME_WAIT state */
	/* http://www-128.ibm.com/developerworks/linux/library/l-sockpit/index.html */
	on = 1;
	if( -1 == setsockopt(sock, SOL_SOCKET, SO_REUSEADDR, (void *)&on, sizeof(on) ))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Cannot setsockopt SO_REUSEADDR [%s]", strerror(errno));
	}

	/* Create socket	Fill in local address structure */
	memset(&serv_addr, 0, sizeof(ZBX_SOCKADDR));

	serv_addr.sin_family		= AF_INET;
	serv_addr.sin_addr.s_addr	= CONFIG_LISTEN_IP ? inet_addr(CONFIG_LISTEN_IP) : htonl(INADDR_ANY);
	serv_addr.sin_port		= htons((unsigned short)CONFIG_LISTEN_PORT);

	/* Bind socket */
	if (bind(sock,(struct sockaddr *)&serv_addr,sizeof(ZBX_SOCKADDR)) == SOCKET_ERROR)
	{
		zabbix_log(LOG_LEVEL_CRIT, "Cannot bind to port %u for server %s. Error [%s]. Another zabbix_agentd already running ?",
				CONFIG_LISTEN_PORT, CONFIG_LISTEN_IP, strerror_from_system(zbx_sock_last_error()));

		exit(1);
	}

	if(listen(sock, SOMAXCONN) == SOCKET_ERROR)
	{
		zabbix_log( LOG_LEVEL_CRIT, "Listen failed. [%s]", strerror_from_system(zbx_sock_last_error()));
		exit(1);
	}

	return sock;
}

int MAIN_ZABBIX_ENTRY(void)
{
	ZBX_THREAD_ACTIVECHK_ARGS	activechk_args;

	int	i = 0;

	ZBX_SOCKET	sock;

	zabbix_open_log(
#if ON	/* !!! normal case must be ON !!! */
		LOG_TYPE_FILE
#elif OFF	/* !!! normal case must be OFF !!! */
		LOG_TYPE_SYSLOG
#else	/* !!! for debug only, print log with zbx_error !!! */ 
		LOG_TYPE_UNDEFINED
#endif
		,
		CONFIG_LOG_LEVEL,
		CONFIG_LOG_FILE
		);

	zabbix_log(LOG_LEVEL_INFORMATION, "zabbix_agentd started. ZABBIX %s.", ZABBIX_VERSION);

	sock = tcp_listen();

	init_collector_data();

	/* --- START THREADS ---*/

	threads = calloc(CONFIG_ZABBIX_FORKS, sizeof(ZBX_THREAD_HANDLE));

	threads[i=0] = zbx_thread_start(collector_thread, NULL);

	/* start listeners */
	for(i++; i < CONFIG_ZABBIX_FORKS - ((0 == CONFIG_DISABLE_ACTIVE) ? 1 : 0); i++)
	{
		threads[i] = zbx_thread_start(listener_thread, &sock);
	}

	/* start active chack */
	if(0 == CONFIG_DISABLE_ACTIVE)
	{
		activechk_args.host = CONFIG_HOSTS_ALLOWED;
		activechk_args.port = (unsigned short)CONFIG_SERVER_PORT;

		threads[i] = zbx_thread_start(active_checks_thread, &activechk_args);
	}

	/* Must be called after all child processes loading. */
	init_main_process();

	/* wait for all threads exiting */
	for(i = 0; i < CONFIG_ZABBIX_FORKS; i++)
	{
		if(zbx_thread_wait(threads[i]))
		{
			zabbix_log( LOG_LEVEL_DEBUG, "%li: thread is terminated", threads[i]);
			ZBX_DO_EXIT();
		}
	}

	free_collector_data();

	zbx_free(threads);

	zbx_on_exit();

	return SUCCEED;
}

void	zbx_on_exit()
{

#if !defined(_WINDOWS)
	
	int i = 0;

	if(threads != NULL)
	{
		for(i = 0; i<CONFIG_ZABBIX_FORKS ; i++)
		{
			if(threads[i]) {
				kill(threads[i],SIGTERM);
				threads[i] = (ZBX_THREAD_HANDLE)NULL;
			}
		}
	}
	
#endif /* not _WINDOWS */
	
	zabbix_log(LOG_LEVEL_DEBUG, "zbx_on_exit() called.");

#ifdef USE_PID_FILE

	daemon_stop();

#endif /* USE_PID_FILE */

	free_collector_data();
	alias_list_free();

	zbx_sleep(2); /* wait for all threads closing */
	
	zabbix_log(LOG_LEVEL_INFORMATION, "ZABBIX Agent stopped");
	zabbix_close_log();

	exit(SUCCEED);
}

static char* get_programm_name(char *path)
{
	char	*p;
	char	*filename;

	for(filename = p = path; p && *p; p++)
		if(*p == '\\' || *p == '/')
			filename = p+1;

	return filename;
}

#ifndef ZABBIX_TEST

int	main(int argc, char **argv)
{
	int	task = ZBX_TASK_START;

	progname = get_programm_name(argv[0]);

	task = parse_commandline(argc, argv);

	import_symbols();

	init_metrics(); /* Must be before load_config().  load_config - use metrics!!! */

	load_config(task == 0);

	load_user_parameters();

	if(FAIL == zbx_sock_init())
		exit(FAIL);

	switch(task)
	{

#if defined (_WINDOWS)
		case ZBX_TASK_INSTALL_SERVICE:
			exit(ZabbixCreateService(argv[0]));
			break;
		case ZBX_TASK_UNINSTALL_SERVICE:
			exit(ZabbixRemoveService());
			break;
		case ZBX_TASK_START_SERVICE:
			exit(ZabbixStartService());
			break;
		case ZBX_TASK_STOP_SERVICE:
			exit(ZabbixStopService());
			break;
#endif /* _WINDOWS */
		case ZBX_TASK_PRINT_SUPPORTED:
			test_parameters();
			exit(SUCCEED);
			break;
		case ZBX_TASK_TEST_METRIC:
			test_parameter(TEST_METRIC);
			exit(SUCCEED);
			break;
		case ZBX_TASK_SHOW_USAGE:
			usage();
			exit(FAIL);
			break;
	}

	START_MAIN_ZABBIX_ENTRY(CONFIG_ALLOW_ROOT_PERMISSION);

	exit(SUCCEED);
}

#else /* ZABBIX_TEST */

#include "messages.h"

int main()
{
#if ON
	int res, val;

	if(FAIL == zbx_sock_init())
	{
		return 1;
	}


	res = check_ntp("142.3.100.15",123,&val);

	zbx_error("check_ntp result '%i' value '%i'", res, val);

#elif OFF

	zbx_error("%s",strerror_from_module(MSG_ZABBIX_MESSAGE, NULL));

#elif OFF
	
	char buffer[100*1024];

	get_http_page("www.zabbix.com", "", 80, buffer, 100*1024);

	printf("Back [%d] [%s]\n", strlen(buffer), buffer);
	
#elif OFF

	char s[] = "ABCDEFGH";
	char p[] = "D(.){0,}E";
	int len=2;

	printf("String: \t %s\n", s);
	printf("Pattern:\t %s\n", p);
	printf("Result: \t [%s] [%d]\n", zbx_regexp_match(s, p, &len), len);
/*
#elif OFF or ON

Place your test code HERE!!!
*/

#endif /* 0 */

	return 0;
}

#endif /* not ZABBIX_TEST */

