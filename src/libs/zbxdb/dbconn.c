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

#include "dbconn.h"
#include "zbxcommon.h"
#include "zbxdb.h"
#include "zbxthreads.h"
#include "zbxalgo.h"
#include "zbxnum.h"
#include "zbxstr.h"
#include "zbxtime.h"

#if defined(HAVE_POSTGRESQL)
#	define ZBX_PG_READ_ONLY	"25006"
#	define ZBX_PG_UNIQUE_VIOLATION	"23505"
#	define ZBX_PG_DEADLOCK		"40P01"
#endif

struct zbx_db_result
{
#if defined(HAVE_MYSQL)
	MYSQL_RES	*result;
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

static const zbx_db_config_t	*db_config = NULL;

#if defined(HAVE_POSTGRESQL)
static 	ZBX_THREAD_LOCAL char	ZBX_PG_ESCAPE_BACKSLASH = 1;
#elif defined(HAVE_SQLITE3)
static zbx_mutex_t		db_sqlite_access = ZBX_MUTEX_NULL;
#endif

#define ZBX_DB_WAIT_DOWN	10

static int	dbconn_execute(zbx_dbconn_t *db, const char *fmt, ...);
static zbx_db_result_t	dbconn_select(zbx_dbconn_t *db, const char *fmt, ...);
static int	dbconn_open(zbx_dbconn_t *db);
static void	dbconn_errlog(zbx_dbconn_t *db, zbx_err_codes_t zbx_errno, int db_errno, const char *db_error,
		const char *context);

/*
 * Private API
 */

int	dbconn_init(char **error)
{
#if defined(HAVE_SQLITE3)
	zbx_stat_t	buf;

	if (SUCCEED != zbx_mutex_create(&db_sqlite_access, ZBX_MUTEX_SQLITE3, error))
		return FAIL;

	if (0 != zbx_stat(db_config->dbname, &buf))
	{
		zbx_dbconn_t	*db;
		int		ret;

		zabbix_log(LOG_LEVEL_WARNING, "cannot open database file \"%s\": %s", db_config->dbname,
				zbx_strerror(errno));
		zabbix_log(LOG_LEVEL_WARNING, "creating database ...");

		db = zbx_dbconn_create();

		if (ZBX_DB_OK != (ret = dbconn_open(db)))
		{
			if (ZBX_DB_FAIL == ret)
				dbconn_errlog(db, ERR_Z3002, 0, sqlite3_errmsg(db->conn), db_config->dbname);

			*error = zbx_strdup(*error, "cannot open database");
			ret = FAIL;
		}
		else
		{
			zbx_dbconn_execute(db, "%s", zbx_dbschema_get_schema());
			zbx_dbconn_close(db);
		}

		zbx_dbconn_free(db);

		return (ZBX_DB_OK == ret ? SUCCEED : FAIL);
	}
#else
	ZBX_UNUSED(error);
#endif
	return SUCCEED;
}

void	dbconn_deinit(void)
{
#if defined(HAVE_SQLITE3)
	zbx_mutex_destroy(&db_sqlite_access);
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: replace non-printable characters in SQL query for logging         *
 *                                                                            *
 ******************************************************************************/
static char	*db_replace_nonprintable_chars(const char *sql, char **sql_printable)
{
	if (NULL == *sql_printable)
	{
		*sql_printable = zbx_strdup(NULL, sql);
		zbx_replace_invalid_utf8_and_nonprintable(*sql_printable);
	}

	return *sql_printable;
}

static void	dbconn_errlog(zbx_dbconn_t *db, zbx_err_codes_t zbx_errno, int db_errno, const char *db_error,
		const char *context)
{
	char	*s;

	db->last_db_errcode = zbx_errno;

	if (NULL != db_error)
		db->last_db_strerror = zbx_strdup(db->last_db_strerror, db_error);
	else
		db->last_db_strerror = zbx_strdup(db->last_db_strerror, "");

	switch (zbx_errno)
	{
		case ERR_Z3001:
			s = zbx_dsprintf(NULL, "connection to database '%s' failed: [%d] %s", context, db_errno,
					db->last_db_strerror);
			break;
		case ERR_Z3002:
			s = zbx_dsprintf(NULL, "cannot create database '%s': [%d] %s", context, db_errno,
					db->last_db_strerror);
			break;
		case ERR_Z3003:
			s = zbx_strdup(NULL, "no connection to the database");
			break;
		case ERR_Z3004:
			s = zbx_dsprintf(NULL, "cannot close database: [%d] %s", db_errno, db->last_db_strerror);
			break;
		case ERR_Z3005:
			s = zbx_dsprintf(NULL, "query failed: [%d] %s [%s]", db_errno, db->last_db_strerror, context);
			break;
		case ERR_Z3006:
			s = zbx_dsprintf(NULL, "fetch failed: [%d] %s", db_errno, db->last_db_strerror);
			break;
		case ERR_Z3007:
			s = zbx_dsprintf(NULL, "query failed: [%d] %s", db_errno, db->last_db_strerror);
			break;
		case ERR_Z3008:
			s = zbx_dsprintf(NULL, "query failed due to primary key constraint: [%d] %s", db_errno,
					db->last_db_strerror);
			break;
		case ERR_Z3009:
			s = zbx_dsprintf(NULL, "query failed due to read-only transaction: [%d] %s", db_errno,
					db->last_db_strerror);
			break;
		default:
			s = zbx_strdup(NULL, "unknown error");
	}

	zabbix_log(LOG_LEVEL_ERR, "[Z%04d] %s", (int)zbx_errno, s);

	zbx_free(s);
}

#if defined(HAVE_MYSQL)

static int	dbconn_is_recoverable_error(zbx_dbconn_t *db, int err_no)
{
	if (0 == err_no)
		err_no = (int)mysql_errno(db->conn);

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

static int	dbconn_is_inhibited_error(zbx_dbconn_t *db, int err_no)
{
	if (1 < db->error_count)
		return FAIL;

	if (0 < db->txn_level && 0 == db->txn_begin)
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

static int	dbconn_is_recoverable_error(zbx_dbconn_t *db, const PGresult *pg_result)
{
	if (CONNECTION_OK != PQstatus(db->conn))
		return SUCCEED;

	if (0 == zbx_strcmp_null(PQresultErrorField(pg_result, PG_DIAG_SQLSTATE), ZBX_PG_DEADLOCK))
		return SUCCEED;

	if (1 == db->config->read_only_recoverable &&
			0 == zbx_strcmp_null(PQresultErrorField(pg_result, PG_DIAG_SQLSTATE), ZBX_PG_READ_ONLY))
	{
		return SUCCEED;
	}

	return FAIL;
}

static void	db_get_postgresql_error(char **error, const PGresult *pg_result)
{
	char	*result_error_msg;
	size_t	error_alloc = 0, error_offset = 0;

	zbx_snprintf_alloc(error, &error_alloc, &error_offset, "%s", PQresStatus(PQresultStatus(pg_result)));

	result_error_msg = PQresultErrorMessage(pg_result);

	if ('\0' != *result_error_msg)
		zbx_snprintf_alloc(error, &error_alloc, &error_offset, ":%s", result_error_msg);
}

#endif

/******************************************************************************
 *                                                                            *
 * Purpose: close database connection                                         *
 *                                                                            *
 ******************************************************************************/
static void	dbconn_close(zbx_dbconn_t *db)
{
#if defined(HAVE_MYSQL)
	if (NULL != db->conn)
	{
		mysql_close(db->conn);
		db->conn = NULL;
	}
#elif defined(HAVE_POSTGRESQL)
	if (NULL != db->conn)
	{
		PQfinish(db->conn);
		db->conn = NULL;
	}
#elif defined(HAVE_SQLITE3)
	if (NULL != db->conn)
	{
		sqlite3_close(db->conn);
		db->conn = NULL;
	}
#endif
}

/******************************************************************************
 *                                                                            *
 * Purpose: open database connection                                          *
 *                                                                            *
 * Return value: ZBX_DB_OK - successfully connected                           *
 *               ZBX_DB_DOWN - database is down                               *
 *               ZBX_DB_FAIL - failed to connect                              *
 *                                                                            *
 ******************************************************************************/
static int	dbconn_open(zbx_dbconn_t *db)
{
	int		ret = ZBX_DB_OK, last_txn_error, last_txn_level;
#if defined(HAVE_MYSQL)
	int		err_no = 0;
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
	if (0 != db->txn_level)
		db->txn_error = ZBX_DB_DOWN;

	last_txn_error = db->txn_error;
	last_txn_level = db->txn_level;

	db->txn_error = ZBX_DB_OK;
	db->txn_level = 0;

#if defined(HAVE_MYSQL)
	if (NULL == (db->conn = mysql_init(NULL)))
	{
		zabbix_log(LOG_LEVEL_CRIT, "cannot allocate or initialize MYSQL database connection object");
		exit(EXIT_FAILURE);
	}

	if (1 == db->autoincrement)
	{
		/* Shadow global auto_increment variables. */
		/* Setting session variables requires special permissions in MySQL 8.0.14-8.0.17. */

		if (0 != MYSQL_OPTIONS(db->conn, MYSQL_INIT_COMMAND, MYSQL_OPTIONS_ARGS_VOID_CAST
				"set @@session.auto_increment_increment=1"))
		{
			zabbix_log(LOG_LEVEL_ERR, "Cannot set auto_increment_increment option.");
			ret = ZBX_DB_FAIL;
		}

		if (ZBX_DB_OK == ret && 0 != MYSQL_OPTIONS(db->conn, MYSQL_INIT_COMMAND, MYSQL_OPTIONS_ARGS_VOID_CAST
				"set @@session.auto_increment_offset=1"))
		{
			zabbix_log(LOG_LEVEL_ERR, "Cannot set auto_increment_offset option.");
			ret = ZBX_DB_FAIL;
		}
	}

#if defined(HAVE_MYSQL_TLS)
	if (ZBX_DB_OK == ret && NULL != db->config->db_tls_connect)
	{
		unsigned int	mysql_tls_mode;

		if (0 == strcmp(db->config->db_tls_connect, ZBX_DB_TLS_CONNECT_REQUIRED_TXT))
			mysql_tls_mode = SSL_MODE_REQUIRED;
		else if (0 == strcmp(db->config->db_tls_connect, ZBX_DB_TLS_CONNECT_VERIFY_CA_TXT))
			mysql_tls_mode = SSL_MODE_VERIFY_CA;
		else
			mysql_tls_mode = SSL_MODE_VERIFY_IDENTITY;

		if (0 != mysql_options(db->conn, MYSQL_OPT_SSL_MODE, &mysql_tls_mode))
		{
			zabbix_log(LOG_LEVEL_ERR, "Cannot set MYSQL_OPT_SSL_MODE option.");
			ret = ZBX_DB_FAIL;
		}
	}

	if (ZBX_DB_OK == ret && NULL != db->config->db_tls_ca_file && 0 != mysql_options(db->conn, MYSQL_OPT_SSL_CA,
			db->config->db_tls_ca_file))
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot set MYSQL_OPT_SSL_CA option.");
		ret = ZBX_DB_FAIL;
	}

	if (ZBX_DB_OK == ret && NULL != db->config->db_tls_key_file && 0 != mysql_options(db->conn, MYSQL_OPT_SSL_KEY,
			db->config->db_tls_key_file))
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot set MYSQL_OPT_SSL_KEY option.");
		ret = ZBX_DB_FAIL;
	}

	if (ZBX_DB_OK == ret && NULL != db->config->db_tls_cert_file && 0 != mysql_options(db->conn, MYSQL_OPT_SSL_CERT,
			db->config->db_tls_cert_file))
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot set MYSQL_OPT_SSL_CERT option.");
		ret = ZBX_DB_FAIL;
	}

	if (ZBX_DB_OK == ret && NULL != db->config->db_tls_cipher && 0 != mysql_options(db->conn, MYSQL_OPT_SSL_CIPHER,
			db->config->db_tls_cipher))
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot set MYSQL_OPT_SSL_CIPHER option.");
		ret = ZBX_DB_FAIL;
	}
#if defined(HAVE_MYSQL_TLS_CIPHERSUITES)
	if (ZBX_DB_OK == ret && NULL != db->config->db_tls_cipher_13 && 0 != mysql_options(db->conn,
			MYSQL_OPT_TLS_CIPHERSUITES, db->config->db_tls_cipher_13))
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot set MYSQL_OPT_TLS_CIPHERSUITES option.");
		ret = ZBX_DB_FAIL;
	}
