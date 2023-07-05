/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "discoverer_queue.h"
#include "discoverer_job.h"

#define DISCOVERER_QUEUE_INIT_NONE	0x00
#define DISCOVERER_QUEUE_INIT_LOCK	0x01
#define DISCOVERER_QUEUE_INIT_EVENT	0x02

/******************************************************************************
 *                                                                            *
 * Purpose: lock job queue                                                    *
 *                                                                            *
 ******************************************************************************/
void	discoverer_queue_lock(zbx_discoverer_queue_t *queue)
{
	pthread_mutex_lock(&queue->lock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: unlock job queue                                                  *
 *                                                                            *
 ******************************************************************************/
void	discoverer_queue_unlock(zbx_discoverer_queue_t *queue)
{
	pthread_mutex_unlock(&queue->lock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: notify one worker                                                 *
 *                                                                            *
 * Parameters: queue - [IN]                                                   *
 *                                                                            *
 * Comments: This function is used by manager to notify worker when single    *
 *           job have been pushed                                             *
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
 * Purpose: notify all workers                                                *
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
 * Purpose: pop job from job queue                                            *
 *                                                                            *
 * Parameters: queue - [IN]                                                   *
 *                                                                            *
 * Return value: The popped job or NULL if there are no jobs to be            *
 *               processed.                                                   *
 *                                                                            *
 ******************************************************************************/
zbx_discoverer_job_t	*discoverer_queue_pop(zbx_discoverer_queue_t *queue)
{
	void	*job;

	if (SUCCEED == zbx_list_pop(&queue->jobs, &job))
		return (zbx_discoverer_job_t*)job;

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: queue the job to be processed                                     *
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
 * Purpose: clear job list                                                    *
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
 * Purpose: destroy job queue                                                 *
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

	queue->flags = DISCOVERER_QUEUE_INIT_NONE;
}

/******************************************************************************
 *                                                                            *
 * Purpose: register a new worker                                             *
 *                                                                            *
 ******************************************************************************/
void	discoverer_queue_register_worker(zbx_discoverer_queue_t *queue)
{
	queue->workers_num++;
}

/******************************************************************************
 *                                                                            *
 * Purpose: deregister a worker                                               *
 *                                                                            *
 ******************************************************************************/
void	discoverer_queue_deregister_worker(zbx_discoverer_queue_t *queue)
{
	queue->workers_num--;
}

/******************************************************************************
 *                                                                            *
 * Purpose: wait for queue notifications                                      *
 *                                                                            *
 * Parameters: queue - [IN]                                                   *
 *             error - [OUT]                                                  *
 *                                                                            *
 * Return value: SUCCEED - the wait succeeded                                 *
 *               FAIL    - an error has occurred                              *
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
 * Purpose: initialize job queue                                              *
 *                                                                            *
 * Parameters: queue - [IN]                                                   *
 *             error - [OUT]                                                  *
 *                                                                            *
 * Return value: SUCCEED - the job queue was initialized successfully         *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	discoverer_queue_init(zbx_discoverer_queue_t *queue, char **error)
{
	int	err, ret = FAIL;

	queue->workers_num = 0;
	queue->pending_checks_count = 0;
	queue->flags = DISCOVERER_QUEUE_INIT_NONE;

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
