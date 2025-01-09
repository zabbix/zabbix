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

#ifndef ZABBIX_DISCOVERER_QUEUE_H
#define ZABBIX_DISCOVERER_QUEUE_H

#include "discoverer_job.h"

#include "zbxalgo.h"
#include "zbxdiscovery.h"

typedef struct
{
	int					workers_num;
	zbx_list_t				jobs;
	zbx_uint64_t				pending_checks_count;
	int					snmpv3_allowed_workers;
	int					checks_per_worker_max;
	pthread_mutex_t				lock;
	pthread_cond_t				event;
	int					flags;
	zbx_vector_uint64_t			del_jobs;
	zbx_vector_discoverer_drule_error_t	errors;
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
int	discoverer_queue_init(zbx_discoverer_queue_t *queue, int snmpv3_allowed_workers, int checks_per_worker_max,
		char **error);
void	discoverer_queue_clear_jobs(zbx_list_t *jobs);
void	discoverer_queue_push(zbx_discoverer_queue_t *queue, zbx_discoverer_job_t *job);
void	discoverer_queue_append_error(zbx_vector_discoverer_drule_error_t *errors, zbx_uint64_t druleid,
			const char *error);

zbx_discoverer_job_t	*discoverer_queue_pop(zbx_discoverer_queue_t *queue);
#endif
