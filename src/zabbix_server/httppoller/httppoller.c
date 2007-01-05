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

#include "config.h"

#include <stdio.h>
#include <stdlib.h>
#include <sys/stat.h>

#include <string.h>


/* Required for getpwuid */
#include <pwd.h>

#include <signal.h>
#include <errno.h>

#include <time.h>
/* getopt() */
#include <unistd.h>

#include "cfg.h"
#include "pid.h"
#include "db.h"
#include "log.h"
#include "zlog.h"

#include "common.h"
#include "../functions.h"
#include "../expression.h"
#include "httppoller.h"

#include "daemon.h"

int	httppoller_num;

static int get_minnextcheck(int now)
{
	DB_RESULT	result;
	DB_ROW		row;

	int		res;

	result = DBselect("select count(*),min(nextcheck) from httptest h where h.status=%d and " ZBX_SQL_MOD(h.httptestid,%d) "=%d and " ZBX_COND_NODEID, HTTPTEST_STATUS_MONITORED, CONFIG_HTTPPOLLER_FORKS, httppoller_num-1, LOCAL_NODE("h.hostid"));

	row=DBfetch(result);

	if(!row || DBis_null(row[0])==SUCCEED || DBis_null(row[1])==SUCCEED)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "No items to update for minnextcheck.");
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

void process_httptests()
{
	sleep(1);
}

void main_httppoller_loop(int num)
{
	int	now;
	int	nextcheck,sleeptime;

	zabbix_log( LOG_LEVEL_WARNING, "In main_httppoller_loop(num:%d)", num);

	httppoller_num = num;

	DBconnect();

	for(;;)
	{
		zbx_setproctitle("http poller [getting values]");

		now=time(NULL);
		process_httptests();

		zabbix_log( LOG_LEVEL_WARNING, "Spent %d seconds while processing HTTP tests", (int)time(NULL)-now );

		nextcheck=get_minnextcheck(now);
		zabbix_log( LOG_LEVEL_WARNING, "Nextcheck:%d Time:%d", nextcheck, (int)time(NULL) );

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
	}

}
