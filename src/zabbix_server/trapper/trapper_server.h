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

#ifndef ZABBIX_TRAPPER_SERVER_H
#define ZABBIX_TRAPPER_SERVER_H

#include "zbxcacheconfig.h"
#include "zbxvault.h"
#include "zbxdbhigh.h"
#include "zbxcomms.h"
#include "zbxtime.h"
#include "zbxjson.h"

int	zbx_send_proxy_data_response(const zbx_dc_proxy_t *proxy, zbx_socket_t *sock, const char *info, int status,
		int upload_status, int config_timeout);

int	zbx_trapper_process_request_server(const char *request, zbx_socket_t *sock, const struct zbx_json_parse *jp,
		const zbx_timespec_t *ts, const zbx_config_comms_args_t *config_comms,
		const zbx_config_vault_t *config_vault, int proxydata_frequency,
		zbx_get_program_type_f get_program_type_cb, const zbx_events_funcs_t *events_cbs,
		zbx_get_config_forks_f get_config_forks);

#endif
