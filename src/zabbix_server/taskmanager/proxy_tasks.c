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

#include "taskmanager_server.h"

#include "zbxdb.h"
#include "zbxnum.h"
#include "zbxtasks.h"
#include "zbxversion.h"

/******************************************************************************
 *                                                                            *
 * Purpose: gets tasks scheduled to be executed on proxy                      *
 *                                                                            *
 * Parameters: tasks         - [OUT] tasks to execute                         *
 *             proxyid       - [IN] target proxy                              *
 *             compatibility - [IN] proxy version compatibility with server   *
 *                                                                            *
 * Comments: This function is used by server to get tasks to be sent to the   *
 *           specified proxy. Expired tasks are ignored and handled by the    *
 *           server task manager.                                             *
 *           All tasks are disabled on unsupported proxies. Only remote       *
 *           command and check now are supported by outdated proxies.         *
 *                                                                            *
 ******************************************************************************/
void	zbx_tm_get_remote_tasks(zbx_vector_tm_task_t *tasks, zbx_uint64_t proxyid,
		zbx_proxy_compatibility_t compatibility)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;

	if (ZBX_PROXY_VERSION_UNDEFINED == compatibility || ZBX_PROXY_VERSION_UNSUPPORTED == compatibility)
		return;

	/* skip tasks past expiry data - task manager will handle them */
	result = zbx_db_select(
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
				" and t.proxyid=" ZBX_FS_UI64
				" and (t.ttl=0 or t.clock+t.ttl>" ZBX_FS_TIME_T ")"
			" order by t.taskid",
			ZBX_TM_STATUS_NEW, proxyid, (zbx_fs_time_t)time(NULL));

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_uint64_t	taskid, alertid, parent_taskid, hostid, itemid;
		zbx_tm_task_t	*task;

		ZBX_STR2UINT64(taskid, row[0]);

		task = zbx_tm_task_create(taskid, atoi(row[1]), ZBX_TM_STATUS_NEW, atoi(row[2]), atoi(row[3]), 0);

		switch (task->type)
		{
			case ZBX_TM_TASK_REMOTE_COMMAND:
				if (SUCCEED == zbx_db_is_null(row[4]))
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
				if (SUCCEED == zbx_db_is_null(row[16]))
				{
					zbx_free(task);
					continue;
				}

				ZBX_STR2UINT64(itemid, row[16]);
				task->data = (void *)zbx_tm_check_now_create(itemid);
				break;
			case ZBX_TM_TASK_DATA:
				if (ZBX_PROXY_VERSION_OUTDATED == compatibility || SUCCEED == zbx_db_is_null(row[17]))
				{
					zbx_free(task);
					continue;
				}

				ZBX_STR2UINT64(parent_taskid, row[18]);
				task->data = (void *)zbx_tm_data_create(parent_taskid, row[17], strlen(row[17]),
						atoi(row[19]));
				break;
		}

		zbx_vector_tm_task_append(tasks, task);
	}
	zbx_db_free_result(result);
}
