/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

typedef struct
{
	zbx_uint64_t	autoreg_hostid;
	char		*host;
	char		*ip;
	char		*dns;
	char		*host_metadata;
	int		now;
	unsigned short	port;
}
zbx_autoreg_host_t;

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

		zabbix_log(LOG_LEVEL_ERR, "database is down: reconnecting in %d seconds", ZBX_DB_WAIT_DOWN);
		connection_failure = 1;
		zbx_sleep(ZBX_DB_WAIT_DOWN);
	}

	if (0 != connection_failure)
	{
		zabbix_log(LOG_LEVEL_ERR, "database connection re-established");
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
int	DBinit(char **error)
{
	return zbx_db_init(CONFIG_DBNAME, db_schema, error);
}

void	DBdeinit(void)
{
	zbx_db_deinit();
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
static void	DBtxn_operation(int (*txn_operation)(void))
{
	int	rc;

	rc = txn_operation();

	while (ZBX_DB_DOWN == rc)
	{
		DBclose();
		DBconnect(ZBX_DB_CONNECT_NORMAL);

		if (ZBX_DB_DOWN == (rc = txn_operation()))
		{
			zabbix_log(LOG_LEVEL_ERR, "database is down: retrying in %d seconds", ZBX_DB_WAIT_DOWN);
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
int	DBcommit(void)
{
	if (ZBX_DB_OK > zbx_db_commit())
	{
		zabbix_log(LOG_LEVEL_DEBUG, "commit called on failed transaction, doing a rollback instead");
		DBrollback();
	}

	return zbx_db_txn_end_error();
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
	if (ZBX_DB_OK > zbx_db_rollback())
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot perform transaction rollback, connection will be reset");

		DBclose();
		DBconnect(ZBX_DB_CONNECT_NORMAL);
	}
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
		DBcommit();
	else
		DBrollback();
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
			zabbix_log(LOG_LEVEL_ERR, "database is down: retrying in %d seconds", ZBX_DB_WAIT_DOWN);
			connection_failure = 1;
			sleep(ZBX_DB_WAIT_DOWN);
		}
	}
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
			zabbix_log(LOG_LEVEL_ERR, "database is down: retrying in %d seconds", ZBX_DB_WAIT_DOWN);
			connection_failure = 1;
			sleep(ZBX_DB_WAIT_DOWN);
		}
	}

	va_end(args);

	return rc;
}

/******************************************************************************
 *                                                                            *
 * Function: __zbx_DBexecute_once                                             *
 *                                                                            *
 * Purpose: execute a non-select statement                                    *
 *                                                                            *
 * Comments: don't retry if DB is down                                        *
 *                                                                            *
 ******************************************************************************/
int	__zbx_DBexecute_once(const char *fmt, ...)
{
	va_list	args;
	int	rc;

	va_start(args, fmt);

	rc = zbx_db_vexecute(fmt, args);

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
			zabbix_log(LOG_LEVEL_ERR, "database is down: retrying in %d seconds", ZBX_DB_WAIT_DOWN);
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
			zabbix_log(LOG_LEVEL_ERR, "database is down: retrying in %d seconds", ZBX_DB_WAIT_DOWN);
			connection_failure = 1;
			sleep(ZBX_DB_WAIT_DOWN);
		}
	}

	return rc;
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

#ifdef HAVE_MYSQL
static size_t	get_string_field_size(unsigned char type)
{
	switch(type)
	{
		case ZBX_TYPE_LONGTEXT:
			return ZBX_SIZE_T_MAX;
		case ZBX_TYPE_CHAR:
		case ZBX_TYPE_TEXT:
		case ZBX_TYPE_SHORTTEXT:
			return 65535u;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
	}
}
#elif HAVE_ORACLE
static size_t	get_string_field_size(unsigned char type)
{
	switch(type)
	{
		case ZBX_TYPE_LONGTEXT:
		case ZBX_TYPE_TEXT:
			return ZBX_SIZE_T_MAX;
		case ZBX_TYPE_CHAR:
		case ZBX_TYPE_SHORTTEXT:
			return 4000u;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
	}
}
#endif

/******************************************************************************
 *                                                                            *
 * Function: DBdyn_escape_string_len                                          *
 *                                                                            *
 ******************************************************************************/
char	*DBdyn_escape_string_len(const char *src, size_t length)
{
#if HAVE_IBM_DB2	/* IBM DB2 fields are limited by bytes rather than characters */
	return zbx_db_dyn_escape_string(src, length, ZBX_SIZE_T_MAX, ESCAPE_SEQUENCE_ON);
#else
	return zbx_db_dyn_escape_string(src, ZBX_SIZE_T_MAX, length, ESCAPE_SEQUENCE_ON);
#endif
}

