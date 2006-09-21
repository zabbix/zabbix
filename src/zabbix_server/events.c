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

#include "events.h"

/******************************************************************************
 *                                                                            *
 * Function: get_latest_event_status                                          *
 *                                                                            *
 * Purpose: return status of latest event of the trigger                      *
 *                                                                            *
 * Parameters: triggerid - trigger ID, status - trigger status                *
 *                                                                            *
 * Return value: On SUCCESS, status - status of last event                    *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: Rewrite required to simplify logic ?                             *
 *                                                                            *
 ******************************************************************************/
static int	get_latest_event_status(int triggerid, int *status)
{
	char		sql[MAX_STRING_LEN];
	DB_RESULT	result;
	DB_ROW		row;
	int 		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG,"In latest_event()");

	zbx_snprintf(sql,sizeof(sql),"select value from events where triggerid=%d order by clock desc",triggerid);
	zabbix_log(LOG_LEVEL_DEBUG,"SQL [%s]",sql);
	result = DBselectN(sql,1);
	row = DBfetch(result);

	if(!row || DBis_null(row[0])==SUCCEED)
        {
                zabbix_log(LOG_LEVEL_DEBUG, "Result for last is empty" );
        }
	else
	{
		*status = atoi(row[0]);
		ret = SUCCEED;
	}
	DBfree_result(result);

	return ret;
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
	int	status;

	zabbix_log(LOG_LEVEL_WARNING,"In process_event(" ZBX_FS_UI64 ")",event->eventid);

	/* Latest event has the same status? */
	if(event->eventid ==0 &&
		get_latest_event_status(event->triggerid,&status) == SUCCEED &&
		status == event->value)
	{
		zabbix_log(LOG_LEVEL_DEBUG,"Alarm for triggerid [%d] status [%d] already exists",
				event->triggerid,event->value);
		return FAIL;
	}

	if(event->eventid == 0)
	{
		event->eventid = DBinsert_id(
			DBexecute("insert into events(triggerid,clock,value) values(%d,%d,%d)",
				event->triggerid, event->clock, event->value),
				"events", "eventid");
	}
	else
	{
		DBexecute("insert into events(eventid,triggerid,clock,value) values(" ZBX_FS_UI64 ",%d,%d,%d)",
			event->eventid,event->triggerid, event->clock, event->value);
	}

	/* Cancel currently active alerts */
	if(event->value == TRIGGER_VALUE_FALSE || event->value == TRIGGER_VALUE_TRUE)
	{
		DBexecute("update alerts set retries=3,error='Trigger changed its status. WIll not send repeats.' where triggerid=%d and repeats>0 and status=%d", event->triggerid, ALERT_STATUS_NOT_SENT);
	}

	zabbix_log(LOG_LEVEL_DEBUG,"End of add_event()");
	
	return SUCCEED;
}
