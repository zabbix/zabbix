/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

#ifndef ZABBIX_AGENT_GET_H
#define ZABBIX_AGENT_GET_H

#include "zbxjson.h"
#include "module.h"

int	zbx_get_agent_protocol_version_int(const char *version_str);
void	zbx_agent_prepare_request(struct zbx_json *j, const char *key, int timeout);
int	zbx_agent_handle_response(char *buffer, size_t read_bytes, ssize_t received_len, const char *addr,
		AGENT_RESULT *result, int *version);

#endif
