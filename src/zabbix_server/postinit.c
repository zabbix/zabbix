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

#include "postinit.h"

#include "db_lengths.h"
#include "zbxtasks.h"
#include "log.h"
#include "zbxserver.h"

#define ZBX_HIST_MACRO_NONE		(-1)
#define ZBX_HIST_MACRO_ITEM_VALUE	0
#define ZBX_HIST_MACRO_ITEM_LASTVALUE	1

/******************************************************************************
 *                                                                            *
 * Purpose: gets the total number of triggers on system                       *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: The total number of triggers on system.                      *
 *                                                                            *
 ******************************************************************************/
static int	get_trigger_count(void)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		triggers_num;

	result = DBselect("select count(*) from triggers");
	if (NULL != (row = DBfetch(result)))
		triggers_num = atoi(row[0]);
	else
		triggers_num = 0;
	DBfree_result(result);

	return triggers_num;
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if this is historical macro that cannot be expanded for    *
 *          bulk event name update                                            *
 *                                                                            *
 * Parameters: macro      - [IN] the macro name                               *
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
 * Parameters: macro - [IN] the macro type (see ZBX_HIST_MACRO_* defines)     *
 *                                                                            *
 * Return value: the macro                                                    *
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
 * Parameters: trigger    - [IN] the trigger                                  *
 *             historical - [OUT] 1 - trigger name contains historical macros *
 *                                0 - otherwise                               *
 *                                                                            *
 * Comments: Some historical macros might be replaced with other macros to    *
 *           better match the trigger name at event creation time.            *
 *                                                                            *
 ******************************************************************************/
