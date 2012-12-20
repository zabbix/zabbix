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

#include "common.h"

#include "db.h"
#include "zbxdb.h"
#include "log.h"
#include "mutexs.h"

static int	txn_level = 0;	/* transaction level, nested transactions are not supported */
static int	txn_error = 0;	/* failed transaction */
static int	txn_init = 0;	/* connecting to db */

#if defined(HAVE_IBM_DB2)
static zbx_ibm_db2_handle_t	ibm_db2;
#elif defined(HAVE_MYSQL)
static MYSQL			*conn = NULL;
#elif defined(HAVE_ORACLE)
static zbx_oracle_db_handle_t	oracle;
#elif defined(HAVE_POSTGRESQL)
static PGconn			*conn = NULL;
static int			ZBX_PG_BYTEAOID = 0;
static int			ZBX_PG_SVERSION = 0;
char				ZBX_PG_ESCAPE_BACKSLASH = 1;
#elif defined(HAVE_SQLITE3)
static sqlite3			*conn = NULL;
static PHP_MUTEX		sqlite_access;
#endif

#if defined(HAVE_ORACLE)
static const char	*zbx_oci_error(sword status)
{
	static char	errbuf[512];
	sb4		errcode = 0;

	errbuf[0] = '\0';

	switch (status)
	{
		case OCI_SUCCESS_WITH_INFO:
			OCIErrorGet((dvoid *)oracle.errhp, (ub4)1, (text *)NULL, &errcode,
					(text *)errbuf, (ub4)sizeof(errbuf), OCI_HTYPE_ERROR);
			break;
		case OCI_NEED_DATA:
			zbx_snprintf(errbuf, sizeof(errbuf), "%s", "OCI_NEED_DATA");
			break;
		case OCI_NO_DATA:
			zbx_snprintf(errbuf, sizeof(errbuf), "%s", "OCI_NODATA");
			break;
		case OCI_ERROR:
			OCIErrorGet((dvoid *)oracle.errhp, (ub4)1, (text *)NULL, &errcode,
					(text *)errbuf, (ub4)sizeof(errbuf), OCI_HTYPE_ERROR);
			break;
		case OCI_INVALID_HANDLE:
			zbx_snprintf(errbuf, sizeof(errbuf), "%s", "OCI_INVALID_HANDLE");
			break;
		case OCI_STILL_EXECUTING:
			zbx_snprintf(errbuf, sizeof(errbuf), "%s", "OCI_STILL_EXECUTING");
			break;
		case OCI_CONTINUE:
			zbx_snprintf(errbuf, sizeof(errbuf), "%s", "OCI_CONTINUE");
			break;
	}

	zbx_rtrim(errbuf, ZBX_WHITESPACE);

	return errbuf;
}
#endif	/* HAVE_ORACLE */

/******************************************************************************
 *                                                                            *
 * Function: zbx_db_connect                                                   *
 *                                                                            *
 * Purpose: connect to the database                                           *
 *                                                                            *
 * Return value: ZBX_DB_OK - succefully connected                             *
 *               ZBX_DB_DOWN - database is down                               *
 *               ZBX_DB_FAIL - failed to connect                              *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_connect(char *host, char *user, char *password, char *dbname, char *dbschema, char *dbsocket, int port)
{
	int		ret = ZBX_DB_OK;
#if defined(HAVE_IBM_DB2)
	char		*connect = NULL;
#elif defined(HAVE_ORACLE)
	char		*connect = NULL;
	sword		err = OCI_SUCCESS;
#elif defined(HAVE_POSTGRESQL)
	char		*cport = NULL;
	DB_RESULT	result;
	DB_ROW		row;
#endif

	txn_init = 1;

	assert(NULL != host);

#if defined(HAVE_IBM_DB2)
	connect = zbx_strdup(connect, "PROTOCOL=TCPIP;");
	if ('\0' != *host)
		connect = zbx_strdcatf(connect, "HOSTNAME=%s;", host);
	if (NULL != dbname && '\0' != *dbname)
		connect = zbx_strdcatf(connect, "DATABASE=%s;", dbname);
	if (0 != port)
		connect = zbx_strdcatf(connect, "PORT=%d;", port);
	if (NULL != user && '\0' != *user)
		connect = zbx_strdcatf(connect, "UID=%s;", user);
	if (NULL != password && '\0' != *password)
		connect = zbx_strdcatf(connect, "PWD=%s;", password);

	memset(&ibm_db2, 0, sizeof(ibm_db2));

	/* allocate an environment handle */
	if (SUCCEED != zbx_ibm_db2_success(SQLAllocHandle(SQL_HANDLE_ENV, SQL_NULL_HANDLE, &ibm_db2.henv)))
		ret = ZBX_DB_FAIL;

	/* set attribute to enable application to run as ODBC 3.0 application; */
	/* recommended for pure IBM DB2 CLI, but not required */
	if (ZBX_DB_OK == ret && SUCCEED != zbx_ibm_db2_success(SQLSetEnvAttr(ibm_db2.henv, SQL_ATTR_ODBC_VERSION,
			(void *)SQL_OV_ODBC3, 0)))
		ret = ZBX_DB_FAIL;

	/* allocate a database connection handle */
	if (ZBX_DB_OK == ret && SUCCEED != zbx_ibm_db2_success(SQLAllocHandle(SQL_HANDLE_DBC, ibm_db2.henv,
			&ibm_db2.hdbc)))
		ret = ZBX_DB_FAIL;

	/* connect to the database */
	if (ZBX_DB_OK == ret && SUCCEED != zbx_ibm_db2_success(SQLDriverConnect(ibm_db2.hdbc, NULL, (SQLCHAR *)connect,
			SQL_NTS, NULL, 0, NULL, SQL_DRIVER_NOPROMPT)))
		ret = ZBX_DB_FAIL;

	/* set autocommit on */
  	if (ZBX_DB_OK == ret && SUCCEED != zbx_ibm_db2_success(SQLSetConnectAttr(ibm_db2.hdbc, SQL_ATTR_AUTOCOMMIT,
								(SQLPOINTER)SQL_AUTOCOMMIT_ON, SQL_NTS)))
		ret = ZBX_DB_DOWN;

	/* we do not generate vendor escape clause sequences */
  	if (ZBX_DB_OK == ret && SUCCEED != zbx_ibm_db2_success(SQLSetConnectAttr(ibm_db2.hdbc, SQL_ATTR_NOSCAN,
								(SQLPOINTER)SQL_NOSCAN_ON, SQL_NTS)))
		ret = ZBX_DB_DOWN;

	/* set current schema */
	if (NULL != dbschema && '\0' != *dbschema && ZBX_DB_OK == ret)
	{
		char	*dbschema_esc;

		dbschema_esc = DBdyn_escape_string(dbschema);
		DBexecute("set current schema='%s'", dbschema_esc);
		zbx_free(dbschema_esc);
	}

	/* output error information */
	if (ZBX_DB_OK != ret)
	{
		zbx_ibm_db2_log_errors(SQL_HANDLE_ENV, ibm_db2.henv);
		zbx_ibm_db2_log_errors(SQL_HANDLE_DBC, ibm_db2.hdbc);

		zbx_db_close();
	}

	zbx_free(connect);
