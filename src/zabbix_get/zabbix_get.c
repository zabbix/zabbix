/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

#ifndef _WINDOWS
#	include "zbxnix.h"
#endif

const char	*progname = NULL;
const char	title_message[] = "zabbix_get";
const char	syslog_app_name[] = "zabbix_get";
const char	*usage_message[] = {
	"-s host-name-or-IP", "[-p port-number]", "[-I IP-address]", "-k item-key", NULL,
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	"-s host-name-or-IP", "[-p port-number]", "[-I IP-address]", "--tls-connect cert", "--tls-ca-file CA-file",
	"[--tls-crl-file CRL-file]", "[--tls-agent-cert-issuer cert-issuer]", "[--tls-agent-cert-subject cert-subject]",
	"--tls-cert-file cert-file", "--tls-key-file key-file", "-k item-key", NULL,
	"-s host-name-or-IP", "[-p port-number]", "[-I IP-address]", "--tls-connect psk",
	"--tls-psk-identity PSK-identity", "--tls-psk-file PSK-file", "-k item-key", NULL,
#endif
	"-h", NULL,
	"-V", NULL,
	NULL	/* end of text */
};

unsigned char	program_type	= ZBX_PROGRAM_TYPE_GET;

const char	*help_message[] = {
	"Get data from Zabbix agent.",
	"",
	"General options:",
	"  -s --host host-name-or-IP  Specify host name or IP address of a host",
	"  -p --port port-number      Specify port number of agent running on the host",
	"                             (default: " ZBX_DEFAULT_AGENT_PORT_STR ")",
	"  -I --source-address IP-address   Specify source IP address",
	"",
	"  -k --key item-key          Specify key of the item to retrieve value for",
	"",
	"  -h --help                  Display this help message",
	"  -V --version               Display version number",
	"",
	"TLS connection options:",
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	"  --tls-connect value        How to connect to agent. Values:",
	"                               unencrypted - connect without encryption",
	"                                             (default)",
	"                               psk         - connect using TLS and a pre-shared",
	"                                             key",
	"                               cert        - connect using TLS and a",
	"                                             certificate",
	"",
	"  --tls-ca-file CA-file      Full pathname of a file containing the top-level",
	"                             CA(s) certificates for peer certificate",
	"                             verification",
	"",
	"  --tls-crl-file CRL-file    Full pathname of a file containing revoked",
	"                             certificates",
	"",
	"  --tls-agent-cert-issuer cert-issuer   Allowed agent certificate issuer",
	"",
	"  --tls-agent-cert-subject cert-subject   Allowed agent certificate subject",
	"",
	"  --tls-cert-file cert-file  Full pathname of a file containing the certificate",
	"                             or certificate chain",
	"",
	"  --tls-key-file key-file    Full pathname of a file containing the private key",
	"",
	"  --tls-psk-identity PSK-identity   Unique, case sensitive string used to",
	"                             identify the pre-shared key",
	"",
	"  --tls-psk-file PSK-file    Full pathname of a file containing the pre-shared",
	"                             key",
#else
	"  Not available. This 'zabbix_get' was compiled without TLS support",
#endif
	"",
	"Example(s):",
	"  zabbix_get -s 127.0.0.1 -p " ZBX_DEFAULT_AGENT_PORT_STR " -k \"system.cpu.load[all,avg1]\"",
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	"",
	"  zabbix_get -s 127.0.0.1 -p " ZBX_DEFAULT_AGENT_PORT_STR " -k \"system.cpu.load[all,avg1]\" \\",
	"    --tls-connect cert --tls-ca-file /home/zabbix/zabbix_ca_file \\",
	"    --tls-agent-cert-issuer \\",
	"    \"CN=Signing CA,OU=IT operations,O=Example Corp,DC=example,DC=com\" \\",
	"    --tls-agent-cert-subject \\",
	"    \"CN=server1,OU=IT operations,O=Example Corp,DC=example,DC=com\" \\",
	"    --tls-cert-file /home/zabbix/zabbix_get.crt \\",
	"    --tls-key-file /home/zabbix/zabbix_get.key",
	"",
	"  zabbix_get -s 127.0.0.1 -p " ZBX_DEFAULT_AGENT_PORT_STR " -k \"system.cpu.load[all,avg1]\" \\",
	"    --tls-connect psk --tls-psk-identity \"PSK ID Zabbix agentd\" \\",
	"    --tls-psk-file /home/zabbix/zabbix_agentd.psk",
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
char	*CONFIG_TLS_CRL_FILE		= NULL;
char	*CONFIG_TLS_SERVER_CERT_ISSUER	= NULL;
char	*CONFIG_TLS_SERVER_CERT_SUBJECT	= NULL;
char	*CONFIG_TLS_CERT_FILE		= NULL;
char	*CONFIG_TLS_KEY_FILE		= NULL;
char	*CONFIG_TLS_PSK_IDENTITY	= NULL;
char	*CONFIG_TLS_PSK_FILE		= NULL;

int	CONFIG_PASSIVE_FORKS		= 0;	/* not used in zabbix_get, just for linking with tls.c */
int	CONFIG_ACTIVE_FORKS		= 0;	/* not used in zabbix_get, just for linking with tls.c */

/* COMMAND LINE OPTIONS */

/* long options */
struct zbx_option	longopts[] =
{
	{"host",			1,	NULL,	's'},
	{"port",			1,	NULL,	'p'},
	{"key",				1,	NULL,	'k'},
	{"source-address",		1,	NULL,	'I'},
	{"help",			0,	NULL,	'h'},
	{"version",			0,	NULL,	'V'},
	{"tls-connect",			1,	NULL,	'1'},
	{"tls-ca-file",			1,	NULL,	'2'},
	{"tls-crl-file",		1,	NULL,	'3'},
	{"tls-agent-cert-issuer",	1,	NULL,	'4'},
	{"tls-agent-cert-subject",	1,	NULL,	'5'},
	{"tls-cert-file",		1,	NULL,	'6'},
	{"tls-key-file",		1,	NULL,	'7'},
	{"tls-psk-identity",		1,	NULL,	'8'},
	{"tls-psk-file",		1,	NULL,	'9'},
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

#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	if (ZBX_TCP_SEC_UNENCRYPTED != configured_tls_connect_mode)
		zbx_tls_free_on_signal();
#endif
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
	zbx_socket_t	s;
	int		ret;
	ssize_t		bytes_received = -1;
	char		*tls_arg1, *tls_arg2;

	switch (configured_tls_connect_mode)
	{
		case ZBX_TCP_SEC_UNENCRYPTED:
			tls_arg1 = NULL;
			tls_arg2 = NULL;
			break;
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		case ZBX_TCP_SEC_TLS_CERT:
			tls_arg1 = CONFIG_TLS_SERVER_CERT_ISSUER;
			tls_arg2 = CONFIG_TLS_SERVER_CERT_SUBJECT;
			break;
		case ZBX_TCP_SEC_TLS_PSK:
			tls_arg1 = CONFIG_TLS_PSK_IDENTITY;
			tls_arg2 = NULL;	/* zbx_tls_connect() will find PSK */
			break;
#endif
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			return FAIL;
	}

	if (SUCCEED == (ret = zbx_tcp_connect(&s, source_ip, host, port, GET_SENDER_TIMEOUT,
			configured_tls_connect_mode, tls_arg1, tls_arg2)))
	{
		if (SUCCEED == (ret = zbx_tcp_send(&s, key)))
		{
			if (0 < (bytes_received = zbx_tcp_recv_ext(&s, 0, 0)))
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
			zbx_error("Get value error: %s", zbx_socket_strerror());
			zbx_error("Check access restrictions in Zabbix agent configuration");
		}
	}
	else
		zbx_error("Get value error: %s", zbx_socket_strerror());

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
	int		i, ret = SUCCEED;
	char		*host = NULL, *key = NULL, *source_ip = NULL, ch;
	unsigned short	opt_count[256] = {0}, port = ZBX_DEFAULT_AGENT_PORT;

#if !defined(_WINDOWS) && (defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL))
	if (SUCCEED != zbx_coredump_disable())
	{
		zbx_error("cannot disable core dump, exiting...");
		exit(EXIT_FAILURE);
	}
#endif
	progname = get_program_name(argv[0]);

	/* parse the command-line */
	while ((char)EOF != (ch = (char)zbx_getopt_long(argc, argv, shortopts, longopts, NULL)))
	{
		opt_count[(unsigned char)ch]++;

		switch (ch)
		{
			case 'k':
				if (NULL == key)
					key = zbx_strdup(NULL, zbx_optarg);
				break;
			case 'p':
				port = (unsigned short)atoi(zbx_optarg);
				break;
			case 's':
				if (NULL == host)
					host = zbx_strdup(NULL, zbx_optarg);
				break;
			case 'I':
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
				CONFIG_TLS_CRL_FILE = zbx_strdup(CONFIG_TLS_CRL_FILE, zbx_optarg);
				break;
			case '4':
				CONFIG_TLS_SERVER_CERT_ISSUER = zbx_strdup(CONFIG_TLS_SERVER_CERT_ISSUER, zbx_optarg);
				break;
			case '5':
				CONFIG_TLS_SERVER_CERT_SUBJECT = zbx_strdup(CONFIG_TLS_SERVER_CERT_SUBJECT, zbx_optarg);
				break;
			case '6':
				CONFIG_TLS_CERT_FILE = zbx_strdup(CONFIG_TLS_CERT_FILE, zbx_optarg);
				break;
			case '7':
				CONFIG_TLS_KEY_FILE = zbx_strdup(CONFIG_TLS_KEY_FILE, zbx_optarg);
				break;
			case '8':
				CONFIG_TLS_PSK_IDENTITY = zbx_strdup(CONFIG_TLS_PSK_IDENTITY, zbx_optarg);
				break;
			case '9':
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
			case '9':
				zbx_error("TLS parameters cannot be used: 'zabbix_get' was compiled without TLS"
						" support");
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

	for (i = 0; NULL != longopts[i].name; i++)
	{
		ch = longopts[i].val;

		if (1 < opt_count[(unsigned char)ch])
		{
			if (NULL == strchr(shortopts, ch))
				zbx_error("option \"--%s\" specified multiple times", longopts[i].name);
			else
				zbx_error("option \"-%c\" or \"--%s\" specified multiple times", ch, longopts[i].name);

			ret = FAIL;
		}
	}

	if (FAIL == ret)
		goto out;

	/* Parameters which are not option values are invalid. The check relies on zbx_getopt_internal() which */
	/* always permutes command line arguments regardless of POSIXLY_CORRECT environment variable. */
	if (argc > zbx_optind)
	{
		for (i = zbx_optind; i < argc; i++)
			zbx_error("invalid parameter \"%s\"", argv[i]);

		ret = FAIL;
	}

	if (FAIL == ret)
	{
		printf("Try '%s --help' for more information.\n", progname);
		goto out;
	}

	if (NULL != CONFIG_TLS_CONNECT || NULL != CONFIG_TLS_CA_FILE || NULL != CONFIG_TLS_CRL_FILE ||
			NULL != CONFIG_TLS_SERVER_CERT_ISSUER || NULL != CONFIG_TLS_SERVER_CERT_SUBJECT ||
			NULL != CONFIG_TLS_CERT_FILE || NULL != CONFIG_TLS_KEY_FILE ||
			NULL != CONFIG_TLS_PSK_IDENTITY || NULL != CONFIG_TLS_PSK_FILE)
	{
#if defined(HAVE_POLARSSL) || defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		zbx_tls_validate_config();

		if (ZBX_TCP_SEC_UNENCRYPTED != configured_tls_connect_mode)
		{
#if defined(_WINDOWS)
			zbx_tls_init_parent();
#endif
			zbx_tls_init_child();
		}
#endif
	}
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
	if (ZBX_TCP_SEC_UNENCRYPTED != configured_tls_connect_mode)
	{
		zbx_tls_free();
#if defined(_WINDOWS)
		zbx_tls_library_deinit();
#endif
	}
#endif
	return SUCCEED == ret ? EXIT_SUCCESS : EXIT_FAILURE;
}