#endif
#elif defined(HAVE_MARIADB_TLS)
	if (ZBX_DB_OK == ret && NULL != db->config->db_tls_connect)
	{
		if (0 == strcmp(db->config->db_tls_connect, ZBX_DB_TLS_CONNECT_REQUIRED_TXT))
		{
			my_bool	enforce_tls = 1;

			if (0 != mysql_optionsv(db->conn, MYSQL_OPT_SSL_ENFORCE, (void *)&enforce_tls))
			{
				zabbix_log(LOG_LEVEL_ERR, "Cannot set MYSQL_OPT_SSL_ENFORCE option.");
				ret = ZBX_DB_FAIL;
			}
		}
		else
		{
			my_bool	verify = 1;

			if (0 != mysql_optionsv(db->conn, MYSQL_OPT_SSL_VERIFY_SERVER_CERT, (void *)&verify))
			{
				zabbix_log(LOG_LEVEL_ERR, "Cannot set MYSQL_OPT_SSL_VERIFY_SERVER_CERT option.");
				ret = ZBX_DB_FAIL;
			}
		}
	}

	if (ZBX_DB_OK == ret && NULL != db->config->db_tls_ca_file && 0 != mysql_optionsv(db->conn, MYSQL_OPT_SSL_CA,
			db->config->db_tls_ca_file))
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot set MYSQL_OPT_SSL_CA option.");
		ret = ZBX_DB_FAIL;
	}

	if (ZBX_DB_OK == ret && NULL != db->config->db_tls_key_file && 0 != mysql_optionsv(db->conn, MYSQL_OPT_SSL_KEY,
			db->config->db_tls_key_file))
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot set MYSQL_OPT_SSL_KEY option.");
		ret = ZBX_DB_FAIL;
	}

	if (ZBX_DB_OK == ret && NULL != db->config->db_tls_cert_file && 0 != mysql_optionsv(db->conn, MYSQL_OPT_SSL_CERT,
			db->config->db_tls_cert_file))
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot set MYSQL_OPT_SSL_CERT option.");
		ret = ZBX_DB_FAIL;
	}

	if (ZBX_DB_OK == ret && NULL != db->config->db_tls_cipher && 0 != mysql_optionsv(db->conn, MYSQL_OPT_SSL_CIPHER,
			db->config->db_tls_cipher))
	{
		zabbix_log(LOG_LEVEL_ERR, "Cannot set MYSQL_OPT_SSL_CIPHER option.");
		ret = ZBX_DB_FAIL;
	}
#endif
	if (ZBX_DB_OK == ret && NULL == mysql_real_connect(db->conn, db->config->dbhost, db->config->dbuser,
			db->config->dbpassword, db->config->dbname, db->config->dbport, db->config->dbsocket,
			CLIENT_MULTI_STATEMENTS))
	{
		err_no = (int)mysql_errno(db->conn);
		dbconn_errlog(db, ERR_Z3001, err_no, mysql_error(db->conn), db->config->dbname);
		ret = ZBX_DB_FAIL;
	}

	if (ZBX_DB_OK == ret)
	{
		/* in contrast to "set names utf8" results of this call will survive auto-reconnects */
		/* utf8mb3 is deprecated and it's superset utf8mb4 should be used instead if available */
		if (0 != mysql_set_character_set(db->conn, ZBX_SUPPORTED_DB_CHARACTER_SET_UTF8MB4) &&
				0 != mysql_set_character_set(db->conn, ZBX_SUPPORTED_DB_CHARACTER_SET_UTF8) &&
				0 != mysql_set_character_set(db->conn, ZBX_SUPPORTED_DB_CHARACTER_SET_UTF8MB3))
		{
			zabbix_log(LOG_LEVEL_WARNING, "cannot set MySQL character set");
		}
	}

	if (ZBX_DB_OK == ret && 0 != mysql_autocommit(db->conn, 1))
	{
		err_no = (int)mysql_errno(db->conn);
		dbconn_errlog(db, ERR_Z3001, err_no, mysql_error(db->conn), db->config->dbname);
		ret = ZBX_DB_FAIL;
	}

	if (ZBX_DB_OK == ret && 0 != mysql_select_db(db->conn, db->config->dbname))
	{
		err_no = (int)mysql_errno(db->conn);
		dbconn_errlog(db, ERR_Z3001, err_no, mysql_error(db->conn), db->config->dbname);
		ret = ZBX_DB_FAIL;
	}

	if (ZBX_DB_FAIL == ret && SUCCEED == dbconn_is_recoverable_error(db, err_no))
		ret = ZBX_DB_DOWN;

	db->error_count = ZBX_DB_OK == ret ? 0 : db->error_count + 1;
