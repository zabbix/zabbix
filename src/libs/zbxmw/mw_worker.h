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

#ifndef ZABBIX_MW_WORKER_H
#define ZABBIX_MW_WORKER_H

#include "zbxmw.h"

#include "zbxipcservice.h"
#include "zbxtimekeeper.h"

typedef struct
{
	unsigned char		process_type;
	zbx_mw_worker_t		*worker;
	void			*(*worker_entry)(void *);
}
zbx_mw_worker_args_t;

#define MW_WORKER_STATE_FREE		0x00
#define MW_WORKER_STATE_STARTING	0x01
#define MW_WORKER_STATE_RUNNING		0x02
#define MW_WORKER_STATE_STOPPING	0x04
#define MW_WORKER_STATE_STOPPED		0x08

void	mw_worker_init(zbx_mw_worker_t *worker, int id, zbx_ipc_service_t *service, zbx_mw_queue_t *queue,
		zbx_timekeeper_t *timekeeper);
void	mw_worker_clear(zbx_mw_worker_t *worker);

int	mw_worker_start(zbx_mw_worker_t *worker, unsigned char process_type, void *(*worker_entry)(void *),
		char **error);

void	mw_worker_join(zbx_mw_worker_t *worker);

#endif
