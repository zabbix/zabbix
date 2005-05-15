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

/* Remove items having status 'deleted' */
#ifdef ZABBIX_THREADS
void *housekeeping_items(MYSQL *database)
#else
int housekeeping_items(void)
#endif
{
	char		sql[MAX_STRING_LEN];
	DB_RESULT	*result;
	int		i,itemid;

	snprintf(sql,sizeof(sql)-1,"select itemid from items where status=%d", ITEM_STATUS_DELETED);
#ifdef ZABBIX_THREADS
	result = DBselect_thread(database, sql);
#else
	result = DBselect(sql);
#endif
	for(i=0;i<DBnum_rows(result);i++)
	{
		itemid=atoi(DBget_field(result,i,0));
#ifdef ZABBIX_THREADS
		DBdelete_item_thread(database, itemid);
#else
		DBdelete_item(itemid);
#endif
	}
	DBfree_result(result);
	return SUCCEED;
}

/* Remove hosts having status 'deleted' */
#ifdef ZABBIX_THREADS
int housekeeping_hosts(MYSQL *database)
#else
int housekeeping_hosts(void)
#endif
{
	char		sql[MAX_STRING_LEN];
	DB_RESULT	*result;
	int		i,hostid;

	snprintf(sql,sizeof(sql)-1,"select hostid from hosts where status=%d", HOST_STATUS_DELETED);
#ifdef ZABBIX_THREADS
	result = DBselect_thread(database, sql);
#else
	result = DBselect(sql);
#endif
	for(i=0;i<DBnum_rows(result);i++)
	{
		hostid=atoi(DBget_field(result,i,0));
#ifdef ZABBIX_THREADS
		DBdelete_host_thread(database, hostid);
#else
		DBdelete_host(hostid);
#endif
	}
	DBfree_result(result);
	return SUCCEED;
}

#ifdef ZABBIX_THREADS
int housekeeping_history_and_trends(MYSQL *database, int now)
#else
int housekeeping_history_and_trends(int now)
#endif
{
	char		sql[MAX_STRING_LEN];
	DB_ITEM		item;

	DB_RESULT	*result;

	int		i;

/* How lastdelete is used ??? */
/*	snprintf(sql,sizeof(sql)-1,"select itemid,lastdelete,history,delay,trends from items where lastdelete<=%d", now);*/
	snprintf(sql,sizeof(sql)-1,"select itemid,history,delay,trends from items");
#ifdef ZABBIX_THREADS
	result = DBselect_thread(database, sql);
#else
	result = DBselect(sql);
#endif

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

/* Delete HISTORY */
#ifdef HAVE_MYSQL
		snprintf(sql,sizeof(sql)-1,"delete from history where itemid=%d and clock<%d limit %d",item.itemid,now-24*3600*item.history,2*CONFIG_HOUSEKEEPING_FREQUENCY*3600/item.delay);
#else
		snprintf(sql,sizeof(sql)-1,"delete from history where itemid=%d and clock<%d",item.itemid,now-24*3600*item.history);
#endif
#ifdef ZABBIX_THREADS
		DBexecute_thread(database, sql);
#else
		DBexecute(sql);
#endif

/* Delete HISTORY_STR */
#ifdef HAVE_MYSQL
		snprintf(sql,sizeof(sql)-1,"delete from history_str where itemid=%d and clock<%d limit %d",item.itemid,now-24*3600*item.history,2*CONFIG_HOUSEKEEPING_FREQUENCY*3600/item.delay);
#else
		snprintf(sql,sizeof(sql)-1,"delete from history_str where itemid=%d and clock<%d",item.itemid,now-24*3600*item.history);
#endif
#ifdef ZABBIX_THREADS
		DBexecute_thread(database, sql);
#else
		DBexecute(sql);
#endif

/* Delete HISTORY_TRENDS */
#ifdef HAVE_MYSQL
		snprintf(sql,sizeof(sql)-1,"delete from trends where itemid=%d and clock<%d limit %d",item.itemid,now-24*3600*item.trends,2*CONFIG_HOUSEKEEPING_FREQUENCY*3600/item.delay);
#else
		snprintf(sql,sizeof(sql)-1,"delete from trends where itemid=%d and clock<%d",item.itemid,now-24*3600*item.trends);
#endif
#ifdef ZABBIX_THREADS
		DBexecute_thread(database,sql);
#else
		DBexecute(sql);
#endif
/*		snprintf(sql,sizeof(sql)-1,"update items set lastdelete=%d where itemid=%d",now,item.itemid);
#ifdef ZABBIX_THREADS
		DBexecute_thread(database,sql);
#else
		DBexecute(sql);
#endif
*/
	}
	DBfree_result(result);
	return SUCCEED;
}

