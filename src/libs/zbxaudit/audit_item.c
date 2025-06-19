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

int	zbx_audit_item_resource_is_only_lld_rule_or_lld_rule_prototype(int resource_type)
{
	return	ZBX_AUDIT_RESOURCE_LLD_RULE == resource_type || ZBX_AUDIT_RESOURCE_LLD_RULE_PROTOTYPE == resource_type;
}

int	zbx_audit_item_flag_to_resource_type(int flag)
{
	if (ZBX_FLAG_DISCOVERY_NORMAL == flag || ZBX_FLAG_DISCOVERY_CREATED == flag)
	{
		return ZBX_AUDIT_RESOURCE_ITEM;
	}
	else if (0 != (flag & ZBX_FLAG_DISCOVERY_PROTOTYPE))
	{
		if (0 == (flag & ZBX_FLAG_DISCOVERY_RULE))
			return ZBX_AUDIT_RESOURCE_ITEM_PROTOTYPE;

		return ZBX_AUDIT_RESOURCE_LLD_RULE_PROTOTYPE;
	}
	else if (0 != (flag & ZBX_FLAG_DISCOVERY_RULE))
	{
		return ZBX_AUDIT_RESOURCE_LLD_RULE;
	}
	else
	{
		THIS_SHOULD_NEVER_HAPPEN_MSG("unsupported item flag detected: %d", flag);
		exit(EXIT_FAILURE);
	}
}

const char	*lld_audit_item_prop(int flags, const char *field, char *buf, size_t len)
{
	int	resource_type = zbx_audit_item_flag_to_resource_type(flags);
	size_t	offset;

	switch (resource_type)
	{
		case ZBX_AUDIT_RESOURCE_ITEM:
			offset = zbx_strlcpy(buf, "item", len);
			break;
		case ZBX_AUDIT_RESOURCE_ITEM_PROTOTYPE:
			offset = zbx_strlcpy(buf, "itemprototype", len);
			break;
		case ZBX_AUDIT_RESOURCE_LLD_RULE:
			offset = zbx_strlcpy(buf, "discoveryrule", len);
			break;
		case ZBX_AUDIT_RESOURCE_LLD_RULE_PROTOTYPE:
			offset = zbx_strlcpy(buf, "discoveryruleprototype", len);
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN_MSG("unsupported resource type: %d", resource_type);
			exit(EXIT_FAILURE);
	}

	if (offset < len - 1)
	{
		buf[offset++] = '.';
		zbx_strlcpy(buf + offset, field, len - offset);
	}

	return buf;
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
	char	prop[AUDIT_DETAILS_KEY_LEN];									\
														\
	RETURN_IF_AUDIT_OFF(audit_context_mode);								\
														\
	zbx_audit_update_json_update_##type2(itemid, AUDIT_ITEM_ID,						\
			lld_audit_item_prop(flags, #resource, prop, sizeof(prop)),				\
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
	zbx_audit_entry_t	*audit_entry = zbx_audit_item_get_entry(audit_context_mode, itemid);

	zbx_audit_entry_update_json_add_filter_conditions(audit_entry, rule_conditionid, op, macro, value);
}

void	zbx_audit_discovery_rule_update_json_update_filter_conditions_create_entry(int audit_context_mode,
		zbx_uint64_t itemid, zbx_uint64_t item_conditionid)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(buf, sizeof(buf), "discoveryrule.filter[" ZBX_FS_UI64 "].conditions", item_conditionid);

	zbx_audit_update_json_append_no_value(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_UPDATE, buf);
}

