/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

#include "dbcache.h"
#include "dbsyncer.h"

extern int		CONFIG_HISTSYNCER_FREQUENCY;
extern unsigned char	process_type;
extern int		process_num;

/******************************************************************************
 *                                                                            *
 * Function: main_dbsyncer_loop                                               *
 *                                                                            *
 * Purpose: periodically synchronises data in memory cache with database      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: never returns                                                    *
 *                                                                            *
 ******************************************************************************/
void	main_dbsyncer_loop()
{
	int	sleeptime, last_sleeptime = -1, num;
	double	sec;
	int	retry_up = 0, retry_dn = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In main_dbsyncer_loop() process_num:%d", process_num);

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;)
	{
		zbx_setproctitle("%s [syncing history]", get_process_type_string(process_type));

		zabbix_log(LOG_LEVEL_DEBUG, "Syncing ...");

		sec = zbx_time();
		num = DCsync_history(ZBX_SYNC_PARTIAL);
		sec = zbx_time() - sec;

		zabbix_log(LOG_LEVEL_DEBUG, "%s #%d spent " ZBX_FS_DBL " seconds while processing %d items",
				get_process_type_string(process_type), process_num, sec, num);

		if (-1 == last_sleeptime)
		{
			sleeptime = num ? ZBX_SYNC_MAX / num : CONFIG_HISTSYNCER_FREQUENCY;
		}
		else
		{
			sleeptime = last_sleeptime;
			if (ZBX_SYNC_MAX < num)
			{
				retry_up = 0;
				retry_dn++;
			}
			else if (ZBX_SYNC_MAX / 2 > num)
			{
				retry_up++;
				retry_dn = 0;
			}
			else
				retry_up = retry_dn = 0;

			if (2 < retry_dn)
			{
				sleeptime--;
				retry_dn = 0;
			}

			if (2 < retry_up)
			{
				sleeptime++;
				retry_up = 0;
			}
		}

		if (0 > sleeptime)
			sleeptime = 0;
		else if (CONFIG_HISTSYNCER_FREQUENCY < sleeptime)
			sleeptime = CONFIG_HISTSYNCER_FREQUENCY;

		last_sleeptime = sleeptime;

		zbx_sleep_loop(sleeptime);
	}
}
