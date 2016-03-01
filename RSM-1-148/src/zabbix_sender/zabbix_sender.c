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

#include "threads.h"
#include "comms.h"
#include "cfg.h"
#include "log.h"
#include "zbxgetopt.h"
#include "zbxjson.h"

const char	*progname = NULL;
const char	title_message[] = "Zabbix Sender";
const char	usage_message[] = "[-Vhv] {[-zpsI] -ko | [-zpI] -T -i <file> -r} [-c <file>]";

const char	*help_message[] = {
	"Options:",
	"  -c --config <file>                   Absolute path to the configuration file",
	"",
	"  -z --zabbix-server <server>          Hostname or IP address of Zabbix server",
	"  -p --port <server port>              Specify port number of server trapper running on the server. Default is " ZBX_DEFAULT_SERVER_PORT_STR,
	"  -s --host <hostname>                 Specify host name. Host IP address and DNS name will not work",
	"  -I --source-address <IP address>     Specify source IP address",
	"",
	"  -k --key <key>                       Specify item key",
	"  -o --value <key value>               Specify value",
	"",
	"  -i --input-file <input file>         Load values from input file. Specify - for standard input",
	"                                       Each line of file contains whitespace delimited: <hostname> <key> <value>",
	"                                       Specify - in <hostname> to use hostname from configuration file or --host argument",
	"  -T --with-timestamps                 Each line of file contains whitespace delimited: <hostname> <key> <timestamp> <value>",
	"                                       This can be used with --input-file option",
	"                                       Timestamp should be specified in Unix timestamp format",
	"  -r --real-time                       Send metrics one by one as soon as they are received",
	"                                       This can be used when reading from standard input",
	"",
	"  -v --verbose                         Verbose mode, -vv for more details",
	"",
	"Other options:",
	"  -h --help                            Give this help",
	"  -V --version                         Display version number",
	NULL	/* end of text */
};

/* COMMAND LINE OPTIONS */

/* long options */
static struct zbx_option	longopts[] =
{
	{"config",		1,	NULL,	'c'},
	{"zabbix-server",	1,	NULL,	'z'},
	{"port",		1,	NULL,	'p'},
	{"host",		1,	NULL,	's'},
	{"source-address",	1,	NULL,	'I'},
	{"key",			1,	NULL,	'k'},
	{"value",		1,	NULL,	'o'},
	{"input-file",		1,	NULL,	'i'},
	{"with-timestamps",	0,	NULL,	'T'},
	{"real-time",		0,	NULL,	'r'},
	{"verbose",		0,	NULL,	'v'},
	{"help",		0,	NULL,	'h'},
	{"version",		0,	NULL,	'V'},
	{NULL}
};

/* short options */
static char	shortopts[] = "c:I:z:p:s:k:o:Ti:rvhV";

/* end of COMMAND LINE OPTIONS */

static int	CONFIG_LOG_LEVEL = LOG_LEVEL_CRIT;

static char	*INPUT_FILE = NULL;
static int	WITH_TIMESTAMPS = 0;
static int	REAL_TIME = 0;

static char	*CONFIG_SOURCE_IP = NULL;
static char	*ZABBIX_SERVER = NULL;
unsigned short	ZABBIX_SERVER_PORT = 0;
static char	*ZABBIX_HOSTNAME = NULL;
static char	*ZABBIX_KEY = NULL;
static char	*ZABBIX_KEY_VALUE = NULL;

#if !defined(_WINDOWS)
static void	send_signal_handler(int sig)
{
	if (SIGALRM == sig)
		zabbix_log(LOG_LEVEL_WARNING, "timeout while executing operation");

	/* Calling _exit() to terminate the process immediately is important. See ZBX-5732 for details. */
	_exit(FAIL);
}
#endif

typedef struct
{
	char		*source_ip, *server;
	unsigned short	port;
	struct zbx_json	json;
}
ZBX_THREAD_SENDVAL_ARGS;

