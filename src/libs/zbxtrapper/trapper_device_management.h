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

#ifndef ZABBIX_TRAPPER_DEVICE_MANAGEMENT_H
#define ZABBIX_TRAPPER_DEVICE_MANAGEMENT_H

#include "zbxcomms.h"
#include "zbxjson.h"

void	zbx_trapper_device_init(zbx_socket_t *sock, const struct zbx_json_parse *jp,
		const zbx_config_comms_args_t *config_comms, const char *config_frontend_allowed_ip,
		const char *config_bridge_adapter_url, const char *config_bridge_adapter_connect_to);
void	zbx_trapper_device_offboard(zbx_socket_t *sock, const struct zbx_json_parse *jp,
		const zbx_config_comms_args_t *config_comms, const char *config_frontend_allowed_ip,
		const char *config_bridge_adapter_url, const char *config_bridge_adapter_connect_to);

#endif
