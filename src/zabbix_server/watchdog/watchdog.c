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
#include <unistd.h>
#include <sys/stat.h>

#include <string.h>


/* Required for getpwuid */
#include <pwd.h>

#include <signal.h>
#include <errno.h>

#include <time.h>

#include "cfg.h"
#include "pid.h"
#include "db.h"
#include "log.h"
#include "zlog.h"

#include "common.h"
#include "watchdog.h"

/******************************************************************************
 *                                                                            *
 * Function: main_watchdog_loop                                               *
 *                                                                            *
 * Purpose: periodically checks availability of database                      *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: check database availability every 60 seconds (hardcoded)         *
 *                                                                            *
 ******************************************************************************/
void main_watchdog_loop()
{
	for(;;)
	{
/*		zbx_setproctitle("updating nodata() functions");

		DBconnect();

		now=time(NULL);

		result = DBselect("select distinct %s, functions f where h.hostid=i.hostid and h.status=%d and i.status=%d and f.function in ('nodata','date','dayofweek','time','now') and i.itemid=f.itemid and" ZBX_COND_NODEID, ZBX_SQL_ITEM_SELECT, HOST_STATUS_MONITORED, ITEM_STATUS_ACTIVE, LOCAL_NODE("h.hostid"));

		while((row=DBfetch(result)))
		{
			DBget_item_from_db(&item,row);

			DBbegin();
			update_functions(&item);
			update_triggers(item.itemid);
			DBcommit();
		}

		DBfree_result(result);
		DBclose();

		zbx_setproctitle("sleeping for 60 sec");*/

		sleep(60);
	}
}
