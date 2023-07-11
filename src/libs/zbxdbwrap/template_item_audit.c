/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#include "template.h"

#include "audit/zbxaudit.h"
#include "audit/zbxaudit_item.h"

void	zbx_audit_item_update_json_add_data(zbx_uint64_t itemid, const zbx_template_item_t *item,
		zbx_uint64_t hostid)
{
	int	resource_type;

	RETURN_IF_AUDIT_OFF();

	resource_type = zbx_audit_item_flag_to_resource_type(item->flags);

#define ADD_JSON_S(x, t, f)	zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,\
		ZBX_AUDIT_IT_OR_ITP_OR_DR(x), item->x, t, f)
#define ADD_JSON_UI(x, t, f)	zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,\
		ZBX_AUDIT_IT_OR_ITP_OR_DR(x), item->x, t, f)
#define AUDIT_TABLE_NAME	"items"
	zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, ZBX_AUDIT_IT_OR_ITP_OR_DR(itemid),
			itemid, AUDIT_TABLE_NAME, "itemid");
	ADD_JSON_S(delay, AUDIT_TABLE_NAME, "delay");
	zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, ZBX_AUDIT_IT_OR_ITP_OR_DR(hostid),
			hostid, AUDIT_TABLE_NAME, "hostid");
	ADD_JSON_UI(interfaceid, AUDIT_TABLE_NAME, "interfaceid");
	ADD_JSON_S(key, AUDIT_TABLE_NAME, "key_");
	ADD_JSON_S(name, AUDIT_TABLE_NAME, "name");
	ADD_JSON_UI(type, AUDIT_TABLE_NAME, "type");
	ADD_JSON_S(url, AUDIT_TABLE_NAME, "url");

	if (1 == zbx_audit_item_resource_is_only_item_prototype(resource_type))
	{
		zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
				(1 == zbx_audit_item_resource_is_only_item(resource_type)) ? "item.value_type" :
				"itemprototype.value_type", item->value_type, AUDIT_TABLE_NAME, "value_type");
	}

	ADD_JSON_UI(allow_traps, AUDIT_TABLE_NAME, "allow_traps");
	ADD_JSON_UI(authtype, AUDIT_TABLE_NAME, "authtype");
	ADD_JSON_S(description, AUDIT_TABLE_NAME, "description");

	if (1 == zbx_audit_item_resource_is_only_item(resource_type))
	{
		zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, "item.flags",
				item->flags, AUDIT_TABLE_NAME, "flags");
	}

	ADD_JSON_UI(follow_redirects, AUDIT_TABLE_NAME, "follow_redirects");
	ADD_JSON_S(headers, AUDIT_TABLE_NAME, "headers");

	if (1 == zbx_audit_item_resource_is_only_item_and_item_prototype(resource_type))
	{
		zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
				(1 == zbx_audit_item_resource_is_only_item(resource_type)) ? "item.history" :
				"itemprototype.history", item->history, AUDIT_TABLE_NAME, "history");
	}

	ADD_JSON_S(http_proxy, AUDIT_TABLE_NAME, "http_proxy");

	if (1 == zbx_audit_item_resource_is_only_item(resource_type))
	{
		zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
				"item.inventory_link", item->inventory_link, AUDIT_TABLE_NAME, "inventory_link");
	}

	ADD_JSON_S(ipmi_sensor, AUDIT_TABLE_NAME, "ipmi_sensor");
	ADD_JSON_S(jmx_endpoint, AUDIT_TABLE_NAME, "jmx_endpoint");

	if (1 == zbx_audit_item_resource_is_only_lld_rule(resource_type))
	{
		zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
				"discoveryrule.lifetime", item->lifetime, AUDIT_TABLE_NAME, "lifetime");
	}

	if (1 == zbx_audit_item_resource_is_only_item_and_item_prototype(resource_type))
	{
		zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
				(1 == zbx_audit_item_resource_is_only_item(resource_type)) ? "item.logtimefmt" :
				"itemprototype.logtimefmt", item->logtimefmt, AUDIT_TABLE_NAME, "logtimefmt");
	}

	ADD_JSON_UI(master_itemid, AUDIT_TABLE_NAME, "master_itemid");
	ADD_JSON_UI(output_format, AUDIT_TABLE_NAME, "output_format");
	ADD_JSON_S(params, AUDIT_TABLE_NAME, "params");

	zbx_audit_update_json_append_string_secret(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
			ZBX_AUDIT_IT_OR_ITP_OR_DR(password), item->password, AUDIT_TABLE_NAME, "password");

	ADD_JSON_UI(post_type, AUDIT_TABLE_NAME, "post_type");
	ADD_JSON_S(posts, AUDIT_TABLE_NAME, "posts");
	ADD_JSON_S(privatekey, AUDIT_TABLE_NAME, "privatekey");
	ADD_JSON_S(publickey, AUDIT_TABLE_NAME, "publickey");
	ADD_JSON_S(query_fields, AUDIT_TABLE_NAME, "query_fields");
	ADD_JSON_UI(request_method, AUDIT_TABLE_NAME, "request_method");
	ADD_JSON_UI(retrieve_mode, AUDIT_TABLE_NAME, "retrieve_mode");
	ADD_JSON_S(snmp_oid, AUDIT_TABLE_NAME, "snmp_oid");
	ADD_JSON_S(ssl_cert_file, AUDIT_TABLE_NAME, "ssl_cert_file");
	ADD_JSON_S(ssl_key_file, AUDIT_TABLE_NAME, "ssl_key_file");

	zbx_audit_update_json_append_string_secret(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
			ZBX_AUDIT_IT_OR_ITP_OR_DR(ssl_key_password), item->ssl_key_password, AUDIT_TABLE_NAME,
			"ssl_key_password");

	ADD_JSON_UI(status, AUDIT_TABLE_NAME, "status");
	ADD_JSON_S(status_codes, AUDIT_TABLE_NAME, "status_codes");
	ADD_JSON_UI(templateid, AUDIT_TABLE_NAME, "templateid");
	ADD_JSON_S(timeout, AUDIT_TABLE_NAME, "timeout");
	ADD_JSON_S(trapper_hosts, AUDIT_TABLE_NAME, "trapper_hosts");

	if (1 == zbx_audit_item_resource_is_only_item_and_item_prototype(resource_type))
	{
		zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
				(1 == zbx_audit_item_resource_is_only_item(resource_type)) ? "item.trends" :
				"itemprototype.trends", item->trends, AUDIT_TABLE_NAME, "trends");
		zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
				(1 == zbx_audit_item_resource_is_only_item(resource_type)) ? "item.units" :
				"itemprototype.units", item->units, AUDIT_TABLE_NAME, "units");
	}

	ADD_JSON_S(username, AUDIT_TABLE_NAME, "username");

	if (1 == zbx_audit_item_resource_is_only_item_and_item_prototype(resource_type))
	{
		zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
				(1 == zbx_audit_item_resource_is_only_item(resource_type)) ? "item.valuemapid" :
				"itemprototype.valuemapid", item->valuemapid, AUDIT_TABLE_NAME, "valuemapid");
	}

	ADD_JSON_UI(verify_host, AUDIT_TABLE_NAME, "verify_host");
	ADD_JSON_UI(verify_peer, AUDIT_TABLE_NAME, "verify_peer");

	if (1 == zbx_audit_item_resource_is_only_item_prototype(resource_type))
	{
		zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
				"itemprototype.discover", item->discover, AUDIT_TABLE_NAME, "discover");
	}

	if (1 == zbx_audit_item_resource_is_only_lld_rule(resource_type))
	{
		zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
				"discoveryrule.filter.formula", item->formula, AUDIT_TABLE_NAME, "formula");
		zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
				"discoveryrule.filter.evaltype", item->evaltype, AUDIT_TABLE_NAME, "evaltype");
	}
#undef AUDIT_TABLE_NAME
#undef ADD_JSON_UI
#undef ADD_JSON_S
}
