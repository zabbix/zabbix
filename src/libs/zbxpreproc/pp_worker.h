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

#ifndef ZABBIX_PP_WORKER_H
#define ZABBIX_PP_WORKER_H

#include "pp_queue.h"
#include "pp_execute.h"
#include "zbxtimekeeper.h"
#include "zbxpreproc.h"


typedef struct
{
	int				id;

	zbx_uint32_t			init_flags;
	int				stop;

	zbx_pp_queue_t			*queue;
	pthread_t			thread;

	zbx_pp_context_t		execute_ctx;

	zbx_timekeeper_t		*timekeeper;

	zbx_pp_notify_cb_t		finished_cb;

	void				*finished_data;

	zbx_log_component_t		logger;

	const char			*config_source_ip;
}
zbx_pp_worker_t;

int	pp_worker_init(zbx_pp_worker_t *worker, int id, zbx_pp_queue_t *queue, zbx_timekeeper_t *timekeeper,
		const char *config_source_ip, char **error);
void	pp_worker_set_finished_cb(zbx_pp_worker_t *worker, zbx_pp_notify_cb_t finished_cb, void *finished_data);
void	pp_worker_stop(zbx_pp_worker_t *worker);
void	pp_worker_destroy(zbx_pp_worker_t *worker);

#endif
