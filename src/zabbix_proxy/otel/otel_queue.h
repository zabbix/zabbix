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

#ifndef ZABBIX_OTEL_QUEUE_H
#define ZABBIX_OTEL_QUEUE_H

#include "zbxmw.h"

typedef enum
{
	OTEL_METRICS,
	OTEL_LOGS,
	OTEL_TRACES
}
zbx_otel_request_type_t;

typedef void * zbx_otel_request_t;

typedef struct
{
	zbx_mw_queue_t	base;
	zbx_uint64_t	usage;
	zbx_uint64_t	quota;
	time_t		window_start;
}
zbx_otel_queue_t;

zbx_otel_queue_t	*otel_queue_create(int quota);

int	otel_queue_push_request(zbx_otel_queue_t *queue, zbx_otel_request_t request, zbx_otel_request_type_t type);

#endif
