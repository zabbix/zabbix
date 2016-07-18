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

#include "actions.h"
#include "events.h"
#include "zbxserver.h"

typedef struct
{
	zbx_uint64_t	eventid;
	zbx_uint64_t	triggerid;
	zbx_timespec_t	ts;
}
zbx_event_correlation_t;

static DB_EVENT	*events = NULL;
static size_t	events_alloc = 0, events_num = 0;
static zbx_hashset_t	event_recovery;

/******************************************************************************
 *                                                                            *
 * Function: validate_event_tag                                               *
 *                                                                            *
 ******************************************************************************/
static int	validate_event_tag(const DB_EVENT* event, const zbx_tag_t *tag)
{
	int		i, whitespace = 1;
	const char	*ptr;

	/* check if the tag is valid - has characters other than whitespace */
	/* and doesn't contain '/' character                                */
	for (ptr = tag->tag; '\0' != *ptr; ptr++)
	{
		if ('/' == *ptr)
			return FAIL;

		if (' ' != *ptr)
			whitespace = 0;
	}

	if (1 == whitespace)
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
 * Function: add_event                                                        *
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
 *                                                                            *
 ******************************************************************************/
DB_EVENT	*add_event(unsigned char source, unsigned char object, zbx_uint64_t objectid,
		const zbx_timespec_t *timespec, int value, const char *trigger_description,
		const char *trigger_expression, const char *trigger_recovery_expression, unsigned char trigger_priority,
		unsigned char trigger_type, const zbx_vector_ptr_t *trigger_tags,
		unsigned char trigger_correlation_mode, const char *trigger_correlation_tag)
{
	int	i;

	if (events_num == events_alloc)
	{
		events_alloc += 64;
		events = zbx_realloc(events, sizeof(DB_EVENT) * events_alloc);
	}

	events[events_num].eventid = 0;
	events[events_num].source = source;
	events[events_num].object = object;
	events[events_num].objectid = objectid;
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

		zbx_vector_ptr_create(&events[events_num].tags);

		if (NULL != trigger_tags)
		{
			for (i = 0; i < trigger_tags->values_num; i++)
			{
				const zbx_tag_t	*trigger_tag = (const zbx_tag_t *)trigger_tags->values[i];
				zbx_tag_t	*tag;

				tag = zbx_malloc(NULL, sizeof(zbx_tag_t));
				tag->tag = zbx_strdup(NULL, trigger_tag->tag);
				tag->value = zbx_strdup(NULL, trigger_tag->value);

				substitute_simple_macros(NULL, &events[events_num], NULL, NULL, NULL, NULL, NULL, NULL,
						&tag->tag, MACRO_TYPE_TRIGGER_TAG, NULL, 0);

				substitute_simple_macros(NULL, &events[events_num], NULL, NULL, NULL, NULL, NULL, NULL,
						&tag->value, MACRO_TYPE_TRIGGER_TAG, NULL, 0);

				if (SUCCEED == validate_event_tag(&events[events_num], tag))
					zbx_vector_ptr_append(&events[events_num].tags, tag);
				else
					zbx_free_tag(tag);
			}
		}
	}

	return &events[events_num++];
}

/******************************************************************************
 *                                                                            *
 * Function: save_events                                                      *
 *                                                                            *
 * Purpose: flushes the events into a database                                *
 *                                                                            *
 ******************************************************************************/
