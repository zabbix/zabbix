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

#ifndef ZABBIX_APM_GRPC_H
#define ZABBIX_APM_GRPC_H

#ifdef __cplusplus
extern "C" {
#endif

#include "apm_queue.h"
#include "apm_config.h"

typedef void * zbx_grpc_handle_t;

zbx_grpc_handle_t	zbx_grpc_start(const char *address, const char *port, zbx_apm_queue_t *queue,
		const zbx_apm_config_tls_t *tls, char **error);
void	zbx_grpc_stop(zbx_grpc_handle_t handle);
void	zbx_grpc_shutdown(void);

void	zbx_apm_request_free(zbx_apm_request_t request, zbx_apm_request_type_t type);

#ifdef __cplusplus
}
#endif


#endif


