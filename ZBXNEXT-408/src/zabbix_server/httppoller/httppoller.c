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

#include "common.h"

#include "db.h"
#include "log.h"
#include "daemon.h"
#include "zbxself.h"

#include "httptest.h"
#include "httppoller.h"

extern int	process_num;

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
			" from httptest t,applications a,hosts h"
			" where t.applicationid=a.applicationid"
				" and a.hostid=h.hostid"
				" and " ZBX_SQL_MOD(t.httptestid,%d) "=%d"
				" and t.status=%d"
				" and h.status=%d"
				" and (h.maintenance_status=%d or h.maintenance_type=%d)"
				DB_NODE,
			CONFIG_HTTPPOLLER_FORKS, process_num - 1,
			HTTPTEST_STATUS_MONITORED,
			HOST_STATUS_MONITORED,
			HOST_MAINTENANCE_STATUS_OFF, MAINTENANCE_TYPE_NORMAL,
			DBnode_local("t.httptestid"));

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
void	main_httppoller_loop()
{
	int	now, nextcheck, sleeptime;
	double	sec;

	zabbix_log(LOG_LEVEL_DEBUG, "In main_httppoller_loop() process_num:%d", process_num);

	set_child_signal_handler();

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;)
	{
		zbx_setproctitle("http poller [getting values]");

		now = time(NULL);
		sec = zbx_time();
		process_httptests(process_num, now);
		sec = zbx_time() - sec;

		if (FAIL == (nextcheck = get_minnextcheck(now)))
			sleeptime = POLLER_DELAY;
		else
		{
			sleeptime = nextcheck - time(NULL);
			if (sleeptime < 0)
				sleeptime = 0;
			else if (sleeptime > POLLER_DELAY)
				sleeptime = POLLER_DELAY;
		}

		zabbix_log(LOG_LEVEL_DEBUG, "HTTP poller #%d spent " ZBX_FS_DBL " seconds while updating HTTP tests"
				" Sleeping for %d seconds",
				process_num, sec, sleeptime);

		if (sleeptime > 0)
		{
			zbx_setproctitle("http poller [sleeping for %d seconds]", sleeptime);

			update_sm_counter(ZBX_PROCESS_STATE_IDLE);
			sleep(sleeptime);
			update_sm_counter(ZBX_PROCESS_STATE_BUSY);
		}
	}
}