#ifdef ZABBIX_THREADS
int housekeeping_sessions(MYSQL *database, int now)
#else
int housekeeping_sessions(int now)
#endif
{
	char	sql[MAX_STRING_LEN];

	snprintf(sql,sizeof(sql)-1,"delete from sessions where lastaccess<%d",now-24*3600);
#ifdef ZABBIX_THREADS
	DBexecute_thread(database,sql);
#else
	DBexecute(sql);
#endif

	return SUCCEED;
}

#ifdef ZABBIX_THREADS
int housekeeping_alerts(MYSQL *database, int now)
#else
int housekeeping_alerts(int now)
#endif
{
	char		sql[MAX_STRING_LEN];
	int		alert_history;
	DB_RESULT	*result;
	int		res = SUCCEED;

	snprintf(sql,sizeof(sql)-1,"select alert_history from config");
#ifdef ZABBIX_THREADS
	result = DBselect_thread(database, sql);
#else
	result = DBselect(sql);
#endif

	if(DBnum_rows(result) == 0)
	{
		zabbix_log( LOG_LEVEL_ERR, "No records in table 'config'.");
		res = FAIL;
	}
	else
	{
		alert_history=atoi(DBget_field(result,0,0));

		snprintf(sql,sizeof(sql)-1,"delete from alerts where clock<%d",now-24*3600*alert_history);
#ifdef ZABBIX_THREADS
		DBexecute_thread(database,sql);
#else
		DBexecute(sql);
#endif
	}

	DBfree_result(result);
	return res;
}

#ifdef ZABBIX_THREADS
int housekeeping_alarms(MYSQL *database, int now)
#else
int housekeeping_alarms(int now)
#endif
{
	char		sql[MAX_STRING_LEN];
	int		alarm_history;
	DB_RESULT	*result;
	int		res = SUCCEED;

	snprintf(sql,sizeof(sql)-1,"select alarm_history from config");
#ifdef ZABBIX_THREADS
	result = DBselect_thread(database, sql);
#else
	result = DBselect(sql);
#endif
	if(DBnum_rows(result) == 0)
	{
		zabbix_log( LOG_LEVEL_ERR, "No records in table 'config'.");
		res = FAIL;
	}
	else
	{
		alarm_history=atoi(DBget_field(result,0,0));

		snprintf(sql,sizeof(sql)-1,"delete from alarms where clock<%d",now-24*3600*alarm_history);
#ifdef ZABBIX_THREADS
		DBexecute_thread(database,sql);
#else
		DBexecute(sql);
#endif
	}
	
	DBfree_result(result);
	return res;
}

#ifdef ZABBIX_THREADS
void *main_housekeeper_loop()
#else
int main_housekeeper_loop()
#endif
{
	int	now;

#ifdef ZABBIX_THREADS
	DB_HANDLE	database;
#endif

#ifdef ZABBIX_THREADS
	my_thread_init();
#endif

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
#ifdef ZABBIX_THREADS
		DBconnect_thread(&database);
#else
		DBconnect();
#endif

#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("housekeeper [removing deleted hosts]");
#endif
#ifdef ZABBIX_THREADS
		housekeeping_hosts(&database);
#else
		housekeeping_hosts();
#endif

#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("housekeeper [removing deleted items]");
#endif

#ifdef ZABBIX_THREADS
		housekeeping_items(&database);
#else
		housekeeping_items();
#endif

#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("housekeeper [removing old values]");
#endif

#ifdef ZABBIX_THREADS
		housekeeping_history_and_trends(&database, now);
#else
		housekeeping_history_and_trends(now);
#endif

#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("housekeeper [removing old alarms]");
#endif

#ifdef ZABBIX_THREADS
		housekeeping_alarms(&database, now);
#else
		housekeeping_alarms(now);
#endif

#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("housekeeper [removing old alerts]");
#endif

#ifdef ZABBIX_THREADS
		housekeeping_alerts(&database, now);
#else
		housekeeping_alerts(now);
#endif

#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("housekeeper [removing old sessions]");
#endif

#ifdef ZABBIX_THREADS
		housekeeping_sessions(&database, now);
#else
		housekeeping_sessions(now);
#endif

#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("housekeeper [vacuuming database]");
#endif
#ifdef ZABBIX_THREADS
		DBvacuum_thread(&database);
#else
		DBvacuum();
#endif

		zabbix_log( LOG_LEVEL_DEBUG, "Sleeping for %d hours", CONFIG_HOUSEKEEPING_FREQUENCY);

#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("housekeeper [sleeping for %d hour(s)]", CONFIG_HOUSEKEEPING_FREQUENCY);
#endif

#ifdef ZABBIX_THREADS
		DBclose_thread(&database);
#else
		DBclose();
#endif
		sleep(3660*CONFIG_HOUSEKEEPING_FREQUENCY);
	}
}
