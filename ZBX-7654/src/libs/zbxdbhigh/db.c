/*
** Zabbix
** Copyright (C) 2001-2015 Zabbix SIA
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

static int	connection_failure;

void	DBclose(void)
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
			exit(EXIT_FAILURE);
		}

		zabbix_log(LOG_LEVEL_WARNING, "database is down: reconnecting in %d seconds", ZBX_DB_WAIT_DOWN);
		connection_failure = 1;
		zbx_sleep(ZBX_DB_WAIT_DOWN);
	}

	if (0 != connection_failure)
	{
		zabbix_log(LOG_LEVEL_WARNING, "database connection re-established");
		connection_failure = 0;
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
void	DBinit(void)
{
	zbx_db_init(CONFIG_DBNAME, db_schema);
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
			zabbix_log(LOG_LEVEL_WARNING, "database is down: retrying in %d seconds", ZBX_DB_WAIT_DOWN);
			connection_failure = 1;
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
void	DBbegin(void)
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
void	DBcommit(void)
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
void	DBrollback(void)
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
			zabbix_log(LOG_LEVEL_WARNING, "database is down: retrying in %d seconds", ZBX_DB_WAIT_DOWN);
			connection_failure = 1;
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
			zabbix_log(LOG_LEVEL_WARNING, "database is down: retrying in %d seconds", ZBX_DB_WAIT_DOWN);
			connection_failure = 1;
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
			zabbix_log(LOG_LEVEL_WARNING, "database is down: retrying in %d seconds", ZBX_DB_WAIT_DOWN);
			connection_failure = 1;
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
			zabbix_log(LOG_LEVEL_WARNING, "database is down: retrying in %d seconds", ZBX_DB_WAIT_DOWN);
			connection_failure = 1;
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
			zabbix_log(LOG_LEVEL_WARNING, "database is down: retrying in %d seconds", ZBX_DB_WAIT_DOWN);
			connection_failure = 1;
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
			zabbix_log(LOG_LEVEL_WARNING, "database is down: retrying in %d seconds", ZBX_DB_WAIT_DOWN);
			connection_failure = 1;
			sleep(ZBX_DB_WAIT_DOWN);
		}
	}

	return rc;
}

/******************************************************************************
 *                                                                            *
 * Function: process_trigger                                                  *
 *                                                                            *
 * Purpose: 1) generate sql statement for updating trigger                    *
 *          2) add events                                                     *
 *          3) update cached trigger                                          *
 *                                                                            *
 * Return value: SUCCEED - trigger processed successfully                     *
 *               FAIL    - no changes                                         *
 *                                                                            *
 * Comments: do not process if there are dependencies with value PROBLEM      *
 *                                                                            *
 ******************************************************************************/
int	process_trigger(char **sql, size_t *sql_alloc, size_t *sql_offset, const struct _DC_TRIGGER *trigger)
{
	const char	*__function_name = "process_trigger";

	const char	*new_error_local;
	char		*new_error_esc;
	int		new_state, new_value, new_lastchange, value_changed, state_changed, multiple_problem,
			error_changed, ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() triggerid:" ZBX_FS_UI64 " value:%d(%d) new_value:%d",
			__function_name, trigger->triggerid, trigger->value, trigger->state, trigger->new_value);

	if (TRIGGER_VALUE_UNKNOWN == trigger->new_value)
	{
		new_state = TRIGGER_STATE_UNKNOWN;
		new_value = trigger->value;
	}
	else
	{
		new_state = TRIGGER_STATE_NORMAL;
		new_value = trigger->new_value;
	}

	/**************************************************************************************************/
	/*                                                                                                */
	/* The following table shows in which cases trigger should be updated and/or events should be     */
	/* generated. Trigger value(state) changes from state "from" to state "to":                       */
	/*                                                                                                */
	/*   _          |                                                                                 */
	/*    \__ to    |                                                                                 */
	/*       \_____ |   OK           OK(?)        PROBLEM     PROBLEM(?)                              */
	/*   from      \|                                                                                 */
	/*              |                                                                                 */
	/*  ------------+------------------------------------------------------                           */
	/*              |                                                                                 */
	/*  OK          |   no           T+I          T+E         I                                       */
	/*              |                                                                                 */
	/*  OK(?)       |   T+I          T(e)         T+E+I       -                                       */
	/*              |                                                                                 */
	/*  PROBLEM     |   T+E          I            T(m)+E(m)   T+I                                     */
	/*              |                                                                                 */
	/*  PROBLEM(?)  |   T+E+I        -            T+E(m)+I    T(e)                                    */
	/*              |                                                                                 */
	/*                                                                                                */
	/* Legend:                                                                                        */
	/*                                                                                                */
	/*  ?   - unknown state                                                                           */
	/*  -   - should never happen                                                                     */
	/*  no  - do nothing                                                                              */
	/*  T   - update a trigger                                                                        */
	/*  E   - generate an event                                                                       */
	/*  (m) - if it is a "multiple PROBLEM events" trigger                                            */
	/*  (e) - if an error message has changed                                                         */
	/*  I   - generate an internal event                                                              */
	/*                                                                                                */
	/**************************************************************************************************/

	new_error_local = (NULL == trigger->new_error ? "" : trigger->new_error);
	new_lastchange = trigger->timespec.sec;

	value_changed = (trigger->value != new_value ||
			(0 == trigger->lastchange && TRIGGER_STATE_UNKNOWN != new_state));
	state_changed = (trigger->state != new_state);
	multiple_problem = (TRIGGER_TYPE_MULTIPLE_TRUE == trigger->type && TRIGGER_VALUE_PROBLEM == new_value &&
			TRIGGER_STATE_NORMAL == new_state);
	error_changed = (0 != strcmp(trigger->error, new_error_local));

	if (0 != value_changed || 0 != state_changed || 0 != multiple_problem || 0 != error_changed)
	{
		if (SUCCEED == DCconfig_check_trigger_dependencies(trigger->triggerid))
		{
			if (NULL == *sql)
			{
				*sql_alloc = 2 * ZBX_KIBIBYTE;
				*sql = zbx_malloc(*sql, *sql_alloc);
			}

			zbx_strcpy_alloc(sql, sql_alloc, sql_offset, "update triggers set ");

			if (0 != value_changed || 0 != multiple_problem)
			{
				DCconfig_set_trigger_value(trigger->triggerid, new_value, new_state, new_error_local,
						&new_lastchange);

				add_event(0, EVENT_SOURCE_TRIGGERS, EVENT_OBJECT_TRIGGER, trigger->triggerid,
						&trigger->timespec, new_value, trigger->description,
						trigger->expression_orig, trigger->priority, trigger->type);

				zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "lastchange=%d,", new_lastchange);
			}
			else
			{
				DCconfig_set_trigger_value(trigger->triggerid, new_value, new_state, new_error_local,
						NULL);
			}

			if (0 != value_changed)
				zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "value=%d,", new_value);

			if (0 != state_changed)
			{
				add_event(0, EVENT_SOURCE_INTERNAL, EVENT_OBJECT_TRIGGER, trigger->triggerid,
						&trigger->timespec, new_state, trigger->description,
						trigger->expression_orig, trigger->priority, trigger->type);

				zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "state=%d,", new_state);
			}

			if (0 != error_changed)
			{
				new_error_esc = DBdyn_escape_string_len(new_error_local, TRIGGER_ERROR_LEN);
				zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "error='%s',", new_error_esc);
				zbx_free(new_error_esc);
			}

			(*sql_offset)--;

			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, " where triggerid=" ZBX_FS_UI64,
					trigger->triggerid);

			ret = SUCCEED;
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Comments: helper function for process_triggers()                           *
 *                                                                            *
 ******************************************************************************/
