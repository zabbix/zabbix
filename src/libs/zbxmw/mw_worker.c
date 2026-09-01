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
#include "zbxcommon.h"
#include "zbxipcservice.h"
#include "zbxlog.h"
#include "zbxmw.h"
#include "zbxnix.h"
#include "zbxthreads.h"
#include "zbxtimekeeper.h"
#include "zbxtypes.h"
#include "zbxalgo.h"

ZBX_PTR_VECTOR_LITE_IMPL(mw_task_ptr, zbx_mw_task_t *)

/******************************************************************************
 *                                                                            *
 * Purpose: initialize worker                                                 *
 *                                                                            *
 * Parameters: worker     - [OUT]                                             *
 *             id         - [IN] worker id                                    *
 *             service    - [IN] IPC service                                  *
 *             queue      - [IN] task queue                                   *
 *             timekeeper - [IN]                                              *
 *                                                                            *
 ******************************************************************************/
void	mw_worker_init(zbx_mw_worker_t *worker, int id,  zbx_ipc_service_t *service, zbx_mw_queue_t *queue,
		zbx_timekeeper_t *timekeeper)
{
	worker->state = MW_WORKER_STATE_FREE;
	worker->id = id;
	worker->timekeeper = timekeeper;
	worker->queue = queue;
	worker->service = service;
}

/******************************************************************************
 *                                                                            *
 * Purpose: release resources allocated by worker                             *
 *                                                                            *
 * Comments: Joins the worker thread only if it was started.                  *
 *                                                                            *
 ******************************************************************************/
void	mw_worker_clear(zbx_mw_worker_t *worker)
{
	if (0 == (atomic_load(&worker->state) & MW_WORKER_STATE_STARTING))
		return;

	void	*retval;

	pthread_join(worker->thread, &retval);
}

/******************************************************************************
 *                                                                            *
 * Purpose: worker thread entry point                                         *
 *                                                                            *
 * Parameters: a - [IN] thread arguments                                      *
 *                                                                            *
 * Return value: return value of the worker entry function                    *
 *                                                                            *
 ******************************************************************************/
static void	*mw_worker_entry(void *a)
{
	zbx_mw_worker_args_t	*args = (zbx_mw_worker_args_t *)a;
	zbx_mw_worker_t		*worker = args->worker;
	sigjmp_buf		jmp_ret;
	void *(*worker_entry)(void *) = args->worker_entry;
	void			*ret;

	atomic_fetch_or(&worker->state, MW_WORKER_STATE_STARTING);

	zbx_init_thread_signal_handler(&jmp_ret);
	if (0 != sigsetjmp(jmp_ret, 1))
	{
		atomic_fetch_or(&worker->state, MW_WORKER_STATE_STOPPED);
		return ZBX_THREAD_FAILURE;
	}

	zbx_snprintf(worker->name, sizeof(worker->name), "%s #%d", get_process_type_string(args->process_type),
		worker->id);

	zbx_set_log_component(worker->name, &worker->logger);
	zbx_free(args);

	atomic_fetch_or(&worker->state, MW_WORKER_STATE_RUNNING);
	ret = worker_entry(worker);

	atomic_fetch_or(&worker->state, MW_WORKER_STATE_STOPPED);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: start worker thread                                               *
 *                                                                            *
 * Parameters: worker       - [IN/OUT]                                        *
 *             process_type - [IN] worker process type                        *
 *             worker_entry - [IN] worker thread entry point                  *
 *             error        - [OUT] error message                             *
 *                                                                            *
 * Return value: SUCCEED on success, FAIL otherwise                           *
 *                                                                            *
 ******************************************************************************/
int	mw_worker_start(zbx_mw_worker_t *worker, unsigned char process_type, void *(*worker_entry)(void *),
		char **error)
{
	int			err, ret = FAIL;
	pthread_attr_t		attr;
	zbx_mw_worker_args_t	*args;

	zbx_pthread_init_attr(&attr);

	args = (zbx_mw_worker_args_t *)zbx_malloc(NULL, sizeof(zbx_mw_worker_args_t));
	args->worker = worker;
	args->process_type = process_type;
	args->worker_entry = worker_entry;

	zbx_timekeeper_reset(worker->timekeeper, worker->id - 1);

	if (0 != (err = pthread_create(&worker->thread, &attr, mw_worker_entry, (void *)args)))
	{
		*error = zbx_dsprintf(NULL, "cannot create thread: %s", zbx_strerror(err));
		zbx_free(args);
		goto out;
	}

	ret = SUCCEED;
out:
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: request worker to stop                                            *
 *                                                                            *
 ******************************************************************************/
void	zbx_mw_worker_stop(zbx_mw_worker_t *worker)
{
	atomic_fetch_or(&worker->state, MW_WORKER_STATE_STOPPING);
}

/******************************************************************************
 *                                                                            *
 * Purpose: join worker thread and reset its state                            *
 *                                                                            *
 ******************************************************************************/
void	mw_worker_join(zbx_mw_worker_t *worker)
{
	void	*retval;

	pthread_join(worker->thread, &retval);
	zbx_timekeeper_reset(worker->timekeeper, worker->id - 1);
	atomic_store(&worker->state, MW_WORKER_STATE_FREE);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if worker is running                                        *
 *                                                                            *
 * Return value: SUCCEED if worker is running, FAIL otherwise                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_mw_worker_is_running(zbx_mw_worker_t *worker)
{
	if ((MW_WORKER_STATE_STARTING | MW_WORKER_STATE_RUNNING) == atomic_load(&worker->state))
		return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: report worker busy state to timekeeper                            *
 *                                                                            *
 * Parameters: worker - [IN]                                                  *
 *                                                                            *
 ******************************************************************************/
void	zbx_mw_worker_report_busy(zbx_mw_worker_t *worker)
{
	zbx_timekeeper_update(worker->timekeeper, worker->id - 1, ZBX_PROCESS_STATE_BUSY);
}

/******************************************************************************
 *                                                                            *
 * Purpose: report worker idle state to timekeeper                            *
 *                                                                            *
 * Parameters: worker - [IN]                                                  *
 *                                                                            *
 ******************************************************************************/
void	zbx_mw_worker_report_idle(zbx_mw_worker_t *worker)
{
	zbx_timekeeper_update(worker->timekeeper, worker->id - 1, ZBX_PROCESS_STATE_IDLE);
}

/******************************************************************************
 *                                                                            *
 * Purpose: notify (wake up) manager                                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_mw_worker_notify(zbx_mw_worker_t *worker)
{
	if (NULL == worker->service)
	{
		THIS_SHOULD_NEVER_HAPPEN_MSG("attempt to notify non existing manager service");
		return;
	}

	zbx_ipc_service_alert(worker->service);
}

