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
#include "comms.h"
#include "cfg.h"
#include "log.h"
#include "sysinfo.h"
#include "zbxconf.h"
#include "zbxgetopt.h"
#include "zbxmodules.h"
#include "alias.h"
#include "sighandler.h"
#include "threads.h"

const char	*progname = NULL;
const char	title_message[] = "zabbix_agent";
const char	syslog_app_name[] = "zabbix_agent";
const char	*usage_message[] = {
	"[-c config-file]",
	"[-c config-file] -p",
	"[-c config-file] -t item-key",
	"-h",
	"-V",
	NULL	/* end of text */
};

unsigned char process_type	= 255;	/* ZBX_PROCESS_TYPE_UNKNOWN */
int process_num;
int server_num			= 0;

const char	*help_message[] = {
	"A Zabbix executable for monitoring of various server parameters, to be started upon request by inetd.",
	"",
	"Options:",
	"  -c --config config-file  Absolute path to the configuration file",
	"  -p --print               Print known items and exit",
	"  -t --test item-key       Test specified item and exit",
	"",
	"  -h --help                Display this help message",
	"  -V --version             Display version number",
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
		{"Server",			&CONFIG_HOSTS_ALLOWED,			TYPE_STRING_LIST,
			PARM_MAND,	0,			0},
		{"Timeout",			&CONFIG_TIMEOUT,			TYPE_INT,
			PARM_OPT,	1,			30},
		{"UnsafeUserParameters",	&CONFIG_UNSAFE_USER_PARAMETERS,		TYPE_INT,
			PARM_OPT,	0,			1},
		{"Alias",			&CONFIG_ALIASES,			TYPE_MULTISTRING,
			PARM_OPT,	0,			0},
		{"UserParameter",		&CONFIG_USER_PARAMETERS,		TYPE_MULTISTRING,
			PARM_OPT,	0,			0},
		{"LoadModulePath",		&CONFIG_LOAD_MODULE_PATH,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"LoadModule",			&CONFIG_LOAD_MODULE,			TYPE_MULTISTRING,
			PARM_OPT,	0,			0},
		{NULL}
	};

	/* initialize multistrings */
	zbx_strarr_init(&CONFIG_ALIASES);
	zbx_strarr_init(&CONFIG_LOAD_MODULE);
	zbx_strarr_init(&CONFIG_USER_PARAMETERS);

	parse_cfg_file(CONFIG_FILE, cfg, optional, ZBX_CFG_STRICT);
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
static void	zbx_free_config(void)
{
	zbx_strarr_free(CONFIG_ALIASES);
	zbx_strarr_free(CONFIG_LOAD_MODULE);
	zbx_strarr_free(CONFIG_USER_PARAMETERS);
}

int	main(int argc, char **argv)
{
	char		ch;
	int		task = ZBX_TASK_START;
	char		*TEST_METRIC = NULL;
	zbx_sock_t	s_in;
	zbx_sock_t	s_out;

	int		ret, opt_c = 0, opt_p = 0, opt_t = 0;
	char		**value;

	AGENT_RESULT	result;

	progname = get_program_name(argv[0]);

	/* parse the command-line */
	while ((char)EOF != (ch = (char)zbx_getopt_long(argc, argv, "c:hVpt:", longopts, NULL)))
	{
		switch (ch)
		{
			case 'c':
				opt_c++;
				if (NULL == CONFIG_FILE)
					CONFIG_FILE = zbx_strdup(NULL, zbx_optarg);
				break;
			case 'h':
				help();
				exit(EXIT_SUCCESS);
				break;
			case 'V':
				version();
#ifdef _AIX
				printf("\n");
				tl_version();
#endif
				exit(EXIT_SUCCESS);
				break;
			case 'p':
				opt_p++;
				if (ZBX_TASK_START == task)
					task = ZBX_TASK_PRINT_SUPPORTED;
				break;
			case 't':
				opt_t++;
				if (ZBX_TASK_START == task)
				{
					task = ZBX_TASK_TEST_METRIC;
					TEST_METRIC = zbx_strdup(TEST_METRIC, zbx_optarg);
				}
				break;
			default:
				usage();
				exit(EXIT_FAILURE);
				break;
		}
	}

	/* every option may be specified only once */
	if (1 < opt_c || 1 < opt_p || 1 < opt_t)
	{
		if (1 < opt_c)
			zbx_error("option \"-c\" or \"--config\" specified multiple times");
		if (1 < opt_p)
			zbx_error("option \"-p\" or \"--print\" specified multiple times");
		if (1 < opt_t)
			zbx_error("option \"-t\" or \"--test\" specified multiple times");

		exit(EXIT_FAILURE);
	}

	/* check for mutually exclusive options */
	if (1 < opt_p + opt_t)
	{
		zbx_error("only one of options \"-p\", \"--print\", \"-t\" or \"--test\" can be used");
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
		CONFIG_FILE = DEFAULT_CONFIG_FILE;

	/* load configuration */
	if (ZBX_TASK_PRINT_SUPPORTED == task || ZBX_TASK_TEST_METRIC == task)
		zbx_load_config(ZBX_CFG_FILE_OPTIONAL);
	else
		zbx_load_config(ZBX_CFG_FILE_REQUIRED);

	/* set defaults */
	if (NULL == CONFIG_LOAD_MODULE_PATH)
		CONFIG_LOAD_MODULE_PATH = zbx_strdup(CONFIG_LOAD_MODULE_PATH, LIBDIR "/modules");

	zbx_set_common_signal_handlers();

	/* metrics should be initialized before loading user parameters */
	init_metrics();

	/* loadable modules */
	if (FAIL == load_modules(CONFIG_LOAD_MODULE_PATH, CONFIG_LOAD_MODULE, CONFIG_TIMEOUT, 0))
	{
		zabbix_log(LOG_LEVEL_CRIT, "loading modules failed, exiting...");
		exit(EXIT_FAILURE);
	}

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
				test_parameter(TEST_METRIC);
			else
				test_parameters();
			zbx_on_exit();
			break;
		default:
			/* do nothing */
			break;
	}

	alarm(CONFIG_TIMEOUT);

	zbx_tcp_init(&s_in, (ZBX_SOCKET)fileno(stdin));
	zbx_tcp_init(&s_out, (ZBX_SOCKET)fileno(stdout));

	if (SUCCEED == (ret = zbx_tcp_check_security(&s_in, CONFIG_HOSTS_ALLOWED, 0)))
	{
		if (SUCCEED == (ret = zbx_tcp_recv(&s_in)))
		{
			zbx_rtrim(s_in.buffer, "\r\n");

			zabbix_log(LOG_LEVEL_DEBUG, "requested [%s]", s_in.buffer);

			init_result(&result);

			process(s_in.buffer, 0, &result);

			if (NULL == (value = GET_TEXT_RESULT(&result)))
				value = GET_MSG_RESULT(&result);

			if (NULL != value)
			{
				zabbix_log(LOG_LEVEL_DEBUG, "sending back [%s]", *value);

				ret = zbx_tcp_send(&s_out, *value);
			}

			free_result(&result);
		}

		if (FAIL == ret)
			zabbix_log(LOG_LEVEL_DEBUG, "processing error: %s", zbx_tcp_strerror());
	}

	fflush(stdout);

	alarm(0);

	zbx_on_exit();

	return SUCCEED;
}

void	zbx_on_exit(void)
{
	unload_modules();
	zabbix_close_log();

	free_metrics();
	alias_list_free();

	exit(EXIT_SUCCESS);
}
