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

#include "zbxdb.h"
#include "db.h"
#include "log.h"
#include "common.h"
#include "events.h"
#include "threads.h"
#include "zbxserver.h"
#include "dbcache.h"

#define ZBX_DB_WAIT_DOWN	10

#if HAVE_POSTGRESQL
extern char	ZBX_PG_ESCAPE_BACKSLASH;
#endif

const char	*DBnode(const char *fieldid, int nodeid)
{
	static char	dbnode[128];

	if (-1 != nodeid)
	{
		zbx_snprintf(dbnode, sizeof(dbnode), " and %s between %d00000000000000 and %d99999999999999",
				fieldid, nodeid, nodeid);
	}
	else
		*dbnode = '\0';

	return dbnode;
}

void	DBclose()
{
	zbx_db_close();
}

/******************************************************************************
 *                                                                            *
 * Function: DBconnect                                                        *
 *                                                                            *
 * Purpose: connect to the database                                           *
 *                                                                            *
 * Parameters: flag - ZBX_DB_CONNECT_ONCE (try once and return the result),   *
 *                    ZBX_DB_CONNECT_EXIT (exit on failure) or                *
 *                    ZBX_DB_CONNECT_NORMAL (retry until connected)           *
 *                                                                            *
 * Return value: same as zbx_db_connect()                                     *
 *                                                                            *
 ******************************************************************************/
int	DBconnect(int flag)
{
	const char	*__function_name = "DBconnect";

	int	err;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() flag:%d", __function_name, flag);

	while (ZBX_DB_OK != (err = zbx_db_connect(CONFIG_DBHOST, CONFIG_DBUSER, CONFIG_DBPASSWORD,
			CONFIG_DBNAME, CONFIG_DBSCHEMA, CONFIG_DBSOCKET, CONFIG_DBPORT)))
	{
		if (ZBX_DB_CONNECT_ONCE == flag)
			break;

		if (ZBX_DB_FAIL == err || ZBX_DB_CONNECT_EXIT == flag)
		{
			zabbix_log(LOG_LEVEL_CRIT, "Cannot connect to the database. Exiting...");
			exit(FAIL);
		}

		zabbix_log(LOG_LEVEL_WARNING, "Database is down. Reconnecting in %d seconds.", ZBX_DB_WAIT_DOWN);
		zbx_sleep(ZBX_DB_WAIT_DOWN);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, err);

	return err;
}

/******************************************************************************
 *                                                                            *
 * Function: DBinit                                                           *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	DBinit()
{
	zbx_db_init(CONFIG_DBHOST, CONFIG_DBUSER, CONFIG_DBPASSWORD, CONFIG_DBNAME,
			CONFIG_DBSCHEMA, CONFIG_DBSOCKET, CONFIG_DBPORT);
}

/******************************************************************************
 *                                                                            *
 * Function: DBtxn_operation                                                  *
 *                                                                            *
 * Purpose: helper function to loop transaction operation while DB is down    *
 *                                                                            *
 * Author: Eugene Grigorjev, Vladimir Levijev                                 *
 *                                                                            *
 ******************************************************************************/
