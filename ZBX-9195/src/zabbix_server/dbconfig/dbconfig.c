/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#include "dbconfig.h"
#include "dbcache.h"

extern int		CONFIG_CONFSYNCER_FREQUENCY;
extern unsigned char	process_type;

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
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: never returns                                                    *
 *                                                                            *
 ******************************************************************************/
void	main_dbconfig_loop()
{
	double	sec;

	zabbix_log(LOG_LEVEL_DEBUG, "In main_dbconfig_loop()");

	/* the initial configuration sync is done by server before worker processes are forked */
	zbx_sleep_loop(CONFIG_CONFSYNCER_FREQUENCY);

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;)
	{
		zbx_setproctitle("%s [syncing configuration]", get_process_type_string(process_type));

		zabbix_log(LOG_LEVEL_DEBUG, "Syncing ...");

		sec = zbx_time();
		DCsync_configuration();
		sec = zbx_time() - sec;

		zabbix_log(LOG_LEVEL_DEBUG, "%s spent " ZBX_FS_DBL " second while processing configuration data",
				get_process_type_string(process_type), sec);

		zbx_sleep_loop(CONFIG_CONFSYNCER_FREQUENCY);
	}
}
