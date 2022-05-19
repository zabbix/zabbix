/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

#include "audit/zbxaudit_item.h"

#include "audit/zbxaudit.h"
#include "log.h"
#include "audit.h"
#include "zbxdbhigh.h"
#include "zbxdb.h"

static int	item_flag_to_resource_type(int flag)
{
	if (ZBX_FLAG_DISCOVERY_NORMAL == flag || ZBX_FLAG_DISCOVERY_CREATED == flag)
	{
		return AUDIT_RESOURCE_ITEM;
	}
	else if (ZBX_FLAG_DISCOVERY_PROTOTYPE == flag)
	{
		return AUDIT_RESOURCE_ITEM_PROTOTYPE;
	}
	else if (ZBX_FLAG_DISCOVERY_RULE == flag)
	{
		return AUDIT_RESOURCE_DISCOVERY_RULE;
	}
	else
	{
		zabbix_log(LOG_LEVEL_CRIT, "unexpected audit detected: ->%d<-", flag);
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}
}

void	zbx_audit_item_create_entry(int audit_action, zbx_uint64_t itemid, const char *name, int flags)
{
	int	resource_type;

	zbx_audit_entry_t	local_audit_item_entry, **found_audit_item_entry;
	zbx_audit_entry_t	*local_audit_item_entry_x = &local_audit_item_entry;

	RETURN_IF_AUDIT_OFF();

	resource_type = item_flag_to_resource_type(flags);

	local_audit_item_entry.id = itemid;
	local_audit_item_entry.cuid = NULL;
	local_audit_item_entry.id_table = AUDIT_ITEM_ID;
	found_audit_item_entry = (zbx_audit_entry_t**)zbx_hashset_search(zbx_get_audit_hashset(),
			&(local_audit_item_entry_x));
	if (NULL == found_audit_item_entry)
	{
		zbx_audit_entry_t	*local_audit_item_entry_insert;

		local_audit_item_entry_insert = zbx_audit_entry_init(itemid, AUDIT_ITEM_ID, name, audit_action,
				resource_type);

		zbx_hashset_insert(zbx_get_audit_hashset(), &local_audit_item_entry_insert,
				sizeof(local_audit_item_entry_insert));
	}
}

#define ONLY_ITEM (AUDIT_RESOURCE_ITEM == resource_type)
#define ONLY_ITEM_PROTOTYPE (AUDIT_RESOURCE_ITEM_PROTOTYPE == resource_type)
#define ONLY_LLD_RULE (AUDIT_RESOURCE_DISCOVERY_RULE == resource_type)
#define IT_OR_ITP_OR_DR(s) ONLY_ITEM ? "item."#s : (ONLY_ITEM_PROTOTYPE ? "itemprototype."#s : "discoveryrule."#s)

void	zbx_audit_item_update_json_add_data(zbx_uint64_t itemid, const zbx_template_item_t *item, zbx_uint64_t hostid)
{
	int	resource_type;

	RETURN_IF_AUDIT_OFF();

	resource_type = item_flag_to_resource_type(item->flags);

#define ONLY_ITEM_AND_ITEM_PROTOTYPE (AUDIT_RESOURCE_ITEM == resource_type || \
		AUDIT_RESOURCE_ITEM_PROTOTYPE == resource_type)
#define ADD_JSON_S(x, t, f)	zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,\
		IT_OR_ITP_OR_DR(x), item->x, t, f)
#define ADD_JSON_UI(x, t, f)	zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,\
		IT_OR_ITP_OR_DR(x), item->x, t, f)
#define AUDIT_TABLE_NAME	"items"
	zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, IT_OR_ITP_OR_DR(itemid),
			itemid, AUDIT_TABLE_NAME, "itemid");
	ADD_JSON_S(delay, AUDIT_TABLE_NAME, "delay");
	zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, IT_OR_ITP_OR_DR(hostid),
			hostid, AUDIT_TABLE_NAME, "hostid");
	ADD_JSON_UI(interfaceid, AUDIT_TABLE_NAME, "interfaceid");
	ADD_JSON_S(key, AUDIT_TABLE_NAME, "key_");
	ADD_JSON_S(name, AUDIT_TABLE_NAME, "name");
	ADD_JSON_UI(type, AUDIT_TABLE_NAME, "type");
	ADD_JSON_S(url, AUDIT_TABLE_NAME, "url");

	if ONLY_ITEM_AND_ITEM_PROTOTYPE
	{
		zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, ONLY_ITEM ?
				"item.value_type" : "itemprototype.value_type", item->value_type, AUDIT_TABLE_NAME,
				"value_type");
	}

	ADD_JSON_UI(allow_traps, AUDIT_TABLE_NAME, "allow_traps");
	ADD_JSON_UI(authtype, AUDIT_TABLE_NAME, "authtype");
	ADD_JSON_S(description, AUDIT_TABLE_NAME, "description");

	if ONLY_ITEM
	{
		zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, "item.flags",
				item->flags, AUDIT_TABLE_NAME, "flags");
	}

	ADD_JSON_UI(follow_redirects, AUDIT_TABLE_NAME, "follow_redirects");
	ADD_JSON_S(headers, AUDIT_TABLE_NAME, "headers");

	if ONLY_ITEM_AND_ITEM_PROTOTYPE
	{
		zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, ONLY_ITEM ?
				"item.history" : "itemprototype.history", item->history, AUDIT_TABLE_NAME, "history");
	}

	ADD_JSON_S(http_proxy, AUDIT_TABLE_NAME, "http_proxy");

	if ONLY_ITEM
	{
		zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
				"item.inventory_link", item->inventory_link, AUDIT_TABLE_NAME, "inventory_link");
	}

	ADD_JSON_S(ipmi_sensor, AUDIT_TABLE_NAME, "ipmi_sensor");
	ADD_JSON_S(jmx_endpoint, AUDIT_TABLE_NAME, "jmx_endpoint");

	if ONLY_LLD_RULE
	{
		zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
				"discoveryrule.lifetime", item->lifetime, AUDIT_TABLE_NAME, "lifetime");
	}

	if ONLY_ITEM_AND_ITEM_PROTOTYPE
	{
		zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, ONLY_ITEM ?
				"item.logtimefmt" : "itemprototype.logtimefmt", item->logtimefmt, AUDIT_TABLE_NAME,
				"logtimefmt");
	}

	ADD_JSON_UI(master_itemid, AUDIT_TABLE_NAME, "master_itemid");
	ADD_JSON_UI(output_format, AUDIT_TABLE_NAME, "output_format");
	ADD_JSON_S(params, AUDIT_TABLE_NAME, "params");

	zbx_audit_update_json_append_string_secret(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
			IT_OR_ITP_OR_DR(password), item->password, AUDIT_TABLE_NAME, "password");

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
			IT_OR_ITP_OR_DR(ssl_key_password), item->ssl_key_password, AUDIT_TABLE_NAME,
			"ssl_key_password");

	ADD_JSON_UI(status, AUDIT_TABLE_NAME, "status");
	ADD_JSON_S(status_codes, AUDIT_TABLE_NAME, "status_codes");
	ADD_JSON_UI(templateid, AUDIT_TABLE_NAME, "templateid");
	ADD_JSON_S(timeout, AUDIT_TABLE_NAME, "timeout");
	ADD_JSON_S(trapper_hosts, AUDIT_TABLE_NAME, "trapper_hosts");

	if ONLY_ITEM_AND_ITEM_PROTOTYPE
	{
		zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, ONLY_ITEM ?
				"item.trends" : "itemprototype.trends", item->trends, AUDIT_TABLE_NAME, "trends");
		zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, ONLY_ITEM ?
				"item.units" : "itemprototype.units", item->units, AUDIT_TABLE_NAME, "units");
	}

	ADD_JSON_S(username, AUDIT_TABLE_NAME, "username");

	if ONLY_ITEM_AND_ITEM_PROTOTYPE
	{
		zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, ONLY_ITEM ?
				"item.valuemapid" : "itemprototype.valuemapid", item->valuemapid, AUDIT_TABLE_NAME,
				"valuemapid");
	}

	ADD_JSON_UI(verify_host, AUDIT_TABLE_NAME, "verify_host");
	ADD_JSON_UI(verify_peer, AUDIT_TABLE_NAME, "verify_peer");

	if ONLY_ITEM_PROTOTYPE
	{
		zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
				"itemprototype.discover", item->discover, AUDIT_TABLE_NAME, "discover");
	}

	if ONLY_LLD_RULE
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

