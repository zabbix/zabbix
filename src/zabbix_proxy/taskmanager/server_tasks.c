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
 * Purpose: get tasks scheduled to be executed on the server                  *
 *                                                                            *
 * Parameters: tasks        - [OUT] the tasks to execute                      *
 *             proxy_hostid - [IN] (ignored)                                  *
 *                                                                            *
 * Comments: This function is used by proxy to get tasks to be sent to the    *
 *           server.                                                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_tm_get_remote_tasks(zbx_vector_ptr_t *tasks, zbx_uint64_t proxy_hostid)
{
	DB_RESULT	result;
	DB_ROW		row;

	ZBX_UNUSED(proxy_hostid);

	result = DBselect(
			"select t.taskid,t.type,t.clock,t.ttl,"
				"r.status,r.parent_taskid,r.info"
			" from task t,task_remote_command_result r"
			" where t.taskid=r.taskid"
				" and t.status=%d"
				" and t.type=%d"
			" order by t.taskid",
			ZBX_TM_STATUS_NEW, ZBX_TM_TASK_REMOTE_COMMAND_RESULT);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	taskid, parent_taskid;
		zbx_tm_task_t	*task;

		ZBX_STR2UINT64(taskid, row[0]);

		if (SUCCEED == DBis_null(row[5]))
			continue;

		ZBX_DBROW2UINT64(parent_taskid, row[5]);

		task = zbx_tm_task_create(taskid, atoi(row[1]), ZBX_TM_STATUS_NEW, atoi(row[2]), atoi(row[3]), 0);

		task->data = zbx_tm_remote_command_result_create(parent_taskid, atoi(row[4]), row[6]);

		zbx_vector_ptr_append(tasks, task);
	}

	DBfree_result(result);
}


