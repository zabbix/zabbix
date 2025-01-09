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

#ifndef ZABBIX_POLLER_PROXY_H
#define ZABBIX_POLLER_PROXY_H

#include "module.h"
#include "zbxcacheconfig.h"

int     zbx_get_value_internal_ext_proxy(const zbx_dc_item_t *item, const char *param1, const AGENT_REQUEST *request,
		AGENT_RESULT *result);

#endif
