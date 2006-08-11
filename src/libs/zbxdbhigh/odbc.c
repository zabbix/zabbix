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
#include "zlog.h"
#include "zodbc.h"

static void odbc_free_row_data(ZBX_ODBC_DBH *pdbh)
{
	SQLSMALLINT i;

	if(pdbh->row_data)
	{
		for(i = 0; i < pdbh->col_num; i++)	free(pdbh->row_data[i]);	
		free(pdbh->row_data);	
		pdbh->row_data = NULL;
	}
	if(pdbh->data_len)
	{
		free(pdbh->data_len);
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

int	odbc_DBconnect(ZBX_ODBC_DBH *pdbh, char *db_name, char *user, char *pass)
{
	char 	
	 	err_stat[10],
	 	err_msg[100],
		error_descr[256];

	SQLINTEGER 
		err_int, 
		err_msg_len;
	
	SQLRETURN	retcode;
	
	memset(pdbh, 0 , sizeof(ZBX_ODBC_DBH));
	memset(error_descr, 0, sizeof(error_descr));
	
	zabbix_log(LOG_LEVEL_DEBUG, "ODBC connect [%s] [%s]", db_name, user);

	/*Allocate environment handle */
	retcode = SQLAllocHandle(SQL_HANDLE_ENV, SQL_NULL_HANDLE, &(pdbh->henv));
	if (retcode != SQL_SUCCESS && retcode != SQL_SUCCESS_WITH_INFO)
	{
		snprintf(error_descr,sizeof(error_descr),"%s","failed environment handle allocation.");
	}
	else
	{
		/* Set the ODBC version environment attribute */
		retcode = SQLSetEnvAttr(pdbh->henv, SQL_ATTR_ODBC_VERSION, (void*)SQL_OV_ODBC3, 0); 
		if (retcode != SQL_SUCCESS && retcode != SQL_SUCCESS_WITH_INFO)
		{
			snprintf(error_descr,sizeof(error_descr),"%s","failed ODBC version setting.");
		}
		else
		{
			/* Allocate connection handle */
			retcode = SQLAllocHandle(SQL_HANDLE_DBC, pdbh->henv, &(pdbh->hdbc)); 
			if (retcode != SQL_SUCCESS && retcode != SQL_SUCCESS_WITH_INFO)
			{
				snprintf(error_descr,sizeof(error_descr),"%s","failed connection handle allocation.");
			}
			else
			{
				/* Set login timeout to 5 seconds. */
				SQLSetConnectAttr(pdbh->hdbc, (SQLINTEGER)SQL_LOGIN_TIMEOUT, (SQLPOINTER)5, (SQLINTEGER)0);

				/* Connect to data source */
				retcode = SQLConnect(pdbh->hdbc,
					(SQLCHAR*) db_name, SQL_NTS,
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
							(SQLSMALLINT *)&err_msg_len
							);
					
					snprintf(error_descr,sizeof(error_descr),"failed connection [%s] (%d)", err_msg, err_int);
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

	zabbix_log(LOG_LEVEL_ERR, "Failed to connect to database '%s' : Error: %s", db_name, error_descr);
	return FAIL; /* error */
}


int	odbc_DBexecute(ZBX_ODBC_DBH *pdbh, char *query)
{
	char 	
	 	err_stat[10],
	 	err_msg[100];

	SQLINTEGER 
		err_int, 
		err_msg_len;

	SQLRETURN	retcode;

	odbc_free_row_data(pdbh);

	retcode = SQLExecDirect(pdbh->hstmt, query, SQL_NTS);
	
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
			(SQLSMALLINT *)&err_msg_len
			);
	
	zabbix_log(LOG_LEVEL_ERR, "Failed select execution [%s] (%d)", err_msg, err_int);

	return FAIL;
}

/*
int	odbc_DBis_null(char *field)
{
	int ret = FAIL;

	if(field == NULL)	ret = SUCCEED;
#ifdef	HAVE_ORACLE
	else if(field[0] == 0)	ret = SUCCEED;
#endif
	return ret;
}

*/
ZBX_ODBC_ROW	odbc_DBfetch(ZBX_ODBC_RESULT pdbh)
{
	char 	
	 	err_stat[10],
	 	err_msg[100];

	SQLINTEGER 
		err_int, 
		err_msg_len;

	SQLRETURN	retcode;
	SQLSMALLINT     i;
	
	if(pdbh == NULL)	return NULL;

	zabbix_log(LOG_LEVEL_DEBUG, "ODBC fetch");
	
	retcode = SQLFetch(pdbh->hstmt);
	if (retcode == SQL_ERROR) goto lbl_err_exit;
	
	if (retcode != SQL_SUCCESS && retcode != SQL_SUCCESS_WITH_INFO )
	{
		zabbix_log(LOG_LEVEL_DEBUG, "odbc_DBfetch [end of rows received]");
		return NULL;
	}

	for(i=0; i < pdbh->col_num; i++)
	{
		rtrim_spaces(pdbh->row_data[i]);
		zabbix_log(LOG_LEVEL_DEBUG, "Featched [%i col]: %s", i, pdbh->row_data[i]);
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
			(SQLSMALLINT *)&err_msg_len
			);
	
	zabbix_log(LOG_LEVEL_ERR, "Failed data fetching [%s] (%d)", err_msg, err_int);

	return NULL;
}

ZBX_ODBC_RESULT	odbc_DBselect(ZBX_ODBC_DBH *pdbh, char *query)
{
	char 	
	 	err_stat[10],
	 	err_msg[100];

	SQLINTEGER 
		err_int, 
		err_msg_len;

	SQLRETURN	retcode;
	SQLSMALLINT	
		i = 0,
		col_num = 0;

	odbc_free_row_data(pdbh);

	zabbix_log(LOG_LEVEL_DEBUG, "ODBC select [%s]", query);
	
	retcode = SQLExecDirect(pdbh->hstmt, query, SQL_NTS);
	
	if (retcode != SQL_SUCCESS && retcode != SQL_SUCCESS_WITH_INFO) goto lbl_err_exit;
	
	retcode = SQLNumResultCols(pdbh->hstmt, &col_num);
	if (retcode != SQL_SUCCESS && retcode != SQL_SUCCESS_WITH_INFO) goto lbl_err_exit;

	pdbh->col_num  = col_num;
	pdbh->row_data = malloc(sizeof(char*) * col_num);
	pdbh->data_len = malloc(sizeof(SQLINTEGER) * col_num);

	for(i=0; i < col_num; i++)
	{
		pdbh->row_data[i] = malloc(sizeof(char) * MAX_STRING_LEN);
		SQLBindCol(pdbh->hstmt, i+1, SQL_C_CHAR, pdbh->row_data[i], MAX_STRING_LEN, &pdbh->data_len[i]);
	}
	
	zabbix_log(LOG_LEVEL_DEBUG, "selected %i cols", col_num);

	return (ZBX_ODBC_RESULT) pdbh;
		
lbl_err_exit:	
	
	SQLGetDiagRec(SQL_HANDLE_STMT, 
			pdbh->hstmt,
			1,
			err_stat,
			&err_int,
			err_msg,
			sizeof(err_msg),
			(SQLSMALLINT *)&err_msg_len
			);
	
	zabbix_log(LOG_LEVEL_ERR, "Failed selection[%s] (%d)", err_msg, err_int);

	return NULL;
}

/*
DB_RESULT odbc_DBselectN(char *query, int n)
{
	char sql[MAX_STRING_LEN];

#ifdef	HAVE_ORACLE
	snprintf(sql,MAX_STRING_LEN-1,"select * from (%s) where rownum<=%d", query, n);
#else
	snprintf(sql,MAX_STRING_LEN-1,"%s limit %d", query, n);
#endif
	return odbc_DBselect(sql);
}
*/
