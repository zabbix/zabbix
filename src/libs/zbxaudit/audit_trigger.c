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

#include "dbcache.h"

#include "log.h"
#include "audit_trigger.h"

static int	trigger_flag_to_resource_type(int flag)
{
	if (ZBX_FLAG_DISCOVERY_NORMAL == flag || ZBX_FLAG_DISCOVERY_CREATED == flag)
	{
		return AUDIT_RESOURCE_TRIGGER;
	}
	else if (ZBX_FLAG_DISCOVERY_PROTOTYPE == flag)
	{
		return AUDIT_RESOURCE_TRIGGER_PROTOTYPE;
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "unexpected audit detected: ->%d<-", flag);
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}
}

#define TR_OR_TRP(s) (AUDIT_RESOURCE_TRIGGER == resource_type) ? "trigger."#s : "triggerprototype."#s

void	zbx_audit_trigger_create_entry(int audit_action, zbx_uint64_t triggerid, const char *name, int flags)
{
	int			resource_type;
	zbx_audit_entry_t	local_audit_trigger_entry, **found_audit_trigger_entry;
	zbx_audit_entry_t	*local_audit_trigger_entry_x = &local_audit_trigger_entry;

	RETURN_IF_AUDIT_OFF();

	resource_type = trigger_flag_to_resource_type(flags);

	local_audit_trigger_entry.id = triggerid;

	found_audit_trigger_entry = (zbx_audit_entry_t**)zbx_hashset_search(zbx_get_audit_hashset(),
			&(local_audit_trigger_entry_x));

	if (NULL == found_audit_trigger_entry)
	{
		zbx_audit_entry_t	*local_audit_trigger_entry_insert;

		local_audit_trigger_entry_insert = (zbx_audit_entry_t*)zbx_malloc(NULL,
				sizeof(zbx_audit_entry_t));
		local_audit_trigger_entry_insert->id = triggerid;
		local_audit_trigger_entry_insert->name = zbx_strdup(NULL, name);
		local_audit_trigger_entry_insert->audit_action = audit_action;
		local_audit_trigger_entry_insert->resource_type = resource_type;
		zbx_json_init(&(local_audit_trigger_entry_insert->details_json), ZBX_JSON_STAT_BUF_LEN);
		zbx_hashset_insert(zbx_get_audit_hashset(), &local_audit_trigger_entry_insert,
				sizeof(local_audit_trigger_entry_insert));

		if (AUDIT_ACTION_ADD == audit_action)
		{
			zbx_audit_update_json_append_uint64(triggerid, AUDIT_DETAILS_ACTION_ADD,
					TR_OR_TRP(triggerid), triggerid);
		}
	}
}

