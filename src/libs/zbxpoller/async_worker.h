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

#ifndef ZABBIX_ASYNC_WORKER_H
#define ZABBIX_ASYNC_WORKER_H

#include "async_manager.h"
#include "async_queue.h"

typedef struct
{
	zbx_uint32_t			init_flags;
	int				stop;

	zbx_async_queue_t		*queue;
	pthread_t			thread;

	zbx_async_notify_cb_t		finished_cb;

	void				*finished_data;
	const char			*progname;
}
zbx_async_worker_t;

int	async_worker_init(zbx_async_worker_t *worker, zbx_async_queue_t *queue, const char *progname, char **error);
void	async_worker_stop(zbx_async_worker_t *worker);
void	async_worker_destroy(zbx_async_worker_t *worker);
void	async_worker_set_finished_cb(zbx_async_worker_t *worker, zbx_async_notify_cb_t finished_cb,
		void *finished_data);

#endif
