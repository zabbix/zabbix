/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "zbxtasks.h"

#include "zbxdbhigh.h"

/******************************************************************************
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
				"r.status,r.parent_taskid,r.info,"
				"tr.status,tr.parent_taskid,tr.info,"
				"d.data,d.parent_taskid,d.type"
			" from task t"
			" left join task_remote_command_result r"
				" on t.taskid=r.taskid"
			" left join task_result tr"
				" on t.taskid=tr.taskid"
			" left join task_data d"
				" on t.taskid=d.taskid"
			" where t.status=%d"
				" and t.type in (%d,%d,%d)"
			" order by t.taskid",
			ZBX_TM_STATUS_NEW, ZBX_TM_TASK_REMOTE_COMMAND_RESULT, ZBX_TM_TASK_DATA_RESULT,
			ZBX_TM_PROXYDATA);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	taskid, parent_taskid;
		zbx_tm_task_t	*task;

		ZBX_STR2UINT64(taskid, row[0]);
		task = zbx_tm_task_create(taskid, atoi(row[1]), ZBX_TM_STATUS_NEW, atoi(row[2]), atoi(row[3]), 0);

		switch (task->type)
		{
			case ZBX_TM_TASK_REMOTE_COMMAND_RESULT:
				if (SUCCEED == DBis_null(row[4]))
				{
					zbx_free(task);
					continue;
				}

				ZBX_DBROW2UINT64(parent_taskid, row[5]);

				task->data = zbx_tm_remote_command_result_create(parent_taskid, atoi(row[4]), row[6]);
				break;
			case ZBX_TM_TASK_DATA_RESULT:
				if (SUCCEED == DBis_null(row[7]))
				{
					zbx_free(task);
					continue;
				}

				ZBX_DBROW2UINT64(parent_taskid, row[8]);

				task->data = zbx_tm_data_result_create(parent_taskid, atoi(row[7]), row[9]);
				break;
			case ZBX_TM_PROXYDATA:
				if (SUCCEED == DBis_null(row[10]))
				{
					zbx_free(task);
					continue;
				}
				ZBX_STR2UINT64(parent_taskid, row[11]);
				task->data = (void *)zbx_tm_data_create(parent_taskid, row[10], strlen(row[10]),
						atoi(row[12]));
				break;
		}

		zbx_vector_ptr_append(tasks, task);
	}

	DBfree_result(result);
}
