/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

#include "db.h"
#include "log.h"
#include "daemon.h"
#include "zbxself.h"

#include "httptest.h"
#include "httppoller.h"

extern int		CONFIG_HTTPPOLLER_FORKS;
extern unsigned char	process_type, program_type;
extern int		server_num, process_num;

/******************************************************************************
 *                                                                            *
 * Function: get_minnextcheck                                                 *
 *                                                                            *
 * Purpose: calculate when we have to process earliest httptest               *
 *                                                                            *
 * Parameters: now - current timestamp (not used)                             *
 *                                                                            *
 * Return value: timestamp of earliest check or -1 if not found               *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	get_minnextcheck(int now)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		res;

	result = DBselect(
			"select min(t.nextcheck)"
			" from httptest t,hosts h"
			" where t.hostid=h.hostid"
				" and " ZBX_SQL_MOD(t.httptestid,%d) "=%d"
				" and t.status=%d"
				" and h.proxy_hostid is null"
				" and h.status=%d"
				" and (h.maintenance_status=%d or h.maintenance_type=%d)",
			CONFIG_HTTPPOLLER_FORKS, process_num - 1,
			HTTPTEST_STATUS_MONITORED,
			HOST_STATUS_MONITORED,
			HOST_MAINTENANCE_STATUS_OFF, MAINTENANCE_TYPE_NORMAL);

	if (NULL == (row = DBfetch(result)) || SUCCEED == DBis_null(row[0]))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "No httptests to process in get_minnextcheck.");
		res = FAIL;
	}
	else
		res = atoi(row[0]);

	DBfree_result(result);

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: main_httppoller_loop                                             *
 *                                                                            *
 * Purpose: main loop of processing of httptests                              *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: never returns                                                    *
 *                                                                            *
 ******************************************************************************/
ZBX_THREAD_ENTRY(httppoller_thread, args)
{
	int	now, nextcheck, sleeptime = -1, httptests_count = 0, old_httptests_count = 0;
	double	sec, total_sec = 0.0, old_total_sec = 0.0;
	time_t	last_stat_time;

	process_type = ((zbx_thread_args_t *)args)->process_type;
	server_num = ((zbx_thread_args_t *)args)->server_num;
	process_num = ((zbx_thread_args_t *)args)->process_num;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(program_type),
			server_num, get_process_type_string(process_type), process_num);

#define STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	zbx_setproctitle("%s #%d [connecting to the database]", get_process_type_string(process_type), process_num);
	last_stat_time = time(NULL);

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;)
	{
		zbx_handle_log();

		if (0 != sleeptime)
		{
			zbx_setproctitle("%s #%d [got %d values in " ZBX_FS_DBL " sec, getting values]",
					get_process_type_string(process_type), process_num, old_httptests_count,
					old_total_sec);
		}

		now = time(NULL);
		sec = zbx_time();
		httptests_count += process_httptests(process_num, now);
		total_sec += zbx_time() - sec;

		nextcheck = get_minnextcheck(now);
		sleeptime = calculate_sleeptime(nextcheck, POLLER_DELAY);

		if (0 != sleeptime || STAT_INTERVAL <= time(NULL) - last_stat_time)
		{
			if (0 == sleeptime)
			{
				zbx_setproctitle("%s #%d [got %d values in " ZBX_FS_DBL " sec, getting values]",
						get_process_type_string(process_type), process_num, httptests_count,
						total_sec);
			}
			else
			{
				zbx_setproctitle("%s #%d [got %d values in " ZBX_FS_DBL " sec, idle %d sec]",
						get_process_type_string(process_type), process_num, httptests_count,
						total_sec, sleeptime);
				old_httptests_count = httptests_count;
				old_total_sec = total_sec;
			}
			httptests_count = 0;
			total_sec = 0.0;
			last_stat_time = time(NULL);
		}

		zbx_sleep_loop(sleeptime);
	}

#undef STAT_INTERVAL
}
