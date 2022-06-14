/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "threads.h"
#include "comms.h"
#include "cfg.h"
#include "log.h"
#include "zbxgetopt.h"
#include "zbxjson.h"
#include "mutexs.h"
#include "zbxcrypto.h"
#if defined(_WINDOWS)
#	include "../libs/zbxcrypto/tls.h"
#else
#	include "zbxnix.h"
#endif

const char	*progname = NULL;
const char	title_message[] = "zabbix_sender";
const char	syslog_app_name[] = "zabbix_sender";

const char	*usage_message[] = {
	"[-v]", "-z server", "[-p port]", "[-I IP-address]", "[-t timeout]", "-s host", "-k key", "-o value", NULL,
	"[-v]", "-z server", "[-p port]", "[-I IP-address]", "[-t timeout]", "[-s host]", "[-T]", "[-N]", "[-r]",
	"-i input-file", NULL,
	"[-v]", "-c config-file", "[-z server]", "[-p port]", "[-I IP-address]", "[-t timeout]", "[-s host]", "-k key",
	"-o value", NULL,
	"[-v]", "-c config-file", "[-z server]", "[-p port]", "[-I IP-address]", "[-t timeout]", "[-s host]", "[-T]",
	"[-N]", "[-r]", "-i input-file", NULL,
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	"[-v]", "-z server", "[-p port]", "[-I IP-address]", "[-t timeout]", "-s host", "--tls-connect cert",
	"--tls-ca-file CA-file", "[--tls-crl-file CRL-file]", "[--tls-server-cert-issuer cert-issuer]",
	"[--tls-server-cert-subject cert-subject]", "--tls-cert-file cert-file", "--tls-key-file key-file",
#if defined(HAVE_OPENSSL)
	"[--tls-cipher13 cipher-string]",
#endif
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	"[--tls-cipher cipher-string]",
#endif
	"-k key", "-o value", NULL,
	"[-v]", "-z server", "[-p port]", "[-I IP-address]", "[-t timeout]", "[-s host]", "--tls-connect cert",
	"--tls-ca-file CA-file", "[--tls-crl-file CRL-file]", "[--tls-server-cert-issuer cert-issuer]",
	"[--tls-server-cert-subject cert-subject]", "--tls-cert-file cert-file", "--tls-key-file key-file",
#if defined(HAVE_OPENSSL)
	"[--tls-cipher13] cipher-string",
#endif
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	"[--tls-cipher cipher-string]",
#endif
	"[-T]", "[-N]", "[-r]", "-i input-file", NULL,
	"[-v]", "-c config-file [-z server]", "[-p port]", "[-I IP-address]", "[-t timeout]", "[-s host]",
	"--tls-connect cert", "--tls-ca-file CA-file", "[--tls-crl-file CRL-file]",
	"[--tls-server-cert-issuer cert-issuer]", "[--tls-server-cert-subject cert-subject]",
	"--tls-cert-file cert-file", "--tls-key-file key-file",
#if defined(HAVE_OPENSSL)
	"[--tls-cipher13 cipher-string]",
#endif
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	"[--tls-cipher cipher-string]",
#endif
	"-k key", "-o value", NULL,
	"[-v]", "-c config-file", "[-z server]", "[-p port]", "[-I IP-address]", "[-t timeout]", "[-s host]",
	"--tls-connect cert", "--tls-ca-file CA-file", "[--tls-crl-file CRL-file]",
	"[--tls-server-cert-issuer cert-issuer]", "[--tls-server-cert-subject cert-subject]",
	"--tls-cert-file cert-file", "--tls-key-file key-file",
#if defined(HAVE_OPENSSL)
	"[--tls-cipher13 cipher-string]",
#endif
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	"[--tls-cipher cipher-string]",
#endif
	"[-T]", "[-N]", "[-r]", "-i input-file", NULL,
	"[-v]", "-z server", "[-p port]", "[-I IP-address]", "[-t timeout]", "-s host", "--tls-connect psk",
	"--tls-psk-identity PSK-identity", "--tls-psk-file PSK-file",
#if defined(HAVE_OPENSSL)
	"[--tls-cipher13 cipher-string]",
#endif
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	"[--tls-cipher cipher-string]",
#endif
	"-k key", "-o value", NULL,
	"[-v]", "-z server", "[-p port]", "[-I IP-address]", "[-t timeout]", "[-s host]", "--tls-connect psk",
	"--tls-psk-identity PSK-identity", "--tls-psk-file PSK-file",
#if defined(HAVE_OPENSSL)
	"[--tls-cipher13 cipher-string]",
#endif
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	"[--tls-cipher cipher-string]",
#endif
	"[-T]", "[-N]", "[-r]", "-i input-file", NULL,
	"[-v]", "-c config-file", "[-z server]", "[-p port]", "[-I IP-address]", "[-t timeout]", "[-s host]",
	"--tls-connect psk", "--tls-psk-identity PSK-identity", "--tls-psk-file PSK-file",
#if defined(HAVE_OPENSSL)
	"[--tls-cipher13 cipher-string]",
#endif
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	"[--tls-cipher cipher-string]",
#endif
	"-k key", "-o value", NULL,
	"[-v]", "-c config-file", "[-z server]", "[-p port]", "[-I IP-address]", "[-t timeout]", "[-s host]",
	"--tls-connect psk", "--tls-psk-identity PSK-identity", "--tls-psk-file PSK-file",
#if defined(HAVE_OPENSSL)
	"[--tls-cipher13 cipher-string]",
#endif
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	"[--tls-cipher cipher-string]",
#endif
	"[-T]", "[-N]", "[-r]", "-i input-file", NULL,
#endif
	"-h", NULL,
	"-V", NULL,
	NULL	/* end of text */
};

unsigned char	program_type	= ZBX_PROGRAM_TYPE_SENDER;

static int	CONFIG_SENDER_TIMEOUT = GET_SENDER_TIMEOUT;

#define CONFIG_SENDER_TIMEOUT_MIN	1
#define CONFIG_SENDER_TIMEOUT_MAX	300
#define CONFIG_SENDER_TIMEOUT_MIN_STR	ZBX_STR(CONFIG_SENDER_TIMEOUT_MIN)
#define CONFIG_SENDER_TIMEOUT_MAX_STR	ZBX_STR(CONFIG_SENDER_TIMEOUT_MAX)

const char	*help_message[] = {
	"Utility for sending monitoring data to Zabbix server or proxy.",
	"",
	"General options:",
	"  -c --config config-file    Path to Zabbix agentd configuration file",
	"",
	"  -z --zabbix-server server  Hostname or IP address of Zabbix server or proxy",
	"                             to send data to. When used together with --config,",
	"                             overrides the first entry of \"ServerActive\"",
	"                             parameter specified in agentd configuration file",
	"",
	"  -p --port port             Specify port number of trapper process of Zabbix",
	"                             server or proxy. When used together with --config,",
	"                             overrides the port of the first entry of",
	"                             \"ServerActive\" parameter specified in agentd",
	"                             configuration file (default: " ZBX_DEFAULT_SERVER_PORT_STR ")",
	"",
	"  -I --source-address IP-address   Specify source IP address. When used",
	"                             together with --config, overrides \"SourceIP\"",
	"                             parameter specified in agentd configuration file",
	"",
	"  -t --timeout seconds       Specify timeout. Valid range: " CONFIG_SENDER_TIMEOUT_MIN_STR "-"
			CONFIG_SENDER_TIMEOUT_MAX_STR " seconds",
	"                             (default: " ZBX_STR(GET_SENDER_TIMEOUT) " seconds)",
	"",
	"  -s --host host             Specify host name the item belongs to (as",
	"                             registered in Zabbix frontend). Host IP address",
	"                             and DNS name will not work. When used together",
	"                             with --config, overrides \"Hostname\" parameter",
	"                             specified in agentd configuration file",
	"",
	"  -k --key key               Specify item key",
	"  -o --value value           Specify item value",
	"",
	"  -i --input-file input-file   Load values from input file. Specify - for",
	"                             standard input. Each line of file contains",
	"                             whitespace delimited: <host> <key> <value>.",
	"                             Specify - in <host> to use hostname from",
	"                             configuration file or --host argument",
	"",
	"  -T --with-timestamps       Each line of file contains whitespace delimited:",
	"                             <host> <key> <timestamp> <value>. This can be used",
	"                             with --input-file option. Timestamp should be",
	"                             specified in Unix timestamp format",
	"",
	"  -N --with-ns               Each line of file contains whitespace delimited:",
	"                             <host> <key> <timestamp> <ns> <value>. This can be used",
	"                             with --with-timestamps option",
	"",
	"  -r --real-time             Send metrics one by one as soon as they are",
	"                             received. This can be used when reading from",
	"                             standard input",
	"",
	"  -v --verbose               Verbose mode, -vv for more details",
	"",
	"  -h --help                  Display this help message",
	"  -V --version               Display version number",
	"",
	"TLS connection options:",
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	"  --tls-connect value        How to connect to server or proxy. Values:",
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
	"  --tls-server-cert-issuer cert-issuer   Allowed server certificate issuer",
	"",
	"  --tls-server-cert-subject cert-subject   Allowed server certificate subject",
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
	"  Not available. This Zabbix sender was compiled without TLS support",
#endif
	"",
	"Example(s):",
	"  zabbix_sender -z 127.0.0.1 -s \"Linux DB3\" -k db.connections -o 43",
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	"",
	"  zabbix_sender -z 127.0.0.1 -s \"Linux DB3\" -k db.connections -o 43 \\",
	"    --tls-connect cert --tls-ca-file /home/zabbix/zabbix_ca_file \\",
	"    --tls-server-cert-issuer \\",
	"    \"CN=Signing CA,OU=IT operations,O=Example Corp,DC=example,DC=com\" \\",
	"    --tls-server-cert-subject \\",
	"    \"CN=Zabbix proxy,OU=IT operations,O=Example Corp,DC=example,DC=com\" \\",
	"    --tls-cert-file /home/zabbix/zabbix_agentd.crt \\",
	"    --tls-key-file /home/zabbix/zabbix_agentd.key",
	"",
	"  zabbix_sender -z 127.0.0.1 -s \"Linux DB3\" -k db.connections -o 43 \\",
	"    --tls-connect psk --tls-psk-identity \"PSK ID Zabbix agentd\" \\",
	"    --tls-psk-file /home/zabbix/zabbix_agentd.psk",
#endif
	NULL	/* end of text */
};

