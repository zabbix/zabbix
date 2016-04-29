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
void	add_event( unsigned char source, unsigned char object, zbx_uint64_t objectid,
		const zbx_timespec_t *timespec, int value, const char *trigger_description,
		const char *trigger_expression, const char *trigger_recovery_expression, unsigned char trigger_priority,
		unsigned char trigger_type, zbx_vector_ptr_t *trigger_tags)
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

	zbx_vector_ptr_create(&events[events_num].tags);

	if (EVENT_SOURCE_TRIGGERS == source)
	{
		events[events_num].trigger.triggerid = objectid;
		events[events_num].trigger.description = zbx_strdup(NULL, trigger_description);
		events[events_num].trigger.expression = zbx_strdup(NULL, trigger_expression);
		events[events_num].trigger.recovery_expression = zbx_strdup(NULL, trigger_recovery_expression);
		events[events_num].trigger.priority = trigger_priority;
		events[events_num].trigger.type = trigger_type;

		if (NULL != trigger_tags)
		{
			for (i = 0; i < trigger_tags->values_num; i++)
			{
				zbx_tag_t	*trigger_tag = (zbx_tag_t *)trigger_tags->values[i];
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
	zbx_db_insert_t	db_insert, db_insert_tags;
	int		j, new_tags = 0;
	zbx_uint64_t	eventid, eventtagid;

	zbx_db_insert_prepare(&db_insert, "events", "eventid", "source", "object", "objectid", "clock", "ns", "value",
			NULL);

	eventid = DBget_maxid_num("events", events_num);

	for (i = 0; i < events_num; i++)
	{
		events[i].eventid = eventid++;

		zbx_db_insert_add_values(&db_insert, events[i].eventid, events[i].source, events[i].object,
				events[i].objectid, events[i].clock, events[i].ns, events[i].value);

		new_tags += events[i].tags.values_num;
	}

	zbx_db_insert_execute(&db_insert);
	zbx_db_insert_clean(&db_insert);

	if (0 == new_tags)
		return

	zbx_db_insert_prepare(&db_insert_tags, "event_tag", "eventtagid", "eventid", "tag", "value", NULL);

	eventtagid = DBget_maxid_num("event_tag", new_tags);

	for (i = 0; i < events_num; i++)
	{
		for (j = 0; j < events[i].tags.values_num; j++)
		{
			zbx_tag_t	*tag = (zbx_tag_t *)events[i].tags.values[j];

			substitute_simple_macros(NULL, &events[i], NULL, NULL, NULL, NULL, NULL, NULL, &tag->tag,
					MACRO_TYPE_TRIGGER_TAG, NULL, 0);

			substitute_simple_macros(NULL, &events[i], NULL, NULL, NULL, NULL, NULL, NULL, &tag->value,
					MACRO_TYPE_TRIGGER_TAG, NULL, 0);

			zbx_db_insert_add_values(&db_insert_tags, eventtagid++, events[i].eventid, tag->tag,
					tag->value);
		}
	}

	zbx_db_insert_execute(&db_insert_tags);
	zbx_db_insert_clean(&db_insert_tags);
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
		zbx_vector_ptr_clear_ext(&events[i].tags, (zbx_clean_func_t)zbx_free_tag);
		zbx_vector_ptr_destroy(&events[i].tags);

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

		process_actions(events, events_num);

		DBupdate_itservices(events, events_num);

		clean_events();
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return ret;		/* performance metric */
}
