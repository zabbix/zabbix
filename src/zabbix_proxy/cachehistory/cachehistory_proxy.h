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

#ifndef ZABBIX_CACHEHISTORY_PROXY_H
#define ZABBIX_CACHEHISTORY_PROXY_H

#include "zbxdbhigh.h"
#include "zbxipcservice.h"

void	zbx_sync_proxy_history(int *values_num, int *triggers_num, const zbx_events_funcs_t *events_cbs,
		zbx_ipc_async_socket_t *rtc, int config_history_storage_pipelines, int *more);

#endif
