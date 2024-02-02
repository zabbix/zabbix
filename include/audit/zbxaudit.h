/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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

#ifndef ZABBIX_ZBXAUDIT_H
#define ZABBIX_ZBXAUDIT_H

#include "zbxtypes.h"

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
int	zbx_audit_flush_once(int audit_context_mode);

void	zbx_audit_update_json_append_uint64(const zbx_uint64_t id, const int id_table, const char *audit_op,
		const char *key, uint64_t value, const char *table, const char *field);
void	zbx_audit_update_json_append_string(const zbx_uint64_t id, const int id_table, const char *audit_op,
		const char *key, const char *value, const char *table, const char *field);
void	zbx_audit_update_json_append_string_secret(const zbx_uint64_t id, const int id_table, const char *audit_op,
		const char *key, const char *value, const char *table, const char *field);

int	zbx_auditlog_history_push(zbx_uint64_t userid, const char *username, const char *clientip, int processed_num,
		int failed_num, double time_spent);

#endif	/* ZABBIX_ZBXAUDIT_H */
