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

#include "common.h"

#include "cfg.h"
#include "log.h"
#include "zbxgetopt.h"
#include "zbxembed.h"
#include "mutexs.h"

const char	*progname;
const char	title_message[] = "zabbix_js";
const char	syslog_app_name[] = "zabbix_js";
const char	*usage_message[] = {
	"-s script-file", "-p input-param", "[-l log-level]", "[-t timeout]", NULL,
	"-s script-file", "-i input-file", "[-l log-level]", "[-t timeout]", NULL,
	"-h", NULL,
	"-V", NULL,
	NULL	/* end of text */
};

unsigned char	program_type;

const char	*help_message[] = {
	"Execute script using Zabbix embedded scripting engine.",
	"",
	"General options:",
	"  -s,--script script-file      Specify the filename of script to execute. Specify - for",
	"                               standard input.",
	"  -i,--input input-file        Specify input parameter file name. Specify - for",
	"                               standard input.",
	"  -p,--param input-param       Specify input parameter",
	"  -l,--loglevel log-level      Specify log level",
	"  -t,--timeout timeout         Specify timeout in seconds",
	"  -h --help                    Display this help message",
	"  -V --version                 Display version number",
	"",
	"Example:",
	"  zabbix_js -s script-file.js -p example",
	NULL	/* end of text */
};


/* long options */
struct zbx_option	longopts[] =
{
	{"script",			1,	NULL,	's'},
	{"input",			1,	NULL,	'i'},
	{"param",			1,	NULL,	'p'},
	{"loglevel",			1,	NULL,	'l'},
	{"timeout",			1,	NULL,	't'},
	{"help",			0,	NULL,	'h'},
	{"version",			0,	NULL,	'V'},
	{NULL}
};

/* short options */
static char	shortopts[] = "s:i:p:hVl:t:";

/* end of COMMAND LINE OPTIONS */

unsigned int	configured_tls_connect_mode;
unsigned int	configured_tls_accept_modes;	/* not used in zabbix_get, just for linking */
									/* with tls.c */
char	*CONFIG_TLS_CONNECT		= NULL;
char	*CONFIG_TLS_ACCEPT		= NULL;	/* not used in zabbix_js, just for linking with tls.c */
char	*CONFIG_TLS_CA_FILE		= NULL;
char	*CONFIG_TLS_CRL_FILE		= NULL;
char	*CONFIG_TLS_SERVER_CERT_ISSUER	= NULL;
char	*CONFIG_TLS_SERVER_CERT_SUBJECT	= NULL;
char	*CONFIG_TLS_CERT_FILE		= NULL;
char	*CONFIG_TLS_KEY_FILE		= NULL;
char	*CONFIG_TLS_PSK_IDENTITY	= NULL;
char	*CONFIG_TLS_PSK_FILE		= NULL;

/* CONFIG_TLS_CIPHER_* are not used in zabbix_js, defined for linking with tls.c */
char	*CONFIG_TLS_CIPHER_CERT13	= NULL;
char	*CONFIG_TLS_CIPHER_CERT		= NULL;
char	*CONFIG_TLS_CIPHER_PSK13	= NULL;
char	*CONFIG_TLS_CIPHER_PSK		= NULL;
char	*CONFIG_TLS_CIPHER_ALL13	= NULL;
char	*CONFIG_TLS_CIPHER_ALL		= NULL;
char	*CONFIG_TLS_CIPHER_CMD13	= NULL;
char	*CONFIG_TLS_CIPHER_CMD		= NULL;

int	CONFIG_PASSIVE_FORKS		= 0;	/* not used in zabbix_js, just for linking with tls.c */
int	CONFIG_ACTIVE_FORKS		= 0;	/* not used in zabbix_js, just for linking with tls.c */


char	*CONFIG_SOURCE_IP 		= NULL;
char	*CONFIG_SSL_CA_LOCATION		= NULL;
char	*CONFIG_SSL_CERT_LOCATION	= NULL;
char	*CONFIG_SSL_KEY_LOCATION	= NULL;

static char	*read_file(const char *filename, char **error)
{
	char	buffer[4096];
	int	n, fd;
	char	*data = NULL;
	size_t	data_alloc = 0, data_offset = 0;

	if (0 != strcmp(filename, "-"))
	{
		if (-1 == (fd = open(filename, O_RDONLY)))
		{
			*error = zbx_strdup(NULL, zbx_strerror(errno));
			return NULL;
		}
	}
	else
		fd = STDIN_FILENO;

	while (0 != (n = read(fd, buffer, sizeof(buffer))))
	{
		if (-1 == n)
		{
			if (fd != STDIN_FILENO)
				close(fd);
			zbx_free(data);
			*error = zbx_strdup(NULL, zbx_strerror(errno));
			return NULL;
		}
		zbx_strncpy_alloc(&data, &data_alloc, &data_offset, buffer, n);
	}

	if (fd != STDIN_FILENO)
		close(fd);

	return data;
}