static void	DBtxn_operation(int (*txn_operation)())
{
	int	rc;

	rc = txn_operation();

	while (ZBX_DB_DOWN == rc)
	{
		DBclose();
		DBconnect(ZBX_DB_CONNECT_NORMAL);

		if (ZBX_DB_DOWN == (rc = txn_operation()))
		{
			zabbix_log(LOG_LEVEL_WARNING, "Database is down. Retrying in %d seconds.", ZBX_DB_WAIT_DOWN);
			sleep(ZBX_DB_WAIT_DOWN);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: DBbegin                                                          *
 *                                                                            *
 * Purpose: start a transaction                                               *
 *                                                                            *
 * Author: Eugene Grigorjev, Vladimir Levijev                                 *
 *                                                                            *
 * Comments: do nothing if DB does not support transactions                   *
 *                                                                            *
 ******************************************************************************/
void	DBbegin()
{
	DBtxn_operation(zbx_db_begin);
}

/******************************************************************************
 *                                                                            *
 * Function: DBcommit                                                         *
 *                                                                            *
 * Purpose: commit a transaction                                              *
 *                                                                            *
 * Author: Eugene Grigorjev, Vladimir Levijev                                 *
 *                                                                            *
 * Comments: do nothing if DB does not support transactions                   *
 *                                                                            *
 ******************************************************************************/
void	DBcommit()
{
	DBtxn_operation(zbx_db_commit);
}

/******************************************************************************
 *                                                                            *
 * Function: DBrollback                                                       *
 *                                                                            *
 * Purpose: rollback a transaction                                            *
 *                                                                            *
 * Author: Eugene Grigorjev, Vladimir Levijev                                 *
 *                                                                            *
 * Comments: do nothing if DB does not support transactions                   *
 *                                                                            *
 ******************************************************************************/
void	DBrollback()
{
	DBtxn_operation(zbx_db_rollback);
}

/******************************************************************************
 *                                                                            *
 * Function: DBexecute                                                        *
 *                                                                            *
 * Purpose: execute a non-select statement                                    *
 *                                                                            *
 * Comments: retry until DB is up                                             *
 *                                                                            *
 ******************************************************************************/
int	__zbx_DBexecute(const char *fmt, ...)
{
	va_list	args;
	int	rc = ZBX_DB_DOWN;

	va_start(args, fmt);

	rc = zbx_db_vexecute(fmt, args);

	while (ZBX_DB_DOWN == rc)
	{
		DBclose();
		DBconnect(ZBX_DB_CONNECT_NORMAL);

		if (ZBX_DB_DOWN == (rc = zbx_db_vexecute(fmt, args)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "Database is down. Retrying in %d seconds.", ZBX_DB_WAIT_DOWN);
			sleep(ZBX_DB_WAIT_DOWN);
		}
	}

	va_end(args);

	return rc;
}

int	DBis_null(const char *field)
{
	return zbx_db_is_null(field);
}

DB_ROW	DBfetch(DB_RESULT result)
{
	return zbx_db_fetch(result);
}

/******************************************************************************
 *                                                                            *
 * Function: DBselect_once                                                    *
 *                                                                            *
 * Purpose: execute a select statement                                        *
 *                                                                            *
 ******************************************************************************/
DB_RESULT	__zbx_DBselect_once(const char *fmt, ...)
{
	va_list		args;
	DB_RESULT	rc;

	va_start(args, fmt);

	rc = zbx_db_vselect(fmt, args);

	va_end(args);

	return rc;
}

/******************************************************************************
 *                                                                            *
 * Function: DBselect                                                         *
 *                                                                            *
 * Purpose: execute a select statement                                        *
 *                                                                            *
 * Comments: retry until DB is up                                             *
 *                                                                            *
 ******************************************************************************/
DB_RESULT	__zbx_DBselect(const char *fmt, ...)
{
	va_list		args;
	DB_RESULT	rc;

	va_start(args, fmt);

	rc = zbx_db_vselect(fmt, args);

	while ((DB_RESULT)ZBX_DB_DOWN == rc)
	{
		DBclose();
		DBconnect(ZBX_DB_CONNECT_NORMAL);

		if ((DB_RESULT)ZBX_DB_DOWN == (rc = zbx_db_vselect(fmt, args)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "Database is down. Retrying in %d seconds.", ZBX_DB_WAIT_DOWN);
			sleep(ZBX_DB_WAIT_DOWN);
		}
	}

	va_end(args);

	return rc;
}

/******************************************************************************
 *                                                                            *
 * Function: DBselectN                                                        *
 *                                                                            *
 * Purpose: execute a select statement and get the first N entries            *
 *                                                                            *
 * Comments: retry until DB is up                                             *
 *                                                                            *
 ******************************************************************************/
DB_RESULT	DBselectN(const char *query, int n)
{
	DB_RESULT rc;

	rc = zbx_db_select_n(query, n);

	while ((DB_RESULT)ZBX_DB_DOWN == rc)
	{
		DBclose();
		DBconnect(ZBX_DB_CONNECT_NORMAL);

		if ((DB_RESULT)ZBX_DB_DOWN == (rc = zbx_db_select_n(query, n)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "Database is down. Retrying in %d seconds.", ZBX_DB_WAIT_DOWN);
			sleep(ZBX_DB_WAIT_DOWN);
		}
	}

	return rc;
}

/* SUCCEED if latest service alarm has this status */
/* Rewrite required to simplify logic ?*/
int	latest_service_alarm(zbx_uint64_t serviceid, int status)
{
	const char	*__function_name = "latest_service_alarm";
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = FAIL;
	char		sql[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(): serviceid [" ZBX_FS_UI64 "] status [%d]",
			__function_name, serviceid, status);

	zbx_snprintf(sql, sizeof(sql), "select servicealarmid,value"
					" from service_alarms"
					" where serviceid=" ZBX_FS_UI64
					" order by servicealarmid desc", serviceid);

	result = DBselectN(sql, 1);
	row = DBfetch(result);

	if (NULL != row && FAIL == DBis_null(row[1]) && status == atoi(row[1]))
		ret = SUCCEED;

	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

int	DBadd_service_alarm(zbx_uint64_t serviceid, int status, int clock)
{
	const char	*__function_name = "DBadd_service_alarm";

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != latest_service_alarm(serviceid, status))
	{
		DBexecute("insert into service_alarms (servicealarmid,serviceid,clock,value)"
				" values(" ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d,%d)",
				DBget_maxid("service_alarms"), serviceid, clock, status);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

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
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments: Recursive function!                                              *
 *                                                                            *
 ******************************************************************************/
static int	trigger_dependent_rec(zbx_uint64_t triggerid, int level)
{
	const char	*__function_name = "trigger_dependent_rec";
	int		ret = FAIL;
	DB_RESULT	result;
	DB_ROW		row;
	unsigned char	value;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() triggerid:" ZBX_FS_UI64 " level:%d", __function_name, triggerid, level);

	if (32 < level)
	{
		zabbix_log(LOG_LEVEL_CRIT, "Recursive trigger dependency detected! Please fix. Triggerid:" ZBX_FS_UI64,
				triggerid);
		goto exit;
	}

	result = DBselect(
			"select t.triggerid,t.value"
			" from trigger_depends d,triggers t"
			" where d.triggerid_up=t.triggerid"
				" and d.triggerid_down=" ZBX_FS_UI64,
			triggerid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(triggerid, row[0]);
		value = (unsigned char)atoi(row[1]);

		if (TRIGGER_VALUE_TRUE == value || SUCCEED == trigger_dependent_rec(triggerid, level + 1))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "This trigger depends on " ZBX_FS_UI64 ". Will not apply actions",
					triggerid);
			ret = SUCCEED;
			break;
		}
	}
	DBfree_result(result);
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

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
 ******************************************************************************/
static int	trigger_dependent(zbx_uint64_t triggerid)
{
	const char	*__function_name = "trigger_dependent";
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() triggerid:" ZBX_FS_UI64, __function_name, triggerid);

	ret = trigger_dependent_rec(triggerid, 0);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_trigger_update_sql                                         *
 *                                                                            *
 * Purpose: generates sql statement for updating trigger value                *
 *                                                                            *
 * Parameters: add_event - [OUT] 0 - do not add event                         *
 *                               1 - generate new event                       *
 *                                                                            *
 * Return value: SUCCEED - sql statement generated successfully               *
 *               FAIL    - trigger update isn't required                      *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: do not update value if there are dependencies with value PROBLEM *
 *                                                                            *
 ******************************************************************************/
int	DBget_trigger_update_sql(char **sql, int *sql_alloc, int *sql_offset, zbx_uint64_t triggerid,
		unsigned char type, int value, const char *error, int new_value, const char *new_error, int lastchange,
		unsigned char *add_event)
{
	const char	*__function_name = "DBget_trigger_update_sql";
	char		*new_error_esc;
	int		generate_event, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() triggerid:" ZBX_FS_UI64 " old:%d new:%d now:%d",
			__function_name, triggerid, value, new_value, lastchange);

	*add_event = 0;

	generate_event = (value != new_value);
	if (TRIGGER_TYPE_MULTIPLE_TRUE == type && 0 == generate_event)
		generate_event = (TRIGGER_VALUE_TRUE == new_value);

	if (0 != generate_event)
	{
		if (SUCCEED != trigger_dependent(triggerid))
		{
			if (NULL == *sql)
			{
				*sql_alloc = 2 * ZBX_KIBIBYTE;
				*sql = zbx_malloc(*sql, *sql_alloc);
			}

			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, 42, "update triggers set lastchange=%d", lastchange);

			if (value != new_value)
				zbx_snprintf_alloc(sql, sql_alloc, sql_offset, 18, ",value=%d", new_value);

			if (NULL == new_error)
			{
				if ('\0' != *error)
					zbx_snprintf_alloc(sql, sql_alloc, sql_offset, 10, ",error=''");
			}
			else if (0 != strcmp(error, new_error))
			{
				new_error_esc = DBdyn_escape_string_len(new_error, TRIGGER_ERROR_LEN);
				zbx_snprintf_alloc(sql, sql_alloc, sql_offset, 10 + strlen(new_error_esc),
						",error='%s'", new_error_esc);
				zbx_free(new_error_esc);
			}

			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, 38, " where triggerid=" ZBX_FS_UI64, triggerid);

			*add_event = 1;	/* create event */

			ret = SUCCEED;
		}
	}
	else if (new_value == TRIGGER_VALUE_UNKNOWN && 0 != strcmp(error, new_error))
	{
		if (SUCCEED != trigger_dependent(triggerid))
		{
			if (NULL == *sql)
			{
				*sql_alloc = 2 * ZBX_KIBIBYTE;
				*sql = zbx_malloc(*sql, *sql_alloc);
			}

			new_error_esc = DBdyn_escape_string_len(new_error, TRIGGER_ERROR_LEN);
			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, 66 + strlen(new_error_esc),
					"update triggers"
					" set error='%s'"
					" where triggerid=" ZBX_FS_UI64,
					new_error_esc, triggerid);
			zbx_free(new_error_esc);

			ret = SUCCEED;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

void	DBdelete_service(zbx_uint64_t serviceid)
{
	DBexecute("delete from services_links where servicedownid=" ZBX_FS_UI64 " or serviceupid=" ZBX_FS_UI64,
			serviceid, serviceid);
	DBexecute("delete from services where serviceid=" ZBX_FS_UI64, serviceid);
}

void	DBdelete_services_by_triggerid(zbx_uint64_t triggerid)
{
	const char	*__function_name = "DBdelete_services_by_triggerid";

	zbx_uint64_t	serviceid;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG,"In %s() triggerid:" ZBX_FS_UI64, __function_name, triggerid);

	result = DBselect("select serviceid from services where triggerid=" ZBX_FS_UI64, triggerid);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(serviceid, row[0]);
		DBdelete_service(serviceid);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

void	DBdelete_trigger(zbx_uint64_t triggerid)
{
	DBexecute("delete from trigger_depends where triggerid_down=" ZBX_FS_UI64 " or triggerid_up=" ZBX_FS_UI64,
			triggerid, triggerid);
	DBexecute("delete from functions where triggerid=" ZBX_FS_UI64,	triggerid);
	DBexecute("delete from events where object=%d and objectid=" ZBX_FS_UI64, EVENT_OBJECT_TRIGGER, triggerid);

	DBdelete_services_by_triggerid(triggerid);

	DBexecute("delete from sysmaps_link_triggers where triggerid=" ZBX_FS_UI64,triggerid);
	DBexecute("delete from triggers where triggerid=" ZBX_FS_UI64,triggerid);
}

void	DBupdate_triggers_status_after_restart()
{
	const char		*__function_name = "DBupdate_triggers_status_after_restart";
	DB_RESULT		result;
	DB_RESULT		result2;
	DB_ROW			row;
	DB_ROW			row2;
	zbx_uint64_t		itemid, triggerid, eventid;
	int			trigger_type, trigger_value, type, lastclock, delay,
				nextcheck, min_nextcheck, now, i;
	const char		*trigger_error;
	char			*sql = NULL;
	int			sql_alloc = 16 * ZBX_KIBIBYTE, sql_offset = 0;
	unsigned char		add_event;
	DB_TRIGGER_UPDATE	*tr = NULL;
	int			tr_alloc = 0, tr_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	now = time(NULL);

	sql = zbx_malloc(sql, sql_alloc);
#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 7, "begin\n");
#endif

	DBbegin();

	result = DBselect(
			"select distinct t.triggerid,t.type,t.value,t.error"
			" from hosts h,items i,functions f,triggers t"
			" where h.hostid=i.hostid"
				" and i.itemid=f.itemid"
				" and i.lastclock is not null"
				" and f.triggerid=t.triggerid"
				" and h.status in (%d)"
				" and i.status in (%d)"
				" and i.type not in (%d)"
				" and i.key_ not in ('%s','%s')"
				" and t.status in (%d)"
				DB_NODE,
			HOST_STATUS_MONITORED,
			ITEM_STATUS_ACTIVE,
			ITEM_TYPE_TRAPPER,
			SERVER_STATUS_KEY, SERVER_ZABBIXLOG_KEY,
			TRIGGER_STATUS_ENABLED,
			DBnode_local("t.triggerid"));

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

			nextcheck = calculate_item_nextcheck(itemid, type, delay, row2[4], lastclock, NULL);
			if (-1 == min_nextcheck || nextcheck < min_nextcheck)
				min_nextcheck = nextcheck;
		}
		DBfree_result(result2);

		if (-1 == min_nextcheck || min_nextcheck >= now)
			continue;

		if (SUCCEED == DBget_trigger_update_sql(&sql, &sql_alloc, &sql_offset, triggerid, trigger_type,
				trigger_value, trigger_error, TRIGGER_VALUE_UNKNOWN, "Zabbix was restarted.",
				min_nextcheck, &add_event))
		{
			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 3, ";\n");

			DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
		}

		if (1 == add_event)
		{
			if (tr_num == tr_alloc)
			{
				tr_alloc += 64;
				tr = zbx_realloc(tr, tr_alloc * sizeof(DB_TRIGGER_UPDATE));
			}

			tr[tr_num].triggerid = triggerid;
			tr[tr_num].lastchange = min_nextcheck;
			tr_num++;
		}
	}
	DBfree_result(result);

#ifdef HAVE_ORACLE
	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 6, "end;\n");