static int	zbx_trigger_topoindex_compare(const void *d1, const void *d2)
{
	const zbx_ptr_pair_t	*p1 = (const zbx_ptr_pair_t *)d1;
	const zbx_ptr_pair_t	*p2 = (const zbx_ptr_pair_t *)d2;

	const DC_TRIGGER	*t1 = (const DC_TRIGGER *)p1->first;
	const DC_TRIGGER	*t2 = (const DC_TRIGGER *)p2->first;

	ZBX_RETURN_IF_NOT_EQUAL(t1->topoindex, t2->topoindex);

	return 0;
}

void	process_triggers(zbx_vector_ptr_t *triggers)
{
	const char		*__function_name = "process_triggers";

	int			i, count = 0;
	char			*sql = NULL;
	size_t			sql_alloc, sql_offset;
	zbx_vector_ptr_pair_t	trigger_sqls;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() values_num:%d", __function_name, triggers->values_num);

	if (0 == triggers->values_num)
		goto out;

	zbx_vector_ptr_pair_create(&trigger_sqls);
	zbx_vector_ptr_pair_reserve(&trigger_sqls, triggers->values_num);

	for (i = 0; i < triggers->values_num; i++)
	{
		zbx_ptr_pair_t	trigger_sql;

		trigger_sql.first = triggers->values[i];
		trigger_sql.second = NULL;

		zbx_vector_ptr_pair_append(&trigger_sqls, trigger_sql);
	}

	zbx_vector_ptr_pair_sort(&trigger_sqls, zbx_trigger_topoindex_compare);

	for (i = 0; i < trigger_sqls.values_num; i++)
	{
		zbx_ptr_pair_t	*trigger_sql = &trigger_sqls.values[i];
		DC_TRIGGER	*trigger = (DC_TRIGGER *)trigger_sql->first;

		sql_alloc = 0;
		sql_offset = 0;

		count += (SUCCEED == process_trigger((char **)&trigger_sql->second, &sql_alloc, &sql_offset, trigger));
	}

	if (0 == count)
		goto clean;

	zbx_vector_ptr_pair_sort(&trigger_sqls, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	sql_alloc = 16 * ZBX_KIBIBYTE;
	sql_offset = 0;

	sql = zbx_malloc(sql, sql_alloc);

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	for (i = 0; i < trigger_sqls.values_num; i++)
	{
		if (NULL == trigger_sqls.values[i].second)
			continue;

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, trigger_sqls.values[i].second);
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
		DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);

		zbx_free(trigger_sqls.values[i].second);
	}

	DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

	if (sql_offset > 16)	/* in ORACLE always present begin..end; */
		DBexecute("%s", sql);

	zbx_free(sql);
clean:
	zbx_vector_ptr_pair_destroy(&trigger_sqls);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
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
		*error = zbx_dsprintf(*error, "Proxy \"%s\" does not exist.", hostname);
	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DBdyn_escape_string                                              *
 *                                                                            *
 ******************************************************************************/
