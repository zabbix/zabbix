/*
** Copyright (C) 2001-2026 Zabbix SIA
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

#include "cep_manager.h"
#include "cep.h"
#include "cep_task.h"
#include "cep_worker.h"
#include "cep_queue.h"
#include "cep_api.h"
#include "zbxcep_client.h"
#include "zbxcep.h"

#include "zbxcommon.h"
#include "zbxipcservice.h"
#include "zbxsupervisor_client.h"
#include "zbxtimekeeper.h"
#include "zbxlog.h"
#include "zbxself.h"
#include "zbxnix.h"
#include "zbxrtc.h"
#include "zbxserialize.h"
#include "zbxalgo.h"
#include "zbxprof.h"
#include "zbx_rtc_constants.h"
#include "zbxthreads.h"
#include "zbxtime.h"

#define ZBX_CEP_WORKERS_MAX	100

typedef enum
{
	CEP_POOL_IDLE,
	CEP_POOL_GROWING,	/* worker thread(s) are being started */
	CEP_POOL_SHRINKING	/* worker thread(s) are being stopped */
}
zbx_cep_pool_state_t;

typedef struct
{
	zbx_cep_worker_t	*workers;

	/* actual number of active workers */
	int			workers_num;

	/* number of workers being started/stopped depending on pool_state */
	int			workers_diff;

	/* number of 10s ticks processors had less than 50% load */
	int			low_load_ticks;
	zbx_cep_pool_state_t	pool_state;

	zbx_timekeeper_t	*timekeeper;

	zbx_cep_queue_t		*queue;

	/* pending task commits */
	zbx_vector_cep_task_ptr_t	commits;
}
zbx_cep_manager_t;

/******************************************************************************
 *                                                                            *
 * Purpose: start event processors                                            *
 *                                                                            *
 * Parameters: manager     - [IN] event processor manager                     *
 *             workers_num - [IN] number of workers to start                  *
 *             error       - [OUT] error message                              *
 *                                                                            *
 * Return value: SUCCEED - workers started successfully                       *
 *               FAIL    - failed to start workers                            *
 *                                                                            *
 ******************************************************************************/