static void	preprocess_trigger_name(ZBX_DB_TRIGGER *trigger, int *historical)
{
	int		pos = 0, macro_len, macro_type;
	zbx_token_t	token;
	size_t		name_alloc, name_len, replace_alloc = 64, replace_offset, r, l;
	char		*replace;
	const char	*macro;
	ZBX_DB_EVENT	event;

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

	memset(&event, 0, sizeof(ZBX_DB_EVENT));
	event.object = EVENT_OBJECT_TRIGGER;
	event.objectid = trigger->triggerid;
	event.trigger = *trigger;

	zbx_substitute_simple_macros(NULL, &event, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
			&trigger->description, MACRO_TYPE_TRIGGER_DESCRIPTION, NULL, 0);

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
 * Purpose: update event/problem names for a trigger with bulk request        *
 *                                                                            *
 * Parameters: trigger    - [IN] the trigger                                  *
 *             sql        - [IN/OUT] the sql query                            *
 *             sql_alloc  - [IN/OUT] the sql query size                       *
 *             sql_offset - [IN/OUT] the sql query length                     *
 *                                                                            *
 * Return value: SUCCEED - the update was successful                          *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Comments: Event names for triggers without historical macros will be the   *
 *           same and can be updated with a single sql query.                 *
 *                                                                            *
 ******************************************************************************/
static int	process_event_bulk_update(const ZBX_DB_TRIGGER *trigger, char **sql, size_t *sql_alloc,
		size_t *sql_offset)
{
	char	*name_esc;
	int	ret;

	name_esc = DBdyn_escape_string_len(trigger->description, EVENT_NAME_LEN);

	zbx_snprintf_alloc(sql, sql_alloc, sql_offset,
			"update events"
			" set name='%s'"
			" where source=%d"
				" and object=%d"
				" and objectid=" ZBX_FS_UI64 ";\n",
			name_esc, EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER, trigger->triggerid);

	if (SUCCEED == (ret = DBexecute_overflowed_sql(sql, sql_alloc, sql_offset)))
	{
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset,
				"update problem"
				" set name='%s'"
				" where source=%d"
					" and object=%d"
					" and objectid=" ZBX_FS_UI64 ";\n",
				name_esc, EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER, trigger->triggerid);

		ret = DBexecute_overflowed_sql(sql, sql_alloc, sql_offset);
	}

	zbx_free(name_esc);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: update event/problem names for a trigger with separate requests   *
 *          for each event                                                    *
 *                                                                            *
 * Parameters: trigger    - [IN] the trigger                                  *
 *             sql        - [IN/OUT] the sql query                            *
 *             sql_alloc  - [IN/OUT] the sql query size                       *
 *             sql_offset - [IN/OUT] the sql query length                     *
 *                                                                            *
 * Return value: SUCCEED - the update was successful                          *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Comments: Event names for triggers with historical macros might differ and *
 *           historical macros in trigger name must be expanded for each      *
 *           event.                                                           *
 *                                                                            *
 ******************************************************************************/
static int	process_event_update(const ZBX_DB_TRIGGER *trigger, char **sql, size_t *sql_alloc, size_t *sql_offset)
{
	DB_RESULT	result;
	DB_ROW		row;
	ZBX_DB_EVENT	event;
	char		*name, *name_esc;
	int		ret = SUCCEED;

	memset(&event, 0, sizeof(ZBX_DB_EVENT));

	result = DBselect("select eventid,source,object,objectid,clock,value,acknowledged,ns,name"
			" from events"
			" where source=%d"
				" and object=%d"
				" and objectid=" ZBX_FS_UI64
			" order by eventid",
			EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER, trigger->triggerid);

	while (SUCCEED == ret && NULL != (row = DBfetch(result)))
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

		zbx_substitute_simple_macros(NULL, &event, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
				&name, MACRO_TYPE_TRIGGER_DESCRIPTION, NULL, 0);

		name_esc = DBdyn_escape_string_len(name, EVENT_NAME_LEN);

		zbx_snprintf_alloc(sql, sql_alloc, sql_offset,
				"update events"
				" set name='%s'"
				" where eventid=" ZBX_FS_UI64 ";\n",
				name_esc, event.eventid);

		if (SUCCEED == (ret = DBexecute_overflowed_sql(sql, sql_alloc, sql_offset)))
		{
			zbx_snprintf_alloc(sql, sql_alloc, sql_offset,
					"update problem"
					" set name='%s'"
					" where eventid=" ZBX_FS_UI64 ";\n",
					name_esc, event.eventid);

			ret = DBexecute_overflowed_sql(sql, sql_alloc, sql_offset);
		}

		zbx_free(name_esc);
		zbx_free(name);
	}

	DBfree_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: update event names in events and problem tables                   *
 *                                                                            *
 * Return value: SUCCEED - the update was successful                          *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
static int	update_event_names(void)
{
	DB_RESULT		result;
	DB_ROW			row;
	ZBX_DB_TRIGGER		trigger;
	int			ret = SUCCEED, historical, triggers_num, processed_num = 0, completed,
				last_completed = 0;
	char			*sql;
	size_t			sql_alloc = 4096, sql_offset = 0;
	zbx_dc_um_handle_t	*um_handle;

	zabbix_log(LOG_LEVEL_WARNING, "starting event name update forced by database upgrade");

	if (0 == (triggers_num = get_trigger_count()))
		goto out;

	memset(&trigger, 0, sizeof(ZBX_DB_TRIGGER));

	sql = (char *)zbx_malloc(NULL, sql_alloc);
	zbx_DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	result = DBselect(
			"select triggerid,description,expression,priority,comments,url,recovery_expression,"
				"recovery_mode,value"
			" from triggers"
			" order by triggerid");

	um_handle = zbx_dc_open_user_macros();

	while (SUCCEED == ret && NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(trigger.triggerid, row[0]);
		trigger.description = zbx_strdup(NULL, row[1]);
		trigger.expression = zbx_strdup(NULL, row[2]);
		ZBX_STR2UCHAR(trigger.priority, row[3]);
		trigger.comments = zbx_strdup(NULL, row[4]);
		trigger.url = zbx_strdup(NULL, row[5]);
		trigger.recovery_expression = zbx_strdup(NULL, row[6]);
		ZBX_STR2UCHAR(trigger.recovery_mode, row[7]);
		ZBX_STR2UCHAR(trigger.value, row[8]);

		preprocess_trigger_name(&trigger, &historical);

		if (FAIL == historical)
			ret = process_event_bulk_update(&trigger, &sql, &sql_alloc, &sql_offset);
		else
			ret = process_event_update(&trigger, &sql, &sql_alloc, &sql_offset);

		zbx_db_trigger_clean(&trigger);

		processed_num++;

		if (last_completed != (completed = 100.0 * processed_num / triggers_num))
		{
			zabbix_log(LOG_LEVEL_WARNING, "completed %d%% of event name update", completed);
			last_completed = completed;
		}
	}

	zbx_dc_close_user_macros(um_handle);

	zbx_DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (SUCCEED == ret && 16 < sql_offset) /* in ORACLE always present begin..end; */
	{
		if (ZBX_DB_OK > DBexecute("%s", sql))
			ret = FAIL;
	}

	DBfree_result(result);

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
 * Purpose: process post initialization tasks                                 *
 *                                                                            *
 * Return value: SUCCEED - the update was successful                          *
 *               FAIL - otherwise                                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_check_postinit_tasks(char **error)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = SUCCEED;

	result = DBselect("select taskid from task where type=%d and status=%d", ZBX_TM_TASK_UPDATE_EVENTNAMES,
			ZBX_TM_STATUS_NEW);

	if (NULL != (row = DBfetch(result)))
	{
		DBbegin();

		if (SUCCEED == (ret = update_event_names()))
		{
			DBexecute("delete from task where taskid=%s", row[0]);
			DBcommit();
		}
		else
			DBrollback();
	}

	DBfree_result(result);

	if (SUCCEED != ret)
		*error = zbx_strdup(*error, "cannot update event names");

	return ret;
}
