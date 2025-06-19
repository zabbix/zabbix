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

#include "audit/zbxaudit.h"

#define ZBX_AUDIT_HA_NODEID				"ha_nodeid"
#define ZBX_AUDIT_HA_NAME				"name"
#define ZBX_AUDIT_HA_STATUS				"status"
#define ZBX_AUDIT_HA_STATUS_CHANGE_REASON_TO_ACTIVE	"status_change_reason"
#define ZBX_AUDIT_HA_ADDRESS				"address"
#define ZBX_AUDIT_HA_PORT				"port"

typedef enum
{
	ZBX_AUDIT_HA_ST_CH_REASON_UNKNOWN = 0,
	ZBX_AUDIT_HA_ST_CH_REASON_NO_ACTIVE_NODES,
	ZBX_AUDIT_HA_ST_CH_REASON_DB_CONNECTION_LOSS
}
zbx_audit_ha_status_change_reason_to_active_type_t;

zbx_audit_entry_t	*zbx_audit_ha_create_entry(int audit_action, const char *nodeid, const char *name);
void	zbx_audit_ha_add_create_fields(zbx_audit_entry_t *audit_entry, const char *nodeid, const char *name,
		int status);

#endif
