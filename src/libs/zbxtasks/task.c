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
 * Function: tm_remote_command_clear                                          *
 *                                                                            *
 * Purpose: frees remote command task resources                               *
 *                                                                            *
 * Parameters: data - [IN] the remote command task data                       *
 *                                                                            *
 ******************************************************************************/
static void	tm_remote_command_clear(zbx_tm_remote_command_t *data)
{
	zbx_free(data->command);
	zbx_free(data->username);
	zbx_free(data->password);
	zbx_free(data->publickey);
	zbx_free(data->privatekey);
}

/******************************************************************************
 *                                                                            *
 * Function: tm_remote_command_result_clear                                   *
 *                                                                            *
 * Purpose: frees remote command result task resources                        *
 *                                                                            *
 * Parameters: data - [IN] the remote command result task data                *
 *                                                                            *
 ******************************************************************************/
static void	tm_remote_command_result_clear(zbx_tm_remote_command_result_t *data)
{
	zbx_free(data->info);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tm_task_clear                                                *
 *                                                                            *
 * Purpose: frees task resources                                              *
 *                                                                            *
 * Parameters: task - [IN]                                                    *
 *                                                                            *
 ******************************************************************************/
void	zbx_tm_task_clear(zbx_tm_task_t *task)
{
	if (NULL != task->data)
	{
		switch (task->type)
		{
			case ZBX_TM_TASK_REMOTE_COMMAND:
				tm_remote_command_clear((zbx_tm_remote_command_t *)task->data);
				break;
			case ZBX_TM_TASK_REMOTE_COMMAND_RESULT:
				tm_remote_command_result_clear((zbx_tm_remote_command_result_t *)task->data);
				break;
			case ZBX_TM_TASK_CHECK_NOW:
				/* nothing to clear */
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
		}
	}

	zbx_free(task->data);
	task->type = ZBX_TM_TASK_UNDEFINED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tm_task_free                                                 *
 *                                                                            *
 * Purpose: frees task and its resources                                      *
 *                                                                            *
 * Parameters: task - [IN] the task to free                                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_tm_task_free(zbx_tm_task_t *task)
{
	zbx_tm_task_clear(task);
	zbx_free(task);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tm_remote_command_create                                     *
 *                                                                            *
 * Purpose: create a remote command task data                                 *
 *                                                                            *
 * Parameters: command_type  - [IN] the remote command type (ZBX_SCRIPT_TYPE_)*
 *             command       - [IN] the command to execute                    *
 *             execute_on    - [IN] the execution target (ZBX_SCRIPT_EXECUTE_)*
 *             port          - [IN] the target port                           *
 *             authtype      - [IN] the authentication type                   *
 *             username      - [IN] the username (can be NULL)                *
 *             password      - [IN] the password (can be NULL)                *
 *             publickey     - [IN] the public key (can be NULL)              *
 *             privatekey    - [IN] the private key (can be NULL)             *
 *             parent_taskid - [IN] the parent task identifier                *
 *             hostid        - [IN] the target host identifier                *
 *             alertid       - [IN] the alert identifier                      *
 *                                                                            *
 * Return value: The created remote command data.                             *
 *                                                                            *
 ******************************************************************************/
zbx_tm_remote_command_t	*zbx_tm_remote_command_create(int command_type, const char *command, int execute_on, int port,
		int authtype, const char *username, const char *password, const char *publickey, const char *privatekey,
		zbx_uint64_t parent_taskid, zbx_uint64_t hostid, zbx_uint64_t alertid)
{
	zbx_tm_remote_command_t	*data;

	data = (zbx_tm_remote_command_t *)zbx_malloc(NULL, sizeof(zbx_tm_remote_command_t));
	data->command_type = command_type;
	data->command = zbx_strdup(NULL, ZBX_NULL2EMPTY_STR(command));
	data->execute_on = execute_on;
	data->port = port;
	data->authtype = authtype;
	data->username = zbx_strdup(NULL, ZBX_NULL2EMPTY_STR(username));
	data->password = zbx_strdup(NULL, ZBX_NULL2EMPTY_STR(password));
	data->publickey = zbx_strdup(NULL, ZBX_NULL2EMPTY_STR(publickey));
	data->privatekey = zbx_strdup(NULL, ZBX_NULL2EMPTY_STR(privatekey));
	data->parent_taskid = parent_taskid;
	data->hostid = hostid;
	data->alertid = alertid;

	return data;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tm_remote_command_result_create                              *
 *                                                                            *
 * Purpose: create a remote command result task data                          *
 *                                                                            *
 * Parameters: parent_taskid - [IN] the parent task identifier                *
 *             status        - [IN] the remote command execution status       *
 *             info          - [IN] the remote command execution result       *
 *                                                                            *
 * Return value: The created remote command result data.                      *
 *                                                                            *
 ******************************************************************************/
zbx_tm_remote_command_result_t	*zbx_tm_remote_command_result_create(zbx_uint64_t parent_taskid, int status,
		const char *info)
{
	zbx_tm_remote_command_result_t	*data;

	data = (zbx_tm_remote_command_result_t *)zbx_malloc(NULL, sizeof(zbx_tm_remote_command_result_t));
	data->status = status;
	data->parent_taskid = parent_taskid;
	data->info = zbx_strdup(NULL, ZBX_NULL2EMPTY_STR(info));

	return data;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tm_check_now_create                                          *
 *                                                                            *
 * Purpose: create a check now task data                                      *
 *                                                                            *
 * Parameters: itemid - [IN] the item identifier                              *
 *                                                                            *
 * Return value: The created check now data.                                  *
 *                                                                            *
 ******************************************************************************/
zbx_tm_check_now_t	*zbx_tm_check_now_create(zbx_uint64_t itemid)
{
	zbx_tm_check_now_t	*data;

	data = (zbx_tm_check_now_t *)zbx_malloc(NULL, sizeof(zbx_tm_check_now_t));
	data->itemid = itemid;

	return data;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tm_task_create                                               *
 *                                                                            *
 * Purpose: create a new task                                                 *
 *                                                                            *
 * Parameters: taskid       - [IN] the task identifier                        *
 *             type         - [IN] the task type (see ZBX_TM_TASK_*)          *
 *             status       - [IN] the task status (see ZBX_TM_STATUS_*)      *
 *             clock        - [IN] the task creation time                     *
 *             ttl          - [IN] the task expiration period in seconds      *
 *             proxy_hostid - [IN] the destination proxy identifier (or 0)    *
 *                                                                            *
 * Return value: The created task.                                            *
 *                                                                            *
 ******************************************************************************/
zbx_tm_task_t	*zbx_tm_task_create(zbx_uint64_t taskid, unsigned char type, unsigned char status, int clock, int ttl,
		zbx_uint64_t proxy_hostid)
{
	zbx_tm_task_t	*task;

	task = (zbx_tm_task_t *)zbx_malloc(NULL, sizeof(zbx_tm_task_t));

	task->taskid = taskid;
	task->type = type;
	task->status = status;
	task->clock = clock;
	task->ttl = ttl;
	task->proxy_hostid = proxy_hostid;
	task->data = NULL;

	return task;
}

/******************************************************************************
 *                                                                            *
 * Function: tm_save_remote_command_tasks                                     *
 *                                                                            *
 * Purpose: saves remote command task data in database                        *
 *                                                                            *
 * Parameters: tasks     - [IN] the tasks                                     *
 *             tasks_num - [IN] the number of tasks to process                *
 *                                                                            *
 * Return value: SUCCEED - the data was saved successfully                    *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: The tasks array can contain mixture of task types.               *
 *                                                                            *
 ******************************************************************************/
static int	tm_save_remote_command_tasks(zbx_tm_task_t **tasks, int tasks_num)
{
	int			i, ret;
	zbx_db_insert_t		db_insert;
	zbx_tm_remote_command_t	*data;

	zbx_db_insert_prepare(&db_insert, "task_remote_command", "taskid", "command_type", "execute_on", "port",
			"authtype", "username", "password", "publickey", "privatekey", "command", "alertid",
			"parent_taskid", "hostid", NULL);

	for (i = 0; i < tasks_num; i++)
	{
		zbx_tm_task_t	*task = tasks[i];

		switch (task->type)
		{
			case ZBX_TM_TASK_REMOTE_COMMAND:
				data = (zbx_tm_remote_command_t *)task->data;
				zbx_db_insert_add_values(&db_insert, task->taskid, data->command_type, data->execute_on,
						data->port, data->authtype, data->username, data->password,
						data->publickey, data->privatekey, data->command, data->alertid,
						data->parent_taskid, data->hostid);
		}
	}

	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: tm_save_remote_command_result_tasks                              *
 *                                                                            *
 * Purpose: saves remote command result task data in database                 *
 *                                                                            *
 * Parameters: tasks     - [IN] the tasks                                     *
 *             tasks_num - [IN] the number of tasks to process                *
 *                                                                            *
 * Return value: SUCCEED - the data was saved successfully                    *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: The tasks array can contain mixture of task types.               *
 *                                                                            *
 ******************************************************************************/
static int	tm_save_remote_command_result_tasks(zbx_tm_task_t **tasks, int tasks_num)
{
	int				i, ret;
	zbx_db_insert_t			db_insert;
	zbx_tm_remote_command_result_t	*data;

	zbx_db_insert_prepare(&db_insert, "task_remote_command_result", "taskid", "status", "parent_taskid", "info",
			NULL);

	for (i = 0; i < tasks_num; i++)
	{
		zbx_tm_task_t	*task = tasks[i];

		switch (task->type)
		{
			case ZBX_TM_TASK_REMOTE_COMMAND_RESULT:
				data = (zbx_tm_remote_command_result_t *)task->data;
				zbx_db_insert_add_values(&db_insert, task->taskid, data->status, data->parent_taskid,
						data->info);
		}
	}

	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: tm_save_check_now_tasks                                          *
 *                                                                            *
 * Purpose: saves remote command task data in database                        *
 *                                                                            *
 * Parameters: tasks     - [IN] the tasks                                     *
 *             tasks_num - [IN] the number of tasks to process                *
 *                                                                            *
 * Return value: SUCCEED - the data was saved successfully                    *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: The tasks array can contain mixture of task types.               *
 *                                                                            *
 ******************************************************************************/
static int	tm_save_check_now_tasks(zbx_tm_task_t **tasks, int tasks_num)
{
	int			i, ret;
	zbx_db_insert_t		db_insert;
	zbx_tm_check_now_t	*data;

	zbx_db_insert_prepare(&db_insert, "task_check_now", "taskid", "itemid", NULL);

	for (i = 0; i < tasks_num; i++)
	{
		const zbx_tm_task_t	*task = tasks[i];

		switch (task->type)
		{
			case ZBX_TM_TASK_CHECK_NOW:
				data = (zbx_tm_check_now_t *)task->data;
				zbx_db_insert_add_values(&db_insert, task->taskid, data->itemid);
		}
	}

	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: tm_save_tasks                                                    *
 *                                                                            *
 * Purpose: saves tasks into database                                         *
 *                                                                            *
 * Parameters: tasks     - [IN] the tasks                                     *
 *             tasks_num - [IN] the number of tasks to process                *
 *                                                                            *
 * Return value: SUCCEED - the tasks were saved successfully                  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	tm_save_tasks(zbx_tm_task_t **tasks, int tasks_num)
{
	int		i, ret, remote_command_num = 0, remote_command_result_num = 0, check_now_num = 0, ids_num = 0;
	zbx_uint64_t	taskid;
	zbx_db_insert_t	db_insert;

	for (i = 0; i < tasks_num; i++)
	{
		if (0 == tasks[i]->taskid)
			ids_num++;
	}

	if (0 != ids_num)
		taskid = DBget_maxid_num("task", ids_num);

	for (i = 0; i < tasks_num; i++)
	{
		switch (tasks[i]->type)
		{
			case ZBX_TM_TASK_REMOTE_COMMAND:
				remote_command_num++;
				break;
			case ZBX_TM_TASK_REMOTE_COMMAND_RESULT:
				remote_command_result_num++;
				break;
			case ZBX_TM_TASK_CHECK_NOW:
				check_now_num++;
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
		}

		if (0 == tasks[i]->taskid)
			tasks[i]->taskid = taskid++;
	}

	zbx_db_insert_prepare(&db_insert, "task", "taskid", "type", "status", "clock", "ttl", "proxy_hostid", NULL);

	for (i = 0; i < tasks_num; i++)
	{
		if (0 == tasks[i]->taskid)
			continue;

		zbx_db_insert_add_values(&db_insert, tasks[i]->taskid, (int)tasks[i]->type, (int)tasks[i]->status,
				tasks[i]->clock, tasks[i]->ttl, tasks[i]->proxy_hostid);
	}

	ret = zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	if (SUCCEED == ret && 0 != remote_command_num)
		ret = tm_save_remote_command_tasks(tasks, tasks_num);

	if (SUCCEED == ret && 0 != remote_command_result_num)
		ret = tm_save_remote_command_result_tasks(tasks, tasks_num);

	if (SUCCEED == ret && 0 != check_now_num)
		ret = tm_save_check_now_tasks(tasks, tasks_num);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tm_save_tasks                                                *
 *                                                                            *
 * Purpose: saves tasks and their data into database                          *
 *                                                                            *
 * Parameters: tasks - [IN] the tasks                                         *
 *                                                                            *
 ******************************************************************************/
void	zbx_tm_save_tasks(zbx_vector_ptr_t *tasks)
{
	const char	*__function_name = "zbx_tm_save_tasks";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tasks_num:%d", __function_name, tasks->values_num);

	tm_save_tasks((zbx_tm_task_t **)tasks->values, tasks->values_num);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tm_save_task                                                 *
 *                                                                            *
 * Purpose: saves task and its data into database                             *
 *                                                                            *
 * Parameters: task - [IN] the task                                           *
 *                                                                            *
 * Return value: SUCCEED - the task was saved successfully                    *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_tm_save_task(zbx_tm_task_t *task)
{
	const char	*__function_name = "zbx_tm_save_task";
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	ret = tm_save_tasks(&task, 1);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tm_update_task_status                                        *
 *                                                                            *
 * Purpose: update status of the specified tasks in database                  *
 *                                                                            *
 * Parameters: tasks  - [IN] the tasks                                        *
 *             status - [IN] the new status                                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_tm_update_task_status(zbx_vector_ptr_t *tasks, int status)
{
	const char		*__function_name = "zbx_tm_update_task_status";
	zbx_vector_uint64_t	taskids;
	int			i;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&taskids);

	for (i = 0; i < tasks->values_num; i++)
	{
		zbx_tm_task_t	*task = (zbx_tm_task_t *)tasks->values[i];
		zbx_vector_uint64_append(&taskids, task->taskid);
	}

	zbx_vector_uint64_sort(&taskids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update task set status=%d where", status);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "taskid", taskids.values, taskids.values_num);
	DBexecute("%s", sql);
	zbx_free(sql);

	zbx_vector_uint64_destroy(&taskids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: tm_json_serialize_task                                           *
 *                                                                            *
 * Purpose: serializes common task data in json format                        *
 *                                                                            *
 * Parameters: json - [OUT] the json data                                     *
 *             data - [IN] the task to serialize                              *
 *                                                                            *
 ******************************************************************************/
static void	tm_json_serialize_task(struct zbx_json *json, const zbx_tm_task_t *task)
{
	zbx_json_addint64(json, ZBX_PROTO_TAG_TYPE, task->type);
	zbx_json_addint64(json, ZBX_PROTO_TAG_CLOCK, task->clock);
	zbx_json_addint64(json, ZBX_PROTO_TAG_TTL, task->ttl);
}

/******************************************************************************
 *                                                                            *
 * Function: tm_json_serialize_remote_command                                 *
 *                                                                            *
 * Purpose: serializes remote command data in json format                     *
 *                                                                            *
 * Parameters: json - [OUT] the json data                                     *
 *             data - [IN] the remote command to serialize                    *
 *                                                                            *
 ******************************************************************************/
static void	tm_json_serialize_remote_command(struct zbx_json *json, const zbx_tm_remote_command_t *data)
{
	zbx_json_addint64(json, ZBX_PROTO_TAG_COMMANDTYPE, data->command_type);
	zbx_json_addstring(json, ZBX_PROTO_TAG_COMMAND, data->command, ZBX_JSON_TYPE_STRING);
	zbx_json_addint64(json, ZBX_PROTO_TAG_EXECUTE_ON, data->execute_on);
	zbx_json_addint64(json, ZBX_PROTO_TAG_PORT, data->port);
	zbx_json_addint64(json, ZBX_PROTO_TAG_AUTHTYPE, data->authtype);
	zbx_json_addstring(json, ZBX_PROTO_TAG_USERNAME, data->username, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(json, ZBX_PROTO_TAG_PASSWORD, data->password, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(json, ZBX_PROTO_TAG_PUBLICKEY, data->publickey, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(json, ZBX_PROTO_TAG_PRIVATEKEY, data->privatekey, ZBX_JSON_TYPE_STRING);
	zbx_json_adduint64(json, ZBX_PROTO_TAG_ALERTID, data->alertid);
	zbx_json_adduint64(json, ZBX_PROTO_TAG_PARENT_TASKID, data->parent_taskid);
	zbx_json_adduint64(json, ZBX_PROTO_TAG_HOSTID, data->hostid);
}

/******************************************************************************
 *                                                                            *
 * Function: tm_json_serialize_remote_command_result                          *
 *                                                                            *
 * Purpose: serializes remote command result data in json format              *
 *                                                                            *
 * Parameters: json - [OUT] the json data                                     *
 *             data - [IN] the remote command result to serialize             *
 *                                                                            *
 ******************************************************************************/
static void	tm_json_serialize_remote_command_result(struct zbx_json *json,
		const zbx_tm_remote_command_result_t *data)
{
	zbx_json_addint64(json, ZBX_PROTO_TAG_STATUS, data->status);
	zbx_json_addstring(json, ZBX_PROTO_TAG_INFO, data->info, ZBX_JSON_TYPE_STRING);
	zbx_json_adduint64(json, ZBX_PROTO_TAG_PARENT_TASKID, data->parent_taskid);
}

/******************************************************************************
 *                                                                            *
 * Function: tm_json_serialize_check_now                                      *
 *                                                                            *
 * Purpose: serializes check now data in json format                          *
 *                                                                            *
 * Parameters: json - [OUT] the json data                                     *
 *             data - [IN] the check now to serialize                         *
 *                                                                            *
 ******************************************************************************/
static void	tm_json_serialize_check_now(struct zbx_json *json, const zbx_tm_check_now_t *data)
{
	zbx_json_addint64(json, ZBX_PROTO_TAG_ITEMID, data->itemid);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tm_json_serialize_tasks                                      *
 *                                                                            *
 * Purpose: serializes remote command data in json format                     *
 *                                                                            *
 * Parameters: json  - [OUT] the json data                                    *
 *             tasks - [IN] the tasks to serialize                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_tm_json_serialize_tasks(struct zbx_json *json, const zbx_vector_ptr_t *tasks)
{
	int	i;

	zbx_json_addarray(json, ZBX_PROTO_TAG_TASKS);

	for (i = 0; i < tasks->values_num; i++)
	{
		const zbx_tm_task_t	*task = (const zbx_tm_task_t *)tasks->values[i];

		zbx_json_addobject(json, NULL);
		tm_json_serialize_task(json, task);

		switch (task->type)
		{
			case ZBX_TM_TASK_REMOTE_COMMAND:
				tm_json_serialize_remote_command(json, (zbx_tm_remote_command_t *)task->data);
				break;
			case ZBX_TM_TASK_REMOTE_COMMAND_RESULT:
				tm_json_serialize_remote_command_result(json, (zbx_tm_remote_command_result_t *)task->data);
				break;
			case ZBX_TM_TASK_CHECK_NOW:
				tm_json_serialize_check_now(json, (zbx_tm_check_now_t *)task->data);
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				break;
		}

		zbx_json_close(json);
	}

	zbx_json_close(json);
}

/******************************************************************************
 *                                                                            *
 * Function: tm_json_deserialize_remote_command                               *
 *                                                                            *
 * Purpose: deserializes remote command from json data                        *
 *                                                                            *
 * Parameters: jp - [IN] the json data                                        *
 *                                                                            *
 * Return value: The deserialized remote command data or NULL if              *
 *               deserialization failed.                                      *
 *                                                                            *
 ******************************************************************************/
static zbx_tm_remote_command_t	*tm_json_deserialize_remote_command(const struct zbx_json_parse *jp)
{
	char			value[MAX_STRING_LEN];
	int			commandtype, execute_on, port, authtype;
	zbx_uint64_t		alertid, parent_taskid, hostid;
	char			*username = NULL, *password = NULL, *publickey = NULL, *privatekey = NULL,
				*command = NULL;
	size_t			username_alloc = 0, password_alloc = 0, publickey_alloc = 0, privatekey_alloc = 0,
				command_alloc = 0;
	zbx_tm_remote_command_t	*data = NULL;

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_COMMANDTYPE, value, sizeof(value)))
		goto out;

	commandtype = atoi(value);

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_EXECUTE_ON, value, sizeof(value)))
		goto out;

	execute_on = atoi(value);

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_PORT, value, sizeof(value)))
		goto out;

	port = atoi(value);

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_AUTHTYPE, value, sizeof(value)))
		goto out;

	authtype = atoi(value);

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_ALERTID, value, sizeof(value)) ||
			SUCCEED != is_uint64(value, &alertid))
	{
		goto out;
	}

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_PARENT_TASKID, value, sizeof(value)) ||
			SUCCEED != is_uint64(value, &parent_taskid))
	{
		goto out;
	}

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_HOSTID, value, sizeof(value)) ||
			SUCCEED != is_uint64(value, &hostid))
	{
		goto out;
	}

	if (SUCCEED != zbx_json_value_by_name_dyn(jp, ZBX_PROTO_TAG_USERNAME, &username, &username_alloc))
		goto out;

	if (SUCCEED != zbx_json_value_by_name_dyn(jp, ZBX_PROTO_TAG_PASSWORD, &password, &password_alloc))
		goto out;

	if (SUCCEED != zbx_json_value_by_name_dyn(jp, ZBX_PROTO_TAG_PUBLICKEY, &publickey, &publickey_alloc))
		goto out;

	if (SUCCEED != zbx_json_value_by_name_dyn(jp, ZBX_PROTO_TAG_PRIVATEKEY, &privatekey, &privatekey_alloc))
		goto out;

	if (SUCCEED != zbx_json_value_by_name_dyn(jp, ZBX_PROTO_TAG_COMMAND, &command, &command_alloc))
		goto out;

	data = zbx_tm_remote_command_create(commandtype, command, execute_on, port, authtype, username, password,
			publickey, privatekey, parent_taskid, hostid, alertid);
out:
	zbx_free(command);
	zbx_free(privatekey);
	zbx_free(publickey);
	zbx_free(password);
	zbx_free(username);

	return data;
}

/******************************************************************************
 *                                                                            *
 * Function: tm_json_deserialize_remote_command_result                        *
 *                                                                            *
 * Purpose: deserializes remote command result from json data                 *
 *                                                                            *
 * Parameters: jp - [IN] the json data                                        *
 *                                                                            *
 * Return value: The deserialized remote command result data or NULL if       *
 *               deserialization failed.                                      *
 *                                                                            *
 ******************************************************************************/
static zbx_tm_remote_command_result_t	*tm_json_deserialize_remote_command_result(const struct zbx_json_parse *jp)
{
	char				value[MAX_STRING_LEN];
	int				status;
	zbx_uint64_t			parent_taskid;
	char				*info = NULL;
	size_t				info_alloc = 0;
	zbx_tm_remote_command_result_t	*data = NULL;

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_STATUS, value, sizeof(value)))
		goto out;

	status = atoi(value);

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_PARENT_TASKID, value, sizeof(value)) ||
			SUCCEED != is_uint64(value, &parent_taskid))
	{
		goto out;
	}

	if (SUCCEED != zbx_json_value_by_name_dyn(jp, ZBX_PROTO_TAG_INFO, &info, &info_alloc))
		goto out;

	data = zbx_tm_remote_command_result_create(parent_taskid, status, info);
out:
	zbx_free(info);

	return data;
}