char	*DBdyn_escape_string(const char *src)
{
	return zbx_db_dyn_escape_string(src);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdyn_escape_string_len                                          *
 *                                                                            *
 ******************************************************************************/
char	*DBdyn_escape_string_len(const char *src, size_t max_src_len)
{
	return zbx_db_dyn_escape_string_len(src, max_src_len);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdyn_escape_like_pattern                                        *
 *                                                                            *
 ******************************************************************************/
char	*DBdyn_escape_like_pattern(const char *src)
{
	return zbx_db_dyn_escape_like_pattern(src);
}

void	DBget_item_from_db(DB_ITEM *item, DB_ROW row)
{
	ZBX_STR2UINT64(item->itemid, row[0]);
	item->key = row[1];
	item->host_name = row[2];
	item->type = atoi(row[3]);
	item->history = atoi(row[4]);
	item->trends = atoi(row[13]);
	item->value_type = atoi(row[6]);

	ZBX_STR2UINT64(item->hostid, row[5]);
	item->delta = atoi(row[7]);

	item->units = row[8];
	item->multiplier = atoi(row[9]);
	item->formula = row[10];
	item->state = (unsigned char)atoi(row[11]);
	ZBX_DBROW2UINT64(item->valuemapid, row[12]);

	item->data_type = atoi(row[14]);
}

const ZBX_TABLE	*DBget_table(const char *tablename)
{
	int	t;

	for (t = 0; NULL != tables[t].table; t++)
	{
		if (0 == strcmp(tables[t].table, tablename))
			return &tables[t];
	}

	return NULL;
}

const ZBX_FIELD	*DBget_field(const ZBX_TABLE *table, const char *fieldname)
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
	zbx_uint64_t	min = 0, max = ZBX_DB_MAX_ID;
	int		found = FAIL, dbres;
	const ZBX_TABLE	*table;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() tablename:'%s'", __function_name, tablename);

	table = DBget_table(tablename);

	while (FAIL == found)
	{
		/* avoid eternal loop within failed transaction */
		if (0 < zbx_db_txn_level() && 0 != zbx_db_txn_error())
		{
			zabbix_log(LOG_LEVEL_DEBUG, "End of %s() transaction failed", __function_name);
			return 0;
		}

		result = DBselect("select nextid from ids where table_name='%s' and field_name='%s'",
				table->table, table->recid);

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
					exit(EXIT_FAILURE);
				}
			}
			DBfree_result(result);

			dbres = DBexecute("insert into ids (table_name,field_name,nextid)"
					" values ('%s','%s'," ZBX_FS_UI64 ")",
					table->table, table->recid, ret1);

			if (ZBX_DB_OK > dbres)
			{
				/* solving the problem of an invisible record created in a parallel transaction */
				DBexecute("update ids set nextid=nextid+1 where table_name='%s' and field_name='%s'",
						table->table, table->recid);
			}

			continue;
		}
		else
		{
			ZBX_STR2UINT64(ret1, row[0]);
			DBfree_result(result);

			if (ret1 < min || ret1 >= max)
			{
				DBexecute("delete from ids where table_name='%s' and field_name='%s'",
						table->table, table->recid);
				continue;
			}

			DBexecute("update ids set nextid=nextid+%d where table_name='%s' and field_name='%s'",
					num, table->table, table->recid);

			result = DBselect("select nextid from ids where table_name='%s' and field_name='%s'",
					table->table, table->recid);

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
			0 == strcmp(tablename, "autoreg_host"))
		return DCget_nextid(tablename, num);

	return DBget_nextid(tablename, num);
}

/******************************************************************************
 *                                                                            *
 * Function: DBadd_condition_alloc                                            *
 *                                                                            *
 * Purpose: Takes an initial part of SQL query and appends a generated        *
 *          WHERE condition. The WHERE condition is generated from the given  *
 *          list of values as a mix of <fieldname> BETWEEN <id1> AND <idN>"   *
 *          and "<fieldname> IN (<id1>,<id2>,...,<idN>)" elements.            *
 *                                                                            *
 * Parameters: sql        - [IN/OUT] buffer for SQL query construction        *
 *             sql_alloc  - [IN/OUT] size of the 'sql' buffer                 *
 *             sql_offset - [IN/OUT] current position in the 'sql' buffer     *
 *             fieldname  - [IN] field name to be used in SQL WHERE condition *
 *             values     - [IN] array of numerical values sorted in          *
 *                               ascending order to be included in WHERE      *
 *             num        - [IN] number of elements in 'values' array         *
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

#undef MAX_EXPRESSIONS
#undef MIN_NUM_BETWEEN
}

/******************************************************************************
 *                                                                            *
 * Function: DBadd_str_condition_alloc                                        *
 *                                                                            *
 * Purpose: This function is similar to DBadd_condition_alloc(), except it is *
 *          designed for generating WHERE conditions for strings. Hence, this *
 *          function is simpler, because only IN condition is possible.       *
 *                                                                            *
 * Parameters: sql        - [IN/OUT] buffer for SQL query construction        *
 *             sql_alloc  - [IN/OUT] size of the 'sql' buffer                 *
 *             sql_offset - [IN/OUT] current position in the 'sql' buffer     *
 *             fieldname  - [IN] field name to be used in SQL WHERE condition *
 *             values     - [IN] array of string values                       *
 *             num        - [IN] number of elements in 'values' array         *
 *                                                                            *
 ******************************************************************************/
void	DBadd_str_condition_alloc(char **sql, size_t *sql_alloc, size_t *sql_offset, const char *fieldname,
		const char **values, const int num)
{
#define MAX_EXPRESSIONS	950

	int	i, cnt = 0;
	char	*value_esc;

	if (0 == num)
		return;

	zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, ' ');

	if (1 == num)
	{
		value_esc = DBdyn_escape_string(values[0]);
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s='%s'", fieldname, value_esc);
		zbx_free(value_esc);

		return;
	}

	if (MAX_EXPRESSIONS < num)
		zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, '(');

	zbx_strcpy_alloc(sql, sql_alloc, sql_offset, fieldname);
	zbx_strcpy_alloc(sql, sql_alloc, sql_offset, " in (");

	for (i = 0; i < num; i++)
	{
		if (MAX_EXPRESSIONS == cnt)
		{
			cnt = 0;
			(*sql_offset)--;
			zbx_strcpy_alloc(sql, sql_alloc, sql_offset, ") or ");
			zbx_strcpy_alloc(sql, sql_alloc, sql_offset, fieldname);
			zbx_strcpy_alloc(sql, sql_alloc, sql_offset, " in (");
		}

		value_esc = DBdyn_escape_string(values[i]);
		zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, '\'');
		zbx_strcpy_alloc(sql, sql_alloc, sql_offset, value_esc);
		zbx_strcpy_alloc(sql, sql_alloc, sql_offset, "',");
		zbx_free(value_esc);

		cnt++;
	}

	(*sql_offset)--;
	zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, ')');

	if (MAX_EXPRESSIONS < num)
		zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, ')');

#undef MAX_EXPRESSIONS
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
 * Comments: NB! Do not use this function more than once in same SQL query    *
 *                                                                            *
 ******************************************************************************/
