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

#include "audit/zbxaudit_trigger.h"
#include "audit/zbxaudit.h"

#include "audit.h"

#include "zbxdb.h"
#include "zbxnum.h"
#include "zbxalgo.h"

static int	trigger_flag_to_resource_type(int flag)
{
	if (ZBX_FLAG_DISCOVERY_NORMAL == flag || ZBX_FLAG_DISCOVERY_CREATED == flag)
	{
		return ZBX_AUDIT_RESOURCE_TRIGGER;
	}
	else if (ZBX_FLAG_DISCOVERY_PROTOTYPE == flag)
	{
		return ZBX_AUDIT_RESOURCE_TRIGGER_PROTOTYPE;
	}
	else
	{
		zabbix_log(LOG_LEVEL_CRIT, "unexpected audit trigger flag detected: ->%d<-", flag);
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}
}

#define TR_OR_TRP(s) (ZBX_AUDIT_RESOURCE_TRIGGER == resource_type) ? "trigger."#s : "triggerprototype."#s

void	zbx_audit_trigger_create_entry(int audit_context_mode, int audit_action, zbx_uint64_t triggerid,
		const char *name, int flags)
{
	int			resource_type;
	zbx_audit_entry_t	local_audit_trigger_entry, **found_audit_trigger_entry;
	zbx_audit_entry_t	*local_audit_trigger_entry_x = &local_audit_trigger_entry;

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	resource_type = trigger_flag_to_resource_type(flags);

	local_audit_trigger_entry.id = triggerid;
	local_audit_trigger_entry.cuid = NULL;
	local_audit_trigger_entry.id_table = AUDIT_TRIGGER_ID;

	found_audit_trigger_entry = (zbx_audit_entry_t**)zbx_hashset_search(zbx_get_audit_hashset(),
			&(local_audit_trigger_entry_x));

	if (NULL == found_audit_trigger_entry)
	{
		zbx_audit_entry_t	*local_audit_trigger_entry_insert;

		local_audit_trigger_entry_insert = zbx_audit_entry_init(triggerid, AUDIT_TRIGGER_ID, name, audit_action,
				resource_type);
		zbx_hashset_insert(zbx_get_audit_hashset(), &local_audit_trigger_entry_insert,
				sizeof(local_audit_trigger_entry_insert));

		if (ZBX_AUDIT_ACTION_ADD == audit_action)
		{
			zbx_audit_update_json_append_uint64(triggerid, AUDIT_TRIGGER_ID, AUDIT_DETAILS_ACTION_ADD,
					TR_OR_TRP(triggerid), triggerid, "triggers", "triggerid");
		}
	}
}

void	zbx_audit_trigger_update_json_add_data(int audit_context_mode, zbx_uint64_t triggerid, zbx_uint64_t templateid,
		unsigned char recovery_mode, unsigned char status, unsigned char type, zbx_uint64_t value,
		zbx_uint64_t state, unsigned char priority, const char *comments, const char *url,
		const char *url_name, int flags, unsigned char correlation_mode, const char *correlation_tag,
		unsigned char manual_close, const char *opdata, unsigned char discover, const char *event_name)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_event_name[AUDIT_DETAILS_KEY_LEN],
		audit_key_opdata[AUDIT_DETAILS_KEY_LEN], audit_key_comments[AUDIT_DETAILS_KEY_LEN],
		audit_key_flags[AUDIT_DETAILS_KEY_LEN], audit_key_priority[AUDIT_DETAILS_KEY_LEN],
		audit_key_state[AUDIT_DETAILS_KEY_LEN], audit_key_status[AUDIT_DETAILS_KEY_LEN],
		audit_key_templateid[AUDIT_DETAILS_KEY_LEN], audit_key_type[AUDIT_DETAILS_KEY_LEN],
		audit_key_url[AUDIT_DETAILS_KEY_LEN], audit_key_value[AUDIT_DETAILS_KEY_LEN],
		audit_key_recovery_mode[AUDIT_DETAILS_KEY_LEN], audit_key_correlation_mode[AUDIT_DETAILS_KEY_LEN],
		audit_key_correlation_tag[AUDIT_DETAILS_KEY_LEN], audit_key_manual_close[AUDIT_DETAILS_KEY_LEN],
		audit_key_discover[AUDIT_DETAILS_KEY_LEN], audit_key_url_name[AUDIT_DETAILS_KEY_LEN];
	int	resource_type;

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	resource_type = trigger_flag_to_resource_type(flags);

	zbx_snprintf(audit_key, sizeof(audit_key), (ZBX_AUDIT_RESOURCE_TRIGGER == resource_type) ? "trigger" :
			"triggerprototype");
