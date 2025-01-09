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

#ifndef ZABBIX_AGENT_GET_H
#define ZABBIX_AGENT_GET_H

#include "zbxjson.h"
#include "module.h"

int	zbx_get_agent_protocol_version_int(const char *version_str);
void	zbx_agent_prepare_request(struct zbx_json *j, const char *key, int timeout);
int	zbx_agent_handle_response(char *buffer, size_t read_bytes, ssize_t received_len, const char *addr,
		AGENT_RESULT *result, int *version);

#endif