const char	*DBsql_id_cmp(zbx_uint64_t id)
{
	static char		buf[22];	/* 1 - '=', 20 - value size, 1 - '\0' */
	static const char	is_null[9] = " is null";

	if (0 == id)
		return is_null;

	zbx_snprintf(buf, sizeof(buf), "=" ZBX_FS_UI64, id);

	return buf;
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
void	DBregister_host(zbx_uint64_t proxy_hostid, const char *host, const char *ip, const char *dns,
		unsigned short port, const char *host_metadata, int now)
{
	char		*host_esc, *ip_esc, *dns_esc, *host_metadata_esc;
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
				" where proxy_hostid=" ZBX_FS_UI64
					" and host='%s'",
				proxy_hostid, host_esc);

		if (NULL != DBfetch(result))
			res = FAIL;
		DBfree_result(result);
	}

	if (SUCCEED == res)
	{
		ip_esc = DBdyn_escape_string_len(ip, INTERFACE_IP_LEN);
		dns_esc = DBdyn_escape_string_len(dns, INTERFACE_DNS_LEN);
		host_metadata_esc = DBdyn_escape_string(host_metadata);

		result = DBselect(
				"select autoreg_hostid"
				" from autoreg_host"
				" where proxy_hostid%s"
					" and host='%s'",
				DBsql_id_cmp(proxy_hostid), host_esc);

		if (NULL != (row = DBfetch(result)))
		{
			ZBX_STR2UINT64(autoreg_hostid, row[0]);

			DBexecute("update autoreg_host"
					" set listen_ip='%s',listen_dns='%s',listen_port=%d,host_metadata='%s'"
					" where autoreg_hostid=" ZBX_FS_UI64,
					ip_esc, dns_esc, (int)port, host_metadata_esc, autoreg_hostid);
		}
		else
		{
			autoreg_hostid = DBget_maxid("autoreg_host");
			DBexecute("insert into autoreg_host"
					" (autoreg_hostid,proxy_hostid,host,listen_ip,listen_dns,listen_port,"
						"host_metadata)"
					" values"
					" (" ZBX_FS_UI64 ",%s,'%s','%s','%s',%d,'%s')",
					autoreg_hostid, DBsql_id_ins(proxy_hostid),
					host_esc, ip_esc, dns_esc, (int)port, host_metadata_esc);
		}
		DBfree_result(result);

		zbx_free(host_metadata_esc);
		zbx_free(dns_esc);
		zbx_free(ip_esc);

		add_event(0, EVENT_SOURCE_AUTO_REGISTRATION, EVENT_OBJECT_ZABBIX_ACTIVE, autoreg_hostid, &ts,
				TRIGGER_VALUE_PROBLEM, NULL, NULL, 0, 0);
		process_events();
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
void	DBproxy_register_host(const char *host, const char *ip, const char *dns, unsigned short port,
		const char *host_metadata)
{
	char	*host_esc, *ip_esc, *dns_esc, *host_metadata_esc;

	host_esc = DBdyn_escape_string_len(host, HOST_HOST_LEN);
	ip_esc = DBdyn_escape_string_len(ip, INTERFACE_IP_LEN);
	dns_esc = DBdyn_escape_string_len(dns, INTERFACE_DNS_LEN);
	host_metadata_esc = DBdyn_escape_string(host_metadata);

	DBexecute("insert into proxy_autoreg_host"
			" (clock,host,listen_ip,listen_dns,listen_port,host_metadata)"
			" values"
			" (%d,'%s','%s','%s',%d,'%s')",
			(int)time(NULL), host_esc, ip_esc, dns_esc, (int)port, host_metadata_esc);

	zbx_free(host_metadata_esc);
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
 * Return value: unique host name which does not exist in the database        *
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
				" and flags<>%d"
				" and status in (%d,%d,%d)",
			host_name_sample_esc, ZBX_SQL_LIKE_ESCAPE_CHAR,
			ZBX_FLAG_DISCOVERY_PROTOTYPE,
			HOST_STATUS_MONITORED, HOST_STATUS_NOT_MONITORED, HOST_STATUS_TEMPLATE);

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

int	DBtxn_status(void)
{
	return 0 == zbx_db_txn_error() ? SUCCEED : FAIL;
}

int	DBtxn_ongoing(void)
{
	return 0 == zbx_db_txn_level() ? FAIL : SUCCEED;
}

int	DBtable_exists(const char *table_name)
{
	char		*table_name_esc;
#ifdef HAVE_POSTGRESQL
	char		*table_schema_esc;
#endif
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
	table_schema_esc = DBdyn_escape_string(NULL == CONFIG_DBSCHEMA || '\0' == *CONFIG_DBSCHEMA ?
			"public" : CONFIG_DBSCHEMA);

	result = DBselect(
			"select 1"
			" from information_schema.tables"
			" where table_name='%s'"
				" and table_schema='%s'",
			table_name_esc, table_schema_esc);

	zbx_free(table_schema_esc);

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
	char		*table_name_esc, *field_name_esc, *table_schema_esc;
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
			" where tabschema=user"
				" and lower(tabname)='%s'"
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
	table_schema_esc = DBdyn_escape_string(NULL == CONFIG_DBSCHEMA || '\0' == *CONFIG_DBSCHEMA ?
			"public" : CONFIG_DBSCHEMA);
	table_name_esc = DBdyn_escape_string(table_name);
	field_name_esc = DBdyn_escape_string(field_name);

	result = DBselect(
			"select 1"
			" from information_schema.columns"
			" where table_name='%s'"
				" and column_name='%s'"
				" and table_schema='%s'",
			table_name_esc, field_name_esc, table_schema_esc);

	zbx_free(field_name_esc);
	zbx_free(table_name_esc);
	zbx_free(table_schema_esc);

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

/******************************************************************************
 *                                                                            *
 * Function: DBselect_uint64                                                  *
 *                                                                            *
 * Parameters: sql - [IN] sql statement                                       *
 *             ids - [OUT] sorted list of selected uint64 values              *
 *                                                                            *
 ******************************************************************************/
void	DBselect_uint64(const char *sql, zbx_vector_uint64_t *ids)
{
	DB_RESULT	result;
	DB_ROW		row;
	zbx_uint64_t	id;

	result = DBselect("%s", sql);

	while (NULL != (row = DBfetch(result)))
	{
		ZBX_STR2UINT64(id, row[0]);

		zbx_vector_uint64_append(ids, id);
	}
	DBfree_result(result);

	zbx_vector_uint64_sort(ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

void	DBexecute_multiple_query(const char *query, const char *field_name, zbx_vector_uint64_t *ids)
{
#define ZBX_MAX_IDS	950
	char	*sql = NULL;
	size_t	sql_alloc = ZBX_KIBIBYTE, sql_offset = 0;
	int	i;

	sql = zbx_malloc(sql, sql_alloc);

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	for (i = 0; i < ids->values_num; i += ZBX_MAX_IDS)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, query);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, field_name,
				&ids->values[i], MIN(ZBX_MAX_IDS, ids->values_num - i));
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

		DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset);
	}

	if (sql_offset > 16)	/* in ORACLE always present begin..end; */
	{
		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		DBexecute("%s", sql);
	}

	zbx_free(sql);
}

