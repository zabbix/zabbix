/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "zbxstr.h"
#include "zbxnum.h"
#include "zbxcomms.h"
#include "zbxgetopt.h"
#include "zbxagentget.h"
#include "zbxversion.h"
#include "zbxlog.h"
#include "zbxjson.h"
#include "zbxbincommon.h"

#ifndef _WINDOWS
#	include "zbxnix.h"
#else
#	include "zbxwin32.h"
#endif

typedef enum
{
	ZBX_AUTO_PROTOCOL,
	ZBX_JSON_PROTOCOL,
	ZBX_PLAINTEXT_PROTOCOL
}
zbx_protocol_t;

ZBX_GET_CONFIG_VAR2(const char *, const char *, zbx_progname, NULL)
static const char	title_message[] = "zabbix_get";
static const char	*usage_message[] = {
	"-s host-name-or-IP", "[-p port-number]", "[-I IP-address]", "[-t timeout]", "-k item-key", NULL,
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	"-s host-name-or-IP", "[-p port-number]", "[-I IP-address]", "[-t timeout]", "--tls-connect cert",
	"--tls-ca-file CA-file", "[--tls-crl-file CRL-file]", "[--tls-agent-cert-issuer cert-issuer]",
	"[--tls-agent-cert-subject cert-subject]", "--tls-cert-file cert-file", "--tls-key-file key-file",
#if defined(HAVE_OPENSSL)
	"[--tls-cipher13 cipher-string]",
#endif
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	"[--tls-cipher cipher-string]",
#endif
	"-k item-key", NULL,
	"-s host-name-or-IP", "[-p port-number]", "[-I IP-address]", "[-t timeout]", "--tls-connect psk",
	"--tls-psk-identity PSK-identity", "--tls-psk-file PSK-file",
#if defined(HAVE_OPENSSL)
	"[--tls-cipher13 cipher-string]",
#endif
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	"[--tls-cipher cipher-string]",
#endif
	"-k item-key", NULL,
#endif
	"-h", NULL,
	"-V", NULL,
	NULL	/* end of text */
};

ZBX_GET_CONFIG_VAR(unsigned char, zbx_program_type, ZBX_PROGRAM_TYPE_GET)

#define CONFIG_GET_TIMEOUT_MIN		1
#define CONFIG_GET_TIMEOUT_MAX		30
#define CONFIG_GET_TIMEOUT_MIN_STR	ZBX_STR(CONFIG_GET_TIMEOUT_MIN)
#define CONFIG_GET_TIMEOUT_MAX_STR	ZBX_STR(CONFIG_GET_TIMEOUT_MAX)

static int	CONFIG_GET_TIMEOUT = CONFIG_GET_TIMEOUT_MAX;

