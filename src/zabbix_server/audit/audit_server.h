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

#ifndef ZABBIX_AUDIT_SERVER_H
#define ZABBIX_AUDIT_SERVER_H

#include "zbxtypes.h"

void	zbx_audit_proxy_config_reload(int audit_context_mode, zbx_uint64_t proxyid, const char *name);

void	zbx_audit_settings_create_entry(int audit_context_mode, int audit_action, zbx_uint64_t configid);
void	zbx_audit_settings_update_field_int(int audit_context_mode, zbx_uint64_t configid, const char *key,
		int old_value, int new_value);

#endif
