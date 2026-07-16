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

#ifndef ZABBIX_OTEL_TASK_H
#define ZABBIX_OTEL_TASK_H

#include "otel_queue.h"
#include "zbxmw.h"

typedef enum
{
	OTEL_TASK_REQUEST
}
zbx_cep_task_type_t;

typedef struct
{
	zbx_mw_task_t		base;
	zbx_otel_request_type_t	type;
	zbx_otel_request_t	request;
	char			*data;
}
zbx_otel_task_request_t;

zbx_mw_task_t	*otel_task_request_create(zbx_otel_request_t request, zbx_otel_request_type_t type);

void	otel_task_free(zbx_mw_task_t *mw_task);

#endif