static const char	*help_message[] = {
	"Get data from Zabbix agent.",
	"",
	"General options:",
	"  -s --host host-name-or-IP  Specify host name or IP address of a host",
	"  -p --port port-number      Specify port number of agent running on the host",
	"                             (default: " ZBX_DEFAULT_AGENT_PORT_STR ")",
	"  -I --source-address IP-address   Specify source IP address",
	"",
	"  -t --timeout seconds       Specify timeout. Valid range: " CONFIG_GET_TIMEOUT_MIN_STR "-"
			CONFIG_GET_TIMEOUT_MAX_STR " seconds",
	"                             (default: " CONFIG_GET_TIMEOUT_MAX_STR " seconds)",
	"",
	"  -k --key item-key          Specify key of the item to retrieve value for",
	"",
	"  --protocol value           Protocol used to communicate with agent. Values:",
	"                               auto      - connect using JSON protocol,",
	"                                           fallback and retry with",
	"                                           plaintext protocol (default)",
	"                               json      - connect using JSON protocol",
	"                               plaintext - connect using plaintext protocol",
	"                                           where just item key is sent",
	"                                           (6.4.x and older releases)",
	"",
	"",
	"  -h --help                  Display this help message",
	"  -V --version               Display version number",
	"",
	"TLS connection options:",
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
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
#if defined(HAVE_OPENSSL)
	"",
	"  --tls-cipher13             Cipher string for OpenSSL 1.1.1 or newer for",
	"                             TLS 1.3. Override the default ciphersuite",
	"                             selection criteria. This option is not available",
	"                             if OpenSSL version is less than 1.1.1",
#endif
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	"",
	"  --tls-cipher               GnuTLS priority string (for TLS 1.2 and up) or",
	"                             OpenSSL cipher string (only for TLS 1.2).",
	"                             Override the default ciphersuite selection",
	"                             criteria",
#endif
#else
	"  Not available. This 'zabbix_get' was compiled without TLS support",
#endif
	"",
	"Example(s):",
	"  zabbix_get -s 127.0.0.1 -p " ZBX_DEFAULT_AGENT_PORT_STR " -k \"system.cpu.load[all,avg1]\"",
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
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

static zbx_config_tls_t	*zbx_config_tls = NULL;

int	CONFIG_TCP_MAX_BACKLOG_SIZE	= SOMAXCONN;

/* COMMAND LINE OPTIONS */

/* long options */
struct zbx_option	longopts[] =
{
	{"host",			1,	NULL,	's'},
	{"port",			1,	NULL,	'p'},
	{"key",				1,	NULL,	'k'},
	{"source-address",		1,	NULL,	'I'},
	{"timeout",			1,	NULL,	't'},
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
	{"tls-cipher13",		1,	NULL,	'A'},
	{"tls-cipher",			1,	NULL,	'B'},
	{"protocol",			1,	NULL,	'P'},
	{0}
};

/* short options */
static char	shortopts[] = "s:p:k:I:t:hVP:";

/* end of COMMAND LINE OPTIONS */

#if !defined(_WINDOWS)

/******************************************************************************
 *                                                                            *
 * Purpose: process signals                                                   *
 *                                                                            *
 * Parameters: sig - signal ID                                                *
 *                                                                            *
 ******************************************************************************/
static void	get_signal_handler(int sig)
{
	if (SIGPIPE == sig)	/* this signal is raised when peer closes connection because of access restrictions */
		return;

	if (SIGALRM == sig)
		zbx_error("Timeout while executing operation");

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	if (ZBX_TCP_SEC_UNENCRYPTED != zbx_config_tls->connect_mode)
		zbx_tls_free_on_signal();
#endif
	exit(EXIT_FAILURE);
}

#endif /* not WINDOWS */

/******************************************************************************
 *                                                                            *
 * Purpose: connect to Zabbix agent, receive and print value                  *
 *                                                                            *
 * Parameters: host - server name or IP address                               *
 *             port - port number                                             *
 *             key  - item's key                                              *
 *                                                                            *
 ******************************************************************************/
static int	get_value(const char *source_ip, const char *host, unsigned short port, const char *key, int *version,
		zbx_protocol_t protocol)
{
	zbx_socket_t	s;
	int		ret;
	ssize_t		received_len = -1;
	char		*tls_arg1, *tls_arg2;

	switch (zbx_config_tls->connect_mode)
	{
		case ZBX_TCP_SEC_UNENCRYPTED:
			tls_arg1 = NULL;
			tls_arg2 = NULL;
			break;
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		case ZBX_TCP_SEC_TLS_CERT:
			tls_arg1 = zbx_config_tls->server_cert_issuer;
			tls_arg2 = zbx_config_tls->server_cert_subject;
			break;
		case ZBX_TCP_SEC_TLS_PSK:
			tls_arg1 = zbx_config_tls->psk_identity;
			tls_arg2 = NULL;	/* zbx_tls_connect() will find PSK */
			break;
#endif
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			return FAIL;
	}

	if (SUCCEED == (ret = zbx_tcp_connect(&s, source_ip, host, port, CONFIG_GET_TIMEOUT + 1,
			zbx_config_tls->connect_mode, tls_arg1, tls_arg2)))
	{
		struct zbx_json	j;
		const char	*ptr;
		size_t		len;
		int		retry = 0;

		zbx_json_init(&j, ZBX_JSON_STAT_BUF_LEN);

		if (ZBX_PLAINTEXT_PROTOCOL == protocol)
			*version = 0;

		if (ZBX_COMPONENT_VERSION(7, 0, 0) <= *version)
		{
			zbx_agent_prepare_request(&j, key, CONFIG_GET_TIMEOUT);
			ptr = j.buffer;
			len = j.buffer_size;
		}
		else
		{
			ptr = key;
			len = strlen(key);
		}

		if (SUCCEED == (ret = zbx_tcp_send_ext(&s, ptr, len, 0, ZBX_TCP_PROTOCOL, 0)))
		{
			if (FAIL == (received_len = zbx_tcp_recv_ext(&s, 0, 0)))
				ret = FAIL;
			else
				(void)zbx_tcp_read_close_notify(&s, 0, NULL);
		}

		if (SUCCEED == ret)
		{
			AGENT_RESULT	result;

			zbx_init_agent_result(&result)

			if (FAIL == (ret = zbx_agent_handle_response(s.buffer, s.read_bytes, received_len, host,
					&result, version)))
			{
				retry = 1;
			}
			else
			{
				if (SUCCEED != ret)
				{
					zbx_rtrim(result.msg, "\r\n");
					printf("%s: %s\n", ZBX_NOTSUPPORTED, result.msg);
				}
				else if (0 == ZBX_ISSET_VALUE(&result))
				{
					puts(ZBX_NODATA ": No value was received.");
				}
				else
				{
					zbx_rtrim(result.text, "\r\n");
					printf("%s\n", result.text);
				}
			}

			zbx_free_agent_result(&result)
		}
		else
			zbx_error("Get value error: %s", zbx_socket_strerror());

		zbx_tcp_close(&s);

		if (1 == retry)
		{
			if (ZBX_AUTO_PROTOCOL == protocol)
			{
				return get_value(source_ip, host, port, key, version, protocol);
			}
			else
				zbx_error("Get value error: JSON protocol requested but received plaintext response");
		}
	}
	else
		zbx_error("Get value error: %s", zbx_socket_strerror());

	return ret;
}

int	main(int argc, char **argv)
{
	int		i, ret = SUCCEED, version = ZBX_COMPONENT_VERSION(7, 0, 0);
	char		*host = NULL, *key = NULL, *source_ip = NULL, ch;
	unsigned short	opt_count[256] = {0}, port = ZBX_DEFAULT_AGENT_PORT;
	zbx_protocol_t	protocol = ZBX_AUTO_PROTOCOL;
#if defined(_WINDOWS)
	char		*error = NULL;
#endif
	/* see description of 'optarg' in 'man 3 getopt' */
	char		*zbx_optarg = NULL;

	/* see description of 'optind' in 'man 3 getopt' */
	int		zbx_optind = 0;

	zbx_progname = get_program_name(argv[0]);

	zbx_init_library_common(zbx_log_impl, get_zbx_progname, zbx_backtrace);
#ifndef _WINDOWS
	zbx_init_library_nix(get_zbx_progname, NULL);
#endif
#if !defined(_WINDOWS) && (defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL))
	if (SUCCEED != zbx_coredump_disable())
	{
		zbx_error("cannot disable core dump, exiting...");
		exit(EXIT_FAILURE);
	}
#endif
	zbx_config_tls = zbx_config_tls_new();

	/* parse the command-line */
	while ((char)EOF != (ch = (char)zbx_getopt_long(argc, argv, shortopts, longopts, NULL, &zbx_optarg,
			&zbx_optind)))
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
			case 't':
				if (FAIL == zbx_is_uint_n_range(zbx_optarg, ZBX_MAX_UINT64_LEN, &CONFIG_GET_TIMEOUT,
						sizeof(CONFIG_GET_TIMEOUT), CONFIG_GET_TIMEOUT_MIN,
						CONFIG_GET_TIMEOUT_MAX))
				{
					zbx_error("Invalid timeout, valid range %d:%d seconds", CONFIG_GET_TIMEOUT_MIN,
							CONFIG_GET_TIMEOUT_MAX);
					exit(EXIT_FAILURE);
				}
				break;
			case 'h':
				zbx_print_help(zbx_progname, help_message, usage_message, NULL);
				exit(EXIT_SUCCESS);
			case 'V':
				zbx_print_version(title_message);
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
				printf("\n");
				zbx_tls_version();
#endif
				exit(EXIT_SUCCESS);
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
			case '1':
				zbx_config_tls->connect = zbx_strdup(zbx_config_tls->connect,
						zbx_optarg);
				break;
			case '2':
				zbx_config_tls->ca_file = zbx_strdup(zbx_config_tls->ca_file,
						zbx_optarg);
				break;
			case '3':
				zbx_config_tls->crl_file = zbx_strdup(zbx_config_tls->crl_file,
						zbx_optarg);
				break;
			case '4':
				zbx_config_tls->server_cert_issuer =
						zbx_strdup(zbx_config_tls->server_cert_issuer, zbx_optarg);
				break;
			case '5':
				zbx_config_tls->server_cert_subject =
						zbx_strdup(zbx_config_tls->server_cert_subject, zbx_optarg);
				break;
			case '6':
				zbx_config_tls->cert_file = zbx_strdup(zbx_config_tls->cert_file,
						zbx_optarg);
				break;
			case '7':
				zbx_config_tls->key_file = zbx_strdup(zbx_config_tls->key_file,
						zbx_optarg);
				break;
			case '8':
				zbx_config_tls->psk_identity = zbx_strdup(zbx_config_tls->psk_identity,
						zbx_optarg);
				break;
			case '9':
				zbx_config_tls->psk_file = zbx_strdup(zbx_config_tls->psk_file,
						zbx_optarg);
				break;
			case 'A':
#if defined(HAVE_OPENSSL)
				zbx_config_tls->cipher_cmd13 =
						zbx_strdup(zbx_config_tls->cipher_cmd13, zbx_optarg);
#elif defined(HAVE_GNUTLS)
				zbx_error("parameter \"--tls-cipher13\" can be used with OpenSSL 1.1.1 or newer."
						" zabbix_get was compiled with GnuTLS");
				exit(EXIT_FAILURE);
#endif
				break;
			case 'B':
				zbx_config_tls->cipher_cmd = zbx_strdup(zbx_config_tls->cipher_cmd,
						zbx_optarg);
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
			case 'A':
			case 'B':
				zbx_error("TLS parameters cannot be used: 'zabbix_get' was compiled without TLS"
						" support");
				exit(EXIT_FAILURE);
				break;
#endif
			case 'P':
				if (0 == strcmp(zbx_optarg, "json"))
				{
					protocol = ZBX_JSON_PROTOCOL;
				}
				else if (0 == strcmp(zbx_optarg, "plaintext"))
				{
					protocol = ZBX_PLAINTEXT_PROTOCOL;
				}
				else if (0 == strcmp(zbx_optarg, "auto"))
				{
					protocol = ZBX_AUTO_PROTOCOL;
				}
				else
				{
					zbx_error("Invalid protocol \"%s\"", zbx_optarg);
					exit(EXIT_FAILURE);
				}
				break;
			default:
				zbx_print_usage(zbx_progname, usage_message);
				exit(EXIT_FAILURE);
		}
	}

