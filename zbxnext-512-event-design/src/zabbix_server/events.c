/*
** ZABBIX
** Copyright (C) 2000-2005 SIA Zabbix
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
 * Function: add_trigger_info                                                 *
 *                                                                            *
 * Purpose: add trigger info to event if required                             *
 *                                                                            *
 * Parameters: event - event data (event.triggerid)                           *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: use 'free_trigger_info' function to clear allocated memory       *
 *                                                                            *
 ******************************************************************************/
static void	add_trigger_info(DB_EVENT *event)
{
	if (event->object == EVENT_OBJECT_TRIGGER && event->objectid != 0)
	{
		DB_RESULT	result;
		DB_ROW		row;
		zbx_uint64_t	triggerid;

		triggerid = event->objectid;

		event->trigger_description[0] = '\0';
		zbx_free(event->trigger_comments);
		zbx_free(event->trigger_url);

		result = DBselect(
				"select description,priority,comments,url,type"
				" from triggers"
				" where triggerid=" ZBX_FS_UI64,
				triggerid);

		if (NULL != (row = DBfetch(result)))
		{
			strscpy(event->trigger_description, row[0]);
			event->trigger_priority = atoi(row[1]);
			event->trigger_comments	= strdup(row[2]);
			event->trigger_url	= strdup(row[3]);
			event->trigger_type	= atoi(row[4]);
		}
		DBfree_result(result);

		if (TRIGGER_VALUE_CHANGED_NO == event->value_changed)
			zabbix_log(LOG_LEVEL_DEBUG, "Skip actions");
	}
}

/******************************************************************************
 *                                                                            *
 * Function: free_trigger_info                                                *
 *                                                                            *
 * Purpose: clean allocated memory by function 'add_trigger_info'             *
 *                                                                            *
 * Parameters: event - event data (event.triggerid)                           *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static void	free_trigger_info(DB_EVENT *event)
{
	zbx_free(event->trigger_url);
	zbx_free(event->trigger_comments);
}

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

	int		ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() eventid:" ZBX_FS_UI64
			" object:%d objectid:" ZBX_FS_UI64
			" value:%d value_changed:%d",
			__function_name, event->eventid, event->object,
			event->objectid, event->value, event->value_changed);

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
	{
		add_trigger_info(event);

		process_actions(event);

		if (EVENT_OBJECT_TRIGGER == event->object)
			DBupdate_services(event->objectid, TRIGGER_VALUE_TRUE == event->value ? event->trigger_priority : 0, event->clock);

		free_trigger_info(event);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
