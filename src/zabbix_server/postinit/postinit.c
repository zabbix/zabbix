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

#include "postinit.h"

#include "../db_lengths_constants.h"

#include "zbxcommon.h"
#include "zbxtypes.h"
#include "zbxtasks.h"
#include "zbxcachevalue.h"
#include "zbxcacheconfig.h"
#include "zbxdbwrap.h"
#include "zbxdb.h"
#include "zbxdbhigh.h"
#include "zbxexpr.h"
#include "zbxstr.h"
#include "zbxnum.h"
#include "zbxalgo.h"
#include "audit/zbxaudit.h"

#define ZBX_HIST_MACRO_NONE		(-1)
#define ZBX_HIST_MACRO_ITEM_VALUE	0
#define ZBX_HIST_MACRO_ITEM_LASTVALUE	1

/******************************************************************************
 *                                                                            *
 * Purpose: gets total number of triggers on system                           *
 *                                                                            *
 ******************************************************************************/
static int	get_trigger_count(void)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int		triggers_num;

	result = zbx_db_select("select count(*) from triggers");
	if (NULL != (row = zbx_db_fetch(result)))
		triggers_num = atoi(row[0]);
	else
		triggers_num = 0;
	zbx_db_free_result(result);

	return triggers_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if this is historical macro that cannot be expanded for    *
 *          bulk event name update                                            *
 *                                                                            *
 * Parameters: macro - [IN]                                                   *
 *                                                                            *
 * Return value: ZBX_HIST_MACRO_* defines                                     *
 *                                                                            *
 ******************************************************************************/
static int	is_historical_macro(const char *macro)
{
	if (0 == strncmp(macro, "ITEM.VALUE", ZBX_CONST_STRLEN("ITEM.VALUE")))
		return ZBX_HIST_MACRO_ITEM_VALUE;

	if (0 == strncmp(macro, "ITEM.LASTVALUE", ZBX_CONST_STRLEN("ITEM.LASTVALUE")))
		return ZBX_HIST_MACRO_ITEM_LASTVALUE;

	return ZBX_HIST_MACRO_NONE;
}

/******************************************************************************
 *                                                                            *
 * Purpose: translates historical macro to lld macro format                   *
 *                                                                            *
 * Parameters: macro - [IN] macro type (see ZBX_HIST_MACRO_* defines)         *
 *                                                                            *
 * Return value: macro                                                        *
 *                                                                            *
 * Comments: Some of the macros can be converted to different name.           *
 *                                                                            *
 ******************************************************************************/
static const char	*convert_historical_macro(int macro)
{
	/* When expanding macros for old events ITEM.LASTVALUE macro would */
	/* always expand to one (latest) value. Expanding it as ITEM.VALUE */
	/* makes more sense in this case.                                  */
	const char	*macros[] = {"#ITEM.VALUE", "#ITEM.VALUE"};

	return macros[macro];
}

/******************************************************************************
 *                                                                            *
 * Purpose: pre-process trigger name(description) by expanding non historical *
 *          macros                                                            *
 *                                                                            *
 * Parameters: trigger    - [IN]                                              *
 *             historical - [OUT] 1 - trigger name contains historical macros *
 *                                0 - otherwise                               *
 *                                                                            *
 * Comments: Some historical macros might be replaced with other macros to    *
 *           better match the trigger name at event creation time.            *
 *                                                                            *
 ******************************************************************************/
