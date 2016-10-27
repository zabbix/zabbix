/*
** Zabbix
** Copyright (C) 2001-2016 Zabbix SIA
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
#include "log.h"

#ifdef HAVE_UNIXODBC

#include "zbxodbc.h"

#define CALLODBC(fun, rc, h_type, h, msg)	(SQL_SUCCESS != (rc = (fun)) && 0 == odbc_Diag((h_type), (h), rc, (msg)))

#define ODBC_ERR_MSG_LEN	255

static char	zbx_last_odbc_strerror[ODBC_ERR_MSG_LEN];

const char	*get_last_odbc_strerror(void)
{
	return zbx_last_odbc_strerror;
}

#ifdef HAVE___VA_ARGS__
#	define set_last_odbc_strerror(fmt, ...) __zbx_set_last_odbc_strerror(ZBX_CONST_STRING(fmt), ##__VA_ARGS__)
#else
#	define set_last_odbc_strerror __zbx_set_last_odbc_strerror
#endif
static void	__zbx_set_last_odbc_strerror(const char *fmt, ...)
{
	va_list	args;

	va_start(args, fmt);

	zbx_vsnprintf(zbx_last_odbc_strerror, sizeof(zbx_last_odbc_strerror), fmt, args);

	va_end(args);
}

#define clean_odbc_strerror()	zbx_last_odbc_strerror[0] = '\0'

static void	odbc_free_row_data(ZBX_ODBC_DBH *pdbh)
{
	SQLSMALLINT	i;

	if (NULL != pdbh->row_data)
	{
		for (i = 0; i < pdbh->col_num; i++)
			zbx_free(pdbh->row_data[i]);

		zbx_free(pdbh->row_data);
	}

	pdbh->col_num = 0;
}

static int	odbc_Diag(SQLSMALLINT h_type, SQLHANDLE h, SQLRETURN sql_rc, const char *msg)
{
	const char	*__function_name = "odbc_Diag";
	SQLCHAR		sql_state[SQL_SQLSTATE_SIZE + 1], err_msg[128];
	SQLINTEGER	native_err_code = 0;
	int		rec_nr = 1;
	char		rc_msg[40];		/* the longest message is "%d (unknown SQLRETURN code)" */
	char		diag_msg[ODBC_ERR_MSG_LEN];
	size_t		offset = 0;

	*sql_state = '\0';
	*err_msg = '\0';
	*diag_msg = '\0';

	switch (sql_rc)
	{
		case SQL_ERROR:
			zbx_strlcpy(rc_msg, "SQL_ERROR", sizeof(rc_msg));
			break;
		case SQL_SUCCESS_WITH_INFO:
			zbx_strlcpy(rc_msg, "SQL_SUCCESS_WITH_INFO", sizeof(rc_msg));
			break;
		case SQL_NO_DATA:
			zbx_strlcpy(rc_msg, "SQL_NO_DATA", sizeof(rc_msg));
			break;
		case SQL_INVALID_HANDLE:
			zbx_strlcpy(rc_msg, "SQL_INVALID_HANDLE", sizeof(rc_msg));
			break;
		case SQL_STILL_EXECUTING:
			zbx_strlcpy(rc_msg, "SQL_STILL_EXECUTING", sizeof(rc_msg));
			break;
		case SQL_NEED_DATA:
			zbx_strlcpy(rc_msg, "SQL_NEED_DATA", sizeof(rc_msg));
			break;
		case SQL_SUCCESS:
			zbx_strlcpy(rc_msg, "SQL_SUCCESS", sizeof(rc_msg));
			break;
		default:
			zbx_snprintf(rc_msg, sizeof(rc_msg), "%d (unknown SQLRETURN code)", (int)sql_rc);
	}

	if (SQL_ERROR == sql_rc || SQL_SUCCESS_WITH_INFO == sql_rc)
	{
		while (0 != SQL_SUCCEEDED(SQLGetDiagRec(h_type, h, (SQLSMALLINT)rec_nr, sql_state, &native_err_code,
				err_msg, sizeof(err_msg), NULL)))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "%s(): rc_msg:'%s' rec_nr:%d sql_state:'%s' native_err_code:%ld "
					"err_msg:'%s'", __function_name, rc_msg, rec_nr, sql_state,
					(long)native_err_code, err_msg);
			if (sizeof(diag_msg) > offset)
			{
				offset += zbx_snprintf(diag_msg + offset, sizeof(diag_msg) - offset, "[%s][%ld][%s]|",
						sql_state, (long)native_err_code, err_msg);
			}
			rec_nr++;
		}
		*(diag_msg + offset) = '\0';
	}
	set_last_odbc_strerror("%s:[%s]:%s", msg, rc_msg, diag_msg);

	return (int)SQL_SUCCEEDED(sql_rc);
}

