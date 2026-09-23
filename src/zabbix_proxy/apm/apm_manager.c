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

#include "apm_config.h"
#include "apm_worker.h"
#include "apm_grpc.h"
#include "apm_exporter.h"
#include "apm_queue.h"
#include "apm_task.h"
#include "apm_dataset.h"
#include "zbx_apm.h"
#include "zbx_apm_client.h"
#include "zbxcommon.h"
#include "zbxtelemetry.h"
#include "zbxtypes.h"
#include "zbx_rtc_constants.h"
#include "zbxipcservice.h"
#include "zbxthreads.h"
#include "zbxtime.h"
#include "zbxtimekeeper.h"
#include "zbxcacheconfig.h"
#include "zbxmw.h"
#include "zbxnix.h"
#include "zbxprof.h"
#include "zbxrtc.h"
#include "zbxself.h"
#include "zbxsupervisor_client.h"
#include "zbxstr.h"

#define APM_WORKERS_MIN		1
#define APM_WORKERS_MAX		100
#define APM_WORKERS_DEFAULT	10
typedef struct
{
	zbx_mw_manager_t		base;
	zbx_grpc_handle_t		grpc;

	zbx_vector_mw_task_ptr_t	commits;

	int				commit_limit;
	int				commit_task_num;

	zbx_apm_commit_stats_t		commit_stats;

	zbx_apm_exporter_pool_t	*exporters;
}
zbx_apm_manager_t;

static void	apm_manager_deactivate(zbx_apm_manager_t *manager);

/******************************************************************************
 *                                                                            *
 * Purpose: free resources allocated by APM manager                           *
 *                                                                            *
 * Parameters: manager - [IN] manager to free                                 *
 *                                                                            *
 * Comments: Deactivates the gRPC listener and releases protobuf runtime      *
 *           resources before freeing manager-owned memory.                   *
 *                                                                            *
 ******************************************************************************/
static void	apm_manager_free(zbx_apm_manager_t *manager)
{
	for (int i = 0; i < manager->commits.values_num; i++)
		apm_task_free(manager->commits.values[i]);
	zbx_vector_mw_task_ptr_destroy(&manager->commits);

	apm_manager_deactivate(manager);
	zbx_grpc_shutdown();

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
		apm_exporter_pool_destroy(manager->exporters);

	zbx_free(manager);
}

/******************************************************************************
 *                                                                            *
 * Purpose: create and initialize APM manager                                 *
 *                                                                            *
 * Parameters: info          - [IN] process info                              *
 *             workers_num   - [IN] number of active worker threads           *
 *             quota         - [IN] initial ingestion quota, messages per     *
 *                                 second                                     *
 *             export_config - [IN] TelemetryProvider configuration options   *
 *             error         - [OUT] error message if the operation fails     *
 *                                                                            *
 * Return value: created manager, or NULL on error                            *
 *                                                                            *
 ******************************************************************************/
static zbx_apm_manager_t	*apm_manager_create(const zbx_thread_info_t *info, int workers_num, zbx_uint64_t quota,
		zbx_apm_db_config_t *export_config, char **error)
{
#define APM_COMMIT_LIMIT	10
	zbx_apm_manager_t	*manager;
	zbx_apm_worker_t	**workers;
	zbx_apm_queue_t	*queue = NULL;
	int			ret = FAIL;
	zbx_apm_exporter_cfg_t	cfg;

	manager = (zbx_apm_manager_t *)zbx_calloc(NULL, 1, sizeof(zbx_apm_manager_t));
	workers = (zbx_apm_worker_t **)zbx_calloc(NULL, (size_t)APM_WORKERS_MAX, sizeof(zbx_apm_worker_t *));
	queue = apm_queue_create(quota);

	if (SUCCEED != apm_exporter_cfg_init(&cfg, export_config, error))
		goto out;

	if (NULL == (manager->exporters = apm_exporter_pool_create(&cfg, error)))
		goto out;

	for (int i = 0; i < APM_WORKERS_MAX; i++)
		workers[i] = apm_worker_create(manager->exporters, &manager->commit_stats);

	if (SUCCEED != zbx_mw_manager_init(&manager->base, info, ZBX_IPC_SERVICE_APM, ZBX_PROCESS_TYPE_APM_WORKER,
			(zbx_mw_worker_t **)workers, APM_WORKERS_MIN, APM_WORKERS_MAX, workers_num, apm_worker_entry,
			(zbx_mw_queue_t *)queue, error))
	{
		goto out;
	}

	manager->commit_limit = APM_COMMIT_LIMIT;
	manager->commit_task_num = 0;

	zbx_vector_mw_task_ptr_create(&manager->commits);

	ret = SUCCEED;
out:
	if (SUCCEED != ret)
	{
		if (queue != (zbx_apm_queue_t *)manager->base.queue)
			zbx_free(queue);

		if (workers != (zbx_apm_worker_t **)manager->base.workers)
		{
			for (int i = 0; i < APM_WORKERS_MAX; i++)
				zbx_free(workers[i]);

			zbx_free(workers);
		}

		apm_manager_free(manager);
		manager = NULL;
	}

	return manager;
#undef APM_COMMIT_LIMIT
}

