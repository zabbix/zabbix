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

#include "async_worker.h"

#include "async_manager.h"

#include "zbxalgo.h"
#include "zbxtime.h"
#include "zbxthreads.h"
#include "zbxcacheconfig.h"
#include "zbxexpression.h"
#include "zbx_availability_constants.h"
#include "zbxpoller.h"
#include "zbxavailability.h"
#include "zbxinterface.h"

#define ASYNC_WORKER_INIT_NONE		0x00
#define ASYNC_WORKER_INIT_THREAD	0x01

static zbx_poller_item_t	*dc_config_async_get_poller_items(zbx_uint64_t processing_num,
		unsigned char poller_type, int config_timeout, zbx_uint64_t processing_limit)
{
	zbx_poller_item_t	*poller_item;

	poller_item = zbx_malloc(NULL, sizeof(zbx_poller_item_t));
	poller_item->items = NULL;

	poller_item->num = zbx_dc_config_get_poller_items(poller_type, config_timeout,
			processing_num, processing_limit, &poller_item->items);

	if (0 != poller_item->num)
	{
		poller_item->results = zbx_malloc(NULL, (size_t)poller_item->num * sizeof(AGENT_RESULT));
		poller_item->errcodes = zbx_malloc(NULL, (size_t)poller_item->num * sizeof(int));

		zbx_prepare_items(poller_item->items, poller_item->errcodes, poller_item->num, poller_item->results,
				ZBX_MACRO_EXPAND_YES);
	}
	else
	{
		zbx_free(poller_item->items);
		zbx_free(poller_item);
	}

	return poller_item;
}

static void	poller_update_interfaces(zbx_vector_interface_status_t *interfaces,
		int config_unavailable_delay, int config_unreachable_period, int config_unreachable_delay)
{
	zbx_interface_status_t	*interface_status;
	unsigned char		*data = NULL;
	size_t			data_alloc = 0, data_offset = 0;
	zbx_timespec_t		timespec;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() num:%d", __func__, interfaces->values_num);

	zbx_timespec(&timespec);

	for (int i = 0; i < interfaces->values_num; i++)
	{
		int	type;

		interface_status = interfaces->values[i];

		type = INTERFACE_TYPE_SNMP == interface_status->interface.type ? ITEM_TYPE_SNMP :
				ITEM_TYPE_ZABBIX;

		switch (interface_status->errcode)
		{
			case SUCCEED:
			case NOTSUPPORTED:
			case AGENT_ERROR:
				zbx_activate_item_interface(&timespec, &interface_status->interface,
						interface_status->itemid, type,
						interface_status->host, interface_status->version, &data, &data_alloc,
						&data_offset);
				break;
			case NETWORK_ERROR:
			case GATEWAY_ERROR:
			case TIMEOUT_ERROR:
				zbx_deactivate_item_interface(&timespec, &interface_status->interface,
						interface_status->itemid,
						type, interface_status->host,
						interface_status->key_orig, &data, &data_alloc, &data_offset,
						config_unavailable_delay,
						config_unreachable_period,
						config_unreachable_delay,
						interface_status->error);
				break;
			case CONFIG_ERROR:
				/* nothing to do */
				break;
			case SIG_ERROR:
				/* nothing to do, execution was forcibly interrupted by signal */
				break;
			default:
				zbx_error("unknown response code returned: %d", interface_status->errcode);
				THIS_SHOULD_NEVER_HAPPEN;
		}

	}

	if (NULL != data)
	{
		zbx_availability_send(ZBX_IPC_AVAILABILITY_REQUEST, data, (zbx_uint32_t)data_offset, NULL);
		zbx_free(data);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: works with configuration cache without blocking main thread       *
 *                                                                            *
 ******************************************************************************/
static void	*async_worker_entry(void *args)
{
	zbx_async_worker_t		*worker = (zbx_async_worker_t *)args;
	zbx_async_queue_t		*queue = worker->queue;
	char				*error = NULL;
	sigset_t			mask;
	int				err;
	zbx_vector_interface_status_t	interfaces;
	zbx_vector_uint64_t		itemids;
	zbx_vector_int32_t		errcodes;
	zbx_vector_int32_t		lastclocks;

	zabbix_log(LOG_LEVEL_INFORMATION, "thread started");

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

	async_task_queue_lock(queue);
	async_task_queue_register_worker(queue);

	zbx_vector_interface_status_create(&interfaces);

	zbx_vector_uint64_create(&itemids);
	zbx_vector_int32_create(&errcodes);
	zbx_vector_int32_create(&lastclocks);

	const unsigned char	poller_type = queue->poller_type;
	const zbx_uint64_t	processing_limit = queue->processing_limit;
	zbx_uint64_t		queue_poller_items_values_num, processing_num = queue->processing_num;
	const int		config_timeout = queue->config_timeout,
				config_unavailable_delay = queue->config_unavailable_delay,
				config_unreachable_period = queue->config_unreachable_period,
				config_unreachable_delay = queue->config_unreachable_delay;

	while (0 == worker->stop)
	{
		zbx_poller_item_t	*poller_item = NULL;
		unsigned char		check_queue = queue->check_queue;

		queue->check_queue = 0;

		if (0 != queue->interfaces.values_num)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "interfaces num:%d", queue->interfaces.values_num);

			zbx_vector_interface_status_append_array(&interfaces, queue->interfaces.values,
					queue->interfaces.values_num);
			zbx_vector_interface_status_clear(&queue->interfaces);
		}

		if (0 != queue->itemids.values_num)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "requeue num:%d", queue->itemids.values_num);

			zbx_vector_uint64_append_array(&itemids, queue->itemids.values, queue->itemids.values_num);
			zbx_vector_int32_append_array(&errcodes, queue->errcodes.values, queue->errcodes.values_num);
			zbx_vector_int32_append_array(&lastclocks, queue->lastclocks.values,
					queue->lastclocks.values_num);

			zbx_vector_uint64_clear(&queue->itemids);
			zbx_vector_int32_clear(&queue->lastclocks);
			zbx_vector_int32_clear(&queue->errcodes);

			processing_num = queue->processing_num -= itemids.values_num;
		}

		queue_poller_items_values_num = queue->poller_items.values_num;

		async_task_queue_unlock(queue);

		if (0 != interfaces.values_num)
		{
			poller_update_interfaces(&interfaces, config_unavailable_delay,
					config_unreachable_period, config_unreachable_delay);
			zbx_vector_interface_status_clear_ext(&interfaces, zbx_interface_status_free);
		}

		if (0 != itemids.values_num)
		{
			int	nextcheck;

			zbx_dc_poller_requeue_items(itemids.values, lastclocks.values, errcodes.values,
					(size_t)itemids.values_num, poller_type, &nextcheck);

			if (FAIL == nextcheck || nextcheck > time(NULL))
				check_queue = 0;
			else
				check_queue = 1;

			zbx_vector_int32_clear(&lastclocks);
			zbx_vector_int32_clear(&errcodes);
			zbx_vector_uint64_clear(&itemids);

			zabbix_log(LOG_LEVEL_DEBUG, "requeue items nextcheck:%d", nextcheck);
		}

		/* only check queue if requested to preserve resources */
		if (1 == check_queue)
		{
			poller_item = dc_config_async_get_poller_items(processing_num, poller_type, config_timeout,
					processing_limit);

			zabbix_log(LOG_LEVEL_DEBUG, "queue processing_num:" ZBX_FS_UI64 " pending:" ZBX_FS_UI64,
					processing_num, queue_poller_items_values_num);
		}

		async_task_queue_lock(queue);

		if (NULL != poller_item)
		{
			processing_num = queue->processing_num += poller_item->num;
			zbx_vector_poller_item_append(&queue->poller_items, poller_item);

			if (NULL != worker->finished_cb)
				worker->finished_cb(worker->finished_data);
		}

		if (SUCCEED != async_task_queue_wait(queue, &error))
		{
			zabbix_log(LOG_LEVEL_WARNING, "%s", error);
			zbx_free(error);
			worker->stop = 1;
		}
	}

	async_task_queue_deregister_worker(queue);
	async_task_queue_unlock(queue);

	zbx_vector_interface_status_clear_ext(&interfaces, zbx_interface_status_free);
	zbx_vector_interface_status_destroy(&interfaces);

	zbx_vector_int32_destroy(&lastclocks);
	zbx_vector_int32_destroy(&errcodes);
	zbx_vector_uint64_destroy(&itemids);

	zabbix_log(LOG_LEVEL_INFORMATION, "thread stopped");

	return NULL;
}