/* TLS parameters */
unsigned int	configured_tls_connect_mode = ZBX_TCP_SEC_UNENCRYPTED;
unsigned int	configured_tls_accept_modes = ZBX_TCP_SEC_UNENCRYPTED;	/* not used in zabbix_sender, just for */
									/* linking with tls.c */
char	*CONFIG_TLS_CONNECT		= NULL;
char	*CONFIG_TLS_ACCEPT		= NULL;	/* not used in zabbix_sender, just for linking with tls.c */
char	*CONFIG_TLS_CA_FILE		= NULL;
char	*CONFIG_TLS_CRL_FILE		= NULL;
char	*CONFIG_TLS_SERVER_CERT_ISSUER	= NULL;
char	*CONFIG_TLS_SERVER_CERT_SUBJECT	= NULL;
char	*CONFIG_TLS_CERT_FILE		= NULL;
char	*CONFIG_TLS_KEY_FILE		= NULL;
char	*CONFIG_TLS_PSK_IDENTITY	= NULL;
char	*CONFIG_TLS_PSK_FILE		= NULL;

char	*CONFIG_TLS_CIPHER_CERT13	= NULL;	/* parameter 'TLSCipherCert13' from agent config file */
char	*CONFIG_TLS_CIPHER_CERT		= NULL;	/* parameter 'TLSCipherCert' from agent config file */
char	*CONFIG_TLS_CIPHER_PSK13	= NULL;	/* parameter 'TLSCipherPSK13' from agent config file */
char	*CONFIG_TLS_CIPHER_PSK		= NULL;	/* parameter 'TLSCipherPSK' from agent config file */
char	*CONFIG_TLS_CIPHER_ALL13	= NULL;	/* not used in zabbix_sender, just for linking with tls.c */
char	*CONFIG_TLS_CIPHER_ALL		= NULL;	/* not used in zabbix_sender, just for linking with tls.c */
char	*CONFIG_TLS_CIPHER_CMD13	= NULL;	/* parameter '--tls-cipher13' from sender command line */
char	*CONFIG_TLS_CIPHER_CMD		= NULL;	/* parameter '--tls-cipher' from sender command line */

int	CONFIG_PASSIVE_FORKS		= 0;	/* not used in zabbix_sender, just for linking with tls.c */
int	CONFIG_ACTIVE_FORKS		= 0;	/* not used in zabbix_sender, just for linking with tls.c */

int	CONFIG_TCP_MAX_BACKLOG_SIZE	= SOMAXCONN;

/* COMMAND LINE OPTIONS */

/* long options */
static struct zbx_option	longopts[] =
{
	{"config",			1,	NULL,	'c'},
	{"zabbix-server",		1,	NULL,	'z'},
	{"port",			1,	NULL,	'p'},
	{"host",			1,	NULL,	's'},
	{"source-address",		1,	NULL,	'I'},
	{"timeout",			1,	NULL,	't'},
	{"key",				1,	NULL,	'k'},
	{"value",			1,	NULL,	'o'},
	{"input-file",			1,	NULL,	'i'},
	{"with-timestamps",		0,	NULL,	'T'},
	{"with-ns",			0,	NULL,	'N'},
	{"real-time",			0,	NULL,	'r'},
	{"verbose",			0,	NULL,	'v'},
	{"help",			0,	NULL,	'h'},
	{"version",			0,	NULL,	'V'},
	{"tls-connect",			1,	NULL,	'1'},
	{"tls-ca-file",			1,	NULL,	'2'},
	{"tls-crl-file",		1,	NULL,	'3'},
	{"tls-server-cert-issuer",	1,	NULL,	'4'},
	{"tls-server-cert-subject",	1,	NULL,	'5'},
	{"tls-cert-file",		1,	NULL,	'6'},
	{"tls-key-file",		1,	NULL,	'7'},
	{"tls-psk-identity",		1,	NULL,	'8'},
	{"tls-psk-file",		1,	NULL,	'9'},
	{"tls-cipher13",		1,	NULL,	'A'},
	{"tls-cipher",			1,	NULL,	'B'},
	{NULL}
};

/* short options */
static char	shortopts[] = "c:I:t:z:p:s:k:o:TNi:rvhV";

/* end of COMMAND LINE OPTIONS */

static int	CONFIG_LOG_LEVEL = LOG_LEVEL_CRIT;

static char	*INPUT_FILE = NULL;
static int	WITH_TIMESTAMPS = 0;
static int	WITH_NS = 0;
static int	REAL_TIME = 0;

char		*CONFIG_SOURCE_IP = NULL;
static char	*ZABBIX_SERVER = NULL;
static char	*ZABBIX_SERVER_PORT = NULL;
static char	*ZABBIX_HOSTNAME = NULL;
static char	*ZABBIX_KEY = NULL;
static char	*ZABBIX_KEY_VALUE = NULL;

typedef struct
{
	zbx_vector_ptr_t	addrs;
	ZBX_THREAD_HANDLE	*thread;
}
zbx_send_destinations_t;

static zbx_send_destinations_t	*destinations = NULL;		/* list of servers to send data to */
static int			destinations_count = 0;

volatile sig_atomic_t	sig_exiting = 0;

