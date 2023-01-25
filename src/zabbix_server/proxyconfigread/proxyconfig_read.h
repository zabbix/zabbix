/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#ifndef ZABBIX_PROXYCONFIG_READ_H
#define ZABBIX_PROXYCONFIG_READ_H

#include "zbxcacheconfig.h"

typedef enum {
	ZBX_PROXYCONFIG_STATUS_EMPTY,
	ZBX_PROXYCONFIG_STATUS_DATA
}
zbx_proxyconfig_status_t;

int	zbx_proxyconfig_get_data(DC_PROXY *proxy, const struct zbx_json_parse *jp_request, struct zbx_json *j,
		zbx_proxyconfig_status_t *status, const zbx_config_vault_t *config_vault, char **error);

void	zbx_send_proxyconfig(zbx_socket_t *sock, const struct zbx_json_parse *jp,
		const zbx_config_vault_t *config_vault, int config_timeout);

#endif