#elif defined(HAVE_MYSQL)
	conn = mysql_init(NULL);

	if (!mysql_real_connect(conn, host, user, password, dbname, port, dbsocket, CLIENT_MULTI_STATEMENTS))
	{
		zabbix_errlog(ERR_Z3001, dbname, mysql_errno(conn), mysql_error(conn));
		ret = ZBX_DB_FAIL;
	}

	if (ZBX_DB_OK == ret)
	{
		if (0 != mysql_select_db(conn, dbname))
		{
			zabbix_errlog(ERR_Z3001, dbname, mysql_errno(conn), mysql_error(conn));
			ret = ZBX_DB_FAIL;
		}
	}

	if (ZBX_DB_OK == ret)
	{
		DBexecute("set names utf8");
	}

	if (ZBX_DB_FAIL == ret)
	{
		switch (mysql_errno(conn))
		{
			case CR_CONN_HOST_ERROR:
			case CR_SERVER_GONE_ERROR:
			case CR_CONNECTION_ERROR:
			case CR_SERVER_LOST:
			case CR_UNKNOWN_HOST:
			case ER_SERVER_SHUTDOWN:
			case ER_ACCESS_DENIED_ERROR:		/* wrong user or password */
			case ER_ILLEGAL_GRANT_FOR_TABLE:	/* user without any privileges */
			case ER_TABLEACCESS_DENIED_ERROR:	/* user without some privilege */
			case ER_UNKNOWN_ERROR:
				ret = ZBX_DB_DOWN;
				break;
			default:
				break;
		}
	}
#elif defined(HAVE_ORACLE)
#if defined(HAVE_GETENV) && defined(HAVE_PUTENV)
	if (NULL == getenv("NLS_LANG"))
		putenv("NLS_LANG=.UTF8");
#endif
	memset(&oracle, 0, sizeof(oracle));

	/* connection string format: [//]host[:port][/service name] */

	if ('\0' != *host)
	{
		connect = zbx_strdcatf(connect, "//%s", host);
		if (0 != port)
			connect = zbx_strdcatf(connect, ":%d", port);
		if (NULL != dbname && '\0' != *dbname)
			connect = zbx_strdcatf(connect, "/%s", dbname);
	}
	else
		ret = ZBX_DB_FAIL;

	if (ZBX_DB_OK == ret)
	{
		/* initialize environment */
		err = OCIEnvCreate((OCIEnv **)&oracle.envhp, (ub4)OCI_DEFAULT,
				(dvoid *)0, (dvoid * (*)(dvoid *,size_t))0,
				(dvoid * (*)(dvoid *, dvoid *, size_t))0,
				(void (*)(dvoid *, dvoid *))0, (size_t)0, (dvoid **)0);

		if (OCI_SUCCESS != err)
		{
			zabbix_errlog(ERR_Z3001, connect, err, zbx_oci_error(err));
			ret = ZBX_DB_FAIL;
		}
	}

	if (ZBX_DB_OK == ret)
	{
		/* allocate an error handle */
		(void)OCIHandleAlloc((dvoid *)oracle.envhp, (dvoid **)&oracle.errhp, OCI_HTYPE_ERROR,
				(size_t)0, (dvoid **)0);

		/* get the session */
		err = OCILogon2(oracle.envhp, oracle.errhp, &oracle.svchp,
				(text *)user, (ub4)(NULL != user ? strlen(user) : 0),
				(text *)password, (ub4)(NULL != password ? strlen(password) : 0),
				(text *)connect, (ub4)strlen(connect),
				OCI_DEFAULT);

		if (OCI_SUCCESS == err)
		{
			err = OCIAttrGet((void *)oracle.svchp, OCI_HTYPE_SVCCTX, (void *)&oracle.srvhp, (ub4 *)0,
					OCI_ATTR_SERVER, oracle.errhp);
		}

		if (OCI_SUCCESS != err)
		{
			zabbix_errlog(ERR_Z3001, connect, err, zbx_oci_error(err));
			ret = ZBX_DB_DOWN;
		}
	}

	if (ZBX_DB_OK == ret)
	{
		/* initialize statement handle */
		err = OCIHandleAlloc((dvoid *)oracle.envhp, (dvoid **)&oracle.stmthp, OCI_HTYPE_STMT,
				(size_t)0, (dvoid **)0);

		if (OCI_SUCCESS != err)
		{
			zabbix_errlog(ERR_Z3001, connect, err, zbx_oci_error(err));
			ret = ZBX_DB_DOWN;
		}
	}

	zbx_free(connect);

	if (ZBX_DB_OK != ret)
		zbx_db_close();
#elif defined(HAVE_POSTGRESQL)
	if (0 != port)
		cport = zbx_dsprintf(cport, "%d", port);

	conn = PQsetdbLogin(host, cport, NULL, NULL, dbname, user, password);

	zbx_free(cport);

	/* check to see that the backend connection was successfully made */
	if (CONNECTION_OK != PQstatus(conn))
	{
		zabbix_errlog(ERR_Z3001, dbname, 0, PQerrorMessage(conn));
		ret = ZBX_DB_DOWN;
	}
	else
	{
		result = DBselect("select oid from pg_type where typname='bytea'");
		if (NULL != (row = DBfetch(result)))
			ZBX_PG_BYTEAOID = atoi(row[0]);
		DBfree_result(result);
	}

#ifdef HAVE_FUNCTION_PQSERVERVERSION
	ZBX_PG_SVERSION = PQserverVersion(conn);
	zabbix_log(LOG_LEVEL_DEBUG, "PostgreSQL Server version: %d", ZBX_PG_SVERSION);
#endif

	if (80100 <= ZBX_PG_SVERSION)
	{
		/* disable "nonstandard use of \' in a string literal" warning */
		DBexecute("set escape_string_warning to off");

		result = DBselect("show standard_conforming_strings");
		if (NULL != (row = DBfetch(result)))
			ZBX_PG_ESCAPE_BACKSLASH = (0 == strcmp(row[0], "off"));
		DBfree_result(result);
	}

	if (90000 <= ZBX_PG_SVERSION)
	{
		/* change the output format for values of type bytea from hex (the default) to escape */
		DBexecute("set bytea_output=escape");
	}
#elif defined(HAVE_SQLITE3)
#ifdef HAVE_FUNCTION_SQLITE3_OPEN_V2
	if (SQLITE_OK != sqlite3_open_v2(dbname, &conn, SQLITE_OPEN_READWRITE, NULL))
#else
	if (SQLITE_OK != sqlite3_open(dbname, &conn))
#endif
	{
		zabbix_errlog(ERR_Z3001, dbname, 0, sqlite3_errmsg(conn));
		sqlite3_close(conn);
		ret = ZBX_DB_DOWN;
	}
	else
	{
		char	*p, *path;

		/* do not return SQLITE_BUSY immediately, wait for N ms */
		sqlite3_busy_timeout(conn, SEC_PER_MIN * 1000);

		path = strdup(dbname);
		if (NULL != (p = strrchr(path, '/')))
			*++p = '\0';
		else
			*path = '\0';

		DBexecute("PRAGMA synchronous = 0");	/* OFF */
		DBexecute("PRAGMA temp_store = 2");	/* MEMORY */
		DBexecute("PRAGMA temp_store_directory = '%s'", path);

		zbx_free(path);
	}
#endif	/* HAVE_SQLITE3 */

	txn_init = 0;

	return ret;
}

#if defined(HAVE_SQLITE3)
void	zbx_create_sqlite3_mutex(const char *dbname)
{
	if (ZBX_MUTEX_ERROR == php_sem_get(&sqlite_access, dbname))
	{
		zbx_error("cannot create mutex for SQLite3");
		exit(FAIL);
	}
}

void	zbx_remove_sqlite3_mutex()
{
	php_sem_remove(&sqlite_access);
}
#endif	/* HAVE_SQLITE3 */

void	zbx_db_init(char *dbname)
{
#if defined(HAVE_SQLITE3)
	struct stat	buf;

	if (0 != stat(dbname, &buf))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot open database file \"%s\": %s", dbname, zbx_strerror(errno));
		zabbix_log(LOG_LEVEL_WARNING, "creating database ...");

		if (SQLITE_OK != sqlite3_open(dbname, &conn))
		{
			zabbix_errlog(ERR_Z3002, dbname, 0, sqlite3_errmsg(conn));
			exit(FAIL);
		}

		zbx_create_sqlite3_mutex(dbname);

		DBexecute("%s", db_schema);
		DBclose();
	}
	else
		zbx_create_sqlite3_mutex(dbname);