#if !defined(_WINDOWS)
static void	sender_signal_handler(int sig)
{
#define CASE_LOG_WARNING(signal) \
	case signal:							\
		zabbix_log(LOG_LEVEL_WARNING, "interrupted by signal " #signal " while executing operation"); \
		break

	switch (sig)
	{
		CASE_LOG_WARNING(SIGINT);
		CASE_LOG_WARNING(SIGQUIT);
		CASE_LOG_WARNING(SIGTERM);
		CASE_LOG_WARNING(SIGHUP);
		CASE_LOG_WARNING(SIGPIPE);
		default:
			zabbix_log(LOG_LEVEL_WARNING, "signal %d while executing operation", sig);
	}
#undef CASE_LOG_WARNING

	/* Calling _exit() to terminate the process immediately is important. See ZBX-5732 for details. */
	/* Return FAIL instead of EXIT_FAILURE to keep return signals consistent for send_value() */
	_exit(FAIL);
}

static void	main_signal_handler(int sig)
{
	if (0 == sig_exiting)
	{
		int	i;

		sig_exiting = 1;

		for (i = 0; i < destinations_count; i++)
		{
			pid_t	child = *(destinations[i].thread);

			if (ZBX_THREAD_HANDLE_NULL != child)
				kill(child, sig);
		}
	}
}
#endif

typedef struct
{
	zbx_vector_ptr_t		*addrs;
	struct zbx_json			json;
#if defined(_WINDOWS) && (defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL))
	ZBX_THREAD_SENDVAL_TLS_ARGS	tls_vars;
#endif
	int				sync_timestamp;
#ifndef _WINDOWS
	int				fds[2];
#endif
}
ZBX_THREAD_SENDVAL_ARGS;

#define SUCCEED_PARTIAL	2

#if !defined(_WINDOWS)
static void	zbx_thread_handle_pipe_response(ZBX_THREAD_SENDVAL_ARGS *sendval_args)
{
	int	offset;
	char	buffer[sizeof(int)], *ptr = buffer;

	while (0 < (offset = (int)read(sendval_args->fds[0], ptr, (size_t)(buffer + sizeof(buffer) - ptr))))
		ptr += offset;

	if (-1 == offset)
		zabbix_log(LOG_LEVEL_WARNING, "cannot read data from pipe: %s", zbx_strerror(errno));

	if (ptr - buffer != sizeof(int))
	{
		zabbix_log(LOG_LEVEL_ERR, "Incorrect response from child thread");
		return;
	}

	memcpy(&offset, buffer, sizeof(int));

	while (0 < offset--)
	{
		zbx_addr_t	*addr = sendval_args->addrs->values[0];

		zbx_vector_ptr_remove(sendval_args->addrs, 0);
		zbx_vector_ptr_append(sendval_args->addrs, addr);
	}
}
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: waits until the "threads" are in the signalled state and manages  *
 *          exit status updates                                               *
 *                                                                            *
 * Parameters:                                                                *
 *      threads -     [IN] thread handles                                     *
 *      threads_num - [IN] thread count                                       *
 *      old_status  - [IN] previous status                                    *
 *                                                                            *
 * Return value:  SUCCEED - success with all values at all destinations       *
 *                FAIL - an error occurred                                    *
 *                SUCCEED_PARTIAL - data sending was completed successfully   *
 *                to at least one destination or processing of at least one   *
 *                value at least at one destination failed                    *
 *                                                                            *
 * Comments: SUCCEED_PARTIAL status should be sticky in the sense that        *
 *           SUCCEED statuses that come after should not overwrite it         *
 *                                                                            *
 ******************************************************************************/
static int	sender_threads_wait(ZBX_THREAD_HANDLE *threads, zbx_thread_args_t *threads_args, int threads_num,
		const int old_status)
{
	int		i, sp_count = 0, fail_count = 0;
#if defined(_WINDOWS)
	/* wait for threads to finish */
	WaitForMultipleObjectsEx(threads_num, threads, TRUE, INFINITE, FALSE);
#endif
	for (i = 0; i < threads_num; i++)
	{
		int	new_status;

		if (ZBX_THREAD_ERROR == threads[i])
		{
			threads[i] = ZBX_THREAD_HANDLE_NULL;
			continue;
		}

		if (SUCCEED_PARTIAL == (new_status = zbx_thread_wait(threads[i])))
				sp_count++;

		if (SUCCEED != new_status && SUCCEED_PARTIAL != new_status)
		{
			int	j;

			for (fail_count++, j = 0; j < destinations_count; j++)
			{
				if (destinations[j].thread == &threads[i])
				{
					zbx_vector_ptr_clear_ext(&destinations[j].addrs,
							(zbx_clean_func_t)zbx_addr_free);
					zbx_vector_ptr_destroy(&destinations[j].addrs);
					destinations[j] = destinations[--destinations_count];
					break;
				}
			}
		}
#if !defined(_WINDOWS)
		else
			zbx_thread_handle_pipe_response((ZBX_THREAD_SENDVAL_ARGS *)threads_args[i].args);

		close(((ZBX_THREAD_SENDVAL_ARGS *)threads_args[i].args)->fds[0]);
		close(((ZBX_THREAD_SENDVAL_ARGS *)threads_args[i].args)->fds[1]);
#endif

		threads[i] = ZBX_THREAD_HANDLE_NULL;
	}

	if (threads_num == fail_count)
		return FAIL;
	else if (SUCCEED_PARTIAL == old_status || 0 != sp_count || 0 != fail_count)
		return SUCCEED_PARTIAL;
	else
		return SUCCEED;
}

/******************************************************************************
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
 * Purpose: Check whether JSON response is SUCCEED                            *
 *                                                                            *
 * Parameters: JSON response from Zabbix trapper                              *
 *                                                                            *
 * Return value:  SUCCEED - processed successfully                            *
 *                FAIL - an error occurred                                    *
 *                SUCCEED_PARTIAL - the sending operation was completed       *
 *                successfully, but processing of at least one value failed   *
 *                                                                            *
 * Comments: active agent has almost the same function!                       *
 *                                                                            *
 ******************************************************************************/
static int	check_response(char *response, const char *server, unsigned short port)
{
	struct zbx_json_parse	jp;
	char			value[MAX_STRING_LEN];
	char			info[MAX_STRING_LEN];
	int			ret;

	ret = zbx_json_open(response, &jp);

	if (SUCCEED == ret)
		ret = zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_RESPONSE, value, sizeof(value), NULL);

	if (SUCCEED == ret && 0 != strcmp(value, ZBX_PROTO_VALUE_SUCCESS))
		ret = FAIL;

	if (SUCCEED == ret && SUCCEED == zbx_json_value_by_name(&jp, ZBX_PROTO_TAG_INFO, info, sizeof(info), NULL))
	{
		int	failed;

		printf("Response from \"%s:%hu\": \"%s\"\n", server, port, info);
		fflush(stdout);

		if (1 == sscanf(info, "processed: %*d; failed: %d", &failed) && 0 < failed)
			ret = SUCCEED_PARTIAL;
	}

	return ret;
}
#if !defined(_WINDOWS) && !defined(__MINGW32)
static void	alarm_signal_handler(int sig, siginfo_t *siginfo, void *context)
{
	ZBX_UNUSED(sig);
	ZBX_UNUSED(siginfo);
	ZBX_UNUSED(context);

	zbx_alarm_flag_set();	/* set alarm flag */
}

static void	zbx_set_sender_signal_handlers(void)
{
	struct sigaction	phan;

	sigemptyset(&phan.sa_mask);
	phan.sa_flags = SA_SIGINFO;

	phan.sa_sigaction = alarm_signal_handler;
	sigaction(SIGALRM, &phan, NULL);

	signal(SIGINT, sender_signal_handler);
	signal(SIGQUIT, sender_signal_handler);
	signal(SIGTERM, sender_signal_handler);
	signal(SIGHUP, sender_signal_handler);
	signal(SIGPIPE, sender_signal_handler);
}
#endif

static	ZBX_THREAD_ENTRY(send_value, args)
{
	ZBX_THREAD_SENDVAL_ARGS	*sendval_args = (ZBX_THREAD_SENDVAL_ARGS *)((zbx_thread_args_t *)args)->args;
	int			ret = FAIL;
	zbx_socket_t		sock;
#if !defined(_WINDOWS)
	int			i;
	zbx_addr_t		*last_addr;

	last_addr = (zbx_addr_t *)sendval_args->addrs->values[0];

	zbx_set_sender_signal_handlers();
#endif

#if defined(_WINDOWS) && (defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL))
	if (ZBX_TCP_SEC_UNENCRYPTED != configured_tls_connect_mode)
	{
		/* take TLS data passed from 'main' thread */
		zbx_tls_take_vars(&sendval_args->tls_vars);
	}
#endif
	if (SUCCEED == connect_to_server(&sock, CONFIG_SOURCE_IP, sendval_args->addrs,
			CONFIG_SENDER_TIMEOUT, CONFIG_TIMEOUT, configured_tls_connect_mode, 0, LOG_LEVEL_DEBUG))
	{
		if (1 == sendval_args->sync_timestamp)
		{
			zbx_timespec_t	ts;

			zbx_timespec(&ts);

			zbx_json_adduint64(&sendval_args->json, ZBX_PROTO_TAG_CLOCK, ts.sec);
			zbx_json_adduint64(&sendval_args->json, ZBX_PROTO_TAG_NS, ts.ns);
		}

		if (SUCCEED == zbx_tcp_send(&sock, sendval_args->json.buffer))
		{
			if (SUCCEED == zbx_tcp_recv(&sock))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "answer [%s]", sock.buffer);

				if (FAIL == (ret = check_response(sock.buffer,
						((zbx_addr_t *)sendval_args->addrs->values[0])->ip,
						((zbx_addr_t *)sendval_args->addrs->values[0])->port)))
				{
					zabbix_log(LOG_LEVEL_WARNING, "incorrect answer from \"%s:%hu\": [%s]",
							((zbx_addr_t *)sendval_args->addrs->values[0])->ip,
							((zbx_addr_t *)sendval_args->addrs->values[0])->port,
							sock.buffer);
				}
			}
			else
			{
				zabbix_log(LOG_LEVEL_DEBUG, "Unable to receive from [%s]:%d [%s]",
						((zbx_addr_t *)sendval_args->addrs->values[0])->ip,
						((zbx_addr_t *)sendval_args->addrs->values[0])->port,
						zbx_socket_strerror());
			}
		}
		else
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Unable to send to [%s]:%d [%s]",
					((zbx_addr_t *)sendval_args->addrs->values[0])->ip,
					((zbx_addr_t *)sendval_args->addrs->values[0])->port,
					zbx_socket_strerror());
		}

		zbx_tcp_close(&sock);
	}
