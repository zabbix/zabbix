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

#ifndef ZABBIX_AUDIT_H
#define ZABBIX_AUDIT_H

#include "zbxalgo.h"
#include "zbxjson.h"

#define AUDIT_ACTION_ADD		0
#define AUDIT_ACTION_UPDATE		1
#define AUDIT_ACTION_DELETE		2
#define AUDIT_ACTION_EXECUTE		7
#define AUDIT_ACTION_CONFIG_REFRESH	11

#define AUDIT_DETAILS_ACTION_ADD	"add"
#define AUDIT_DETAILS_ACTION_UPDATE	"update"
#define AUDIT_DETAILS_ACTION_DELETE	"delete"

#define	AUDIT_DETAILS_KEY_LEN		100

#define AUDIT_RESOURCE_HOST			4
#define	AUDIT_RESOURCE_GRAPH			6
#define AUDIT_RESOURCE_TRIGGER			13
#define AUDIT_RESOURCE_HOST_GROUP		14
#define AUDIT_RESOURCE_ITEM			15
#define AUDIT_RESOURCE_SCENARIO			22
#define AUDIT_RESOURCE_DISCOVERY_RULE		23
#define AUDIT_RESOURCE_SCRIPT			25
#define AUDIT_RESOURCE_PROXY			26

#define AUDIT_RESOURCE_TRIGGER_PROTOTYPE	31
#define AUDIT_RESOURCE_GRAPH_PROTOTYPE		35
#define AUDIT_RESOURCE_ITEM_PROTOTYPE		36
#define AUDIT_RESOURCE_HOST_PROTOTYPE		37
#define AUDIT_RESOURCE_SETTINGS			40
#define AUDIT_RESOURCE_HA_NODE			47

#define AUDIT_HOST_ID		1
#define AUDIT_HOSTGRP_ID	2
#define AUDIT_ITEM_ID		3
#define AUDIT_TRIGGER_ID	4
#define AUDIT_GRAPH_ID		5
#define AUDIT_HTTPTEST_ID	6
#define AUDIT_HA_NODE_ID	7
#define AUDIT_CONFIG_ID		8

int		zbx_get_audit_mode(void);
zbx_hashset_t	*zbx_get_audit_hashset(void);

#define RETURN_IF_AUDIT_OFF()					\
	if (ZBX_AUDITLOG_ENABLED != zbx_get_audit_mode())	\
		return						\

typedef struct zbx_audit_entry
{
	zbx_uint64_t	id;
	char		*cuid;
	int		id_table;
	char		*name;
	struct zbx_json	details_json;
	int		audit_action;
	int		resource_type;
	char		audit_cuid[CUID_LEN];
}
zbx_audit_entry_t;

zbx_audit_entry_t	*zbx_audit_entry_init(zbx_uint64_t id, const int id_table, const char *name, int audit_action,
		int resource_type);
zbx_audit_entry_t	*zbx_audit_entry_init_cuid(const char *cuid, const int id_table,const char *name,
		int audit_action, int resource_type);

void	zbx_audit_update_json_append_string(const zbx_uint64_t id, const int id_table, const char *audit_op,
		const char *key, const char *value, const char *table, const char *field);
void	zbx_audit_update_json_append_string_secret(const zbx_uint64_t id, const int id_table, const char *audit_op,
		const char *key, const char *value, const char *table, const char *field);
void	zbx_audit_update_json_append_uint64(const zbx_uint64_t id, const int id_table, const char *audit_op,
		const char *key, uint64_t value, const char *table, const char *field);
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

zbx_audit_entry_t	*zbx_audit_get_entry(zbx_uint64_t id, const char *cuid, int id_table);
void	zbx_audit_entry_append_int(zbx_audit_entry_t *entry, int audit_op, const char *key, ...);
void	zbx_audit_entry_append_string(zbx_audit_entry_t *entry, int audit_op, const char *key, ...);

#endif	/* ZABBIX_AUDIT_H */