#elif defined(HAVE_POSTGRESQL)
	if (NULL != db->config->db_tls_connect)
	{
		keywords[i] = "sslmode";

		if (0 == strcmp(db->config->db_tls_connect, ZBX_DB_TLS_CONNECT_REQUIRED_TXT))
			values[i++] = "require";
		else if (0 == strcmp(db->config->db_tls_connect, ZBX_DB_TLS_CONNECT_VERIFY_CA_TXT))
			values[i++] = "verify-ca";
		else
			values[i++] = "verify-full";
	}

	if (NULL != db->config->db_tls_cert_file)
	{
		keywords[i] = "sslcert";
		values[i++] = db->config->db_tls_cert_file;
	}

	if (NULL != db->config->db_tls_key_file)
	{
		keywords[i] = "sslkey";
		values[i++] = db->config->db_tls_key_file;
	}

	if (NULL != db->config->db_tls_ca_file)
	{
		keywords[i] = "sslrootcert";
		values[i++] = db->config->db_tls_ca_file;
	}

	if (NULL != db->config->dbhost)
	{
		keywords[i] = "host";
		values[i++] = db->config->dbhost;
	}

	if (NULL != db->config->dbname)
	{
		keywords[i] = "dbname";
		values[i++] = db->config->dbname;
	}

	if (NULL != db->config->dbuser)
	{
		keywords[i] = "user";
		values[i++] = db->config->dbuser;
	}

	if (NULL != db->config->dbpassword)
	{
		keywords[i] = "password";
		values[i++] = db->config->dbpassword;
	}

	if (0 != db->config->dbport)
	{
		keywords[i] = "port";
		values[i++] = cport = zbx_dsprintf(cport, "%u", db->config->dbport);
	}

	keywords[i] = NULL;
	values[i] = NULL;

	db->conn = PQconnectdbParams(keywords, values, 0);

	zbx_free(cport);

	/* check to see that the backend connection was successfully made */
	if (CONNECTION_OK != PQstatus(db->conn))
	{
		dbconn_errlog(db, ERR_Z3001, 0, PQerrorMessage(db->conn), db->config->dbname);
		ret = ZBX_DB_DOWN;
		goto out;
	}

	if (NULL != db->config->dbschema && '\0' != *db->config->dbschema)
	{
		char	*dbschema_esc;

		dbschema_esc = db_dyn_escape_string(db->config->dbschema, ZBX_SIZE_T_MAX, ZBX_SIZE_T_MAX,
				ESCAPE_SEQUENCE_ON);
		if (ZBX_DB_DOWN == (rc = dbconn_execute(db, "set schema '%s'", dbschema_esc)) || ZBX_DB_FAIL == rc)
			ret = rc;
		zbx_free(dbschema_esc);
	}

	if (ZBX_DB_FAIL == ret || ZBX_DB_DOWN == ret)
		goto out;

	/* disable "nonstandard use of \' in a string literal" warning */
	if (0 < (ret = dbconn_execute(db, "set escape_string_warning to off")))
		ret = ZBX_DB_OK;

	if (ZBX_DB_OK != ret)
		goto out;

	/* increase float precision */
	if (0 < (ret = dbconn_execute(db, "set extra_float_digits to 3")))
		ret = ZBX_DB_OK;

	if (ZBX_DB_OK != ret)
		goto out;

	result = dbconn_select(db, "show standard_conforming_strings");

	if ((zbx_db_result_t)ZBX_DB_DOWN == result || NULL == result)
	{
		ret = (NULL == result) ? ZBX_DB_FAIL : ZBX_DB_DOWN;
		goto out;
	}

	if (NULL != (row = zbx_db_fetch(result)))
		ZBX_PG_ESCAPE_BACKSLASH = (0 == strcmp(row[0], "off"));

	zbx_db_free_result(result);

	if (90000 <= db_get_server_version())
	{
		/* change the output format for values of type bytea from hex (the default) to escape */
		if (0 < (ret = dbconn_execute(db, "set bytea_output=escape")))
			ret = ZBX_DB_OK;
	}
out:
#elif defined(HAVE_SQLITE3)
#ifdef HAVE_FUNCTION_SQLITE3_OPEN_V2
	if (SQLITE_OK != sqlite3_open_v2(db->config->dbname, &db->conn, SQLITE_OPEN_READWRITE |
			SQLITE_OPEN_CREATE, NULL))
#else
	if (SQLITE_OK != sqlite3_open(db->config->dbname, &db->conn))
#endif
	{
		dbconn_errlog(db, ERR_Z3001, 0, sqlite3_errmsg(db->conn), db->config->dbname);
		ret = ZBX_DB_DOWN;
		goto out;
	}

	/* do not return SQLITE_BUSY immediately, wait for N ms */
	sqlite3_busy_timeout(db->conn, SEC_PER_MIN * 1000);

	if (0 < (ret = dbconn_execute(db, "pragma synchronous=0")))
		ret = ZBX_DB_OK;

	if (ZBX_DB_OK != ret)
		goto out;

	if (0 < (ret = dbconn_execute(db, "pragma foreign_keys=on")))
		ret = ZBX_DB_OK;

	if (ZBX_DB_OK != ret)
		goto out;

	if (0 < (ret = dbconn_execute(db, "pragma temp_store=2")))
		ret = ZBX_DB_OK;

	if (ZBX_DB_OK != ret)
		goto out;

	path = zbx_strdup(NULL, db->config->dbname);

	if (NULL != (p = strrchr(path, '/')))
		*++p = '\0';
	else
		*path = '\0';

	if (0 < (ret = dbconn_execute(db, "pragma temp_store_directory='%s'", path)))
		ret = ZBX_DB_OK;

	zbx_free(path);
out:
#endif	/* HAVE_SQLITE3 */
	if (ZBX_DB_OK != ret)
		dbconn_close(db);

	db->txn_error = last_txn_error;
	db->txn_level = last_txn_level;

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: set connection as managed by connection pool                      *
 *                                                                            *
 ******************************************************************************/
void	dbconn_set_managed(zbx_dbconn_t *db)
{
	db->managed = DBCONN_TYPE_MANAGED;
}

int	db_is_escape_sequence(char c)
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
 * Purpose: Execute SQL statement. For non-select statements only.            *
 *                                                                            *
 * Return value: ZBX_DB_FAIL (on error) or ZBX_DB_DOWN (on recoverable error) *
 *               or number of rows affected (on success)                      *
 *                                                                            *
 ******************************************************************************/
static int	dbconn_vexecute(zbx_dbconn_t *db, const char *fmt, va_list args)
{
	char		*sql = NULL, *sql_printable = NULL;
	int		ret = ZBX_DB_OK;
	double		sec = 0;
#if defined(HAVE_POSTGRESQL)
	PGresult	*result;
	char		*error = NULL;
#elif defined(HAVE_SQLITE3)
	int		err;
	char		*error = NULL;
#endif

	if (0 != db->config->log_slow_queries)
		sec = zbx_time();

	sql = zbx_dvsprintf(sql, fmt, args);

	if (0 == db->txn_level)
		zabbix_log(LOG_LEVEL_DEBUG, "query without transaction detected");

	if (ZBX_DB_OK != db->txn_error)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "ignoring query [txnlev:%d] [%s] within failed transaction", db->txn_level,
				db_replace_nonprintable_chars(sql, &sql_printable));
		ret = ZBX_DB_FAIL;
		goto clean;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "query [txnlev:%d] [%s]", db->txn_level,
			db_replace_nonprintable_chars(sql, &sql_printable));

#if defined(HAVE_MYSQL)
	if (NULL == db->conn)
	{
		dbconn_errlog(db, ERR_Z3003, 0, NULL, NULL);
		ret = ZBX_DB_FAIL;
	}
	else
	{
		zbx_err_codes_t	errcode;
		int		err_no;

		if (0 != mysql_query(db->conn, sql))
		{
			err_no = (int)mysql_errno(db->conn);
			errcode = (ER_DUP_ENTRY == err_no ? ERR_Z3008 : ERR_Z3005);
			db->error_count++;

			if (FAIL == dbconn_is_inhibited_error(db, err_no))
				dbconn_errlog(db, errcode, err_no, mysql_error(db->conn), sql);

			ret = (SUCCEED == dbconn_is_recoverable_error(db, err_no) ? ZBX_DB_DOWN : ZBX_DB_FAIL);
		}
		else
		{
			int	status;

			do
			{
				if (0 != mysql_field_count(db->conn))
				{
					zabbix_log(LOG_LEVEL_DEBUG, "cannot retrieve result set");
					break;
				}

				ret += (int)mysql_affected_rows(db->conn);

				/* more results? 0 = yes (keep looping), -1 = no, >0 = error */
				if (0 < (status = mysql_next_result(db->conn)))
				{
					err_no = (int)mysql_errno(db->conn);
					errcode = (ER_DUP_ENTRY == err_no ? ERR_Z3008 : ERR_Z3005);
					db->error_count++;

					if (FAIL == dbconn_is_inhibited_error(db, err_no))
						dbconn_errlog(db, errcode, err_no, mysql_error(db->conn), sql);

					ret = (SUCCEED == dbconn_is_recoverable_error(db, err_no) ? ZBX_DB_DOWN : ZBX_DB_FAIL);
				}
			}
			while (0 == status);
		}
	}
#elif defined(HAVE_POSTGRESQL)
	result = PQexec(db->conn, sql);

	if (NULL == result)
	{
		dbconn_errlog(db, ERR_Z3005, 0, "result is NULL", sql);
		ret = (CONNECTION_OK == PQstatus(db->conn) ? ZBX_DB_FAIL : ZBX_DB_DOWN);
	}
	else if (PGRES_COMMAND_OK != PQresultStatus(result))
	{
		zbx_err_codes_t	errcode;

		db_get_postgresql_error(&error, result);

		if (0 == zbx_strcmp_null(PQresultErrorField(result, PG_DIAG_SQLSTATE), ZBX_PG_UNIQUE_VIOLATION))
			errcode = ERR_Z3008;
		else if (0 == zbx_strcmp_null(PQresultErrorField(result, PG_DIAG_SQLSTATE), ZBX_PG_READ_ONLY))
			errcode = ERR_Z3009;
		else
			errcode = ERR_Z3005;

		dbconn_errlog(db, errcode, 0, error, sql);
		zbx_free(error);

		ret = (SUCCEED == dbconn_is_recoverable_error(db, result) ? ZBX_DB_DOWN : ZBX_DB_FAIL);
	}

	if (ZBX_DB_OK == ret)
		ret = atoi(PQcmdTuples(result));

	PQclear(result);
