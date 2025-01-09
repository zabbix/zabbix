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

#ifndef ZABBIX_AUDIT_TRIGGER_H
#define ZABBIX_AUDIT_TRIGGER_H

#include "zbxalgo.h"

void	zbx_audit_trigger_create_entry(int audit_context_mode, int audit_action, zbx_uint64_t triggerid,
		const char *name, int flags);

void	zbx_audit_trigger_update_json_add_data(int audit_context_mode, zbx_uint64_t triggerid, zbx_uint64_t templateid,
		unsigned char recovery_mode, unsigned char status, unsigned char type, zbx_uint64_t value,
		zbx_uint64_t state, unsigned char priority, const char *comments, const char *url,
		const char *url_name, int flags, unsigned char correlation_mode, const char *correlation_tag,
		unsigned char manual_close, const char *opdata, unsigned char discover, const char *event_name);

void	zbx_audit_trigger_update_json_add_expr(int audit_context_mode, zbx_uint64_t triggerid, int flags,
		const char *expression);
void	zbx_audit_trigger_update_json_add_rexpr(int audit_context_mode, zbx_uint64_t triggerid, int flags,
		const char *recovery_expression);

#define PREPARE_AUDIT_TRIGGER_UPDATE_H(resource, type1)							\
void	zbx_audit_trigger_update_json_update_##resource(int audit_context_mode, zbx_uint64_t triggerid,	\
		int flags, type1 resource##_old, type1 resource##_new);
PREPARE_AUDIT_TRIGGER_UPDATE_H(flags, int)
PREPARE_AUDIT_TRIGGER_UPDATE_H(recovery_mode, int)
PREPARE_AUDIT_TRIGGER_UPDATE_H(correlation_mode, int)
PREPARE_AUDIT_TRIGGER_UPDATE_H(correlation_tag, const char*)
PREPARE_AUDIT_TRIGGER_UPDATE_H(manual_close, int)
PREPARE_AUDIT_TRIGGER_UPDATE_H(opdata, const char*)
PREPARE_AUDIT_TRIGGER_UPDATE_H(discover, int)
PREPARE_AUDIT_TRIGGER_UPDATE_H(event_name, const char*)
PREPARE_AUDIT_TRIGGER_UPDATE_H(priority, int)
PREPARE_AUDIT_TRIGGER_UPDATE_H(comments, const char*)
PREPARE_AUDIT_TRIGGER_UPDATE_H(url, const char*)
PREPARE_AUDIT_TRIGGER_UPDATE_H(url_name, const char*)
PREPARE_AUDIT_TRIGGER_UPDATE_H(type, int)
PREPARE_AUDIT_TRIGGER_UPDATE_H(status, int)
PREPARE_AUDIT_TRIGGER_UPDATE_H(templateid, zbx_uint64_t)
PREPARE_AUDIT_TRIGGER_UPDATE_H(description, const char*)
PREPARE_AUDIT_TRIGGER_UPDATE_H(expression, const char*)
PREPARE_AUDIT_TRIGGER_UPDATE_H(recovery_expression, const char*)

void	zbx_audit_trigger_delete(int audit_context_mode, zbx_vector_uint64_t *triggerids);
void	zbx_audit_trigger_update_json_add_dependency(int audit_context_mode, int flags, zbx_uint64_t triggerdepid,
		zbx_uint64_t triggerid, zbx_uint64_t triggerid_up);
void	zbx_audit_trigger_update_json_remove_dependency(int audit_context_mode, int flags, zbx_uint64_t triggerdepid,
		zbx_uint64_t triggerid);
void	zbx_audit_trigger_update_json_add_tags_and_values(int audit_context_mode, zbx_uint64_t triggerid, int flags,
		zbx_uint64_t triggertagid, const char *tag, const char *value);
void	zbx_audit_trigger_update_json_delete_tags(int audit_context_mode, zbx_uint64_t triggerid, int flags,
		zbx_uint64_t triggertagid);
void	zbx_audit_trigger_update_json_update_trigger_tag_create_entry(int audit_context_mode, zbx_uint64_t triggerid,
		int trigger_flags, zbx_uint64_t triggertagid);

#define PREPARE_AUDIT_TRIGGER_UPDATE_TAG_H(resource, type1)							\
void	zbx_audit_trigger_update_json_update_tag_##resource(int audit_context_mode, zbx_uint64_t triggerid,	\
		int trigger_flags, zbx_uint64_t triggertagid, type1 resource##_old, type1 resource##_new);
PREPARE_AUDIT_TRIGGER_UPDATE_TAG_H(tag, const char*)
PREPARE_AUDIT_TRIGGER_UPDATE_TAG_H(value, const char*)

#endif	/* ZABBIX_AUDIT_TRIGGER_H */
