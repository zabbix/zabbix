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

static DB_EVENT	*events = NULL;
static size_t	events_alloc = 0, events_num = 0;

/******************************************************************************
 *                                                                            *
 * Function: add_event                                                        *
 *                                                                            *
 * Purpose: add event to an array                                             *
 *                                                                            *
 * Parameters: eventid  - [IN] event identificator from database              *
 *             source   - [IN] event source (EVENT_SOURCE_*)                  *
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
 *                                                                            *
 ******************************************************************************/
void	add_event(zbx_uint64_t eventid, unsigned char source, unsigned char object, zbx_uint64_t objectid,
		const zbx_timespec_t *timespec, int value, const char *trigger_description,
		const char *trigger_expression, const char *trigger_recovery_expression, unsigned char trigger_priority,
		unsigned char trigger_type)
{
	if (events_num == events_alloc)
	{
		events_alloc += 64;
		events = zbx_realloc(events, sizeof(DB_EVENT) * events_alloc);
	}

	events[events_num].eventid = eventid;
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
	}

	events_num++;
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
	size_t		i;
	zbx_db_insert_t	db_insert;
	int		new_events = 0;
	zbx_uint64_t	eventid;

	zbx_db_insert_prepare(&db_insert, "events", "eventid", "source", "object", "objectid", "clock", "ns", "value",
			NULL);

	for (i = 0; i < events_num; i++)
	{
		if (0 == events[i].eventid)
			new_events++;
	}

	eventid = DBget_maxid_num("events", new_events);

	for (i = 0; i < events_num; i++)
	{
		if (0 == events[i].eventid)
			events[i].eventid = eventid++;

		zbx_db_insert_add_values(&db_insert, events[i].eventid, events[i].source, events[i].object,
				events[i].objectid, events[i].clock, events[i].ns, events[i].value);
	}

	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);
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