#endif	/* HAVE_SQLITE3 */
}

void	zbx_db_close()
{
#if defined(HAVE_IBM_DB2)
	if (ibm_db2.hdbc)
	{
		SQLDisconnect(ibm_db2.hdbc);
		SQLFreeHandle(SQL_HANDLE_DBC, ibm_db2.hdbc);
	}

	if (ibm_db2.henv)
		SQLFreeHandle(SQL_HANDLE_ENV, ibm_db2.henv);

	memset(&ibm_db2, 0, sizeof(ibm_db2));
#elif defined(HAVE_MYSQL)
	mysql_close(conn);
	conn = NULL;
#elif defined(HAVE_ORACLE)
	/* deallocate statement handle */
	if (NULL != oracle.stmthp)
	{
		OCIHandleFree((dvoid *)oracle.stmthp, OCI_HTYPE_STMT);
		oracle.stmthp = NULL;
	}

	if (NULL != oracle.svchp)
	{
		OCILogoff(oracle.svchp, oracle.errhp);
		oracle.svchp = NULL;
	}

	if (NULL != oracle.errhp)
	{
		OCIHandleFree(oracle.errhp, OCI_HTYPE_ERROR);
		oracle.errhp = NULL;
	}

	if (NULL != oracle.envhp)
	{
		OCIHandleFree((dvoid *)oracle.envhp, OCI_HTYPE_ENV);
		oracle.envhp = NULL;
	}

	if (NULL != oracle.srvhp)
	{
		OCIHandleFree(oracle.srvhp, OCI_HTYPE_SERVER);
		oracle.srvhp = NULL;
	}
#elif defined(HAVE_POSTGRESQL)
	PQfinish(conn);
	conn = NULL;
#elif defined(HAVE_SQLITE3)
	sqlite3_close(conn);
	conn = NULL;
#endif
}

#if defined(HAVE_MYSQL) || defined(HAVE_POSTGRESQL) || defined(HAVE_SQLITE3)
#ifdef HAVE___VA_ARGS__
#	define zbx_db_execute(fmt, ...)	__zbx_zbx_db_execute(ZBX_CONST_STRING(fmt), ##__VA_ARGS__)
#else
#	define zbx_db_execute		__zbx_zbx_db_execute
#endif
static int	__zbx_zbx_db_execute(const char *fmt, ...)
{
	va_list	args;
	int	ret;

	va_start(args, fmt);
	ret = zbx_db_vexecute(fmt, args);
	va_end(args);

	return ret;
}
#endif /* HAVE_MYSQL || HAVE_POSTGRESQL || HAVE_SQLITE3 */

#ifdef HAVE___VA_ARGS__
#	define zbx_db_select(fmt, ...)	__zbx_zbx_db_select(ZBX_CONST_STRING(fmt), ##__VA_ARGS__)
#else
#	define zbx_db_select		__zbx_zbx_db_select
#endif
static DB_RESULT	__zbx_zbx_db_select(const char *fmt, ...)
{
	va_list		args;
	DB_RESULT	result;

	va_start(args, fmt);
	result = zbx_db_vselect(fmt, args);
	va_end(args);

	return result;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_db_begin                                                     *
 *                                                                            *
 * Purpose: start transaction                                                 *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: do nothing if DB does not support transactions                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_begin()
{
	int	rc = ZBX_DB_OK;

	if (txn_level > 0)
	{
		zabbix_log(LOG_LEVEL_CRIT, "ERROR: nested transaction detected. Please report it to Zabbix Team.");
		assert(0);
	}

	txn_level++;

#if defined(HAVE_IBM_DB2)
	if (SUCCEED != zbx_ibm_db2_success(SQLSetConnectAttr(ibm_db2.hdbc, SQL_ATTR_AUTOCOMMIT, (SQLPOINTER)SQL_AUTOCOMMIT_OFF, SQL_NTS)))
		rc = ZBX_DB_DOWN;

	if (ZBX_DB_OK != rc)
	{
		zbx_ibm_db2_log_errors(SQL_HANDLE_DBC, ibm_db2.hdbc);
		rc = (SQL_CD_TRUE == IBM_DB2server_status() ? ZBX_DB_FAIL : ZBX_DB_DOWN);
	}
#elif defined(HAVE_MYSQL) || defined(HAVE_POSTGRESQL)
	rc = zbx_db_execute("%s", "begin;");
#elif defined(HAVE_SQLITE3)
	if (PHP_MUTEX_OK != php_sem_acquire(&sqlite_access))
	{
		zabbix_log(LOG_LEVEL_CRIT, "ERROR: cannot create lock on SQLite3 database");
		assert(0);
	}
	rc = zbx_db_execute("%s", "begin;");
#endif

	if (ZBX_DB_DOWN == rc)
		txn_level--;

	return rc;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_db_commit                                                    *
 *                                                                            *
 * Purpose: commit transaction                                                *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: do nothing if DB does not support transactions                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_commit()
{
	int	rc = ZBX_DB_OK;

	if (0 == txn_level)
	{
		zabbix_log(LOG_LEVEL_CRIT, "ERROR: commit without transaction."
				" Please report it to Zabbix Team.");
		assert(0);
	}

	if (1 == txn_error)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "commit called on failed transaction, doing a rollback instead");
		return zbx_db_rollback();
	}

#if defined(HAVE_IBM_DB2)
	if (SUCCEED != zbx_ibm_db2_success(SQLEndTran(SQL_HANDLE_DBC, ibm_db2.hdbc, SQL_COMMIT)))
		rc = ZBX_DB_DOWN;
	if (SUCCEED != zbx_ibm_db2_success(SQLSetConnectAttr(ibm_db2.hdbc, SQL_ATTR_AUTOCOMMIT, (SQLPOINTER)SQL_AUTOCOMMIT_ON, SQL_NTS)))
		rc = ZBX_DB_DOWN;

	if (ZBX_DB_OK != rc)
	{
		zbx_ibm_db2_log_errors(SQL_HANDLE_DBC, ibm_db2.hdbc);
		rc = (SQL_CD_TRUE == IBM_DB2server_status() ? ZBX_DB_FAIL : ZBX_DB_DOWN);
	}
#elif defined(HAVE_MYSQL) || defined(HAVE_POSTGRESQL)
	rc = zbx_db_execute("%s", "commit;");
#elif defined(HAVE_ORACLE)
	OCITransCommit(oracle.svchp, oracle.errhp, OCI_DEFAULT);
#elif defined(HAVE_SQLITE3)
	rc = zbx_db_execute("%s", "commit;");
	php_sem_release(&sqlite_access);
#endif

	if (ZBX_DB_DOWN != rc)	/* ZBX_DB_FAIL or ZBX_DB_OK or number of changes */
		txn_level--;

	return rc;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_db_rollback                                                  *
 *                                                                            *
 * Purpose: rollback transaction                                              *
 *                                                                            *
 * Author: Eugene Grigorjev                                                   *
 *                                                                            *
 * Comments: do nothing if DB does not support transactions                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_rollback()
{
	int	rc = ZBX_DB_OK, last_txn_error;

	if (0 == txn_level)
	{
		zabbix_log(LOG_LEVEL_CRIT, "ERROR: rollback without transaction."
				" Please report it to Zabbix Team.");
		assert(0);
	}

	last_txn_error = txn_error;

	/* allow rollback of failed transaction */
	txn_error = 0;

#if defined(HAVE_IBM_DB2)
	if (SUCCEED != zbx_ibm_db2_success(SQLEndTran(SQL_HANDLE_DBC, ibm_db2.hdbc, SQL_ROLLBACK)))
		rc = ZBX_DB_DOWN;
	if (SUCCEED != zbx_ibm_db2_success(SQLSetConnectAttr(ibm_db2.hdbc, SQL_ATTR_AUTOCOMMIT, (SQLPOINTER)SQL_AUTOCOMMIT_ON, SQL_NTS)))
		rc = ZBX_DB_DOWN;

	if (ZBX_DB_OK != rc)
	{
		zbx_ibm_db2_log_errors(SQL_HANDLE_DBC, ibm_db2.hdbc);
		rc = (SQL_CD_TRUE == IBM_DB2server_status() ? ZBX_DB_FAIL : ZBX_DB_DOWN);
	}
#elif defined(HAVE_MYSQL) || defined(HAVE_POSTGRESQL)
	rc = zbx_db_execute("%s", "rollback;");
#elif defined(HAVE_ORACLE)
	OCITransRollback(oracle.svchp, oracle.errhp, OCI_DEFAULT);
#elif defined(HAVE_SQLITE3)
	rc = zbx_db_execute("%s", "rollback;");
	php_sem_release(&sqlite_access);
#endif

	if (ZBX_DB_DOWN != rc)	/* ZBX_DB_FAIL or ZBX_DB_OK or number of changes */
		txn_level--;
	else
		txn_error = last_txn_error;	/* in case of DB down we will repeat this operation */

	return rc;
}

int	zbx_db_txn_level()
{
	return txn_level;
}

int	zbx_db_txn_error()
{
	return txn_error;
}

#ifdef HAVE_ORACLE
static sword	zbx_oracle_statement_prepare(const char *sql)
{
	return OCIStmtPrepare(oracle.stmthp, oracle.errhp, (text *)sql, (ub4)strlen((char *)sql), (ub4)OCI_NTV_SYNTAX,
			(ub4)OCI_DEFAULT);
}

static sword	zbx_oracle_bind_parameter(ub4 position, void *buffer, sb4 buffer_sz, ub2 dty)
{
	OCIBind	*bindhp = NULL;

	return OCIBindByPos(oracle.stmthp, &bindhp, oracle.errhp, position, buffer, buffer_sz, dty,
			0, 0, 0, 0, 0, (ub4)OCI_DEFAULT);
}

static sword	zbx_oracle_statement_execute(ub4 *nrows)
{
	sword	err;

	if (OCI_SUCCESS == (err = OCIStmtExecute(oracle.svchp, oracle.stmthp, oracle.errhp, (ub4)1, (ub4)0,
			(CONST OCISnapshot *)NULL, (OCISnapshot *)NULL,
			0 == txn_level ? OCI_COMMIT_ON_SUCCESS : OCI_DEFAULT)))
	{
		err = OCIAttrGet((void *)oracle.stmthp, OCI_HTYPE_STMT, nrows, (ub4 *)0, OCI_ATTR_ROW_COUNT, oracle.errhp);
	}

	return err;
}
#endif

#ifdef HAVE_ORACLE
int	zbx_db_statement_prepare(const char *sql)
{
	sword	err;
	int	ret = ZBX_DB_OK;

	if (0 == txn_init && 0 == txn_level)
		zabbix_log(LOG_LEVEL_DEBUG, "query without transaction detected");

	if (1 == txn_error)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "ignoring query [txnlev:%d] within failed transaction", txn_level);
		return ZBX_DB_FAIL;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "query [txnlev:%d] [%s]", txn_level, sql);

	/* Oracle */
	if (OCI_SUCCESS != (err = zbx_oracle_statement_prepare(sql)))
	{
		zabbix_errlog(ERR_Z3005, err, zbx_oci_error(err), sql);
		ret = (OCI_SERVER_NORMAL == OCI_DBserver_status() ? ZBX_DB_FAIL : ZBX_DB_DOWN);
	}

	if (ZBX_DB_FAIL == ret && 0 < txn_level)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "query [%s] failed, setting transaction as failed", sql);
		txn_error = 1;
	}

	return ret;
}

