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
void	main_dbsyncer_loop(void)
{
	int	sleeptime = -1, num = 0, old_num = 0, retry_up = 0, retry_dn = 0;
	double	sec, total_sec = 0.0, old_total_sec = 0.0;
	time_t	last_stat_time;

#define STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	zbx_setproctitle("%s #%d [connecting to the database]", get_process_type_string(process_type), process_num);
	last_stat_time = time(NULL);

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;)
	{
		if (0 != sleeptime)
		{
			zbx_setproctitle("%s #%d [synced %d items in " ZBX_FS_DBL " sec, syncing history]",
					get_process_type_string(process_type), process_num, old_num, old_total_sec);
		}

		sec = zbx_time();
		num += DCsync_history(ZBX_SYNC_PARTIAL);
		total_sec += zbx_time() - sec;

		if (-1 == sleeptime)
		{
			sleeptime = num ? ZBX_SYNC_MAX / num : CONFIG_HISTSYNCER_FREQUENCY;
		}
		else
		{
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

		if (0 != sleeptime || STAT_INTERVAL <= time(NULL) - last_stat_time)
		{
			if (0 == sleeptime)
			{
				zbx_setproctitle("%s #%d [synced %d items in " ZBX_FS_DBL " sec, syncing history]",
						get_process_type_string(process_type), process_num, num, total_sec);
			}
			else
			{
				zbx_setproctitle("%s #%d [synced %d items in " ZBX_FS_DBL " sec, idle %d sec]",
						get_process_type_string(process_type), process_num, num, total_sec,
						sleeptime);
				old_num = num;
				old_total_sec = total_sec;
			}
			num = 0;
			total_sec = 0.0;
			last_stat_time = time(NULL);
		}

		zbx_sleep_loop(sleeptime);
	}

#undef STAT_INTERVAL
}