void	zbx_audit_discovery_rule_update_json_update_filter_conditions(int audit_context_mode,
		zbx_uint64_t itemid, zbx_uint64_t item_conditionid, const char *resource, const char *value_old,
		const char *value_new)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(buf, sizeof(buf), "discoveryrule.filter[" ZBX_FS_UI64 "].conditions.%s",
			item_conditionid, resource);

	zbx_audit_update_json_update_string(itemid, AUDIT_ITEM_ID, buf, value_old, value_new);
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
	zbx_audit_entry_t	*audit_entry = zbx_audit_item_get_entry(audit_context_mode, itemid);

	zbx_audit_entry_update_json_delete_filter_conditions(audit_entry, item_conditionid);
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
	else if (ZBX_AUDIT_RESOURCE_LLD_RULE_PROTOTYPE == resource_type)					\
	{													\
		zbx_snprintf(audit_key_##resource, sizeof(audit_key_##resource), 				\
				"discoveryruleprototype.preprocessing[" ZBX_FS_UI64 "]"#nested#resource, 	\
				preprocid);									\
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
	else if (ZBX_AUDIT_RESOURCE_LLD_RULE_PROTOTYPE == resource_type)					\
	{													\
		zbx_snprintf(audit_key_##resource, sizeof(audit_key_##resource), "discoveryruleprototype.tag["	\
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
				ZBX_FS_UI64 "]"#nested#resource, item_parameter_id);				\
	}													\
	else if (ZBX_AUDIT_RESOURCE_LLD_RULE_PROTOTYPE == resource_type)					\
	{													\
		zbx_snprintf(audit_key_##resource, sizeof(audit_key_##resource), "discoveryruleprototype.parameters[" \
				ZBX_FS_UI64 "]"#nested#resource, item_parameter_id);				\
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
	zbx_audit_entry_t	*audit_entry = zbx_audit_item_get_entry(audit_context_mode, itemid);

	zbx_audit_entry_update_json_add_lld_macro_path(audit_entry, lld_macro_pathid, lld_macro, path);
}

void	zbx_audit_discovery_rule_update_json_lld_macro_path_create_update_entry(int audit_context_mode,
		zbx_uint64_t itemid, zbx_uint64_t lld_macro_pathid)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(buf, sizeof(buf), "discoveryrule.lld_macro_paths[" ZBX_FS_UI64 "]", lld_macro_pathid);

	zbx_audit_update_json_append_no_value(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_UPDATE, buf);
}

void	zbx_audit_discovery_rule_update_json_update_lld_macro_path(int audit_context_mode,
		zbx_uint64_t itemid, zbx_uint64_t lld_macro_pathid, const char *resource, const char *value_old,
		const char *value_new)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(audit_key, sizeof(audit_key), "discoveryrule.lld_macro_paths[" ZBX_FS_UI64 "].%s",
			lld_macro_pathid, resource);

	zbx_audit_update_json_update_string(itemid, AUDIT_ITEM_ID, audit_key, value_old, value_new);
}


#define PREPARE_AUDIT_DISCOVERY_RULE_UPDATE_LLD_MACRO_PATH(resource)						\
void	zbx_audit_discovery_rule_update_json_update_lld_macro_path_##resource(int audit_context_mode,		\
		zbx_uint64_t itemid, zbx_uint64_t lld_macro_pathid, const char *resource##_old,			\
		const char *resource##_new)									\
{														\
	zbx_audit_discovery_rule_update_json_update_lld_macro_path(audit_context_mode, itemid, lld_macro_pathid,\
			#resource, resource##_old, resource##_new); 						\
}
PREPARE_AUDIT_DISCOVERY_RULE_UPDATE_LLD_MACRO_PATH(lld_macro)
PREPARE_AUDIT_DISCOVERY_RULE_UPDATE_LLD_MACRO_PATH(path)
#undef PREPARE_AUDIT_DISCOVERY_RULE_UPDATE_LLD_MACRO_PATH

void   zbx_audit_discovery_rule_update_json_delete_lld_macro_path(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t lld_macro_pathid)
{
	zbx_audit_entry_t	*audit_entry = zbx_audit_item_get_entry(audit_context_mode, itemid);

	zbx_audit_audit_entry_update_json_delete_lld_macro_path(audit_entry, lld_macro_pathid);
}

void	zbx_audit_discovery_rule_update_json_add_lld_override(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t overrideid, const char *name, int step, int stop)
{
	zbx_audit_entry_t	*audit_entry = zbx_audit_item_get_entry(audit_context_mode, itemid);

	zbx_audit_entry_update_json_add_lld_override(audit_entry, overrideid, name, step, stop);
}

void	zbx_audit_discovery_rule_update_json_update_lld_override_str(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t overrideid, const char *resource, const char *value_old, const char *value_new)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(audit_key, sizeof(audit_key), "discoveryrule.overrides[" ZBX_FS_UI64 "].%s",
			overrideid, resource);

	zbx_audit_update_json_update_string(itemid, AUDIT_ITEM_ID, audit_key, value_old, value_new);
}

void	zbx_audit_discovery_rule_update_json_delete_lld_override(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t overrideid)
{
	zbx_audit_entry_t	*audit_entry = zbx_audit_item_get_entry(audit_context_mode, itemid);

	zbx_audit_entry_update_json_delete_lld_override(audit_entry, overrideid);
}

void	zbx_audit_discovery_rule_update_json_add_lld_override_filter(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t overrideid, int evaltype, const char *formula)
{
	zbx_audit_entry_t	*audit_entry = zbx_audit_item_get_entry(audit_context_mode, itemid);

	zbx_audit_entry_update_json_add_lld_override_filter(audit_entry, overrideid, evaltype, formula);
}

void	zbx_audit_discovery_rule_update_json_update_lld_override_filter_str(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t overrideid, const char *resource, const char *value_old, const char  *value_new)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(audit_key, sizeof(audit_key), "discoveryrule.overrides[" ZBX_FS_UI64 "].filter.%s",
			overrideid, resource);

	zbx_audit_update_json_update_string(itemid, AUDIT_ITEM_ID, audit_key, value_old, value_new);
}

void	zbx_audit_discovery_rule_update_json_add_lld_override_condition(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t overrideid, zbx_uint64_t override_conditionid, int condition_operator, const char *macro,
		const char *value)
{
	zbx_audit_entry_t	*audit_entry = zbx_audit_item_get_entry(audit_context_mode, itemid);

	zbx_audit_entry_update_json_add_lld_override_condition(audit_entry, overrideid, override_conditionid,
			condition_operator, macro, value);
}

void	zbx_audit_discovery_rule_update_json_add_lld_override_operation(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t overrideid, zbx_uint64_t override_operationid, int operationobject,
		int condition_operator, const char *value)
{
	zbx_audit_entry_t	*audit_entry = zbx_audit_item_get_entry(audit_context_mode, itemid);

	zbx_audit_entry_update_json_add_lld_override_operation(audit_entry, overrideid, override_operationid,
			operationobject, condition_operator, value);
}

