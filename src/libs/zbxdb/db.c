/*
** Copyright (C) 2001-2025 Zabbix SIA
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

#include "zbxdb.h"

#include "zbxstr.h"
#include "zbxtime.h"
#include "zbx_dbversion_constants.h"

#if defined(HAVE_MYSQL)
#	include "mysql.h"
#	include "errmsg.h"
#	include "mysqld_error.h"
#elif defined(HAVE_ORACLE)
#	include "zbxcrypto.h"
#	include "zbxdbschema.h"
#	include "oci.h"
#elif defined(HAVE_POSTGRESQL)
#	include <libpq-fe.h>
#elif defined(HAVE_SQLITE3)
#	include <sqlite3.h>
#endif

#if defined(HAVE_SQLITE3)
#	include "zbxmutexs.h"
#endif
struct zbx_db_result
{
#if defined(HAVE_MYSQL)
	MYSQL_RES	*result;
#elif defined(HAVE_ORACLE)
	OCIStmt		*stmthp;	/* the statement handle for select operations */
	int		ncolumn;
	zbx_db_row_t	values;
	ub4		*values_alloc;
	OCILobLocator	**clobs;
#elif defined(HAVE_POSTGRESQL)
	PGresult	*pg_result;
	int		row_num;
	int		fld_num;
	int		cursor;
	zbx_db_row_t	values;
#elif defined(HAVE_SQLITE3)
	int		curow;
	char		**data;
	int		nrow;
	int		ncolumn;
	zbx_db_row_t	values;
#endif
};

static int		txn_level = 0;	/* transaction level, nested transactions are not supported */
static int		txn_error = ZBX_DB_OK;	/* failed transaction */
static int		txn_end_error = ZBX_DB_OK;	/* transaction result */

static char		*last_db_strerror = NULL;	/* last database error message */

static int		config_log_slow_queries;

static int		db_auto_increment;

#if defined(HAVE_MYSQL)
static MYSQL			*conn = NULL;
static int			mysql_err_cnt = 0;
static zbx_uint32_t		ZBX_MYSQL_SVERSION = ZBX_DBVERSION_UNDEFINED;
static int			ZBX_MARIADB_SFORK = OFF;
static int			txn_begin = 0;	/* transaction begin statement is executed */
#elif defined(HAVE_ORACLE)
#include "zbxalgo.h"

typedef struct
{
	OCIEnv			*envhp;
	OCIError		*errhp;
	OCISvcCtx		*svchp;
	OCIServer		*srvhp;
	OCIStmt			*stmthp;	/* the statement handle for execute operations */
	zbx_vector_ptr_t	db_results;
}
zbx_oracle_db_handle_t;

static zbx_uint32_t		ZBX_ORACLE_SVERSION = ZBX_DBVERSION_UNDEFINED;

static zbx_oracle_db_handle_t	oracle;

static ub4	OCI_DBserver_status(void);

#define ORA_ERR_UNIQ_CONSTRAINT	-1

#elif defined(HAVE_POSTGRESQL)
#define ZBX_PG_READ_ONLY	"25006"
#define ZBX_PG_UNIQUE_VIOLATION	"23505"
#define ZBX_PG_DEADLOCK		"40P01"

static PGconn			*conn = NULL;
static int			ZBX_TSDB_VERSION = -1;
static zbx_uint32_t		ZBX_PG_SVERSION = ZBX_DBVERSION_UNDEFINED;
char				ZBX_PG_ESCAPE_BACKSLASH = 1;
static int 			ZBX_TIMESCALE_COMPRESSION_AVAILABLE = OFF;
#elif defined(HAVE_SQLITE3)
static sqlite3			*conn = NULL;
static zbx_mutex_t		sqlite_access = ZBX_MUTEX_NULL;
#endif

#if defined(HAVE_ORACLE)
static void	OCI_DBclean_result_handle(zbx_db_result_t result);
static void	OCI_DBclean_result(zbx_db_result_t result);
#endif

static zbx_err_codes_t last_db_errcode;

static void	zbx_db_errlog(zbx_err_codes_t zbx_errno, int db_errno, const char *db_error, const char *context)
{
	char	*s;

	last_db_errcode = zbx_errno;

	if (NULL != db_error)
		last_db_strerror = zbx_strdup(last_db_strerror, db_error);
	else
		last_db_strerror = zbx_strdup(last_db_strerror, "");

	switch (zbx_errno)
	{
		case ERR_Z3001:
			s = zbx_dsprintf(NULL, "connection to database '%s' failed: [%d] %s", context, db_errno,
					last_db_strerror);
			break;
		case ERR_Z3002:
			s = zbx_dsprintf(NULL, "cannot create database '%s': [%d] %s", context, db_errno,
					last_db_strerror);
			break;
		case ERR_Z3003:
			s = zbx_strdup(NULL, "no connection to the database");
			break;
		case ERR_Z3004:
			s = zbx_dsprintf(NULL, "cannot close database: [%d] %s", db_errno, last_db_strerror);
			break;
		case ERR_Z3005:
			s = zbx_dsprintf(NULL, "query failed: [%d] %s [%s]", db_errno, last_db_strerror, context);
			break;
		case ERR_Z3006:
			s = zbx_dsprintf(NULL, "fetch failed: [%d] %s", db_errno, last_db_strerror);
			break;
		case ERR_Z3007:
			s = zbx_dsprintf(NULL, "query failed: [%d] %s", db_errno, last_db_strerror);
			break;
		case ERR_Z3008:
			s = zbx_dsprintf(NULL, "query failed due to primary key constraint: [%d] %s", db_errno,
					last_db_strerror);
			break;
		case ERR_Z3009:
			s = zbx_dsprintf(NULL, "query failed due to read-only transaction: [%d] %s", db_errno,
					last_db_strerror);
			break;
		default:
			s = zbx_strdup(NULL, "unknown error");
	}

	zabbix_log(LOG_LEVEL_ERR, "[Z%04d] %s", (int)zbx_errno, s);

	zbx_free(s);
}

/******************************************************************************
 *                                                                            *
 * Purpose: get last error set by database                                    *
 *                                                                            *
 * Return value: last database error message                                  *
 *                                                                            *
 ******************************************************************************/
const char	*zbx_db_last_strerr(void)
{
	return last_db_strerror;
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
	return last_db_errcode;
}

#if defined(HAVE_ORACLE)
static const char	*zbx_oci_error(sword status, sb4 *err)
{
	static char	errbuf[512];
	sb4		errcode, *perrcode;

	perrcode = (NULL == err ? &errcode : err);

	errbuf[0] = '\0';
	*perrcode = 0;

	switch (status)
	{
		case OCI_SUCCESS_WITH_INFO:
			OCIErrorGet((dvoid *)oracle.errhp, (ub4)1, (text *)NULL, perrcode,
					(text *)errbuf, (ub4)sizeof(errbuf), OCI_HTYPE_ERROR);
			break;
		case OCI_NEED_DATA:
			zbx_snprintf(errbuf, sizeof(errbuf), "%s", "OCI_NEED_DATA");
			break;
		case OCI_NO_DATA:
			zbx_snprintf(errbuf, sizeof(errbuf), "%s", "OCI_NODATA");
			break;
		case OCI_ERROR:
			OCIErrorGet((dvoid *)oracle.errhp, (ub4)1, (text *)NULL, perrcode,
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

/******************************************************************************
 *                                                                            *
 * Purpose: handles Oracle prepare/bind/execute/select operation error        *
 *                                                                            *
 * Parameters: zerrcode   - [IN] the Zabbix errorcode for the failed database *
 *                               operation                                    *
 *             oci_error  - [IN] the return code from failed Oracle operation *
 *             sql        - [IN] the failed sql statement (can be NULL)       *
 *                                                                            *
 * Return value: ZBX_DB_DOWN - database connection is down                    *
 *               ZBX_DB_FAIL - otherwise                                      *
 *                                                                            *
 * Comments: This function logs the error description and checks the          *
 *           database connection status.                                      *
 *                                                                            *
 ******************************************************************************/
static int	OCI_handle_sql_error(int zerrcode, sword oci_error, const char *sql)
{
	sb4	errcode;
	int	ret = ZBX_DB_DOWN;

	zbx_db_errlog(zerrcode, oci_error, zbx_oci_error(oci_error, &errcode), sql);

	/* after ORA-02396 (and consequent ORA-01012) errors the OCI_SERVER_NORMAL server status is still returned */
	switch (errcode)
	{
		case 1012:	/* ORA-01012: not logged on */
		case 2396:	/* ORA-02396: exceeded maximum idle time */
			goto out;
	}

	if (OCI_SERVER_NORMAL == OCI_DBserver_status())
		ret = ZBX_DB_FAIL;
out:
	return ret;
}

#endif	/* HAVE_ORACLE */

#ifdef HAVE_POSTGRESQL
static void	zbx_postgresql_error(char **error, const PGresult *pg_result)
{
	char	*result_error_msg;
	size_t	error_alloc = 0, error_offset = 0;

	zbx_snprintf_alloc(error, &error_alloc, &error_offset, "%s", PQresStatus(PQresultStatus(pg_result)));

	result_error_msg = PQresultErrorMessage(pg_result);

	if ('\0' != *result_error_msg)
		zbx_snprintf_alloc(error, &error_alloc, &error_offset, ":%s", result_error_msg);
}
#endif /*HAVE_POSTGRESQL*/

__zbx_attr_format_printf(1, 2)
static int	zbx_db_execute_basic(const char *fmt, ...)
{
	va_list	args;
	int	ret;

	va_start(args, fmt);
	ret = zbx_db_vexecute(fmt, args);
	va_end(args);

	return ret;
}

__zbx_attr_format_printf(1, 2)
static zbx_db_result_t	zbx_db_select_basic(const char *fmt, ...)
{
	va_list		args;
	zbx_db_result_t	result;

	va_start(args, fmt);
	result = zbx_db_vselect(fmt, args);
	va_end(args);

	return result;
}

#if defined(HAVE_MYSQL)
static int	is_recoverable_mysql_error(int err_no)
{
	if (0 == err_no)
		err_no = (int)mysql_errno(conn);

	switch (err_no)
	{
		case CR_CONN_HOST_ERROR:
		case CR_SERVER_GONE_ERROR:
		case CR_CONNECTION_ERROR:
		case CR_SERVER_LOST:
		case CR_UNKNOWN_HOST:
		case CR_COMMANDS_OUT_OF_SYNC:
		case ER_SERVER_SHUTDOWN:
		case ER_ACCESS_DENIED_ERROR:		/* wrong user or password */
		case ER_ILLEGAL_GRANT_FOR_TABLE:	/* user without any privileges */
		case ER_TABLEACCESS_DENIED_ERROR:	/* user without some privilege */
		case ER_UNKNOWN_ERROR:
		case ER_UNKNOWN_COM_ERROR:
		case ER_LOCK_DEADLOCK:
		case ER_LOCK_WAIT_TIMEOUT:
#ifdef ER_CLIENT_INTERACTION_TIMEOUT
		case ER_CLIENT_INTERACTION_TIMEOUT:
#endif
#ifdef CR_SSL_CONNECTION_ERROR
		case CR_SSL_CONNECTION_ERROR:
#endif
#ifdef ER_CONNECTION_KILLED
		case ER_CONNECTION_KILLED:
#endif
			return SUCCEED;
	}

	return FAIL;
}

static int	is_inhibited_mysql_error(int err_no)
{
	if (1 < mysql_err_cnt)
		return FAIL;

	if (0 < txn_level && 0 == txn_begin)
		return FAIL;

	switch (err_no)
	{
		case CR_SERVER_GONE_ERROR:
		case CR_SERVER_LOST:
#ifdef ER_CLIENT_INTERACTION_TIMEOUT
		case ER_CLIENT_INTERACTION_TIMEOUT:
#endif
			return SUCCEED;
	}

	return FAIL;
}
#elif defined(HAVE_POSTGRESQL)
static int	is_recoverable_postgresql_error(const PGconn *pg_conn, const PGresult *pg_result)
{
	if (CONNECTION_OK != PQstatus(pg_conn))
		return SUCCEED;

	if (0 == zbx_strcmp_null(PQresultErrorField(pg_result, PG_DIAG_SQLSTATE), ZBX_PG_DEADLOCK))
		return SUCCEED;

	if (0 == zbx_strcmp_null(PQresultErrorField(pg_result, PG_DIAG_SQLSTATE), ZBX_PG_READ_ONLY))
		return SUCCEED;

	return FAIL;
}
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: specify the autoincrement options during db connect               *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_init_autoincrement_options_basic(void)
{
	db_auto_increment = 1;
}

/******************************************************************************
 *                                                                            *
 * Purpose: connect to the database                                           *
 *                                                                            *
 * Return value: ZBX_DB_OK - successfully connected                           *
 *               ZBX_DB_DOWN - database is down                               *
 *               ZBX_DB_FAIL - failed to connect                              *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_connect_basic(const zbx_config_dbhigh_t *cfg)
{
	int		ret = ZBX_DB_OK, last_txn_error, last_txn_level;
#if defined(HAVE_MYSQL)
	int		err_no = 0;
#elif defined(HAVE_ORACLE)
	char		*connect = NULL;
	sword		err = OCI_SUCCESS;
	static ub2	csid = 0;
#elif defined(HAVE_POSTGRESQL)
#	define ZBX_DB_MAX_PARAMS	9

	int		rc;
	char		*cport = NULL;
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	const char	*keywords[ZBX_DB_MAX_PARAMS + 1];
	const char	*values[ZBX_DB_MAX_PARAMS + 1];
	unsigned int	i = 0;
#elif defined(HAVE_SQLITE3)
	char		*p, *path = NULL;
#endif

	/* Allow executing statements during a connection initialization. Make sure to mark transaction as failed. */
	if (0 != txn_level)
		txn_error = ZBX_DB_DOWN;

	last_txn_error = txn_error;
	last_txn_level = txn_level;

	txn_error = ZBX_DB_OK;
	txn_level = 0;

#if defined(HAVE_MYSQL)
	if (NULL == (conn = mysql_init(NULL)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot allocate or initialize MYSQL database connection object");
		exit(EXIT_FAILURE);
	}

	if (1 == db_auto_increment)
	{
		/* Shadow global auto_increment variables. */
		/* Setting session variables requires special permissions in MySQL 8.0.14-8.0.17. */

		if (0 != MYSQL_OPTIONS(conn, MYSQL_INIT_COMMAND, MYSQL_OPTIONS_ARGS_VOID_CAST
				"set @@session.auto_increment_increment=1"))
		{
			zabbix_log(LOG_LEVEL_ERR, "Cannot set auto_increment_increment option.");
			ret = ZBX_DB_FAIL;
		}

		if (ZBX_DB_OK == ret && 0 != MYSQL_OPTIONS(conn, MYSQL_INIT_COMMAND, MYSQL_OPTIONS_ARGS_VOID_CAST
				"set @@session.auto_increment_offset=1"))
		{
			zabbix_log(LOG_LEVEL_ERR, "Cannot set auto_increment_offset option.");
			ret = ZBX_DB_FAIL;
		}
	}

#if defined(HAVE_MYSQL_TLS)
	if (ZBX_DB_OK == ret && NULL != cfg->config_db_tls_connect)
	{
		unsigned int	mysql_tls_mode;

		if (0 == strcmp(cfg->config_db_tls_connect, ZBX_DB_TLS_CONNECT_REQUIRED_TXT))
			mysql_tls_mode = SSL_MODE_REQUIRED;
		else if (0 == strcmp(cfg->config_db_tls_connect, ZBX_DB_TLS_CONNECT_VERIFY_CA_TXT))
			mysql_tls_mode = SSL_MODE_VERIFY_CA;
		else
			mysql_tls_mode = SSL_MODE_VERIFY_IDENTITY;

		if (0 != mysql_options(conn, MYSQL_OPT_SSL_MODE, &mysql_tls_mode))
		{
			zabbix_log(LOG_LEVEL_ERR, "Cannot set MYSQL_OPT_SSL_MODE option.");
			ret = ZBX_DB_FAIL;
		}
	}

	if (ZBX_DB_OK == ret && NULL != cfg->config_db_tls_ca_file && 0 != mysql_options(conn, MYSQL_OPT_SSL_CA,
			cfg->config_db_tls_ca_file))
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot set MYSQL_OPT_SSL_CA option.");
		ret = ZBX_DB_FAIL;
	}

	if (ZBX_DB_OK == ret && NULL != cfg->config_db_tls_key_file && 0 != mysql_options(conn, MYSQL_OPT_SSL_KEY,
			cfg->config_db_tls_key_file))
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot set MYSQL_OPT_SSL_KEY option.");
		ret = ZBX_DB_FAIL;
	}

	if (ZBX_DB_OK == ret && NULL != cfg->config_db_tls_cert_file && 0 != mysql_options(conn, MYSQL_OPT_SSL_CERT,
			cfg->config_db_tls_cert_file))
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot set MYSQL_OPT_SSL_CERT option.");
		ret = ZBX_DB_FAIL;
	}

	if (ZBX_DB_OK == ret && NULL != cfg->config_db_tls_cipher && 0 != mysql_options(conn, MYSQL_OPT_SSL_CIPHER,
			cfg->config_db_tls_cipher))
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot set MYSQL_OPT_SSL_CIPHER option.");
		ret = ZBX_DB_FAIL;
	}
