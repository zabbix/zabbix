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

#ifndef ZABBIX_DIAG_PROXY_H
#define ZABBIX_DIAG_PROXY_H

#include "zbxjson.h"

int	diag_add_section_info_proxy(const char *section, const struct zbx_json_parse *jp, struct zbx_json *json,
		char **error);

#endif /* ZABBIX_DIAG_PROXY_H */