/******************************************************************************
 *                                                                            *
 * Function: check_response                                                   *
 *                                                                            *
 * Purpose: Check whether JSON response is SUCCEED                            *
 *                                                                            *
 * Parameters: result SUCCEED or FAIL                                         *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: active agent has almost the same function!                       *
 *                                                                            *
 ******************************************************************************/
static int	check_response(char *response)
{
	struct zbx_json_parse	jp;
	char			value[MAX_STRING_LEN];
	char			info[MAX_STRING_LEN];
	int			ret;

	ret = zbx_json_open(response, &jp);

	if (SUCCEED == ret)
		ret = zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_RESPONSE, value, sizeof(value));

	if (SUCCEED == ret && 0 != strcmp(value, ZBX_PROTO_VALUE_SUCCESS))
		ret = FAIL;

	if (SUCCEED == ret && SUCCEED == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_INFO, info, sizeof(info)))
	{
		printf("info from server: \"%s\"\n", info);
		fflush(stdout);
	}

	return ret;
}

static	ZBX_THREAD_ENTRY(send_value, args)
{
	ZBX_THREAD_SENDVAL_ARGS	*sentdval_args;
	zbx_sock_t		sock;
	char			*answer = NULL;
	int			tcp_ret, ret = FAIL;

	assert(args);
	assert(((zbx_thread_args_t *)args)->args);

	sentdval_args = (ZBX_THREAD_SENDVAL_ARGS *)((zbx_thread_args_t *)args)->args;

#if !defined(_WINDOWS)
	signal(SIGINT,  send_signal_handler);
	signal(SIGTERM, send_signal_handler);
	signal(SIGQUIT, send_signal_handler);
	signal(SIGALRM, send_signal_handler);
#endif

	if (SUCCEED == (tcp_ret = zbx_tcp_connect(&sock, CONFIG_SOURCE_IP, sentdval_args->server, sentdval_args->port, GET_SENDER_TIMEOUT)))
	{
		if (SUCCEED == (tcp_ret = zbx_tcp_send(&sock, sentdval_args->json.buffer)))
		{
			if (SUCCEED == (tcp_ret = zbx_tcp_recv(&sock, &answer)))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "answer [%s]", answer);
				if (NULL == answer || SUCCEED != check_response(answer))
					zabbix_log(LOG_LEVEL_WARNING, "incorrect answer from server [%s]", answer);
				else
					ret = SUCCEED;
			}
		}

		zbx_tcp_close(&sock);
	}

	if (FAIL == tcp_ret)
		zabbix_log(LOG_LEVEL_DEBUG, "send value error: %s", zbx_tcp_strerror());

	zbx_thread_exit(ret);
}

static void    zbx_load_config(const char *config_file)
{
	char	*cfg_source_ip = NULL, *cfg_active_hosts = NULL, *cfg_hostname = NULL, *r = NULL;

	struct cfg_line	cfg[] =
	{
		/* PARAMETER,			VAR,					TYPE,
			MANDATORY,	MIN,			MAX */
		{"SourceIP",			&cfg_source_ip,				TYPE_STRING,
			PARM_OPT,	0,			0},
		{"ServerActive",		&cfg_active_hosts,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"Hostname",			&cfg_hostname,				TYPE_STRING,
			PARM_OPT,	0,			0},
		{NULL}
	};

	if (NULL != config_file)
	{
		/* do not complain about unknown parameters */
		parse_cfg_file(config_file, cfg, ZBX_CFG_FILE_REQUIRED, ZBX_CFG_NOT_STRICT);

		if (NULL != cfg_source_ip)
		{
			if (NULL == CONFIG_SOURCE_IP)
			{
				CONFIG_SOURCE_IP = zbx_strdup(CONFIG_SOURCE_IP, cfg_source_ip);
			}
			zbx_free(cfg_source_ip);
		}

		if (NULL == ZABBIX_SERVER)
		{
			if (NULL != cfg_active_hosts && '\0' != *cfg_active_hosts)
			{
				unsigned short	cfg_server_port = 0;

				if (NULL != (r = strchr(cfg_active_hosts, ',')))
					*r = '\0';

				if (SUCCEED != parse_serveractive_element(cfg_active_hosts, &ZABBIX_SERVER,
						&cfg_server_port, 0))
				{
					zbx_error("error parsing a \"ServerActive\" option: address \"%s\" is invalid",
							cfg_active_hosts);
					exit(EXIT_FAILURE);
				}

				if (0 == ZABBIX_SERVER_PORT && 0 != cfg_server_port)
					ZABBIX_SERVER_PORT = cfg_server_port;
			}
		}
		zbx_free(cfg_active_hosts);

		if (NULL != cfg_hostname)
		{
			if (NULL == ZABBIX_HOSTNAME)
			{
				ZABBIX_HOSTNAME = zbx_strdup(ZABBIX_HOSTNAME, cfg_hostname);
			}
			zbx_free(cfg_hostname);
		}
	}
}

