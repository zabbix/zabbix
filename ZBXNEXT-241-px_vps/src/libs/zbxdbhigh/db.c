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
#include "dbcache.h"

const char *DBnode(const char *fieldid, const int nodeid)
{
	static char	dbnode[128];

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

	zabbix_log(LOG_LEVEL_DEBUG, "Connect to the database");
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
					zabbix_log(LOG_LEVEL_WARNING, "Database is down. Reconnecting in 10 seconds");
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
 * Author: Aleksander Vladishev                                               *
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

	ret = (ZBX_DB_OK != zbx_db_connect(CONFIG_DBHOST, CONFIG_DBUSER, CONFIG_DBPASSWORD, CONFIG_DBNAME, CONFIG_DBSOCKET, CONFIG_DBPORT)) ? FAIL : SUCCEED;
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
 * Comments: Do nothing if DB does not support transactions                   *
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
 * Comments: Do nothing if DB does not support transactions                   *
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
 * Comments: Do nothing if DB does not support transactions                   *
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
			zabbix_log(LOG_LEVEL_WARNING, "Database is down. Retrying in 10 seconds");
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
			zabbix_log(LOG_LEVEL_WARNING, "Database is down. Retrying in 10 seconds");
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
	DB_RESULT result = (DB_RESULT)ZBX_DB_DOWN;

	while(result == (DB_RESULT)ZBX_DB_DOWN)
	{
		result = zbx_db_select_n(query, n);

		if( result == (DB_RESULT)ZBX_DB_DOWN)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Database is down. Retrying in 10 seconds");
			sleep(10);
			DBclose();
			DBconnect(ZBX_DB_CONNECT_NORMAL);
		}
	}

	return result;
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

int	DBupdate_trigger_value(zbx_uint64_t triggerid, int trigger_type, int trigger_value,
		const char *trigger_error, int new_value, int now, const char *reason)
{
	const char	*__function_name = "update_trigger_value";
	int		ret = SUCCEED;
	DB_EVENT	event;
	int		update_status;
	char		*reason_esc;

	if (reason == NULL)
		zabbix_log(LOG_LEVEL_DEBUG, "In %s() triggerid:" ZBX_FS_UI64 " old:%d new:%d now:%d",
				__function_name, triggerid, trigger_value, new_value, now);
	else
		zabbix_log(LOG_LEVEL_DEBUG,"In %s() triggerid:" ZBX_FS_UI64 " old:%d new:%d now:%d reason:'%s'",
				__function_name, triggerid, trigger_value, new_value, now, reason);

	switch (trigger_type)
	{
		case TRIGGER_TYPE_MULTIPLE_TRUE:
			update_status = (trigger_value != new_value || new_value == TRIGGER_VALUE_TRUE);
			update_status = update_status && trigger_dependent(triggerid) == FAIL;
			break;
		case TRIGGER_TYPE_NORMAL:
		default:
			update_status = (trigger_value != new_value && trigger_dependent(triggerid) == FAIL);
			break;
	}

	/* New trigger value differs from current one AND ... */
	/* ... Do not update status if there are dependencies with status TRUE */
	if (update_status)
	{
		/* New trigger status is NOT equal to previous one, update trigger */
		if (trigger_value != new_value ||
				(trigger_type == TRIGGER_TYPE_MULTIPLE_TRUE && new_value == TRIGGER_VALUE_TRUE))
		{
			if (reason == NULL)
			{
				DBexecute("update triggers"
						" set value=%d,"
							"lastchange=%d,"
							"error=''"
						" where triggerid=" ZBX_FS_UI64,
					new_value, now, triggerid);
			}
			else
			{
				reason_esc = DBdyn_escape_string_len(reason, TRIGGER_ERROR_LEN);
				DBexecute("update triggers"
						" set value=%d,"
							"lastchange=%d,"
							"error='%s'"
						" where triggerid=" ZBX_FS_UI64,
					new_value, now, reason_esc, triggerid);
				zbx_free(reason_esc);
			}

			/* Preparing event for processing */
			memset(&event, 0, sizeof(DB_EVENT));
			event.source = EVENT_SOURCE_TRIGGERS;
			event.object = EVENT_OBJECT_TRIGGER;
			event.objectid = triggerid;
			event.clock = now;
			event.value = new_value;

			/* Processing event */
			if (FAIL == (ret = process_event(&event)))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "Event not added for triggerid [" ZBX_FS_UI64 "]",
						triggerid);
			}
		}
		else
			ret = FAIL;
	}
	else if (new_value == TRIGGER_VALUE_UNKNOWN && 0 != strcmp(trigger_error, reason))
	{
		reason_esc = DBdyn_escape_string_len(reason, TRIGGER_ERROR_LEN);
		DBexecute("update triggers"
				" set lastchange=%d,"
					"error='%s'"
				" where triggerid=" ZBX_FS_UI64,
				now,
				reason_esc,
				triggerid);
		zbx_free(reason_esc);
	}
	else
		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s",
			__function_name, zbx_result_string(ret));

	return ret;
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

	DBdelete_services_by_triggerid(triggerid);

	DBexecute("delete from sysmaps_link_triggers where triggerid=" ZBX_FS_UI64,triggerid);
	DBexecute("delete from triggers where triggerid=" ZBX_FS_UI64,triggerid);
}

