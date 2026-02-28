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

#ifndef ZABBIX_CEP_WORKER_H
#define ZABBIX_CEP_WORKER_H

#include "cep_queue.h"

#include "zbxcommon.h"
#include "zbxexport.h"
#include "zbxipcservice.h"
#include "zbxtypes.h"
#include "zbxtimekeeper.h"
#include "zbxtypes_ext.h"

#define ZBX_CEP_WORKER_STATE_FREE	0x00
#define ZBX_CEP_WORKER_STATE_STARTING	0x01
#define ZBX_CEP_WORKER_STATE_RUNNING	0x02
#define ZBX_CEP_WORKER_STATE_STOPPING	0x04
#define ZBX_CEP_WORKER_STATE_STOPPED	0x08

typedef struct
{
	/* worker id (index) */
	int				id;

	pthread_t			thread;

	/* worker state, see ZBX_CEP_WORKER_STATE_ defines */
	zbx_atomic_uint32_t		state;

	zbx_timekeeper_t		*timekeeper;

	zbx_log_component_t		logger;

	zbx_cep_queue_t			*queue;

	zbx_dbconn_pool_t		*dbpool;

	/* manager service, only for alerting with zbx_ipc_service_alert() */
	zbx_ipc_service_t		*service;

	zbx_ipc_async_socket_t		rtc;

	zbx_export_file_t		*problem_export;
}
zbx_cep_worker_t;

void	cep_worker_init(zbx_cep_worker_t *worker, int id, zbx_timekeeper_t *timekeeper, zbx_cep_queue_t *queue,
		zbx_dbconn_pool_t *dbpool, zbx_ipc_service_t *service);
int	cep_worker_start(zbx_cep_worker_t *worker, char **error);
void	cep_worker_stop(zbx_cep_worker_t *worker);
void	cep_worker_join(zbx_cep_worker_t *worker);
void	cep_worker_destroy(zbx_cep_worker_t *worker);

#endif /* ZABBIX_CEP_WORKER_H */