static void	parse_commandline(int argc, char **argv)
{
	char	ch = '\0';

	/* parse the command-line */
	while ((char)EOF != (ch = (char)zbx_getopt_long(argc, argv, shortopts, longopts, NULL)))
	{
		switch (ch)
		{
			case 'c':
				CONFIG_FILE = zbx_strdup(CONFIG_FILE, zbx_optarg);
				break;
			case 'h':
				help();
				exit(-1);
				break;
			case 'V':
				version();
				exit(-1);
				break;
			case 'I':
				CONFIG_SOURCE_IP = zbx_strdup(CONFIG_SOURCE_IP, zbx_optarg);
				break;
			case 'z':
				ZABBIX_SERVER = zbx_strdup(ZABBIX_SERVER, zbx_optarg);
				break;
			case 'p':
				ZABBIX_SERVER_PORT = (unsigned short)atoi(zbx_optarg);
				break;
			case 's':
				ZABBIX_HOSTNAME = zbx_strdup(ZABBIX_HOSTNAME, zbx_optarg);
				break;
			case 'k':
				ZABBIX_KEY = zbx_strdup(ZABBIX_KEY, zbx_optarg);
				break;
			case 'o':
				ZABBIX_KEY_VALUE = zbx_strdup(ZABBIX_KEY_VALUE, zbx_optarg);
				break;
			case 'i':
				INPUT_FILE = zbx_strdup(INPUT_FILE, zbx_optarg);
				break;
			case 'T':
				WITH_TIMESTAMPS = 1;
				break;
			case 'r':
				REAL_TIME = 1;
				break;
			case 'v':
				if (LOG_LEVEL_WARNING == CONFIG_LOG_LEVEL)
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

	if (NULL == ZABBIX_SERVER && NULL == CONFIG_FILE)
	{
		usage();
		exit(FAIL);
	}
}

/* sending a huge amount of values in a single connection is likely to */
/* take long and hit timeout, so we limit values to 250 per connection */
#define VALUES_MAX	250

int	main(int argc, char **argv)
{
	FILE			*in;
	char			in_line[MAX_BUFFER_LEN], hostname[MAX_STRING_LEN], key[MAX_STRING_LEN],
				key_value[MAX_BUFFER_LEN], clock[32];
	int			total_count = 0, succeed_count = 0, buffer_count = 0, read_more = 0, ret = SUCCEED;
	double			last_send = 0;
	const char		*p;
	zbx_thread_args_t	thread_args;
	ZBX_THREAD_SENDVAL_ARGS sentdval_args;

	progname = get_program_name(argv[0]);

	parse_commandline(argc, argv);

	zbx_load_config(CONFIG_FILE);

	zabbix_open_log(LOG_TYPE_UNDEFINED, CONFIG_LOG_LEVEL, NULL);

	if (NULL == ZABBIX_SERVER)
	{
		zabbix_log(LOG_LEVEL_WARNING, "'Server' parameter required");
		goto exit;
	}
	if (0 == ZABBIX_SERVER_PORT)
		ZABBIX_SERVER_PORT = ZBX_DEFAULT_SERVER_PORT;

	if (MIN_ZABBIX_PORT > ZABBIX_SERVER_PORT)
	{
		zabbix_log(LOG_LEVEL_WARNING, "Incorrect port number [%d]. Allowed [%d:%d]",
				(int)ZABBIX_SERVER_PORT, (int)MIN_ZABBIX_PORT, (int)MAX_ZABBIX_PORT);
		goto exit;
	}

	thread_args.thread_num = 0;
	thread_args.args = &sentdval_args;

	sentdval_args.server = ZABBIX_SERVER;
	sentdval_args.port = ZABBIX_SERVER_PORT;

	zbx_json_init(&sentdval_args.json, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addstring(&sentdval_args.json, ZBX_PROTO_TAG_REQUEST, ZBX_PROTO_VALUE_SENDER_DATA, ZBX_JSON_TYPE_STRING);
	zbx_json_addarray(&sentdval_args.json, ZBX_PROTO_TAG_DATA);

	if (INPUT_FILE)
	{
		if (0 == strcmp(INPUT_FILE, "-"))
		{
			in = stdin;
			if (1 == REAL_TIME)
			{
				/* set line buffering on stdin */
				setvbuf(stdin, (char *)NULL, _IOLBF, 1024);
			}
		}
		else if (NULL == (in = fopen(INPUT_FILE, "r")) )
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot open [%s]: %s", INPUT_FILE, zbx_strerror(errno));
			ret = FAIL;
			goto exit;
		}

		while (NULL != fgets(in_line, sizeof(in_line), in) && SUCCEED == ret)	/* <hostname> <key> [<timestamp>] <value> */
		{
			total_count++; /* also used as inputline */

			zbx_rtrim(in_line, "\r\n");

			p = in_line;

			if ('\0' == *p || NULL == (p = get_string(p, hostname, sizeof(hostname))) || '\0' == *hostname)
			{
				zabbix_log(LOG_LEVEL_WARNING, "[line %d] 'Hostname' required", total_count);
				continue;
			}

			if (0 == strcmp(hostname, "-"))
			{
			       if (NULL == ZABBIX_HOSTNAME)
			       {
				       zabbix_log(LOG_LEVEL_WARNING, "[line %d] '-' encountered as 'Hostname', "
							"but no default hostname was specified", total_count);
				       continue;
			       }
			       else
				       zbx_strlcpy(hostname, ZABBIX_HOSTNAME, sizeof(hostname));
			}

			if ('\0' == *p || NULL == (p = get_string(p, key, sizeof(key))) || '\0' == *key)
			{
				zabbix_log(LOG_LEVEL_WARNING, "[line %d] 'Key' required", total_count);
				continue;
			}

			if (1 == WITH_TIMESTAMPS)
			{
				if ('\0' == *p || NULL == (p = get_string(p, clock, sizeof(clock))) || '\0' == *clock)
				{
					zabbix_log(LOG_LEVEL_WARNING, "[line %d] 'Timestamp' required", total_count);
					continue;
				}
			}

			if ('\0' != *p && '"' != *p)
				zbx_strlcpy(key_value, p, sizeof(key_value));
			else if ('\0' == *p || NULL == (p = get_string(p, key_value, sizeof(key_value))) || '\0' == *key_value)
			{
				zabbix_log(LOG_LEVEL_WARNING, "[line %d] 'Key value' required", total_count);
				continue;
			}

			zbx_rtrim(key_value, "\r\n");

			zbx_json_addobject(&sentdval_args.json, NULL);
			zbx_json_addstring(&sentdval_args.json, ZBX_PROTO_TAG_HOST, hostname, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&sentdval_args.json, ZBX_PROTO_TAG_KEY, key, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&sentdval_args.json, ZBX_PROTO_TAG_VALUE, key_value, ZBX_JSON_TYPE_STRING);
			if (1 == WITH_TIMESTAMPS)
				zbx_json_adduint64(&sentdval_args.json, ZBX_PROTO_TAG_CLOCK, atoi(clock));
			zbx_json_close(&sentdval_args.json);

			succeed_count++;
			buffer_count++;

			if (stdin == in && 1 == REAL_TIME)
			{
				/* if there is nothing on standard input after 1/5 seconds, we send what we have */
				/* otherwise, we keep reading, but we should send data at least once per second */

				struct timeval	tv;
				fd_set		read_set;

				tv.tv_sec = 0;
				tv.tv_usec = 200000;

				FD_ZERO(&read_set);
				FD_SET(0, &read_set);	/* stdin is file descriptor 0 */

				if (-1 == (read_more = select(1, &read_set, NULL, NULL, &tv)))
				{
					zabbix_log(LOG_LEVEL_WARNING, "select() failed: %s", zbx_strerror(errno));
				}
				else if (1 <= read_more)
				{
					if (0 == last_send)
						last_send = zbx_time();
					else if (zbx_time() - last_send >= 1)
						read_more = 0;
				}
			}

			if (VALUES_MAX == buffer_count || (stdin == in && 1 == REAL_TIME && 0 >= read_more))
			{
				zbx_json_close(&sentdval_args.json);

				if (1 == WITH_TIMESTAMPS)
				{
					zbx_timespec_t	timespec;

					zbx_timespec(&timespec);
					zbx_json_adduint64(&sentdval_args.json, ZBX_PROTO_TAG_CLOCK, timespec.sec);
					zbx_json_adduint64(&sentdval_args.json, ZBX_PROTO_TAG_NS, timespec.ns);
				}

				last_send = zbx_time();

				ret = zbx_thread_wait(zbx_thread_start(send_value, &thread_args));

				buffer_count = 0;
				zbx_json_clean(&sentdval_args.json);
				zbx_json_addstring(&sentdval_args.json, ZBX_PROTO_TAG_REQUEST, ZBX_PROTO_VALUE_SENDER_DATA,
						ZBX_JSON_TYPE_STRING);
				zbx_json_addarray(&sentdval_args.json, ZBX_PROTO_TAG_DATA);
			}
		}
		zbx_json_close(&sentdval_args.json);

		if (0 != buffer_count)
		{
			if (1 == WITH_TIMESTAMPS)
			{
					zbx_timespec_t	timespec;

					zbx_timespec(&timespec);
					zbx_json_adduint64(&sentdval_args.json, ZBX_PROTO_TAG_CLOCK, timespec.sec);
					zbx_json_adduint64(&sentdval_args.json, ZBX_PROTO_TAG_NS, timespec.ns);
			}

			ret = zbx_thread_wait(zbx_thread_start(send_value, &thread_args));
		}

		if (in != stdin)
			fclose(in);
	}
	else
	{
		total_count++;

		do /* try block simulation */
		{
			if (NULL == ZABBIX_HOSTNAME)
			{
				zabbix_log(LOG_LEVEL_WARNING, "'Hostname' parameter required");
				break;
			}
			if (NULL == ZABBIX_KEY)
			{
				zabbix_log(LOG_LEVEL_WARNING, "Key required");
				break;
			}
			if (NULL == ZABBIX_KEY_VALUE)
			{
				zabbix_log(LOG_LEVEL_WARNING, "Key value required");
				break;
			}

			zbx_json_addobject(&sentdval_args.json, NULL);
			zbx_json_addstring(&sentdval_args.json, ZBX_PROTO_TAG_HOST, ZABBIX_HOSTNAME, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&sentdval_args.json, ZBX_PROTO_TAG_KEY, ZABBIX_KEY, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&sentdval_args.json, ZBX_PROTO_TAG_VALUE, ZABBIX_KEY_VALUE, ZBX_JSON_TYPE_STRING);
			zbx_json_close(&sentdval_args.json);

			succeed_count++;

			ret = zbx_thread_wait(zbx_thread_start(send_value, &thread_args));
		}
		while(0); /* try block simulation */
	}

	zbx_json_free(&sentdval_args.json);

	if (SUCCEED == ret)
		printf("sent: %d; skipped: %d; total: %d\n", succeed_count, (total_count - succeed_count), total_count);
	else
		printf("Sending failed. Use option -vv for more detailed output.\n");
exit:
	zabbix_close_log();

	return ret;
}