#ifdef HAVE_ORACLE
/******************************************************************************
 *                                                                            *
 * Function: zbx_db_format_values                                             *
 *                                                                            *
 * Purpose: format bulk operation (insert, update) value list                 *
 *                                                                            *
 * Parameters: fields     - [IN] the field list                               *
 *             values     - [IN] the corresponding value list                 *
 *             values_num - [IN] the number of values to format               *
 *                                                                            *
 * Return value: the formatted value list <value1>,<value2>...                *
 *                                                                            *
 * Comments: The returned string is allocated by this function and must be    *
 *           freed by the caller later.                                       *
 *                                                                            *
 ******************************************************************************/
static char	*zbx_db_format_values(ZBX_FIELD **fields, const zbx_db_value_t *values, int values_num)
{
	int	i;
	char	*str = NULL;
	size_t	str_alloc = 0, str_offset = 0;

	for (i = 0; i < values_num; i++)
	{
		ZBX_FIELD		*field = fields[i];
		const zbx_db_value_t	*value = &values[i];

		if (0 < i)
			zbx_chrcpy_alloc(&str, &str_alloc, &str_offset, ',');

		switch (field->type)
		{
			case ZBX_TYPE_CHAR:
			case ZBX_TYPE_TEXT:
			case ZBX_TYPE_SHORTTEXT:
			case ZBX_TYPE_LONGTEXT:
				zbx_snprintf_alloc(&str, &str_alloc, &str_offset, "'%s'", value->str);
				break;
			case ZBX_TYPE_FLOAT:
				zbx_snprintf_alloc(&str, &str_alloc, &str_offset, ZBX_FS_DBL, value->dbl);
				break;
			case ZBX_TYPE_ID:
			case ZBX_TYPE_UINT:
				zbx_snprintf_alloc(&str, &str_alloc, &str_offset, ZBX_FS_UI64, value->ui64);
				break;
			case ZBX_TYPE_INT:
				zbx_snprintf_alloc(&str, &str_alloc, &str_offset, "%d", value->i32);
				break;
			default:
				zbx_strcpy_alloc(&str, &str_alloc, &str_offset, "(unknown type)");
				break;
		}
	}

	return str;
}
#endif

