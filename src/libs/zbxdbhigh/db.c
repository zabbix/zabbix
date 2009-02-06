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
#include "events.h"
#include "threads.h"
#include "zbxserver.h"

const char *DBnode(const char *fieldid, const int nodeid)
{
	static char	dbnode[256];

	if (nodeid == -1)
		*dbnode = '\0';
	else
		zbx_snprintf(dbnode, sizeof(dbnode), " and %s between %d00000000000000 and %d99999999999999",
				fieldid,
				nodeid,
				nodeid);

	return dbnode;
}

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
 * Function: DBinit                                                           *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Alksander Vladishev                                                *
 *                                                                            *
 * Comments:                                                                  * 
 *                                                                            *
 ******************************************************************************/
void	DBinit()
{
	zbx_db_init(CONFIG_DBHOST, CONFIG_DBUSER, CONFIG_DBPASSWORD, CONFIG_DBNAME, CONFIG_DBSOCKET, CONFIG_DBPORT);
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
int     DBget_function_result(char **result, char *functionid, char *error, int maxerrlen)
{
	DB_RESULT dbresult;
	DB_ROW	row;
	int		res = SUCCEED;

/* 0 is added to distinguish between lastvalue==NULL and empty result */
	dbresult = DBselect("select 0,lastvalue from functions where functionid=%s",
		functionid );

	row = DBfetch(dbresult);

	if (!row)
	{
		zbx_snprintf(error, maxerrlen, "invalid functionid [%s]",
				functionid);
		res = FAIL;
	}
	else if(DBis_null(row[1]) == SUCCEED)
	{
		zbx_snprintf(error, maxerrlen, "lastvalue IS NULL for function [%s][%s]",
				functionid,
				zbx_host_key_function_string(zbx_atoui64(functionid)));
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

	/* Object and objectid are used for efficient sort by the same index as in wehere condition */
	zbx_snprintf(sql,sizeof(sql),"select eventid,value,clock,object,objectid from events where source=%d and object=%d and objectid=" ZBX_FS_UI64 " order by object desc,objectid desc,eventid desc",
	/* The SQL is inefficient */
/*	zbx_snprintf(sql,sizeof(sql),"select eventid,value,clock from events where source=%d and object=%d and objectid=" ZBX_FS_UI64 " order by clock desc",*/
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
	DB_RESULT	result;
	DB_ROW		row;
	int ret = FAIL;
	char sql[MAX_STRING_LEN];

	zbx_snprintf(sql,sizeof(sql),"select servicealarmid, value from service_alarms where serviceid=" ZBX_FS_UI64 " order by servicealarmid desc", serviceid);

	zabbix_log(LOG_LEVEL_DEBUG,"In latest_service_alarm()");

	result = DBselectN(sql,1);
	row = DBfetch(result);

	if(row && (DBis_null(row[1])==FAIL) && (atoi(row[1]) == status)){
		ret = SUCCEED;
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

/******************************************************************************
 *                                                                            *
 * Function: trigger_dependent_rec                                            *
 *                                                                            *
 * Purpose: check if status depends on triggers having status TRUE            *
 *                                                                            *
 * Parameters: triggerid - trigger ID                                         *
 *                                                                            *
 * Return value: SUCCEED - it does depend, FAIL - otherwise                   *
 *                                                                            *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: Recursive function!                                              *
 *                                                                            *
 ******************************************************************************/
static int	trigger_dependent_rec(zbx_uint64_t triggerid, int *level)
{
	int	ret = FAIL;
	DB_RESULT	result;
	DB_ROW		row;

	zbx_uint64_t	triggerid_tmp;
	int		value_tmp;

	zabbix_log( LOG_LEVEL_DEBUG, "In trigger_dependent_rec(triggerid:" ZBX_FS_UI64 ",level:%d)",
		triggerid,
		*level);

	(*level)++;

	if(*level > 32)
	{
		zabbix_log( LOG_LEVEL_CRIT, "Recursive trigger dependency detected! Please fix. Triggerid:" ZBX_FS_UI64,
			triggerid);
		return ret;
	}

	result = DBselect("select t.triggerid, t.value from trigger_depends d,triggers t where d.triggerid_down=" ZBX_FS_UI64 " and d.triggerid_up=t.triggerid",
		triggerid);
	while((row=DBfetch(result)))
	{
		ZBX_STR2UINT64(triggerid_tmp, row[0]);
		value_tmp = atoi(row[1]);
		if(TRIGGER_VALUE_TRUE == value_tmp || trigger_dependent_rec(triggerid_tmp, level) == SUCCEED)
		{
			zabbix_log( LOG_LEVEL_DEBUG, "This trigger depends on " ZBX_FS_UI64 ". Will not apply actions",
				triggerid_tmp);
			ret = SUCCEED;
			break;
		}
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of trigger_dependent_rec():%s",
			zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: trigger_dependent                                                *
 *                                                                            *
 * Purpose: check if status depends on triggers having status TRUE            *
 *                                                                            *
 * Parameters: triggerid - trigger ID                                         *
 *                                                                            *
 * Return value: SUCCEED - it does depend, FAIL - not such triggers           *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	trigger_dependent(zbx_uint64_t triggerid)
{
	int	ret;
	int	level = 0;

	zabbix_log( LOG_LEVEL_DEBUG, "In trigger_dependent(triggerid:" ZBX_FS_UI64 ")",
		triggerid);

	ret =  trigger_dependent_rec(triggerid, &level);

	zabbix_log(LOG_LEVEL_DEBUG, "End of trigger_dependent():%s",
			zbx_result_string(ret));

	return ret;
}

int	DBupdate_trigger_value(DB_TRIGGER *trigger, int new_value, int now, const char *reason)
{
	int	ret = SUCCEED;
	DB_EVENT	event;
	int		event_last_status;
	int		event_prev_status;
	int		update_status;

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

	switch(trigger->type)
	{
		case TRIGGER_TYPE_MULTIPLE_TRUE:
			update_status = (trigger->value != new_value) || (new_value == TRIGGER_VALUE_TRUE);
			update_status = update_status && trigger_dependent(trigger->triggerid) == FAIL;
			break;
		case TRIGGER_TYPE_NORMAL:
		default:
			update_status = (trigger->value != new_value && trigger_dependent(trigger->triggerid) == FAIL);
			break;
	}

	/* New trigger value differs from current one AND ...*/
	/* ... Do not update status if there are dependencies with status TRUE*/
	if(update_status)
	{
		get_latest_event_status(trigger->triggerid, &event_prev_status, &event_last_status);

		zabbix_log(LOG_LEVEL_DEBUG,"tr value [%d] event_prev_value [%d] event_last_status [%d] new_value [%d]",
				trigger->value,
				event_prev_status,
				event_last_status,
				new_value);

		/* New trigger status is NOT equal to previous one, update trigger */
		if(trigger->value != new_value ||
			(trigger->type == TRIGGER_TYPE_MULTIPLE_TRUE && new_value == TRIGGER_VALUE_TRUE))
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
		/* Generate also UNKNOWN events, We are not interested in prev trigger value here. */
		if(event_last_status != new_value ||
			(trigger->type == TRIGGER_TYPE_MULTIPLE_TRUE && new_value == TRIGGER_VALUE_TRUE)
		)
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
				ret = FAIL;
				zabbix_log(LOG_LEVEL_WARNING,"Event processed not OK");
			}
		}
		else
		{
			ret = FAIL;
		}

		if( FAIL == ret)
		{
			zabbix_log(LOG_LEVEL_DEBUG,"Event not added for triggerid [" ZBX_FS_UI64 "]",
				trigger->triggerid);
			ret = FAIL;
		}
	}
	else
	{
		ret = FAIL;
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
	DBexecute("delete from events where object=%d AND objectid=" ZBX_FS_UI64,
		EVENT_OBJECT_TRIGGER,
		triggerid);
/*	zbx_snprintf(sql,sizeof(sql),"delete from actions where triggerid=%d and scope=%d", triggerid, ACTION_SCOPE_TRIGGER);
	DBexecute(sql);*/

	DBdelete_services_by_triggerid(triggerid);

	DBexecute("delete from sysmaps_link_triggers where triggerid=" ZBX_FS_UI64,triggerid);
	DBexecute("delete from triggers where triggerid=" ZBX_FS_UI64,triggerid);
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

void DBupdate_host_availability(DB_ITEM *item, int available, int clock, const char *error)
{
	char	error_esc[MAX_STRING_LEN], error_msg[MAX_STRING_LEN];
	int	log_level = LOG_LEVEL_WARNING;

	zabbix_log(LOG_LEVEL_DEBUG, "In update_host_availability()");

	if (item->host_available == available)
	{
		if (available == HOST_AVAILABLE_FALSE)
		{
			DBescape_string(error, error_esc, sizeof(error_esc));
			DBexecute("update hosts set error='%s',disable_until=%d where hostid=" ZBX_FS_UI64,
					error_esc,
					clock + CONFIG_UNAVAILABLE_DELAY,
					item->hostid);
		}
		return;
	}

	item->host_available = available;

	if (available == HOST_AVAILABLE_TRUE)
	{
		zbx_snprintf(error_msg, sizeof(error_msg), "Enabling host [%s]",
				item->host_name);

		DBexecute("update hosts set available=%d,error='',errors_from=0 where hostid=" ZBX_FS_UI64,
				available,
				item->hostid);
		item->host_errors_from = 0;
	}
	else if (available == HOST_AVAILABLE_FALSE)
	{
		zbx_snprintf(error_msg, sizeof(error_msg), "Host [%s] will be checked after %d seconds",
				item->host_name,
				CONFIG_UNAVAILABLE_DELAY);

		DBescape_string(error, error_esc, sizeof(error_esc));
		DBexecute("update hosts set available=%d,error='%s',disable_until=%d where hostid=" ZBX_FS_UI64,
				available,
				error_esc,
				clock + CONFIG_UNAVAILABLE_DELAY,
				item->hostid);

		update_triggers_status_to_unknown(item->hostid, clock, "Host is unavailable.");
	}
	else
	{
		log_level = LOG_LEVEL_ERR;
		zbx_snprintf(error_msg, sizeof(error_msg), "Unknown host availability [%d] for host [%s]",
				available,
				item->host_name);
	}

	zabbix_log(log_level, "%s", error_msg);
	zabbix_syslog("%s", error_msg);
}

void	DBproxy_update_host_availability(DB_ITEM *item, int available, int clock)
{
	char	error_msg[MAX_STRING_LEN];
	int	log_level = LOG_LEVEL_WARNING;

	zabbix_log(LOG_LEVEL_DEBUG, "In DBproxy_update_host_availability()");

	if (item->host_available == available)
	{
		if (available == HOST_AVAILABLE_FALSE)
		{
			DBexecute("update hosts set disable_until=%d where hostid=" ZBX_FS_UI64,
					clock + CONFIG_UNAVAILABLE_DELAY,
					item->hostid);
		}
		return;
	}

	item->host_available = available;

	if (available == HOST_AVAILABLE_TRUE)
	{
		zbx_snprintf(error_msg, sizeof(error_msg), "Enabling host [%s]",
				item->host_name);

		DBexecute("update hosts set available=%d,errors_from=0 where hostid=" ZBX_FS_UI64,
				available,
				item->hostid);
		item->host_errors_from = 0;
	}
	else if (available == HOST_AVAILABLE_FALSE)
	{
		zbx_snprintf(error_msg, sizeof(error_msg), "Host [%s] will be checked after %d seconds",
				item->host_name,
				CONFIG_UNAVAILABLE_DELAY);

		DBexecute("update hosts set available=%d,disable_until=%d where hostid=" ZBX_FS_UI64,
				available,
				clock + CONFIG_UNAVAILABLE_DELAY,
				item->hostid);
	}
	else
	{
		log_level = LOG_LEVEL_ERR;
		zbx_snprintf(error_msg, sizeof(error_msg), "Unknown host availability [%d] for host [%s]",
				available,
				item->host_name);
	}

	zabbix_log(log_level, "%s", error_msg);
	zabbix_syslog("%s", error_msg);
}

int	DBupdate_item_status_to_notsupported(DB_ITEM *item, int clock, const char *error)
{
	char		error_esc[MAX_STRING_LEN];
	DB_RESULT	result;
	DB_ROW		row;
	DB_TRIGGER	trigger;

	zabbix_log(LOG_LEVEL_DEBUG, "In DBupdate_item_status_to_notsupported()");

	DBescape_string(error, error_esc, sizeof(error_esc));

	item->status	= ITEM_STATUS_NOTSUPPORTED;
	item->nextcheck	= clock + CONFIG_REFRESH_UNSUPPORTED;

	DBexecute("update items set status=%d,lastclock=%d,nextcheck=%d,error='%s' where itemid=" ZBX_FS_UI64,
			item->status,
			clock,
			item->nextcheck,
			error_esc,
			item->itemid);

	result = DBselect("select t.triggerid,t.expression,t.description,t.url,t.comments,t.status,t.value,t.priority"
			" from triggers t,functions f,items i"
			" where t.triggerid=f.triggerid"
				" and f.itemid=i.itemid"
				" and t.status in (%d)"
				" and t.value not in (%d)"
				" and i.itemid=" ZBX_FS_UI64,
		TRIGGER_STATUS_ENABLED,
		TRIGGER_VALUE_UNKNOWN,
		item->itemid);

	while (NULL != (row = DBfetch(result)))
	{
		trigger.triggerid 	= zbx_atoui64(row[0]);
		strscpy(trigger.expression, row[1]);
		strscpy(trigger.description, row[2]);
		trigger.url		= row[3];
		trigger.comments	= row[4];
		trigger.status		= atoi(row[5]);
		trigger.value		= atoi(row[6]);
		trigger.priority	= atoi(row[7]);

		DBupdate_trigger_value(&trigger, TRIGGER_VALUE_UNKNOWN, clock, error);
	}

	DBfree_result(result);

	return SUCCEED;
}

static int	DBadd_trend(zbx_uint64_t itemid, double value, int clock)
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

static int	DBadd_trend_uint(zbx_uint64_t itemid, zbx_uint64_t value, int clock)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		hour;
	int		num;
	zbx_uint64_t	value_min, value_avg, value_max;

	zabbix_log(LOG_LEVEL_DEBUG,"In add_trend_uint()");

	hour=clock-clock%3600;

	result = DBselect("select num,value_min,value_avg,value_max from trends_uint where itemid=" ZBX_FS_UI64 " and clock=%d",
		itemid,
		hour);

	row=DBfetch(result);

	if(row)
	{
		num = atoi(row[0]);
		value_min = zbx_atoui64(row[1]);
		value_avg = zbx_atoui64(row[2]);
		value_max = zbx_atoui64(row[3]);
		if(value<value_min)	value_min=value;
		if(value>value_max)	value_max=value;
		value_avg=(num*value_avg+value)/(num+1);
		num++;
		DBexecute("update trends_uint set num=%d,value_min=" ZBX_FS_UI64 ",value_avg=" ZBX_FS_UI64 ",value_max=" ZBX_FS_UI64 " where itemid=" ZBX_FS_UI64 " and clock=%d",
			num,
			value_min,
			value_avg,
			value_max,
			itemid,
			hour);
	}
	else
	{
		DBexecute("insert into trends_uint (clock,itemid,num,value_min,value_avg,value_max) values (%d," ZBX_FS_UI64 ",%d," ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ")",
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

	if ((CONFIG_NODE_NOHISTORY == 0) && (CONFIG_MASTER_NODEID > 0))
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

	DBadd_trend_uint(itemid, value, clock);

	if ((CONFIG_NODE_NOHISTORY == 0) && (CONFIG_MASTER_NODEID > 0))
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

	if ((CONFIG_NODE_NOHISTORY == 0) && (CONFIG_MASTER_NODEID > 0))
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
#else
	char		*value_esc = NULL;
	zbx_uint64_t	id;
#endif

	zabbix_log(LOG_LEVEL_DEBUG, "In add_history_text()");

#ifdef HAVE_ORACLE
	sqlo_lob_desc_t		loblp;		/* the lob locator */
	sqlo_stmt_handle_t	sth = 0;

	sqlo_autocommit_off(oracle);

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

	id = DBget_maxid("history_text", "id");
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
	value_esc = DBdyn_escape_string(value);

	id = DBget_maxid("history_text", "id");

	DBexecute("insert into history_text (id,clock,itemid,value)"
			" values (" ZBX_FS_UI64 ",%d," ZBX_FS_UI64 ",'%s')",
			id,
			clock,
			itemid,
			value_esc);

	zbx_free(value_esc);
#endif

	return SUCCEED;
}

int	DBadd_history_log(zbx_uint64_t itemid, char *value, int clock, int timestamp, char *source, int severity, int lastlogsize)
{
	char		*value_esc = NULL, source_esc[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In add_history_log()");

	value_esc = DBdyn_escape_string(value);
	DBescape_string(source, source_esc, sizeof(source_esc));

	DBexecute("insert into history_log (id,clock,itemid,timestamp,value,source,severity)"
			" values (" ZBX_FS_UI64 ",%d," ZBX_FS_UI64 ",%d,'%s','%s',%d)",
			DBget_maxid("history_log", "id"),
			clock,
			itemid,
			timestamp,
			value_esc,
			source_esc,
			severity);

	zbx_free(value_esc);

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

double	DBget_requiredperformance(void)
{
	double		qps_total = 0;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In DBget_requiredperformance()");

	/* !!! Don't forget sync code with PHP !!! */
	result = DBselect("select i.type,i.delay,count(*)/i.delay from hosts h,items i"
			" where h.hostid=i.hostid and h.status=%d and i.status=%d"
			" group by i.type,i.delay",
			HOST_STATUS_MONITORED,
			ITEM_STATUS_ACTIVE);
	while (NULL != (row = DBfetch(result)))
		qps_total += atof(row[2]);
	DBfree_result(result);

	return qps_total;
}

zbx_uint64_t DBget_proxy_lastaccess(const char *hostname)
{
	zbx_uint64_t	res;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG,"In D()");

	result = DBselect("select lastaccess from hosts where host='%s' and status in (%d)",
			hostname,
			HOST_STATUS_PROXY);

	if (NULL == (row = DBfetch(result)) || SUCCEED == DBis_null(row[0])) {
		zabbix_log(LOG_LEVEL_ERR, "Proxy \"%s\" not exists",
				hostname);
		zabbix_syslog("Proxy \"%s\" not exists",
				hostname);
		DBfree_result(result);
		return FAIL;
	}

	res = zbx_atoui64(row[0]);

	DBfree_result(result);

	return res;
}

int	DBadd_alert(zbx_uint64_t actionid, zbx_uint64_t userid, zbx_uint64_t eventid,  zbx_uint64_t mediatypeid, char *sendto, char *subject, char *message)
{
	int	now;
	
	char	*sendto_esc	= NULL;
	char	*subject_esc	= NULL;
	char	*message_esc	= NULL;
	
	int	size;

	zabbix_log(LOG_LEVEL_DEBUG,"In add_alert(eventid:" ZBX_FS_UI64 ")",
		eventid);

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
	
	DBexecute("insert into alerts (alertid,actionid,eventid,userid,clock,mediatypeid,sendto,subject,message,status,retries)"
		" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d," ZBX_FS_UI64 ",'%s','%s','%s',0,0)",
		DBget_maxid("alerts","alertid"),
		actionid,
		eventid,
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

int	DBstart_escalation(zbx_uint64_t actionid, zbx_uint64_t triggerid, zbx_uint64_t eventid)
{
	zbx_uint64_t	escalationid;

	escalationid = DBget_maxid("escalations", "escalationid");

	DBexecute("insert into escalations (escalationid,actionid,triggerid,eventid,status)"
			" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d)",
			escalationid,
			actionid,
			triggerid,
			eventid,
			ESCALATION_STATUS_ACTIVE);

	return SUCCEED;
}

int	DBstop_escalation(zbx_uint64_t actionid, zbx_uint64_t triggerid, zbx_uint64_t eventid)
{
	DBexecute("update escalations set r_eventid=" ZBX_FS_UI64 ",status=%d,nextcheck=0"
			" where actionid=" ZBX_FS_UI64 " and triggerid=" ZBX_FS_UI64,
			eventid,
			ESCALATION_STATUS_RECOVERY,
			actionid,
			triggerid);

	return SUCCEED;
}

int	DBremove_escalation(zbx_uint64_t escalationid)
{
	DBexecute("delete from escalations where escalationid=" ZBX_FS_UI64,
			escalationid);

	return SUCCEED;
}

void	DBvacuum(void)
{
#ifdef	HAVE_POSTGRESQL
	char *table_for_housekeeping[]={"services", "services_links", "graphs_items", "graphs", "sysmaps_links",
			"sysmaps_elements", "sysmaps_link_triggers","sysmaps", "config", "groups", "hosts_groups", "alerts",
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

/* NOTE: sync changes with 'DBdyn_escape_string' */
void    DBescape_string(const char *src, char *dst, int len)
{
	const char	*s;
	char		*d;
#if defined(HAVE_ORACLE) || defined(HAVE_SQLITE3)
#	define ZBX_DB_ESC_CH	'\''
#else
#	define ZBX_DB_ESC_CH	'\\'
#endif
	assert(dst);

	len--;	/* '\0' */

	for (s = src, d = dst; s && *s && len; s++)
	{
		if (*s == '\r')
			continue;

		if (*s == '\'' 
#if !defined(HAVE_ORACLE) && !defined(HAVE_SQLITE3)
			|| *s == '\\'
#endif
			)
		{
			if (len < 2)
				break;
			*d++ = ZBX_DB_ESC_CH;
			len--;
		}
		*d++ = *s;
		len--;
	}
	*d = '\0';
}

/* NOTE: sync changes with 'DBdyn_escape_string' */
char*	DBdyn_escape_string(const char *src)
{
	const char	*s;
	char		*dst = NULL;
	int		len = 0;

	len++;	/* '\0' */

	for (s = src; s && *s; s++)
	{
		if (*s == '\r')
			continue;

		if (*s == '\'' 
#if !defined(HAVE_ORACLE) && !defined(HAVE_SQLITE3)
			|| *s == '\\'
#endif
			)
		{
			len++;
		}
		len++;
	}

	dst = zbx_malloc(dst, len);

	DBescape_string(src, dst, len);

	return dst;
}

void	DBget_item_from_db(DB_ITEM *item,DB_ROW row)
{
	char		*s;
	static char	*key = NULL;
	static char	*ipmi_ip = NULL;

	ZBX_STR2UINT64(item->itemid, row[0]);
/*	item->itemid=atoi(row[0]); */
	item->key	= row[1];
	item->key_orig=row[1];
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
	item->trends=atoi(row[38]);
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
	s = row[22];
	if(DBis_null(s)==SUCCEED)
	{
		item->lastclock=0;
	}
	else
	{
		item->lastclock=atoi(s);
	}

	item->units			= row[23];
	item->multiplier		= atoi(row[24]);
	item->snmpv3_securityname	= row[25];
	item->snmpv3_securitylevel	= atoi(row[26]);
	item->snmpv3_authpassphrase	= row[27];
	item->snmpv3_privpassphrase	= row[28];
	item->formula		= row[29];
	item->host_available	= atoi(row[30]);
	item->status		= atoi(row[31]);
	item->trapper_hosts	= row[32];
	item->logtimefmt	= row[33];
	ZBX_STR2UINT64(item->valuemapid, row[34]);
	item->delay_flex	= row[35];
	item->host_dns		= row[36];
	item->params		= row[37];		/* !!! WHAT about CLOB??? */

	item->eventlog_source	= NULL;

	item->useipmi		= atoi(row[39]);
	item->ipmi_ip		= row[51];
	item->ipmi_port		= atoi(row[40]);
	item->ipmi_authtype	= atoi(row[41]);
	item->ipmi_privilege	= atoi(row[42]);
	item->ipmi_username	= row[43];
	item->ipmi_password	= row[44];
	item->ipmi_sensor	= row[45];

	item->maintenance_status	= atoi(row[46]);
	item->maintenance_type		= atoi(row[47]);
	item->maintenance_from		= atoi(row[48]);

	item->lastlogsize		= atoi(row[49]);
	item->data_type			= atoi(row[50]);

	switch (item->type) {
		case ITEM_TYPE_ZABBIX:
		case ITEM_TYPE_ZABBIX_ACTIVE:
		case ITEM_TYPE_SIMPLE:
		case ITEM_TYPE_EXTERNAL:
			key = zbx_dsprintf(key, "%s", item->key_orig);
			substitute_simple_macros(NULL, NULL, item, &key, MACRO_TYPE_ITEM_KEY);
			item->key	= key;
			break;
		case ITEM_TYPE_IPMI:
			ipmi_ip = zbx_dsprintf(ipmi_ip, "%s", item->ipmi_ip);
			substitute_simple_macros(NULL, NULL, item, &ipmi_ip, MACRO_TYPE_HOST_IPMI_IP);
			item->ipmi_ip	= ipmi_ip;
			break;
		default:
			/* nothing */;
	}
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

const ZBX_TABLE *DBget_table(const char *tablename)
{
	int	t;

	for (t = 0; tables[t].table != 0; t++ )
		if (0 == strcmp(tables[t].table, tablename))
			return &tables[t];
	return NULL;
}

const ZBX_FIELD *DBget_field(const ZBX_TABLE *table, const char *fieldname)
{
	int	f;

	for (f = 0; table->fields[f].name != 0; f++ )
		if (0 == strcmp(table->fields[f].name, fieldname))
			return &table->fields[f];
	return NULL;
}

zbx_uint64_t DBget_maxid_num(char *tablename, char *fieldname, int num)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	ret1,ret2;
	zbx_uint64_t	min, max;
	int		found  = FAIL, dbres, nodeid;
	const ZBX_TABLE	*table;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBget_maxid \"%s\".\"%s\"",
			tablename,
			fieldname);

	table = DBget_table(tablename);
	nodeid = CONFIG_NODEID >= 0 ? CONFIG_NODEID : 0;

	if (table->flags & ZBX_SYNC) {
		min = (zbx_uint64_t)__UINT64_C(100000000000000)*(zbx_uint64_t)nodeid+(zbx_uint64_t)__UINT64_C(100000000000)*(zbx_uint64_t)nodeid;
		max = (zbx_uint64_t)__UINT64_C(100000000000000)*(zbx_uint64_t)nodeid+(zbx_uint64_t)__UINT64_C(100000000000)*(zbx_uint64_t)nodeid+(zbx_uint64_t)__UINT64_C(99999999999);
	} else {
		min = (zbx_uint64_t)__UINT64_C(100000000000000)*(zbx_uint64_t)nodeid;
		max = (zbx_uint64_t)__UINT64_C(100000000000000)*(zbx_uint64_t)nodeid+(zbx_uint64_t)__UINT64_C(99999999999999);
	}

	do {
		result = DBselect("select nextid from ids where nodeid=%d and table_name='%s' and field_name='%s'",
			nodeid,
			tablename,
			fieldname);

		if(NULL == (row = DBfetch(result))) {
			DBfree_result(result);

			result = DBselect("select max(%s) from %s where %s between " ZBX_FS_UI64 " and " ZBX_FS_UI64,
					fieldname,
					tablename,
					fieldname,
					min,
					max);

			if(NULL == (row = DBfetch(result)) || SUCCEED == DBis_null(row[0]) || !*row[0])
				ret1 = min;
			else {
				ZBX_STR2UINT64(ret1, row[0]);
				if(ret1 >= max) {
					zabbix_log(LOG_LEVEL_CRIT, "DBget_maxid: Maximum number of id's was exceeded"
							" [table:%s, field:%s, id:" ZBX_FS_UI64 "]",
							tablename,
							fieldname,
							ret1);

					exit(FAIL);
				}
			}
			DBfree_result(result);

			dbres = DBexecute("insert into ids (nodeid,table_name,field_name,nextid)"
					" values (%d,'%s','%s'," ZBX_FS_UI64 ")",
					nodeid,
					tablename,
					fieldname,
					ret1);

			if (dbres < ZBX_DB_OK) {
				/* reshenie problemi nevidimosti novoj zapisi, sozdannoj v parallel'noj tranzakcii */
				DBexecute("update ids set nextid=nextid+1 where nodeid=%d and table_name='%s'"
						" and field_name='%s'",
						nodeid,
						tablename,
						fieldname);
			}
			continue;
		} else {
			ZBX_STR2UINT64(ret1, row[0]);
			DBfree_result(result);

			if((ret1 < min) || (ret1 >= max)) {
				DBexecute("delete from ids where nodeid=%d and table_name='%s' and field_name='%s'",
					nodeid,
					tablename,
					fieldname);
				continue;
			}

			DBexecute("update ids set nextid=nextid+%d where nodeid=%d and table_name='%s' and field_name='%s'",
					num,
					nodeid,
					tablename,
					fieldname);

			result = DBselect("select nextid from ids where nodeid=%d and table_name='%s' and field_name='%s'",
				nodeid,
				tablename,
				fieldname);
			row = DBfetch(result);
			if (!row || DBis_null(row[0])==SUCCEED) {
				/* Should never be here */
				DBfree_result(result);
				continue;
			} else {
				ZBX_STR2UINT64(ret2, row[0]);
				DBfree_result(result);
				if (ret1 + num == ret2)
					found = SUCCEED;
			}
		}
	}
	while(FAIL == found);

	zabbix_log(LOG_LEVEL_DEBUG, "End of DBget_maxid \"%s\".\"%s\":" ZBX_FS_UI64,
			tablename,
			fieldname,
			ret2);

	return ret2 - num + 1;

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

void	DBproxy_add_history(zbx_uint64_t itemid, double value, int clock)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In proxy_add_history()");

	DBexecute("insert into proxy_history (itemid,clock,value) values (" ZBX_FS_UI64 ",%d,'" ZBX_FS_DBL "')",
			itemid,
			clock,
			value);
}

void	DBproxy_add_history_uint(zbx_uint64_t itemid, zbx_uint64_t value, int clock)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In proxy_add_history_uint()");

	DBexecute("insert into proxy_history (itemid,clock,value) values (" ZBX_FS_UI64 ",%d,'" ZBX_FS_UI64 "')",
			itemid,
			clock,
			value);
}

void	DBproxy_add_history_str(zbx_uint64_t itemid, char *value, int clock)
{
	char	value_esc[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In proxy_add_history_str()");

	DBescape_string(value, value_esc, sizeof(value_esc));

	DBexecute("insert into proxy_history (itemid,clock,value) values (" ZBX_FS_UI64 ",%d,'%s')",
			itemid,
			clock,
			value_esc);
}

void	DBproxy_add_history_text(zbx_uint64_t itemid, char *value, int clock)
{
	char	*value_esc;

	zabbix_log(LOG_LEVEL_DEBUG, "In proxy_add_history_text()");

	value_esc = DBdyn_escape_string(value);

	DBexecute("insert into proxy_history (itemid,clock,value) values (" ZBX_FS_UI64 ",%d,'%s')",
			itemid,
			clock,
			value_esc);

	zbx_free(value_esc);
}

void	DBproxy_add_history_log(zbx_uint64_t itemid, char *value, int clock, int timestamp, char *source, int severity, int lastlogsize)
{
	char		source_esc[MAX_STRING_LEN], *value_esc;

	zabbix_log(LOG_LEVEL_DEBUG, "In proxy_add_history_log()");

	DBescape_string(source, source_esc, sizeof(source_esc));
	value_esc = DBdyn_escape_string(value);

	DBexecute("insert into proxy_history (itemid,clock,timestamp,source,severity,value)"
			" values (" ZBX_FS_UI64 ",%d,%d,'%s',%d,'%s')",
			itemid,
			clock,
			timestamp,
			source_esc,
			severity,
			value_esc);

	zbx_free(value_esc);
}

void	DBadd_condition_alloc(char **sql, int *sql_alloc, int *sql_offset, const char *fieldname, const zbx_uint64_t *values, const int num)
{
#define MAX_EXPRESSIONS 950
	int	i;

	if (0 == num)
		return;

	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, 2, " ");
	if (num > MAX_EXPRESSIONS)
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, 2, "(");

	for (i = 0; i < num; i++)
	{
		if (0 == (i % MAX_EXPRESSIONS))
		{
			if (0 != i)
			{
				(*sql_offset)--;
				zbx_snprintf_alloc(sql, sql_alloc, sql_offset, 8, ") or ");
			}
			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, 128, "%s in (",
					fieldname);
		}
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, 128, ZBX_FS_UI64 ",",
				values[i]);
	}

	(*sql_offset)--;
	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, 2, ")");

	if (num > MAX_EXPRESSIONS)
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, 2, ")");
}

static char	string[640];

/******************************************************************************
 *                                                                            *
 * Function: zbx_host_key_string                                              *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: <host>:<key> or "???" if item not found                      *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  * 
 *                                                                            *
 ******************************************************************************/
char	*zbx_host_key_string(zbx_uint64_t itemid)
{
	DB_RESULT	result;
	DB_ROW		row;

	result = DBselect("select i.itemid,h.host,i.key_ from items i,hosts h"
			" where i.hostid=h.hostid and i.itemid=" ZBX_FS_UI64,
			itemid);

	if (NULL != (row = DBfetch(result)) && SUCCEED != DBis_null(row[0]))
		zbx_snprintf(string, sizeof(string), "%s:%s", row[1], row[2]);
	else
		zbx_snprintf(string, sizeof(string), "???");

	DBfree_result(result);

	return string;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_host_key_string_by_item                                      *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: <host>:<key>                                                 *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  * 
 *                                                                            *
 ******************************************************************************/
char	*zbx_host_key_string_by_item(DB_ITEM *item)
{
	zbx_snprintf(string, sizeof(string), "%s:%s", item->host_name, item->key);

	return string;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_host_key_function_string                                     *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: function name in format:                                     *
 *                                    <host>:<key>.<function>(<parameters>)   *
 *                             or "???" if function not found                 *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  * 
 *                                                                            *
 ******************************************************************************/
char	*zbx_host_key_function_string(zbx_uint64_t functionid)
{
	DB_RESULT	result;
	DB_ROW		row;

	result = DBselect("select f.functionid,h.host,i.key_,f.function,f.parameter from items i,hosts h,functions f"
			" where i.hostid=h.hostid and f.itemid=i.itemid and f.functionid=" ZBX_FS_UI64,
			functionid);

	if (NULL != (row = DBfetch(result)) && SUCCEED != DBis_null(row[0]))
		zbx_snprintf(string, sizeof(string), "%s:%s.%s(%s)", row[1], row[2], row[3], row[4]);
	else
		zbx_snprintf(string, sizeof(string), "???");

	DBfree_result(result);

	return string;
}

double	DBmultiply_value_float(DB_ITEM *item, double value)
{
	double	value_double;

	if (ITEM_MULTIPLIER_USE != item->multiplier)
		return value;

	value_double = value * atof(item->formula);

	zabbix_log(LOG_LEVEL_DEBUG, "DBmultiply_value_float() " ZBX_FS_DBL ",%s " ZBX_FS_DBL,
			value, item->formula, value_double);

	return value_double;
}

zbx_uint64_t	DBmultiply_value_uint64(DB_ITEM *item, zbx_uint64_t value)
{
	zbx_uint64_t	formula_uint64, value_uint64;

	if (ITEM_MULTIPLIER_USE != item->multiplier)
		return value;

	if (SUCCEED == is_uint64(item->formula, &formula_uint64))
		value_uint64 = value * formula_uint64;
	else
		value_uint64 = (zbx_uint64_t)((double)value * atof(item->formula));

	zabbix_log(LOG_LEVEL_DEBUG, "DBmultiply_value_float() " ZBX_FS_UI64 ",%s " ZBX_FS_UI64,
			value, item->formula, value_uint64);

	return value_uint64;
}

