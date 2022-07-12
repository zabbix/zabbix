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

#include "events.h"

#include "db_lengths.h"
#include "log.h"
#include "actions.h"
#include "zbxserver.h"
#include "zbxexport.h"
#include "zbxservice.h"

/* event recovery data */
typedef struct
{
	zbx_uint64_t	eventid;
	zbx_uint64_t	objectid;
	ZBX_DB_EVENT	*r_event;
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

typedef enum
{
	CORRELATION_MATCH = 0,
	CORRELATION_NO_MATCH,
	CORRELATION_MAY_MATCH
}
zbx_correlation_match_result_t;

static zbx_vector_ptr_t		events;
static zbx_hashset_t		event_recovery;
static zbx_hashset_t		correlation_cache;
static zbx_correlation_rules_t	correlation_rules;

/******************************************************************************
 *                                                                            *
 * Purpose: Check that tag name is not empty and that tag is not duplicate.   *
 *                                                                            *
 ******************************************************************************/
static int	validate_event_tag(const ZBX_DB_EVENT* event, const zbx_tag_t *tag)
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

static zbx_tag_t	*duplicate_tag(const zbx_tag_t *tag)
{
	zbx_tag_t	*t;

	t = (zbx_tag_t *)zbx_malloc(NULL, sizeof(zbx_tag_t));
	t->tag = zbx_strdup(NULL, tag->tag);
	t->value = zbx_strdup(NULL, tag->value);

	return t;
}

static void	validate_and_add_tag(ZBX_DB_EVENT* event, zbx_tag_t *tag)
{
	zbx_ltrim(tag->tag, ZBX_WHITESPACE);
	zbx_ltrim(tag->value, ZBX_WHITESPACE);

	if (ZBX_DB_TAG_NAME_LEN < zbx_strlen_utf8(tag->tag))
		tag->tag[zbx_strlen_utf8_nchars(tag->tag, ZBX_DB_TAG_NAME_LEN)] = '\0';
	if (ZBX_DB_TAG_VALUE_LEN < zbx_strlen_utf8(tag->value))
		tag->value[zbx_strlen_utf8_nchars(tag->value, ZBX_DB_TAG_VALUE_LEN)] = '\0';

	zbx_rtrim(tag->tag, ZBX_WHITESPACE);
	zbx_rtrim(tag->value, ZBX_WHITESPACE);

	if (SUCCEED == validate_event_tag(event, tag))
		zbx_vector_ptr_append(&event->tags, tag);
	else
		zbx_free_tag(tag);
}

static void	substitute_trigger_tag_macro(const ZBX_DB_EVENT* event, char **str)
{
	zbx_substitute_simple_macros(NULL, event, NULL, NULL, NULL, NULL, NULL, NULL,
			NULL, NULL, NULL, NULL, str, MACRO_TYPE_TRIGGER_TAG, NULL, 0);
}

static void	process_trigger_tag(ZBX_DB_EVENT* event, const zbx_tag_t *tag)
{
	zbx_tag_t	*t;

	t = duplicate_tag(tag);
	substitute_trigger_tag_macro(event, &t->tag);
	substitute_trigger_tag_macro(event, &t->value);
	validate_and_add_tag(event, t);
}

static void	substitute_item_tag_macro(const ZBX_DB_EVENT* event, const DC_ITEM *dc_item, char **str)
{
	zbx_substitute_simple_macros(NULL, event, NULL, NULL, NULL, NULL, dc_item, NULL,
			NULL, NULL, NULL, NULL, str, MACRO_TYPE_ITEM_TAG, NULL, 0);
}

static void	process_item_tag(ZBX_DB_EVENT* event, const zbx_item_tag_t *item_tag)
{
	zbx_tag_t	*t;
	DC_ITEM		dc_item; /* used to pass data into zbx_substitute_simple_macros() function */

	t = duplicate_tag(&item_tag->tag);

	dc_item.host.hostid = item_tag->hostid;
	dc_item.itemid = item_tag->itemid;

	substitute_item_tag_macro(event, &dc_item, &t->tag);
	substitute_item_tag_macro(event, &dc_item, &t->value);
	validate_and_add_tag(event, t);
}

static void	get_item_tags_by_expression(const ZBX_DB_TRIGGER *trigger, zbx_vector_ptr_t *item_tags)
{
	zbx_vector_uint64_t	functionids;

	zbx_vector_uint64_create(&functionids);
	zbx_db_trigger_get_functionids(trigger, &functionids);
	zbx_dc_get_item_tags_by_functionids(functionids.values, functionids.values_num, item_tags);
	zbx_vector_uint64_destroy(&functionids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: add event to an array                                             *
 *                                                                            *
 * Parameters: source   - [IN] event source (EVENT_SOURCE_*)                  *
 *             object   - [IN] event object (EVENT_OBJECT_*)                  *
 *             objectid - [IN] trigger, item ... identifier from database,    *
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
 *             trigger_opdata              - [IN] trigger operational data    *
 *             event_name                  - [IN] event name, can be NULL     *
 *             error                       - [IN] error for internal events   *
 *                                                                            *
 * Return value: The added event.                                             *
 *                                                                            *
 ******************************************************************************/
ZBX_DB_EVENT	*zbx_add_event(unsigned char source, unsigned char object, zbx_uint64_t objectid,
		const zbx_timespec_t *timespec, int value, const char *trigger_description,
		const char *trigger_expression, const char *trigger_recovery_expression, unsigned char trigger_priority,
		unsigned char trigger_type, const zbx_vector_ptr_t *trigger_tags,
		unsigned char trigger_correlation_mode, const char *trigger_correlation_tag,
		unsigned char trigger_value, const char *trigger_opdata, const char *event_name, const char *error)
{
	zbx_vector_ptr_t	item_tags;
	int			i;
	ZBX_DB_EVENT		*event;

	event = zbx_malloc(NULL, sizeof(ZBX_DB_EVENT));

	event->eventid = 0;
	event->source = source;
	event->object = object;
	event->objectid = objectid;
	event->name = NULL;
	event->clock = timespec->sec;
	event->ns = timespec->ns;
	event->value = value;
	event->acknowledged = EVENT_NOT_ACKNOWLEDGED;
	event->flags = ZBX_FLAGS_DB_EVENT_CREATE;
	event->severity = TRIGGER_SEVERITY_NOT_CLASSIFIED;
	event->suppressed = ZBX_PROBLEM_SUPPRESSED_FALSE;

	if (EVENT_SOURCE_TRIGGERS == source)
	{
		char			err[256];
		zbx_dc_um_handle_t	*um_handle;

		um_handle = zbx_dc_open_user_macros();

		if (TRIGGER_VALUE_PROBLEM == value)
			event->severity = trigger_priority;

		event->trigger.triggerid = objectid;
		event->trigger.description = zbx_strdup(NULL, trigger_description);
		event->trigger.expression = zbx_strdup(NULL, trigger_expression);
		event->trigger.recovery_expression = zbx_strdup(NULL, trigger_recovery_expression);
		event->trigger.priority = trigger_priority;
		event->trigger.type = trigger_type;
		event->trigger.correlation_mode = trigger_correlation_mode;
		event->trigger.correlation_tag = zbx_strdup(NULL, trigger_correlation_tag);
		event->trigger.value = trigger_value;
		event->trigger.opdata = zbx_strdup(NULL, trigger_opdata);
		event->trigger.event_name = (NULL != event_name ? zbx_strdup(NULL, event_name) : NULL);
		event->name = zbx_strdup(NULL, (NULL != event_name ? event_name : trigger_description));
		event->trigger.cache = NULL;
		event->trigger.url = NULL;
		event->trigger.comments = NULL;

		zbx_substitute_simple_macros(NULL, event, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
				&event->trigger.correlation_tag, MACRO_TYPE_TRIGGER_TAG, err, sizeof(err));

		zbx_substitute_simple_macros(NULL, event, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
				&event->name, MACRO_TYPE_EVENT_NAME, err, sizeof(err));

		zbx_vector_ptr_create(&event->tags);

		if (NULL != trigger_tags)
		{
			for (i = 0; i < trigger_tags->values_num; i++)
				process_trigger_tag(event, (const zbx_tag_t *)trigger_tags->values[i]);
		}

		zbx_vector_ptr_create(&item_tags);
		get_item_tags_by_expression(&event->trigger, &item_tags);

		for (i = 0; i < item_tags.values_num; i++)
		{
			process_item_tag(event, (const zbx_item_tag_t *)item_tags.values[i]);
			zbx_free_item_tag(item_tags.values[i]);
		}

		zbx_vector_ptr_destroy(&item_tags);

		zbx_dc_close_user_macros(um_handle);
	}
	else if (EVENT_SOURCE_INTERNAL == source)
	{
		zbx_dc_um_handle_t	*um_handle;

		um_handle = zbx_dc_open_user_macros();

		if (NULL != error)
			event->name = zbx_strdup(NULL, error);

		zbx_vector_ptr_create(&event->tags);
		zbx_vector_ptr_create(&item_tags);

		switch (object)
		{
			case EVENT_OBJECT_TRIGGER:
				memset(&event->trigger, 0, sizeof(ZBX_DB_TRIGGER));

				event->trigger.expression = zbx_strdup(NULL, trigger_expression);
				event->trigger.recovery_expression = zbx_strdup(NULL, trigger_recovery_expression);

				for (i = 0; i < trigger_tags->values_num; i++)
					process_trigger_tag(event, (const zbx_tag_t *)trigger_tags->values[i]);

				get_item_tags_by_expression(&event->trigger, &item_tags);
				break;
			case EVENT_OBJECT_ITEM:
				zbx_dc_get_item_tags(objectid, &item_tags);
		}

		for (i = 0; i < item_tags.values_num; i++)
		{
			process_item_tag(event, (const zbx_item_tag_t *)item_tags.values[i]);
			zbx_free_item_tag(item_tags.values[i]);
		}

		zbx_vector_ptr_destroy(&item_tags);

		zbx_dc_close_user_macros(um_handle);
	}

	zbx_vector_ptr_append(&events, event);

	return event;
}

/******************************************************************************
 *                                                                            *
 * Purpose: add closing OK event for the specified problem event to an array  *
 *                                                                            *
 * Parameters: eventid  - [IN] the problem eventid                            *
 *             objectid - [IN] trigger, item ... identifier from database,    *
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
 *             trigger_opdata              - [IN] trigger operational data    *
 *             event_name                  - [IN] event name                  *
 *                                                                            *
 * Return value: Recovery event, created to close the specified event.        *
 *                                                                            *
 ******************************************************************************/
static ZBX_DB_EVENT	*close_trigger_event(zbx_uint64_t eventid, zbx_uint64_t objectid, const zbx_timespec_t *ts,
		zbx_uint64_t userid, zbx_uint64_t correlationid, zbx_uint64_t c_eventid,
		const char *trigger_description, const char *trigger_expression,
		const char *trigger_recovery_expression, unsigned char trigger_priority, unsigned char trigger_type,
		const char *trigger_opdata, const char *event_name)
{
	zbx_event_recovery_t	recovery_local;
	ZBX_DB_EVENT		*r_event;

	r_event = zbx_add_event(EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER, objectid, ts, TRIGGER_VALUE_OK,
			trigger_description, trigger_expression, trigger_recovery_expression, trigger_priority,
			trigger_type, NULL, ZBX_TRIGGER_CORRELATION_NONE, "", TRIGGER_VALUE_PROBLEM, trigger_opdata,
			event_name, NULL);

	recovery_local.eventid = eventid;
	recovery_local.objectid = objectid;
	recovery_local.correlationid = correlationid;
	recovery_local.c_eventid = c_eventid;
	recovery_local.r_event = r_event;
	recovery_local.userid = userid;

	zbx_hashset_insert(&event_recovery, &recovery_local, sizeof(recovery_local));

	return r_event;
}

/******************************************************************************
 *                                                                            *
 * Purpose: flushes the events into a database                                *
 *                                                                            *
 ******************************************************************************/
static int	save_events(void)
{
	int			i;
	zbx_db_insert_t		db_insert, db_insert_tags;
	int			j, num = 0, insert_tags = 0;
	zbx_uint64_t		eventid;
	ZBX_DB_EVENT		*event;

	for (i = 0; i < events.values_num; i++)
	{
		event = (ZBX_DB_EVENT *)events.values[i];

		if (0 != (event->flags & ZBX_FLAGS_DB_EVENT_CREATE) && 0 == event->eventid)
			num++;
	}

	zbx_db_insert_prepare(&db_insert, "events", "eventid", "source", "object", "objectid", "clock", "ns", "value",
			"name", "severity", NULL);

	eventid = DBget_maxid_num("events", num);

	num = 0;

	for (i = 0; i < events.values_num; i++)
	{
		event = (ZBX_DB_EVENT *)events.values[i];

		if (0 == (event->flags & ZBX_FLAGS_DB_EVENT_CREATE))
			continue;

		if (0 == event->eventid)
			event->eventid = eventid++;

		zbx_db_insert_add_values(&db_insert, event->eventid, event->source, event->object,
				event->objectid, event->clock, event->ns, event->value,
				ZBX_NULL2EMPTY_STR(event->name), event->severity);

		num++;

		if (EVENT_SOURCE_TRIGGERS != event->source && EVENT_SOURCE_INTERNAL != event->source)
			continue;

		if (0 == event->tags.values_num)
			continue;

		if (0 == insert_tags)
		{
			zbx_db_insert_prepare(&db_insert_tags, "event_tag", "eventtagid", "eventid", "tag", "value",
					NULL);
			insert_tags = 1;
		}

		for (j = 0; j < event->tags.values_num; j++)
		{
			zbx_tag_t	*tag = (zbx_tag_t *)event->tags.values[j];

			zbx_db_insert_add_values(&db_insert_tags, __UINT64_C(0), event->eventid, tag->tag, tag->value);
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
 * Purpose: generates problems from problem events (trigger and internal      *
 *          event sources)                                                    *
 *                                                                            *
 ******************************************************************************/
static void	save_problems(void)
{
	int			i;
	zbx_vector_ptr_t	problems;
	int			j, tags_num = 0;

	zbx_vector_ptr_create(&problems);

	for (i = 0; i < events.values_num; i++)
	{
		ZBX_DB_EVENT	*event = events.values[i];

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

					tags_num += event->tags.values_num;
					break;
				case EVENT_OBJECT_ITEM:
					if (ITEM_STATE_NOTSUPPORTED != event->value)
						continue;

					tags_num += event->tags.values_num;
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
				"name", "severity", NULL);

		for (j = 0; j < problems.values_num; j++)
		{
			const ZBX_DB_EVENT	*event = (const ZBX_DB_EVENT *)problems.values[j];

			zbx_db_insert_add_values(&db_insert, event->eventid, event->source, event->object,
					event->objectid, event->clock, event->ns, ZBX_NULL2EMPTY_STR(event->name),
					event->severity);
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
				const ZBX_DB_EVENT	*event = (const ZBX_DB_EVENT *)problems.values[j];

				if (EVENT_SOURCE_TRIGGERS != event->source && EVENT_SOURCE_INTERNAL != event->source)
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

	if (0 == event_recovery.num_data)
		return;

	zbx_DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	zbx_db_insert_prepare(&db_insert, "event_recovery", "eventid", "r_eventid", "correlationid", "c_eventid",
			"userid", NULL);

	zbx_hashset_iter_reset(&event_recovery, &iter);
	while (NULL != (recovery = (zbx_event_recovery_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_db_insert_add_values(&db_insert, recovery->eventid, recovery->r_event->eventid,
				recovery->correlationid, recovery->c_eventid, recovery->userid);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"update problem set"
			" r_eventid=" ZBX_FS_UI64
			",r_clock=%d"
			",r_ns=%d"
			",userid=" ZBX_FS_UI64,
			recovery->r_event->eventid,
			recovery->r_event->clock,
			recovery->r_event->ns,
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

	zbx_DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset)	/* in ORACLE always present begin..end; */
		DBexecute("%s", sql);

	zbx_free(sql);
}

/******************************************************************************
 *                                                                            *
 * Purpose: find event index by its source object                             *
 *                                                                            *
 * Parameters: source   - [IN] the event source                               *
 *             object   - [IN] the object type                                *
 *             objectid - [IN] the object id                                  *
 *                                                                            *
 * Return value: the event or NULL                                            *
 *                                                                            *
 ******************************************************************************/
static ZBX_DB_EVENT	*get_event_by_source_object_id(int source, int object, zbx_uint64_t objectid)
{
	int		i;
	ZBX_DB_EVENT	*event;

	for (i = 0; i < events.values_num; i++)
	{
		event = (ZBX_DB_EVENT *)events.values[i];

		if (event->source == source && event->object == object && event->objectid == objectid)
			return event;
	}

	return NULL;
}

/******************************************************************************
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
static int	correlation_match_event_hostgroup(const ZBX_DB_EVENT *event, zbx_uint64_t groupid)
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
				" from hstgrp g,hosts_groups hg,items i,functions f"
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
 * Purpose: checks if the correlation condition matches the new event         *
 *                                                                            *
 * Parameters: condition - [IN] the correlation condition to check            *
 *             event     - [IN] the new event to match                        *
 *             old_value - [IN] SUCCEED - the old event conditions may        *
 *                                        match event                         *
 *                              FAIL    - the old event conditions never      *
 *                                        match event                         *
 *                                                                            *
 * Return value: "1"            - the correlation rule match event            *
 *               "0"            - the correlation rule doesn't match event    *
 *               "ZBX_UNKNOWN0" - the correlation rule might match            *
 *                                depending on old events                     *
 *                                                                            *
 ******************************************************************************/
static const char	*correlation_condition_match_new_event(zbx_corr_condition_t *condition,
		const ZBX_DB_EVENT *event, int old_value)
{
	int		i, ret;
	zbx_tag_t	*tag;

	/* return SUCCEED for conditions using old events */
	switch (condition->type)
	{
		case ZBX_CORR_CONDITION_OLD_EVENT_TAG:
		case ZBX_CORR_CONDITION_OLD_EVENT_TAG_VALUE:
			return (SUCCEED == old_value) ? ZBX_UNKNOWN_STR "0" : "0";
	}

	switch (condition->type)
	{
		case ZBX_CORR_CONDITION_NEW_EVENT_TAG:
			for (i = 0; i < event->tags.values_num; i++)
			{
				tag = (zbx_tag_t *)event->tags.values[i];

				if (0 == strcmp(tag->tag, condition->data.tag.tag))
					return "1";
			}
			break;

		case ZBX_CORR_CONDITION_NEW_EVENT_TAG_VALUE:
			for (i = 0; i < event->tags.values_num; i++)
			{
				zbx_corr_condition_tag_value_t	*cond = &condition->data.tag_value;

				tag = (zbx_tag_t *)event->tags.values[i];

				if (0 == strcmp(tag->tag, cond->tag) &&
					SUCCEED == zbx_strmatch_condition(tag->value, cond->value, cond->op))
				{
					return "1";
				}
			}
			break;

		case ZBX_CORR_CONDITION_NEW_EVENT_HOSTGROUP:
			ret =  correlation_match_event_hostgroup(event, condition->data.group.groupid);

			if (CONDITION_OPERATOR_NOT_EQUAL == condition->data.group.op)
				return (SUCCEED == ret ? "0" : "1");

			return (SUCCEED == ret ? "1" : "0");

		case ZBX_CORR_CONDITION_EVENT_TAG_PAIR:
			for (i = 0; i < event->tags.values_num; i++)
			{
				tag = (zbx_tag_t *)event->tags.values[i];

				if (0 == strcmp(tag->tag, condition->data.tag_pair.newtag))
					return (SUCCEED == old_value) ? ZBX_UNKNOWN_STR "0" : "0";
			}
			break;
	}

	return "0";
}

/******************************************************************************
 *                                                                            *
 * Purpose: checks if the correlation rule might match the new event          *
 *                                                                            *
 * Parameters: correlation - [IN] the correlation rule to check               *
 *             event       - [IN] the new event to match                      *
 *             old_value   - [IN] SUCCEED - the old event conditions may      *
 *                                          match event                       *
 *                                FAIL    - the old event conditions never    *
 *                                          match event                       *
 *                                                                            *
 * Return value: CORRELATION_MATCH     - the correlation rule match           *
 *               CORRELATION_MAY_MATCH - the correlation rule might match     *
 *                                       depending on old events              *
 *               CORRELATION_NO_MATCH  - the correlation rule doesn't match   *
 *                                                                            *
 ******************************************************************************/
static zbx_correlation_match_result_t	correlation_match_new_event(zbx_correlation_t *correlation,
		const ZBX_DB_EVENT *event, int old_value)
{
	char				*expression, error[256];
	const char			*value;
	zbx_token_t			token;
	int				pos = 0;
	zbx_uint64_t			conditionid;
	zbx_strloc_t			*loc;
	zbx_corr_condition_t		*condition;
	double				result;
	zbx_correlation_match_result_t	ret = CORRELATION_NO_MATCH;

	if ('\0' == *correlation->formula)
		return CORRELATION_MAY_MATCH;

	expression = zbx_strdup(NULL, correlation->formula);

	for (; SUCCEED == zbx_token_find(expression, pos, &token, ZBX_TOKEN_SEARCH_BASIC); pos++)
	{
		if (ZBX_TOKEN_OBJECTID != token.type)
			continue;

		loc = &token.data.objectid.name;

		if (SUCCEED != is_uint64_n(expression + loc->l, loc->r - loc->l + 1, &conditionid))
			continue;

		if (NULL == (condition = (zbx_corr_condition_t *)zbx_hashset_search(&correlation_rules.conditions,
				&conditionid)))
			goto out;

		value = correlation_condition_match_new_event(condition, event, old_value);

		zbx_replace_string(&expression, token.loc.l, &token.loc.r, value);
		pos = token.loc.r;
	}

	if (SUCCEED == zbx_evaluate_unknown(expression, &result, error, sizeof(error)))
	{
		if (result == ZBX_UNKNOWN)
			ret = CORRELATION_MAY_MATCH;
		else if (SUCCEED == zbx_double_compare(result, 1))
			ret = CORRELATION_MATCH;
	}

out:
	zbx_free(expression);

	return ret;
}

/******************************************************************************
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
 * Purpose: creates sql filter to find events matching a correlation          *
 *          condition                                                         *
 *                                                                            *
 * Parameters: condition - [IN] the correlation condition to match            *
 *             event     - [IN] the new event to match                        *
 *                                                                            *
 * Return value: the created filter or NULL                                   *
 *                                                                            *
 ******************************************************************************/
static char	*correlation_condition_get_event_filter(zbx_corr_condition_t *condition, const ZBX_DB_EVENT *event)
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
			return zbx_dsprintf(NULL, "%s=1",
					correlation_condition_match_new_event(condition, event, SUCCEED));
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
					zbx_vector_str_append(&values, zbx_strdup(NULL, tag->value));
			}

			if (0 == values.values_num)
			{
				/* no new tag found, substitute condition with failure expression */
				filter = zbx_strdup(NULL, "1=0");
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
				zbx_vector_str_clear_ext(&values, zbx_str_free);
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
		zbx_correlation_t *correlation, const ZBX_DB_EVENT *event)
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

		zbx_replace_string(&expression, token.loc.l, &token.loc.r, filter);
		pos = token.loc.r;
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
 * Purpose: execute correlation operations for the new event and matched      *
 *          old eventid                                                       *
 *                                                                            *
 * Parameters: correlation  - [IN] the correlation to execute                 *
 *             event        - [IN] the new event                              *
 *             old_eventid  - [IN] the old eventid                            *
 *             old_objectid - [IN] the old event source objectid (triggerid)  *
 *                                                                            *
 ******************************************************************************/
static void	correlation_execute_operations(zbx_correlation_t *correlation, ZBX_DB_EVENT *event,
		zbx_uint64_t old_eventid, zbx_uint64_t old_objectid)
{
	int			i;
	zbx_corr_operation_t	*operation;
	zbx_event_recovery_t	recovery_local;
	zbx_timespec_t		ts;
	ZBX_DB_EVENT		*r_event;

	for (i = 0; i < correlation->operations.values_num; i++)
	{
		operation = (zbx_corr_operation_t *)correlation->operations.values[i];

		switch (operation->type)
		{
			case ZBX_CORR_OPERATION_CLOSE_NEW:
				/* generate OK event to close the new event */

				/* check if this event has not been closed by another correlation rule */
				if (NULL != zbx_hashset_search(&event_recovery, &event->eventid))
					break;

				ts.sec = event->clock;
				ts.ns = event->ns;

				r_event = close_trigger_event(event->eventid, event->objectid, &ts, 0,
						correlation->correlationid, event->eventid, event->trigger.description,
						event->trigger.expression, event->trigger.recovery_expression,
						event->trigger.priority, event->trigger.type, event->trigger.opdata,
						event->trigger.event_name);

				event->flags |= ZBX_FLAGS_DB_EVENT_NO_ACTION;
				r_event->flags |= ZBX_FLAGS_DB_EVENT_NO_ACTION;

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

/* specifies correlation execution scope */
typedef enum
{
	ZBX_CHECK_NEW_EVENTS,
	ZBX_CHECK_OLD_EVENTS
}
zbx_correlation_scope_t;

/* flag to cache state of problem table during event correlation */
typedef enum
{
	/* unknown state, not initialized */
	ZBX_PROBLEM_STATE_UNKNOWN = -1,
	/* all problems are resolved */
	ZBX_PROBLEM_STATE_RESOLVED,
	/* at least one open problem exists */
	ZBX_PROBLEM_STATE_OPEN
}
zbx_problem_state_t;

/******************************************************************************
 *                                                                            *
 * Purpose: find problem events that must be recovered by global correlation  *
 *          rules and check if the new event must be closed                   *
 *                                                                            *
 * Parameters: event         - [IN] the new event                             *
 *             problem_state - [IN/OUT] problem state cache variable          *
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
static void	correlate_event_by_global_rules(ZBX_DB_EVENT *event, zbx_problem_state_t *problem_state)
{
	int			i;
	zbx_correlation_t	*correlation;
	zbx_vector_ptr_t	corr_old, corr_new;
	char			*sql = NULL;
	const char		*delim = "";
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_uint64_t		eventid, correlationid, objectid;

	zbx_vector_ptr_create(&corr_old);
	zbx_vector_ptr_create(&corr_new);

	for (i = 0; i < correlation_rules.correlations.values_num; i++)
	{
		zbx_correlation_scope_t	scope;

		correlation = (zbx_correlation_t *)correlation_rules.correlations.values[i];

		switch (correlation_match_new_event(correlation, event, SUCCEED))
		{
			case CORRELATION_MATCH:
				if (SUCCEED == correlation_has_old_event_operation(correlation))
					scope = ZBX_CHECK_OLD_EVENTS;
				else
					scope = ZBX_CHECK_NEW_EVENTS;
				break;
			case CORRELATION_NO_MATCH:	/* proceed with next rule */
				continue;
			case CORRELATION_MAY_MATCH:	/* might match depending on old events */
				scope = ZBX_CHECK_OLD_EVENTS;
				break;
		}

		if (ZBX_CHECK_OLD_EVENTS == scope)
		{
			if (ZBX_PROBLEM_STATE_UNKNOWN == *problem_state)
			{
				DB_RESULT	result;

				result = DBselectN("select eventid from problem"
						" where r_eventid is null and source="
						ZBX_STR(EVENT_SOURCE_TRIGGERS), 1);

				if (NULL == DBfetch(result))
					*problem_state = ZBX_PROBLEM_STATE_RESOLVED;
				else
					*problem_state = ZBX_PROBLEM_STATE_OPEN;
				DBfree_result(result);
			}

			if (ZBX_PROBLEM_STATE_RESOLVED == *problem_state)
			{
				/* with no open problems all conditions involving old events will fail       */
				/* so there is no need to check old events. Instead re-check if correlation  */
				/* still matches the new event and must be processed in new event scope.     */
				if (CORRELATION_MATCH == correlation_match_new_event(correlation, event, FAIL))
					zbx_vector_ptr_append(&corr_new, correlation);
			}
			else
				zbx_vector_ptr_append(&corr_old, correlation);
		}
		else
			zbx_vector_ptr_append(&corr_new, correlation);
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
		DB_RESULT	result;
		DB_ROW		row;

		/* Process correlations that matches new event and either uses old events in conditions */
		/* or has operations involving old events.                                              */

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select p.eventid,p.objectid,c.correlationid"
								" from correlation c,problem p"
								" where p.r_eventid is null"
								" and p.source=" ZBX_STR(EVENT_SOURCE_TRIGGERS)
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
 * Purpose: add events to the closing queue according to global correlation   *
 *          rules                                                             *
 *                                                                            *
 ******************************************************************************/
static void	correlate_events_by_global_rules(zbx_vector_ptr_t *trigger_events, zbx_vector_ptr_t *trigger_diff)
{
	int			i, index;
	zbx_trigger_diff_t	*diff;
	zbx_problem_state_t	problem_state = ZBX_PROBLEM_STATE_UNKNOWN;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() events:%d", __func__, correlation_cache.num_data);

	zbx_dc_correlation_rules_get(&correlation_rules);

	if (0 == correlation_rules.correlations.values_num)
		goto out;

	/* process global correlation and queue the events that must be closed */
	for (i = 0; i < trigger_events->values_num; i++)
	{
		ZBX_DB_EVENT	*event = (ZBX_DB_EVENT *)trigger_events->values[i];

		if (0 == (ZBX_FLAGS_DB_EVENT_CREATE & event->flags))
			continue;

		correlate_event_by_global_rules(event, &problem_state);

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

out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: try flushing correlation close events queue, generated by         *
 *          correlation rules                                                 *
 *                                                                            *
 ******************************************************************************/
static void	flush_correlation_queue(zbx_vector_ptr_t *trigger_diff, zbx_vector_uint64_t *triggerids_lock)
{
	zbx_vector_uint64_t	triggerids, lockids, eventids;
	zbx_hashset_iter_t	iter;
	zbx_event_recovery_t	*recovery;
	int			i, closed_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() events:%d", __func__, correlation_cache.num_data);

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
						trigger->expression, trigger->recovery_expression,
						trigger->priority, trigger->type, trigger->opdata, trigger->event_name);

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
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() closed:%d", __func__, closed_num);
}

/******************************************************************************
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
 * Purpose: update trigger value, problem count fields depending on problem   *
 *          and recovered events                                              *
 *                                                                            *
 ******************************************************************************/
static void	update_trigger_changes(zbx_vector_ptr_t *trigger_diff)
{
	int			i;
	int			index, j, new_value;
	zbx_trigger_diff_t	*diff;

	update_trigger_problem_count(trigger_diff);

	/* update trigger problem_count for new problem events */
	for (i = 0; i < events.values_num; i++)
	{
		ZBX_DB_EVENT	*event = (ZBX_DB_EVENT *)events.values[i];

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
 * Purpose: initializes the data structures required for event processing     *
 *                                                                            *
 ******************************************************************************/
void	zbx_initialize_events(void)
{
	zbx_vector_ptr_create(&events);
	zbx_hashset_create(&event_recovery, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_hashset_create(&correlation_cache, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_dc_correlation_rules_init(&correlation_rules);
}

/******************************************************************************
 *                                                                            *
 * Purpose: uninitializes the data structures required for event processing   *
 *                                                                            *
 ******************************************************************************/
void	zbx_uninitialize_events(void)
{
	zbx_vector_ptr_destroy(&events);
	zbx_hashset_destroy(&event_recovery);
	zbx_hashset_destroy(&correlation_cache);

	zbx_dc_correlation_rules_free(&correlation_rules);
}

/******************************************************************************
 *                                                                            *
 * Purpose: reset event_recovery data                                         *
 *                                                                            *
 ******************************************************************************/
void	zbx_reset_event_recovery(void)
{
	zbx_hashset_clear(&event_recovery);
}

/******************************************************************************
 *                                                                            *
 * Purpose: cleans single event                                               *
 *                                                                            *
 ******************************************************************************/
static void	zbx_clean_event(ZBX_DB_EVENT *event)
{
	zbx_free(event->name);

	if (EVENT_OBJECT_TRIGGER == event->object)
	{
		zbx_db_trigger_clean(&event->trigger);
		zbx_free(event->trigger.correlation_tag);
	}

	if (EVENT_SOURCE_TRIGGERS == event->source || EVENT_SOURCE_INTERNAL == event->source)
	{
		zbx_vector_ptr_clear_ext(&event->tags, (zbx_clean_func_t)zbx_free_tag);
		zbx_vector_ptr_destroy(&event->tags);
	}

	zbx_free(event);
}

/******************************************************************************
 *                                                                            *
 * Purpose: cleans all events and events recoveries                           *
 *                                                                            *
 ******************************************************************************/
void	zbx_clean_events(void)
{
	zbx_vector_ptr_clear_ext(&events, (zbx_clean_func_t)zbx_clean_event);

	zbx_reset_event_recovery();
}

/******************************************************************************
 *                                                                            *
 * Purpose:  get hosts that are associated with trigger expression/recovery   *
 *           expression                                                       *
 *                                                                            *
 ******************************************************************************/
static void	db_trigger_get_hosts(zbx_hashset_t *hosts, ZBX_DB_TRIGGER *trigger)
{
	zbx_vector_uint64_t	functionids;

	zbx_vector_uint64_create(&functionids);
	zbx_db_trigger_get_all_functionids(trigger, &functionids);
	DCget_hosts_by_functionids(&functionids, hosts);
	zbx_vector_uint64_destroy(&functionids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: export events                                                     *
 *                                                                            *
 ******************************************************************************/
void	zbx_export_events(void)
{
	int			i, j;
	struct zbx_json		json;
	size_t			sql_alloc = 256, sql_offset;
	char			*sql = NULL;
	DB_RESULT		result;
	DB_ROW			row;
	zbx_hashset_t		hosts;
	zbx_vector_uint64_t	hostids;
	zbx_hashset_iter_t	iter;
	zbx_event_recovery_t	*recovery;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() events:" ZBX_FS_SIZE_T, __func__, (zbx_fs_size_t)events.values_num);

	if (0 == events.values_num)
		goto exit;

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);
	sql = (char *)zbx_malloc(sql, sql_alloc);
	zbx_hashset_create(&hosts, events.values_num, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_create(&hostids);

	for (i = 0; i < events.values_num; i++)
	{
		DC_HOST		*host;
		ZBX_DB_EVENT	*event;

		event = (ZBX_DB_EVENT *)events.values[i];

		if (EVENT_SOURCE_TRIGGERS != event->source || 0 == (event->flags & ZBX_FLAGS_DB_EVENT_CREATE))
			continue;

		if (TRIGGER_VALUE_PROBLEM != event->value)
			continue;

		zbx_json_clean(&json);

		zbx_json_addint64(&json, ZBX_PROTO_TAG_CLOCK, event->clock);
		zbx_json_addint64(&json, ZBX_PROTO_TAG_NS, event->ns);
		zbx_json_addint64(&json, ZBX_PROTO_TAG_VALUE, event->value);
		zbx_json_adduint64(&json, ZBX_PROTO_TAG_EVENTID, event->eventid);
		zbx_json_addstring(&json, ZBX_PROTO_TAG_NAME, event->name, ZBX_JSON_TYPE_STRING);
		zbx_json_addint64(&json, ZBX_PROTO_TAG_SEVERITY, event->severity);

		db_trigger_get_hosts(&hosts, &event->trigger);

		zbx_json_addarray(&json, ZBX_PROTO_TAG_HOSTS);

		zbx_hashset_iter_reset(&hosts, &iter);

		while (NULL != (host = (DC_HOST *)zbx_hashset_iter_next(&iter)))
		{
			zbx_json_addobject(&json,NULL);
			zbx_json_addstring(&json, ZBX_PROTO_TAG_HOST, host->host, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json, ZBX_PROTO_TAG_NAME, host->name, ZBX_JSON_TYPE_STRING);
			zbx_json_close(&json);
			zbx_vector_uint64_append(&hostids, host->hostid);
		}

		zbx_json_close(&json);

		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"select distinct g.name"
					" from hstgrp g, hosts_groups hg"
					" where g.groupid=hg.groupid"
						" and");

		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "hg.hostid", hostids.values,
				hostids.values_num);

		result = DBselect("%s", sql);

		zbx_json_addarray(&json, ZBX_PROTO_TAG_GROUPS);

		while (NULL != (row = DBfetch(result)))
			zbx_json_addstring(&json, NULL, row[0], ZBX_JSON_TYPE_STRING);
		DBfree_result(result);

		zbx_json_close(&json);

		zbx_json_addarray(&json, ZBX_PROTO_TAG_TAGS);
		for (j = 0; j < event->tags.values_num; j++)
		{
			zbx_tag_t	*tag = (zbx_tag_t *)event->tags.values[j];

			zbx_json_addobject(&json, NULL);
			zbx_json_addstring(&json, ZBX_PROTO_TAG_TAG, tag->tag, ZBX_JSON_TYPE_STRING);
			zbx_json_addstring(&json, ZBX_PROTO_TAG_VALUE, tag->value, ZBX_JSON_TYPE_STRING);
			zbx_json_close(&json);
		}

		zbx_hashset_clear(&hosts);
		zbx_vector_uint64_clear(&hostids);

		zbx_problems_export_write(json.buffer, json.buffer_size);
	}

	zbx_hashset_iter_reset(&event_recovery, &iter);
	while (NULL != (recovery = (zbx_event_recovery_t *)zbx_hashset_iter_next(&iter)))
	{
		if (EVENT_SOURCE_TRIGGERS != recovery->r_event->source)
			continue;

		zbx_json_clean(&json);

		zbx_json_addint64(&json, ZBX_PROTO_TAG_CLOCK, recovery->r_event->clock);
		zbx_json_addint64(&json, ZBX_PROTO_TAG_NS, recovery->r_event->ns);
		zbx_json_addint64(&json, ZBX_PROTO_TAG_VALUE, recovery->r_event->value);
		zbx_json_adduint64(&json, ZBX_PROTO_TAG_EVENTID, recovery->r_event->eventid);
		zbx_json_adduint64(&json, ZBX_PROTO_TAG_PROBLEM_EVENTID, recovery->eventid);

		zbx_problems_export_write(json.buffer, json.buffer_size);
	}

	zbx_problems_export_flush();

	zbx_hashset_destroy(&hosts);
	zbx_vector_uint64_destroy(&hostids);
	zbx_free(sql);
	zbx_json_free(&json);
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

void	zbx_events_update_itservices(void)
{
	unsigned char		*data = NULL;
	size_t			data_alloc = 0, data_offset = 0;
	int			i;
	zbx_hashset_iter_t	iter;
	zbx_event_recovery_t	*recovery;

	zbx_hashset_iter_reset(&event_recovery, &iter);
	while (NULL != (recovery = (zbx_event_recovery_t *)zbx_hashset_iter_next(&iter)))
	{
		int	values_num;

		if (EVENT_SOURCE_TRIGGERS != recovery->r_event->source)
			continue;

		values_num = recovery->r_event->tags.values_num;
		recovery->r_event->tags.values_num = 0;

		zbx_service_serialize(&data, &data_alloc, &data_offset, recovery->eventid, recovery->r_event->clock,
				recovery->r_event->ns, recovery->r_event->value, recovery->r_event->severity,
				&recovery->r_event->tags);

		recovery->r_event->tags.values_num = values_num;
	}

	for (i = 0; i < events.values_num; i++)
	{
		ZBX_DB_EVENT	*event = events.values[i];

		if (EVENT_SOURCE_TRIGGERS != event->source || 0 == (event->flags & ZBX_FLAGS_DB_EVENT_CREATE))
			continue;

		if (TRIGGER_VALUE_PROBLEM != event->value)
			continue;

		zbx_service_serialize(&data, &data_alloc, &data_offset, event->eventid, event->clock, event->ns,
				event->value, event->severity, &event->tags);
	}

	if (NULL == data)
		return;

	zbx_service_flush(ZBX_IPC_SERVICE_SERVICE_PROBLEMS, data, (zbx_uint32_t)data_offset);
	zbx_free(data);
}

/******************************************************************************
 *                                                                            *
 * Purpose: adds event suppress data for problem events matching active       *
 *          maintenance periods                                               *
 *                                                                            *
 ******************************************************************************/
static void	add_event_suppress_data(zbx_vector_ptr_t *event_refs, zbx_vector_uint64_t *maintenanceids)
{
	zbx_vector_ptr_t		event_queries;
	int				i, j;
	zbx_event_suppress_query_t	*query;

	/* prepare query data  */

	zbx_vector_ptr_create(&event_queries);

	for (i = 0; i < event_refs->values_num; i++)
	{
		ZBX_DB_EVENT	*event = (ZBX_DB_EVENT *)event_refs->values[i];

		query = (zbx_event_suppress_query_t *)zbx_malloc(NULL, sizeof(zbx_event_suppress_query_t));
		query->eventid = event->eventid;

		zbx_vector_uint64_create(&query->functionids);
		zbx_db_trigger_get_all_functionids(&event->trigger, &query->functionids);

		zbx_vector_ptr_create(&query->tags);
		if (0 != event->tags.values_num)
			zbx_vector_ptr_append_array(&query->tags, event->tags.values, event->tags.values_num);

		zbx_vector_uint64_pair_create(&query->maintenances);

		zbx_vector_ptr_append(&event_queries, query);
	}

	if (0 != event_queries.values_num)
	{
		zbx_db_insert_t	db_insert;

		/* get maintenance data and save it in database */
		if (SUCCEED == zbx_dc_get_event_maintenances(&event_queries, maintenanceids) &&
				SUCCEED == zbx_db_lock_maintenanceids(maintenanceids))
		{
			zbx_db_insert_prepare(&db_insert, "event_suppress", "event_suppressid", "eventid",
					"maintenanceid", "suppress_until", NULL);

			for (j = 0; j < event_queries.values_num; j++)
			{
				query = (zbx_event_suppress_query_t *)event_queries.values[j];

				for (i = 0; i < query->maintenances.values_num; i++)
				{
					/* when locking maintenances not-locked (deleted) maintenance ids */
					/* are removed from the maintenanceids vector                   */
					if (FAIL == zbx_vector_uint64_bsearch(maintenanceids,
							query->maintenances.values[i].first,
							ZBX_DEFAULT_UINT64_COMPARE_FUNC))
					{
						continue;
					}

					zbx_db_insert_add_values(&db_insert, __UINT64_C(0), query->eventid,
							query->maintenances.values[i].first,
							(int)query->maintenances.values[i].second);

					((ZBX_DB_EVENT *)event_refs->values[j])->suppressed =
							ZBX_PROBLEM_SUPPRESSED_TRUE;
				}
			}

			zbx_db_insert_autoincrement(&db_insert, "event_suppressid");
			zbx_db_insert_execute(&db_insert);
			zbx_db_insert_clean(&db_insert);
		}

		for (j = 0; j < event_queries.values_num; j++)
		{
			query = (zbx_event_suppress_query_t *)event_queries.values[j];
			/* reset tags vector to avoid double freeing copied tag name/value pointers */
			zbx_vector_ptr_clear(&query->tags);
		}
		zbx_vector_ptr_clear_ext(&event_queries, (zbx_clean_func_t)zbx_event_suppress_query_free);
	}

	zbx_vector_ptr_destroy(&event_queries);
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieve running maintenances for each event and saves it in      *
 *          event_suppress table                                              *
 *                                                                            *
 ******************************************************************************/
static void	update_event_suppress_data(void)
{
	zbx_vector_ptr_t	event_refs;
	zbx_vector_uint64_t	maintenanceids;
	int			i;
	ZBX_DB_EVENT		*event;

	zbx_vector_uint64_create(&maintenanceids);
	zbx_vector_ptr_create(&event_refs);
	zbx_vector_ptr_reserve(&event_refs, events.values_num);

	/* prepare trigger problem event vector */
	for (i = 0; i < events.values_num; i++)
	{
		event = (ZBX_DB_EVENT *)events.values[i];

		if (0 == (event->flags & ZBX_FLAGS_DB_EVENT_CREATE))
			continue;

		if (EVENT_SOURCE_TRIGGERS != event->source)
			continue;

		if (TRIGGER_VALUE_PROBLEM == event->value)
			zbx_vector_ptr_append(&event_refs, event);
	}

	if (0 == event_refs.values_num)
		goto out;

	if (SUCCEED != zbx_dc_get_running_maintenanceids(&maintenanceids))
		goto out;

	if (0 != event_refs.values_num)
		add_event_suppress_data(&event_refs, &maintenanceids);
out:
	zbx_vector_ptr_destroy(&event_refs);
	zbx_vector_uint64_destroy(&maintenanceids);
}

/******************************************************************************
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
	update_event_suppress_data();

	zbx_vector_uint64_pair_create(&closed_events);

	zbx_hashset_iter_reset(&event_recovery, &iter);
	while (NULL != (recovery = (zbx_event_recovery_t *)zbx_hashset_iter_next(&iter)))
	{
		zbx_uint64_pair_t	pair = {recovery->eventid, recovery->r_event->eventid};

		zbx_vector_uint64_pair_append_ptr(&closed_events, &pair);
	}

	zbx_vector_uint64_pair_sort(&closed_events, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	process_actions(&events, &closed_events);
	zbx_vector_uint64_pair_destroy(&closed_events);

	return ret;
}

/******************************************************************************
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
	ZBX_DB_EVENT		*event;
	zbx_event_recovery_t	recovery_local;

	if (NULL == (event = get_event_by_source_object_id(source, object, objectid)))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return;
	}

	if (EVENT_SOURCE_INTERNAL == source)
		event->flags |= ZBX_FLAGS_DB_EVENT_RECOVER;

	recovery_local.eventid = eventid;

	if (NULL != zbx_hashset_search(&event_recovery, &recovery_local))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return;
	}

	recovery_local.objectid = objectid;
	recovery_local.r_event = event;
	recovery_local.correlationid = 0;
	recovery_local.c_eventid = 0;
	recovery_local.userid = 0;
	zbx_hashset_insert(&event_recovery, &recovery_local, sizeof(recovery_local));
}

/******************************************************************************
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
	ZBX_DB_EVENT		*event;

	zbx_vector_uint64_create(&triggerids);
	zbx_vector_uint64_create(&itemids);
	zbx_vector_uint64_create(&lldruleids);

	for (i = 0; i < ok_events->values_num; i++)
	{
		event = (ZBX_DB_EVENT *)ok_events->values[i];

		if (ZBX_FLAGS_DB_EVENT_UNSET == event->flags)
			continue;

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

	if (0 == triggerids.values_num && 0 == itemids.values_num && 0 == lldruleids.values_num)
		goto out;

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

out:
	zbx_vector_uint64_destroy(&lldruleids);
	zbx_vector_uint64_destroy(&itemids);
	zbx_vector_uint64_destroy(&triggerids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: do not generate unnecessary internal events if there are no       *
 *          internal actions and no problem recovery from when actions were   *
 *          enabled                                                           *
 *                                                                            *
 * Parameters: internal_problem_events - [IN/OUT] problem events to process   *
 * Parameters: internal_ok_events      - [IN/OUT] recovery events to process  *
 *                                                                            *
 ******************************************************************************/
static void	process_internal_events_without_actions(zbx_vector_ptr_t *internal_problem_events,
		zbx_vector_ptr_t *internal_ok_events)
{
	ZBX_DB_EVENT	*event;
	int		i;

	if (0 != DCget_internal_action_count())
		return;

	for (i = 0; i < internal_problem_events->values_num; i++)
		((ZBX_DB_EVENT *)internal_problem_events->values[i])->flags = ZBX_FLAGS_DB_EVENT_UNSET;

	for (i = 0; i < internal_ok_events->values_num; i++)
	{
		event = (ZBX_DB_EVENT *)internal_ok_events->values[i];

		if (0 == (event->flags & ZBX_FLAGS_DB_EVENT_RECOVER))
			event->flags = ZBX_FLAGS_DB_EVENT_UNSET;
	}
}

/******************************************************************************
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
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select eventid,tag,value from problem_tag where");
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
 * Purpose: check event dependency based on cached and actual trigger values  *
 *                                                                            *
 * Parameters: event        - [IN] the event to check                         *
 *             deps         - [IN] the trigger dependency data (sorted by     *
 *                                 triggerid)                                 *
 *             trigger_diff - [IN] the trigger changeset - source of actual   *
 *                                 trigger values (sorted by triggerid)       *
 *                                                                            *
 ******************************************************************************/
static int	event_check_dependency(const ZBX_DB_EVENT *event, const zbx_vector_ptr_t *deps,
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
 * Purpose: checks if the two tag sets have matching tag                      *
 *                                                                            *
 * Parameters: name  - [IN] the name of tag to match                          *
 *             tags1 - [IN] the first tag vector                              *
 *             tags2 - [IN] the second tag vector                             *
 *                                                                            *
 * Return value: SUCCEED - both tag sets contains a tag with the specified    *
 *                         name and the same value                            *
 *               FAIL    - otherwise.                                         *
 *                                                                            *
 ******************************************************************************/
static int	match_tag(const char *name, const zbx_vector_ptr_t *tags1, const zbx_vector_ptr_t *tags2)
{
	int		i, j;
	zbx_tag_t	*tag1, *tag2;

	for (i = 0; i < tags1->values_num; i++)
	{
		tag1 = (zbx_tag_t *)tags1->values[i];

		if (0 != strcmp(tag1->tag, name))
			continue;

		for (j = 0; j < tags2->values_num; j++)
		{
			tag2 = (zbx_tag_t *)tags2->values[j];

			if (0 == strcmp(tag2->tag, name) && 0 == strcmp(tag1->value, tag2->value))
				return SUCCEED;
		}
	}

	return FAIL;
}

/******************************************************************************
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
	ZBX_DB_EVENT		*event;
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
		event = (ZBX_DB_EVENT *)trigger_events->values[i];

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
		event = (ZBX_DB_EVENT *)trigger_events->values[i];
		zbx_vector_uint64_append(&triggerids, event->objectid);
	}

	zbx_vector_uint64_sort(&triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_dc_get_trigger_dependencies(&triggerids, &deps);

	/* process trigger events */

	for (i = 0; i < trigger_events->values_num; i++)
	{
		event = (ZBX_DB_EVENT *)trigger_events->values[i];

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
			/* With trigger correlation enabled the recovery event recovers    */
			/* all problem events generated by the same trigger and matching   */
			/* recovery event tags. The trigger value is set to OK only if all */
			/* problem events were recovered.                                  */

			value = TRIGGER_VALUE_OK;
			event->flags = ZBX_FLAGS_DB_EVENT_UNSET;

			for (j = 0; j < problems.values_num; j++)
			{
				problem = (zbx_event_problem_t *)problems.values[j];

				if (problem->triggerid == event->objectid)
				{
					if (SUCCEED == match_tag(event->trigger.correlation_tag,
							&problem->tags, &event->tags))
					{
						recover_event(problem->eventid, EVENT_SOURCE_TRIGGERS,
								EVENT_OBJECT_TRIGGER, event->objectid);
						event->flags = ZBX_FLAGS_DB_EVENT_CREATE;
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
 * Purpose: process internal trigger events                                   *
 *          to avoid trigger dependency                                       *
 *                                                                            *
 * Parameters: internal_events - [IN] the internal events to process          *
 *             trigger_events  - [IN] the trigger events used for dependency  *
 *             trigger_diff   -  [IN] the trigger changeset                   *
 *                                                                            *
 ******************************************************************************/
static void	process_internal_events_dependency(zbx_vector_ptr_t *internal_events, zbx_vector_ptr_t *trigger_events,
		zbx_vector_ptr_t *trigger_diff)
{
	int			i, index;
	ZBX_DB_EVENT		*event;
	zbx_vector_uint64_t	triggerids;
	zbx_vector_ptr_t	deps;
	zbx_trigger_diff_t	*diff;

	zbx_vector_uint64_create(&triggerids);
	zbx_vector_uint64_reserve(&triggerids, internal_events->values_num + trigger_events->values_num);

	zbx_vector_ptr_create(&deps);
	zbx_vector_ptr_reserve(&deps, internal_events->values_num + trigger_events->values_num);

	for (i = 0; i < internal_events->values_num; i++)
	{
		event = (ZBX_DB_EVENT *)internal_events->values[i];
		zbx_vector_uint64_append(&triggerids, event->objectid);
	}

	for (i = 0; i < trigger_events->values_num; i++)
	{
		event = (ZBX_DB_EVENT *)trigger_events->values[i];
		zbx_vector_uint64_append(&triggerids, event->objectid);
	}

	zbx_vector_uint64_sort(&triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(&triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_dc_get_trigger_dependencies(&triggerids, &deps);

	for (i = 0; i < internal_events->values_num; i++)
	{
		event = (ZBX_DB_EVENT *)internal_events->values[i];

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
	}

	zbx_vector_ptr_clear_ext(&deps, (zbx_clean_func_t)trigger_dep_free);
	zbx_vector_ptr_destroy(&deps);

	zbx_vector_uint64_destroy(&triggerids);
}

/******************************************************************************
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
	int			i, processed_num = 0;
	zbx_uint64_t		eventid;
	zbx_vector_ptr_t	internal_problem_events, internal_ok_events, trigger_events, internal_events;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() events_num:" ZBX_FS_SIZE_T, __func__, (zbx_fs_size_t)events.values_num);

	if (NULL != trigger_diff && 0 != correlation_cache.num_data)
		flush_correlation_queue(trigger_diff, triggerids_lock);

	if (0 != events.values_num)
	{
		zbx_vector_ptr_create(&internal_problem_events);
		zbx_vector_ptr_reserve(&internal_problem_events, events.values_num);
		zbx_vector_ptr_create(&internal_ok_events);
		zbx_vector_ptr_reserve(&internal_ok_events, events.values_num);

		zbx_vector_ptr_create(&trigger_events);
		zbx_vector_ptr_reserve(&trigger_events, events.values_num);

		zbx_vector_ptr_create(&internal_events);
		zbx_vector_ptr_reserve(&internal_events, events.values_num);

		/* assign event identifiers - they are required to set correlation event ids */
		eventid = DBget_maxid_num("events", events.values_num);
		for (i = 0; i < events.values_num; i++)
		{
			ZBX_DB_EVENT	*event = (ZBX_DB_EVENT *)events.values[i];

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
						else
							zbx_vector_ptr_append(&internal_problem_events, event);
						zbx_vector_ptr_append(&internal_events, event);
						break;
					case EVENT_OBJECT_ITEM:
						if (ITEM_STATE_NORMAL == event->value)
							zbx_vector_ptr_append(&internal_ok_events, event);
						else
							zbx_vector_ptr_append(&internal_problem_events, event);
						break;
					case EVENT_OBJECT_LLDRULE:
						if (ITEM_STATE_NORMAL == event->value)
							zbx_vector_ptr_append(&internal_ok_events, event);
						else
							zbx_vector_ptr_append(&internal_problem_events, event);
						break;
				}
			}
		}

		if (0 != internal_events.values_num)
			process_internal_events_dependency(&internal_events, &trigger_events, trigger_diff);

		if (0 != internal_ok_events.values_num)
			process_internal_ok_events(&internal_ok_events);

		if (0 != internal_problem_events.values_num || 0 != internal_ok_events.values_num)
			process_internal_events_without_actions(&internal_problem_events, &internal_ok_events);

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
		zbx_vector_ptr_destroy(&internal_problem_events);
		zbx_vector_ptr_destroy(&internal_events);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() processed:%d", __func__, (int)processed_num);

	return processed_num;
}

/******************************************************************************
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
	int		errcode, processed_num = 0;
	zbx_timespec_t	ts;
	ZBX_DB_EVENT	*r_event;

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

		r_event = close_trigger_event(eventid, triggerid, &ts, userid, 0, 0, trigger.description,
				trigger.expression, trigger.recovery_expression, trigger.priority,
				trigger.type, trigger.opdata, trigger.event_name);

		r_event->eventid = DBget_maxid_num("events", 1);

		processed_num = flush_events();
		update_trigger_changes(&trigger_diff);
		zbx_db_save_trigger_changes(&trigger_diff);

		if (ZBX_DB_OK == DBcommit())
		{
			DCconfig_triggers_apply_changes(&trigger_diff);

			if (SUCCEED == zbx_is_export_enabled(ZBX_FLAG_EXPTYPE_EVENTS))
				zbx_export_events();

			zbx_events_update_itservices();
		}

		zbx_clean_events();
		zbx_vector_ptr_clear_ext(&trigger_diff, (zbx_clean_func_t)zbx_trigger_diff_free);
		zbx_vector_ptr_destroy(&trigger_diff);
	}

	DCconfig_clean_triggers(&trigger, &errcode, 1);

	return (0 == processed_num ? FAIL : SUCCEED);
}
