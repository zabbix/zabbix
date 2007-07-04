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


#include <stdlib.h>
#include <stdio.h>

/* for setproctitle() */
#include <sys/types.h>
#include <unistd.h>

#include <string.h>
#include <strings.h>

#include "zbxdb.h"
#include "db.h"
#include "log.h"
#include "zlog.h"
#include "common.h"
#include "actions.h"
#include "events.h"
#include "threads.h"

void	DBclose(void)
{
	zbx_db_close();
}


/*
 * Connect to the database.
 * If fails, program terminates.
 */ 
void    DBconnect(int flag)
{
	int	err;

	do {
		err = zbx_db_connect(CONFIG_DBHOST, CONFIG_DBUSER, CONFIG_DBPASSWORD, CONFIG_DBNAME, CONFIG_DBSOCKET, CONFIG_DBPORT);

		switch(err) {
			case ZBX_DB_OK:
				break;
			case ZBX_DB_DOWN:
				if(flag == ZBX_DB_CONNECT_EXIT)
				{
					exit(FAIL);
				}
				else
				{
					zabbix_log(LOG_LEVEL_DEBUG, "Database is down. Reconnecting in 10 seconds");
					zbx_sleep(10);
				}
				break;
			default:
				exit(FAIL);
		}
	} while(ZBX_DB_OK != err);
}

/******************************************************************************
 *                                                                            *
 * Function: DBping                                                           *
 *                                                                            *
 * Purpose: Check if database is down                                         *
 *                                                                            *
 * Parameters: -                                                              *
 *                                                                            *
 * Return value: SUCCEED - database is up, FAIL - database is down            *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  * 
 *                                                                            *
 ******************************************************************************/
int	DBping(void)
{
	int ret;

	ret = (ZBX_DB_DOWN == zbx_db_connect(CONFIG_DBHOST, CONFIG_DBUSER, CONFIG_DBPASSWORD, CONFIG_DBNAME, CONFIG_DBSOCKET, CONFIG_DBPORT))? FAIL:SUCCEED;
	DBclose();

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DBbegin                                                          *
 *                                                                            *
 * Purpose: Start transaction                                                 *
 *                                                                            *
 * Parameters: -                                                              *
 *                                                                            *
 * Return value: -                                                            *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: Do nothing of DB does not support transactions                   *
 *                                                                            *
 ******************************************************************************/
void DBbegin(void)
{
	zbx_db_begin();
}

/******************************************************************************
 *                                                                            *
 * Function: DBcommit                                                         *
 *                                                                            *
 * Purpose: Commit transaction                                                *
 *                                                                            *
 * Parameters: -                                                              *
 *                                                                            *
 * Return value: -                                                            *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: Do nothing of DB does not support transactions                   *
 *                                                                            *
 ******************************************************************************/
void DBcommit(void)
{
	zbx_db_commit();
}

/******************************************************************************
 *                                                                            *
 * Function: DBrollback                                                       *
 *                                                                            *
 * Purpose: Rollback transaction                                              *
 *                                                                            *
 * Parameters: -                                                              *
 *                                                                            *
 * Return value: -                                                            *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: Do nothing of DB does not support transactions                   *
 *                                                                            *
 ******************************************************************************/
void DBrollback(void)
{
	zbx_db_rollback();
}

/*
 * Execute SQL statement. For non-select statements only.
 * If fails, program terminates.
 */ 
int __zbx_DBexecute(const char *fmt, ...)
{
	va_list args;
	int ret = ZBX_DB_DOWN;

	while(ret == ZBX_DB_DOWN)
	{
		va_start(args, fmt);
		ret = zbx_db_vexecute(fmt, args);
		va_end(args);
		if( ret == ZBX_DB_DOWN)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Database is down. Retrying in 10 seconds");
			sleep(10);
			DBclose();
			DBconnect(ZBX_DB_CONNECT_NORMAL);
		}
	}

	return ret;
}


int	DBis_null(char *field)
{
	return zbx_db_is_null(field);
}

DB_ROW	DBfetch(DB_RESULT result)
{

	return zbx_db_fetch(result);
}

/*
 * Execute SQL statement. For select statements only.
 * If fails, program terminates.
 */ 
DB_RESULT __zbx_DBselect(const char *fmt, ...)
{
	va_list args;
	DB_RESULT result = (DB_RESULT)ZBX_DB_DOWN;

	while(result == (DB_RESULT)ZBX_DB_DOWN)
	{
		va_start(args, fmt);
		result = zbx_db_vselect(fmt, args);
		va_end(args);
		if( result == (DB_RESULT)ZBX_DB_DOWN)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Database is down. Retrying in 10 seconds");
			sleep(10);
			DBclose();
			DBconnect(ZBX_DB_CONNECT_NORMAL);
		}
	}

	return result;
}

/*
 * Execute SQL statement. For select statements only.
 * If fails, program terminates.
 */ 
DB_RESULT DBselectN(char *query, int n)
{
	return zbx_db_select_n(query,n);
}

/*
 * Get value of autoincrement field for last insert or update statement
 */ 
zbx_uint64_t	DBinsert_id(int exec_result, const char *table, const char *field)
{
	return zbx_db_insert_id(exec_result, table, field);
}

/*
 * Get function value.
 */ 