#define PREPARE_AUDIT_DISCOVERY_RULE_OVERRIDE_ADD(resource, type, type2, table, field)				\
void	zbx_audit_discovery_rule_update_json_add_lld_override_##resource(int audit_context_mode,		\
		zbx_uint64_t itemid, zbx_uint64_t overrideid, zbx_uint64_t resource##_id, type resource)	\
{														\
	char	key[AUDIT_DETAILS_KEY_LEN];									\
														\
	zbx_audit_entry_t	*audit_entry = zbx_audit_item_get_entry(audit_context_mode, itemid);            \
														\
	if (NULL == audit_entry)										\
		return;												\
														\
	zbx_audit_lldrule_override_operation(overrideid, resource##_id, NULL, key, sizeof(key));		\
	zbx_audit_entry_add(audit_entry, key);									\
	zbx_audit_lldrule_override_operation(overrideid, resource##_id, #field, key, sizeof(key));		\
	zbx_audit_entry_add_##type2(audit_entry, table, #field, key, resource);					\
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
	zbx_audit_entry_t	*audit_entry = zbx_audit_item_get_entry(audit_context_mode, itemid);

	zbx_audit_entry_update_json_add_lld_override_optag(audit_entry, overrideid, override_operationid,
			lld_override_optagid, tag, value);
}

void	zbx_audit_discovery_rule_update_json_update_lld_override_optag(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t overrideid, zbx_uint64_t operationid, zbx_uint64_t optagid, const char *resource,
		const char *value_old, const char *value_new)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(audit_key, sizeof(audit_key), "discoveryrule.overrides[" ZBX_FS_UI64 "].operations[" ZBX_FS_UI64
				"].%s", overrideid, operationid, resource);

	zbx_snprintf(buf, sizeof(buf), "discoveryrule.overrides[" ZBX_FS_UI64 "].operations[" ZBX_FS_UI64
			"].optag[" ZBX_FS_UI64 "].%s", overrideid, operationid, optagid, resource);

	zbx_audit_update_json_append_no_value(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_UPDATE, audit_key);
	zbx_audit_update_json_update_string(itemid, AUDIT_ITEM_ID, buf, value_old, value_new);
}

void	zbx_audit_discovery_rule_update_json_add_lld_override_optemplate(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t overrideid, zbx_uint64_t operationid, zbx_uint64_t lld_override_optemplateid,
		zbx_uint64_t templateid)
{
	zbx_audit_entry_t	*audit_entry = zbx_audit_item_get_entry(audit_context_mode, itemid);

	zbx_audit_entry_update_json_add_lld_override_optemplate(audit_entry, overrideid, operationid,
			lld_override_optemplateid, templateid);
}

void	zbx_audit_discovery_rule_update_json_update_lld_override_optemplate(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t overrideid, zbx_uint64_t operationid, zbx_uint64_t optemplateid, const char *resource,
		const char *value_old, const char *value_new)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf(audit_key, sizeof(audit_key), "discoveryrule.overrides[" ZBX_FS_UI64 "].operations[" ZBX_FS_UI64
				"].%s", overrideid, operationid, resource);

	zbx_snprintf(buf, sizeof(buf), "discoveryrule.overrides[" ZBX_FS_UI64 "].operations[" ZBX_FS_UI64
			"].optemplate[" ZBX_FS_UI64 "].%s", overrideid, operationid, optemplateid, resource);

	zbx_audit_update_json_append_no_value(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_UPDATE, audit_key);
	zbx_audit_update_json_update_string(itemid, AUDIT_ITEM_ID, buf, value_old, value_new);
}

void	zbx_audit_item_prototype_update_json_add_lldruleid(int audit_context_mode, zbx_uint64_t itemid,
		zbx_uint64_t parent_itemid)
{
	zbx_audit_entry_t	*audit_entry = zbx_audit_item_get_entry(audit_context_mode, itemid);

	if (NULL == audit_entry)
		return;

	zbx_audit_entry_add_uint64(audit_entry, NULL, NULL, "ruleid", parent_itemid);
}

