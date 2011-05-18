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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"
#include "threads.h"
#include "comms.h"
#include "cfg.h"
#include "log.h"
#include "zbxgetopt.h"
#include "zbxjson.h"

#define LOG(f) if (NULL != LOG_FILE) f;

const char	*progname = NULL;
const char	title_message[] = "Zabbix Snmp Trap Handler";
const char	usage_message[] = "[-Vhv] -o <file> [-l <file>]";

#ifdef HAVE_GETOPT_LONG
const char	*help_message[] = {
	"Options:",
	"  -o --output-file <file>              Specify absolute path to the output file",
	"  -l --logfile <file>                  Specify absolute path to the logfile",
	"  -t --with-snmptt                     Handler will be called by SNMPTT",
	"",
	"  -v --verbose                         Verbose mode, -vv for more details",
	"",
	" Other options:",
	"  -h --help                            Give this help",
	"  -V --version                         Display version number",
	0 /* end of text */
};
#else
const char	*help_message[] = {
	"Options:",
	"  -o <file>                    Specify absolute path to the output file",
	"  -l <file>                    Specify absolute path to the logfile",
	"  -t                           Handler will be called by SNMPTT",
	"",
	"  -v --verbose                 Verbose mode, -vv for more details",
	"",
	" Other options:",
	"  -h                           Give this help",
	"  -V                           Display version number",
	0 /* end of text */
};
#endif

/* COMMAND LINE OPTIONS */

/* long options */

static struct zbx_option longopts[] =
{
	{"output-file",		1,	NULL,	'o'},
	{"logfile",		1,	NULL,	'l'},
	{"help",		0,	NULL,	'h'},
	{"verbose",		0,	NULL,	'v'},
	{"version",		0,	NULL,	'V'},
	{0,0,0,0}
};

/* short options */

static char	shortopts[] = "o:l:thVv";

/* end of COMMAND LINE OPTIONS */

static int	USE_SNMPTT = 0;
static int	CONFIG_LOG_LEVEL = LOG_LEVEL_CRIT;
static char*	OUTPUT_FILE = NULL;
static char*	LOG_FILE = NULL;

static zbx_task_t parse_commandline(int argc, char **argv)
{
	zbx_task_t      task    = ZBX_TASK_START;
	char    ch      = '\0';

	/* parse the command-line */
	while ((char)EOF != (ch = (char)zbx_getopt_long(argc, argv, shortopts, longopts, NULL)))
	{
		switch (ch) {
			case 'o':
				OUTPUT_FILE = strdup(zbx_optarg);
				break;
			case 'l':
				LOG_FILE = strdup(zbx_optarg);
				break;
			case 't':
				USE_SNMPTT = 1;
				break;
			case 'h':
				help();
				exit(FAIL);
				break;
			case 'V':
				version();
				exit(FAIL);
				break;
			case 'v':
				if(CONFIG_LOG_LEVEL == LOG_LEVEL_WARNING)
					CONFIG_LOG_LEVEL = LOG_LEVEL_DEBUG;
				else
					CONFIG_LOG_LEVEL = LOG_LEVEL_WARNING;
				break;
			default:
				usage();
				exit(FAIL);
				break;
		}
	}

	if (NULL == OUTPUT_FILE)
	{
		usage();
		exit(FAIL);
	}

	return task;
}

static int parse_snmptrapd_input(char *buffer, size_t size, int *offset)
{
	const char	*__function_name = "parse_snmptrapd_input";
	char		line[1024];
	int		ret = FAIL;

	LOG(zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name));

	if (NULL == fgets(line, sizeof(line), stdin)) /* host */
	{
		LOG(zabbix_log(LOG_LEVEL_CRIT, "could not get host name"));
		goto exit;
	}

	zbx_rtrim(line, " \n\r");
	*offset += zbx_snprintf(buffer + *offset, size - *offset, "%s (", line);

	if (NULL == fgets(line, sizeof(line), stdin)) /* IP */
	{
		LOG(zabbix_log(LOG_LEVEL_CRIT, "could not get IP"));
		goto exit;
	}

	zbx_rtrim(line, " \n\r");
	*offset += zbx_snprintf(buffer + *offset, size - *offset, "%s): ", line);

	while (NULL != fgets(line, sizeof(line), stdin))
	{
		zbx_rtrim(line, " \n\r");
		*offset += zbx_snprintf(buffer + *offset, size - *offset, "%s, ", line);
	}

	zbx_rtrim(buffer, ", ");

	ret = SUCCEED;
exit:
	LOG(zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret)));

	return ret;
}

int main(int argc, char ** argv)
{
	int	fd, offset = 0, task = ZBX_TASK_START, ret = FAIL;
	char 	buffer[1024*50];

	LOG(zabbix_open_log(LOG_TYPE_UNDEFINED, CONFIG_LOG_LEVEL, LOG_FILE));

	progname = get_program_name(argv[0]);

	task = parse_commandline(argc, argv);

	*buffer = '\0';

	if (1 == USE_SNMPTT)
		ret = FAIL;
	else
		ret = parse_snmptrapd_input(buffer, sizeof(buffer), &offset);

	if (0 < offset)
		buffer[offset]= '\n';

	if (-1 == (fd = open(OUTPUT_FILE, O_WRONLY | O_APPEND | O_CREAT, 0666 )))
	{
		LOG(zabbix_log(LOG_LEVEL_CRIT, "could not open \"%s\"", OUTPUT_FILE));
		goto close;
	}
	else if (0 != flock(fd, LOCK_EX))
	{
		LOG(zabbix_log(LOG_LEVEL_CRIT, "could not lock \"%s\"", OUTPUT_FILE));
		goto close;
	}

	LOG(zabbix_log(LOG_LEVEL_DEBUG, "opened file \"%s\"", OUTPUT_FILE));
	write(fd, buffer, offset + 1);
close:
	close(fd);

	LOG(zabbix_log(LOG_LEVEL_DEBUG, "closed file \"%s\"", OUTPUT_FILE));

	if (NULL != LOG_FILE)
		zabbix_close_log();

	return ret;
}
