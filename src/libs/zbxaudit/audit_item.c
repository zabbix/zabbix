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

#include "audit/zbxaudit_item.h"
#include "audit/zbxaudit.h"

#include "audit.h"

#include "zbxdb.h"
#include "zbxnum.h"
#include "zbxalgo.h"
#include "zbxstr.h"

int	zbx_audit_item_resource_is_only_item(int resource_type)
{
	return ZBX_AUDIT_RESOURCE_ITEM == resource_type;
}

int	zbx_audit_item_resource_is_only_item_prototype(int resource_type)
{
	return ZBX_AUDIT_RESOURCE_ITEM_PROTOTYPE == resource_type;
}

int	zbx_audit_item_resource_is_only_item_and_item_prototype(int resource_type)
{
	return (ZBX_AUDIT_RESOURCE_ITEM == resource_type || ZBX_AUDIT_RESOURCE_ITEM_PROTOTYPE == resource_type);
}

int	zbx_audit_item_resource_is_only_lld_rule(int resource_type)
{
	return	ZBX_AUDIT_RESOURCE_LLD_RULE == resource_type;
}

int	zbx_audit_item_flag_to_resource_type(int flag)
{
	if (ZBX_FLAG_DISCOVERY_NORMAL == flag || ZBX_FLAG_DISCOVERY_CREATED == flag)
	{
		return ZBX_AUDIT_RESOURCE_ITEM;
	}
	else if (ZBX_FLAG_DISCOVERY_PROTOTYPE == flag)
	{
		return ZBX_AUDIT_RESOURCE_ITEM_PROTOTYPE;
	}
	else if (ZBX_FLAG_DISCOVERY_RULE == flag)
	{
		return ZBX_AUDIT_RESOURCE_LLD_RULE;
	}
	else
	{
		zabbix_log(LOG_LEVEL_CRIT, "unexpected audit detected: ->%d<-", flag);
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}
}

void	zbx_audit_item_create_entry(int audit_context_mode, int audit_action, zbx_uint64_t itemid, const char *name,
		int flags)
{
	int	resource_type;

	zbx_audit_entry_t	local_audit_item_entry, **found_audit_item_entry;
	zbx_audit_entry_t	*local_audit_item_entry_x = &local_audit_item_entry;

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	resource_type = zbx_audit_item_flag_to_resource_type(flags);

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

#define PREPARE_AUDIT_ITEM_UPDATE(resource, type1, type2)							\
void	zbx_audit_item_update_json_update_##resource(int audit_context_mode, zbx_uint64_t itemid, int flags,	\
		type1 resource##_old, type1 resource##_new)							\
{														\
	int	resource_type;											\
														\
	RETURN_IF_AUDIT_OFF(audit_context_mode);								\
														\
	resource_type = zbx_audit_item_flag_to_resource_type(flags);						\
	zbx_audit_update_json_update_##type2(itemid, AUDIT_ITEM_ID, ZBX_AUDIT_IT_OR_ITP_OR_DR(resource),	\
			resource##_old,	resource##_new);							\
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
PREPARE_AUDIT_ITEM_UPDATE(lifetime_type,	int,		int)
PREPARE_AUDIT_ITEM_UPDATE(enabled_lifetime,	const char*,	string)
PREPARE_AUDIT_ITEM_UPDATE(enabled_lifetime_type,	int,	int)
PREPARE_AUDIT_ITEM_UPDATE(evaltype,		int,		int)
PREPARE_AUDIT_ITEM_UPDATE(jmx_endpoint,		const char*,	string)
PREPARE_AUDIT_ITEM_UPDATE(master_itemid,	zbx_uint64_t,	uint64)
PREPARE_AUDIT_ITEM_UPDATE(timeout,		const char*,	string)
PREPARE_AUDIT_ITEM_UPDATE(url,			const char*,	string)
PREPARE_AUDIT_ITEM_UPDATE(posts,		const char*,	string)
PREPARE_AUDIT_ITEM_UPDATE(status_codes,		const char*,	string)
PREPARE_AUDIT_ITEM_UPDATE(follow_redirects,	int,		int)
PREPARE_AUDIT_ITEM_UPDATE(redirects,		int,		int)
PREPARE_AUDIT_ITEM_UPDATE(post_type,		int,		int)
PREPARE_AUDIT_ITEM_UPDATE(http_proxy,		const char*,	string)
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
PREPARE_AUDIT_ITEM_UPDATE(key_,			const char*,	string)
#undef PREPARE_AUDIT_ITEM_UPDATE

/********************************************************************************
 *                                                                              *
 * Purpose: create audit events for items that are to be removed                *
 *                                                                              *
 ********************************************************************************/
void	zbx_audit_item_delete(int audit_context_mode, zbx_vector_uint64_t *itemids)
{
	zbx_db_large_query_t	query;
	zbx_db_row_t		row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "select itemid,name,flags from items where");
	zbx_db_large_query_prepare_uint(&query, &sql, &sql_alloc, &sql_offset, "itemid", itemids);

	while (NULL != (row = zbx_db_large_query_fetch(&query)))
	{
		zbx_uint64_t	itemid;

		ZBX_STR2UINT64(itemid, row[0]);

		zbx_audit_item_create_entry(audit_context_mode, ZBX_AUDIT_ACTION_DELETE, itemid, row[1], atoi(row[2]));
	}
	zbx_db_large_query_clear(&query);
	zbx_free(sql);
}

void	zbx_audit_discovery_rule_update_json_add_filter_conditions(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t rule_conditionid, zbx_uint64_t op, const char *macro, const char *value)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_operator[AUDIT_DETAILS_KEY_LEN],
		audit_key_macro[AUDIT_DETAILS_KEY_LEN], audit_key_value[AUDIT_DETAILS_KEY_LEN],
		audit_key_op[AUDIT_DETAILS_KEY_LEN], audit_key_item_conditionid[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(audit_key_op, sizeof(audit_key_op), "discoveryrule.filter");
	zbx_snprintf(audit_key_item_conditionid, sizeof(audit_key_item_conditionid),
			"discoveryrule.filter.conditions[" ZBX_FS_UI64 "].item_conditionid", rule_conditionid);
	zbx_snprintf(audit_key, sizeof(audit_key),
			"discoveryrule.filter.conditions[" ZBX_FS_UI64 "]", rule_conditionid);
	zbx_snprintf(audit_key_operator, sizeof(audit_key_operator),
			"discoveryrule.filter.conditions[" ZBX_FS_UI64 "].operator", rule_conditionid);
	zbx_snprintf(audit_key_macro, sizeof(audit_key_macro),
			"discoveryrule.filter.conditions[" ZBX_FS_UI64 "].macro", rule_conditionid);
	zbx_snprintf(audit_key_value, sizeof(audit_key_value),
			"discoveryrule.filter.conditions[" ZBX_FS_UI64 "]value", rule_conditionid);

#define	AUDIT_TABLE_NAME	"item_condition"
	zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_op,
			"Added", NULL, NULL);
	zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_item_conditionid,
			rule_conditionid, NULL, NULL);
	zbx_audit_update_json_append_no_value(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key);
	zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_operator, op,
			AUDIT_TABLE_NAME, "operator");
	zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_macro, macro,
			AUDIT_TABLE_NAME, "macro");
	zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_value, value,
			AUDIT_TABLE_NAME, "value");
