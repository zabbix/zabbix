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
 * Return value: SUCCEED - information removed successfully                   *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Author: Alexei Vladishev, Dmitry Borovikov                                 *
 *                                                                            *
 * Comments: sqlite3 does not use CONFIG_MAX_HOUSEKEEPER_DELETE, deletes all  *
 *                                                                            *
 ******************************************************************************/
static int	housekeeping_process_log()
{
	const char	*__function_name = "housekeeping_process_log";
	DB_HOUSEKEEPER	housekeeper;
	DB_RESULT	result;
	DB_ROW		row;
	long		deleted;
	char		*sql = NULL;
	int		sql_alloc = 512, sql_offset = 0;
	zbx_uint64_t	*ids = NULL;
	int		ids_alloc = 0, ids_num = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	/* order by tablename to effectively use DB cache */
	result = DBselect(
			"select housekeeperid,tablename,field,value"
			" from housekeeper"
			" order by tablename");

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(housekeeper.housekeeperid, row[0]);
		housekeeper.tablename = row[1];
		housekeeper.field = row[2];
		ZBX_STR2UINT64(housekeeper.value, row[3]);

		if (0 == CONFIG_MAX_HOUSEKEEPER_DELETE)
		{
			deleted = DBexecute(
					"delete from %s"
					" where %s=" ZBX_FS_UI64,
					housekeeper.tablename,
					housekeeper.field,
					housekeeper.value);
		}
		else
		{
#ifdef HAVE_ORACLE
			deleted = DBexecute(
					"delete from %s"
					" where %s=" ZBX_FS_UI64
						" and rownum<=%d",
					housekeeper.tablename,
					housekeeper.field,
					housekeeper.value,
					CONFIG_MAX_HOUSEKEEPER_DELETE);
#elif defined(HAVE_POSTGRESQL)
			deleted = DBexecute(
					"delete from %s"
					" where %s=" ZBX_FS_UI64
						" and oid in (select oid from %s"
							" where %s=" ZBX_FS_UI64 " limit %d)",
					housekeeper.tablename,
					housekeeper.field,
					housekeeper.value,
					housekeeper.tablename,
					housekeeper.field,
					housekeeper.value,
					CONFIG_MAX_HOUSEKEEPER_DELETE);
#elif defined(HAVE_MYSQL)
			deleted = DBexecute(
					"delete from %s"
					" where %s=" ZBX_FS_UI64 " limit %d",
					housekeeper.tablename,
					housekeeper.field,
					housekeeper.value,
					CONFIG_MAX_HOUSEKEEPER_DELETE);
#else	/* HAVE_SQLITE3 */
			deleted = 0;
#endif
		}

		if (0 == deleted || 0 == CONFIG_MAX_HOUSEKEEPER_DELETE ||
				CONFIG_MAX_HOUSEKEEPER_DELETE > deleted)
			uint64_array_add(&ids, &ids_alloc, &ids_num, housekeeper.housekeeperid, 64);

		if (deleted > 0)
		{
			zabbix_log( LOG_LEVEL_DEBUG, "Deleted [%ld] records from table [%s]",
					deleted, housekeeper.tablename);
		}
	}
	DBfree_result(result);

	if (NULL != ids)
	{
		sql = zbx_malloc(sql, sql_alloc * sizeof(char));

		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, 32,
				"delete from housekeeper where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset,
				"housekeeperid", ids, ids_num);

		DBexecute("%s", sql);

		zbx_free(sql);
		zbx_free(ids);
	}

	return SUCCEED;
}