static int	cep_manager_start_workers(zbx_cep_manager_t *manager, int workers_num, char **error)
{
	if (CEP_POOL_IDLE != manager->pool_state)
		return SUCCEED;

	if (ZBX_CEP_WORKERS_MAX < manager->workers_num + workers_num)
	{
		if (0 == (workers_num = ZBX_CEP_WORKERS_MAX - manager->workers_num))
			return SUCCEED;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "starting %d event processors", workers_num);

	for (int i = manager->workers_num; i < manager->workers_num + workers_num; i++)
	{
		if (SUCCEED != cep_worker_start(&manager->workers[i], error))
			return FAIL;
	}

	manager->workers_diff = workers_num;
	manager->pool_state = CEP_POOL_GROWING;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: stop event processors                                             *
 *                                                                            *
 * Parameters: manager     - [IN] event processor manager                     *
 *             workers_num - [IN] number of workers to stop                   *
 *                                                                            *
 ******************************************************************************/
static void	cep_manager_stop_workers(zbx_cep_manager_t *manager, int workers_num)
{
	if (CEP_POOL_IDLE != manager->pool_state)
		return;

	if (manager->workers_num <= workers_num)
	{
		if (0 == (workers_num = manager->workers_num - 1))
			return;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "stopping %d event processor(s)", workers_num);

	for (int i = manager->workers_num - 1; i >= manager->workers_num - workers_num; i--)
		cep_worker_stop(&manager->workers[i]);

	manager->workers_diff = workers_num;
	manager->workers_num -= workers_num;
	manager->pool_state = CEP_POOL_SHRINKING;

	cep_queue_notify_all(manager->queue);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check event processor load and adjust worker pool size            *
 *                                                                            *
 * Parameters: manager - [IN/OUT] event processor manager                     *
 *                                                                            *
 * Return value: SUCCEED - usage checked and workers adjusted if needed       *
 *               FAIL    - failed to start workers                            *
 *                                                                            *
 ******************************************************************************/
static int	cep_manager_scale_workers(zbx_cep_manager_t *manager)
{
/* number of 10s ticks below 50% usage when start shrinking worker pool */
#define CEP_LOW_LOAD_SHRINK	30
#define CEP_LOW_LOAD_USAGE	50.0
#define CEP_HIGH_LOAD_USAGE	90.0
#define CEP_HIGH_LOAD_OFFSET	20.0

	double	usage = -1;
	char	*error = NULL;
	int	ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() workers_num:%d, low_load_ticks:%d", __func__, manager->workers_num,
			manager->low_load_ticks);

	if (SUCCEED == zbx_timekeeper_get_stat(manager->timekeeper, 0, manager->workers_num, ZBX_SELFMON_AGGR_FUNC_AVG,
		ZBX_PROCESS_STATE_BUSY, &usage, &error))
	{
		if (CEP_LOW_LOAD_USAGE < usage)
			manager->low_load_ticks = 0;
		else
			manager->low_load_ticks++;

		if (CEP_HIGH_LOAD_USAGE < usage)
		{
			int	num;

			num = MIN(ZBX_CEP_WORKERS_MAX - manager->workers_num, MIN(10, (manager->workers_num + 1) / 2));

			if (SUCCEED != (ret = cep_manager_start_workers(manager, num, &error)))
			{
				zabbix_log(LOG_LEVEL_ERR, "cannot start event processor: %s", error);
				zbx_free(error);

				goto out;
			}
		}
		else if (CEP_LOW_LOAD_SHRINK <= manager->low_load_ticks)
		{
			manager->low_load_ticks = 0;
			if (1 != manager->workers_num)
			{
				double	projected_usage = usage * manager->workers_num / (manager->workers_num - 1);

				if (CEP_HIGH_LOAD_USAGE - CEP_HIGH_LOAD_OFFSET > projected_usage)
					cep_manager_stop_workers(manager, 1);
			}
		}
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() usage:%.1f low_load_ticks:%d", __func__, usage,
			manager->low_load_ticks);

	return ret;

#undef CEP_LOW_LOAD_SHRINK
}

/******************************************************************************
 *                                                                            *
 * Purpose: check and manage the status of the preprocessing worker pool      *
 *                                                                            *
 * Parameters: manager - [IN] the CEP manager instance                        *
 *             now     - [IN] current timestamp                               *
 *                                                                            *
 * Return value: SUCCEED - pool status check completed successfully           *
 *                                                                            *
 * Comments: Internally throttled by CEP_POOL_STATUS_CHECK_DELAY.             *
 *                                                                            *
 ******************************************************************************/
static int	cep_manager_check_pool_status(zbx_cep_manager_t *manager, double now)
{
#define CEP_POOL_STATUS_CHECK_DELAY	10

	static double	last_check;

	if (CEP_POOL_STATUS_CHECK_DELAY > now - last_check)
		return SUCCEED;

	last_check = now;

	switch (manager->pool_state)
	{
		case CEP_POOL_IDLE:
			return cep_manager_scale_workers(manager);

		case CEP_POOL_GROWING:
			for (int i = manager->workers_num; i < manager->workers_num + manager->workers_diff; i++)
			{
				if (0 == (atomic_load(&manager->workers[i].state) & ZBX_CEP_WORKER_STATE_RUNNING))
					return SUCCEED;
			}
			manager->workers_num += manager->workers_diff;
			break;

		case CEP_POOL_SHRINKING:
			for (int i = manager->workers_num; i < manager->workers_num + manager->workers_diff; i++)
			{
				if (0 == (atomic_load(&manager->workers[i].state) & ZBX_CEP_WORKER_STATE_STOPPED))
					return SUCCEED;
			}

			for (int i = manager->workers_num; i < manager->workers_num + manager->workers_diff; i++)
				cep_worker_join(&manager->workers[i]);

			/* when shrinking the requested number of workers is set immediately */
			break;
	}

	manager->workers_diff = 0;
	manager->pool_state = CEP_POOL_IDLE;

	return SUCCEED;

#undef CEP_POOL_STATUS_CHECK_DELAY
}

/******************************************************************************
 *                                                                            *
 * Purpose: free CEP manager resources                                        *
 *                                                                            *
 * Parameters: manager - [IN] the CEP manager instance                        *
 *                                                                            *
 ******************************************************************************/
static void	cep_manager_free(zbx_cep_manager_t *manager)
{
	/* workers are created after queue, if queue failed there will be no workers to stop/destroy */
	if (NULL != manager->queue)
	{
		cep_queue_lock(manager->queue);

		for (int i = 0; i < ZBX_CEP_WORKERS_MAX; i++)
			cep_worker_stop(&manager->workers[i]);

		cep_queue_notify_all(manager->queue);

		cep_queue_unlock(manager->queue);

		for (int i = 0; i < ZBX_CEP_WORKERS_MAX; i++)
			cep_worker_destroy(&manager->workers[i]);

		zbx_free(manager->workers);
	}

	zbx_timekeeper_free(manager->timekeeper);

	cep_api_destroy();

	if (NULL != manager->queue)
		cep_queue_destroy(manager->queue);

	zbx_vector_cep_task_ptr_clear_ext(&manager->commits, cep_task_free);
	zbx_vector_cep_task_ptr_destroy(&manager->commits);

	zbx_free(manager);
}

/******************************************************************************
 *                                                                            *
 * Purpose: create and initialize CEP manager instance                        *
 *                                                                            *
 * Parameters: workers_num - [IN] initial number of workers                   *
 *             dbpool      - [IN] database connection pool                    *
 *             error       - [OUT] error message                              *
 *                                                                            *
 * Return value: pointer to the created CEP manager instance or NULL on       *
 *               error                                                        *
 *                                                                            *
 ******************************************************************************/
static zbx_cep_manager_t	*cep_manager_create(int workers_num, zbx_dbconn_pool_t *dbpool,
		zbx_ipc_service_t *service, char **error)
{
	zbx_cep_manager_t	*manager;
	int			ret = FAIL;
	zbx_cep_t		*cep;

	manager = (zbx_cep_manager_t *)zbx_malloc(NULL, sizeof(zbx_cep_manager_t));
	memset(manager, 0, sizeof(zbx_cep_manager_t));

	manager->pool_state = CEP_POOL_IDLE;
	manager->timekeeper = zbx_timekeeper_create(ZBX_CEP_WORKERS_MAX, NULL);

	if (FAIL == cep_api_init(error))
		goto out;

	if (NULL == (manager->queue = cep_queue_create(error)))
		goto out;

	cep_cache_acquire(&cep);
	cep_init(cep, dbpool);
	cep_dump(cep, "cache initialization");
	cep_cache_release(&cep);

	manager->workers = (zbx_cep_worker_t *)zbx_calloc(NULL, (size_t)ZBX_CEP_WORKERS_MAX, sizeof(zbx_cep_worker_t));

	zbx_vector_cep_task_ptr_create(&manager->commits);

	for (int i = 0; i < ZBX_CEP_WORKERS_MAX; i++)
	{
		cep_worker_init(&manager->workers[i], i + 1, manager->timekeeper, manager->queue, dbpool, service);
	}

	if (FAIL == cep_manager_start_workers(manager, workers_num, error))
		goto out;

	ret = SUCCEED;
out:
	if (SUCCEED != ret)
	{
		cep_manager_free(manager);
		manager = NULL;
	}

	return manager;
}

/******************************************************************************
 *                                                                            *
 * Purpose: change worker log level                                           *
 *                                                                            *
 ******************************************************************************/
static void	cep_change_worker_loglevel(zbx_cep_manager_t *manager, int worker_num, int direction)
{
	if (0 > worker_num || manager->workers_num < worker_num)
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "Cannot change log level for preprocessing worker #%d:"
				" no such instance", worker_num);
		return;
	}

	for (int i = 0; i < ZBX_CEP_WORKERS_MAX; i++)
	{
		if (0 != worker_num && worker_num != i + 1)
			continue;

		if (i < manager->workers_num)
			zbx_change_component_log_level(&manager->workers[i].logger, direction);
		else
			zbx_change_component_log_level_silent(&manager->workers[i].logger, direction);
	}}

