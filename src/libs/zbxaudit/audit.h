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

#ifndef ZABBIX_AUDIT_H
#define ZABBIX_AUDIT_H

#include "audit/zbxaudit.h"

#define	AUDIT_DETAILS_KEY_LEN		100

#define ZBX_AUDIT_RESOURCE_TRIGGER_PROTOTYPE		31
#define ZBX_AUDIT_RESOURCE_GRAPH_PROTOTYPE		35
#define ZBX_AUDIT_RESOURCE_ITEM_PROTOTYPE		36
#define ZBX_AUDIT_RESOURCE_HOST_PROTOTYPE		37
#define ZBX_AUDIT_RESOURCE_SETTINGS			40
#define ZBX_AUDIT_RESOURCE_HA_NODE			47
#define ZBX_AUDIT_RESOURCE_HISTORY			53

zbx_audit_entry_t	*zbx_audit_entry_init_cuid(const char *cuid, const int id_table,const char *name,
		int audit_action, int resource_type);

void	zbx_audit_update_json_append_no_value(const zbx_uint64_t id, const int id_table, const char *audit_op,
		const char *key);
void	zbx_audit_update_json_append_int(const zbx_uint64_t id, const int id_table, const char *audit_op,
		const char *key, int value, const char *table, const char *field);
void	zbx_audit_update_json_append_double(const zbx_uint64_t id, const int id_table, const char *audit_op,
		const char *key, double value, const char *table, const char *field);
void	zbx_audit_update_json_update_string(const zbx_uint64_t id, const int id_table, const char *key,
		const char *value_old, const char *value_new);
void	zbx_audit_update_json_update_uint64(const zbx_uint64_t id, const int id_table, const char *key,
		uint64_t value_old, uint64_t value_new);
void	zbx_audit_update_json_update_int(const zbx_uint64_t id, const int id_table, const char *key, int value_old,
		int value_new);
void	zbx_audit_update_json_update_double(const zbx_uint64_t id, const int id_table, const char *key,
		double value_old, double value_new);
void	zbx_audit_update_json_delete(const zbx_uint64_t id, const int id_table, const char *audit_op, const char *key);

int	audit_field_value_matches_db_default(const char *table_name, const char *field_name, const char *value,
		uint64_t id);

#endif	/* ZABBIX_AUDIT_H */
