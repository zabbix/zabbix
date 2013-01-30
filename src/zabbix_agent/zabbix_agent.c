/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "comms.h"
#include "cfg.h"
#include "log.h"
#include "sysinfo.h"
#include "zbxconf.h"
#include "zbxgetopt.h"
#include "alias.h"

const char	*progname = NULL;
const char	title_message[] = "Zabbix agent";
const char	usage_message[] = "[-Vhp] [-c <file>] [-t <item>]";

const char	*help_message[] = {
	"Options:",
	"  -c --config <file>  Absolute path to the configuration file",
	"  -p --print          Print known items and exit",
	"  -t --test <item>    Test specified item and exit",
	"  -h --help           Give this help",
	"  -V --version        Display version number",
	NULL	/* end of text */
};

static struct zbx_option	longopts[] =
{
	{"config",	1,	NULL,	'c'},
	{"help",	0,	NULL,	'h'},
	{"version",	0,	NULL,	'V'},
	{"print",	0,	NULL,	'p'},
	{"test",	1,	NULL,	't'},
	{NULL}
};

void	child_signal_handler(int sig)
{
	if (SIGALRM == sig)
		signal(SIGALRM, child_signal_handler);

	exit(FAIL);
}

static char	DEFAULT_CONFIG_FILE[] = SYSCONFDIR "/zabbix_agent.conf";

/******************************************************************************
 *                                                                            *
 * Function: zbx_load_config                                                  *
 *                                                                            *
 * Purpose: load configuration from config file                               *
 *                                                                            *
 * Parameters: optional - do not produce error if config file missing         *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Vladimir Levijev                                                   *
 *                                                                            *
 * Comments:                                                                  *
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
		{"Timeout",			&CONFIG_TIMEOUT,			TYPE_INT,
			PARM_OPT,	1,			30},
		{"UnsafeUserParameters",	&CONFIG_UNSAFE_USER_PARAMETERS,		TYPE_INT,
			PARM_OPT,	0,			1},
		{"Alias",			&CONFIG_ALIASES,			TYPE_MULTISTRING,
			PARM_OPT,	0,			0},
		{"UserParameter",		&CONFIG_USER_PARAMETERS,		TYPE_MULTISTRING,
			PARM_OPT,	0,			0},
		{NULL}
	};

	/* initialize multistrings */
	zbx_strarr_init(&CONFIG_ALIASES);
	zbx_strarr_init(&CONFIG_USER_PARAMETERS);

	parse_cfg_file(CONFIG_FILE, cfg, optional, ZBX_CFG_STRICT);

	zbx_trim_str_list(CONFIG_HOSTS_ALLOWED, ',');
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_free_config                                                  *
 *                                                                            *
 * Purpose: free configuration memory                                         *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Vladimir Levijev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	zbx_free_config()
{
	zbx_strarr_free(CONFIG_ALIASES);
	zbx_strarr_free(CONFIG_USER_PARAMETERS);
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

	progname = get_program_name(argv[0]);

	/* parse the command-line */
	while ((char)EOF != (ch = (char)zbx_getopt_long(argc, argv, "c:hVpt:", longopts, NULL)))
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
				usage();
				exit(FAIL);
				break;
		}
	}

	if (NULL == CONFIG_FILE)
		CONFIG_FILE = DEFAULT_CONFIG_FILE;

	/* load configuration */
	if (ZBX_TASK_PRINT_SUPPORTED == task || ZBX_TASK_TEST_METRIC == task)
		zbx_load_config(ZBX_CFG_FILE_OPTIONAL);
	else
		zbx_load_config(ZBX_CFG_FILE_REQUIRED);

	/* metrics should be initialized before loading user parameters */
	init_metrics();

	/* user parameters */
	load_user_parameters(CONFIG_USER_PARAMETERS);

	/* aliases */
	load_aliases(CONFIG_ALIASES);

	zbx_free_config();

	/* do not create debug files */
	zabbix_open_log(LOG_TYPE_SYSLOG, LOG_LEVEL_EMPTY, NULL);

	switch (task)
	{
		case ZBX_TASK_TEST_METRIC:
		case ZBX_TASK_PRINT_SUPPORTED:
			if (ZBX_TASK_TEST_METRIC == task)
				test_parameter(TEST_METRIC, PROCESS_TEST);
			else
				test_parameters();
			zabbix_close_log();
			free_metrics();
			alias_list_free();
			exit(SUCCEED);
			break;
		default:
			/* do nothing */
			break;
	}

	signal(SIGINT,  child_signal_handler);
	signal(SIGTERM, child_signal_handler);
	signal(SIGQUIT, child_signal_handler);
	signal(SIGALRM, child_signal_handler);

	alarm(CONFIG_TIMEOUT);

	zbx_tcp_init(&s_in, (ZBX_SOCKET)fileno(stdin));
	zbx_tcp_init(&s_out, (ZBX_SOCKET)fileno(stdout));

	if (SUCCEED == (ret = zbx_tcp_check_security(&s_in, CONFIG_HOSTS_ALLOWED, 0)))
	{
		if (SUCCEED == (ret = zbx_tcp_recv(&s_in, &command)))
		{
			zbx_rtrim(command, "\r\n");

			zabbix_log(LOG_LEVEL_DEBUG, "Requested [%s]", command);

			init_result(&result);

			process(command, 0, &result);

			if (NULL == (value = GET_TEXT_RESULT(&result)))
				value = GET_MSG_RESULT(&result);

			if (NULL != value)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "Sending back [%s]", *value);

				ret = zbx_tcp_send(&s_out, *value);
			}

			free_result(&result);
		}

		if (FAIL == ret)
			zabbix_log(LOG_LEVEL_DEBUG, "Processing error: %s", zbx_tcp_strerror());
	}

	fflush(stdout);

	alarm(0);

	zabbix_close_log();

	free_metrics();
	alias_list_free();

	return SUCCEED;
}