#if defined(HAVE_MYSQL_TLS_CIPHERSUITES)
	if (ZBX_DB_OK == ret && NULL != cfg->config_db_tls_cipher_13 && 0 != mysql_options(conn,
			MYSQL_OPT_TLS_CIPHERSUITES, cfg->config_db_tls_cipher_13))
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot set MYSQL_OPT_TLS_CIPHERSUITES option.");
		ret = ZBX_DB_FAIL;
	}
#endif
#elif defined(HAVE_MARIADB_TLS)
	if (ZBX_DB_OK == ret && NULL != cfg->config_db_tls_connect)
	{
		if (0 == strcmp(cfg->config_db_tls_connect, ZBX_DB_TLS_CONNECT_REQUIRED_TXT))
		{
			my_bool	enforce_tls = 1;

			if (0 != mysql_optionsv(conn, MYSQL_OPT_SSL_ENFORCE, (void *)&enforce_tls))
			{
				zabbix_log(LOG_LEVEL_ERR, "Cannot set MYSQL_OPT_SSL_ENFORCE option.");
				ret = ZBX_DB_FAIL;
			}
		}
		else
		{
			my_bool	verify = 1;

			if (0 != mysql_optionsv(conn, MYSQL_OPT_SSL_VERIFY_SERVER_CERT, (void *)&verify))
			{
				zabbix_log(LOG_LEVEL_ERR, "Cannot set MYSQL_OPT_SSL_VERIFY_SERVER_CERT option.");
				ret = ZBX_DB_FAIL;
			}
		}
	}

	if (ZBX_DB_OK == ret && NULL != cfg->config_db_tls_ca_file && 0 != mysql_optionsv(conn, MYSQL_OPT_SSL_CA,
			cfg->config_db_tls_ca_file))
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot set MYSQL_OPT_SSL_CA option.");
		ret = ZBX_DB_FAIL;
	}

	if (ZBX_DB_OK == ret && NULL != cfg->config_db_tls_key_file && 0 != mysql_optionsv(conn, MYSQL_OPT_SSL_KEY,
			cfg->config_db_tls_key_file))
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot set MYSQL_OPT_SSL_KEY option.");
		ret = ZBX_DB_FAIL;
	}

	if (ZBX_DB_OK == ret && NULL != cfg->config_db_tls_cert_file && 0 != mysql_optionsv(conn, MYSQL_OPT_SSL_CERT,
			cfg->config_db_tls_cert_file))
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot set MYSQL_OPT_SSL_CERT option.");
		ret = ZBX_DB_FAIL;
	}

	if (ZBX_DB_OK == ret && NULL != cfg->config_db_tls_cipher && 0 != mysql_optionsv(conn, MYSQL_OPT_SSL_CIPHER,
			cfg->config_db_tls_cipher))
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot set MYSQL_OPT_SSL_CIPHER option.");
		ret = ZBX_DB_FAIL;
	}
#endif
	if (ZBX_DB_OK == ret && NULL == mysql_real_connect(conn, cfg->config_dbhost, cfg->config_dbuser,
			cfg->config_dbpassword, cfg->config_dbname, cfg->config_dbport, cfg->config_dbsocket,
			CLIENT_MULTI_STATEMENTS))
	{
		err_no = (int)mysql_errno(conn);
		zbx_db_errlog(ERR_Z3001, err_no, mysql_error(conn), cfg->config_dbname);
		ret = ZBX_DB_FAIL;
	}

	if (ZBX_DB_OK == ret)
	{
		/* in contrast to "set names utf8" results of this call will survive auto-reconnects */
		/* utf8mb3 is deprecated and it's superset utf8mb4 should be used instead if available */
		if (0 != mysql_set_character_set(conn, ZBX_SUPPORTED_DB_CHARACTER_SET_UTF8MB4) &&
				0 != mysql_set_character_set(conn, ZBX_SUPPORTED_DB_CHARACTER_SET_UTF8) &&
				0 != mysql_set_character_set(conn, ZBX_SUPPORTED_DB_CHARACTER_SET_UTF8MB3))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot set MySQL character set");
		}
	}

	if (ZBX_DB_OK == ret && 0 != mysql_autocommit(conn, 1))
	{
		err_no = (int)mysql_errno(conn);
		zbx_db_errlog(ERR_Z3001, err_no, mysql_error(conn), cfg->config_dbname);
		ret = ZBX_DB_FAIL;
	}

	if (ZBX_DB_OK == ret && 0 != mysql_select_db(conn, cfg->config_dbname))
	{
		err_no = (int)mysql_errno(conn);
		zbx_db_errlog(ERR_Z3001, err_no, mysql_error(conn), cfg->config_dbname);
		ret = ZBX_DB_FAIL;
	}

	if (ZBX_DB_FAIL == ret && SUCCEED == is_recoverable_mysql_error(err_no))
		ret = ZBX_DB_DOWN;

	mysql_err_cnt = ZBX_DB_OK == ret ? 0 : mysql_err_cnt + 1;
#elif defined(HAVE_ORACLE)
	memset(&oracle, 0, sizeof(oracle));

	zbx_vector_ptr_create(&oracle.db_results);

	/* connection string format: [//]host[:port][/service name] */

	if ('\0' != *cfg->config_dbhost)
	{
		/* Easy Connect method */
		connect = zbx_strdcatf(connect, "//%s", cfg->config_dbhost);
		if (0 != cfg->config_dbport)
			connect = zbx_strdcatf(connect, ":%d", cfg->config_dbport);
		if ('\0' != *cfg->config_dbname)
			connect = zbx_strdcatf(connect, "/%s", cfg->config_dbname);
	}
	else
	{
		/* Net Service Name method */
		connect = zbx_strdup(connect, cfg->config_dbname);
	}

	while (ZBX_DB_OK == ret)
	{
		/* initialize environment */
		if (OCI_SUCCESS == (err = OCIEnvNlsCreate((OCIEnv **)&oracle.envhp, (ub4)OCI_DEFAULT, (dvoid *)0,
				(dvoid * (*)(dvoid *,size_t))0, (dvoid * (*)(dvoid *, dvoid *, size_t))0,
				(void (*)(dvoid *, dvoid *))0, (size_t)0, (dvoid **)0, csid, csid)))
		{
			if (0 != csid)
				break;	/* environment with UTF8 character set successfully created */

			/* try to find out the id of UTF8 character set */
			if (0 == (csid = OCINlsCharSetNameToId(oracle.envhp, (const oratext *)"UTF8")))
			{
				zabbix_log(LOG_LEVEL_WARNING, "Cannot find out the ID of \"UTF8\" character set."
						" Relying on current \"NLS_LANG\" settings.");
				break;	/* use default environment with character set derived from NLS_LANG */
			}

			/* get rid of this environment to create a better one on the next iteration */
			OCIHandleFree((dvoid *)oracle.envhp, OCI_HTYPE_ENV);
			oracle.envhp = NULL;
		}
		else
		{
			zbx_db_errlog(ERR_Z3001, err, zbx_oci_error(err, NULL), connect);
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
				(text *)cfg->config_dbuser, (ub4)(NULL != cfg->config_dbuser ?
				strlen(cfg->config_dbuser) : 0), (text *)cfg->config_dbpassword,
				(ub4)(NULL != cfg->config_dbpassword ?
				strlen(cfg->config_dbpassword) : 0), (text *)connect, (ub4)strlen(connect),
				OCI_DEFAULT);

		switch (err)
		{
			case OCI_SUCCESS_WITH_INFO:
				zabbix_log(LOG_LEVEL_WARNING, "%s", zbx_oci_error(err, NULL));
				/* break; is not missing here */
				ZBX_FALLTHROUGH;
			case OCI_SUCCESS:
				err = OCIAttrGet((void *)oracle.svchp, OCI_HTYPE_SVCCTX, (void *)&oracle.srvhp,
						(ub4 *)0, OCI_ATTR_SERVER, oracle.errhp);
		}

		if (OCI_SUCCESS != err)
		{
			zbx_db_errlog(ERR_Z3001, err, zbx_oci_error(err, NULL), connect);
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
			zbx_db_errlog(ERR_Z3001, err, zbx_oci_error(err, NULL), connect);
			ret = ZBX_DB_DOWN;
		}
	}

	if (ZBX_DB_OK == ret)
	{
		if (0 < (ret = zbx_db_execute_basic("alter session set nls_numeric_characters='. '")))
			ret = ZBX_DB_OK;
	}

	zbx_free(connect);