int	zbx_db_bind_parameter(int position, void *buffer, unsigned char type)
{
	sword	err;
	int	ret = ZBX_DB_OK;

	if (1 == txn_error)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "ignoring query [txnlev:%d] within failed transaction", txn_level);
		return ZBX_DB_FAIL;
	}

	/* Oracle */
	switch (type)
	{
		case ZBX_TYPE_ID:
			err = zbx_oracle_bind_parameter((ub4)position, buffer, (sb4)sizeof(zbx_uint64_t), SQLT_INT);
			break;
		case ZBX_TYPE_INT:
			err = zbx_oracle_bind_parameter((ub4)position, buffer, (sb4)sizeof(int), SQLT_INT);
			break;
		case ZBX_TYPE_TEXT:
			err = zbx_oracle_bind_parameter((ub4)position, buffer, (sb4)strlen((char *)buffer), SQLT_LNG);
			break;
		default:
			err = OCI_SUCCESS;
	}

	if (OCI_SUCCESS != err)
	{
		zabbix_errlog(ERR_Z3007, err, zbx_oci_error(err));
		ret = (OCI_SERVER_NORMAL == OCI_DBserver_status() ? ZBX_DB_FAIL : ZBX_DB_DOWN);
	}

	if (ZBX_DB_FAIL == ret && 0 < txn_level)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "query failed, setting transaction as failed");
		txn_error = 1;
	}

	return ret;

}

int	zbx_db_statement_execute()
{
	const char	*__function_name = "zbx_db_statement_execute";
	sword	err;
	ub4	nrows;
	int	ret;

	if (1 == txn_error)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "ignoring query [txnlev:%d] within failed transaction", txn_level);
		ret = ZBX_DB_FAIL;
		goto out;
	}

	if (OCI_SUCCESS != (err = zbx_oracle_statement_execute(&nrows)))
	{
		zabbix_errlog(ERR_Z3007, err, zbx_oci_error(err));
		ret = (OCI_SERVER_NORMAL == OCI_DBserver_status() ? ZBX_DB_FAIL : ZBX_DB_DOWN);
	}
	else
		ret = (int)nrows;

	if (ZBX_DB_FAIL == ret && 0 < txn_level)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "query failed, setting transaction as failed");
		txn_error = 1;
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "%s():%d", __function_name, ret);

	return ret;
}
#endif

/*
 * Execute SQL statement. For non-select statements only.
 */
int	zbx_db_vexecute(const char *fmt, va_list args)
{
	char	*sql = NULL;
	int	ret = ZBX_DB_OK;
	double	sec = 0;

#if defined(HAVE_IBM_DB2)
	SQLHANDLE	hstmt = 0;
	SQLRETURN	ret1;
	SQLLEN		row1;
	SQLLEN		rows = 0;
#elif defined(HAVE_MYSQL)
	int		status;
#elif defined(HAVE_ORACLE)
	sword		err = OCI_SUCCESS;
#elif defined(HAVE_POSTGRESQL)
	PGresult	*result;
	char		*error = NULL;
#elif defined(HAVE_SQLITE3)
	int		err;
	char		*error = NULL;
#endif

	if (0 != CONFIG_LOG_SLOW_QUERIES)
		sec = zbx_time();

	sql = zbx_dvsprintf(sql, fmt, args);

	if (0 == txn_init && 0 == txn_level)
		zabbix_log(LOG_LEVEL_DEBUG, "query without transaction detected");

	if (1 == txn_error)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "ignoring query [txnlev:%d] [%s] within failed transaction", txn_level, sql);
		ret = ZBX_DB_FAIL;
		goto clean;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "query [txnlev:%d] [%s]", txn_level, sql);