/******************************************************************************
 *                                                                            *
 * Purpose: process finished worker tasks                                     *
 *                                                                            *
 * Parameters: manager - [IN/OUT] manager                                     *
 *             tasks   - [IN/OUT] finished tasks to process                   *
 *                                                                            *
 * Comments: Finished request tasks are queued for the next commit            *
 *           batch, finished commit tasks are freed. The task vector is       *
 *           cleared on return.                                               *
 *                                                                            *
 ******************************************************************************/
static void	apm_manager_process_finished(zbx_apm_manager_t *manager, zbx_vector_mw_task_ptr_t *tasks)
{
	for (int i = 0; i < tasks->values_num; i++)
	{
		switch (tasks->values[i]->type)
		{
			case APM_TASK_REQUEST:
				zbx_vector_mw_task_ptr_append(&manager->commits, tasks->values[i]);
				break;
			case APM_TASK_COMMIT:
				apm_task_free(tasks->values[i]);
				manager->commit_task_num--;
				break;
		}
	}

	zbx_vector_mw_task_ptr_clear(tasks);
}

/******************************************************************************
 *                                                                            *
 * Purpose: start the APM gRPC listener                                       *
 *                                                                            *
 * Parameters: manager  - [IN/OUT] manager                                    *
 *             sourceip - [IN] address to listen on                           *
 *             port     - [IN] port to listen on                              *
 *             tls      - [IN] TLS configuration, or NULL for an              *
 *                              insecure listener                             *
 *             error    - [OUT] error message if the operation fails          *
 *                                                                            *
 * Return value: SUCCEED on success, FAIL otherwise                           *
 *                                                                            *
 ******************************************************************************/