int     DBget_function_result(char **result,char *functionid)
{
	DB_RESULT dbresult;
	DB_ROW	row;
	int		res = SUCCEED;

/* 0 is added to distinguish between lastvalue==NULL and empty result */
	dbresult = DBselect("select 0,lastvalue from functions where functionid=%s",
		functionid );

	row = DBfetch(dbresult);

	if(!row)
	{
		zabbix_log(LOG_LEVEL_WARNING, "No function for functionid:[%s]",
			functionid );
		zabbix_syslog("No function for functionid:[%s]",
			functionid);
		res = FAIL;
	}
	else if(DBis_null(row[1]) == SUCCEED)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "function.lastvalue==NULL [%s]",
			functionid);
		res = FAIL;
	}
	else
	{
		*result = strdup(row[1]);
	}
        DBfree_result(dbresult);

        return res;
}

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
void	get_latest_event_status(zbx_uint64_t triggerid, int *prev_status, int *latest_status)
{
	char		sql[MAX_STRING_LEN];
	DB_RESULT	result;
	DB_ROW		row;
/*	zbx_uint64_t	eventid_max=0;
	zbx_uint64_t	eventid_prev_max=0;
	zbx_uint64_t	eventid_tmp;
	int		value_max;
	int		value_prev_max;*/


	zabbix_log(LOG_LEVEL_DEBUG,"In get_latest_event_status(triggerid:" ZBX_FS_UI64,
		triggerid);

	zbx_snprintf(sql,sizeof(sql),"select eventid,value,clock from events where source=%d and object=%d and objectid=" ZBX_FS_UI64 " order by clock desc",
		EVENT_SOURCE_TRIGGERS,
		EVENT_OBJECT_TRIGGER,
		triggerid);
	result = DBselectN(sql,2);

	row=DBfetch(result);
	if(row && (DBis_null(row[0])!=SUCCEED))
	{
		*latest_status = atoi(row[1]);
		*prev_status = TRIGGER_VALUE_UNKNOWN;

		row=DBfetch(result);
		if(row && (DBis_null(row[0])!=SUCCEED))
		{
			*prev_status = atoi(row[1]);
		}
	}
	else
	{
		*latest_status = TRIGGER_VALUE_UNKNOWN;
		*prev_status = TRIGGER_VALUE_UNKNOWN;
	}
	DBfree_result(result);

/* I do not remember exactlywhy it was so complex. Rewritten. */

/*
	zbx_snprintf(sql,sizeof(sql),"select eventid,value,clock from events where source=%d and object=%d and objectid=" ZBX_FS_UI64 " order by clock desc",
		EVENT_SOURCE_TRIGGERS,
		EVENT_OBJECT_TRIGGER,
		triggerid);
	result = DBselectN(sql,20);

	while((row=DBfetch(result)))
	{
		ZBX_STR2UINT64(eventid_tmp, row[0]);
		zabbix_log(LOG_LEVEL_WARNING,"eventid_tmp " ZBX_FS_UI64, eventid_tmp);
		if(eventid_tmp >= eventid_max)
		{
			zabbix_log(LOG_LEVEL_WARNING,"New max id " ZBX_FS_UI64, eventid_tmp);
			eventid_prev_max=eventid_max;
			value_prev_max=value_max;
			eventid_max=eventid_tmp;
			value_max=atoi(row[1]);
		}
	}
	
	if(eventid_max == 0)
        {
		zabbix_log(LOG_LEVEL_DEBUG, "Result for last is empty" );
                *prev_status = TRIGGER_VALUE_UNKNOWN;
		*latest_status = TRIGGER_VALUE_UNKNOWN;
        }
	else
	{
		*latest_status = value_max;
                *prev_status = TRIGGER_VALUE_FALSE;

		if(eventid_prev_max != 0)
		{
			*prev_status = value_prev_max;
		}
	}
	DBfree_result(result);
*/
}

/* SUCCEED if latest service alarm has this status */
/* Rewrite required to simplify logic ?*/
int	latest_service_alarm(zbx_uint64_t serviceid, int status)
{
	int	clock;
	DB_RESULT	result;
	DB_ROW		row;
	int ret = FAIL;


	zabbix_log(LOG_LEVEL_DEBUG,"In latest_service_alarm()");

	result = DBselect("select max(clock) from service_alarms where serviceid=" ZBX_FS_UI64,
		serviceid);
	row = DBfetch(result);

	if(!row || DBis_null(row[0])==SUCCEED)
        {
                zabbix_log(LOG_LEVEL_DEBUG, "Result for MAX is empty" );
                ret = FAIL;
        }
	else
	{
		clock=atoi(row[0]);
		DBfree_result(result);

		result = DBselect("select value from service_alarms where serviceid=" ZBX_FS_UI64 " and clock=%d",
			serviceid,
			clock);
		row = DBfetch(result);
		if(row && DBis_null(row[0]) != SUCCEED)
		{
			if(atoi(row[0]) == status)
			{
				ret = SUCCEED;
			}
		}
	}

	DBfree_result(result);

	return ret;
}

int	DBadd_service_alarm(zbx_uint64_t serviceid,int status,int clock)
{
	zabbix_log(LOG_LEVEL_DEBUG,"In add_service_alarm()");

	if(latest_service_alarm(serviceid,status) == SUCCEED)
	{
		return SUCCEED;
	}

	DBexecute("insert into service_alarms(servicealarmid,serviceid,clock,value) values(" ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,%d)",
		DBget_maxid("service_alarms","servicealarmid"),
		serviceid,
		clock,
		status);

	zabbix_log(LOG_LEVEL_DEBUG,"End of add_service_alarm()");
	
	return SUCCEED;
}

