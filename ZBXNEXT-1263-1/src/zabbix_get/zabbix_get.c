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

#include "threads.h"
#include "comms.h"
#include "cfg.h"
#include "log.h"
#include "zbxgetopt.h"
#include "../libs/zbxcrypto/tls.h"

const char	*progname = NULL;
const char	title_message[] = "zabbix_get";
const char	syslog_app_name[] = "zabbix_get";
const char	*usage_message[] = {
	"-s host-name-or-IP [-p port-number] [-I IP-address] -k item-key",
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	"-s host-name-or-IP [-p port-number] [-I IP-address] --tls-connect=cert (--tls-ca-file=ca_file | --tls-ca-path=ca_path) [--tls-crl-file=crl_file] --tls-cert-file=cert_file --tls-key-file=key_file -k item-key",
	"-s host-name-or-IP [-p port-number] [-I IP-address] --tls-connect=psk --tls-psk-identity=psk_identity --tls-psk-file=psk_file -k item-key",
#endif
	"-h",
	"-V",
	NULL	/* end of text */
};

unsigned char	program_type	= ZBX_PROGRAM_TYPE_GET;

const char	*help_message[] = {
	"Get data from Zabbix agent.",
	"",
	"General options:",
	"  -s --host host-name-or-IP          Specify host name or IP address of a host",
	"  -p --port port-number              Specify port number of agent running on the host. Default is " ZBX_DEFAULT_AGENT_PORT_STR,
	"  -I --source-address IP-address     Specify source IP address",
	"",
	"  -k --key item-key                  Specify key of the item to retrieve value for",
	"",
	"  -h --help                          Display this help message",
	"  -V --version                       Display version number",
	"",
	"TLS connection options:",
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	"  --tls-connect                      How to connect to agent. Values:",
	"                                         unencrypted - connect without encryption",
	"                                         psk         - connect using TLS and a pre-shared key",
	"                                         cert        - connect using TLS and a certificate",
	"",
	"  --tls-ca-file                      Full pathname of a file containing the top-level CA(s) certificates for",
	"                                     peer certificate verification",
	"",
	"  --tls-ca-path                      Full path of a directory containing the top-level CA(s) certificates for",
	"                                     peer certificate verification. Overrides '--tls-ca-file' parameter",
	"  --tls-crl-file                     Full pathname of a file containing revoked certificates",
	"",
	"  --tls-cert-file                    Full pathname of a file containing the certificate or certificate chain",
	"",
	"  --tls-key-file                     Full pathname of a file containing the private key",
	"",
	"  --tls-psk-identity                 Unique, case sensitive string used to identify the pre-shared key",
	"",
	"  --tls-psk-file                     Full pathname of a file containing the pre-shared key",
#else
	"  Not available. This 'zabbix_get' was compiled without TLS support",
#endif
	"",
	"Example(s):",
	"    zabbix_get -s 127.0.0.1 -p " ZBX_DEFAULT_AGENT_PORT_STR " -k \"system.cpu.load[all,avg1]\"",
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	"",
	"    zabbix_get -s 127.0.0.1 -p " ZBX_DEFAULT_AGENT_PORT_STR " -k \"system.cpu.load[all,avg1]\" --tls-connect=psk \\",
	"        --tls-psk-identity=\"PSK ID Zabbix agentd\" --tls-psk-file=/home/zabbix/zabbix_agentd.psk ",
	"",
	"    zabbix_get -s 127.0.0.1 -p " ZBX_DEFAULT_AGENT_PORT_STR " -k \"system.cpu.load[all,avg1]\" --tls-connect=cert \\",
	"        --tls-ca-file=/home/zabbix/zabbix_ca_file --tls-cert-file=/home/zabbix/zabbix_get.crt \\",
	"        --tls-key-file=/home/zabbix/zabbix_get.key",
#endif
	NULL	/* end of text */
};

/* TLS parameters */
unsigned int	configured_tls_connect_mode = ZBX_TCP_SEC_UNENCRYPTED;
unsigned int	configured_tls_accept_modes = ZBX_TCP_SEC_UNENCRYPTED;	/* not used in zabbix_get, just for linking */
									/* with tls.c */