#if defined(_WINDOWS)
	if (SUCCEED != zbx_socket_start(&error))
	{
		zbx_error(error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}
#endif

	if (NULL == host || NULL == key)
	{
		zbx_print_usage(zbx_progname, usage_message);
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
		printf("Try '%s --help' for more information.\n", zbx_progname);
		goto out;
	}

	if (NULL != zbx_config_tls->connect || NULL != zbx_config_tls->ca_file || NULL != zbx_config_tls->crl_file ||
			NULL != zbx_config_tls->server_cert_issuer || NULL != zbx_config_tls->server_cert_subject ||
			NULL != zbx_config_tls->cert_file || NULL != zbx_config_tls->key_file ||
			NULL != zbx_config_tls->psk_identity || NULL != zbx_config_tls->psk_file ||
			NULL != zbx_config_tls->cipher_cmd13 || NULL != zbx_config_tls->cipher_cmd)
	{
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		zbx_tls_validate_config(zbx_config_tls, 0, 0, get_zbx_program_type);

		if (ZBX_TCP_SEC_UNENCRYPTED != zbx_config_tls->connect_mode)
		{
#if defined(_WINDOWS)
			zbx_tls_init_parent(get_zbx_program_type);
#endif
			zbx_tls_init_child(zbx_config_tls, get_zbx_program_type, NULL);
		}
#else
		ZBX_UNUSED(get_zbx_program_type);
#endif
	}
#if !defined(_WINDOWS)
	signal(SIGINT, get_signal_handler);
	signal(SIGQUIT, get_signal_handler);
	signal(SIGTERM, get_signal_handler);
	signal(SIGHUP, get_signal_handler);
	signal(SIGALRM, get_signal_handler);
	signal(SIGPIPE, get_signal_handler);
#endif
	ret = get_value(source_ip, host, port, key, &version, protocol);
out:
	zbx_free(host);
	zbx_free(key);
	zbx_free(source_ip);
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	if (ZBX_TCP_SEC_UNENCRYPTED != zbx_config_tls->connect_mode)
	{
		zbx_tls_free();
		zbx_tls_library_deinit(ZBX_TLS_INIT_THREADS);
	}
#endif
	zbx_config_tls_free(zbx_config_tls);
#if defined(_WINDOWS)
	while (0 == WSACleanup())
		;
#endif

	return SUCCEED == ret ? EXIT_SUCCESS : EXIT_FAILURE;
}
