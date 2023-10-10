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

#ifndef ZABBIX_PROXYCONFIG_WRITE_H
#define ZABBIX_PROXYCONFIG_WRITE_H

#include "zbxcomms.h"
#include "zbxjson.h"
#include "zbxvault.h"

int	zbx_proxyconfig_process(const char *addr, struct zbx_json_parse *jp, char **error);

void	zbx_recv_proxyconfig(zbx_socket_t *sock, const zbx_config_tls_t *config_tls,
		const zbx_config_vault_t *config_vault, int config_timeout, int config_trapper_timeout,
		const char *config_source_ip, const char *server);

#endif