#elif defined(HAVE_POSTGRESQL)
	if (NULL != cfg->config_db_tls_connect)
	{
		keywords[i] = "sslmode";

		if (0 == strcmp(cfg->config_db_tls_connect, ZBX_DB_TLS_CONNECT_REQUIRED_TXT))
			values[i++] = "require";
		else if (0 == strcmp(cfg->config_db_tls_connect, ZBX_DB_TLS_CONNECT_VERIFY_CA_TXT))
			values[i++] = "verify-ca";
		else
			values[i++] = "verify-full";
	}

	if (NULL != cfg->config_db_tls_cert_file)
	{
		keywords[i] = "sslcert";
		values[i++] = cfg->config_db_tls_cert_file;
	}

	if (NULL != cfg->config_db_tls_key_file)
	{
		keywords[i] = "sslkey";
		values[i++] = cfg->config_db_tls_key_file;
	}

	if (NULL != cfg->config_db_tls_ca_file)
	{
		keywords[i] = "sslrootcert";
		values[i++] = cfg->config_db_tls_ca_file;
	}

	if (NULL != cfg->config_dbhost)
	{
		keywords[i] = "host";
		values[i++] = cfg->config_dbhost;
	}

	if (NULL != cfg->config_dbname)
	{
		keywords[i] = "dbname";
		values[i++] = cfg->config_dbname;
	}

	if (NULL != cfg->config_dbuser)
	{
		keywords[i] = "user";
		values[i++] = cfg->config_dbuser;
	}

	if (NULL != cfg->config_dbpassword)
	{
		keywords[i] = "password";
		values[i++] = cfg->config_dbpassword;
	}

	if (0 != cfg->config_dbport)
	{
		keywords[i] = "port";
		values[i++] = cport = zbx_dsprintf(cport, "%d", cfg->config_dbport);
	}

	keywords[i] = NULL;
	values[i] = NULL;

	conn = PQconnectdbParams(keywords, values, 0);

	zbx_free(cport);

	/* check to see that the backend connection was successfully made */
	if (CONNECTION_OK != PQstatus(conn))
	{
		zbx_db_errlog(ERR_Z3001, 0, PQerrorMessage(conn), cfg->config_dbname);
		ret = ZBX_DB_DOWN;
		goto out;
	}

	if (NULL != cfg->config_dbschema && '\0' != *cfg->config_dbschema)
	{
		char	*dbschema_esc;

		dbschema_esc = zbx_db_dyn_escape_string_basic(cfg->config_dbschema, ZBX_SIZE_T_MAX, ZBX_SIZE_T_MAX,
				ESCAPE_SEQUENCE_ON);
		if (ZBX_DB_DOWN == (rc = zbx_db_execute_basic("set schema '%s'", dbschema_esc)) || ZBX_DB_FAIL == rc)
			ret = rc;
		zbx_free(dbschema_esc);
	}

	if (ZBX_DB_FAIL == ret || ZBX_DB_DOWN == ret)
		goto out;

	/* disable "nonstandard use of \' in a string literal" warning */
	if (0 < (ret = zbx_db_execute_basic("set escape_string_warning to off")))
		ret = ZBX_DB_OK;

	if (ZBX_DB_OK != ret)
		goto out;

	/* increase float precision */
	if (0 < (ret = zbx_db_execute_basic("set extra_float_digits to 3")))
		ret = ZBX_DB_OK;

	if (ZBX_DB_OK != ret)
		goto out;

	result = zbx_db_select_basic("show standard_conforming_strings");

	if ((zbx_db_result_t)ZBX_DB_DOWN == result || NULL == result)
	{
		ret = (NULL == result) ? ZBX_DB_FAIL : ZBX_DB_DOWN;
		goto out;
	}

	if (NULL != (row = zbx_db_fetch_basic(result)))
		ZBX_PG_ESCAPE_BACKSLASH = (0 == strcmp(row[0], "off"));

	zbx_db_free_result(result);

	result = zbx_db_select_basic("show default_transaction_read_only");

	if ((zbx_db_result_t)ZBX_DB_DOWN == result || NULL == result)
	{
		ret = (NULL == result) ? ZBX_DB_FAIL : ZBX_DB_DOWN;
		goto out;
	}

	if (NULL != (row = zbx_db_fetch_basic(result)))
	{
		if (0 == strcmp(row[0], "on"))
		{
			zbx_db_free_result(result);
			ret = ZBX_DB_RONLY;
			goto out;
		}
	}

	zbx_db_free_result(result);

	result = zbx_db_select_basic("select pg_is_in_recovery();");

	if ((zbx_db_result_t)ZBX_DB_DOWN == result || NULL == result)
	{
		ret = (NULL == result) ? ZBX_DB_FAIL : ZBX_DB_DOWN;
		goto out;
	}

	if (NULL != (row = zbx_db_fetch_basic(result)))
	{
		if (0 == strcmp(row[0], "t"))
		{
			zbx_db_free_result(result);
			ret = ZBX_DB_RONLY;
			goto out;
		}
	}

	zbx_db_free_result(result);

	if (90000 <= ZBX_PG_SVERSION)
	{
		/* change the output format for values of type bytea from hex (the default) to escape */
		if (0 < (ret = zbx_db_execute_basic("set bytea_output=escape")))
			ret = ZBX_DB_OK;
	}

out:
#elif defined(HAVE_SQLITE3)
#ifdef HAVE_FUNCTION_SQLITE3_OPEN_V2
	if (SQLITE_OK != sqlite3_open_v2(cfg->config_dbname, &conn, SQLITE_OPEN_READWRITE |
		SQLITE_OPEN_CREATE, NULL))
#else
	if (SQLITE_OK != sqlite3_open(cfg->config_dbname, &conn))
#endif
	{
		zbx_db_errlog(ERR_Z3001, 0, sqlite3_errmsg(conn), cfg->config_dbname);
		ret = ZBX_DB_DOWN;
		goto out;
	}

	/* do not return SQLITE_BUSY immediately, wait for N ms */
	sqlite3_busy_timeout(conn, SEC_PER_MIN * 1000);

	if (0 < (ret = zbx_db_execute_basic("pragma synchronous=0")))
		ret = ZBX_DB_OK;

	if (ZBX_DB_OK != ret)
		goto out;

	if (0 < (ret = zbx_db_execute_basic("pragma foreign_keys=on")))
		ret = ZBX_DB_OK;

	if (ZBX_DB_OK != ret)
		goto out;

	if (0 < (ret = zbx_db_execute_basic("pragma temp_store=2")))
		ret = ZBX_DB_OK;

	if (ZBX_DB_OK != ret)
		goto out;

	path = zbx_strdup(NULL, cfg->config_dbname);

	if (NULL != (p = strrchr(path, '/')))
		*++p = '\0';
	else
		*path = '\0';

	if (0 < (ret = zbx_db_execute_basic("pragma temp_store_directory='%s'", path)))
		ret = ZBX_DB_OK;

	zbx_free(path);
out:
#endif	/* HAVE_SQLITE3 */
	if (ZBX_DB_OK != ret)
		zbx_db_close_basic();

	txn_error = last_txn_error;
	txn_level = last_txn_level;

	return ret;
}

int	zbx_db_init_basic(const char *dbname, const char *const dbschema, int log_slow_queries, char **error)
{
#ifdef HAVE_SQLITE3
	zbx_stat_t	buf;
#endif
	config_log_slow_queries = log_slow_queries;
#ifdef HAVE_SQLITE3
	if (0 != zbx_stat(dbname, &buf))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot open database file \"%s\": %s", dbname, zbx_strerror(errno));
		zabbix_log(LOG_LEVEL_WARNING, "creating database ...");

		if (SQLITE_OK != sqlite3_open(dbname, &conn))
		{
			zbx_db_errlog(ERR_Z3002, 0, sqlite3_errmsg(conn), dbname);
			*error = zbx_strdup(*error, "cannot open database");
			return FAIL;
		}

		if (SUCCEED != zbx_mutex_create(&sqlite_access, ZBX_MUTEX_SQLITE3, error))
			return FAIL;

		zbx_db_execute_basic("%s", dbschema);
		zbx_db_close_basic();
		return SUCCEED;
	}

	return zbx_mutex_create(&sqlite_access, ZBX_MUTEX_SQLITE3, error);
#else	/* not HAVE_SQLITE3 */
	ZBX_UNUSED(dbname);
	ZBX_UNUSED(dbschema);
	ZBX_UNUSED(error);

	return SUCCEED;
#endif	/* HAVE_SQLITE3 */
}

void	zbx_db_deinit_basic(void)
{
#ifdef HAVE_SQLITE3
	zbx_mutex_destroy(&sqlite_access);
#endif
}

void	zbx_db_close_basic(void)
{
#if defined(HAVE_MYSQL)
	if (NULL != conn)
	{
		mysql_close(conn);
		conn = NULL;
	}
#elif defined(HAVE_ORACLE)
	if (0 != oracle.db_results.values_num)
	{
		int	i;

		zabbix_log(LOG_LEVEL_WARNING, "cannot process queries: database is closed");

		for (i = 0; i < oracle.db_results.values_num; i++)
		{
			/* deallocate all handles before environment is deallocated */
			OCI_DBclean_result_handle(oracle.db_results.values[i]);
		}
	}

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

	if (NULL != oracle.srvhp)
	{
		OCIHandleFree(oracle.srvhp, OCI_HTYPE_SERVER);
		oracle.srvhp = NULL;
	}

	if (NULL != oracle.envhp)
	{
		/* delete the environment handle, which deallocates all other handles associated with it */
		OCIHandleFree((dvoid *)oracle.envhp, OCI_HTYPE_ENV);
		oracle.envhp = NULL;
	}

	zbx_vector_ptr_destroy(&oracle.db_results);
#elif defined(HAVE_POSTGRESQL)
	if (NULL != conn)
	{
		PQfinish(conn);
		conn = NULL;
	}
#elif defined(HAVE_SQLITE3)
	if (NULL != conn)
	{
		sqlite3_close(conn);
		conn = NULL;
	}
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: start transaction                                                 *
 *                                                                            *
 * Comments: do nothing if DB does not support transactions                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_begin_basic(void)
{
	int	rc = ZBX_DB_OK;

	if (txn_level > 0)
	{
		zabbix_log(LOG_LEVEL_CRIT, "ERROR: nested transaction detected. Please report it to Zabbix Team.");
		zbx_this_should_never_happen_backtrace();
		assert(0);
	}
#if defined(HAVE_MYSQL)
	txn_begin = 1;
#endif
	txn_level++;

#if defined(HAVE_MYSQL) || defined(HAVE_POSTGRESQL)
	rc = zbx_db_execute_basic("begin;");
#elif defined(HAVE_SQLITE3)
	zbx_mutex_lock(sqlite_access);
	rc = zbx_db_execute_basic("begin;");
#endif

	if (ZBX_DB_DOWN == rc)
		txn_level--;
#if defined(HAVE_MYSQL)
	txn_begin = 0;
#endif
	return rc;
}

/******************************************************************************
 *                                                                            *
 * Purpose: commit transaction                                                *
 *                                                                            *
 * Comments: do nothing if DB does not support transactions                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_commit_basic(void)
{
	int	rc = ZBX_DB_OK;
#ifdef HAVE_ORACLE
	sword	err;
#endif

	if (0 == txn_level)
	{
		zabbix_log(LOG_LEVEL_CRIT, "ERROR: commit without transaction."
				" Please report it to Zabbix Team.");
		zbx_this_should_never_happen_backtrace();
		assert(0);
	}

	if (ZBX_DB_OK != txn_error)
		return ZBX_DB_FAIL; /* commit called on failed transaction */

#if defined(HAVE_ORACLE)
	if (OCI_SUCCESS != (err = OCITransCommit(oracle.svchp, oracle.errhp, OCI_DEFAULT)))
		rc = OCI_handle_sql_error(ERR_Z3005, err, "commit failed");
#elif defined(HAVE_MYSQL) || defined(HAVE_POSTGRESQL) || defined(HAVE_SQLITE3)
	rc = zbx_db_execute_basic("commit;");
#endif

	if (ZBX_DB_OK > rc) { /* commit failed */
		txn_error = rc;
		return rc;
	}

#ifdef HAVE_SQLITE3
	zbx_mutex_unlock(sqlite_access);
#endif

	txn_level--;
	txn_end_error = ZBX_DB_OK;

	return rc;
}

