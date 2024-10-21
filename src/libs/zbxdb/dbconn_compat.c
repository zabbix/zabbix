/*
** Copyright (C) 2001-2024 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

#include "zbxcommon.h"
#include "zbxdb.h"
#include "zbxalgo.h"
#include "zbxdbschema.h"
#include "zbxtypes.h"

static zbx_dbconn_t	*dbconn;
static int		db_autoincrement;

void	zbx_db_init_autoincrement_options(void)
{
	db_autoincrement = 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: connect to the database                                           *
 *                                                                            *
 * Parameters: flag - ZBX_DB_CONNECT_ONCE (try once and return the result),   *
 *                    ZBX_DB_CONNECT_EXIT (exit on failure) or                *
 *                    ZBX_DB_CONNECT_NORMAL (retry until connected)           *
 *                                                                            *
 * Return value: ZBX_DB_OK - successfully connected                           *
 *               ZBX_DB_DOWN - database is down                               *
 *               ZBX_DB_FAIL - failed to connect                              *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_connect(int flag)
{
	if (NULL != dbconn)
		THIS_SHOULD_NEVER_HAPPEN;

	dbconn = zbx_dbconn_create();

	zbx_dbconn_set_connect_options(dbconn, flag);
	zbx_dbconn_set_autoincrement(dbconn, db_autoincrement);

	return zbx_dbconn_open(dbconn);
}

/******************************************************************************
 *                                                                            *
 * Purpose: close database connection                                         *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_close(void)
{
	if (NULL == dbconn)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return;
	}

	zbx_dbconn_free(dbconn);
	dbconn = NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: start a transaction                                               *
 *                                                                            *
 * Comments: do nothing if DB does not support transactions                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_begin(void)
{
	if (NULL == dbconn)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return;
	}

	(void)zbx_dbconn_begin(dbconn);

	return;
}

/******************************************************************************
 *                                                                            *
 * Purpose: commit a transaction                                              *
 *                                                                            *
 * Comments: do nothing if DB does not support transactions                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_commit(void)
{
	if (NULL == dbconn)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return ZBX_DB_FAIL;
	}

	return zbx_dbconn_commit(dbconn);
}

/******************************************************************************
 *                                                                            *
 * Purpose: rollback a transaction                                            *
 *                                                                            *
 * Comments: do nothing if DB does not support transactions                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_rollback(void)
{
	if (NULL == dbconn)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return;
	}

	(void)zbx_dbconn_rollback(dbconn);
}

/******************************************************************************
 *                                                                            *
 * Purpose: commit or rollback a transaction depending on a parameter value   *
 *                                                                            *
 * Comments: do nothing if DB does not support transactions                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_end(int ret)
{
	if (NULL == dbconn)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return FAIL;
	}

	return zbx_dbconn_end(dbconn, ret);
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute a non-select statement                                    *
 *                                                                            *
 * Comments: retry until DB is up                                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_execute(const char *fmt, ...)
{
	if (NULL == dbconn)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return FAIL;
	}

	va_list	args;
	int	rc;

	va_start(args, fmt);
	rc = zbx_dbconn_vexecute(dbconn, fmt, args);
	va_end(args);

	return rc;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute a non-select statement                                    *
 *                                                                            *
 * Comments: don't retry if DB is down                                        *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_execute_once(const char *fmt, ...)
{
	if (NULL == dbconn)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return FAIL;
	}

	va_list	args;
	int	rc, options;

	va_start(args, fmt);
	options = zbx_dbconn_set_connect_options(dbconn, ZBX_DB_CONNECT_ONCE);
	rc = zbx_dbconn_vexecute(dbconn, fmt, args);
	zbx_dbconn_set_connect_options(dbconn, options);
	va_end(args);

	return rc;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute a select statement                                        *
 *                                                                            *
 * Comments: retry until DB is up                                             *
 *                                                                            *
 ******************************************************************************/
zbx_db_result_t	zbx_db_select(const char *fmt, ...)
{
	if (NULL == dbconn)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return NULL;
	}

	va_list		args;
	zbx_db_result_t	rc;

	va_start(args, fmt);
	rc = zbx_dbconn_vselect(dbconn, fmt, args);
	va_end(args);

	return rc;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute a select statement                                        *
 *                                                                            *
 * Comments: retry until DB is up                                             *
 *                                                                            *
 ******************************************************************************/
