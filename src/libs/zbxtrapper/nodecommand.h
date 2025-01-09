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

#ifndef ZABBIX_NODECOMMAND_H
#define ZABBIX_NODECOMMAND_H

#include "zbxcomms.h"
#include "zbxjson.h"

int	node_process_command(zbx_socket_t *sock, const char *data, const struct zbx_json_parse *jp,
		int config_timeout, int config_trapper_timeout, const char *config_source_ip,
		const char *config_ssh_key_location, zbx_get_config_forks_f get_config_forks,
		int config_enable_global_scripts, unsigned char program_type);

#endif
