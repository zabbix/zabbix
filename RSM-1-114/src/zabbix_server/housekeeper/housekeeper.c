/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "common.h"
#include "db.h"
#include "dbcache.h"
#include "log.h"
#include "daemon.h"
#include "zbxself.h"
#include "zbxalgo.h"

#include "housekeeper.h"

extern unsigned char	process_type;

/******************************************************************************
 *                                                                            *
 * Function: housekeeping_cleanup                                             *
 *                                                                            *
 * Purpose: remove deleted items data                                         *
 *                                                                            *
 * Return value: number of rows deleted                                       *
 *                                                                            *
 * Author: Alexei Vladishev, Dmitry Borovikov                                 *
 *                                                                            *
 * Comments: sqlite3 does not use CONFIG_MAX_HOUSEKEEPER_DELETE, deletes all  *
 *                                                                            *
 ******************************************************************************/
static int	housekeeping_cleanup()
{
	const char		*__function_name = "housekeeping_cleanup";
	DB_HOUSEKEEPER		housekeeper;
	DB_RESULT		result;
	DB_ROW			row;
	int			d, deleted = 0;
	zbx_vector_uint64_t	housekeeperids;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_uint64_create(&housekeeperids);

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
			d = DBexecute(
					"delete from %s"
					" where %s=" ZBX_FS_UI64,
					housekeeper.tablename,
					housekeeper.field,
					housekeeper.value);
		}
		else
		{
#if defined(HAVE_IBM_DB2) || defined(HAVE_ORACLE)
			d = DBexecute(
					"delete from %s"
					" where %s=" ZBX_FS_UI64
						" and rownum<=%d",
					housekeeper.tablename,
					housekeeper.field,
					housekeeper.value,
					CONFIG_MAX_HOUSEKEEPER_DELETE);
#elif defined(HAVE_MYSQL)
			d = DBexecute(
					"delete from %s"
					" where %s=" ZBX_FS_UI64 " limit %d",
					housekeeper.tablename,
					housekeeper.field,
					housekeeper.value,
					CONFIG_MAX_HOUSEKEEPER_DELETE);
#elif defined(HAVE_POSTGRESQL)
			d = DBexecute(
					"delete from %s"
					" where ctid = any(array(select ctid from %s"
						" where %s=" ZBX_FS_UI64 " limit %d))",
					housekeeper.tablename,
					housekeeper.tablename,
					housekeeper.field,
					housekeeper.value,
					CONFIG_MAX_HOUSEKEEPER_DELETE);
#elif defined(HAVE_SQLITE3)
			d = 0;
#endif
		}

		if (0 == d || 0 == CONFIG_MAX_HOUSEKEEPER_DELETE || CONFIG_MAX_HOUSEKEEPER_DELETE > d)
			zbx_vector_uint64_append(&housekeeperids, housekeeper.housekeeperid);

		deleted += d;
	}
	DBfree_result(result);

	if (0 != housekeeperids.values_num)
	{
		char	*sql = NULL;
		size_t	sql_alloc = 512, sql_offset = 0;

		sql = zbx_malloc(sql, sql_alloc);

		zbx_vector_uint64_sort(&housekeeperids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, "delete from housekeeper where");
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, "housekeeperid",
				housekeeperids.values, housekeeperids.values_num);

		DBexecute("%s", sql);

		zbx_free(sql);
	}

	zbx_vector_uint64_destroy(&housekeeperids);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, deleted);

	return deleted;
}

static int	housekeeping_sessions(int now)
{
	const char	*__function_name = "housekeeping_sessions";
	int		deleted;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() now:%d", __function_name, now);

	deleted = DBexecute("delete from sessions where lastaccess<%d", now - SEC_PER_YEAR);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, deleted);

	return deleted;
}

static int	housekeeping_alerts(int now)
{
	const char	*__function_name = "housekeeping_alerts";
	int		deleted, alert_history;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() now:%d", __function_name, now);

	deleted = DBexecute("delete from alerts where clock<%d",
			now - *(int *)DCconfig_get_config_data(&alert_history, CONFIG_ALERT_HISTORY) * SEC_PER_DAY);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, deleted);

	return deleted;
}