void DBupdate_triggers_status_after_restart(void)
{
	const char	*__function_name = "DBupdate_triggers_after_restart";
	DB_RESULT	result;
	DB_RESULT	result2;
	DB_ROW		row;
	DB_ROW		row2;
	zbx_uint64_t	itemid, triggerid;
	int		trigger_type, trigger_value, type, lastclock, delay,
			nextcheck, min_nextcheck, now;
	const char	*trigger_error;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	now = time(NULL);

	DBbegin();

	result = DBselect(
			"select distinct t.triggerid,t.type,t.value,t.error"
			" from hosts h,items i,functions f,triggers t"
			" where h.hostid=i.hostid"
				" and i.itemid=f.itemid"
				" and f.triggerid=t.triggerid"
				" and h.status in (%d)"
				" and i.status in (%d)"
				" and i.type not in (%d)"
				" and i.key_ not in ('%s','%s')"
				" and t.status in (%d)",
			HOST_STATUS_MONITORED,
			ITEM_STATUS_ACTIVE,
			ITEM_TYPE_TRAPPER,
			SERVER_STATUS_KEY, SERVER_ZABBIXLOG_KEY,
			TRIGGER_STATUS_ENABLED);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(triggerid, row[0]);
		trigger_type = atoi(row[1]);
		trigger_value = atoi(row[2]);
		trigger_error = row[3];

		result2 = DBselect(
				"select distinct i.itemid,i.type,i.lastclock,i.delay,i.delay_flex"
				" from items i,functions f,triggers t"
				" where i.itemid=f.itemid"
					" and f.triggerid=t.triggerid"
					" and i.type not in (%d)"
					" and t.triggerid=" ZBX_FS_UI64,
				ITEM_TYPE_TRAPPER,
				triggerid);

		min_nextcheck = -1;
		while (NULL != (row2 = DBfetch(result2)))
		{
			ZBX_STR2UINT64(itemid, row2[0]);
			type = atoi(row2[1]);
			if (SUCCEED == DBis_null(row2[2]))
				lastclock = 0;
			else
				lastclock = atoi(row2[2]);
			delay = atoi(row2[3]);

			nextcheck = calculate_item_nextcheck(itemid, type, delay, row2[4], lastclock);
			if (-1 == min_nextcheck || nextcheck < min_nextcheck)
				min_nextcheck = nextcheck;
		}
		DBfree_result(result2);

		if (-1 == min_nextcheck || min_nextcheck >= now)
			continue;

		DBupdate_trigger_value(triggerid, trigger_type, trigger_value, trigger_error,
				TRIGGER_VALUE_UNKNOWN, min_nextcheck, "Zabbix was restarted.");
	}
	DBfree_result(result);

	DBcommit();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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

