/*
** Copyright (C) 2001-2024 Zabbix SIA
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

#ifndef ZABBIX_PP_QUEUE_H
#define ZABBIX_PP_QUEUE_H

#include "zbxpreproc.h"
#include "zbxalgo.h"

typedef struct
{
	zbx_uint32_t	init_flags;
	int		workers_num;
	zbx_uint64_t	pending_num;
	zbx_uint64_t	finished_num;
	zbx_uint64_t	processing_num;

	zbx_hashset_t	sequences;

	zbx_list_t	pending;
	zbx_list_t	immediate;
	zbx_list_t	finished;

	pthread_mutex_t	lock;
	pthread_cond_t	event;
}
zbx_pp_queue_t;

int	pp_task_queue_init(zbx_pp_queue_t *queue, char **error);
void	pp_task_queue_destroy(zbx_pp_queue_t *queue);

void	pp_task_queue_lock(zbx_pp_queue_t *queue);
void	pp_task_queue_unlock(zbx_pp_queue_t *queue);
void	pp_task_queue_register_worker(zbx_pp_queue_t *queue);
void	pp_task_queue_deregister_worker(zbx_pp_queue_t *queue);
void	pp_task_queue_remove_sequence(zbx_pp_queue_t *queue, zbx_uint64_t itemid);

int	pp_task_queue_wait(zbx_pp_queue_t *queue, char **error);
void	pp_task_queue_notify(zbx_pp_queue_t *queue);
void	pp_task_queue_notify_all(zbx_pp_queue_t *queue);

void	pp_task_queue_push_test(zbx_pp_queue_t *queue, zbx_pp_task_t *task);
void	pp_task_queue_push(zbx_pp_queue_t *queue, zbx_pp_task_t *task);

zbx_pp_task_t	*pp_task_queue_pop_new(zbx_pp_queue_t *queue);
void	pp_task_queue_push_immediate(zbx_pp_queue_t *queue, zbx_pp_task_t *task);
void	pp_task_queue_push_finished(zbx_pp_queue_t *queue, zbx_pp_task_t *task);
zbx_pp_task_t	*pp_task_queue_pop_finished(zbx_pp_queue_t *queue);

void	pp_task_queue_get_sequence_stats(zbx_pp_queue_t *queue, zbx_vector_pp_top_stats_ptr_t *stats);

#endif
