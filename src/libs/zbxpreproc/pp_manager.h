/*
** Copyright (C) 2001-2025 Zabbix SIA
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

#ifndef ZABBIX_PP_MANAGER_H
#define ZABBIX_PP_MANAGER_H

#include "pp_worker.h"
#include "pp_queue.h"
#include "zbxalgo.h"
#include "zbxtimekeeper.h"
#include "zbxcacheconfig.h"
#include "zbxipcservice.h"

struct zbx_pp_manager
{
	zbx_pp_worker_t			*workers;
	int				workers_num;
	int				program_type;

	zbx_hashset_t			items;
	zbx_uint64_t			revision;

	zbx_pp_queue_t			queue;

	zbx_timekeeper_t		*timekeeper;

	zbx_dc_um_shared_handle_t	*um_handle;
	zbx_ipc_async_socket_t		rtc;
};

zbx_get_progname_f	preproc_get_progname_cb(void);

#endif