#if defined(HAVE_IBM_DB2)
	/* allocate a statement handle */
	if (SUCCEED != zbx_ibm_db2_success(SQLAllocHandle(SQL_HANDLE_STMT, ibm_db2.hdbc, &hstmt)))
		ret = ZBX_DB_DOWN;

	/* directly execute the statement; returns SQL_NO_DATA_FOUND when no rows were affected */
  	if (ZBX_DB_OK == ret && SUCCEED != zbx_ibm_db2_success_ext(SQLExecDirect(hstmt, (SQLCHAR *)sql, SQL_NTS)))
		ret = ZBX_DB_DOWN;

	/* get number of affected rows */
	if (ZBX_DB_OK == ret && SUCCEED != zbx_ibm_db2_success(SQLRowCount(hstmt, &rows)))
		ret = ZBX_DB_DOWN;

	/* process other SQL statements in the batch */
	while (ZBX_DB_OK == ret && SUCCEED == zbx_ibm_db2_success(ret1 = SQLMoreResults(hstmt)))
	{
		if (SUCCEED != zbx_ibm_db2_success(SQLRowCount(hstmt, &row1)))
			ret = ZBX_DB_DOWN;
		else
			rows += row1;
	}

	if (ZBX_DB_OK == ret && SQL_NO_DATA_FOUND != ret1)
		ret = ZBX_DB_DOWN;

	if (ZBX_DB_OK != ret)
	{
		zbx_ibm_db2_log_errors(SQL_HANDLE_DBC, ibm_db2.hdbc);
		zbx_ibm_db2_log_errors(SQL_HANDLE_STMT, hstmt);

		ret = (SQL_CD_TRUE == IBM_DB2server_status() ? ZBX_DB_FAIL : ZBX_DB_DOWN);
	}
	else if (0 <= rows)
	{
		ret = (int)rows;
	}

	if (hstmt)
		SQLFreeHandle(SQL_HANDLE_STMT, hstmt);
#elif defined(HAVE_MYSQL)
	if (NULL == conn)
	{
		zabbix_errlog(ERR_Z3003);
		ret = ZBX_DB_FAIL;
	}
	else
	{
		if (0 != (status = mysql_query(conn, sql)))
		{
			zabbix_errlog(ERR_Z3005, mysql_errno(conn), mysql_error(conn), sql);

			switch (mysql_errno(conn))
			{
				case CR_CONN_HOST_ERROR:
				case CR_SERVER_GONE_ERROR:
				case CR_CONNECTION_ERROR:
				case CR_SERVER_LOST:
				case ER_SERVER_SHUTDOWN:
				case ER_ACCESS_DENIED_ERROR: /* wrong user or password */
				case ER_ILLEGAL_GRANT_FOR_TABLE: /* user without any privileges */
				case ER_TABLEACCESS_DENIED_ERROR:/* user without some privilege */
				case ER_UNKNOWN_ERROR:
					ret = ZBX_DB_DOWN;
					break;
				default:
					ret = ZBX_DB_FAIL;
					break;
			}
		}
		else
		{
			do
			{
				if (0 != mysql_field_count(conn))
				{
					zabbix_log(LOG_LEVEL_DEBUG, "cannot retrieve result set");
					break;
				}
				else
					ret += (int)mysql_affected_rows(conn);

				/* more results? -1 = no, >0 = error, 0 = yes (keep looping) */
				if (0 < (status = mysql_next_result(conn)))
					zabbix_errlog(ERR_Z3005, mysql_errno(conn), mysql_error(conn), sql);
			}
			while (0 == status);
		}
	}
#elif defined(HAVE_ORACLE)
	if (OCI_SUCCESS == (err = zbx_oracle_statement_prepare(sql)))
	{
		ub4	nrows = 0;

		if (OCI_SUCCESS == (err = zbx_oracle_statement_execute(&nrows)))
			ret = (int)nrows;
	}

	if (OCI_SUCCESS != err)
	{
		zabbix_errlog(ERR_Z3005, err, zbx_oci_error(err), sql);
		ret = (OCI_SERVER_NORMAL == OCI_DBserver_status() ? ZBX_DB_FAIL : ZBX_DB_DOWN);
	}
#elif defined(HAVE_POSTGRESQL)
	result = PQexec(conn,sql);

	if (NULL == result)
	{
		zabbix_errlog(ERR_Z3005, 0, "result is NULL", sql);
		ret = (CONNECTION_OK == PQstatus(conn) ? ZBX_DB_FAIL : ZBX_DB_DOWN);
	}
	else if (PGRES_COMMAND_OK != PQresultStatus(result))
	{
		error = zbx_dsprintf(error, "%s:%s",
				PQresStatus(PQresultStatus(result)),
				PQresultErrorMessage(result));
		zabbix_errlog(ERR_Z3005, 0, error, sql);
		zbx_free(error);

		ret = (CONNECTION_OK == PQstatus(conn) ? ZBX_DB_FAIL : ZBX_DB_DOWN);
	}

	if (ZBX_DB_OK == ret)
		ret = atoi(PQcmdTuples(result));

	PQclear(result);
#elif defined(HAVE_SQLITE3)
	if (0 == txn_level && PHP_MUTEX_OK != php_sem_acquire(&sqlite_access))
	{
		zabbix_log(LOG_LEVEL_CRIT, "ERROR: cannot create lock on SQLite3 database");
		exit(FAIL);
	}

lbl_exec:
	if (SQLITE_OK != (err = sqlite3_exec(conn, sql, NULL, 0, &error)))
	{
		if (SQLITE_BUSY == err)
			goto lbl_exec;

		zabbix_errlog(ERR_Z3005, 0, error, sql);
		sqlite3_free(error);

		switch (err)
		{
			case SQLITE_ERROR:	/* SQL error or missing database; assuming SQL error, because if we
						   are this far into execution, zbx_db_connect() was successful */
			case SQLITE_NOMEM:	/* A malloc() failed */
			case SQLITE_TOOBIG:	/* String or BLOB exceeds size limit */
			case SQLITE_CONSTRAINT:	/* Abort due to constraint violation */
			case SQLITE_MISMATCH:	/* Data type mismatch */
				ret = ZBX_DB_FAIL;
				break;
			default:
				ret = ZBX_DB_DOWN;
				break;
		}
	}

	if (ZBX_DB_OK == ret)
		ret = sqlite3_changes(conn);

	if (0 == txn_level)
		php_sem_release(&sqlite_access);
#endif	/* HAVE_SQLITE3 */

	if (0 != CONFIG_LOG_SLOW_QUERIES)
	{
		sec = zbx_time() - sec;
		if (sec > (double)CONFIG_LOG_SLOW_QUERIES / 1000.0)
			zabbix_log(LOG_LEVEL_WARNING, "slow query: " ZBX_FS_DBL " sec, \"%s\"", sec, sql);
	}

	if (ZBX_DB_FAIL == ret && 0 < txn_level)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "query [%s] failed, setting transaction as failed", sql);
		txn_error = 1;
	}
clean:
	zbx_free(sql);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_db_vselect                                                   *
 *                                                                            *
 * Purpose: execute a select statement                                        *
 *                                                                            *
 * Return value: data, NULL (on error) or (DB_RESULT)ZBX_DB_DOWN              *
 *                                                                            *
 ******************************************************************************/
DB_RESULT	zbx_db_vselect(const char *fmt, va_list args)
{
	char		*sql = NULL;
	DB_RESULT	result = NULL;
	double		sec = 0;

#if defined(HAVE_IBM_DB2)
	int		i;
	SQLRETURN	ret = SQL_SUCCESS;
#elif defined(HAVE_ORACLE)
	sword		err = OCI_SUCCESS;
	ub4		counter, prefetch_mem_size = 2 * ZBX_MEBIBYTE;	/* prefetch 2 MB of data */
	ub4		attrvalue_zero = 0;
#elif defined(HAVE_POSTGRESQL)
	char		*error = NULL;
#elif defined(HAVE_SQLITE3)
	int		ret = FAIL;
	char		*error = NULL;
#endif

	if (0 != CONFIG_LOG_SLOW_QUERIES)
		sec = zbx_time();

	sql = zbx_dvsprintf(sql, fmt, args);

	if (1 == txn_error)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "ignoring query [txnlev:%d] [%s] within failed transaction", txn_level, sql);
		goto clean;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "query [txnlev:%d] [%s]", txn_level, sql);

