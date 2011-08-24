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
 * Function: get_latest_event_status                                          *
 *                                                                            *
 * Purpose: get identifiers and values of the last two events                 *
 *                                                                            *
 * Parameters: triggerid    - [IN] trigger identifier from database           *
 *             last_eventid - [OUT] last trigger identifier                   *
 *             last_value   - [OUT] last trigger value                        *
 *                                                                            *
 * Author: Alexei Vladishev, Alexander Vladishev                              *
 *                                                                            *
 * Comments: "UNKNOWN" events will be ignored                                 *
 *                                                                            *
 ******************************************************************************/
static void	get_latest_event_status(zbx_uint64_t triggerid,
		zbx_uint64_t *last_eventid, int *last_value)
{
	const char	*__function_name = "get_latest_event_status";
	char		sql[256];
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() triggerid:" ZBX_FS_UI64, __function_name, triggerid);

	/* object and objectid are used for efficient */
	/* sort by the same index as in where condition */
	zbx_snprintf(sql, sizeof(sql),
			"select eventid,value"
			" from events"
			" where source=%d"
				" and object=%d"
				" and objectid=" ZBX_FS_UI64
				" and value in (%d,%d)"
			" order by object desc,objectid desc,eventid desc",
			EVENT_SOURCE_TRIGGERS,
			EVENT_OBJECT_TRIGGER,
			triggerid,
			TRIGGER_VALUE_FALSE, TRIGGER_VALUE_TRUE);

	result = DBselectN(sql, 1);

	if (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(*last_eventid, row[0]);
		*last_value = atoi(row[1]);
	}
	else
	{
		*last_eventid = 0;
		*last_value = TRIGGER_VALUE_UNKNOWN;
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() last_eventid:" ZBX_FS_UI64 " last_value:%d",
			__function_name, *last_eventid, *last_value);
}

/******************************************************************************
 *                                                                            *
 * Function: add_trigger_info                                                 *
 *                                                                            *
 * Purpose: add trigger info to event if required                             *
 *                                                                            *
 * Parameters: event - [IN] event data                                        *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 ******************************************************************************/
