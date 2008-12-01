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
#include "log.h"
#include "zlog.h"

extern char	*CONFIG_SOURCE_IP;
extern char	*CONFIG_FPING_LOCATION;
#ifdef HAVE_IPV6
extern char	*CONFIG_FPING6_LOCATION;
#endif /* HAVE_IPV6 */
extern char	*CONFIG_TMPDIR;

#ifdef HAVE_IPV6
static int	get_address_family(const char *addr, int *family, char *error, int max_error_len)
{
	struct	addrinfo hints, *ai = NULL;
	int	err, res = NOTSUPPORTED;

	memset(&hints, 0, sizeof(hints));
	hints.ai_family = PF_UNSPEC;
	hints.ai_flags = AI_NUMERICHOST;
	hints.ai_socktype = SOCK_STREAM;

	if (0 != (err = getaddrinfo(addr, NULL, &hints, &ai)))
	{
		zbx_snprintf(error, max_error_len, "%s: [%d] %s", addr, err, gai_strerror(err));
		goto out;
	}

	if (ai->ai_family != PF_INET && ai->ai_family != PF_INET6)
	{
		zbx_snprintf(error, max_error_len, "%s: Unsupported address family", addr);
		goto out;
	}

	*family = (int)ai->ai_family;

	res = SUCCEED;
out:
	if (NULL != ai)
		freeaddrinfo(ai);

	return res;
}
#endif /* HAVE_IPV6 */

static int	process_ping(ZBX_FPING_HOST *hosts, int hosts_count, char *error, int max_error_len)
{
	FILE		*f;
	char		filename[MAX_STRING_LEN], tmp[MAX_STRING_LEN],
			*c, source_ip[64];
	int		i;
	ZBX_FPING_HOST	*host;
#ifdef HAVE_IPV6
	char		*fping;
	int		family;
#endif

	assert(hosts);

	zabbix_log(LOG_LEVEL_DEBUG, "In process_ping()");

	if (NULL != CONFIG_SOURCE_IP)
		zbx_snprintf(source_ip, sizeof(source_ip), "-S%s ", CONFIG_SOURCE_IP);
	else
		*source_ip = '\0';

	if (access(CONFIG_FPING_LOCATION, F_OK|X_OK) == -1)
	{
		zbx_snprintf(error, max_error_len, "%s: [%d] %s", CONFIG_FPING_LOCATION, errno, strerror(errno));
		return NOTSUPPORTED;
	}

	zbx_snprintf(filename, sizeof(filename), "%s/zabbix_server_%li.pinger",
			CONFIG_TMPDIR,
			zbx_get_thread_id());

#ifdef HAVE_IPV6
	if (access(CONFIG_FPING6_LOCATION, F_OK|X_OK) == -1)
	{
		zbx_snprintf(error, max_error_len, "%s: [%d] %s", CONFIG_FPING6_LOCATION, errno, strerror(errno));
		return NOTSUPPORTED;
	}

	if (NULL != CONFIG_SOURCE_IP)
	{
		if (NOTSUPPORTED == get_address_family(CONFIG_SOURCE_IP, &family, error, max_error_len))
			return NOTSUPPORTED;

		if (family == PF_INET)
			fping = CONFIG_FPING_LOCATION;
		else
			fping = CONFIG_FPING6_LOCATION;

		zbx_snprintf(tmp, sizeof(tmp), "%s %s-c3 2>/dev/null <%s",
				fping,
				source_ip,
				filename);
	}
	else
		zbx_snprintf(tmp, sizeof(tmp), "%s -c3 2>/dev/null <%s;%s -c3 2>/dev/null <%s",
				CONFIG_FPING_LOCATION,
				filename,
				CONFIG_FPING6_LOCATION,
				filename);
#else /* HAVE_IPV6 */
	zbx_snprintf(tmp, sizeof(tmp), "%s %s-c3 2>/dev/null <%s",
			CONFIG_FPING_LOCATION,
			source_ip,
			filename);
#endif /* HAVE_IPV6 */

	if (NULL == (f = fopen(filename, "w"))) {
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

	if (0 == (f = popen(tmp, "r"))) {
		zbx_snprintf(error, max_error_len, "%s: [%d] %s", tmp, errno, strerror(errno));

		unlink(filename);

		return NOTSUPPORTED;
	}

	while (NULL != fgets(tmp, sizeof(tmp), f)) {
		zbx_rtrim(tmp, "\n");
		zabbix_log(LOG_LEVEL_DEBUG, "Update IP [%s]",
				tmp);

		/* 12fc::21 : [0], 76 bytes, 0.39 ms (0.39 avg, 0% loss) */

		host = NULL;

		if (NULL != (c = strchr(tmp, ' '))) {
			*c = '\0';
			for (i = 0; i < hosts_count; i++)
				if (0 == strcmp(tmp, hosts[i].addr)) {
					host = &hosts[i];
					break;
				}
		}

		if (NULL != host) {
			c++;
			if (NULL != (c = strchr(c, '('))) {
				c++;
				host->alive = 1;
				host->sec = atof(c)/1000;
			}
		}
	}
	pclose(f);

	unlink(filename);

	zabbix_log(LOG_LEVEL_DEBUG, "End of process_ping()");

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
 * Comments: use external binary 'fping' to avoid superuser priviledges       *
 *                                                                            *
 ******************************************************************************/
int	do_ping(ZBX_FPING_HOST *hosts, int hosts_count, char *error, int max_error_len)
{
	int res;

	zabbix_log(LOG_LEVEL_DEBUG, "In do_ping(hosts_count:%d)",
			hosts_count);

	if (NOTSUPPORTED == (res = process_ping(hosts, hosts_count, error, max_error_len)))
	{
		zabbix_log(LOG_LEVEL_ERR, "%s", error);
		zabbix_syslog("%s", error);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of do_ping():%s",
			zbx_result_string(res));

	return res;
}