int	DBupdate_trigger_value(DB_TRIGGER *trigger, int new_value, int now, char *reason)
{
	int	ret = SUCCEED;
	DB_EVENT	event;
	int		event_last_status;
	int		event_prev_status;

	if(reason==NULL)
	{
		zabbix_log(LOG_LEVEL_DEBUG,"In update_trigger_value(triggerid:" ZBX_FS_UI64 ",old:%d,new:%d,%d)",
			trigger->triggerid,
			trigger->value,
			new_value,
			now);
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG,"In update_trigger_value(triggerid:" ZBX_FS_UI64 ",old:%d,new:%d,%d,%s)",
			trigger->triggerid,
			trigger->value,
			new_value,
			now,
			reason);
	}

	/* New trigger value differs from current one */
	if(trigger->value != new_value)
	{

		get_latest_event_status(trigger->triggerid, &event_prev_status, &event_last_status);

		zabbix_log(LOG_LEVEL_DEBUG,"tr value [%d] event_prev_value [%d] event_last_status [%d] new_value [%d]",
				trigger->value,
				event_prev_status,
				event_last_status,
				new_value);

		/* New trigger status is NOT equal to previous one, update trigger */
		if(trigger->value != new_value)
		{
			zabbix_log(LOG_LEVEL_DEBUG,"Updating trigger");
			if(reason==NULL)
			{
				DBexecute("update triggers set value=%d,lastchange=%d,error='' where triggerid=" ZBX_FS_UI64,
					new_value,
					now,
					trigger->triggerid);
			}
			else
			{
				DBexecute("update triggers set value=%d,lastchange=%d,error='%s' where triggerid=" ZBX_FS_UI64,
					new_value,
					now,
					reason,
					trigger->triggerid);
			}
		}

		/* The lastest event has the same status, do not generate new one */
		if(event_last_status != new_value)
		{
/*			if(	((trigger->value == TRIGGER_VALUE_TRUE) && (new_value == TRIGGER_VALUE_FALSE)) ||
				((trigger->value == TRIGGER_VALUE_FALSE) && (new_value == TRIGGER_VALUE_TRUE)) ||
				((event_last_status == TRIGGER_VALUE_FALSE) && (trigger->value == TRIGGER_VALUE_UNKNOWN) && (new_value == TRIGGER_VALUE_TRUE)) ||
				((event_last_status == TRIGGER_VALUE_TRUE) && (trigger->value == TRIGGER_VALUE_UNKNOWN) && (new_value == TRIGGER_VALUE_FALSE)) ||
				((event_prev_status == TRIGGER_VALUE_UNKNOWN) && (event_last_status == TRIGGER_VALUE_UNKNOWN) && (trigger->value == TRIGGER_VALUE_UNKNOWN) && (new_value == TRIGGER_VALUE_TRUE)) ||
				((event_prev_status == TRIGGER_VALUE_FALSE) && (event_last_status == TRIGGER_VALUE_UNKNOWN) && (trigger->value == TRIGGER_VALUE_UNKNOWN) && (new_value == TRIGGER_VALUE_TRUE)) ||
				((event_prev_status == TRIGGER_VALUE_TRUE) && (event_last_status == TRIGGER_VALUE_UNKNOWN) && (trigger->value == TRIGGER_VALUE_UNKNOWN) && (new_value == TRIGGER_VALUE_FALSE))
			)*/

			/* Generate also UNKNOWN events, We are not interested in prev trigger value here. */
			if(event_last_status != new_value)
/*			if(	((event_last_status == TRIGGER_VALUE_FALSE) && (new_value != TRIGGER_VALUE_FALSE)) ||
				((event_last_status == TRIGGER_VALUE_TRUE) && (new_value != TRIGGER_VALUE_TRUE)) ||
				((event_prev_status == TRIGGER_VALUE_UNKNOWN) && (event_last_status == TRIGGER_VALUE_UNKNOWN) && (new_value != TRIGGER_VALUE_UNKNOWN)) ||
				((event_prev_status == TRIGGER_VALUE_FALSE) && (event_last_status == TRIGGER_VALUE_UNKNOWN) &&(new_value == TRIGGER_VALUE_TRUE)) ||
				((event_prev_status == TRIGGER_VALUE_TRUE) && (event_last_status == TRIGGER_VALUE_UNKNOWN) && (trigger->value == TRIGGER_VALUE_UNKNOWN) && (new_value == TRIGGER_VALUE_FALSE))
			)*/
			{
				/* Preparing event for processing */
				memset(&event,0,sizeof(DB_EVENT));
				event.eventid = 0;
				event.source = EVENT_SOURCE_TRIGGERS;
				event.object = EVENT_OBJECT_TRIGGER;
				event.objectid = trigger->triggerid;
				event.clock = now;
				event.value = new_value;
				event.acknowledged = 0;

				/* Processing event */
				if(process_event(&event) == SUCCEED)
				{
					zabbix_log(LOG_LEVEL_DEBUG,"Event processed OK");
				}
				else
				{
					zabbix_log(LOG_LEVEL_WARNING,"Event processed not OK");
				}
			}
		}
		else
		{
			zabbix_log(LOG_LEVEL_DEBUG,"Event not added for triggerid [" ZBX_FS_UI64 "]",
				trigger->triggerid);
			ret = FAIL;
		}
	}
	zabbix_log(LOG_LEVEL_DEBUG,"End update_trigger_value()");
	return ret;
}

void update_triggers_status_to_unknown(zbx_uint64_t hostid,int clock,char *reason)
{
	DB_RESULT	result;
	DB_ROW		row;
	DB_TRIGGER	trigger;

	zabbix_log(LOG_LEVEL_DEBUG,"In update_triggers_status_to_unknown()");

	result = DBselect("select distinct t.triggerid,t.expression,t.description,t.status,t.priority,t.value,t.url,t.comments from hosts h,items i,triggers t,functions f where f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid and h.hostid=" ZBX_FS_UI64 " and i.key_ not in ('%s','%s','%s')",
		hostid,
		SERVER_STATUS_KEY,
		SERVER_ICMPPING_KEY,
		SERVER_ICMPPINGSEC_KEY);

	while((row=DBfetch(result)))
	{
		ZBX_STR2UINT64(trigger.triggerid,row[0]);
		strscpy(trigger.expression,row[1]);
		strscpy(trigger.description,row[2]);
		trigger.status		= atoi(row[3]);
		trigger.priority	= atoi(row[4]);
		trigger.value		= atoi(row[5]);
		trigger.url		= row[6];
		trigger.comments	= row[7];
		DBupdate_trigger_value(&trigger,TRIGGER_VALUE_UNKNOWN,clock,reason);
	}

	DBfree_result(result);
	zabbix_log(LOG_LEVEL_DEBUG,"End of update_triggers_status_to_unknown()");

	return; 
}

void  DBdelete_service(zbx_uint64_t serviceid)
{
	DBexecute("delete from services_links where servicedownid=" ZBX_FS_UI64 " or serviceupid=" ZBX_FS_UI64,
		serviceid,
		serviceid);
	DBexecute("delete from services where serviceid=" ZBX_FS_UI64,
		serviceid);
}

void  DBdelete_services_by_triggerid(zbx_uint64_t triggerid)
{
	zbx_uint64_t	serviceid;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBdelete_services_by_triggerid(" ZBX_FS_UI64 ")",
		triggerid);
	result = DBselect("select serviceid from services where triggerid=" ZBX_FS_UI64,
		triggerid);

	while((row=DBfetch(result)))
	{
/*		serviceid=atoi(row[0]);*/
		ZBX_STR2UINT64(serviceid, row[0]);
		DBdelete_service(serviceid);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG,"End of DBdelete_services_by_triggerid(" ZBX_FS_UI64 ")",
		triggerid);
}

void  DBdelete_trigger(zbx_uint64_t triggerid)
{
	DBexecute("delete from trigger_depends where triggerid_down=" ZBX_FS_UI64 " or triggerid_up=" ZBX_FS_UI64,
		triggerid,
		triggerid);
	DBexecute("delete from functions where triggerid=" ZBX_FS_UI64,
		triggerid);
	DBexecute("delete from events where triggerid=" ZBX_FS_UI64,
		triggerid);
/*	zbx_snprintf(sql,sizeof(sql),"delete from actions where triggerid=%d and scope=%d", triggerid, ACTION_SCOPE_TRIGGER);
	DBexecute(sql);*/

	DBdelete_services_by_triggerid(triggerid);

	DBexecute("update sysmaps_links set triggerid=NULL where triggerid=" ZBX_FS_UI64,
		triggerid);
	DBexecute("delete from triggers where triggerid=" ZBX_FS_UI64,
		triggerid);
}

