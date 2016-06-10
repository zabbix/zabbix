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

static DB_EVENT	*events = NULL;
static size_t	events_alloc = 0, events_num = 0;

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
void	add_event(unsigned char source, unsigned char object, zbx_uint64_t objectid,
		const zbx_timespec_t *timespec, int value, const char *trigger_description,
		const char *trigger_expression, const char *trigger_recovery_expression, unsigned char trigger_priority,
		unsigned char trigger_type, const zbx_vector_ptr_t *trigger_tags)
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

	if (EVENT_SOURCE_TRIGGERS == source)
	{
		events[events_num].trigger.triggerid = objectid;
		events[events_num].trigger.description = zbx_strdup(NULL, trigger_description);
		events[events_num].trigger.expression = zbx_strdup(NULL, trigger_expression);
		events[events_num].trigger.recovery_expression = zbx_strdup(NULL, trigger_recovery_expression);
		events[events_num].trigger.priority = trigger_priority;
		events[events_num].trigger.type = trigger_type;

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

				zbx_vector_ptr_append(&events[events_num].tags, tag);
			}
		}
	}

	events_num++;
}

typedef struct
{
	zbx_uint64_t	eventid;
	zbx_tag_t	*tag;
}
zbx_event_tag_t;

/******************************************************************************
 *                                                                            *
 * Event tag indexing hashset support functions                               *
 *                                                                            *
 ******************************************************************************/
static zbx_hash_t	zbx_event_tag_hash_func(const void *data)
{
	zbx_event_tag_t	*event_tag = (zbx_event_tag_t *)data;
	zbx_hash_t	hash;

	hash = ZBX_DEFAULT_STRING_HASH_FUNC(event_tag->tag->tag);

	if ('\0' != *event_tag->tag->value)
		hash = ZBX_DEFAULT_STRING_HASH_ALGO(event_tag->tag->value, strlen(event_tag->tag->value), hash);

	hash = ZBX_DEFAULT_UINT64_HASH_ALGO(&event_tag->eventid, sizeof(event_tag->eventid), hash);

	return hash;
}

static int	zbx_event_tag_compare_func(const void *d1, const void *d2)
{
	int	ret;

	zbx_event_tag_t	*event_tag1 = (zbx_event_tag_t *)d1;
	zbx_event_tag_t	*event_tag2 = (zbx_event_tag_t *)d2;

	ZBX_RETURN_IF_NOT_EQUAL(event_tag1->eventid, event_tag2->eventid);

	if (0 != (ret = strcmp(event_tag1->tag->tag, event_tag2->tag->tag)))
		return ret;

	return strcmp(event_tag1->tag->value, event_tag2->tag->value);
}

/******************************************************************************
 *                                                                            *
 * Function: expand_event_tag_macros                                          *
 *                                                                            *
 ******************************************************************************/