static void	save_events()
{
	size_t			i;
	zbx_db_insert_t		db_insert, db_insert_tags;
	int			j, tags_num = 0, create_num = 0;
	zbx_uint64_t		eventid;

	for (i = 0; i < events_num; i++)
	{
		if (0 != (events[i].flags & ZBX_FLAGS_DB_EVENT_CREATE))
			create_num++;
	}

	zbx_db_insert_prepare(&db_insert, "events", "eventid", "source", "object", "objectid", "clock", "ns", "value",
			NULL);

	eventid = DBget_maxid_num("events", create_num);

	for (i = 0; i < events_num; i++)
	{
		if (0 == (events[i].flags & ZBX_FLAGS_DB_EVENT_CREATE))
			continue;

		events[i].eventid = eventid++;

		zbx_db_insert_add_values(&db_insert, events[i].eventid, events[i].source, events[i].object,
				events[i].objectid, events[i].clock, events[i].ns, events[i].value);

		if (EVENT_SOURCE_TRIGGERS != events[i].source)
			continue;

		tags_num += events[i].tags.values_num;
	}

	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	if (0 != tags_num)
	{
		zbx_db_insert_prepare(&db_insert_tags, "event_tag", "eventtagid", "eventid", "tag", "value", NULL);

		for (i = 0; i < events_num; i++)
		{
			if (0 == (events[i].flags & ZBX_FLAGS_DB_EVENT_CREATE))
				continue;

			if (EVENT_SOURCE_TRIGGERS != events[i].source)
				continue;

			for (j = 0; j < events[i].tags.values_num; j++)
			{
				zbx_tag_t	*tag = (zbx_tag_t *)events[i].tags.values[j];

				zbx_db_insert_add_values(&db_insert_tags, __UINT64_C(0), events[i].eventid, tag->tag,
						tag->value);
			}
		}

		zbx_db_insert_autoincrement(&db_insert_tags, "eventtagid");
		zbx_db_insert_execute(&db_insert_tags);
		zbx_db_insert_clean(&db_insert_tags);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: save_problems                                                    *
 *                                                                            *
 * Purpose: generates problems from problem events (trigger and internal      *
 *          event sources)                                                    *
 *                                                                            *
 ******************************************************************************/
static void	save_problems()
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
				NULL);

		for (j = 0; j < problems.values_num; j++)
		{
			const DB_EVENT	*event = (const DB_EVENT *)problems.values[j];

			zbx_db_insert_add_values(&db_insert, event->eventid, event->source, event->object,
					event->objectid, event->clock, event->ns);
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
 * Parameters: event_recovery - [IN] a vector of (problem eventid, OK event)  *
 *                                   pairs.                                   *
 *                                                                            *
 ******************************************************************************/
static void	save_event_recovery()
{
	zbx_db_insert_t		db_insert;
	zbx_event_recovery_t	*recovery;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_hashset_iter_t	iter;

	if (0 == event_recovery.num_data)
		return;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	zbx_db_insert_prepare(&db_insert, "event_recovery", "eventid", "r_eventid", NULL);

	zbx_hashset_iter_reset(&event_recovery, &iter);
	while (NULL != (recovery = zbx_hashset_iter_next(&iter)))
	{
		zbx_db_insert_add_values(&db_insert, recovery->eventid, recovery->r_event->eventid);

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"update problem set"
			" r_eventid=" ZBX_FS_UI64 ","
			" r_clock=%d,"
			" r_ns=%d"
			" where eventid=" ZBX_FS_UI64 ";\n",
			recovery->r_event->eventid,
			recovery->r_event->clock,
			recovery->r_event->ns,
			recovery->eventid);
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
 * Function: get_event_by_source_object_id                                    *
 *                                                                            *
 * Purpose: find event by its source object                                   *
 *                                                                            *
 * Parameters: source   - [IN] the event source                               *
 *             object   - [IN] the object type                                *
 *             objectid - [IN] the object id                                  *
 *                                                                            *
 * Return value: the event or NULL                                            *
 *                                                                            *
 ******************************************************************************/
static DB_EVENT	*get_event_by_source_object_id(int source, int object, zbx_uint64_t objectid)
{
	size_t		i;
	DB_EVENT	*event;

	for (i = 0; i < events_num; i++)
	{
		event = &events[i];

		if (event->source == source && event->object == object && event->objectid == objectid)
			return event;
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Function: correlate_events_by_default_rules                                *
 *                                                                            *
 * Purpose: find problem events recovered by the new success (OK) events      *
 *                                                                            *
 ******************************************************************************/
static void	correlate_events_by_default_rules()
{
	int			source, object;
	zbx_uint64_t		objectid;
	char			*sql = NULL, *separator = "";
	size_t			i, sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	trigger_triggerids, internal_triggerids, internal_itemids, internal_lldruleids;
	DB_RESULT		result;
	DB_ROW			row;
	DB_EVENT		*event;
	zbx_event_recovery_t	recovery_local;

	zbx_vector_uint64_create(&trigger_triggerids);
	zbx_vector_uint64_create(&internal_triggerids);
	zbx_vector_uint64_create(&internal_itemids);
	zbx_vector_uint64_create(&internal_lldruleids);

	for (i = 0; i < events_num; i++)
	{
		event = &events[i];

		if (EVENT_SOURCE_TRIGGERS == event->source)
		{
			if (EVENT_OBJECT_TRIGGER == event->object && TRIGGER_VALUE_OK == event->value &&
					ZBX_TRIGGER_CORRELATION_NONE == event->trigger.correlation_mode)
			{
				zbx_vector_uint64_append(&trigger_triggerids, event->objectid);
			}

			continue;
		}

		if (EVENT_SOURCE_INTERNAL == event->source)
		{
			switch (event->object)
			{
				case EVENT_OBJECT_TRIGGER:
					if (TRIGGER_STATE_NORMAL == event->value)
						zbx_vector_uint64_append(&internal_triggerids, event->objectid);
					break;
				case EVENT_OBJECT_ITEM:
					if (ITEM_STATE_NORMAL == event->value)
						zbx_vector_uint64_append(&internal_itemids, event->objectid);
					break;
				case EVENT_OBJECT_LLDRULE:
					if (ITEM_STATE_NORMAL == event->value)
						zbx_vector_uint64_append(&internal_lldruleids, event->objectid);
					break;
			}
		}
	}

	if (0 == trigger_triggerids.values_num && 0 == internal_triggerids.values_num &&
			0 == internal_itemids.values_num && 0 == internal_lldruleids.values_num)
	{
		goto out;
	}

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select eventid,source,object,objectid from problem where r_eventid is null and");

	if (0 != trigger_triggerids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%s (source=%d and object=%d and",
				separator, EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "objectid", trigger_triggerids.values,
				trigger_triggerids.values_num);
		zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, ')');
		separator=" or";
	}

	if (0 != internal_triggerids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%s (source=%d and object=%d and",
				separator, EVENT_SOURCE_INTERNAL, EVENT_OBJECT_TRIGGER);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "objectid", internal_triggerids.values,
				internal_triggerids.values_num);
		zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, ')');
		separator=" or";
	}

	if (0 != internal_itemids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%s (source=%d and object=%d and",
				separator, EVENT_SOURCE_INTERNAL, EVENT_OBJECT_ITEM);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "objectid", internal_itemids.values,
				internal_itemids.values_num);
		zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, ')');
		separator=" or";
	}

	if (0 != internal_lldruleids.values_num)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%s (source=%d and object=%d and",
				separator, EVENT_SOURCE_INTERNAL, EVENT_OBJECT_LLDRULE);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "objectid", internal_lldruleids.values,
				internal_lldruleids.values_num);
		zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, ')');
	}

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		source = atoi(row[1]);
		object = atoi(row[2]);
		ZBX_STR2UINT64(objectid, row[3]);

		if (NULL == (event = get_event_by_source_object_id(source, object, objectid)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		ZBX_STR2UINT64(recovery_local.eventid, row[0]);

		if (NULL != zbx_hashset_search(&event_recovery, &recovery_local))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		recovery_local.objectid = objectid;
		recovery_local.r_event = event;
		zbx_hashset_insert(&event_recovery, &recovery_local, sizeof(recovery_local));
	}

	DBfree_result(result);
	zbx_free(sql);
out:
	zbx_vector_uint64_destroy(&internal_lldruleids);
	zbx_vector_uint64_destroy(&internal_itemids);
	zbx_vector_uint64_destroy(&internal_triggerids);
	zbx_vector_uint64_destroy(&trigger_triggerids);
}

