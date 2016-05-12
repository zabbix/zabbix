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
 * Function: link_recovery_events                                             *
 *                                                                            *
 * Purpose: links trigger recovery (OK) events to corresponding problem       *
 *          events                                                            *
 *                                                                            *
 * Parameters: events - [IN] recovery events                                  *
 *                                                                            *
 ******************************************************************************/
static void	link_recovery_events(const zbx_vector_ptr_t *events)
{
	char	*sql = NULL;
	size_t	sql_alloc = 0, sql_offset = 0;
	int	i;

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	for (i = 0; i < events->values_num; i++)
	{
		const DB_EVENT	*event = (const DB_EVENT *)events->values[i];

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"update events set r_eventid=" ZBX_FS_UI64
				" where source=%d"
					" and object=%d"
					" and objectid=" ZBX_FS_UI64
					" and value=%d;\n",
				event->eventid, EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER, event->trigger.triggerid,
				TRIGGER_VALUE_PROBLEM);

		DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (16 < sql_offset)	/* in ORACLE always present begin..end; */
		DBexecute("%s", sql);

	zbx_free(sql);
}

/******************************************************************************
 *                                                                            *
 * Function: remove_problems                                                  *
 *                                                                            *
 * Purpose: remove problems created by now recovered (OK) triggers            *
 *                                                                            *
 * Parameters: events - [IN] recovery events                                  *
 *                                                                            *
 ******************************************************************************/
static void	remove_problems(const zbx_vector_ptr_t *events)
{
	char			*sql = NULL;
	size_t			sql_alloc = 0, sql_offset = 0;
	zbx_vector_uint64_t	triggerids;
	int			i;

	zbx_vector_uint64_create(&triggerids);

	for (i = 0; i < events->values_num; i++)
	{
		const DB_EVENT	*event = (const DB_EVENT *)events->values[i];

		zbx_vector_uint64_append(&triggerids, event->trigger.triggerid);
	}

	zbx_vector_uint64_sort(&triggerids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from problem where");
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "triggerid", triggerids.values, triggerids.values_num);

	DBexecute("%s", sql);

	zbx_free(sql);
	zbx_vector_uint64_destroy(&triggerids);
}

/******************************************************************************
 *                                                                            *
 * Function: add_problems                                                     *
 *                                                                            *
 * Purpose: add problems based on problem events and their triggers           *
 *                                                                            *
 * Parameters: events - [IN] problem events                                   *
 *                                                                            *
 ******************************************************************************/
static void	add_problems(const zbx_vector_ptr_t *events)
{
	int		i;
	zbx_db_insert_t	db_insert;

	zbx_db_insert_prepare(&db_insert, "problem", "problemid", "triggerid", "eventid", NULL);

	for (i = 0; i < events->values_num; i++)
	{
		const DB_EVENT	*event = (const DB_EVENT *)events->values[i];

		zbx_db_insert_add_values(&db_insert, __UINT64_C(0), event->trigger.triggerid, event->eventid);
	}

	zbx_db_insert_autoincrement(&db_insert, "problemid");
	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);
}

/******************************************************************************
 *                                                                            *
 * Function: process_trigger_events                                           *
 *                                                                            *
 * Purpose: process trigger based events                                      *
 *                                                                            *
 ******************************************************************************/
static void	process_trigger_events()
{
	size_t	i;
	zbx_vector_ptr_t	problem_events, recovery_events;

	zbx_vector_ptr_create(&problem_events);
	zbx_vector_ptr_create(&recovery_events);

	for (i = 0; i < events_num; i++)
	{
		DB_EVENT	*event = &events[i];

		if (EVENT_SOURCE_TRIGGERS != event->source || EVENT_OBJECT_TRIGGER != event->object)
			continue;

		switch (event->value)
		{
			case TRIGGER_VALUE_OK:
				zbx_vector_ptr_append(&recovery_events, event);
				break;
			case TRIGGER_VALUE_PROBLEM:
				zbx_vector_ptr_append(&problem_events, event);
				break;
		}
	}

	if (0 != recovery_events.values_num)
	{
		link_recovery_events(&recovery_events);
		remove_problems(&recovery_events);
	}

	if (0 != problem_events.values_num)
		add_problems(&problem_events);

	zbx_vector_ptr_destroy(&recovery_events);
	zbx_vector_ptr_destroy(&problem_events);
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
		save_events();

		process_trigger_events();

		process_actions(events, events_num);

		DBupdate_itservices(events, events_num);

		clean_events();
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return ret;		/* performance metric */
}
