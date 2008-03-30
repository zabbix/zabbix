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

extern	char	*CONFIG_FPING_LOCATION;
#ifdef HAVE_IPV6
extern	char	*CONFIG_FPING6_LOCATION;
#endif /* HAVE_IPV6 */

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
int do_ping(ZBX_FPING_HOST *hosts, int hosts_count)
{
	FILE		*f;
	char		filename[MAX_STRING_LEN];
	char		tmp[MAX_STRING_LEN];
	int		i;
	char		*c;
	ZBX_FPING_HOST	*host;

	zabbix_log(LOG_LEVEL_DEBUG, "In do_ping() [hosts_count:%d]",
			hosts_count);

	zbx_snprintf(filename, sizeof(filename), "/tmp/zabbix_server_%li.pinger",
			zbx_get_thread_id());

	if (NULL == (f = fopen(filename, "w"))) {
		zabbix_log(LOG_LEVEL_ERR, "Cannot open file [%s] [%s]",
				filename,
				strerror(errno));
		zabbix_syslog("Cannot open file [%s] [%s]",
				filename,
				strerror(errno));
		return FAIL;
	}

	for (i = 0; i < hosts_count; i++)
		fprintf(f, "%s\n", hosts[i].addr);

	fclose(f);

#ifdef HAVE_IPV6
	zbx_snprintf(tmp, sizeof(tmp), "%s -c3 2>/dev/null <%s;%s -c3 2>/dev/null <%s",
			CONFIG_FPING_LOCATION,
			filename,
			CONFIG_FPING6_LOCATION,
			filename);
#else /* HAVE_IPV6 */
	zbx_snprintf(tmp, sizeof(tmp), "%s -c3 2>/dev/null <%s",
			CONFIG_FPING_LOCATION,
			filename);
#endif /* HAVE_IPV6 */

	if (0 == (f = popen(tmp, "r"))) {
		zabbix_log(LOG_LEVEL_ERR, "Cannot execute [%s] [%s]",
				CONFIG_FPING_LOCATION,
				strerror(errno));
		zabbix_syslog("Cannot execute [%s] [%s]",
				CONFIG_FPING_LOCATION,
				strerror(errno));
		return FAIL;
	}

	while (NULL != fgets(tmp, sizeof(tmp), f)) {
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

	zabbix_log(LOG_LEVEL_DEBUG, "End of do_ping()");

	return SUCCEED;
}

