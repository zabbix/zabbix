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

#include "lld_audit.h"

#include "lld.h"
#include "audit/zbxaudit.h"
#include "audit/zbxaudit_item.h"

#define AUDIT_TABLE_NAME	"items"

static void	lld_audit_item_add_string(const zbx_lld_item_full_t *item, const char *field, const char *value)
{
	char	prop[AUDIT_MAX_KEY_LEN];

	zbx_audit_update_json_append_string(item->itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
			lld_audit_item_prop(item->item_flags, field, prop, sizeof(prop)),
			value, AUDIT_TABLE_NAME, field);
}

static void	lld_audit_item_add_uint64(const zbx_lld_item_full_t *item, const char *field, zbx_uint64_t value)
{
	char	prop[AUDIT_MAX_KEY_LEN];

	zbx_audit_update_json_append_uint64(item->itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
			lld_audit_item_prop(item->item_flags, field, prop, sizeof(prop)),
			value, AUDIT_TABLE_NAME, field);
}

void	zbx_audit_item_update_json_add_lld_data(const zbx_lld_item_full_t *item,
		const zbx_lld_item_prototype_t *item_prototype, zbx_uint64_t hostid, zbx_uint64_t lldruleid)
{
	RETURN_IF_AUDIT_OFF(ZBX_AUDIT_LLD_CONTEXT);

	char	prop[AUDIT_MAX_KEY_LEN];

	lld_audit_item_add_uint64(item, "itemid", item->itemid);
	lld_audit_item_add_string(item, "delay", item->delay);
	lld_audit_item_add_uint64(item, "hostid", hostid);
	lld_audit_item_add_string(item, "name", item->name);
	lld_audit_item_add_string(item, "key_", item->key_);
	lld_audit_item_add_uint64(item, "type", item_prototype->type);
	lld_audit_item_add_uint64(item, "value_type", item_prototype->value_type);
	lld_audit_item_add_string(item, "history", item->history);
	lld_audit_item_add_string(item, "trends", item->trends);
	lld_audit_item_add_uint64(item, "status", item->status);
	lld_audit_item_add_string(item, "trapper_hosts", item_prototype->trapper_hosts);
	lld_audit_item_add_string(item, "units", item->units);
	lld_audit_item_add_string(item, "formula", item_prototype->formula);
	lld_audit_item_add_string(item, "logtimefmt", item_prototype->logtimefmt);
	lld_audit_item_add_uint64(item, "valuemapid", item_prototype->valuemapid);
	lld_audit_item_add_string(item, "params", item->params);
	lld_audit_item_add_string(item, "ipmi_sensor", item->ipmi_sensor);
	lld_audit_item_add_string(item, "snmp_oid", item->snmp_oid);
	lld_audit_item_add_uint64(item, "authtype", item_prototype->authtype);
	lld_audit_item_add_string(item, "username", item->username);

	if (SUCCEED == zbx_audit_item_has_password(item->type))
	{
		zbx_audit_update_json_append_string_secret(item->itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
				lld_audit_item_prop(item->item_flags, "password", prop, sizeof(prop)));
	}

	lld_audit_item_add_string(item, "publickey", item_prototype->publickey);
	lld_audit_item_add_string(item, "privatekey", item_prototype->privatekey);
	lld_audit_item_add_string(item, "description", item->description);
	lld_audit_item_add_uint64(item, "interfaceid", item_prototype->interfaceid);
	lld_audit_item_add_uint64(item, "flags", ZBX_FLAG_DISCOVERY_CREATED | item_prototype->item_flags);
	lld_audit_item_add_string(item, "jmx_endpoint", item->jmx_endpoint);
	lld_audit_item_add_uint64(item, "master_itemid", item->master_itemid);
	lld_audit_item_add_string(item, "timeout", item->timeout);
	lld_audit_item_add_string(item, "url", item->url);

	zbx_audit_item_update_json_add_query_fields_json(ZBX_AUDIT_LLD_CONTEXT, item->itemid, item->item_flags,
			item->query_fields);

	lld_audit_item_add_string(item, "posts", item->posts);
	lld_audit_item_add_string(item, "status_codes", item->status_codes);
	lld_audit_item_add_uint64(item, "follow_redirects", item_prototype->follow_redirects);
	lld_audit_item_add_uint64(item, "post_type", item_prototype->post_type);
	lld_audit_item_add_string(item, "http_proxy", item->http_proxy);

	zbx_audit_item_update_json_add_headers(ZBX_AUDIT_LLD_CONTEXT, item->itemid, item->item_flags, item->headers);

	lld_audit_item_add_uint64(item, "retrieve_mode", item_prototype->retrieve_mode);
	lld_audit_item_add_uint64(item, "request_method", item_prototype->request_method);
	lld_audit_item_add_uint64(item, "output_format", item_prototype->output_format);
	lld_audit_item_add_string(item, "ssl_cert_file", item->ssl_cert_file);
	lld_audit_item_add_string(item, "ssl_key_file", item->ssl_key_file);

	if (SUCCEED == zbx_audit_item_has_ssl_key_password(item->type))
	{
		zbx_audit_update_json_append_string_secret(item->itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
				lld_audit_item_prop(item->item_flags, "ssl_key_password", prop, sizeof(prop)));
	}

	lld_audit_item_add_uint64(item, "verify_peer", item_prototype->verify_peer);
	lld_audit_item_add_uint64(item, "verify_host", item_prototype->verify_host);
	lld_audit_item_add_uint64(item, "allow_traps", item_prototype->allow_traps);

	if (0 != (item->item_flags & ZBX_FLAG_DISCOVERY_PROTOTYPE))
	{
		lld_audit_item_add_uint64(item, "discover", item_prototype->discover);
		lld_audit_item_add_uint64(item, "ruleid", lldruleid);
	}
}
