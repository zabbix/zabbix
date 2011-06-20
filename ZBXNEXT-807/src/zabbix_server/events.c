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
** Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
**/

#include "common.h"
#include "db.h"
#include "log.h"
#include "zbxserver.h"

#include "actions.h"
#include "events.h"

/******************************************************************************
 *                                                                            *
 * Function: process_event                                                    *
 *                                                                            *
 * Purpose: process new event                                                 *
 *                                                                            *
 * Parameters: event - event data (event.eventid - new event)                 *
 *                                                                            *
 * Return value: SUCCESS - event added                                        *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
int	process_event(DB_EVENT *event, int force_actions)
{
	const char	*__function_name = "process_event";
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() eventid:" ZBX_FS_UI64
			" object:%d objectid:" ZBX_FS_UI64
			" value:%d value_changed:%d",
			__function_name, event->eventid, event->object,
			event->objectid, event->value, event->value_changed);

	if (TRIGGER_VALUE_CHANGED_YES == event->value_changed || 1 == force_actions)
	{
		if (EVENT_OBJECT_TRIGGER == event->object && 0 != event->objectid)
		{
			if (SUCCEED != DCconfig_get_trigger_for_event(&event->trigger, event->objectid))
				goto fail;
		}
	}

	if (0 == event->eventid)
		event->eventid = DBget_maxid("events");

	DBexecute("insert into events (eventid,source,object,objectid,clock,ns,value,value_changed)"
			" values (" ZBX_FS_UI64 ",%d,%d," ZBX_FS_UI64 ",%d,%d,%d,%d)",
			event->eventid,
			event->source,
			event->object,
			event->objectid,
			event->clock,
			event->ns,
			event->value,
			event->value_changed);

	if (TRIGGER_VALUE_CHANGED_YES == event->value_changed || 1 == force_actions)
		process_actions(event);

	if (TRIGGER_VALUE_CHANGED_YES == event->value_changed && EVENT_OBJECT_TRIGGER == event->object)
		DBupdate_services(event->objectid, TRIGGER_VALUE_TRUE == event->value ? event->trigger.priority : 0, event->clock);

	ret = SUCCEED;
fail:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
