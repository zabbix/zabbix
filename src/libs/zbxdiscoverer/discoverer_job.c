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

#include "discoverer_job.h"
#include "discoverer_queue.h"
#include "discoverer_int.h"
#include "zbx_discoverer_constants.h"

ZBX_VECTOR_IMPL(iprange, zbx_iprange_t)

int	discoverer_range_check_iter(zbx_discoverer_task_t *task)
{
	int			ret;
	zbx_ds_dcheck_t	*dcheck = task->ds_dchecks.values[task->range.state.index_dcheck];

	if (0 == task->range.state.count)
		return FAIL;

	ret = zbx_portrange_uniq_iter(dcheck->portranges.values, dcheck->portranges.values_num,
			&task->range.state.index_port, &task->range.state.port);

	if (SUCCEED == ret)
	{
		task->range.state.count--;

		return 0 == task->range.state.count ? FAIL : SUCCEED;
	}

	task->range.state.port = ZBX_PORTRANGE_INIT_PORT;

	if (++(task->range.state.index_dcheck) < task->ds_dchecks.values_num)
		return discoverer_range_check_iter(task);

	task->range.state.index_dcheck = 0;

	if (SUCCEED == zbx_iprange_uniq_iter(task->range.ipranges->values,
			task->range.ipranges->values_num, &task->range.state.index_ip,
			task->range.state.ipaddress))
	{
		return discoverer_range_check_iter(task);
	}
	else
		task->range.state.count--;

	return FAIL;
}

#include "zbxdiscovery.h"

zbx_hash_t	discoverer_task_hash(const void *data)
{
	const zbx_discoverer_task_t	*task = (const zbx_discoverer_task_t *)data;
	zbx_hash_t			hash;

	hash = ZBX_DEFAULT_UINT64_HASH_FUNC(&task->range.id);
	hash = ZBX_DEFAULT_STRING_HASH_ALGO(&task->ds_dchecks.values[0]->dcheck.type,
			sizeof(task->ds_dchecks.values[0]->dcheck.type), hash);

	return hash;
}