#undef AUDIT_TABLE_NAME
}

void	zbx_audit_discovery_rule_update_json_update_filter_conditions_create_entry(int audit_context_mode,
		zbx_uint64_t itemid, zbx_uint64_t item_conditionid)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(buf, sizeof(buf), "discoveryrule.filter[" ZBX_FS_UI64 "].conditions", item_conditionid);

	zbx_audit_update_json_append_no_value(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_UPDATE, buf);
}

#define PREPARE_AUDIT_DISCOVERY_RULE_UPDATE(resource, type1, type2)						\
void	zbx_audit_discovery_rule_update_json_update_filter_conditions_##resource(int audit_context_mode,	\
		zbx_uint64_t itemid, zbx_uint64_t item_conditionid, type1 resource##_old, type1 resource##_new)	\
{														\
	char	buf[AUDIT_DETAILS_KEY_LEN];									\
														\
	RETURN_IF_AUDIT_OFF(audit_context_mode);								\
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

void	zbx_audit_discovery_rule_update_json_delete_filter_conditions(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t item_conditionid)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(buf, sizeof(buf), "discoveryrule.filter[" ZBX_FS_UI64 "].conditions", item_conditionid);

	zbx_audit_update_json_delete(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_DELETE, buf);
}

#define ITEM_RESOURCE_KEY_RESOLVE_PREPROC(resource, nested)							\
	if (ZBX_AUDIT_RESOURCE_ITEM == resource_type)								\
	{													\
		zbx_snprintf(audit_key_##resource, sizeof(audit_key_##resource), "item.preprocessing["		\
				ZBX_FS_UI64 "]"#nested#resource, preprocid);					\
	}													\
	else if (ZBX_AUDIT_RESOURCE_ITEM_PROTOTYPE == resource_type)						\
	{													\
		zbx_snprintf(audit_key_##resource, sizeof(audit_key_##resource), "itemprototype.preprocessing["	\
				ZBX_FS_UI64 "]"#nested#resource, preprocid);					\
	}													\
	else if (ZBX_AUDIT_RESOURCE_LLD_RULE == resource_type)							\
	{													\
		zbx_snprintf(audit_key_##resource, sizeof(audit_key_##resource), "discoveryrule.preprocessing["	\
				ZBX_FS_UI64 "]"#nested#resource, preprocid);					\
	}													\
	else													\
	{													\
		THIS_SHOULD_NEVER_HAPPEN;									\
		return;												\
	}

void	zbx_audit_item_update_json_add_item_preproc(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t preprocid, int item_flags, int step, int type, const char *params, int error_handler,
		const char *error_handler_params)
{
	int	resource_type;
	char	audit_key_[AUDIT_DETAILS_KEY_LEN], audit_key_step[AUDIT_DETAILS_KEY_LEN],
		audit_key_type[AUDIT_DETAILS_KEY_LEN], audit_key_params[AUDIT_DETAILS_KEY_LEN],
		audit_key_error_handler[AUDIT_DETAILS_KEY_LEN], audit_key_error_handler_params[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	resource_type = zbx_audit_item_flag_to_resource_type(item_flags);

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

void	zbx_audit_item_update_json_update_item_preproc_create_entry(int audit_context_mode, zbx_uint64_t itemid,
		int item_flags, zbx_uint64_t preprocid)
{
	int	resource_type;
	char	audit_key_[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	resource_type = zbx_audit_item_flag_to_resource_type(item_flags);

	ITEM_RESOURCE_KEY_RESOLVE_PREPROC(,)

	zbx_audit_update_json_append_no_value(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_UPDATE, audit_key_);
}

#define PREPARE_AUDIT_ITEM_UPDATE_PREPROC(resource, type1, type2)						\
void	zbx_audit_item_update_json_update_item_preproc_##resource(int audit_context_mode, zbx_uint64_t itemid,	\
		int item_flags, zbx_uint64_t preprocid, type1 resource##_old, type1 resource##_new)		\
{														\
	int	resource_type;											\
	char	audit_key_##resource[AUDIT_DETAILS_KEY_LEN];							\
														\
	RETURN_IF_AUDIT_OFF(audit_context_mode);								\
	resource_type = zbx_audit_item_flag_to_resource_type(item_flags);					\
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

void	zbx_audit_item_delete_preproc(int audit_context_mode, zbx_uint64_t itemid, int item_flags,
		zbx_uint64_t preprocid)
{
	int	resource_type;
	char	audit_key_[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	resource_type = zbx_audit_item_flag_to_resource_type(item_flags);

	ITEM_RESOURCE_KEY_RESOLVE_PREPROC(,)

	zbx_audit_update_json_delete(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_DELETE, audit_key_);
}

#define ITEM_RESOURCE_KEY_RESOLVE_TAG(resource, nested)								\
	if (ZBX_AUDIT_RESOURCE_ITEM == resource_type)								\
	{													\
		zbx_snprintf(audit_key_##resource, sizeof(audit_key_##resource), "item.tag[" ZBX_FS_UI64	\
				"]"#nested#resource, tagid);							\
	}													\
	else if (ZBX_AUDIT_RESOURCE_ITEM_PROTOTYPE == resource_type)						\
	{													\
		zbx_snprintf(audit_key_##resource, sizeof(audit_key_##resource), "itemprototype.tag["		\
				ZBX_FS_UI64 "]"#nested#resource, tagid);					\
	}													\
	else if (ZBX_AUDIT_RESOURCE_LLD_RULE == resource_type)							\
	{													\
		zbx_snprintf(audit_key_##resource, sizeof(audit_key_##resource), "discoveryrule.tag["		\
				ZBX_FS_UI64 "]"#resource, tagid);						\
	}													\
	else													\
	{													\
		THIS_SHOULD_NEVER_HAPPEN;									\
		return;												\
	}

void	zbx_audit_item_update_json_add_item_tag(int audit_context_mode, zbx_uint64_t itemid, zbx_uint64_t tagid,
		int item_flags, const char *tag, const char *value)
{
	int	resource_type;
	char	audit_key_[AUDIT_DETAILS_KEY_LEN], audit_key_tag[AUDIT_DETAILS_KEY_LEN],
		audit_key_value[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	resource_type = zbx_audit_item_flag_to_resource_type(item_flags);

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

void	zbx_audit_item_update_json_update_item_tag_create_entry(int audit_context_mode, zbx_uint64_t itemid,
		int item_flags, zbx_uint64_t tagid)
{
	int	resource_type;
	char	audit_key_[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	resource_type = zbx_audit_item_flag_to_resource_type(item_flags);

	ITEM_RESOURCE_KEY_RESOLVE_TAG(,)

	zbx_audit_update_json_append_no_value(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_UPDATE, audit_key_);
}

#define PREPARE_AUDIT_ITEM_UPDATE_TAG(resource, type1, type2)							\
void	zbx_audit_item_update_json_update_item_tag_##resource(int audit_context_mode, zbx_uint64_t itemid,	\
		int item_flags, zbx_uint64_t tagid, type1 resource##_old, type1 resource##_new)			\
{														\
	int	resource_type;											\
	char	audit_key_##resource[AUDIT_DETAILS_KEY_LEN];							\
														\
	RETURN_IF_AUDIT_OFF(audit_context_mode);								\
	resource_type = zbx_audit_item_flag_to_resource_type(item_flags);					\
														\
	ITEM_RESOURCE_KEY_RESOLVE_TAG(resource,.)								\
														\
	zbx_audit_update_json_update_##type2(itemid, AUDIT_ITEM_ID, audit_key_##resource, resource##_old,	\
			resource##_new);									\
}
PREPARE_AUDIT_ITEM_UPDATE_TAG(tag, const char*, string)
PREPARE_AUDIT_ITEM_UPDATE_TAG(value, const char*, string)
#undef PREPARE_AUDIT_ITEM_UPDATE_TAG

void	zbx_audit_item_delete_tag(int audit_context_mode, zbx_uint64_t itemid, int item_flags, zbx_uint64_t tagid)
{
	int	resource_type;
	char	audit_key_[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	resource_type = zbx_audit_item_flag_to_resource_type(item_flags);

	ITEM_RESOURCE_KEY_RESOLVE_TAG(,)

	zbx_audit_update_json_delete(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_DELETE, audit_key_);
}

#define ITEM_RESOURCE_KEY_RESOLVE(resource, nested)								\
	if (ZBX_AUDIT_RESOURCE_ITEM == resource_type)								\
	{													\
		zbx_snprintf(audit_key_##resource, sizeof(audit_key_##resource), "item.parameters[" ZBX_FS_UI64 \
				"]"#nested#resource, item_parameter_id);					\
	}													\
	else if (ZBX_AUDIT_RESOURCE_ITEM_PROTOTYPE == resource_type)						\
	{													\
		zbx_snprintf(audit_key_##resource, sizeof(audit_key_##resource), "itemprototype.parameters["	\
				ZBX_FS_UI64 "]"#nested#resource, item_parameter_id);				\
	}													\
	else if (ZBX_AUDIT_RESOURCE_LLD_RULE == resource_type)							\
	{													\
		zbx_snprintf(audit_key_##resource, sizeof(audit_key_##resource), "discoveryrule.parameters["	\
				ZBX_FS_UI64 "]"#resource, item_parameter_id);					\
	}													\
	else													\
	{													\
		THIS_SHOULD_NEVER_HAPPEN;									\
		return;												\
	}

void	zbx_audit_item_update_json_add_params(int audit_context_mode, zbx_uint64_t itemid, int item_flags,
		zbx_uint64_t item_parameter_id, const char *name, const char *value)
{
	int	resource_type;
	char	audit_key_[AUDIT_DETAILS_KEY_LEN], audit_key_name[AUDIT_DETAILS_KEY_LEN],
		audit_key_value[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	resource_type = zbx_audit_item_flag_to_resource_type(item_flags);

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

void	zbx_audit_item_update_json_update_params_create_entry(int audit_context_mode, zbx_uint64_t itemid,
		int item_flags, zbx_uint64_t item_parameter_id)
{
	int	resource_type;
	char	audit_key_[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	resource_type = zbx_audit_item_flag_to_resource_type(item_flags);

	ITEM_RESOURCE_KEY_RESOLVE(,)
	zbx_audit_update_json_append_no_value(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_UPDATE, audit_key_);
}

#define PREPARE_AUDIT_ITEM_PARAMS_UPDATE(resource)								\
void	zbx_audit_item_update_json_update_params_##resource(int audit_context_mode, zbx_uint64_t itemid,	\
		int item_flags, zbx_uint64_t item_parameter_id, const char *resource##_orig,			\
		const char *resource)										\
{														\
	int	resource_type;											\
	char	audit_key_##resource[AUDIT_DETAILS_KEY_LEN];							\
														\
	RETURN_IF_AUDIT_OFF(audit_context_mode);								\
														\
	resource_type = zbx_audit_item_flag_to_resource_type(item_flags);					\
	ITEM_RESOURCE_KEY_RESOLVE(resource, .)									\
														\
	zbx_audit_update_json_update_string(itemid, AUDIT_ITEM_ID, audit_key_##resource, resource##_orig,	\
			resource);										\
}

PREPARE_AUDIT_ITEM_PARAMS_UPDATE(name)
PREPARE_AUDIT_ITEM_PARAMS_UPDATE(value)

void	zbx_audit_item_delete_params(int audit_context_mode, zbx_uint64_t itemid, int item_flags,
		zbx_uint64_t item_parameter_id)
{
	int	resource_type;
	char	audit_key_[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	resource_type = zbx_audit_item_flag_to_resource_type(item_flags);

	ITEM_RESOURCE_KEY_RESOLVE(,)

	zbx_audit_update_json_delete(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_DELETE, audit_key_);
}

void	zbx_audit_discovery_rule_update_json_add_lld_macro_path(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t lld_macro_pathid, const char *lld_macro, const char *path)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_lld_macro[AUDIT_DETAILS_KEY_LEN],
		audit_key_path[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(audit_key, sizeof(audit_key),
			"discoveryrule.lld_macro_paths[" ZBX_FS_UI64 "]", lld_macro_pathid);
	zbx_snprintf(audit_key_lld_macro, sizeof(audit_key_lld_macro),
			"discoveryrule.lld_macro_paths[" ZBX_FS_UI64 "].lld_macro", lld_macro_pathid);
	zbx_snprintf(audit_key_path, sizeof(audit_key_path),
			"discoveryrule.lld_macro_paths[" ZBX_FS_UI64 "].path", lld_macro_pathid);

#define AUDIT_TABLE_NAME	"lld_macro_path"
	zbx_audit_update_json_append_no_value(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key);
	zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_lld_macro,
			lld_macro, AUDIT_TABLE_NAME, "lld_macro");
	zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_path, path,
			AUDIT_TABLE_NAME, "path");
#undef AUDIT_TABLE_NAME
}

void	zbx_audit_discovery_rule_update_json_lld_macro_path_create_update_entry(int audit_context_mode,
		zbx_uint64_t itemid, zbx_uint64_t lld_macro_pathid)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(buf, sizeof(buf), "discoveryrule.lld_macro_paths[" ZBX_FS_UI64 "]", lld_macro_pathid);

	zbx_audit_update_json_append_no_value(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_UPDATE, buf);
}

#define PREPARE_AUDIT_DISCOVERY_RULE_UPDATE_LLD_MACRO_PATH(resource)						\
void	zbx_audit_discovery_rule_update_json_update_lld_macro_path_##resource(int audit_context_mode,		\
		zbx_uint64_t itemid, zbx_uint64_t lld_macro_pathid, const char *resource##_old,			\
		const char *resource##_new)									\
{														\
	char	audit_key_##resource[AUDIT_DETAILS_KEY_LEN];							\
														\
	RETURN_IF_AUDIT_OFF(audit_context_mode);								\
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

void	zbx_audit_discovery_rule_update_json_delete_lld_macro_path(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t lld_macro_pathid)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(buf, sizeof(buf),"discoveryrule.lld_macro_paths[" ZBX_FS_UI64 "]", lld_macro_pathid);

	zbx_audit_update_json_delete(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_DELETE, buf);
}

void	zbx_audit_discovery_rule_update_json_add_lld_override(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t overrideid, const char *name, int step, int stop)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_name[AUDIT_DETAILS_KEY_LEN],
		audit_key_step[AUDIT_DETAILS_KEY_LEN], audit_key_stop[AUDIT_DETAILS_KEY_LEN],
		audit_key_lld_overrideid[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(audit_key, sizeof(audit_key), "discoveryrule.overrides[" ZBX_FS_UI64 "]", overrideid);
	zbx_snprintf(audit_key_lld_overrideid, sizeof(audit_key_lld_overrideid), "discoveryrule.overrides[" ZBX_FS_UI64
			"].lld_overrideid", overrideid);
	zbx_snprintf(audit_key_name, sizeof(audit_key_name), "discoveryrule.overrides[" ZBX_FS_UI64 "].name",
			overrideid);
	zbx_snprintf(audit_key_step, sizeof(audit_key_step), "discoveryrule.overrides[" ZBX_FS_UI64 "].step",
			overrideid);
	zbx_snprintf(audit_key_stop, sizeof(audit_key_stop), "discoveryrule.overrides[" ZBX_FS_UI64 "].stop",
			overrideid);

#define AUDIT_TABLE_NAME	"lld_override"
	zbx_audit_update_json_append_no_value(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key);

	zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
			audit_key_lld_overrideid, overrideid, NULL, NULL);
	zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_name, name,
			AUDIT_TABLE_NAME, "name");
	zbx_audit_update_json_append_int(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_step, step,
			AUDIT_TABLE_NAME, "step");
	zbx_audit_update_json_append_int(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_stop, stop,
			AUDIT_TABLE_NAME, "stop");
#undef AUDIT_TABLE_NAME
}

void	zbx_audit_discovery_rule_update_json_delete_lld_override(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t overrideid)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(buf, sizeof(buf), "discoveryrule.overrides[" ZBX_FS_UI64 "]", overrideid);

	zbx_audit_update_json_append_no_value(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_DELETE, buf);
}

void	zbx_audit_discovery_rule_update_json_add_lld_override_filter(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t overrideid, int evaltype, const char *formula)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_evaltype[AUDIT_DETAILS_KEY_LEN],
		audit_key_formula[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

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

void	zbx_audit_discovery_rule_update_json_add_lld_override_condition(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t overrideid, zbx_uint64_t override_conditionid, int condition_operator, const char *macro,
		const char *value)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_operator[AUDIT_DETAILS_KEY_LEN],
		audit_key_macro[AUDIT_DETAILS_KEY_LEN], audit_key_value[AUDIT_DETAILS_KEY_LEN],
		audit_key_lld_override_conditionid[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(audit_key, sizeof(audit_key), "discoveryrule.overrides[" ZBX_FS_UI64
			"].filter.conditions[" ZBX_FS_UI64 "]", overrideid, override_conditionid);
	zbx_snprintf(audit_key_operator, sizeof(audit_key_operator), "discoveryrule.overrides[" ZBX_FS_UI64
			"].filter.conditions[" ZBX_FS_UI64 "].operator", overrideid, override_conditionid);
	zbx_snprintf(audit_key_macro, sizeof(audit_key_macro), "discoveryrule.overrides[" ZBX_FS_UI64
			"].filter.conditions["ZBX_FS_UI64 "].macro", overrideid, override_conditionid);
	zbx_snprintf(audit_key_value, sizeof(audit_key_value), "discoveryrule.overrides[" ZBX_FS_UI64
			"].filter.conditions[" ZBX_FS_UI64 "].value", overrideid, override_conditionid);
	zbx_snprintf(audit_key_lld_override_conditionid, sizeof(audit_key_lld_override_conditionid),
			"discoveryrule.overrides[" ZBX_FS_UI64 "].filter.conditions[" ZBX_FS_UI64
			"].lld_override_conditionid", overrideid, override_conditionid);

#define AUDIT_TABLE_NAME	"lld_override_condition"
	zbx_audit_update_json_append_no_value(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key);
	zbx_audit_update_json_append_int(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_operator,
			condition_operator, AUDIT_TABLE_NAME, "operator");
	zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_macro, macro,
			AUDIT_TABLE_NAME, "macro");
	zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_value, value,
			AUDIT_TABLE_NAME, "value");
	zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
			audit_key_lld_override_conditionid, override_conditionid, AUDIT_TABLE_NAME,
			"lld_override_conditionid");
#undef AUDIT_TABLE_NAME
}

void	zbx_audit_discovery_rule_update_json_add_lld_override_operation(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t overrideid, zbx_uint64_t override_operationid, zbx_uint64_t operationobject,
		int condition_operator, const char *value)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_operator[AUDIT_DETAILS_KEY_LEN],
		audit_key_value[AUDIT_DETAILS_KEY_LEN], audit_key_lld_override_operationid[AUDIT_DETAILS_KEY_LEN],
		audit_key_lld_operationobject[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(audit_key, sizeof(audit_key), "discoveryrule.overrides[" ZBX_FS_UI64 "].operations[" ZBX_FS_UI64
			"]", overrideid, override_operationid);
	zbx_snprintf(audit_key_operator, sizeof(audit_key_operator), "discoveryrule.overrides[" ZBX_FS_UI64
			"].operations[" ZBX_FS_UI64 "].operator", overrideid, override_operationid);
	zbx_snprintf(audit_key_value, sizeof(audit_key_value), "discoveryrule.overrides[" ZBX_FS_UI64 "].operations["
			ZBX_FS_UI64 "].value", overrideid, override_operationid);
	zbx_snprintf(audit_key_lld_override_operationid, sizeof(audit_key_lld_override_operationid),
			"discoveryrule.overrides[" ZBX_FS_UI64 "].operations["
			ZBX_FS_UI64 "].lld_override_operationid", overrideid, override_operationid);
	zbx_snprintf(audit_key_lld_operationobject, sizeof(audit_key_lld_operationobject), "discoveryrule.overrides["
			ZBX_FS_UI64 "].operations[" ZBX_FS_UI64 "].operationobject", overrideid, override_operationid);

#define	AUDIT_TABLE_NAME	"lld_override_operation"
	zbx_audit_update_json_append_no_value(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key);
	zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
			audit_key_lld_operationobject, operationobject, AUDIT_TABLE_NAME, "operationobject");
	zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
			audit_key_lld_override_operationid, override_operationid, NULL, NULL);
	zbx_audit_update_json_append_int(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_operator,
			condition_operator, AUDIT_TABLE_NAME, "operator");
	zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_value, value,
			AUDIT_TABLE_NAME, "value");
#undef AUDIT_TABLE_NAME
}

#define PREPARE_AUDIT_DISCOVERY_RULE_OVERRIDE_ADD(resource, type, type2, table, field)				\
void	zbx_audit_discovery_rule_update_json_add_lld_override_##resource(int audit_context_mode,		\
		zbx_uint64_t itemid, zbx_uint64_t overrideid, zbx_uint64_t resource##_id, type resource)	\
{														\
	char	buf[AUDIT_DETAILS_KEY_LEN], audit_key[AUDIT_DETAILS_KEY_LEN];					\
														\
	RETURN_IF_AUDIT_OFF(audit_context_mode);								\
														\
	zbx_snprintf(audit_key, sizeof(audit_key), "discoveryrule.overrides[" ZBX_FS_UI64			\
			"].operations[" ZBX_FS_UI64 "]."#resource, overrideid, resource##_id);			\
	zbx_snprintf(buf, sizeof(buf), "discoveryrule.overrides[" ZBX_FS_UI64					\
			"].operations[" ZBX_FS_UI64 "]."#resource"."#field, overrideid, resource##_id);		\
														\
	zbx_audit_update_json_append_no_value(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key);	\
	zbx_audit_update_json_append_##type2(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, buf, resource,	\
			table, #field);										\
}

PREPARE_AUDIT_DISCOVERY_RULE_OVERRIDE_ADD(opstatus, int, int, "lld_override_opstatus", status)
PREPARE_AUDIT_DISCOVERY_RULE_OVERRIDE_ADD(opdiscover, int, int, "lld_override_opdiscover", discover)
PREPARE_AUDIT_DISCOVERY_RULE_OVERRIDE_ADD(opperiod, const char*, string, "lld_override_opperiod", delay)
PREPARE_AUDIT_DISCOVERY_RULE_OVERRIDE_ADD(optrends, const char*, string, "lld_override_optrends", trends)
PREPARE_AUDIT_DISCOVERY_RULE_OVERRIDE_ADD(ophistory, const char*, string, "lld_override_ophistory", history)
PREPARE_AUDIT_DISCOVERY_RULE_OVERRIDE_ADD(opseverity, int, int, "lld_override_opseverity", severity)
PREPARE_AUDIT_DISCOVERY_RULE_OVERRIDE_ADD(opinventory, int, int, "lld_override_opinventory", inventory_mode)

void	zbx_audit_discovery_rule_update_json_add_lld_override_optag(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t overrideid, zbx_uint64_t override_operationid, zbx_uint64_t lld_override_optagid,
		const char *tag, const char *value)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_lld_override_optagid[AUDIT_DETAILS_KEY_LEN],
		audit_key_tag[AUDIT_DETAILS_KEY_LEN], audit_key_value[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(audit_key, sizeof(audit_key), "discoveryrule.overrides[" ZBX_FS_UI64 "].operations[" ZBX_FS_UI64
			"].optag[" ZBX_FS_UI64 "]", overrideid, override_operationid, lld_override_optagid);
	zbx_snprintf(audit_key_lld_override_optagid, sizeof(audit_key_lld_override_optagid), "discoveryrule.overrides["
			ZBX_FS_UI64 "].operations[" ZBX_FS_UI64 "].optag[" ZBX_FS_UI64 "].lld_override_optagid",
			overrideid, override_operationid, lld_override_optagid);
	zbx_snprintf(audit_key_tag, sizeof(audit_key_tag), "discoveryrule.overrides[" ZBX_FS_UI64
			"].operations[" ZBX_FS_UI64 "].optag[" ZBX_FS_UI64 "].tag", overrideid, override_operationid,
			lld_override_optagid);
	zbx_snprintf(audit_key_value, sizeof(audit_key_value), "discoveryrule.overrides[" ZBX_FS_UI64
			"].operations[" ZBX_FS_UI64 "].optag[" ZBX_FS_UI64 "].value", overrideid, override_operationid,
			lld_override_optagid);

#define	AUDIT_TABLE_NAME	"lld_override_optag"
	zbx_audit_update_json_append_no_value(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key);
	zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
			audit_key_lld_override_optagid, lld_override_optagid, NULL, NULL);
	zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_tag, tag,
			AUDIT_TABLE_NAME, "tag");
	zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_value, value,
			AUDIT_TABLE_NAME, "value");
#undef AUDIT_TABLE_NAME
}

void	zbx_audit_discovery_rule_update_json_add_lld_override_optemplate(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t overrideid, zbx_uint64_t lld_override_optemplateid, zbx_uint64_t templateid)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_templateid[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(audit_key, sizeof(audit_key), "discoveryrule.overrides[" ZBX_FS_UI64 "].optemplateid[" ZBX_FS_UI64
			"]", overrideid, lld_override_optemplateid);
	zbx_snprintf(audit_key_templateid, sizeof(audit_key_templateid), "discoveryrule.overrides[" ZBX_FS_UI64
			"].optemplateid[" ZBX_FS_UI64 "].templateid", overrideid, lld_override_optemplateid);

	zbx_audit_update_json_append_no_value(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key);
	zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_templateid,
			templateid, "lld_override_optemplate", "templateid");
}

void	zbx_audit_item_prototype_update_json_add_lldruleid(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t parent_itemid)
{
	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, "itemprototype.ruleid",
			parent_itemid, NULL, NULL);
}

void	zbx_audit_item_update_json_add_query_fields_json(int audit_context_mode, zbx_uint64_t itemid, int flags,
		const char *val)
{
	struct zbx_json_parse	jp_array, jp_object;
	const char		*key, *element = NULL;
	int			resource_type;
	zbx_uint64_t		index = 0;

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	if (SUCCEED == audit_field_value_matches_db_default("items", "query_fields", val, 0))
		return;

	if (SUCCEED != zbx_json_open(val, &jp_array))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot parse query fields for itemid: " ZBX_FS_UI64 ", error: %s",
				itemid, zbx_json_strerror());

		return;
	}

	if (NULL == (element = zbx_json_next(&jp_array, element)))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot parse query fields for itemid: " ZBX_FS_UI64 ", array is empty",
				itemid);

		return;
	}

	resource_type = zbx_audit_item_flag_to_resource_type(flags);
	key = ZBX_AUDIT_IT_OR_ITP_OR_DR(query_fields);

	do
	{
		char		name[MAX_STRING_LEN], value[MAX_STRING_LEN];
		const char	*member;

		index++;

		if (SUCCEED != zbx_json_brackets_open(element, &jp_object) ||
				NULL == (member = zbx_json_pair_next(&jp_object, NULL, name, sizeof(name))) ||
				NULL == zbx_json_decodevalue(member, value, sizeof(value), NULL))
		{
			zabbix_log(LOG_LEVEL_ERR, "cannot parse query fields for itemid: " ZBX_FS_UI64 ", %s", itemid,
					zbx_json_strerror());

			return;
		}

		char	audit_key_op[AUDIT_DETAILS_KEY_LEN], audit_key_name[AUDIT_DETAILS_KEY_LEN],
			audit_key_sortorder[AUDIT_DETAILS_KEY_LEN], audit_key_value[AUDIT_DETAILS_KEY_LEN];

		zbx_snprintf(audit_key_op, sizeof(audit_key_op), "%s[" ZBX_FS_UI64 "]", key, index);
		zbx_snprintf(audit_key_name, sizeof(audit_key_name), "%s[" ZBX_FS_UI64 "].name",
				key, index);
		zbx_snprintf(audit_key_value, sizeof(audit_key_value), "%s[" ZBX_FS_UI64 "].value", key,
				index);
		zbx_snprintf(audit_key_sortorder, sizeof(audit_key_sortorder), "%s[" ZBX_FS_UI64 "].sortorder",
				key, index);

		zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_op,
				"Added", NULL, NULL);

		zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_name,
				name, NULL, NULL);
		zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_value,
				value, NULL, NULL);
		zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
				audit_key_sortorder, index, NULL, NULL);
	}
	while (NULL != (element = zbx_json_next(&jp_array, element)));
}

