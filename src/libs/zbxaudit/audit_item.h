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

#ifndef ZABBIX_AUDIT_ITEM_H
#define ZABBIX_AUDIT_ITEM_H

#include "common.h"
#include "audit.h"

#include "../zbxdbhigh/template.h"
#include "../../zabbix_server/lld/lld.h"

void	zbx_audit_item_create_entry(int audit_action, zbx_uint64_t itemid, const char *name);
void	zbx_audit_item_add_data(zbx_uint64_t itemid, const zbx_template_item_t *item, zbx_uint64_t hostid);
void	zbx_audit_item_add_lld_data(zbx_uint64_t itemid, const zbx_lld_item_full_t *item,
		const zbx_lld_item_prototype_t *item_prototype, zbx_uint64_t hostid);


#define PREPARE_AUDIT_ITEM_UPDATE_H(resource, type1, type2)				\
void	zbx_audit_item_update_json_update_##resource(zbx_uint64_t itemid, int flags,	\
		type1 resource##_old, type1 resource##_new);

PREPARE_AUDIT_ITEM_UPDATE_H(interfaceid, zbx_uint64_t, uint64)
PREPARE_AUDIT_ITEM_UPDATE_H(templateid, zbx_uint64_t, uint64)
PREPARE_AUDIT_ITEM_UPDATE_H(name, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE_H(type, int, int)
PREPARE_AUDIT_ITEM_UPDATE_H(value_type, int, int)
PREPARE_AUDIT_ITEM_UPDATE_H(delay, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE_H(history, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE_H(trends, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE_H(status, int, int)
PREPARE_AUDIT_ITEM_UPDATE_H(trapper_hosts, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE_H(units, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE_H(formula, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE_H(logtimefmt, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE_H(valuemapid, zbx_uint64_t, uint64)
PREPARE_AUDIT_ITEM_UPDATE_H(params, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE_H(ipmi_sensor, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE_H(snmp_oid, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE_H(authtype, int, int)
PREPARE_AUDIT_ITEM_UPDATE_H(username, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE_H(password, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE_H(publickey, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE_H(privatekey, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE_H(flags, int, int)
PREPARE_AUDIT_ITEM_UPDATE_H(description, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE_H(inventory_link, int, int)
PREPARE_AUDIT_ITEM_UPDATE_H(lifetime, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE_H(evaltype, int, int)
PREPARE_AUDIT_ITEM_UPDATE_H(jmx_endpoint, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE_H(master_itemid, zbx_uint64_t, zbx_uint64_t)
PREPARE_AUDIT_ITEM_UPDATE_H(timeout, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE_H(url, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE_H(query_fields, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE_H(posts, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE_H(status_codes, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE_H(follow_redirects, int, int)
PREPARE_AUDIT_ITEM_UPDATE_H(redirects, int, int)
PREPARE_AUDIT_ITEM_UPDATE_H(post_type, int, int)
PREPARE_AUDIT_ITEM_UPDATE_H(http_proxy, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE_H(headers, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE_H(retrieve_mode, int, int)
PREPARE_AUDIT_ITEM_UPDATE_H(request_method, int, int)
PREPARE_AUDIT_ITEM_UPDATE_H(output_format, int, int)
PREPARE_AUDIT_ITEM_UPDATE_H(ssl_cert_file, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE_H(ssl_key_file, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE_H(ssl_key_password, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE_H(verify_peer, int, int)
PREPARE_AUDIT_ITEM_UPDATE_H(verify_host, int, int)
PREPARE_AUDIT_ITEM_UPDATE_H(allow_traps, int, int)
PREPARE_AUDIT_ITEM_UPDATE_H(discover, int, int)
PREPARE_AUDIT_ITEM_UPDATE_H(key, const char*, string)

void	zbx_audit_discovery_rule_update_json_add_overrides_conditions(zbx_uint64_t itemid,
		zbx_uint64_t item_conditionid, zbx_uint64_t op, const char *macro, const char *value);

#define PREPARE_AUDIT_DISCOVERY_RULE_UPDATE_H(resource, type1, type2)						\
void	zbx_audit_discovery_rule_update_json_update_##resource(zbx_uint64_t itemid,				\
		zbx_uint64_t item_conditionind, type1 resource##_old, type1 resource##_new);
PREPARE_AUDIT_DISCOVERY_RULE_UPDATE_H(operator, int, int)
PREPARE_AUDIT_DISCOVERY_RULE_UPDATE_H(macro, const char*, string)
PREPARE_AUDIT_DISCOVERY_RULE_UPDATE_H(value, const char*, string)

void	zbx_audit_discovery_rule_update_json_delete_overrides_conditions(zbx_uint64_t itemid,
		zbx_uint64_t item_conditionid);

void	zbx_audit_discovery_rule_update_json_add_discovery_rule_preproc(zbx_uint64_t itemid,
		zbx_uint64_t item_preprocid, int step, int type, const char *params, int error_handler,
		const char *error_handler_params);

#define PREPARE_AUDIT_DISCOVERY_RULE_UPDATE_PREPROC_H(resource, type1, type2)					\
void	zbx_audit_discovery_rule_update_json_update_discovery_rule_preproc_##resource(zbx_uint64_t itemid,	\
		zbx_uint64_t preprocid, type1 resource##_old, type1 resource##_new);

PREPARE_AUDIT_DISCOVERY_RULE_UPDATE_PREPROC_H(type, int, int)
PREPARE_AUDIT_DISCOVERY_RULE_UPDATE_PREPROC_H(params, const char*, string)
PREPARE_AUDIT_DISCOVERY_RULE_UPDATE_PREPROC_H(error_handler, int, int)
PREPARE_AUDIT_DISCOVERY_RULE_UPDATE_PREPROC_H(error_handler_params, const char*, string)

#endif	/* ZABBIX_AUDIT_ITEM_H */
