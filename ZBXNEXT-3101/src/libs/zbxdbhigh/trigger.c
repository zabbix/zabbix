/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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

#include "common.h"

#include "db.h"
#include "log.h"
#include "dbcache.h"
#include "zbxserver.h"
#include "template.h"
#include "events.h"

#define ZBX_FLAGS_TRIGGER_CREATE_NOTHING		0x00
#define ZBX_FLAGS_TRIGGER_CREATE_TRIGGER_EVENT		0x01
#define ZBX_FLAGS_TRIGGER_CREATE_INTERNAL_EVENT		0x02
#define ZBX_FLAGS_TRIGGER_CREATE_EVENT										\
		(ZBX_FLAGS_TRIGGER_CREATE_TRIGGER_EVENT | ZBX_FLAGS_TRIGGER_CREATE_INTERNAL_EVENT)

/******************************************************************************
 *                                                                            *
 * Function: zbx_process_trigger                                              *
 *                                                                            *
 * Purpose: 1) calculate changeset of trigger fields to be updated            *
 *          2) generate events                                                *
 *                                                                            *
 * Parameters: trigger - [IN] the trigger to process                          *
 *             diffs   - [OUT] the vector with trigger changes                *
 *                                                                            *
 * Return value: SUCCEED - trigger processed successfully                     *
 *               FAIL    - no changes                                         *
 *                                                                            *
 * Comments: do not process if there are dependencies with value PROBLEM      *
 *                                                                            *
 * Event generation depending on trigger value/state changes:                 *
 *                                                                            *
 * From \ To  | OK         | OK(?)      | PROBLEM    | PROBLEM(?) | NONE      *
 *----------------------------------------------------------------------------*
 * OK         | .          | I          | E          | I          | .         *
 *            |            |            |            |            |           *
 * OK(?)      | I          | .          | E,I        | -          | I         *
 *            |            |            |            |            |           *
 * PROBLEM    | E          | I          | E(m)       | I          | .         *
 *            |            |            |            |            |           *
 * PROBLEM(?) | E,I        | -          | E(m),I     | .          | I         *
 *                                                                            *
 * Legend:                                                                    *
 *        'E' - trigger event                                                 *
 *        'I' - internal event                                                *
 *        '.' - nothing                                                       *
 *        '-' - should never happen                                           *
 *                                                                            *
 ******************************************************************************/
static int	zbx_process_trigger(struct _DC_TRIGGER *trigger, zbx_vector_ptr_t *diffs)
{
	const char	*__function_name = "zbx_process_trigger";

	const char	*new_error;
	int		new_state, new_value, ret = FAIL;
	zbx_uint64_t	flags = ZBX_FLAGS_TRIGGER_DIFF_UNSET, event_flags = ZBX_FLAGS_TRIGGER_CREATE_NOTHING;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() triggerid:" ZBX_FS_UI64 " value:%d(%d) new_value:%d",
			__function_name, trigger->triggerid, trigger->value, trigger->state, trigger->new_value);

	if (TRIGGER_VALUE_UNKNOWN == trigger->new_value)
	{
		new_state = TRIGGER_STATE_UNKNOWN;
		new_value = trigger->value;
	}
	else
	{
		new_state = TRIGGER_STATE_NORMAL;
		new_value = trigger->new_value;
	}
	new_error = (NULL == trigger->new_error ? "" : trigger->new_error);

	if (trigger->state != new_state)
	{
		flags |= ZBX_FLAGS_TRIGGER_DIFF_UPDATE_STATE;
		event_flags |= ZBX_FLAGS_TRIGGER_CREATE_INTERNAL_EVENT;
	}

	if (0 != strcmp(trigger->error, new_error))
		flags |= ZBX_FLAGS_TRIGGER_DIFF_UPDATE_ERROR;

	if (TRIGGER_STATE_NORMAL == new_state)
	{
		if (TRIGGER_VALUE_PROBLEM == new_value)
		{
			if (TRIGGER_VALUE_OK == trigger->value || TRIGGER_TYPE_MULTIPLE_TRUE == trigger->type)
				event_flags |= ZBX_FLAGS_TRIGGER_CREATE_TRIGGER_EVENT;
		}
		else if (TRIGGER_VALUE_OK == new_value)
		{
			if (TRIGGER_VALUE_PROBLEM == trigger->value || 0 == trigger->lastchange)
				event_flags |= ZBX_FLAGS_TRIGGER_CREATE_TRIGGER_EVENT;
		}
	}

	/* check if there is something to be updated */
	if (0 == (flags & ZBX_FLAGS_TRIGGER_DIFF_UPDATE) && 0 == (event_flags & ZBX_FLAGS_TRIGGER_CREATE_EVENT))
		goto out;

	if (SUCCEED != DCconfig_check_trigger_dependencies(trigger->triggerid))
		goto out;

	if (0 != (event_flags & ZBX_FLAGS_TRIGGER_CREATE_TRIGGER_EVENT))
	{
		flags |= ZBX_FLAGS_TRIGGER_DIFF_UPDATE_LASTCHANGE;

		add_event(EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER, trigger->triggerid,
				&trigger->timespec, new_value, trigger->description,
				trigger->expression_orig, trigger->recovery_expression_orig,
				trigger->priority, trigger->type, &trigger->tags,
				trigger->correlation_mode, trigger->correlation_tag);
	}

	if (0 != (event_flags & ZBX_FLAGS_TRIGGER_CREATE_INTERNAL_EVENT))
	{
		add_event(EVENT_SOURCE_INTERNAL, EVENT_OBJECT_TRIGGER, trigger->triggerid,
				&trigger->timespec, new_state, NULL, NULL, NULL, 0, 0, NULL, 0, NULL);
	}

	if (0 != (flags & ZBX_FLAGS_TRIGGER_DIFF_UPDATE))
	{
		zbx_append_trigger_diff(diffs, trigger->triggerid, trigger->priority, flags, trigger->value,
				new_state, trigger->timespec.sec, new_error);
	}

	ret = SUCCEED;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s flags:" ZBX_FS_UI64, __function_name, zbx_result_string(ret),
			flags);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_save_trigger_changes                                         *
 *                                                                            *
 * Purpose: save the trigger changes to database                              *
 *                                                                            *
 * Parameters:trigger_diff - [IN] the trigger changeset                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_save_trigger_changes(const zbx_vector_ptr_t *trigger_diff)
{
	const char			*__function_name = "zbx_save_trigger_changes";

	int				i;
	char				*sql = NULL;
	size_t				sql_alloc = 0, sql_offset = 0;
	const zbx_trigger_diff_t	*diff;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	for (i = 0; i < trigger_diff->values_num; i++)
	{
		char	delim = ' ';
		diff = (const zbx_trigger_diff_t *)trigger_diff->values[i];

		if (0 == (diff->flags & ZBX_FLAGS_TRIGGER_DIFF_UPDATE))
			continue;

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "update triggers set");

		if (0 != (diff->flags & ZBX_FLAGS_TRIGGER_DIFF_UPDATE_LASTCHANGE))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%clastchange=%d", delim, diff->lastchange);
			delim = ',';
		}

		if (0 != (diff->flags & ZBX_FLAGS_TRIGGER_DIFF_UPDATE_VALUE))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cvalue=%d", delim, diff->value);
			delim = ',';
		}

		if (0 != (diff->flags & ZBX_FLAGS_TRIGGER_DIFF_UPDATE_STATE))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cstate=%d", delim, diff->state);
			delim = ',';
		}

		if (0 != (diff->flags & ZBX_FLAGS_TRIGGER_DIFF_UPDATE_ERROR))
		{
			char	*error_esc;

			error_esc = DBdyn_escape_string_len(diff->error, TRIGGER_ERROR_LEN);
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%cerror='%s'", delim, error_esc);
			zbx_free(error_esc);
		}

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where triggerid=" ZBX_FS_UI64 ";\n",
				diff->triggerid);

		DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (sql_offset > 16)	/* in ORACLE always present begin..end; */
		DBexecute("%s", sql);

	zbx_free(sql);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_trigger_diff_free                                            *
 *                                                                            *
 * Purpose: frees trigger changeset                                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_trigger_diff_free(zbx_trigger_diff_t *diff)
{
	zbx_free(diff->error);
	zbx_free(diff);
}