void  DBdelete_triggers_by_itemid(zbx_uint64_t itemid)
{
	zbx_uint64_t	triggerid;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBdelete_triggers_by_itemid(" ZBX_FS_UI64 ")",
		itemid);
	result = DBselect("select triggerid from functions where itemid=" ZBX_FS_UI64,
		itemid);

	while((row=DBfetch(result)))
	{
		ZBX_STR2UINT64(triggerid, row[0]);
/*		triggerid=atoi(row[0]);*/
		DBdelete_trigger(triggerid);
	}
	DBfree_result(result);

	DBexecute("delete from functions where itemid=" ZBX_FS_UI64,
		itemid);

	zabbix_log(LOG_LEVEL_DEBUG,"End of DBdelete_triggers_by_itemid(" ZBX_FS_UI64 ")",
		itemid);
}

void DBdelete_trends_by_itemid(zbx_uint64_t itemid)
{
	DBexecute("delete from trends where itemid=" ZBX_FS_UI64,
		itemid);
}

void DBdelete_history_by_itemid(zbx_uint64_t itemid)
{
	DBexecute("delete from history where itemid=" ZBX_FS_UI64,
		itemid);
	DBexecute("delete from history_str where itemid=" ZBX_FS_UI64,
		itemid);
}

void DBdelete_sysmaps_links_by_shostid(zbx_uint64_t shostid)
{
	DBexecute("delete from sysmaps_links where shostid1=" ZBX_FS_UI64 " or shostid2=" ZBX_FS_UI64,
		shostid, shostid);
}

void DBdelete_sysmaps_hosts_by_hostid(zbx_uint64_t hostid)
{
	zbx_uint64_t	shostid;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBdelete_sysmaps_hosts(" ZBX_FS_UI64 ")",
		hostid);
	result = DBselect("select shostid from sysmaps_elements where elementid=" ZBX_FS_UI64,
		hostid);

	while((row=DBfetch(result)))
	{
		ZBX_STR2UINT64(shostid, row[0]);
/*		shostid=atoi(row[0]);*/
		DBdelete_sysmaps_links_by_shostid(shostid);
	}
	DBfree_result(result);

	DBexecute("delete from sysmaps_elements where elementid=" ZBX_FS_UI64,
		hostid);
}

void DBupdate_triggers_status_after_restart(void)
{
	int	lastchange;
	int	now;

	DB_RESULT	result;
	DB_RESULT	result2;
	DB_ROW	row;
	DB_ROW	row2;
	DB_TRIGGER	trigger;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBupdate_triggers_after_restart()");

	now=time(NULL);

	result = DBselect("select distinct t.triggerid,t.expression,t.description,t.status,t.priority,t.value,t.url,t.comments from hosts h,items i,triggers t,functions f where f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid and i.nextcheck+i.delay<%d and i.key_<>'%s' and h.status not in (%d,%d) and i.type not in (%d)",
		now,
		SERVER_STATUS_KEY,
		HOST_STATUS_DELETED,
		HOST_STATUS_TEMPLATE,
		ITEM_TYPE_TRAPPER);

	while((row=DBfetch(result)))
	{
		ZBX_STR2UINT64(trigger.triggerid,row[0]);
		strscpy(trigger.expression,row[1]);
		strscpy(trigger.description,row[2]);
		trigger.status		= atoi(row[3]);
		trigger.priority	= atoi(row[4]);
		trigger.value		= atoi(row[5]);
		trigger.url		= row[6];
		trigger.comments	= row[7];

		result2 = DBselect("select min(i.nextcheck+i.delay) from hosts h,items i,triggers t,functions f where f.triggerid=t.triggerid and f.itemid=i.itemid and h.hostid=i.hostid and i.nextcheck<>0 and t.triggerid=" ZBX_FS_UI64 " and i.type not in (%d)",
			trigger.triggerid,
			ITEM_TYPE_TRAPPER);
		row2=DBfetch(result2);
		if(!row2 || DBis_null(row2[0])==SUCCEED)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "No triggers to update (2)");
			DBfree_result(result2);
			continue;
		}

		lastchange=atoi(row2[0]);
		DBfree_result(result2);

		DBupdate_trigger_value(&trigger,TRIGGER_VALUE_UNKNOWN,lastchange,"ZABBIX was down.");
	}

	DBfree_result(result);
	zabbix_log(LOG_LEVEL_DEBUG,"End of DBupdate_triggers_after_restart()");

	return; 
}

void DBupdate_host_availability(zbx_uint64_t hostid,int available,int clock, char *error)
{
	DB_RESULT	result;
	DB_ROW		row;
	char	error_esc[MAX_STRING_LEN];
	int	disable_until;

	zabbix_log(LOG_LEVEL_DEBUG,"In update_host_availability()");

	if(error!=NULL)
	{
		DBescape_string(error,error_esc,MAX_STRING_LEN);
	}
	else
	{
		strscpy(error_esc,"");
	}

	result = DBselect("select available,disable_until from hosts where hostid=" ZBX_FS_UI64,
		hostid);
	row=DBfetch(result);

	if(!row)
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot select host with hostid [" ZBX_FS_UI64 "]",
			hostid);
		zabbix_syslog("Cannot select host with hostid [" ZBX_FS_UI64 "]",
			hostid);
		DBfree_result(result);
		return;
	}

	disable_until = atoi(row[1]);

	if(available == atoi(row[0]))
	{
/*		if((available==HOST_AVAILABLE_FALSE) 
		&&(clock+CONFIG_UNREACHABLE_PERIOD>disable_until) )
		{
		}
		else
		{*/
			zabbix_log(LOG_LEVEL_DEBUG, "Host already has availability [%d]",
				available);
			DBfree_result(result);
			return;
/*		}*/
	}

	DBfree_result(result);

	if(available==HOST_AVAILABLE_TRUE)
	{
		DBexecute("update hosts set available=%d,error=' ',errors_from=0 where hostid=" ZBX_FS_UI64,
			HOST_AVAILABLE_TRUE,
			hostid);
	}
	else if(available==HOST_AVAILABLE_FALSE)
	{
/*		if(disable_until+CONFIG_UNREACHABLE_PERIOD>clock)
		{
			zbx_snprintf(sql,sizeof(sql),"update hosts set available=%d,disable_until=disable_until+%d,error='%s' where hostid=%d",HOST_AVAILABLE_FALSE,CONFIG_UNREACHABLE_DELAY,error_esc,hostid);
		}
		else
		{
			zbx_snprintf(sql,sizeof(sql),"update hosts set available=%d,disable_until=%d,error='%s' where hostid=%d",HOST_AVAILABLE_FALSE,clock+CONFIG_UNREACHABLE_DELAY,error_esc,hostid);
		}*/
		/* '%s ' - space to make Oracle happy */
		DBexecute("update hosts set available=%d,error='%s ' where hostid=" ZBX_FS_UI64,
			HOST_AVAILABLE_FALSE,
			error_esc,
			hostid);
	}
	else
	{
		zabbix_log( LOG_LEVEL_ERR, "Unknown host availability [%d] for hostid [" ZBX_FS_UI64 "]",
			available,
			hostid);
		zabbix_syslog("Unknown host availability [%d] for hostid [" ZBX_FS_UI64 "]",
			available,
			hostid);
		return;
	}

	update_triggers_status_to_unknown(hostid,clock,"Host is unavailable.");
	zabbix_log(LOG_LEVEL_DEBUG,"End of update_host_availability()");

	return;
}

