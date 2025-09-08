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

#include "template.h"

#include "audit/zbxaudit.h"
#include "audit/zbxaudit_item.h"

#define AUDIT_TABLE_NAME	"items"

static void	lld_audit_item_add_string(const zbx_template_item_t *item, const char *field, const char *value)
{
	char	prop[AUDIT_DETAILS_KEY_LEN];

	zbx_audit_update_json_append_string(item->itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
			lld_audit_item_prop(item->flags, field, prop, sizeof(prop)),
			value, AUDIT_TABLE_NAME, field);
}

static void	lld_audit_item_add_uint64(const zbx_template_item_t *item, const char *field, zbx_uint64_t value)
{
	char	prop[AUDIT_DETAILS_KEY_LEN];

	zbx_audit_update_json_append_uint64(item->itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
			lld_audit_item_prop(item->flags, field, prop, sizeof(prop)),
			value, AUDIT_TABLE_NAME, field);
}

void	zbx_audit_item_update_json_add_data(int audit_context_mode, const zbx_template_item_t *item,
		zbx_uint64_t hostid)
{
	char	prop[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	lld_audit_item_add_uint64(item, "itemid", item->itemid);
	lld_audit_item_add_string(item, "delay", item->delay);
	lld_audit_item_add_uint64(item, "hostid", hostid);
	lld_audit_item_add_uint64(item, "interfaceid", item->interfaceid);
	lld_audit_item_add_string(item, "key_", item->key_);
	lld_audit_item_add_string(item, "name", item->name);
	lld_audit_item_add_uint64(item, "type", item->type);
	lld_audit_item_add_string(item, "url", item->url);
	lld_audit_item_add_uint64(item, "value_type", item->value_type);

	lld_audit_item_add_uint64(item, "allow_traps", item->allow_traps);
	lld_audit_item_add_uint64(item, "authtype", item->authtype);
	lld_audit_item_add_string(item, "description", item->description);
	lld_audit_item_add_uint64(item, "flags", item->flags);
	lld_audit_item_add_uint64(item, "follow_redirects", item->follow_redirects);

	zbx_audit_item_update_json_add_headers(audit_context_mode, item->itemid, item->flags, item->headers);

	lld_audit_item_add_string(item, "http_proxy", item->http_proxy);
	lld_audit_item_add_uint64(item, "inventory_link", item->inventory_link);

	lld_audit_item_add_string(item, "ipmi_sensor", item->ipmi_sensor);
	lld_audit_item_add_string(item, "jmx_endpoint", item->jmx_endpoint);

	if (0 != (item->flags & ZBX_FLAG_DISCOVERY_RULE))
	{
		lld_audit_item_add_string(item, "lifetime", item->lifetime);
		lld_audit_item_add_uint64(item, "lifetime_type", item->lifetime_type);

		lld_audit_item_add_string(item, "enabled_lifetime", item->enabled_lifetime);
		lld_audit_item_add_uint64(item, "enabled_lifetime_type", item->enabled_lifetime_type);

		lld_audit_item_add_string(item, "formula", item->formula);
		lld_audit_item_add_uint64(item, "evaltype", item->evaltype);
	}
	else
	{
		lld_audit_item_add_string(item, "logtimefmt", item->logtimefmt);

		lld_audit_item_add_string(item, "history", item->history);
		lld_audit_item_add_string(item, "trends", item->trends);
		lld_audit_item_add_string(item, "units", item->units);
		lld_audit_item_add_uint64(item, "valuemapid", item->valuemapid);
	}

	lld_audit_item_add_uint64(item, "master_itemid", item->master_itemid);
	lld_audit_item_add_uint64(item, "output_format", item->output_format);
	lld_audit_item_add_string(item, "params", item->params);

	if (SUCCEED == zbx_audit_item_has_password(item->type))
	{
		zbx_audit_update_json_append_string_secret(item->itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
				lld_audit_item_prop(item->flags, "password", prop, sizeof(prop)));
	}

	lld_audit_item_add_uint64(item, "post_type", item->post_type);
	lld_audit_item_add_string(item, "posts", item->posts);
	lld_audit_item_add_string(item, "privatekey", item->privatekey);
	lld_audit_item_add_string(item, "publickey", item->publickey);

	zbx_audit_item_update_json_add_query_fields_json(audit_context_mode, item->itemid, item->flags,
			item->query_fields);

	lld_audit_item_add_uint64(item, "request_method", item->request_method);
	lld_audit_item_add_uint64(item, "retrieve_mode", item->retrieve_mode);
	lld_audit_item_add_string(item, "snmp_oid", item->snmp_oid);
	lld_audit_item_add_string(item, "ssl_cert_file", item->ssl_cert_file);
	lld_audit_item_add_string(item, "ssl_key_file", item->ssl_key_file);

	if (SUCCEED == zbx_audit_item_has_ssl_key_password(item->type))
	{
		zbx_audit_update_json_append_string_secret(item->itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
				lld_audit_item_prop(item->flags, "ssl_key_password", prop, sizeof(prop)));
	}

	lld_audit_item_add_uint64(item, "status", item->status);
	lld_audit_item_add_string(item, "status_codes", item->status_codes);
	lld_audit_item_add_uint64(item, "templateid", item->templateid);
	lld_audit_item_add_string(item, "timeout", item->timeout);
	lld_audit_item_add_string(item, "trapper_hosts", item->trapper_hosts);

	lld_audit_item_add_string(item, "username", item->username);
	lld_audit_item_add_uint64(item, "verify_host", item->verify_host);
	lld_audit_item_add_uint64(item, "verify_peer", item->verify_peer);

	if (0 != (item->flags & ZBX_FLAG_DISCOVERY_PROTOTYPE))
	{
		lld_audit_item_add_uint64(item, "discover", item->discover);
	}

}
