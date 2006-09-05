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

#ifdef HAVE_ORACLE
		snprintf(sql,sizeof(sql)-1,"delete from %s where %s=%d and rownum<500",housekeeper.tablename, housekeeper.field,housekeeper.value);
#elif defined(HAVE_PGSQL)
		snprintf(sql,sizeof(sql)-1,"delete from %s where oid in (select oid from %s where %s=%d limit 500)",
				housekeeper.tablename, 
				housekeeper.tablename, 
				housekeeper.field,
				housekeeper.value);
#else
		snprintf(sql,sizeof(sql)-1,"delete from %s where %s=%d limit 500",housekeeper.tablename, housekeeper.field,housekeeper.value);
#endif
		if(( deleted = DBexecute(sql)) == 0)
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

	zabbix_log( LOG_LEVEL_DEBUG, "Deleted [%ld] records from table [sessions]", DBexecute(sql));

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

	if(!row || DBis_null(row[0])==SUCCEED)
	{
		zabbix_log( LOG_LEVEL_ERR, "No records in table 'config'.");
		res = FAIL;
	}
	else
	{
		alert_history=atoi(row[0]);

		snprintf(sql,sizeof(sql)-1,"delete from alerts where clock<%d",now-24*3600*alert_history);
		zabbix_log( LOG_LEVEL_DEBUG, "Deleted [%ld] records from table [alerts]", DBexecute(sql));
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
	
	if(!row1 || DBis_null(row1[0])==SUCCEED)
	{
		zabbix_log( LOG_LEVEL_ERR, "No records in table 'config'.");
		res = FAIL;
	}
	else
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
			zabbix_log( LOG_LEVEL_DEBUG, "Deleted [%ld] records from table [alarms]", DBexecute(sql));
		}
		DBfree_result(result2);

	}
	
	DBfree_result(result);
	return res;
}


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
static int housekeeping_history_and_trends(int now)
{
	char		sql[MAX_STRING_LEN];
	DB_ITEM		item;

	DB_RESULT	result;
	DB_ROW		row;

	int		deleted = 0;

	zabbix_log( LOG_LEVEL_DEBUG, "In housekeeping_history_and_trends(%d)", now);

	snprintf(sql,sizeof(sql)-1,"select itemid,history,delay,trends from items");
	result = DBselect(sql);

	while((row=DBfetch(result)))
	{
		item.itemid=atoi(row[0]);
		item.history=atoi(row[1]);
		item.delay=atoi(row[2]);
		item.trends=atoi(row[3]);

		if(item.delay==0)
		{
			item.delay=1;
		}

#ifdef HAVE_MYSQL
		snprintf(sql,sizeof(sql)-1,"delete from history where itemid=%d and clock<%d limit %d",item.itemid,now-24*3600*item.history,2*CONFIG_HOUSEKEEPING_FREQUENCY*3600/item.delay);
#else
		snprintf(sql,sizeof(sql)-1,"delete from history where itemid=%d and clock<%d",item.itemid,now-24*3600*item.history);
#endif
		deleted += DBexecute(sql);

#ifdef HAVE_MYSQL
		snprintf(sql,sizeof(sql)-1,"delete from history_uint where itemid=%d and clock<%d limit %d",item.itemid,now-24*3600*item.history,2*CONFIG_HOUSEKEEPING_FREQUENCY*3600/item.delay);
#else
		snprintf(sql,sizeof(sql)-1,"delete from history_uint where itemid=%d and clock<%d",item.itemid,now-24*3600*item.history);
#endif
		deleted += DBexecute(sql);

#ifdef HAVE_MYSQL
		snprintf(sql,sizeof(sql)-1,"delete from history_str where itemid=%d and clock<%d limit %d",item.itemid,now-24*3600*item.history,2*CONFIG_HOUSEKEEPING_FREQUENCY*3600/item.delay);
#else
		snprintf(sql,sizeof(sql)-1,"delete from history_str where itemid=%d and clock<%d",item.itemid,now-24*3600*item.history);
#endif
		deleted += DBexecute(sql);

#ifdef HAVE_MYSQL
		snprintf(sql,sizeof(sql)-1,"delete from history_log where itemid=%d and clock<%d limit %d",item.itemid,now-24*3600*item.history,2*CONFIG_HOUSEKEEPING_FREQUENCY*3600/item.delay);
#else
		snprintf(sql,sizeof(sql)-1,"delete from history_log where itemid=%d and clock<%d",item.itemid,now-24*3600*item.history);
#endif
		deleted += DBexecute(sql);

#ifdef HAVE_MYSQL
		snprintf(sql,sizeof(sql)-1,"delete from trends where itemid=%d and clock<%d limit %d",item.itemid,now-24*3600*item.trends,2*CONFIG_HOUSEKEEPING_FREQUENCY*3600/item.delay);
#else
		snprintf(sql,sizeof(sql)-1,"delete from trends where itemid=%d and clock<%d",item.itemid,now-24*3600*item.trends);
#endif
		deleted += DBexecute(sql);
	}
	DBfree_result(result);
	return deleted;
}

int main_housekeeper_loop()
{
	int	now;
	int	d;

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
		zabbix_log( LOG_LEVEL_WARNING, "Executing housekeeper");
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

		d = housekeeping_history_and_trends(now);
		zabbix_log( LOG_LEVEL_WARNING, "Deleted %d records from history and trends", d);

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
		zabbix_log( LOG_LEVEL_WARNING, "Next housekeeper run is after %dh", CONFIG_HOUSEKEEPING_FREQUENCY);
		sleep(3660*CONFIG_HOUSEKEEPING_FREQUENCY);
	}
}