void	zbx_audit_item_update_json_add_lld_data(zbx_uint64_t itemid, const zbx_lld_item_full_t *item,
		const zbx_lld_item_prototype_t *item_prototype, zbx_uint64_t hostid)
{
	RETURN_IF_AUDIT_OFF();

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
	ADD_JSON_S(key, AUDIT_TABLE_NAME, "key_");
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
	zbx_audit_update_json_append_string_secret(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, IT(password),
			item->password, AUDIT_TABLE_NAME, "password");
	ADD_JSON_P_S(publickey, AUDIT_TABLE_NAME, "publickey");
	ADD_JSON_P_S(privatekey, AUDIT_TABLE_NAME, "privatekey");
	ADD_JSON_S(description, AUDIT_TABLE_NAME, "description");
	ADD_JSON_P_UI(interfaceid, AUDIT_TABLE_NAME, "interfaceid");
	zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, "item.flags",
			ZBX_FLAG_DISCOVERY_CREATED, AUDIT_TABLE_NAME, "flags");
	ADD_JSON_S(jmx_endpoint, AUDIT_TABLE_NAME, "jmx_endpoint");
	ADD_JSON_UI(master_itemid, AUDIT_TABLE_NAME, "master_itemid");
	ADD_JSON_S(timeout, AUDIT_TABLE_NAME, "timeout");
	ADD_JSON_S(url, AUDIT_TABLE_NAME, "url");
	ADD_JSON_S(query_fields, AUDIT_TABLE_NAME, "query_fields");
	ADD_JSON_S(posts, AUDIT_TABLE_NAME, "posts");
	ADD_JSON_S(status_codes, AUDIT_TABLE_NAME, "status_codes");
	ADD_JSON_P_UI(follow_redirects, AUDIT_TABLE_NAME, "follow_redirects");
	ADD_JSON_P_UI(post_type, AUDIT_TABLE_NAME, "post_type");
	ADD_JSON_S(http_proxy, AUDIT_TABLE_NAME, "http_proxy");
	ADD_JSON_S(headers, AUDIT_TABLE_NAME, "headers");
	ADD_JSON_P_UI(retrieve_mode, AUDIT_TABLE_NAME, "retrieve_mode");
	ADD_JSON_P_UI(request_method, AUDIT_TABLE_NAME, "request_method");
	ADD_JSON_P_UI(output_format, AUDIT_TABLE_NAME, "output_format");
	ADD_JSON_S(ssl_cert_file, AUDIT_TABLE_NAME, "ssl_cert_file");
	ADD_JSON_S(ssl_key_file, AUDIT_TABLE_NAME, "ssl_key_file");
	zbx_audit_update_json_append_string_secret(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
			IT(ssl_key_password), item->ssl_key_password, AUDIT_TABLE_NAME, "ssl_key_password");
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

#define PREPARE_AUDIT_ITEM_UPDATE(resource, type1, type2)							\
void	zbx_audit_item_update_json_update_##resource(zbx_uint64_t itemid, int flags,				\
		type1 resource##_old, type1 resource##_new)							\
{														\
	int	resource_type;											\
														\
	RETURN_IF_AUDIT_OFF();											\
														\
	resource_type = item_flag_to_resource_type(flags);							\
	zbx_audit_update_json_update_##type2(itemid, AUDIT_ITEM_ID, IT_OR_ITP_OR_DR(resource), resource##_old,	\
			resource##_new);									\
}