#if !defined(_WINDOWS)
	for (i = sendval_args->addrs->values_num - 1; i >= 0; i--)
	{
		if (last_addr == sendval_args->addrs->values[i])
		{
			int	offset = sendval_args->addrs->values_num - i;

			if (0 == i)
				offset = 0;

			if (FAIL == zbx_write_all(sendval_args->fds[1], (char *)&offset, sizeof(offset)))
				zabbix_log(LOG_LEVEL_WARNING, "cannot write data to pipe: %s", zbx_strerror(errno));

			close(sendval_args->fds[0]);
			close(sendval_args->fds[1]);
			break;
		}
	}

#endif
	zbx_thread_exit(ret);
}

/******************************************************************************
 *                                                                            *
 * Purpose: Send data to all destinations each in a separate thread and wait  *
 *          till threads have completed their task                            *
 *                                                                            *
 * Parameters:                                                                *
 *      sendval_args - [IN] arguments for thread function                     *
 *      old_status   - [IN] previous status                                   *
 *                                                                            *
 * Return value:  SUCCEED - success with all values at all destinations       *
 *                FAIL - an error occurred                                    *
 *                SUCCEED_PARTIAL - data sending was completed successfully   *
 *                to at least one destination or processing of at least one   *
 *                value at least at one destination failed                    *
 *                                                                            *
 ******************************************************************************/
