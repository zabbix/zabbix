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

#include "config.h"

#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <sys/socket.h>
#include <netinet/in.h>

#include <sys/wait.h>

#include <string.h>

#ifdef HAVE_NETDB_H
	#include <netdb.h>
#endif

/* Required for getpwuid */
#include <pwd.h>

#include <signal.h>
#include <errno.h>

#include <time.h>

#include "common.h"
#include "cfg.h"
#include "db.h"
#include "log.h"

#include "housekeeper.h"

/*
static int delete_history(int itemid)
{
	char	sql[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_WARNING,"In delete_history(%d)", itemid);

	snprintf(sql,sizeof(sql)-1,"delete from history where itemid=%d limit 500", itemid);
	DBexecute(sql);

	return DBaffected_rows();
}*/

/*
static int delete_trends(int itemid)
{
	char	sql[MAX_STRING_LEN];

	zabbix_log(LOG_LEVEL_WARNING,"In delete_trends(%d)", itemid);

	snprintf(sql,sizeof(sql)-1,"delete from trends where itemid=%d limit 500", itemid);
	DBexecute(sql);

	return DBaffected_rows();
}
*/

/*
static int delete_item(int itemid)
{
	char	sql[MAX_STRING_LEN];
	int	res = 0;

	zabbix_log(LOG_LEVEL_WARNING,"In delete_item(%d)", itemid);

	res = delete_history(itemid);

	if(res == 0)
	{
		res = delete_trends(itemid);
	}

	if(res == 0)
	{
		DBdelete_triggers_by_itemid(itemid);

		snprintf(sql,sizeof(sql)-1,"delete from items where itemid=%d", itemid);
		DBexecute(sql);
	}

	zabbix_log(LOG_LEVEL_DEBUG,"End of delete_item(%d)", itemid);

	return res;	
}
*/

/*
static int delete_host(int hostid)
{
	int	i, itemid;
	char	sql[MAX_STRING_LEN];
	DB_RESULT	*result;
	int	res = 0;

	zabbix_log(LOG_LEVEL_WARNING,"In delete_host(%d)", hostid);

	snprintf(sql,sizeof(sql)-1,"select itemid from items where hostid=%d", hostid);
	result = DBselect(sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		itemid=atoi(DBget_field(result,i,0));
		res += delete_item(itemid);
	}
	DBfree_result(result);

	if(res==0)
	{
		DBdelete_sysmaps_hosts_by_hostid(hostid);

		snprintf(sql,sizeof(sql)-1,"delete from hosts_groups where hostid=%d", hostid);
		DBexecute(sql);

		snprintf(sql,sizeof(sql)-1,"delete from hosts where hostid=%d", hostid);
		DBexecute(sql);
	}

	zabbix_log(LOG_LEVEL_DEBUG,"End of delete_host(%d)", hostid);

	return res;
}
*/

/******************************************************************************
 *                                                                            *
 * Function: housekeeping_items                                               *
 *                                                                            *
 * Purpose: remove items having status 'deleted'                              *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCEED - items deleted succesfully                          *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
/*
static int housekeeping_items(void)
{
	char		sql[MAX_STRING_LEN];
	DB_RESULT	*result;
	int		i,itemid;

	zabbix_log( LOG_LEVEL_WARNING, "In housekeeping_items()");

	snprintf(sql,sizeof(sql)-1,"select itemid from items where status=%d", ITEM_STATUS_DELETED);
	result = DBselect(sql);
	for(i=0;i<DBnum_rows(result);i++)
	{
		itemid=atoi(DBget_field(result,i,0));
		delete_item(itemid);
	}
	DBfree_result(result);
	return SUCCEED;
}
*/

/******************************************************************************
 *                                                                            *
 * Function: housekeeping_hosts                                               *
 *                                                                            *
 * Purpose: remove hosts having status 'deleted'                              *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCEED - hosts deleted succesfully                          *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
/*static int housekeeping_hosts(void)
{
	char		sql[MAX_STRING_LEN];
	DB_RESULT	*result;
	int		i,hostid;
	
	zabbix_log( LOG_LEVEL_WARNING, "In housekeeping_hosts()");

	snprintf(sql,sizeof(sql)-1,"select hostid from hosts where status=%d", HOST_STATUS_DELETED);
	result = DBselect(sql);
	for(i=0;i<DBnum_rows(result);i++)
	{
		hostid=atoi(DBget_field(result,i,0));
		delete_host(hostid);
	}
	DBfree_result(result);
	return SUCCEED;
}*/