#elif defined(HAVE_SQLITE3)
	if (0 == db->txn_level)
		zbx_mutex_lock(*db->sqlite_access);

lbl_exec:
	if (SQLITE_OK != (err = sqlite3_exec(db->conn, sql, NULL, 0, &error)))
	{
		if (SQLITE_BUSY == err)
			goto lbl_exec;

		dbconn_errlog(db, ERR_Z3005, 0, error, sql);
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
		ret = sqlite3_changes(db->conn);

	if (0 == db->txn_level)
		zbx_mutex_unlock(*db->sqlite_access);
#endif	/* HAVE_SQLITE3 */

	if (0 != db->config->log_slow_queries)
	{
		sec = zbx_time() - sec;
		if (sec > (double)db->config->log_slow_queries / 1000.0)
		{
			zabbix_log(LOG_LEVEL_WARNING, "slow query: " ZBX_FS_DBL " sec, \"%s\"", sec,
					db_replace_nonprintable_chars(sql, &sql_printable));
		}
	}

	if (ZBX_DB_FAIL == ret && 0 < db->txn_level)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "query [%s] failed, setting transaction as failed",
				db_replace_nonprintable_chars(sql, &sql_printable));
		db->txn_error = ZBX_DB_FAIL;
	}
clean:
	zbx_free(sql_printable);
	zbx_free(sql);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: Execute SQL statement. For non-select statements only.            *
 *                                                                            *
 * Return value: ZBX_DB_FAIL (on error) or ZBX_DB_DOWN (on recoverable error) *
 *               or number of rows affected (on success)                      *
 *                                                                            *
 ******************************************************************************/
__zbx_attr_format_printf(2, 3)
static int	dbconn_execute(zbx_dbconn_t *db, const char *fmt, ...)
{
	va_list	args;
	int	ret;

	va_start(args, fmt);
	ret = dbconn_vexecute(db, fmt, args);
	va_end(args);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: start transaction                                                 *
 *                                                                            *
 * Comments: do nothing if DB does not support transactions                   *
 *                                                                            *
 ******************************************************************************/
static int	dbconn_begin(zbx_dbconn_t *db)
{
	int	rc = ZBX_DB_OK;

	if (db->txn_level > 0)
	{
		zabbix_log(LOG_LEVEL_CRIT, "ERROR: nested transaction detected. Please report it to Zabbix Team.");
		zbx_this_should_never_happen_backtrace();
		assert(0);
	}

#if defined(HAVE_MYSQL)
	db->txn_begin = 1;
#endif
	db->txn_level++;

#if defined(HAVE_MYSQL) || defined(HAVE_POSTGRESQL)
	rc = dbconn_execute(db, "begin;");
#elif defined(HAVE_SQLITE3)
	zbx_mutex_lock(*db->sqlite_access);
	rc = dbconn_execute(db, "begin;");
#endif

	if (ZBX_DB_DOWN == rc)
		db->txn_level--;

#if defined(HAVE_MYSQL)
	db->txn_begin = 0;
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
static int	dbconn_commit(zbx_dbconn_t *db)
{
	int	rc = ZBX_DB_OK;

	if (0 == db->txn_level)
	{
		zabbix_log(LOG_LEVEL_CRIT, "ERROR: commit without transaction."
				" Please report it to Zabbix Team.");
		zbx_this_should_never_happen_backtrace();
		assert(0);
	}

	if (ZBX_DB_OK != db->txn_error)
		return ZBX_DB_FAIL; /* commit called on failed transaction */

	rc = dbconn_execute(db, "commit;");

#ifdef HAVE_SQLITE3
	zbx_mutex_unlock(*db->sqlite_access);
#endif
	if (ZBX_DB_OK > rc)	/* commit failed */
	{
		db->txn_error = rc;
		return rc;
	}

	db->txn_level--;
	db->txn_end_error = ZBX_DB_OK;

	return rc;
}

/******************************************************************************
 *                                                                            *
 * Purpose: rollback transaction                                              *
 *                                                                            *
 * Comments: do nothing if DB does not support transactions                   *
 *                                                                            *
 ******************************************************************************/
static int	dbconn_rollback(zbx_dbconn_t *db)
{
	int	rc = ZBX_DB_OK, last_txn_error;

	if (0 == db->txn_level)
	{
		zabbix_log(LOG_LEVEL_CRIT, "ERROR: rollback without transaction."
				" Please report it to Zabbix Team.");
		zbx_this_should_never_happen_backtrace();
		assert(0);
	}

	last_txn_error = db->txn_error;

	/* allow rollback of failed transaction */
	db->txn_error = ZBX_DB_OK;

	rc = dbconn_execute(db, "rollback;");

#if defined(HAVE_SQLITE3)
	zbx_mutex_unlock(*db->sqlite_access);
#endif

	/* There is no way to recover from rollback errors, so there is no need to preserve transaction level / error. */
	db->txn_level = 0;
	db->txn_error = ZBX_DB_OK;

	if (ZBX_DB_FAIL == rc)
		db->txn_end_error = ZBX_DB_FAIL;
	else
		db->txn_end_error = last_txn_error;	/* error that caused rollback */

	return rc;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute a select statement                                        *
 *                                                                            *
 * Return value: data, NULL (on error) or (zbx_db_result_t)ZBX_DB_DOWN        *
 *                                                                            *
 ******************************************************************************/
static zbx_db_result_t	dbconn_vselect(zbx_dbconn_t *db, const char *fmt, va_list args)
{
	char		*sql = NULL;
	zbx_db_result_t	result = NULL;
	double		sec = 0;
#if defined(HAVE_POSTGRESQL)
	char		*error = NULL;
#elif defined(HAVE_SQLITE3)
	int		ret = FAIL;
	char		*error = NULL;
#endif

	if (0 != db->config->log_slow_queries)
		sec = zbx_time();

	sql = zbx_dvsprintf(sql, fmt, args);

	if (ZBX_DB_OK != db->txn_error)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "ignoring query [txnlev:%d] [%s] within failed transaction", db->txn_level,
				sql);
		goto clean;
	}

	zabbix_log(LOG_LEVEL_DEBUG, "query [txnlev:%d] [%s]", db->txn_level, sql);

#if defined(HAVE_MYSQL)
	result = (zbx_db_result_t)zbx_malloc(NULL, sizeof(struct zbx_db_result));
	result->result = NULL;

	if (NULL == db->conn)
	{
		dbconn_errlog(db, ERR_Z3003, 0, NULL, NULL);

		zbx_db_free_result(result);
		result = NULL;
	}
	else
	{
		if (0 != mysql_query(db->conn, sql) || NULL == (result->result = mysql_store_result(db->conn)))
		{
			int err_no = (int)mysql_errno(db->conn);

			db->error_count++;

			if (FAIL == dbconn_is_inhibited_error(db, err_no))
				dbconn_errlog(db, ERR_Z3005, err_no, mysql_error(db->conn), sql);

			zbx_db_free_result(result);
			result = (SUCCEED == dbconn_is_recoverable_error(db, err_no) ? (zbx_db_result_t)ZBX_DB_DOWN : NULL);
		}
	}
#elif defined(HAVE_POSTGRESQL)
	result = zbx_malloc(NULL, sizeof(struct zbx_db_result));
	result->pg_result = PQexec(db->conn, sql);
	result->values = NULL;
	result->cursor = 0;
	result->row_num = 0;

	if (NULL == result->pg_result)
		dbconn_errlog(db, ERR_Z3005, 0, "result is NULL", sql);

	if (PGRES_TUPLES_OK != PQresultStatus(result->pg_result))
	{
		db_get_postgresql_error(&error, result->pg_result);
		dbconn_errlog(db, ERR_Z3005, 0, error, sql);
		zbx_free(error);

		if (SUCCEED == dbconn_is_recoverable_error(db, result->pg_result))
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
	if (0 == db->txn_level)
		zbx_mutex_lock(*db->sqlite_access);

	result = zbx_malloc(NULL, sizeof(struct zbx_db_result));
	result->curow = 0;

lbl_get_table:
	if (SQLITE_OK != (ret = sqlite3_get_table(db->conn, sql, &result->data, &result->nrow, &result->ncolumn,
			&error)))
	{
		if (SQLITE_BUSY == ret)
			goto lbl_get_table;

		dbconn_errlog(db, ERR_Z3005, 0, error, sql);
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

	if (0 == db->txn_level)
		zbx_mutex_unlock(*db->sqlite_access);
#endif	/* HAVE_SQLITE3 */
	if (0 != db->config->log_slow_queries)
	{
		sec = zbx_time() - sec;
		if (sec > (double)db->config->log_slow_queries / 1000.0)
			zabbix_log(LOG_LEVEL_WARNING, "slow query: " ZBX_FS_DBL " sec, \"%s\"", sec, sql);
	}

	if (NULL == result && 0 < db->txn_level)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "query [%s] failed, setting transaction as failed", sql);
		db->txn_error = ZBX_DB_FAIL;
	}
clean:
	zbx_free(sql);

	return result;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute a select statement                                        *
 *                                                                            *
 * Return value: data, NULL (on error) or (zbx_db_result_t)ZBX_DB_DOWN        *
 *                                                                            *
 ******************************************************************************/
__zbx_attr_format_printf(2, 3)
static zbx_db_result_t	dbconn_select(zbx_dbconn_t *db, const char *fmt, ...)
{
	va_list		args;
	zbx_db_result_t	ret;

	va_start(args, fmt);
	ret = dbconn_vselect(db, fmt, args);
	va_end(args);

	return ret;
}

static zbx_db_result_t	dbconn_select_n(zbx_dbconn_t *db, const char *query, int n)
{
	return dbconn_select(db, "%s limit %d", query, n);
}

/*
 * Public API
 */

/******************************************************************************
 *                                                                            *
 * Purpose: set connection options                                            *
 *                                                                            *
 * Parameters: db      - [IN]                                                 *
 *             options - [IN] connection options:                             *
 *                               ZBX_DB_CONNECT_NORMAL (retry on failure)     *
 *                               ZBX_DB_CONNECT_EXIT (exit on failure)        *
 *                               ZBX_DB_CONNECT_ONCE (return on failure)      *
 *                                                                            *
 *                                                                            *
 * Return value: old connection options                                       *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbconn_set_connect_options(zbx_dbconn_t *db, int options)
{
	if (0 != db->managed)
	{
		THIS_SHOULD_NEVER_HAPPEN_MSG("Cannot change connection options for managed connections");
		return ZBX_DB_CONNECT_NORMAL;
	}

	int	old_options = db->connect_options;

	db->connect_options = options;

	return old_options;
}

/******************************************************************************
 *                                                                            *
 * Purpose: set autoincrement                                                 *
 *                                                                            *
 ******************************************************************************/
void	zbx_dbconn_set_autoincrement(zbx_dbconn_t *db, int options)
{
	db->autoincrement = options;
}

/******************************************************************************
 *                                                                            *
 * Purpose: create database connection object                                 *
 *                                                                            *
 ******************************************************************************/
zbx_dbconn_t	*zbx_dbconn_create(void)
{
	zbx_dbconn_t	*db;

	db = (zbx_dbconn_t *)zbx_malloc(NULL, sizeof(zbx_dbconn_t));
	memset(db, 0, sizeof(zbx_dbconn_t));

	db->managed = DBCONN_TYPE_UNMANAGED;
	db->config = db_config;
	db->txn_error = ZBX_DB_OK;
	db->txn_end_error = ZBX_DB_OK;
	db->connect_options = ZBX_DB_CONNECT_NORMAL;

#if defined(HAVE_SQLITE3)
	db->sqlite_access = &db_sqlite_access;
#endif
	return db;
}

/******************************************************************************
 *                                                                            *
 * Purpose: free database connection object                                   *
 *                                                                            *
 ******************************************************************************/
void	zbx_dbconn_free(zbx_dbconn_t *db)
{
	dbconn_close(db);
	zbx_free(db->last_db_strerror);

	zbx_free(db);
}

/******************************************************************************
 *                                                                            *
 * Purpose: open database connection                                          *
 *                                                                            *
 * Return value: ZBX_DB_OK - successfully connected                           *
 *               ZBX_DB_DOWN - database is down                               *
 *               ZBX_DB_FAIL - failed to connect                              *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbconn_open(zbx_dbconn_t *db)
{
	int	err;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() options:%d", __func__, db->connect_options);

	if (DBCONN_TYPE_MANAGED == db->managed)
	{
		THIS_SHOULD_NEVER_HAPPEN_MSG("Cannot open managed connections");
		err = ZBX_DB_FAIL;

		goto out;
	}

	while (ZBX_DB_OK != (err = dbconn_open(db)))
	{
		if (ZBX_DB_CONNECT_ONCE == db->connect_options)
			break;

		if (ZBX_DB_FAIL == err || ZBX_DB_CONNECT_EXIT == db->connect_options)
		{
			zabbix_log(LOG_LEVEL_CRIT, "Cannot connect to the database. Exiting...");
			exit(EXIT_FAILURE);
		}

		zabbix_log(LOG_LEVEL_ERR, "database is down: reconnecting in %d seconds", ZBX_DB_WAIT_DOWN);
		db->connection_failure = 1;
		zbx_sleep(ZBX_DB_WAIT_DOWN);
	}

	if (0 != db->connection_failure)
	{
		zabbix_log(LOG_LEVEL_ERR, "database connection re-established");
		db->connection_failure = 0;
	}
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%d", __func__, err);

	return err;
}

/******************************************************************************
 *                                                                            *
 * Purpose: close database connection                                         *
 *                                                                            *
 ******************************************************************************/
void 	zbx_dbconn_close(zbx_dbconn_t *db)
{
	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __func__);

	if (DBCONN_TYPE_MANAGED == db->managed)
		THIS_SHOULD_NEVER_HAPPEN_MSG("Cannot close managed connections");
	else
		dbconn_close(db);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __func__);
}