static int	apm_manager_activate(zbx_apm_manager_t *manager, const char *sourceip, const char *port,
		const zbx_apm_config_tls_t *tls, char **error)
{
	if (NULL == (manager->grpc = zbx_grpc_start(sourceip, port, (zbx_apm_queue_t *)manager->base.queue, tls,
			error)))
	{
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: stop the APM gRPC listener                                        *
 *                                                                            *
 ******************************************************************************/
static void	apm_manager_deactivate(zbx_apm_manager_t *manager)
{
	zbx_grpc_stop(manager->grpc);
	manager->grpc = NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: queue a commit task for the finished request tasks                *
 *                                                                            *
 * Parameters: manager - [IN/OUT] manager                                     *
 *             attrs   - [IN] resource attributes to apply to the             *
 *                             commit; the reference is transferred to        *
 *                             the created task                               *
 *                                                                            *
 * Comments: The manager's list of finished request tasks is cleared.         *
 *                                                                            *
 ******************************************************************************/
static void	apm_manager_commit_tasks(zbx_apm_manager_t *manager, zbx_apm_config_attrs_t *attrs)
{
	zbx_mw_task_t	*t = apm_task_commit_create(&manager->commits, attrs);

	zbx_mw_queue_lock(manager->base.queue);
	zbx_mw_queue_push_normal(manager->base.queue, t);
	zbx_mw_queue_unlock(manager->base.queue);

	manager->commit_task_num++;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check whether the exporter uses the global telemetry              *
 *          provider configuration                                            *
 *                                                                            *
 * Parameters: manager - [IN] manager                                         *
 *                                                                            *
 * Return value: SUCCEED if the global configuration is active, FAIL          *
 *               otherwise                                                    *
 *                                                                            *
 ******************************************************************************/
static int	apm_manager_global_config_active(zbx_apm_manager_t *manager)
{
	if (NULL == manager->exporters || APM_EXPORTER_GLOBAL != manager->exporters->cfg.type)
		return FAIL;

	return SUCCEED;
}


/******************************************************************************
 *                                                                            *
 * Purpose: validate that all TLS files required for a listener are           *
 *          configured                                                        *
 *                                                                            *
 * Parameters: tls - [IN] TLS configuration to validate                       *
 *                                                                            *
 * Return value: tls if fully configured, NULL otherwise                      *
 *                                                                            *
 ******************************************************************************/
static zbx_apm_config_tls_t	*apm_manager_validate_tls(zbx_apm_config_tls_t *tls)
{
	if (NULL == tls->ca_file || '\0' == *tls->ca_file)
		return NULL;

	if (NULL == tls->cert_file || '\0' == *tls->cert_file)
		return NULL;

	if (NULL == tls->key_file || '\0' == *tls->key_file)
		return NULL;

	return tls;
}

/******************************************************************************
 *                                                                            *
 * Purpose: serialize and send APM statistics to the requesting client        *
 *                                                                            *
 * Parameters: manager - [IN] manager                                         *
 *             client  - [IN] IPC client to send the statistics to            *
 *                                                                            *
 ******************************************************************************/
static void	apm_manager_send_stats(zbx_apm_manager_t *manager, zbx_ipc_client_t *client)
{
	unsigned char	buf[sizeof(zbx_apm_stats_t)];
	zbx_uint32_t	len;
	zbx_apm_stats_t	stats;

	stats.written_logs = atomic_load(&manager->commit_stats.logs);
	stats.written_traces = atomic_load(&manager->commit_stats.traces);
	stats.written_metrics_gauge = atomic_load(&manager->commit_stats.metrics_gauge);
	stats.written_metrics_sum = atomic_load(&manager->commit_stats.metrics_sum);
	stats.written_metrics_histogram = atomic_load(&manager->commit_stats.metrics_histogram);
	stats.written_metrics_exponential_histogram = atomic_load(&manager->commit_stats.metrics_exponential_histogram);
	stats.written_metrics_summary = atomic_load(&manager->commit_stats.metrics_summary);
	apm_queue_get_stats((zbx_apm_queue_t *)manager->base.queue, &stats.accepted_requests, &stats.dropped_requests);

	if (0 != (len = zbx_apm_serialize_stats(&stats, buf, (zbx_uint32_t)sizeof(buf))))
		zbx_ipc_client_send(client, ZBX_APM_GET_STATS, buf, len);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check whether new apm db config matches current config            *
 *                                                                            *
 * Parameters: c1 - [IN] current apm db config                                *
 *             c2 - [IN] new config to compare against current                *
 *                                                                            *
 * Return value: SUCCEED - configs match, FAIL - configs differ               *
 *                                                                            *
 ******************************************************************************/
static int	apm_manager_compare_config(const zbx_apm_db_config_t *c1, const zbx_apm_db_config_t *c2)
{
	if (c1->status != c2->status)
		return FAIL;

	if (c1->db_type != c2->db_type)
		return FAIL;

	if (0 != zbx_strcmp_null(c1->url, c2->url))
		return FAIL;

	if (0 != zbx_strcmp_null(c1->username, c2->username))
		return FAIL;

	if (0 != zbx_strcmp_null(c1->password, c2->password))
		return FAIL;

	if (0 != zbx_strcmp_null(c1->db, c2->db))
		return FAIL;

	if (0 != zbx_strcmp_null(c1->source_ip, c2->source_ip))
		return FAIL;

	if (0 != zbx_strcmp_null(c1->vault_path, c2->vault_path))
		return FAIL;

	if (c1->ssl_verify_peer != c2->ssl_verify_peer || c1->ssl_verify_host != c2->ssl_verify_host)
		return FAIL;

	if (0 != zbx_strcmp_null(c1->ssl_cert_file, c2->ssl_cert_file))
		return FAIL;

	if (0 != zbx_strcmp_null(c1->ssl_key_file, c2->ssl_key_file))
		return FAIL;

	if (0 != zbx_strcmp_null(c1->ssl_key_password, c2->ssl_key_password))
		return FAIL;

	if (0 != zbx_strcmp_null(c1->ssl_ca_location, c2->ssl_ca_location))
		return FAIL;

	if (0 != zbx_strcmp_null(c1->ssl_cert_location, c2->ssl_cert_location))
		return FAIL;

	if (0 != zbx_strcmp_null(c1->ssl_key_location, c2->ssl_key_location))
		return FAIL;

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: fetch global apm db config and apply it if it has changed         *
 *                                                                            *
 * Parameters: manager                 - [IN/OUT] apm manager                 *
 *             cfg_now                 - [IN/OUT] current apm db config       *
 *             config_source_ip        - [IN]                                 *
 *             config_ssl_ca_location  - [IN]                                 *
 *                                                                            *
 * Return value: APM_STATUS_ENABLED or APM_STATUS_DISABLED based on the       *
 *               resulting config status                                      *
 *                                                                            *
 ******************************************************************************/
static int	apm_manager_update_global_config(zbx_apm_manager_t *manager, zbx_apm_db_config_t *cfg_now,
		const char *config_source_ip, const char *config_ssl_ca_location)
{
	zbx_apm_db_config_t	cfg_new;

	zbx_dc_config_get_apm_db_config(&cfg_new, NULL, config_source_ip, config_ssl_ca_location);

	if (SUCCEED == apm_manager_compare_config(cfg_now, &cfg_new))
	{
		zbx_apm_db_config_clear(&cfg_new);
	}
	else
	{
		zbx_apm_db_config_clear(cfg_now);
		*cfg_now = cfg_new;
		apm_exporter_pool_set_global_config(manager->exporters, cfg_now);
	}

	return (0 == cfg_now->status ? APM_STATUS_DISABLED : APM_STATUS_ENABLED);
}

/******************************************************************************
 *                                                                            *
 * Purpose: entry point of APM manager process                                *
 *                                                                            *
 ******************************************************************************/
void	*zbx_apm_manager_thread(void *args)
{
#define	STAT_INTERVAL	5	/* if a process is busy and does not sleep then update status not faster than */
			/* once in STAT_INTERVAL seconds */
#define CONFIG_INTERVAL 1

	zbx_supervisor_unit_args_t		*unit_args = (zbx_supervisor_unit_args_t *)args;
	const zbx_thread_info_t			*info = &unit_args->args.info;
	int					server_num = info->server_num,
						process_num = info->process_num;
	unsigned char				process_type = info->process_type;
	const zbx_thread_apm_manager_args_t	*apm_args;
	zbx_apm_manager_t			*manager;
	char					*error = NULL;
	double					time_stat, time_idle = 0, time_config = 0;
	zbx_ipc_client_t			*client;
	zbx_ipc_message_t			*message;
	int					shutdown = 0, workers_num, apm_enabled = APM_STATUS_DISABLED;
	zbx_vector_mw_task_ptr_t		tasks;
	zbx_uint64_t				cfg_revision = 0, quota, accepted_num = 0, dropped_num = 0;
	char					*proxy_apm_config = NULL;
	zbx_apm_config_t			apm_config = {0};
	zbx_apm_config_tls_t			apm_config_tls, *tls;
	zbx_apm_db_config_t			apm_global = {0};
	zbx_uint32_t				rtc_manager_msgs[] = {ZBX_RTC_PROF_ENABLE, ZBX_RTC_PROF_DISABLE};

	apm_args = (const zbx_thread_apm_manager_args_t *)unit_args->args.args;

	apm_config_tls.ca_file = apm_args->ca_file;
	apm_config_tls.key_file = apm_args->key_file;
	apm_config_tls.cert_file = apm_args->cert_file;

	tls = apm_manager_validate_tls(&apm_config_tls);

	zbx_dc_config_local_acquire();
	proxy_apm_config = zbx_dc_get_apm_config(proxy_apm_config, &cfg_revision);
	if (SUCCEED != apm_config_set(&apm_config, proxy_apm_config, cfg_revision))
		apm_config_reset(&apm_config);

	quota = apm_config.quota;

	/* when disabled leave one worker running */
	workers_num = (APM_STATUS_ENABLED != apm_config.enabled ? 1 : APM_WORKERS_DEFAULT);

	if (NULL == (manager = apm_manager_create(info, workers_num, apm_config.quota,
			apm_args->export_config, &error)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot initialize open telemetry manager: %s", error);
		zbx_free(error);
		apm_config_clear(&apm_config);
		zbx_dc_config_local_release();
		zbx_free(args);

		zbx_exit(EXIT_FAILURE);
	}

	zbx_rtc_subscribe_service(ZBX_PROCESS_TYPE_APM_MANAGER, 0, rtc_manager_msgs, ARRSIZE(rtc_manager_msgs),
			apm_args->config_timeout, ZBX_IPC_SERVICE_APM);

	zbx_vector_mw_task_ptr_create(&tasks);

	/* initialize statistics */
	time_stat = zbx_time();

	zbx_supervisor_update_activity("%s #%d started", get_process_type_string(process_type), process_num);

	zbx_supervisor_set_process_running(server_num);

	while (1)
	{
		double	time_start = zbx_time();

		if (STAT_INTERVAL < time_start - time_stat)
		{
			zbx_uint64_t	accepted, dropped;

			apm_queue_get_stats((zbx_apm_queue_t *)manager->base.queue, &accepted, &dropped);

			zbx_supervisor_update_activity("%s #%d [accepted " ZBX_FS_UI64 ", dropped " ZBX_FS_UI64
					" requests, idle %.1fs, during %.1fs]",
					get_process_type_string(process_type), process_num,
					accepted - accepted_num, dropped - dropped_num, time_idle,
					time_start - time_stat);

			time_stat = time_start;
			time_idle = 0;
			accepted_num = accepted;
			dropped_num = dropped;
		}

		if (CONFIG_INTERVAL < time_start - time_config)
		{
			proxy_apm_config = zbx_dc_get_apm_config(proxy_apm_config, &cfg_revision);
			if (SUCCEED != apm_config_set(&apm_config, proxy_apm_config, cfg_revision))
				apm_config_reset(&apm_config);

			if (SUCCEED == apm_manager_global_config_active(manager))
			{
				apm_config.enabled = apm_manager_update_global_config(manager, &apm_global,
						apm_args->source_ip, apm_args->ca_location);
			}

			if (apm_enabled != apm_config.enabled)
			{
				if (APM_STATUS_ENABLED == apm_config.enabled)
				{
					if (FAIL == apm_manager_activate(manager, apm_args->export_config->source_ip,
							apm_args->port, tls, &error))
					{
						zabbix_log(LOG_LEVEL_CRIT, "cannot activate Open Telemetry listener:"
								" %s", error);
						zbx_free(error);
						zbx_exit(EXIT_FAILURE);
					}
				}
				else
				{
					apm_manager_deactivate(manager);
				}
				apm_enabled = apm_config.enabled;
			}

			if (quota != apm_config.quota)
			{
				zbx_mw_queue_lock(manager->base.queue);
				apm_queue_set_quota((zbx_apm_queue_t *)manager->base.queue, apm_config.quota);
				zbx_mw_queue_unlock(manager->base.queue);

				zabbix_log(LOG_LEVEL_WARNING, "changed Open Telemetry quota from " ZBX_FS_UI64 " to "
						ZBX_FS_UI64, quota, apm_config.quota);
				quota = apm_config.quota;
			}

			time_config = time_start;
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
				case ZBX_APM_GET_STATS:
					apm_manager_send_stats(manager, client);
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

		/* only stop cep when history syncers no longer require it */
		if ((!ZBX_IS_RUNNING() || 1 == shutdown))
			break;

		zbx_mw_queue_lock(manager->base.queue);
		(void)zbx_mw_queue_drain_completed(manager->base.queue, &tasks);
		zbx_mw_queue_unlock(manager->base.queue);

		if (0 != tasks.values_num)
		{
			apm_manager_process_finished(manager, &tasks);
			zbx_vector_mw_task_ptr_clear(&tasks);
		}

		if (0 != manager->commits.values_num && manager->commit_task_num < manager->commit_limit)
			apm_manager_commit_tasks(manager, apm_config_attrs_acquire(apm_config.attrs));

	}

	zbx_supervisor_update_activity("%s [terminating]", unit_args->name);

	zbx_vector_mw_task_ptr_destroy(&tasks);

	/* on normal exit the shutdown message already has been processed and no more messages will be sent */
	if (SUCCEED != ZBX_EXIT_STATUS())
		zbx_rtc_unsubscribe_service(apm_args->config_timeout, ZBX_IPC_SERVICE_APM);

	apm_manager_free(manager);

	zbx_apm_db_config_clear(apm_args->export_config);
	zbx_free(proxy_apm_config);
	apm_config_clear(&apm_config);
	zbx_apm_db_config_clear(&apm_global);
	zbx_dc_config_local_release();
	zbx_free(args);

	return NULL;

#undef CONFIG_INTERVAL
#undef STAT_INTERVAL
}