void	odbc_DBclose(ZBX_ODBC_DBH *pdbh)
{
	if (NULL == pdbh)
		return;

	if (NULL != pdbh->hstmt)
	{
		SQLFreeHandle(SQL_HANDLE_STMT, pdbh->hstmt);
		pdbh->hstmt = NULL;
	}

	if (NULL != pdbh->hdbc)
	{
		if (pdbh->connected)
			SQLDisconnect(pdbh->hdbc);

		SQLFreeHandle(SQL_HANDLE_DBC, pdbh->hdbc);
		pdbh->hdbc = NULL;
	}

	if (NULL != pdbh->henv)
	{
		SQLFreeHandle(SQL_HANDLE_ENV, pdbh->henv);
		pdbh->henv = NULL;
	}

	odbc_free_row_data(pdbh);
}

int	odbc_DBconnect(ZBX_ODBC_DBH *pdbh, char *db_dsn, char *user, char *pass, int login_timeout)
{
	const char	*__function_name = "odbc_DBconnect";
	int		ret = FAIL;
	SQLRETURN	rc;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() db_dsn:'%s' user:'%s'", __function_name, db_dsn, user);

	clean_odbc_strerror();

	memset(pdbh, 0, sizeof(ZBX_ODBC_DBH));

	/* allocate environment handle */
	if (0 == SQL_SUCCEEDED(SQLAllocHandle(SQL_HANDLE_ENV, SQL_NULL_HANDLE, &(pdbh->henv))))
	{
		set_last_odbc_strerror("%s", "Cannot create ODBC environment handle.");
		goto end;
	}

	/* set the ODBC version environment attribute */
	if (0 != CALLODBC(SQLSetEnvAttr(pdbh->henv, SQL_ATTR_ODBC_VERSION, (void*)SQL_OV_ODBC3, 0), rc, SQL_HANDLE_ENV,
			pdbh->henv, "Cannot set ODBC version"))
	{
		goto end;
	}

	/* allocate connection handle */
	if (0 != CALLODBC(SQLAllocHandle(SQL_HANDLE_DBC, pdbh->henv, &(pdbh->hdbc)), rc, SQL_HANDLE_ENV, pdbh->henv,
			"Cannot create ODBC connection handle"))
	{
		goto end;
	}

	/* set login timeout */
	if (0 != CALLODBC(SQLSetConnectAttr(pdbh->hdbc, (SQLINTEGER)SQL_LOGIN_TIMEOUT,
			(SQLPOINTER)(intptr_t)login_timeout, (SQLINTEGER)0), rc, SQL_HANDLE_DBC, pdbh->hdbc,
			"Cannot set ODBC login timeout"))
	{
		goto end;
	}

	/* connect to data source */
	if (0 != CALLODBC(SQLConnect(pdbh->hdbc, (SQLCHAR *)db_dsn, SQL_NTS, (SQLCHAR *)user, SQL_NTS, (SQLCHAR *)pass,
			SQL_NTS), rc, SQL_HANDLE_DBC, pdbh->hdbc, "Cannot connect to ODBC DSN"))
	{
		goto end;
	}

	/* allocate statement handle */
	if (0 != CALLODBC(SQLAllocHandle(SQL_HANDLE_STMT, pdbh->hdbc, &(pdbh->hstmt)), rc, SQL_HANDLE_DBC, pdbh->hdbc,
			"Cannot create ODBC statement handle."))
	{
		goto end;
	}

	if (SUCCEED == zabbix_check_log_level(LOG_LEVEL_DEBUG))
	{
		char	driver_name[MAX_STRING_LEN + 1], driver_ver[MAX_STRING_LEN + 1], db_name[MAX_STRING_LEN + 1],
			db_ver[MAX_STRING_LEN + 1];

		if (0 != CALLODBC(SQLGetInfo(pdbh->hdbc, SQL_DRIVER_NAME, driver_name, MAX_STRING_LEN, NULL),
				rc, SQL_HANDLE_DBC, pdbh->hdbc, "Cannot obtain driver name"))
		{
			zbx_strlcpy(driver_name, "unknown", sizeof(driver_name));
		}

		if (0 != CALLODBC(SQLGetInfo(pdbh->hdbc, SQL_DRIVER_VER, driver_ver, MAX_STRING_LEN, NULL),
				rc, SQL_HANDLE_DBC, pdbh->hdbc, "Cannot obtain driver version"))
		{
			zbx_strlcpy(driver_ver, "unknown", sizeof(driver_ver));
		}

		if (0 != CALLODBC(SQLGetInfo(pdbh->hdbc, SQL_DBMS_NAME, db_name, MAX_STRING_LEN, NULL),
				rc, SQL_HANDLE_DBC, pdbh->hdbc, "Cannot obtain database name"))
		{
			zbx_strlcpy(db_name, "unknown", sizeof(db_name));
		}

		if (0 != CALLODBC(SQLGetInfo(pdbh->hdbc, SQL_DBMS_VER, db_ver, MAX_STRING_LEN, NULL),
				rc, SQL_HANDLE_DBC, pdbh->hdbc, "Cannot obtain database version"))
		{
			zbx_strlcpy(db_ver, "unknown", sizeof(db_ver));
		}

		zabbix_log(LOG_LEVEL_DEBUG, "%s() connected to %s(%s) using %s(%s)", __function_name,
				db_name, db_ver, driver_name, driver_ver);
	}

	pdbh->connected = 1;

	ret = SUCCEED;
end:
	if (SUCCEED != ret)
		odbc_DBclose(pdbh);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

ZBX_ODBC_ROW	odbc_DBfetch(ZBX_ODBC_RESULT pdbh)
{
	const char	*__function_name = "odbc_DBfetch";
	SQLRETURN	retcode;
	SQLSMALLINT	i;
	ZBX_ODBC_ROW	result_row = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	clean_odbc_strerror();

	if (NULL == pdbh)
	{
		set_last_odbc_strerror("cannot fetch row on an empty connection handle");
		goto end;
	}

	if (SQL_NO_DATA == (retcode = SQLFetch(pdbh->hstmt)))
	{
		/* end of rows */
		goto end;
	}

	if (SQL_SUCCESS != retcode && 0 == odbc_Diag(SQL_HANDLE_STMT, pdbh->hstmt, retcode, "cannot fetch row"))
		goto end;

	for (i = 0; i < pdbh->col_num; i++)
	{
		size_t		alloc = 0, offset = 0;
		char		buffer[MAX_STRING_LEN + 1];
		SQLLEN		len, col_type;
		SQLSMALLINT	c_type;

		zbx_free(pdbh->row_data[i]);

		if (0 != CALLODBC(SQLColAttribute(pdbh->hstmt, i + 1, SQL_DESC_TYPE, NULL, 0, NULL, &col_type),
				retcode, SQL_HANDLE_STMT, pdbh->hstmt, "Cannot get column type"))
		{
			goto end;
		}

		/* force col_type to integer value for DB2 compatibility */
		switch ((int)col_type)
		{
			case SQL_WLONGVARCHAR:
				c_type = SQL_C_BINARY;
				break;
			default:
				c_type = SQL_C_CHAR;
		}

		/* force len to integer value for DB2 compatibility */
		while (SQL_NO_DATA != (retcode = SQLGetData(pdbh->hstmt, i + 1, c_type, buffer, MAX_STRING_LEN, &len)))
		{
			if (0 == SQL_SUCCEEDED(retcode))
			{
				odbc_Diag(SQL_HANDLE_STMT, pdbh->hstmt, retcode, "Cannot get column data");
				goto end;
			}

			if (SQL_NULL_DATA == (int)len)
				break;

			buffer[(int)len] = '\0';

			zbx_strcpy_alloc(&pdbh->row_data[i], &alloc, &offset, buffer);
		}

		if (NULL != pdbh->row_data[i])
			zbx_rtrim(pdbh->row_data[i], " ");

		zabbix_log(LOG_LEVEL_DEBUG, "%s() fetched [%i col (%d)]: '%s'", __function_name, i, (int)col_type,
						NULL == pdbh->row_data[i] ? "NULL" : pdbh->row_data[i]);
	}

	result_row = pdbh->row_data;
end:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return result_row;
}

ZBX_ODBC_RESULT	odbc_DBselect(ZBX_ODBC_DBH *pdbh, char *query)
{
	const char	*__function_name = "odbc_DBselect";
	ZBX_ODBC_RESULT	result = NULL;
	SQLRETURN	rc;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() query:'%s'", __function_name, query);

	clean_odbc_strerror();

	odbc_free_row_data(pdbh);

	if (0 != CALLODBC(SQLExecDirect(pdbh->hstmt, (SQLCHAR *)query, SQL_NTS), rc, SQL_HANDLE_STMT, pdbh->hstmt,
			"Cannot execute ODBC query"))
	{
		goto end;
	}

	if (0 != CALLODBC(SQLNumResultCols(pdbh->hstmt, &pdbh->col_num), rc, SQL_HANDLE_STMT, pdbh->hstmt,
			"Cannot get number of columns in ODBC result"))
	{
		goto end;
	}

	pdbh->row_data = zbx_malloc(pdbh->row_data, sizeof(char *) * (size_t)pdbh->col_num);
	memset(pdbh->row_data, 0, sizeof(char *) * (size_t)pdbh->col_num);

	zabbix_log(LOG_LEVEL_DEBUG, "%s() selected %i columns", __function_name, pdbh->col_num);

	result = (ZBX_ODBC_RESULT)pdbh;
end:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return result;
}

#endif	/* HAVE_UNIXODBC */