/******************************************************************************
 *                                                                            *
 * Function: correlate_events_by_trigger_rules                                *
 *                                                                            *
 * Purpose: find problem events recovered by the new success (OK) events      *
 *          based on trigger correlation rules                                *
 *                                                                            *
 ******************************************************************************/
static void	correlate_events_by_trigger_rules()
{
	int			j;
	zbx_uint64_t		objectid;
	DB_RESULT		result;
	DB_ROW			row;
	DB_EVENT		*event;
	zbx_event_recovery_t	recovery_local;
	zbx_vector_str_t	values;
	char			*sql = NULL, *tag_esc, *separator = "";
	size_t			i, sql_alloc = 0, sql_offset = 0, sql_offset_old;

	zbx_vector_str_create(&values);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
			"select distinct p.eventid,p.objectid from problem p,problem_tag pt"
			" where r_eventid is null"
				" and");

	sql_offset_old = sql_offset;

	for (i = 0; i < events_num; i++)
	{
		event = &events[i];

		if (EVENT_SOURCE_TRIGGERS == event->source)
		{

			if (EVENT_OBJECT_TRIGGER != event->object || TRIGGER_VALUE_OK != event->value ||
					ZBX_TRIGGER_CORRELATION_TAG != event->trigger.correlation_mode)
			{
				continue;
			}

			/* reset event flags - create flag will be set if a problem event was closed */
			event->flags = ZBX_FLAGS_DB_EVENT_UNSET;

			for (j = 0; j < events->tags.values_num; j++)
			{
				zbx_tag_t	*tag = (zbx_tag_t *)events->tags.values[j];

				if (0 == strcmp(tag->tag, event->trigger.correlation_tag))
					zbx_vector_str_append(&values, tag->value);
			}

			if (0 == values.values_num)
				continue;

			tag_esc = DBdyn_escape_string(event->trigger.correlation_tag);

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%s("
					"p.source=%d"
					" and p.object=%d"
					" and p.objectid=%d"
					" and p.eventid=pt.eventid"
					" and pt.tag='%s'"
					" and",
					separator, EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER, event->objectid,
					tag_esc);

			DBadd_str_condition_alloc(&sql, &sql_alloc, &sql_offset, "pt.value",
					(const char **)values.values, values.values_num);

			zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, ')');
			separator = " or ";

			zbx_free(tag_esc);

			zbx_vector_str_clear(&values);
		}
	}

	if (sql_offset_old != sql_offset)
	{
		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(objectid, row[1]);

			if (NULL == (event = get_event_by_source_object_id(EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER,
					objectid)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			ZBX_STR2UINT64(recovery_local.eventid, row[0]);

			if (NULL != zbx_hashset_search(&event_recovery, &recovery_local))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			event->flags = ZBX_FLAGS_DB_EVENT_CREATE;

			recovery_local.objectid = objectid;
			recovery_local.r_event = event;
			zbx_hashset_insert(&event_recovery, &recovery_local, sizeof(recovery_local));

		}
		DBfree_result(result);
	}

	zbx_free(sql);
	zbx_vector_str_destroy(&values);
}