#if defined(HAVE_IBM_DB2)
	result = zbx_malloc(result, sizeof(ZBX_IBM_DB2_RESULT));
	memset(result, 0, sizeof(ZBX_IBM_DB2_RESULT));

	/* allocate a statement handle */
	if (SUCCEED != zbx_ibm_db2_success(ret = SQLAllocHandle(SQL_HANDLE_STMT, ibm_db2.hdbc, &result->hstmt)))
		goto error;

	/* directly execute the statement */
	if (SUCCEED != zbx_ibm_db2_success(ret = SQLExecDirect(result->hstmt, (SQLCHAR *)sql, SQL_NTS)))
		goto error;

	/* identify the number of output columns */
	if (SUCCEED != zbx_ibm_db2_success(ret = SQLNumResultCols(result->hstmt, &result->ncolumn)))
		goto error;

	if (0 == result->ncolumn)
		goto error;

	result->nalloc = 0;
	result->values = zbx_malloc(result->values, sizeof(char *) * result->ncolumn);
	result->values_cli = zbx_malloc(result->values_cli, sizeof(char *) * result->ncolumn);
	result->values_len = zbx_malloc(result->values_len, sizeof(SQLINTEGER) * result->ncolumn);

	for (i = 0; i < result->ncolumn; i++)
	{
		/* get the display size for a column */
		if (SUCCEED != zbx_ibm_db2_success(ret = SQLColAttribute(result->hstmt, (SQLSMALLINT)(i + 1),
				SQL_DESC_DISPLAY_SIZE, NULL, 0, NULL, &result->values_len[i])))
		{
			goto error;
		}

		result->values_len[i] += 1; /* '\0'; */

		/* allocate memory to bind a column */
		result->values_cli[i] = zbx_malloc(NULL, result->values_len[i]);
		result->nalloc++;

		/* bind columns to program variables, converting all types to CHAR */
		if (SUCCEED != zbx_ibm_db2_success(ret = SQLBindCol(result->hstmt, (SQLSMALLINT)(i + 1),
				SQL_C_CHAR, result->values_cli[i], result->values_len[i], &result->values_len[i])))
		{
			goto error;
		}
	}
error:
	if (SUCCEED != zbx_ibm_db2_success(ret) || 0 == result->ncolumn)
	{
		zbx_ibm_db2_log_errors(SQL_HANDLE_DBC, ibm_db2.hdbc);
		zbx_ibm_db2_log_errors(SQL_HANDLE_STMT, result->hstmt);

		IBM_DB2free_result(result);

		result = (SQL_CD_TRUE == IBM_DB2server_status() ? NULL : (DB_RESULT)ZBX_DB_DOWN);
	}
#elif defined(HAVE_MYSQL)
	if (NULL == conn)
	{
		zabbix_errlog(ERR_Z3003);
		result = NULL;
	}
	else
	{
		if (0 != mysql_query(conn, sql))
		{
			zabbix_errlog(ERR_Z3005, mysql_errno(conn), mysql_error(conn), sql);
			switch (mysql_errno(conn))
			{
				case CR_CONN_HOST_ERROR:
				case CR_SERVER_GONE_ERROR:
				case CR_CONNECTION_ERROR:
				case CR_SERVER_LOST:
				case ER_SERVER_SHUTDOWN:
				case ER_ACCESS_DENIED_ERROR: /* wrong user or password */
				case ER_ILLEGAL_GRANT_FOR_TABLE: /* user without any privileges */
				case ER_TABLEACCESS_DENIED_ERROR:/* user without some privilege */
				case ER_UNKNOWN_ERROR:
					result = (DB_RESULT)ZBX_DB_DOWN;
					break;
				default:
					result = NULL;
					break;
			}
		}
		else
			result = mysql_store_result(conn);
	}
#elif defined(HAVE_ORACLE)
	result = zbx_malloc(NULL, sizeof(ZBX_OCI_DB_RESULT));
	memset(result, 0, sizeof(ZBX_OCI_DB_RESULT));

	err = OCIHandleAlloc((dvoid *)oracle.envhp, (dvoid **)&result->stmthp, OCI_HTYPE_STMT, (size_t)0, (dvoid **)0);

	if (OCI_SUCCESS == err)
	{
		/* Set prefetch memory size. */
		/* By default prefetching is done by 1 row per iteration. */
		/* More info: docs.oracle.com/cd/B28359_01/appdev.111/b28395/oci04sql.htm */
		err = OCIAttrSet(result->stmthp, OCI_HTYPE_STMT, &prefetch_mem_size, sizeof(prefetch_mem_size),
				OCI_ATTR_PREFETCH_MEMORY, oracle.errhp);
	}

	if (OCI_SUCCESS == err)
	{
		/* unset prefetch rows so that memory size takes effect */
		err = OCIAttrSet(result->stmthp, OCI_HTYPE_STMT, &attrvalue_zero, sizeof(attrvalue_zero),
				OCI_ATTR_PREFETCH_ROWS, oracle.errhp);
	}

	if (OCI_SUCCESS == err)
	{
		err = OCIStmtPrepare(result->stmthp, oracle.errhp, (text *)sql, (ub4)strlen((char *)sql),
				(ub4)OCI_NTV_SYNTAX, (ub4)OCI_DEFAULT);
	}

	if (OCI_SUCCESS == err)
	{
		err = OCIStmtExecute(oracle.svchp, result->stmthp, oracle.errhp, (ub4)0, (ub4)0,
				(CONST OCISnapshot *)NULL, (OCISnapshot *)NULL,
				0 == txn_level ? OCI_COMMIT_ON_SUCCESS : OCI_DEFAULT);
	}

	if (OCI_SUCCESS == err)
	{
		/* get the number of columns in the query */
		err = OCIAttrGet((void *)result->stmthp, OCI_HTYPE_STMT, (void *)&result->ncolumn,
				  (ub4 *)0, OCI_ATTR_PARAM_COUNT, oracle.errhp);
	}

	if (OCI_SUCCESS != err)
		goto error;

	assert(0 < result->ncolumn);

	result->values = zbx_malloc(NULL, result->ncolumn * sizeof(char *));
	result->clobs = zbx_malloc(NULL, result->ncolumn * sizeof(OCILobLocator *));
	result->values_alloc = zbx_malloc(NULL, result->ncolumn * sizeof(ub4));
	memset(result->values, 0, result->ncolumn * sizeof(char *));
	memset(result->clobs, 0, result->ncolumn * sizeof(OCILobLocator *));
	memset(result->values_alloc, 0, result->ncolumn * sizeof(ub4));

	for (counter = 1; OCI_SUCCESS == err && counter <= result->ncolumn; counter++)
	{
		OCIParam	*parmdp = NULL;
		OCIDefine	*defnp = NULL;
		ub4		char_semantics;
		ub2		col_width = 0, data_type;

		/* request a parameter descriptor in the select-list */
		err = OCIParamGet((void *)result->stmthp, OCI_HTYPE_STMT, oracle.errhp, (void **)&parmdp, (ub4)counter);

		if (OCI_SUCCESS == err)
		{
			/* retrieve the data type for the column */
			err = OCIAttrGet((void *)parmdp, OCI_DTYPE_PARAM, (dvoid *)&data_type, (ub4 *)NULL,
					(ub4)OCI_ATTR_DATA_TYPE, (OCIError *)oracle.errhp);
		}

		if (SQLT_CLOB == data_type)
		{
			if (ZBX_DB_OK == err)
			{
				/* allocate the lob locator variable */
				err = OCIDescriptorAlloc((dvoid *)oracle.envhp, (dvoid **)&result->clobs[counter - 1],
						OCI_DTYPE_LOB, (size_t)0, (dvoid **)0);
			}

			if (OCI_SUCCESS == err)
			{
				/* associate clob var with its define handle */
				err = OCIDefineByPos((void *)result->stmthp, &defnp, (OCIError *)oracle.errhp,
						(ub4)counter, (dvoid *)&result->clobs[counter - 1], (sb4)-1,
						data_type, (dvoid *)0, (ub2 *)0, (ub2 *)0, (ub4)OCI_DEFAULT);
			}
		}
		else
		{
			if (OCI_SUCCESS == err)
			{
				/* retrieve the length semantics for the column */
				char_semantics = 0;
				err = OCIAttrGet((void *)parmdp, (ub4)OCI_DTYPE_PARAM, (void *)&char_semantics,
						(ub4 *)NULL, (ub4)OCI_ATTR_CHAR_USED, (OCIError *)oracle.errhp);
			}

			if (OCI_SUCCESS == err)
			{
				if (0 != char_semantics)
				{
					/* retrieve the column width in characters */
					err = OCIAttrGet((void *)parmdp, (ub4)OCI_DTYPE_PARAM, (void *)&col_width,
							(ub4 *)NULL, (ub4)OCI_ATTR_CHAR_SIZE, (OCIError *)oracle.errhp);
				}
				else
				{
					/* retrieve the column width in bytes */
					err = OCIAttrGet((void *)parmdp, (ub4)OCI_DTYPE_PARAM, (void *)&col_width,
							(ub4 *)NULL, (ub4)OCI_ATTR_DATA_SIZE, (OCIError *)oracle.errhp);
				}
			}
			col_width++;	/* add 1 byte for terminating '\0' */

			result->values_alloc[counter - 1] = col_width;
			result->values[counter - 1] = zbx_malloc(NULL, col_width);
			*result->values[counter - 1] = '\0';

			if (OCI_SUCCESS == err)
			{
				/* represent any data as characters */
				err = OCIDefineByPos(result->stmthp, &defnp, oracle.errhp, counter,
						(dvoid *)result->values[counter - 1], col_width, SQLT_STR,
						(dvoid *)0, (ub2 *)0, (ub2 *)0, OCI_DEFAULT);
			}
		}

		/* free cell descriptor */
		OCIDescriptorFree(parmdp, OCI_DTYPE_PARAM);
		parmdp = NULL;
	}

