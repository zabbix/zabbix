/*
** Copyright (C) 2001-2024 Zabbix SIA
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

#include "../poller/poller_proxy.h"

#include "zbxtimekeeper.h"
#include "zbxtrapper.h"
#include "zbxscripts.h"
#include "zbxnix.h"
#include "zbxself.h"
#include "zbxtasks.h"
#include "zbxlog.h"
#include "zbxdiag.h"
#include "zbxrtc.h"
#include "zbxdbwrap.h"
#include "zbxcacheconfig.h"
#include "zbxnum.h"
#include "zbxtime.h"
#include "zbx_rtc_constants.h"
#include "zbxalgo.h"
#include "zbxdb.h"
#include "zbxipcservice.h"
#include "zbxjson.h"
#include "zbxstr.h"
#include "zbx_scripts_constants.h"
#include "zbx_item_constants.h"

#ifdef HAVE_NETSNMP
#	include "zbxpoller.h"
#endif

/**************************************************************************************
 *                                                                                    *
 * Purpose: executes remote command task                                              *
 *                                                                                    *
 * Parameters: taskid                        - [IN]                                   *
 *             clock                         - [IN] task creation time                *
 *             ttl                           - [IN] task expiration period in seconds *
 *             now                           - [IN]                                   *
 *             config_timeout                - [IN]                                   *
 *             config_trapper_timeout        - [IN]                                   *
 *             config_source_ip              - [IN]                                   *
 *             config_ssh_key_location       - [IN]                                   *
 *             config_enable_remote_commands - [IN]                                   *
 *             config_log_remote_commands    - [IN]                                   *
 *             config_enable_global_scripts  - [IN]                                   *
 *             get_config_forks              - [IN]                                   *
 *             program_type                  - [IN]                                   *
 *                                                                                    *
 * Return value: SUCCEED - remote command was executed                                *
 *               FAIL    - otherwise                                                  *
 *                                                                                    *
 **************************************************************************************/
