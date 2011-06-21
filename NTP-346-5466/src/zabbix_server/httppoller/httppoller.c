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

#include "cfg.h"
#include "pid.h"
#include "db.h"
#include "log.h"
#include "zlog.h"

#include "../functions.h"
#include "../expression.h"
#include "httptest.h"
#include "httppoller.h"

#include "daemon.h"

int	httppoller_num;

/******************************************************************************
 *                                                                            *
 * Function: get_minnextcheck                                                 *
 *                                                                            *
 * Purpose: calculate when we have to process earliest httptest               *
 *                                                                            *
 * Parameters: now - current timestamp                                        *
 *                                                                            *
 * Return value: timestamp of earliest check or -1 if not found               *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: never returns                                                    *
 *                                                                            *
 ******************************************************************************/
static int get_minnextcheck(int now)
{
	DB_RESULT	result;
	DB_ROW		row;

	int		res;

	result = DBselect("select count(*),min(nextcheck) from httptest t where t.status=%d and " ZBX_SQL_MOD(t.httptestid,%d) "=%d and " ZBX_COND_NODEID,
		HTTPTEST_STATUS_MONITORED,
		CONFIG_HTTPPOLLER_FORKS,
		httppoller_num-1,
		LOCAL_NODE("t.httptestid"));

	row=DBfetch(result);

	if(!row || DBis_null(row[0])==SUCCEED || DBis_null(row[1])==SUCCEED)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "No httptests to process in get_minnextcheck.");
		res = FAIL; 
	}
	else
	{
		if( atoi(row[0]) == 0)
		{
			res = FAIL;
		}
		else
		{
			res = atoi(row[1]);
		}
	}
	DBfree_result(result);

	return	res;
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
void main_httppoller_loop(int num)
{
	int	now;
	int	nextcheck,sleeptime;

	zabbix_log( LOG_LEVEL_DEBUG, "In main_httppoller_loop(num:%d)",
		num);

	httppoller_num = num;

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for(;;)
	{
		zbx_setproctitle("http poller [getting values]");

		now=time(NULL);
		process_httptests(now);

		zabbix_log( LOG_LEVEL_DEBUG, "Spent %d seconds while processing HTTP tests",
			(int)time(NULL)-now);

		nextcheck=get_minnextcheck(now);
		zabbix_log( LOG_LEVEL_DEBUG, "Nextcheck:%d Time:%d",
			nextcheck,
			(int)time(NULL));

		if( FAIL == nextcheck)
		{
			sleeptime=POLLER_DELAY;
		}
		else
		{
			sleeptime=nextcheck-time(NULL);
			if(sleeptime<0)
			{
				sleeptime=0;
			}
		}
		if(sleeptime>0)
		{
			if(sleeptime > POLLER_DELAY)
			{
				sleeptime = POLLER_DELAY;
			}
			zabbix_log( LOG_LEVEL_DEBUG, "Sleeping for %d seconds",
					sleeptime );

			zbx_setproctitle("http poller [sleeping for %d seconds]", 
					sleeptime);

			sleep( sleeptime );
		}
		else
		{
			zabbix_log( LOG_LEVEL_DEBUG, "No sleeping" );
		}

#ifdef ZABBIX_TEST
		break;
#endif /* ZABBIX_TEST */
	}
}
