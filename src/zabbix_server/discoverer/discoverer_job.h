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

#ifndef ZABBIX_DISCOVERER_JOB_H
#define ZABBIX_DISCOVERER_JOB_H

#include "zbxdiscovery.h"

typedef struct
{
	zbx_vector_dc_dcheck_ptr_t	dchecks;
	char				*ip;
	zbx_vector_str_t		*ips;
	unsigned short			port;
	zbx_uint64_t			unique_dcheckid;
	int				resolve_dns;
}
zbx_discoverer_task_t;

#define DISCOVERER_JOB_STATUS_QUEUED	0
#define DISCOVERER_JOB_STATUS_WAITING	1
#define DISCOVERER_JOB_STATUS_REMOVING	2

typedef struct
{
	zbx_uint64_t			druleid;
	zbx_list_t			tasks;
	zbx_uint64_t			drule_revision;
	int				workers_used;
	int				workers_max;
	unsigned char			status;
}
zbx_discoverer_job_t;

zbx_hash_t		discoverer_task_hash(const void *data);
int			discoverer_task_compare(const void *d1, const void *d2);
void			discoverer_task_clear(zbx_discoverer_task_t *task);
void			discoverer_task_free(zbx_discoverer_task_t *task);
zbx_uint64_t		discoverer_task_check_count_get(zbx_discoverer_task_t *task);
zbx_uint64_t		discoverer_job_tasks_free(zbx_discoverer_job_t *job);
void			discoverer_job_free(zbx_discoverer_job_t *job);
zbx_discoverer_job_t	*discoverer_job_create(zbx_dc_drule_t *drule);

#endif
