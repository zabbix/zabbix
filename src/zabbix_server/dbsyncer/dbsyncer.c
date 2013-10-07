/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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
extern int		ZBX_SYNC_MAX;
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
	int	sleeptime, last_sleeptime = -1, num, retry_up = 0, retry_dn = 0;
	double	sec;

	zbx_setproctitle("%s #%d [connecting to the database]", get_process_type_string(process_type), process_num);

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;)
	{
		zbx_setproctitle("%s #%d [syncing history]", get_process_type_string(process_type), process_num);

		sec = zbx_time();
		num = DCsync_history(ZBX_SYNC_PARTIAL);
		sec = zbx_time() - sec;

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

		zbx_setproctitle("%s #%d [synced %d items in " ZBX_FS_DBL " sec, idle %d sec]",
				get_process_type_string(process_type), process_num, num, sec, sleeptime);

		zbx_sleep_loop(sleeptime);
	}
}
