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
static int housekeeping_process_log()
{
	DB_HOUSEKEEPER	housekeeper;

	DB_RESULT	result;
	DB_ROW		row;
	int		res = SUCCEED;

	long		deleted;

	zabbix_log( LOG_LEVEL_DEBUG, "In housekeeping_process_log()");

	/* order by tablename to effectively use DB cache */
	result = DBselect("select housekeeperid, tablename, field, value from housekeeper order by tablename");

	while((row=DBfetch(result)))
	{
		ZBX_STR2UINT64(housekeeper.housekeeperid,row[0]);
		housekeeper.tablename=row[1];
		housekeeper.field=row[2];
		ZBX_STR2UINT64(housekeeper.value,row[3]);

#ifdef HAVE_ORACLE
		deleted = DBexecute("delete from %s where %s=" ZBX_FS_UI64 " and rownum<500",
			housekeeper.tablename,
			housekeeper.field,
			housekeeper.value);
#elif defined(HAVE_POSTGRESQL)
		deleted = DBexecute("delete from %s where oid in (select oid from %s where %s=" ZBX_FS_UI64 " limit 500)",
				housekeeper.tablename, 
				housekeeper.tablename, 
				housekeeper.field,
				housekeeper.value);
#else
		deleted = DBexecute("delete from %s where %s=" ZBX_FS_UI64 " limit 500",
			housekeeper.tablename,
			housekeeper.field,
			housekeeper.value);
#endif
		if(deleted == 0)
		{
			DBexecute("delete from housekeeper where housekeeperid=" ZBX_FS_UI64,
				housekeeper.housekeeperid);
		}
		else
		{
			zabbix_log( LOG_LEVEL_DEBUG, "Deleted [%ld] records from table [%s]",
				deleted,
				housekeeper.tablename);
		}
	}
	DBfree_result(result);

	return res;
}


static int housekeeping_sessions(int now)
{
	int deleted;

	zabbix_log( LOG_LEVEL_DEBUG, "In housekeeping_sessions(%d)",
		now);

	deleted = DBexecute("delete from sessions where lastaccess<%d",
		now-24*3600);

	zabbix_log( LOG_LEVEL_DEBUG, "Deleted [%ld] records from table [sessions]",
		deleted);

	return SUCCEED;
}

static int housekeeping_alerts(int now)
{
	int	alert_history;
	DB_RESULT	result;
	DB_ROW		row;
	int		res = SUCCEED;
	int		deleted;

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

		deleted = DBexecute("delete from alerts where clock<%d",
			now-24*3600*alert_history);
		zabbix_log( LOG_LEVEL_DEBUG, "Deleted [%ld] records from table [alerts]",
			deleted);
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

/******************************************************************************
 *                                                                            *
 * Function: delete_history                                                   *
 *                                                                            *
 * Purpose: remove outdated information from historical table                 *
 *                                                                            *
 * Parameters: now - current timestamp                                        *
 *                                                                            *
 * Return value: number of rows deleted                                       *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int delete_history(char *table, zbx_uint64_t itemid, int keep_history, int now)
{
	char            sql[MAX_STRING_LEN];
	DB_RESULT       result;
	DB_ROW          row;
	int             min_clock;

	zabbix_log( LOG_LEVEL_DEBUG, "In delete_history(%s," ZBX_FS_UI64 "," ZBX_FS_UI64 ",%d)",
		table,
		itemid,
		keep_history,
		now);

	zbx_snprintf(sql,sizeof(sql)-1,"select min(clock) from %s where itemid=" ZBX_FS_UI64,
		table,
		itemid);
	result = DBselect(sql);

	row=DBfetch(result);

	if(!row || DBis_null(row[0]) == SUCCEED)
	{
		DBfree_result(result);
		return 0;
	}

	min_clock = atoi(row[0]);
	DBfree_result(result);

/*	zabbix_log( LOG_LEVEL_DEBUG, "Now %d keep_history %d Itemid " ZBX_FS_UI64 " min %d new min %d",
		now,
		keep_history,
		itemid,
		min_clock,
		MIN(now-24*3600*keep_history, min_clock+4*3600*CONFIG_HOUSEKEEPING_FREQUENCY));*/

	zbx_snprintf(sql,sizeof(sql)-1,"delete from %s where itemid=" ZBX_FS_UI64 " and clock<%d",
		table,
		itemid,
		MIN(now-24*3600*keep_history, min_clock+4*3600*CONFIG_HOUSEKEEPING_FREQUENCY)
	);

	return DBexecute(sql);
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
        char            sql[MAX_STRING_LEN];
        DB_ITEM         item;

        DB_RESULT       result;
        DB_ROW          row;

        int             deleted = 0;

        zabbix_log( LOG_LEVEL_DEBUG, "In housekeeping_history_and_trends(%d)",
		now);

        zbx_snprintf(sql,sizeof(sql)-1,"select itemid,history,trends from items");
        result = DBselect(sql);

        while((row=DBfetch(result)))
        {
		ZBX_STR2UINT64(item.itemid,row[0]);
                item.history=atoi(row[1]);
                item.trends=atoi(row[2]);

                deleted += delete_history("history", item.itemid, item.history, now);
                deleted += delete_history("history_uint", item.itemid, item.history, now);
                deleted += delete_history("history_str", item.itemid, item.history, now);
                deleted += delete_history("history_str", item.itemid, item.history, now);
                deleted += delete_history("history_log", item.itemid, item.history, now);
                deleted += delete_history("trends", item.itemid, item.trends, now);
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
			zbx_setproctitle("do nothing");

			sleep(3600);
		}
	}

	for(;;)
	{
		zabbix_log( LOG_LEVEL_WARNING, "Executing housekeeper");
		now = time(NULL);

		zbx_setproctitle("connecting to the database");

		DBconnect(ZBX_DB_CONNECT_NORMAL);

		DBbegin();

/*		zbx_setproctitle("housekeeper [removing deleted hosts]");*/

/*		housekeeping_hosts();*/

/*		zbx_setproctitle("housekeeper [removing deleted items]");*/

/*		housekeeping_items();*/

/*		zbx_setproctitle("housekeeper [removing old history]");*/

		d = housekeeping_history_and_trends(now);
		zabbix_log( LOG_LEVEL_WARNING, "Deleted %d records from history and trends",
			d);

		zbx_setproctitle("housekeeper [removing old history]");

		housekeeping_process_log(now);

		zbx_setproctitle("housekeeper [removing old events]");

		housekeeping_events(now);

		zbx_setproctitle("housekeeper [removing old alerts]");

		housekeeping_alerts(now);

		zbx_setproctitle("housekeeper [removing old sessions]");

		housekeeping_sessions(now);

		zbx_setproctitle("housekeeper [vacuuming database]");

		DBcommit();

		DBvacuum();

		zabbix_log( LOG_LEVEL_DEBUG, "Sleeping for %d hours",
			CONFIG_HOUSEKEEPING_FREQUENCY);

		zbx_setproctitle("housekeeper [sleeping for %d hour(s)]",
			CONFIG_HOUSEKEEPING_FREQUENCY);

		DBclose();
		zabbix_log( LOG_LEVEL_DEBUG, "Next housekeeper run is after %dh",
			CONFIG_HOUSEKEEPING_FREQUENCY);
		sleep(3660*CONFIG_HOUSEKEEPING_FREQUENCY);
	}
}