int	DBupdate_item_status_to_notsupported(zbx_uint64_t itemid, char *error)
{
	char	error_esc[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG,"In DBupdate_item_status_to_notsupported()");

	if(error!=NULL)
	{
		DBescape_string(error,error_esc,MAX_STRING_LEN);
	}
	else
	{
		strscpy(error_esc,"");
	}

	/* '%s ' to make Oracle happy */
	DBexecute("update items set status=%d,error='%s ' where itemid=" ZBX_FS_UI64,
		ITEM_STATUS_NOTSUPPORTED,
		error_esc,
		itemid);

	return SUCCEED;
}

int	DBadd_trend(zbx_uint64_t itemid, double value, int clock)
{
	DB_RESULT	result;
	DB_ROW		row;
	int	hour;
	int	num;
	double	value_min, value_avg, value_max;	

	zabbix_log(LOG_LEVEL_DEBUG,"In add_trend()");

	hour=clock-clock%3600;

	result = DBselect("select num,value_min,value_avg,value_max from trends where itemid=" ZBX_FS_UI64 " and clock=%d",
		itemid,
		hour);

	row=DBfetch(result);

	if(row)
	{
		num=atoi(row[0]);
		value_min=atof(row[1]);
		value_avg=atof(row[2]);
		value_max=atof(row[3]);
		if(value<value_min)	value_min=value;
/* Unfortunate mistake... */
/*		if(value>value_avg)	value_max=value;*/
		if(value>value_max)	value_max=value;
		value_avg=(num*value_avg+value)/(num+1);
		num++;
		DBexecute("update trends set num=%d, value_min=" ZBX_FS_DBL ", value_avg=" ZBX_FS_DBL ", value_max=" ZBX_FS_DBL " where itemid=" ZBX_FS_UI64 " and clock=%d",
			num,
			value_min,
			value_avg,
			value_max,
			itemid,
			hour);
	}
	else
	{
		DBexecute("insert into trends (clock,itemid,num,value_min,value_avg,value_max) values (%d," ZBX_FS_UI64 ",%d," ZBX_FS_DBL "," ZBX_FS_DBL "," ZBX_FS_DBL ")",
			hour,
			itemid,
			1,
			value,
			value,
			value);
	}

	DBfree_result(result);

	return SUCCEED;
}

int	DBadd_history(zbx_uint64_t itemid, double value, int clock)
{
	zabbix_log(LOG_LEVEL_DEBUG,"In add_history()");

	DBexecute("insert into history (clock,itemid,value) values (%d," ZBX_FS_UI64 "," ZBX_FS_DBL ")",
		clock,
		itemid,
		value);

	DBadd_trend(itemid, value, clock);

	if(CONFIG_MASTER_NODEID>0)
	{
		DBexecute("insert into history_sync (nodeid,clock,itemid,value) values (%d,%d," ZBX_FS_UI64 "," ZBX_FS_DBL ")",
			get_nodeid_by_id(itemid),
			clock,
			itemid,
			value);
	}

	return SUCCEED;
}

int	DBadd_history_uint(zbx_uint64_t itemid, zbx_uint64_t value, int clock)
{
	zabbix_log(LOG_LEVEL_DEBUG,"In add_history_uint()");

	DBexecute("insert into history_uint (clock,itemid,value) values (%d," ZBX_FS_UI64 "," ZBX_FS_UI64 ")",
		clock,
		itemid,
		value);

	DBadd_trend(itemid, (double)value, clock);

	if(CONFIG_MASTER_NODEID>0)
	{
		DBexecute("insert into history_uint_sync (nodeid,clock,itemid,value) values (%d,%d," ZBX_FS_UI64 "," ZBX_FS_UI64 ")",
			get_nodeid_by_id(itemid),
			clock,
			itemid,
			value);
	}

	return SUCCEED;
}

int	DBadd_history_str(zbx_uint64_t itemid, char *value, int clock)
{
	char	value_esc[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG,"In add_history_str()");

	DBescape_string(value,value_esc,MAX_STRING_LEN);
	DBexecute("insert into history_str (clock,itemid,value) values (%d," ZBX_FS_UI64 ",'%s')",
		clock,
		itemid,
		value_esc);

	if(CONFIG_MASTER_NODEID>0)
	{
		DBexecute("insert into history_str_sync (nodeid,clock,itemid,value) values (%d,%d," ZBX_FS_UI64 ",'%s')",
			get_nodeid_by_id(itemid),
			clock,
			itemid,
			value_esc);
	}

	return SUCCEED;
}

int	DBadd_history_text(zbx_uint64_t itemid, char *value, int clock)
{
#ifdef HAVE_ORACLE
	char		sql[MAX_STRING_LEN];
	char		*value_esc = NULL;
	int		value_esc_max_len = 0;
	int		ret = FAIL;
	zbx_uint64_t	id;

	sqlo_lob_desc_t		loblp;		/* the lob locator */
	sqlo_stmt_handle_t	sth;

	sqlo_autocommit_off(oracle);

	zabbix_log(LOG_LEVEL_DEBUG,"In add_history_text()");

	value_esc_max_len = strlen(value)+1024;
	value_esc = zbx_malloc(value_esc, value_esc_max_len);

	DBescape_string(value, value_esc, value_esc_max_len-1);
	value_esc_max_len = strlen(value_esc);

	/* alloate the lob descriptor */
	if(sqlo_alloc_lob_desc(oracle, &loblp) < 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG,"CLOB allocating failed:%s", sqlo_geterror(oracle));
		goto lbl_exit;
	}

	id = DBget_maxid("history_log", "id");
	zbx_snprintf(sql, sizeof(sql), "insert into history_text (id,clock,itemid,value)"
		" values (" ZBX_FS_UI64 ",%d," ZBX_FS_UI64 ", EMPTY_CLOB()) returning value into :1",
		id,
		clock,
		itemid);

	zabbix_log(LOG_LEVEL_DEBUG,"Query:%s", sql);

	/* parse the statement */
	sth = sqlo_prepare(oracle, sql);
	if(sth < 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG,"Query prepearing failed:%s", sqlo_geterror(oracle));
		goto lbl_exit;
	}

	/* bind input variables. Note: we bind the lob descriptor here */
	if(SQLO_SUCCESS != sqlo_bind_by_pos(sth, 1, SQLOT_CLOB, &loblp, 0, NULL, 0))
	{
		zabbix_log(LOG_LEVEL_DEBUG,"CLOB binding failed:%s", sqlo_geterror(oracle));
		goto lbl_exit_loblp;
	}

	/* execute the statement */
	if(sqlo_execute(sth, 1) != SQLO_SUCCESS)
	{
		zabbix_log(LOG_LEVEL_DEBUG,"Query failed:%s", sqlo_geterror(oracle));
		goto lbl_exit_loblp;
	}

	/* write the lob */
	ret = sqlo_lob_write_buffer(oracle, loblp, value_esc_max_len, value_esc, value_esc_max_len, SQLO_ONE_PIECE);
	if(ret < 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG,"CLOB writing failed:%s", sqlo_geterror(oracle) );
		goto lbl_exit_loblp;
	}

	/* commiting */
	if(sqlo_commit(oracle) < 0)
	{
		zabbix_log(LOG_LEVEL_DEBUG,"Commiting failed:%s", sqlo_geterror(oracle) );
	}

	ret = SUCCEED;

