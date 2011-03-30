/*
** ZABBIX
** Copyright (C) 2000-2006 SIA Zabbix
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
#include "db.h"
#include "log.h"
#include "zlog.h"

#include "nodewatcher.h"
#include "nodesender.h"
#include "history.h"

/******************************************************************************
 *                                                                            *
 * Function: is_master_node                                                   *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:  SUCCEED - nodeid is master node                             *
 *                FAIL - nodeid is slave node                                 *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	is_master_node(int current_nodeid, int nodeid)
{
	DB_RESULT	dbresult;
	DB_ROW		dbrow;
	int		res = FAIL;

	dbresult = DBselect("select masterid from nodes where nodeid=%d",
		current_nodeid);

	if (NULL != (dbrow = DBfetch(dbresult))) {
		current_nodeid = atoi(dbrow[0]);
		if (current_nodeid == nodeid)
			res = SUCCEED;
		else if (0 != current_nodeid)
			res = is_master_node(current_nodeid, nodeid);
	}
	DBfree_result(dbresult);

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: main_nodewatcher_loop                                            *
 *                                                                            *
 * Purpose: periodically calculates checksum of config data                   *
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
int main_nodewatcher_loop()
{
	int start, end;
	int	lastrun = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In main_nodeupdater_loop()");

	zbx_setproctitle("connecting to the database");

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for(;;)
	{
		start = time(NULL);

		zabbix_log( LOG_LEVEL_DEBUG, "Starting sync with nodes");

		if(lastrun + 120 < start)
		{
			process_nodes();

			lastrun = start;
		}

		/* Send new history data to master node */
		main_historysender();

		end = time(NULL);

		if(end-start<10)
		{
			zbx_setproctitle("sender [sleeping for %d seconds]",
				10-(end-start));
			zabbix_log( LOG_LEVEL_DEBUG, "Sleeping %d seconds",
				10-(end-start));
			sleep(10-(end-start));
		}
	}

	DBclose();
}
