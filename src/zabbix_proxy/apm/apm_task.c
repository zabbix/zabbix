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

#include "apm_task.h"
#include "apm_grpc.h"
#include "apm_config.h"

static void	apm_task_request_free(void *task);
static void	apm_task_commit_free(void *task);

zbx_mw_task_t	*apm_task_request_create(zbx_apm_request_t request, zbx_apm_request_type_t type)
{
	zbx_apm_task_request_t	*task;

	task = (zbx_apm_task_request_t *)zbx_mw_task_create(APM_TASK_REQUEST, apm_task_request_free,
			sizeof(zbx_apm_task_request_t));

	task->request = request;
	task->type = type;
	task->data = NULL;

	return (zbx_mw_task_t *)task;
}

static void	apm_task_request_free(void *task)
{
	zbx_apm_task_request_t	*apm_task = (zbx_apm_task_request_t *)task;

	zbx_apm_request_free(apm_task->request, apm_task->type);
	zbx_free(apm_task->data);
	zbx_free(apm_task);
}

zbx_mw_task_t	*apm_task_commit_create(zbx_vector_mw_task_ptr_t *tasks, zbx_apm_config_attrs_t *attrs)
{
	zbx_apm_task_commit_t	*task;

	task = (zbx_apm_task_commit_t *)zbx_mw_task_create(APM_TASK_COMMIT, apm_task_commit_free,
			sizeof(zbx_apm_task_commit_t));

	zbx_vector_mw_task_ptr_create(&task->tasks);
	zbx_vector_mw_task_ptr_append_array(&task->tasks, tasks->values, tasks->values_num);
	zbx_vector_mw_task_ptr_clear(tasks);

	task->attrs = attrs;

	return (zbx_mw_task_t *)task;
}

static void	apm_task_commit_free(void *task)
{
	zbx_apm_task_commit_t	*apm_task = (zbx_apm_task_commit_t *)task;

	for (int i = 0; i < apm_task->tasks.values_num; i++)
		apm_task_free(apm_task->tasks.values[i]);

	zbx_vector_mw_task_ptr_destroy(&apm_task->tasks);

	apm_config_attrs_release(apm_task->attrs);

	zbx_free(apm_task);
}

/******************************************************************************
 *                                                                            *
 * Purpose: free a task                                                       *
 *                                                                            *
 ******************************************************************************/
void	apm_task_free(zbx_mw_task_t *mw_task)
{
	zbx_mw_task_t	*task = (zbx_mw_task_t *)mw_task;

	switch (task->type)
	{
		case APM_TASK_REQUEST:
			apm_task_request_free(task);
			break;
		case APM_TASK_COMMIT:
			apm_task_commit_free(task);
			break;
	}
}

