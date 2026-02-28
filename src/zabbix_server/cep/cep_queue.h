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

#ifndef ZABBIX_CEP_QUEUE_H
#define ZABBIX_CEP_QUEUE_H

#include "cep_task.h"

typedef struct zbx_cep_queue zbx_cep_queue_t;

zbx_cep_queue_t	*cep_queue_create(char **error);
void	cep_queue_destroy(zbx_cep_queue_t *queue);

void	cep_queue_lock(zbx_cep_queue_t *queue);
void	cep_queue_unlock(zbx_cep_queue_t *queue);

void	cep_queue_push(zbx_cep_queue_t *queue, zbx_cep_task_t *task);
void	cep_queue_push_batch(zbx_cep_queue_t *queue, zbx_vector_cep_task_ptr_t *tasks);
void	cep_queue_push_finished_nl(zbx_cep_queue_t *queue, zbx_cep_task_t *task);
void	cep_queue_push_finished_direct(zbx_cep_queue_t *queue, zbx_cep_task_t *task);
zbx_cep_task_t	*cep_queue_pop_nl(zbx_cep_queue_t *queue);

int	cep_queue_wait(zbx_cep_queue_t *queue, char **error);
void	cep_queue_notify(zbx_cep_queue_t *queue);
void	cep_queue_notify_all(zbx_cep_queue_t *queue);
int	cep_queue_pop_finished(zbx_cep_queue_t *queue, zbx_vector_cep_task_ptr_t *tasks);

#endif