/******************************************************************************
 *                                                                            *
 * Function: housekeeping_history_and_trends                                  *
 *                                                                            *
 * Purpose: remove outdated information from history and trends               *
 *                                                                            *
 * Parameters: now - current timestamp                                        *
 *                                                                            *
 * Return value: SUCCEED - information removed succesfully                    *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
/*
static int housekeeping_history_and_trends(int now)
{
	char		sql[MAX_STRING_LEN];
	DB_ITEM		item;

	DB_RESULT	*result;

	int		i;

	zabbix_log( LOG_LEVEL_WARNING, "In housekeeping_history_and_trends(%d)", now);

	snprintf(sql,sizeof(sql)-1,"select itemid,history,delay,trends from items");
	result = DBselect(sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		item.itemid=atoi(DBget_field(result,i,0));
		item.history=atoi(DBget_field(result,i,1));
		item.delay=atoi(DBget_field(result,i,2));
		item.trends=atoi(DBget_field(result,i,3));

		if(item.delay==0)
		{
			item.delay=1;
		}

#ifdef HAVE_MYSQL
		snprintf(sql,sizeof(sql)-1,"delete from history where itemid=%d and clock<%d limit %d",item.itemid,now-24*3600*item.history,2*CONFIG_HOUSEKEEPING_FREQUENCY*3600/item.delay);
#else
		snprintf(sql,sizeof(sql)-1,"delete from history where itemid=%d and clock<%d",item.itemid,now-24*3600*item.history);
#endif
		DBexecute(sql);

#ifdef HAVE_MYSQL
		snprintf(sql,sizeof(sql)-1,"delete from history_uint where itemid=%d and clock<%d limit %d",item.itemid,now-24*3600*item.history,2*CONFIG_HOUSEKEEPING_FREQUENCY*3600/item.delay);
#else
		snprintf(sql,sizeof(sql)-1,"delete from history_uint where itemid=%d and clock<%d",item.itemid,now-24*3600*item.history);
#endif
		DBexecute(sql);

#ifdef HAVE_MYSQL
		snprintf(sql,sizeof(sql)-1,"delete from history_str where itemid=%d and clock<%d limit %d",item.itemid,now-24*3600*item.history,2*CONFIG_HOUSEKEEPING_FREQUENCY*3600/item.delay);
#else
		snprintf(sql,sizeof(sql)-1,"delete from history_str where itemid=%d and clock<%d",item.itemid,now-24*3600*item.history);
#endif
		DBexecute(sql);

#ifdef HAVE_MYSQL
		snprintf(sql,sizeof(sql)-1,"delete from history_log where itemid=%d and clock<%d limit %d",item.itemid,now-24*3600*item.history,2*CONFIG_HOUSEKEEPING_FREQUENCY*3600/item.delay);
#else
		snprintf(sql,sizeof(sql)-1,"delete from history_log where itemid=%d and clock<%d",item.itemid,now-24*3600*item.history);
#endif
		DBexecute(sql);

#ifdef HAVE_MYSQL
		snprintf(sql,sizeof(sql)-1,"delete from trends where itemid=%d and clock<%d limit %d",item.itemid,now-24*3600*item.trends,2*CONFIG_HOUSEKEEPING_FREQUENCY*3600/item.delay);
#else
		snprintf(sql,sizeof(sql)-1,"delete from trends where itemid=%d and clock<%d",item.itemid,now-24*3600*item.trends);
#endif
		DBexecute(sql);
	}
	DBfree_result(result);
	return SUCCEED;
}
*/

/******************************************************************************
 *                                                                            *
 * Function: housekeeping_process_log                                         *
 *                                                                            *
 * Purpose: process table 'housekeeper' and remove data if required           *
 *                                                                            *
 * Parameters:                                                                *
 *                                                                            *
 * Return value: SUCCEED - information removed succesfully                    *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int housekeeping_process_log()
{
	char		sql[MAX_STRING_LEN];
	DB_HOUSEKEEPER	housekeeper;

	DB_RESULT	result;
	DB_ROW		row;
	int		res = SUCCEED;

	long		deleted;

	zabbix_log( LOG_LEVEL_DEBUG, "In housekeeping_process_log()");

	/* order by tablename to effectively use DB cache */
	snprintf(sql,sizeof(sql)-1,"select housekeeperid, tablename, field, value from housekeeper order by tablename");
	result = DBselect(sql);

	while((row=DBfetch(result)))
	{
		housekeeper.housekeeperid=atoi(row[0]);
		housekeeper.tablename=row[1];
		housekeeper.field=row[2];
		housekeeper.value=atoi(row[3]);

		snprintf(sql,sizeof(sql)-1,"delete from %s where %s=%d limit 500",housekeeper.tablename, housekeeper.field,housekeeper.value);
		DBexecute(sql);

		if(( deleted = DBaffected_rows()) == 0)
		{
			snprintf(sql,sizeof(sql)-1,"delete from housekeeper where housekeeperid=%d",housekeeper.housekeeperid);
			DBexecute(sql);
		}
		else
		{
			zabbix_log( LOG_LEVEL_DEBUG, "Deleted [%ld] records from table [%s]", deleted, housekeeper.tablename);
		}
	}
	DBfree_result(result);

	return res;
}