void	zbx_audit_item_update_json_add_query_fields_json(int audit_context_mode, zbx_uint64_t itemid, int flags,
		const char *val)
{
	struct zbx_json_parse	jp_array, jp_object;
	const char		*element = NULL;
	char			prop[AUDIT_DETAILS_KEY_LEN];
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

	lld_audit_item_prop(flags, "query_fields", prop, sizeof(prop));

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

		zbx_snprintf(audit_key_op, sizeof(audit_key_op), "%s[" ZBX_FS_UI64 "]", prop, index);
		zbx_snprintf(audit_key_name, sizeof(audit_key_name), "%s[" ZBX_FS_UI64 "].name",
				prop, index);
		zbx_snprintf(audit_key_value, sizeof(audit_key_value), "%s[" ZBX_FS_UI64 "].value", prop,
				index);
		zbx_snprintf(audit_key_sortorder, sizeof(audit_key_sortorder), "%s[" ZBX_FS_UI64 "].sortorder",
				prop, index);

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
				value_new[MAX_STRING_LEN], prop[AUDIT_DETAILS_KEY_LEN];
	const char		*member_old, *element_old = NULL, *member_new, *element_new = NULL;

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	if (0 == strcmp(val_old, val_new))
		return;

	lld_audit_item_prop(flags, "query_fields", prop, sizeof(prop));

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

				zbx_snprintf(audit_key_op, sizeof(audit_key_op), "%s[" ZBX_FS_UI64 "]", prop, index);

				zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
						audit_key_op, "Updated", NULL, NULL);

				if (0 != strcmp(name_old, name_new))
				{
					char	audit_key_name[AUDIT_DETAILS_KEY_LEN];

					zbx_snprintf(audit_key_name, sizeof(audit_key_name), "%s["ZBX_FS_UI64 "].name",
							prop, index);

					zbx_audit_update_json_update_string(itemid, AUDIT_ITEM_ID, audit_key_name,
							name_old, name_new);
				}

				if (0 != strcmp(value_old, value_new))
				{
					char	audit_key_value[AUDIT_DETAILS_KEY_LEN];

					zbx_snprintf(audit_key_value, sizeof(audit_key_value), "%s[" ZBX_FS_UI64
							"].value", prop, index);

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
						prop, index);

				zbx_audit_update_json_delete(itemid, AUDIT_ITEM_ID,AUDIT_DETAILS_ACTION_DELETE,
						audit_key_op);
			}
			else if (NULL != element_new) // more new values, need to mention they were added
			{
				char	audit_key_op[AUDIT_DETAILS_KEY_LEN], audit_key_name[AUDIT_DETAILS_KEY_LEN],
					audit_key_sortorder[AUDIT_DETAILS_KEY_LEN],
					audit_key_value[AUDIT_DETAILS_KEY_LEN];

				zbx_snprintf(audit_key_op, sizeof(audit_key_op), "%s[" ZBX_FS_UI64 "]",	prop, index);
				zbx_snprintf(audit_key_name, sizeof(audit_key_name), "%s[" ZBX_FS_UI64 "].name", prop,
						index);
				zbx_snprintf(audit_key_value, sizeof(audit_key_value), "%s[" ZBX_FS_UI64 "].value",
						prop, index);
				zbx_snprintf(audit_key_sortorder, sizeof(audit_key_sortorder), "%s[" ZBX_FS_UI64
						"].sortorder", prop, index);

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
	char		*val_tmp, *val_mut, *element = NULL, prop[AUDIT_DETAILS_KEY_LEN];
	zbx_uint64_t	index = 0;

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	if (SUCCEED == audit_field_value_matches_db_default("items", "headers", val, 0))
		return;

	lld_audit_item_prop(flags, "headers", prop, sizeof(prop));

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

		zbx_snprintf(audit_key_op, sizeof(audit_key_op), "%s[" ZBX_FS_UI64 "]", prop, index);
		zbx_snprintf(audit_key_name, sizeof(audit_key_name), "%s[" ZBX_FS_UI64 "].name",
				prop, index);
		zbx_snprintf(audit_key_value, sizeof(audit_key_value), "%s[" ZBX_FS_UI64 "].value", prop,
				index);
		zbx_snprintf(audit_key_sortorder, sizeof(audit_key_sortorder), "%s[" ZBX_FS_UI64 "].sortorder",
				prop, index);

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
			*element_new = NULL, prop[AUDIT_DETAILS_KEY_LEN];
	zbx_uint64_t	index = 0;

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	if (0 == strcmp(val_old, val_new))
		return;

	lld_audit_item_prop(flags, "headers", prop, sizeof(prop));

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
				zbx_snprintf(audit_key_op, sizeof(audit_key_op), "%s[" ZBX_FS_UI64 "]", prop, index);

				zbx_audit_update_json_append_string(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_ADD,
						audit_key_op, "Updated", NULL, NULL);

				if (0 != strcmp(name_old, name_new))
				{
					char	audit_key_name[AUDIT_DETAILS_KEY_LEN];

					zbx_snprintf(audit_key_name, sizeof(audit_key_name), "%s["ZBX_FS_UI64 "].name",
							prop, index);

					zbx_audit_update_json_update_string(itemid, AUDIT_ITEM_ID, audit_key_name,
							name_old, name_new);
				}

				if (0 != strcmp(ZBX_NULL2EMPTY_STR(value_old), ZBX_NULL2EMPTY_STR(value_new)))
				{
					char	audit_key_value[AUDIT_DETAILS_KEY_LEN];

					zbx_snprintf(audit_key_value, sizeof(audit_key_value), "%s[" ZBX_FS_UI64
							"].value", prop, index);

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
						prop, index);

				zbx_audit_update_json_delete(itemid, AUDIT_ITEM_ID, AUDIT_DETAILS_ACTION_DELETE,
						audit_key_op);
			}
			else if (NULL != element_new) // more new values, need to mention they were added
			{
				char	audit_key_op[AUDIT_DETAILS_KEY_LEN], audit_key_name[AUDIT_DETAILS_KEY_LEN],
					audit_key_sortorder[AUDIT_DETAILS_KEY_LEN],
					audit_key_value[AUDIT_DETAILS_KEY_LEN];

				zbx_snprintf(audit_key_op, sizeof(audit_key_op), "%s[" ZBX_FS_UI64 "]", prop, index);
				zbx_snprintf(audit_key_name, sizeof(audit_key_name), "%s[" ZBX_FS_UI64 "].name", prop,
						index);
				zbx_snprintf(audit_key_value, sizeof(audit_key_value), "%s[" ZBX_FS_UI64 "].value",
						prop, index);
				zbx_snprintf(audit_key_sortorder, sizeof(audit_key_sortorder), "%s[" ZBX_FS_UI64
						"].sortorder", prop, index);

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

void	zbx_audit_entry_update_json_add_headers(zbx_audit_entry_t* audit_entry, const char *val)
{
#define KEY(s) zbx_audit_item_headers(index, s, key, sizeof(key))
#define AUDIT_TABLE_NAME	"lld_override"

	char	*val_tmp, *val_mut, *element = NULL, key[AUDIT_DETAILS_KEY_LEN];
	int	index = 0;

	if (NULL == audit_entry)
		return;

	if (SUCCEED == audit_field_value_matches_db_default("items", "headers", val, 0))
		return;

	val_mut = zbx_strdup(NULL, val);

	if (NULL == (element = strtok_r(val_mut, "\r\n", &val_tmp)))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot parse headers for itemid: " ZBX_FS_UI64 ", array is empty",
				audit_entry->id);

		goto out;
	}

	do
	{
		index++;

		char	*val_tmp2, *name, *value;

		if (NULL == (name = strtok_r(element, ":", &val_tmp2)))
		{
			zabbix_log(LOG_LEVEL_ERR, "cannot parse headers for itemid: " ZBX_FS_UI64 " for element %s",
					audit_entry->id, element);

			goto out;
		}

		value = strtok_r(NULL, ":", &val_tmp2);

		zbx_audit_entry_add_string(audit_entry, NULL, NULL, KEY(NULL), "Added");
		zbx_audit_entry_add_string(audit_entry, NULL, NULL, KEY("name"), name);
		zbx_audit_entry_add_string(audit_entry, NULL, NULL, KEY("value"), ZBX_NULL2EMPTY_STRTOK_R_VALUE(value));
		zbx_audit_entry_add_int(audit_entry, NULL, NULL, KEY("sortorder"), index);
	}
	while (NULL != (element = strtok_r(NULL, "\r\n", &val_tmp)));