/******************************************************************************
 *                                                                            *
 * Comments: helper function for process_triggers()                           *
 *                                                                            *
 ******************************************************************************/
static int	zbx_trigger_topoindex_compare(const void *d1, const void *d2)
{
	const zbx_ptr_pair_t	*p1 = (const zbx_ptr_pair_t *)d1;
	const zbx_ptr_pair_t	*p2 = (const zbx_ptr_pair_t *)d2;

	const DC_TRIGGER	*t1 = (const DC_TRIGGER *)p1->first;
	const DC_TRIGGER	*t2 = (const DC_TRIGGER *)p2->first;

	ZBX_RETURN_IF_NOT_EQUAL(t1->topoindex, t2->topoindex);

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_process_triggers                                             *
 *                                                                            *
 * Purpose: process triggers - calculates property changeset and generates    *
 *          events                                                            *
 *                                                                            *
 * Parameters: triggers     - [IN] the triggers to process                    *
 *             trigger_diff - [OUT] the trigger changeset                     *
 *                                                                            *
 * Comments: The trigger_diff changeset must be cleaned by the caller:        *
 *                zbx_vector_ptr_clear_ext(trigger_diff,                      *
 *                              (zbx_clean_func_t)zbx_trigger_diff_free);     *
 *                                                                            *
 ******************************************************************************/
void	zbx_process_triggers(zbx_vector_ptr_t *triggers, zbx_vector_ptr_t *trigger_diff)
{
	const char		*__function_name = "zbx_process_triggers";

	int			i;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() values_num:%d", __function_name, triggers->values_num);

	if (0 == triggers->values_num)
		goto out;

	zbx_vector_ptr_sort(triggers, zbx_trigger_topoindex_compare);

	for (i = 0; i < triggers->values_num; i++)
		zbx_process_trigger(triggers->values[i], trigger_diff);

	zbx_vector_ptr_sort(trigger_diff, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_append_trigger_diff                                          *
 *                                                                            *
 * Purpose: Adds a new trigger diff to trigger changeset vector               *
 *                                                                            *
 ******************************************************************************/
void	zbx_append_trigger_diff(zbx_vector_ptr_t *trigger_diff, zbx_uint64_t triggerid, unsigned char priority,
		zbx_uint64_t flags, unsigned char value, unsigned char state, int lastchange, const char *error)
{
	zbx_trigger_diff_t	*diff;

	diff = (zbx_trigger_diff_t *)zbx_malloc(NULL, sizeof(zbx_trigger_diff_t));
	diff->triggerid = triggerid;
	diff->priority = priority;
	diff->flags = flags;
	diff->value = value;
	diff->state = state;
	diff->lastchange = lastchange;
	diff->error = (NULL != error ? zbx_strdup(NULL, error) : NULL);

	diff->problem_count = 0;

	zbx_vector_ptr_append(trigger_diff, diff);
}
