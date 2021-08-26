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

#define	PREPARE_AUDIT_TRIGGER(funcname, auditentry, audit_resource_flag)					\
void	zbx_audit_##funcname##_create_entry(int audit_action, zbx_uint64_t triggerid, const char *name)		\
{														\
	zbx_audit_entry_t	local_audit_trigger_entry, **found_audit_trigger_entry;				\
	zbx_audit_entry_t	*local_audit_trigger_entry_x = &local_audit_trigger_entry;			\
														\
	RETURN_IF_AUDIT_OFF();											\
														\
	local_audit_trigger_entry.id = triggerid;								\
														\
	found_audit_trigger_entry = (zbx_audit_entry_t**)zbx_hashset_search(zbx_get_audit_hashset(),		\
			&(local_audit_trigger_entry_x));							\
	if (NULL == found_audit_trigger_entry)									\
	{													\
		zbx_audit_entry_t	*local_audit_trigger_entry_insert;					\
														\
		local_audit_trigger_entry_insert = (zbx_audit_entry_t*)zbx_malloc(NULL,				\
				sizeof(zbx_audit_entry_t));							\
		local_audit_trigger_entry_insert->id = triggerid;						\
		local_audit_trigger_entry_insert->name = zbx_strdup(NULL, name);				\
		local_audit_trigger_entry_insert->audit_action = audit_action;					\
		local_audit_trigger_entry_insert->resource_type = audit_resource_flag;				\
		zbx_json_init(&(local_audit_trigger_entry_insert->details_json), ZBX_JSON_STAT_BUF_LEN);	\
		zbx_hashset_insert(zbx_get_audit_hashset(), &local_audit_trigger_entry_insert,			\
				sizeof(local_audit_trigger_entry_insert));					\
														\
		if (AUDIT_ACTION_ADD == audit_action)								\
		{												\
			zbx_audit_update_json_append_uint64(triggerid, AUDIT_DETAILS_ACTION_ADD,		\
					#auditentry".triggerid", triggerid);					\
		}												\
	}													\
}

PREPARE_AUDIT_TRIGGER(trigger, trigger, AUDIT_RESOURCE_TRIGGER)
PREPARE_AUDIT_TRIGGER(trigger_prototype, triggerprototype, AUDIT_RESOURCE_TRIGGER_PROTOTYPE)
#undef PREPARE_AUDIT_TRIGGER

#define TR_OR_TRP(s) (ZBX_FLAG_DISCOVERY_NORMAL == flags) ? "trigger."#s : "triggerprototype:"#s
void	zbx_audit_trigger_update_json_add_data(zbx_uint64_t triggerid, zbx_uint64_t templateid, unsigned char recovery_mode,
		unsigned char status, unsigned char type, zbx_uint64_t value, zbx_uint64_t state,
		unsigned char priority, const char *comments, const char *url, unsigned char flags,
		unsigned char correlation_mode, const char *correlation_tag, unsigned char manual_close,
		const char *opdata, unsigned char discover, const char *event_name)
{
	char	audit_key[AUDIT_DETAILS_KEY_LEN], audit_key_event_name[AUDIT_DETAILS_KEY_LEN],
		audit_key_opdata[AUDIT_DETAILS_KEY_LEN],
		audit_key_comments[AUDIT_DETAILS_KEY_LEN], audit_key_flags[AUDIT_DETAILS_KEY_LEN],
		audit_key_priority[AUDIT_DETAILS_KEY_LEN], audit_key_state[AUDIT_DETAILS_KEY_LEN],
		audit_key_status[AUDIT_DETAILS_KEY_LEN], audit_key_templateid[AUDIT_DETAILS_KEY_LEN],
		audit_key_type[AUDIT_DETAILS_KEY_LEN], audit_key_url[AUDIT_DETAILS_KEY_LEN],
		audit_key_value[AUDIT_DETAILS_KEY_LEN], audit_key_recovery_mode[AUDIT_DETAILS_KEY_LEN],
		audit_key_correlation_mode[AUDIT_DETAILS_KEY_LEN], audit_key_correlation_tag[AUDIT_DETAILS_KEY_LEN],
		audit_key_manual_close[AUDIT_DETAILS_KEY_LEN], audit_key_discover[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();
	
#define AUDIT_KEY_SNPRINTF(r) zbx_snprintf(audit_key_##r, sizeof(audit_key_##r), TR_OR_TRP(r));

	AUDIT_KEY_SNPPRINTF(event_name)
	AUDIT_KEY_SNPPRINTF(opdata)
	AUDIT_KEY_SNPPRINTF(comments)
	AUDIT_KEY_SNPPRINTF(flags)
	AUDIT_KEY_SNPPRINTF(priority)
	AUDIT_KEY_SNPPRINTF(state)
	AUDIT_KEY_SNPPRINTF(status)
	AUDIT_KEY_SNPPRINTF(templateid)
	AUDIT_KEY_SNPPRINTF(type)
	AUDIT_KEY_SNPPRINTF(url)
	AUDIT_KEY_SNPPRINTF(value)
	AUDIT_KEY_SNPPRINTF(recovery_mode)
	AUDIT_KEY_SNPPRINTF(correlation_mode)
	AUDIT_KEY_SNPPRINTF(correlation_tag)
	AUDIT_KEY_SNPPRINTF(manual_close)	  
	if (ZBX_FLAG_DISCOVERY_PROTOTYPE == flags)
		AUDIT_KEY_SNPPRINTF(discover)
#undef AUDIT_KEY_SNPRINTF

	zbx_audit_update_json_append_no_value(triggerid, AUDIT_DETAILS_ACTION_ADD, audit_key);
#define ADD_STR(r) zbx_audit_update_json_append_string(triggerid, AUDIT_DETAILS_ACTION_ADD, audit_key_##r, r)
#define ADD_UINT64(r) zbx_audit_update_json_append_uint64(triggerid, AUDIT_DETAILS_ACTION_ADD, audit_key_##r, r)
#define ADD_INT(r) zbx_audit_update_json_append_int(triggerid, AUDIT_DETAILS_ACTION_ADD, audit_key_##r, r)
	ADD_STR(event_name)
	ADD_STR(opdata)
	ADD_STR(comments)
	ADD_INT(flags)
	ADD_INT(priority)
	ADD_INT(state)
	ADD_INT(status)
	ADD_UINT64(templateid)
	ADD_INT(type)
	ADD_STRING(url)
	ADD_INT(value)
	ADD_INT(recovery_mode)
	ADD_INT(correlation_mode)
	ADD_STR(correlation_tag)
	ADD_INT(manual_close)
	ADD_UINT64(discover)
#undef ADD_STR
#undef ADD_UINT64
	  }

void	zbx_audit_trigger_update_json_add_expr(zbx_uint64_t triggerid, unsigned char flags, const char *expression)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();	

	zbx_snprintf(buf, sizeof(buf), TR_OR_TRP(expression));
	zbx_audit_update_json_append_string(triggerid, AUDIT_DETAILS_ACTION_ADD, buf, expression);
}


void	zbx_audit_trigger_update_json_add_rexpr(zbx_uint64_t triggerid, unsigned char flags, const char recovery_expression)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();	

	zbx_snprintf(buf, sizeof(buf), TR_OR_TRP(recovery_expression));
	zbx_audit_update_json_append_string(triggerid, AUDIT_DETAILS_ACTION_ADD, buf, recovery_expression);
}

