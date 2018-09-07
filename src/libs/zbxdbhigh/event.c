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
#include "dbcache.h"

/******************************************************************************
 *                                                                            *
 * Function: zbx_get_events_by_eventids                                       *
 *                                                                            *
 * Purpose: get events and flags that indicate what was filled in DB_EVENT    *
 *          structure                                                         *
 *                                                                            *
 * Parameters: eventids   - [IN] requested event ids                          *
 *             events     - [OUT] the array of events                         *
 *                                                                            *
 * Comments: use 'free_db_event' function to release allocated memory         *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_get_events_by_eventids(zbx_vector_uint64_t *eventids, zbx_vector_ptr_t *events)
{
	DB_RESULT		result;
	DB_ROW			row;
	char			*filter = NULL;
	size_t			filter_alloc = 0, filter_offset = 0;
	zbx_vector_uint64_t	trigger_eventids, triggerids;
	int			i, index;

	zbx_vector_uint64_create(&trigger_eventids);
	zbx_vector_uint64_create(&triggerids);

	zbx_vector_uint64_sort(eventids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
	zbx_vector_uint64_uniq(eventids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	DBadd_condition_alloc(&filter, &filter_alloc, &filter_offset, "eventid", eventids->values,
			eventids->values_num);

	result = DBselect("select eventid,source,object,objectid,clock,value,acknowledged,ns,name,severity"
			" from events"
			" where%s order by eventid",
			filter);

	while (NULL != (row = DBfetch(result)))
	{
		DB_EVENT	*event = NULL;

		event = (DB_EVENT *)zbx_malloc(event, sizeof(DB_EVENT));
		ZBX_STR2UINT64(event->eventid, row[0]);
		event->source = atoi(row[1]);
		event->object = atoi(row[2]);
		ZBX_STR2UINT64(event->objectid, row[3]);
		event->clock = atoi(row[4]);
		event->value = atoi(row[5]);
		event->acknowledged = atoi(row[6]);
		event->ns = atoi(row[7]);
		event->name = zbx_strdup(NULL, row[8]);
		event->severity = atoi(row[9]);

		event->trigger.triggerid = 0;

		if (EVENT_SOURCE_TRIGGERS == event->source)
		{
			zbx_vector_ptr_create(&event->tags);
			zbx_vector_uint64_append(&trigger_eventids, event->eventid);
		}

		if (EVENT_OBJECT_TRIGGER == event->object)
			zbx_vector_uint64_append(&triggerids, event->objectid);

		zbx_vector_ptr_append(events, event);
	}
	DBfree_result(result);

	if (0 != trigger_eventids.values_num)	/* EVENT_SOURCE_TRIGGERS */
	{
		DB_EVENT	*event = NULL;

		filter_offset = 0;
		DBadd_condition_alloc(&filter, &filter_alloc, &filter_offset, "eventid", trigger_eventids.values,
				trigger_eventids.values_num);

		result = DBselect("select eventid,tag,value from event_tag where%s order by eventid", filter);

		while (NULL != (row = DBfetch(result)))
		{
			zbx_uint64_t	eventid;
			zbx_tag_t	*tag;

			ZBX_STR2UINT64(eventid, row[0]);

			if (NULL == event || eventid != event->eventid)
			{
				if (FAIL == (index = zbx_vector_ptr_bsearch(events, &eventid,
						ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC)))
				{
					THIS_SHOULD_NEVER_HAPPEN;
					continue;
				}

				event = (DB_EVENT *)events->values[index];
			}

			tag = (zbx_tag_t *)zbx_malloc(NULL, sizeof(zbx_tag_t));
			tag->tag = zbx_strdup(NULL, row[1]);
			tag->value = zbx_strdup(NULL, row[2]);
			zbx_vector_ptr_append(&event->tags, tag);
		}
		DBfree_result(result);
	}

	if (0 != triggerids.values_num)	/* EVENT_OBJECT_TRIGGER */
	{
		zbx_vector_uint64_sort(&triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
		zbx_vector_uint64_uniq(&triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		filter_offset = 0;
		DBadd_condition_alloc(&filter, &filter_alloc, &filter_offset, "triggerid", triggerids.values,
				triggerids.values_num);

		result = DBselect(
				"select triggerid,description,expression,priority,comments,url,recovery_expression,"
					"recovery_mode,value"
				" from triggers"
				" where%s",
				filter);

		while (NULL != (row = DBfetch(result)))
		{
			zbx_uint64_t	triggerid;

			ZBX_STR2UINT64(triggerid, row[0]);

			for (i = 0; i < events->values_num; i++)
			{
				DB_EVENT	*event = (DB_EVENT *)events->values[i];

				if (EVENT_OBJECT_TRIGGER != event->object)
					continue;

				if (triggerid == event->objectid)
				{
					event->trigger.triggerid = triggerid;
					event->trigger.description = zbx_strdup(NULL, row[1]);
					event->trigger.expression = zbx_strdup(NULL, row[2]);
					ZBX_STR2UCHAR(event->trigger.priority, row[3]);
					event->trigger.comments = zbx_strdup(NULL, row[4]);
					event->trigger.url = zbx_strdup(NULL, row[5]);
					event->trigger.recovery_expression = zbx_strdup(NULL, row[6]);
					ZBX_STR2UCHAR(event->trigger.recovery_mode, row[7]);
					ZBX_STR2UCHAR(event->trigger.value, row[8]);
				}
			}
		}
		DBfree_result(result);
	}

	zbx_free(filter);

	zbx_vector_uint64_destroy(&trigger_eventids);
	zbx_vector_uint64_destroy(&triggerids);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_db_trigger_clean                                             *
 *                                                                            *
 * Purpose: frees resources allocated to store trigger data                   *
 *                                                                            *
 * Parameters: trigger -                                                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_trigger_clean(DB_TRIGGER *trigger)
{
	zbx_free(trigger->description);
	zbx_free(trigger->expression);
	zbx_free(trigger->recovery_expression);
	zbx_free(trigger->comments);
	zbx_free(trigger->url);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_free_event                                                   *
 *                                                                            *
 * Purpose: deallocate memory allocated in function 'get_db_events_info'      *
 *                                                                            *
 * Parameters: event - [IN] event data                                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_free_event(DB_EVENT *event)
{
	if (EVENT_SOURCE_TRIGGERS == event->source)
	{
		zbx_vector_ptr_clear_ext(&event->tags, (zbx_clean_func_t)zbx_free_tag);
		zbx_vector_ptr_destroy(&event->tags);
	}

	if (0 != event->trigger.triggerid)
		zbx_db_trigger_clean(&event->trigger);

	zbx_free(event->name);
	zbx_free(event);
}

/******************************************************************************
 *                                                                            *
 * Function: get_db_eventid_r_eventid_pairs                                   *
 *                                                                            *
 * Purpose: get recovery event IDs by event IDs then map them together also   *
 *          additional create a separate array of recovery event IDs          *
 *                                                                            *
 * Parameters: eventids    - [IN] requested event IDs                         *
 *             event_pairs - [OUT] the array of event ID and recovery event   *
 *                                 pairs                                      *
 *             r_eventids  - [OUT] array of recovery event IDs                *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_get_eventid_r_eventid_pairs(zbx_vector_uint64_t *eventids, zbx_vector_uint64_pair_t *event_pairs,
		zbx_vector_uint64_t *r_eventids)
{
	DB_RESULT	result;
	DB_ROW		row;
	char		*filter = NULL;
	size_t		filter_alloc = 0, filter_offset = 0;

	DBadd_condition_alloc(&filter, &filter_alloc, &filter_offset, "eventid", eventids->values,
			eventids->values_num);

	result = DBselect("select eventid,r_eventid"
			" from event_recovery"
			" where%s order by eventid",
			filter);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_pair_t	r_event;

		ZBX_STR2UINT64(r_event.first, row[0]);
		ZBX_STR2UINT64(r_event.second, row[1]);

		zbx_vector_uint64_pair_append(event_pairs, r_event);
		zbx_vector_uint64_append(r_eventids, r_event.second);
	}
	DBfree_result(result);

	zbx_free(filter);
}