/******************************************************************************
 *                                                                            *
 * Purpose: change log level for the specified worker(s)                      *
 *                                                                            *
 * Parameters: manager   - [IN] CEP manager                                   *
 *             direction - [IN] 1) increase, -1) decrease                     *
 *             data      - [IN] rtc data in json format                       *
 *                                                                            *
 ******************************************************************************/
static void	cep_manager_change_loglevel(zbx_cep_manager_t *manager, int direction, const char *data)
{
	char	*error = NULL;
	pid_t	pid;
	int	proc_type, proc_num;

	if (SUCCEED != zbx_rtc_get_command_target(data, &pid, &proc_type, &proc_num, NULL, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "Cannot change log level: %s", error);
		zbx_free(error);
		return;
	}

	if (0 != pid)
	{
		zabbix_log(LOG_LEVEL_WARNING, "Cannot change log level for event processor by pid");
		return;
	}

	cep_change_worker_loglevel(manager, proc_num, direction);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add remote task to CEP manager                                    *
 *                                                                            *
 * Parameters: manager      - [IN]  CEP manager instance                      *
 *             client       - [IN]  IPC client                                *
 *             message      - [IN]  IPC message                               *
 *             response     - [IN]  response buffer                           *
 *             response_len - [IN]  length of the response buffer             *
 *                                                                            *
 ******************************************************************************/
static void	cep_manager_add_remote_task(zbx_cep_manager_t *manager, zbx_ipc_client_t **client,
		zbx_ipc_message_t **message, unsigned char *response, zbx_uint32_t response_len)
{
	zbx_cep_task_t	*task;

	task = cep_create_task_remote(*client, *message, response, response_len);

	cep_queue_push(manager->queue, task);

	*client = NULL;
	*message = NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add assess trigger events task to CEP manager                     *
 *                                                                            *
 * Parameters: manager - [IN] CEP manager instance                            *
 *             client  - [IN] IPC client handle                               *
 *             message - [IN] IPC message descriptor                          *
 *                                                                            *
 ******************************************************************************/
static void	cep_manager_add_assess_trigger_events(zbx_cep_manager_t *manager, zbx_ipc_client_t **client,
	zbx_ipc_message_t **message)
{
	unsigned char*	response;
	int		queries_num;

	queries_num = zbx_cep_peek_event_queries((*message)->data);
	response = (unsigned char*)zbx_malloc(NULL, (size_t)queries_num);

	cep_manager_add_remote_task(manager, client, message, response, (zbx_uint32_t)queries_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: send remote CEP task response if available                        *
 *                                                                            *
 * Parameters: task - [IN] remote CEP task descriptor                         *
 *                                                                            *
 ******************************************************************************/
static void	cep_manager_flush_remote_task(zbx_cep_task_remote_t *task)
{
	if (NULL != task->response)
		zbx_ipc_client_send(task->client, task->message->code, task->response, task->response_len);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process finished CEP tasks and handle commits                     *
 *                                                                            *
 * Parameters: manager - [IN] CEP manager instance                            *
 *             tasks   - [IN] vector of finished CEP tasks                    *
 *                                                                            *
 * Comments: Remote tasks are flushed, event-related tasks with pending       *
 *           operations are queued for commit, and other tasks are freed.     *
 *                                                                            *
 ******************************************************************************/
static void	cep_manager_process_finished(zbx_cep_manager_t *manager, zbx_vector_cep_task_ptr_t *tasks)
{
	for (int i = 0; i < tasks->values_num; i++)
	{
		switch (tasks->values[i]->type)
		{
			case CEP_TASK_REMOTE:
				cep_manager_flush_remote_task((zbx_cep_task_remote_t *)tasks->values[i]);
				break;
			case CEP_TASK_EVENT:
			case CEP_TASK_CLOSE_EVENT:
				if (CEP_EVENT_NONE != cep_get_event_task(tasks->values[i])->event_op)
				{
					zbx_vector_cep_task_ptr_append(&manager->commits, tasks->values[i]);
					continue;
				}
				break;
			case CEP_TASK_COMMIT:
				break;
			case CEP_TASK_ADD_TAGS:
				zbx_vector_cep_task_ptr_append(&manager->commits, tasks->values[i]);
				continue;
		}

		cep_task_free(tasks->values[i]);
	}

	zbx_vector_cep_task_ptr_clear(tasks);
}

/******************************************************************************
 *                                                                            *
 * Purpose: enqueue commit task for pending CEP operations                    *
 *                                                                            *
 * Parameters: manager - [IN] CEP manager instance                            *
 *                                                                            *
 * Comments: Creates a commit task for all queued commit operations and       *
 *           pushes it to the CEP manager queue.                              *
 *                                                                            *
 ******************************************************************************/
static void	cep_manager_flush_commmits(zbx_cep_manager_t *manager)
{
	zbx_cep_task_t	*task = cep_create_task_commit(&manager->commits);
	cep_queue_push(manager->queue, task);
}

/******************************************************************************
 *                                                                            *
 * Purpose: send the number of CEP workers to the client                      *
 *                                                                            *
 * Parameters: manager - [IN] CEP manager instance containing worker count    *
 *             client  - [IN] IPC client to send the response to              *
 *                                                                            *
 ******************************************************************************/
static void	cep_manager_get_workers_num(zbx_cep_manager_t *manager, zbx_ipc_client_t *client)
{
	unsigned char	data[sizeof(int)];

	(void)zbx_serialize_value(data, manager->workers_num);

	zbx_ipc_client_send(client, ZBX_CEP_GET_WORKERS_NUM, data, (zbx_uint32_t)sizeof(data));
}

/******************************************************************************
 *                                                                            *
 * Purpose: send serialized CEP worker usage statistics to the client         *
 *                                                                            *
 * Parameters: manager - [IN] CEP manager instance holding usage data         *
 *             client  - [IN] IPC client to send the statistics to            *
 *                                                                            *
 ******************************************************************************/
static void	cep_manager_get_usage_stats(zbx_cep_manager_t *manager, zbx_ipc_client_t *client)
{
	unsigned char		*data;
	zbx_uint32_t		data_len;
	zbx_vector_dbl_t	usage;

	zbx_vector_dbl_create(&usage);
	(void)zbx_timekeeper_get_usage(manager->timekeeper, &usage);

	data_len = zbx_cep_serialize_usage_stats(&data, &usage, manager->workers_num);

	zbx_ipc_client_send(client, ZBX_CEP_GET_USAGE_STATS, data, data_len);

	zbx_free(data);
	zbx_vector_dbl_destroy(&usage);
}

/******************************************************************************
 *                                                                            *
 * Purpose: CEP manager main thread function                                  *
 *                                                                            *
 * Parameters: args - [IN] thread arguments                                   *
 *                                                                            *
 * Return value: NULL                                                         *
 *                                                                            *
 ******************************************************************************/
void	*zbx_cep_manager_thread(void *args)
{
#define CEP_MANAGER_DELAY_SEC		0
#define CEP_MANAGER_DELAY_NS		5e8
#define CEP_MANAGER_BATCH_LIMIT		1000
#define CEP_MANAGER_FLUSH_TIMEOUT	1.0

	zbx_ipc_service_t			service;
	char					*error = NULL, *process_title;
	zbx_ipc_client_t			*client;
	zbx_ipc_message_t			*message;
	double					time_stat, time_idle = 0, time_timekeeper = 0, time_flush;
	zbx_timespec_t				timeout = {CEP_MANAGER_DELAY_SEC, CEP_MANAGER_DELAY_NS};
	zbx_supervisor_unit_args_t		*unit_args = (zbx_supervisor_unit_args_t *)args;
	const zbx_thread_info_t			*info = &unit_args->args.info;
	int					server_num = info->server_num,
						process_num = info->process_num;
	unsigned char				process_type = info->process_type;
	const zbx_thread_cep_manager_args_t	*cep_args = (const zbx_thread_cep_manager_args_t *)unit_args->args.args;
	zbx_cep_manager_t			*manager;
	zbx_uint32_t				rtc_msgs[] = {ZBX_RTC_LOG_LEVEL_INCREASE, ZBX_RTC_LOG_LEVEL_DECREASE};
	sigjmp_buf				jmp_ret;
	zbx_vector_cep_task_ptr_t		tasks;


#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
				/* once in STAT_INTERVAL seconds */

	process_title = zbx_dsprintf(NULL, "%s #%d", get_process_type_string(process_type), process_num);
	zbx_set_log_component(process_title, unit_args->logger);

	zbx_supervisor_update_activity("%s starting", process_title);

	zabbix_log(LOG_LEVEL_INFORMATION, "%s #%d started", get_program_type_string(info->program_type), server_num);

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

	ZBX_INIT_THREAD_OR_RETURN(jmp_ret);

	zbx_vector_cep_task_ptr_create(&tasks);

	if (FAIL == zbx_ipc_service_start(&service, ZBX_IPC_SERVICE_CEP, &error))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot start CEP service: %s", error);
		zbx_free(error);
		zbx_exit(EXIT_FAILURE);
	}

	if (NULL == (manager = cep_manager_create(cep_args->workers_num, unit_args->shared->dbpool, &service, &error)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize CEP manager: %s", error);
		zbx_free(error);
		zbx_exit(EXIT_FAILURE);
	}

	/* subscribe for worker log level rtc messages */
	zbx_rtc_subscribe_service(ZBX_PROCESS_TYPE_CEP_WORKER, 0, rtc_msgs, ARRSIZE(rtc_msgs),
			cep_args->config_timeout, ZBX_IPC_SERVICE_CEP);

	/* initialize statistics */
	time_stat = zbx_time();

	zbx_supervisor_update_activity("%s #%d started", get_process_type_string(process_type), process_num);

	zbx_supervisor_set_process_running(server_num);

	time_flush = zbx_time();

	while (1)
	{
		int		shutdown = 0, pending_num;
		double		time_start = zbx_time();

		if (STAT_INTERVAL < time_start - time_stat)
		{
			// TODO: update activity in supervisor
			time_stat = time_start;
			time_idle = 0;
		}

		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_IDLE);

		int	ret = zbx_ipc_service_recv(&service, &timeout, &client, &message);

		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

		double	time_now = zbx_time();

		zbx_prof_update(get_process_type_string(process_type), time_now);

		if (ZBX_IPC_RECV_IMMEDIATE != ret)
			time_idle += time_now - time_start;

		if (NULL != message)
		{
			switch (message->code)
			{
				case ZBX_RTC_LOG_LEVEL_INCREASE:
					cep_manager_change_loglevel(manager, 1, (const char *)message->data);
					break;
				case ZBX_RTC_LOG_LEVEL_DECREASE:
					cep_manager_change_loglevel(manager, -1, (const char *)message->data);
					break;
				case ZBX_CEP_ASSESS_TRIGGER_EVENTS:
					cep_manager_add_assess_trigger_events(manager, &client, &message);
					break;
				case ZBX_CEP_ADD_EVENTS:
				case ZBX_CEP_ADD_USER_CLOSE_EVENT:
				case ZBX_CEP_SUPPRESS_EVENTS:
				case ZBX_CEP_UNSUPPRESS_EVENTS:
				case ZBX_CEP_UPDATE_SEVERITIES:
				case ZBX_CEP_ADD_EVENT_TAGS:
				case ZBX_CEP_DELETE_EVENTS:
					cep_manager_add_remote_task(manager, &client, &message, NULL, 0);
					break;
				case ZBX_CEP_GET_WORKERS_NUM:
					cep_manager_get_workers_num(manager, client);
					break;
				case ZBX_CEP_GET_USAGE_STATS:
					cep_manager_get_usage_stats(manager, client);
					break;
				case ZBX_RTC_SHUTDOWN:
					zabbix_log(LOG_LEVEL_DEBUG, "shutdown message received, terminating...");
					shutdown = 1;
					break;
			}

			zbx_ipc_message_free(message);
		}

		if (NULL != client)
			zbx_ipc_client_release(client);

		if (!ZBX_IS_RUNNING() || 1 == shutdown)
			break;

		pending_num = cep_queue_pop_finished(manager->queue, &tasks);
		if (0 != tasks.values_num)
			cep_manager_process_finished(manager, &tasks);

		if (0 != manager->commits.values_num)
		{
			if ( CEP_MANAGER_BATCH_LIMIT <= manager->commits.values_num || 0 == pending_num ||
					CEP_MANAGER_FLUSH_TIMEOUT < time_now - time_flush)
			{
				cep_manager_flush_commmits(manager);
				time_flush = time_now;
			}
		}

		if (0.5 < time_now - time_timekeeper)
		{
			zbx_timekeeper_collect(manager->timekeeper);
			time_timekeeper = time_now;
		}

		if (SUCCEED != cep_manager_check_pool_status(manager, time_now))
		{
			zbx_set_exiting_with_fail();
			break;
		}
	}

	zbx_supervisor_update_activity("%s [terminating]", process_title);

	/* on normal exit the shutdown message already has been processed and no more messages will be sent */
	if (SUCCEED != ZBX_EXIT_STATUS())
		zbx_rtc_unsubscribe_service(cep_args->config_timeout, ZBX_IPC_SERVICE_CEP);

	zbx_ipc_service_close(&service);
	zbx_free(args);

	zbx_supervisor_update_activity("%s [terminated]", process_title);

	zbx_deinit_regexp_env();

	zbx_vector_cep_task_ptr_destroy(&tasks);

	cep_manager_free(manager);
	zbx_free(process_title);
	zbx_free(args);

#undef STAT_INTERVAL

	return NULL;
}