out:
	zbx_free(val_mut);

#undef AUDIT_TABLE_NAME
#undef KEY
}

#undef ZBX_NULL2EMPTY_STRTOK_R_VALUE

/******************************************************************************
 *                                                                            *
 * Purpose: get audit entry for an item                                       *
 *                                                                            *
 * Parameters: audit_context_mode - [IN] audit context mode                   *
 *             itemid             - [IN] host identifier                      *
 *                                                                            *
 * Return value: pointer to the audit entry or NULL if audit is disabled      *
 *                                                                            *
 ******************************************************************************/
zbx_audit_entry_t	*zbx_audit_item_get_entry(int audit_context_mode, zbx_uint64_t itemid)
{
	int	audit_enabled = 0;

	zbx_audit_get_status(audit_context_mode, &audit_enabled);

	if (0 == audit_enabled)
		return NULL;

	return audit_get_entry(itemid, NULL, AUDIT_ITEM_ID);
}
/******************************************************************************
 *                                                                            *
 * Purpose: get or create audit entry for a host                              *
 *                                                                            *
 * Parameters: audit_context_mode - [IN] audit context mode                   *
 *             audit_action       - [IN] audit action                         *
 *             hostid             - [IN] host identifier                      *
 *             name               - [IN] host name                            *
 *             flags              - [IN] host flags                           *
 *                                                                            *
 * Return value: pointer to the audit entry or NULL if audit is disabled      *
 *                                                                            *
 ******************************************************************************/
zbx_audit_entry_t	*zbx_audit_item_get_or_create_entry(int audit_context_mode, int audit_action,
		zbx_uint64_t itemid, const char *name, int flags)
{
	int	audit_enabled = 0;

	zbx_audit_get_status(audit_context_mode, &audit_enabled);

	if (0 == audit_enabled)
		return NULL;

	zbx_audit_entry_t	audit_entry_local, *paudit_entry_local = &audit_entry_local, **audit_entry;

	audit_entry_local.id = itemid;
	audit_entry_local.cuid = NULL;
	audit_entry_local.id_table = AUDIT_ITEM_ID;

	if (NULL == (audit_entry = (zbx_audit_entry_t **)zbx_hashset_search(zbx_get_audit_hashset(),
			&paudit_entry_local)))
	{
		int	resource_type = zbx_audit_item_flag_to_resource_type(flags);

		paudit_entry_local = zbx_audit_entry_init(itemid, AUDIT_ITEM_ID, name, audit_action, resource_type);

		audit_entry = (zbx_audit_entry_t **)zbx_hashset_insert(zbx_get_audit_hashset(), &paudit_entry_local,
				sizeof(paudit_entry_local));
	}

	return *audit_entry;
}

const char	*zbx_audit_lldrule_macro_path(zbx_uint64_t lld_macro_pathid, const char *field, char *key,
		size_t key_size)
{
	size_t	offset = zbx_snprintf(key, key_size, "lld_macro_paths[" ZBX_FS_UI64 "]", lld_macro_pathid);

	if (NULL != field)
		zbx_snprintf(key + offset, key_size - offset, ".%s", field);

	return key;
}

const char	*zbx_audit_lldrule_filter_condition(zbx_uint64_t filterid, const char *field, char *key,
		size_t key_size)
{
	size_t	offset = zbx_snprintf(key, key_size, "filter.conditions[" ZBX_FS_UI64 "]", filterid);

	if (NULL != field)
		zbx_snprintf(key + offset, key_size - offset, ".%s", field);

	return key;
}

const char	*zbx_audit_lldrule_override(zbx_uint64_t overrideid, const char *field, char *key, size_t key_size)
{
	size_t	offset = zbx_snprintf(key, key_size, "overrides[" ZBX_FS_UI64 "]",
			overrideid);

	if (NULL != field)
		zbx_snprintf(key + offset, key_size - offset, ".%s", field);

	return key;
}

const char	*zbx_audit_lldrule_override_filter(zbx_uint64_t overrideid, const char *field, char *key,
		size_t key_size)
{
	size_t	offset = zbx_snprintf(key, key_size, "overrides[" ZBX_FS_UI64 "].filter",
			overrideid);

	if (NULL != field)
		zbx_snprintf(key + offset, key_size - offset, ".%s", field);

	return key;
}

const char	*zbx_audit_lldrule_override_filter_condition(zbx_uint64_t overrideid, zbx_uint64_t filterid,
		const char *field, char *key, size_t key_size)
{
	size_t	offset = zbx_snprintf(key, key_size, "overrides[" ZBX_FS_UI64 "].filter.conditions[" ZBX_FS_UI64 "]",
			overrideid, filterid);

	if (NULL != field)
		zbx_snprintf(key + offset, key_size - offset, ".%s", field);

	return key;
}