int	async_worker_init(zbx_async_worker_t *worker, zbx_async_queue_t *queue, const char *progname, char **error)
{
	int		err, ret = FAIL;
	pthread_attr_t	attr;

	worker->progname = progname;
	worker->queue = queue;

	zbx_pthread_init_attr(&attr);
	if (0 != (err = pthread_create(&worker->thread, &attr, async_worker_entry, (void *)worker)))
	{
		*error = zbx_dsprintf(NULL, "cannot create thread: %s", zbx_strerror(err));
		goto out;
	}
	worker->init_flags |= ASYNC_WORKER_INIT_THREAD;

	ret = SUCCEED;
out:
	if (FAIL == ret)
		async_worker_stop(worker);

	return err;
}

void	async_worker_stop(zbx_async_worker_t *worker)
{
	if (0 != (worker->init_flags & ASYNC_WORKER_INIT_THREAD))
		worker->stop = 1;
}

void	async_worker_destroy(zbx_async_worker_t *worker)
{
	if (0 != (worker->init_flags & ASYNC_WORKER_INIT_THREAD))
	{
		void	*retval;

		pthread_join(worker->thread, &retval);
	}

	worker->init_flags = ASYNC_WORKER_INIT_NONE;
}

/******************************************************************************
 *                                                                            *
 * Purpose: sets callback to call after task is processed                     *
 *                                                                            *
 * Parameters: worker        - [IN]                                           *
 *             finished_cb   - [IN] callback to call after finishing          *
 *                                  task                                      *
 *             finished_data - [IN] callback data                             *
 *                                                                            *
 ******************************************************************************/
void	async_worker_set_finished_cb(zbx_async_worker_t *worker, zbx_async_notify_cb_t finished_cb, void *finished_data)
{
	worker->finished_cb = finished_cb;
	worker->finished_data = finished_data;
}
