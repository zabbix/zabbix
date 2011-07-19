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

#include "threads.h"
#include "comms.h"
#include "cfg.h"
#include "log.h"
#include "zbxgetopt.h"

const char	*progname = NULL;
const char	title_message[] = "Zabbix Get";
const char	usage_message[] = "[-hV] -s <host name or IP> [-p <port>] [-I <IP address>] -k <key>";

const char	*help_message[] = {
	"Options:",
	"  -s --host <host name or IP>          Specify host name or IP address of a host",
	"  -p --port <port number>              Specify port number of agent running on the host. Default is 10050",
	"  -I --source-address <IP address>     Specify source IP address",
	"",
	"  -k --key <key of metric>             Specify key of item to retrieve value for",
	"",
	"  -h --help                            Give this help",
	"  -V --version                         Display version number",
	"",
	"Example: zabbix_get -s 127.0.0.1 -p 10050 -k \"system.cpu.load[all,avg1]\"",
	NULL	/* end of text */
};

/* COMMAND LINE OPTIONS */

/* long options */
struct zbx_option	longopts[] =
{
	{"host",		1,	NULL,	's'},
	{"port",		1,	NULL,	'p'},
	{"key",			1,	NULL,	'k'},
	{"source-address",	1,	NULL,	'I'},
	{"help",		0,	NULL,	'h'},
	{"version",		0,	NULL,	'V'},
	{NULL}
};

/* short options */
static char     shortopts[] = "s:p:k:I:hV";

/* end of COMMAND LINE OPTIONS */

#if !defined(_WINDOWS)

/******************************************************************************
 *                                                                            *
 * Function: get_signal_handler                                               *
 *                                                                            *
 * Purpose: process signals                                                   *
 *                                                                            *
 * Parameters: sig - signal ID                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	get_signal_handler(int sig)
{
	if (SIGALRM == sig)
		zbx_error("Timeout while executing operation");

	exit(FAIL);
}

#endif /* not WINDOWS */

/******************************************************************************
 *                                                                            *
 * Function: get_value                                                        *
 *                                                                            *
 * Purpose: connect to Zabbix agent and receive value for given key           *
 *                                                                            *
 * Parameters: host   - server name or IP address                             *
 *             port   - port number                                           *
 *             key    - item's key                                            *
 *                                                                            *
 * Return value: SUCCEED - ok, FAIL - otherwise                               *
 *             value  - retrieved value                                       *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	get_value(const char *source_ip, const char *host, unsigned short port, const char *key, char **value)
{
	zbx_sock_t	s;
	int		ret;
	char		*buf, request[1024];

	assert(value);

	*value = NULL;

	if (SUCCEED == (ret = zbx_tcp_connect(&s, source_ip, host, port, GET_SENDER_TIMEOUT)))
	{
		zbx_snprintf(request, sizeof(request), "%s\n", key);

		if (SUCCEED == (ret = zbx_tcp_send(&s, request)))
		{
			if (SUCCEED == (ret = zbx_tcp_recv_ext(&s, &buf, ZBX_TCP_READ_UNTIL_CLOSE, 0)))
			{
				zbx_rtrim(buf, "\r\n");
				*value = strdup(buf);
			}
		}

		zbx_tcp_close(&s);
	}

	if (FAIL == ret)
		zbx_error("Get value error: %s", zbx_tcp_strerror());

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: main                                                             *
 *                                                                            *
 * Purpose: main function                                                     *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int main(int argc, char **argv)
{
	unsigned short	port	= 10050;
	int	ret	= SUCCEED;
	char	*value	= NULL;
	char	*host	= NULL;
	char	*key	= NULL;
	char	*source_ip = NULL;
	char	ch;

	progname = get_program_name(argv[0]);

	/* Parse the command-line. */
	while ((ch = (char)zbx_getopt_long(argc, argv, shortopts, longopts, NULL)) != (char)EOF)
		switch (ch) {
			case 'k':
				key = strdup(zbx_optarg);
				break;
			case 'p':
				port = (unsigned short)atoi(zbx_optarg);
				break;
			case 's':
				host = strdup(zbx_optarg);
				break;
			case 'I':
				source_ip = strdup(zbx_optarg);
				break;
			case 'h':
				help();
				exit(-1);
				break;
			case 'V':
				version();
				exit(-1);
				break;
			default:
				usage();
				exit(-1);
				break;
		}

	if( (host==NULL) || (key==NULL))
	{
		usage();
		ret = FAIL;
	}

	if(ret == SUCCEED)
	{

#if !defined(_WINDOWS)
		signal(SIGINT,  get_signal_handler);
		signal(SIGTERM, get_signal_handler);
		signal(SIGQUIT, get_signal_handler);
		signal(SIGALRM, get_signal_handler);
#endif /* not WINDOWS */

		ret = get_value(source_ip, host, port, key, &value);

		if(ret == SUCCEED)
		{
			printf("%s\n",value);
		}

		zbx_free(value);
	}

	zbx_free(host);
	zbx_free(key);

	return ret;
}
