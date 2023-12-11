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

#ifndef ZABBIX_NODECOMMAND_H
#define ZABBIX_NODECOMMAND_H

#include "zbxcomms.h"
#include "zbxjson.h"

int	node_process_command(zbx_socket_t *sock, const char *data, const struct zbx_json_parse *jp,
		int config_timeout, int config_trapper_timeout, const char *config_source_ip,
		zbx_get_config_forks_f get_config_forks, unsigned char program_type);

#endif
