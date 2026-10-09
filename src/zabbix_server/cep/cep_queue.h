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

#include "zbxmw.h"

typedef struct zbx_cep_queue zbx_cep_queue_t;

zbx_cep_queue_t	*cep_queue_create(void);
void	cep_queue_clear(zbx_cep_queue_t *queue);

void	cep_queue_lock(zbx_cep_queue_t *queue);
void	cep_queue_unlock(zbx_cep_queue_t *queue);

void	cep_queue_push(zbx_cep_queue_t *queue, zbx_mw_task_t *task);
void	cep_queue_push_batch(zbx_cep_queue_t *queue, zbx_vector_mw_task_ptr_t *tasks);
void	cep_queue_push_completed(zbx_cep_queue_t *queue, zbx_mw_task_t *task);

int	cep_queue_pending_commits_num(zbx_cep_queue_t *queue);
int	cep_queue_is_empty(zbx_cep_queue_t *queue);

#endif

