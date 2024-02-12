/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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
#include "zbxip.h"

ZBX_VECTOR_DECL(iprange, zbx_iprange_t)

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
	zbx_vector_dc_dcheck_ptr_t	*dchecks_common;
	zbx_vector_iprange_t		*ipranges;
}
zbx_discoverer_job_t;

ZBX_PTR_VECTOR_DECL(discoverer_jobs_ptr, zbx_discoverer_job_t*)

typedef struct
{
	struct
	{
		int		ipaddress[8];
		int		index_ip;
		int		port;
		int		index_port;
		int		index_dcheck;
		zbx_uint64_t	count;		/* total count of checks in range */
		unsigned int	checks_per_ip;	/* count of checks per ip */
	}
	state;
	zbx_vector_iprange_t	*ipranges;
	zbx_uint64_t		id;
}
zbx_task_range_t;

typedef struct
{
	zbx_vector_dc_dcheck_ptr_t	dchecks;
	zbx_task_range_t		range;
	zbx_uint64_t			unique_dcheckid;
}
zbx_discoverer_task_t;

ZBX_VECTOR_DECL(portrange, zbx_range_t)

zbx_hash_t		discoverer_task_hash(const void *data);
int			discoverer_task_compare(const void *d1, const void *d2);
void			discoverer_task_clear(zbx_discoverer_task_t *task);
void			discoverer_task_free(zbx_discoverer_task_t *task);
zbx_uint64_t		discoverer_task_check_count_get(zbx_discoverer_task_t *task);
zbx_uint64_t		discoverer_task_ip_check_count_get(zbx_discoverer_task_t *task);
zbx_uint64_t		discoverer_job_tasks_free(zbx_discoverer_job_t *job);
void			discoverer_job_free(zbx_discoverer_job_t *job);
zbx_discoverer_job_t	*discoverer_job_create(zbx_dc_drule_t *drule, zbx_vector_dc_dcheck_ptr_t *dchecks_common,
					zbx_vector_iprange_t *ipranges);
void			discoverer_job_abort(zbx_discoverer_job_t *job, zbx_uint64_t *pending_checks_count,
					zbx_vector_discoverer_drule_error_t *errors, char *error);
zbx_discoverer_task_t	*discoverer_task_pop(zbx_discoverer_job_t *job);
int			discoverer_range_check_iter(zbx_discoverer_task_t *task);


#endif