#define AUDIT_KEY_SNPRINTF(r) zbx_snprintf(audit_key_##r, sizeof(audit_key_##r), TR_OR_TRP(r));
	AUDIT_KEY_SNPRINTF(event_name)
	AUDIT_KEY_SNPRINTF(opdata)
	AUDIT_KEY_SNPRINTF(comments)
	AUDIT_KEY_SNPRINTF(flags)
	AUDIT_KEY_SNPRINTF(priority)
	AUDIT_KEY_SNPRINTF(state)
	AUDIT_KEY_SNPRINTF(status)
	AUDIT_KEY_SNPRINTF(templateid)
	AUDIT_KEY_SNPRINTF(type)
	AUDIT_KEY_SNPRINTF(url)
	AUDIT_KEY_SNPRINTF(url_name)
	AUDIT_KEY_SNPRINTF(value)
	AUDIT_KEY_SNPRINTF(recovery_mode)
	AUDIT_KEY_SNPRINTF(correlation_mode)
	AUDIT_KEY_SNPRINTF(correlation_tag)
	AUDIT_KEY_SNPRINTF(manual_close)
	if (ZBX_AUDIT_RESOURCE_TRIGGER_PROTOTYPE == resource_type)
		AUDIT_KEY_SNPRINTF(discover)
#undef AUDIT_KEY_SNPRINTF
	zbx_audit_update_json_append_no_value(triggerid, AUDIT_TRIGGER_ID, AUDIT_DETAILS_ACTION_ADD, audit_key);
#define ADD_STR(r, t, f) zbx_audit_update_json_append_string(triggerid, AUDIT_TRIGGER_ID, AUDIT_DETAILS_ACTION_ADD, \
		audit_key_##r, r, t, f);
#define ADD_UINT64(r,t, f) zbx_audit_update_json_append_uint64(triggerid, AUDIT_TRIGGER_ID, AUDIT_DETAILS_ACTION_ADD, \
		audit_key_##r, r, t, f);
#define ADD_INT(r, t, f) zbx_audit_update_json_append_int(triggerid, AUDIT_TRIGGER_ID, AUDIT_DETAILS_ACTION_ADD, \
		audit_key_##r, r, t, f);
#define AUDIT_TABLE_NAME	"triggers"
	ADD_STR(event_name, AUDIT_TABLE_NAME, "event_name")
	ADD_STR(opdata, AUDIT_TABLE_NAME, "opdata")
	ADD_STR(comments, AUDIT_TABLE_NAME, "comments")
	ADD_INT(flags, AUDIT_TABLE_NAME, "flags")
	ADD_INT(priority, AUDIT_TABLE_NAME, "priority")
	ADD_UINT64(state, AUDIT_TABLE_NAME, "state")
	ADD_INT(status, AUDIT_TABLE_NAME, "status")
	ADD_UINT64(templateid, AUDIT_TABLE_NAME, "templateid")
	ADD_INT(type, AUDIT_TABLE_NAME, "type")
	ADD_STR(url, AUDIT_TABLE_NAME, "url")
	ADD_STR(url_name, AUDIT_TABLE_NAME, "url_name")
	ADD_UINT64(value, AUDIT_TABLE_NAME, "value")
	ADD_INT(recovery_mode, AUDIT_TABLE_NAME, "recovery_mode")
	ADD_INT(correlation_mode, AUDIT_TABLE_NAME, "correlation_mode")
	ADD_STR(correlation_tag, AUDIT_TABLE_NAME, "correlation_tag")
	ADD_INT(manual_close, AUDIT_TABLE_NAME, "manual_close")

	if (ZBX_AUDIT_RESOURCE_TRIGGER_PROTOTYPE == resource_type)
		ADD_UINT64(discover, AUDIT_TABLE_NAME, "discover")

#undef AUDIT_TABLE_NAME
#undef ADD_STR
#undef ADD_UINT64
#undef ADD_INT
}