/******************************************************************************
 *                                                                            *
 * Purpose: rollback transaction                                              *
 *                                                                            *
 * Comments: do nothing if DB does not support transactions                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_rollback_basic(void)
{
	int	rc = ZBX_DB_OK, last_txn_error;
#ifdef HAVE_ORACLE
	sword	err;
#endif

	if (0 == txn_level)
	{
		zabbix_log(LOG_LEVEL_CRIT, "ERROR: rollback without transaction."
				" Please report it to Zabbix Team.");
		zbx_this_should_never_happen_backtrace();
		assert(0);
	}

	last_txn_error = txn_error;

	/* allow rollback of failed transaction */
	txn_error = ZBX_DB_OK;

#if defined(HAVE_MYSQL) || defined(HAVE_POSTGRESQL)
	rc = zbx_db_execute_basic("rollback;");
#elif defined(HAVE_ORACLE)
	if (OCI_SUCCESS != (err = OCITransRollback(oracle.svchp, oracle.errhp, OCI_DEFAULT)))
		rc = OCI_handle_sql_error(ERR_Z3005, err, "rollback failed");
#elif defined(HAVE_SQLITE3)
	rc = zbx_db_execute_basic("rollback;");
	zbx_mutex_unlock(sqlite_access);
#endif

	/* There is no way to recover from rollback errors, so there is no need to preserve transaction level / error. */
	txn_level = 0;
	txn_error = ZBX_DB_OK;

	if (ZBX_DB_FAIL == rc)
		txn_end_error = ZBX_DB_FAIL;
	else
		txn_end_error = last_txn_error;	/* error that caused rollback */

	return rc;
}

int	zbx_db_txn_level(void)
{
	return txn_level;
}

int	zbx_db_txn_error(void)
{
	return txn_error;
}

int	zbx_db_txn_end_error(void)
{
	return txn_end_error;
}

#ifdef HAVE_ORACLE
static sword	zbx_oracle_statement_prepare(const char *sql)
{
	return OCIStmtPrepare(oracle.stmthp, oracle.errhp, (text *)sql, (ub4)strlen((char *)sql), (ub4)OCI_NTV_SYNTAX,
			(ub4)OCI_DEFAULT);
}

static sword	zbx_oracle_statement_execute(ub4 iters, ub4 *nrows)
{
	sword	err;

	if (OCI_SUCCESS == (err = OCIStmtExecute(oracle.svchp, oracle.stmthp, oracle.errhp, iters, (ub4)0,
			(CONST OCISnapshot *)NULL, (OCISnapshot *)NULL,
			0 == txn_level ? OCI_COMMIT_ON_SUCCESS : OCI_DEFAULT)))
	{
		err = OCIAttrGet((void *)oracle.stmthp, OCI_HTYPE_STMT, nrows, (ub4 *)0, OCI_ATTR_ROW_COUNT,
				oracle.errhp);
	}

	return err;
}
#endif

#ifdef HAVE_ORACLE
int	zbx_db_statement_prepare_basic(const char *sql)
{
	sword	err;
	int	ret = ZBX_DB_OK;

	if (0 == txn_level)
		zabbix_log(LOG_LEVEL_DEBUG, "query without transaction detected");

	if (ZBX_DB_OK != txn_error)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "ignoring query [txnlev:%d] within failed transaction", txn_level);
		return ZBX_DB_FAIL;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "query [txnlev:%d] [%s]", txn_level, sql);

	if (OCI_SUCCESS != (err = zbx_oracle_statement_prepare(sql)))
		ret = OCI_handle_sql_error(ERR_Z3005, err, sql);

	if (ZBX_DB_FAIL == ret && 0 < txn_level)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "query [%s] failed, setting transaction as failed", sql);
		txn_error = ZBX_DB_FAIL;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: callback function used by dynamic parameter binding               *
 *                                                                            *
 ******************************************************************************/
static sb4 db_bind_dynamic_cb(dvoid *ctxp, OCIBind *bindp, ub4 iter, ub4 index, dvoid **bufpp, ub4 *alenp, ub1 *piecep,
		dvoid **indpp)
{
	zbx_db_bind_context_t	*context = (zbx_db_bind_context_t *)ctxp;

	ZBX_UNUSED(bindp);
	ZBX_UNUSED(index);

	switch (context->type)
	{
		case ZBX_TYPE_ID: /* handle 0 -> NULL conversion */
			if (0 == context->rows[iter][context->position].ui64)
			{
				*bufpp = NULL;
				*alenp = 0;
				break;
			}
			ZBX_FALLTHROUGH;
		case ZBX_TYPE_UINT:
			*bufpp = &((OCINumber *)context->data)[iter];
			*alenp = sizeof(OCINumber);
			break;
		case ZBX_TYPE_INT:
			*bufpp = &context->rows[iter][context->position].i32;
			*alenp = sizeof(int);
			break;
		case ZBX_TYPE_FLOAT:
			*bufpp = &context->rows[iter][context->position].dbl;
			*alenp = sizeof(double);
			break;
		case ZBX_TYPE_CHAR:
		case ZBX_TYPE_TEXT:
		case ZBX_TYPE_SHORTTEXT:
		case ZBX_TYPE_LONGTEXT:
		case ZBX_TYPE_CUID:
		case ZBX_TYPE_BLOB:
			*bufpp = context->rows[iter][context->position].str;
			*alenp = ((size_t *)context->data)[iter];
			break;
		default:
			return FAIL;
	}

	*indpp = NULL;
	*piecep = OCI_ONE_PIECE;

	return OCI_CONTINUE;
}

/******************************************************************************
 *                                                                            *
 * Purpose: performs dynamic parameter binding, converting value if necessary *
 *                                                                            *
 * Parameters: context  - [OUT] the bind context                              *
 *             position - [IN] the parameter position                         *
 *             type     - [IN] the parameter type (ZBX_TYPE_* )               *
 *             rows     - [IN] the data to bind - array of rows,              *
 *                             each row being an array of columns             *
 *             rows_num - [IN] the number of rows in the data                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_bind_parameter_dyn(zbx_db_bind_context_t *context, int position, unsigned char type,
		zbx_db_value_t **rows, int rows_num)
{
	int		i, ret = ZBX_DB_OK;
	size_t		*sizes;
	sword		err;
	OCINumber	*values;
	ub2		data_type;
	OCIBind		*bindhp = NULL;

	context->position = position;
	context->rows = rows;
	context->data = NULL;
	context->type = type;

	switch (type)
	{
		case ZBX_TYPE_ID:
		case ZBX_TYPE_UINT:
			values = (OCINumber *)zbx_malloc(NULL, sizeof(OCINumber) * rows_num);

			for (i = 0; i < rows_num; i++)
			{
				err = OCINumberFromInt(oracle.errhp, &rows[i][position].ui64, sizeof(zbx_uint64_t),
						OCI_NUMBER_UNSIGNED, &values[i]);

				if (OCI_SUCCESS != err)
				{
					ret = OCI_handle_sql_error(ERR_Z3007, err, NULL);
					goto out;
				}
			}

			context->data = (OCINumber *)values;
			context->size_max = sizeof(OCINumber);
			data_type = SQLT_VNU;
			break;
		case ZBX_TYPE_INT:
			context->size_max = sizeof(int);
			data_type = SQLT_INT;
			break;
		case ZBX_TYPE_FLOAT:
			context->size_max = sizeof(double);
			data_type = SQLT_BDOUBLE;
			break;
		case ZBX_TYPE_CHAR:
		case ZBX_TYPE_TEXT:
		case ZBX_TYPE_SHORTTEXT:
		case ZBX_TYPE_LONGTEXT:
		case ZBX_TYPE_CUID:
			sizes = (size_t *)zbx_malloc(NULL, sizeof(size_t) * rows_num);
			context->size_max = 0;

			for (i = 0; i < rows_num; i++)
			{
				sizes[i] = strlen(rows[i][position].str);

				if (sizes[i] > context->size_max)
					context->size_max = sizes[i];
			}

			context->data = sizes;
			data_type = SQLT_LNG;
			break;
		case ZBX_TYPE_BLOB:
			sizes = (size_t *)zbx_malloc(NULL, sizeof(size_t) * rows_num);
			context->size_max = 0;

			for (i = 0; i < rows_num; i++)
			{
				size_t	dst_len;
				size_t	src_len = strlen(rows[i][position].str) * 3 / 4 + 1;
				char	*dst = (char*)zbx_malloc(NULL, src_len);

				zbx_base64_decode(rows[i][position].str, (char *)dst, src_len, &dst_len);
				sizes[i] = dst_len;
				zbx_free(rows[i][position].str);
				rows[i][position].str = dst;

				if (sizes[i] > context->size_max)
					context->size_max = sizes[i];
			}

			context->data = sizes;
			data_type = SQLT_BIN;
			break;
		default:
			THIS_SHOULD_NEVER_HAPPEN;
			exit(EXIT_FAILURE);
	}

	err = OCIBindByPos(oracle.stmthp, &bindhp, oracle.errhp, context->position + 1, NULL, context->size_max,
			data_type, NULL, NULL, NULL, 0, NULL, (ub4)OCI_DATA_AT_EXEC);

	if (OCI_SUCCESS != err)
	{
		ret = OCI_handle_sql_error(ERR_Z3007, err, NULL);

		if (ZBX_DB_FAIL == ret && 0 < txn_level)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "query failed, setting transaction as failed");
			txn_error = ZBX_DB_FAIL;
		}

		goto out;
	}

	err = OCIBindDynamic(bindhp, oracle.errhp, (dvoid *)context, db_bind_dynamic_cb, NULL, NULL);

	if (OCI_SUCCESS != err)
	{
		ret = OCI_handle_sql_error(ERR_Z3007, err, NULL);

		if (ZBX_DB_FAIL == ret && 0 < txn_level)
		{
			zabbix_log(LOG_LEVEL_DEBUG, "query failed, setting transaction as failed");
			txn_error = ZBX_DB_FAIL;
		}

		goto out;
	}
out:
	if (ret != ZBX_DB_OK)
		zbx_db_clean_bind_context(context);

	return ret;
}

void	zbx_db_clean_bind_context(zbx_db_bind_context_t *context)
{
	zbx_free(context->data);
}

int	zbx_db_statement_execute(int iters)
{
	sword	err;
	ub4	nrows;
	int	ret;

	if (ZBX_DB_OK != txn_error)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "ignoring query [txnlev:%d] within failed transaction", txn_level);
		ret = ZBX_DB_FAIL;
		goto out;
	}

	if (OCI_SUCCESS != (err = zbx_oracle_statement_execute(iters, &nrows)))
		ret = OCI_handle_sql_error((ORA_ERR_UNIQ_CONSTRAINT == err ? ERR_Z3008 : ERR_Z3007), err, NULL);
	else
		ret = (int)nrows;

	if (ZBX_DB_FAIL == ret && 0 < txn_level)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "query failed, setting transaction as failed");
		txn_error = ZBX_DB_FAIL;
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "%s():%d", __func__, ret);

	return ret;
}
#endif

#if defined(HAVE_MYSQL)
void	zbx_mysql_escape_bin(const char *src, char *dst, size_t size)
{
	mysql_real_escape_string(conn, dst, src, size);
}
#elif defined(HAVE_POSTGRESQL)
void	zbx_postgresql_escape_bin(const char *src, char **dst, size_t size)
{
	size_t	dst_size;

	*dst = (char*)PQescapeByteaConn(conn, (const unsigned char*)src, size, &dst_size);
}
#endif

static char	*db_replace_nonprintable_chars(const char *sql, char **sql_printable)
{
	if (NULL == *sql_printable)
	{
		*sql_printable = zbx_strdup(NULL, sql);
		zbx_replace_invalid_utf8_and_nonprintable(*sql_printable);
	}

	return *sql_printable;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Execute SQL statement. For non-select statements only.            *
 *                                                                            *
 * Return value: ZBX_DB_FAIL (on error) or ZBX_DB_DOWN (on recoverable error) *
 *               or number of rows affected (on success)                      *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_vexecute(const char *fmt, va_list args)
{
	char		*sql = NULL, *sql_printable = NULL;
	int		ret = ZBX_DB_OK;
	double		sec = 0;
#if defined(HAVE_MYSQL)
#elif defined(HAVE_ORACLE)
	sword		err = OCI_SUCCESS;
#elif defined(HAVE_POSTGRESQL)
	PGresult	*result;
	char		*error = NULL;
#elif defined(HAVE_SQLITE3)
	int		err;
	char		*error = NULL;
#endif

	if (0 != config_log_slow_queries)
		sec = zbx_time();

	sql = zbx_dvsprintf(sql, fmt, args);

	if (0 == txn_level)
		zabbix_log(LOG_LEVEL_DEBUG, "query without transaction detected");

	if (ZBX_DB_OK != txn_error)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "ignoring query [txnlev:%d] [%s] within failed transaction", txn_level,
				db_replace_nonprintable_chars(sql, &sql_printable));
		ret = ZBX_DB_FAIL;
		goto clean;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "query [txnlev:%d] [%s]", txn_level,
			db_replace_nonprintable_chars(sql, &sql_printable));

#if defined(HAVE_MYSQL)
	if (NULL == conn)
	{
		zbx_db_errlog(ERR_Z3003, 0, NULL, NULL);
		ret = ZBX_DB_FAIL;
	}
	else
	{
		zbx_err_codes_t	errcode;
		int		err_no;

		if (0 != mysql_query(conn, sql))
		{
			err_no = (int)mysql_errno(conn);
			errcode = (ER_DUP_ENTRY == err_no ? ERR_Z3008 : ERR_Z3005);
			mysql_err_cnt++;

			if (FAIL == is_inhibited_mysql_error(err_no))
				zbx_db_errlog(errcode, err_no, mysql_error(conn), sql);

			ret = (SUCCEED == is_recoverable_mysql_error(err_no) ? ZBX_DB_DOWN : ZBX_DB_FAIL);
		}
		else
		{
			int	status;

			do
			{
				if (0 != mysql_field_count(conn))
				{
					zabbix_log(LOG_LEVEL_DEBUG, "cannot retrieve result set");
					break;
				}

				ret += (int)mysql_affected_rows(conn);

				/* more results? 0 = yes (keep looping), -1 = no, >0 = error */
				if (0 < (status = mysql_next_result(conn)))
				{
					err_no = (int)mysql_errno(conn);
					errcode = (ER_DUP_ENTRY == err_no ? ERR_Z3008 : ERR_Z3005);
					mysql_err_cnt++;

					if (FAIL == is_inhibited_mysql_error(err_no))
						zbx_db_errlog(errcode, err_no, mysql_error(conn), sql);

					ret = (SUCCEED == is_recoverable_mysql_error(err_no) ? ZBX_DB_DOWN : ZBX_DB_FAIL);
				}
			}
			while (0 == status);
		}
	}