char	*CONFIG_TLS_CONNECT		= NULL;
char	*CONFIG_TLS_ACCEPT		= NULL;	/* not used in zabbix_get, just for linking with tls.c */
char	*CONFIG_TLS_CA_FILE		= NULL;
char	*CONFIG_TLS_CA_PATH		= NULL;
char	*CONFIG_TLS_CRL_FILE		= NULL;
char	*CONFIG_TLS_CERT_FILE		= NULL;
char	*CONFIG_TLS_KEY_FILE		= NULL;
char	*CONFIG_TLS_PSK_FILE		= NULL;
char	*CONFIG_TLS_PSK_IDENTITY	= NULL;

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
	{"tls-connect",		1,	NULL,	'1'},
	{"tls-ca-file",		1,	NULL,	'2'},
	{"tls-ca-path",		1,	NULL,	'3'},
	{"tls-crl-file",	1,	NULL,	'4'},
	{"tls-cert-file",	1,	NULL,	'5'},
	{"tls-key-file",	1,	NULL,	'6'},
	{"tls-psk-identity",	1,	NULL,	'7'},
	{"tls-psk-file",	1,	NULL,	'8'},
	{NULL}
};

/* short options */
static char	shortopts[] = "s:p:k:I:hV";

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
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	get_signal_handler(int sig)
{
	if (SIGPIPE == sig)	/* this signal is raised when peer closes connection because of access restrictions */
		return;

	if (SIGALRM == sig)
		zbx_error("Timeout while executing operation");

	exit(EXIT_FAILURE);
}

#endif /* not WINDOWS */

/******************************************************************************
 *                                                                            *
 * Function: get_value                                                        *
 *                                                                            *
 * Purpose: connect to Zabbix agent, receive and print value                  *
 *                                                                            *
 * Parameters: host - server name or IP address                               *
 *             port - port number                                             *
 *             key  - item's key                                              *
 *                                                                            *
 ******************************************************************************/
