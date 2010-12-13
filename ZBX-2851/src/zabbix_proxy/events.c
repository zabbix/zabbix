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
#include "log.h"

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
 * Comments: Cannot use action->userid as it may also be groupid              *
 *                                                                            *
 ******************************************************************************/
int	process_event(DB_EVENT *event, int force_actions)
{
/*	zabbix_log(LOG_LEVEL_DEBUG,"In [proxy]process_event(eventid:" ZBX_FS_UI64 ",object:%d,objectid:" ZBX_FS_UI64 ")",
			event->eventid,
			event->object,
			event->objectid);

	if (event->eventid == 0)
		event->eventid = DBget_maxid("events", "eventid");

	DBexecute("insert into events(eventid,source,object,objectid,clock,value)"
			" values(" ZBX_FS_UI64 ",%d,%d," ZBX_FS_UI64 ",%d,%d)",
			event->eventid,
			event->source,
			event->object,
			event->objectid,
			event->clock,
			event->value);

	zabbix_log(LOG_LEVEL_DEBUG, "End of [proxy]process_event()");
*/
	return SUCCEED;
}