void	zbx_audit_item_update_json_update_query_fields(int audit_context_mode, zbx_uint64_t itemid, int flags,
		const char *val_old, const char *val_new)
{
	struct zbx_json_parse	jp_array_old, jp_object_old, jp_array_new, jp_object_new;
	char			name_old[MAX_STRING_LEN], value_old[MAX_STRING_LEN], name_new[MAX_STRING_LEN],
				value_new[MAX_STRING_LEN];
	const char		*member_old, *element_old = NULL, *member_new, *key, *element_new = NULL;
	int			resource_type;

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	if (0 == strcmp(val_old, val_new))
		return;

	resource_type = zbx_audit_item_flag_to_resource_type(flags);
	key = ZBX_AUDIT_IT_OR_ITP_OR_DR(query_fields);

	if ((0 != strcmp(val_old, "") && SUCCEED != zbx_json_open(val_old, &jp_array_old)) ||
			(0 != strcmp(val_new, "") && SUCCEED != zbx_json_open(val_new, &jp_array_new)))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot parse query fields for itemid: " ZBX_FS_UI64 ", error: %s", itemid,
				zbx_json_strerror());

		return;
	}

	if (0 != strcmp(val_old, ""))
		element_old = zbx_json_next(&jp_array_old, element_old);

	if (0 != strcmp(val_new, ""))
		element_new = zbx_json_next(&jp_array_new, element_new);

	zbx_uint64_t	index = 0;

	do
	{
		index++;

		if (NULL != element_old)
		{
			if (SUCCEED != zbx_json_brackets_open(element_old, &jp_object_old) ||
					NULL == (member_old = zbx_json_pair_next(&jp_object_old, NULL, name_old,
					sizeof(name_old))) || NULL == zbx_json_decodevalue(member_old, value_old,
					sizeof(value_old), NULL))
			{
				zabbix_log(LOG_LEVEL_ERR, "cannot parse query fields for itemid: " ZBX_FS_UI64
						", error: %s", itemid, zbx_json_strerror());

				return;
			}
		}

		if (NULL != element_new)
		{
			if (SUCCEED != zbx_json_brackets_open(element_new, &jp_object_new) ||
					NULL == (member_new = zbx_json_pair_next(&jp_object_new, NULL, name_new,
					sizeof(name_new))) || NULL == zbx_json_decodevalue(member_new, value_new,
					sizeof(value_new), NULL))
			{
				zabbix_log(LOG_LEVEL_ERR, "cannot parse query fields for itemid: " ZBX_FS_UI64
						", error: %s", itemid, zbx_json_strerror());

				return;
			}
		}

		if (NULL != element_new && NULL != element_old)
		{
			if (0 != strcmp(name_old, name_new) || 0 != strcmp(value_old, value_new))
			{
				char	audit_key_op[AUDIT_DETAILS_KEY_LEN];

				zbx_snprintf(audit_key_op, sizeof(audit_key_op), "%s[" ZBX_FS_UI64 "]", key, index);

				zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
						audit_key_op, "Updated", NULL, NULL);

				if (0 != strcmp(name_old, name_new))
				{
					char	audit_key_name[AUDIT_DETAILS_KEY_LEN];

					zbx_snprintf(audit_key_name, sizeof(audit_key_name), "%s["ZBX_FS_UI64 "].name",
							key, index);

					zbx_audit_update_json_update_string(itemid, AUDIT_ITEM_ID, audit_key_name,
							name_old, name_new);
				}

				if (0 != strcmp(value_old, value_new))
				{
					char	audit_key_value[AUDIT_DETAILS_KEY_LEN];

					zbx_snprintf(audit_key_value, sizeof(audit_key_value), "%s[" ZBX_FS_UI64
							"].value", key, index);

					zbx_audit_update_json_update_string(itemid, AUDIT_ITEM_ID, audit_key_value,
							value_old, value_new);
				}
			}
		}
		else
		{
			// more old values, need to mention they were deleted
			if (NULL != element_old)
			{
				char	audit_key_op[AUDIT_DETAILS_KEY_LEN];

				zbx_snprintf(audit_key_op, sizeof(audit_key_op), "%s[" ZBX_FS_UI64 "]",
						key, index);

				zbx_audit_update_json_delete(itemid, AUDIT_ITEM_ID,AUDIT_DETAILS_ACTION_DELETE,
						audit_key_op);
			}
			else if (NULL != element_new) // more new values, need to mention they were added
			{
				char	audit_key_op[AUDIT_DETAILS_KEY_LEN], audit_key_name[AUDIT_DETAILS_KEY_LEN],
					audit_key_sortorder[AUDIT_DETAILS_KEY_LEN],
					audit_key_value[AUDIT_DETAILS_KEY_LEN];

				zbx_snprintf(audit_key_op, sizeof(audit_key_op), "%s[" ZBX_FS_UI64 "]",	key, index);
				zbx_snprintf(audit_key_name, sizeof(audit_key_name), "%s[" ZBX_FS_UI64 "].name", key,
						index);
				zbx_snprintf(audit_key_value, sizeof(audit_key_value), "%s[" ZBX_FS_UI64 "].value",
						key, index);
				zbx_snprintf(audit_key_sortorder, sizeof(audit_key_sortorder), "%s[" ZBX_FS_UI64
						"].sortorder", key, index);

				zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
						audit_key_op, "Added", NULL, NULL);
				zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
						audit_key_name, name_new, NULL, NULL);
				zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
						audit_key_value, value_new, NULL, NULL);
				zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
						audit_key_sortorder, index, NULL, NULL);
			}
		}

		if (NULL != element_old)
			element_old = zbx_json_next(&jp_array_old, element_old);

		if (NULL != element_new)
			element_new = zbx_json_next(&jp_array_new, element_new);

		if (NULL == element_old && NULL == element_new)
			break;
	}
	while(1);
}

