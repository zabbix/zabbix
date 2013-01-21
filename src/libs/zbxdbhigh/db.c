/*
** Zabbix
** Copyright (C) 2000-2011 Zabbix SIA
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
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

#include "zbxdb.h"
#include "db.h"
#include "log.h"
#include "common.h"
#include "events.h"
#include "threads.h"
#include "zbxserver.h"
#include "dbcache.h"
#include "zbxalgo.h"

#define ZBX_DB_WAIT_DOWN	10

#if HAVE_POSTGRESQL
extern char	ZBX_PG_ESCAPE_BACKSLASH;
#endif
/***************************************************************/
/* ID structure: NNNSSSDDDDDDDDDDD, where                      */
/*      NNN - nodeid (to which node the ID belongs to)         */
/*      SSS - source nodeid (in which node was the ID created) */
/*      DDDDDDDDDDD - the ID itself                            */
/***************************************************************/

extern int	txn_level;
extern int	txn_error;

/******************************************************************************
 *                                                                            *
 * Function: __DBnode                                                         *
 *                                                                            *
 * Purpose: prepare a SQL statement to select records from a specific node    *
 *                                                                            *
 * Parameters: field_name - [IN] the name of the field                        *
 *             nodeid     - [IN] node identificator from database             *
 *             op         - [IN] 0 - and; 1 - where                           *
 *                                                                            *
 * Return value:                                                              *
 *  An SQL condition like:                                                    *
 *   " and hostid between 100000000000000 and 199999999999999"                *
 *  or                                                                        *
 *   " where hostid between 100000000000000 and 199999999999999"              *
 *  or an empty string for a standalone setup (nodeid = 0)                    *
 *                                                                            *
 ******************************************************************************/
const char	*__DBnode(const char *field_name, int nodeid, int op)
{
	static char	dbnode[62 + ZBX_FIELDNAME_LEN];
	const char	*operators[] = {"and", "where"};

	if (0 != nodeid)
	{
		zbx_uint64_t	min, max;

		min = ZBX_DM_MAX_HISTORY_IDS * (zbx_uint64_t)nodeid;
		max = min + ZBX_DM_MAX_HISTORY_IDS - 1;

		zbx_snprintf(dbnode, sizeof(dbnode), " %s %s between " ZBX_FS_UI64 " and " ZBX_FS_UI64,
				operators[op], field_name, min, max);
	}
	else
		*dbnode = '\0';

	return dbnode;
}

/******************************************************************************
 *                                                                            *
 * Function: DBis_node_id                                                     *
 *                                                                            *
 * Purpose: checks belonging of an identifier to a certain node               *
 *                                                                            *
 * Parameters: id     - [IN] the checked identifier                           *
 *             nodeid - [IN] node identificator from database                 *
 *                                                                            *
 * Return value: SUCCEED if identifier is belonging to a node, FAIL otherwise *
 *                                                                            *
 ******************************************************************************/
