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

#include "zbxicmpping.h"

#ifdef HAVE_IPV6
#	include "zbxcomms.h"
#endif

#include "zbxstr.h"
#include "zbxip.h"
#include "zbxfile.h"

#include <signal.h>

static const zbx_config_icmpping_t	*config_icmpping;

/* old official fping (2.4b2_to_ipv6) did not support source IP address */
/* old patched versions (2.4b2_to_ipv6) provided either -I or -S options */
/* since fping 3.x it provides -I option for binding to an interface and -S option for source IP address */

static ZBX_THREAD_LOCAL unsigned char	source_ip_checked;
static ZBX_THREAD_LOCAL const char	*source_ip_option;
#ifdef HAVE_IPV6
static ZBX_THREAD_LOCAL unsigned char	source_ip6_checked;
static ZBX_THREAD_LOCAL const char	*source_ip6_option;
#endif

#define FPING_UNINITIALIZED_VALUE	-2
static ZBX_THREAD_LOCAL int		packet_interval;
#ifdef HAVE_IPV6
static ZBX_THREAD_LOCAL int		packet_interval6;
static ZBX_THREAD_LOCAL int		fping_ipv6_supported;
#endif

static ZBX_THREAD_LOCAL time_t		fping_check_reset_at;	/* time of the last fping options expiration */
static ZBX_THREAD_LOCAL char		tmpfile_uniq[255] = {'\0'};

typedef struct
{
	zbx_fping_host_t	*hosts;
	int			hosts_count;
	int			requests_count;
	unsigned char		allow_redirect;
	int			rdns;
#ifdef HAVE_IPV6
#	define FPING_EXISTS	0x1
#	define FPING6_EXISTS	0x2
	char			fping_existence;
#endif
}
zbx_fping_args;

typedef struct
{
	FILE	*input_pipe;
	char	*linebuf;
	size_t	linebuf_size;
}
zbx_fping_resp;

