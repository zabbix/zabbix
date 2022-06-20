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

#include "zbxicmpping.h"

#include "zbxthreads.h"
#include "zbxcomms.h"
#include "zbxexec.h"
#include "log.h"
#include <signal.h>

extern char	*CONFIG_SOURCE_IP;
extern char	*CONFIG_FPING_LOCATION;
#ifdef HAVE_IPV6
extern char	*CONFIG_FPING6_LOCATION;
#endif
extern char	*CONFIG_TMPDIR;

/* old official fping (2.4b2_to_ipv6) did not support source IP address */
/* old patched versions (2.4b2_to_ipv6) provided either -I or -S options */
/* since fping 3.x it provides -I option for binding to an interface and -S option for source IP address */

static unsigned char	source_ip_checked;
static const char	*source_ip_option;
#ifdef HAVE_IPV6
static unsigned char	source_ip6_checked;
static const char	*source_ip6_option;
#endif

#define FPING_UNINITIALIZED_VALUE	-2
static int		packet_interval;
#ifdef HAVE_IPV6
static int		packet_interval6;
static int		fping_ipv6_supported;
#endif

#define FPING_CHECK_EXPIRED	3600	/* seconds, expire detected fping options every hour */
static time_t	fping_check_reset_at;	/* time of the last fping options expiration */

