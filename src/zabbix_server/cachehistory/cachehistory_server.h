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

#ifndef ZABBIX_CACHEHISTORY_SERVER_H
#define ZABBIX_CACHEHISTORY_SERVER_H

#include "zbxdbhigh.h"
#include "zbxipcservice.h"
#include "zbxcacheconfig.h"
#include "zbxalgo.h"

void	zbx_sync_server_history(int *values_num, int *triggers_num, const zbx_events_funcs_t *events_cbs,
		zbx_ipc_async_socket_t *rtc, int config_history_storage_pipelines, int *more);

int	zbx_hc_check_proxy(zbx_uint64_t proxyid);

void	zbx_evaluate_expressions(zbx_vector_dc_trigger_t *triggers, const zbx_vector_uint64_t *history_itemids,
		const zbx_history_sync_item_t *history_items, const int *history_errcodes);

#endif
