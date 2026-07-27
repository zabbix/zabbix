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
#include "otel_exporter.h"
#include "otel_queue.h"
#include "otel_task.h"
#include "zbx_otel.h"
#include "zbx_otel_client.h"
#include "zbxcacheconfig.h"
#include "zbxmw.h"
#include "zbxcommon.h"
#include "zbxnix.h"
#include "zbxprof.h"
#include "zbxrtc.h"
#include "zbxself.h"
#include "zbxsupervisor_client.h"

#define OTEL_WORKERS_MAX		100
#define OTEL_WORKERS_DEFAULT		10
typedef struct
{
	zbx_mw_manager_t		base;
	zbx_grpc_handle_t		grpc;

	zbx_vector_mw_task_ptr_t	commits;

	int				commit_limit;
	int				commit_task_num;

	zbx_otel_exporter_pool_t	*exporters;
}
zbx_otel_manager_t;

static void	otel_manager_free(zbx_otel_manager_t *manager)
{
	for (int i = 0; i < manager->commits.values_num; i++)
		otel_task_free(manager->commits.values[i]);
	zbx_vector_mw_task_ptr_destroy(&manager->commits);

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

	if (NULL != manager->exporters)
		otel_exporter_pool_destroy(manager->exporters);

	zbx_free(manager);
}

static zbx_otel_manager_t	*otel_manager_create(const zbx_thread_info_t *info, int workers_num, int quota,
		const char *options, char **error)
{
	zbx_otel_manager_t	*manager;
	zbx_otel_worker_t	**workers;
	zbx_otel_queue_t	*queue;
	int			ret = FAIL;
	zbx_otel_exporter_cfg_t	cfg;

	manager = (zbx_otel_manager_t *)zbx_calloc(NULL, 1, sizeof(zbx_otel_manager_t));
	queue = otel_queue_create(quota);
	workers = (zbx_otel_worker_t **)zbx_calloc(NULL, (size_t)OTEL_WORKERS_MAX, sizeof(zbx_otel_worker_t));

	if (SUCCEED != otel_exporter_cfg_init(&cfg, options, error))
		goto out;

	if (NULL == (manager->exporters = otel_exporter_pool_create(&cfg, error)))
		goto out;

	for (int i = 0; i < OTEL_WORKERS_MAX; i++)
		workers[i] = otel_worker_create(manager->exporters);

	if (SUCCEED != zbx_mw_manager_init(&manager->base, info, ZBX_IPC_SERVICE_OTEL, ZBX_PROCESS_TYPE_OTEL_WORKER,
			(zbx_mw_worker_t **)workers, OTEL_WORKERS_MAX, workers_num, otel_worker_entry,
			(zbx_mw_queue_t *)queue, error))
	{
		goto out;
	}

	/* TODO: make configuratble */
	manager->commit_limit = 10;
	manager->commit_task_num = 0;

	zbx_vector_mw_task_ptr_create(&manager->commits);

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
	for (int i = 0; i < tasks->values_num; i++)
	{
		switch (tasks->values[i]->type)
		{
			case OTEL_TASK_REQUEST:
				zbx_vector_mw_task_ptr_append(&manager->commits, tasks->values[i]);
				break;
			case OTEL_TASK_COMMIT:
				otel_task_free(tasks->values[i]);
				manager->commit_task_num--;
				break;
		}
	}

	zbx_vector_mw_task_ptr_clear(tasks);
}

static int	otel_manager_activate(zbx_otel_manager_t *manager, char **error)
{
	/* TODO: use configured address/port */
	if (NULL == (manager->grpc = zbx_grpc_start(NULL, NULL, (zbx_otel_queue_t *)manager->base.queue, error)))
		return FAIL;

	return SUCCEED;
}

static void	otel_manager_deactivate(zbx_otel_manager_t *manager)
{
	zbx_grpc_stop(manager->grpc);
	manager->grpc = NULL;
}