static int	tm_execute_remote_command(zbx_uint64_t taskid, int clock, int ttl, time_t now, int config_timeout,
		int config_trapper_timeout, const char *config_source_ip, const char *config_ssh_key_location,
		int config_enable_remote_commands, int config_log_remote_commands, int config_enable_global_scripts,
		zbx_get_config_forks_f get_config_forks, unsigned char program_type)
{
	zbx_db_row_t	row;
	zbx_uint64_t	parent_taskid, hostid, alertid;
	int		ret = FAIL;
	zbx_script_t	script;
	char		*info = NULL, error[MAX_STRING_LEN];
	zbx_dc_host_t	host;

	zbx_db_result_t	result = zbx_db_select("select command_type,execute_on,port,authtype,username,password,"
					"publickey,privatekey,command,parent_taskid,hostid,alertid"
				" from task_remote_command"
				" where taskid=" ZBX_FS_UI64,
				taskid);
	zbx_tm_task_t	*task = NULL;
	double		t;

	if (NULL == (row = zbx_db_fetch(result)))
		goto finish;

	t = zbx_time();

	task = zbx_tm_task_create(0, ZBX_TM_TASK_REMOTE_COMMAND_RESULT, ZBX_TM_STATUS_NEW, (time_t)t,
			0, 0);

	ZBX_STR2UINT64(parent_taskid, row[9]);

	if (0 != ttl && clock + ttl < now)
	{
		task->data = zbx_tm_remote_command_result_create(parent_taskid, FAIL,
				"The remote command has been expired.");
		goto finish;
	}

	ZBX_STR2UINT64(hostid, row[10]);
	if (FAIL == zbx_dc_get_host_by_hostid(&host, hostid))
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
			if (0 == config_enable_remote_commands)
			{
				task->data = zbx_tm_remote_command_result_create(parent_taskid, FAIL,
						"Remote commands are not enabled");
				goto finish;
			}

			if (1 == config_log_remote_commands)
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

	if (SUCCEED != (ret = zbx_script_execute(&script, &host, NULL, config_timeout, config_trapper_timeout,
			config_source_ip, config_ssh_key_location, config_enable_global_scripts, get_config_forks,
			program_type, 0 == alertid ? &info : NULL, error, sizeof(error), NULL)))
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
 * Purpose: processes 'check now' tasks for item rescheduling                 *
 *                                                                            *
 * Return value: number of successfully processed tasks                       *
 *                                                                            *
 ******************************************************************************/
static int	tm_process_check_now(zbx_vector_uint64_t *taskids)
{
	zbx_db_row_t		row;
	zbx_db_result_t		result;
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
	{
		double t = zbx_time();
		zbx_dc_reschedule_items(&itemids, (time_t)t, NULL);
	}

	if (0 != taskids->values_num)
	{
		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "update task set status=%d where",
				ZBX_TM_STATUS_DONE);
		zbx_db_add_condition_alloc(&sql, &sql_alloc, &sql_offset, "taskid", taskids->values,
				taskids->values_num);

		zbx_db_execute("%s", sql);
	}

	zbx_free(sql);
	zbx_vector_uint64_destroy(&itemids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() processed:%d", __func__, processed_num);

	return processed_num;
}

static int	tm_execute_test_item(struct zbx_json_parse *jp_data, const zbx_config_comms_args_t *config_comms,
		int config_startup_time, unsigned char program_type, const char *progname,
		zbx_get_config_forks_f get_config_forks,  const char *config_java_gateway,
		int config_java_gateway_port, const char *config_externalscripts, const char *config_ssh_key_location,
		const char *config_webdriver_url, char **info)
{
	int			ret, state;
	struct zbx_json		json;
	struct zbx_json_parse	jp_options, jp_steps, jp_item;
	char			*value = NULL, *error = NULL, buf[32];
	size_t			value_size;

	ret = zbx_trapper_item_test_run(jp_data, 0, &value, config_comms,
			config_startup_time, program_type, progname, get_config_forks,
			config_java_gateway, config_java_gateway_port, config_externalscripts,
			zbx_get_value_internal_ext_proxy, config_ssh_key_location, config_webdriver_url);

	if (NULL == value)
	{
		*info = zbx_strdup(NULL, "No value returned.");
		return FAIL;
	}

	if (FAIL == zbx_json_value_by_name(jp_data, ZBX_PROTO_TAG_PREPROC, buf, sizeof(buf), NULL) || 1 != atoi(buf))
	{
		*info = value;
		return ret;
	}

	if (FAIL == zbx_json_brackets_by_name(jp_data, ZBX_PROTO_TAG_ITEM, &jp_item))
	{
		zbx_free(value);
		*info = zbx_strdup(NULL, "Missing item field.");
		return FAIL;
	}

	zbx_json_init(&json, 1024);

	zbx_trapper_item_test_add_value(&json, ret, value);
	value_size = strlen(value);

	if (FAIL == zbx_json_brackets_by_name(jp_data, ZBX_PROTO_TAG_OPTIONS, &jp_options))
		zbx_json_open("{}", &jp_options);

	if (FAIL == zbx_json_brackets_by_name(&jp_item, ZBX_PROTO_TAG_STEPS, &jp_steps))
		jp_steps.end = jp_steps.start = NULL;

	if (FAIL == ret)
		state = ITEM_STATE_NOTSUPPORTED;
	else if (SUCCEED == zbx_json_value_by_name(&jp_options, ZBX_PROTO_TAG_STATE, buf, sizeof(buf), NULL))
		state = atoi(buf);
	else
		state = ITEM_STATE_NORMAL;

	zbx_json_addobject(&json, ZBX_PROTO_TAG_PREPROCESSING);
	if (SUCCEED == (ret = zbx_trapper_preproc_test_run(&jp_item, &jp_options, &jp_steps, value, value_size, state,
			&json, &error)))
	{
		*info = zbx_strdup(NULL, json.buffer);
	}
	else
		*info = error;

	zbx_json_free(&json);
	zbx_free(value);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes data task with json contents                            *
 *                                                                            *
 * Return value: SUCCEED - data task was executed                             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	tm_execute_data_json(int type, const char *data, char **info,
		const zbx_config_comms_args_t *config_comms, int config_startup_time, unsigned char program_type,
		const char *progname, zbx_get_config_forks_f get_config_forks,  const char *config_java_gateway,
		int config_java_gateway_port, const char *config_externalscripts, const char *config_ssh_key_location,
		const char *config_webdriver_url)
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
			return tm_execute_test_item(&jp_data, config_comms, config_startup_time, program_type, progname,
					get_config_forks, config_java_gateway, config_java_gateway_port,
					config_externalscripts, config_ssh_key_location, config_webdriver_url, info);
		case ZBX_TM_DATA_TYPE_DIAGINFO:
			return zbx_diag_get_info(&jp_data, info);
	}

	THIS_SHOULD_NEVER_HAPPEN;

	*info = zbx_strdup(*info, "Unknown task data type");
	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes data task                                               *
 *                                                                            *
 * Return value: SUCCEED - data task was executed                             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	tm_execute_data(zbx_ipc_async_socket_t *rtc, zbx_uint64_t taskid, int clock, int ttl, time_t now,
		const zbx_config_comms_args_t *config_comms, int config_startup_time,
		unsigned char program_type, const char *progname, zbx_get_config_forks_f get_config_forks,
		const char *config_java_gateway, int config_java_gateway_port, const char *config_externalscripts,
		const char *config_ssh_key_location, const char *config_webdriver_url)
{
	zbx_db_row_t		row;
	zbx_tm_task_t		*task = NULL;
	int			ret = FAIL, data_type;
	char			*info = NULL;
	zbx_uint64_t		parent_taskid;
	double			t;

	zbx_db_result_t		result = zbx_db_select("select parent_taskid,data,type"
				" from task_data"
				" where taskid=" ZBX_FS_UI64,
				taskid);

	if (NULL == (row = zbx_db_fetch(result)))
		goto finish;

	t = zbx_time();
	task = zbx_tm_task_create(0, ZBX_TM_TASK_DATA_RESULT, ZBX_TM_STATUS_NEW, (time_t)t, 0, 0);

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
			ret = tm_execute_data_json(data_type, row[1], &info, config_comms, config_startup_time,
					program_type, progname, get_config_forks, config_java_gateway,
					config_java_gateway_port, config_externalscripts, config_ssh_key_location,
					config_webdriver_url);
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
 * Purpose: processes task manager tasks depending on task type               *
 *                                                                            *
 * Return value: number of successfully processed tasks                       *
 *                                                                            *
 ******************************************************************************/
static int	tm_process_tasks(zbx_ipc_async_socket_t *rtc, time_t now, const zbx_config_comms_args_t *config_comms,
		int config_startup_time, int config_enable_remote_commands, int config_log_remote_commands,
		unsigned char program_type, const char *progname, zbx_get_config_forks_f get_config_forks,
		const char *config_java_gateway, int config_java_gateway_port, const char *config_externalscripts,
		int config_enable_global_scripts, const char *config_ssh_key_location, const char *config_webdriver_url)
{
	zbx_db_row_t		row;
	int			processed_num = 0, clock, ttl;
	zbx_uint64_t		taskid;
	unsigned char		type;
	zbx_vector_uint64_t	check_now_taskids;

	zbx_vector_uint64_create(&check_now_taskids);

	zbx_db_result_t		result = zbx_db_select("select taskid,type,clock,ttl"
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
						config_comms->config_timeout, config_comms->config_trapper_timeout,
						config_comms->config_source_ip, config_ssh_key_location,
						config_enable_remote_commands, config_log_remote_commands,
						config_enable_global_scripts, get_config_forks, program_type))
				{
					processed_num++;
				}
				break;
			case ZBX_TM_TASK_CHECK_NOW:
				zbx_vector_uint64_append(&check_now_taskids, taskid);
				break;
			case ZBX_TM_TASK_DATA:
				if (SUCCEED == tm_execute_data(rtc, taskid, clock, ttl, now, config_comms,
						config_startup_time, program_type, progname, get_config_forks,
						config_java_gateway, config_java_gateway_port, config_externalscripts,
						config_ssh_key_location, config_webdriver_url))
				{
					processed_num++;
				}
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
 * Purpose: removes old done/expired tasks                                    *
 *                                                                            *
 ******************************************************************************/
static void	tm_remove_old_tasks(time_t now)
{
	zbx_db_begin();
	zbx_db_execute("delete from task where status in (%d,%d) and clock<=" ZBX_FS_TIME_T,
			ZBX_TM_STATUS_DONE, ZBX_TM_STATUS_EXPIRED, (zbx_fs_time_t)(now - ZBX_TM_CLEANUP_TASK_AGE));
	zbx_db_commit();
}

/******************************************************************************
 *                                                                            *
 * Purpose: Creates config cache reload request to be sent to the server      *
 *          (only from passive proxy).                                        *
 *                                                                            *
 ******************************************************************************/
static void	force_config_sync(const char *config_hostname)
{
	struct zbx_json	j;
	zbx_uint64_t	taskid = zbx_db_get_maxid("task");

	zbx_db_begin();

	zbx_tm_task_t	*task = zbx_tm_task_create(taskid, ZBX_TM_PROXYDATA, ZBX_TM_STATUS_NEW, time(NULL), 0, 0);

	zbx_json_init(&j, 1024);
	zbx_json_addstring(&j, ZBX_PROTO_TAG_PROXY_NAME, config_hostname, ZBX_JSON_TYPE_STRING);
	zbx_json_close(&j);

	task->data = zbx_tm_data_create(taskid, j.buffer, j.buffer_size, ZBX_TM_DATA_TYPE_PROXYNAME);

	zbx_tm_save_task(task);

	zbx_db_commit();

	zbx_tm_task_free(task);
	zbx_json_free(&j);
}

ZBX_THREAD_ENTRY(taskmanager_thread, args)
{
#define ZBX_TM_PROCESS_PERIOD		5
#define ZBX_TM_CLEANUP_PERIOD		SEC_PER_HOUR
	zbx_thread_taskmanager_args	*taskmanager_args_in = (zbx_thread_taskmanager_args *)
							(((zbx_thread_args_t *)args)->args);
	static time_t			sleeptime, nextcheck;
	static double			cleanup_time = 0.0;
	double				sec1, sec2;
	zbx_ipc_async_socket_t		rtc;
	const zbx_thread_info_t		*info = &((zbx_thread_args_t *)args)->info;
	int				tasks_num, rtc_msgs_num = 1,
					server_num = ((zbx_thread_args_t *)args)->info.server_num,
					process_num = ((zbx_thread_args_t *)args)->info.process_num;
	unsigned char			process_type = ((zbx_thread_args_t *)args)->info.process_type;
	zbx_uint32_t			rtc_msgs[] = {ZBX_RTC_CONFIG_CACHE_RELOAD, ZBX_RTC_SNMP_CACHE_RELOAD};

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started [%s #%d]", get_program_type_string(info->program_type),
			server_num, get_process_type_string(process_type), process_num);

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

#if defined(HAVE_GNUTLS) || defined(HAVE_OPENSSL)
	zbx_tls_init_child(taskmanager_args_in->config_comms->config_tls,
			taskmanager_args_in->zbx_get_program_type_cb_arg, zbx_dc_get_psk_by_identity);
#endif
	zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));
	zbx_db_connect(ZBX_DB_CONNECT_NORMAL);

	sec1 = zbx_time();

	sleeptime = ZBX_TM_PROCESS_PERIOD - (time_t)sec1 % ZBX_TM_PROCESS_PERIOD;

	zbx_setproctitle("%s [started, idle " ZBX_FS_TIME_T " sec]", get_process_type_string(process_type),
			(zbx_fs_time_t)sleeptime);

#ifdef HAVE_NETSNMP
	rtc_msgs_num++;
#endif

	zbx_rtc_subscribe(process_type, process_num, rtc_msgs,rtc_msgs_num,
			taskmanager_args_in->config_comms->config_timeout, &rtc);

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
			{
				force_config_sync(taskmanager_args_in->config_hostname);
			}

			zbx_free(rtc_data);

			if (ZBX_RTC_SHUTDOWN == rtc_cmd)
				break;
		}

		sec1 = zbx_time();
		zbx_update_env(get_process_type_string(process_type), sec1);

		zbx_setproctitle("%s [processing tasks]", get_process_type_string(process_type));

		tasks_num = tm_process_tasks(&rtc, (time_t)sec1, taskmanager_args_in->config_comms,
				taskmanager_args_in->config_startup_time,
				taskmanager_args_in->config_enable_remote_commands,
				taskmanager_args_in->config_log_remote_commands, info->program_type,
				taskmanager_args_in->progname,
				taskmanager_args_in->get_process_forks_cb_arg,
				taskmanager_args_in->config_java_gateway,
				taskmanager_args_in->config_java_gateway_port,
				taskmanager_args_in->config_externalscripts,
				taskmanager_args_in->config_enable_global_scripts,
				taskmanager_args_in->config_ssh_key_location,
				taskmanager_args_in->config_webdriver_url);

		if (ZBX_TM_CLEANUP_PERIOD <= sec1 - cleanup_time)
		{
			tm_remove_old_tasks((time_t)sec1);
			cleanup_time = sec1;
		}

		sec2 = zbx_time();

		nextcheck = (time_t)sec1 - (time_t)sec1 % ZBX_TM_PROCESS_PERIOD + ZBX_TM_PROCESS_PERIOD;

		if (0 > (sleeptime = nextcheck - (time_t)sec2))
			sleeptime = 0;

		zbx_setproctitle("%s [processed %d task(s) in " ZBX_FS_DBL " sec, idle " ZBX_FS_TIME_T " sec]",
				get_process_type_string(process_type), tasks_num, sec2 - sec1,
				(zbx_fs_time_t)sleeptime);
	}

	zbx_setproctitle("%s #%d [terminated]", get_process_type_string(process_type), process_num);

	while (1)
		zbx_sleep(SEC_PER_MIN);
#undef ZBX_TM_PROCESS_PERIOD
#undef ZBX_TM_CLEANUP_PERIOD
}