static char	*execute_script(const char *script, const char *param, int timeout, char **error)
{
	zbx_es_t	es;
	char		*code = NULL;
	int		size;
	char		*errmsg = NULL, *result = NULL;

	zbx_es_init(&es);
	if (FAIL == zbx_es_init_env(&es, &errmsg))
	{
		*error = zbx_dsprintf(NULL, "cannot initialize scripting environment: %s", errmsg);
		zbx_free(errmsg);
		return NULL;
	}

	if (0 != timeout)
		zbx_es_set_timeout(&es, timeout);

	if (FAIL == zbx_es_compile(&es, script, &code, &size, &errmsg))
	{
		*error = zbx_dsprintf(NULL, "cannot compile script: %s", errmsg);
		zbx_free(errmsg);
		goto out;
	}

	if (FAIL == zbx_es_execute(&es, script, code, size, param, &result, &errmsg))
	{
		*error = zbx_dsprintf(NULL, "cannot execute script: %s", errmsg);
		zbx_free(errmsg);
		goto out;
	}
out:
	if (FAIL == zbx_es_destroy_env(&es, &errmsg))
	{
		zbx_error("cannot destroy scripting environment: %s", errmsg);
		zbx_free(result);
	}

	zbx_free(code);
	zbx_free(errmsg);

	return result;
}

int	main(int argc, char **argv)
{
	int	ret = FAIL, loglevel = LOG_LEVEL_WARNING, timeout = 0;
	char	*script_file = NULL, *input_file = NULL, *param = NULL, ch, *script = NULL, *error = NULL,
		*result = NULL;

	progname = get_program_name(argv[0]);

	/* parse the command-line */
	while ((char)EOF != (ch = (char)zbx_getopt_long(argc, argv, shortopts, longopts, NULL)))
	{
		switch (ch)
		{
			case 's':
				if (NULL == script_file)
					script_file = zbx_strdup(NULL, zbx_optarg);
				break;
			case 'i':
				if (NULL == input_file)
					input_file = zbx_strdup(NULL, zbx_optarg);
				break;
			case 'p':
				if (NULL == param)
					param = zbx_strdup(NULL, zbx_optarg);
				break;
			case 'l':
				loglevel = atoi(zbx_optarg);
				break;
			case 't':
				timeout = atoi(zbx_optarg);
				break;
			case 'h':
				help();
				ret = SUCCEED;
				goto clean;
			case 'V':
				version();
				ret = SUCCEED;
				goto clean;
			default:
				usage();
				goto clean;
		}
	}

	if (SUCCEED != zbx_locks_create(&error))
	{
		zbx_error("cannot create locks: %s", error);
		goto clean;
	}

	if (SUCCEED != zabbix_open_log(LOG_TYPE_UNDEFINED, loglevel, NULL, &error))
	{
		zbx_error("cannot open log: %s", error);
		goto clean;
	}


	if (NULL == script_file || (NULL == input_file && NULL == param))
	{
		usage();
		goto close;
	}

	if (NULL != input_file && NULL != param)
	{
		zbx_error("input and script options are mutually exclusive");
		goto close;
	}

	if (0 == strcmp(script_file, "-") && NULL != input_file && 0 == strcmp(input_file, "-"))
	{
		zbx_error("cannot read script and input parameters from standard input at the same time");
		goto close;
	}

	if (NULL == (script = read_file(script_file, &error)))
	{
		if (NULL != error)
			zbx_error("cannot read script file: %s", error);
		else
			zbx_error("cannot use empty script file");

		goto close;
	}

	if (NULL != input_file)
	{
		if (NULL == (param = read_file(input_file, &error)))
		{
			if (NULL != error)
				zbx_error("cannot read input file: %s", error);
			else
				zbx_error("cannot use empty input file");

			goto close;
		}
	}

	if (NULL == (result = execute_script(script, param, timeout, &error)))
	{
		zbx_error("error executing script:\n%s", error);
		goto close;
	}
	ret = SUCCEED;
	printf("\n%s\n", result);
close:
	zabbix_close_log();
clean:
	zbx_free(result);
	zbx_free(error);
	zbx_free(script);
	zbx_free(script_file);
	zbx_free(input_file);
	zbx_free(param);

	return SUCCEED == ret ? EXIT_SUCCESS : EXIT_FAILURE;
}