/******************************************************************************
 *                                                                            *
 * Purpose: start transaction                                                 *
 *                                                                            *
 * Comments: do nothing if DB does not support transactions                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbconn_begin(zbx_dbconn_t *db)
{
	int	rc;

	rc = dbconn_begin(db);

	if (ZBX_DB_CONNECT_NORMAL != db->connect_options)
		return rc;

	while (ZBX_DB_DOWN == rc)
	{
		zbx_dbconn_close(db);
		zbx_dbconn_open(db);

		if (ZBX_DB_DOWN == (rc = dbconn_begin(db)))
		{
			zabbix_log(LOG_LEVEL_ERR, "database is down: retrying in %d seconds", ZBX_DB_WAIT_DOWN);
			db->connection_failure = 1;
			sleep(ZBX_DB_WAIT_DOWN);
		}
	}

	return rc;
}

/******************************************************************************
 *                                                                            *
 * Purpose: commit transaction                                                *
 *                                                                            *
 * Comments: do nothing if DB does not support transactions                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbconn_commit(zbx_dbconn_t *db)
{
	if (ZBX_DB_OK > dbconn_commit(db))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "commit called on failed transaction, doing a rollback instead");
		zbx_dbconn_rollback(db);
	}

	return db->txn_end_error;
}

/******************************************************************************
 *                                                                            *
 * Purpose: rollback transaction                                              *
 *                                                                            *
 * Comments: do nothing if DB does not support transactions                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbconn_rollback(zbx_dbconn_t *db)
{
	int	rc;

	rc = dbconn_rollback(db);

	if (ZBX_DB_CONNECT_NORMAL != db->connect_options)
		return rc;

	if (ZBX_DB_OK > rc)
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot perform transaction rollback, connection will be reset");

		zbx_dbconn_close(db);
		rc = zbx_dbconn_open(db);
	}
	else
	{
		if (ZBX_DB_DOWN == db->txn_end_error && ERR_Z3009 == db->last_db_errcode)
		{
			zabbix_log(LOG_LEVEL_ERR, "database is read-only: waiting for %d seconds", ZBX_DB_WAIT_DOWN);
			sleep(ZBX_DB_WAIT_DOWN);
		}
	}

	return rc;
}

/******************************************************************************
 *                                                                            *
 * Purpose: commit or rollback a transaction depending on a parameter value   *
 *                                                                            *
 * Comments: do nothing if DB does not support transactions                   *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbconn_end(zbx_dbconn_t *db, int ret)
{
	if (SUCCEED == ret)
		return ZBX_DB_OK == zbx_dbconn_commit(db) ? SUCCEED : FAIL;

	zbx_dbconn_rollback(db);

	return FAIL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute a non-select statement                                    *
 *                                                                            *
 * Comments: retry until DB is up                                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbconn_vexecute(zbx_dbconn_t *db, const char *fmt, va_list args)
{
	int	rc;

	rc = dbconn_vexecute(db, fmt, args);

	if (ZBX_DB_CONNECT_NORMAL != db->connect_options)
		return rc;

	while (ZBX_DB_DOWN == rc)
	{
		zbx_dbconn_close(db);
		zbx_dbconn_open(db);

		if (ZBX_DB_DOWN == (rc = dbconn_vexecute(db, fmt, args)))
		{
			zabbix_log(LOG_LEVEL_ERR, "database is down: retrying in %d seconds", ZBX_DB_WAIT_DOWN);
			db->connection_failure = 1;
			sleep(ZBX_DB_WAIT_DOWN);
		}
	}

	return rc;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute a non-select statement                                    *
 *                                                                            *
 * Comments: retry until DB is up                                             *
 *                                                                            *
 ******************************************************************************/
