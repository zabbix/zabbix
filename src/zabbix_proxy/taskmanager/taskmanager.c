/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#include "taskmanager.h"

#include "../../zabbix_server/scripts/scripts.h"
#include "../../zabbix_server/trapper/trapper_item_test.h"
#include "../../zabbix_server/poller/checks_snmp.h"

#include "zbxnix.h"
#include "zbxself.h"
#include "zbxtasks.h"
#include "log.h"
#include "zbxdiag.h"
#include "zbxrtc.h"
#include "zbxdbwrap.h"
#include "zbxcacheconfig.h"
#include "zbxnum.h"
#include "zbxtime.h"
#include "zbx_rtc_constants.h"

#define ZBX_TM_PROCESS_PERIOD		5
#define ZBX_TM_CLEANUP_PERIOD		SEC_PER_HOUR

extern int				CONFIG_ENABLE_REMOTE_COMMANDS;
extern int				CONFIG_LOG_REMOTE_COMMANDS;
extern unsigned char			program_type;
extern char 				*CONFIG_HOSTNAME;

/******************************************************************************
 *                                                                            *
 * Purpose: execute remote command task                                       *
 *                                                                            *
 * Parameters: taskid         - [IN] task identifier                          *
 *             clock          - [IN] task creation time                       *
 *             ttl            - [IN] task expiration period in seconds        *
 *             now            - [IN] current time                             *
 *             config_timeout - [IN]                                          *
 *                                                                            *
 * Return value: SUCCEED -     remote command was executed                    *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	tm_execute_remote_command(zbx_uint64_t taskid, int clock, int ttl, int now, int config_timeout)
{
	DB_ROW		row;
	DB_RESULT	result;
	zbx_uint64_t	parent_taskid, hostid, alertid;
	zbx_tm_task_t	*task = NULL;
	int		ret = FAIL;
	zbx_script_t	script;
	char		*info = NULL, error[MAX_STRING_LEN];
	DC_HOST		host;

	result = zbx_db_select("select command_type,execute_on,port,authtype,username,password,publickey,privatekey,"
					"command,parent_taskid,hostid,alertid"
				" from task_remote_command"
				" where taskid=" ZBX_FS_UI64,
				taskid);

	if (NULL == (row = zbx_db_fetch(result)))
		goto finish;

	task = zbx_tm_task_create(0, ZBX_TM_TASK_REMOTE_COMMAND_RESULT, ZBX_TM_STATUS_NEW, zbx_time(), 0, 0);

	ZBX_STR2UINT64(parent_taskid, row[9]);

	if (0 != ttl && clock + ttl < now)
	{
		task->data = zbx_tm_remote_command_result_create(parent_taskid, FAIL,
				"The remote command has been expired.");
		goto finish;
	}

	ZBX_STR2UINT64(hostid, row[10]);
	if (FAIL == DCget_host_by_hostid(&host, hostid))
	{
		task->data = zbx_tm_remote_command_result_create(parent_taskid, FAIL, "Unknown host.");
		goto finish;
	}

	zbx_script_init(&script);

	ZBX_STR2UCHAR(script.type, row[0]);
	ZBX_STR2UCHAR(script.execute_on, row[1]);
	script.port = (0 == atoi(row[2]) ? (char *)"" : row[2]);
	ZBX_STR2UCHAR(script.authtype, row[3]);
	script.username = row[4];
	script.password = row[5];
	script.publickey = row[6];
	script.privatekey = row[7];
	script.command = row[8];

	if (ZBX_SCRIPT_EXECUTE_ON_PROXY == script.execute_on)
	{
		/* always wait for execution result when executing on Zabbix proxy */
		alertid = 0;

		if (ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT == script.type)
		{
			if (0 == CONFIG_ENABLE_REMOTE_COMMANDS)
			{
				task->data = zbx_tm_remote_command_result_create(parent_taskid, FAIL,
						"Remote commands are not enabled");
				goto finish;
			}

			if (1 == CONFIG_LOG_REMOTE_COMMANDS)
				zabbix_log(LOG_LEVEL_WARNING, "Executing command '%s'", script.command);
			else
				zabbix_log(LOG_LEVEL_DEBUG, "Executing command '%s'", script.command);
		}
	}
	else
	{
		/* only wait for execution result when executed on Zabbix agent if it's not automatic alert but */
		/* manually initiated command through frontend                                                  */
		ZBX_DBROW2UINT64(alertid, row[11]);
	}

	if (SUCCEED != (ret = zbx_script_execute(&script, &host, NULL, config_timeout, 0 == alertid ? &info : NULL,
			error, sizeof(error), NULL)))
	{
		task->data = zbx_tm_remote_command_result_create(parent_taskid, ret, error);
	}
	else
		task->data = zbx_tm_remote_command_result_create(parent_taskid, ret, info);

	zbx_free(info);