static int housekeeping_sessions(int now)
{
	int deleted;

	zabbix_log( LOG_LEVEL_DEBUG, "In housekeeping_sessions(%d)",
		now);

	deleted = DBexecute("delete from sessions where lastaccess<%d",
		now-365*86400);

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
static int	delete_history(const char *table, zbx_uint64_t itemid, int keep_history, int now)
{
	DB_RESULT       result;
	DB_ROW          row;
	int             min_clock;

	zabbix_log( LOG_LEVEL_DEBUG, "In delete_history(%s," ZBX_FS_UI64 ",%d,%d)",
		table,
		itemid,
		keep_history,
		now);

	result = DBselect("select min(clock) from %s where itemid=" ZBX_FS_UI64,
		table,
		itemid);

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

	return DBexecute("delete from %s where itemid=" ZBX_FS_UI64 " and clock<%d",
		table,
		itemid,
		MIN(now-24*3600*keep_history, min_clock+4*3600*CONFIG_HOUSEKEEPING_FREQUENCY)
	);
}

/******************************************************************************
 *                                                                            *
 * Function: housekeeping_history_and_trends                                  *
 *                                                                            *
 * Purpose: remove outdated information from history and trends               *
 *                                                                            *
 * Parameters: now - current timestamp                                        *
 *                                                                            *
 * Return value: SUCCEED - information removed successfully                   *
 *               FAIL - otherwise                                             *
 *                                                                            *
 * Author: Alexei Vladishev                                                   *
 *                                                                            *
 * Comments:                                                                  *
 *                                                                            *
 ******************************************************************************/
static int	housekeeping_history_and_trends(int now)
{
	DB_ITEM         item;
	DB_RESULT       result;
	DB_ROW          row;
	int             deleted = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In housekeeping_history_and_trends() now:%d", now);

	result = DBselect("select itemid,history,trends from items");

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(item.itemid, row[0]);
		item.history = atoi(row[1]);
		item.trends = atoi(row[2]);

		deleted += delete_history("history", item.itemid, item.history, now);
		deleted += delete_history("history_uint", item.itemid, item.history, now);
		deleted += delete_history("history_str", item.itemid, item.history, now);
		deleted += delete_history("history_text", item.itemid, item.history, now);
		deleted += delete_history("history_log", item.itemid, item.history, now);
		deleted += delete_history("trends", item.itemid, item.trends, now);
		deleted += delete_history("trends_uint", item.itemid, item.trends, now);
	}
	DBfree_result(result);

	return deleted;
}

int	main_housekeeper_loop()
{
	int	d, now;

	if (1 == CONFIG_DISABLE_HOUSEKEEPING)
	{
		for (;;)
		{
			zbx_setproctitle("housekeeper [sleeping forever]");
			sleep(SEC_PER_HOUR);
		}
	}

	for (;;)
	{
		zabbix_log(LOG_LEVEL_WARNING, "Executing housekeeper");
		now = time(NULL);

		zbx_setproctitle("housekeeper [connecting to the database]");

		DBconnect(ZBX_DB_CONNECT_NORMAL);

/* Transaction is not required here. It causes timeouts under MySQL. */
/*		DBbegin();*/

		zbx_setproctitle("housekeeper [removing old history]");

		d = housekeeping_history_and_trends(now);
		zabbix_log(LOG_LEVEL_WARNING, "Deleted %d records from history and trends", d);

		zbx_setproctitle("housekeeper [removing old history]");

		housekeeping_process_log(now);

		zbx_setproctitle("housekeeper [removing old events]");

		housekeeping_events(now);

		zbx_setproctitle("housekeeper [removing old alerts]");

		housekeeping_alerts(now);

		zbx_setproctitle("housekeeper [removing old sessions]");

		housekeeping_sessions(now);

/* Transaction is not required here. It causes timeouts under MySQL. */
/*		DBcommit();*/

/*		zbx_setproctitle("housekeeper [vacuuming database]");*/

/*		DBvacuum();*/

		DBclose();

		zabbix_log(LOG_LEVEL_DEBUG, "Sleeping for %d hours", CONFIG_HOUSEKEEPING_FREQUENCY);
		zbx_setproctitle("housekeeper [sleeping for %d hours]", CONFIG_HOUSEKEEPING_FREQUENCY);

		sleep(SEC_PER_HOUR * CONFIG_HOUSEKEEPING_FREQUENCY);
	}
}