PREPARE_AUDIT_ITEM_UPDATE(interfaceid,		zbx_uint64_t,	uint64)
PREPARE_AUDIT_ITEM_UPDATE(templateid,		zbx_uint64_t,	uint64)
PREPARE_AUDIT_ITEM_UPDATE(name,			const char*,	string)
PREPARE_AUDIT_ITEM_UPDATE(type,			int,		int)
PREPARE_AUDIT_ITEM_UPDATE(value_type,		int,		int)
PREPARE_AUDIT_ITEM_UPDATE(delay,		const char*,	string)
PREPARE_AUDIT_ITEM_UPDATE(history,		const char*,	string)
PREPARE_AUDIT_ITEM_UPDATE(trends,		const char*,	string)
PREPARE_AUDIT_ITEM_UPDATE(status,		int,		int)
PREPARE_AUDIT_ITEM_UPDATE(trapper_hosts,	const char*,	string)
PREPARE_AUDIT_ITEM_UPDATE(units,		const char*,	string)
PREPARE_AUDIT_ITEM_UPDATE(formula,		const char*,	string)
PREPARE_AUDIT_ITEM_UPDATE(logtimefmt,		const char*,	string)
PREPARE_AUDIT_ITEM_UPDATE(valuemapid,		zbx_uint64_t,	uint64)
PREPARE_AUDIT_ITEM_UPDATE(params,		const char*,	string)
PREPARE_AUDIT_ITEM_UPDATE(ipmi_sensor,		const char*,	string)
PREPARE_AUDIT_ITEM_UPDATE(snmp_oid,		const char*,	string)
PREPARE_AUDIT_ITEM_UPDATE(authtype,		int,		int)
PREPARE_AUDIT_ITEM_UPDATE(username,		const char*,	string)
PREPARE_AUDIT_ITEM_UPDATE(password,		const char*,	string)
PREPARE_AUDIT_ITEM_UPDATE(publickey,		const char*,	string)
PREPARE_AUDIT_ITEM_UPDATE(privatekey,		const char*,	string)
PREPARE_AUDIT_ITEM_UPDATE(flags,		int,		int)
PREPARE_AUDIT_ITEM_UPDATE(description,		const char*,	string)
PREPARE_AUDIT_ITEM_UPDATE(inventory_link,	int,		int)
PREPARE_AUDIT_ITEM_UPDATE(lifetime,		const char*,	string)
PREPARE_AUDIT_ITEM_UPDATE(evaltype,		int,		int)
PREPARE_AUDIT_ITEM_UPDATE(jmx_endpoint,		const char*,	string)
PREPARE_AUDIT_ITEM_UPDATE(master_itemid,	zbx_uint64_t,	uint64)
PREPARE_AUDIT_ITEM_UPDATE(timeout,		const char*,	string)
PREPARE_AUDIT_ITEM_UPDATE(url,			const char*,	string)
PREPARE_AUDIT_ITEM_UPDATE(query_fields,		const char*,	string)
PREPARE_AUDIT_ITEM_UPDATE(posts,		const char*,	string)
PREPARE_AUDIT_ITEM_UPDATE(status_codes,		const char*,	string)
PREPARE_AUDIT_ITEM_UPDATE(follow_redirects,	int,		int)
PREPARE_AUDIT_ITEM_UPDATE(redirects,		int,		int)
PREPARE_AUDIT_ITEM_UPDATE(post_type,		int,		int)
PREPARE_AUDIT_ITEM_UPDATE(http_proxy,		const char*,	string)
PREPARE_AUDIT_ITEM_UPDATE(headers,		const char*,	string)
PREPARE_AUDIT_ITEM_UPDATE(retrieve_mode,	int,		int)
PREPARE_AUDIT_ITEM_UPDATE(request_method,	int,		int)
PREPARE_AUDIT_ITEM_UPDATE(output_format,	int,		int)
PREPARE_AUDIT_ITEM_UPDATE(ssl_cert_file,	const char*,	string)
PREPARE_AUDIT_ITEM_UPDATE(ssl_key_file,		const char*,	string)
PREPARE_AUDIT_ITEM_UPDATE(ssl_key_password,	const char*,	string)
PREPARE_AUDIT_ITEM_UPDATE(verify_peer,		int,		int)
PREPARE_AUDIT_ITEM_UPDATE(verify_host,		int,		int)
PREPARE_AUDIT_ITEM_UPDATE(allow_traps,		int,		int)
PREPARE_AUDIT_ITEM_UPDATE(discover,		int,		int)
PREPARE_AUDIT_ITEM_UPDATE(key,			const char*,	string)
#undef PREPARE_AUDIT_ITEM_UPDATE

#undef ONLY_ITEM
#undef ONLY_ITEM_PROTOTYPE
#undef ONLY_LLD_RULE
#undef IT_OR_ITP_OR_DR