static void	preprocess_trigger_name(zbx_db_trigger *trigger, int *historical)
{
	int		pos = 0, macro_len;
	zbx_token_t	token;
	size_t		name_alloc, name_len, replace_alloc = 64, replace_offset, r, l;
	char		*replace;
	const char	*macro;
	zbx_db_event	event;

	*historical = FAIL;

	replace = (char *)zbx_malloc(NULL, replace_alloc);

	name_alloc = name_len = strlen(trigger->description) + 1;

	while (SUCCEED == zbx_token_find(trigger->description, pos, &token, ZBX_TOKEN_SEARCH_BASIC))
	{
		if (ZBX_TOKEN_MACRO == token.type || ZBX_TOKEN_FUNC_MACRO == token.type)
		{
			/* the macro excluding the opening and closing brackets {}, for example: ITEM.VALUE */
			if (ZBX_TOKEN_MACRO == token.type)
			{
				l = token.data.macro.name.l;
				r = token.data.macro.name.r;
			}
			else
			{
				l = token.data.func_macro.macro.l + 1;
				r = token.data.func_macro.macro.r - 1;
			}

			macro = trigger->description + l;

			int	macro_type;

			if (ZBX_HIST_MACRO_NONE != (macro_type = is_historical_macro(macro)))
			{
				if (0 != isdigit(*(trigger->description + r)))
					macro_len = r - l;
				else
					macro_len = r - l + 1;

				macro = convert_historical_macro(macro_type);

				token.loc.r += zbx_replace_mem_dyn(&trigger->description, &name_alloc, &name_len, l,
						macro_len, macro, strlen(macro));
				*historical = SUCCEED;
			}
		}
		pos = token.loc.r;
	}

	memset(&event, 0, sizeof(zbx_db_event));
	event.object = EVENT_OBJECT_TRIGGER;
	event.objectid = trigger->triggerid;
	event.trigger = *trigger;

	zbx_dc_um_handle_t		*um_handle = zbx_dc_open_user_macros_masked();

	zbx_substitute_macros_ext_search(ZBX_TOKEN_SEARCH_REFERENCES, &trigger->description, NULL, 0,
			&zbx_macro_trigger_desc_resolv, um_handle, &event, NULL);

	zbx_dc_close_user_macros(um_handle);

	if (SUCCEED == *historical)
	{
		pos = 0;
		name_alloc = name_len = strlen(trigger->description) + 1;

		while (SUCCEED == zbx_token_find(trigger->description, pos, &token, ZBX_TOKEN_SEARCH_BASIC))
		{
			if (ZBX_TOKEN_LLD_MACRO == token.type || ZBX_TOKEN_LLD_FUNC_MACRO == token.type)
			{
				if (ZBX_TOKEN_LLD_MACRO == token.type)
				{
					l = token.data.lld_macro.name.l;
					r = token.data.lld_macro.name.r;
				}
				else
				{
					l = token.data.lld_func_macro.macro.l + 2;
					r = token.data.lld_func_macro.macro.r - 1;
				}

				macro = trigger->description + l;

				if (ZBX_HIST_MACRO_NONE != is_historical_macro(macro))
				{
					macro_len = r - l + 1;
					replace_offset = 0;
					zbx_strncpy_alloc(&replace, &replace_alloc, &replace_offset, macro, macro_len);

					token.loc.r += zbx_replace_mem_dyn(&trigger->description, &name_alloc,
							&name_len, l - 1, macro_len + 1, replace, replace_offset);
				}
			}
			pos = token.loc.r;
		}
	}

	zbx_free(replace);
}

/******************************************************************************
 *                                                                            *
 * Purpose: updates event/problem names for trigger with bulk request         *
 *                                                                            *
 * Parameters: trigger    - [IN]                                              *
 *             sql        - [IN/OUT] sql query                                *
 *             sql_alloc  - [IN/OUT] sql query size                           *
 *             sql_offset - [IN/OUT] sql query length                         *
 *                                                                            *
 * Return value: SUCCEED - update was successful                              *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Comments: Event names for triggers without historical macros will be the   *
 *           same and can be updated with a single sql query.                 *
 *                                                                            *
 ******************************************************************************/
static int	process_event_bulk_update(const zbx_db_trigger *trigger, char **sql, size_t *sql_alloc,
		size_t *sql_offset)
{
	char	*name_esc;
	int	ret;

	name_esc = zbx_db_dyn_escape_string_len(trigger->description, EVENT_NAME_LEN);

	zbx_snprintf_alloc(sql, sql_alloc, sql_offset,
			"update events"
			" set name='%s'"
			" where source=%d"
				" and object=%d"
				" and objectid=" ZBX_FS_UI64 ";\n",
			name_esc, EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER, trigger->triggerid);

	if (SUCCEED == (ret = zbx_db_execute_overflowed_sql(sql, sql_alloc, sql_offset)))
	{
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset,
				"update problem"
				" set name='%s'"
				" where source=%d"
					" and object=%d"
					" and objectid=" ZBX_FS_UI64 ";\n",
				name_esc, EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER, trigger->triggerid);

		ret = zbx_db_execute_overflowed_sql(sql, sql_alloc, sql_offset);
	}

	zbx_free(name_esc);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Updates event/problem names for a trigger with separate requests  *
 *          for each event.                                                   *
 *                                                                            *
 * Parameters: trigger    - [IN]                                              *
 *             sql        - [IN/OUT] sql query                                *
 *             sql_alloc  - [IN/OUT] sql query size                           *
 *             sql_offset - [IN/OUT] sql query length                         *
 *                                                                            *
 * Return value: SUCCEED - update was successful                              *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Comments: Event names for triggers with historical macros might differ and *
 *           historical macros in trigger name must be expanded for each      *
 *           event.                                                           *
 *                                                                            *
 ******************************************************************************/