const char	*zbx_audit_lldrule_override_operation(zbx_uint64_t overrideid, zbx_uint64_t operationid,
		const char *field, char *key, size_t key_size)
{
	size_t	offset = zbx_snprintf(key, key_size, "overrides[" ZBX_FS_UI64 "].operations[" ZBX_FS_UI64 "]",
			overrideid, operationid);

	if (NULL != field)
		zbx_snprintf(key + offset, key_size - offset, ".%s", field);

	return key;
}

const char	*zbx_audit_lldrule_override_operation_optag(zbx_uint64_t overrideid, zbx_uint64_t operationid,
		zbx_uint64_t optagid, const char *field, char *key, size_t key_size)
{
	size_t	offset;

	zbx_audit_lldrule_override_operation(overrideid, operationid, NULL, key, key_size);
	offset = strlen(key);

	offset += zbx_snprintf(key + offset, key_size - offset, ".optags[" ZBX_FS_UI64 "]", optagid);

	if (NULL != field)
		zbx_snprintf(key + offset, key_size - offset, ".%s", field);

	return key;
}

const char	*zbx_audit_lldrule_override_operation_optemplate(zbx_uint64_t overrideid, zbx_uint64_t operationid,
		zbx_uint64_t optemplateid, const char *field, char *key, size_t key_size)
{
	size_t	offset;

	zbx_audit_lldrule_override_operation(overrideid, operationid, NULL, key, key_size);
	offset = strlen(key);

	offset += zbx_snprintf(key + offset, key_size - offset, ".optemplates[" ZBX_FS_UI64 "]", optemplateid);

	if (NULL != field)
		zbx_snprintf(key + offset, key_size - offset, ".%s", field);

	return key;
}

const char	*zbx_audit_item_query_fields(int index, const char *field, char *key, size_t key_size)
{
	size_t	offset = zbx_snprintf(key, key_size, "query_fields[%d]", index);

	if (NULL != field)
		zbx_snprintf(key + offset, key_size - offset, ".%s", field);

	return key;
}

const char	*zbx_audit_item_headers(int index, const char *field, char *key, size_t key_size)
{
	size_t	offset = zbx_snprintf(key, key_size, "headers[%d]", index);

	if (NULL != field)
		zbx_snprintf(key + offset, key_size - offset, ".%s", field);

	return key;
}

void	zbx_audit_audit_entry_update_json_delete_lld_macro_path(zbx_audit_entry_t *audit_entry,
		zbx_uint64_t lld_macro_pathid)
{
	if (NULL == audit_entry)
		return;

	char	key[AUDIT_DETAILS_KEY_LEN];

	zbx_audit_lldrule_macro_path(lld_macro_pathid, NULL, key, sizeof(key));
	zbx_audit_entry_delete(audit_entry, key);
}

void	zbx_audit_entry_update_json_add_lld_macro_path(zbx_audit_entry_t *audit_entry, zbx_uint64_t lld_macro_pathid,
		const char *lld_macro, const char *path)
{
#define KEY(s) zbx_audit_lldrule_macro_path(lld_macro_pathid, s, key, sizeof(key))
#define AUDIT_TABLE_NAME	"lld_macro_path"

	if (NULL == audit_entry)
		return;

	char	key[AUDIT_DETAILS_KEY_LEN];

	zbx_audit_entry_add(audit_entry, KEY(NULL));
	zbx_audit_entry_add_string(audit_entry, AUDIT_TABLE_NAME, "lld_macro", KEY("lld_macro"), lld_macro);
	zbx_audit_entry_add_string(audit_entry, AUDIT_TABLE_NAME, "path", KEY("path"), path);

#undef AUDIT_TABLE_NAME
#undef KEY
}

void	zbx_audit_entry_update_json_delete_filter_conditions(zbx_audit_entry_t *audit_entry,
		zbx_uint64_t rule_conditionid)
{
	if (NULL == audit_entry)
		return;

	char	key[AUDIT_DETAILS_KEY_LEN];

	zbx_audit_lldrule_filter_condition(rule_conditionid, NULL, key, sizeof(key));
	zbx_audit_entry_delete(audit_entry, key);
}

void	zbx_audit_entry_update_json_add_filter_conditions(zbx_audit_entry_t *audit_entry, zbx_uint64_t rule_conditionid,
		zbx_uint64_t op, const char *macro, const char *value)
{
#define KEY(s) zbx_audit_lldrule_filter_condition(rule_conditionid, s, key, sizeof(key))
#define	AUDIT_TABLE_NAME	"item_condition"

	if (NULL == audit_entry)
		return;

	char	key[AUDIT_DETAILS_KEY_LEN];

	zbx_audit_entry_add_string(audit_entry, NULL, NULL, "filter", "Added");
	zbx_audit_entry_add(audit_entry, KEY(NULL));
	zbx_audit_entry_add_uint64(audit_entry, NULL, NULL, KEY("item_conditionid"), rule_conditionid);
	zbx_audit_entry_add_int(audit_entry, AUDIT_TABLE_NAME, "operator", KEY("operator"), op);
	zbx_audit_entry_add_string(audit_entry, AUDIT_TABLE_NAME, "macro", KEY("macro"), macro);
	zbx_audit_entry_add_string(audit_entry, AUDIT_TABLE_NAME, "value", KEY("value"), value);

#undef AUDIT_TABLE_NAME
#undef KEY
}

