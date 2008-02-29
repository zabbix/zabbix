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
#include "zlog.h"

#include "dbcache.h"
#include "dbsyncer.h"

/******************************************************************************
 *                                                                            *
 * Function: main_dbsyncer_loop                                               *
 *                                                                            *
 * Purpose: periodically syncronises data in memory cache with database       *
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
int main_dbsyncer_loop()
{
	int	now;
	double	sec;

	zbx_setproctitle("db syncer [connecting to the database]");

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;) {
		zabbix_log(LOG_LEVEL_DEBUG, "Syncing ...");

		now = time(NULL);
		sec = zbx_time();

		DCsync();

		zabbix_log(LOG_LEVEL_DEBUG, "Spent " ZBX_FS_DBL " sec",
				zbx_time() - sec);

		zbx_setproctitle("db syncer [sleeping for %d seconds]",
				CONFIG_DBSYNCER_FREQUENCY);
		zabbix_log(LOG_LEVEL_DEBUG, "Sleeping for %d seconds",
				CONFIG_DBSYNCER_FREQUENCY);

		sleep(CONFIG_DBSYNCER_FREQUENCY);
	}
	DBclose();
}
