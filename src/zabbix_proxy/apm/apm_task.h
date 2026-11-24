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

#ifndef ZABBIX_APM_TASK_H
#define ZABBIX_APM_TASK_H

#include "apm_queue.h"
#include "apm_config.h"
#include "zbxmw.h"

typedef enum
{
	APM_TASK_REQUEST,
	APM_TASK_COMMIT
}
zbx_cep_task_type_t;

typedef struct
{
	zbx_mw_task_t		base;
	zbx_apm_request_type_t	type;
	zbx_apm_request_t	request;
	char			*data;
}
zbx_apm_task_request_t;

typedef struct
{
	zbx_mw_task_t			base;
	zbx_vector_mw_task_ptr_t	tasks;
	zbx_apm_config_attrs_t		*attrs;
}
zbx_apm_task_commit_t;

zbx_mw_task_t	*apm_task_request_create(zbx_apm_request_t request, zbx_apm_request_type_t type);
zbx_mw_task_t	*apm_task_commit_create(zbx_vector_mw_task_ptr_t *tasks, zbx_apm_config_attrs_t *attrs);

void	apm_task_free(zbx_mw_task_t *mw_task);

#endif