static void	get_source_ip_option(const char *fping, const char **option, unsigned char *checked)
{
	FILE	*f;
	char	*p, tmp[MAX_STRING_LEN];

	zbx_snprintf(tmp, sizeof(tmp), "%s -h 2>&1", fping);

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
 *           | version X        | as root/non-root/without | Default |        *
 *           |                  | "safe limits"            |         |        *
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
static int	get_interval_option(const char *fping, ZBX_FPING_HOST *hosts, int hosts_count, int *value, char *error,
		size_t max_error_len)
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
			int		ret_exec;
			char		tmp[MAX_STRING_LEN], err[255];
			const char	*p;

			zabbix_log(LOG_LEVEL_DEBUG, "testing fping interval %u ms", intervals[j]);

			zbx_snprintf(tmp, sizeof(tmp), "%s -c1 -t50 -i%u %s", fping, intervals[j], dst);

			zbx_free(out);

			/* call fping, ignore its exit code but mind execution failures */
			if (TIMEOUT_ERROR == (ret_exec = zbx_execute(tmp, &out, err, sizeof(err), 1,
					ZBX_EXIT_CODE_CHECKS_DISABLED, NULL)))
			{
				zbx_snprintf(error, max_error_len, "Timeout while executing \"%s\"", tmp);
				goto out;
			}

			if (SUCCEED != ret_exec)
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
static int	get_ipv6_support(const char * fping, const char *dst)
{
	int	ret;
	char	tmp[MAX_STRING_LEN], error[255], *out = NULL;

	zbx_snprintf(tmp, sizeof(tmp), "%s -6 -c1 -t50 %s", fping, dst);

	if ((SUCCEED == (ret = zbx_execute(tmp, &out, error, sizeof(error), 1, ZBX_EXIT_CODE_CHECKS_DISABLED, NULL)) &&
				ZBX_KIBIBYTE > strlen(out) && NULL != strstr(out, dst)) || TIMEOUT_ERROR == ret)
	{
		ret = SUCCEED;
	}
	else
	{
		ret = FAIL;
	}

	zbx_free(out);

	return ret;

}
#endif	/* HAVE_IPV6 */

static int	process_ping(ZBX_FPING_HOST *hosts, int hosts_count, int count, int interval, int size, int timeout,
		char *error, size_t max_error_len)
{
	const int	response_time_chars_max = 20;
	FILE		*f;
	char		params[70];
	char		filename[MAX_STRING_LEN];
	char		*tmp = NULL;
	size_t		tmp_size;
	size_t		offset;
	double		sec;
	int 		i, ret = NOTSUPPORTED, index, rc;
	sigset_t	mask, orig_mask;

#ifdef HAVE_IPV6
	int		family;
	char		params6[70];
	size_t		offset6;
	char		fping_existence = 0;
#define	FPING_EXISTS	0x1
#define	FPING6_EXISTS	0x2

#endif	/* HAVE_IPV6 */

	assert(hosts);

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

	tmp_size = (size_t)(MAX_STRING_LEN + count * response_time_chars_max);
	tmp = zbx_malloc(tmp, tmp_size);

	if (-1 == access(CONFIG_FPING_LOCATION, X_OK))
	{
#if !defined(HAVE_IPV6)
		zbx_snprintf(error, max_error_len, "%s: %s", CONFIG_FPING_LOCATION, zbx_strerror(errno));
		goto out;
#endif
	}
	else
	{
#ifdef HAVE_IPV6
		fping_existence |= FPING_EXISTS;
#else
		if (NULL != CONFIG_SOURCE_IP)
		{
			if (FAIL == is_ip4(CONFIG_SOURCE_IP)) /* we do not have IPv4 family address in CONFIG_SOURCE_IP */
			{
				zbx_snprintf(error, max_error_len,
					"You should enable IPv6 support to use IPv6 family address for SourceIP '%s'.", CONFIG_SOURCE_IP);
				goto out;
			}
		}
#endif
	}

#ifdef HAVE_IPV6
	if (-1 == access(CONFIG_FPING6_LOCATION, X_OK))
	{
		if (0 == (fping_existence & FPING_EXISTS))
		{
			zbx_snprintf(error, max_error_len, "At least one of '%s', '%s' must exist. Both are missing in the system.",
					CONFIG_FPING_LOCATION,
					CONFIG_FPING6_LOCATION);
			goto out;
		}
	}
	else
		fping_existence |= FPING6_EXISTS;
#endif	/* HAVE_IPV6 */

	offset = zbx_snprintf(params, sizeof(params), "-C%d", count);
	if (0 != interval)
		offset += zbx_snprintf(params + offset, sizeof(params) - offset, " -p%d", interval);
	if (0 != size)
		offset += zbx_snprintf(params + offset, sizeof(params) - offset, " -b%d", size);
	if (0 != timeout)
		offset += zbx_snprintf(params + offset, sizeof(params) - offset, " -t%d", timeout);

#ifdef HAVE_IPV6
	strscpy(params6, params);
	offset6 = offset;

	if (0 != (fping_existence & FPING_EXISTS) && 0 != hosts_count)
	{
		if (FPING_UNINITIALIZED_VALUE == packet_interval)
		{
			if (SUCCEED != get_interval_option(CONFIG_FPING_LOCATION, hosts, hosts_count, &packet_interval,
					error, max_error_len))
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
			if (SUCCEED != get_interval_option(CONFIG_FPING6_LOCATION, hosts, hosts_count,
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
			if (SUCCEED != get_interval_option(CONFIG_FPING_LOCATION, hosts, hosts_count, &packet_interval,
					error, max_error_len))
			{
				goto out;
			}

			zabbix_log(LOG_LEVEL_DEBUG, "detected minimum supported fping interval (-i): %d",
					packet_interval);
		}

		offset += zbx_snprintf(params + offset, sizeof(params) - offset, " -i%d", packet_interval);
	}
#endif	/* HAVE_IPV6 */

	if (NULL != CONFIG_SOURCE_IP)
	{
#ifdef HAVE_IPV6
		if (0 != (fping_existence & FPING_EXISTS))
		{
			if (0 == source_ip_checked)
			{
				get_source_ip_option(CONFIG_FPING_LOCATION, &source_ip_option, &source_ip_checked);

				zabbix_log(LOG_LEVEL_DEBUG, "detected fping source IP option: \"%s\"",
						ZBX_NULL2EMPTY_STR(source_ip_option));
			}

			if (NULL != source_ip_option)
				zbx_snprintf(params + offset, sizeof(params) - offset,
						" %s%s", source_ip_option, CONFIG_SOURCE_IP);
		}

		if (0 != (fping_existence & FPING6_EXISTS))
		{
			if (0 == source_ip6_checked)
			{
				get_source_ip_option(CONFIG_FPING6_LOCATION, &source_ip6_option, &source_ip6_checked);

				zabbix_log(LOG_LEVEL_DEBUG, "detected fping6 source IP option: \"%s\"",
						ZBX_NULL2EMPTY_STR(source_ip6_option));
			}

			if (NULL != source_ip6_option)
				zbx_snprintf(params6 + offset6, sizeof(params6) - offset6,
						" %s%s", source_ip6_option, CONFIG_SOURCE_IP);
		}
#else
		if (0 == source_ip_checked)
		{
			get_source_ip_option(CONFIG_FPING_LOCATION, &source_ip_option, &source_ip_checked);

			zabbix_log(LOG_LEVEL_DEBUG, "detected fping source IP option: \"%s\"",
					ZBX_NULL2EMPTY_STR(source_ip_option));
		}

		if (NULL != source_ip_option)
			zbx_snprintf(params + offset, sizeof(params) - offset,
					" %s%s", source_ip_option, CONFIG_SOURCE_IP);
#endif	/* HAVE_IPV6 */
	}

	zbx_snprintf(filename, sizeof(filename), "%s/%s_%li.pinger", CONFIG_TMPDIR, progname, zbx_get_thread_id());

#ifdef HAVE_IPV6
	if (NULL != CONFIG_SOURCE_IP)
	{
		if (SUCCEED != get_address_family(CONFIG_SOURCE_IP, &family, error, (int)max_error_len))
			goto out;

		if (family == PF_INET)
		{
			if (0 == (fping_existence & FPING_EXISTS))
			{
				zbx_snprintf(error, max_error_len, "File '%s' cannot be found in the system.",
						CONFIG_FPING_LOCATION);
				goto out;
			}

			zbx_snprintf(tmp, tmp_size, "%s %s 2>&1 <%s", CONFIG_FPING_LOCATION, params, filename);
		}
		else
		{
			if (0 == (fping_existence & FPING6_EXISTS))
			{
				zbx_snprintf(error, max_error_len, "File '%s' cannot be found in the system.",
						CONFIG_FPING6_LOCATION);
				goto out;
			}

			zbx_snprintf(tmp, tmp_size, "%s %s 2>&1 <%s", CONFIG_FPING6_LOCATION, params6, filename);
		}
	}
	else
	{
		offset = 0;

		if (0 != (fping_existence & FPING_EXISTS))
		{
			if (FPING_UNINITIALIZED_VALUE == fping_ipv6_supported)
			{
				fping_ipv6_supported = get_ipv6_support(CONFIG_FPING_LOCATION,hosts[0].addr);

				zabbix_log(LOG_LEVEL_DEBUG, "detected fping IPv6 support: \"%s\"",
						SUCCEED == fping_ipv6_supported ? "yes" : "no");
			}

			offset += zbx_snprintf(tmp + offset, tmp_size - offset,
					"%s %s 2>&1 <%s;", CONFIG_FPING_LOCATION, params, filename);
		}

		if (0 != (fping_existence & FPING6_EXISTS) && SUCCEED != fping_ipv6_supported)
		{
			zbx_snprintf(tmp + offset, tmp_size - offset,
					"%s %s 2>&1 <%s;", CONFIG_FPING6_LOCATION, params6, filename);
		}
	}
#else
	zbx_snprintf(tmp, tmp_size, "%s %s 2>&1 <%s", CONFIG_FPING_LOCATION, params, filename);
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

	zabbix_log(LOG_LEVEL_DEBUG, "%s", tmp);

	sigemptyset(&mask);
	sigaddset(&mask, SIGINT);
	sigaddset(&mask, SIGQUIT);

	if (0 > sigprocmask(SIG_BLOCK, &mask, &orig_mask))
		zbx_error("cannot set sigprocmask to block the user signal");

	if (NULL == (f = popen(tmp, "r")))
	{
		zbx_snprintf(error, max_error_len, "%s: %s", tmp, zbx_strerror(errno));

		unlink(filename);

		if (0 > sigprocmask(SIG_SETMASK, &orig_mask, NULL))
			zbx_error("cannot restore sigprocmask");

		goto out;
	}

	if (NULL == zbx_fgets(tmp, (int)tmp_size, f))
	{
		zbx_snprintf(tmp, tmp_size, "no output");
	}
	else
	{
		for (i = 0; i < hosts_count; i++)
		{
			hosts[i].status = (char *)zbx_malloc(NULL, (size_t)count);
			memset(hosts[i].status, 0, (size_t)count);
		}

		do
		{
			ZBX_FPING_HOST	*host = NULL;
			char		*c;

			zbx_rtrim(tmp, "\n");
			zabbix_log(LOG_LEVEL_DEBUG, "read line [%s]", tmp);

			if (NULL != (c = strchr(tmp, ' ')))
			{
				*c = '\0';

				for (i = 0; i < hosts_count; i++)
				{
					if (0 == strcmp(tmp, hosts[i].addr))
					{
						host = &hosts[i];
						break;
					}
				}

				*c = ' ';
			}

			if (NULL == host)
				continue;

			if (NULL == (c = strstr(tmp, " : ")))
				continue;

			/* when NIC bonding is used, there are also lines like */
			/* 192.168.1.2 : duplicate for [0], 96 bytes, 0.19 ms */

			if (NULL != strstr(tmp, "duplicate for"))
				continue;

			c += 3;

			/* There were two issues with processing only the fping's final status line: */
			/*   1) pinging broadcast addresses could have resulted in responses from    */
			/*      different hosts, which were counted as the target host responses;    */
			/*   2) there is a bug in fping (v3.8 at least) where pinging broadcast      */
			/*      address will result in no individual responses, but the final        */
			/*      status line might contain a bogus value.                             */
			/* Because of the above issues we must monitor the individual responses      */
			/* and mark the valid ones.                                                  */
			if ('[' == *c)
			{
				/* Fping appends response source address in format '[<- 10.3.0.10]' */
				/* if it does not match the target address. Ignore such responses.  */
				if (NULL != strstr(c + 1, "[<-"))
					continue;

				/* get the index of individual ping response */
				index = atoi(c + 1);

				if (0 > index || index >= count)
					continue;

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

				if (NULL != strstr(c + 2, " timed out "))
					continue;

				host->status[index] = 1;

				continue;
			}

			/* process status line for a host */
			index = 0;
			do
			{
				if (1 == host->status[index])
				{
					sec = atof(c) / 1000; /* convert ms to seconds */

					if (0 == host->rcv || host->min > sec)
						host->min = sec;
					if (0 == host->rcv || host->max < sec)
						host->max = sec;
					host->sum += sec;
					host->rcv++;
				}
			}
			while (++index < count && NULL != (c = strchr(c + 1, ' ')));

			host->cnt += count;
#ifdef HAVE_IPV6
			if (host->cnt == count && NULL == CONFIG_SOURCE_IP &&
					0 != (fping_existence & FPING_EXISTS) &&
					0 != (fping_existence & FPING6_EXISTS))
			{
				memset(host->status, 0, (size_t)count);	/* reset response statuses for IPv6 */
			}
#endif
			ret = SUCCEED;
		}
		while (NULL != zbx_fgets(tmp, (int)tmp_size, f));

		for (i = 0; i < hosts_count; i++)
			zbx_free(hosts[i].status);
	}
	rc = pclose(f);

	if (0 > sigprocmask(SIG_SETMASK, &orig_mask, NULL))
		zbx_error("cannot restore sigprocmask");

	unlink(filename);

	if (WIFSIGNALED(rc))
		ret = FAIL;
	else
		zbx_snprintf(error, max_error_len, "fping failed: %s", tmp);
out:
	zbx_free(tmp);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: ping hosts listed in the host files                               *
 *                                                                            *
 *             hosts_count   - [IN]  number of target hosts                   *
 *             count         - [IN]  number of pings to send to each target   *
 *                                   (fping option -C)                        *
 *             period        - [IN]  interval between ping packets to one     *
 *                                   target, in milliseconds                  *
 *                                   (fping option -p)                        *
 *             size          - [IN]  amount of ping data to send, in bytes    *
 *                                   (fping option -b)                        *
 *             timeout       - [IN]  individual target initial timeout except *
 *                                   when count > 1, where it's the -p period *
 *                                   (fping option -t)                        *
 *             error         - [OUT] error string if function fails           *
 *             max_error_len - [IN]  length of error buffer                   *
 *                                                                            *
 * Return value: SUCCEED - successfully processed hosts                       *
 *               NOTSUPPORTED - otherwise                                     *
 *                                                                            *
 * Comments: use external binary 'fping' to avoid superuser privileges        *
 *                                                                            *
 ******************************************************************************/
int	zbx_ping(ZBX_FPING_HOST *hosts, int hosts_count, int count, int period, int size, int timeout,
		char *error, size_t max_error_len)
{
	int	ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() hosts_count:%d", __func__, hosts_count);

	if (NOTSUPPORTED == (ret = process_ping(hosts, hosts_count, count, period, size, timeout, error, max_error_len)))
		zabbix_log(LOG_LEVEL_ERR, "%s", error);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __func__, zbx_result_string(ret));

	return ret;
}
