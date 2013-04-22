/*
** Zabbix
** Copyright (C) 2001-2013 Zabbix SIA
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
#include "zbxodbc.h"

static char	zbx_last_odbc_strerror[255];

const char	*get_last_odbc_strerror(void)
{
	return zbx_last_odbc_strerror;
}

#ifdef HAVE___VA_ARGS__
#	define set_last_odbc_strerror(fmt, ...) __zbx_set_last_odbc_strerror(ZBX_CONST_STRING(fmt), ##__VA_ARGS__)
#else
#	define set_last_odbc_strerror __zbx_set_last_odbc_strerror
#endif /* HAVE___VA_ARGS__ */
static void	__zbx_set_last_odbc_strerror(const char *fmt, ...)
{
	va_list	args;

	va_start(args, fmt);

	zbx_vsnprintf(zbx_last_odbc_strerror, sizeof(zbx_last_odbc_strerror), fmt, args);

	va_end(args);
}

#define clean_odbc_strerror() zbx_last_odbc_strerror[0]='\0'

static void	odbc_free_row_data(ZBX_ODBC_DBH *pdbh)
{
	SQLSMALLINT	i;

	if (NULL != pdbh->row_data)
	{
		for (i = 0; i < pdbh->col_num; i++)
			zbx_free(pdbh->row_data[i]);

		zbx_free(pdbh->row_data);
	}

	zbx_free(pdbh->data_len);

	pdbh->col_num = 0;
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

int	odbc_DBconnect(ZBX_ODBC_DBH *pdbh, const char *db_dsn, const char *user, const char *pass)
{
	const char	*__function_name = "odbc_DBconnect";
	SQLCHAR		err_msg[128];
	SQLINTEGER	err_int;
	int		ret = FAIL;

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
	if (0 == SQL_SUCCEEDED(SQLSetEnvAttr(pdbh->henv, SQL_ATTR_ODBC_VERSION, (void*)SQL_OV_ODBC3, 0)))
	{
		set_last_odbc_strerror("%s", "Cannot set ODBC version.");
		goto end;
	}

	/* allocate connection handle */
	if (0 == SQL_SUCCEEDED(SQLAllocHandle(SQL_HANDLE_DBC, pdbh->henv, &(pdbh->hdbc))))
	{
		set_last_odbc_strerror("%s", "Cannot create ODBC connection handle.");
		goto end;
	}

	/* set login timeout to 5 seconds */
	SQLSetConnectAttr(pdbh->hdbc, (SQLINTEGER)SQL_LOGIN_TIMEOUT, (SQLPOINTER)5, (SQLINTEGER)0);

	/* connect to data source */
	if (0 == SQL_SUCCEEDED(SQLConnect(pdbh->hdbc, (SQLCHAR *)db_dsn, SQL_NTS, (SQLCHAR *)user, SQL_NTS,
			(SQLCHAR *)pass, SQL_NTS)))
	{
		SQLGetDiagRec(SQL_HANDLE_DBC, pdbh->hdbc, 1, NULL, &err_int, err_msg, sizeof(err_msg), NULL);

		set_last_odbc_strerror("Cannot connect to ODBC DSN '%s': %s (%d).", db_dsn, err_msg, err_int);
		goto end;
	}

	/* allocate statement handle */
	if (0 == SQL_SUCCEEDED(SQLAllocHandle(SQL_HANDLE_STMT, pdbh->hdbc, &(pdbh->hstmt))))
	{
		set_last_odbc_strerror("%s", "Cannot create ODBC statement handle.");
		goto end;
	}

	pdbh->connected = 1;

	ret = SUCCEED;
end:
	if (SUCCEED != ret)
	{
		odbc_DBclose(pdbh);
		zabbix_log(LOG_LEVEL_ERR, "%s", get_last_odbc_strerror());
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

ZBX_ODBC_ROW	odbc_DBfetch(ZBX_ODBC_RESULT pdbh)
{
	const char	*__function_name = "odbc_DBfetch";
	SQLCHAR		err_msg[128];
	SQLINTEGER	err_int;
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

	if (0 == SQL_SUCCEEDED(retcode))
	{
		SQLGetDiagRec(SQL_HANDLE_STMT, pdbh->hstmt, 1, NULL, &err_int, err_msg, sizeof(err_msg), NULL);
		set_last_odbc_strerror("cannot fetch row [%s] (%d)", err_msg, err_int);
		goto end;
	}

	for (i = 0; i < pdbh->col_num; i++)
	{
		/* set NULL column value where appropriate */
		if (SQL_NULL_DATA == pdbh->data_len[i])
			zbx_free(pdbh->row_data[i]);
		else
			zbx_rtrim(pdbh->row_data[i], " ");

		zabbix_log(LOG_LEVEL_DEBUG, "%s() fetched [%i col]: '%s'", __function_name, i,
				NULL == pdbh->row_data[i] ? "NULL" : pdbh->row_data[i]);
	}

	result_row = pdbh->row_data;
end:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return result_row;
}

ZBX_ODBC_RESULT	odbc_DBselect(ZBX_ODBC_DBH *pdbh, const char *query)
{
	const char	*__function_name = "odbc_DBselect";
	SQLCHAR		err_msg[128];
	SQLINTEGER	err_int;
	SQLSMALLINT	i = 0;
	ZBX_ODBC_RESULT	result = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() query:'%s'", __function_name, query);

	clean_odbc_strerror();

	odbc_free_row_data(pdbh);

	if (0 == SQL_SUCCEEDED(SQLExecDirect(pdbh->hstmt, (SQLCHAR *)query, SQL_NTS)))
		goto end;

	if (0 == SQL_SUCCEEDED(SQLNumResultCols(pdbh->hstmt, &pdbh->col_num)))
		goto end;

	pdbh->row_data = zbx_malloc(pdbh->row_data, sizeof(char *) * pdbh->col_num);
	memset(pdbh->row_data, 0, sizeof(char *) * pdbh->col_num);

	pdbh->data_len = zbx_malloc(pdbh->data_len, sizeof(SQLLEN) * pdbh->col_num);
	memset(pdbh->data_len, 0, sizeof(SQLLEN) * pdbh->col_num);

	for (i = 0; i < pdbh->col_num; i++)
	{
		pdbh->row_data[i] = zbx_malloc(pdbh->row_data[i], MAX_STRING_LEN);
		SQLBindCol(pdbh->hstmt, i + 1, SQL_C_CHAR, pdbh->row_data[i], MAX_STRING_LEN, &pdbh->data_len[i]);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "%s() selected %i columns", __function_name, pdbh->col_num);

	result = (ZBX_ODBC_RESULT)pdbh;
end:
	if (NULL == result)
	{
		SQLGetDiagRec(SQL_HANDLE_STMT, pdbh->hstmt, 1, NULL, &err_int, err_msg, sizeof(err_msg), NULL);

		set_last_odbc_strerror("Cannot execute ODBC query: %s (%d).", err_msg, err_int);
		zabbix_log(LOG_LEVEL_ERR, "%s", get_last_odbc_strerror());
	}

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return result;
}