int	discoverer_task_compare(const void *d1, const void *d2)
{
	const zbx_discoverer_task_t	*task1 = (const zbx_discoverer_task_t *)d1;
	const zbx_discoverer_task_t	*task2 = (const zbx_discoverer_task_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(task1->range.id, task2->range.id);
	ZBX_RETURN_IF_NOT_EQUAL(GET_DTYPE(task1), GET_DTYPE(task2));

	return 0;
}

void	discoverer_task_clear(zbx_discoverer_task_t *task)
{
	/* ds_dcheck is stored in job->dcheck_common */
	zbx_vector_ds_dcheck_ptr_destroy(&task->ds_dchecks);
}

void	discoverer_task_free(zbx_discoverer_task_t *task)
{
	discoverer_task_clear(task);
	zbx_free(task);
}

zbx_uint64_t	discoverer_task_check_count_get(zbx_discoverer_task_t *task)
{
	return task->range.state.count;
}

static zbx_discoverer_task_t	*discoverer_task_clone(zbx_discoverer_task_t *task)
{
	zbx_discoverer_task_t	*task_copy;

	task_copy = (zbx_discoverer_task_t*)zbx_malloc(NULL, sizeof(zbx_discoverer_task_t));
	zbx_vector_ds_dcheck_ptr_create(&task_copy->ds_dchecks);
	zbx_vector_ds_dcheck_ptr_append_array(&task_copy->ds_dchecks, task->ds_dchecks.values,
			task->ds_dchecks.values_num);
	task_copy->unique_dcheckid = task->unique_dcheckid;
	task_copy->range = task->range;
	task_copy->range.id++;

	return task_copy;
}

static int	discoverer_task_icmp_shift(zbx_discoverer_task_t *task, int checks_per_worker_max)
{
	if (task->range.state.count <= (zbx_uint64_t)checks_per_worker_max)
		return FAIL;

	do
	{
		int	ret;

		do
		{
			checks_per_worker_max--;
			task->range.state.count--;
		}
		while (SUCCEED == (ret = zbx_iprange_uniq_iter(task->range.ipranges->values,
				task->range.ipranges->values_num,
				&task->range.state.index_ip, task->range.state.ipaddress)) &&
				0 != checks_per_worker_max);

		if (FAIL == ret)
		{
			task->range.state.index_dcheck++;
			task->range.state.index_ip = 0;
			zbx_iprange_first(task->range.ipranges->values, task->range.state.ipaddress);
		}
	}
	while (task->range.state.index_dcheck < task->ds_dchecks.values_num && 0 != checks_per_worker_max);

	return SUCCEED;
}

static int	discoverer_task_async_shift(zbx_discoverer_task_t *task, int checks_per_worker_max)
{
	if (task->range.state.count <= (zbx_uint64_t)checks_per_worker_max)
		return FAIL;

	do
	{
		(void)discoverer_range_check_iter(task);
	}
	while (0 != --checks_per_worker_max);

	return SUCCEED;
}

static zbx_discoverer_task_t	*discoverer_task_split_get(zbx_discoverer_task_t *task, zbx_discoverer_job_t *job,
		int checks_per_task_max)
{
	zbx_task_range_t	range;
	int			ret;

	if (0 != job->concurrency_max || SVC_SNMPv3 == GET_DTYPE(task))
		return task;

	range.state = task->range.state;

	if (SVC_ICMPPING == GET_DTYPE(task))
		ret = discoverer_task_icmp_shift(task, checks_per_task_max);
	else
		ret = discoverer_task_async_shift(task, checks_per_task_max);

	if (SUCCEED == ret)
	{
		(void)zbx_list_append(&job->tasks, discoverer_task_clone(task), NULL);
		range.state.count = (zbx_uint64_t)checks_per_task_max;
	}

	task->range.state = range.state;

	return task;
}

zbx_discoverer_task_t	*discoverer_task_pop(zbx_discoverer_job_t *job, int checks_per_task_max)
{
	zbx_discoverer_task_t	*task;
	zbx_task_range_t	range;

	if (SUCCEED != zbx_list_pop(&job->tasks, (void*)&task))
		return NULL;

	if (SUCCEED == dcheck_is_async(task->ds_dchecks.values[0]))
		return discoverer_task_split_get(task, job, checks_per_task_max);

	range.state = task->range.state;

	if (SUCCEED == discoverer_range_check_iter(task))
		(void)zbx_list_append(&job->tasks, discoverer_task_clone(task), NULL);

	task->range.state = range.state;

	return task;
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

	zbx_vector_ds_dcheck_ptr_clear_ext(job->ds_dchecks_common, discoverer_ds_dcheck_free);
	zbx_vector_ds_dcheck_ptr_destroy(job->ds_dchecks_common);
	zbx_free(job->ds_dchecks_common);
	zbx_vector_iprange_destroy(job->ipranges);
	zbx_free(job->ipranges);
	zbx_free(job);
}

zbx_discoverer_job_t	*discoverer_job_create(zbx_dc_drule_t *drule, zbx_vector_ds_dcheck_ptr_t *ds_dchecks_common,
		zbx_vector_iprange_t *ipranges)
{
	zbx_discoverer_job_t	*job;

	job = (zbx_discoverer_job_t*)zbx_malloc(NULL, sizeof(zbx_discoverer_job_t));
	job->druleid = drule->druleid;
	job->concurrency_max = drule->concurrency_max;
	job->workers_used = 0;
	job->drule_revision = drule->revision;
	job->status = DISCOVERER_JOB_STATUS_QUEUED;
	zbx_list_create(&job->tasks);
	job->ds_dchecks_common = ds_dchecks_common;
	job->ipranges = ipranges;

	return job;
}

void	discoverer_job_abort(zbx_discoverer_job_t *job, zbx_uint64_t *pending_checks_count,
		zbx_vector_discoverer_drule_error_t *errors, char *error)
{
	discoverer_queue_append_error(errors, job->druleid, error);
	*pending_checks_count -= discoverer_job_tasks_free(job);
}