/******************************************************************************
 *                                                                            *
 * Function: DBdyn_escape_string                                              *
 *                                                                            *
 ******************************************************************************/
char	*DBdyn_escape_string(const char *src)
{
	return zbx_db_dyn_escape_string(src, ZBX_SIZE_T_MAX, ZBX_SIZE_T_MAX, ESCAPE_SEQUENCE_ON);
}

/******************************************************************************
 *                                                                            *
 * Function: DBdyn_escape_field_len                                           *
 *                                                                            *
 ******************************************************************************/
static char	*DBdyn_escape_field_len(const ZBX_FIELD *field, const char *src, zbx_escape_sequence_t flag)
{
	size_t	length;

	if (ZBX_TYPE_LONGTEXT == field->type && 0 == field->length)
		length = ZBX_SIZE_T_MAX;
	else
		length = field->length;

#if defined(HAVE_MYSQL) || defined(HAVE_ORACLE)
	return zbx_db_dyn_escape_string(src, get_string_field_size(field->type), length, flag);
#elif HAVE_IBM_DB2	/* IBM DB2 fields are limited by bytes rather than characters */
	return zbx_db_dyn_escape_string(src, length, ZBX_SIZE_T_MAX, flag);
#else
	return zbx_db_dyn_escape_string(src, ZBX_SIZE_T_MAX, length, flag);
#endif
}

/******************************************************************************
 *                                                                            *
 * Function: DBdyn_escape_field                                               *
 *                                                                            *
 ******************************************************************************/
char	*DBdyn_escape_field(const char *table_name, const char *field_name, const char *src)
{
	const ZBX_TABLE	*table;
	const ZBX_FIELD	*field;

	if (NULL == (table = DBget_table(table_name)) || NULL == (field = DBget_field(table, field_name)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "invalid table: \"%s\" field: \"%s\"", table_name, field_name);
		exit(EXIT_FAILURE);
	}

	return DBdyn_escape_field_len(field, src, ESCAPE_SEQUENCE_ON);
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

				if (ret1 + num == ret2)
					found = SUCCEED;
			}
			else
				THIS_SHOULD_NEVER_HAPPEN;

			DBfree_result(result);
		}
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():" ZBX_FS_UI64 " table:'%s' recid:'%s'",
			__function_name, ret2 - num + 1, table->table, table->recid);

	return ret2 - num + 1;
}

zbx_uint64_t	DBget_maxid_num(const char *tablename, int num)
{
	if (0 == strcmp(tablename, "events") ||
			0 == strcmp(tablename, "event_tag") ||
			0 == strcmp(tablename, "problem_tag") ||
			0 == strcmp(tablename, "dservices") ||
			0 == strcmp(tablename, "dhosts") ||
			0 == strcmp(tablename, "alerts") ||
			0 == strcmp(tablename, "escalations") ||
			0 == strcmp(tablename, "autoreg_host") ||
			0 == strcmp(tablename, "task_remote_command") ||
			0 == strcmp(tablename, "task_remote_command_result"))
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
#if defined(HAVE_SQLITE3)
	int		expr_num, expr_cnt = 0;
#endif
	if (0 == num)
		return;

	zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, ' ');

	/* Store lengths of consecutive sequences of values in a temporary array 'seq_len'. */
	/* An isolated value is represented as a sequence with length 1. */
	seq_len = (int *)zbx_malloc(seq_len, num * sizeof(int));

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

#if defined(HAVE_SQLITE3)
	expr_num = between_num + (in_num + MAX_EXPRESSIONS - 1) / MAX_EXPRESSIONS;

	if (MAX_EXPRESSIONS < expr_num)
		zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, '(');
#endif
	/* compose "between"s */
	for (i = 0, first = 1, start = 0; i < seq_num; i++)
	{
		if (MIN_NUM_BETWEEN <= seq_len[i])
		{
			if (1 != first)
			{
#if defined(HAVE_SQLITE3)
				if (MAX_EXPRESSIONS == ++expr_cnt)
				{
					zbx_strcpy_alloc(sql, sql_alloc, sql_offset, ") or (");
					expr_cnt = 0;
				}
				else
#endif
					zbx_strcpy_alloc(sql, sql_alloc, sql_offset, " or ");
			}
			else
				first = 0;

			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s between " ZBX_FS_UI64 " and " ZBX_FS_UI64,
					fieldname, values[start], values[start + seq_len[i] - 1]);
		}

		start += seq_len[i];
	}

	if (0 < in_num && 0 < between_num)
	{
#if defined(HAVE_SQLITE3)
		if (MAX_EXPRESSIONS == ++expr_cnt)
		{
			zbx_strcpy_alloc(sql, sql_alloc, sql_offset, ") or (");
			expr_cnt = 0;
		}
		else
#endif
			zbx_strcpy_alloc(sql, sql_alloc, sql_offset, " or ");
	}

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
#if defined(HAVE_SQLITE3)
						if (MAX_EXPRESSIONS == ++expr_cnt)
						{
							zbx_snprintf_alloc(sql, sql_alloc, sql_offset, ")) or (%s in (",
									fieldname);
							expr_cnt = 0;
						}
						else
						{
#endif
							zbx_snprintf_alloc(sql, sql_alloc, sql_offset, ") or %s in (",
									fieldname);
#if defined(HAVE_SQLITE3)
						}