void	zbx_audit_trigger_update_json_add_expr(int audit_context_mode, zbx_uint64_t triggerid, int flags,
		const char *expression)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];
	int	resource_type;

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	resource_type = trigger_flag_to_resource_type(flags);

	zbx_snprintf(buf, sizeof(buf), TR_OR_TRP(expression));
	zbx_audit_update_json_append_string(triggerid, AUDIT_TRIGGER_ID, AUDIT_DETAILS_ACTION_ADD, buf, expression,
			"triggers", "expression");
}

void	zbx_audit_trigger_update_json_add_rexpr(int audit_context_mode, zbx_uint64_t triggerid, int flags,
		const char *recovery_expression)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];
	int	resource_type;

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	resource_type = trigger_flag_to_resource_type(flags);

	zbx_snprintf(buf, sizeof(buf), TR_OR_TRP(recovery_expression));
	zbx_audit_update_json_append_string(triggerid, AUDIT_TRIGGER_ID, AUDIT_DETAILS_ACTION_ADD, buf,
			recovery_expression, "triggers", "recovery_expression");
}

#define PREPARE_AUDIT_TRIGGER_UPDATE(resource, type1, type2)							\
void	zbx_audit_trigger_update_json_update_##resource(int audit_context_mode, zbx_uint64_t triggerid,		\
		int flags, type1 resource##_old, type1 resource##_new)						\
{														\
	char	buf[AUDIT_DETAILS_KEY_LEN];									\
	int	resource_type;											\
														\
	RETURN_IF_AUDIT_OFF(audit_context_mode);								\
														\
	resource_type = trigger_flag_to_resource_type(flags);							\
														\
	zbx_snprintf(buf, sizeof(buf), TR_OR_TRP(resource));							\
														\
	zbx_audit_update_json_update_##type2(triggerid, AUDIT_TRIGGER_ID, buf, resource##_old, resource##_new);	\
}

PREPARE_AUDIT_TRIGGER_UPDATE(recovery_mode, int, int)
PREPARE_AUDIT_TRIGGER_UPDATE(correlation_mode, int, int)
PREPARE_AUDIT_TRIGGER_UPDATE(correlation_tag, const char*, string)
PREPARE_AUDIT_TRIGGER_UPDATE(manual_close, int, int)
PREPARE_AUDIT_TRIGGER_UPDATE(opdata, const char*, string)
PREPARE_AUDIT_TRIGGER_UPDATE(discover, int, int)
PREPARE_AUDIT_TRIGGER_UPDATE(event_name, const char*, string)
PREPARE_AUDIT_TRIGGER_UPDATE(priority, int, int)
PREPARE_AUDIT_TRIGGER_UPDATE(comments, const char*, string)
PREPARE_AUDIT_TRIGGER_UPDATE(url, const char*, string)
PREPARE_AUDIT_TRIGGER_UPDATE(url_name, const char*, string)
PREPARE_AUDIT_TRIGGER_UPDATE(type, int, int)
PREPARE_AUDIT_TRIGGER_UPDATE(status, int, int)
PREPARE_AUDIT_TRIGGER_UPDATE(templateid, zbx_uint64_t, uint64)
PREPARE_AUDIT_TRIGGER_UPDATE(description, const char*, string)
PREPARE_AUDIT_TRIGGER_UPDATE(expression, const char*, string)
PREPARE_AUDIT_TRIGGER_UPDATE(recovery_expression, const char*, string)

#undef PREPARE_AUDIT_TRIGGER_UPDATE
#undef TR_OR_TRP


/********************************************************************************
 *                                                                              *
 * Purpose: create audit events for triggers that are to be removed             *
 *                                                                              *
 ********************************************************************************/
void	zbx_audit_trigger_delete(int audit_context_mode, zbx_vector_uint64_t *triggerids)
{
	zbx_db_large_query_t	query;
	zbx_db_row_t		row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "select triggerid,description,flags from triggers where");
	zbx_db_large_query_prepare_uint(&query, &sql, &sql_alloc, &sql_offset, "triggerid", triggerids);

	while (NULL != (row = zbx_db_large_query_fetch(&query)))
	{
		zbx_uint64_t	triggerid;

		ZBX_STR2UINT64(triggerid, row[0]);

		zbx_audit_trigger_create_entry(audit_context_mode, ZBX_AUDIT_ACTION_DELETE, triggerid, row[1],
				atoi(row[2]));
	}
	zbx_db_large_query_clear(&query);
	zbx_free(sql);
}

