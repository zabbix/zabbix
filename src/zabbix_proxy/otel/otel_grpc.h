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

#ifndef ZABBIX_OTEL_GRPC_H
#define ZABBIX_OTEL_GRPC_H

#ifdef __cplusplus
extern "C" {
#endif

#include "otel_queue.h"
#include "otel_config.h"

typedef void * zbx_grpc_handle_t;

zbx_grpc_handle_t	zbx_grpc_start(const char *address, const char *port, zbx_otel_queue_t *queue,
		const zbx_otel_config_tls_t *tls, char **error);
void	zbx_grpc_stop(zbx_grpc_handle_t handle);

int	zbx_otel_decode_request(zbx_otel_request_t request, zbx_otel_request_type_t type, char **output,
		char **error);
void	zbx_otel_request_free(zbx_otel_request_t request, zbx_otel_request_type_t type);

#ifdef __cplusplus
}
#endif


#endif


