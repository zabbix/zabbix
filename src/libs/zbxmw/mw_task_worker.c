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

#include "zbxmw.h"
#include "zbxipcservice.h"

/******************************************************************************
 *                                                                            *
 * Purpose: initialize task worker                                            *
 *                                                                            *
 * Parameters: worker       - [OUT] worker to initialize                      *
 *             process_task - [IN] callback for processing tasks              *
 *                                                                            *
 ******************************************************************************/
void	zbx_mw_task_worker_init(zbx_mw_task_worker_t *worker, zbx_mw_process_task_f process_task)
{
	worker->process_task = process_task;
}

/******************************************************************************
 *                                                                            *
 * Purpose: task worker thread entry point                                    *
 *                                                                            *
 * Parameters: arg - [IN] zbx_mw_task_worker_t worker                         *
 *                                                                            *
 ******************************************************************************/
void	*zbx_mw_task_worker_entry(void *arg)
{
	zbx_mw_task_worker_t	*worker = (zbx_mw_task_worker_t *)arg;
	zbx_mw_queue_t		*queue = (zbx_mw_queue_t *)worker->base.queue;
	zbx_mw_task_t		*task;
	char			*error = NULL;

	zabbix_log(LOG_LEVEL_INFORMATION, "thread started");

	zbx_mw_queue_lock(queue);

	while (SUCCEED == zbx_mw_worker_is_running(&worker->base))
	{
		if (NULL != (task = (zbx_mw_task_t *)zbx_mw_queue_pop(queue)))
		{
			zbx_mw_queue_unlock(queue);
			zbx_mw_worker_report_busy(&worker->base);

			worker->process_task(&worker->base, task);

			zbx_mw_worker_report_idle(&worker->base);
			zbx_mw_queue_lock(queue);
			zbx_mw_queue_push_completed(queue, task);

			if (NULL != worker->base.service)
				zbx_ipc_service_alert(worker->base.service);

			continue;
		}

		if (SUCCEED != zbx_mw_queue_wait(queue, &error))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot wait for new task: %s", error);
			zbx_free(error);
			zbx_mw_worker_stop(&worker->base);
		}
	}

	zbx_mw_queue_unlock(queue);

	zabbix_log(LOG_LEVEL_INFORMATION, "thread stopped");

	return NULL;
}