void	zbx_audit_trigger_update_json_add_dependency(int audit_context_mode, int flags, zbx_uint64_t triggerdepid,
		zbx_uint64_t triggerid, zbx_uint64_t triggerid_up)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_triggerid_up[AUDIT_DETAILS_KEY_LEN];
	int	resource_type;

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	resource_type = trigger_flag_to_resource_type(flags);

	if (ZBX_AUDIT_RESOURCE_TRIGGER == resource_type)
	{
		zbx_snprintf(audit_key, sizeof(audit_key), "trigger.dependencies[" ZBX_FS_UI64 "]", triggerdepid);
		zbx_snprintf(audit_key_triggerid_up, sizeof(audit_key_triggerid_up), "trigger.dependencies["
				ZBX_FS_UI64 "].dependsOnTriggerid", triggerdepid);
	}
	else
	{
		zbx_snprintf(audit_key, sizeof(audit_key), "triggerprototype.dependencies[" ZBX_FS_UI64 "]",
				triggerdepid);
		zbx_snprintf(audit_key_triggerid_up, sizeof(audit_key_triggerid_up), "triggerprototype.dependencies["
				ZBX_FS_UI64 "].dependsOnTriggerid", triggerdepid);
	}

	zbx_audit_update_json_append_no_value(triggerid, AUDIT_TRIGGER_ID, AUDIT_DETAILS_ACTION_ADD, audit_key);
	zbx_audit_update_json_append_uint64(triggerid, AUDIT_TRIGGER_ID, AUDIT_DETAILS_ACTION_ADD,
			audit_key_triggerid_up, triggerid_up, "trigger_depends", "triggerid_up");
}

void	zbx_audit_trigger_update_json_remove_dependency(int audit_context_mode, int flags, zbx_uint64_t triggerdepid,
		zbx_uint64_t triggerid)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN];
	int	resource_type;

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	resource_type = trigger_flag_to_resource_type(flags);

	zbx_snprintf(audit_key, sizeof(audit_key), "trigger%s.dependencies[" ZBX_FS_UI64 "]",
			(ZBX_AUDIT_RESOURCE_TRIGGER == resource_type) ? "" : "prototype", triggerdepid);

	zbx_audit_update_json_append_no_value(triggerid, AUDIT_TRIGGER_ID, AUDIT_DETAILS_ACTION_DELETE, audit_key);
}

void	zbx_audit_trigger_update_json_add_tags_and_values(int audit_context_mode, zbx_uint64_t triggerid, int flags,
		zbx_uint64_t triggertagid, const char *tag, const char *value)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_tag[AUDIT_DETAILS_KEY_LEN],
		audit_key_value[AUDIT_DETAILS_KEY_LEN];
	int	resource_type;

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	resource_type = trigger_flag_to_resource_type(flags);

	if (ZBX_AUDIT_RESOURCE_TRIGGER == resource_type)
	{
		zbx_snprintf(audit_key, sizeof(audit_key), "trigger.tags[" ZBX_FS_UI64 "]", triggertagid);
		zbx_snprintf(audit_key_tag, sizeof(audit_key_tag), "trigger.tags[" ZBX_FS_UI64 "].tag", triggertagid);
		zbx_snprintf(audit_key_value, sizeof(audit_key_value), "trigger.tags[" ZBX_FS_UI64 "].value",
				triggertagid);
	}
	else if(ZBX_AUDIT_RESOURCE_TRIGGER_PROTOTYPE == resource_type)
	{
		zbx_snprintf(audit_key, sizeof(audit_key), "triggerprototype.tags[" ZBX_FS_UI64 "]", triggertagid);
		zbx_snprintf(audit_key_tag, sizeof(audit_key_tag), "triggerprototype.tags[" ZBX_FS_UI64 "].tag",
				triggertagid);
		zbx_snprintf(audit_key_value, sizeof(audit_key_value), "triggerprototype.tags[" ZBX_FS_UI64 "].value",
				triggertagid);
	}
	else
	{
		zabbix_log(LOG_LEVEL_CRIT, "unexpected audit trigger resource type detected: ->%d<-", resource_type);
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}