static int	process_event_update(const zbx_db_trigger *trigger, char **sql, size_t *sql_alloc, size_t *sql_offset)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_db_event		event;
	char			*name, *name_esc;
	int			ret = SUCCEED;
	zbx_dc_um_handle_t	*um_handle = zbx_dc_open_user_macros_masked();

	memset(&event, 0, sizeof(zbx_db_event));

	result = zbx_db_select("select eventid,source,object,objectid,clock,value,acknowledged,ns,name"
			" from events"
			" where source=%d"
				" and object=%d"
				" and objectid=" ZBX_FS_UI64
			" order by eventid",
			EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER, trigger->triggerid);

	while (SUCCEED == ret && NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(event.eventid, row[0]);
		event.source = atoi(row[1]);
		event.object = atoi(row[2]);
		ZBX_STR2UINT64(event.objectid, row[3]);
		event.clock = atoi(row[4]);
		event.value = atoi(row[5]);
		event.acknowledged = atoi(row[6]);
		event.ns = atoi(row[7]);
		event.name = row[8];

		event.trigger = *trigger;

		name = zbx_strdup(NULL, trigger->description);

		zbx_substitute_macros_ext_search(ZBX_TOKEN_SEARCH_REFERENCES, &name, NULL, 0,
				&zbx_macro_trigger_desc_resolv, um_handle, &event, NULL);

		name_esc = zbx_db_dyn_escape_string_len(name, EVENT_NAME_LEN);

		zbx_snprintf_alloc(sql, sql_alloc, sql_offset,
				"update events"
				" set name='%s'"
				" where eventid=" ZBX_FS_UI64 ";\n",
				name_esc, event.eventid);

		if (SUCCEED == (ret = zbx_db_execute_overflowed_sql(sql, sql_alloc, sql_offset)))
		{
			zbx_snprintf_alloc(sql, sql_alloc, sql_offset,
					"update problem"
					" set name='%s'"
					" where eventid=" ZBX_FS_UI64 ";\n",
					name_esc, event.eventid);

			ret = zbx_db_execute_overflowed_sql(sql, sql_alloc, sql_offset);
		}

		zbx_free(name_esc);
		zbx_free(name);
	}

	zbx_db_free_result(result);
	zbx_dc_close_user_macros(um_handle);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: updates event names in events and problem tables                  *
 *                                                                            *
 * Return value: SUCCEED - update was successful                              *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	update_event_names(void)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_db_trigger		trigger;
	int			ret = SUCCEED, triggers_num, processed_num = 0, last_completed = 0;
	char			*sql;
	size_t			sql_alloc = 4096, sql_offset = 0;
	zbx_dc_um_handle_t	*um_handle;

	zabbix_log(LOG_LEVEL_WARNING, "starting event name update forced by database upgrade");

	if (0 == (triggers_num = get_trigger_count()))
		goto out;

	memset(&trigger, 0, sizeof(zbx_db_trigger));

	sql = (char *)zbx_malloc(NULL, sql_alloc);

	result = zbx_db_select(
			"select triggerid,description,expression,priority,comments,url,url_name,"
				"recovery_expression,recovery_mode,value"
			" from triggers"
			" order by triggerid");

	um_handle = zbx_dc_open_user_macros();

	while (SUCCEED == ret && NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(trigger.triggerid, row[0]);
		trigger.description = zbx_strdup(NULL, row[1]);
		trigger.expression = zbx_strdup(NULL, row[2]);
		ZBX_STR2UCHAR(trigger.priority, row[3]);
		trigger.comments = zbx_strdup(NULL, row[4]);
		trigger.url = zbx_strdup(NULL, row[5]);
		trigger.url_name = zbx_strdup(NULL, row[6]);
		trigger.recovery_expression = zbx_strdup(NULL, row[7]);
		ZBX_STR2UCHAR(trigger.recovery_mode, row[8]);
		ZBX_STR2UCHAR(trigger.value, row[9]);

		int	historical;

		preprocess_trigger_name(&trigger, &historical);

		if (FAIL == historical)
			ret = process_event_bulk_update(&trigger, &sql, &sql_alloc, &sql_offset);
		else
			ret = process_event_update(&trigger, &sql, &sql_alloc, &sql_offset);

		zbx_db_trigger_clean(&trigger);

		processed_num++;

		int	completed;

		if (last_completed != (completed = (int)(100.0 * processed_num / triggers_num)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "completed %d%% of event name update", completed);
			last_completed = completed;
		}
	}

	zbx_dc_close_user_macros(um_handle);

	if (SUCCEED == ret)
	{
		if (ZBX_DB_OK > zbx_db_flush_overflowed_sql(sql, sql_offset))
			ret = FAIL;
	}

	zbx_db_free_result(result);

	zbx_free(sql);