static int housekeeping_sessions(int now)
{
	char	sql[MAX_STRING_LEN];

	zabbix_log( LOG_LEVEL_DEBUG, "In housekeeping_sessions(%d)", now);

	snprintf(sql,sizeof(sql)-1,"delete from sessions where lastaccess<%d",now-24*3600);
	DBexecute(sql);

	zabbix_log( LOG_LEVEL_DEBUG, "Deleted [%ld] records from table [sessions]", DBaffected_rows());

	return SUCCEED;
}

static int housekeeping_alerts(int now)
{
	char		sql[MAX_STRING_LEN];
	int		alert_history;
	DB_RESULT	result;
	DB_ROW		row;
	int		res = SUCCEED;

	zabbix_log( LOG_LEVEL_DEBUG, "In housekeeping_alerts(%d)", now);

	snprintf(sql,sizeof(sql)-1,"select alert_history from config");
	result = DBselect(sql);

	row=DBfetch(result);

	if(row)
	{
		alert_history=atoi(DBget_field(result,0,0));

		snprintf(sql,sizeof(sql)-1,"delete from alerts where clock<%d",now-24*3600*alert_history);
		DBexecute(sql);
		zabbix_log( LOG_LEVEL_DEBUG, "Deleted [%ld] records from table [alerts]", DBaffected_rows());
	}
	else
	{
		zabbix_log( LOG_LEVEL_ERR, "No records in table 'config'.");
		res = FAIL;
	}

	DBfree_result(result);
	return res;
}

static int housekeeping_alarms(int now)
{
	char		sql[MAX_STRING_LEN];
	int		alarm_history;
	DB_RESULT	result;
	DB_RESULT	result2;
	DB_ROW		row1;
	DB_ROW		row2;
	int 		alarmid;
	int		res = SUCCEED;

	zabbix_log( LOG_LEVEL_DEBUG, "In housekeeping_alarms(%d)", now);

	snprintf(sql,sizeof(sql)-1,"select alarm_history from config");
	result = DBselect(sql);
	row1=DBfetch(result);
	
	if(row1)
	{
		alarm_history=atoi(row1[0]);

		snprintf(sql,sizeof(sql)-1,"select alarmid from alarms where clock<%d", now-24*3600*alarm_history);
		result2 = DBselect(sql);
		while((row2=DBfetch(result2)))
		{
			alarmid=atoi(row2[0]);
			
			snprintf(sql,sizeof(sql)-1,"delete from acknowledges where alarmid=%d",alarmid);
			DBexecute(sql);
			
			snprintf(sql,sizeof(sql)-1,"delete from alarms where alarmid=%d",alarmid);
			DBexecute(sql);
		}
		DBfree_result(result2);

		zabbix_log( LOG_LEVEL_DEBUG, "Deleted [%ld] records from table [alarms]", DBaffected_rows());
	}
	else
	{
		zabbix_log( LOG_LEVEL_ERR, "No records in table 'config'.");
		res = FAIL;
	}
	
	DBfree_result(result);
	return res;
}

int main_housekeeper_loop()
{
	int	now;

	if(CONFIG_DISABLE_HOUSEKEEPING == 1)
	{
		for(;;)
		{
/* Do nothing */
#ifdef HAVE_FUNCTION_SETPROCTITLE
			setproctitle("do nothing");
#endif
			sleep(3600);
		}
	}

	for(;;)
	{
		now = time(NULL);
#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("connecting to the database");
#endif
		DBconnect();

#ifdef HAVE_FUNCTION_SETPROCTITLE
/*		setproctitle("housekeeper [removing deleted hosts]");*/
#endif
/*		housekeeping_hosts();*/

#ifdef HAVE_FUNCTION_SETPROCTITLE
/*		setproctitle("housekeeper [removing deleted items]");*/
#endif

/*		housekeeping_items();*/

#ifdef HAVE_FUNCTION_SETPROCTITLE
/*		setproctitle("housekeeper [removing old history]");*/
#endif

/*		housekeeping_history_and_trends(now);*/

#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("housekeeper [removing old history]");
#endif

		housekeeping_process_log(now);

#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("housekeeper [removing old alarms]");
#endif

		housekeeping_alarms(now);

#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("housekeeper [removing old alerts]");
#endif

		housekeeping_alerts(now);

#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("housekeeper [removing old sessions]");
#endif

		housekeeping_sessions(now);

#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("housekeeper [vacuuming database]");
#endif
		DBvacuum();

		zabbix_log( LOG_LEVEL_DEBUG, "Sleeping for %d hours", CONFIG_HOUSEKEEPING_FREQUENCY);

#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("housekeeper [sleeping for %d hour(s)]", CONFIG_HOUSEKEEPING_FREQUENCY);
#endif

		DBclose();
		sleep(3660*CONFIG_HOUSEKEEPING_FREQUENCY);
	}
}