error:
	if (OCI_SUCCESS != err)
	{
		zabbix_errlog(ERR_Z3005, err, zbx_oci_error(err), sql);

		OCI_DBfree_result(result);

		result = (OCI_SERVER_NORMAL == OCI_DBserver_status() ? NULL : (DB_RESULT)ZBX_DB_DOWN);
	}
#elif defined(HAVE_POSTGRESQL)
	result = zbx_malloc(NULL, sizeof(ZBX_PG_DB_RESULT));
	result->pg_result = PQexec(conn, sql);
	result->values = NULL;
	result->cursor = 0;
	result->row_num = 0;

	if (NULL == result->pg_result)
		zabbix_errlog(ERR_Z3005, 0, "result is NULL", sql);

	if (PGRES_TUPLES_OK != PQresultStatus(result->pg_result))
	{
		error = zbx_dsprintf(error, "%s:%s",
				PQresStatus(PQresultStatus(result->pg_result)),
				PQresultErrorMessage(result->pg_result));
		zabbix_errlog(ERR_Z3005, 0, error, sql);
		zbx_free(error);

		PG_DBfree_result(result);
		result = (CONNECTION_OK == PQstatus(conn) ? NULL : (DB_RESULT)ZBX_DB_DOWN);
	}
	else	/* init rownum */
		result->row_num = PQntuples(result->pg_result);
#elif defined(HAVE_SQLITE3)
	if (0 == txn_level && PHP_MUTEX_OK != php_sem_acquire(&sqlite_access))
	{
		zabbix_log(LOG_LEVEL_CRIT, "ERROR: cannot create lock on SQLite3 database");
		exit(FAIL);
	}

	result = zbx_malloc(NULL, sizeof(ZBX_SQ_DB_RESULT));
	result->curow = 0;

lbl_get_table:
	if (SQLITE_OK != (ret = sqlite3_get_table(conn,sql, &result->data, &result->nrow, &result->ncolumn, &error)))
	{
		if (SQLITE_BUSY == ret)
			goto lbl_get_table;

		zabbix_errlog(ERR_Z3005, 0, error, sql);
		sqlite3_free(error);

		SQ_DBfree_result(result);

		switch (ret)
		{
			case SQLITE_ERROR:	/* SQL error or missing database; assuming SQL error, because if we
						   are this far into execution, zbx_db_connect() was successful */
			case SQLITE_NOMEM:	/* a malloc() failed */
			case SQLITE_MISMATCH:	/* data type mismatch */
				result = NULL;
				break;
			default:
				result = (DB_RESULT)ZBX_DB_DOWN;
				break;
		}
	}

	if (0 == txn_level)
		php_sem_release(&sqlite_access);
#endif	/* HAVE_SQLITE3 */

	if (0 != CONFIG_LOG_SLOW_QUERIES)
	{
		sec = zbx_time() - sec;
		if (sec > (double)CONFIG_LOG_SLOW_QUERIES / 1000.0)
			zabbix_log(LOG_LEVEL_WARNING, "slow query: " ZBX_FS_DBL " sec, \"%s\"", sec, sql);
	}

	if (NULL == result && 0 < txn_level)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "query [%s] failed, setting transaction as failed", sql);
		txn_error = 1;
	}
clean:
	zbx_free(sql);

	return result;
}

/*
 * Execute SQL statement. For select statements only.
 */
DB_RESULT	zbx_db_select_n(const char *query, int n)
{
#if defined(HAVE_IBM_DB2)
	return zbx_db_select("%s fetch first %d rows only", query, n);
#elif defined(HAVE_MYSQL)
	return zbx_db_select("%s limit %d", query, n);
#elif defined(HAVE_ORACLE)
	return zbx_db_select("select * from (%s) where rownum<=%d", query, n);
#elif defined(HAVE_POSTGRESQL)
	return zbx_db_select("%s limit %d", query, n);
#elif defined(HAVE_SQLITE3)
	return zbx_db_select("%s limit %d", query, n);
#endif
}

