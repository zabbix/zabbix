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

#ifndef ZABBIX_AUDIT_HA_H
#define ZABBIX_AUDIT_HA_H

#define ZBX_AUDIT_HA_NODE				"hanode"
#define ZBX_AUDIT_HA_NODEID				ZBX_AUDIT_HA_NODE ".ha_nodeid"
#define ZBX_AUDIT_HA_NAME				ZBX_AUDIT_HA_NODE ".name"
#define ZBX_AUDIT_HA_STATUS				ZBX_AUDIT_HA_NODE ".status"
#define ZBX_AUDIT_HA_STATUS_CHANGE_REASON_TO_ACTIVE	ZBX_AUDIT_HA_NODE ".status_change_reason"
#define ZBX_AUDIT_HA_ADDRESS				ZBX_AUDIT_HA_NODE ".address"
#define ZBX_AUDIT_HA_PORT				ZBX_AUDIT_HA_NODE ".port"

typedef enum
{
	ZBX_AUDIT_HA_ST_CH_REASON_UNKNOWN = 0,
	ZBX_AUDIT_HA_ST_CH_REASON_NO_ACTIVE_NODES,
	ZBX_AUDIT_HA_ST_CH_REASON_DB_CONNECTION_LOSS
}
zbx_audit_ha_status_change_reason_to_active_type_t;

void	zbx_audit_ha_create_entry(int audit_action, const char *nodeid, const char *name);
void	zbx_audit_ha_add_create_fields(const char *nodeid, const char *name, int status);
void	zbx_audit_ha_update_field_string(const char *nodeid, const char *key, const char *old_value,
		const char *new_value);
void	zbx_audit_ha_update_field_int(const char *nodeid, const char *key, int old_value, int new_value);
void	zbx_audit_ha_add_field_int(const char *nodeid, const char *key, int value);

#endif