/* Multiple characters in strtok_r delimiter means several independent     */
/* delimiters. In case of headers  value, we need a single ": " delimiter. */
/* There seems to be no way for strtok_r to use multiple characters as a   */
/* single delimiter. There are no good alternatives, but we can just       */
/* specifically ignore this single extra space for a value.                */
#define ZBX_NULL2EMPTY_STRTOK_R_VALUE(val)	(NULL != (val) ? (val + 1) : "")

/*******************************************************************************
 *                                                                             *
 * Comment:                                                                    *
 *                                                                             *
 *    Unlike query_fields headers are not stored in JSON, but instead they     *
 *     use their own 'special' format:                                         *
 *                                                                             *
 *    query_fields                                                             *
 *    --------------------------------                                         *
 *    [{"qn1":"qv11"},{"qn3":"qv3"}]                                           *
 *                                                                             *
 *      headers                                                                *
 *    -------------                                                            *
 *     hn1: hv11\r+                                                            *
 *     hn2: hv2                                                                *
 *                                                                             *
 *    This is intentional, so server needs to use special parsing for it.      *
 *                                                                             *
 *******************************************************************************/
void	zbx_audit_item_update_json_add_headers(int audit_context_mode, zbx_uint64_t itemid, int flags,
		const char *val)
{
	char		*val_tmp, *val_mut, *element = NULL;
	const char	*key;
	int		resource_type;
	zbx_uint64_t	index = 0;

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	if (SUCCEED == audit_field_value_matches_db_default("items", "headers", val, 0))
		return;

	resource_type = zbx_audit_item_flag_to_resource_type(flags);
	key = ZBX_AUDIT_IT_OR_ITP_OR_DR(headers);

	val_mut = zbx_strdup(NULL, val);

	if (NULL == (element = strtok_r(val_mut, "\r\n", &val_tmp)))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot parse headers for itemid: " ZBX_FS_UI64 ", array is empty", itemid);

		goto out;
	}

	do
	{
		index++;

		char	*val_tmp2, *name, *value;

		if (NULL == (name = strtok_r(element, ":", &val_tmp2)))
		{
			zabbix_log(LOG_LEVEL_ERR, "cannot parse headers for itemid: " ZBX_FS_UI64 " for element %s",
					itemid, element);

			goto out;
		}

		value = strtok_r(NULL, ":", &val_tmp2);

		char	audit_key_op[AUDIT_DETAILS_KEY_LEN], audit_key_name[AUDIT_DETAILS_KEY_LEN],
			audit_key_sortorder[AUDIT_DETAILS_KEY_LEN], audit_key_value[AUDIT_DETAILS_KEY_LEN];

		zbx_snprintf(audit_key_op, sizeof(audit_key_op), "%s[" ZBX_FS_UI64 "]", key, index);
		zbx_snprintf(audit_key_name, sizeof(audit_key_name), "%s[" ZBX_FS_UI64 "].name",
				key, index);
		zbx_snprintf(audit_key_value, sizeof(audit_key_value), "%s[" ZBX_FS_UI64 "].value", key,
				index);
		zbx_snprintf(audit_key_sortorder, sizeof(audit_key_sortorder), "%s[" ZBX_FS_UI64 "].sortorder",
				key, index);

		zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_op,
				"Added", NULL, NULL);

		zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_name,
				name, NULL, NULL);
		zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_value,
				ZBX_NULL2EMPTY_STRTOK_R_VALUE(value), NULL, NULL);
		zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
				audit_key_sortorder, index, NULL, NULL);
	}
	while (NULL != (element = strtok_r(NULL, "\r\n", &val_tmp)));