int	DBis_node_id(zbx_uint64_t id, int nodeid)
{
	zbx_uint64_t	min, max;

	min = ZBX_DM_MAX_HISTORY_IDS * (zbx_uint64_t)nodeid;
	max = min + ZBX_DM_MAX_HISTORY_IDS - 1;

	return min <= id && id <= max ? SUCCEED : FAIL;
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
	int		err;

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
	zbx_db_init(CONFIG_DBNAME);
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
 * Function: DBend                                                            *
 *                                                                            *
 * Purpose: commit or rollback a transaction depending on a parameter value   *
 *                                                                            *
 * Comments: do nothing if DB does not support transactions                   *
 *                                                                            *
 ******************************************************************************/
void	DBend(int ret)
{
	if (SUCCEED == ret)
		DBtxn_operation(zbx_db_commit);
	else
		DBtxn_operation(zbx_db_rollback);
}

#ifdef HAVE_ORACLE
/******************************************************************************
 *                                                                            *
 * Function: DBstatement_prepare                                              *
 *                                                                            *
 * Purpose: prepares a SQL statement for execution                            *
 *                                                                            *
 * Comments: retry until DB is up                                             *
 *                                                                            *
 ******************************************************************************/
void	DBstatement_prepare(const char *sql)
{
	int	rc;

	rc = zbx_db_statement_prepare(sql);

	while (ZBX_DB_DOWN == rc)
	{
		DBclose();
		DBconnect(ZBX_DB_CONNECT_NORMAL);

		if (ZBX_DB_DOWN == (rc = zbx_db_statement_prepare(sql)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "Database is down. Retrying in %d seconds.", ZBX_DB_WAIT_DOWN);
			sleep(ZBX_DB_WAIT_DOWN);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: DBbind_parameter                                                 *
 *                                                                            *
 * Purpose: creates an association between a program variable and             *
 *          a placeholder in a SQL statement                                  *
 *                                                                            *
 * Comments: retry until DB is up                                             *
 *                                                                            *
 ******************************************************************************/
void	DBbind_parameter(int position, void *buffer, unsigned char type)
{
	int	rc;

	rc = zbx_db_bind_parameter(position, buffer, type);

	while (ZBX_DB_DOWN == rc)
	{
		DBclose();
		DBconnect(ZBX_DB_CONNECT_NORMAL);

		if (ZBX_DB_DOWN == (rc = zbx_db_bind_parameter(position, buffer, type)))
		{
			zabbix_log(LOG_LEVEL_WARNING, "Database is down. Retrying in %d seconds.", ZBX_DB_WAIT_DOWN);
			sleep(ZBX_DB_WAIT_DOWN);
		}
	}
}

/******************************************************************************
 *                                                                            *
 * Function: DBstatement_execute                                              *
 *                                                                            *
 * Purpose: executes a SQL statement                                          *
 *                                                                            *
 * Comments: retry until DB is up                                             *
 *                                                                            *
 ******************************************************************************/
int	DBstatement_execute()
{
	int	rc;

	rc = zbx_db_statement_execute();

	while (ZBX_DB_DOWN == rc)
	{
		DBclose();
		DBconnect(ZBX_DB_CONNECT_NORMAL);

		if (ZBX_DB_DOWN == (rc = zbx_db_statement_execute()))
		{
			zabbix_log(LOG_LEVEL_WARNING, "Database is down. Retrying in %d seconds.", ZBX_DB_WAIT_DOWN);
			sleep(ZBX_DB_WAIT_DOWN);
		}
	}

	return rc;
}
#endif

/******************************************************************************
 *                                                                            *
 * Function: __zbx_DBexecute                                                  *
 *                                                                            *
 * Purpose: execute a non-select statement                                    *
 *                                                                            *
 * Comments: retry until DB is up                                             *
 *                                                                            *
 ******************************************************************************/
int	__zbx_DBexecute(const char *fmt, ...)
{
	va_list	args;
	int	rc;

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
	DB_RESULT	rc;

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

/******************************************************************************
 *                                                                            *
 * Function: DBget_trigger_update_sql                                         *
 *                                                                            *
 * Purpose: generates sql statement for updating trigger value                *
 *                                                                            *
 * Parameters: add_event      - [OUT] 0 - do not add event                    *
 *                                    1 - generate new event                  *
 *                                                                            *
 * Return value: SUCCEED - sql statement generated successfully               *
 *               FAIL    - trigger update isn't required                      *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 * Comments: do not update value if there are dependencies with value PROBLEM *
 *                                                                            *
 ******************************************************************************/
int	DBget_trigger_update_sql(char **sql, size_t *sql_alloc, size_t *sql_offset, zbx_uint64_t triggerid,
		unsigned char type, int value, int value_flags, const char *error, int lastchange,
		int new_value, const char *new_error, int new_lastchange, unsigned char *add_event)
{
	const char	*__function_name = "DBget_trigger_update_sql";
	const char	*new_error_local;
	char		*new_error_esc;
	int		new_value_flags, value_changed, value_flags_changed, multiple_problem, error_changed,
			ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() triggerid:" ZBX_FS_UI64 " value:%d(%d) new_value:%d",
			__function_name, triggerid, value, value_flags, new_value);

	if (TRIGGER_VALUE_UNKNOWN == new_value)
	{
		new_value_flags = TRIGGER_VALUE_FLAG_UNKNOWN;
		new_value = value;
	}
	else
		new_value_flags = TRIGGER_VALUE_FLAG_NORMAL;

	/**************************************************************************************************/
	/*                                                                                                */
	/* The following table shows in which cases events should be generated:                           */
	/*                                                                                                */
	/*   _          |                                                                                 */
	/*    \__ to    |                                                                                 */
	/*       \_____ |   OK           OK(?)        PROBLEM     PROBLEM(?)                              */
	/*   from      \|                                                                                 */
	/*              |                                                                                 */
	/*  ------------+------------------------------------------------------                           */
	/*              |                                                                                 */
	/*  OK          |   no           T            T+E         -                                       */
	/*              |                                                                                 */
	/*  OK(?)       |   T            T(e)         T+E         -                                       */
	/*              |                                                                                 */
	/*  PROBLEM     |   T+E          -            T(m)+E(m)   T                                       */
	/*              |                                                                                 */
	/*  PROBLEM(?)  |   T+E          -            T+E(m)      T(e)                                    */
	/*              |                                                                                 */
	/*                                                                                                */
	/* Legend:                                                                                        */
	/*                                                                                                */
	/*  -   - should never happen                                                                     */
	/*  no  - do not update a trigger and do not generate an event                                    */
	/*  T   - update a trigger                                                                        */
	/*  E   - generate an event                                                                       */
	/*  (m) - if it is a "multiple true" trigger                                                      */
	/*  (e) - if an error message has changed                                                         */
	/*                                                                                                */
	/**************************************************************************************************/

	*add_event = 0;
	new_error_local = (NULL == new_error ? "" : new_error);

	value_changed = (value != new_value || (0 == lastchange && TRIGGER_VALUE_FLAG_UNKNOWN != new_value_flags));
	value_flags_changed = (value_flags != new_value_flags);
	multiple_problem = (TRIGGER_TYPE_MULTIPLE_TRUE == type && TRIGGER_VALUE_PROBLEM == new_value &&
			TRIGGER_VALUE_FLAG_NORMAL == new_value_flags);
	error_changed = (TRIGGER_VALUE_FLAG_UNKNOWN == new_value_flags && 0 != strcmp(error, new_error_local));

	if (0 != value_changed || 0 != value_flags_changed || 0 != multiple_problem || 0 != error_changed)
	{
		if (SUCCEED == DCconfig_check_trigger_dependencies(triggerid))
		{
			if (NULL == *sql)
			{
				*sql_alloc = 2 * ZBX_KIBIBYTE;
				*sql = zbx_malloc(*sql, *sql_alloc);
			}

			zbx_strcpy_alloc(sql, sql_alloc, sql_offset, "update triggers set ");

			if (0 != value_changed || 0 != multiple_problem)
			{
				DCconfig_set_trigger_value(triggerid, new_value, new_value_flags, new_error_local,
						&new_lastchange);

				*add_event = 1;
				zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "lastchange=%d,", new_lastchange);
			}
			else
			{
				DCconfig_set_trigger_value(triggerid, new_value, new_value_flags, new_error_local,
						NULL);
			}

			if (0 != value_changed)
				zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "value=%d,", new_value);

			if (0 != value_flags_changed)
				zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "value_flags=%d,", new_value_flags);

			if (0 != error_changed)
			{
				new_error_esc = DBdyn_escape_string_len(new_error_local, TRIGGER_ERROR_LEN);
				zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "error='%s',", new_error_esc);
				zbx_free(new_error_esc);
			}

			(*sql_offset)--;

			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, " where triggerid=" ZBX_FS_UI64, triggerid);

			ret = SUCCEED;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

void	DBadd_trend(zbx_uint64_t itemid, double value, int clock)
{
	const char	*__function_name = "DBadd_trend";
	DB_RESULT	result;
	DB_ROW		row;
	int		hour, num;
	double		value_min, value_avg, value_max;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

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
			value_min = value;
		if (value > value_max)
			value_max = value;
		value_avg = (num * value_avg + value) / (num + 1);
		num++;

		DBexecute("update trends"
				" set num=%d,"
				"value_min=" ZBX_FS_DBL ","
				"value_avg=" ZBX_FS_DBL ","
				"value_max=" ZBX_FS_DBL
				" where itemid=" ZBX_FS_UI64
					" and clock=%d",
				num, value_min, value_avg, value_max, itemid, hour);
	}
	else
	{
		DBexecute("insert into trends (itemid,clock,num,value_min,value_avg,value_max)"
				" values (" ZBX_FS_UI64 ",%d,%d," ZBX_FS_DBL "," ZBX_FS_DBL "," ZBX_FS_DBL ")",
				itemid, hour, 1, value, value, value);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

void	DBadd_trend_uint(zbx_uint64_t itemid, zbx_uint64_t value, int clock)
{
	const char	*__function_name = "DBadd_trend_uint";
	DB_RESULT	result;
	DB_ROW		row;
	int		hour, num;
	zbx_uint64_t	value_min, value_avg, value_max;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	hour = clock - clock % SEC_PER_HOUR;

	result = DBselect("select num,value_min,value_avg,value_max from trends_uint where itemid=" ZBX_FS_UI64 " and clock=%d",
		itemid, hour);

	if (NULL != (row = DBfetch(result)))
	{
		num = atoi(row[0]);
		ZBX_STR2UINT64(value_min, row[1]);
		ZBX_STR2UINT64(value_avg, row[2]);
		ZBX_STR2UINT64(value_max, row[3]);
		if (value < value_min)
			value_min = value;
		if (value > value_max)
			value_max = value;
		value_avg = (num * value_avg + value) / (num + 1);
		num++;

		DBexecute("update trends_uint"
				" set num=%d,"
				"value_min=" ZBX_FS_UI64 ","
				"value_avg=" ZBX_FS_UI64 ","
				"value_max=" ZBX_FS_UI64
				" where itemid=" ZBX_FS_UI64
					" and clock=%d",
				num, value_min, value_avg, value_max, itemid, hour);
	}
	else
	{
		DBexecute("insert into trends_uint (itemid,clock,num,value_min,value_avg,value_max)"
				" values (" ZBX_FS_UI64 ",%d,%d," ZBX_FS_UI64 "," ZBX_FS_UI64 "," ZBX_FS_UI64 ")",
				itemid, hour, 1, value, value, value);
	}
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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
	zbx_uint64_t	interfaceid, itemid, proxy_hostid;
	int		item_type, delay, effective_delay, nextcheck;
	char		*delay_flex;
	time_t		lastclock;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s(): from [%d] to [%d]", __function_name, from, to);

	now = time(NULL);

	result = DBselect(
			"select i.itemid,i.type,i.delay,i.delay_flex,i.lastclock,i.interfaceid,h.proxy_hostid"
			" from items i,hosts h"
			" where i.hostid=h.hostid"
				" and h.status=%d"
				" and i.status=%d"
				" and i.value_type not in (%d)"
				" and ("
					"i.lastclock is not null"
					" and i.lastclock<%d"
					")"
				" and ("
					"i.type in (%d,%d,%d,%d,%d,%d,%d,%d,%d)"
					" or (h.available<>%d and i.type in (%d))"
					" or (h.snmp_available<>%d and i.type in (%d,%d,%d))"
					" or (h.ipmi_available<>%d and i.type in (%d))"
					" or (h.jmx_available<>%d and i.type in (%d))"
					")"
				" and i.flags not in (%d)"
				ZBX_SQL_NODE,
			HOST_STATUS_MONITORED,
			ITEM_STATUS_ACTIVE,
			ITEM_VALUE_TYPE_LOG,
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
			HOST_AVAILABLE_FALSE,
				ITEM_TYPE_JMX,
			ZBX_FLAG_DISCOVERY_CHILD,
			DBand_node_local("i.itemid"));

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(itemid, row[0]);
		item_type	= atoi(row[1]);
		delay		= atoi(row[2]);
		delay_flex	= row[3];
		ZBX_DBROW2UINT64(interfaceid, row[5]);
		ZBX_DBROW2UINT64(proxy_hostid, row[6]);

		if (FAIL == (lastclock = DCget_item_lastclock(itemid)))
			lastclock = (time_t)atoi(row[4]);

		nextcheck = calculate_item_nextcheck(interfaceid, itemid, item_type,
				delay, delay_flex, lastclock, &effective_delay);

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

	/* !!! Don't forget to sync the code with PHP !!! */
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
		*error = zbx_dsprintf(*error, "proxy \"%s\" does not exist", hostname);
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

void	DBstart_escalation(zbx_uint64_t actionid, zbx_uint64_t triggerid, zbx_uint64_t eventid)
{
	const char	*__function_name = "DBstart_escalation";
	zbx_uint64_t	escalationid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	escalationid = DBget_maxid("escalations");

	DBexecute("insert into escalations (escalationid,actionid,triggerid,eventid,status)"
			" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",%s," ZBX_FS_UI64 ",%d)",
			escalationid, actionid, DBsql_id_ins(triggerid), eventid, ESCALATION_STATUS_ACTIVE);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

void	DBstop_escalation(zbx_uint64_t actionid, zbx_uint64_t triggerid, zbx_uint64_t eventid)
{
	const char	*__function_name = "DBstop_escalation";
	zbx_uint64_t	escalationid;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	escalationid = DBget_maxid("escalations");

	DBexecute("insert into escalations (escalationid,actionid,triggerid,r_eventid,status)"
			" values (" ZBX_FS_UI64 "," ZBX_FS_UI64 ",%s," ZBX_FS_UI64 ",%d)",
			escalationid, actionid, DBsql_id_ins(triggerid), eventid, ESCALATION_STATUS_ACTIVE);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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
	ZBX_STR2UINT64(item->itemid, row[0]);
	item->key = row[1];
	item->host_name = row[2];
	item->type = atoi(row[3]);
	item->history = atoi(row[4]);
	item->trends = atoi(row[17]);
	item->value_type = atoi(row[8]);

	if (SUCCEED != DBis_null(row[5]))
		item->lastvalue[0] = row[5];
	else
		item->lastvalue[0] = NULL;

	if (SUCCEED != DBis_null(row[6]))
		item->lastvalue[1] = row[6];
	else
		item->lastvalue[1] = NULL;

	ZBX_STR2UINT64(item->hostid, row[7]);
	item->delta = atoi(row[9]);

	if (SUCCEED != DBis_null(row[10]))
	{
		item->prevorgvalue_null = 0;

		switch (item->value_type)
		{
			case ITEM_VALUE_TYPE_FLOAT:
				item->prevorgvalue.dbl = atof(row[10]);
				break;
			case ITEM_VALUE_TYPE_UINT64:
				ZBX_STR2UINT64(item->prevorgvalue.ui64, row[10]);
				break;
			default:
				item->prevorgvalue.str = row[10];
				break;
		}
	}
	else
		item->prevorgvalue_null = 1;

	if (SUCCEED == DBis_null(row[11]))
		item->lastclock = 0;
	else
		item->lastclock = atoi(row[11]);

	item->units = row[12];
	item->multiplier = atoi(row[13]);
	item->formula = row[14];
	item->status = atoi(row[15]);
	ZBX_DBROW2UINT64(item->valuemapid, row[16]);

	item->data_type = atoi(row[18]);

	item->h_lastvalue[0] = NULL;
	item->h_lastvalue[1] = NULL;
	item->h_lasteventid = NULL;
	item->h_lastsource = NULL;
	item->h_lastseverity = NULL;
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

	for (t = 0; NULL != tables[t].table; t++)
	{
		if (0 == strcmp(tables[t].table, tablename))
			return &tables[t];
	}

	return NULL;
}

const ZBX_FIELD *DBget_field(const ZBX_TABLE *table, const char *fieldname)
{
	int	f;

	for (f = 0; NULL != table->fields[f].name; f++)
	{
		if (0 == strcmp(table->fields[f].name, fieldname))
			return &table->fields[f];
	}

	return NULL;
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_nextid                                                     *
 *                                                                            *
 * Purpose: gets a new identifier(s) for a specified table                    *
 *                                                                            *
 * Parameters: tablename - [IN] the name of a table                           *
 *             num       - [IN] the number of reserved records                *
 *                                                                            *
 * Return value: first reserved identifier                                    *
 *                                                                            *
 ******************************************************************************/
static zbx_uint64_t	DBget_nextid(const char *tablename, int num)
{
	const char	*__function_name = "DBget_nextid";
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	ret1, ret2;
	zbx_uint64_t	min, max;
	int		found = FAIL, dbres;
	const ZBX_TABLE	*table;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tablename:'%s'", __function_name, tablename);

	table = DBget_table(tablename);

	if (0 == CONFIG_NODEID)
	{
		min = 0;
		max = ZBX_STANDALONE_MAX_IDS;
	}
	else if (0 != (table->flags & ZBX_SYNC))
	{
		min = ZBX_DM_MAX_HISTORY_IDS * (zbx_uint64_t)CONFIG_NODEID +
			ZBX_DM_MAX_CONFIG_IDS * (zbx_uint64_t)CONFIG_NODEID;
		max = min + ZBX_DM_MAX_CONFIG_IDS - 1;
	}
	else
	{
		min = ZBX_DM_MAX_HISTORY_IDS * (zbx_uint64_t)CONFIG_NODEID;
		max = min + ZBX_DM_MAX_HISTORY_IDS - 1;
	}

	while (FAIL == found)
	{
		/* avoid eternal loop within failed transaction */
		if (0 < zbx_db_txn_level() && 0 != zbx_db_txn_error())
		{
			zabbix_log(LOG_LEVEL_DEBUG, "End of %s() transaction failed", __function_name);
			return 0;
		}

		result = DBselect("select nextid from ids where nodeid=%d and table_name='%s' and field_name='%s'",
				CONFIG_NODEID, table->table, table->recid);

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
					CONFIG_NODEID, table->table, table->recid, ret1);

			if (ZBX_DB_OK > dbres)
			{
				/* solving the problem of an invisible record created in a parallel transaction */
				DBexecute("update ids set nextid=nextid+1 where nodeid=%d and table_name='%s'"
						" and field_name='%s'",
						CONFIG_NODEID, table->table, table->recid);
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
						CONFIG_NODEID, table->table, table->recid);
				continue;
			}

			DBexecute("update ids set nextid=nextid+%d where nodeid=%d and table_name='%s' and field_name='%s'",
					num, CONFIG_NODEID, table->table, table->recid);

			result = DBselect("select nextid from ids where nodeid=%d and table_name='%s' and field_name='%s'",
					CONFIG_NODEID, table->table, table->recid);

			if (NULL != (row = DBfetch(result)) && SUCCEED != DBis_null(row[0]))
			{
				ZBX_STR2UINT64(ret2, row[0]);
				DBfree_result(result);
				if (ret1 + num == ret2)
					found = SUCCEED;
			}
			else
				THIS_SHOULD_NEVER_HAPPEN;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():" ZBX_FS_UI64 " table:'%s' recid:'%s'",
			__function_name, ret2 - num + 1, table->table, table->recid);

	return ret2 - num + 1;
}

zbx_uint64_t	DBget_maxid_num(const char *tablename, int num)
{
	if (0 == strcmp(tablename, "history_log") ||
			0 == strcmp(tablename, "history_text") ||
			0 == strcmp(tablename, "events") ||
			0 == strcmp(tablename, "dservices") ||
			0 == strcmp(tablename, "dhosts") ||
			0 == strcmp(tablename, "alerts") ||
			0 == strcmp(tablename, "escalations") ||
			0 == strcmp(tablename, "autoreg_host") ||
			0 == strcmp(tablename, "graph_discovery") ||
			0 == strcmp(tablename, "trigger_discovery"))
		return DCget_nextid(tablename, num);

	return DBget_nextid(tablename, num);
}

/******************************************************************************
 *                                                                            *
 * Function: DBadd_condition_alloc                                            *
 *                                                                            *
 * Purpose: takes an initial part of SQL query and appends a generated        *
 *          WHERE condition. The WHERE condidion is generated from the given  *
 *          list of values as a mix of <fieldname> BETWEEN <id1> AND <idN>"   *
 *          and "<fieldname> IN (<id1>,<id2>,...,<idN>)" elements.            *
 *                                                                            *
 * Parameters: sql        - [IN/OUT] buffer for SQL query construction        *
 *             sql_alloc  - [IN/OUT] size of the 'sql' buffer                 *
 *             sql_offset - [IN/OUT] current position in the 'sql' buffer     *
 *             fieldname  - [IN] field name to be used in SQL WHERE condition *
 *             values     - [IN] array of numerical values sorted in          *
 *                               ascending order to be included in WHERE      *
 *             num        - [IN] number of elemnts in 'values' array          *
 *                                                                            *
 ******************************************************************************/
void	DBadd_condition_alloc(char **sql, size_t *sql_alloc, size_t *sql_offset, const char *fieldname,
		const zbx_uint64_t *values, const int num)
{
#define MAX_EXPRESSIONS	950
#define MIN_NUM_BETWEEN	5	/* minimum number of consecutive values for using "between <id1> and <idN>" */

	int		i, start, len, seq_num, first;
	int		between_num = 0, in_num = 0, in_cnt;
	zbx_uint64_t	value;
	int		*seq_len = NULL;

	if (0 == num)
		return;

	zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, ' ');

	/* Store lengths of consecutive sequences of values in a temporary array 'seq_len'. */
	/* An isolated value is represented as a sequence with length 1. */
	seq_len = zbx_malloc(seq_len, num * sizeof(int));

	for (i = 1, seq_num = 0, value = values[0], len = 1; i < num; i++)
	{
		if (values[i] != ++value)
		{
			if (MIN_NUM_BETWEEN <= len)
				between_num++;
			else
				in_num += len;

			seq_len[seq_num++] = len;
			len = 1;
			value = values[i];
		}
		else
			len++;
	}

	if (MIN_NUM_BETWEEN <= len)
		between_num++;
	else
		in_num += len;

	seq_len[seq_num++] = len;

	if (MAX_EXPRESSIONS < in_num || 1 < between_num || (0 < in_num && 0 < between_num))
		zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, '(');

	/* compose "between"s */
	for (i = 0, first = 1, start = 0; i < seq_num; i++)
	{
		if (MIN_NUM_BETWEEN <= seq_len[i])
		{
			if (1 != first)
				zbx_strcpy_alloc(sql, sql_alloc, sql_offset, " or ");

			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s between " ZBX_FS_UI64 " and " ZBX_FS_UI64,
					fieldname, values[start], values[start + seq_len[i] - 1]);
			first = 0;
		}

		start += seq_len[i];
	}

	if (0 < in_num && 0 < between_num)
		zbx_strcpy_alloc(sql, sql_alloc, sql_offset, " or ");

	if (1 < in_num)
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s in (", fieldname);

	/* compose "in"s */
	for (i = 0, in_cnt = 0, start = 0; i < seq_num; i++)
	{
		if (MIN_NUM_BETWEEN > seq_len[i])
		{
			if (1 == in_num)
			{
				zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s=" ZBX_FS_UI64, fieldname,
						values[start]);
				break;
			}
			else
			{
				do
				{
					if (MAX_EXPRESSIONS == in_cnt)
					{
						in_cnt = 0;
						(*sql_offset)--;
						zbx_snprintf_alloc(sql, sql_alloc, sql_offset, ") or %s in (", fieldname);
					}

					zbx_snprintf_alloc(sql, sql_alloc, sql_offset, ZBX_FS_UI64 ",", values[start++]);
					in_cnt++;
				}
				while (0 != --seq_len[i]);
			}
		}
		else
			start += seq_len[i];
	}

	if (1 < in_num)
	{
		(*sql_offset)--;
		zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, ')');
	}

	zbx_free(seq_len);

	if (MAX_EXPRESSIONS < in_num || 1 < between_num || (0 < in_num && 0 < between_num))
		zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, ')');
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
void	DBregister_host(zbx_uint64_t proxy_hostid, const char *host, const char *ip,
		const char *dns, unsigned short port, int now)
{
	char		*host_esc, *ip_esc, *dns_esc;
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	autoreg_hostid;
	zbx_timespec_t	ts;
	int		res = SUCCEED;

	host_esc = DBdyn_escape_string_len(host, HOST_HOST_LEN);

	ts.sec = now;
	ts.ns = 0;

	if (0 != proxy_hostid)
	{
		result = DBselect(
				"select hostid"
				" from hosts"
				" where proxy_hostid%s"
					" and host='%s'"
					ZBX_SQL_NODE,
				DBsql_id_cmp(proxy_hostid), host_esc,
				DBand_node_local("hostid"));

		if (NULL != DBfetch(result))
			res = FAIL;
		DBfree_result(result);
	}

	if (SUCCEED == res)
	{
		ip_esc = DBdyn_escape_string_len(ip, INTERFACE_IP_LEN);
		dns_esc = DBdyn_escape_string_len(dns, INTERFACE_DNS_LEN);

		result = DBselect(
				"select autoreg_hostid"
				" from autoreg_host"
				" where proxy_hostid%s"
					" and host='%s'"
					ZBX_SQL_NODE,
				DBsql_id_cmp(proxy_hostid), host_esc,
				DBand_node_local("autoreg_hostid"));

		if (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(autoreg_hostid, row[0]);

			DBexecute("update autoreg_host"
					" set listen_ip='%s',listen_dns='%s',listen_port=%d"
					" where autoreg_hostid=" ZBX_FS_UI64,
					ip_esc, dns_esc, (int)port, autoreg_hostid);
		}
		else
		{
			autoreg_hostid = DBget_maxid("autoreg_host");
			DBexecute("insert into autoreg_host"
					" (autoreg_hostid,proxy_hostid,host,listen_ip,listen_dns,listen_port)"
					" values"
					" (" ZBX_FS_UI64 ",%s,'%s','%s','%s',%d)",
					autoreg_hostid, DBsql_id_ins(proxy_hostid),
					host_esc, ip_esc, dns_esc, (int)port);
		}
		DBfree_result(result);

		process_event(0, EVENT_SOURCE_AUTO_REGISTRATION, EVENT_OBJECT_ZABBIX_ACTIVE,
				autoreg_hostid, &ts, TRIGGER_VALUE_PROBLEM, 0, 1);

		zbx_free(dns_esc);
		zbx_free(ip_esc);
	}

	zbx_free(host_esc);
}

