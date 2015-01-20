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

#include "nodewatcher.h"
#include "nodesender.h"
#include "history.h"

extern unsigned char	process_type;

/******************************************************************************
 *                                                                            *
 * Function: is_master_node                                                   *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:  SUCCEED - master_nodeid is a master node of current_nodeid  *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	is_master_node(int current_nodeid, int master_nodeid)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		res = FAIL;

	result = DBselect("select masterid from nodes where nodeid=%d",
		current_nodeid);

	if (NULL != (row = DBfetch(result)))
	{
		current_nodeid = (SUCCEED == DBis_null(row[0])) ? 0 : atoi(row[0]);
		if (current_nodeid == master_nodeid)
			res = SUCCEED;
		else if (0 != current_nodeid)
			res = is_master_node(current_nodeid, master_nodeid);
	}
	DBfree_result(result);

	return res;
}

/******************************************************************************
 *                                                                            *
 * Function: is_slave_node                                                    *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:  SUCCEED - slave_nodeid is a slave node of current_nodeid    *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Aleksandrs Saveljevs                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	is_slave_node(int current_nodeid, int slave_nodeid)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		nodeid;
	int		ret = FAIL;

	result = DBselect(
			"select nodeid"
			" from nodes"
			" where masterid=%d",
			current_nodeid);

	while (FAIL == ret && NULL != (row = DBfetch(result)))
	{
		nodeid = atoi(row[0]);
		if (nodeid == slave_nodeid)
			ret = SUCCEED;
		else
			ret = is_slave_node(nodeid, slave_nodeid);
	}
	DBfree_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: is_direct_slave_node                                             *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:  SUCCEED - slave_nodeid is our direct slave node             *
 *                FAIL - otherwise                                            *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	is_direct_slave_node(int slave_nodeid)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = FAIL;

	result = DBselect(
			"select nodeid"
			" from nodes"
			" where nodeid=%d"
				" and masterid=%d",
			slave_nodeid,
			CONFIG_NODEID);

	if (NULL != (row = DBfetch(result)))
		ret = SUCCEED;
	DBfree_result(result);

	return ret;
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
void	main_nodewatcher_loop(void)
{
	int	start, end, lastrun = 0, sleeptime = -1;
	double	sec, total_sec = 0.0, old_total_sec = 0.0;
	time_t	last_stat_time;

#define STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));
	last_stat_time = time(NULL);

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	for (;;)
	{
		if (0 != sleeptime)
		{
			zbx_setproctitle("%s [synced with nodes in " ZBX_FS_DBL " sec, syncing with nodes]",
					get_process_type_string(process_type), old_total_sec);
		}

		start = time(NULL);
		sec = zbx_time();

		if (lastrun + 120 < start)
		{
			process_nodes();
			lastrun = start;
		}

		/* send new history data to master node */
		main_historysender();

		total_sec += zbx_time() - sec;
		end = time(NULL);

		sleeptime = 10 - (end - start) > 0 ? 10 - (end - start) : 0;

		if (0 != sleeptime || STAT_INTERVAL <= time(NULL) - last_stat_time)
		{
			if (0 == sleeptime)
			{
				zbx_setproctitle("%s [synced with nodes in " ZBX_FS_DBL " sec, syncing with nodes]",
						get_process_type_string(process_type), total_sec);
			}
			else
			{
				zbx_setproctitle("%s [synced with nodes in " ZBX_FS_DBL " sec, idle %d sec]",
						get_process_type_string(process_type), total_sec, sleeptime);
				old_total_sec = total_sec;
			}
			total_sec = 0.0;
			last_stat_time = time(NULL);
		}

		zbx_sleep_loop(sleeptime);
	}

#undef STAT_INTERVAL
}