#endif
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

#if defined(HAVE_SQLITE3)
	if (MAX_EXPRESSIONS < expr_num)
		zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, ')');
#endif
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
 * Comments: To support Oracle empty values are checked separately (is null   *
 *           for Oracle and ='' for the other databases).                     *
 *                                                                            *
 ******************************************************************************/
void	DBadd_str_condition_alloc(char **sql, size_t *sql_alloc, size_t *sql_offset, const char *fieldname,
		const char **values, const int num)
{
#define MAX_EXPRESSIONS	950

	int	i, cnt = 0;
	char	*value_esc;
	int	values_num = 0, empty_num = 0;

	if (0 == num)
		return;

	zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, ' ');

	for (i = 0; i < num; i++)
	{
		if ('\0' == *values[i])
			empty_num++;
		else
			values_num++;
	}

	if (MAX_EXPRESSIONS < values_num || (0 != values_num && 0 != empty_num))
		zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, '(');

	if (0 != empty_num)
	{
		zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s" ZBX_SQL_STRCMP, fieldname, ZBX_SQL_STRVAL_EQ(""));

		if (0 == values_num)
			return;

		zbx_strcpy_alloc(sql, sql_alloc, sql_offset, " or ");
	}

	if (1 == values_num)
	{
		for (i = 0; i < num; i++)
		{
			if ('\0' == *values[i])
				continue;

			value_esc = DBdyn_escape_string(values[i]);
			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%s='%s'", fieldname, value_esc);
			zbx_free(value_esc);
		}

		if (0 != empty_num)
			zbx_chrcpy_alloc(sql, sql_alloc, sql_offset, ')');
		return;
	}

	zbx_strcpy_alloc(sql, sql_alloc, sql_offset, fieldname);
	zbx_strcpy_alloc(sql, sql_alloc, sql_offset, " in (");

	for (i = 0; i < num; i++)
	{
		if ('\0' == *values[i])
			continue;

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

	if (MAX_EXPRESSIONS < values_num || 0 != empty_num)
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
 * Function: zbx_check_user_permissions                                       *
 *                                                                            *
 * Purpose: check if user has access rights to information - full name, alias,*
 *          Email, SMS, Jabber, etc                                           *
 *                                                                            *
 * Parameters: userid           - [IN] user who owns the information          *
 *             recipient_userid - [IN] user who will receive the information  *
 *                                     can be NULL for remote command         *
 *                                                                            *
 * Return value: SUCCEED - if information receiving user has access rights    *
 *               FAIL    - otherwise                                          *
 *                                                                            *
 * Comments: Users has access rights or can view personal information only    *
 *           about themselves and other user who belong to their group.       *
 *           "Zabbix Super Admin" can view and has access rights to           *
 *           information about any user.                                      *
 *                                                                            *
 ******************************************************************************/
int	zbx_check_user_permissions(const zbx_uint64_t *userid, const zbx_uint64_t *recipient_userid)
{
	const char	*__function_name = "zbx_check_user_permissions";

	DB_RESULT	result;
	DB_ROW		row;
	int		user_type = -1, ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (NULL == recipient_userid || *userid == *recipient_userid)
		goto out;

	result = DBselect("select type from users where userid=" ZBX_FS_UI64, *recipient_userid);

	if (NULL != (row = DBfetch(result)) && FAIL == DBis_null(row[0]))
		user_type = atoi(row[0]);
	DBfree_result(result);

	if (-1 == user_type)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "%s() cannot check permissions", __function_name);
		ret = FAIL;
		goto out;
	}

	if (USER_TYPE_SUPER_ADMIN != user_type)
	{
		/* check if users are from the same user group */
		result = DBselect(
				"select null"
				" from users_groups ug1"
				" where ug1.userid=" ZBX_FS_UI64
					" and exists (select null"
						" from users_groups ug2"
						" where ug1.usrgrpid=ug2.usrgrpid"
							" and ug2.userid=" ZBX_FS_UI64
					")",
				*userid, *recipient_userid);

		if (NULL == DBfetch(result))
			ret = FAIL;
		DBfree_result(result);
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
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

	result = DBselect("select name,surname,alias from users where userid=" ZBX_FS_UI64, userid);

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
	zbx_vector_ptr_t	autoreg_hosts;

	zbx_vector_ptr_create(&autoreg_hosts);

	DBregister_host_prepare(&autoreg_hosts, host, ip, dns, port, host_metadata, now);
	DBregister_host_flush(&autoreg_hosts, proxy_hostid);

	DBregister_host_clean(&autoreg_hosts);
	zbx_vector_ptr_destroy(&autoreg_hosts);
}