#endif

	if (sql_offset > 16)	/* in ORACLE always present begin..end; */
		DBexecute("%s", sql);

	zbx_free(sql);

	if (0 != tr_num)
	{
		eventid = DBget_maxid_num("events", tr_num);

		for (i = 0; i < tr_num; i++)
		{
			process_event(eventid++, EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER, tr[i].triggerid,
					tr[i].lastchange, TRIGGER_VALUE_UNKNOWN, 0, 0);
		}
	}

	zbx_free(tr);

	DBcommit();

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

int	DBadd_trend(zbx_uint64_t itemid, double value, int clock)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		hour, num;
	double		value_min, value_avg, value_max;

	zabbix_log(LOG_LEVEL_DEBUG, "In add_trend()");

	hour = clock - clock % SEC_PER_HOUR;

	result = DBselect("select num,value_min,value_avg,value_max from trends where itemid=" ZBX_FS_UI64 " and clock=%d",
			itemid, hour);

	if (NULL != (row = DBfetch(result)))
	{
		num = atoi(row[0]);
		value_min = atof(row[1]);
		value_avg = atof(row[2]);
		value_max = atof(row[3]);
		if (value < value_min)
			value_min=value;
		if (value > value_max)
			value_max=value;
		value_avg=(num*value_avg+value)/(num+1);
		num++;
		DBexecute("update trends set num=%d,value_min=" ZBX_FS_DBL ",value_avg=" ZBX_FS_DBL ",value_max=" ZBX_FS_DBL
				" where itemid=" ZBX_FS_UI64 " and clock=%d",
				num, value_min, value_avg, value_max, itemid, hour);
	}
	else
	{
		DBexecute("insert into trends (clock,itemid,num,value_min,value_avg,value_max)"
				" values (%d," ZBX_FS_UI64 ",%d," ZBX_FS_DBL "," ZBX_FS_DBL "," ZBX_FS_DBL ")",
				hour, itemid, 1, value, value, value);
	}

	DBfree_result(result);

	return SUCCEED;
}