lbl_exit_loblp:
	sqlo_free_lob_desc(oracle, &loblp);

lbl_exit:
	if(sth >= 0)	sqlo_close(sth);
	zbx_free(value_esc);

	sqlo_autocommit_on(oracle);

	return ret;

#else /* HAVE_ORACLE */

	char		*value_esc = NULL;
	int		value_esc_max_len = 0;
	int		sql_max_len = 0;
	zbx_uint64_t	id;

	zabbix_log(LOG_LEVEL_DEBUG,"In add_history_str()");

	value_esc_max_len = strlen(value)+1024;
	value_esc = zbx_malloc(value_esc, value_esc_max_len);

	sql_max_len = value_esc_max_len+100;

	DBescape_string(value,value_esc,value_esc_max_len);
	id = DBget_maxid("history_text", "id");
	DBexecute("insert into history_text (id,clock,itemid,value) values (" ZBX_FS_UI64 ",%d," ZBX_FS_UI64 ",'%s')",
		id,
		clock,
		itemid,
		value_esc);

	zbx_free(value_esc);

	return SUCCEED;

#endif
}


int	DBadd_history_log(zbx_uint64_t itemid, char *value, int clock, int timestamp,char *source, int severity)
{
	char		value_esc[MAX_STRING_LEN];
	char		source_esc[MAX_STRING_LEN];
	zbx_uint64_t	id;

	zabbix_log(LOG_LEVEL_DEBUG,"In add_history_log()");

	DBescape_string(value,value_esc,MAX_STRING_LEN);
	DBescape_string(source,source_esc,MAX_STRING_LEN);
	id = DBget_maxid("history_log", "id");
	DBexecute("insert into history_log (id,clock,itemid,timestamp,value,source,severity) values (" ZBX_FS_UI64 ",%d," ZBX_FS_UI64 ",%d,'%s','%s',%d)",
		id,
		clock,
		itemid,
		timestamp,
		value_esc,
		source_esc,
		severity);

	return SUCCEED;
}


int	DBget_items_count(void)
{
	int	res;
	char	sql[MAX_STRING_LEN];
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBget_items_count()");

	result = DBselect("select count(*) from items");

	row=DBfetch(result);

	if(!row || DBis_null(row[0])==SUCCEED)
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot execute query [%s]", sql);
		zabbix_syslog("Cannot execute query [%s]", sql);
		DBfree_result(result);
		return 0;
	}

	res  = atoi(row[0]);

	DBfree_result(result);

	return res;
}

int	DBget_triggers_count(void)
{
	int	res;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBget_triggers_count()");

	result = DBselect("select count(*) from triggers");

	row=DBfetch(result);

	if(!row || DBis_null(row[0])==SUCCEED)
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot execute query");
		zabbix_syslog("Cannot execute query");
		DBfree_result(result);
		return 0;
	}

	res  = atoi(row[0]);

	DBfree_result(result);

	return res;
}

int	DBget_items_unsupported_count(void)
{
	int	res;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBget_items_unsupported_count()");

	result = DBselect("select count(*) from items where status=%d", ITEM_STATUS_NOTSUPPORTED);

	row=DBfetch(result);

	if(!row || DBis_null(row[0])==SUCCEED)
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot execute query");
		zabbix_syslog("Cannot execute query");
		DBfree_result(result);
		return 0;
	}

	res  = atoi(row[0]);

	DBfree_result(result);

	return res;
}

int	DBget_history_str_count(void)
{
	int	res;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBget_history_str_count()");

	result = DBselect("select count(*) from history_str");

	row=DBfetch(result);

	if(!row || DBis_null(row[0])==SUCCEED)
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot execute query");
		zabbix_syslog("Cannot execute query");
		DBfree_result(result);
		return 0;
	}

	res  = atoi(row[0]);

	DBfree_result(result);

	return res;
}

int	DBget_history_count(void)
{
	int	res;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBget_history_count()");

	result = DBselect("select count(*) from history");

	row=DBfetch(result);

	if(!row || DBis_null(row[0])==SUCCEED)
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot execute query");
		zabbix_syslog("Cannot execute query");
		DBfree_result(result);
		return 0;
	}

	res  = atoi(row[0]);

	DBfree_result(result);

	return res;
}

int	DBget_trends_count(void)
{
	int	res;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBget_trends_count()");

	result = DBselect("select count(*) from trends");

	row=DBfetch(result);

	if(!row || DBis_null(row[0])==SUCCEED)
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot execute query");
		zabbix_syslog("Cannot execute query");
		DBfree_result(result);
		return 0;
	}

	res  = atoi(row[0]);

	DBfree_result(result);

	return res;
}

int	DBget_queue_count(void)
{
	int	res;
	DB_RESULT	result;
	DB_ROW		row;
	int	now;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBget_queue_count()");

	now=time(NULL);
/*	zbx_snprintf(sql,sizeof(sql),"select count(*) from items i,hosts h where i.status=%d and i.type not in (%d) and h.status=%d and i.hostid=h.hostid and i.nextcheck<%d and i.key_<>'status'", ITEM_STATUS_ACTIVE, ITEM_TYPE_TRAPPER, HOST_STATUS_MONITORED, now);*/
	result = DBselect("select count(*) from items i,hosts h where i.status=%d and i.type not in (%d) and ((h.status=%d and h.available!=%d) or (h.status=%d and h.available=%d and h.disable_until<=%d)) and i.hostid=h.hostid and i.nextcheck<%d and i.key_ not in ('%s','%s','%s','%s')",
		ITEM_STATUS_ACTIVE,
		ITEM_TYPE_TRAPPER,
		HOST_STATUS_MONITORED,
		HOST_AVAILABLE_FALSE,
		HOST_STATUS_MONITORED,
		HOST_AVAILABLE_FALSE,
		now,
		now,
		SERVER_STATUS_KEY,
		SERVER_ICMPPING_KEY,
		SERVER_ICMPPINGSEC_KEY,
		SERVER_ZABBIXLOG_KEY);

	row=DBfetch(result);

	if(!row || DBis_null(row[0])==SUCCEED)
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot execute query");
		zabbix_syslog("Cannot execute query");
		DBfree_result(result);
		return 0;
	}

	res  = atoi(row[0]);

	DBfree_result(result);

	return res;
}

