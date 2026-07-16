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

#include "otel_task.h"
#include "otel_grpc.h"

static void	otel_task_message_free(void *task);

zbx_mw_task_t	*otel_task_request_create(zbx_otel_request_t request, zbx_otel_request_type_t type)
{
	zbx_otel_task_request_t	*task;

	task = (zbx_otel_task_request_t *)zbx_mw_task_create(OTEL_TASK_REQUEST, otel_task_message_free,
			sizeof(zbx_otel_task_request_t));

	task->request = request;
	task->type = type;
	task->data = NULL;

	return (zbx_mw_task_t *)task;
}

static void	otel_task_message_free(void *task)
{
	zbx_otel_task_request_t	*otel_task = (zbx_otel_task_request_t *)task;

	zbx_otel_request_free(otel_task->request, otel_task->type);
	zbx_free(otel_task->data);
	zbx_free(otel_task);
}

/******************************************************************************
 *                                                                            *
 * Purpose: free a task                                                       *
 *                                                                            *
 ******************************************************************************/
void	otel_task_free(zbx_mw_task_t *mw_task)
{
	zbx_mw_task_t	*task = (zbx_mw_task_t *)mw_task;

	switch (task->type)
	{
		case OTEL_TASK_REQUEST:
			otel_task_message_free((zbx_otel_task_request_t *)task);
			break;
	}
}