void	zbx_audit_entry_update_json_delete_lld_override_filter(zbx_audit_entry_t *audit_entry,
		zbx_uint64_t overrideid, zbx_uint64_t conditionid)
{
	if (NULL == audit_entry)
		return;

	char	key[AUDIT_DETAILS_KEY_LEN];

	zbx_audit_lldrule_override_filter_condition(overrideid, conditionid, NULL, key, sizeof(key));
	zbx_audit_entry_delete(audit_entry, key);
}

void	zbx_audit_entry_update_json_delete_lld_override_operation(zbx_audit_entry_t *audit_entry,
		zbx_uint64_t overrideid, zbx_uint64_t operationid)
{
	if (NULL == audit_entry)
		return;

	char	key[AUDIT_DETAILS_KEY_LEN];

	zbx_audit_lldrule_override_operation(overrideid, operationid, NULL, key, sizeof(key));
	zbx_audit_entry_delete(audit_entry, key);
}

void	zbx_audit_entry_update_json_delete_lld_override_operation_optag(zbx_audit_entry_t *audit_entry,
		zbx_uint64_t overrideid, zbx_uint64_t operationid, zbx_uint64_t optagid)
{
	if (NULL == audit_entry)
		return;

	char	key[AUDIT_DETAILS_KEY_LEN];

	zbx_audit_lldrule_override_operation_optag(overrideid, operationid, optagid, NULL, key, sizeof(key));
	zbx_audit_entry_delete(audit_entry, key);
}

void	zbx_audit_entry_update_json_delete_lld_override_operation_optemplate(zbx_audit_entry_t *audit_entry,
		zbx_uint64_t overrideid, zbx_uint64_t operationid, zbx_uint64_t optemplateid)
{
	if (NULL == audit_entry)
		return;

	char	key[AUDIT_DETAILS_KEY_LEN];

	zbx_audit_lldrule_override_operation_optemplate(overrideid, operationid, optemplateid, NULL, key, sizeof(key));
	zbx_audit_entry_delete(audit_entry, key);
}

void	zbx_audit_entry_update_json_add_lld_override_condition(zbx_audit_entry_t *audit_entry,
		zbx_uint64_t overrideid, zbx_uint64_t override_conditionid, int condition_operator, const char *macro,
		const char *value)
{
#define KEY(s) zbx_audit_lldrule_override_filter_condition(overrideid, override_conditionid, s, key, sizeof(key))
#define AUDIT_TABLE_NAME	"lld_override_condition"

	if (NULL == audit_entry)
		return;

	char	key[AUDIT_DETAILS_KEY_LEN];

	zbx_audit_entry_add(audit_entry, KEY(NULL));
	zbx_audit_entry_add_uint64(audit_entry, AUDIT_TABLE_NAME, "lld_override_conditionid",
			KEY("lld_override_conditionid"), override_conditionid);
	zbx_audit_entry_add_int(audit_entry, AUDIT_TABLE_NAME, "operator", KEY("operator"), condition_operator);
	zbx_audit_entry_add_string(audit_entry, AUDIT_TABLE_NAME, "macro", KEY("macro"), macro);
	zbx_audit_entry_add_string(audit_entry, AUDIT_TABLE_NAME, "value", KEY("value"), value);

#undef AUDIT_TABLE_NAME
#undef KEY
}

void	zbx_audit_entry_update_json_add_lld_override_operation(zbx_audit_entry_t *audit_entry, zbx_uint64_t overrideid,
		zbx_uint64_t override_operationid, int operationobject, int condition_operator, const char *value)
{
#define KEY(s) zbx_audit_lldrule_override_operation(overrideid, override_operationid, s, key, sizeof(key))
#define AUDIT_TABLE_NAME	"lld_override_operation"

	if (NULL == audit_entry)
		return;

	char	key[AUDIT_DETAILS_KEY_LEN];

	zbx_audit_entry_add(audit_entry, KEY(NULL));
	zbx_audit_entry_add_uint64(audit_entry, AUDIT_TABLE_NAME, "lld_override_operationid",
			KEY("lld_override_operationid"), override_operationid);
	zbx_audit_entry_add_int(audit_entry, AUDIT_TABLE_NAME, "operationobject", KEY("operationobject"),
			operationobject);
	zbx_audit_entry_add_int(audit_entry, AUDIT_TABLE_NAME, "operator", KEY("operator"), condition_operator);
	zbx_audit_entry_add_string(audit_entry, AUDIT_TABLE_NAME, "value", KEY("value"), value);

#undef AUDIT_TABLE_NAME
#undef KEY
}

void	zbx_audit_entry_update_json_add_lld_override_optag(zbx_audit_entry_t *audit_entry, zbx_uint64_t overrideid,
		zbx_uint64_t override_operationid, zbx_uint64_t lld_override_optagid, const char *tag,
		const char *value)
{
#define KEY(s) zbx_audit_lldrule_override_operation_optag(overrideid, override_operationid, lld_override_optagid, s, \
		key, sizeof(key))
#define AUDIT_TABLE_NAME	"lld_override_optag"

	if (NULL == audit_entry)
		return;

	char	key[AUDIT_DETAILS_KEY_LEN];

	zbx_audit_entry_add(audit_entry, KEY(NULL));
	zbx_audit_entry_add_uint64(audit_entry, AUDIT_TABLE_NAME, "lld_override_optagid", KEY("lld_override_optagid"),
			lld_override_optagid);
	zbx_audit_entry_add_string(audit_entry, AUDIT_TABLE_NAME, "tag", KEY("tag"), tag);
	zbx_audit_entry_add_string(audit_entry, AUDIT_TABLE_NAME, "value", KEY("value"), value);

