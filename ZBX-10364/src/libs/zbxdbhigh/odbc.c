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

#include "common.h"
#include "log.h"
#include "zbxodbc.h"

static char	zbx_last_odbc_strerror[255];

const char	*get_last_odbc_strerror()
{
	return zbx_last_odbc_strerror;
}

#ifdef HAVE___VA_ARGS__
#	define set_last_odbc_strerror(fmt, ...) __zbx_set_last_odbc_strerror(ZBX_CONST_STRING(fmt), ##__VA_ARGS__)
#else
#	define set_last_odbc_strerror __zbx_set_last_odbc_strerror
#endif /* HAVE___VA_ARGS__ */
static void __zbx_set_last_odbc_strerror(const char *fmt, ...)
{
	va_list args;

	va_start(args, fmt);

	zbx_vsnprintf(zbx_last_odbc_strerror, sizeof(zbx_last_odbc_strerror), fmt, args);

	va_end(args);
}

#define clean_odbc_strerror() zbx_last_odbc_strerror[0]='\0'

static void odbc_free_row_data(ZBX_ODBC_DBH *pdbh)
{
	SQLSMALLINT i;

	if(pdbh->row_data)
	{
		for(i = 0; i < pdbh->col_num; i++)
			zbx_free(pdbh->row_data[i]);

		zbx_free(pdbh->row_data);
		pdbh->row_data = NULL;
	}
	if(pdbh->data_len)
	{
		zbx_free(pdbh->data_len);
		pdbh->data_len = NULL;
	}
	pdbh->col_num = 0;
}

void	odbc_DBclose(ZBX_ODBC_DBH *pdbh)
{
	if(pdbh->hstmt)
	{
		SQLFreeHandle(SQL_HANDLE_STMT, pdbh->hstmt);
		pdbh->hstmt = NULL;
	}

	if(pdbh->hdbc)
	{
		if(pdbh->connected)
		{
			SQLDisconnect(pdbh->hdbc);
		}

		SQLFreeHandle(SQL_HANDLE_DBC, pdbh->hdbc);
		pdbh->hdbc = NULL;
	}

	if(pdbh->henv)
	{
		SQLFreeHandle(SQL_HANDLE_ENV, pdbh->henv);
		pdbh->henv = NULL;
	}

	odbc_free_row_data(pdbh);
}

int	odbc_DBconnect(ZBX_ODBC_DBH *pdbh, const char *db_dsn, const char *user, const char *pass)
{
	SQLCHAR
		err_stat[10],
		err_msg[100];

	SQLINTEGER
		err_int;

	SQLSMALLINT
		err_msg_len;

	SQLRETURN	retcode;

	clean_odbc_strerror();

	memset(pdbh, 0 , sizeof(ZBX_ODBC_DBH));

	zabbix_log(LOG_LEVEL_DEBUG, "ODBC connect [%s] [%s]", db_dsn, user);

	/*Allocate environment handle */
	retcode = SQLAllocHandle(SQL_HANDLE_ENV, SQL_NULL_HANDLE, &(pdbh->henv));
	if (retcode != SQL_SUCCESS && retcode != SQL_SUCCESS_WITH_INFO)
	{
		set_last_odbc_strerror("%s","failed environment handle allocation.");
	}
	else
	{
		/* Set the ODBC version environment attribute */
		retcode = SQLSetEnvAttr(pdbh->henv, SQL_ATTR_ODBC_VERSION, (void*)SQL_OV_ODBC3, 0);
		if (retcode != SQL_SUCCESS && retcode != SQL_SUCCESS_WITH_INFO)
		{
			set_last_odbc_strerror("%s","failed ODBC version setting.");
		}
		else
		{
			/* Allocate connection handle */
			retcode = SQLAllocHandle(SQL_HANDLE_DBC, pdbh->henv, &(pdbh->hdbc));
			if (retcode != SQL_SUCCESS && retcode != SQL_SUCCESS_WITH_INFO)
			{
				set_last_odbc_strerror("%s","failed connection handle allocation.");
			}
			else
			{
				/* Set login timeout to 5 seconds. */
				SQLSetConnectAttr(pdbh->hdbc, (SQLINTEGER)SQL_LOGIN_TIMEOUT, (SQLPOINTER)5, (SQLINTEGER)0);

				/* Connect to data source */
				retcode = SQLConnect(pdbh->hdbc,
					(SQLCHAR*) db_dsn, SQL_NTS,
					(SQLCHAR*) user, SQL_NTS,
					(SQLCHAR*) pass, SQL_NTS
					);
				if (retcode != SQL_SUCCESS && retcode != SQL_SUCCESS_WITH_INFO)
				{

					SQLGetDiagRec(SQL_HANDLE_DBC,
							pdbh->hdbc,
							1,
							err_stat,
							&err_int,
							err_msg,
							sizeof(err_msg),
							&err_msg_len
							);

					set_last_odbc_strerror("failed connection [%s] (%d)", err_msg, err_int);
				}
				else
				{
					pdbh->connected = 1;

					/* Allocate statement handle */
					retcode = SQLAllocHandle(SQL_HANDLE_STMT, pdbh->hdbc, &(pdbh->hstmt));

					if (retcode == SQL_SUCCESS || retcode == SQL_SUCCESS_WITH_INFO)
					{
						return SUCCEED;
					}
					else
					{
						SQLFreeHandle(SQL_HANDLE_STMT, pdbh->hstmt);
						pdbh->hstmt = NULL;
					}
					SQLDisconnect(pdbh->hdbc);
				}
				SQLFreeHandle(SQL_HANDLE_DBC, pdbh->hdbc);
				pdbh->hdbc = NULL;
			}
		}
		SQLFreeHandle(SQL_HANDLE_ENV, pdbh->henv);
		pdbh->henv = NULL;
	}

	zabbix_log(LOG_LEVEL_ERR, "Failed to connect to DSN '%s' : Error: %s", db_dsn, get_last_odbc_strerror());
	return FAIL; /* error */
}


