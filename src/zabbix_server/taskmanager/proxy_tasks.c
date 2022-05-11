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

#include "common.h"
#include "db.h"

#include "zbxtasks.h"

/******************************************************************************
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
	DB_RESULT	result;
	DB_ROW		row;

	/* skip tasks past expiry data - task manager will handle them */
	result = DBselect(
			"select t.taskid,t.type,t.clock,t.ttl,"
				"c.command_type,c.execute_on,c.port,c.authtype,c.username,c.password,c.publickey,"
				"c.privatekey,c.command,c.alertid,c.parent_taskid,c.hostid,"
				"cn.itemid,"
				"d.data,d.parent_taskid,d.type"
			" from task t"
			" left join task_remote_command c"
				" on t.taskid=c.taskid"
			" left join task_check_now cn"
				" on t.taskid=cn.taskid"
			" left join task_data d"
				" on t.taskid=d.taskid"
			" where t.status=%d"
				" and t.proxy_hostid=" ZBX_FS_UI64
				" and (t.ttl=0 or t.clock+t.ttl>" ZBX_FS_TIME_T ")"
			" order by t.taskid",
			ZBX_TM_STATUS_NEW, proxy_hostid, (zbx_fs_time_t)time(NULL));

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	taskid, alertid, parent_taskid, hostid, itemid;
		zbx_tm_task_t	*task;

		ZBX_STR2UINT64(taskid, row[0]);

		task = zbx_tm_task_create(taskid, atoi(row[1]), ZBX_TM_STATUS_NEW, atoi(row[2]), atoi(row[3]), 0);

		switch (task->type)
		{
			case ZBX_TM_TASK_REMOTE_COMMAND:
				if (SUCCEED == DBis_null(row[4]))
				{
					zbx_free(task);
					continue;
				}

				ZBX_DBROW2UINT64(alertid, row[13]);
				ZBX_DBROW2UINT64(parent_taskid, row[14]);
				ZBX_DBROW2UINT64(hostid, row[15]);
				task->data = (void *)zbx_tm_remote_command_create(atoi(row[4]), row[12], atoi(row[5]),
						atoi(row[6]), atoi(row[7]), row[8], row[9], row[10], row[11],
						parent_taskid, hostid, alertid);
				break;
			case ZBX_TM_TASK_CHECK_NOW:
				if (SUCCEED == DBis_null(row[16]))
				{
					zbx_free(task);
					continue;
				}

				ZBX_STR2UINT64(itemid, row[16]);
				task->data = (void *)zbx_tm_check_now_create(itemid);
				break;
			case ZBX_TM_TASK_DATA:
				if (SUCCEED == DBis_null(row[17]))
				{
					zbx_free(task);
					continue;
				}

				ZBX_STR2UINT64(parent_taskid, row[18]);
				task->data = (void *)zbx_tm_data_create(parent_taskid, row[17], strlen(row[17]),
						atoi(row[19]));
				break;
		}

		zbx_vector_ptr_append(tasks, task);
	}
	DBfree_result(result);
}
