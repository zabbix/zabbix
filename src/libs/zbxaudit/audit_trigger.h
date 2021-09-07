/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

#ifndef ZABBIX_AUDIT_TRIGGER_H
#define ZABBIX_AUDIT_TRIGGER_H

#include "common.h"
#include "audit.h"

#include "../zbxdbhigh/template.h"

void	zbx_audit_trigger_create_entry(int audit_action, zbx_uint64_t triggerid, const char *name, int flags);

void	zbx_audit_trigger_update_json_add_data(zbx_uint64_t triggerid, zbx_uint64_t templateid,
		unsigned char recovery_mode, unsigned char status, unsigned char type, zbx_uint64_t value,
		zbx_uint64_t state, unsigned char priority, const char *comments, const char *url, int flags,
		unsigned char correlation_mode, const char *correlation_tag, unsigned char manual_close,
		const char *opdata, unsigned char discover, const char *event_name);

void	zbx_audit_trigger_update_json_add_expr(zbx_uint64_t triggerid, int flags, const char *expression);
void	zbx_audit_trigger_update_json_add_rexpr(zbx_uint64_t triggerid, int flags, const char *recovery_expression);

#define PREPARE_AUDIT_TRIGGER_UPDATE_H(resource, type1, type2)							\
void	zbx_audit_trigger_update_json_update_##resource(zbx_uint64_t triggerid, int flags,			\
		type1 resource##_old, type1 resource##_new);
PREPARE_AUDIT_TRIGGER_UPDATE_H(flags, int, int)
PREPARE_AUDIT_TRIGGER_UPDATE_H(recovery_mode, int, int)
PREPARE_AUDIT_TRIGGER_UPDATE_H(correlation_mode, int, int)
PREPARE_AUDIT_TRIGGER_UPDATE_H(correlation_tag, const char*, string)
PREPARE_AUDIT_TRIGGER_UPDATE_H(manual_close, int, int)
PREPARE_AUDIT_TRIGGER_UPDATE_H(opdata, const char*, string)
PREPARE_AUDIT_TRIGGER_UPDATE_H(discover, int, int)
PREPARE_AUDIT_TRIGGER_UPDATE_H(event_name, const char*, string)
PREPARE_AUDIT_TRIGGER_UPDATE_H(type, int, int)
PREPARE_AUDIT_TRIGGER_UPDATE_H(templateid, zbx_uint64_t, uint64)

void	zbx_audit_DBselect_delete_for_trigger(const char *sql, zbx_vector_uint64_t *ids);
void	zbx_audit_trigger_update_json_add_dependency(int flags, zbx_uint64_t triggerdepid,
		zbx_uint64_t triggerid, zbx_uint64_t triggerid_up);
void	zbx_audit_trigger_update_json_add_tags_and_values(zbx_uint64_t triggerid, int flags, zbx_uint64_t triggertagid,
		const char *tag, const char *value);
void	zbx_audit_trigger_update_json_delete_tags(zbx_uint64_t triggerid, int flags, zbx_uint64_t triggertagid);

#endif	/* ZABBIX_AUDIT_TRIGGER_H */
