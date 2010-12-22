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

#include "zbxicmpping.h"
#include "threads.h"
#include "comms.h"
#include "log.h"
#include "zlog.h"

extern char	*CONFIG_SOURCE_IP;
extern char	*CONFIG_FPING_LOCATION;
#ifdef HAVE_IPV6
extern char	*CONFIG_FPING6_LOCATION;
#endif /* HAVE_IPV6 */
extern char	*CONFIG_TMPDIR;

static int	process_ping(ZBX_FPING_HOST *hosts, int hosts_count, int count, int interval, int size, int timeout,
		char *error, int max_error_len)
{
	const char	*__function_name = "process_ping";

	FILE		*f;
	char		filename[MAX_STRING_LEN], tmp[MAX_STRING_LEN],
			*c, *c2, params[64]; /*usually this amount of memory is enough*/
	int		i;
	ZBX_FPING_HOST	*host;
	double		sec;
	
#ifdef HAVE_IPV6
	int		family;
	char		*fping;
	char		fping_fping6_combination = 0;
#define	FPING_EXISTS		0x1
#define	FPING6_EXISTS		0x2
#define	FPING_FPING6_EXIST	0x3

#endif /* #ifdef HAVE_IPV6 */

	assert(hosts);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() hosts_count:%d", __function_name, hosts_count);

	i = zbx_snprintf(params, sizeof(params), "-q -C%d", count);
	if (0 != interval)
		i += zbx_snprintf(params + i, sizeof(params) - i, " -p%d", interval);
	if (0 != size)
		i += zbx_snprintf(params + i, sizeof(params) - i, " -b%d", size);
	if (0 != timeout)
		i += zbx_snprintf(params + i, sizeof(params) - i, " -t%d", timeout);
	if (NULL != CONFIG_SOURCE_IP)
		i += zbx_snprintf(params + i, sizeof(params) - i, " -S%s", CONFIG_SOURCE_IP);

	if (access(CONFIG_FPING_LOCATION, F_OK|X_OK) == -1)
	{
#ifndef HAVE_IPV6
		zbx_snprintf(error, max_error_len, "%s: [%d] %s", CONFIG_FPING_LOCATION, errno, strerror(errno));
		return NOTSUPPORTED;
#endif /* #ifndef HAVE_IPV6 */
	}
	else
	{
#ifdef HAVE_IPV6
		fping_fping6_combination |= FPING_EXISTS;
#else /* #ifdef HAVE_IPV6 */
		if (NULL != CONFIG_SOURCE_IP)
		{
			if (FAIL == is_ip4(CONFIG_SOURCE_IP)) /* we do not have IPv4 family address in CONFIG_SOURCE_IP */
			{
				zbx_snprintf(error, max_error_len, 
					"You should enable IPv6 support to use IPv6 family address for SourceIP '%s'.", CONFIG_SOURCE_IP);
				return NOTSUPPORTED;
			}
		}		
#endif /* #ifdef HAVE_IPV6 */
	}

	zbx_snprintf(filename, sizeof(filename), "%s/zabbix_server_%li.pinger",
			CONFIG_TMPDIR,
			zbx_get_thread_id());

#ifdef HAVE_IPV6
	if (access(CONFIG_FPING6_LOCATION, F_OK|X_OK) == -1)
	{
		if (FPING_EXISTS != (fping_fping6_combination & FPING_EXISTS))
		{
			zbx_snprintf(error, max_error_len, "At least one of '%s', '%s' must exist. Both are missing in the system.",
					CONFIG_FPING_LOCATION,
					CONFIG_FPING6_LOCATION);
			return NOTSUPPORTED;
		}
	}
	else
		fping_fping6_combination |= FPING6_EXISTS;
	
	if (NULL != CONFIG_SOURCE_IP)
	{
		if (SUCCEED != get_address_family(CONFIG_SOURCE_IP, &family, error, max_error_len))
			return NOTSUPPORTED;

		if (family == PF_INET)
		{
			if (FPING_EXISTS != (fping_fping6_combination & FPING_EXISTS))
			{
				zbx_snprintf(error, max_error_len, "File '%s' cannot be found in the system.", 
						CONFIG_FPING_LOCATION);
				return NOTSUPPORTED;
			}
			else
				fping = CONFIG_FPING_LOCATION;
		}
		else
		{
			if (FPING6_EXISTS != (fping_fping6_combination & FPING6_EXISTS))
			{
				zbx_snprintf(error, max_error_len, "File '%s' cannot be found in the system.", 
						CONFIG_FPING6_LOCATION);
				return NOTSUPPORTED;
			}
			else
				fping = CONFIG_FPING6_LOCATION;
		}

		zbx_snprintf(tmp, sizeof(tmp), "%s %s 2>&1 <%s",
				fping,
				params,
				filename);
	}
	else /* CONFIG_SOURCE_IP has no value */	
	{
		if (FPING_FPING6_EXIST == (fping_fping6_combination & FPING_FPING6_EXIST))
		{
			zbx_snprintf(tmp, sizeof(tmp), "%s %s 2>&1 <%s;%s %s 2>&1 <%s",
				CONFIG_FPING_LOCATION,
				params,
				filename,
				CONFIG_FPING6_LOCATION,
				params,
				filename);
		}
		else if (FPING_EXISTS == (fping_fping6_combination & FPING_EXISTS))
		{
			zbx_snprintf(tmp, sizeof(tmp), "%s %s 2>&1 <%s",
				CONFIG_FPING_LOCATION,
				params,
				filename);
		}
		else /* fping6 must surely exist */
		{
			zbx_snprintf(tmp, sizeof(tmp), "%s %s 2>&1 <%s",
				CONFIG_FPING6_LOCATION,
				params,
				filename);
		}
	}
#else /* HAVE_IPV6 */				
	zbx_snprintf(tmp, sizeof(tmp), "%s %s 2>&1 <%s",
			CONFIG_FPING_LOCATION,
			params,
			filename);
#endif /* HAVE_IPV6 */

	if (NULL == (f = fopen(filename, "w")))
	{
		zbx_snprintf(error, max_error_len, "%s: [%d] %s", filename, errno, strerror(errno));
		return NOTSUPPORTED;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s", filename);

	for (i = 0; i < hosts_count; i++)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s", hosts[i].addr);
		fprintf(f, "%s\n", hosts[i].addr);
	}

	fclose(f);

	zabbix_log(LOG_LEVEL_DEBUG, "%s", tmp);

	if (0 == (f = popen(tmp, "r")))
	{
		zbx_snprintf(error, max_error_len, "%s: [%d] %s", tmp, errno, strerror(errno));

		unlink(filename);

		return NOTSUPPORTED;
	}

	while (NULL != fgets(tmp, sizeof(tmp), f))
	{
		zbx_rtrim(tmp, "\n");
		zabbix_log(LOG_LEVEL_DEBUG, "Update IP [%s]",
				tmp);

		/* 12fc::21 : [0], 76 bytes, 0.39 ms (0.39 avg, 0% loss) */

		host = NULL;

		if (NULL != (c = strchr(tmp, ' ')))
		{
			*c = '\0';
			for (i = 0; i < hosts_count; i++)
				if (0 == strcmp(tmp, hosts[i].addr))
				{
					host = &hosts[i];
					break;
				}
			*c = ' ';
		}

		if (NULL == host)
			continue;

		if (NULL == (c = strstr(tmp, " : ")))
			continue;

		c += 3;

		do
		{
			if (NULL != (c2 = strchr(c, ' ')))
				*c2 = '\0';

			if (0 != strcmp(c, "-"))
			{
				/* Convert ms to seconds */
				sec = atof(c) / 1000;

				if (host->rcv == 0 || host->min > sec)
					host->min = sec;
				if (host->rcv == 0 || host->max < sec)
					host->max = sec;
				host->avg = (host->avg * host->rcv + sec) / (host->rcv + 1);
				host->rcv++;
			}

			if (NULL != c2)
				*c2++ = ' ';
		}
		while (NULL != (c = c2));
	}
	pclose(f);

	unlink(filename);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: do_ping                                                          *
 *                                                                            *
 * Purpose: ping hosts listed in the host files                               *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: => 0 - successfully processed items                          *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: use external binary 'fping' to avoid superuser privileges        *
 *                                                                            *
 ******************************************************************************/
int	do_ping(ZBX_FPING_HOST *hosts, int hosts_count, int count, int interval, int size, int timeout, char *error, int max_error_len)
{
	const char	*__function_name = "do_ping";

	int	res;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() hosts_count:%d", __function_name, hosts_count);

	if (NOTSUPPORTED == (res = process_ping(hosts, hosts_count, count, interval, size, timeout, error, max_error_len)))
	{
		zabbix_log(LOG_LEVEL_ERR, "%s", error);
		zabbix_syslog("%s", error);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(res));

	return res;
}
