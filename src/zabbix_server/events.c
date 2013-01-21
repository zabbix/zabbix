/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "db.h"
#include "log.h"
#include "zbxserver.h"

#include "actions.h"
#include "events.h"

/******************************************************************************
 *                                                                            *
 * Function: add_trigger_info                                                 *
 *                                                                            *
 * Purpose: add trigger info to event if required                             *
 *                                                                            *
 * Parameters: event - [IN] event data                                        *
 *                                                                            *
 * Author: Alexei Vladishev, Aleksandrs Saveljevs                             *
 *                                                                            *
 ******************************************************************************/
static int	add_trigger_info(DB_EVENT *event)
{
	const char	*__function_name = "add_trigger_info";
	int		ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (EVENT_SOURCE_TRIGGERS == event->source)
	{
		if (SUCCEED == DBis_node_local_id(event->objectid))
		{
			ret = DCconfig_get_trigger_for_event(&event->trigger, event->objectid);
		}
		else
		{
			DB_RESULT	result;
			DB_ROW		row;

			result = DBselect(
					"select description,expression,priority,type"
					" from triggers"
					" where triggerid=" ZBX_FS_UI64,
					event->objectid);

			if (NULL != (row = DBfetch(result)))
			{
				event->trigger.triggerid = event->objectid;
				event->trigger.description = zbx_strdup(event->trigger.description, row[0]);
				event->trigger.expression = zbx_strdup(event->trigger.expression, row[1]);
				event->trigger.priority = (unsigned char)atoi(row[2]);
				event->trigger.type = (unsigned char)atoi(row[3]);
			}
			else
				ret = FAIL;
			DBfree_result(result);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static void	free_trigger_info(DB_EVENT *event)
{
	if (EVENT_SOURCE_TRIGGERS == event->source)
	{
		zbx_free(event->trigger.description);
		zbx_free(event->trigger.expression);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: process_event                                                    *
 *                                                                            *
 * Purpose: process new event                                                 *
 *                                                                            *
 * Parameters: event - event data (event.eventid - new event)                 *
 *                                                                            *
 * Return value: SUCCEED - event added                                        *
 *               FAIL    - event not added                                    *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
int	process_event(zbx_uint64_t eventid, int source, int object, zbx_uint64_t objectid,
		const zbx_timespec_t *timespec, int value, int acknowledged, int force_actions)
{
	const char	*__function_name = "process_event";
	DB_EVENT	event;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() eventid:" ZBX_FS_UI64 " object:%d objectid:" ZBX_FS_UI64 " value:%d",
			__function_name, eventid, object, objectid, value);

	/* preparing event for processing */
	memset(&event, 0, sizeof(DB_EVENT));
	event.eventid = eventid;
	event.source = source;
	event.object = object;
	event.objectid = objectid;
	event.clock = timespec->sec;
	event.ns = timespec->ns;
	event.value = value;
	event.acknowledged = acknowledged;

	if (EVENT_SOURCE_TRIGGERS == event.source || 1 == force_actions)
	{
		if (SUCCEED != add_trigger_info(&event))
			goto fail;
	}

	if (0 == event.eventid)
		event.eventid = DBget_maxid("events");

	DBexecute("insert into events (eventid,source,object,objectid,clock,ns,value)"
			" values (" ZBX_FS_UI64 ",%d,%d," ZBX_FS_UI64 ",%d,%d,%d)",
			event.eventid, event.source, event.object, event.objectid, event.clock, event.ns, event.value);

	if (EVENT_SOURCE_TRIGGERS == event.source || 1 == force_actions)
		process_actions(&event);

	if (EVENT_SOURCE_TRIGGERS == event.source)
		DBupdate_services(event.objectid, TRIGGER_VALUE_PROBLEM == event.value ? event.trigger.priority : 0, event.clock);

	if (EVENT_SOURCE_TRIGGERS == event.source || 1 == force_actions)
		free_trigger_info(&event);

	ret = SUCCEED;
fail:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
