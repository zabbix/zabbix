/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "taskmanager_proxy.h"

#include "zbxdb.h"
#include "zbxnum.h"
#include "zbxtasks.h"
#include "zbxversion.h"

/******************************************************************************
 *                                                                            *
 * Purpose: gets tasks scheduled to be executed on server                     *
 *                                                                            *
 * Parameters: tasks         - [OUT] tasks to execute                         *
 *             proxyid       - [IN] (ignored)                                 *
 *             compatibility - [IN] (ignored)                                 *
 *                                                                            *
 * Comments: This function is used by proxy to get tasks to be sent to the    *
 *           server.                                                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_tm_get_remote_tasks(zbx_vector_tm_task_t *tasks, zbx_uint64_t proxyid,
		zbx_proxy_compatibility_t compatibility)
{
	zbx_db_row_t	row;

	ZBX_UNUSED(proxyid);
	ZBX_UNUSED(compatibility);

	zbx_db_result_t	result = zbx_db_select(
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

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	taskid, parent_taskid;

		ZBX_STR2UINT64(taskid, row[0]);

		zbx_tm_task_t	*task = zbx_tm_task_create(taskid, atoi(row[1]), ZBX_TM_STATUS_NEW, atoi(row[2]),
				atoi(row[3]), 0);

		switch (task->type)
		{
			case ZBX_TM_TASK_REMOTE_COMMAND_RESULT:
				if (SUCCEED == zbx_db_is_null(row[4]))
				{
					zbx_free(task);
					continue;
				}

				ZBX_DBROW2UINT64(parent_taskid, row[5]);

				task->data = zbx_tm_remote_command_result_create(parent_taskid, atoi(row[4]), row[6]);
				break;
			case ZBX_TM_TASK_DATA_RESULT:
				if (SUCCEED == zbx_db_is_null(row[7]))
				{
					zbx_free(task);
					continue;
				}

				ZBX_DBROW2UINT64(parent_taskid, row[8]);

				task->data = zbx_tm_data_result_create(parent_taskid, atoi(row[7]), row[9]);
				break;
			case ZBX_TM_PROXYDATA:
				if (SUCCEED == zbx_db_is_null(row[10]))
				{
					zbx_free(task);
					continue;
				}
				ZBX_STR2UINT64(parent_taskid, row[11]);
				task->data = (void *)zbx_tm_data_create(parent_taskid, row[10], strlen(row[10]),
						atoi(row[12]));
				break;
		}

		zbx_vector_tm_task_append(tasks, task);
	}

	zbx_db_free_result(result);
}