/******************************************************************************
 *                                                                            *
 * Function: DBproxy_register_host                                            *
 *                                                                            *
 * Purpose: register unknown host                                             *
 *                                                                            *
 * Parameters: host - host name                                               *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
void	DBproxy_register_host(const char *host, const char *ip, const char *dns, unsigned short port)
{
	char	*host_esc, *ip_esc, *dns_esc;

	host_esc = DBdyn_escape_string_len(host, HOST_HOST_LEN);
	ip_esc = DBdyn_escape_string_len(ip, INTERFACE_IP_LEN);
	dns_esc = DBdyn_escape_string_len(dns, INTERFACE_DNS_LEN);

	DBexecute("insert into proxy_autoreg_host"
			" (clock,host,listen_ip,listen_dns,listen_port)"
			" values"
			" (%d,'%s','%s','%s',%d)",
			(int)time(NULL), host_esc, ip_esc, dns_esc, (int)port);

	zbx_free(dns_esc);
	zbx_free(ip_esc);
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
int	DBexecute_overflowed_sql(char **sql, size_t *sql_alloc, size_t *sql_offset)
{
	int	ret = SUCCEED;

	if (ZBX_MAX_SQL_SIZE < *sql_offset)
	{
#ifdef HAVE_MULTIROW_INSERT
		if (',' == (*sql)[*sql_offset - 1])
		{
			(*sql_offset)--;
			zbx_strcpy_alloc(sql, sql_alloc, sql_offset, ";\n");
		}
#endif
		DBend_multiple_update(sql, sql_alloc, sql_offset);

		if (ZBX_DB_OK > DBexecute("%s", *sql))
			ret = FAIL;
		*sql_offset = 0;

		DBbegin_multiple_update(sql, sql_alloc, sql_offset);
	}

	return ret;
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
char	*DBget_unique_hostname_by_sample(const char *host_name_sample)
{
	const char		*__function_name = "DBget_unique_hostname_by_sample";
	DB_RESULT		result;
	DB_ROW			row;
	int			full_match = 0, i;
	char			*host_name_temp = NULL, *host_name_sample_esc;
	zbx_vector_uint64_t	nums;
	zbx_uint64_t		num = 2;	/* produce alternatives starting from "2" */
	size_t			sz;

	assert(host_name_sample && *host_name_sample);

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() sample:'%s'", __function_name, host_name_sample);

	zbx_vector_uint64_create(&nums);
	zbx_vector_uint64_reserve(&nums, 8);

	sz = strlen(host_name_sample);
	host_name_sample_esc = DBdyn_escape_like_pattern(host_name_sample);

	result = DBselect(
			"select host"
			" from hosts"
			" where host like '%s%%' escape '%c'"
				ZBX_SQL_NODE,
			host_name_sample_esc, ZBX_SQL_LIKE_ESCAPE_CHAR, DBand_node_local("hostid"));

	zbx_free(host_name_sample_esc);

	while (NULL != (row = DBfetch(result)))
	{
		zbx_uint64_t	n;
		const char	*p;

		if (0 != strncmp(row[0], host_name_sample, sz))
			continue;

		p = row[0] + sz;

		if ('\0' == *p)
		{
			full_match = 1;
			continue;
		}

		if ('_' != *p || FAIL == is_uint64(p + 1, &n))
			continue;

		zbx_vector_uint64_append(&nums, n);
	}
	DBfree_result(result);

	zbx_vector_uint64_sort(&nums, ZBX_DEFAULT_UINT64_COMPARE_FUNC);

	if (0 == full_match)
	{
		host_name_temp = zbx_strdup(host_name_temp, host_name_sample);
		goto clean;
	}

	for (i = 0; i < nums.values_num; i++)
	{
		if (num > nums.values[i])
			continue;

		if (num < nums.values[i])	/* found, all other will be bigger */
			break;

		num++;
	}

	host_name_temp = zbx_dsprintf(host_name_temp, "%s_%d", host_name_sample, num);