#define AUDIT_TABLE_NAME	"trigger_tag"
	zbx_audit_update_json_append_no_value(triggerid, AUDIT_TRIGGER_ID, AUDIT_DETAILS_ACTION_ADD, audit_key);
	zbx_audit_update_json_append_string(triggerid, AUDIT_TRIGGER_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_tag,
			tag, AUDIT_TABLE_NAME, "tag");
	zbx_audit_update_json_append_string(triggerid, AUDIT_TRIGGER_ID, AUDIT_DETAILS_ACTION_ADD, audit_key_value,
			value, AUDIT_TABLE_NAME, "value");
#undef AUDIT_TABLE_NAME
}

void	zbx_audit_trigger_update_json_delete_tags(int audit_context_mode, zbx_uint64_t triggerid, int flags,
		zbx_uint64_t triggertagid)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN];
	int	resource_type;

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	resource_type = trigger_flag_to_resource_type(flags);

	if (ZBX_AUDIT_RESOURCE_TRIGGER == resource_type)
		zbx_snprintf(audit_key, sizeof(audit_key), "trigger.tags[" ZBX_FS_UI64 "]", triggertagid);
	else
		zbx_snprintf(audit_key, sizeof(audit_key), "triggerprototype.tags[" ZBX_FS_UI64 "]", triggertagid);

	zbx_audit_update_json_append_no_value(triggerid, AUDIT_TRIGGER_ID, AUDIT_DETAILS_ACTION_DELETE, audit_key);
}

#define TRIGGER_RESOURCE_KEY_RESOLVE_TAG(resource, nested)							\
	if (ZBX_AUDIT_RESOURCE_TRIGGER == resource_type)							\
	{													\
		zbx_snprintf(audit_key_##resource, sizeof(audit_key_##resource), "trigger.tag[" ZBX_FS_UI64	\
				"]"#nested#resource, triggertagid);						\
	}													\
	else if (ZBX_AUDIT_RESOURCE_TRIGGER_PROTOTYPE == resource_type)						\
	{													\
		zbx_snprintf(audit_key_##resource, sizeof(audit_key_##resource), "triggerprototype.tag["	\
				ZBX_FS_UI64 "]"#nested#resource, triggertagid);					\
	}													\
	else													\
	{													\
		THIS_SHOULD_NEVER_HAPPEN;									\
		return;												\
	}

#define PREPARE_AUDIT_TRIGGER_UPDATE_TAG(resource, type1, type2)						\
void	zbx_audit_trigger_update_json_update_tag_##resource(int audit_context_mode, zbx_uint64_t triggerid,	\
		int trigger_flags, zbx_uint64_t triggertagid, type1 resource##_old, type1 resource##_new)	\
{														\
	int	resource_type;											\
	char	audit_key_##resource[AUDIT_DETAILS_KEY_LEN];							\
														\
	RETURN_IF_AUDIT_OFF(audit_context_mode);								\
	resource_type = trigger_flag_to_resource_type(trigger_flags);						\
														\
	TRIGGER_RESOURCE_KEY_RESOLVE_TAG(resource,.)								\
														\
	zbx_audit_update_json_update_##type2(triggerid, AUDIT_TRIGGER_ID, audit_key_##resource, resource##_old,	\
			resource##_new);									\
}

PREPARE_AUDIT_TRIGGER_UPDATE_TAG(tag, const char*, string)
PREPARE_AUDIT_TRIGGER_UPDATE_TAG(value, const char*, string)

#undef PREPARE_AUDIT_TRIGGER_UPDATE_TAG

void	zbx_audit_trigger_update_json_update_trigger_tag_create_entry(int audit_context_mode, zbx_uint64_t triggerid,
		int trigger_flags, zbx_uint64_t triggertagid)
{
	int	resource_type;
	char	audit_key_[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF(audit_context_mode);

	resource_type = trigger_flag_to_resource_type(trigger_flags);

	TRIGGER_RESOURCE_KEY_RESOLVE_TAG(,)

	zbx_audit_update_json_append_no_value(triggerid, AUDIT_TRIGGER_ID, AUDIT_DETAILS_ACTION_UPDATE, audit_key_);
}
#undef TRIGGER_RESOURCE_KEY_RESOLVE_TAG