static int	get_value(const char *source_ip, const char *host, unsigned short port, const char *key)
{
	zbx_sock_t	s;
	int		ret = SUCCEED;
	ssize_t		bytes_received = -1;
	char		request[1024];

	/* The connect mode can specify TLS with PSK but here we do not know PSK details. Therefore we put NULL in */
	/* the last 2 arguments. zbx_tls_connect() will find out PSK in this case. If the connect mode specifies TLS */
	/* with certificate then also NULLs are ok, as the sender does not verify server certificate issuer and */
	/* subject. */
	if (SUCCEED == (ret = zbx_tcp_connect(&s, source_ip, host, port, GET_SENDER_TIMEOUT,
			configured_tls_connect_mode, NULL, NULL)))
	{
		zbx_snprintf(request, sizeof(request), "%s\n", key);

		if (SUCCEED == (ret = zbx_tcp_send(&s, request)))
		{
			if (0 < (bytes_received = zbx_tcp_recv_ext(&s, ZBX_TCP_READ_UNTIL_CLOSE, 0)))
			{
				if (0 == strcmp(s.buffer, ZBX_NOTSUPPORTED) && sizeof(ZBX_NOTSUPPORTED) < s.read_bytes)
				{
					zbx_rtrim(s.buffer + sizeof(ZBX_NOTSUPPORTED), "\r\n");
					printf("%s: %s\n", s.buffer, s.buffer + sizeof(ZBX_NOTSUPPORTED));
				}
				else
				{
					zbx_rtrim(s.buffer, "\r\n");
					printf("%s\n", s.buffer);
				}
			}
			else
			{
				if (0 == bytes_received)
					zbx_error("Check access restrictions in Zabbix agent configuration");
				ret = FAIL;
			}
		}

		zbx_tcp_close(&s);

		if (SUCCEED != ret && 0 != bytes_received)
		{
			zbx_error("Get value error: %s", zbx_tcp_strerror());
			zbx_error("Check access restrictions in Zabbix agent configuration");
		}
	}
	else
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
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	main(int argc, char **argv)
{
	unsigned short	port = ZBX_DEFAULT_AGENT_PORT;
	int		ret = SUCCEED, opt_k = 0, opt_p = 0, opt_s = 0, opt_i = 0;
	char		*host = NULL, *key = NULL, *source_ip = NULL, ch;

	progname = get_program_name(argv[0]);

	/* parse the command-line */
	while ((char)EOF != (ch = (char)zbx_getopt_long(argc, argv, shortopts, longopts, NULL)))
	{
		switch (ch)
		{
			case 'k':
				opt_k++;

				if (NULL == key)
					key = zbx_strdup(NULL, zbx_optarg);
				break;
			case 'p':
				opt_p++;
				port = (unsigned short)atoi(zbx_optarg);
				break;
			case 's':
				opt_s++;

				if (NULL == host)
					host = zbx_strdup(NULL, zbx_optarg);
				break;
			case 'I':
				opt_i++;

				if (NULL == source_ip)
					source_ip = zbx_strdup(NULL, zbx_optarg);
				break;
			case 'h':
				help();
				exit(EXIT_SUCCESS);
				break;
			case 'V':
				version();
				exit(EXIT_SUCCESS);
				break;
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
			case '1':
				CONFIG_TLS_CONNECT = zbx_strdup(CONFIG_TLS_CONNECT, zbx_optarg);
				break;
			case '2':
				CONFIG_TLS_CA_FILE = zbx_strdup(CONFIG_TLS_CA_FILE, zbx_optarg);
				break;
			case '3':
				CONFIG_TLS_CA_PATH = zbx_strdup(CONFIG_TLS_CA_PATH, zbx_optarg);
				break;
			case '4':
				CONFIG_TLS_CRL_FILE = zbx_strdup(CONFIG_TLS_CRL_FILE, zbx_optarg);
				break;
			case '5':
				CONFIG_TLS_CERT_FILE = zbx_strdup(CONFIG_TLS_CERT_FILE, zbx_optarg);
				break;
			case '6':
				CONFIG_TLS_KEY_FILE = zbx_strdup(CONFIG_TLS_KEY_FILE, zbx_optarg);
				break;
			case '7':
				CONFIG_TLS_PSK_IDENTITY = zbx_strdup(CONFIG_TLS_PSK_IDENTITY, zbx_optarg);
				break;
			case '8':
				CONFIG_TLS_PSK_FILE = zbx_strdup(CONFIG_TLS_PSK_FILE, zbx_optarg);
				break;
#else
			case '1':
			case '2':
			case '3':
			case '4':
			case '5':
			case '6':
			case '7':
			case '8':
				zbx_error("TLS parameters cannot be used: 'zabbix_get' was compiled without TLS "
						"support.");
				exit(EXIT_FAILURE);
				break;
#endif
			default:
				usage();
				exit(EXIT_FAILURE);
				break;
		}
	}

	if (NULL == host || NULL == key)
	{
		usage();
		ret = FAIL;
	}

	/* every option may be specified only once */
	if (1 < opt_k || 1 < opt_p || 1 < opt_s || 1 < opt_i)
	{
		if (1 < opt_k)
			zbx_error("option \"-k\" specified multiple times");
		if (1 < opt_p)
			zbx_error("option \"-p\" specified multiple times");
		if (1 < opt_s)
			zbx_error("option \"-s\" specified multiple times");
		if (1 < opt_i)
			zbx_error("option \"-I\" specified multiple times");

		ret = FAIL;
	}

	/* Parameters which are not option values are invalid. The check relies on zbx_getopt_internal() which */
	/* always permutes command line arguments regardless of POSIXLY_CORRECT environment variable. */
	if (argc > zbx_optind)
	{
		int	i;

		for (i = zbx_optind; i < argc; i++)
			zbx_error("invalid parameter \"%s\"", argv[i]);

		ret = FAIL;
	}

	if (FAIL == ret)
	{
		printf("Try '%s --help' for more information.\n", progname);
		goto out;
	}

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_init_child();
#else
	if (NULL != CONFIG_TLS_CONNECT || NULL != CONFIG_TLS_CA_FILE || NULL != CONFIG_TLS_CA_PATH ||
			NULL != CONFIG_TLS_CRL_FILE || NULL != CONFIG_TLS_CERT_FILE || NULL != CONFIG_TLS_KEY_FILE ||
			NULL != CONFIG_TLS_PSK_IDENTITY || NULL != CONFIG_TLS_PSK_FILE)
	{
		zbx_error("TLS parameters cannot be used: 'zabbix_get' was compiled without TLS support.");
		ret = FAIL;
		goto out;
	}
#endif
#if !defined(_WINDOWS)
	signal(SIGINT,  get_signal_handler);
	signal(SIGTERM, get_signal_handler);
	signal(SIGQUIT, get_signal_handler);
	signal(SIGALRM, get_signal_handler);
	signal(SIGPIPE, get_signal_handler);
#endif
	ret = get_value(source_ip, host, port, key);
out:
	zbx_free(host);
	zbx_free(key);
	zbx_free(source_ip);
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_free();
#endif
	return ret;
}