void	zbx_audit_trigger_update_json_add_data(zbx_uint64_t triggerid, zbx_uint64_t templateid,
		unsigned char recovery_mode, unsigned char status, unsigned char type, zbx_uint64_t value,
		zbx_uint64_t state, unsigned char priority, const char *comments, const char *url, int flags,
		unsigned char correlation_mode, const char *correlation_tag, unsigned char manual_close,
		const char *opdata, unsigned char discover, const char *event_name)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_event_name[AUDIT_DETAILS_KEY_LEN],
		audit_key_opdata[AUDIT_DETAILS_KEY_LEN], audit_key_comments[AUDIT_DETAILS_KEY_LEN],
		audit_key_flags[AUDIT_DETAILS_KEY_LEN], audit_key_priority[AUDIT_DETAILS_KEY_LEN],
		audit_key_state[AUDIT_DETAILS_KEY_LEN], audit_key_status[AUDIT_DETAILS_KEY_LEN],
		audit_key_templateid[AUDIT_DETAILS_KEY_LEN], audit_key_type[AUDIT_DETAILS_KEY_LEN],
		audit_key_url[AUDIT_DETAILS_KEY_LEN], audit_key_value[AUDIT_DETAILS_KEY_LEN],
		audit_key_recovery_mode[AUDIT_DETAILS_KEY_LEN], audit_key_correlation_mode[AUDIT_DETAILS_KEY_LEN],
		audit_key_correlation_tag[AUDIT_DETAILS_KEY_LEN], audit_key_manual_close[AUDIT_DETAILS_KEY_LEN],
		audit_key_discover[AUDIT_DETAILS_KEY_LEN];
	int	resource_type;

	RETURN_IF_AUDIT_OFF();

	resource_type = trigger_flag_to_resource_type(flags);

	zbx_snprintf(audit_key, sizeof(audit_key), (ZBX_FLAG_DISCOVERY_NORMAL == flags) ? "trigger" :
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
	AUDIT_KEY_SNPRINTF(value)
	AUDIT_KEY_SNPRINTF(recovery_mode)
	AUDIT_KEY_SNPRINTF(correlation_mode)
	AUDIT_KEY_SNPRINTF(correlation_tag)
	AUDIT_KEY_SNPRINTF(manual_close)
	if (ZBX_FLAG_DISCOVERY_PROTOTYPE == flags)
		AUDIT_KEY_SNPRINTF(discover)
#undef AUDIT_KEY_SNPRINTF
	zbx_audit_update_json_append_no_value(triggerid, AUDIT_DETAILS_ACTION_ADD, audit_key);
#define ADD_STR(r) zbx_audit_update_json_append_string(triggerid, AUDIT_DETAILS_ACTION_ADD, audit_key_##r, r);
#define ADD_UINT64(r) zbx_audit_update_json_append_uint64(triggerid, AUDIT_DETAILS_ACTION_ADD, audit_key_##r, r);
#define ADD_INT(r) zbx_audit_update_json_append_int(triggerid, AUDIT_DETAILS_ACTION_ADD, audit_key_##r, r);
	ADD_STR(event_name)
	ADD_STR(opdata)
	ADD_STR(comments)
	ADD_INT(flags)
	ADD_INT(priority)
	ADD_UINT64(state)
	ADD_INT(status)
	ADD_UINT64(templateid)
	ADD_INT(type)
	ADD_STR(url)
	ADD_UINT64(value)
	ADD_INT(recovery_mode)
	ADD_INT(correlation_mode)
	ADD_STR(correlation_tag)
	ADD_INT(manual_close)

	if (ZBX_FLAG_DISCOVERY_PROTOTYPE == flags)
		ADD_UINT64(discover)

#undef ADD_STR
#undef ADD_UINT64
#undef ADD_INT
}

void	zbx_audit_trigger_update_json_add_expr(zbx_uint64_t triggerid, int flags, const char *expression)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];
	int	resource_type;

	RETURN_IF_AUDIT_OFF();

	resource_type = trigger_flag_to_resource_type(flags);

	zbx_snprintf(buf, sizeof(buf), TR_OR_TRP(expression));
	zbx_audit_update_json_append_string(triggerid, AUDIT_DETAILS_ACTION_ADD, buf, expression);
}

void	zbx_audit_trigger_update_json_add_rexpr(zbx_uint64_t triggerid, int flags, const char *recovery_expression)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];
	int	resource_type;

	RETURN_IF_AUDIT_OFF();

	resource_type = trigger_flag_to_resource_type(flags);

	zbx_snprintf(buf, sizeof(buf), TR_OR_TRP(recovery_expression));
	zbx_audit_update_json_append_string(triggerid, AUDIT_DETAILS_ACTION_ADD, buf, recovery_expression);
}

#define PREPARE_AUDIT_TRIGGER_UPDATE(resource, type1, type2)							\
void	zbx_audit_trigger_update_json_update_##resource(zbx_uint64_t triggerid, int flags,			\
		type1 resource##_old, type1 resource##_new)							\
{														\
	char	buf[AUDIT_DETAILS_KEY_LEN];									\
	int	resource_type;											\
														\
	RETURN_IF_AUDIT_OFF();											\
														\
	resource_type = trigger_flag_to_resource_type(flags);							\
														\
	zbx_snprintf(buf, sizeof(buf), TR_OR_TRP(resource));							\
														\
	zbx_audit_update_json_update_##type2(triggerid, buf, resource##_old, resource##_new);			\
}

PREPARE_AUDIT_TRIGGER_UPDATE(recovery_mode, int, int)
PREPARE_AUDIT_TRIGGER_UPDATE(correlation_mode, int, int)
PREPARE_AUDIT_TRIGGER_UPDATE(manual_close, int, int)
PREPARE_AUDIT_TRIGGER_UPDATE(opdata, const char*, string)
PREPARE_AUDIT_TRIGGER_UPDATE(discover, int, int)
PREPARE_AUDIT_TRIGGER_UPDATE(event_name, const char*, string)
PREPARE_AUDIT_TRIGGER_UPDATE(templateid, zbx_uint64_t, uint64)

#undef PREPARE_AUDIT_ITEM_UPDATE
#undef TR_OR_TRP