clean:
	zbx_vector_uint64_destroy(&nums);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():'%s'", __function_name, host_name_temp);

	return host_name_temp;
}

/******************************************************************************
 *                                                                            *
 * Function: DBsql_id_cmp                                                     *
 *                                                                            *
 * Purpose: construct where condition                                         *
 *                                                                            *
 * Return value: "=<id>" if id not equal zero,                                *
 *               otherwise " is null"                                         *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
const char	*DBsql_id_cmp(zbx_uint64_t id)
{
	static unsigned char	n = 0;
	static char		buf[4][22];	/* 1 - '=', 20 - value size, 1 - '\0' */
	static const char	is_null[9] = " is null";

	if (0 == id)
		return is_null;

	n = (n + 1) & 3;

	zbx_snprintf(buf[n], sizeof(buf[n]), "=" ZBX_FS_UI64, id);

	return buf[n];
}

/******************************************************************************
 *                                                                            *
 * Function: DBsql_id_ins                                                     *
 *                                                                            *
 * Purpose: construct insert statement                                        *
 *                                                                            *
 * Return value: "<id>" if id not equal zero,                                 *
 *               otherwise "null"                                             *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
const char	*DBsql_id_ins(zbx_uint64_t id)
{
	static unsigned char	n = 0;
	static char		buf[4][21];	/* 20 - value size, 1 - '\0' */
	static const char	null[5] = "null";

	if (0 == id)
		return null;

	n = (n + 1) & 3;

	zbx_snprintf(buf[n], sizeof(buf[n]), ZBX_FS_UI64, id);

	return buf[n];
}

