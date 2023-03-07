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

zbx_hash_t	zbx_discoverer_task_hash(const void *data)
{
	const zbx_discoverer_task_t	*task = (const zbx_discoverer_task_t *)data;
	zbx_hash_t			hash;
	zbx_uint64_t			port = task->port;

	hash = ZBX_DEFAULT_UINT64_HASH_FUNC(&port);
	hash = ZBX_DEFAULT_STRING_HASH_ALGO(task->ip, strlen(task->ip), hash);

	return hash;
}

int	zbx_discoverer_task_compare(const void *d1, const void *d2)
{
	const zbx_discoverer_task_t	*task1 = (const zbx_discoverer_task_t *)d1;
	const zbx_discoverer_task_t	*task2 = (const zbx_discoverer_task_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(task1->port, task2->port);

	return strcmp(task1->ip, task2->ip);
}

void	zbx_discoverer_task_free(zbx_discoverer_task_t *task)
{
	if (NULL != task->ips)
	{
		zbx_vector_str_clear_ext(task->ips, zbx_str_free);
		zbx_vector_str_destroy(task->ips);
		zbx_free(task->ips);
	}

	zbx_vector_discoverer_dcheck_clear_ext(&task->dchecks, zbx_discovery_dcheck_free);
	zbx_vector_discoverer_dcheck_destroy(&task->dchecks);

	zbx_free(task->ip);
	zbx_free(task);
}

void	zbx_discoverer_job_tasks_free(zbx_discoverer_job_t *job)
{
	zbx_discoverer_task_t	*task;

	while (SUCCEED == zbx_list_pop(&job->tasks, (void*)&task))
		zbx_discoverer_task_free(task);
}

void	zbx_discoverer_job_free(zbx_discoverer_job_t *job)
{
	zbx_discoverer_job_tasks_free(job);

	zbx_free(job);
}
