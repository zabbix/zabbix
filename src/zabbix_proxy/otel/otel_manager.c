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

#include "otel_worker.h"
#include "otel_grpc.h"
#include "zabbix_proxy/otel/otel_task.h"
#include "zbx_otel.h"
#include "zbx_otel_client.h"
#include "zbxmw.h"
#include "zbxcommon.h"
#include "zbxnix.h"
#include "zbxprof.h"
#include "zbxrtc.h"
#include "zbxself.h"
#include "zbxsupervisor_client.h"

#define OTEL_WORKERS_MAX		100
#define OTEL_WORKERS_DEFAULT	10

typedef struct
{
	zbx_mw_manager_t	base;
	zbx_grpc_handle_t	grpc;
}
zbx_otel_manager_t;

static void	otel_manager_free(zbx_otel_manager_t *manager)
{
	if (NULL != manager->grpc)
		zbx_grpc_stop(manager->grpc);

	zbx_mw_manager_clear(&manager->base);

	if (NULL != manager->base.queue)
	{
		zbx_free(manager->base.queue);

		if (NULL != manager->base.workers)
		{
			for (int i = 0; i < manager->base.workers_max; i++)
				zbx_free(manager->base.workers[i]);

			zbx_free(manager->base.workers);
		}
	}

	zbx_free(manager);
}

static zbx_otel_manager_t	*otel_manager_create(const zbx_thread_info_t *info, char **error)
{
	zbx_otel_manager_t	*manager;
	zbx_otel_worker_t	**workers;
	zbx_mw_queue_t		*queue;
	int			ret = FAIL;

	manager = (zbx_otel_manager_t *)zbx_calloc(NULL, 1, sizeof(zbx_otel_manager_t));
	queue = (zbx_mw_queue_t *)zbx_calloc(NULL, 1, sizeof(zbx_mw_queue_t));
	workers = (zbx_otel_worker_t **)zbx_calloc(NULL, (size_t)OTEL_WORKERS_MAX, sizeof(zbx_otel_worker_t));

	for (int i = 0; i < OTEL_WORKERS_MAX; i++)
		workers[i] = otel_worker_create();

	if (SUCCEED != zbx_mw_manager_init(&manager->base, info, ZBX_IPC_SERVICE_OTEL, ZBX_PROCESS_TYPE_OTEL_WORKER,
			(zbx_mw_worker_t **)workers, OTEL_WORKERS_MAX, OTEL_WORKERS_DEFAULT, otel_worker_entry,
			queue, error))
	{
		goto out;
	}

	/* TODO: use grpc listen address / port */
	if (NULL == (manager->grpc = zbx_grpc_start(NULL, NULL, manager->base.queue, error)))
		goto out;

	ret = SUCCEED;
out:
	if (SUCCEED != ret)
	{
		otel_manager_free(manager);
		manager = NULL;
	}

	return manager;
}

static void	otel_manager_process_finished(zbx_otel_manager_t *manager, zbx_vector_mw_task_ptr_t *tasks)
{
	zabbix_log(LOG_LEVEL_ERR, "[WDN] process finished");
	for (int i = 0; i < tasks->values_num; i++)
	{
		otel_task_free(tasks->values[i]);
	}

	zbx_vector_mw_task_ptr_clear(tasks);
}

void	*zbx_otel_manager_thread(void *args)
{
#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
			/* once in STAT_INTERVAL seconds */

	zbx_supervisor_unit_args_t		*unit_args = (zbx_supervisor_unit_args_t *)args;
	const zbx_thread_info_t			*info = &unit_args->args.info;
	int					server_num = info->server_num,
						process_num = info->process_num;
	unsigned char				process_type = info->process_type;
	const zbx_thread_otel_manager_args_t	*otel_args;
	zbx_otel_manager_t			*manager;
	char					*error = NULL;
	double					time_stat, time_flush, time_idle = 0;
	zbx_ipc_client_t			*client;
	zbx_ipc_message_t			*message;
	int					shutdown = 0;
	zbx_vector_mw_task_ptr_t		tasks;

	otel_args = (const zbx_thread_otel_manager_args_t *)unit_args->args.args;

	if (NULL == (manager = otel_manager_create(info, &error)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize open telemetry manager: %s", error);
		zbx_free(error);
		zbx_exit(EXIT_FAILURE);
	}

	zbx_vector_mw_task_ptr_create(&tasks);

	/* initialize statistics */
	time_stat = zbx_time();

	zbx_supervisor_update_activity("%s #%d started", get_process_type_string(process_type), process_num);

	zbx_supervisor_set_process_running(server_num);

	time_flush = zbx_time();

	while (1)
	{
		double	time_start = zbx_time();

		if (STAT_INTERVAL < time_start - time_stat)
		{
			zbx_supervisor_update_activity("%s #%d [processing something, idle %.1fs, during %.1fs]",
					get_process_type_string(process_type), process_num,
					time_idle, time_start - time_stat);

			time_stat = time_start;
			time_idle = 0;
		}

		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_IDLE);

		(void)zbx_mw_manager_recv(&manager->base, &client, &message);

		zbx_update_selfmon_counter(info, ZBX_PROCESS_STATE_BUSY);

		double	time_now = zbx_time();

		time_idle += time_now - time_start;

		zbx_prof_update(get_process_type_string(process_type), time_now);

		if (NULL != message)
		{
			switch (message->code)
			{
				case ZBX_RTC_SHUTDOWN:
					zabbix_log(LOG_LEVEL_DEBUG, "shutdown message received, terminating...");
					shutdown = 1;
					break;
			}

			zbx_ipc_message_free(message);
		}

		if (NULL != client)
			zbx_ipc_client_release(client);

		/* only stop cep when history syncers no longer require it */
		if ((!ZBX_IS_RUNNING() || 1 == shutdown))
			break;

		zbx_mw_queue_lock(manager->base.queue);
		(void)zbx_mw_queue_drain_completed(manager->base.queue, &tasks);
		zbx_mw_queue_unlock(manager->base.queue);

		if (0 != tasks.values_num)
		{
			otel_manager_process_finished(manager, &tasks);
		}
	}

	zbx_supervisor_update_activity("%s [terminating]", unit_args->name);

	zbx_vector_mw_task_ptr_destroy(&tasks);

	/* on normal exit the shutdown message already has been processed and no more messages will be sent */
	if (SUCCEED != ZBX_EXIT_STATUS())
		zbx_rtc_unsubscribe_service(otel_args->config_timeout, ZBX_IPC_SERVICE_OTEL);

	otel_manager_free(manager);
	zbx_free(args);

	return NULL;

#undef STAT_INTERVAL
}