#elif defined(HAVE_ORACLE)
	if (OCI_SUCCESS == (err = zbx_oracle_statement_prepare(sql)))
	{
		ub4	nrows = 0;

		if (OCI_SUCCESS == (err = zbx_oracle_statement_execute(1, &nrows)))
			ret = (int)nrows;
	}

	if (OCI_SUCCESS != err)
		ret = OCI_handle_sql_error((err == ORA_ERR_UNIQ_CONSTRAINT ? ERR_Z3008 : ERR_Z3005), err, sql);

#elif defined(HAVE_POSTGRESQL)
	result = PQexec(conn,sql);

	if (NULL == result)
	{
		zbx_db_errlog(ERR_Z3005, 0, "result is NULL", sql);
		ret = (CONNECTION_OK == PQstatus(conn) ? ZBX_DB_FAIL : ZBX_DB_DOWN);
	}
	else if (PGRES_COMMAND_OK != PQresultStatus(result))
	{
		zbx_err_codes_t	errcode;

		zbx_postgresql_error(&error, result);

		if (0 == zbx_strcmp_null(PQresultErrorField(result, PG_DIAG_SQLSTATE), ZBX_PG_UNIQUE_VIOLATION))
			errcode = ERR_Z3008;
		else if (0 == zbx_strcmp_null(PQresultErrorField(result, PG_DIAG_SQLSTATE), ZBX_PG_READ_ONLY))
			errcode = ERR_Z3009;
		else
			errcode = ERR_Z3005;

		zbx_db_errlog(errcode, 0, error, sql);
		zbx_free(error);

		ret = (SUCCEED == is_recoverable_postgresql_error(conn, result) ? ZBX_DB_DOWN : ZBX_DB_FAIL);
	}

	if (ZBX_DB_OK == ret)
		ret = atoi(PQcmdTuples(result));

	PQclear(result);
#elif defined(HAVE_SQLITE3)
	if (0 == txn_level)
		zbx_mutex_lock(sqlite_access);

lbl_exec:
	if (SQLITE_OK != (err = sqlite3_exec(conn, sql, NULL, 0, &error)))
	{
		if (SQLITE_BUSY == err)
			goto lbl_exec;

		zbx_db_errlog(ERR_Z3005, 0, error, sql);
		sqlite3_free(error);

		switch (err)
		{
			case SQLITE_ERROR:	/* SQL error or missing database; assuming SQL error, because if we are
						this far into execution, zbx_db_connect_basic() was successful */
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
		zbx_mutex_unlock(sqlite_access);
#endif	/* HAVE_SQLITE3 */

	if (0 != config_log_slow_queries)
	{
		sec = zbx_time() - sec;
		if (sec > (double)config_log_slow_queries / 1000.0)
		{
			zabbix_log(LOG_LEVEL_WARNING, "slow query: " ZBX_FS_DBL " sec, \"%s\"", sec,
					db_replace_nonprintable_chars(sql, &sql_printable));
		}
	}

	if (ZBX_DB_FAIL == ret && 0 < txn_level)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "query [%s] failed, setting transaction as failed",
				db_replace_nonprintable_chars(sql, &sql_printable));
		txn_error = ZBX_DB_FAIL;
	}
clean:
	zbx_free(sql_printable);
	zbx_free(sql);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute a select statement                                        *
 *                                                                            *
 * Return value: data, NULL (on error) or (zbx_db_result_t)ZBX_DB_DOWN        *
 *                                                                            *
 ******************************************************************************/
zbx_db_result_t	zbx_db_vselect(const char *fmt, va_list args)
{
	char		*sql = NULL;
	zbx_db_result_t	result = NULL;
	double		sec = 0;
#if defined(HAVE_ORACLE)
	sword		err = OCI_SUCCESS;
	ub4		prefetch_rows = 200, counter;

	ZBX_UNUSED(counter);
#elif defined(HAVE_POSTGRESQL)
	char		*error = NULL;
#elif defined(HAVE_SQLITE3)
	int		ret = FAIL;
	char		*error = NULL;
#endif

	if (0 != config_log_slow_queries)
		sec = zbx_time();

	sql = zbx_dvsprintf(sql, fmt, args);

	if (ZBX_DB_OK != txn_error)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "ignoring query [txnlev:%d] [%s] within failed transaction", txn_level, sql);
		goto clean;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "query [txnlev:%d] [%s]", txn_level, sql);

#if defined(HAVE_MYSQL)
	result = (zbx_db_result_t)zbx_malloc(NULL, sizeof(struct zbx_db_result));
	result->result = NULL;

	if (NULL == conn)
	{
		zbx_db_errlog(ERR_Z3003, 0, NULL, NULL);

		zbx_db_free_result(result);
		result = NULL;
	}
	else
	{
		if (0 != mysql_query(conn, sql) || NULL == (result->result = mysql_store_result(conn)))
		{
			int err_no = (int)mysql_errno(conn);

			mysql_err_cnt++;

			if (FAIL == is_inhibited_mysql_error(err_no))
				zbx_db_errlog(ERR_Z3005, err_no, mysql_error(conn), sql);

			zbx_db_free_result(result);
			result = (SUCCEED == is_recoverable_mysql_error(err_no) ? (zbx_db_result_t)ZBX_DB_DOWN : NULL);
		}
	}
#elif defined(HAVE_ORACLE)
	result = zbx_malloc(NULL, sizeof(struct zbx_db_result));
	memset(result, 0, sizeof(struct zbx_db_result));
	zbx_vector_ptr_append(&oracle.db_results, result);

	err = OCIHandleAlloc((dvoid *)oracle.envhp, (dvoid **)&result->stmthp, OCI_HTYPE_STMT, (size_t)0, (dvoid **)0);

	/* Prefetching when working with Oracle is needed because otherwise it fetches only 1 row at a time when doing */
	/* selects (default behavior). There are 2 ways to do prefetching: memory based and rows based. Based on the   */
	/* study optimal (speed-wise) memory based prefetch is 2 MB. But in case of many subsequent selects CPU usage  */
	/* jumps up to 100 %. Using rows prefetch with up to 200 rows does not affect CPU usage, it is the same as     */
	/* without prefetching at all. See ZBX-5920, ZBX-6493 for details.                                             */
	/*                                                                                                             */
	/* Tested on Oracle 11gR2.                                                                                     */
	/*                                                                                                             */
	/* Oracle info: docs.oracle.com/cd/B28359_01/appdev.111/b28395/oci04sql.htm                                    */

	if (OCI_SUCCESS == err)
	{
		err = OCIAttrSet(result->stmthp, OCI_HTYPE_STMT, &prefetch_rows, sizeof(prefetch_rows),
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

	for (counter = 1; OCI_SUCCESS == err && counter <= (ub4)result->ncolumn; counter++)
	{
		OCIParam	*parmdp = NULL;
		OCIDefine	*defnp = NULL;
		ub4		char_semantics;
		ub2		col_width = 0, data_type = 0;

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
			if (OCI_SUCCESS == err)
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
			if (SQLT_IBDOUBLE != data_type && SQLT_BDOUBLE != data_type)
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

						/* adjust for UTF-8 */
						col_width *= 4;
					}
					else
					{
#define ZBX_MIN_OCI_NUMBER_WIDTH	22
						/* retrieve the column width in bytes */
						err = OCIAttrGet((void *)parmdp, (ub4)OCI_DTYPE_PARAM, (void *)&col_width,
								(ub4 *)NULL, (ub4)OCI_ATTR_DATA_SIZE, (OCIError *)oracle.errhp);

						if (ZBX_MIN_OCI_NUMBER_WIDTH > col_width && SQLT_NUM == data_type)
						{
							col_width = ZBX_MIN_OCI_NUMBER_WIDTH;
						}
#undef ZBX_MIN_OCI_NUMBER_WIDTH
					}
				}
				col_width++;	/* add 1 byte for terminating '\0' */
			}
			else
				col_width = ZBX_MAX_DOUBLE_LEN + 1;

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
		int	server_status;

		server_status = OCI_handle_sql_error(ERR_Z3005, err, sql);
		zbx_db_free_result(result);

		result = (ZBX_DB_DOWN == server_status ? (zbx_db_result_t)(intptr_t)server_status : NULL);
	}
#elif defined(HAVE_POSTGRESQL)
	result = zbx_malloc(NULL, sizeof(struct zbx_db_result));
	result->pg_result = PQexec(conn, sql);
	result->values = NULL;
	result->cursor = 0;
	result->row_num = 0;

	if (NULL == result->pg_result)
		zbx_db_errlog(ERR_Z3005, 0, "result is NULL", sql);

	if (PGRES_TUPLES_OK != PQresultStatus(result->pg_result))
	{
		zbx_err_codes_t	errcode;

		if (0 == zbx_strcmp_null(PQresultErrorField(result->pg_result, PG_DIAG_SQLSTATE), ZBX_PG_READ_ONLY))
			errcode = ERR_Z3009;
		else
			errcode = ERR_Z3005;

		zbx_postgresql_error(&error, result->pg_result);
		zbx_db_errlog(errcode, 0, error, sql);
		zbx_free(error);

		if (SUCCEED == is_recoverable_postgresql_error(conn, result->pg_result))
		{
			zbx_db_free_result(result);
			result = (zbx_db_result_t)ZBX_DB_DOWN;
		}
		else
		{
			zbx_db_free_result(result);
			result = NULL;
		}
	}
	else	/* init rownum */
		result->row_num = PQntuples(result->pg_result);
#elif defined(HAVE_SQLITE3)
	if (0 == txn_level)
		zbx_mutex_lock(sqlite_access);

	result = zbx_malloc(NULL, sizeof(struct zbx_db_result));
	result->curow = 0;

lbl_get_table:
	if (SQLITE_OK != (ret = sqlite3_get_table(conn,sql, &result->data, &result->nrow, &result->ncolumn, &error)))
	{
		if (SQLITE_BUSY == ret)
			goto lbl_get_table;

		zbx_db_errlog(ERR_Z3005, 0, error, sql);
		sqlite3_free(error);

		zbx_db_free_result(result);

		switch (ret)
		{
			case SQLITE_ERROR:	/* SQL error or missing database; assuming SQL error, because if we are
						this far into execution, zbx_db_connect_basic() was successful */
			case SQLITE_NOMEM:	/* a malloc() failed */
			case SQLITE_MISMATCH:	/* data type mismatch */
				result = NULL;
				break;
			default:
				result = (zbx_db_result_t)ZBX_DB_DOWN;
				break;
		}
	}

	if (0 == txn_level)
		zbx_mutex_unlock(sqlite_access);