#define PREPARE_AUDIT_TRIGGER_UPDATE(resource, type1, type2)		\
void	zbx_audit_trigger_update_json_update_##resource(zbx_uint64_t triggerid, unsigned char flags,		\
		type1 resource##_old, type1 resource##_new)
{
	char	buf[AUDIT_DETAILS_KEY_LEN];

	RETURN_IF_AUDIT_OFF();

	zbx_snprintf(buf, sizeof(buf), TR_OR_TRP(resource));

	zbx_audit_update_json_update_##type2(triggerid, buf, resource##_old, resource##_new);
}

PREPARE_AUDIT_TRIGGER_UPDATE(flags, int, int)
PREPARE_AUDIT_TRIGGER_UPDATE(recovery_mode, int, int)
PREPARE_AUDIT_TRIGGER_UPDATE(correlation_mode, int, int)
PREPARE_AUDIT_TRIGGER_UPDATE(manual_close, int, int)
PREPARE_AUDIT_TRIGGER_UPDATE(opdata, const char*, string)
PREPARE_AUDIT_TRIGGER_UPDATE(discover, int, int)
PREPARE_AUDIT_TRIGGER_UPDATE(event_name, const char*, string)
PREPARE_AUDIT_TRIGGER_UPDATE(templateid, zbx_uint64_t, uint64)

#undef PREPARE_AUDIT_ITEM_UPDATE
#undef TR_OR_TRP