static int	add_trigger_info(DB_EVENT *event)
{
	const char	*__function_name = "add_trigger_info";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	last_eventid;
	int		last_value, ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (EVENT_OBJECT_TRIGGER == event->object && 0 != event->objectid)
	{
		result = DBselect("select description,expression,priority,type"
				" from triggers"
				" where triggerid=" ZBX_FS_UI64,
				event->objectid);

		if (NULL != (row = DBfetch(result)))
		{
			event->trigger.triggerid = event->objectid;
			strscpy(event->trigger.description, row[0]);
			strscpy(event->trigger.expression, row[1]);
			event->trigger.priority = (unsigned char)atoi(row[2]);
			event->trigger.type = (unsigned char)atoi(row[3]);
		}
		else
			ret = FAIL;
		DBfree_result(result);

		if (SUCCEED != ret)
			goto fail;

		/* skip actions in next cases:
		 * (1)  -any- / UNKNOWN	(-any-/-any-/UNKNOWN)
		 * (2)  FALSE / FALSE	(FALSE/UNKNOWN/FALSE)
		 * (3) UNKNOWN/ FALSE	(UNKNOWN/UNKNOWN/FALSE)
		 * if event->trigger.type is not TRIGGER_TYPE_MULTIPLE_TRUE:
		 * (4)  TRUE  / TRUE	(TRUE/UNKNOWN/TRUE)
		 */
		if (TRIGGER_VALUE_UNKNOWN == event->value)	/* (1) */
		{
			event->skip_actions = 1;
		}
		else
		{
			get_latest_event_status(event->objectid, &last_eventid, &last_value);

			if (event->value == TRIGGER_VALUE_FALSE &&	/* (2) & (3) */
					(last_value == TRIGGER_VALUE_FALSE || last_value == TRIGGER_VALUE_UNKNOWN))
			{
				event->skip_actions = 1;
			}
			else if (event->trigger.type != TRIGGER_TYPE_MULTIPLE_TRUE &&	/* (4) */
					last_value == TRIGGER_VALUE_TRUE && event->value == TRIGGER_VALUE_TRUE)
			{
				event->skip_actions = 1;
			}

			/* copy acknowledges in next cases:
			 * (1) FALSE/FALSE	(FALSE/UNKNOWN/FALSE)
			 * if event->trigger.type is not TRIGGER_TYPE_MULTIPLE_TRUE:
			 * (2) TRUE /TRUE	(TRUE/UNKNOWN/TRUE)
			 */
			if (last_value == event->value &&
					(last_value == TRIGGER_VALUE_FALSE ||			/* (1) */
					(event->trigger.type != TRIGGER_TYPE_MULTIPLE_TRUE &&	/* (2) */
					last_value == TRIGGER_VALUE_TRUE)))
			{
				event->ack_eventid = last_eventid;
			}
		}

		if (1 == event->skip_actions)
			zabbix_log(LOG_LEVEL_DEBUG, "skip actions");
		if (0 != event->ack_eventid)
			zabbix_log(LOG_LEVEL_DEBUG, "copy acknowledges");
	}
fail:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: copy_acknowledges                                                *
 *                                                                            *
 * Purpose: copy acknowledges from src_eventid to dst_eventid                 *
 *                                                                            *
 * Parameters: src_eventid - [IN] source event identifier from database       *
 *             dst_eventid - [IN] destination event identifier from database  *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
static void	copy_acknowledges(zbx_uint64_t src_eventid, zbx_uint64_t dst_eventid)
{
	const char	*__function_name = "copy_acknowledges";
	zbx_uint64_t	acknowledgeid, *ids = NULL;
	int		ids_alloc = 0, ids_num = 0, i;
	DB_RESULT	result;
	DB_ROW		row;
	char		*sql = NULL;
	int		sql_alloc = 4096, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() src_eventid:" ZBX_FS_UI64
			" dst_eventid:" ZBX_FS_UI64,
			__function_name, src_eventid, dst_eventid);

	result = DBselect("select acknowledgeid"
			" from acknowledges"
			" where eventid=" ZBX_FS_UI64,
			src_eventid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(acknowledgeid, row[0]);
		uint64_array_add(&ids, &ids_alloc, &ids_num, acknowledgeid, 64);
	}
	DBfree_result(result);

	if (NULL == ids)
		goto out;

	sql = zbx_malloc(sql, sql_alloc * sizeof(char));

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "begin\n");
#endif

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 96,
			"update events"
			" set acknowledged=1"
			" where eventid=" ZBX_FS_UI64 ";\n",
			dst_eventid);

	acknowledgeid = DBget_maxid_num("acknowledges", ids_num);

	for (i = 0; i < ids_num; i++, acknowledgeid++)
	{
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 192,
				"insert into acknowledges"
				" (acknowledgeid,userid,eventid,clock,message)"
					" select " ZBX_FS_UI64 ",userid," ZBX_FS_UI64 ",clock,message"
					" from acknowledges"
					" where acknowledgeid=" ZBX_FS_UI64 ";\n",
				acknowledgeid, dst_eventid, ids[i]);
	}

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 8, "end;\n");
#endif

	if (sql_offset > 16)	/* In ORACLE always present begin..end; */
		DBexecute("%s", sql);

	zbx_free(sql);
	zbx_free(ids);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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
int	process_event(zbx_uint64_t eventid, int source, int object, zbx_uint64_t objectid, int clock,
		int value, int acknowledged, int force_actions)
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
	event.clock = clock;
	event.value = value;
	event.acknowledged = acknowledged;

	if (SUCCEED != add_trigger_info(&event))
		goto fail;

	if (0 == event.eventid)
		event.eventid = DBget_maxid("events");

	DBexecute("insert into events (eventid,source,object,objectid,clock,value)"
			" values (" ZBX_FS_UI64 ",%d,%d," ZBX_FS_UI64 ",%d,%d)",
			event.eventid, event.source, event.object, event.objectid, event.clock, event.value);

	if (0 != event.ack_eventid)
		copy_acknowledges(event.ack_eventid, event.eventid);

	if (0 == event.skip_actions || 1 == force_actions)
		process_actions(&event);

	if (EVENT_OBJECT_TRIGGER == event.object)
		DBupdate_services(event.objectid, (TRIGGER_VALUE_TRUE == event.value) ? event.trigger.priority : 0, event.clock);

	ret = SUCCEED;
fail:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
