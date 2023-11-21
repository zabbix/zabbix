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

#include "discoverer_job.h"

ZBX_VECTOR_IMPL(iprange, zbx_iprange_t)

typedef union {
	struct {
		zbx_uint32_t	port;
		zbx_uint32_t	dcheck_type;
	}			key;
	zbx_uint64_t		buf;
}
dtask_hash_t;

zbx_hash_t	discoverer_task_hash(const void *data)
{
	const zbx_discoverer_task_t	*task = (const zbx_discoverer_task_t *)data;
	zbx_hash_t			hash;
	dtask_hash_t			state;

	state.key.port = task->port;
	state.key.dcheck_type = DISCOVERY_ADDR_IP == task->addr_type ? 0 : task->dchecks.values[0]->type;
	hash = ZBX_DEFAULT_UINT64_HASH_FUNC(&state.buf);

	if (DISCOVERY_ADDR_RANGE == task->addr_type)
		hash = ZBX_DEFAULT_UINT64_HASH_ALGO(&task->addr.range->id, sizeof(task->addr.range->id), hash);
	else
		hash = ZBX_DEFAULT_STRING_HASH_ALGO(task->addr.ip, strlen(task->addr.ip), hash);

	return hash;
}

int	discoverer_task_compare(const void *d1, const void *d2)
{
	const zbx_discoverer_task_t	*task1 = (const zbx_discoverer_task_t *)d1;
	const zbx_discoverer_task_t	*task2 = (const zbx_discoverer_task_t *)d2;
	dtask_hash_t			state1, state2;
	int				ret;

	ZBX_RETURN_IF_NOT_EQUAL(task1->addr_type, task2->addr_type);

	state1.key.port = task1->port;
	state2.key.port = task2->port;
	state1.key.dcheck_type = DISCOVERY_ADDR_IP == task1->addr_type ? 0 : task1->dchecks.values[0]->type;
	state2.key.dcheck_type = DISCOVERY_ADDR_IP == task2->addr_type ? 0 : task2->dchecks.values[0]->type;

	if (0 != (ret = memcmp(&state1, &state2, sizeof(dtask_hash_t))))
		return ret;

	if (DISCOVERY_ADDR_IP == task1->addr_type)
		return strcmp(task1->addr.ip, task2->addr.ip);

	ZBX_RETURN_IF_NOT_EQUAL(task1->addr.range->id, task2->addr.range->id);
	return 0;
}

void	discoverer_task_clear(zbx_discoverer_task_t *task)
{
	if (DISCOVERY_ADDR_IP == task->addr_type)
		zbx_free(task->addr.ip);
	else
		zbx_free(task->addr.range);	/* the range vector is stored on job level */

	/* dcheck is stored in job->dcheck_common */
	zbx_vector_dc_dcheck_ptr_destroy(&task->dchecks);
}

void	discoverer_task_free(zbx_discoverer_task_t *task)
{
	discoverer_task_clear(task);
	zbx_free(task);
}

zbx_uint64_t	discoverer_task_check_count_get(zbx_discoverer_task_t *task)
{
	return (zbx_uint64_t)task->dchecks.values_num;
}

zbx_uint64_t	discoverer_task_ip_check_count_get(zbx_discoverer_task_t *task)
{
	if (DISCOVERY_ADDR_RANGE == task->addr_type && 0 != task->addr.range->state.checks_per_ip)
		return (zbx_uint64_t)task->addr.range->state.checks_per_ip;	/* except ICMP */
	else
		return discoverer_task_check_count_get(task);
}

zbx_uint64_t	discoverer_job_tasks_free(zbx_discoverer_job_t *job)
{
	zbx_uint64_t		check_count = 0;
	zbx_discoverer_task_t	*task;

	while (SUCCEED == zbx_list_pop(&job->tasks, (void*)&task))
	{
		check_count += discoverer_task_check_count_get(task);
		discoverer_task_free(task);
	}

	return check_count;
}

void	discoverer_job_free(zbx_discoverer_job_t *job)
{
	(void)discoverer_job_tasks_free(job);

	zbx_vector_dc_dcheck_ptr_clear_ext(job->dchecks_common, zbx_discovery_dcheck_free);
	zbx_vector_dc_dcheck_ptr_destroy(job->dchecks_common);
	zbx_free(job->dchecks_common);
	zbx_vector_iprange_destroy(job->ipranges);
	zbx_free(job->ipranges);
	zbx_free(job);
}

zbx_discoverer_job_t	*discoverer_job_create(zbx_dc_drule_t *drule, zbx_vector_dc_dcheck_ptr_t *dchecks_common,
		zbx_vector_iprange_t *ipranges)
{
	zbx_discoverer_job_t	*job;

	job = (zbx_discoverer_job_t*)zbx_malloc(NULL, sizeof(zbx_discoverer_job_t));
	job->druleid = drule->druleid;
	job->workers_max = drule->concurrency_max;
	job->workers_used = 0;
	job->drule_revision = drule->revision;
	job->status = DISCOVERER_JOB_STATUS_QUEUED;
	zbx_list_create(&job->tasks);
	job->dchecks_common = dchecks_common;
	job->ipranges = ipranges;

	return job;
}