/******************************************************************************
 *                                                                            *
 * Function: zbx_audit_DBselect_delete_for_trigger                            *
 *                                                                            *
 * Parameters: sql - [IN] sql statement                                       *
 *             ids - [OUT] sorted list of selected uint64 values              *
 *                                                                            *
 * Return value: SUCCEED - query SUCCEEDED                                    *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_audit_DBselect_delete_for_trigger(const char *sql, zbx_vector_uint64_t *ids)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	id;

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(id, row[0]);
		zbx_vector_uint64_append(ids, id);

		zbx_audit_trigger_create_entry(AUDIT_ACTION_DELETE, id, row[1], atoi(row[2]));
	}

	DBfree_result(result);

	zbx_vector_uint64_sort(ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

void	zbx_audit_trigger_update_json_add_dependency(int flags, zbx_uint64_t triggerdepid,
		zbx_uint64_t triggerid, zbx_uint64_t triggerid_up)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_triggerid_up[AUDIT_DETAILS_KEY_LEN];
	int	resource_type;

	RETURN_IF_AUDIT_OFF();

	resource_type = trigger_flag_to_resource_type(flags);

	if (AUDIT_RESOURCE_TRIGGER == resource_type)
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

	zbx_audit_update_json_append_no_value(triggerid, AUDIT_DETAILS_ACTION_ADD, audit_key);
	zbx_audit_update_json_append_uint64(triggerid, AUDIT_DETAILS_ACTION_ADD, audit_key_triggerid_up, triggerid_up);
}

void	zbx_audit_trigger_update_json_add_tags_and_values(zbx_uint64_t triggerid, int flags, zbx_uint64_t triggertagid,
		const char *tag, const char *value)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_tag[AUDIT_DETAILS_KEY_LEN],
		audit_key_value[AUDIT_DETAILS_KEY_LEN];
	int	resource_type;

	RETURN_IF_AUDIT_OFF();

	resource_type = trigger_flag_to_resource_type(flags);

	if (AUDIT_RESOURCE_TRIGGER == flags)
	{
		zbx_snprintf(audit_key, AUDIT_DETAILS_KEY_LEN, "trigger.tags[" ZBX_FS_UI64 "]", triggertagid);
		zbx_snprintf(audit_key_tag, AUDIT_DETAILS_KEY_LEN, "trigger.tags[" ZBX_FS_UI64 "].tag", triggertagid);
		zbx_snprintf(audit_key_value, AUDIT_DETAILS_KEY_LEN, "trigger.tags[" ZBX_FS_UI64 "].value",
				triggertagid);
	}
	else
	{
		zbx_snprintf(audit_key, AUDIT_DETAILS_KEY_LEN, "triggerprototype.tags[" ZBX_FS_UI64 "]", triggertagid);
		zbx_snprintf(audit_key_tag, AUDIT_DETAILS_KEY_LEN, "triggerprototype.tags[" ZBX_FS_UI64 "].tag",
				triggertagid);
		zbx_snprintf(audit_key_value, AUDIT_DETAILS_KEY_LEN, "triggerprototype.tags[" ZBX_FS_UI64 "].value",
				triggertagid);
	}

	zbx_audit_update_json_append_no_value(triggerid, AUDIT_DETAILS_ACTION_ADD, audit_key);
	zbx_audit_update_json_append_string(triggerid, AUDIT_DETAILS_ACTION_ADD, audit_key_tag, tag);
	zbx_audit_update_json_append_string(triggerid, AUDIT_DETAILS_ACTION_ADD, audit_key_value, value);
}

void	zbx_audit_trigger_update_json_delete_tags(zbx_uint64_t triggerid, int flags, zbx_uint64_t triggertagid)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN];
	int	resource_type;

	RETURN_IF_AUDIT_OFF();

	resource_type = trigger_flag_to_resource_type(flags);

	if (AUDIT_RESOURCE_TRIGGER == resource_type)
		zbx_snprintf(audit_key, AUDIT_DETAILS_KEY_LEN, "trigger.tags[" ZBX_FS_UI64 "]", triggertagid);
	else
		zbx_snprintf(audit_key, AUDIT_DETAILS_KEY_LEN, "triggerprototype.tags[" ZBX_FS_UI64 "]", triggertagid);

	zbx_audit_update_json_append_no_value(triggerid, AUDIT_DETAILS_ACTION_DELETE, audit_key);
}