/******************************************************************************
 *                                                                            *
 * Parameters: id   - [IN] resource id                                        *
 *             name - [IN] resource name                                      *
 *             flag - [IN] resource flag                                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_audit_item_create_entry_for_delete(zbx_uint64_t id, const char *name, int flag)
{
	int			resource_type;
	zbx_audit_entry_t	local_audit_item_entry, **found_audit_item_entry;
	zbx_audit_entry_t	*local_audit_item_entry_x = &local_audit_item_entry;

	RETURN_IF_AUDIT_OFF();

	resource_type = item_flag_to_resource_type(flag);

	local_audit_item_entry.id = id;
	local_audit_item_entry.cuid = NULL;
	local_audit_item_entry.id_table = AUDIT_ITEM_ID;

	found_audit_item_entry = (zbx_audit_entry_t**)zbx_hashset_search(zbx_get_audit_hashset(),
			&(local_audit_item_entry_x));
	if (NULL == found_audit_item_entry)
	{
		zbx_audit_entry_t	*local_audit_item_entry_insert;

		local_audit_item_entry_insert = zbx_audit_entry_init(id, AUDIT_ITEM_ID, name, ZBX_AUDIT_ACTION_DELETE,
				resource_type);
		zbx_hashset_insert(zbx_get_audit_hashset(), &local_audit_item_entry_insert,
				sizeof(local_audit_item_entry_insert));
	}
}

/******************************************************************************
 *                                                                            *
 * Parameters: sql - [IN] sql statement                                       *
 *             ids - [OUT] sorted list of selected uint64 values              *
 *                                                                            *
 * Return value: SUCCEED - query SUCCEEDED                                    *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_audit_DBselect_delete_for_item(const char *sql, zbx_vector_uint64_t *ids)
{
	int		ret = FAIL;
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	id;

	if (NULL == (result = DBselect("%s", sql)))
		goto out;

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(id, row[0]);
		zbx_vector_uint64_append(ids, id);
		zbx_audit_item_create_entry_for_delete(id, row[1], atoi(row[2]));
	}

	DBfree_result(result);

	zbx_vector_uint64_sort(ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	ret = SUCCEED;
out:
	return ret;
}

void	zbx_audit_discovery_rule_update_json_add_filter_conditions(zbx_uint64_t itemid, zbx_uint64_t rule_conditionid,
		zbx_uint64_t op, const char *macro, const char *value)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_operator[AUDIT_DETAILS_KEY_LEN],
		audit_key_macro[AUDIT_DETAILS_KEY_LEN], audit_key_value[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	zbx_snprintf(audit_key, sizeof(audit_key),
			"discoveryrule.filter[" ZBX_FS_UI64 "].conditions", rule_conditionid);
	zbx_snprintf(audit_key_operator, sizeof(audit_key_operator),
			"discoveryrule.filter[" ZBX_FS_UI64 "].conditions.operator", rule_conditionid);
	zbx_snprintf(audit_key_macro, sizeof(audit_key_macro),
			"discoveryrule.filter[" ZBX_FS_UI64 "].conditions.macro", rule_conditionid);
	zbx_snprintf(audit_key_value, sizeof(audit_key_value),
			"discoveryrule.filter[" ZBX_FS_UI64 "].conditions.value", rule_conditionid);

#define	AUDIT_TABLE_NAME	"item_condition"
	zbx_audit_update_json_append_no_value(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key);
	zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_operator, op,
			AUDIT_TABLE_NAME, "operator");
	zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_macro, macro,
			AUDIT_TABLE_NAME, "macro");
	zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_value, value,
			AUDIT_TABLE_NAME, "value");
#undef AUDIT_TABLE_NAME
}

void	zbx_audit_discovery_rule_update_json_update_filter_conditions_create_entry(zbx_uint64_t itemid,
		zbx_uint64_t item_conditionid)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	zbx_snprintf(buf, sizeof(buf), "discoveryrule.filter[" ZBX_FS_UI64 "].conditions", item_conditionid);

	zbx_audit_update_json_append_no_value(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_UPDATE, buf);
}

#define PREPARE_AUDIT_DISCOVERY_RULE_UPDATE(resource, type1, type2)						\
void	zbx_audit_discovery_rule_update_json_update_filter_conditions_##resource(zbx_uint64_t itemid,		\
		zbx_uint64_t item_conditionid, type1 resource##_old, type1 resource##_new)			\
{														\
	char	buf[AUDIT_DETAILS_KEY_LEN];									\
														\
	RETURN_IF_AUDIT_OFF();											\
														\
	zbx_snprintf(buf, sizeof(buf), "discoveryrule.filter[" ZBX_FS_UI64 "].conditions."#resource,		\
			item_conditionid);									\
														\
	zbx_audit_update_json_update_##type2(itemid, AUDIT_ITEM_ID, buf, resource##_old, resource##_new);	\
}
PREPARE_AUDIT_DISCOVERY_RULE_UPDATE(operator, int, int)
PREPARE_AUDIT_DISCOVERY_RULE_UPDATE(macro, const char*, string)
PREPARE_AUDIT_DISCOVERY_RULE_UPDATE(value, const char*, string)
#undef PREPARE_AUDIT_DISCOVERY_RULE_UPDATE

void	zbx_audit_discovery_rule_update_json_delete_filter_conditions(zbx_uint64_t itemid,
		zbx_uint64_t item_conditionid)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	zbx_snprintf(buf, sizeof(buf), "discoveryrule.filter[" ZBX_FS_UI64 "].conditions", item_conditionid);

	zbx_audit_update_json_delete(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_DELETE, buf);
}

#define ITEM_RESOURCE_KEY_RESOLVE_PREPROC(resource, nested)							\
	if (AUDIT_RESOURCE_ITEM == resource_type)								\
	{													\
		zbx_snprintf(audit_key_##resource, sizeof(audit_key_##resource), "item.preprocessing["		\
				ZBX_FS_UI64 "]"#nested#resource, preprocid);					\
	}													\
	else if (AUDIT_RESOURCE_ITEM_PROTOTYPE == resource_type)						\
	{													\
		zbx_snprintf(audit_key_##resource, sizeof(audit_key_##resource), "itemprototype.preprocessing["	\
				ZBX_FS_UI64 "]"#nested#resource, preprocid);					\
	}													\
	else if (AUDIT_RESOURCE_DISCOVERY_RULE == resource_type)						\
	{													\
		zbx_snprintf(audit_key_##resource, sizeof(audit_key_##resource), "discoveryrule.preprocessing["	\
				ZBX_FS_UI64 "]"#nested#resource, preprocid);					\
	}													\
	else													\
	{													\
		THIS_SHOULD_NEVER_HAPPEN;									\
		return;												\
	}

void	zbx_audit_item_update_json_add_item_preproc(zbx_uint64_t itemid, zbx_uint64_t preprocid, int item_flags,
		int step, int type, const char *params, int error_handler, const char *error_handler_params)
{
	int	resource_type;
	char	audit_key_[AUDIT_DETAILS_KEY_LEN], audit_key_step[AUDIT_DETAILS_KEY_LEN],
		audit_key_type[AUDIT_DETAILS_KEY_LEN], audit_key_params[AUDIT_DETAILS_KEY_LEN],
		audit_key_error_handler[AUDIT_DETAILS_KEY_LEN], audit_key_error_handler_params[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	resource_type = item_flag_to_resource_type(item_flags);

	ITEM_RESOURCE_KEY_RESOLVE_PREPROC(,)
	ITEM_RESOURCE_KEY_RESOLVE_PREPROC(step, .)
	ITEM_RESOURCE_KEY_RESOLVE_PREPROC(type, .)
	ITEM_RESOURCE_KEY_RESOLVE_PREPROC(params, .)
	ITEM_RESOURCE_KEY_RESOLVE_PREPROC(error_handler, .)
	ITEM_RESOURCE_KEY_RESOLVE_PREPROC(error_handler_params, .)

#define AUDIT_TABLE_NAME	"item_preproc"
	zbx_audit_update_json_append_no_value(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_);
	zbx_audit_update_json_append_int(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_step, step,
			AUDIT_TABLE_NAME, "step");
	zbx_audit_update_json_append_int(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_type, type,
			AUDIT_TABLE_NAME, "type");
	zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_params, params,
			AUDIT_TABLE_NAME, "params");
	zbx_audit_update_json_append_int(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_error_handler,
			error_handler, AUDIT_TABLE_NAME, "error_handler");
	zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
			audit_key_error_handler_params, error_handler_params, AUDIT_TABLE_NAME, "error_handler_params");
#undef AUDIT_TABLE_NAME
}

void	zbx_audit_item_update_json_update_item_preproc_create_entry(zbx_uint64_t itemid, int item_flags,
		zbx_uint64_t preprocid)
{
	int	resource_type;
	char	audit_key_[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	resource_type = item_flag_to_resource_type(item_flags);

	ITEM_RESOURCE_KEY_RESOLVE_PREPROC(,)

	zbx_audit_update_json_append_no_value(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_UPDATE, audit_key_);
}

#define PREPARE_AUDIT_ITEM_UPDATE_PREPROC(resource, type1, type2)						\
void	zbx_audit_item_update_json_update_item_preproc_##resource(zbx_uint64_t itemid, int item_flags,		\
		zbx_uint64_t preprocid, type1 resource##_old, type1 resource##_new)				\
{														\
	int	resource_type;											\
	char	audit_key_##resource[AUDIT_DETAILS_KEY_LEN];							\
														\
	RETURN_IF_AUDIT_OFF();											\
	resource_type = item_flag_to_resource_type(item_flags);							\
														\
	ITEM_RESOURCE_KEY_RESOLVE_PREPROC(resource,.)								\
														\
	zbx_audit_update_json_update_##type2(itemid, AUDIT_ITEM_ID, audit_key_##resource, resource##_old,	\
			resource##_new);									\
}
PREPARE_AUDIT_ITEM_UPDATE_PREPROC(type, int, int)
PREPARE_AUDIT_ITEM_UPDATE_PREPROC(params, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE_PREPROC(error_handler, int, int)
PREPARE_AUDIT_ITEM_UPDATE_PREPROC(error_handler_params, const char*, string)
#undef PREPARE_AUDIT_ITEM_UPDATE_PREPROC

void	zbx_audit_item_delete_preproc(zbx_uint64_t itemid, int item_flags, zbx_uint64_t preprocid)
{
	int	resource_type;
	char	audit_key_[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	resource_type = item_flag_to_resource_type(item_flags);

	ITEM_RESOURCE_KEY_RESOLVE_PREPROC(,)

	zbx_audit_update_json_delete(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_DELETE, audit_key_);
}

#define ITEM_RESOURCE_KEY_RESOLVE_TAG(resource, nested)								\
	if (AUDIT_RESOURCE_ITEM == resource_type)								\
	{													\
		zbx_snprintf(audit_key_##resource, sizeof(audit_key_##resource), "item.tag[" ZBX_FS_UI64	\
				"]"#nested#resource, tagid);							\
	}													\
	else if (AUDIT_RESOURCE_ITEM_PROTOTYPE == resource_type)						\
	{													\
		zbx_snprintf(audit_key_##resource, sizeof(audit_key_##resource), "itemprototype.tag["		\
				ZBX_FS_UI64 "]"#nested#resource, tagid);					\
	}													\
	else if (AUDIT_RESOURCE_DISCOVERY_RULE == resource_type)						\
	{													\
		zbx_snprintf(audit_key_##resource, sizeof(audit_key_##resource), "discoveryrule.tag["		\
				ZBX_FS_UI64 "]"#resource, tagid);						\
	}													\
	else													\
	{													\
		THIS_SHOULD_NEVER_HAPPEN;									\
		return;												\
	}

void	zbx_audit_item_update_json_add_item_tag(zbx_uint64_t itemid, zbx_uint64_t tagid, int item_flags,
		const char *tag, const char *value)
{
	int	resource_type;
	char	audit_key_[AUDIT_DETAILS_KEY_LEN], audit_key_tag[AUDIT_DETAILS_KEY_LEN],
		audit_key_value[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	resource_type = item_flag_to_resource_type(item_flags);

	ITEM_RESOURCE_KEY_RESOLVE_TAG(,)
	ITEM_RESOURCE_KEY_RESOLVE_TAG(tag, .)
	ITEM_RESOURCE_KEY_RESOLVE_TAG(value, .)

#define AUDIT_TABLE_NAME	"item_tag"
	zbx_audit_update_json_append_no_value(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_);
	zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_tag, tag,
			AUDIT_TABLE_NAME, "tag");
	zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_value, value,
			AUDIT_TABLE_NAME, "value");
#undef AUDIT_TABLE_NAME
}

void	zbx_audit_item_update_json_update_item_tag_create_entry(zbx_uint64_t itemid, int item_flags,
		zbx_uint64_t tagid)
{
	int	resource_type;
	char	audit_key_[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	resource_type = item_flag_to_resource_type(item_flags);

	ITEM_RESOURCE_KEY_RESOLVE_TAG(,)

	zbx_audit_update_json_append_no_value(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_UPDATE, audit_key_);
}

#define PREPARE_AUDIT_ITEM_UPDATE_TAG(resource, type1, type2)							\
void	zbx_audit_item_update_json_update_item_tag_##resource(zbx_uint64_t itemid, int item_flags,		\
		zbx_uint64_t tagid, type1 resource##_old, type1 resource##_new)					\
{														\
	int	resource_type;											\
	char	audit_key_##resource[AUDIT_DETAILS_KEY_LEN];							\
														\
	RETURN_IF_AUDIT_OFF();											\
	resource_type = item_flag_to_resource_type(item_flags);							\
														\
	ITEM_RESOURCE_KEY_RESOLVE_TAG(resource,.)								\
														\
	zbx_audit_update_json_update_##type2(itemid, AUDIT_ITEM_ID, audit_key_##resource, resource##_old,	\
			resource##_new);									\
}
PREPARE_AUDIT_ITEM_UPDATE_TAG(tag, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE_TAG(value, const char*, string)
#undef PREPARE_AUDIT_ITEM_UPDATE_TAG

void	zbx_audit_item_delete_tag(zbx_uint64_t itemid, int item_flags, zbx_uint64_t tagid)
{
	int	resource_type;
	char	audit_key_[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	resource_type = item_flag_to_resource_type(item_flags);

	ITEM_RESOURCE_KEY_RESOLVE_TAG(,)

	zbx_audit_update_json_delete(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_DELETE, audit_key_);
}

#define ITEM_RESOURCE_KEY_RESOLVE(resource, nested)								\
	if (AUDIT_RESOURCE_ITEM == resource_type)								\
	{													\
		zbx_snprintf(audit_key_##resource, sizeof(audit_key_##resource), "item.parameters[" ZBX_FS_UI64 \
				"]"#nested#resource, item_parameter_id);					\
	}													\
	else if (AUDIT_RESOURCE_ITEM_PROTOTYPE == resource_type)						\
	{													\
		zbx_snprintf(audit_key_##resource, sizeof(audit_key_##resource), "itemprototype.parameters["	\
				ZBX_FS_UI64 "]"#nested#resource, item_parameter_id);				\
	}													\
	else if (AUDIT_RESOURCE_DISCOVERY_RULE == resource_type)						\
	{													\
		zbx_snprintf(audit_key_##resource, sizeof(audit_key_##resource), "discoveryrule.parameters["	\
				ZBX_FS_UI64 "]"#resource, item_parameter_id);					\
	}													\
	else													\
	{													\
		THIS_SHOULD_NEVER_HAPPEN;									\
		return;												\
	}

void	zbx_audit_item_update_json_add_params(zbx_uint64_t itemid, int item_flags, zbx_uint64_t item_parameter_id,
		const char *name, const char *value)
{
	int	resource_type;
	char	audit_key_[AUDIT_DETAILS_KEY_LEN], audit_key_name[AUDIT_DETAILS_KEY_LEN],
		audit_key_value[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	resource_type = item_flag_to_resource_type(item_flags);

	ITEM_RESOURCE_KEY_RESOLVE(,)
	ITEM_RESOURCE_KEY_RESOLVE(name, .)
	ITEM_RESOURCE_KEY_RESOLVE(value, .)

#define AUDIT_TABLE_NAME	"item_parameter"
	zbx_audit_update_json_append_no_value(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_);
	zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_name, name,
			AUDIT_TABLE_NAME, "name");
	zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_value, value,
			AUDIT_TABLE_NAME, "value");
#undef AUDIT_TABLE_NAME
}

void	zbx_audit_item_update_json_update_params_create_entry(zbx_uint64_t itemid, int item_flags,
		zbx_uint64_t item_parameter_id)
{
	int	resource_type;
	char	audit_key_[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	resource_type = item_flag_to_resource_type(item_flags);

	ITEM_RESOURCE_KEY_RESOLVE(,)
	zbx_audit_update_json_append_no_value(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_UPDATE, audit_key_);
}

#define PREPARE_AUDIT_ITEM_PARAMS_UPDATE(resource)								\
void	zbx_audit_item_update_json_update_params_##resource(zbx_uint64_t itemid, int item_flags,		\
		zbx_uint64_t item_parameter_id, const char *resource##_orig, const char *resource)		\
{														\
	int	resource_type;											\
	char	audit_key_##resource[AUDIT_DETAILS_KEY_LEN];							\
														\
	RETURN_IF_AUDIT_OFF();											\
														\
	resource_type = item_flag_to_resource_type(item_flags);							\
	ITEM_RESOURCE_KEY_RESOLVE(resource, .)									\
														\
	zbx_audit_update_json_update_string(itemid, AUDIT_ITEM_ID, audit_key_##resource, resource##_orig,	\
			resource);										\
}

PREPARE_AUDIT_ITEM_PARAMS_UPDATE(name)
PREPARE_AUDIT_ITEM_PARAMS_UPDATE(value)

void	zbx_audit_item_delete_params(zbx_uint64_t itemid, int item_flags, zbx_uint64_t item_parameter_id)
{
	int	resource_type;
	char	audit_key_[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	resource_type = item_flag_to_resource_type(item_flags);

	ITEM_RESOURCE_KEY_RESOLVE(,)

	zbx_audit_update_json_delete(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_DELETE, audit_key_);
}

void	zbx_audit_discovery_rule_update_json_add_lld_macro_path(zbx_uint64_t itemid,
		zbx_uint64_t lld_macro_pathid, const char *lld_macro, const char *path)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_lld_macro[AUDIT_DETAILS_KEY_LEN],
		audit_key_path[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	zbx_snprintf(audit_key, sizeof(audit_key),
			"discoveryrule.lld_macro_paths[" ZBX_FS_UI64 "]", lld_macro_pathid);
	zbx_snprintf(audit_key_lld_macro, sizeof(audit_key_lld_macro),
			"discoveryrule.lld_macro_paths[" ZBX_FS_UI64 "].lld_macro", lld_macro_pathid);
	zbx_snprintf(audit_key_path, sizeof(audit_key_lld_macro),
			"discoveryrule.lld_macro_paths[" ZBX_FS_UI64 "].path", lld_macro_pathid);

#define AUDIT_TABLE_NAME	"lld_macro_path"
	zbx_audit_update_json_append_no_value(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key);
	zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_lld_macro,
			lld_macro, AUDIT_TABLE_NAME, "lld_macro");
	zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_path, path,
			AUDIT_TABLE_NAME, "path");
#undef AUDIT_TABLE_NAME
}

void	zbx_audit_discovery_rule_update_json_lld_macro_path_create_update_entry(zbx_uint64_t itemid,
		zbx_uint64_t lld_macro_pathid)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	zbx_snprintf(buf, sizeof(buf), "discoveryrule.lld_macro_paths[" ZBX_FS_UI64 "]", lld_macro_pathid);

	zbx_audit_update_json_append_no_value(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_UPDATE, buf);
}

#define PREPARE_AUDIT_DISCOVERY_RULE_UPDATE_LLD_MACRO_PATH(resource)						\
void	zbx_audit_discovery_rule_update_json_update_lld_macro_path_##resource(zbx_uint64_t itemid,		\
		zbx_uint64_t lld_macro_pathid, const char *resource##_old, const char *resource##_new)		\
{														\
	char	audit_key_##resource[AUDIT_DETAILS_KEY_LEN];							\
														\
	RETURN_IF_AUDIT_OFF();											\
														\
	zbx_snprintf(audit_key_##resource, sizeof(audit_key_##resource),					\
			"discoveryrule.lld_macro_paths[" ZBX_FS_UI64 "]."#resource, lld_macro_pathid);		\
														\
	zbx_audit_update_json_update_string(itemid, AUDIT_ITEM_ID, audit_key_##resource, resource##_old,	\
			resource##_new);									\
}
PREPARE_AUDIT_DISCOVERY_RULE_UPDATE_LLD_MACRO_PATH(lld_macro)
PREPARE_AUDIT_DISCOVERY_RULE_UPDATE_LLD_MACRO_PATH(path)
#undef PREPARE_AUDIT_DISCOVERY_RULE_UPDATE_LLD_MACRO_PATH

void	zbx_audit_discovery_rule_update_json_delete_lld_macro_path(zbx_uint64_t itemid,
		zbx_uint64_t lld_macro_pathid)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	zbx_snprintf(buf, sizeof(buf),"discoveryrule.lld_macro_paths[" ZBX_FS_UI64 "]", lld_macro_pathid);

	zbx_audit_update_json_delete(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_DELETE, buf);
}

void	zbx_audit_discovery_rule_update_json_add_lld_override(zbx_uint64_t itemid, zbx_uint64_t overrideid,
		const char *name, int step, int stop)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_name[AUDIT_DETAILS_KEY_LEN],
		audit_key_step[AUDIT_DETAILS_KEY_LEN], audit_key_stop[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	zbx_snprintf(audit_key, sizeof(audit_key), "discoveryrule.overrides[" ZBX_FS_UI64 "]", overrideid);
	zbx_snprintf(audit_key_name, sizeof(audit_key_name), "discoveryrule.overrides[" ZBX_FS_UI64 "].name",
			overrideid);
	zbx_snprintf(audit_key_step, sizeof(audit_key_step), "discoveryrule.overrides[" ZBX_FS_UI64 "].step",
			overrideid);
	zbx_snprintf(audit_key_stop, sizeof(audit_key_stop), "discoveryrule.overrides[" ZBX_FS_UI64 "].stop",
			overrideid);

#define AUDIT_TABLE_NAME	"lld_override"
	zbx_audit_update_json_append_no_value(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key);
	zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_name, name,
			AUDIT_TABLE_NAME, "name");
	zbx_audit_update_json_append_int(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_step, step,
			AUDIT_TABLE_NAME, "step");
	zbx_audit_update_json_append_int(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_stop, stop,
			AUDIT_TABLE_NAME, "stop");
#undef AUDIT_TABLE_NAME
}

void	zbx_audit_discovery_rule_update_json_delete_lld_override(zbx_uint64_t itemid, zbx_uint64_t overrideid)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	zbx_snprintf(buf, sizeof(buf), "discoveryrule.overrides[" ZBX_FS_UI64 "]", overrideid);

	zbx_audit_update_json_append_no_value(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_DELETE, buf);
}

void	zbx_audit_discovery_rule_update_json_add_lld_override_filter(zbx_uint64_t itemid, zbx_uint64_t overrideid,
		int evaltype, const char *formula)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_evaltype[AUDIT_DETAILS_KEY_LEN],
		audit_key_formula[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	zbx_snprintf(audit_key, sizeof(audit_key), "discoveryrule.overrides[" ZBX_FS_UI64 "].filter", overrideid);

	zbx_snprintf(audit_key_evaltype, sizeof(audit_key_evaltype), "discoveryrule.overrides[" ZBX_FS_UI64
			"].filter.evaltype", overrideid);

	zbx_snprintf(audit_key_formula, sizeof(audit_key_formula), "discoveryrule.overrides[" ZBX_FS_UI64
			"].filter.formula", overrideid);

#define	AUDIT_TABLE_NAME	"lld_override"
	zbx_audit_update_json_append_no_value(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key);
	zbx_audit_update_json_append_int(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_evaltype, evaltype,
			AUDIT_TABLE_NAME, "evaltype");
	zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_formula, formula,
			AUDIT_TABLE_NAME, "formula");
#undef 	AUDIT_TABLE_NAME
}

void	zbx_audit_discovery_rule_update_json_add_lld_override_condition(zbx_uint64_t itemid, zbx_uint64_t overrideid,
		zbx_uint64_t override_conditionid, int condition_operator, const char *macro, const char *value)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_operator[AUDIT_DETAILS_KEY_LEN],
		audit_key_macro[AUDIT_DETAILS_KEY_LEN], audit_key_value[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	zbx_snprintf(audit_key, sizeof(audit_key), "discoveryrule.overrides[" ZBX_FS_UI64
			"].conditions[" ZBX_FS_UI64 "]", overrideid, override_conditionid);
	zbx_snprintf(audit_key_operator, sizeof(audit_key_operator), "discoveryrule.overrides[" ZBX_FS_UI64
			"].conditions[" ZBX_FS_UI64 "].operator", overrideid, override_conditionid);
	zbx_snprintf(audit_key_macro, sizeof(audit_key_macro), "discoveryrule.overrides[" ZBX_FS_UI64 "].conditions["
			ZBX_FS_UI64 "].macro", overrideid, override_conditionid);
	zbx_snprintf(audit_key_value, sizeof(audit_key_value), "discoveryrule.overrides[" ZBX_FS_UI64 "].conditions["
			ZBX_FS_UI64 "].value", overrideid, override_conditionid);

#define AUDIT_TABLE_NAME	"lld_override_condition"
	zbx_audit_update_json_append_no_value(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key);
	zbx_audit_update_json_append_int(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_operator,
			condition_operator, AUDIT_TABLE_NAME, "operator");
	zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_macro, macro,
			AUDIT_TABLE_NAME, "macro");
	zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_value, value,
			AUDIT_TABLE_NAME, "value");
#undef AUDIT_TABLE_NAME
}

void	zbx_audit_discovery_rule_update_json_add_lld_override_operation(zbx_uint64_t itemid, zbx_uint64_t overrideid,
		zbx_uint64_t override_operationid, int condition_operator, const char *value)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_operator[AUDIT_DETAILS_KEY_LEN],
		audit_key_value[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	zbx_snprintf(audit_key, sizeof(audit_key), "discoveryrule.overrides[" ZBX_FS_UI64 "].operations[" ZBX_FS_UI64
			"]", overrideid, override_operationid);
	zbx_snprintf(audit_key_operator, sizeof(audit_key_operator), "discoveryrule.overrides[" ZBX_FS_UI64
			"].operations[" ZBX_FS_UI64 "].operator", overrideid, override_operationid);
	zbx_snprintf(audit_key_value, sizeof(audit_key_value), "discoveryrule.overrides[" ZBX_FS_UI64 "].operations["
			ZBX_FS_UI64 "].value", overrideid, override_operationid);

#define	AUDIT_TABLE_NAME	"lld_override_operation"
	zbx_audit_update_json_append_no_value(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key);
	zbx_audit_update_json_append_int(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_operator,
			condition_operator, AUDIT_TABLE_NAME, "operator");
	zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_value, value,
			AUDIT_TABLE_NAME, "value");
#undef AUDIT_TABLE_NAME
}

#define PREPARE_AUDIT_DISCOVERY_RULE_OVERRIDE_ADD(resource, type, type2, table, field)				\
void	zbx_audit_discovery_rule_update_json_add_lld_override_##resource(zbx_uint64_t itemid,			\
		zbx_uint64_t overrideid, zbx_uint64_t resource##_id, type resource)				\
{														\
	char	buf[AUDIT_DETAILS_KEY_LEN];									\
														\
	RETURN_IF_AUDIT_OFF();											\
														\
	zbx_snprintf(buf, sizeof(buf), "discoveryrule.overrides[" ZBX_FS_UI64					\
			"].operations[" ZBX_FS_UI64 "]."#resource, overrideid, resource##_id);			\
														\
	zbx_audit_update_json_append_##type2(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, buf, resource,	\
			table, field);										\
}

PREPARE_AUDIT_DISCOVERY_RULE_OVERRIDE_ADD(opstatus, int, int, "lld_override_opstatus", "status")
PREPARE_AUDIT_DISCOVERY_RULE_OVERRIDE_ADD(opdiscover, int, int, "lld_override_opdiscover", "discover")
PREPARE_AUDIT_DISCOVERY_RULE_OVERRIDE_ADD(opperiod, const char*, string, "lld_override_opperiod", "delay")
PREPARE_AUDIT_DISCOVERY_RULE_OVERRIDE_ADD(optrends, const char*, string, "lld_override_optrends", "trends")
PREPARE_AUDIT_DISCOVERY_RULE_OVERRIDE_ADD(ophistory, const char*, string, "lld_override_ophistory", "history")
PREPARE_AUDIT_DISCOVERY_RULE_OVERRIDE_ADD(opseverity, int, int, "lld_override_opseverity", "severity")
PREPARE_AUDIT_DISCOVERY_RULE_OVERRIDE_ADD(opinventory, int, int, "lld_override_opinventory", "inventory_mode")

void	zbx_audit_discovery_rule_update_json_add_lld_override_optag(zbx_uint64_t itemid, zbx_uint64_t overrideid,
		zbx_uint64_t lld_override_optagid, const char *tag, const char *value)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_tag[AUDIT_DETAILS_KEY_LEN],
		audit_key_value[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	zbx_snprintf(audit_key, sizeof(audit_key), "discoveryrule.overrides[" ZBX_FS_UI64
			"].optag[" ZBX_FS_UI64 "]", overrideid, lld_override_optagid);
	zbx_snprintf(audit_key_tag, sizeof(audit_key_tag), "discoveryrule.overrides[" ZBX_FS_UI64
			"].optag[" ZBX_FS_UI64 "].tag", overrideid, lld_override_optagid);
	zbx_snprintf(audit_key_value, sizeof(audit_key_value), "discoveryrule.overrides[" ZBX_FS_UI64
			"].optag[" ZBX_FS_UI64 "].value", overrideid, lld_override_optagid);

#define	AUDIT_TABLE_NAME	"lld_override_optag"
	zbx_audit_update_json_append_no_value(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key);
	zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_tag, tag,
			AUDIT_TABLE_NAME, "tag");
	zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_value, value,
			AUDIT_TABLE_NAME, "value");
#undef AUDIT_TABLE_NAME
}

void	zbx_audit_discovery_rule_update_json_add_lld_override_optemplate(zbx_uint64_t itemid, zbx_uint64_t overrideid,
		zbx_uint64_t lld_override_optemplateid, zbx_uint64_t templateid)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_templateid[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	zbx_snprintf(audit_key, sizeof(audit_key), "discoveryrule.overrides[" ZBX_FS_UI64 "].optemplateid[" ZBX_FS_UI64
			"]", overrideid, lld_override_optemplateid);
	zbx_snprintf(audit_key_templateid, sizeof(audit_key_templateid), "discoveryrule.overrides[" ZBX_FS_UI64
			"].optemplateid[" ZBX_FS_UI64 "].templateid", overrideid, lld_override_optemplateid);

	zbx_audit_update_json_append_no_value(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key);
	zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_templateid,
			templateid, "lld_override_optemplate", "templateid");
}