zbx_db_result_t	zbx_db_vselect(const char *fmt, va_list args)
{
	if (NULL == dbconn)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return NULL;
	}

	return zbx_dbconn_vselect(dbconn, fmt, args);
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute a select statement and get the first N entries            *
 *                                                                            *
 * Comments: retry until DB is up                                             *
 *                                                                            *
 ******************************************************************************/
zbx_db_result_t	zbx_db_select_n(const char *query, int n)
{
	if (NULL == dbconn)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return NULL;
	}

	return zbx_dbconn_select_n(dbconn, query, n);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get next id for requested table                                   *
 *                                                                            *
 ******************************************************************************/
zbx_uint64_t	zbx_db_get_maxid_num(const char *tablename, int num)
{
	if (NULL == dbconn)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return 0;
	}

	return zbx_dbconn_get_maxid_num(dbconn, tablename, num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepare for database bulk insert operation                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_insert_prepare_dyn(zbx_db_insert_t *self, const zbx_db_table_t *table, const zbx_db_field_t **fields,
		int fields_num)
{
	if (NULL == dbconn)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return;
	}

	zbx_dbconn_prepare_insert_dyn(dbconn, self, table, fields, fields_num);
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepare for database bulk insert operation                        *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_insert_prepare(zbx_db_insert_t *self, const char *table, ...)
{
	if (NULL == dbconn)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return;
	}

	va_list	args;

	va_start(args, table);
	zbx_dbconn_prepare_vinsert(dbconn, self, table, args);
	va_end(args);
}

/******************************************************************************
 *                                                                            *
 * Purpose: connects to DB and tries to detect DB version                     *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_extract_version_info(struct zbx_db_version_info_t *version_info)
{
	if (NULL == dbconn)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return;
	}

	zbx_dbconn_extract_version_info(dbconn, version_info);
}

#ifdef HAVE_POSTGRESQL

void	zbx_tsdb_extract_compressed_chunk_flags(struct zbx_db_version_info_t *version_info)
{
	if (NULL == dbconn)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return;
	}

	zbx_dbconn_tsdb_extract_compressed_chunk_flags(dbconn, version_info);
}

/***************************************************************************************************************
 *                                                                                                             *
 * Purpose: retrieves TimescaleDB extension info, including license string and numeric version value           *
 *                                                                                                             *
 **************************************************************************************************************/
void	zbx_tsdb_info_extract(struct zbx_db_version_info_t *version_info)
{
	if (NULL == dbconn)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return;
	}

	zbx_dbconn_tsdb_info_extract(dbconn, version_info);
}

/******************************************************************************
 *                                                                            *
 * Purpose: returns TimescaleDB (TSDB) version as integer: MMmmuu             *
 *          M = major version part                                            *
 *          m = minor version part                                            *
 *          u = patch version part                                            *
 *                                                                            *
 * Example: TSDB 1.5.1 version will be returned as 10501                      *
 *                                                                            *
 * Return value: TSDB version or 0 if unknown or the extension not installed  *
 *                                                                            *
 ******************************************************************************/
int	zbx_tsdb_get_version(void)
{
	if (NULL == dbconn)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return 0;
	}

	return zbx_dbconn_tsdb_get_version(dbconn);
}

#endif

/******************************************************************************
 *                                                                            *
 * Purpose: get last error set by database                                    *
 *                                                                            *
 * Return value: last database error message                                  *
 *                                                                            *
 ******************************************************************************/
const char	*zbx_db_last_strerr(void)
{
	if (NULL == dbconn)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return NULL;
	}

	return zbx_dbconn_last_strerr(dbconn);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get last error code returned by database                          *
 *                                                                            *
 * Return value: last database error code                                     *
 *                                                                            *
 ******************************************************************************/
zbx_err_codes_t	zbx_db_last_errcode(void)
{
	if (NULL == dbconn)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return 0;
	}

	return zbx_dbconn_last_errcode(dbconn);
}

