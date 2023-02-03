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

#ifndef ZABBIX_PP_WORKER_H
#define ZABBIX_PP_WORKER_H

#include "pp_queue.h"
#include "pp_execute.h"
#include "zbxtimekeeper.h"
#include "zbxpreproc.h"

typedef struct
{
	int			id;	/* TODO: for debug logging, remove */

	zbx_uint32_t		init_flags;
	int			stop;

	zbx_pp_queue_t		*queue;
	pthread_t		thread;

	zbx_pp_context_t	execute_ctx;

	zbx_timekeeper_t	*timekeeper;
}
zbx_pp_worker_t;

int	pp_worker_init(zbx_pp_worker_t *worker, int id, zbx_pp_queue_t *queue, zbx_timekeeper_t *timekeeper,
		char **error);
void	pp_worker_stop(zbx_pp_worker_t *worker);
void	pp_worker_destroy(zbx_pp_worker_t *worker);

#endif
