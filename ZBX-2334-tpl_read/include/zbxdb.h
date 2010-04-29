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

#ifndef ZABBIX_ZBXDB_H
#define ZABBIX_ZBXDB_H

#include "common.h"

#define	ZBX_DB_OK	(0)
#define	ZBX_DB_FAIL	(-1)
#define	ZBX_DB_DOWN	(-2)

#define ZBX_MAX_SQL_SIZE	262144	/* 256KB */

#ifdef HAVE_MYSQL
#	include "mysql.h"
#	include "errmsg.h"
#	include "mysqld_error.h"
#	define	DB_HANDLE	MYSQL
extern MYSQL	*conn;
#endif /* HAVE_MYSQL */

#ifdef HAVE_ORACLE
#	include "oci.h"
typedef struct zbx_oracle_db_handle_s {
	OCIEnv *envhp;
	OCIError *errhp;
	OCISvcCtx *svchp;
	OCIServer *srvhp;
} zbx_oracle_db_handle_t;

extern zbx_oracle_db_handle_t oracle;
#endif /* HAVE_ORACLE */

#ifdef HAVE_POSTGRESQL
#	include <libpq-fe.h>
extern PGconn	*conn;
#endif /* HAVE_POSTGRESQL */

#ifdef HAVE_SQLITE3
#	include <sqlite3.h>
extern sqlite3		*conn;
#endif /* HAVE_SQLITE3 */

#ifdef HAVE_SQLITE3
/* We have to put double % here for sprintf */
#	define ZBX_SQL_MOD(x,y) #x "%%" #y
#else
#	define ZBX_SQL_MOD(x,y) "mod(" #x "," #y ")"
#endif

#ifdef HAVE_SQLITE3

	#include "mutexs.h"

	#define DB_ROW		char **
	#define	DB_RESULT	ZBX_SQ_DB_RESULT*
	#define	DBfree_result	SQ_DBfree_result

	typedef struct zbx_sq_db_result_s
	{
		int		curow;
		char		**data;
		int		nrow;
		int		ncolumn;

		DB_ROW		values;
	} ZBX_SQ_DB_RESULT;

void	SQ_DBfree_result(DB_RESULT result);

	extern PHP_MUTEX	sqlite_access;

#endif

#ifdef HAVE_MYSQL
	#define	DB_RESULT	MYSQL_RES *
	#define	DBfree_result	mysql_free_result
	#define DB_ROW		MYSQL_ROW
#endif

#ifdef HAVE_POSTGRESQL
	#define DB_ROW		char **
	#define	DB_RESULT	ZBX_PG_DB_RESULT*
	#define	DBfree_result	PG_DBfree_result

	typedef struct zbx_pg_db_result_s
	{
		PGresult	*pg_result;
		int		row_num;
		int		fld_num;
		int		cursor;
		DB_ROW		values;
	} ZBX_PG_DB_RESULT;

void	PG_DBfree_result(DB_RESULT result);

#endif

#ifdef HAVE_ORACLE
	#define	DB_RESULT ZBX_OCI_DB_RESULT*
	#define	DBfree_result OCI_DBfree_result
	#define DB_ROW		char **

	typedef struct zbx_oci_db_result_s
	{
		OCIStmt	*stmthp;
		int 	ncolumn;
		DB_ROW	values;
	} ZBX_OCI_DB_RESULT;

	void	OCI_DBfree_result(DB_RESULT result);
	ub4	OCI_DBserver_status();
	char*	zbx_oci_error(sword status);
#endif

int	zbx_db_connect(char *host, char *user, char *password, char *dbname, char *dbsocket, int port);
void	zbx_db_init(char *host, char *user, char *password, char *dbname, char *dbsocket, int port);

void    zbx_db_close(void);
void    zbx_db_vacuum(void);

int	zbx_db_vexecute(const char *fmt, va_list args);

#ifdef HAVE___VA_ARGS__
#	define zbx_db_execute(fmt, ...)	__zbx_zbx_db_execute(ZBX_CONST_STRING(fmt), ##__VA_ARGS__)
#else
#	define zbx_db_execute __zbx_zbx_db_execute
#endif /* HAVE___VA_ARGS__ */
int	__zbx_zbx_db_execute(const char *fmt, ...);

DB_RESULT	zbx_db_vselect(const char *fmt, va_list args);
DB_RESULT	zbx_db_select_n(char *query, int n);
DB_ROW		zbx_db_fetch(DB_RESULT result);
int		zbx_db_is_null(char *field);
void		zbx_db_begin();
void		zbx_db_commit();
void		zbx_db_rollback();

#endif
