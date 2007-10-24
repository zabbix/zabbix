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

#include "dbsync.h"
#include "nodecomms.h"
#include "nodesender.h"

#define	ZBX_NODE_MASTER	0
#define	ZBX_NODE_SLAVE	1

/******************************************************************************
 *                                                                            *
 * Function: get_slave_node                                                   *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCESS - processed succesfully                              * 
 *               FAIL - an error occured                                      *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int get_slave_node(int nodeid, int synked_nodeid)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		master_nodeid;

	zabbix_log( LOG_LEVEL_DEBUG, "In get_slave_node(%d)",
		nodeid);

	result = DBselect("select masterid from nodes where nodeid=%d",
		synked_nodeid);
	if (NULL != (row = DBfetch(result)))
		master_nodeid = atoi(row[0]);
	else
		master_nodeid = 0;
	DBfree_result(result);

	if (master_nodeid == 0)
		return 0;
	if (master_nodeid == nodeid)
		return synked_nodeid;
	return get_slave_node(nodeid, master_nodeid);
}

/******************************************************************************
 *                                                                            *
 * Function: main_nodesender                                                  *
 *                                                                            *
 * Purpose: periodically sends config changes and history to related nodes    *
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
/*void main_nodesender(int synked_nodeid, int *synked_slave, int *synked_master)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		nodeid, slave_nodeid, master_nodeid;
	char		*data;

	zabbix_log(LOG_LEVEL_DEBUG, "In main_nodesender()");

	*synked_slave = FAIL;
	*synked_master = FAIL;

	result = DBselect("select nodeid from nodes where nodetype=%d",
		ZBX_NODE_TYPE_LOCAL);

	if (NULL != (row = DBfetch(result))) {
		nodeid = atoi(row[0]);
		if (CONFIG_NODEID != nodeid) {
			zabbix_log(LOG_LEVEL_WARNING, "NodeID does not match configuration settings."
				" Processing of the node is disabled.");
		} else {
			slave_nodeid = get_slave_node(nodeid, synked_nodeid);
			master_nodeid = CONFIG_MASTER_NODEID;
			if (0 != slave_nodeid && NULL != (data = get_config_data(synked_nodeid, ZBX_NODE_SLAVE))) {
				*synked_slave = send_to_node("configuration changes", slave_nodeid, synked_nodeid, data);
				zbx_free(data);
			}
			if (0 != master_nodeid && NULL != (data = get_config_data(synked_nodeid, ZBX_NODE_MASTER))) {
				*synked_master = send_to_node("configuration changes", master_nodeid, synked_nodeid, data);
				zbx_free(data);
			}
		}
	}
	DBfree_result(result);
}*/