/******************************************************************************
 *                                                                            *
 * Function: zbx_db_insert_clean                                              *
 *                                                                            *
 * Purpose: releases resources allocated by bulk insert operations            *
 *                                                                            *
 * Parameters: self        - [IN] the bulk insert data                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_insert_clean(zbx_db_insert_t *self)
{
	int	i, j;

	for (i = 0; i < self->rows.values_num; i++)
	{
		zbx_db_value_t	*row = (zbx_db_value_t *)self->rows.values[i];

		for (j = 0; j < self->fields.values_num; j++)
		{
			ZBX_FIELD	*field = (ZBX_FIELD *)self->fields.values[j];

			switch (field->type)
			{
				case ZBX_TYPE_CHAR:
				case ZBX_TYPE_TEXT:
				case ZBX_TYPE_SHORTTEXT:
				case ZBX_TYPE_LONGTEXT:
					zbx_free(row[j].str);
			}
		}

		zbx_free(row);
	}

	zbx_vector_ptr_destroy(&self->rows);

	zbx_vector_ptr_destroy(&self->fields);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_db_insert_prepare_dyn                                        *
 *                                                                            *
 * Purpose: prepare for database bulk insert operation                        *
 *                                                                            *
 * Parameters: self        - [IN] the bulk insert data                        *
 *             table       - [IN] the target table name                       *
 *             fields      - [IN] names of the fields to insert               *
 *             fields_num  - [IN] the number of items in fields array         *
 *                                                                            *
 * Return value: Returns SUCCEED if the operation completed successfully or   *
 *               FAIL otherwise.                                              *
 *                                                                            *
 * Comments: The operation fails if the target table does not have the        *
 *           specified fields defined in its schema.                          *
 *                                                                            *
 *           Usage example:                                                   *
 *             zbx_db_insert_t ins;                                           *
 *                                                                            *
 *             zbx_db_insert_prepare(&ins, "history", "id", "value");         *
 *             zbx_db_insert_add_values(&ins, (zbx_uint64_t)1, 1.0);          *
 *             zbx_db_insert_add_values(&ins, (zbx_uint64_t)2, 2.0);          *
 *               ...                                                          *
 *             zbx_db_insert_execute(&ins);                                   *
 *             zbx_db_insert_clean(&ins);                                     *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_insert_prepare_dyn(zbx_db_insert_t *self, const ZBX_TABLE *table, const ZBX_FIELD **fields, int fields_num)
{
	int	i;

	if (0 == fields_num)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}

	self->autoincrement = -1;

	zbx_vector_ptr_create(&self->fields);
	zbx_vector_ptr_create(&self->rows);

	self->table = table;

	for (i = 0; i < fields_num; i++)
		zbx_vector_ptr_append(&self->fields, (ZBX_FIELD *)fields[i]);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_db_insert_prepare                                            *
 *                                                                            *
 * Purpose: prepare for database bulk insert operation                        *
 *                                                                            *
 * Parameters: self  - [IN] the bulk insert data                              *
 *             table - [IN] the target table name                             *
 *             ...   - [IN] names of the fields to insert                     *
 *             NULL  - [IN] terminating NULL pointer                          *
 *                                                                            *
 * Return value: Returns SUCCEED if the operation completed successfully or   *
 *               FAIL otherwise.                                              *
 *                                                                            *
 * Comments: This is a convenience wrapper for zbx_db_insert_prepare_dyn()    *
 *           function.                                                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_insert_prepare(zbx_db_insert_t *self, const char *table, ...)
{
	zbx_vector_ptr_t	fields;
	va_list			args;
	char			*field;
	const ZBX_TABLE		*ptable;
	const ZBX_FIELD		*pfield;

	/* find the table and fields in database schema */
	if (NULL == (ptable = DBget_table(table)))
	{
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}

	va_start(args, table);

	zbx_vector_ptr_create(&fields);

	while (NULL != (field = va_arg(args, char *)))
	{
		if (NULL == (pfield = DBget_field(ptable, field)))
		{
			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
		}
		zbx_vector_ptr_append(&fields, (ZBX_FIELD *)pfield);
	}

	va_end(args);

	zbx_db_insert_prepare_dyn(self, ptable, (const ZBX_FIELD **)fields.values, fields.values_num);

	zbx_vector_ptr_destroy(&fields);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_db_insert_add_values_dyn                                     *
 *                                                                            *
 * Purpose: adds row values for database bulk insert operation                *
 *                                                                            *
 * Parameters: self        - [IN] the bulk insert data                        *
 *             values      - [IN] the values to insert                        *
 *             fields_num  - [IN] the number of items in values array         *
 *                                                                            *
 * Comments: The values must be listed in the same order as the field names   *
 *           for insert preparation functions.                                *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_insert_add_values_dyn(zbx_db_insert_t *self, const zbx_db_value_t **values, int values_num)
{
	int		i;
	zbx_db_value_t	*row;

	if (values_num != self->fields.values_num)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		exit(EXIT_FAILURE);
	}

	row = zbx_malloc(NULL, self->fields.values_num * sizeof(zbx_db_value_t));

	for (i = 0; i < self->fields.values_num; i++)
	{
		ZBX_FIELD		*field = self->fields.values[i];
		const zbx_db_value_t	*value = values[i];
#ifdef HAVE_ORACLE
		size_t			str_alloc = 0, str_offset = 0;
#endif
		switch (field->type)
		{
			case ZBX_TYPE_LONGTEXT:
				if (0 == field->length)
				{
#ifdef HAVE_ORACLE
					row[i].str = zbx_strdup(NULL, value->str);
#else
					row[i].str = DBdyn_escape_string(value->str);
#endif
					break;
				}
				/* break; is not missing here */
			case ZBX_TYPE_CHAR:
			case ZBX_TYPE_TEXT:
			case ZBX_TYPE_SHORTTEXT:
#ifdef HAVE_ORACLE
				row[i].str = NULL;
				zbx_strncpy_alloc(&row[i].str, &str_alloc, &str_offset, value->str,
						zbx_strlen_utf8_nchars(value->str, field->length));
#else
				row[i].str = DBdyn_escape_string_len(value->str, field->length);
#endif
				break;
			default:
				row[i] = *value;
				break;
		}
	}

	zbx_vector_ptr_append(&self->rows, row);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_db_insert_add_values                                         *
 *                                                                            *
 * Purpose: adds row values for database bulk insert operation                *
 *                                                                            *
 * Parameters: self - [IN] the bulk insert data                               *
 *             ...  - [IN] the values to insert                               *
 *                                                                            *
 * Return value: Returns SUCCEED if the operation completed successfully or   *
 *               FAIL otherwise.                                              *
 *                                                                            *
 * Comments: This is a convenience wrapper for zbx_db_insert_add_values_dyn() *
 *           function.                                                        *
 *           Note that the types of the passed values must conform to the     *
 *           corresponding field types.                                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_insert_add_values(zbx_db_insert_t *self, ...)
{
	zbx_vector_ptr_t	values;
	va_list			args;
	int			i;
	ZBX_FIELD		*field;
	zbx_db_value_t		*value;

	va_start(args, self);

	zbx_vector_ptr_create(&values);

	for (i = 0; i < self->fields.values_num; i++)
	{
		field = self->fields.values[i];

		value = zbx_malloc(NULL, sizeof(zbx_db_value_t));

		switch (field->type)
		{
			case ZBX_TYPE_CHAR:
			case ZBX_TYPE_TEXT:
			case ZBX_TYPE_SHORTTEXT:
			case ZBX_TYPE_LONGTEXT:
				value->str = va_arg(args, char *);
				break;
			case ZBX_TYPE_INT:
				value->i32 = va_arg(args, int);
				break;
			case ZBX_TYPE_FLOAT:
				value->dbl = va_arg(args, double);
				break;
			case ZBX_TYPE_UINT:
			case ZBX_TYPE_ID:
				value->ui64 = va_arg(args, zbx_uint64_t);
				break;
			default:
				THIS_SHOULD_NEVER_HAPPEN;
				exit(EXIT_FAILURE);
		}

		zbx_vector_ptr_append(&values, value);
	}

	va_end(args);

	zbx_db_insert_add_values_dyn(self, (const zbx_db_value_t **)values.values, values.values_num);

	zbx_vector_ptr_clear_ext(&values, zbx_ptr_free);
	zbx_vector_ptr_destroy(&values);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_db_insert_execute                                            *
 *                                                                            *
 * Purpose: executes the prepared database bulk insert operation              *
 *                                                                            *
 * Parameters: self - [IN] the bulk insert data                               *
 *                                                                            *
 * Return value: Returns SUCCEED if the operation completed successfully or   *
 *               FAIL otherwise.                                              *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_insert_execute(zbx_db_insert_t *self)
{
	int		ret = FAIL, i, j;
	const ZBX_FIELD	*field;
	char		*sql_command, delim[2] = {',', '('};
	size_t		sql_command_alloc = 512, sql_command_offset = 0;

#ifndef HAVE_ORACLE
	char		*sql;
	size_t		sql_alloc = 16 * ZBX_KIBIBYTE, sql_offset = 0;

#	ifdef HAVE_MYSQL
	char		*sql_values = NULL;
	size_t		sql_values_alloc = 0, sql_values_offset = 0;
#	endif
#endif

	if (0 == self->rows.values_num)
		return SUCCEED;

	/* process the auto increment field */
	if (-1 != self->autoincrement)
	{
		zbx_uint64_t	id;

		id = DBget_maxid_num(self->table->table, self->rows.values_num);

		for (i = 0; i < self->rows.values_num; i++)
		{
			zbx_db_value_t	*values = (zbx_db_value_t *)self->rows.values[i];

			values[self->autoincrement].ui64 = id++;
		}
	}

#ifndef HAVE_ORACLE
	sql = zbx_malloc(NULL, sql_alloc);
#endif
	sql_command = zbx_malloc(NULL, sql_command_alloc);

	/* create sql insert statement command */

	zbx_strcpy_alloc(&sql_command, &sql_command_alloc, &sql_command_offset, "insert into ");
	zbx_strcpy_alloc(&sql_command, &sql_command_alloc, &sql_command_offset, self->table->table);
	zbx_chrcpy_alloc(&sql_command, &sql_command_alloc, &sql_command_offset, ' ');

	for (i = 0; i < self->fields.values_num; i++)
	{
		field = (ZBX_FIELD *)self->fields.values[i];

		zbx_chrcpy_alloc(&sql_command, &sql_command_alloc, &sql_command_offset, delim[(int)(0 == i)]);
		zbx_strcpy_alloc(&sql_command, &sql_command_alloc, &sql_command_offset, field->name);
	}

#ifdef HAVE_MYSQL
	/* MySQL workaround - explicitly add missing text fields with '' default value */
	for (field = (const ZBX_FIELD *)self->table->fields; NULL != field->name; field++)
	{
		switch (field->type)
		{
			case ZBX_TYPE_BLOB:
			case ZBX_TYPE_TEXT:
			case ZBX_TYPE_SHORTTEXT:
			case ZBX_TYPE_LONGTEXT:
				if (FAIL != zbx_vector_ptr_search(&self->fields, (void *)field,
						ZBX_DEFAULT_PTR_COMPARE_FUNC))
				{
					continue;
				}

				zbx_chrcpy_alloc(&sql_command, &sql_command_alloc, &sql_command_offset, ',');
				zbx_strcpy_alloc(&sql_command, &sql_command_alloc, &sql_command_offset, field->name);

				zbx_strcpy_alloc(&sql_values, &sql_values_alloc, &sql_values_offset, ",''");
				break;
		}
	}
#endif
	zbx_strcpy_alloc(&sql_command, &sql_command_alloc, &sql_command_offset, ") values ");

#ifdef HAVE_ORACLE
	for (i = 0; i < self->fields.values_num; i++)
	{
		zbx_chrcpy_alloc(&sql_command, &sql_command_alloc, &sql_command_offset, delim[(int)(0 == i)]);
		zbx_snprintf_alloc(&sql_command, &sql_command_alloc, &sql_command_offset, ":%d", i + 1);
	}
	zbx_chrcpy_alloc(&sql_command, &sql_command_alloc, &sql_command_offset, ')');

	DBstatement_prepare(sql_command);

	for (i = 0; i < self->rows.values_num; i++)
	{
		zbx_db_value_t	*values = (zbx_db_value_t *)self->rows.values[i];

		if (SUCCEED == zabbix_check_log_level(LOG_LEVEL_DEBUG))
		{
			char	*str;

			str = zbx_db_format_values((ZBX_FIELD **)self->fields.values, values, self->fields.values_num);
			zabbix_log(LOG_LEVEL_DEBUG, "insert [txnlev:%d] [%s]", zbx_db_txn_level(), str);
			zbx_free(str);
		}

		for (j = 0; j < self->fields.values_num; j++)
		{
			const zbx_db_value_t	*value = &values[j];

			field = self->fields.values[j];

			switch (field->type)
			{
				case ZBX_TYPE_CHAR:
				case ZBX_TYPE_TEXT:
				case ZBX_TYPE_SHORTTEXT:
				case ZBX_TYPE_LONGTEXT:
					DBbind_parameter(j + 1, (void *)value->str, field->type);
					break;
				default:
					DBbind_parameter(j + 1, (void *)value, field->type);
					break;
			}

			if (0 != zbx_db_txn_error())
			{
				zabbix_log(LOG_LEVEL_ERR, "failed to bind field: %s", field->name);
				goto out;
			}
		}
		if (ZBX_DB_OK > DBstatement_execute())
			goto out;

	}
	ret = SUCCEED;

#else
	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	for (i = 0; i < self->rows.values_num; i++)
	{
		zbx_db_value_t	*values = (zbx_db_value_t *)self->rows.values[i];

#	ifdef HAVE_MULTIROW_INSERT
		if (16 > sql_offset)
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, sql_command);
#	else
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, sql_command);
#	endif

		for (j = 0; j < self->fields.values_num; j++)
		{
			const zbx_db_value_t	*value = &values[j];

			field = self->fields.values[j];

			zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, delim[(int)(0 == j)]);

			switch (field->type)
			{
				case ZBX_TYPE_CHAR:
				case ZBX_TYPE_TEXT:
				case ZBX_TYPE_SHORTTEXT:
				case ZBX_TYPE_LONGTEXT:
					zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, '\'');
					zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, value->str);
					zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, '\'');
					break;
				case ZBX_TYPE_INT:
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "%d", value->i32);
					break;
				case ZBX_TYPE_FLOAT:
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, ZBX_FS_DBL,
							value->dbl);
					break;
				case ZBX_TYPE_UINT:
					zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, ZBX_FS_UI64,
							value->ui64);
					break;
				case ZBX_TYPE_ID:
					zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset,
							DBsql_id_ins(value->ui64));
					break;
				default:
					THIS_SHOULD_NEVER_HAPPEN;
					exit(EXIT_FAILURE);
			}
		}
