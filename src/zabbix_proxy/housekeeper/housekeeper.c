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
/*
static int housekeeping_process_log()
{
	DB_HOUSEKEEPER	housekeeper;

	DB_RESULT	result;
	DB_ROW		row;
	int		res = SUCCEED;

	long		records;

	zabbix_log( LOG_LEVEL_DEBUG, "In housekeeping_process_log()");

*/	/* order by tablename to effectively use DB cache *//*
	result = DBselect("select housekeeperid, tablename, field, value from housekeeper order by tablename");

	while((row=DBfetch(result)))
	{
		ZBX_STR2UINT64(housekeeper.housekeeperid,row[0]);
		housekeeper.tablename=row[1];
		housekeeper.field=row[2];
		ZBX_STR2UINT64(housekeeper.value,row[3]);

#ifdef HAVE_ORACLE
		records = DBexecute("delete from %s where %s=" ZBX_FS_UI64 " and rownum<500",
			housekeeper.tablename,
			housekeeper.field,
			housekeeper.value);
#elif defined(HAVE_POSTGRESQL)
		records = DBexecute("delete from %s where oid in (select oid from %s where %s=" ZBX_FS_UI64 " limit 500)",
				housekeeper.tablename, 
				housekeeper.tablename, 
				housekeeper.field,
				housekeeper.value);
#else
		records = DBexecute("delete from %s where %s=" ZBX_FS_UI64 " limit 500",
			housekeeper.tablename,
			housekeeper.field,
			housekeeper.value);
#endif
		if(records == 0)
		{
			DBexecute("delete from housekeeper where housekeeperid=" ZBX_FS_UI64,
				housekeeper.housekeeperid);
		}
		else
		{
			zabbix_log( LOG_LEVEL_DEBUG, "Deleted [%ld] records from table [%s]",
				records,
				housekeeper.tablename);
		}
	}
	DBfree_result(result);

	return res;
}


static int housekeeping_sessions(int now)
{
	int records;

	zabbix_log( LOG_LEVEL_DEBUG, "In housekeeping_sessions(%d)",
		now);

	records = DBexecute("delete from sessions where lastaccess<%d",
		now-24*3600);

	zabbix_log( LOG_LEVEL_DEBUG, "Deleted [%ld] records from table [sessions]",
		records);

	return SUCCEED;
}

static int housekeeping_alerts(int now)
{
	int	alert_history;
	DB_RESULT	result;
	DB_ROW		row;
	int		res = SUCCEED;
	int		records;

	zabbix_log( LOG_LEVEL_DEBUG, "In housekeeping_alerts(%d)",
		now);

	result = DBselect("select alert_history from config");

	row=DBfetch(result);

	if(!row || DBis_null(row[0])==SUCCEED)
	{
		zabbix_log( LOG_LEVEL_ERR, "No records in table 'config'.");
		res = FAIL;
	}
	else
	{
		alert_history=atoi(row[0]);

		records = DBexecute("delete from alerts where clock<%d",
			now-24*3600*alert_history);
		zabbix_log( LOG_LEVEL_DEBUG, "Deleted [%ld] records from table [alerts]",
			records);
	}

	DBfree_result(result);
	return res;
}

static int housekeeping_events(int now)
{
	int		event_history;
	DB_RESULT	result;
	DB_RESULT	result2;
	DB_ROW		row1;
	DB_ROW		row2;
	zbx_uint64_t	eventid;
	int		res = SUCCEED;

	zabbix_log( LOG_LEVEL_DEBUG, "In housekeeping_events(%d)",
		now);

	result = DBselect("select event_history from config");

	row1=DBfetch(result);
	
	if(!row1 || DBis_null(row1[0])==SUCCEED)
	{
		zabbix_log( LOG_LEVEL_ERR, "No records in table 'config'.");
		res = FAIL;
	}
	else
	{
		event_history=atoi(row1[0]);

		result2 = DBselect("select eventid from events where clock<%d",
			now-24*3600*event_history);
		while((row2=DBfetch(result2)))
		{
			ZBX_STR2UINT64(eventid,row2[0]);
			
			DBexecute("delete from acknowledges where eventid=" ZBX_FS_UI64,
				eventid);
			
			DBexecute("delete from events where eventid=" ZBX_FS_UI64,
				eventid);
		}
		DBfree_result(result2);

	}
	
	DBfree_result(result);
	return res;
}
*/
/******************************************************************************
 *                                                                            *
 * Function: delete_history                                                   *
 *                                                                            *
 * Purpose: remove outdated information from historical table                 *
 *                                                                            *
 * Parameters: now - current timestamp                                        *
 *                                                                            *
 * Return value: number of rows records                                       *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int delete_history(const char *table, const char *fieldname, int now)
{
	DB_RESULT       result;
	DB_ROW          row;
	int             minclock, records = 0;
	zbx_uint64_t	lastid;
/*	double		sec;*/

	zabbix_log(LOG_LEVEL_DEBUG, "In delete_history() [table:%s] [now:%d]",
			table,
			now);