static int	DBregister_host_active(void)
{
	const char	*__function_name = "DBregister_host_active";

	DB_RESULT	result;
	int		ret = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	result = DBselect(
			"select null"
			" from actions"
			" where eventsource=%d"
				" and status=%d",
			EVENT_SOURCE_AUTO_REGISTRATION,
			ACTION_STATUS_ACTIVE);

	if (NULL == DBfetch(result))
		ret = FAIL;

	DBfree_result(result);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

static void	autoreg_host_free(zbx_autoreg_host_t *autoreg_host)
{
	zbx_free(autoreg_host->host);
	zbx_free(autoreg_host->ip);
	zbx_free(autoreg_host->dns);
	zbx_free(autoreg_host->host_metadata);
	zbx_free(autoreg_host);
}

void	DBregister_host_prepare(zbx_vector_ptr_t *autoreg_hosts, const char *host, const char *ip, const char *dns,
		unsigned short port, const char *host_metadata, int now)
{
	zbx_autoreg_host_t	*autoreg_host;
	int 			i;

	for (i = 0; i < autoreg_hosts->values_num; i++)	/* duplicate check */
	{
		autoreg_host = (zbx_autoreg_host_t *)autoreg_hosts->values[i];

		if (0 == strcmp(host, autoreg_host->host))
		{
			zbx_vector_ptr_remove(autoreg_hosts, i);
			autoreg_host_free(autoreg_host);
			break;
		}
	}

	autoreg_host = (zbx_autoreg_host_t *)zbx_malloc(NULL, sizeof(zbx_autoreg_host_t));
	autoreg_host->autoreg_hostid = 0;
	autoreg_host->host = zbx_strdup(NULL, host);
	autoreg_host->ip = zbx_strdup(NULL, ip);
	autoreg_host->dns = zbx_strdup(NULL, dns);
	autoreg_host->port = port;
	autoreg_host->host_metadata = zbx_strdup(NULL, host_metadata);
	autoreg_host->now = now;

	zbx_vector_ptr_append(autoreg_hosts, autoreg_host);
}

static void	autoreg_get_hosts(zbx_vector_ptr_t *autoreg_hosts, zbx_vector_str_t *hosts)
{
	int	i;

	for (i = 0; i < autoreg_hosts->values_num; i++)
	{
		zbx_autoreg_host_t	*autoreg_host = (zbx_autoreg_host_t *)autoreg_hosts->values[i];

		zbx_vector_str_append(hosts, autoreg_host->host);
	}
}

static void	process_autoreg_hosts(zbx_vector_ptr_t *autoreg_hosts, zbx_uint64_t proxy_hostid)
{
	DB_RESULT		result;
	DB_ROW			row;
	zbx_vector_str_t	hosts;
	char			*sql = NULL;
	size_t			sql_alloc = 256, sql_offset;
	zbx_autoreg_host_t	*autoreg_host;
	int			i;

	sql = (char *)zbx_malloc(sql, sql_alloc);
	zbx_vector_str_create(&hosts);

	if (0 != proxy_hostid)
	{
		autoreg_get_hosts(autoreg_hosts, &hosts);

		/* delete from vector if already exist in hosts table */
		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select host"
				" from hosts"
				" where proxy_hostid=" ZBX_FS_UI64
					" and",
				proxy_hostid);
		DBadd_str_condition_alloc(&sql, &sql_alloc, &sql_offset, "host",
				(const char **)hosts.values, hosts.values_num);

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			for (i = 0; i < autoreg_hosts->values_num; i++)
			{
				autoreg_host = (zbx_autoreg_host_t *)autoreg_hosts->values[i];

				if (0 == strcmp(autoreg_host->host, row[0]))
				{
					zbx_vector_ptr_remove(autoreg_hosts, i);
					autoreg_host_free(autoreg_host);
					break;
				}
			}

		}
		DBfree_result(result);

		hosts.values_num = 0;
	}

	if (0 != autoreg_hosts->values_num)
	{
		autoreg_get_hosts(autoreg_hosts, &hosts);

		/* update autoreg_id in vector if already exists in autoreg_host table */
		sql_offset = 0;
		zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
				"select autoreg_hostid,host"
				" from autoreg_host"
				" where proxy_hostid%s"
					" and",
				DBsql_id_cmp(proxy_hostid));
		DBadd_str_condition_alloc(&sql, &sql_alloc, &sql_offset, "host",
				(const char **)hosts.values, hosts.values_num);

		result = DBselect("%s", sql);

		while (NULL != (row = DBfetch(result)))
		{
			for (i = 0; i < autoreg_hosts->values_num; i++)
			{
				autoreg_host = (zbx_autoreg_host_t *)autoreg_hosts->values[i];

				if (0 == autoreg_host->autoreg_hostid && 0 == strcmp(autoreg_host->host, row[1]))
				{
					ZBX_STR2UINT64(autoreg_host->autoreg_hostid, row[0]);
					break;
				}
			}
		}
		DBfree_result(result);

		hosts.values_num = 0;
	}

	zbx_vector_str_destroy(&hosts);
	zbx_free(sql);
}

