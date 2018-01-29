/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

#include <assert.h>

#include "common.h"
#include "log.h"

#include "db.h"
#include "zbxjson.h"
#include "zbxtasks.h"

/******************************************************************************
 *                                                                            *
 * Function: zbx_tm_get_remote_tasks                                          *
 *                                                                            *
 * Purpose: get tasks scheduled to be executed on a proxy                     *
 *                                                                            *
 * Parameters: tasks        - [OUT] the tasks to execute                      *
 *             proxy_hostid - [IN] the target proxy                           *
 *                                                                            *
 * Comments: This function is used by server to get tasks to be sent to the   *
 *           specified proxy. Expired tasks are ignored and handled by the    *
 *           server task manager.                                             *
 *                                                                            *
 ******************************************************************************/
void	zbx_tm_get_remote_tasks(zbx_vector_ptr_t *tasks, zbx_uint64_t proxy_hostid)
{
	DB_RESULT		result;
	DB_ROW			row;

	/* skip tasks past expiry data - task manager will handle them */
	result = DBselect(
			"select t.taskid,t.type,t.clock,t.ttl,"
				"c.command_type,c.execute_on,c.port,c.authtype,c.username,c.password,c.publickey,"
				"c.privatekey,c.command,c.alertid,c.parent_taskid,c.hostid"
			" from task t"
			" left join task_remote_command c"
				" on t.taskid=c.taskid"
			" where t.status=%d"
				" and t.proxy_hostid=" ZBX_FS_UI64
				" and t.clock+t.ttl>%d"
				" and c.command_type is not null"
			" order by t.taskid",
			ZBX_TM_STATUS_NEW, proxy_hostid, time(NULL));

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	taskid, alertid, parent_taskid, hostid;
		zbx_tm_task_t	*task;

		ZBX_STR2UINT64(taskid, row[0]);
		ZBX_DBROW2UINT64(alertid, row[13]);
		ZBX_DBROW2UINT64(parent_taskid, row[14]);
		ZBX_DBROW2UINT64(hostid, row[15]);

		task = zbx_tm_task_create(taskid, atoi(row[1]), ZBX_TM_STATUS_NEW, atoi(row[2]), atoi(row[3]), 0);

		task->data = zbx_tm_remote_command_create(atoi(row[4]), row[12], atoi(row[5]), atoi(row[6]),
				atoi(row[7]), row[8], row[9], row[10], row[11], parent_taskid, hostid, alertid);

		zbx_vector_ptr_append(tasks, task);
	}

	DBfree_result(result);
}


