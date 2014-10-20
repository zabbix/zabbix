/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
const char	title_message[] = "zabbix_sender";
const char	syslog_app_name[] = "zabbix_sender";

const char	*usage_message[] = {
	"[-v] -z server [-p port] [-I IP-address] -s host -k key -o value",
	"[-v] -z server [-p port] [-I IP-address] [-T] [-r] -i input-file",
	"[-v] -c config-file -s host -k key -o value",
	"[-v] -c config-file [-T] [-r] -i input-file",
	"-h",
	"-V",
	NULL	/* end of text */
};

const char	*help_message[] = {
	"Utility for sending monitoring data to Zabbix server or proxy.",
	"",
	"Options:",
	"  -c --config config-file              Absolute path to Zabbix agentd configuration file",
	"",
	"  -z --zabbix-server server            Hostname or IP address of Zabbix server or proxy to send data to",
	"  -p --port port                       Specify port number of trapper process of Zabbix server or proxy.",
	"                                       Default is " ZBX_DEFAULT_SERVER_PORT_STR,
	"  -I --source-address IP-address       Specify source IP address",
	"",
	"  -s --host host                       Specify host name the item belongs to (as registered in Zabbix front-end).",
	"                                       Host IP address and DNS name will not work",
	"  -k --key key                         Specify item key",
	"  -o --value value                     Specify item value",
	"",
	"  -i --input-file input-file           Load values from input file. Specify - for standard input.",
	"                                       Each line of file contains whitespace delimited: <host> <key> <value>.",
	"                                       Specify - in <host> to use hostname from configuration file or --host argument",
	"  -T --with-timestamps                 Each line of file contains whitespace delimited: <host> <key> <timestamp> <value>.",
	"                                       This can be used with --input-file option.",
	"                                       Timestamp should be specified in Unix timestamp format",
	"  -r --real-time                       Send metrics one by one as soon as they are received.",
	"                                       This can be used when reading from standard input",
	"",
	"  -v --verbose                         Verbose mode, -vv for more details",
	"",
	"  -h --help                            Display this help message",
	"  -V --version                         Display version number",
	"",
	"Example: zabbix_sender -z 127.0.0.1 -s \"Linux DB3\" -k db.connections -o 43",
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
	_exit(EXIT_FAILURE);
}
#endif

typedef struct
{
	char		*source_ip;
	char		*server;
	unsigned short	port;
	struct zbx_json	json;
}
ZBX_THREAD_SENDVAL_ARGS;

#define SUCCEED_PARTIAL	2

/******************************************************************************
 *                                                                            *
 * Function: update_exit_status                                               *
 *                                                                            *
 * Purpose: manage exit status updates after batch sends                      *
 *                                                                            *
 * Comments: SUCCEED_PARTIAL status should be sticky in the sense that        *
 *           SUCCEED statuses that come after should not overwrite it         *
 *                                                                            *
 ******************************************************************************/
