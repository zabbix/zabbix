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

void	zbx_discoverer_job_net_check_task_free(zbx_discoverer_net_check_task_t *task)
{
	if (NULL != task->ips)
	{
		zbx_vector_str_clear_ext(task->ips, zbx_str_free);
		zbx_vector_str_destroy(task->ips);
		zbx_free(task->ips);
	}

	zbx_vector_discoverer_net_check_clear_ext(&task->dchecks, zbx_discovery_dcheck_free);
	zbx_vector_discoverer_net_check_destroy(&task->dchecks);

	zbx_free(task->ip);
	zbx_free(task);
}

void	zbx_discoverer_job_net_check_free(zbx_discoverer_drule_job_t *job)
{
	zbx_discoverer_net_check_task_t	*task;

	while (SUCCEED == zbx_list_pop(&job->tasks, (void*)&task))
		zbx_discoverer_job_net_check_task_free(task);
}

void	zbx_discoverer_job_net_check_destroy(zbx_discoverer_drule_job_t *job)
{
	zbx_discoverer_job_net_check_free(job);

	zbx_free(job);
}