static void	get_source_ip_option(const char *fping, const char **option, unsigned char *checked)
{
	FILE	*f;
	char	*p, tmp[MAX_STRING_LEN];

	zbx_snprintf(tmp, sizeof(tmp), "%s -h 2>&1", fping);

	zabbix_log(LOG_LEVEL_DEBUG, "executing %s", tmp);

	if (NULL == (f = popen(tmp, "r")))
		return;

	while (NULL != zbx_fgets(tmp, sizeof(tmp), f))
	{
		for (p = tmp; isspace(*p); p++)
			;

		if ('-' == p[0] && 'I' == p[1] && (isspace(p[2]) || ',' == p[2]))
		{
			*option = "-I";
			continue;
		}

		if ('-' == p[0] && 'S' == p[1] && (isspace(p[2]) || ',' == p[2]))
		{
			*option = "-S";
			break;
		}
	}

	pclose(f);

	*checked = 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute external program and return stdout and stderr values      *
 *                                                                            *
 * Parameters: fping         - [IN] location of fping program                 *
 *             out           - [OUT] stdout and stderr values                 *
 *             error         - [OUT] error string if function fails           *
 *             max_error_len - [IN] length of error buffer                    *
 *                                                                            *
 * Return value: SUCCEED if processed successfully or FAIL otherwise          *
 *                                                                            *
 ******************************************************************************/
static int	get_fping_out(const char *fping, const char *address, char **out, char *error, size_t max_error_len)
{
	FILE		*f;
	size_t		buf_size = 0, offset = 0, len;
	ssize_t		n;
	char		tmp[MAX_STRING_LEN], *buffer = NULL;
	int		ret = FAIL, fd;
	sigset_t	mask, orig_mask;
	char		filename[MAX_STRING_LEN];
	mode_t		mode;

	if (FAIL == zbx_validate_hostname(address) && FAIL == zbx_is_supported_ip(address))
	{
		zbx_strlcpy(error, "Invalid host name or IP address", max_error_len);
		return FAIL;
	}

	zbx_snprintf(filename, sizeof(filename), "%s/%s_XXXXXX", config_icmpping->get_tmpdir(),
			config_icmpping->get_progname());

	mode = umask(077);
	fd = mkstemp(filename);
	umask(mode);

	if (-1 == fd)
	{
		zbx_snprintf(error, max_error_len, "Cannot create temporary file \"%s\": %s", filename,
				zbx_strerror(errno));

		return FAIL;
	}

	sigemptyset(&mask);
	sigaddset(&mask, SIGINT);
	sigaddset(&mask, SIGQUIT);

	if (0 > zbx_sigmask(SIG_BLOCK, &mask, &orig_mask))
		zbx_error("cannot set sigprocmask to block the user signal");

	len = strlen(address);
	if (-1 == (n = write(fd, address, len)))
	{
		zbx_snprintf(error, max_error_len, "Cannot write address into temporary file: %s", zbx_strerror(errno));
		(void)close(fd);
		goto out;
	}

	if (n != (ssize_t)len)
	{
		zbx_strlcpy(error, "Cannot write full address into temporary file", max_error_len);
		(void)close(fd);
		goto out;
	}

	if (-1 == close(fd))
	{
		zbx_snprintf(error, max_error_len, "Cannot close temporary file: %s", zbx_strerror(errno));
		goto out;
	}

	zbx_snprintf(tmp, sizeof(tmp), "%s 2>&1 < %s", fping, filename);

	zabbix_log(LOG_LEVEL_DEBUG, "executing %s", tmp);

	if (NULL == (f = popen(tmp, "r")))
	{
		zbx_strlcpy(error, zbx_strerror(errno), max_error_len);
		goto out;
	}

	while (NULL != zbx_fgets(tmp, sizeof(tmp), f))
	{
		len = strlen(tmp);

		if (MAX_EXECUTE_OUTPUT_LEN < offset + len)
			break;

		zbx_strncpy_alloc(&buffer, &buf_size, &offset, tmp, len);
	}

	pclose(f);

	if (NULL == buffer)
	{
		zbx_strlcpy(error, "Cannot obtain the program output", max_error_len);
		goto out;
	}

	*out = buffer;
	ret = SUCCEED;
out:
	if (0 > zbx_sigmask(SIG_SETMASK, &orig_mask, NULL))
		zbx_error("cannot restore sigprocmask");

	unlink(filename);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Detect if response was redirected or not and if redirected        *
 *          response is treated as host down.                                 *
 *                                                                            *
 * Parameters: allow_redirect - [IN] 0: redirected response treated as host   *
 *                                      down                                  *
 *                                   1: redirected response is not treated    *
 *                                      as host                               *
 *             linebuf        - [IN]    bufuer containing fping output line   *
 *                                                                            *
 * Return value: SUCCEED - no redirect was detected or                        *
 *                         redirect was detected and redirect is allowed      *
 *               FAIL    - redirect was detected and redirect is not allowed  *
 *                         (target host down)                                 *
 *                                                                            *
 * Comments: Redirected response is a situation when the target that is being *
 *           ICMP pinged responds from a different IP address.                *
 *                                                                            *
 ******************************************************************************/
static int	redirect_detect(const char *linebuf, unsigned char allow_redirect)
{
	int	ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	/* In case of a redirected response, fping would add the response IP address in square        */
	/* brackets with left triangular bracket and a dash: '[<- AAA.BBB.CCC.DDD]'.                  */

	if (0 == allow_redirect && NULL != strstr(linebuf, " [<-"))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "treating redirected response as target host down: \"%s\"",
				linebuf);
		ret = FAIL;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Remove redirected response source address '[<- AAA.BBB.CCC.DDD]'  *
 *          from fping output line buffer, if present                         *
 *                                                                            *
 * Parameters: linebuf        - [IN/OUT] buffer containing fping output line  *
 *                                                                            *
 * Return value: SUCCEED - no format error was detected                       *
 *               FAIL    - unexpected format was detected                     *
 *                                                                            *
 * Comments: Redirected response is a situation when the target that is being *
 *           ICMP pinged responds from a different IP address.                *
 *                                                                            *
 *           Format error should never happen unless fping output format is   *
 *           changed in future versions.                                      *
 *                                                                            *
 ******************************************************************************/
static int	redirect_remove(char *linebuf)
{
	int	ret = SUCCEED;
	char	*p_start;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	/* In case of a redirected response, fping would add the response IP address in square        */
	/* brackets with left triangular bracket and a dash: '[<- AAA.BBB.CCC.DDD]'.                  */
	/*                                                                                            */
	/* Before fping 3.11, fping appends response source address at the end of the line:           */
	/* '192.168.1.1 : [0], 84 bytes, 0.61 ms (0.61 avg, 0% loss) [<- 192.168.1.2]'                */
	/*                                                                                            */
	/* Since fping 3.11, fping prepends response source address at the beginning of the line:     */
	/* ' [<- 192.168.1.2]192.168.1.1 : [0], 84 bytes, 0.65 ms (0.65 avg, 0% loss)'                */

	if (NULL != (p_start = strstr(linebuf, " [<-")))
	{
		char	*p_end;

		if (NULL == (p_end = strchr(p_start, ']')))
		{
			zabbix_log(LOG_LEVEL_WARNING, "should never happen; unexpected syntax in response from fping:"
					" \"%s\"; \"]\" after \" [<-\" was expected", linebuf);
			ret = FAIL;
			goto out;
		}

		zabbix_log(LOG_LEVEL_DEBUG, "removing redirected response source address from line: \"%s\"", linebuf);

		p_end++;

		memmove(p_start, p_end, strlen(p_end) + 1);	/* include zero-termination character */
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: detect minimal possible fping packet interval                     *
 *                                                                            *
 * Parameters: fping         - [IN] the location of fping program             *
 *             hosts         - [IN] list of hosts to test                     *
 *             hosts_count   - [IN] number of target hosts                    *
 *             value         - [OUT] interval between sending ping packets    *
 *                                   (in millisec)                            *
 *             error         - [OUT] error string if function fails           *
 *             max_error_len - [IN] length of error buffer                    *
 *                                                                            *
 * Return value: SUCCEED if processed successfully or FAIL otherwise          *
 *                                                                            *
 * Comments: supported minimum interval (in milliseconds) in different fping  *
 *           versions:                                                        *
 *           +------------------+--------------------------+---------+        *
 *           | version X        | as root/non-root/non-    | Default |        *
 *           |                  | root with "safe limits"  |         |        *
 *           +------------------+--------------------------+---------+        *
 *           |         X < 3.14 | 1 / 10 / -               | 25      |        *
 *           | 3.14 <= X <  4.0 | 0 /  1 / -               | 25      |        *
 *           | 4.0  <= X        | 0 /  0 / 1               | 10      |        *
 *           +------------------+--------------------------+---------+        *
 *           Note! "Safe limits" is compile-time option introduced in         *
 *           fping 4.0. Distribution packages ship fping binary without       *
 *           "safe limits".                                                   *
 *                                                                            *
 ******************************************************************************/
static int	get_interval_option(const char *fping, const zbx_fping_host_t *hosts, int hosts_count, int *value,
		char *error, size_t max_error_len)
{
	char		*out = NULL;
	unsigned int	intervals[] = {0, 1, 10};
	size_t		out_len;
	int		ret = FAIL, i;

	for (i = 0; i < hosts_count; i++)
	{
		size_t		j;
		const char	*dst = hosts[i].addr;

		for (j = 0; j < ARRSIZE(intervals); j++)
		{
			char		tmp[MAX_STRING_LEN], err[255];
			const char	*p;

			zabbix_log(LOG_LEVEL_DEBUG, "testing fping interval %u ms", intervals[j]);

			zbx_snprintf(tmp, sizeof(tmp), "%s -c1 -t50 -i%u", fping, intervals[j]);

			zbx_free(out);

			if (FAIL == get_fping_out(tmp, dst, &out, err, sizeof(err)))
			{
				zbx_snprintf(error, max_error_len, "Cannot execute \"%s\": %s", tmp, err);
				goto out;
			}

			/* First, check the output for suggested interval option, e. g.:          */
			/*                                                                        */
			/* /usr/sbin/fping: these options are too risky for mere mortals.         */
			/* /usr/sbin/fping: You need i >= 1, p >= 20, r < 20, and t >= 50         */

	#define FPING_YOU_NEED_PREFIX	"You need i >= "

			if (NULL != (p = strstr(out, FPING_YOU_NEED_PREFIX)))
			{
				p += ZBX_CONST_STRLEN(FPING_YOU_NEED_PREFIX);

				*value = atoi(p);
				ret = SUCCEED;

				goto out;
			}

	#undef FPING_YOU_NEED_PREFIX

			/* in fping 3.16 they changed "You need i >=" to "You need -i >=" */

	#define FPING_YOU_NEED_PREFIX	"You need -i >= "

			if (NULL != (p = strstr(out, FPING_YOU_NEED_PREFIX)))
			{
				p += ZBX_CONST_STRLEN(FPING_YOU_NEED_PREFIX);

				*value = atoi(p);
				ret = SUCCEED;

				goto out;
			}

	#undef FPING_YOU_NEED_PREFIX

			/* if we get dst in the beginning of the output, the used interval is allowed, */
			/* unless we hit the help message which is always bigger than 1 Kb             */
			if (ZBX_KIBIBYTE > strlen(out))
			{
				int	unused = redirect_remove(out);

				ZBX_UNUSED(unused);

				/* skip white spaces */
				for (p = out; '\0' != *p && isspace(*p); p++)
					;

				if (strlen(p) >= strlen(dst) && 0 == strncmp(p, dst, strlen(dst)))
				{
					*value = (int)intervals[j];
					ret = SUCCEED;

					goto out;
				}

				/* check if we hit the error message */
				if (NULL != strstr(out, " as root"))
				{
					zbx_rtrim(out, "\n");
					zbx_strlcpy(error, out, max_error_len);
					goto out;
				}
			}
		}
	}

	/* if we are here we have probably hit the usage or error message, let's collect it if it's error message */

	if (NULL != out && ZBX_KIBIBYTE > (out_len = strlen(out)) && 0 != out_len)
	{
		zbx_rtrim(out, "\n");
		zbx_strlcpy(error, out, max_error_len);
	}
	else
		zbx_snprintf(error, max_error_len, "Cannot detect the minimum interval of %s", fping);
out:
	zbx_free(out);

	return ret;
}

#ifdef HAVE_IPV6
/******************************************************************************
 *                                                                            *
 * Purpose: check fping supports IPv6                                         *
 *                                                                            *
 * Parameters: fping - [IN] the location of fping program                     *
 *             dst   - [IN] the ip address for test                           *
 *                                                                            *
 * Return value: SUCCEED - IPv6 is supported                                  *
 *               FAIL    - IPv6 is not supported                              *
 *                                                                            *
 ******************************************************************************/
static int	get_ipv6_support(const char *fping, const char *dst)
{
	int	ret;
	char	tmp[MAX_STRING_LEN], *out = NULL, error[255];

	zbx_snprintf(tmp, sizeof(tmp), "%s -6 -c1 -t50", fping);

	if ((FAIL == (ret = get_fping_out(tmp, dst, &out, error, sizeof(error))) ||
			ZBX_KIBIBYTE < strlen(out) || NULL == strstr(out, dst)))
	{
		ret = FAIL;
	}

	zbx_free(out);

	return ret;
}
#endif	/* HAVE_IPV6 */

/******************************************************************************
 *                                                                            *
 * Purpose: check fping response                                              *
 *                                                                            *
 * Parameters: resp        - [IN] fping stdout                                *
 *             hosts       - [IN] array of ip address for test                *
 *             hosts_count - [IN] size of ip address array for test           *
 *             rdns        - [IN] flag that dns name is present               *
 *             dnsname_len - [OUT] dns name length                            *
 *             host        - [OUT] found correspondent host from array        *
 *                                                                            *
 * Return value: SUCCEED - successfully processed hosts                       *
 *               NOTSUPPORTED - otherwise                                     *
 *                                                                            *
 ******************************************************************************/
static int	check_hostip_response(char *resp, zbx_fping_host_t *hosts, const int hosts_count, const int rdns,
		size_t *dnsname_len, zbx_fping_host_t **host)
{
	int	i, ret = FAIL;
	char	*c, *tmp = resp;

	if (NULL == (c = strchr(tmp, ' ')))
		return FAIL;

	*c = '\0';

	/* when rdns is used, there are also lines like */
	/* Lab-u22 (192.168.6.51) : [0], 64 bytes, 0.024 ms (0.024 avg, 0% loss) */

	if (0 != rdns)
	{
		*dnsname_len = SUCCEED == zbx_is_ip(tmp) ? 0 : zbx_strlen_utf8(tmp);
		*c = ' ';

		if (ZBX_MAX_DNSNAME_LEN < *dnsname_len)
			return FAIL;

		if (NULL == (c = strchr(tmp, '(')))
			return FAIL;

		tmp = c + 1;

		if (NULL == (c = strchr(tmp, ')')))
			return FAIL;

		*c = '\0';
	}

	for (i = 0; i < hosts_count; i++)
	{
		if ((0 != rdns && SUCCEED == zbx_ip_in_list(tmp, hosts[i].addr)) ||
				(0 == rdns && 0 == strcmp(tmp, hosts[i].addr)))
		{
			*host = &hosts[i];
			ret = SUCCEED;
			break;
		}
	}

	*c = (0 == rdns) ? ' ' : ')';

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get ICMP pinged host by host address in fping output line         *
 *                                                                            *
 * Parameters: resp        - [IN] fping output                                *
 *             args        - [IN] host data and fping settings                *
 *             dnsname_len - [IN]                                             *
 *             host        - [OUT]                                            *
 *                                                                            *
 * Return value: SUCCEED - host was found                                     *
 *               FAIL    - fping returned response for and unknown host       *
 *                                                                            *
 ******************************************************************************/
static int	host_get(zbx_fping_resp *resp, zbx_fping_args *args, size_t *dnsname_len, zbx_fping_host_t **host)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	*host = NULL;

	ret = check_hostip_response(resp->linebuf, args->hosts, args->hosts_count, args->rdns, dnsname_len, host);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: process a line containing status of individual ICMP ping          *
 *          response packet and set host status up or down                    *
 *                                                                            *
 * Parameters: linebuf_p - [IN]                                               *
 *             host      - [IN/OUT]                                           *
 *             args       -[IN/OUT] host data and fping settings              *
 *                                                                            *
 ******************************************************************************/
static void	host_status_set(char *linebuf_p, zbx_fping_host_t *host, zbx_fping_args *args)
{
	int	response_idx;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	response_idx = atoi(linebuf_p + 1);

	if (0 > response_idx || response_idx >= args->requests_count)
		return;

	/* since 5.0 Fping outputs individual failed packages in additional to successful: */
	/*                                                                                 */
	/*   fping -C3 -i0 7.7.7.7 8.8.8.8                                                 */
	/*   8.8.8.8 : [0], 64 bytes, 9.37 ms (9.37 avg, 0% loss)                          */
	/*   7.7.7.7 : [0], timed out (NaN avg, 100% loss)                                 */
	/*   8.8.8.8 : [1], 64 bytes, 8.72 ms (9.05 avg, 0% loss)                          */
	/*   7.7.7.7 : [1], timed out (NaN avg, 100% loss)                                 */
	/*   8.8.8.8 : [2], 64 bytes, 7.28 ms (8.46 avg, 0% loss)                          */
	/*   7.7.7.7 : [2], timed out (NaN avg, 100% loss)                                 */
	/*                                                                                 */
	/*   7.7.7.7 : - - -                                                               */
	/*   8.8.8.8 : 9.37 8.72 7.28                                                      */
	/*                                                                                 */
	/* Judging by Fping source code we can disregard lines reporting "timed out".      */

	if (NULL != strstr(linebuf_p + 2, " timed out "))
		return;

	host->status[response_idx] = 1;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process the status line containing response times for one target  *
 *          host and one or more requests and calculate statistics            *
 *                                                                            *
 * Parameters: linebuf_p - [IN]                                               *
 *             host      - [IN/OUT]                                           *
 *             args       -[IN/OUT] host data and fping settings              *
 *                                                                            *
 ******************************************************************************/
static void	stats_calc(char *linebuf_p, zbx_fping_host_t *host, zbx_fping_args *args)
{
	int	response_idx = 0;
	double	sec;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	/* Process the status line for a host. There were 5 requests in this example. A status      */
	/* line for a host shows response time in milliseconds for the individual requests, with    */
	/* the "−" indicating that no response was received to the request with index 3:            */
	/* 8.8.8.8 : 91.7 37.0 29.2 − 36.8                                                          */

	do
	{
		if (1 == host->status[response_idx])
		{
			sec = atof(linebuf_p) / 1000; /* convert ms to seconds */

			if (0 == host->rcv || host->min > sec)
				host->min = sec;
			if (0 == host->rcv || host->max < sec)
				host->max = sec;
			host->sum += sec;
			host->rcv++;
		}
	}
	while (++response_idx < args->requests_count && NULL != (linebuf_p = strchr(linebuf_p + 1, ' ')));

	host->cnt += args->requests_count;
#ifdef HAVE_IPV6
	if (host->cnt == args->requests_count && NULL == config_icmpping->get_source_ip() &&
			0 != (args->fping_existence & FPING_EXISTS) &&
			0 != (args->fping_existence & FPING6_EXISTS))
	{
		memset(host->status, 0, (size_t)args->requests_count);	/* reset response statuses for IPv6 */
	}
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process an individual fping output line                           *
 *                                                                            *
 * Parameters: resp - [IN] fping output                                       *
 *             args - [IN/OUT] host data and fping settings                   *
 *                                                                            *
 ******************************************************************************/
static void	line_process(zbx_fping_resp *resp, zbx_fping_args *args)
{
	zbx_fping_host_t	*host;
	char			*linebuf_p;
	size_t			dnsname_len;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() linebuf: \"%s\"", __func__, resp->linebuf);

	if (SUCCEED != redirect_detect(resp->linebuf, args->allow_redirect))
		return;

	if (SUCCEED != redirect_remove(resp->linebuf))
		return;

	if (SUCCEED != host_get(resp, args, &dnsname_len, &host))
		return;

	if (NULL == (linebuf_p = strstr(resp->linebuf, " : ")))
		return;

	/* When NIC bonding is used, there are also lines like:                                          */
	/* 192.168.1.2 : duplicate for [0], 96 bytes, 0.19 ms                                            */

	if (NULL != strstr(resp->linebuf, "duplicate for"))
		return;

	linebuf_p += 3;

	if ('[' == *linebuf_p)
	{
		/* There is a bug in fping (v3.8 at least) where pinging broadcast address will result in */
		/* no individual responses, but the final status line might contain a bogus value.        */
		/* Because of this issue, we must monitor individual responses and mark the valid ones.   */
		/*   8.8.8.8 : [0], 64 bytes, 9.37 ms (9.37 avg, 0% loss)                                 */
		host_status_set(linebuf_p, host, args);
	}
	else
	{
		/* Fping statistics may look like:                                                        */
		/* 8.8.8.8 : 91.7 37.0 29.2 − 36.8                                                        */
		stats_calc(linebuf_p, host, args);
	}

	if (0 != args->rdns && (NULL == host->dnsname || ('\0' == *host->dnsname && 0 != dnsname_len)))
	{
		host->dnsname = zbx_dsprintf(host->dnsname, "%.*s", (int)dnsname_len, resp->linebuf);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process multiple-line fping output                                *
 *                                                                            *
 * Parameters: resp - [IN] fping output                                       *
 *             args - [IN/OUT] host data and fping settings                   *
 *                                                                            *
 * Return value: SUCCEED      - fping output processed successfully           *
 *               NOTSUPPORTED - unexpected error                              *
 *                                                                            *
 ******************************************************************************/
static int	fping_output_process(zbx_fping_resp *resp, zbx_fping_args *args)
{
	int	i, ret = NOTSUPPORTED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL == zbx_fgets(resp->linebuf, (int)resp->linebuf_size, resp->input_pipe))
	{
		zbx_snprintf(resp->linebuf, resp->linebuf_size, "no output");
	}
	else if (NULL == strstr(resp->linebuf, " error:"))
	{
		for (i = 0; i < args->hosts_count; i++)
		{
			args->hosts[i].status = (char *)zbx_malloc(NULL, (size_t)args->requests_count);
			memset(args->hosts[i].status, 0, (size_t)args->requests_count);
		}

		do
		{
			zbx_rtrim(resp->linebuf, "\n");
			line_process(resp, args);
			ret = SUCCEED;
		}
		while (NULL != zbx_fgets(resp->linebuf, (int)resp->linebuf_size, resp->input_pipe));

		for (i = 0; i < args->hosts_count; i++)
			zbx_free(args->hosts[i].status);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

static int	hosts_ping(zbx_fping_host_t *hosts, int hosts_count, int requests_count, int interval, int size,
		int timeout, unsigned char allow_redirect, int rdns, char *error, size_t max_error_len)
{
	const int	response_time_chars_max = 20;
	FILE		*f;
	char		params[70];
	char		filename[MAX_STRING_LEN];
	char		*linebuf = NULL;
	size_t		linebuf_size;
	size_t		offset;
	int 		i, ret = NOTSUPPORTED, rc;
	sigset_t	mask, orig_mask;
	zbx_fping_args	fping_args;
	zbx_fping_resp	fping_resp;

#ifdef HAVE_IPV6
	int		family;
	char		params6[70];
	size_t		offset6;
	char		fping_existence = 0;
#endif

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	assert(hosts);

#define FPING_CHECK_EXPIRED	3600	/* seconds, expire detected fping options every hour */

	/* expire detected options once in a while */
	if ((time(NULL) - fping_check_reset_at) > FPING_CHECK_EXPIRED)
	{
		fping_check_reset_at = time(NULL);

		source_ip_checked = 0;
		packet_interval = FPING_UNINITIALIZED_VALUE;
#ifdef HAVE_IPV6
		source_ip6_checked = 0;
		packet_interval6 = FPING_UNINITIALIZED_VALUE;
		fping_ipv6_supported = FPING_UNINITIALIZED_VALUE;
#endif
	}

#undef FPING_CHECK_EXPIRED

	linebuf_size = (size_t)(MAX_STRING_LEN + requests_count * response_time_chars_max);
	linebuf = zbx_malloc(linebuf, linebuf_size);

	if (-1 == access(config_icmpping->get_fping_location(), X_OK))
	{
#if !defined(HAVE_IPV6)
		zbx_snprintf(error, max_error_len, "%s: %s", config_icmpping->get_fping_location(),
				zbx_strerror(errno));
		goto out;
#endif
	}
	else
	{
#ifdef HAVE_IPV6
		fping_existence |= FPING_EXISTS;
#else
		if (NULL != config_icmpping->get_source_ip())
		{
			if (FAIL == zbx_is_ip4(config_icmpping->get_source_ip()))
			{
				zbx_snprintf(error, max_error_len,
					"You should enable IPv6 support to use IPv6 family address for SourceIP '%s'.",
					config_icmpping->get_source_ip());
				goto out;
			}
		}
#endif
	}

#ifdef HAVE_IPV6
	if (-1 == access(config_icmpping->get_fping6_location(), X_OK))
	{
		if (0 == (fping_existence & FPING_EXISTS))
		{
			zbx_snprintf(error, max_error_len, "At least one of '%s', '%s' must exist. "
					"Both are missing in the system.", config_icmpping->get_fping_location(),
					config_icmpping->get_fping6_location());
			goto out;
		}
	}
	else
		fping_existence |= FPING6_EXISTS;
#endif	/* HAVE_IPV6 */

	offset = zbx_snprintf(params, sizeof(params), "-C%d", requests_count);
	if (0 != interval)
		offset += zbx_snprintf(params + offset, sizeof(params) - offset, " -p%d", interval);
	if (0 != size)
		offset += zbx_snprintf(params + offset, sizeof(params) - offset, " -b%d", size);
	if (0 != timeout)
		offset += zbx_snprintf(params + offset, sizeof(params) - offset, " -t%d", timeout);
	if (0 != rdns)
		offset += zbx_snprintf(params + offset, sizeof(params) - offset, " -dA");

#ifdef HAVE_IPV6
	zbx_strscpy(params6, params);
	offset6 = offset;

	if (0 != (fping_existence & FPING_EXISTS) && 0 != hosts_count)
	{
		if (FPING_UNINITIALIZED_VALUE == packet_interval)
		{
			int			hsts_count = 1;
			const zbx_fping_host_t	h = {.addr = "127.0.0.1"}, *hsts = &h;

			if (0 == rdns)
			{
				hsts = hosts;
				hsts_count = hosts_count;
			}

			if (SUCCEED != get_interval_option(config_icmpping->get_fping_location(), hsts, hsts_count,
					&packet_interval, error, max_error_len))
			{
				goto out;
			}

			zabbix_log(LOG_LEVEL_DEBUG, "detected minimum supported fping interval (-i): %d",
					packet_interval);
		}

		offset += zbx_snprintf(params + offset, sizeof(params) - offset, " -i%d", packet_interval);
	}

	if (0 != (fping_existence & FPING6_EXISTS) && 0 != hosts_count)
	{
		if (FPING_UNINITIALIZED_VALUE == packet_interval6)
		{
			int			hsts_count = 1;
			const zbx_fping_host_t	h = {.addr = "::1"}, *hsts = &h;

			if (0 == rdns)
			{
				hsts = hosts;
				hsts_count = hosts_count;
			}

			if (SUCCEED != get_interval_option(config_icmpping->get_fping6_location(), hsts, hsts_count,
					&packet_interval6, error, max_error_len))
			{
				goto out;
			}

			zabbix_log(LOG_LEVEL_DEBUG, "detected minimum supported fping6 interval (-i): %d",
					packet_interval6);
		}

		offset6 += zbx_snprintf(params6 + offset6, sizeof(params6) - offset6, " -i%d", packet_interval6);
	}
#else
	if (0 != hosts_count)
	{
		if (FPING_UNINITIALIZED_VALUE == packet_interval)
		{
			int			hsts_count = 1;
			const zbx_fping_host_t	h = {.addr = "127.0.0.1"}, *hsts = &h;

			if (0 == rdns)
			{
				hsts = hosts;
				hsts_count = hosts_count;
			}

			if (SUCCEED != get_interval_option(config_icmpping->get_fping_location(), hsts, hsts_count,
					&packet_interval, error, max_error_len))
			{
				goto out;
			}

			zabbix_log(LOG_LEVEL_DEBUG, "detected minimum supported fping interval (-i): %d",
					packet_interval);
		}

		offset += zbx_snprintf(params + offset, sizeof(params) - offset, " -i%d", packet_interval);
	}
#endif	/* HAVE_IPV6 */

	if (NULL != config_icmpping->get_source_ip())
	{
#ifdef HAVE_IPV6
		if (0 != (fping_existence & FPING_EXISTS))
		{
			if (0 == source_ip_checked)
			{
				get_source_ip_option(config_icmpping->get_fping_location(), &source_ip_option,
						&source_ip_checked);

				zabbix_log(LOG_LEVEL_DEBUG, "detected fping source IP option: \"%s\"",
						ZBX_NULL2EMPTY_STR(source_ip_option));
			}

			if (NULL != source_ip_option)
				zbx_snprintf(params + offset, sizeof(params) - offset, " %s%s", source_ip_option,
						config_icmpping->get_source_ip());
		}

		if (0 != (fping_existence & FPING6_EXISTS))
		{
			if (0 == source_ip6_checked)
			{
				get_source_ip_option(config_icmpping->get_fping6_location(), &source_ip6_option,
						&source_ip6_checked);

				zabbix_log(LOG_LEVEL_DEBUG, "detected fping6 source IP option: \"%s\"",
						ZBX_NULL2EMPTY_STR(source_ip6_option));
			}

			if (NULL != source_ip6_option)
				zbx_snprintf(params6 + offset6, sizeof(params6) - offset6,
						" %s%s", source_ip6_option, config_icmpping->get_source_ip());
		}
#else
		if (0 == source_ip_checked)
		{
			get_source_ip_option(config_icmpping->get_fping_location(), &source_ip_option,
					&source_ip_checked);

			zabbix_log(LOG_LEVEL_DEBUG, "detected fping source IP option: \"%s\"",
					ZBX_NULL2EMPTY_STR(source_ip_option));
		}

		if (NULL != source_ip_option)
			zbx_snprintf(params + offset, sizeof(params) - offset, " %s%s", source_ip_option,
					config_icmpping->get_source_ip());
#endif	/* HAVE_IPV6 */
	}

	if ('\0' == *tmpfile_uniq)
		zbx_snprintf(tmpfile_uniq, sizeof(tmpfile_uniq), "%li", zbx_get_thread_id());

	zbx_snprintf(filename, sizeof(filename), "%s/%s_%s.pinger", config_icmpping->get_tmpdir(),
			config_icmpping->get_progname(), tmpfile_uniq);

#ifdef HAVE_IPV6
	if (NULL != config_icmpping->get_source_ip())
	{
		if (SUCCEED != get_address_family(config_icmpping->get_source_ip(), &family, error,
				(int)max_error_len))
			goto out;

		if (family == PF_INET)
		{
			if (0 == (fping_existence & FPING_EXISTS))
			{
				zbx_snprintf(error, max_error_len, "File '%s' cannot be found in the system.",
						config_icmpping->get_fping_location());
				goto out;
			}

			zbx_snprintf(linebuf, linebuf_size, "%s %s 2>&1 <%s", config_icmpping->get_fping_location(),
					params, filename);
		}
		else
		{
			if (0 == (fping_existence & FPING6_EXISTS))
			{
				zbx_snprintf(error, max_error_len, "File '%s' cannot be found in the system.",
						config_icmpping->get_fping6_location());
				goto out;
			}

			zbx_snprintf(linebuf, linebuf_size, "%s %s 2>&1 <%s", config_icmpping->get_fping6_location(),
					params6, filename);
		}
	}
	else
	{
		offset = 0;

		if (0 != (fping_existence & FPING_EXISTS))
		{
			if (FPING_UNINITIALIZED_VALUE == fping_ipv6_supported)
			{
				fping_ipv6_supported = get_ipv6_support(config_icmpping->get_fping_location(),
						hosts[0].addr);

				zabbix_log(LOG_LEVEL_DEBUG, "detected fping IPv6 support: \"%s\"",
						SUCCEED == fping_ipv6_supported ? "yes" : "no");
			}

			offset += zbx_snprintf(linebuf + offset, linebuf_size - offset, "%s %s 2>&1 <%s;",
					config_icmpping->get_fping_location(), params, filename);
		}

		if (0 != (fping_existence & FPING6_EXISTS) && SUCCEED != fping_ipv6_supported)
		{
			zbx_snprintf(linebuf + offset, linebuf_size - offset, "%s %s 2>&1 <%s;",
					config_icmpping->get_fping6_location(), params6, filename);
		}
	}
#else
	zbx_snprintf(linebuf, linebuf_size, "%s %s 2>&1 <%s", config_icmpping->get_fping_location(), params, filename);
#endif	/* HAVE_IPV6 */

	if (NULL == (f = fopen(filename, "w")))
	{
		zbx_snprintf(error, max_error_len, "%s: %s", filename, zbx_strerror(errno));
		goto out;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s", filename);

	for (i = 0; i < hosts_count; i++)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "    %s", hosts[i].addr);
		fprintf(f, "%s\n", hosts[i].addr);
	}

	fclose(f);

	zabbix_log(LOG_LEVEL_DEBUG, "executing %s", linebuf);

	sigemptyset(&mask);
	sigaddset(&mask, SIGINT);
	sigaddset(&mask, SIGQUIT);

	if (0 > zbx_sigmask(SIG_BLOCK, &mask, &orig_mask))
		zbx_error("cannot set sigprocmask to block the user signal");

	if (NULL == (f = popen(linebuf, "r")))
	{
		zbx_snprintf(error, max_error_len, "%s: %s", linebuf, zbx_strerror(errno));

		unlink(filename);

		if (0 > zbx_sigmask(SIG_SETMASK, &orig_mask, NULL))
			zbx_error("cannot restore sigprocmask");

		goto out;
	}

	fping_resp.input_pipe = f;
	fping_resp.linebuf = linebuf;
	fping_resp.linebuf_size = linebuf_size;

	fping_args.hosts = hosts;
	fping_args.hosts_count = hosts_count;
	fping_args.requests_count = requests_count;
	fping_args.allow_redirect = allow_redirect;
	fping_args.rdns = rdns;
#ifdef HAVE_IPV6
	fping_args.fping_existence = fping_existence;
#endif
	if (SUCCEED == fping_output_process(&fping_resp, &fping_args))
	{
		ret = SUCCEED;
	}

	rc = pclose(f);

	if (0 > zbx_sigmask(SIG_SETMASK, &orig_mask, NULL))
		zbx_error("cannot restore sigprocmask");

	unlink(filename);

	if (WIFSIGNALED(rc))
		ret = FAIL;
	else
		zbx_snprintf(error, max_error_len, "fping failed: %s", linebuf);
out:
	zbx_free(linebuf);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize library                                                *
 *                                                                            *
 * Parameters: config - [IN]  pointer to library configuration structure      *
 *                                                                            *
 ******************************************************************************/
void	zbx_init_library_icmpping(const zbx_config_icmpping_t *config)
{
	config_icmpping = config;
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize unique tmp file name                                   *
 *                                                                            *
 * Parameters: prefix - [IN] base name                                        *
 *             id     - [IN] thread or process id                             *
 *                                                                            *
 ******************************************************************************/
void	zbx_init_icmpping_env(const char *prefix, long int id)
{
	zbx_snprintf(tmpfile_uniq, sizeof(tmpfile_uniq), "%s_%li", prefix, id);
	zbx_remove_chars(tmpfile_uniq, " ");
}

/******************************************************************************
 *                                                                            *
 * Purpose: ping hosts listed in the host files                               *
 *                                                                            *
 * Parameters: hosts          - [IN]  list of target hosts                    *
 *             hosts_count    - [IN]  number of target hosts                  *
 *             requests_count - [IN]  number of pings to send to each target  *
 *                                    (fping option -C)                       *
 *             period         - [IN]  interval between ping packets to one    *
 *                                    target, in milliseconds                 *
 *                                    (fping option -p)                       *
 *             size           - [IN]  amount of ping data to send, in bytes   *
 *                                   (fping option -b)                        *
 *             timeout        - [IN]  individual target initial timeout       *
 *                                    except when count > 1, where it's the   *
 *                                    -p period (fping option -t)             *
 *             allow_redirect - [IN]  treat redirected response as host up:   *
 *                                    0 - no, 1 - yes                         *
 *             rdns          - [IN]  flag required rdns option                *
 *                                   (fping option -dA)                       *
 *             error          - [OUT] error string if function fails          *
 *             max_error_len  - [IN]  length of error buffer                  *
 *                                                                            *
 * Return value: SUCCEED - successfully processed hosts                       *
 *               NOTSUPPORTED - otherwise                                     *
 *                                                                            *
 * Comments: use external binary 'fping' to avoid superuser privileges        *
 *                                                                            *
 ******************************************************************************/
int	zbx_ping(zbx_fping_host_t *hosts, int hosts_count, int requests_count, int period, int size, int timeout,
		unsigned char allow_redirect, int rdns, char *error, size_t max_error_len)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() hosts_count:%d", __func__, hosts_count);

	if (NOTSUPPORTED == (ret = hosts_ping(hosts, hosts_count, requests_count, period, size, timeout,
			allow_redirect, rdns, error, max_error_len)))
	{
		zabbix_log(LOG_LEVEL_ERR, "%s", error);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