/******************************************************************************
 *                                                                            *
 * Purpose: locks a record in a table by its primary key and an optional      *
 *          constraint field                                                  *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_lock_record(const char *table, zbx_uint64_t id, const char *add_field, zbx_uint64_t add_id)
{
	if (NULL == dbconn)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return FAIL;
	}

	return zbx_dbconn_lock_record(dbconn, table, id, add_field, add_id);
}

/******************************************************************************
 *                                                                            *
 * Purpose: locks a records in a table by its primary key                     *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_lock_records(const char *table, const zbx_vector_uint64_t *ids)
{
	if (NULL == dbconn)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return FAIL;
	}

	return zbx_dbconn_lock_records(dbconn, table, ids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: locks a records in a table by field name                          *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_lock_ids(const char *table_name, const char *field_name, zbx_vector_uint64_t *ids)
{
	if (NULL == dbconn)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return FAIL;
	}

	return zbx_dbconn_lock_ids(dbconn, table_name, field_name, ids);
}

/*********************************************************************************
 *                                                                               *
 * Purpose: verify that Zabbix can work with DB extension                        *
 *                                                                               *
 *********************************************************************************/
int	zbx_db_check_extension(struct zbx_db_version_info_t *info, int allow_unsupported)
{
	if (NULL == dbconn)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return FAIL;
	}

	return zbx_dbconn_check_extension(dbconn, info, allow_unsupported);
}


/******************************************************************************
 *                                                                            *
 * Purpose: flush SQL request                                                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_flush_overflowed_sql(char *sql, size_t sql_offset)
{
	if (NULL == dbconn)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return ZBX_DB_FAIL;
	}

	return zbx_dbconn_flush_overflowed_sql(dbconn, sql, sql_offset);
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute a set of SQL statements IF it is big enough               *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_execute_overflowed_sql(char **sql, size_t *sql_alloc, size_t *sql_offset)
{
	if (NULL == dbconn)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return ZBX_DB_FAIL;
	}

	return zbx_dbconn_execute_overflowed_sql(dbconn, sql, sql_alloc, sql_offset);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if table exists                                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_table_exists(const char *table_name)
{
	if (NULL == dbconn)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return ZBX_DB_FAIL;
	}

	return zbx_dbconn_table_exists(dbconn, table_name);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if table field exists                                       *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_field_exists(const char *table_name, const char *field_name)
{
	if (NULL == dbconn)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return ZBX_DB_FAIL;
	}

	return zbx_dbconn_field_exists(dbconn, table_name, field_name);
}

#if !defined(HAVE_SQLITE3)
/******************************************************************************
 *                                                                            *
 * Purpose: check if table trigger exists                                     *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_trigger_exists(const char *table_name, const char *trigger_name)
{
	if (NULL == dbconn)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return ZBX_DB_FAIL;
	}

	return zbx_dbconn_trigger_exists(dbconn, table_name, trigger_name);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if table index exists                                       *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_index_exists(const char *table_name, const char *index_name)
{
	if (NULL == dbconn)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return ZBX_DB_FAIL;
	}

	return zbx_dbconn_index_exists(dbconn, table_name, index_name);
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if table primary key exists                                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_pk_exists(const char *table_name)
{
	if (NULL == dbconn)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return ZBX_DB_FAIL;
	}

	return zbx_dbconn_pk_exists(dbconn, table_name);
}
#endif /* !defined(HAVE_SQLITE3) */

/******************************************************************************
 *                                                                            *
 * Parameters: sql - [IN] sql statement                                       *
 *             ids - [OUT] sorted list of selected uint64 values              *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_select_uint64(const char *sql, zbx_vector_uint64_t *ids)
{
	if (NULL == dbconn)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return;
	}

	zbx_dbconn_select_uint64(dbconn, sql, ids);
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute query with large number of primary key matches in smaller *
 *          batches (last batch is not executed)                              *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_prepare_multiple_query(const char *query, const char *field_name, zbx_vector_uint64_t *ids, char **sql,
		size_t	*sql_alloc, size_t *sql_offset)
{
	if (NULL == dbconn)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return ZBX_DB_FAIL;
	}

	return zbx_dbconn_prepare_multiple_query(dbconn, query, field_name, ids, sql, sql_alloc, sql_offset);
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute query with large number of primary key matches in smaller *
 *          batches                                                           *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_execute_multiple_query(const char *query, const char *field_name, zbx_vector_uint64_t *ids)
{
	if (NULL == dbconn)
	{
		THIS_SHOULD_NEVER_HAPPEN;
		return ZBX_DB_FAIL;
	}

	return zbx_dbconn_execute_multiple_query(dbconn, query, field_name, ids);
}