int	DBadd_trend_uint(zbx_uint64_t itemid, zbx_uint64_t value, int clock)
{
	DB_RESULT	result;
	DB_ROW		row;
	int		hour, num;
	zbx_uint64_t	value_min, value_avg, value_max;

	zabbix_log(LOG_LEVEL_DEBUG,"In add_trend_uint()");

	hour=clock-clock%SEC_PER_HOUR;

	result = DBselect("select num,value_min,value_avg,value_max from trends_uint where itemid=" ZBX_FS_UI64 " and clock=%d",
		itemid,
		hour);

	row=DBfetch(result);

	if(row)
	{
		num = atoi(row[0]);
		ZBX_STR2UINT64(value_min, row[1]);
		ZBX_STR2UINT64(value_avg, row[2]);
		ZBX_STR2UINT64(value_max, row[3]);
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

int	DBget_row_count(const char *table_name)
{
	const char	*__function_name = "DBget_row_count";
	int		count = 0;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() table_name:'%s'", __function_name, table_name);

	result = DBselect("select count(*) from %s", table_name);

	if (NULL != (row = DBfetch(result)))
		count = atoi(row[0]);
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, count);

	return count;
}

int	DBget_items_unsupported_count()
{
	const char	*__function_name = "DBget_items_unsupported_count";
	int		count = 0;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect("select count(*) from items where status=%d", ITEM_STATUS_NOTSUPPORTED);

	if (NULL != (row = DBfetch(result)))
		count = atoi(row[0]);
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, count);

	return count;
}