#	ifdef HAVE_MYSQL
		if (NULL != sql_values)
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, sql_values);
#	endif

		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ")" ZBX_ROW_DL);

		if (SUCCEED != (ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset)))
			goto out;
	}

	if (16 < sql_offset)
	{
#	ifdef HAVE_MULTIROW_INSERT
		if (',' == sql[sql_offset - 1])
		{
			sql_offset--;
			zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");
		}
#	endif
		DBend_multiple_update(sql, sql_alloc, sql_offset);

		if (ZBX_DB_OK > DBexecute("%s", sql))
			ret = FAIL;
	}
#endif

out:
	zbx_free(sql_command);

#ifndef HAVE_ORACLE
	zbx_free(sql);

#	ifdef HAVE_MYSQL
	zbx_free(sql_values);
#	endif
#endif
	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_db_insert_autoincrement                                      *
 *                                                                            *
 * Purpose: executes the prepared database bulk insert operation              *
 *                                                                            *
 * Parameters: self - [IN] the bulk insert data                               *
 *                                                                            *
 * Return value: Returns SUCCEED if the operation completed successfully or   *
 *               FAIL otherwise.                                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_insert_autoincrement(zbx_db_insert_t *self, const char *field_name)
{
	int	i;

	for (i = 0; i < self->fields.values_num; i++)
	{
		ZBX_FIELD	*field = self->fields.values[i];

		if (ZBX_TYPE_ID == field->type && 0 == strcmp(field_name, field->name))
		{
			self->autoincrement = i;
			return;
		}
	}

	THIS_SHOULD_NEVER_HAPPEN;
	exit(EXIT_FAILURE);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_db_get_database_type                                         *
 *                                                                            *
 * Purpose: determine is it a server or a proxy database                      *
 *                                                                            *
 * Return value: ZBX_DB_SERVER - server database                              *
 *               ZBX_DB_PROXY - proxy database                                *
 *               ZBX_DB_UNKNOWN - an error occurred                           *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_get_database_type(void)
{
	const char	*__function_name = "zbx_db_get_database_type";

	const char	*result_string;
	DB_RESULT	result;
	DB_ROW		row;
	int		ret = ZBX_DB_UNKNOWN;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	DBconnect(ZBX_DB_CONNECT_NORMAL);

	if (NULL == (result = DBselectN("select userid from users", 1)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "cannot select records from \"users\" table");
		goto out;
	}

	if (NULL != (row = DBfetch(result)))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "there is at least 1 record in \"users\" table");
		ret = ZBX_DB_SERVER;
	}
	else
	{
		zabbix_log(LOG_LEVEL_DEBUG, "no records in \"users\" table");
		ret = ZBX_DB_PROXY;
	}

	DBfree_result(result);
