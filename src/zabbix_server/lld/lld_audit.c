/*
** Copyright (C) 2001-2024 Zabbix SIA
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

#include "lld_audit.h"

#include "lld.h"
#include "audit/zbxaudit.h"
#include "audit/zbxaudit_item.h"

void	zbx_audit_item_update_json_add_lld_data(zbx_uint64_t itemid, const zbx_lld_item_full_t *item,
		const zbx_lld_item_prototype_t *item_prototype, zbx_uint64_t hostid)
{
	RETURN_IF_AUDIT_OFF(ZBX_AUDIT_LLD_CONTEXT);

#define IT(s) "item."#s
#define ADD_JSON_S(x, t, f)	zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, \
		IT(x), item->x, t, f)
#define ADD_JSON_UI(x, t, f)	zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, \
		IT(x), item->x, t, f)
#define ADD_JSON_P_S(x, t, f)	zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, \
		IT(x), item_prototype->x, t, f)
#define ADD_JSON_P_UI(x, t, f)	zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, \
		IT(x), item_prototype->x, t, f)
#define AUDIT_TABLE_NAME	"items"
	zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, IT(itemid), itemid, \
			AUDIT_TABLE_NAME, "itemid");
	ADD_JSON_S(delay, AUDIT_TABLE_NAME, "delay");
	zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, IT(hostid), hostid, \
			AUDIT_TABLE_NAME, "hostid");
	ADD_JSON_S(name, AUDIT_TABLE_NAME, "name");
	ADD_JSON_S(key_, AUDIT_TABLE_NAME, "key_");
	ADD_JSON_P_UI(type, AUDIT_TABLE_NAME, "type");
	ADD_JSON_P_UI(value_type, AUDIT_TABLE_NAME, "value_type");
	ADD_JSON_S(history, AUDIT_TABLE_NAME, "history");
	ADD_JSON_S(trends, AUDIT_TABLE_NAME, "trends");
	ADD_JSON_UI(status, AUDIT_TABLE_NAME, "status");
	ADD_JSON_P_S(trapper_hosts, AUDIT_TABLE_NAME, "trapper_hosts");
	ADD_JSON_S(units, AUDIT_TABLE_NAME, "units");
	ADD_JSON_P_S(formula, AUDIT_TABLE_NAME, "formula");
	ADD_JSON_P_S(logtimefmt, AUDIT_TABLE_NAME, "logtimefmt");
	ADD_JSON_P_UI(valuemapid, AUDIT_TABLE_NAME, "valuemapid");
	ADD_JSON_S(params, AUDIT_TABLE_NAME, "params");
	ADD_JSON_S(ipmi_sensor, AUDIT_TABLE_NAME, "ipmi_sensor");
	ADD_JSON_S(snmp_oid, AUDIT_TABLE_NAME, "snmp_oid");
	ADD_JSON_P_UI(authtype, AUDIT_TABLE_NAME, "authtype");
	ADD_JSON_S(username, AUDIT_TABLE_NAME, "username");

	if (SUCCEED == zbx_audit_item_has_password(item->type))
	{
		zbx_audit_update_json_append_string_secret(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
				IT(password));
	}

	ADD_JSON_P_S(publickey, AUDIT_TABLE_NAME, "publickey");
	ADD_JSON_P_S(privatekey, AUDIT_TABLE_NAME, "privatekey");
	ADD_JSON_S(description, AUDIT_TABLE_NAME, "description");
	ADD_JSON_P_UI(interfaceid, AUDIT_TABLE_NAME, "interfaceid");
	zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, "item.flags",
			(int)ZBX_FLAG_DISCOVERY_CREATED, AUDIT_TABLE_NAME, "flags");
	ADD_JSON_S(jmx_endpoint, AUDIT_TABLE_NAME, "jmx_endpoint");
	ADD_JSON_UI(master_itemid, AUDIT_TABLE_NAME, "master_itemid");
	ADD_JSON_S(timeout, AUDIT_TABLE_NAME, "timeout");
	ADD_JSON_S(url, AUDIT_TABLE_NAME, "url");
	zbx_audit_item_update_json_add_query_fields_json(ZBX_AUDIT_LLD_CONTEXT, itemid, ZBX_FLAG_DISCOVERY_CREATED,
			item->query_fields);

	ADD_JSON_S(posts, AUDIT_TABLE_NAME, "posts");
	ADD_JSON_S(status_codes, AUDIT_TABLE_NAME, "status_codes");
	ADD_JSON_P_UI(follow_redirects, AUDIT_TABLE_NAME, "follow_redirects");
	ADD_JSON_P_UI(post_type, AUDIT_TABLE_NAME, "post_type");
	ADD_JSON_S(http_proxy, AUDIT_TABLE_NAME, "http_proxy");

	zbx_audit_item_update_json_add_headers(ZBX_AUDIT_LLD_CONTEXT, itemid, ZBX_FLAG_DISCOVERY_CREATED,
			item->headers);

	ADD_JSON_P_UI(retrieve_mode, AUDIT_TABLE_NAME, "retrieve_mode");
	ADD_JSON_P_UI(request_method, AUDIT_TABLE_NAME, "request_method");
	ADD_JSON_P_UI(output_format, AUDIT_TABLE_NAME, "output_format");
	ADD_JSON_S(ssl_cert_file, AUDIT_TABLE_NAME, "ssl_cert_file");
	ADD_JSON_S(ssl_key_file, AUDIT_TABLE_NAME, "ssl_key_file");

	if (SUCCEED == zbx_audit_item_has_ssl_key_password(item->type))
	{
		zbx_audit_update_json_append_string_secret(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
				IT(ssl_key_password));
	}

	ADD_JSON_P_UI(verify_peer, AUDIT_TABLE_NAME, "verify_peer");
	ADD_JSON_P_UI(verify_host, AUDIT_TABLE_NAME, "verify_host");
	ADD_JSON_P_UI(allow_traps, AUDIT_TABLE_NAME, "allow_traps");

#undef AUDIT_TABLE_NAME
#undef ADD_JSON_UI
#undef ADD_JSON_S
#undef ADD_JSON_P_UI
#undef ADD_JSON_P_S
#undef IT
}
