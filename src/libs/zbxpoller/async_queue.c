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

#include "async_queue.h"

#include "async_manager.h"

#include "zbxalgo.h"
#include "zbxcomms.h"

#define ASYNC_TASK_QUEUE_INIT_NONE	0x00
#define ASYNC_TASK_QUEUE_INIT_LOCK	0x01
#define ASYNC_TASK_QUEUE_INIT_EVENT	0x02

void	async_task_queue_destroy(zbx_async_queue_t *queue)
{
	if (0 != (queue->init_flags & ASYNC_TASK_QUEUE_INIT_LOCK))
		pthread_mutex_destroy(&queue->lock);

	if (0 != (queue->init_flags & ASYNC_TASK_QUEUE_INIT_EVENT))
		pthread_cond_destroy(&queue->event);

	zbx_vector_uint64_destroy(&queue->itemids);
	zbx_vector_int32_destroy(&queue->errcodes);
	zbx_vector_int32_destroy(&queue->lastclocks);

	zbx_vector_poller_item_clear_ext(&queue->poller_items, zbx_poller_item_free);
	zbx_vector_poller_item_destroy(&queue->poller_items);
	zbx_vector_interface_status_clear_ext(&queue->interfaces, zbx_interface_status_free);
	zbx_vector_interface_status_destroy(&queue->interfaces);

	queue->init_flags = ASYNC_TASK_QUEUE_INIT_NONE;
}

int	async_task_queue_init(zbx_async_queue_t *queue, zbx_thread_poller_args *poller_args_in, char **error)
{
	int	err, ret = FAIL;

	queue->workers_num = 0;
	queue->processing_num = 0;
	queue->processing_limit = poller_args_in->config_max_concurrent_checks_per_poller;
	queue->poller_type = poller_args_in->poller_type;
	queue->config_timeout = poller_args_in->config_comms->config_timeout;
	queue->config_unavailable_delay = poller_args_in->config_unavailable_delay;
	queue->config_unreachable_delay = poller_args_in->config_unreachable_delay;
	queue->config_unreachable_period = poller_args_in-> config_unreachable_period;

	zbx_vector_uint64_create(&queue->itemids);
	zbx_vector_int32_create(&queue->errcodes);
	zbx_vector_int32_create(&queue->lastclocks);
	zbx_vector_poller_item_create(&queue->poller_items);
	zbx_vector_interface_status_create(&queue->interfaces);

	if (0 != (err = pthread_mutex_init(&queue->lock, NULL)))
	{
		*error = zbx_dsprintf(NULL, "cannot initialize task queue mutex: %s", zbx_strerror(err));
		goto out;
	}
	queue->init_flags |= ASYNC_TASK_QUEUE_INIT_LOCK;

	if (0 != (err = pthread_cond_init(&queue->event, NULL)))
	{
		*error = zbx_dsprintf(NULL, "cannot initialize task queue conditional variable: %s", zbx_strerror(err));
		goto out;
	}
	queue->init_flags |= ASYNC_TASK_QUEUE_INIT_EVENT;

	ret = SUCCEED;
out:
	if (FAIL == ret)
		async_task_queue_destroy(queue);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: locks task queue                                                  *
 *                                                                            *
 ******************************************************************************/
void	async_task_queue_lock(zbx_async_queue_t *queue)
{
	pthread_mutex_lock(&queue->lock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: unlocks task queue                                                *
 *                                                                            *
 ******************************************************************************/
void	async_task_queue_unlock(zbx_async_queue_t *queue)
{
	pthread_mutex_unlock(&queue->lock);
}

/******************************************************************************
 *                                                                            *
 * Purpose: registers new worker                                              *
 *                                                                            *
 ******************************************************************************/
void	async_task_queue_register_worker(zbx_async_queue_t *queue)
{
	queue->workers_num++;
}

/******************************************************************************
 *                                                                            *
 * Purpose: deregisters worker                                                *
 *                                                                            *
 ******************************************************************************/
void	async_task_queue_deregister_worker(zbx_async_queue_t *queue)
{
	queue->workers_num--;
}

int	async_task_queue_wait(zbx_async_queue_t *queue, char **error)
{
	int	err;

	if (0 != (err = pthread_cond_wait(&queue->event, &queue->lock)))
	{
		*error = zbx_dsprintf(NULL, "cannot wait for conditional variable: %s", zbx_strerror(err));
		return FAIL;
	}

	return SUCCEED;
}

void	async_task_queue_notify(zbx_async_queue_t *queue)
{
	int	err;

	if (0 != (err = pthread_cond_signal(&queue->event)))
		zabbix_log(LOG_LEVEL_WARNING, "cannot signal conditional variable: %s", zbx_strerror(err));
}