/******************************************************************************
 *                                                                            *
 * Function: tm_json_deserialize_check_now                                    *
 *                                                                            *
 * Purpose: deserializes check now from json data                             *
 *                                                                            *
 * Parameters: jp - [IN] the json data                                        *
 *                                                                            *
 * Return value: The deserialized check now data or NULL if deserialization   *
 *               failed.                                                      *
 *                                                                            *
 ******************************************************************************/
static zbx_tm_check_now_t	*tm_json_deserialize_check_now(const struct zbx_json_parse *jp)
{
	char			value[MAX_ID_LEN + 1];
	zbx_uint64_t		itemid;

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_ITEMID, value, sizeof(value)) ||
			SUCCEED != is_uint64(value, &itemid))
	{
		return NULL;
	}

	return zbx_tm_check_now_create(itemid);
}

/******************************************************************************
 *                                                                            *
 * Function: tm_json_deserialize_task                                         *
 *                                                                            *
 * Purpose: deserializes common task data from json data                      *
 *                                                                            *
 * Parameters: jp - [IN] the json data                                        *
 *                                                                            *
 * Return value: The deserialized task data or NULL if deserialization failed.*
 *                                                                            *
 ******************************************************************************/
static zbx_tm_task_t	*tm_json_deserialize_task(const struct zbx_json_parse *jp)
{
	char	value[MAX_STRING_LEN];
	int	type, clock, ttl;

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_TYPE, value, sizeof(value)))
		return NULL;

	ZBX_STR2UCHAR(type, value);

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_CLOCK, value, sizeof(value)))
		return NULL;

	clock = atoi(value);

	if (SUCCEED != zbx_json_value_by_name(jp, ZBX_PROTO_TAG_TTL, value, sizeof(value)))
		return NULL;

	ttl = atoi(value);

	return zbx_tm_task_create(0, type, ZBX_TM_STATUS_NEW, clock, ttl, 0);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_tm_json_deserialize_tasks                                    *
 *                                                                            *
 * Purpose: deserializes tasks from json data                                 *
 *                                                                            *
 * Parameters: jp    - [IN] the json data                                     *
 *             tasks - [OUT] the deserialized tasks                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_tm_json_deserialize_tasks(const struct zbx_json_parse *jp, zbx_vector_ptr_t *tasks)
{
	const char		*pnext = NULL;
	struct zbx_json_parse	jp_task;

	while (NULL != (pnext = zbx_json_next(jp, pnext)))
	{
		zbx_tm_task_t	*task;

		if (SUCCEED != zbx_json_brackets_open(pnext, &jp_task))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Cannot deserialize task record: %s", jp->start);
			continue;
		}

		task = tm_json_deserialize_task(&jp_task);

		if (NULL == task)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Cannot deserialize task at: %s", jp_task.start);
			continue;
		}

		switch (task->type)
		{
			case ZBX_TM_TASK_REMOTE_COMMAND:
				task->data = tm_json_deserialize_remote_command(&jp_task);
				break;
			case ZBX_TM_TASK_REMOTE_COMMAND_RESULT:
				task->data = tm_json_deserialize_remote_command_result(&jp_task);
				break;
			case ZBX_TM_TASK_CHECK_NOW:
				task->data = tm_json_deserialize_check_now(&jp_task);
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				break;
		}

		if (NULL == task->data)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Cannot deserialize task data at: %s", jp_task.start);
			zbx_tm_task_free(task);
			continue;
		}

		zbx_vector_ptr_append(tasks, task);
	}
}
