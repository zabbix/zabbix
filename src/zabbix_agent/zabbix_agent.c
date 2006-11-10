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

#include "config.h"

#include "common.h"
#include "cfg.h"
#include "log.h"
#include "sysinfo.h"
#include "security.h"
#include "zabbix_agent.h"

char *progname = NULL;
char title_message[] = "ZABBIX Agent";
char usage_message[] = "[-vhp] [-c <file>] [-t <metric>]";
#ifndef HAVE_GETOPT_LONG
char *help_message[] = {
	"Options:",
	"  -c <file>     Specify configuration file",
	"  -h            give this help",
	"  -v            display version number",
	"  -p            print supported metrics and exit",
	"  -t <metric>   test specified metric and exit",
	0 /* end of text */
};
#else
char *help_message[] = {
	"Options:",
	"  -c --config <file>  Specify configuration file",
	"  -h --help           give this help",
	"  -v --version        display version number",
	"  -p --print          print supported metrics and exit",
	"  -t --test <metric>  test specified metric and exit",
	0 /* end of text */
};
#endif

struct option longopts[] =
{
	{"config",	1,	0,	'c'},
	{"help",	0,	0,	'h'},
	{"version",	0,	0,	'v'},
	{"print",	0,	0,	'p'},
	{"test",	1,	0,	't'},
	{0,0,0,0}
};

char	*CONFIG_FILE			= NULL;
static	char	*CONFIG_HOSTS_ALLOWED	= NULL;
static	int	CONFIG_TIMEOUT		= AGENT_TIMEOUT;
int		CONFIG_ENABLE_REMOTE_COMMANDS	= 0;

void	signal_handler( int sig )
{
	if( SIGALRM == sig )
	{
		signal( SIGALRM, signal_handler );
	}
 
	if( SIGQUIT == sig || SIGINT == sig || SIGTERM == sig )
	{
	}
	exit( FAIL );
}

int	add_parameter(char *value)
{
	char	*value2;

	value2=strstr(value,",");
	if(NULL == value2)
	{
		return	FAIL;
	}
	value2[0]=0;
	value2++;
	add_user_parameter(value, value2);
	return	SUCCEED;
}

void    init_config(void)
{
	struct cfg_line cfg[]=
	{
/*               PARAMETER      ,VAR    ,FUNC,  TYPE(0i,1s),MANDATORY,MIN,MAX
*/
		{"Server",&CONFIG_HOSTS_ALLOWED,0,TYPE_STRING,PARM_MAND,0,0},
		{"Timeout",&CONFIG_TIMEOUT,0,TYPE_INT,PARM_OPT,1,30},
		{"UserParameter",0,&add_parameter,0,0,0,0},
		{0}
	};

	if(CONFIG_FILE == NULL)
	{
		CONFIG_FILE = strdup("/etc/zabbix/zabbix_agent.conf");
	}
	
	parse_cfg_file(CONFIG_FILE,cfg);
}

int	main(int argc, char **argv)
{
	char		s[MAX_STRING_LEN];
	char		value[MAX_STRING_LEN];
	int             ch;
	int		task = ZBX_TASK_START;
	char		*TEST_METRIC = NULL;
	AGENT_RESULT	result;

	memset(&result, 0, sizeof(AGENT_RESULT));

	progname = argv[0];

/* Parse the command-line. */
	while ((ch = getopt_long(argc, argv, "c:hvpt:", longopts, NULL)) != EOF)
		switch ((char) ch) {
		case 'c':
			CONFIG_FILE = optarg;
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
				TEST_METRIC = optarg;
			}
			break;
		default:
			task = ZBX_TASK_SHOW_USAGE;
			break;
	}

/* Must be before init_config() */
	init_metrics();
	init_config();

/* Do not create debug files */
	zabbix_open_log(LOG_TYPE_SYSLOG,LOG_LEVEL_EMPTY,NULL);

        switch(task)
        {
                case ZBX_TASK_PRINT_SUPPORTED:
                        test_parameters();
                        exit(-1);
                        break;
                case ZBX_TASK_TEST_METRIC:
                        test_parameter(TEST_METRIC);
                        exit(-1);
                        break;
                case ZBX_TASK_SHOW_USAGE:
                        usage();
                        exit(-1);
                        break;
        }

	signal( SIGINT,  signal_handler );
	signal( SIGQUIT, signal_handler );
	signal( SIGTERM, signal_handler );
	signal( SIGALRM, signal_handler );

	alarm(CONFIG_TIMEOUT);

	if(check_security(0,CONFIG_HOSTS_ALLOWED,0) == FAIL)
	{
		exit(FAIL);
	}

	fgets(s,MAX_STRING_LEN,stdin);
	
	process(s, 0, &result);
	if(result.type & AR_DOUBLE)
		snprintf(value, MAX_STRING_LEN-1, "%f", result.dbl);
	else if(result.type & AR_UINT64)
		snprintf(value, MAX_STRING_LEN-1, ZBX_FS_UI64, result.ui64);
	else if(result.type & AR_STRING)
		snprintf(value, MAX_STRING_LEN-1, "%s", result.str);
	else if(result.type & AR_TEXT)
		snprintf(value, MAX_STRING_LEN-1, "%s", result.text);
	else if(result.type & AR_MESSAGE)
		snprintf(value, MAX_STRING_LEN-1, "%s", result.msg);
	free_result(&result);
  
	printf("%s\n",value);

	fflush(stdout);

	alarm(0);

	return SUCCEED;
}