int	odbc_DBexecute(ZBX_ODBC_DBH *pdbh, const char *query)
{
	SQLCHAR
		err_stat[10],
		err_msg[100];

	SQLINTEGER
		err_int;

	SQLSMALLINT
		err_msg_len;

	SQLRETURN	retcode;

	clean_odbc_strerror();

	odbc_free_row_data(pdbh);

	retcode = SQLExecDirect(pdbh->hstmt, (SQLCHAR*) query, SQL_NTS);

	if (retcode != SQL_SUCCESS && retcode != SQL_SUCCESS_WITH_INFO) goto lbl_err_exit;

	return SUCCEED;

lbl_err_exit:

	SQLGetDiagRec(SQL_HANDLE_STMT,
			pdbh->hstmt,
			1,
			err_stat,
			&err_int,
			err_msg,
			sizeof(err_msg),
			&err_msg_len
			);

	set_last_odbc_strerror("Failed select execution [%s] (%d)", err_msg, err_int);

	zabbix_log(LOG_LEVEL_ERR, "%s", get_last_odbc_strerror());

	return FAIL;
}

ZBX_ODBC_ROW	odbc_DBfetch(ZBX_ODBC_RESULT pdbh)
{
	SQLCHAR
		err_stat[10],
		err_msg[100];

	SQLINTEGER
		err_int;

	SQLSMALLINT
		err_msg_len;

	SQLRETURN	retcode;
	SQLSMALLINT     i;

	if (pdbh == NULL)	return NULL;

	clean_odbc_strerror();

	zabbix_log(LOG_LEVEL_DEBUG, "ODBC fetch");

	retcode = SQLFetch(pdbh->hstmt);
	if (retcode == SQL_ERROR) goto lbl_err_exit;

	if (retcode != SQL_SUCCESS && retcode != SQL_SUCCESS_WITH_INFO)
	{
		zabbix_log(LOG_LEVEL_DEBUG, "odbc_DBfetch [end of rows received]");
		return NULL;
	}

	for (i = 0; i < pdbh->col_num; i++)
	{
		rtrim_spaces(pdbh->row_data[i]);
		zabbix_log(LOG_LEVEL_DEBUG, "Fetched [%i col]: %s", i, pdbh->row_data[i]);
	}

	return pdbh->row_data;

lbl_err_exit:

	SQLGetDiagRec(SQL_HANDLE_STMT,
			pdbh->hstmt,
			1,
			err_stat,
			&err_int,
			err_msg,
			sizeof(err_msg),
			&err_msg_len
			);

	set_last_odbc_strerror("Failed data fetching [%s] (%d)", err_msg, err_int);

	zabbix_log(LOG_LEVEL_ERR, "%s", get_last_odbc_strerror());

	return NULL;
}

ZBX_ODBC_RESULT	odbc_DBselect(ZBX_ODBC_DBH *pdbh, const char *query)
{
	SQLCHAR
		err_stat[10],
		err_msg[100];

	SQLINTEGER
		err_int;

	SQLSMALLINT
		err_msg_len;

	SQLRETURN	retcode;
	SQLSMALLINT
		i = 0,
		col_num = 0;

	clean_odbc_strerror();

	odbc_free_row_data(pdbh);

	zabbix_log(LOG_LEVEL_DEBUG, "ODBC select [%s]", query);

	retcode = SQLExecDirect(pdbh->hstmt, (SQLCHAR*) query, SQL_NTS);

	if (retcode != SQL_SUCCESS && retcode != SQL_SUCCESS_WITH_INFO) goto lbl_err_exit;

	retcode = SQLNumResultCols(pdbh->hstmt, &col_num);
	if (retcode != SQL_SUCCESS && retcode != SQL_SUCCESS_WITH_INFO) goto lbl_err_exit;

	pdbh->col_num  = col_num;

	pdbh->row_data = zbx_malloc(pdbh->row_data, sizeof(char *) * col_num);
	memset(pdbh->row_data, 0, sizeof(char *) * col_num);

	pdbh->data_len = zbx_malloc(pdbh->data_len, sizeof(SQLINTEGER) * col_num);
	memset(pdbh->data_len, 0, sizeof(SQLINTEGER) * col_num);

	for (i = 0; i < col_num; i++)
	{
		pdbh->row_data[i] = zbx_malloc(pdbh->row_data[i], MAX_STRING_LEN);
		SQLBindCol(pdbh->hstmt, i + 1, SQL_C_CHAR, pdbh->row_data[i], MAX_STRING_LEN, &pdbh->data_len[i]);
	}

	zabbix_log(LOG_LEVEL_DEBUG, "selected %i cols", col_num);

	return (ZBX_ODBC_RESULT)pdbh;

lbl_err_exit:

	SQLGetDiagRec(SQL_HANDLE_STMT,
			pdbh->hstmt,
			1,
			err_stat,
			&err_int,
			err_msg,
			sizeof(err_msg),
			&err_msg_len
			);

	set_last_odbc_strerror("Failed selection [%s] (%d)", err_msg, err_int);

	zabbix_log(LOG_LEVEL_ERR, "%s", get_last_odbc_strerror());

	return NULL;
}