static void	otel_manager_commit_tasks(zbx_otel_manager_t *manager, const char *attributes)
{
	zbx_mw_task_t	*t = otel_task_commit_create(&manager->commits, attributes);

	zbx_mw_queue_lock(manager->base.queue);
	zbx_mw_queue_push_normal(manager->base.queue, t);
	zbx_mw_queue_unlock(manager->base.queue);

	manager->commit_task_num++;
}

void	*zbx_otel_manager_thread(void *args)
{
#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
			/* once in STAT_INTERVAL seconds */
#define CONFIG_INTERVAL 1

	zbx_supervisor_unit_args_t		*unit_args = (zbx_supervisor_unit_args_t *)args;
	const zbx_thread_info_t			*info = &unit_args->args.info;
	int					server_num = info->server_num,
						process_num = info->process_num;
	unsigned char				process_type = info->process_type;
	const zbx_thread_otel_manager_args_t	*otel_args;
	zbx_otel_manager_t			*manager;
	char					*error = NULL;
	double					time_stat, time_flush, time_idle = 0, time_config;
	zbx_ipc_client_t			*client;
	zbx_ipc_message_t			*message;
	int					shutdown = 0, workers_num, apm_status = 1;
	zbx_vector_mw_task_ptr_t		tasks;
	zbx_uint64_t				cfg_revision = 0, quota;
	zbx_dc_apm_config_t                    cfg = {0};

	otel_args = (const zbx_thread_otel_manager_args_t *)unit_args->args.args;

	zbx_dc_get_apm_config(&cfg, &cfg_revision);
	quota = cfg.quota;

	/* when disabled leave one worker running */
	workers_num = (0 == cfg.status ? 1 : OTEL_WORKERS_DEFAULT);

	if (NULL == (manager = otel_manager_create(info, workers_num, cfg.quota, otel_args->exporter_options, &error)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize open telemetry manager: %s", error);
		zbx_free(error);
		zbx_exit(EXIT_FAILURE);
	}

	zbx_vector_mw_task_ptr_create(&tasks);

	/* initialize statistics */
	time_config = time_stat = zbx_time();

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

		if (CONFIG_INTERVAL < time_start - time_config)
		{
			zbx_dc_get_apm_config(&cfg, &cfg_revision);

			if (apm_status != cfg.status)
			{
				if (0 == cfg.status)
				{
					if (FAIL == otel_manager_activate(manager, &error))
					{
						zabbix_log(LOG_LEVEL_CRIT, "cannot activate Open Telemetry listener:"
								" %s", error);
						zbx_free(error);
						zbx_exit(EXIT_FAILURE);
					}
				}
				else
				{
					otel_manager_deactivate(manager);
				}
				apm_status = cfg.status;
			}

			if (quota != cfg.quota)
			{
				zbx_mw_queue_lock(manager->base.queue);
				otel_queue_set_quota((zbx_otel_queue_t *)manager->base.queue, cfg.quota);
				zbx_mw_queue_unlock(manager->base.queue);

				zabbix_log(LOG_LEVEL_WARNING, "changed Open Telemetry quota from " ZBX_FS_UI64 " to "
						ZBX_FS_UI64, quota, cfg.quota);
				quota = cfg.quota;
			}
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
			zbx_vector_mw_task_ptr_clear(&tasks);
		}

		if (0 != manager->commits.values_num && manager->commit_task_num < manager->commit_limit)
			otel_manager_commit_tasks(manager, cfg.attributes);

	}

	zbx_supervisor_update_activity("%s [terminating]", unit_args->name);

	zbx_vector_mw_task_ptr_destroy(&tasks);

	/* on normal exit the shutdown message already has been processed and no more messages will be sent */
	if (SUCCEED != ZBX_EXIT_STATUS())
		zbx_rtc_unsubscribe_service(otel_args->config_timeout, ZBX_IPC_SERVICE_OTEL);

	zbx_dc_apm_config_clear(&cfg);

	otel_manager_free(manager);
	zbx_free(args);

	return NULL;

#undef CONFIG_INTERVAL
#undef STAT_INTERVAL
}

