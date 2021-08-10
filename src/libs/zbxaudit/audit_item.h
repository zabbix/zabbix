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

void	zbx_audit_item_create_entry(int audit_action, zbx_uint64_t itemid, const char *name);
void	zbx_audit_item_add_data(zbx_uint64_t itemid, const zbx_template_item_t *item, zbx_uint64_t hostid);


#define PREPARE_AUDIT_ITEM_UPDATE(resource, type1, type2)				\
void	zbx_audit_item_update_json_update_##resource(zbx_uint64_t itemid,		\
		type1 resource##_old, type1 resource##_new);

PREPARE_AUDIT_ITEM_UPDATE(interfaceid, zbx_uint64_t, uint64)
PREPARE_AUDIT_ITEM_UPDATE(templateid, zbx_uint64_t, uint64)
PREPARE_AUDIT_ITEM_UPDATE(name, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE(type, int, int)
PREPARE_AUDIT_ITEM_UPDATE(value_type, int, int)
PREPARE_AUDIT_ITEM_UPDATE(delay, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE(history, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE(trends, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE(status, int, int)
PREPARE_AUDIT_ITEM_UPDATE(trapper_hosts, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE(units, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE(formula, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE(logtimefmt, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE(valuemapid, zbx_uint64_t, uint64)
PREPARE_AUDIT_ITEM_UPDATE(params, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE(snmp_oid, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE(authtype, int, int)
PREPARE_AUDIT_ITEM_UPDATE(username, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE(password, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE(publickey, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE(privatekey, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE(flags, int, int)
PREPARE_AUDIT_ITEM_UPDATE(description, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE(inventory_link, int, int)
PREPARE_AUDIT_ITEM_UPDATE(lifetime, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE(evaltype, int, int)
PREPARE_AUDIT_ITEM_UPDATE(jmx_endpoint, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE(master_itemid, zbx_uint64_t, zbx_uint64_t)
PREPARE_AUDIT_ITEM_UPDATE(timeout, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE(url, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE(query_fields, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE(posts, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE(status_codes, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE(follow_redirects, int, int)
PREPARE_AUDIT_ITEM_UPDATE(redirects, int, int)
PREPARE_AUDIT_ITEM_UPDATE(post_type, int, int)
PREPARE_AUDIT_ITEM_UPDATE(http_proxy, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE(headers, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE(retrieve_mode, int, int)
PREPARE_AUDIT_ITEM_UPDATE(request_method, int, int)
PREPARE_AUDIT_ITEM_UPDATE(output_format, int, int)
PREPARE_AUDIT_ITEM_UPDATE(ssl_cert_file, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE(ssl_key_file, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE(ssl_key_password, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE(verify_peer, int, int)
PREPARE_AUDIT_ITEM_UPDATE(verify_host, int, int)
PREPARE_AUDIT_ITEM_UPDATE(allow_traps, int, int)
PREPARE_AUDIT_ITEM_UPDATE(discover, int, int)
#undef PREPARE_AUDIT_ITEM_UPDATE

#endif	/* ZABBIX_AUDIT_ITEM_H */