#undef AUDIT_TABLE_NAME
#undef KEY
}

void	zbx_audit_entry_update_json_add_lld_override_optemplate(zbx_audit_entry_t *audit_entry, zbx_uint64_t overrideid,
		zbx_uint64_t operationid, zbx_uint64_t lld_override_optemplateid, zbx_uint64_t templateid)
{
#define KEY(s) zbx_audit_lldrule_override_operation_optemplate(overrideid, operationid, lld_override_optemplateid, s, \
		key, sizeof(key))
#define AUDIT_TABLE_NAME	"lld_override_optemplate"

	if (NULL == audit_entry)
		return;

	char	key[AUDIT_DETAILS_KEY_LEN];

	zbx_audit_entry_add(audit_entry, KEY(NULL));
	zbx_audit_entry_add_uint64(audit_entry, AUDIT_TABLE_NAME, "templateid", KEY("templateid"), templateid);

#undef AUDIT_TABLE_NAME
#undef KEY
}

void	zbx_audit_entry_update_json_delete_lld_override(zbx_audit_entry_t *audit_entry,
		zbx_uint64_t overrideid)
{
	if (NULL == audit_entry)
		return;

	char	key[AUDIT_DETAILS_KEY_LEN];

	zbx_audit_lldrule_override(overrideid, NULL, key, sizeof(key));
	zbx_audit_entry_delete(audit_entry, key);
}

void	zbx_audit_entry_update_json_add_lld_override(zbx_audit_entry_t *audit_entry, zbx_uint64_t overrideid,
		const char *name, int step, int stop)
{
#define KEY(s) zbx_audit_lldrule_override(overrideid, s, key, sizeof(key))
#define AUDIT_TABLE_NAME	"lld_override"

	if (NULL == audit_entry)
		return;

	char	key[AUDIT_DETAILS_KEY_LEN];

	zbx_audit_entry_add(audit_entry, KEY(NULL));
	zbx_audit_entry_add_uint64(audit_entry, NULL, NULL, KEY("lld_overrideid"), overrideid);
	zbx_audit_entry_add_string(audit_entry, AUDIT_TABLE_NAME, "name", KEY("name"), name);
	zbx_audit_entry_add_int(audit_entry, AUDIT_TABLE_NAME, "step", KEY("step"), step);
	zbx_audit_entry_add_int(audit_entry, AUDIT_TABLE_NAME, "stop", KEY("stop"), stop);

#undef AUDIT_TABLE_NAME
#undef KEY
}

void	zbx_audit_entry_update_json_add_lld_override_filter(zbx_audit_entry_t *audit_entry, zbx_uint64_t overrideid,
		int evaltype, const char *formula)
{
#define KEY(s) zbx_audit_lldrule_override_filter(overrideid, s, key, sizeof(key))
#define AUDIT_TABLE_NAME	"lld_override"

	if (NULL == audit_entry)
		return;

	char	key[AUDIT_DETAILS_KEY_LEN];

	zbx_audit_entry_add(audit_entry, KEY(NULL));
	zbx_audit_entry_add_int(audit_entry, AUDIT_TABLE_NAME, "evaltype", KEY("evaltype"), evaltype);
	zbx_audit_entry_add_string(audit_entry, AUDIT_TABLE_NAME, "formula", KEY("formula"), formula);

#undef AUDIT_TABLE_NAME
#undef KEY
}

void	zbx_audit_entry_update_json_add_query_fields_json(zbx_audit_entry_t *audit_entry, const char *val)
{
#define KEY(s) zbx_audit_item_query_fields(index, s, key, sizeof(key))
#define AUDIT_TABLE_NAME	"lld_override"

	struct zbx_json_parse	jp_array, jp_object;
	const char		*element = NULL;
	char			key[AUDIT_DETAILS_KEY_LEN];
	int			index = 0;

	if (NULL == audit_entry)
		return;

	if (SUCCEED == audit_field_value_matches_db_default("items", "query_fields", val, 0))
		return;

	if (SUCCEED != zbx_json_open(val, &jp_array))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot parse query fields for itemid: " ZBX_FS_UI64 ", error: %s",
				audit_entry->id, zbx_json_strerror());

		return;
	}

	if (NULL == (element = zbx_json_next(&jp_array, element)))
	{
		zabbix_log(LOG_LEVEL_ERR, "cannot parse query fields for itemid: " ZBX_FS_UI64 ", array is empty",
				audit_entry->id);

		return;
	}

	do
	{
		char		name[MAX_STRING_LEN], value[MAX_STRING_LEN];
		const char	*member;

		index++;

		if (SUCCEED != zbx_json_brackets_open(element, &jp_object) ||
				NULL == (member = zbx_json_pair_next(&jp_object, NULL, name, sizeof(name))) ||
				NULL == zbx_json_decodevalue(member, value, sizeof(value), NULL))
		{
			zabbix_log(LOG_LEVEL_ERR, "cannot parse query fields for itemid: " ZBX_FS_UI64 ", %s",
					audit_entry->id, zbx_json_strerror());

			return;
		}

		zbx_audit_entry_add_string(audit_entry, NULL, NULL, KEY(NULL), "Added");
		zbx_audit_entry_add_string(audit_entry, NULL, NULL, KEY("name"), name);
		zbx_audit_entry_add_string(audit_entry, NULL, NULL, KEY("value"), value);
		zbx_audit_entry_add_int(audit_entry, NULL, NULL, KEY("sortorder"), index);

	}
	while (NULL != (element = zbx_json_next(&jp_array, element)));

#undef AUDIT_TABLE_NAME
#undef KEY
}
