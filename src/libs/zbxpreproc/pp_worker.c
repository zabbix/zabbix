/*
** Copyright (C) 2001-2025 Zabbix SIA
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

#include "pp_worker.h"
#include "pp_task.h"
#include "pp_queue.h"
#include "pp_execute.h"

#include "zbxpreproc.h"
#include "zbxalgo.h"
#include "zbxregexp.h"
#include "zbxthreads.h"

#define PP_WORKER_INIT_NONE	0x00
#define PP_WORKER_INIT_THREAD	0x01

/******************************************************************************
 *                                                                            *
 * Purpose: process preprocessing testing task                                *
 *                                                                            *
 ******************************************************************************/
static void	pp_task_process_test(zbx_pp_context_t *ctx, zbx_pp_task_t *task, const char *config_source_ip)
{
	zbx_pp_task_test_t	*d = (zbx_pp_task_test_t *)PP_TASK_DATA(task);

	pp_execute(ctx, d->preproc, NULL, NULL, &d->value, d->ts, config_source_ip, &d->result, &d->results,
			&d->results_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process value preprocessing task                                  *
 *                                                                            *
 ******************************************************************************/
static void	pp_task_process_value(zbx_pp_context_t *ctx, zbx_pp_task_t *task, const char *config_source_ip)
{
	zbx_pp_task_value_t	*d = (zbx_pp_task_value_t *)PP_TASK_DATA(task);

	pp_execute(ctx, d->preproc, d->cache, d->um_handle, &d->value, d->ts, config_source_ip, &d->result, NULL, NULL);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process dependent preprocessing task                              *
 *                                                                            *
 ******************************************************************************/
static void	pp_task_process_dependent(zbx_pp_context_t *ctx, zbx_pp_task_t *task, const char *config_source_ip)
{
	zbx_pp_task_dependent_t	*d = (zbx_pp_task_dependent_t *)PP_TASK_DATA(task);
	zbx_pp_task_value_t	*d_first = (zbx_pp_task_value_t *)PP_TASK_DATA(d->primary);

	pp_execute(ctx, d_first->preproc, d->cache, d_first->um_handle, &d_first->value, d_first->ts, config_source_ip,
			&d_first->result, NULL, NULL);
}

/******************************************************************************
 *                                                                            *
 * Purpose: process first task in sequence task                               *
 *                                                                            *
 ******************************************************************************/
static	void	pp_task_process_sequence(zbx_pp_context_t *ctx, zbx_pp_task_t *task_seq, const char *config_source_ip)
{
	zbx_pp_task_sequence_t	*d_seq = (zbx_pp_task_sequence_t *)PP_TASK_DATA(task_seq);
	zbx_pp_task_t		*task;

	if (SUCCEED == zbx_list_peek(&d_seq->tasks, (void **)&task))
	{
		switch (task->type)
		{
			case ZBX_PP_TASK_VALUE:
			case ZBX_PP_TASK_VALUE_SEQ:
				pp_task_process_value(ctx, task, config_source_ip);
				break;
			case ZBX_PP_TASK_DEPENDENT:
				pp_task_process_dependent(ctx, task, config_source_ip);
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				break;
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: preprocessing worker thread entry                                 *
 *                                                                            *
 ******************************************************************************/
static void	*pp_worker_entry(void *args)
{
	zbx_pp_worker_t		*worker = (zbx_pp_worker_t *)args;
	zbx_pp_queue_t		*queue = worker->queue;
	zbx_pp_task_t		*in;
	char			*error = NULL, component[MAX_ID_LEN + 1];
	sigset_t		mask;
	int			err;

	zbx_snprintf(component, sizeof(component), "%d", worker->id);
	zbx_set_log_component(component, &worker->logger);

	zabbix_log(LOG_LEVEL_INFORMATION, "thread started [%s #%d]",
			get_process_type_string(ZBX_PROCESS_TYPE_PREPROCESSOR), worker->id);

	zbx_init_regexp_env();

	sigemptyset(&mask);
	sigaddset(&mask, SIGTERM);
	sigaddset(&mask, SIGUSR1);
	sigaddset(&mask, SIGUSR2);
	sigaddset(&mask, SIGHUP);
	sigaddset(&mask, SIGQUIT);
	sigaddset(&mask, SIGINT);

	if (0 != (err = pthread_sigmask(SIG_BLOCK, &mask, NULL)))
		zabbix_log(LOG_LEVEL_WARNING, "cannot block signals: %s", zbx_strerror(err));

	worker->stop = 0;

	pp_context_init(&worker->execute_ctx);
	pp_task_queue_lock(queue);
	pp_task_queue_register_worker(queue);

	while (0 == worker->stop)
	{
		if (NULL != (in = pp_task_queue_pop_new(queue)))
		{
			pp_task_queue_unlock(queue);

			zbx_timekeeper_update(worker->timekeeper, worker->id - 1, ZBX_PROCESS_STATE_BUSY);

			zabbix_log(LOG_LEVEL_TRACE, "%s() process task type:%u itemid:" ZBX_FS_UI64, __func__,
					in->type, in->itemid);

			switch (in->type)
			{
				case ZBX_PP_TASK_TEST:
					pp_task_process_test(&worker->execute_ctx, in, worker->config_source_ip);
					break;
				case ZBX_PP_TASK_VALUE:
				case ZBX_PP_TASK_VALUE_SEQ:
					pp_task_process_value(&worker->execute_ctx, in, worker->config_source_ip);
					break;
				case ZBX_PP_TASK_DEPENDENT:
					pp_task_process_dependent(&worker->execute_ctx, in, worker->config_source_ip);
					break;
				case ZBX_PP_TASK_SEQUENCE:
					pp_task_process_sequence(&worker->execute_ctx, in, worker->config_source_ip);
					break;
			}

			zbx_timekeeper_update(worker->timekeeper, worker->id - 1, ZBX_PROCESS_STATE_IDLE);

			pp_task_queue_lock(queue);
			pp_task_queue_push_finished(queue, in);

			if (NULL != worker->finished_cb)
				worker->finished_cb(worker->finished_data);

			continue;
		}

		if (SUCCEED != pp_task_queue_wait(queue, &error))
		{
			zabbix_log(LOG_LEVEL_WARNING, "[%d] %s", worker->id, error);
			zbx_free(error);
			worker->stop = 1;
		}

		if (1 < queue->pending_num)
			pp_task_queue_notify(queue);
	}

	pp_task_queue_deregister_worker(queue);
	pp_task_queue_unlock(queue);

	zabbix_log(LOG_LEVEL_INFORMATION, "thread stopped [%s #%d]",
			get_process_type_string(ZBX_PROCESS_TYPE_PREPROCESSOR), worker->id);

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize and start preprocessing worker                         *
 *                                                                            *
 * Parameters: worker           - [IN] preprocessing worker                   *
 *             id               - [IN] worker id (index)                      *
 *             queue            - [IN] task queue                             *
 *             timekeeper       - [IN] timekeeper object for busy/idle worker *
 *                                     state reporting                        *
 *             config_source_ip - [IN]                                        *
 *             error            - [OUT]                                       *
 *                                                                            *
 * Return value: SUCCEED - the worker was initialized and started             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	pp_worker_init(zbx_pp_worker_t *worker, int id, zbx_pp_queue_t *queue, zbx_timekeeper_t *timekeeper,
		const char *config_source_ip, char **error)
{
	int		err, ret = FAIL;
	pthread_attr_t	attr;

	worker->id = id;
	worker->queue = queue;
	worker->timekeeper = timekeeper;
	worker->config_source_ip = config_source_ip;

	zbx_pthread_init_attr(&attr);
	if (0 != (err = pthread_create(&worker->thread, &attr, pp_worker_entry, (void *)worker)))
	{
		*error = zbx_dsprintf(NULL, "cannot create thread: %s", zbx_strerror(err));
		goto out;
	}
	worker->init_flags |= PP_WORKER_INIT_THREAD;

	ret = SUCCEED;
out:
	if (FAIL == ret)
		pp_worker_stop(worker);

	return err;
}

/******************************************************************************
 *                                                                            *
 * Purpose: stop the worker thread                                            *
 *                                                                            *
 ******************************************************************************/
void	pp_worker_stop(zbx_pp_worker_t *worker)
{
	if (0 != (worker->init_flags & PP_WORKER_INIT_THREAD))
		worker->stop = 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: destroy the worker                                                *
 *                                                                            *
 ******************************************************************************/
void	pp_worker_destroy(zbx_pp_worker_t *worker)
{
	if (0 != (worker->init_flags & PP_WORKER_INIT_THREAD))
	{
		void	*retval;

		pthread_join(worker->thread, &retval);
	}

	pp_context_destroy(&worker->execute_ctx);

	worker->init_flags = PP_WORKER_INIT_NONE;
}

/******************************************************************************
 *                                                                            *
 * Purpose: set callback to call after task is processed                      *
 *                                                                            *
 * Parameters: worker         - [IN] the preprocessing worker                 *
 *             finished_cb   - [IN] a callback to call after finishing        *
 *                                     task                                   *
 *             finished_data - [IN] the callback data                         *
 *                                                                            *
 ******************************************************************************/
void	pp_worker_set_finished_cb(zbx_pp_worker_t *worker, zbx_pp_notify_cb_t finished_cb, void *finished_data)
{
	worker->finished_cb = finished_cb;
	worker->finished_data = finished_data;
}