finish:
	zbx_db_free_result(result);

	zbx_db_begin();

	if (NULL != task)
	{
		zbx_tm_save_task(task);
		zbx_tm_task_free(task);
	}

	zbx_db_execute("update task set status=%d where taskid=" ZBX_FS_UI64, ZBX_TM_STATUS_DONE, taskid);

	zbx_db_commit();

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: process check now tasks for item rescheduling                     *
 *                                                                            *
 * Return value: The number of successfully processed tasks                   *
 *                                                                            *
 ******************************************************************************/
static int	tm_process_check_now(zbx_vector_uint64_t *taskids)
{
	DB_ROW			row;
	DB_RESULT		result;
	int			processed_num;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	itemids;
	zbx_uint64_t		itemid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tasks_num:%d", __func__, taskids->values_num);

	zbx_vector_uint64_create(&itemids);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select itemid from task_check_now where");
	zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "taskid", taskids->values, taskids->values_num);
	result = zbx_db_select("%s", sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(itemid, row[0]);
		zbx_vector_uint64_append(&itemids, itemid);
	}
	zbx_db_free_result(result);

	if (0 != (processed_num = itemids.values_num))
		zbx_dc_reschedule_items(&itemids, zbx_time(), NULL);

	if (0 != taskids->values_num)
	{
		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update task set status=%d where",
				ZBX_TM_STATUS_DONE);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "taskid", taskids->values, taskids->values_num);

		zbx_db_execute("%s", sql);
	}

	zbx_free(sql);
	zbx_vector_uint64_destroy(&itemids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() processed:%d", __func__, processed_num);

	return processed_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: process data task with json contents                              *
 *                                                                            *
 * Return value: SUCCEED - the data task was executed                         *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	tm_execute_data_json(int type, const char *data, char **info,
		const zbx_config_comms_args_t *config_comms, int config_startup_time)
{
	struct zbx_json_parse	jp_data;

	if (SUCCEED != zbx_json_brackets_open(data, &jp_data))
	{
		*info = zbx_strdup(*info, zbx_json_strerror());
		return FAIL;
	}

	switch (type)
	{
		case ZBX_TM_DATA_TYPE_TEST_ITEM:
			return zbx_trapper_item_test_run(&jp_data, 0, info, config_comms,
					config_startup_time);
		case ZBX_TM_DATA_TYPE_DIAGINFO:
			return zbx_diag_get_info(&jp_data, info);
	}

	THIS_SHOULD_NEVER_HAPPEN;

	*info = zbx_strdup(*info, "Unknown task data type");
	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: process data task                                                 *
 *                                                                            *
 * Return value: SUCCEED - the data task was executed                         *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	tm_execute_data(zbx_ipc_async_socket_t *rtc, zbx_uint64_t taskid, int clock, int ttl, int now,
		const zbx_config_comms_args_t *config_comms, int config_startup_time)
{
	DB_ROW			row;
	DB_RESULT		result;
	zbx_tm_task_t		*task = NULL;
	int			ret = FAIL, data_type;
	char			*info = NULL;
	zbx_uint64_t		parent_taskid;

	result = zbx_db_select("select parent_taskid,data,type"
				" from task_data"
				" where taskid=" ZBX_FS_UI64,
				taskid);

	if (NULL == (row = zbx_db_fetch(result)))
		goto finish;

	task = zbx_tm_task_create(0, ZBX_TM_TASK_DATA_RESULT, ZBX_TM_STATUS_NEW, zbx_time(), 0, 0);
	ZBX_STR2UINT64(parent_taskid, row[0]);

	if (0 != ttl && clock + ttl < now)
	{
		task->data = zbx_tm_data_result_create(parent_taskid, FAIL, "The task has been expired.");
		goto finish;
	}

	switch (data_type = atoi(row[2]))
	{
		case ZBX_TM_DATA_TYPE_TEST_ITEM:
		case ZBX_TM_DATA_TYPE_DIAGINFO:
			ret = tm_execute_data_json(data_type, row[1], &info, config_comms, config_startup_time);
			break;
		case ZBX_TM_DATA_TYPE_ACTIVE_PROXY_CONFIG_RELOAD:
			if (0 != (program_type & ZBX_PROGRAM_TYPE_PROXY_ACTIVE))
				ret = zbx_ipc_async_socket_send(rtc, ZBX_RTC_CONFIG_CACHE_RELOAD, NULL, 0);
			break;
		default:
			task->data = zbx_tm_data_result_create(parent_taskid, FAIL, "Unknown task.");
			goto finish;
	}

	task->data = zbx_tm_data_result_create(parent_taskid, ret, info);

	zbx_free(info);
finish:
	zbx_db_free_result(result);

	zbx_db_begin();

	if (NULL != task)
	{
		zbx_tm_save_task(task);
		zbx_tm_task_free(task);
	}

	zbx_db_execute("update task set status=%d where taskid=" ZBX_FS_UI64, ZBX_TM_STATUS_DONE, taskid);

	zbx_db_commit();

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: process task manager tasks depending on task type                 *
 *                                                                            *
 * Return value: The number of successfully processed tasks                   *
 *                                                                            *
 ******************************************************************************/
static int	tm_process_tasks(zbx_ipc_async_socket_t *rtc, int now, const zbx_config_comms_args_t *config_comms,
		int config_startup_time)
{
	DB_ROW			row;
	DB_RESULT		result;
	int			processed_num = 0, clock, ttl;
	zbx_uint64_t		taskid;
	unsigned char		type;
	zbx_vector_uint64_t	check_now_taskids;

	zbx_vector_uint64_create(&check_now_taskids);

	result = zbx_db_select("select taskid,type,clock,ttl"
				" from task"
				" where status=%d"
					" and type in (%d, %d, %d)"
				" order by taskid",
			ZBX_TM_STATUS_NEW, ZBX_TM_TASK_REMOTE_COMMAND, ZBX_TM_TASK_CHECK_NOW, ZBX_TM_TASK_DATA);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(taskid, row[0]);
		ZBX_STR2UCHAR(type, row[1]);
		clock = atoi(row[2]);
		ttl = atoi(row[3]);

		switch (type)
		{
			case ZBX_TM_TASK_REMOTE_COMMAND:
				if (SUCCEED == tm_execute_remote_command(taskid, clock, ttl, now,
						config_comms->config_timeout))
				{
					processed_num++;
				}
				break;
			case ZBX_TM_TASK_CHECK_NOW:
				zbx_vector_uint64_append(&check_now_taskids, taskid);
				break;
			case ZBX_TM_TASK_DATA:
				if (SUCCEED == tm_execute_data(rtc, taskid, clock, ttl, now, config_comms,
						config_startup_time))
					processed_num++;
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				break;
		}
	}
	zbx_db_free_result(result);

	if (0 < check_now_taskids.values_num)
		processed_num += tm_process_check_now(&check_now_taskids);

	zbx_vector_uint64_destroy(&check_now_taskids);

	return processed_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: remove old done/expired tasks                                     *
 *                                                                            *
 ******************************************************************************/
static void	tm_remove_old_tasks(int now)
{
	zbx_db_begin();
	zbx_db_execute("delete from task where status in (%d,%d) and clock<=%d",
			ZBX_TM_STATUS_DONE, ZBX_TM_STATUS_EXPIRED, now - ZBX_TM_CLEANUP_TASK_AGE);
	zbx_db_commit();
}

/******************************************************************************
 *                                                                            *
 * Purpose: create config cache reload request to be sent to the server       *
 *          (only from passive proxy)                                         *
 *                                                                            *
 ******************************************************************************/
static void	force_config_sync(void)
{
	zbx_tm_task_t	*task;
	zbx_uint64_t	taskid;
	struct zbx_json	j;

	taskid = zbx_db_get_maxid("task");

	zbx_db_begin();

	task = zbx_tm_task_create(taskid, ZBX_TM_PROXYDATA, ZBX_TM_STATUS_NEW, (int)time(NULL), 0, 0);

	zbx_json_init(&j, 1024);
	zbx_json_addstring(&j, ZBX_PROTO_TAG_PROXY_NAME, CONFIG_HOSTNAME, ZBX_JSON_TYPE_STRING);
	zbx_json_close(&j);

	task->data = zbx_tm_data_create(taskid, j.buffer, j.buffer_size, ZBX_TM_DATA_TYPE_PROXY_HOSTNAME);

	zbx_tm_save_task(task);

	zbx_db_commit();

	zbx_tm_task_free(task);
	zbx_json_free(&j);
}

ZBX_THREAD_ENTRY(taskmanager_thread, args)
{
	zbx_thread_taskmanager_args	*taskmanager_args_in = (zbx_thread_taskmanager_args *)
							(((zbx_thread_args_t *)args)->args);
	static int			cleanup_time = 0;

	double				sec1, sec2;
	int				tasks_num, sleeptime, nextcheck;
	zbx_ipc_async_socket_t		rtc;
	const zbx_thread_info_t		*info = &((zbx_thread_args_t *)args)->info;
	int				server_num = ((zbx_thread_args_t *)args)->info.server_num;
	int				process_num = ((zbx_thread_args_t *)args)->info.process_num;
	unsigned char			process_type = ((zbx_thread_args_t *)args)->info.process_type;

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			server_num, get_process_type_string(process_type), process_num);

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_init_child(taskmanager_args_in->config_comms->config_tls,
			taskmanager_args_in->zbx_get_program_type_cb_arg);
#endif
	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));
	zbx_db_connect(ZBX_DB_CONNECT_NORMAL);

	sec1 = zbx_time();

	sleeptime = ZBX_TM_PROCESS_PERIOD - (int)sec1 % ZBX_TM_PROCESS_PERIOD;

	zbx_setproctitle("%s [started, idle %d sec]", get_process_type_string(process_type), sleeptime);

	zbx_rtc_subscribe(process_type, process_num, taskmanager_args_in->config_comms->config_timeout, &rtc);

	while (ZBX_IS_RUNNING())
	{
		zbx_uint32_t	rtc_cmd;
		unsigned char	*rtc_data = NULL;

		if (SUCCEED == zbx_rtc_wait(&rtc, info, &rtc_cmd, &rtc_data, sleeptime) && 0 != rtc_cmd)
		{
#ifdef HAVE_NETSNMP
			if (ZBX_RTC_SNMP_CACHE_RELOAD == rtc_cmd)
				zbx_clear_cache_snmp(process_type, process_num);
#endif
			if (ZBX_RTC_CONFIG_CACHE_RELOAD == rtc_cmd &&
					ZBX_PROXYMODE_PASSIVE == taskmanager_args_in->config_comms->proxymode)
				force_config_sync();

			zbx_free(rtc_data);

			if (ZBX_RTC_SHUTDOWN == rtc_cmd)
				break;
		}

		sec1 = zbx_time();
		zbx_update_env(get_process_type_string(process_type), sec1);

		zbx_setproctitle("%s [processing tasks]", get_process_type_string(process_type));

		tasks_num = tm_process_tasks(&rtc, (int)sec1, taskmanager_args_in->config_comms,
				taskmanager_args_in->config_startup_time);
		if (ZBX_TM_CLEANUP_PERIOD <= sec1 - cleanup_time)
		{
			tm_remove_old_tasks((int)sec1);
			cleanup_time = sec1;
		}

		sec2 = zbx_time();

		nextcheck = (int)sec1 - (int)sec1 % ZBX_TM_PROCESS_PERIOD + ZBX_TM_PROCESS_PERIOD;

		if (0 > (sleeptime = nextcheck - (int)sec2))
			sleeptime = 0;

		zbx_setproctitle("%s [processed %d task(s) in " ZBX_FS_DBL " sec, idle %d sec]",
				get_process_type_string(process_type), tasks_num, sec2 - sec1, sleeptime);
	}

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
}
