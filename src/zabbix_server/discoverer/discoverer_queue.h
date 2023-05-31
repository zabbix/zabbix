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

#ifndef ZABBIX_DISCOVERER_QUEUE_H
#define ZABBIX_DISCOVERER_QUEUE_H

#include "discoverer_job.h"

#define DISCOVERER_QUEUE_MAX_SIZE	2000000

typedef struct
{
	int		workers_num;
	zbx_list_t	jobs;
	zbx_uint64_t	pending_checks_count;
	pthread_mutex_t	lock;
	pthread_cond_t	event;
	int		flags;
}
zbx_discoverer_queue_t;

void	discoverer_queue_lock(zbx_discoverer_queue_t *queue);
void	discoverer_queue_unlock(zbx_discoverer_queue_t *queue);
void	discoverer_queue_notify(zbx_discoverer_queue_t *queue);
void	discoverer_queue_notify_all(zbx_discoverer_queue_t *queue);
void	discoverer_queue_destroy(zbx_discoverer_queue_t *queue);
void	discoverer_queue_register_worker(zbx_discoverer_queue_t *queue);
void	discoverer_queue_deregister_worker(zbx_discoverer_queue_t *queue);
int	discoverer_queue_wait(zbx_discoverer_queue_t *queue, char **error);
int	discoverer_queue_init(zbx_discoverer_queue_t *queue, char **error);
void	discoverer_queue_clear_jobs(zbx_list_t *jobs);
void	discoverer_queue_push(zbx_discoverer_queue_t *queue, zbx_discoverer_job_t *job);

zbx_discoverer_job_t	*discoverer_queue_pop(zbx_discoverer_queue_t *queue);

#endif