void	DBregister_host_flush(zbx_vector_ptr_t *autoreg_hosts, zbx_uint64_t proxy_hostid)
{
	const char		*__function_name = "DBregister_host_flush";

	zbx_autoreg_host_t	*autoreg_host;
	zbx_uint64_t		autoreg_hostid;
	zbx_db_insert_t		db_insert;
	int			i, create = 0, update = 0;
	char			*sql = NULL, *ip_esc, *dns_esc, *host_metadata_esc;
	size_t			sql_alloc = 256, sql_offset = 0;
	zbx_timespec_t		ts = {0, 0};

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SUCCEED != DBregister_host_active())
		goto exit;

	process_autoreg_hosts(autoreg_hosts, proxy_hostid);

	for (i = 0; i < autoreg_hosts->values_num; i++)
	{
		autoreg_host = (zbx_autoreg_host_t *)autoreg_hosts->values[i];

		if (0 == autoreg_host->autoreg_hostid)
			create++;
	}

	if (0 != create)
	{
		autoreg_hostid = DBget_maxid_num("autoreg_host", create);

		zbx_db_insert_prepare(&db_insert, "autoreg_host", "autoreg_hostid", "proxy_hostid", "host", "listen_ip",
				"listen_dns", "listen_port", "host_metadata", NULL);
	}

	if (0 != (update = autoreg_hosts->values_num - create))
	{
		sql = (char *)zbx_malloc(sql, sql_alloc);
		DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);
	}

	zbx_vector_ptr_sort(autoreg_hosts, ZBX_DEFAULT_UINT64_PTR_COMPARE_FUNC);

	for (i = 0; i < autoreg_hosts->values_num; i++)
	{
		autoreg_host = (zbx_autoreg_host_t *)autoreg_hosts->values[i];

		if (0 == autoreg_host->autoreg_hostid)
		{
			autoreg_host->autoreg_hostid = autoreg_hostid++;

			zbx_db_insert_add_values(&db_insert, autoreg_host->autoreg_hostid, proxy_hostid,
					autoreg_host->host, autoreg_host->ip, autoreg_host->dns,
					(int)autoreg_host->port, autoreg_host->host_metadata);
		}
		else
		{
			ip_esc = DBdyn_escape_string(autoreg_host->ip);
			dns_esc = DBdyn_escape_string(autoreg_host->dns);
			host_metadata_esc = DBdyn_escape_string(autoreg_host->host_metadata);

			zbx_snprintf_alloc(&sql, &sql_alloc, &sql_offset,
					"update autoreg_host"
					" set listen_ip='%s',"
						"listen_dns='%s',"
						"listen_port=%hu,"
						"host_metadata='%s'"
					" where autoreg_hostid=" ZBX_FS_UI64 ";\n",
				ip_esc, dns_esc, autoreg_host->port, host_metadata_esc, autoreg_host->autoreg_hostid);

			zbx_free(host_metadata_esc);
			zbx_free(dns_esc);
			zbx_free(ip_esc);
		}

		ts.sec = autoreg_host->now;

		zbx_add_event(EVENT_SOURCE_AUTO_REGISTRATION, EVENT_OBJECT_ZABBIX_ACTIVE, autoreg_host->autoreg_hostid,
				&ts, TRIGGER_VALUE_PROBLEM, NULL, NULL, NULL, 0, 0, NULL, 0, NULL, 0, NULL);
	}

	if (0 != create)
	{
		zbx_db_insert_execute(&db_insert);
		zbx_db_insert_clean(&db_insert);
	}

	if (0 != update)
	{
		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);
		DBexecute("%s", sql);
		zbx_free(sql);
	}

	zbx_process_events(NULL, NULL);
	zbx_clean_events();