#define ZBX_MAX_INVENTORY_FIELDS	70

/******************************************************************************
 *                                                                            *
 * Function: DBget_inventory_field                                            *
 *                                                                            *
 * Purpose: get corresponding host_inventory field name                       *
 *                                                                            *
 * Parameters: inventory_link - [IN] field number; 1..ZBX_MAX_INVENTORY_FIELDS*
 *                                                                            *
 * Return value: field name or NULL if value of inventory_link is incorrect   *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
const char	*DBget_inventory_field(unsigned char inventory_link)
{
	static const char	*inventory_fields[ZBX_MAX_INVENTORY_FIELDS] =
	{
		"type", "type_full", "name", "alias", "os", "os_full", "os_short", "serialno_a", "serialno_b", "tag",
		"asset_tag", "macaddress_a", "macaddress_b", "hardware", "hardware_full", "software", "software_full",
		"software_app_a", "software_app_b", "software_app_c", "software_app_d", "software_app_e", "contact",
		"location", "location_lat", "location_lon", "notes", "chassis", "model", "hw_arch", "vendor",
		"contract_number", "installer_name", "deployment_status", "url_a", "url_b", "url_c", "host_networks",
		"host_netmask", "host_router", "oob_ip", "oob_netmask", "oob_router", "date_hw_purchase",
		"date_hw_install", "date_hw_expiry", "date_hw_decomm", "site_address_a", "site_address_b",
		"site_address_c", "site_city", "site_state", "site_country", "site_zip", "site_rack", "site_notes",
		"poc_1_name", "poc_1_email", "poc_1_phone_a", "poc_1_phone_b", "poc_1_cell", "poc_1_screen",
		"poc_1_notes", "poc_2_name", "poc_2_email", "poc_2_phone_a", "poc_2_phone_b", "poc_2_cell",
		"poc_2_screen", "poc_2_notes"
	};

	if (1 > inventory_link || inventory_link > ZBX_MAX_INVENTORY_FIELDS)
		return NULL;

	return inventory_fields[inventory_link - 1];
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_inventory_field_len                                        *
 *                                                                            *
 * Purpose: get host_inventory field length by inventory_link                 *
 *                                                                            *
 * Parameters: inventory_link - [IN] field number; 1..ZBX_MAX_INVENTORY_FIELDS*
 *                                                                            *
 * Return value: field length                                                 *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
unsigned short	DBget_inventory_field_len(unsigned char inventory_link)
{
	static unsigned short	*inventory_field_len = NULL;
	const char		*inventory_field;
	const ZBX_TABLE		*table;
	const ZBX_FIELD		*field;

	if (1 > inventory_link || inventory_link > ZBX_MAX_INVENTORY_FIELDS)
		assert(0);

	inventory_link--;

	if (NULL == inventory_field_len)
	{
		inventory_field_len = zbx_malloc(inventory_field_len, ZBX_MAX_INVENTORY_FIELDS * sizeof(unsigned short));
		memset(inventory_field_len, 0, ZBX_MAX_INVENTORY_FIELDS * sizeof(unsigned short));
	}

	if (0 != inventory_field_len[inventory_link])
		return inventory_field_len[inventory_link];

	inventory_field = DBget_inventory_field(inventory_link + 1);
	table = DBget_table("host_inventory");
	assert(NULL != table);
	field = DBget_field(table, inventory_field);
	assert(NULL != field);

	inventory_field_len[inventory_link] = field->length;

	return inventory_field_len[inventory_link];
}

#undef ZBX_MAX_INVENTORY_FIELDS

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
		zbx_timespec_t *ts, const char *field_name, int last_n)
{
	const char	*__function_name = "DBget_history";
	char		sql[512];
	size_t		offset;
	DB_RESULT	result;
	DB_ROW		row;
	char		**h_value = NULL;
	int		h_alloc = 1, h_num = 0, retry = 0, sec = 0, ns = 0;
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

	if (NULL == ts)
	{
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
	}
	else if (1 == retry)
	{
		offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, " and clock=%d", sec);
		if (-1 != ns)
			offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, " and ns<%d", ns);
	}
	else
		offset += zbx_snprintf(sql + offset, sizeof(sql) - offset, " and clock=%d and ns=%d", ts->sec, ts->ns);

	if (0 != last_n)
	{
		if (NULL == ts)
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
		}
		else if (1 == retry)
		{
			offset += zbx_snprintf(sql + offset, sizeof(sql) - offset,
					" order by ns desc");
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

	if (NULL != ts && 0 == h_num && 0 == retry)
	{
		result = DBselect(
				"select max(clock)"
				" from %s"
				" where itemid=" ZBX_FS_UI64
					" and clock<=%d",
				get_table_by_value_type(value_type), itemid, ts->sec);

		if (NULL != (row = DBfetch(result)) && SUCCEED != DBis_null(row[0]))
		{
			if (ts->sec == (sec = atoi(row[0])))
				ns = ts->ns;
			else
				ns = -1;
			retry = 1;
		}
		DBfree_result(result);

		if (1 == retry)
			goto retry;
	}

	if (NULL == ts && 0 != last_n && 0 == clock_from && 0 != clock_to && h_num != last_n && 4 != retry)
	{
		retry++;
		goto retry;
	}

	h_value[h_num] = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() h_num:%d", __function_name, h_num);

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

int	DBtable_exists(const char *table_name)
{
	char		*table_name_esc;
	DB_RESULT	result;
	int		ret;

	table_name_esc = DBdyn_escape_string(table_name);

#if defined(HAVE_IBM_DB2)
	/* publib.boulder.ibm.com/infocenter/db2luw/v9r7/topic/com.ibm.db2.luw.admin.cmd.doc/doc/r0001967.html */
	result = DBselect(
			"select 1"
			" from syscat.tables"
			" where tabschema=user"
				" and lower(tabname)='%s'",
			table_name_esc);