/******************************************************************************
 *                                                                            *
 * Function: correlate_events_by_global_rules                                 *
 *                                                                            *
 * Purpose: add events to the closing queue according to global correlation   *
 *          rules, try to lock and generate OK events for events in closing   *
 *          queue.                                                            *
 *                                                                            *
 ******************************************************************************/
static void	correlate_events_by_global_rules(zbx_vector_ptr_t *trigger_diff, zbx_vector_uint64_t *triggerids_lock)
{
	/* TODO: implementation */
}

/******************************************************************************
 *                                                                            *
 * Function: correlate_events                                                 *
 *                                                                            *
 * Purpose: find problem events recovered by the new events                   *
 *                                                                            *
 ******************************************************************************/
static void	correlate_events(zbx_vector_ptr_t *trigger_diff, zbx_vector_uint64_t *triggerids_lock)
{
	if (0 != events_num)
	{
		correlate_events_by_default_rules();

		if (NULL != trigger_diff)
			correlate_events_by_trigger_rules();
	}

	/* When correlating by global rules events may be taken from local  */
	/* queue where they were waiting for trigger lock. Because of that  */
	/* we are processing global correlation rules event if there are    */
	/* events in cache - new OK events might be added to cache during   */
	/* global correlation.                                              */
	if (NULL != triggerids_lock)
		correlate_events_by_global_rules(trigger_diff, triggerids_lock);
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
	zbx_hashset_iter_t	iter;
	zbx_event_recovery_t	*recovery;

	/* update trigger problem_count for new problem events */
	for (i = 0; i < events_num; i++)
	{
		DB_EVENT	*event = &events[i];

		if (EVENT_SOURCE_TRIGGERS != event->source)
			continue;

		if (0 == (event->flags & ZBX_FLAGS_DB_EVENT_CREATE))
			continue;

		if (TRIGGER_VALUE_PROBLEM != event->value)
			continue;

		if (FAIL == (index = zbx_vector_ptr_bsearch(trigger_diff, &event->objectid,
				ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		diff = (zbx_trigger_diff_t *)trigger_diff->values[index];
		diff->problem_count++;
		diff->flags |= ZBX_FLAGS_TRIGGER_DIFF_UPDATE_PROBLEM_COUNT;
	}

	/* update trigger problem_count for recovered events */
	if (0 != event_recovery.num_data)
	{
		/* Note that we expect trigger changeset in the trigger_diff vector.      */
		/* For normal operation and trigger level correlation it will be true.    */
		/* For global correlation the trigger diff of recovered events must be    */
		/* added there by correlation module.                                     */

		zbx_hashset_iter_reset(&event_recovery, &iter);

		while (NULL != (recovery = zbx_hashset_iter_next(&iter)))
		{
			if (EVENT_SOURCE_TRIGGERS != recovery->r_event->source)
				continue;

			if (FAIL == (index = zbx_vector_ptr_bsearch(trigger_diff, &recovery->objectid,
					ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				continue;
			}

			diff = (zbx_trigger_diff_t *)trigger_diff->values[index];
			diff->problem_count--;
			diff->lastchange = recovery->r_event->clock;
			diff->flags |= ZBX_FLAGS_TRIGGER_DIFF_UPDATE_PROBLEM_COUNT |
					ZBX_FLAGS_TRIGGER_DIFF_UPDATE_LASTCHANGE;
		}
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
 * Function: initialize_events                                                *
 *                                                                            *
 * Purpose: initializes the data structures required for event processing     *
 *                                                                            *
 ******************************************************************************/
static void	initialize_events()
{
	static int	is_initialized = 0;

	if (0 != is_initialized)
		return;

	zbx_hashset_create(&event_recovery, 0, ZBX_DEFAULT_UINT64_HASH_FUNC, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	is_initialized = 1;
}

/******************************************************************************
 *                                                                            *
 * Function: clean_events                                                     *
 *                                                                            *
 * Purpose: cleans all array entries and resets events_num                    *
 *                                                                            *
 ******************************************************************************/
static void	clean_events()
{
	size_t	i;

	for (i = 0; i < events_num; i++)
	{
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

/******************************************************************************
 *                                                                            *
 * Function: process_events                                                   *
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
 ******************************************************************************/
int	process_events(zbx_vector_ptr_t *trigger_diff, zbx_vector_uint64_t *triggerids_lock)
{
	const char	*__function_name = "process_events";
	int		ret = (int)events_num;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() events_num:" ZBX_FS_SIZE_T, __function_name, (zbx_fs_size_t)events_num);

	initialize_events();

	correlate_events(trigger_diff, triggerids_lock);

	if (0 != events_num)
	{
		save_events();
		save_problems();
		save_event_recovery();

		process_actions(events, events_num, &event_recovery);

		DBupdate_itservices(events, events_num);

		if (NULL != trigger_diff)
			update_trigger_changes(trigger_diff);

		clean_events();
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return ret;		/* performance metric */
}
