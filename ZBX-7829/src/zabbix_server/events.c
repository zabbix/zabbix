/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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
 *             trigger_description - [IN] trigger description                 *
 *             trigger_expression  - [IN] trigger short expression            *
 *             trigger_priority    - [IN] trigger priority                    *
 *             trigger_type        - [IN] trigger type (TRIGGER_TYPE_*)       *
 *                                                                            *
 ******************************************************************************/
void	add_event(zbx_uint64_t eventid, unsigned char source, unsigned char object, zbx_uint64_t objectid,
		const zbx_timespec_t *timespec, int value, const char *trigger_description,
		const char *trigger_expression, unsigned char trigger_priority, unsigned char trigger_type)
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
	char		*sql = NULL;
	size_t		sql_alloc = 2 * ZBX_KIBIBYTE, sql_offset = 0, i;
	const char	*ins_event_sql = "insert into events (eventid,source,object,objectid,clock,ns,value) values ";

	sql = zbx_malloc(sql, sql_alloc);

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

#ifdef HAVE_MULTIROW_INSERT
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ins_event_sql);
#endif

	for (i = 0; i < events_num; i++)
	{
		if (0 == events[i].eventid)
			events[i].eventid = DBget_maxid("events");
#ifndef HAVE_MULTIROW_INSERT
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ins_event_sql);
#endif
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
			"(" ZBX_FS_UI64 ",%d,%d," ZBX_FS_UI64 ",%d,%d,%d)" ZBX_ROW_DL,
			events[i].eventid, events[i].source, events[i].object, events[i].objectid, events[i].clock,
			events[i].ns, events[i].value);
	}

#ifdef HAVE_MULTIROW_INSERT
	sql_offset--;
	zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
#endif
	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	DBexecute("%s", sql);

	zbx_free(sql);
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
	}

	events_num = 0;
}

int	process_events(void)
{
	const char	*__function_name = "process_events";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() events_num:" ZBX_FS_SIZE_T, __function_name, (zbx_fs_size_t)events_num);

	if (0 != events_num)
	{
		save_events();

		process_actions(events, events_num);

		DBupdate_itservices(events, events_num);

		clean_events();
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return (int)events_num;		/* performance metric */
}
