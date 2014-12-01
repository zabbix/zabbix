/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
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

#ifndef ZABBIX_ZBXDB_H
#define ZABBIX_ZBXDB_H

#include "common.h"

#define ZBX_DB_OK	0
#define ZBX_DB_FAIL	-1
#define ZBX_DB_DOWN	-2

#define ZBX_MAX_SQL_SIZE	262144	/* 256KB */

#if defined(HAVE_IBM_DB2)

#	include <sqlcli1.h>

	typedef struct
	{
		SQLHANDLE	henv;
		SQLHANDLE	hdbc;
	}
	zbx_ibm_db2_handle_t;

#	define DB_ROW		char **
#	define DB_RESULT	ZBX_IBM_DB2_RESULT *
#	define DBfree_result	IBM_DB2free_result

	typedef struct
	{
		SQLHANDLE	hstmt;
		SQLSMALLINT	nalloc;
		SQLSMALLINT	ncolumn;
		DB_ROW		values;
		DB_ROW		values_cli;
		SQLINTEGER	*values_len;
	}
	ZBX_IBM_DB2_RESULT;

	void	IBM_DB2free_result(DB_RESULT result);
	int	IBM_DB2server_status();
	int	zbx_ibm_db2_success(SQLRETURN ret);
	int	zbx_ibm_db2_success_ext(SQLRETURN ret);
	void	zbx_ibm_db2_log_errors(SQLSMALLINT htype, SQLHANDLE hndl);

#elif defined(HAVE_MYSQL)

#	include "mysql.h"
#	include "errmsg.h"
#	include "mysqld_error.h"

#	define DB_ROW		MYSQL_ROW
#	define DB_RESULT	MYSQL_RES *
#	define DBfree_result	mysql_free_result

#elif defined(HAVE_ORACLE)

#	include "oci.h"

	typedef struct
	{
		OCIEnv		*envhp;
		OCIError	*errhp;
		OCISvcCtx	*svchp;
		OCIServer	*srvhp;
		OCIStmt		*stmthp;	/* the statement handle for execute operations */
	}
	zbx_oracle_db_handle_t;

#	define DB_ROW		char **
#	define DB_RESULT	ZBX_OCI_DB_RESULT *
#	define DBfree_result	OCI_DBfree_result

	typedef struct
	{
		OCIStmt		*stmthp;	/* the statement handle for select operations */
		int 		ncolumn;
		DB_ROW		values;
		ub4		*values_alloc;
		OCILobLocator	**clobs;
	}
	ZBX_OCI_DB_RESULT;

	void	OCI_DBfree_result(DB_RESULT result);
	ub4	OCI_DBserver_status();

#elif defined(HAVE_POSTGRESQL)

#	include <libpq-fe.h>

#	define DB_ROW		char **
#	define DB_RESULT	ZBX_PG_DB_RESULT *
#	define DBfree_result	PG_DBfree_result

	typedef struct
	{
		PGresult	*pg_result;
		int		row_num;
		int		fld_num;
		int		cursor;
		DB_ROW		values;
	}
	ZBX_PG_DB_RESULT;

	void	PG_DBfree_result(DB_RESULT result);

#elif defined(HAVE_SQLITE3)

#	include <sqlite3.h>

#	define DB_ROW		char **
#	define DB_RESULT	ZBX_SQ_DB_RESULT *
#	define DBfree_result	SQ_DBfree_result

	typedef struct
	{
		int		curow;
		char		**data;
		int		nrow;
		int		ncolumn;
		DB_ROW		values;
	}
	ZBX_SQ_DB_RESULT;

	void	SQ_DBfree_result(DB_RESULT result);

#endif	/* HAVE_SQLITE3 */

#ifdef HAVE_SQLITE3
	/* we have to put double % here for sprintf */
#	define ZBX_SQL_MOD(x, y) #x "%%" #y
#else
#	define ZBX_SQL_MOD(x, y) "mod(" #x "," #y ")"
#endif

#ifdef HAVE_MULTIROW_INSERT
#	define ZBX_ROW_DL	","
#else
#	define ZBX_ROW_DL	";\n"
#endif

int	zbx_db_connect(char *host, char *user, char *password, char *dbname, char *dbschema, char *dbsocket, int port);
#ifdef HAVE_SQLITE3
void	zbx_create_sqlite3_mutex(void);
void	zbx_remove_sqlite3_mutex(void);
#endif
void	zbx_db_init(const char *dbname, const char *const db_schema);
void    zbx_db_close(void);

int	zbx_db_begin(void);
int	zbx_db_commit(void);
int	zbx_db_rollback(void);
int	zbx_db_txn_level(void);
int	zbx_db_txn_error(void);

#ifdef HAVE_ORACLE
int		zbx_db_statement_prepare(const char *sql);
int		zbx_db_bind_parameter(int position, void *buffer, unsigned char type);
int		zbx_db_statement_execute();
#endif
int		zbx_db_vexecute(const char *fmt, va_list args);
DB_RESULT	zbx_db_vselect(const char *fmt, va_list args);
DB_RESULT	zbx_db_select_n(const char *query, int n);

DB_ROW		zbx_db_fetch(DB_RESULT result);
int		zbx_db_is_null(const char *field);

char		*zbx_db_dyn_escape_string(const char *src);
char		*zbx_db_dyn_escape_string_len(const char *src, size_t max_src_len);
#define ZBX_SQL_LIKE_ESCAPE_CHAR '!'
char		*zbx_db_dyn_escape_like_pattern(const char *src);

int		zbx_db_strlen_n(const char *text, size_t maxlen);

#endif