/*	sec = zbx_time();*/

	DBbegin();

	result = DBselect("select %s from proxies",
			fieldname);

	if (NULL == (row = DBfetch(result)) || DBis_null(row[0]) == SUCCEED)
		goto rollback;

	lastid = zbx_atoui64(row[0]);
	DBfree_result(result);

	result = DBselect("select min(clock) from %s",
			table);

	if (NULL == (row = DBfetch(result)) || DBis_null(row[0]) == SUCCEED)
		goto rollback;

	minclock = atoi(row[0]);
	DBfree_result(result);

	records = DBexecute("delete from %s where clock<%d or (id<" ZBX_FS_UI64 " and clock<%d)",
			table,
			now - CONFIG_PROXY_OFFLINE_BUFFER * 3600,
			lastid,
			MIN(now - CONFIG_PROXY_LOCAL_BUFFER * 3600, minclock + 4 * CONFIG_HOUSEKEEPING_FREQUENCY * 3600));

/*	zabbix_log(LOG_LEVEL_DEBUG, "----- [table:%s] [now:%d] [lastid:" ZBX_FS_UI64 "] [minclock:%d] [offline:%d] [24h:%d] [maxdeleted:%d] [%d] [seconds:%f]",
			table,
			now,
			lastid,
			minclock,
			now - CONFIG_PROXY_OFFLINE_BUFFER * 3600,
			now - CONFIG_PROXY_LOCAL_BUFFER * 3600,
			minclock + 4 * CONFIG_HOUSEKEEPING_FREQUENCY * 3600,
			records,
			zbx_time() - sec);*/

	DBcommit();

	return records;
rollback:
	DBfree_result(result);

	DBrollback();

	return 0;
}

/******************************************************************************
 *                                                                            *
 * Function: housekeeping_history                                             *
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
static int housekeeping_history(int now)
{
        int	records = 0;

        zabbix_log(LOG_LEVEL_DEBUG, "In housekeeping_history()");

	records += delete_history("history_sync", "history_lastid", now);
	records += delete_history("history_uint_sync", "history_uint_lastid", now);
	records += delete_history("history_str_sync", "history_str_lastid", now);
	records += delete_history("history_text", "history_text_lastid", now);
	records += delete_history("history_log", "history_log_lastid", now);

        return records;
}

int main_housekeeper_loop()
{
	int	records;
	int	start, sleeptime;

	if (CONFIG_DISABLE_HOUSEKEEPING == 1) {
		zbx_setproctitle("housekeeper [disabled]");

		for(;;) /* Do nothing */
			sleep(3600);
	}

	for (;;) {
		start = time(NULL);

		zabbix_log(LOG_LEVEL_WARNING, "Executing housekeeper");

		zbx_setproctitle("housekeeper [connecting to the database]");

		DBconnect(ZBX_DB_CONNECT_NORMAL);

		zbx_setproctitle("housekeeper [removing old history]");

		records = housekeeping_history(start);

		zabbix_log(LOG_LEVEL_WARNING, "Deleted %d records from history",
				records);

/*		zbx_setproctitle("housekeeper [removing old history]");

		housekeeping_process_log(start);

		zbx_setproctitle("housekeeper [removing old events]");

		housekeeping_events(start);

		zbx_setproctitle("housekeeper [removing old alerts]");

		housekeeping_alerts(start);

		zbx_setproctitle("housekeeper [removing old sessions]");

		housekeeping_sessions(start);*/

		zbx_setproctitle("housekeeper [vacuuming database]");

/* Transaction is not required here. It causes timeouts under MySQL */
/*		DBcommit();*/

		DBvacuum();
		DBclose();

		sleeptime = CONFIG_HOUSEKEEPING_FREQUENCY * 3600 - (time(NULL) - start);

		if (sleeptime > 0) {
			zbx_setproctitle("housekeeper [sleeping for %d seconds]",
					sleeptime);
			zabbix_log(LOG_LEVEL_DEBUG, "Sleeping for %d seconds",
					sleeptime);
			sleep(sleeptime);
		}
	}
}