int	DBadd_trend_uint(zbx_uint64_t itemid, zbx_uint64_t value, int clock)
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
	int		res = 0, now;
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	itemid;
	int		item_type, delay, nextcheck;
	char		*delay_flex;
	time_t		lastclock;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBget_queue_count()");

	now = time(NULL);

	result = DBselect(
			"select i.itemid,i.type,i.delay,i.delay_flex,i.lastclock,h.host"
			" from items i,hosts h"
			" where i.hostid=h.hostid"
				" and h.proxy_hostid=0"
				" and h.status=%d"
				" and i.status=%d"
				" and i.value_type not in (%d)"
				" and i.key_ not in ('%s','%s')"
				" and not i.lastclock is null"
				" and ("
					"i.type in (%d,%d,%d,%d,%d,%d,%d)"
					" or (h.available<>%d and i.type in (%d))"
					" or (h.snmp_available<>%d and i.type in (%d,%d,%d))"
					" or (h.ipmi_available<>%d and i.type in (%d))"
					")"
				DB_NODE,
			HOST_STATUS_MONITORED,
			ITEM_STATUS_ACTIVE,
			ITEM_VALUE_TYPE_LOG,
			SERVER_STATUS_KEY, SERVER_ZABBIXLOG_KEY,
				ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_SSH, ITEM_TYPE_TELNET, ITEM_TYPE_SIMPLE,
				ITEM_TYPE_INTERNAL, ITEM_TYPE_AGGREGATE, ITEM_TYPE_EXTERNAL,
			HOST_AVAILABLE_FALSE,
				ITEM_TYPE_ZABBIX,
			HOST_AVAILABLE_FALSE,
				ITEM_TYPE_SNMPv1, ITEM_TYPE_SNMPv2c, ITEM_TYPE_SNMPv3,
			HOST_AVAILABLE_FALSE,
				ITEM_TYPE_IPMI,
			DBnode_local("i.itemid"));
	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(itemid, row[0]);
		item_type	= atoi(row[1]);
		delay		= atoi(row[2]);
		delay_flex	= row[3];

		if (FAIL == (lastclock = DCget_item_lastclock(itemid)))
		{
			if (SUCCEED == DBis_null(row[4]))
				lastclock = 0;
			else
				lastclock = (time_t)atoi(row[4]);
		}

		nextcheck = calculate_item_nextcheck(itemid, item_type, delay, delay_flex, lastclock);

		if (now - nextcheck > 5)
			res++;
	}
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
	result = DBselect("select sum(1.0/i.delay) from hosts h,items i"
			" where h.hostid=i.hostid and h.status=%d and i.status=%d and i.delay<>0",
			HOST_STATUS_MONITORED,
			ITEM_STATUS_ACTIVE);
	if (NULL != (row = DBfetch(result)) && SUCCEED != DBis_null(row[0]))
		qps_total += atof(row[0]);
	DBfree_result(result);

	return qps_total;
}

