/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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

#ifdef HAVE_UNIXODBC

#include <sql.h>
#include <sqlext.h>
#include <sqltypes.h>

#include "odbc.h"
#include "log.h"
#include "zbxjson.h"
#include "zbxalgo.h"

struct zbx_odbc_data_source
{
	SQLHENV	henv;
	SQLHDBC	hdbc;
};

typedef char	**zbx_odbc_row_t;

struct zbx_odbc_query_result
{
	SQLHSTMT	hstmt;
	SQLSMALLINT	col_num;
	zbx_odbc_row_t	row_data;
};

#define CALLODBC(fun, rc, h_type, h, msg)	(SQL_SUCCESS != (rc = (fun)) && 0 == odbc_Diag((h_type), (h), rc, (msg)))

#define ODBC_ERR_MSG_LEN	255

static char	zbx_last_odbc_strerror[ODBC_ERR_MSG_LEN];

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

static int	odbc_Diag(SQLSMALLINT h_type, SQLHANDLE h, SQLRETURN sql_rc, const char *msg)
{
	const char	*__function_name = "odbc_Diag";
	SQLCHAR		sql_state[SQL_SQLSTATE_SIZE + 1] = "", err_msg[128] = "";
	SQLINTEGER	native_err_code = 0;
	int		rec_nr = 1;
	char		rc_msg[40];		/* the longest message is "%d (unknown SQLRETURN code)" */
	char		diag_msg[ODBC_ERR_MSG_LEN] = "";
	size_t		offset = 0;

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

static void	zbx_log_odbc_connection_info(const char *function, SQLHDBC hdbc)
{
	if (SUCCEED == zabbix_check_log_level(LOG_LEVEL_DEBUG))
	{
		char		driver_name[MAX_STRING_LEN + 1], driver_ver[MAX_STRING_LEN + 1],
				db_name[MAX_STRING_LEN + 1], db_ver[MAX_STRING_LEN + 1];
		SQLRETURN	rc;

		if (0 != CALLODBC(SQLGetInfo(hdbc, SQL_DRIVER_NAME, driver_name, MAX_STRING_LEN, NULL),
				rc, SQL_HANDLE_DBC, hdbc, "Cannot obtain driver name"))
		{
			zbx_strlcpy(driver_name, "unknown", sizeof(driver_name));
		}

		if (0 != CALLODBC(SQLGetInfo(hdbc, SQL_DRIVER_VER, driver_ver, MAX_STRING_LEN, NULL),
				rc, SQL_HANDLE_DBC, hdbc, "Cannot obtain driver version"))
		{
			zbx_strlcpy(driver_ver, "unknown", sizeof(driver_ver));
		}

		if (0 != CALLODBC(SQLGetInfo(hdbc, SQL_DBMS_NAME, db_name, MAX_STRING_LEN, NULL),
				rc, SQL_HANDLE_DBC, hdbc, "Cannot obtain database name"))
		{
			zbx_strlcpy(db_name, "unknown", sizeof(db_name));
		}

		if (0 != CALLODBC(SQLGetInfo(hdbc, SQL_DBMS_VER, db_ver, MAX_STRING_LEN, NULL),
				rc, SQL_HANDLE_DBC, hdbc, "Cannot obtain database version"))
		{
			zbx_strlcpy(db_ver, "unknown", sizeof(db_ver));
		}

		zabbix_log(LOG_LEVEL_DEBUG, "%s() connected to %s(%s) using %s(%s)", function,
				db_name, db_ver, driver_name, driver_ver);
	}
}

zbx_odbc_data_source_t	*zbx_odbc_connect(const char *dsn, const char *user, const char *pass, int timeout, char **error)
{
	const char		*__function_name = "zbx_odbc_connect";
	zbx_odbc_data_source_t	*data_source = NULL;
	SQLRETURN		rc;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() dsn:'%s' user:'%s'", __function_name, dsn, user);

	data_source = zbx_malloc(data_source, sizeof(zbx_odbc_data_source_t));

	if (0 != SQL_SUCCEEDED(SQLAllocHandle(SQL_HANDLE_ENV, SQL_NULL_HANDLE, &data_source->henv)))
	{
		if (0 == CALLODBC(SQLSetEnvAttr(data_source->henv, SQL_ATTR_ODBC_VERSION, (void*)SQL_OV_ODBC3, 0),
				rc, SQL_HANDLE_ENV, data_source->henv,
				"Cannot set ODBC version"))
		{
			if(0 == CALLODBC(SQLAllocHandle(SQL_HANDLE_DBC, data_source->henv, &data_source->hdbc),
					rc, SQL_HANDLE_ENV, data_source->henv,
					"Cannot create ODBC connection handle"))
			{
				if (0 == CALLODBC(SQLSetConnectAttr(data_source->hdbc, (SQLINTEGER)SQL_LOGIN_TIMEOUT,
						(SQLPOINTER)(intptr_t)timeout, (SQLINTEGER)0),
						rc, SQL_HANDLE_DBC, data_source->hdbc,
						"Cannot set ODBC login timeout"))
				{
					if (0 == CALLODBC(SQLConnect(data_source->hdbc, (SQLCHAR *)dsn, SQL_NTS,
							(SQLCHAR *)user, SQL_NTS, (SQLCHAR *)pass, SQL_NTS),
							rc, SQL_HANDLE_DBC, data_source->hdbc,
							"Cannot connect to ODBC DSN"))
					{
						zbx_log_odbc_connection_info(__function_name, data_source->hdbc);
						goto out;
					}
				}

				SQLFreeHandle(SQL_HANDLE_DBC, data_source->hdbc);
			}
		}

		SQLFreeHandle(SQL_HANDLE_ENV, data_source->henv);
	}
	else
		set_last_odbc_strerror("Cannot create ODBC environment handle.");

	zbx_free(data_source);

	*error = zbx_strdup(*error, zbx_last_odbc_strerror);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return data_source;
}

void	zbx_odbc_data_source_free(zbx_odbc_data_source_t *data_source)
{
	SQLDisconnect(data_source->hdbc);
	SQLFreeHandle(SQL_HANDLE_DBC, data_source->hdbc);
	SQLFreeHandle(SQL_HANDLE_ENV, data_source->henv);
	zbx_free(data_source);
}

zbx_odbc_query_result_t	*zbx_odbc_select(const zbx_odbc_data_source_t *data_source, const char *query, char **error)
{
	const char		*__function_name = "zbx_odbc_select";
	zbx_odbc_query_result_t	*query_result = NULL;
	SQLRETURN		rc;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() query:'%s'", __function_name, query);

	zbx_malloc(query_result, sizeof(zbx_odbc_query_result_t));

	if (0 == CALLODBC(SQLAllocHandle(SQL_HANDLE_STMT, data_source->hdbc, &query_result->hstmt),
			rc, SQL_HANDLE_DBC, data_source->hdbc,
			"Cannot create ODBC statement handle."))
	{
		if (0 == CALLODBC(SQLExecDirect(query_result->hstmt, (SQLCHAR *)query, SQL_NTS),
				rc, SQL_HANDLE_STMT, query_result->hstmt,
				"Cannot execute ODBC query"))
		{
			if (0 == CALLODBC(SQLNumResultCols(query_result->hstmt, &query_result->col_num),
					rc, SQL_HANDLE_STMT, query_result->hstmt,
					"Cannot get number of columns in ODBC result"))
			{
				query_result->row_data = zbx_malloc(NULL, sizeof(char *) * (size_t)query_result->col_num);
				memset(query_result->row_data, 0, sizeof(char *) * (size_t)query_result->col_num);
				zabbix_log(LOG_LEVEL_DEBUG, "%s() selected %i columns", __function_name,
						query_result->col_num);
				goto out;
			}
		}

		SQLFreeHandle(SQL_HANDLE_STMT, query_result->hstmt);
	}

	zbx_free(query_result);

	*error = zbx_strdup(*error, zbx_last_odbc_strerror);
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return query_result;
}

void	zbx_odbc_query_result_free(zbx_odbc_query_result_t *query_result)
{
	SQLSMALLINT	i;

	SQLFreeHandle(SQL_HANDLE_STMT, query_result->hstmt);

	for (i = 0; i < query_result->col_num; i++)
		zbx_free(query_result->row_data[i]);

	zbx_free(query_result->row_data);
	zbx_free(query_result);
}

static zbx_odbc_row_t	zbx_odbc_fetch(zbx_odbc_query_result_t *query_result)
{
	const char	*__function_name = "zbx_odbc_fetch";
	SQLRETURN	retcode;
	SQLSMALLINT	i;
	zbx_odbc_row_t	row = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SQL_NO_DATA == (retcode = SQLFetch(query_result->hstmt)))
	{
		/* end of rows */
		goto out;
	}

	if (SQL_SUCCESS != retcode && 0 == odbc_Diag(SQL_HANDLE_STMT, query_result->hstmt, retcode, "cannot fetch row"))
		goto out;

	for (i = 0; i < query_result->col_num; i++)
	{
		size_t		alloc = 0, offset = 0;
		char		buffer[MAX_STRING_LEN + 1];
		SQLLEN		len, col_type;
		SQLSMALLINT	c_type;

		zbx_free(query_result->row_data[i]);

		if (0 != CALLODBC(SQLColAttribute(query_result->hstmt, i + 1, SQL_DESC_TYPE, NULL, 0, NULL, &col_type),
				retcode, SQL_HANDLE_STMT, query_result->hstmt, "Cannot get column type"))
		{
			goto out;
		}

		/* force col_type to integer value for DB2 compatibility */
		switch ((int)col_type)
		{
			case SQL_WLONGVARCHAR:
				c_type = SQL_C_DEFAULT;
				break;
			default:
				c_type = SQL_C_CHAR;
		}

		/* force len to integer value for DB2 compatibility */
		do
		{
			retcode = SQLGetData(query_result->hstmt, i + 1, c_type, buffer, MAX_STRING_LEN, &len);

			if (0 == SQL_SUCCEEDED(retcode))
			{
				odbc_Diag(SQL_HANDLE_STMT, query_result->hstmt, retcode, "Cannot get column data");
				goto out;
			}

			if (SQL_NULL_DATA == (int)len)
				break;

			buffer[(int)len] = '\0';

			zbx_strcpy_alloc(&query_result->row_data[i], &alloc, &offset, buffer);
		}
		while (SQL_SUCCESS != retcode);

		if (NULL != query_result->row_data[i])
			zbx_rtrim(query_result->row_data[i], " ");

		zabbix_log(LOG_LEVEL_DEBUG, "%s() fetched [%i col (%d)]: '%s'", __function_name, i, (int)col_type,
						NULL == query_result->row_data[i] ? "NULL" : query_result->row_data[i]);
	}

	row = query_result->row_data;
out:
	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return row;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_odbc_query_result_to_string                                  *
 *                                                                            *
 * Purpose: extract the first column of the first row of ODBC SQL query       *
 *                                                                            *
 * Parameters: query_result - [IN] result of SQL query                        *
 *             string       - [OUT] the first column of the first row         *
 *             error        - [OUT] error message                             *
 *                                                                            *
 * Return value: SUCCEED - result wasn't empty, the first column of the first *
 *                         result row is not NULL and is returned in string   *
 *                         parameter, error remains untouched in this case    *
 *               FAIL    - otherwise, allocated error message is returned in  *
 *                         error parameter, string remains untouched          *
 *                                                                            *
 * Comments: It is caller's responsibility to free allocated buffers!         *
 *                                                                            *
 ******************************************************************************/
int	zbx_odbc_query_result_to_string(zbx_odbc_query_result_t *query_result, char **string, char **error)
{
	const char	*__function_name = "zbx_odbc_query_result_to_string";
	zbx_odbc_row_t	row;
	int		ret = FAIL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (NULL != (row = zbx_odbc_fetch(query_result)))
	{
		if (NULL != row[0])
		{
			*string = zbx_strdup(*string, row[0]);
			zbx_replace_invalid_utf8(*string);
			ret = SUCCEED;
		}
		else
			*error = zbx_strdup(*error, "SQL query returned NULL value.");
	}
	else
		*error = zbx_strdup(*error, "SQL query returned empty result.");

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_odbc_query_result_to_lld_json                                *
 *                                                                            *
 * Purpose: convert ODBC SQL query result into low level discovery data       *
 *                                                                            *
 * Parameters: query_result - [IN] result of SQL query                        *
 *             lld_json     - [OUT] low level discovery data                  *
 *             error        - [OUT] error message                             *
 *                                                                            *
 * Return value: SUCCEED - conversion was successful and allocated LLD JSON   *
 *                         is returned in lld_json parameter, error remains   *
 *                         untouched in this case                             *
 *               FAIL    - otherwise, allocated error message is returned in  *
 *                         error parameter, lld_json remains untouched        *
 *                                                                            *
 * Comments: It is caller's responsibility to free allocated buffers!         *
 *                                                                            *
 ******************************************************************************/
int	zbx_odbc_query_result_to_lld_json(zbx_odbc_query_result_t *query_result, char **lld_json, char **error)
{
	const char		*__function_name = "zbx_odbc_query_result_to_lld_json";
	zbx_odbc_row_t		row;
	struct zbx_json		json;
	zbx_vector_str_t	macros;
	int			ret = FAIL, i, j;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	zbx_vector_str_create(&macros);
	zbx_vector_str_reserve(&macros, query_result->col_num);

	for (i = 0; i < query_result->col_num; i++)
	{
		char		str[MAX_STRING_LEN], *p;
		SQLRETURN	rc;
		SQLSMALLINT	len;

		rc = SQLColAttribute(query_result->hstmt, i + 1, SQL_DESC_LABEL, str, sizeof(str), &len, NULL);

		if (SQL_SUCCESS != rc || sizeof(str) <= (size_t)len || '\0' == *str)
		{
			*error = zbx_dsprintf(*error, "Cannot obtain column #%d name.", i + 1);
			goto out;
		}

		zabbix_log(LOG_LEVEL_DEBUG, "column #%d name:'%s'", i + 1, str);

		for (p = str; '\0' != *p; p++)
		{
			if (0 != isalpha((unsigned char)*p))
				*p = toupper((unsigned char)*p);

			if (SUCCEED != is_macro_char(*p))
			{
				*error = zbx_dsprintf(*error, "Cannot convert column #%d name to macro.", i + 1);
				goto out;
			}
		}

		zbx_vector_str_append(&macros, zbx_dsprintf(NULL, "{#%s}", str));

		for (j = 0; j < i; j++)
		{
			if (0 == strcmp(macros.values[i], macros.values[j]))
			{
				*error = zbx_dsprintf(*error, "Duplicate macro name: %s.", macros.values[i]);
				goto out;
			}
		}
	}

	zbx_json_init(&json, ZBX_JSON_STAT_BUF_LEN);
	zbx_json_addarray(&json, ZBX_PROTO_TAG_DATA);

	while (NULL != (row = zbx_odbc_fetch(query_result)))
	{
		zbx_json_addobject(&json, NULL);

		for (i = 0; i < query_result->col_num; i++)
		{
			char	*value = NULL;

			value = zbx_strdup(value, row[i]);
			zbx_replace_invalid_utf8(value);
			zbx_json_addstring(&json, macros.values[i], value, ZBX_JSON_TYPE_STRING);
			zbx_free(value);
		}

		zbx_json_close(&json);
	}

	zbx_json_close(&json);

	*lld_json = zbx_strdup(*lld_json, json.buffer);

	zbx_json_free(&json);

	ret = SUCCEED;
out:
	zbx_vector_str_destroy(&macros);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s():%s", __function_name, zbx_result_string(ret));

	return ret;
}

#endif	/* HAVE_UNIXODBC */
