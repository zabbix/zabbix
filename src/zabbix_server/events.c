/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

#include "actions.h"
#include "events.h"
#include "zbxserver.h"
#include "export.h"

/* event recovery data */
typedef struct
{
	zbx_uint64_t	eventid;
	zbx_uint64_t	objectid;
	int		r_event_index;
	zbx_uint64_t	correlationid;
	zbx_uint64_t	c_eventid;
	zbx_uint64_t	userid;
	zbx_timespec_t	ts;
}
zbx_event_recovery_t;

/* problem event, used to cache open problems for recovery attempts */
typedef struct
{
	zbx_uint64_t		eventid;
	zbx_uint64_t		triggerid;

	zbx_vector_ptr_t	tags;
}
zbx_event_problem_t;

static DB_EVENT			*events = NULL;
static size_t			events_alloc = 0, events_num = 0;
static zbx_hashset_t		event_recovery;
static zbx_hashset_t		correlation_cache;
static zbx_correlation_rules_t	correlation_rules;

/******************************************************************************
 *                                                                            *
 * Function: validate_event_tag                                               *
 *                                                                            *
 ******************************************************************************/
static int	validate_event_tag(const DB_EVENT* event, const zbx_tag_t *tag)
{
	int	i;

	if ('\0' == *tag->tag)
		return FAIL;

	/* check for duplicated tags */
	for (i = 0; i < event->tags.values_num; i++)
	{
		zbx_tag_t	*event_tag = (zbx_tag_t *)event->tags.values[i];

		if (0 == strcmp(event_tag->tag, tag->tag) && 0 == strcmp(event_tag->value, tag->value))
			return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_add_event                                                    *
 *                                                                            *
 * Purpose: add event to an array                                             *
 *                                                                            *
 * Parameters: source   - [IN] event source (EVENT_SOURCE_*)                  *
 *             object   - [IN] event object (EVENT_OBJECT_*)                  *
 *             objectid - [IN] trigger, item ... identificator from database, *
 *                             depends on source and object                   *
 *             timespec - [IN] event time                                     *
 *             value    - [IN] event value (TRIGGER_VALUE_*,                  *
 *                             TRIGGER_STATE_*, ITEM_STATE_* ... depends on   *
 *                             source and object)                             *
 *             trigger_description         - [IN] trigger description         *
 *             trigger_expression          - [IN] trigger short expression    *
 *             trigger_recovery_expression - [IN] trigger recovery expression *
 *             trigger_priority            - [IN] trigger priority            *
 *             trigger_type                - [IN] TRIGGER_TYPE_* defines      *
 *             trigger_tags                - [IN] trigger tags                *
 *             trigger_correlation_mode    - [IN] trigger correlation mode    *
 *             trigger_correlation_tag     - [IN] trigger correlation tag     *
 *             trigger_value               - [IN] trigger value               *
 *             error                       - [IN] error for internal events   *
 *                                                                            *
 ******************************************************************************/
int	zbx_add_event(unsigned char source, unsigned char object, zbx_uint64_t objectid,
		const zbx_timespec_t *timespec, int value, const char *trigger_description,
		const char *trigger_expression, const char *trigger_recovery_expression, unsigned char trigger_priority,
		unsigned char trigger_type, const zbx_vector_ptr_t *trigger_tags,
		unsigned char trigger_correlation_mode, const char *trigger_correlation_tag,
		unsigned char trigger_value, const char *error)
{
	int	i;

	if (events_num == events_alloc)
	{
		events_alloc += 64;
		events = (DB_EVENT *)zbx_realloc(events, sizeof(DB_EVENT) * events_alloc);
	}

	events[events_num].eventid = 0;
	events[events_num].source = source;
	events[events_num].object = object;
	events[events_num].objectid = objectid;
	events[events_num].name = NULL;
	events[events_num].clock = timespec->sec;
	events[events_num].ns = timespec->ns;
	events[events_num].value = value;
	events[events_num].acknowledged = EVENT_NOT_ACKNOWLEDGED;
	events[events_num].flags = ZBX_FLAGS_DB_EVENT_CREATE;

	if (EVENT_SOURCE_TRIGGERS == source)
	{
		events[events_num].trigger.triggerid = objectid;
		events[events_num].trigger.description = zbx_strdup(NULL, trigger_description);
		events[events_num].trigger.expression = zbx_strdup(NULL, trigger_expression);
		events[events_num].trigger.recovery_expression = zbx_strdup(NULL, trigger_recovery_expression);
		events[events_num].trigger.priority = trigger_priority;
		events[events_num].trigger.type = trigger_type;
		events[events_num].trigger.correlation_mode = trigger_correlation_mode;
		events[events_num].trigger.correlation_tag = zbx_strdup(NULL, trigger_correlation_tag);
		events[events_num].trigger.value = trigger_value;
		events[events_num].name = zbx_strdup(NULL, trigger_description);

		substitute_simple_macros(NULL, &events[events_num], NULL, NULL, NULL, NULL, NULL, NULL, NULL,
				&events[events_num].trigger.correlation_tag, MACRO_TYPE_TRIGGER_TAG, NULL, 0);

		substitute_simple_macros(NULL, &events[events_num], NULL, NULL, NULL, NULL, NULL, NULL, NULL,
				&events[events_num].name, MACRO_TYPE_TRIGGER_DESCRIPTION, NULL, 0);

		zbx_vector_ptr_create(&events[events_num].tags);

		if (NULL != trigger_tags)
		{
			for (i = 0; i < trigger_tags->values_num; i++)
			{
				const zbx_tag_t	*trigger_tag = (const zbx_tag_t *)trigger_tags->values[i];
				zbx_tag_t	*tag;

				tag = (zbx_tag_t *)zbx_malloc(NULL, sizeof(zbx_tag_t));
				tag->tag = zbx_strdup(NULL, trigger_tag->tag);
				tag->value = zbx_strdup(NULL, trigger_tag->value);

				substitute_simple_macros(NULL, &events[events_num], NULL, NULL, NULL, NULL, NULL, NULL,
						NULL, &tag->tag, MACRO_TYPE_TRIGGER_TAG, NULL, 0);

				substitute_simple_macros(NULL, &events[events_num], NULL, NULL, NULL, NULL, NULL, NULL,
						NULL, &tag->value, MACRO_TYPE_TRIGGER_TAG, NULL, 0);

				if (TAG_NAME_LEN < zbx_strlen_utf8(tag->tag))
					tag->tag[zbx_strlen_utf8_nchars(tag->tag, TAG_NAME_LEN)] = '\0';
				if (TAG_VALUE_LEN < zbx_strlen_utf8(tag->value))
					tag->value[zbx_strlen_utf8_nchars(tag->value, TAG_VALUE_LEN)] = '\0';

				zbx_lrtrim(tag->tag, ZBX_WHITESPACE);
				zbx_lrtrim(tag->value, ZBX_WHITESPACE);

				if (SUCCEED == validate_event_tag(&events[events_num], tag))
					zbx_vector_ptr_append(&events[events_num].tags, tag);
				else
					zbx_free_tag(tag);
			}
		}
	}
	else if (EVENT_SOURCE_INTERNAL == source && NULL != error)
		events[events_num].name = zbx_strdup(NULL, error);

	return events_num++;
}

/******************************************************************************
 *                                                                            *
 * Function: close_trigger_event                                              *
 *                                                                            *
 * Purpose: add closing OK event for the specified problem event to an array  *
 *                                                                            *
 * Parameters: eventid  - [IN] the problem eventid                            *
 *             objectid - [IN] trigger, item ... identificator from database, *
 *                             depends on source and object                   *
 *             ts       - [IN] event time                                     *
 *             userid   - [IN] the user closing the problem                   *
 *             correlationid - [IN] the correlation rule                      *
 *             c_eventid - [IN] the correlation event                         *
 *             trigger_description         - [IN] trigger description         *
 *             trigger_expression          - [IN] trigger short expression    *
 *             trigger_recovery_expression - [IN] trigger recovery expression *
 *             trigger_priority            - [IN] trigger priority            *
 *             trigger_type                - [IN] TRIGGER_TYPE_* defines      *
 *                                                                            *
 ******************************************************************************/
static int	close_trigger_event(zbx_uint64_t eventid, zbx_uint64_t objectid, const zbx_timespec_t *ts,
		zbx_uint64_t userid, zbx_uint64_t correlationid, zbx_uint64_t c_eventid,
		const char *trigger_description, const char *trigger_expression,
		const char *trigger_recovery_expression, unsigned char trigger_priority, unsigned char trigger_type)
{
	int			index;
	zbx_event_recovery_t	recovery_local;

	index = zbx_add_event(EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER, objectid, ts, TRIGGER_VALUE_OK,
			trigger_description, trigger_expression, trigger_recovery_expression, trigger_priority,
			trigger_type, NULL, ZBX_TRIGGER_CORRELATION_NONE, "", TRIGGER_VALUE_PROBLEM,
			NULL);

	recovery_local.eventid = eventid;
	recovery_local.objectid = objectid;
	recovery_local.correlationid = correlationid;
	recovery_local.c_eventid = c_eventid;
	recovery_local.r_event_index = index;
	recovery_local.userid = userid;

	zbx_hashset_insert(&event_recovery, &recovery_local, sizeof(recovery_local));

	return index;
}

/******************************************************************************
 *                                                                            *
 * Function: save_events                                                      *
 *                                                                            *
 * Purpose: flushes the events into a database                                *
 *                                                                            *
 ******************************************************************************/
static int	save_events(void)
{
	size_t			i;
	zbx_db_insert_t		db_insert, db_insert_tags;
	int			j, num = 0, insert_tags = 0;
	zbx_uint64_t		eventid;

	for (i = 0; i < events_num; i++)
	{
		if (0 != (events[i].flags & ZBX_FLAGS_DB_EVENT_CREATE) && 0 == events[i].eventid)
			num++;
	}

	zbx_db_insert_prepare(&db_insert, "events", "eventid", "source", "object", "objectid", "clock", "ns", "value",
			"name", NULL);

	eventid = DBget_maxid_num("events", num);

	num = 0;

	for (i = 0; i < events_num; i++)
	{
		if (0 == (events[i].flags & ZBX_FLAGS_DB_EVENT_CREATE))
			continue;

		if (0 == events[i].eventid)
			events[i].eventid = eventid++;

		zbx_db_insert_add_values(&db_insert, events[i].eventid, events[i].source, events[i].object,
				events[i].objectid, events[i].clock, events[i].ns, events[i].value,
				ZBX_NULL2EMPTY_STR(events[i].name));

		num++;

		if (EVENT_SOURCE_TRIGGERS != events[i].source)
			continue;

		if (0 == events[i].tags.values_num)
			continue;

		if (0 == insert_tags)
		{
			zbx_db_insert_prepare(&db_insert_tags, "event_tag", "eventtagid", "eventid", "tag", "value",
					NULL);
			insert_tags = 1;
		}

		for (j = 0; j < events[i].tags.values_num; j++)
		{
			zbx_tag_t	*tag = (zbx_tag_t *)events[i].tags.values[j];

			zbx_db_insert_add_values(&db_insert_tags, __UINT64_C(0), events[i].eventid, tag->tag,
					tag->value);
		}
	}

	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	if (0 != insert_tags)
	{
		zbx_db_insert_autoincrement(&db_insert_tags, "eventtagid");
		zbx_db_insert_execute(&db_insert_tags);
		zbx_db_insert_clean(&db_insert_tags);
	}

	return num;
}

/******************************************************************************
 *                                                                            *
 * Function: save_problems                                                    *
 *                                                                            *
 * Purpose: generates problems from problem events (trigger and internal      *
 *          event sources)                                                    *
 *                                                                            *
 ******************************************************************************/
static void	save_problems(void)
{
	size_t			i;
	zbx_vector_ptr_t	problems;
	int			j, tags_num = 0;

	zbx_vector_ptr_create(&problems);

	for (i = 0; i < events_num; i++)
	{
		DB_EVENT	*event = &events[i];

		if (0 == (event->flags & ZBX_FLAGS_DB_EVENT_CREATE))
			continue;

		if (EVENT_SOURCE_TRIGGERS == event->source)
		{
			if (EVENT_OBJECT_TRIGGER != event->object || TRIGGER_VALUE_PROBLEM != event->value)
				continue;

			tags_num += event->tags.values_num;
		}
		else if (EVENT_SOURCE_INTERNAL == event->source)
		{
			switch (event->object)
			{
				case EVENT_OBJECT_TRIGGER:
					if (TRIGGER_STATE_UNKNOWN != event->value)
						continue;
					break;
				case EVENT_OBJECT_ITEM:
					if (ITEM_STATE_NOTSUPPORTED != event->value)
						continue;
					break;
				case EVENT_OBJECT_LLDRULE:
					if (ITEM_STATE_NOTSUPPORTED != event->value)
						continue;
					break;
				default:
					continue;
			}
		}
		else
			continue;

		zbx_vector_ptr_append(&problems, event);
	}

	if (0 != problems.values_num)
	{
		zbx_db_insert_t	db_insert;

		zbx_db_insert_prepare(&db_insert, "problem", "eventid", "source", "object", "objectid", "clock", "ns",
				"name", NULL);

		for (j = 0; j < problems.values_num; j++)
		{
			const DB_EVENT	*event = (const DB_EVENT *)problems.values[j];

			zbx_db_insert_add_values(&db_insert, event->eventid, event->source, event->object,
					event->objectid, event->clock, event->ns, ZBX_NULL2EMPTY_STR(event->name));
		}

		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);

		if (0 != tags_num)
		{
			int	k;

			zbx_db_insert_prepare(&db_insert, "problem_tag", "problemtagid", "eventid", "tag", "value",
					NULL);

			for (j = 0; j < problems.values_num; j++)
			{
				const DB_EVENT	*event = (const DB_EVENT *)problems.values[j];

				if (EVENT_SOURCE_TRIGGERS != event->source)
					continue;

				for (k = 0; k < event->tags.values_num; k++)
				{
					zbx_tag_t	*tag = (zbx_tag_t *)event->tags.values[k];

					zbx_db_insert_add_values(&db_insert, __UINT64_C(0), event->eventid, tag->tag,
							tag->value);
				}
			}

			zbx_db_insert_autoincrement(&db_insert, "problemtagid");
			zbx_db_insert_execute(&db_insert);
			zbx_db_insert_clean(&db_insert);
		}
	}

	zbx_vector_ptr_destroy(&problems);
}

/******************************************************************************
 *                                                                            *
 * Function: save_event_recovery                                              *
 *                                                                            *
 * Purpose: saves event recovery data and removes recovered events from       *
 *          problem table                                                     *
 *                                                                            *
 ******************************************************************************/
static void	save_event_recovery(void)
{
	zbx_db_insert_t		db_insert;
	zbx_event_recovery_t	*recovery;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_hashset_iter_t	iter;
	DB_EVENT		*r_event;

	if (0 == event_recovery.num_data)
		return;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	zbx_db_insert_prepare(&db_insert, "event_recovery", "eventid", "r_eventid", "correlationid", "c_eventid",
			"userid", NULL);

	zbx_hashset_iter_reset(&event_recovery, &iter);
	while (NULL != (recovery = (zbx_event_recovery_t *)zbx_hashset_iter_next(&iter)))
	{
		r_event = &events[recovery->r_event_index];

		zbx_db_insert_add_values(&db_insert, recovery->eventid, r_event->eventid,
				recovery->correlationid, recovery->c_eventid, recovery->userid);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"update problem set"
			" r_eventid=" ZBX_FS_UI64
			",r_clock=%d"
			",r_ns=%d"
			",userid=" ZBX_FS_UI64,
			r_event->eventid,
			r_event->clock,
			r_event->ns,
			recovery->userid);

		if (0 != recovery->correlationid)
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, ",correlationid=" ZBX_FS_UI64,
					recovery->correlationid);
		}

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, " where eventid=" ZBX_FS_UI64 ";\n",
				recovery->eventid);

		DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset)	/* in ORACLE always present begin..end; */
		DBexecute("%s", sql);

	zbx_free(sql);
}