out:
	DBclose();

	switch (ret)
	{
		case ZBX_DB_SERVER:
			result_string = "ZBX_DB_SERVER";
			break;
		case ZBX_DB_PROXY:
			result_string = "ZBX_DB_PROXY";
			break;
		case ZBX_DB_UNKNOWN:
			result_string = "ZBX_DB_UNKNOWN";
			break;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, result_string);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DBlock_record                                                    *
 *                                                                            *
 * Purpose: locks a record in a table by its primary key and an optional      *
 *          constraint field                                                  *
 *                                                                            *
 * Parameters: table     - [IN] the target table                              *
 *             id        - [IN] primary key value                             *
 *             add_field - [IN] additional constraint field name (optional)   *
 *             add_id    - [IN] constraint field value                        *
 *                                                                            *
 * Return value: SUCCEED - the record was successfully locked                 *
 *               FAIL    - the table does not contain the specified record    *
 *                                                                            *
 ******************************************************************************/
int	DBlock_record(const char *table, zbx_uint64_t id, const char *add_field, zbx_uint64_t add_id)
{
	const char	*__function_name = "DBlock_record";

	DB_RESULT	result;
	const ZBX_TABLE	*t;
	int		ret;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (0 == zbx_db_txn_level())
		zabbix_log(LOG_LEVEL_DEBUG, "%s() called outside of transaction", __function_name);

	t = DBget_table(table);

	if (NULL == add_field)
	{
		result = DBselect("select null from %s where %s=" ZBX_FS_UI64 ZBX_FOR_UPDATE, table, t->recid, id);
	}
	else
	{
		result = DBselect("select null from %s where %s=" ZBX_FS_UI64 " and %s=" ZBX_FS_UI64 ZBX_FOR_UPDATE,
				table, t->recid, id, add_field, add_id);
	}

	if (NULL == DBfetch(result))
		ret = FAIL;
	else
		ret = SUCCEED;

	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: DBlock_records                                                   *
 *                                                                            *
 * Purpose: locks a records in a table by its primary key                     *
 *                                                                            *
 * Parameters: table     - [IN] the target table                              *
 *             ids       - [IN] primary key values                            *
 *                                                                            *
 * Return value: SUCCEED - one or more of the specified records were          *
 *                         successfully locked                                *
 *               FAIL    - the table does not contain any of the specified    *
 *                         records                                            *
 *                                                                            *
 ******************************************************************************/
int	DBlock_records(const char *table, const zbx_vector_uint64_t *ids)
{
	const char	*__function_name = "DBlock_records";

	DB_RESULT	result;
	const ZBX_TABLE	*t;
	int		ret;
	char		*sql = NULL;
	size_t		sql_alloc = 0, sql_offset = 0;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (0 == zbx_db_txn_level())
		zabbix_log(LOG_LEVEL_DEBUG, "%s() called outside of transaction", __function_name);

	t = DBget_table(table);

	zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset, "select null from %s where", table);
	DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, t->recid, ids->values, ids->values_num);

	result = DBselect("%s" ZBX_FOR_UPDATE, sql);

	if (NULL == DBfetch(result))
		ret = FAIL;
	else
		ret = SUCCEED;

	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