static void	expand_event_tag_macros()
{
	size_t	i;
	int	j;

	for (i = 0; i < events_num; i++)
	{
		if (EVENT_SOURCE_TRIGGERS != events[i].source)
			continue;

		for (j = 0; j < events[i].tags.values_num; j++)
		{
			zbx_tag_t	*tag = (zbx_tag_t *)events[i].tags.values[j];

			substitute_simple_macros(NULL, &events[i], NULL, NULL, NULL, NULL, NULL, NULL, &tag->tag,
					MACRO_TYPE_TRIGGER_TAG, NULL, 0);

			substitute_simple_macros(NULL, &events[i], NULL, NULL, NULL, NULL, NULL, NULL, &tag->value,
					MACRO_TYPE_TRIGGER_TAG, NULL, 0);
		}
	}
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
	int			j;
	zbx_uint64_t		eventid, eventtagid;
	zbx_hashset_t		event_tags;
	zbx_event_tag_t		*event_tag;
	zbx_hashset_iter_t	iter;

	zbx_hashset_create(&event_tags, events_num, zbx_event_tag_hash_func, zbx_event_tag_compare_func);

	zbx_db_insert_prepare(&db_insert, "events", "eventid", "source", "object", "objectid", "clock", "ns", "value",
			NULL);

	eventid = DBget_maxid_num("events", events_num);

	for (i = 0; i < events_num; i++)
	{
		zbx_event_tag_t	event_tag_local;

		events[i].eventid = eventid++;

		zbx_db_insert_add_values(&db_insert, events[i].eventid, events[i].source, events[i].object,
				events[i].objectid, events[i].clock, events[i].ns, events[i].value);

		if (EVENT_SOURCE_TRIGGERS != events[i].source)
			continue;

		event_tag_local.eventid = events[i].eventid;

		for (j = 0; j < events[i].tags.values_num; j++)
		{
			event_tag_local.tag = (zbx_tag_t *)events[i].tags.values[j];

			/* remove tags containing invalid characters */
			if (0 != strchr(event_tag_local.tag->tag, '/'))
				continue;

			if (NULL == (event_tag = zbx_hashset_search(&event_tags, &event_tag_local)))
				zbx_hashset_insert(&event_tags, &event_tag_local, sizeof(event_tag_local));
		}
	}

	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	if (0 != event_tags.num_data)
	{
		zbx_db_insert_prepare(&db_insert_tags, "event_tag", "eventtagid", "eventid", "tag", "value", NULL);

		eventtagid = DBget_maxid_num("event_tag", event_tags.num_data);

		zbx_hashset_iter_reset(&event_tags, &iter);
		while (NULL != (event_tag = zbx_hashset_iter_next(&iter)))
		{
			zbx_db_insert_add_values(&db_insert_tags, eventtagid++, event_tag->eventid, event_tag->tag->tag,
					event_tag->tag->value);
		}

		zbx_db_insert_execute(&db_insert_tags);
		zbx_db_insert_clean(&db_insert_tags);
	}

	zbx_hashset_destroy(&event_tags);
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
	int			j;

	zbx_vector_ptr_create(&problems);

	for (i = 0; i < events_num; i++)
	{
		DB_EVENT	*event = &events[i];

		if (EVENT_SOURCE_TRIGGERS == event->source)
		{
			if (EVENT_OBJECT_TRIGGER != event->object || TRIGGER_VALUE_PROBLEM != event->value)
				continue;
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

		zbx_db_insert_prepare(&db_insert, "problem", "eventid", "source", "object", "objectid", NULL);

		for (j = 0; j < problems.values_num; j++)
		{
			const DB_EVENT	*event = (const DB_EVENT *)problems.values[j];

			zbx_db_insert_add_values(&db_insert, event->eventid, event->source, event->object,
					event->objectid);
		}

		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
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
static void	save_event_recovery(zbx_vector_ptr_t *event_recovery)
{
	int			i;
	zbx_db_insert_t		db_insert;
	zbx_vector_uint64_t	eventids;
	zbx_event_recovery_t	*recovery;
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;

	if (0 == event_recovery->values_num)
		return;

	zbx_vector_uint64_create(&eventids);
	zbx_db_insert_prepare(&db_insert, "event_recovery", "eventid", "r_eventid", NULL);

	for (i = 0; i < event_recovery->values_num; i++)
	{
		recovery = (zbx_event_recovery_t *)event_recovery->values[i];

		zbx_db_insert_add_values(&db_insert, recovery->eventid, recovery->r_event->eventid);
		zbx_vector_uint64_append(&eventids, recovery->eventid);
	}

	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from problem where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "eventid", eventids.values, eventids.values_num);
	DBexecute("%s", sql);

	zbx_free(sql);
	zbx_vector_uint64_destroy(&eventids);
}

/******************************************************************************
 *                                                                            *
 * Function: get_event_recovery                                               *
 *                                                                            *
 * Purpose: get list of problem events recovered by the new OK events         *
 *                                                                            *
 * Parameters: event_recovery - [OUT] a vector of (problem eventid, OK event) *
 *                                    pairs.                                  *
 *                                                                            *
 ******************************************************************************/
static void	get_event_recovery(zbx_vector_ptr_t *event_recovery)
{
	int			source, object;
	zbx_uint64_t		objectid, eventid;
	char			*sql = NULL, *separator = "";
	size_t			i, sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	trigger_triggerids, internal_triggerids, internal_itemids, internal_lldruleids;
	DB_RESULT		result;
	DB_ROW			row;
	DB_EVENT		*event;
	zbx_event_recovery_t	*recovery;

	zbx_vector_uint64_create(&trigger_triggerids);
	zbx_vector_uint64_create(&internal_triggerids);
	zbx_vector_uint64_create(&internal_itemids);
	zbx_vector_uint64_create(&internal_lldruleids);

	for (i = 0; i < events_num; i++)
	{
		event = &events[i];

		if (EVENT_SOURCE_TRIGGERS == event->source)
		{
			if (EVENT_OBJECT_TRIGGER == event->object && TRIGGER_VALUE_OK == event->value)
				zbx_vector_uint64_append(&trigger_triggerids, event->objectid);

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

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "select eventid,source,object,objectid from problem where");

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
		ZBX_STR2UINT64(eventid, row[0]);

		for (i = 0; i < events_num; i++)
		{
			event = &events[i];

			if (event->source == source && event->object == object && event->objectid == objectid)
				break;
		}

		if (i == events_num)
		{
			THIS_SHOULD_NEVER_HAPPEN;
			continue;
		}

		recovery = (zbx_event_recovery_t *)zbx_malloc(NULL, sizeof(zbx_event_recovery_t));
		recovery->eventid = eventid;
		recovery->r_event = event;
		zbx_vector_ptr_append(event_recovery, recovery);
	}

	zbx_vector_ptr_sort(event_recovery, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

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

		zbx_vector_ptr_clear_ext(&events[i].tags, (zbx_clean_func_t)zbx_free_tag);
		zbx_vector_ptr_destroy(&events[i].tags);
	}

	events_num = 0;
}

int	process_events(void)
{
	const char	*__function_name = "process_events";
	int		ret = (int)events_num;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() events_num:" ZBX_FS_SIZE_T, __function_name, (zbx_fs_size_t)events_num);

	if (0 != events_num)
	{
		zbx_vector_ptr_t	event_recovery;

		zbx_vector_ptr_create(&event_recovery);

		expand_event_tag_macros();
		get_event_recovery(&event_recovery);

		save_events();
		save_problems();
		save_event_recovery(&event_recovery);

		process_actions(events, events_num, &event_recovery);

		DBupdate_itservices(events, events_num);

		zbx_vector_ptr_clear_ext(&event_recovery, zbx_ptr_free);
		zbx_vector_ptr_destroy(&event_recovery);
		clean_events();
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return ret;		/* performance metric */
}
