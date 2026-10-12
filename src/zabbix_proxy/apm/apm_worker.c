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

#include "apm_worker.h"
#include "apm_dataset.h"
#include "apm_decode.h"
#include "apm_exporter.h"
#include "apm_dataset.h"
#include "apm_task.h"
#include "zbxmw.h"
#include "zbxcommon.h"
#include "zbxtypes.h"
#include "zbxalgo.h"
#include "zbxnix.h"
#include "zbxsupervisor_client.h"

/******************************************************************************
 *                                                                            *
 * Purpose: create an APM worker                                              *
 *                                                                            *
 * Parameters: exporters    - [IN] exporter pool shared by all workers        *
 *             commit_stats - [IN] commit statistics shared by all            *
 *                                 workers                                    *
 *                                                                            *
 * Return value: created worker                                               *
 *                                                                            *
 ******************************************************************************/
zbx_apm_worker_t	*apm_worker_create(zbx_apm_exporter_pool_t *exporters, zbx_apm_commit_stats_t *commit_stats)
{
	zbx_apm_worker_t	*worker;

	worker = (zbx_apm_worker_t *)zbx_calloc(NULL, 1, sizeof(zbx_apm_worker_t));
	worker->exporters = exporters;
	worker->commit_stats = commit_stats;

	return worker;
}

/******************************************************************************
 *                                                                            *
 * Purpose: decode and commit the request tasks accumulated in a commit       *
 *          task                                                              *
 *                                                                            *
 * Parameters: worker - [IN] worker processing the task                       *
 *             task   - [IN] commit task to process                           *
 *                                                                            *
 ******************************************************************************/
static void	apm_worker_process_commit(zbx_apm_worker_t *worker, zbx_apm_task_commit_t *task)
{
#define APM_MAX_RETRY_DELAY	10

	zbx_apm_dataset_t	ds;
	zbx_vector_tag_t	*attrs;
	int			ret, retries = 0;

	apm_dataset_init(&ds);

	for (int i = 0; i < task->tasks.values_num; i++)
	{
		zbx_apm_task_request_t	*t = (zbx_apm_task_request_t *)task->tasks.values[i];

		switch (t->type)
		{
			case APM_METRICS:
				attrs = &task->attrs->metrics;
				break;
			case APM_LOGS:
				attrs = &task->attrs->logs;
				break;
			case APM_TRACES:
				attrs = &task->attrs->traces;
				break;
		}

		zbx_apm_request_decode(t->request, t->type, &ds, attrs);
	}

	zbx_apm_exporter_t	*exporter;

	exporter = apm_exporter_acquire(worker->exporters);

	apm_dataset_flush_stats(&ds, worker->commit_stats);

	do
	{
		if (1 < retries)
		{
			int	delay;

			if (5 < retries)
				delay = APM_MAX_RETRY_DELAY;
			else
				delay = 1 << (retries - 2);

			zabbix_log(LOG_LEVEL_WARNING, "ClickHouse database is down, retrying in %d seconds",
					delay);

			for (int i = 0; i < delay && SUCCEED == zbx_mw_worker_is_running(&worker->base); i++)
				sleep(1);
		}

		ret = apm_exporter_commit(exporter, &ds);
		retries++;
	}
	while (0 != (ret & APM_COMMIT_RETRY) && SUCCEED == zbx_mw_worker_is_running(&worker->base));

	/* uncommitted rows are left in dataset, undo commit stats for them */
	if (APM_COMMIT_OK != ret)
		apm_dataset_undo_stats(&ds, worker->commit_stats);

	apm_exporter_release(worker->exporters, exporter);

	apm_dataset_destroy(&ds);

#undef APM_MAX_RETRY_DELAY
}

/******************************************************************************
 *                                                                            *
 * Purpose: entry point of an APM worker thread                               *
 *                                                                            *
 ******************************************************************************/
void	*apm_worker_entry(void *args)
{
#define CEP_RTC_OPEN_TIMEOUT	10

	zbx_apm_worker_t	*worker = (zbx_apm_worker_t *)args;
	char			*error = NULL;

	zbx_supervisor_update_activity("%s starting", worker->base.name);

	zabbix_log(LOG_LEVEL_INFORMATION, "thread started");
	zbx_supervisor_update_activity("%s running", worker->base.name);

	zbx_mw_queue_lock(worker->base.queue);

	while (SUCCEED == zbx_mw_worker_is_running(&worker->base))
	{
		zbx_mw_task_t	*task;

		if (NULL != (task = zbx_mw_queue_pop(worker->base.queue)))
		{
			zbx_mw_queue_unlock(worker->base.queue);
			zbx_mw_worker_report_busy(&worker->base);

			zabbix_log(LOG_LEVEL_DEBUG, "%s() process task type:%u", __func__, task->type);

			switch (task->type)
			{
				case APM_TASK_COMMIT:
					apm_worker_process_commit(worker, (zbx_apm_task_commit_t *)task);
					break;
				case APM_TASK_REQUEST:
					THIS_SHOULD_NEVER_HAPPEN_MSG("incomplete request task received");
					break;
				default:
					THIS_SHOULD_NEVER_HAPPEN_MSG("unknown task type %d", task->type);
					break;
			}

			zbx_mw_worker_report_idle(&worker->base);
			zbx_mw_queue_lock(worker->base.queue);
			zbx_mw_queue_push_completed(worker->base.queue, task);
			zbx_mw_worker_notify(&worker->base);

			continue;
		}

		if (SUCCEED != zbx_mw_queue_wait(worker->base.queue, &error))
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s", error);
			zbx_free(error);

			zbx_mw_worker_stop(&worker->base);

			zbx_set_exiting_with_fail();
		}
	}

	zbx_mw_queue_unlock(worker->base.queue);

	zbx_supervisor_update_activity("%s stopped", worker->base.name);
	zabbix_log(LOG_LEVEL_INFORMATION, "thread stopped");

	return NULL;

#undef CEP_RTC_OPEN_TIMEOUT
}