int	DBadd_alert(zbx_uint64_t actionid, zbx_uint64_t userid, zbx_uint64_t triggerid,  zbx_uint64_t mediatypeid, char *sendto, char *subject, char *message)
{
	int	now;
	
	char	*sendto_esc	= NULL;
	char	*subject_esc	= NULL;
	char	*message_esc	= NULL;
	
	int	size;

	zabbix_log(LOG_LEVEL_DEBUG,"In add_alert(triggerid[%d])",triggerid);

	now = time(NULL);

	size = strlen(sendto) * 3 / 2 + 1;
	sendto_esc = zbx_malloc(sendto_esc, size);
	memset(sendto_esc, 0, size);
	DBescape_string(sendto, sendto_esc, size);

	size = strlen(subject) * 3 / 2 + 1;
	subject_esc = zbx_malloc(subject_esc, size);
	memset(subject_esc, 0, size);
	DBescape_string(subject,subject_esc,size);
	
	size = strlen(message) * 3 / 2 + 1;
	message_esc = zbx_malloc(message_esc,size);
	memset(message_esc, 0, size);
	DBescape_string(message,message_esc,size);
	
	DBexecute("insert into alerts (alertid, actionid,triggerid,userid,clock,mediatypeid,sendto,subject,message,status,retries)"
		" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d," ZBX_FS_UI64 ",'%s','%s','%s',0,0)",
		DBget_maxid("alerts","alertid"),
		actionid,
		triggerid,
		userid,
		now,
		mediatypeid,
		sendto_esc,
		subject_esc,
		message_esc);

	zbx_free(sendto_esc);
	zbx_free(subject_esc);
	zbx_free(message_esc);

	return SUCCEED;
}

void	DBvacuum(void)
{
#ifdef	HAVE_POSTGRESQL
	char *table_for_housekeeping[]={"services", "services_links", "graphs_items", "graphs", "sysmaps_links",
			"sysmaps_elements", "sysmaps", "config", "groups", "hosts_groups", "alerts",
			"actions", "events", "functions", "history", "history_str", "hosts", "trends",
			"items", "media", "media_type", "triggers", "trigger_depends", "users",
			"sessions", "rights", "service_alarms", "profiles", "screens", "screens_items",
			NULL};

	char	*table;
	int	i;

	zbx_setproctitle("housekeeper [vacuum DB]");

	i=0;
	while (NULL != (table = table_for_housekeeping[i++]))
	{
		DBexecute("vacuum analyze %s", table);
	}
#endif

#ifdef	HAVE_MYSQL
	/* Nothing to do */
#endif
}

void    DBescape_string(const char *str, char *to, int maxlen)
{  /* NOTE: sync changes with 'DBdyn_escape_string' */
	register int     i=0, ptr=0;
#ifdef  HAVE_ORACLE
#	define ZBX_DB_ESC_CH	'\''
#else /* not HAVE_ORACLE */
#	define ZBX_DB_ESC_CH	'\\'
#endif /* HAVE_ORACLE */
	assert(to);

	maxlen--;
	for( i=0,ptr=0; str && str[i] && ptr < maxlen; i++)
	{
		if( str[i] == '\r' ) continue;

		if(	( str[i] == '\'' ) 
#ifndef	HAVE_ORACLE
			|| ( str[i] == '\\' )
#endif /* not HAVE_ORACLE */
		)
		{
			to[ptr++] = ZBX_DB_ESC_CH;
			if(ptr >= maxlen)       break;
		}
		to[ptr++] = str[i];
	}
	to[ptr] = '\0';
}

char*	DBdyn_escape_string(const char *str)
{  /* NOTE: sync changes with 'DBescape_string' */
	register int i;

	char *str_esc = NULL;

	int	str_esc_len = 0;

	for(i=0; str && str[i]; i++)
	{
		str_esc_len++;
		if(	( str[i] == '\'' ) 
#ifndef	HAVE_ORACLE
			|| ( str[i] == '\\' )
#endif /* not HAVE_ORACLE */
		)
		{
			str_esc_len++;
		}
	}
	str_esc_len++;

	str_esc = zbx_malloc(str_esc, str_esc_len);

	DBescape_string(str, str_esc, str_esc_len);

	return str_esc;
}

