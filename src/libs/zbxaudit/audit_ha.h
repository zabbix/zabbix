/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#ifndef ZABBIX_AUDIT_HA_H
#define ZABBIX_AUDIT_HA_H

#define ZBX_AUDIT_HA_NODE	"hanode"
#define ZBX_AUDIT_HA_NODEID	ZBX_AUDIT_HA_NODE ".ha_nodeid"
#define ZBX_AUDIT_HA_NAME	ZBX_AUDIT_HA_NODE ".name"
#define ZBX_AUDIT_HA_STATUS	ZBX_AUDIT_HA_NODE ".status"
#define ZBX_AUDIT_HA_ADDRESS	ZBX_AUDIT_HA_NODE ".address"
#define ZBX_AUDIT_HA_PORT	ZBX_AUDIT_HA_NODE ".port"

void	zbx_audit_ha_create_entry(int audit_action, const char *nodeid, const char *name);
void	zbx_audit_ha_add_create_fields(const char *nodeid, const char *name, int status);
void	zbx_audit_ha_update_field_string(const char *nodeid, const char *key, const char *old_value,
		const char *new_value);
void	zbx_audit_ha_update_field_int(const char *nodeid, const char *key, int old_value, int new_value);

#endif