__zbx_attr_format_printf(2, 3)
int	zbx_dbconn_execute(zbx_dbconn_t *db, const char *fmt, ...)
{
	va_list	args;
	int	rc;

	va_start(args, fmt);
	rc = zbx_dbconn_vexecute(db, fmt, args);
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
zbx_db_result_t	__zbx_attr_weak zbx_dbconn_vselect(zbx_dbconn_t *db, const char *fmt, va_list args)
{
	zbx_db_result_t	rc;

	rc = dbconn_vselect(db, fmt, args);

	if (ZBX_DB_CONNECT_NORMAL != db->connect_options)
		return rc;

	while ((zbx_db_result_t)ZBX_DB_DOWN == rc)
	{
		zbx_dbconn_close(db);
		zbx_dbconn_open(db);

		if ((zbx_db_result_t)ZBX_DB_DOWN == (rc = dbconn_vselect(db, fmt, args)))
		{
			zabbix_log(LOG_LEVEL_ERR, "database is down: retrying in %d seconds", ZBX_DB_WAIT_DOWN);
			db->connection_failure = 1;
			sleep(ZBX_DB_WAIT_DOWN);
		}
	}

	return rc;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute a select statement                                        *
 *                                                                            *
 * Comments: retry until DB is up                                             *
 *                                                                            *
 ******************************************************************************/
__zbx_attr_format_printf(2, 3)
zbx_db_result_t	zbx_dbconn_select(zbx_dbconn_t *db, const char *fmt, ...)
{
	va_list		args;
	zbx_db_result_t	rc;

	va_start(args, fmt);
	rc = zbx_dbconn_vselect(db, fmt, args);
	va_end(args);

	return rc;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute a select statement and get the first N entries            *
 *                                                                            *
 * Comments: retry until DB is up                                             *
 *                                                                            *
 ******************************************************************************/
zbx_db_result_t	zbx_dbconn_select_n(zbx_dbconn_t *db, const char *query, int n)
{
	zbx_db_result_t	rc;

	rc = dbconn_select_n(db, query, n);

	if (ZBX_DB_CONNECT_NORMAL != db->connect_options)
		return rc;

	while ((zbx_db_result_t)ZBX_DB_DOWN == rc)
	{
		zbx_dbconn_close(db);
		zbx_dbconn_open(db);

		if ((zbx_db_result_t)ZBX_DB_DOWN == (rc = dbconn_select_n(db, query, n)))
		{
			zabbix_log(LOG_LEVEL_ERR, "database is down: retrying in %d seconds", ZBX_DB_WAIT_DOWN);
			db->connection_failure = 1;
			sleep(ZBX_DB_WAIT_DOWN);
		}
	}

	return rc;
}

/******************************************************************************
 *                                                                            *
 * Purpose: fetch database row from result returned from select               *
 *                                                                            *
 ******************************************************************************/
zbx_db_row_t	__zbx_attr_weak zbx_db_fetch(zbx_db_result_t result)
{
	if (NULL == result)
		return NULL;

#if defined(HAVE_MYSQL)
	if (NULL == result->result)
		return NULL;

	return (zbx_db_row_t)mysql_fetch_row(result->result);
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

/******************************************************************************
 *                                                                            *
 * Purpose: free result returned from database select                         *
 *                                                                            *
 ******************************************************************************/
void	__zbx_attr_weak zbx_db_free_result(zbx_db_result_t result)
{
#if defined(HAVE_MYSQL)
	if (NULL == result)
		return;

	mysql_free_result(result->result);
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

/******************************************************************************
 *                                                                            *
 * Purpose: get last error set by database                                    *
 *                                                                            *
 * Return value: last database error message                                  *
 *                                                                            *
 ******************************************************************************/
const char	*zbx_dbconn_last_strerr(zbx_dbconn_t *db)
{
	return db->last_db_strerror;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get last error code returned by database                          *
 *                                                                            *
 * Return value: last database error code                                     *
 *                                                                            *
 ******************************************************************************/
zbx_err_codes_t	zbx_dbconn_last_errcode(zbx_dbconn_t *db)
{
	return db->last_db_errcode;
}

/******************************************************************************
 *                                                                            *
 * Purpose: initialize database library                                       *
 *                                                                            *
 ******************************************************************************/
void	zbx_init_library_db(zbx_db_config_t *config)
{
	db_config = config;
}

/******************************************************************************
 *                                                                            *
 * Purpose: get number of row in result set                                   *
 *                                                                            *
 ******************************************************************************/
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

#if defined(HAVE_MYSQL) || defined(HAVE_POSTGRESQL)
static void	warn_char_set(const char *db_name, const char *char_set)
{
	zabbix_log(LOG_LEVEL_WARNING, "Zabbix supports only \"" ZBX_SUPPORTED_DB_CHARACTER_SET "\" character set(s)."
			" Database \"%s\" has default character set \"%s\"", db_name, char_set);
}

static void	warn_no_charset_info(const char *db_name)
{
	zabbix_log(LOG_LEVEL_WARNING, "Cannot get database \"%s\" character set", db_name);
}
#endif

#if defined(HAVE_MYSQL)
/******************************************************************************
 *                                                                            *
 * Purpose: convert string list with custom delimiter to a string list with   *
 *           quoted strings separated with ','                                *
 *                                                                            *
 * Return value: quoted string list, must be freed by caller                  *
 *                                                                            *
 ******************************************************************************/
static char	*db_strlist_quote(const char *strlist, char delimiter)
{
	const char	*delim;
	char		*str = NULL;
	size_t		str_alloc = 0, str_offset = 0;

	while (NULL != (delim = strchr(strlist, delimiter)))
	{
		zbx_snprintf_alloc(&str, &str_alloc, &str_offset, "'%.*s',", (int)(delim - strlist), strlist);
		strlist = delim + 1;
	}

	zbx_snprintf_alloc(&str, &str_alloc, &str_offset, "'%s'", strlist);

	return str;
}
#endif

void	zbx_db_check_character_set(void)
{
#if defined(HAVE_MYSQL)
	char		*database_name_esc, *charset_list, *collation_list;
	zbx_db_result_t	result;
	zbx_db_row_t	row;

	database_name_esc = zbx_db_dyn_escape_string(db_config->dbname);

	result = zbx_db_select(
			"select default_character_set_name,default_collation_name"
			" from information_schema.SCHEMATA"
			" where schema_name='%s'", database_name_esc);

	if (NULL == result || NULL == (row = zbx_db_fetch(result)))
	{
		warn_no_charset_info(db_config->dbname);
	}
	else
	{
		char	*char_set = row[0];
		char	*collation = row[1];

		if (FAIL == zbx_str_in_list(ZBX_SUPPORTED_DB_CHARACTER_SET, char_set, ZBX_DB_STRLIST_DELIM))
			warn_char_set(db_config->dbname, char_set);

		if (SUCCEED != zbx_str_in_list(ZBX_SUPPORTED_DB_COLLATION, collation, ZBX_DB_STRLIST_DELIM))
		{
			zabbix_log(LOG_LEVEL_WARNING, "Zabbix supports only \"%s\" collation(s)."
					" Database \"%s\" has default collation \"%s\"", ZBX_SUPPORTED_DB_COLLATION,
					db_config->dbname, collation);
		}
	}

	zbx_db_free_result(result);

	charset_list = db_strlist_quote(ZBX_SUPPORTED_DB_CHARACTER_SET, ZBX_DB_STRLIST_DELIM);
	collation_list = db_strlist_quote(ZBX_SUPPORTED_DB_COLLATION, ZBX_DB_STRLIST_DELIM);

	result = zbx_db_select(
			"select count(*)"
			" from information_schema.`COLUMNS`"
			" where table_schema='%s'"
				" and data_type in ('text','varchar','longtext')"
				" and (character_set_name not in (%s) or collation_name not in (%s))",
			database_name_esc, charset_list, collation_list);

	zbx_free(collation_list);
	zbx_free(charset_list);

	if (NULL == result || NULL == (row = zbx_db_fetch(result)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot get character set of database \"%s\" tables",
				db_config->dbname);
	}
	else if (0 != strcmp("0", row[0]))
	{
		zabbix_log(LOG_LEVEL_WARNING, "character set name or collation name that is not supported by Zabbix"
				" found in %s column(s) of database \"%s\"", row[0], db_config->dbname);
		zabbix_log(LOG_LEVEL_WARNING, "only character set(s) \"%s\" and corresponding collation(s) \"%s\""
				" should be used in database", ZBX_SUPPORTED_DB_CHARACTER_SET,
				ZBX_SUPPORTED_DB_COLLATION);
	}

	zbx_db_free_result(result);
	zbx_free(database_name_esc);
#elif defined(HAVE_POSTGRESQL)
#define OID_LENGTH_MAX		20

	char		*database_name_esc, oid[OID_LENGTH_MAX];
	zbx_db_result_t	result;
	zbx_db_row_t	row;

	database_name_esc = zbx_db_dyn_escape_string(db_config->dbname);

	result = zbx_db_select(
			"select pg_encoding_to_char(encoding)"
			" from pg_database"
			" where datname='%s'",
			database_name_esc);

	if (NULL == result || NULL == (row = zbx_db_fetch(result)))
	{
		warn_no_charset_info(db_config->dbname);
		goto out;
	}
	else if (strcasecmp(row[0], ZBX_SUPPORTED_DB_CHARACTER_SET))
	{
		warn_char_set(db_config->dbname, row[0]);
		goto out;

	}

	zbx_db_free_result(result);

	result = zbx_db_select(
			"select oid"
			" from pg_namespace"
			" where nspname='%s'",
			zbx_db_get_schema_esc());

	if (NULL == result || NULL == (row = zbx_db_fetch(result)) || '\0' == **row)
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot get character set of database \"%s\" fields",
				db_config->dbname);
		goto out;
	}

	zbx_strscpy(oid, *row);

	zbx_db_free_result(result);

	result = zbx_db_select(
			"select count(*)"
			" from pg_attribute as a"
				" left join pg_class as c"
					" on c.relfilenode=a.attrelid"
				" left join pg_collation as l"
					" on l.oid=a.attcollation"
			" where atttypid in (25,1043)"
				" and c.relnamespace=%s"
				" and c.relam=0"
				" and l.collname<>'default'",
			oid);

	if (NULL == result || NULL == (row = zbx_db_fetch(result)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot get character set of database \"%s\" fields",
				db_config->dbname);
	}
	else if (0 != strcmp("0", row[0]))
	{
		zabbix_log(LOG_LEVEL_WARNING, "database has %s fields with unsupported character set. Zabbix supports"
				" only \"%s\" character set", row[0], ZBX_SUPPORTED_DB_CHARACTER_SET);
	}

	zbx_db_free_result(result);

	result = zbx_db_select("show client_encoding");

	if (NULL == result || NULL == (row = zbx_db_fetch(result)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot get info about database \"%s\" client encoding",
				db_config->dbname);
	}
	else if (0 != strcasecmp(row[0], ZBX_SUPPORTED_DB_CHARACTER_SET))
	{
		zabbix_log(LOG_LEVEL_WARNING, "client_encoding for database \"%s\" is \"%s\". Zabbix supports only"
				" \"%s\"", db_config->dbname, row[0], ZBX_SUPPORTED_DB_CHARACTER_SET);
	}

	zbx_db_free_result(result);

	result = zbx_db_select("show server_encoding");

	if (NULL == result || NULL == (row = zbx_db_fetch(result)))
	{
		zabbix_log(LOG_LEVEL_WARNING, "cannot get info about database \"%s\" server encoding",
				db_config->dbname);
	}
	else if (0 != strcasecmp(row[0], ZBX_SUPPORTED_DB_CHARACTER_SET))
	{
		zabbix_log(LOG_LEVEL_WARNING, "server_encoding for database \"%s\" is \"%s\". Zabbix supports only"
				" \"%s\"", db_config->dbname, row[0], ZBX_SUPPORTED_DB_CHARACTER_SET);
	}
out:
	zbx_db_free_result(result);
	zbx_free(database_name_esc);
#endif
}

#if defined(HAVE_POSTGRESQL)
/******************************************************************************
 *                                                                            *
 * Purpose: returns escaped DB schema name                                    *
 *                                                                            *
 ******************************************************************************/
char	*zbx_db_get_schema_esc(void)
{
	static char	*name;

	if (NULL == name)
	{
		name = zbx_db_dyn_escape_string(NULL == db_config->dbschema ||
				'\0' == *db_config->dbschema ? "public" : db_config->dbschema);
	}

	return name;
}
#endif

/******************************************************************************
 *                                                                            *
 * Purpose: check if table exists                                             *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbconn_table_exists(zbx_dbconn_t *db, const char *table_name)
{
	char		*table_name_esc;
	zbx_db_result_t	result;
	int		ret;

	table_name_esc = zbx_db_dyn_escape_string(table_name);

#if defined(HAVE_MYSQL)
	result = zbx_dbconn_select(db, "show tables like '%s'", table_name_esc);
#elif defined(HAVE_POSTGRESQL)
	result = zbx_dbconn_select(db,
			"select 1"
			" from information_schema.tables"
			" where table_name='%s'"
				" and table_schema='%s'",
			table_name_esc, zbx_db_get_schema_esc());
#elif defined(HAVE_SQLITE3)
	result = zbx_dbconn_select(db,
			"select 1"
			" from sqlite_master"
			" where tbl_name='%s'"
				" and type='table'",
			table_name_esc);
#endif

	zbx_free(table_name_esc);

	ret = (NULL == zbx_db_fetch(result) ? FAIL : SUCCEED);

	zbx_db_free_result(result);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if table field exists                                       *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbconn_field_exists(zbx_dbconn_t *db, const char *table_name, const char *field_name)
{
#if (defined(HAVE_MYSQL) || defined(HAVE_POSTGRESQL) || defined(HAVE_SQLITE3))
	zbx_db_result_t	result;
#endif
#if defined(HAVE_MYSQL)
	char		*field_name_esc;
	int		ret;
#elif defined(HAVE_POSTGRESQL)
	char		*table_name_esc, *field_name_esc;
	int		ret;
#elif defined(HAVE_SQLITE3)
	char		*table_name_esc;
	zbx_db_row_t	row;
	int		ret = FAIL;
#endif

#if defined(HAVE_MYSQL)
	field_name_esc = zbx_db_dyn_escape_string(field_name);

	result = zbx_dbconn_select(db, "show columns from %s like '%s'",
			table_name, field_name_esc);

	zbx_free(field_name_esc);

	ret = (NULL == zbx_db_fetch(result) ? FAIL : SUCCEED);

	zbx_db_free_result(result);
#elif defined(HAVE_POSTGRESQL)
	table_name_esc = zbx_db_dyn_escape_string(table_name);
	field_name_esc = zbx_db_dyn_escape_string(field_name);

	result = zbx_dbconn_select(db,
			"select 1"
			" from information_schema.columns"
			" where table_name='%s'"
				" and column_name='%s'"
				" and table_schema='%s'",
			table_name_esc, field_name_esc, zbx_db_get_schema_esc());

	zbx_free(field_name_esc);
	zbx_free(table_name_esc);

	ret = (NULL == zbx_db_fetch(result) ? FAIL : SUCCEED);

	zbx_db_free_result(result);
#elif defined(HAVE_SQLITE3)
	table_name_esc = zbx_db_dyn_escape_string(table_name);

	result = zbx_dbconn_select(db, "PRAGMA table_info('%s')", table_name_esc);

	zbx_free(table_name_esc);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		if (0 != strcmp(field_name, row[1]))
			continue;

		ret = SUCCEED;
		break;
	}
	zbx_db_free_result(result);
#endif

	return ret;
}

#if !defined(HAVE_SQLITE3)
/******************************************************************************
 *                                                                            *
 * Purpose: check if table trigger exists                                     *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbconn_trigger_exists(zbx_dbconn_t *db, const char *table_name, const char *trigger_name)
{
	char		*table_name_esc, *trigger_name_esc;
	zbx_db_result_t	result;
	int		ret;

	table_name_esc = zbx_db_dyn_escape_string(table_name);
	trigger_name_esc = zbx_db_dyn_escape_string(trigger_name);

#if defined(HAVE_MYSQL)
	result = zbx_dbconn_select(db,
			"show triggers where `table`='%s'"
			" and `trigger`='%s'",
			table_name_esc, trigger_name_esc);
#elif defined(HAVE_POSTGRESQL)
	result = zbx_dbconn_select(db,
			"select 1"
			" from information_schema.triggers"
			" where event_object_table='%s'"
			" and trigger_name='%s'"
			" and trigger_schema='%s'",
			table_name_esc, trigger_name_esc, zbx_db_get_schema_esc());
#endif
	ret = (NULL == zbx_db_fetch(result) ? FAIL : SUCCEED);

	zbx_db_free_result(result);

	zbx_free(table_name_esc);
	zbx_free(trigger_name_esc);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if table index exists                                       *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbconn_index_exists(zbx_dbconn_t *db, const char *table_name, const char *index_name)
{
	char		*table_name_esc, *index_name_esc;
	zbx_db_result_t	result;
	int		ret;

	table_name_esc = zbx_db_dyn_escape_string(table_name);
	index_name_esc = zbx_db_dyn_escape_string(index_name);

#if defined(HAVE_MYSQL)
	result = zbx_dbconn_select(db,
			"show index from %s"
			" where key_name='%s'",
			table_name_esc, index_name_esc);
#elif defined(HAVE_POSTGRESQL)
	result = zbx_dbconn_select(db,
			"select 1"
			" from pg_indexes"
			" where tablename='%s'"
				" and indexname='%s'"
				" and schemaname='%s'",
			table_name_esc, index_name_esc, zbx_db_get_schema_esc());
#endif

	ret = (NULL == zbx_db_fetch(result) ? FAIL : SUCCEED);

	zbx_db_free_result(result);

	zbx_free(table_name_esc);
	zbx_free(index_name_esc);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: check if table primary key exists                                 *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbconn_pk_exists(zbx_dbconn_t *db, const char *table_name)
{
	zbx_db_result_t	result;
	int		ret;

#if defined(HAVE_MYSQL)
	result = zbx_dbconn_select(db,
			"show index from %s"
			" where key_name='PRIMARY'",
			table_name);
#elif defined(HAVE_POSTGRESQL)
	result = zbx_dbconn_select(db,
			"select 1"
			" from information_schema.table_constraints"
			" where table_name='%s'"
				" and constraint_type='PRIMARY KEY'"
				" and constraint_schema='%s'",
			table_name, zbx_db_get_schema_esc());
#endif
	ret = (NULL == zbx_db_fetch(result) ? FAIL : SUCCEED);

	zbx_db_free_result(result);

	return ret;
}

#endif /* !defined(HAVE_SQLITE3) */

/******************************************************************************
 *                                                                            *
 * Parameters: sql - [IN] sql statement                                       *
 *             ids - [OUT] sorted list of selected uint64 values              *
 *                                                                            *
 ******************************************************************************/
void	zbx_dbconn_select_uint64(zbx_dbconn_t *db, const char *sql, zbx_vector_uint64_t *ids)
{
	zbx_db_result_t	result;
	zbx_db_row_t	row;
	zbx_uint64_t	id;

	result = zbx_dbconn_select(db, "%s", sql);

	while (NULL != (row = zbx_db_fetch(result)))
	{
		ZBX_STR2UINT64(id, row[0]);

		zbx_vector_uint64_append(ids, id);
	}
	zbx_db_free_result(result);

	zbx_vector_uint64_sort(ids, ZBX_DEFAULT_UINT64_COMPARE_FUNC);
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute query with large number of primary key matches in smaller *
 *          batches (last batch is not executed)                              *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbconn_prepare_multiple_query(zbx_dbconn_t *db, const char *query, const char *field_name,
		zbx_vector_uint64_t *ids, char **sql, size_t *sql_alloc, size_t *sql_offset)
{
	int	i, ret = SUCCEED;

	for (i = 0; i < ids->values_num; i += ZBX_DB_LARGE_QUERY_BATCH_SIZE)
	{
		zbx_strcpy_alloc(sql, sql_alloc, sql_offset, query);
		zbx_db_add_condition_alloc(sql, sql_alloc, sql_offset, field_name, &ids->values[i],
				MIN(ZBX_DB_LARGE_QUERY_BATCH_SIZE, ids->values_num - i));
		zbx_strcpy_alloc(sql, sql_alloc, sql_offset, ";\n");

		if (SUCCEED != (ret = zbx_dbconn_execute_overflowed_sql(db, sql, sql_alloc, sql_offset)))
			break;
	}

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: execute query with large number of primary key matches in smaller *
 *          batches                                                           *
 *                                                                            *
 ******************************************************************************/
int	zbx_dbconn_execute_multiple_query(zbx_dbconn_t *db, const char *query, const char *field_name,
		zbx_vector_uint64_t *ids)
{
	char	*sql = NULL;
	size_t	sql_alloc = ZBX_KIBIBYTE, sql_offset = 0;
	int	ret = SUCCEED;

	sql = (char *)zbx_malloc(sql, sql_alloc);

	ret = zbx_dbconn_prepare_multiple_query(db, query, field_name, ids, &sql, &sql_alloc, &sql_offset);

	if (SUCCEED == ret && ZBX_DB_OK > zbx_dbconn_flush_overflowed_sql(db, sql, sql_offset))
		ret = FAIL;

	zbx_free(sql);

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Purpose: selects next batch of ids from database                           *
 *                                                                            *
 ******************************************************************************/
static int	db_large_query_select(zbx_db_large_query_t *query)
{
	int	values_num, size = ZBX_DB_LARGE_QUERY_BATCH_SIZE;

	switch (query->type)
	{
		case ZBX_DB_LARGE_QUERY_UI64:
			values_num = query->ids.ui64->values_num;
			break;
		case ZBX_DB_LARGE_QUERY_STR:
			values_num = query->ids.str->values_num;
			break;
	}

	if (query->offset >= values_num)
		return FAIL;

	if (NULL != query->result)
	{
		zbx_db_free_result(query->result);
		query->result = NULL;
	}

	if (query->offset + size > values_num)
		size = values_num - query->offset;

	*query->sql_offset = query->sql_reset;

	switch (query->type)
	{
		case ZBX_DB_LARGE_QUERY_UI64:
			zbx_db_add_condition_alloc(query->sql, query->sql_alloc, query->sql_offset, query->field,
					query->ids.ui64->values + query->offset, size);
			break;
		case ZBX_DB_LARGE_QUERY_STR:
			zbx_db_add_str_condition_alloc(query->sql, query->sql_alloc, query->sql_offset, query->field,
					(const char * const *)&query->ids.str->values[query->offset], size);
			break;
	}

	if (NULL != query->suffix)
		zbx_strcpy_alloc(query->sql, query->sql_alloc, query->sql_offset, query->suffix);

	query->offset += size;
	query->result = dbconn_select(query->db, "%s", *query->sql);

	return SUCCEED;
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepare large SQL query                                           *
 *                                                                            *
 ******************************************************************************/
static void	db_large_query_prepare(zbx_db_large_query_t *query, zbx_dbconn_t *db, char **sql, size_t *sql_alloc,
		size_t *sql_offset, const char *field)
{
	query->db = db;
	query->field = field;
	query->sql = sql;
	query->sql_alloc = sql_alloc;
	query->sql_offset = sql_offset;
	query->sql_reset = *sql_offset;
	query->offset = 0;
	query->result = NULL;
	query->suffix = NULL;
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepare large SQL query with uint based IDs                       *
 *                                                                            *
 * Parameters: query      - [IN] large query object                           *
 *             db         - [IN] database connection object                   *
 *             sql        - [IN/OUT] first part of the query, can be modified *
 *                              or reallocated                                *
 *             sql_alloc  - [IN/OUT] size of allocated sql string             *
 *             sql_offset - [IN/OUT] length of the sql string                 *
 *             field      - [IN] ID field name                                *
 *             ids        - [IN] vector of IDs                                *
 *                                                                            *
 * Comments: Large query object 'borrows' the sql buffer with the query part, *
 *           meaning:                                                         *
 *             - caller must not free/modify this sql buffer while the        *
 *               prepared large query object is being used                    *
 *             - caller must free this sql buffer afterwards - it's not freed *
 *               when large query object is cleared.                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_dbconn_large_query_prepare_uint(zbx_db_large_query_t *query, zbx_dbconn_t *db, char **sql,
		size_t *sql_alloc, size_t *sql_offset, const char *field, const zbx_vector_uint64_t *ids)
{
	query->type = ZBX_DB_LARGE_QUERY_UI64;
	query->ids.ui64 = ids;

	db_large_query_prepare(query, db, sql, sql_alloc, sql_offset, field);
}

/******************************************************************************
 *                                                                            *
 * Purpose: prepare large SQL query with string based IDs                     *
 *                                                                            *
 * Parameters: query      - [IN] large query object                           *
 *             db         - [IN] database connection object                   *
 *             sql        - [IN/OUT] first part of the query, can be modified *
 *                              or reallocated                                *
 *             sql_alloc  - [IN/OUT] size of allocated sql string             *
 *             sql_offset - [IN/OUT] length of the sql string                 *
 *             field      - [IN] ID field name                                *
 *             ids        - [IN] vector of IDs                                *
 *                                                                            *
 * Comments: Large query object 'borrows' the sql buffer with the query part, *
 *           meaning:                                                         *
 *             - caller must not free/modify this sql buffer while the        *
 *               prepared large query object is being used                    *
 *             - caller must free this sql buffer afterwards - it's not freed *
 *               when large query object is cleared.                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_dbconn_large_query_prepare_str(zbx_db_large_query_t *query, zbx_dbconn_t *db, char **sql, size_t *sql_alloc,
		size_t *sql_offset, const char *field, const zbx_vector_str_t *ids)
{
	query->type = ZBX_DB_LARGE_QUERY_STR;
	query->ids.str = ids;

	db_large_query_prepare(query, db, sql, sql_alloc, sql_offset, field);
}

/******************************************************************************
 *                                                                            *
 * Purpose: fetch next row from large SQL query                               *
 *                                                                            *
 * Parameters: query      - [IN] large query object                           *
 *                                                                            *
 * Return value: database row or NULL if all rows are fetched                 *
 *                                                                            *
 * Comments: When all rows of the current batch are fetched this function     *
 *           will automatically select next batch and fetch next row until    *
 *           all batches are fetched.                                         *
 *                                                                            *
 ******************************************************************************/
zbx_db_row_t	zbx_db_large_query_fetch(zbx_db_large_query_t *query)
{
	zbx_db_row_t	row;
	int		values_num;

	if (NULL == query->db)
		return NULL;

	switch (query->type)
	{
		case ZBX_DB_LARGE_QUERY_UI64:
			values_num = query->ids.ui64->values_num;
			break;
		case ZBX_DB_LARGE_QUERY_STR:
			values_num = query->ids.str->values_num;
			break;
	}

	while (NULL == (row = zbx_db_fetch(query->result)) && query->offset < values_num)
	{
		if (SUCCEED != db_large_query_select(query))
			return NULL;
	}

	return row;
}

/******************************************************************************
 *                                                                            *
 * Purpose: clear large SQL query                                             *
 *                                                                            *
 ******************************************************************************/
void	zbx_db_large_query_clear(zbx_db_large_query_t *query)
{
	zbx_db_free_result(query->result);
	zbx_free(query->suffix);
}

/******************************************************************************
 *                                                                            *
 * Purpose: set sql statement to be appended to each batch                    *
 *                                                                            *
 * Parameters: query      - [IN] large query object                           *
 *             sql        - [IN] sql statement to append                      *
 *                                                                            *
 ******************************************************************************/
void	zbx_dbconn_large_query_append_sql(zbx_db_large_query_t *query, const char *sql)
{
	query->suffix = zbx_strdup(NULL, sql);
}


