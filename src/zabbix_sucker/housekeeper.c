/* 
** Zabbix
** Copyright (C) 2000,2001,2002,2003,2004 Alexei Vladishev
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
int housekeeping_items(void)
{
	char		sql[MAX_STRING_LEN];
	DB_RESULT	*result;
	int		i,itemid;

	snprintf(sql,sizeof(sql)-1,"select itemid from items where status=%d", ITEM_STATUS_DELETED);
	result = DBselect(sql);
	for(i=0;i<DBnum_rows(result);i++)
	{
		itemid=atoi(DBget_field(result,i,0));
		DBdelete_item(itemid);
	}
	DBfree_result(result);
	return SUCCEED;
}

/* Remove hosts having status 'deleted' */
int housekeeping_hosts(void)
{
	char		sql[MAX_STRING_LEN];
	DB_RESULT	*result;
	int		i,hostid;

	snprintf(sql,sizeof(sql)-1,"select hostid from hosts where status=%d", HOST_STATUS_DELETED);
	result = DBselect(sql);
	for(i=0;i<DBnum_rows(result);i++)
	{
		hostid=atoi(DBget_field(result,i,0));
		DBdelete_host(hostid);
	}
	DBfree_result(result);
	return SUCCEED;
}

int housekeeping_history(int now)
{
	char		sql[MAX_STRING_LEN];
	DB_ITEM		item;

	DB_RESULT	*result;

	int		i;

/* How lastdelete is used ??? */
	snprintf(sql,sizeof(sql)-1,"select itemid,lastdelete,history,delay from items where lastdelete<=%d", now);
	result = DBselect(sql);

	for(i=0;i<DBnum_rows(result);i++)
	{
		item.itemid=atoi(DBget_field(result,i,0));
		item.lastdelete=atoi(DBget_field(result,i,1));
		item.history=atoi(DBget_field(result,i,2));
		item.delay=atoi(DBget_field(result,i,3));

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
		snprintf(sql,sizeof(sql)-1,"delete from history_str where itemid=%d and clock<%d limit %d",item.itemid,now-24*3600*item.history,2*CONFIG_HOUSEKEEPING_FREQUENCY*3600/item.delay);
#else
		snprintf(sql,sizeof(sql)-1,"delete from history_str where itemid=%d and clock<%d",item.itemid,now-24*3600*item.history);
#endif
		DBexecute(sql);
	
		snprintf(sql,sizeof(sql)-1,"update items set lastdelete=%d where itemid=%d",now,item.itemid);
		DBexecute(sql);
	}
	DBfree_result(result);
	return SUCCEED;
}

int housekeeping_sessions(int now)
{
	char	sql[MAX_STRING_LEN];

	snprintf(sql,sizeof(sql)-1,"delete from sessions where lastaccess<%d",now-24*3600);
	DBexecute(sql);

	return SUCCEED;
}

int housekeeping_alerts(int now)
{
	char		sql[MAX_STRING_LEN];
	int		alert_history;
	DB_RESULT	*result;
	int		res = SUCCEED;

	snprintf(sql,sizeof(sql)-1,"select alert_history from config");
	result = DBselect(sql);

	if(DBnum_rows(result) == 0)
	{
		zabbix_log( LOG_LEVEL_ERR, "No records in table 'config'.");
		res = FAIL;
	}
	else
	{
		alert_history=atoi(DBget_field(result,0,0));

		snprintf(sql,sizeof(sql)-1,"delete from alerts where clock<%d",now-24*3600*alert_history);
		DBexecute(sql);
	}

	DBfree_result(result);
	return res;
}

int housekeeping_alarms(int now)
{
	char		sql[MAX_STRING_LEN];
	int		alarm_history;
	DB_RESULT	*result;
	int		res = SUCCEED;

	snprintf(sql,sizeof(sql)-1,"select alarm_history from config");
	result = DBselect(sql);
	if(DBnum_rows(result) == 0)
	{
		zabbix_log( LOG_LEVEL_ERR, "No records in table 'config'.");
		res = FAIL;
	}
	else
	{
		alarm_history=atoi(DBget_field(result,0,0));

		snprintf(sql,sizeof(sql)-1,"delete from alarms where clock<%d",now-24*3600*alarm_history);
		DBexecute(sql);
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
		setproctitle("housekeeper [removing deleted hosts]");
#endif
		housekeeping_hosts();

#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("housekeeper [removing deleted items]");
#endif
		housekeeping_items();

#ifdef HAVE_FUNCTION_SETPROCTITLE
		setproctitle("housekeeper [removing old values]");
#endif
		housekeeping_history(now);

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
