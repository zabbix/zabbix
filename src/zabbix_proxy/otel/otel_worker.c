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
#include "zabbix_proxy/otel/otel_task.h"
#include "zbxmw.h"
#include "zbxnix.h"
#include "zbxsupervisor_client.h"

zbx_otel_worker_t	*otel_worker_create(void)
{
	zbx_otel_worker_t	*worker;

	worker = (zbx_otel_worker_t *)zbx_calloc(NULL, 1, sizeof(zbx_otel_worker_t));

	return worker;
}

static void	otel_worker_process_message(zbx_otel_task_message_t *task)
{
	char	*error = NULL;

	if (SUCCEED != zbx_grpc_decode_request(task->request, task->type, &task->message, &error))
	{
		zabbix_log(LOG_LEVEL_WARNING, "%s", error);
		zbx_free(error);
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "OTEL: %s", task->message);
	}
}

void	*otel_worker_entry(void *args)
{
#define CEP_RTC_OPEN_TIMEOUT	10

	zbx_otel_worker_t	*worker = (zbx_otel_worker_t *)args;
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

			zabbix_log(LOG_LEVEL_DEBUG, "%s() process task type:%u", __func__, task->type);

			switch (task->type)
			{
				case OTEL_TASK_MESSAGE:
					otel_worker_process_message((zbx_otel_task_message_t *)task);
					break;
				default:
					THIS_SHOULD_NEVER_HAPPEN_MSG("unknown task type %d", task->type);
					break;
			}

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