/******************************************************************************
 *                                                                            *
 * Function: get_event_index_by_source_object_id                              *
 *                                                                            *
 * Purpose: find event index by its source object                             *
 *                                                                            *
 * Parameters: source   - [IN] the event source                               *
 *             object   - [IN] the object type                                *
 *             objectid - [IN] the object id                                  *
 *                                                                            *
 * Return value: the event index or FAIL                                      *
 *                                                                            *
 ******************************************************************************/
static int	get_event_index_by_source_object_id(int source, int object, zbx_uint64_t objectid)
{
	size_t		i;
	DB_EVENT	*event;

	for (i = 0; i < events_num; i++)
	{
		event = &events[i];

		if (event->source == source && event->object == object && event->objectid == objectid)
			return i;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: correlation_match_event_hostgroup                                *
 *                                                                            *
 * Purpose: checks if the event matches the specified host group              *
 *          (including nested groups)                                         *
 *                                                                            *
 * Parameters: event   - [IN] the new event to check                          *
 *             groupid - [IN] the group id to match                           *
 *                                                                            *
 * Return value: SUCCEED - the group matches                                  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	correlation_match_event_hostgroup(const DB_EVENT *event, zbx_uint64_t groupid)
{
	DB_RESULT		result;
	int			ret = FAIL;
	zbx_vector_uint64_t	groupids;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;

	zbx_vector_uint64_create(&groupids);
	zbx_dc_get_nested_hostgroupids(&groupid, 1, &groupids);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select hg.groupid"
				" from groups g,hosts_groups hg,items i,functions f"
				" where f.triggerid=" ZBX_FS_UI64
				" and i.itemid=f.itemid"
				" and hg.hostid=i.hostid"
				" and",
				event->objectid);

	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hg.groupid", groupids.values,
			groupids.values_num);

	result = DBselect("%s", sql);

	if (NULL != DBfetch(result))
		ret = SUCCEED;

	DBfree_result(result);
	zbx_free(sql);
	zbx_vector_uint64_destroy(&groupids);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: correlation_condition_match_new_event                            *
 *                                                                            *
 * Purpose: checks if the correlation condition matches the new event         *
 *                                                                            *
 * Parameters: condition - [IN] the correlation condition to check            *
 *             event     - [IN] the new event to match                        *
 *             old_value - [IN] SUCCEED - the old event conditions always     *
 *                                        match event                         *
 *                              FAIL    - the old event conditions never      *
 *                                        match event                         *
 *                                                                            *
 * Return value: SUCCEED - the correlation condition matches                  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	correlation_condition_match_new_event(zbx_corr_condition_t *condition, const DB_EVENT *event,
		int old_value)
{
	int		i, ret;
	zbx_tag_t	*tag;

	/* return SUCCEED for conditions using old events */
	switch (condition->type)
	{
		case ZBX_CORR_CONDITION_EVENT_TAG_PAIR:
			/* If old event condition never matches event we can return FAIL.  */
			/* Otherwise we must check if the new event has the requested tag. */
			if (SUCCEED != old_value)
				return FAIL;
			break;
		case ZBX_CORR_CONDITION_OLD_EVENT_TAG:
		case ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE:
			return old_value;
	}

	switch (condition->type)
	{
		case ZBX_CORR_CONDITION_NEW_EVENT_TAG:
			for (i = 0; i < event->tags.values_num; i++)
			{
				tag = (zbx_tag_t *)event->tags.values[i];
				if (0 == strcmp(tag->tag, condition->data.tag.tag))
					return SUCCEED;
			}
			return FAIL;

		case ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE:
			for (i = 0; i < event->tags.values_num; i++)
			{
				zbx_corr_condition_tag_value_t	*cond = &condition->data.tag_value;

				tag = (zbx_tag_t *)event->tags.values[i];
				if (0 == strcmp(tag->tag, cond->tag) &&
					SUCCEED == zbx_strmatch_condition(tag->value, cond->value, cond->op))
				{
					return SUCCEED;
				}
			}
			return FAIL;

		case ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP:
			ret =  correlation_match_event_hostgroup(event, condition->data.group.groupid);
			if (CONDITION_OPERATOR_NOT_EQUAL == condition->data.group.op)
				ret = (SUCCEED == ret ? FAIL : SUCCEED);

			return ret;

		case ZBX_CORR_CONDITION_EVENT_TAG_PAIR:
			for (i = 0; i < event->tags.values_num; i++)
			{
				tag = (zbx_tag_t *)event->tags.values[i];
				if (0 == strcmp(tag->tag, condition->data.tag_pair.newtag))
					return SUCCEED;
			}
			return FAIL;
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: correlation_match_new_event                                      *
 *                                                                            *
 * Purpose: checks if the correlation rule might match the new event          *
 *                                                                            *
 * Parameters: correlation - [IN] the correlation rule to check               *
 *             event       - [IN] the new event to match                      *
 *             old_value   - [IN] SUCCEED - the old event conditions always   *
 *                                        match event                         *
 *                              FAIL    - the old event conditions never      *
 *                                        match event                         *
 *                                                                            *
 *                                                                            *
 * Return value: SUCCEED - the correlation rule might match depending on old  *
 *                         events                                             *
 *               FAIL    - the correlation rule doesn't match the new event   *
 *                         (no matter what the old events are)                *
 *                                                                            *
 ******************************************************************************/
static int	correlation_match_new_event(zbx_correlation_t *correlation, const DB_EVENT *event, int old_value)
{
	char			*expression, error[256];
	const char		*value;
	zbx_token_t		token;
	int			pos = 0, ret = FAIL;
	zbx_uint64_t		conditionid;
	zbx_strloc_t		*loc;
	zbx_corr_condition_t	*condition;
	double			result;

	if ('\0' == *correlation->formula)
		return SUCCEED;

	expression = zbx_strdup(NULL, correlation->formula);

	for (; SUCCEED == zbx_token_find(expression, pos, &token, ZBX_TOKEN_SEARCH_BASIC); pos++)
	{
		if (ZBX_TOKEN_OBJECTID != token.type)
			continue;

		loc = &token.data.objectid.name;

		if (SUCCEED != is_uint64_n(expression + loc->l, loc->r - loc->l + 1, &conditionid))
			continue;

		if (NULL == (condition = (zbx_corr_condition_t *)zbx_hashset_search(&correlation_rules.conditions, &conditionid)))
			goto out;

		if (SUCCEED == correlation_condition_match_new_event(condition, event, old_value))
			value = "1";
		else
			value = "0";

		zbx_replace_string(&expression, token.token.l, &token.token.r, value);
		pos = token.token.r;
	}

	if (SUCCEED == evaluate(&result, expression, error, sizeof(error), NULL))
		ret = zbx_double_compare(result, 1);
out:
	zbx_free(expression);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: correlation_has_old_event_filter                                 *
 *                                                                            *
 * Purpose: checks if correlation has conditions to match old events          *
 *                                                                            *
 * Parameters: correlation - [IN] the correlation to check                    *
 *                                                                            *
 * Return value: SUCCEED - correlation has conditions to match old events     *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	correlation_has_old_event_filter(const zbx_correlation_t *correlation)
{
	int				i;
	const zbx_corr_condition_t	*condition;

	for (i = 0; i < correlation->conditions.values_num; i++)
	{
		condition = (zbx_corr_condition_t *)correlation->conditions.values[i];

		switch (condition->type)
		{
			case ZBX_CORR_CONDITION_OLD_EVENT_TAG:
			case ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE:
			case ZBX_CORR_CONDITION_EVENT_TAG_PAIR:
				return SUCCEED;
		}
	}
	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: correlation_has_old_event_operation                              *
 *                                                                            *
 * Purpose: checks if correlation has operations to change old events         *
 *                                                                            *
 * Parameters: correlation - [IN] the correlation to check                    *
 *                                                                            *
 * Return value: SUCCEED - correlation has operations to change old events    *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	correlation_has_old_event_operation(const zbx_correlation_t *correlation)
{
	int				i;
	const zbx_corr_operation_t	*operation;

	for (i = 0; i < correlation->operations.values_num; i++)
	{
		operation = (zbx_corr_operation_t *)correlation->operations.values[i];

		switch (operation->type)
		{
			case ZBX_CORR_OPERATION_CLOSE_OLD:
				return SUCCEED;
		}
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: correlation_condition_add_tag_match                              *
 *                                                                            *
 * Purpose: adds sql statement to match tag according to the defined          *
 *          matching operation                                                *
 *                                                                            *
 * Parameters: sql         - [IN/OUT]                                         *
 *             sql_alloc   - [IN/OUT]                                         *
 *             sql_offset  - [IN/OUT]                                         *
 *             tag         - [IN] the tag to match                            *
 *             value       - [IN] the tag value to match                      *
 *             op          - [IN] the matching operation (CONDITION_OPERATOR_)*
 *                                                                            *
 ******************************************************************************/
static void	correlation_condition_add_tag_match(char **sql, size_t *sql_alloc, size_t *sql_offset, const char *tag,
		const char *value, unsigned char op)
{
	char	*tag_esc, *value_esc;

	tag_esc = DBdyn_escape_string(tag);
	value_esc = DBdyn_escape_string(value);

	switch (op)
	{
		case CONDITION_OPERATOR_NOT_EQUAL:
		case CONDITION_OPERATOR_NOT_LIKE:
			zbx_strcpy_alloc(sql, sql_alloc, sql_offset, "not ");
			break;
	}

	zbx_strcpy_alloc(sql, sql_alloc, sql_offset,
			"exists (select null from problem_tag pt where p.eventid=pt.eventid and ");

	switch (op)
	{
		case CONDITION_OPERATOR_EQUAL:
		case CONDITION_OPERATOR_NOT_EQUAL:
			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "pt.tag='%s' and pt.value" ZBX_SQL_STRCMP,
					tag_esc, ZBX_SQL_STRVAL_EQ(value_esc));
			break;
		case CONDITION_OPERATOR_LIKE:
		case CONDITION_OPERATOR_NOT_LIKE:
			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "pt.tag='%s' and pt.value like '%%%s%%'",
					tag_esc, value_esc);
			break;
	}

	zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, ')');

	zbx_free(value_esc);
	zbx_free(tag_esc);
}


/******************************************************************************
 *                                                                            *
 * Function: correlation_condition_get_event_filter                           *
 *                                                                            *
 * Purpose: creates sql filter to find events matching a correlation          *
 *          condition                                                         *
 *                                                                            *
 * Parameters: condition - [IN] the correlation condition to match            *
 *             event     - [IN] the new event to match                        *
 *                                                                            *
 * Return value: the created filter or NULL                                   *
 *                                                                            *
 ******************************************************************************/
static char	*correlation_condition_get_event_filter(zbx_corr_condition_t *condition, const DB_EVENT *event)
{
	int			i;
	zbx_tag_t		*tag;
	char			*tag_esc, *filter = NULL;
	size_t			filter_alloc = 0, filter_offset = 0;
	zbx_vector_str_t	values;

	/* replace new event dependent condition with precalculated value */
	switch (condition->type)
	{
		case ZBX_CORR_CONDITION_NEW_EVENT_TAG:
		case ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE:
		case ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP:
			if (SUCCEED == correlation_condition_match_new_event(condition, event, SUCCEED))
				filter = (char *)"1=1";
			else
				filter = (char *)"0=1";

			return zbx_strdup(NULL, filter);
	}

	/* replace old event dependent condition with sql filter on problem_tag pt table */
	switch (condition->type)
	{
		case ZBX_CORR_CONDITION_OLD_EVENT_TAG:
			tag_esc = DBdyn_escape_string(condition->data.tag.tag);
			zbx_snprintf_alloc(&filter, &filter_alloc, &filter_offset,
					"exists (select null from problem_tag pt"
						" where p.eventid=pt.eventid"
							" and pt.tag='%s')",
					tag_esc);
			zbx_free(tag_esc);
			return filter;

		case ZBX_CORR_CONDITION_EVENT_TAG_PAIR:
			zbx_vector_str_create(&values);

			for (i = 0; i < event->tags.values_num; i++)
			{
				tag = (zbx_tag_t *)event->tags.values[i];
				if (0 == strcmp(tag->tag, condition->data.tag_pair.newtag))
					zbx_vector_str_append(&values, DBdyn_escape_string(tag->value));
			}

			if (0 == values.values_num)
			{
				/* no new tag found, substitute condition with failure expression */
				filter = zbx_strdup(NULL, "0");
			}
			else
			{
				tag_esc = DBdyn_escape_string(condition->data.tag_pair.oldtag);

				zbx_snprintf_alloc(&filter, &filter_alloc, &filter_offset,
						"exists (select null from problem_tag pt"
							" where p.eventid=pt.eventid"
								" and pt.tag='%s'"
								" and",
						tag_esc);

				DBadd_str_condition_alloc(&filter, &filter_alloc, &filter_offset, "pt.value",
						(const char **)values.values, values.values_num);

				zbx_chrcpy_alloc(&filter, &filter_alloc, &filter_offset, ')');

				zbx_free(tag_esc);
				zbx_vector_str_clear_ext(&values, zbx_ptr_free);
			}

			zbx_vector_str_destroy(&values);
			return filter;

		case ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE:
			correlation_condition_add_tag_match(&filter, &filter_alloc, &filter_offset,
					condition->data.tag_value.tag, condition->data.tag_value.value,
					condition->data.tag_value.op);
			return filter;
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Function: correlation_add_event_filter                                     *
 *                                                                            *
 * Purpose: add sql statement to filter out correlation conditions and        *
 *          matching events                                                   *
 *                                                                            *
 * Parameters: sql         - [IN/OUT]                                         *
 *             sql_alloc   - [IN/OUT]                                         *
 *             sql_offset  - [IN/OUT]                                         *
 *             correlation - [IN] the correlation rule to match               *
 *             event       - [IN] the new event to match                      *
 *                                                                            *
 * Return value: SUCCEED - the filter was added successfully                  *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
static int	correlation_add_event_filter(char **sql, size_t *sql_alloc, size_t *sql_offset,
		zbx_correlation_t *correlation, const DB_EVENT *event)
{
	char			*expression, *filter;
	zbx_token_t		token;
	int			pos = 0, ret = FAIL;
	zbx_uint64_t		conditionid;
	zbx_strloc_t		*loc;
	zbx_corr_condition_t	*condition;

	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "c.correlationid=" ZBX_FS_UI64, correlation->correlationid);

	expression = zbx_strdup(NULL, correlation->formula);

	for (; SUCCEED == zbx_token_find(expression, pos, &token, ZBX_TOKEN_SEARCH_BASIC); pos++)
	{
		if (ZBX_TOKEN_OBJECTID != token.type)
			continue;

		loc = &token.data.objectid.name;

		if (SUCCEED != is_uint64_n(expression + loc->l, loc->r - loc->l + 1, &conditionid))
			continue;

		if (NULL == (condition = (zbx_corr_condition_t *)zbx_hashset_search(&correlation_rules.conditions, &conditionid)))
			goto out;

		if (NULL == (filter = correlation_condition_get_event_filter(condition, event)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			goto out;
		}

		zbx_replace_string(&expression, token.token.l, &token.token.r, filter);
		pos = token.token.r;
		zbx_free(filter);
	}

	if ('\0' != *expression)
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, " and (%s)", expression);

	ret = SUCCEED;
out:
	zbx_free(expression);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: correlation_execute_operations                                   *
 *                                                                            *
 * Purpose: execute correlation operations for the new event and matched      *
 *          old eventid                                                       *
 *                                                                            *
 * Parameters: correlation  - [IN] the correlation to execute                 *
 *             event        - [IN] the new event                              *
 *             old_eventid  - [IN] the old eventid                            *
 *             old_objectid - [IN] the old event source objectid (triggerid)  *
 *                                                                            *
 ******************************************************************************/
static void	correlation_execute_operations(zbx_correlation_t *correlation, DB_EVENT *event,
		zbx_uint64_t old_eventid, zbx_uint64_t old_objectid)
{
	int			i, index;
	zbx_corr_operation_t	*operation;
	zbx_event_recovery_t	recovery_local;
	zbx_timespec_t		ts;

	for (i = 0; i < correlation->operations.values_num; i++)
	{
		operation = (zbx_corr_operation_t *)correlation->operations.values[i];

		switch (operation->type)
		{
			case ZBX_CORR_OPERATION_CLOSE_NEW:
				/* generate OK event to close the new event */

				/* check if this event was not been closed by another correlation rule */
				if (NULL != zbx_hashset_search(&event_recovery, &event->eventid))
					break;

				ts.sec = event->clock;
				ts.ns = event->ns;


				index = close_trigger_event(event->eventid, event->objectid, &ts, 0,
						correlation->correlationid, event->eventid, event->trigger.description,
						event->trigger.expression, event->trigger.recovery_expression,
						event->trigger.priority, event->trigger.type);

				event->flags |= ZBX_FLAGS_DB_EVENT_NO_ACTION;
				events[index].flags |= ZBX_FLAGS_DB_EVENT_NO_ACTION;

				break;
			case ZBX_CORR_OPERATION_CLOSE_OLD:
				/* queue closing of old events to lock them by triggerids */
				if (0 != old_eventid)
				{
					recovery_local.eventid = old_eventid;
					recovery_local.c_eventid = event->eventid;
					recovery_local.correlationid = correlation->correlationid;
					recovery_local.objectid = old_objectid;
					recovery_local.ts.sec = event->clock;
					recovery_local.ts.ns = event->ns;

					zbx_hashset_insert(&correlation_cache, &recovery_local, sizeof(recovery_local));
				}
				break;
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: correlate_event_by_global_rules                                  *
 *                                                                            *
 * Purpose: find problem events that must be recovered by global correlation  *
 *          rules and check if the new event must be closed                   *
 *                                                                            *
 * Comments: The correlation data (zbx_event_recovery_t) of events that       *
 *           must be closed are added to event_correlation hashset            *
 *                                                                            *
 *           The global event correlation matching is done in two parts:      *
 *             1) exclude correlations that can't possibly match the event    *
 *                based on new event tag/value/group conditions               *
 *             2) assemble sql statement to select problems/correlations      *
 *                based on the rest correlation conditions                    *
 *                                                                            *
 ******************************************************************************/
static void	correlate_event_by_global_rules(DB_EVENT *event)
{
	int			i;
	zbx_correlation_t	*correlation;
	zbx_vector_ptr_t	corr_old, corr_new;
	char			*sql = NULL;
	const char		*delim = "";
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t		eventid, correlationid, objectid;
	DB_RESULT		result;
	DB_ROW			row;

	zbx_vector_ptr_create(&corr_old);
	zbx_vector_ptr_create(&corr_new);

	for (i = 0; i < correlation_rules.correlations.values_num; i++)
	{
		correlation = (zbx_correlation_t *)correlation_rules.correlations.values[i];

		if (SUCCEED == correlation_match_new_event(correlation, event, SUCCEED))
		{
			if (SUCCEED == correlation_has_old_event_filter(correlation) ||
					SUCCEED == correlation_has_old_event_operation(correlation))
			{
				zbx_vector_ptr_append(&corr_old, correlation);
			}
			else
			{
				if (SUCCEED == correlation_match_new_event(correlation, event, FAIL))
					zbx_vector_ptr_append(&corr_new, correlation);
			}
		}
	}

	if (0 != corr_new.values_num)
	{
		/* Process correlations that matches new event and does not use or affect old events. */
		/* Those correlations can be executed directly, without checking database.            */
		for (i = 0; i < corr_new.values_num; i++)
			correlation_execute_operations((zbx_correlation_t *)corr_new.values[i], event, 0, 0);
	}

	if (0 != corr_old.values_num)
	{
		/* Process correlations that matches new event and either uses old events in conditions */
		/* or has operations involving old events.                                              */

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select p.eventid,p.objectid,c.correlationid"
								" from correlation c,problem p"
								" where p.r_eventid is null"
								" and (");

		for (i = 0; i < corr_old.values_num; i++)
		{
			correlation = (zbx_correlation_t *)corr_old.values[i];

			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, delim);
			correlation_add_event_filter(&sql, &sql_alloc, &sql_offset, correlation, event);
			delim = " or ";
		}

		zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, ')');
		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(eventid, row[0]);

			/* check if this event is not already recovered by another correlation rule */
			if (NULL != zbx_hashset_search(&correlation_cache, &eventid))
				continue;

			ZBX_STR2UINT64(correlationid, row[2]);

			if (FAIL == (i = zbx_vector_ptr_bsearch(&corr_old, &correlationid,
					ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			ZBX_STR2UINT64(objectid, row[1]);
			correlation_execute_operations((zbx_correlation_t *)corr_old.values[i], event, eventid, objectid);
		}

		DBfree_result(result);
		zbx_free(sql);
	}

	zbx_vector_ptr_destroy(&corr_new);
	zbx_vector_ptr_destroy(&corr_old);
}

/******************************************************************************
 *                                                                            *
 * Function: correlate_events_by_global_rules                                 *
 *                                                                            *
 * Purpose: add events to the closing queue according to global correlation   *
 *          rules                                                             *
 *                                                                            *
 ******************************************************************************/
static void	correlate_events_by_global_rules(zbx_vector_ptr_t *trigger_events, zbx_vector_ptr_t *trigger_diff)
{
	const char		*__function_name = "correlate_events_by_global_rules";

	int			i, index;
	zbx_trigger_diff_t	*diff;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() events:%d", __function_name, correlation_cache.num_data);

	zbx_dc_correlation_rules_get(&correlation_rules);

	/* process global correlation and queue the events that must be closed */
	for (i = 0; i < trigger_events->values_num; i++)
	{
		DB_EVENT	*event = (DB_EVENT *)trigger_events->values[i];

		if (0 == (ZBX_FLAGS_DB_EVENT_CREATE & event->flags))
			continue;

		correlate_event_by_global_rules(event);

		/* force value recalculation based on open problems for triggers with */
		/* events closed by 'close new' correlation operation                */
		if (0 != (event->flags & ZBX_FLAGS_DB_EVENT_NO_ACTION))
		{
			if (FAIL != (index = zbx_vector_ptr_bsearch(trigger_diff, &event->objectid,
					ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				diff = (zbx_trigger_diff_t *)trigger_diff->values[index];
				diff->flags |= ZBX_FLAGS_TRIGGER_DIFF_RECALCULATE_PROBLEM_COUNT;
			}
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

/******************************************************************************
 *                                                                            *
 * Function: flush_correlation_queue                                          *
 *                                                                            *
 * Purpose: try flushing correlation close events queue, generated by         *
 *          correlation rules                                                 *
 *                                                                            *
 ******************************************************************************/
static void	flush_correlation_queue(zbx_vector_ptr_t *trigger_diff, zbx_vector_uint64_t *triggerids_lock)
{
	const char		*__function_name = "flush_correlation_queue";

	zbx_vector_uint64_t	triggerids, lockids, eventids;
	zbx_hashset_iter_t	iter;
	zbx_event_recovery_t	*recovery;
	int			i, closed_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() events:%d", __function_name, correlation_cache.num_data);

	if (0 == correlation_cache.num_data)
		goto out;

	zbx_vector_uint64_create(&triggerids);
	zbx_vector_uint64_create(&lockids);
	zbx_vector_uint64_create(&eventids);

	/* lock source triggers of events to be closed by global correlation rules */

	zbx_vector_uint64_sort(triggerids_lock, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	/* create a list of triggers that must be locked to close correlated events */
	zbx_hashset_iter_reset(&correlation_cache, &iter);
	while (NULL != (recovery = (zbx_event_recovery_t *)zbx_hashset_iter_next(&iter)))
	{
		if (FAIL != zbx_vector_uint64_bsearch(triggerids_lock, recovery->objectid,
				ZBX_DEFAULT_UINT64_COMPARE_FUNC))
		{
			/* trigger already locked by this process, add to locked triggerids */
			zbx_vector_uint64_append(&triggerids, recovery->objectid);
		}
		else
			zbx_vector_uint64_append(&lockids, recovery->objectid);
	}

	if (0 != lockids.values_num)
	{
		int	num = triggerids_lock->values_num;

		zbx_vector_uint64_sort(&lockids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&lockids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		DCconfig_lock_triggers_by_triggerids(&lockids, triggerids_lock);

		/* append the locked trigger ids to already locked trigger ids */
		for (i = num; i < triggerids_lock->values_num; i++)
			zbx_vector_uint64_append(&triggerids, triggerids_lock->values[i]);
	}

	/* process global correlation actions if we have successfully locked trigger(s) */
	if (0 != triggerids.values_num)
	{
		DC_TRIGGER		*triggers, *trigger;
		int			*errcodes, index;
		char			*sql = NULL;
		size_t			sql_alloc = 0, sql_offset = 0;
		zbx_trigger_diff_t	*diff;

		/* get locked trigger data - needed for trigger diff and event generation */

		zbx_vector_uint64_sort(&triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		triggers = (DC_TRIGGER *)zbx_malloc(NULL, sizeof(DC_TRIGGER) * triggerids.values_num);
		errcodes = (int *)zbx_malloc(NULL, sizeof(int) * triggerids.values_num);

		DCconfig_get_triggers_by_triggerids(triggers, triggerids.values, errcodes, triggerids.values_num);

		/* add missing diffs to the trigger changeset */

		for (i = 0; i < triggerids.values_num; i++)
		{
			if (SUCCEED != errcodes[i])
				continue;

			trigger = &triggers[i];

			if (FAIL == (index = zbx_vector_ptr_bsearch(trigger_diff, &triggerids.values[i],
					ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				zbx_append_trigger_diff(trigger_diff, trigger->triggerid, trigger->priority,
						ZBX_FLAGS_TRIGGER_DIFF_RECALCULATE_PROBLEM_COUNT, trigger->value,
						TRIGGER_STATE_NORMAL, 0, NULL);

				/* TODO: should we store trigger diffs in hashset rather than vector? */
				zbx_vector_ptr_sort(trigger_diff, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
			}
			else
			{
				diff = (zbx_trigger_diff_t *)trigger_diff->values[index];
				diff->flags |= ZBX_FLAGS_TRIGGER_DIFF_RECALCULATE_PROBLEM_COUNT;
			}
		}

		/* get correlated eventids that are still open (unresolved) */

		zbx_hashset_iter_reset(&correlation_cache, &iter);
		while (NULL != (recovery = (zbx_event_recovery_t *)zbx_hashset_iter_next(&iter)))
		{
			/* close event only if its source trigger has been locked */
			if (FAIL == (index = zbx_vector_uint64_bsearch(&triggerids, recovery->objectid,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				continue;
			}

			if (SUCCEED != errcodes[index])
				continue;

			zbx_vector_uint64_append(&eventids, recovery->eventid);
		}

		zbx_vector_uint64_sort(&eventids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select eventid from problem"
								" where r_eventid is null and");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "eventid", eventids.values, eventids.values_num);
		zbx_vector_uint64_clear(&eventids);
		DBselect_uint64(sql, &eventids);
		zbx_free(sql);

		/* generate OK events and add event_recovery data for closed events */
		zbx_hashset_iter_reset(&correlation_cache, &iter);
		while (NULL != (recovery = (zbx_event_recovery_t *)zbx_hashset_iter_next(&iter)))
		{
			if (FAIL == (index = zbx_vector_uint64_bsearch(&triggerids, recovery->objectid,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC)))
			{
				continue;
			}

			/* close the old problem only if it's still open and trigger is not removed */
			if (SUCCEED == errcodes[index] && FAIL != zbx_vector_uint64_bsearch(&eventids, recovery->eventid,
					ZBX_DEFAULT_UINT64_COMPARE_FUNC))
			{
				trigger = &triggers[index];

				close_trigger_event(recovery->eventid, recovery->objectid, &recovery->ts, 0,
						recovery->correlationid, recovery->c_eventid, trigger->description,
						trigger->expression_orig, trigger->recovery_expression_orig,
						trigger->priority, trigger->type);

				closed_num++;
			}

			zbx_hashset_iter_remove(&iter);
		}

		DCconfig_clean_triggers(triggers, errcodes, triggerids.values_num);
		zbx_free(errcodes);
		zbx_free(triggers);
	}

	zbx_vector_uint64_destroy(&eventids);
	zbx_vector_uint64_destroy(&lockids);
	zbx_vector_uint64_destroy(&triggerids);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() closed:%d", __function_name, closed_num);
}

/******************************************************************************
 *                                                                            *
 * Function: update_trigger_problem_count                                     *
 *                                                                            *
 * Purpose: update number of open problems                                    *
 *                                                                            *
 * Parameters: trigger_diff    - [IN/OUT] the changeset of triggers that      *
 *                               generated the events in local cache.         *
 *                                                                            *
 * Comments: When a specific event is closed (by correlation or manually) the *
 *           open problem count has to be queried from problem table to       *
 *           correctly calculate new trigger value.                           *
 *                                                                            *
 ******************************************************************************/
static void	update_trigger_problem_count(zbx_vector_ptr_t *trigger_diff)
{
	DB_RESULT		result;
	DB_ROW			row;
	zbx_vector_uint64_t	triggerids;
	zbx_trigger_diff_t	*diff;
	int			i, index;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t		triggerid;

	zbx_vector_uint64_create(&triggerids);

	for (i = 0; i < trigger_diff->values_num; i++)
	{
		diff = (zbx_trigger_diff_t *)trigger_diff->values[i];

		if (0 != (diff->flags & ZBX_FLAGS_TRIGGER_DIFF_RECALCULATE_PROBLEM_COUNT))
		{
			zbx_vector_uint64_append(&triggerids, diff->triggerid);

			/* reset problem count, it will be updated from database if there are open problems */
			diff->problem_count = 0;
			diff->flags |= ZBX_FLAGS_TRIGGER_DIFF_UPDATE_PROBLEM_COUNT;
		}
	}

	if (0 == triggerids.values_num)
		goto out;

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select objectid,count(objectid) from problem"
			" where r_eventid is null"
				" and source=%d"
				" and object=%d"
				" and",
			EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER);

	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "objectid", triggerids.values, triggerids.values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " group by objectid");

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(triggerid, row[0]);

		if (FAIL == (index = zbx_vector_ptr_bsearch(trigger_diff, &triggerid,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		diff = (zbx_trigger_diff_t *)trigger_diff->values[index];
		diff->problem_count = atoi(row[1]);
		diff->flags |= ZBX_FLAGS_TRIGGER_DIFF_UPDATE_PROBLEM_COUNT;
	}
	DBfree_result(result);

	zbx_free(sql);
out:
	zbx_vector_uint64_destroy(&triggerids);
}

/******************************************************************************
 *                                                                            *
 * Function: update_trigger_changes                                           *
 *                                                                            *
 * Purpose: update trigger value, problem count fields depending on problem   *
 *          and recovered events                                              *
 *                                                                            *
 ******************************************************************************/
static void	update_trigger_changes(zbx_vector_ptr_t *trigger_diff)
{
	size_t			i;
	int			index, j, new_value;
	zbx_trigger_diff_t	*diff;

	update_trigger_problem_count(trigger_diff);

	/* update trigger problem_count for new problem events */
	for (i = 0; i < events_num; i++)
	{
		DB_EVENT	*event = &events[i];

		if (EVENT_SOURCE_TRIGGERS != event->source || EVENT_OBJECT_TRIGGER != event->object)
			continue;

		if (FAIL == (index = zbx_vector_ptr_bsearch(trigger_diff, &event->objectid,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		diff = (zbx_trigger_diff_t *)trigger_diff->values[index];

		if (0 == (event->flags & ZBX_FLAGS_DB_EVENT_CREATE))
		{
			diff->flags &= ~(zbx_uint64_t)(ZBX_FLAGS_TRIGGER_DIFF_UPDATE_PROBLEM_COUNT |
					ZBX_FLAGS_TRIGGER_DIFF_UPDATE_LASTCHANGE);
			continue;
		}

		/* always update trigger last change whenever a trigger event has been created */
		diff->lastchange = event->clock;
		diff->flags |= ZBX_FLAGS_TRIGGER_DIFF_UPDATE_LASTCHANGE;
	}

	/* recalculate trigger value from problem_count and mark for updating if necessary */
	for (j = 0; j < trigger_diff->values_num; j++)
	{
		diff = (zbx_trigger_diff_t *)trigger_diff->values[j];

		if (0 == (diff->flags & ZBX_FLAGS_TRIGGER_DIFF_UPDATE_PROBLEM_COUNT))
			continue;

		new_value = (0 == diff->problem_count ? TRIGGER_VALUE_OK : TRIGGER_VALUE_PROBLEM);

		if (new_value != diff->value)
		{
			diff->value = new_value;
			diff->flags |= ZBX_FLAGS_TRIGGER_DIFF_UPDATE_VALUE;
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_initialize_events                                            *
 *                                                                            *
 * Purpose: initializes the data structures required for event processing     *
 *                                                                            *
 ******************************************************************************/
void	zbx_initialize_events(void)
{
	zbx_hashset_create(&event_recovery, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_hashset_create(&correlation_cache, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_dc_correlation_rules_init(&correlation_rules);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_uninitialize_events                                          *
 *                                                                            *
 * Purpose: uninitializes the data structures required for event processing   *
 *                                                                            *
 ******************************************************************************/
void	zbx_uninitialize_events(void)
{
	zbx_hashset_destroy(&event_recovery);
	zbx_hashset_destroy(&correlation_cache);

	zbx_dc_correlation_rules_free(&correlation_rules);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_clean_events                                                 *
 *                                                                            *
 * Purpose: cleans all array entries and resets events_num                    *
 *                                                                            *
 ******************************************************************************/
void	zbx_clean_events(void)
{
	size_t	i;

	for (i = 0; i < events_num; i++)
	{
		zbx_free(events[i].name);

		if (EVENT_SOURCE_TRIGGERS != events[i].source)
			continue;

		zbx_free(events[i].trigger.description);
		zbx_free(events[i].trigger.expression);
		zbx_free(events[i].trigger.recovery_expression);
		zbx_free(events[i].trigger.correlation_tag);

		zbx_vector_ptr_clear_ext(&events[i].tags, (zbx_clean_func_t)zbx_free_tag);
		zbx_vector_ptr_destroy(&events[i].tags);
	}

	events_num = 0;

	zbx_hashset_clear(&event_recovery);
}
static void	get_hosts_by_expression(zbx_hashset_t *hosts, const char *expression, const char *recovery_expression)
{
	zbx_vector_uint64_t	functionids;

	zbx_vector_uint64_create(&functionids);
	get_functionids(&functionids, expression);
	get_functionids(&functionids, recovery_expression);
	DCget_hosts_by_functionids(&functionids, hosts);
	zbx_vector_uint64_destroy(&functionids);
}

void	zbx_export_events(void)
{
	size_t			i;
	struct zbx_json		json;
	size_t			sql_alloc = 256, sql_offset;
	char			*sql = NULL;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_hashset_t		hosts;
	zbx_vector_uint64_t	hostids;

	if (0 == events_num)
		return;

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);
	sql = (char *)zbx_malloc(sql, sql_alloc);
	zbx_hashset_create(&hosts, events_num, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_create(&hostids);

	for (i = 0; i < events_num; i++)
	{
		if (EVENT_SOURCE_TRIGGERS != events[i].source)
			continue;

		zbx_json_clean(&json);

		zbx_json_addint64(&json, "clock", events[i].clock);
		zbx_json_addint64(&json, "ns", events[i].ns);

		if (TRIGGER_VALUE_PROBLEM == events[i].value)
		{
			DC_HOST			*host;
			zbx_hashset_iter_t	iter;

			zbx_json_adduint64(&json, "eventid", events[i].eventid);
			zbx_json_addstring(&json, "name", events[i].name, ZBX_JSON_TYPE_STRING);

			get_hosts_by_expression(&hosts, events[i].trigger.expression,
					events[i].trigger.recovery_expression);

			zbx_json_addarray(&json, "hosts");

			zbx_hashset_iter_reset(&hosts, &iter);
			while (NULL != (host = (DC_HOST *)zbx_hashset_iter_next(&iter)))
			{
				zbx_json_addstring(&json, NULL, host->name, ZBX_JSON_TYPE_STRING);
				zbx_vector_uint64_append(&hostids, host->hostid);
			}

			zbx_json_close(&json);

			sql_offset = 0;
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
						"select distinct g.name"
						" from groups g, hosts_groups hg"
						" where g.groupid=hg.groupid"
							" and");

			DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hg.hostid", hostids.values,
					hostids.values_num);

			result = DBselect("%s", sql);

			zbx_json_addarray(&json, "groups");

			while (NULL != (row = DBfetch(result)))
				zbx_json_addstring(&json, NULL, row[0], ZBX_JSON_TYPE_STRING);
			DBfree_result(result);

			zbx_hashset_clear(&hosts);
			zbx_vector_uint64_clear(&hostids);
		}
		else
		{
			zbx_hashset_iter_t	iter;
			zbx_event_recovery_t	*recovery_local;

			zbx_hashset_iter_reset(&event_recovery, &iter);
			while (NULL != (recovery_local = (zbx_event_recovery_t *)zbx_hashset_iter_next(&iter)))
			{
				if (events[i].eventid == events[recovery_local->r_event_index].eventid)
				{
					zbx_json_adduint64(&json, "eventid", recovery_local->eventid);
					break;
				}
			}
		}

		zbx_problems_export_write(json.buffer, json.buffer_size);
	}

	zbx_problems_export_flush();

	zbx_hashset_destroy(&hosts);
	zbx_vector_uint64_destroy(&hostids);
	zbx_free(sql);
	zbx_json_free(&json);
}

/******************************************************************************
 *                                                                            *
 * Function: flush_events                                                     *
 *                                                                            *
 * Purpose: flushes local event cache to database                             *
 *                                                                            *
 ******************************************************************************/
static int	flush_events(void)
{
	int				ret;
	zbx_event_recovery_t		*recovery;
	zbx_vector_uint64_pair_t	closed_events;
	zbx_hashset_iter_t		iter;

	ret = save_events();
	save_problems();
	save_event_recovery();

	zbx_vector_uint64_pair_create(&closed_events);

	zbx_hashset_iter_reset(&event_recovery, &iter);
	while (NULL != (recovery = (zbx_event_recovery_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_uint64_pair_t	pair = {recovery->eventid, events[recovery->r_event_index].eventid};

		zbx_vector_uint64_pair_append_ptr(&closed_events, &pair);
	}

	zbx_vector_uint64_pair_sort(&closed_events, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	process_actions(events, events_num, &closed_events);
	zbx_vector_uint64_pair_destroy(&closed_events);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: recover_event                                                    *
 *                                                                            *
 * Purpose: recover an event                                                  *
 *                                                                            *
 * Parameters: eventid   - [IN] the event to recover                          *
 *             source    - [IN] the recovery event source                     *
 *             object    - [IN] the recovery event object                     *
 *             objectid  - [IN] the recovery event object id                  *
 *                                                                            *
 ******************************************************************************/
static void	recover_event(zbx_uint64_t eventid, int source, int object, zbx_uint64_t objectid)
{
	int			index;
	zbx_event_recovery_t	recovery_local;

	if (FAIL == (index = get_event_index_by_source_object_id(source, object, objectid)))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return;
	}

	recovery_local.eventid = eventid;

	if (NULL != zbx_hashset_search(&event_recovery, &recovery_local))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return;
	}

	recovery_local.objectid = objectid;
	recovery_local.r_event_index = index;
	recovery_local.correlationid = 0;
	recovery_local.c_eventid = 0;
	recovery_local.userid = 0;
	zbx_hashset_insert(&event_recovery, &recovery_local, sizeof(recovery_local));
}

/******************************************************************************
 *                                                                            *
 * Function: process_internal_ok_events                                       *
 *                                                                            *
 * Purpose: process internal recovery events                                  *
 *                                                                            *
 * Parameters: ok_events - [IN] the recovery events to process                *
 *                                                                            *
 ******************************************************************************/
static void	process_internal_ok_events(zbx_vector_ptr_t *ok_events)
{
	int			i, object;
	zbx_uint64_t		objectid, eventid;
	char			*sql = NULL;
	const char		*separator = "";
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	triggerids, itemids, lldruleids;
	DB_RESULT		result;
	DB_ROW			row;
	DB_EVENT		*event;

	zbx_vector_uint64_create(&triggerids);
	zbx_vector_uint64_create(&itemids);
	zbx_vector_uint64_create(&lldruleids);

	for (i = 0; i < ok_events->values_num; i++)
	{
		event = (DB_EVENT *)ok_events->values[i];

		switch (event->object)
		{
			case EVENT_OBJECT_TRIGGER:
				zbx_vector_uint64_append(&triggerids, event->objectid);
				break;
			case EVENT_OBJECT_ITEM:
				zbx_vector_uint64_append(&itemids, event->objectid);
				break;
			case EVENT_OBJECT_LLDRULE:
				zbx_vector_uint64_append(&lldruleids, event->objectid);
				break;
		}
	}

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select eventid,object,objectid from problem"
			" where r_eventid is null"
				" and source=%d"
			" and (", EVENT_SOURCE_INTERNAL);

	if (0 != triggerids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%s (object=%d and",
				separator, EVENT_OBJECT_TRIGGER);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "objectid", triggerids.values,
				triggerids.values_num);
		zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, ')');
		separator=" or";
	}

	if (0 != itemids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%s (object=%d and",
				separator, EVENT_OBJECT_ITEM);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "objectid", itemids.values,
				itemids.values_num);
		zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, ')');
		separator=" or";
	}

	if (0 != lldruleids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%s (object=%d and",
				separator, EVENT_OBJECT_LLDRULE);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "objectid", lldruleids.values,
				lldruleids.values_num);
		zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, ')');
	}

	zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, ')');
	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(eventid, row[0]);
		object = atoi(row[1]);
		ZBX_STR2UINT64(objectid, row[2]);

		recover_event(eventid, EVENT_SOURCE_INTERNAL, object, objectid);
	}

	DBfree_result(result);
	zbx_free(sql);

	zbx_vector_uint64_destroy(&lldruleids);
	zbx_vector_uint64_destroy(&itemids);
	zbx_vector_uint64_destroy(&triggerids);
}

/******************************************************************************
 *                                                                            *
 * Function: get_open_problems                                                *
 *                                                                            *
 * Purpose: gets open problems created by the specified triggers              *
 *                                                                            *
 * Parameters: triggerids - [IN] the trigger identifiers (sorted)             *
 *             problems   - [OUT] the problems                                *
 *                                                                            *
 ******************************************************************************/
static void	get_open_problems(const zbx_vector_uint64_t *triggerids, zbx_vector_ptr_t *problems)
{
	DB_RESULT		result;
	DB_ROW			row;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_event_problem_t	*problem;
	zbx_tag_t		*tag;
	zbx_uint64_t		eventid;
	int			index;
	zbx_vector_uint64_t	eventids;

	zbx_vector_uint64_create(&eventids);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"select eventid,objectid from problem where source=%d and object=%d and",
			EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "objectid", triggerids->values, triggerids->values_num);
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, " and r_eventid is null");

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		problem = (zbx_event_problem_t *)zbx_malloc(NULL, sizeof(zbx_event_problem_t));

		ZBX_STR2UINT64(problem->eventid, row[0]);
		ZBX_STR2UINT64(problem->triggerid, row[1]);
		zbx_vector_ptr_create(&problem->tags);
		zbx_vector_ptr_append(problems, problem);

		zbx_vector_uint64_append(&eventids, problem->eventid);
	}
	DBfree_result(result);

	if (0 != problems->values_num)
	{
		zbx_vector_ptr_sort(problems, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);
		zbx_vector_uint64_sort(&eventids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		sql_offset = 0;
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select eventid,tag,value from problem_tag where ");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "eventid", eventids.values, eventids.values_num);

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(eventid, row[0]);
			if (FAIL == (index = zbx_vector_ptr_bsearch(problems, &eventid,
					ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			problem = (zbx_event_problem_t *)problems->values[index];

			tag = (zbx_tag_t *)zbx_malloc(NULL, sizeof(zbx_tag_t));
			tag->tag = zbx_strdup(NULL, row[1]);
			tag->value = zbx_strdup(NULL, row[2]);
			zbx_vector_ptr_append(&problem->tags, tag);
		}
		DBfree_result(result);
	}

	zbx_free(sql);

	zbx_vector_uint64_destroy(&eventids);
}

/******************************************************************************
 *                                                                            *
 * Function: event_problem_free                                               *
 *                                                                            *
 * Purpose: frees cached problem event                                        *
 *                                                                            *
 ******************************************************************************/
static void	event_problem_free(zbx_event_problem_t *problem)
{
	zbx_vector_ptr_clear_ext(&problem->tags, (zbx_clean_func_t)zbx_free_tag);
	zbx_vector_ptr_destroy(&problem->tags);
	zbx_free(problem);
}


/******************************************************************************
 *                                                                            *
 * Function: trigger_dep_free                                                 *
 *                                                                            *
 * Purpose: frees trigger dependency                                          *
 *                                                                            *
 ******************************************************************************/

static void	trigger_dep_free(zbx_trigger_dep_t *dep)
{
	zbx_vector_uint64_destroy(&dep->masterids);
	zbx_free(dep);
}

/******************************************************************************
 *                                                                            *
 * Function: event_check_dependency                                           *
 *                                                                            *
 * Purpose: check event dependency based on cached and actual trigger values  *
 *                                                                            *
 * Parameters: event        - [IN] the event to check                         *
 *             deps         - [IN] the trigger dependency data (sorted by     *
 *                                 triggerid)                                 *
 *             trigger_diff - [IN] the trigger changeset - source of actual   *
 *                                 trigger values (sorted by triggerid)       *
 *                                                                            *
 ******************************************************************************/
static int	event_check_dependency(const DB_EVENT *event, const zbx_vector_ptr_t *deps,
		const zbx_vector_ptr_t *trigger_diff)
{
	int			i, index;
	zbx_trigger_dep_t	*dep;
	zbx_trigger_diff_t	*diff;

	if (FAIL == (index = zbx_vector_ptr_bsearch(deps, &event->objectid, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		return SUCCEED;

	dep = (zbx_trigger_dep_t *)deps->values[index];

	if (ZBX_TRIGGER_DEPENDENCY_FAIL == dep->status)
		return FAIL;

	/* check the trigger dependency based on actual (currently being processed) trigger values */
	for (i = 0; i < dep->masterids.values_num; i++)
	{
		if (FAIL == (index = zbx_vector_ptr_bsearch(trigger_diff, &dep->masterids.values[i],
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		diff = (zbx_trigger_diff_t *)trigger_diff->values[index];

		if (0 == (ZBX_FLAGS_TRIGGER_DIFF_UPDATE_VALUE & diff->flags))
			continue;

		if (TRIGGER_VALUE_PROBLEM == diff->value)
			return FAIL;
	}

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: match_tags                                                       *
 *                                                                            *
 * Purpose: match two tag vectors                                             *
 *                                                                            *
 * Parameters: tags1 - [IN] the first tag vector                              *
 *             tags2 - [IN] the second tag vector                             *
 *                                                                            *
 * Return value: SUCCEED - at least one tag/value from the first vector       *
 *                         matches tag/value from the second vector.          *
 *               FAIL    - otherwise.                                         *
 *                                                                            *
 ******************************************************************************/
static int	match_tags(const zbx_vector_ptr_t *tags1, const zbx_vector_ptr_t *tags2)
{
	int		i, j;
	zbx_tag_t	*tag1, *tag2;

	for (i = 0; i < tags1->values_num; i++)
	{
		tag1 = (zbx_tag_t *)tags1->values[i];

		for (j = 0; j < tags2->values_num; j++)
		{
			tag2 = (zbx_tag_t *)tags2->values[j];

			if (0 == strcmp(tag1->tag, tag2->tag) && 0 == strcmp(tag1->value, tag2->value))
				return SUCCEED;
		}
	}

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: process_trigger_events                                           *
 *                                                                            *
 * Purpose: processes trigger events                                          *
 *                                                                            *
 * Parameters: trigger_events - [IN] the trigger events to process            *
 *             trigger_diff   - [IN] the trigger changeset                    *
 *                                                                            *
 ******************************************************************************/
static void	process_trigger_events(zbx_vector_ptr_t *trigger_events, zbx_vector_ptr_t *trigger_diff)
{
	int			i, j, index;
	zbx_vector_uint64_t	triggerids;
	zbx_vector_ptr_t	problems, deps;
	DB_EVENT		*event;
	zbx_event_problem_t	*problem;
	zbx_trigger_diff_t	*diff;
	unsigned char		value;

	zbx_vector_uint64_create(&triggerids);
	zbx_vector_uint64_reserve(&triggerids, trigger_events->values_num);

	zbx_vector_ptr_create(&problems);
	zbx_vector_ptr_reserve(&problems, trigger_events->values_num);

	zbx_vector_ptr_create(&deps);
	zbx_vector_ptr_reserve(&deps, trigger_events->values_num);

	/* cache relevant problems */

	for (i = 0; i < trigger_events->values_num; i++)
	{
		event = (DB_EVENT *)trigger_events->values[i];

		if (TRIGGER_VALUE_OK == event->value)
			zbx_vector_uint64_append(&triggerids, event->objectid);
	}

	if (0 != triggerids.values_num)
	{
		zbx_vector_uint64_sort(&triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		get_open_problems(&triggerids, &problems);
	}

	/* get trigger dependency data */

	zbx_vector_uint64_clear(&triggerids);
	for (i = 0; i < trigger_events->values_num; i++)
	{
		event = (DB_EVENT *)trigger_events->values[i];
		zbx_vector_uint64_append(&triggerids, event->objectid);
	}

	zbx_vector_uint64_sort(&triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_dc_get_trigger_dependencies(&triggerids, &deps);

	/* process trigger events */

	for (i = 0; i < trigger_events->values_num; i++)
	{
		event = (DB_EVENT *)trigger_events->values[i];

		if (FAIL == (index = zbx_vector_ptr_search(trigger_diff, &event->objectid,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		diff = (zbx_trigger_diff_t *)trigger_diff->values[index];

		if (FAIL == (event_check_dependency(event, &deps, trigger_diff)))
		{
			/* reset event data/trigger changeset if dependency check failed */
			event->flags = ZBX_FLAGS_DB_EVENT_UNSET;
			diff->flags = ZBX_FLAGS_TRIGGER_DIFF_UNSET;
			continue;
		}

		if (TRIGGER_VALUE_PROBLEM == event->value)
		{
			/* Problem events always sets problem value to trigger.    */
			/* if the trigger is affected by global correlation rules, */
			/* its value is recalculated later.                        */
			diff->value = TRIGGER_VALUE_PROBLEM;
			diff->lastchange = event->clock;
			diff->flags |= (ZBX_FLAGS_TRIGGER_DIFF_UPDATE_VALUE | ZBX_FLAGS_TRIGGER_DIFF_UPDATE_LASTCHANGE);
			continue;
		}

		if (TRIGGER_VALUE_OK != event->value)
			continue;

		/* attempt to recover problem events/triggers */

		if (ZBX_TRIGGER_CORRELATION_NONE == event->trigger.correlation_mode)
		{
			/* with trigger correlation disabled the recovery event recovers */
			/* all problem events generated by the same trigger and sets     */
			/* trigger value to OK                                           */
			for (j = 0; j < problems.values_num; j++)
			{
				problem = (zbx_event_problem_t *)problems.values[j];

				if (problem->triggerid == event->objectid)
				{
					recover_event(problem->eventid, EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER,
							event->objectid);
				}
			}

			diff->value = TRIGGER_VALUE_OK;
			diff->flags |= ZBX_FLAGS_TRIGGER_DIFF_UPDATE_VALUE;
		}
		else
		{
			/* With trigger correlation enabled the recovery event recovers   */
			/* all problem events generated by the same trigger and matching  */
			/* recovery event tags. Te trigger value is set to OK only if all */
			/* problem events were recovered.                                 */

			value = TRIGGER_VALUE_OK;

			for (j = 0; j < problems.values_num; j++)
			{
				problem = (zbx_event_problem_t *)problems.values[j];

				if (problem->triggerid == event->objectid)
				{
					if (SUCCEED == match_tags(&problem->tags, &event->tags))
					{
						recover_event(problem->eventid, EVENT_SOURCE_TRIGGERS,
								EVENT_OBJECT_TRIGGER, event->objectid);
					}
					else
						value = TRIGGER_VALUE_PROBLEM;

				}
			}

			diff->value = value;
			diff->flags |= ZBX_FLAGS_TRIGGER_DIFF_UPDATE_VALUE;
		}
	}

	zbx_vector_ptr_clear_ext(&problems, (zbx_clean_func_t)event_problem_free);
	zbx_vector_ptr_destroy(&problems);

	zbx_vector_ptr_clear_ext(&deps, (zbx_clean_func_t)trigger_dep_free);
	zbx_vector_ptr_destroy(&deps);

	zbx_vector_uint64_destroy(&triggerids);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_process_events                                               *
 *                                                                            *
 * Purpose: processes cached events                                           *
 *                                                                            *
 * Parameters: trigger_diff    - [IN/OUT] the changeset of triggers that      *
 *                               generated the events in local cache. When    *
 *                               processing global correlation rules new      *
 *                               diffs can be added to trigger changeset.     *
 *                               Can be NULL when processing events from      *
 *                               non trigger sources                          *
 *             triggerids_lock - [IN/OUT] the ids of triggers locked by items.*
 *                               When processing global correlation rules new *
 *                               triggers can be locked and added to this     *
 *                               vector.                                      *
 *                               Can be NULL when processing events from      *
 *                               non trigger sources                          *
 *                                                                            *
 * Return value: The number of processed events                               *
 *                                                                            *
 ******************************************************************************/
int	zbx_process_events(zbx_vector_ptr_t *trigger_diff, zbx_vector_uint64_t *triggerids_lock)
{
	const char		*__function_name = "process_events";
	size_t			i, processed_num = 0;
	zbx_uint64_t		eventid;
	zbx_vector_ptr_t	internal_ok_events, trigger_events;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() events_num:" ZBX_FS_SIZE_T, __function_name, (zbx_fs_size_t)events_num);

	if (0 != events_num)
	{
		zbx_vector_ptr_create(&internal_ok_events);
		zbx_vector_ptr_reserve(&internal_ok_events, events_num);

		zbx_vector_ptr_create(&trigger_events);
		zbx_vector_ptr_reserve(&trigger_events, events_num);

		/* assign event identifiers - they are required to set correlation event ids */
		eventid = DBget_maxid_num("events", events_num);
		for (i = 0; i < events_num; i++)
		{
			DB_EVENT	*event = &events[i];

			event->eventid = eventid++;

			if (EVENT_SOURCE_TRIGGERS == event->source)
			{
				zbx_vector_ptr_append(&trigger_events, event);
				continue;
			}

			if (EVENT_SOURCE_INTERNAL == event->source)
			{
				switch (event->object)
				{
					case EVENT_OBJECT_TRIGGER:
						if (TRIGGER_STATE_NORMAL == event->value)
							zbx_vector_ptr_append(&internal_ok_events, event);
						break;
					case EVENT_OBJECT_ITEM:
						if (ITEM_STATE_NORMAL == event->value)
							zbx_vector_ptr_append(&internal_ok_events, event);
						break;
					case EVENT_OBJECT_LLDRULE:
						if (ITEM_STATE_NORMAL == event->value)
							zbx_vector_ptr_append(&internal_ok_events, event);
						break;
				}
			}
		}

		if (0 != internal_ok_events.values_num)
			process_internal_ok_events(&internal_ok_events);

		if (0 != trigger_events.values_num)
		{
			process_trigger_events(&trigger_events, trigger_diff);
			correlate_events_by_global_rules(&trigger_events, trigger_diff);
			flush_correlation_queue(trigger_diff, triggerids_lock);
		}

		processed_num = flush_events();

		if (0 != trigger_events.values_num)
			update_trigger_changes(trigger_diff);

		zbx_vector_ptr_destroy(&trigger_events);
		zbx_vector_ptr_destroy(&internal_ok_events);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() processed:%d", __function_name, processed_num);

	return processed_num;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_close_problem                                                *
 *                                                                            *
 * Purpose: closes problem event                                              *
 *                                                                            *
 * Parameters: triggerid - [IN] the source trigger id                         *
 *             eventid   - [IN] the event to close                            *
 *             userid    - [IN] the user closing the event                    *
 *                                                                            *
 * Return value: SUCCEED - the problem was closed                             *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_close_problem(zbx_uint64_t triggerid, zbx_uint64_t eventid, zbx_uint64_t userid)
{
	DC_TRIGGER	trigger;
	int		errcode, index, processed_num = 0;
	zbx_timespec_t	ts;

	DCconfig_get_triggers_by_triggerids(&trigger, &triggerid, &errcode, 1);

	if (SUCCEED == errcode)
	{
		zbx_vector_ptr_t	trigger_diff;

		zbx_vector_ptr_create(&trigger_diff);

		zbx_append_trigger_diff(&trigger_diff, triggerid, trigger.priority,
				ZBX_FLAGS_TRIGGER_DIFF_RECALCULATE_PROBLEM_COUNT, trigger.value,
				TRIGGER_STATE_NORMAL, 0, NULL);

		zbx_timespec(&ts);

		DBbegin();

		index = close_trigger_event(eventid, triggerid, &ts, userid, 0, 0, trigger.description,
				trigger.expression_orig, trigger.recovery_expression_orig, trigger.priority,
				trigger.type);

		events[index].eventid = DBget_maxid_num("events", 1);

		processed_num = flush_events();
		update_trigger_changes(&trigger_diff);
		zbx_db_save_trigger_changes(&trigger_diff);

		DBcommit();

		DCconfig_triggers_apply_changes(&trigger_diff);
		DBupdate_itservices(&trigger_diff);

		if (SUCCEED == zbx_is_export_enabled())
			zbx_export_events();

		zbx_clean_events();
		zbx_vector_ptr_clear_ext(&trigger_diff, (zbx_clean_func_t)zbx_trigger_diff_free);
		zbx_vector_ptr_destroy(&trigger_diff);
	}

	DCconfig_clean_triggers(&trigger, &errcode, 1);

	return (0 == processed_num ? FAIL : SUCCEED);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_flush_correlated_events                                      *
 *                                                                            *
 * Purpose: try flushing closing events queued by correlation operations      *
 *                                                                            *
 * Return value: The number of events left in correlation queue               *
 *                                                                            *
 * Comments: This function will try to lock corresponding triggers before     *
 *           flushing closing events. If the trigger cannot be locked the     *
 *           event will stay in the queue.                                    *
 *                                                                            *
 ******************************************************************************/
int	zbx_flush_correlated_events(void)
{
	const char		*__function_name = "zbx_flush_correlated_events";
	zbx_vector_ptr_t	trigger_diff;
	zbx_vector_uint64_t	triggerids_lock;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() events_num:%d", __function_name, correlation_cache.num_data);

	if (0 == correlation_cache.num_data)
		goto out;

	zbx_vector_ptr_create(&trigger_diff);
	zbx_vector_uint64_create(&triggerids_lock);

	flush_correlation_queue(&trigger_diff, &triggerids_lock);

	if (0 != events_num)
	{
		DBbegin();

		flush_events();
		update_trigger_changes(&trigger_diff);
		DCconfig_triggers_apply_changes(&trigger_diff);
		zbx_db_save_trigger_changes(&trigger_diff);

		DBcommit();

		DBupdate_itservices(&trigger_diff);

		zbx_clean_events();
	}

	zbx_vector_ptr_clear_ext(&trigger_diff, (zbx_clean_func_t)zbx_trigger_diff_free);
	DCconfig_unlock_triggers(&triggerids_lock);

	zbx_vector_uint64_destroy(&triggerids_lock);
	zbx_vector_ptr_destroy(&trigger_diff);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() events_num:%d", __function_name, correlation_cache.num_data);

	return correlation_cache.num_data;
}