static int	perform_data_sending(ZBX_THREAD_SENDVAL_ARGS *sendval_args, int old_status)
{
	int			i, ret;
	ZBX_THREAD_HANDLE	*threads = NULL;
	zbx_thread_args_t	*threads_args;

	threads = (ZBX_THREAD_HANDLE *)zbx_calloc(threads, (size_t)destinations_count, sizeof(ZBX_THREAD_HANDLE));
	threads_args = (zbx_thread_args_t *)zbx_calloc(NULL, (size_t)destinations_count, sizeof(zbx_thread_args_t));

	for (i = 0; i < destinations_count; i++)
	{
		zbx_thread_args_t	*thread_args = threads_args + i;

		thread_args->args = &sendval_args[i];

		sendval_args[i].addrs = &destinations[i].addrs;

		if (0 != i)
		{
			sendval_args[i].json = sendval_args[0].json;
#if defined(_WINDOWS) && (defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL))
			sendval_args[i].tls_vars = sendval_args[0].tls_vars;
#endif
			sendval_args[i].sync_timestamp = sendval_args[0].sync_timestamp;
		}

		destinations[i].thread = &threads[i];
#ifndef _WINDOWS
		if (-1 == pipe(sendval_args[i].fds))
		{
			zabbix_log(LOG_LEVEL_ERR, "Cannot create data pipe: %s",
					strerror_from_system((unsigned long)errno));
			threads[i] = (ZBX_THREAD_HANDLE)ZBX_THREAD_ERROR;
			continue;
		}
#endif
		zbx_thread_start(send_value, thread_args, &threads[i]);
	}

	ret = sender_threads_wait(threads, threads_args, destinations_count, old_status);

	zbx_free(threads_args);
	zbx_free(threads);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add server or proxy to the list of destinations                   *
 *                                                                            *
 * Parameters:                                                                *
 *      host - [IN] IP or hostname                                            *
 *      port - [IN] port number                                               *
 *                                                                            *
 * Return value:  SUCCEED - destination added successfully                    *
 *                FAIL - destination has been already added                   *
 *                                                                            *
 ******************************************************************************/
static int	sender_add_serveractive_host_cb(const zbx_vector_ptr_t *addrs, zbx_vector_str_t *hostnames, void *data)
{
	ZBX_UNUSED(hostnames);
	ZBX_UNUSED(data);

	destinations_count++;
#if defined(_WINDOWS)
	if (MAXIMUM_WAIT_OBJECTS < destinations_count)
	{
		zbx_error("error parsing the \"ServerActive\" parameter: maximum destination limit of %d has been"
				" exceeded", MAXIMUM_WAIT_OBJECTS);
		exit(EXIT_FAILURE);
	}
#endif
	destinations = (zbx_send_destinations_t *)zbx_realloc(destinations,
			sizeof(zbx_send_destinations_t) * destinations_count);

	zbx_vector_ptr_create(&destinations[destinations_count - 1].addrs);

	zbx_addr_copy(&destinations[destinations_count - 1].addrs, addrs);

	return SUCCEED;
}

static void	zbx_fill_from_config_file(char **dst, char *src)
{
	/* helper function, only for TYPE_STRING configuration parameters */

	if (NULL != src)
	{
		if (NULL == *dst)
			*dst = zbx_strdup(*dst, src);

		zbx_free(src);
	}
}

static void	zbx_load_config(const char *config_file)
{
	char	*cfg_source_ip = NULL, *cfg_active_hosts = NULL, *cfg_hostname = NULL, *cfg_tls_connect = NULL,
		*cfg_tls_ca_file = NULL, *cfg_tls_crl_file = NULL, *cfg_tls_server_cert_issuer = NULL,
		*cfg_tls_server_cert_subject = NULL, *cfg_tls_cert_file = NULL, *cfg_tls_key_file = NULL,
		*cfg_tls_psk_file = NULL, *cfg_tls_psk_identity = NULL,
		*cfg_tls_cipher_cert13 = NULL, *cfg_tls_cipher_cert = NULL,
		*cfg_tls_cipher_psk13 = NULL, *cfg_tls_cipher_psk = NULL;

	struct cfg_line	cfg[] =
	{
		/* PARAMETER,			VAR,					TYPE,
			MANDATORY,	MIN,			MAX */
		{"SourceIP",			&cfg_source_ip,				TYPE_STRING,
			PARM_OPT,	0,			0},
		{"ServerActive",		&cfg_active_hosts,			TYPE_STRING_LIST,
			PARM_OPT,	0,			0},
		{"Hostname",			&cfg_hostname,				TYPE_STRING_LIST,
			PARM_OPT,	0,			0},
		{"TLSConnect",			&cfg_tls_connect,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSCAFile",			&cfg_tls_ca_file,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSCRLFile",			&cfg_tls_crl_file,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSServerCertIssuer",		&cfg_tls_server_cert_issuer,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSServerCertSubject",	&cfg_tls_server_cert_subject,		TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSCertFile",			&cfg_tls_cert_file,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSKeyFile",			&cfg_tls_key_file,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSPSKIdentity",		&cfg_tls_psk_identity,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSPSKFile",			&cfg_tls_psk_file,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSCipherCert13",		&cfg_tls_cipher_cert13,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSCipherCert",		&cfg_tls_cipher_cert,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSCipherPSK13",		&cfg_tls_cipher_psk13,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"TLSCipherPSK",		&cfg_tls_cipher_psk,			TYPE_STRING,
			PARM_OPT,	0,			0},
		{"ListenBacklog",		&CONFIG_TCP_MAX_BACKLOG_SIZE,		TYPE_INT,
			PARM_OPT,	0,			INT_MAX},
		{NULL}
	};

	/* do not complain about unknown parameters in agent configuration file */
	parse_cfg_file(config_file, cfg, ZBX_CFG_FILE_REQUIRED, ZBX_CFG_NOT_STRICT, ZBX_CFG_EXIT_FAILURE);

	/* get first hostname only */
	if (NULL != cfg_hostname)
	{
		if (NULL == ZABBIX_HOSTNAME)
		{
			char	*p;

			ZABBIX_HOSTNAME = NULL != (p = strchr(cfg_hostname, ',')) ?
					zbx_dsprintf(NULL, "%.*s", (int)(p - cfg_hostname), cfg_hostname) :
					zbx_strdup(NULL, cfg_hostname);
		}

		zbx_free(cfg_hostname);
	}

	zbx_fill_from_config_file(&CONFIG_SOURCE_IP, cfg_source_ip);

	if (NULL == ZABBIX_SERVER)
	{
		if (NULL != cfg_active_hosts && '\0' != *cfg_active_hosts)
		{
			char	*error;

			if (FAIL == zbx_set_data_destination_hosts(cfg_active_hosts,
					(unsigned short)ZBX_DEFAULT_SERVER_PORT, "ServerActive",
					sender_add_serveractive_host_cb, NULL, NULL, &error))
			{
				zbx_error("%s", error);
				exit(EXIT_FAILURE);
			}
		}
	}
	zbx_free(cfg_active_hosts);

	zbx_fill_from_config_file(&CONFIG_TLS_CONNECT, cfg_tls_connect);
	zbx_fill_from_config_file(&CONFIG_TLS_CA_FILE, cfg_tls_ca_file);
	zbx_fill_from_config_file(&CONFIG_TLS_CRL_FILE, cfg_tls_crl_file);
	zbx_fill_from_config_file(&CONFIG_TLS_SERVER_CERT_ISSUER, cfg_tls_server_cert_issuer);
	zbx_fill_from_config_file(&CONFIG_TLS_SERVER_CERT_SUBJECT, cfg_tls_server_cert_subject);
	zbx_fill_from_config_file(&CONFIG_TLS_CERT_FILE, cfg_tls_cert_file);
	zbx_fill_from_config_file(&CONFIG_TLS_KEY_FILE, cfg_tls_key_file);
	zbx_fill_from_config_file(&CONFIG_TLS_PSK_IDENTITY, cfg_tls_psk_identity);
	zbx_fill_from_config_file(&CONFIG_TLS_PSK_FILE, cfg_tls_psk_file);

	zbx_fill_from_config_file(&CONFIG_TLS_CIPHER_CERT13, cfg_tls_cipher_cert13);
	zbx_fill_from_config_file(&CONFIG_TLS_CIPHER_CERT, cfg_tls_cipher_cert);
	zbx_fill_from_config_file(&CONFIG_TLS_CIPHER_PSK13, cfg_tls_cipher_psk13);
	zbx_fill_from_config_file(&CONFIG_TLS_CIPHER_PSK, cfg_tls_cipher_psk);
}

static void	parse_commandline(int argc, char **argv)
{
/* Minimum and maximum port numbers Zabbix sender can connect to. */
/* Do not forget to modify port number validation below if MAX_ZABBIX_PORT is ever changed. */
#define MIN_ZABBIX_PORT 1u
#define MAX_ZABBIX_PORT 65535u

	int		i, fatal = 0;
	char		ch;
	unsigned int	opt_mask = 0;
	unsigned short	opt_count[256] = {0};

	/* parse the command-line */
	while ((char)EOF != (ch = (char)zbx_getopt_long(argc, argv, shortopts, longopts, NULL)))
	{
		opt_count[(unsigned char)ch]++;

		switch (ch)
		{
			case 'c':
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
				if (NULL == CONFIG_SOURCE_IP)
					CONFIG_SOURCE_IP = zbx_strdup(CONFIG_SOURCE_IP, zbx_optarg);
				break;
			case 'z':
				if (NULL == ZABBIX_SERVER)
					ZABBIX_SERVER = zbx_strdup(ZABBIX_SERVER, zbx_optarg);
				break;
			case 'p':
				if (NULL == ZABBIX_SERVER_PORT)
					ZABBIX_SERVER_PORT = zbx_strdup(ZABBIX_SERVER_PORT, zbx_optarg);
				break;
			case 's':
				if (NULL == ZABBIX_HOSTNAME)
					ZABBIX_HOSTNAME = zbx_strdup(ZABBIX_HOSTNAME, zbx_optarg);
				break;
			case 'k':
				if (NULL == ZABBIX_KEY)
					ZABBIX_KEY = zbx_strdup(ZABBIX_KEY, zbx_optarg);
				break;
			case 'o':
				if (NULL == ZABBIX_KEY_VALUE)
					ZABBIX_KEY_VALUE = zbx_strdup(ZABBIX_KEY_VALUE, zbx_optarg);
				break;
			case 'i':
				if (NULL == INPUT_FILE)
					INPUT_FILE = zbx_strdup(INPUT_FILE, zbx_optarg);
				break;
			case 'T':
				WITH_TIMESTAMPS = 1;
				break;
			case 'N':
				WITH_NS = 1;
				break;
			case 'r':
				REAL_TIME = 1;
				break;
			case 't':
				if (FAIL == is_uint_n_range(zbx_optarg, ZBX_MAX_UINT64_LEN, &CONFIG_SENDER_TIMEOUT,
						sizeof(CONFIG_SENDER_TIMEOUT), CONFIG_SENDER_TIMEOUT_MIN,
						CONFIG_SENDER_TIMEOUT_MAX))
				{
					zbx_error("Invalid timeout, valid range %d:%d seconds",
							CONFIG_SENDER_TIMEOUT_MIN, CONFIG_SENDER_TIMEOUT_MAX);
					exit(EXIT_FAILURE);
				}
				break;
			case 'v':
				if (LOG_LEVEL_WARNING > CONFIG_LOG_LEVEL)
					CONFIG_LOG_LEVEL = LOG_LEVEL_WARNING;
				else if (LOG_LEVEL_DEBUG > CONFIG_LOG_LEVEL)
					CONFIG_LOG_LEVEL = LOG_LEVEL_DEBUG;
				break;
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
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
			case 'A':
#if defined(HAVE_OPENSSL)
				CONFIG_TLS_CIPHER_CMD13 = zbx_strdup(CONFIG_TLS_CIPHER_CMD13, zbx_optarg);
#elif defined(HAVE_GNUTLS)
				zbx_error("parameter \"--tls-cipher13\" can be used with OpenSSL 1.1.1 or newer."
						" Zabbix sender was compiled with GnuTLS");
				exit(EXIT_FAILURE);
#endif
				break;
			case 'B':
				CONFIG_TLS_CIPHER_CMD = zbx_strdup(CONFIG_TLS_CIPHER_CMD, zbx_optarg);
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
				zbx_error("TLS parameters cannot be used: Zabbix sender was compiled without TLS"
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

	if (NULL != ZABBIX_SERVER)
	{
		unsigned short	port;
		char		*error;

		if (NULL != ZABBIX_SERVER_PORT)
		{
			if (SUCCEED != is_ushort(ZABBIX_SERVER_PORT, &port) || MIN_ZABBIX_PORT > port)
			{
				zbx_error("option \"-p\" used with invalid port number \"%s\", valid port numbers are"
						" %d-%d", ZABBIX_SERVER_PORT, (int)MIN_ZABBIX_PORT,
						(int)MAX_ZABBIX_PORT);
				exit(EXIT_FAILURE);
			}
		}
		else
			port = (unsigned short)ZBX_DEFAULT_SERVER_PORT;

		if (FAIL == zbx_set_data_destination_hosts(ZABBIX_SERVER, port, "-z",
				sender_add_serveractive_host_cb, NULL, NULL, &error))
		{
			zbx_error("%s", error);
			exit(EXIT_FAILURE);
		}
	}

	/* every option may be specified only once */

	for (i = 0; NULL != longopts[i].name; i++)
	{
		ch = longopts[i].val;

		if ('v' == ch && 2 < opt_count[(unsigned char)ch])	/* '-v' or '-vv' can be specified */
		{
			zbx_error("option \"-v\" or \"--verbose\" specified more than 2 times");

			fatal = 1;
			continue;
		}

		if ('v' != ch && 1 < opt_count[(unsigned char)ch])
		{
			if (NULL == strchr(shortopts, ch))
				zbx_error("option \"--%s\" specified multiple times", longopts[i].name);
			else
				zbx_error("option \"-%c\" or \"--%s\" specified multiple times", ch, longopts[i].name);

			fatal = 1;
		}
	}

	if (1 == fatal)
		exit(EXIT_FAILURE);

	/* check for mutually exclusive options    */

	/* Allowed option combinations.                                */
	/* Option 'v' is always optional.                              */
	/*   c  z  s  k  o  i  N  T  r  p  I opt_mask comment          */
	/* --------------------------------- -------- -------          */
	/*   -  z  -  -  -  i  -  -  -  -  -  0x220   !c i             */
	/*   -  z  -  -  -  i  -  -  -  -  I  0x221                    */
	/*   -  z  -  -  -  i  -  -  -  p  -  0x222                    */
	/*   -  z  -  -  -  i  -  -  -  p  I  0x223                    */
	/*   -  z  -  -  -  i  -  -  r  -  -  0x224                    */
	/*   -  z  -  -  -  i  -  -  r  -  I  0x225                    */
	/*   -  z  -  -  -  i  -  -  r  p  -  0x226                    */
	/*   -  z  -  -  -  i  -  -  r  p  I  0x227                    */
	/*   -  z  -  -  -  i  -  T  -  -  -  0x228                    */
	/*   -  z  -  -  -  i  -  T  -  -  I  0x229                    */
	/*   -  z  -  -  -  i  -  T  -  p  -  0x22a                    */
	/*   -  z  -  -  -  i  -  T  -  p  I  0x22b                    */
	/*   -  z  -  -  -  i  -  T  r  -  -  0x22c                    */
	/*   -  z  -  -  -  i  -  T  r  -  I  0x22d                    */
	/*   -  z  -  -  -  i  -  T  r  p  -  0x22e                    */
	/*   -  z  -  -  -  i  -  T  r  p  I  0x22f                    */
	/*   -  z  -  -  -  i  N  T  -  -  -  0x238                    */
	/*   -  z  -  -  -  i  N  T  -  -  I  0x239                    */
	/*   -  z  -  -  -  i  N  T  -  p  -  0x23a                    */
	/*   -  z  -  -  -  i  N  T  -  p  I  0x23b                    */
	/*   -  z  -  -  -  i  N  T  r  -  -  0x23c                    */
	/*   -  z  -  -  -  i  N  T  r  -  I  0x23d                    */
	/*   -  z  -  -  -  i  N  T  r  p  -  0x23e                    */
	/*   -  z  -  -  -  i  N  T  r  p  I  0x23f                    */
	/*   -  z  s  -  -  i  -  -  -  -  -  0x320                    */
	/*   -  z  s  -  -  i  -  -  -  -  I  0x321                    */
	/*   -  z  s  -  -  i  -  -  -  p  -  0x322                    */
	/*   -  z  s  -  -  i  -  -  -  p  I  0x323                    */
	/*   -  z  s  -  -  i  -  -  r  -  -  0x324                    */
	/*   -  z  s  -  -  i  -  -  r  -  I  0x325                    */
	/*   -  z  s  -  -  i  -  -  r  p  -  0x326                    */
	/*   -  z  s  -  -  i  -  -  r  p  I  0x327                    */
	/*   -  z  s  -  -  i  -  T  -  -  -  0x328                    */
	/*   -  z  s  -  -  i  -  T  -  -  I  0x329                    */
	/*   -  z  s  -  -  i  -  T  -  p  -  0x32a                    */
	/*   -  z  s  -  -  i  -  T  -  p  I  0x32b                    */
	/*   -  z  s  -  -  i  -  T  r  -  -  0x32c                    */
	/*   -  z  s  -  -  i  -  T  r  -  I  0x32d                    */
	/*   -  z  s  -  -  i  -  T  r  p  -  0x32e                    */
	/*   -  z  s  -  -  i  -  T  r  p  I  0x32f                    */
	/*   -  z  s  -  -  i  N  T  -  -  -  0x338                    */
	/*   -  z  s  -  -  i  N  T  -  -  I  0x339                    */
	/*   -  z  s  -  -  i  N  T  -  p  -  0x33a                    */
	/*   -  z  s  -  -  i  N  T  -  p  I  0x33b                    */
	/*   -  z  s  -  -  i  N  T  r  -  -  0x33c                    */
	/*   -  z  s  -  -  i  N  T  r  -  I  0x33d                    */
	/*   -  z  s  -  -  i  N  T  r  p  -  0x33e                    */
	/*   -  z  s  -  -  i  N  T  r  p  I  0x33f                    */
	/*                                                             */
	/*   -  z  s  k  o  -  -  -  -  -  -  0x3c0   !c !i            */
	/*   -  z  s  k  o  -  -  -  -  -  I  0x3c1                    */
	/*   -  z  s  k  o  -  -  -  -  p  -  0x3c2                    */
	/*   -  z  s  k  o  -  -  -  -  p  I  0x3c3                    */
	/*                                                             */
	/*   c  -  -  -  -  i  -  -  -  -  -  0x420   c i              */
	/*   c  -  -  -  -  i  -  -  -  -  I  0x421                    */
	/*   c  -  -  -  -  i  -  -  -  p  -  0x422                    */
	/*   c  -  -  -  -  i  -  -  -  p  I  0x423                    */
	/*   c  -  -  -  -  i  -  -  r  -  -  0x424                    */
	/*   c  -  -  -  -  i  -  -  r  -  I  0x425                    */
	/*   c  -  -  -  -  i  -  -  r  p  -  0x426                    */
	/*   c  -  -  -  -  i  -  -  r  p  I  0x427                    */
	/*   c  -  -  -  -  i  -  T  -  -  -  0x428                    */
	/*   c  -  -  -  -  i  -  T  -  -  I  0x429                    */
	/*   c  -  -  -  -  i  -  T  -  p  -  0x42a                    */
	/*   c  -  -  -  -  i  -  T  -  p  I  0x42b                    */
	/*   c  -  -  -  -  i  -  T  r  -  -  0x42c                    */
	/*   c  -  -  -  -  i  -  T  r  -  I  0x42d                    */
	/*   c  -  -  -  -  i  -  T  r  p  -  0x42e                    */
	/*   c  -  -  -  -  i  -  T  r  p  I  0x42f                    */
	/*   c  -  -  -  -  i  N  T  -  -  -  0x438                    */
	/*   c  -  -  -  -  i  N  T  -  -  I  0x439                    */
	/*   c  -  -  -  -  i  N  T  -  p  -  0x43a                    */
	/*   c  -  -  -  -  i  N  T  -  p  I  0x43b                    */
	/*   c  -  -  -  -  i  N  T  r  -  -  0x43c                    */
	/*   c  -  -  -  -  i  N  T  r  -  I  0x43d                    */
	/*   c  -  -  -  -  i  N  T  r  p  -  0x43e                    */
	/*   c  -  -  -  -  i  N  T  r  p  I  0x43f                    */
	/*                                                             */
	/*   c  -  -  k  o  -  -  -  -  -  -  0x4c0   c !i             */
	/*   c  -  -  k  o  -  -  -  -  -  I  0x4c1                    */
	/*   c  -  -  k  o  -  -  -  -  p  -  0x4c2                    */
	/*   c  -  -  k  o  -  -  -  -  p  I  0x4c3                    */
	/*   c  -  s  k  o  -  -  -  -  -  -  0x5c0                    */
	/*   c  -  s  k  o  -  -  -  -  -  I  0x5c1                    */
	/*   c  -  s  k  o  -  -  -  -  p  -  0x5c2                    */
	/*   c  -  s  k  o  -  -  -  -  p  I  0x5c3                    */
	/*                                                             */
	/*   c  -  s  -  -  i  -  -  -  -  -  0x520   c i (continues)  */
	/*   c  -  s  -  -  i  -  -  -  -  I  0x521                    */
	/*   c  -  s  -  -  i  -  -  -  p  -  0x522                    */
	/*   c  -  s  -  -  i  -  -  -  p  I  0x523                    */
	/*   c  -  s  -  -  i  -  -  r  -  -  0x524                    */
	/*   c  -  s  -  -  i  -  -  r  -  I  0x525                    */
	/*   c  -  s  -  -  i  -  -  r  p  -  0x526                    */
	/*   c  -  s  -  -  i  -  -  r  p  I  0x527                    */
	/*   c  -  s  -  -  i  -  T  -  -  -  0x528                    */
	/*   c  -  s  -  -  i  -  T  -  -  I  0x529                    */
	/*   c  -  s  -  -  i  -  T  -  p  -  0x52a                    */
	/*   c  -  s  -  -  i  -  T  -  p  I  0x52b                    */
	/*   c  -  s  -  -  i  -  T  r  -  -  0x52c                    */
	/*   c  -  s  -  -  i  -  T  r  -  I  0x52d                    */
	/*   c  -  s  -  -  i  -  T  r  p  -  0x52e                    */
	/*   c  -  s  -  -  i  -  T  r  p  I  0x52f                    */
	/*   c  -  s  -  -  i  N  T  -  -  -  0x538                    */
	/*   c  -  s  -  -  i  N  T  -  -  I  0x539                    */
	/*   c  -  s  -  -  i  N  T  -  p  -  0x53a                    */
	/*   c  -  s  -  -  i  N  T  -  p  I  0x53b                    */
	/*   c  -  s  -  -  i  N  T  r  -  -  0x53c                    */
	/*   c  -  s  -  -  i  N  T  r  -  I  0x53d                    */
	/*   c  -  s  -  -  i  N  T  r  p  -  0x53e                    */
	/*   c  -  s  -  -  i  N  T  r  p  I  0x53f                    */
	/*   c  z  -  -  -  i  -  -  -  -  -  0x620                    */
	/*   c  z  -  -  -  i  -  -  -  -  I  0x621                    */
	/*   c  z  -  -  -  i  -  -  -  p  -  0x622                    */
	/*   c  z  -  -  -  i  -  -  -  p  I  0x623                    */
	/*   c  z  -  -  -  i  -  -  r  -  -  0x624                    */
	/*   c  z  -  -  -  i  -  -  r  -  I  0x625                    */
	/*   c  z  -  -  -  i  -  -  r  p  -  0x626                    */
	/*   c  z  -  -  -  i  -  -  r  p  I  0x627                    */
	/*   c  z  -  -  -  i  -  T  -  -  -  0x628                    */
	/*   c  z  -  -  -  i  -  T  -  -  I  0x629                    */
	/*   c  z  -  -  -  i  -  T  -  p  -  0x62a                    */
	/*   c  z  -  -  -  i  -  T  -  p  I  0x62b                    */
	/*   c  z  -  -  -  i  -  T  r  -  -  0x62c                    */
	/*   c  z  -  -  -  i  -  T  r  -  I  0x62d                    */
	/*   c  z  -  -  -  i  -  T  r  p  -  0x62e                    */
	/*   c  z  -  -  -  i  -  T  r  p  I  0x62f                    */
	/*   c  z  -  -  -  i  N  T  -  -  -  0x638                    */
	/*   c  z  -  -  -  i  N  T  -  -  I  0x639                    */
	/*   c  z  -  -  -  i  N  T  -  p  -  0x63a                    */
	/*   c  z  -  -  -  i  N  T  -  p  I  0x63b                    */
	/*   c  z  -  -  -  i  N  T  r  -  -  0x63c                    */
	/*   c  z  -  -  -  i  N  T  r  -  I  0x63d                    */
	/*   c  z  -  -  -  i  N  T  r  p  -  0x63e                    */
	/*   c  z  -  -  -  i  N  T  r  p  I  0x63f                    */
	/*   c  z  s  -  -  i  -  -  -  -  -  0x720                    */
	/*   c  z  s  -  -  i  -  -  -  -  I  0x721                    */
	/*   c  z  s  -  -  i  -  -  -  p  -  0x722                    */
	/*   c  z  s  -  -  i  -  -  -  p  I  0x723                    */
	/*   c  z  s  -  -  i  -  -  r  -  -  0x724                    */
	/*   c  z  s  -  -  i  -  -  r  -  I  0x725                    */
	/*   c  z  s  -  -  i  -  -  r  p  -  0x726                    */
	/*   c  z  s  -  -  i  -  -  r  p  I  0x727                    */
	/*   c  z  s  -  -  i  -  T  -  -  -  0x728                    */
	/*   c  z  s  -  -  i  -  T  -  -  I  0x729                    */
	/*   c  z  s  -  -  i  -  T  -  p  -  0x72a                    */
	/*   c  z  s  -  -  i  -  T  -  p  I  0x72b                    */
	/*   c  z  s  -  -  i  -  T  r  -  -  0x72c                    */
	/*   c  z  s  -  -  i  -  T  r  -  I  0x72d                    */
	/*   c  z  s  -  -  i  -  T  r  p  -  0x72e                    */
	/*   c  z  s  -  -  i  -  T  r  p  I  0x72f                    */
	/*   c  z  s  -  -  i  N  T  -  -  -  0x738                    */
	/*   c  z  s  -  -  i  N  T  -  -  I  0x739                    */
	/*   c  z  s  -  -  i  N  T  -  p  -  0x73a                    */
	/*   c  z  s  -  -  i  N  T  -  p  I  0x73b                    */
	/*   c  z  s  -  -  i  N  T  r  -  -  0x73c                    */
	/*   c  z  s  -  -  i  N  T  r  -  I  0x73d                    */
	/*   c  z  s  -  -  i  N  T  r  p  -  0x73e                    */
	/*   c  z  s  -  -  i  N  T  r  p  I  0x73f                    */
	/*                                                             */
	/*   c  z  -  k  o  -  -  -  -  -  -  0x6c0   c !i (continues) */
	/*   c  z  -  k  o  -  -  -  -  -  I  0x6c1                    */
	/*   c  z  -  k  o  -  -  -  -  p  -  0x6c2                    */
	/*   c  z  -  k  o  -  -  -  -  p  I  0x6c3                    */
	/*   c  z  s  k  o  -  -  -  -  -  -  0x7c0                    */
	/*   c  z  s  k  o  -  -  -  -  -  I  0x7c1                    */
	/*   c  z  s  k  o  -  -  -  -  p  -  0x7c2                    */
	/*   c  z  s  k  o  -  -  -  -  p  I  0x7c3                    */

	if (0 == opt_count['c'] + opt_count['z'])
	{
		zbx_error("either '-c' or '-z' option must be specified");
		usage();
		printf("Try '%s --help' for more information.\n", progname);
		exit(EXIT_FAILURE);
	}

	if (0 < opt_count['c'])
		opt_mask |= 0x400;
	if (0 < opt_count['z'])
		opt_mask |= 0x200;
	if (0 < opt_count['s'])
		opt_mask |= 0x100;
	if (0 < opt_count['k'])
		opt_mask |= 0x80;
	if (0 < opt_count['o'])
		opt_mask |= 0x40;
	if (0 < opt_count['i'])
		opt_mask |= 0x20;
	if (0 < opt_count['N'])
		opt_mask |= 0x10;
	if (0 < opt_count['T'])
		opt_mask |= 0x08;
	if (0 < opt_count['r'])
		opt_mask |= 0x04;
	if (0 < opt_count['p'])
		opt_mask |= 0x02;
	if (0 < opt_count['I'])
		opt_mask |= 0x01;

	if (
			(0 == opt_count['c'] && 1 == opt_count['i'] &&	/* !c i */
					!((0x220 <= opt_mask && opt_mask <= 0x22f) ||
					(0x238 <= opt_mask && opt_mask <= 0x23f) ||
					(0x320 <= opt_mask && opt_mask <= 0x32f) ||
					(0x338 <= opt_mask && opt_mask <= 0x33f))) ||
			(0 == opt_count['c'] && 0 == opt_count['i'] &&	/* !c !i */
					!(0x3c0 <= opt_mask && opt_mask <= 0x3c3)) ||
			(1 == opt_count['c'] && 1 == opt_count['i'] &&	/* c i */
					!((0x420 <= opt_mask && opt_mask <= 0x42f) ||
					(0x438 <= opt_mask && opt_mask <= 0x43f) ||
					(0x620 <= opt_mask && opt_mask <= 0x62f) ||
					(0x638 <= opt_mask && opt_mask <= 0x63f) ||
					(0x520 <= opt_mask && opt_mask <= 0x52f) ||
					(0x538 <= opt_mask && opt_mask <= 0x53f) ||
					(0x720 <= opt_mask && opt_mask <= 0x72f) ||
					(0x738 <= opt_mask && opt_mask <= 0x73f))) ||
			(1 == opt_count['c'] && 0 == opt_count['i'] &&	/* c !i */
					!((0x4c0 <= opt_mask && opt_mask <= 0x4c3) ||
					(0x5c0 <= opt_mask && opt_mask <= 0x5c3) ||
					(0x6c0 <= opt_mask && opt_mask <= 0x6c3) ||
					(0x7c0 <= opt_mask && opt_mask <= 0x7c3))))
	{
		zbx_error("too few or mutually exclusive options used");
		usage();
		exit(EXIT_FAILURE);
	}

	/* Parameters which are not option values are invalid. The check relies on zbx_getopt_internal() which */
	/* always permutes command line arguments regardless of POSIXLY_CORRECT environment variable. */
	if (argc > zbx_optind)
	{
		for (i = zbx_optind; i < argc; i++)
			zbx_error("invalid parameter \"%s\"", argv[i]);

		exit(EXIT_FAILURE);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: reads a line from file                                            *
 *                                                                            *
 * Parameters: buffer       - [IN/OUT] the output buffer                      *
 *             buffer_alloc - [IN/OUT] the buffer size                        *
 *             fp           - [IN] the file to read                           *
 *                                                                            *
 * Return value: Pointer to the line or NULL.                                 *
 *                                                                            *
 * Comments: This is a fgets() function wrapper with dynamically reallocated  *
 *           buffer.                                                          *
 *                                                                            *
 ******************************************************************************/
static char	*zbx_fgets_alloc(char **buffer, size_t *buffer_alloc, FILE *fp)
{
	char	tmp[MAX_BUFFER_LEN];
	size_t	buffer_offset = 0, len;

	do
	{
		if (NULL == fgets(tmp, sizeof(tmp), fp))
			return (0 != buffer_offset ? *buffer : NULL);

		len = strlen(tmp);

		if (*buffer_alloc - buffer_offset < len + 1)
		{
			*buffer_alloc = (buffer_offset + len + 1) * 3 / 2;
			*buffer = (char *)zbx_realloc(*buffer, *buffer_alloc);
		}

		memcpy(*buffer + buffer_offset, tmp, len);
		buffer_offset += len;
		(*buffer)[buffer_offset] = '\0';
	}
	while (MAX_BUFFER_LEN - 1 == len && '\n' != tmp[len - 1]);

	return *buffer;
}

/* sending a huge amount of values in a single connection is likely to */
/* take long and hit timeout, so we limit values to 250 per connection */
#define VALUES_MAX	250

int	main(int argc, char **argv)
{
	char			*error = NULL;
	int			total_count = 0, succeed_count = 0, ret = FAIL, timestamp, ns;
	ZBX_THREAD_SENDVAL_ARGS	*sendval_args = NULL;

	progname = get_program_name(argv[0]);

	parse_commandline(argc, argv);

	if (NULL != CONFIG_FILE)
		zbx_load_config(CONFIG_FILE);
#ifndef _WINDOWS
	if (SUCCEED != zbx_locks_create(&error))
	{
		zbx_error("cannot create locks: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}
#endif
	if (SUCCEED != zabbix_open_log(LOG_TYPE_UNDEFINED, CONFIG_LOG_LEVEL, NULL, &error))
	{
		zbx_error("cannot open log: %s", error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}
#if defined(_WINDOWS)
	if (SUCCEED != zbx_socket_start(&error))
	{
		zbx_error(error);
		zbx_free(error);
		exit(EXIT_FAILURE);
	}
#endif
#if !defined(_WINDOWS) && (defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL))
	if (SUCCEED != zbx_coredump_disable())
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot disable core dump, exiting...");
		goto exit;
	}
#endif
	if (0 == destinations_count)
	{
		zabbix_log(LOG_LEVEL_CRIT, "'ServerActive' parameter required");
		goto exit;
	}
#if !defined(_WINDOWS)
	signal(SIGINT, main_signal_handler);
	signal(SIGQUIT, main_signal_handler);
	signal(SIGTERM, main_signal_handler);
	signal(SIGHUP, main_signal_handler);
	signal(SIGALRM, main_signal_handler);
	signal(SIGPIPE, main_signal_handler);
#endif
	if (NULL != CONFIG_TLS_CONNECT || NULL != CONFIG_TLS_CA_FILE || NULL != CONFIG_TLS_CRL_FILE ||
			NULL != CONFIG_TLS_SERVER_CERT_ISSUER || NULL != CONFIG_TLS_SERVER_CERT_SUBJECT ||
			NULL != CONFIG_TLS_CERT_FILE || NULL != CONFIG_TLS_KEY_FILE ||
			NULL != CONFIG_TLS_PSK_IDENTITY || NULL != CONFIG_TLS_PSK_FILE ||
			NULL != CONFIG_TLS_CIPHER_CERT13 || NULL != CONFIG_TLS_CIPHER_CERT ||
			NULL != CONFIG_TLS_CIPHER_PSK13 || NULL != CONFIG_TLS_CIPHER_PSK ||
			NULL != CONFIG_TLS_CIPHER_CMD13 || NULL != CONFIG_TLS_CIPHER_CMD)
	{
#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
		zbx_tls_validate_config();

		if (ZBX_TCP_SEC_UNENCRYPTED != configured_tls_connect_mode)
		{
#if defined(_WINDOWS)
			zbx_tls_init_parent();
#endif
			zbx_tls_init_child();
		}
#else
		zabbix_log(LOG_LEVEL_CRIT, "TLS parameters cannot be used: Zabbix sender was compiled without TLS"
				" support");
		goto exit;
#endif
	}

	sendval_args = (ZBX_THREAD_SENDVAL_ARGS *)zbx_calloc(sendval_args, destinations_count,
			sizeof(ZBX_THREAD_SENDVAL_ARGS));

#if defined(_WINDOWS) && (defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL))
	if (ZBX_TCP_SEC_UNENCRYPTED != configured_tls_connect_mode)
	{
		/* prepare to pass necessary TLS data to 'send_value' thread (to be started soon) */
		zbx_tls_pass_vars(&sendval_args->tls_vars);
	}
#endif
	zbx_json_init(&sendval_args->json, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addstring(&sendval_args->json, ZBX_PROTO_TAG_REQUEST, ZBX_PROTO_VALUE_SENDER_DATA, ZBX_JSON_TYPE_STRING);
	zbx_json_addarray(&sendval_args->json, ZBX_PROTO_TAG_DATA);

	if (INPUT_FILE)
	{
		FILE	*in;
		char	*in_line = NULL, *key = NULL, *key_value = NULL;
		int	buffer_count = 0;
		size_t	key_alloc = 0, in_line_alloc = MAX_BUFFER_LEN;
		double	last_send = 0;

		if (0 == strcmp(INPUT_FILE, "-"))
		{
			in = stdin;
			if (1 == REAL_TIME)
			{
				/* set line buffering on stdin */
				setvbuf(stdin, (char *)NULL, _IOLBF, 1024);
			}
		}
		else if (NULL == (in = fopen(INPUT_FILE, "r")))
		{
			zabbix_log(LOG_LEVEL_CRIT, "cannot open [%s]: %s", INPUT_FILE, zbx_strerror(errno));
			goto free;
		}

		sendval_args->sync_timestamp = WITH_TIMESTAMPS;
		in_line = (char *)zbx_malloc(NULL, in_line_alloc);

		ret = SUCCEED;

		while (0 == sig_exiting && (SUCCEED == ret || SUCCEED_PARTIAL == ret) &&
				NULL != zbx_fgets_alloc(&in_line, &in_line_alloc, in))
		{
			char		hostname[MAX_STRING_LEN], clock[32];
			int		read_more = 0;
			size_t		key_value_alloc = 0;
			const char	*p;

			/* line format: <hostname> <key> [<timestamp>] [<ns>] <value> */

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
					zabbix_log(LOG_LEVEL_CRIT, "[line %d] '-' encountered as 'Hostname',"
							" but no default hostname was specified", total_count);
					ret = FAIL;
					break;
				}
				else
					zbx_strlcpy(hostname, ZABBIX_HOSTNAME, sizeof(hostname));
			}

			if (key_alloc != in_line_alloc)
			{
				key_alloc = in_line_alloc;
				key = (char *)zbx_realloc(key, key_alloc);
			}

			if ('\0' == *p || NULL == (p = get_string(p, key, key_alloc)) || '\0' == *key)
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

				if (1 == WITH_NS)
				{
					if ('\0' == *p || NULL == (p = get_string(p, clock, sizeof(clock))) ||
							'\0' == *clock)
					{
						zabbix_log(LOG_LEVEL_CRIT, "[line %d] 'Nanoseconds' required",
								total_count);
						ret = FAIL;
						break;
					}

					if (FAIL == is_uint_n_range(clock, sizeof(clock), &ns, sizeof(ns),
							0LL, 999999999LL))
					{
						zabbix_log(LOG_LEVEL_WARNING,
								"[line %d] invalid 'Nanoseconds' value detected",
								total_count);
						ret = FAIL;
						break;
					}
				}
			}

			if (key_value_alloc != in_line_alloc)
			{
				key_value_alloc = in_line_alloc;
				key_value = (char *)zbx_realloc(key_value, key_value_alloc);
			}

			if ('\0' != *p && '"' != *p)
			{
				zbx_strlcpy(key_value, p, key_value_alloc);
			}
			else if ('\0' == *p || NULL == (p = get_string(p, key_value, key_value_alloc)))
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

			zbx_json_addobject(&sendval_args->json, NULL);
			zbx_json_addstring(&sendval_args->json, ZBX_PROTO_TAG_HOST, hostname, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&sendval_args->json, ZBX_PROTO_TAG_KEY, key, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&sendval_args->json, ZBX_PROTO_TAG_VALUE, key_value, ZBX_JSON_TYPE_STRING);

			if (1 == WITH_TIMESTAMPS)
			{
				zbx_json_adduint64(&sendval_args->json, ZBX_PROTO_TAG_CLOCK, timestamp);

				if (1 == WITH_NS)
					zbx_json_adduint64(&sendval_args->json, ZBX_PROTO_TAG_NS, ns);
			}

			zbx_json_close(&sendval_args->json);

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
				zbx_json_close(&sendval_args->json);

				last_send = zbx_time();

				ret = perform_data_sending(sendval_args, ret);

				buffer_count = 0;
				zbx_json_clean(&sendval_args->json);
				zbx_json_addstring(&sendval_args->json, ZBX_PROTO_TAG_REQUEST,
						ZBX_PROTO_VALUE_SENDER_DATA, ZBX_JSON_TYPE_STRING);
				zbx_json_addarray(&sendval_args->json, ZBX_PROTO_TAG_DATA);
			}
		}

		if (FAIL != ret && 0 != buffer_count)
		{
			zbx_json_close(&sendval_args->json);
			ret = perform_data_sending(sendval_args, ret);
		}

		if (in != stdin)
			fclose(in);

		zbx_free(key);
		zbx_free(key_value);
		zbx_free(in_line);
	}
	else
	{
		sendval_args->sync_timestamp = 0;
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

			zbx_json_addobject(&sendval_args->json, NULL);
			zbx_json_addstring(&sendval_args->json, ZBX_PROTO_TAG_HOST, ZABBIX_HOSTNAME, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&sendval_args->json, ZBX_PROTO_TAG_KEY, ZABBIX_KEY, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&sendval_args->json, ZBX_PROTO_TAG_VALUE, ZABBIX_KEY_VALUE, ZBX_JSON_TYPE_STRING);
			zbx_json_close(&sendval_args->json);

			succeed_count++;

			ret = perform_data_sending(sendval_args, ret);
		}
		while (0); /* try block simulation */
	}
free:
	zbx_json_free(&sendval_args->json);
	zbx_free(sendval_args);
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

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	if (ZBX_TCP_SEC_UNENCRYPTED != configured_tls_connect_mode)
	{
		zbx_tls_free();
#if defined(_WINDOWS)
		zbx_tls_library_deinit();
#endif
	}
#endif
	zabbix_close_log();
#ifndef _WINDOWS
	zbx_locks_destroy();
#endif
#if defined(_WINDOWS)
	while (0 == WSACleanup())
		;
#endif
#if !defined(_WINDOWS) && defined(HAVE_PTHREAD_PROCESS_SHARED)
	zbx_locks_disable();
#endif

	if (FAIL == ret)
		ret = EXIT_FAILURE;

	return ret;
}
