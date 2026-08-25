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

#include "mw_worker.h"
#include "mw_queue.h"
#include "zbxipcservice.h"
#include "zbxlog.h"
#include "zbxmw.h"

#include "zbx_rtc_constants.h"
#include "zbxnix.h"
#include "zbxprof.h"
#include "zbxrtc.h"
#include "zbxself.h"
#include "zbxserialize.h"
#include "zbxtimekeeper.h"

#define MANAGER_SERVICE_TIMEOUT		SEC_PER_MIN

/******************************************************************************
 *                                                                            *
 * Purpose: start specified number of workers                                 *
 *                                                                            *
 * Parameters: manager     - [IN/OUT]                                         *
 *             workers_num - [IN] number of workers to start                  *
 *             error       - [OUT] error message                              *
 *                                                                            *
 * Return value: SUCCEED on success, FAIL otherwise                           *
 *                                                                            *
 * Comments: If the requested count exceeds available capacity, the remaining *
 *           slots are started instead. Does nothing if the pool is not in    *
 *           idle state.                                                      *
 *                                                                            *
 ******************************************************************************/
static int	mw_manager_start_workers(zbx_mw_manager_t *manager, int workers_num, char **error)
{
	if (MW_WORKER_POOL_IDLE != manager->worker_pool_state)
		return SUCCEED;

	if (manager->workers_max < manager->workers_num + workers_num)
	{
		if (0 == (workers_num = manager->workers_max - manager->workers_num))
			return SUCCEED;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "starting %d workers", workers_num);

	manager->workers_diff = 0;

	for (int i = manager->workers_num; i < manager->workers_num + workers_num; i++)
	{
		if (SUCCEED != mw_worker_start(manager->workers[i], manager->worker_process_type,
				manager->worker_entry, error))
		{
			return FAIL;
		}

		manager->workers_diff++;
		manager->worker_pool_state = MW_WORKER_POOL_GROWING;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: stop specified number of workers                                  *
 *                                                                            *
 * Parameters: manager     - [IN/OUT]                                         *
 *             workers_num - [IN] number of workers to stop                   *
 *                                                                            *
 * Comments: At least one worker is always kept running. If the requested     *
 *           count meets or exceeds the active count, all but one are         *
 *           stopped. Does nothing if the pool is not in idle state.          *
 *                                                                            *
 ******************************************************************************/
static void	mw_manager_stop_workers(zbx_mw_manager_t *manager, int workers_num)
{
	if (MW_WORKER_POOL_IDLE != manager->worker_pool_state)
		return;

	if (manager->workers_num <= workers_num)
	{
		if (0 == (workers_num = manager->workers_num - 1))
			return;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "stopping %d event processor(s)", workers_num);

	for (int i = manager->workers_num - 1; i >= manager->workers_num - workers_num; i--)
		zbx_mw_worker_stop(manager->workers[i]);

	manager->workers_diff = workers_num;
	manager->workers_num -= workers_num;
	manager->worker_pool_state = MW_WORKER_POOL_SHRINKING;

	if (NULL != manager->queue)
	{
		zbx_mw_queue_lock(manager->queue);
		zbx_mw_queue_notify_all(manager->queue);
		zbx_mw_queue_unlock(manager->queue);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: scale worker pool based on current load                           *
 *                                                                            *
 * Return value: SUCCEED on success, FAIL otherwise                           *
 *                                                                            *
 * Comments: A scaling operation must complete before another one can be      *
 *           initiated.                                                       *
 *                                                                            *
 ******************************************************************************/
static int	mw_manager_scale_workers(zbx_mw_manager_t *manager)
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

			num = MIN(manager->workers_max - manager->workers_num, MIN(10, (manager->workers_num + 1) / 2));

			if (SUCCEED != (ret = mw_manager_start_workers(manager, num, &error)))
			{
				zabbix_log(LOG_LEVEL_ERR, "cannot start %s: %s",
						get_process_type_string(manager->worker_process_type), error);
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
					mw_manager_stop_workers(manager, 1);
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
 * Purpose: check worker pool scaling status and initiate scaling if needed   *
 *                                                                            *
 * Parameters: manager - [IN/OUT]                                             *
 *             now     - [IN] current timestamp                               *
 *                                                                            *
 * Return value: SUCCEED on success, FAIL otherwise                           *
 *                                                                            *
 *                                                                            *
 ******************************************************************************/
static int	mw_manager_check_pool_status(zbx_mw_manager_t *manager, double now)
{
#define MW_POOL_STATUS_CHECK_DELAY	10

	static double	last_check;

	if (MW_POOL_STATUS_CHECK_DELAY > now - last_check)
		return SUCCEED;

	last_check = now;

	switch (manager->worker_pool_state)
	{
		case MW_WORKER_POOL_IDLE:
			return mw_manager_scale_workers(manager);

		case MW_WORKER_POOL_GROWING:
			for (int i = manager->workers_num; i < manager->workers_num + manager->workers_diff; i++)
			{
				if (0 == (atomic_load(&manager->workers[i]->state) & MW_WORKER_STATE_RUNNING))
					return SUCCEED;
			}
			manager->workers_num += manager->workers_diff;
			break;

		case MW_WORKER_POOL_SHRINKING:
			for (int i = manager->workers_num; i < manager->workers_num + manager->workers_diff; i++)
			{
				if (0 == (atomic_load(&manager->workers[i]->state) & MW_WORKER_STATE_STOPPED))
					return SUCCEED;
			}

			for (int i = manager->workers_num; i < manager->workers_num + manager->workers_diff; i++)
				mw_worker_join(manager->workers[i]);

			/* when shrinking the requested number of workers is set immediately */
			break;
	}

	manager->workers_diff = 0;
	manager->worker_pool_state = MW_WORKER_POOL_IDLE;

	return SUCCEED;

#undef CEP_POOL_STATUS_CHECK_DELAY
}

/******************************************************************************
 *                                                                            *
 * Purpose: stop workers and release resources allocated by manager           *
 *                                                                            *
 ******************************************************************************/
void	zbx_mw_manager_clear(zbx_mw_manager_t *manager)
{
	if (NULL != manager->workers)
	{
		if (NULL != manager->queue)
			zbx_mw_queue_lock(manager->queue);

		for (int i = 0; i < manager->workers_max; i++)
			zbx_mw_worker_stop(manager->workers[i]);

		if (NULL != manager->queue)
		{
			zbx_mw_queue_notify_all(manager->queue);
			zbx_mw_queue_unlock(manager->queue);
		}

		for (int i = 0; i < manager->workers_max; i++)
			mw_worker_clear(manager->workers[i]);

	}
	if (NULL != manager->queue)
		mw_queue_clear(manager->queue);

	if (NULL != manager->service)
	{
		zbx_ipc_service_close(manager->service);
		zbx_free(manager->service);
	}

	if (NULL != manager->timekeeper)
	{
		zbx_timekeeper_free(manager->timekeeper);
		manager->timekeeper = NULL;
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize manager                                                *
 *                                                                            *
 * Parameters: manager             - [OUT] manager to initialize              *
 *             service             - [IN] IPC service name (optional)         *
 *             process_type        - [IN] manager process type                *
 *             worker_process_type - [IN] worker process type                 *
 *             workers             - [IN] pre-allocated worker array          *
 *             workers_max         - [IN] maximum number of workers           *
 *             workers_num         - [IN] number of workers to start          *
 *             worker_entry        - [IN] worker thread entry point           *
 *             queue               - [IN] task queue (optional)               *
 *             error               - [OUT] error message                      *
 *                                                                            *
 * Return value: SUCCEED on success, FAIL otherwise                           *
 *                                                                            *
 ******************************************************************************/
static int	mw_manager_init(zbx_mw_manager_t *manager, const char *service, unsigned char process_type,
		unsigned char worker_process_type, zbx_mw_worker_t **workers, int workers_max, int workers_num,
		void *(*worker_entry)(void *), zbx_mw_queue_t *queue, char **error)
{
	char	*errmsg = NULL;

	if (NULL != service)
	{
		manager->service = (zbx_ipc_service_t *)zbx_malloc(NULL, sizeof(zbx_ipc_service_t));

		if (FAIL == zbx_ipc_service_start(manager->service, service, &errmsg))
		{
			*error = zbx_dsprintf(NULL, "cannot start %s service: %s", service, errmsg);
			zbx_free(manager->service);
			zbx_free(errmsg);
			return FAIL;
		}

		zbx_uint32_t	rtc_worker_msgs[] = {ZBX_RTC_LOG_LEVEL_INCREASE, ZBX_RTC_LOG_LEVEL_DECREASE};

		/* subscribe for worker log level rtc messages */
		zbx_rtc_subscribe_service(worker_process_type, 0, rtc_worker_msgs, ARRSIZE(rtc_worker_msgs),
				MANAGER_SERVICE_TIMEOUT, service);

		zbx_uint32_t	rtc_manager_msgs[] = {ZBX_RTC_SHUTDOWN};

		zbx_rtc_subscribe_service(process_type, 0, rtc_manager_msgs, ARRSIZE(rtc_manager_msgs),
				MANAGER_SERVICE_TIMEOUT, service);
	}

	manager->timekeeper = zbx_timekeeper_create(workers_max, NULL);
	manager->worker_entry = worker_entry;
	manager->workers_max = workers_max;

	if (FAIL == mw_queue_init(queue, &errmsg))
	{
		*error = zbx_dsprintf(NULL, "cannot create %s task queue: %s", service, errmsg);
		zbx_free(errmsg);
		return FAIL;
	}
	manager->queue = queue;

	manager->worker_process_type = worker_process_type;
	manager->worker_pool_state = MW_WORKER_POOL_IDLE;
	manager->workers = workers;

	for (int i = 0; i < workers_max; i++)
		mw_worker_init(manager->workers[i], i + 1, manager->service, manager->queue, manager->timekeeper);

	if (FAIL == mw_manager_start_workers(manager, workers_num, &errmsg))
	{
		*error = zbx_dsprintf(NULL, "cannot start %s workers: %s", service, errmsg);
		zbx_free(errmsg);
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize manager                                                *
 *                                                                            *
 * Parameters: manager             - [OUT] manager to initialize              *
 *             info                - [IN] thread info                         *
 *             service             - [IN] IPC service name (optional)         *
 *             worker_process_type - [IN] worker process type                 *
 *             workers             - [IN] pre-allocated worker array          *
 *             workers_max         - [IN] maximum number of workers           *
 *             workers_num         - [IN] number of workers to start          *
 *             worker_entry        - [IN] worker thread entry point           *
 *             queue               - [IN] task queue (optional)               *
 *             error               - [OUT] error message                      *
 *                                                                            *
 * Return value: SUCCEED on success, FAIL otherwise                           *
 *                                                                            *
 ******************************************************************************/
int	zbx_mw_manager_init(zbx_mw_manager_t *manager, const zbx_thread_info_t *info, const char *service,
	unsigned char worker_process_type, zbx_mw_worker_t **workers, int workers_max, int workers_num,
	void *(*worker_entry)(void *), zbx_mw_queue_t *queue, char **error)
{
	int	ret = FAIL;

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

	memset(manager, 0, sizeof(zbx_mw_manager_t));

	if (SUCCEED != (ret = mw_manager_init(manager, service, info->process_type, worker_process_type, workers,
			workers_max, workers_num, worker_entry, queue, error)))
	{
		zbx_mw_manager_clear(manager);
	}
	else
	{
		manager->thread_info = info;
	}

	zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_IDLE);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: change log level for specified workers                            *
 *                                                                            *
 * Parameters: manager   - [IN/OUT]                                           *
 *             direction - [IN] log level change direction                    *
 *             data      - [IN] RTC command target data                       *
 *                                                                            *
 * Comments: Inactive workers (beyond workers_num) are updated silently.      *
 *                                                                            *
 ******************************************************************************/
static void	mw_manager_change_loglevel(zbx_mw_manager_t *manager, int direction, const char *data)
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
		zabbix_log(LOG_LEVEL_WARNING, "Cannot change log level for thread based worker by pid");
		return;
	}

	if (0 > proc_num || manager->workers_num < proc_num)
	{
		zabbix_log(LOG_LEVEL_INFORMATION, "Cannot change log level for worker #%d: no such instance", proc_num);
		return;
	}

	for (int i = 0; i < manager->workers_max; i++)
	{
		if (0 != proc_num && proc_num != i + 1)
			continue;

		if (i < manager->workers_num)
			zbx_change_component_log_level(&manager->workers[i]->logger, direction);
		else
			zbx_change_component_log_level_silent(&manager->workers[i]->logger, direction);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: send current worker count to IPC client                           *
 *                                                                            *
 * Parameters: manager - [IN]                                                 *
 *             client  - [IN] IPC client to send response to                  *
 *                                                                            *
 ******************************************************************************/
static void	mw_manager_reply_worker_count(zbx_mw_manager_t *manager, zbx_ipc_client_t *client)
{
	unsigned char	data[sizeof(int)];

	(void)zbx_serialize_value(data, manager->workers_num);

	zbx_ipc_client_send(client, ZBX_RTC_MW_GET_WORKER_COUNT, data, (zbx_uint32_t)sizeof(data));
}

/******************************************************************************
 *                                                                            *
 * Purpose: send worker load statistics to IPC client                         *
 *                                                                            *
 * Parameters: manager - [IN]                                                 *
 *             client  - [IN] IPC client to send response to                  *
 *                                                                            *
 ******************************************************************************/
static void	mw_manager_reply_worker_load(zbx_mw_manager_t *manager, zbx_ipc_client_t *client)
{
	unsigned char		*data;
	zbx_uint32_t		data_len;
	zbx_vector_dbl_t	usage;
	unsigned char		*ptr;

	zbx_vector_dbl_create(&usage);
	(void)zbx_timekeeper_get_usage(manager->timekeeper, &usage);

	data_len = (zbx_uint32_t)((unsigned int)usage.values_num * sizeof(double) + sizeof(int) + sizeof(int));
	ptr = data = (unsigned char *)zbx_malloc(NULL, data_len);
	ptr += zbx_serialize_value(ptr, usage.values_num);

	for (int i = 0; i < usage.values_num; i++)
		ptr += zbx_serialize_value(ptr, usage.values[i]);

	(void)zbx_serialize_value(ptr, manager->workers_num);
	zbx_ipc_client_send(client, ZBX_RTC_MW_GET_WORKER_LOAD, data, data_len);

	zbx_free(data);
	zbx_vector_dbl_destroy(&usage);
}

/******************************************************************************
 *                                                                            *
 * Purpose: receive and dispatch IPC messages                                 *
 *                                                                            *
 * Parameters: manager - [IN/OUT]                                             *
 *             client  - [OUT] IPC client that sent the message               *
 *             message - [OUT] received message, NULL if none or handled      *
 *                                                                            *
 * Return value: current timestamp                                            *
 *                                                                            *
 * Comments: Built-in RTC messages are handled and consumed internally;       *
 *           unrecognized messages are passed back to the caller.             *
 *           Also performs worker pool scaling and worker load tracking.      *
 *                                                                            *
 ******************************************************************************/
double	zbx_mw_manager_recv(zbx_mw_manager_t *manager, zbx_ipc_client_t **client, zbx_ipc_message_t **message)
{
#define MW_MANAGER_DELAY_SEC		0
#define MW_MANAGER_DELAY_NS		5e8

	static time_t	timekeeper_clock = 0;
	zbx_timespec_t	timeout = {MW_MANAGER_DELAY_SEC, MW_MANAGER_DELAY_NS};

	zbx_update_selfmon_counter(manager->thread_info, ZBX_PROCESS_STATE_IDLE);

	(void) zbx_ipc_service_recv(manager->service, &timeout, client, message);

	zbx_update_selfmon_counter(manager->thread_info, ZBX_PROCESS_STATE_BUSY);

	double	time_now = zbx_time();

	zbx_prof_update(get_process_type_string(manager->thread_info->process_type), time_now);

	if (NULL == *message)
		goto out;

	switch ((*message)->code)
	{
		case ZBX_RTC_LOG_LEVEL_INCREASE:
			mw_manager_change_loglevel(manager, 1, (const char *)(*message)->data);
			break;
		case ZBX_RTC_LOG_LEVEL_DECREASE:
			mw_manager_change_loglevel(manager, -1, (const char *)(*message)->data);
			break;
		case ZBX_RTC_MW_GET_WORKER_COUNT:
			mw_manager_reply_worker_count(manager, *client);
			break;
		case ZBX_RTC_MW_GET_WORKER_LOAD:
			mw_manager_reply_worker_load(manager, *client);
			break;
		default:
			goto out;
	}

	zbx_ipc_message_free(*message);
	*message = NULL;
	zbx_ipc_client_release(*client);
	*client = NULL;
out:
	if (SUCCEED != mw_manager_check_pool_status(manager, time_now))
		zbx_set_exiting_with_fail();

	if ((int)time_now != timekeeper_clock)
	{
		zbx_timekeeper_collect(manager->timekeeper);
		timekeeper_clock = (int)time_now;
	}

	return time_now;

#undef MW_MANAGER_DELAY_SEC
#undef MW_MANAGER_DELAY_NS
}

/******************************************************************************
 *                                                                            *
 * Purpose: get current worker count from manager                             *
 *                                                                            *
 * Parameters: service     - [IN] IPC service name                            *
 *             workers_num - [OUT] number of active workers                   *
 *             error       - [OUT] error message                              *
 *                                                                            *
 * Return value: SUCCEED on success, FAIL otherwise                           *
 *                                                                            *
 ******************************************************************************/
int	zbx_mw_get_worker_count(const char *service, int *workers_num, char **error)
{
	unsigned char	*data;

	if (FAIL == zbx_ipc_async_exchange(service, ZBX_RTC_MW_GET_WORKER_COUNT, MANAGER_SERVICE_TIMEOUT, NULL, 0,
			&data, error))
	{
		return FAIL;
	}

	(void)zbx_deserialize_value(data, workers_num);
	zbx_free(data);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get worker load statistics from manager                           *
 *                                                                            *
 * Parameters: service - [IN] IPC service name                                *
 *             usage   - [OUT] per-worker busy time ratios                    *
 *             count   - [OUT] number of active workers                       *
 *             error   - [OUT] error message                                  *
 *                                                                            *
 * Return value: SUCCEED on success, FAIL otherwise                           *
 *                                                                            *
 ******************************************************************************/
int	zbx_mw_get_worker_load(const char *service, zbx_vector_dbl_t *usage, int *count, char **error)
{
	unsigned char	*data;

	if (FAIL == zbx_ipc_async_exchange(service, ZBX_RTC_MW_GET_WORKER_LOAD, MANAGER_SERVICE_TIMEOUT, NULL, 0,
			&data, error))
	{
		return FAIL;
	}

	unsigned char	*ptr = data;
	int		usage_num;

	ptr += zbx_deserialize_value(ptr, &usage_num);
	zbx_vector_dbl_reserve(usage, (size_t)usage_num);

	for (int i = 0; i < usage_num; i++)
	{
		double	busy;

		ptr += zbx_deserialize_value(ptr, &busy);
		zbx_vector_dbl_append(usage, busy);
	}

	(void)zbx_deserialize_value(ptr, count);
	zbx_free(data);

	return SUCCEED;
}
