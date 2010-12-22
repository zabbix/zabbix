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
#include "threads.h"

#include "dbconfig.h"
#include "dbcache.h"

/******************************************************************************
 *                                                                            *
 * Function: main_dbconfig_loop                                               *
 *                                                                            *
 * Purpose: periodically synchronises database data with memory cache         *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments: never returns                                                    *
 *                                                                            *
 ******************************************************************************/
int	main_dbconfig_loop()
{
	double	sec;

	zabbix_log(LOG_LEVEL_DEBUG, "In main_dbconfig_loop()");

	zbx_setproctitle("db config [connecting to the database]");

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;) {
		zabbix_log(LOG_LEVEL_DEBUG, "Syncing ...");

		sec = zbx_time();
		DCsync_configuration();
		sec = zbx_time() - sec;

		zabbix_log(LOG_LEVEL_DEBUG, "DB config spent " ZBX_FS_DBL " second while processing configuration data. "
				"Nextsync after %d sec.",
				sec,
				CONFIG_DBCONFIG_FREQUENCY);

		zbx_setproctitle("db config [sleeping for %d seconds]",
				CONFIG_DBCONFIG_FREQUENCY);

		sleep(CONFIG_DBCONFIG_FREQUENCY);
	}
	DBclose();
}