void	DBget_item_from_db(DB_ITEM *item,DB_ROW row)
{
	char	*s;

	ZBX_STR2UINT64(item->itemid, row[0]);
/*	item->itemid=atoi(row[0]); */
	zbx_snprintf(item->key, ITEM_KEY_LEN, "%s", row[1]);
	item->host_name=row[2];
	item->port=atoi(row[3]);
	item->delay=atoi(row[4]);
	item->description=row[5];
	item->nextcheck=atoi(row[6]);
	item->type=atoi(row[7]);
	item->snmp_community=row[8];
	item->snmp_oid=row[9];
	item->useip=atoi(row[10]);
	item->host_ip=row[11];
	item->history=atoi(row[12]);
	item->value_type=atoi(row[17]);

	s=row[13];
	if(DBis_null(s)==SUCCEED)
	{
		item->lastvalue_null=1;
	}
	else
	{
		item->lastvalue_null=0;
		switch(item->value_type) {
			case ITEM_VALUE_TYPE_FLOAT:
				item->lastvalue_dbl=atof(s);
				break;
			case ITEM_VALUE_TYPE_UINT64:
				ZBX_STR2UINT64(item->lastvalue_uint64,s);
				break;
			default:
				item->lastvalue_str=s;
				break;
		}	
	}
	s=row[14];
	if(DBis_null(s)==SUCCEED)
	{
		item->prevvalue_null=1;
	}
	else
	{
		item->prevvalue_null=0;
		switch(item->value_type) {
			case ITEM_VALUE_TYPE_FLOAT:
				item->prevvalue_dbl=atof(s);
				break;
			case ITEM_VALUE_TYPE_UINT64:
				ZBX_STR2UINT64(item->prevvalue_uint64,s);
				break;
			default:
				item->prevvalue_str=s;
				break;
		}	
	}
	ZBX_STR2UINT64(item->hostid, row[15]);
	item->host_status=atoi(row[16]);

	item->host_errors_from=atoi(row[18]);
	item->snmp_port=atoi(row[19]);
	item->delta=atoi(row[20]);

	s=row[21];
	if(DBis_null(s)==SUCCEED)
	{
		item->prevorgvalue_null=1;
	}
	else
	{
		item->prevorgvalue_null=0;
		switch(item->value_type) {
			case ITEM_VALUE_TYPE_FLOAT:
				item->prevorgvalue_dbl=atof(s);
				break;
			case ITEM_VALUE_TYPE_UINT64:
				ZBX_STR2UINT64(item->prevorgvalue_uint64,s);
				break;
			default:
				item->prevorgvalue_str=s;
				break;
		}	
	}
	s=row[22];
	if(DBis_null(s)==SUCCEED)
	{
		item->lastclock=0;
	}
	else
	{
		item->lastclock=atoi(s);
	}

	item->units=row[23];
	item->multiplier=atoi(row[24]);

	item->snmpv3_securityname = row[25];
	item->snmpv3_securitylevel = atoi(row[26]);
	item->snmpv3_authpassphrase = row[27];
	item->snmpv3_privpassphrase = row[28];
	item->formula = row[29];
	item->host_available=atoi(row[30]);
	item->status=atoi(row[31]);
	item->trapper_hosts=row[32];
	item->logtimefmt=row[33];
	ZBX_STR2UINT64(item->valuemapid, row[34]);
/*	item->valuemapid=atoi(row[34]); */
	item->delay_flex=row[35];
	item->host_dns=row[36];
}

/*
zbx_uint64_t DBget_nextid(char *table, char *field)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	res;
	zbx_uint64_t	min;
	zbx_uint64_t	max;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBget_nextid(%s,%s)", table, field);

	min = (zbx_uint64_t)__UINT64_C(100000000000000)*(zbx_uint64_t)CONFIG_NODEID;
	max = (zbx_uint64_t)__UINT64_C(100000000000000)*(zbx_uint64_t)(CONFIG_NODEID+1)-1;

	result = DBselect("select max(%s) from %s where %s>=" ZBX_FS_UI64 " and %s<=" ZBX_FS_UI64,
		field,
		table,
		field,
		min,
		field,
		max);
	zabbix_log(LOG_LEVEL_DEBUG, "select max(%s) from %s where %s>=" ZBX_FS_UI64 " and %s<=" ZBX_FS_UI64, field, table, field, min, field, max); 

	row=DBfetch(result);

	if(row && (DBis_null(row[0])!=SUCCEED))
	{
		sscanf(row[0],ZBX_FS_UI64,&res);

		res++;
	}
	else
	{
		res=(zbx_uint64_t)__UINT64_C(100000000000000)*(zbx_uint64_t)CONFIG_NODEID+1;
	}
	DBfree_result(result);
	zabbix_log(LOG_LEVEL_DEBUG, ZBX_FS_UI64, res);

	return res;
}
*/

zbx_uint64_t DBget_maxid(char *table, char *field)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	ret1,ret2;
	int		found  = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBget_maxid(%s,%s)",table,field);

	do
	{
		result = DBselect("select nextid from ids where nodeid=%d and table_name='%s' and field_name='%s'",
			CONFIG_NODEID,
			table,
			field);
		row = DBfetch(result);
		if(!row || DBis_null(row[0])==SUCCEED || !*row[0])
		{
			DBfree_result(result);
			result = DBselect("select max(%s) from %s where " ZBX_COND_NODEID,
				field,
				table,
				LOCAL_NODE(field));
			row = DBfetch(result);
			if(!row || DBis_null(row[0])==SUCCEED || !*row[0])
			{
				DBexecute("insert into ids (nodeid,table_name,field_name,nextid) values (%d,'%s','%s'," ZBX_FS_UI64 ")",
					CONFIG_NODEID,
					table,
					field,
					CONFIG_NODEID*(zbx_uint64_t)__UINT64_C(100000000000000)+1);
			}
			else
			{
				DBexecute("insert into ids ( nodeid,table_name,field_name,nextid) values (%d,'%s','%s',%s)",
					CONFIG_NODEID,
					table,
					field,
					row[0]);
			}
			DBfree_result(result);
			continue;
		}
		else
		{
			ZBX_STR2UINT64(ret1, row[0]);
			DBfree_result(result);

			DBexecute("update ids set nextid=nextid+1 where nodeid=%d and table_name='%s' and field_name='%s'",
				CONFIG_NODEID,
				table,
				field);

			result = DBselect("select nextid from ids where nodeid=%d and table_name='%s' and field_name='%s'",
				CONFIG_NODEID,
				table,
				field);
			row = DBfetch(result);
			if(!row || DBis_null(row[0])==SUCCEED)
			{
				/* Should never be here */
				DBfree_result(result);
				continue;
			}
			else
			{
				ZBX_STR2UINT64(ret2, row[0]);
				DBfree_result(result);
				if(ret1+1 == ret2)
				{
					found = SUCCEED;
				}
			}
		}
	}
	while(FAIL == found);

	zabbix_log(LOG_LEVEL_DEBUG, ZBX_FS_UI64, ret2);

	return ret2;

/*	if(CONFIG_NODEID == 0)
	{
		result = DBselect("select max(%s) from %s where " ZBX_COND_NODEID, field, table, LOCAL_NODE(field));
		row = DBfetch(result);

		if(!row || DBis_null(row[0])==SUCCEED)
		{
			ret = 1;
		}
		else
		{
			ZBX_STR2UINT64(ret, row[0]);
			ret = CONFIG_NODEID*(zbx_uint64_t)__UINT64_C(100000000000000) + ret;
			ret++;
		}
	}
	else
	{
		result = DBselect("select %s_%s from nodes where nodeid=%d", table, field, CONFIG_NODEID);
		row = DBfetch(result);
	
		if(!row || DBis_null(row[0])==SUCCEED)
		{
			ret = CONFIG_NODEID*(zbx_uint64_t)__UINT64_C(100000000000000) + 1;
		}
		else
		{
			ZBX_STR2UINT64(ret, row[0]);
			ret = CONFIG_NODEID*(zbx_uint64_t)__UINT64_C(100000000000000) + ret;
			ret++;
		}
		DBexecute("update nodes set %s_%s=%s_%s+1 where nodeid=%d",
			table, field, table, field, CONFIG_NODEID);
		DBfree_result(result);
	}

	return ret;*/
}
