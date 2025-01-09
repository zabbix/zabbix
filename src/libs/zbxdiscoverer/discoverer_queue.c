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

#include "discoverer_queue.h"
#include "discoverer_job.h"
#include "zbx_discoverer_constants.h"
#include "discoverer_int.h"

#define DISCOVERER_QUEUE_INIT_NONE	0x00
#define DISCOVERER_QUEUE_INIT_LOCK	0x01
#define DISCOVERER_QUEUE_INIT_EVENT	0x02

/******************************************************************************
 *                                                                            *
 * Purpose: locks job queue                                                   *
 *                                                                            *
 ******************************************************************************/
void	discoverer_queue_lock(zbx_discoverer_queue_t *queue)
{
	pthread_mutex_lock(&queue->lock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: unlocks job queue                                                 *
 *                                                                            *
 ******************************************************************************/
void	discoverer_queue_unlock(zbx_discoverer_queue_t *queue)
{
	pthread_mutex_unlock(&queue->lock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: notifies one worker                                               *
 *                                                                            *
 * Parameters: queue - [IN]                                                   *
 *                                                                            *
 * Comments: This function is used by manager to notify worker when single    *
 *           job have been pushed.                                            *
 *                                                                            *
 ******************************************************************************/
void	discoverer_queue_notify(zbx_discoverer_queue_t *queue)
{
	int	err;

	if (0 != (err = pthread_cond_signal(&queue->event)))
		zabbix_log(LOG_LEVEL_WARNING, "cannot signal conditional variable: %s", zbx_strerror(err));
}

/******************************************************************************
 *                                                                            *
 * Purpose: notifies all workers                                              *
 *                                                                            *
 * Parameters: queue - [IN]                                                   *
 *                                                                            *
 * Comments: This function is used by manager to notify workers when either   *
 *           multiple jobs have been pushed or when stopping workers.         *
 *                                                                            *
 ******************************************************************************/
void	discoverer_queue_notify_all(zbx_discoverer_queue_t *queue)
{
	int	err;

	if (0 != (err = pthread_cond_broadcast(&queue->event)))
		zabbix_log(LOG_LEVEL_WARNING, "cannot broadcast conditional variable: %s", zbx_strerror(err));
}

/******************************************************************************
 *                                                                            *
 * Purpose: pops job from job queue and count control of snmpv3 workers       *
 *                                                                            *
 * Parameters: queue - [IN]                                                   *
 *                                                                            *
 * Return value: The popped job or NULL if there are no jobs to be            *
 *               processed.                                                   *
 *                                                                            *
 ******************************************************************************/
zbx_discoverer_job_t	*discoverer_queue_pop(zbx_discoverer_queue_t *queue)
{
	zbx_discoverer_job_t	*job = NULL;
	zbx_discoverer_task_t	*task;
	zbx_vector_uint64_t	ids;

	zbx_vector_uint64_create(&ids);

	while (SUCCEED == zbx_list_pop(&queue->jobs, (void*)&job))
	{
		if (SUCCEED != zbx_list_peek(&job->tasks, (void*)&task))
			break;

		if (SVC_SNMPv3 != GET_DTYPE(task))
			break;

		if (0 != queue->snmpv3_allowed_workers)
		{
			queue->snmpv3_allowed_workers--;
			break;
		}

		if (job->tasks.head == job->tasks.tail)	/* just one snmpv3 task in the list */
		{
			zbx_uint64_t	id = job->druleid;

			discoverer_queue_push(queue, job);
			job = NULL;

			if (queue->jobs.head == queue->jobs.tail)	/* just one snmpv3 job in the list */
				break;

			if (FAIL != zbx_vector_uint64_search(&ids, id, ZBX_DEFAULT_UINT64_COMPARE_FUNC))
				break;

			zbx_vector_uint64_append(&ids, id);
			continue;
		}

		(void)zbx_list_pop(&job->tasks, (void*)&task);
		(void)zbx_list_append(&job->tasks, (void*)task, NULL);

		break;
	}

	zbx_vector_uint64_destroy(&ids);

	return job;
}

/******************************************************************************
 *                                                                            *
 * Purpose: queues job to be processed                                        *
 *                                                                            *
 * Parameters: queue - [IN]                                                   *
 *             job   - [IN]                                                   *
 *                                                                            *
*******************************************************************************/
void	discoverer_queue_push(zbx_discoverer_queue_t *queue, zbx_discoverer_job_t *job)
{
	(void)zbx_list_append(&queue->jobs, job, NULL);
}

/******************************************************************************
 *                                                                            *
 * Purpose: clears job list                                                   *
 *                                                                            *
 ******************************************************************************/
void	discoverer_queue_clear_jobs(zbx_list_t *jobs)
{
	zbx_discoverer_job_t	*job = NULL;

	while (SUCCEED == zbx_list_pop(jobs, (void **)&job))
		discoverer_job_free(job);
}

/******************************************************************************
 *                                                                            *
 * Purpose: destroys job queue                                                *
 *                                                                            *
 ******************************************************************************/
void	discoverer_queue_destroy(zbx_discoverer_queue_t *queue)
{
	if (0 != (queue->flags & DISCOVERER_QUEUE_INIT_LOCK))
		pthread_mutex_destroy(&queue->lock);

	if (0 != (queue->flags & DISCOVERER_QUEUE_INIT_EVENT))
		pthread_cond_destroy(&queue->event);

	discoverer_queue_clear_jobs(&queue->jobs);
	zbx_list_destroy(&queue->jobs);

	zbx_vector_uint64_destroy(&queue->del_jobs);
	zbx_vector_discoverer_drule_error_clear_ext(&queue->errors, zbx_discoverer_drule_error_free);
	zbx_vector_discoverer_drule_error_destroy(&queue->errors);

	queue->flags = DISCOVERER_QUEUE_INIT_NONE;
}

/******************************************************************************
 *                                                                            *
 * Purpose: registers new worker                                              *
 *                                                                            *
 ******************************************************************************/
void	discoverer_queue_register_worker(zbx_discoverer_queue_t *queue)
{
	queue->workers_num++;
}

void	discoverer_queue_deregister_worker(zbx_discoverer_queue_t *queue)
{
	queue->workers_num--;
}

/******************************************************************************
 *                                                                            *
 * Purpose: waits for queue notifications                                     *
 *                                                                            *
 * Parameters: queue - [IN]                                                   *
 *             error - [OUT]                                                  *
 *                                                                            *
 * Return value: SUCCEED - wait succeeded                                     *
 *               FAIL    - error has occurred                                 *
 *                                                                            *
 * Comments: This function is used by workers to wait for new jobs.           *
 *                                                                            *
 ******************************************************************************/
int	discoverer_queue_wait(zbx_discoverer_queue_t *queue, char **error)
{
	int	err;

	if (0 != (err = pthread_cond_wait(&queue->event, &queue->lock)))
	{
		*error = zbx_dsprintf(NULL, "cannot wait for conditional variable: %s", zbx_strerror(err));
		return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: initializes job queue                                             *
 *                                                                            *
 * Parameters: queue - [IN]                                                   *
 *             error - [OUT]                                                  *
 *                                                                            *
 * Return value: SUCCEED - job queue was initialized successfully             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	discoverer_queue_init(zbx_discoverer_queue_t *queue, int snmpv3_allowed_workers, int checks_per_worker_max,
		char **error)
{
	int	err, ret = FAIL;

	queue->workers_num = 0;
	queue->pending_checks_count = 0;
	queue->snmpv3_allowed_workers = snmpv3_allowed_workers;
	queue->checks_per_worker_max = checks_per_worker_max;
	queue->flags = DISCOVERER_QUEUE_INIT_NONE;
	zbx_vector_discoverer_drule_error_create(&queue->errors);
	zbx_vector_uint64_create(&queue->del_jobs);

	zbx_list_create(&queue->jobs);

	if (0 != (err = pthread_mutex_init(&queue->lock, NULL)))
	{
		*error = zbx_dsprintf(NULL, "cannot initialize queue mutex: %s", zbx_strerror(err));
		goto out;
	}

	queue->flags |= DISCOVERER_QUEUE_INIT_LOCK;

	if (0 != (err = pthread_cond_init(&queue->event, NULL)))
	{
		*error = zbx_dsprintf(NULL, "cannot initialize conditional variable: %s", zbx_strerror(err));
		goto out;
	}

	queue->flags |= DISCOVERER_QUEUE_INIT_EVENT;

	ret = SUCCEED;
out:
	if (FAIL == ret)
		discoverer_queue_destroy(queue);

	return ret;
}

void	discoverer_queue_append_error(zbx_vector_discoverer_drule_error_t *errors, zbx_uint64_t druleid,
		const char *error)
{
	zbx_discoverer_drule_error_t	*derror_ptr, derror = {.druleid = druleid};
	int				i;

	if (FAIL == (i = zbx_vector_discoverer_drule_error_search(errors, derror,
			ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
	{
		derror.error = zbx_strdup(NULL, error);
		zbx_vector_discoverer_drule_error_append(errors, derror);
		return;
	}

	derror_ptr = &errors->values[i];

	if (NULL != strstr(derror_ptr->error, error))
		return;

	derror_ptr->error = zbx_dsprintf(derror_ptr->error, "%s\n%s", derror_ptr->error, error);
}
