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

#ifndef ZABBIX_ZBXAUDIT_H
#define ZABBIX_ZBXAUDIT_H

#include "zbxjson.h"
#include "zbxdb.h"

/* audit logging mode */
#define ZBX_AUDITLOG_DISABLED	0
#define ZBX_AUDITLOG_ENABLED	1

#define AUDIT_HOST_ID		1
#define AUDIT_HOSTGRP_ID	2
#define AUDIT_ITEM_ID		3
#define AUDIT_TRIGGER_ID	4
#define AUDIT_GRAPH_ID		5
#define AUDIT_HTTPTEST_ID	6
#define AUDIT_HA_NODE_ID	7
#define AUDIT_CONFIG_ID		8

#define ZBX_AUDIT_ACTION_ADD		0
#define ZBX_AUDIT_ACTION_UPDATE		1
#define ZBX_AUDIT_ACTION_DELETE		2
#define ZBX_AUDIT_ACTION_EXECUTE	7
#define ZBX_AUDIT_ACTION_CONFIG_REFRESH	11
#define ZBX_AUDIT_ACTION_PUSH		12

#define AUDIT_DETAILS_ACTION_ADD	"add"
#define AUDIT_DETAILS_ACTION_UPDATE	"update"
#define AUDIT_DETAILS_ACTION_DELETE	"delete"

#define ZBX_AUDIT_EMPTY_CONTEXT			__UINT64_C(0x00) /* not used yet */
#define ZBX_AUDIT_AUTOREGISTRATION_CONTEXT	__UINT64_C(0x01)
#define ZBX_AUDIT_NETWORK_DISCOVERY_CONTEXT	__UINT64_C(0x02)
#define ZBX_AUDIT_LLD_CONTEXT			__UINT64_C(0x04)
#define ZBX_AUDIT_SCRIPT_CONTEXT		__UINT64_C(0x08) /* not used yet */
#define ZBX_AUDIT_HA_CONTEXT			__UINT64_C(0x10)
#define ZBX_AUDIT_HISTORY_PUSH_CONTEXT		__UINT64_C(0x20) /* not used yet */
#define ZBX_AUDIT_TASKS_RELOAD_CONTEXT		__UINT64_C(0x40)
#define ZBX_AUDIT_ALL_CONTEXT				\
		(ZBX_AUDIT_AUTOREGISTRATION_CONTEXT |	\
		ZBX_AUDIT_NETWORK_DISCOVERY_CONTEXT |	\
		ZBX_AUDIT_LLD_CONTEXT |			\
		ZBX_AUDIT_SCRIPT_CONTEXT |		\
		ZBX_AUDIT_HA_CONTEXT |			\
		ZBX_AUDIT_HISTORY_PUSH_CONTEXT |	\
		ZBX_AUDIT_TASKS_RELOAD_CONTEXT		\
		)

#define ZBX_AUDIT_AUTOREGISTRATION_NETWORK_DISCOVERY_LLD_CONTEXT	\
		(ZBX_AUDIT_AUTOREGISTRATION_CONTEXT |			\
		ZBX_AUDIT_NETWORK_DISCOVERY_CONTEXT |			\
		ZBX_AUDIT_LLD_CONTEXT					\
		)

int	zbx_get_auditlog_enabled(void);
int	zbx_get_auditlog_mode(void);

#define RETURN_IF_AUDIT_OFF(context_mode)								\
	do												\
	{												\
		if (ZBX_AUDITLOG_ENABLED != zbx_get_auditlog_enabled())					\
		{											\
			return;										\
		}											\
		if ((0 != (context_mode & ZBX_AUDIT_AUTOREGISTRATION_NETWORK_DISCOVERY_LLD_CONTEXT)) && \
				SUCCEED == zbx_get_auditlog_mode())					\
		{											\
			return;										\
		}											\
	}												\
	while (0)											\

int	zbx_auditlog_global_script(unsigned char script_type, unsigned char script_execute_on,
		const char *script_command_orig, zbx_uint64_t hostid, const char *hostname, zbx_uint64_t eventid,
		zbx_uint64_t proxyid, zbx_uint64_t userid, const char *username, const char *clientip,
		const char *output, const char *error);

void	zbx_audit_init(int auditlog_enabled_set, int auditlog_mode_set, int audit_context_mode);
void	zbx_audit_prepare(int audit_context_mode);
void	zbx_audit_clean(int audit_context_mode);
void	zbx_audit_flush(int audit_context_mode);
int	zbx_audit_flush_dbconn(zbx_dbconn_t *db, int audit_context_mode);

void	zbx_audit_update_json_append_uint64(const zbx_uint64_t id, const int id_table, const char *audit_op,
		const char *key, uint64_t value, const char *table, const char *field);
void	zbx_audit_update_json_append_string(const zbx_uint64_t id, const int id_table, const char *audit_op,
		const char *key, const char *value, const char *table, const char *field);

int	zbx_audit_item_has_password(int item_type);
int	zbx_audit_item_has_ssl_key_password(int item_type);

void	zbx_audit_update_json_append_string_secret(const zbx_uint64_t id, const int id_table, const char *audit_op,
		const char *key);

int	zbx_auditlog_history_push(zbx_uint64_t userid, const char *username, const char *clientip, int processed_num,
		int failed_num, double time_spent);

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

zbx_hashset_t	*zbx_get_audit_hashset(void);

zbx_audit_entry_t	*zbx_audit_entry_init(zbx_uint64_t id, const int id_table, const char *name, int audit_action,
		int resource_type);

#define ZBX_AUDIT_RESOURCE_HOST				4
#define	ZBX_AUDIT_RESOURCE_GRAPH			6
#define ZBX_AUDIT_RESOURCE_TRIGGER			13
#define ZBX_AUDIT_RESOURCE_HOST_GROUP			14
#define ZBX_AUDIT_RESOURCE_ITEM				15
#define ZBX_AUDIT_RESOURCE_SCENARIO			22
#define ZBX_AUDIT_RESOURCE_SCRIPT			25
#define ZBX_AUDIT_RESOURCE_PROXY			26

#define ZBX_AUDIT_RESOURCE_TRIGGER_PROTOTYPE		31
#define ZBX_AUDIT_RESOURCE_GRAPH_PROTOTYPE		35
#define ZBX_AUDIT_RESOURCE_ITEM_PROTOTYPE		36
#define ZBX_AUDIT_RESOURCE_HOST_PROTOTYPE		37
#define ZBX_AUDIT_RESOURCE_SETTINGS			40
#define ZBX_AUDIT_RESOURCE_HA_NODE			47
#define ZBX_AUDIT_RESOURCE_LLD_RULE			52
#define ZBX_AUDIT_RESOURCE_HISTORY			53

zbx_audit_entry_t	*zbx_audit_get_entry(zbx_uint64_t id, const char *cuid, int id_table);

void	zbx_audit_entry_append_int(zbx_audit_entry_t *entry, int audit_op, const char *key, ...);
void	zbx_audit_entry_append_string(zbx_audit_entry_t *entry, int audit_op, const char *key, ...);


#endif	/* ZABBIX_ZBXAUDIT_H */