#endif	/* HAVE_SQLITE3 */
	if (0 != config_log_slow_queries)
	{
		sec = zbx_time() - sec;
		if (sec > (double)config_log_slow_queries / 1000.0)
			zabbix_log(LOG_LEVEL_WARNING, "slow query: " ZBX_FS_DBL " sec, \"%s\"", sec, sql);
	}

	if (NULL == result && 0 < txn_level)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "query [%s] failed, setting transaction as failed", sql);
		txn_error = ZBX_DB_FAIL;
	}
clean:
	zbx_free(sql);

	return result;
}

/*
 * Execute SQL statement. For select statements only.
 */
zbx_db_result_t	zbx_db_select_n_basic(const char *query, int n)
{
#if defined(HAVE_ORACLE)
	return zbx_db_select_basic("select * from (%s) where rownum<=%d", query, n);
#elif defined(HAVE_MYSQL) || defined(HAVE_POSTGRESQL) || defined(HAVE_SQLITE3)
	return zbx_db_select_basic("%s limit %d", query, n);
#endif
}

#if defined(HAVE_ORACLE)
static void	db_set_fetch_error(int dberr)
{
	if (0 < txn_level)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "fetch failed, setting transaction as failed");
		txn_error = dberr;
	}
}
#endif

int	zbx_db_get_row_num(zbx_db_result_t result)
{
#if defined(HAVE_POSTGRESQL)
	return result->row_num;
#elif defined(HAVE_MYSQL)
	return (int)mysql_num_rows(result->result);
#else
	ZBX_UNUSED(result);
	return 0;
#endif
}

