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


#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <netinet/in.h>
#include <netdb.h>

#include <signal.h>

#include <string.h>

#include <time.h>

#include <sys/socket.h>
#include <errno.h>

/* Functions: pow(), round() */
#include <math.h>

#include "common.h"
#include "db.h"
#include "log.h"
#include "zlog.h"

#include "actions.h"
#include "functions.h"
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
 * Comments: use 'free_trigger_info' function to clear allocated memory     *
 *                                                                            *
 ******************************************************************************/
static void	add_trigger_info(DB_EVENT *event)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	triggerid;

	int		event_prev_status, event_last_status;

	if(event->object==EVENT_OBJECT_TRIGGER && event->objectid != 0)
	{
		triggerid = event->objectid;

		result = DBselect("select description,priority,comments,url,type from triggers where triggerid=" ZBX_FS_UI64,
			triggerid);
		row = DBfetch(result);
		event->trigger_description[0]=0;
		zbx_free(event->trigger_comments);
		zbx_free(event->trigger_url);

		if(row)
		{
			strscpy(event->trigger_description, row[0]);
			event->trigger_priority = atoi(row[1]);
			event->trigger_comments	= strdup(row[2]);
			event->trigger_url	= strdup(row[3]);
			event->trigger_type	= atoi(row[4]);
		}
		DBfree_result(result);

		get_latest_event_status(triggerid, &event_prev_status, &event_last_status);
		zabbix_log(LOG_LEVEL_DEBUG,"event_prev_status %d event_last_status %d event->value %d",
			event_prev_status,
			event_last_status,
			event->value);

		event->skip_actions = 0;

		switch(event->trigger_type)
		{
		case	TRIGGER_TYPE_NORMAL:
			if(	(event->value == TRIGGER_VALUE_UNKNOWN) ||
				(event_prev_status == TRIGGER_VALUE_TRUE && event_last_status == TRIGGER_VALUE_UNKNOWN && event->value == TRIGGER_VALUE_TRUE) ||
				(event_prev_status == TRIGGER_VALUE_FALSE && event_last_status == TRIGGER_VALUE_UNKNOWN && event->value == TRIGGER_VALUE_FALSE) ||
				(event_prev_status == TRIGGER_VALUE_UNKNOWN && event_last_status == TRIGGER_VALUE_UNKNOWN && event->value == TRIGGER_VALUE_FALSE)
			)
			{
				zabbix_log(LOG_LEVEL_DEBUG,"Skip actions");
				event->skip_actions = 1;
			}
		case	TRIGGER_TYPE_MULTIPLE_TRUE:
			if(	(event->value == TRIGGER_VALUE_UNKNOWN) ||
/*				(event_prev_status == TRIGGER_VALUE_TRUE && event_last_status == TRIGGER_VALUE_UNKNOWN && event->value == TRIGGER_VALUE_TRUE) ||*/
				(event_prev_status == TRIGGER_VALUE_FALSE && event_last_status == TRIGGER_VALUE_UNKNOWN && event->value == TRIGGER_VALUE_FALSE) ||
				(event_prev_status == TRIGGER_VALUE_UNKNOWN && event_last_status == TRIGGER_VALUE_UNKNOWN && event->value == TRIGGER_VALUE_FALSE)
			)
			{
				zabbix_log(LOG_LEVEL_DEBUG,"Skip actions");
				event->skip_actions = 1;
			}
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: free_trigger_info                                              *
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
 * Comments: Cannot use action->userid as it may also be groupid              *
 *                                                                            *
 ******************************************************************************/
int	process_event(DB_EVENT *event)
{
	zabbix_log(LOG_LEVEL_DEBUG,"In process_event(eventid:" ZBX_FS_UI64 ",object:%d,objectid:" ZBX_FS_UI64 ")",
			event->eventid,
			event->object,
			event->objectid);

	add_trigger_info(event);

	if(event->eventid == 0)
	{
		event->eventid = DBget_maxid("events","eventid");
	}
	DBexecute("insert into events(eventid,source,object,objectid,clock,ms,value) values ("ZBX_FS_UI64",%d,%d,"ZBX_FS_UI64",%d,%d,%d)",
		event->eventid,
		event->source,
		event->object,
		event->objectid,
		event->clock,
		event->ms,
		event->value);

	/* Cancel currently active alerts */
/*	if(event->value == TRIGGER_VALUE_FALSE || event->value == TRIGGER_VALUE_TRUE)
	{
		DBexecute("update alerts set retries=3,error='Trigger changed its status. Will not send repeats.' where triggerid=" ZBX_FS_UI64 " and repeats>0 and status=%d",
			event->triggerid, ALERT_STATUS_NOT_SENT);
	}*/

	if(event->skip_actions == 0)
	{
		process_actions(event);
	}

	if(event->value == TRIGGER_VALUE_TRUE)
	{
		DBupdate_services(event->objectid, event->trigger_priority);
	}
	else
	{
		DBupdate_services(event->objectid, 0);
	}

	free_trigger_info(event);

	zabbix_log(LOG_LEVEL_DEBUG,"End of process_event()");
	
	return SUCCEED;
}