zbx_uint64_t DBget_proxy_lastaccess(const char *hostname)
{
	zbx_uint64_t	res;
	DB_RESULT	result;
	DB_ROW		row;
	char		*host_esc;

	zabbix_log(LOG_LEVEL_DEBUG,"In DBget_proxy_lastaccess()");

	host_esc = DBdyn_escape_string(hostname);
	result = DBselect("select lastaccess from hosts where host='%s' and status in (%d)",
			host_esc,
			HOST_STATUS_PROXY);
	zbx_free(host_esc);

	if (NULL == (row = DBfetch(result)) || SUCCEED == DBis_null(row[0])) {
		zabbix_log(LOG_LEVEL_ERR, "Proxy \"%s\" does not exist",
				hostname);
		zabbix_syslog("Proxy \"%s\" does not exist",
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
	char	*sendto_esc, *subject_esc, *message_esc;

	zabbix_log(LOG_LEVEL_DEBUG,"In add_alert(eventid:" ZBX_FS_UI64 ")",
		eventid);

	now = time(NULL);

	sendto_esc = DBdyn_escape_string_len(sendto, ALERT_SENDTO_LEN);
	subject_esc = DBdyn_escape_string_len(subject, ALERT_SUBJECT_LEN);
	message_esc = DBdyn_escape_string(message);

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

	/* remove older active escalations... */
	DBexecute("delete from escalations"
			" where actionid=" ZBX_FS_UI64
				" and triggerid=" ZBX_FS_UI64
				" and status not in (%d,%d,%d)"
				" and (esc_step<>0 or status<>%d)",
			actionid,
			triggerid,
			ESCALATION_STATUS_RECOVERY,
			ESCALATION_STATUS_SUPERSEDED_ACTIVE,
			ESCALATION_STATUS_SUPERSEDED_RECOVERY,
			ESCALATION_STATUS_ACTIVE);

	/* ...except we should execute an escalation at least once before it is removed */
	DBexecute("update escalations"
			" set status=%d"
			" where actionid=" ZBX_FS_UI64
				" and triggerid=" ZBX_FS_UI64
				" and esc_step=0"
				" and status=%d",
			ESCALATION_STATUS_SUPERSEDED_ACTIVE,
			actionid,
			triggerid,
			ESCALATION_STATUS_ACTIVE);

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
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	escalationid;
	int		old_status, esc_step;
	int		new_status;
	char		sql[256];

	/* stopping only last active escalation */
	zbx_snprintf(sql, sizeof(sql),
			"select escalationid,esc_step,status"
			" from escalations"
			" where actionid=" ZBX_FS_UI64
				" and triggerid=" ZBX_FS_UI64
				" and status not in (%d,%d)"
			" order by escalationid desc",
			actionid,
			triggerid,
			ESCALATION_STATUS_RECOVERY,
			ESCALATION_STATUS_SUPERSEDED_RECOVERY);

	result = DBselectN(sql, 1);

	if (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(escalationid, row[0]);
		esc_step = atoi(row[1]);
		old_status = atoi(row[2]);

		if ((0 == esc_step && ESCALATION_STATUS_ACTIVE == old_status) ||
				ESCALATION_STATUS_SUPERSEDED_ACTIVE == old_status)
		{
			new_status = ESCALATION_STATUS_SUPERSEDED_RECOVERY;
		}
		else
			new_status = ESCALATION_STATUS_RECOVERY;

		DBexecute("update escalations"
				" set r_eventid=" ZBX_FS_UI64 ","
					"status=%d,"
					"nextcheck=0"
				" where escalationid=" ZBX_FS_UI64,
				eventid,
				new_status,
				escalationid);
	}
	DBfree_result(result);

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

/******************************************************************************
 *                                                                            *
 * Function: DBget_escape_string_len                                          *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: return length of escaped string with terminating '\0'        *
 *                                                                            *
 * Author: Aleksandrs Saveljevs                                               *
 *                                                                            *
 * Comments: sync changes with 'DBescape_string'                              *
 *           and 'DBdyn_escape_string_len'                                    *
 *                                                                            *
 ******************************************************************************/
int	DBget_escape_string_len(const char *src)
{
	const char	*s;
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

	return len;
}

/******************************************************************************
 *                                                                            *
 * Function: DBescape_string                                                  *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: escaped string                                               *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments: sync changes with 'DBget_escape_string_len'                      *
 *           and 'DBdyn_escape_string_len'                                    *
 *                                                                            *
 ******************************************************************************/
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
#if defined(HAVE_POSTGRESQL)
			*d++ = *s;
#else
			*d++ = ZBX_DB_ESC_CH;
#endif
			len--;
		}
		*d++ = *s;
		len--;
	}
	*d = '\0';
}