zbx_db_row_t	zbx_db_fetch_basic(zbx_db_result_t result)
{
#if defined(HAVE_ORACLE)
	int		i;
	sword		rc;
	static char	errbuf[512];
	sb4		errcode;
#endif

	if (NULL == result)
		return NULL;

#if defined(HAVE_MYSQL)
	if (NULL == result->result)
		return NULL;

	return (zbx_db_row_t)mysql_fetch_row(result->result);
#elif defined(HAVE_ORACLE)
	if (NULL == result->stmthp)
		return NULL;

	if (OCI_NO_DATA == (rc = OCIStmtFetch2(result->stmthp, oracle.errhp, 1, OCI_FETCH_NEXT, 0, OCI_DEFAULT)))
		return NULL;

	if (OCI_SUCCESS != rc)
	{
		ub4	rows_fetched;
		ub4	sizep = sizeof(ub4);

		if (OCI_SUCCESS != (rc = OCIErrorGet((dvoid *)oracle.errhp, (ub4)1, (text *)NULL,
				&errcode, (text *)errbuf, (ub4)sizeof(errbuf), OCI_HTYPE_ERROR)))
		{
			zbx_db_errlog(ERR_Z3006, rc, zbx_oci_error(rc, NULL), NULL);
			db_set_fetch_error(ZBX_DB_FAIL);
			return NULL;
		}

		switch (errcode)
		{
			case 1012:	/* ORA-01012: not logged on */
			case 2396:	/* ORA-02396: exceeded maximum idle time */
			case 3113:	/* ORA-03113: end-of-file on communication channel */
			case 3114:	/* ORA-03114: not connected to ORACLE */
				zbx_db_errlog(ERR_Z3006, errcode, errbuf, NULL);
				db_set_fetch_error(ZBX_DB_DOWN);
				return NULL;
			default:
				rc = OCIAttrGet((void *)result->stmthp, (ub4)OCI_HTYPE_STMT, (void *)&rows_fetched,
						(ub4 *)&sizep, (ub4)OCI_ATTR_ROWS_FETCHED, (OCIError *)oracle.errhp);

				if (OCI_SUCCESS != rc || 1 != rows_fetched)
				{
					zbx_db_errlog(ERR_Z3006, errcode, errbuf, NULL);
					db_set_fetch_error(ZBX_DB_FAIL);
					return NULL;
				}
		}
	}

	for (i = 0; i < result->ncolumn; i++)
	{
		ub4	alloc, amount;
		ub1	csfrm;
		sword	rc2;

		if (NULL == result->clobs[i])
			continue;

		if (OCI_SUCCESS != (rc2 = OCILobGetLength(oracle.svchp, oracle.errhp, result->clobs[i], &amount)))
		{
			/* If the LOB is NULL, the length is undefined. */
			/* In this case the function returns OCI_INVALID_HANDLE. */
			if (OCI_INVALID_HANDLE != rc2)
			{
				zbx_db_errlog(ERR_Z3006, rc2, zbx_oci_error(rc2, NULL), NULL);
				db_set_fetch_error(ZBX_DB_FAIL);
				return NULL;
			}
			else
				amount = 0;
		}
		else if (OCI_SUCCESS != (rc2 = OCILobCharSetForm(oracle.envhp, oracle.errhp, result->clobs[i], &csfrm)))
		{
			zbx_db_errlog(ERR_Z3006, rc2, zbx_oci_error(rc2, NULL), NULL);
			db_set_fetch_error(ZBX_DB_FAIL);
			return NULL;
		}

		if (result->values_alloc[i] < (alloc = amount * ZBX_MAX_BYTES_IN_UTF8_CHAR + 1))
		{
			result->values_alloc[i] = alloc;
			result->values[i] = zbx_realloc(result->values[i], result->values_alloc[i]);
		}

		if (OCI_SUCCESS == rc2)
		{
			if (OCI_SUCCESS != (rc2 = OCILobRead(oracle.svchp, oracle.errhp, result->clobs[i], &amount,
					(ub4)1, (dvoid *)result->values[i], (ub4)(result->values_alloc[i] - 1),
					(dvoid *)NULL, (OCICallbackLobRead)NULL, (ub2)0, csfrm)))
			{
				zbx_db_errlog(ERR_Z3006, rc2, zbx_oci_error(rc2, NULL), NULL);
				db_set_fetch_error(ZBX_DB_FAIL);
				return NULL;
			}
		}

		result->values[i][amount] = '\0';
	}

	return result->values;
#elif defined(HAVE_POSTGRESQL)
	/* EOF */
	if (result->cursor == result->row_num)
		return NULL;

	/* init result */
	if (0 == result->cursor)
		result->fld_num = PQnfields(result->pg_result);

	if (result->fld_num > 0)
	{
		int	i;

		if (NULL == result->values)
			result->values = zbx_malloc(result->values, sizeof(char *) * result->fld_num);

		for (i = 0; i < result->fld_num; i++)
		{
			result->values[i] = PQgetvalue(result->pg_result, result->cursor, i);

			if ('\0' == *result->values[i] && PQgetisnull(result->pg_result, result->cursor, i))
					result->values[i] = NULL;
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

int	zbx_db_is_null_basic(const char *field)
{
	if (NULL == field)
		return SUCCEED;
#ifdef HAVE_ORACLE
	if ('\0' == *field)
		return SUCCEED;
#endif
	return FAIL;
}

#ifdef HAVE_ORACLE
static void	OCI_DBclean_result_handle(zbx_db_result_t result)
{
	if (NULL != result->values)
	{
		int	i;

		for (i = 0; i < result->ncolumn; i++)
		{
			/* deallocate the lob locator variable */
			if (NULL != result->clobs[i])
			{
				OCIDescriptorFree((dvoid *)result->clobs[i], OCI_DTYPE_LOB);
				result->clobs[i] = NULL;
			}
		}
	}

	if (result->stmthp)
	{
		OCIHandleFree((dvoid *)result->stmthp, OCI_HTYPE_STMT);
		result->stmthp = NULL;
	}
}
static void	OCI_DBclean_result(zbx_db_result_t result)
{
	if (NULL == result)
		return;

	OCI_DBclean_result_handle(result);

	if (NULL != result->values)
	{
		int	i;

		for (i = 0; i < result->ncolumn; i++)
			zbx_free(result->values[i]);

		zbx_free(result->values);
		zbx_free(result->clobs);
		zbx_free(result->values_alloc);
	}
}
#endif

void	zbx_db_free_result(zbx_db_result_t result)
{
#if defined(HAVE_MYSQL)
	if (NULL == result)
		return;

	mysql_free_result(result->result);
	zbx_free(result);
#elif defined(HAVE_ORACLE)
	int i;

	if (NULL == result)
		return;

	OCI_DBclean_result(result);

	for (i = 0; i < oracle.db_results.values_num; i++)
	{
		if (oracle.db_results.values[i] == result)
		{
			zbx_vector_ptr_remove_noorder(&oracle.db_results, i);
			break;
		}
	}

	zbx_free(result);
#elif defined(HAVE_POSTGRESQL)
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
#elif defined(HAVE_SQLITE3)
	if (NULL == result)
		return;

	if (NULL != result->data)
	{
		sqlite3_free_table(result->data);
	}

	zbx_free(result);
#endif	/* HAVE_SQLITE3 */
}

#ifdef HAVE_ORACLE
/* server status: OCI_SERVER_NORMAL or OCI_SERVER_NOT_CONNECTED */
static ub4	OCI_DBserver_status(void)
{
	sword	err;
	ub4	server_status = OCI_SERVER_NOT_CONNECTED;

	err = OCIAttrGet((void *)oracle.srvhp, OCI_HTYPE_SERVER, (void *)&server_status,
			(ub4 *)0, OCI_ATTR_SERVER_STATUS, (OCIError *)oracle.errhp);

	if (OCI_SUCCESS != err)
		zabbix_log(LOG_LEVEL_WARNING, "cannot determine Oracle server status, assuming not connected");

	return server_status;
}
#endif	/* HAVE_ORACLE */

static int	zbx_db_is_escape_sequence(char c)
{
#if defined(HAVE_MYSQL)
	if ('\'' == c || '\\' == c)
#elif defined(HAVE_POSTGRESQL)
	if ('\'' == c || ('\\' == c && 1 == ZBX_PG_ESCAPE_BACKSLASH))
#else
	if ('\'' == c)
#endif
		return SUCCEED;

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Return value: escaped string                                               *
 *                                                                            *
 * Comments: sync changes with 'zbx_db_get_escape_string_len'                 *
 *           and 'zbx_db_dyn_escape_string_basic                              *
 *                                                                            *
 ******************************************************************************/
static void	zbx_db_escape_string(const char *src, char *dst, size_t len, zbx_escape_sequence_t flag)
{
	const char	*s;
	char		*d;

	assert(dst);

	len--;	/* '\0' */

	for (s = src, d = dst; NULL != s && '\0' != *s && 0 < len; s++)
	{
		if (ESCAPE_SEQUENCE_ON == flag && SUCCEED == zbx_db_is_escape_sequence(*s))
		{
			if (2 > len)
				break;

#if defined(HAVE_MYSQL)
			*d++ = '\\';
#elif defined(HAVE_POSTGRESQL)
			*d++ = *s;
#else
			*d++ = '\'';
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
 * Purpose: to calculate escaped string length limited by bytes or characters *
 *          whichever is reached first.                                       *
 *                                                                            *
 * Parameters: s         - [IN] string to escape                              *
 *             max_bytes - [IN] limit in bytes                                *
 *             max_chars - [IN] limit in characters                           *
 *             flag      - [IN] sequences need to be escaped on/off           *
 *                                                                            *
 * Return value: return length in bytes of escaped string                     *
 *               with terminating '\0'                                        *
 *                                                                            *
 ******************************************************************************/
static size_t	zbx_db_get_escape_string_len(const char *s, size_t max_bytes, size_t max_chars,
		zbx_escape_sequence_t flag)
{
	size_t	csize, len = 1;	/* '\0' */

	if (NULL == s)
		return len;

	while ('\0' != *s && 0 < max_chars)
	{
		csize = zbx_utf8_char_len(s);

		/* process non-UTF-8 characters as single byte characters */
		if (0 == csize)
			csize = 1;

		if (max_bytes < csize)
			break;

		if (ESCAPE_SEQUENCE_ON == flag && SUCCEED == zbx_db_is_escape_sequence(*s))
			len++;

		s += csize;
		len += csize;
		max_bytes -= csize;
		max_chars--;
	}

	return len;
}

/******************************************************************************
 *                                                                            *
 * Purpose: to escape string limited by bytes or characters, whichever limit  *
 *          is reached first.                                                 *
 *                                                                            *
 * Parameters: src       - [IN] string to escape                              *
 *             max_bytes - [IN] limit in bytes                                *
 *             max_chars - [IN] limit in characters                           *
 *             flag      - [IN] sequences need to be escaped on/off           *
 *                                                                            *
 * Return value: escaped string                                               *
 *                                                                            *
 ******************************************************************************/
char	*zbx_db_dyn_escape_string_basic(const char *src, size_t max_bytes, size_t max_chars, zbx_escape_sequence_t flag)
{
	char	*dst = NULL;
	size_t	len;

	len = zbx_db_get_escape_string_len(src, max_bytes, max_chars, flag);

	dst = (char *)zbx_malloc(dst, len);

	zbx_db_escape_string(src, dst, len, flag);

	return dst;
}

/******************************************************************************
 *                                                                            *
 * Return value: return length of escaped LIKE pattern with terminating '\0'  *
 *                                                                            *
 * Comments: sync changes with 'zbx_db_escape_like_pattern'                   *
 *                                                                            *
 ******************************************************************************/
static int	zbx_db_get_escape_like_pattern_len(const char *src)
{
	int		len;
	const char	*s;

	len = zbx_db_get_escape_string_len(src, ZBX_SIZE_T_MAX, ZBX_SIZE_T_MAX, ESCAPE_SEQUENCE_ON) - 1; /* minus '\0' */

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
 * Return value: escaped string to be used as pattern in LIKE                 *
 *                                                                            *
 * Comments: sync changes with 'zbx_db_get_escape_like_pattern_len'           *
 *                                                                            *
 *           For instance, we wish to find string a_b%c\d'e!f in our database *
 *           using '!' as escape character. Our queries then become:          *
 *                                                                            *
 *           ... LIKE 'a!_b!%c\\d\'e!!f' ESCAPE '!' (MySQL, PostgreSQL)       *
 *           ... LIKE 'a!_b!%c\d''e!!f' ESCAPE '!' (Oracle, SQLite3)          *
 *                                                                            *
 *           Using backslash as escape character in LIKE would be too much    *
 *           trouble, because escaping backslashes would have to be escaped   *
 *           as well, like so:                                                *
 *                                                                            *
 *           ... LIKE 'a\\_b\\%c\\\\d\'e!f' ESCAPE '\\' or                    *
 *           ... LIKE 'a\\_b\\%c\\\\d\\\'e!f' ESCAPE '\\' (MySQL, PostgreSQL) *
 *           ... LIKE 'a\_b\%c\\d''e!f' ESCAPE '\' (Oracle, SQLite3)          *
 *                                                                            *
 *           Hence '!' instead of backslash.                                  *
 *                                                                            *
 ******************************************************************************/
static void	zbx_db_escape_like_pattern(const char *src, char *dst, int len)
{
	char		*d;
	char		*tmp = NULL;
	const char	*t;

	assert(dst);

	tmp = (char *)zbx_malloc(tmp, len);

	zbx_db_escape_string(src, tmp, len, ESCAPE_SEQUENCE_ON);

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
 * Return value: escaped string to be used as pattern in LIKE                 *
 *                                                                            *
 ******************************************************************************/
char	*zbx_db_dyn_escape_like_pattern_basic(const char *src)
{
	int	len;
	char	*dst = NULL;

	len = zbx_db_get_escape_like_pattern_len(src);

	dst = (char *)zbx_malloc(dst, len);

	zbx_db_escape_like_pattern(src, dst, len);

	return dst;
}

/******************************************************************************
 *                                                                            *
 * Purpose: return the string length to fit into a database field of the      *
 *          specified size                                                    *
 *                                                                            *
 * Return value: the string length in bytes                                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_db_strlen_n(const char *text_loc, size_t maxlen)
{
	return zbx_strlen_utf8_nchars(text_loc, maxlen);
}

/*********************************************************************************
 *                                                                               *
 * Purpose: determine if a vendor database(MySQL, MariaDB, PostgreSQL,           *
 *          Oracle, ElasticDB) version satisfies Zabbix requirements             *
 *                                                                               *
 * Parameters: database                - [IN] database name                      *
 *             current_version         - [IN] detected numeric version           *
 *             min_version             - [IN] minimum required numeric version   *
 *             max_version             - [IN] maximum required numeric version   *
 *             min_supported_version   - [IN] minimum supported numeric version  *
 *                                                                               *
 * Return value: resulting status flag                                           *
 *                                                                               *
 *********************************************************************************/
int	zbx_db_version_check(const char *database, zbx_uint32_t current_version, zbx_uint32_t min_version,
		zbx_uint32_t max_version, zbx_uint32_t min_supported_version)
{
	int	flag;

	if (ZBX_DBVERSION_UNDEFINED == current_version)
	{
		flag = DB_VERSION_FAILED_TO_RETRIEVE;
		zabbix_log(LOG_LEVEL_WARNING, "Failed to retrieve %s version", database);
	}
	else if (min_version > current_version && ZBX_DBVERSION_UNDEFINED != min_version)
	{
		flag = DB_VERSION_LOWER_THAN_MINIMUM;
		zabbix_log(LOG_LEVEL_WARNING, "Unsupported DB! %s version %lu is older than %lu",
				database, (unsigned long)current_version, (unsigned long)min_version);
	}
	else if (max_version < current_version && ZBX_DBVERSION_UNDEFINED != max_version)
	{
		flag = DB_VERSION_HIGHER_THAN_MAXIMUM;
		zabbix_log(LOG_LEVEL_WARNING, "Unsupported DB! %s version %lu is newer than %lu",
				database, (unsigned long)current_version, (unsigned long)max_version);
	}
	else if (min_supported_version > current_version && ZBX_DBVERSION_UNDEFINED != min_supported_version)
	{
		flag = DB_VERSION_NOT_SUPPORTED_ERROR;
		/* log message must be handled by server or proxy */
	}
	else
		flag = DB_VERSION_SUPPORTED;

	return flag;
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepare json for front-end with the DB current, minimum and       *
 *          maximum versions and a flag that indicates if the version         *
 *          satisfies the requirements, and other information as well as      *
 *          information about DB extension in a similar way                   *
 *                                                                            *
 * Parameters:  json                     - [IN/OUT] json data                 *
 *              info                     - [IN] info to serialize             *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_version_json_create(struct zbx_json *json, struct zbx_db_version_info_t *info)
{
	zbx_json_addobject(json, NULL);
	zbx_json_addstring(json, "database", info->database, ZBX_JSON_TYPE_STRING);

	if (DB_VERSION_FAILED_TO_RETRIEVE != info->flag)
		zbx_json_addstring(json, "current_version", info->friendly_current_version, ZBX_JSON_TYPE_STRING);

	zbx_json_addstring(json, "min_version", info->friendly_min_version, ZBX_JSON_TYPE_STRING);
	zbx_json_addstring(json, "max_version", info->friendly_max_version, ZBX_JSON_TYPE_STRING);
	zbx_json_addint64(json, "history_pk", info->history_pk);

	if (NULL != info->friendly_min_supported_version)
	{
		zbx_json_addstring(json, "min_supported_version", info->friendly_min_supported_version,
				ZBX_JSON_TYPE_STRING);
	}

	zbx_json_addint64(json, "flag", info->flag);
#ifdef HAVE_ORACLE
	if (0 != info->tables_json.buffer_offset)
	{
		zbx_json_addobject(json, "schema_diff");
		if (0 != strcmp(info->tables_json.buffer, "{}"))
			zbx_json_addraw(json, "tables", info->tables_json.buffer);
		zbx_json_close(json);
	}
#endif
	zbx_json_close(json);

	if (NULL != info->extension)
	{
		zbx_json_addobject(json, NULL);
		zbx_json_addstring(json, "database", info->extension, ZBX_JSON_TYPE_STRING);

		if (DB_VERSION_FAILED_TO_RETRIEVE != info->ext_flag)
		{
			zbx_json_addstring(json, "current_version",
					info->ext_friendly_current_version, ZBX_JSON_TYPE_STRING);
		}

		zbx_json_addstring(json, "min_version", info->ext_friendly_min_version, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(json, "max_version", info->ext_friendly_max_version, ZBX_JSON_TYPE_STRING);
		zbx_json_addstring(json, "min_supported_version", info->ext_friendly_min_supported_version,
				ZBX_JSON_TYPE_STRING);

		zbx_json_addint64(json, "flag", info->ext_flag);
		zbx_json_addint64(json, "extension_err_code", info->ext_err_code);
#ifdef HAVE_POSTGRESQL
		if (0 == zbx_strcmp_null(info->extension, ZBX_DB_EXTENSION_TIMESCALEDB))
		{
			if (ON == zbx_tsdb_get_compression_availability())
			{
				zbx_json_addstring(json, "compression_availability", "true", ZBX_JSON_TYPE_INT);
			}
			else
			{
				zbx_json_addstring(json, "compression_availability", "false", ZBX_JSON_TYPE_INT);
			}

			zbx_json_addint64(json, "compressed_chunks_history", info->history_compressed_chunks);
			zbx_json_addint64(json, "compressed_chunks_trends", info->trends_compressed_chunks);
		}
#endif
		zbx_json_close(json);
	}
}

/******************************************************************************
 *                                                                            *
 * Purpose: For PostgreSQL, MySQL and MariaDB:                                *
 *          returns DBMS version as integer: MMmmuu                           *
 *          M = major version part                                            *
 *          m = minor version part                                            *
 *          u = patch version part                                            *
 *                                                                            *
 * Example: if the original DB version was 1.2.34 then 10234 gets returned    *
 *                                                                            *
 * Purpose: For OracleDB:                                                     *
 *          returns DBMS version as integer: MRruRRivUU                       *
 *          MR = major release version part                                   *
 *          ru = release update version part                                  *
 *          RR = release update version revision part                         *
 *          iv = increment version part                                       *
 *          UU = unused, reserved for future use                              *
 *                                                                            *
 * Example: if the OracleDB version was 18.1.0.0.7 then 1801000007 gets       *
 *          returned                                                          *
 *                                                                            *
 * Return value: DBMS version or DBVERSION_UNDEFINED if unknown               *
 *                                                                            *
 ******************************************************************************/
static zbx_uint32_t	zbx_dbms_version_get(void)
{
#if defined(HAVE_MYSQL)
	return ZBX_MYSQL_SVERSION;
#elif defined(HAVE_POSTGRESQL)
	return ZBX_PG_SVERSION;
#elif defined(HAVE_ORACLE)
	return ZBX_ORACLE_SVERSION;
#else
	return ZBX_DBVERSION_UNDEFINED;
#endif
}

/***************************************************************************************************************
 *                                                                                                             *
 * Purpose: retrieves the DB version info, including numeric version value                                     *
 *                                                                                                             *
 *          For PostgreSQL:                                                                                    *
 *          numeric version is available from the API                                                          *
 *                                                                                                             *
 *          For MySQL and MariaDB:                                                                             *
 *          numeric version is available from the API, but also the additional processing is required          *
 *          to determine if it is a MySQL or MariaDB and save this result as well                              *
 *                                                                                                             *
 *          For Oracle:                                                                                        *
 *          numeric version needs to be manually parsed from the string result                                 *
 *          Oracle DB format is like 18.1.2.3.0 where                                                          *
 *            18 - major release version                                                                       *
 *            1 - release update version                                                                       *
 *            2 - release update version revision                                                              *
 *            3 - increment version                                                                            *
 *            0 - unused, reserved for future use                                                              *
 *                                                                                                             *
 *          Oracle Examples:                                                                                   *
 *          For "Oracle Database 18c Express Edition Release 1.0.0.0.0 - Production"    => 100000000           *
 *          For "Oracle Database 18c Express Edition Release 18.2.0.0.7 - Production"   => 1802000007          *
 *          For "Oracle Database 18c Express Edition Release 0.0.34.123.7 - Production" => DBVERSION_UNDEFINED *
 *          For "Oracle Database 18c Express Edition Release 1.0.3.x.7 - Production"    => DBVERISON_UNDEFINED *
 *          For "<anything else>"                                                       => DBVERSION_UNDEFINED *
 *                                                                                                             *
 **************************************************************************************************************/
void	zbx_dbms_version_info_extract(struct zbx_db_version_info_t *version_info)
{
#define RIGHT2(x)	((int)((zbx_uint32_t)(x) - ((zbx_uint32_t)((x)/100))*100))
#if defined(HAVE_MYSQL)
	int		client_major_version, client_minor_version, client_release_version, server_major_version,
			server_minor_version, server_release_version;
	const char	*info;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (NULL != (info = mysql_get_server_info(conn)) && NULL != strstr(info, "MariaDB"))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "MariaDB fork detected");
		ZBX_MARIADB_SFORK = ON;
	}

	if (ON == ZBX_MARIADB_SFORK && 6 == sscanf(info, "%d.%d.%d-%d.%d.%d-MariaDB", &client_major_version,
			&client_minor_version, &client_release_version, &server_major_version,
			&server_minor_version, &server_release_version))
	{
		ZBX_MYSQL_SVERSION = server_major_version * 10000 + server_minor_version * 100 +
				server_release_version;
		zabbix_log(LOG_LEVEL_DEBUG, "MariaDB subversion detected");
	}
	else
		ZBX_MYSQL_SVERSION = (zbx_uint32_t)mysql_get_server_version(conn);

	version_info->current_version = ZBX_MYSQL_SVERSION;
	version_info->friendly_current_version = zbx_dsprintf(NULL, "%d.%.2d.%.2d", RIGHT2(ZBX_MYSQL_SVERSION/10000),
			RIGHT2(ZBX_MYSQL_SVERSION/100), RIGHT2(ZBX_MYSQL_SVERSION));

	if (ON == ZBX_MARIADB_SFORK)
	{
		version_info->database = "MariaDB";

		version_info->min_version = ZBX_MARIADB_MIN_VERSION;
		version_info->max_version = ZBX_MARIADB_MAX_VERSION;
		version_info->min_supported_version = ZBX_MARIADB_MIN_SUPPORTED_VERSION;

		version_info->friendly_min_version = ZBX_MARIADB_MIN_VERSION_STR;
		version_info->friendly_max_version = ZBX_MARIADB_MAX_VERSION_STR;
		version_info->friendly_min_supported_version = ZBX_MARIADB_MIN_SUPPORTED_VERSION_STR;
	}
	else
	{
		version_info->database = "MySQL";

		version_info->min_version = ZBX_MYSQL_MIN_VERSION;
		version_info->max_version = ZBX_MYSQL_MAX_VERSION;
		version_info->min_supported_version = ZBX_MYSQL_MIN_SUPPORTED_VERSION;

		version_info->friendly_min_version = ZBX_MYSQL_MIN_VERSION_STR;
		version_info->friendly_max_version = ZBX_MYSQL_MAX_VERSION_STR;
		version_info->friendly_min_supported_version = ZBX_MYSQL_MIN_SUPPORTED_VERSION_STR;
	}

	version_info->flag = zbx_db_version_check(version_info->database, version_info->current_version,
			version_info->min_version, version_info->max_version, version_info->min_supported_version);

#elif defined(HAVE_POSTGRESQL)
	zbx_uint32_t major;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);
	ZBX_PG_SVERSION = (zbx_uint32_t)PQserverVersion(conn);

	major = ZBX_PG_SVERSION/10000;

	version_info->database = "PostgreSQL";

	version_info->current_version = ZBX_PG_SVERSION;
	version_info->min_version = ZBX_POSTGRESQL_MIN_VERSION;
	version_info->max_version = ZBX_POSTGRESQL_MAX_VERSION;
	version_info->min_supported_version = ZBX_POSTGRESQL_MIN_SUPPORTED_VERSION;

	if (10 > major)
	{
		version_info->friendly_current_version = zbx_dsprintf(NULL, "%" PRIu32 ".%d.%d", major,
				RIGHT2(ZBX_PG_SVERSION/100), RIGHT2(ZBX_PG_SVERSION));
	}
	else
	{
		version_info->friendly_current_version = zbx_dsprintf(NULL, "%" PRIu32 ".%d", major,
				RIGHT2(ZBX_PG_SVERSION));
	}

	version_info->friendly_min_version = ZBX_POSTGRESQL_MIN_VERSION_STR;
	version_info->friendly_max_version = ZBX_POSTGRESQL_MAX_VERSION_STR;
	version_info->friendly_min_supported_version = ZBX_POSTGRESQL_MIN_SUPPORTED_VERSION_STR;

	version_info->flag = zbx_db_version_check(version_info->database, version_info->current_version,
			version_info->min_version, version_info->max_version, version_info->min_supported_version);

#elif defined(HAVE_ORACLE)
#	ifdef HAVE_OCI_SERVER_RELEASE2
	char	*version_str = "Version ";
	ub4	oci_ver = 0;
#	endif
	char	*start, *release_str = "Release ";
	char	version_friendly[MAX_STRING_LEN / 8];
	int	major_release_version, release_update_version, release_update_version_revision,
			increment_version, reserved_for_future_use, overall_status = SUCCEED;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);
#	ifdef HAVE_OCI_SERVER_RELEASE2
	if (OCI_SUCCESS != OCIServerRelease2(oracle.svchp, oracle.errhp, (OraText *) version_friendly,
			(ub4)sizeof(version_friendly), OCI_HTYPE_SVCCTX, &oci_ver, OCI_DEFAULT))
#	else
	if (OCI_SUCCESS != OCIServerVersion(oracle.svchp, oracle.errhp, (OraText *) version_friendly,
			(ub4)sizeof(version_friendly), OCI_HTYPE_SVCCTX))
#	endif
	{
		overall_status = FAIL;
		goto out;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "OracleDB version retrieved unparsed: %s", version_friendly);

	if (
#	ifdef HAVE_OCI_SERVER_RELEASE2
			NULL != (start = strstr(version_friendly, version_str)) ||
#	endif
			NULL != (start = strstr(version_friendly, release_str)))
	{
		size_t	next_start_index;

		next_start_index = start - version_friendly + strlen(release_str); /* same length for version_str */

		if (5 != sscanf(version_friendly + next_start_index, "%d.%d.%d.%d.%d", &major_release_version,
				&release_update_version, &release_update_version_revision, &increment_version,
				&reserved_for_future_use) || major_release_version >= 100 ||
				major_release_version <= 0 || release_update_version >= 100 ||
				release_update_version < 0 || release_update_version_revision >= 100 ||
				release_update_version_revision < 0 || increment_version >= 100 ||
				increment_version < 0)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Unexpected Oracle DB version format: %s", version_friendly);
			overall_status = FAIL;
		}
	}
	else
	{
		zabbix_log(LOG_LEVEL_WARNING, "Cannot find Release keyword in Oracle DB version.");
		overall_status = FAIL;
	}
out:
	if (FAIL == overall_status)
	{
		zabbix_log(LOG_LEVEL_WARNING, "Failed to detect OracleDB version");
		ZBX_ORACLE_SVERSION = ZBX_DBVERSION_UNDEFINED;
	}
	else
	{
		ZBX_ORACLE_SVERSION = major_release_version * 100000000 + release_update_version * 1000000 +
				release_update_version_revision * 10000 + increment_version * 100 +
				reserved_for_future_use;
#	ifndef HAVE_OCI_SERVER_RELEASE2
		if (18 <= major_release_version)
		{
			zabbix_log(LOG_LEVEL_WARNING, "Unable to determine the accurate Oracle DB version "
					"(possibly there is a DB driver - DB version mismatch, "
					"only the major Oracle DB version can be established): %s", version_friendly);
		}
#	endif
	}

	version_info->database = "Oracle";

	version_info->current_version = ZBX_ORACLE_SVERSION;
	version_info->min_version = ZBX_ORACLE_MIN_VERSION;
	version_info->max_version = ZBX_ORACLE_MAX_VERSION;
	version_info->min_supported_version = ZBX_ORACLE_MIN_SUPPORTED_VERSION;

	version_info->friendly_current_version = zbx_strdup(NULL, version_friendly);
	version_info->friendly_min_version = ZBX_ORACLE_MIN_VERSION_STR;
	version_info->friendly_max_version = ZBX_ORACLE_MAX_VERSION_STR;
	version_info->friendly_min_supported_version = ZBX_ORACLE_MIN_SUPPORTED_VERSION_STR;

	version_info->flag = zbx_db_version_check(version_info->database, version_info->current_version,
			version_info->min_version, version_info->max_version, version_info->min_supported_version);

#else
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);
	version_info->flag = DB_VERSION_SUPPORTED;
	version_info->friendly_current_version = NULL;
#endif
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s() version:%lu", __func__, (unsigned long)zbx_dbms_version_get());
}

#ifdef HAVE_POSTGRESQL
static int	zbx_tsdb_table_has_compressed_chunks(const char *table_names)
{
	zbx_db_result_t	result;
	int		ret;

	result = zbx_db_select_basic("select null from timescaledb_information.chunks"
			" where hypertable_name in (%s) and is_compressed='t'", table_names);

	if ((zbx_db_result_t)ZBX_DB_DOWN == result)
	{
		ret = FAIL;
		goto out;
	}

	if (NULL != zbx_db_fetch_basic(result))
		ret = SUCCEED;
	else
		ret = FAIL;
out:
	zbx_db_free_result(result);

	return ret;
}

void	zbx_tsdb_extract_compressed_chunk_flags(struct zbx_db_version_info_t *version_info)
{
#define ZBX_TSDB_HISTORY_TABLES "'history_bin','history_uint','history_log','history_str','history_text','history'"
#define ZBX_TSDB_TRENDS_TABLES "'trends','trends_uint'"

	version_info->history_compressed_chunks =
			(SUCCEED == zbx_tsdb_table_has_compressed_chunks(ZBX_TSDB_HISTORY_TABLES)) ? 1 : 0;

	version_info->trends_compressed_chunks =
			(SUCCEED == zbx_tsdb_table_has_compressed_chunks(ZBX_TSDB_TRENDS_TABLES)) ? 1 : 0;

#undef ZBX_TSDB_HISTORY_TABLES
#undef ZBX_TSDB_TRENDS_TABLES
}

/***************************************************************************************************************
 *                                                                                                             *
 * Purpose: retrieves TimescaleDB extension info, including license string and numeric version value           *
 *                                                                                                             *
 **************************************************************************************************************/
void	zbx_tsdb_info_extract(struct zbx_db_version_info_t *version_info)
{
	int	tsdb_ver;

	if (0 != zbx_strcmp_null(version_info->extension, ZBX_DB_EXTENSION_TIMESCALEDB))
		return;

	tsdb_ver = zbx_tsdb_get_version();

	version_info->ext_current_version = (zbx_uint32_t)tsdb_ver;
	version_info->ext_min_version = ZBX_TIMESCALE_MIN_VERSION;
	version_info->ext_max_version = ZBX_TIMESCALE_MAX_VERSION;
	version_info->ext_min_supported_version = ZBX_TIMESCALE_MIN_SUPPORTED_VERSION;

	version_info->ext_friendly_current_version = zbx_dsprintf(NULL, "%d.%d.%d", RIGHT2(tsdb_ver/10000),
			RIGHT2(tsdb_ver/100), RIGHT2(tsdb_ver));

	version_info->ext_friendly_min_version = ZBX_TIMESCALE_MIN_VERSION_STR;
	version_info->ext_friendly_max_version = ZBX_TIMESCALE_MAX_VERSION_STR;
	version_info->ext_friendly_min_supported_version = ZBX_TIMESCALE_MIN_SUPPORTED_VERSION_STR;

	version_info->ext_flag = zbx_db_version_check(version_info->extension, version_info->ext_current_version,
			version_info->ext_min_version, version_info->ext_max_version,
			version_info->ext_min_supported_version);

	zabbix_log(LOG_LEVEL_DEBUG, "TimescaleDB version: [%d]", tsdb_ver);
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
	int		ver, major, minor, patch;
	zbx_db_result_t	result;
	zbx_db_row_t	row;

	if (-1 == ZBX_TSDB_VERSION)
	{
		/* catalog pg_extension not available */
		if (90001 > ZBX_PG_SVERSION)
		{
			ver = ZBX_TSDB_VERSION = 0;
			goto out;
		}

		result = zbx_db_select_basic("select extversion from pg_extension where extname = 'timescaledb'");

		/* database down, can re-query in the next call */
		if ((zbx_db_result_t)ZBX_DB_DOWN == result)
		{
			ver = 0;
			goto out;
		}

		/* extension is not installed */
		if (NULL == result)
		{
			ver = ZBX_TSDB_VERSION = 0;
			goto out;
		}

		if (NULL != (row = zbx_db_fetch_basic(result)) &&
				3 == sscanf((const char*)row[0], "%d.%d.%d", &major, &minor, &patch))
		{
			ver = major * 10000;
			ver += minor * 100;
			ver += patch;
			ZBX_TSDB_VERSION = ver;
		}
		else
			ver = ZBX_TSDB_VERSION = 0;

		zbx_db_free_result(result);
	}
	else
		ver = ZBX_TSDB_VERSION;
out:
	return ver;
}

/******************************************************************************
 *                                                                            *
 * Purpose: sets TimescaleDB (TSDB) compression availability                  *
 *                                                                            *
 * Parameters:  compression_availabile - [IN] compression availability        *
 *              0 (OFF): compression is not available                         *
 *              1 (ON): compression is available                              *
 *                                                                            *
 ******************************************************************************/
void	zbx_tsdb_set_compression_availability(int compression_availabile)
{
	ZBX_TIMESCALE_COMPRESSION_AVAILABLE = compression_availabile;
}

/******************************************************************************
 *                                                                            *
 * Purpose: retrieves TimescaleDB (TSDB) compression availability             *
 *                                                                            *
 * Return value: compression availability as as integer                       *
 *               0 (OFF): compression is not available                        *
 *               1 (ON): compression is available                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_tsdb_get_compression_availability(void)
{
	return ZBX_TIMESCALE_COMPRESSION_AVAILABLE;
}

/******************************************************************************
 *                                                                            *
 * Purpose: clear last error code                                             *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_clear_last_errcode(void)
{
	last_db_errcode = 0;
}

#endif