static int	update_exit_status(int old_status, int new_status)
{
	if (FAIL == old_status || FAIL == new_status || (unsigned char)FAIL == new_status)
		return FAIL;

	if (SUCCEED == old_status)
		return new_status;

	if (SUCCEED_PARTIAL == old_status)
		return old_status;

	THIS_SHOULD_NEVER_HAPPEN;
	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: get_string                                                       *
 *                                                                            *
 * Purpose: get current string from the quoted or unquoted string list,       *
 *          delimited by blanks                                               *
 *                                                                            *
 * Parameters:                                                                *
 *      p       - [IN] parameter list, delimited by blanks (' ' or '\t')      *
 *      buf     - [OUT] output buffer                                         *
 *      bufsize - [IN] output buffer size                                     *
 *                                                                            *
 * Return value: pointer to the next string                                   *
 *                                                                            *
 ******************************************************************************/
static const char	*get_string(const char *p, char *buf, size_t bufsize)
{
/* 0 - init, 1 - inside quoted param, 2 - inside unquoted param */
	int	state;
	size_t	buf_i = 0;

	bufsize--;	/* '\0' */

	for (state = 0; '\0' != *p; p++)
	{
		switch (state)
		{
			/* init state */
			case 0:
				if (' ' == *p || '\t' == *p)
				{
					/* skipping the leading spaces */;
				}
				else if ('"' == *p)
				{
					state = 1;
				}
				else
				{
					state = 2;
					p--;
				}
				break;
			/* quoted */
			case 1:
				if ('"' == *p)
				{
					if (' ' != p[1] && '\t' != p[1] && '\0' != p[1])
						return NULL;	/* incorrect syntax */

					while (' ' == p[1] || '\t' == p[1])
						p++;

					buf[buf_i] = '\0';
					return ++p;
				}
				else if ('\\' == *p && ('"' == p[1] || '\\' == p[1]))
				{
					p++;
					if (buf_i < bufsize)
						buf[buf_i++] = *p;
				}
				else if ('\\' == *p && 'n' == p[1])
				{
					p++;
					if (buf_i < bufsize)
						buf[buf_i++] = '\n';
				}
				else if (buf_i < bufsize)
				{
					buf[buf_i++] = *p;
				}
				break;
			/* unquoted */
			case 2:
				if (' ' == *p || '\t' == *p)
				{
					while (' ' == *p || '\t' == *p)
						p++;

					buf[buf_i] = '\0';
					return p;
				}
				else if (buf_i < bufsize)
				{
					buf[buf_i++] = *p;
				}
				break;
		}
	}

	/* missing terminating '"' character */
	if (1 == state)
		return NULL;

	buf[buf_i] = '\0';

	return p;
}

/******************************************************************************
 *                                                                            *
 * Function: check_response                                                   *
 *                                                                            *
 * Purpose: Check whether JSON response is SUCCEED                            *
 *                                                                            *
 * Parameters: JSON response from Zabbix trapper                              *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                SUCCEED_PARTIAL - the sending operation was completed       *
 *                successfully, but processing of at least one value failed   *
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
		int	failed;

		printf("info from server: \"%s\"\n", info);
		fflush(stdout);

		if (1 == sscanf(info, "processed: %*d; failed: %d", &failed) && 0 < failed)
			ret = SUCCEED_PARTIAL;
	}

	return ret;
}

static	ZBX_THREAD_ENTRY(send_value, args)
{
	ZBX_THREAD_SENDVAL_ARGS	*sentdval_args;
	zbx_sock_t		sock;
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
			if (SUCCEED == (tcp_ret = zbx_tcp_recv(&sock)))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "answer [%s]", sock.buffer);
				if (NULL == sock.buffer || FAIL == (ret = check_response(sock.buffer)))
					zabbix_log(LOG_LEVEL_WARNING, "incorrect answer from server [%s]", sock.buffer);
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
		{"ServerActive",		&cfg_active_hosts,			TYPE_STRING_LIST,
			PARM_OPT,	0,			0},
		{"Hostname",			&cfg_hostname,				TYPE_STRING,
			PARM_OPT,	0,			0},
		{NULL}
	};

	if (NULL == config_file)
		return;

	/* do not complain about unknown parameters in agent configuration file */
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
				zbx_error("error parsing \"ServerActive\" option: address \"%s\" is invalid",
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

static void	parse_commandline(int argc, char **argv)
{
	char		ch = '\0';
	int		opt_c = 0, opt_cap_i = 0, opt_z = 0, opt_p = 0, opt_s = 0, opt_k = 0, opt_o = 0, opt_i = 0,
			opt_cap_t = 0, opt_r = 0, opt_v = 0;
	unsigned int	opt_mask = 0;

	/* parse the command-line */
	while ((char)EOF != (ch = (char)zbx_getopt_long(argc, argv, shortopts, longopts, NULL)))
	{
		switch (ch)
		{
			case 'c':
				opt_c++;
				if (NULL == CONFIG_FILE)
					CONFIG_FILE = zbx_strdup(CONFIG_FILE, zbx_optarg);
				break;
			case 'h':
				help();
				exit(EXIT_SUCCESS);
				break;
			case 'V':
				version();
				exit(EXIT_SUCCESS);
				break;
			case 'I':
				opt_cap_i++;
				if (NULL == CONFIG_SOURCE_IP)
					CONFIG_SOURCE_IP = zbx_strdup(CONFIG_SOURCE_IP, zbx_optarg);
				break;
			case 'z':
				opt_z++;
				if (NULL == ZABBIX_SERVER)
					ZABBIX_SERVER = zbx_strdup(ZABBIX_SERVER, zbx_optarg);
				break;
			case 'p':
				opt_p++;
				ZABBIX_SERVER_PORT = (unsigned short)atoi(zbx_optarg);
				break;
			case 's':
				opt_s++;
				if (NULL == ZABBIX_HOSTNAME)
					ZABBIX_HOSTNAME = zbx_strdup(ZABBIX_HOSTNAME, zbx_optarg);
				break;
			case 'k':
				opt_k++;
				if (NULL == ZABBIX_KEY)
					ZABBIX_KEY = zbx_strdup(ZABBIX_KEY, zbx_optarg);
				break;
			case 'o':
				opt_o++;
				if (NULL == ZABBIX_KEY_VALUE)
					ZABBIX_KEY_VALUE = zbx_strdup(ZABBIX_KEY_VALUE, zbx_optarg);
				break;
			case 'i':
				opt_i++;
				if (NULL == INPUT_FILE)
					INPUT_FILE = zbx_strdup(INPUT_FILE, zbx_optarg);
				break;
			case 'T':
				opt_cap_t++;
				WITH_TIMESTAMPS = 1;
				break;
			case 'r':
				opt_r++;
				REAL_TIME = 1;
				break;
			case 'v':
				opt_v++;
				if (LOG_LEVEL_WARNING > CONFIG_LOG_LEVEL)
					CONFIG_LOG_LEVEL = LOG_LEVEL_WARNING;
				else if (LOG_LEVEL_DEBUG > CONFIG_LOG_LEVEL)
					CONFIG_LOG_LEVEL = LOG_LEVEL_DEBUG;
				break;
			default:
				usage();
				exit(EXIT_FAILURE);
				break;
		}
	}

	/* every option may be specified only once */
	if (1 < opt_c || 1 < opt_cap_i || 1 < opt_z || 1 < opt_p || 1 < opt_s || 1 < opt_k || 1 < opt_o || 1 < opt_i ||
			1 < opt_cap_t || 1 < opt_r || 2 < opt_v)
	{
		if (1 < opt_c)
			zbx_error("option \"-c\" or \"--config\" specified multiple times");
		if (1 < opt_cap_i)
			zbx_error("option \"-I\" or \"--source-address\" specified multiple times");
		if (1 < opt_z)
			zbx_error("option \"-z\" or \"--zabbix-server\" specified multiple times");
		if (1 < opt_p)
			zbx_error("option \"-p\" or \"--port\" specified multiple times");
		if (1 < opt_s)
			zbx_error("option \"-s\" or \"--host\" specified multiple times");
		if (1 < opt_k)
			zbx_error("option \"-k\" or \"--key\" specified multiple times");
		if (1 < opt_o)
			zbx_error("option \"-o\" or \"--value\" specified multiple times");
		if (1 < opt_i)
			zbx_error("option \"-i\" or \"--input-file\" specified multiple times");
		if (1 < opt_cap_t)
			zbx_error("option \"-T\" or \"--with-timestamps\" specified multiple times");
		if (1 < opt_r)
			zbx_error("option \"-r\" or \"--real-time\" specified multiple times");
		/* '-v' or '-vv' can be specified */
		if (2 < opt_v)
			zbx_error("option \"-v\" or \"--verbose\" specified more than 2 times");

		exit(EXIT_FAILURE);
	}

	/* check for mutually exclusive options    */

	/* Allowed option combinations.            */
	/* Option 'v' is always optional.          */
	/*   c  z  s  k  o  i  T  r  p  I opt_mask */
	/* ------------------------------ -------- */
	/*   -  z  -  -  -  i  -  -  -  -  0x110   */
	/*   -  z  -  -  -  i  -  -  -  I  0x111   */
	/*   -  z  -  -  -  i  -  -  p  -  0x112   */
	/*   -  z  -  -  -  i  -  -  p  I  0x113   */
	/*   -  z  -  -  -  i  -  r  -  -  0x114   */
	/*   -  z  -  -  -  i  -  r  -  I  0x115   */
	/*   -  z  -  -  -  i  -  r  p  -  0x116   */
	/*   -  z  -  -  -  i  -  r  p  I  0x117   */
	/*   -  z  -  -  -  i  T  -  -  -  0x118   */
	/*   -  z  -  -  -  i  T  -  -  I  0x119   */
	/*   -  z  -  -  -  i  T  -  p  -  0x11a   */
	/*   -  z  -  -  -  i  T  -  p  I  0x11b   */
	/*   -  z  -  -  -  i  T  r  -  -  0x11c   */
	/*   -  z  -  -  -  i  T  r  -  I  0x11d   */
	/*   -  z  -  -  -  i  T  r  p  -  0x11e   */
	/*   -  z  -  -  -  i  T  r  p  I  0x11f   */
	/*   -  z  s  k  o  -  -  -  -  -  0x1e0   */
	/*   -  z  s  k  o  -  -  -  -  I  0x1e1   */
	/*   -  z  s  k  o  -  -  -  p  -  0x1e2   */
	/*   -  z  s  k  o  -  -  -  p  I  0x1e3   */
	/*   c  -  -  -  -  i  -  -  -  -  0x210   */
	/*   c  -  -  -  -  i  -  r  -  -  0x214   */
	/*   c  -  -  -  -  i  T  -  -  -  0x218   */
	/*   c  -  -  -  -  i  T  r  -  -  0x21c   */
	/*   c  -  s  k  o  -  -  -  -  -  0x2e0   */

	if (0 == opt_c + opt_z)
	{
		zbx_error("either '-c' or '-z' option must be specified");
		usage();
		printf("Try '%s --help' for more information.\n", progname);
		exit(EXIT_FAILURE);
	}

	if (1 < opt_c + opt_z)
	{
		zbx_error("either '-c' or '-z' option must be specified but not both");
		usage();
		exit(EXIT_FAILURE);
	}

	if (0 < opt_c)
		opt_mask |= 0x200;
	if (0 < opt_z)
		opt_mask |= 0x100;
	if (0 < opt_s)
		opt_mask |= 0x80;
	if (0 < opt_k)
		opt_mask |= 0x40;
	if (0 < opt_o)
		opt_mask |= 0x20;
	if (0 < opt_i)
		opt_mask |= 0x10;
	if (0 < opt_cap_t)
		opt_mask |= 0x08;
	if (0 < opt_r)
		opt_mask |= 0x04;
	if (0 < opt_p)
		opt_mask |= 0x02;
	if (0 < opt_cap_i)
		opt_mask |= 0x01;

	if (1 == opt_i && ((1 == opt_z && ! (0x110 <= opt_mask && opt_mask <= 0x11f)) ||
			(1 == opt_c && 0x210 != opt_mask && 0x214 != opt_mask && 0x218 != opt_mask &&
			0x21c != opt_mask)))
	{
		zbx_error("option '-i' can be used only with '-T' and '-r' options");
		usage();
		exit(EXIT_FAILURE);
	}

	if ((1 == opt_z && 0 == opt_i && ! (0x1e0 <= opt_mask && opt_mask <= 0x1e3)) ||
			(1 == opt_c && 0 == opt_i && 0x2e0 != opt_mask))
	{
		zbx_error("too few or mutually exclusive options used");
		usage();
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
}

/* sending a huge amount of values in a single connection is likely to */
/* take long and hit timeout, so we limit values to 250 per connection */
#define VALUES_MAX	250

int	main(int argc, char **argv)
{
	FILE			*in;
	char			in_line[MAX_BUFFER_LEN], hostname[MAX_STRING_LEN], key[MAX_STRING_LEN],
				key_value[MAX_BUFFER_LEN], clock[32];
	int			total_count = 0, succeed_count = 0, buffer_count = 0, read_more = 0, ret = FAIL,
				timestamp;
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
		zabbix_log(LOG_LEVEL_CRIT, "'ServerActive' parameter required");
		goto exit;
	}
	if (0 == ZABBIX_SERVER_PORT)
		ZABBIX_SERVER_PORT = ZBX_DEFAULT_SERVER_PORT;

	if (MIN_ZABBIX_PORT > ZABBIX_SERVER_PORT)
	{
		zabbix_log(LOG_LEVEL_CRIT, "Incorrect port number [%d]. Allowed [%d:%d]",
				(int)ZABBIX_SERVER_PORT, (int)MIN_ZABBIX_PORT, (int)MAX_ZABBIX_PORT);
		goto exit;
	}

	thread_args.server_num = 0;
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
			zabbix_log(LOG_LEVEL_CRIT, "cannot open [%s]: %s", INPUT_FILE, zbx_strerror(errno));
			goto free;
		}

		ret = SUCCEED;

		while ((SUCCEED == ret || SUCCEED_PARTIAL == ret) && NULL != fgets(in_line, sizeof(in_line), in))
		{
			/* line format: <hostname> <key> [<timestamp>] <value> */

			total_count++; /* also used as inputline */

			zbx_rtrim(in_line, "\r\n");

			p = in_line;

			if ('\0' == *p || NULL == (p = get_string(p, hostname, sizeof(hostname))) || '\0' == *hostname)
			{
				zabbix_log(LOG_LEVEL_CRIT, "[line %d] 'Hostname' required", total_count);
				ret = FAIL;
				break;
			}

			if (0 == strcmp(hostname, "-"))
			{
				if (NULL == ZABBIX_HOSTNAME)
				{
					zabbix_log(LOG_LEVEL_CRIT, "[line %d] '-' encountered as 'Hostname', "
							"but no default hostname was specified", total_count);
					ret = FAIL;
					break;
				}
				else
					zbx_strlcpy(hostname, ZABBIX_HOSTNAME, sizeof(hostname));
			}

			if ('\0' == *p || NULL == (p = get_string(p, key, sizeof(key))) || '\0' == *key)
			{
				zabbix_log(LOG_LEVEL_CRIT, "[line %d] 'Key' required", total_count);
				ret = FAIL;
				break;
			}

			if (1 == WITH_TIMESTAMPS)
			{
				if ('\0' == *p || NULL == (p = get_string(p, clock, sizeof(clock))) || '\0' == *clock)
				{
					zabbix_log(LOG_LEVEL_CRIT, "[line %d] 'Timestamp' required", total_count);
					ret = FAIL;
					break;
				}

				if (FAIL == is_uint31(clock, &timestamp))
				{
					zabbix_log(LOG_LEVEL_WARNING, "[line %d] invalid 'Timestamp' value detected",
							total_count);
					ret = FAIL;
					break;
				}
			}

			if ('\0' != *p && '"' != *p)
			{
				zbx_strlcpy(key_value, p, sizeof(key_value));
			}
			else if ('\0' == *p || NULL == (p = get_string(p, key_value, sizeof(key_value))))
			{
				zabbix_log(LOG_LEVEL_CRIT, "[line %d] 'Key value' required", total_count);
				ret = FAIL;
				break;
			}
			else if ('\0' != *p)
			{
				zabbix_log(LOG_LEVEL_CRIT, "[line %d] too many parameters", total_count);
				ret = FAIL;
				break;
			}

			zbx_json_addobject(&sentdval_args.json, NULL);
			zbx_json_addstring(&sentdval_args.json, ZBX_PROTO_TAG_HOST, hostname, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&sentdval_args.json, ZBX_PROTO_TAG_KEY, key, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&sentdval_args.json, ZBX_PROTO_TAG_VALUE, key_value, ZBX_JSON_TYPE_STRING);
			if (1 == WITH_TIMESTAMPS)
				zbx_json_adduint64(&sentdval_args.json, ZBX_PROTO_TAG_CLOCK, timestamp);
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
					zbx_json_adduint64(&sentdval_args.json, ZBX_PROTO_TAG_CLOCK, (int)time(NULL));

				last_send = zbx_time();

				ret = update_exit_status(ret, zbx_thread_wait(zbx_thread_start(send_value, &thread_args)));

				buffer_count = 0;
				zbx_json_clean(&sentdval_args.json);
				zbx_json_addstring(&sentdval_args.json, ZBX_PROTO_TAG_REQUEST, ZBX_PROTO_VALUE_SENDER_DATA,
						ZBX_JSON_TYPE_STRING);
				zbx_json_addarray(&sentdval_args.json, ZBX_PROTO_TAG_DATA);
			}
		}

		if (FAIL != ret && 0 != buffer_count)
		{
			zbx_json_close(&sentdval_args.json);

			if (1 == WITH_TIMESTAMPS)
				zbx_json_adduint64(&sentdval_args.json, ZBX_PROTO_TAG_CLOCK, (int)time(NULL));

			ret = update_exit_status(ret, zbx_thread_wait(zbx_thread_start(send_value, &thread_args)));
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

			ret = SUCCEED;

			zbx_json_addobject(&sentdval_args.json, NULL);
			zbx_json_addstring(&sentdval_args.json, ZBX_PROTO_TAG_HOST, ZABBIX_HOSTNAME, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&sentdval_args.json, ZBX_PROTO_TAG_KEY, ZABBIX_KEY, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&sentdval_args.json, ZBX_PROTO_TAG_VALUE, ZABBIX_KEY_VALUE, ZBX_JSON_TYPE_STRING);
			zbx_json_close(&sentdval_args.json);

			succeed_count++;

			ret = update_exit_status(ret, zbx_thread_wait(zbx_thread_start(send_value, &thread_args)));
		}
		while (0); /* try block simulation */
	}
free:
	zbx_json_free(&sentdval_args.json);
exit:
	if (FAIL != ret)
	{
		printf("sent: %d; skipped: %d; total: %d\n", succeed_count, total_count - succeed_count, total_count);
	}
	else
	{
		printf("Sending failed.%s\n", CONFIG_LOG_LEVEL != LOG_LEVEL_DEBUG ?
				" Use option -vv for more detailed output." : "");
	}

	zabbix_close_log();

	if (FAIL == ret)
		ret = EXIT_FAILURE;

	return ret;
}
