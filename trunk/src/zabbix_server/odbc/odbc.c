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

struct zbx_odbc_query_result
{
	SQLHSTMT	hstmt;
	SQLSMALLINT	col_num;
	SQLSMALLINT	*c_types;
	char		**row;
};

/******************************************************************************
 *                                                                            *
 * Function: zbx_odbc_rc_str                                                  *
 *                                                                            *
 * Purpose: get human readable representation of ODBC return code             *
 *                                                                            *
 * Parameters: rc - [IN] ODBC return code                                     *
 *                                                                            *
 * Return value: human readable representation of error code or NULL if the   *
 *               given code is unknown                                        *
 *                                                                            *
 ******************************************************************************/
static const char	*zbx_odbc_rc_str(SQLRETURN rc)
{
	switch (rc)
	{
		case SQL_ERROR:
			return "SQL_ERROR";
		case SQL_SUCCESS_WITH_INFO:
			return "SQL_SUCCESS_WITH_INFO";
		case SQL_NO_DATA:
			return "SQL_NO_DATA";
		case SQL_INVALID_HANDLE:
			return "SQL_INVALID_HANDLE";
		case SQL_STILL_EXECUTING:
			return "SQL_STILL_EXECUTING";
		case SQL_NEED_DATA:
			return "SQL_NEED_DATA";
		case SQL_SUCCESS:
			return "SQL_SUCCESS";
		default:
			return NULL;
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_odbc_diag                                                    *
 *                                                                            *
 * Purpose: diagnose result of ODBC function call                             *
 *                                                                            *
 * Parameters: h_type - [IN] type of handle call was executed on              *
 *             h      - [IN] handle call was executed on                      *
 *             rc     - [IN] function return code                             *
 *             diag   - [OUT] diagnostic message                              *
 *                                                                            *
 * Return value: SUCCEED - function call was successful                       *
 *               FAIL    - otherwise, error message is returned in diag       *
 *                                                                            *
 * Comments: It is caller's responsibility to free diag in case this function *
 *           returns FAIL!                                                    *
 *                                                                            *
 ******************************************************************************/
static int	zbx_odbc_diag(SQLSMALLINT h_type, SQLHANDLE h, SQLRETURN rc, char **diag)
{
	const char	*__function_name = "zbx_odbc_diag";
	const char	*rc_str = NULL;
	char		*buffer = NULL;
	size_t		alloc = 0, offset = 0;

	if (SQL_ERROR == rc || SQL_SUCCESS_WITH_INFO == rc)
	{
		SQLCHAR		sql_state[SQL_SQLSTATE_SIZE + 1], err_msg[128];
		SQLINTEGER	err_code = 0;
		SQLSMALLINT	rec_nr = 1;

		while (0 != SQL_SUCCEEDED(SQLGetDiagRec(h_type, h, rec_nr++, sql_state, &err_code, err_msg,
				sizeof(err_msg), NULL)))
		{
			zbx_chrcpy_alloc(&buffer, &alloc, &offset, (NULL == buffer ? ':' : '|'));
			zbx_snprintf_alloc(&buffer, &alloc, &offset, "[%s][%ld][%s]", sql_state, (long)err_code, err_msg);
		}
	}

	if (0 != SQL_SUCCEEDED(rc))
	{
		if (NULL == (rc_str = zbx_odbc_rc_str(rc)))
		{
			zabbix_log(LOG_LEVEL_TRACE, "%s(): [%d (unknown SQLRETURN code)]%s", __function_name,
					(int)rc, ZBX_NULL2EMPTY_STR(buffer));
		}
		else
			zabbix_log(LOG_LEVEL_TRACE, "%s(): [%s]%s", __function_name, rc_str, ZBX_NULL2EMPTY_STR(buffer));
	}
	else
	{
		if (NULL == (rc_str = zbx_odbc_rc_str(rc)))
		{
			*diag = zbx_dsprintf(*diag, "[%d (unknown SQLRETURN code)]%s",
					(int)rc, ZBX_NULL2EMPTY_STR(buffer));
		}
		else
			*diag = zbx_dsprintf(*diag, "[%s]%s", rc_str, ZBX_NULL2EMPTY_STR(buffer));

		zabbix_log(LOG_LEVEL_TRACE, "%s(): %s", __function_name, *diag);
	}

	zbx_free(buffer);

	return 0 != SQL_SUCCEEDED(rc) ? SUCCEED : FAIL;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_log_odbc_connection_info                                     *
 *                                                                            *
 * Purpose: log details upon successful connection on behalf of caller        *
 *                                                                            *
 * Parameters: function - [IN] caller function name                           *
 *             hdbc     - [IN] ODBC connection handle                         *
 *                                                                            *
 ******************************************************************************/
static void	zbx_log_odbc_connection_info(const char *function, SQLHDBC hdbc)
{
	if (SUCCEED == zabbix_check_log_level(LOG_LEVEL_DEBUG))
	{
		char		driver_name[MAX_STRING_LEN + 1], driver_ver[MAX_STRING_LEN + 1],
				db_name[MAX_STRING_LEN + 1], db_ver[MAX_STRING_LEN + 1], *diag = NULL;
		SQLRETURN	rc;

		rc = SQLGetInfo(hdbc, SQL_DRIVER_NAME, driver_name, MAX_STRING_LEN, NULL);

		if (SUCCEED != zbx_odbc_diag(SQL_HANDLE_DBC, hdbc, rc, &diag))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Cannot obtain driver name: %s", diag);
			zbx_strlcpy(driver_name, "unknown", sizeof(driver_name));
		}

		rc = SQLGetInfo(hdbc, SQL_DRIVER_VER, driver_ver, MAX_STRING_LEN, NULL);

		if (SUCCEED != zbx_odbc_diag(SQL_HANDLE_DBC, hdbc, rc, &diag))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Cannot obtain driver version: %s", diag);
			zbx_strlcpy(driver_ver, "unknown", sizeof(driver_ver));
		}

		rc = SQLGetInfo(hdbc, SQL_DBMS_NAME, db_name, MAX_STRING_LEN, NULL);

		if (SUCCEED != zbx_odbc_diag(SQL_HANDLE_DBC, hdbc, rc, &diag))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Cannot obtain database name: %s", diag);
			zbx_strlcpy(db_name, "unknown", sizeof(db_name));
		}

		rc = SQLGetInfo(hdbc, SQL_DBMS_VER, db_ver, MAX_STRING_LEN, NULL);

		if (SUCCEED != zbx_odbc_diag(SQL_HANDLE_DBC, hdbc, rc, &diag))
		{
			zabbix_log(LOG_LEVEL_DEBUG, "Cannot obtain database version: %s", diag);
			zbx_strlcpy(db_ver, "unknown", sizeof(db_ver));
		}

		zabbix_log(LOG_LEVEL_DEBUG, "%s() connected to %s(%s) using %s(%s)", function,
				db_name, db_ver, driver_name, driver_ver);
		zbx_free(diag);
	}
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_odbc_connect                                                 *
 *                                                                            *
 * Purpose: connect to ODBC data source                                       *
 *                                                                            *
 * Parameters: dsn     - [IN] data source name                                *
 *             user    - [IN] user name                                       *
 *             pass    - [IN] password                                        *
 *             timeout - [IN] timeout                                         *
 *             error   - [OUT] error message                                  *
 *                                                                            *
 * Return value: pointer to opaque data source data structure or NULL in case *
 *               of failure, allocated error message is returned in error     *
 *                                                                            *
 * Comments: It is caller's responsibility to free error buffer!              *
 *                                                                            *
 ******************************************************************************/
zbx_odbc_data_source_t	*zbx_odbc_connect(const char *dsn, const char *user, const char *pass, int timeout, char **error)
{
	const char		*__function_name = "zbx_odbc_connect";
	char			*diag = NULL;
	zbx_odbc_data_source_t	*data_source = NULL;
	SQLRETURN		rc;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() dsn:'%s' user:'%s'", __function_name, dsn, user);

	data_source = (zbx_odbc_data_source_t *)zbx_malloc(data_source, sizeof(zbx_odbc_data_source_t));

	if (0 != SQL_SUCCEEDED(SQLAllocHandle(SQL_HANDLE_ENV, SQL_NULL_HANDLE, &data_source->henv)))
	{
		rc = SQLSetEnvAttr(data_source->henv, SQL_ATTR_ODBC_VERSION, (void *)SQL_OV_ODBC3, 0);

		if (SUCCEED == zbx_odbc_diag(SQL_HANDLE_ENV, data_source->henv, rc, &diag))
		{
			rc = SQLAllocHandle(SQL_HANDLE_DBC, data_source->henv, &data_source->hdbc);

			if(SUCCEED == zbx_odbc_diag(SQL_HANDLE_ENV, data_source->henv, rc, &diag))
			{
				rc = SQLSetConnectAttr(data_source->hdbc, (SQLINTEGER)SQL_LOGIN_TIMEOUT,
						(SQLPOINTER)(intptr_t)timeout, (SQLINTEGER)0);

				if (SUCCEED == zbx_odbc_diag(SQL_HANDLE_DBC, data_source->hdbc, rc, &diag))
				{
					rc = SQLConnect(data_source->hdbc, (SQLCHAR *)dsn, SQL_NTS, (SQLCHAR *)user,
							SQL_NTS, (SQLCHAR *)pass, SQL_NTS);

					if (SUCCEED == zbx_odbc_diag(SQL_HANDLE_DBC, data_source->hdbc, rc, &diag))
					{
						zbx_log_odbc_connection_info(__function_name, data_source->hdbc);
						goto out;
					}

					*error = zbx_dsprintf(*error, "Cannot connect to ODBC DSN: %s", diag);
				}
				else
					*error = zbx_dsprintf(*error, "Cannot set ODBC login timeout: %s", diag);

				SQLFreeHandle(SQL_HANDLE_DBC, data_source->hdbc);
			}
			else
				*error = zbx_dsprintf(*error, "Cannot create ODBC connection handle: %s", diag);
		}
		else
			*error = zbx_dsprintf(*error, "Cannot set ODBC version: %s", diag);

		SQLFreeHandle(SQL_HANDLE_ENV, data_source->henv);
	}
	else
		*error = zbx_strdup(*error, "Cannot create ODBC environment handle.");

	zbx_free(data_source);
out:
	zbx_free(diag);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return data_source;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_odbc_data_source_free                                        *
 *                                                                            *
 * Purpose: free resources allocated by successful zbx_odbc_connect() call    *
 *                                                                            *
 * Parameters: data_source - [IN] pointer to data source structure            *
 *                                                                            *
 * Comments: Input parameter data_source must be obtained using               *
 *           zbx_odbc_connect() and must not be NULL.                         *
 *                                                                            *
 ******************************************************************************/
void	zbx_odbc_data_source_free(zbx_odbc_data_source_t *data_source)
{
	SQLDisconnect(data_source->hdbc);
	SQLFreeHandle(SQL_HANDLE_DBC, data_source->hdbc);
	SQLFreeHandle(SQL_HANDLE_ENV, data_source->henv);
	zbx_free(data_source);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_odbc_select                                                  *
 *                                                                            *
 * Purpose: execute a query to ODBC data source                               *
 *                                                                            *
 * Parameters: data_source - [IN] pointer to data source structure            *
 *             query       - [IN] SQL query                                   *
 *             error       - [OUT] error message                              *
 *                                                                            *
 * Return value: pointer to opaque query result structure or NULL in case of  *
 *               failure, allocated error message is returned in error        *
 *                                                                            *
 * Comments: It is caller's responsibility to free error buffer!              *
 *                                                                            *
 ******************************************************************************/
zbx_odbc_query_result_t	*zbx_odbc_select(const zbx_odbc_data_source_t *data_source, const char *query, char **error)
{
	const char		*__function_name = "zbx_odbc_select";
	char			*diag = NULL;
	zbx_odbc_query_result_t	*query_result = NULL;
	SQLRETURN		rc;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s() query:'%s'", __function_name, query);

	query_result = (zbx_odbc_query_result_t *)zbx_malloc(query_result, sizeof(zbx_odbc_query_result_t));

	rc = SQLAllocHandle(SQL_HANDLE_STMT, data_source->hdbc, &query_result->hstmt);

	if (SUCCEED == zbx_odbc_diag(SQL_HANDLE_DBC, data_source->hdbc, rc, &diag))
	{
		rc = SQLExecDirect(query_result->hstmt, (SQLCHAR *)query, SQL_NTS);

		if (SUCCEED == zbx_odbc_diag(SQL_HANDLE_STMT, query_result->hstmt, rc, &diag))
		{
			rc = SQLNumResultCols(query_result->hstmt, &query_result->col_num);

			if (SUCCEED == zbx_odbc_diag(SQL_HANDLE_STMT, query_result->hstmt, rc, &diag))
			{
				SQLSMALLINT	i;
				SQLLEN		col_type;

				query_result->c_types = (SQLSMALLINT *)zbx_malloc(NULL,
						sizeof(SQLSMALLINT) * (size_t)query_result->col_num);
				query_result->row = (char **)zbx_malloc(NULL, sizeof(char *) * (size_t)query_result->col_num);

				for (i = 0; ; i++)
				{
					if (i == query_result->col_num)
					{
						zabbix_log(LOG_LEVEL_DEBUG, "selected all %d columns",
								(int)query_result->col_num);
						goto out;
					}

					rc = SQLColAttribute(query_result->hstmt, i + 1, SQL_DESC_TYPE, NULL, 0, NULL,
							&col_type);

					if (SUCCEED != zbx_odbc_diag(SQL_HANDLE_STMT, query_result->hstmt, rc, &diag))
					{
						*error = zbx_dsprintf(*error, "Cannot get column type: %s", diag);
						zbx_free(query_result->c_types);
						zbx_free(query_result->row);
						break;
					}

					zabbix_log(LOG_LEVEL_DEBUG, "column #%d type:%d", (int)i + 1, (int)col_type);

					/* force col_type to integer value for DB2 compatibility */
					query_result->c_types[i] = (SQL_WLONGVARCHAR == (int)col_type ?
							SQL_C_DEFAULT : SQL_C_CHAR);
					query_result->row[i] = NULL;
				}
			}
			else
				*error = zbx_dsprintf(*error, "Cannot get number of columns in ODBC result: %s", diag);
		}
		else
			*error = zbx_dsprintf(*error, "Cannot execute ODBC query: %s", diag);

		SQLFreeHandle(SQL_HANDLE_STMT, query_result->hstmt);
	}
	else
		*error = zbx_dsprintf(*error, "Cannot create ODBC statement handle: %s", diag);

	zbx_free(query_result);
out:
	zbx_free(diag);

	zabbix_log(LOG_LEVEL_DEBUG, "End of %s()", __function_name);

	return query_result;
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_odbc_query_result_free                                       *
 *                                                                            *
 * Purpose: free resources allocated by successful zbx_odbc_select() call     *
 *                                                                            *
 * Parameters: query_result - [IN] pointer to query result structure          *
 *                                                                            *
 * Comments: Input parameter query_result must be obtained using              *
 *           zbx_odbc_select() and must not be NULL.                          *
 *                                                                            *
 ******************************************************************************/
void	zbx_odbc_query_result_free(zbx_odbc_query_result_t *query_result)
{
	SQLSMALLINT	i;

	SQLFreeHandle(SQL_HANDLE_STMT, query_result->hstmt);
	zbx_free(query_result->c_types);

	for (i = 0; i < query_result->col_num; i++)
		zbx_free(query_result->row[i]);

	zbx_free(query_result->row);
	zbx_free(query_result);
}

/******************************************************************************
 *                                                                            *
 * Function: zbx_odbc_fetch                                                   *
 *                                                                            *
 * Purpose: fetch single row of ODBC query result                             *
 *                                                                            *
 * Parameters: query_result - [IN] pointer to query result structure          *
 *                                                                            *
 * Return value: array of strings or NULL (see Comments)                      *
 *                                                                            *
 * Comments: NULL result can signify both end of rows (which is normal) and   *
 *           failure. There is currently no way to distinguish these cases.   *
 *           There is no need to free strings returned by this function.      *
 *           Lifetime of strings is limited to next call of zbx_odbc_fetch()  *
 *           or zbx_odbc_query_result_free(), caller needs to make a copy if  *
 *           result is needed for longer.                                     *
 *                                                                            *
 ******************************************************************************/
static const char	*const *zbx_odbc_fetch(zbx_odbc_query_result_t *query_result)
{
	const char	*__function_name = "zbx_odbc_fetch";
	char		*diag = NULL;
	SQLRETURN	rc;
	SQLSMALLINT	i;
	const char	*const *row = NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "In %s()", __function_name);

	if (SQL_NO_DATA == (rc = SQLFetch(query_result->hstmt)))
	{
		/* end of rows */
		goto out;
	}

	if (SUCCEED != zbx_odbc_diag(SQL_HANDLE_STMT, query_result->hstmt, rc, &diag))
	{
		zabbix_log(LOG_LEVEL_DEBUG, "Cannot fetch row: %s", diag);
		goto out;
	}

	for (i = 0; i < query_result->col_num; i++)
	{
		size_t		alloc = 0, offset = 0;
		char		buffer[MAX_STRING_LEN + 1];
		SQLLEN		len;

		zbx_free(query_result->row[i]);

		/* force len to integer value for DB2 compatibility */
		do
		{
			rc = SQLGetData(query_result->hstmt, i + 1, query_result->c_types[i], buffer, MAX_STRING_LEN, &len);

			if (SUCCEED != zbx_odbc_diag(SQL_HANDLE_STMT, query_result->hstmt, rc, &diag))
			{
				zabbix_log(LOG_LEVEL_DEBUG, "Cannot get column data: %s", diag);
				goto out;
			}

			if (SQL_NULL_DATA == (int)len)
				break;

			buffer[(int)len] = '\0';

			zbx_strcpy_alloc(&query_result->row[i], &alloc, &offset, buffer);
		}
		while (SQL_SUCCESS != rc);

		if (NULL != query_result->row[i])
			zbx_rtrim(query_result->row[i], " ");

		zabbix_log(LOG_LEVEL_DEBUG, "column #%d value:'%s'", (int)i + 1, ZBX_NULL2STR(query_result->row[i]));
	}

	row = (const char *const *)query_result->row;
out:
	zbx_free(diag);

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
	const char	*const *row;
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
	const char		*const *row;
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