/******************************************************************************
 *                                                                            *
 * Function: DBdyn_escape_string                                              *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: escaped string                                               *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
char*	DBdyn_escape_string(const char *src)
{
	int	len;
	char	*dst = NULL;

	len = DBget_escape_string_len(src);

	dst = zbx_malloc(dst, len);

	DBescape_string(src, dst, len);

	return dst;
}

/******************************************************************************
 *                                                                            *
 * Function: DBdyn_escape_string_len                                          *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: escaped string                                               *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments: sync changes with 'DBescape_string', 'DBget_escape_string_len'   *
 *                                                                            *
 ******************************************************************************/
char*	DBdyn_escape_string_len(const char *src, int max_src_len)
{
	const char	*s;
	char		*dst = NULL;
	int		len = 0;

	len++;	/* '\0' */

	for (s = src; s && *s; s++)
	{
		if (max_src_len <= 0)
			break;

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
		max_src_len--;
	}

	dst = zbx_malloc(dst, len);

	DBescape_string(src, dst, len);

	return dst;
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_escape_like_pattern_len                                    *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: return length of escaped LIKE pattern with terminating '\0'  *
 *                                                                            *
 * Author: Aleksandrs Saveljevs                                               *
 *                                                                            *
 * Comments: sync changes with 'DBescape_like_pattern'                        *
 *                                                                            *
 ******************************************************************************/
int	DBget_escape_like_pattern_len(const char *src)
{
	int		len;
	const char	*s;

	len = DBget_escape_string_len(src) - 1; /* minus '\0' */

	for (s = src; s && *s; s++)
	{
		len += (*s == '_' || *s == '%' || *s == ZBX_SQL_LIKE_ESCAPE_CHAR);
		len += 1;
	}

	len++; /* '\0' */

	return len;
}

/******************************************************************************
 *                                                                            *
 * Function: DBescape_like_pattern                                            *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: escaped string to be used as pattern in LIKE                 *
 *                                                                            *
 * Author: Aleksandrs Saveljevs                                               *
 *                                                                            *
 * Comments: sync changes with 'DBget_escape_like_pattern_len'                *
 *                                                                            *
 *           For instance, we wish to find string a_b%c\d'e!f in our database *
 *           using '!' as escape character. Our queries then become:          *
 *                                                                            *
 *           ... LIKE 'a!_b!%c\\d\'e!!f' ESCAPE '!' (MySQL, PostgreSQL)       *
 *           ... LIKE 'a!_b!%c\d''e!!f' ESCAPE '!' (SQLite3, Oracle)          *
 *                                                                            *
 *           Using backslash as escape character in LIKE would be too much    *
 *           trouble, because escaping backslashes would have to be escaped   *
 *           as well, like so:                                                *
 *                                                                            *
 *           ... LIKE 'a\\_b\\%c\\\\d\'e!f' ESCAPE '\\' or                    *
 *           ... LIKE 'a\\_b\\%c\\\\d\\\'e!f' ESCAPE '\\' (MySQL, PostgreSQL) *
 *           ... LIKE 'a\_b\%c\\d''e!f' ESCAPE '\' (SQLite3, Oracle)          *
 *                                                                            *
 *           Hence '!' instead of backslash.                                  *
 *                                                                            *
 ******************************************************************************/
void	DBescape_like_pattern(const char *src, char *dst, int len)
{
	char		*d;
	char		*tmp = NULL;
	const char	*t;

	assert(dst);

	tmp = zbx_malloc(tmp, len);

	DBescape_string(src, tmp, len);

	len--; /* '\0' */

	for (t = tmp, d = dst; t && *t && len; t++)
	{
		if (*t == '_' || *t == '%' || *t == ZBX_SQL_LIKE_ESCAPE_CHAR)
		{
			if (len <= 1)
				break;
			*d++ = ZBX_SQL_LIKE_ESCAPE_CHAR;
			len--;
		}
		*d++ = *t;
		len--;
	}

	*d = '\0';

	zbx_free(tmp);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdyn_escape_like_pattern                                        *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: escaped string to be used as pattern in LIKE                 *
 *                                                                            *
 * Author: Aleksandrs Saveljevs                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
char*	DBdyn_escape_like_pattern(const char *src)
{
	int	len;
	char	*dst = NULL;
	
	len = DBget_escape_like_pattern_len(src);

	dst = zbx_malloc(dst, len);

	DBescape_like_pattern(src, dst, len);

	return dst;
}

void	DBget_item_from_db(DB_ITEM *item, DB_ROW row)
{
	static char	*key = NULL;

	ZBX_STR2UINT64(item->itemid, row[0]);
	item->key			= row[1];
	item->key_orig			= row[1];
	item->host_name			= row[2];
	item->port			= atoi(row[3]);
	item->delay			= atoi(row[4]);
	item->description		= row[5];
	item->type			= atoi(row[6]);
	item->useip			= atoi(row[7]);
	item->host_ip			= row[8];
	item->history			= atoi(row[9]);
	item->trends			= atoi(row[23]);
	item->value_type		= atoi(row[13]);

	if (SUCCEED == DBis_null(row[10]))
		item->lastvalue_null = 1;
	else
	{
		item->lastvalue_null = 0;
		switch (item->value_type) {
		case ITEM_VALUE_TYPE_FLOAT:
			item->lastvalue_dbl = atof(row[10]);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			ZBX_STR2UINT64(item->lastvalue_uint64, row[10]);
			break;
		default:
			item->lastvalue_str = row[10];
			break;
		}
	}

	if (SUCCEED == DBis_null(row[11]))
		item->prevvalue_null = 1;
	else
	{
		item->prevvalue_null = 0;
		switch (item->value_type) {
		case ITEM_VALUE_TYPE_FLOAT:
			item->prevvalue_dbl = atof(row[11]);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			ZBX_STR2UINT64(item->prevvalue_uint64, row[11]);
			break;
		default:
			item->prevvalue_str = row[11];
			break;
		}
	}

	ZBX_STR2UINT64(item->hostid, row[12]);
	item->delta			= atoi(row[14]);

	if (SUCCEED == DBis_null(row[15]))
		item->prevorgvalue_null = 1;
	else
	{
		item->prevorgvalue_null = 0;
		switch (item->value_type) {
		case ITEM_VALUE_TYPE_FLOAT:
			item->prevorgvalue_dbl = atof(row[15]);
			break;
		case ITEM_VALUE_TYPE_UINT64:
			ZBX_STR2UINT64(item->prevorgvalue_uint64, row[15]);
			break;
		default:
			item->prevorgvalue_str = row[15];
			break;
		}
	}

	if (SUCCEED == DBis_null(row[16]))
		item->lastclock = 0;
	else
		item->lastclock = atoi(row[16]);

	item->units			= row[17];
	item->multiplier		= atoi(row[18]);
	item->formula			= row[19];
	item->status			= atoi(row[20]);
	ZBX_STR2UINT64(item->valuemapid, row[21]);
	item->host_dns			= row[22];

	item->lastlogsize		= atoi(row[24]);
	item->data_type			= atoi(row[25]);
	item->mtime			= atoi(row[26]);

	key = zbx_dsprintf(key, "%s", item->key_orig);
	substitute_simple_macros(NULL, NULL, item, NULL, NULL, NULL, &key, MACRO_TYPE_ITEM_KEY, NULL, 0);
	item->key = key;
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

	zabbix_log(LOG_LEVEL_DEBUG,"In DBget_maxid %s.%s",
			tablename,
			fieldname);

	if ((0 == strcmp(tablename, "history_log") && 0 == strcmp(fieldname, "id")) ||
			(0 == strcmp(tablename, "history_text") && 0 == strcmp(fieldname, "id")) ||
			(0 == strcmp(tablename, "dservices") && 0 == strcmp(fieldname, "dserviceid")) ||
			(0 == strcmp(tablename, "dhosts") && 0 == strcmp(fieldname, "dhostid")) ||
			(0 == strcmp(tablename, "alerts") && 0 == strcmp(fieldname, "alertid")) ||
			(0 == strcmp(tablename, "escalations") && 0 == strcmp(fieldname, "escalationid")) ||
			(0 == strcmp(tablename, "autoreg_host") && 0 == strcmp(fieldname, "autoreg_hostid")))
		return DCget_nextid(tablename, fieldname, num);

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

static char	buf_string[640];

/******************************************************************************
 *                                                                            *
 * Function: zbx_host_string                                                  *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: <host> or "???" if host not found                            *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
char	*zbx_host_string(zbx_uint64_t hostid)
{
	DB_RESULT	result;
	DB_ROW		row;

	result = DBselect(
			"select host"
			" from hosts"
			" where hostid=" ZBX_FS_UI64,
			hostid);

	if (NULL != (row = DBfetch(result)))
		zbx_snprintf(buf_string, sizeof(buf_string), "%s", row[0]);
	else
		zbx_snprintf(buf_string, sizeof(buf_string), "???");

	DBfree_result(result);

	return buf_string;
}

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
		zbx_snprintf(buf_string, sizeof(buf_string), "%s:%s", row[1], row[2]);
	else
		zbx_snprintf(buf_string, sizeof(buf_string), "???");

	DBfree_result(result);

	return buf_string;
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
	zbx_snprintf(buf_string, sizeof(buf_string), "%s:%s", item->host_name, item->key);

	return buf_string;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_user_string                                                  *
 *                                                                            *
 * Purpose:                                                                   *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: "Name Surname (Alias)" or "unknown" if user not found        *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
char	*zbx_user_string(zbx_uint64_t userid)
{
	DB_RESULT	result;
	DB_ROW		row;

	result = DBselect("select name,surname,alias from users where userid=" ZBX_FS_UI64,
			userid);

	if (NULL != (row = DBfetch(result)))
	{
		zbx_snprintf(buf_string, sizeof(buf_string), "%s %s (%s)",
				row[0],
				row[1],
				row[2]);
	}
	else
		zbx_snprintf(buf_string, sizeof(buf_string), "unknown");

	DBfree_result(result);

	return buf_string;
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
		zbx_snprintf(buf_string, sizeof(buf_string), "%s:%s.%s(%s)", row[1], row[2], row[3], row[4]);
	else
		zbx_snprintf(buf_string, sizeof(buf_string), "???");

	DBfree_result(result);

	return buf_string;
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

/******************************************************************************
 *                                                                            *
 * Function: DBregister_host                                                  *
 *                                                                            *
 * Purpose: registrate unknown host and generate event                        *
 *                                                                            *
 * Parameters: host - host name                                               *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	DBregister_host(zbx_uint64_t proxy_hostid, const char *host, int now)
{
	char		*host_esc;
	DB_RESULT	result;
	DB_ROW		row;
	DB_EVENT	event;
	zbx_uint64_t	autoreg_hostid;

	host_esc = DBdyn_escape_string_len(host, HOST_HOST_LEN);

	result = DBselect("select autoreg_hostid from autoreg_host where proxy_hostid=" ZBX_FS_UI64 " and host='%s'" DB_NODE,
			proxy_hostid,
			host_esc,
			DBnode_local("autoreg_hostid"));

	if (NULL != (row = DBfetch(result)))
		ZBX_STR2UINT64(autoreg_hostid, row[0])
	else
	{
		autoreg_hostid = DBget_maxid("autoreg_host", "autoreg_hostid");
		DBexecute("insert into autoreg_host (autoreg_hostid,proxy_hostid,host) values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",'%s')",
				autoreg_hostid,
				proxy_hostid,
				host_esc);
	}
	DBfree_result(result);

	zbx_free(host_esc);

	/* Preparing auto registration event for processing */
	memset(&event, 0, sizeof(DB_EVENT));
	event.source	= EVENT_SOURCE_AUTO_REGISTRATION;
	event.object	= EVENT_OBJECT_ZABBIX_ACTIVE;
	event.objectid	= autoreg_hostid;
	event.clock	= now;
	event.value	= TRIGGER_VALUE_TRUE;

	/* Processing event */
	process_event(&event);
}

/******************************************************************************
 *                                                                            *
 * Function: DBproxy_register_host                                            *
 *                                                                            *
 * Purpose: registrate unknown host                                           *
 *                                                                            *
 * Parameters: host - host name                                               *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Aleksander Vladishev                                               *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void	DBproxy_register_host(const char *host)
{
	char	*host_esc;

	host_esc = DBdyn_escape_string_len(host, HOST_HOST_LEN);

	DBexecute("insert into proxy_autoreg_host (clock,host) values (%d,'%s')",
			(int)time(NULL),
			host_esc);

	zbx_free(host_esc);
}

/******************************************************************************
 *                                                                            *
 * Function: DBexecute_overflowed_sql                                         *
 *                                                                            *
 * Purpose: execute a set of SQL statements IF it is big enough               *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value:                                                              *
 *                                                                            *
 * Author: Dmitry Borovikov                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
void DBexecute_overflowed_sql(char **sql, int *sql_allocated, int *sql_offset)
{
	if (*sql_offset > ZBX_MAX_SQL_SIZE)
	{
#ifdef HAVE_ORACLE
		zbx_snprintf_alloc(sql, sql_allocated, sql_offset, 8, "end;\n");
#endif
		DBexecute("%s", *sql);
		*sql_offset = 0;
#ifdef HAVE_ORACLE
		zbx_snprintf_alloc(sql, sql_allocated, sql_offset, 8, "begin\n");
#endif
	}
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_unique_hostname_by_sample                                  *
 *                                                                            *
 * Purpose: construct a unique host name by the given sample                  *
 *                                                                            *
 * Parameters: host_name_sample - a host name to start constructing from      *
 *                                                                            *
 * Return value: unique host name which does not exist in the data base       *
 *                                                                            *
 * Author: Dmitry Borovikov                                                   *
 *                                                                            *
 * Comments: the sample cannot be empty                                       *
 *           constructs new by adding "_$(number+1)", where "number"          *
 *           shows count of the sample itself plus already constructed ones   *
 *           host_name_sample is not modified, allocates new memory!          *
 *                                                                            *
 ******************************************************************************/
char	*DBget_unique_hostname_by_sample(char *host_name_sample)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		num = 2;	/* produce alternatives starting from "2" */
	char		*host_name_temp, *host_name_sample_esc;

	assert(host_name_sample && *host_name_sample);

	zabbix_log(LOG_LEVEL_DEBUG, "In DBget_unique_hostname_by_sample() sample:'%s'",
			host_name_sample);

	host_name_sample_esc = DBdyn_escape_like_pattern(host_name_sample);
	result = DBselect(
			"select host"
			" from hosts"
			" where host like '%s%%' escape '%c'"
		                 DB_NODE
			" group by host",
			host_name_sample_esc,
			ZBX_SQL_LIKE_ESCAPE_CHAR,
			DBnode_local("hostid"));

	host_name_temp = strdup(host_name_sample);

	while (NULL != (row = DBfetch(result)))
	{
		if (0 < strcmp(host_name_temp, row[0]))
		{
			/* skip those which are lexicographically smaller */
			continue;
		}
		if (0 > strcmp(host_name_temp, row[0]))
		{
			/* found, all other will be bigger */
			break;
		}
		/* 0 == strcmp(host_name_temp, row[0]) */
		/* must construct bigger one, the constructed one already exists */
		host_name_temp = zbx_dsprintf(host_name_temp, "%s_%d", host_name_sample, num++);
	}
	DBfree_result(result);

	zbx_free(host_name_sample_esc);

	zabbix_log(LOG_LEVEL_DEBUG, "End of DBget_unique_hostname_by_sample() constructed:'%s'",
			host_name_temp);

	return host_name_temp;
}