static int	housekeeping_events(int now)
{
	const char	*__function_name = "housekeeping_events";
	int		event_history, deleted = 0;
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	eventid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() now:%d", __function_name, now);

	result = DBselect("select eventid from events where clock<%d",
			now - *(int *)DCconfig_get_config_data(&event_history, CONFIG_EVENT_HISTORY) * SEC_PER_DAY);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(eventid, row[0]);

		DBexecute("delete from acknowledges where eventid=" ZBX_FS_UI64, eventid);
		deleted += DBexecute("delete from events where eventid=" ZBX_FS_UI64, eventid);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, deleted);

	return deleted;
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
	const char	*__function_name = "delete_history";
	DB_RESULT       result;
	DB_ROW          row;
	int             min_clock, deleted;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() table:'%s' itemid:" ZBX_FS_UI64 " keep_history:%d now:%d",
		__function_name, table, itemid, keep_history, now);

	result = DBselect("select min(clock) from %s where itemid=" ZBX_FS_UI64, table, itemid);

	if (NULL == (row = DBfetch(result)) || SUCCEED == DBis_null(row[0]))
	{
		DBfree_result(result);
		return 0;
	}

	min_clock = atoi(row[0]);
	min_clock = MIN(now - keep_history * SEC_PER_DAY, min_clock + 4 * CONFIG_HOUSEKEEPING_FREQUENCY * SEC_PER_HOUR);
	DBfree_result(result);

	deleted = DBexecute("delete from %s where itemid=" ZBX_FS_UI64 " and clock<%d", table, itemid, min_clock);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, deleted);

	return deleted;
}

/******************************************************************************
 *                                                                            *
 * Function: housekeeping_history_and_trends                                  *
 *                                                                            *
 * Purpose: remove outdated information from history and trends               *
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
static int	housekeeping_history_and_trends(int now)
{
	const char	*__function_name = "housekeeping_history_and_trends";
	zbx_uint64_t	itemid;
	DB_RESULT       result;
	DB_ROW          row;
	int             history, trends, deleted = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() now:%d", __function_name, now);

	result = DBselect(
			"select i.itemid,i.history,i.trends from items i,hosts h"
			" where i.hostid=h.hostid"
				" and h.status in (%d,%d)",
			HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(itemid, row[0]);
		history = atoi(row[1]);
		trends = atoi(row[2]);

		deleted += delete_history("history", itemid, history, now);
		deleted += delete_history("history_uint", itemid, history, now);
		deleted += delete_history("history_str", itemid, history, now);
		deleted += delete_history("history_text", itemid, history, now);
		deleted += delete_history("history_log", itemid, history, now);
		deleted += delete_history("trends", itemid, trends, now);
		deleted += delete_history("trends_uint", itemid, trends, now);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __function_name, deleted);

	return deleted;
}

void	main_housekeeper_loop()
{
	int	now, d_history_and_trends, d_cleanup, d_events, d_alerts, d_sessions;

	for (;;)
	{
		zabbix_log(LOG_LEVEL_WARNING, "executing housekeeper");
		now = time(NULL);

		zbx_setproctitle("%s [connecting to the database]", get_process_type_string(process_type));

		DBconnect(ZBX_DB_CONNECT_NORMAL);

		zbx_setproctitle("%s [removing old history and trends]", get_process_type_string(process_type));
		d_history_and_trends = housekeeping_history_and_trends(now);

		zbx_setproctitle("%s [removing deleted items data]", get_process_type_string(process_type));
		d_cleanup = housekeeping_cleanup();

		zbx_setproctitle("%s [removing old events]", get_process_type_string(process_type));
		d_events = housekeeping_events(now);

		zbx_setproctitle("%s [removing old alerts]", get_process_type_string(process_type));
		d_alerts = housekeeping_alerts(now);

		zbx_setproctitle("%s [removing old sessions]", get_process_type_string(process_type));
		d_sessions = housekeeping_sessions(now);

		zabbix_log(LOG_LEVEL_WARNING, "housekeeper deleted: %d records from history and trends,"
				" %d records of deleted items, %d events, %d alerts, %d sessions",
				d_history_and_trends, d_cleanup, d_events, d_alerts, d_sessions);

		DBclose();

		zbx_sleep_loop(CONFIG_HOUSEKEEPING_FREQUENCY * SEC_PER_HOUR);
	}
}
