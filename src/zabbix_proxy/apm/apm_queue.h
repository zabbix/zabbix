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

#ifndef ZABBIX_APM_QUEUE_H
#define ZABBIX_APM_QUEUE_H

#include "zbxmw.h"
#include "zbxtypes.h"

typedef enum
{
	APM_METRICS,
	APM_LOGS,
	APM_TRACES
}
zbx_apm_request_type_t;

typedef void * zbx_apm_request_t;

typedef struct
{
	zbx_mw_queue_t	base;
	zbx_uint64_t	usage;
	zbx_uint64_t	quota;
	zbx_uint64_t	accepted_num;
	zbx_uint64_t	dropped_num;
	time_t		window_start;
}
zbx_apm_queue_t;

zbx_apm_queue_t	*apm_queue_create(zbx_uint64_t quota);
void	apm_queue_set_quota(zbx_apm_queue_t *queue, zbx_uint64_t quota);

int	apm_queue_push_request(zbx_apm_queue_t *queue, zbx_apm_request_t request, zbx_apm_request_type_t type);
void	apm_queue_get_stats(zbx_apm_queue_t *queue, zbx_uint64_t *accepted_num, zbx_uint64_t *dropped_num);

#endif