DB_ROW	zbx_db_fetch(DB_RESULT result)
{
#if defined(HAVE_IBM_DB2)
	int		i;
#elif defined(HAVE_ORACLE)
	int		i;
	sword		rc;
	static char	errbuf[512];
	sb4		errcode;
#endif

	if (NULL == result)
		return NULL;

#if defined(HAVE_IBM_DB2)
	if (SUCCEED != zbx_ibm_db2_success(SQLFetch(result->hstmt)))	/* e.g., SQL_NO_DATA_FOUND */
		return NULL;

	for (i = 0; i < result->ncolumn; i++)
	{
		result->values[i] = (SQL_NULL_DATA == result->values_len[i] ? NULL : result->values_cli[i]);
	}

	return result->values;
#elif defined(HAVE_MYSQL)
	return mysql_fetch_row(result);
#elif defined(HAVE_ORACLE)
	if (OCI_NO_DATA == (rc = OCIStmtFetch2(result->stmthp, oracle.errhp, 1, OCI_FETCH_NEXT, 0, OCI_DEFAULT)))
		return NULL;

	for (i = 0; i < result->ncolumn; i++)
	{
		if (NULL != result->clobs[i])
		{
			ub4	alloc, amount, amt = 0;
			ub1	csfrm;

			rc = OCILobGetLength(oracle.svchp, oracle.errhp, result->clobs[i], &amount);

			if (OCI_SUCCESS != rc)
				break;

			rc = OCILobCharSetForm(oracle.envhp, oracle.errhp, result->clobs[i], &csfrm);

			if (OCI_SUCCESS != rc)
				break;

			if (result->values_alloc[i] < (alloc = amount * 4 + 1))
			{
				result->values_alloc[i] = alloc;
				result->values[i] = zbx_realloc(result->values[i], result->values_alloc[i]);
			}

			amt = amount;
			rc = OCILobRead(oracle.svchp, oracle.errhp, result->clobs[i], &amt, (ub4)1,
					(dvoid *)result->values[i], (ub4)(result->values_alloc[i] - 1),
					(dvoid *)NULL, (OCICallbackLobRead)NULL, (ub2)0, csfrm);

			if (OCI_SUCCESS != rc)
				zabbix_errlog(ERR_Z3006, rc, zbx_oci_error(rc));

			result->values[i][amt] = '\0';
		}
	}

	if (OCI_SUCCESS == rc)
		return result->values;

	if (OCI_SUCCESS != (rc = OCIErrorGet((dvoid *)oracle.errhp, (ub4)1, (text *)NULL,
			&errcode, (text *)errbuf, (ub4)sizeof(errbuf), OCI_HTYPE_ERROR)))
	{
		zabbix_errlog(ERR_Z3006, rc, zbx_oci_error(rc));
		return NULL;
	}

	switch (errcode)
	{
		case 3113:	/* ORA-03113: end-of-file on communication channel */
		case 3114:	/* ORA-03114: not connected to ORACLE */
			zabbix_errlog(ERR_Z3006, errcode, errbuf);
			return NULL;
	}

	return result->values;
#elif defined(HAVE_POSTGRESQL)
	/* free old data */
	if (NULL != result->values)
		zbx_free(result->values);

	/* EOF */
	if (result->cursor == result->row_num)
		return NULL;

	/* init result */
	result->fld_num = PQnfields(result->pg_result);

	if (result->fld_num > 0)
	{
		int	i;

		result->values = zbx_malloc(result->values, sizeof(char *) * result->fld_num);

		for (i = 0; i < result->fld_num; i++)
		{
			if (PQgetisnull(result->pg_result, result->cursor, i))
			{
				result->values[i] = NULL;
			}
			else
			{
				result->values[i] = PQgetvalue(result->pg_result, result->cursor, i);
				if (PQftype(result->pg_result, i) == ZBX_PG_BYTEAOID)	/* binary data type BYTEAOID */
					zbx_pg_unescape_bytea((u_char *)result->values[i]);
			}
		}
	}

	result->cursor++;

	return result->values;
#elif defined(HAVE_SQLITE3)
	/* EOF */
	if (result->curow >= result->nrow)
		return NULL;

	if (NULL == result->data)
		return NULL;

	result->curow++;	/* NOTE: first row == header row */

	return &(result->data[result->curow * result->ncolumn]);
#endif
}

int	zbx_db_is_null(const char *field)
{
	if (NULL == field)
		return SUCCEED;
#ifdef HAVE_ORACLE
	if ('\0' == *field)
		return SUCCEED;
#endif
	return FAIL;
}

#if defined(HAVE_IBM_DB2)
/* in zbxdb.h - #define DBfree_result   IBM_DB2free_result */
void	IBM_DB2free_result(DB_RESULT result)
{
	if (NULL == result)
		return;

	if (NULL != result->values_cli)
	{
		int	i;

		for (i = 0; i < result->nalloc; i++)
		{
			zbx_free(result->values_cli[i]);
		}

		zbx_free(result->values);
		zbx_free(result->values_cli);
		zbx_free(result->values_len);
	}

	if (result->hstmt)
		SQLFreeHandle(SQL_HANDLE_STMT, result->hstmt);

	zbx_free(result);
}
#elif defined(HAVE_ORACLE)
/* in zbxdb.h - #define DBfree_result   OCI_DBfree_result */
void	OCI_DBfree_result(DB_RESULT result)
{
	if (NULL == result)
		return;

	if (NULL != result->values)
	{
		int	i;

		for (i = 0; i < result->ncolumn; i++)
		{
			zbx_free(result->values[i]);

			/* deallocate the lob locator variable */
			if (NULL != result->clobs[i])
			{
				OCIDescriptorFree((dvoid *)result->clobs[i], OCI_DTYPE_LOB);
				result->clobs[i] = NULL;
			}
		}

		zbx_free(result->values);
		zbx_free(result->clobs);
		zbx_free(result->values_alloc);
	}

	if (result->stmthp)
		OCIHandleFree((dvoid *)result->stmthp, OCI_HTYPE_STMT);

	zbx_free(result);
}
#elif defined(HAVE_POSTGRESQL)
/* in zbxdb.h - #define DBfree_result   PG_DBfree_result */
void	PG_DBfree_result(DB_RESULT result)
{
	if (NULL == result)
		return;

	if (NULL != result->values)
	{
		result->fld_num = 0;
		zbx_free(result->values);
		result->values = NULL;
	}

	PQclear(result->pg_result);
	zbx_free(result);
}
#elif defined(HAVE_SQLITE3)
/* in zbxdb.h - #define DBfree_result   SQ_DBfree_result */
void	SQ_DBfree_result(DB_RESULT result)
{
	if (NULL == result)
		return;

	if (NULL != result->data)
	{
		sqlite3_free_table(result->data);
	}

	zbx_free(result);
}
#endif	/* HAVE_SQLITE3 */

#if defined(HAVE_IBM_DB2)
/* server status: SQL_CD_TRUE or SQL_CD_FALSE */
int	IBM_DB2server_status()
{
	int	server_status = SQL_CD_TRUE;

	if (SUCCEED != zbx_ibm_db2_success(SQLGetConnectAttr(ibm_db2.hdbc, SQL_ATTR_CONNECTION_DEAD,
								&server_status, SQL_IS_POINTER, NULL)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot determine IBM DB2 server status, assuming not connected");
	}

	return (SQL_CD_FALSE == server_status ? SQL_CD_TRUE : SQL_CD_FALSE);
}

int	zbx_ibm_db2_success(SQLRETURN ret)
{
	return (SQL_SUCCESS == ret || SQL_SUCCESS_WITH_INFO == ret ? SUCCEED : FAIL);
}

int	zbx_ibm_db2_success_ext(SQLRETURN ret)
{
	return (SQL_SUCCESS == ret || SQL_SUCCESS_WITH_INFO == ret || SQL_NO_DATA_FOUND == ret ? SUCCEED : FAIL);
}

void	zbx_ibm_db2_log_errors(SQLSMALLINT htype, SQLHANDLE hndl)
{
	SQLCHAR		message[SQL_MAX_MESSAGE_LENGTH + 1];
	SQLCHAR		sqlstate[SQL_SQLSTATE_SIZE + 1];
	SQLINTEGER	sqlcode;
	SQLSMALLINT	length, i = 1;

	while (SQL_SUCCESS == SQLGetDiagRec(htype, hndl, i++, sqlstate, &sqlcode, message, SQL_MAX_MESSAGE_LENGTH + 1, &length))
	{
		zabbix_log(LOG_LEVEL_ERR, "IBM DB2 ERROR: [%d] %s [%s]", (int)sqlcode, sqlstate, message);
	}
}
#elif defined(HAVE_ORACLE)
/* server status: OCI_SERVER_NORMAL or OCI_SERVER_NOT_CONNECTED */
ub4	OCI_DBserver_status()
{
	sword	err;
	ub4	server_status = OCI_SERVER_NOT_CONNECTED;

	err = OCIAttrGet((void *)oracle.srvhp, OCI_HTYPE_SERVER, (void *)&server_status,
			(ub4 *)0, OCI_ATTR_SERVER_STATUS, (OCIError *)oracle.errhp);

	if (OCI_SUCCESS != err)
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot determine Oracle server status, assuming not connected");
	}

	return server_status;
}
#endif	/* HAVE_ORACLE */