out:
	if (SUCCEED == ret)
		zabbix_log(LOG_LEVEL_WARNING, "event name update completed");
	else
		zabbix_log(LOG_LEVEL_WARNING, "event name update failed");

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: copy nested host prototypes from templates to discovered hosts    *
 *                                                                            *
 ******************************************************************************/
static void	copy_nested_host_prototypes(void)
{
	zbx_db_result_t		result;
	zbx_db_row_t		row;
	zbx_uint64_t		last_hostid = 0, hostid, templateid;
	zbx_vector_uint64_t	templateids;
	zbx_db_insert_t		db_insert;

	zabbix_log(LOG_LEVEL_WARNING, "starting nested host prototype update forced by database upgrade");

	zbx_vector_uint64_create(&templateids);

	zbx_db_insert_prepare(&db_insert, "hosts_templates",  "hosttemplateid", "hostid", "templateid",
			"link_type", (char *)NULL);

	/* 4 - ZBX_FLAG_DISCOVERY_CREATED */
	result = zbx_db_select("select h.hostid,ht.templateid"
				" from hosts_templates ht"
					" join hosts h on ht.hostid=h.hostid"
				" where h.flags=4"
					" and exists (select null from items i,host_discovery hd"
							" where i.hostid=ht.templateid"
								" and hd.lldruleid=i.itemid)"
				" order by hostid");

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(hostid, row[0]);

		if (hostid != last_hostid)
		{
			if (0 != last_hostid)
			{
				zbx_db_copy_template_host_prototypes(last_hostid, &templateids, ZBX_AUDIT_LLD_CONTEXT,
						&db_insert);
			}

			last_hostid = hostid;
			zbx_vector_uint64_clear(&templateids);
		}

		ZBX_STR2UINT64(templateid, row[1]);
		zbx_vector_uint64_append(&templateids, templateid);
	}
	zbx_db_free_result(result);

	if (0 != last_hostid)
	{
		if (0 != templateids.values_num)
		{
			zbx_db_copy_template_host_prototypes(last_hostid, &templateids, ZBX_AUDIT_LLD_CONTEXT,
					&db_insert);
		}

		zbx_db_insert_execute(&db_insert);
	}

	zbx_db_insert_clean(&db_insert);
	zbx_vector_uint64_destroy(&templateids);

	zabbix_log(LOG_LEVEL_WARNING, "nested host prototype update completed");
}

/******************************************************************************
 *                                                                            *
 * Purpose: processes post initialization tasks                               *
 *                                                                            *
 * Return value: SUCCEED - update was successful                              *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_check_postinit_tasks(char **error)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	int		type, ret = SUCCEED;

	/* avoid filling value cache with unnecessary data during event name update */
	zbx_vc_disable();

	result = zbx_db_select("select taskid,type from task where type in (%d,%d) and status=%d order by taskid",
			ZBX_TM_TASK_UPDATE_EVENTNAMES, ZBX_TM_TASK_COPY_NESTED_HOST_PROTOTYPES,
			ZBX_TM_STATUS_NEW);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		zbx_db_begin();

		type = atoi(row[1]);

		switch (type)
		{
			case ZBX_TM_TASK_UPDATE_EVENTNAMES:
				if (FAIL == (ret = update_event_names()))
					*error = zbx_strdup(*error, "cannot update event names");
				break;
			case ZBX_TM_TASK_COPY_NESTED_HOST_PROTOTYPES:
				copy_nested_host_prototypes();
				break;
		}

		if (SUCCEED == ret)
		{
			zbx_db_execute("delete from task where taskid=%s", row[0]);
			zbx_db_commit();
		}
		else
		{
			zbx_db_rollback();
			break;
		}
	}

	zbx_db_free_result(result);

	zbx_vc_enable();

	return ret;
}