int	DBget_queue_count(int from, int to)
{
	const char	*__function_name = "DBget_queue_count";
	int		count = 0, now;
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	itemid, proxy_hostid;
	int		item_type, delay, effective_delay, nextcheck;
	char		*delay_flex;
	time_t		lastclock;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(): from [%d] to [%d]", __function_name, from, to);

	now = time(NULL);

	result = DBselect(
			"select i.itemid,i.type,i.delay,i.delay_flex,i.lastclock,h.proxy_hostid"
			" from items i,hosts h"
			" where i.hostid=h.hostid"
				" and h.status=%d"
				" and i.status=%d"
				" and i.value_type not in (%d)"
				" and i.key_ not in ('%s','%s')"
				" and ("
					"i.lastclock is not null"
					" and i.lastclock<%d"
					")"
				" and ("
					"i.type in (%d,%d,%d,%d,%d,%d,%d,%d,%d)"
					" or (h.available<>%d and i.type in (%d))"
					" or (h.snmp_available<>%d and i.type in (%d,%d,%d))"
					" or (h.ipmi_available<>%d and i.type in (%d))"
					")"
				DB_NODE,
			HOST_STATUS_MONITORED,
			ITEM_STATUS_ACTIVE,
			ITEM_VALUE_TYPE_LOG,
			SERVER_STATUS_KEY, SERVER_ZABBIXLOG_KEY,
			now - from,
				ITEM_TYPE_ZABBIX_ACTIVE, ITEM_TYPE_SSH, ITEM_TYPE_TELNET,
				ITEM_TYPE_SIMPLE, ITEM_TYPE_INTERNAL, ITEM_TYPE_DB_MONITOR,
				ITEM_TYPE_AGGREGATE, ITEM_TYPE_EXTERNAL, ITEM_TYPE_CALCULATED,
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
		ZBX_STR2UINT64(proxy_hostid, row[5]);

		if (FAIL == (lastclock = DCget_item_lastclock(itemid)))
			lastclock = (time_t)atoi(row[4]);

		nextcheck = calculate_item_nextcheck(itemid, item_type, delay, delay_flex, lastclock, &effective_delay);
		if (0 != proxy_hostid)
			nextcheck = lastclock + effective_delay;

		if ((-1 == from || from <= now - nextcheck) && (-1 == to || now - nextcheck <= to))
			count++;
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(): %d", __function_name, count);

	return count;
}

double	DBget_requiredperformance()
{
	const char	*__function_name = "DBget_requiredperformance";
	double		qps_total = 0;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	/* !!! Don't forget sync code with PHP !!! */
	result = DBselect("select sum(1.0/i.delay) from hosts h,items i"
			" where h.hostid=i.hostid and h.status=%d and i.status=%d and i.delay<>0",
			HOST_STATUS_MONITORED,
			ITEM_STATUS_ACTIVE);
	if (NULL != (row = DBfetch(result)) && SUCCEED != DBis_null(row[0]))
		qps_total = atof(row[0]);
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s(): " ZBX_FS_DBL, __function_name, qps_total);

	return qps_total;
}

int	DBget_proxy_lastaccess(const char *hostname, int *lastaccess, char **error)
{
	const char	*__function_name = "DBget_proxy_lastaccess";
	DB_RESULT	result;
	DB_ROW		row;
	char		*host_esc;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	host_esc = DBdyn_escape_string(hostname);
	result = DBselect("select lastaccess from hosts where host='%s' and status in (%d,%d)",
			host_esc, HOST_STATUS_PROXY_ACTIVE, HOST_STATUS_PROXY_PASSIVE);
	zbx_free(host_esc);

	if (NULL != (row = DBfetch(result)))
	{
		*lastaccess = atoi(row[0]);
		ret = SUCCEED;
	}
	else
		*error = zbx_dsprintf(*error, "Proxy \"%s\" does not exist", hostname);
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
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

	escalationid = DBget_maxid("escalations");

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

void	DBvacuum()
{
#ifdef	HAVE_POSTGRESQL
	char	*table_for_housekeeping[] = {"services", "services_links", "graphs_items", "graphs", "sysmaps_links",
			"sysmaps_elements", "sysmaps_link_triggers","sysmaps", "config", "groups", "hosts_groups", "alerts",
			"actions", "events", "functions", "history", "history_str", "hosts", "trends",
			"items", "media", "media_type", "triggers", "trigger_depends", "users",
			"sessions", "rights", "service_alarms", "profiles", "screens", "screens_items",
			NULL};

	char	*table;
	int	i;

	zbx_setproctitle("housekeeper [vacuum DB]");

	i = 0;
	while (NULL != (table = table_for_housekeeping[i++]))
	{
		DBexecute("vacuum analyze %s", table);
	}
#endif
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_escape_string_len                                          *
 *                                                                            *
 * Return value: return length in bytes of escaped string                     *
 *               with terminating '\0'                                        *
 *                                                                            *
 * Author: Aleksandrs Saveljevs                                               *
 *                                                                            *
 * Comments: sync changes with 'DBescape_string'                              *
 *           and 'DBdyn_escape_string_len'                                    *
 *                                                                            *
 ******************************************************************************/
static size_t	DBget_escape_string_len(const char *src)
{
	const char	*s;
	size_t		len = 1;	/* '\0' */

	for (s = src; NULL != s && '\0' != *s; s++)
	{
		if ('\r' == *s)
			continue;
#if defined(HAVE_MYSQL)
		if ('\'' == *s || '\\' == *s)
#elif defined(HAVE_POSTGRESQL)
		if ('\'' == *s || ('\\' == *s && 1 == ZBX_PG_ESCAPE_BACKSLASH))
#else
		if ('\'' == *s)
#endif
			len++;

		len++;
	}

	return len;
}

/******************************************************************************
 *                                                                            *
 * Function: DBescape_string                                                  *
 *                                                                            *
 * Return value: escaped string                                               *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: sync changes with 'DBget_escape_string_len'                      *
 *           and 'DBdyn_escape_string_len'                                    *
 *                                                                            *
 ******************************************************************************/
static void	DBescape_string(const char *src, char *dst, size_t len)
{
	const char	*s;
	char		*d;
#if defined(HAVE_MYSQL)
#	define ZBX_DB_ESC_CH	'\\'
#elif !defined(HAVE_POSTGRESQL)
#	define ZBX_DB_ESC_CH	'\''
#endif
	assert(dst);

	len--;	/* '\0' */

	for (s = src, d = dst; NULL != s && '\0' != *s && 0 < len; s++)
	{
		if ('\r' == *s)
			continue;

#if defined(HAVE_MYSQL)
		if ('\'' == *s || '\\' == *s)
#elif defined(HAVE_POSTGRESQL)
		if ('\'' == *s || ('\\' == *s && 1 == ZBX_PG_ESCAPE_BACKSLASH))
#else
		if ('\'' == *s)
#endif
		{
			if (2 > len)
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
 * Return value: escaped string                                               *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
char	*DBdyn_escape_string(const char *src)
{
	size_t	len;
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
 * Return value: escaped string                                               *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
char	*DBdyn_escape_string_len(const char *src, size_t max_src_len)
{
	const char	*s;
	char		*dst = NULL;
	size_t		len = 1;	/* '\0' */

	max_src_len++;

	for (s = src; NULL != s && '\0' != *s && 0 < max_src_len; s++)
	{
		if ('\r' == *s)
			continue;

		/* only UTF-8 characters should reduce a variable max_src_len */
		if (0x80 != (0xc0 & *s) && 0 == --max_src_len)
			break;

#if defined(HAVE_MYSQL)
		if ('\'' == *s || '\\' == *s)
#elif defined(HAVE_POSTGRESQL)
		if ('\'' == *s || ('\\' == *s && 1 == ZBX_PG_ESCAPE_BACKSLASH))
#else
		if ('\'' == *s)
#endif
			len++;

		len++;
	}

	dst = zbx_malloc(dst, len);

	DBescape_string(src, dst, len);

	return dst;
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_escape_like_pattern_len                                    *
 *                                                                            *
 * Return value: return length of escaped LIKE pattern with terminating '\0'  *
 *                                                                            *
 * Author: Aleksandrs Saveljevs                                               *
 *                                                                            *
 * Comments: sync changes with 'DBescape_like_pattern'                        *
 *                                                                            *
 ******************************************************************************/
static int	DBget_escape_like_pattern_len(const char *src)
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
 *           ... LIKE 'a!_b!%c\d''e!!f' ESCAPE '!' (IBM DB2, Oracle, SQLite3) *
 *                                                                            *
 *           Using backslash as escape character in LIKE would be too much    *
 *           trouble, because escaping backslashes would have to be escaped   *
 *           as well, like so:                                                *
 *                                                                            *
 *           ... LIKE 'a\\_b\\%c\\\\d\'e!f' ESCAPE '\\' or                    *
 *           ... LIKE 'a\\_b\\%c\\\\d\\\'e!f' ESCAPE '\\' (MySQL, PostgreSQL) *
 *           ... LIKE 'a\_b\%c\\d''e!f' ESCAPE '\' (IBM DB2, Oracle, SQLite3) *
 *                                                                            *
 *           Hence '!' instead of backslash.                                  *
 *                                                                            *
 ******************************************************************************/
static void	DBescape_like_pattern(const char *src, char *dst, int len)
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
 * Return value: escaped string to be used as pattern in LIKE                 *
 *                                                                            *
 * Author: Aleksandrs Saveljevs                                               *
 *                                                                            *
 ******************************************************************************/
char	*DBdyn_escape_like_pattern(const char *src)
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

	if (SUCCEED != DBis_null(row[10]))
		item->lastvalue[0] = row[10];
	else
		item->lastvalue[0] = NULL;

	if (SUCCEED != DBis_null(row[11]))
		item->lastvalue[1] = row[11];
	else
		item->lastvalue[1] = NULL;

	ZBX_STR2UINT64(item->hostid, row[12]);
	item->delta			= atoi(row[14]);

	if (SUCCEED != DBis_null(row[15]))
	{
		item->prevorgvalue_null = 0;

		switch (item->value_type)
		{
			case ITEM_VALUE_TYPE_FLOAT:
				item->prevorgvalue.dbl = atof(row[15]);
				break;
			case ITEM_VALUE_TYPE_UINT64:
				ZBX_STR2UINT64(item->prevorgvalue.ui64, row[15]);
				break;
			default:
				item->prevorgvalue.str = row[15];
				break;
		}
	}
	else
		item->prevorgvalue_null = 1;

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

	item->h_lastvalue[0] = NULL;
	item->h_lastvalue[1] = NULL;
	item->h_lasteventid = NULL;
	item->h_lastsource = NULL;
	item->h_lastseverity = NULL;

	key = zbx_dsprintf(key, "%s", item->key_orig);
	substitute_simple_macros(NULL, item, NULL, NULL, NULL, &key, MACRO_TYPE_ITEM_KEY, NULL, 0);
	item->key = key;
}

void	DBfree_item_from_db(DB_ITEM *item)
{
	zbx_free(item->h_lastvalue[0]);
	zbx_free(item->h_lastvalue[1]);
	zbx_free(item->h_lasteventid);
	zbx_free(item->h_lastsource);
	zbx_free(item->h_lastseverity);
}

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

static zbx_uint64_t	DBget_nextid(const char *tablename, int num)
{
	const char	*__function_name = "DBget_nextid";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	ret1, ret2;
	zbx_uint64_t	min, max;
	int		found = FAIL, dbres, nodeid;
	const ZBX_TABLE	*table;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tablename:'%s'", __function_name, tablename);

	table = DBget_table(tablename);
	nodeid = 0 <= CONFIG_NODEID ? CONFIG_NODEID : 0;

	if (0 != (table->flags & ZBX_SYNC))
	{
		min = (zbx_uint64_t)__UINT64_C(100000000000000)*(zbx_uint64_t)nodeid+(zbx_uint64_t)__UINT64_C(100000000000)*(zbx_uint64_t)nodeid;
		max = (zbx_uint64_t)__UINT64_C(100000000000000)*(zbx_uint64_t)nodeid+(zbx_uint64_t)__UINT64_C(100000000000)*(zbx_uint64_t)nodeid+(zbx_uint64_t)__UINT64_C(99999999999);
	}
	else
	{
		min = (zbx_uint64_t)__UINT64_C(100000000000000)*(zbx_uint64_t)nodeid;
		max = (zbx_uint64_t)__UINT64_C(100000000000000)*(zbx_uint64_t)nodeid+(zbx_uint64_t)__UINT64_C(99999999999999);
	}

	do
	{
		/* avoid eternal loop within failed transaction */
		if (0 < zbx_db_txn_level() && 0 != zbx_db_txn_error())
		{
			zabbix_log(LOG_LEVEL_DEBUG, "End of %s() transaction failed", __function_name);
			return 0;
		}

		result = DBselect("select nextid from ids where nodeid=%d and table_name='%s' and field_name='%s'",
				nodeid, table->table, table->recid);

		if (NULL == (row = DBfetch(result)))
		{
			DBfree_result(result);

			result = DBselect("select max(%s) from %s where %s between " ZBX_FS_UI64 " and " ZBX_FS_UI64,
					table->recid, table->table, table->recid, min, max);

			if (NULL == (row = DBfetch(result)) || SUCCEED == DBis_null(row[0]))
			{
				ret1 = min;
			}
			else
			{
				ZBX_STR2UINT64(ret1, row[0]);
				if (ret1 >= max)
				{
					zabbix_log(LOG_LEVEL_CRIT, "maximum number of id's exceeded"
							" [table:%s, field:%s, id:" ZBX_FS_UI64 "]",
							table->table, table->recid, ret1);
					exit(FAIL);
				}
			}
			DBfree_result(result);

			dbres = DBexecute("insert into ids (nodeid,table_name,field_name,nextid)"
					" values (%d,'%s','%s'," ZBX_FS_UI64 ")",
					nodeid, table->table, table->recid, ret1);

			if (ZBX_DB_OK > dbres)
			{
				/* solving the problem of an invisible record created in a parallel transaction */
				DBexecute("update ids set nextid=nextid+1 where nodeid=%d and table_name='%s'"
						" and field_name='%s'",
						nodeid, table->table, table->recid);
			}

			continue;
		}
		else
		{
			ZBX_STR2UINT64(ret1, row[0]);
			DBfree_result(result);

			if (ret1 < min || ret1 >= max)
			{
				DBexecute("delete from ids where nodeid=%d and table_name='%s' and field_name='%s'",
						nodeid, table->table, table->recid);
				continue;
			}

			DBexecute("update ids set nextid=nextid+%d where nodeid=%d and table_name='%s' and field_name='%s'",
					num, nodeid, table->table, table->recid);

			result = DBselect("select nextid from ids where nodeid=%d and table_name='%s' and field_name='%s'",
					nodeid, table->table, table->recid);

			if (NULL == (row = DBfetch(result)) || SUCCEED == DBis_null(row[0]))
			{
				THIS_SHOULD_NEVER_HAPPEN;
				DBfree_result(result);
				continue;
			}
			else
			{
				ZBX_STR2UINT64(ret2, row[0]);
				DBfree_result(result);
				if (ret1 + num == ret2)
					found = SUCCEED;
			}
		}
	}
	while (FAIL == found);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():" ZBX_FS_UI64 " table:'%s' recid:'%s'",
			__function_name, ret2 - num + 1, table->table, table->recid);

	return ret2 - num + 1;
}

zbx_uint64_t	DBget_maxid_num(const char *tablename, int num)
{
	if (0 == strcmp(tablename, "history_log") ||
			0 == strcmp(tablename, "history_text") ||
			0 == strcmp(tablename, "dservices") ||
			0 == strcmp(tablename, "dhosts") ||
			0 == strcmp(tablename, "alerts") ||
			0 == strcmp(tablename, "escalations") ||
			0 == strcmp(tablename, "autoreg_host"))
		return DCget_nextid(tablename, num);

	return DBget_nextid(tablename, num);
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
 * Return value: <host> or "???" if host not found                            *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
const char	*zbx_host_string(zbx_uint64_t hostid)
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
 * Return value: <host>:<key> or "???" if item not found                      *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
const char	*zbx_host_key_string(zbx_uint64_t itemid)
{
	DB_RESULT	result;
	DB_ROW		row;

	result = DBselect(
			"select h.host,i.key_"
			" from hosts h,items i"
			" where h.hostid=i.hostid"
				" and i.itemid=" ZBX_FS_UI64,
			itemid);

	if (NULL != (row = DBfetch(result)))
		zbx_snprintf(buf_string, sizeof(buf_string), "%s:%s", row[0], row[1]);
	else
		zbx_snprintf(buf_string, sizeof(buf_string), "???");

	DBfree_result(result);

	return buf_string;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_host_key_string_by_item                                      *
 *                                                                            *
 * Return value: <host>:<key>                                                 *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
const char	*zbx_host_key_string_by_item(DB_ITEM *item)
{
	zbx_snprintf(buf_string, sizeof(buf_string), "%s:%s", item->host_name, item->key);

	return buf_string;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_user_string                                                  *
 *                                                                            *
 * Return value: "Name Surname (Alias)" or "unknown" if user not found        *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
const char	*zbx_user_string(zbx_uint64_t userid)
{
	DB_RESULT	result;
	DB_ROW		row;

	result = DBselect("select name,surname,alias from users where userid=" ZBX_FS_UI64,
			userid);

	if (NULL != (row = DBfetch(result)))
		zbx_snprintf(buf_string, sizeof(buf_string), "%s %s (%s)", row[0], row[1], row[2]);
	else
		zbx_snprintf(buf_string, sizeof(buf_string), "unknown");

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

	zabbix_log(LOG_LEVEL_DEBUG, "DBmultiply_value_uint64() " ZBX_FS_UI64 ",%s " ZBX_FS_UI64,
			value, item->formula, value_uint64);

	return value_uint64;
}

/******************************************************************************
 *                                                                            *
 * Function: DBregister_host                                                  *
 *                                                                            *
 * Purpose: register unknown host and generate event                          *
 *                                                                            *
 * Parameters: host - host name                                               *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	DBregister_host(zbx_uint64_t proxy_hostid, const char *host, int now)
{
	char		*host_esc;
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	autoreg_hostid;
	int		res = SUCCEED;

	host_esc = DBdyn_escape_string_len(host, HOST_HOST_LEN);

	if (0 != proxy_hostid)
	{
		result = DBselect(
				"select hostid"
				" from hosts"
				" where proxy_hostid=" ZBX_FS_UI64
					" and host='%s'"
					DB_NODE,
				proxy_hostid, host_esc,
				DBnode_local("hostid"));

		if (NULL != DBfetch(result))
			res = FAIL;
		DBfree_result(result);
	}

	if (SUCCEED == res)
	{
		result = DBselect(
				"select autoreg_hostid"
				" from autoreg_host"
				" where proxy_hostid=" ZBX_FS_UI64
					" and host='%s'"
					DB_NODE,
				proxy_hostid, host_esc,
				DBnode_local("autoreg_hostid"));

		if (NULL != (row = DBfetch(result)))
			ZBX_STR2UINT64(autoreg_hostid, row[0]);
		else
		{
			autoreg_hostid = DBget_maxid("autoreg_host");
			DBexecute("insert into autoreg_host"
					" (autoreg_hostid,proxy_hostid,host)"
					" values"
					" (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",'%s')",
					autoreg_hostid, proxy_hostid, host_esc);
		}
		DBfree_result(result);

		/* Processing event */
		process_event(0, EVENT_SOURCE_AUTO_REGISTRATION, EVENT_OBJECT_ZABBIX_ACTIVE,
				autoreg_hostid, now, TRIGGER_VALUE_TRUE, 0, 0);
	}

	zbx_free(host_esc);
}

/******************************************************************************
 *                                                                            *
 * Function: DBproxy_register_host                                            *
 *                                                                            *
 * Purpose: registrate unknown host                                           *
 *                                                                            *
 * Parameters: host - host name                                               *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
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
 * Author: Dmitry Borovikov                                                   *
 *                                                                            *
 ******************************************************************************/
void	DBexecute_overflowed_sql(char **sql, int *sql_allocated, int *sql_offset)
{
	if (*sql_offset > ZBX_MAX_SQL_SIZE)
	{
#ifdef HAVE_MULTIROW_INSERT
		if (',' == (*sql)[*sql_offset - 1])
		{
			(*sql_offset)--;
			zbx_snprintf_alloc(sql, sql_allocated, sql_offset, 3, ";\n");
		}
#endif
#ifdef HAVE_ORACLE
		zbx_snprintf_alloc(sql, sql_allocated, sql_offset, 6, "end;\n");
#endif
		DBexecute("%s", *sql);
		*sql_offset = 0;
#ifdef HAVE_ORACLE
		zbx_snprintf_alloc(sql, sql_allocated, sql_offset, 7, "begin\n");
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
	const char	*__function_name = "DBget_unique_hostname_by_sample";
	DB_RESULT	result;
	DB_ROW		row;
	int		num = 2;	/* produce alternatives starting from "2" */
	char		*host_name_temp, *host_name_sample_esc;

	assert(host_name_sample && *host_name_sample);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() sample:'%s'",
			__function_name, host_name_sample);

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
			continue;	/* skip those which are lexicographically smaller */

		if (0 > strcmp(host_name_temp, row[0]))
			break;	/* found, all other will be bigger */

		/* 0 == strcmp(host_name_temp, row[0]) */
		/* must construct bigger one, the constructed one already exists */
		host_name_temp = zbx_dsprintf(host_name_temp, "%s_%d", host_name_sample, num++);
	}
	DBfree_result(result);

	zbx_free(host_name_sample_esc);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() constructed:'%s'",
			__function_name, host_name_temp);

	return host_name_temp;
}

static const char	*get_table_by_value_type(unsigned char value_type)
{
	switch (value_type)
	{
		case ITEM_VALUE_TYPE_FLOAT:
			return "history";
		case ITEM_VALUE_TYPE_STR:
			return "history_str";
		case ITEM_VALUE_TYPE_LOG:
			return "history_log";
		case ITEM_VALUE_TYPE_UINT64:
			return "history_uint";
		case ITEM_VALUE_TYPE_TEXT:
			return "history_text";
		default:
			assert(0);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_history                                                    *
 *                                                                            *
 * Parameters: itemid     - [IN] item identificator from database             *
 *                               required parameter                           *
 *             value_type - [IN] item value type                              *
 *                               required parameter                           *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
char	**DBget_history(zbx_uint64_t itemid, unsigned char value_type, int function, int clock_from, int clock_to,
		const char *field_name, int last_n)
{
	const char	*__function_name = "DBget_history";
	char		sql[512];
	size_t		offset;
	DB_RESULT	result;
	DB_ROW		row;
	char		**h_value = NULL;
	int		h_alloc = 1, h_num = 0, retry = 0;
	const char	*func[] = {"min", "avg", "max", "sum", "count"};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (NULL == field_name)
		field_name = "value";

	if (ZBX_DB_GET_HIST_VALUE == function)
		h_alloc = (0 == last_n ? 128 : last_n);

	h_value = zbx_malloc(h_value, (h_alloc + 1) * sizeof(char *));
retry:
	switch (function)
	{
		case ZBX_DB_GET_HIST_MIN:
		case ZBX_DB_GET_HIST_AVG:
		case ZBX_DB_GET_HIST_MAX:
		case ZBX_DB_GET_HIST_SUM:
			offset = zbx_snprintf(sql, sizeof(sql), "select %s(%s)", func[function], field_name);
			break;
		case ZBX_DB_GET_HIST_COUNT:
			offset = zbx_snprintf(sql, sizeof(sql), "select %s(*)", func[function]);
			break;
		case ZBX_DB_GET_HIST_DELTA:
			offset = zbx_snprintf(sql, sizeof(sql), "select max(%s)-min(%s)", field_name, field_name);
			break;
		case ZBX_DB_GET_HIST_VALUE:
			offset = zbx_snprintf(sql, sizeof(sql), "select %s", field_name);
			break;
		default:
			assert(0);
	}

	offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, " from %s where itemid=" ZBX_FS_UI64,
			get_table_by_value_type(value_type), itemid);
	if (0 != last_n && 0 == clock_from && 0 != clock_to)
	{
		const int	steps[] = {SEC_PER_HOUR, SEC_PER_DAY, SEC_PER_WEEK, SEC_PER_MONTH};

		if (0 != retry)
			clock_to -= steps[retry - 1];
		if (4 != retry)
		{
			offset += zbx_snprintf(sql + offset, sizeof(sql) - offset,
					" and clock>%d", clock_to - steps[retry]);
		}
	}
	if (0 != clock_from)
		offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, " and clock>%d", clock_from);
	if (0 != clock_to)
		offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, " and clock<=%d", clock_to);

	if (0 != last_n)
	{
		switch (value_type)
		{
			case ITEM_VALUE_TYPE_FLOAT:
			case ITEM_VALUE_TYPE_UINT64:
			case ITEM_VALUE_TYPE_STR:
				offset += zbx_snprintf(sql + offset, sizeof(sql) - offset,
						" order by clock desc");
				break;
			default:
				offset += zbx_snprintf(sql + offset, sizeof(sql) - offset,
						" order by id desc");
				break;
		}
		result = DBselectN(sql, last_n - h_num);
	}
	else
		result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)) && SUCCEED != DBis_null(row[0]))
	{
		if (h_alloc == h_num)
		{
			h_alloc = MAX(h_alloc + 1, h_alloc * 3 / 2);
			h_value = zbx_realloc(h_value, (h_alloc + 1) * sizeof(char *));
		}

		h_value[h_num++] = zbx_strdup(NULL, row[0]);
	}
	DBfree_result(result);

	if (0 != last_n && 0 == clock_from && 0 != clock_to && h_num != last_n && 4 != retry)
	{
		retry++;
		goto retry;
	}

	h_value[h_num] = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return h_value;
}

void	DBfree_history(char **h_value)
{
	const char	*__function_name = "DBfree_history";
	int		h_num;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	for (h_num = 0; NULL != h_value[h_num]; h_num++)
		zbx_free(h_value[h_num]);

	zbx_free(h_value);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

int	DBtxn_status()
{
	return 0 == zbx_db_txn_error() ? SUCCEED : FAIL;
}

int	DBtxn_ongoing()
{
	return 0 == zbx_db_txn_level() ? FAIL : SUCCEED;
}