out:
	zbx_free(val_mut);
}

void	zbx_audit_item_update_json_update_headers(int audit_context_mode, zbx_uint64_t itemid, int flags,
		const char *val_old, const char *val_new)
{
	char		*val_old_tmp, *val_new_tmp, *val_old_mut, *val_new_mut, *element_old = NULL,
			*element_new = NULL;
	const char	*key;
	int		resource_type;
	zbx_uint64_t	index = 0;

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	if (0 == strcmp(val_old, val_new))
		return;

	resource_type = zbx_audit_item_flag_to_resource_type(flags);
	key = ZBX_AUDIT_IT_OR_ITP_OR_DR(headers);

	val_old_mut = zbx_strdup(NULL, val_old);
	val_new_mut = zbx_strdup(NULL, val_new);

	element_old = strtok_r(val_old_mut, "\r\n", &val_old_tmp);
	element_new = strtok_r(val_new_mut, "\r\n", &val_new_tmp);

	do
	{
		index++;

		char	*val_old_tmp2, *val_new_tmp2, *name_old, *value_old, *name_new, *value_new;

		if (NULL != element_old)
		{
			if (NULL == (name_old = strtok_r(element_old, ":", &val_old_tmp2)))
			{
				zabbix_log(LOG_LEVEL_ERR, "cannot parse headers for itemid: " ZBX_FS_UI64
						" for element: %s", itemid, element_old);

				goto out;
			}

			value_old = strtok_r(NULL, ":", &val_old_tmp2);
		}
		else
			value_old = NULL;


		if (NULL != element_new)
		{
			if (NULL == (name_new = strtok_r(element_new, ":", &val_new_tmp2)))
			{
				zabbix_log(LOG_LEVEL_ERR, "cannot parse headers for itemid: " ZBX_FS_UI64
						", for element %s", itemid, element_new);

				goto out;
			}

			value_new = strtok_r(NULL, ":", &val_new_tmp2);
		}
		else
			value_new = NULL;


		if (NULL != element_new && NULL != element_old)
		{
			char	audit_key_op[AUDIT_DETAILS_KEY_LEN];

			if (0 != strcmp(name_old, name_new) || 0 != strcmp(ZBX_NULL2EMPTY_STR(value_old),
					ZBX_NULL2EMPTY_STR(value_new)))
			{
				zbx_snprintf(audit_key_op, sizeof(audit_key_op), "%s[" ZBX_FS_UI64 "]", key, index);

				zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
						audit_key_op, "Updated", NULL, NULL);

				if (0 != strcmp(name_old, name_new))
				{
					char	audit_key_name[AUDIT_DETAILS_KEY_LEN];

					zbx_snprintf(audit_key_name, sizeof(audit_key_name), "%s["ZBX_FS_UI64 "].name",
							key, index);

					zbx_audit_update_json_update_string(itemid, AUDIT_ITEM_ID, audit_key_name,
							name_old, name_new);
				}

				if (0 != strcmp(ZBX_NULL2EMPTY_STR(value_old), ZBX_NULL2EMPTY_STR(value_new)))
				{
					char	audit_key_value[AUDIT_DETAILS_KEY_LEN];

					zbx_snprintf(audit_key_value, sizeof(audit_key_value), "%s[" ZBX_FS_UI64
							"].value", key, index);

					zbx_audit_update_json_update_string(itemid, AUDIT_ITEM_ID, audit_key_value,
							ZBX_NULL2EMPTY_STRTOK_R_VALUE(value_old),
							ZBX_NULL2EMPTY_STRTOK_R_VALUE(value_new));
				}
			}
		}
		else
		{
			// more old values, need to mention they were deleted
			if (NULL != element_old)
			{
				char	audit_key_op[AUDIT_DETAILS_KEY_LEN];
				zbx_snprintf(audit_key_op, sizeof(audit_key_op), "%s[" ZBX_FS_UI64 "]",
						key, index);

				zbx_audit_update_json_delete(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_DELETE,
						audit_key_op);
			}
			else if (NULL != element_new) // more new values, need to mention they were added
			{
				char	audit_key_op[AUDIT_DETAILS_KEY_LEN], audit_key_name[AUDIT_DETAILS_KEY_LEN],
					audit_key_sortorder[AUDIT_DETAILS_KEY_LEN],
					audit_key_value[AUDIT_DETAILS_KEY_LEN];

				zbx_snprintf(audit_key_op, sizeof(audit_key_op), "%s[" ZBX_FS_UI64 "]", key, index);
				zbx_snprintf(audit_key_name, sizeof(audit_key_name), "%s[" ZBX_FS_UI64 "].name", key,
						index);
				zbx_snprintf(audit_key_value, sizeof(audit_key_value), "%s[" ZBX_FS_UI64 "].value",
						key, index);
				zbx_snprintf(audit_key_sortorder, sizeof(audit_key_sortorder), "%s[" ZBX_FS_UI64
						"].sortorder", key, index);

				zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
						audit_key_op, "Added", NULL, NULL);
				zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
						audit_key_name, name_new, NULL, NULL);
				zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
						audit_key_value, ZBX_NULL2EMPTY_STRTOK_R_VALUE(value_new), NULL, NULL);
				zbx_audit_update_json_append_uint64(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
						audit_key_sortorder, index, NULL, NULL);
			}
		}

		if (NULL != element_old)
			element_old = strtok_r(NULL, "\r\n", &val_old_tmp);

		if (NULL != element_new)
			element_new = strtok_r(NULL, "\r\n", &val_new_tmp);

		if (NULL == element_old && NULL == element_new)
			break;
	}
	while(1);
out:
	zbx_free(val_old_mut);
	zbx_free(val_new_mut);
}
#undef ZBX_NULL2EMPTY_STRTOK_R_VALUE
