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

#ifndef ZABBIX_PROXYCONFIG_READ_H
#define ZABBIX_PROXYCONFIG_READ_H

#include "zbxcacheconfig.h"

#include "zbxcomms.h"
#include "zbxvault.h"

typedef enum {
	ZBX_PROXYCONFIG_STATUS_EMPTY,
	ZBX_PROXYCONFIG_STATUS_DATA
}
zbx_proxyconfig_status_t;

int	zbx_proxyconfig_get_data(zbx_dc_proxy_t *proxy, const struct zbx_json_parse *jp_request, struct zbx_json *j,
		zbx_proxyconfig_status_t *status, const zbx_config_vault_t *config_vault, const char *config_source_ip,
		const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, char **error);

void	zbx_send_proxyconfig(zbx_socket_t *sock, const struct zbx_json_parse *jp,
		const zbx_config_vault_t *config_vault, int config_timeout, int config_trapper_timeout,
		const char *config_source_ip, const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location);

#endif
