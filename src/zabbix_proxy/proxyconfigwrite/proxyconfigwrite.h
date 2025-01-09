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

#ifndef ZABBIX_PROXYCONFIG_WRITE_H
#define ZABBIX_PROXYCONFIG_WRITE_H

#include "zbxcomms.h"
#include "zbxjson.h"
#include "zbxvault.h"

typedef enum {
	ZBX_PROXYCONFIG_WRITE_STATUS_EMPTY,
	ZBX_PROXYCONFIG_WRITE_STATUS_DATA
}
zbx_proxyconfig_write_status_t;

int	zbx_proxyconfig_process(const char *addr, struct zbx_json_parse *jp, zbx_proxyconfig_write_status_t *status,
		char **error);

void	zbx_recv_proxyconfig(zbx_socket_t *sock, const zbx_config_tls_t *config_tls,
		const zbx_config_vault_t *config_vault, int config_timeout, int config_trapper_timeout,
		const char *config_source_ip, const char *config_ssl_ca_location, const char *config_ssl_cert_location,
		const char *config_ssl_key_location, const char *server);

#endif