#elif defined(HAVE_MYSQL)
	result = DBselect("show tables like '%s'", table_name_esc);
#elif defined(HAVE_ORACLE)
	result = DBselect(
			"select 1"
			" from tab"
			" where tabtype='TABLE'"
				" and lower(tname)='%s'",
			table_name_esc);
#elif defined(HAVE_POSTGRESQL)
	result = DBselect(
			"select 1"
			" from information_schema.tables"
			" where table_name='%s'"
				" and table_schema='public'",
			table_name_esc);
#elif defined(HAVE_SQLITE3)
	result = DBselect(
			"select 1"
			" from sqlite_master"
			" where tbl_name='%s'"
				" and type='table'",
			table_name_esc);
#endif

	zbx_free(table_name_esc);

	ret = (NULL == DBfetch(result) ? FAIL : SUCCEED);

	DBfree_result(result);

	return ret;
}

int	DBfield_exists(const char *table_name, const char *field_name)
{
	DB_RESULT	result;
#if defined(HAVE_IBM_DB2)
	char		*table_name_esc, *field_name_esc;
	int		ret;
#elif defined(HAVE_MYSQL)
	char		*field_name_esc;
	int		ret;
#elif defined(HAVE_ORACLE)
	char		*table_name_esc, *field_name_esc;
	int		ret;
#elif defined(HAVE_POSTGRESQL)
	char		*table_name_esc, *field_name_esc;
	int		ret;
#elif defined(HAVE_SQLITE3)
	char		*table_name_esc;
	DB_ROW		row;
	int		ret = FAIL;
#endif

#if defined(HAVE_IBM_DB2)
	table_name_esc = DBdyn_escape_string(table_name);
	field_name_esc = DBdyn_escape_string(field_name);

	result = DBselect(
			"select 1"
			" from syscat.columns"
			" where lower(tabname)='%s'"
				" and lower(colname)='%s'",
			table_name_esc, field_name_esc);

	zbx_free(field_name_esc);
	zbx_free(table_name_esc);

	ret = (NULL == DBfetch(result) ? FAIL : SUCCEED);

	DBfree_result(result);
#elif defined(HAVE_MYSQL)
	field_name_esc = DBdyn_escape_string(field_name);

	result = DBselect("show columns from %s like '%s'",
			table_name, field_name_esc, ZBX_SQL_LIKE_ESCAPE_CHAR);

	zbx_free(field_name_esc);

	ret = (NULL == DBfetch(result) ? FAIL : SUCCEED);

	DBfree_result(result);
#elif defined(HAVE_ORACLE)
	table_name_esc = DBdyn_escape_string(table_name);
	field_name_esc = DBdyn_escape_string(field_name);

	result = DBselect(
			"select 1"
			" from col"
			" where lower(tname)='%s'"
				" and lower(cname)='%s'",
			table_name_esc, field_name_esc);

	zbx_free(field_name_esc);
	zbx_free(table_name_esc);

	ret = (NULL == DBfetch(result) ? FAIL : SUCCEED);

	DBfree_result(result);
#elif defined(HAVE_POSTGRESQL)
	table_name_esc = DBdyn_escape_string(table_name);
	field_name_esc = DBdyn_escape_string(field_name);

	result = DBselect(
			"select 1"
			" from information_schema.columns"
			" where table_name='%s'"
				" and column_name='%s'",
			table_name_esc, field_name_esc);

	zbx_free(field_name_esc);
	zbx_free(table_name_esc);

	ret = (NULL == DBfetch(result) ? FAIL : SUCCEED);

	DBfree_result(result);
#elif defined(HAVE_SQLITE3)
	table_name_esc = DBdyn_escape_string(table_name);

	result = DBselect("PRAGMA table_info('%s')", table_name_esc);

	zbx_free(table_name_esc);

	while (NULL != (row = DBfetch(result)))
	{
		if (0 != strcmp(field_name, row[1]))
			continue;

		ret = SUCCEED;
		break;
	}
	DBfree_result(result);
#endif

	return ret;
}