exit:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);
}

void	DBregister_host_clean(zbx_vector_ptr_t *autoreg_hosts)
{
	zbx_vector_ptr_clear_ext(autoreg_hosts, (zbx_mem_free_func_t)autoreg_host_free);
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

	host_esc = DBdyn_escape_field("proxy_autoreg_host", "host", host);
	ip_esc = DBdyn_escape_field("proxy_autoreg_host", "listen_ip", ip);
	dns_esc = DBdyn_escape_field("proxy_autoreg_host", "listen_dns", dns);
	host_metadata_esc = DBdyn_escape_field("proxy_autoreg_host", "host_metadata", host_metadata);

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

/******************************************************************************
 *                                                                            *
 * Function: DBget_inventory_field                                            *
 *                                                                            *
 * Purpose: get corresponding host_inventory field name                       *
 *                                                                            *
 * Parameters: inventory_link - [IN] field link 1..HOST_INVENTORY_FIELD_COUNT *
 *                                                                            *
 * Return value: field name or NULL if value of inventory_link is incorrect   *
 *                                                                            *
 * Author: Alexander Vladishev                                                *
 *                                                                            *
 ******************************************************************************/
const char	*DBget_inventory_field(unsigned char inventory_link)
{
	static const char	*inventory_fields[HOST_INVENTORY_FIELD_COUNT] =
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

	if (1 > inventory_link || inventory_link > HOST_INVENTORY_FIELD_COUNT)
		return NULL;

	return inventory_fields[inventory_link - 1];
}

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

#ifndef HAVE_SQLITE3
int	DBindex_exists(const char *table_name, const char *index_name)
{
	char		*table_name_esc, *index_name_esc;
#if defined(HAVE_POSTGRESQL)
	char		*table_schema_esc;
#endif
	DB_RESULT	result;
	int		ret;

	table_name_esc = DBdyn_escape_string(table_name);
	index_name_esc = DBdyn_escape_string(index_name);

#if defined(HAVE_IBM_DB2)
	result = DBselect(
			"select 1"
			" from syscat.indexes"
			" where tabschema=user"
				" and lower(tabname)='%s'"
				" and lower(indname)='%s'",
			table_name_esc, index_name_esc);
#elif defined(HAVE_MYSQL)
	result = DBselect(
			"show index from %s"
			" where key_name='%s'",
			table_name_esc, index_name_esc);
#elif defined(HAVE_ORACLE)
	result = DBselect(
			"select 1"
			" from user_indexes"
			" where lower(table_name)='%s'"
				" and lower(index_name)='%s'",
			table_name_esc, index_name_esc);
#elif defined(HAVE_POSTGRESQL)
	table_schema_esc = DBdyn_escape_string(NULL == CONFIG_DBSCHEMA || '\0' == *CONFIG_DBSCHEMA ?
				"public" : CONFIG_DBSCHEMA);

	result = DBselect(
			"select 1"
			" from pg_indexes"
			" where tablename='%s'"
				" and indexname='%s'"
				" and schemaname='%s'",
			table_name_esc, index_name_esc, table_schema_esc);

	zbx_free(table_schema_esc);
#endif

	ret = (NULL == DBfetch(result) ? FAIL : SUCCEED);

	DBfree_result(result);

	zbx_free(table_name_esc);
	zbx_free(index_name_esc);

	return ret;
}
#endif

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

int	DBexecute_multiple_query(const char *query, const char *field_name, zbx_vector_uint64_t *ids)
{
#define ZBX_MAX_IDS	950
	char	*sql = NULL;
	size_t	sql_alloc = ZBX_KIBIBYTE, sql_offset = 0;
	int	i, ret = SUCCEED;

	sql = (char *)zbx_malloc(sql, sql_alloc);

	DBbegin_multiple_update(&sql, &sql_alloc, &sql_offset);

	for (i = 0; i < ids->values_num; i += ZBX_MAX_IDS)
	{
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, query);
		DBadd_condition_alloc(&sql, &sql_alloc, &sql_offset, field_name,
				&ids->values[i], MIN(ZBX_MAX_IDS, ids->values_num - i));
		zbx_strcpy_alloc(&sql, &sql_alloc, &sql_offset, ";\n");

		if (SUCCEED != (ret = DBexecute_overflowed_sql(&sql, &sql_alloc, &sql_offset)))
			break;
	}

	if (SUCCEED == ret && sql_offset > 16)	/* in ORACLE always present begin..end; */
	{
		DBend_multiple_update(&sql, &sql_alloc, &sql_offset);

		if (ZBX_DB_OK > DBexecute("%s", sql))
			ret = FAIL;
	}

	zbx_free(sql);

	return ret;
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
			zabbix_log(LOG_LEVEL_ERR, "Cannot locate table \"%s\" field \"%s\" in database schema",
					table, field);
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

	row = (zbx_db_value_t *)zbx_malloc(NULL, self->fields.values_num * sizeof(zbx_db_value_t));

	for (i = 0; i < self->fields.values_num; i++)
	{
		ZBX_FIELD		*field = (ZBX_FIELD *)self->fields.values[i];
		const zbx_db_value_t	*value = values[i];

		switch (field->type)
		{
			case ZBX_TYPE_LONGTEXT:
			case ZBX_TYPE_CHAR:
			case ZBX_TYPE_TEXT:
			case ZBX_TYPE_SHORTTEXT:
#ifdef HAVE_ORACLE
				row[i].str = DBdyn_escape_field_len(field, value->str, ESCAPE_SEQUENCE_OFF);
#else
				row[i].str = DBdyn_escape_field_len(field, value->str, ESCAPE_SEQUENCE_ON);
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
		field = (ZBX_FIELD *)self->fields.values[i];

		value = (zbx_db_value_t *)zbx_malloc(NULL, sizeof(zbx_db_value_t));

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
#else
	zbx_db_bind_context_t	*contexts;
	int			rc, tries = 0;
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
	sql = (char *)zbx_malloc(NULL, sql_alloc);
#endif
	sql_command = (char *)zbx_malloc(NULL, sql_command_alloc);

	/* create sql insert statement command */

	zbx_strcpy_alloc(&sql_command, &sql_command_alloc, &sql_command_offset, "insert into ");
	zbx_strcpy_alloc(&sql_command, &sql_command_alloc, &sql_command_offset, self->table->table);
	zbx_chrcpy_alloc(&sql_command, &sql_command_alloc, &sql_command_offset, ' ');

	for (i = 0; i < self->fields.values_num; i++)
	{
		field = (ZBX_FIELD *)self->fields.values[i];

		zbx_chrcpy_alloc(&sql_command, &sql_command_alloc, &sql_command_offset, delim[0 == i]);
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
		zbx_chrcpy_alloc(&sql_command, &sql_command_alloc, &sql_command_offset, delim[0 == i]);
		zbx_snprintf_alloc(&sql_command, &sql_command_alloc, &sql_command_offset, ":%d", i + 1);
	}
	zbx_chrcpy_alloc(&sql_command, &sql_command_alloc, &sql_command_offset, ')');

	contexts = (zbx_db_bind_context_t *)zbx_malloc(NULL, sizeof(zbx_db_bind_context_t) * self->fields.values_num);

retry_oracle:
	DBstatement_prepare(sql_command);

	for (j = 0; j < self->fields.values_num; j++)
	{
		field = (ZBX_FIELD *)self->fields.values[j];

		if (ZBX_DB_OK > zbx_db_bind_parameter_dyn(&contexts[j], j, field->type,
				(zbx_db_value_t **)self->rows.values, self->rows.values_num))
		{
			for (i = 0; i < j; i++)
				zbx_db_clean_bind_context(&contexts[i]);

			goto out;
		}
	}

	if (SUCCEED == zabbix_check_log_level(LOG_LEVEL_DEBUG))
	{
		for (i = 0; i < self->rows.values_num; i++)
		{
			zbx_db_value_t	*values = (zbx_db_value_t *)self->rows.values[i];
			char	*str;

			str = zbx_db_format_values((ZBX_FIELD **)self->fields.values, values, self->fields.values_num);
			zabbix_log(LOG_LEVEL_DEBUG, "insert [txnlev:%d] [%s]", zbx_db_txn_level(), str);
			zbx_free(str);
		}
	}

	rc = zbx_db_statement_execute(self->rows.values_num);

	for (j = 0; j < self->fields.values_num; j++)
		zbx_db_clean_bind_context(&contexts[j]);

	if (ZBX_DB_DOWN == rc)
	{
		if (0 < tries++)
		{
			zabbix_log(LOG_LEVEL_ERR, "database is down: retrying in %d seconds", ZBX_DB_WAIT_DOWN);
			connection_failure = 1;
			sleep(ZBX_DB_WAIT_DOWN);
		}

		DBclose();
		DBconnect(ZBX_DB_CONNECT_NORMAL);

		goto retry_oracle;
	}

	ret = (ZBX_DB_OK <= rc ? SUCCEED : FAIL);

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

			field = (const ZBX_FIELD *)self->fields.values[j];

			zbx_chrcpy_alloc(&sql, &sql_alloc, &sql_offset, delim[0 == j]);

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
#else
	zbx_free(contexts);
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
 ******************************************************************************/
void	zbx_db_insert_autoincrement(zbx_db_insert_t *self, const char *field_name)
{
	int	i;

	for (i = 0; i < self->fields.values_num; i++)
	{
		ZBX_FIELD	*field = (ZBX_FIELD *)self->fields.values[i];

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

	zbx_free(sql);

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
 * Function: zbx_sql_add_host_availability                                    *
 *                                                                            *
 * Purpose: adds host availability update to sql statement                    *
 *                                                                            *
 * Parameters: sql        - [IN/OUT] the sql statement                        *
 *             sql_alloc  - [IN/OUT] the number of bytes allocated for sql    *
 *                                   statement                                *
 *             sql_offset - [IN/OUT] the number of bytes used in sql          *
 *                                   statement                                *
 *             ha           [IN] the host availability data                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_sql_add_host_availability(char **sql, size_t *sql_alloc, size_t *sql_offset,
		const zbx_host_availability_t *ha)
{
	const char	*field_prefix[ZBX_AGENT_MAX] = {"", "snmp_", "ipmi_", "jmx_"};
	char		delim = ' ';
	int		i;

	if (FAIL == zbx_host_availability_is_set(ha))
		return FAIL;

	zbx_strcpy_alloc(sql, sql_alloc, sql_offset, "update hosts set");

	for (i = 0; i < ZBX_AGENT_MAX; i++)
	{
		if (0 != (ha->agents[i].flags & ZBX_FLAGS_AGENT_STATUS_AVAILABLE))
		{
			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%c%savailable=%d", delim, field_prefix[i],
					(int)ha->agents[i].available);
			delim = ',';
		}

		if (0 != (ha->agents[i].flags & ZBX_FLAGS_AGENT_STATUS_ERROR))
		{
			char	*error_esc;

			error_esc = DBdyn_escape_field("hosts", "error", ha->agents[i].error);
			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%c%serror='%s'", delim, field_prefix[i],
					error_esc);
			zbx_free(error_esc);
			delim = ',';
		}

		if (0 != (ha->agents[i].flags & ZBX_FLAGS_AGENT_STATUS_ERRORS_FROM))
		{
			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%c%serrors_from=%d", delim, field_prefix[i],
					ha->agents[i].errors_from);
			delim = ',';
		}

		if (0 != (ha->agents[i].flags & ZBX_FLAGS_AGENT_STATUS_DISABLE_UNTIL))
		{
			zbx_snprintf_alloc(sql, sql_alloc, sql_offset, "%c%sdisable_until=%d", delim, field_prefix[i],
					ha->agents[i].disable_until);
			delim = ',';
		}
	}

	zbx_snprintf_alloc(sql, sql_alloc, sql_offset, " where hostid=" ZBX_FS_UI64, ha->hostid);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Function: DBget_user_by_active_session                                     *
 *                                                                            *
 * Purpose: validate that session is active and get associated user data      *
 *                                                                            *
 * Parameters: sessionid - [IN] the session id to validate                    *
 *             user      - [OUT] user information                             *
 *                                                                            *
 * Return value:  SUCCEED - session is active and user data was retrieved     *
 *                FAIL    - otherwise                                         *
 *                                                                            *
 ******************************************************************************/
int	DBget_user_by_active_session(const char *sessionid, zbx_user_t *user)
{
	const char	*__function_name = "DBget_user_by_active_session";
	char		*sessionid_esc;
	int		ret = FAIL;
	DB_RESULT	result;
	DB_ROW		row;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() sessionid:%s", __function_name, sessionid);

	sessionid_esc = DBdyn_escape_string(sessionid);

	if (NULL == (result = DBselect(
			"select u.userid,u.type"
				" from sessions s,users u"
			" where s.userid=u.userid"
				" and s.sessionid='%s'"
				" and s.status=%d",
			sessionid_esc, ZBX_SESSION_ACTIVE)))
	{
		goto out;
	}

	if (NULL == (row = DBfetch(result)))
		goto out;

	ZBX_STR2UINT64(user->userid, row[0]);
	user->type = atoi(row[1]);

	ret = SUCCEED;
out:
	DBfree_result(result);
	zbx_free(sessionid_esc);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}
