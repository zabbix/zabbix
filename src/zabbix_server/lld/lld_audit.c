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

void	zbx_audit_item_update_json_add_lld_data(const zbx_lld_item_full_t *item,
		const zbx_lld_item_prototype_t *item_prototype, zbx_uint64_t hostid, zbx_uint64_t lldruleid)
{
#define AUDIT_TABLE_NAME	"items"

	zbx_audit_entry_t	*audit_entry = zbx_audit_item_get_entry(ZBX_AUDIT_LLD_CONTEXT, item->itemid);

	if (NULL == audit_entry)
		return;

	zbx_audit_entry_add_uint64(audit_entry, AUDIT_TABLE_NAME, "itemid", "itemid", item->itemid);
	zbx_audit_entry_add_string(audit_entry, AUDIT_TABLE_NAME, "delay", "delay", item->delay);
	zbx_audit_entry_add_uint64(audit_entry, AUDIT_TABLE_NAME, "hostid", "hostid", hostid);
	zbx_audit_entry_add_string(audit_entry, AUDIT_TABLE_NAME, "name", "name", item->name);
	zbx_audit_entry_add_string(audit_entry, AUDIT_TABLE_NAME, "key_", "key_", item->key_);
	zbx_audit_entry_add_int(audit_entry, AUDIT_TABLE_NAME, "type", "type", item_prototype->type);
	zbx_audit_entry_add_int(audit_entry, AUDIT_TABLE_NAME, "value_type", "value_type",
			item_prototype->value_type);
	zbx_audit_entry_add_string(audit_entry, AUDIT_TABLE_NAME, "history", "history", item->history);
	zbx_audit_entry_add_string(audit_entry, AUDIT_TABLE_NAME, "trends", "trends", item->trends);
	zbx_audit_entry_add_int(audit_entry, AUDIT_TABLE_NAME, "status", "status", item->status);
	zbx_audit_entry_add_string(audit_entry, AUDIT_TABLE_NAME, "trapper_hosts", "trapper_hosts",
			item_prototype->trapper_hosts);
	zbx_audit_entry_add_string(audit_entry, AUDIT_TABLE_NAME, "units", "units", item->units);
	zbx_audit_entry_add_string(audit_entry, AUDIT_TABLE_NAME, "formula", "formula",
			item_prototype->formula);
	zbx_audit_entry_add_string(audit_entry, AUDIT_TABLE_NAME, "logtimefmt", "logtimefmt",
			item_prototype->logtimefmt);
	zbx_audit_entry_add_uint64(audit_entry, AUDIT_TABLE_NAME, "valuemapid", "valuemapid",
			item_prototype->valuemapid);
	zbx_audit_entry_add_string(audit_entry, AUDIT_TABLE_NAME, "params", "params", item->params);
	zbx_audit_entry_add_string(audit_entry, AUDIT_TABLE_NAME, "ipmi_sensor", "ipmi_sensor",
			item->ipmi_sensor);
	zbx_audit_entry_add_string(audit_entry, AUDIT_TABLE_NAME, "snmp_oid", "snmp_oid", item->snmp_oid);
	zbx_audit_entry_add_int(audit_entry, AUDIT_TABLE_NAME, "authtype", "authtype", item_prototype->authtype);
	zbx_audit_entry_add_string(audit_entry, AUDIT_TABLE_NAME, "username", "username", item->username);

	if (SUCCEED == zbx_audit_item_has_password(item->type))
	{
		zbx_audit_entry_add_secret(audit_entry, AUDIT_TABLE_NAME, "password", "password", item->password);
	}

	zbx_audit_entry_add_string(audit_entry, AUDIT_TABLE_NAME, "publickey", "publickey", item_prototype->publickey);
	zbx_audit_entry_add_string(audit_entry, AUDIT_TABLE_NAME, "privatekey", "privatekey",
			item_prototype->privatekey);
	zbx_audit_entry_add_string(audit_entry, AUDIT_TABLE_NAME, "description", "description", item->description);
	zbx_audit_entry_add_uint64(audit_entry, AUDIT_TABLE_NAME, "interfaceid", "interfaceid",
			item_prototype->interfaceid);
	zbx_audit_entry_add_int(audit_entry, AUDIT_TABLE_NAME, "flags", "flags",
			ZBX_FLAG_DISCOVERY_CREATED | item_prototype->item_flags);
	zbx_audit_entry_add_string(audit_entry, AUDIT_TABLE_NAME, "jmx_endpoint", "jmx_endpoint", item->jmx_endpoint);
	zbx_audit_entry_add_uint64(audit_entry, AUDIT_TABLE_NAME, "master_itemid", "master_itemid",
			item->master_itemid);
	zbx_audit_entry_add_string(audit_entry, AUDIT_TABLE_NAME, "timeout", "timeout", item->timeout);
	zbx_audit_entry_add_string(audit_entry, AUDIT_TABLE_NAME, "url", "url", item->url);

	zbx_audit_entry_update_json_add_query_fields_json(audit_entry, item->query_fields);

	zbx_audit_entry_add_string(audit_entry, AUDIT_TABLE_NAME, "posts", "posts", item->posts);
	zbx_audit_entry_add_string(audit_entry, AUDIT_TABLE_NAME, "status_codes", "status_codes", item->status_codes);
	zbx_audit_entry_add_int(audit_entry, AUDIT_TABLE_NAME, "follow_redirects", "follow_redirects",
			item_prototype->follow_redirects);
	zbx_audit_entry_add_int(audit_entry, AUDIT_TABLE_NAME, "post_type", "post_type", item_prototype->post_type);
	zbx_audit_entry_add_string(audit_entry, AUDIT_TABLE_NAME, "http_proxy", "http_proxy", item->http_proxy);

	zbx_audit_entry_update_json_add_headers(audit_entry, item->headers);

	zbx_audit_entry_add_int(audit_entry, AUDIT_TABLE_NAME, "retrieve_mode", "retrieve_mode",
			item_prototype->retrieve_mode);
	zbx_audit_entry_add_int(audit_entry, AUDIT_TABLE_NAME, "request_method", "request_method",
			item_prototype->request_method);
	zbx_audit_entry_add_int(audit_entry, AUDIT_TABLE_NAME, "output_format", "output_format",
			item_prototype->output_format);
	zbx_audit_entry_add_string(audit_entry, AUDIT_TABLE_NAME, "ssl_cert_file", "ssl_cert_file",
			item->ssl_cert_file);
	zbx_audit_entry_add_string(audit_entry, AUDIT_TABLE_NAME, "ssl_key_file", "ssl_key_file", item->ssl_key_file);

	if (SUCCEED == zbx_audit_item_has_ssl_key_password(item->type))
	{
		zbx_audit_entry_add_secret(audit_entry, AUDIT_TABLE_NAME, "ssl_key_password", "ssl_key_password",
				item->ssl_key_password);
	}

	zbx_audit_entry_add_int(audit_entry, AUDIT_TABLE_NAME, "verify_peer", "verify_peer",
			item_prototype->verify_peer);
	zbx_audit_entry_add_int(audit_entry, AUDIT_TABLE_NAME, "verify_host", "verify_host",
			item_prototype->verify_host);
	zbx_audit_entry_add_int(audit_entry, AUDIT_TABLE_NAME, "allow_traps", "allow_traps",
			item_prototype->allow_traps);

	if (0 != (item->item_flags & ZBX_FLAG_DISCOVERY_PROTOTYPE))
	{
		zbx_audit_entry_add_int(audit_entry, AUDIT_TABLE_NAME, "discover", "discover",
				item_prototype->discover);
		zbx_audit_entry_add_uint64(audit_entry, NULL, NULL, "ruleid", lldruleid);
	}

#undef AUDIT_TABLE_NAME
}
