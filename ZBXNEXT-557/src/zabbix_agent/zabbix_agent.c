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
#include "zabbix_agent.h"

#include "comms.h"
#include "cfg.h"
#include "log.h"
#include "sysinfo.h"
#include "zbxconf.h"
#include "zbxgetopt.h"
#include "alias.h"

const char	*progname = NULL;
const char	title_message[] = "Zabbix Agent";
const char	usage_message[] = "[-Vhp] [-c <file>] [-t <item>]";
#ifndef HAVE_GETOPT_LONG
const char	*help_message[] = {
	"Options:",
	"  -c <file>     Specify configuration file. Use absolute path",
	"  -h            give this help",
	"  -V            display version number",
	"  -p            print supported items and exit",
	"  -t <item>     test specified item and exit",
	0 /* end of text */
};
#else
const char	*help_message[] = {
	"Options:",
	"  -c --config <file>  Specify configuration file. Use absolute path",
	"  -h --help           give this help",
	"  -V --version        display version number",
	"  -p --print          print supported items and exit",
	"  -t --test <item>    test specified item and exit",
	0 /* end of text */
};
#endif

struct zbx_option longopts[] =
{
	{"config",	1,	0,	'c'},
	{"help",	0,	0,	'h'},
	{"version",	0,	0,	'V'},
	{"print",	0,	0,	'p'},
	{"test",	1,	0,	't'},
	{0,0,0,0}
};

void	child_signal_handler( int sig )
{
	if( SIGALRM == sig )
	{
		signal( SIGALRM, child_signal_handler );
	}

	if( SIGQUIT == sig || SIGINT == sig || SIGTERM == sig )
	{
	}
	exit( FAIL );
}

static char	DEFAULT_CONFIG_FILE[] = "/etc/zabbix/zabbix_agent.conf";

void    init_config(void)
{
	struct cfg_line cfg[] =
	{
/*               PARAMETER      ,VAR    ,FUNC,  TYPE(0i,1s),MANDATORY,MIN,MAX
*/
		{"Server",&CONFIG_HOSTS_ALLOWED,0,TYPE_STRING,PARM_MAND,0,0},
		{"Timeout",&CONFIG_TIMEOUT,0,TYPE_INT,PARM_OPT,1,30},
		{0}
	};

	parse_cfg_file(CONFIG_FILE, cfg);
}

int	main(int argc, char **argv)
{
	char		ch;
	int		task = ZBX_TASK_START;
	char		*TEST_METRIC = NULL;
	zbx_sock_t	s_in;
	zbx_sock_t	s_out;

	int		ret;
	char		**value, *command;

	AGENT_RESULT	result;

	memset(&result, 0, sizeof(AGENT_RESULT));

	progname = get_program_name(argv[0]);

/* Parse the command-line. */
	while ((ch = (char)zbx_getopt_long(argc, argv, "c:hVpt:", longopts, NULL)) != (char)EOF)
		switch (ch)
		{
			case 'c':
				CONFIG_FILE = strdup(zbx_optarg);
				break;
			case 'h':
				help();
				exit(-1);
				break;
			case 'V':
				version();
#ifdef _AIX
				tl_version();
#endif /* _AIX */
				exit(-1);
				break;
			case 'p':
				if (task == ZBX_TASK_START)
					task = ZBX_TASK_PRINT_SUPPORTED;
				break;
			case 't':
				if (task == ZBX_TASK_START)
				{
					task = ZBX_TASK_TEST_METRIC;
					TEST_METRIC = strdup(zbx_optarg);
				}
				break;
			default:
				task = ZBX_TASK_SHOW_USAGE;
				break;
		}

	if (CONFIG_FILE == NULL)
		CONFIG_FILE = DEFAULT_CONFIG_FILE;

	init_metrics(); /* Must be before init_config() */

	if (ZBX_TASK_START == task)
	{
		load_config();
		load_user_parameters(0);
	}

	/* Do not create debug files */
	zabbix_open_log(LOG_TYPE_SYSLOG, LOG_LEVEL_EMPTY, NULL);

	switch (task)
	{
		case ZBX_TASK_PRINT_SUPPORTED:
			load_user_parameters(1);
			test_parameters();
			exit(-1);
			break;
		case ZBX_TASK_TEST_METRIC:
			load_user_parameters(1);
			test_parameter(TEST_METRIC, PROCESS_TEST);
			exit(-1);
			break;
		case ZBX_TASK_SHOW_USAGE:
			usage();
			exit(-1);
			break;
	}

	signal(SIGINT,  child_signal_handler);
	signal(SIGTERM, child_signal_handler);
	signal(SIGQUIT, child_signal_handler);
	signal(SIGALRM, child_signal_handler);

	alarm(CONFIG_TIMEOUT);

	zbx_tcp_init(&s_in, (ZBX_SOCKET)fileno(stdin));
	zbx_tcp_init(&s_out, (ZBX_SOCKET)fileno(stdout));

	if( SUCCEED == (ret = zbx_tcp_check_security(&s_in, CONFIG_HOSTS_ALLOWED, 0)) )
	{
		if( SUCCEED == (ret = zbx_tcp_recv(&s_in, &command)) )
		{
			zbx_rtrim(command, "\r\n");

			zabbix_log(LOG_LEVEL_DEBUG, "Requested [%s]", command);

			init_result(&result);

			process(command, 0, &result);

			if( NULL == (value = GET_TEXT_RESULT(&result)) )
				value = GET_MSG_RESULT(&result);

			if(value)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "Sending back [%s]", *value);

				ret = zbx_tcp_send(&s_out, *value);
			}

			free_result(&result);
		}

		if( FAIL == ret )
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Processing error: %s", zbx_tcp_strerror());
		}
	}

	fflush(stdout);

	free_metrics();
	alias_list_free();

	alarm(0);

	zabbix_close_log();

	return SUCCEED;
}
